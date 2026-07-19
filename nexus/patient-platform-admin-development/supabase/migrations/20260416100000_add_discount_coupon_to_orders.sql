-- Restored to preserve remote migration history for Supabase branch deployments.
-- Later migration 20260416120000_add_coupon_fields_to_orders.sql extends this safely.
ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS discount_cents INTEGER DEFAULT 0;
ALTER TABLE public.orders ADD COLUMN IF NOT EXISTS coupon_code TEXT;

CREATE INDEX IF NOT EXISTS idx_orders_coupon_code ON public.orders(coupon_code);
