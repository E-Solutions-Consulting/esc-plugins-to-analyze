-- Optimize platform admin "All Users" list:
--   ORDER BY created_at DESC
--   email/full_name ILIKE '%term%'
--   LIMIT/OFFSET pagination

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX IF NOT EXISTS idx_admin_users_created_at_desc
  ON public.admin_users (created_at DESC);

CREATE INDEX IF NOT EXISTS idx_admin_users_email_trgm
  ON public.admin_users USING gin (email gin_trgm_ops)
  WHERE email IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_admin_users_full_name_trgm
  ON public.admin_users USING gin (full_name gin_trgm_ops)
  WHERE full_name IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_user_roles_user_id
  ON public.user_roles (user_id);

CREATE INDEX IF NOT EXISTS idx_tenant_memberships_admin_user_id
  ON public.tenant_memberships (admin_user_id);
