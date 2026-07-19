ALTER TYPE public.app_role ADD VALUE IF NOT EXISTS 'customer_support';

CREATE OR REPLACE FUNCTION public.is_customer_support(_auth_user_id UUID)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.user_roles ur
    JOIN public.admin_users au ON ur.user_id = au.id
    WHERE au.auth_user_id = _auth_user_id
      AND ur.role::text = 'customer_support'
  )
$$;

CREATE OR REPLACE FUNCTION public.has_customer_support_tenant_access(
  _auth_user_id UUID,
  _tenant_id UUID
)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.admin_users au
    JOIN public.user_roles ur ON ur.user_id = au.id
    JOIN public.tenant_memberships tm ON tm.admin_user_id = au.id
    WHERE au.auth_user_id = _auth_user_id
      AND ur.role::text = 'customer_support'
      AND tm.tenant_id = _tenant_id
  )
$$;

-- Replace touched tenant policies that used membership alone with role-aware
-- tenant-admin access plus explicit customer-support access.

DROP POLICY IF EXISTS "Tenant admins can manage their patients" ON public.patients;
DROP POLICY IF EXISTS "Tenant admins can manage their subscriptions" ON public.subscriptions;
DROP POLICY IF EXISTS "Tenant admins can manage their orders" ON public.orders;
DROP POLICY IF EXISTS "Tenant admins can manage their products" ON public.products;
DROP POLICY IF EXISTS "Tenant admins can manage their medications" ON public.medications;
DROP POLICY IF EXISTS "Access via product ownership" ON public.product_medications;
DROP POLICY IF EXISTS "Access via product ownership for FAQs" ON public.product_faqs;
DROP POLICY IF EXISTS "Access via product ownership" ON public.product_category_assignments;
DROP POLICY IF EXISTS "Access via product ownership for payment providers" ON public.product_payment_providers;
DROP POLICY IF EXISTS "Access via product ownership for provider platforms" ON public.product_provider_platforms;
DROP POLICY IF EXISTS tenant_product_types_tenant_admin ON public.tenant_product_types;
DROP POLICY IF EXISTS "Tenant admins can manage subscription payment provider links" ON public.subscription_payment_provider_links;
DROP POLICY IF EXISTS "Tenant admins can manage order payment transactions" ON public.order_payment_provider_transactions;
DROP POLICY IF EXISTS "Tenant admins can manage order provider platform links" ON public.order_provider_platform_links;
DROP POLICY IF EXISTS "Tenant admins can manage patient provider platform links" ON public.patient_provider_platform_links;

DO $$
BEGIN
  IF to_regclass('public.product_provider_platform_load_balancing_rules') IS NOT NULL THEN
    DROP POLICY IF EXISTS "Access via product ownership for provider platform load balancing rules"
      ON public.product_provider_platform_load_balancing_rules;
  END IF;

  IF to_regclass('public.product_provider_platform_load_balancing_rule_sets') IS NOT NULL THEN
    DROP POLICY IF EXISTS "Access via product ownership for provider platform load balancing rule sets"
      ON public.product_provider_platform_load_balancing_rule_sets;
  END IF;

  IF to_regclass('public.product_provider_platform_load_balancing_rule_set_states') IS NOT NULL THEN
    DROP POLICY IF EXISTS "Access via product ownership for provider platform load balancing rule set states"
      ON public.product_provider_platform_load_balancing_rule_set_states;
  END IF;

  IF to_regclass('public.product_provider_platform_load_balancing_rule_set_allocations') IS NOT NULL THEN
    DROP POLICY IF EXISTS "Access via product ownership for provider platform load balancing rule set allocations"
      ON public.product_provider_platform_load_balancing_rule_set_allocations;
  END IF;
END $$;

