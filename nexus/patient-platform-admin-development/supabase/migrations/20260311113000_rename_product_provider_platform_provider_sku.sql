DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'product_provider_platforms'
      AND column_name = 'provider_sku'
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'product_provider_platforms'
      AND column_name = 'provider_product_variation_sku'
  ) THEN
    ALTER TABLE public.product_provider_platforms
      RENAME COLUMN provider_sku TO provider_product_variation_sku;
  END IF;
END $$;
