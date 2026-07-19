# Brello Migration Working Notes

> Last updated: 30 June 2026
> Owner: Rajesh Pandey
> Purpose: durable handoff state for continuing the Brello to Patient Platform migration across chats, machines, and PR review cycles.

Use this file as the operating notebook. Keep `docs/DataMigrationPlan.md` as the polished migration plan, and update this file whenever status, blockers, commands, PRs, or Linear task shape changes.

## Start-A-New-Chat Prompt

Copy this into a new Codex chat when continuing the migration:

```text
Read these files first:
- patient-platform-admin/docs/DataMigrationPlan.md
- patient-platform-admin/docs/migration-working-notes.md
- patient-platform-admin/docs/miguel-step2-call-outline.md
- patient-platform-admin/supabase/functions/migration-phase1-import/index.ts
- patient-platform-admin/supabase/migrations/20260526120000_add_metadata_to_orders.sql
- rt-data-hub-functions/functions/brelloMigrationPhase1/src/index.js
- rt-data-hub-functions/functions/brelloMigrationPhase1/package.json

Continue the Brello to Patient Platform migration from the current status.
Before editing, check git status in both repos:
- /Users/raj/Developer/Professional/Allia/Patient Platform/patient-platform-admin
- /Users/raj/Developer/Professional/Allia/RTDH/rt-data-hub-functions

Do not assume unresolved blockers are decided.
Do not use Bun or pnpm; use npm for installs and builds.
Do not add Mermaid labels with HTML tags or slash-style line breaks; keep diagram labels plain text.
Do not add Linear comments unless Raj explicitly asks. Updating Linear issue descriptions is okay when requested.
Keep Linear PP-90 and its subtasks aligned with docs and PR state.
```

## Current Plan Snapshot

| Step | Linear | Name                                             | Approx effort senior engineer days | ETA date | Status                 |
| ---- | ------ | ------------------------------------------------ | ---------------------------------- | -------- | ---------------------- |
| 0    | PP-529 | Pre-Migration Environment Setup                  | 0                                  |          | Done                   |
| 1    | PP-528 | Account + Empty Order Creation                   | 2                                  |          | Done                   |
| 2A   | PP-539 | Health Data Schema Approval & Mapping            | 1                                  |          | Done                   |
| 2    | PP-530 | Past Order Backfill + Health Data Migration      | 6                                  |          | Done                   |
| 2B   | PP-627 | Multi-product WooCommerce order handling         | 2                                  |          | In Progress (Miguel)   |
| 3    | PP-531 | Active Order Integration                         | 2                                  |          | Done                   |
| 4    | PP-532 | Same-Account Stripe Subscription Handoff         | 4                                  |          | Blocked (see below)    |
| X    | PP-540 | Brello Rise ID Mapping                           | 1                                  |          | Done                   |

ETA cells intentionally stay blank so Raj can fill actual dates.

## Repositories

| Repo                     | Branch/PR Context                                     | Current Notes                                                                                                   |
| ------------------------ | ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `patient-platform-admin` | Step 1 and idempotency work in PRs linked from PP-528 | Step 1 Edge Function deployed to staging; PR #49 includes migrated-event support and health idempotency support |
| `rt-data-hub-functions`  | Step 1 Cloud Function in PR #90                       | `brelloMigrationPhase1` deployed and tested in staging                                                          |
| `brello-backend`         | Source schema and prod DB access                      | Use only for schema/data investigation; do not use Bun/pnpm                                                     |

## Linear Map

| Issue    | Title                                                         | Current Meaning                                                           |
| -------- | ------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `PP-90`  | Brello to Patient Platform: Data Migration                    | Parent task; In Progress until Step 4 runs                                |
| `PP-529` | Step 0 - Pre-Migration Environment Setup                      | Done                                                                      |
| `PP-545` | Step 0A - Supabase Migration Setup                            | Done; owned by Joao                                                       |
| `PP-546` | Step 0B - Grant GCP Access for Brello Migration               | Done; owned by Cristovao                                                  |
| `PP-528` | Step 1 - Account + Empty Order Creation                       | Done; full population ran June 28-30 2026                                 |
| `PP-539` | Step 2A - Health Data Schema Approval & Migration Mapping     | Done                                                                      |
| `PP-530` | Step 2 - Past Order Backfill + Approved Health Data Migration | Done; full population ran June 28-30 2026                                 |
| `PP-627` | Step 2B - Multi-product WooCommerce order handling            | In Progress; Miguel owns                                                  |
| `PP-531` | Step 3 - Active Order Integration                             | Done; validated end to end against a real WooCommerce order on 26 June 2026 |
| `PP-532` | Step 4 - Same-Account Stripe Subscription Handoff             | Code done and deployed to staging; blocked on WC billing handoff agreement and internal user decision |
| `PP-540` | Cross-Cutting - Brello Rise ID Mapping                        | Done; use legacy_brello_uid for migrated Rise users                       |

Linear formatting rules:

- Use plain Markdown.
- Do not use HTML line breaks.
- Do not use escaped newline text in Mermaid labels.
- Do not make comments sound overly polished or generated.

## Step 0 Status

Step 0 is complete.

- Supabase setup is complete.
- GCP access and runtime permissions are complete.
- `migration-phase1-import` is deployed to Supabase staging.
- `brello-migration-phase1-staging` is deployed and serving.
- Runtime can read `allia-brello-raw`.
- Runtime can read `allia-woocommerce-raw-prod`.
- Runtime can read `brello-crypto-secret` and `migration-api-key`.
- Dry-run and non-dry-run Step 1 executions work.

## Step 1 Status

Step 1 is ready for QA / release.

Implemented:

- GCP Cloud Function `brelloMigrationPhase1`.
- Supabase Edge Function `migration-phase1-import`.
- Supabase Auth user creation/reuse.
- Patient upsert by `(tenant_id, email)`.
- Subscription stubs using `metadata->>'woo_subscription_id'`.
- Order stubs using `metadata->>'woo_order_id'`.
- Address mapping to current Patient Platform shipping and billing columns.
- Idempotency rerun confirmed auth created 0, subscriptions inserted 0, orders inserted 0.

Five internal test users imported in Supabase staging:

- `ecommerce@brellohealth.com`
- `brellorise@brellohealth.com`
- `semaglutide@brellohealth.com`
- `nad@brellohealth.com`
- `tirzepatide@brellohealth.com`

Supabase staging project:

- Project ref: `rhzrxfckhogjppjsioyn`
- Edge Function URL: `https://rhzrxfckhogjppjsioyn.supabase.co/functions/v1/migration-phase1-import`

Known staging artifact:

- One internal test account had duplicate stubs before the idempotency fix was deployed. The deployed fix prevents new duplicates.

## Step 2 Plan Change - 8 June 2026

Step 2 now follows the same operating model as Step 1.

Components:

- `brelloMigrationPhase2` GCP Cloud Function reads WooCommerce and Brello backup data from GCS.
- A new Supabase Edge Function ingests transformed Step 2 payloads directly into Supabase.
- Historical order/subscription and health data is written directly to Supabase by the Edge Function.
- Historical order data is still written directly to Elasticsearch.
- Patient Platform consumer dependency is removed from Step 2.

Step 2 must not rely on PP consumer migrated-event flow.

Step 2 should still be idempotent:

- Match order stubs by `orders.metadata->>'woo_order_id'`.
- Match subscription stubs by `subscriptions.metadata->>'woo_subscription_id'`.
- Preserve source IDs and migration status in metadata.
- Use health migration keys `tenant_id`, `patient_id`, `migration_source`, `migration_source_id`, and `migration_source_item_key`.

Step 2 data sources:

- WooCommerce: `mrb_wc_orders`, `mrb_wc_orders_meta`, `mrb_wc_order_addresses`, `mrb_wc_order_addresses_contact`, `mrb_wc_order_operational_data`, `mrb_woocommerce_order_items`, `mrb_woocommerce_order_itemmeta`, `mrb_comments`.
- Brello health: `weight_log`, `medication_log`, `measurement_log`, `symptoms_log`.

Health mapping:

- `weight_log` to `patient_weight_entries`.
- `medication_log` to `medication_shot_intakes`.
- `symptoms_log.symptoms` to `patient_symptom_entries`.
- `symptoms_log.mood` to `patient_mood_change_entries`.
- `symptoms_log.activities` to `patient_activity_entries`.
- `measurement_log` to `patient_body_measurement_entries` for chest, waist, hips, and arms.
- `symptoms_log.other` needs a new target table or explicit deferral.

Known data findings:

- `measurement_log.thigh`, `neck`, and `notes` have 0 populated rows in production, so they should not block Step 2.
- `symptoms_log.other` has 2,498 rows and needs a decision.
- `medication_log.pain_level` ranges 0 to 10; Patient Platform scale is 0 to 5, so use `round(pain_level / 2)`.

Step 2 lookup CSV:

- Current file: `patient-platform-admin/step2-migration-lookup-mapping.csv`.
- Current size: 54 lookup rows plus header.
- The CSV is the current source of truth for Miguel's Step 2 implementation unless a later product decision changes it.
- It includes WooCommerce product/variation mappings, medication mappings, injection-site label mappings, and Rise policy.

Confirmed product mappings:

| Woo product | Woo variation | Patient Platform product | Product id | Notes |
| ------------ | ------------- | ------------------------ | ---------- | ----- |
| 10122 | 10123 | Compounded Tirzepatide | 95737fde-85a1-48b0-871d-f14a6d3bb400 | Primary 3-month Tirzepatide |
| 331 | 332 | Compounded Tirzepatide | 95737fde-85a1-48b0-871d-f14a6d3bb400 | Legacy 3-month Tirzepatide VIP |
| 366 | 367 | Compounded Tirzepatide | 95737fde-85a1-48b0-871d-f14a6d3bb400 | Legacy 3-month Tirzepatide Black Friday |
| 515177 | 515266 | Compounded Tirzepatide | 95737fde-85a1-48b0-871d-f14a6d3bb400 | 3-month special |
| 10116 | 10119 | Compounded Semaglutide | 3435f2fd-4d09-4704-a52e-c722775f8445 | Primary 3-month Semaglutide |
| 369 | 370 | Compounded Semaglutide | 3435f2fd-4d09-4704-a52e-c722775f8445 | Legacy 3-month Semaglutide VIP |
| 251 | 252 | Compounded Semaglutide | 3435f2fd-4d09-4704-a52e-c722775f8445 | Legacy 3-month Semaglutide Black Friday |
| 518309 | 518324 | Compounded NAD+ | b06ff475-1793-42bb-99f3-8874b4fc3888 | Primary 3-month NAD |
| 497138 | 497143 | Empowered+ Longevity Lifestyle Plan (Tirz.) | 3c5fc94b-1370-41ca-bebc-bf1e778fec53 | Empowered Tirzepatide and NAD bundle |
| 497138 | 497142 | Empowered+ Longevity Lifestyle Plan (Semag.) | 40f3fde0-7d2b-48cc-a987-05b3bf80c862 | Empowered Semaglutide and NAD bundle |
| 617908 | 617914 | Thrive Forward Longevity Lifestyle Plan (Semaglutide) | 4d47f3c7-2d37-4d65-86d5-a2d133415989 | Thrive Semaglutide, Sermorelin, and NAD bundle |

Empowered product note:

- Empowered products are matched.
- Woo `497138/497143` maps to PP `empowered-longevity-lifestyle-plan`.
- Woo `497138/497142` maps to PP `empowered-longevity-lifestyle-plan-semag`.
- Both target products exist in the Supabase export, are disabled, and have `subscription_interval_count = 3`.
- Preserve original Woo item name in metadata because a few rows under the same Woo product can have ambiguous labels.

Monthly product decision:

- Latest João/Alessandra direction: monthly and three-month subscriptions must remain separate products.
- The fresh `products_rows.csv` export has only six products, all with `subscription_interval_count = 3`.
- Monthly Woo pairs are marked in the CSV as `product_unmapped` with action `needs_pp_monthly_product`.
- Step 2 must not map monthly Woo pairs into the existing three-month PP products unless product/ops explicitly approves a fallback.

Monthly Woo pairs currently marked as needing PP monthly products:

| Woo product | Woo variation | Meaning |
| ----------- | ------------- | ------- |
| 331 | 333 | Monthly Tirzepatide |
| 366 | 368 | Monthly Tirzepatide Black Friday |
| 10116 | 10120 | Monthly Semaglutide |
| 369 | 371 | Monthly Semaglutide VIP |
| 251 | 253 | Monthly Semaglutide Black Friday |
| 67207 | 71193 | Monthly NAD |

Sermorelin decision:

- Standalone Sermorelin is mapped to the PP Sermorelin medication.
- Woo `562678/562680` and `559983/559991` map to medication `135ae212-a4e6-4a92-8d6d-317cf4c1320a` (Sermorelin).
- Current PP product export does not include a standalone Sermorelin product, so order-level `product_id` may stay null unless a product is created.

Medication and injection-site mapping:

- Medication names map to PP medications by stable medication identity, not by product id.
- Preserve raw source strength in metadata while using `medication_log.strength` for dosage strength.
- Injection-site ids must be resolved at runtime by `tenant_id` plus label/name.
- Do not hardcode tenant-specific injection-site definition ids.
- Unmapped injection sites `Other`, `Right Buttock`, and `Left Buttock` should use null `injection_site_id` and preserve the source label in metadata unless PP adds those sites.

Latest Step 2 comment thread:

