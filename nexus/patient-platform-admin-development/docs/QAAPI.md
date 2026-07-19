# QA API Documentation

> **Version:** 1.3.0 **Last Updated:** July 2026 **Audience:** QA Engineers,
> Platform Developers, Ops Teams

This document describes the `qa-api` Edge Function. It exists to create a
synthetic Stripe-backed order flow for QA in non-live environments without going
through the full frontend checkout flow, and to expose a small development and
staging-only order lookup utility.

The function is intentionally narrow:

- Synthetic QA routes are only enabled in non-live Supabase environments
- `provider_platform_order_id` is only enabled in development and staging
  environments
- It requires a shared QA secret
- It currently supports three routes:
  `POST /qa-api/new_order/{product-id}/{user-email}` and
  `POST /qa-api/approve_order_prescription/{order-id}`, and
  `GET /qa-api/provider_platform_order_id/{order-id}`
- It creates an address-complete local order, links a manual-capture
  PaymentIntent, dispatches the normal RTDH `create-order` lifecycle, authorizes
  the Stripe test payment, and returns the resulting order details
- It can also trigger Telegra's prescription approval action for an existing
  Telegra-backed order in development and staging only
- It can expose the provider platform order id associated with an existing
  platform order

---

## Table of Contents

1. [Overview](#overview)
2. [Base Configuration](#base-configuration)
3. [Authentication](#authentication)
4. [Endpoints](#endpoints)
5. [Execution Flow](#execution-flow)
6. [Response Model](#response-model)
7. [Error Handling](#error-handling)
8. [Implementation Notes](#implementation-notes)

---

## Overview

The QA API is an internal testing utility for quickly creating orders against
real tenant/product configuration in development and staging.

For `new_order`, the function:

1. Verifies the request is running in a non-live environment
2. Validates the shared QA secret from `Authorization: Bearer ...` or
   `x-qa-api-key`
3. Loads the enabled product by `product-id`
4. Loads the verified patient by `user-email` inside the product's tenant
5. Resolves a complete shipping address from the request or patient profile and
   copies it to the order's billing fields
6. Resolves any configured QA discount from the request or product metadata
7. Creates the local order and initial status history before creating Stripe
   payment objects
8. Creates or reuses a Stripe customer
9. For a positive total, creates an unconfirmed manual-capture PaymentIntent
   containing `patient_platform_order_id`, then persists its local transaction
10. Calls `order-lifecycle`, which dispatches the normal RTDH `create-order`
    event while the local order and payment correlation already exist
11. Confirms the PaymentIntent with Stripe test card `pm_card_visa` and persists
    its `requires_capture` authorization status
12. For a zero-value subscription, confirms a SetupIntent instead so a payment
    method is available for renewal; zero-value one-time orders need no intent
13. Returns the current order status and whether the asynchronous RTDH link is
    still pending

The QA path no longer creates a hosted Checkout Session, replays a synthetic
`checkout.session.completed` event, or creates a Stripe subscription before
clinical approval. Stripe subscriptions are created by the normal lifecycle at
payment capture, matching the embedded checkout.

For `approve_order_prescription`, the function:

1. Verifies the request is running specifically in development or staging
2. Validates the shared QA secret from `Authorization: Bearer ...` or
   `x-qa-api-key`
3. Loads the order by `order-id`
4. Loads the order's selected provider-platform link
5. Validates that the order is linked to a Telegra provider-platform integration
6. Resolves the Telegra base URL and access token from the tenant integration
7. Calls Telegra
   `POST /orders/{TELEGRA_ORDER_ID}/actions/lifecycleProcessor/approvePrescription/?access_token={TELEGRA_ACCESS_TOKEN}`
8. Returns `200` when Telegra accepts the call

For `provider_platform_order_id`, the function:

1. Verifies the request is running in a development or staging environment
2. Validates the shared QA secret from `Authorization: Bearer ...` or
   `x-qa-api-key`
3. Loads the order by `order-id`
4. Returns `orders.provider_platform_order_id`, falling back to the newest
   `order_provider_platform_links.provider_order_id` when the order snapshot is
   empty

This function is designed for QA automation, manual testing, and narrow internal
order diagnostics. The synthetic order and prescription routes should not be
exposed to live environments.

---

## Base Configuration

### Base URL

```text
VITE_SUPABASE_URL/functions/v1/qa-api
```

### Supported Methods

| Method    | Supported |
| --------- | --------- |
| `GET`     | Yes       |
| `POST`    | Yes       |
| `OPTIONS` | Yes       |
| `PUT`     | No        |
| `DELETE`  | No        |

`GET` is supported for `new_order` and `provider_platform_order_id`.
`approve_order_prescription` only accepts `POST`.

### Required Headers

| Header          | Description                                          | Required    |
| --------------- | ---------------------------------------------------- | ----------- |
| `apikey`        | Supabase anon key                                    | Yes         |
| `Authorization` | `Bearer <QA_API_KEY>` shared secret                  | Yes\*       |
| `x-qa-api-key`  | Alternative way to send the shared secret            | Yes\*       |
| `x-request-id`  | Optional caller-supplied request id for traceability | No          |
| `Content-Type`  | `application/json`                                   | Recommended |

\* Provide either `Authorization` or `x-qa-api-key`.

### Edge Function Environment Variables

| Variable                     | Description                                                                                              |
| ---------------------------- | -------------------------------------------------------------------------------------------------------- |
| `SUPABASE_URL`               | Supabase project URL                                                                                     |
| `SUPABASE_SERVICE_ROLE_KEY`  | Service role key used for admin table access                                                             |
| `QA_API_KEY`                 | Shared secret required to call the function                                                              |

### Environment Restriction

Synthetic QA routes refuse to run in live environments and return `403` with:

```json
{
  "error": "qa-api is only enabled in non-live environments"
}
```

Environment detection is delegated to `isNonLiveEnvironment()` in
[`supabase/functions/_shared/environment.ts`](../supabase/functions/_shared/environment.ts).

The `provider_platform_order_id` route is intentionally separate: it only runs
when the current environment resolves to development or staging. Production and
other environments return `403` with:

```json
{
  "error": "provider_platform_order_id is only enabled in development and staging environments"
}
```

### Endpoint-Specific Restriction

`approve_order_prescription` is narrower than the rest of the function: it only
runs when the current environment resolves to development or staging. In other
non-live environments it returns `403` with:

```json
{
  "error": "approve_order_prescription is only enabled in development and staging environments"
}
```

---

## Authentication

The function does not use a Supabase user JWT. It uses a shared QA secret.

Supported auth formats:

1. `Authorization: Bearer <QA_API_KEY>`
2. `x-qa-api-key: <QA_API_KEY>`

If the provided secret is missing or incorrect, the function returns:

```json
{
  "error": "Unauthorized"
}
```

with HTTP `401`.

---

## Endpoints

### Create QA Order

```http
POST /functions/v1/qa-api/new_order/{product-id}/{user-email}
GET  /functions/v1/qa-api/new_order/{product-id}/{user-email}
```

### Path Parameters

| Parameter    | Type   | Required | Description                                                                 |
| ------------ | ------ | -------- | --------------------------------------------------------------------------- |
| `product-id` | UUID   | Yes      | Enabled product id from `products.id`                                       |
| `user-email` | string | Yes      | Patient email address; matched case-insensitively within the product tenant |

### Optional Coupon Parameters

`new_order` accepts coupon inputs either as query parameters or as a JSON body.
Explicit values override the product default coupon.

| Parameter            | Aliases                    | Type    | Description                                                                                                              |
| -------------------- | -------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------ |
| `promotion_code_id`  | `stripe_promotion_code_id` | string  | Stripe Promotion Code id, for example `promo_...`                                                                        |
| `promotion_code`     | `coupon_code`, `code`      | string  | Visible promotion code, for example `OFFER100`                                                                           |
| `coupon_id`          | `stripe_coupon_id`         | string  | Stripe Coupon id, for example `coupon_...`                                                                               |
| `use_default_coupon` | -                          | boolean | Defaults to `true`; when enabled and no explicit coupon input is sent, uses `products.metadata.stripe_promotion_code_id` |

Send only one of `promotion_code_id`, `promotion_code`, or `coupon_id`.

### Shipping Address

The patient must have a complete shipping address on their profile, or a POST
request must provide `shipping_address` in the JSON body. Request fields override
profile fields. Primary patient address fields are used as a fallback when the
shipping-specific profile fields are empty.

Required fields are `first_name`, `last_name`, `line1`, `city`, `state`,
`postal_code`, and `country`. Optional fields are `company`, `line2`, and
`instructions`. The resolved shipping address is saved to the patient and copied
to both the shipping and billing fields on the new order.

When a coupon is applied:

- The QA Payment Intent amount is reduced to the discounted total
- The created order stores `subtotal_cents`, `discount_cents`, `total_cents`,
  `coupon_code`, and `coupon_name`
- Subscription discounts are applied later by the normal capture-time
  subscription creation logic
- For a 100% discount, no PaymentIntent is created. Subscription products use a
  SetupIntent to save the test card; one-time products need no Stripe intent.

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/qa-api/new_order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/patient@example.com" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <qa-api-key>" \
  -H "Content-Type: application/json" \
  -H "x-request-id: qa-run-001"
```

### Example Request With Promotion Code Id

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/qa-api/new_order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/patient@example.com?promotion_code_id=promo_123" \
  -H "apikey: <supabase-anon-key>" \
  -H "x-qa-api-key: <qa-api-key>" \
  -H "x-request-id: qa-run-coupon-001"
```

### Example Request With Coupon Code Body

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/qa-api/new_order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/patient@example.com" \
  -H "apikey: <supabase-anon-key>" \
  -H "x-qa-api-key: <qa-api-key>" \
  -H "Content-Type: application/json" \
  -H "x-request-id: qa-run-coupon-002" \
  -d '{
    "coupon_code": "OFFER100",
    "shipping_address": {
      "first_name": "QA",
      "last_name": "Patient",
      "line1": "123 Test Street",
      "city": "Denver",
      "state": "CO",
      "postal_code": "80202",
      "country": "US"
    }
  }'
```

### Example Request Without Default Coupon

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/qa-api/new_order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/patient@example.com?use_default_coupon=false" \
  -H "apikey: <supabase-anon-key>" \
  -H "x-qa-api-key: <qa-api-key>"
```

### Successful Response

**Response:** `200 OK`

```json
{
  "message": "QA order created",
  "data": {
    "tenant_id": "8d1df6b2-b8e5-4d08-9668-6f1041e02f8a",
    "patient_id": "77d35d4d-35fb-4235-8d34-4fc0c9b2f041",
    "product_id": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
    "amount_subtotal_cents": 49900,
    "amount_discount_cents": 10000,
    "amount_total_cents": 39900,
    "coupon_id": "coupon_123",
    "promotion_code_id": "promo_123",
    "promotion_code": "OFFER100",
    "coupon_duration": "forever",
    "stripe_customer_id": "cus_123",
    "stripe_payment_method_id": "pm_card_visa",
    "stripe_payment_intent_id": "pi_123",
    "stripe_payment_intent_status": "requires_capture",
    "stripe_setup_intent_id": null,
    "stripe_setup_intent_status": null,
    "lifecycle_response": {
      "action": "no_change",
      "message": "Order is in order_created; RTDH create-order webhook dispatch attempted"
    },
    "order_status": "order_created",
    "lifecycle_pending": true,
    "order_created": true,
    "order": {
      "id": "c2b17e88-4801-4304-b0a4-0da7dfdca2ef",
      "order_number": "ORD-ML5IG2IW-P142",
      "subscription_id": null,
      "paid_at": null,
      "renewal_at": null
    }
  }
}
```

### Preconditions

The route succeeds only when all of the following are true:

- The product exists and `products.is_enabled = true`
- The patient exists in the same tenant as the product
- The patient has `email_verified_at` set
- A complete shipping address exists either in the POST body or patient profile
- The tenant has an enabled Stripe payment provider row in
  `tenant_payment_providers`
- The Stripe provider has `settings.secret_key`
- The normal order lifecycle can dispatch RTDH `create-order`
- If a coupon is requested, the Stripe promotion code/coupon exists, is usable,
  and matches the product scope when product metadata or `applies_to` is present

### Approve Order Prescription

```http
POST /functions/v1/qa-api/approve_order_prescription/{order-id}
```

### Path Parameters

| Parameter  | Type | Required | Description                                 |
| ---------- | ---- | -------- | ------------------------------------------- |
| `order-id` | UUID | Yes      | Existing platform order id from `orders.id` |

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/qa-api/approve_order_prescription/c2b17e88-4801-4304-b0a4-0da7dfdca2ef" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <qa-api-key>" \
  -H "Content-Type: application/json" \
  -H "x-request-id: qa-approve-001"
```

### Successful Response

**Response:** `200 OK`

```json
{
  "message": "Prescription approved",
  "data": {
    "order_id": "c2b17e88-4801-4304-b0a4-0da7dfdca2ef",
    "telegra_order_id": "order::12345678",
    "telegra_response": {
      "success": true
    }
  }
}
```

### Preconditions

The route succeeds only when all of the following are true:

- The current environment resolves to development or staging
- The order exists
- The order is linked to a Telegra provider-platform integration
- The selected Telegra provider link has a valid `provider_order_id`
- The Telegra integration has a configured `settings.url`
- Telegra authentication succeeds through configured `username/password` or
  `access_token`
- The downstream Telegra approval endpoint accepts the request

### Get Provider Platform Order Id

```http
GET /functions/v1/qa-api/provider_platform_order_id/{order-id}
```

### Path Parameters

| Parameter  | Type | Required | Description                                 |
| ---------- | ---- | -------- | ------------------------------------------- |
| `order-id` | UUID | Yes      | Existing platform order id from `orders.id` |

### Example Request

```bash
curl -X GET \
  "VITE_SUPABASE_URL/functions/v1/qa-api/provider_platform_order_id/c2b17e88-4801-4304-b0a4-0da7dfdca2ef" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <qa-api-key>" \
  -H "x-request-id: qa-provider-id-001"
```

### Successful Response

**Response:** `200 OK`

```json
{
  "message": "Provider platform order id retrieved",
  "data": {
    "order_id": "c2b17e88-4801-4304-b0a4-0da7dfdca2ef",
    "provider_platform_order_id": "order::12345678"
  }
}
```

If the order exists but has not been assigned a provider platform order id yet,
`provider_platform_order_id` is returned as `null`.

### Preconditions

The route succeeds only when all of the following are true:

- The current environment resolves to development or staging
- The order exists
- The request uses `GET`

---

## Execution Flow

### Get Provider Platform Order Id

The function reads:

- `orders.provider_platform_order_id` for the target order
- `order_provider_platform_links.provider_order_id` as a fallback when the order
  snapshot is empty

The fallback chooses the newest non-null provider link by `updated_at`, then
`created_at`.

### Approve Order Prescription

The function reads:

- `orders` for the target order and its tenant
- `order_provider_platform_links` for the selected provider-platform record
- `tenant_integrations` for the linked Telegra configuration

Telegra validation accepts either:

- an enabled `tenant_integrations.integration_key = telegramd`, or
- provider metadata that normalizes to `telegra` / `telegramd`

The function then:

- validates that the stored provider order id is a Telegra-scoped id
- resolves the Telegra access token with the shared auth helper
- calls the Telegra lifecycle approval endpoint with the token in the query
  string as `access_token`
- returns the parsed Telegra response payload

If the order is not Telegra-backed, the function returns `400`.

### Product and Patient Resolution

The function reads:

- `products` for the product metadata and tenant ownership
- `patients` for the matching verified email and address fields in the same
  tenant

If the product or patient is missing, it returns `404`.

An unverified patient returns `409`. An incomplete address returns `400` with a
`missing_fields` array unless the POST body supplies the missing fields.

### Order-First Correlation

The local order is created before Stripe confirmation. It includes complete
shipping and billing addresses, totals, coupon data, and the initial
`order_created` history row.

For positive totals, the function then creates an unconfirmed PaymentIntent with
`metadata.patient_platform_order_id` and inserts the corresponding
`order_payment_provider_transactions` row. Only after those records exist does
it invoke `order-lifecycle`. This lets RTDH `create-order` correlate by both the
platform order id and PaymentIntent id without a Checkout Session race.

### Stripe Behavior

The function:

- Reuses `patients.metadata.stripe_customer_id` when present
- Otherwise creates a Stripe customer with tenant and patient metadata and
  caches the id on the patient
- Creates an unconfirmed manual-capture PaymentIntent when the discounted total
  is greater than zero
- Stores tenant, product, patient, email, and `patient_platform_order_id`
  metadata on the PaymentIntent
- Persists the PaymentIntent transaction before dispatching RTDH `create-order`
- Confirms the PaymentIntent afterward and requires `status=requires_capture`
- Uses test payment method `pm_card_visa`
- Sets `setup_future_usage=off_session` for subscription products
- Uses a confirmed SetupIntent for zero-value subscriptions
- Does not create a Stripe subscription during QA order creation; the normal
  capture-time lifecycle creates it after clinical approval

### Discount Behavior

For `new_order`, the function resolves discounts in this order:

1. Explicit `promotion_code_id` / `stripe_promotion_code_id`
2. Explicit `promotion_code` / `coupon_code` / `code`
3. Explicit `coupon_id` / `stripe_coupon_id`
4. Default `products.metadata.stripe_promotion_code_id`, when
   `use_default_coupon` is not false

The function rejects requests that send more than one explicit discount input.
It also validates inactive, expired, fully redeemed, invalid, or
product-mismatched discounts before continuing.

The coupon id/code remains on the order so the normal subscription creation at
capture can apply the configured Stripe coupon duration to renewals.

### Lifecycle Result

The QA API treats a non-success response from `order-lifecycle` as a request
failure rather than logging it and returning a false success. Its response also
includes the lifecycle payload, current `order_status`, and
`lifecycle_pending`. `lifecycle_pending=true` means RTDH has accepted or is still
processing the asynchronous `create-order` link and the order remains at
`order_created` at response time.

---

## Response Model

### Top-Level Response

| Field     | Type   | Description                            |
| --------- | ------ | -------------------------------------- |
| `message` | string | Success message for the executed route |
| `data`    | object | Result payload                         |

### `new_order` Data Fields

| Field                          | Type           | Description                                                                    |
| ------------------------------ | -------------- | ------------------------------------------------------------------------------ |
| `tenant_id`                    | UUID           | Tenant owning the product                                                      |
| `patient_id`                   | UUID           | Matched patient id                                                             |
| `product_id`                   | UUID           | Requested product id                                                           |
| `amount_subtotal_cents`        | number         | Original product price in cents                                                |
| `amount_discount_cents`        | number         | Applied discount amount in cents                                               |
| `amount_total_cents`           | number         | Discounted total in cents                                                      |
| `coupon_id`                    | string \| null | Resolved Stripe Coupon id                                                      |
| `promotion_code_id`            | string \| null | Resolved Stripe Promotion Code id                                              |
| `promotion_code`               | string \| null | Resolved visible promotion code                                                |
| `coupon_duration`              | string \| null | Stripe coupon duration (`once`, `repeating`, or `forever`)                     |
| `stripe_customer_id`           | string \| null | Stripe customer id; null for a zero-value one-time order                        |
| `stripe_payment_method_id`     | string \| null | Attached default payment method id; null for a zero-value one-time order        |
| `stripe_payment_intent_id`     | string \| null | Confirmed PaymentIntent id; null for zero-value orders                          |
| `stripe_payment_intent_status` | string \| null | Payment intent status returned by Stripe                                       |
| `stripe_setup_intent_id`       | string \| null | SetupIntent id for a zero-value subscription                                   |
| `stripe_setup_intent_status`   | string \| null | SetupIntent status for a zero-value subscription                               |
| `lifecycle_response`           | object \| null | Parsed response from the initial order-lifecycle invocation                    |
| `order_status`                 | string \| null | Current order status after lifecycle dispatch and payment authorization         |
| `lifecycle_pending`            | boolean        | Whether the order is still awaiting asynchronous RTDH linking                  |
| `order_created`                | boolean        | Always true after successful local order insertion                             |
| `order`                        | object         | Created order summary                                                          |

### `approve_order_prescription` Data Fields

| Field              | Type                     | Description                      |
| ------------------ | ------------------------ | -------------------------------- |
| `order_id`         | UUID                     | Requested platform order id      |
| `telegra_order_id` | string                   | Linked Telegra provider order id |
| `telegra_response` | object \| string \| null | Parsed Telegra response body     |

### `provider_platform_order_id` Data Fields

| Field                        | Type           | Description                                                                      |
| ---------------------------- | -------------- | -------------------------------------------------------------------------------- |
| `order_id`                   | UUID           | Requested platform order id                                                      |
| `provider_platform_order_id` | string \| null | Provider platform order id stored on the order, or newest provider link fallback |

### `order` Fields

| Field             | Type              | Description                           |
| ----------------- | ----------------- | ------------------------------------- |
| `id`              | UUID              | Created order id                      |
| `order_number`    | string            | Human-readable order number           |
| `subscription_id` | UUID \| null      | Linked subscription id                |
| `paid_at`         | timestamp \| null | Paid timestamp on the order           |
| `renewal_at`      | timestamp \| null | Renewal timestamp for recurring flows |

---

## Error Handling

The function returns JSON errors for both validation failures and runtime
failures.

### Common Error Responses

| Status | Error                                                                                                                                                               |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `400`  | `Both product-id and user-email are required`                                                                                                                       |
| `400`  | `A complete shipping address is required for QA orders`                                                                                                             |
| `400`  | `order-id is required`                                                                                                                                              |
| `400`  | `Stripe is not configured for this tenant`                                                                                                                          |
| `400`  | `Order is not linked to a Telegra provider platform integration`                                                                                                    |
| `400`  | `Telegra provider order id is missing or invalid for this order`                                                                                                    |
| `400`  | `Telegra integration is missing URL configuration`                                                                                                                  |
| `400`  | `Telegra authentication failed: ...`                                                                                                                                |
| `401`  | `Unauthorized`                                                                                                                                                      |
| `409`  | `QA patient email must be verified before an order can enter provider intake`                                                                                       |
| `403`  | `qa-api is only enabled in non-live environments`                                                                                                                   |
| `403`  | `approve_order_prescription is only enabled in development and staging environments`                                                                                |
| `403`  | `provider_platform_order_id is only enabled in development and staging environments`                                                                                |
| `404`  | `Route not found. Use /qa-api/new_order/{product-id}/{user-email}, /qa-api/approve_order_prescription/{order-id}, or /qa-api/provider_platform_order_id/{order-id}` |
| `404`  | `Order not found`                                                                                                                                                   |
| `404`  | `Product not found`                                                                                                                                                 |
| `404`  | `Patient not found for product tenant`                                                                                                                              |
| `405`  | `Method not allowed`                                                                                                                                                |
| `500`  | `Missing Supabase environment configuration`                                                                                                                        |
| `500`  | `QA_API_KEY is not configured`                                                                                                                                      |
| `500`  | Stripe, Supabase, or order-lifecycle execution failures                                                                                                             |

### Discount Validation Errors

Discount validation failures are returned through the standard runtime failure
shape with `request_id`. Common messages include:

| Error                                                                     |
| ------------------------------------------------------------------------- |
| `qa_coupon_conflict: use promotion_code_id, promotion_code, or coupon_id` |
| `qa_coupon_conflict: use promotion_code_id or promotion_code, not both`   |
| `qa_promotion_code_not_found`                                             |
| `qa_promotion_code_inactive`                                              |
| `qa_promotion_code_expired`                                               |
| `qa_promotion_code_fully_redeemed`                                        |
| `qa_coupon_invalid`                                                       |
| `qa_coupon_product_mismatch`                                              |

### Runtime Failure Shape

Unhandled exceptions return:

```json
{
  "error": "order_lifecycle_failed:500:...",
  "request_id": "c94b0560-79c3-4c53-bdda-fcdb3053fb42"
}
```

The `request_id` is either the caller-provided `x-request-id` or a generated
UUID.

---

## Implementation Notes

- The function strips `/functions/v1` and `/qa-api` prefixes internally, so it
  accepts the deployed Supabase path shape directly.
- All routes are path-based. `new_order` also accepts optional coupon controls
  through query parameters or a JSON body, and an optional `shipping_address`
  through the JSON body.
- It lowercases and trims the incoming `user-email` before lookup.
- It uses the service role key and bypasses end-user RLS.
- Positive-value PaymentIntents use manual capture. `requires_capture` means the
  QA payment is authorized and may pass the checkout resume gate; `paid_at`
  remains null until clinical approval triggers capture.
- The function intentionally creates no Checkout Session and sends no synthetic
  Stripe webhook. Normal Stripe and RTDH event delivery handles subsequent
  transitions.
- `approve_order_prescription` resolves Telegra auth via the shared
  [`telegra-auth`](../supabase/functions/_shared/telegra-auth.ts) helper but
  sends the Telegra lifecycle call using the token in the query string to match
  the required provider contract.
- The function logs failures as `qa-api new_order failed` with the resolved
  `requestId`, including failures from `approve_order_prescription`.
