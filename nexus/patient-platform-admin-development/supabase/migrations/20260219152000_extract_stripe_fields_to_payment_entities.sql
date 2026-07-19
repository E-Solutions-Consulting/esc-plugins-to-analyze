-- Extract Stripe-specific payment fields into provider-agnostic payment entities.
-- This enables future payment providers without adding provider-specific columns to orders/subscriptions.

CREATE TABLE IF NOT EXISTS public.subscription_payment_provider_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  subscription_id UUID NOT NULL REFERENCES public.subscriptions(id) ON DELETE CASCADE,
  payment_provider_id UUID NOT NULL REFERENCES public.payment_providers(id) ON DELETE RESTRICT,
  provider_subscription_id TEXT,
  provider_checkout_session_id TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (subscription_id, payment_provider_id)
);

CREATE INDEX IF NOT EXISTS idx_subscription_payment_links_tenant_id
  ON public.subscription_payment_provider_links(tenant_id);

CREATE INDEX IF NOT EXISTS idx_subscription_payment_links_subscription_id
  ON public.subscription_payment_provider_links(subscription_id);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_subscription_payment_links_provider_subscription_id
  ON public.subscription_payment_provider_links(payment_provider_id, provider_subscription_id)
  WHERE provider_subscription_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_subscription_payment_links_provider_checkout_session_id
  ON public.subscription_payment_provider_links(payment_provider_id, provider_checkout_session_id)
  WHERE provider_checkout_session_id IS NOT NULL;

ALTER TABLE public.subscription_payment_provider_links ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'subscription_payment_provider_links'
      AND policyname = 'Tenant admins can manage subscription payment provider links'
  ) THEN
    CREATE POLICY "Tenant admins can manage subscription payment provider links"
      ON public.subscription_payment_provider_links
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
      AND tablename = 'subscription_payment_provider_links'
      AND policyname = 'Patients can view their own subscription payment provider links'
  ) THEN
    CREATE POLICY "Patients can view their own subscription payment provider links"
      ON public.subscription_payment_provider_links
      FOR SELECT
      USING (
        subscription_id IN (
          SELECT s.id
          FROM public.subscriptions s
          WHERE s.patient_id = public.get_patient_by_auth_id(auth.uid())
        )
      );
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.order_payment_provider_transactions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  order_id UUID NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
  subscription_id UUID REFERENCES public.subscriptions(id) ON DELETE SET NULL,
  payment_provider_id UUID NOT NULL REFERENCES public.payment_providers(id) ON DELETE RESTRICT,
  provider_payment_intent_id TEXT,
  provider_invoice_id TEXT,
  provider_charge_id TEXT,
  provider_subscription_id TEXT,
  provider_checkout_session_id TEXT,
  payment_status TEXT,
  paid_at TIMESTAMPTZ,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (order_id, payment_provider_id)
);

CREATE INDEX IF NOT EXISTS idx_order_payment_transactions_tenant_id
  ON public.order_payment_provider_transactions(tenant_id);

CREATE INDEX IF NOT EXISTS idx_order_payment_transactions_order_id
  ON public.order_payment_provider_transactions(order_id);

CREATE INDEX IF NOT EXISTS idx_order_payment_transactions_subscription_id
  ON public.order_payment_provider_transactions(subscription_id);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_order_payment_transactions_provider_invoice_id
  ON public.order_payment_provider_transactions(payment_provider_id, provider_invoice_id)
  WHERE provider_invoice_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_order_payment_transactions_provider_payment_intent_id
  ON public.order_payment_provider_transactions(payment_provider_id, provider_payment_intent_id)
  WHERE provider_payment_intent_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_order_payment_transactions_provider_charge_id
  ON public.order_payment_provider_transactions(payment_provider_id, provider_charge_id)
  WHERE provider_charge_id IS NOT NULL;