- Miguel found unmapped WooCommerce statuses: `wc-temporary-collect`, `auto-draft`, and blank status.
- `cancel_cus_req` and `cancel_pat_rej` are already mapped in Phase 1 as `order_cancelled`.
- João suggested asking Jaime what the remaining statuses mean.
- João/Alessandra confirmed monthly and three-month subscriptions should stay as separate products.

Open Step 2 blockers:

- Status mapping for `wc-temporary-collect`, `auto-draft`, and blank status.
- Decision for monthly Woo products: create inactive monthly PP products or keep `product_id` null with source metadata.
- Decision for standalone Sermorelin product id if order items require a PP product.
- Product decisions for Gastro Guard, Thrive Tirzepatide bundle, Metabolic Compass plans, and GLP-Ignite.
- Elasticsearch alias and mapping names.
- `symptoms_log.other` target or deferral.

## Step 3 Plan Change - 8 June 2026

Step 3 is active order migration.

Current direction:

- Once Real Time Data Hub is live in production, it should already be reading active order information.
- Migration work should make sure active WooCommerce orders are linked to the corresponding Supabase orders.
- Supabase consumer should identify active order events using WooCommerce IDs stored in Supabase metadata or a lookup table.
- Step 3 should focus on ID linking, validation, reconciliation, and retry behavior for active orders.

Step 3 is no longer described as a custom migration replay path. It should lean on RTDH production data flow.

Step 3 still needs:

- Final confirmation of the WooCommerce ID fields sent through RTDH.
- Final confirmation of lookup strategy in Supabase.
- Product/SKU mapping for active orders.
- QA plan for active orders across WooCommerce, RTDH, and Supabase.

## Step 3 Active Order Linking Findings - 12 June 2026

RTDH sample master object confirms the Patient Platform webhook route works when the master object includes:

- `ids.patient_platform_order_id` matching `orders.id` in Supabase.
- `ids.provider_order_id` and `provider.provider_order_id` for Telegra order identity.
- `fulfillment.order_reference_id` as a `trn::...` reference for LifeFile style references.
- `global_status` set to a PP status key such as `pharmacy_approved`.

Important PP behavior from code:

- `rtdh-webhook` validates `ids.patient_platform_order_id` directly against `orders.id`.
- As of 13 June 2026, `rtdh-webhook` can also resolve migrated orders by `ids.woocommerce_order_id`, `ids.woo_order_id`, or `ids.wc_order_id` against `orders.metadata.woo_order_id`.
- The Woo fallback is only useful if RTDH sends one of those Woo order id fields in the master object payload.
- RTDH master-object tests suppress outbound webhook delivery when `patient_platform_order_id` is missing.

Woo snapshot findings from 12 June 2026:

| Finding | Count |
| ------- | ----- |
| Active-ish Woo shop orders | 9,193 |
| Woo comments mentioning Telegra activity | 1,391,682 |
| Woo comments containing explicit `order::...` ids | 527 |
| Active-ish Woo orders with explicit `order::...` ids in comments | 27 |
| Woo comments containing `trn::...` ids | 0 |

Current Step 3 conclusion:

- Step 3 should still prefer RTDH master-object linking to `ids.patient_platform_order_id`.
- PP now has a Woo order id fallback, but this is a safety net, not the primary plan.
- The safest path is to make sure RTDH master objects for migrated active orders get linked to the Supabase order id before PP receives future events.
- For already-linked RTDH master objects, no PP code change is needed.
- For unlinked historical active orders, we need a reliable way to map Woo order id or Telegra provider order id to the RTDH master object.
- Woo comments are not enough for broad provider-order backfill; they only expose explicit provider order ids for a small subset and mostly in error comments.

Likely next implementation options:

1. Preferred: RTDH link backfill. Build a one-time script/function that writes RTDH master-order link docs connecting existing master objects to Supabase `orders.id` using a confirmed provider id or Woo id source.
2. Alternative: PP webhook fallback. This code exists now, but still depends on RTDH sending a Woo order id in the master object payload. Current sample does not show Woo id.
3. Last resort: wait for next RTDH event and reject/unlinked-events report, then reconcile manually or with a retry job.

Code update completed on 13 June 2026:

- `patient-platform-admin/supabase/functions/rtdh-webhook/index.ts`
- Added order-id extraction helpers for `patient_platform_order_id`, `woocommerce_order_id`, `woo_order_id`, and `wc_order_id`.
- Added Supabase fallback lookup from Woo order id to `orders.id` through `orders.metadata.woo_order_id`.
- Reused the resolved order id for reference validation, invoice transaction update, and direct status handling.
- Verified with `deno check`.

RTDH code status from 13 June 2026:

- WooCommerce order webhooks are normalized by `pubsubProcessor` and written to the order Elasticsearch alias.
- WooCommerce distribution currently publishes normalized events to the configured distribution topic.
- `masterObjectProcessor` does not currently dispatch `source = woocommerce`; supported branches are Stripe, Patient Platform, provider, LifeFile, EasyPost, JotForm, and MDI.
- Therefore a Woo identity-link patch inside `masterObjectProcessor` is not sufficient by itself unless Woo events are routed into that processor.

Questions still needed from RTDH/Telegra owners:

- Does the RTDH active WooCommerce master object include any Woo order id field in `ids`, `provider`, `timeline`, or raw source payload?
- Can RTDH expose or query master objects by Woo order id?
- Is there a Telegra API or backup table that maps Woo order id to Telegra `order::...` id for successful orders?
- Should Step 2 store provider order ids in Supabase if Miguel finds them while backfilling full historical order data?

## Step 4 Plan Change - 18 June 2026 (Same Stripe Account API Handoff)

João and Raj confirmed:

- **First migration batch: Brello WC → Brello PP stays in the same Stripe account** (`acct_1OyxbR08au2AZSqr`).
- Existing Stripe customers and saved payment methods are reusable — no re-entry needed.
- `_stripe_customer_id` from WC meta IS the destination customer id for this batch.
- CareLink is a later separate migration; users will re-enter card details + use a coupon.
- Because Brello WC and Brello PP are using the same Stripe account, the Stripe Billing Migration Toolkit is not required for this first Brello WC to Brello PP path.

Primary Step 4 path:

1. Build a Supabase-admin migration function for Step 4, likely `migration-phase4-subscription-enable`.
2. Accept an explicit scoped list of emails or patient ids for each run.
3. Resolve each patient to Woo customer, Woo subscription, Supabase subscription, and `_stripe_customer_id`.
4. Verify the Stripe customer exists in the Brello Stripe account.
5. Verify the customer has a reusable saved payment method in the same Stripe account.
6. Create a new Stripe Billing subscription through the Stripe API using the existing Stripe customer id.
7. Use a PP recurring Stripe price id when one is provided, otherwise create the subscription with inline Stripe `price_data` from the PP product amount and interval.
8. Align `billing_cycle_anchor` to the Woo next payment date and use no proration.
9. Save the new Stripe subscription id in Supabase and mark the migrated subscription as PP-managed.
10. Mark or block the WooCommerce subscription so it does not renew again.
11. Return row-level success/failure so the function can be safely rerun.

