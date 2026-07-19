-- Rename medication form value from injectible_solution to injectable_solution
-- for databases that already ran earlier migrations.
ALTER TABLE public.medications
  DROP CONSTRAINT IF EXISTS medications_form_check;

UPDATE public.medications
SET form = 'injectable_solution'
WHERE form = 'injectible_solution';

UPDATE public.medications
SET form = 'tablets'
WHERE form IS NULL OR form NOT IN ('tablets', 'injectable_solution', 'spray');

ALTER TABLE public.medications
  ALTER COLUMN form SET DEFAULT 'tablets';

ALTER TABLE public.medications
  ALTER COLUMN form SET NOT NULL;

ALTER TABLE public.medications
  ADD CONSTRAINT medications_form_check
  CHECK (form IN ('tablets', 'injectable_solution', 'spray'));
