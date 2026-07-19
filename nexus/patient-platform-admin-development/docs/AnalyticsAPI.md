# Analytics API (Product Usage Tracking)

First-party behavioural event-ingestion API for **User & Product Usage analytics** — the backend half of the homegrown "our own Mixpanel". It receives batched client events from `patient-platform-patient-ui` (web + Despia mobile), for both authenticated and guest users, and writes them to the Supabase hot store.

For the full architecture, data model, RLS, and warehouse-migration plan see [AnalyticsTracking.md](./AnalyticsTracking.md). For the per-tenant opt-in toggles see the **Product Usage Tracking** settings tab (tenant settings, next to Deployments).

## Base URL

```
VITE_SUPABASE_URL/functions/v1/analytics-api
```

Registered in `supabase/config.toml` with `verify_jwt = false` (the function does its own auth, like every other function). Reuses the shared CORS helper (`_shared/cors.ts`).

## Authentication

- **Anonymous-friendly.** No JWT required — guests are tracked when the tenant enables `track_guest_sessions`.
- If an `Authorization: Bearer <token>` header is present and valid, the function resolves the patient (`auth_user_id` → `patients` row scoped to the tenant) and attributes the batch + backfills the session identity.
- Tenant is resolved from `x-tenant-slug` (header) or `tenant_slug` (query param) against `tenants.slug` where `status = 'active'`.

All database writes are performed with the **service role** inside the function; clients never touch Postgres directly.

## Endpoints

### GET /analytics-api/config

Returns the **effective** tracking flags for the tenant (tenant override if present, else platform default). The client fetches this on boot and only sends data for enabled categories.

**Headers:** `apikey`, `x-tenant-slug`

**200 Response**

```json
{
  "tracking_enabled": true,
  "track_page_views": true,
  "track_activity_events": true,
  "track_time_on_page": true,
  "track_device_info": true,
  "track_utm_attribution": true,
  "track_guest_sessions": true,
  "session_idle_timeout_minutes": 30,
  "hot_retention_days": 30
}
```

### POST /analytics-api/collect

Batch-ingests device + session + events. **Idempotent** on `(tenant_id, client_event_id)` so client retries / offline replay are safe.

**Headers:** `Content-Type: application/json`, `apikey`, `x-tenant-slug`, optional `Authorization: Bearer <token>`

**Request body**

```jsonc
{
  "anonymous_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479", // client-persisted UUID
  "session_id": "…",            // optional; omit to open a new session
  "device": {                    // sent only when track_device_info is enabled
    "platform": "ios",          // web | ios | android
    "device_type": "mobile",    // mobile | tablet | desktop
    "os_name": "iOS", "os_version": "17.4",
    "browser_name": "Safari", "app_version": "1.2.3",
    "user_agent": "…", "screen_width": 390, "screen_height": 844,
    "locale": "en-US", "timezone": "America/New_York",
    "onesignal_player_id": "…"
  },
  "session": {
    "entry_url": "https://app.acme.health/products",
    "referrer": "https://google.com/",
    "utm": { "source": "google", "medium": "cpc", "campaign": "ga" }
  },
  "events": [
    {
      "client_event_id": "…",   // required, idempotency key
      "event_type": "page_view", // page_view | track | identify | session_start | session_end
      "event_name": "product_viewed",
      "page_path": "/products/123",
      "page_title": "Compounded Tirzepatide",
      "referrer": "…",
      "duration_ms": 4200,        // dropped if track_time_on_page is off
      "properties": { "product_id": "p_123" }, // PHI/PII keys are stripped server-side
      "occurred_at": "2026-06-17T12:00:00.000Z"
    }
  ]
}
```

**202 Response**

```json
{ "accepted": 1, "rejected": 0, "session_id": "…" }
```

When the master switch is off, returns `202 { "accepted": 0, "rejected": 0, "reason": "tracking_disabled" }` so the client can stop sending.

## Server-side guardrails (defence in depth)

The client gates capture by the `/config` flags; the server **re-enforces** every rule (see `analytics-api/helpers.ts`, fully unit-tested):