ALTER TABLE public.order_payment_provider_transactions ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'order_payment_provider_transactions'
      AND policyname = 'Tenant admins can manage order payment transactions'
  ) THEN
    CREATE POLICY "Tenant admins can manage order payment transactions"
      ON public.order_payment_provider_transactions
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
      AND tablename = 'order_payment_provider_transactions'
      AND policyname = 'Patients can view their own order payment transactions'
  ) THEN
    CREATE POLICY "Patients can view their own order payment transactions"
      ON public.order_payment_provider_transactions
      FOR SELECT
      USING (
        order_id IN (
          SELECT o.id
          FROM public.orders o
          WHERE o.patient_id = public.get_patient_by_auth_id(auth.uid())
        )
      );
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
    WHERE tgname = 'update_subscription_payment_provider_links_updated_at'
      AND tgrelid = 'public.subscription_payment_provider_links'::regclass
  ) THEN
    CREATE TRIGGER update_subscription_payment_provider_links_updated_at
      BEFORE UPDATE ON public.subscription_payment_provider_links
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
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
    WHERE tgname = 'update_order_payment_provider_transactions_updated_at'
      AND tgrelid = 'public.order_payment_provider_transactions'::regclass
  ) THEN
    CREATE TRIGGER update_order_payment_provider_transactions_updated_at
      BEFORE UPDATE ON public.order_payment_provider_transactions
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

WITH stripe_provider AS (
  SELECT id
  FROM public.payment_providers
  WHERE key = 'stripe'
  LIMIT 1
),
subscription_source AS (
  SELECT
    s.id AS subscription_id,
    s.tenant_id,
    COALESCE(s.stripe_subscription_id, latest_order.stripe_subscription_id) AS provider_subscription_id,
    COALESCE(s.stripe_checkout_session_id, first_order.stripe_checkout_session_id) AS provider_checkout_session_id
  FROM public.subscriptions s
  LEFT JOIN LATERAL (
    SELECT o.stripe_subscription_id
    FROM public.orders o
    WHERE o.subscription_id = s.id
      AND o.stripe_subscription_id IS NOT NULL
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT 1
  ) latest_order ON true
  LEFT JOIN LATERAL (
    SELECT o.stripe_checkout_session_id
    FROM public.orders o
    WHERE o.subscription_id = s.id
      AND o.stripe_checkout_session_id IS NOT NULL
    ORDER BY o.created_at ASC, o.id ASC
    LIMIT 1
  ) first_order ON true
  WHERE COALESCE(s.stripe_subscription_id, latest_order.stripe_subscription_id) IS NOT NULL
     OR COALESCE(s.stripe_checkout_session_id, first_order.stripe_checkout_session_id) IS NOT NULL
)
INSERT INTO public.subscription_payment_provider_links (
  tenant_id,
  subscription_id,
  payment_provider_id,
  provider_subscription_id,
  provider_checkout_session_id,
  created_at,
  updated_at
)
SELECT
  ss.tenant_id,
  ss.subscription_id,
  sp.id,
  ss.provider_subscription_id,
  ss.provider_checkout_session_id,
  now(),
  now()
FROM subscription_source ss
CROSS JOIN stripe_provider sp
ON CONFLICT (subscription_id, payment_provider_id) DO UPDATE
SET
  provider_subscription_id = COALESCE(
    EXCLUDED.provider_subscription_id,
    public.subscription_payment_provider_links.provider_subscription_id
  ),
  provider_checkout_session_id = COALESCE(
    public.subscription_payment_provider_links.provider_checkout_session_id,
    EXCLUDED.provider_checkout_session_id
  ),
  updated_at = now();

WITH stripe_provider AS (
  SELECT id
  FROM public.payment_providers
  WHERE key = 'stripe'
  LIMIT 1
)
INSERT INTO public.order_payment_provider_transactions (
  tenant_id,
  order_id,
  subscription_id,
  payment_provider_id,
  provider_payment_intent_id,
  provider_invoice_id,
  provider_charge_id,
  provider_subscription_id,
  provider_checkout_session_id,
  payment_status,
  paid_at,
  created_at,
  updated_at
)
SELECT
  o.tenant_id,
  o.id,
  o.subscription_id,
  sp.id,
  o.stripe_payment_intent_id,
  o.stripe_invoice_id,
  o.stripe_charge_id,
  COALESCE(o.stripe_subscription_id, spl.provider_subscription_id),
  COALESCE(o.stripe_checkout_session_id, spl.provider_checkout_session_id),
  o.stripe_payment_status,
  o.paid_at,
  COALESCE(o.created_at, now()),
  now()
