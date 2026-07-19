-- Keep the medication capability keys used by the application and remove the
-- legacy tracker aliases. Descriptions from legacy aliases are preserved when
-- the canonical rows do not already have one.

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

WITH capability_aliases (canonical_key, legacy_key) AS (
  VALUES
    ('mood_tracking', 'mood_tracker'),
    ('symptoms_tracking', 'symptoms_tracker')
)
UPDATE public.medication_capabilities AS canonical
SET
  description = COALESCE(NULLIF(canonical.description, ''), legacy.description),
  is_active = canonical.is_active OR legacy.is_active
FROM capability_aliases AS aliases
JOIN public.medication_capabilities AS legacy
  ON legacy.key = aliases.legacy_key
WHERE canonical.key = aliases.canonical_key;

WITH capability_aliases (canonical_key, legacy_key) AS (
  VALUES
    ('mood_tracking', 'mood_tracker'),
    ('symptoms_tracking', 'symptoms_tracker')
)
INSERT INTO public.medication_capability_assignments (
  medication_id,
  capability_id
)
SELECT
  assignment.medication_id,
  canonical.id
FROM public.medication_capability_assignments AS assignment
JOIN public.medication_capabilities AS legacy
  ON legacy.id = assignment.capability_id
JOIN capability_aliases AS aliases
  ON aliases.legacy_key = legacy.key
JOIN public.medication_capabilities AS canonical
  ON canonical.key = aliases.canonical_key
ON CONFLICT (medication_id, capability_id) DO NOTHING;

DELETE FROM public.medication_capability_assignments AS assignment
USING public.medication_capabilities AS legacy
WHERE assignment.capability_id = legacy.id
  AND legacy.key IN ('mood_tracker', 'symptoms_tracker');

DELETE FROM public.medication_capabilities
WHERE key IN ('mood_tracker', 'symptoms_tracker');
