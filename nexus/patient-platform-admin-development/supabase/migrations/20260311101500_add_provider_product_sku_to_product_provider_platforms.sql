ALTER TABLE public.product_provider_platforms
ADD COLUMN IF NOT EXISTS provider_product_sku TEXT;
