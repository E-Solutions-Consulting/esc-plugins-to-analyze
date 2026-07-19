-- Create enum for payment type
CREATE TYPE payment_type AS ENUM ('one_time', 'subscription');

-- Create enum for subscription interval
CREATE TYPE subscription_interval AS ENUM ('day', 'week', 'month', 'year');

-- Add payment configuration columns to products table
ALTER TABLE public.products
ADD COLUMN payment_type payment_type NOT NULL DEFAULT 'one_time',
ADD COLUMN subscription_interval subscription_interval DEFAULT NULL,
ADD COLUMN subscription_interval_count integer DEFAULT NULL;

-- Add check constraint for subscription fields
ALTER TABLE public.products
ADD CONSTRAINT products_subscription_check 
CHECK (
  (payment_type = 'one_time' AND subscription_interval IS NULL AND subscription_interval_count IS NULL)
  OR
  (payment_type = 'subscription' AND subscription_interval IS NOT NULL AND subscription_interval_count IS NOT NULL AND subscription_interval_count > 0)
);

-- Add comment for documentation
COMMENT ON COLUMN public.products.payment_type IS 'Whether this product is a one-time purchase or subscription';
COMMENT ON COLUMN public.products.subscription_interval IS 'Billing interval for subscriptions (day, week, month, year)';
COMMENT ON COLUMN public.products.subscription_interval_count IS 'Number of intervals between billings (e.g., 2 weeks = interval=week, count=2)';