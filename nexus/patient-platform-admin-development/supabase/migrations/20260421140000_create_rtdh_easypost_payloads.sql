-- rtdh_easypost_payloads stores raw EasyPost webhook event payloads.
-- Columns mirror exactly the properties captured by easypost-webhook/index.ts:
-- requestId, method, url, pathname, search, headers, content, contentLength,
-- readError.
CREATE TABLE IF NOT EXISTS public.rtdh_easypost_payloads (
  id                   UUID        PRIMARY KEY DEFAULT gen_random_uuid(),

  -- Request identity
  request_id           TEXT,
  request_method       TEXT,
  request_url          TEXT,
  request_pathname     TEXT,
  request_search       TEXT,
  request_headers      JSONB       NOT NULL DEFAULT '{}',

  -- Body (stored as raw text, exactly as received)
  raw_content          TEXT,
  raw_content_length   INTEGER,
  raw_read_error       TEXT,

  -- Processing state
  is_processed         BOOLEAN     NOT NULL DEFAULT false,
  processed_at         TIMESTAMPTZ,
  processing_error     TEXT,

  created_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ── Indexes ───────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS rtdh_easypost_payloads_is_processed_created_at_idx
  ON public.rtdh_easypost_payloads (is_processed, created_at DESC);

-- ── Row Level Security ────────────────────────────────────────────────────
ALTER TABLE public.rtdh_easypost_payloads ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename  = 'rtdh_easypost_payloads'
      AND policyname = 'service_role_full_access_rtdh_easypost_payloads'
  ) THEN
    CREATE POLICY "service_role_full_access_rtdh_easypost_payloads"
      ON public.rtdh_easypost_payloads
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END $$;
