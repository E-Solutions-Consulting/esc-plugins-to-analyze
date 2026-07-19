-- Product Usage analytics — (1) make the nightly prune actually clear rows after
-- the per-tenant retention window, and (2) add read-only summary RPCs powering the
-- new "Product Usage" viewer in the admin (Nexus).
--
-- Context:
--   * Retention is a PER-TENANT setting: tenant_analytics_settings.hot_retention_days
--     (tenant override → platform-default row → fallback 30). This is now an
--     admin-editable control in the Product Usage Tracking settings tab, bounded 7–90.
--   * BigQuery export (which set exported_to_warehouse) is owned by a different team
--     and may not be running. So the prune no longer waits on that flag — it deletes
--     strictly by the per-tenant window. Clearing the hot store after the window is
--     the contract; the warehouse copy is that other team's responsibility.
--
-- Idempotent: safe to re-run.

-- ---------------------------------------------------------------------------
-- 1. Bound hot_retention_days to [7, 90] to match the admin UI control.
--    Clamp any existing out-of-band values first so the constraint can be added.
-- ---------------------------------------------------------------------------
UPDATE public.tenant_analytics_settings
   SET hot_retention_days = LEAST(GREATEST(hot_retention_days, 7), 90)
 WHERE hot_retention_days < 7 OR hot_retention_days > 90;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint
    WHERE conname = 'tenant_analytics_settings_hot_retention_days_range'
      AND conrelid = 'public.tenant_analytics_settings'::regclass
  ) THEN
    ALTER TABLE public.tenant_analytics_settings
      ADD CONSTRAINT tenant_analytics_settings_hot_retention_days_range
      CHECK (hot_retention_days BETWEEN 7 AND 90);
  END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 2. Redefine the prune to delete by the per-tenant window UNCONDITIONALLY
--    (no exported_to_warehouse gate). Effective window resolution is unchanged:
--    tenant override → platform default → fallback 30. Events first, then
--    orphan sessions, then orphan devices. Returns per-table delete counts.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.prune_analytics_hot_store(p_dry_run BOOLEAN DEFAULT false)
RETURNS TABLE (events_deleted BIGINT, sessions_deleted BIGINT, devices_deleted BIGINT)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_default_days INTEGER;
  v_events BIGINT := 0;
  v_sessions BIGINT := 0;
  v_devices BIGINT := 0;
BEGIN
  SELECT COALESCE(hot_retention_days, 30) INTO v_default_days
  FROM public.tenant_analytics_settings
  WHERE tenant_id IS NULL
  LIMIT 1;
  v_default_days := COALESCE(v_default_days, 30);

  -- Events: eligible iff event_date is older than the tenant's effective window.
  WITH eligible AS (
    SELECT e.id
    FROM public.analytics_events e
    LEFT JOIN public.tenant_analytics_settings ts ON ts.tenant_id = e.tenant_id
    WHERE e.event_date < (now()::date - COALESCE(ts.hot_retention_days, v_default_days))
  )
  SELECT count(*) INTO v_events FROM eligible;

  IF NOT p_dry_run THEN
    DELETE FROM public.analytics_events e
    USING public.tenant_analytics_settings ts
    WHERE ts.tenant_id = e.tenant_id
      AND e.event_date < (now()::date - COALESCE(ts.hot_retention_days, v_default_days));
    -- Events for tenants with no override row (use platform default).
    DELETE FROM public.analytics_events e
    WHERE NOT EXISTS (SELECT 1 FROM public.tenant_analytics_settings ts WHERE ts.tenant_id = e.tenant_id)
      AND e.event_date < (now()::date - v_default_days);
  END IF;

  -- Sessions: prune old sessions with no remaining events.
  WITH eligible_sessions AS (
    SELECT s.id
    FROM public.analytics_sessions s
    LEFT JOIN public.tenant_analytics_settings ts ON ts.tenant_id = s.tenant_id
    WHERE s.started_at < (now() - make_interval(days => COALESCE(ts.hot_retention_days, v_default_days)))
      AND NOT EXISTS (SELECT 1 FROM public.analytics_events e WHERE e.session_id = s.id)
  )
  SELECT count(*) INTO v_sessions FROM eligible_sessions;

  IF NOT p_dry_run THEN
    DELETE FROM public.analytics_sessions s
    WHERE s.started_at < (
        now() - make_interval(days => COALESCE(
          (SELECT ts.hot_retention_days FROM public.tenant_analytics_settings ts WHERE ts.tenant_id = s.tenant_id),
          v_default_days
        ))
      )
      AND NOT EXISTS (SELECT 1 FROM public.analytics_events e WHERE e.session_id = s.id);
  END IF;

  -- Devices: prune devices not seen within the window and with no remaining
  -- sessions/events (orphans only).
  WITH eligible_devices AS (
    SELECT d.id
    FROM public.analytics_devices d
    LEFT JOIN public.tenant_analytics_settings ts ON ts.tenant_id = d.tenant_id
    WHERE d.last_seen_at < (now() - make_interval(days => COALESCE(ts.hot_retention_days, v_default_days)))
      AND NOT EXISTS (SELECT 1 FROM public.analytics_sessions s WHERE s.device_id = d.id)
      AND NOT EXISTS (SELECT 1 FROM public.analytics_events e WHERE e.device_id = d.id)
  )
  SELECT count(*) INTO v_devices FROM eligible_devices;

  IF NOT p_dry_run THEN
    DELETE FROM public.analytics_devices d
    WHERE d.last_seen_at < (
        now() - make_interval(days => COALESCE(
          (SELECT ts.hot_retention_days FROM public.tenant_analytics_settings ts WHERE ts.tenant_id = d.tenant_id),
          v_default_days
        ))
      )
      AND NOT EXISTS (SELECT 1 FROM public.analytics_sessions s WHERE s.device_id = d.id)
      AND NOT EXISTS (SELECT 1 FROM public.analytics_events e WHERE e.device_id = d.id);
  END IF;

  RETURN QUERY SELECT v_events, v_sessions, v_devices;