FROM public.orders o
CROSS JOIN stripe_provider sp
LEFT JOIN public.subscription_payment_provider_links spl
  ON spl.subscription_id = o.subscription_id
 AND spl.payment_provider_id = sp.id
WHERE o.stripe_payment_intent_id IS NOT NULL
   OR o.stripe_invoice_id IS NOT NULL
   OR o.stripe_charge_id IS NOT NULL
   OR o.stripe_subscription_id IS NOT NULL
   OR o.stripe_checkout_session_id IS NOT NULL
   OR o.stripe_payment_status IS NOT NULL
   OR o.paid_at IS NOT NULL
ON CONFLICT (order_id, payment_provider_id) DO UPDATE
SET
  subscription_id = COALESCE(
    EXCLUDED.subscription_id,
    public.order_payment_provider_transactions.subscription_id
  ),
  provider_payment_intent_id = COALESCE(
    EXCLUDED.provider_payment_intent_id,
    public.order_payment_provider_transactions.provider_payment_intent_id
  ),
  provider_invoice_id = COALESCE(
    EXCLUDED.provider_invoice_id,
    public.order_payment_provider_transactions.provider_invoice_id
  ),
  provider_charge_id = COALESCE(
    EXCLUDED.provider_charge_id,
    public.order_payment_provider_transactions.provider_charge_id
  ),
  provider_subscription_id = COALESCE(
    EXCLUDED.provider_subscription_id,
    public.order_payment_provider_transactions.provider_subscription_id
  ),
  provider_checkout_session_id = COALESCE(
    EXCLUDED.provider_checkout_session_id,
    public.order_payment_provider_transactions.provider_checkout_session_id
  ),
  payment_status = COALESCE(
    EXCLUDED.payment_status,
    public.order_payment_provider_transactions.payment_status
  ),
  paid_at = COALESCE(
    EXCLUDED.paid_at,
    public.order_payment_provider_transactions.paid_at
  ),
  updated_at = now();

CREATE OR REPLACE FUNCTION public.sync_legacy_stripe_fields_to_payment_entities()
RETURNS TRIGGER AS $$
DECLARE
  v_stripe_provider_id UUID;
BEGIN
  SELECT id
  INTO v_stripe_provider_id
  FROM public.payment_providers
  WHERE key = 'stripe'
  LIMIT 1;

  IF v_stripe_provider_id IS NULL THEN
    RETURN NEW;
  END IF;

  IF NEW.subscription_id IS NOT NULL
     AND (NEW.stripe_subscription_id IS NOT NULL OR NEW.stripe_checkout_session_id IS NOT NULL) THEN
    INSERT INTO public.subscription_payment_provider_links (
      tenant_id,
      subscription_id,
      payment_provider_id,
      provider_subscription_id,
      provider_checkout_session_id,
      created_at,
      updated_at
    )
    VALUES (
      NEW.tenant_id,
      NEW.subscription_id,
      v_stripe_provider_id,
      NEW.stripe_subscription_id,
      NEW.stripe_checkout_session_id,
      now(),
      now()
    )
    ON CONFLICT (subscription_id, payment_provider_id) DO UPDATE
    SET
      provider_subscription_id = COALESCE(
        EXCLUDED.provider_subscription_id,
        public.subscription_payment_provider_links.provider_subscription_id
      ),
      provider_checkout_session_id = COALESCE(
        public.subscription_payment_provider_links.provider_checkout_session_id,
        EXCLUDED.provider_checkout_session_id
      ),
      updated_at = now();
  END IF;

  IF NEW.stripe_payment_intent_id IS NOT NULL
     OR NEW.stripe_invoice_id IS NOT NULL
     OR NEW.stripe_charge_id IS NOT NULL
     OR NEW.stripe_subscription_id IS NOT NULL
     OR NEW.stripe_checkout_session_id IS NOT NULL
     OR NEW.stripe_payment_status IS NOT NULL
     OR NEW.paid_at IS NOT NULL THEN
    INSERT INTO public.order_payment_provider_transactions (
      tenant_id,
      order_id,
      subscription_id,
      payment_provider_id,
      provider_payment_intent_id,
      provider_invoice_id,
      provider_charge_id,
      provider_subscription_id,
      provider_checkout_session_id,
      payment_status,
      paid_at,
      created_at,
      updated_at
    )
    VALUES (
      NEW.tenant_id,
      NEW.id,
      NEW.subscription_id,
      v_stripe_provider_id,
      NEW.stripe_payment_intent_id,
      NEW.stripe_invoice_id,
      NEW.stripe_charge_id,
      NEW.stripe_subscription_id,
      NEW.stripe_checkout_session_id,
      NEW.stripe_payment_status,
      NEW.paid_at,
      COALESCE(NEW.created_at, now()),
      now()
    )
    ON CONFLICT (order_id, payment_provider_id) DO UPDATE
    SET
      subscription_id = COALESCE(
        EXCLUDED.subscription_id,
        public.order_payment_provider_transactions.subscription_id
      ),
      provider_payment_intent_id = COALESCE(
        EXCLUDED.provider_payment_intent_id,
        public.order_payment_provider_transactions.provider_payment_intent_id
      ),
      provider_invoice_id = COALESCE(
        EXCLUDED.provider_invoice_id,
        public.order_payment_provider_transactions.provider_invoice_id
      ),
      provider_charge_id = COALESCE(
        EXCLUDED.provider_charge_id,
        public.order_payment_provider_transactions.provider_charge_id
      ),
      provider_subscription_id = COALESCE(
        EXCLUDED.provider_subscription_id,
        public.order_payment_provider_transactions.provider_subscription_id
      ),
      provider_checkout_session_id = COALESCE(
        EXCLUDED.provider_checkout_session_id,
        public.order_payment_provider_transactions.provider_checkout_session_id
      ),
      payment_status = COALESCE(
        EXCLUDED.payment_status,
        public.order_payment_provider_transactions.payment_status
      ),
      paid_at = COALESCE(
        EXCLUDED.paid_at,
        public.order_payment_provider_transactions.paid_at
      ),
      updated_at = now();
  END IF;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public;

