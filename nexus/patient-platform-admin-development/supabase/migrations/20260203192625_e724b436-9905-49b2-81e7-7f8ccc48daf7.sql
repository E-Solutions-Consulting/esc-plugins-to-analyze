INSERT INTO public.user_roles (user_id, role)
SELECT 'dc9678c7-e87d-4d74-95c3-9c2553315566', 'platform_superadmin'
WHERE EXISTS (
  SELECT 1
  FROM public.admin_users
  WHERE id = 'dc9678c7-e87d-4d74-95c3-9c2553315566'
)
ON CONFLICT (user_id, role) DO NOTHING;
