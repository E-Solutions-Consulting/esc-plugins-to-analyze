UPDATE public.platform_integrations
SET required_settings = '["client_id", "client_secret", "backend_url", "patient_questionnaire_definition"]'::jsonb
WHERE key = 'md_integrations';
