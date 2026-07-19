-- Remove messenger_secret_key from Intercom required settings and tenant settings.
-- Safe to run multiple times.

UPDATE public.platform_integrations
SET required_settings = COALESCE(
  (
    SELECT jsonb_agg(setting)
    FROM jsonb_array_elements_text(COALESCE(required_settings, '[]'::jsonb)) AS setting
    WHERE setting <> 'messenger_secret_key'
  ),
  '[]'::jsonb
)
WHERE key = 'intercom'
  AND COALESCE(required_settings, '[]'::jsonb) ? 'messenger_secret_key';

UPDATE public.tenant_integrations
SET settings = COALESCE(settings, '{}'::jsonb) - 'messenger_secret_key'
WHERE integration_key = 'intercom'
  AND COALESCE(settings, '{}'::jsonb) ? 'messenger_secret_key';
