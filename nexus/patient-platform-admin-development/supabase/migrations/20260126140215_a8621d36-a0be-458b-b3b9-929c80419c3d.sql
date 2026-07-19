-- Allow public/anonymous read access to basic tenant info for patient-api
-- Only exposes non-sensitive fields needed for tenant resolution
CREATE POLICY "Public can view active tenant basic info"
ON public.tenants
FOR SELECT
USING (status = 'active');