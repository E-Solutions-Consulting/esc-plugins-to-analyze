# Telegra Integration Requirements

This document defines the current requirements to make the TelegraMD integration
work in this codebase, based on the existing admin UI, database schema, and
`order-lifecycle` Edge Function.

## 1. Current Integration Scope

Today, TelegraMD is only partially wired into the platform.

What already exists:

- A platform integration seed for `telegramd` with the display name `TelegraMD`
- Tenant integration settings for `username`, `password`, `url`, `project_id`,
  and an optional legacy `access_token` fallback
- Tenant integration support for a tenant-specific
  `patient_questionnaire_definition` JSON object
- Product-level provider-platform assignment through
  `product_provider_platforms`
- A backend helper in `supabase/functions/order-lifecycle/telegra-helper.ts`
  that attempts to create a Telegra order when an order reaches
  `provider_order_creation_pending`
- An inbound webhook endpoint in `supabase/functions/telegra-webhook`
- Durable provider-order correlation in `order_provider_platform_links`

What does not exist yet:

- A concrete frontend/backend adapter implementation behind
  `ProviderPlatformAdapter`
- Confirmed Telegra API contract documentation in the repo
- Confirmed Telegra webhook contract documentation in the repo

## 2. Required Tenant Settings

Each tenant using TelegraMD must have a `tenant_integrations` row with:

### 2.1 `integration_key`

- Value must be exactly `telegramd`

### 2.2 `username` (required)

- Stored in `tenant_integrations.settings.username`
- Used with `password` to authenticate against Telegra `POST /auth/client`

Expected shape:

```json
{
  "username": "affiliate-admin@example.com"
}
```

### 2.3 `password` (required)

- Stored in `tenant_integrations.settings.password`
- Used with `username` to authenticate against Telegra `POST /auth/client`

Expected shape:

```json
{
  "password": "<affiliate-admin-password>"
}
```

### 2.4 `access_token` (optional fallback)

- Stored in `tenant_integrations.settings.access_token`
- Used only as a fallback Bearer token when `username/password` are not yet
  configured during the transition

Expected shape:

```json
{
  "access_token": "<telegra-jwt-or-api-token>"
}
```

### 2.5 `url` (required)

- Stored in `tenant_integrations.settings.url`
- Must be the Telegra base API URL
- The backend currently derives the order endpoint as `{url}/orders`

Expected shape:

```json
{
  "url": "https://api.telegramd.example.com"
}
```

### 2.6 `project_id` (required)

- Stored in `tenant_integrations.settings.project_id`
- Identifies the tenant-specific Telegra project
- Sent as both top-level `projectId` and `project` properties on every Telegra
  create-order request. `projectId` follows Telegra support guidance, while
  `project` matches the field name in Telegra's public Update Order schema.

Expected shape:

```json
{
  "project_id": "project-tenant-1"
}
```

### 2.7 Full expected settings payload

