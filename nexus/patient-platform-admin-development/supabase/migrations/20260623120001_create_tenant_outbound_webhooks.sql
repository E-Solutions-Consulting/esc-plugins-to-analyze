-- Outbound webhooks: let a tenant forward selected platform events to an
-- external endpoint (n8n, Attentive, marketing/automation engines, etc.).
--
-- Two DISTINCT, non-mixable webhook types:
--   * 'lifecycle'      - order/subscription/provider lifecycle events
--   * 'product_usage'  - product-usage / analytics events from the patient app
-- A webhook subscribes to events of EXACTLY ONE type; the event keys it may
-- select are scoped to that type (enforced in the app + dispatcher). Keeping the
-- type on the row lets the dispatcher route each event source to only the
-- webhooks of the matching type.
--
-- Delivery mechanism is intentionally pluggable: the dispatcher edge function
-- reads enabled webhooks for an event and POSTs a signed payload. This can later
-- be repointed to deliver via RTDH -> Pub/Sub fan-out without schema changes
-- (the row model is delivery-agnostic).

CREATE TABLE IF NOT EXISTS public.tenant_outbound_webhooks (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  name            TEXT NOT NULL,
  webhook_type    TEXT NOT NULL CHECK (webhook_type IN ('lifecycle', 'product_usage')),
  target_url      TEXT NOT NULL,
  -- Per-endpoint signing secret used to HMAC-sign the delivered payload.
  signing_secret  TEXT NOT NULL,
  -- Event keys this endpoint subscribes to. Must all belong to webhook_type's
  -- catalog (validated in the app/dispatcher; kept as text[] for flexibility).
  event_keys      TEXT[] NOT NULL DEFAULT '{}',
  is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_by      UUID NULL
);

CREATE INDEX IF NOT EXISTS idx_outbound_webhooks_tenant_type
  ON public.tenant_outbound_webhooks (tenant_id, webhook_type, is_enabled);

COMMENT ON TABLE public.tenant_outbound_webhooks IS
  'Tenant-configured outbound webhooks. webhook_type is fixed per row (lifecycle | product_usage) and event_keys must belong to that type.';
COMMENT ON COLUMN public.tenant_outbound_webhooks.webhook_type IS
  'lifecycle = order/subscription/provider events; product_usage = analytics/usage events. Event types must not be mixed on one webhook.';

-- Delivery log (recent attempts; useful for the UI + debugging/retries).
CREATE TABLE IF NOT EXISTS public.tenant_outbound_webhook_deliveries (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  webhook_id      UUID NOT NULL REFERENCES public.tenant_outbound_webhooks(id) ON DELETE CASCADE,
  tenant_id       UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  event_key       TEXT NOT NULL,
  status_code     INTEGER NULL,
  success         BOOLEAN NOT NULL DEFAULT FALSE,
  attempts        INTEGER NOT NULL DEFAULT 1,
  error           TEXT NULL,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_webhook
  ON public.tenant_outbound_webhook_deliveries (webhook_id, created_at DESC);

-- updated_at maintenance
CREATE OR REPLACE FUNCTION public.touch_tenant_outbound_webhooks_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_outbound_webhooks_updated_at ON public.tenant_outbound_webhooks;
CREATE TRIGGER trg_outbound_webhooks_updated_at
  BEFORE UPDATE ON public.tenant_outbound_webhooks
  FOR EACH ROW EXECUTE FUNCTION public.touch_tenant_outbound_webhooks_updated_at();

-- RLS: tenant admins manage their own tenant's webhooks; service role (the
-- dispatcher) has full access.
ALTER TABLE public.tenant_outbound_webhooks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_outbound_webhook_deliveries ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS outbound_webhooks_tenant_admin ON public.tenant_outbound_webhooks;
CREATE POLICY outbound_webhooks_tenant_admin
  ON public.tenant_outbound_webhooks
  FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

DROP POLICY IF EXISTS webhook_deliveries_tenant_admin_read ON public.tenant_outbound_webhook_deliveries;
CREATE POLICY webhook_deliveries_tenant_admin_read
  ON public.tenant_outbound_webhook_deliveries
  FOR SELECT
  USING (public.is_tenant_admin(auth.uid(), tenant_id));
