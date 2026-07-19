UPDATE public.platform_integrations
SET required_settings = '["access_token", "url", "orders_webhook_secret_token", "patient_questionnaire_definition"]'::jsonb
WHERE key = 'telegramd';
