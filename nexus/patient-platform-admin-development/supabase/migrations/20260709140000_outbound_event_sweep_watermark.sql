-- Outbound Webhooks producer watermark.
--
-- The `outbound-webhook-sweeper` edge function reads new order_status_history +
-- subscription_events rows since these per-source watermarks and forwards them
-- to `outbound-webhook-dispatcher` for fan-out. Kept SEPARATE from
-- comms_event_sweep_state so Outbound Webhooks have no dependency on the
-- Communications Automations feature (either can be provisioned without the
-- other). This project has no pg_net, so the DB cannot call edge functions from
-- a trigger — the sweeper is invoked on a cron (see the operational note in
-- docs/OutboundWebhooksAPI.md) and pulls committed rows instead.

CREATE TABLE IF NOT EXISTS public.outbound_event_sweep_state (
  source        TEXT PRIMARY KEY,           -- 'order_status_history' | 'subscription_events'
  last_swept_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed both sources at "now" so the first tick doesn't replay all history.
INSERT INTO public.outbound_event_sweep_state (source, last_swept_at)
VALUES ('order_status_history', now()), ('subscription_events', now())
ON CONFLICT (source) DO NOTHING;

COMMENT ON TABLE public.outbound_event_sweep_state IS
  'Per-source high-water marks for the outbound-webhook-sweeper. Independent of comms_event_sweep_state.';

-- Service-role only (the sweeper uses the service key). Enable RLS with no
-- policies so the table is not reachable by tenant/anon roles.
ALTER TABLE public.outbound_event_sweep_state ENABLE ROW LEVEL SECURITY;
