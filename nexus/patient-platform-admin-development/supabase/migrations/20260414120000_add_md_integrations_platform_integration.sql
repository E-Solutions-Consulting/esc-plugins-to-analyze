INSERT INTO public.platform_integrations (
  key,
  name,
  description,
  required_settings,
  category
)
VALUES (
  'md_integrations',
  'MD Integrations',
  'Provider platform integration for MD Integrations.',
  '["client_id", "client_secret", "backend_url"]'::jsonb,
  'provider_platform'
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