| Guard | Behaviour |
|---|---|
| Master switch | `tracking_enabled = false` → accept nothing. |
| Category gating | Drops `page_view` when `track_page_views` off; drops `track`/activity when `track_activity_events` off. Lifecycle/identity events (`session_*`, `identify`) always allowed when tracking is on. |
| Guest gating | Guest (no Bearer) events rejected when `track_guest_sessions` off. |
| Time-on-page | `duration_ms` stripped when `track_time_on_page` off. |
| UTM | `session.utm` dropped when `track_utm_attribution` off. |
| **PII/PHI guard** | Event `properties` keys matching the denylist (`email`, `phone`, `dob`, `address`, `medication`, `diagnosis`, `symptom`, `condition`, `allerg`, names, card/cvv, …) are stripped before persistence. |
| Batch size | Max 100 events per batch (excess truncated + reported). |
| Property size | Events with `properties` > 8 KB rejected. |

## Persistence

1. **Device** — upsert `analytics_devices` on `(tenant_id, anonymous_id)` (only when `track_device_info` on).
2. **Session** — open `analytics_sessions` if no `session_id`; otherwise backfill identity when the user authenticates. Counters rolled up via `bump_analytics_session_counters(...)`.
3. **Events** — upsert `analytics_events` with `ignoreDuplicates` on `(tenant_id, client_event_id)`.

## Client SDK

The patient app calls this API through a homegrown SDK (`patient-platform-patient-ui/src/services/analytics/`). Surface: `init()`, `page()`, `track()`, `identify()`, `reset()`, `flush()`. It persists an `anonymous_id` in `localStorage`, batches events offline-safe, flushes on interval / batch-size / `pagehide` (via `sendBeacon`), and no-ops for any category disabled by `/config`.

## Hot-store retention (Phase 4)

The hot store keeps `hot_retention_days` of data (default 30, per-tenant, bounded **7–90**). `prune_analytics_hot_store(p_dry_run boolean default false)` (service-role only) deletes rows older than the tenant's effective window (tenant override → platform default → 30), pruning events first then orphan sessions/devices. It returns `(events_deleted, sessions_deleted, devices_deleted)`; pass `true` for a non-destructive dry run.

As of migration `20260709120000` the prune is **window-based and unconditional** — it no longer waits on `exported_to_warehouse`. The BigQuery warehouse copy is owned by a separate team; the hot-store cleanup deletes strictly by the retention window so the store never grows unbounded regardless of export status. It is scheduled nightly (03:17 UTC) via `pg_cron`. The window is admin-editable in the Product Usage Tracking settings tab.

## Recent-activity admin read

There is **no admin read endpoint** on `analytics-api`. Tenant admins query `analytics_sessions` / `analytics_events` directly via supabase-js; the Phase-1 RLS (`is_tenant_admin(auth.uid(), tenant_id)`) scopes reads to the admin's own tenant, e.g.:

```ts
const { data } = await supabase
  .from('analytics_sessions')
  .select('*')
  .eq('tenant_id', currentTenantId)
  .order('started_at', { ascending: false })
  .limit(50);
```

This avoids a trust-the-`p_tenant_id` hole and keeps behavioural data isolated by RLS.

The **Product Usage viewer** (`src/pages/tenant-admin/ProductUsage.tsx`, Workspace nav) reads aggregates through `SECURITY INVOKER` summary RPCs so the same RLS applies — a tenant admin only ever sees their own tenant's rows even though `p_tenant_id` is passed:

- `get_product_usage_summary(p_tenant_id, p_days)` — headline KPIs (events, sessions, devices, auth/guest split, avg session, page views)
- `get_product_usage_timeseries(p_tenant_id, p_days)` — daily events + sessions
- `get_product_usage_top_pages(p_tenant_id, p_days, p_limit)` / `get_product_usage_top_events(...)`
- `get_product_usage_recent_sessions(p_tenant_id, p_limit)`

`p_days` is clamped 1..90 server-side. Granted to `authenticated`; RLS (not the arg) is the tenant boundary.

## Privacy

Events carry **behavioural** data only — no PHI. Guest tracking is opt-in per tenant. Identity stitching links a guest `anonymous_id` to a `patient_id` only after authentication. Behavioural data is tenant-isolated by RLS and excluded from platform-superadmin reads in the hot store; long-term/cross-tenant analytics are served from the access-controlled BigQuery warehouse. See [AnalyticsTracking.md §8](./AnalyticsTracking.md#8-privacy-pii--compliance).

## Related Documentation

- [AnalyticsTracking.md](./AnalyticsTracking.md) — architecture, data model, warehouse migration
- [TenantAPI.md](./TenantAPI.md) — public tenant config endpoint (same tenant-resolution pattern)
- [Backend.md](./Backend.md) — backend structure, Edge Functions, RLS
