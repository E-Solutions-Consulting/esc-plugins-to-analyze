// Per-product guardrail for how early a renewal may be moved/triggered.
//
// A product's `renewal_advance_max_weeks` bounds how many weeks before the end
// of a billing cycle an admin may trigger (or a patient/admin may move) a
// renewal. It defaults to 2 weeks, which reproduces the buffer that used to be
// hardcoded as 14 days.

export const DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS = 2;

type ProductLike = { renewal_advance_max_weeks?: number | null };

/**
 * Resolve a product's configured renewal-advance guardrail in weeks, falling
 * back to the default when unset/invalid. Accepts a single product, an array
 * (as returned by nested PostgREST selects), or null.
 */
export function getRenewalAdvanceMaxWeeks(
  product: ProductLike | ProductLike[] | null | undefined,
): number {
  const single = Array.isArray(product) ? product[0] ?? null : product ?? null;
  const value = single?.renewal_advance_max_weeks;
  return typeof value === "number" && value >= 0
    ? value
    : DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS;
}

/** Guardrail expressed in days (weeks * 7). */
export function getRenewalAdvanceMaxDays(
  product: ProductLike | ProductLike[] | null | undefined,
): number {
  return getRenewalAdvanceMaxWeeks(product) * 7;
}
