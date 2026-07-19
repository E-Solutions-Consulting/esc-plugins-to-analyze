-- Deliverability tracking on comms_run_steps, fed by the Resend webhook
-- (comms-resend-webhook). We match a run step by its provider_message_id
-- (the Resend email id captured at send time).

ALTER TABLE public.comms_run_steps
  ADD COLUMN IF NOT EXISTS delivery_status TEXT,        -- delivered | opened | clicked | bounced | complained | delivery_delayed
  ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS opened_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS bounced_at TIMESTAMPTZ;

-- Look up steps by provider message id when a webhook arrives.
CREATE INDEX IF NOT EXISTS idx_comms_run_steps_provider_message_id
  ON public.comms_run_steps(provider_message_id)
  WHERE provider_message_id IS NOT NULL;
