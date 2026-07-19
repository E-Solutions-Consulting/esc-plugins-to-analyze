-- Multi-tenant Friendbuy referral program configuration, sync state, and event logs.

INSERT INTO public.feature_flags (
  key,
  name,
  description,
  default_value,
  flag_type,
  is_active
)
SELECT
  'friendbuy_referrals',
  'Friendbuy Referrals',
  'Enable Friendbuy referral widget and conversion tracking in the patient UI.',
  false,
  'boolean',
  true
WHERE NOT EXISTS (
  SELECT 1 FROM public.feature_flags WHERE key = 'friendbuy_referrals'
);

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'platform_integrations'
      AND column_name = 'category'
  ) THEN
    INSERT INTO public.platform_integrations (
      key,
      name,
      description,
      required_settings,
      category
    )
    VALUES (
      'friendbuy',
      'Friendbuy',
      'Referral program integration for Friendbuy hosted widgets and Merchant API conversion tracking.',
      jsonb_build_array(
        jsonb_build_object('key', 'merchant_id', 'label', 'Merchant ID', 'type', 'text', 'required', true),
        jsonb_build_object('key', 'campaign_id', 'label', 'Campaign ID', 'type', 'text', 'required', true),
        jsonb_build_object('key', 'mount_element_id', 'label', 'Mount Element ID', 'type', 'text', 'required', false, 'description', 'Raw PP DOM id for the hosted widget mount. Enter #<id> in Friendbuy under "HTML Elements to insert widget into".'),
        jsonb_build_object('key', 'access_key', 'label', 'Access Key', 'type', 'password', 'required', true),
        jsonb_build_object('key', 'secret_key', 'label', 'Secret Key', 'type', 'password', 'required', true),
        jsonb_build_object('key', 'webhook_secret', 'label', 'Webhook Secret', 'type', 'password', 'required', false),
        jsonb_build_object('key', 'placement', 'label', 'Placement', 'type', 'text', 'required', false),
        jsonb_build_object('key', 'reward_label', 'label', 'Reward Label', 'type', 'text', 'required', false)
      ),
      'referrals'
    )
    ON CONFLICT (key) DO UPDATE
    SET
      name = EXCLUDED.name,
      description = EXCLUDED.description,
      required_settings = EXCLUDED.required_settings,
      category = EXCLUDED.category;
  ELSE
    INSERT INTO public.platform_integrations (
      key,
      name,
      description,
      required_settings
    )
    VALUES (
      'friendbuy',
      'Friendbuy',
      'Referral program integration for Friendbuy hosted widgets and Merchant API conversion tracking.',
      jsonb_build_array(
        jsonb_build_object('key', 'merchant_id', 'label', 'Merchant ID', 'type', 'text', 'required', true),
        jsonb_build_object('key', 'campaign_id', 'label', 'Campaign ID', 'type', 'text', 'required', true),
        jsonb_build_object('key', 'mount_element_id', 'label', 'Mount Element ID', 'type', 'text', 'required', false, 'description', 'Raw PP DOM id for the hosted widget mount. Enter #<id> in Friendbuy under "HTML Elements to insert widget into".'),
        jsonb_build_object('key', 'access_key', 'label', 'Access Key', 'type', 'password', 'required', true),
        jsonb_build_object('key', 'secret_key', 'label', 'Secret Key', 'type', 'password', 'required', true),
        jsonb_build_object('key', 'webhook_secret', 'label', 'Webhook Secret', 'type', 'password', 'required', false),
        jsonb_build_object('key', 'placement', 'label', 'Placement', 'type', 'text', 'required', false),
        jsonb_build_object('key', 'reward_label', 'label', 'Reward Label', 'type', 'text', 'required', false)
      )
    )
    ON CONFLICT (key) DO UPDATE
    SET
      name = EXCLUDED.name,
      description = EXCLUDED.description,
      required_settings = EXCLUDED.required_settings;
  END IF;
END
$$;

CREATE TABLE IF NOT EXISTS public.friendbuy_event_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  request_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  response_payload JSONB,
  error_message TEXT,
  sent_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT friendbuy_event_logs_unique_event UNIQUE (tenant_id, event_type, entity_id)
);

ALTER TABLE public.friendbuy_event_logs ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'friendbuy_event_logs'
      AND policyname = 'Service role can manage friendbuy event logs'
  ) THEN
    CREATE POLICY "Service role can manage friendbuy event logs"
      ON public.friendbuy_event_logs
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'friendbuy_event_logs'
      AND policyname = 'Platform superadmins can read friendbuy event logs'
  ) THEN
    CREATE POLICY "Platform superadmins can read friendbuy event logs"
      ON public.friendbuy_event_logs
      FOR SELECT
      USING (public.is_platform_superadmin(auth.uid()));
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_friendbuy_event_logs_updated_at'
  ) THEN
    CREATE TRIGGER update_friendbuy_event_logs_updated_at
      BEFORE UPDATE ON public.friendbuy_event_logs
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END
$$;

