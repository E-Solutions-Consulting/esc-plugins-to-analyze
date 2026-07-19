# Plan API Documentation

> **Version:** 1.1.0\
> **Last Updated:** February 2026\
> **Audience:** Patient UI Developers

This document contains Plan API endpoints for patient order and checkout flows.

Related sequence diagram:
[Order Flow Sequence](./SequenceDiagrams.md#order-flow-sequence).

## Orders

> 💡 **Recommended:** Use the **Plan API Edge Function** for all order
> operations. It provides proper validation, tenant scoping, and security.

**Orders Base URL**

```
VITE_SUPABASE_URL/functions/v1/plan-api
```

#### Create Order (Plan API)

Creates a new order for the authenticated patient.

```http
POST /functions/v1/plan-api/orders
Content-Type: application/json
Authorization: Bearer <access_token>
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "product_id": "product-uuid",
  "shipping_address": {
    "first_name": "John",
    "last_name": "Doe",
    "company": "Acme Corp",
    "line1": "123 Main Street",
    "line2": "Apt 4B",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "US",
    "instructions": "Leave at front door"
  }
}
```

**Request Body:**

| Field                           | Type   | Required | Description                  |
| ------------------------------- | ------ | -------- | ---------------------------- |
| `product_id`                    | string | Yes      | UUID of the product to order |
| `shipping_address`              | object | Yes      | Shipping address details     |
| `shipping_address.first_name`   | string | No       | Recipient first name         |
| `shipping_address.last_name`    | string | No       | Recipient last name          |
| `shipping_address.company`      | string | No       | Company name                 |
| `shipping_address.line1`        | string | Yes      | Street address line 1        |
| `shipping_address.line2`        | string | No       | Street address line 2        |
| `shipping_address.city`         | string | Yes      | City                         |
| `shipping_address.state`        | string | Yes      | State/Province               |
| `shipping_address.postal_code`  | string | Yes      | Postal/ZIP code              |
| `shipping_address.country`      | string | No       | Country code (default: "US") |
| `shipping_address.instructions` | string | No       | Delivery instructions        |

> Address fields are never auto-populated from customer/profile data during
> order creation. To add or change billing/shipping later, use
> `PATCH /functions/v1/plan-api/orders/{order_id}/address`.

**Response:** `201 Created`

```json
{
  "message": "Order created successfully",
  "data": {
    "id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "status": "order_created",
    "status_id": "status-uuid-1",
    "status_details": {
      "id": "status-uuid-1",
      "key": "order_created",
      "label": "Order started",
      "description": "We've created your order and are getting things ready.",
      "action_required": false,
      "is_final": false,
      "display_order": 1
    },
    "product": {
      "id": "product-uuid",
      "name": "Testosterone Therapy - Monthly",
      "price_cents": 19900,
      "terms_and_conditions_html": "<p>Terms and conditions...</p>",
      "subscription_renewal_lead_days": 7
    },
    "subtotal_cents": 19900,
    "shipping_cents": 0,
    "tax_cents": 0,
    "total_cents": 19900,
    "total_formatted": "$199.00",
    "subscription_order_type": "new",
    "shipping_address": {
      "first_name": "John",
      "last_name": "Doe",
      "company": "Acme Corp",
      "line1": "123 Main Street",
      "line2": "Apt 4B",
      "city": "New York",
      "state": "NY",
      "postal_code": "10001",
      "country": "US",
      "instructions": "Leave at front door"
    },
    "renewal_at": null,
    "created_at": "2026-01-26T10:30:00Z"
  }
}
```

**Error Responses:**

| Code | Error               | Description                              |
| ---- | ------------------- | ---------------------------------------- |
| 400  | `MISSING_FIELDS`    | Required fields not provided             |
| 400  | `INVALID_ADDRESS`   | Address missing required fields          |
| 401  | `UNAUTHORIZED`      | Missing or invalid authorization         |
| 403  | `ACCOUNT_INACTIVE`  | Patient account is suspended/deactivated |
| 404  | `PRODUCT_NOT_FOUND` | Product not found or not available       |

---

#### List Patient Orders (Plan API)

Returns paginated list of orders for the authenticated patient.

```http
GET /functions/v1/plan-api/orders
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Query Parameters:**

| Parameter    | Type    | Default | Description              |
| ------------ | ------- | ------- | ------------------------ |
| `page`       | integer | 1       | Page number              |
| `page_size`  | integer | 20      | Items per page (max 100) |
| `status_id`  | string  | -       | Filter by status UUID    |
| `status_key` | string  | -       | Filter by status key     |

**Response:**

```json
{
  "data": [
    {
      "id": "order-uuid-1",
      "order_number": "ORD-ABC123-XYZ9",
      "status_id": "status-uuid",
      "status_details": {
        "id": "status-uuid",
        "key": "shipped",
        "label": "On the way",
        "description": "Your order has shipped and is on its way to you.",
        "action_required": false,
        "is_final": false,
        "display_order": 11
      },
      "status_changed_at": "2026-01-27T14:20:00Z",
      "subtotal_cents": 19900,
      "shipping_cents": 0,
      "tax_cents": 0,
      "total_cents": 19900,
      "total_formatted": "$199.00",
      "tracking": {
        "number": "1Z999AA10123456784",
        "url": "https://track.carrier.com/1Z999AA10123456784"
      },
      "shipped_at": "2026-01-27T14:20:00Z",
      "delivered_at": null,
      "cancelled_at": null,
      "cancellation_reason": null,
      "paused_at": null,
      "renewal_at": "2026-02-21T00:00:00Z",
      "expires_at": "2026-02-28T00:00:00Z",
      "created_at": "2026-01-26T10:30:00Z",
      "updated_at": "2026-01-27T14:20:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "page_size": 20,
    "total": 5,
    "total_pages": 1,
    "has_more": false
  }
}
```

**Response Fields:**

| Field                            | Type           | Description                                                                            |
| -------------------------------- | -------------- | -------------------------------------------------------------------------------------- |
| `status_id`                      | string         | Status UUID                                                                            |
| `status_details`                 | object         | Rich status information from order_statuses table                                      |
| `status_details.id`              | string         | Status UUID                                                                            |
| `status_details.key`             | string         | Unique status identifier (snake_case)                                                  |
| `status_details.label`           | string         | Patient-facing display label                                                           |
| `status_details.description`     | string         | Patient-facing description/microcopy                                                   |
| `status_details.action_required` | boolean        | `true` if patient needs to take action                                                 |
| `status_details.is_final`        | boolean        | `true` if this is a terminal status                                                    |
| `status_details.display_order`   | number         | Order for display in progress tracker                                                  |
| `status_changed_at`              | string         | Timestamp of last status change                                                        |
| `cancelled_at`                   | string \| null | Timestamp when the order was cancelled, or `null`                                      |
| `cancellation_reason`            | string \| null | Patient-provided reason for cancellation, or `null`                                    |
| `renewal_at`                     | string \| null | Next renewal date from `orders.subscription_id -> subscriptions.current_period_end_at` |
| `expires_at`                     | string \| null | Plan expiration date from `orders.subscription_id -> subscriptions.expires_at`         |

> Note: `renewal_at` and `expires_at` are sourced from the linked subscription
> lifecycle record and do not fall back to legacy order columns.

---

#### Get Order Details (Plan API)

Returns full details of a specific order.

```http
GET /functions/v1/plan-api/orders/{order_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "status_id": "status-uuid",
    "product_id": "product-uuid",
    "status_details": {
      "id": "status-uuid",
      "key": "shipped",
      "label": "On the way",
      "description": "Your order has shipped and is on its way to you.",
      "action_required": false,
      "is_final": false,
      "display_order": 11
    },
    "status_changed_at": "2026-01-27T14:20:00Z",
    "status_history": [
      {
        "id": "history-uuid-1",
        "timestamp": "2026-01-26T10:30:00Z",
        "status": {
          "id": "status-uuid-1",
          "key": "order_created",
          "label": "Order started",
          "description": "We've created your order and are getting things ready.",
          "action_required": false,
          "is_final": false
        }
      },
      {
        "id": "history-uuid-2",
        "timestamp": "2026-01-26T10:31:00Z",
        "status": {
          "id": "status-uuid-2",
          "key": "payment_received",
          "label": "Payment confirmed",
          "description": "Your payment was successful and your order is moving forward.",
          "action_required": false,
          "is_final": false
        }
      },
      {
        "id": "history-uuid-3",
        "timestamp": "2026-01-27T14:20:00Z",
        "status": {
          "id": "status-uuid-3",
          "key": "shipped",
          "label": "On the way",
          "description": "Your order has shipped and is on its way to you.",
          "action_required": false,
          "is_final": false
        }
      }
    ],
    "subtotal_cents": 19900,
    "shipping_cents": 0,
    "tax_cents": 0,
    "total_cents": 19900,
    "total_formatted": "$199.00",
    "shipping_address": {
      "first_name": "John",
      "last_name": "Doe",
      "company": null,
      "line1": "123 Main Street",
      "line2": "Apt 4B",
      "city": "New York",
      "state": "NY",
      "postal_code": "10001",
      "country": "US",
      "instructions": "Leave at front door"
    },
    "billing_address": {
      "first_name": "John",
      "last_name": "Doe",
      "company": null,
      "line1": "123 Main Street",
      "line2": "Apt 4B",
      "city": "New York",
      "state": "NY",
      "postal_code": "10001",
      "country": "US"
    },
    "tracking": {
      "number": "1Z999AA10123456784",
      "url": "https://track.carrier.com/1Z999AA10123456784"
    },
    "shipped_at": "2026-01-27T14:20:00Z",
    "delivered_at": null,
    "cancelled_at": null,
    "cancellation_reason": null,
    "paused_at": null,
    "renewal_at": "2026-02-21T00:00:00Z",
    "expires_at": "2026-02-28T00:00:00Z",
    "created_at": "2026-01-26T10:30:00Z",
    "updated_at": "2026-01-27T14:20:00Z"
  }
}
```

**Response Fields:**

| Field                            | Type           | Description                                                                            |
| -------------------------------- | -------------- | -------------------------------------------------------------------------------------- |
| `status_id`                      | string         | Status UUID                                                                            |
| `product_id`                     | string \| null | Product UUID linked to the order (`orders.product_id`)                                 |
| `status_details`                 | object         | Rich status information from order_statuses table                                      |
| `status_details.key`             | string         | Unique status identifier (snake_case)                                                  |
| `status_details.label`           | string         | Patient-facing display label                                                           |
| `status_details.description`     | string         | Patient-facing description/microcopy                                                   |
| `status_details.action_required` | boolean        | `true` if patient needs to take action                                                 |
| `status_details.is_final`        | boolean        | `true` if this is a terminal status                                                    |
| `status_changed_at`              | string         | Timestamp of last status change                                                        |
| `status_history`                 | array          | Chronological list of all status transitions                                           |
| `status_history[].timestamp`     | string         | When the status changed                                                                |
| `status_history[].status`        | object         | Status details at that point in time                                                   |
| `cancelled_at`                   | string \| null | Timestamp when the order was cancelled, or `null`                                      |
| `cancellation_reason`            | string \| null | Patient-provided reason for cancellation, or `null`                                    |
| `renewal_at`                     | string \| null | Next renewal date from `orders.subscription_id -> subscriptions.current_period_end_at` |
| `expires_at`                     | string \| null | Plan expiration date from `orders.subscription_id -> subscriptions.expires_at`         |
| `billing_address`                | object         | Billing address details for the order                                                  |

---

#### Get Order Status History

Retrieves the complete status history for a specific order. Useful for building
order tracking timelines.

```http
GET /functions/v1/plan-api/orders/{order_id}/status-history
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "order_id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "current_status": "shipped",
    "status_changed_at": "2026-01-27T14:20:00Z",
    "history": [
      {
        "id": "history-uuid-1",
        "timestamp": "2026-01-26T10:30:00Z",
        "status": {
          "id": "status-uuid-1",
          "key": "order_created",
          "label": "Order started",
          "description": "We've created your order and are getting things ready.",
          "action_required": false,
          "is_final": false,
          "display_order": 1
        }
      }
    ],
    "total_transitions": 1
  }
}
```

**Response Fields:**

| Field                 | Type   | Description                                  |
| --------------------- | ------ | -------------------------------------------- |
| `order_id`            | string | The order UUID                               |
| `order_number`        | string | Human-readable order number                  |
| `current_status`      | string | Current status key                           |
| `status_changed_at`   | string | When the status last changed                 |
| `history`             | array  | Chronological list of all status transitions |
| `history[].id`        | string | Status history entry UUID                    |
| `history[].timestamp` | string | When the transition occurred                 |
| `history[].status`    | object | Status details at that transition            |
| `total_transitions`   | number | Total number of status changes               |

---

#### Cancel Order (Plan API)

Cancels an order in `order_created` status. Only orders with status
`order_created` can be cancelled by patients.

```http
PATCH /functions/v1/plan-api/orders/{order_id}
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "action": "cancel",
  "reason": "I no longer need this order."
}
```

`reason` is optional and is persisted as `orders.cancellation_reason`.

**Response:**

```json
{
  "message": "Order cancelled successfully",
  "data": {
    "id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "status": "order_cancelled",
    "cancelled_at": "2026-01-26T12:00:00Z",
    "cancellation_reason": "I no longer need this order."
  }
}
```

**Error Responses:**

| Code | Error             | Description                             |
| ---- | ----------------- | --------------------------------------- |
| 400  | `CANNOT_CANCEL`   | Order status doesn't allow cancellation |
| 404  | `ORDER_NOT_FOUND` | Order not found                         |

---

#### Request Order Cancellation

Requests an order cancellation. Most cancellable orders are moved to
`order_pending_cancellation` and immediately trigger `order-lifecycle` to
evaluate the cancellation workflow asynchronously.

```http
POST /functions/v1/plan-api/orders/{order_id}/cancel
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "reason": "I no longer need this order."
}
```

`reason` is optional and is persisted as `orders.cancellation_reason`.

When an MDI order is in `provider_review_pending`, the endpoint queues
`order_pending_cancellation` immediately so `order-lifecycle` can cancel the MDI
case before provider review completes. For held MDI questionnaire cases, the
lifecycle cancels the existing MDI case directly without releasing
`hold_status`. Non-MDI orders in `provider_review_pending` keep the existing
deferred behavior: the reason is stored and cancellation processing resumes
after the provider decision.

`order-lifecycle` now performs the cancellation workflow in stages:

- In `order_pending_cancellation`, it looks at the most recent non-cancellation
  status in `order_status_history` to determine refund eligibility.
- It writes a refund-eligibility note to `order_status_history`.
- If refund, Stripe, or MDI provider-side cancellation work is required, it
  advances the order to `order_cancellation_processing` and triggers
  `order-lifecycle` again.
- If no refund, Stripe, or provider-side action is required, it moves the order
  directly to `order_cancelled`.
- In `order_cancellation_processing`, it performs the refund and any applicable
  Stripe actions, updates the linked plan lifecycle, and performs supported
  provider-side cancellation:
  - Telegra: `POST /orders/{orderId}/actions/cancel` using the stored provider
    order id.
  - MDI: for held questionnaire cases or orders where provider cancellation is
    required, `POST /v1/partner/cases/{provider_order_id}/cancel` with reason
    `Patient requested cancellation before provider review was completed.`
- After `order_cancellation_processing` completes the applicable refund, Stripe,
  and provider-side cancellation work, it advances the order to the configured
  next status and triggers a follow-up lifecycle run.

**Response:**

```json
{
  "message": "Order cancellation requested successfully",
  "data": {
    "id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "status": "order_pending_cancellation",
    "cancelled_at": null,
    "cancellation_reason": "I no longer need this order."
  }
}
```

If the order is already in `order_pending_cancellation`, the endpoint is
idempotent and triggers `order-lifecycle` again.

**Error Responses:**

| Code | Error             | Description                                  |
| ---- | ----------------- | -------------------------------------------- |
| 404  | `ORDER_NOT_FOUND` | Order not found                              |
| 500  | `CONFIG_ERROR`    | Required statuses are not configured         |
| 500  | `FETCH_ERROR`     | Failed to fetch order data                   |
| 500  | `UPDATE_ERROR`    | Failed to persist pending cancellation state |

##### Order vs Plan Cancellation Behavior

Order cancellation and plan cancellation are related but not equivalent.

| Patient action | Order effect | Plan effect | Existing linked orders |
| --- | --- | --- | --- |
| Cancel an order | The target order moves through `order_pending_cancellation` and may advance to `order_cancellation_processing` before reaching `order_cancelled`. | The linked plan is updated by `order-lifecycle` to `cancelled` or `pending_cancellation`, depending on the subscription timeline. | Other orders on the same plan are not bulk-cancelled. |
| Cancel a plan | No order status is changed by `POST /plans/{plan_id}/cancel`. | The plan is set to `pending_cancellation` when it has future validity, or `cancelled` when expired/no expiration is available. | Existing orders are not cancelled; the response returns `orders_cancelled_count: 0`. |

> **Planned change (fulfillment-stage-gated plan cancellation).** Today
> `POST /plans/{plan_id}/cancel` cancels only the subscription/Stripe billing and
> leaves any in-flight order untouched (a cancelled plan can still show an
> "Action Required" order). The agreed product rule is to gate plan cancellation
> on the linked order's fulfillment stage:
>
> - **Pre-fulfillment** (order not yet `order_sent_to_pharmacy`): also **cancel
>   the order** and propagate the cancellation to the provider (via the existing
>   `POST /orders/{id}/cancel` → `order-lifecycle` → `cancel-helper` path), and
>   report the real `orders_cancelled_count`.
> - **Post-fulfillment** (shipped, not yet delivered): **prevent the renewal**
>   only (Stripe `cancel_at_period_end`) — do not cancel the dispensed order.
> - **Delivered/received:** cancellation is blocked.

Order cancellation behavior by status:

| Current order status | Order behavior | Provider behavior | Stripe/payment behavior | Linked plan behavior |
| --- | --- | --- | --- | --- |
| `order_created` | Can be cancelled directly to `order_cancelled` in the legacy `PATCH /orders/{id}` cancel path; the async cancellation endpoint queues lifecycle processing. | No provider order/case should exist yet. | Usually no provider-side refund work. | Updated when lifecycle completes cancellation. |
| `shipping_details_required` | Queued as `order_pending_cancellation`; may cancel directly if no extra work is needed. | Usually no provider action. | Cancels or refunds payment only if applicable. | Updated to `cancelled` or `pending_cancellation`. |
| `provider_order_creation_pending` | Queued as `order_pending_cancellation`. | Lifecycle evaluates whether provider work exists. | Evaluates refund/cancel PaymentIntent requirements. | Updated by lifecycle. |
| `patient_questionnaire_pending` | Queued as `order_pending_cancellation`. | MDI cancels an existing held case directly; Telegra follows normal provider cancellation rules. | Evaluates refund/cancel PaymentIntent requirements. | Updated by lifecycle. |
| `medical_questionnaire_pending` | Queued as `order_pending_cancellation`. | MDI cancels an existing held case directly without releasing `hold_status`; Telegra follows normal provider cancellation rules. | Evaluates refund/cancel PaymentIntent requirements. | Updated by lifecycle. |
| `provider_review_pending` | MDI queues cancellation immediately; non-MDI stores the reason and waits for provider decision. | Telegra waits for provider decision before processing; MDI cancels the case before review completes. | Evaluates refund/cancel PaymentIntent requirements when lifecycle processes cancellation. | Updated by lifecycle. |
| `provider_approved` | Deferred cancellation resumes through lifecycle. | Provider cancellation may be called when required. | May capture provider fee and/or issue refund. | Updated by lifecycle. |
| `provider_rejected` | Deferred cancellation resumes through lifecycle. | Provider already rejected the order. | May cancel/release payment. | Cancelled immediately, not left as `pending_cancellation`. |
| `payment_pending` | Queued as `order_pending_cancellation`. | Provider cancellation may be called when required. | Cancels uncaptured PaymentIntent when required. | Updated by lifecycle. |
| `payment_collected` | Queued as `order_pending_cancellation` and may require processing. | Provider cancellation may be called when required. | Refund rules are evaluated and applied. | Updated by lifecycle. |
| `order_approved` | Queued as `order_pending_cancellation` and may require processing. | Provider cancellation may be called before pharmacy handoff when supported. | Refund rules are evaluated and applied. | Updated by lifecycle. |
| `order_sent_to_pharmacy` and later fulfillment statuses | Cancellation may still enter lifecycle, but operational reversal may require manual/provider-side handling. | Pharmacy/shipping work may already be in progress. | May result in partial/no refund depending on rules. | Updated by lifecycle where applicable. |
| Terminal statuses such as `order_cancelled`, `delivered`, `payment_failed`, `pharmacy_rejected`, `shipping_exception` | Normal order cancellation is rejected, ignored, or treated as already complete. | No normal provider cancellation action. | No normal payment cancellation action. | Use plan cancellation if the patient wants to stop future renewals. |

---

#### Update Order Shipping Address (Plan API)

Updates the shipping address for an order in `shipping_details_required` status.

```http
PATCH /functions/v1/plan-api/orders/{order_id}
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "shipping_address": {
    "first_name": "Jane",
    "last_name": "Doe",
    "company": "Acme Inc",
    "line1": "456 Oak Avenue",
    "line2": "Suite 200",
    "city": "Brooklyn",
    "state": "NY",
    "postal_code": "11201",
    "country": "US",
    "instructions": "Ring doorbell twice"
  }
}
```

**Request Body:**

| Field                           | Type   | Required | Description                       |
| ------------------------------- | ------ | -------- | --------------------------------- |
| `shipping_address`              | object | Yes      | Shipping address fields to update |
| `shipping_address.first_name`   | string | No       | Recipient first name              |
| `shipping_address.last_name`    | string | No       | Recipient last name               |
| `shipping_address.company`      | string | No       | Company name                      |
| `shipping_address.line1`        | string | No       | Street address line 1             |
| `shipping_address.line2`        | string | No       | Street address line 2             |
| `shipping_address.city`         | string | No       | City                              |
| `shipping_address.state`        | string | No       | State/Province                    |
| `shipping_address.postal_code`  | string | No       | Postal/ZIP code                   |
| `shipping_address.country`      | string | No       | Country code                      |
| `shipping_address.instructions` | string | No       | Delivery instructions             |

**Response:**

```json
{
  "message": "Order shipping address updated successfully",
  "data": {
    "id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "status": "shipping_details_required",
    "shipping_address": {
      "first_name": "Jane",
      "last_name": "Doe",
      "company": "Acme Inc",
      "line1": "456 Oak Avenue",
      "line2": "Suite 200",
      "city": "Brooklyn",
      "state": "NY",
      "postal_code": "11201",
      "country": "US",
      "instructions": "Ring doorbell twice"
    },
    "updated_at": "2026-01-26T15:30:00Z"
  }
}
```

**Error Responses:**

| Code | Error             | Description                         |
| ---- | ----------------- | ----------------------------------- |
| 400  | `CANNOT_UPDATE`   | Order status doesn't allow updates  |
| 400  | `NO_CHANGES`      | No shipping address fields provided |
| 404  | `ORDER_NOT_FOUND` | Order not found                     |

---

#### Update Order Address (Dedicated Endpoint)

A dedicated endpoint for updating order addresses. Flat fields update the
shipping address; `billing_address` updates billing fields.

Shipping address fields can be changed before `provider_review_pending`. Billing
address fields can be changed before `payment_pending`.

If the order is already past `shipping_details_required` and a shipping address
field actually changes, the API syncs the order address payload to the linked
provider platform before returning success.

Provider sync calls:

- Telegra: `PUT {telegra_base_url}/orders/{provider_order_id}` with
  `address.billing` and `address.shipping`.
- MD Integrations:
  `PATCH {mdi_backend_url}/v1/partner/patients/{provider_patient_id}` with the
  MDI patient `address` fields.

When submitted address fields actually change, the API records an
`order_status_history` note using the order's current status. The note indicates
whether the patient updated the shipping address, billing address, or both.

```http
PATCH /functions/v1/plan-api/orders/{order_id}/address
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "first_name": "Jane",
  "last_name": "Doe",
  "company": "Acme Inc",
  "line1": "456 Oak Avenue",
  "line2": "Suite 200",
  "city": "Brooklyn",
  "state": "NY",
  "postal_code": "11201",
  "country": "US",
  "instructions": "Ring doorbell twice",
  "billing_address": {
    "first_name": "Jane",
    "last_name": "Doe",
    "line1": "456 Oak Avenue",
    "city": "Brooklyn",
    "state": "NY",
    "postal_code": "11201",
    "country": "US"
  }
}
```

**Request Body:**

| Field             | Type   | Required | Description                                                |
| ----------------- | ------ | -------- | ---------------------------------------------------------- |
| `first_name`      | string | No       | Recipient first name                                       |
| `last_name`       | string | No       | Recipient last name                                        |
| `company`         | string | No       | Company name                                               |
| `line1`           | string | No       | Street address line 1                                      |
| `line2`           | string | No       | Street address line 2                                      |
| `city`            | string | No       | City                                                       |
| `state`           | string | No       | State/Province (validated against tenant's allowed_states) |
| `postal_code`     | string | No       | Postal/ZIP code                                            |
| `country`         | string | No       | Country code (e.g., "US")                                  |
| `instructions`    | string | No       | Delivery instructions                                      |
| `billing_address` | object | No       | Billing address fields to update before `payment_pending`  |

> **Note:** At least one field must be provided. All fields are optional for
> partial updates.

**Response:** `200 OK`

```json
{
  "message": "Order address updated successfully",
  "data": {
    "id": "order-uuid",
    "order_number": "ORD-ABC123-XYZ9",
    "status": "shipping_details_required",
    "shipping_address": {
      "first_name": "Jane",
      "last_name": "Doe",
      "company": "Acme Inc",
      "line1": "456 Oak Avenue",
      "line2": "Suite 200",
      "city": "Brooklyn",
      "state": "NY",
      "postal_code": "11201",
      "country": "US",
      "instructions": "Ring doorbell twice"
    },
    "billing_address": {
      "first_name": "Jane",
      "last_name": "Doe",
      "company": null,
      "line1": "456 Oak Avenue",
      "line2": null,
      "city": "Brooklyn",
      "state": "NY",
      "postal_code": "11201",
      "country": "US"
    },
    "provider_shipping_sync": {
      "attempted": true,
      "provider": "telegramd"
    },
    "updated_at": "2026-01-26T15:30:00Z"
  }
}
```

When Telegra provider sync is required, the provider request payload is:

```json
{
  "address": {
    "billing": {
      "address1": "456 Oak Avenue",
      "address2": "Suite 200",
      "city": "Brooklyn",
      "state": "NY",
      "zipcode": "11201"
    },
    "shipping": {
      "address1": "456 Oak Avenue",
      "address2": "Suite 200",
      "city": "Brooklyn",
      "state": "NY",
      "zipcode": "11201"
    }
  }
}
```

`provider_shipping_sync` is `null` when no provider sync is required, for
example while the order is still in `shipping_details_required`, when no
shipping field changed, or when only billing fields were updated.

The tenant admin Order Detail UI applies the same edit windows. Successful
tenant-admin address changes are now sent through Plan API instead of direct
table updates.

Tenant-admin endpoint:

```http
PATCH /functions/v1/plan-api/admin/orders/{order_id}/address
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "shipping_address": {
    "first_name": "Jane",
    "last_name": "Doe",
    "line1": "456 Oak Avenue",
    "city": "Brooklyn",
    "state": "NY",
    "postal_code": "11201",
    "country": "US",
    "instructions": "Ring doorbell twice"
  },
  "billing_address": {
    "first_name": "Jane",
    "last_name": "Doe",
    "line1": "456 Oak Avenue",
    "city": "Brooklyn",
    "state": "NY",
    "postal_code": "11201",
    "country": "US"
  }
}
```

This admin endpoint follows the same address-update rules and side effects as
the patient endpoint above:

- shipping updates allowed only before `provider_review_pending`
- billing updates allowed only before `payment_pending`
- shipping/billing state validation against tenant allowed states
- provider shipping sync when shipping actually changes after
  `shipping_details_required`
- async `order-lifecycle` trigger after success

For admin-originated changes, the `order_status_history` row stores `changed_by`
/ `changed_by_email` with the authenticated admin user.

**Error Responses:**

| Code | Error                            | Description                                                        |
| ---- | -------------------------------- | ------------------------------------------------------------------ |
| 400  | `CANNOT_UPDATE_SHIPPING_ADDRESS` | Shipping updates are only allowed before `provider_review_pending` |
| 400  | `CANNOT_UPDATE_BILLING_ADDRESS`  | Billing updates are only allowed before `payment_pending`          |
| 400  | `NO_CHANGES`                     | No address fields provided                                         |
| 400  | `INVALID_SHIPPING_STATE`         | State not in tenant's allowed_states                               |
| 400  | `INVALID_BILLING_STATE`          | Billing state not in tenant's allowed_states                       |
| 401  | `UNAUTHORIZED`                   | Missing or invalid authorization                                   |
| 403  | `FORBIDDEN`                      | Missing tenant-admin access to the order tenant                    |
| 404  | `ORDER_NOT_FOUND`                | Order not found                                                    |
| 502  | `PROVIDER_SHIPPING_SYNC_ERROR`   | Provider platform shipping address sync failed                     |

---

#### Get Order Statuses (Plan API)

Returns all active **and patient-visible** order statuses with patient-facing
labels and descriptions. Statuses marked as not visible to patients are excluded
from this endpoint. This is a **public endpoint** - no authentication required.

```http
GET /functions/v1/plan-api/order-statuses
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "status-uuid-1",
      "key": "order_created",
      "label": "Order Created",
      "description": "Your order has been received and is being processed.",
      "action_required": false,
      "is_final": false,
      "display_order": 1
    },
    {
      "id": "status-uuid-2",
      "key": "payment_received",
      "label": "Payment Received",
      "description": "Payment confirmed. Your order is being prepared.",
      "action_required": false,
      "is_final": false,
      "display_order": 2
    },
    {
      "id": "status-uuid-3",
      "key": "shipped",
      "label": "Shipped",
      "description": "Your order is on its way!",
      "action_required": false,
      "is_final": false,
      "display_order": 5
    },
    {
      "id": "status-uuid-4",
      "key": "delivered",
      "label": "Delivered",
      "description": "Your order has been delivered.",
      "action_required": false,
      "is_final": true,
      "display_order": 6
    }
  ]
}
```

**Response Fields:**

| Field             | Type    | Description                            |
| ----------------- | ------- | -------------------------------------- |
| `id`              | string  | Status UUID                            |
| `key`             | string  | Unique status identifier (snake_case)  |
| `label`           | string  | Human-readable patient-facing label    |
| `description`     | string  | Patient-facing description/microcopy   |
| `action_required` | boolean | `true` if patient needs to take action |
| `is_final`        | boolean | `true` if this is a terminal status    |
| `display_order`   | number  | Order for display in UI                |

**Usage:**

Use this endpoint to:

- Display order progress trackers in the Patient UI
- Show appropriate status labels and descriptions
- Indicate when patient action is needed
- Identify final/terminal order states

---

#### Create Checkout Session (Plan API)

Initiates a Stripe Checkout Session for a product. Returns a URL to redirect the
patient to Stripe's hosted checkout page.

**Authentication required:** this endpoint only supports authenticated patients.

##### Authenticated Checkout

For logged-in patients. Requires a valid Authorization token.

```http
POST /functions/v1/plan-api/orders/{product_id}/checkout
Content-Type: application/json
Authorization: Bearer <access_token>
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "success_url": "https://patient-app.example.com/checkout/success?session_id={CHECKOUT_SESSION_ID}",
  "cancel_url": "https://patient-app.example.com/checkout/cancel"
}
```

**Request Body:**

| Field         | Type   | Required | Description                                                                                 |
| ------------- | ------ | -------- | ------------------------------------------------------------------------------------------- |
| `success_url` | string | No       | URL to redirect after successful payment (must include `{CHECKOUT_SESSION_ID}` placeholder) |
| `cancel_url`  | string | No       | URL to redirect if customer cancels                                                         |

Operational note: If the patient app is served from a new domain, add that
origin to the `CORS_ALLOWED_ORIGINS` Supabase secret for the target project
before calling this endpoint from the new domain.

**Response:** `200 OK`

```json
{
  "message": "Checkout session created",
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
    "session_id": "cs_test_...",
    "expires_at": 1706184000,
    "product": {
      "id": "product-uuid",
      "name": "Testosterone Therapy - Monthly",
      "price_cents": 19900,
      "price_formatted": "$199.00",
      "payment_type": "subscription",
      "subscription_renewal_lead_days": 7
    }
  }
}
```

**Response Fields:**

| Field                                    | Type   | Description                                             |
| ---------------------------------------- | ------ | ------------------------------------------------------- |
| `checkout_url`                           | string | Stripe-hosted checkout page URL                         |
| `session_id`                             | string | Stripe Checkout Session ID                              |
| `expires_at`                             | number | Unix timestamp when session expires                     |
| `product`                                | object | Product details                                         |
| `product.subscription_renewal_lead_days` | number | Days before expiration when renewal should be initiated |

**Usage Flow (Authenticated):**

1. Patient signs in to the Patient UI
2. Patient selects a product
3. Patient UI calls `POST /orders/{product_id}/checkout` with auth token
4. Patient UI receives `checkout_url` and redirects the browser to it
5. Patient completes payment on Stripe's hosted checkout page
6. Stripe webhook writes lifecycle/payment entities:
   - Creates or updates `subscriptions` and links order via
     `orders.subscription_id`
   - Upserts `subscription_payment_provider_links` and
     `order_payment_provider_transactions`
   - Creates/updates the order record without auto-filling shipping/billing
     addresses from Stripe customer data
7. Stripe redirects back to `success_url`

**Error Responses:**

| Code | Error                     | Description                                     |
| ---- | ------------------------- | ----------------------------------------------- |
| 400  | `MISSING_TENANT`          | Tenant slug header not provided                 |
| 400  | `NO_PAYMENT_PROVIDER`     | No Stripe provider configured for tenant        |
| 400  | `PROVIDER_NOT_CONFIGURED` | Stripe secret key not set                       |
| 401  | `UNAUTHORIZED`            | Missing or invalid authorization                |
| 403  | `ACCOUNT_INACTIVE`        | Patient account is suspended/deactivated        |
| 403  | `TENANT_MISMATCH`         | Patient does not belong to the specified tenant |
| 404  | `NOT_FOUND`               | Patient profile not found                       |
| 404  | `PRODUCT_NOT_FOUND`       | Product not found or not available              |
| 404  | `TENANT_NOT_FOUND`        | Tenant not found or inactive                    |
| 500  | `STRIPE_ERROR`            | Stripe API error (see message for details)      |

> 💡 **Note:** This endpoint creates Stripe line-item `price_data` inline at
> checkout time from the current product data in Allia. Shipping/billing
> addresses are not auto-populated from Stripe checkout customer data and should
> be provided via order address endpoints.

---

#### Get Checkout Session Details (Plan API)

Retrieves the details of a Stripe Checkout Session. When the session is
`complete`, this endpoint also ensures the corresponding order exists and
triggers `order-lifecycle`; when the order is in `order_created`, lifecycle
dispatches the downstream RTDH create-order webhook at
`{rtdh_config.api_url}/create-order`.

```http
GET /functions/v1/plan-api/orders/checkout/{session_id}
x-tenant-slug: acme-health
apikey: <supabase-anon-key>
```

**Path Parameters:**

| Parameter    | Type   | Description                                    |
| ------------ | ------ | ---------------------------------------------- |
| `session_id` | string | Stripe Checkout Session ID (starts with `cs_`) |

**Response:** `200 OK`

```json
{
  "data": {
    "id": "cs_test_a1b2c3d4e5f6...",
    "order_id": "order-uuid",
    "status": "complete",
    "payment_status": "paid",
    "customer_email": "patient@example.com",
    "customer_name": "John Doe",
    "amount_total": 19900,
    "currency": "usd",
    "mode": "payment",
    "product_id": "product-uuid",
    "created_at": "2026-01-29T10:30:00Z",
    "expires_at": "2026-01-29T11:30:00Z",
    "shipping_details": {
      "name": "John Doe",
      "address": {
        "line1": "123 Main Street",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "US"
      }
    }
  }
}
```

**Response Fields:**

| Field              | Type           | Description                                                                       |
| ------------------ | -------------- | --------------------------------------------------------------------------------- |
| `id`               | string         | Stripe Checkout Session ID                                                        |
| `order_id`         | string \| null | Existing or newly created internal order ID once the checkout session is complete |
| `status`           | string         | Session status (`open`, `complete`, `expired`)                                    |
| `payment_status`   | string         | Payment status (`unpaid`, `paid`, `no_payment_required`)                          |
| `customer_email`   | string         | Customer's email address                                                          |
| `customer_name`    | string         | Customer's name                                                                   |
| `amount_total`     | integer        | Total amount in cents                                                             |
| `currency`         | string         | Three-letter currency code                                                        |
| `mode`             | string         | `payment` or `subscription`                                                       |
| `product_id`       | string         | Product ID from session metadata                                                  |
| `created_at`       | string         | ISO 8601 timestamp when session was created                                       |
| `expires_at`       | string         | ISO 8601 timestamp when session expires                                           |
| `shipping_details` | object         | Shipping details if collected                                                     |

**Error Responses:**

| Code | Error                     | Description                            |
| ---- | ------------------------- | -------------------------------------- |
| 400  | `MISSING_TENANT`          | Tenant slug not provided               |
| 400  | `NO_PAYMENT_PROVIDER`     | No Stripe provider configured          |
| 400  | `PROVIDER_NOT_CONFIGURED` | Stripe secret key not configured       |
| 404  | `TENANT_NOT_FOUND`        | Tenant not found or inactive           |
| 404  | `SESSION_NOT_FOUND`       | Checkout session not found             |
| 500  | `STRIPE_ERROR`            | Failed to retrieve session from Stripe |

> 💡 **Tip:** Use this endpoint on your checkout success page to display order
> confirmation details. The `customer_email` field is particularly useful for
> displaying who completed the checkout.

---

#### Order Status Values

| Status       | Description                       |
| ------------ | --------------------------------- |
| `pending`    | Order placed, awaiting processing |
| `processing` | Order being prepared              |
| `shipped`    | Order shipped, tracking available |
| `delivered`  | Order delivered                   |
| `paused`     | Order temporarily paused          |
| `cancelled`  | Order cancelled                   |

---

## Plans

#### List Current Patient Plans (Plan API)

Returns the plans (subscriptions) currently associated with the authenticated
patient.

By default, cancelled plans are excluded. To include cancelled plans, pass
`include_cancelled=true`.

```http
GET /functions/v1/plan-api/plans
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Query Parameters:**

