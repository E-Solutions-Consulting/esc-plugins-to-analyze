-- Add `publishable_key` to the Stripe payment provider's required settings so it
-- can be configured per-tenant in Nexus (alongside secret_key) and served to the
-- patient UI via tenant-info. The publishable key is safe to expose client-side.
-- Idempotent: only appends if not already present.

UPDATE public.payment_providers
SET required_settings = COALESCE(required_settings, '[]'::jsonb) || jsonb_build_array(
  jsonb_build_object(
    'key', 'publishable_key',
    'label', 'Publishable Key',
    'type', 'text',
    'required', false,
    'placeholder', 'pk_live_...'
  )
)
WHERE key = 'stripe'
  AND NOT (
    COALESCE(required_settings, '[]'::jsonb) @> '[{"key": "publishable_key"}]'::jsonb
  );
