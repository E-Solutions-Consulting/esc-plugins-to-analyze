-- Add expanded shipping address fields to orders table
-- These fields match the patient's shipping address structure

ALTER TABLE public.orders
ADD COLUMN IF NOT EXISTS shipping_first_name text,
ADD COLUMN IF NOT EXISTS shipping_last_name text,
ADD COLUMN IF NOT EXISTS shipping_company text,
ADD COLUMN IF NOT EXISTS shipping_instructions text;