END;
$$;

REVOKE ALL ON FUNCTION public.prune_analytics_hot_store(BOOLEAN) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.prune_analytics_hot_store(BOOLEAN) TO service_role;

-- ---------------------------------------------------------------------------
-- 3. Viewer summary RPCs. SECURITY INVOKER so RLS applies: a tenant admin only
--    ever sees their own tenant's rows (the SELECT policies from 20260617140000).
--    p_tenant_id scopes the query; p_days bounds the window (clamped 1..90).
--    Cross-tenant/superadmin access is intentionally NOT provided here (raw hot
--    store is tenant-only; cross-tenant analytics come from the warehouse).
-- ---------------------------------------------------------------------------

-- 3a. Headline KPIs for the window.
CREATE OR REPLACE FUNCTION public.get_product_usage_summary(
  p_tenant_id UUID,
  p_days INTEGER DEFAULT 30
)
RETURNS TABLE (
  total_events BIGINT,
  total_sessions BIGINT,
  total_devices BIGINT,
  authenticated_sessions BIGINT,
  guest_sessions BIGINT,
  avg_session_seconds NUMERIC,
  page_views BIGINT
)
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path = public
AS $$
  WITH w AS (SELECT (now() - make_interval(days => LEAST(GREATEST(p_days, 1), 90))) AS since)
  SELECT
    (SELECT count(*) FROM public.analytics_events e, w
       WHERE e.tenant_id = p_tenant_id AND e.occurred_at >= w.since),
    (SELECT count(*) FROM public.analytics_sessions s, w
       WHERE s.tenant_id = p_tenant_id AND s.started_at >= w.since),
    (SELECT count(DISTINCT d.id) FROM public.analytics_devices d, w
       WHERE d.tenant_id = p_tenant_id AND d.last_seen_at >= w.since),
    (SELECT count(*) FROM public.analytics_sessions s, w
       WHERE s.tenant_id = p_tenant_id AND s.started_at >= w.since AND s.is_authenticated),
    (SELECT count(*) FROM public.analytics_sessions s, w
       WHERE s.tenant_id = p_tenant_id AND s.started_at >= w.since AND NOT s.is_authenticated),
    (SELECT COALESCE(round(avg(NULLIF(s.duration_seconds, 0))::numeric, 1), 0) FROM public.analytics_sessions s, w
       WHERE s.tenant_id = p_tenant_id AND s.started_at >= w.since),
    (SELECT count(*) FROM public.analytics_events e, w
       WHERE e.tenant_id = p_tenant_id AND e.occurred_at >= w.since AND e.event_type = 'page_view');
$$;