```json
{
  "username": "affiliate-admin@example.com",
  "password": "<affiliate-admin-password>",
  "access_token": "<optional-legacy-telegra-token>",
  "url": "https://api.telegramd.example.com",
  "project_id": "project-tenant-1",
  "patient_questionnaire_definition": {
    "questions": [
      {
        "name": "symptoms",
        "type": "symptoms",
        "options": [],
        "multiple": true,
        "question": "Tell us about your symptoms",
        "required": false,
        "description": "Please check all the symptoms you have."
      },
      {
        "name": "other_symptoms",
        "type": "input",
        "question": "Other symptoms not listed in previous screen",
        "required": true,
        "description": "Even if they're not related to the condition or products you requested, this will help us analyze your health condition and recommend the best treatments."
      },
      {
        "name": "medication",
        "type": "medication",
        "options": [],
        "multiple": true,
        "question": "Please list the medications you regularly take",
        "required": false,
        "description": "Please add all the medications you currently take, even if they're not directly related to the reason for your visit."
      },
      {
        "name": "medication_confirmation",
        "options": [
          {
            "id": "Yes",
            "label": "Yes, Confirm"
          }
        ],
        "question": "I confirm that I've listed all medications I take",
        "required": true
      },
      {
        "name": "allergies",
        "type": "allergies",
        "options": [],
        "multiple": true,
        "question": "Please list any medication allergies you have",
        "required": false,
        "description": "Please add all the allergies you have, even if they're not directly related to the reason for your visit."
      },
      {
        "name": "allergies_confirmation",
        "options": [
          {
            "id": "Yes",
            "label": "Yes, Confirm"
          }
        ],
        "question": "I confirm that I've listed all my allergies",
        "required": true
      },
      {
        "name": "biological_gender",
        "options": [
          {
            "id": "male",
            "icon": "MaleIcon",
            "label": "Male"
          },
          {
            "id": "female",
            "icon": "FemaleIcon",
            "label": "Female"
          }
        ],
        "multiple": false,
        "question": "What is your biological gender?",
        "required": true
      },
      {
        "mode": "picker",
        "name": "weight_lbs",
        "type": "weight",
        "question": "What is your current weight?",
        "required": true
      },
      {
        "mode": "picker",
        "name": "height_ft",
        "type": "height",
        "question": "What is your height?",
        "required": true
      },
      {
        "name": "birth_date",
        "type": "date",
        "question": "What's your birth date?",
        "required": true,
        "description": "This helps our healthcare providers personalize your treatment plan and ensure your safety."
      }
    ]
  }
}
```

Default `patient_questionnaire_definition` only:

```json
{
  "questions": [
    {
      "name": "symptoms",
      "type": "symptoms",
      "options": [],
      "multiple": true,
      "question": "Tell us about your symptoms",
      "required": false,
      "description": "Please check all the symptoms you have."
    },
    {
      "name": "other_symptoms",
      "type": "input",
      "question": "Other symptoms not listed in previous screen",
      "required": true,
      "description": "Even if they're not related to the condition or products you requested, this will help us analyze your health condition and recommend the best treatments."
    },
    {
      "name": "medication",
      "type": "medication",
      "options": [],
      "multiple": true,
      "question": "Please list the medications you regularly take",
      "required": false,
      "description": "Please add all the medications you currently take, even if they're not directly related to the reason for your visit."
    },
    {
      "name": "medication_confirmation",
      "options": [
        {
          "id": "Yes",
          "label": "Yes, Confirm"
        }
      ],
      "question": "I confirm that I've listed all medications I take",
      "required": true
    },
    {
      "name": "allergies",
      "type": "allergies",
      "options": [],
      "multiple": true,
      "question": "Please list any medication allergies you have",
      "required": false,
      "description": "Please add all the allergies you have, even if they're not directly related to the reason for your visit."
    },
    {
      "name": "allergies_confirmation",
      "options": [
        {
          "id": "Yes",
          "label": "Yes, Confirm"
        }
      ],
      "question": "I confirm that I've listed all my allergies",
      "required": true
    },
    {
      "name": "biological_gender",
      "options": [
        {
          "id": "male",
          "icon": "MaleIcon",
          "label": "Male"
        },
        {
          "id": "female",
          "icon": "FemaleIcon",
          "label": "Female"
        }
      ],
      "multiple": false,
      "question": "What is your biological gender?",
      "required": true
    },
    {
      "mode": "picker",
      "name": "weight_lbs",
      "type": "weight",
      "question": "What is your current weight?",
      "required": true
    },
    {
      "mode": "picker",
      "name": "height_ft",
      "type": "height",
      "question": "What is your height?",
      "required": true
    },
    {
      "name": "birth_date",
      "type": "date",
      "question": "What's your birth date?",
      "required": true,
      "description": "This helps our healthcare providers personalize your treatment plan and ensure your safety."
    }
  ]
}
```

### 2.8 Webhook Signature Source

