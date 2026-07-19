-- Create table for tenant-managed mood change definitions
CREATE TABLE public.tenant_mood_change_definitions (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Ensure unique mood change labels per tenant (case-insensitive)
CREATE UNIQUE INDEX idx_tenant_mood_change_definitions_tenant_label_unique
  ON public.tenant_mood_change_definitions (tenant_id, lower(label));

-- Add indexes for performance
CREATE INDEX idx_tenant_mood_change_definitions_tenant_id
  ON public.tenant_mood_change_definitions (tenant_id);

CREATE INDEX idx_tenant_mood_change_definitions_active
  ON public.tenant_mood_change_definitions (tenant_id, is_active);

-- Enable Row Level Security
ALTER TABLE public.tenant_mood_change_definitions ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage mood change definitions for their tenants
CREATE POLICY "Tenant admins can manage mood change definitions"
ON public.tenant_mood_change_definitions
FOR ALL
USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- Patients can view mood change definitions for their tenant
CREATE POLICY "Patients can view mood change definitions"
ON public.tenant_mood_change_definitions
FOR SELECT
USING (
  tenant_id = (
    SELECT tenant_id
    FROM public.patients
    WHERE auth_user_id = auth.uid()
    LIMIT 1
  )
);

-- Add trigger for updated_at
CREATE TRIGGER update_tenant_mood_change_definitions_updated_at
  BEFORE UPDATE ON public.tenant_mood_change_definitions
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Documentation
COMMENT ON TABLE public.tenant_mood_change_definitions IS 'Tenant-managed mood change definitions for patient tracking';
COMMENT ON COLUMN public.tenant_mood_change_definitions.label IS 'Mood change label displayed to patients';
COMMENT ON COLUMN public.tenant_mood_change_definitions.is_active IS 'Whether the mood change is active for tracking';
COMMENT ON COLUMN public.tenant_mood_change_definitions.display_order IS 'Optional sort order for mood change display';
