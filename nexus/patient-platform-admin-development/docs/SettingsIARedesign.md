# Nexus Settings — Information Architecture Redesign (Proposal)

> Status: **Design proposal + clickable mockups** for review.
> Branch: `elianomarques/settings-ia-redesign`. No backend/schema changes — mockups reuse
> existing UI components with static/placeholder data and live under a parallel route
> namespace (`/tenant-admin/settings-v2/*`) so the current settings stay untouched while
> you click through the new structure.

## 1. Why this redesign

The current tenant settings have grown organically and several concepts overlap or are
misplaced. Concretely (verified against the current code):

| Problem | Where it shows up today |
|---|---|
| **General is a junk drawer** | `settings/General.tsx` bundles Localization, Apps, Health Trackers, Feature Flags, Users (signup domains), Support, Communication — seven unrelated concerns behind one nav item. |
| **Questionnaires are split & mis-homed** | The **Patient** questionnaire is configured under *Integrations → Forms* (Jotform creds) **and** per-provider in `TenantIntegrationSettings.tsx`. The **Medical** questionnaire (which is product- *and* provider-dependent) is buried in *Catalog → Product → Provider Platforms* (`product_provider_platforms.jotform_new_order_questionnaire_id` / `_renewall_`). Two halves of one concept live in three places. |
| **Provider dependency is implicit** | Questionnaires are provider-dependent, but nothing in the IA tells an onboarding admin "configure the provider first, then its questionnaires." |
| **Deployments is a mock with no Domain** | `settings/Deployments.tsx` is static mock data. The tenant **Domain** (custom hostname for the patient app) is not configurable anywhere — `tenants.slug` is the closest thing. Domain belongs in Deployment. |
| **Product Usage Tracking is a top-level nav item** | It is one analytics-config page (`tenant_analytics_settings`) sitting at the same level as General/Branding/Integrations — over-promoted. |
| **No provider→RTDH validation secret** | The secret that validates Telegra → RTDH webhooks lives in Google GCP, with no admin surface. There is no place to view/rotate it per provider. |
| **No outbound integration surface** | The platform can *receive* webhooks (RTDH, Stripe, LifeFile, EasyPost) but there is **no place to mint API keys** (key + secret) or **configure an outbound webhook** to push selected RTDH events to external engines (n8n, Attentive, marketing automation). |
| **"Forms" is the wrong word** | Should be **Questionnaires**, covering both Patient and Medical. |

## 2. Design principles

1. **Onboarding order = menu order.** A new tenant should be able to read the menu top-to-bottom and onboard: identity → look → where it lives → who treats patients → what they fill in → what they sell → how it connects → who can sell → compliance.
2. **One concept, one home.** Questionnaires in one place. Domain with Deployment. API/webhooks together.
3. **Dependencies are explicit.** Providers are configured *before* (and link *into*) Questionnaires; Questionnaires are organized by Provider → Product.
4. **Group, don't flatten.** Sidebar groups give scannable structure without deep nesting.
5. **No regressions.** Every existing field keeps a home; nothing is dropped, only relocated/renamed.

## 3. Proposed menu (grouped sidebar)

The left nav has three zones. The top **Workspace** zone (a CRM-style operational
workspace — Dashboard, **Analytics**, Patients, Subscriptions, Orders) and the **Catalog**
zone (Medications / Products) are **unchanged in content** — only the top group is relabelled
from "Navigation" to **Workspace**. Analytics is an operational view and **stays in Workspace,
not in Settings**. The **Settings** zone is what gets regrouped:

```
SETUP
  General            ← Localization, Orders cancel-window, Allowed States, signup domains (from old "Users")
  Branding           ← unchanged (logos, colors, typography, contact, legal links)
  Domain & Deployment← NEW HOME for Domain (custom hostname, web base URL, app store URLs,
                        QR) + the existing deployment status/version view

CLINICAL
  Providers          ← Telegra / MDI / Zito: credentials, enable/disable, AND the
                        per-provider RTDH validation secret (view/rotate) — NEW surface
  Questionnaires     ← NEW unified home (renamed from "Forms"):
                         · Jotform connection (API key, team workspace, default webhook)
                         · Patient questionnaire  (per provider; Direct | Jotform mode)
                         · Medical questionnaire   (per provider → per product;
                            Direct | Jotform mode; Jotform sets new-order + renewal IDs)

CATALOG
  Products           ← pricing, linked medications, provider enablement + SKUs, and
                        per-STATE provider routing (load-balancing rule sets/allocations)
  Medications        ← form, type, capabilities, MDI offering ID

INTEGRATIONS & DATA
  Connections        ← the rest of today's Integrations: Payment, Email (Resend),
                        Support (Intercom), Push (OneSignal), Shipping (EasyPost),
                        Pharmacy (LifeFile). (Providers + Jotform move out to Clinical.)
  API Keys & Webhooks← NEW: mint API key + secret for external consumers; configure
                        outbound webhook endpoints (n8n / Attentive / etc.) and select
                        which RTDH events to forward; delivery log.
  Usage Tracking     ← old "Product Usage Tracking" (demoted from top level)
  Health Trackers    ← moved out of General (injection sites, activity, mood, symptom)

COMPLIANCE & ACCESS
  Legal              ← Terms & Conditions + Privacy Policy under one item (two tabs)
  Feature Flags      ← moved out of General to its own item
  Admins & Roles     ← unchanged
  Audit Logs         ← unchanged
```

