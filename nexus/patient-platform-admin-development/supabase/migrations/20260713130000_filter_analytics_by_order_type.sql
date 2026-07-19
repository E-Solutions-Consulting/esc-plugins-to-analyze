-- Allow Business Analytics order metrics to be filtered by the subscription
-- lifecycle order type. NULL preserves the existing all-orders behaviour.

DROP FUNCTION IF EXISTS public.get_analytics_order_timeseries(uuid, text, uuid, text);

CREATE FUNCTION public.get_analytics_order_timeseries(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL,
  p_order_type text DEFAULT NULL
)
RETURNS TABLE (
  bucket_label text,
  total_orders bigint,
  initial_orders bigint,
  renewal_orders bigint
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  WITH buckets AS (
    SELECT
      gs AS bucket_start,
      gs + ('1 ' || p_unit)::interval AS bucket_end,
      CASE p_unit
        WHEN 'month' THEN to_char(gs, 'Mon')
        ELSE to_char(gs, 'Mon DD')
      END AS bucket_label
    FROM generate_series(
      date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval,
      date_trunc(p_unit, now()),
      ('1 ' || p_unit)::interval
    ) AS gs
  ),
  filtered_orders AS (
    SELECT o.created_at, o.subscription_order_type
    FROM orders o
    WHERE o.tenant_id = p_tenant_id
      AND (p_product_id IS NULL OR o.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(o.shipping_state)) = UPPER(TRIM(p_state)))
      AND (p_order_type IS NULL OR o.subscription_order_type = p_order_type)
      AND o.created_at >= (date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval)
      AND o.created_at < (date_trunc(p_unit, now()) + ('1 ' || p_unit)::interval)
  )
  SELECT
    b.bucket_label,
    COUNT(fo.created_at) AS total_orders,
    COUNT(fo.created_at) FILTER (WHERE fo.subscription_order_type = 'initial') AS initial_orders,
    COUNT(fo.created_at) FILTER (WHERE fo.subscription_order_type = 'renewal') AS renewal_orders
  FROM buckets b
  LEFT JOIN filtered_orders fo
    ON fo.created_at >= b.bucket_start AND fo.created_at < b.bucket_end
  GROUP BY b.bucket_start, b.bucket_label
  ORDER BY b.bucket_start;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_order_timeseries(uuid, text, uuid, text, text) TO authenticated;

DROP FUNCTION IF EXISTS public.get_analytics_order_by_product(uuid, text, uuid, text);

CREATE FUNCTION public.get_analytics_order_by_product(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL,
  p_order_type text DEFAULT NULL
)
RETURNS TABLE (
  bucket_label text,
  product_id uuid,
  product_name text,
  order_count bigint
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  WITH buckets AS (
    SELECT
      gs AS bucket_start,
      gs + ('1 ' || p_unit)::interval AS bucket_end,
      CASE p_unit
        WHEN 'month' THEN to_char(gs, 'Mon')
        ELSE to_char(gs, 'Mon DD')
      END AS bucket_label
    FROM generate_series(
      date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval,
      date_trunc(p_unit, now()),
      ('1 ' || p_unit)::interval
    ) AS gs
  ),
  filtered_orders AS (
    SELECT o.created_at, o.product_id
    FROM orders o
    WHERE o.tenant_id = p_tenant_id
      AND o.product_id IS NOT NULL
      AND (p_product_id IS NULL OR o.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(o.shipping_state)) = UPPER(TRIM(p_state)))
      AND (p_order_type IS NULL OR o.subscription_order_type = p_order_type)
      AND o.created_at >= (date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval)
      AND o.created_at < (date_trunc(p_unit, now()) + ('1 ' || p_unit)::interval)
  )
  SELECT
    b.bucket_label,
    fo.product_id,
    COALESCE(TRIM(p.name), 'Unknown product') AS product_name,
    COUNT(*) AS order_count
  FROM buckets b
  INNER JOIN filtered_orders fo
    ON fo.created_at >= b.bucket_start AND fo.created_at < b.bucket_end
  INNER JOIN products p ON p.id = fo.product_id
  GROUP BY b.bucket_start, b.bucket_label, fo.product_id, p.name
  ORDER BY b.bucket_start, p.name;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_order_by_product(uuid, text, uuid, text, text) TO authenticated;

