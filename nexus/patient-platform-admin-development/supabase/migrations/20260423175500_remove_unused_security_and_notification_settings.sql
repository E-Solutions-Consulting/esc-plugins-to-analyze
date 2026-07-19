-- Remove unused tenant settings fields and platform setting records
-- for Security and Notifications screens that no longer exist in the UI.

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND table_name = 'platform_settings'
  ) THEN
    DELETE FROM public.platform_settings
    WHERE key IN (
      'notifications_enabled',
      'security_mfa_required',
      'security_session_timeout',
      'security_password_policy'
    );
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND table_name = 'tenant_settings'
  ) THEN
    ALTER TABLE public.tenant_settings
    DROP COLUMN IF EXISTS session_timeout_minutes,
    DROP COLUMN IF EXISTS require_mfa,
    DROP COLUMN IF EXISTS notifications_email_enabled,
    DROP COLUMN IF EXISTS notifications_sms_enabled;
  END IF;
END $$;
