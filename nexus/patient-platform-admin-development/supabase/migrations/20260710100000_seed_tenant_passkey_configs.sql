-- Store an environment-specific passkey configuration for each tenant. The
-- migration runs identically in every Supabase project; patient-api selects only
-- the row matching its current project/environment.

ALTER TABLE public.tenant_passkey_configs
  ADD COLUMN IF NOT EXISTS environment TEXT;

-- Existing manually seeded rows came from the development rollout. They are
-- assigned to development before replacing the old one-row-per-tenant key.
UPDATE public.tenant_passkey_configs
SET environment = 'development'
WHERE environment IS NULL;

ALTER TABLE public.tenant_passkey_configs
  DROP CONSTRAINT IF EXISTS tenant_passkey_configs_tenant_id_key;

ALTER TABLE public.tenant_passkey_configs
  ALTER COLUMN environment SET NOT NULL;

ALTER TABLE public.tenant_passkey_configs
  ADD CONSTRAINT tenant_passkey_configs_environment_known
  CHECK (environment IN ('development', 'staging', 'production'));

ALTER TABLE public.tenant_passkey_configs
  ADD CONSTRAINT tenant_passkey_configs_tenant_environment_rp_key
  UNIQUE (tenant_id, environment, rp_id);

INSERT INTO public.tenant_passkey_configs (
  tenant_id,
  rp_id,
  rp_name,
  allowed_origins,
  environment,
  is_enabled
)
SELECT
  t.id,
  config.rp_id,
  CASE t.slug
    WHEN 'brello' THEN 'Brello'
    ELSE 'CareLink'
  END,
  config.allowed_origins,
  config.environment,
  TRUE
FROM public.tenants t
JOIN (
  VALUES
    (
      'allia',
      'development',
      'alliahealthgroup.com',
      ARRAY['https://carelink-dev.alliahealthgroup.com']::TEXT[]
    ),
    (
      'allia',
      'staging',
      'alliahealthgroup.com',
      ARRAY['https://carelink-stg.alliahealthgroup.com']::TEXT[]
    ),
    (
      'allia',
      'production',
      'alliahealthgroup.com',
      ARRAY['https://carelink.alliahealthgroup.com']::TEXT[]
    ),
    (
      'allia',
      'development',
      'localhost',
      ARRAY['http://localhost:8080']::TEXT[]
    ),
    (
      'brello',
      'development',
      'alliahealthgroup.com',
      ARRAY['https://app-dev.joinbrello.com']::TEXT[]
    ),
    (
      'brello',
      'staging',
      'alliahealthgroup.com',
      ARRAY['https://app-stg.joinbrello.com']::TEXT[]
    ),
    (
      'brello',
      'production',
      'alliahealthgroup.com',
      ARRAY['https://app.joinbrello.com']::TEXT[]
    ),
    (
      'brello',
      'development',
      'localhost',
      ARRAY['http://localhost:8080']::TEXT[]
    )
) AS config(tenant_slug, environment, rp_id, allowed_origins)
  ON config.tenant_slug = t.slug
WHERE t.slug IN ('allia', 'brello')
ON CONFLICT (tenant_id, environment, rp_id) DO UPDATE
SET
  rp_id = EXCLUDED.rp_id,
  rp_name = EXCLUDED.rp_name,
  allowed_origins = EXCLUDED.allowed_origins,
  is_enabled = EXCLUDED.is_enabled,
  updated_at = now();
