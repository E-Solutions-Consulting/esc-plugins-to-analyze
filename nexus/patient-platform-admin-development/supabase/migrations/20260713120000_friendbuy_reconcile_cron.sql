
DO $$
BEGIN
  BEGIN
    CREATE EXTENSION IF NOT EXISTS pg_net;
  EXCEPTION WHEN OTHERS THEN
    RAISE NOTICE 'Could not CREATE EXTENSION pg_net (%): relying on it being pre-enabled.', SQLERRM;
  END;
END $$;


CREATE OR REPLACE FUNCTION public.invoke_friendbuy_reconcile()
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, vault, net, extensions
AS $$
DECLARE
  v_base_url  text;
  v_secret    text;
  v_tenant    uuid;
BEGIN
  -- pg_net must be installed to make the call.
  IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_net') THEN
    RAISE NOTICE 'pg_net not installed — skipping friendbuy-reconcile invocation.';
    RETURN;
  END IF;

  SELECT decrypted_secret INTO v_base_url
  FROM vault.decrypted_secrets WHERE name = 'project_url' LIMIT 1;

  -- Shared with outbound-webhook-sweeper: this is the project-wide CRON_SECRET.
  SELECT decrypted_secret INTO v_secret
  FROM vault.decrypted_secrets WHERE name = 'outbound_sweeper_cron_secret' LIMIT 1;

  IF v_base_url IS NULL OR v_secret IS NULL THEN
    RAISE NOTICE 'project_url / outbound_sweeper_cron_secret not in vault — skipping.';
    RETURN;
  END IF;


  FOR v_tenant IN
    SELECT tenant_id
    FROM public.tenant_integrations
    WHERE integration_key = 'friendbuy'
      AND is_enabled = true
  LOOP
    PERFORM net.http_post(
      url     := rtrim(v_base_url, '/') || '/functions/v1/friendbuy-reconcile',
      headers := jsonb_build_object(
        'Content-Type', 'application/json',
        'Authorization', 'Bearer ' || v_secret
      ),
      body    := jsonb_build_object('tenant_id', v_tenant),
      timeout_milliseconds := 55000
    );
  END LOOP;
END;
$$;

REVOKE ALL ON FUNCTION public.invoke_friendbuy_reconcile() FROM PUBLIC;

-- 3) Schedule it hourly (idempotent). Plenty fresh for a referrals list, and
--    trivial load at our tenant count.
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
    PERFORM cron.unschedule('friendbuy-reconcile')
    WHERE EXISTS (SELECT 1 FROM cron.job WHERE jobname = 'friendbuy-reconcile');

    PERFORM cron.schedule(
      'friendbuy-reconcile',
      '0 * * * *', -- top of every hour
      $cron$ SELECT public.invoke_friendbuy_reconcile(); $cron$
    );
    RAISE NOTICE 'Scheduled friendbuy-reconcile via pg_cron (hourly).';
  ELSE
    RAISE NOTICE 'pg_cron not installed — enable it and re-run this migration to schedule friendbuy-reconcile.';
  END IF;
END $$;
