DO $$
BEGIN
  -- Keep Intercom required settings aligned with actual runtime usage.
  -- Only app_id is required. backend_secret and help_center_url are optional.
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
      'Customer support integration for Intercom Messenger configuration.',
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
    INSERT INTO public.platform_integrations (
      key,
      name,
      description,
      required_settings
    )
    VALUES (
      'intercom',
      'Intercom',
      'Customer support integration for Intercom Messenger configuration.',
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

-- Remove unused legacy Intercom settings from existing tenant integration rows.
UPDATE public.tenant_integrations
SET settings = COALESCE(settings, '{}'::jsonb)
  - 'access_token'
  - 'client_id'
  - 'client_secret'
WHERE integration_key = 'intercom'
  AND (
    COALESCE(settings, '{}'::jsonb) ? 'access_token'
    OR COALESCE(settings, '{}'::jsonb) ? 'client_id'
    OR COALESCE(settings, '{}'::jsonb) ? 'client_secret'
  );
