-- Rename provider_id to provider_sku for products if needed
DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'products'
      AND column_name = 'provider_id'
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'products'
      AND column_name = 'provider_sku'
  ) THEN
    ALTER TABLE public.products
      RENAME COLUMN provider_id TO provider_sku;
  ELSIF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'products'
      AND column_name = 'provider_sku'
  ) THEN
    ALTER TABLE public.products
      ADD COLUMN provider_sku TEXT;
  END IF;
END $$;
