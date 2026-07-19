

UPDATE public.platform_integrations
SET
  required_settings = jsonb_build_array(
    jsonb_build_object('key', 'merchant_id', 'label', 'Merchant ID', 'type', 'text', 'required', true),
    jsonb_build_object('key', 'campaign_id', 'label', 'Campaign ID', 'type', 'text', 'required', true),
    jsonb_build_object('key', 'mount_element_id', 'label', 'Mount Element ID', 'type', 'text', 'required', false, 'description', 'Raw PP DOM id for the hosted widget mount. Enter #<id> in Friendbuy under "HTML Elements to insert widget into".'),
    jsonb_build_object('key', 'secret_key', 'label', 'Webhook Signing Secret', 'type', 'password', 'required', true, 'description', 'Verifies inbound Friendbuy webhook signatures (X-Friendbuy-Hmac-SHA256). Distinct from the Merchant API credentials below.'),
    jsonb_build_object('key', 'api_key', 'label', 'API Key', 'type', 'password', 'required', true, 'description', 'Merchant API key used by PP-admin Edge Functions to authenticate outbound Friendbuy API calls.'),
    jsonb_build_object('key', 'api_secret_key', 'label', 'API Secret Key', 'type', 'password', 'required', true, 'description', 'Merchant API secret paired with API Key for outbound Friendbuy API calls.'),
    jsonb_build_object('key', 'placement', 'label', 'Placement', 'type', 'text', 'required', false),
    jsonb_build_object('key', 'banner_title', 'label', 'Banner Title', 'type', 'text', 'required', false, 'description', 'PP-owned referral banner title. Example: Brello Bestie.'),
    jsonb_build_object('key', 'reward_label', 'label', 'Reward Label', 'type', 'text', 'required', false)
  )
WHERE key = 'friendbuy';

UPDATE public.tenant_integrations
SET settings = (COALESCE(settings, '{}'::jsonb) - 'access_key')
  || jsonb_build_object('api_key', settings -> 'access_key')
WHERE integration_key = 'friendbuy'
  AND COALESCE(settings, '{}'::jsonb) ? 'access_key';
