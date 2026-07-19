# Communications Automations — Architecture & PRD

**Status:** In development · feature branch `elianomarques/comms-automations`
**Repo:** `patient-platform-admin`
**Author:** Eliano Marques (CTO) + automated implementation
**Date:** 2026-06-23

---

## 1. Goal

Give each tenant a no-code **Communications Automations** builder — an Attentive / Customer.io-style
journey canvas — to drive **email and SMS** campaigns off our own first-party data:

- **Event triggers** — any `analytics_events` event (e.g. `checkout_completed`, `product_viewed`).
- **Subscription lifecycle triggers** — `subscription_events` (`created`, `cancelled`, `paused`, `resumed`)
  and **relative-time** triggers computed from `subscriptions` (`X days before renewal`,
  `X days after purchase`).
- **Order triggers** — order status transitions (`shipped`, `delivered`, …).

Messages personalise with **placeholders** drawn from the patient / subscription / order / event records.

We do **not** reinvent a general workflow engine. For anything past the initial trigger and a few
native steps, we hand off to **n8n** — our own automation tool — with **one n8n project per tenant**
(n8n Enterprise *Projects*). The user designs the trigger natively here, then designs the rest of the
flow in n8n, and can **visualise the n8n flow** inside our UI.

---

## 2. Why this shape

- **Reuse, don't rebuild.** Email already works via `_shared/email-distribution.ts`
  (`sendEmailViaTenantDistribution`, per-tenant Resend). We extend it; we don't replace it.
- **First-party data already exists.** `analytics_events`, `subscription_events`, `subscriptions`,
  `orders`, `patients` are all tenant-scoped with RLS. We trigger and personalise off them directly.
- **n8n is our escape hatch.** Branching, external API calls, Slack, complex fan-out — all live in n8n.
  We own the trigger + the first native messaging steps; n8n owns the long tail.

---

## 3. n8n reality check & the graceful-degradation decision

The infra audit found our self-hosted n8n (GKE `allia-ai-n8n-cluster`,
`n8n-dev.alliahealth.co` / webhooks at `n8n-dev-webhooks.alliahealth.co`) is currently
**Community Edition** — `N8N_LICENSE` / `n8n_multi_main_enabled` are **not set**, so the
**Projects** Enterprise feature is **not active yet**.

**Decision:** Build to the **Enterprise Projects API contract** (one project per tenant, scoped API
key, project-scoped workflow/webhook listing) behind a server-side capability flag
`N8N_PROJECTS_ENABLED`. When the license is **absent**, the same integration **degrades to
webhook-mode**:

| Capability | Enterprise Projects (licensed) | Webhook-mode fallback (today) |
|---|---|---|
| Per-tenant isolation | n8n Project per tenant | Tenant-tagged workflows; we enforce mapping in our DB |
| Provisioning | `POST /api/v1/projects` + scoped API key | Manual / shared API key; we store webhook URL per tenant |
| Pick a flow | List project workflows via API | User pastes/selects a registered webhook URL |
| Visualise flow | Fetch workflow graph via API → render | Same if API key present; else show registered metadata only |
| Trigger | Project webhook or `POST /workflows/:id/execute` | `POST` to the registered webhook URL (HMAC-signed) |

This ships value **now** and "lights up" full multi-tenancy when the license lands — no rewrite,
only flipping `N8N_PROJECTS_ENABLED=true` and provisioning projects.

---

## 4. Data model (new tables, all tenant-scoped + RLS)

Prefix: `comms_`. RLS mirrors existing pattern:
`tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))`.

| Table | Purpose |
|---|---|
| `comms_automations` | One automation (a.k.a. "journey"/"recipe"). Name, status (draft/active/paused/archived), trigger config (JSONB), versioning. |
| `comms_automation_nodes` | Canvas nodes: `trigger`, `email`, `sms`, `delay`, `wait_until`, `branch`, `n8n`, `exit`. Position + config JSONB. |
| `comms_automation_edges` | Directed edges between nodes (with optional branch label: `true`/`false`/cohort key). |
| `comms_templates` | Reusable email/SMS message templates with placeholder bodies (per tenant). |
| `comms_enrollments` | A patient's live run through an automation (current node, status, context snapshot JSONB). |
| `comms_run_steps` | Per-node execution log (sent/skipped/failed, provider message id, error). |
| `comms_scheduled_jobs` | Due-time queue for delays & relative-time triggers (claimed by the scheduler tick). |
| `comms_n8n_projects` | Tenant ↔ n8n project mapping: `n8n_project_id`, scoped `api_key_secret_ref`, base URL, mode (`projects`/`webhook`). |
| `comms_n8n_webhooks` | Registered n8n webhooks selectable as an `n8n` node target (url, method, auth header ref, cached graph JSON). |