- The tenant no longer stores a separate Telegra webhook secret
- `telegra-webhook` verifies inbound HMAC signatures using the Telegra access
  token resolved through `resolveTelegraAccessToken`
- When `username` and `password` are configured, that token is read from and
  refreshed through `tenant_integration_auth_tokens`
- Legacy stored webhook-secret settings are removed from `tenant_integrations`

### 2.9 `patient_questionnaire_definition`

- Stored in `tenant_integrations.settings.patient_questionnaire_definition`
- Must be a JSON object
- Holds the tenant-specific questionnaire definition that is required for all
  Telegra patients for that tenant
- Exposed in the tenant admin under **Settings → Questionnaires → Patient** as a
  per-provider JSON editor (the "Direct" path config). It previously lived on the
  Providers page; the storage key is unchanged — only the admin-UI location moved.
- The default definition is the `patient_questionnaire_definition` shown in
  section 2.7
- The backend replaces the `options` array on any object with
  `"type": "symptoms"` using the current Telegra
  `GET /products/actions/getConditionsAndSymptoms` response before returning the
  questionnaire to the patient app
- The same default definition is used by the Jotform patient-questionnaire
  generator for Telegra. The generated Jotform form also includes the hidden
  fields `provider_key`, `patient_platform_order_id`, and `questionnaire_type`;
  renders one visible question per Jotform page; enables Save and Continue Later
  behavior; saves the generated form id on the Telegra provider integration's
  `settings.patient_questionnaire_form_id`; and submissions are mapped back into
  the same patient profile update shape before being sent to Telegra.

The current default question names map to Telegra patient profile update fields
as follows:

| Question name             | Submitted shape                       | Backend use                                              |
| ------------------------- | ------------------------------------- | -------------------------------------------------------- |
| `birth_date`              | Date string, for example `1990-11-07` | Converted to Telegra `dateOfBirth`                       |
| `biological_gender`       | String, for example `female`          | Sent as Telegra `genderBiological`                       |
| `weight_lbs`              | Weight string or number               | Converted to Telegra `weight` in pounds                  |
| `height_ft`               | Height string or number               | Converted to Telegra `height` in inches                  |
| `symptoms`                | Array of Telegra symptom ids          | Converted to Telegra patient `notes` using symptom names |
| `other_symptoms`          | Free-text string                      | Appended to Telegra patient `notes`                      |
| `medication`              | Medication-list answer                | Converted to Telegra `patientMedications[]`              |
| `medication_confirmation` | `Yes`                                 | Sets Telegra `medicationsConfirmationDate`               |
| `allergies`               | Medication-allergy-list answer        | Converted to Telegra `medicationAllergies[]`             |
| `allergies_confirmation`  | `Yes`                                 | Sets Telegra `allergiesConfirmationDate`                 |

Generated Telegra Jotforms may submit the same concepts with widget-oriented
answer keys. Before sending the Telegra update, the bridge normalizes
`weight_value` plus `weight_unit` into `weight_lbs` in pounds, `height_value`
plus `height_unit` into `height_ft` in inches, and `date_of_birth` into
`birth_date`. Medication widget labels such as `Medication name`, `Dosage`,
`Frequency`, and `Condition treated` are converted into the same `medication[]`
structure used by the native questionnaire. After this normalization, the
Jotform and native flows share the same Telegra patient payload transformation,
patient update call, order status advance, and order lifecycle trigger.

## 3. Platform and Tenant Enablement Requirements

Telegra will not work unless all three layers below are configured.

### 3.1 Platform integration must be active

`platform_integrations` must contain an active row:

```json
{
  "key": "telegramd",
  "name": "TelegraMD",
  "category": "provider_platform",
  "required_settings": ["username", "password", "url", "project_id"],
  "is_active": true
}
```

### 3.2 Tenant integration must be enabled

There must be an enabled tenant integration:

