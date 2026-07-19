# Multi-Tenant Referral + Friendbuy Integration

> **Audience:** Platform, backend, and Patient UI engineers\
> **Scope:** Friendbuy hosted widget/API sync, backend conversion tracking,
> referral tracking, and Patient UI credits balance.

## Overview

Patient Platform uses Friendbuy as the referral engine. Friendbuy owns PURLs,
click attribution, fraud signals, referral emails, and hosted invite UX. Nexus /
PP-admin owns tenant Friendbuy credentials, local display/fallback settings,
tracking state, audit logs, backend conversion events, and the Patient UI
referral banner.

The integration is enabled per tenant with the `friendbuy_referrals` feature
flag and a tenant-level `friendbuy` platform integration. Secrets are used only
by backend Edge Functions.

No tenant is configured or enabled by default. Friendbuy/Stripe coupon economics
are configured in Friendbuy/Stripe. PP-admin stores only the Friendbuy
connection settings and PP-owned display/balance fallback values.

## Nexus Referral Display Configuration

PP-admin keeps a small tenant-scoped `referral_program_configs` row for local
display and synced balance fallback:

- status: `disabled`, `draft`, `active`, `paused`, or `archived`
- currency
- reward amount

PP-admin exposes this under **Settings → Referrals**. These values do not create
or modify Friendbuy/Stripe coupons. Coupon rules, minimum purchase, validity,
redemption limits, invite copy, and reward economics must be configured in
Friendbuy/Stripe. Config changes are written through RLS-protected tenant tables
and audit logged.

Referral activity is tracked in:

- `referral_records`: code/link, referrer/friend emails, Friendbuy identifiers,
  Stripe coupon/promotion IDs, statuses, timestamps, and raw snapshots.
- `referral_sync_events`: idempotent Friendbuy webhook/API and Nexus/Stripe sync
  events.
- `referral_reward_actions`: reward/refund/manual exception actions.

## Tenant Configuration

Create or enable the tenant integration with:

| Setting        | Required | Exposed to PP-UI | Purpose                                                     |
| -------------- | -------- | ---------------- | ----------------------------------------------------------- |
| `merchant_id`  | Yes      | Yes              | Loads `https://campaign.fbot.me/{merchant_id}/campaigns.js` |
| `campaign_id`  | Yes      | Yes              | Campaign context for widget and conversion events           |
| `mount_element_id` | No   | Yes              | Raw PP DOM id for the hosted widget mount; use `#<id>` in Friendbuy placement |
| `secret_key`   | Yes      | No               | Verifies inbound Friendbuy webhook signatures only. Distinct from the Merchant API credentials below. |
| `api_key`      | Yes      | No               | Merchant API authorization key, used for outbound Friendbuy API calls |
| `api_secret_key` | Yes    | No               | Merchant API authorization secret, paired with `api_key`     |
| `placement`    | No       | Yes              | PP placement name, defaults to `dashboard`                  |
| `banner_title` | No       | Yes              | PP-owned referral banner heading, e.g. `Brello Bestie`      |
| `reward_label` | No       | Yes              | Copy used in the PP-owned banner step label                 |

`tenant-info` returns only safe client config:

```json
{
  "integrations": {
    "friendbuy": {
      "merchant_id": "merchant-123",
      "campaign_id": "campaign-456",
      "mount_element_id": "friendbuy-referral-widget",
      "placement": "dashboard",
      "banner_title": "Brello Bestie",
      "reward_label": "Both earn $25"
    },
    "referral_program": {
      "status": "active",
      "currency": "USD",
      "reward_amount_cents": 2500
    }
  }
}
```

`secret_key`, `api_key`, `api_secret_key`, and Stripe keys must never be returned to PP-UI.

## Patient UI Responsibilities

PP-UI shows the referral area only when all gates pass:

- `friendbuy_referrals` is enabled for the tenant.
- `tenant-info.integrations.friendbuy` exists.
- An authenticated patient is available.

PP-UI initializes Friendbuy from tenant config in two stages:

