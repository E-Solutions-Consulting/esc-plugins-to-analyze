-- Fix list_tenant_orders timeouts seen with staging-sized datasets.
--
-- The previous SECURITY INVOKER implementation evaluated the orders/patients
-- RLS policies while building both sides of a UNION. Those policies call
-- role/membership helpers per candidate row, and the patient-search branch
-- could be planned from orders first. A full-email search could consequently
-- inspect most of a tenant's orders before finding a matching patient.
--
-- This admin-only RPC now authorizes the caller once, then runs as the function
-- owner with an immutable tenant predicate on every tenant-owned join. The
-- unsearched and searched paths are deliberately separate so normal keyset
-- pagination stays on the tenant/created_at/id index. Patient matches are
-- materialized before joining to orders, which makes email/name searches use
-- the patient trigram indexes and the orders.patient_id index.

CREATE OR REPLACE FUNCTION public.list_tenant_orders(
  p_tenant_id uuid,
  p_status_id uuid DEFAULT NULL,
  p_product_id uuid DEFAULT NULL,
  p_provider_platform text DEFAULT NULL,
  p_shipping_state text DEFAULT NULL,
  p_created_from timestamptz DEFAULT NULL,
  p_created_to timestamptz DEFAULT NULL,
  p_search text DEFAULT NULL,
  p_cursor_created_at timestamptz DEFAULT NULL,
  p_cursor_id uuid DEFAULT NULL,
  p_limit integer DEFAULT 26,
  p_include_metadata boolean DEFAULT true
)
RETURNS TABLE (
  id uuid,
  order_number text,
  status_id uuid,
  provider_platform_integration_key text,
  total_cents integer,
  discount_cents integer,
  metadata jsonb,
  created_at timestamptz,
  renewal_at timestamptz,
  shipping_state text,
  subscription_order_type text,
  subscription_id uuid,
  subscription_status text,
  subscription_current_period_end_at timestamptz,
  patient_first_name text,
  patient_last_name text,
  patient_email text,
  product_name text,
  order_status_id uuid,
  order_status_key text,
  order_status_label text,
  order_status_is_terminal boolean,
  order_status_next_step_owner text
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
SET row_security = off
SET plan_cache_mode = force_custom_plan
AS $$
DECLARE
  caller_id uuid := auth.uid();
  search_value text := NULLIF(BTRIM(p_search), '');
  search_terms text[];
  first_term text;
  second_term text;
  bounded_limit integer := LEAST(GREATEST(p_limit, 1), 1001);
BEGIN
  IF caller_id IS NULL OR NOT (
    public.is_tenant_admin(caller_id, p_tenant_id)
    OR public.has_customer_support_tenant_access(caller_id, p_tenant_id)
  ) THEN
    RAISE EXCEPTION 'Not authorized to list orders for this tenant'
      USING ERRCODE = '42501';
  END IF;

  IF (p_cursor_created_at IS NULL) <> (p_cursor_id IS NULL) THEN
    RAISE EXCEPTION 'Both cursor values must be provided together';
  END IF;

  IF search_value IS NULL THEN
    RETURN QUERY
    WITH page_order_ids AS MATERIALIZED (
      SELECT o.id, o.created_at
      FROM public.orders AS o
      WHERE o.tenant_id = p_tenant_id
        AND (p_status_id IS NULL OR o.status_id = p_status_id)
        AND (p_product_id IS NULL OR o.product_id = p_product_id)
        AND (
          p_provider_platform IS NULL
          OR o.provider_platform_integration_key = p_provider_platform
        )
        AND (p_shipping_state IS NULL OR o.shipping_state = p_shipping_state)
        AND (p_created_from IS NULL OR o.created_at >= p_created_from)
        AND (p_created_to IS NULL OR o.created_at <= p_created_to)
        AND (
          p_cursor_created_at IS NULL
          OR (o.created_at, o.id) < (p_cursor_created_at, p_cursor_id)
        )
      ORDER BY o.created_at DESC, o.id DESC
      LIMIT bounded_limit
    )
    SELECT
      o.id,
      o.order_number,
      o.status_id,
      o.provider_platform_integration_key,
      o.total_cents,
      COALESCE(o.discount_cents, 0),
      CASE WHEN p_include_metadata THEN o.metadata ELSE NULL::jsonb END,
      o.created_at,
      o.renewal_at,
      o.shipping_state,
      o.subscription_order_type,
      s.id,
      s.status::text,
      s.current_period_end_at,
      patient.first_name,
      patient.last_name,
      patient.email,
      product.name,
      order_status.id,
      order_status.status_key,
      order_status.admin_status_label,
      order_status.is_terminal,
      order_status.next_step_owner::text
    FROM page_order_ids AS page
    JOIN public.orders AS o
      ON o.id = page.id
     AND o.tenant_id = p_tenant_id
    JOIN public.patients AS patient
      ON patient.id = o.patient_id
     AND patient.tenant_id = p_tenant_id
    LEFT JOIN public.subscriptions AS s
      ON s.id = o.subscription_id
     AND s.tenant_id = p_tenant_id
    LEFT JOIN public.products AS product
      ON product.id = o.product_id
     AND product.tenant_id = p_tenant_id
    LEFT JOIN public.order_statuses AS order_status ON order_status.id = o.status_id
    ORDER BY page.created_at DESC, page.id DESC;

    RETURN;
  END IF;

  search_terms := regexp_split_to_array(search_value, '\s+');
  first_term := search_terms[1];
  IF cardinality(search_terms) >= 2 THEN
    second_term := search_terms[2];
  END IF;

  RETURN QUERY
  WITH matching_patients AS MATERIALIZED (
    SELECT patient.id
    FROM public.patients AS patient
    WHERE patient.tenant_id = p_tenant_id
      AND (
        patient.first_name ILIKE '%' || search_value || '%'
        OR patient.last_name ILIKE '%' || search_value || '%'
        OR patient.email ILIKE '%' || search_value || '%'
        OR (
          first_term IS NOT NULL
          AND second_term IS NOT NULL
          AND (
            (
              patient.first_name ILIKE '%' || first_term || '%'
              AND patient.last_name ILIKE '%' || second_term || '%'
            )
            OR (
              patient.first_name ILIKE '%' || second_term || '%'
              AND patient.last_name ILIKE '%' || first_term || '%'
            )
          )
        )
      )
  ),
  candidate_orders AS (
    SELECT o.id, o.created_at
    FROM public.orders AS o
    WHERE o.tenant_id = p_tenant_id
      AND (p_status_id IS NULL OR o.status_id = p_status_id)
      AND (p_product_id IS NULL OR o.product_id = p_product_id)
      AND (
        p_provider_platform IS NULL
        OR o.provider_platform_integration_key = p_provider_platform
      )
      AND (p_shipping_state IS NULL OR o.shipping_state = p_shipping_state)
      AND (p_created_from IS NULL OR o.created_at >= p_created_from)
      AND (p_created_to IS NULL OR o.created_at <= p_created_to)
      AND (
        p_cursor_created_at IS NULL
        OR (o.created_at, o.id) < (p_cursor_created_at, p_cursor_id)
      )
      AND o.order_number ILIKE '%' || search_value || '%'

    UNION ALL

    SELECT o.id, o.created_at
    FROM matching_patients AS matching_patient
    JOIN public.orders AS o ON o.patient_id = matching_patient.id
    WHERE o.tenant_id = p_tenant_id
      AND (p_status_id IS NULL OR o.status_id = p_status_id)
      AND (p_product_id IS NULL OR o.product_id = p_product_id)
      AND (
        p_provider_platform IS NULL
        OR o.provider_platform_integration_key = p_provider_platform
      )
      AND (p_shipping_state IS NULL OR o.shipping_state = p_shipping_state)
      AND (p_created_from IS NULL OR o.created_at >= p_created_from)
      AND (p_created_to IS NULL OR o.created_at <= p_created_to)
      AND (
        p_cursor_created_at IS NULL
        OR (o.created_at, o.id) < (p_cursor_created_at, p_cursor_id)
      )
      AND o.order_number NOT ILIKE '%' || search_value || '%'
  ),
  page_order_ids AS MATERIALIZED (
    SELECT candidate.id, candidate.created_at
    FROM candidate_orders AS candidate
    ORDER BY candidate.created_at DESC, candidate.id DESC
    LIMIT bounded_limit
  )
  SELECT
    o.id,
    o.order_number,
    o.status_id,
    o.provider_platform_integration_key,
    o.total_cents,
    COALESCE(o.discount_cents, 0),
    CASE WHEN p_include_metadata THEN o.metadata ELSE NULL::jsonb END,
    o.created_at,
    o.renewal_at,
    o.shipping_state,
    o.subscription_order_type,
    s.id,
    s.status::text,
    s.current_period_end_at,
    patient.first_name,
    patient.last_name,
    patient.email,
    product.name,
    order_status.id,
    order_status.status_key,
    order_status.admin_status_label,
    order_status.is_terminal,
    order_status.next_step_owner::text
  FROM page_order_ids AS page
  JOIN public.orders AS o
    ON o.id = page.id
   AND o.tenant_id = p_tenant_id
  JOIN public.patients AS patient
    ON patient.id = o.patient_id
   AND patient.tenant_id = p_tenant_id
  LEFT JOIN public.subscriptions AS s
    ON s.id = o.subscription_id
   AND s.tenant_id = p_tenant_id
  LEFT JOIN public.products AS product
    ON product.id = o.product_id
   AND product.tenant_id = p_tenant_id
  LEFT JOIN public.order_statuses AS order_status ON order_status.id = o.status_id
  ORDER BY page.created_at DESC, page.id DESC;
END;
$$;

REVOKE ALL ON FUNCTION public.list_tenant_orders(
  uuid, uuid, uuid, text, text, timestamptz, timestamptz, text,
  timestamptz, uuid, integer, boolean
) FROM PUBLIC;

GRANT EXECUTE ON FUNCTION public.list_tenant_orders(
  uuid, uuid, uuid, text, text, timestamptz, timestamptz, text,
  timestamptz, uuid, integer, boolean
) TO authenticated;