### What moved where (migration map)

| Old location | New location |
|---|---|
| General → Localization, Orders, Allowed States | **General** (kept) |
| General → Users (signup domain allowlist) | **General** (folded into an "Access / Signup" card) |
| General → Apps (web URL, store URLs, QR) | **Domain & Deployment** |
| General → Health Trackers | **Usage & Trackers → Health Trackers** |
| General → Feature Flags | **Feature Flags** (own item) |
| General → Support, Communication | **Connections** (Support/email lives with the other connections) |
| Integrations → Providers (Telegra/MDI/Zito) | **Providers** |
| Integrations → Forms (Jotform) | **Questionnaires** |
| Integrations → Payment/Email/Push/Shipping/Pharmacy/Support | **Connections** |
| Catalog → Product → Provider Platforms → Jotform medical Q IDs | **Questionnaires → Medical** (Product page links here; data model unchanged) |
| Catalog → Product → Provider Platforms → provider enable + SKUs | **Catalog → Products → Providers tab** (stays on the product) |
| Catalog → Product → Provider Platforms → Load balancing (per-state) | **Catalog → Products → State Routing tab** (stays on the product) |
| Catalog → Medication detail (form, type, capabilities, MDI offering ID) | **Catalog → Medications** |
| Deployments (mock) | **Domain & Deployment** (adds Domain + promotion + rollback) |
| Product Usage Tracking | **Usage Tracking** |
| Terms & Conditions + Privacy Policy | **Legal** (two tabs) |
| *(did not exist)* — provider RTDH secret in GCP | **Providers → per-provider "RTDH validation secret"** card |
| *(did not exist)* — outbound events | **API Keys & Webhooks** |

## 4. The three genuinely new surfaces

### 4.1 Domain & Deployment
- **Custom domain**: hostname the patient app is served on (e.g. `app.carelink.com`), DNS/verification status, fallback `*.allia` slug. This is what's missing today.
- Web base URL + iOS/Android store URLs + QR (relocated from General → Apps).
- **Patient UI deployment control (web + mobile)** — the operational surface you asked for:
  - A **promotion pipeline**: `Testing (staging) → Production`, mapping onto the real
    `staging → main` branch-promotion model. A **"Promote to Prod"** button moves the
    Testing build to Production.
  - **Redeploy** the current version per environment, and **roll back** to a previous one.
  - Each environment shows all three surfaces — **Web, iOS, Android** — with their version
    and state (e.g. Live / TestFlight / Internal). Mobile promotion may still gate on store
    review; the UI surfaces that state.
  - A **version history** table with per-version **Redeploy** and **Roll back** actions.

### 4.2 Providers — RTDH validation secret
Each provider card gains a **"RTDH webhook validation"** section showing the secret used to
validate inbound provider→RTDH webhooks (currently GCP-only), with copy + rotate actions and
a "last verified" indicator. (Telegra v2 uses a 64-hex secret with `t=,sha256=` signing — this
surface is where that secret would be owned, per the migration plan.) *Mockup is read-only/placeholder; wiring the actual secret store is a follow-up.*

### 4.3 API Keys & Webhooks
Two tabs:
- **API Keys** — generate named key/secret pairs for external consumers; show key prefix, created/last-used, revoke. (Secret shown once on creation.)
- **Outbound Webhooks** — register an endpoint URL (n8n, Attentive, custom), select which RTDH/order events to forward (checkbox list driven by the event catalog), per-endpoint signing secret, enable/disable, and a recent-delivery log with status codes/retry.

## 5. Questionnaires page — the unified model

```text
Questionnaires
├── Connection          (Jotform API key, team workspace, default webhook URL)
├── Patient             per provider → integration mode [ Jotform | Direct ]
│                         · Jotform → Patient questionnaire form ID + validate + webhook sync
│                         · Direct  → uses the provider's native intake (working today)
└── Medical             grouped by Provider → Product, each row has mode [ Jotform | Direct ]:
        Telegra
          ▸ Semaglutide   [Direct]   uses provider's native questionnaire
          ▸ Tirzepatide   [Direct]   uses provider's native questionnaire
        MD Integrations
          ▸ Semaglutide   [Jotform]  new-order ID [____]  renewal ID [____]
```

