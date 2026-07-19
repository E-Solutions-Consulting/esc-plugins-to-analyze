ALTER TABLE public.product_provider_platforms
ADD COLUMN IF NOT EXISTS questionnaire_id TEXT;

UPDATE public.product_provider_platforms
SET questionnaire_id = COALESCE(questionnaire_id, questionnaire_ids[1])
WHERE questionnaire_id IS NULL
  AND questionnaire_ids IS NOT NULL
  AND array_length(questionnaire_ids, 1) > 0;

ALTER TABLE public.product_provider_platforms
DROP COLUMN IF EXISTS questionnaire_ids;