CREATE POLICY "Tenant admins can manage their patients"
  ON public.patients FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view tenant patients"
  ON public.patients FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Customer support can create tenant patients"
  ON public.patients FOR INSERT
  WITH CHECK (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Customer support can update tenant patients"
  ON public.patients FOR UPDATE
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id))
  WITH CHECK (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage their subscriptions"
  ON public.subscriptions FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view tenant subscriptions"
  ON public.subscriptions FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Customer support can update tenant subscriptions"
  ON public.subscriptions FOR UPDATE
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id))
  WITH CHECK (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage their orders"
  ON public.orders FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view tenant orders"
  ON public.orders FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage their products"
  ON public.products FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view tenant products"
  ON public.products FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage their medications"
  ON public.medications FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view tenant medications"
  ON public.medications FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage product medications"
  ON public.product_medications FOR ALL
  USING (
    product_id IN (
      SELECT p.id
      FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  )
  WITH CHECK (
    product_id IN (
      SELECT p.id
      FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  );

CREATE POLICY "Customer support can view product medications"
  ON public.product_medications FOR SELECT
  USING (
    product_id IN (
      SELECT p.id
      FROM public.products p
      WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
    )
  );

DO $$
BEGIN
  IF to_regclass('public.product_faqs') IS NOT NULL THEN
    CREATE POLICY "Tenant admins can manage product FAQs"
      ON public.product_faqs FOR ALL
      USING (
        product_id IN (
          SELECT p.id
          FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      )
      WITH CHECK (
        product_id IN (
          SELECT p.id
          FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      );

    CREATE POLICY "Customer support can view product FAQs"
      ON public.product_faqs FOR SELECT
      USING (
        product_id IN (
          SELECT p.id
          FROM public.products p
          WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
        )
      );
  END IF;
END $$;

CREATE POLICY "Tenant admins can manage product category assignments"
  ON public.product_category_assignments FOR ALL
  USING (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  )
  WITH CHECK (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  );

CREATE POLICY "Customer support can view product category assignments"
  ON public.product_category_assignments FOR SELECT
  USING (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
    )
  );

CREATE POLICY "Tenant admins can manage product payment providers"
  ON public.product_payment_providers FOR ALL
  USING (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  )
  WITH CHECK (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  );

CREATE POLICY "Customer support can view product payment providers"
  ON public.product_payment_providers FOR SELECT
  USING (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
    )
  );

CREATE POLICY "Tenant admins can manage product provider platforms"
  ON public.product_provider_platforms FOR ALL
  USING (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  )
  WITH CHECK (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
        OR public.is_platform_superadmin(auth.uid())
    )
  );

CREATE POLICY "Customer support can view product provider platforms"
  ON public.product_provider_platforms FOR SELECT
  USING (
    product_id IN (
      SELECT p.id FROM public.products p
      WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
    )
  );

DO $$
BEGIN
  IF to_regclass('public.product_provider_platform_load_balancing_rules') IS NOT NULL THEN
    CREATE POLICY "Tenant admins can manage provider platform load balancing rules"
      ON public.product_provider_platform_load_balancing_rules FOR ALL
      USING (
        product_provider_platform_id IN (
          SELECT ppp.id
          FROM public.product_provider_platforms ppp
          JOIN public.products p ON p.id = ppp.product_id
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      )
      WITH CHECK (
        product_provider_platform_id IN (
          SELECT ppp.id
          FROM public.product_provider_platforms ppp
          JOIN public.products p ON p.id = ppp.product_id
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      );

    CREATE POLICY "Customer support can view provider platform load balancing rules"
      ON public.product_provider_platform_load_balancing_rules FOR SELECT
      USING (
        product_provider_platform_id IN (
          SELECT ppp.id
          FROM public.product_provider_platforms ppp
          JOIN public.products p ON p.id = ppp.product_id
          WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
        )
      );
  END IF;
END $$;

DO $$
BEGIN
  IF to_regclass('public.product_provider_platform_load_balancing_rule_sets') IS NOT NULL THEN
    CREATE POLICY "Tenant admins can manage provider platform load balancing rule sets"
      ON public.product_provider_platform_load_balancing_rule_sets FOR ALL
      USING (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      )
      WITH CHECK (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      );

    CREATE POLICY "Customer support can view provider platform load balancing rule sets"
      ON public.product_provider_platform_load_balancing_rule_sets FOR SELECT
      USING (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
        )
      );
  END IF;
END $$;

DO $$
BEGIN
  IF to_regclass('public.product_provider_platform_load_balancing_rule_set_states') IS NOT NULL THEN
    CREATE POLICY "Tenant admins can manage provider platform load balancing rule set states"
      ON public.product_provider_platform_load_balancing_rule_set_states FOR ALL
      USING (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      )
      WITH CHECK (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      );

    CREATE POLICY "Customer support can view provider platform load balancing rule set states"
      ON public.product_provider_platform_load_balancing_rule_set_states FOR SELECT
      USING (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
        )
      );
  END IF;
END $$;

DO $$
BEGIN
  IF to_regclass('public.product_provider_platform_load_balancing_rule_set_allocations') IS NOT NULL THEN
    CREATE POLICY "Tenant admins can manage provider platform load balancing rule set allocations"
      ON public.product_provider_platform_load_balancing_rule_set_allocations FOR ALL
      USING (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      )
      WITH CHECK (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.is_tenant_admin(auth.uid(), p.tenant_id)
            OR public.is_platform_superadmin(auth.uid())
        )
      );

    CREATE POLICY "Customer support can view provider platform load balancing rule set allocations"
      ON public.product_provider_platform_load_balancing_rule_set_allocations FOR SELECT
      USING (
        product_id IN (
          SELECT p.id FROM public.products p
          WHERE public.has_customer_support_tenant_access(auth.uid(), p.tenant_id)
        )
      );
  END IF;
END $$;

CREATE POLICY tenant_product_types_tenant_admin
  ON public.tenant_product_types FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view tenant product types"
  ON public.tenant_product_types FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage subscription payment provider links"
  ON public.subscription_payment_provider_links FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view subscription payment provider links"
  ON public.subscription_payment_provider_links FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage order payment transactions"
  ON public.order_payment_provider_transactions FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view order payment transactions"
  ON public.order_payment_provider_transactions FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage order provider platform links"
  ON public.order_provider_platform_links FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view order provider platform links"
  ON public.order_provider_platform_links FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));

CREATE POLICY "Tenant admins can manage patient provider platform links"
  ON public.patient_provider_platform_links FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

CREATE POLICY "Customer support can view patient provider platform links"
  ON public.patient_provider_platform_links FOR SELECT
  USING (public.has_customer_support_tenant_access(auth.uid(), tenant_id));
