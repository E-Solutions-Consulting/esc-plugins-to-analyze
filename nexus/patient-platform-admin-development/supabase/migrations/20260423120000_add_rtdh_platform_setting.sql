DO $$
BEGIN
  IF to_regclass('public.platform_settings') IS NULL THEN
    RETURN;
  END IF;

  INSERT INTO public.platform_settings (key, value, description, category)
  VALUES (
    'rtdh_config',
    '{"api_url": "", "access_token": "", "consumer_secret": ""}'::jsonb,
    'RealTime Data Hub connection settings',
    'integrations'
  )
  ON CONFLICT (key) DO UPDATE
  SET
    value = jsonb_build_object(
      'api_url', COALESCE(public.platform_settings.value ->> 'api_url', ''),
      'access_token', COALESCE(public.platform_settings.value ->> 'access_token', ''),
      'consumer_secret', COALESCE(public.platform_settings.value ->> 'consumer_secret', '')
    ),
    description = COALESCE(public.platform_settings.description, EXCLUDED.description),
    category = COALESCE(NULLIF(public.platform_settings.category, ''), EXCLUDED.category);
END
$$;
