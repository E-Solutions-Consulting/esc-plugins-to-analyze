-- Create table for patient mood change tracking entries
CREATE TABLE public.patient_mood_change_entries (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  mood_change_definition_id UUID REFERENCES public.tenant_mood_change_definitions(id) ON DELETE SET NULL,
  mood_change_label TEXT NOT NULL,
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add indexes for performance
CREATE INDEX idx_patient_mood_change_entries_tenant_id ON public.patient_mood_change_entries(tenant_id);
CREATE INDEX idx_patient_mood_change_entries_patient_id ON public.patient_mood_change_entries(patient_id);
CREATE INDEX idx_patient_mood_change_entries_recorded_at ON public.patient_mood_change_entries(recorded_at);
CREATE INDEX idx_patient_mood_change_entries_definition_id
  ON public.patient_mood_change_entries(mood_change_definition_id);

-- Enable Row Level Security
ALTER TABLE public.patient_mood_change_entries ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage mood change entries for their tenants
CREATE POLICY "Tenant admins can manage patient mood change entries"
ON public.patient_mood_change_entries
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view their own mood change entries
CREATE POLICY "Patients can view their own mood change entries"
ON public.patient_mood_change_entries
FOR SELECT
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Patients can create their own mood change entries
CREATE POLICY "Patients can create their own mood change entries"
ON public.patient_mood_change_entries
FOR INSERT
WITH CHECK (
  patient_id = public.get_patient_by_auth_id(auth.uid())
  AND tenant_id = (
    SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
  )
);

-- Patients can delete their own mood change entries
CREATE POLICY "Patients can delete their own mood change entries"
ON public.patient_mood_change_entries
FOR DELETE
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Add trigger for updated_at
CREATE TRIGGER update_patient_mood_change_entries_updated_at
  BEFORE UPDATE ON public.patient_mood_change_entries
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.patient_mood_change_entries IS 'Patient-reported mood change tracking entries';
COMMENT ON COLUMN public.patient_mood_change_entries.mood_change_definition_id
  IS 'Reference to tenant mood change definition used for this entry';