DROP TRIGGER IF EXISTS trigger_sync_legacy_stripe_fields_to_payment_entities ON public.orders;

CREATE TRIGGER trigger_sync_legacy_stripe_fields_to_payment_entities
  AFTER INSERT OR UPDATE OF
    subscription_id,
    stripe_subscription_id,
    stripe_checkout_session_id,
    stripe_payment_intent_id,
    stripe_invoice_id,
    stripe_charge_id,
    stripe_payment_status,
    paid_at
  ON public.orders
  FOR EACH ROW
  EXECUTE FUNCTION public.sync_legacy_stripe_fields_to_payment_entities();

COMMENT ON TABLE public.subscription_payment_provider_links IS
  'Provider-agnostic subscription payment linkage (Stripe and future providers).';

COMMENT ON TABLE public.order_payment_provider_transactions IS
  'Provider-agnostic order payment transaction snapshot (invoice/intent/charge/status).';

COMMENT ON COLUMN public.orders.stripe_subscription_id IS
  'Deprecated. Use subscription_payment_provider_links.provider_subscription_id.';

COMMENT ON COLUMN public.orders.stripe_checkout_session_id IS
  'Deprecated. Use subscription_payment_provider_links.provider_checkout_session_id or order_payment_provider_transactions.provider_checkout_session_id.';

COMMENT ON COLUMN public.orders.stripe_payment_intent_id IS
  'Deprecated. Use order_payment_provider_transactions.provider_payment_intent_id.';

COMMENT ON COLUMN public.orders.stripe_invoice_id IS
  'Deprecated. Use order_payment_provider_transactions.provider_invoice_id.';

COMMENT ON COLUMN public.orders.stripe_charge_id IS
  'Deprecated. Use order_payment_provider_transactions.provider_charge_id.';

COMMENT ON COLUMN public.orders.stripe_payment_status IS
  'Deprecated. Use order_payment_provider_transactions.payment_status.';

COMMENT ON COLUMN public.orders.paid_at IS
  'Deprecated for provider data. Use order_payment_provider_transactions.paid_at.';

COMMENT ON COLUMN public.subscriptions.stripe_subscription_id IS
  'Deprecated. Use subscription_payment_provider_links.provider_subscription_id.';

COMMENT ON COLUMN public.subscriptions.stripe_checkout_session_id IS
  'Deprecated. Use subscription_payment_provider_links.provider_checkout_session_id.';
