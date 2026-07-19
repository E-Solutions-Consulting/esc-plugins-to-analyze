-- Add feature flag for education content (idempotent).
-- The platform default stays off; enable Allia explicitly where the tenant exists.
INSERT INTO public.feature_flags (key, name, description, default_value, flag_type, is_active)
VALUES (
  'education_content',
  'Education Content',
  'Enable patient education content experiences for tenants.',
  false,
  'boolean',
  true
)
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  default_value = EXCLUDED.default_value,
  flag_type = EXCLUDED.flag_type,
  is_active = EXCLUDED.is_active;

INSERT INTO public.tenant_feature_flag_overrides (tenant_id, feature_flag_id, enabled)
SELECT
  tenants.id,
  feature_flags.id,
  true
FROM public.tenants
CROSS JOIN public.feature_flags
WHERE tenants.slug = 'allia'
  AND feature_flags.key = 'education_content'
ON CONFLICT (tenant_id, feature_flag_id) DO UPDATE
SET enabled = EXCLUDED.enabled;
