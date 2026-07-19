-- Add feature flag for mood change tracking (idempotent)
INSERT INTO public.feature_flags (key, name, description, default_value, flag_type, is_active)
SELECT
  'mood_tracking',
  'Mood Tracking',
  'Enable mood tracking for patient health check-ins.',
  false,
  'boolean',
  true
WHERE NOT EXISTS (
  SELECT 1 FROM public.feature_flags WHERE key = 'mood_tracking'
);
