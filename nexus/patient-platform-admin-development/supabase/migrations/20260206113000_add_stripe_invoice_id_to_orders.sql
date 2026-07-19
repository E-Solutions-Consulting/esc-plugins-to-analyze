-- Add Stripe invoice id to orders
ALTER TABLE public.orders
ADD COLUMN IF NOT EXISTS stripe_invoice_id text;

CREATE INDEX IF NOT EXISTS idx_orders_stripe_invoice_id
ON public.orders (stripe_invoice_id)
WHERE stripe_invoice_id IS NOT NULL;

-- Backfill from internal_notes where possible
UPDATE public.orders
SET stripe_invoice_id = substring(internal_notes from 'Invoice: ([^,\s]+)')
WHERE stripe_invoice_id IS NULL
  AND internal_notes ILIKE '%Invoice:%';

COMMENT ON COLUMN public.orders.stripe_invoice_id IS 'Stripe invoice id associated with this order (for idempotency).';
