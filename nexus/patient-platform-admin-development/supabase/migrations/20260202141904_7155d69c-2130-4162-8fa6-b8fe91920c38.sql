-- Add allowed_states column to tenant_settings
ALTER TABLE public.tenant_settings
ADD COLUMN allowed_states text[] DEFAULT ARRAY[]::text[];

-- Add a comment for documentation
COMMENT ON COLUMN public.tenant_settings.allowed_states IS 'Array of US state codes that the tenant is allowed to sell to';