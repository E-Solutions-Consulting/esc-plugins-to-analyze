-- Add feature flag for fitness content (idempotent)
INSERT INTO public.feature_flags (key, name, description, default_value, flag_type, is_active)
SELECT
  'fitness_content',
  'Fitness Content',
  'Enable fitness content experiences for tenants.',
  false,
  'boolean',
  true
WHERE NOT EXISTS (
  SELECT 1 FROM public.feature_flags WHERE key = 'fitness_content'
);
