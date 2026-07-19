#!/usr/bin/env node
/**
 * Emit SQL to RETIRE the known duplicate product rows, one file per environment.
 *
 * `product-uuid-aliases.json` lists the extra product rows that are the SAME product
 * as a mapped website slug — a second enabled row with the same sku and price that the
 * config map does not point at (e.g. an older "Compounded Tirzepatide" beside the
 * newer one). The content and compare-at generators write to BOTH rows so the
 * duplicate does not render an empty page; this generator does the opposite — it
 * removes the duplicate from the storefront so only the canonical row is sold.
 *
 * ## Disable, not delete
 *
 * It sets `is_enabled = false` on each alias row — it does NOT DELETE. A product row is
 * referenced by orders, subscriptions, faqs, medications, categories and terms
 * acceptances; a DELETE would fail on those foreign keys or, worse, orphan a real
 * customer order. Disabling drops the row out of the catalogue immediately (the
 * patient-api product queries filter on is_enabled = true), harms nothing, and is
 * trivially reversible (set it back to true). If a row is later confirmed to have zero
 * dependents, a DELETE can follow — but that is a separate, deliberate step, not this.
 *
 * ## Look before you leap
 *
 * Every file leads with a diagnostic SELECT: for each alias uuid it shows the row's
 * name/sku/price/is_enabled and its order + subscription counts. READ THAT FIRST. If a
 * "duplicate" has real orders against it, stop and investigate — disabling is safe
 * (existing orders keep working; it only hides the row from new purchases), but a
 * high count may mean the "duplicate" is the row customers actually bought from.
 *
 * ## Safe against the wrong row
 *
 * Matched on the alias uuid AND the Brello tenant (resolved by slug, per env), AND
 * guarded so it only touches a row whose sku matches the canonical mapped product's
 * sku — a stale/mistyped uuid, or a uuid that turns out NOT to share the sku, updates
 * nothing rather than disabling something real. Idempotent: re-running is a no-op once
 * the row is already disabled.
 *
 * Usage:  node scripts/generate-product-duplicate-disable-sql.mjs
 *         → writes scripts/sql/product-duplicate-disable-{development,staging,production}.sql
 */

import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = resolve(__dirname, "sql");

const JOURNEY_REPO =
  process.env.JOURNEY_REPO ??
  resolve(__dirname, "../../brellohealth-purchase-journey");

const PRODUCTS_CONFIG = resolve(JOURNEY_REPO, "src/config/products.config.ts");
const UUID_ALIASES = resolve(__dirname, "product-uuid-aliases.json");

const BRELLO_TENANT_SLUG = "brello";
const ENVS = ["development", "staging", "production"];

/** Same website-slug → platform-uuid map the sibling generators read, per env. */
function parseProductsConfig() {
  const src = readFileSync(PRODUCTS_CONFIG, "utf8");
  const body = src.slice(
    src.indexOf("export const PRODUCTS"),
    src.indexOf("/* --------------------------------- Lookups"),
  );
  const products = {};
  for (const [, slug, entry] of body.matchAll(
    /"([\w-]+)":\s*\{([\s\S]*?)\n  \},/g,
  )) {
    const platform = {};
    const block = entry.match(/platform:\s*\{([\s\S]*?)\}/)?.[1] ?? "";
    for (const [, env, id] of block.matchAll(/(\w+):\s*"([^"]+)"/g))
      platform[env] = id;
    products[slug] = {
      name: entry.match(/name:\s*"([^"]+)"/)?.[1],
      platform,
    };
  }
  return products;
}

/* ---------------------------------- emit ------------------------------------ */

const config = parseProductsConfig();
const aliases = JSON.parse(readFileSync(UUID_ALIASES, "utf8"));

mkdirSync(OUT_DIR, { recursive: true });

