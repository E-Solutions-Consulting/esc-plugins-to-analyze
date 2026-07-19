-- Serve the tenant-admin orders list from a single, count-free query. Search
-- candidates are split into disjoint order-number and patient branches so the
-- existing trigram indexes can be used without materializing patient IDs in
-- the browser. Both functions run with the caller's privileges and retain RLS.

CREATE INDEX IF NOT EXISTS idx_orders_tenant_created_at_id_desc
  ON public.orders (tenant_id, created_at DESC, id DESC);

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
SECURITY INVOKER
SET search_path = public
SET plan_cache_mode = force_custom_plan
AS $$
DECLARE
  search_value text := NULLIF(BTRIM(p_search), '');
  search_terms text[];
  first_term text;
  second_term text;
BEGIN
  IF (p_cursor_created_at IS NULL) <> (p_cursor_id IS NULL) THEN
    RAISE EXCEPTION 'Both cursor values must be provided together';
  END IF;

  search_terms := regexp_split_to_array(search_value, '\s+');
  first_term := search_terms[1];
  IF cardinality(search_terms) >= 2 THEN
    second_term := search_terms[2];
  END IF;

  RETURN QUERY
  WITH candidate_orders AS (
    -- This branch serves the unsearched list and indexed order-number search.
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
      AND (
        search_value IS NULL
        OR o.order_number ILIKE '%' || search_value || '%'
      )

    UNION ALL

    -- Kept disjoint from the first branch to avoid duplicate removal/sorting.
    SELECT o.id, o.created_at
    FROM public.patients AS p
    JOIN public.orders AS o ON o.patient_id = p.id
    WHERE search_value IS NOT NULL
      AND p.tenant_id = p_tenant_id
      AND o.tenant_id = p_tenant_id
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
      AND (
        p.first_name ILIKE '%' || search_value || '%'
        OR p.last_name ILIKE '%' || search_value || '%'
        OR p.email ILIKE '%' || search_value || '%'
        OR (
          first_term IS NOT NULL
          AND second_term IS NOT NULL
          AND (
            (
              p.first_name ILIKE '%' || first_term || '%'
              AND p.last_name ILIKE '%' || second_term || '%'
            )
            OR (
              p.first_name ILIKE '%' || second_term || '%'
              AND p.last_name ILIKE '%' || first_term || '%'
            )
          )
        )
      )
  ),
  page_order_ids AS MATERIALIZED (
    SELECT candidate.id, candidate.created_at
    FROM candidate_orders AS candidate
    ORDER BY candidate.created_at DESC, candidate.id DESC
    LIMIT LEAST(GREATEST(p_limit, 1), 1001)
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
    p.first_name,
    p.last_name,
    p.email,
    product.name,
    os.id,
    os.status_key,
    os.admin_status_label,
    os.is_terminal,
    os.next_step_owner::text
  FROM page_order_ids AS page
  JOIN public.orders AS o ON o.id = page.id
  JOIN public.patients AS p
    ON p.id = o.patient_id
   AND p.tenant_id = p_tenant_id
  LEFT JOIN public.subscriptions AS s ON s.id = o.subscription_id
  LEFT JOIN public.products AS product ON product.id = o.product_id
  LEFT JOIN public.order_statuses AS os ON os.id = o.status_id
  ORDER BY page.created_at DESC, page.id DESC;
END;
$$;

CREATE OR REPLACE FUNCTION public.export_tenant_orders_page(
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
  p_limit integer DEFAULT 1000
)
RETURNS TABLE (
  id uuid,
  order_number text,
  total_cents integer,
  created_at timestamptz,
  shipping_state text,
  subscription_order_type text,
  subscription_id uuid,
  subscription_status text,
  subscription_current_period_end_at timestamptz,
  patient_first_name text,
  patient_last_name text,
  patient_email text,
  product_name text,
  order_status_label text
)
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path = public
AS $$
  SELECT
    orders.id,
    orders.order_number,
    orders.total_cents,
    orders.created_at,
    orders.shipping_state,
    orders.subscription_order_type,
    orders.subscription_id,
    orders.subscription_status,
    orders.subscription_current_period_end_at,
    orders.patient_first_name,
    orders.patient_last_name,
    orders.patient_email,
    orders.product_name,
    orders.order_status_label
  FROM public.list_tenant_orders(
    p_tenant_id,
    p_status_id,
    p_product_id,
    p_provider_platform,
    p_shipping_state,
    p_created_from,
    p_created_to,
    p_search,
    p_cursor_created_at,
    p_cursor_id,
    LEAST(GREATEST(p_limit, 1), 1000),
    false
  ) AS orders;
$$;

REVOKE ALL ON FUNCTION public.list_tenant_orders(
  uuid, uuid, uuid, text, text, timestamptz, timestamptz, text,
  timestamptz, uuid, integer, boolean
) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.list_tenant_orders(
  uuid, uuid, uuid, text, text, timestamptz, timestamptz, text,
  timestamptz, uuid, integer, boolean
) TO authenticated;

REVOKE ALL ON FUNCTION public.export_tenant_orders_page(
  uuid, uuid, uuid, text, text, timestamptz, timestamptz, text,
  timestamptz, uuid, integer
) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.export_tenant_orders_page(
  uuid, uuid, uuid, text, text, timestamptz, timestamptz, text,
  timestamptz, uuid, integer
) TO authenticated;
