-- Add renewal timestamp to orders table
ALTER TABLE public.orders
ADD COLUMN IF NOT EXISTS renewal_at timestamptz;
