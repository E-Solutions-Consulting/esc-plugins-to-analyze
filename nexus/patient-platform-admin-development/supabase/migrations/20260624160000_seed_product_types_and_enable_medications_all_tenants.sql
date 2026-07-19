-- Make the product-types catalog work for every tenant (idempotent re-seed).
--
-- The original migration (20260624140000) created product_types /
-- tenant_product_types and backfilled Medications for tenants that existed at that
-- moment. This migration re-asserts the seed and (re)enables the Medications line
-- for ALL tenants, so:
--   * any tenant created after the original migration (e.g. brello) gets Medications,
--   * any environment where the original backfill didn't fully apply is corrected.
-- Safe to run repeatedly.

-- 1) Ensure the five product types exist with the intended availability.
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

-- 2) Enable the Medications line for EVERY tenant (current behavior: all tenants
--    have Medications). Existing rows are forced back to enabled so a tenant that
--    was never seeded — or was left disabled by the partial backfill — is fixed.
INSERT INTO public.tenant_product_types (tenant_id, product_type_id, is_enabled)
SELECT t.id, pt.id, TRUE
FROM public.tenants t
CROSS JOIN public.product_types pt
WHERE pt.key = 'medications'
ON CONFLICT (tenant_id, product_type_id) DO UPDATE
  SET is_enabled = TRUE,
      updated_at = now();
