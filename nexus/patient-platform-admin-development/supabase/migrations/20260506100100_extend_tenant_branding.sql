-- Extend tenant_branding with additional brand asset columns:
--   rise_logo_url  — RISE fitness program logo (replaces hardcoded src/assets/svg/riseLogo.svg)
--   aria_logo_url  — Aria AI companion logo (replaces hardcoded src/assets/svg/aria.svg)
--   favicon_url    — Browser tab favicon (replaces hardcoded public/favicon*.png)
--   font_family    — Primary brand font (e.g. 'Inter, sans-serif')
--   support_email  — Tenant support email address
--   terms_url      — Terms of service page URL
--   privacy_url    — Privacy policy page URL
--   hipaa_url      — HIPAA notice page URL

ALTER TABLE public.tenant_branding
  ADD COLUMN IF NOT EXISTS rise_logo_url   TEXT,
  ADD COLUMN IF NOT EXISTS aria_logo_url   TEXT,
  ADD COLUMN IF NOT EXISTS favicon_url     TEXT,
  ADD COLUMN IF NOT EXISTS font_family     TEXT,
  ADD COLUMN IF NOT EXISTS support_email   TEXT,
  ADD COLUMN IF NOT EXISTS terms_url       TEXT,
  ADD COLUMN IF NOT EXISTS privacy_url     TEXT,
  ADD COLUMN IF NOT EXISTS hipaa_url       TEXT;
