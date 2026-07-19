#!/usr/bin/env node
/**
 * Emit the compare-at (strike-through) price import as SQL, one file per environment.
 *
 * The pricing sibling of generate-product-pdp-sql.mjs. That script owns product
 * *content* (metadata.pdp) and deliberately never writes price. This one writes the
 * one pricing field that is genuinely missing and genuinely safe to backfill:
 * `compare_at_price_cents` — the struck-through anchor the website shows beside the
 * price ("~~$749~~ $499  Save 33%"). The saving percentage is derived on the website
 * from the two prices; it is not stored, so writing the anchor is all that is needed.
 *
 * ## What it writes, and what it refuses to
 *
 * It sets `compare_at_price_cents` ONLY. It never touches `price_cents`: the platform
 * is the source of truth for what a customer is charged, its values already match the
 * live site exactly (verified), and writing a marketing price back is exactly the drift
 * that once made MICC advertise "was $199" against a real $199 charge — a saving that
 * did not exist. The anchors here are real: each is a WooCommerce `display_regular_price`
 * that is genuinely higher than the charged `display_price`. `product-compare-at-prices.json`
 * omits any product where the two are equal (compounded-nad), so nothing writes a $0 saving.
 *
 * ## Why it is safe against the wrong row / the wrong price
 *
 * Same guards as the content generator, plus one more:
 *   - matched on the product uuid AND the Brello tenant (resolved by slug, per env),
 *     so a stale uuid updates nothing rather than another tenant's row;
 *   - the UPDATE has `AND p.price_cents < <compare_at>` in its WHERE, so it can only
 *     ever set an anchor that is strictly above the current charged price. If a price
 *     has moved such that the anchor is no longer higher, that row is skipped rather
 *     than made to advertise a false or negative saving. This mirrors the app's own
 *     rule (ProductDetail requires compare-at > price).
 *
 * Idempotent: re-running writes the same value; `updated_at` moves only on a real change.
 *
 * Usage:  node scripts/generate-product-pricing-sql.mjs
 *         → writes scripts/sql/product-compare-at-{development,staging,production}.sql
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
const COMPARE_AT = resolve(__dirname, "product-compare-at-prices.json");

/** Duplicate product rows the config map misses, per env — see the content generator
 *  and the alias file's own header. They get the same anchor as their slug. */
const UUID_ALIASES = resolve(__dirname, "product-uuid-aliases.json");

const BRELLO_TENANT_SLUG = "brello";
const ENVS = ["development", "staging", "production"];

/** Same website-slug → platform-uuid map the content generator reads, per env. */
function parseProductsConfig() {
  const src = readFileSync(PRODUCTS_CONFIG, "utf8");
  const body = src.slice(
    src.indexOf("export const PRODUCTS"),
    src.indexOf("/* --------------------------------- Lookups"),
  );
  const products = {};
  for (const [, slug, entry] of body.matchAll(/"([\w-]+)":\s*\{([\s\S]*?)\n  \},/g)) {
    const platform = {};
    const block = entry.match(/platform:\s*\{([\s\S]*?)\}/)?.[1] ?? "";
    for (const [, env, id] of block.matchAll(/(\w+):\s*"([^"]+)"/g)) platform[env] = id;
    products[slug] = { name: entry.match(/name:\s*"([^"]+)"/)?.[1], platform };
  }
  return products;
}

/* ---------------------------------- emit ------------------------------------ */

const config = parseProductsConfig();

// Whole dollars → cents. Skip the comment key and anything non-numeric.
const compareAt = {};
for (const [slug, dollars] of Object.entries(JSON.parse(readFileSync(COMPARE_AT, "utf8")))) {
  if (slug.startsWith("_")) continue;
  if (typeof dollars === "number" && Number.isFinite(dollars)) {
    compareAt[slug] = Math.round(dollars * 100);
  }
}

const aliases = JSON.parse(readFileSync(UUID_ALIASES, "utf8"));

mkdirSync(OUT_DIR, { recursive: true });

