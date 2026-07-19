-- Create table for patient weight tracking entries
CREATE TABLE public.patient_weight_entries (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  weight_value NUMERIC NOT NULL CHECK (weight_value > 0),
  weight_unit TEXT NOT NULL DEFAULT 'lb' CHECK (weight_unit IN ('lb', 'kg')),
  weighed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add indexes for performance
CREATE INDEX idx_patient_weight_entries_tenant_id ON public.patient_weight_entries(tenant_id);
CREATE INDEX idx_patient_weight_entries_patient_id ON public.patient_weight_entries(patient_id);
CREATE INDEX idx_patient_weight_entries_weighed_at ON public.patient_weight_entries(weighed_at);

-- Enable Row Level Security
ALTER TABLE public.patient_weight_entries ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage weight entries for their tenants
CREATE POLICY "Tenant admins can manage patient weight entries"
ON public.patient_weight_entries
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view their own weight entries
CREATE POLICY "Patients can view their own weight entries"
ON public.patient_weight_entries
FOR SELECT
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Patients can create their own weight entries
CREATE POLICY "Patients can create their own weight entries"
ON public.patient_weight_entries
FOR INSERT
WITH CHECK (
  patient_id = public.get_patient_by_auth_id(auth.uid())
  AND tenant_id = (
    SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
  )
);

-- Add trigger for updated_at
CREATE TRIGGER update_patient_weight_entries_updated_at
  BEFORE UPDATE ON public.patient_weight_entries
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.patient_weight_entries IS 'Patient-reported weight tracking entries';
