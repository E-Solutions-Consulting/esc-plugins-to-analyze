# Outbound Webhooks — Production go-live checklist

Bring the **Outbound Webhooks** feature live in **production** (Patient Platform
Prod, Supabase project ref `dfejvhgwqhywmtxyxkyo`). Dev and Staging are already
provisioned; this runbook is Prod only.

Context: outbound webhooks deliver **PP → RTDH → Pub/Sub → external endpoint**.
The producers are (a) `analytics-api` inline for `usage.*` events and (b) the
cron-driven `outbound-webhook-sweeper` for lifecycle/subscription events. See
`docs/OutboundWebhooksAPI.md` for the full architecture.

Prod starting state (verified): `pg_cron` + `supabase_vault` installed;
`pg_net` **not** installed; `CRON_SECRET` **not** set; Vault empty.

---

## A. Code + migrations reach prod (via the `main` branch)

> Edge functions + migrations deploy through the **Supabase native GitHub
> integration**, NOT `deploy-*.yml`. They land in prod only when the code is on
> the **`main`** git branch. Order: feature → `development` → `staging` → `main`.

- [ ] Merge the Outbound Webhooks change up to `main` (feature PR #233 →
      `development`, then `development → staging`, then `staging → main`).
- [ ] Confirm the Supabase integration deployed the two edge functions to prod:
      `outbound-webhook-dispatcher` and `outbound-webhook-sweeper`
      (both are declared in `supabase/config.toml`).
- [ ] Confirm the two migrations ran on prod:
  - `…_outbound_event_sweep_watermark.sql` → table `public.outbound_event_sweep_state` exists.
  - `…_outbound_webhook_sweeper_cron.sql` → enables `pg_net`, creates
    `public.invoke_outbound_webhook_sweeper()`, schedules cron job
    `outbound-webhook-sweeper`.

## B. Provision prod secrets/config (one-time)

> The cron job reads its config from **Vault** and no-ops (no error) until these
> exist. Do this any time — before or after the migration.

- [ ] **Set `CRON_SECRET`** on the prod Edge Functions env (generate a fresh
      random value; do NOT reuse dev/staging). Dashboard → Project Settings →
      Edge Functions → Secrets, or:
      `supabase secrets set CRON_SECRET='<random>' --project-ref dfejvhgwqhywmtxyxkyo`
- [ ] **Seed Vault** (SQL editor on prod, or Management API):
  - [ ] `SELECT vault.create_secret('https://dfejvhgwqhywmtxyxkyo.supabase.co', 'project_url');`
  - [ ] `SELECT vault.create_secret('<the same CRON_SECRET as above>', 'outbound_sweeper_cron_secret');`
- [ ] **Verify** the cron will call the right place with the right secret:
      ```sql
      SELECT rtrim((SELECT decrypted_secret FROM vault.decrypted_secrets WHERE name='project_url'),'/')
             || '/functions/v1/outbound-webhook-sweeper' AS target_url,
             length((SELECT decrypted_secret FROM vault.decrypted_secrets WHERE name='outbound_sweeper_cron_secret')) AS secret_len;
      ```
      `target_url` must be the prod functions URL; `secret_len` must equal the
      length of the `CRON_SECRET` you set on the functions env.

## C. RTDH side (prod) — required for delivery to leave the platform

> RTDH (`rt-data-hub-functions`) owns the receiver, Pub/Sub topic and fan-out.
> Confirm on the **prod** RTDH GCP project (`allia-rt-data-hub-prod`).
>
> ⚠️ **On STAGING, all THREE of the following were missing/broken and each
> caused a distinct failure — check every one on prod.** See "Known failure
> modes" below.

- [ ] **Pub/Sub topic `pp-outbound-webhook-events` exists** in
      `allia-rt-data-hub-prod`.
      `gcloud pubsub topics describe pp-outbound-webhook-events --project=allia-rt-data-hub-prod`
      — if `NOT_FOUND`, the receiver publish 500s. Create it (the fan-out deploy
      with `--trigger-topic` also auto-creates it):
      `gcloud pubsub topics create pp-outbound-webhook-events --project=allia-rt-data-hub-prod`
- [ ] **Receiver deployed**: Cloud Function
      `patient-platform-outbound-event-receiver-prod` (region us-central1).
      Quick check — an unsigned POST to its root should return **400/401, NOT
      404**: `curl -X POST https://us-central1-allia-rt-data-hub-prod.cloudfunctions.net/patient-platform-outbound-event-receiver-prod -d '{}'`.
      A 404 means the function name/URL is wrong (see failure modes).
- [ ] **Fan-out deployed** — Cloud Function
      `patient-platform-outbound-webhook-fanout-prod`, Pub/Sub-triggered on
      `pp-outbound-webhook-events`, `all` egress (reaches external endpoints).
      **This is the function that actually POSTs to n8n.** On staging it had NOT
      deployed even though its source was on the branch — verify with
      `gcloud functions list --project=allia-rt-data-hub-prod | grep fanout` and
      that a push subscription on the topic exists. If missing, deploy via the
      RTDH `Deploy Cloud Functions - Production` workflow (a no-input
      `workflow_dispatch` deploys nothing — it needs a change to that function's
      folder or the `shared` path; ask the RTDH owner to run a full deploy).
- [ ] **Secret match**: RTDH's Patient-Platform webhook secret **==** PP's
      `platform_settings.rtdh_config.patient_platform_webhook_secret` (dispatcher
      signs `x-patientplatform-signature`; receiver verifies). Mismatch = 401.