for (const env of ENVS) {
  const envAliases = aliases[env] ?? {};
  const rows = [];
  for (const [slug, ids] of Object.entries(envAliases)) {
    const canonicalId = config[slug]?.platform[env];
    if (!canonicalId) continue;
    for (const aliasId of ids) {
      rows.push({ slug, name: config[slug].name, canonicalId, aliasId });
    }
  }

  const aliasIdList = rows.map((r) => `'${r.aliasId}'::uuid`).join(",\n           ");

  const statements = rows
    .map(
      ({ slug, name, canonicalId, aliasId }) => `-- ${slug} — ${name} (duplicate row)
--   Disable the alias row only when it shares the canonical row's sku (so a
--   mistyped uuid, or one that is NOT actually this product, touches nothing).
UPDATE public.products dup
   SET is_enabled = false,
       updated_at = now()
  FROM public.tenants t,
       public.products canon
 WHERE dup.tenant_id = t.id
   AND t.slug        = '${BRELLO_TENANT_SLUG}'
   AND dup.id        = '${aliasId}'::uuid
   AND canon.id      = '${canonicalId}'::uuid
   AND canon.tenant_id = dup.tenant_id
   AND dup.sku       = canon.sku
   AND dup.is_enabled IS DISTINCT FROM false;`,
    )
    .join("\n\n");

  const sql = `-- Retire duplicate product rows → products.is_enabled = false
-- Environment: ${env}
--
-- Generated by scripts/generate-product-duplicate-disable-sql.mjs. Do not hand-edit: regenerate.
-- Source: product-uuid-aliases.json (the known duplicate rows per env)
--         + products.config.ts (the website-slug → platform-uuid map, per env)
--
-- WHAT THIS DOES
--   Sets is_enabled = false on the known DUPLICATE product rows — a second enabled
--   row that is the same product (same sku + price) as the canonical one the config
--   points at, and which otherwise renders a second card / an empty product page.
--   The patient-api product queries filter on is_enabled = true, so a disabled row
--   drops out of the storefront immediately.
--
-- WHY DISABLE, NOT DELETE
--   A product row is referenced by orders, subscriptions, faqs, medications,
--   categories and terms acceptances. A DELETE would fail on those foreign keys or
--   orphan a real customer order. Disabling is safe (existing orders keep working;
--   it only hides the row from NEW purchases) and is trivially reversible — set
--   is_enabled back to true. A DELETE, if ever wanted, is a separate deliberate step
--   taken only after confirming zero dependents (see the diagnostic below).
--
-- LOOK BEFORE YOU LEAP
--   The diagnostic SELECT below shows, for each alias row, its name/sku/price/
--   is_enabled and its order + subscription counts. READ IT FIRST. Disabling a row
--   with existing orders is still safe, but a high order count may mean the
--   "duplicate" is the row customers actually purchased from — investigate before
--   COMMIT.
--
-- SAFE AGAINST THE WRONG ROW
--   Each UPDATE is matched on the alias uuid AND the Brello tenant AND requires the
--   alias row's sku to equal the canonical mapped product's sku, so a stale/mistyped
--   uuid disables nothing. Idempotent: a no-op once the row is already disabled.
--
-- ${rows.length} duplicate row${rows.length === 1 ? "" : "s"} in ${env}.

BEGIN;

-- Diagnostic — run this and READ IT before the UPDATEs below take effect.
--   orders / subscriptions: how many rows reference this product. 0 / 0 is the
--   expected shape for a genuine unused duplicate.
SELECT p.id,
       p.name,
       p.sku,
       p.price_cents / 100.0                                        AS price,
       p.is_enabled,
       (SELECT count(*) FROM public.orders o WHERE o.product_id = p.id)        AS orders,
       (SELECT count(*) FROM public.subscriptions s WHERE s.product_id = p.id) AS subscriptions
  FROM public.products p
  JOIN public.tenants  t ON t.id = p.tenant_id
 WHERE t.slug = '${BRELLO_TENANT_SLUG}'
   AND p.id IN (
           ${aliasIdList}
       )
 ORDER BY p.name;

${statements}

-- Confirm the result: every alias row above should now read is_enabled = false,
-- and the canonical row for each slug should remain is_enabled = true.
SELECT p.name, p.sku, p.is_enabled,
       p.id IN (${aliasIdList}) AS is_duplicate_row
  FROM public.products p
  JOIN public.tenants  t ON t.id = p.tenant_id
 WHERE t.slug = '${BRELLO_TENANT_SLUG}'
   AND p.sku IN (
     SELECT sku FROM public.products WHERE id IN (${aliasIdList})
   )
 ORDER BY p.sku, is_duplicate_row;

-- Happy? COMMIT. Not happy? ROLLBACK — nothing above has been persisted yet.
COMMIT;
`;

  const path = resolve(OUT_DIR, `product-duplicate-disable-${env}.sql`);
  writeFileSync(path, sql);
  console.log(
    `  ${env.padEnd(12)} ${rows.length} duplicate rows → scripts/sql/product-duplicate-disable-${env}.sql`,
  );
}
