UPDATE public.platform_integrations
SET
  key = 'zito_care',
  name = 'Zito Care',
  description = 'Provider platform integration for Zito Care.'
WHERE key = 'allia_care'
  AND category = 'provider_platform';

UPDATE public.tenant_integrations
SET integration_key = 'zito_care'
WHERE integration_key = 'allia_care';

UPDATE public.orders
SET provider_platform_integration_key = 'zito_care'
WHERE provider_platform_integration_key = 'allia_care';
