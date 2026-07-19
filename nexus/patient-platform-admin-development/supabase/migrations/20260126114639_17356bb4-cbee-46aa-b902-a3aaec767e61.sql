-- Function to get all platform superadmins
CREATE OR REPLACE FUNCTION public.get_platform_superadmins()
RETURNS TABLE (
  admin_user_id uuid,
  email text,
  full_name text,
  avatar_url text,
  is_active boolean,
  created_at timestamptz,
  tenant_count bigint
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  -- Only platform superadmins can call this
  IF NOT is_platform_superadmin(auth.uid()) THEN
    RAISE EXCEPTION 'Access denied';
  END IF;

  RETURN QUERY
  SELECT 
    au.id AS admin_user_id,
    au.email,
    au.full_name,
    au.avatar_url,
    au.is_active,
    au.created_at,
    COUNT(DISTINCT tm.tenant_id) AS tenant_count
  FROM admin_users au
  JOIN user_roles ur ON ur.user_id = au.id
  LEFT JOIN tenant_memberships tm ON tm.admin_user_id = au.id
  WHERE ur.role = 'platform_superadmin'
  GROUP BY au.id, au.email, au.full_name, au.avatar_url, au.is_active, au.created_at
  ORDER BY au.email;
END;
$$;

-- Function to get all admin users across all tenants
CREATE OR REPLACE FUNCTION public.get_all_admin_users()
RETURNS TABLE (
  admin_user_id uuid,
  email text,
  full_name text,
  avatar_url text,
  is_active boolean,
  created_at timestamptz,
  roles text[],
  tenants jsonb
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  -- Only platform superadmins can call this
  IF NOT is_platform_superadmin(auth.uid()) THEN
    RAISE EXCEPTION 'Access denied';
  END IF;

  RETURN QUERY
  SELECT 
    au.id AS admin_user_id,
    au.email,
    au.full_name,
    au.avatar_url,
    au.is_active,
    au.created_at,
    ARRAY_AGG(DISTINCT ur.role::text) FILTER (WHERE ur.role IS NOT NULL) AS roles,
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
    ) AS tenants
  FROM admin_users au
  LEFT JOIN user_roles ur ON ur.user_id = au.id
  LEFT JOIN tenant_memberships tm ON tm.admin_user_id = au.id
  LEFT JOIN tenants t ON t.id = tm.tenant_id
  GROUP BY au.id, au.email, au.full_name, au.avatar_url, au.is_active, au.created_at
  ORDER BY au.email;
END;
$$;