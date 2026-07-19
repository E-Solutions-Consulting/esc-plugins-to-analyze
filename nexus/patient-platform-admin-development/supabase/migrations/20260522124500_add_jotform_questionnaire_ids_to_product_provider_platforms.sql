-- Idempotent: add Jotform questionnaire mappings per product-provider assignment.
ALTER TABLE public.product_provider_platforms
  ADD COLUMN IF NOT EXISTS jotform_new_order_questionnaire_id TEXT,
  ADD COLUMN IF NOT EXISTS jotform_renewall_questionnaire_id TEXT;

-- Preserve values saved before the final naming was introduced.
-- Each backfill only writes empty target columns, so rerunning this migration
-- does not overwrite questionnaire IDs already saved by admins.
DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'product_provider_platforms'
      AND column_name = 'jotform_questionnaire_id'
  ) THEN
    EXECUTE '
      UPDATE public.product_provider_platforms
      SET jotform_renewall_questionnaire_id = jotform_questionnaire_id
      WHERE jotform_renewall_questionnaire_id IS NULL
        AND jotform_questionnaire_id IS NOT NULL
    ';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'product_provider_platforms'
      AND column_name = 'jotform_renewal_questionnaire_id'
  ) THEN
    EXECUTE '
      UPDATE public.product_provider_platforms
      SET jotform_renewall_questionnaire_id = jotform_renewal_questionnaire_id
      WHERE jotform_renewall_questionnaire_id IS NULL
        AND jotform_renewal_questionnaire_id IS NOT NULL
    ';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'product_provider_platforms'
      AND column_name = 'jotform_first_time_questionnaire_id'
  ) THEN
    EXECUTE '
      UPDATE public.product_provider_platforms
      SET jotform_new_order_questionnaire_id = jotform_first_time_questionnaire_id
      WHERE jotform_new_order_questionnaire_id IS NULL
        AND jotform_first_time_questionnaire_id IS NOT NULL
    ';
  END IF;
END $$;

COMMENT ON COLUMN public.product_provider_platforms.jotform_new_order_questionnaire_id IS
  'Jotform questionnaire/form ID used for new patient orders on this product and provider platform assignment.';

COMMENT ON COLUMN public.product_provider_platforms.jotform_renewall_questionnaire_id IS
  'Jotform questionnaire/form ID used for renewal orders on this product and provider platform assignment.';
