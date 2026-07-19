-- Create table to store tenant integration configurations (like API keys)
CREATE TABLE public.tenant_integrations (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  integration_key VARCHAR(100) NOT NULL,
  is_enabled BOOLEAN NOT NULL DEFAULT false,
  settings JSONB NOT NULL DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  CONSTRAINT unique_tenant_integration UNIQUE (tenant_id, integration_key)
);

-- Enable RLS
ALTER TABLE public.tenant_integrations ENABLE ROW LEVEL SECURITY;

-- Platform superadmins can view all tenant integrations
CREATE POLICY "Platform superadmins can view all tenant integrations"
  ON public.tenant_integrations
  FOR SELECT
  USING (public.is_platform_superadmin(auth.uid()));

-- Platform superadmins can manage all tenant integrations
CREATE POLICY "Platform superadmins can manage all tenant integrations"
  ON public.tenant_integrations
  FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

-- Tenant admins can view their own integrations
CREATE POLICY "Tenant admins can view their own integrations"
  ON public.tenant_integrations
  FOR SELECT
  USING (public.is_tenant_admin(auth.uid(), tenant_id));

-- Tenant admins can update their own integrations (but not insert/delete - that's admin only)
CREATE POLICY "Tenant admins can update their own integrations"
  ON public.tenant_integrations
  FOR UPDATE
  USING (public.is_tenant_admin(auth.uid(), tenant_id));

-- Create trigger for updated_at
CREATE TRIGGER update_tenant_integrations_updated_at
  BEFORE UPDATE ON public.tenant_integrations
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Create the platform-level integrations table to define available integrations
CREATE TABLE public.platform_integrations (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  key VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  logo_url TEXT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  required_settings JSONB NOT NULL DEFAULT '[]',
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Enable RLS for platform_integrations
ALTER TABLE public.platform_integrations ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can read platform integrations
CREATE POLICY "Authenticated users can view platform integrations"
  ON public.platform_integrations
  FOR SELECT
  TO authenticated
  USING (true);

-- Only platform superadmins can manage platform integrations
CREATE POLICY "Platform superadmins can manage platform integrations"
  ON public.platform_integrations
  FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

-- Create trigger for updated_at
CREATE TRIGGER update_platform_integrations_updated_at
  BEFORE UPDATE ON public.platform_integrations
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Insert the Resend integration
INSERT INTO public.platform_integrations (key, name, description, required_settings)
VALUES (
  'resend',
  'Resend Email',
  'Send transactional emails using Resend API',
  '["api_key"]'::jsonb
);