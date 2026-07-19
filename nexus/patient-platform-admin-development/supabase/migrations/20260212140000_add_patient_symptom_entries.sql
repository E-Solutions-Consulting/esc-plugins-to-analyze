-- Create table for patient symptom tracking entries
CREATE TABLE public.patient_symptom_entries (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  symptom_label TEXT NOT NULL,
  symptom_severity INTEGER CHECK (symptom_severity >= 0 AND symptom_severity <= 10),
  symptom_note TEXT,
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add indexes for performance
CREATE INDEX idx_patient_symptom_entries_tenant_id ON public.patient_symptom_entries(tenant_id);
CREATE INDEX idx_patient_symptom_entries_patient_id ON public.patient_symptom_entries(patient_id);
CREATE INDEX idx_patient_symptom_entries_recorded_at ON public.patient_symptom_entries(recorded_at);

-- Enable Row Level Security
ALTER TABLE public.patient_symptom_entries ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage symptom entries for their tenants
CREATE POLICY "Tenant admins can manage patient symptom entries"
ON public.patient_symptom_entries
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view their own symptom entries
CREATE POLICY "Patients can view their own symptom entries"
ON public.patient_symptom_entries
FOR SELECT
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Patients can create their own symptom entries
CREATE POLICY "Patients can create their own symptom entries"
ON public.patient_symptom_entries
FOR INSERT
WITH CHECK (
  patient_id = public.get_patient_by_auth_id(auth.uid())
  AND tenant_id = (
    SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
  )
);

-- Add trigger for updated_at
CREATE TRIGGER update_patient_symptom_entries_updated_at
  BEFORE UPDATE ON public.patient_symptom_entries
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.patient_symptom_entries IS 'Patient-reported symptom tracking entries';
