-- Add feature flag for activity tracking (idempotent)
INSERT INTO public.feature_flags (key, name, description, default_value, flag_type, is_active)
SELECT
  'activities_tracking',
  'Activities Tracking',
  'Enable activities tracking for patient health check-ins.',
  false,
  'boolean',
  true
WHERE NOT EXISTS (
  SELECT 1 FROM public.feature_flags WHERE key = 'activities_tracking'
);
