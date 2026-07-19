-- rtdh_lifefile_payloads stores raw LifeFile webhook event payloads.
-- Columns mirror the payload properties used across all currently handled
-- LifeFile events plus request metadata for replay/debugging.
CREATE TABLE IF NOT EXISTS public.rtdh_lifefile_payloads (
  id                   UUID        PRIMARY KEY DEFAULT gen_random_uuid(),

  -- Request identity
  request_id           TEXT,
  webhook_request_id   TEXT,
  request_method       TEXT,
  request_url          TEXT,
  request_pathname     TEXT,
  request_search       TEXT,
  request_headers      JSONB       NOT NULL DEFAULT '{}',

  -- Body (stored exactly as received)
  raw_content          TEXT,
  raw_content_length   INTEGER,
  raw_read_error       TEXT,
  raw_parse_error      TEXT,

  -- Event payload and extracted fields
  event_payload        JSONB,
  lifefile_order_id    TEXT,
  lifefile_fill_id     TEXT,
  rx_number            TEXT,
  rx_status            TEXT,
  order_status         TEXT,
  order_reference_id   TEXT,
  patient_email        TEXT,
  tracking_number      TEXT,

  -- Processing state
  is_processed         BOOLEAN     NOT NULL DEFAULT false,
  processed_at         TIMESTAMPTZ,
  processing_error     TEXT,

  -- Reception timestamp
  received_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ── Indexes ───────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS rtdh_lifefile_payloads_is_processed_received_at_idx
  ON public.rtdh_lifefile_payloads (is_processed, received_at DESC);

CREATE INDEX IF NOT EXISTS rtdh_lifefile_payloads_lifefile_order_id_idx
  ON public.rtdh_lifefile_payloads (lifefile_order_id);

CREATE INDEX IF NOT EXISTS rtdh_lifefile_payloads_webhook_request_id_idx
  ON public.rtdh_lifefile_payloads (webhook_request_id);

-- ── Row Level Security ────────────────────────────────────────────────────
ALTER TABLE public.rtdh_lifefile_payloads ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename  = 'rtdh_lifefile_payloads'
      AND policyname = 'service_role_full_access_rtdh_lifefile_payloads'
  ) THEN
    CREATE POLICY "service_role_full_access_rtdh_lifefile_payloads"
      ON public.rtdh_lifefile_payloads
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END $$;
