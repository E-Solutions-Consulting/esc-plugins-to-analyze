DO $$
BEGIN
  -- Handle environments where category already exists.
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
      'intercom',
      'Intercom',
      'Customer support integration for Intercom app configuration.',
      '["app_id"]'::jsonb,
      'customer_support'
    )
    ON CONFLICT (key) DO UPDATE
    SET
      name = EXCLUDED.name,
      description = EXCLUDED.description,
      required_settings = EXCLUDED.required_settings,
      category = EXCLUDED.category;
  ELSE
    -- Fallback for older schemas without category.
    INSERT INTO public.platform_integrations (
      key,
      name,
      description,
      required_settings
    )
    VALUES (
      'intercom',
      'Intercom',
      'Customer support integration for Intercom app configuration.',
      '["app_id"]'::jsonb
    )
    ON CONFLICT (key) DO UPDATE
    SET
      name = EXCLUDED.name,
      description = EXCLUDED.description,
      required_settings = EXCLUDED.required_settings;
  END IF;
END
$$;
