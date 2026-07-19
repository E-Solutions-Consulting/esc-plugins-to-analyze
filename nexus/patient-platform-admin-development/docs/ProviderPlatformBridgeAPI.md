# Provider Platform Bridge API Documentation

> **Version:** 1.6.0\
> **Last Updated:** June 2026\
> **Audience:** Frontend Developers, Backend Developers, Integrations Team

This document describes the `provider-platform-bridge` Edge Function. It is an
internal bridge endpoint used to fetch provider-platform questionnaire data and
proxy provider-specific order flows for an existing order.

Related sequence diagram:
[Order Flow Sequence](./SequenceDiagrams.md#order-flow-sequence).

Current provider support:

- TelegraMD for questionnaires, symptoms, patient profile updates, and
  questionnaire answer submission
- MD Integrations for questionnaires, patient questionnaire retrieval, patient
  profile updates, and medical question submission
- JotForm form validation, Telegra patient questionnaire generation, and
  submission routing for configured patient/medical questionnaire forms

---

## Table of Contents

1. [Overview](#overview)
2. [Base Configuration](#base-configuration)
3. [Endpoints](#endpoints)
4. [Provider Validation Flow](#provider-validation-flow)
5. [Data Dependencies](#data-dependencies)
6. [Response Models](#response-models)
7. [Error Handling](#error-handling)
8. [Implementation Notes](#implementation-notes)

---

## Overview

The bridge exists to isolate provider-specific outbound API calls from other
application flows.

For `GET /get-questionnaires/:orderId`, the function:

1. Reads the order id from the request path
2. Loads the order from `orders`
3. Resolves the selected provider-platform context
4. For Telegra orders:
   - loads the tenant's enabled Telegra integration
   - validates the stored Telegra provider-platform link
   - reads `questionnaire_instance_ids` from link metadata
   - calls Telegra for each questionnaire schema
   - returns one aggregated response object
5. For MD Integrations orders:
   - checks whether the medical questionnaire should be rendered from JotForm
   - returns `questionnairePresentation.type = "jotform"` with the embed URL
     when JotForm is configured
   - the returned JotForm embed URL includes both `patient_platform_order_id`
     and `provider_key` query parameters
   - otherwise resolves the order product's linked medications, maps each
     medication-level `offering_id` to its native MDI questionnaire(s), and
     returns the aggregated `questionnaires` response shape

For `GET /symptoms`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Resolves the requested tenant from `tenant_id` / `slug` query params or
   `x-tenant-id` / `x-tenant-slug` headers
3. Verifies the caller can access that tenant
4. Loads the tenant's enabled Telegra integration from `tenant_integrations`
5. Calls Telegra `GET /products/actions/getConditionsAndSymptoms`
6. Returns a deduplicated list of symptom ids, descriptions, and names

For `GET /get-patient-questionnaire/:orderId`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Resolves the order and selected provider-platform context
3. Determines the provider from the selected
   `order_provider_platform_links.tenant_integration_id` first, then falls back
   to stored provider metadata or `orders.provider_platform_integration_key`
4. Determines the patient-questionnaire **mode** for the order's provider. The
   provider integration's `settings.patient_questionnaire_mode`
   (`'direct' | 'jotform'`) is **authoritative when set**:
   - `direct` → always use the provider's native questionnaire (step 5/6); the
     Jotform form id is ignored even if present.
   - `jotform` (or **unset**) → loads the tenant's enabled `jotform` integration
     for credentials and checks the selected provider integration for
     `settings.patient_questionnaire_form_id`. If configured (and valid), returns
     `questionnairePresentation.type = "jotform"` for both Telegra and MDI patient
     questionnaire flows. The returned embed URL includes
     `patient_platform_order_id`, `provider_key`, and
     `questionnaire_type=patient_questionnaire`.
   - When the mode is **unset**, the decision falls back to inference (has a valid
     form id? → Jotform; else native), preserving pre-flag behavior for tenants
     that never set an explicit mode.
5. For Telegra orders resolved to the native path (direct mode, or no Jotform
   form configured):
   - reads `settings.patient_questionnaire_definition` from the tenant's enabled
     Telegra integration
   - resolves the order product's provider-platform assignment when available
   - extracts configured `provider_product_sku` values from the order product's
     provider-platform assignment
   - calls Telegra `GET /products/actions/getConditionsAndSymptoms`
   - replaces every questionnaire object whose `type` is `symptoms` with the
     current Telegra symptom list in its `options` property
6. For MD Integrations orders without a configured Jotform patient
   questionnaire:
   - reads `settings.patient_questionnaire_definition` from the tenant's enabled
     `md_integrations` integration
   - returns the configured questionnaire definition directly
7. Returns either the Jotform presentation or the provider-specific
   questionnaire JSON object

For `POST /order/:orderId/patient-profile`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Resolves the order and selected provider-platform context
3. Determines the provider from the selected
   `order_provider_platform_links.tenant_integration_id` first, then falls back
   to stored provider metadata or `orders.provider_platform_integration_key`
4. For Telegra orders:
   - verifies the order already has a stored Telegra order id
   - resolves the stored Telegra patient id from
     `patient_provider_platform_links`
   - loads the platform patient profile for the order's patient
   - accepts either `{ patientData: ... }` or the raw app payload object
   - transforms the raw app payload into Telegra's expected patient-profile
     shape when needed
   - injects the Telegra patient id plus `email`, `firstName`, and `lastName`
     from the platform patient profile, and injects `phone` when available
   - calls Telegra `PUT /patients/{id}` with the merged payload
5. For MD Integrations orders:
   - resolves the stored MDI patient id from `patient_provider_platform_links`
   - authenticates against `POST /v1/partner/auth/token`
   - accepts either `{ patientData: ... }` or the raw app payload object
   - transforms the raw app payload into MDI's expected patient-profile PATCH
     shape when needed
   - calls MDI `PATCH /v1/partner/patients/{id}`
6. After a successful provider update, advances the order to the next active
   status and writes the note `Patient Questionnaire has been submitted.`
7. Best-effort triggers `order-lifecycle` for the order
8. Returns the provider response payload plus status transition and lifecycle
   trigger status

For `POST /order/:orderId/questionnaire-answer-location`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Resolves the order and Telegra provider-platform context
3. Validates that the submitted questionnaire id belongs to the order
4. Calls Telegra `answerLocation` with `shouldNavigateNext=true` for all answer
   types, including strings, arrays, files, and informed-consent locations
5. Returns the Telegra response payload

For `POST /order/:orderId/mdi-medical-questions`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Resolves the order and selected provider-platform context
3. Validates that the order is linked to MD Integrations
4. Validates the request body is a non-empty array where each object includes
   `question`, `answer`, and `type`
5. Resolves the stored MDI case id from
   `order_provider_platform_links.provider_order_id`
6. Authenticates against MDI
7. Calls `POST /v1/partner/cases/{case_id}/questions` once for each submitted
   question object, and uploads/attaches any file answers
8. After all MDI question/file calls succeed, calls
   `PATCH /v1/partner/cases/{case_id}/status` with `{ "hold_status": false }` so
   the MDI case can enter the MDI flow
9. Returns the raw MDI response payloads as an array plus the hold release
   result

For `POST /jotform-form-validation`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Reads `tenantIntegrationId` and `formId` from the JSON request body
3. Loads the provider platform tenant integration to resolve the tenant context
4. Verifies the caller can access that tenant
5. Loads the tenant's enabled `jotform` integration from `tenant_integrations`
6. Calls JotForm questions lookup using the tenant's configured JotForm
   `api_url`, `api_key`, and `team_workspace_id` (sent as `jf-team-id`);
   site-root URLs are normalized to `GET {api_url}/API/form/{formId}/questions`,
   while API hosts such as `api.jotform.com` use
   `GET {api_url}/form/{formId}/questions`
7. Validates that the returned form questions include the order correlation
   field whose `name` is exactly `patient_platform_order_id`
8. Returns `200 OK` only when the form can be read and that order correlation
   field is present. JotForm forms must still be configured with the full hidden
   field set documented below: `patient_platform_order_id`, `provider_key`, and
   `questionnaire_type`.

For `POST /jotform-patient-questionnaire`, the function:

1. Authenticates the caller using the incoming Supabase JWT
2. Reads `tenantIntegrationId` from the JSON request body; this must be an
   enabled Telegra `tenant_integrations.id`
3. Verifies the caller can access that tenant
4. Loads the tenant's enabled `jotform` integration and reads `api_url`,
   `api_key`, optional `team_workspace_id`, and optional `default_webhook_url`
5. Creates a Jotform form from the default Telegra patient questionnaire
   definition using the Jotform API `POST /form`
6. Adds the required hidden fields: `provider_key`, `patient_platform_order_id`,
   and `questionnaire_type`
7. Adds page breaks so each visible patient questionnaire question renders on a
   separate Jotform page
8. Enables Save and Continue Later behavior for the generated form
9. Attaches the RTDH webhook through the Jotform API
   `POST /form/{formId}/webhooks`. The URL comes from the request body,
   `tenant_integrations.settings.default_webhook_url`, or the built-in RTDH dev
   receiver fallback.
10. Saves the generated form id in
    `tenant_integrations.settings.patient_questionnaire_form_id` for the Telegra
    provider integration unless `saveAsPatientQuestionnaire` is `false`. The
    Jotform integration row stores only the shared API credentials and optional
    team workspace id.

Tenant admin UI note:

- In **Settings → Questionnaires → Connection**, Jotform settings are split into
  independent sections: **API Credentials**, **Team Workspace ID**, and
  **Default Webhook URL**.
- Team Workspace ID is shown in plain text for quick verification and can be
  edited/saved independently from API URL and API Key.
- Patient questionnaire form IDs are configured under **Settings → Questionnaires
  → Patient**, per provider, directly after each provider's **Patient
  Questionnaire Definition** setting as **Patient Questionnaire Jotform ID** (these
  moved out of the Providers page; the Providers page now owns only the provider
  connection credentials and the RTDH validation secret). If the field is blank,
  that provider
  keeps using the legacy patient-questionnaire implementation. Admins edit or
  clear the value through the provider card's **Update Settings** flow; deleting
  the value and saving removes the configured Jotform ID. The field reuses the
  same hidden-field tooltip shown in the product **Jotform Medical
  Questionnaires** section. For this provider-level field, the tooltip displays
  `medical_questionnaire` as the fixed `questionnaire_type` value for the linked
  form. Jotform generation is currently implemented for the Telegra patient
  questionnaire, while other provider patient questionnaire IDs can be saved
  manually.
- For each configured Jotform ID, admin UI shows compact webhook status in a
  tooltip on the field title. The input row includes an eye button for public
  preview, a pencil button for editing the form in Jotform, and a wrench button
  for additively fixing the current Default Webhook URL when it is missing. The
  UI does not expose the full list of webhooks configured on the Jotform.
- Changing **Default Webhook URL** starts a backend sync that checks all
  currently configured Jotforms and additively attaches the new default webhook
  URL where missing. Existing webhook URLs are preserved.
- The Default Webhook URL field presents the RTDH dev receiver as an `Example:`
  placeholder only; webhook checks are suspended until a value is saved.

The bridge does not derive questionnaire ids from product configuration at
request time. It uses the questionnaire instance ids already stored when the
Telegra order was created.

For JotForm submission-processing flows, when answers include uploaded files or
images, the bridge downloads those assets from JotForm using tenant `api_key`
plus `jf-team-id` before forwarding them to provider APIs.

For MDI `medical_questionnaire_submitted` processing, uploaded files are sent to
MDI as standard uploads but are not mapped to `driver_license_id` on the MDI
patient profile.

If a JotForm file/image download or provider upload step fails in this flow, the
bridge writes a diagnostic note into `order_status_history` on the same order
(including a failure code and `requestId`) to simplify support triage.

---

## Base Configuration

### Base URL

```text
VITE_SUPABASE_URL/functions/v1/provider-platform-bridge
```

### Supported Methods

| Method    | Supported |
| --------- | --------- |
| `GET`     | Yes       |
| `OPTIONS` | Yes       |
| `POST`    | Yes       |
| `PATCH`   | No        |
| `DELETE`  | No        |

### Required Headers

| Header          | Description                     | Required    |
| --------------- | ------------------------------- | ----------- |
| `apikey`        | Supabase anon key               | Yes         |
| `Content-Type`  | `application/json`              | Recommended |
| `Authorization` | Supabase user JWT Bearer token  | Yes         |
| `x-tenant-id`   | Tenant UUID for `GET /symptoms` | Conditional |
| `x-tenant-slug` | Tenant slug for `GET /symptoms` | Conditional |

### Edge Function Environment Variables

| Variable                    | Description                                   |
| --------------------------- | --------------------------------------------- |
| `SUPABASE_URL`              | Supabase project URL                          |
| `SUPABASE_SERVICE_ROLE_KEY` | Service role key used to read internal tables |

---

## Endpoints

### Get Questionnaires

```http
GET /functions/v1/provider-platform-bridge/get-questionnaires/{order_id}
```

Notes:

- `order_id` is passed in the path, not as a query parameter.
- For MDI orders, this endpoint is the medical-questionnaire decision point. The
  product/provider-platform assignment's `integration_mode`
  (`'direct' | 'jotform'`) is **authoritative when set**:
  - `direct` → always native MDI questionnaire (JotForm IDs ignored even if set);
    `questionnairePresentation.reason = "integration_mode_direct"`.
  - `jotform` (or **unset**) → when the assignment has a matching JotForm ID and
    the tenant has an enabled `jotform` integration, the response sets
    `questionnairePresentation.type = "jotform"` and returns the selected embed
    URL; otherwise it falls back to the native MDI questionnaire response.
  - When `integration_mode` is **unset** (legacy rows), the decision falls back to
    inference (has a matching form id? → JotForm; else native), preserving
    pre-flag behavior.
- Native MDI questionnaire resolution is medication-level: every medication
  linked through `product_medications` must have `medications.offering_id`, and
  the bridge fetches the MDI questionnaires associated with each offering. This
  lets bundles return all relevant medication questionnaires in one response.
- For native Telegra and MDI questionnaire responses, any question object with
  `type = "agreement"` is decorated with `provider_legal_agreement`, populated
  from the selected provider platform tenant integration.
- JotForm selection is based on `orders.subscription_order_type`:
  - `renewal` uses `jotform_renewall_questionnaire_id`
  - every other value uses `jotform_new_order_questionnaire_id`

### Example Request

```bash
curl -X GET \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/get-questionnaires/539fcc38-66fd-4668-8bbf-0c0f40d55b4b" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json"
```

### Successful Response

**Response:** `200 OK`

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "provider": "TelegraMD",
  "providerOrderId": "telegra-order-123",
  "questionnaireInstanceIds": [
    "qi-1",
    "qi-2"
  ],
  "questionnaires": {
    "qi-1": {
      "schema": {
        "type": "object"
      },
      "status": "in_progress",
      "valid": false
    },
    "qi-2": {
      "schema": {
        "type": "object"
      },
      "status": "completed",
      "valid": true
    }
  }
}
```

MDI JotForm response:

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "provider": "MDI",
  "providerOrderId": "mdi-order-123",
  "questionnairePresentation": {
    "type": "jotform",
    "orderType": "initial",
    "selectedQuestionnaire": "new_order",
    "jotform": {
      "formId": "261405426738055",
      "baseUrl": "https://ahghipaa.jotform.com",
      "embedHandlerBaseUrl": "https://ahghipaa.jotform.com/",
      "embedHandlerScriptUrl": "https://ahghipaa.jotform.com/s/umd/latest/for-form-embed-handler.js",
      "formUrl": "https://ahghipaa.jotform.com/261405426738055?patient_platform_order_id=539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
      "iframeId": "JotFormIFrame-261405426738055",
      "orderIdFieldName": "patient_platform_order_id"
    }
  },
  "questionnaireInstanceIds": [],
  "questionnaires": {}
}
```

### Get Symptoms

```http
GET /functions/v1/provider-platform-bridge/symptoms?tenant_id={tenant_id}&products={product_id_1},{product_id_2}
```

Notes:

- `products` is required and must contain one or more Telegra product ids.
- You can provide the tenant as `tenant_id` or `slug` query params instead of
  headers.
- You can also provide the tenant in `x-tenant-id` or `x-tenant-slug` headers.

### Example Request

```bash
curl -X GET \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/symptoms?tenant_id=8d1df6b2-b8e5-4d08-9668-6f1041e02f8a&products=pro::65a3a357-ec0e-490d-8fa0-ad2e14cd3c25,pro::88988fe3-c590-40d9-bbb0-31beb2e074fd" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json"
```

### Successful Response

**Response:** `200 OK`

```json
{
  "symptoms": [
    {
      "id": "symp::9d65e74b-caed-4b38-b343-d7f84946da60",
      "description": "Difficulty Sleeping",
      "name": "Difficulty Sleeping"
    },
    {
      "id": "symp::f2f869e6-0ff4-4491-a968-c0d8caed89a0",
      "description": "Joint Pain",
      "name": "Joint Pain"
    },
    {
      "id": null,
      "description": "None of the above",
      "name": "None of the above"
    }
  ]
}
```

### Get Patient Questionnaire

```http
GET /functions/v1/provider-platform-bridge/get-patient-questionnaire/{order_id}
```

Notes:

- `order_id` is passed in the path.
- The endpoint determines the provider from the selected
  `order_provider_platform_links` row when present. This is the primary signal
  used for MDI vs Telegra routing.
- For Telegra orders:
  - the endpoint requires an enabled Telegra integration
  - the tenant's Telegra integration authenticates outbound Telegra requests
    using `username/password` via `/auth/client` when available
  - a stored `access_token` is only used as a legacy fallback during the
    transition
  - the tenant's Telegra integration must have
    `settings.patient_questionnaire_definition` configured as a JSON object
  - any questionnaire object whose `type` is `symptoms` will have its `options`
    property replaced with the Telegra symptom summaries used by `GET /symptoms`
  - when the order product has a matching
    `product_provider_platforms.provider_product_sku`, that value is forwarded
    to Telegra as the `products` filter
- For MD Integrations orders:
  - the endpoint requires an enabled `md_integrations` integration
  - the tenant integration must have `settings.patient_questionnaire_definition`
    configured as a JSON object
  - no provider-side symptoms lookup is performed
  - JotForm is not used here; this endpoint preserves the default
    patient-questionnaire flow.

For JotForm-based questionnaire presentation flows, the bridge appends
`provider_key` to the returned form URL using the resolved provider integration
key for the order. When the order already carries
`orders.provider_platform_integration_key`, that value is used directly.

### Example Request

```bash
curl -X GET \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/get-patient-questionnaire/539fcc38-66fd-4668-8bbf-0c0f40d55b4b" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json"
```

### Successful Response

**Response:** `200 OK`

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "provider": "TelegraMD",
  "providerOrderId": "order::c7157e46-8e3a-47a8-9cea-9281fb56472b",
  "patientQuestionnaire": {
    "title": "Patient intake",
    "fields": [
      {
        "id": "primary-symptoms",
        "type": "symptoms",
        "options": [
          {
            "id": "symp::9d65e74b-caed-4b38-b343-d7f84946da60",
            "description": "Difficulty Sleeping",
            "name": "Difficulty Sleeping"
          },
          {
            "id": "symp::f2f869e6-0ff4-4491-a968-c0d8caed89a0",
            "description": "Joint Pain",
            "name": "Joint Pain"
          }
        ]
      }
    ]
  },
  "symptomsCount": 2,
  "symptomsQuestionCount": 1
}
```

MDI example:

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "provider": "MDI",
  "providerOrderId": "mdi-order-123",
  "patientQuestionnaire": {
    "title": "Patient intake",
    "fields": [
      {
        "id": "allergies",
        "type": "multiselect",
        "options": ["Penicillin", "Sulfa"]
      }
    ]
  },
  "symptomsCount": 0,
  "symptomsQuestionCount": 0
}
```

### Update Patient Profile

```http
POST /functions/v1/provider-platform-bridge/order/{order_id}/patient-profile
```

Notes:

- `order_id` is passed in the path.
- The request body must include either a non-empty `patientData` object or a
  non-empty raw app payload object.
- The endpoint is provider-aware and supports both Telegra and MD Integrations.
- The request body may be either:
  - `{ "patientData": { ... } }`
  - or the raw app payload object directly
- For Telegra:
  - the bridge can forward a Telegra-shaped `patientData` object directly
  - or transform the newer app payload shape into Telegra's expected patient
    profile payload
  - the bridge resolves the stored Telegra patient id and overwrites
    `patientData.id`
  - the bridge also overwrites `patientData.email`, `patientData.firstName`,
    `patientData.lastName`, and `patientData.phone` from the platform patient
    profile when available
- For MD Integrations:
  - the bridge transforms the newer app payload shape into the MDI PATCH
    contract
  - the bridge resolves the stored MDI patient id and authenticates with the
    tenant's MDI integration before calling the provider
- After a successful provider patient update, the bridge advances the order to
  the next active status and adds the note
  `Patient Questionnaire has been submitted.` to `order_status_history`.
  - **Transition guard:** this advance is gated by `expectedFromStatusKeys`
    (`patient_questionnaire_pending`, `medical_questionnaire_pending`). The order
    is advanced **only** when its current status is one of those questionnaire
    stages; otherwise the call is an idempotent no-op
    (`advanced: false`, `skippedReason: "unexpected_status"`). This prevents a
    questionnaire submission from blindly advancing the order to `next_status`
    from an unexpected state — e.g. a returning patient whose questionnaire was
    reused leaves the order already at `provider_review_pending` (advancing would
    self-approve the order and trigger payment capture with no real provider
    decision), or a `provider_order_creation_error` order (whose `next_status` is
    `order_cancelled`) being cancelled by a late questionnaire submit.
    `provider_approved` must come **only** from a real provider event via the
    `rtdh-webhook`. JotForm submissions use the equivalent
    `getJotformOrderStatusGate` check.
- After a successful provider patient update, the bridge also best-effort
  triggers `order-lifecycle` for the same order.

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/patient-profile" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "patientData": {
      "dateOfBirth": "1990-02-10"
    }
  }'
```

### Request Body

| Field         | Type                    | Required | Description                                                                                                                                                                                                                                                                                                          |
| ------------- | ----------------------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `patientData` | Record<string, unknown> | Yes      | Provider patient object. The endpoint accepts either a provider-shaped `patientData` object or the newer raw app questionnaire payload. The bridge resolves the selected provider for the order and transforms raw app payloads into the correct Telegra or MDI contract before sending the provider update request. |

### Example `patientData` Object

Telegra-shaped example:

```json
{
  "allergiesConfirmationDate": "2024-01-01T00:00:00.000Z",
  "bmi": 24.41,
  "dateOfBirth": "2222-12-22T00:00:00.000Z",
  "genderBiological": "male",
  "height": 72,
  "isPhoneValid": false,
  "labOrders": [],
  "medicationAllergies": [
    {
      "key": "allergyKey123",
      "medicationAllergies": "Penicillin",
      "reaction": "Rash"
    }
  ],
  "medicationsConfirmationDate": "2024-01-01T00:00:00.000Z",
  "notes": [],
  "patientMedications": [
    {
      "conditionPrescribed": "Headache",
      "dosage": "100mg",
      "frequency": "daily",
      "key": "5eb69712-2280-4be8-9966-df3d3aa202c8",
      "medication": "Aspirin"
    }
  ],
  "updatedAt": "2024-07-19T13:36:24.987Z",
  "weight": 180
}
```

Raw app payload example accepted for Telegra:

```json
{
  "symptoms": [
    "symp::f2f869e6-0ff4-4491-a968-c0d8caed89a0",
    "symp::58e002cd-ef59-4a76-9707-4173a3302955",
    "None of the above"
  ],
  "other_symptoms": "More symptoms",
  "medication": [
    {
      "medication_name": "Meds 1",
      "dosage": "dose 1",
      "frequency": "Frequency 1",
      "Condition_threated": "Cond 1"
    }
  ],
  "medication_confirmation": "Yes",
  "allergies": [
    {
      "Medication": "Med 1",
      "Reaction": "Reaction 1"
    }
  ],
  "allergies_confirmation": "Yes",
  "biological_gender": "female",
  "weight_lbs": "100 kgs",
  "height_ft": "160 cm",
  "birth_date": "1990-11-07"
}
```

The bridge transforms that example into a Telegra payload with:

- `dateOfBirth` as ISO datetime
- `genderBiological`
- `weight` in `lbs`
- `height` in `inches`
- `notes` populated from symptom names plus other symptoms
- `medicationAllergies[]`
- `patientMedications[]`
- confirmation timestamps when the confirmation fields are `"Yes"`

Raw app payload example accepted for MDI:

```json
{
  "medical_conditions": "medical conditions that are applicable to me",
  "current_medications": "regular medications",
  "allergies": [
    {
      "Medication": "Meds alergic",
      "Reaction": "rash"
    },
    {
      "Medication": "Burfen",
      "Reaction": "headicks"
    }
  ],
  "gender": "female",
  "pregnancy": "no",
  "weight_lbs": "100 kgs",
  "height_ft": "160 cm",
  "birth_date": "1988-04-01"
}
```

The bridge transforms that example into an MDI payload with:

- `gender` in ISO 5218 numeric format
- `date_of_birth` as `YYYY-MM-DD`
- `weight` in `kgs`
- `height` in `cm`
- `allergies` flattened into a provider string field

### Successful Response

**Response:** `200 OK`

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "patientId": "7e85a8a8-8c2b-4b3e-bbf7-85a6afce1cfe",
  "provider": "TelegraMD",
  "providerOrderId": "order::c7157e46-8e3a-47a8-9cea-9281fb56472b",
  "providerPatientId": "pat::db1ce9fa-06c6-4d1a-97d7-aa29e1a8c794",
  "orderStatusAdvanced": true,
  "previousStatusKey": "medical_questionnaire_pending",
  "newStatusKey": "provider_review_pending",
  "orderLifecycleTriggered": true,
  "response": {
    "id": "pat::db1ce9fa-06c6-4d1a-97d7-aa29e1a8c794",
    "firstName": "Jane",
    "lastName": "Doe"
  }
}
```

### Answer Questionnaire Location

```http
POST /functions/v1/provider-platform-bridge/order/{order_id}/questionnaire-answer-location
```

Notes:

- `order_id` is passed in the path.
- The request body must include `questionnaire-id`, `location`, and `value`.

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/questionnaire-answer-location" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "questionnaire-id": "qi-1",
    "location": "patient.address.state",
    "value": "TX"
  }'
```

### Example File Upload Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/questionnaire-answer-location" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -F "questionnaire-id=qi-1" \
  -F "location=patient.identity.document" \
  -F "value=@/path/to/document.jpg"
```

### Request Body

| Field              | Type                       | Required | Description                                                                                                                                                           |
| ------------------ | -------------------------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `questionnaire-id` | string                     | Yes      | Telegra questionnaire instance id associated with the order                                                                                                           |
| `location`         | string                     | Yes      | Telegra answer location path                                                                                                                                          |
| `value`            | string \| string[] \| file | Yes      | Value to submit for that location. Send JSON string for scalar answers, JSON string array for multiple-option answers, or `multipart/form-data` when uploading a file |

### Example Multiple-Option Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/questionnaire-answer-location" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "questionnaire-id": "qi-1",
    "location": "loc::weight-loss:2",
    "value": ["option-a", "option-b"]
  }'
```

### Successful Response

**Response:** `200 OK`

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "provider": "TelegraMD",
  "providerOrderId": "telegra-order-123",
  "questionnaireId": "qi-1",
  "response": {
    "status": "ok"
  }
}
```

### Submit MDI Medical Question

```http
POST /functions/v1/provider-platform-bridge/order/{order_id}/mdi-medical-questions
```

Notes:

- `order_id` is passed in the path.
- This route is only available for orders linked to `md_integrations`.
- The order must already have a stored MDI case id in
  `order_provider_platform_links.provider_order_id`.
- The request body must be a non-empty array of question objects.
- For file questions, use `multipart/form-data` with a `questions` JSON field
  plus one attached file field per file question.
- After every MDI question/file is sent successfully, the bridge releases the
  MDI case hold by calling `PATCH /v1/partner/cases/{case_id}/status` with
  `{ "hold_status": false }`. This behavior is MDI-only and does not affect
  Telegra.
- When native MDI questionnaires were resolved from a bundle, submitted
  questions are matched against all resolved medication questionnaires for type
  and option normalization.

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/mdi-medical-questions" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '[
    {
      "question": "Are you pregnant?",
      "answer": "true",
      "type": "boolean",
      "important": true,
      "is_critical": true,
      "display_in_pdf": true,
      "description": "Here is the question description",
      "label": "Question Label Example",
      "metadata": "example of metadata #123 for question",
      "displayed_options": ["yes", "no"]
    },
    {
      "question": "Do you smoke?",
      "answer": "false",
      "type": "boolean"
    }
  ]'
```

Multipart example for file questions:

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/order/539fcc38-66fd-4668-8bbf-0c0f40d55b4b/mdi-medical-questions" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -F 'questions=[
    {
      "question": "Upload your lab result",
      "type": "file",
      "file_field": "lab_result_file",
      "file_type": "lab-result"
    }
  ]' \
  -F "lab_result_file=@/path/to/lab-result.pdf"
```

### Request Body

The JSON request body must be a non-empty array of objects with this shape:

| Field               | Type     | Required | Description                                      |
| ------------------- | -------- | -------- | ------------------------------------------------ |
| `question`          | string   | Yes      | The question text sent to the MDI case endpoint  |
| `answer`            | string   | Yes      | The answer value to persist on the MDI case      |
| `type`              | string   | Yes      | MDI question type, for example `boolean`         |
| `important`         | boolean  | No       | Whether the question is marked important         |
| `is_critical`       | boolean  | No       | Whether the question is marked critical          |
| `display_in_pdf`    | boolean  | No       | Whether MDI should display the answer in the PDF |
| `description`       | string   | No       | Optional question description                    |
| `label`             | string   | No       | Optional question label                          |
| `metadata`          | string   | No       | Optional question metadata                       |
| `displayed_options` | string[] | No       | Optional list of rendered answer options         |

For `multipart/form-data`, include:

| Field       | Type   | Required | Description                                                                       |
| ----------- | ------ | -------- | --------------------------------------------------------------------------------- |
| `questions` | string | Yes      | JSON-encoded array of question objects                                            |
| file field  | file   | Yes\*    | Attached file for any question whose `type` is `file`; referenced by `file_field` |

\* Required for each file-type question.

Additional file-question fields inside each `questions` array item:

| Field        | Type   | Required | Description                                                                  |
| ------------ | ------ | -------- | ---------------------------------------------------------------------------- |
| `file_field` | string | Yes      | Name of the multipart file field that contains the uploaded file             |
| `file_type`  | string | No       | MDI file category sent to `POST /v1/partner/files`; defaults to `lab-result` |

### Successful Response

**Response:** `200 OK`

```json
{
  "orderId": "539fcc38-66fd-4668-8bbf-0c0f40d55b4b",
  "provider": "MDI",
  "providerOrderId": "mdi-case-123",
  "response": [
    {
      "status": "ok"
    },
    {
      "status": "ok"
    }
  ]
}
```

### Validate JotForm Questionnaire Form

```http
POST /functions/v1/provider-platform-bridge/jotform-form-validation
```

This endpoint validates a product/provider-platform JotForm questionnaire ID
before the admin UI saves it to the Jotform questionnaire fields on
`product_provider_platforms`: `jotform_new_order_questionnaire_id` for new-user
orders or `jotform_renewall_questionnaire_id` for renewal orders. The JotForm
API key is read server-side from the tenant's enabled `jotform` integration and
is not exposed to the browser.

The configured JotForm form must include the required hidden configuration
fields:

| Hidden field name           | Required value                                                      |
| --------------------------- | ------------------------------------------------------------------- |
| `patient_platform_order_id` | Populated by the Patient Platform when the form is opened.          |
| `provider_key`              | Fixed provider integration key, e.g. `md_integrations`.             |
| `questionnaire_type`        | Fixed per form: `patient_questionnaire` or `medical_questionnaire`. |

`patient_platform_order_id` is required so downstream RTDH/JotForm payloads can
be correlated back to the Patient Platform order. `questionnaire_type` must be
fixed per questionnaire and must not be patient-editable. Disable PHI for these
hidden fields so the JotForm API can return them.

Each new JotForm form must also configure a JotForm Webhook to RTDH. When
`settings.default_webhook_url` is defined on the tenant Jotform integration,
`POST /jotform-form-validation` checks the target form and adds that default
webhook if it is missing. This is additive and preserves all existing Jotform
webhook URLs.

```text
https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev
```

If no Default Webhook URL is configured, webhook validation is suspended and the
admin UI shows a warning near Jotform ID fields.

Without this webhook, the JotForm submission notification will not reach RTDH
and the submission will not be used by the Patient Platform processing flow.

The lookup URL is normalized from the tenant's configured `api_url`: URLs such
as `https://www.jotform.com` are called as
`https://www.jotform.com/API/form/{formId}/questions`, URLs already ending in
`/api` are not duplicated, and API hosts such as `https://api.jotform.com` use
`https://api.jotform.com/form/{formId}/questions`.

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/jotform-form-validation" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "tenantIntegrationId": "tenant-integration-uuid",
    "formId": "123456789012345"
  }'
```

### Request Body

| Field                 | Type   | Required | Description                                                                                                          |
| --------------------- | ------ | -------- | -------------------------------------------------------------------------------------------------------------------- |
| `tenantIntegrationId` | string | Yes      | The provider platform `tenant_integrations.id` associated with the product/provider-platform assignment being edited |
| `formId`              | string | Yes      | The numeric JotForm form ID entered as the questionnaire ID                                                          |

### Successful Response

**Response:** `200 OK`

```json
{
  "valid": true,
  "formId": "123456789012345",
  "webhookStatus": "configured",
  "hasDefaultWebhook": true,
  "webhookCount": 2,
  "message": "Webhook properly setup"
}
```

## Get Or Update Jotform Form Webhooks

Reads or additively fixes the current Default Webhook URL on a Jotform form. The
bridge uses the tenant's Jotform API credentials and `jf-team-id` header when
present.

```text
POST /functions/v1/provider-platform-bridge/jotform-form-webhooks
PUT /functions/v1/provider-platform-bridge/jotform-form-webhooks
```

`POST` returns compact status for the current Default Webhook URL. `PUT` ensures
that URL exists by creating it only when missing. Existing Jotform webhook URLs
are never deleted or changed by this endpoint. If the tenant does not have
`settings.default_webhook_url`, the endpoint returns
`webhookStatus: "default_not_configured"` and does not call the Jotform webhook
API. Browser preflight allows `PUT` for this route.

### Example Lookup Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/jotform-form-webhooks" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "tenantIntegrationId": "tenant-integration-uuid",
    "formId": "123456789012345"
  }'
```

### Example Update Request

```bash
curl -X PUT \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/jotform-form-webhooks" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "tenantIntegrationId": "tenant-integration-uuid",
    "formId": "123456789012345"
  }'
```

### Successful Response

**Response:** `200 OK`

```json
{
  "success": true,
  "formId": "123456789012345",
  "defaultWebhookUrl": "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev",
  "webhookStatus": "configured",
  "hasDefaultWebhook": true,
  "webhookCount": 2,
  "added": false
}
```

## Sync Configured Jotform Default Webhooks

Starts a backend sync for all currently configured Jotform IDs in the tenant.
The sync includes provider-level patient questionnaire IDs and
product/provider-level medical questionnaire IDs.

```text
POST /functions/v1/provider-platform-bridge/jotform-webhook-sync
```

The endpoint skips missing or inaccessible Jotforms and preserves all existing
Jotform webhook URLs. It only adds the current Default Webhook URL when the form
is accessible and missing that URL.

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/jotform-webhook-sync" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "tenantIntegrationId": "jotform-tenant-integration-uuid"
  }'
