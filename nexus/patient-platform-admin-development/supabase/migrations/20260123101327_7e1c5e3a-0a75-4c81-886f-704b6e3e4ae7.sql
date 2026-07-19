-- Create product_payment_providers junction table
CREATE TABLE public.product_payment_providers (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  tenant_payment_provider_id UUID NOT NULL REFERENCES public.tenant_payment_providers(id) ON DELETE CASCADE,
  is_enabled BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  UNIQUE(product_id, tenant_payment_provider_id)
);

-- Enable RLS
ALTER TABLE public.product_payment_providers ENABLE ROW LEVEL SECURITY;

-- RLS policy: Access via product ownership
CREATE POLICY "Access via product ownership for payment providers"
  ON public.product_payment_providers
  FOR ALL
  USING (
    product_id IN (
      SELECT id FROM public.products 
      WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
    )
  );

-- Add index for faster lookups
CREATE INDEX idx_product_payment_providers_product ON public.product_payment_providers(product_id);
CREATE INDEX idx_product_payment_providers_tenant_provider ON public.product_payment_providers(tenant_payment_provider_id);