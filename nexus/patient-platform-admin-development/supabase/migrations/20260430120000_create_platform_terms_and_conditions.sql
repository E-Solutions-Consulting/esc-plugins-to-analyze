-- Create versioned platform-wide terms and conditions plus patient acceptance history.

CREATE SEQUENCE IF NOT EXISTS public.platform_terms_version_number_seq
  AS INTEGER
  START WITH 1
  INCREMENT BY 1
  MINVALUE 1;

CREATE TABLE IF NOT EXISTS public.platform_terms_versions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  version INTEGER NOT NULL DEFAULT nextval('public.platform_terms_version_number_seq'),
  content TEXT NOT NULL,
  is_live BOOLEAN NOT NULL DEFAULT false,
  created_by_admin_user_id UUID REFERENCES public.admin_users(id) ON DELETE SET NULL,
  published_by_admin_user_id UUID REFERENCES public.admin_users(id) ON DELETE SET NULL,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT platform_terms_versions_version_key UNIQUE (version),
  CONSTRAINT platform_terms_versions_content_not_blank CHECK (btrim(content) <> '')
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_platform_terms_versions_live
  ON public.platform_terms_versions(is_live)
  WHERE is_live = true;

CREATE INDEX IF NOT EXISTS idx_platform_terms_versions_created_at
  ON public.platform_terms_versions(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_platform_terms_versions_published_at
  ON public.platform_terms_versions(published_at DESC NULLS LAST);

ALTER TABLE public.platform_terms_versions ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'platform_terms_versions'
      AND policyname = 'Platform superadmins can view platform terms versions'
  ) THEN
    CREATE POLICY "Platform superadmins can view platform terms versions"
      ON public.platform_terms_versions
      FOR SELECT
      USING (public.is_platform_superadmin(auth.uid()));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'platform_terms_versions'
      AND policyname = 'Platform superadmins can manage platform terms versions'
  ) THEN
    CREATE POLICY "Platform superadmins can manage platform terms versions"
      ON public.platform_terms_versions
      FOR ALL
      USING (public.is_platform_superadmin(auth.uid()))
      WITH CHECK (public.is_platform_superadmin(auth.uid()));
  END IF;
END $$;

CREATE OR REPLACE FUNCTION public.enforce_single_live_platform_terms_version()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF NEW.is_live THEN
    UPDATE public.platform_terms_versions
    SET is_live = false
    WHERE id <> COALESCE(NEW.id, '00000000-0000-0000-0000-000000000000'::uuid)
      AND is_live = true;

    IF NEW.published_at IS NULL THEN
      NEW.published_at = now();
    END IF;
  END IF;

  RETURN NEW;
END;
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'enforce_single_live_platform_terms_version'
      AND tgrelid = 'public.platform_terms_versions'::regclass
  ) THEN
    CREATE TRIGGER enforce_single_live_platform_terms_version
      BEFORE INSERT OR UPDATE ON public.platform_terms_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.enforce_single_live_platform_terms_version();
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_proc
    WHERE proname = 'update_updated_at_column'
      AND pg_function_is_visible(oid)
  ) AND NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_platform_terms_versions_updated_at'
      AND tgrelid = 'public.platform_terms_versions'::regclass
  ) THEN
    CREATE TRIGGER update_platform_terms_versions_updated_at
      BEFORE UPDATE ON public.platform_terms_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.patient_platform_terms_acceptances (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  platform_terms_version_id UUID NOT NULL REFERENCES public.platform_terms_versions(id) ON DELETE RESTRICT,
  platform_terms_version INTEGER NOT NULL,
  accepted_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uniq_patient_platform_terms_acceptance UNIQUE (patient_id, platform_terms_version_id)
);

CREATE INDEX IF NOT EXISTS idx_patient_platform_terms_acceptances_tenant_id
  ON public.patient_platform_terms_acceptances(tenant_id);

CREATE INDEX IF NOT EXISTS idx_patient_platform_terms_acceptances_patient_id
  ON public.patient_platform_terms_acceptances(patient_id);

CREATE INDEX IF NOT EXISTS idx_patient_platform_terms_acceptances_accepted_at
  ON public.patient_platform_terms_acceptances(accepted_at DESC);

ALTER TABLE public.patient_platform_terms_acceptances ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_platform_terms_acceptances'
      AND policyname = 'Tenant admins can manage patient platform terms acceptances'
  ) THEN
    CREATE POLICY "Tenant admins can manage patient platform terms acceptances"
      ON public.patient_platform_terms_acceptances
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
      WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_platform_terms_acceptances'
      AND policyname = 'Patients can view their own platform terms acceptances'
  ) THEN
    CREATE POLICY "Patients can view their own platform terms acceptances"
      ON public.patient_platform_terms_acceptances
      FOR SELECT
      USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_platform_terms_acceptances'
      AND policyname = 'Patients can insert their own platform terms acceptances'
  ) THEN
    CREATE POLICY "Patients can insert their own platform terms acceptances"
      ON public.patient_platform_terms_acceptances
      FOR INSERT
      WITH CHECK (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_proc
    WHERE proname = 'update_updated_at_column'
      AND pg_function_is_visible(oid)
  ) AND NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_patient_platform_terms_acceptances_updated_at'
      AND tgrelid = 'public.patient_platform_terms_acceptances'::regclass
  ) THEN
    CREATE TRIGGER update_patient_platform_terms_acceptances_updated_at
      BEFORE UPDATE ON public.patient_platform_terms_acceptances
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

COMMENT ON TABLE public.platform_terms_versions IS
  'Immutable versions of platform-wide terms and conditions managed by platform admins.';

COMMENT ON TABLE public.patient_platform_terms_acceptances IS
  'Historical record of which platform terms version each patient accepted and when.';
