# Jotform Integration

Jotform is used as a HIPAA-compliant embedded forms provider. The Patient
Platform frontend renders the selected Jotform form and submits responses
directly to Jotform. Jotform then notifies the RTDH JotForm Consumer about the
new submission; RTDH processes that event and forwards it to the Patient
Platform backend, where the questionnaire is finalized and sent to the selected
provider workflow, for example MDI.

---

## Requirements

### Tenant-Level

- A **Jotform API Key** with read access to submissions.
- A **Jotform API URL** (e.g. `https://api.jotform.com`).
- A **Jotform Team Workspace ID** for the workspace that owns the forms and
  submissions.
- A Jotform plan and account configuration with HIPAA compliance enabled,
  including the required Business Associate Agreement (BAA) where applicable.
- These values must be saved and enabled in the tenant's integration settings
  before any product-level configuration takes effect.

### Form-Level (per Jotform form)

Every Jotform form linked to the platform **must** contain the following hidden
configuration fields:

| Field name                  | Jotform field type | Value                                                                                                                                                                                                                                                                                                                                               |
| --------------------------- | ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `patient_platform_order_id` | Short Text         | Set by the Patient Platform backend as a query-string parameter on the Jotform URL returned to the frontend. The field must exist on the Jotform form, but its value must not be hard-coded in Jotform.                                                                                                                                             |
| `provider_key`              | Single Choice Menu | Set by the Patient Platform backend as a query-string parameter on the Jotform URL returned to the frontend, based on the selected provider workflow for the order. The field must exist on the Jotform form, but its value must not be hard-coded in Jotform. Configure it with the fixed options `md_integrations`, `telegramd`, and `zito_care`. |
| `questionnaire_type`        | Single Choice Menu | Fixed value per form: `patient_questionnaire` for patient questionnaires or `medical_questionnaire` for medical/provider questionnaires. Set the correct default value directly on the Jotform form.                                                                                                                                                |

- The fields must use the exact names above.
- These configuration fields are configured at the widget level in the Jotform
  form builder and should be hidden from patients.
- **PHI must be disabled** for these fields — otherwise RTDH may not be able to
  read the values from the Jotform API.
- `patient_platform_order_id` lets RTDH match the submitted form back to the
  correct order. The field must be available on the Jotform form; the Patient
  Platform backend supplies the value in the Jotform URL query string returned
  to the frontend.
- `provider_key` lets RTDH route the submission to the selected provider
  workflow. The field must exist on the Jotform form, but its value must not be
  hard-coded in Jotform; the Patient Platform backend supplies it in the Jotform
  URL query string returned to the frontend.
- `provider_key` must be configured as a Single Choice Menu with the fixed
  options `md_integrations`, `telegramd`, and `zito_care`.
- `questionnaire_type` is fixed per questionnaire and should not be changed by
  the patient.

Every new Jotform form must also have a Jotform Webhook configured to send
submissions to RTDH. Use the RTDH JotForm Consumer (RTDH webhook receiver URL)
for the environment where the form will be used. For example, in dev:

```
https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev
```

Staging and production forms must use the corresponding RTDH JotForm Consumer
URL for their environment.

If this webhook is not configured on the form, RTDH will not receive the
submission event and the form submission will not be processed by the Patient
Platform workflow.

Generated forms use the Jotform API to attach the webhook automatically through
`POST /form/{formId}/webhooks` with `webhookURL` set to the configured default
webhook URL. When a configured form is validated or fixed from admin UI, the
backend checks whether the current Default Webhook URL is present and adds it if
missing. Existing Jotform webhook URLs are preserved.

### Optional MDI Question Priority Fields

Medical questionnaire forms that submit to MDI can mark submitted answers as
important or critical directly in Jotform. RTDH reads these fields and does not
forward them as patient answers.

Use either of these patterns:

