-- Add a tenant-scoped feature flag for patient passkey registration and login.
-- Keep the platform default off, enable the current Allia/CareLink rollout,
-- and explicitly keep Brello disabled until its rollout is ready.

INSERT INTO public.feature_flags (
  key,
  name,
  description,
  default_value,
  flag_type,
  is_active
)
VALUES (
  'passkey_authentication',
  'Passkey Authentication',
  'Enable patient passkey registration and authentication.',
  FALSE,
  'boolean',
  TRUE
)
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  default_value = EXCLUDED.default_value,
  flag_type = EXCLUDED.flag_type,
  is_active = EXCLUDED.is_active,
  updated_at = now();

INSERT INTO public.tenant_feature_flag_overrides (
  tenant_id,
  feature_flag_id,
  enabled
)
SELECT
  tenants.id,
  feature_flags.id,
  tenants.slug = 'allia'
FROM public.tenants
CROSS JOIN public.feature_flags
WHERE tenants.slug IN ('allia', 'brello')
  AND feature_flags.key = 'passkey_authentication'
ON CONFLICT (tenant_id, feature_flag_id) DO UPDATE
SET
  enabled = EXCLUDED.enabled,
  updated_at = now();
