# RTDH Webhook API Documentation

> **Version:** 1.0.3
> **Last Updated:** May 2026
> **Audience:** Platform Developers, Integrations Team, Ops

This document describes the `rtdh-webhook` Edge Function route `/event`, used to ingest RTDH order events, validate references, apply order status actions, and persist provider chat message notifications.

Related sequence diagram: [Order Flow Sequence](./SequenceDiagrams.md#order-flow-sequence).

---

## Table of Contents

1. [Overview](#overview)
2. [Endpoint](#endpoint)
3. [Authentication](#authentication)
4. [Request Payload](#request-payload)
5. [Responses](#responses)
6. [Operational Notes](#operational-notes)

---

## Overview

The `rtdh-webhook/event` route receives normalized cross-system RTDH order events (Telegra, MD Integrations, Stripe, LifeFile, EasyPost, Patient Platform), validates required top-level fields and references, logs the full request payload, and executes order status actions.

It also accepts canonical provider chat events from RTDH with `event_type = "chat.message.received"`. Chat events are handled before Master Object `global_status` validation because they are not Master Object documents.

### Key Features

| Feature | Description |
| ------- | ----------- |
| **Full payload logging** | Logs all inbound event data to console (`rawContent` and parsed payload) |
| **Schema validation** | Validates required top-level event fields before processing |
| **QA validation bypass** | Allows a QA-only header to skip selected Stripe metadata checks |
| **Traceability** | Uses `x-request-id` (or generated UUID) in logs and responses |
| **Deterministic dispatch** | Resolves action type from top-level `global_status` |
| **Renewal intent branch** | Supports top-level payload field `rtdh_intent: "renewal_order_create"` to create a renewal order and return `orderId` synchronously |
| **Provider chat notifications** | Persists `chat.message.received` events to `patient_notifications` and sends best-effort OneSignal push |

---

## Endpoint

- **Function:** `rtdh-webhook`
- **Route:** `POST /event`
- **Full URL:** `<SUPABASE_URL>/functions/v1/rtdh-webhook/event`

### CORS

- Allowed methods: `POST, OPTIONS`
- Allowed headers: `authorization, x-client-info, apikey, content-type, x-request-id, x-rtdh-webhook-secret, x-webhook-secret, x-webhook-signature, x-qa-bypass, x-rtdh-intent`

---

## Authentication

Authentication is controlled by the RTDH platform setting:

- `platform_settings.rtdh_config.patient_platform_consumer_webhook_token`

Behavior:

1. If `MIGRATION_API_KEY` is configured and the request sends that exact value in `x-rtdh-webhook-secret`, `x-webhook-secret`, or `Authorization: Bearer <MIGRATION_API_KEY>`, the request is authorized as a migration bypass and signature validation is skipped.
2. Otherwise, `patient_platform_consumer_webhook_token` must be configured on the Realtime Data Hub admin screen.
3. The request must include a lowercase `x-webhook-signature` header with this format:
   - `x-webhook-signature: sha256=<lowercase hex HMAC-SHA256 of raw body using patient_platform_consumer_webhook_token>`

Invalid, missing, or incorrectly formatted signatures return `401 unauthorized`.

---

## Request Payload

The route accepts a single JSON object with this shape (abridged):

```json
{
  "master_order_id": "<InternalOrderID>",
  "internal_tenant_id": "<patient_platform_tenant_id_or_slug>",
  "source_systems": ["telegra", "md_integrations", "stripe", "lifefile", "easypost", "patient_platform"],
  "global_status": "Rx Shipping Pickup",
  "status_provider": "lifefile",
  "updated_at": "2026-04-16T21:19:55.170Z",
  "ids": {},
  "customer": {},
  "provider": {},
  "subscription": {},
  "payment": {},
  "prescription": {},
  "fulfillment": {},
  "shipping": {},
  "products": [],
  "status_rollup": {},
  "timeline": [
    {
      "event_id": "event::...",
      "source": "patient_platform",
      "event_type": "order.linked",
      "status": "linked",
      "at": "2026-04-16T21:19:55.170Z"
    }
  ]
}
```

### Provider Chat Message Events

RTDH publishes provider chat events as canonical events, not Master Object documents:

The upstream provider events that must be enabled in RTDH/provider configuration are:

| Provider | Event |
| --- | --- |
| TelegraMD | `patient_communication_text_message_sent` |
| TelegraMD | `message-from-admin-practitioner-received` |
| MD Integrations | `message_created` |

```json
{
  "event_type": "chat.message.received",
  "source": "provider_chat",
  "provider_name": "telegramd",
  "event_id": "telegramd:evt::123456789",
  "occurred_at": "2026-06-19T00:00:00.000Z",
  "ids": {
    "tenant_id": "resolved-tenant-id",
    "patient_id": "resolved-patient-id",
    "provider_patient_id": "pat::123456789",
    "provider_message_id": "evt::123456789",
    "provider_order_id": "order::123456789",
    "patient_platform_order_id": "resolved-order-id"
  },
  "notification": {
    "title": "New message",
    "body": "You have a new message from your care team.",
    "resource": {
      "type": "chat",
      "provider_name": "telegramd",
      "provider_patient_id": "pat::123456789",
      "order_id": "resolved-order-id"
    }
  }
}
```

Required fields are `provider_name`, `ids.tenant_id`, `ids.patient_id`, `ids.provider_message_id`, `notification.title`, `notification.body`, and `notification.resource.type = "chat"`.

On success, the function inserts one `patient_notifications` row keyed by `(tenant_id, provider_name, provider_message_id)`. Duplicate deliveries return success and do not create duplicate notifications. OneSignal push is best effort; persistence success is the source of truth.

### Required Fields

- `master_order_id` (string)
- `internal_tenant_id` (string; tenant UUID or slug)
- `source_systems` (non-empty string array)
- `updated_at` (valid ISO timestamp string)
- `timeline` (non-empty array with at least one event object)

`schema_version` is ignored when supplied.

`global_status` is accepted but its value is not validated.

`status_provider` is optional.

### Optional Headers

| Header | Value | Behavior |
| --- | --- | --- |
| `x-request-id` | Any non-empty string | Used for traceability in logs and responses |
| `x-qa-bypass` | `true` | Skips validation for `payment.api_version`, `payment.livemode`, and `payment.provider_created_at` only. The value is trimmed and compared case-insensitively. Any other value leaves normal validation enabled. |

### Optional Transport Fields

| Field | Value | Behavior |
| --- | --- | --- |
| `rtdh_intent` | `renewal_order_create` | Routes request to renewal-create handling. When absent/mismatched, legacy `global_status` behavior is used unchanged. |

### Nested Validation

When supplied, nested RTDH sections are validated against the documented shape:

- `customer`: must include `email`; this value must be a string or `null`. `patient_id`, `provider_name`, `provider_patient_id`, `phone`, `first_name`, and `last_name` are optional and are not required by object validation. `customer_id` is accepted when supplied but is not validated.
- `subscription`: must include `subscription_id`, `status`, `billing_period`, `billing_interval`, `created_at`, `updated_at`, `next_payment_at`, `end_date_at`, and `cancelled_at`; ID/status fields must be strings or `null`, `billing_interval` must be numeric or `null`, and timestamp fields must be valid ISO strings or `null`.
- `payment`: must include `provider`, `status`, `amount`, `currency`, `customer_id`, `checkout_session_id`, `event_id`, `event_type`, `object_id`, `object_type`, `api_version`, `livemode`, and `provider_created_at`; `subscription_id`, `charge_id`, `invoice_id`, and `payment_intent_id` are optional. Provider/status/Stripe identifier fields must be strings or `null` when supplied, `amount` must be numeric or `null`, `livemode` must be boolean or `null`, and `provider_created_at` must be a valid ISO string or `null`.
  - When `x-qa-bypass: true` is sent, schema validation does not require or type-check `payment.api_version`, `payment.livemode`, or `payment.provider_created_at`. All other `payment` fields and all reference validation still run normally.
- `products`: each array item must include `product_id`, `name`, `subscription_duration`, numeric `quantity`, and numeric `price`. `product_variation_id` is accepted when supplied but is not validated.
- `status_rollup`: must include `order_stage`, `payment_stage`, `prescription_stage`, `fulfillment_stage`, `shipping_stage`, `is_complete`, and `is_cancelled`; stage fields must be strings or `null`; `is_complete` and `is_cancelled` must be booleans or `null`.
- `timeline`: required top-level array with at least one item; each item must include `event_id`, `source`, `event_type`, `status`, and a valid ISO timestamp `at`.

### Reference Validation

After schema validation passes, the webhook verifies that referenced entities actually exist in the database. If any reference is invalid, the request is rejected with a `422 reference_not_found` error before processing.

| Payload Field | Validated Against | Condition |
| --- | --- | --- |
| `internal_tenant_id` | `tenants.id` or `tenants.slug` | Always required; must match an existing tenant by id or slug |
| `ids.patient_platform_order_id` | `orders.id` | When set; must match an existing order |
| `ids.patient_id` | `patients.id` | When set; must match an existing patient |
| `customer.provider_name` | `tenant_integrations.integration_key` | When set; must match an existing provider platform integration key |
| `subscription.subscription_id` | `subscriptions.id` | When set; must match an existing plan |
| `subscription.subscription_id` | `subscriptions.patient_id` | When set and `ids.patient_id` is also set; the plan must belong to the given patient |
| `payment.checkout_session_id` | `order_payment_provider_transactions.provider_checkout_session_id` | When set; must match an existing transaction |
| `payment.subscription_id` | `order_payment_provider_transactions.provider_subscription_id` | When set; must match an existing transaction |
| `payment.payment_intent_id` | `order_payment_provider_transactions.provider_payment_intent_id` | When set; must match an existing transaction |
| `payment.invoice_id` | `order_payment_provider_transactions.provider_invoice_id` | When set; must match an existing transaction |
| `payment.provider` | `payment_providers.key` | When set; must match an existing payment provider key |
| `payment.customer_id` | `order_payment_provider_transactions.provider_customer_id` | When set and a transaction is matched by checkout_session, subscription, payment_intent, or invoice; the customer ID must match the stored `provider_customer_id` on that transaction |
| `products[].product_id` | `products.id` | For each product in the array; must match an existing product |

All reference lookups run concurrently. Errors are collected and returned together in the `details` array.

When `internal_tenant_id` is provided as a slug, it is resolved to the tenant UUID and that UUID is used for downstream order and status processing.

### Event-Type Dispatch

### Renewal Intent Dispatch

When top-level payload field `rtdh_intent: "renewal_order_create"` is present, the webhook executes renewal order creation before legacy `global_status` dispatch:

1. Resolve tenant from `internal_tenant_id` (id or slug)
2. Resolve renewal context from payment identifiers (subscription preferred; fallback invoice/checkout/payment_intent transaction lineage)
3. Apply idempotency by invoice (`tenant + payment_provider + provider_invoice_id`)
4. Create a new order under the existing subscription in `order_created`
5. For newly created renewal orders, trigger `order-lifecycle` for that order immediately
6. Return created/reused `orderId` synchronously

This branch still does not bypass the normal status model or create provider orders directly. Instead, it feeds newly created renewals into the same single-order lifecycle pipeline used elsewhere. Idempotent invoice replays return the existing order without re-triggering lifecycle. Legacy non-renewal `global_status` dispatch remains unchanged.

Renewal orders created by this branch are linked to the resolved
`subscriptions.id`. The order classification trigger marks them as
`subscription_order_type = "renewal"` when the same subscription already has an
earlier order. The follow-up `order-lifecycle` run is responsible for advancing
the new order through the configured status model and synchronizing lifecycle
date state on the linked subscription where payment/status data is available.

After validation, the webhook resolves dispatch type from the top-level `global_status` value.

Timeline objects are validated for shape, but they are ignored for dispatch (no sorting/reordering and no `timeline[].event_type` selection).

If `global_status` is missing/empty or not in the supported set, the request is rejected with a `422 unsupported_event_type` error.

#### Questionnaire Submission Events

RTDH may also send a flat questionnaire submission event using `event_type`
instead of `global_status`. The accepted values are
`patient_questionnaire_submitted` and `medical_questionnaire_submitted`; this
event type is the source of truth for the questionnaire flow.

```json
{
  "source": "rtdh",
  "event_type": "medical_questionnaire_submitted",
  "event_id": "submission_123",
  "tenant": "tenant_1",
  "occurred_at": "2026-05-26T12:00:00.000Z",
  "master_order_id": "master::test",
  "patient_platform_order_id": "pp_order_1",
  "submissionID": "submission_123",
  "payload": {
    "patient_platform_order_id": "pp_order_1",
    "submissionID": "submission_123"
  }
}
```

The webhook resolves `tenant` as a tenant id or slug, verifies that the order
belongs to that tenant, and calls the internal JotForm submission processor. The
processor fetches the submission from JotForm using the tenant's configured
`jotform` API key and Team Workspace ID (`jf-team-id` header). The submitted `form_id` is retained for traceability, but it
is not used to decide patient-vs-medical processing:

When the submitted answers contain file/image uploads (for example ID uploads),
the processor also uses the same tenant credentials and `jf-team-id` header to
download those assets from JotForm before sending them to provider workflows.
When one of those JotForm file-processing steps fails, the provider bridge now
adds a diagnostic note to `order_status_history` for the same order, including
an internal failure code and `requestId`.

- `patient_questionnaire_submitted` is processed only while the order is in
  `patient_questionnaire_pending`. This path is currently implemented for MDI
  orders only.
- `medical_questionnaire_submitted` is processed only while the order is in
  `medical_questionnaire_pending`. The submission is processed with the same
  MDI patient-profile mapping path used for patient questionnaires, then the
  order advances via `next_status_id`.

Both paths validate that the JotForm submission contains
`patient_platform_order_id` and that it matches the order id in the RTDH event.
Duplicate or out-of-order submissions are acknowledged but skipped when the
order is no longer in the expected questionnaire-pending status.

Each linked JotForm must include these hidden configuration fields:

| Hidden field name | Required value |
| --- | --- |
| `patient_platform_order_id` | Populated by the Patient Platform when the form is opened. |
| `provider_key` | Fixed provider integration key, e.g. `md_integrations`. |
| `questionnaire_type` | Fixed per form: `patient_questionnaire` or `medical_questionnaire`. |

These fields must not be treated as PHI in JotForm so they remain readable from
the JotForm API. `questionnaire_type` is a fixed value on each form, not a
patient-editable answer.

Each new JotForm form must also configure a JotForm Webhook that points to RTDH:

```text
https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev
```

Without this JotForm Webhook, RTDH will not receive the submission notification,
so no `patient_questionnaire_submitted` or `medical_questionnaire_submitted`
event will be emitted for the Patient Platform to process.

##### MDI Patient Questionnaire Processing

For `patient_questionnaire_submitted`, the processing order is:

1. Fetch the JotForm submission by `submissionID` using the tenant's JotForm
  API key and Team Workspace ID (`jf-team-id`).
2. Validate the submission includes `patient_platform_order_id` and that it
   matches the RTDH event order id.
3. Confirm the order is currently in `patient_questionnaire_pending`.
4. Resolve the tenant's enabled `md_integrations` configuration and the stored
   MDI patient link.
5. Upload any submitted ID verification file to MDI as a `driver-license` file.
   This includes JotForm file uploads and ID upload widgets such as camera or
   gallery pickers. If the patient explicitly chose to continue without ID
   verification, the submission is still processed and the confirmation is
   retained as patient metadata.
6. Build the MDI patient PATCH payload from the JotForm answers.
7. Set `driver_license_id` on that PATCH payload when an ID file upload returned
   a file id.
8. PATCH the MDI patient profile.
9. Advance the order to the current status' configured `next_status_id`.
10. Trigger `order-lifecycle` for the order when the status advance succeeds.

The patient questionnaire answer mapping currently includes:

| JotForm answer | MDI patient field |
| --- | --- |
| `biological_gender` | `gender` (`Male` → `1`, `Female` → `2`) |
| `date_of_birth` | `date_of_birth` normalized to `YYYY-MM-DD` |
| `weight_value` + `weight_unit` | `weight` in kilograms; converted from lbs when needed and kept as a number/float |
| `height_value` + `height_unit` | `height` in centimeters; converted when needed and rounded to an integer |
| `medications_list` | `current_medications` |
| `allergies_list` | `allergies` |
| `other_symptoms_text` | `medical_conditions` |
| ID upload fields | uploaded to MDI; returned file id is sent as `driver_license_id` |
| `symptoms`, medication/allergy confirmations, ID skip confirmation | MDI `metafields` |

Fields not collected by the JotForm patient questionnaire are not overwritten on
the MDI patient profile.

##### MDI Medical Questionnaire Processing

For `medical_questionnaire_submitted`, the processing order is:

1. Fetch the JotForm submission by `submissionID` using the tenant's JotForm
  API key and Team Workspace ID (`jf-team-id`).
2. Validate the submission includes `patient_platform_order_id` and that it
  matches the RTDH event order id.
3. Confirm the order is currently in `medical_questionnaire_pending`.
4. Resolve the tenant's enabled `md_integrations` configuration and the stored
  MDI patient link.
5. Upload any submitted ID verification file to MDI as a `driver-license` file.
6. Build the MDI patient PATCH payload from the JotForm answers using the same
  patient-profile mapping rules as the patient questionnaire flow.
7. PATCH the MDI patient profile.
8. Advance the order to the current status' configured `next_status_id`.
9. Trigger `order-lifecycle` for the order when the status advance succeeds.

For this medical questionnaire path, uploaded file ids are **not** mapped to
`driver_license_id` on the MDI patient profile.

#### Legacy Events (Status-Flow Dependent)

| `global_status` | Action |
| --- | --- |
| `order.linked` | Adds `ids.patient_platform_order_id` to the linked Stripe PaymentIntent metadata as `patient_platform_order_id`, then advances the matched order only when its current status key is `order_created`; if valid, moves to `order_created.next_status_id` and then triggers `order-lifecycle` |
| `order.fulfillment_linked` | Advances the matched order only when its current status key is `provider_order_creation_pending`; if valid, moves to that status key's configured `next_status_id` and triggers `order-lifecycle` |

For `order.linked`, Stripe metadata sync is best-effort and non-blocking for the webhook response. The webhook resolves the Stripe PaymentIntent from the order's Stripe payment transaction first, then falls back to `payment.payment_intent_id`. When a PaymentIntent reference and tenant Stripe `secret_key` are available, the webhook updates the Stripe PaymentIntent with:

```json
{
  "metadata": {
    "patient_platform_order_id": "<ids.patient_platform_order_id>"
  }
}
```

#### Direct Status Events (Status-by-Event-Type)

The following `global_status` values set the order status **directly** to the same value (as an `order_statuses.status_key`). After the status is updated and history is recorded, the `order-lifecycle` function is triggered.

| `global_status` | Target Status Key | Notes |
| --- | --- | --- |
| `provider_review_pending` | `provider_review_pending` | Provider review stage initiated |
| `provider_approved` | `provider_approved` | Provider has approved the order |
| `provider_rejected` | `provider_rejected` | Provider rejected the order; after the status update, `order-lifecycle` is triggered to cancel/release the Stripe payment intent when applicable and cancel the linked plan |
| `payment_pending` | `payment_pending` | Payment processing initiated |
| `payment_collected` | `payment_collected` | Payment has been successfully collected |
| `payment_failed` | `payment_failed` | Payment failed or was declined |
| `order_sent_to_pharmacy` | `order_sent_to_pharmacy` | Order dispatched to pharmacy |
| `pharmacy_approval_pending` | `pharmacy_approval_pending` | Waiting for pharmacy approval |
| `pharmacy_approved` | `pharmacy_approved` | Pharmacy has approved the order |
| `fulfillment_in_progress` | `fulfillment_in_progress` | Pharmacy is fulfilling the order |
| `final_pharmacy_verification` | `final_pharmacy_verification` | Final verification before shipment |
| `in_transit` | `in_transit` | Order is in transit to patient |
| `delivered` | `delivered` | Order has been delivered |
| `shipping_exception` | `shipping_exception` | EasyPost reported a delivery exception such as failure, cancellation, or error |

For each direct status event, the webhook:
1. Fetches the order by `ids.patient_platform_order_id`
2. Resolves the target `order_statuses` row by `status_key` matching `global_status`
3. Updates the order's `status_id` and `status_changed_at`
4. Records an `order_status_history` entry with a transition note
5. Triggers the `order-lifecycle` function for downstream processing

---

## Responses

### Success

- **Status:** `200`
- **Body:**

```json
{
  "received": true,
  "requestId": "<request-id>",
  "eventType": "<global_status>",
  "actionResult": {
    "action": "<resolved_dispatch_type>",
    "orderId": "<order-id or null>",
    "statusAdvanced": true,
    "lifecycleTriggered": true,
    "stripeMetadataSynced": true
  }
}
```

`stripeMetadataSynced` is returned for `order.linked` actions.

Renewal intent success response keeps the legacy envelope and returns `orderId` inside `actionResult`:

```json
{
  "received": true,
  "requestId": "<request-id>",
  "eventType": "renewal_order_create",
  "actionResult": {
    "action": "renewal_order_create",
    "orderId": "<order-id>",
    "created": true,
    "resolutionStrategy": "subscription",
    "lifecycleTriggered": true
  }
}
```

For idempotent renewal replays where an existing order is returned, `created` is `false` and `lifecycleTriggered` is `false`.

### Error Codes

- `400 invalid_json`: request body is not a valid JSON object
- `400 validation_error`: required fields are missing or invalid
- `401 unauthorized`: secret check failed
- `404 not_found`: unsupported route
- `405 method_not_allowed`: non-POST call to `/event`
- `422 reference_not_found`: one or more referenced entities (tenant, order, patient, plan, transaction, integration) do not exist
- `422 unsupported_event_type`: `global_status` is missing/empty or not supported

---

## Operational Notes

1. Deploy function as public webhook endpoint (typically without JWT verification):

```bash
supabase functions deploy rtdh-webhook --no-verify-jwt --project-ref <project-ref>
```

1. Apply migrations before receiving traffic. Provider chat notifications require the `patient_notifications` table:

```bash
supabase db push
```

1. Logging policy:
   - Every inbound event is logged with full body data via console output.
   - Logs include `requestId`, raw content, parse errors (if any), and parsed payload.