```

```json
{
  "success": true,
  "defaultWebhookUrl": "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev",
  "checked": 3,
  "configured": 2,
  "added": 1,
  "missing": 0,
  "skipped": 1,
  "inaccessible": 1,
  "results": [
    {
      "formId": "123456789012345",
      "webhookStatus": "configured",
      "hasDefaultWebhook": true,
      "added": true,
      "skipped": false
    }
  ]
}
```

## Generate Telegra Patient Jotform

Creates the default Telegra patient questionnaire in Jotform and optionally
saves the generated form id as the active Telegra patient questionnaire form for
that provider integration.

```text
POST /functions/v1/provider-platform-bridge/jotform-patient-questionnaire
```

This endpoint uses the tenant Jotform integration credentials stored in the
backend. The Jotform API key is never sent to the browser.

The generated form includes these required fields:

| Field                     | Jotform field type | Unique name                 | Default                               |
| ------------------------- | ------------------ | --------------------------- | ------------------------------------- |
| Provider key              | Single Choice      | `provider_key`              | `telegramd`                           |
| Patient platform order ID | Short Text         | `patient_platform_order_id` | populated later through the embed URL |
| Questionnaire type        | Single Choice      | `questionnaire_type`        | `patient_questionnaire`               |

The provider key options are `md_integrations`, `telegramd`, and `zito_care`.
The questionnaire type options are `medical_questionnaire` and
`patient_questionnaire`.

Telegra patient-questionnaire submissions from generated Jotforms are normalized
into the same `patientData` shape as the native patient questionnaire before the
bridge calls Telegra. In particular, `weight_value` plus `weight_unit` becomes
`weight_lbs` in pounds, `height_value` plus `height_unit` becomes `height_ft` in
inches, `date_of_birth` is accepted as `birth_date`, and generated medication
widget labels such as `Medication name`, `Dosage`, `Frequency`, and
`Condition treated` are converted into the same medication-list structure used
by the native flow.

### Example Request

```bash
curl -X POST \
  "VITE_SUPABASE_URL/functions/v1/provider-platform-bridge/jotform-patient-questionnaire" \
  -H "apikey: <supabase-anon-key>" \
  -H "Authorization: Bearer <supabase-user-jwt>" \
  -H "Content-Type: application/json" \
  -d '{
    "tenantIntegrationId": "telegra-tenant-integration-uuid",
    "title": "Telegra Patient Questionnaire",
    "webhookUrl": "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev",
    "saveAsPatientQuestionnaire": true
  }'
