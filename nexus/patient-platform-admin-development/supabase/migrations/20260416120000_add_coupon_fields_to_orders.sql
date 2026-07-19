-- Add coupon/discount fields to orders table
-- These are populated at order creation time from Stripe checkout session or invoice data.
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS discount_cents INTEGER NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS coupon_code TEXT,
  ADD COLUMN IF NOT EXISTS coupon_name TEXT;