| Jotform field name | Value |
| --- | --- |
| `important_questions` | Comma, newline, semicolon, or pipe-separated question names, labels, or order numbers to send to MDI with `important: true`. |
| `critical_questions` | Comma, newline, semicolon, or pipe-separated question names, labels, or order numbers to send to MDI with `critical: true`. |
| `<question_name>_important` or `important_<question_name>` | Boolean value such as `Yes`, `true`, or `1` for one question. |
| `<question_name>_is_critical` or `is_critical_<question_name>` | Boolean value such as `Yes`, `true`, or `1` for one question. |

For example, if a Jotform answer field is named `allergies`, adding a hidden
field named `allergies_is_critical` with value `Yes` causes the submitted
`allergies` answer to be sent to MDI as critical.

When the tenant Default Webhook URL is changed in Patient Platform Admin, the
backend can sync all currently configured Jotform IDs. The sync loads
provider-level patient questionnaire IDs and product/provider medical
questionnaire IDs, skips missing or inaccessible forms, and additively attaches
the new default webhook only where needed.

### Product-Provider Level

- Each product–provider platform assignment can optionally store two Jotform
  questionnaire IDs:
  - **Jotform New Order Questionnaire ID** for new users / first-time orders.
  - **Jotform Renewal Questionnaire ID** for renewal orders.
- Maximum length: **128 characters**.
- Both fields are saved per product/provider-platform assignment, so the same
  product can use different Jotform questionnaires for different provider
  platforms.

---

## Setup

### 1. Configure the Jotform integration (API level)

1. Sign in as a **Tenant Admin**.
2. Navigate to **Settings → Questionnaires → Connection**.
3. Click **Add Settings** (or **Edit Settings** if already configured).
4. Fill in the required fields:

| Field               | Description                                                                                                                                                       |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| API URL             | Base URL RTDH will call when retrieving submitted forms (e.g. `https://www.jotform.com`, `https://www.jotform.com/api`, or `https://api.jotform.com`).            |
| API Key             | Authentication token used by RTDH to access the Jotform API.                                                                                                      |
| Team Workspace ID   | Workspace/team identifier required by Jotform for submission access. RTDH sends this as `jf-team-id` when requesting form submissions.                            |
| Default Webhook URL | RTDH JotForm Consumer URL used when generated forms are created automatically and when validating configured Jotform IDs. If empty, webhook checks are suspended. |

The Default Webhook URL field shows the RTDH dev receiver only as an `Example:`
placeholder until an admin saves an actual value.

RTDH also sends `jf-team-id` when downloading uploaded Jotform files/images from
submission answers (for example ID uploads) before forwarding them to provider
integrations.

5. Click **Save Settings**.

Notes for tenant admins:

- The Team Workspace ID is displayed in plain text in the Forms tab so it can be
  quickly confirmed.
- The Team Workspace ID has its own **Edit/Save** action and can be updated
  independently from API URL and API Key.
- The Forms tab separates **API Credentials**, **Team Workspace ID**, and
  **Default Webhook URL** into distinct sections so each setting group can be
  managed without conflicting actions.

The settings are persisted in the `tenant_integrations` table with
`integration_key = 'jotform'`.

### 2. Generate the Telegra patient questionnaire form

For Telegra patient questionnaires, use the provider platform bridge generation
endpoint instead of manually creating the form in Jotform:

```text
POST /functions/v1/provider-platform-bridge/jotform-patient-questionnaire
```

Request body:

```json
{
  "tenantIntegrationId": "<enabled Telegra tenant_integrations.id>",
  "title": "Telegra Patient Questionnaire",
  "webhookUrl": "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev",
  "saveAsPatientQuestionnaire": true
}
```

The endpoint:

1. Authenticates the caller with the incoming Supabase JWT.
2. Verifies access to the tenant that owns the Telegra integration.
3. Reads the enabled tenant `jotform` integration for `api_url`, `api_key`, and
   optional `team_workspace_id`.
4. Creates the Jotform form using the default Telegra
   `patient_questionnaire_definition` questions.
5. Adds the required generated hidden fields: `provider_key`,
   `patient_platform_order_id`, and `questionnaire_type`.
6. Adds Jotform page breaks so each visible patient questionnaire question is
   rendered on its own page.