Double-charge prevention:

- Create PP subscription with `billing_cycle_anchor = next_payment_date` matching WC renewal date.
- Use `proration_behavior = none`.
- Keep the PP subscription from charging immediately unless the renewal date is intended to be now.
- Cancel, pause, or mark the WooCommerce subscription as migrated **before** PP takes over renewal.
- Validate via Stripe webhook that first charge only fires once on go-live date.

Why this replaces the toolkit for the first Brello batch:

- The toolkit mainly helps import subscriptions from another billing system or account.
- Here, the customer and saved payment method already live in the same Stripe account that PP will use.
- API-based subscription creation gives us better control over scoped email batches, Supabase metadata, WooCommerce handoff state, and row-level retries.

Toolkit fallback:

- Toolkit-specific CSV files and the local import generator were removed from the active Brello WC to Brello PP path on 19 June 2026.
- Cross-account migrations still need destination customers and saved payment methods first; saved payment methods cannot be copied between Stripe accounts.
- If a later CareLink or other cross-account path needs CSV import, create a fresh toolkit generator in that task instead of carrying it in the Brello same-account path.

Artifacts added 17 June 2026:

- `scripts/check-stripe-customer-payment-methods.cjs` — reads source discovery, calls Stripe API, outputs customer map.

Removed toolkit artifacts on 19 June 2026:

- `billing_migration_template.csv`
- `stripe-subscription-import-template.csv`
- `stripe-destination-customer-map-template.csv`
- `stripe-destination-price-map-template.csv`
- `stripe-subscription-source-discovery.csv`
- `stripe-subscription-source-discovery-summary.csv`
- `stripe-source-customer-list-for-payment-method-migration.csv`
- `stripe-migration-product-gap.csv`
- `stripe-subscription-stripe-enrichment-random-sample.csv`
- `stripe-subscription-stripe-enrichment-random-sample-summary.csv`
- `scripts/generate-stripe-subscription-import.cjs`
- `docs/stripe-subscription-migration-prep.md`

Remaining needs:

- Stripe restricted key must have read access to customers (`sk_live_...` from `.env.prod`).
- Destination Stripe price ids are optional for the first API test because the Step 4 function can use inline Stripe `price_data` from the PP product. Product metadata `stripe_product_id` can still be used when present.
- First email-list batch must be defined (even 5–10 users to validate end-to-end).
- João/Miguel must confirm WC subscription cancel mechanism and timing.
- Step 4 must accept an explicit list of emails and a destination account/tenant config for each run.

Sandbox/prod rule:

- Live Stripe objects and sandbox Stripe objects are separate.
- Live customers, payment methods, prices, and subscriptions cannot be used in sandbox.
- Sandbox can test CSV format and flow only with test-mode destination customers and prices.
- Production migration must use live destination customers and live destination prices in the correct account.

Current Step 4 implementation artifacts:

- `supabase/functions/migration-phase4-subscription-handoff/index.ts` — dry-run-first same-account Stripe handoff function.
- The function accepts scoped emails, patient ids, or subscription ids.
- The function requires `X-Migration-API-Key`.
- Live creation requires `dry_run=false` and `confirm_live=true`.
- The function is idempotent and skips subscriptions that already have a Stripe subscription id or subscription payment provider link.

Needed for same-account Brello PP batch:

- Run `check-stripe-customer-payment-methods.cjs` with Stripe key to confirm which customers have saved payment methods.
- Create PP Stripe products and recurring prices in the Brello Stripe account where they do not exist yet.
- Create the Step 4 subscription-enable Edge Function.
- Test a scoped 5-user run in live mode only with internal/test customers, or test mode with test customers/prices.
- Coordinate WooCommerce renewal blocking with João/Miguel before any real customer handoff.

Fallback:

- For users with no saved payment method in the Brello Stripe account, keep WooCommerce renewal active and do not create a PP-managed Stripe subscription yet.

## Step 4 Stripe Customer Fallback - 25 June 2026

Step 4 eligibility was updated after the Mariana test case.

Previous assumption:

- The active subscription row itself needed to carry `_stripe_customer_id`.

Current rule:

- Stripe customer id is account-level evidence.
- If any Woo subscription for the same patient/customer has `_stripe_customer_id`, Step 4 can reuse that Stripe customer id for the active handoff.
- The active subscription still needs a future billing anchor.
- The active subscription still needs a mapped PP product before live Stripe subscription creation.
- Step 4 only attempts handoff for active PP subscription rows. Historical/cancelled rows are intentionally blocked.

Why this matters:

- WooCommerce stores Stripe customer id inconsistently across subscription rows.
- The Stripe customer id is account-wide in the same Brello Stripe account, so one valid Stripe customer id is enough for the patient.
- Cohort eligibility should use patient/customer-level Stripe evidence, not row-level Stripe evidence.

Mariana validation:

- Approved test account: `marianamaglioni@gmail.com`.
- Woo customer id: `29323`.
- Woo active subscription: `689893`.
- Woo shows product `MICC - 3 MONTH PLAN`, variation id `686422`, next payment `2026-08-27 01:10`.
- Step 4 dry-run now resolves the shared Stripe customer id correctly from another migrated subscription row.
- The active handoff still blocks because the active MICC subscription has no mapped PP product.

Implication for cohorts:

- Some users previously marked as missing payment evidence may become eligible if another subscription row for the same Woo customer has `_stripe_customer_id`.
- Some users may still be blocked by product mapping or missing future renewal date.
- Regenerate the cohort pack using account-level Stripe customer evidence.


## Current Questions To Ask Humans

Ask Jaime/João:

- What do `wc-temporary-collect`, `auto-draft`, and blank WooCommerce status mean?
- Should these be skipped, mapped, or treated as invalid historical data?

Ask João/Alessandra:

- Is `symptoms_log.other` deferred from Step 2 first pass?
- If not deferred, what exact table and fields should receive it?
- Should monthly Woo subscription products be created as inactive PP products, or should Step 2 keep their `product_id` null and preserve source metadata?
- Does standalone Sermorelin need a PP product row for historical order backfill, or is medication-level mapping enough?
- Should Gastro Guard, Thrive Tirzepatide bundle, Metabolic Compass plans, and GLP-Ignite get PP products or remain nullable historical metadata?

Ask João:

- What Supabase lookup should Step 3 use for active WC order IDs?

Ask Umar/João:

- Which Elasticsearch aliases should Step 2 write to in staging/prod?
- What RTDH production payload fields identify the WooCommerce order/subscription/customer?

Ask Alessandra/Mariana/Stripe owner:

