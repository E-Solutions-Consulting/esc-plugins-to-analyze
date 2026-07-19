-- Allow tenant terms drafts to be edited or deleted until their first publish.
-- Once a version has been made live, its terms content stays immutable and
-- the version cannot be deleted even after another version replaces it as live.
--
-- This migration intentionally does not backfill or mutate existing rows.
-- It is safe to run repeatedly: functions are replaced in place and triggers
-- are created only when they do not already exist.

CREATE OR REPLACE FUNCTION public.prevent_published_tenant_terms_version_edits()
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
    RAISE EXCEPTION 'Published tenant terms versions cannot be edited';
  END IF;

  IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
    OR NEW.version IS DISTINCT FROM OLD.version THEN
    RAISE EXCEPTION 'Published tenant terms versions cannot be reassigned or renumbered';
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
    WHERE tgname = 'prevent_published_tenant_terms_version_edits'
      AND tgrelid = 'public.platform_terms_versions'::regclass
  ) THEN
    CREATE TRIGGER prevent_published_tenant_terms_version_edits
      BEFORE UPDATE ON public.platform_terms_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.prevent_published_tenant_terms_version_edits();
  END IF;
END $$;

COMMENT ON FUNCTION public.prevent_published_tenant_terms_version_edits() IS
  'Allows draft tenant terms versions to be edited until first publish, then locks published terms content and first-publish metadata.';

CREATE OR REPLACE FUNCTION public.prevent_published_tenant_terms_version_deletes()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF OLD.published_at IS NOT NULL OR OLD.is_live IS TRUE THEN
    RAISE EXCEPTION 'Published tenant terms versions cannot be deleted';
  END IF;

  RETURN OLD;
END;
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'prevent_published_tenant_terms_version_deletes'
      AND tgrelid = 'public.platform_terms_versions'::regclass
  ) THEN
    CREATE TRIGGER prevent_published_tenant_terms_version_deletes
      BEFORE DELETE ON public.platform_terms_versions
      FOR EACH ROW
      EXECUTE FUNCTION public.prevent_published_tenant_terms_version_deletes();
  END IF;
END $$;

COMMENT ON FUNCTION public.prevent_published_tenant_terms_version_deletes() IS
  'Allows draft tenant terms versions to be deleted, then prevents deletion after first publish.';

COMMENT ON TABLE public.platform_terms_versions IS
  'Versions of tenant-managed terms and conditions. Drafts can be edited or deleted until first publish; published versions are immutable.';
