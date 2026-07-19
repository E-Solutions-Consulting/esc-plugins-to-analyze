-- Seed platform lookup data so staging and production have the same product
-- categories and medication capabilities expected by the development flow.

INSERT INTO public.product_categories (
  id,
  name,
  key,
  description,
  is_active,
  display_order,
  created_at,
  updated_at
)
VALUES
  (
    '9b06587e-eee5-4b88-831a-cfca948d0364',
    'Energy',
    'energy',
    NULL,
    true,
    0,
    '2026-01-22T17:18:50.14878+00:00',
    '2026-04-13T16:50:33.715639+00:00'
  ),
  (
    '9e09f45f-4e9d-403e-bb8d-7499a50d4a7b',
    'Longevity Protocols',
    'longevity_protocols',
    NULL,
    true,
    0,
    '2026-01-22T17:18:18.753663+00:00',
    '2026-04-13T16:50:33.715639+00:00'
  ),
  (
    'ce86cdbe-421c-43fe-a3b3-7eee817b5d2d',
    'Weight Loss',
    'weight_loss',
    NULL,
    true,
    0,
    '2026-01-22T17:18:26.400343+00:00',
    '2026-04-13T16:50:33.715639+00:00'
  )
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  is_active = EXCLUDED.is_active,
  display_order = EXCLUDED.display_order,
  updated_at = EXCLUDED.updated_at;

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
  ),
  (
    '31ae3745-94be-46e2-b1b9-206c0f3e8fe6',
    'Shot Counter',
    'shot_counter',
    NULL,
    true,
    30
  ),
  (
    '42303ae9-d940-4ee2-8dbd-27a78a7e2d98',
    'Pill Counter',
    'pill_counter',
    NULL,
    true,
    40
  ),
  (
    'c07f940c-a4a9-4cbc-9a1f-00e9fad476ab',
    'Weight Tracker',
    'weight_tracker',
    NULL,
    true,
    50
  ),
  (
    'c187de66-355d-49b3-b894-df47aa818cba',
    'Energy Tracker',
    'energy_tracker',
    NULL,
    true,
    60
  )
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = COALESCE(NULLIF(public.medication_capabilities.description, ''), EXCLUDED.description),
  is_active = EXCLUDED.is_active,
  display_order = EXCLUDED.display_order;
