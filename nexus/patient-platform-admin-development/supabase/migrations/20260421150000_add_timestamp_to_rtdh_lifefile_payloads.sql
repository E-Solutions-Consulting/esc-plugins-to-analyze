-- Add canonical timestamp column for integration consistency.
ALTER TABLE public.rtdh_lifefile_payloads
  ADD COLUMN IF NOT EXISTS "timestamp" TIMESTAMPTZ NOT NULL DEFAULT now();

CREATE INDEX IF NOT EXISTS rtdh_lifefile_payloads_is_processed_timestamp_idx
  ON public.rtdh_lifefile_payloads (is_processed, "timestamp" DESC);
