-- Remove energy_note from patient energy tracking entries
ALTER TABLE public.patient_energy_entries
  DROP COLUMN IF EXISTS energy_note;
