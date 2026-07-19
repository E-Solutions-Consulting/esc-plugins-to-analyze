# Order Lifecycle API Documentation

> **Version:** 1.3.0\
> **Last Updated:** June 2026\
> **Audience:** Platform Developers, Ops Teams

This document describes the Order Lifecycle API, an internal Edge Function
responsible for automated order state management and lifecycle processing.

Related sequence diagram:
[Order Flow Sequence](./SequenceDiagrams.md#order-flow-sequence).

---

## Table of Contents

1. [Overview](#overview)
2. [Base Configuration](#base-configuration)
3. [Endpoints](#endpoints)
   - [Process Single Order](#process-single-order)
   - [Process All Active Orders](#process-all-active-orders)
4. [Automation Rules](#automation-rules)
5. [Response Models](#response-models)
6. [Error Handling](#error-handling)
7. [Integration Patterns](#integration-patterns)
8. [Cron Job Setup](#cron-job-setup)

---

## Overview

The Order Lifecycle API provides automated order state management. It is
designed for two primary use cases:

1. **Single Order Processing**: Review and apply automations to a specific order
2. **Batch Processing**: Iterate through all active (non-terminal) orders and
   apply automations

### Key Features

| Feature                                          | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Initial Status Hold + RTDH Create Order**      | Leaves `order_created` orders unchanged, but attempts the outbound RTDH `create-order` callback before returning `no_change`                                                                                                                                                                                                                                                                                                                                                       |
| **Address Validation**                           | Validates shipping and billing address fields for `shipping_details_required` orders                                                                                                                                                                                                                                                                                                                                                                                               |
| **Provider Platform Selection + Order Creation** | In `provider_order_creation_pending`, lifecycle first resolves the provider platform using load balancing rules (state-aware), then creates the provider order using the tenant's provider credentials. For Telegra, lifecycle requires the tenant's configured `project_id`, authenticates via `username/password` against `/auth/client`, and sends that identifier as both `projectId` and `project` when calling `/orders` to support Telegra's support-provided and publicly documented field names. For MDI, lifecycle creates one case and sends one `case_offerings` entry per linked medication `offering_id`. Failures move to the configured failure status and can be retried from `provider_order_creation_error`, including via a manual admin "Process Order" trigger |
| **Payment Capture**                              | Attempts Stripe/manual capture handling for `payment_pending` orders                                                                                                                                                                                                                                                                                                                                                                                                               |
| **Pending Cancellation Analysis**                | Evaluates `order_pending_cancellation` orders, logs refund eligibility, and decides whether to cancel directly or advance to processing                                                                                                                                                                                                                                                                                                                                            |
| **Cancellation Processing**                      | Processes `order_cancellation_processing` orders, applies refund/Stripe work, updates the linked plan lifecycle, and advances to the next configured status                                                                                                                                                                                                                                                                                                                        |
| **Lifecycle Schedule Sync**                      | Syncs lifecycle dates and advances `payment_collected` orders to the next active status before provider follow-up                                                                                                                                                                                                                                                                                                                                                                  |
| **Status History Logging**                       | Records automated status changes in `order_status_history`                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **Terminal State Detection**                     | Skips orders in terminal states (completed, cancelled, etc.), except for explicitly retryable error states such as `provider_order_creation_error`                                                                                                                                                                                                                                                                                                                                 |
| **Recursive Processing**                         | Chains automations when multiple rules apply sequentially                                                                                                                                                                                                                                                                                                                                                                                                                          |
| **Batch Processing**                             | Process all active orders in a single API call                                                                                                                                                                                                                                                                                                                                                                                                                                     |

Renewal orders created through the RTDH webhook `renewal_order_create` branch
now invoke single-order lifecycle processing immediately after insert. This does
not change first-order entry paths; it only ensures renewal-created orders do
not wait for batch catch-up before entering the normal lifecycle pipeline.

### Order Status System Integration

The API integrates with the `order_statuses` table configuration:

| Field             | Usage                                                                                                                                                                                                  |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `display_order`   | Determines the sequence of statuses for auto-advancement                                                                                                                                               |
| `is_terminal`     | If `true`, order is normally treated as final and won't be processed. `provider_order_creation_error` is a documented retry exception and should not be configured as terminal in current environments |
| `next_step_owner` | Indicates who is responsible for the next action                                                                                                                                                       |

---

## Base Configuration

### Base URL

```
VITE_SUPABASE_URL/functions/v1/order-lifecycle
```

### Required Headers

| Header         | Description        | Required          |
| -------------- | ------------------ | ----------------- |
| `apikey`       | Supabase anon key  | Yes               |
| `Content-Type` | `application/json` | For POST requests |

### Environment Variables (Edge Function)

The Edge Function requires these secrets (already configured):

| Variable                    | Description                           |
| --------------------------- | ------------------------------------- |
| `SUPABASE_URL`              | Supabase project URL                  |
| `SUPABASE_SERVICE_ROLE_KEY` | Service role key for admin operations |

---

## Endpoints

### Process Single Order

Processes a single order by ID and applies any matching lifecycle automations.

```http
GET /functions/v1/order-lifecycle?orderId={order_id}
apikey: <supabase-anon-key>
```

**Query Parameters:**

| Parameter | Type | Required | Description             |
| --------- | ---- | -------- | ----------------------- |
| `orderId` | UUID | Yes      | The order ID to process |

**Example Request:**

```bash
curl -X GET \
  "VITE_SUPABASE_URL/functions/v1/order-lifecycle?orderId=539fcc38-66fd-4668-8bbf-0c0f40d55b4b" \
  -H "apikey: <supabase-anon-key>"
```

**Response:** `200 OK`

```json
{
  "success": true,
  "requestId": "c94b0560-79c3-4c53-bdda-fcdb3053fb42",
  "result": {
    "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
    "orderNumber": "ORD-ML5IG2IW-P142",
    "previousStatus": "Order Created",
    "newStatus": "Shipping Details Required",
    "action": "advanced",
    "message": "Order advanced through 1 status(es)",
    "transitions": [
      {
        "from": "Order Created",
        "to": "Shipping Details Required",
        "reason": "Order created - moved to next active status"
      }
    ]
  }
}
```

**Possible `action` Values:**

| Action      | Description                                          |
| ----------- | ---------------------------------------------------- |
| `advanced`  | Order was auto-advanced through one or more statuses |
| `no_change` | No automation was applied                            |
| `error`     | An error occurred during processing                  |

---

### Process All Active Orders

Iterates through all non-terminal orders and processes each one, applying
automations as needed.

```http
GET /functions/v1/order-lifecycle?action=process-all
apikey: <supabase-anon-key>
```

**Query Parameters:**

| Parameter | Type   | Required | Description           |
| --------- | ------ | -------- | --------------------- |
| `action`  | string | Yes      | Must be `process-all` |

**Example Request:**

```bash
curl -X GET \
  "VITE_SUPABASE_URL/functions/v1/order-lifecycle?action=process-all" \
  -H "apikey: <supabase-anon-key>"
```

**Response:** `200 OK`

```json
{
  "success": true,
  "requestId": "6238d911-f111-4c4a-a894-0100a3333f9d",
  "summary": {
    "totalProcessed": 15,
    "advanced": 3,
    "unchanged": 11,
    "errors": 1
  },
  "results": [
    {
      "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
      "orderNumber": "ORD-ML5IG2IW-P142",
      "previousStatus": "Order Created",
      "newStatus": "Shipping Details Required",
      "action": "advanced",
      "message": "Order advanced through 1 status(es)",
      "transitions": [
        {
          "from": "Order Created",
          "to": "Shipping Details Required",
          "reason": "Order created - moved to next active status"
        }
      ]
    },
    {
      "orderId": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "orderNumber": "ORD-XY7KL9MN-Q283",
      "previousStatus": "Provider Approved",
      "newStatus": null,
      "action": "no_change",
      "message": "No automation rules for current status"
    }
  ]
}
```

---

## Automation Rules

### 1. Initial Hold (`order_created`)

When an order is in `order_created`, the API does not auto-advance it. The order
remains in `order_created` until another explicit action changes it, but
lifecycle attempts the outbound RTDH `create-order` callback.

Current behavior:

- It resolves the tenant slug and RTDH config from
  `platform_settings.rtdh_config`
- It looks up the most recent checkout-session id from
  `order_payment_provider_transactions.provider_checkout_session_id`, when
  available
- It looks up the initial order status history row for `event_id`, when
  available
- It sends `POST {rtdh_config.api_url}/create-order`
- On successful dispatch, it writes an `order_status_history` marker with notes
  `RTDH create-order webhook dispatched`; later lifecycle runs skip the callback
  when this marker already exists
- No payment-history lookup happens in the `order_created` branch
- No status update happens in the `order_created` branch
- The function returns `no_change` for `order_created` orders

**Outbound RTDH request:**

```json
{
  "source": "patient_platform",
  "event_id": "<order-status-history-id-or-null>",
  "tenant": "<tenant-slug>",
  "occurred_at": "<iso-8601-timestamp>",
  "payload": {
    "checkout_session_id": "<stripe-checkout-session-id-or-null>",
    "patient_platform_order_id": "<internal-order-uuid>",
    "patient_id": "<internal-patient-uuid>"
  }
}
```

**Initial Hold Logic:**

```
┌─────────────────────────────────────────────────────────────┐
│                   Initial Hold Flow                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐    ┌────────────────────┐                │
│  │ Fetch Order  │───>│ Status is          │                │
│  │ with Status  │    │ order_created?     │                │
│  └──────────────┘    └─────────┬──────────┘                │
│                                │                            │
│                    ┌───────────┴───────────┐                │
│                    │                       │                │
│                   Yes                      No               │
│                    │                       │                │
│                    ▼                       ▼                │
│         ┌──────────────────┐    ┌──────────────────┐       │
│         │ Dispatch RTDH    │    │ Check other      │       │
│         │ create-order     │    │ automation rules │       │
│         └────────┬─────────┘    └──────────────────┘       │
│                  │                                          │
│                  ▼                                          │
│         ┌──────────────────┐                               │
│         │ Return no_change │                               │
│         │ keep status      │                               │
│         └──────────────────┘                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 1.1 Payment Capture (`payment_pending`)

When an order is in `payment_pending`, the API attempts Stripe-specific payment
capture and synchronization through
`maybeCaptureStripePaymentForPaymentPendingOrder`.

Current behavior:

- The branch is Stripe-oriented; if the order payment provider is not Stripe, it
  returns `no_change`
- **Invoice-pay path (renewal orders):** If the order has no payment intent yet,
  the lifecycle tries `POST /v1/invoices/{id}/pay` directly
  (source: `invoice_pay_direct`). This covers fresh renewal orders where RTDH
  has created the invoice but no manual-capture payment intent exists.
- **Stale/failed PI path:** If the order has a payment intent that is not in
  `requires_capture` state (e.g. `payment_failed` or `requires_confirmation`
  from a previous failed attempt), the lifecycle tries `POST /v1/invoices/{id}/pay`
  before declaring the intent uncapturable (source: `invoice_pay_direct_retry`).
- **Normal capture path:** If the payment intent is in `requires_capture` state,
  the lifecycle captures it via `POST /v1/payment_intents/{id}/capture`.
- On success, helper logic persists Stripe transaction state and `orders.paid_at`
  but does **not** update the order status to `payment_collected`
- `payment_collected` is applied only by `rtdh-webhook` when RTDH sends the
  `payment_collected` status event
- The lifecycle response keeps the order in `payment_pending` with
  `action: "no_change"` while waiting for RTDH confirmation
- This branch does not recursively re-process the order in the same invocation

### 1.2 Pending Cancellation Analysis (`order_pending_cancellation`)

When an order is in `order_pending_cancellation`, the lifecycle API completes
the decision phase of the deferred cancellation flow requested through
`plan-api`.

Current behavior:

- The branch inspects the previous non-cancellation status from
  `order_status_history` to determine refund eligibility
- It writes a refund-eligibility note to `order_status_history`
- If refund, Stripe, or MDI provider cancellation work is required, it advances
  the order to the configured next status, expected to be
  `order_cancellation_processing`
- If no refund, Stripe, or provider action is required, it moves the order
  directly to `order_cancelled`
- When it advances to processing, it triggers a follow-up lifecycle run for the
  same order after the status change completes

### 1.3 Cancellation Processing (`order_cancellation_processing`)

When an order is in `order_cancellation_processing`, the lifecycle API performs
the Stripe and plan-side work required to finish the cancellation.

Current behavior:

- The branch re-evaluates the refund and Stripe requirements for the order
- If a refund is eligible, it issues the Stripe refund using the existing refund
  rules based on the prior order status
- If a Stripe PaymentIntent still needs to be cancelled, it cancels it in Stripe
- If the linked plan is connected to Stripe, it updates or cancels the Stripe
  subscription as needed
- If the order is linked to Telegra and provider cancellation is required, it
  calls `POST /orders/{orderId}/actions/cancel` using the stored provider order
  id and Telegra Bearer token before finalizing cancellation
- If the order is linked to MDI and a case already exists before provider-side
  cancellation is complete, including held questionnaire cases, it calls
  `POST /v1/partner/cases/{provider_order_id}/cancel` using the stored MDI case
  id, MDI Bearer token, and reason
  `Patient requested cancellation before provider review was completed.`
- It updates the linked subscription lifecycle in Supabase
- It advances the order to the configured next status and writes a completion
  note to `order_status_history`
- It triggers a follow-up lifecycle run for the same order after the status
  change completes

### 1.4 Payment Collected Sync (`payment_collected`)

RTDH calls `rtdh-webhook` with `global_status = payment_collected` to set the
order status after payment is confirmed. The order-lifecycle API detects
`payment_collected`, syncs lifecycle renewal data on `subscriptions` records
linked through `orders.subscription_id`, advances the order to the next active
status by `display_order`, and, when that next status is `order_approved`,
immediately triggers a follow-up lifecycle run so provider workflow handling can
continue.

Current behavior:

- The branch first calls `syncLifecycleDatesForPaymentCollectedOrder`
- `syncLifecycleDatesForPaymentCollectedOrder` uses `orders.paid_at` as the
  calculation anchor for renewal dates; if `paid_at` is null it falls back to
  `orders.created_at`
- If `isAlreadySynced` is true (dates already match), the function returns
  `synced: true` immediately and the order advances — it does **not** block
- Stripe subscription `trial_end` updates are best-effort: if Stripe rejects
  the update (e.g. `trial_end` is in the past due to a test clock frozen
  ahead), the function retries with `trial_end=now`. If that also fails, it
  logs a warning and continues — the local DB is still updated and the order
  still advances
- If schedule sync fails for a non-Stripe reason (missing product, missing plan,
  etc.), the order stays in `payment_collected` until re-triggered
- If schedule sync succeeds, the branch advances the order to the next active
  status by `display_order` and writes `order_status_history`
- If no next active status exists after `payment_collected`, the function
  returns `no_change` and does not call Telegra
- If the next status is `order_approved`, the branch triggers a follow-up
  `order-lifecycle` run for the same order so the `order_approved` logic
  executes immediately
- That follow-up lifecycle run handles Telegra `sendToPharmacyRecipients` when
  applicable
- If the Telegra send-to-pharmacy call fails, the function returns
  `action: "error"` after the status change
- Otherwise, this branch does not recursively re-process the order

#### Payment Failed Recovery path

For orders that previously failed payment and were later recovered (patient
updated card → `customer.updated` → payment retried → succeeded), the
`payment_collected` sync behaves identically to the normal path as long as
`orders.paid_at` is set. The `stripe-webhook` function ensures `paid_at` is
set before triggering lifecycle — see [Payment Failed Recovery](#payment-failed-recovery)
in StripeIntegrationRequirements.md.

### 1.5 Provider Approved (`provider_approved`)

When an order is in `provider_approved`, lifecycle behavior depends on the
selected provider platform.

Current behavior:

- Telegra orders stay in `provider_approved`; lifecycle returns
  `action: "no_change"` and does not move them to `payment_pending`
- Telegra can move forward only when RTDH sends a later provider/payment status,
  such as Telegra's order-processing event mapped to `payment_pending`
- Non-Telegra orders still use `order_statuses.next_status_id`; the configured
  next status must be `payment_pending`
- For non-Telegra orders, lifecycle writes the status history note
  `Auto-advanced: provider_approved moved to payment_pending for payment capture.`
  and then continues processing the `payment_pending` branch

### 1.6 Provider Rejected Payment Release (`provider_rejected`)

When an order is in `provider_rejected`, the lifecycle API performs the
financial and plan cleanup needed after the provider declines treatment. This
branch also runs for terminal `provider_rejected` orders when lifecycle is
invoked by RTDH or another caller.

Current behavior:

- If the order has no cancellation request, the branch calls
  `releaseStripePaymentForRejectedOrder`
- If the order has an uncaptured Stripe PaymentIntent, it cancels the
  PaymentIntent in Stripe with cancellation reason `abandoned`
- If the order has already been captured, it issues the Stripe refund path used
  for provider rejection
- It writes an `order_status_history` note describing the Stripe action. For a
  PaymentIntent cancellation, the note includes the PaymentIntent id and Stripe
  status when available
- It cancels the linked plan by updating the subscription to
  `status = cancelled` and setting `cancelled_at`
- If the provider-rejected order entered the deferred cancellation flow, plan
  cancellation is immediate; it does not leave the plan in
  `pending_cancellation`

---

### 2. Shipping Details Validation (`shipping_details_required`)

When an order is in `shipping_details_required` status, the API validates:

1. **Shipping Address**: Order must have complete shipping address fields
2. **Billing Address**: Order must have complete billing address fields

If both shipping and billing details are complete, the order automatically
advances to the next status (based on `display_order`).

**Required Shipping Fields:**

- `shipping_first_name`
- `shipping_last_name`
- `shipping_address_line1`
- `shipping_city`
- `shipping_state`
- `shipping_postal_code`
- `shipping_country`

**Required Billing Fields:**

- `billing_first_name`
- `billing_last_name`
- `billing_address_line1`
- `billing_city`
- `billing_state`
- `billing_postal_code`
- `billing_country`

```
┌─────────────────────────────────────────────────────────────┐
│             Shipping Details Validation Flow                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐    ┌────────────────────┐                │
│  │ Fetch Order  │───>│ Status is          │                │
│  │ with Status  │    │ shipping_details   │                │
│  └──────────────┘    │ _required?         │                │
│                      └─────────┬──────────┘                │
│                                │                            │
│                    ┌───────────┴───────────┐                │
│                    │                       │                │
│                   Yes                      No               │
│                    │                       │                │
│                    ▼                       ▼                │
│         ┌──────────────────┐    ┌──────────────────┐       │
│         │ Validate         │    │ Return: no_change│       │
│         │ shipping +       │    └──────────────────┘       │
│         │ billing fields   │                               │
│         └────────┬─────────┘                               │
│                  │                                         │
│         ┌────────┴─────────┐                               │
│         │ Both addresses   │                               │
│         │ complete?        │                               │
│         └────────┬─────────┘                               │
│                  │                                         │
│       ┌──────────┴──────────┐                              │
│       │                     │                              │
│      Yes                    No                             │
│       │                     │                              │
│       ▼                     ▼                              │
│ ┌──────────────┐   ┌──────────────────┐                    │
│ │Get next status│  │ Return: no_change│                    │
│ │by display_order│ │ (waiting for     │                    │
│ │Update order   │  │  address data)   │                    │
│ │Log to history │  └──────────────────┘                    │
│ │               │                                          │
│ │ RECURSE: Call │                                          │
│ │ processOrder  │                                          │
│ │ again         │                                          │
│ └──────────────┘                                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 3. Provider Order Creation (`provider_order_creation_pending`)

When an order is in `provider_order_creation_pending`, the lifecycle API is
responsible for deciding the provider platform and then creating the provider
order.

Current behavior:

- Lifecycle checks whether provider platform selection already exists on the
  order (idempotency guard for repeated lifecycle executions)
- If selection is not defined yet, lifecycle calls provider-platform load
  balancing and persists the selected provider for the order
- Load balancing uses `orders.shipping_state` to evaluate state-specific rule
  sets before falling back to default rules
- After provider selection, lifecycle proceeds with provider order creation
  (current implementation supports Telegra and MDI)
- The order must have a mapped product/provider-platform assignment and valid
  tenant integration credentials
- MDI products must have linked medications, and each linked medication must
  have a distinct `medications.offering_id`; those values become the MDI
  `case_offerings` payload
- On successful provider order creation, lifecycle also sends an outbound RTDH
  callback to `/provider-platform/new-order` using an HMAC-SHA256 signature of
  the raw JSON body with `platform_settings.key = rtdh_config`
  `patient_platform_webhook_secret`
- On successful provider order creation, the order advances to the next active
  status by `display_order`
- The function writes an `order_status_history` entry and recursively
  re-processes the order
- On failure, the order moves to the current status's configured
  `failure_status_id`, which is expected to be `provider_order_creation_error`
- The function writes a failure note to `order_status_history`

### 3.1 Provider Order Creation Retry (`provider_order_creation_error`)

When an order is in `provider_order_creation_error`, the lifecycle API retries
provider-side order creation for the same determined provider.

Current behavior:

- The retry path is entered when `order-lifecycle` is invoked again for the
  order, including from the admin "Process Order" action
- Lifecycle first moves the order back to `provider_order_creation_pending`,
  writes a retry note to `order_status_history`, and then immediately
  re-processes the order
- The lifecycle resolves `provider_order_creation_pending` as the canonical
  source of the "next success status"
- If the order already has an `order_provider_platform_links` row, that provider
  selection is reused for the retry
- If no provider link exists yet, lifecycle falls back to the normal provider
  platform selection rules for the order
- If the retry succeeds, the order advances to the same next configured status
  used by `provider_order_creation_pending`
- If the retry fails again, the order remains in `provider_order_creation_error`
- `provider_order_creation_error` is retryable even if an older database still
  has that status flagged as terminal. Current schema/config should keep this
  status non-terminal so UI and lifecycle semantics stay aligned

### RTDH Callback On Provider Order Creation

When lifecycle successfully creates a provider order in Telegra or MDI and has a
resolved provider order id, it performs an outbound RTDH callback.

**Endpoint:**

```http
POST {rtdh_config.api_url}/provider-platform/new-order
Content-Type: application/json
x-patientplatform-signature: sha256=<lowercase hex HMAC-SHA256 of raw body using {rtdh_config.patient_platform_webhook_secret}>
x-request-id: <request-id>
x-request-source: order-lifecycle:provider-platform-new-order
```

**Configuration source:**

- `platform_settings.key = rtdh_config`
- `value.api_url`
- `value.patient_platform_webhook_secret`

**Triggered for:**

- `provider_platform_key = "telegramd"`
- `provider_platform_key = "md_integrations"`

**Payload:**

```json
{
  "source": "patient_platform",
  "event_id": "<order-status-history-id-or-null>",
  "tenant": "<tenant-slug>",
  "occurred_at": "<iso-8601-timestamp>",
  "payload": {
    "patient_platform_order_id": "<internal-order-uuid>",
    "patient_id": "<internal-patient-uuid>",
    "provider_patient_id": "<provider-patient-id>",
    "provider_name": "telegramd",
    "provider_order_id": "<provider-order-id>"
  }
}
```

`patient_id` is the canonical Patient Platform `patients.id`. `provider_patient_id`
is the provider-side patient id returned by Telegra or MDI and stored in
`patient_provider_platform_links.provider_patient_id`.

**Behavior notes:**

- The callback is sent after lifecycle persists the provider order id on the
  order/provider-platform link
- The callback currently logs and skips when RTDH configuration is missing or
  the provider order id is absent
- RTDH callback failures are logged as warnings and do not block successful
  provider-order creation in lifecycle

### Recursive Processing

When the order advances in a recursive branch, the API calls itself again to
check whether additional automations apply to the new status. This currently
applies to:

- `order_created`
- `shipping_details_required`
- `provider_order_creation_pending`
- `provider_order_creation_error`
- `payment_collected`

Recursive processing continues until:

- No automation rules match the current status
- The order reaches a terminal state

The response includes a `transitions` array showing all status changes made:

```json
{
  "transitions": [
    {
      "from": "Shipping Details Required",
      "to": "Ready for Review",
      "reason": "Shipping and billing addresses validated"
    }
  ]
}
```

### Required Configuration

Ensure the `order_statuses` table has proper `display_order` values and that the
lifecycle-controlled statuses are ordered consistently:

| display_order | status_key                        | admin_status_label              |
| ------------- | --------------------------------- | ------------------------------- |
| ...           | `order_created`                   | Order Created                   |
| ...           | `payment_pending`                 | Payment Pending                 |
| ...           | `payment_collected`               | Payment Collected               |
| ...           | `order_approved`                  | Order Approved                  |
| ...           | `shipping_details_required`       | Shipping Details Required       |
| ...           | `provider_order_creation_pending` | Provider Order Creation Pending |
| ...           | ...                               | ...                             |

---

## Response Models

### ProcessingResult

| Field            | Type           | Description                         |
| ---------------- | -------------- | ----------------------------------- |
| `orderId`        | string         | The order UUID                      |
| `orderNumber`    | string         | Human-readable order number         |
| `previousStatus` | string \| null | Status label before processing      |
| `newStatus`      | string \| null | New status label (if changed)       |
| `action`         | enum           | `advanced`, `no_change`, or `error` |
| `message`        | string         | Human-readable description          |

### BatchSummary

| Field            | Type   | Description                                       |
| ---------------- | ------ | ------------------------------------------------- |
| `totalProcessed` | number | Total orders processed                            |
| `advanced`       | number | Orders auto-advanced through one or more statuses |
| `unchanged`      | number | Orders with no automation needed                  |
| `errors`         | number | Orders that failed processing                     |

---

## Error Handling

### Error Response Format

```json
{
  "error": "Internal server error",
  "requestId": "c94b0560-79c3-4c53-bdda-fcdb3053fb42",
  "message": "Detailed error message"
}
```

### Error Codes

| HTTP Code | Error                   | Description                 |
| --------- | ----------------------- | --------------------------- |
| 400       | `Invalid request`       | Missing required parameters |
| 500       | `Internal server error` | Processing failure          |

### Common Error Scenarios

| Scenario                   | Behavior                                    |
| -------------------------- | ------------------------------------------- |
| Order not found            | Returns `action: "error"` with message      |
| Next status not configured | Returns `action: "no_change"` with guidance |
| Database error             | Returns `action: "error"` with details      |

---

## Integration Patterns

### Webhook Integration

Call the single-order endpoint from payment webhooks after order events:

```typescript
// In stripe-webhook Edge Function
if (event.type === "invoice.paid") {
  // After creating/updating order...
  await fetch(
    `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`,
    { headers: { apikey: supabaseAnonKey } },
  );
}
```

### Admin Dashboard Integration

Provide a "Refresh Status" button that calls the single-order endpoint:

```typescript
const refreshOrderStatus = async (orderId: string) => {
  const response = await fetch(
    `${SUPABASE_URL}/functions/v1/order-lifecycle?orderId=${orderId}`,
    {
      headers: {
        apikey: SUPABASE_ANON_KEY,
      },
    },
  );
  return response.json();
};
```

### RTDH Integration

Lifecycle emits an outbound RTDH notification after successful provider order
creation for Telegra and MDI.

Use this when RTDH needs to know that the internal order has been created on the
selected provider platform and now has a provider-side order identifier.

**Outbound request:**

```json
{
  "source": "patient_platform",
  "event_id": "<order-status-history-id-or-null>",
  "tenant": "<tenant-slug>",
  "occurred_at": "<iso-8601-timestamp>",
  "payload": {
    "patient_platform_order_id": "<internal-order-uuid>",
    "patient_id": "<internal-patient-uuid>",
    "provider_name": "telegramd | md_integrations",
    "provider_order_id": "<provider-order-id>"
  }
}
```

**Authentication:**

- `x-patientplatform-signature` header containing `sha256=` plus the lowercase hex HMAC-SHA256
  of the raw JSON body, signed with
  `platform_settings.rtdh_config.patient_platform_webhook_secret`

---

## Cron Job Setup

For automated batch processing, set up a cron job using Supabase `pg_cron`:

### Enable Required Extensions

```sql
-- Enable in Supabase Dashboard > SQL Editor
CREATE EXTENSION IF NOT EXISTS pg_cron;
CREATE EXTENSION IF NOT EXISTS pg_net;
```

### Create Cron Job

```sql
SELECT cron.schedule(
  'order-lifecycle-hourly',
  '0 * * * *',  -- Run every hour at minute 0
  $$
  SELECT net.http_post(
    url := 'VITE_SUPABASE_URL/functions/v1/order-lifecycle?action=process-all',
    headers := '{"Content-Type": "application/json", "apikey": "<supabase-anon-key>"}'::jsonb,
    body := '{}'::jsonb
  ) AS request_id;
  $$
);
```

### Recommended Schedules

| Use Case     | Cron Expression | Description      |
| ------------ | --------------- | ---------------- |
| High-urgency | `*/15 * * * *`  | Every 15 minutes |
| Standard     | `0 * * * *`     | Every hour       |
| Low-urgency  | `0 */6 * * *`   | Every 6 hours    |

### View Scheduled Jobs

```sql
SELECT * FROM cron.job;
```

### Remove Cron Job

```sql
SELECT cron.unschedule('order-lifecycle-hourly');
```

---

## Logging

All requests are logged with structured JSON for debugging:

### Request Log Fields

| Field       | Description               |
| ----------- | ------------------------- |
| `requestId` | Unique request identifier |
| `timestamp` | ISO 8601 timestamp        |
| `method`    | HTTP method               |
| `url`       | Request URL               |

### Processing Log Fields

| Field           | Description              |
| --------------- | ------------------------ |
| `orderId`       | Order being processed    |
| `currentStatus` | Status before processing |
| `action`        | Action taken             |
| `duration`      | Processing time in ms    |

### Viewing Logs

Access logs in the Supabase Dashboard:

```
Dashboard > Edge Functions > order-lifecycle > Logs
```

Or via direct link:

```
https://supabase.com/dashboard/project/dfejvhgwqhywmtxyxkyo/functions/order-lifecycle/logs
```

---

## Security Considerations

### Authentication

This endpoint uses service role access internally and does not require user
authentication. It is designed for:

- Internal system calls (cron jobs)
- Webhook integrations
- Admin dashboard refresh actions

### Rate Limiting

No explicit rate limiting is implemented. For production, consider:

- Adding rate limiting for external callers
- Implementing request queuing for large batch operations
- Setting up monitoring alerts for unusual activity

### Audit Trail

All automated status changes are logged to `order_status_history` with:

- `changed_by`: null (indicates system automation)
- `notes`: Description of the automation applied

---

## Future Enhancements

Potential additions for future versions:

| Feature                 | Description                                  |
| ----------------------- | -------------------------------------------- |
| **Status Transitions**  | Auto-transition between specific statuses    |
| **Notifications**       | Send alerts when orders expire or transition |
| **Tenant Filtering**    | Process orders for specific tenants only     |
| **Dry Run Mode**        | Preview changes without applying them        |
| **Parallel Processing** | Process orders concurrently for performance  |