- Globally, on app load (`FriendbuyProvider`): creates/uses `window.friendbuyAPI`,
  sets the tenant `merchant_id`, and loads `https://static.fbot.me/friendbuy.js`.
  This is what powers customer tracking and attribution capture regardless of
  which page the patient is on.
- From the referral widget itself, once its hosted-widget mount container is
  already rendered in the DOM: loads
  `https://campaign.fbot.me/{merchant_id}/campaigns.js`. This script is what
  scans the page for the mount container and renders the hosted widget into
  it, so it's only requested for the first time after that container exists —
  loading it any earlier risks campaigns.js's first DOM scan finding nothing
  if the patient lands on a different route before ever visiting the
  Dashboard.
- Tracks the authenticated patient with `track customer`.
- Captures visitor attribution with `getVisitorStatus`.

The PP-owned banner displays:

- Configured `banner_title`, falling back to `Refer a Friend`.
- Credit balance from `GET /patient-api/friendbuy/balance`.
- Pending credit pill when Friendbuy returns pending credit details.
- Refill helper text.
- Referral steps, using `reward_label` or safe `referral_program` reward amount
  for the final reward copy.

The lower invite/email/share UI is intentionally not implemented in PP-UI.
Friendbuy's hosted widget renders below the banner and should be styled in
Friendbuy.

## Attribution

When Friendbuy identifies a referred-friend session, PP-UI stores safe
attribution in local storage and sends it to PP-admin:

```json
{
  "referralCode": "REF123",
  "attributionId": "attr-456",
  "campaignId": "campaign-456"
}
```

Attribution is persisted on `patients.metadata.friendbuy_attribution`.

The signup request may include `friendbuy_attribution`. Authenticated sessions
can also persist attribution with:

```http
POST /functions/v1/patient-api/friendbuy/attribution
Authorization: Bearer <patient-token>
x-tenant-slug: <tenant-slug>
Content-Type: application/json

{
  "attribution": {
    "referralCode": "REF123",
    "attributionId": "attr-456",
    "campaignId": "campaign-456"
  }
}
```

## Conversion Events

All conversion events are sent from PP-admin/backend. PP-UI does not call the
Friendbuy Merchant API and never has Merchant API secrets.

### Signup

After PP-admin creates or links the patient account, it sends:

```http
POST https://mapi.fbot.me/v1/event/account-sign-up
Authorization: Bearer <friendbuy-token>
```

Payload includes:

- `customerId` = PP patient id
- `email`
- `firstName`
- `lastName`
- tenant `campaignId`
- optional `referralCode`
- optional `attributionId`

### Purchase

After a paid order is confirmed, PP-admin sends:

```http
POST https://mapi.fbot.me/v1/event/purchase
Authorization: Bearer <friendbuy-token>
```

Payload includes:

- `orderId` = PP order id
- `customerId` = PP patient id
- `email`
- `amount`
- `currency`
- tenant `campaignId`
- optional `referralCode`
- optional `attributionId`
- optional product details

Purchase tracking is wired into:

- RTDH `payment_collected` events.
- First-order Stripe PaymentIntent success/capture via order lifecycle.
- Existing Stripe invoice/order payment-collected path.

When a purchase includes a referral/promotion code, Nexus links the order to
`referral_records`, marks the referral as purchased/pending eligibility, and
records a `nexus_purchase_link` sync event. The purchase event sent to Friendbuy
uses the tenant-specific Friendbuy credentials.

## Friendbuy API + Webhook Sync

Friendbuy webhooks are received at:

```http
POST /functions/v1/friendbuy-webhook?tenant_id=<tenant-id>
Content-Type: application/json
x-friendbuy-event-id: <event-id>
x-friendbuy-event-type: <event-type>
X-Friendbuy-Hmac-SHA256: <signature>
```

