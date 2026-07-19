-- Add billing address fields to orders table
ALTER TABLE public.orders
ADD COLUMN IF NOT EXISTS billing_first_name text,
ADD COLUMN IF NOT EXISTS billing_last_name text,
ADD COLUMN IF NOT EXISTS billing_company text,
ADD COLUMN IF NOT EXISTS billing_address_line1 text,
ADD COLUMN IF NOT EXISTS billing_address_line2 text,
ADD COLUMN IF NOT EXISTS billing_city text,
ADD COLUMN IF NOT EXISTS billing_state text,
ADD COLUMN IF NOT EXISTS billing_postal_code text,
ADD COLUMN IF NOT EXISTS billing_country text;
