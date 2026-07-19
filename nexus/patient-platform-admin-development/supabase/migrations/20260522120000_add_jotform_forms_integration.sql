-- Idempotent: seed Jotform into the platform integrations catalog.
-- Safe to re-run: ON CONFLICT updates fields only when they differ.
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
        'jotform',
        'Jotform',
        'Forms integration used by RTDH to access submitted forms and route form data to provider workflows.',
        '["api_url", "api_key"]'::jsonb,
        'forms'
      )
      ON CONFLICT (key) DO UPDATE SET
        name = EXCLUDED.name,
        description = EXCLUDED.description,
        required_settings = EXCLUDED.required_settings,
        category = EXCLUDED.category
      WHERE
        platform_integrations.name IS DISTINCT FROM EXCLUDED.name OR
        platform_integrations.description IS DISTINCT FROM EXCLUDED.description OR
        platform_integrations.required_settings IS DISTINCT FROM EXCLUDED.required_settings OR
        platform_integrations.category IS DISTINCT FROM EXCLUDED.category;
    ELSE
      INSERT INTO public.platform_integrations (
        key,
        name,
        description,
        required_settings
      )
      VALUES (
        'jotform',
        'Jotform',
        'Forms integration used by RTDH to access submitted forms and route form data to provider workflows.',
        '["api_url", "api_key"]'::jsonb
      )
      ON CONFLICT (key) DO UPDATE SET
        name = EXCLUDED.name,
        description = EXCLUDED.description,
        required_settings = EXCLUDED.required_settings
      WHERE
        platform_integrations.name IS DISTINCT FROM EXCLUDED.name OR
        platform_integrations.description IS DISTINCT FROM EXCLUDED.description OR
        platform_integrations.required_settings IS DISTINCT FROM EXCLUDED.required_settings;
    END IF;
  END IF;
END
$$;