- What are the destination Stripe accounts for Carelink, Allia Health, and other destinations?
- Who owns creating destination customers and prices in each destination account?
- Which payment recollection flow should we use: Checkout setup mode, Setup Intents, or Payment Element?
- What email lists define the first scoped Step 4 batches?
- When should WooCommerce renewal be blocked for each destination batch?

## Supabase Queries For Five Step 1 Users

Patient rows:

```sql
select
  p.id as patient_id,
  p.auth_user_id,
  p.email,
  p.first_name,
  p.last_name,
  p.metadata->>'legacy_brello_uid' as legacy_brello_uid,
  p.metadata->>'woo_id' as woo_id,
  p.metadata->>'telegra_id' as telegra_id,
  p.metadata->>'is_migrated' as is_migrated,
  p.metadata->>'migration_phase' as migration_phase,
  p.created_at
from public.patients p
join public.tenants t on t.id = p.tenant_id
where t.slug = 'brello'
  and lower(p.email) in (
    'ecommerce@brellohealth.com',
    'brellorise@brellohealth.com',
    'semaglutide@brellohealth.com',
    'nad@brellohealth.com',
    'tirzepatide@brellohealth.com'
  )
order by p.email;
```

Stub counts:

```sql
select
  p.email,
  count(distinct s.id) as subscription_stubs,
  count(distinct o.id) as order_stubs
from public.patients p
join public.tenants t on t.id = p.tenant_id
left join public.subscriptions s
  on s.patient_id = p.id
 and s.tenant_id = p.tenant_id
 and s.metadata ? 'woo_subscription_id'
left join public.orders o
  on o.patient_id = p.id
 and o.tenant_id = p.tenant_id
 and o.metadata ? 'woo_order_id'
where t.slug = 'brello'
  and lower(p.email) in (
    'ecommerce@brellohealth.com',
    'brellorise@brellohealth.com',
    'semaglutide@brellohealth.com',
    'nad@brellohealth.com',
    'tirzepatide@brellohealth.com'
  )
group by p.email
order by p.email;
```

## Maintenance Rules For Future Codex Sessions

- Always check git status in both repos before editing.
- Never revert user changes unless explicitly asked.
- Prefer repo patterns over new abstractions.
- Keep docs, diagrams, PRs, and Linear in sync.
- Do not use `Brellarized`; use `Brello Rise`.
- Do not use HTML line breaks or slash-style tags inside Mermaid labels for Linear.
- Do not use Bun or pnpm for migration work.
- Step 2 no longer depends on the Patient Platform consumer.
- Step 2 must use one GCP reader function and one Supabase ingest Edge Function.
- Step 2 should still write historical order data to Elasticsearch.
- Do not include `symptoms_log.other` in Step 2 first pass unless a target table is confirmed.
- Use `patient-platform-admin/step2-migration-lookup-mapping.csv` as the current Step 2 lookup source.
- Do not map monthly Woo products to three-month PP products.
- Empowered Woo products are matched to existing disabled three-month PP Empowered products.
- Standalone Sermorelin maps to PP Sermorelin medication, but standalone Sermorelin product id remains undecided.
- Rise migration is complete as Option A: use `patients.metadata.legacy_brello_uid` for migrated Rise users.
- Stripe Billing migration toolkit is not the primary path for Brello WC to Brello PP anymore; use API-based same-account handoff first.
- Stripe Billing migration toolkit creates destination subscriptions only after destination customers and payment methods exist, and remains useful for later cross-account migrations.
- **For Brello WC → Brello PP: same Stripe account** — old_customer_id IS the destination customer id. No new customer creation needed.
- Do not assume saved payment methods can move between Stripe accounts — this applies only to cross-account migrations (e.g. CareLink).
- Step 4 must support explicit email-list batches and destination account routing for future cross-account batches.
- Phase 2 GCP function emits `stripe_customer_id` in subscription metadata from WC meta `_stripe_customer_id`.
- Phase 2 Supabase Edge Function now preserves `source_billing.stripe_customer_id` and `migration_phase_2.stripe_customer_id` for Step 4.
- Phase 2 Edge Function now uses `null` injection_site_id (with console.warn) for unmapped injection sites instead of throwing.

## Step 4 Stripe Subscription Migration Findings - 11 June 2026

Historical direction, superseded on 18 June 2026 for Brello WC to Brello PP:

- The original plan was to use Stripe Billing migration toolkit as the primary path.
- That is no longer the primary path for Brello WC to Brello PP because the first Brello batch stays in the same Stripe account.
- Keep these findings for source-data discovery and possible later cross-account migrations.
- WooCommerce/GCS has old Stripe customer ids in `mrb_wc_orders_meta` under key `_stripe_customer_id`.
- Old Stripe subscription ids were not found in the Woo metadata snapshot scanned on 11 June 2026.
- `mrb_usermeta` did not contain useful Stripe references for this migration.
- Generated local discovery files:
  - `patient-platform-admin/stripe-subscription-source-discovery.csv`
  - `patient-platform-admin/stripe-subscription-source-discovery-summary.csv`

Discovery counts:

| Metric | Count |
| ------ | ----- |
| Total Woo subscriptions | 178,828 |
| Active-like Woo subscriptions | 93,533 |
| Active-like subscriptions with `_stripe_customer_id` | 89,560 |
| All subscriptions with `_stripe_customer_id` | 169,773 |
| Active-like subscriptions with billing schedule metadata | 93,533 |

Active-like statuses used for the discovery file:

- `wc-active`
- `wc-pending`
- `wc-on-hold`
- `wc-processing`

Current use of these findings:

- Use `_stripe_customer_id` as the reusable Stripe customer id for same-account Brello WC to Brello PP.
- Do not depend on old Stripe subscription ids; create new PP-managed subscriptions through Stripe API.
- Create PP-specific Stripe products and recurring prices in the Brello account for importable PP products.
- Keep monthly Woo subscriptions blocked until monthly PP products and Stripe prices exist.
- Keep rows with `missing_lookup` blocked until product mapping is resolved.


## Step 4 Stripe API Random Sample - 12 June 2026

Using Brello Stripe API access from `.env.prod`, sampled 100 deterministic-random active mapped Woo subscription rows with `total_amount >= 50`.

Output files:

- `patient-platform-admin/stripe-subscription-stripe-enrichment-random-sample.csv`
- `patient-platform-admin/stripe-subscription-stripe-enrichment-random-sample-summary.csv`

Results:

| Metric | Count |
| ------ | ----- |
| Eligible active mapped rows with Stripe customer id and total amount >= 50 | 88,620 |
| Random sampled rows | 100 |
| Stripe customers found | 100 |
| Customers with default payment method | 99 |
| Stripe Billing subscriptions found | 0 |
| Lookup errors | 0 |

Current Step 4 implication:

