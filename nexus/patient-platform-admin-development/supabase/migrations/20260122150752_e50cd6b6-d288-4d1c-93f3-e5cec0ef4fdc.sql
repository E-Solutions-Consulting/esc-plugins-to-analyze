-- =======================================================
-- Fix audit_logs RLS policy to enforce tenant ownership
-- =======================================================

-- Drop the existing permissive INSERT policy
DROP POLICY IF EXISTS "Authenticated users can insert audit logs" ON public.audit_logs;

-- Create a more restrictive INSERT policy that validates:
-- 1. User is authenticated
-- 2. actor_id matches the current user's admin_user_id
-- 3. tenant_id is in the user's tenant memberships (or NULL for platform-level actions)
CREATE POLICY "Users can insert audit logs for their tenants"
  ON public.audit_logs FOR INSERT
  WITH CHECK (
    auth.uid() IS NOT NULL
    AND (
      actor_id IS NULL 
      OR actor_id = public.get_admin_user_id(auth.uid())
    )
    AND (
      tenant_id IS NULL 
      OR tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
    )
  );

-- Create a trigger function to auto-set actor_id if not provided
CREATE OR REPLACE FUNCTION public.set_audit_log_actor()
RETURNS TRIGGER AS $$
BEGIN
  -- Auto-set actor_id to current user's admin_user_id if not provided
  IF NEW.actor_id IS NULL THEN
    NEW.actor_id := public.get_admin_user_id(auth.uid());
  END IF;
  
  -- Auto-set actor_email from admin_users if not provided
  IF NEW.actor_email IS NULL AND NEW.actor_id IS NOT NULL THEN
    SELECT email INTO NEW.actor_email
    FROM public.admin_users
    WHERE id = NEW.actor_id;
  END IF;
  
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

-- Create trigger to enforce actor_id auto-population
DROP TRIGGER IF EXISTS enforce_audit_actor ON public.audit_logs;
CREATE TRIGGER enforce_audit_actor
  BEFORE INSERT ON public.audit_logs
  FOR EACH ROW
  EXECUTE FUNCTION public.set_audit_log_actor();