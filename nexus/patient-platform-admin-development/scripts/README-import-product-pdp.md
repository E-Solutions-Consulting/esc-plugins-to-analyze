# Importing product page content (`metadata.pdp`)

One-off import of the PDP's editorial content — the "What is …?" body, the studied
benefits and their citations, the gallery, the path-to-care steps — from the marketing
site's `start-wellness.json` into Nexus, so that Nexus owns the copy and every surface
reads it from there.

Run it once per environment. It is idempotent: re-running rewrites the same content.

**This is the one-time seed, not the ongoing edit path.** Once the content is in a
product, it is edited in the Nexus admin UI — see [Editing it afterwards](#editing-it-afterwards-the-page-content-tab)
below. The importer exists only to get the existing marketing copy in for the first
time; nothing schedules or re-runs it after that.

---

## Editing it afterwards: the Page Content tab

After the seed, this content is owned and edited in Nexus, not in a script and not
back in `start-wellness.json`.

Open a product in the admin — **Catalog → Products → (a product) → Page Content**.
The tab surfaces the whole `metadata.pdp` block as a form:

- **Header** — `badge`, `subBadge`, `shortDesc`, `description`.
- **Gallery** — the `images` array. Upload a file (it lands in the `product-images`
  storage bucket under the tenant's folder and the public URL is stored) or paste an
  external URL — the seeded galleries reference `brellohealth.com` directly, so both
  kinds coexist. Reorder with the arrows; the first image is the hero.
- **About** — `heading`, illustration `image`, `paragraphs` (each with an optional
  citation number), `note`, `benefitsHeading`, `benefits` (lead / rest / citation),
  and the `citations` reference list. A paragraph or benefit's citation number is a
  1-indexed pointer into the References list, matching the seed's shape.
- **Includes** — the page-level `includes` bullets. (Distinct from the checkout's
  "What's Included", which is `products.included_features` on the Details tab.)
- **Path to care** — the `steps` (title / description / image).

**How saving preserves the rest of `metadata`.** The tab reads `product.metadata`,
prunes the form back to the same shape the importer writes (empty fields dropped, so
nothing persists `{"about": {}}`), and PATCHes `{ ...existingMetadata, pdp }` — the
whole object, never a bare `{ pdp }`. `metadata` is a shared blob (other producers
keep keys like `allow_promo_codes` in it), so the merge is what keeps those keys
alive. This is the same rule the importer and the generated SQL follow.

Permissions follow the rest of the product page: editable by users who can edit
products, read-only otherwise.

The code: `ProductPageContentEditor` and `GalleryImagesEditor`
(`src/components/features/`), wired as a tab in
`src/pages/tenant-admin/catalog/ProductDetail.tsx`. The `ProductPdpContent` shape
lives in `types.d.ts`.

---

## Why this content isn't already in Nexus

It was written in Elementor, on WordPress. WooCommerce's Store API returns
`description: ""` for every product — the copy lives inside builder widgets, not in
any field the API exposes. It cannot be fetched.

It *was* extracted, though, into `brellohealth-purchase-journey/start-wellness.json`,
where it is already clean and already structured. That file is this script's input.

---

## What you need

A **service-role key** per environment. `metadata` is not writable with the anon or
publishable key, and the `.env.*` files here carry only the publishable one.

Get it from the Supabase dashboard → Project Settings → API → `service_role`.
It is a secret: don't commit it, and prefer pasting it inline over exporting it into
a long-lived shell.

| env | project ref |
|---|---|
| development | `sunzxjnbgtknqeivljtd` |
| staging | `rhzrxfckhogjppjsioyn` |
| production | `dfejvhgwqhywmtxyxkyo` |

---

## Running it

**Always dry-run first.** That is the default — it writes nothing and prints exactly
what it would change.

```bash
cd patient-platform-admin

# 1. Dev — see what would happen
SUPABASE_URL_DEVELOPMENT="https://sunzxjnbgtknqeivljtd.supabase.co" \
SUPABASE_SERVICE_ROLE_KEY_DEVELOPMENT="<service_role key>" \
  node scripts/import-product-pdp.mjs --env development

# 2. Dev — write it
SUPABASE_URL_DEVELOPMENT="https://sunzxjnbgtknqeivljtd.supabase.co" \
SUPABASE_SERVICE_ROLE_KEY_DEVELOPMENT="<service_role key>" \
  node scripts/import-product-pdp.mjs --env development --apply
```

Then the same for `--env staging` (`rhzrxfckhogjppjsioyn`) and `--env production`
(`dfejvhgwqhywmtxyxkyo`), swapping the `_STAGING` / `_PRODUCTION` variable names.

Check the patient PDP in dev before moving on to staging and production.

---

## What it writes, and what it refuses to

**Writes** into `products.metadata.pdp`: `badge`, `shortDesc`, `description`, `images`,
`about` (heading, paragraphs, benefits, citations), `includes`, `steps`.

**Galleries come from a second source.** `start-wellness.json` only ever carried images
for `compounded-tirzepatide`, so every other product first imported with an empty
gallery. `scripts/product-galleries.json` fills that gap: it holds the gallery image
URLs per website slug, scraped from brellohealth.com's WooCommerce `product-sitemap.xml`
(the `<image:loc>` block under each `/product/` URL) and verified reachable. The SQL
generator layers these over the JSON's `images` — the gallery wins, every other field
(`about`, benefits, citations) stays exactly as authored. Bundles have no
dedicated `/product/` page, so they inherit their primary medication's gallery, keyed by
the variant's own slug — the same inheritance the editorial content already uses. To
refresh after the marketing site changes its images: re-read `product-sitemap.xml`,
update `product-galleries.json`, and re-run the generator.

**Path-to-care steps are backfilled from a constant.** The "A Straightforward Path to
Care" steps (Submit Your Application → Provider Review → Direct-To-Door Delivery →
Ongoing Support) are identical on every Brello product — the company's standard process,
not product copy. `start-wellness.json` only spelled them out on two products, so the
rest imported with no steps. The generator applies a `CANONICAL_STEPS` constant (the
exact block those two carry) to any product that has none of its own, so every product
gets the steps while anything hand-written elsewhere still wins. What it deliberately
does **not** backfill is the per-product `about` prose: products with no About written
anywhere (`compounded-micc`, `sermorelin-and-nad`) stay without one — that copy is
authored by hand in the Nexus **Page Content** tab, not scraped, because the source
pages mix in template content that isn't theirs.

It **merges** onto the existing `metadata` rather than replacing the column — that blob
is shared, and other producers keep their own keys in it.

**Never writes price.** Nexus is already the pricing source of truth: the marketing site
pulls `price_cents` / `compare_at_price_cents` / cadence *from* the platform, precisely
because hand-typed prices had drifted — MICC once advertised "was $199" against a real
$199 charge, presenting the actual price as a saving. Writing marketing prices back into
Nexus would reopen exactly that hole. `href` and `variants` are skipped for the same
reason: routing and commerce are not content.

---

## Compare-at prices (a separate, pricing-only output)

Content and price are kept apart on purpose. The scripts above write `metadata.pdp`
and never touch price. There is, though, one pricing field that the platform was
missing and that the website genuinely has: the **compare-at** (strike-through) price —
the "~~$749~~ $499  Save 33%" anchor, configured in Nexus as *Compare-at price (USD)*
and stored in `products.compare_at_price_cents`.

`scripts/product-compare-at-prices.json` holds those anchors per website slug, scraped
from each WooCommerce product page's `display_regular_price` where it is genuinely above
the charged `display_price`. `scripts/generate-product-pricing-sql.mjs` turns them into
per-env SQL:

```bash
node scripts/generate-product-pricing-sql.mjs
# → scripts/sql/product-compare-at-{development,staging,production}.sql
```

What keeps this safe — and distinct from the price drift the content scripts guard
against:

- **It sets `compare_at_price_cents` ONLY.** It never writes `price_cents`. The platform
  is the source of truth for what a customer is charged, and its prices already match the
  live site exactly (verified against production before writing).
- **Every anchor is real.** Each is a WooCommerce regular price strictly above the sale
  price — a discount that actually exists. `compounded-nad` is omitted because its regular
  and sale price are equal (no discount), so nothing writes a $0 saving.
- **The saving % is not stored.** The website computes it from the two prices, so writing
  the anchor is all that is needed.
- **The UPDATE guards on `price_cents < <anchor>`.** A row is set only when the anchor is
  strictly above the current charged price — if a price has moved, that row is skipped
  rather than made to advertise a false or negative saving, mirroring the app's own rule.

Run it the same way as the content SQL: paste the file for an environment into that
environment's Supabase SQL editor, read the verification `SELECT` (it shows price,
compare-at and the derived saving per product), then `COMMIT`.

