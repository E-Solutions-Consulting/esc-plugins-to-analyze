-- Remove deprecated readiness flags from products
ALTER TABLE public.products
  DROP COLUMN IF EXISTS pharmacy_ready,
  DROP COLUMN IF EXISTS provider_ready,
  DROP COLUMN IF EXISTS legal_ready;
