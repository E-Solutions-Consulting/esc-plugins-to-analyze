-- Schedule the outbound-webhook-sweeper edge function via pg_cron + pg_net.
--
-- The sweeper is the producer for lifecycle/subscription outbound webhook events
-- (see 20260709140000_outbound_event_sweep_watermark.sql and
-- docs/OutboundWebhooksAPI.md). It is an EDGE function, so the DB must reach it
-- over HTTP — that needs pg_net (this migration enables it). Rather than a manual
-- dashboard cron, we schedule it here so it ships with the migration.
--
-- Config is read from Vault (NOT hardcoded), so the same migration works across
-- dev/staging/prod. Seed these two secrets per environment BEFORE (or after —
-- the job simply no-ops until they exist) this runs:
--   * project_url                  -> e.g. https://<ref>.supabase.co
--   * outbound_sweeper_cron_secret -> the value of the CRON_SECRET env var set
--                                     on the edge functions (the sweeper checks
--                                     Authorization: Bearer <CRON_SECRET>)
-- Seed with:
--   SELECT vault.create_secret('https://<ref>.supabase.co', 'project_url');
--   SELECT vault.create_secret('<cron-secret>', 'outbound_sweeper_cron_secret');
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

-- 2) Callable that POSTs to the sweeper, pulling URL + secret from Vault at call
--    time. SECURITY DEFINER so the cron job (runs as the job owner) can read the
--    decrypted secrets. Silently no-ops if config/extensions are absent, so a
--    half-provisioned environment never errors the tick.
CREATE OR REPLACE FUNCTION public.invoke_outbound_webhook_sweeper()
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, vault, net, extensions
AS $$
DECLARE
  v_base_url   text;
  v_secret     text;
BEGIN
  -- pg_net must be installed to make the call.
  IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_net') THEN
    RAISE NOTICE 'pg_net not installed — skipping outbound-webhook-sweeper invocation.';
    RETURN;
  END IF;

  SELECT decrypted_secret INTO v_base_url
  FROM vault.decrypted_secrets WHERE name = 'project_url' LIMIT 1;

  SELECT decrypted_secret INTO v_secret
  FROM vault.decrypted_secrets WHERE name = 'outbound_sweeper_cron_secret' LIMIT 1;

  IF v_base_url IS NULL OR v_secret IS NULL THEN
    RAISE NOTICE 'project_url / outbound_sweeper_cron_secret not in vault — skipping.';
    RETURN;
  END IF;

  PERFORM net.http_post(
    url     := rtrim(v_base_url, '/') || '/functions/v1/outbound-webhook-sweeper',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer ' || v_secret
    ),
    body    := '{}'::jsonb,
    timeout_milliseconds := 55000
  );
END;
$$;

REVOKE ALL ON FUNCTION public.invoke_outbound_webhook_sweeper() FROM PUBLIC;

-- 3) Schedule it every minute (idempotent).
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
    PERFORM cron.unschedule('outbound-webhook-sweeper')
    WHERE EXISTS (SELECT 1 FROM cron.job WHERE jobname = 'outbound-webhook-sweeper');

    PERFORM cron.schedule(
      'outbound-webhook-sweeper',
      '* * * * *', -- every minute
      $cron$ SELECT public.invoke_outbound_webhook_sweeper(); $cron$
    );
    RAISE NOTICE 'Scheduled outbound-webhook-sweeper via pg_cron (every minute).';
  ELSE
    RAISE NOTICE 'pg_cron not installed — enable it and re-run this migration to schedule the sweeper.';
  END IF;
END $$;
