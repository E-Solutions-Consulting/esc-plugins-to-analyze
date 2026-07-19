CREATE TABLE IF NOT EXISTS public.product_provider_platform_load_balancing_rule_sets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  is_default BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (id, product_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_product_provider_platform_lb_default_rule_set
  ON public.product_provider_platform_load_balancing_rule_sets(product_id)
  WHERE is_default = true;

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_rule_sets_product_id
  ON public.product_provider_platform_load_balancing_rule_sets(product_id);

ALTER TABLE public.product_provider_platform_load_balancing_rule_sets ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_provider_platform_load_balancing_rule_sets'
      AND policyname = 'Access via product ownership for provider platform load balancing rule sets'
  ) THEN
    CREATE POLICY "Access via product ownership for provider platform load balancing rule sets"
      ON public.product_provider_platform_load_balancing_rule_sets
      FOR ALL
      USING (
        product_id IN (
          SELECT id FROM public.products
          WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
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
    WHERE tgname = 'update_product_provider_platform_lb_rule_sets_updated_at'
      AND tgrelid = 'public.product_provider_platform_load_balancing_rule_sets'::regclass
  ) THEN
    CREATE TRIGGER update_product_provider_platform_lb_rule_sets_updated_at
      BEFORE UPDATE ON public.product_provider_platform_load_balancing_rule_sets
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_product_provider_platforms_id_product_id
  ON public.product_provider_platforms(id, product_id);

CREATE TABLE IF NOT EXISTS public.product_provider_platform_load_balancing_rule_set_states (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  rule_set_id UUID NOT NULL,
  product_id UUID NOT NULL,
  state_code TEXT NOT NULL CHECK (state_code ~ '^[A-Z]{2}$'),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (rule_set_id, state_code),
  UNIQUE (product_id, state_code),
  FOREIGN KEY (rule_set_id, product_id)
    REFERENCES public.product_provider_platform_load_balancing_rule_sets(id, product_id)
    ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_rule_set_states_rule_set_id
  ON public.product_provider_platform_load_balancing_rule_set_states(rule_set_id);

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_rule_set_states_product_id
  ON public.product_provider_platform_load_balancing_rule_set_states(product_id);

ALTER TABLE public.product_provider_platform_load_balancing_rule_set_states ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_provider_platform_load_balancing_rule_set_states'
      AND policyname = 'Access via product ownership for provider platform load balancing rule set states'
  ) THEN
    CREATE POLICY "Access via product ownership for provider platform load balancing rule set states"
      ON public.product_provider_platform_load_balancing_rule_set_states
      FOR ALL
      USING (
        product_id IN (
          SELECT id FROM public.products
          WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
        )
      );
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.product_provider_platform_load_balancing_rule_set_allocations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  rule_set_id UUID NOT NULL,
  product_id UUID NOT NULL,
  product_provider_platform_id UUID NOT NULL,
  allocation_percentage INTEGER NOT NULL CHECK (
    allocation_percentage >= 0
    AND allocation_percentage <= 100
  ),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (rule_set_id, product_provider_platform_id),
  FOREIGN KEY (rule_set_id, product_id)
    REFERENCES public.product_provider_platform_load_balancing_rule_sets(id, product_id)
    ON DELETE CASCADE,
  FOREIGN KEY (product_provider_platform_id, product_id)
    REFERENCES public.product_provider_platforms(id, product_id)
    ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_allocations_rule_set_id
  ON public.product_provider_platform_load_balancing_rule_set_allocations(rule_set_id);

CREATE INDEX IF NOT EXISTS idx_product_provider_platform_lb_allocations_product_id
  ON public.product_provider_platform_load_balancing_rule_set_allocations(product_id);

ALTER TABLE public.product_provider_platform_load_balancing_rule_set_allocations ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_provider_platform_load_balancing_rule_set_allocations'
      AND policyname = 'Access via product ownership for provider platform load balancing rule set allocations'
  ) THEN
    CREATE POLICY "Access via product ownership for provider platform load balancing rule set allocations"
      ON public.product_provider_platform_load_balancing_rule_set_allocations
      FOR ALL
      USING (
        product_id IN (
          SELECT id FROM public.products
          WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
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
    WHERE tgname = 'update_product_provider_platform_lb_allocations_updated_at'
      AND tgrelid = 'public.product_provider_platform_load_balancing_rule_set_allocations'::regclass
  ) THEN
    CREATE TRIGGER update_product_provider_platform_lb_allocations_updated_at
      BEFORE UPDATE ON public.product_provider_platform_load_balancing_rule_set_allocations
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

DO $$
DECLARE
  default_rule_set_id UUID;
  state_rule_set_id UUID;
  current_product_id UUID;
  current_state_code TEXT;
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND table_name = 'product_provider_platform_load_balancing_rules'
  ) THEN
    FOR current_product_id IN
      SELECT DISTINCT ppp.product_id
      FROM public.product_provider_platform_load_balancing_rules legacy_rules
      JOIN public.product_provider_platforms ppp
        ON ppp.id = legacy_rules.product_provider_platform_id
    LOOP
      IF EXISTS (
        SELECT 1
        FROM public.product_provider_platform_load_balancing_rules legacy_rules
        JOIN public.product_provider_platforms ppp
          ON ppp.id = legacy_rules.product_provider_platform_id
        WHERE ppp.product_id = current_product_id
          AND legacy_rules.state_code IS NULL
      ) THEN
        INSERT INTO public.product_provider_platform_load_balancing_rule_sets (
          product_id,
          is_default
        )
        VALUES (current_product_id, true)
        RETURNING id INTO default_rule_set_id;

        INSERT INTO public.product_provider_platform_load_balancing_rule_set_allocations (
          rule_set_id,
          product_id,
          product_provider_platform_id,
          allocation_percentage
        )
        SELECT
          default_rule_set_id,
          current_product_id,
          legacy_rules.product_provider_platform_id,
          legacy_rules.allocation_percentage
        FROM public.product_provider_platform_load_balancing_rules legacy_rules
        JOIN public.product_provider_platforms ppp
          ON ppp.id = legacy_rules.product_provider_platform_id
        WHERE ppp.product_id = current_product_id
          AND legacy_rules.state_code IS NULL;
      END IF;

      FOR current_state_code IN
        SELECT DISTINCT legacy_rules.state_code
        FROM public.product_provider_platform_load_balancing_rules legacy_rules
        JOIN public.product_provider_platforms ppp
          ON ppp.id = legacy_rules.product_provider_platform_id
        WHERE ppp.product_id = current_product_id
          AND legacy_rules.state_code IS NOT NULL
      LOOP
        INSERT INTO public.product_provider_platform_load_balancing_rule_sets (
          product_id,
          is_default
        )
        VALUES (current_product_id, false)
        RETURNING id INTO state_rule_set_id;

        INSERT INTO public.product_provider_platform_load_balancing_rule_set_states (
          rule_set_id,
          product_id,
          state_code
        )
        VALUES (
          state_rule_set_id,
          current_product_id,
          current_state_code
        );

        INSERT INTO public.product_provider_platform_load_balancing_rule_set_allocations (
          rule_set_id,
          product_id,
          product_provider_platform_id,
          allocation_percentage
        )
        SELECT
          state_rule_set_id,
          current_product_id,
          legacy_rules.product_provider_platform_id,
          legacy_rules.allocation_percentage
        FROM public.product_provider_platform_load_balancing_rules legacy_rules
        JOIN public.product_provider_platforms ppp
          ON ppp.id = legacy_rules.product_provider_platform_id
        WHERE ppp.product_id = current_product_id
          AND legacy_rules.state_code = current_state_code;
      END LOOP;
    END LOOP;

    DROP TABLE public.product_provider_platform_load_balancing_rules;
  END IF;
END $$;

DROP TABLE IF EXISTS public.product_provider_platform_load_balancing_rules;
