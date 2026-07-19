#!/usr/bin/env node
/**
 * Emit the `metadata.pdp` import as SQL, one file per environment.
 *
 * The companion to `import-product-pdp.mjs`, for when you would rather paste SQL
 * into the Supabase SQL editor than hand a service-role key to a script. Same
 * content, same rules, same result — this one just needs no credentials to
 * *generate*, only the ability to run SQL against the environment.
 *
 * ## Why it is safe to re-run
 *
 * The statements are idempotent and they **merge**:
 *
 *     SET metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object('pdp', …)
 *
 * `||` is a shallow merge on jsonb, so it replaces the `pdp` key and leaves every
 * other key in the blob untouched. `metadata` is shared — other producers keep
 * their own keys in it — and a bare `SET metadata = '{…}'` would silently drop
 * theirs. `COALESCE` handles the rows where `metadata` is still null.
 *
 * ## Why it matches on id AND tenant
 *
 * Belt and braces. The uuid alone is unique, but every environment's `products`
 * table also holds *other tenants'* rows — production carries CareLink-owned
 * `compounded-*` products that are not Brello's. The tenant predicate means a
 * stale or mistyped uuid updates nothing rather than something else's row.
 *
 * The tenant is resolved by *slug*, inside the SQL, because Brello's tenant uuid
 * is different in every environment.
 *
 * Usage:  node scripts/generate-product-pdp-sql.mjs
 *         → writes scripts/sql/product-pdp-{development,staging,production}.sql
 */

import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = resolve(__dirname, "sql");

const JOURNEY_REPO =
  process.env.JOURNEY_REPO ??
  resolve(__dirname, "../../brellohealth-purchase-journey");

const CONTENT = resolve(JOURNEY_REPO, "start-wellness.json");
const PRODUCTS_CONFIG = resolve(JOURNEY_REPO, "src/config/products.config.ts");

/**
 * Galleries scraped from brellohealth.com's WooCommerce sitemap, keyed by website
 * slug. `start-wellness.json` only carried images for compounded-tirzepatide, so
 * every other product imported with an empty gallery — this fills that gap. See
 * the file's own header for provenance and how to refresh it.
 */
const GALLERIES = resolve(__dirname, "product-galleries.json");

/**
 * Extra product uuids that are the same product as a website slug, per environment.
 * Each environment's products table carries duplicate rows for some products — a
 * second enabled row with the same sku/price that the config map does not point at.
 * Left alone they render an empty page; listing them here gives them the same
 * content. See the file's own header for how it was built and how to refresh it.
 */
const UUID_ALIASES = resolve(__dirname, "product-uuid-aliases.json");

const BRELLO_TENANT_SLUG = "brello";
const ENVS = ["development", "staging", "production"];

/* -------------------------- shared with the importer ------------------------ */

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

/**
 * Products keyed by website slug, with variants inheriting their parent page's
 * content. The website has one *page* per bundle with a variant selector; the
 * platform sells that bundle as separate *products*. The copy was written for the
 * page, the uuids belong to the products — so the variants inherit, exactly as the
 * live site does, where the selector switches price and label but never editorial.
 */
function readJourneyProducts() {
  const data = JSON.parse(readFileSync(CONTENT, "utf8"));
  const bySlug = {};
  for (const group of ["glp1Plans", "longevityMeds", "protocols"]) {
    for (const parent of data[group] ?? []) {
      bySlug[parent.slug] = parent;
      for (const variant of parent.variants ?? []) {
        if (!variant.slug) continue;
        const { variants: _drop, ...content } = parent;
        bySlug[variant.slug] = {
          ...content,
          slug: variant.slug,
          title: variant.label ?? parent.title,
          inheritedFrom: parent.slug,
        };
      }
    }
  }
  return bySlug;
}

function prune(value) {
  if (Array.isArray(value)) {
    const out = value.map(prune).filter((v) => v !== undefined);
    return out.length ? out : undefined;
  }
  if (value && typeof value === "object") {
    const out = {};
    for (const [k, v] of Object.entries(value)) {
      const p = prune(v);
      if (p !== undefined) out[k] = p;
    }
    return Object.keys(out).length ? out : undefined;
  }
  if (typeof value === "string" && value.trim() === "") return undefined;
  return value ?? undefined;
}

/**
 * Content only. `price`, `href` and `variants` are dropped deliberately: Nexus is
 * already the pricing source of truth, and the marketing JSON's prices had drifted
 * from it (MICC once advertised "was $199" against a real $199 charge).
 */
function toPdp(p) {
  return prune({
    badge: p.badge,
    subBadge: p.subBadge,
    shortDesc: p.shortDesc,
    description: p.description,
    images: p.images,
    about: p.about,
    includes: p.includes,
    steps: p.steps,
  });
}

/** Single-quote escaping for a SQL string literal. */
const sqlString = (s) => `'${s.replace(/'/g, "''")}'`;

/* ---------------------------------- emit ------------------------------------ */

const config = parseProductsConfig();
const journey = readJourneyProducts();

/**
 * Scraped galleries win over start-wellness.json's `images`. The JSON had images
 * only for compounded-tirzepatide; the scrape has them for every product (bundles
 * inherit their primary medication's gallery, keyed by the variant's own slug).
 * Every other pdp field — about, benefits, citations, steps — is left exactly as
 * authored in the JSON. Non-array/empty entries are ignored, so a missing slug or
 * a stray comment key never blanks an existing gallery.
 */
const galleries = JSON.parse(readFileSync(GALLERIES, "utf8"));
for (const [slug, product] of Object.entries(journey)) {
  const images = galleries[slug];
  if (Array.isArray(images) && images.length) product.images = images;
}

