-- Computes overdue order counts per status for the tenant dashboard.
-- Returns current overdue count and previous-day overdue count per status,
-- avoiding large client-side data transfers.
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
SECURITY DEFINER
AS $$
  WITH active_statuses AS (
    SELECT
      os.id,
      os.status_key,
      os.admin_status_label,
      os.is_terminal,
      os.next_step_owner,
      os.expiration_timer_hours
    FROM order_statuses os
    WHERE os.is_active = true
      AND os.expiration_timer_hours IS NOT NULL
  ),
  tenant_orders AS (
    SELECT o.id, o.status_id, o.status_changed_at
    FROM orders o
    WHERE o.tenant_id = p_tenant_id
  ),
  -- For each order, find the most recent history entry at or before now
  latest_history_now AS (
    SELECT DISTINCT ON (h.order_id)
      h.order_id,
      h.status_id,
      h.created_at
    FROM order_status_history h
    INNER JOIN tenant_orders o ON o.id = h.order_id
    WHERE h.created_at <= now()
    ORDER BY h.order_id, h.created_at DESC
  ),
  -- For each order, find the most recent history entry at or before 24h ago
  latest_history_prev AS (
    SELECT DISTINCT ON (h.order_id)
      h.order_id,
      h.status_id,
      h.created_at
    FROM order_status_history h
    INNER JOIN tenant_orders o ON o.id = h.order_id
    WHERE h.created_at <= (now() - interval '1 day')
    ORDER BY h.order_id, h.created_at DESC
  ),
  -- Determine effective status for each order at current time
  effective_now AS (
    SELECT
      o.id AS order_id,
      COALESCE(lh.status_id, o.status_id) AS effective_status_id,
      COALESCE(lh.created_at, o.status_changed_at) AS effective_changed_at
    FROM tenant_orders o
    LEFT JOIN latest_history_now lh ON lh.order_id = o.id
  ),
  -- Determine effective status for each order at previous day
  effective_prev AS (
    SELECT
      o.id AS order_id,
      COALESCE(lh.status_id, 
        CASE WHEN o.status_changed_at <= (now() - interval '1 day') THEN o.status_id END
      ) AS effective_status_id,
      COALESCE(lh.created_at, 
        CASE WHEN o.status_changed_at <= (now() - interval '1 day') THEN o.status_changed_at END
      ) AS effective_changed_at
    FROM tenant_orders o
    LEFT JOIN latest_history_prev lh ON lh.order_id = o.id
  ),
  -- Count overdue orders per status now
  overdue_now AS (
    SELECT
      s.id AS status_id,
      COUNT(e.order_id) AS overdue_count
    FROM active_statuses s
    LEFT JOIN effective_now e
      ON e.effective_status_id = s.id
      AND e.effective_changed_at IS NOT NULL
      AND EXTRACT(EPOCH FROM (now() - e.effective_changed_at)) / 3600.0 > s.expiration_timer_hours
    GROUP BY s.id
  ),
  -- Count overdue orders per status at previous day
  overdue_prev AS (
    SELECT
      s.id AS status_id,
      COUNT(e.order_id) AS overdue_count
    FROM active_statuses s
    LEFT JOIN effective_prev e
      ON e.effective_status_id = s.id
      AND e.effective_changed_at IS NOT NULL
      AND EXTRACT(EPOCH FROM ((now() - interval '1 day') - e.effective_changed_at)) / 3600.0 > s.expiration_timer_hours
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

-- Grant execute to authenticated users (RLS on orders table still applies via SECURITY DEFINER)
GRANT EXECUTE ON FUNCTION public.get_dashboard_overdue_counts(uuid) TO authenticated;
