ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS payment_retry_count INT NOT NULL DEFAULT 0;

COMMENT ON COLUMN public.orders.payment_retry_count IS
  'Running count of manual payment retry attempts made by the patient while in payment_failed status.';