- Brello/Woo likely used Stripe customers plus saved default payment methods, not Stripe Billing subscriptions, for the sampled subscription rows.
- Do not build Step 4 around old Stripe subscription ids unless a broader Stripe export later proves they exist.
- For Brello WC to Brello PP, build new PP-managed subscriptions in the same Stripe account after product and price mapping is ready.
- For later cross-account migrations, destination customers and payment setup are still required.

## Step 4 Stripe Documentation Review - 15 June 2026

Docs reviewed:

- https://docs.stripe.com/billing/subscriptions/import-subscriptions-toolkit
- https://docs.stripe.com/get-started/data-migrations/pan-import
- https://docs.stripe.com/get-started/data-migrations/map-payment-data

Historical interpretation, partially superseded on 18 June 2026:

- Stripe Billing migration toolkit is still a valid subscription import path for CSV-driven or cross-account cases.
- It is no longer the preferred path for Brello WC to Brello PP because API-based same-account handoff is more controllable.
- Stripe says Stripe-to-Stripe subscription migrations can skip the separate payment data import prerequisite in some toolkit flows, but that does not remove our need to coordinate WooCommerce renewal blocking.
- The subscription import CSV still needs valid customer ids and destination recurring price ids.
- For same-account Brello WC to Brello PP, Woo/GCS `_stripe_customer_id` is the Stripe customer id to reuse.
- For cross-account cases, a source-to-destination Stripe customer mapping is still required before final subscription CSV generation.
- Stripe docs support mapping imported payment data into existing Stripe Customer objects with a two-column file: `old_customer_id,stripe_customer_id`.
- Stripe payment data import output can include a mapping file showing old customer/payment ids to new Stripe customer/payment method ids.
- If Stripe can migrate or map old Brello customers/payment methods into the new Patient Platform Stripe account, use those destination customer ids in the import CSV.
- If Stripe cannot migrate or map payment methods for this account-to-account setup, those customers cannot be imported as `charge_automatically` subscriptions without patient payment setup in PP.

Historical local artifacts from this exploration were removed from the active repo path on 19 June 2026.

Current Stripe account evidence:

- Old Brello Stripe account id: `acct_1OyxbR08au2AZSqr`
- Stripe API account lookup was blocked by the restricted key, but the error returned the account id.
- Source customer list contains 87,863 unique old Stripe customers plus header.

Superseded cross-account path, not the current Brello WC to Brello PP path:

1. Create destination customers in the correct Stripe account for a scoped email batch.
2. Store destination customer ids in Patient Platform.
3. Collect payment details again in the destination account.
4. Create destination products/prices for all importable PP products.
5. Generate sandbox subscription import CSV with destination customer ids, saved-payment-method flag, and destination price ids.
6. Validate 5 to 10 rows in Stripe Billing migration toolkit before any live batch.

## Step 4 Stripe Payment Method Decision - 16 June 2026

Raj asked Stripe whether the migration toolkit can move saved payment methods between old Brello Stripe and destination accounts.

Stripe response summary:

- Payment methods cannot be migrated or copied between separate Stripe accounts.
- Customers must re-save payment details in the destination account.
- Billing Migration Toolkit should only import subscriptions after destination customers have saved payment methods.

Questions answered:

- Do we create destination customers? For Brello WC to Brello PP, no, because it is the same Stripe account. For cross-account cases such as CareLink, yes.
- Where does confirmation about payment method migration come from? Stripe support/migration guidance. Stripe has now confirmed that transfer is not available for our separate-account setup.
- Do we need to mention Billing Migration Toolkit? Yes for later cross-account or CSV-driven migrations, but it is not the primary path for Brello WC to Brello PP.
- Can live and sandbox Stripe objects be migrated between each other? No. Live and sandbox objects are separate. Sandbox is for test customers/prices/import validation only.
- Does the pipeline need an email-list filter? Yes. Step 4 must accept explicit email lists because migration will happen in multiple batches and to multiple destination accounts.
- Does the pipeline need destination routing? Yes. Some users will go to Carelink, some to Allia Health, and some to other accounts. A later level 2 migration from Carelink to another account is also expected.

## Step 4 API Handoff Function - 19 June 2026

Added `migration-phase4-subscription-handoff` as the active Step 4 path for Brello WC to Brello PP.

Request shape:

```json
{
  "emails": ["ecommerce@brellohealth.com"],
  "dry_run": true
}
```

Supported scope inputs:

- `emails`
- `patient_ids`
- `subscription_ids`

Live-write guard:

- Default is dry run.
- Live creation requires `dry_run=false` and `confirm_live=true`.

What it validates:

- Patient exists in the Brello tenant.
- Subscription exists in Supabase.
- Woo/Stripe customer id exists in subscription metadata.
- Stripe customer exists in the same Brello Stripe account.
- Stripe customer has a reusable payment method.
- Product is a PP subscription product with amount and interval, or a price override is supplied.
- Billing anchor is in the future.

What it writes in live mode:

- Creates a Stripe Billing subscription with no proration.
- Uses a supplied Stripe price id when provided.
- Otherwise uses inline Stripe `price_data` from the PP product.
- Stores the Stripe subscription id in `subscriptions.stripe_subscription_id`.
- Upserts `subscription_payment_provider_links`.
- Adds `metadata.migration_phase_4`.

What it does not do yet:

- It does not block WooCommerce renewal. That must be coordinated after PP subscription creation is confirmed.

## Architecture Review Follow-Up - 19 June 2026

New context from the 18 June architecture review:

- Step 4 for Brello WC to Brello PP is confirmed as a same-Stripe-account handoff.
- The Stripe Billing Migration Toolkit is not the active path for this first Brello migration because WooCommerce is not using Stripe Billing subscriptions in the same way Patient Platform will.
- Patient Platform should create its own Stripe Billing subscriptions directly through Stripe APIs, using the existing Stripe customer and saved payment method in the same Brello Stripe account.
- The migration should set the billing anchor from the Woo next-payment date and then coordinate WooCommerce renewal blocking so the patient is not charged twice.
- João confirmed this direction is okay for Brello. Later Brello PP to CareLink PP or other cross-account moves are separate work and may need toolkit/payment re-collection again.

Current Step 3 staging blocker:

- `pubsubProcessor` is configured with `WOOCOMMERCE_MASTER_OBJECT_TOPIC=projects/allia-rt-data-hub-staging/topics/woocommerce-master-object-events`.
- Staging does not currently have the `woocommerce-master-object-events` topic.
- `masterObjectProcessor` was packaged and uploaded for the WooCommerce staging processor, but Cloud Function deploy failed because the trigger topic does not exist.
- Raj tried creating the topic and hit IAM: the active account does not have Pub/Sub topic create permission.
- Next unblock: João, Joana, or Cristóvão creates the topic in `allia-rt-data-hub-staging`, or grants temporary permission for Raj to create Pub/Sub topics. Then deploy `master-object-processor-woocommerce-staging`.