/**
 * The path-to-care steps ("A Straightforward Path to Care") are identical on every
 * Brello product — it is the company's standard process, not product-specific copy.
 * start-wellness.json only spelled it out on two products, so the rest imported with
 * no steps. This is the exact block those two carry, verbatim; applied only where a
 * product has none of its own, so anything hand-written elsewhere still wins.
 */
const CANONICAL_STEPS = [
  { n: "1", title: "Submit Your Application", body: "Complete an online intake form." },
  {
    n: "2",
    title: "Provider Review",
    body: "A healthcare provider will review your information to determine if you qualify for prescription medication.",
  },
  {
    n: "3",
    title: "Direct-To-Door Delivery",
    body: "If approved, your medication will be shipped to your doorstep by our partner pharmacy.",
  },
  {
    n: "4",
    title: "Ongoing Support",
    body: "We care about your journey. You can always reach out if you need guidance or support.",
  },
];
for (const product of Object.values(journey)) {
  if (!Array.isArray(product.steps) || product.steps.length === 0) {
    product.steps = CANONICAL_STEPS;
  }
}

const aliases = JSON.parse(readFileSync(UUID_ALIASES, "utf8"));

mkdirSync(OUT_DIR, { recursive: true });

for (const env of ENVS) {
  const rows = [];
  const envAliases = aliases[env] ?? {};

  for (const [slug, entry] of Object.entries(config)) {
    const id = entry.platform[env];
    const content = journey[slug];
    if (!id || !content) continue;

    const pdp = toPdp(content);
    if (!pdp) continue;

    rows.push({ slug, id, name: entry.name, pdp, inheritedFrom: content.inheritedFrom });

    // Duplicate product rows the config map misses — same content, same slug.
    for (const aliasId of envAliases[slug] ?? []) {
      rows.push({
        slug,
        id: aliasId,
        name: `${entry.name} (duplicate row)`,
        pdp,
        inheritedFrom: content.inheritedFrom,
      });
    }
  }

  const statements = rows
    .map(({ slug, id, name, pdp, inheritedFrom }) => {
      const via = inheritedFrom ? `\n--   content inherited from the ${inheritedFrom} page` : "";
      return `-- ${slug} — ${name}${via}
UPDATE public.products p
   SET metadata   = COALESCE(p.metadata, '{}'::jsonb) || jsonb_build_object('pdp', ${sqlString(
     JSON.stringify(pdp),
   )}::jsonb),
       updated_at = now()
  FROM public.tenants t
 WHERE p.tenant_id = t.id
   AND t.slug      = '${BRELLO_TENANT_SLUG}'
   AND p.id        = '${id}'::uuid;`;
    })
    .join("\n\n");

  const sql = `-- Product page content → products.metadata.pdp
-- Environment: ${env}
--
-- Generated by scripts/generate-product-pdp-sql.mjs. Do not hand-edit: regenerate.
-- Source: brellohealth-purchase-journey/start-wellness.json
--         + products.config.ts (the website-slug → platform-uuid map, per env)
--
-- WHAT THIS WRITES
--   The editorial content of the product detail page — the "What is …?" body, the
--   studied benefits and the academic citations behind them, the gallery, the
--   path-to-care steps. None of it is derivable from the product row, and none of
--   it is reachable from WooCommerce (its API returns description: "" — the copy
--   lives in Elementor widgets).
--
-- WHAT IT DOES NOT WRITE
--   Price. Nexus is already the pricing source of truth: the marketing site pulls
--   price and cadence *from* the platform, precisely because hand-typed prices had
--   drifted — MICC once advertised "was $199" against a real $199 charge, showing
--   the actual price as a saving. Nothing here touches price_cents.
--
-- SAFE TO RE-RUN
--   Idempotent. \`||\` is a shallow jsonb merge, so it replaces the 'pdp' key and
--   leaves every other key in metadata intact — that blob is shared, and a bare
--   SET metadata = '{…}' would silently drop other producers' keys.
--
-- SAFE AGAINST THE WRONG ROW
--   Matched on the product uuid AND the Brello tenant. Every environment's products
--   table also holds other tenants' rows (production carries CareLink-owned
--   compounded-* products), so the tenant predicate means a stale uuid updates
--   nothing rather than something else's row. The tenant is resolved by slug because
--   its uuid differs per environment.
--
-- ${rows.length} product${rows.length === 1 ? "" : "s"} in ${env}.

BEGIN;

${statements}

-- Check this BEFORE you COMMIT.
--
--   has_pdp        should be true for the products listed above.
--   other_keys     everything in metadata that is NOT 'pdp'. If another producer
--                  had keys here before the run, they must still be here now —
--                  that is the merge doing its job.
--   about_heading  a spot-check that real content landed, not an empty object.
SELECT p.name,
       (p.metadata -> 'pdp') IS NOT NULL                            AS has_pdp,
       ARRAY(SELECT jsonb_object_keys(p.metadata)
              EXCEPT SELECT 'pdp')                                  AS other_keys,
       p.metadata #>> '{pdp,about,heading}'                         AS about_heading,
       jsonb_array_length(COALESCE(p.metadata #> '{pdp,about,citations}', '[]'::jsonb))
                                                                    AS citations
  FROM public.products p
  JOIN public.tenants  t ON t.id = p.tenant_id
 WHERE t.slug = '${BRELLO_TENANT_SLUG}'
 ORDER BY p.name;

-- Happy? COMMIT. Not happy? ROLLBACK — nothing above has been persisted yet.
COMMIT;
`;

  const path = resolve(OUT_DIR, `product-pdp-${env}.sql`);
  writeFileSync(path, sql);
  console.log(`  ${env.padEnd(12)} ${rows.length} products → scripts/sql/product-pdp-${env}.sql`);
}
