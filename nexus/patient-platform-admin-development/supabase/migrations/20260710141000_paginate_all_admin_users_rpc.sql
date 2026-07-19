DROP FUNCTION IF EXISTS public.get_all_admin_users();

CREATE OR REPLACE FUNCTION public.get_all_admin_users(
  _search text DEFAULT NULL,
  _offset integer DEFAULT 0,
  _limit integer DEFAULT 25
)
RETURNS TABLE (
  admin_user_id uuid,
  email text,
  full_name text,
  avatar_url text,
  is_active boolean,
  created_at timestamptz,
  roles text[],
  tenants jsonb,
  total_count bigint
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  search_value text := NULLIF(BTRIM(_search), '');
BEGIN
  IF NOT is_platform_superadmin(auth.uid()) THEN
    RAISE EXCEPTION 'Access denied';
  END IF;

  RETURN QUERY
  WITH filtered_count AS (
    SELECT COUNT(*) AS total_count
    FROM public.admin_users au
    WHERE search_value IS NULL
      OR au.email ILIKE '%' || search_value || '%'
      OR au.full_name ILIKE '%' || search_value || '%'
  ),
  page_admin_users AS (
    SELECT au.id, au.email, au.full_name, au.avatar_url, au.is_active, au.created_at
    FROM public.admin_users au
    WHERE search_value IS NULL
      OR au.email ILIKE '%' || search_value || '%'
      OR au.full_name ILIKE '%' || search_value || '%'
    ORDER BY created_at DESC
    OFFSET GREATEST(_offset, 0)
    LIMIT LEAST(GREATEST(_limit, 1), 100)
  )
  SELECT
    au.id AS admin_user_id,
    au.email,
    au.full_name,
    au.avatar_url,
    au.is_active,
    au.created_at,
    COALESCE(
      ARRAY_AGG(DISTINCT ur.role::text) FILTER (WHERE ur.role IS NOT NULL),
      ARRAY[]::text[]
    ) AS roles,
    COALESCE(
      jsonb_agg(
        DISTINCT jsonb_build_object(
          'id', t.id,
          'name', t.name,
          'slug', t.slug,
          'is_primary', tm.is_primary
        )
      ) FILTER (WHERE t.id IS NOT NULL),
      '[]'::jsonb
    ) AS tenants,
    fc.total_count
  FROM page_admin_users au
  CROSS JOIN filtered_count fc
  LEFT JOIN public.user_roles ur ON ur.user_id = au.id
  LEFT JOIN public.tenant_memberships tm ON tm.admin_user_id = au.id
  LEFT JOIN public.tenants t ON t.id = tm.tenant_id
  GROUP BY au.id, au.email, au.full_name, au.avatar_url, au.is_active, au.created_at, fc.total_count
  ORDER BY au.created_at DESC;
END;
$$;
