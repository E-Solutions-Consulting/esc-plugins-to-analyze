-- Scope versioned terms and conditions to tenants.

ALTER TABLE public.platform_terms_versions
  ADD COLUMN IF NOT EXISTS tenant_id UUID REFERENCES public.tenants(id) ON DELETE CASCADE;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'platform_terms_versions_tenant_id_required'
      AND conrelid = 'public.platform_terms_versions'::regclass
  ) THEN
    ALTER TABLE public.platform_terms_versions
      ADD CONSTRAINT platform_terms_versions_tenant_id_required
      CHECK (tenant_id IS NOT NULL) NOT VALID;
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_platform_terms_versions_tenant_id
  ON public.platform_terms_versions(tenant_id);

ALTER TABLE public.platform_terms_versions
  DROP CONSTRAINT IF EXISTS platform_terms_versions_version_key;

DROP INDEX IF EXISTS public.uniq_platform_terms_versions_live;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_platform_terms_versions_tenant_version
  ON public.platform_terms_versions(tenant_id, version)
  WHERE tenant_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_platform_terms_versions_tenant_live
  ON public.platform_terms_versions(tenant_id)
  WHERE is_live = true
    AND tenant_id IS NOT NULL;

ALTER TABLE public.platform_terms_versions
  ALTER COLUMN version DROP DEFAULT;

CREATE OR REPLACE FUNCTION public.enforce_single_live_platform_terms_version()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF TG_OP = 'INSERT' AND NEW.version IS NULL THEN
    IF NEW.tenant_id IS NOT NULL THEN
      PERFORM pg_advisory_xact_lock(hashtext(NEW.tenant_id::text));
    END IF;

    SELECT COALESCE(MAX(version), 0) + 1
    INTO NEW.version
    FROM public.platform_terms_versions
    WHERE tenant_id IS NOT DISTINCT FROM NEW.tenant_id;
  END IF;

  IF NEW.is_live THEN
    UPDATE public.platform_terms_versions
    SET is_live = false
    WHERE id <> COALESCE(NEW.id, '00000000-0000-0000-0000-000000000000'::uuid)
      AND tenant_id IS NOT DISTINCT FROM NEW.tenant_id
      AND is_live = true;

    IF NEW.published_at IS NULL THEN
      NEW.published_at = now();
    END IF;
  END IF;

  RETURN NEW;
END;
$$;

DROP POLICY IF EXISTS "Platform superadmins can view platform terms versions"
  ON public.platform_terms_versions;
DROP POLICY IF EXISTS "Platform superadmins can manage platform terms versions"
  ON public.platform_terms_versions;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'platform_terms_versions'
      AND policyname = 'Tenant admins can view tenant terms versions'
  ) THEN
    CREATE POLICY "Tenant admins can view tenant terms versions"
      ON public.platform_terms_versions
      FOR SELECT
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'platform_terms_versions'
      AND policyname = 'Tenant admins can manage tenant terms versions'
  ) THEN
    CREATE POLICY "Tenant admins can manage tenant terms versions"
      ON public.platform_terms_versions
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
      WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

COMMENT ON COLUMN public.platform_terms_versions.tenant_id IS
  'Tenant that owns this terms and conditions version. Null values are legacy platform-wide records.';

COMMENT ON TABLE public.platform_terms_versions IS
  'Immutable versions of tenant-managed terms and conditions.';

COMMENT ON TABLE public.patient_platform_terms_acceptances IS
  'Historical record of which tenant terms version each patient accepted and when.';
