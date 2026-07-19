CREATE TABLE IF NOT EXISTS public.product_provider_platforms (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  tenant_integration_id UUID NOT NULL REFERENCES public.tenant_integrations(id) ON DELETE CASCADE,
  provider_product_variation_sku TEXT,
  is_enabled BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  UNIQUE(product_id, tenant_integration_id)
);

ALTER TABLE public.product_provider_platforms ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_provider_platforms'
      AND policyname = 'Access via product ownership for provider platforms'
  ) THEN
    CREATE POLICY "Access via product ownership for provider platforms"
      ON public.product_provider_platforms
      FOR ALL
      USING (
        product_id IN (
          SELECT id FROM public.products
          WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
        )
      );
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_product_provider_platforms_product
  ON public.product_provider_platforms(product_id);

CREATE INDEX IF NOT EXISTS idx_product_provider_platforms_tenant_integration
  ON public.product_provider_platforms(tenant_integration_id);
