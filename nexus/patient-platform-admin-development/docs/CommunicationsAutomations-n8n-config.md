# Communications Automations — n8n integration config

Concrete configuration for wiring Communications Automations to our **existing,
running** self-hosted n8n. Values sourced from `allia-infrastructure/ai/n8n/*`.
This documents what to set; nothing here is deployed yet (feature branch only).

> **Reality check (2026-06-24):** our n8n is **Community Edition** — no Enterprise
> license, `n8n_multi_main_enabled = false`, **Projects feature not enabled** in any
> env. So new tenants run in **webhook-mode** today. The Projects/folders path
> "lights up" when a license is activated and `N8N_PROJECTS_ENABLED=true`.
>
> The same instance also runs Allia's **internal** automations (Pharmacy Data
> Entry, etc.) with **no isolation** between workflows. We therefore namespace our
> SaaS tenant projects `comms-<env>-tenant-<id>` and tenant secrets
> `n8n-<env>-tenant-<id>-api-key` so they never collide with internal work.

## Instance URLs (from `ai/n8n/{env}/terraform.tfvars`)

| Env | UI / Public API base | Webhook base | GCP project | GKE cluster | ns |
|---|---|---|---|---|---|
| dev | `https://n8n-dev.alliahealth.co` | `https://n8n-dev-webhooks.alliahealth.co` | `ai-dev-471816` | `allia-ai-n8n-cluster` | `n8n` |
| staging | `https://n8n-staging.alliahealth.co` | `https://n8n-staging-webhooks.alliahealth.co` | `allia-ai-staging` | `allia-ai-n8n-cluster` | `n8n` |
| prod | `https://n8n.alliahealth.co` | `https://n8n-webhooks.alliahealth.co` | `ai-prod-471816` | `allia-ai-n8n-cluster` | `n8n` |

The code defaults to these per-env hosts (`_shared/comms-n8n.ts` →
`defaultN8nBaseUrl` / `defaultN8nWebhookBaseUrl`, keyed off `N8N_ENV`).

> **Host split that matters (queue mode):** PRODUCTION webhooks
> (`/webhook/<path>`) are served by the **webhook base**; TEST webhooks
> (`/webhook-test/<path>`) are registered on the **UI/editor base only** — the
> webhook host answers `/webhook-test/` with a bare Express "Cannot POST".
> `toTestWebhookUrl` therefore builds test urls on `defaultN8nBaseUrl()`.
> Building them on the webhook base made "Listen for test event" unreachable
> (fixed 2026-07, PR #361).

## Supabase Edge Function secrets to set (per Supabase project: dev/staging/prod)

| Secret / env var | Value / source | Used by |
|---|---|---|
| `N8N_ENV` | `dev` \| `staging` \| `prod` | picks default URLs + secret-name env suffix |
| `N8N_BASE_URL` | *(optional)* overrides the per-env UI/API host above | n8n API calls |
| `N8N_WEBHOOK_BASE_URL` | *(optional)* overrides the per-env webhook host | webhook hints |
| `N8N_PROJECTS_ENABLED` | `false` today; `true` once Enterprise licensed | projects vs webhook-mode |
| `N8N_ADMIN_API_KEY` | *(optional fallback)* an n8n API key — **must be created in the n8n UI** (Settings → API), none is pre-provisioned in infra | proxy when a tenant has no key yet |
| `GCP_SECRET_MANAGER_SA_KEY` | shared platform SA JSON key for n8n Secret Manager writes | `_shared/gcp-secret-manager.ts` |
| `GCP_N8N_SECRETS_PROJECT_ID` | `ai-dev-471816` / `allia-ai-staging` / `ai-prod-471816` (else falls back to `GCP_SECRETS_PROJECT_ID`, then SA `project_id`) | where tenant n8n secrets live |
| `COMMS_INTERNAL_SECRET` | new random shared secret | dispatcher/scheduler/producers → execute-node/dispatcher |
| `COMMS_N8N_SIGNING_SECRET` | new random secret | HMAC on outbound n8n webhook calls |
| `CRON_SECRET` | the existing cron secret (same as `reminder-scheduler`) | `comms-scheduler` auth |
| `COMMS_RESEND_WEBHOOK_SECRET` | Resend webhook signing secret (`whsec_…`) | `comms-resend-webhook` Svix verify (accepts+warns if unset) |

## Event producers (already wired in code — no further edits needed)

- **Behavioral events**: `analytics-api` emits named events (with a `patient_id`) to
  `comms-event-dispatcher` on ingestion.
- **Subscription + order events**: no function hook exists and there's no `pg_net`,
  so `comms-scheduler` **sweeps** new `subscription_events` / `order_status_history`
  rows since a per-source watermark (`comms_event_sweep_state`) each tick and emits
  them. Latency = the scheduler cadence (set the pg_cron interval accordingly, e.g.
  every 1–2 min for near-real-time triggers).
- **Deliverability**: point the Resend dashboard webhook at
  `…/functions/v1/comms-resend-webhook` and set `COMMS_RESEND_WEBHOOK_SECRET`.

### Per-tenant n8n API key
Stored in **GCP Secret Manager** under `n8n-<env>-tenant-<tenantId>-api-key` in the
env's GCP project. Written **only-on-change** by `comms-n8n-proxy` action
`set_api_key` (platform-admin → n8n page). The DB
(`comms_n8n_projects.api_key_secret_ref`) holds only the secret **name**.

## Service-account IAM (one-time, in `allia-infrastructure`)

The shared `GCP_SECRET_MANAGER_SA_KEY` SA (already used for RTDH provider secrets)
needs, in each env's GCP project (`ai-dev-471816` / `allia-ai-staging` /
`ai-prod-471816`):

- `roles/secretmanager.admin` **or** the narrower trio
  `roles/secretmanager.secretCreator` + `roles/secretmanager.secretVersionAdder` +
  `roles/secretmanager.secretAccessor`

(create-if-missing + add-version + read-latest are all used). This is the only infra
change required to go live; it belongs in a separate `allia-infrastructure` PR, not
here.

## What is NOT needed
- No new per-tenant Google service accounts (we reuse the shared SA — same as RTDH).
- No n8n redeploy for webhook-mode (Public REST API is already on by default).
- No license to ship v1 (webhook-mode works on Community Edition today).

## Go-live checklist (when the user says "migrate")
1. `allia-infrastructure` PR: grant the shared SA Secret Manager roles per env.
2. Set the Supabase secrets above for each env.
3. `supabase db push` the two comms migrations.
4. Deploy the `comms-*` edge functions.
5. Create an n8n API key in the UI; store per-tenant via platform-admin → n8n
   (or set `N8N_ADMIN_API_KEY` as a shared fallback).
6. Schedule `comms-scheduler` via pg_cron; wire event producers to
   `comms-event-dispatcher`.
7. (Later) activate Enterprise license → `N8N_PROJECTS_ENABLED=true` to enable real
   per-tenant projects + folders.
