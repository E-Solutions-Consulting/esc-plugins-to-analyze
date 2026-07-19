-- Phase 4 — hot-store retention for Product Usage analytics.
-- See docs/AnalyticsTracking.md §3.1 (hot -> warm -> cold) and §10 (prune handshake).
--
-- The hot store keeps ~5 days of behavioural data in Supabase; older data lives in
-- the BigQuery warehouse. This migration adds:
--   1. prune_analytics_hot_store(p_dry_run) — SECURITY DEFINER function that deletes
--      ONLY rows already exported to the warehouse AND older than the tenant's
--      hot_retention_days (export-before-prune handshake → no data loss).
--   2. A nightly pg_cron schedule, created ONLY if the pg_cron extension is present
--      (enabling pg_cron is a one-time Supabase dashboard/superuser step — see docs).
--
-- Idempotent: safe to re-run.

-- ---------------------------------------------------------------------------
-- 1. Prune function
-- ---------------------------------------------------------------------------
-- Retention is per-tenant: the effective hot_retention_days is the tenant override
-- if present, else the platform-default row (tenant_id IS NULL), else 5.
-- Only exported rows are eligible, so an export outage delays pruning rather than
-- losing data. Events are pruned first, then sessions/devices with no remaining
-- referencing events. Returns the number of rows deleted per table (one row).
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
  SELECT COALESCE(hot_retention_days, 5) INTO v_default_days
  FROM public.tenant_analytics_settings
  WHERE tenant_id IS NULL
  LIMIT 1;
  v_default_days := COALESCE(v_default_days, 5);

  -- Per-tenant cutoff = now() - effective hot_retention_days.
  -- An event is eligible iff exported AND its event_date is older than its tenant's cutoff.
  WITH eligible AS (
    SELECT e.id
    FROM public.analytics_events e
    LEFT JOIN public.tenant_analytics_settings ts
      ON ts.tenant_id = e.tenant_id
    WHERE e.exported_to_warehouse = true
      AND e.event_date < (now()::date - COALESCE(ts.hot_retention_days, v_default_days))
  )
  SELECT count(*) INTO v_events FROM eligible;

  IF NOT p_dry_run THEN
    DELETE FROM public.analytics_events e
    USING public.tenant_analytics_settings ts
    WHERE ts.tenant_id = e.tenant_id
      AND e.exported_to_warehouse = true
      AND e.event_date < (now()::date - COALESCE(ts.hot_retention_days, v_default_days));
    -- Events for tenants with no override row (use platform default).
    DELETE FROM public.analytics_events e
    WHERE e.exported_to_warehouse = true
      AND NOT EXISTS (SELECT 1 FROM public.tenant_analytics_settings ts WHERE ts.tenant_id = e.tenant_id)
      AND e.event_date < (now()::date - v_default_days);
  END IF;

  -- Sessions: prune exported, ended/old sessions that have no remaining events.
  WITH eligible_sessions AS (
    SELECT s.id
    FROM public.analytics_sessions s
    LEFT JOIN public.tenant_analytics_settings ts ON ts.tenant_id = s.tenant_id
    WHERE s.exported_to_warehouse = true
      AND s.started_at < (now() - make_interval(days => COALESCE(ts.hot_retention_days, v_default_days)))
      AND NOT EXISTS (SELECT 1 FROM public.analytics_events e WHERE e.session_id = s.id)
  )
  SELECT count(*) INTO v_sessions FROM eligible_sessions;

  IF NOT p_dry_run THEN
    DELETE FROM public.analytics_sessions s
    WHERE s.exported_to_warehouse = true
      AND s.started_at < (
        now() - make_interval(days => COALESCE(
          (SELECT ts.hot_retention_days FROM public.tenant_analytics_settings ts WHERE ts.tenant_id = s.tenant_id),
          v_default_days
        ))
      )
      AND NOT EXISTS (SELECT 1 FROM public.analytics_events e WHERE e.session_id = s.id);
  END IF;

  -- Devices: prune devices not seen within the retention window and with no
  -- remaining sessions/events (orphans only).
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

-- Only the service role (edge functions / warehouse export job) and superadmin
-- tooling should run the prune. Never expose to the anon/authenticated roles.
REVOKE ALL ON FUNCTION public.prune_analytics_hot_store(BOOLEAN) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.prune_analytics_hot_store(BOOLEAN) TO service_role;

-- ---------------------------------------------------------------------------
-- 2. Schedule nightly prune via pg_cron.
--    pg_cron is enabled on the target Supabase project (Dashboard → Database →
--    Extensions). We best-effort ensure the extension first (idempotent no-op
--    when already enabled, swallowed if the migration role lacks CREATE EXTENSION
--    privilege), then schedule. If pg_cron is genuinely absent the block degrades
--    to a NOTICE and the prune can instead be driven by the Phase-5 warehouse
--    export job (which calls prune_analytics_hot_store() right after a load).
-- ---------------------------------------------------------------------------
DO $$
BEGIN
  BEGIN
    CREATE EXTENSION IF NOT EXISTS pg_cron;
  EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'Could not CREATE EXTENSION pg_cron (%): relying on it being pre-enabled.', SQLERRM;
  END;

  IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
    -- Unschedule a prior definition if present (idempotent re-run).
    PERFORM cron.unschedule('analytics-hot-store-prune')
    WHERE EXISTS (SELECT 1 FROM cron.job WHERE jobname = 'analytics-hot-store-prune');

    PERFORM cron.schedule(
      'analytics-hot-store-prune',
      '17 3 * * *', -- 03:17 UTC daily (after the daily warehouse export window)
      $cron$ SELECT public.prune_analytics_hot_store(false); $cron$
    );
    RAISE NOTICE 'Scheduled analytics-hot-store-prune via pg_cron.';
  ELSE
    RAISE NOTICE 'pg_cron not installed — skipping schedule. Enable pg_cron and re-run, or drive prune from the warehouse export job.';
  END IF;
END $$;
