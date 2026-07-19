-- rtdh_event_payloads stores normalized RTDH webhook event payloads.
CREATE TABLE IF NOT EXISTS public.rtdh_event_payloads (
  id                         UUID        PRIMARY KEY DEFAULT gen_random_uuid(),

  -- Request metadata
  request_id                 TEXT,
  request_method             TEXT,
  request_url                TEXT,
  request_pathname           TEXT,
  request_search             TEXT,
  request_headers            JSONB       NOT NULL DEFAULT '{}'::jsonb,

  -- Raw payload capture
  raw_content                TEXT,
  raw_content_length         INTEGER,
  raw_read_error             TEXT,
  raw_parse_error            TEXT,

  -- Core event fields
  event_payload              JSONB       NOT NULL,
  schema_version             TEXT,
  master_order_id            TEXT,
  internal_tenant_id         TEXT,
  source_systems             TEXT[]      NOT NULL DEFAULT '{}',
  global_status              TEXT,
  status_provider            TEXT,
  event_updated_at           TIMESTAMPTZ,

  -- Structured subdocuments
  ids                        JSONB,
  customer                   JSONB,
  provider                   JSONB,
  subscription               JSONB,
  payment                    JSONB,
  prescription               JSONB,
  fulfillment                JSONB,
  shipping                   JSONB,
  products                   JSONB,
  status_rollup              JSONB,
  timeline                   JSONB,

  -- Common IDs for indexed lookup
  telegra_order_id           TEXT,
  patient_platform_order_id  TEXT,
  telegra_transaction_id     TEXT,
  stripe_subscription_id     TEXT,
  stripe_invoice_id          TEXT,
  stripe_payment_intent_id   TEXT,
  lifefile_rx_number         TEXT,
  easypost_tracking_code     TEXT,
  mdi_case_id                TEXT,

  -- Processing state
  is_processed               BOOLEAN     NOT NULL DEFAULT false,
  processed_at               TIMESTAMPTZ,
  processing_error           TEXT,

  created_at                 TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Indexes
CREATE INDEX IF NOT EXISTS rtdh_event_payloads_is_processed_created_at_idx
  ON public.rtdh_event_payloads (is_processed, created_at DESC);

CREATE INDEX IF NOT EXISTS rtdh_event_payloads_master_order_id_idx
  ON public.rtdh_event_payloads (master_order_id);

CREATE INDEX IF NOT EXISTS rtdh_event_payloads_internal_tenant_id_idx
  ON public.rtdh_event_payloads (internal_tenant_id);

CREATE INDEX IF NOT EXISTS rtdh_event_payloads_event_updated_at_idx
  ON public.rtdh_event_payloads (event_updated_at DESC);

CREATE INDEX IF NOT EXISTS rtdh_event_payloads_telegra_order_id_idx
  ON public.rtdh_event_payloads (telegra_order_id);

CREATE INDEX IF NOT EXISTS rtdh_event_payloads_patient_platform_order_id_idx
  ON public.rtdh_event_payloads (patient_platform_order_id);

CREATE INDEX IF NOT EXISTS rtdh_event_payloads_easypost_tracking_code_idx
  ON public.rtdh_event_payloads (easypost_tracking_code);

-- Row level security
ALTER TABLE public.rtdh_event_payloads ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename  = 'rtdh_event_payloads'
      AND policyname = 'service_role_full_access_rtdh_event_payloads'
  ) THEN
    CREATE POLICY "service_role_full_access_rtdh_event_payloads"
      ON public.rtdh_event_payloads
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END $$;