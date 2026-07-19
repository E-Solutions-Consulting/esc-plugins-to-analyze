# Provider RTDH Secrets

Patient Platform is the source of truth for tenant/provider integration secrets
that RTDH needs at runtime. PP stores the integration settings in Supabase as
before, and also sends secret changes to RTDH. RTDH verifies the signed request
and creates or rotates the corresponding GCP Secret Manager secrets.

Write-only: secret values are never read back into the UI or returned by RTDH.

## Managed Secrets

| Provider                | Patient Platform key | RTDH secret id                                 |
| ----------------------- | -------------------- | ---------------------------------------------- |
| Telegra (`telegramd`)   | `webhook_secret`     | `telegramd-webhook-secret--<tenantSlug>`       |
| MD Integrations (`mdi`) | `webhook_secret`     | `md-integrations-webhook-secret--<tenantSlug>` |
| Stripe (`stripe`)       | `signing_secret`     | `stripe-signing-secret--<tenantSlug>`          |
| EasyPost (`easypost`)   | `webhook_secret`     | `easypost-webhook-secret--<tenantSlug>`        |
| Intercom (`intercom`)   | `webhook_secret`     | `intercom-webhook-secret--<tenantSlug>`        |
| Jotform (`jotform`)     | `webhook_secret`     | `jotform-webhook-secret--<tenantSlug>`         |
| LifeFile (`lifefile`)   | `webhook_username`   | `lifefile-webhook-username--<tenantSlug>`      |
| LifeFile (`lifefile`)   | `webhook_password`   | `lifefile-webhook-password--<tenantSlug>`      |

## Request Contract

Patient Platform sends either a single-secret or multi-secret payload:

```json
{
  "tenant": "allia",
  "provider": "mdi",
  "encoding": "base64",
  "key": "webhook_secret",
  "value": "YWN0dWFsLXNlY3JldC12YWx1ZQ=="
}
```

```json
{
  "tenant": "allia",
  "provider": "lifefile",
  "encoding": "base64",
  "secrets": {
    "webhook_username": "dXNlcm5hbWU=",
    "webhook_password": "cGFzc3dvcmQ="
  }
}
```

Secret values are standard base64 encoded UTF-8 strings. Optional `context` is
included where needed.

Requests are signed with:

```text
x-patientplatform-signature: sha256=<hex_digest>
```

The signature is `HMAC-SHA256(rawJsonBody, shared_secret)`. PP signs the exact
raw JSON string that it sends in the request body.

## Patient Platform Pieces

- UI provider credentials: `src/components/features/TenantIntegrationSettings.tsx`
- UI payment providers: `src/components/features/TenantPaymentProvidersManager.tsx`
- UI provider validation secret card: `src/pages/tenant-admin/settings-v2/ProvidersReal.tsx`
- Edge function broker: `supabase/functions/set-provider-rtdh-secret`
- Shared RTDH client: `supabase/functions/_shared/rtdh-secret-manager-interface.ts`

The Edge Function authenticates the PP admin, checks tenant access, resolves the
tenant slug from Supabase, then sends only the secret-change request to RTDH.
Patient Platform does not write to GCP Secret Manager directly.

## Required Config

Supabase Edge Function secrets:

- `RTDH_BASE_URL`
- `RTDH_SECRET_TENANT` — optional fallback tenant namespace.

Platform RTDH settings:

- `RTDH_BASE_URL`
- `patient_platform_webhook_secret`
- `RTDH_SECRET_MANAGER_RECEIVER_SECRET`

`patient_platform_webhook_secret` signs normal PP -> RTDH events and is stored
only in `platform_settings.rtdh_config`.
`RTDH_SECRET_MANAGER_RECEIVER_SECRET` signs PP -> RTDH Secret Manager requests.
Both are configurable from the platform RTDH settings UI. The values must match
the corresponding webhook secrets configured in RTDH.

The secret manager sender posts to:

```ts
`${RTDH_BASE_URL}/secret-manager-receiver`;
```

and signs with `RTDH_SECRET_MANAGER_RECEIVER_SECRET`.

Normal PP to RTDH event dispatch uses the fixed RTDH receiver Cloud Function
without a tenant query parameter:

```ts
`${RTDH_BASE_URL}/patient-platform-webhook-receiver`;
```

with event-specific subpaths, for example:

```ts
`${RTDH_BASE_URL}/patient-platform-webhook-receiver/create-order`
`${RTDH_BASE_URL}/patient-platform-webhook-receiver/provider-platform/new-order`
`${RTDH_BASE_URL}/patient-platform-webhook-receiver/order_updated`;
```

and signs with `patient_platform_webhook_secret`. The body also keeps
`internal_tenant_id` for downstream master-object linking.

Legacy database keys `rtdh_config.patient_platform_receiver_secret`,
`rtdh_config.consumer_secret`, and `rtdh_config.api_url` values are still
accepted as fallback during rollout. No environment variable is used as a
fallback for the Patient Platform webhook secret.

## Notes

- Until the RTDH base URL and signing secret are set, the UI save returns a clear
  "not configured" (`501`) error.
- RTDH owns GCP Secret Manager permissions through the Cloud Function runtime
  service account.
- Telegra/MDI API credentials remain normal Nexus integration settings. PP only
  sends their RTDH webhook validation secret through this secret-manager flow.
