-- Create a table for medication capabilities (platform-level definitions)
CREATE TABLE public.medication_capabilities (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  name TEXT NOT NULL,
  key TEXT NOT NULL UNIQUE,
  description TEXT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Add comments for documentation
COMMENT ON TABLE public.medication_capabilities IS 'Platform-level definitions of capabilities that can be assigned to medications';
COMMENT ON COLUMN public.medication_capabilities.key IS 'Unique identifier key for the capability (e.g., requires_prior_auth, refrigeration_required)';
COMMENT ON COLUMN public.medication_capabilities.name IS 'Display name for the capability';
COMMENT ON COLUMN public.medication_capabilities.is_active IS 'Whether this capability is available for use';

-- Enable RLS
ALTER TABLE public.medication_capabilities ENABLE ROW LEVEL SECURITY;

-- Authenticated users can view active capabilities
CREATE POLICY "Authenticated users can view capabilities"
ON public.medication_capabilities
FOR SELECT
USING (auth.uid() IS NOT NULL);

-- Only platform superadmin can manage capabilities
CREATE POLICY "Superadmin can manage capabilities"
ON public.medication_capabilities
FOR ALL
USING (public.is_platform_superadmin(auth.uid()));

-- Add trigger for updated_at
CREATE TRIGGER update_medication_capabilities_updated_at
  BEFORE UPDATE ON public.medication_capabilities
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();