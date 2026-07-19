-- Add tenant-level signup email domain restrictions.

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND table_name = 'tenant_settings'
  ) THEN
    ALTER TABLE public.tenant_settings
    ADD COLUMN IF NOT EXISTS signup_domain_restrictions_enabled BOOLEAN NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS allowed_signup_email_domains TEXT[] NOT NULL DEFAULT ARRAY[]::TEXT[];
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'tenant_settings'
      AND column_name = 'signup_domain_restrictions_enabled'
  ) THEN
    COMMENT ON COLUMN public.tenant_settings.signup_domain_restrictions_enabled IS
    'When true, only emails whose domain appears in allowed_signup_email_domains may register.';
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'tenant_settings'
      AND column_name = 'allowed_signup_email_domains'
  ) THEN
    COMMENT ON COLUMN public.tenant_settings.allowed_signup_email_domains IS
    'Allowlist of email domains accepted during tenant signup flows, normalized without the @ prefix.';
  END IF;
END $$;