-- 3b. Daily time series (events + sessions per day).
CREATE OR REPLACE FUNCTION public.get_product_usage_timeseries(
  p_tenant_id UUID,
  p_days INTEGER DEFAULT 30
)
RETURNS TABLE (
  day DATE,
  events BIGINT,
  sessions BIGINT
)
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path = public
AS $$
  WITH days AS (
    SELECT generate_series(
      (now()::date - (LEAST(GREATEST(p_days, 1), 90) - 1)),
      now()::date,
      interval '1 day'
    )::date AS day
  ),
  ev AS (
    SELECT (e.occurred_at AT TIME ZONE 'UTC')::date AS day, count(*) AS c
    FROM public.analytics_events e
    WHERE e.tenant_id = p_tenant_id
      AND e.occurred_at >= (now()::date - (LEAST(GREATEST(p_days, 1), 90) - 1))
    GROUP BY 1
  ),
  se AS (
    SELECT (s.started_at AT TIME ZONE 'UTC')::date AS day, count(*) AS c
    FROM public.analytics_sessions s
    WHERE s.tenant_id = p_tenant_id
      AND s.started_at >= (now()::date - (LEAST(GREATEST(p_days, 1), 90) - 1))
    GROUP BY 1
  )
  SELECT d.day, COALESCE(ev.c, 0), COALESCE(se.c, 0)
  FROM days d
  LEFT JOIN ev ON ev.day = d.day
  LEFT JOIN se ON se.day = d.day
  ORDER BY d.day;
$$;

-- 3c. Top pages by view count.
CREATE OR REPLACE FUNCTION public.get_product_usage_top_pages(
  p_tenant_id UUID,
  p_days INTEGER DEFAULT 30,
  p_limit INTEGER DEFAULT 10
)
RETURNS TABLE (
  page_path TEXT,
  views BIGINT
)
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path = public
AS $$
  SELECT COALESCE(e.page_path, '(unknown)') AS page_path, count(*) AS views
  FROM public.analytics_events e
  WHERE e.tenant_id = p_tenant_id
    AND e.event_type = 'page_view'
    AND e.occurred_at >= (now() - make_interval(days => LEAST(GREATEST(p_days, 1), 90)))
  GROUP BY COALESCE(e.page_path, '(unknown)')
  ORDER BY views DESC
  LIMIT LEAST(GREATEST(p_limit, 1), 100);
$$;

-- 3d. Top named events (activity) by count.
CREATE OR REPLACE FUNCTION public.get_product_usage_top_events(
  p_tenant_id UUID,
  p_days INTEGER DEFAULT 30,
  p_limit INTEGER DEFAULT 10
)
RETURNS TABLE (
  event_name TEXT,
  occurrences BIGINT
)
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path = public
AS $$
  SELECT COALESCE(e.event_name, e.event_type) AS event_name, count(*) AS occurrences
  FROM public.analytics_events e
  WHERE e.tenant_id = p_tenant_id
    AND e.event_type <> 'page_view'
    AND e.occurred_at >= (now() - make_interval(days => LEAST(GREATEST(p_days, 1), 90)))
  GROUP BY COALESCE(e.event_name, e.event_type)
  ORDER BY occurrences DESC
  LIMIT LEAST(GREATEST(p_limit, 1), 100);
$$;

-- 3e. Recent sessions (most recent first) for the activity table.
CREATE OR REPLACE FUNCTION public.get_product_usage_recent_sessions(
  p_tenant_id UUID,
  p_limit INTEGER DEFAULT 25
)
RETURNS TABLE (
  id UUID,
  started_at TIMESTAMPTZ,
  last_activity_at TIMESTAMPTZ,
  is_authenticated BOOLEAN,
  duration_seconds INTEGER,
  page_view_count INTEGER,
  event_count INTEGER,
  entry_url TEXT
)
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path = public
AS $$
  SELECT s.id, s.started_at, s.last_activity_at, s.is_authenticated,
         s.duration_seconds, s.page_view_count, s.event_count, s.entry_url
  FROM public.analytics_sessions s
  WHERE s.tenant_id = p_tenant_id
  ORDER BY s.started_at DESC
  LIMIT LEAST(GREATEST(p_limit, 1), 200);
$$;

-- Expose the viewer RPCs to authenticated tenant admins (RLS still filters rows).
DO $$
DECLARE fn TEXT;
BEGIN
  FOREACH fn IN ARRAY ARRAY[
    'public.get_product_usage_summary(uuid, integer)',
    'public.get_product_usage_timeseries(uuid, integer)',
    'public.get_product_usage_top_pages(uuid, integer, integer)',
    'public.get_product_usage_top_events(uuid, integer, integer)',
    'public.get_product_usage_recent_sessions(uuid, integer)'
  ] LOOP
    EXECUTE format('REVOKE ALL ON FUNCTION %s FROM PUBLIC', fn);
    EXECUTE format('GRANT EXECUTE ON FUNCTION %s TO authenticated', fn);
  END LOOP;
END $$;
