-- Payment providers managed at platform level
CREATE TABLE public.payment_providers (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  key TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT,
  logo_url TEXT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  required_settings JSONB NOT NULL DEFAULT '[]',
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Tenant-specific payment provider configurations
CREATE TABLE public.tenant_payment_providers (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  payment_provider_id UUID NOT NULL REFERENCES public.payment_providers(id) ON DELETE CASCADE,
  is_enabled BOOLEAN NOT NULL DEFAULT false,
  settings JSONB NOT NULL DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, payment_provider_id)
);

-- Enable RLS
ALTER TABLE public.payment_providers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_payment_providers ENABLE ROW LEVEL SECURITY;

-- Payment providers: Platform superadmins can manage, tenant admins can read active ones
CREATE POLICY "Platform superadmins can manage payment providers"
  ON public.payment_providers FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Tenant admins can read active payment providers"
  ON public.payment_providers FOR SELECT
  USING (is_active = true);

-- Tenant payment providers: Tenant admins can manage their own
CREATE POLICY "Platform superadmins can read all tenant payment providers"
  ON public.tenant_payment_providers FOR SELECT
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Tenant admins can manage their payment providers"
  ON public.tenant_payment_providers FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id));

-- Updated_at triggers
CREATE TRIGGER update_payment_providers_updated_at
  BEFORE UPDATE ON public.payment_providers
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_tenant_payment_providers_updated_at
  BEFORE UPDATE ON public.tenant_payment_providers
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- Seed Stripe as initial payment provider
INSERT INTO public.payment_providers (key, name, description, required_settings) VALUES
  ('stripe', 'Stripe', 'Accept payments with Stripe - credit cards, Apple Pay, Google Pay, and more.', 
   '[{"key": "secret_key", "label": "Secret Key", "type": "secret", "required": true, "placeholder": "sk_live_..."}]'::jsonb);