# Product Page Content (`metadata.pdp`) & Pricing Anchors

How the patient-facing product page's editorial content and its strike-through price
are stored, edited, and backfilled. This is the reference for the **Page Content** tab
in Nexus and the two scraping scripts that seeded it.

---

## Where it lives

The product detail page's editorial content is stored on the product itself, in
`products.metadata.pdp` — a JSON blob. `metadata` is a **shared** column (other
producers keep their own keys in it, e.g. `allow_promo_codes`), so everything that
writes `pdp` merges rather than overwrites.

The strike-through anchor price is a first-class column, not part of the blob:
`products.compare_at_price_cents`.

Both are read by the patient app (via `patient-api`) and, going forward, by the
marketing site — one source of truth for the copy and the anchor.

---

## Editing it: the Page Content tab

**Catalog → Products → (a product) → Page Content.** The tab surfaces the whole
`metadata.pdp` block as a form and saves by merging back into `metadata` (never a bare
`{ pdp }` — that would drop sibling keys).

Sections:

| Section | `pdp` field | Notes |
|---|---|---|
| Header | `badge`, `subBadge`, `shortDesc`, `description` | Pills + teaser + lede |
| Gallery | `images[]` | Upload (to `product-images` bucket) or paste a URL; reorder; first is the hero |
| About | `about.heading`, `about.image`, `about.paragraphs[]`, `about.note`, `about.benefitsHeading`, `about.benefits[]`, `about.citations[]` | A paragraph/benefit `citation: n` is a 1-indexed pointer into `citations` |
| Includes | `includes[]` | Page-level bullets (distinct from checkout's `included_features`) |
| Path to care | `steps[]` | Title / description / image per step |

The strike-through price is edited on the **Payment Configuration** tab as
*Compare-at price (USD)* — it must be higher than the charged price, and it is display
only (never charged).

Code: `ProductPageContentEditor` + `GalleryImagesEditor` in
`src/components/features/`, wired as a tab in
`src/pages/tenant-admin/catalog/ProductDetail.tsx`. The `ProductPdpContent` shape is
in `types.d.ts`.

---

## How the content got there: the backfill scripts

The editorial content was written in Elementor on WordPress and isn't reachable from
WooCommerce's API (`description: ""`). It was extracted once into the marketing repo's
`start-wellness.json` and imported into Nexus. From then on it is edited in Nexus, not
re-imported. Three things were assembled to make products look complete:

### 1. Editorial content — `import-product-pdp.mjs` / `generate-product-pdp-sql.mjs`

Reads `start-wellness.json` (the about body, benefits, citations, includes). Emits
per-env SQL into `scripts/sql/product-pdp-{development,staging,production}.sql`.

Rules it never breaks: resolve the tenant **by slug** (`brello`), never by uuid (it
differs per env); match on uuid **and** tenant so a stale uuid can't hit another
tenant's row; **never write price**; merge onto existing `metadata`. Variants inherit
their parent bundle page's content, exactly as the site does at runtime.

### 2. Galleries — `product-galleries.json`

`start-wellness.json` carried images only for `compounded-tirzepatide`, so every other
product imported with an empty gallery. `product-galleries.json` holds the gallery URLs
per website slug, scraped from `product-sitemap.xml` (the `<image:loc>` block under each
`/product/` URL) and verified reachable. The generator layers these over the JSON's
`images` — the gallery wins, all other fields are left as authored. Bundles inherit
their primary medication's gallery.

### 3. Path-to-care steps — a constant in the generator

"A Straightforward Path to Care" (Submit → Provider Review → Delivery → Ongoing Support)
is identical on every Brello product. The JSON only spelled it out on two, so the
generator applies a `CANONICAL_STEPS` fallback to any product with none of its own.

### Duplicate product rows — `product-uuid-aliases.json`

`products.config.ts` maps one platform uuid per website slug, but each environment's
`products` table carries **duplicate rows** for some products — a second enabled row with
the same sku and price that the config map does not point at (e.g. the older
`Compounded Tirzepatide` alongside the newer `Compounded Tirzepatide - 3 month`). Left
alone, those duplicates render an empty product page. `product-uuid-aliases.json` lists
the extra uuids per environment under their matching slug; both generators emit an
additional statement for each, so the duplicate gets the same content and the same
compare-at anchor as the canonical row. Only genuine duplicates are listed (verified same
sku + price, currently without `pdp`); scratch/test rows with different skus or prices are
deliberately excluded. To refresh: list products per env, find enabled rows that share a
mapped product's sku+price but have no `metadata.pdp`, and add their uuid under that slug.

**Not backfilled:** per-product `about` prose for products that never had one written
(`compounded-micc`, `sermorelin-and-nad`). Their live pages mix in template content that
isn't theirs (e.g. MICC's page reuses the NAD+ studies block), so that copy is authored
by hand in the Page Content tab rather than scraped.

### Compare-at prices — `product-compare-at-prices.json` / `generate-product-pricing-sql.mjs`

A **separate, pricing-only** output. The website shows a struck-through anchor and a
saving ("~~$749~~ $499  Save 33%"). Every Brello product had `compare_at_price_cents`
null. `product-compare-at-prices.json` holds the anchors per slug, scraped from each
WooCommerce page's `display_regular_price` where it is genuinely above the charged
`display_price`. `generate-product-pricing-sql.mjs` emits
`scripts/sql/product-compare-at-{development,staging,production}.sql`.

It sets `compare_at_price_cents` **only** — never `price_cents` (the platform owns the
charged price; its values already match the site). Every anchor is real (a WooCommerce
regular price strictly above the sale price); `compounded-nad` is omitted (no discount).
Each `UPDATE` guards on `price_cents < anchor`, so a moved price skips rather than
advertising a false saving. The saving % is derived on the website, not stored.

---

## Applying any of it

The scripts **generate SQL only** — nothing writes to a database. For each environment,
paste the relevant file into that environment's Supabase SQL editor, read the
verification `SELECT` before the `COMMIT`, then commit (or `ROLLBACK`). The two families
touch different columns and can be applied in either order:

- `scripts/sql/product-pdp-<env>.sql` — content (`metadata.pdp`)
- `scripts/sql/product-compare-at-<env>.sql` — the strike-through anchor

Project refs and the service-role details for the script-based path are in
`scripts/README-import-product-pdp.md`.