```

### Request Body

| Field                        | Type    | Required | Description                                                                                             |
| ---------------------------- | ------- | -------- | ------------------------------------------------------------------------------------------------------- |
| `tenantIntegrationId`        | string  | Yes      | Enabled Telegra `tenant_integrations.id`; used to resolve the tenant and permissions                    |
| `title`                      | string  | No       | Jotform form title. Defaults to `Telegra Patient Questionnaire`                                         |
| `webhookUrl`                 | string  | No       | RTDH Jotform Consumer URL. Defaults to tenant `settings.default_webhook_url`, then the dev receiver URL |
| `saveAsPatientQuestionnaire` | boolean | No       | Defaults to `true`; when true, saves the new form id to the Telegra provider integration settings       |

### Successful Response

**Response:** `200 OK`

```json
{
  "success": true,
  "provider": "telegramd",
  "providerIntegrationId": "telegra-tenant-integration-uuid",
  "questionnaireType": "patient_questionnaire",
  "formId": "123456789012345",
  "formUrl": "https://www.jotform.com/123456789012345",
  "webhook": {
    "attached": true,
    "webhookUrl": "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev"
  },
  "savedAsPatientQuestionnaire": true,
  "hiddenFields": [
    "provider_key",
    "patient_platform_order_id",
    "questionnaire_type"
  ]
}
```

---

## Provider Validation Flow

Before calling Telegra, the bridge validates that the order belongs to the
expected provider platform.

### Validation Steps

1. Fetch the tenant's enabled integration where:
   - `integration_key = "telegramd"`
   - `is_enabled = true`
2. Load all `order_provider_platform_links` for the order and tenant
3. Find the link whose `tenant_integration_id` matches that enabled Telegra
   integration
4. If `metadata.provider` exists on the link, normalize it and verify it maps to
   Telegra

### Current Accepted Provider Names

These metadata values are treated as Telegra:

- `TelegraMD`
- `telegra`
- `telegramd`

### Validation Failure Behavior

If the order is linked to another provider platform, the function returns
`409 Conflict` and does not call Telegra.

Before any provider lookup, the bridge also validates the incoming Supabase user
JWT by calling `auth.getUser()`. Requests without a valid bearer token return
`401 Unauthorized`.

For `GET /symptoms`, the bridge also validates tenant access before reading the
tenant's Telegra configuration:

1. Resolve the tenant from `tenant_id`, `slug`, `x-tenant-id`, or
   `x-tenant-slug`
2. Check whether the caller is a platform superadmin via
   `public.is_platform_superadmin`
3. If not superadmin, check the caller's tenant memberships via
   `public.get_user_tenant_ids`
4. Only after that, load the tenant's enabled Telegra integration and call
   Telegra

For `GET /get-patient-questionnaire/:orderId`, the bridge first resolves the
selected provider platform for the order. Telegra-backed orders then use the
same order-based Telegra validation flow as `GET /get-questionnaires/:orderId`.
MD Integrations orders instead load the configured tenant questionnaire
definition directly without making provider-side questionnaire or symptom calls.

---

## Data Dependencies

The endpoint depends on these records being present.

### `orders`

Used fields:

- `id`
- `tenant_id`
- `patient_id`
- `product_id`
- `provider_platform_integration_key`

### `tenant_integrations`

Used fields:

- `id`
- `tenant_id`
- `integration_key`
- `is_enabled`
- `provider_legal_agreement` for `agreement` questions returned by
  `GET /get-questionnaires/:orderId`
- `settings.url` for Telegra
- `settings.access_token` as a legacy Telegra fallback
- `settings.username` and `settings.password` for Telegra
- `settings.backend_url` for MD Integrations
- `settings.client_id` and `settings.client_secret` for MD Integrations
- `settings.patient_questionnaire_definition` for Telegra and MD Integrations
- `settings.api_url` for `jotform`
- `settings.api_key` for `jotform`

Notes:

- Telegra routes use `integration_key = telegramd`
- MDI patient questionnaire routes use `integration_key = md_integrations`
- MD Integrations no longer uses a separate webhook authorization tenant setting
- JotForm form validation uses `integration_key = jotform`

### `tenants`

Used fields:

- `id`
- `slug`

### `order_provider_platform_links`

Used fields:

- `order_id`
- `tenant_id`
- `tenant_integration_id`
- `provider_order_id`
- `metadata.provider`
- `metadata.questionnaire_instance_ids`

Notes:

- `tenant_integration_id` is the primary provider-resolution signal for
  `GET /get-patient-questionnaire/:orderId`
- metadata provider fields are treated as secondary or legacy fallback signals
- `provider_order_id` is used as the MDI case id for
  `POST /order/:orderId/mdi-medical-questions`

### `patient_provider_platform_links`

Used fields:

- `patient_id`
- `tenant_id`
- `tenant_integration_id`
- `provider_patient_id`

### `patients`

Used fields:

- `id`
- `tenant_id`
- `first_name`
- `last_name`
- `email`

### `product_provider_platforms`

Used fields:

- `product_id`
- `tenant_integration_id`
- `provider_product_sku`
- `provider_product_variation_sku`
- `jotform_new_order_questionnaire_id`
- `jotform_renewall_questionnaire_id`
- `is_enabled`

### `product_medications`

Used fields:

- `product_id`
- `medication_id`

### `medications`

Used fields:

- `id`
- `title`
- `offering_id` for MD Integrations native questionnaire and case-offering
  resolution

### Required Link Metadata

Telegra questionnaire flows expect the order link metadata to contain:

```json
{
  "provider": "TelegraMD",
  "questionnaire_instance_ids": ["qi-1", "qi-2"]
}
```

These ids are currently persisted during successful Telegra order creation in
`supabase/functions/order-lifecycle/telegra-helper.ts`.

### Tenant Access RPCs

Used functions:

- `public.is_platform_superadmin`
- `public.get_user_tenant_ids`

---

## Response Models

### Success Response

| Field                       | Type                                                                | Description                                                                                                                                                                                                                                                            |
| --------------------------- | ------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `orderId`                   | string                                                              | Internal order UUID                                                                                                                                                                                                                                                    |
| `patientId`                 | string                                                              | Present on `POST /order/:orderId/patient-profile`                                                                                                                                                                                                                      |
| `provider`                  | string                                                              | Current provider name returned by the bridge                                                                                                                                                                                                                           |
| `providerOrderId`           | string \| null                                                      | External provider order id if available                                                                                                                                                                                                                                |
| `providerPatientId`         | string                                                              | Present on `POST /order/:orderId/patient-profile`                                                                                                                                                                                                                      |
| `orderStatusAdvanced`       | boolean                                                             | Present on `POST /order/:orderId/patient-profile`; indicates whether the bridge advanced the order to the next active status                                                                                                                                           |
| `previousStatusKey`         | string \| null                                                      | Present on `POST /order/:orderId/patient-profile`; the order status key before the patient questionnaire submission transition                                                                                                                                         |
| `newStatusKey`              | string \| null                                                      | Present on `POST /order/:orderId/patient-profile`; the next order status key applied after a successful Telegra patient update                                                                                                                                         |
| `orderLifecycleTriggered`   | boolean                                                             | Present on `POST /order/:orderId/patient-profile`; indicates whether the bridge successfully called `order-lifecycle`                                                                                                                                                  |
| `holdStatusReleased`        | boolean                                                             | Present on MDI medical-question submission responses when the MDI case hold was released                                                                                                                                                                               |
| `holdStatusResponse`        | unknown                                                             | Present on `POST /order/:orderId/mdi-medical-questions`; raw MDI response from `PATCH /v1/partner/cases/{case_id}/status`                                                                                                                                              |
| `questionnairePresentation` | Record<string, unknown>                                             | Present on MDI `GET /get-questionnaires/:orderId`; tells the app whether to render `jotform` or the native questionnaire response                                                                                                                                      |
| `patientQuestionnaire`      | Record<string, unknown>                                             | Present on `GET /get-patient-questionnaire/:orderId`; tenant questionnaire definition with populated symptom options                                                                                                                                                   |
| `questionnaireInstanceIds`  | string[]                                                            | Present on `GET /get-questionnaires/:orderId`                                                                                                                                                                                                                          |
| `questionnaires`            | Record<string, unknown>                                             | Present on `GET /get-questionnaires/:orderId`; aggregated questionnaire entries keyed by questionnaire instance id. Native Telegra/MDI question objects with `type = "agreement"` include `provider_legal_agreement` from the selected provider tenant integration |
| `symptomsCount`             | number                                                              | Present on `GET /get-patient-questionnaire/:orderId`; number of Telegra symptom summaries used to populate the questionnaire                                                                                                                                           |
| `symptomsQuestionCount`     | number                                                              | Present on `GET /get-patient-questionnaire/:orderId`; number of questionnaire objects whose `options` were replaced for `symptoms`                                                                                                                                     |
| `symptoms`                  | { id: string \| null; description: string \| null; name: string }[] | Present on `GET /symptoms`; deduplicated Telegra symptom summaries plus a trailing `None of the above` option                                                                                                                                                          |
| `questionnaireId`           | string                                                              | Present on `POST /order/:orderId/questionnaire-answer-location`                                                                                                                                                                                                        |
| `response`                  | unknown                                                             | Present on `POST /order/:orderId/patient-profile`, `POST /order/:orderId/questionnaire-answer-location`, and `POST /order/:orderId/mdi-medical-questions`; raw provider response body, or an array of raw provider response bodies for the MDI medical-questions route |

### Error Response

| Field       | Type   | Description                       |
| ----------- | ------ | --------------------------------- |
| `error`     | string | Short error category              |
| `message`   | string | Human-readable explanation        |
| `requestId` | string | Present on internal server errors |

---

## Error Handling

### `400 Bad Request`

Returned when:

- the path does not include an order id
- the `GET /symptoms` request is missing a tenant identifier
- the `GET /symptoms` request is missing `products`
- the `POST /order/:orderId/patient-profile` body is empty or cannot be parsed
  into a patient data object
- the `POST` body is missing `questionnaire-id`, `location`, or `value`
- the `POST /order/:orderId/mdi-medical-questions` body is missing `question`,
  `answer`, or `type`, or is not a non-empty array
- the `POST /jotform-form-validation` body is missing `tenantIntegrationId` or
  `formId`
- the `POST /jotform-form-validation` `formId` is not a numeric JotForm form ID

Example:

```json
{
  "error": "Missing orderId",
  "message": "Provide orderId in the request path"
}
```

POST body validation example:

```json
{
  "error": "Invalid request body",
  "message": "Provide questionnaire-id, location, and either a non-empty string value or a file upload"
}
```

Symptoms validation examples:

```json
{
  "error": "Missing tenant identifier",
  "message": "Provide tenant_id or slug as a query parameter, or x-tenant-id or x-tenant-slug as a request header"
}
```

```json
{
  "error": "Missing products",
  "message": "Provide at least one Telegra product id in the products query parameter"
}
```

```json
{
  "error": "Invalid request body",
  "message": "Provide a non-empty patientData object"
}
```

### `404 Not Found`

Returned when:

- the route is unsupported
- the order does not exist
- the provided tenant identifier does not match any tenant
- there is no enabled Telegra integration for the tenant
- there is no enabled MD Integrations integration for the order tenant when the
  selected provider is MDI
- there is no Telegra provider-platform link for a Telegra-backed order
- there is no patient profile row for the patient associated with the order
- there is no Telegra patient id stored for the patient associated with the
  order
- there is no MD Integrations patient id stored for the patient associated with
  the order
- there are no stored questionnaire instance ids for a Telegra-backed order
- the selected provider integration does not have a valid
  `patient_questionnaire_definition` object configured
- the `tenantIntegrationId` provided to `POST /jotform-form-validation` does not
  match an existing provider platform integration

### `405 Method Not Allowed`

Returned when a method other than `GET`, `POST`, or `OPTIONS` is used.

### `401 Unauthorized`

Returned when:

- the `Authorization` header is missing
- the provided Supabase JWT is invalid or expired

### `409 Conflict`

Returned when:

- the order is linked to a different provider platform
- the order link metadata identifies a non-Telegra provider for a Telegra-only
  route
- the order does not yet have a stored Telegra order id
- the submitted `questionnaire-id` does not belong to the order
- the tenant's JotForm integration is not enabled or is missing `api_url` or
  `api_key`

Example:

```json
{
  "error": "Provider platform mismatch",
  "message": "The order is linked to a different provider platform and cannot be fetched from Telegra"
}
```

### `403 Forbidden`

Returned when the caller does not have access to the tenant requested by
`GET /symptoms` or `POST /jotform-form-validation`.

Questionnaire mismatch example:

```json
{
  "error": "Questionnaire mismatch",
  "message": "The provided questionnaire id is not available for this order on Telegra"
}
```

### `422 Unprocessable Entity`

Returned by `POST /jotform-form-validation` when the JotForm form cannot be
retrieved or does not include the required `patient_platform_order_id` order
correlation field.

Example:

```json
{
  "error": "Missing required Jotform field",
  "message": "This Jotform form must include a field named patient_platform_order_id before it can be saved."
}
```

### `500 Internal Server Error`

Returned when:

- Supabase queries fail unexpectedly
- tenant integration configuration is missing `url` and both `username/password`
  and legacy `access_token`
- MD Integrations configuration is missing `backend_url`, `client_id`, or
  `client_secret`
- an MDI order does not have a stored case id for
  `POST /order/:orderId/mdi-medical-questions`
- `patient_questionnaire_definition` exists but cannot be treated as a JSON
  object
- a Telegra questionnaire schema request fails
- a Telegra questionnaire instance request fails
- a Telegra conditions and symptoms request fails
- a Telegra patient update request fails
- a Telegra `answerLocation` request fails
- MDI authentication fails
- an MDI patient update request fails

Example:

```json
{
  "error": "Internal server error",
  "message": "Telegra questionnaire schema fetch failed for qi-1: 404 Not Found",
  "requestId": "f6f1f7f6-d4b2-45f3-94c5-06f7234f4f7c"
}
```

---

## Implementation Notes

### Current Provider Scope

The bridge is now provider-aware:

- `telegra.ts` handles Telegra-specific endpoints and outbound calls
- `mdi.ts` handles MDI-specific questionnaire retrieval and patient profile
  updates
- `common.ts` contains shared Supabase, routing, and parsing helpers

### Post-Update Behavior

After a successful `POST /order/:orderId/patient-profile` call for either
Telegra or MD Integrations, the bridge:

1. advances the order to the next configured status
2. writes `Patient Questionnaire has been submitted.` to `order_status_history`
3. best-effort triggers `order-lifecycle` for the same order

### Patient Profile Transformations

For `POST /order/:orderId/patient-profile`, the bridge supports
provider-specific measurement normalization:

- Telegra patient profile updates expect:
  - `weight` in `lbs`
  - `height` in `inches`
- MD Integrations patient profile updates expect:
  - `weight` in `kgs`
  - `height` in `cm`

The bridge inspects the unit contained in the incoming raw app payload string,
not just the field name. Examples:

- Telegra:
  - `"100 kgs"` -> `220.46` lbs
  - `"160 cm"` -> `62.99` inches
- MDI:
  - `"100 kgs"` -> `100` kgs
  - `"160 cm"` -> `160` cm
  - `"220.46 lbs"` -> `100` kgs

### Outbound Telegra Call Pattern

For each questionnaire instance id, the bridge calls:

```text
GET {tenant_integrations.settings.url}/questionnaireInstances/{questionnaireInstanceId}/schema
```

and:

```text
GET {tenant_integrations.settings.url}/questionnaireInstances/{questionnaireInstanceId}
```

For symptoms and patient questionnaire composition, the bridge calls:

```text
GET {tenant_integrations.settings.url}/products/actions/getConditionsAndSymptoms
```

If the order product has a `product_provider_platforms.provider_product_sku`
value, the bridge forwards it as:

```text
GET {tenant_integrations.settings.url}/products/actions/getConditionsAndSymptoms?products=pro::abc,pro::def
```

When building `GET /get-patient-questionnaire/:orderId` for Telegra orders, the
bridge walks the configured `settings.patient_questionnaire_definition` object
recursively and replaces every object's `options` field where
`type = "symptoms"` with the deduplicated Telegra symptom summaries:

```json
[
  {
    "id": "symp::9d65e74b-caed-4b38-b343-d7f84946da60",
    "description": "Difficulty Sleeping",
    "name": "Difficulty Sleeping"
  }
]
```

For answer-location updates, the bridge calls:

```text
PUT {tenant_integrations.settings.url}/questionnaireInstances/{questionnaireInstanceId}/actions/answerLocation?shouldNavigateNext=true
```

JSON request body sent for scalar answers:

```json
{
  "location": "<location>",
  "value": "<value>"
}
```

If the incoming bridge request uploads a file, the bridge forwards
`multipart/form-data` to Telegra with:

- `location` as a text field
- `value` as the uploaded file field

For multiple-option answers (array `value`), the bridge still calls
`answerLocation?shouldNavigateNext=true` and sends the array as the JSON
`value`:

```json
{
  "location": "<location>",
  "value": ["<selected-option-1>", "<selected-option-2>"]
}
```

When `location` is `loc::informed-consent:1`, the bridge sends
`data.agreementData` instead of `value`:

```json
{
  "location": "loc::informed-consent:1",
  "data": {
    "agreementData": {
      "consent": true,
      "consentDate": "<generated-iso-timestamp>",
      "signature": "<incoming-signature-value>"
    }
  }
}
```

Headers sent:

```http
Authorization: Bearer <access_token>
x-request-id: <generated-request-id>
x-source: provider-platform-bridge
```

For non-file answers the bridge sends `Content-Type: application/json`. For file
uploads it lets the runtime set the multipart boundary automatically.

### Aggregation Shape

The bridge returns questionnaire entries keyed by questionnaire instance id:

```json
{
  "questionnaires": {
    "qi-1": {
      "schema": { "...raw Telegra schema response..." },
      "status": "in_progress",
      "valid": false
    },
    "qi-2": {
      "schema": { "...raw Telegra schema response..." },
      "status": "completed",
      "valid": true
    }
  }
}
```

### Authentication Behavior

The bridge validates the incoming Supabase user token with `auth.getUser()`
before using the service-role client for internal data reads.

### Path Normalization

The router supports both runtime path variants:

- `/provider-platform-bridge/...`
- `/functions/v1/provider-platform-bridge/...`

### Provider Resolution

For `GET /get-patient-questionnaire/:orderId`, the router resolves the provider
using this precedence:

1. the selected `order_provider_platform_links.tenant_integration_id`
2. link metadata provider fields
3. `orders.provider_platform_integration_key`