### Direct vs. Jotform — the integration-mode decision

The **target** is to standardize every questionnaire through **Jotform**, integrated per
provider + product, so we only ever deal with Jotform. That path is **clean but not fully
working today**. The path that works today is **Direct** to the provider's native
questionnaire (e.g. Telegra).

Rather than force Jotform everywhere (blocks on incomplete work) or lock in Direct (keeps the
messier model), each questionnaire carries an **integration-mode toggle: `Jotform | Direct`**,
mirroring the per-provider pattern already used on the Providers page. **Jotform is the
default/target; Direct is the explicitly-labelled "working today" fallback.** Telegra rows
default to **Direct**; others to **Jotform**. This lets the migration happen **per product** —
flip a product to Jotform the moment that path is ready, and the UI always shows exactly what is
still on the legacy direct path.

This keeps the existing data model (`tenant_integrations` for Jotform/patient-Q,
`product_provider_platforms` for the medical-Q IDs) — it only **re-presents** it in one place,
ordered by the real dependency (provider first, then product), and adds the mode flag. The
Product detail page keeps a read-only link here. *(A `questionnaire_integration_mode` column
would be the follow-up data change.)*

## 5b. Catalog — Products & Medications (restored, with per-state routing)

The Catalog pages were missing from the first preview; they're restored under a **Catalog**
group. The Product detail keeps its tabs (Details, Medications, Providers, Payment) and the
provider-routing capability is made first-class:

- **Providers tab** — enable providers for the product and set their SKUs (Telegra variation
  SKU, MDI handled per-medication offering ID). The medical-Q form IDs that used to live here
  now link out to **Questionnaires**.
- **State Routing tab** — **which provider fulfills an order, by patient state**. Backed by
  the existing `product_provider_platform_load_balancing_rule_sets` / `_states` /
  `_allocations` tables: a **Default** rule for all un-overridden states, plus **per-state
  overrides**, each splitting traffic across the product's enabled providers by **percentage
  allocation** (must total 100%). e.g. Default 50/50, California 80/20, NY+FL 30/70.

## 6. Mockup scope (this branch)

Clickable React mockups under `/tenant-admin/settings-v2/*`, reachable from a temporary
**"Settings (v2 preview)"** entry. The mockup renders **inside the real `AdminLayout`**, so the
actual per-tenant top navigation (Dashboard, Analytics, Patients, Subscriptions, Orders, the
Catalog group, Settings) appears exactly as today; the regrouped settings show as a secondary
sub-nav rail inside the page. It reuses existing primitives with static placeholder data.

A few areas pull from **real** sources so they read true-to-life:
- **Connections** mirrors today's Integrations: real categories
  (`integrationCategoryLabels`/`Descriptions`) and per-provider fields rendered from
  `getIntegrationSettingDefinitions` (Stripe, Resend, Intercom, OneSignal, EasyPost, LifeFile).
- **API Keys** — each key can carry its own **outbound webhook**: *None*, *attach an existing*
  webhook (from the Outbound Webhooks tab), or *define a new standalone one inline* (URL +
  events + secret) that also surfaces in the Webhooks tab.
- **Product → Payment** mirrors the current product payment config: price / payment type /
  interval / interval count / renewal lead days, the Stripe payment-provider assignment, and
  the Stripe coupons + "allow promotion codes" sub-config.

Specifically:

- New grouped sidebar rendering (Setup / Clinical / Integrations & Data / Compliance & Access).
- New pages with real layout + placeholder content: **Domain & Deployment**, **Providers**
  (with RTDH-secret card), **Questionnaires** (Connection/Patient/Medical), **API Keys & Webhooks**.
- Relocated pages shown in their new home (General, Branding, Connections, Usage Tracking,
  Health Trackers, Feature Flags, Legal, Admins, Audit) — reusing existing components where they
  drop in cleanly, or a labelled placeholder where a real port would be follow-up work.

**Out of scope here (follow-ups):** DB migrations, edge functions, real secret storage, real
key minting / webhook delivery, and deleting the old pages. The old settings remain the live
ones until the redesign is approved.

## 7. Suggested rollout (after approval)

1. Land nav grouping + move existing pages into the new structure (no data changes).
2. Build **API Keys & Webhooks** backend (new tables: `tenant_api_keys`, `tenant_outbound_webhooks`, `outbound_webhook_deliveries`) + RTDH event forwarder.
3. Surface the provider **RTDH validation secret** (move ownership from GCP into provider settings).
4. Add real **custom domain** config + verification.
5. Port the **Questionnaires** unified page to live data; make Product page read-only link.
   *(Partially done — see Part 5: the **Patient** tab is now real; **Medical** is still pending.)*
