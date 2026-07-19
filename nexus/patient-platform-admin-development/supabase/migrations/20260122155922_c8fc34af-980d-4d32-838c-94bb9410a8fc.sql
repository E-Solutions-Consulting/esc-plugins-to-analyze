-- Create storage bucket for medication images
INSERT INTO storage.buckets (id, name, public)
VALUES ('medication-images', 'medication-images', true)
ON CONFLICT (id) DO NOTHING;

-- Allow authenticated users to upload images to their tenant's folder
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Tenant admins can upload medication images'
  ) THEN
    CREATE POLICY "Tenant admins can upload medication images"
    ON storage.objects
    FOR INSERT
    WITH CHECK (
      bucket_id = 'medication-images'
      AND auth.uid() IS NOT NULL
      AND (storage.foldername(name))[1]::uuid IN (
        SELECT get_user_tenant_ids(auth.uid())
      )
    );
  END IF;
END
$$;

-- Allow authenticated users to update their tenant's images
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Tenant admins can update medication images'
  ) THEN
    CREATE POLICY "Tenant admins can update medication images"
    ON storage.objects
    FOR UPDATE
    USING (
      bucket_id = 'medication-images'
      AND auth.uid() IS NOT NULL
      AND (storage.foldername(name))[1]::uuid IN (
        SELECT get_user_tenant_ids(auth.uid())
      )
    );
  END IF;
END
$$;

-- Allow authenticated users to delete their tenant's images
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Tenant admins can delete medication images'
  ) THEN
    CREATE POLICY "Tenant admins can delete medication images"
    ON storage.objects
    FOR DELETE
    USING (
      bucket_id = 'medication-images'
      AND auth.uid() IS NOT NULL
      AND (storage.foldername(name))[1]::uuid IN (
        SELECT get_user_tenant_ids(auth.uid())
      )
    );
  END IF;
END
$$;

-- Allow public read access to medication images (bucket is public)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Anyone can view medication images'
  ) THEN
    CREATE POLICY "Anyone can view medication images"
    ON storage.objects
    FOR SELECT
    USING (bucket_id = 'medication-images');
  END IF;
END
$$;
