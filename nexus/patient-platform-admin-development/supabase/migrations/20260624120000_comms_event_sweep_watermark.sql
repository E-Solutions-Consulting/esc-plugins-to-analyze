-- Watermark for the comms-scheduler's event sweep.
--
-- subscription_events and order_status_history are written by DB triggers, and
-- this project has no pg_net (so triggers can't call edge functions). Instead the
-- comms-scheduler tick sweeps rows created since the last watermark and emits them
-- to comms-event-dispatcher. This table stores the per-source high-water mark.

CREATE TABLE IF NOT EXISTS public.comms_event_sweep_state (
  source TEXT PRIMARY KEY,            -- 'subscription_events' | 'order_status_history'
  last_swept_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed the two sources at "now" so the first tick doesn't replay all history.
INSERT INTO public.comms_event_sweep_state (source, last_swept_at)
VALUES ('subscription_events', now()), ('order_status_history', now())
ON CONFLICT (source) DO NOTHING;

-- Service-role only (the scheduler uses the service key). Enable RLS with no
-- policies so it is not reachable by tenant/anon roles.
ALTER TABLE public.comms_event_sweep_state ENABLE ROW LEVEL SECURITY;