6. Remove the v2 namespace; retire old routes.

## 8. Prototype updates from Settings V2 review pass

The `/tenant-admin/settings-v2/*` mockup was expanded after review with the following UI
changes. These remain prototype-only unless called out in a later implementation ticket.

### Catalog / Products

- Product **Details** now includes patient-facing **description** and **product image** controls.
- Settings V2 image controls use a mock upload pattern with preview and remove actions, rather
  than URL-only fields.
- Product **Medications** now has mock controls to **add** a medication to a product and
  **delete** linked medications.
- Product detail now includes a **FAQs** tab, with mock add/edit/delete controls, question,
  answer, and display-order fields.
- Product detail keeps provider enablement/SKUs, payment configuration, coupons, and state
  routing as separate tabs.

### Catalog / Medications

- Medication rows now include an **Open** action to reach a medication details screen.
- The medication list does not expose MDI identifiers inline; **MDI Offering ID** is managed on
  the medication details screen.
- The **New medication** action opens the same medication editor pattern used by the detail
  screen.
- Medication add/edit screens now include a **Medication capabilities** manager with selectable
  patient-tracking capabilities: Weight Tracker, Body Measurement, Shot Counter, Pill Counter,
  Energy Tracker, Mood Tracker, and Symptoms Tracker.
- Medication add/edit screens now include a **medication image** upload control.

### Legal

- Legal now has a third **Products** tab.
- Product terms are edited per existing product; there is no "add product terms" action because
  every available product can have its own terms.
- The Products tab shows product rows, published/draft status, version metadata, edit actions,
  and a selected-product terms editor mock.

### Health Trackers

- Health Trackers now mirrors the working settings behavior more closely:
  - Injection sites can be added with a name and image upload.
  - Activities, moods, and symptoms can be added from inline controls.
  - Configured items can be removed.
- Each configured item list is collapsible.
- All Health Tracker item panels open collapsed by default.

### Integrations & Data

- **Analytics is NOT in Settings.** It is an operational/CRM view and lives in the top
  **Workspace** nav group alongside Dashboard / Patients / Subscriptions / Orders, where it is
  today. (An earlier iteration surfaced it under Integrations & Data; that was removed —
  Settings is for configuration, not operational dashboards.) The `Analytics` page split into
  reusable content + `AdminLayout` wrapper is still useful and can stay.
- **Connections → Customer Support** now exposes the Intercom settings from the shared
  integration config, including **App ID** and **Help Center URL**.

### Branding

- Branding now includes a **Tenant logo** control.
- Tenant logo uses the same mock upload/preview/remove pattern as the other Settings V2 image
  controls and represents the logo shown in the app header and login screen.

---

# Part 2 — Platform Admin (Superadmin) IA Redesign

> Status: **design proposal + clickable mockup** for review.
> Mockup namespace: `/platform-superadmin/v2/*` (reuses the real
> `AdminLayout variant="platform"`; static placeholder data; no backend/schema
> changes). The current platform-admin pages stay live until adopted.

## P1. Why

The platform-admin nav is a flat list of 10 items mixing three different jobs:
operational management (Dashboard, Tenants, Admins), catalog/workflow definitions
(Product Categories, Medication Capabilities, Order Statuses), and platform
configuration (Integrations, Feature Flags, Settings/RTDH) — plus Audit Logs. Same
problem as the tenant side: no grouping, related things scattered, "Settings" is just
the RTDH credentials page rather than a home for configuration.

We apply the **same logic** used for the tenant redesign: a top **Workspace**
(operational) zone, then grouped configuration zones. **Every existing page,
field and action is migrated — nothing is dropped.**

## P2. Proposed grouped nav

```text
WORKSPACE                      (operational — what a superadmin runs)
  Dashboard          ← stat cards + PHI-restriction notice (unchanged)
  Tenants            ← tenant table, create, edit details/branding, activate
                       /deactivate, logo + brand colors, + Tenant detail (unchanged)
  Admins & Roles     ← Superadmins / All Users tabs, create admin, role assignment

PLATFORM CATALOG               (definitions tenants build on)
  Product Categories ← name/key/description/order, active toggle, CRUD
  Medication Capabilities ← name/key/description/order, active toggle, CRUD
  Order Statuses     ← status-flow viz, admin/patient labels, transitions,
                       next-step owner, terminal flag, active toggle, edit

INTEGRATIONS & DATA            (platform configuration)
  Integrations       ← provider/connection registry, platform-level enable,
                       per-tenant enablement, provider logo manager
  Feature Flags      ← flag registry, active toggle, per-tenant override manager
  RTDH & Platform Settings ← the current "Settings" page: RTDH API URL,
                       access token, consumer secret (renamed for clarity)

GOVERNANCE & ACCESS
  Audit Logs         ← platform-scope audit table (unchanged)
```

