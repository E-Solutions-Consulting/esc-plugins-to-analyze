ALTER TABLE public.referral_records
  ADD COLUMN IF NOT EXISTS coupon_status TEXT,
  ADD COLUMN IF NOT EXISTS validity_status TEXT,
  ADD COLUMN IF NOT EXISTS redemption_count INTEGER
    CHECK (redemption_count IS NULL OR redemption_count >= 0),
  ADD COLUMN IF NOT EXISTS max_redemptions INTEGER
    CHECK (max_redemptions IS NULL OR max_redemptions > 0),
  ADD COLUMN IF NOT EXISTS redeemed_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS reward_pending_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS reward_rejected_at TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS referral_records_coupon_status_idx
  ON public.referral_records (tenant_id, coupon_status);

CREATE INDEX IF NOT EXISTS referral_records_validity_status_idx
  ON public.referral_records (tenant_id, validity_status);

CREATE INDEX IF NOT EXISTS referral_records_reward_status_tracking_idx
  ON public.referral_records (tenant_id, reward_status);