```json
{
  "tenant_id": "<tenant_uuid>",
  "integration_key": "telegramd",
  "is_enabled": true,
  "settings": {
    "username": "affiliate-admin@example.com",
    "password": "<affiliate-admin-password>",
    "url": "https://api.telegramd.example.com",
    "project_id": "project-tenant-1",
    "patient_questionnaire_definition": {
      "questions": []
    }
  }
}
```

### 3.3 Product-provider assignment must exist

Each product that should be fulfilled by Telegra must have a
`product_provider_platforms` row:

```json
{
  "product_id": "<product_uuid>",
  "tenant_integration_id": "<tenant_integration_uuid>",
  "provider_product_sku": "pro::...",
  "provider_product_variation_sku": "pvar::..."
}
```

## 4. Order Lifecycle Requirements

The current Telegra flow is executed from
`supabase/functions/order-lifecycle/index.ts`.

Telegra order creation only happens when:

1. The order reaches status `provider_order_creation_pending`
2. The order has a `product_id`
3. The product is mapped to the tenant’s enabled Telegra integration
4. The tenant integration contains `url`, `project_id`, and either
   `username/password` or the legacy `access_token`
5. The patient record exists for the same tenant

If Telegra order creation succeeds:

- The lifecycle function advances the order to the next configured status
- A note is written to `order_status_history`

If it fails:

- The lifecycle moves the order to the current status's configured
  `failure_status_id`
- For `provider_order_creation_pending`, that failure status is expected to be
  `provider_order_creation_error`
- A failure note is written to `order_status_history`
- The lifecycle returns the provider error message in the processing result

Retry behavior:

- If an order is in `provider_order_creation_error` and `order-lifecycle` is
  called again for that order, the lifecycle retries provider order creation
- A manual admin "Process Order" action also triggers this retry path because it
  calls `v1/order-lifecycle?orderId={orderId}` for the current order
- Lifecycle first moves the order back to `provider_order_creation_pending`,
  writes a retry history entry, and then re-runs the normal provider creation
  branch
- The retry reuses the same determined provider for the order
- If an `order_provider_platform_links` row already exists for the order, that
  provider selection is reused
- If the retry succeeds, the order advances to the next configured status after
  `provider_order_creation_pending`
- If the retry fails again, the order remains in `provider_order_creation_error`
- `provider_order_creation_error` should be treated as retryable, not terminal

Telegra questionnaire validation happens when:

1. The order reaches status `medical_questionnaire_pending`
2. The order has a Telegra provider-platform link
3. The link metadata contains `questionnaire_instance_ids`
4. The tenant integration contains `url` and either `username/password` or the
   legacy `access_token`
5. Every Telegra questionnaire instance returns `valid = true`

Current behavior:

- For Telegra-linked orders in `provider_order_creation_pending`,
  `order-lifecycle` attempts `POST /orders`
- If Telegra order creation fails from `provider_order_creation_pending`, the
  order is moved to `provider_order_creation_error` via the configured
  `failure_status_id`
- For Telegra-linked orders already in `provider_order_creation_error`,
  `order-lifecycle` retries `POST /orders` against the same selected provider
  platform for that order
- For Telegra-linked orders in `payment_collected`, `order-lifecycle` first
  syncs lifecycle dates, advances the order to the next active status, and then
  calls: `POST /orders/actions/sendToPharmacyRecipients` with
  `{"orderIdentifier":"order::..."}`
- If no next active status exists after `payment_collected`, the function
  returns `no_change` and does not call Telegra
- For Telegra-linked orders in `order_cancellation_processing`,
  `order-lifecycle` may call `POST /orders/{orderId}/actions/cancel` using the
  stored `orders.provider_platform_order_id`
- The provider-side cancel call is only attempted if the order has never reached
  `provider_review_pending`
- `order-lifecycle` does not automatically advance the order from
  `medical_questionnaire_pending`
- The order remains in `medical_questionnaire_pending` until a provider platform
  webhook advances it
- The only currently supported webhook-driven exit from
  `medical_questionnaire_pending` is: `eventType = new_status_set_to_request`
  with `targetEntity.status = requires_provider_review` and
  `targetEntity.id = order::...`, which moves the order to
  `provider_review_pending`
