# Stripe Integration Requirements

This document defines everything that must be configured on the Stripe side (and
in tenant provider settings) for the platform to work correctly.

Related sequence diagram: [Order Flow Sequence](./SequenceDiagrams.md#order-flow-sequence).

## 1. Required Keys

These values come from Stripe and must be stored in each tenant Stripe provider
configuration.

### 1.1 `secret_key` (required backend key)

- Preferred format: `rk_test_...` or `rk_live_...` restricted API key.
- Legacy/unrestricted format: `sk_test_...` or `sk_live_...`.
- Used by backend flows (checkout/session retrieval, payment-intent creation,
  subscription updates, product sync, billing portal, webhook-side Stripe
  lookups).
- Stored in tenant Stripe settings as:

```json
{
  "secret_key": "rk_live_..."
}
```

Use a Stripe **restricted API key** for new tenant setup. The platform setting is
still named `secret_key` for compatibility, but the value should be an `rk_*`
key rather than an unrestricted `sk_*` key whenever possible.

### 1.2 Restricted API key permissions

Create the restricted API key from **Developers -> API keys -> Restricted keys**
in the same Stripe mode (`Test` or `Live`) as the tenant. Configure these
permissions:

| Stripe resource | Permission |
| --- | --- |
| `PaymentIntents` | Write |
| `SetupIntents` | Write |
| `Customers` | Write |
| `Subscriptions` | Write |
| `Invoices` | Write |
| `Checkout Sessions` | Write |
| `Customer Portal` | Write |
| `Products` | Write |
| `Prices` | Write |
| `Coupons` | Write |
| `Promotion Codes` | Write |
| `Refunds` | Write |
| `PaymentMethods` | Read |

Notes:

- Stripe `Write` permissions include `Read`, so separate read permission is not
  required for resources marked `Write`.
- Stripe's Dashboard labels the Billing Portal API permissions as
  **Customer Portal**. The API endpoints used by the platform are still
  `/v1/billing_portal/sessions` and `/v1/billing_portal/configurations`.
- Validate the test-mode restricted key before creating the live-mode key. Run
  checkout, embedded PaymentIntent, SetupIntent, subscription renewal, billing
  portal, coupon/promotion-code, cancellation, and refund flows, then review the
  restricted key's Stripe request logs for `403` permission errors.
- Restrict live keys to the platform's stable outbound IP addresses when the
  hosting/networking setup supports it.

### 1.3 `publishable_key` (required for embedded checkout)

- Format: `pk_test_...` or `pk_live_...`
- **Safe to expose client-side** — it only tokenizes payment details; it cannot
  move money.
- Required for the embedded **Stripe Elements** checkout (Option 2 signup flow).
  Without it, the patient UI shows _"Payments are not configured for this
  tenant."_
- Configured in Nexus: **Tenant settings → Payment Providers → Stripe →
  "Publishable Key"** (the `publishable_key` field is part of the Stripe
  provider's `required_settings`).
- Stored in tenant Stripe settings as:

```json
{
  "publishable_key": "pk_live_..."
}
```

- **Served to the patient UI** by the `tenant-info` edge function as
  `stripe_publishable_key`. The patient UI reads it from tenant config at runtime
  (no front-end env var) — single source of truth for Stripe credentials. After
  setting/changing it in Nexus, the patient UI picks it up on its next
  `tenant-info` fetch (a reload).

### 1.4 Full expected tenant settings

```json
{
  "secret_key": "rk_live_...",
  "publishable_key": "pk_live_..."
}
```

Notes:

- Use `rk_test_...` / `pk_test_...` while validating in test mode.
- Use `rk_live_...` / `pk_live_...` in production.
- Existing tenants may still use unrestricted `sk_*` keys, but new tenants should
  use restricted `rk_*` keys and existing tenants should migrate when practical.
- The `secret_key` and `publishable_key` **must belong to the same Stripe
  account** (a publishable key from a different account will fail to confirm the
  PaymentIntent created with the secret key).
- `api_key` is not the key used by Stripe payment flows in this codebase;
  `secret_key` is the required one.
- Checkout requires an authenticated patient; Stripe webhook does not create users.
- Tenant Stripe settings no longer store Stripe webhook signing secrets.
- **Apple Pay / Google Pay** render only on an HTTPS domain that is registered
  for Apple Pay in the Stripe Dashboard (Settings → Payments → Apple Pay → Web
  domains). They do not render on `localhost`.

## 2. Webhook Endpoint to Configure in Stripe

Create one webhook endpoint in Stripe Dashboard:

- URL: `<SUPABASE_URL>/functions/v1/stripe-webhook`
- Method: `POST`
- Header expected by backend: `stripe-signature`

Examples:

- Test: `https://<project-ref>.supabase.co/functions/v1/stripe-webhook`
- Production: your production Supabase project URL +
  `/functions/v1/stripe-webhook`

## 3. Webhook Events to Subscribe

Configure these events in Stripe for this endpoint:

1. `checkout.session.completed`
2. `checkout.session.expired`
3. `payment_intent.succeeded`
4. `payment_intent.amount_capturable_updated`
5. `payment_intent.payment_failed`
6. `payment_intent.cancelled`
7. `customer.subscription.created`
8. `customer.subscription.updated`
9. `customer.subscription.deleted`
10. `invoice.created`
11. `invoice.finalized`
12. `invoice.payment_failed`
13. `invoice.paid`
14. `customer.updated`

Important:

- `invoice.paid` is required for recurring invoice payment collection. It can
  create/update renewal orders, persist provider transaction references, and
  synchronize subscription period-end/expiry dates.
- `customer.updated` is required for the payment-failed recovery flow. When a
  patient updates their default payment method via the Stripe Billing Portal,
  Stripe fires `customer.updated`. The platform detects the default payment
  method change, moves all `payment_failed` orders for that customer back to
  `payment_pending`, and delegates payment capture entirely to `order-lifecycle`.
  The lifecycle handler pays the invoice directly via `POST /v1/invoices/{id}/pay`
  rather than re-confirming a potentially stale payment intent.

## 4. Stripe Dashboard Setup Checklist

1. Open Stripe Dashboard in the correct mode (`Test` or `Live`).
2. Go to `Developers -> Webhooks`.
3. Click `Add endpoint`.
4. Set endpoint URL to `<SUPABASE_URL>/functions/v1/stripe-webhook`.
5. Select the 14 required events listed above.
6. Save endpoint.
7. Create a restricted API key with the permissions listed in
   [1.2 Restricted API key permissions](#12-restricted-api-key-permissions).
8. In platform admin, configure tenant Stripe provider with:
   - `secret_key` (`rk_test_...` or `rk_live_...`)
   - `publishable_key` (`pk_test_...` or `pk_live_...`)
9. Ensure Stripe provider is enabled for that tenant.

## 5. Operational Requirements (for events to resolve tenant/order)

For robust webhook correlation, checkout/subscription data must carry metadata
(added by checkout flow):

- `tenant_id`
- `patient_id` (when available)
- `product_id`

After RTDH sends an `order.linked` event to
`/functions/v1/rtdh-webhook/event`, Patient Platform also updates the linked
Stripe PaymentIntent metadata with:

- `patient_platform_order_id`

This value is copied from `ids.patient_platform_order_id` on the RTDH payload.
The update is best-effort and uses the tenant's enabled Stripe `secret_key`.
The webhook resolves the Stripe PaymentIntent from the order payment transaction
first, then falls back to `payment.payment_intent_id`.

Without tenant context, some events may be skipped or retried (depending on
event type).

### 5.1 Two Stripe webhook consumers (don't conflate them)

There are **two independent Stripe webhook consumers**, and the events above
configure only the first:

1. **Patient Platform Supabase webhook** — `<SUPABASE_URL>/functions/v1/stripe-webhook`
   (`supabase/functions/stripe-webhook/index.ts`). Handles **subscription /
   invoice / customer** flows (renewals, billing-portal payment-method updates).
   This is what §3's 14 events configure.
2. **RTDH Stripe receiver** — the GCP function `stripeWebhookReceiver` in
   `rt-data-hub-functions`. Handles the **order payment** flow: Stripe → RTDH →
   master object → `POST /functions/v1/rtdh-webhook/event` with a computed
   `global_status`. This is the path that advances the patient-facing order.

### 5.2 PP-566 embedded PaymentIntent — RTDH authorization recognition (planned)

The PP-566 Option 2 signup uses the **embedded Stripe Elements PaymentIntent**
flow (`POST /plan-api/orders/{product_id}/payment-intent`, `capture_method=manual`)
instead of the hosted Checkout Session. Patient Platform side:

- Order is created synchronously at `order_created`, linked to the PaymentIntent
  via `order_payment_provider_transactions.provider_payment_intent_id`, and
  `metadata.patient_platform_order_id` is stamped on the PaymentIntent.
- The route triggers `order-lifecycle`, which dispatches RTDH `create-order`
  → `order.linked` → order advances to `shipping_details_required`.

**create-order PaymentIntent linking — FIXED.** The embedded flow has no Checkout
Session, and RTDH's `create-order` previously **required** a `checkout_session_id`,
so it rejected the dispatch with HTTP 400
(`Field 'checkout_session_id' is required …`). The master object was never built,
`order.linked` never fired, and orders were stuck at `order_created` (questionnaire
404). Fix (both repos):

- `order-lifecycle/rtdh-helper.ts` now includes `payment_intent_id` (from
  `order_payment_provider_transactions.provider_payment_intent_id`) in the
  `/create-order` payload.
- RTDH `patientPlatformWebhookReceiver` accepts create-order when **either**
  `checkout_session_id` **or** `payment_intent_id` is present, forwards
  `payment_intent_id` on the `order.linked` envelope, and persists it as the master
  object's `stripe_payment_intent_id` + identity link.

**Remaining gap (RTDH side, non-blocking, code pending):** when the patient confirms
the embedded PaymentElement, Stripe emits **`payment_intent.amount_capturable_updated`**
(authorization), not `checkout.session.completed` and not `payment_intent.succeeded`.
RTDH does not yet recognize the authorization event. This is **non-blocking** now that
the order links by PaymentIntent at create-order and advances via `order.linked`; it
only affects recording the authorization. **Decision:** when added, the authorization
will be **recorded only (no order-status push)** because the order already advances via
the `order.linked` trigger; the capture path (`order-lifecycle` capture at
`payment_pending` → `payment_intent.succeeded` → `payment_collected`) is unchanged. See
`rt-data-hub-functions` `docs/stripe/stripe-event-pipeline.md` §6 and the
`masterObjectProcessor` / `stripeWebhookReceiver` / `patientPlatformWebhookReceiver`
READMEs.

### 5.3 PP-566 embedded checkout — subscription parity gap (planned)

The embedded PaymentIntent flow currently does **not** create a Stripe **Customer**,
a Stripe **Subscription**, or save a **card on file**. Consequences, by product type:

- **One-time products** (paid or 100%-off) — fully working.
- **Subscription products** — the embedded path sets up **no renewal capability**:
  no automatic renewals, no payment-failed retry (`/orders/{id}/retry-payment`),
  no Billing Portal (`/orders/{id}/payment-portal`), no `customer.updated` recovery,
  and no Stripe-subscription cancellation. All of these remain **fully working on
  the hosted Checkout Session path**, which creates the Customer + Subscription up
  front (`customer_creation: always`; `createSendInvoiceStripeSubscription`).

**Decisions (2026-06-18):** keep the embedded UX and **reuse the hosted setup logic**
(Customer + Subscription + card-on-file helpers) from the embedded flow, without
changing hosted behavior. A **100%-off subscription always collects a payment
method** via a $0 **SetupIntent** (so it can renew); a 100%-off **one-time** order
stays card-free. Coupon renewal behavior is governed by the Stripe **Coupon
`duration`** (`once` vs `forever`/`repeating`) — managed in Stripe, audited, not in code.

A full before/after comparison across all angles and the phased remediation plan +
test matrix are kept as an internal engineering reference (not in this repo). This
section is the source of truth for what has actually shipped.

#### Implemented so far

- **Stripe Customer creation (DONE).** The embedded `payment-intent` route now
  resolves a reusable Stripe Customer for the patient (`ensureStripeCustomerForPatient`):
  reads `patients.metadata.stripe_customer_id`, or creates a Customer (`POST /v1/customers`
  with email/name/phone + tenant_id/patient_id metadata) and caches the id back on the
  patient. The Customer is attached to the PaymentIntent (`customer` param) so
  `setup_future_usage: off_session` yields a reusable payment method, and
  `provider_customer_id` is recorded on `order_payment_provider_transactions`. This
  restores the basis for off-session renewals, the Billing Portal
  (`/orders/{id}/payment-portal`), payment-failed retry (`/orders/{id}/retry-payment`),
  and `customer.updated` recovery — all of which previously required a Customer that the
  embedded flow never created. Applies to all embedded orders (one-time and subscription);
  best-effort for one-time (a failure does not block the purchase).
- **Stripe Subscription creation for paid subscription orders (DONE).** The shared
  helpers `createSendInvoiceStripeSubscription` / `ensureOrderSubscription` /
  `upsertSubscriptionProviderLink` were extracted to `supabase/functions/_shared/stripe-subscription.ts`
  (parameterized on a Stripe customer id + an idempotency scope instead of a Checkout
  `session`; the hosted plan-api callers are unchanged). `order-lifecycle` now calls
  `ensureSubscriptionForCapturedOrder` **at payment capture** (`payment_pending`, after
  clinical approval): for a subscription product with no `subscription_id` yet, it
  creates the Stripe subscription (`send_invoice`, customer from the order, payment
  method from the captured PaymentIntent or the customer default, coupon resolved from
  the order's `coupon_code`), inserts the local `subscriptions` row + provider link, and
  sets `order.subscription_id` + `renewal_at` from the subscription's
  `current_period_end`. Renewals then fire from Stripe `invoice.created`
  (`subscription_cycle`) → RTDH `renewal_order_create` as on the hosted path. Deferred to
  capture (not order creation) so rejected/abandoned orders never get a subscription.
  Best-effort + idempotent (keyed by order id); a failure does not undo the captured
  payment. No-op for one-time products and the hosted flow.
- **$0 / 100%-off subscription SetupIntent — backend DONE.** The embedded
  `payment-intent` route's zero-amount path now branches by product type: a $0
  **one-time** product is unchanged (no card; advances straight through), while a $0
  **subscription** creates a Stripe **SetupIntent** (Stripe rejects a $0 PaymentIntent
  but allows a $0 SetupIntent) attached to the Customer with `usage: off_session`, and
  returns its `client_secret` + `requires_setup: true`. The order stays at
  `order_created` until the card is saved. New endpoint **`POST /orders/{id}/setup-complete`**
  (called by the UI after `stripe.confirmSetup` succeeds): verifies the SetupIntent
  succeeded and matches the order, sets the saved card as the Customer's
  `invoice_settings.default_payment_method` (so renewals charge off_session), and triggers
  the lifecycle. `payment_collected` is not set locally by lifecycle; it is applied
  only after RTDH sends the `payment_collected` status event. Result: a 100%-off
  subscription always has a card on file and can renew at full price (per the
  coupon's Stripe `duration`), while status progression remains RTDH-driven.
  The **patient-ui** wiring is DONE (`StripePaymentSection` setup mode +
  `confirmSetup` + `completeSubscriptionSetup` → `setup-complete`).
- **Coupon `duration` — operational rule (no code).** Whether a renewal is
  discounted is governed entirely by the **Stripe Coupon `duration`**, set in
  Stripe (not in our code):
  - `duration: once` → only the **first** invoice is discounted; **renewals charge
    full price**. Use this for first-order / acquisition promos.
  - `duration: forever` (or `repeating`) → **every renewal** is also discounted. A
    100%-off `forever` coupon on a **subscription** renews **free forever** — almost
    never intended for a paid subscription.
  **Rule:** any coupon meant as a first-order incentive for a **subscription**
  product MUST be `duration: once`. Audit before attaching a coupon to a
  subscription SKU. (Dev audit 2026-06-18 found multiple 100%-off `forever`
  coupons — mostly throwaway test data — confirming this needs an explicit
  operational guard; coupons are managed in the Stripe Dashboard, not here.)

### Embedded ↔ hosted subscription parity — status

With Phases A–C the embedded PaymentIntent checkout now matches the hosted
Checkout Session for subscription products: Stripe **Customer** (Phase A),
Stripe **Subscription** created at capture (Phase B), and **card-on-file via
SetupIntent for $0/100%-off** subscriptions (Phase C). This restores automatic
**renewals**, **payment-failed retry**, the **Billing Portal**, `customer.updated`
recovery, and **subscription cancellation** for the embedded flow — all of which
previously worked only on the hosted path. The hosted path is unchanged. Coupon
renewal behavior is governed by the Stripe Coupon `duration` (operational rule
above). **Status: code-complete across patient-admin + patient-platform-patient-ui;
pending deploy + live verification.**

### 5.4 Embedded `payment-intent` — eligibility & returning-customer handling

`POST /plan-api/orders/{product_id}/payment-intent` runs these checks before
creating the order / PaymentIntent (all keyed by buyer email, so they work for
guest checkout):

- **Same-product duplicate guard.** Rejects with `409 DUPLICATE_PRODUCT_ORDER`
  when the buyer email already has an **active/pending subscription for this
  product** OR a **non-terminal order for this product**. Prevents a returning
  customer from creating a second concurrent plan/order of the same medication.
- **Medication-conflict guard (existing).** Rejects with `409 NOT_ELIGIBLE` when
  the email already has an active subscription whose product shares a medication
  with the product being purchased.
- **`account_exists` signal.** The response includes `account_exists: boolean` —
  `true` when the buyer email already has a registered account (a patient linked
  to an auth user). The order still attaches to that existing patient. The patient
  UI uses this flag to show a **login** step for returning customers instead of
  the create-password + email-verify step (which is only for first-time buyers).
  Returned on all three success shapes (paid, `requires_setup`, zero-amount).

## 6. Validation Steps

After setup, validate end-to-end:

1. Trigger a Stripe checkout in test mode.
2. Confirm Stripe sends `checkout.session.completed` to the endpoint.
3. Confirm webhook response is `2xx` in Stripe event delivery logs.
4. Confirm order/subscription/provider transaction records are created/updated.
5. Trigger a recurring/subscription scenario and validate `invoice.created` /
   `invoice.finalized` / `invoice.paid` handling.

## 7. Renewal Invoice Handling

Stripe subscription renewal behavior is handled inside
`supabase/functions/stripe-webhook/index.ts`.

### 7.1 `invoice.created`

For recurring invoices, the webhook prepares payment collection context before
the renewal is paid:

- Resolves tenant, subscription, checkout session, customer, payment intent,
  product, and Stripe period-end data from invoice fields, Stripe metadata,
  subscription metadata, line metadata, or existing provider links.
- When a payment intent exists, prepares the recurring payment intent for the
  manual-capture flow **only when the linked order is already in `payment_pending`**.
  If the order is in any other status (e.g. `patient_questionnaire_pending`,
  `shipping_details_required`), the confirm step is skipped and the payment
  intent reference is persisted for later. This guard prevents a premature
  Stripe confirm from causing `invoice.payment_failed` before the order
  reaches the payment step.
- Persists payment intent, invoice, subscription, checkout session, and customer
  references in `order_payment_provider_transactions` for later lookup.
- Treats missing tenant context as retryable for invoice/payment-intent event
  types that may resolve after related metadata or local links are persisted.

### 7.2 `invoice.paid`

When the recurring invoice is paid, the webhook turns that Stripe event into
Patient Platform order and subscription lifecycle state:

- Finds the patient from invoice context. If the invoice arrives before
  `checkout.session.completed` has persisted local linkage, the webhook can
  fetch the checkout session, run the checkout-completed recovery path, and
  retry patient lookup.
- Ensures the local `subscriptions` row and
  `subscription_payment_provider_links` row exist for the Stripe subscription
  and checkout session.
- Suppresses duplicates by provider charge id and provider invoice id.
- If an order already exists for the provider invoice, updates that order to
  `payment_collected` and sets `paid_at`, then triggers order-lifecycle.
- If the order is already at `payment_collected` (e.g. a duplicate/replayed
  Stripe event), the webhook skips the status update but still sets `paid_at`
  if it is null, then re-triggers order-lifecycle. This ensures
  `syncLifecycleDatesForPaymentCollectedOrder` uses the correct anchor and the
  order can advance.
- If no order exists for the provider invoice, creates a new subscription-linked
  renewal order with `paid_at` set. The database trigger classifies it as
  `subscription_order_type = "renewal"` when another order already exists for
  the same subscription.
- Stores provider invoice, charge, payment intent, subscription, checkout
  session, paid status, and paid timestamp references in
  `order_payment_provider_transactions`.
- Synchronizes `subscriptions.current_period_end_at` and
  `subscriptions.expires_at` from Stripe period-end data, resolving the local
  subscription first by `provider_subscription_id` and then by
  `provider_checkout_session_id`.

Tenant admin order/subscription views use these persisted values to show
initial-vs-renewal order type, provider transaction history, and the latest
subscription period end.

## 8. Payment Failed Recovery

### 8.1 Overview

When a subscription invoice fails to collect payment, Stripe fires
`invoice.payment_failed`. The platform marks the order as `payment_failed` and
sets `orders.payment_failed_at`. The patient can then update their default
payment method via the Stripe Billing Portal.

### 8.2 End-to-end flow

```
Patient opens Billing Portal → updates default card
  → Stripe fires customer.updated

RTDH masterObjectProcessor: customer.updated handler
  → resolves stripe_customer_id identity link → finds renewal master order
  → merger: current status = payment_failed → explicitly bypasses rank selection
  → master object updated to order_status_key: "payment_pending"
  → sends customer_updated intent to Patient Platform

stripe-webhook: handleCustomerUpdated
  → detects default payment method changed
  → finds all payment_failed orders for the patient
  → for each order:
      → moves order to payment_pending (clears payment_failed_at, paid_at: null)
      → writes history note: "moved from payment_failed to payment_pending
         for lifecycle-managed retry"
      → triggers order-lifecycle (NO direct Stripe API calls here)

order-lifecycle: payment_pending handler
  → maybeCaptureStripePaymentForPaymentPendingOrder
  → if no payment intent yet: tries POST /v1/invoices/{id}/pay directly
  → if payment intent is stale/failed: tries POST /v1/invoices/{id}/pay
  → on success: persists payment metadata and sets paid_at
  → does not move status; waits for RTDH payment_collected

Stripe fires invoice.paid
  → stripe-webhook: handleInvoicePaid
  → updates the matching order to payment_collected and triggers order-lifecycle

order-lifecycle: payment_collected handler
  → syncLifecycleDatesForPaymentCollectedOrder (uses paid_at as anchor)
  → isAlreadySynced? → returns synced: true → order advances to order_approved
  → triggers lifecycle for order_approved

order-lifecycle: order_approved handler (Telegra)
  → sendTelegraOrderToPharmacyForLifecycle
  → POST sendToPharmacyRecipients with existing providerOrderId
  → Telegra dispatches to pharmacy
```

### 8.3 Billing Portal configuration guard

The `plan-api` function caches the Stripe Billing Portal configuration ID in the
tenant DB. Before using it, the function verifies the configuration still exists
in Stripe. If it has been deleted, the stale ID is cleared and a new
configuration is created automatically.

### 8.4 Payment capture fallback chain (`payment_pending`)

When `order-lifecycle` processes a `payment_pending` order, it resolves the
payment reference using this chain inside
`maybeCaptureStripePaymentForPaymentPendingOrder`:

1. If no payment intent exists on the order: tries `POST /v1/invoices/{id}/pay`
   directly.
2. If a payment intent exists but is not in `requires_capture` state (e.g. it
   is `payment_failed` or `requires_confirmation`): tries
   `POST /v1/invoices/{id}/pay` before declaring the PI uncapturable.
3. If a payment intent is in `requires_capture`: captures it via
   `POST /v1/payment_intents/{id}/capture`.

`handleCustomerUpdated` no longer makes any direct Stripe API calls. All
charging is delegated to `order-lifecycle`.

Successful lifecycle capture/pay paths persist Stripe transaction state and
`orders.paid_at`, but they do **not** set `payment_collected`. The status moves
to `payment_collected` only when RTDH calls `rtdh-webhook` with
`global_status = payment_collected`.

### 8.5 `paid_at` requirement for lifecycle sync

`syncLifecycleDatesForPaymentCollectedOrder` uses `orders.paid_at` as the
calculation anchor for renewal and expiration dates. If `paid_at` is null, it
falls back to `orders.created_at`, which can produce a date that mismatches
`subscriptions.expires_at` and causes unnecessary Stripe update calls.

All paths that move an order to `payment_collected` are webhook-driven and must
set or preserve `paid_at`:

| Path | Where `paid_at` is set |
|------|------------------------|
| Normal `invoice.paid` (new order) | `handleInvoicePaid` insert |
| Normal `invoice.paid` (existing order) | `updateOrderToPaymentCollected` update payload |
| Recovery `customer.updated` | `order-lifecycle` sets `paid_at` during retry; RTDH/Stripe later applies `payment_collected` |
| `invoice.paid` when already at `payment_collected` | `updateOrderToPaymentCollected` early-exit guard |

### 8.6 Stripe subscription `trial_end` sync (test clock behaviour)

After a `payment_collected` order advances, `order-lifecycle` updates the Stripe
subscription `trial_end` to align the next billing cycle with the calculated
renewal date. In test environments with a Stripe Test Clock frozen ahead of the
calculated date, Stripe rejects the update with a 400 error (`trial_end` in the
past). The lifecycle function handles this by:

1. Retrying with `trial_end=now` so the subscription renews immediately
2. If the retry also fails, logging a warning and continuing — the local DB is
   updated and the order advances regardless

This is a test-environment edge case only. In production, the calculated renewal
date is always in the future.

## 9. Common Misconfigurations

- Using `api_key` instead of `secret_key` in tenant Stripe settings.
- Copying test `whsec_...` into a live tenant (or inverse).
- Creating webhook endpoint with wrong URL (missing
  `/functions/v1/stripe-webhook`).
- Subscribing only `checkout.session.completed` and missing invoice/payment
  intent/subscription events.
- Not enabling Stripe provider for the tenant after saving settings.
