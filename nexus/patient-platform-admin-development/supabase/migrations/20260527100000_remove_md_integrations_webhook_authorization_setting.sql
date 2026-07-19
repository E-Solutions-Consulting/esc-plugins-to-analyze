UPDATE public.platform_integrations
SET required_settings = (
  SELECT COALESCE(jsonb_agg(setting ORDER BY ordinality), '[]'::jsonb)
  FROM jsonb_array_elements_text(required_settings) WITH ORDINALITY AS settings(setting, ordinality)
  WHERE setting <> 'webhook_authorization'
)
WHERE key = 'md_integrations'
  AND COALESCE(required_settings, '[]'::jsonb) ? 'webhook_authorization';

UPDATE public.tenant_integrations
SET settings = COALESCE(settings, '{}'::jsonb) - 'webhook_authorization'
WHERE integration_key = 'md_integrations'
  AND COALESCE(settings, '{}'::jsonb) ? 'webhook_authorization';
