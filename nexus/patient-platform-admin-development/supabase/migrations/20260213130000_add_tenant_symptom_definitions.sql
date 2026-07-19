-- Create table for tenant-managed symptom definitions
CREATE TABLE public.tenant_symptom_definitions (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Ensure unique symptom labels per tenant (case-insensitive)
CREATE UNIQUE INDEX idx_tenant_symptom_definitions_tenant_label_unique
  ON public.tenant_symptom_definitions (tenant_id, lower(label));

-- Add indexes for performance
CREATE INDEX idx_tenant_symptom_definitions_tenant_id
  ON public.tenant_symptom_definitions (tenant_id);

CREATE INDEX idx_tenant_symptom_definitions_active
  ON public.tenant_symptom_definitions (tenant_id, is_active);

-- Enable Row Level Security
ALTER TABLE public.tenant_symptom_definitions ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage symptom definitions for their tenants
CREATE POLICY "Tenant admins can manage symptom definitions"
ON public.tenant_symptom_definitions
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Add trigger for updated_at
CREATE TRIGGER update_tenant_symptom_definitions_updated_at
  BEFORE UPDATE ON public.tenant_symptom_definitions
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.tenant_symptom_definitions IS 'Tenant-managed symptom definitions for symptom tracking';
COMMENT ON COLUMN public.tenant_symptom_definitions.label IS 'Symptom label displayed to patients';
COMMENT ON COLUMN public.tenant_symptom_definitions.is_active IS 'Whether the symptom is active for tracking';
COMMENT ON COLUMN public.tenant_symptom_definitions.display_order IS 'Optional sort order for symptom display';
