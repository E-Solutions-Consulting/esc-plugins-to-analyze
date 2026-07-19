-- Add logo_has_wordmark to tenant_branding.
--
-- Some tenants' logos are wordmarks (the brand name is part of the image, e.g.
-- Brello's "brello" logo). Rendering the tenant name next to such a logo shows
-- the brand name twice. Icon-only logos still need the name rendered beside them,
-- so this cannot be a global UI decision — it is a property of the logo asset.
--
-- The patient UI reads this via the tenant-info edge function and hides the
-- adjacent tenant name when true.

ALTER TABLE public.tenant_branding
  ADD COLUMN IF NOT EXISTS logo_has_wordmark BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN public.tenant_branding.logo_has_wordmark IS
  'True when logo_url already contains the brand name (a wordmark). The patient UI then hides the tenant name next to the logo to avoid showing it twice.';