CREATE TABLE IF NOT EXISTS public.referral_program_configs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'disabled'
    CHECK (status IN ('draft', 'active', 'paused', 'disabled', 'archived')),
  code_prefix TEXT,
  discount_type TEXT NOT NULL DEFAULT 'fixed'
    CHECK (discount_type IN ('fixed')),
  discount_amount_cents INTEGER NOT NULL DEFAULT 0
    CHECK (discount_amount_cents >= 0),
  currency TEXT NOT NULL DEFAULT 'USD',
  minimum_purchase_cents INTEGER NOT NULL DEFAULT 0
    CHECK (minimum_purchase_cents >= 0),
  validity_window_days INTEGER
    CHECK (validity_window_days IS NULL OR validity_window_days > 0),
  max_redemptions INTEGER
    CHECK (max_redemptions IS NULL OR max_redemptions > 0),
  reward_type TEXT NOT NULL DEFAULT 'stripe_customer_balance'
    CHECK (reward_type IN ('stripe_customer_balance', 'stripe_coupon', 'gift_card')),
  reward_amount_cents INTEGER NOT NULL DEFAULT 0
    CHECK (reward_amount_cents >= 0),
  annual_referral_cap INTEGER
    CHECK (annual_referral_cap IS NULL OR annual_referral_cap > 0),
  annual_reward_cap_cents INTEGER
    CHECK (annual_reward_cap_cents IS NULL OR annual_reward_cap_cents >= 0),
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT referral_program_configs_tenant_unique UNIQUE (tenant_id)
);

CREATE TABLE IF NOT EXISTS public.referral_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  program_config_id UUID REFERENCES public.referral_program_configs(id) ON DELETE SET NULL,
  referrer_patient_id UUID REFERENCES public.patients(id) ON DELETE SET NULL,
  friend_patient_id UUID REFERENCES public.patients(id) ON DELETE SET NULL,
  referrer_email TEXT,
  friend_email TEXT,
  referral_code TEXT,
  referral_url TEXT,
  status TEXT NOT NULL DEFAULT 'issued'
    CHECK (status IN (
      'issued',
      'delivered',
      'clicked',
      'purchased',
      'conversion_recorded',
      'reward_pending',
      'rewarded',
      'invalid',
      'expired',
      'exception'
    )),
  reward_status TEXT NOT NULL DEFAULT 'not_earned'
    CHECK (reward_status IN (
      'not_earned',
      'pending_eligibility',
      'pending_approval',
      'credited',
      'rejected',
      'manual_exception'
    )),
  friendbuy_customer_id TEXT,
  friendbuy_purl_id TEXT,
  friendbuy_share_id TEXT,
  friendbuy_click_id TEXT,
  friendbuy_conversion_id TEXT,
  friendbuy_reward_id TEXT,
  friendbuy_campaign_id TEXT,
  friendbuy_widget_id TEXT,
  stripe_coupon_id TEXT,
  stripe_promotion_code_id TEXT,
  stripe_customer_id TEXT,
  stripe_payment_intent_id TEXT,
  stripe_invoice_id TEXT,
  stripe_subscription_id TEXT,
  first_purchase_order_id UUID REFERENCES public.orders(id) ON DELETE SET NULL,
  discount_amount_cents INTEGER,
  reward_amount_cents INTEGER,
  currency TEXT,
  issued_at TIMESTAMPTZ,
  delivered_at TIMESTAMPTZ,
  clicked_at TIMESTAMPTZ,
  purchased_at TIMESTAMPTZ,
  reward_credited_at TIMESTAMPTZ,
  expires_at TIMESTAMPTZ,
  raw_friendbuy JSONB NOT NULL DEFAULT '{}'::jsonb,
  raw_stripe JSONB NOT NULL DEFAULT '{}'::jsonb,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS referral_records_tenant_code_unique
  ON public.referral_records (tenant_id, referral_code)
  WHERE referral_code IS NOT NULL;

CREATE INDEX IF NOT EXISTS referral_records_tenant_status_idx
  ON public.referral_records (tenant_id, status, reward_status);

CREATE INDEX IF NOT EXISTS referral_records_referrer_email_idx
  ON public.referral_records (tenant_id, lower(referrer_email));

