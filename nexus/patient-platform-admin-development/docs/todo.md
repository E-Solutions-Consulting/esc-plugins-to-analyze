# Catalog / Questionnaires — outstanding items (discussed, not yet built)

Tracking list of items raised during the catalog + questionnaires work (PRs
#128–#144). These were discussed and deliberately **deferred** — captured here so
they're not lost. None are in progress.

Context for the model as shipped: the sellable unit is a **Product**; a product can be
a single medication or a **bundle of medications** (`product_medications`). Per-state %
provider **routing** and **medical/patient questionnaires** are **product-level** and
RTDH-wired. Product **types** (Medications / Labs / Fitness / Wearables / Experiences)
are global with per-tenant activation; only **Medications** is available today.

## 1. Cross-category / cross-type product bundles
A product can currently link **only medications** (`product_medications`). There is no
schema for non-medication items, so a product cannot bundle across types (e.g. a
**fitness program + NAD+ + Semaglutide**). Needs a generic multi-item model (e.g. a
`product_items` table spanning medications + other item types) + UI. (Previously
scoped; on hold.)

## 2. Create-product modal with up-front item selection
The "New Product" dialog only captures name / SKU / description / price; items are added
**after** on the product's Medications tab. Desired: select the items (medications now,
other item types later) **in the create modal**, building a bundle in one step (the way
CareLink bundles read today).

## 3. Non-medication product types — backend
Labs / Fitness / Wearables / Experiences exist only as `product_types` tiles
(`availability = coming_soon`). No items, ordering, routing, or questionnaire behavior
behind them. Note: non-medication products should **not** require provider %-routing or
questionnaires — only products that contain medications do.

## 4. Patient-questionnaire submission → RTDH (Telegra path)
Docs note `patient_questionnaire_submitted` is implemented for **MDI only**. Telegra
patient-questionnaire submissions flow via the Jotform webhook separately and are not
represented in the RTDH event schema. Confirm the Telegra path end-to-end and close any
gap.

## 5. Telegra v2 downstream remap + Nexus secret migration (rt-data-hub-functions)
Pre-existing backend follow-ups:
- `config/data-extraction.yaml` `telegramd` block still maps v1 nested paths → rewrite to
  flat v2 `snake_case` fields.
- Revisit the `new_status_set_to_request` ignore-guard for v2 (`order_status` based).
- Move the Telegra webhook signing secret from **GCP Secret Manager** into per-tenant
  **Nexus provider settings** (the admin surface exists; the receiver reading the
  per-tenant secret is the remaining half).

## 6. Regenerate Supabase types
`product_types` / `tenant_product_types` are accessed via the
`as "medication_capabilities"` cast because `src/integrations/supabase/types.ts` was not
regenerated after the migration. Regenerate the types and drop the casts.

## 7. Stabilize flaky edge-function test
`edge-function-tests` → `triggerRtdhCreateOrder includes canonical patient_id` has
intermittently failed on `development`. Investigate and stabilize.

## 8. Bundle discoverability / labeling
Bundling today = "add multiple medications to a product" with no explicit "bundle"
affordance or label. Consider clearer UX (e.g. a labeled bundle flow) once cross-type
bundling (#1) lands.
