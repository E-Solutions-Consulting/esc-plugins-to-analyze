-- Create storage bucket for tenant logos
INSERT INTO storage.buckets (id, name, public)
VALUES ('tenant-logos', 'tenant-logos', true)
ON CONFLICT (id) DO NOTHING;

-- Allow public read access to tenant logos
CREATE POLICY "Public can view tenant logos"
ON storage.objects FOR SELECT
USING (bucket_id = 'tenant-logos');

-- Platform superadmins can upload/update/delete tenant logos
CREATE POLICY "Superadmins can manage tenant logos"
ON storage.objects FOR ALL
USING (bucket_id = 'tenant-logos' AND is_platform_superadmin(auth.uid()))
WITH CHECK (bucket_id = 'tenant-logos' AND is_platform_superadmin(auth.uid()));