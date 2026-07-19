-- Product Usage analytics — raise the default hot-store retention 5 → 30 days.
--
-- Retention is resolved per-tenant in prune_analytics_hot_store() as:
--   tenant override → platform-default row (tenant_id IS NULL) → hardcoded fallback.
-- Originally all three layers said 5 days (see 20260617140000 / 20260617160000).
-- This migration moves the PLATFORM baseline to 30 days across every layer so the
-- code default, the seeded default row, and any tenant row still sitting on the old
-- default all agree — while leaving any deliberate per-tenant override untouched.
--
-- The prune is still export-gated (only rows with exported_to_warehouse = true are
-- deleted), so raising retention only widens the window; it never risks data loss.
--
-- Idempotent: safe to re-run.

-- ---------------------------------------------------------------------------
-- 1. Column default for fresh installs / newly inserted rows.
-- ---------------------------------------------------------------------------
ALTER TABLE public.tenant_analytics_settings
  ALTER COLUMN hot_retention_days SET DEFAULT 30;

-- ---------------------------------------------------------------------------
-- 2. Migrate existing rows that are still on the OLD default (5) to the new
--    baseline (30). Rows with any other value are treated as an intentional
--    per-tenant override and left as-is.
-- ---------------------------------------------------------------------------
UPDATE public.tenant_analytics_settings
   SET hot_retention_days = 30
 WHERE hot_retention_days = 5;

-- ---------------------------------------------------------------------------
-- 3. Redefine the prune function with the fallback literal updated 5 → 30.
--    Body is otherwise identical to 20260617160000 (export-gated, per-tenant
--    cutoff, events → orphan sessions → orphan devices).
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

REVOKE ALL ON FUNCTION public.prune_analytics_hot_store(BOOLEAN) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.prune_analytics_hot_store(BOOLEAN) TO service_role;