`tenant_slug` can be used instead of `tenant_id`. Every request must carry a
valid `X-Friendbuy-Hmac-SHA256` signature, computed by Friendbuy as
`Base64(HMAC-SHA256(secret_key, raw_request_body))` using the tenant's
dedicated Friendbuy webhook-signing secret — a distinct credential from the
Merchant API `api_key`/`api_secret_key` pair. PP-admin looks up the tenant's
Friendbuy `secret_key` (via `getFriendbuyIntegrationConfig`), recomputes the
signature over the raw body, and rejects the request with `401` if it's
missing or doesn't match (`verifyFriendbuyWebhookSignature` in
`_shared/friendbuy.ts`).

Friendbuy's real webhook event types for a standard referral campaign are
`advocateReward` (a reward created for the advocate after a friend's
conversion is evaluated as rewardable — mapped to `reward_status:
"pending_eligibility"`, never directly to `"credited"`), `friendIncentive`
(the friend's own incentive after they convert), and `emailCapture` (a friend
enters their email into a referral/incentive widget). `emailOptOut` is logged
for audit/idempotency but doesn't update any `referral_records` row — there's
no "opted out" concept in the schema. `customerUpdate`, `loyaltyReward`,
`receipt`, and `ledgerTransaction` are Friendbuy **Loyalty product** events;
this integration is a plain refer-a-friend campaign and never uses Loyalty,
so these are recognized-but-ignored (logged, not actioned) if Friendbuy ever
sends one. All of this dispatch logic lives in
`normalizeFriendbuyEventSnapshots` in `_shared/friendbuy.ts`. One webhook
delivery's `data[]` array can contain multiple items — every item is
processed, not just the first. Each coupon code is issued to and redeemed by
exactly one friend, so an `advocateReward` item's `friends[]` array holds at
most a single entry in practice; the code still iterates it defensively
rather than assuming shape.

`friendbuy-webhook` is the source of referral lifecycle data (status,
reward_status, timestamps), populated in near-real-time as Friendbuy events
arrive. Two pieces of data have no webhook equivalent at all, though, and are
only reconciled by a narrow pull (scheduled hourly, plus an on-demand button):

- **Reward outcome** (`reward_status: credited`/`rejected`) — `advocateReward`
  only ever announces a reward's creation (mapped conservatively to
  `pending_eligibility`; confirmed empty of any status field in a real
  delivery). The Merchant API's GetReferralRewards endpoint
  (`/analytics/rewards/referral`) is the only source for the confirmed
  outcome.
- **Coupon distribution/redemption** (`coupon_status`, `validity_status`,
  `redemption_count`, `delivered_at`, `redeemed_at`) — no webhook reports
  this either. The Merchant API's GetCoupons endpoint (`/reward/coupons`)
  is the only source. Its `code` field is the **friend's** redeemable coupon
  code (Friendbuy's `couponCode`), which is **distinct** from the advocate's
  PURL `referral_code` — so it is matched back to `referral_records` on
  `friend_coupon_code` (populated by the `friendIncentive`/`emailCapture`
  webhooks), not `referral_code`. Because the coupon belongs to the friend (the
  advocate's reward is a Stripe customer-balance credit, not a coupon),
  `/reward/coupons` is queried by **friend** email, one request per friend.
  Note: the real response has no expiry field, so coupon validity window/expiry
  still isn't covered by any current source.

Code glossary (three distinct values — do not conflate):
- `referral_code` — the advocate's shareable PURL/referral code (e.g.
  `brelloadvocate`). The join key across share/click/conversion/reward-API/nexus.
- `friend_coupon_code` — the friend's redeemable discount coupon
  (Friendbuy `couponCode`), from `friendIncentive`/`emailCapture` and confirmed
  by GetCoupons. This is "what the friend gets".
- advocate reward — a Stripe customer-balance credit, **not** a coupon;
  `advocateReward.couponCode` is therefore not persisted as a code.

Because `advocateReward` and `friendIncentive` webhooks carry **no**
`referralCode`, `upsertFriendbuyReferralSnapshot` links them to the correct row
by `friendbuy_reward_id` when present, else by an identity fallback
(campaign + referrer_email + friend_email), gated on the snapshot's
`identityMatch` flag so it never disturbs the deliberate separate-row behavior
of `emailCapture`/`share`/`click`/`conversion`.

Both are pulled together by `POST /functions/v1/friendbuy-reconcile`, which
accepts two callers:

```http
POST /functions/v1/friendbuy-reconcile
Authorization: Bearer <admin-token | CRON_SECRET>
Content-Type: application/json

{
  "tenant_id": "tenant-id"
}
```

- **Scheduled (primary):** a `pg_cron` job (`friendbuy-reconcile`, hourly)
  runs `public.invoke_friendbuy_reconcile()`, which loops the Friendbuy-enabled
  tenants and `pg_net`-POSTs one reconcile per tenant with a 7-day lookback,
  authenticating with the shared project-wide `CRON_SECRET`. This keeps reward
  statuses current without anyone touching the UI. See
  `supabase/migrations/20260713120000_friendbuy_reconcile_cron.sql`; it reuses
  the same `project_url` + `outbound_sweeper_cron_secret` Vault entries the
  outbound-webhook-sweeper already seeds (one Vault copy of `CRON_SECRET` serves
  every cron tick), and no-ops until they exist. Rotating `CRON_SECRET` means
  updating that Vault value in lockstep or all cron ticks start 401ing.
- **On-demand:** an admin/superadmin can still force a pull via the "Reconcile
  rewards & coupons" button on the tenant Referrals settings page (authorized
  by their JWT + tenant membership); the button can pass a wider window for a
  full backfill.

