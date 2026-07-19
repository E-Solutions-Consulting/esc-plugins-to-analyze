ALTER TABLE public.medications
ADD COLUMN IF NOT EXISTS offering_id TEXT;

COMMENT ON COLUMN public.medications.offering_id IS
  'MD Integrations offering_id used when creating MDI case offerings from linked medications.';
