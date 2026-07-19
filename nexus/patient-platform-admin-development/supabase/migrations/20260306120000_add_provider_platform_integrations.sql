INSERT INTO public.platform_integrations (
  key,
  name,
  description,
  required_settings,
  category
)
VALUES
  (
    'telegramd',
    'TelegraMD',
    'Provider platform integration for TelegraMD.',
    '["access_token", "url"]'::jsonb,
    'provider_platform'
  ),
  (
    'allia_care',
    'Allia Care',
    'Provider platform integration for Allia Care.',
    '["access_token", "url"]'::jsonb,
    'provider_platform'
  )
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  required_settings = EXCLUDED.required_settings,
  category = EXCLUDED.category;
