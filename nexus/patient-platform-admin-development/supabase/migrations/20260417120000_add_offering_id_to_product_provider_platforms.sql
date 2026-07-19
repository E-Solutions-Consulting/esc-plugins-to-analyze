ALTER TABLE public.product_provider_platforms
ADD COLUMN IF NOT EXISTS offering_id TEXT;
