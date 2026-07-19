-- Recreate injection-site image storage policies with the same tenant-folder
-- matching pattern used by other buckets and allow platform superadmins.

INSERT INTO storage.buckets (id, name, public)
VALUES ('injection-site-images', 'injection-site-images', true)
ON CONFLICT (id) DO NOTHING;

DROP POLICY IF EXISTS "Public can view injection site images" ON storage.objects;
DROP POLICY IF EXISTS "Tenant admins can upload injection site images" ON storage.objects;
DROP POLICY IF EXISTS "Tenant admins can update injection site images" ON storage.objects;
DROP POLICY IF EXISTS "Tenant admins can delete injection site images" ON storage.objects;

CREATE POLICY "Public can view injection site images"
ON storage.objects
FOR SELECT
USING (bucket_id = 'injection-site-images');

CREATE POLICY "Tenant admins can upload injection site images"
ON storage.objects
FOR INSERT
TO authenticated
WITH CHECK (
  bucket_id = 'injection-site-images'
  AND (
    public.is_platform_superadmin(auth.uid())
    OR EXISTS (
      SELECT 1
      FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
      WHERE tenant_id.id::text = (storage.foldername(name))[1]
    )
  )
);

CREATE POLICY "Tenant admins can update injection site images"
ON storage.objects
FOR UPDATE
TO authenticated
USING (
  bucket_id = 'injection-site-images'
  AND (
    public.is_platform_superadmin(auth.uid())
    OR EXISTS (
      SELECT 1
      FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
      WHERE tenant_id.id::text = (storage.foldername(name))[1]
    )
  )
)
WITH CHECK (
  bucket_id = 'injection-site-images'
  AND (
    public.is_platform_superadmin(auth.uid())
    OR EXISTS (
      SELECT 1
      FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
      WHERE tenant_id.id::text = (storage.foldername(name))[1]
    )
  )
);

CREATE POLICY "Tenant admins can delete injection site images"
ON storage.objects
FOR DELETE
TO authenticated
USING (
  bucket_id = 'injection-site-images'
  AND (
    public.is_platform_superadmin(auth.uid())
    OR EXISTS (
      SELECT 1
      FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
      WHERE tenant_id.id::text = (storage.foldername(name))[1]
    )
  )
);
