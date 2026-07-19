-- Track plan renewal and expiration as separate lifecycle fields.
ALTER TABLE public.subscriptions
  ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;

-- Backfill existing records so historical plans preserve prior behavior.
UPDATE public.subscriptions
SET expires_at = current_period_end_at
WHERE expires_at IS NULL
  AND current_period_end_at IS NOT NULL;

COMMENT ON COLUMN public.subscriptions.current_period_end_at IS
  'Lifecycle renewal date for the subscription.';

COMMENT ON COLUMN public.subscriptions.expires_at IS
  'Lifecycle expiration date for the subscription.';

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
    expires_at,
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
      ELSE 'pending_validation'::public.subscription_status
    END,
    NEW.created_at,
    NEW.renewal_at,
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
      ELSE public.subscriptions.status
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
    expires_at = COALESCE(
      GREATEST(public.subscriptions.expires_at, EXCLUDED.expires_at),
      public.subscriptions.expires_at,
      EXCLUDED.expires_at
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
