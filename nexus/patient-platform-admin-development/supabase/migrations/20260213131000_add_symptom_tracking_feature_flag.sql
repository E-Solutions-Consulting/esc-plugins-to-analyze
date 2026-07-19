-- Add feature flag for symptom tracking (idempotent)
INSERT INTO public.feature_flags (key, name, description, default_value, flag_type, is_active)
SELECT
  'symptoms_tracking',
  'Symptom Tracking',
  'Enable symptom tracking for patient health check-ins.',
  false,
  'boolean',
  true
WHERE NOT EXISTS (
  SELECT 1 FROM public.feature_flags WHERE key = 'symptoms_tracking'
);
