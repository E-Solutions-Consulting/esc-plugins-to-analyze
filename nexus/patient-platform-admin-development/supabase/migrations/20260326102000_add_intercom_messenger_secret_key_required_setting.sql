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
  '["app_id", "access_token", "client_id", "client_secret", "backend_secret", "messenger_secret_key"]'::jsonb,
  'customer_support'
)
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  required_settings = EXCLUDED.required_settings,
  category = EXCLUDED.category;
