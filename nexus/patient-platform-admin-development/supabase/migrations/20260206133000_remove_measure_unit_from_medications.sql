-- Remove measure_unit from medications
ALTER TABLE public.medications
  DROP CONSTRAINT IF EXISTS medications_measure_unit_check;

ALTER TABLE public.medications
  DROP COLUMN IF EXISTS measure_unit;
