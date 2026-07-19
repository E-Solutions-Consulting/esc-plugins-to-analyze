# Welcome to your Lovable project

## Project info

**URL**: https://lovable.dev/projects/REPLACE_WITH_PROJECT_ID

## Recent Data Model Changes (February 2026)

Subscription lifecycle and payment-provider data ownership were refactored:

- Added `subscriptions` as the lifecycle entity and linked each order through `orders.subscription_id`.
- Moved lifecycle renewal reads to `subscriptions.current_period_end_at` (exposed in APIs as `renewal_at` via `orders.subscription_id`) and lifecycle expiration reads to `subscriptions.expires_at` (exposed as `expires_at` in plan responses).
- Added provider-agnostic payment tables:
  - `subscription_payment_provider_links` for provider lifecycle IDs
  - `order_payment_provider_transactions` for provider transaction IDs/status
- Moved patient order endpoints from `patient-api` to `plan-api` (`/functions/v1/plan-api`).

Applied migrations:

- `supabase/migrations/20260219150000_create_subscriptions_entity.sql`
- `supabase/migrations/20260219151000_backfill_subscriptions_from_orders.sql`
- `supabase/migrations/20260219152000_extract_stripe_fields_to_payment_entities.sql`

Documentation updated:

- `docs/PatientAPI.md`
- `docs/PlanAPI.md`
- `docs/OrderLifecycleAPI.md`

## Tenant Terms and Conditions

Tenant admins manage versioned tenant-specific terms and conditions in
Tenant Admin > Settings > Terms & Conditions. Each tenant has its own live
version.

Draft versions can be edited or deleted until first publication. Once a version
has been made live, its legal content is immutable and only the live flag can
move to another published version.

Patient-facing app integration:

- Fetch the current live version from `GET /functions/v1/patient-api/terms-and-conditions/latest`
- Submit acceptance with `POST /functions/v1/patient-api/terms-and-conditions/accept`
- Check acceptance status with `GET /functions/v1/patient-api/terms-and-conditions/acceptance-status`
- During patient signup, send `tenant_terms_version_id` in `POST /functions/v1/patient-api/auth/signup`
- Existing clients may continue sending `platform_terms_version_id` while migrating

Acceptance persistence: 

- Product-level acceptance history is stored in `patient_terms_acceptances`
- Tenant terms acceptance history is stored in `patient_platform_terms_acceptances`
- Tenant admins can review accepted tenant terms from Patient Details > Tenant Terms

## Tenant Privacy Policy

Tenant admins manage versioned tenant-specific privacy policies in
Tenant Admin > Settings > Privacy Policy. Each tenant has its own live version.

Privacy policy versions use the same draft, publish, live-version, and immutable
published-content workflow as tenant terms and conditions.

Patient-facing app integration:

- Fetch the current live version from `GET /functions/v1/patient-api/privacy-policy/latest`
- Submit acceptance with `POST /functions/v1/patient-api/privacy-policy/accept`
- Check acceptance status with `GET /functions/v1/patient-api/privacy-policy/acceptance-status`
- During patient signup, clients must send `privacy_policy_version_id` in `POST /functions/v1/patient-api/auth/signup` to record acceptance immediately
- `product_id` is optional during signup; when supplied, it is only validated as product context

Acceptance persistence:

- Tenant privacy policy acceptance history is stored in `patient_privacy_policy_acceptances`
- Tenant admins can review accepted privacy policy versions from Patient Details > Privacy Policy

## How can I edit this code?

There are several ways of editing your application.

**Use Lovable**

