-- Create junction table for medication capability assignments
CREATE TABLE public.medication_capability_assignments (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  medication_id UUID NOT NULL REFERENCES public.medications(id) ON DELETE CASCADE,
  capability_id UUID NOT NULL REFERENCES public.medication_capabilities(id) ON DELETE CASCADE,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  UNIQUE(medication_id, capability_id)
);

-- Enable RLS
ALTER TABLE public.medication_capability_assignments ENABLE ROW LEVEL SECURITY;

-- RLS: Access via medication ownership (tenant admins can manage their medication's capabilities)
CREATE POLICY "Access via medication ownership"
ON public.medication_capability_assignments
FOR ALL
USING (
  medication_id IN (
    SELECT id FROM public.medications
    WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
  )
);

-- Index for faster lookups
CREATE INDEX idx_medication_capability_assignments_medication_id 
ON public.medication_capability_assignments(medication_id);

CREATE INDEX idx_medication_capability_assignments_capability_id 
ON public.medication_capability_assignments(capability_id);