| Parameter           | Type    | Default | Description                           |
| ------------------- | ------- | ------- | ------------------------------------- |
| `include_cancelled` | boolean | `false` | Include plans with status `cancelled` |

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "subscription-uuid",
      "status": "active",
      "is_current": true,
      "started_at": "2026-02-10T09:00:00Z",
      "renewal_at": "2026-03-03T09:00:00Z",
      "expires_at": "2026-03-10T09:00:00Z",
      "paused_at": null,
      "cancelled_at": null,
      "created_at": "2026-02-10T09:00:00Z",
      "updated_at": "2026-02-10T09:00:00Z",
      "orders": [
        {
          "id": "order-uuid",
          "order_number": "ORD-ABC123-XYZ9",
          "status_id": "status-uuid",
          "cancellation_reason": null,
          "status_details": {
            "id": "status-uuid",
            "key": "payment_received",
            "label": "Payment Received",
            "description": "Payment confirmed. Your order is being prepared.",
            "action_required": false,
            "is_final": false,
            "display_order": 2
          },
          "status_changed_at": "2026-02-10T09:01:00Z",
          "subtotal_cents": 19900,
          "shipping_cents": 0,
          "tax_cents": 0,
          "total_cents": 19900,
          "total_formatted": "$199.00",
          "subscription_order_type": "initial",
          "provider_platforms": [
            {
              "name": "TelegraMD",
              "integration_key": "telegramd"
            }
          ],
          "tracking": null,
          "shipped_at": null,
          "delivered_at": null,
          "cancelled_at": null,
          "paused_at": null,
          "created_at": "2026-02-10T09:00:00Z",
          "updated_at": "2026-02-10T09:01:00Z"
        }
      ],
      "product": {
        "id": "product-uuid",
        "name": "Testosterone Therapy - Monthly",
        "description": "Monthly treatment plan",
        "image_url": "https://...",
        "price_cents": 19900,
        "price_formatted": "$199.00",
        "payment_type": "subscription",
        "subscription_interval": "month",
        "subscription_interval_count": 1,
        "subscription_renewal_lead_days": 7
      }
    }
  ],
  "meta": {
    "total": 1,
    "include_cancelled": false
  }
}
```

**Response Fields:**

| Field                                           | Type           | Description                                                                                                           |
| ----------------------------------------------- | -------------- | --------------------------------------------------------------------------------------------------------------------- |
| `renewal_at`                                    | string \| null | Next renewal timestamp from `subscriptions.current_period_end_at`                                                     |
| `expires_at`                                    | string \| null | Plan expiration timestamp from `subscriptions.expires_at`                                                             |
| `orders`                                        | array          | Orders associated with the plan (`orders.subscription_id = plan.id`)                                                  |
| `orders[].cancellation_reason`                  | string \| null | Free-text order cancellation reason. When present, the order should be treated as pending cancellation in patient UI. |
| `orders[].provider_platforms`                   | array          | Provider-platform links for the order, resolved from `order_provider_platform_links`                                  |
| `orders[].provider_platforms[].name`            | string \| null | Provider-platform display name                                                                                        |
| `orders[].provider_platforms[].integration_key` | string \| null | Provider-platform integration key                                                                                     |
| `orders[].status_details`                       | object \| null | Patient-facing status metadata from `order_statuses`                                                                  |
| `orders[].total_formatted`                      | string         | Currency-formatted order total                                                                                        |
| `orders[].tracking`                             | object \| null | Tracking number/url when available                                                                                    |
| `product.subscription_renewal_lead_days`        | number         | Days before expiration when renewal should be initiated                                                               |

> Note: `renewal_at` and `expires_at` are independent lifecycle fields
> (`subscriptions.current_period_end_at` and `subscriptions.expires_at`
> respectively).

**Error Responses:**

| Code | Error              | Description                              |
| ---- | ------------------ | ---------------------------------------- |
| 401  | `UNAUTHORIZED`     | Missing or invalid authorization         |
| 403  | `ACCOUNT_INACTIVE` | Patient account is suspended/deactivated |
| 404  | `NOT_FOUND`        | Patient profile not found                |
| 500  | `FETCH_ERROR`      | Failed to fetch plans                    |

---

#### Move Plan Refill Date (Plan API)

Updates the refill date for a plan owned by the authenticated patient.

This endpoint updates the plan's `renewal_at` value
(`subscriptions.current_period_end_at`).

```http
POST /functions/v1/plan-api/plans/{plan_id}/refill-date
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "new_date": "2026-03-15T09:00:00Z"
}
```

**Request Body:**

| Field      | Type   | Required | Description                                                   |
| ---------- | ------ | -------- | ------------------------------------------------------------- |
| `new_date` | string | Yes      | New refill date (any valid date string; ISO 8601 recommended) |

**Response:** `200 OK`

```json
{
  "message": "Plan refill date updated successfully",
  "data": {
    "id": "subscription-uuid",
    "status": "active",
    "renewal_at": "2026-03-15T09:00:00.000Z",
    "expires_at": "2026-03-22T09:00:00Z",
    "updated_at": "2026-02-25T12:00:00Z"
  }
}
```

Only plans with `status = active` can be updated by this endpoint.

The requested refill date must fall within a fixed window — **the current
renewal date (`current_period_end_at`) ± 2 weeks**, compared at day granularity:

- Earliest: `current_period_end_at − 14 days`
- Latest: `current_period_end_at + 14 days`

> This is a temporary hardcoded window (reschedule the renewal by up to 2 weeks
> either way). A later ticket will make it per-product/dynamic via
> `product.renewal_advance_max_weeks` (currently stored but not enforced).

If the plan is linked to Stripe, this endpoint also updates the Stripe
subscription cycle (via `trial_end`) so the next billing date moves to the
requested refill date and bills then. If the chosen date is today or earlier
(only reachable when the current renewal is already within ~2 weeks), it bills
immediately (`trial_end=now`). The local `current_period_end_at` is read back
from Stripe after the update.

> The same window and Stripe rescheduling apply to the tenant-admin refill
> endpoint (`POST /admin/plans/{plan_id}/refill-date`, used by the Subscription
> Detail UI), which previously updated only local/metadata state without moving
> the Stripe billing date.
>
> The tenant-admin endpoint additionally carries two safeguards (there is no
> separate "trigger renewal" action — moving the refill date to today *is* the
> renew-now path):
>
> - **Double-charge guard:** before rescheduling it lists the subscription's
>   Stripe invoices and returns `409 RENEWAL_IN_PROGRESS` if any is open/unpaid,
>   so a renew-now can't stack a second charge on an in-flight renewal. It also
>   reads Stripe's returned `current_period_end` back into `renewal_at`.
> - **Reconciliation:** when the refill bills immediately (today), the attempt is
>   recorded in `renewal_trigger_attempts`; the `renewal-trigger-reconcile` sweep
>   (pg_cron, every 5 min) marks it `fulfilled` once the renewal order appears or
>   `unresolved` after a 15-min grace window (emitting a `renewal_trigger_safeguard`
>   log for ops). Future-dated refills are not recorded — no order is due yet.

**Error Responses:**

| Code | Error                       | Description                                                        |
| ---- | --------------------------- | ------------------------------------------------------------------ |
| 400  | `MISSING_FIELDS`            | Missing required field (`new_date`)                                |
| 400  | `INVALID_JSON`              | Invalid JSON body                                                  |
| 400  | `INVALID_DATE`              | `new_date` is not a valid date                                     |
| 400  | `PLAN_DATE_MISSING`         | Plan start or expiration date is missing                           |
| 400  | `INVALID_PLAN_DATE`         | Plan start or expiration date is invalid                           |
| 400  | `REFILL_DATE_OUT_OF_RANGE`  | `new_date` is outside the allowed refill date window               |
| 400  | `PAYMENT_REFERENCE_MISSING` | Plan is linked to Stripe but missing Stripe subscription reference |
| 400  | `PLAN_NOT_ACTIVE`           | Plan is not in active state                                        |
| 400  | `NO_PAYMENT_PROVIDER`       | Stripe provider is not configured for the tenant                   |
| 400  | `PROVIDER_NOT_CONFIGURED`   | Stripe secret key is missing                                       |
| 401  | `UNAUTHORIZED`              | Missing or invalid authorization                                   |
| 403  | `ACCOUNT_INACTIVE`          | Patient account is suspended/deactivated                           |
| 404  | `NOT_FOUND`                 | Patient profile not found                                          |
| 404  | `PLAN_NOT_FOUND`            | Plan not found for this patient                                    |
| 500  | `FETCH_ERROR`               | Failed to fetch plan                                               |
| 500  | `STRIPE_ERROR`              | Failed to update Stripe subscription                               |
| 500  | `UPDATE_ERROR`              | Failed to update plan refill date                                  |

---

#### Pause Plan (Plan API)

Pauses a plan belonging to the authenticated patient.

```http
POST /functions/v1/plan-api/plans/{plan_id}/pause
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "message": "Plan paused successfully",
  "data": {
    "id": "plan-uuid",
    "status": "paused",
    "renewal_at": null,
    "expires_at": "2026-03-10T09:00:00Z",
    "paused_at": "2026-02-26T15:00:00Z",
    "updated_at": "2026-02-26T15:00:00Z"
  }
}
```

Only plans with `status = active` can be paused by this endpoint.

When pausing succeeds, the API:

- Updates the plan status to `paused`
- Sets `subscriptions.current_period_end_at` to `null` (`renewal_at` in the API
  response)
- Sets `subscriptions.paused_at` to the current timestamp

If the plan is linked to Stripe, this endpoint also syncs Stripe by setting
`pause_collection` so recurring billing is paused and new collectible renewals
are not generated while paused.

The plan status/lifecycle change is recorded in `subscription_events` by the
existing subscription lifecycle trigger.

**Error Responses:**

| Code | Error                       | Description                                                        |
| ---- | --------------------------- | ------------------------------------------------------------------ |
| 400  | `PLAN_NOT_ACTIVE`           | Only active plans can be paused                                    |
| 400  | `NO_PAYMENT_PROVIDER`       | Stripe provider is not configured for the tenant                   |
| 400  | `PROVIDER_NOT_CONFIGURED`   | Stripe secret key is missing                                       |
| 400  | `PAYMENT_REFERENCE_MISSING` | Plan is linked to Stripe but missing Stripe subscription reference |
| 401  | `UNAUTHORIZED`              | Missing or invalid authorization                                   |
| 403  | `ACCOUNT_INACTIVE`          | Patient account is suspended/deactivated                           |
| 404  | `NOT_FOUND`                 | Patient profile not found                                          |
| 404  | `PLAN_NOT_FOUND`            | Plan not found for this patient                                    |
| 500  | `FETCH_ERROR`               | Failed to fetch plan                                               |
| 500  | `STRIPE_ERROR`              | Failed to sync pause state with Stripe                             |
| 500  | `UPDATE_ERROR`              | Failed to pause plan                                               |

---

#### Resume Plan (Plan API)

Resumes a paused plan belonging to the authenticated patient.

```http
POST /functions/v1/plan-api/plans/{plan_id}/resume
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "message": "Plan resumed successfully",
  "data": {
    "id": "plan-uuid",
    "status": "active",
    "renewal_at": "2026-03-03T09:00:00Z",
    "expires_at": "2026-03-10T09:00:00Z",
    "paused_at": null,
    "updated_at": "2026-02-26T15:30:00Z"
  }
}
```

Only plans with `status = paused` can be resumed by this endpoint.

When resuming succeeds, the API computes `renewal_at` as:

- `max(now, expires_at - product.subscription_renewal_lead_days)`

Then it:

- Updates plan status to `active`
- Clears `subscriptions.paused_at`
- Sets `subscriptions.current_period_end_at` to the computed/synced renewal date

If the plan is linked to Stripe, this endpoint also syncs Stripe by:

- Clearing `pause_collection`
- Setting the next billing date to the computed renewal date

The plan status/lifecycle change is recorded in `subscription_events` by the
existing subscription lifecycle trigger.

**Error Responses:**

| Code | Error                       | Description                                                        |
| ---- | --------------------------- | ------------------------------------------------------------------ |
| 400  | `PLAN_NOT_PAUSED`           | Only paused plans can be resumed                                   |
| 400  | `PLAN_EXPIRATION_MISSING`   | Plan expiration date is required                                   |
| 400  | `INVALID_PLAN_EXPIRATION`   | Plan expiration date is invalid                                    |
| 400  | `NO_PAYMENT_PROVIDER`       | Stripe provider is not configured for the tenant                   |
| 400  | `PROVIDER_NOT_CONFIGURED`   | Stripe secret key is missing                                       |
| 400  | `PAYMENT_REFERENCE_MISSING` | Plan is linked to Stripe but missing Stripe subscription reference |
| 401  | `UNAUTHORIZED`              | Missing or invalid authorization                                   |
| 403  | `ACCOUNT_INACTIVE`          | Patient account is suspended/deactivated                           |
| 404  | `NOT_FOUND`                 | Patient profile not found                                          |
| 404  | `PLAN_NOT_FOUND`            | Plan not found for this patient                                    |
| 500  | `FETCH_ERROR`               | Failed to fetch plan                                               |
| 500  | `STRIPE_ERROR`              | Failed to sync resume state with Stripe                            |
| 500  | `UPDATE_ERROR`              | Failed to resume plan                                              |

---

#### Reactivate Plan (Plan API)

Reactivates a plan belonging to the authenticated patient when it is in
`pending_cancellation`.

```http
POST /functions/v1/plan-api/plans/{plan_id}/reactivate
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "message": "Plan reactivated successfully",
  "data": {
    "id": "plan-uuid",
    "status": "active",
    "renewal_at": "2026-03-03T09:00:00Z",
    "expires_at": "2026-03-10T09:00:00Z",
    "paused_at": null,
    "cancelled_at": null,
    "cancellation_reason": null,
    "updated_at": "2026-03-01T12:00:00Z"
  }
}
```

Only plans with `status = pending_cancellation` can be reactivated by this
endpoint.

When reactivation succeeds, the API:

- Updates plan status to `active`
- Clears `subscriptions.cancelled_at`
- Clears `subscriptions.cancellation_reason`

If the plan is linked to Stripe, this endpoint also syncs Stripe by:

- Setting `cancel_at_period_end=false` so auto-renewal resumes
- Keeping the existing billing cycle date (renewal remains on the previously
  scheduled time)

The plan status/lifecycle change is recorded in `subscription_events` by the
existing subscription lifecycle trigger.

**Error Responses:**

| Code | Error                           | Description                                                        |
| ---- | ------------------------------- | ------------------------------------------------------------------ |
| 400  | `PLAN_NOT_PENDING_CANCELLATION` | Only plans pending cancellation can be reactivated                 |
| 400  | `NO_PAYMENT_PROVIDER`           | Stripe provider is not configured for the tenant                   |
| 400  | `PROVIDER_NOT_CONFIGURED`       | Stripe secret key is missing                                       |
| 400  | `PAYMENT_REFERENCE_MISSING`     | Plan is linked to Stripe but missing Stripe subscription reference |
| 401  | `UNAUTHORIZED`                  | Missing or invalid authorization                                   |
| 403  | `ACCOUNT_INACTIVE`              | Patient account is suspended/deactivated                           |
| 404  | `NOT_FOUND`                     | Patient profile not found                                          |
| 404  | `PLAN_NOT_FOUND`                | Plan not found for this patient                                    |
| 500  | `FETCH_ERROR`                   | Failed to fetch plan                                               |
| 500  | `STRIPE_ERROR`                  | Failed to sync reactivation state with Stripe                      |
| 500  | `UPDATE_ERROR`                  | Failed to reactivate plan                                          |

---

#### Create Payment Details Session (Plan API)

Creates a Stripe Billing Portal session so the authenticated patient can open
Stripe's hosted billing portal and manage payment details for a specific plan.

```http
POST /functions/v1/plan-api/plans/{plan_id}/payment-details
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "return_url": "https://patient-app.example.com/plans/subscription-uuid"
}
```

**Request Body:**

| Field        | Type   | Required | Description                                                                                           |
| ------------ | ------ | -------- | ----------------------------------------------------------------------------------------------------- |
| `return_url` | string | No       | Absolute URL to return to after leaving Stripe Billing Portal. Defaults to `{origin}/plans/{plan_id}` |

Operational note: If the patient app is served from a new domain, add that
origin to the `CORS_ALLOWED_ORIGINS` Supabase secret for the target project
before calling this endpoint from the new domain.

**Response:** `200 OK`

```json
{
  "message": "Payment details session created",
  "data": {
    "plan_id": "subscription-uuid",
    "session_id": "bps_123",
    "portal_url": "https://billing.stripe.com/p/session/test_...",
    "return_url": "https://patient-app.example.com/plans/subscription-uuid",
    "saved_payment_methods": [
      {
        "id": "pm_123",
        "brand": "visa",
        "last4": "4242",
        "exp_month": 12,
        "exp_year": 2030,
        "funding": "credit",
        "is_default": true
      }
    ]
  }
}
```

**Response Fields:**

| Field                   | Type   | Description                                                                                                                               |
| ----------------------- | ------ | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `plan_id`               | string | Subscription identifier                                                                                                                   |
| `session_id`            | string | Stripe Billing Portal session ID                                                                                                          |
| `portal_url`            | string | Stripe-hosted Billing Portal URL for managing payment details                                                                             |
| `return_url`            | string | URL used for return navigation                                                                                                            |
| `saved_payment_methods` | array  | Saved Stripe card payment methods currently attached to the customer; empty when none are available or Stripe card listing is unavailable |

**Error Responses:**

| Code | Error                        | Description                                        |
| ---- | ---------------------------- | -------------------------------------------------- |
| 400  | `NO_PAYMENT_PROVIDER`        | Stripe provider is not configured for the tenant   |
| 400  | `PROVIDER_NOT_CONFIGURED`    | Stripe secret key is missing                       |
| 400  | `PLAN_INACTIVE`              | Plan is cancelled and cannot be updated            |
| 400  | `PAYMENT_REFERENCE_MISSING`  | Plan does not have a Stripe subscription reference |
| 400  | `INVALID_RETURN_URL`         | `return_url` is invalid or not absolute            |
| 401  | `UNAUTHORIZED`               | Missing or invalid authorization                   |
| 403  | `ACCOUNT_INACTIVE`           | Patient account is suspended/deactivated           |
| 404  | `NOT_FOUND`                  | Patient profile not found                          |
| 404  | `PLAN_NOT_FOUND`             | Plan not found for this patient                    |
| 404  | `PAYMENT_CUSTOMER_NOT_FOUND` | Stripe customer reference is missing               |
| 500  | `FETCH_ERROR`                | Failed to fetch plan/patient/provider context      |
| 500  | `STRIPE_ERROR`               | Stripe API call failed                             |

---

#### Cancel Plan (Plan API)

Cancels a plan belonging to the authenticated patient.

```http
POST /functions/v1/plan-api/plans/{plan_id}/cancel
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "reason": "I'm moving to another treatment provider and this no longer fits my schedule."
}
```

**Request Body:**

| Field    | Type   | Required | Description                                            |
| -------- | ------ | -------- | ------------------------------------------------------ |
| `reason` | string | Yes      | Free-text cancellation reason submitted by the patient |

**Response:** `200 OK`

```json
{
  "message": "Plan cancellation scheduled successfully",
  "data": {
    "id": "plan-uuid",
    "status": "pending_cancellation",
    "renewal_at": "2026-03-03T09:00:00Z",
    "expires_at": "2026-03-10T09:00:00Z",
    "cancelled_at": null,
    "cancellation_reason": "I'm moving to another treatment provider and this no longer fits my schedule.",
    "orders_cancelled_count": 0,
    "updated_at": "2026-02-24T15:00:00Z"
  }
}
```

The API cancels the plan record only. It does not validate, update, or
transition order statuses as part of this endpoint.

The cancellation status is determined from `expires_at`:

- If `now < expires_at`, status is set to `pending_cancellation`.
- If `now >= expires_at` (or expiration is unavailable), status is set to
  `cancelled`.

If the plan is linked to a Stripe subscription:

- For `pending_cancellation`, the API sets Stripe `cancel_at_period_end=true` to
  stop future renewals.
- For immediate `cancelled`, the API cancels the Stripe subscription.

The endpoint does not cancel/refund Stripe Payment Intents, does not void
invoices, and does not expire checkout sessions.

If the plan is already cancelled, the endpoint is idempotent and returns
`200 OK` with the current cancelled state.

**Error Responses:**

| Code | Error                     | Description                                               |
| ---- | ------------------------- | --------------------------------------------------------- |
| 400  | `NO_PAYMENT_PROVIDER`     | Stripe provider is missing when a Stripe plan link exists |
| 400  | `PROVIDER_NOT_CONFIGURED` | Stripe secret key is missing                              |
| 400  | `MISSING_FIELDS`          | Missing required fields (`reason`)                        |
| 400  | `INVALID_JSON`            | Invalid JSON body                                         |
| 401  | `UNAUTHORIZED`            | Missing or invalid authorization                          |
| 403  | `ACCOUNT_INACTIVE`        | Patient account is suspended/deactivated                  |
| 404  | `NOT_FOUND`               | Patient profile not found                                 |
| 404  | `PLAN_NOT_FOUND`          | Plan not found for this patient                           |
| 500  | `FETCH_ERROR`             | Failed to fetch plan/provider data                        |
| 500  | `UPDATE_ERROR`            | Failed to persist cancellation state                      |
| 500  | `STRIPE_ERROR`            | Stripe cancellation request failed                        |

---
