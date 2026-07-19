-- Create table for patient medication shot intake records
CREATE TABLE public.medication_shot_intakes (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  medication_id UUID NOT NULL REFERENCES public.medications(id) ON DELETE RESTRICT,
  shot_location TEXT NOT NULL,
  dosage_strength NUMERIC NOT NULL,
  pain_level INTEGER NOT NULL CHECK (pain_level >= 0 AND pain_level <= 5),
  intake_date TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add indexes for performance
CREATE INDEX idx_medication_shot_intakes_tenant_id ON public.medication_shot_intakes(tenant_id);
CREATE INDEX idx_medication_shot_intakes_patient_id ON public.medication_shot_intakes(patient_id);
CREATE INDEX idx_medication_shot_intakes_medication_id ON public.medication_shot_intakes(medication_id);
CREATE INDEX idx_medication_shot_intakes_intake_date ON public.medication_shot_intakes(intake_date);

-- Enable Row Level Security
ALTER TABLE public.medication_shot_intakes ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage intake records for their tenants
CREATE POLICY "Tenant admins can manage medication shot intakes"
ON public.medication_shot_intakes
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view their own intake records
CREATE POLICY "Patients can view their own medication shot intakes"
ON public.medication_shot_intakes
FOR SELECT
USING (patient_id = public.get_patient_by_auth_id(auth.uid()));

-- Patients can create their own intake records
CREATE POLICY "Patients can create their own medication shot intakes"
ON public.medication_shot_intakes
FOR INSERT
WITH CHECK (
  patient_id = public.get_patient_by_auth_id(auth.uid())
  AND tenant_id = (
    SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
  )
  AND medication_id IN (
    SELECT id FROM public.medications WHERE tenant_id = medication_shot_intakes.tenant_id
  )
);

-- Add trigger for updated_at
CREATE TRIGGER update_medication_shot_intakes_updated_at
  BEFORE UPDATE ON public.medication_shot_intakes
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.medication_shot_intakes IS 'Patient-reported medication shot intake records';
