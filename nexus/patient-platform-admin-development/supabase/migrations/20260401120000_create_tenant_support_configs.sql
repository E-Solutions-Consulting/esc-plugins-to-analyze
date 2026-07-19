CREATE TABLE IF NOT EXISTS public.tenant_support_configs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE UNIQUE,
  support_html TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.tenant_support_configs
  ADD COLUMN IF NOT EXISTS support_html TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT now();

ALTER TABLE public.tenant_support_configs ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'tenant_support_configs'
      AND policyname = 'Tenant admins can manage their support config'
  ) THEN
    CREATE POLICY "Tenant admins can manage their support config"
      ON public.tenant_support_configs FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'tenant_support_configs'
      AND policyname = 'Superadmin can view tenant support configs'
  ) THEN
    CREATE POLICY "Superadmin can view tenant support configs"
      ON public.tenant_support_configs FOR SELECT
      USING (public.is_platform_superadmin(auth.uid()));
  END IF;
END $$;

DROP TRIGGER IF EXISTS update_tenant_support_configs_updated_at
  ON public.tenant_support_configs;

CREATE TRIGGER update_tenant_support_configs_updated_at
  BEFORE UPDATE ON public.tenant_support_configs
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();