The **trigger catalog** (event names, subscription/order lifecycle, relative-time templates) is
derived/served from `analytics_event_types` + a small static catalog in code — not a new table.

### Placeholder namespaces (grounded in real columns)
`patient.*` (first_name, last_name, email, phone, city, state, postal_code, country) ·
`subscription.*` (status, current_period_end_at → `renewal_date`, started_at, days_until_renewal, days_since_start) ·
`order.*` (order_number, status [patient label], status_key, tracking_number, tracking_url, total_usd, days_since_order) ·
`product.*` (name, sku) · `event.*` (event_name, properties.*) · `tenant.*` (name, slug).

**THE RULE:** a placeholder exists only if `resolveContext` (comms-event-dispatcher) /
`enrichContext` (_shared/comms-automations.ts) actually delivers it. The single source is
`PLACEHOLDER_GROUPS` + `TRIGGER_CONTEXT_NAMESPACES` in `src/lib/comms-automations/catalog.ts` —
the same catalog drives template placeholders AND the builder's "Payload this trigger delivers"
reference on the trigger node. Change `resolveContext` and the catalog together.

**Schema gotchas that bit us (2026-07):** the patient address is `patients.shipping_city/
shipping_state/shipping_postal_code` (mapped to the stable `patient.city/state/postal_code`
keys), and order status is `orders.status_id → order_statuses` (embed
`order_statuses!orders_status_id_fkey`, flatten to `status` = patient_status_label +
`status_key`). Selecting phantom columns 42703s and — because supabase-js errors were
swallowed — silently dropped whole context blocks. All context lookups now log errors.

### What each trigger kind delivers (context payload)
This is what an n8n node receives under `context` and what placeholders can read:

| Trigger kind | Always | When the trigger carries the entity |
|---|---|---|
| `event` (analytics) | `event`, `patient`, `tenant` | `product` |
| `order` | `order`, `patient`, `tenant` | `product`, `subscription` |
| `subscription` | `subscription`, `patient`, `tenant` | `product` |
| `relative_time` | `patient`, `tenant` | `subscription`, `order`, `product` |

---

## 5. Backend (Supabase Edge Functions — `verify_jwt = false`, manual auth)

| Function | Role |
|---|---|
| `comms-automation-admin` | CRUD for automations/nodes/edges/templates. JWT + tenant-membership verified (existing pattern). Validates the graph. |
| `comms-event-dispatcher` | Receives a domain event (from analytics ingestion / subscription_events / order transitions), matches active automation triggers for the tenant, creates enrollments. |
| `comms-scheduler` | Cron-invoked tick. Claims due `comms_scheduled_jobs` (delays + relative-time triggers like "3 days before renewal"), advances enrollments, executes the next node. |
| `comms-execute-node` | Executes a single node: email (reuse `sendEmailViaTenantDistribution`), SMS (Twilio via tenant integration), delay (reschedule), branch (evaluate condition), n8n (call project/webhook), exit. |
| `comms-n8n-proxy` | Server-side proxy to the tenant's n8n project: provision project, list workflows/webhooks, fetch a workflow graph (for visualisation), trigger a webhook (HMAC-signed). Capability-flagged. |

The **engine** is a small state machine: an enrollment sits at a node; `comms-execute-node` runs it,
writes a `comms_run_steps` row, then either advances to the next edge immediately or parks in
`comms_scheduled_jobs` (for delays / wait-until). Idempotency via a per-(enrollment,node) key.

SMS reuses the tenant-integration pattern (`tenant_integrations.integration_key = 'twilio'`,
settings `{ account_sid, auth_token, from_number }`), mirroring how Resend is stored.

---

## 6. Frontend

- **Workspace nav:** new **Automations** item (`/tenant-admin/automations`) — primary surface, sibling
  to Subscriptions/Orders. (The existing **Settings → Communications** page keeps provider config:
  Resend/Twilio keys, n8n connection.)
- **List page:** automations table (name, trigger, status, enrolled count, last run) + "New automation".
- **Builder page** (`/tenant-admin/automations/:id`): a canvas mirroring the reference UI —
  Trigger at top, a vertical flow of node cards (Time Delay, Email, SMS, Branch, n8n, Exit), a left
  **Build** palette, a right inspector panel per selected node.
