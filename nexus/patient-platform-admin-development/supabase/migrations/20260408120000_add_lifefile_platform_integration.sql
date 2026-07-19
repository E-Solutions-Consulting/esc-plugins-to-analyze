-- Idempotent: seed lifefile into platform_integrations catalog.
-- Safe to re-run: ON CONFLICT updates all fields to the desired values.
INSERT INTO public.platform_integrations (key, name, description, required_settings, category)
VALUES (
  'lifefile',
  'LifeFile',
  'LifeFile pharmacy platform. Receives real-time prescription and fulfillment status webhooks.',
  '["webhook_username", "webhook_password"]'::jsonb,
  'pharmacy'
)
ON CONFLICT (key) DO UPDATE SET
  name              = EXCLUDED.name,
  description       = EXCLUDED.description,
  required_settings = EXCLUDED.required_settings,
  category          = EXCLUDED.category
WHERE
  platform_integrations.name              IS DISTINCT FROM EXCLUDED.name              OR
  platform_integrations.description       IS DISTINCT FROM EXCLUDED.description       OR
  platform_integrations.required_settings IS DISTINCT FROM EXCLUDED.required_settings OR
  platform_integrations.category          IS DISTINCT FROM EXCLUDED.category;