Simply visit the [Lovable Project](https://lovable.dev/projects/REPLACE_WITH_PROJECT_ID) and start prompting.

Changes made via Lovable will be committed automatically to this repo.

**Use your preferred IDE**

If you want to work locally using your own IDE, you can clone this repo and push changes. Pushed changes will also be reflected in Lovable.

The only requirement is having Node.js & npm installed - [install with nvm](https://github.com/nvm-sh/nvm#installing-and-updating)

Follow these steps:

```sh
# Step 1: Clone the repository using the project's Git URL.
git clone <YOUR_GIT_URL>

# Step 2: Navigate to the project directory.
cd <YOUR_PROJECT_NAME>

# Step 3: Install the necessary dependencies.
npm i

# Step 4: Start the development server with auto-reloading and an instant preview.
npm run dev
```

**Setup your first user:**

Use the signup feature on Admin UI.
After registering the user, go to Supabase and get the UUID from `auth.users.id` for the new user.

Run the following query in Supabase to add the user to the `platform_superadmin` role:

```code
INSERT INTO public.user_roles (user_id, role)
SELECT id, 'platform_superadmin'
FROM public.admin_users
WHERE auth_user_id = '[ADD-NEW-AUTH-USER-UUID-HERE]'
ON CONFLICT (user_id, role) DO NOTHING;
```

`public.user_roles.user_id` references `public.admin_users.id`, not `auth.users.id`. If the insert affects `0` rows, verify that the signup created a matching row in `public.admin_users`.

**Edit a file directly in GitHub**

- Navigate to the desired file(s).
- Click the "Edit" button (pencil icon) at the top right of the file view.
- Make your changes and commit the changes.

**Use GitHub Codespaces**

- Navigate to the main page of your repository.
- Click on the "Code" button (green button) near the top right.
- Select the "Codespaces" tab.
- Click on "New codespace" to launch a new Codespace environment.
- Edit files directly within the Codespace and commit and push your changes once you're done.

## What technologies are used for this project?

This project is built with:

- Vite
- TypeScript
- React
- shadcn-ui
- Tailwind CSS

## How can I deploy this project?

Simply open [Lovable](https://lovable.dev/projects/REPLACE_WITH_PROJECT_ID) and click on Share -> Publish.

For Vercel deployments, this repo includes a root `vercel.json` SPA rewrite so client-side routes resolve to `index.html`.

## Communications Automations

A no-code journey builder (Attentive / Customer.io–style) that drives **email + SMS**
off first-party data, with native **n8n** hand-off. See
[docs/CommunicationsAutomations.md](docs/CommunicationsAutomations.md) for the full
architecture and [docs/CommunicationsAutomations-n8n-config.md](docs/CommunicationsAutomations-n8n-config.md)
for the n8n/secrets configuration and go-live checklist.

- **Where:** tenant **Automations** workspace (`/tenant-admin/automations`) + builder;
  **Templates** library; SMS provider config under Settings → Communications → SMS;
  n8n connection managed in **platform admin** (Integrations & Data → n8n).
- **Triggers:** analytics events, subscription lifecycle, order status, and
  relative-time (e.g. *3 days before renewal*, *7 days after purchase*).
- **Steps:** Email (Resend), SMS (Twilio), delays, wait-until, branch / multi-split,
  **n8n** hand-off, exit — personalised with `{{patient.first_name}}` etc.
- **Engine (Supabase edge functions):** `comms-automation-admin` (CRUD + test-send),
  `comms-event-dispatcher` (resolves context, enrols), `comms-execute-node`
  (state machine), `comms-scheduler` (delays + relative-time + event sweep),
  `comms-n8n-proxy` (per-tenant project/folder provisioning via GCP Secret Manager),
  `comms-resend-webhook` (deliverability).
- **n8n model:** one project per tenant, one folder per automation; degrades to
  webhook-mode on Community Edition until an Enterprise license is activated.

> Feature branch `elianomarques/comms-automations`; migrations + functions are not
> deployed until explicitly migrated.

## LifeFile Webhook Integration

The `lifefile-webhook` Supabase edge function receives pharmacy status updates from LifeFile and advances internal order state.

### Status mapping

| LifeFile `rxStatus`     | Internal `status_key`                                |
| ----------------------- | ---------------------------------------------------- |
| `Rx Scheduled`          | _(log-only, no change)_                              |
| `Ready for Fulfillment` | `pharmacy_approved`                                  |
| `Fulfillment`           | `fulfillment_in_progress`                            |
| `Final Verification`    | `final_pharmacy_verification`                        |
| `Rx Shipping pickup`    | `in_transit` (sets `tracking_number` + `shipped_at`) |

### Testing with Postman

Import `docs/lifefile-webhook.postman_collection.json` into Postman. Set these collection variables:

| Variable                    | Example                                    |
| --------------------------- | ------------------------------------------ |
| `SUPABASE_URL`              | `https://sunzxjnbgtknqeivljtd.supabase.co` |
| `LIFEFILE_WEBHOOK_USERNAME` | `brello-backend`                           |
| `LIFEFILE_WEBHOOK_PASSWORD` | _(see Supabase secrets)_                   |
| `ORDER_REFERENCE_ID`        | `trn::YOUR-ORDER-UUID`                     |
| `PATIENT_EMAIL`             | `rosalia@example.com`                      |

The collection covers all `rxStatus` paths, idempotency replay, batch format, error case, and CORS preflight.

### Supabase deploy commands

Deploy a **single function** to a specific environment (pass `--fn=<function-name>`):

```sh
bun run supabase:deploy:dev --fn=lifefile-webhook
bun run supabase:deploy:staging --fn=lifefile-webhook
bun run supabase:deploy:prod --fn=lifefile-webhook
```

Deploy a single function to a **custom project ref**:

```sh
bun run supabase:deploy --fn=order-lifecycle --env=<PROJECT_REF>
```

Deploy **all functions** at once:

```sh
bun run supabase:deploy:all:dev
bun run supabase:deploy:all:staging
bun run supabase:deploy:all:prod
```

### CORS configuration

CORS for Supabase Edge Functions is centralized in `supabase/functions/_shared/cors.ts`.

The helper supports `*` globs. The current allowed-origin example is:

```env
CORS_ALLOWED_ORIGINS=",https://patient-platform-admin.vercel.app"
```

Current prod settings:
```env
CORS_ALLOWED_ORIGINS="http://localhost:*,http://127.0.0.1:*,https://*.lovableproject.com,https://*.lovable.app,https://*.temp-alliahealth.co,https://*.vercel.app,https://*.alliahealthgroup.com,https://*.joinbrello.com"
```

Important: deployed Supabase Edge Functions do not read the app root `.env.*` files. They read `Deno.env`, so `CORS_ALLOWED_ORIGINS` must be set as a Supabase secret in each project ref before deploying:

```sh
supabase secrets set CORS_ALLOWED_ORIGINS="http://localhost:*,http://127.0.0.1:*,https://*.lovableproject.com,https://*.lovable.app,https://*.temp-alliahealth.co,https://*.vercel.app,https://*.alliahealthgroup.com" --project-ref <PROJECT_REF>
```

Set it separately for dev, staging, and production project refs.

For Supabase preview branches created through Branching, this repo is now wired to read `CORS_ALLOWED_ORIGINS` from `[edge_runtime.secrets]` in [supabase/config.toml](/Users/joaosobrinho/Projects/patient-platform-admin/supabase/config.toml). To make preview instances receive the value automatically, use Supabase's `dotenvx` preview workflow once:

```sh
bun run supabase:preview-secrets:init-cors
bun run supabase:preview-secrets:push-keys
```

This creates or updates `supabase/.env.preview` with the CORS value and pushes `supabase/.env.keys` as branch secrets. Commit `supabase/.env.preview`, but do not commit `supabase/.env.keys`.

If you prefer to do it manually, copy [supabase/.env.preview.example](/Users/joaosobrinho/Projects/patient-platform-admin/supabase/.env.preview.example) to `supabase/.env.preview`, then encrypt/manage it with `dotenvx` per the Supabase Branching docs.

If a request includes an `Origin` header that is not matched by `CORS_ALLOWED_ORIGINS`, the function will not return `Access-Control-Allow-Origin`.

### New patient app domains

Whenever a new patient app domain is added, update Edge Function CORS
configuration for every affected project ref.

Required tasks:

1. Add the new patient app origin to the `CORS_ALLOWED_ORIGINS` Supabase secret.
   Edge Functions read CORS from `Deno.env`, so new domains are not picked up
   automatically unless the secret is updated.
2. If preview/branch environments are used, update `supabase/.env.preview` and
   push the preview secrets again so preview branches also allow the new domain.

Example CORS update:

```sh
supabase secrets set CORS_ALLOWED_ORIGINS="https://*.temp-alliahealth.co,https://patientplatformui.vercel.app,https://patient-platform-admin.vercel.app,https://new-patient-app.example.com" --project-ref <PROJECT_REF>
```

Notes:

- Apply the `CORS_ALLOWED_ORIGINS` secret update separately for each project
  ref.
- For `/functions/v1/patient-api/auth/forgot-password`, the `redirect_url`
  passed by the patient app should point to that patient app's reset-password
  page. The Edge Function now emails a direct patient-app link with a
  `reset_token` query parameter and does not use Supabase Auth recovery
  redirects.
- The admin UI forgot-password page also uses Supabase Auth redirects on
  `window.location.origin`, so new admin domains must be added to Supabase Auth
  Redirect URLs as well.

Run **database migrations**:

```sh
bun run supabase:migrate:dev
bun run supabase:migrate:staging
bun run supabase:migrate:prod
```

Supabase Data API grants for public tables are explicit in
`supabase/migrations/20260514120000_make_data_api_grants_explicit.sql`. When
adding a migration that creates a new `public` table, include the table-specific
`GRANT` statements in that migration as well as RLS policies.

### Auth

The webhook uses HTTP Basic Auth. Credentials must be set as Supabase secrets by a project Owner:

- `LIFEFILE_WEBHOOK_USERNAME` = `brello-backend`
- `LIFEFILE_WEBHOOK_PASSWORD` = _(contact team lead)_

> **Dev note:** Auth is bypassed in the dev environment (`_skipAuthForDev = true` in `index.ts`). Re-enable before deploying to staging/prod.

---

## Can I connect a custom domain to my Lovable project?

Yes, you can!

To connect a domain, navigate to Project > Settings > Domains and click Connect Domain.

Read more here: [Setting up a custom domain](https://docs.lovable.dev/features/custom-domain#custom-domain)