Current Step 4 validation blocker:

- `migration-phase4-subscription-handoff` is deployed to staging.
- Local Deno checks pass.
- Dry-run cannot be called from Raj's current credentials because Secret Manager access to `migration-api-key` is denied.
- Next unblock: grant Secret Manager version access to `migration-api-key`, or have someone with the key run the scoped dry-run request for the five sample accounts.

## Architecture Review Follow-Up - 24 June 2026 (João/Miguel/Raj call)

Raj's framing for the whole call: Steps 1-3 copy data into Supabase in a way that is hard to amend retroactively. The plan is to test Steps 1-4 end to end for at least 2-4 accounts and check data integrity in Patient Platform before calling the pipeline done, not just test each step in isolation.

Step 3 status from this call:

- Code is deployed in staging. Tested so far only with a simulated Pub/Sub trigger (dry run), not yet with a real account flowing through end to end the way Step 1 and Step 2 were tested.
- João confirmed there is no real downside to testing Step 3 against an in-progress production account: worst case is clearing a few Elasticsearch and Supabase entries.
- Step 3 is not structurally blocked. The only blockers are operational:
  - Need a WooCommerce discount coupon (anything other than 100 percent off; Miguel flagged that Stripe/Supabase auto-cancels purchases under 50 cents, so the test purchase needs to land above that) to buy a real product cheaply for the test.
  - Need a company credit card to make that purchase. Alessandra or Mariana may have one; João is following up with them.
  - Test accounts must use "test" in the first or last name so Telegra does not approve/dispatch a real pharmacy order. This needs to be communicated to Telegra explicitly: the order is a test, but it still needs a subscription generated and the order completed end to end.
  - Raj can create these test accounts himself directly against production WooCommerce data; no separate approved-account list is required for this round, though Alessandra/Sara can flag specific accounts later if needed.

Step 4 status from this call:

- Still completely blocked for any account with a real existing subscription. Same-account same-Stripe-account handoff means there is no way to run live without cancelling the customer's current subscription, so a live run is always customer-visible.
- The first cohort identified for migration (internal users) are all free users with no payment method registered, so they cannot validate the live handoff path either.
- Raj hit a "Stripe key expired" error during a dry-run on 24 June. João had rotated/created a new Stripe key for the Brello tenant that day and set it up on staging and production. Action for Raj: confirm via the Patient Platform admin UI (not a direct database write) whether the key configured for Brello on staging is the sandbox or production key. João noted RTDH project secrets are a separate access path from the Patient Platform tenant Stripe key.
- This appears resolved as of 25 June: the Mariana dry-run in the section above resolved the Stripe customer id without hitting a key/auth error, only blocking on missing product mapping.

Cohort scoping decision from this call:

- João asked about the cohort bucket covering cancelled, pending-cancellation, or on-hold orders that also have no active subscription, no valid future renewal date, and no Stripe id (the `unclassified_or_not_in_active_scope` bucket in the cohort pack).
- João's call: ignore this cohort for the first migration pass.
- Action item (João's, not yet done): raise this as a question in Slack/Linear so he can follow up with Ian to confirm. Do not treat this as final until that confirmation comes back.

## Cohort Pack Regenerated With Account-Level Stripe Evidence - 25 June 2026

Following the Step 4 Stripe customer fallback fix above, `brello-backend/scripts/generate-migration-cohort-pack.cjs` was updated to match: a Woo customer counts as having Stripe evidence if any of their subscription rows carries `_stripe_customer_id`, not just the one subscription row selected for cohorting. This mirrors `buildPatientHandoffContexts` in `migration-phase4-subscription-handoff`.

Isolated impact of the Stripe rule alone (holding the cohort reference date fixed for a clean before/after):

- 244 Woo customers had no Stripe id on their selected subscription row but did have one on another subscription row for the same customer.
- Most of those 244 did not move into `ready_to_migrate_now` (+5 only). The larger shifts were out of `waiting_telegra_or_payment_capture` (-89) and into `unclassified_or_not_in_active_scope` (+77) and `missing_or_past_renewal_date` (+8).
- Takeaway: the Stripe fix mostly changes *why* a customer is blocked (status/renewal date instead of missing payment evidence), not whether they are migration-ready today.

The script was also changed to use the live current date instead of a hardcoded `2026-06-24` reference, since cohorts will keep being regenerated after that date.

Not regenerated this pass: `README.md`, `business-user-flow-visuals.md/html`, `stakeholder-summary.txt`, `claude-design-prompt.md`, `next-scoped-test-commands.md`, `remaining-migration-actions.md`. Only the CSVs were rewritten. The markdown/HTML docs in `migration-cohort-pack/` still reflect the old row-level numbers until someone regenerates them too.

Per repo rules, the regenerated PII-bearing CSVs were left uncommitted in `brello-backend/migration-cohort-pack/` for Raj to review before any commit.

## Cohort Decisions Confirmed - 25 June 2026

Two decisions confirmed by Raj following the status update sent to João and Eliano:

- `unclassified_or_not_in_active_scope` cohort (19,778 users as of the 25 June regeneration): **confirmed ignored** for the first migration pass. This was João's open action item from the 24 June call (raise with Ian) — now resolved, no longer pending.
- `100_percent_coupon_internal` cohort (26 users as of the 25 June regeneration, see `brello-backend/migration-cohort-pack/internal-100-percent-coupon-users.csv`): for verified internal accounts, send a $1 Stripe payment link manually and capture their Stripe customer id that way, instead of building dedicated migration code for this small subset. This is a manual ops process, not a Step 4 code change.
- Blocker on the $1-link plan: still need Alessandra's confirmed internal-user list before finalizing who gets a link. The 26-user CSV is the auto-classified candidate list (email/name pattern match) and is provisional until cross-checked against her official list.

## Full Population Staging Run - June 28 to 30 2026

Steps 1 and 2 ran against the full Brello population in staging. Not just test accounts.

Step 1 final numbers:

- 67,761 patients created (target was 67,669; slightly higher due to a daily WC snapshot rollover during the multi-day run)
- 203k+ order stubs
- 84k+ subscription stubs
- 0 user failures

Step 2 final numbers:

- 198,791 orders enriched with product and historical status (97.8% of total)
- 74,847 subscriptions enriched (88.3% of total; remainder is mostly wc-on-hold and wc-pending-cancel which are intentionally skipped)
- 404k weight entries
- 354k medication entries
- 27k symptom entries
- 15k body measurement entries
- All historical order events indexed in Elasticsearch

Bugs found and fixed during UI QA:

- orders.subscription_id was never set by any migration step. Fixed in migration-phase2-import by resolving subscription_id from woo_subscription_id on renewal orders and from subscriptions.metadata.source_billing.woo_parent_order_id for initiating orders. Backfilled via SQL for ~194k already-migrated orders.
- patient_provider_platform_links rows were missing for all migrated patients, so the Provider Platform tab in the admin showed empty even though telegra_id was in patient metadata. Backfilled all 67k patients via SQL. Step 1 code now creates these rows automatically going forward.
- order_status_history was empty for all migrated orders, so the Status History card showed "No status changes recorded yet." Added one entry per order with the current status and WC creation date, note "Migrated from WooCommerce." Full timeline would need wp_woocommerce_order_notes from WC, which is not in the GCS export. Flagged to Joao. Step 1 code now inserts the history entry automatically going forward.

Known remaining gap:

- 166 active subscriptions (0.2% of total) have no order items in the WC GCS export under their subscription ID or parent order ID. Product cannot be resolved for these. Accepted as a known data quality gap from the source. These exist as valid stubs in PP, just without product_id enrichment.

Where things stand:

Code for Steps 0, 1, 2, 3, and 4 is done and deployed to staging. Remaining work is blocked on two decisions:

1. WC billing handoff. WC team needs to agree on how to mark/block WooCommerce subscriptions when Step 4 completes. Ideally a new WC status. A call is needed.
2. Internal users with 100% coupons (about 72 people). Three options: A) send $1 Stripe payment links now; B) migrate without Stripe ID and collect at next renewal; C) create $0 Stripe subscriptions.

