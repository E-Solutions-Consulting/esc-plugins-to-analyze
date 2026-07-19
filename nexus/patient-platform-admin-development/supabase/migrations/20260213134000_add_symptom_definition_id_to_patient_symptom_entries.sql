-- Add symptom_definition_id to patient symptom entries
ALTER TABLE public.patient_symptom_entries
  ADD COLUMN IF NOT EXISTS symptom_definition_id UUID
  REFERENCES public.tenant_symptom_definitions(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_patient_symptom_entries_definition_id
  ON public.patient_symptom_entries(symptom_definition_id);

COMMENT ON COLUMN public.patient_symptom_entries.symptom_definition_id
  IS 'Reference to tenant symptom definition used for this entry';
