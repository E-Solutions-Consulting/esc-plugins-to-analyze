UPDATE public.platform_integrations
SET required_settings = '["client_id", "client_secret", "backend_url", "webhook_authorization"]'::jsonb
WHERE key = 'md_integrations'
  AND required_settings IS DISTINCT FROM '["client_id", "client_secret", "backend_url", "webhook_authorization"]'::jsonb;
