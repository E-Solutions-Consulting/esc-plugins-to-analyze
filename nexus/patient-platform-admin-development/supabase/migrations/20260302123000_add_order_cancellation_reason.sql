-- Store free-text cancellation reasons provided by patients when cancelling orders.
ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS cancellation_reason TEXT;

COMMENT ON COLUMN public.orders.cancellation_reason IS
  'Free-text reason captured when a patient cancels an order.';
