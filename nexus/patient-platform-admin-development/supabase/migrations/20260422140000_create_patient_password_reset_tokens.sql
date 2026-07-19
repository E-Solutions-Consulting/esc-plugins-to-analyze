CREATE TABLE IF NOT EXISTS public.patient_password_reset_tokens (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL
    REFERENCES public.patients(id) ON DELETE CASCADE,
  auth_user_id UUID NOT NULL,
  token_hash TEXT NOT NULL,
  redirect_url TEXT NOT NULL,
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  used_at TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS patient_password_reset_tokens_token_hash_idx
  ON public.patient_password_reset_tokens (token_hash);

CREATE INDEX IF NOT EXISTS patient_password_reset_tokens_patient_id_idx
  ON public.patient_password_reset_tokens (patient_id);

CREATE INDEX IF NOT EXISTS patient_password_reset_tokens_auth_user_id_idx
  ON public.patient_password_reset_tokens (auth_user_id);

CREATE INDEX IF NOT EXISTS patient_password_reset_tokens_active_idx
  ON public.patient_password_reset_tokens (tenant_id, patient_id, expires_at)
  WHERE used_at IS NULL;

ALTER TABLE public.patient_password_reset_tokens ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_password_reset_tokens'
      AND policyname = 'service_role_full_access_patient_password_reset_tokens'
  ) THEN
    CREATE POLICY "service_role_full_access_patient_password_reset_tokens"
      ON public.patient_password_reset_tokens
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
    WHERE tgname = 'update_patient_password_reset_tokens_updated_at'
      AND tgrelid = 'public.patient_password_reset_tokens'::regclass
  ) THEN
    CREATE TRIGGER update_patient_password_reset_tokens_updated_at
      BEFORE UPDATE ON public.patient_password_reset_tokens
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;