Once those two are decided, Step 4 can run.

Open PRs as of 30 June 2026:

| Repo | PR | Description |
| ---- | -- | ----------- |
| rt-data-hub-functions | https://github.com/Allia-Health/rt-data-hub-functions/pull/178 | Subscription renewal linkage + parent-order item fallback for product resolution |
| patient-platform-admin | https://github.com/Allia-Health/patient-platform-admin/pull/189 | Step 1 auto-creates Telegra links and order status history |

## Step 3 Real Order Validation - 26 June 2026

Mariana approved a real subscription for Step 3 testing: order `757687` on subscription `757566`, Woo customer `29323` (`marianamaglioni@gmail.com`). Tested by toggling the real order through several WooCommerce statuses rather than replaying a synthetic Pub/Sub event, since the goal was to validate what will actually run in prod.

Five bugs found and fixed along the way, all in `rt-data-hub-functions` unless noted:

1. **Idempotency keyed only on order id.** WooCommerce's `event_id` is the order id, which never changes, so the order's first status change marked the idempotency record `completed` and silently dropped every later status change for that same order forever. Fixed in `idempotency.js` by appending WooCommerce's own `date_modified_gmt` to the key. `date_modified_gmt` is a top-level payload field, not nested under `raw_payload` — first attempt at this fix used the wrong path and had to be corrected after a redeploy showed no change in behavior.
2. **Webhook suppressed for unlinked WooCommerce orders.** `merger.js`'s `shouldSendMasterObjectWebhook` skipped the outbound Patient Platform webhook whenever `ids.patient_platform_order_id` was missing, on the assumption a later `order.linked` event would carry the accumulated state forward. That assumption holds for Stripe/provider branches but not WooCommerce: an order Step 1/2 never imported has no guaranteed future linking event. Removed the suppression for the `woocommerce.order.updated` branch specifically.
3. **Webhook was never actually configured.** `MASTER_OBJECT_WEBHOOK_URL` and `MASTER_OBJECT_WEBHOOK_SECRET` were unset on both `master-object-processor-staging` and `master-object-processor-woocommerce-staging`. `readWebhookConfig()` silently returns `null` when both are unset, so the webhook send has been a no-op since Step 3 was first deployed — every earlier "validated" run never actually reached Patient Platform. Wired both staging functions to point at `rtdh-webhook`, reusing the existing `migration-api-key` secret (already shared between the two repos for Step 1/2/4 auth) instead of provisioning a new one. `rtdh-webhook`'s `isAuthorized` now falls back to `MIGRATION_API_KEY` when `RTDH_WEBHOOK_SECRET` isn't set.
4. **Wrong webhook URL path.** Configured `MASTER_OBJECT_WEBHOOK_URL` as the bare function root instead of `.../rtdh-webhook/event`. This produced a `404 Route not found`, which looked exactly like a Supabase platform routing failure — went as far as deleting and redeploying the function before checking its own boot logs, which showed it was alive and just had no route for the path being hit. The 404 body is `rtdh-webhook`'s own fallback handler (`index.ts` line ~2349), not a platform error.
5. **`customer.email` mapping bug.** `merger.js`'s `mergeWooCommerceOrderUpdated` read email from `payload.raw_payload.billing.email`, but the real WooCommerce order payload has `billing.email` as a top-level field. Same shape mismatch as bug 1. `rtdh-webhook` rejected the payload with `validation_error: customer.email is required` until this was fixed.

Also found, in `patient-platform-admin`: `rtdh-webhook`'s `DIRECT_STATUS_EVENT_TYPES` allowlist never included `order_cancelled`, so a real cancellation would have been rejected with `unsupported_event_type`. Added it — `order_cancelled` already exists as a status key everywhere else in Patient Platform (`order-lifecycle`, `cleanup-unpaid-helper`, the outbound `rtdh-helper`), it was just missing from this one inbound list. The rest of the WooCommerce status map (`payment_pending`, `payment_collected`, `payment_failed`, `order_sent_to_pharmacy`, `delivered`) was already covered.

Verification path once all fixes were deployed:

- Toggled the real order through `on-hold`, `pending`, `completed`, `cancelled`, `failed` to exercise distinct canonical statuses (each WC status maps to one of a small set of canonical buckets; repeating a bucket hits an unrelated, correct duplicate-status guard that skips the webhook send entirely — not a bug, just not useful for re-testing the same code path twice).
- After the `order_cancelled` allowlist fix, the live order's own next event would have hit the same duplicate-status guard, so confirmed by fetching the order's real master document from Elasticsearch and POSTing it directly to `rtdh-webhook/event` with the real shared secret. Got `200 {"received":true,"eventType":"order_cancelled"}`.
- Every status-change test in this round used real WooCommerce data, approved case by case as it touched a live customer order (`completed`, then `cancelled`, then `failed` each needed separate sign-off — only `on-hold`/`processing` toggles were pre-approved as the safe baseline). `send_to_telegra` was deliberately avoided since it triggers a real pharmacy dispatch.

Net result: the full Step 3 chain — WooCommerce webhook receipt, pubsub-processor, the WooCommerce master object processor, the outbound webhook, and `rtdh-webhook`'s auto-create-on-miss path — now works end to end for a real order Step 1/2 never saw. This was one order for one customer; cohort-scale validation across more order/product types is still open.

Code changes are split across two PRs (RTDH: idempotency + merger fixes, staging webhook config; Patient Platform: auto-create path, event-type allowlist), both off feature branches rather than `staging`/`development` directly.
