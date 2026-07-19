-- Add policy for platform superadmins to view platform-level audit logs (where tenant_id is NULL)
CREATE POLICY "Superadmin can view platform audit logs"
ON public.audit_logs
FOR SELECT
USING (
  public.is_platform_superadmin(auth.uid()) 
  AND tenant_id IS NULL
);

-- Add policy for platform superadmins to insert platform-level audit logs
CREATE POLICY "Superadmin can insert platform audit logs"
ON public.audit_logs
FOR INSERT
WITH CHECK (
  public.is_platform_superadmin(auth.uid())
  AND tenant_id IS NULL
);