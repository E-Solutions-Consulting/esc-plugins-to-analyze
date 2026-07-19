-- Add body measurement as a platform medication capability. It is selected
-- for weight-loss medications alongside the existing weight tracker capability.
INSERT INTO public.medication_capabilities (
  id,
  name,
  key,
  description,
  is_active,
  display_order
)
VALUES (
  '9a2f850d-3d0a-4381-9d77-8a46a80b26b9',
  'Body Measurement',
  'body_measurement',
  'Allows users to track body measurements during medication use.',
  true,
  55
)
ON CONFLICT (key) DO UPDATE
SET
  name = EXCLUDED.name,
  description = COALESCE(NULLIF(public.medication_capabilities.description, ''), EXCLUDED.description),
  is_active = EXCLUDED.is_active,
  display_order = EXCLUDED.display_order;
