-- Add optional display copy for the PP-owned Friendbuy referral banner.
-- This is safe client config only; Friendbuy secrets remain backend-only.

UPDATE public.platform_integrations
SET required_settings = jsonb_build_array(
  jsonb_build_object('key', 'merchant_id', 'label', 'Merchant ID', 'type', 'text', 'required', true),
  jsonb_build_object('key', 'campaign_id', 'label', 'Campaign ID', 'type', 'text', 'required', true),
  jsonb_build_object('key', 'mount_element_id', 'label', 'Mount Element ID', 'type', 'text', 'required', false, 'description', 'Raw PP DOM id for the hosted widget mount. Enter #<id> in Friendbuy under "HTML Elements to insert widget into".'),
  jsonb_build_object('key', 'access_key', 'label', 'Access Key', 'type', 'password', 'required', true),
  jsonb_build_object('key', 'secret_key', 'label', 'Secret Key', 'type', 'password', 'required', true),
  jsonb_build_object('key', 'webhook_secret', 'label', 'Webhook Secret', 'type', 'password', 'required', false),
  jsonb_build_object('key', 'placement', 'label', 'Placement', 'type', 'text', 'required', false),
  jsonb_build_object('key', 'banner_title', 'label', 'Banner Title', 'type', 'text', 'required', false, 'description', 'PP-owned referral banner title. Example: Brello Bestie.'),
  jsonb_build_object('key', 'reward_label', 'label', 'Reward Label', 'type', 'text', 'required', false)
)
WHERE key = 'friendbuy';
