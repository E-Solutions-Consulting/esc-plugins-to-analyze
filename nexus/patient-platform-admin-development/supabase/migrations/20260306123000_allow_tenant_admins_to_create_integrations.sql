DROP POLICY IF EXISTS "Tenant admins can create their own integrations"
  ON public.tenant_integrations;

CREATE POLICY "Tenant admins can create their own integrations"
  ON public.tenant_integrations
  FOR INSERT
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));
