-- Analytics RPC functions: move heavy client-side aggregation to the database.
-- Each function returns pre-aggregated data so the frontend receives only
-- summary rows regardless of how many orders/subscriptions exist.

--------------------------------------------------------------------------------
-- 1. Order time series (total / initial / renewal per bucket)
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_order_timeseries(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL
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

GRANT EXECUTE ON FUNCTION public.get_analytics_order_timeseries(uuid, text, uuid, text) TO authenticated;

--------------------------------------------------------------------------------
-- 2. Order breakdown by product per bucket
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_order_by_product(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL
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

GRANT EXECUTE ON FUNCTION public.get_analytics_order_by_product(uuid, text, uuid, text) TO authenticated;

--------------------------------------------------------------------------------
-- 3. Order breakdown by provider platform per bucket
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_order_by_provider(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_state text DEFAULT NULL
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
      AND (p_state IS NULL OR UPPER(TRIM(o.shipping_state)) = UPPER(TRIM(p_state)))
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

GRANT EXECUTE ON FUNCTION public.get_analytics_order_by_provider(uuid, text, text) TO authenticated;

--------------------------------------------------------------------------------
-- 4. Subscription time series (new / total active / churned per bucket)
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_subscription_timeseries(
  p_tenant_id uuid,
  p_unit text DEFAULT 'day',
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL
)
RETURNS TABLE (
  bucket_label text,
  new_subscriptions bigint,
  total_subscriptions bigint,
  churned_subscriptions bigint
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
AS $$
DECLARE
  rec RECORD;
BEGIN
  FOR rec IN
    SELECT
      gs AS bucket_start,
      gs + ('1 ' || p_unit)::interval AS bucket_end,
      CASE p_unit
        WHEN 'month' THEN to_char(gs, 'Mon')
        ELSE to_char(gs, 'Mon DD')
      END AS lbl
    FROM generate_series(
      date_trunc(p_unit, now()) - 10 * ('1 ' || p_unit)::interval,
      date_trunc(p_unit, now()),
      ('1 ' || p_unit)::interval
    ) AS gs
    ORDER BY gs
  LOOP
    bucket_label := rec.lbl;

    SELECT COUNT(*) INTO new_subscriptions
    FROM subscriptions s
    LEFT JOIN patients pat ON pat.id = s.patient_id
    WHERE s.tenant_id = p_tenant_id
      AND (p_product_id IS NULL OR s.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(pat.shipping_state)) = UPPER(TRIM(p_state)))
      AND s.created_at >= rec.bucket_start
      AND s.created_at < rec.bucket_end;

    SELECT COUNT(*) INTO total_subscriptions
    FROM subscriptions s
    LEFT JOIN patients pat ON pat.id = s.patient_id
    WHERE s.tenant_id = p_tenant_id
      AND (p_product_id IS NULL OR s.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(pat.shipping_state)) = UPPER(TRIM(p_state)))
      AND s.created_at < rec.bucket_end
      AND (s.cancelled_at IS NULL OR s.cancelled_at >= rec.bucket_end);

    SELECT COUNT(*) INTO churned_subscriptions
    FROM subscriptions s
    LEFT JOIN patients pat ON pat.id = s.patient_id
    WHERE s.tenant_id = p_tenant_id
      AND (p_product_id IS NULL OR s.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(pat.shipping_state)) = UPPER(TRIM(p_state)))
      AND s.cancelled_at >= rec.bucket_start
      AND s.cancelled_at < rec.bucket_end;

    RETURN NEXT;
  END LOOP;
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_subscription_timeseries(uuid, text, uuid, text) TO authenticated;

--------------------------------------------------------------------------------
-- 5. Product trends (today vs yesterday order counts per product)
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_product_trends(
  p_tenant_id uuid,
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL
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

GRANT EXECUTE ON FUNCTION public.get_analytics_product_trends(uuid, uuid, text) TO authenticated;

--------------------------------------------------------------------------------
-- 6. Subscription 7-day daily trend (new vs churned)
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_subscription_daily_trend(
  p_tenant_id uuid,
  p_product_id uuid DEFAULT NULL,
  p_state text DEFAULT NULL
)
RETURNS TABLE (
  day_label text,
  new_subscriptions bigint,
  churned_subscriptions bigint
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  WITH days AS (
    SELECT
      gs AS day_start,
      gs + interval '1 day' AS day_end,
      to_char(gs, 'Dy') AS day_label
    FROM generate_series(
      date_trunc('day', now()) - interval '6 days',
      date_trunc('day', now()),
      interval '1 day'
    ) AS gs
  ),
  filtered_subs AS (
    SELECT s.created_at, s.cancelled_at
    FROM subscriptions s
    LEFT JOIN patients pat ON pat.id = s.patient_id
    WHERE s.tenant_id = p_tenant_id
      AND (p_product_id IS NULL OR s.product_id = p_product_id)
      AND (p_state IS NULL OR UPPER(TRIM(pat.shipping_state)) = UPPER(TRIM(p_state)))
      AND (
        (s.created_at >= date_trunc('day', now()) - interval '6 days' AND s.created_at < date_trunc('day', now()) + interval '1 day')
        OR
        (s.cancelled_at >= date_trunc('day', now()) - interval '6 days' AND s.cancelled_at < date_trunc('day', now()) + interval '1 day')
      )
  )
  SELECT
    d.day_label,
    COUNT(fs.created_at) FILTER (WHERE fs.created_at >= d.day_start AND fs.created_at < d.day_end) AS new_subscriptions,
    COUNT(fs.cancelled_at) FILTER (WHERE fs.cancelled_at >= d.day_start AND fs.cancelled_at < d.day_end) AS churned_subscriptions
  FROM days d
  LEFT JOIN filtered_subs fs ON true
  GROUP BY d.day_start, d.day_label
  ORDER BY d.day_start;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_subscription_daily_trend(uuid, uuid, text) TO authenticated;

--------------------------------------------------------------------------------
-- 7. Analytics summary and filter options
--------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.get_analytics_summary(
  p_tenant_id uuid
)
RETURNS TABLE (
  patient_count bigint,
  provider_platform_count bigint,
  filter_products jsonb,
  filter_states jsonb
)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  SELECT
    (SELECT COUNT(*) FROM patients WHERE tenant_id = p_tenant_id) AS patient_count,
    (
      SELECT COUNT(*)
      FROM platform_integrations pi
      INNER JOIN tenant_integrations ti
        ON ti.integration_key = pi.key
        AND ti.tenant_id = p_tenant_id
        AND ti.is_enabled = true
      WHERE pi.is_active = true
        AND pi.category = 'provider_platform'
    ) AS provider_platform_count,
    (
      SELECT COALESCE(jsonb_agg(jsonb_build_object('id', sub.id, 'name', sub.name) ORDER BY sub.name), '[]'::jsonb)
      FROM (
        SELECT DISTINCT p.id, COALESCE(TRIM(p.name), 'Unknown product') AS name
        FROM products p
        WHERE p.tenant_id = p_tenant_id
          AND EXISTS (
            SELECT 1 FROM orders o WHERE o.product_id = p.id AND o.tenant_id = p_tenant_id
            UNION ALL
            SELECT 1 FROM subscriptions s WHERE s.product_id = p.id AND s.tenant_id = p_tenant_id
          )
      ) sub
    ) AS filter_products,
    (
      SELECT COALESCE(jsonb_agg(DISTINCT UPPER(TRIM(state_val)) ORDER BY UPPER(TRIM(state_val))), '[]'::jsonb)
      FROM (
        SELECT shipping_state AS state_val FROM orders WHERE tenant_id = p_tenant_id AND shipping_state IS NOT NULL AND TRIM(shipping_state) <> ''
        UNION
        SELECT pat.shipping_state AS state_val FROM subscriptions s JOIN patients pat ON pat.id = s.patient_id WHERE s.tenant_id = p_tenant_id AND pat.shipping_state IS NOT NULL AND TRIM(pat.shipping_state) <> ''
      ) states
    ) AS filter_states;
$$;

GRANT EXECUTE ON FUNCTION public.get_analytics_summary(uuid) TO authenticated;
