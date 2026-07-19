-- Add tenant-managed "What's Included" bullet list per product.
-- Stored as a JSONB array of strings (e.g. ["Provider consultation & medical review",
-- "Free cold-chain shipping"]). Per-product and per-tenant: products are already tenant-scoped,
-- so each tenant curates its own bullets for each of its products. Rendered in the patient
-- checkout order summary under "WHAT'S INCLUDED".
ALTER TABLE public.products
ADD COLUMN IF NOT EXISTS included_features JSONB NOT NULL DEFAULT '[]'::jsonb;

COMMENT ON COLUMN public.products.included_features IS
  'Tenant-authored "What''s Included" bullets shown in checkout. JSONB array of strings.';