### Migration map (old → new)

| Old platform nav item | New home |
|---|---|
| Dashboard | **Workspace → Dashboard** |
| Tenants (+ detail) | **Workspace → Tenants** |
| Admins & Roles | **Workspace → Admins & Roles** |
| Product Categories | **Platform Catalog → Product Categories** |
| Medication Capabilities | **Platform Catalog → Medication Capabilities** |
| Order Statuses | **Platform Catalog → Order Statuses** |
| Integrations | **Integrations & Data → Integrations** |
| Feature Flags | **Integrations & Data → Feature Flags** |
| Settings (RTDH) | **Integrations & Data → RTDH & Platform Settings** (renamed) |
| Audit Logs | **Governance & Access → Audit Logs** |

No page is removed; the only rename is **Settings → "RTDH & Platform Settings"** so
the label describes its contents (and leaves room for future platform-wide settings).

## P3. Rationale (same principles as the tenant side)

1. **Workspace = operate, Settings groups = configure.** Dashboard/Tenants/Admins are
   the operational CRM-style zone; everything else is configuration grouped by kind.
2. **One concept, one home.** Catalog definitions (categories, capabilities, order
   statuses) sit together; platform configuration (integrations, flags, RTDH) sits
   together.
3. **Onboarding-ordered.** A new superadmin reads top-to-bottom: see the platform →
   manage tenants/admins → define the catalog tenants build on → wire integrations →
   govern.
4. **No regressions.** Faithful migration of every field/action; old routes remain
   until the redesign is adopted.

## P4. Mockup scope

Clickable mockups under `/platform-superadmin/v2/*` reachable from a temporary
**"⚡ Platform v2 (preview)"** entry, rendering inside the real
`AdminLayout variant="platform"` with a grouped secondary sub-nav (same pattern as the
tenant Settings v2). Pages reuse existing primitives with representative static data,
faithfully reproducing each current page's sections/fields/actions.

Out of scope (follow-ups): backend/schema changes, deleting old pages, real CRUD wiring.

---

# Part 3 — Migration plan (mockup → real, no feature left behind)

> Status: **in progress.** We are converting the v2 IA from a mockup into the
> real, working UI. Governing rule: **bring the exact existing capability across,
> never re-implement it, never drop it.**

## M1. Strategy — reuse real components in place

We do **not** rewrite pages. Each existing page already wraps itself in
`<AdminLayout> + <PageHeader>`. To re-home it without nesting layouts or losing
behavior, we apply a mechanical **Content/wrapper split** (the same pattern the
team already used for `TenantAnalyticsContent`):

1. Extract the page body (all hooks, queries, mutations, tables, dialogs) into a
   `XxxContent` component that renders **no** `AdminLayout`.
2. The page's **default export** keeps wrapping `XxxContent` in `AdminLayout` so the
   **old route keeps working unchanged**.
3. The v2 grouped IA renders `<XxxContent />` directly.

Because v2 renders the *same* component body with the *same* hooks and data, the
feature is preserved by construction — there is nothing new to re-test beyond "does
it render in the new spot."

## M2. Cutover — alongside, per group

- The new grouped nav becomes the default entry, but **old routes stay alive**
  (eventually as redirects) until each group is verified.
- We cut over **one group at a time**, verify against the old page, then move on.
- This lets us compare old vs new and roll back per area.

## M3. Orphans — nothing disappears

Any feature without an agreed new home renders under a visible **"Unsorted / To
place"** group at the bottom of the v2 nav, so it stays reachable and you decide
where it belongs. Nothing is hidden or deleted.

## M4. Per-page migration checklist (apply to every page)

- [ ] Split into `XxxContent` (no layout) + default wrapper (keeps old route).
- [ ] v2 group item renders the real `XxxContent`.
- [ ] Old route still resolves (no 404, same behavior).
- [ ] Every sub-section / field / action present in the old page is present in the
      new home — or explicitly parked in **Unsorted** with a note.
- [ ] `vite build` passes.
- [ ] Eyeball in the running app: real data loads, dialogs open, mutations work.

## M5. Order of migration

1. **Admins & Roles** (pilot — self-contained real page + dialogs).
2. Compliance & Access rest (Audit Logs, Legal).
3. Branding, then the General junk-drawer split (uses Unsorted heavily).
4. Integrations → Connections / Providers / Questionnaires.
5. Catalog, Domain & Deployment, API Keys & Webhooks (these have net-new surfaces
   that remain mockups until their backend lands — clearly flagged).

