ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS payment_failed_at TIMESTAMPTZ;

COMMENT ON COLUMN public.orders.payment_failed_at IS
  'Timestamp when the order most recently entered payment_failed status. Used to enforce the 7-day expiry window.';
