-- Add name fields and delivery instructions to shipping address
ALTER TABLE public.patients
ADD COLUMN IF NOT EXISTS shipping_first_name text,
ADD COLUMN IF NOT EXISTS shipping_last_name text,
ADD COLUMN IF NOT EXISTS shipping_company text,
ADD COLUMN IF NOT EXISTS shipping_instructions text;

-- Add name fields and delivery instructions to billing address
ALTER TABLE public.patients
ADD COLUMN IF NOT EXISTS billing_first_name text,
ADD COLUMN IF NOT EXISTS billing_last_name text,
ADD COLUMN IF NOT EXISTS billing_company text;