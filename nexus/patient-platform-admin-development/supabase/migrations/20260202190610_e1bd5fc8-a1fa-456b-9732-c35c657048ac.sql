-- Add Stripe payment metadata fields to orders table
ALTER TABLE public.orders
ADD COLUMN stripe_payment_intent_id text,
ADD COLUMN stripe_payment_status text,
ADD COLUMN stripe_charge_id text,
ADD COLUMN paid_at timestamp with time zone;

-- Add index for payment intent lookups
CREATE INDEX idx_orders_stripe_payment_intent ON public.orders(stripe_payment_intent_id) WHERE stripe_payment_intent_id IS NOT NULL;