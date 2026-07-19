-- Add Stripe subscription id to orders
ALTER TABLE public.orders
ADD COLUMN stripe_subscription_id TEXT;

CREATE INDEX idx_orders_stripe_subscription_id
ON public.orders (stripe_subscription_id)
WHERE stripe_subscription_id IS NOT NULL;
