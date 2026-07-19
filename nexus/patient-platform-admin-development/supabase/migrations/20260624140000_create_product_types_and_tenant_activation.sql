-- Product TYPES (a.k.a. product lines) and per-tenant activation.
--
-- Two levels in the catalog:
--   * product_types          -- top-level lines: Medications, Labs, Fitness,
--                               Wearables, Experiences. Global definitions with
--                               an availability flag (available | coming_soon).
--   * product_categories     -- existing per-type sub-tags (Weight Loss, Energy,
--                               Longevity, ...). Now linked to a product_type via
--                               product_type_id (nullable; existing rows backfill
--                               to Medications).
--
-- Per-tenant activation lives in tenant_product_types so each tenant enables only
-- the lines it offers (Medications enabled by default; others opt-in as they ship).
--
-- Governance mirrors product_categories: global type DEFINITIONS are superadmin-
-- managed and readable by any authed user; per-tenant ENABLEMENT is managed by the
-- tenant's own admins.

-- 1) product_types -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.product_types (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key           TEXT NOT NULL UNIQUE,
  name          TEXT NOT NULL,
  description   TEXT,
  -- 'available'   -> tenants can enable it and manage real products
  -- 'coming_soon' -> shown but not yet usable (e.g. Labs today)
  availability  TEXT NOT NULL DEFAULT 'coming_soon'
                  CHECK (availability IN ('available', 'coming_soon')),
  display_order INTEGER NOT NULL DEFAULT 0,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_product_types_display_order
  ON public.product_types (display_order);

ALTER TABLE public.product_types ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Authenticated users can view product types"
  ON public.product_types;
CREATE POLICY "Authenticated users can view product types"
  ON public.product_types
  FOR SELECT
  USING (auth.uid() IS NOT NULL);

DROP POLICY IF EXISTS "Superadmin can manage product types"
  ON public.product_types;
CREATE POLICY "Superadmin can manage product types"
  ON public.product_types
  FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

DROP TRIGGER IF EXISTS update_product_types_updated_at ON public.product_types;
CREATE TRIGGER update_product_types_updated_at
  BEFORE UPDATE ON public.product_types
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- 2) Seed the five lines (idempotent) ---------------------------------------
INSERT INTO public.product_types (key, name, description, availability, display_order)
VALUES
  ('medications', 'Medications', 'Prescription products fulfilled via clinical providers and pharmacies.', 'available',   0),
  ('labs',        'Labs',        'Lab tests and diagnostics.',                                                'coming_soon', 1),
  ('fitness',     'Fitness',     'Coaching and fitness programs.',                                            'coming_soon', 2),
  ('wearables',   'Wearables',   'Connected devices and wearables.',                                          'coming_soon', 3),
  ('experiences', 'Experiences', 'Bookable experiences and services.',                                        'coming_soon', 4)
ON CONFLICT (key) DO UPDATE
  SET name          = EXCLUDED.name,
      description    = EXCLUDED.description,
      availability   = EXCLUDED.availability,
      display_order  = EXCLUDED.display_order,
      updated_at     = now();

-- 3) Link existing product_categories (sub-tags) to a product type ----------
ALTER TABLE public.product_categories
  ADD COLUMN IF NOT EXISTS product_type_id UUID
    REFERENCES public.product_types(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_product_categories_product_type
  ON public.product_categories (product_type_id);

-- Existing categories (Weight Loss, Energy, Longevity, tracker tags, ...) are
-- medication sub-tags today -> backfill them under the Medications line.
UPDATE public.product_categories
SET product_type_id = (
  SELECT id FROM public.product_types WHERE key = 'medications'
)
WHERE product_type_id IS NULL;

-- 4) Per-tenant activation --------------------------------------------------
CREATE TABLE IF NOT EXISTS public.tenant_product_types (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  product_type_id UUID NOT NULL REFERENCES public.product_types(id) ON DELETE CASCADE,
  is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, product_type_id)
);

CREATE INDEX IF NOT EXISTS idx_tenant_product_types_tenant
  ON public.tenant_product_types (tenant_id, is_enabled);

ALTER TABLE public.tenant_product_types ENABLE ROW LEVEL SECURITY;

-- Read: any authed member of the tenant (admins manage; the patient app/back end
-- read via service role). Keep read aligned with other tenant-scoped tables.
DROP POLICY IF EXISTS tenant_product_types_tenant_admin
  ON public.tenant_product_types;
CREATE POLICY tenant_product_types_tenant_admin
  ON public.tenant_product_types
  FOR ALL
  USING (public.is_tenant_admin(auth.uid(), tenant_id))
  WITH CHECK (public.is_tenant_admin(auth.uid(), tenant_id));

DROP TRIGGER IF EXISTS update_tenant_product_types_updated_at
  ON public.tenant_product_types;
CREATE TRIGGER update_tenant_product_types_updated_at
  BEFORE UPDATE ON public.tenant_product_types
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- Enable the Medications line for every existing tenant by default so today's
-- behavior (everyone sees Medications) is preserved.
INSERT INTO public.tenant_product_types (tenant_id, product_type_id, is_enabled)
SELECT t.id, pt.id, TRUE
FROM public.tenants t
CROSS JOIN public.product_types pt
WHERE pt.key = 'medications'
ON CONFLICT (tenant_id, product_type_id) DO NOTHING;