Net-new capabilities (custom domain, deployment promote/rollback, per-provider RTDH
secret, API keys, outbound webhooks) have **no existing implementation**, so they
stay as flagged mockups until their backend is built — they are additive and break
nothing.

---

# Part 4 — Cutover, Coming-soon, Product types, Webhooks

This phase made the regrouped IA the **real** application navigation and added the
remaining functional pieces.

## P4.1 Cutover (regrouped nav is now the real sidebar)

- `src/lib/nav-config.tsx` is the single source of truth for the grouped nav
  (tenant + platform); it is layout-free to avoid a circular import with
  `AdminLayout`.
- `AdminLayout` renders a **data-driven grouped sidebar** for both variants, so
  every page — including the dashboards — shows the new IA.
- Routes are now **canonical** (the `v2` URLs are gone):
  - Tenant settings own `/tenant-admin/settings/*`.
  - Platform admin owns `/platform-superadmin/*`.
  - Old flat URLs whose slug changed redirect to their new home
    (integrations/payment-providers -> connections, deployments -> domain,
    product-usage-tracking -> usage-tracking, terms/privacy -> legal). Unchanged
    slugs are served by the grouped shell.
  - Legacy /settings-v2 and /platform-superadmin/v2 redirect to the canonical
    base, so old links/bookmarks keep working.

## P4.2 Coming-soon treatment

`src/components/common/ComingSoon.tsx` (+ `ComingSoonBadge`) is the reusable,
grayed-out placeholder for capabilities whose core functionality isn't built yet.
Applied to: API Keys and Data API tabs; and non-available product types (Fitness,
Labs) render as dimmed (opacity-50 grayscale, non-interactive) tiles.

## P4.3 Product types

`src/pages/tenant-admin/settings-v2/ProductsHome.tsx` introduces multiple product
types: Medication (available today -> renders real ProductsContent) plus Fitness
and Labs as grayed Coming-soon tiles. Extensible via the PRODUCT_TYPES array.

> **Superseded by Part 6** — product types are now data-driven (the global
> `product_types` table) with per-tenant activation, not a hardcoded array.

## P4.4 Webhooks (real) + API/Data API (coming soon)

Webhooks are a complete, working feature — see docs/OutboundWebhooksAPI.md for the
data model, dispatcher, event catalog, security and tests. API Keys and the Data
API are Coming soon (no backend yet); additive, break nothing.

## P4.5 Migration pattern recap

Every real page was re-homed via the Content/wrapper split (Part 3 M1): a
XxxContent body (no layout) reused by the grouped IA, plus a default export that
keeps the old route working. No page was re-implemented; functionality is
preserved by construction.

---

# Part 5 — Questionnaires Patient tab is now real (cutover)

This phase delivered rollout step 5 for the **Patient** questionnaire (Medical
still pending) and finished separating the two provider concerns.

## P5.1 What changed

- The **Patient Questionnaire Definition** (the Direct-path JSON) and its **Patient
  Questionnaire Jotform ID** (the Jotform path) **moved out of the Providers page**
  and into **Settings → Questionnaires → Patient**, replacing that tab's mockup with
  the real, persisted editor. The Questionnaires page now defaults to the Patient tab.
- The **Providers page** now owns only the provider's two connection flows:
  **send** (API credentials to push orders/requests) and **receive** (the RTDH
  webhook-validation secret). It no longer shows any questionnaire field.

## P5.2 How (no new component, no data change)

`TenantIntegrationSettings` gained a **`facet`** prop
(`"all" | "credentials" | "questionnaire"`) that scopes which subset of a
`provider_platform` integration's settings a page renders and saves:

- Providers → `facet="credentials"` (everything except the questionnaire definition;
  hides the questionnaire's Jotform-ID block, the provider enable toggle, and the
  creds "Configured" badge are owned here).
- Questionnaires → Patient → `facet="questionnaire"` (only the Patient Questionnaire
  Definition + its Jotform-ID/webhook block).
- Legacy `/settings/Integrations` → `facet="all"` (unchanged).

`handleStartEdit`/`handleSaveSettings`/required-validation all operate on the
facet-visible subset, and the save merges over `...tenantIntegration.settings`, so a
save from one facet **never drops the other facet's keys**. Storage is identical:
the same `tenant_integrations.settings.patient_questionnaire_definition` /
`patient_questionnaire_form_id` keys the bridge already reads. Two paths, same as
before: a Jotform ID present → Jotform path; absent → Direct (provider-native).

## P5.3 Medical tab — now real (follow-up landed)

