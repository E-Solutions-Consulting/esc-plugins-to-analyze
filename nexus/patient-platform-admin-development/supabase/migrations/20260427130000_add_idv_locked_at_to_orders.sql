ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS idv_locked_at TIMESTAMPTZ DEFAULT NULL;

COMMENT ON COLUMN public.orders.idv_locked_at IS
  'Set when identity verification is locked out after exceeding the maximum number of failed attempts. NULL = not locked.';
