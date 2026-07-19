-- Add feature flag for chat support/intercom availability (idempotent).
-- Keep it inactive by default so Platform Admin can explicitly enable it.
INSERT INTO public.feature_flags (key, name, description, default_value, flag_type, is_active)
VALUES (
  'enable_ai_companion_mode',
  'Enable AI Companion Mode',
  'Enable AI-powered companion mode for tenant-facing experiences.',
  false,
  'boolean',
  false
)
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  default_value = EXCLUDED.default_value,
  flag_type = EXCLUDED.flag_type;
