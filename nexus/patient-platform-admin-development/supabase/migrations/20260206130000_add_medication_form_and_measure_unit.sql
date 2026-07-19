-- Add required medication form/measure unit fields and constraints
ALTER TABLE public.medications
  ALTER COLUMN form SET DEFAULT 'tablets';

UPDATE public.medications
SET form = 'tablets'
WHERE form IS NULL OR form NOT IN ('tablets', 'injectable_solution', 'spray');

ALTER TABLE public.medications
  ALTER COLUMN form SET NOT NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'medications_form_check'
      AND conrelid = 'public.medications'::regclass
  ) THEN
    ALTER TABLE public.medications
      ADD CONSTRAINT medications_form_check
      CHECK (form IN ('tablets', 'injectable_solution', 'spray'));
  END IF;
END $$;
