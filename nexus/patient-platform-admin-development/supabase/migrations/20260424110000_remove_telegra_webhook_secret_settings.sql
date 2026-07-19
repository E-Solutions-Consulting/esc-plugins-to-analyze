UPDATE public.platform_integrations
SET required_settings = '["username", "password", "url", "patient_questionnaire_definition"]'::jsonb
WHERE key = 'telegramd'
  AND required_settings IS DISTINCT FROM '["username", "password", "url", "patient_questionnaire_definition"]'::jsonb;

UPDATE public.tenant_integrations
SET settings = settings
  - 'orders_webhook_secret_token'
  - 'prescription_webhook_secret_token'
  - 'pharmacy_webhook_secret_token'
  - 'webhook_secret'
WHERE integration_key = 'telegramd'
  AND settings IS NOT NULL
  AND (
    settings ? 'orders_webhook_secret_token'
    OR settings ? 'prescription_webhook_secret_token'
    OR settings ? 'pharmacy_webhook_secret_token'
    OR settings ? 'webhook_secret'
  );
