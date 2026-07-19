-- Migration: Create patient reminders tables
-- Supports the mobile reminders feature with OneSignal push scheduling

-- ================================================================
-- ENUM TYPES
-- ================================================================

DO $$ BEGIN
  CREATE TYPE reminder_category AS ENUM (
    'medication',
    'body',
    'energy',
    'weight'
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  CREATE TYPE reminder_frequency AS ENUM (
    'daily',
    'weekly'
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  CREATE TYPE reminder_notification_status AS ENUM (
    'scheduled',
    'delivered',
    'cancelled',
    'failed'
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ================================================================
-- patient_reminders
-- Stores reminder settings per patient. All scheduling metadata
-- is persisted here; actual push jobs live in patient_reminder_notifications.
-- ================================================================

CREATE TABLE IF NOT EXISTS public.patient_reminders (
  id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  patient_id          UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  tenant_id           UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,

  -- What kind of reminder
  category            reminder_category NOT NULL,

  -- Server-derived display name:
  --   medication → medication name, body → "Body Measure",
  --   energy → "Energy Check", weight → "Weight Check"
  title               TEXT NOT NULL,

  -- Only populated when category = 'medication'
  medication_id       UUID REFERENCES public.medications(id) ON DELETE SET NULL,

  -- Schedule
  frequency           reminder_frequency NOT NULL,
  -- 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
  -- NULL when frequency = 'daily'; required when frequency = 'weekly'
  repeat_days         INTEGER[],

  -- Local time as stored HH:MM (e.g. '09:00')
  time_local          TIME NOT NULL,

  -- IANA timezone string (e.g. 'America/New_York')
  timezone            TEXT NOT NULL,

  -- Enable / disable state
  is_enabled          BOOLEAN NOT NULL DEFAULT true,
  -- Reason for being disabled: 'user_disabled' | 'subscription_expired'
  disabled_reason     TEXT,

  -- Optional subscription lifecycle coupling.
  -- When subscription_linked = true and subscription_id is set,
  -- the reminder is auto-disabled when the subscription is cancelled/expired.
  subscription_linked BOOLEAN NOT NULL DEFAULT false,
  subscription_id     UUID REFERENCES public.subscriptions(id) ON DELETE SET NULL,

  -- Audit
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at          TIMESTAMPTZ
);

-- ================================================================
-- patient_reminder_notifications
-- Tracks each pre-scheduled OneSignal push job for a reminder.
-- One row per occurrence (e.g. each daily or weekly slot for 30 days).
-- ================================================================

CREATE TABLE IF NOT EXISTS public.patient_reminder_notifications (
  id                          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  reminder_id                 UUID NOT NULL REFERENCES public.patient_reminders(id) ON DELETE CASCADE,
  onesignal_notification_id   TEXT NOT NULL,
  scheduled_for               TIMESTAMPTZ NOT NULL,
  status                      reminder_notification_status NOT NULL DEFAULT 'scheduled',
  created_at                  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ================================================================
-- INDEXES
-- ================================================================

-- Primary query pattern: list patient's active reminders
CREATE INDEX IF NOT EXISTS idx_patient_reminders_patient_tenant
  ON public.patient_reminders (patient_id, tenant_id)
  WHERE deleted_at IS NULL;

-- Subscription lifecycle: find linked reminders on Stripe events
CREATE INDEX IF NOT EXISTS idx_patient_reminders_subscription
  ON public.patient_reminders (subscription_id)
  WHERE subscription_linked = true AND deleted_at IS NULL;

-- Scheduler: find reminders needing top-up (enabled, not deleted)
CREATE INDEX IF NOT EXISTS idx_patient_reminders_enabled
  ON public.patient_reminders (tenant_id, is_enabled)
  WHERE deleted_at IS NULL AND is_enabled = true;

-- Notification lookup: cancel pending jobs for a reminder
CREATE INDEX IF NOT EXISTS idx_reminder_notifications_reminder_status
  ON public.patient_reminder_notifications (reminder_id, status, scheduled_for);

-- Scheduler cleanup: find stale scheduled rows
CREATE INDEX IF NOT EXISTS idx_reminder_notifications_scheduled_for
  ON public.patient_reminder_notifications (scheduled_for, status);

-- ================================================================
-- UPDATED_AT TRIGGER
-- ================================================================

CREATE OR REPLACE FUNCTION update_patient_reminders_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_patient_reminders_updated_at ON public.patient_reminders;
CREATE TRIGGER trg_patient_reminders_updated_at
  BEFORE UPDATE ON public.patient_reminders
  FOR EACH ROW EXECUTE FUNCTION update_patient_reminders_updated_at();

-- ================================================================
-- ROW LEVEL SECURITY
-- ================================================================

ALTER TABLE public.patient_reminders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patient_reminder_notifications ENABLE ROW LEVEL SECURITY;

-- Patients can read/write their own reminders (via anon key + auth token)
DROP POLICY IF EXISTS "patient_reminders_self_access" ON public.patient_reminders;
CREATE POLICY "patient_reminders_self_access"
  ON public.patient_reminders
  FOR ALL
  USING (
    patient_id = (
      SELECT id FROM public.patients
      WHERE auth_user_id = auth.uid()
      LIMIT 1
    )
  );

-- Notification rows are managed by service role only (backend Edge Functions)
-- No patient-facing RLS policy needed; queries use service-role client.
DROP POLICY IF EXISTS "reminder_notifications_service_role_only" ON public.patient_reminder_notifications;
CREATE POLICY "reminder_notifications_service_role_only"
  ON public.patient_reminder_notifications
  FOR ALL
  USING (
    (SELECT current_setting('role', true)) = 'service_role'
    OR auth.role() = 'service_role'
  );

-- ================================================================
-- GRANTS
-- ================================================================

GRANT SELECT, INSERT, UPDATE, DELETE
  ON public.patient_reminders TO authenticated;

GRANT SELECT, INSERT, UPDATE, DELETE
  ON public.patient_reminder_notifications TO service_role;

-- ================================================================
-- ONESIGNAL PLATFORM INTEGRATION
-- ================================================================

INSERT INTO public.platform_integrations (key, name, description, required_settings, category)
VALUES (
  'onesignal',
  'OneSignal',
  'Push notification delivery for the mobile app. Tenants configure their own App ID and REST API Key.',
  '["app_id", "rest_api_key"]'::jsonb,
  'push_notifications'
)
ON CONFLICT (key) DO UPDATE
SET
  name              = EXCLUDED.name,
  description       = EXCLUDED.description,
  required_settings = EXCLUDED.required_settings,
  category          = EXCLUDED.category;
