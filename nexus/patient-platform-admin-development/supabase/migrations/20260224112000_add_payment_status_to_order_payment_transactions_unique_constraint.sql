-- Allow multiple payment transaction snapshots per order/provider by status.
-- This supports recording an explicit cancellation transaction row.

ALTER TABLE public.order_payment_provider_transactions
  DROP CONSTRAINT IF EXISTS order_payment_provider_transactions_order_id_payment_provider_id_key;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'order_payment_provider_transactions_order_provider_status_key'
      AND conrelid = 'public.order_payment_provider_transactions'::regclass
  ) THEN
    ALTER TABLE public.order_payment_provider_transactions
      ADD CONSTRAINT order_payment_provider_transactions_order_provider_status_key
      UNIQUE (order_id, payment_provider_id, payment_status);
  END IF;
END $$;
