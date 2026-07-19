-- Create table for patient activity tracking entries
CREATE TABLE public.patient_activity_entries (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  activity_definition_id UUID REFERENCES public.tenant_activity_definitions(id) ON DELETE SET NULL,
  activity_label TEXT NOT NULL,
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add indexes for performance
CREATE INDEX idx_patient_activity_entries_tenant_id ON public.patient_activity_entries(tenant_id);
CREATE INDEX idx_patient_activity_entries_patient_id ON public.patient_activity_entries(patient_id);
CREATE INDEX idx_patient_activity_entries_recorded_at ON public.patient_activity_entries(recorded_at);
CREATE INDEX idx_patient_activity_entries_definition_id
  ON public.patient_activity_entries(activity_definition_id);

-- Enable Row Level Security
ALTER TABLE public.patient_activity_entries ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage activity entries for their tenants
CREATE POLICY "Tenant admins can manage patient activity entries"
ON public.patient_activity_entries
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view their own activity entries
CREATE POLICY "Patients can view their own activity entries"
ON public.patient_activity_entries
FOR SELECT
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Patients can create their own activity entries
CREATE POLICY "Patients can create their own activity entries"
ON public.patient_activity_entries
FOR INSERT
WITH CHECK (
  patient_id = public.get_patient_by_auth_id(auth.uid())
  AND tenant_id = (
    SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
  )
);

-- Patients can delete their own activity entries
CREATE POLICY "Patients can delete their own activity entries"
ON public.patient_activity_entries
FOR DELETE
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Add trigger for updated_at
CREATE TRIGGER update_patient_activity_entries_updated_at
  BEFORE UPDATE ON public.patient_activity_entries
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.patient_activity_entries IS 'Patient-reported activity tracking entries';
COMMENT ON COLUMN public.patient_activity_entries.activity_definition_id
  IS 'Reference to tenant activity definition used for this entry';
