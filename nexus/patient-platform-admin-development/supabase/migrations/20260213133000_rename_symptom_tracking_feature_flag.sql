-- Rename symptom tracking feature flag key (idempotent)
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM public.feature_flags WHERE key = 'symptom_tracking'
  ) AND NOT EXISTS (
    SELECT 1 FROM public.feature_flags WHERE key = 'symptoms_tracking'
  ) THEN
    UPDATE public.feature_flags
    SET key = 'symptoms_tracking'
    WHERE key = 'symptom_tracking';
  END IF;
END $$;