CREATE INDEX IF NOT EXISTS referral_records_friend_email_idx
  ON public.referral_records (tenant_id, lower(friend_email));

CREATE TABLE IF NOT EXISTS public.referral_sync_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  source TEXT NOT NULL CHECK (source IN ('friendbuy_api', 'friendbuy_webhook', 'stripe', 'nexus')),
  source_event_type TEXT NOT NULL,
  source_event_id TEXT NOT NULL,
  referral_record_id UUID REFERENCES public.referral_records(id) ON DELETE SET NULL,
  status TEXT NOT NULL DEFAULT 'processed'
    CHECK (status IN ('processed', 'ignored', 'failed')),
  payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  error_message TEXT,
  processed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT referral_sync_events_unique_event UNIQUE (tenant_id, source, source_event_type, source_event_id)
);

CREATE TABLE IF NOT EXISTS public.referral_reward_actions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  referral_record_id UUID REFERENCES public.referral_records(id) ON DELETE SET NULL,
  action_type TEXT NOT NULL
    CHECK (action_type IN ('stripe_credit', 'stripe_coupon', 'manual_reward', 'courtesy_refund', 'reject')),
  status TEXT NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending', 'success', 'failed', 'cancelled')),
  idempotency_key TEXT NOT NULL,
  amount_cents INTEGER,
  currency TEXT,
  stripe_customer_balance_transaction_id TEXT,
  stripe_refund_id TEXT,
  request_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  response_payload JSONB,
  error_message TEXT,
  created_by UUID REFERENCES public.admin_users(id) ON DELETE SET NULL,
  processed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT referral_reward_actions_unique_key UNIQUE (tenant_id, action_type, idempotency_key)
);

ALTER TABLE public.referral_program_configs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.referral_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.referral_sync_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.referral_reward_actions ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_program_configs'
      AND policyname = 'Tenant admins can manage referral program configs'
  ) THEN
    CREATE POLICY "Tenant admins can manage referral program configs"
      ON public.referral_program_configs
      FOR ALL
      USING (public.is_tenant_admin(auth.uid(), tenant_id))
      WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_program_configs'
      AND policyname = 'Platform superadmins can manage referral program configs'
  ) THEN
    CREATE POLICY "Platform superadmins can manage referral program configs"
      ON public.referral_program_configs
      FOR ALL
      USING (public.is_platform_superadmin(auth.uid()))
      WITH CHECK (public.is_platform_superadmin(auth.uid()));
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_records'
      AND policyname = 'Tenant admins can read referral records'
  ) THEN
    CREATE POLICY "Tenant admins can read referral records"
      ON public.referral_records
      FOR SELECT
      USING (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_records'
      AND policyname = 'Service role can manage referral records'
  ) THEN
    CREATE POLICY "Service role can manage referral records"
      ON public.referral_records
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_records'
      AND policyname = 'Platform superadmins can read referral records'
  ) THEN
    CREATE POLICY "Platform superadmins can read referral records"
      ON public.referral_records
      FOR SELECT
      USING (public.is_platform_superadmin(auth.uid()));
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_sync_events'
      AND policyname = 'Tenant admins can read referral sync events'
  ) THEN
    CREATE POLICY "Tenant admins can read referral sync events"
      ON public.referral_sync_events
      FOR SELECT
      USING (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_sync_events'
      AND policyname = 'Service role can manage referral sync events'
  ) THEN
    CREATE POLICY "Service role can manage referral sync events"
      ON public.referral_sync_events
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_reward_actions'
      AND policyname = 'Tenant admins can read referral reward actions'
  ) THEN
    CREATE POLICY "Tenant admins can read referral reward actions"
      ON public.referral_reward_actions
      FOR SELECT
      USING (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'referral_reward_actions'
      AND policyname = 'Service role can manage referral reward actions'
  ) THEN
    CREATE POLICY "Service role can manage referral reward actions"
      ON public.referral_reward_actions
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_trigger WHERE tgname = 'update_referral_program_configs_updated_at'
  ) THEN
    CREATE TRIGGER update_referral_program_configs_updated_at
      BEFORE UPDATE ON public.referral_program_configs
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_trigger WHERE tgname = 'update_referral_records_updated_at'
  ) THEN
    CREATE TRIGGER update_referral_records_updated_at
      BEFORE UPDATE ON public.referral_records
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_trigger WHERE tgname = 'update_referral_reward_actions_updated_at'
  ) THEN
    CREATE TRIGGER update_referral_reward_actions_updated_at
      BEFORE UPDATE ON public.referral_reward_actions
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END
$$;
