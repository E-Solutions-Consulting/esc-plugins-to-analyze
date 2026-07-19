-- Persist the n8n webhook PATH alongside the production URL.
--
-- n8n exposes two URLs per webhook, and only one of them works unattended:
--   production  <webhook-host>/webhook/<path>       — live only while the workflow is ACTIVE
--   test        <webhook-host>/webhook-test/<path>  — live only while the n8n editor has
--                                                     "Listen for test event" armed (one shot)
--
-- `webhook_url` stores the production URL. Keeping the raw path lets us build the
-- test URL for the node inspector's test-mode toggle without string-surgery on the
-- stored URL (which would silently break if the webhook host ever changes).
--
-- Backfilled from the existing URL: everything after the last '/webhook/'.

ALTER TABLE public.comms_n8n_webhooks
  ADD COLUMN IF NOT EXISTS webhook_path TEXT;

UPDATE public.comms_n8n_webhooks
SET webhook_path = regexp_replace(webhook_url, '^.*/webhook(?:-test)?/', '')
WHERE webhook_path IS NULL
  AND webhook_url ~ '/webhook(?:-test)?/';

COMMENT ON COLUMN public.comms_n8n_webhooks.webhook_path IS
  'The n8n webhook path segment. Production URL is <webhook-host>/webhook/<path>; the test URL is <webhook-host>/webhook-test/<path>.';
