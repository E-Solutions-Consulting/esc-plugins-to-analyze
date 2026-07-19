-- PP-847: allow one auth user across multiple tenants
--
-- Previously UNIQUE(auth_user_id) meant one Supabase auth user could only exist
-- in one tenant. Cross-tenant migration (PP-854) requires the same auth_user_id
-- to have a patient row in both source and destination tenants.
--
-- Audit (PP-853) confirmed no unsafe callsites in patient-platform-admin or
-- rt-data-hub-functions — all patients queries already scope by tenant_id.

ALTER TABLE public.patients
  DROP CONSTRAINT IF EXISTS patients_auth_user_id_key;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_catalog.pg_constraint
    WHERE conrelid = 'public.patients'::regclass
      AND conname = 'patients_auth_user_id_tenant_id_key'
  ) THEN
    ALTER TABLE public.patients
      ADD CONSTRAINT patients_auth_user_id_tenant_id_key
      UNIQUE (auth_user_id, tenant_id);
  END IF;
END;
$$;