for (const env of ENVS) {
  const rows = [];
  const envAliases = aliases[env] ?? {};
  for (const [slug, cents] of Object.entries(compareAt)) {
    const id = config[slug]?.platform[env];
    if (!id) continue; // not sold in this environment
    rows.push({ slug, id, name: config[slug].name, cents });

    // Duplicate product rows the config map misses — same anchor, same slug.
    for (const aliasId of envAliases[slug] ?? []) {
      rows.push({ slug, id: aliasId, name: `${config[slug].name} (duplicate row)`, cents });
    }
  }

  const statements = rows
    .map(
      ({ slug, id, name, cents }) => `-- ${slug} — ${name}  (compare-at $${(cents / 100).toFixed(0)})
UPDATE public.products p
   SET compare_at_price_cents = ${cents},
       updated_at             = now()
  FROM public.tenants t
 WHERE p.tenant_id = t.id
   AND t.slug      = '${BRELLO_TENANT_SLUG}'
   AND p.id        = '${id}'::uuid
   AND p.price_cents < ${cents};`,
    )
    .join("\n\n");

  const sql = `-- Product compare-at (strike-through) prices → products.compare_at_price_cents
-- Environment: ${env}
--
-- Generated by scripts/generate-product-pricing-sql.mjs. Do not hand-edit: regenerate.
-- Source: brellohealth.com WooCommerce product pages (display_regular_price)
--         + products.config.ts (the website-slug → platform-uuid map, per env)
--
-- WHAT THIS WRITES
--   compare_at_price_cents only — the struck-through anchor the website shows beside
--   the price ("~~$749~~ $499  Save 33%"). Configured in Nexus as "Compare-at price".
--   The saving % is derived from the two prices on the website; it is not stored.
--
-- WHAT IT DOES NOT WRITE
--   price_cents. The platform is the source of truth for what a customer is charged,
--   its values already match the live site, and writing a marketing price back is the
--   drift that once advertised a saving that did not exist. Nothing here touches it.
--
-- SAFE TO RE-RUN
--   Idempotent — re-running sets the same value, and updated_at moves only on a real
--   change. Each anchor is a genuine one (WooCommerce regular price strictly above the
--   sale price); products with no real discount (compounded-nad) are not listed.
--
-- SAFE AGAINST THE WRONG ROW / A MOVED PRICE
--   Matched on the product uuid AND the Brello tenant (resolved by slug, since the
--   tenant uuid differs per environment), so a stale uuid updates nothing rather than
--   another tenant's row. The extra "AND p.price_cents < <anchor>" means a row is set
--   only when the anchor is strictly above the current charged price — if a price has
--   moved, that row is skipped rather than made to show a false or negative saving.
--   This mirrors the app's own rule (compare-at must be higher than the price).
--
-- ${rows.length} product${rows.length === 1 ? "" : "s"} in ${env}.

BEGIN;

${statements}

-- Check this BEFORE you COMMIT.
--
--   Every row below should show compare_at ABOVE price, with a sensible saving.
--   A product you expected but do not see here was skipped by the price guard —
--   its charged price is no longer below the intended anchor. Investigate before
--   forcing it.
SELECT p.name,
       p.price_cents / 100.0                                        AS price,
       p.compare_at_price_cents / 100.0                             AS compare_at,
       CASE WHEN p.compare_at_price_cents > p.price_cents
            THEN round(100.0 * (p.compare_at_price_cents - p.price_cents)
                       / p.compare_at_price_cents)
       END                                                          AS saving_pct
  FROM public.products p
  JOIN public.tenants  t ON t.id = p.tenant_id
 WHERE t.slug = '${BRELLO_TENANT_SLUG}'
   AND p.compare_at_price_cents IS NOT NULL
 ORDER BY p.name;

-- Happy? COMMIT. Not happy? ROLLBACK — nothing above has been persisted yet.
COMMIT;
`;

  const path = resolve(OUT_DIR, `product-compare-at-${env}.sql`);
  writeFileSync(path, sql);
  console.log(
    `  ${env.padEnd(12)} ${rows.length} products → scripts/sql/product-compare-at-${env}.sql`,
  );
}
