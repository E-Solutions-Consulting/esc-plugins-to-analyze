-- Create a function to get all members of a tenant
-- Uses SECURITY DEFINER to bypass RLS while enforcing manual permission checks
CREATE OR REPLACE FUNCTION public.get_tenant_members(p_tenant_id uuid)
RETURNS TABLE (
  membership_id uuid,
  admin_user_id uuid,
  tenant_id uuid,
  is_primary boolean,
  membership_created_at timestamptz,
  email text,
  full_name text,
  avatar_url text,
  is_active boolean,
  roles text[]
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  -- Verify caller has access to this tenant
  IF NOT (
    is_platform_superadmin(auth.uid()) OR
    is_tenant_admin(auth.uid(), p_tenant_id)
  ) THEN
    RAISE EXCEPTION 'Access denied';
  END IF;

  RETURN QUERY
  SELECT 
    tm.id AS membership_id,
    tm.admin_user_id,
    tm.tenant_id,
    tm.is_primary,
    tm.created_at AS membership_created_at,
    au.email,
    au.full_name,
    au.avatar_url,
    au.is_active,
    ARRAY_AGG(ur.role::text) FILTER (WHERE ur.role IS NOT NULL) AS roles
  FROM tenant_memberships tm
  JOIN admin_users au ON au.id = tm.admin_user_id
  LEFT JOIN user_roles ur ON ur.user_id = tm.admin_user_id
  WHERE tm.tenant_id = p_tenant_id
  GROUP BY tm.id, tm.admin_user_id, tm.tenant_id, tm.is_primary, 
           tm.created_at, au.email, au.full_name, au.avatar_url, au.is_active
  ORDER BY tm.is_primary DESC, au.email;
END;
$$;