- When the order is already in `provider_review_pending`, the only supported
  webhook-driven transition to `provider_approved` is:
  `eventType = prescription_approved_by_practitioner` with
  `targetEntity.order.id = order::...`
- When Telegra sends `eventType = prescription_sent_to_pharmacy` with
  `targetEntity.order.id = order::...`, the resolved non-terminal order is moved
  to `pharmacy_approval_pending`
- After an order reaches `provider_approved`, `order-lifecycle` does not
  automatically advance Telegra orders to another status. Non-Telegra orders may
  auto-advance from `provider_approved` to `payment_pending`, but Telegra waits
  for the next provider/RTDH status event.
- Other Telegra webhook conditions do not move an order out of
  `medical_questionnaire_pending`, and other approval-like Telegra events do not
  move an order from `provider_review_pending` to `provider_approved`

## 5. Required Order and Patient Data

The Telegra payload builder currently depends on these platform records.

### 5.1 Order fields

Required in practice:

- `orders.id`
- `orders.order_number`
- `orders.tenant_id`
- `orders.patient_id`
- `orders.product_id`

Shipping fields expected by payload:

- `shipping_first_name`
- `shipping_last_name`
- `shipping_address_line1`
- `shipping_address_line2` optional
- `shipping_city`
- `shipping_state`
- `shipping_postal_code`
- `shipping_country`

Billing fields expected by payload:

- `billing_first_name`
- `billing_last_name`
- `billing_address_line1`
- `billing_address_line2` optional
- `billing_city`
- `billing_state`
- `billing_postal_code`
- `billing_country`

### 5.2 Patient fields

The helper fetches:

- `patients.id`
- `patients.first_name`
- `patients.last_name`
- `patients.email`
- `patients.phone`
- `patients.date_of_birth`

Minimum practical requirement:

- first name
- last name
- email
- date of birth

## 6. Outbound API Contract Expected by Current Code

The current helper constructs the request as follows.

### 6.1 Endpoint

- `POST {base_url}/orders`

Example:

```text
https://api.telegramd.example.com/orders
```

### 6.2 Headers

```http
Authorization: Bearer <access_token>
Content-Type: application/json
x-request-id: <uuid>
x-source: order-lifecycle
```

### 6.3 Payload shape currently sent

```json
{
  "projectId": "project-tenant-1",
  "project": "project-tenant-1",
  "orderNumber": "ORD-100",
  "productVariations": [
    {
      "productVariation": "TELEGRA-PROD-1",
      "quantity": 1
    }
  ],
  "patient": {
    "firstName": "Jane",
    "email": "jane@example.com",
    "phone": "+15551234567",
    "dateOfBirth": "1990-01-01"
  },
  "address": {
    "billing": {
      "address1": "123 Billing St",
      "city": "Austin",
      "state": "TX",
      "zipcode": "78702"
    },
    "shipping": {
      "address1": "123 Main St",
      "address2": "Apt 4",
      "city": "Austin",
      "state": "TX",
      "zipcode": "78701"
    }
  }
}
```

### 6.4 Order cancellation endpoint used by current code

- `POST {base_url}/orders/{orderId}/actions/cancel`

Example:

```text
https://api.telegramd.example.com/orders/order::123/actions/cancel
```

Headers currently sent:

```http
Authorization: Bearer <access_token>
x-request-id: <uuid>
x-source: order-lifecycle
```

Notes:

- `orderId` is the provider-side order identifier stored in
  `orders.provider_platform_order_id`
- The current implementation does not send a JSON body for this cancel action
- The call is only made from `order_cancellation_processing`
- The call is skipped once the order has ever reached `provider_review_pending`

## 7. Open Contract Gaps That Must Be Resolved

The current implementation is not enough to call the integration
production-ready. These gaps must be resolved.

### 7.1 Confirm the Telegra API schema

The helper explicitly says the payload was inferred from:

- available docs
- runtime validation errors
- implementation requirements

Before go-live, confirm with Telegra:

- exact base URL
- auth method
- canonical order creation endpoint
- required request headers
- field names for product variation identifiers
- required patient/address fields
- expected success response shape
- expected error response shape

### 7.2 Phone handling

The integration now sources Telegra phone values from `patients.phone` when the
platform patient profile has one.

Open point:

- confirm with Telegra whether phone is optional when the platform patient
  record does not have a stored phone value

### 7.3 Resolve the payload/test mismatch

The payload and test expectations must stay aligned:

- implementation sends `productVariations[].productVariation`
- tests should assert that same field name
- implementation sends the stored patient phone when available

### 7.4 Persist the external Telegra order reference

The current flow only writes the external order id into an order history note
when available.

Missing requirement:

- add a durable field/table to store the Telegra external order id
- make it queryable for support, retries, reconciliation, and status sync

### 7.5 Add idempotency and duplicate protection

The current implementation relies on stored `provider_order_id` correlation, but
the code does not yet enforce a durable idempotency model.

Required:

- define retry behavior
- define duplicate response handling
- define how to reconcile if Telegra created the order but the platform timed
  out

### 7.6 Add status synchronization

There is now a `telegra-webhook` Edge Function in this repo, but the upstream
contract is still inferred rather than confirmed.

Current implementation:

- inbound webhook endpoint: `<SUPABASE_URL>/functions/v1/telegra-webhook`
- Telegra webhook events currently listened for:
  - `Order Created`
  - `Order Updated`
  - `New Order Status`
  - `Order Submitted`
  - `Order Expedited`
  - `Order Ready For Submission`
  - `Order Case Status Updated`
- order resolution by `orders.provider_platform_order_id`
- event type is read only from the top-level `eventType` property
- status is read from the top-level `status` property, plus
  `targetEntity.status` for the supported `new_status_set_to_request` transition
  rules
- event-type mapping takes precedence over status-derived signals, except for
  the explicit `new_status_set_to_request` +
  `targetEntity.status = requires_order_processing` and
  `targetEntity.status = requires_provider_review` transition rules
- provider order id is read from `targetEntity.id`, except for
  `prescription_approved_by_practitioner` and `prescription_sent_to_pharmacy`,
  which read `targetEntity.order.id`
- the selected provider order id path is only accepted when the value starts
  with `order::`
- status mapping for common fulfillment/shipping states
- order/tracking updates plus `order_status_history` notes
- structured logs for provider order id extraction attempts, selected path, and
  final resolution outcome

Still required before go-live:

- confirm exact webhook auth method
- confirm exact webhook payload fields and nesting
- confirm the full status vocabulary Telegra sends
- confirm retry semantics / duplicate delivery guarantees
- add integration tests against real sandbox payloads

### 7.7 Confirm webhook authentication contract

Production target:

- A shared secret is configured in Telegra webhook settings
- Telegra signs the raw webhook payload using HMAC SHA-256
- The signature is sent in the `TelegraMD-Signature` header
- The runtime verifies that signature against the raw request body before
  processing the event

Configured secret source:

- The raw webhook body is verified against the Telegra access token currently
  resolved for the tenant integration
- When client-credential auth is configured, the runtime uses the cached token
  in `tenant_integration_auth_tokens` before re-authenticating with Telegra

Current runtime behavior:

- HMAC signature verification is enabled in `supabase/functions/telegra-webhook`
- the primary inbound signature header is `TelegraMD-Signature`
- the runtime also accepts legacy `telegramd-signature` for backward
  compatibility
- the runtime no longer accepts direct token-comparison webhook credentials
- webhook verification depends on the authenticated Telegra access token

Before production:

- narrow the accepted surface area if Telegra provides a stricter contract

## 8. Webhook Endpoint Setup

Configure Telegra to call:

- URL: `<SUPABASE_URL>/functions/v1/telegra-webhook`

