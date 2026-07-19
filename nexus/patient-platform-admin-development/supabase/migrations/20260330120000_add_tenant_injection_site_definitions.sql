-- Create table for tenant-managed injection site definitions
CREATE TABLE IF NOT EXISTS public.tenant_injection_site_definitions (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  image_url TEXT NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Ensure unique injection site labels per tenant (case-insensitive)
CREATE UNIQUE INDEX IF NOT EXISTS idx_tenant_injection_site_definitions_tenant_label_unique
  ON public.tenant_injection_site_definitions (tenant_id, lower(label));

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_tenant_injection_site_definitions_tenant_id
  ON public.tenant_injection_site_definitions (tenant_id);

CREATE INDEX IF NOT EXISTS idx_tenant_injection_site_definitions_active
  ON public.tenant_injection_site_definitions (tenant_id, is_active);

-- Enable Row Level Security
ALTER TABLE public.tenant_injection_site_definitions ENABLE ROW LEVEL SECURITY;

-- Tenant admins can manage injection site definitions for their tenants
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'tenant_injection_site_definitions'
      AND policyname = 'Tenant admins can manage injection site definitions'
  ) THEN
    CREATE POLICY "Tenant admins can manage injection site definitions"
    ON public.tenant_injection_site_definitions
    FOR ALL
    USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
    WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END
$$;

-- Patients can view injection site definitions for their tenant
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'tenant_injection_site_definitions'
      AND policyname = 'Patients can view injection site definitions'
  ) THEN
    CREATE POLICY "Patients can view injection site definitions"
    ON public.tenant_injection_site_definitions
    FOR SELECT
    USING (
      tenant_id = (
        SELECT tenant_id
        FROM public.patients
        WHERE auth_user_id = auth.uid()
        LIMIT 1
      )
    );
  END IF;
END
$$;

-- Add trigger for updated_at
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_tenant_injection_site_definitions_updated_at'
      AND tgrelid = 'public.tenant_injection_site_definitions'::regclass
  ) THEN
    CREATE TRIGGER update_tenant_injection_site_definitions_updated_at
      BEFORE UPDATE ON public.tenant_injection_site_definitions
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END
$$;

-- Documentation
COMMENT ON TABLE public.tenant_injection_site_definitions IS 'Tenant-managed injection site definitions for shot tracking';
COMMENT ON COLUMN public.tenant_injection_site_definitions.label IS 'Injection site label displayed to patients';
COMMENT ON COLUMN public.tenant_injection_site_definitions.image_url IS 'Public image URL used to illustrate the injection site';
COMMENT ON COLUMN public.tenant_injection_site_definitions.is_active IS 'Whether the injection site is active for tracking';
COMMENT ON COLUMN public.tenant_injection_site_definitions.display_order IS 'Optional sort order for injection site display';

-- Create storage bucket for injection site images
INSERT INTO storage.buckets (id, name, public)
VALUES ('injection-site-images', 'injection-site-images', true)
ON CONFLICT (id) DO NOTHING;

-- Allow public read access to injection site images
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Public can view injection site images'
  ) THEN
    CREATE POLICY "Public can view injection site images"
    ON storage.objects
    FOR SELECT
    USING (bucket_id = 'injection-site-images');
  END IF;
END
$$;

-- Tenant admins can upload injection site images
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Tenant admins can upload injection site images'
  ) THEN
    CREATE POLICY "Tenant admins can upload injection site images"
    ON storage.objects
    FOR INSERT
    WITH CHECK (
      bucket_id = 'injection-site-images'
      AND auth.uid() IS NOT NULL
      AND (storage.foldername(name))[1]::uuid IN (
        SELECT get_user_tenant_ids(auth.uid())
      )
    );
  END IF;
END
$$;

-- Tenant admins can update injection site images
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Tenant admins can update injection site images'
  ) THEN
    CREATE POLICY "Tenant admins can update injection site images"
    ON storage.objects
    FOR UPDATE
    USING (
      bucket_id = 'injection-site-images'
      AND auth.uid() IS NOT NULL
      AND (storage.foldername(name))[1]::uuid IN (
        SELECT get_user_tenant_ids(auth.uid())
      )
    );
  END IF;
END
$$;

-- Tenant admins can delete injection site images
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'storage'
      AND tablename = 'objects'
      AND policyname = 'Tenant admins can delete injection site images'
  ) THEN
    CREATE POLICY "Tenant admins can delete injection site images"
    ON storage.objects
    FOR DELETE
    USING (
      bucket_id = 'injection-site-images'
      AND auth.uid() IS NOT NULL
      AND (storage.foldername(name))[1]::uuid IN (
        SELECT get_user_tenant_ids(auth.uid())
      )
    );
  END IF;
END
$$;
