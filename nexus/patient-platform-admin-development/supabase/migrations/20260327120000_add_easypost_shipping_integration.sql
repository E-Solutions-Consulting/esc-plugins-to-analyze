DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = 'public'
      AND table_name = 'platform_integrations'
  ) THEN
    IF EXISTS (
      SELECT 1
      FROM information_schema.columns
      WHERE table_schema = 'public'
        AND table_name = 'platform_integrations'
        AND column_name = 'category'
    ) THEN
      INSERT INTO public.platform_integrations (
        key,
        name,
        description,
        required_settings,
        category
      )
      VALUES (
        'easypost',
        'EasyPost',
        'Shipping integration for EasyPost label purchasing and carrier routing.',
        '["api_key", "carrier"]'::jsonb,
        'shipping'
      )
      ON CONFLICT (key) DO UPDATE
      SET
        name = EXCLUDED.name,
        description = EXCLUDED.description,
        required_settings = EXCLUDED.required_settings,
        category = EXCLUDED.category;
    ELSE
      INSERT INTO public.platform_integrations (
        key,
        name,
        description,
        required_settings
      )
      VALUES (
        'easypost',
        'EasyPost',
        'Shipping integration for EasyPost label purchasing and carrier routing.',
        '["api_key", "carrier"]'::jsonb
      )
      ON CONFLICT (key) DO UPDATE
      SET
        name = EXCLUDED.name,
        description = EXCLUDED.description,
        required_settings = EXCLUDED.required_settings;
    END IF;
  END IF;
END
$$;
