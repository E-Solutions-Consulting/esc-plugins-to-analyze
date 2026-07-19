ALTER TABLE public.product_provider_platforms
ADD COLUMN IF NOT EXISTS questionnaire_ids TEXT[];
