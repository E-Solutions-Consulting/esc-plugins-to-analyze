CREATE TABLE IF NOT EXISTS public.product_provider_platform_load_balancing_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_provider_platform_id UUID NOT NULL REFERENCES public.product_provider_platforms(id) ON DELETE CASCADE,
  state_code TEXT,
  allocation_percentage INTEGER NOT NULL CHECK (
    allocation_percentage >= 0
    AND allocation_percentage <= 100
  ),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (product_provider_platform_id, state_code),
  CONSTRAINT product_provider_platform_load_balancing_rules_state_code_check CHECK (
    state_code IS NULL OR state_code ~ '^[A-Z]{2}$'
  )
);

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_rules_assignment_id
  ON public.product_provider_platform_load_balancing_rules(product_provider_platform_id);

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_rules_state_code
  ON public.product_provider_platform_load_balancing_rules(state_code);

ALTER TABLE public.product_provider_platform_load_balancing_rules ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_provider_platform_load_balancing_rules'
      AND policyname = 'Access via product ownership for provider platform load balancing rules'
  ) THEN
    CREATE POLICY "Access via product ownership for provider platform load balancing rules"
      ON public.product_provider_platform_load_balancing_rules
      FOR ALL
      USING (
        product_provider_platform_id IN (
          SELECT ppp.id
          FROM public.product_provider_platforms ppp
          JOIN public.products p ON p.id = ppp.product_id
          WHERE p.tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
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
    WHERE tgname = 'update_product_provider_platform_lb_rules_updated_at'
      AND tgrelid = 'public.product_provider_platform_load_balancing_rules'::regclass
  ) THEN
    CREATE TRIGGER update_product_provider_platform_lb_rules_updated_at
      BEFORE UPDATE ON public.product_provider_platform_load_balancing_rules
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.provider_platform_selection_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  order_id UUID REFERENCES public.orders(id) ON DELETE SET NULL,
  tenant_integration_id UUID NOT NULL REFERENCES public.tenant_integrations(id) ON DELETE CASCADE,
  product_provider_platform_id UUID REFERENCES public.product_provider_platforms(id) ON DELETE SET NULL,
  state_code TEXT,
  applied_state_code TEXT,
  selection_reason TEXT NOT NULL,
  random_bucket INTEGER CHECK (random_bucket BETWEEN 1 AND 100),
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT provider_platform_selection_logs_state_code_check CHECK (
    state_code IS NULL OR state_code ~ '^[A-Z]{2}$'
  ),
  CONSTRAINT provider_platform_selection_logs_applied_state_code_check CHECK (
    applied_state_code IS NULL OR applied_state_code ~ '^[A-Z]{2}$'
  )
);

CREATE INDEX IF NOT EXISTS idx_provider_platform_selection_logs_tenant_id
  ON public.provider_platform_selection_logs(tenant_id);

CREATE INDEX IF NOT EXISTS idx_provider_platform_selection_logs_product_id
  ON public.provider_platform_selection_logs(product_id);

CREATE INDEX IF NOT EXISTS idx_provider_platform_selection_logs_order_id
  ON public.provider_platform_selection_logs(order_id);

CREATE INDEX IF NOT EXISTS idx_provider_platform_selection_logs_created_at
  ON public.provider_platform_selection_logs(created_at DESC);

ALTER TABLE public.provider_platform_selection_logs ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'provider_platform_selection_logs'
      AND policyname = 'Tenant admins can view provider platform selection logs'
  ) THEN
    CREATE POLICY "Tenant admins can view provider platform selection logs"
      ON public.provider_platform_selection_logs
      FOR SELECT
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'provider_platform_selection_logs'
      AND policyname = 'Service role can manage provider platform selection logs'
  ) THEN
    CREATE POLICY "Service role can manage provider platform selection logs"
      ON public.provider_platform_selection_logs
      FOR ALL
      USING (auth.role() = 'service_role')
      WITH CHECK (auth.role() = 'service_role');
  END IF;
END $$;
