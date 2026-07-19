CREATE TABLE IF NOT EXISTS public.patient_notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  type TEXT NOT NULL,
  event_type TEXT NOT NULL,
  provider_name TEXT NOT NULL,
  provider_message_id TEXT NOT NULL,
  provider_patient_id TEXT,
  provider_order_id TEXT,
  order_id UUID REFERENCES public.orders(id) ON DELETE SET NULL,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  resource JSONB NOT NULL DEFAULT '{}'::jsonb,
  read_at TIMESTAMPTZ,
  onesignal_notification_id TEXT,
  onesignal_status INTEGER,
  onesignal_response JSONB,
  onesignal_error TEXT,
  raw_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_notifications_provider_message
  ON public.patient_notifications (tenant_id, provider_name, provider_message_id);

CREATE INDEX IF NOT EXISTS idx_patient_notifications_patient_unread
  ON public.patient_notifications (patient_id, read_at, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_patient_notifications_tenant_patient
  ON public.patient_notifications (tenant_id, patient_id);

CREATE INDEX IF NOT EXISTS idx_patient_notifications_provider_lookup
  ON public.patient_notifications (provider_name, provider_patient_id, provider_order_id);

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
    WHERE tgname = 'update_patient_notifications_updated_at'
      AND tgrelid = 'public.patient_notifications'::regclass
  ) THEN
    CREATE TRIGGER update_patient_notifications_updated_at
      BEFORE UPDATE ON public.patient_notifications
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

ALTER TABLE public.patient_notifications ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "patient_notifications_service_role_only" ON public.patient_notifications;
CREATE POLICY "patient_notifications_service_role_only"
  ON public.patient_notifications
  FOR ALL
  USING (
    (SELECT current_setting('role', true)) = 'service_role'
    OR auth.role() = 'service_role'
  );

GRANT SELECT, INSERT, UPDATE, DELETE
  ON public.patient_notifications TO service_role;

COMMENT ON TABLE public.patient_notifications IS
  'Durable patient-facing notifications created from provider chat events and other backend event streams.';
