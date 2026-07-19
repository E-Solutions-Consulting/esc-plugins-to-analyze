-- Add tenant-managed terms and conditions content per product.
ALTER TABLE public.products
ADD COLUMN IF NOT EXISTS terms_and_conditions_html TEXT;