Recommended tenant settings:

```json
{
  "access_token": "<telegra-jwt-or-api-token>",
  "url": "https://api.telegramd.example.com"
}
```

Important:

- Telegra should send all webhook events to the same URL:
  `<SUPABASE_URL>/functions/v1/telegra-webhook`.
- The runtime requires a valid HMAC SHA-256 signature for the raw webhook body.
- The signing secret is the Telegra access token resolved for that tenant.
- When username/password auth is configured, the runtime uses the cached token
  in `tenant_integration_auth_tokens` before refreshing it.

Current supported webhook payload handling:

- single inbound webhook route for all Telegra events
- order webhook events currently listened for:
  - `Order Created`
  - `Order Updated`
  - `New Order Status`
  - `Order Submitted`
  - `Order Expedited`
  - `Order Ready For Submission`
  - `Order Case Status Updated`
- provider order correlation via `orders.provider_platform_order_id`
- event type extraction supports only:
  - `eventType`
- status extraction supports only:
  - `status`
- `new_status_set_to_request` transition gating also reads:
  - `targetEntity.status`
- provider order id extraction supports only:
  - `targetEntity.id`
- `prescription_approved_by_practitioner` reads:
  - `targetEntity.order.id`
- `prescription_sent_to_pharmacy` reads:
  - `targetEntity.order.id`
- `new_status_set_to_request` with
  `targetEntity.status = requires_order_processing` reads:
  - `targetEntity.id`
- `new_status_set_to_request` with
  `targetEntity.status = requires_provider_review` reads:
  - `targetEntity.id`
- `targetEntity.id` is ignored unless it starts with `order::`
- `targetEntity.order.id` is ignored unless it starts with `order::`
- the webhook logs every attempted provider-order-id path, the raw value found,
  and the selected path/value for debugging
- when a status change is written to `order_status_history`, the note includes
  the webhook event type, selected provider order id, and `targetEntity.status`
  when present
- tracking extraction via `trackingNumber` / `trackingUrl` and common nested
  variants
- timestamp extraction via common `occurredAt` / `shippedAt` / `deliveredAt`
  fields

Current internal status mapping:

- `prescription_*` / provider review signals -> `provider_review_pending`,
  `provider_approved`, `provider_rejected`, `medical_followup_required`
- `pharmacy_*` / fulfillment signals -> `pharmacy_approval_pending`,
  `pharmacy_approved`, `fulfillment_in_progress`, `final_pharmacy_verification`,
  `pharmacy_rejected`, `inventory_unavailable`
- `prescription_approved_by_practitioner` with a valid `order::...`
  `targetEntity.order.id` -> `provider_approved`
- An order already in `provider_review_pending` only advances to
  `provider_approved` for `prescription_approved_by_practitioner`
- for practitioner approval payloads,
  `eventType = prescription_approved_by_practitioner` overrides
  `targetEntity.status = requires_provider_review`
- `prescription_sent_to_pharmacy` with a valid `order::...`
  `targetEntity.order.id` -> `pharmacy_approval_pending`
- when `eventType = prescription_sent_to_pharmacy`, the resolved non-terminal
  order is moved to `pharmacy_approval_pending` regardless of its current
  non-terminal status or `display_order`
- `new_status_set_to_request` with
  `targetEntity.status = requires_order_processing` and a valid `order::...`
  `targetEntity.id` -> `payment_pending`
- for that `new_status_set_to_request` case, the webhook still writes
  `order_status_history` and triggers the downstream lifecycle flow even if the
  resolved order is already in `payment_pending`
- `new_status_set_to_request` with
  `targetEntity.status = requires_provider_review` and a valid `order::...`
  `targetEntity.id` -> `provider_review_pending`
- `medical_questionnaire_pending` only advances to `provider_review_pending` for
  that `new_status_set_to_request` +
  `targetEntity.status = requires_provider_review` combination
- Telegra `provider_approved` does not auto-advance to the next active status
  by `display_order`
