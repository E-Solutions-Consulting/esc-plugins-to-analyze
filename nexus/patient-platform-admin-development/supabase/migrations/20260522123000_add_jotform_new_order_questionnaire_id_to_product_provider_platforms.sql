-- Idempotent: add the optional Jotform new-order questionnaire mapping to product-provider assignments.
ALTER TABLE public.product_provider_platforms
  ADD COLUMN IF NOT EXISTS jotform_new_order_questionnaire_id TEXT;

COMMENT ON COLUMN public.product_provider_platforms.jotform_new_order_questionnaire_id IS
  'Jotform questionnaire/form ID used for new patient orders on this product and provider platform assignment.';
