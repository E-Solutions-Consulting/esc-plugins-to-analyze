
ALTER TABLE public.referral_records
  ADD COLUMN IF NOT EXISTS friend_coupon_code TEXT;

-- Coupon-redemption reconciliation (GetCoupons) looks up the referral row by the
-- friend's coupon code, so index it per tenant.
CREATE INDEX IF NOT EXISTS referral_records_friend_coupon_code_idx
  ON public.referral_records USING btree (tenant_id, friend_coupon_code)
  WHERE friend_coupon_code IS NOT NULL;
