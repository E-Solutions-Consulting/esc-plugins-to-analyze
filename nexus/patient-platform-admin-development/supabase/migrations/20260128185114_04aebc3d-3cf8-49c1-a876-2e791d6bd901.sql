-- Add allowed_countries column to tenant_settings
-- Stores an array of ISO 3166-1 alpha-2 country codes (e.g., ['US', 'CA', 'GB'])
ALTER TABLE public.tenant_settings
ADD COLUMN allowed_countries TEXT[] DEFAULT ARRAY['US']::TEXT[];

-- Add comment for documentation
COMMENT ON COLUMN public.tenant_settings.allowed_countries IS 'Array of ISO 3166-1 alpha-2 country codes where the tenant is allowed to sell';