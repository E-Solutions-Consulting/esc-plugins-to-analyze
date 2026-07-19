CREATE TABLE IF NOT EXISTS public.patient_provider_platform_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  tenant_integration_id UUID NOT NULL REFERENCES public.tenant_integrations(id) ON DELETE CASCADE,
  provider_patient_id TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (patient_id, tenant_integration_id)
);

CREATE INDEX IF NOT EXISTS idx_patient_provider_platform_links_tenant_id
  ON public.patient_provider_platform_links(tenant_id);

CREATE INDEX IF NOT EXISTS idx_patient_provider_platform_links_patient_id
  ON public.patient_provider_platform_links(patient_id);

CREATE INDEX IF NOT EXISTS idx_patient_provider_platform_links_tenant_integration_id
  ON public.patient_provider_platform_links(tenant_integration_id);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_provider_platform_links_provider_patient_id
  ON public.patient_provider_platform_links(tenant_integration_id, provider_patient_id)
  WHERE provider_patient_id IS NOT NULL;

ALTER TABLE public.patient_provider_platform_links ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_provider_platform_links'
      AND policyname = 'Tenant admins can manage patient provider platform links'
  ) THEN
    CREATE POLICY "Tenant admins can manage patient provider platform links"
      ON public.patient_provider_platform_links
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
      AND tablename = 'patient_provider_platform_links'
      AND policyname = 'Patients can view their own provider platform links'
  ) THEN
    CREATE POLICY "Patients can view their own provider platform links"
      ON public.patient_provider_platform_links
      FOR SELECT
      USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
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
    WHERE tgname = 'update_patient_provider_platform_links_updated_at'
      AND tgrelid = 'public.patient_provider_platform_links'::regclass
  ) THEN
    CREATE TRIGGER update_patient_provider_platform_links_updated_at
      BEFORE UPDATE ON public.patient_provider_platform_links
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

COMMENT ON TABLE public.patient_provider_platform_links IS
  'Maps internal patients to external provider-platform patient ids for each tenant integration.';
