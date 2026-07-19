-- Add platform_superadmin role to João Sobrinho
INSERT INTO public.user_roles (user_id, role)
SELECT '0f465b4a-e94a-402d-8f74-8a29449e12d9', 'platform_superadmin'
WHERE EXISTS (
  SELECT 1
  FROM public.admin_users
  WHERE id = '0f465b4a-e94a-402d-8f74-8a29449e12d9'
)
ON CONFLICT (user_id, role) DO NOTHING;
