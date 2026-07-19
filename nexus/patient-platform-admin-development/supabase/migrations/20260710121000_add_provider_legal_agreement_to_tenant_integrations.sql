ALTER TABLE public.tenant_integrations
  ADD COLUMN IF NOT EXISTS provider_legal_agreement TEXT;

COMMENT ON COLUMN public.tenant_integrations.provider_legal_agreement IS
  'Tenant-specific provider legal agreement HTML shown for agreement-type provider questionnaire questions.';
