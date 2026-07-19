-- Add configurable guardrail for how early an admin may move/trigger a renewal.
ALTER TABLE public.products
  ADD COLUMN IF NOT EXISTS renewal_advance_max_weeks INTEGER;

UPDATE public.products
SET renewal_advance_max_weeks = 2
WHERE renewal_advance_max_weeks IS NULL;

ALTER TABLE public.products
  ALTER COLUMN renewal_advance_max_weeks SET DEFAULT 2,
  ALTER COLUMN renewal_advance_max_weeks SET NOT NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'products_renewal_advance_max_weeks_check'
      AND conrelid = 'public.products'::regclass
  ) THEN
    ALTER TABLE public.products
      ADD CONSTRAINT products_renewal_advance_max_weeks_check
      CHECK (renewal_advance_max_weeks >= 0);
  END IF;
END $$;

COMMENT ON COLUMN public.products.renewal_advance_max_weeks IS
  'Max number of weeks before end of cycle that an admin may move/trigger a renewal.';
