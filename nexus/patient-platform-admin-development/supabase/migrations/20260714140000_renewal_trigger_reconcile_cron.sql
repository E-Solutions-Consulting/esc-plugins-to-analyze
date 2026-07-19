-- Schedule the renewal-trigger reconciliation sweep. Mirrors
-- friendbuy_reconcile_cron: pg_cron calls a SECURITY DEFINER function that uses
-- pg_net to invoke the edge function, authenticated with the shared
-- outbound_sweeper_cron_secret (sent as CRON_SECRET).
DO $$
BEGIN
  BEGIN
    CREATE EXTENSION IF NOT EXISTS pg_net;
  EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'Could not CREATE EXTENSION pg_net (%): relying on it being pre-enabled.', SQLERRM;
  END;
END $$;


CREATE OR REPLACE FUNCTION public.invoke_renewal_trigger_reconcile()
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, vault, net, extensions
AS $$
DECLARE
  v_base_url  text;
  v_secret    text;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_net') THEN
    RAISE NOTICE 'pg_net not installed — skipping renewal-trigger-reconcile invocation.';
    RETURN;
  END IF;

  SELECT decrypted_secret INTO v_base_url
  FROM vault.decrypted_secrets WHERE name = 'project_url' LIMIT 1;

  -- Shared project-wide CRON_SECRET (same secret used by other sweeps).
  SELECT decrypted_secret INTO v_secret
  FROM vault.decrypted_secrets WHERE name = 'outbound_sweeper_cron_secret' LIMIT 1;

  IF v_base_url IS NULL OR v_secret IS NULL THEN
    RAISE NOTICE 'project_url / outbound_sweeper_cron_secret not in vault — skipping.';
    RETURN;
  END IF;

  PERFORM net.http_post(
    url     := rtrim(v_base_url, '/') || '/functions/v1/renewal-trigger-reconcile',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer ' || v_secret
    ),
    body    := '{}'::jsonb,
    timeout_milliseconds := 55000
  );
END;
$$;

REVOKE ALL ON FUNCTION public.invoke_renewal_trigger_reconcile() FROM PUBLIC;

-- Schedule every 5 minutes (idempotent). Manual triggers are low-volume; a
-- 5-minute cadence comfortably fits the 15-minute grace window.
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
    PERFORM cron.unschedule('renewal-trigger-reconcile')
    WHERE EXISTS (SELECT 1 FROM cron.job WHERE jobname = 'renewal-trigger-reconcile');

    PERFORM cron.schedule(
      'renewal-trigger-reconcile',
      '*/5 * * * *',
      $cron$ SELECT public.invoke_renewal_trigger_reconcile(); $cron$
    );
    RAISE NOTICE 'Scheduled renewal-trigger-reconcile via pg_cron (every 5 minutes).';
  ELSE
    RAISE NOTICE 'pg_cron not installed — enable it and re-run this migration to schedule renewal-trigger-reconcile.';
  END IF;
END $$;
