-- Email one-time passcodes for passwordless patient sign-in.
-- Mirrors patient_password_reset_tokens: the code is stored HASHED (never
-- plaintext), single-use (used_at), time-limited (expires_at), and brute-force
-- limited (attempt_count). Service-role only via RLS.

CREATE TABLE IF NOT EXISTS public.patient_auth_otps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL
    REFERENCES public.patients(id) ON DELETE CASCADE,
  auth_user_id UUID NOT NULL,
  email TEXT NOT NULL,
  code_hash TEXT NOT NULL,
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  used_at TIMESTAMP WITH TIME ZONE,
  attempt_count INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Verify lookups by (tenant, email) among active codes.
CREATE INDEX IF NOT EXISTS patient_auth_otps_lookup_idx
  ON public.patient_auth_otps (tenant_id, email, expires_at)
  WHERE used_at IS NULL;

CREATE INDEX IF NOT EXISTS patient_auth_otps_patient_id_idx
  ON public.patient_auth_otps (patient_id);

-- Rate-limit lookups (count recent requests per email).
CREATE INDEX IF NOT EXISTS patient_auth_otps_rate_idx
  ON public.patient_auth_otps (tenant_id, email, created_at);

ALTER TABLE public.patient_auth_otps ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_auth_otps'
      AND policyname = 'service_role_full_access_patient_auth_otps'
  ) THEN
    CREATE POLICY "service_role_full_access_patient_auth_otps"
      ON public.patient_auth_otps
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_patient_auth_otps_updated_at'
      AND tgrelid = 'public.patient_auth_otps'::regclass
  ) THEN
    CREATE TRIGGER update_patient_auth_otps_updated_at
      BEFORE UPDATE ON public.patient_auth_otps
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;
