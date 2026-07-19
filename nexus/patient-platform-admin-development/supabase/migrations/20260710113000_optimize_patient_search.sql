-- Support tenant admin patient search:
--   first_name ILIKE '%term%'
--   last_name ILIKE '%term%'
--   email ILIKE '%term%'
--   phone ILIKE '%term%'

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX IF NOT EXISTS idx_patients_first_name_trgm
  ON public.patients USING gin (first_name gin_trgm_ops)
  WHERE first_name IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_patients_last_name_trgm
  ON public.patients USING gin (last_name gin_trgm_ops)
  WHERE last_name IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_patients_email_trgm
  ON public.patients USING gin (email gin_trgm_ops)
  WHERE email IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_patients_phone_trgm
  ON public.patients USING gin (phone gin_trgm_ops)
  WHERE phone IS NOT NULL;