To refresh after the marketing site changes a price: re-read each `/product/` page's
`data-product_variations` (compare `display_regular_price` to `display_price`) and update
the dollar values in `product-compare-at-prices.json`, then regenerate.

---

## The two things that make this non-obvious

**1. Tenant and product uuids differ per environment.**
Brello's tenant uuid is different in dev, staging and production, and every environment's
`products` table also holds *other tenants'* rows — production carries CareLink-owned
`compounded-*` products that are not Brello's. So the script resolves the tenant **by
slug** (`brello`), never by uuid, and maps website slug → platform uuid through
`products.config.ts`, which pins them per environment. Never copy a uuid between envs.

**2. The website and the platform slice bundles differently.**
The website has one *page* per bundle (`glp-1-and-nad`) with a variant selector on it.
The platform, in production, sells that bundle as two separate *products*
(`…-tirzepatide`, `…-semaglutide`) — while staging sells it as a single one.

The content was written for the page; the uuids belong to the products. So a variant
**inherits its parent page's content**, which is what the site itself does at runtime:
the selector switches the price and the label, never the editorial. Without this, the
production variant products would import nothing, losing two `about` bodies and twenty
citations between them.

---

## Coverage

Environments are deliberately uneven — dev carries fewer products than production. That
is fine; the script imports whatever exists in the environment it is pointed at.

**10 of 11** platform products have content. `compounded-micc` has none written in the
marketing JSON (it has a badge and nothing else), so it imports nothing — expected, not
a failure.
