-- Add platform_superadmin role to joao.sobrinho+admin@alliahealth.co
INSERT INTO public.user_roles (user_id, role)
SELECT '0f465b4a-e94a-402d-8f74-8a29449e12d9', 'platform_superadmin'
WHERE EXISTS (
  SELECT 1
  FROM public.admin_users
  WHERE id = '0f465b4a-e94a-402d-8f74-8a29449e12d9'
)
ON CONFLICT (user_id, role) DO NOTHING;

-- Add tenant membership to Allia Demo Tenant
INSERT INTO public.tenant_memberships (admin_user_id, tenant_id, is_primary)
SELECT '0f465b4a-e94a-402d-8f74-8a29449e12d9', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', true
WHERE EXISTS (
  SELECT 1
  FROM public.admin_users
  WHERE id = '0f465b4a-e94a-402d-8f74-8a29449e12d9'
)
AND EXISTS (
  SELECT 1
  FROM public.tenants
  WHERE id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'
)
ON CONFLICT DO NOTHING;
