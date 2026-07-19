-- Track subscription lifecycle events similarly to order_status_history.
-- This stores status and lifecycle date changes over time.

CREATE TABLE IF NOT EXISTS public.subscription_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  subscription_id UUID NOT NULL REFERENCES public.subscriptions(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL,
  old_status public.subscription_status,
  new_status public.subscription_status,
  old_renewal_at TIMESTAMPTZ,
  new_renewal_at TIMESTAMPTZ,
  old_expires_at TIMESTAMPTZ,
  new_expires_at TIMESTAMPTZ,
  old_paused_at TIMESTAMPTZ,
  new_paused_at TIMESTAMPTZ,
  old_cancelled_at TIMESTAMPTZ,
  new_cancelled_at TIMESTAMPTZ,
  changed_by UUID REFERENCES public.admin_users(id),
  changed_by_email TEXT,
  notes TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_subscription_events_subscription_id
  ON public.subscription_events(subscription_id);

CREATE INDEX IF NOT EXISTS idx_subscription_events_tenant_id
  ON public.subscription_events(tenant_id);

CREATE INDEX IF NOT EXISTS idx_subscription_events_patient_id
  ON public.subscription_events(patient_id);

CREATE INDEX IF NOT EXISTS idx_subscription_events_created_at
  ON public.subscription_events(created_at DESC);

ALTER TABLE public.subscription_events ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'subscription_events'
      AND policyname = 'Patients can view their own subscription events'
  ) THEN
    CREATE POLICY "Patients can view their own subscription events"
      ON public.subscription_events
      FOR SELECT
      USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'subscription_events'
      AND policyname = 'Tenant admins can manage subscription events'
  ) THEN
    CREATE POLICY "Tenant admins can manage subscription events"
      ON public.subscription_events
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

CREATE OR REPLACE FUNCTION public.log_subscription_lifecycle_event()
RETURNS TRIGGER AS $$
DECLARE
  event_type_value TEXT;
  changed_fields TEXT[] := ARRAY[]::TEXT[];
  actor_id UUID;
  actor_email TEXT;
  metadata_value JSONB := '{}'::jsonb;
BEGIN
  IF TG_OP = 'INSERT' THEN
    event_type_value := 'created';
  ELSIF TG_OP = 'UPDATE' THEN
    IF OLD.status IS DISTINCT FROM NEW.status THEN
      changed_fields := array_append(changed_fields, 'status');
    END IF;
    IF OLD.current_period_end_at IS DISTINCT FROM NEW.current_period_end_at THEN
      changed_fields := array_append(changed_fields, 'renewal_at');
    END IF;
    IF OLD.expires_at IS DISTINCT FROM NEW.expires_at THEN
      changed_fields := array_append(changed_fields, 'expires_at');
    END IF;
    IF OLD.paused_at IS DISTINCT FROM NEW.paused_at THEN
      changed_fields := array_append(changed_fields, 'paused_at');
    END IF;
    IF OLD.cancelled_at IS DISTINCT FROM NEW.cancelled_at THEN
      changed_fields := array_append(changed_fields, 'cancelled_at');
    END IF;

    IF array_length(changed_fields, 1) IS NULL THEN
      RETURN NEW;
    END IF;

    IF OLD.status IS DISTINCT FROM NEW.status THEN
      event_type_value := CASE NEW.status
        WHEN 'cancelled' THEN 'cancelled'
        WHEN 'paused' THEN 'paused'
        WHEN 'active' THEN 'resumed'
        ELSE 'status_changed'
      END;
    ELSIF OLD.current_period_end_at IS DISTINCT FROM NEW.current_period_end_at THEN
      event_type_value := 'renewal_date_changed';
    ELSIF OLD.expires_at IS DISTINCT FROM NEW.expires_at THEN
      event_type_value := 'expiration_date_changed';
    ELSE
      event_type_value := 'lifecycle_updated';
    END IF;

    metadata_value := jsonb_build_object('changed_fields', to_jsonb(changed_fields));
  ELSE
    RETURN NEW;
  END IF;

  SELECT au.id, au.email
  INTO actor_id, actor_email
  FROM public.admin_users au
  WHERE au.auth_user_id = auth.uid()
  LIMIT 1;

  INSERT INTO public.subscription_events (
    subscription_id,
    tenant_id,
    patient_id,
    event_type,
    old_status,
    new_status,
    old_renewal_at,
    new_renewal_at,
    old_expires_at,
    new_expires_at,
    old_paused_at,
    new_paused_at,
    old_cancelled_at,
    new_cancelled_at,
    changed_by,
    changed_by_email,
    metadata
  )
  VALUES (
    NEW.id,
    NEW.tenant_id,
    NEW.patient_id,
    event_type_value,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.status ELSE NULL END,
    NEW.status,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.current_period_end_at ELSE NULL END,
    NEW.current_period_end_at,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.expires_at ELSE NULL END,
    NEW.expires_at,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.paused_at ELSE NULL END,
    NEW.paused_at,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.cancelled_at ELSE NULL END,
    NEW.cancelled_at,
    actor_id,
    actor_email,
    metadata_value
  );

  RETURN NEW;
END;
$$ LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public;

DROP TRIGGER IF EXISTS trigger_log_subscription_lifecycle_event ON public.subscriptions;

CREATE TRIGGER trigger_log_subscription_lifecycle_event
AFTER INSERT OR UPDATE OF
  status,
  current_period_end_at,
  expires_at,
  paused_at,
  cancelled_at
ON public.subscriptions
FOR EACH ROW
EXECUTE FUNCTION public.log_subscription_lifecycle_event();

COMMENT ON TABLE public.subscription_events IS
  'Lifecycle event history for subscriptions (status and lifecycle date changes).';
