-- Create table for patient energy tracking entries
CREATE TABLE public.patient_energy_entries (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  energy_value INTEGER NOT NULL CHECK (energy_value >= 1 AND energy_value <= 10),
  energy_label TEXT,
  energy_note TEXT,
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add indexes for performance
CREATE INDEX idx_patient_energy_entries_tenant_id ON public.patient_energy_entries(tenant_id);
CREATE INDEX idx_patient_energy_entries_patient_id ON public.patient_energy_entries(patient_id);
CREATE INDEX idx_patient_energy_entries_recorded_at ON public.patient_energy_entries(recorded_at);

-- Enable Row Level Security
ALTER TABLE public.patient_energy_entries ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage energy entries for their tenants
CREATE POLICY "Tenant admins can manage patient energy entries"
ON public.patient_energy_entries
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view their own energy entries
CREATE POLICY "Patients can view their own energy entries"
ON public.patient_energy_entries
FOR SELECT
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Patients can create their own energy entries
CREATE POLICY "Patients can create their own energy entries"
ON public.patient_energy_entries
FOR INSERT
WITH CHECK (
  patient_id = public.get_patient_by_auth_id(auth.uid())
  AND tenant_id = (
    SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
  )
);

-- Add trigger for updated_at
CREATE TRIGGER update_patient_energy_entries_updated_at
  BEFORE UPDATE ON public.patient_energy_entries
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.patient_energy_entries IS 'Patient-reported energy tracking entries';
