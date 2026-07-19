CREATE TABLE IF NOT EXISTS public.rtdh_telegra_payloads (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID REFERENCES public.tenants(id) ON DELETE SET NULL,
  order_id UUID REFERENCES public.orders(id) ON DELETE SET NULL,
  raw_provider_order_id TEXT,
  raw_event_type TEXT,
  raw_status TEXT,
  raw_target_entity_status TEXT,
  raw_tracking_number TEXT,
  raw_tracking_url TEXT,
  raw_occurred_at TEXT,
  raw_shipped_at TEXT,
  raw_delivered_at TEXT,
  raw_cancelled_at TEXT,
  raw_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  request_headers JSONB NOT NULL DEFAULT '{}'::jsonb,
  request_id TEXT,
  is_processed BOOLEAN NOT NULL DEFAULT false,
  processed_at TIMESTAMPTZ,
  processing_error TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_rtdh_telegra_payloads_tenant_id
  ON public.rtdh_telegra_payloads(tenant_id);

CREATE INDEX IF NOT EXISTS idx_rtdh_telegra_payloads_order_id
  ON public.rtdh_telegra_payloads(order_id);

CREATE INDEX IF NOT EXISTS idx_rtdh_telegra_payloads_raw_provider_order_id
  ON public.rtdh_telegra_payloads(raw_provider_order_id)
  WHERE raw_provider_order_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_telegra_payloads_event_type
  ON public.rtdh_telegra_payloads(raw_event_type);

CREATE INDEX IF NOT EXISTS idx_rtdh_telegra_payloads_processed_created
  ON public.rtdh_telegra_payloads(is_processed, created_at DESC);

ALTER TABLE public.rtdh_telegra_payloads ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'rtdh_telegra_payloads'
      AND policyname = 'Tenant admins can manage Telegra payloads'
  ) THEN
    CREATE POLICY "Tenant admins can manage Telegra payloads"
      ON public.rtdh_telegra_payloads
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

COMMENT ON TABLE public.rtdh_telegra_payloads IS
  'Stores raw Telegra webhook payloads for all listened Telegra events.';

COMMENT ON COLUMN public.rtdh_telegra_payloads.created_at IS
  'Webhook payload receive timestamp. Defaults to now().';

COMMENT ON COLUMN public.rtdh_telegra_payloads.is_processed IS
  'False by default. Set to true after payload processing completes.';