DROP FUNCTION IF EXISTS public.get_analytics_order_by_provider(uuid, text, text);

CREATE FUNCTION public.get_analytics_order_by_provider(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL,
  p_order_type text DEFAULT NULL
)
RETURNS TABLE (
  bucket_label text,
  provider_key text,
  provider_name text,
  order_count bigint
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  WITH buckets AS (
    SELECT
      gs AS bucket_start,
      gs + ('1 ' || p_unit)::interval AS bucket_end,
      CASE p_unit
        WHEN 'month' THEN to_char(gs, 'Mon')
        ELSE to_char(gs, 'Mon DD')
      END AS bucket_label
    FROM generate_series(
      date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval,
      date_trunc(p_unit, now()),
      ('1 ' || p_unit)::interval
    ) AS gs
  ),
  tenant_providers AS (
    SELECT pi.key, pi.name
    FROM platform_integrations pi
    INNER JOIN tenant_integrations ti
      ON ti.integration_key = pi.key
      AND ti.tenant_id = p_tenant_id
      AND ti.is_enabled = true
    WHERE pi.is_active = true
      AND pi.category = 'provider_platform'
  ),
  filtered_orders AS (
    SELECT o.created_at, o.provider_platform_integration_key
    FROM orders o
    WHERE o.tenant_id = p_tenant_id
      AND o.provider_platform_integration_key IS NOT NULL
      AND (p_product_id IS NULL OR o.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(o.shipping_state)) = UPPER(TRIM(p_state)))
      AND (p_order_type IS NULL OR o.subscription_order_type = p_order_type)
      AND o.created_at >= (date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval)
      AND o.created_at < (date_trunc(p_unit, now()) + ('1 ' || p_unit)::interval)
  )
  SELECT
    b.bucket_label,
    tp.key AS provider_key,
    tp.name AS provider_name,
    COUNT(fo.created_at) AS order_count
  FROM buckets b
  CROSS JOIN tenant_providers tp
  LEFT JOIN filtered_orders fo
    ON fo.provider_platform_integration_key = tp.key
    AND fo.created_at >= b.bucket_start AND fo.created_at < b.bucket_end
  GROUP BY b.bucket_start, b.bucket_label, tp.key, tp.name
  ORDER BY b.bucket_start, tp.name;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_order_by_provider(uuid, text, uuid, text, text) TO authenticated;

DROP FUNCTION IF EXISTS public.get_analytics_product_trends(uuid, uuid, text);

CREATE FUNCTION public.get_analytics_product_trends(
  p_tenant_id uuid,
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL,
  p_order_type text DEFAULT NULL
)
RETURNS TABLE (
  product_name text,
  today_count bigint,
  yesterday_count bigint
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  WITH filtered_orders AS (
    SELECT o.created_at, o.product_id
    FROM orders o
    WHERE o.tenant_id = p_tenant_id
      AND o.product_id IS NOT NULL
      AND (p_product_id IS NULL OR o.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(o.shipping_state)) = UPPER(TRIM(p_state)))
      AND (p_order_type IS NULL OR o.subscription_order_type = p_order_type)
      AND o.created_at >= date_trunc('day', now()) - interval '1 day'
      AND o.created_at < date_trunc('day', now()) + interval '1 day'
  )
  SELECT
    COALESCE(TRIM(p.name), 'Unknown product') AS product_name,
    COUNT(*) FILTER (WHERE fo.created_at >= date_trunc('day', now()) AND fo.created_at < date_trunc('day', now()) + interval '1 day') AS today_count,
    COUNT(*) FILTER (WHERE fo.created_at >= date_trunc('day', now()) - interval '1 day' AND fo.created_at < date_trunc('day', now())) AS yesterday_count
  FROM filtered_orders fo
  INNER JOIN products p ON p.id = fo.product_id
  GROUP BY p.name
  HAVING
    COUNT(*) FILTER (WHERE fo.created_at >= date_trunc('day', now())) > 0
    OR COUNT(*) FILTER (WHERE fo.created_at < date_trunc('day', now())) > 0
  ORDER BY
    COUNT(*) FILTER (WHERE fo.created_at >= date_trunc('day', now())) DESC,
    p.name;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_product_trends(uuid, uuid, text, text) TO authenticated;
