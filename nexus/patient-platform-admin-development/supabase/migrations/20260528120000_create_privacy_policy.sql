-- Create tenant-managed privacy policy versions plus patient acceptance history.

CREATE TABLE IF NOT EXISTS public.privacy_policy_versions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  version INTEGER NOT NULL,
  content TEXT NOT NULL,
  is_live BOOLEAN NOT NULL DEFAULT false,
  created_by_admin_user_id UUID REFERENCES public.admin_users(id) ON DELETE SET NULL,
  published_by_admin_user_id UUID REFERENCES public.admin_users(id) ON DELETE SET NULL,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT privacy_policy_versions_content_not_blank CHECK (btrim(content) <> '')
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_privacy_policy_versions_tenant_version
  ON public.privacy_policy_versions(tenant_id, version);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_privacy_policy_versions_tenant_live
  ON public.privacy_policy_versions(tenant_id)
  WHERE is_live = true;

CREATE INDEX IF NOT EXISTS idx_privacy_policy_versions_tenant_id
  ON public.privacy_policy_versions(tenant_id);

CREATE INDEX IF NOT EXISTS idx_privacy_policy_versions_created_at
  ON public.privacy_policy_versions(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_privacy_policy_versions_published_at
  ON public.privacy_policy_versions(published_at DESC NULLS LAST);

ALTER TABLE public.privacy_policy_versions ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'privacy_policy_versions'
      AND policyname = 'Tenant admins can view privacy policy versions'
  ) THEN
    CREATE POLICY "Tenant admins can view privacy policy versions"
      ON public.privacy_policy_versions
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
      AND tablename = 'privacy_policy_versions'
      AND policyname = 'Tenant admins can manage privacy policy versions'
  ) THEN
    CREATE POLICY "Tenant admins can manage privacy policy versions"
      ON public.privacy_policy_versions
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
      WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

CREATE OR REPLACE FUNCTION public.enforce_single_live_privacy_policy_version()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF TG_OP = 'INSERT' AND NEW.version IS NULL THEN
    PERFORM pg_advisory_xact_lock(hashtext(NEW.tenant_id::text));

    SELECT COALESCE(MAX(version), 0) + 1
    INTO NEW.version
    FROM public.privacy_policy_versions
    WHERE tenant_id = NEW.tenant_id;
  END IF;

  IF NEW.is_live THEN
    UPDATE public.privacy_policy_versions
    SET is_live = false
    WHERE id <> COALESCE(NEW.id, '00000000-0000-0000-0000-000000000000'::uuid)
      AND tenant_id = NEW.tenant_id
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
    WHERE tgname = 'enforce_single_live_privacy_policy_version'
      AND tgrelid = 'public.privacy_policy_versions'::regclass
  ) THEN
    CREATE TRIGGER enforce_single_live_privacy_policy_version
      BEFORE INSERT OR UPDATE ON public.privacy_policy_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.enforce_single_live_privacy_policy_version();
  END IF;
END $$;

CREATE OR REPLACE FUNCTION public.prevent_published_privacy_policy_version_edits()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF OLD.published_at IS NULL AND OLD.is_live IS NOT TRUE THEN
    RETURN NEW;
  END IF;

  IF NEW.content IS DISTINCT FROM OLD.content THEN
    RAISE EXCEPTION 'Published privacy policy versions cannot be edited';
  END IF;

  IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
    OR NEW.version IS DISTINCT FROM OLD.version THEN
    RAISE EXCEPTION 'Published privacy policy versions cannot be reassigned or renumbered';
  END IF;

  NEW.published_at := OLD.published_at;
  NEW.published_by_admin_user_id := OLD.published_by_admin_user_id;
  NEW.created_at := OLD.created_at;
  NEW.created_by_admin_user_id := OLD.created_by_admin_user_id;

  RETURN NEW;
END;
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'prevent_published_privacy_policy_version_edits'
      AND tgrelid = 'public.privacy_policy_versions'::regclass
  ) THEN
    CREATE TRIGGER prevent_published_privacy_policy_version_edits
      BEFORE UPDATE ON public.privacy_policy_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.prevent_published_privacy_policy_version_edits();
  END IF;
END $$;

CREATE OR REPLACE FUNCTION public.prevent_published_privacy_policy_version_deletes()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF OLD.published_at IS NOT NULL OR OLD.is_live IS TRUE THEN
    RAISE EXCEPTION 'Published privacy policy versions cannot be deleted';
  END IF;

  RETURN OLD;
END;
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'prevent_published_privacy_policy_version_deletes'
      AND tgrelid = 'public.privacy_policy_versions'::regclass
  ) THEN
    CREATE TRIGGER prevent_published_privacy_policy_version_deletes
      BEFORE DELETE ON public.privacy_policy_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.prevent_published_privacy_policy_version_deletes();
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
    WHERE tgname = 'update_privacy_policy_versions_updated_at'
      AND tgrelid = 'public.privacy_policy_versions'::regclass
  ) THEN
    CREATE TRIGGER update_privacy_policy_versions_updated_at
      BEFORE UPDATE ON public.privacy_policy_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.patient_privacy_policy_acceptances (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  privacy_policy_version_id UUID NOT NULL REFERENCES public.privacy_policy_versions(id) ON DELETE RESTRICT,
  privacy_policy_version INTEGER NOT NULL,
  accepted_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uniq_patient_privacy_policy_acceptance UNIQUE (patient_id, privacy_policy_version_id)
);

CREATE INDEX IF NOT EXISTS idx_patient_privacy_policy_acceptances_tenant_id
  ON public.patient_privacy_policy_acceptances(tenant_id);

CREATE INDEX IF NOT EXISTS idx_patient_privacy_policy_acceptances_patient_id
  ON public.patient_privacy_policy_acceptances(patient_id);

CREATE INDEX IF NOT EXISTS idx_patient_privacy_policy_acceptances_accepted_at
  ON public.patient_privacy_policy_acceptances(accepted_at DESC);

ALTER TABLE public.patient_privacy_policy_acceptances ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_privacy_policy_acceptances'
      AND policyname = 'Tenant admins can manage patient privacy policy acceptances'
  ) THEN
    CREATE POLICY "Tenant admins can manage patient privacy policy acceptances"
      ON public.patient_privacy_policy_acceptances
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
      AND tablename = 'patient_privacy_policy_acceptances'
      AND policyname = 'Patients can view their own privacy policy acceptances'
  ) THEN
    CREATE POLICY "Patients can view their own privacy policy acceptances"
      ON public.patient_privacy_policy_acceptances
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
      AND tablename = 'patient_privacy_policy_acceptances'
      AND policyname = 'Patients can insert their own privacy policy acceptances'
  ) THEN
    CREATE POLICY "Patients can insert their own privacy policy acceptances"
      ON public.patient_privacy_policy_acceptances
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
    WHERE tgname = 'update_patient_privacy_policy_acceptances_updated_at'
      AND tgrelid = 'public.patient_privacy_policy_acceptances'::regclass
  ) THEN
    CREATE TRIGGER update_patient_privacy_policy_acceptances_updated_at
      BEFORE UPDATE ON public.patient_privacy_policy_acceptances
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

COMMENT ON TABLE public.privacy_policy_versions IS
  'Versions of tenant-managed privacy policies. Drafts can be edited or deleted until first publish; published versions are immutable.';

COMMENT ON TABLE public.patient_privacy_policy_acceptances IS
  'Historical record of which tenant privacy policy version each patient accepted and when.';
