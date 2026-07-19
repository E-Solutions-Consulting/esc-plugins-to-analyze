-- Add provider_sku to products
ALTER TABLE public.products
  ADD COLUMN IF NOT EXISTS provider_sku TEXT;