The **Medical** questionnaire tab is now wired to real data (completing rollout
step 5). The medical-questionnaire UI (Direct | Jotform mode + new-order/renewal
Jotform IDs + validation + webhook controls) was extracted into a shared
`ProductMedicalQuestionnaires` / `ProviderMedicalQuestionnaire`
(`src/components/features/ProductMedicalQuestionnaires.tsx`) used in BOTH places:

- **Settings → Questionnaires → Medical** — one card per product, listing each
  enabled provider with its mode toggle + form IDs.
- **Catalog → Product → Provider Platforms** — the same component (single source of
  truth; the old inline block was removed).

Persistence unchanged: `product_provider_platforms.integration_mode` /
`jotform_new_order_questionnaire_id` / `jotform_renewall_questionnaire_id` via
`saveProviderPlatformSku`. Direct (native) vs Jotform preserved per provider×product.

---

# Part 6 — Catalog product TYPES (two levels) + per-tenant activation

The Catalog now distinguishes two levels and activates the top level per tenant.

## P6.1 Two levels: types vs categories

- **Product type (line)** — the top level: **Medications, Labs, Fitness,
  Wearables, Experiences**. Stored in the new global **`product_types`** table
  (`key`, `name`, `description`, `availability` = `available | coming_soon`,
  `display_order`, `is_active`). Superadmin-managed, authed-read (same governance as
  `product_categories`).
- **Category (sub-tag)** — the existing **`product_categories`** rows (Weight Loss,
  Energy, Longevity, …) are now **sub-tags under a type**: a nullable
  `product_categories.product_type_id` FK was added and existing rows backfilled to
  **Medications**. Per-product category assignment (`product_category_assignments`,
  `ProductCategoriesManager`) is unchanged.

> Earlier (P4.3) the hardcoded `PRODUCT_TYPES` array and a brief reuse of
> `product_categories` as ad-hoc "types" are both superseded by this model.

## P6.2 Per-tenant activation

New **`tenant_product_types`** (`tenant_id`, `product_type_id`, `is_enabled`,
UNIQUE(tenant_id, product_type_id)), RLS-scoped to the tenant's own admins. Every
existing tenant is seeded with **Medications enabled** so today's behavior is
preserved. The hook `useTenantProductTypes` joins the global types with this tenant's
enablement and exposes a `setTypeEnabled` toggle. Migration:
`supabase/migrations/20260624140000_create_product_types_and_tenant_activation.sql`.

## P6.3 UI

- **`ProductsHome`** renders the types as tiles: **available** types (Medications)
  show an enable toggle and, when enabled, open the real `ProductsContent`;
  **coming_soon** types (Labs, Fitness, Wearables, Experiences) render greyed with a
  "Coming soon" badge.
- **Sidebar**: Catalog → **Products** is now **expandable**; the tenant's enabled
  available types render as children (Medications → the real medications list).
  `Medications` is no longer a separate top-level Catalog nav item.

---

# Part 7 — Explicit questionnaire mode + catalog CRUD completeness

Refines the questionnaire UIs (the inferred mode was confusing) and closes CRUD gaps.

## P7.1 Patient questionnaire — explicit Direct | Jotform

`src/components/features/PatientQuestionnaires.tsx` replaces the old facet-scoped
`TenantIntegrationSettings` on the Patient tab. Per enabled provider it shows an
**explicit Direct | Jotform toggle** persisted to
`tenant_integrations.settings.patient_questionnaire_mode` (`'direct' | 'jotform'`):

- **Direct** → the **Patient Questionnaire Definition** JSON editor (save validates a
  JSON object; **Clear** removes it). Stored at `settings.patient_questionnaire_definition`.
- **Jotform** → the **Patient Questionnaire Jotform ID** + webhook status (save
  validates via the bridge; **Clear** removes it). Stored at
  `settings.patient_questionnaire_form_id`.

Switching modes never deletes the other field's stored value — it only changes which
path is active. Backed by the new `useProviderIntegrations` hook (enabled
provider-platform list + a settings-patch mutation). The Jotform validation call was
extracted to `src/lib/jotform-validation.ts` and is shared by the patient + medical
editors. `patient_questionnaire_mode` is now **honored end-to-end** by the
provider-platform-bridge (see ProviderPlatformBridgeAPI §`GET
/get-patient-questionnaire`): `direct` forces the native questionnaire, `jotform`
serves the embed, and an **unset** flag falls back to the legacy "has form id?"
inference (so existing tenants are unaffected). The medical equivalent
(`product_provider_platforms.integration_mode`) is honored the same way.

## P7.2 Medical questionnaire — per-product provider table