7. Enables the generated form's Save and Continue Later behavior.
8. Calls the Jotform webhook API and points the form to the supplied
   `webhookUrl`, the tenant Jotform `settings.default_webhook_url`, or the
   built-in RTDH dev receiver fallback.
9. Saves the generated form id into the Telegra provider integration setting
   `settings.patient_questionnaire_form_id` when `saveAsPatientQuestionnaire` is
   not `false`. The Jotform integration continues to store only shared API
   credentials and workspace settings.

The generated Telegra patient form sets:

| Field                       | Default                                                |
| --------------------------- | ------------------------------------------------------ |
| `provider_key`              | `telegramd`                                            |
| `questionnaire_type`        | `patient_questionnaire`                                |
| `patient_platform_order_id` | Supplied later by the backend in the embedded form URL |

Medical questionnaire generation is intentionally not implemented in this phase.
The Jotform creation helpers are shared so the future medical generator can
reuse the same API URL normalization, team workspace header, hidden-field
contract, and webhook attachment path.

The **Settings → Questionnaires → Patient** tab exposes, per provider, an explicit
**Direct | Jotform** toggle (persisted to
`tenant_integrations.settings.patient_questionnaire_mode`). In **Jotform** mode it
shows the **Patient Questionnaire Jotform ID** field; in **Direct** mode it shows the
**Patient Questionnaire Definition** JSON editor. (Storage keys
`patient_questionnaire_form_id` / `patient_questionnaire_definition` are unchanged.)
The **provider-platform-bridge honors this flag**: `direct` always serves the native
provider questionnaire (the form id is ignored even if set); `jotform` serves the
Jotform embed; when the flag is **unset**, the bridge infers the path from whether a
valid form id exists (pre-flag behavior). The medical questionnaire works the same
way via `product_provider_platforms.integration_mode`.
The field is labeled **Patient Questionnaire Jotform ID**;
when it is blank, the provider falls back to the legacy patient-questionnaire
implementation. Admins edit or clear this value from the provider card's
**Update Settings** flow; clearing the input and saving removes the configured
Jotform ID. The field reuses the same hidden-field tooltip shown in the product
**Jotform Medical Questionnaires** section. For this provider-level field, the
tooltip displays `medical_questionnaire` as the fixed `questionnaire_type` value
for the linked form. Jotform generation is currently implemented for the Telegra
patient questionnaire; other providers can still be configured manually with the
same `settings.patient_questionnaire_form_id` storage model. For each configured
patient form, the UI shows a compact webhook status tooltip on the field title,
an eye button to preview the public form, a pencil button to edit the form in
Jotform, and a wrench button when the form exists but is missing the current
Default Webhook URL. It does not expose the full list of webhooks configured on
the Jotform.

### 3. Link Jotform forms to a product–provider platform (Product level)

1. Navigate to **Product Management → Provider Platforms** for the target
   product.
2. Enable the desired provider platform if it is not already enabled.
3. In the **Jotform Medical Questionnaires** section, enter the form IDs from
   Jotform:
   - **Jotform New Order Questionnaire ID** for new users.
   - **Jotform Renewal Questionnaire ID** for renewals.
4. Click **Save Settings**.

The IDs are stored in the `jotform_new_order_questionnaire_id` and
`jotform_renewall_questionnaire_id` columns of the `product_provider_platforms`
table.

> **Note:** If the tenant-level Jotform integration is not configured, an amber
> warning icon appears next to the Jotform section label indicating that setup
> is missing.

### 4. Prepare a manually created Jotform form

1. Open the form in the Jotform form builder.
2. At the widget level in the Jotform form builder, add the required hidden
   configuration fields:
   - `patient_platform_order_id` as **Short Text**.
   - `provider_key` as a **Single Choice Menu**.
   - `questionnaire_type` as a **Single Choice Menu**.
3. Do not set fixed values for `patient_platform_order_id` or `provider_key` in
   Jotform. Both fields must be available on the form, and the Patient Platform
   backend appends their values as query-string parameters on the Jotform URL
   returned to the frontend.
4. Configure `provider_key` with the fixed options `md_integrations`,
   `telegramd`, and `zito_care`.