- **Node types:** Email, SMS, Time Delay, Wait Until, True/False Branch, Multi-Split, **n8n step**, Exit.
- **n8n step inspector:** pick the tenant's n8n project → pick a workflow/webhook → optional inline
  **flow visualisation** (read-only render of the n8n graph fetched via `comms-n8n-proxy`), plus a
  deep-link to open it in n8n.
- **Template editor:** email (rich) + SMS (plain) with a placeholder picker that inserts
  `{{patient.first_name}}` etc., backed by the placeholder catalog.

Canvas: lightweight custom renderer (SVG edges + absolutely-positioned node cards) to avoid adding a
heavy graph dependency; drag/reposition persisted to `comms_automation_nodes.position`.

---

## 7. Trigger types (v1)

1. **Event** — pick an `event_name` from the catalog (+ optional property filters).
   The catalog is `analytics_event_types` (is_active) served by `trigger_catalog`;
   **only names the patient UI actually emits are active** (login, signup_started/
   completed, product_viewed, checkout_started/completed, questionnaire_started/
   step_completed/completed). `page_view`/`session_start`/`session_end` are
   deactivated — page views carry `event_name NULL` so a name trigger can never
   match, and session events are never emitted.
2. **Subscription lifecycle** — canonical event keys from `platform-events.ts`
   (`subscription.created` / `.cancelled` / `.paused` / `.resumed` /
   `.renewal_date_changed`; `.renewed` = a renewal ORDER was paid).
3. **Relative-time** — `N days {before|after} {renewal | purchase | order_shipped | order_delivered}`.
   Materialised into `comms_scheduled_jobs` by `comms-scheduler`.
4. **Order status** — canonical event keys (`order.created`, `order.paid`,
   `provider.approved`, `prescription.shipped`, …); raw `to_status` is the
   Advanced escape hatch.
5. **Manual / segment** — enroll a chosen audience (future-friendly; minimal in v1).

**Dedup / re-enrollment:** enrollments dedup on
`(patient, event_name/subscription_type/order_status, entity_id)`. For analytics
events the producer passes the event's `client_event_id` as `entity_id`, so a
patient re-enrolls on each real occurrence (a second login re-triggers a
login journey) while client retries of the same batch stay deduped.

### n8n node — test vs production URL

n8n serves each webhook at TWO urls on DIFFERENT hosts (queue mode): production
`/webhook/<path>` on the webhook processors (`n8n-<env>-webhooks.alliahealth.co`,
live only while the workflow is ACTIVE) and test `/webhook-test/<path>` on the
MAIN/editor instance (`n8n-<env>.alliahealth.co`, live only while "Listen for
test event" is armed, one call per arm). `toTestWebhookUrl`
(_shared/comms-n8n.ts) builds the test url on the editor base — the webhook host
answers `/webhook-test/` with a bare "Cannot POST". The builder shows both urls
on the n8n node; `n8n_test_404` on a run step means nobody was listening at that
moment, `n8n_status_404` in production mode means the workflow is inactive.

The builder's **Send test event** saves a dirty graph first (the test runs
against the persisted graph) and requires the automation to be ACTIVE.

---

## 8. Security & tenancy

- Every table tenant-scoped + RLS (`get_user_tenant_ids`). Admin function verifies JWT + membership.
- Execution functions run with the **service role** but always filter by the automation's `tenant_id`.
- n8n API keys stored as **Supabase secrets**, referenced by `api_key_secret_ref` (never in a row).
- n8n webhook calls are **HMAC-signed**; inbound n8n callbacks (if any) verified by shared secret.
- PHI: emails/SMS personalise with patient fields but bodies/log context **mask** addresses (existing
  `maskEmail` helper) and never store raw PHI in `comms_run_steps` beyond a provider message id.

---

## 9. Rollout

1. Migrations + RLS.
2. Admin CRUD function + hooks + list/builder UI (email + delay + exit path working E2E).
3. Event dispatcher + scheduler + execute-node (live enrollments).
4. SMS (Twilio integration).
5. n8n proxy + builder n8n node + flow visualisation (webhook-mode first; Projects when licensed).
6. Relative-time triggers (renewal/purchase) materialisation.

Feature-flagged per tenant via existing feature-flags so it can be dark-launched.

---

## 10. Open items / follow-ups (not blocking v1)

- Purchase + configure n8n Enterprise license → set `N8N_LICENSE`, `N8N_PROJECTS_ENABLED=true`,
  provision per-tenant projects (infra change in `allia-infrastructure`, separate PR).
- Per-tenant n8n execution quotas (capacity doc flagged shared-queue starvation).
- Deliverability analytics (opens/clicks) — Resend webhooks → `comms_run_steps`.