`ProductMedicalQuestionnaires` now renders a **table** per product: one row per
enabled provider with columns Provider | Integration (Direct|Jotform toggle) |
New-order form ID | Renewal form ID | actions (save + **Clear**). The Catalog →
Product card editor (`ProviderMedicalQuestionnaire`) keeps its card layout and also
gained a **Clear**. Both write the same `product_provider_platforms` columns via
`saveProviderPlatformSku` (single source of persistence).

## P7.3 CRUD completeness

Every catalog/questionnaire config can now be created/edited AND cleared/removed:

- **Medications list** gained **Edit** + **Delete** (confirm dialog) row actions,
  matching Products.
- Patient + Medical questionnaire fields gained **Clear**.
- Product **types** remain per-tenant **enable/disable** (the global type definitions
  are managed in Platform Admin → Product Categories, which has full CRUD).

---

# Part 8 — Platform Admin manages Product Types; robust per-tenant defaults

## P8.1 Platform Admin → "Product Catalog"

The platform-admin **Product Categories** page is now **Product Catalog** and has two
sections:

- **Product Types** (primary) — full CRUD on the global `product_types` table via the
  new `ProductTypesManager` (`src/components/features/ProductTypesManager.tsx`),
  including the **availability** select (`available | coming_soon`), `is_active`
  toggle, and display order. Modeled on the Medication Capabilities CRUD.
- **Sub-categories** (secondary) — the existing legacy `product_categories`
  (Weight Loss, Energy, …) used to tag products within a type, kept editable.

## P8.2 Robust per-tenant defaults (Medications shows everywhere)

Two changes ensure every tenant — including new ones like brello and any DB where the
original backfill didn't fully apply — sees Medications:

- Migration `20260624160000_seed_product_types_and_enable_medications_all_tenants.sql`
  idempotently re-seeds the five `product_types` and (re)enables the **Medications**
  line for **all** tenants.
- `useTenantProductTypes` now treats an **available** type as **enabled by default**
  when a tenant has no explicit `tenant_product_types` row (an explicit row still wins,
  so admins can opt out). This makes the catalog correct even before/without the
  backfill and for tenants created later.

---

# Part 9 — Medications as sellable products; routing/questionnaires only for med products

Clarifies the catalog model (no data-model or RTDH change):

- The **sellable unit is the Product**. A product can be a **single medication** or a
  **bundle** of medications (and, in future, non-medication items like a fitness
  program). %-per-state provider routing + medical/patient questionnaires remain
  **product-level** and RTDH-wired — unchanged.
- **Every medication can be sold as a product.** The Medications list (and detail) now
  has a per-row **"Create product"** action that creates a 1:1 single-medication product
  (reusing the standard product create + payment-provider sync) and links the medication
  via `product_medications`; if a product already contains the medication the action
  becomes **"Open product."** Bundles are still built on the Products page by adding
  multiple medications.
- **Routing + questionnaires only apply to products that contain medications.** On the
  Product detail page the **Provider Platforms** tab (routing % + medical questionnaire)
  is **hidden when the product has no linked medications**; Questionnaires → Medical only
  lists products that have ≥1 medication. Non-medication products (e.g. fitness) never
  show provider routing/questionnaire config.

No changes to `product_provider_platforms`, the load-balancing tables, the
select-provider-platform function, or the order-creation/RTDH path.

---

# Part 10 — Providers = IDs + Routing; questionnaires only on Questionnaires tab

Reorganizes catalog provider config for clarity (no backend/RTDH/data change — same
underlying fields):

- **Catalog nav**: **Medications** is again a **top-level Catalog item, sibling of
  Products** (you add medications first, then build products from them). The previous
  "Products expands to its product types" sidebar tree was removed.
- **Product → "Providers"** (renamed from "Provider Platforms"): now has two sub-tabs
  with the enable toggle inline:
  - **IDs** — per-provider identifiers (Telegra variation/product SKU, MDI medication
    offering IDs) + the enable toggle.
  - **Routing** — per-state % load-balancing rule sets (unchanged logic).
  - The medical-questionnaire block was **removed** from this page.
- **Questionnaires → Medical** is now the **only** home for medical questionnaire
  config. It lists products (with ≥1 medication) and, per routed provider, a
  **Direct | Jotform** toggle (default **Direct**):
  - **Jotform** → editable new-order + renewal form IDs (+ validate/clear).
  - **Direct** → explains it uses the provider's native questionnaire off the product's
    provider IDs, with a **link to Product → Providers → IDs** to view/edit them (no
    dead "nothing to edit" state).
- `ProductDetail` honors a `?tab=` query param so the link opens the Providers tab.

Provider routing stays product-level and RTDH-wired; the bridge reads the same fields
(`integration_mode`, jotform IDs, offering_id, variation_sku) — only the admin-UI
location of each control changed.
