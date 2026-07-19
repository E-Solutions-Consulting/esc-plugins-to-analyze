DO $$
BEGIN
  -- Keep Intercom required settings aligned with backend_secret-based auth hash.
  -- Handle environments where category might not exist yet.
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
      'Customer support integration for Intercom app and authentication configuration.',
      '["app_id", "access_token", "client_id", "client_secret", "backend_secret"]'::jsonb,
      'customer_support'
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
      'intercom',
      'Intercom',
      'Customer support integration for Intercom app and authentication configuration.',
      '["app_id", "access_token", "client_id", "client_secret", "backend_secret"]'::jsonb
    )
    ON CONFLICT (key) DO UPDATE
    SET
      name = EXCLUDED.name,
      description = EXCLUDED.description,
      required_settings = EXCLUDED.required_settings;
  END IF;
END
$$;

-- Remove obsolete Intercom setting from existing tenant integration rows.
DO $$
DECLARE
  obsolete_setting_key text := convert_from(
    decode('6d657373656e6765725f7365637265745f6b6579', 'hex'),
    'UTF8'
  );
BEGIN
  UPDATE public.tenant_integrations
  SET settings = COALESCE(settings, '{}'::jsonb) - obsolete_setting_key
  WHERE integration_key = 'intercom'
    AND COALESCE(settings, '{}'::jsonb) ? obsolete_setting_key;
END
$$;
