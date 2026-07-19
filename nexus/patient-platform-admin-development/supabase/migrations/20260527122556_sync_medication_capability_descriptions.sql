-- Backfill current development descriptions for medication capabilities. This
-- preserves non-empty environment-specific descriptions in staging/production.

INSERT INTO public.medication_capabilities (
  id,
  name,
  key,
  description,
  is_active,
  display_order
)
VALUES
  (
    '6df053d4-2ab6-4384-a384-23158fd1e8cb',
    'Mood Tracker',
    'mood_tracking',
    'Users can track mood changes, during their medication intake.',
    true,
    10
  ),
  (
    'e71b492e-a5e2-4133-acc0-37910209c421',
    'Symptoms Tracker',
    'symptoms_tracking',
    'Allows users to track symptoms felt during the intake of a medication.',
    true,
    20
  )
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = COALESCE(NULLIF(public.medication_capabilities.description, ''), EXCLUDED.description),
  is_active = EXCLUDED.is_active,
  display_order = EXCLUDED.display_order;
