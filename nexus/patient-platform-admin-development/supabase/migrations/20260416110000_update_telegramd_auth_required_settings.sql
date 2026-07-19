UPDATE public.platform_integrations
SET required_settings = '["username", "password", "url", "orders_webhook_secret_token", "patient_questionnaire_definition"]'::jsonb
WHERE key = 'telegramd';
