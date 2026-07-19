-- Safely remove orphan admin_users rows left behind by failed signup flows.
-- An orphan is an admin_users record whose auth_user_id no longer exists in auth.users.
-- This cleanup is intentionally conservative and skips any row still referenced elsewhere.

DELETE FROM public.admin_users au
WHERE NOT EXISTS (
    SELECT 1
    FROM auth.users u
    WHERE u.id = au.auth_user_id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM public.user_roles ur
    WHERE ur.user_id = au.id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM public.tenant_memberships tm
    WHERE tm.admin_user_id = au.id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM public.order_status_history osh
    WHERE osh.changed_by = au.id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM public.subscription_events se
    WHERE se.changed_by = au.id
  );