5. Set the default value for `questionnaire_type` directly on the Jotform form:
   `patient_questionnaire` or `medical_questionnaire`.
6. In the field properties make sure **PHI is disabled** for these fields.
7. Open **Settings → Integrations → Webhooks** in Jotform.
8. Add the environment-specific RTDH JotForm Consumer (RTDH webhook receiver
   URL). For example, in dev:

   ```text
   https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev
   ```

9. For staging or production forms, use the corresponding RTDH JotForm Consumer
   URL configured for that environment.
10. Keep **Send PHI to Webhooks** disabled unless RTDH explicitly requires PHI
    in webhook payloads for that form.
11. Save and publish the form.

---

## How It Works

```
┌──────────────────┐   embedded form   ┌─────────────┐
│ Patient Platform │───────────────────▶│   Jotform   │
│ frontend         │                    │             │
└──────────────────┘                    └──────┬──────┘
                                                │
                                                │ submission webhook
                                                ▼
┌──────────────────┐   processed event  ┌─────────────┐
│ Patient Platform │◀───────────────────│ RTDH        │
│ backend          │                    │ JotForm     │
└────────┬─────────┘                    │ Consumer    │
         │                              └─────────────┘
         │ provider request
         ▼
┌──────────────────┐
│ Provider workflow│
│ e.g. MDI         │
└──────────────────┘
```

1. The Patient Platform backend selects the configured Jotform questionnaire ID
   for the order. Patient questionnaire form IDs are read from the selected
   provider integration's `settings.patient_questionnaire_form_id`; medical
   questionnaire form IDs remain product/provider assignment settings. The
   returned Jotform URL includes `patient_platform_order_id`, `provider_key`,
   and, for patient questionnaire forms,
   `questionnaire_type=patient_questionnaire` in the query string.
2. The Patient Platform frontend renders the embedded Jotform form. The patient
   submits the questionnaire directly to Jotform.
3. Jotform sends a submission webhook to the environment-specific RTDH JotForm
   Consumer.
4. RTDH reads the tenant's Jotform credentials (`api_url`, `api_key`,
   `team_workspace_id`) from `tenant_integrations` and uses the Jotform API to
   retrieve the submission details when needed. The API URL is normalized so
   site roots use `/API/form/{formId}/...`, while API hosts such as
   `api.jotform.com` use `/form/{formId}/...`.
5. For file/image answers, RTDH downloads the file from Jotform using the same
   tenant credentials and `jf-team-id` header.
6. RTDH matches the submission using the `patient_platform_order_id` hidden
   field value and forwards the processed event to the Patient Platform backend.
7. The Patient Platform backend finalizes the questionnaire processing, sends
   the mapped data to the selected provider workflow, and advances the order
   through its lifecycle. Telegra patient questionnaire submissions are mapped
   into the same `patientData` shape used by the native patient questionnaire
   endpoint before being transformed into the Telegra patient payload.

For Telegra patient questionnaires, Jotform-generated answer keys are normalized
before the provider update is sent:

| Jotform answer shape                                                                               | Normalized patient data                                                 |
| -------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `weight_value` plus `weight_unit`                                                                  | `weight_lbs`, converted to pounds when the submitted unit is kilograms  |
| `height_value` plus `height_unit`                                                                  | `height_ft`, converted to inches when the submitted unit is centimeters |
| `date_of_birth` or `birth_date`                                                                    | `birth_date`                                                            |
| Medication widget labels such as `Medication name`, `Dosage`, `Frequency`, and `Condition treated` | `medication[]` entries in the same shape as the native questionnaire    |

After normalization, both native and Jotform Telegra patient questionnaires use
the same patient-profile transformation, Telegra patient update, order status
advance, and order lifecycle trigger.

---

## Database Schema

### `platform_integrations` (seed row)

| Column              | Value                                                                                               |
| ------------------- | --------------------------------------------------------------------------------------------------- |
| `key`               | `jotform`                                                                                           |
| `name`              | `Jotform`                                                                                           |
| `category`          | `forms`                                                                                             |
| `required_settings` | `["api_url", "api_key", "team_workspace_id"]`                                                       |
| `description`       | Forms integration used by RTDH to access submitted forms and route form data to provider workflows. |

