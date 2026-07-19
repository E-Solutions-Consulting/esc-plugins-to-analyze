-- Schedule the comms-scheduler edge function via pg_cron + pg_net.
--
-- WHY THIS EXISTS: comms-scheduler was NEVER invoked. Its own header claimed it
-- was "cron-invoked (see config.toml schedule)", but Supabase's config.toml has
-- no schedule key — pg_cron is the only mechanism, and the migration that would
-- have scheduled it was never written. The function was deployed, authenticated
-- and correct; nothing ever called it. Consequences, in every environment:
--   * order/subscription automation triggers NEVER fired (comms-scheduler's
--     sweepDomainEvents is their ONLY producer),
--   * delay / wait_until nodes never resumed,
--   * relative-time triggers were never materialised,
--   * stuck enrollments were never recovered.
--
-- Mirrors 20260709150000_outbound_webhook_sweeper_cron.sql exactly, and reuses
-- the SAME vault secrets (project_url + outbound_sweeper_cron_secret).
-- comms-scheduler authenticates with CRON_SECRET — the same value already set on
-- the edge functions in dev/staging/prod, and already shared by the friendbuy and
-- renewal-reconcile crons. NO new secret to provision in any environment.
--
-- Idempotent: re-running unschedules the prior definition first.

-- 1) Enable pg_net (HTTP from SQL). No-op if already present.
DO $$
BEGIN
  BEGIN
    CREATE EXTENSION IF NOT EXISTS pg_net;
  EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'Could not CREATE EXTENSION pg_net (%): relying on it being pre-enabled.', SQLERRM;
  END;
END $$;

-- 2) BACKLOG GUARD — MUST run BEFORE the job is scheduled.
--
--    comms_event_sweep_state was seeded at now() on 2026-06-24 and has never
--    advanced, because nothing ever ran the sweep. Scheduling the cron without
--    this would make the FIRST tick replay every order_status_history and
--    subscription_events row committed since then — enrolling real patients into
--    any live automation and REALLY sending them email/SMS.
--
--    History that predates a working scheduler is not a comms event. Fast-forward
--    both watermarks so the sweep starts from a clean edge. This is deliberate and
--    one-time: it discards a backlog that was never deliverable in the first place.
INSERT INTO public.comms_event_sweep_state (source, last_swept_at, updated_at)
VALUES ('subscription_events', now(), now()),
       ('order_status_history', now(), now())
ON CONFLICT (source) DO UPDATE
  SET last_swept_at = now(),
      updated_at    = now();

-- 3) Callable that POSTs to comms-scheduler, pulling URL + secret from Vault at
--    call time. SECURITY DEFINER so the cron job (running as the job owner) can
--    read the decrypted secrets. Silently no-ops when pg_net or the vault secrets
--    are absent, so a half-provisioned environment never errors the tick.
CREATE OR REPLACE FUNCTION public.invoke_comms_scheduler()
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, vault, net, extensions
AS $$
DECLARE
  v_base_url text;
  v_secret   text;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_net') THEN
    RAISE NOTICE 'pg_net not installed — skipping comms-scheduler invocation.';
    RETURN;
  END IF;

  SELECT decrypted_secret INTO v_base_url
  FROM vault.decrypted_secrets WHERE name = 'project_url' LIMIT 1;

  -- Shared project-wide CRON_SECRET (the same secret the other sweeps use).
  SELECT decrypted_secret INTO v_secret
  FROM vault.decrypted_secrets WHERE name = 'outbound_sweeper_cron_secret' LIMIT 1;

  IF v_base_url IS NULL OR v_secret IS NULL THEN
    RAISE NOTICE 'project_url / outbound_sweeper_cron_secret not in vault — skipping.';
    RETURN;
  END IF;

  PERFORM net.http_post(
    url     := rtrim(v_base_url, '/') || '/functions/v1/comms-scheduler',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer ' || v_secret
    ),
    body    := '{}'::jsonb,
    timeout_milliseconds := 55000
  );
END;
$$;

REVOKE ALL ON FUNCTION public.invoke_comms_scheduler() FROM PUBLIC;

-- 4) Schedule every minute (idempotent). Same cadence as outbound-webhook-sweeper,
--    which sweeps the same two source tables. One minute is pg_cron's floor.
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
    PERFORM cron.unschedule('comms-scheduler')
    WHERE EXISTS (SELECT 1 FROM cron.job WHERE jobname = 'comms-scheduler');

    PERFORM cron.schedule(
      'comms-scheduler',
      '* * * * *', -- every minute
      $cron$ SELECT public.invoke_comms_scheduler(); $cron$
    );
    RAISE NOTICE 'Scheduled comms-scheduler via pg_cron (every minute).';
  ELSE
    RAISE NOTICE 'pg_cron not installed — enable it and re-run this migration to schedule comms-scheduler.';
  END IF;
END $$;
