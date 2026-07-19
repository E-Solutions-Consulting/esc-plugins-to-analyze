-- Add Stripe checkout session id to orders
ALTER TABLE public.orders
ADD COLUMN stripe_checkout_session_id TEXT;

CREATE INDEX idx_orders_stripe_checkout_session_id
ON public.orders (stripe_checkout_session_id)
WHERE stripe_checkout_session_id IS NOT NULL;
