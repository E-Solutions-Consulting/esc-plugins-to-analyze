-- Create brand-assets storage bucket for tenant logos, SVGs, favicons, and other brand assets.
-- Files are organized as {tenant_id}/{filename} to enforce tenant isolation.
-- Migration is fully idempotent: safe to run multiple times.

INSERT INTO storage.buckets (id, name, public)
VALUES ('brand-assets', 'brand-assets', true)
ON CONFLICT (id) DO NOTHING;

-- Public read access for all brand assets
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename  = 'objects'
      AND policyname = 'Public can view brand assets'
  ) THEN
    CREATE POLICY "Public can view brand assets"
    ON storage.objects
    FOR SELECT
    USING (bucket_id = 'brand-assets');
  END IF;
END $$;

-- Tenant admins can upload brand assets into their own folder
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename  = 'objects'
      AND policyname = 'Tenant admins can upload brand assets'
  ) THEN
    CREATE POLICY "Tenant admins can upload brand assets"
    ON storage.objects
    FOR INSERT
    TO authenticated
    WITH CHECK (
      bucket_id = 'brand-assets'
      AND (
        public.is_platform_superadmin(auth.uid())
        OR EXISTS (
          SELECT 1
          FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
          WHERE tenant_id.id::text = (storage.foldername(name))[1]
        )
      )
    );
  END IF;
END $$;

-- Tenant admins can update brand assets in their own folder
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename  = 'objects'
      AND policyname = 'Tenant admins can update brand assets'
  ) THEN
    CREATE POLICY "Tenant admins can update brand assets"
    ON storage.objects
    FOR UPDATE
    TO authenticated
    USING (
      bucket_id = 'brand-assets'
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
      bucket_id = 'brand-assets'
      AND (
        public.is_platform_superadmin(auth.uid())
        OR EXISTS (
          SELECT 1
          FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
          WHERE tenant_id.id::text = (storage.foldername(name))[1]
        )
      )
    );
  END IF;
END $$;

-- Tenant admins can delete brand assets in their own folder
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename  = 'objects'
      AND policyname = 'Tenant admins can delete brand assets'
  ) THEN
    CREATE POLICY "Tenant admins can delete brand assets"
    ON storage.objects
    FOR DELETE
    TO authenticated
    USING (
      bucket_id = 'brand-assets'
      AND (
        public.is_platform_superadmin(auth.uid())
        OR EXISTS (
          SELECT 1
          FROM public.get_user_tenant_ids(auth.uid()) AS tenant_id(id)
          WHERE tenant_id.id::text = (storage.foldername(name))[1]
        )
      )
    );
  END IF;
END $$;
