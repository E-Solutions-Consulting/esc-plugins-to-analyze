-- Re-introduce subscriptions as the lifecycle entity and link orders to subscriptions.
-- Data ownership split:
--   - subscriptions: lifecycle-level fields shared across renewals.
--   - orders: per-renewal fulfillment, payment, and provider-eligibility processing.

CREATE TABLE IF NOT EXISTS public.subscriptions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  product_id UUID REFERENCES public.products(id) ON DELETE SET NULL,
  status public.subscription_status NOT NULL DEFAULT 'active',
  started_at TIMESTAMPTZ,
  current_period_end_at TIMESTAMPTZ,
  paused_at TIMESTAMPTZ,
  cancelled_at TIMESTAMPTZ,
  stripe_subscription_id TEXT,
  stripe_checkout_session_id TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'subscriptions_stripe_subscription_id_key'
      AND conrelid = 'public.subscriptions'::regclass
  ) THEN
    ALTER TABLE public.subscriptions
      ADD CONSTRAINT subscriptions_stripe_subscription_id_key UNIQUE (stripe_subscription_id);
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_id ON public.subscriptions(tenant_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_patient_id ON public.subscriptions(patient_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_product_id ON public.subscriptions(product_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON public.subscriptions(status);

ALTER TABLE public.subscriptions ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'subscriptions'
      AND policyname = 'Tenant admins can manage their subscriptions'
  ) THEN
    CREATE POLICY "Tenant admins can manage their subscriptions"
      ON public.subscriptions
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'subscriptions'
      AND policyname = 'Patients can view their own subscriptions'
  ) THEN
    CREATE POLICY "Patients can view their own subscriptions"
      ON public.subscriptions
      FOR SELECT
      USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_proc
    WHERE proname = 'update_updated_at_column'
      AND pg_function_is_visible(oid)
  ) AND NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_subscriptions_updated_at'
      AND tgrelid = 'public.subscriptions'::regclass
  ) THEN
    CREATE TRIGGER update_subscriptions_updated_at
      BEFORE UPDATE ON public.subscriptions
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS subscription_id UUID;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'orders_subscription_id_fkey'
      AND conrelid = 'public.orders'::regclass
  ) THEN
    ALTER TABLE public.orders
      ADD CONSTRAINT orders_subscription_id_fkey
      FOREIGN KEY (subscription_id)
      REFERENCES public.subscriptions(id)
      ON DELETE SET NULL;
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_orders_subscription_id ON public.orders(subscription_id);

COMMENT ON COLUMN public.orders.subscription_id IS
  'Lifecycle subscription for this order. All renewals for the same patient-product lifecycle should point to the same subscription.';

COMMENT ON COLUMN public.orders.stripe_subscription_id IS
  'Deprecated lifecycle field. Use subscription_payment_provider_links.provider_subscription_id.';

COMMENT ON COLUMN public.orders.stripe_checkout_session_id IS
  'Deprecated lifecycle field. Use subscription_payment_provider_links.provider_checkout_session_id.';

CREATE OR REPLACE FUNCTION public.sync_order_to_subscription()
RETURNS TRIGGER AS $$
DECLARE
  v_subscription_id UUID;
BEGIN
  IF NEW.stripe_subscription_id IS NULL THEN
    RETURN NEW;
  END IF;

  INSERT INTO public.subscriptions (
    tenant_id,
    patient_id,
    product_id,
    status,
    started_at,
    current_period_end_at,
    paused_at,
    cancelled_at,
    stripe_subscription_id,
    stripe_checkout_session_id,
    created_at,
    updated_at
  )
  VALUES (
    NEW.tenant_id,
    NEW.patient_id,
    NEW.product_id,
    CASE
      WHEN NEW.cancelled_at IS NOT NULL THEN 'cancelled'::public.subscription_status
      WHEN NEW.paused_at IS NOT NULL THEN 'paused'::public.subscription_status
      ELSE 'active'::public.subscription_status
    END,
    NEW.created_at,
    NEW.renewal_at,
    NEW.paused_at,
    NEW.cancelled_at,
    NEW.stripe_subscription_id,
    NEW.stripe_checkout_session_id,
    COALESCE(NEW.created_at, now()),
    now()
  )
  ON CONFLICT (stripe_subscription_id) DO UPDATE
  SET
    tenant_id = EXCLUDED.tenant_id,
    patient_id = EXCLUDED.patient_id,
    product_id = COALESCE(EXCLUDED.product_id, public.subscriptions.product_id),
    status = CASE
      WHEN EXCLUDED.cancelled_at IS NOT NULL THEN 'cancelled'::public.subscription_status
      WHEN EXCLUDED.paused_at IS NOT NULL THEN 'paused'::public.subscription_status
      ELSE 'active'::public.subscription_status
    END,
    started_at = COALESCE(
      LEAST(public.subscriptions.started_at, EXCLUDED.started_at),
      public.subscriptions.started_at,
      EXCLUDED.started_at
    ),
    current_period_end_at = COALESCE(
      GREATEST(public.subscriptions.current_period_end_at, EXCLUDED.current_period_end_at),
      public.subscriptions.current_period_end_at,
      EXCLUDED.current_period_end_at
    ),
    paused_at = COALESCE(EXCLUDED.paused_at, public.subscriptions.paused_at),
    cancelled_at = COALESCE(EXCLUDED.cancelled_at, public.subscriptions.cancelled_at),
    stripe_checkout_session_id = COALESCE(
      public.subscriptions.stripe_checkout_session_id,
      EXCLUDED.stripe_checkout_session_id
    ),
    updated_at = now()
  RETURNING id INTO v_subscription_id;

  NEW.subscription_id = v_subscription_id;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public;

DROP TRIGGER IF EXISTS trigger_sync_order_to_subscription ON public.orders;

CREATE TRIGGER trigger_sync_order_to_subscription
  BEFORE INSERT OR UPDATE OF
    stripe_subscription_id,
    stripe_checkout_session_id,
    tenant_id,
    patient_id,
    product_id,
    renewal_at,
    paused_at,
    cancelled_at
  ON public.orders
  FOR EACH ROW
  EXECUTE FUNCTION public.sync_order_to_subscription();
