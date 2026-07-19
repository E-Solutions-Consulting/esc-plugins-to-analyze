CREATE TABLE IF NOT EXISTS public.order_provider_platform_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  order_id UUID NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
  tenant_integration_id UUID NOT NULL REFERENCES public.tenant_integrations(id) ON DELETE CASCADE,
  provider_order_id TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (order_id, tenant_integration_id)
);

CREATE INDEX IF NOT EXISTS idx_order_provider_platform_links_tenant_id
  ON public.order_provider_platform_links(tenant_id);

CREATE INDEX IF NOT EXISTS idx_order_provider_platform_links_order_id
  ON public.order_provider_platform_links(order_id);

CREATE INDEX IF NOT EXISTS idx_order_provider_platform_links_tenant_integration_id
  ON public.order_provider_platform_links(tenant_integration_id);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_order_provider_platform_links_provider_order_id
  ON public.order_provider_platform_links(tenant_integration_id, provider_order_id)
  WHERE provider_order_id IS NOT NULL;

ALTER TABLE public.order_provider_platform_links ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'order_provider_platform_links'
      AND policyname = 'Tenant admins can manage order provider platform links'
  ) THEN
    CREATE POLICY "Tenant admins can manage order provider platform links"
      ON public.order_provider_platform_links
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
      AND tablename = 'order_provider_platform_links'
      AND policyname = 'Patients can view their own order provider platform links'
  ) THEN
    CREATE POLICY "Patients can view their own order provider platform links"
      ON public.order_provider_platform_links
      FOR SELECT
      USING (
        order_id IN (
          SELECT o.id
          FROM public.orders o
          WHERE o.patient_id = public.get_patient_by_auth_id(auth.uid())
        )
      );
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
    WHERE tgname = 'update_order_provider_platform_links_updated_at'
      AND tgrelid = 'public.order_provider_platform_links'::regclass
  ) THEN
    CREATE TRIGGER update_order_provider_platform_links_updated_at
      BEFORE UPDATE ON public.order_provider_platform_links
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

COMMENT ON TABLE public.order_provider_platform_links IS
  'Maps internal orders to external provider-platform order ids (for example TelegraMD order ids).';
