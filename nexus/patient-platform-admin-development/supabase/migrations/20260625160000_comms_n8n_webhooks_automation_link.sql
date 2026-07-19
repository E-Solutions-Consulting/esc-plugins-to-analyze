-- Link a registered n8n webhook/workflow to the automation it belongs to, so we
-- can auto-create + reuse one workflow per automation and resolve editor links.

ALTER TABLE public.comms_n8n_webhooks
  ADD COLUMN IF NOT EXISTS automation_id UUID
    REFERENCES public.comms_automations(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_comms_n8n_webhooks_automation_id
  ON public.comms_n8n_webhooks(automation_id)
  WHERE automation_id IS NOT NULL;

-- One active auto-created workflow per automation (manual registrations have NULL
-- automation_id and are unaffected).
CREATE UNIQUE INDEX IF NOT EXISTS uq_comms_n8n_webhooks_automation_active
  ON public.comms_n8n_webhooks(automation_id)
  WHERE automation_id IS NOT NULL AND is_active = true;
