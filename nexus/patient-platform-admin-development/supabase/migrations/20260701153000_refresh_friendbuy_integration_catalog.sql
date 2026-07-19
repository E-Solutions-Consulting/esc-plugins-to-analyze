

UPDATE public.platform_integrations
SET
  name = 'Friendbuy',
  description = 'Referral program integration for Friendbuy hosted widgets and conversion tracking.',
  category = 'referrals',
  required_settings = jsonb_build_array(
    jsonb_build_object('key', 'merchant_id', 'label', 'Merchant ID', 'type', 'text', 'required', true),
    jsonb_build_object('key', 'campaign_id', 'label', 'Campaign ID', 'type', 'text', 'required', true),
    jsonb_build_object('key', 'mount_element_id', 'label', 'Mount Element ID', 'type', 'text', 'required', false, 'description', 'Raw PP DOM id for the hosted widget mount. Enter #<id> in Friendbuy under "HTML Elements to insert widget into".'),
    jsonb_build_object('key', 'access_key', 'label', 'Access Key', 'type', 'password', 'required', true),
    jsonb_build_object('key', 'secret_key', 'label', 'Secret Key', 'type', 'password', 'required', true),
    jsonb_build_object('key', 'placement', 'label', 'Placement', 'type', 'text', 'required', false),
    jsonb_build_object('key', 'banner_title', 'label', 'Banner Title', 'type', 'text', 'required', false, 'description', 'PP-owned referral banner title. Example: Brello Bestie.'),
    jsonb_build_object('key', 'reward_label', 'label', 'Reward Label', 'type', 'text', 'required', false)
  )
WHERE key = 'friendbuy';

ALTER TABLE public.referral_program_configs
  DROP COLUMN IF EXISTS code_prefix,
  DROP COLUMN IF EXISTS discount_type,
  DROP COLUMN IF EXISTS discount_amount_cents,
  DROP COLUMN IF EXISTS minimum_purchase_cents,
  DROP COLUMN IF EXISTS validity_window_days,
  DROP COLUMN IF EXISTS max_redemptions,
  DROP COLUMN IF EXISTS reward_type,
  DROP COLUMN IF EXISTS annual_referral_cap,
  DROP COLUMN IF EXISTS annual_reward_cap_cents,
  DROP COLUMN IF EXISTS metadata;

UPDATE public.tenant_integrations
SET settings = COALESCE(settings, '{}'::jsonb) - 'webhook_secret'
WHERE integration_key = 'friendbuy'
  AND COALESCE(settings, '{}'::jsonb) ? 'webhook_secret';
