UPDATE public.platform_integrations
SET required_settings =
  '["username", "password", "url", "project_id", "patient_questionnaire_definition"]'::jsonb
WHERE key = 'telegramd'
  AND required_settings IS DISTINCT FROM
    '["username", "password", "url", "project_id", "patient_questionnaire_definition"]'::jsonb;
