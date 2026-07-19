-- Add provider_customer_id column to order_payment_provider_transactions
-- Stores the payment provider's customer identifier (e.g. Stripe customer ID)
-- for cross-referencing during webhook validation.

ALTER TABLE public.order_payment_provider_transactions
  ADD COLUMN IF NOT EXISTS provider_customer_id TEXT;

CREATE INDEX IF NOT EXISTS idx_order_payment_transactions_provider_customer_id
  ON public.order_payment_provider_transactions(payment_provider_id, provider_customer_id)
  WHERE provider_customer_id IS NOT NULL;
