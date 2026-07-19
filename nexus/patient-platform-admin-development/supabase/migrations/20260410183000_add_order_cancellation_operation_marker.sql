ALTER TABLE public.orders
ADD COLUMN IF NOT EXISTS cancellation_operation_key TEXT,
ADD COLUMN IF NOT EXISTS cancellation_operation_started_at TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS cancellation_operation_completed_at TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS idx_orders_cancellation_operation_key
ON public.orders(cancellation_operation_key)
WHERE cancellation_operation_key IS NOT NULL;
