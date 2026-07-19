# Patient UI API Documentation

> **Version:** 1.6.5\
> **Last Updated:** July 8, 2026\
> **Audience:** Patient UI Developers

This document describes the API endpoints available for the Patient UI
application to integrate with the Allia Care platform. The Patient UI is
responsible for patient registration, order placement, and order tracking.

Related sequence diagrams:

- [Patient Sign Up Flow](./SequenceDiagrams.md#patient-sign-up-flow)
- [Order Flow Sequence](./SequenceDiagrams.md#order-flow-sequence)

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base Configuration](#base-configuration)
4. [Endpoints](#endpoints)
   - [Patient Registration](#patient-registration)
   - [Migration Status](#migration-status)
   - [Patient Profile](#patient-profile)
   - [Notifications](#notifications)
   - [Reminders](#reminders)
   - [Messaging (Chat Threads)](#messaging-chat-threads)
   - [Catalog (Products & Protocols)](#catalog)
   - [Questionnaires](#questionnaires)
   - [Subscriptions](#subscriptions)
5. [Data Models](#data-models)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Security Considerations](#security-considerations)

---

## Overview

The Allia Care platform is a multi-tenant healthcare SaaS system. Each Patient
UI deployment is scoped to a specific **tenant** (healthcare organization). All
API requests must include tenant context.

### Key Concepts

| Concept           | Description                                                |
| ----------------- | ---------------------------------------------------------- |
| **Tenant**        | A healthcare organization using the platform               |
| **Patient**       | End user who registers, orders, and tracks medications     |
| **Product**       | A purchasable item (may contain one or more medications)   |
| **Protocol**      | A bundled treatment plan containing multiple products      |
| **Questionnaire** | Health intake forms that must be completed before ordering |
| **Subscription**  | Lifecycle record for recurring product orders              |

### Lifecycle and Payment Data Ownership

- Lifecycle state lives in `subscriptions` (`status`, `started_at`,
  `current_period_end_at`, `expires_at`, `paused_at`, `cancelled_at`).
- Each renewal/fulfillment record stays in `orders` and links to lifecycle via
  `orders.subscription_id`.
- Provider lifecycle IDs live in `subscription_payment_provider_links`.
- Provider transaction IDs/status live in `order_payment_provider_transactions`.
- Patient order response field `renewal_at` is derived from
  `subscriptions.current_period_end_at` through `orders.subscription_id` (no
  legacy fallback reads).

---

## Authentication

The Patient API provides built-in authentication endpoints for patient
registration, sign-in, and password management. All auth endpoints are
tenant-scoped.

**Supported sign-in methods** (all resolve to the same tenant session tokens):

| Method                   | Endpoint(s)                                     | Notes                                         |
| ------------------------ | ----------------------------------------------- | --------------------------------------------- |
| Email + password         | `POST /auth/signin`                             | Classic credentials                           |
| Passwordless (email OTP) | `POST /auth/otp/request` + `/auth/otp/verify`   | Single-use, 10-min, rate-limited 6-digit code |
| Google (and later Apple) | `POST /auth/oauth/resolve`                      | Browser OAuth → resolve to tenant session     |
| Passkey / biometrics     | `POST /auth/oauth/resolve` (UI + Supabase Auth) | WebAuthn (Face ID / Touch ID / fingerprint)   |

Password is **optional** under the PP-566 sign-up flow (Option 2): a patient can
finish checkout and later sign in via OTP, Google, or a passkey without ever
setting one. See
[Patient Sign Up Flow](./SequenceDiagrams.md#patient-sign-up-flow) and
[Auth Methods Setup](./AuthMethodsSetup.md).

### Base URL

```
VITE_SUPABASE_URL/functions/v1/patient-api
```

> Note: Order and checkout endpoints are served from
> `VITE_SUPABASE_URL/functions/v1/plan-api`.
>
> Messaging endpoints are served from
> `VITE_SUPABASE_URL/functions/v1/messenger-api`.

### Required Headers

| Header          | Description                             | Required                                                   |
| --------------- | --------------------------------------- | ---------------------------------------------------------- |
| `x-tenant-slug` | Tenant identifier (e.g., `acme-health`) | Yes (for most endpoints)                                   |
| `apikey`        | Supabase anon key                       | Yes, except `GET/POST /migration-status`                   |
| `Authorization` | Bearer token                            | For authenticated endpoints; see `/migration-status` below |
| `Content-Type`  | `application/json`                      | For POST/PATCH requests                                    |

---

### Sign Up (Patient Registration)

Creates a new patient account and links it to the tenant.

See [Patient Sign Up Flow](./SequenceDiagrams.md#patient-sign-up-flow) for the
end-to-end sequence across Patient UI, `tenant-info`, `patient-api`, Supabase
Auth, generated password email delivery, tenant terms acceptance, and privacy
policy acceptance.

```http
POST /functions/v1/patient-api/auth/signup
Content-Type: application/json
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "email": "patient@example.com",
  "name": "John Doe",
  "phone": "+1-555-123-4567",
  "shipping_state": "CA",
  "tenant_terms_version_id": "tenant-terms-version-uuid-1",
  "privacy_policy_version_id": "privacy-policy-version-uuid-1",
  "subscribe_to_email_and_sms_marketing": true
}
```

**Request Body:**

| Field                                  | Type    | Required | Description                                                                                                       |
| -------------------------------------- | ------- | -------- | ----------------------------------------------------------------------------------------------------------------- |
| `email`                                | string  | Yes      | Patient email address                                                                                             |
| `name`                                 | string  | Yes      | Patient full name                                                                                                 |
| `phone`                                | string  | Yes      | Phone number                                                                                                      |
| `shipping_state`                       | string  | Yes      | Two-letter US shipping state code (e.g. `CA`)                                                                     |
| `product_id`                           | string  | No       | Optional product context; when sent, it must reference an enabled product for the tenant                          |
| `tenant_terms_version_id`              | string  | Yes      | Live tenant terms version id returned by `GET /terms-and-conditions/latest`                                       |
| `privacy_policy_version_id`            | string  | Yes      | Live privacy policy version id returned by `GET /privacy-policy/latest`; signup records privacy policy acceptance |
| `subscribe_to_email_and_sms_marketing` | boolean | No       | When `true`, opts the patient into both email and SMS marketing. Defaults to `false` when omitted                  |

Legal acceptance behavior:

- `accepted_at` is set to the signup request timestamp.
- A new row is inserted into `patient_platform_terms_acceptances` for the
  patient + tenant.
- `tenant_terms_version_id` must match the current live tenant terms version.
- `platform_terms_version_id`, `platform_terms_version`, and `accepted_at` are
  stored from the live tenant terms at signup time.
- Client-provided terms content is not required for signup.
- Existing clients may send `platform_terms_version_id`; it is accepted as a
  legacy alias for `tenant_terms_version_id`.
- A new row is inserted into `patient_privacy_policy_acceptances` for the
  patient + tenant.
- `privacy_policy_version_id` must match the current live privacy policy
  version.
- Product terms acceptance is recorded separately through
  `POST /products/:id/terms-acceptance`.

If the supplied `tenant_terms_version_id` is stale, the API returns:

| Code | Error                        | Description                                             |
| ---- | ---------------------------- | ------------------------------------------------------- |
| 409  | `STALE_TENANT_TERMS_VERSION` | The submitted tenant terms version is no longer current |

If no live tenant terms version exists, the API returns:

| Code | Error                         | Description                                |
| ---- | ----------------------------- | ------------------------------------------ |
| 503  | `TENANT_TERMS_NOT_CONFIGURED` | No live tenant terms version is configured |

If `privacy_policy_version_id` is stale or cannot be resolved against a live
version, the API returns:

| Code | Error                           | Description                                                 |
| ---- | ------------------------------- | ----------------------------------------------------------- |
| 409  | `STALE_PRIVACY_POLICY_VERSION`  | The submitted privacy policy version is no longer current   |
| 503  | `PRIVACY_POLICY_NOT_CONFIGURED` | No live privacy policy version is configured for the tenant |

Password behavior:

- The API always auto-generates the patient password.
- Clients should not send `password` or `password_confirmation`.
- The generated password is sent by email to the patient, except for the
  non-live test domains below.
- If the email ends with `@example.com`, `@dev.com`, or `@staging.com`, the API
  skips email delivery
- In **non-live environments** (staging/dev/test/local), if the email ends with
  `@example.com`, the password is set to `allia-tester`
- In **non-live environments** (staging/dev/test/local), if the email ends with
  `@dev.com`, the password is set to `Password123!`
- In **non-live environments** (staging/dev/test/local), if the email ends with
  `@staging.com`, the password is set to `Password123!`

**Response:** `201 Created`

```json
{
  "message": "Account created successfully",
  "data": {
    "user_id": "auth-user-uuid",
    "email": "patient@example.com"
  }
}
```

**Error Responses:**

| Code | Error                             | Description                                          |
| ---- | --------------------------------- | ---------------------------------------------------- |
| 400  | `MISSING_FIELDS`                  | Required fields not provided                         |
| 400  | `INVALID_EMAIL`                   | Invalid email format                                 |
| 400  | `INVALID_SHIPPING_STATE`          | Shipping state is not allowed for tenant             |
| 500  | `EMAIL_DELIVERY_FAILED`           | Auto-generated password email could not be delivered |
| 500  | `PRIVACY_POLICY_ACCEPTANCE_ERROR` | Privacy policy acceptance could not be stored        |
| 503  | `PRIVACY_POLICY_NOT_CONFIGURED`   | No live privacy policy exists                        |
| 404  | `PRODUCT_NOT_FOUND`               | Product not found or not available                   |
| 404  | `TENANT_NOT_FOUND`                | Tenant not found or inactive                         |
| 409  | `EMAIL_EXISTS`                    | Account with email already exists                    |

---

### Sign In

Authenticates a patient and returns access tokens.

```http
POST /functions/v1/patient-api/auth/signin
Content-Type: application/json
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "email": "patient@example.com",
  "password": "SecurePass123"
}
```

**Response:** `200 OK`

```json
{
  "message": "Signed in successfully",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "v1.refresh_token...",
    "expires_in": 3600,
    "expires_at": 1706180400,
    "user": {
      "id": "auth-user-uuid",
      "email": "patient@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "patient_id": "patient-uuid"
    }
  }
}
```

**Error Responses:**

| Code | Error                 | Description                         |
| ---- | --------------------- | ----------------------------------- |
| 401  | `INVALID_CREDENTIALS` | Invalid email or password           |
| 403  | `ACCOUNT_INACTIVE`    | Account is suspended or deactivated |
| 404  | `TENANT_NOT_FOUND`    | Tenant not found or inactive        |

---

### Sign Out

Signs out the current patient session.

```http
POST /functions/v1/patient-api/auth/signout
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "message": "Signed out successfully"
}
```

---

### Forgot Password

Requests a password reset email.

This endpoint generates a custom one-time reset token and sends the email
through the tenant's configured email-distribution integration. The patient
password reset flow is fully managed by the Edge Function and does not use
Supabase's built-in recovery email flow.

`redirect_url` is required and must point to the patient app reset-password
route. The email link points directly to that patient app route and appends a
`reset_token` query parameter. The patient app origin must be included in the
`CORS_ALLOWED_ORIGINS` Supabase secret so the patient app can call the Edge
Functions from that domain.

```http
POST /functions/v1/patient-api/auth/forgot-password
Content-Type: application/json
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "email": "patient@example.com",
  "redirect_url": "https://patient-app.example.com/reset-password"
}
```

**Request Body:**

| Field          | Type   | Required | Description                    |
| -------------- | ------ | -------- | ------------------------------ |
| `email`        | string | Yes      | Patient email address          |
| `redirect_url` | string | Yes      | Patient app reset-password URL |

**Response:** `200 OK`

```json
{
  "message": "If an account with this email exists, a password reset link has been sent"
}
```

> ⚠️ **Security Note:** The response is intentionally generic to prevent email
> enumeration attacks.

---

### Reset Password

Resets the password using the custom `reset_token` from the patient password
reset email.

```http
POST /functions/v1/patient-api/auth/reset-password
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "reset_token": "0d3c8b...",
  "new_password": "NewSecurePass456"
}
```

**Request Body:**

| Field          | Type   | Required | Description                         |
| -------------- | ------ | -------- | ----------------------------------- |
| `reset_token`  | string | Yes\*    | One-time token from reset email URL |
| `new_password` | string | Yes      | New password                        |

\*Alternatively, you can use the `Authorization` header if already
authenticated.

**Response:** `200 OK`

```json
{
  "message": "Password updated successfully"
}
```

---

### Refresh Token

Refreshes the access token using a refresh token.

```http
POST /functions/v1/patient-api/auth/refresh
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "refresh_token": "v1.refresh_token..."
}
```

**Response:** `200 OK`

```json
{
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "v1.new_refresh_token...",
    "expires_in": 3600,
    "expires_at": 1706184000
  }
}
```

---

### Passwordless Sign-In (Email OTP)

Lets a returning patient sign in with a **one-time 6-digit code** emailed to
them — no password required. Part of the PP-566 sign-up flow update (Option 2),
where the patient password is optional. Codes are **single-use, time-limited,
and rate-limited**. The same OTP also powers the **post-payment email
verification** step (PP-566): a successful `/auth/otp/verify` stamps
`patients.email_verified_at`, which the order lifecycle requires before the
order advances to provider intake (see
[Update Contact](#update-contact-post-payment-validation)).

Security properties (enforced server-side):

- **Single active code per email.** Requesting a new code invalidates any prior
  unused code for that email + tenant.
- **Hashed at rest.** Only `sha256(tenant_id:email:code)` is stored in
  `patient_auth_otps` — the plaintext code lives only in the email.
- **10-minute TTL** (`PATIENT_AUTH_OTP_TTL_MS`).
- **Max 5 verify attempts per code** (`PATIENT_AUTH_OTP_MAX_ATTEMPTS`); the code
  is burned on the 6th attempt.
- **Max 5 requests per email per hour**
  (`PATIENT_AUTH_OTP_MAX_REQUESTS_PER_HOUR`).
- **No account-existence leak.** `/auth/otp/request` always returns the same
  generic `200` whether or not the email maps to an active patient.
- The email is delivered via the **tenant's** Resend configuration
  (`sendEmailViaTenantDistribution`), so branding/domain match the tenant.

#### Request a Code

```http
POST /functions/v1/patient-api/auth/otp/request
Content-Type: application/json
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "email": "patient@example.com"
}
```

**Response:** `200 OK` (always — does not reveal whether the email exists)

```json
{ "message": "If an account exists, a code has been sent." }
```

> **Test-mode code retrieval (PP-566).** For **non-live test-domain** emails
> only (`@example.com` / `@dev.com` / `@staging.com`), the response also
> includes the code as `dev_code` so automated tests / example accounts can
> verify email without an inbox. Real domains never receive the code in the
> response. In development/staging, those same test-domain emails are also
> auto-marked as verified for the post-payment contact gate.
>
> ```json
> {
>   "message": "If an account exists, a code has been sent.",
>   "dev_code": "418207"
> }
> ```

| Code | Error              | Description                    |
| ---- | ------------------ | ------------------------------ |
| 400  | `INVALID_EMAIL`    | Missing or malformed email     |
| 400  | `MISSING_TENANT`   | Tenant slug header is required |
| 404  | `TENANT_NOT_FOUND` | Tenant not found               |

> A code is only generated/sent when the email maps to an **active** patient
> with a linked auth user in this tenant. Otherwise the same `200` is returned
> with no email sent.

#### Verify a Code

```http
POST /functions/v1/patient-api/auth/otp/verify
Content-Type: application/json
x-tenant-slug: acme-health
apikey: <supabase-anon-key>

{
  "email": "patient@example.com",
  "code": "123456"
}
```

**Response:** `200 OK` (session tokens, same shape as Sign In)

```json
{
  "message": "Signed in successfully",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "v1.refresh_token...",
    "expires_in": 3600,
    "expires_at": 1706184000,
    "user": {
      "id": "auth-user-uuid",
      "email": "patient@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "patient_id": "patient-uuid"
    }
  }
}
```

| Code | Error            | Description                                           |
| ---- | ---------------- | ----------------------------------------------------- |
| 400  | `MISSING_FIELDS` | Email and code are both required                      |
| 400  | `INVALID_CODE`   | Code is wrong, expired, already used, or not found    |
| 429  | `INVALID_CODE`   | Too many attempts on this code — request a new one    |
| 500  | `AUTH_ERROR`     | Session could not be established after a correct code |

> **How the session is issued:** on a correct code the backend marks it used,
> calls `auth.admin.generateLink({ type: 'magiclink' })`, then `verifyOtp` to
> mint a real Supabase session — so no password is ever needed.

> **Email verification side effect (PP-566).** A successful verify also stamps
> `patients.email_verified_at = now()` — verifying the OTP proves control of the
> email. This is what satisfies the post-payment contact-validation gate.

---

### Update Contact (post-payment validation)

`POST /functions/v1/patient-api/auth/contact/update` — **authenticated.** Lets
the patient correct their **email** and/or **phone** during the post-payment
"Secure your account & confirm contact" step (PP-566), before the
questionnaires. This ensures the provider managing the questionnaires receives
validated contact info.

- Changing the email updates the Supabase Auth user, **clears
  `email_verified_at`** (the patient must re-verify the new email via OTP), and
  rejects an email already used by another patient in the tenant.
- Phone is **attested** (no SMS verification today): it stamps
  `phone_confirmed_at` unless `confirm_phone: false`.

```http
POST /functions/v1/patient-api/auth/contact/update
Content-Type: application/json
x-tenant-slug: acme-health
Authorization: Bearer <patient-access-token>

{
  "email": "corrected@example.com",
  "phone": "5551234567",
  "confirm_phone": true
}
```

**Response:** `200 OK`

```json
{
  "message": "Contact details updated",
  "data": {
    "email": "corrected@example.com",
    "email_verified": false,
    "requires_email_verification": true
  }
}
```

| Code | Error                | Description                               |
| ---- | -------------------- | ----------------------------------------- |
| 400  | `INVALID_EMAIL`      | Malformed email                           |
| 401  | `UNAUTHORIZED`       | Missing/invalid session                   |
| 409  | `EMAIL_IN_USE`       | Email belongs to another patient (tenant) |
| 500  | `AUTH_UPDATE_FAILED` | Supabase Auth email change failed         |

> **Contact-validation gate.** The order lifecycle holds the order at
> `shipping_details_required` until `patients.email_verified_at` is set, so the
> provider never receives an unvalidated email. After verifying, the patient UI
> calls `POST /plan-api/orders/{id}/resume` to release the gate and advance the
> order toward provider intake.

---

### Social Login (Google / Apple) — OAuth Session Resolve

Returning patients can sign in with **Google** (and, later, **Apple**). The
provider OAuth dance happens **in the browser** via the Supabase client; the
resulting OAuth session is then exchanged here for a **tenant patient session**.
This endpoint is **provider-agnostic** — Google and Apple both resolve through
it; only the dashboard provider config differs.

Flow:

1. Patient UI calls
   `signInWithOAuth({ provider: 'google', redirectTo:
   <origin>/auth/callback })`.
2. Google → Supabase callback → back to the app's `/auth/callback` page with an
   established OAuth session.
3. The app posts that OAuth session's access token here with `x-tenant-slug`.

Because emails are **per-tenant**, the backend maps the OAuth email back to the
patient in the _active_ tenant. **No auto-create**: if there's no patient for
that email in this tenant, the request is **blocked** (per product decision —
the account-first checkout is what creates patients).

```http
POST /functions/v1/patient-api/auth/oauth/resolve
Content-Type: application/json
x-tenant-slug: acme-health
apikey: <supabase-anon-key>
Authorization: Bearer <oauth-session-access-token>
```

**Response:** `200 OK` (session tokens, same shape as Sign In / OTP verify)

```json
{
  "message": "Signed in successfully",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "v1.refresh_token...",
    "expires_in": 3600,
    "expires_at": 1706184000,
    "user": {
      "id": "auth-user-uuid",
      "email": "patient@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "patient_id": "patient-uuid"
    }
  }
}
```

| Code | Error              | Description                                        |
| ---- | ------------------ | -------------------------------------------------- |
| 401  | `UNAUTHORIZED`     | Missing/invalid/expired OAuth session token        |
| 403  | `ACCOUNT_INACTIVE` | Patient exists but `access_status` is not `active` |
| 404  | `NO_ACCOUNT`       | No patient for this email in this tenant (blocked) |
| 500  | `AUTH_ERROR`       | Session could not be established                   |

> On success, if the patient row has no `auth_user_id` yet, it is linked to the
> OAuth user. The tenant session is then issued via the same `generateLink` +
> `verifyOtp` magic-link technique as Email OTP.

---

### Passkeys / Biometrics

Patients can sign in with a **passkey** — a WebAuthn platform credential (Face
ID, Touch ID, fingerprint, or device passkey). Passkeys are **UI + Supabase Auth
only**: there is no dedicated patient-api endpoint for the WebAuthn ceremony.
After the ceremony establishes a Supabase session, that session is exchanged for
a tenant session through the **same `POST /auth/oauth/resolve`** endpoint
documented above (it is provider-agnostic).

- **Enrollment** (`registerPasskey`) — runs for an already-signed-in patient
  (e.g. in the post-questionnaire "Set up account access" step). The patient's
  tenant session tokens (which are Supabase JWTs) are loaded onto the Supabase
  client via `setSession`, then `registerPasskey()` performs the WebAuthn
  registration.
- **Sign-in** (`signInWithPasskey`) — needs no email; the passkey identifies the
  user. The resulting Supabase session is resolved to a tenant session via
  `/auth/oauth/resolve` (so the same `NO_ACCOUNT` / `ACCOUNT_INACTIVE` rules
  apply).

**Requirements:**

- `@supabase/supabase-js` ≥ 2.105 with `auth.experimental.passkey: true`.
- **Passkeys enabled** in the Supabase dashboard (Auth → Beta).
- The patient app origin added to the passkey **allowed RP origins** (e.g.
  `https://carelink-dev.alliahealthgroup.com`).

See [Passkeys / Biometrics Setup](./AuthMethodsSetup.md#passkeys--biometrics)
for the dashboard configuration.

---

### Migration Status

Checks whether a tenant user was migrated before the patient has signed in. This
is a tenant-scoped public lookup protected by the MD5 hash of a configured
Supabase API publishable key, which must be sent in the `Authorization` header
as a bearer token. The hash may correspond to `SUPABASE_ANON_KEY` or an
additional server-configured key in `SUPABASE_ANON_KEYS`.

The endpoint accepts the email address either as a `GET` query parameter or in a
`POST` JSON body. The response intentionally returns only the derived migration
status and does not expose patient IDs, legacy source IDs, or migration
metadata.

```http
GET /functions/v1/patient-api/migration-status?email=patient@example.com
Authorization: Bearer <md5-of-supabase-api-publishable-key>
x-tenant-slug: acme-health
```

Equivalent `POST` request:

```http
POST /functions/v1/patient-api/migration-status
Authorization: Bearer <md5-of-supabase-api-publishable-key>
x-tenant-slug: acme-health
Content-Type: application/json
```

```json
{
  "email": "patient@example.com"
}
```

**Migrated Response:** `200 OK`

```json
{
  "data": {
    "migration": {
      "isMigrated": true,
      "status": "migrated",
      "label": "Migrated"
    }
  }
}
```

If no patient record is found for the tenant/email pair, the endpoint returns
`not_migrated`.

**Not Migrated Response:** `200 OK`

```json
{
  "data": {
    "migration": {
      "isMigrated": false,
      "status": "not_migrated",
      "label": "Not migrated"
    }
  }
}
```

**Request Fields:**

| Field   | Type   | Required | Description                                      |
| ------- | ------ | -------- | ------------------------------------------------ |
| `email` | string | Yes      | Patient email address to check within the tenant |

**Required Headers:**

| Header          | Description                                                                                  |
| --------------- | -------------------------------------------------------------------------------------------- |
| `Authorization` | `Bearer <md5-of-supabase-api-publishable-key>` matching a server-configured Supabase anon key |
| `x-tenant-slug` | Tenant identifier                                                                            |
| `Content-Type`  | `application/json` for `POST` requests                                                       |

**Error Responses:**

| Code | Error            | Description                                         |
| ---- | ---------------- | --------------------------------------------------- |
| 400  | `MISSING_FIELDS` | `email` is required                                 |
| 400  | `INVALID_EMAIL`  | Email format is invalid                             |
| 401  | `UNAUTHORIZED`   | Supabase publishable key hash is missing or invalid |
| 500  | `SERVER_ERROR`   | Supabase publishable key is not configured          |

---

### Get Current Patient Profile

Retrieves the authenticated patient's profile.

```http
GET /functions/v1/patient-api/auth/me
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": {
    "user_id": "auth-user-uuid",
    "id": "patient-uuid",
    "tenant_id": "tenant-uuid",
    "first_name": "John",
    "last_name": "Doe",
    "email": "patient@example.com",
    "phone": "+1-555-123-4567",
    "date_of_birth": "1985-06-15",
    "starting_weight": 210.5,
    "target_weight": 180,
    "subscribed_to_email_marketing": true,
    "subscribed_to_sms_marketing": true,
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
      "line2": null,
      "city": "New York",
      "state": "NY",
      "postal_code": "10001",
      "country": "US"
    },
    "access_status": "active",
    "created_at": "2026-01-21T10:30:00Z",
    "intercom_user_hash": "f4d4e4c7ecf7f5b3f0f0f17f3f0e4f6956bf7c0b27ecf307d8d4f0f7f7f0f37e"
  }
}
```

`intercom_user_hash` is included only when the tenant has an enabled Intercom
integration with `backend_secret` configured. The value is an HMAC SHA-256 hex
digest generated from the authenticated patient `user_id`.

---

### Update Patient Profile

Updates the authenticated patient's profile. Address fields use nested objects
for `shipping_address` and `billing_address`.

```http
PATCH /functions/v1/patient-api/auth/me
Content-Type: application/json
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>

{
  "phone": "+1-555-987-6543",
  "starting_weight": 210.5,
  "target_weight": 180,
  "subscribed_to_email_marketing": true,
  "subscribed_to_sms_marketing": false,
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

**Updatable Fields:**

| Field                              | Type    | Description                              |
| ---------------------------------- | ------- | ---------------------------------------- |
| `first_name`                       | string  | First name                               |
| `last_name`                        | string  | Last name                                |
| `phone`                            | string  | Phone number                             |
| `date_of_birth`                    | string  | Date of birth (YYYY-MM-DD)               |
| `starting_weight`                  | number  | Starting weight                          |
| `target_weight`                    | number  | Target weight                            |
| `subscribed_to_email_marketing`    | boolean | Email marketing subscription preference  |
| `subscribed_to_sms_marketing`      | boolean | SMS marketing subscription preference    |
| `shipping_address`                 | object  | Shipping address (see below)             |
| `billing_address`                  | object  | Billing address (see below)              |

**Shipping Address Object Fields:**

| Field          | Type   | Description                                                |
| -------------- | ------ | ---------------------------------------------------------- |
| `first_name`   | string | Recipient first name                                       |
| `last_name`    | string | Recipient last name                                        |
| `company`      | string | Company name                                               |
| `line1`        | string | Street address line 1                                      |
| `line2`        | string | Street address line 2                                      |
| `city`         | string | City                                                       |
| `state`        | string | State/Province (validated against tenant's allowed_states) |
| `postal_code`  | string | Postal/ZIP code                                            |
| `country`      | string | Country code (e.g., "US")                                  |
| `instructions` | string | Delivery instructions                                      |

**Billing Address Object Fields:**

| Field         | Type   | Description                                                |
| ------------- | ------ | ---------------------------------------------------------- |
| `first_name`  | string | Billing first name                                         |
| `last_name`   | string | Billing last name                                          |
| `company`     | string | Company name                                               |
| `line1`       | string | Street address line 1                                      |
| `line2`       | string | Street address line 2                                      |
| `city`        | string | City                                                       |
| `state`       | string | State/Province (validated against tenant's allowed_states) |
| `postal_code` | string | Postal/ZIP code                                            |
| `country`     | string | Country code (e.g., "US")                                  |

> ⚠️ **State Validation:** The `state` field in any address object is validated
> against the tenant's `allowed_states` configuration. If the state is not
> allowed, the API returns `INVALID_SHIPPING_STATE` or `INVALID_BILLING_STATE`
> error.

**Response:** `200 OK`

```json
{
  "message": "Profile updated successfully",
  "data": {
    "user_id": "auth-user-uuid",
    "id": "patient-uuid",
    "tenant_id": "tenant-uuid",
    "first_name": "John",
    "last_name": "Doe",
    "email": "patient@example.com",
    "phone": "+1-555-987-6543",
    "starting_weight": 210.5,
    "target_weight": 180,
    "subscribed_to_email_marketing": true,
    "subscribed_to_sms_marketing": false,
    "shipping_address": {
      "first_name": "Jane",
      "last_name": "Doe",
      "company": null,
      "line1": "456 Oak Avenue",
      "line2": null,
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
    "access_status": "active"
  }
}
```

**Error Responses:**

| Code | Error                    | Description                                           |
| ---- | ------------------------ | ----------------------------------------------------- |
| 400  | `INVALID_SHIPPING_STATE` | Shipping address state not in tenant's allowed_states |
| 400  | `INVALID_BILLING_STATE`  | Billing address state not in tenant's allowed_states  |
| 400  | `INVALID_MARKETING_SUBSCRIPTION` | Marketing subscription values must be booleans |
| 401  | `UNAUTHORIZED`           | Missing or invalid authorization                      |
| 403  | `ACCOUNT_INACTIVE`       | Patient account is suspended/deactivated              |

---

### Authorization Header

All authenticated requests must include:

```http
Authorization: Bearer <access_token>
```

---

### Notifications

Returns pending in-app actions for the authenticated patient. The response
combines order-derived action notifications and unread durable chat
notifications.

Order notifications are derived from orders whose current
`order_statuses.patient_action_required = true` and
`order_statuses.is_patient_visible = true`. Chat notifications are persisted
from RTDH `chat.message.received` events in `patient_notifications`.

```http
GET /functions/v1/patient-api/notifications
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "order:5c5e9c65-3be3-4b4a-8e6d-b6f2f4b8ccdb",
      "type": "order_action_required",
      "title": "Complete medical questions",
      "message": "Answer a few medical questions so a licensed provider can safely review your treatment.",
      "created_at": "2026-04-01T10:15:00Z",
      "updated_at": "2026-04-02T08:30:00Z",
      "resource": {
        "type": "order",
        "id": "5c5e9c65-3be3-4b4a-8e6d-b6f2f4b8ccdb",
        "order_number": "ORD-ABC123-XYZ9",
        "product_title": "Weight Management Plan",
        "status_changed_at": "2026-04-02T08:25:00Z"
      }
    },
    {
      "id": "7d545ff2-9c0a-4f5a-bff8-9225f4d77c55",
      "type": "chat_message",
      "title": "New message",
      "message": "You have a new message from your care team.",
      "created_at": "2026-06-19T10:15:00Z",
      "updated_at": "2026-06-19T10:15:00Z",
      "resource": {
        "type": "chat",
        "provider_name": "md_integrations",
        "provider_patient_id": "b4a1e9cd-4f0b-4787-b77f-1bfb7293f6aa",
        "order_id": null
      }
    }
  ],
  "summary": {
    "total_pending_actions": 2
  }
}
```

**Response Fields:**

| Field                           | Type           | Description                                                                  |
| ------------------------------- | -------------- | ---------------------------------------------------------------------------- |
| Field                           | Type           | Description                                                                  |
| ------------------------------- | -------------- | ---------------------------------------------------------------------------- |
| `id`                            | string         | Notification identifier. Order-backed notifications use `order:{order_id}`   |
| `type`                          | string         | `order_action_required` or `chat_message`                                    |
| `title`                         | string         | Patient-facing notification title                                            |
| `message`                       | string         | Patient-facing notification body                                             |
| `resource.type`                 | string         | `order` or `chat`                                                            |
| `resource.id`                   | string         | Order UUID for order notifications                                           |
| `resource.order_number`         | string         | Human-readable order number for order notifications                          |
| `resource.product_title`        | string \| null | Product name for order notifications                                         |
| `resource.status_changed_at`    | string \| null | When the order last entered the current status                               |
| `resource.provider_name`        | string \| null | Provider key for chat notifications                                          |
| `resource.provider_patient_id`  | string \| null | Provider-side patient id for chat notifications                              |
| `resource.order_id`             | string \| null | Patient Platform order id for order-scoped chat notifications; null for MDI  |
| `summary.total_pending_actions` | integer        | Count of pending order actions plus unread chat notifications returned       |

**Error Responses:**

| Code | Error              | Description                              |
| ---- | ------------------ | ---------------------------------------- |
| 401  | `UNAUTHORIZED`     | Missing or invalid authorization         |
| 403  | `ACCOUNT_INACTIVE` | Patient account is suspended/deactivated |
| 404  | `NOT_FOUND`        | Patient profile not found                |
| 500  | `FETCH_ERROR`      | Failed to fetch notifications            |

#### Mark Notification Read

Marks an unread durable notification, such as `chat_message`, as read for the
authenticated patient.

```http
POST /functions/v1/patient-api/notifications/{notification_id}/read
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "7d545ff2-9c0a-4f5a-bff8-9225f4d77c55",
    "read_at": "2026-06-19T10:20:00.000Z"
  }
}
```

Order-derived notifications are not marked read through this endpoint because
they disappear only when the order no longer requires patient action.

---

### Reminders

Persists patient reminder settings for mobile apps and backend OneSignal push
scheduling. Reminder settings are cross-device for the same patient account.

> For full endpoint details, request/response schemas, and error codes see
> **[docs/RemindersAPI.md](./RemindersAPI.md)**.

#### List Reminders

```http
GET /functions/v1/patient-api/reminders
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK` — returns `{ "data": [ <Reminder>, … ] }`.

Each reminder object includes: `id`, `category`, `title`, `medication_id`,
`frequency`, `repeat_days`, `time_local`, `timezone`, `is_enabled`,
`disabled_reason`, `subscription_linked`, `subscription_id`, `schedule_summary`,
`created_at`, `updated_at`.

#### Create Reminder

```http
POST /functions/v1/patient-api/reminders
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json
```

Supported categories: `medication`, `body`, `energy`, `weight`.

| Frequency | Behavior                                                       |
| --------- | -------------------------------------------------------------- |
| `daily`   | `repeat_days` is ignored and stored as `null`                  |
| `weekly`  | `repeat_days` is required (array of integers 0–6, where 0=Sun) |

`title` is derived server-side from `category` and `medication_id` — do not send
it. `medication_id` is required for `category = medication` and must be `null`
for all other categories. `timezone` must be a valid IANA timezone.

On creation, 30 days of OneSignal push notifications are pre-scheduled
(non-blocking).

**Response:** `201 Created` — returns `{ "data": <Reminder> }`.

#### Update Reminder

```http
PATCH /functions/v1/patient-api/reminders/{id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json
```

Partial update — only fields provided are changed. On success, existing future
OneSignal notifications are cancelled and a fresh 30-day window is scheduled
from the updated definition (if the reminder is enabled).

**Response:** `200 OK` — returns `{ "data": <Reminder> }`.

#### Toggle Reminder

```http
PATCH /functions/v1/patient-api/reminders/{id}/enabled
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json

{ "is_enabled": false }
```

Turning a reminder off cancels all future scheduled notifications and sets
`disabled_reason` to `"user_disabled"`. Turning it on reschedules a 30-day
notification window immediately.

**Response:** `200 OK` — returns `{ "data": <Reminder> }`.

#### Delete Reminder

```http
DELETE /functions/v1/patient-api/reminders/{id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

Soft-deletes the reminder (`deleted_at` is set) and cancels all future scheduled
notifications.

**Response:** `200 OK` — returns
`{ "data": { "id": "<uuid>", "deleted": true } }`.

#### Backend Scheduler

`reminder-scheduler` is an internal Supabase Edge Function that runs daily at
01:00 UTC via `pg_cron`. It tops up pre-scheduled OneSignal notifications for
all enabled reminders to maintain a 30-day window (triggers when fewer than 14
days are remaining). Stale `scheduled` notification rows are marked `delivered`
for hygiene.

Required tenant integration:

| Integration | Settings                 |
| ----------- | ------------------------ |
| `onesignal` | `app_id`, `rest_api_key` |

---

## Base Configuration

### Environment Variables

```env
VITE_SUPABASE_URL=https://<project-id>.supabase.co
VITE_SUPABASE_ANON_KEY=<anon-key>
VITE_TENANT_SLUG=acme-health
```

### Tenant Resolution

The Patient UI must resolve the tenant context. Options:

1. **Subdomain-based:** `acme-health.patient-app.com`
2. **Path-based:** `patient-app.com/acme-health`
3. **Environment variable:** Pre-configured for white-label deployments

#### Get Tenant by Slug

```http
GET /rest/v1/tenants?slug=eq.{tenant_slug}&select=id,name,slug,status
```

**Response:**

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "ACME Health",
    "slug": "acme-health",
    "status": "active"
  }
]
```

> ⚠️ **Important:** Only tenants with `status = 'active'` should allow patient
> operations.

---

## Endpoints

### Patient Registration

After Supabase Auth signup, create the patient profile record.

#### Create Patient Profile

```http
POST /rest/v1/patients
Content-Type: application/json
Authorization: Bearer <token>
Prefer: return=representation

{
  "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "patient@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+1-555-123-4567",
  "date_of_birth": "1985-06-15",
  "address_line1": "123 Main Street",
  "address_line2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "US"
}
```

**Response:** `201 Created`

```json
{
  "id": "patient-uuid",
  "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "patient@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "access_status": "active",
  "created_at": "2026-01-21T10:30:00Z"
}
```

---

### Patient Profile

#### Get Current Patient Profile

```http
GET /rest/v1/patients?email=eq.{patient_email}&tenant_id=eq.{tenant_id}&select=*
Authorization: Bearer <token>
```

#### Update Patient Profile

```http
PATCH /rest/v1/patients?id=eq.{patient_id}
Content-Type: application/json
Authorization: Bearer <token>
Prefer: return=representation

{
  "phone": "+1-555-987-6543",
  "address_line1": "456 Oak Avenue"
}
```

---

### Catalog

Products and protocols available for the tenant.

> 💡 **Recommended:** Use the dedicated **Patient API Edge Function** for
> catalog access. It provides optimized responses, rate limiting, and better
> error handling.

---

#### Patient API Edge Function

The Patient API is available at:

```
VITE_SUPABASE_URL/functions/v1/patient-api
```

##### Configuration

| Header          | Description                                | Required        |
| --------------- | ------------------------------------------ | --------------- |
| `x-tenant-slug` | Tenant identifier (e.g., `acme-health`)    | Yes             |
| `Authorization` | Bearer token (for authenticated endpoints) | For auth routes |
| `Content-Type`  | `application/json`                         | For POST/PATCH  |

##### Get Tenant Info

```http
GET /functions/v1/patient-api/tenant
x-tenant-slug: acme-health
```

**Response:**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "ACME Health",
    "slug": "acme-health",
    "status": "active",
    "contact_email": "support@acme-health.com",
    "branding": {
      "logo_url": "https://...",
      "primary_color": "#3B82F6",
      "secondary_color": "#10B981",
      "accent_color": "#F59E0B"
    }
  }
}
```

#### Messaging (Chat Threads)

Messaging endpoints are served from:

```
VITE_SUPABASE_URL/functions/v1/messenger-api
```

This section keeps the Telegra examples for backward compatibility. For the
complete current Messenger API, including MDI patient-channel messages and the
MDI `/mdi-patient-chat/status` polling endpoint for chat badges, see
[`MessengerAPI.md`](./MessengerAPI.md).

##### Get Chat Threads

Fetches patient chat threads from Telegra for the authenticated patient.

```http
GET /functions/v1/messenger-api/telegra-clinical-chat?chatType=clinical
Authorization: Bearer <patient_access_token>
```

**Query Parameters:**

| Parameter  | Type   | Required | Description                     |
| ---------- | ------ | -------- | ------------------------------- |
| `chatType` | string | Yes      | Must be `clinical` or `support` |

**Behavior:**

- Validates the bearer token and resolves the active patient profile
- Resolves the patient's Telegra provider-platform patient id
- Calls Telegra:
  `GET /patientConversations/getByPatient/{providerPatientId}?channelType={chatType}`
- Returns provider metadata, required ids, and the available chat list
- If Telegra returns a single `channel` object, the API normalizes it to
  `chats: [channel]`
- When available, `participantIdentifier` is included in each object under
  `chats`
- For `chatType=clinical`, Telegra messages where `type === "ADMM"` are filtered
  out from each chat `messages` array, including the first message in the thread

**Response:**

```json
{
  "data": {
    "provider_platform": {
      "name": "TelegraMD",
      "integration_key": "telegramd"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "telegra-patient-id"
    },
    "chat_type": "clinical",
    "total_chats": 1,
    "chats": [
      {
        "participantIdentifier": "pcv::...",
        "...telegra channel payload...": "..."
      }
    ]
  }
}
```

##### Send Chat Message

Sends a text message or one file to a Telegra patient conversation for the
authenticated patient.

```http
POST /functions/v1/messenger-api/telegra-clinical-chat
Authorization: Bearer <patient_access_token>
Content-Type: application/json

{
  "conversationID": "pcv::be056e0d-3cce-46c7-8ef7-7318f9386d14",
  "channelType": "clinical",
  "message": "Hello team, I have a question about my treatment.",
  "file": {
    "name": "Lab result",
    "ext": "pdf",
    "base64Data": "JVBERi0x..."
  }
}
```

**Request Body:**

| Field            | Type   | Required                          | Description                                            |
| ---------------- | ------ | --------------------------------- | ------------------------------------------------------ |
| `conversationID` | string | Yes                               | Telegra conversation id                                |
| `channelType`    | string | Yes                               | Must be `clinical` or `support`                        |
| `message`        | string | Required unless `file` is present | Message text to send                                   |
| `file`           | object | Required when sending a file      | `{ name, ext?, base64Data }` with raw base64 data only |

> Note: `conversationId` is also accepted as an alias for `conversationID`. Send
> either `message` or `file`, not both.

**Behavior:**

- Validates the bearer token and resolves the active patient profile
- Resolves the patient's Telegra provider-platform patient id
- Calls Telegra: `POST /patientConversations/{conversationID}/sendMessage`
- Sends either a text body with `message` or a file body with
  `file: { name, ext?, base64Data }`, plus `sender: "patient"` and `channelType`

**Response:**

```json
{
  "message": "Message sent successfully",
  "data": {
    "provider_platform": {
      "name": "TelegraMD",
      "integration_key": "telegramd"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "pat::..."
    },
    "conversation_id": "pcv::...",
    "channel_type": "clinical",
    "telegra_response": { "...raw telegra response..." }
  }
}
```

**Error Responses (Get/Send Chat Threads):**

| Code | Error                        | Description                                                |
| ---- | ---------------------------- | ---------------------------------------------------------- |
| 400  | `INVALID_CHAT_TYPE`          | `chatType` or `channelType` is not `clinical` or `support` |
| 400  | `INVALID_JSON`               | Request body is not valid JSON                             |
| 400  | `MISSING_FIELDS`             | Required fields (for send message) are missing             |
| 401  | `UNAUTHORIZED`               | Missing, invalid, or expired bearer token                  |
| 403  | `ACCOUNT_INACTIVE`           | Patient account is not active                              |
| 404  | `NOT_FOUND`                  | Patient profile not found                                  |
| 404  | `TELEGRA_NOT_CONFIGURED`     | No enabled Telegra integration for the patient             |
| 404  | `TELEGRA_PATIENT_ID_MISSING` | Patient has no linked Telegra patient id                   |
| 500  | `FETCH_ERROR`                | Internal fetch/read error from platform data               |
| 500  | `TELEGRA_CONFIG_MISSING`     | Telegra URL/access token not configured                    |
| 502  | `TELEGRA_REQUEST_FAILED`     | Telegra endpoint was unreachable                           |
| 502  | `TELEGRA_API_ERROR`          | Telegra returned a non-success response                    |

##### List Products

```http
GET /functions/v1/patient-api/products
x-tenant-slug: acme-health
```

**Query Parameters:**

| Parameter   | Type    | Default | Description              |
| ----------- | ------- | ------- | ------------------------ |
| `page`      | integer | 1       | Page number              |
| `page_size` | integer | 20      | Items per page (max 100) |
| `category`  | string  | -       | Filter by category key   |

**Response:**

```json
{
  "data": [
    {
      "id": "product-uuid-1",
      "name": "Testosterone Therapy - Monthly",
      "description": "Monthly testosterone cypionate treatment",
      "terms_and_conditions": "<p>By purchasing this product you agree...</p>",
      "terms_and_conditions_html": "<p>By purchasing this product you agree...</p>",
      "price_cents": 19900,
      "price_formatted": "$199.00",
      "sku": "TRT-MONTHLY-001",
      "image_url": "https://...",
      "payment_type": "subscription",
      "subscription_interval": "month",
      "subscription_interval_count": 1,
      "subscription_renewal_lead_days": 7,
      "faqs": [
        {
          "id": "faq-uuid-1",
          "question": "How long does shipping take?",
          "answer": "Orders typically arrive within 3-5 business days.",
          "display_order": 1
        }
      ],
      "categories": [
        {
          "id": "cat-uuid",
          "key": "hormone-therapy",
          "name": "Hormone Therapy"
        }
      ]
    }
  ],
  "pagination": {
    "page": 1,
    "page_size": 20,
    "total": 42,
    "total_pages": 3,
    "has_more": true
  }
}
```

`faqs` is sorted by `display_order` ascending.

##### Get Product Details

```http
GET /functions/v1/patient-api/products/{product_id}
x-tenant-slug: acme-health
```

**Response:**

```json
{
  "data": {
    "id": "product-uuid-1",
    "name": "Testosterone Therapy - Monthly",
    "description": "Monthly testosterone cypionate treatment",
    "terms_and_conditions": "<p>By purchasing this product you agree...</p>",
    "terms_and_conditions_html": "<p>By purchasing this product you agree...</p>",
    "price_cents": 19900,
    "price_formatted": "$199.00",
    "sku": "TRT-MONTHLY-001",
    "image_url": "https://...",
    "payment_type": "subscription",
    "subscription_interval": "month",
    "subscription_interval_count": 1,
    "subscription_renewal_lead_days": 7,
    "faqs": [
      {
        "id": "faq-uuid-1",
        "question": "How long does shipping take?",
        "answer": "Orders typically arrive within 3-5 business days.",
        "display_order": 1
      }
    ],
    "metadata": {},
    "categories": [
      { "id": "cat-uuid", "key": "hormone-therapy", "name": "Hormone Therapy" }
    ],
    "medications": [
      {
        "quantity": 1,
        "instructions": "Inject weekly as directed",
        "medication": {
          "id": "med-uuid",
          "name": "Testosterone Cypionate 200mg/mL",
          "description": "Injectable testosterone",
          "form": "injection",
          "image_url": "https://..."
        }
      }
    ],
    "questionnaires": [
      {
        "display_order": 1,
        "is_required": true,
        "id": "q-uuid",
        "name": "Medical History Intake",
        "description": "Complete before ordering",
        "schema": { "type": "object", "properties": {} },
        "version": 1
      }
    ]
  }
}
```

`faqs` is sorted by `display_order` ascending.

##### Validate Product Shipping State

Validates whether a product can be shipped to a given state (tenant shipping
rules are applied).

```http
POST /functions/v1/patient-api/products/{product_id}/validate-shipping-state
Content-Type: application/json
x-tenant-slug: acme-health

{
  "state": "CA",
  "country": "US"
}
```

**Request Body:**

| Field     | Type   | Required | Description                         |
| --------- | ------ | -------- | ----------------------------------- |
| `state`   | string | Yes      | State code to validate (e.g., `CA`) |
| `country` | string | No       | Country code. Defaults to `US`      |

**Response:**

```json
{
  "data": {
    "product_id": "product-uuid-1",
    "product_name": "Testosterone Therapy - Monthly",
    "state": "CA",
    "country": "US",
    "is_shippable": true,
    "message": "Shipping is available for this product in the requested state."
  }
}
```

**Error Responses:**

| Code | Error               | Description                        |
| ---- | ------------------- | ---------------------------------- |
| 400  | `MISSING_FIELDS`    | `state` is missing                 |
| 404  | `TENANT_NOT_FOUND`  | Tenant not found or inactive       |
| 404  | `PRODUCT_NOT_FOUND` | Product not found or not available |

##### Get Latest Tenant Terms and Conditions

Returns the current live terms version for the tenant identified by
`x-tenant-slug`.

```http
GET /functions/v1/patient-api/terms-and-conditions/latest
x-tenant-slug: acme-health
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "id": "tenant-terms-version-uuid-1",
    "tenant_id": "tenant-uuid-1",
    "version": 3,
    "content": "<p>These are the live tenant terms...</p>",
    "published_at": "2026-05-06T12:00:00.000Z"
  }
}
```

**Error Responses:**

| Code | Error                         | Description                                |
| ---- | ----------------------------- | ------------------------------------------ |
| 400  | `MISSING_TENANT`              | Tenant slug header/query is missing        |
| 404  | `TENANT_NOT_FOUND`            | Tenant not found or inactive               |
| 404  | `TENANT_TERMS_NOT_CONFIGURED` | No live tenant terms version is configured |

##### Get Tenant Terms Acceptance Status

Checks whether the authenticated patient has already accepted the current live
tenant terms version.

```http
GET /functions/v1/patient-api/terms-and-conditions/acceptance-status
x-tenant-slug: acme-health
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "patient_id": "patient-uuid-1",
    "tenant_id": "tenant-uuid-1",
    "live_version_id": "tenant-terms-version-uuid-1",
    "live_version": 3,
    "has_accepted_latest_terms": true,
    "latest_accepted_version_id": "tenant-terms-version-uuid-1",
    "latest_accepted_version": 3,
    "latest_accepted_at": "2026-05-06T12:34:56.000Z"
  }
}
```

**Error Responses:**

| Code | Error                         | Description                                |
| ---- | ----------------------------- | ------------------------------------------ |
| 401  | `UNAUTHORIZED`                | Missing/invalid patient token              |
| 403  | `ACCOUNT_INACTIVE`            | Patient account is not active              |
| 404  | `TENANT_NOT_FOUND`            | Tenant not found or inactive               |
| 404  | `NOT_FOUND`                   | Patient profile not found                  |
| 404  | `TENANT_TERMS_NOT_CONFIGURED` | No live tenant terms version is configured |

##### Accept Tenant Terms and Conditions

Records acceptance of the current live tenant terms version.

Behavior:

- The submitted `tenant_terms_version_id` must match the current live version.
- `platform_terms_version_id` is accepted as a legacy alias while clients
  migrate.
- If the patient already accepted the live version, the API returns the existing
  acceptance record.
- If the version is stale, the API returns `409 STALE_TENANT_TERMS_VERSION`.

```http
POST /functions/v1/patient-api/terms-and-conditions/accept
x-tenant-slug: acme-health
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json

{
  "tenant_terms_version_id": "tenant-terms-version-uuid-1"
}
```

**Response:**

```json
{
  "data": {
    "id": "acceptance-uuid-1",
    "tenant_terms_version_id": "tenant-terms-version-uuid-1",
    "platform_terms_version_id": "tenant-terms-version-uuid-1",
    "tenant_terms_version": 3,
    "platform_terms_version": 3,
    "accepted_at": "2026-05-06T12:34:56.000Z",
    "already_accepted": false
  }
}
```

**Error Responses:**

| Code | Error                         | Description                                              |
| ---- | ----------------------------- | -------------------------------------------------------- |
| 400  | `MISSING_TENANT`              | Tenant slug header/query is missing                      |
| 400  | `MISSING_FIELDS`              | `tenant_terms_version_id` is missing                     |
| 401  | `UNAUTHORIZED`                | Missing/invalid patient token                            |
| 403  | `ACCOUNT_INACTIVE`            | Patient account is not active                            |
| 404  | `TENANT_NOT_FOUND`            | Tenant not found or inactive                             |
| 404  | `NOT_FOUND`                   | Patient profile not found                                |
| 404  | `TENANT_TERMS_NOT_CONFIGURED` | No live tenant terms version is configured               |
| 409  | `STALE_TENANT_TERMS_VERSION`  | Submitted version is not the current live tenant version |

##### Get Latest Tenant Privacy Policy

Returns the current live privacy policy version for the tenant identified by
`x-tenant-slug`.

```http
GET /functions/v1/patient-api/privacy-policy/latest
x-tenant-slug: acme-health
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "id": "privacy-policy-version-uuid-1",
    "tenant_id": "tenant-uuid-1",
    "version": 2,
    "content": "<p>This is the live tenant privacy policy...</p>",
    "published_at": "2026-05-28T12:00:00.000Z"
  }
}
```

**Error Responses:**

| Code | Error                           | Description                                 |
| ---- | ------------------------------- | ------------------------------------------- |
| 400  | `MISSING_TENANT`                | Tenant slug header/query is missing         |
| 404  | `TENANT_NOT_FOUND`              | Tenant not found or inactive                |
| 404  | `PRIVACY_POLICY_NOT_CONFIGURED` | No live tenant privacy policy is configured |

##### Get Privacy Policy Acceptance Status

Checks whether the authenticated patient has already accepted the current live
privacy policy version.

```http
GET /functions/v1/patient-api/privacy-policy/acceptance-status
x-tenant-slug: acme-health
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "patient_id": "patient-uuid-1",
    "tenant_id": "tenant-uuid-1",
    "live_version_id": "privacy-policy-version-uuid-1",
    "live_version": 2,
    "has_accepted_latest_privacy_policy": true,
    "latest_accepted_version_id": "privacy-policy-version-uuid-1",
    "latest_accepted_version": 2,
    "latest_accepted_at": "2026-05-28T12:34:56.000Z"
  }
}
```

**Error Responses:**

| Code | Error                           | Description                                 |
| ---- | ------------------------------- | ------------------------------------------- |
| 401  | `UNAUTHORIZED`                  | Missing/invalid patient token               |
| 403  | `ACCOUNT_INACTIVE`              | Patient account is not active               |
| 404  | `TENANT_NOT_FOUND`              | Tenant not found or inactive                |
| 404  | `NOT_FOUND`                     | Patient profile not found                   |
| 404  | `PRIVACY_POLICY_NOT_CONFIGURED` | No live tenant privacy policy is configured |

##### Accept Privacy Policy

Records acceptance of the current live privacy policy version.

Behavior:

- The submitted `privacy_policy_version_id` must match the current live version.
- If the patient already accepted the live version, the API returns the existing
  acceptance record.
- If the version is stale, the API returns `409 STALE_PRIVACY_POLICY_VERSION`.

```http
POST /functions/v1/patient-api/privacy-policy/accept
x-tenant-slug: acme-health
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json

{
  "privacy_policy_version_id": "privacy-policy-version-uuid-1"
}
```

**Response:**

```json
{
  "data": {
    "id": "privacy-policy-acceptance-uuid-1",
    "privacy_policy_version_id": "privacy-policy-version-uuid-1",
    "privacy_policy_version": 2,
    "accepted_at": "2026-05-28T12:34:56.000Z",
    "already_accepted": false
  }
}
```

**Error Responses:**

| Code | Error                           | Description                                              |
| ---- | ------------------------------- | -------------------------------------------------------- |
| 400  | `MISSING_TENANT`                | Tenant slug header/query is missing                      |
| 400  | `MISSING_FIELDS`                | `privacy_policy_version_id` is missing                   |
| 401  | `UNAUTHORIZED`                  | Missing/invalid patient token                            |
| 403  | `ACCOUNT_INACTIVE`              | Patient account is not active                            |
| 404  | `TENANT_NOT_FOUND`              | Tenant not found or inactive                             |
| 404  | `NOT_FOUND`                     | Patient profile not found                                |
| 404  | `PRIVACY_POLICY_NOT_CONFIGURED` | No live tenant privacy policy is configured              |
| 409  | `STALE_PRIVACY_POLICY_VERSION`  | Submitted version is not the current live privacy policy |

##### Get Product Terms Acceptance Status

Checks whether the authenticated patient has accepted the **latest** terms and
conditions for a product.

Comparison behavior:

- Uses the latest acceptance record for the patient + product from
  `patient_terms_acceptances`.
- Compares accepted content vs current product terms as **plain text without
  HTML**.
- HTML/styling-only changes do not affect the comparison outcome.

```http
GET /functions/v1/patient-api/products/{product_id}/terms-acceptance-status
x-tenant-slug: acme-health
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "product_id": "product-uuid-1",
    "product_name": "Testosterone Therapy - Monthly",
    "patient_id": "patient-uuid-1",
    "has_acceptance_record": true,
    "has_accepted_latest_terms": true,
    "latest_accepted_at": "2026-03-24T12:34:56.000Z",
    "comparison_mode": "plain_text_without_html"
  }
}
```

**Error Responses:**

| Code | Error               | Description                        |
| ---- | ------------------- | ---------------------------------- |
| 401  | `UNAUTHORIZED`      | Missing/invalid patient token      |
| 403  | `ACCOUNT_INACTIVE`  | Patient account is not active      |
| 404  | `TENANT_NOT_FOUND`  | Tenant not found or inactive       |
| 404  | `PRODUCT_NOT_FOUND` | Product not found or not available |
| 404  | `NOT_FOUND`         | Patient profile not found          |

##### Accept Product Terms and Conditions

Creates a new acceptance record for the authenticated patient and product in
`patient_terms_acceptances`.

Behavior:

- Uses the current `products.terms_and_conditions_html` as the accepted
  snapshot.
- Sets `accepted_at` to the request time.
- Always creates a new historical record.

```http
POST /functions/v1/patient-api/products/{product_id}/terms-acceptance
x-tenant-slug: acme-health
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:**

```json
{
  "data": {
    "id": "acceptance-uuid-1",
    "product_id": "product-uuid-1",
    "product_name": "Testosterone Therapy - Monthly",
    "patient_id": "patient-uuid-1",
    "accepted_at": "2026-03-24T13:22:33.000Z",
    "accepted_content": "<p>By purchasing this product you agree...</p>"
  }
}
```

**Error Responses:**

| Code | Error                    | Description                         |
| ---- | ------------------------ | ----------------------------------- |
| 400  | `MISSING_TENANT`         | Tenant slug header/query is missing |
| 401  | `UNAUTHORIZED`           | Missing/invalid patient token       |
| 403  | `ACCOUNT_INACTIVE`       | Patient account is not active       |
| 404  | `TENANT_NOT_FOUND`       | Tenant not found or inactive        |
| 404  | `PRODUCT_NOT_FOUND`      | Product not found or not available  |
| 404  | `NOT_FOUND`              | Patient profile not found           |
| 500  | `FETCH_ERROR`            | Failed to fetch patient/product     |
| 500  | `TERMS_ACCEPTANCE_ERROR` | Failed to save accepted terms       |

##### List Categories

```http
GET /functions/v1/patient-api/categories
```

**Response:**

```json
{
  "data": [
    {
      "id": "cat-uuid-1",
      "key": "hormone-therapy",
      "name": "Hormone Therapy",
      "description": "Hormone replacement treatments",
      "display_order": 1
    },
    {
      "id": "cat-uuid-2",
      "key": "weight-management",
      "name": "Weight Management",
      "description": "Weight loss medications",
      "display_order": 2
    }
  ]
}
```

---

#### Direct Database Access (Alternative)

You can also query the database directly via PostgREST:

##### List Available Products

```http
GET /rest/v1/products?tenant_id=eq.{tenant_id}&is_enabled=eq.true&select=id,name,description,price_cents,sku
Authorization: Bearer <token>
```

**Response:**

```json
[
  {
    "id": "product-uuid-1",
    "name": "Testosterone Therapy - Monthly",
    "description": "Monthly testosterone cypionate treatment",
    "price_cents": 19900,
    "sku": "TRT-MONTHLY-001"
  }
]
```

##### Get Product Details with Medications

```http
GET /rest/v1/products?id=eq.{product_id}&select=*,product_medications(quantity,instructions,medication:medications(title,description,form))
Authorization: Bearer <token>
```

#### List Available Protocols

```http
GET /rest/v1/protocols?tenant_id=eq.{tenant_id}&is_enabled=eq.true&select=id,name,description,price_cents,duration_days
Authorization: Bearer <token>
```

#### Get Protocol with Products

```http
GET /rest/v1/protocols?id=eq.{protocol_id}&select=*,protocol_products(display_order,product:products(id,name,price_cents))
Authorization: Bearer <token>
```

---

### Questionnaires

Intake questionnaires required before ordering.

#### Get Questionnaires for a Product

```http
GET /rest/v1/product_questionnaire_links?product_id=eq.{product_id}&select=display_order,is_required,questionnaire:questionnaire_templates(id,name,description,schema,version)
Authorization: Bearer <token>
Order: display_order.asc
```

**Response:**

```json
[
  {
    "display_order": 1,
    "is_required": true,
    "questionnaire": {
      "id": "questionnaire-uuid",
      "name": "Medical History Intake",
      "description": "Please complete your medical history",
      "version": 1,
      "schema": {
        "type": "object",
        "properties": {
          "current_medications": {
            "type": "array",
            "title": "Current Medications",
            "items": { "type": "string" }
          },
          "allergies": {
            "type": "array",
            "title": "Known Allergies",
            "items": { "type": "string" }
          },
          "medical_conditions": {
            "type": "array",
            "title": "Medical Conditions",
            "items": { "type": "string" }
          }
        }
      }
    }
  }
]
```

#### Get Questionnaires for a Protocol

```http
GET /rest/v1/protocol_questionnaire_links?protocol_id=eq.{protocol_id}&select=display_order,is_required,questionnaire:questionnaire_templates(id,name,description,schema,version)
Authorization: Bearer <token>
Order: display_order.asc
```

> **Note:** Shared questionnaires (`is_shared = true`) are available across all
> tenants.

---

### Orders

Order and checkout endpoints have moved to
[Plan API Documentation](./PlanAPI.md).

---

### Subscriptions

Subscriptions are lifecycle records that group recurring orders. They are
created/updated by checkout + webhook flows.

#### List Current Patient Plans (Plan API)

See [Plan API Documentation](./PlanAPI.md#plans) for endpoint details, including
embedded `orders` per plan.

#### List Patient Subscriptions

```http
GET /rest/v1/subscriptions?patient_id=eq.{patient_id}&tenant_id=eq.{tenant_id}&select=id,status,started_at,current_period_end_at,expires_at,paused_at,cancelled_at,created_at,updated_at,product:products(id,name,price_cents)
Authorization: Bearer <token>
```

#### Lifecycle Source of Truth

- Renewal date: `subscriptions.current_period_end_at`
- Expiration date: `subscriptions.expires_at`
- Order linkage: `orders.subscription_id -> subscriptions.id`
- Provider lifecycle IDs: `subscription_payment_provider_links`
- Provider payment transactions: `order_payment_provider_transactions`

#### Payment Provider Entities

Use these tables for provider data instead of provider-specific columns on
`orders`/`subscriptions`:

- `subscription_payment_provider_links`: provider lifecycle linkage
  (subscription/checkout IDs per provider)
- `order_payment_provider_transactions`: provider payment transaction snapshot
  per order (intent/invoice/charge/status/paid_at)

> Patient-facing plan cancel/reactivate are available via
> `POST /functions/v1/plan-api/plans/{plan_id}/cancel` and
> `POST /functions/v1/plan-api/plans/{plan_id}/reactivate`. Pause/resume are not
> exposed in this API version.

#### Subscription Status Values

| Status                 | Description                                                                    |
| ---------------------- | ------------------------------------------------------------------------------ |
| `pending_validation`   | Subscription created but waiting for validation before becoming active         |
| `active`               | Subscription active, will auto-renew                                           |
| `pending_cancellation` | Subscription marked to cancel at expiration; no further renewals are scheduled |
| `paused`               | Subscription paused, can be resumed                                            |
| `cancelled`            | Subscription cancelled, cannot be resumed                                      |

---

## Data Models

### Patient

```typescript
interface Patient {
  id: string; // UUID
  tenant_id: string; // UUID - Required
  email: string; // Required
  first_name: string; // Required
  last_name: string; // Required
  phone?: string;
  date_of_birth?: string; // YYYY-MM-DD format
  address?: Address; // Primary/residential address
  shipping_address?: ShippingAddress; // Shipping address
  billing_address?: BillingAddress; // Billing address
  access_status: "active" | "suspended" | "deactivated";
  external_id?: string; // External system reference
  vitals?: Record<string, unknown>; // JSON object
  allergies?: string[]; // JSON array
  medications?: string[]; // JSON array
  conditions?: string[]; // JSON array
  metadata?: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

interface Address {
  line1?: string;
  line2?: string;
  city?: string;
  state?: string;
  postal_code?: string;
  country?: string; // Default: 'US'
}

interface ShippingAddress extends Address {
  first_name?: string;
  last_name?: string;
  company?: string;
  instructions?: string;
}

interface BillingAddress extends Address {
  first_name?: string;
  last_name?: string;
  company?: string;
}
```

### Product

```typescript
interface Product {
  id: string;
  tenant_id: string;
  name: string;
  description?: string;
  terms_and_conditions?: string | null; // Alias of terms_and_conditions_html
  terms_and_conditions_html?: string | null; // Rich HTML content managed per product
  sku?: string;
  price_cents: number; // Price in cents (e.g., 19900 = $199.00)
  is_enabled: boolean;
  payment_type?: "one_time" | "subscription";
  subscription_interval?: "day" | "week" | "month" | "year" | null;
  subscription_interval_count?: number | null;
  subscription_renewal_lead_days?: number;
  faqs?: ProductFaq[];
  metadata?: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

interface ProductFaq {
  id: string;
  question: string;
  answer: string;
  display_order: number;
}
```

### Order

```typescript
interface Order {
  id: string;
  tenant_id: string;
  patient_id: string;
  subscription_id?: string | null;
  order_number: string; // Unique order reference
  status_id: string;
  status_details?: {
    id: string;
    key: string;
    label: string;
    description?: string | null;
    action_required: boolean;
    is_final: boolean;
    display_order?: number;
  };
  subtotal_cents: number;
  tax_cents: number;
  shipping_cents: number;
  total_cents: number;
  shipping_address?: ShippingAddress; // Nested shipping address object
  tracking_number?: string;
  tracking_url?: string;
  internal_notes?: string; // Not visible to patients
  shipped_at?: string;
  delivered_at?: string;
  paused_at?: string;
  cancelled_at?: string;
  renewal_at?: string | null; // Derived from subscription.current_period_end_at
  created_at: string;
  updated_at: string;
}

// Order status is now managed via the order_statuses table
// Orders reference status_id (UUID) instead of a status enum
// Use status_details object for display information

// ShippingAddress is defined in the Patient section above
```

### Subscription

```typescript
interface Subscription {
  id: string;
  tenant_id: string;
  patient_id: string;
  product_id?: string | null;
  status: SubscriptionStatus;
  started_at?: string | null;
  current_period_end_at?: string | null;
  expires_at?: string | null;
  paused_at?: string | null;
  cancelled_at?: string | null;
  cancellation_reason?: string | null;
  metadata?: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

type SubscriptionStatus =
  | "active"
  | "pending_validation"
  | "pending_cancellation"
  | "paused"
  | "cancelled";

interface SubscriptionPaymentProviderLink {
  id: string;
  tenant_id: string;
  subscription_id: string;
  payment_provider_id: string;
  provider_subscription_id?: string | null;
  provider_checkout_session_id?: string | null;
  metadata?: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

interface OrderPaymentProviderTransaction {
  id: string;
  tenant_id: string;
  order_id: string;
  subscription_id?: string | null;
  payment_provider_id: string;
  provider_payment_intent_id?: string | null;
  provider_invoice_id?: string | null;
  provider_charge_id?: string | null;
  provider_subscription_id?: string | null;
  provider_checkout_session_id?: string | null;
  payment_status?: string | null;
  paid_at?: string | null;
  metadata?: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}
```

### Questionnaire Schema

Questionnaire schemas follow JSON Schema format:

```typescript
interface QuestionnaireSchema {
  type: "object";
  properties: Record<string, QuestionDefinition>;
  required?: string[];
}

interface QuestionDefinition {
  type: "string" | "number" | "boolean" | "array";
  title: string;
  description?: string;
  enum?: string[]; // For select/radio options
  items?: { type: string }; // For array types
  minimum?: number;
  maximum?: number;
}
```

---

## Error Handling

### HTTP Status Codes

| Code  | Meaning                                  |
| ----- | ---------------------------------------- |
| `200` | Success                                  |
| `201` | Created                                  |
| `400` | Bad Request - Invalid input              |
| `401` | Unauthorized - Invalid or missing token  |
| `403` | Forbidden - Insufficient permissions     |
| `404` | Not Found                                |
| `409` | Conflict - Duplicate resource            |
| `422` | Unprocessable Entity - Validation failed |
| `429` | Too Many Requests - Rate limited         |
| `500` | Internal Server Error                    |

### Error Response Format

```json
{
  "code": "PGRST116",
  "details": null,
  "hint": null,
  "message": "The result contains 0 rows"
}
```

### Common Error Codes

| Code       | Description                      |
| ---------- | -------------------------------- |
| `PGRST116` | No rows returned (not found)     |
| `PGRST301` | Row-level security violation     |
| `23505`    | Unique constraint violation      |
| `23503`    | Foreign key constraint violation |
| `22P02`    | Invalid UUID format              |

---

## Rate Limiting

API requests are rate-limited per authenticated user:

| Tier          | Limit              |
| ------------- | ------------------ |
| Anonymous     | 100 requests/hour  |
| Authenticated | 1000 requests/hour |

Rate limit headers are included in responses:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 950
X-RateLimit-Reset: 1674307200
```

---

## Security Considerations

### Row-Level Security (RLS)

All database tables enforce RLS policies. Patients can only:

- **Read:** Their own profile, their orders, their subscriptions
- **Read:** Enabled products/protocols for their tenant
- **Read:** Active questionnaire templates
- **Update:** Their own profile

Patient order creation/updates are enforced through Patient API business rules
(Edge Functions), not direct table writes.

### Tenant Isolation

All data is strictly isolated by `tenant_id`. Patients can only access data
belonging to their registered tenant.

### Patient Access Status

Patients with `access_status` other than `active` may have restricted
functionality:

| Status        | Access Level     |
| ------------- | ---------------- |
| `active`      | Full access      |
| `suspended`   | Read-only access |
| `deactivated` | No access        |

### Data Protection

- PHI (Protected Health Information) is encrypted at rest
- All API communication must use HTTPS
- Tokens expire after 1 hour (configurable)
- Refresh tokens can be used to obtain new access tokens

---

## Appendix: Supabase Client Setup

### JavaScript/TypeScript

```typescript
import { createClient } from "@supabase/supabase-js";

const supabase = createClient(
  import.meta.env.VITE_SUPABASE_URL,
  import.meta.env.VITE_SUPABASE_ANON_KEY,
);

// Example: Fetch products
const { data: products, error } = await supabase
  .from("products")
  .select("id, name, description, price_cents")
  .eq("tenant_id", tenantId)
  .eq("is_enabled", true);
```

### React Query Integration

```typescript
import { useQuery } from "@tanstack/react-query";
import { supabase } from "./supabaseClient";

export function useProducts(tenantId: string) {
  return useQuery({
    queryKey: ["products", tenantId],
    queryFn: async () => {
      const { data, error } = await supabase
        .from("products")
        .select("*")
        .eq("tenant_id", tenantId)
        .eq("is_enabled", true);

      if (error) throw error;
      return data;
    },
    enabled: !!tenantId,
  });
}
```

---

## Troubleshooting

### CORS Errors with 401 Unauthorized (Browser-Only)

If you receive CORS errors in the browser console when calling authenticated
endpoints (like `/orders/{product_id}/checkout`), but the same request works in
Postman/curl, this is typically caused by **expired or invalid JWT tokens**.

**Root Cause:** The API gateway validates the `Authorization` header before the
request reaches the edge function. When the JWT is expired or malformed, the
gateway returns a 401 response **without CORS headers**, which browsers then
block as a CORS error.

Allowed browser origins for the edge functions are configured centrally through
the `CORS_ALLOWED_ORIGINS` environment variable in
`supabase/functions/_shared/cors.ts`. For deployed Supabase Edge Functions, set
that value as a Supabase secret for the target project ref. The root app
`.env.*` files are only local reference values and are not read by deployed edge
functions. Current example value:
`http://localhost:*,http://127.0.0.1:*,https://*.lovableproject.com,https://*.lovable.app`

**Solution: Implement Token Refresh Before Authenticated Calls**

```typescript
// Before making authenticated API calls, ensure the token is fresh
async function ensureValidToken() {
  const {
    data: { session },
    error,
  } = await supabase.auth.getSession();

  if (error || !session) {
    throw new Error("Not authenticated");
  }

  // Check if token expires within next 60 seconds
  const expiresAt = session.expires_at * 1000; // Convert to milliseconds
  const bufferMs = 60 * 1000; // 60 second buffer

  if (Date.now() > expiresAt - bufferMs) {
    // Token is about to expire, refresh it
    const { data, error: refreshError } = await supabase.auth.refreshSession();
    if (refreshError) {
      throw new Error("Failed to refresh session");
    }
    return data.session?.access_token;
  }

  return session.access_token;
}

// Usage in checkout
async function initiateCheckout(productId: string) {
  const token = await ensureValidToken();

  const response = await fetch(
    `${SUPABASE_URL}/functions/v1/plan-api/orders/${productId}/checkout`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        apikey: SUPABASE_ANON_KEY, // Must be lowercase 'apikey'
        Authorization: `Bearer ${token}`,
        "x-tenant-slug": TENANT_SLUG,
      },
      body: JSON.stringify({
        success_url: window.location.origin + "/checkout/success",
        cancel_url: window.location.origin + "/checkout/cancel",
      }),
    },
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error?.message || "Checkout failed");
  }

  return response.json();
}
```

**Key Points:**

- Always use lowercase `apikey` header (not `apiKey`)
- Do not send `apikey` to `GET/POST /migration-status`; that endpoint validates
  only the `Authorization` bearer MD5 hash against server-configured anon keys
- Refresh tokens proactively before they expire
- Handle 401 errors by prompting re-authentication
- Tokens expire after 1 hour by default

### Request Headers Reference

For authenticated endpoints, always include:

| Header          | Value                   | Notes                    |
| --------------- | ----------------------- | ------------------------ |
| `apikey`        | `<supabase-anon-key>`   | **Must be lowercase**    |
| `Authorization` | `Bearer <access_token>` | Fresh, non-expired token |
| `x-tenant-slug` | `<tenant-slug>`         | Tenant identifier        |
| `Content-Type`  | `application/json`      | For POST/PATCH requests  |

`GET/POST /migration-status` is the exception: send `Authorization:
Bearer <md5-of-supabase-api-publishable-key>` and `x-tenant-slug`, but do not
send `apikey`.

---

## Stripe Webhook Integration

The platform includes a dedicated webhook endpoint for processing Stripe payment
events. This is used internally and should be configured in the Stripe
Dashboard.

### Persistence Behavior

- Lifecycle renewal state is persisted on `subscriptions` and linked via
  `orders.subscription_id`.
- Lifecycle expiration state is persisted on `subscriptions.expires_at`.
- Provider-specific lifecycle identifiers are persisted to
  `subscription_payment_provider_links`.
- Provider-specific transaction identifiers/status are persisted to
  `order_payment_provider_transactions`.
- Patient order APIs read lifecycle renewal from the linked subscription
  (`subscriptions.current_period_end_at`), not legacy order lifecycle columns.

### Webhook URL

```
VITE_SUPABASE_URL/functions/v1/stripe-webhook
```

### Supported Events

| Event Type                      | Description                 | Action                           |
| ------------------------------- | --------------------------- | -------------------------------- |
| `checkout.session.completed`    | Customer completed checkout | Creates order in database        |
| `checkout.session.expired`      | Checkout session timed out  | Logs for abandoned cart tracking |
| `payment_intent.succeeded`      | Payment was successful      | Confirmation logging             |
| `payment_intent.payment_failed` | Payment failed              | Error logging                    |
| `customer.subscription.created` | New subscription started    | Logs subscription details        |
| `customer.subscription.updated` | Subscription modified       | Logs status changes              |
| `customer.subscription.deleted` | Subscription cancelled      | Logs cancellation                |
| `invoice.paid`                  | Subscription invoice paid   | Logs recurring payment           |
| `invoice.payment_failed`        | Subscription payment failed | Error logging                    |

### Configuration

1. **Add Webhook in Stripe Dashboard:**
   - Go to Developers → Webhooks → Add Endpoint
   - Enter the webhook URL above
   - Select the events listed above

2. **Tenant Settings Structure:**
   ```json
   {
     "secret_key": "sk_live_..."
   }
   ```

### Signature Verification

Tenant Stripe settings no longer store Stripe webhook signing secrets. If a
legacy tenant still has one configured, the webhook handler verifies Stripe
signatures using HMAC-SHA256 and rejects invalid or expired signatures.

### Checkout Authentication Requirement

Stripe checkout sessions must be started by authenticated patients. The Stripe
webhook does not create users.

### Metadata Requirements

For the webhook to process events correctly, checkout sessions must include
these metadata fields (automatically added by the checkout endpoint):

- `tenant_id` - Required for routing to correct tenant configuration
- `patient_id` - Links order to patient
- `product_id` - Identifies the purchased product

---

## Changelog

| Version | Date     | Changes                                                                                                                                                                                                                                                  |
| ------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1.6.6   | Jul 2026 | `GET/POST /migration-status` validates the bearer MD5 hash against server-configured Supabase anon keys (`SUPABASE_ANON_KEY`/`SUPABASE_ANON_KEYS`) and does not use the `apikey` header                                                                 |
| 1.6.5   | Jul 2026 | Added signup marketing opt-in via `subscribe_to_email_and_sms_marketing`; `GET /auth/me` returns separate `subscribed_to_email_marketing` and `subscribed_to_sms_marketing` settings, and `PATCH /auth/me` can update them                            |
| 1.6.4   | Jun 2026 | `privacy_policy_version_id` is required during signup and must match the live privacy policy version                                                                                                                                                     |
| 1.6.4   | Jun 2026 | Clarified that current provider messaging details live in `MessengerAPI.md`, including MDI patient messages and badge polling via `/mdi-patient-chat/status`                                                                                             |
| 1.6.3   | May 2026 | Signup no longer uses patient-supplied passwords; `POST /auth/signup` always generates the password server-side and emails it to the patient                                                                                                             |
| 1.6.2   | May 2026 | `privacy_policy_version_id` is optional during signup; when supplied, it is validated against the live privacy policy and records acceptance                                                                                                             |
| 1.6.1   | May 2026 | `privacy_policy_version_id` validation was added to signup; `product_id` is optional and only validated when supplied                                                                                                                                    |
| 1.6.0   | May 2026 | Added tenant privacy policy endpoints `GET /privacy-policy/latest`, `GET /privacy-policy/acceptance-status`, and `POST /privacy-policy/accept`; signup may record `privacy_policy_version_id` when supplied                                              |
| 1.5.0   | May 2026 | Tenant terms are now managed per tenant; `GET /terms-and-conditions/latest` resolves by tenant slug, signup/acceptance use `tenant_terms_version_id`, and `platform_terms_version_id` remains a legacy alias while clients migrate                       |
| 1.4.0   | Apr 2026 | Added platform-wide terms endpoints `GET /terms-and-conditions/latest`, `GET /terms-and-conditions/acceptance-status`, and `POST /terms-and-conditions/accept`; documented required `platform_terms_version_id` during patient signup                    |
| 1.3.0   | Mar 2026 | Moved chat-thread endpoints out of `patient-api` into dedicated messenger endpoint `GET/POST /messenger-api/telegra-clinical-chat`; retained Telegra chat normalization and send-message behavior                                                        |
| 1.2.0   | Mar 2026 | Added chat messaging endpoint `POST /patient-api/chat-threads`; documented Telegra `sendMessage` integration; clarified chat-thread normalization when Telegra returns a single `channel` object                                                         |
| 1.1.0   | Feb 2026 | Added subscription lifecycle ownership (`orders.subscription_id`), provider-agnostic payment entities (`subscription_payment_provider_links`, `order_payment_provider_transactions`), and documented no-legacy-fallback renewal reads from subscriptions |
| 1.0.3   | Feb 2026 | Updated address fields to use nested objects (address, shipping_address, billing_address)                                                                                                                                                                |
| 1.0.2   | Jan 2026 | Added Stripe webhook documentation                                                                                                                                                                                                                       |
| 1.0.1   | Jan 2026 | Added CORS troubleshooting section                                                                                                                                                                                                                       |
| 1.0.0   | Jan 2026 | Initial release                                                                                                                                                                                                                                          |

---

## Support

For API issues or questions, contact the platform team.