- [ ] **PP `rtdh_config` sane**: `base_url` points at the RTDH prod Cloud
      Functions host (`https://us-central1-allia-rt-data-hub-prod.cloudfunctions.net`)
      and `patient_platform_webhook_secret` is set. If unset → dispatcher 503.
      NOTE: the dispatcher derives the env (`dev|staging|prod`) from this
      `base_url` to build the receiver function name
      `patient-platform-outbound-event-receiver-<env>` — so the `base_url` MUST
      contain `allia-rt-data-hub-prod`, or it defaults to `prod` (fine for prod,
      but wrong if a non-standard host is used).

## D. Smoke test (prod)

- [ ] In the prod admin (Developer → Outbound Webhooks), create a **lifecycle**
      webhook pointing at a test endpoint (e.g. a webhook.site URL or the prod
      n8n webhook), subscribed to `order.created`.
- [ ] Trigger a real (or test) order status change, OR manually fire the sweeper:
      `curl -X POST 'https://dfejvhgwqhywmtxyxkyo.supabase.co/functions/v1/outbound-webhook-sweeper' -H 'Authorization: Bearer <CRON_SECRET>'`
      (expect `{ "orders": N, "subscriptions": M, "errors": 0 }`).
- [ ] Confirm a row appears in **Recent deliveries** in the admin UI
      (`tenant_outbound_webhook_deliveries`) with a 2xx `status_code`.
      **IMPORTANT:** this `status_code` is the **publish to RTDH** result, NOT
      the final n8n delivery. A `200` here only means RTDH accepted + queued the
      message on Pub/Sub. The fan-out consumer (Section C) is what actually
      delivers to n8n — so also:
- [ ] Confirm the **endpoint itself received the delivery** (headers
      `X-Allia-Event`, `X-Allia-Signature: t=…,sha256=…`). If the delivery row is
      200 but n8n got nothing → the fan-out consumer isn't deployed/subscribed.
- [ ] Create a **product_usage** webhook subscribed to `usage.page_view`; browse
      the patient app; confirm delivery (this path is inline in `analytics-api`,
      no cron needed).

## E. Post go-live

- [ ] Check `cron.job_run_details` for the `outbound-webhook-sweeper` job — it
      runs every minute; confirm `status='succeeded'` and no error spikes.
- [ ] (Optional) Tune the sweep cadence in the cron migration if 1-minute
      latency is too frequent/slow for the business.

---

## Known failure modes (all hit on staging — verify each on prod)

The delivery **`status_code`** in *Recent deliveries* is the publish-to-RTDH
result. Its value tells you exactly where the chain breaks:

| Delivery `status_code` | Meaning | Fix |
|---|---|---|
| **404** | Dispatcher hit a non-existent RTDH URL. On staging the dispatcher targeted the wrong function (`patient-platform-webhook-receiver/…`) instead of `patient-platform-outbound-event-receiver-<env>`. | Fixed in code (`outbound-webhook-dispatcher` derives env from `base_url` and targets `patient-platform-outbound-event-receiver-<env>` at root). Ensure prod runs the fixed dispatcher, and `rtdh_config.base_url` contains `allia-rt-data-hub-prod`. |
| **500** + `NOT_FOUND … pp-outbound-webhook-events` | Receiver reached, signature OK, but the **Pub/Sub topic doesn't exist**. | Create the topic (Section C) or deploy the fan-out with `--trigger-topic`. |
| **401** | Signature mismatch — RTDH's PP webhook secret ≠ PP's `patient_platform_webhook_secret`. | Align the secrets (Section C). |
| **503** (no publish) | PP `rtdh_config.base_url`/secret missing. | Set `rtdh_config` (Section C). |
| **200 but n8n gets nothing** | Publish succeeded; the **fan-out consumer isn't deployed/subscribed** to the topic, so nobody drains it. | Deploy `patient-platform-outbound-webhook-fanout-prod` (Section C). |

> Staging sequence for reference: 404 (wrong fn name) → fixed dispatcher → 500
> (missing topic) → created topic → **200** (publish OK). Final n8n delivery still
> required deploying the fan-out consumer.

Direct probe of the prod receiver (bypasses PP; confirms topic + fn health):
```bash
# unsigned → 400/401 (NOT 404) proves the function + URL are right
curl -X POST https://us-central1-allia-rt-data-hub-prod.cloudfunctions.net/patient-platform-outbound-event-receiver-prod -d '{}'
# a correctly SIGNED envelope → 200 {success:true, messageId} proves the topic exists
```

### Rollback / kill switch
- Disable a single webhook: toggle it off in the admin UI (`is_enabled=false`).
- Stop ALL lifecycle fan-out: `SELECT cron.unschedule('outbound-webhook-sweeper');`
  (usage events still emit inline from `analytics-api`).
- Stop everything: the dispatcher is a no-op when no webhook matches; deleting a
  tenant's webhooks stops their deliveries.

### Reference — how dev/staging were provisioned (for parity)
- Enabled `pg_net` (`CREATE EXTENSION IF NOT EXISTS pg_net;`).
- Set a distinct random `CRON_SECRET` per env on the Edge Functions env.
- Seeded Vault `project_url` + `outbound_sweeper_cron_secret` per env.
- Verified the Vault reads resolve to that env's functions URL + secret length.
