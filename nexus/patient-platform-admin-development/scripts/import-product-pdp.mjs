#!/usr/bin/env node
/**
 * Import product page content into `products.metadata.pdp`, per environment.
 *
 * ## What this is for
 *
 * The patient PDP is a spec sheet; brellohealth.com's is a long sales page. The
 * gap is ~9 sections of editorial content that exist in neither the product row
 * nor WooCommerce's API (which returns `description: ""` — the copy lives inside
 * Elementor widgets). They *do* exist, already written and already structured, in
 * the marketing site's repo: `brellohealth-purchase-journey/start-wellness.json`.
 *
 * This script moves that content into Nexus, so one place owns it and both
 * surfaces read it — the patient PDP and, later, the marketing site itself.
 *
 * ## Why it is a script and not a sync
 *
 * It is a one-off. Nexus is the destination, not a mirror: once the content is
 * here, it is edited here. Re-running is safe (it is idempotent), but nothing
 * schedules it.
 *
 * ## The rules it inherits from resolve-product-ids.mjs
 *
 * - **Resolve the tenant by slug, never by uuid.** Brello's tenant uuid differs in
 *   every environment, and every environment's `products` table also holds other
 *   tenants' rows — production contains CareLink-owned `compounded-*` products
 *   that are not Brello's. Filtering by slug is what keeps us off them.
 * - **A product uuid belongs to one environment.** The website→platform map in
 *   `products.config.ts` pins them per env. Never copy one across.
 *
 * ## What it deliberately does NOT write
 *
 * `price`, `href`, `variants`. Nexus is already the pricing source of truth — the
 * marketing site pulls price and cadence *from* the platform, precisely because
 * hand-typed prices had drifted (MICC once advertised "was $199" against a real
 * $199 charge — the actual price presented as a saving). Writing a price back
 * from the marketing JSON would reopen exactly that hole.
 *
 * ## Usage
 *
 *   # See what would change. Writes nothing. This is the default.
 *   SUPABASE_URL_DEVELOPMENT=… SUPABASE_SERVICE_ROLE_KEY_DEVELOPMENT=… \
 *     node scripts/import-product-pdp.mjs --env development
 *
 *   # Actually write it.
 *   … node scripts/import-product-pdp.mjs --env development --apply
 *
 * Needs the **service-role** key: `metadata` is not writable with the anon key.
 */

import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));

/** The marketing site's repo, alongside this one. Overridable for CI. */
const JOURNEY_REPO =
  process.env.JOURNEY_REPO ??
  resolve(__dirname, "../../brellohealth-purchase-journey");

const CONTENT = resolve(JOURNEY_REPO, "start-wellness.json");
const PRODUCTS_CONFIG = resolve(JOURNEY_REPO, "src/config/products.config.ts");

/** See the header: by slug, never by uuid. */
const BRELLO_TENANT_SLUG = "brello";

const ENVS = {
  development: {
    url: process.env.SUPABASE_URL_DEVELOPMENT,
    key: process.env.SUPABASE_SERVICE_ROLE_KEY_DEVELOPMENT,
  },
  staging: {
    url: process.env.SUPABASE_URL_STAGING,
    key: process.env.SUPABASE_SERVICE_ROLE_KEY_STAGING,
  },
  production: {
    url: process.env.SUPABASE_URL_PRODUCTION,
    key: process.env.SUPABASE_SERVICE_ROLE_KEY_PRODUCTION,
  },
};

const argv = process.argv.slice(2);
const apply = argv.includes("--apply");
const envArg = argv[argv.indexOf("--env") + 1];

const c = {
  red: (s) => `\x1b[31m${s}\x1b[0m`,
  yellow: (s) => `\x1b[33m${s}\x1b[0m`,
  green: (s) => `\x1b[32m${s}\x1b[0m`,
  cyan: (s) => `\x1b[36m${s}\x1b[0m`,
  dim: (s) => `\x1b[2m${s}\x1b[0m`,
  bold: (s) => `\x1b[1m${s}\x1b[0m`,
};