- `order_submitted` is not used for the `medical_questionnaire_pending` ->
  `provider_review_pending` transition
- `shipped` / `in_transit` -> `in_transit`
- `delivered` -> `delivered`
- `exception` / `delivery_exception` -> `shipping_exception`
- `cancelled` -> `order_cancelled`
- `fulfillment_in_progress` / `processing` / `preparing` ->
  `fulfillment_in_progress`
- `final_pharmacy_verification` / `quality_check` ->
  `final_pharmacy_verification`
- `pharmacy_approved` -> `pharmacy_approved`

## 9. Admin Setup Checklist

To enable Telegra for one tenant/product combination:

1. Ensure the `telegramd` platform integration exists and is active.
2. In tenant integration settings, enable TelegraMD for the tenant.
3. Save `username`.
4. Save `password`.
5. Save `url`.
6. Save the tenant's Telegra `project_id`.
7. If needed during migration, optionally keep `access_token` as a legacy
   fallback until all tenants are moved to `username/password`.
8. In the product configuration, assign the product to the tenant’s Telegra
   integration.
9. Set `provider_product_variation_sku` to the exact Telegra product variation
   identifier expected by their API.
10. Set `provider_product_sku` to the exact Telegra product identifier expected
   by the symptoms endpoint when product-scoped symptom lists are needed.
11. Create or verify a patient with valid identity/contact data.
12. Create or verify an order with complete billing and shipping data.
13. Move the order into `provider_order_creation_pending`.
14. Run `order-lifecycle` for that order and verify the outbound Telegra call
    succeeds.
15. If provider order creation fails, verify the order moves to
    `provider_order_creation_error`.
16. Run `order-lifecycle` again for the same order in
    `provider_order_creation_error` and verify provider order creation is
    retried using the same selected provider.
17. Configure Telegra to send webhooks to
    `<SUPABASE_URL>/functions/v1/telegra-webhook`.
18. Confirm webhook requests are signed with the tenant's current Telegra access
    token.
19. If the tenant authenticates with username/password, confirm the access token
    is being reused from `tenant_integration_auth_tokens` before refresh.
20. Confirm webhook payloads include the expected order-id field for the event:
    `targetEntity.id` for general/order-submitted events, and
    `targetEntity.order.id` for `prescription_approved_by_practitioner` and
    `prescription_sent_to_pharmacy`; the selected value must start with
    `order::`.

## 10. Validation Checklist

Minimum validation before calling the integration ready:

1. Confirm Telegra accepts the configured token and base URL.
2. Confirm `POST {url}/orders` returns success for a real sandbox/test order.
3. Confirm the correct product variation field name with Telegra.
4. Confirm patient phone handling is valid.
5. Confirm the platform advances the order only after successful Telegra
   creation.
6. Confirm failures leave the order in place and produce actionable logs.
7. Confirm the external Telegra order id is stored durably.
8. Confirm repeat processing does not create duplicate provider orders.
9. Confirm Telegra webhook deliveries update tracking and lifecycle state as
   expected.

## 11. Recommended Engineering Tasks Before Production Use

These are the concrete implementation tasks still missing:

1. Replace the hardcoded Telegra phone with validated patient data.
2. Confirm and correct the Telegra payload schema.
3. Add persistent Telegra order reference storage.
4. Confirm and harden the webhook payload/auth contract with Telegra.
5. Implement a real `TelegraAdapter` behind
   `src/integrations/adapters/interfaces.ts`.
6. Add integration tests around `order-lifecycle` + Telegra responses.
7. Add operational documentation for sandbox vs production credentials if
   Telegra provides separate environments.

## 12. Key Failure Modes

The current code will fail or behave incompletely in these cases:

- tenant integration is disabled
- `access_token` missing
- `url` missing
- order has no `product_id`
- product is not assigned to the tenant’s Telegra integration
- patient lookup fails
- Telegra rejects the inferred payload schema
- the platform needs the external Telegra order id later but it was only written
  to history text
