CREATE TABLE IF NOT EXISTS public.tenant_integration_auth_tokens (
  tenant_integration_id UUID PRIMARY KEY
    REFERENCES public.tenant_integrations(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  access_token TEXT NOT NULL,
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  refreshed_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS tenant_integration_auth_tokens_tenant_id_idx
  ON public.tenant_integration_auth_tokens (tenant_id);

ALTER TABLE public.tenant_integration_auth_tokens ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'tenant_integration_auth_tokens'
      AND policyname = 'service_role_full_access_tenant_integration_auth_tokens'
  ) THEN
    CREATE POLICY "service_role_full_access_tenant_integration_auth_tokens"
      ON public.tenant_integration_auth_tokens
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
    WHERE tgname = 'update_tenant_integration_auth_tokens_updated_at'
      AND tgrelid = 'public.tenant_integration_auth_tokens'::regclass
  ) THEN
    CREATE TRIGGER update_tenant_integration_auth_tokens_updated_at
      BEFORE UPDATE ON public.tenant_integration_auth_tokens
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;