if (!envArg || !ENVS[envArg]) {
  console.error(
    c.red("usage: import-product-pdp.mjs --env <development|staging|production> [--apply]"),
  );
  process.exit(1);
}

/* ------------------------------- the mapping ------------------------------- */

/**
 * Read the website-slug → platform-uuid map out of `products.config.ts` as text.
 * That file imports the generated ids JSON, so it cannot be imported from plain
 * node — the same reason `resolve-product-ids.mjs` parses it rather than importing.
 */
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
    products[slug] = { platform };
  }
  return products;
}

/* ------------------------------- the content ------------------------------- */

/**
 * Every product in the marketing content, keyed by website slug — including the
 * **variant** slugs, which inherit their parent page's content.
 *
 * The website and the platform slice the same bundles differently. The website
 * has one *page* per bundle (`glp-1-and-nad`) with a variant selector on it; the
 * platform, in dev and production, sells that bundle as two separate *products*
 * (`glp-1-and-nad-tirzepatide`, `…-semaglutide`) — while staging sells it as one.
 * `products.config.ts` says so outright: "Staging sells this bundle as one product
 * rather than per-medication variants; dev and production sell it through the
 * variants below."
 *
 * So the content lives on the parent (that is the page that was written), and the
 * uuids live on the variants. Without bridging the two, production's variant
 * products would import nothing — losing, for the GLP-1 & NAD+ bundle alone, an
 * `about` body and eleven citations.
 *
 * A variant inherits the parent's content and overrides only what it names for
 * itself. That is precisely what the site does at runtime: same page, same copy,
 * the selector switches the price and the label, never the editorial.
 */
