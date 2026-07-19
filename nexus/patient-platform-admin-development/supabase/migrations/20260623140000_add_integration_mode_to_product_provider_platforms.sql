-- Questionnaire integration mode per product-provider assignment.
--
-- The medical (and patient) questionnaire for a given product + provider can be
-- collected one of two ways:
--   * 'direct'  - the provider's native questionnaire path (what works today,
--                 e.g. Telegra). No Jotform form IDs required.
--   * 'jotform' - the standardized Jotform path; the new-order / renewal Jotform
--                 form IDs on this row are used.
--
-- Default is 'direct' to match current behaviour (no forced migration to Jotform
-- before that path is ready). Admins flip a product to 'jotform' once ready.

ALTER TABLE public.product_provider_platforms
  ADD COLUMN IF NOT EXISTS integration_mode TEXT NOT NULL DEFAULT 'direct'
    CHECK (integration_mode IN ('direct', 'jotform'));

COMMENT ON COLUMN public.product_provider_platforms.integration_mode IS
  'How the questionnaire is collected for this product+provider: direct (provider-native, default) or jotform (uses the jotform_*_questionnaire_id fields).';