This is intentionally narrow — it is not a general-purpose multi-collection
sync (that was `friendbuy-sync`, removed). Every other referral signal
(share, click, conversion, email capture, opt-out) already arrives via
`friendbuy-webhook`.

## Stripe Referral Discounts

Friendbuy owns friend coupon issuance through its Stripe integration. It
generates or assigns the Stripe coupon/promotion code, includes it in the
referral flow/email, and applies advocate rewards through the configured Stripe
reward behavior.

Nexus does not create referral Stripe coupons or promotion codes in this
integration model. Nexus stores Stripe coupon/promotion identifiers only when
Friendbuy webhooks or checkout/order data provide them.

PP checkout accepts the Stripe code the friend receives from Friendbuy. After
the friend's order reaches `payment_collected` or later, PP-admin sends the
purchase/conversion event to Friendbuy once. Friendbuy then attributes the
conversion and handles the referrer reward through its Stripe integration.

## Credits Balance

The Patient UI calls:

```http
GET /functions/v1/patient-api/friendbuy/balance
Authorization: Bearer <patient-token>
x-tenant-slug: <tenant-slug>
```

PP-admin resolves the authenticated patient and tenant, then builds the
balance from two sources:

- `available_total` is read live from Friendbuy's Merchant API
  [GET Ledger Balance](https://developers.friendbuy.com/#get-ledger-balance)
  endpoint. `getFriendbuyLedgerBalance` (in `_shared/friendbuy.ts`) fetches a
  bearer token with the tenant's `api_key`/`api_secret_key`, then calls
  `GET https://mapi.fbot.me/v1/ledger-balance?customerId={patient.id}&currency=USD`.
  The customer is keyed on **`patient.id`** — the same id we send to Friendbuy
  as `customer.id` when tracking purchases/signups — so the ledger and our
  patient record line up (verified: stored `referral_records.friendbuy_customer_id`
  values equal the corresponding `patients.id`). Friendbuy returns `total` in
  major currency units (dollars); it is converted to cents and clamped to a
  non-negative value.
- `pending_total` is computed from tenant-scoped `referral_records`, since a
  reward that hasn't yet been disbursed by Friendbuy has no ledger entry to
  read.

> **Note on 404s.** Friendbuy only creates a ledger once a customer has
> *credited* credit. For a customer with no credited balance yet, the endpoint
> returns `404 {"error":"Not found","message":"No ledger found for customer
> with id: …"}`. `getFriendbuyLedgerBalance` treats any non-200 (including this
> 404) as "no live balance" and returns `null`, so `available_total` degrades
> to `0`. A 404 is therefore the expected response for a customer with only
> pending/rejected rewards — not an error.

The response sent to PP-UI contains safe balance fields only:

- `available_total`
- `formatted_available_total`
- `pending_total`
- `formatted_pending_total`
- `currency`
- `applied_to_label`

Rewards in `pending_eligibility` or `pending_approval` are summed into
`pending_total`. Rejected, invalid, expired, and exception records are
excluded. Records are matched to the advocate by `referrer_patient_id`, with
`referrer_email` as a fallback for older synced rows. If the Friendbuy
integration is not configured/enabled for the tenant, or the ledger lookup
fails, `available_total` degrades to `0` rather than erroring.

## Idempotency

## PP-admin Tracking View

The PP-admin Referrals page exposes a per-code tracking view backed by
`referral_records` and `referral_sync_events`.

The table shows:

- referral code
- referrer and friend email
- issuance date
- delivery date/status
- redemption date/status
- validity/expiry
- referrer reward status

Search covers referral code, referrer email, and friend email. Filters cover
lifecycle status, validity status, and reward status. The detail dialog shows
Friendbuy/Stripe ids, related sync events, lifecycle timestamps, and the raw
Friendbuy snapshot preserved from the webhook delivery or reconciliation pull.

Friendbuy webhook ingestion is the source for referral lifecycle status. The
"Reconcile rewards & coupons" button (`friendbuy-reconcile`) additionally
pulls reward outcome and coupon distribution/redemption — see
"Friendbuy API + Webhook Sync" above for why those two need a separate pull.

Friendbuy conversion events are protected by `friendbuy_event_logs`.

The unique key is:

```text
tenant_id + event_type + entity_id
```

For purchases, `entity_id` is the PP order id. For signups, `entity_id` is the
PP patient id.

Before calling Friendbuy, the helper inserts a `pending` row as a send lock.
Duplicate workers skip when a row is already `pending` or `success`. Failed rows
can be retried by atomically moving the row back to `pending`.

This protects against:

- RTDH retries.
- Stripe webhook retries.
- order-lifecycle re-runs.
- multiple paid-state paths touching the same order.

## Friendbuy/Stripe Setup Checklist

In Friendbuy:

1. Create or select the referral campaign.
2. Configure the hosted/account-page widget.
3. Configure friend incentives using Stripe coupons.
4. Configure advocate rewards using Stripe credit or next-renewal reward.
5. Connect Stripe in Friendbuy.
6. Publish the campaign.
7. Copy `merchant_id` and `campaign_id`, plus the Merchant API `api_key` and
   `api_secret_key` credential pair, into the PP tenant integration. Separately
   copy the webhook-signing secret from Friendbuy's webhook/developer settings
   into `secret_key` — it is a distinct credential from the Merchant API pair
   and is used only to verify inbound webhook signatures.
8. In Friendbuy's widget placement settings, enter the PP mount selector,
   usually `#friendbuy-referral-widget`, in "HTML Elements to insert widget into".

In PP:

1. Apply the Friendbuy migration.
2. Create an active referral program config in **Settings → Referrals**.
3. Enable `friendbuy_referrals` for the tenant.
4. Enable the tenant `friendbuy` integration.
5. Deploy PP-admin functions.
6. Deploy PP Patient UI.

## Manual QA

1. Sign in as an advocate patient for the enabled tenant.
2. Confirm the Brello Bestie banner appears.
3. Confirm the Friendbuy hosted widget renders below the banner.
4. Send a referral invite through the Friendbuy widget.
5. Open the friend email/referral link in a new session.
6. Sign up as the referred friend.
7. Complete checkout/payment.
8. Confirm `friendbuy_event_logs` has successful `sign_up` and `purchase`
   events.
9. Confirm Friendbuy attributes the conversion and Stripe reward behavior.
10. Confirm the advocate balance appears in the Brello Bestie banner.