### `tenant_integrations` (per tenant)

| Column            | Type    | Description                                                                                        |
| ----------------- | ------- | -------------------------------------------------------------------------------------------------- |
| `tenant_id`       | UUID    | Owning tenant                                                                                      |
| `integration_key` | TEXT    | `'jotform'`                                                                                        |
| `is_enabled`      | BOOLEAN | Whether the integration is active                                                                  |
| `settings`        | JSONB   | `{ "api_url": "...", "api_key": "...", "team_workspace_id": "...", "default_webhook_url": "..." }` |

Patient questionnaire form IDs are stored per provider integration in the same
table, for example the Telegra row with `integration_key = 'telegramd'` stores
`settings.patient_questionnaire_form_id`. This keeps provider-specific patient
forms independent while sharing one tenant-level Jotform API configuration.

### `product_provider_platforms` (per assignment)

| Column                               | Type | Description                                                 |
| ------------------------------------ | ---- | ----------------------------------------------------------- |
| `jotform_new_order_questionnaire_id` | TEXT | Jotform form ID used by RTDH for new-user orders (max 128). |
| `jotform_renewall_questionnaire_id`  | TEXT | Jotform form ID used by RTDH for renewal orders (max 128).  |

If a previous local migration created `jotform_questionnaire_id`,
`jotform_first_time_questionnaire_id`, or `jotform_renewal_questionnaire_id`,
those values are copied into the new column names without overwriting existing
data.

### Migration Idempotency

The Jotform questionnaire migration is safe to run more than once:

- `ADD COLUMN IF NOT EXISTS` creates `jotform_new_order_questionnaire_id` and
  `jotform_renewall_questionnaire_id` only when they do not already exist.
- Backfills check whether legacy columns exist before reading from them.
- Backfills only update rows where the target questionnaire field is currently
  `NULL`, so existing admin-saved values are preserved.
- Column comments can be reapplied safely.

---

## Validation Rules

| Field                              | Rule                                   |
| ---------------------------------- | -------------------------------------- |
| API URL                            | Required, valid URL format             |
| API Key                            | Required, sensitive (masked)           |
| Team Workspace ID                  | Required for Jotform submission access |
| Provider patient questionnaire ID  | Optional, max 128 characters           |
| Jotform New Order Questionnaire ID | Optional, max 128 characters           |
| Jotform Renewal Questionnaire ID   | Optional, max 128 characters           |

---

## Troubleshooting

| Symptom                                                                     | Cause / Fix                                                                                                                                                                                                                                            |
| --------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Amber warning icon next to Jotform Medical Questionnaires                   | Jotform API Key or API URL not configured — go to **Settings → Questionnaires → Connection**.                                                                                                                                                          |
| Submission lookup returns unauthorized or cannot access submission endpoint | Confirm the tenant Jotform Team Workspace ID is configured in **Settings → Questionnaires → Connection** and matches the workspace that owns the form.                                                                                                  |
| Form file/image upload fails during provider processing                     | Check the order timeline/history: the bridge now records a technical note in `order_status_history` with a failure code and `requestId` for Jotform file processing errors.                                                                            |
| RTDH cannot match a submission to an order                                  | The form is missing the `patient_platform_order_id` hidden field or PHI is enabled on that field.                                                                                                                                                      |
| RTDH/provider processing has insufficient form context                      | The form is missing `provider_key` or `questionnaire_type`, `provider_key` was not populated from the Patient Platform backend's Jotform URL query string, or `questionnaire_type` is not fixed to `patient_questionnaire` or `medical_questionnaire`. |
| Submission never reaches RTDH                                               | The Jotform form is missing the RTDH JotForm Consumer (RTDH webhook receiver URL) for the environment where the form is used. Dev example: `https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver-dev`.                |
| "Jotform questionnaire ID must be 128 characters or less"                   | The entered form ID exceeds the maximum length.                                                                                                                                                                                                        |
| Settings not saving                                                         | The provider platform must be enabled before saving provider-specific settings.                                                                                                                                                                        |