function readJourneyProducts() {
  const data = JSON.parse(readFileSync(CONTENT, "utf8"));
  const bySlug = {};

  for (const group of ["glp1Plans", "longevityMeds", "protocols"]) {
    for (const parent of data[group] ?? []) {
      bySlug[parent.slug] = parent;

      for (const variant of parent.variants ?? []) {
        if (!variant.slug) continue;
        // Inherit the page's content; keep the variant's own identity. `price`,
        // `billingCadence` and `savingsLabel` are deliberately not carried over —
        // the platform is the source of truth for what a customer is charged.
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

/** Drop undefined / empty values so we never write `{"about": {}}` or `null`s. */
function prune(value) {
  if (Array.isArray(value)) {
    const out = value.map(prune).filter((v) => v !== undefined);
    return out.length ? out : undefined;
  }
  if (value && typeof value === "object") {
    const out = {};
    for (const [k, v] of Object.entries(value)) {
      const pruned = prune(v);
      if (pruned !== undefined) out[k] = pruned;
    }
    return Object.keys(out).length ? out : undefined;
  }
  if (typeof value === "string" && value.trim() === "") return undefined;
  return value ?? undefined;
}

/**
 * Marketing product → `metadata.pdp`.
 *
 * Content only. `price`, `href` and `variants` are dropped on purpose: price
 * belongs to the platform (see the header), and href/variants are the website's
 * routing and commerce concerns, not the product's content.
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

/* ------------------------------- the platform ------------------------------ */

async function fetchProducts(env, { url, key }) {
  const headers = { apikey: key, Authorization: `Bearer ${key}` };

  const tenants = await fetch(
    `${url}/rest/v1/tenants?select=id&slug=eq.${BRELLO_TENANT_SLUG}`,
    { headers },
  ).then((r) => r.json());
  if (!tenants?.[0]) throw new Error(`${env}: no tenant with slug "${BRELLO_TENANT_SLUG}"`);

  const res = await fetch(
    `${url}/rest/v1/products?select=id,name,metadata&tenant_id=eq.${tenants[0].id}`,
    { headers },
  );
  if (!res.ok) throw new Error(`${env}: products query failed (${res.status})`);
  return res.json();
}

/**
 * Write `pdp` into `metadata`, preserving every other key.
 *
 * `metadata` is a shared blob — other producers keep their own keys in it. A
 * whole-column overwrite would silently drop theirs, so merge onto what is
 * already there and PATCH the merged object back.
 */
async function writePdp(url, key, id, existingMetadata, pdp) {
  const base =
    existingMetadata && typeof existingMetadata === "object" && !Array.isArray(existingMetadata)
      ? existingMetadata
      : {};

  const res = await fetch(`${url}/rest/v1/products?id=eq.${id}`, {
    method: "PATCH",
    headers: {
      apikey: key,
      Authorization: `Bearer ${key}`,
      "Content-Type": "application/json",
      Prefer: "return=minimal",
    },
    body: JSON.stringify({ metadata: { ...base, pdp } }),
  });
  if (!res.ok) throw new Error(`write failed (${res.status}): ${await res.text()}`);
}

/* ---------------------------------- main ----------------------------------- */

const creds = ENVS[envArg];
if (!creds.url || !creds.key) {
  console.error(
    c.red(`✗ ${envArg}: set SUPABASE_URL_${envArg.toUpperCase()} and ` +
      `SUPABASE_SERVICE_ROLE_KEY_${envArg.toUpperCase()}`),
  );
  console.error(c.dim("  The service-role key is required — metadata is not writable with anon."));
  process.exit(1);
}

const config = parseProductsConfig();
const journey = readJourneyProducts();
const live = await fetchProducts(envArg, creds);
const liveById = new Map(live.map((p) => [p.id, p]));

console.log(
  `\n${c.bold(envArg)}  ${c.dim(`${live.length} Brello products on the platform`)}` +
    (apply ? c.red("  APPLY — writing") : c.cyan("  DRY RUN — writing nothing")) +
    "\n",
);

let written = 0;
let skipped = 0;
let unmapped = 0;

for (const [slug, entry] of Object.entries(config)) {
  const content = journey[slug];
  const id = entry.platform[envArg];

  if (!content) continue; // mapped for checkout, but has no page content
  if (!id) {
    console.log(`${c.dim("–")} ${c.dim(slug.padEnd(26))} ${c.dim(`not on the platform in ${envArg}`)}`);
    unmapped++;
    continue;
  }

  const product = liveById.get(id);
  if (!product) {
    console.log(`${c.red("✗")} ${slug.padEnd(26)} ${c.red(`uuid ${id} is not a Brello product here`)}`);
    unmapped++;
    continue;
  }

  const pdp = toPdp(content);
  if (!pdp) {
    console.log(`${c.dim("–")} ${c.dim(slug.padEnd(26))} ${c.dim("no content to import")}`);
    skipped++;
    continue;
  }

  // What the page gains, section by section — the useful unit to review.
  const sections = [
    pdp.about?.heading && `about("${pdp.about.heading}")`,
    pdp.about?.benefits && `benefits×${pdp.about.benefits.length}`,
    pdp.about?.citations && `citations×${pdp.about.citations.length}`,
    pdp.images && `images×${pdp.images.length}`,
    pdp.steps && `steps×${pdp.steps.length}`,
    pdp.includes && `includes×${pdp.includes.length}`,
    pdp.badge && `badge`,
    pdp.shortDesc && `shortDesc`,
  ].filter(Boolean);

  const had = product.metadata?.pdp ? c.yellow(" (overwrites existing pdp)") : "";
  const via = content.inheritedFrom
    ? c.cyan(` ← content inherited from ${content.inheritedFrom}`)
    : "";

  console.log(`${c.green("✓")} ${slug.padEnd(26)} ${c.dim(product.name)}${via}`);
  console.log(`  ${c.dim(sections.join("  "))}${had}`);

  if (apply) {
    await writePdp(creds.url, creds.key, id, product.metadata, pdp);
    written++;
  } else {
    written++;
  }
}

console.log(
  `\n${apply ? c.green(`wrote ${written}`) : c.cyan(`would write ${written}`)}` +
    (skipped ? c.dim(`, skipped ${skipped}`) : "") +
    (unmapped ? c.yellow(`, ${unmapped} not on the platform here`) : "") +
    "\n",
);

if (!apply) console.log(c.dim("  Re-run with --apply to write.\n"));
