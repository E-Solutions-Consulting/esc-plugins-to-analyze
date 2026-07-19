DO $$
BEGIN
  IF to_regclass('public.platform_settings') IS NULL THEN
    RETURN;
  END IF;

  UPDATE public.platform_settings
  SET value = CASE
    WHEN value IS NOT NULL AND jsonb_typeof(value) = 'object' THEN value
    ELSE '{}'::jsonb
  END || jsonb_build_object(
    'patient_platform_consumer_webhook_token',
    COALESCE(
      value ->> 'patient_platform_consumer_webhook_token',
      value ->> 'patient-platform-consumer-webhook-token',
      ''
    )
  )
  WHERE key = 'rtdh_config'
    AND (
      value IS NULL
      OR jsonb_typeof(value) <> 'object'
      OR NOT value ? 'patient_platform_consumer_webhook_token'
    );
END
$$;
