-- Optimize the tenant dashboard initial load by avoiding full-table row
-- transfers for scalar cards and by supporting grouped/status lookups.

CREATE INDEX IF NOT EXISTS idx_patients_tenant_access_status
  ON public.patients (tenant_id, access_status);

CREATE INDEX IF NOT EXISTS idx_products_tenant_is_enabled
  ON public.products (tenant_id, is_enabled);

CREATE INDEX IF NOT EXISTS idx_orders_tenant_status_id
  ON public.orders (tenant_id, status_id);

CREATE INDEX IF NOT EXISTS idx_orders_tenant_status_changed_at
  ON public.orders (tenant_id, status_id, status_changed_at)
  WHERE status_id IS NOT NULL
    AND status_changed_at IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_order_status_history_order_created_at_desc
  ON public.order_status_history (order_id, created_at DESC)
  INCLUDE (status_id);

CREATE OR REPLACE FUNCTION public.get_tenant_dashboard_summary(p_tenant_id uuid)
RETURNS TABLE (
  total_patients integer,
  active_patients integer,
  total_orders integer,
  pending_orders integer,
  total_products integer,
  enabled_products integer
)
LANGUAGE sql
STABLE
SECURITY INVOKER
AS $$
  WITH patient_counts AS (
    SELECT
      COUNT(*)::integer AS total_patients,
      (COUNT(*) FILTER (WHERE access_status = 'active'))::integer AS active_patients
    FROM public.patients
    WHERE tenant_id = p_tenant_id
  ),
  order_counts AS (
    SELECT
      COUNT(*)::integer AS total_orders,
      (COUNT(*) FILTER (WHERE status_id IS NULL))::integer AS pending_orders
    FROM public.orders
    WHERE tenant_id = p_tenant_id
  ),
  product_counts AS (
    SELECT
      COUNT(*)::integer AS total_products,
      (COUNT(*) FILTER (WHERE is_enabled = true))::integer AS enabled_products
    FROM public.products
    WHERE tenant_id = p_tenant_id
  )
  SELECT
    patient_counts.total_patients,
    patient_counts.active_patients,
    order_counts.total_orders,
    order_counts.pending_orders,
    product_counts.total_products,
    product_counts.enabled_products
  FROM patient_counts
  CROSS JOIN order_counts
  CROSS JOIN product_counts;
$$;

GRANT EXECUTE ON FUNCTION public.get_tenant_dashboard_summary(uuid) TO authenticated;

CREATE OR REPLACE FUNCTION public.get_dashboard_overdue_counts(p_tenant_id uuid)
RETURNS TABLE (
  status_id uuid,
  status_key text,
  admin_status_label text,
  is_terminal boolean,
  next_step_owner text,
  expiration_timer_hours double precision,
  overdue_count bigint,
  previous_day_overdue_count bigint
)
LANGUAGE sql
STABLE
SECURITY INVOKER
AS $$
  WITH active_statuses AS (
    SELECT
      os.id,
      os.status_key,
      os.admin_status_label,
      os.is_terminal,
      os.next_step_owner,
      os.expiration_timer_hours
    FROM public.order_statuses os
    WHERE os.is_active = true
      AND os.expiration_timer_hours IS NOT NULL
  ),
  tenant_orders AS (
    SELECT o.id, o.status_id, o.status_changed_at
    FROM public.orders o
    WHERE o.tenant_id = p_tenant_id
  ),
  overdue_now AS (
    SELECT
      s.id AS status_id,
      COUNT(o.id) AS overdue_count
    FROM active_statuses s
    LEFT JOIN tenant_orders o
      ON o.status_id = s.id
      AND o.status_changed_at IS NOT NULL
      AND o.status_changed_at < (now() - (s.expiration_timer_hours * interval '1 hour'))
    GROUP BY s.id
  ),
  latest_history_prev AS (
    SELECT DISTINCT ON (h.order_id)
      h.order_id,
      h.status_id,
      h.created_at
    FROM public.order_status_history h
    INNER JOIN tenant_orders o ON o.id = h.order_id
    WHERE h.created_at <= (now() - interval '1 day')
    ORDER BY h.order_id, h.created_at DESC
  ),
  effective_prev AS (
    SELECT
      o.id AS order_id,
      COALESCE(
        lh.status_id,
        CASE WHEN o.status_changed_at <= (now() - interval '1 day') THEN o.status_id END
      ) AS effective_status_id,
      COALESCE(
        lh.created_at,
        CASE WHEN o.status_changed_at <= (now() - interval '1 day') THEN o.status_changed_at END
      ) AS effective_changed_at
    FROM tenant_orders o
    LEFT JOIN latest_history_prev lh ON lh.order_id = o.id
  ),
  overdue_prev AS (
    SELECT
      s.id AS status_id,
      COUNT(e.order_id) AS overdue_count
    FROM active_statuses s
    LEFT JOIN effective_prev e
      ON e.effective_status_id = s.id
      AND e.effective_changed_at IS NOT NULL
      AND e.effective_changed_at < ((now() - interval '1 day') - (s.expiration_timer_hours * interval '1 hour'))
    GROUP BY s.id
  )
  SELECT
    s.id AS status_id,
    s.status_key,
    s.admin_status_label,
    s.is_terminal,
    s.next_step_owner,
    s.expiration_timer_hours,
    COALESCE(on_now.overdue_count, 0) AS overdue_count,
    COALESCE(op.overdue_count, 0) AS previous_day_overdue_count
  FROM active_statuses s
  LEFT JOIN overdue_now on_now ON on_now.status_id = s.id
  LEFT JOIN overdue_prev op ON op.status_id = s.id
  WHERE COALESCE(on_now.overdue_count, 0) > 0
     OR COALESCE(op.overdue_count, 0) > 0;
$$;

GRANT EXECUTE ON FUNCTION public.get_dashboard_overdue_counts(uuid) TO authenticated;
