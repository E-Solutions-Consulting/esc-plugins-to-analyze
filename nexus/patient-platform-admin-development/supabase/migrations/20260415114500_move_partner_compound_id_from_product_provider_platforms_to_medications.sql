ALTER TABLE public.medications
ADD COLUMN IF NOT EXISTS partner_compound_id TEXT;

ALTER TABLE public.product_provider_platforms
DROP COLUMN IF EXISTS partner_compound_id;
