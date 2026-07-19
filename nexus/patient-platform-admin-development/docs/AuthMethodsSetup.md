# Auth Methods Setup (dashboard / cloud config)

Patient sign-in supports four methods: **email + password**, **passwordless
email OTP**, **social login (Google, later Apple)**, and **passkeys /
biometrics**. The code ships in `patient-api` (backend) and the patient UI, but
each method also needs configuration that lives **outside the repo** (Supabase
dashboard, Google Cloud, Resend). Do these once per Supabase project.

> Dev project ref: `sunzxjnbgtknqeivljtd`
> Dev patient app origin: `https://carelink-dev.alliahealthgroup.com`

API behavior for every method is documented in
[PatientAPI.md → Authentication](./PatientAPI.md#authentication). All methods
resolve to the **same tenant session tokens**.

---

## Passwordless Email OTP

Sends a single-use 6-digit code by email. No dashboard provider needed — the
backend generates and verifies the code itself — but it has two dependencies:

1. **Migration applied.** The `patient_auth_otps` table
   (`supabase/migrations/20260617120000_create_patient_auth_otps.sql`) must be
   applied. Migrations run on merge to `dev`/`development`, not on feature
   branches — so the OTP endpoints return `500 OTP_ERROR` until the migration
   lands on the deployed environment.
2. **Tenant email distribution configured.** The code email is sent via the
   tenant's Resend config (`tenant_integrations.settings.api_key`), so the
   tenant must have email distribution set up. See
   [Backend.md](./Backend.md) email-distribution notes.

Security parameters (in `patient-api/index.ts`):

| Constant                               | Value      |
| -------------------------------------- | ---------- |
| `PATIENT_AUTH_OTP_TTL_MS`              | 10 minutes |
| `PATIENT_AUTH_OTP_MAX_ATTEMPTS`        | 5 per code |
| `PATIENT_AUTH_OTP_MAX_REQUESTS_PER_HOUR` | 5 per email |
| `PATIENT_AUTH_OTP_CODE_LENGTH`         | 6 digits   |

Codes are stored hashed (`sha256(tenant_id:email:code)`), single-use (`used_at`),
and prior unused codes are invalidated on each new request.

---

## Google Sign-In

### 1. Google Cloud — create OAuth credentials

1. Google Cloud Console → **APIs & Services → Credentials**.
2. **Create OAuth client ID** → Application type **Web application**.
3. **Authorized redirect URIs** — add the Supabase callback:
   `https://sunzxjnbgtknqeivljtd.supabase.co/auth/v1/callback`
4. Copy the **Client ID** and **Client secret**.
5. Configure the OAuth consent screen if not already done (app name, support
   email, scopes: `email` + `profile`).

### 2. Supabase — enable the Google provider

1. Supabase Dashboard → **Authentication → Providers → Google**.
2. Enable it; paste the **Client ID** + **Client secret** from step 1. Save.

### 3. Supabase — allowed redirect URLs

Authentication → **URL Configuration**:

- **Site URL**: the patient app URL, e.g.
  `https://carelink-dev.alliahealthgroup.com`
- **Redirect URLs** (allow list): add the app callback page
  `https://carelink-dev.alliahealthgroup.com/auth/callback`
  (and any other env URLs, e.g. `http://localhost:8080/auth/callback` for local).

### How the flow works

1. User clicks **Continue with Google** → `signInWithOAuth({ provider:
   'google', redirectTo: <origin>/auth/callback })` → Google consent in the
   browser.
2. Google → Supabase callback → back to `/auth/callback` with an OAuth session.
3. `/auth/callback` calls **`POST /auth/oauth/resolve`** with the OAuth session
   token. The backend verifies the email and resolves the patient in **this
   tenant** by `(tenant, email)`:
   - **Match** → links `auth_user_id`, **stamps `patients.email_verified_at`**
     (a Google session whose email maps to the patient proves ownership —
     equivalent to an OTP verify) and releases any orders held by the
     contact-validation gate, then returns a tenant session (with
     `email_verified: true`) → dashboard.
   - **No patient for that email in this tenant** → **blocked** with
     `NO_ACCOUNT` (no auto-create — the account-first checkout creates patients).

Mid-checkout Google ("Sign up with Google" in step 2) uses this **same**
`signInWithOAuth` + resolve flow — with `login_hint` preselecting the purchase
email — and a sessionStorage resume stash so `/auth/callback` returns the buyer
into `/products/:id/checkout?resumeOrder=<orderId>` instead of the dashboard.
Because resolve verifies the email, the Google path skips the email-OTP
sub-step entirely (see `docs/Checkout.md`). The earlier `supabase.auth
.linkIdentity` approach was removed: it required the per-project **"Allow
manual linking"** toggle (off by default) and risked consuming the app's
refresh token via `setSession` — the reason Google worked on the login page
but not in checkout.

After enabling the provider + redirect URLs, **no redeploy** is needed for
config (dashboard-side), but the UI build must include the Google button and the
OTP migration must be applied.

---

## Apple Sign-In (deferred)

Apple is **not yet enabled** — it needs an Apple Developer account + Service ID
and key. The resolve endpoint is **provider-agnostic**, so Apple slots in the
same way once its provider is enabled in the Supabase dashboard:

1. Create an Apple **Service ID** + **Sign in with Apple** key (Apple Developer).
2. Supabase → **Authentication → Providers → Apple** → enable, paste credentials.
3. No code changes — the UI adds an "Continue with Apple" button that calls
   `signInWithOAuth({ provider: 'apple' })`; `/auth/oauth/resolve` handles the
   rest.

---

## Passkeys / Biometrics

Passkeys use **Supabase native WebAuthn** (Beta). Like Google/Apple, the sign-in
session is exchanged for a tenant session via `/auth/oauth/resolve` — so this is
**UI + dashboard only**, no new backend endpoint.

### Requirements

- `@supabase/supabase-js` ≥ 2.105 with `auth.experimental.passkey: true`
  (already set on the patient UI's `supabaseAuthClient`).
- A device with a **platform authenticator** (Face ID, Touch ID, fingerprint,
  Windows Hello, or a device/cloud passkey).

### Dashboard config

1. Supabase Dashboard → **Authentication** → enable **Passkeys** (Beta).
2. Add the patient app origin(s) to the passkey **allowed RP origins**:
   - `https://carelink-dev.alliahealthgroup.com`
   - any other env origins (prod, staging, `http://localhost:8080` for local).

### How the flow works

- **Enroll** — in the post-questionnaire "Set up account access" step, the
  signed-in patient taps **Use biometrics / passkey**. The patient's tenant
  session tokens (Supabase JWTs) are loaded onto the Supabase client via
  `setSession`, then `registerPasskey()` runs the WebAuthn registration.
- **Sign in** — on the login page, **Sign in with passkey** calls
  `signInWithPasskey()` (no email needed); the resulting Supabase session is
  resolved to a tenant session via `/auth/oauth/resolve` (same `NO_ACCOUNT` /
  `ACCOUNT_INACTIVE` rules apply).

---

## Where the password fits (PP-566 Option 2)

Under the PP-566 sign-up flow update, the **password step is optional and moved
to after the questionnaires** — embedded in the **Provider Update** step as an
optional "Set up account access" card (`AccountAccessSetup`). The patient is
already authenticated by then (signup issues a session with a temporary
password), so the card just lets them **choose** how to secure the account:
set a password, or **enroll a passkey**. They can skip and still proceed,
recovering later via OTP / Google / passkey.

See [SequenceDiagrams.md → Patient Sign Up Flow](./SequenceDiagrams.md#patient-sign-up-flow).
