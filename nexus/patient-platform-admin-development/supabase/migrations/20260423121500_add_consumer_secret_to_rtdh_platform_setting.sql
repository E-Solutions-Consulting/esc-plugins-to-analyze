DO $$
BEGIN
  IF to_regclass('public.platform_settings') IS NULL THEN
    RETURN;
  END IF;

  UPDATE public.platform_settings
  SET value = jsonb_build_object(
    'api_url', COALESCE(value ->> 'api_url', ''),
    'access_token', COALESCE(value ->> 'access_token', ''),
    'consumer_secret', COALESCE(value ->> 'consumer_secret', '')
  )
  WHERE key = 'rtdh_config'
    AND (
      value IS NULL
      OR jsonb_typeof(value) <> 'object'
      OR NOT value ? 'consumer_secret'
      OR NOT value ? 'api_url'
      OR NOT value ? 'access_token'
    );
END
$$;
