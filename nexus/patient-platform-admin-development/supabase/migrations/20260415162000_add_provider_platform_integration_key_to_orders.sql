DO $$
BEGIN
  IF to_regclass('public.orders') IS NULL THEN
    RETURN;
  END IF;

  ALTER TABLE public.orders
    ADD COLUMN IF NOT EXISTS provider_platform_integration_key TEXT;

  UPDATE public.orders
  SET provider_platform_integration_key = 'telegramd'
  WHERE provider_platform_integration_key IS NULL
    OR btrim(provider_platform_integration_key) = '';

  ALTER TABLE public.orders
    ALTER COLUMN provider_platform_integration_key SET DEFAULT 'telegramd';

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'orders'
      AND column_name = 'provider_platform_integration_key'
      AND is_nullable = 'YES'
  ) THEN
    ALTER TABLE public.orders
      ALTER COLUMN provider_platform_integration_key SET NOT NULL;
  END IF;

  COMMENT ON COLUMN public.orders.provider_platform_integration_key IS
    'Selected provider platform integration key for this order (e.g., telegramd).';
END $$;

CREATE INDEX IF NOT EXISTS idx_orders_provider_platform_integration_key
  ON public.orders(provider_platform_integration_key);
