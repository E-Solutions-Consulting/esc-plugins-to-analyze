// Patient API Edge Function
// Provides public/authenticated endpoints for Patient UI consumption

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  generateAuthenticationOptions,
  generateRegistrationOptions,
  verifyAuthenticationResponse,
  verifyRegistrationResponse,
} from "npm:@simplewebauthn/server@13.3.2";
import type {
  AuthenticationResponseJSON,
  AuthenticatorTransportFuture,
  RegistrationResponseJSON,
} from "npm:@simplewebauthn/server@13.3.2";
import { dateTime } from "../_shared/dayjs.ts";
import { sendEmailViaTenantDistribution } from "../_shared/email-distribution.ts";
import { getDeploymentEnvironment } from "../_shared/environment.ts";
import {
  dollarsFromCents,
  formatCurrency,
  getFriendbuyLedgerBalance,
  getPatientReferrals,
  getPendingFriendbuyRewardTotal,
  normalizeFriendbuyAttribution,
  sendFriendbuySignupEvent,
} from "../_shared/friendbuy.ts";
import {
  cancelNotification,
  getOneSignalConfig,
  scheduleNotification,
  scheduleNotificationWithResult,
} from "../_shared/onesignal.ts";
import {
  buildScheduleSummary,
  calculateOccurrences,
  deriveReminderTitle,
} from "../_shared/reminder-schedule.ts";
import {
  checkRateLimit,
  derivePatientMigrationInfo,
  getBearerToken,
  getCorsHeaders,
  getDefaultPatientPassword,
  getSupabaseAnonKeyCandidates,
  intercomUserHash,
  isSupabaseAnonKeyHash,
  isValidEmail,
  isValidPassword,
  isValidUsPhoneWithoutCountryCode,
  mapDurablePatientNotification,
  normalizeTenantSlug,
  normalizeUsPhoneDigits,
  type PatientNotificationResponse,
  sortPatientNotificationsByRecency,
  US_STATE_CODES,
  validateEmailDomainAgainstTenant,
  validateStateAgainstTenant,
} from "./helpers.ts";

function escapeHtml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function buildPatientPasswordResetEmail(params: {
  tenantName: string | null;
  recoveryLink: string;
}): { subject: string; html: string } {
  const { tenantName, recoveryLink } = params;
  const safeTenantName = tenantName?.trim() || "your care team";
  const safeRecoveryLink = escapeHtml(recoveryLink);
  const safeDisplayTenantName = escapeHtml(safeTenantName);

  return {
    subject: `${safeTenantName} Patient Password Reset`,
    html: `
      <div style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827;">
        <h2 style="margin: 0 0 16px;">Reset your password</h2>
        <p style="margin: 0 0 16px;">
          We received a request to reset your password for ${safeDisplayTenantName}.
        </p>
        <p style="margin: 0 0 24px;">
          Click the button below to choose a new password.
        </p>
        <p style="margin: 0 0 24px;">
          <a
            href="${safeRecoveryLink}"
            style="display: inline-block; padding: 12px 20px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px;"
          >
            Reset password
          </a>
        </p>
        <p style="margin: 0 0 16px;">
          If you did not request this, you can ignore this email.
        </p>
        <p style="margin: 0; font-size: 14px; color: #6b7280;">
          If the button does not work, copy and paste this link into your browser:<br />
          <a href="${safeRecoveryLink}">${safeRecoveryLink}</a>
        </p>
      </div>
    `,
  };
}

const PATIENT_PASSWORD_RESET_TOKEN_TTL_MS = 1000 * 60 * 60;

// --- Email OTP (passwordless sign-in) -------------------------------------
const PATIENT_AUTH_OTP_TTL_MS = 1000 * 60 * 10; // 10 minutes
const PATIENT_AUTH_OTP_MAX_ATTEMPTS = 5; // per code
const PATIENT_AUTH_OTP_MAX_REQUESTS_PER_HOUR = 5; // per email
const PATIENT_AUTH_OTP_CODE_LENGTH = 6;
const PATIENT_PASSKEY_CHALLENGE_TTL_MS = 1000 * 60 * 5;
const PATIENT_PASSKEY_FEATURE_FLAG_KEY = "passkey_authentication";

type PasskeyCeremonyType = "registration" | "authentication";

type TenantPasskeyConfig = {
  rp_id: string;
  rp_name: string;
  allowed_origins: string[];
};

type PatientPasskeyRow = {
  id: string;
  tenant_id: string;
  patient_id: string;
  credential_id: string;
  credential_public_key: string;
  counter: number;
  transports: AuthenticatorTransportFuture[] | null;
  patient?: {
    id: string;
    auth_user_id: string | null;
    access_status: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
  } | null;
};

/**
 * Test/non-live account domains. Real patients never use these; they are the
 * codebase's "non-live test account" signal (also used to skip the generated
 * password email). For these, OTP codes may be returned in the API response so
 * automated tests can verify email without an inbox — never for real domains.
 */
function isTestDomainEmail(email: string | null | undefined): boolean {
  const e = (email ?? "").toLowerCase().trim();
  return (
    e.endsWith("@example.com") ||
    e.endsWith("@dev.com") ||
    e.endsWith("@staging.com") ||
    e.endsWith("@stg.com")
  );
}

/**
 * Re-run the order lifecycle for a patient's orders that the contact-validation
 * gate is holding pre-provider (PP-566). Verifying email stamps the patient, but
 * the gate only re-evaluates when the lifecycle runs — so when email becomes
 * verified we nudge any held orders to advance toward provider intake. This makes
 * advancement reliable regardless of which UI path verified the email (it no
 * longer depends solely on the checkout step calling /orders/:id/resume).
 *
 * Best-effort and fire-and-forget per order; failures are logged, never thrown.
 */
async function resumeHeldOrdersForPatient(params: {
  // deno-lint-ignore no-explicit-any
  supabaseAdmin: any;
  supabaseUrl: string;
  serviceRoleKey: string;
  tenantId: string;
  patientId: string;
}): Promise<void> {
  const { supabaseAdmin, supabaseUrl, serviceRoleKey, tenantId, patientId } =
    params;
  try {
    // Orders held before provider creation: order_created and
    // shipping_details_required are the states the gate can release.
    const { data: heldOrders } = await supabaseAdmin
      .from("orders")
      .select("id, order_statuses!inner ( status_key )")
      .eq("tenant_id", tenantId)
      .eq("patient_id", patientId)
      .in("order_statuses.status_key", [
        "order_created",
        "shipping_details_required",
      ]);

    for (const order of (heldOrders ?? []) as Array<{ id: string }>) {
      try {
        await fetch(
          `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${order.id}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${serviceRoleKey}`,
              "Content-Type": "application/json",
              "x-request-source": "patient-api:email-verified-resume",
            },
          },
        );
      } catch (e) {
        console.warn("resumeHeldOrdersForPatient: lifecycle trigger failed", {
          orderId: order.id,
          error: e instanceof Error ? e.message : String(e),
        });
      }
    }
  } catch (e) {
    console.warn("resumeHeldOrdersForPatient: could not load held orders", {
      patientId,
      error: e instanceof Error ? e.message : String(e),
    });
  }
}

/** Cryptographically-random numeric OTP (no modulo bias). */
function generateNumericOtp(length = PATIENT_AUTH_OTP_CODE_LENGTH): string {
  let code = "";
  while (code.length < length) {
    const r = crypto.getRandomValues(new Uint32Array(1))[0];
    // Reject the tail that would bias the distribution.
    if (r < 4294967290) code += (r % 10).toString();
  }
  return code;
}

function generatePasswordResetToken(bytes = 32): string {
  return Array.from(crypto.getRandomValues(new Uint8Array(bytes)))
    .map((value) => value.toString(16).padStart(2, "0"))
    .join("");
}

async function sha256Hex(value: string): Promise<string> {
  const digest = await crypto.subtle.digest(
    "SHA-256",
    new TextEncoder().encode(value),
  );

  return Array.from(new Uint8Array(digest))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

async function sha256Bytes(value: string): Promise<Uint8Array<ArrayBuffer>> {
  const digest = await crypto.subtle.digest(
    "SHA-256",
    new TextEncoder().encode(value),
  );

  return new Uint8Array(digest as ArrayBuffer);
}

function bytesToBase64Url(bytes: Uint8Array): string {
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary)
    .replaceAll("+", "-")
    .replaceAll("/", "_")
    .replaceAll("=", "");
}

function base64UrlToBytes(value: string): Uint8Array<ArrayBuffer> {
  const normalized = value.replaceAll("-", "+").replaceAll("_", "/");
  const padded = normalized.padEnd(
    normalized.length + ((4 - (normalized.length % 4)) % 4),
    "=",
  );
  const binary = atob(padded);
  const bytes = new Uint8Array(binary.length) as Uint8Array<ArrayBuffer>;
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes;
}

function base64UrlToUtf8(value: string): string {
  return new TextDecoder().decode(base64UrlToBytes(value));
}

function normalizePasskeyOrigin(value: string): string | null {
  const parsedUrl = parseAbsoluteHttpUrl(value);
  if (!parsedUrl) return null;
  return parsedUrl.origin;
}

function parseWebAuthnChallenge(
  response: RegistrationResponseJSON | AuthenticationResponseJSON,
): string | null {
  try {
    const clientDataJson = base64UrlToUtf8(response.response.clientDataJSON);
    const clientData = JSON.parse(clientDataJson) as { challenge?: unknown };
    return typeof clientData.challenge === "string"
      ? clientData.challenge
      : null;
  } catch {
    return null;
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function getPasskeyCredentialPayload(parsedBody: unknown): unknown {
  if (!isRecord(parsedBody)) return parsedBody;
  if (isRecord(parsedBody.credential)) return parsedBody.credential;
  if (isRecord(parsedBody.response)) return parsedBody.response;
  return parsedBody;
}

function getPasskeyChallengeId(parsedBody: unknown): string | null {
  if (!isRecord(parsedBody)) return null;
  return typeof parsedBody.challenge_id === "string"
    ? parsedBody.challenge_id
    : null;
}

function parseAbsoluteHttpUrl(value: string): URL | null {
  try {
    const parsedUrl = new URL(value);
    if (!["http:", "https:"].includes(parsedUrl.protocol)) {
      return null;
    }
    return parsedUrl;
  } catch {
    return null;
  }
}

function buildPatientPasswordResetUrl(
  redirectUrl: string,
  resetToken: string,
): string {
  const url = new URL(redirectUrl);
  url.searchParams.set("reset_token", resetToken);
  return url.toString();
}

function maskEmail(email: string): string {
  const [localPart = "", domainPart = ""] = email
    .trim()
    .toLowerCase()
    .split("@");
  if (!domainPart) return "***";
  if (localPart.length <= 2) {
    return `${localPart.slice(0, 1) || "*"}***@${domainPart}`;
  }
  return `${localPart.slice(0, 2)}***@${domainPart}`;
}

function normalizeErrorForLog(error: unknown): Record<string, unknown> {
  if (error instanceof Error) {
    return {
      name: error.name,
      message: error.message,
      stack: error.stack,
    };
  }

  return { error };
}

Deno.serve(async (req) => {
  const corsHeaders = getCorsHeaders(req);

  function jsonResponse(
    data: unknown,
    status = 200,
    headers: Record<string, string> = {},
  ) {
    return new Response(JSON.stringify(data), {
      status,
      headers: {
        ...corsHeaders,
        "Content-Type": "application/json",
        ...headers,
      },
    });
  }

  function errorResponse(code: string, message: string, status = 400) {
    return jsonResponse({ error: { code, message } }, status);
  }

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const url = new URL(req.url);
  // Normalize path so the router works consistently across environments.
  // Depending on the gateway/runtime, the function may see either:
  // - /patient-api/products
  // - /functions/v1/patient-api/products
  const pathname = url.pathname;
  let path = pathname;
  path = path.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/patient-api")) {
    path = path.slice("/patient-api".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) path = path.slice(0, -1);
  const requestId = req.headers.get("x-request-id") || crypto.randomUUID();

  // Rate limiting
  const clientIp = req.headers.get("x-forwarded-for") || "unknown";
  const rateCheck = checkRateLimit(clientIp);

  if (!rateCheck.allowed) {
    return errorResponse(
      "RATE_LIMIT_EXCEEDED",
      "Too many requests. Please try again later.",
      429,
    );
  }

  // Create Supabase clients
  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
  const supabaseServiceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const authHeader = req.headers.get("Authorization");
  const supabaseAnonKeyCandidates = getSupabaseAnonKeyCandidates(
    supabaseAnonKey,
    Deno.env.get("SUPABASE_ANON_KEYS"),
  );

  // Regular client for authenticated requests
  const supabase = createClient(supabaseUrl, supabaseAnonKey, {
    global: {
      headers: authHeader ? { Authorization: authHeader } : {},
    },
  });

  // Service role client for admin operations (signup, password reset)
  const supabaseAdmin = createClient(supabaseUrl, supabaseServiceRoleKey, {
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });

  // Extract tenant slug from header or query param
  const headerTenantSlug = normalizeTenantSlug(
    req.headers.get("x-tenant-slug"),
  );
  const queryTenantSlug = normalizeTenantSlug(
    url.searchParams.get("tenant_slug"),
  );
  const tenantSlugs = Array.from(
    new Set([headerTenantSlug, queryTenantSlug].filter(Boolean) as string[]),
  );

  // Debug logging for troubleshooting external calls
  console.log("Patient API Request Debug:", {
    requestId,
    method: req.method,
    path,
    headerTenantSlug,
    queryTenantSlug,
    tenantSlugs,
    origin: req.headers.get("origin"),
    secFetchCredentials: req.headers.get("sec-fetch-credentials"),
    hasCookie: Boolean(req.headers.get("cookie")),
  });

  async function getActiveTenant<T extends string>(select: T) {
    for (const slug of tenantSlugs) {
      const { data, error } = await supabaseAdmin
        .from("tenants")
        .select(select)
        .eq("slug", slug)
        .eq("status", "active")
        .maybeSingle();

      if (error) throw error;
      if (data) return data;
    }
    return null;
  }

  async function getTenantPasskeyConfig(
    tenantId: string,
  ): Promise<TenantPasskeyConfig | null> {
    const environment = getDeploymentEnvironment(supabaseUrl);

    if (!environment) return null;

    let { data, error } = await supabaseAdmin
      .from("tenant_passkey_configs")
      .select("rp_id, rp_name, allowed_origins, is_enabled")
      .eq("tenant_id", tenantId)
      .eq("environment", environment)
      .eq("is_enabled", true);

    // Supports deploying patient-api before the environment column migration.
    if (
      error &&
      (error.code === "42703" ||
        (error.code === "PGRST204" && error.message.includes("environment")))
    ) {
      const legacyResult = await supabaseAdmin
        .from("tenant_passkey_configs")
        .select("rp_id, rp_name, allowed_origins, is_enabled")
        .eq("tenant_id", tenantId)
        .eq("is_enabled", true)
        .maybeSingle();

      data = legacyResult.data ? [legacyResult.data] : [];
      error = legacyResult.error;
    }

    if (error) throw error;
    if (!data?.length) return null;

    const requestOrigin = req.headers.get("origin");
    const normalizedRequestOrigin = requestOrigin
      ? normalizePasskeyOrigin(requestOrigin)
      : null;
    let fallbackConfig: TenantPasskeyConfig | null = null;

    for (const row of data) {
      const rpId = String(row.rp_id ?? "").trim().toLowerCase();
      const rpName = String(row.rp_name ?? "").trim();
      const allowedOrigins = Array.isArray(row.allowed_origins)
        ? row.allowed_origins
          .map((origin: unknown) =>
            typeof origin === "string" ? normalizePasskeyOrigin(origin) : null
          )
          .filter(Boolean) as string[]
        : [];

      if (rpId && rpName && allowedOrigins.length > 0) {
        const config = {
          rp_id: rpId,
          rp_name: rpName,
          allowed_origins: Array.from(new Set(allowedOrigins)),
        };

        fallbackConfig ??= config;
        if (
          normalizedRequestOrigin &&
          allowedOrigins.includes(normalizedRequestOrigin)
        ) {
          return config;
        }
      }
    }

    return fallbackConfig;
  }

  async function isTenantFeatureEnabled(
    tenantId: string,
    featureKey: string,
  ): Promise<boolean> {
    const { data: featureFlag, error: featureFlagError } = await supabaseAdmin
      .from("feature_flags")
      .select("id, default_value, is_active")
      .eq("key", featureKey)
      .maybeSingle();

    if (featureFlagError) throw featureFlagError;
    if (!featureFlag?.is_active) return false;

    const { data: override, error: overrideError } = await supabaseAdmin
      .from("tenant_feature_flag_overrides")
      .select("enabled")
      .eq("tenant_id", tenantId)
      .eq("feature_flag_id", featureFlag.id)
      .maybeSingle();

    if (overrideError) throw overrideError;
    return override?.enabled ?? Boolean(featureFlag.default_value);
  }

  async function requirePasskeyFeature(tenantId: string) {
    const isEnabled = await isTenantFeatureEnabled(
      tenantId,
      PATIENT_PASSKEY_FEATURE_FLAG_KEY,
    );

    return isEnabled ? null : errorResponse(
      "PASSKEYS_DISABLED",
      "Passkey authentication is disabled for this tenant",
      403,
    );
  }

  function validatePasskeyRequestOrigin(config: TenantPasskeyConfig) {
    const requestOrigin = req.headers.get("origin");
    const normalizedOrigin = requestOrigin
      ? normalizePasskeyOrigin(requestOrigin)
      : null;

    if (
      !normalizedOrigin || !config.allowed_origins.includes(normalizedOrigin)
    ) {
      return {
        origin: null,
        error: errorResponse(
          "INVALID_ORIGIN",
          "Origin is not allowed for passkey ceremonies",
          403,
        ),
      };
    }

    return { origin: normalizedOrigin, error: null };
  }

  async function storePasskeyChallenge(params: {
    tenantId: string;
    patientId?: string | null;
    type: PasskeyCeremonyType;
    challenge: string;
    origin: string;
    rpId: string;
    webauthnUserId?: string | null;
  }): Promise<{ id: string } | null> {
    const expiresAt = new Date(Date.now() + PATIENT_PASSKEY_CHALLENGE_TTL_MS)
      .toISOString();
    const { data, error } = await supabaseAdmin
      .from("patient_passkey_challenges")
      .insert({
        tenant_id: params.tenantId,
        patient_id: params.patientId ?? null,
        ceremony_type: params.type,
        challenge: params.challenge,
        origin: params.origin,
        rp_id: params.rpId,
        webauthn_user_id: params.webauthnUserId ?? null,
        expires_at: expiresAt,
      })
      .select("id")
      .single();

    if (error) {
      console.error("Passkey challenge insert failed", error);
      return null;
    }

    return data as { id: string };
  }

  async function consumePasskeyChallenge(params: {
    tenantId: string;
    type: PasskeyCeremonyType;
    challenge: string;
    challengeId?: string | null;
  }) {
    const nowIso = new Date().toISOString();
    let query = supabaseAdmin
      .from("patient_passkey_challenges")
      .update({ used_at: nowIso })
      .eq("tenant_id", params.tenantId)
      .eq("ceremony_type", params.type)
      .eq("challenge", params.challenge)
      .is("used_at", null)
      .gt("expires_at", nowIso);

    if (params.challengeId) {
      query = query.eq("id", params.challengeId);
    }

    const { data, error } = await query
      .select("id, patient_id, challenge, origin, rp_id, webauthn_user_id")
      .maybeSingle();

    if (error) {
      console.error("Passkey challenge consume failed", error);
      return null;
    }

    return data;
  }

  async function issuePatientSessionForEmail(params: {
    email: string;
    patient: {
      id: string;
      first_name: string | null;
      last_name: string | null;
    };
    logContext: string;
  }) {
    const { data: linkData, error: linkError } = await supabaseAdmin.auth.admin
      .generateLink({ type: "magiclink", email: params.email });
    const hashedToken = linkData?.properties?.hashed_token;
    if (linkError || !hashedToken) {
      console.error(`${params.logContext} generateLink failed`, linkError);
      return null;
    }

    const { data: verifyData, error: verifyError } = await supabase.auth
      .verifyOtp({ type: "magiclink", token_hash: hashedToken });
    if (verifyError || !verifyData.session) {
      console.error(`${params.logContext} verifyOtp failed`, verifyError);
      return null;
    }

    return {
      access_token: verifyData.session.access_token,
      refresh_token: verifyData.session.refresh_token,
      expires_in: verifyData.session.expires_in,
      expires_at: verifyData.session.expires_at,
      user: {
        id: verifyData.user?.id,
        email: verifyData.user?.email,
        first_name: params.patient.first_name,
        last_name: params.patient.last_name,
        patient_id: params.patient.id,
      },
    };
  }

  type LiveTenantTermsVersion = {
    id: string;
    tenant_id: string;
    version: number;
    content: string;
    published_at: string | null;
  };

  async function getLiveTenantTermsVersion(tenantId: string) {
    const { data, error } = await supabaseAdmin
      .from("platform_terms_versions")
      .select("id, tenant_id, version, content, published_at")
      .eq("tenant_id", tenantId)
      .eq("is_live", true)
      .maybeSingle();

    if (error) {
      console.error("Live tenant terms lookup error:", error);
      throw error;
    }

    return data as LiveTenantTermsVersion | null;
  }

  type LiveTenantPrivacyPolicyVersion = {
    id: string;
    tenant_id: string;
    version: number;
    content: string;
    published_at: string | null;
  };

  async function getLiveTenantPrivacyPolicyVersion(tenantId: string) {
    const { data, error } = await supabaseAdmin
      .from("privacy_policy_versions")
      .select("id, tenant_id, version, content, published_at")
      .eq("tenant_id", tenantId)
      .eq("is_live", true)
      .maybeSingle();

    if (error) {
      console.error("Live tenant privacy policy lookup error:", error);
      throw error;
    }

    return data as LiveTenantPrivacyPolicyVersion | null;
  }

  function generateAutoPassword(length = 12): string {
    const uppercase = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    const lowercase = "abcdefghijkmnopqrstuvwxyz";
    const digits = "23456789";
    const allChars = `${uppercase}${lowercase}${digits}`;

    const randomChar = (source: string): string => {
      const randomIndex = crypto.getRandomValues(new Uint32Array(1))[0] %
        source.length;
      return source[randomIndex];
    };

    const characters = [
      randomChar(uppercase),
      randomChar(lowercase),
      randomChar(digits),
    ];

    for (let i = characters.length; i < length; i += 1) {
      characters.push(randomChar(allChars));
    }

    for (let i = characters.length - 1; i > 0; i -= 1) {
      const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
      const temp = characters[i];
      characters[i] = characters[j];
      characters[j] = temp;
    }

    return characters.join("");
  }

  function decodeHtmlEntities(input: string): string {
    return input
      .replace(/&nbsp;/gi, " ")
      .replace(/&amp;/gi, "&")
      .replace(/&lt;/gi, "<")
      .replace(/&gt;/gi, ">")
      .replace(/&quot;/gi, '"')
      .replace(/&#39;|&apos;/gi, "'")
      .replace(/&#(\d+);/g, (_, decimal) => {
        const codePoint = Number.parseInt(decimal, 10);
        if (Number.isNaN(codePoint)) return _;
        try {
          return String.fromCodePoint(codePoint);
        } catch {
          return _;
        }
      })
      .replace(/&#x([0-9a-fA-F]+);/g, (_, hex) => {
        const codePoint = Number.parseInt(hex, 16);
        if (Number.isNaN(codePoint)) return _;
        try {
          return String.fromCodePoint(codePoint);
        } catch {
          return _;
        }
      });
  }

  function normalizeTermsText(input: string | null | undefined): string {
    if (!input) return "";

    const withoutScripts = input.replace(/<script[\s\S]*?<\/script>/gi, " ");
    const withoutStyles = withoutScripts.replace(
      /<style[\s\S]*?<\/style>/gi,
      " ",
    );
    const withoutTags = withoutStyles.replace(/<[^>]*>/g, " ");
    const decoded = decodeHtmlEntities(withoutTags);

    return decoded.replace(/\s+/g, " ").trim();
  }

  // deno-lint-ignore no-explicit-any
  async function sendGeneratedPasswordEmail(
    supabaseClient: any,
    tenantId: string,
    recipientEmail: string,
    recipientName: string,
    generatedPassword: string,
  ): Promise<void> {
    await sendEmailViaTenantDistribution({
      supabaseClient,
      tenantId,
      to: recipientEmail,
      subject: "Your account has been created",
      html: `
        <div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto;">
          <h1 style="font-size: 20px; color: #1f2937;">Welcome, ${recipientName}</h1>
          <p style="color: #374151;">
            Your account is ready. Use the credentials below to sign in:
          </p>
          <div style="background: #f3f4f6; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <p style="margin: 0;"><strong>Email:</strong> ${recipientEmail}</p>
            <p style="margin: 8px 0 0;"><strong>Password:</strong> <code>${generatedPassword}</code></p>
          </div>
          <p style="color: #6b7280;">
            For security, please change this password after your first login.
          </p>
        </div>
      `,
    });
  }

  function asSingle<T>(value: T | T[] | null | undefined): T | null {
    if (Array.isArray(value)) {
      return value[0] ?? null;
    }

    return value ?? null;
  }

  function formatPatientStatusLabel(
    statusKey: string,
    patientStatusLabel: string | null,
  ): string {
    return (
      patientStatusLabel ||
      statusKey
        .replace(/_/g, " ")
        .replace(/\b\w/g, (character: string) => character.toUpperCase())
    );
  }

  function getStringSetting(
    settings: Record<string, unknown> | null | undefined,
    key: string,
  ): string | null {
    const value = settings?.[key];
    if (typeof value !== "string") return null;

    const trimmedValue = value.trim();
    return trimmedValue.length > 0 ? trimmedValue : null;
  }

  function validateSupabasePublishableKeyHash() {
    const providedKey = getBearerToken(authHeader);
    if (!providedKey) {
      return {
        valid: false,
        error: errorResponse(
          "UNAUTHORIZED",
          "Authorization header with Supabase publishable key hash is required",
          401,
        ),
      };
    }

    if (supabaseAnonKeyCandidates.length === 0) {
      return {
        valid: false,
        error: errorResponse(
          "SERVER_ERROR",
          "Missing Supabase publishable key configuration",
          500,
        ),
      };
    }

    if (!isSupabaseAnonKeyHash(providedKey, supabaseAnonKeyCandidates)) {
      return {
        valid: false,
        error: errorResponse(
          "UNAUTHORIZED",
          "Invalid Supabase publishable key hash",
          401,
        ),
      };
    }

    return { valid: true, error: null };
  }

  try {
    async function getAuthenticatedPatient() {
      if (!authHeader) {
        return {
          user: null,
          patient: null,
          error: errorResponse(
            "UNAUTHORIZED",
            "Authorization header required",
            401,
          ),
        };
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return {
          user: null,
          patient: null,
          error: errorResponse("UNAUTHORIZED", "Invalid or expired token", 401),
        };
      }

      const { data: patient, error: patientError } = await supabase
        .from("patients")
        .select(
          "id, tenant_id, first_name, last_name, email, email_verified_at, access_status, metadata",
        )
        .eq("auth_user_id", user.id)
        .maybeSingle();

      if (patientError) {
        console.error("Patient fetch error:", patientError);
        return {
          user: null,
          patient: null,
          error: errorResponse("FETCH_ERROR", "Failed to fetch patient", 500),
        };
      }

      if (!patient) {
        return {
          user: null,
          patient: null,
          error: errorResponse("NOT_FOUND", "Patient profile not found", 404),
        };
      }

      if (patient.access_status !== "active") {
        return {
          user,
          patient: null,
          error: errorResponse(
            "ACCOUNT_INACTIVE",
            `Your account is ${patient.access_status}`,
            403,
          ),
        };
      }

      return { user, patient, error: null };
    }

    // ==================== AUTH ENDPOINTS ====================

    // GET/POST /migration-status - Return a tenant user's migration status.
    // Uses md5(SUPABASE_ANON_KEY) so clients can check status before sign-in.
    if (
      (req.method === "GET" || req.method === "POST") &&
      path === "/migration-status"
    ) {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const keyValidation = validateSupabasePublishableKeyHash();
      if (!keyValidation.valid) return keyValidation.error!;

      let email = url.searchParams.get("email")?.trim().toLowerCase() ?? "";

      if (req.method === "POST") {
        let body: { email?: string };
        try {
          body = await req.json();
        } catch {
          return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
        }
        email = body.email?.trim().toLowerCase() ?? email;
      }

      if (!email) {
        return errorResponse("MISSING_FIELDS", "email is required", 400);
      }

      if (!isValidEmail(email)) {
        return errorResponse("INVALID_EMAIL", "Invalid email format", 400);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, metadata")
        .eq("tenant_id", tenant.id)
        .ilike("email", email)
        .maybeSingle();

      if (patientError) {
        console.error("Migration status patient lookup error:", patientError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch migration status",
          500,
        );
      }

      const migration = derivePatientMigrationInfo(patient?.metadata ?? null);

      const patientMeta = (patient?.metadata ?? {}) as Record<string, unknown>;
      const phase4Done = patient !== null &&
        ((typeof patientMeta.migration_phase_4 === "object" &&
          patientMeta.migration_phase_4 !== null) ||
          (typeof patientMeta.migration_phase4 === "object" &&
            patientMeta.migration_phase4 !== null));
      const showMigrationPopup = migration.isMigrated && !phase4Done;

      const BLOCKING_STATUS_KEYS = [
        "wc-on-hold",
        "wc-pending",
        "wc-provider_review",
        "wc-error_review",
        "wc-processing",
        "wc-waiting_room",
        "wc-prerequisites",
      ];

      let blockingOrderStatusKey: string | null = null;
      if (showMigrationPopup && patient?.id) {
        const { data: blockingOrders } = await supabaseAdmin
          .from("orders")
          .select("id, order_statuses!inner ( status_key )")
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id)
          .in("order_statuses.status_key", BLOCKING_STATUS_KEYS)
          .limit(1);

        if (blockingOrders && blockingOrders.length > 0) {
          blockingOrderStatusKey =
            (blockingOrders as unknown as Array<{
              order_statuses: { status_key: string };
            }>)[0].order_statuses.status_key;
        }
      }

      return jsonResponse({
        data: {
          migration: {
            isMigrated: migration.isMigrated,
            status: migration.status,
            label: migration.label,
            show_migration_popup: showMigrationPopup,
            has_blocking_order: blockingOrderStatusKey !== null,
            blocking_order_status: blockingOrderStatusKey,
          },
        },
      });
    }

    // POST /migration-trigger — per-patient self-service migration at login time.
    // Requires Supabase JWT (patient already logged into PP). Checks for blocking
    // orders, then either triggers Phase 4 (subscription handoff) or records that
    // the patient declined subscription migration so the popup does not reappear.
    if (req.method === "POST" && path === "/migration-trigger") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: { migrate_subscription?: boolean };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const migrateSubscription = body.migrate_subscription === true;
      const patientMeta = (patient.metadata ?? {}) as Record<string, unknown>;

      const phase4Done =
        (typeof patientMeta.migration_phase_4 === "object" &&
          patientMeta.migration_phase_4 !== null) ||
        (typeof patientMeta.migration_phase4 === "object" &&
          patientMeta.migration_phase4 !== null);
      if (phase4Done) {
        return jsonResponse({ data: { success: true, already_migrated: true } });
      }

      const BLOCKING_STATUS_KEYS = [
        "wc-on-hold",
        "wc-pending",
        "wc-provider_review",
        "wc-error_review",
        "wc-processing",
        "wc-waiting_room",
        "wc-prerequisites",
      ];

      const { data: blockingOrders } = await supabaseAdmin
        .from("orders")
        .select("id, order_statuses!inner ( status_key )")
        .eq("tenant_id", patient.tenant_id)
        .eq("patient_id", patient.id)
        .in("order_statuses.status_key", BLOCKING_STATUS_KEYS)
        .limit(1);

      if (blockingOrders && blockingOrders.length > 0) {
        const blockingStatusKey =
          (blockingOrders as unknown as Array<{
            order_statuses: { status_key: string };
          }>)[0].order_statuses.status_key;
        return jsonResponse(
          {
            error: {
              code: "BLOCKED_BY_ORDER",
              message:
                "Your order is currently being processed. Please try again in 2 days.",
              blocking_order_status: blockingStatusKey,
            },
          },
          409,
        );
      }

      const migrationApiKey = Deno.env.get("MIGRATION_API_KEY");
      if (!migrationApiKey) {
        console.error("migration-trigger: MIGRATION_API_KEY not configured");
        return errorResponse("CONFIG_ERROR", "Migration service unavailable", 500);
      }

      const { data: tenantRow } = await supabaseAdmin
        .from("tenants")
        .select("slug")
        .eq("id", patient.tenant_id)
        .single();

      if (!tenantRow?.slug) {
        return errorResponse("TENANT_NOT_FOUND", "Tenant not found", 404);
      }

      // allia (CareLink) uses free_handoff — $0 coupon subscriptions, no Stripe, no opt-out
      const isAlliaTenant = tenantRow.slug === "allia";

      if (!migrateSubscription && !isAlliaTenant) {
        await supabaseAdmin
          .from("patients")
          .update({
            metadata: {
              ...patientMeta,
              migration_phase_4: {
                skipped: true,
                skipped_at: new Date().toISOString(),
                reason: "user_declined_subscription_migration",
              },
            },
          })
          .eq("id", patient.id);

        return jsonResponse({
          data: { success: true, subscriptions_migrated: false },
        });
      }

      const phase4Response = await fetch(
        `${supabaseUrl}/functions/v1/migration-phase4-subscription-handoff`,
        {
          method: "POST",
          headers: {
            "X-Migration-API-Key": migrationApiKey,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            emails: [patient.email],
            tenant_slug: tenantRow.slug,
            dry_run: false,
            confirm_live: true,
            ...(isAlliaTenant
              ? { free_handoff: true }
              : { woo_renewal_blocking_confirmed: true }),
          }),
        },
      );

      if (!phase4Response.ok) {
        const errorBody = await phase4Response.text().catch(() => "");
        console.error(
          "migration-trigger: Phase 4 EF error",
          phase4Response.status,
          errorBody,
        );
        return errorResponse(
          "MIGRATION_FAILED",
          "Subscription migration failed. Please try again or contact support.",
          502,
        );
      }

      const phase4Result = await phase4Response.json().catch(() => ({}));
      return jsonResponse({
        data: { success: true, subscriptions_migrated: true, phase4: phase4Result },
      });
    }

    if (req.method === "POST" && path === "/friendbuy/attribution") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: { attribution?: unknown };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const attribution = normalizeFriendbuyAttribution(body.attribution);
      if (!attribution) {
        return errorResponse(
          "INVALID_ATTRIBUTION",
          "A referralCode, attributionId, or campaignId is required",
          400,
        );
      }

      const existingMetadata = patient!.metadata &&
          typeof patient!.metadata === "object" &&
          !Array.isArray(patient!.metadata)
        ? patient!.metadata as Record<string, unknown>
        : {};
      const { error: updateError } = await supabaseAdmin
        .from("patients")
        .update({
          metadata: {
            ...existingMetadata,
            friendbuy_attribution: attribution,
          },
        })
        .eq("id", patient!.id)
        .eq("tenant_id", patient!.tenant_id);

      if (updateError) {
        console.warn("Failed to persist Friendbuy attribution", {
          requestId,
          patientId: patient!.id,
          tenantId: patient!.tenant_id,
          error: updateError.message,
        });
        return errorResponse(
          "SAVE_ERROR",
          "Failed to save Friendbuy attribution",
          500,
        );
      }

      return jsonResponse({ data: { saved: true } });
    }

    if (req.method === "GET" && path === "/friendbuy/balance") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let availableCents = 0;
      let balanceCurrency: string | null = null;

      // Live available credit comes from Friendbuy's ledger balance, keyed on
      // the customerId we track with Friendbuy (our patient id).
      const ledger = await getFriendbuyLedgerBalance(supabaseAdmin, {
        tenantId: patient!.tenant_id,
        customerId: patient!.id,
        currency: "USD",
      }).catch((error) => {
        console.warn("Failed to load Friendbuy ledger balance", {
          requestId,
          patientId: patient!.id,
          tenantId: patient!.tenant_id,
          error: error instanceof Error ? error.message : String(error),
        });
        return null;
      });

      if (ledger) {
        availableCents = ledger.availableCents;
        balanceCurrency = ledger.currency;
      }

      const pending = await getPendingFriendbuyRewardTotal(supabaseAdmin, {
        tenantId: patient!.tenant_id,
        patientId: patient!.id,
        patientEmail: patient!.email,
        currency: "USD",
      }).catch((error) => {
        console.warn("Failed to load Friendbuy pending reward total", {
          requestId,
          patientId: patient!.id,
          tenantId: patient!.tenant_id,
          error: error instanceof Error ? error.message : String(error),
        });
        return {
          pendingTotal: 0,
          formattedPendingTotal: null,
          currency: "USD",
        };
      });

      const currency = balanceCurrency || pending.currency || "USD";
      const availableTotal = dollarsFromCents(availableCents);

      return jsonResponse({
        data: {
          available_total: availableTotal,
          formatted_available_total: availableCents > 0
            ? formatCurrency(availableTotal, currency)
            : null,
          pending_total: pending.pendingTotal,
          formatted_pending_total: pending.formattedPendingTotal,
          currency,
          applied_to_label: null,
        },
      });
    }

    if (req.method === "GET" && path === "/friendbuy/referrals") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const referrals = await getPatientReferrals(supabaseAdmin, {
        tenantId: patient!.tenant_id,
        patientId: patient!.id,
        patientEmail: patient!.email,
        currency: "USD",
      }).catch((error) => {
        console.warn("Failed to load Friendbuy referrals", {
          requestId,
          patientId: patient!.id,
          tenantId: patient!.tenant_id,
          error: error instanceof Error ? error.message : String(error),
        });
        return null;
      });
      const safeReferrals = referrals ?? { total: 0, referrals: [] };

      return jsonResponse({
        data: {
          total: safeReferrals.total,
          referrals: safeReferrals.referrals.map((entry) => ({
            friend_email: entry.friendEmail,
            status: entry.status,
            occurred_at: entry.occurredAt,
            amount_cents: entry.amountCents,
            formatted_amount: entry.formattedAmount,
          })),
        },
      });
    }

    // GET /terms-and-conditions/latest - Return the live tenant terms version
    if (req.method === "GET" && path === "/terms-and-conditions/latest") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      try {
        const liveVersion = await getLiveTenantTermsVersion(tenant.id);

        if (!liveVersion) {
          return errorResponse(
            "TENANT_TERMS_NOT_CONFIGURED",
            "No live tenant terms and conditions version is configured",
            404,
          );
        }

        return jsonResponse({
          data: {
            id: liveVersion.id,
            tenant_id: liveVersion.tenant_id,
            version: liveVersion.version,
            content: liveVersion.content,
            published_at: liveVersion.published_at,
          },
        });
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant terms and conditions",
          500,
        );
      }
    }

    // GET /terms-and-conditions/acceptance-status - Check whether patient accepted the live tenant version
    if (
      req.method === "GET" &&
      path === "/terms-and-conditions/acceptance-status"
    ) {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, access_status")
        .eq("auth_user_id", user.id)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (patientError) {
        console.error(
          "Patient lookup error for tenant terms acceptance status:",
          patientError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch patient", 500);
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      let liveVersion: LiveTenantTermsVersion | null;
      try {
        liveVersion = await getLiveTenantTermsVersion(tenant.id);
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant terms and conditions",
          500,
        );
      }

      if (!liveVersion) {
        return errorResponse(
          "TENANT_TERMS_NOT_CONFIGURED",
          "No live tenant terms and conditions version is configured",
          404,
        );
      }

      const { data: liveVersionAcceptance, error: liveVersionAcceptanceError } =
        await supabaseAdmin
          .from("patient_platform_terms_acceptances")
          .select(
            "id, platform_terms_version_id, platform_terms_version, accepted_at",
          )
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id)
          .eq("platform_terms_version_id", liveVersion.id)
          .maybeSingle();

      if (liveVersionAcceptanceError) {
        console.error(
          "Tenant terms acceptance status lookup error:",
          liveVersionAcceptanceError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch tenant terms acceptance status",
          500,
        );
      }

      return jsonResponse({
        data: {
          patient_id: patient.id,
          tenant_id: tenant.id,
          live_version_id: liveVersion.id,
          live_version: liveVersion.version,
          has_accepted_latest_terms: !!liveVersionAcceptance,
          latest_accepted_version_id:
            liveVersionAcceptance?.platform_terms_version_id ?? null,
          latest_accepted_version:
            liveVersionAcceptance?.platform_terms_version ?? null,
          latest_accepted_at: liveVersionAcceptance?.accepted_at ?? null,
        },
      });
    }

    // POST /terms-and-conditions/accept - Record acceptance of the live tenant terms version
    if (req.method === "POST" && path === "/terms-and-conditions/accept") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        tenant_terms_version_id?: string;
        platform_terms_version_id?: string;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const acceptedVersionId = body.tenant_terms_version_id?.trim() ||
        body.platform_terms_version_id?.trim();

      if (!acceptedVersionId) {
        return errorResponse(
          "MISSING_FIELDS",
          "tenant_terms_version_id is required",
          400,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, access_status")
        .eq("auth_user_id", user.id)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (patientError) {
        console.error(
          "Patient lookup error for tenant terms acceptance:",
          patientError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch patient", 500);
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      let liveVersion: LiveTenantTermsVersion | null;
      try {
        liveVersion = await getLiveTenantTermsVersion(tenant.id);
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant terms and conditions",
          500,
        );
      }

      if (!liveVersion) {
        return errorResponse(
          "TENANT_TERMS_NOT_CONFIGURED",
          "No live tenant terms and conditions version is configured",
          404,
        );
      }

      if (acceptedVersionId !== liveVersion.id) {
        return errorResponse(
          "STALE_TENANT_TERMS_VERSION",
          "The accepted terms version is no longer current. Please fetch the latest version and try again.",
          409,
        );
      }

      const { data: existingAcceptance, error: existingAcceptanceError } =
        await supabaseAdmin
          .from("patient_platform_terms_acceptances")
          .select("id, accepted_at")
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id)
          .eq("platform_terms_version_id", liveVersion.id)
          .maybeSingle();

      if (existingAcceptanceError) {
        console.error(
          "Existing tenant terms acceptance lookup error:",
          existingAcceptanceError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch existing tenant terms acceptance",
          500,
        );
      }

      if (existingAcceptance) {
        return jsonResponse({
          data: {
            id: existingAcceptance.id,
            tenant_terms_version_id: liveVersion.id,
            platform_terms_version_id: liveVersion.id,
            tenant_terms_version: liveVersion.version,
            platform_terms_version: liveVersion.version,
            accepted_at: existingAcceptance.accepted_at,
            already_accepted: true,
          },
        });
      }

      const acceptedAt = dateTime().toISOString();
      const { data: createdAcceptance, error: createAcceptanceError } =
        await supabaseAdmin
          .from("patient_platform_terms_acceptances")
          .insert({
            tenant_id: tenant.id,
            patient_id: patient.id,
            platform_terms_version_id: liveVersion.id,
            platform_terms_version: liveVersion.version,
            accepted_at: acceptedAt,
          })
          .select("id, accepted_at")
          .single();

      if (createAcceptanceError) {
        console.error(
          "Tenant terms acceptance creation error:",
          createAcceptanceError,
        );
        return errorResponse(
          "TERMS_ACCEPTANCE_ERROR",
          "Failed to save accepted tenant terms",
          500,
        );
      }

      return jsonResponse(
        {
          data: {
            id: createdAcceptance.id,
            tenant_terms_version_id: liveVersion.id,
            platform_terms_version_id: liveVersion.id,
            tenant_terms_version: liveVersion.version,
            platform_terms_version: liveVersion.version,
            accepted_at: createdAcceptance.accepted_at,
            already_accepted: false,
          },
        },
        201,
      );
    }

    // GET /privacy-policy/latest - Return the live tenant privacy policy version
    if (req.method === "GET" && path === "/privacy-policy/latest") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      try {
        const liveVersion = await getLiveTenantPrivacyPolicyVersion(tenant.id);

        if (!liveVersion) {
          return errorResponse(
            "PRIVACY_POLICY_NOT_CONFIGURED",
            "No live tenant privacy policy version is configured",
            404,
          );
        }

        return jsonResponse({
          data: {
            id: liveVersion.id,
            tenant_id: liveVersion.tenant_id,
            version: liveVersion.version,
            content: liveVersion.content,
            published_at: liveVersion.published_at,
          },
        });
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant privacy policy",
          500,
        );
      }
    }

    // GET /privacy-policy/acceptance-status - Check whether patient accepted the live tenant privacy policy
    if (req.method === "GET" && path === "/privacy-policy/acceptance-status") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, access_status")
        .eq("auth_user_id", user.id)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (patientError) {
        console.error(
          "Patient lookup error for privacy policy acceptance status:",
          patientError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch patient", 500);
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      let liveVersion: LiveTenantPrivacyPolicyVersion | null;
      try {
        liveVersion = await getLiveTenantPrivacyPolicyVersion(tenant.id);
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant privacy policy",
          500,
        );
      }

      if (!liveVersion) {
        return errorResponse(
          "PRIVACY_POLICY_NOT_CONFIGURED",
          "No live tenant privacy policy version is configured",
          404,
        );
      }

      const { data: liveVersionAcceptance, error: liveVersionAcceptanceError } =
        await supabaseAdmin
          .from("patient_privacy_policy_acceptances")
          .select(
            "id, privacy_policy_version_id, privacy_policy_version, accepted_at",
          )
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id)
          .eq("privacy_policy_version_id", liveVersion.id)
          .maybeSingle();

      if (liveVersionAcceptanceError) {
        console.error(
          "Privacy policy acceptance status lookup error:",
          liveVersionAcceptanceError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch privacy policy acceptance status",
          500,
        );
      }

      return jsonResponse({
        data: {
          patient_id: patient.id,
          tenant_id: tenant.id,
          live_version_id: liveVersion.id,
          live_version: liveVersion.version,
          has_accepted_latest_privacy_policy: !!liveVersionAcceptance,
          latest_accepted_version_id:
            liveVersionAcceptance?.privacy_policy_version_id ?? null,
          latest_accepted_version:
            liveVersionAcceptance?.privacy_policy_version ?? null,
          latest_accepted_at: liveVersionAcceptance?.accepted_at ?? null,
        },
      });
    }

    // POST /privacy-policy/accept - Record acceptance of the live tenant privacy policy version
    if (req.method === "POST" && path === "/privacy-policy/accept") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        privacy_policy_version_id?: string;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const acceptedVersionId = body.privacy_policy_version_id?.trim();

      if (!acceptedVersionId) {
        return errorResponse(
          "MISSING_FIELDS",
          "privacy_policy_version_id is required",
          400,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, access_status")
        .eq("auth_user_id", user.id)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (patientError) {
        console.error(
          "Patient lookup error for privacy policy acceptance:",
          patientError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch patient", 500);
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      let liveVersion: LiveTenantPrivacyPolicyVersion | null;
      try {
        liveVersion = await getLiveTenantPrivacyPolicyVersion(tenant.id);
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant privacy policy",
          500,
        );
      }

      if (!liveVersion) {
        return errorResponse(
          "PRIVACY_POLICY_NOT_CONFIGURED",
          "No live tenant privacy policy version is configured",
          404,
        );
      }

      if (acceptedVersionId !== liveVersion.id) {
        return errorResponse(
          "STALE_PRIVACY_POLICY_VERSION",
          "The accepted privacy policy version is no longer current. Please fetch the latest version and try again.",
          409,
        );
      }

      const { data: existingAcceptance, error: existingAcceptanceError } =
        await supabaseAdmin
          .from("patient_privacy_policy_acceptances")
          .select("id, accepted_at")
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id)
          .eq("privacy_policy_version_id", liveVersion.id)
          .maybeSingle();

      if (existingAcceptanceError) {
        console.error(
          "Existing privacy policy acceptance lookup error:",
          existingAcceptanceError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch existing privacy policy acceptance",
          500,
        );
      }

      if (existingAcceptance) {
        return jsonResponse({
          data: {
            id: existingAcceptance.id,
            privacy_policy_version_id: liveVersion.id,
            privacy_policy_version: liveVersion.version,
            accepted_at: existingAcceptance.accepted_at,
            already_accepted: true,
          },
        });
      }

      const acceptedAt = dateTime().toISOString();
      const { data: createdAcceptance, error: createAcceptanceError } =
        await supabaseAdmin
          .from("patient_privacy_policy_acceptances")
          .insert({
            tenant_id: tenant.id,
            patient_id: patient.id,
            privacy_policy_version_id: liveVersion.id,
            privacy_policy_version: liveVersion.version,
            accepted_at: acceptedAt,
          })
          .select("id, accepted_at")
          .single();

      if (createAcceptanceError) {
        console.error(
          "Privacy policy acceptance creation error:",
          createAcceptanceError,
        );
        return errorResponse(
          "PRIVACY_POLICY_ACCEPTANCE_ERROR",
          "Failed to save accepted privacy policy",
          500,
        );
      }

      return jsonResponse(
        {
          data: {
            id: createdAcceptance.id,
            privacy_policy_version_id: liveVersion.id,
            privacy_policy_version: liveVersion.version,
            accepted_at: createdAcceptance.accepted_at,
            already_accepted: false,
          },
        },
        201,
      );
    }

    // POST /auth/signup - Register a new patient
    if (req.method === "POST" && path === "/auth/signup") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        email?: string;
        name?: string;
        phone?: string;
        shipping_state?: string;
        shipping_address?: {
          first_name?: string;
          last_name?: string;
          company?: string;
          line1?: string;
          line2?: string;
          city?: string;
          state?: string;
          postal_code?: string;
          country?: string;
          instructions?: string;
        };
        product_id?: string;
        tenant_terms_version_id?: string;
        platform_terms_version_id?: string;
        privacy_policy_version_id?: string;
        friendbuy_attribution?: unknown;
        subscribe_to_email_and_sms_marketing?: boolean;
        /** Opt-in: when true, sign the new user in and return a session so the
         * caller (e.g. the Option 2 checkout) is authenticated immediately. */
        return_session?: boolean;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const {
        email,
        name,
        phone,
        shipping_state,
        shipping_address,
        product_id,
        tenant_terms_version_id,
        platform_terms_version_id,
        privacy_policy_version_id,
      } = body;
      const friendbuyAttribution = normalizeFriendbuyAttribution(
        body.friendbuy_attribution,
      );
      if (
        "subscribe_to_email_and_sms_marketing" in body &&
        typeof body.subscribe_to_email_and_sms_marketing !== "boolean"
      ) {
        return errorResponse(
          "INVALID_MARKETING_SUBSCRIPTION",
          "subscribe_to_email_and_sms_marketing must be a boolean",
          400,
        );
      }

      const normalizedEmail = email?.toLowerCase().trim();
      const normalizedName = name?.trim();
      const normalizedPhone = typeof phone === "string"
        ? normalizeUsPhoneDigits(phone)
        : undefined;
      const normalizedShippingState = (shipping_address?.state ??
        shipping_state)?.toUpperCase().trim();
      const normalizedShippingCountry = shipping_address?.country?.trim()
        .toUpperCase() || "US";
      const normalizedProductId = product_id?.trim();
      const normalizedTenantTermsVersionId = tenant_terms_version_id?.trim() ||
        platform_terms_version_id?.trim();
      const normalizedPrivacyPolicyVersionId = privacy_policy_version_id
        ?.trim();
      const subscribedToMarketing =
        body.subscribe_to_email_and_sms_marketing === true;
      const shouldSkipGeneratedPasswordEmail =
        normalizedEmail?.endsWith("@example.com") ||
        normalizedEmail?.endsWith("@dev.com") ||
        normalizedEmail?.endsWith("@staging.com") ||
        normalizedEmail?.endsWith("@stg.com") ||
        false;

      // Validate required fields
      if (
        !normalizedEmail ||
        !normalizedName ||
        !normalizedPhone ||
        !normalizedShippingState ||
        !normalizedTenantTermsVersionId ||
        !normalizedPrivacyPolicyVersionId
      ) {
        return errorResponse(
          "MISSING_FIELDS",
          "email, name, phone, shipping_state, tenant_terms_version_id, and privacy_policy_version_id are required",
          400,
        );
      }

      if (!isValidEmail(normalizedEmail)) {
        return errorResponse("INVALID_EMAIL", "Invalid email format", 400);
      }

      const emailDomainValidation = await validateEmailDomainAgainstTenant(
        supabaseAdmin,
        tenant.id,
        normalizedEmail,
      );
      if (!emailDomainValidation.valid) {
        return errorResponse(
          "EMAIL_DOMAIN_NOT_ALLOWED",
          emailDomainValidation.message!,
          403,
        );
      }

      const phoneValidation = isValidUsPhoneWithoutCountryCode(normalizedPhone);
      if (!phoneValidation.valid) {
        return errorResponse("INVALID_PHONE", phoneValidation.message!, 400);
      }

      const passwordToUse =
        getDefaultPatientPassword(normalizedEmail, supabaseUrl) ||
        generateAutoPassword();

      const stateValidation = await validateStateAgainstTenant(
        supabaseAdmin,
        tenant.id,
        normalizedShippingState,
        normalizedShippingCountry,
      );
      if (!stateValidation.valid) {
        return errorResponse(
          "INVALID_SHIPPING_STATE",
          stateValidation.message!,
          400,
        );
      }

      const nameParts = normalizedName.split(/\s+/).filter(Boolean);
      const first_name = nameParts[0];
      const last_name = nameParts.slice(1).join(" ") || nameParts[0];
      const termsAcceptedAt = dateTime().toISOString();
      const normalizeAddressField = (value: string | undefined) => {
        const normalizedValue = value?.trim();
        return normalizedValue ? normalizedValue : null;
      };
      const normalizedSignupShippingAddress = shipping_address
        ? {
          first_name: normalizeAddressField(shipping_address.first_name) ||
            first_name,
          last_name: normalizeAddressField(shipping_address.last_name) ||
            last_name,
          company: normalizeAddressField(shipping_address.company),
          line1: normalizeAddressField(shipping_address.line1),
          line2: normalizeAddressField(shipping_address.line2),
          city: normalizeAddressField(shipping_address.city),
          state: normalizedShippingState,
          postal_code: normalizeAddressField(shipping_address.postal_code),
          country: normalizedShippingCountry,
          instructions: normalizeAddressField(shipping_address.instructions),
        }
        : null;
      let liveTenantTermsVersion: LiveTenantTermsVersion | null;

      if (
        normalizedSignupShippingAddress &&
        (!normalizedSignupShippingAddress.line1 ||
          !normalizedSignupShippingAddress.city ||
          !normalizedSignupShippingAddress.state ||
          !normalizedSignupShippingAddress.postal_code)
      ) {
        return errorResponse(
          "INVALID_SHIPPING_ADDRESS",
          "Shipping address must include line1, city, state, and postal_code",
          400,
        );
      }

      try {
        liveTenantTermsVersion = await getLiveTenantTermsVersion(tenant.id);
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant terms and conditions",
          500,
        );
      }

      if (!liveTenantTermsVersion) {
        return errorResponse(
          "TENANT_TERMS_NOT_CONFIGURED",
          "No live tenant terms and conditions version is configured",
          503,
        );
      }

      if (normalizedTenantTermsVersionId !== liveTenantTermsVersion.id) {
        return errorResponse(
          "STALE_TENANT_TERMS_VERSION",
          "The accepted tenant terms version is no longer current. Please fetch the latest version and try again.",
          409,
        );
      }

      let livePrivacyPolicyVersion: LiveTenantPrivacyPolicyVersion | null;
      try {
        livePrivacyPolicyVersion = await getLiveTenantPrivacyPolicyVersion(
          tenant.id,
        );
      } catch {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch live tenant privacy policy",
          500,
        );
      }

      if (!livePrivacyPolicyVersion) {
        return errorResponse(
          "PRIVACY_POLICY_NOT_CONFIGURED",
          "No live tenant privacy policy version is configured",
          503,
        );
      }

      if (normalizedPrivacyPolicyVersionId !== livePrivacyPolicyVersion.id) {
        return errorResponse(
          "STALE_PRIVACY_POLICY_VERSION",
          "The accepted privacy policy version is no longer current. Please fetch the latest version and try again.",
          409,
        );
      }

      if (normalizedProductId) {
        // Validate the selected product as optional signup context only.
        // Product terms acceptance is recorded exclusively by
        // POST /products/:id/terms-acceptance after the patient explicitly accepts.
        const { data: selectedProduct, error: selectedProductError } =
          await supabase
            .from("products")
            .select("id")
            .eq("id", normalizedProductId)
            .eq("tenant_id", tenant.id)
            .eq("is_enabled", true)
            .maybeSingle();

        if (selectedProductError) {
          console.error("Signup product lookup error:", selectedProductError);
          return errorResponse(
            "FETCH_ERROR",
            "Failed to fetch signup product",
            500,
          );
        }

        if (!selectedProduct) {
          return errorResponse(
            "PRODUCT_NOT_FOUND",
            "Product not found or not available",
            404,
          );
        }
      }

      // Check if patient with this email already exists for this tenant
      const { data: existingPatient } = await supabaseAdmin
        .from("patients")
        .select(
          `
          id,
          auth_user_id,
          first_name,
          last_name,
          phone,
          shipping_state,
          shipping_country,
          shipping_first_name,
          shipping_last_name,
          shipping_company,
          shipping_address_line1,
          shipping_address_line2,
          shipping_city,
          shipping_postal_code,
          shipping_instructions,
          subscribed_to_email_marketing,
          subscribed_to_sms_marketing,
          email_verified_at
        `,
        )
        .eq("tenant_id", tenant.id)
        .eq("email", normalizedEmail)
        .maybeSingle();

      if (existingPatient?.auth_user_id) {
        return errorResponse(
          "EMAIL_EXISTS",
          "An account with this email already exists",
          409,
        );
      }

      // Create auth user
      const { data: authData, error: authError } = await supabaseAdmin.auth
        .admin.createUser({
          email: normalizedEmail,
          password: passwordToUse,
          email_confirm: true, // Auto-confirm for patient signups
          user_metadata: {
            full_name: normalizedName,
            first_name,
            last_name,
            tenant_id: tenant.id,
            user_type: "patient",
          },
        });

      if (authError) {
        console.error("Auth signup error:", authError);
        if (authError.message.includes("already been registered")) {
          return errorResponse(
            "EMAIL_EXISTS",
            "An account with this email already exists",
            409,
          );
        }
        return errorResponse("AUTH_ERROR", authError.message, 500);
      }

      // Create or update patient record
      let createdNewPatient = false;
      let createdPlatformTermsAcceptanceId: string | null = null;
      let createdPrivacyPolicyAcceptanceId: string | null = null;
      let patientIdForTermsAcceptance = existingPatient?.id || null;
      if (existingPatient) {
        // Link existing patient to auth user
        const { error: updateError } = await supabaseAdmin
          .from("patients")
          .update({
            auth_user_id: authData.user.id,
            first_name,
            last_name,
            phone: normalizedPhone || null,
            shipping_state: normalizedShippingState,
            shipping_country: normalizedShippingCountry,
            subscribed_to_email_marketing: subscribedToMarketing,
            subscribed_to_sms_marketing: subscribedToMarketing,
            ...(normalizedSignupShippingAddress
              ? {
                shipping_first_name: normalizedSignupShippingAddress
                  .first_name,
                shipping_last_name: normalizedSignupShippingAddress.last_name,
                shipping_company: normalizedSignupShippingAddress.company,
                shipping_address_line1: normalizedSignupShippingAddress.line1,
                shipping_address_line2: normalizedSignupShippingAddress.line2,
                shipping_city: normalizedSignupShippingAddress.city,
                shipping_postal_code: normalizedSignupShippingAddress
                  .postal_code,
                shipping_instructions: normalizedSignupShippingAddress
                  .instructions,
              }
              : {}),
          })
          .eq("id", existingPatient.id);

        if (updateError) {
          console.error("Patient update error:", updateError);
          // Clean up auth user if patient update fails
          await supabaseAdmin.auth.admin.deleteUser(authData.user.id);
          return errorResponse(
            "PATIENT_ERROR",
            "Failed to update patient record",
            500,
          );
        }
      } else {
        // Create new patient record
        const { data: newPatient, error: patientError } = await supabaseAdmin
          .from("patients")
          .insert({
            tenant_id: tenant.id,
            auth_user_id: authData.user.id,
            email: normalizedEmail,
            first_name,
            last_name,
            phone: normalizedPhone || null,
            shipping_state: normalizedShippingState,
            shipping_country: normalizedShippingCountry,
            shipping_first_name: normalizedSignupShippingAddress?.first_name ??
              null,
            shipping_last_name: normalizedSignupShippingAddress?.last_name ??
              null,
            shipping_company: normalizedSignupShippingAddress?.company ?? null,
            shipping_address_line1: normalizedSignupShippingAddress?.line1 ??
              null,
            shipping_address_line2: normalizedSignupShippingAddress?.line2 ??
              null,
            shipping_city: normalizedSignupShippingAddress?.city ?? null,
            shipping_postal_code:
              normalizedSignupShippingAddress?.postal_code ?? null,
            shipping_instructions:
              normalizedSignupShippingAddress?.instructions ?? null,
            subscribed_to_email_marketing: subscribedToMarketing,
            subscribed_to_sms_marketing: subscribedToMarketing,
          })
          .select("id")
          .single();

        if (patientError) {
          console.error("Patient creation error:", patientError);
          // Clean up auth user if patient creation fails
          await supabaseAdmin.auth.admin.deleteUser(authData.user.id);
          return errorResponse(
            "PATIENT_ERROR",
            "Failed to create patient record",
            500,
          );
        }
        patientIdForTermsAcceptance = newPatient.id;
        createdNewPatient = true;
      }

      const {
        data: createdPlatformTermsAcceptance,
        error: platformTermsAcceptanceError,
      } = await supabaseAdmin
        .from("patient_platform_terms_acceptances")
        .insert({
          tenant_id: tenant.id,
          patient_id: patientIdForTermsAcceptance,
          platform_terms_version_id: liveTenantTermsVersion.id,
          platform_terms_version: liveTenantTermsVersion.version,
          accepted_at: termsAcceptedAt,
        })
        .select("id")
        .single();

      if (platformTermsAcceptanceError) {
        console.error(
          "Tenant terms acceptance creation error:",
          platformTermsAcceptanceError,
        );
        await supabaseAdmin.auth.admin.deleteUser(authData.user.id);

        if (createdNewPatient) {
          await supabaseAdmin
            .from("patients")
            .delete()
            .eq("tenant_id", tenant.id)
            .eq("email", normalizedEmail);
        } else if (existingPatient) {
          await supabaseAdmin
            .from("patients")
            .update({
              auth_user_id: existingPatient.auth_user_id,
              first_name: existingPatient.first_name,
              last_name: existingPatient.last_name,
              phone: existingPatient.phone,
              shipping_state: existingPatient.shipping_state,
              shipping_country: existingPatient.shipping_country,
              shipping_first_name: existingPatient.shipping_first_name,
              shipping_last_name: existingPatient.shipping_last_name,
              shipping_company: existingPatient.shipping_company,
              shipping_address_line1: existingPatient.shipping_address_line1,
              shipping_address_line2: existingPatient.shipping_address_line2,
              shipping_city: existingPatient.shipping_city,
              shipping_postal_code: existingPatient.shipping_postal_code,
              shipping_instructions: existingPatient.shipping_instructions,
              subscribed_to_email_marketing:
                existingPatient.subscribed_to_email_marketing,
              subscribed_to_sms_marketing:
                existingPatient.subscribed_to_sms_marketing,
              email_verified_at: existingPatient.email_verified_at,
            })
            .eq("id", existingPatient.id);
        }

        return errorResponse(
          "TERMS_ACCEPTANCE_ERROR",
          "Failed to store accepted tenant terms and conditions",
          500,
        );
      }

      createdPlatformTermsAcceptanceId = createdPlatformTermsAcceptance.id;

      const {
        data: createdPrivacyPolicyAcceptance,
        error: privacyPolicyAcceptanceError,
      } = await supabaseAdmin
        .from("patient_privacy_policy_acceptances")
        .insert({
          tenant_id: tenant.id,
          patient_id: patientIdForTermsAcceptance,
          privacy_policy_version_id: livePrivacyPolicyVersion.id,
          privacy_policy_version: livePrivacyPolicyVersion.version,
          accepted_at: termsAcceptedAt,
        })
        .select("id")
        .single();

      if (privacyPolicyAcceptanceError) {
        console.error(
          "Privacy policy acceptance creation error:",
          privacyPolicyAcceptanceError,
        );
        await supabaseAdmin.auth.admin.deleteUser(authData.user.id);

        if (createdPlatformTermsAcceptanceId) {
          await supabaseAdmin
            .from("patient_platform_terms_acceptances")
            .delete()
            .eq("id", createdPlatformTermsAcceptanceId)
            .eq("tenant_id", tenant.id);
        }

        if (createdNewPatient) {
          await supabaseAdmin
            .from("patients")
            .delete()
            .eq("tenant_id", tenant.id)
            .eq("email", normalizedEmail);
        } else if (existingPatient) {
          await supabaseAdmin
            .from("patients")
            .update({
              auth_user_id: existingPatient.auth_user_id,
              first_name: existingPatient.first_name,
              last_name: existingPatient.last_name,
              phone: existingPatient.phone,
              shipping_state: existingPatient.shipping_state,
              shipping_country: existingPatient.shipping_country,
              shipping_first_name: existingPatient.shipping_first_name,
              shipping_last_name: existingPatient.shipping_last_name,
              shipping_company: existingPatient.shipping_company,
              shipping_address_line1: existingPatient.shipping_address_line1,
              shipping_address_line2: existingPatient.shipping_address_line2,
              shipping_city: existingPatient.shipping_city,
              shipping_postal_code: existingPatient.shipping_postal_code,
              shipping_instructions: existingPatient.shipping_instructions,
              subscribed_to_email_marketing:
                existingPatient.subscribed_to_email_marketing,
              subscribed_to_sms_marketing:
                existingPatient.subscribed_to_sms_marketing,
              email_verified_at: existingPatient.email_verified_at,
            })
            .eq("id", existingPatient.id);
        }

        return errorResponse(
          "PRIVACY_POLICY_ACCEPTANCE_ERROR",
          "Failed to store accepted privacy policy",
          500,
        );
      }

      createdPrivacyPolicyAcceptanceId = createdPrivacyPolicyAcceptance.id;

      if (!shouldSkipGeneratedPasswordEmail) {
        try {
          await sendGeneratedPasswordEmail(
            supabaseAdmin,
            tenant.id,
            normalizedEmail,
            normalizedName,
            passwordToUse,
          );
        } catch (emailError) {
          console.error(
            "Generated password email delivery failed:",
            emailError,
          );
          await supabaseAdmin.auth.admin.deleteUser(authData.user.id);

          if (createdNewPatient) {
            await supabaseAdmin
              .from("patients")
              .delete()
              .eq("tenant_id", tenant.id)
              .eq("email", normalizedEmail);
          } else if (existingPatient) {
            if (createdPrivacyPolicyAcceptanceId) {
              await supabaseAdmin
                .from("patient_privacy_policy_acceptances")
                .delete()
                .eq("id", createdPrivacyPolicyAcceptanceId)
                .eq("tenant_id", tenant.id);
            }

            if (createdPlatformTermsAcceptanceId) {
              await supabaseAdmin
                .from("patient_platform_terms_acceptances")
                .delete()
                .eq("id", createdPlatformTermsAcceptanceId)
                .eq("tenant_id", tenant.id);
            }

            await supabaseAdmin
              .from("patients")
              .update({
                auth_user_id: existingPatient.auth_user_id,
                first_name: existingPatient.first_name,
                last_name: existingPatient.last_name,
                phone: existingPatient.phone,
                shipping_state: existingPatient.shipping_state,
                shipping_country: existingPatient.shipping_country,
                shipping_first_name: existingPatient.shipping_first_name,
                shipping_last_name: existingPatient.shipping_last_name,
                shipping_company: existingPatient.shipping_company,
                shipping_address_line1: existingPatient.shipping_address_line1,
                shipping_address_line2: existingPatient.shipping_address_line2,
                shipping_city: existingPatient.shipping_city,
                shipping_postal_code: existingPatient.shipping_postal_code,
                shipping_instructions: existingPatient.shipping_instructions,
                subscribed_to_email_marketing:
                  existingPatient.subscribed_to_email_marketing,
                subscribed_to_sms_marketing:
                  existingPatient.subscribed_to_sms_marketing,
                email_verified_at: existingPatient.email_verified_at,
              })
              .eq("id", existingPatient.id);
          }

          return errorResponse(
            "EMAIL_DELIVERY_FAILED",
            "Failed to deliver generated password email. Please try again.",
            500,
          );
        }
      }

      if (friendbuyAttribution && patientIdForTermsAcceptance) {
        const { data: currentPatientMetadata } = await supabaseAdmin
          .from("patients")
          .select("metadata")
          .eq("id", patientIdForTermsAcceptance)
          .eq("tenant_id", tenant.id)
          .maybeSingle();
        const existingMetadata = currentPatientMetadata?.metadata &&
            typeof currentPatientMetadata.metadata === "object" &&
            !Array.isArray(currentPatientMetadata.metadata)
          ? currentPatientMetadata.metadata as Record<string, unknown>
          : {};
        const { error: attributionUpdateError } = await supabaseAdmin
          .from("patients")
          .update({
            metadata: {
              ...existingMetadata,
              friendbuy_attribution: friendbuyAttribution,
            },
          })
          .eq("id", patientIdForTermsAcceptance)
          .eq("tenant_id", tenant.id);

        if (attributionUpdateError) {
          console.warn("Signup Friendbuy attribution persist failed", {
            requestId,
            tenantId: tenant.id,
            patientId: patientIdForTermsAcceptance,
            error: attributionUpdateError.message,
          });
        }
      }

      if (patientIdForTermsAcceptance) {
        await sendFriendbuySignupEvent(supabaseAdmin, {
          tenantId: tenant.id,
          patientId: patientIdForTermsAcceptance,
          customer: {
            id: patientIdForTermsAcceptance,
            email: normalizedEmail,
            firstName: first_name,
            lastName: last_name,
          },
          attribution: friendbuyAttribution,
        }).catch((error) => {
          console.warn("Signup Friendbuy event failed", {
            requestId,
            tenantId: tenant.id,
            patientId: patientIdForTermsAcceptance,
            error: error instanceof Error ? error.message : String(error),
          });
        });
      }

      // Optionally sign the new user in and return a session so the caller is
      // authenticated immediately (account-first checkout). The server knows the
      // generated password, so it can establish the session without exposing it.
      let session: {
        access_token?: string;
        refresh_token?: string;
        expires_in?: number;
        expires_at?: number;
      } | null = null;
      if (body.return_session) {
        const { data: signInData, error: signInError } = await supabase.auth
          .signInWithPassword({
            email: normalizedEmail,
            password: passwordToUse,
          });
        if (signInError) {
          console.warn("Signup return_session sign-in failed", {
            message: signInError.message,
          });
        } else if (signInData.session) {
          session = {
            access_token: signInData.session.access_token,
            refresh_token: signInData.session.refresh_token,
            expires_in: signInData.session.expires_in,
            expires_at: signInData.session.expires_at,
          };
        }
      }

      return jsonResponse(
        {
          message: "Account created successfully",
          data: {
            user_id: authData.user.id,
            email: authData.user.email,
            ...(session ? { session } : {}),
          },
        },
        201,
      );
    }

    // POST /auth/passkey/register/options - Generate registration options for
    // the currently authenticated patient and tenant.
    if (req.method === "POST" && path === "/auth/passkey/register/options") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const featureError = await requirePasskeyFeature(tenant.id);
      if (featureError) return featureError;

      const { patient, user, error: authError } =
        await getAuthenticatedPatient();
      if (authError) return authError;
      if (!patient || !user || patient.tenant_id !== tenant.id) {
        return errorResponse(
          "TENANT_MISMATCH",
          "Authenticated patient does not belong to this tenant",
          403,
        );
      }

      const passkeyConfig = await getTenantPasskeyConfig(tenant.id);
      if (!passkeyConfig) {
        return errorResponse(
          "PASSKEYS_NOT_CONFIGURED",
          "Passkeys are not configured for this tenant",
          503,
        );
      }
      const originCheck = validatePasskeyRequestOrigin(passkeyConfig);
      if (originCheck.error) return originCheck.error;

      const { data: existingPasskeys, error: passkeyLookupError } =
        await supabaseAdmin
          .from("patient_passkeys")
          .select("credential_id, transports")
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id);
      if (passkeyLookupError) {
        console.error("Passkey lookup failed", passkeyLookupError);
        return errorResponse(
          "PASSKEY_ERROR",
          "Could not generate registration options",
          500,
        );
      }

      const webauthnUserIdBytes = await sha256Bytes(
        `${tenant.id}:${patient.id}`,
      );
      const options = await generateRegistrationOptions({
        rpName: passkeyConfig.rp_name,
        rpID: passkeyConfig.rp_id,
        userID: webauthnUserIdBytes,
        userName: patient.email,
        userDisplayName:
          `${patient.first_name ?? ""} ${patient.last_name ?? ""}`.trim() ||
          patient.email,
        attestationType: "none",
        excludeCredentials: (existingPasskeys ?? []).map(
          (passkey: {
            credential_id: string;
            transports: AuthenticatorTransportFuture[] | null;
          }) => ({
            id: passkey.credential_id,
            transports: passkey.transports ?? undefined,
          }),
        ),
        authenticatorSelection: {
          residentKey: "preferred",
          userVerification: "required",
        },
        supportedAlgorithmIDs: [-7, -257],
      });

      const challengeRow = await storePasskeyChallenge({
        tenantId: tenant.id,
        patientId: patient.id,
        type: "registration",
        challenge: options.challenge,
        origin: originCheck.origin!,
        rpId: passkeyConfig.rp_id,
        webauthnUserId: options.user.id,
      });
      if (!challengeRow) {
        return errorResponse(
          "PASSKEY_ERROR",
          "Could not store registration challenge",
          500,
        );
      }

      return jsonResponse({
        data: { ...options, challenge_id: challengeRow.id },
      });
    }

    // POST /auth/passkey/register/verify - Verify registration and persist the
    // credential for the authenticated patient.
    if (req.method === "POST" && path === "/auth/passkey/register/verify") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }
      const featureError = await requirePasskeyFeature(tenant.id);
      if (featureError) return featureError;

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;
      if (!patient || patient.tenant_id !== tenant.id) {
        return errorResponse(
          "TENANT_MISMATCH",
          "Authenticated patient does not belong to this tenant",
          403,
        );
      }

      const passkeyConfig = await getTenantPasskeyConfig(tenant.id);
      if (!passkeyConfig) {
        return errorResponse(
          "PASSKEYS_NOT_CONFIGURED",
          "Passkeys are not configured for this tenant",
          503,
        );
      }
      const originCheck = validatePasskeyRequestOrigin(passkeyConfig);
      if (originCheck.error) return originCheck.error;

      let parsedBody: unknown;
      try {
        parsedBody = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }
      const response = getPasskeyCredentialPayload(
        parsedBody,
      ) as RegistrationResponseJSON;
      const challengeId = getPasskeyChallengeId(parsedBody);
      if (!isRecord(response) || typeof response.id !== "string") {
        console.warn("Passkey registration response missing credential", {
          hasChallengeId: Boolean(challengeId),
          bodyKeys: isRecord(parsedBody) ? Object.keys(parsedBody) : [],
        });
        return errorResponse(
          "INVALID_PASSKEY_RESPONSE",
          "A valid passkey registration response is required",
          400,
        );
      }
      const challenge = parseWebAuthnChallenge(response);
      if (!challenge) {
        return errorResponse(
          "INVALID_PASSKEY_RESPONSE",
          "Passkey response is missing a challenge",
          400,
        );
      }

      const challengeRow = await consumePasskeyChallenge({
        tenantId: tenant.id,
        type: "registration",
        challenge,
        challengeId,
      });
      if (
        !challengeRow ||
        challengeRow.patient_id !== patient.id ||
        challengeRow.origin !== originCheck.origin ||
        challengeRow.rp_id !== passkeyConfig.rp_id
      ) {
        return errorResponse(
          "INVALID_CHALLENGE",
          "Invalid or expired passkey challenge",
          400,
        );
      }

      let verification;
      try {
        verification = await verifyRegistrationResponse({
          response,
          expectedChallenge: challengeRow.challenge,
          expectedOrigin: challengeRow.origin,
          expectedRPID: passkeyConfig.rp_id,
          requireUserVerification: true,
          supportedAlgorithmIDs: [-7, -257],
        });
      } catch (error) {
        console.error("Passkey registration verification failed", error);
        return errorResponse(
          "PASSKEY_VERIFICATION_FAILED",
          "Could not verify passkey registration",
          400,
        );
      }

      if (!verification.verified) {
        return errorResponse(
          "PASSKEY_VERIFICATION_FAILED",
          "Could not verify passkey registration",
          400,
        );
      }

      const { credential, credentialDeviceType, credentialBackedUp } =
        verification.registrationInfo;
      const { error: insertCredentialError } = await supabaseAdmin
        .from("patient_passkeys")
        .insert({
          tenant_id: tenant.id,
          patient_id: patient.id,
          credential_id: credential.id,
          credential_public_key: bytesToBase64Url(credential.publicKey),
          webauthn_user_id: challengeRow.webauthn_user_id,
          counter: credential.counter,
          transports: credential.transports ?? [],
          device_type: credentialDeviceType,
          backed_up: credentialBackedUp,
          last_used_at: null,
        });

      if (insertCredentialError) {
        console.error(
          "Passkey credential insert failed",
          insertCredentialError,
        );
        return errorResponse(
          "PASSKEY_ERROR",
          "Could not store passkey credential",
          500,
        );
      }

      return jsonResponse({
        message: "Passkey registered successfully",
        data: { verified: true },
      });
    }

    // POST /auth/passkey/authenticate/options - Generate authentication options
    // for a tenant. If email is omitted, discoverable credentials are allowed.
    if (
      req.method === "POST" && path === "/auth/passkey/authenticate/options"
    ) {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const featureError = await requirePasskeyFeature(tenant.id);
      if (featureError) return featureError;

      const passkeyConfig = await getTenantPasskeyConfig(tenant.id);
      if (!passkeyConfig) {
        return errorResponse(
          "PASSKEYS_NOT_CONFIGURED",
          "Passkeys are not configured for this tenant",
          503,
        );
      }
      const originCheck = validatePasskeyRequestOrigin(passkeyConfig);
      if (originCheck.error) return originCheck.error;

      let body: { email?: string } = {};
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const email = body.email?.toLowerCase().trim();
      if (email && !isValidEmail(email)) {
        return errorResponse("INVALID_EMAIL", "A valid email is required", 400);
      }

      let patientId: string | null = null;
      let allowCredentials:
        | Array<{
          id: string;
          transports?: AuthenticatorTransportFuture[];
        }>
        | undefined;

      if (email) {
        const { data: authPatient } = await supabaseAdmin
          .from("patients")
          .select("id, access_status")
          .eq("tenant_id", tenant.id)
          .ilike("email", email)
          .maybeSingle();

        if (!authPatient || authPatient.access_status !== "active") {
          return errorResponse(
            "NO_PASSKEYS",
            "No passkeys are available for this account",
            404,
          );
        }

        patientId = authPatient.id;
        const { data: passkeys, error: passkeyLookupError } =
          await supabaseAdmin
            .from("patient_passkeys")
            .select("credential_id, transports")
            .eq("tenant_id", tenant.id)
            .eq("patient_id", authPatient.id);
        if (passkeyLookupError) {
          console.error("Passkey lookup failed", passkeyLookupError);
          return errorResponse(
            "PASSKEY_ERROR",
            "Could not generate authentication options",
            500,
          );
        }

        if (!passkeys || passkeys.length === 0) {
          return errorResponse(
            "NO_PASSKEYS",
            "No passkeys are available for this account",
            404,
          );
        }

        allowCredentials = passkeys.map(
          (passkey: {
            credential_id: string;
            transports: AuthenticatorTransportFuture[] | null;
          }) => ({
            id: passkey.credential_id,
            transports: passkey.transports ?? undefined,
          }),
        );
      }

      const options = await generateAuthenticationOptions({
        rpID: passkeyConfig.rp_id,
        allowCredentials,
        userVerification: "required",
      });

      const challengeRow = await storePasskeyChallenge({
        tenantId: tenant.id,
        patientId,
        type: "authentication",
        challenge: options.challenge,
        origin: originCheck.origin!,
        rpId: passkeyConfig.rp_id,
      });
      if (!challengeRow) {
        return errorResponse(
          "PASSKEY_ERROR",
          "Could not store authentication challenge",
          500,
        );
      }

      return jsonResponse({
        data: { ...options, challenge_id: challengeRow.id },
      });
    }

    // POST /auth/passkey/authenticate/verify - Verify a passkey assertion and
    // return the same tenant session shape as the existing auth flows.
    if (req.method === "POST" && path === "/auth/passkey/authenticate/verify") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const featureError = await requirePasskeyFeature(tenant.id);
      if (featureError) return featureError;

      const passkeyConfig = await getTenantPasskeyConfig(tenant.id);
      if (!passkeyConfig) {
        return errorResponse(
          "PASSKEYS_NOT_CONFIGURED",
          "Passkeys are not configured for this tenant",
          503,
        );
      }
      const originCheck = validatePasskeyRequestOrigin(passkeyConfig);
      if (originCheck.error) return originCheck.error;

      let parsedBody: unknown;
      try {
        parsedBody = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }
      const response = getPasskeyCredentialPayload(
        parsedBody,
      ) as AuthenticationResponseJSON;
      const challengeId = getPasskeyChallengeId(parsedBody);
      if (!isRecord(response) || typeof response.id !== "string") {
        console.warn("Passkey authentication response missing credential", {
          hasChallengeId: Boolean(challengeId),
          bodyKeys: isRecord(parsedBody) ? Object.keys(parsedBody) : [],
        });
        return errorResponse(
          "INVALID_PASSKEY_RESPONSE",
          "A valid passkey authentication response is required",
          400,
        );
      }
      const challenge = parseWebAuthnChallenge(response);
      if (!challenge) {
        return errorResponse(
          "INVALID_PASSKEY_RESPONSE",
          "Passkey response is missing a challenge",
          400,
        );
      }

      const challengeRow = await consumePasskeyChallenge({
        tenantId: tenant.id,
        type: "authentication",
        challenge,
        challengeId,
      });
      if (
        !challengeRow ||
        challengeRow.origin !== originCheck.origin ||
        challengeRow.rp_id !== passkeyConfig.rp_id
      ) {
        return errorResponse(
          "INVALID_CHALLENGE",
          "Invalid or expired passkey challenge",
          400,
        );
      }

      const { data: passkey, error: passkeyError } = await supabaseAdmin
        .from("patient_passkeys")
        .select(
          "id, tenant_id, patient_id, credential_id, credential_public_key, counter, transports, patient:patients(id, auth_user_id, access_status, email, first_name, last_name)",
        )
        .eq("tenant_id", tenant.id)
        .eq("credential_id", response.id)
        .maybeSingle();

      if (passkeyError) {
        console.error("Passkey credential lookup failed", passkeyError);
        return errorResponse("PASSKEY_ERROR", "Could not verify passkey", 500);
      }

      const storedPasskey = passkey as PatientPasskeyRow | null;
      if (
        !storedPasskey ||
        !storedPasskey.patient ||
        storedPasskey.patient.access_status !== "active" ||
        !storedPasskey.patient.auth_user_id ||
        (challengeRow.patient_id &&
          challengeRow.patient_id !== storedPasskey.patient_id)
      ) {
        return errorResponse(
          "INVALID_PASSKEY",
          "Passkey is not valid for this tenant",
          401,
        );
      }

      let verification;
      try {
        verification = await verifyAuthenticationResponse({
          response,
          expectedChallenge: challengeRow.challenge,
          expectedOrigin: challengeRow.origin,
          expectedRPID: passkeyConfig.rp_id,
          requireUserVerification: true,
          credential: {
            id: storedPasskey.credential_id,
            publicKey: base64UrlToBytes(storedPasskey.credential_public_key),
            counter: storedPasskey.counter,
            transports: storedPasskey.transports ?? undefined,
          },
        });
      } catch (error) {
        console.error("Passkey authentication verification failed", error);
        return errorResponse(
          "PASSKEY_VERIFICATION_FAILED",
          "Could not verify passkey authentication",
          400,
        );
      }

      if (!verification.verified) {
        return errorResponse(
          "PASSKEY_VERIFICATION_FAILED",
          "Could not verify passkey authentication",
          400,
        );
      }

      const { error: updateCounterError } = await supabaseAdmin
        .from("patient_passkeys")
        .update({
          counter: verification.authenticationInfo.newCounter,
          last_used_at: new Date().toISOString(),
        })
        .eq("id", storedPasskey.id)
        .eq("tenant_id", tenant.id);
      if (updateCounterError) {
        console.error("Passkey counter update failed", updateCounterError);
        return errorResponse("PASSKEY_ERROR", "Could not verify passkey", 500);
      }

      const session = await issuePatientSessionForEmail({
        email: storedPasskey.patient.email,
        patient: {
          id: storedPasskey.patient.id,
          first_name: storedPasskey.patient.first_name,
          last_name: storedPasskey.patient.last_name,
        },
        logContext: "Passkey authentication",
      });
      if (!session) {
        return errorResponse("AUTH_ERROR", "Could not establish session", 500);
      }

      return jsonResponse({
        message: "Signed in successfully",
        data: session,
      });
    }

    // POST /auth/signin - Sign in a patient
    if (req.method === "POST" && path === "/auth/signin") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: { email?: string; password?: string };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { email, password } = body;

      if (!email || !password) {
        return errorResponse(
          "MISSING_FIELDS",
          "Email and password are required",
          400,
        );
      }

      // First verify patient exists for this tenant
      const { data: patient } = await supabaseAdmin
        .from("patients")
        .select("id, auth_user_id, access_status, first_name, last_name")
        .eq("tenant_id", tenant.id)
        .eq("email", email.toLowerCase())
        .maybeSingle();

      if (!patient || !patient.auth_user_id) {
        return errorResponse(
          "INVALID_CREDENTIALS",
          "Invalid email or password",
          401,
        );
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      const normalizedSigninEmail = email.toLowerCase().trim();

      // Sign in with Supabase Auth
      const { data: authData, error: authError } = await supabase.auth
        .signInWithPassword({
          email: normalizedSigninEmail,
          password,
        });

      if (authError) {
        console.error("Auth signin error:", authError);
        return errorResponse(
          "INVALID_CREDENTIALS",
          "Invalid email or password",
          401,
        );
      }

      return jsonResponse({
        message: "Signed in successfully",
        data: {
          access_token: authData.session?.access_token,
          refresh_token: authData.session?.refresh_token,
          expires_in: authData.session?.expires_in,
          expires_at: authData.session?.expires_at,
          user: {
            id: authData.user.id,
            email: authData.user.email,
            first_name: patient.first_name,
            last_name: patient.last_name,
            patient_id: patient.id,
          },
        },
      });
    }

    // POST /auth/otp/request - Send a one-time passcode for passwordless sign-in.
    // Always responds 200 (does not reveal whether the email has an account).
    if (req.method === "POST" && path === "/auth/otp/request") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse("TENANT_NOT_FOUND", "Tenant not found", 404);
      }

      let otpBody: { email?: string } = {};
      try {
        otpBody = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }
      const otpEmail = otpBody.email?.toLowerCase().trim();
      if (!otpEmail || !isValidEmail(otpEmail)) {
        return errorResponse("INVALID_EMAIL", "A valid email is required", 400);
      }

      // Generic OK response — used for both "sent" and "no account" to avoid
      // leaking which emails exist.
      const genericOk = () =>
        jsonResponse({
          message: "If an account exists, a code has been sent.",
        });

      const { data: otpPatient } = await supabaseAdmin
        .from("patients")
        .select("id, auth_user_id, access_status, first_name")
        .eq("tenant_id", tenant.id)
        .ilike("email", otpEmail)
        .maybeSingle();

      if (
        !otpPatient || !otpPatient.auth_user_id ||
        otpPatient.access_status !== "active"
      ) {
        return genericOk();
      }

      // Rate limit: cap requests per email per hour.
      const oneHourAgo = new Date(Date.now() - 60 * 60 * 1000).toISOString();
      const { count: recentCount } = await supabaseAdmin
        .from("patient_auth_otps")
        .select("id", { count: "exact", head: true })
        .eq("tenant_id", tenant.id)
        .ilike("email", otpEmail)
        .gte("created_at", oneHourAgo);
      if ((recentCount ?? 0) >= PATIENT_AUTH_OTP_MAX_REQUESTS_PER_HOUR) {
        // Silently succeed to avoid revealing the rate-limit to attackers.
        return genericOk();
      }

      // Invalidate prior unused codes for this email, then store the new one
      // (hashed). Single active code at a time keeps verification simple.
      await supabaseAdmin
        .from("patient_auth_otps")
        .update({ used_at: new Date().toISOString() })
        .eq("tenant_id", tenant.id)
        .ilike("email", otpEmail)
        .is("used_at", null);

      const code = generateNumericOtp();
      const codeHash = await sha256Hex(`${tenant.id}:${otpEmail}:${code}`);
      const expiresAt = new Date(Date.now() + PATIENT_AUTH_OTP_TTL_MS)
        .toISOString();

      const { error: insertOtpError } = await supabaseAdmin
        .from("patient_auth_otps")
        .insert({
          tenant_id: tenant.id,
          patient_id: otpPatient.id,
          auth_user_id: otpPatient.auth_user_id,
          email: otpEmail,
          code_hash: codeHash,
          expires_at: expiresAt,
        });
      if (insertOtpError) {
        console.error("OTP insert failed", insertOtpError);
        return errorResponse("OTP_ERROR", "Could not send code", 500);
      }

      try {
        await sendEmailViaTenantDistribution({
          supabaseClient: supabaseAdmin,
          tenantId: tenant.id,
          to: otpEmail,
          subject: "Your sign-in code",
          html: `
            <div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto;">
              <h1 style="font-size: 20px; color: #1f2937;">Your sign-in code</h1>
              <p style="color: #374151;">Use this code to sign in. It expires in 10 minutes.</p>
              <div style="background: #f3f4f6; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
                <span style="font-size: 28px; letter-spacing: 6px; font-weight: bold; color: #111827;">${code}</span>
              </div>
              <p style="color: #6b7280;">If you didn't request this, you can ignore this email.</p>
            </div>
          `,
        });
      } catch (e) {
        console.error("OTP email send failed", e);
        // Don't leak send failures; the code is stored and can be retried.
      }

      // Test-mode convenience: for non-live test-domain emails only, return the
      // code so automated tests can verify email without an inbox. Real domains
      // never receive the code in the response.
      if (isTestDomainEmail(otpEmail)) {
        return jsonResponse({
          message: "If an account exists, a code has been sent.",
          dev_code: code,
        });
      }

      return genericOk();
    }

    // POST /auth/otp/verify - Verify a one-time passcode and issue a session.
    if (req.method === "POST" && path === "/auth/otp/verify") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse("TENANT_NOT_FOUND", "Tenant not found", 404);
      }

      let vBody: { email?: string; code?: string } = {};
      try {
        vBody = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }
      const vEmail = vBody.email?.toLowerCase().trim();
      const vCode = vBody.code?.trim();
      if (!vEmail || !vCode) {
        return errorResponse(
          "MISSING_FIELDS",
          "Email and code are required",
          400,
        );
      }

      const nowIso = new Date().toISOString();
      const { data: otpRow } = await supabaseAdmin
        .from("patient_auth_otps")
        .select(
          "id, patient_id, auth_user_id, code_hash, expires_at, attempt_count",
        )
        .eq("tenant_id", tenant.id)
        .ilike("email", vEmail)
        .is("used_at", null)
        .gt("expires_at", nowIso)
        .order("created_at", { ascending: false })
        .limit(1)
        .maybeSingle();

      if (!otpRow) {
        return errorResponse("INVALID_CODE", "Invalid or expired code", 400);
      }
      if (otpRow.attempt_count >= PATIENT_AUTH_OTP_MAX_ATTEMPTS) {
        // Burn the code on too many attempts.
        await supabaseAdmin
          .from("patient_auth_otps")
          .update({ used_at: nowIso })
          .eq("id", otpRow.id);
        return errorResponse(
          "INVALID_CODE",
          "Too many attempts. Request a new code.",
          429,
        );
      }

      const candidateHash = await sha256Hex(`${tenant.id}:${vEmail}:${vCode}`);
      if (candidateHash !== otpRow.code_hash) {
        await supabaseAdmin
          .from("patient_auth_otps")
          .update({ attempt_count: otpRow.attempt_count + 1 })
          .eq("id", otpRow.id);
        return errorResponse("INVALID_CODE", "Invalid or expired code", 400);
      }

      // Correct code → mark used (single-use) and issue a session via a
      // server-generated magic link (no password needed).
      await supabaseAdmin
        .from("patient_auth_otps")
        .update({ used_at: nowIso })
        .eq("id", otpRow.id);

      // Verifying an OTP proves control of the email — stamp it verified so the
      // post-payment contact-validation gate (PP-566) is satisfied and providers
      // receive a validated email.
      await supabaseAdmin
        .from("patients")
        .update({ email_verified_at: nowIso })
        .eq("id", otpRow.patient_id)
        .eq("tenant_id", tenant.id);

      // Email is now verified → release any orders the contact-validation gate is
      // holding pre-provider, so they advance toward the questionnaire without
      // depending on the UI calling /orders/:id/resume. Fire-and-forget.
      resumeHeldOrdersForPatient({
        supabaseAdmin,
        supabaseUrl,
        serviceRoleKey: supabaseServiceRoleKey,
        tenantId: tenant.id,
        patientId: otpRow.patient_id,
      }).catch((e) =>
        console.warn("OTP verify: resumeHeldOrdersForPatient failed", {
          patientId: otpRow.patient_id,
          error: e instanceof Error ? e.message : String(e),
        })
      );

      const { data: linkData, error: linkError } = await supabaseAdmin.auth
        .admin
        .generateLink({ type: "magiclink", email: vEmail });
      const hashedToken = linkData?.properties?.hashed_token;
      if (linkError || !hashedToken) {
        console.error("OTP generateLink failed", linkError);
        return errorResponse("AUTH_ERROR", "Could not establish session", 500);
      }

      const { data: verifyData, error: verifyError } = await supabase.auth
        .verifyOtp({ type: "magiclink", token_hash: hashedToken });
      if (verifyError || !verifyData.session) {
        console.error("OTP verifyOtp failed", verifyError);
        return errorResponse("AUTH_ERROR", "Could not establish session", 500);
      }

      const { data: vPatient } = await supabaseAdmin
        .from("patients")
        .select("id, first_name, last_name")
        .eq("id", otpRow.patient_id)
        .maybeSingle();

      return jsonResponse({
        message: "Signed in successfully",
        data: {
          access_token: verifyData.session.access_token,
          refresh_token: verifyData.session.refresh_token,
          expires_in: verifyData.session.expires_in,
          expires_at: verifyData.session.expires_at,
          user: {
            id: verifyData.user?.id,
            email: verifyData.user?.email,
            first_name: vPatient?.first_name,
            last_name: vPatient?.last_name,
            patient_id: otpRow.patient_id,
          },
        },
      });
    }

    // POST /auth/contact/update - Update the authenticated patient's email
    // and/or phone during the post-payment account & contact validation step
    // (PP-566). Changing the email clears email_verified_at (must re-verify via
    // OTP) and updates the Supabase Auth user email. Confirming/keeping the phone
    // can stamp phone_confirmed_at. This lets a patient fix an unvalidated email
    // or phone before the provider receives their contact info.
    if (req.method === "POST" && path === "/auth/contact/update") {
      const { patient, user, error: authError } =
        await getAuthenticatedPatient();
      if (authError) return authError;

      let cBody: { email?: string; phone?: string; confirm_phone?: boolean } =
        {};
      try {
        cBody = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const newEmail = cBody.email?.toLowerCase().trim();
      const newPhone = cBody.phone?.trim();
      const updates: Record<string, unknown> = {};
      let emailChanged = false;

      if (newEmail && newEmail !== patient!.email?.toLowerCase()) {
        if (!isValidEmail(newEmail)) {
          return errorResponse("INVALID_EMAIL", "Enter a valid email", 400);
        }
        // Block taking another patient's email within the tenant.
        const { data: emailTaken } = await supabaseAdmin
          .from("patients")
          .select("id")
          .eq("tenant_id", patient!.tenant_id)
          .ilike("email", newEmail)
          .neq("id", patient!.id)
          .maybeSingle();
        if (emailTaken) {
          return errorResponse(
            "EMAIL_IN_USE",
            "That email is already in use.",
            409,
          );
        }
        updates.email = newEmail;
        updates.email_verified_at = null; // must re-verify the new email
        emailChanged = true;
      }

      if (typeof newPhone === "string" && newPhone.length > 0) {
        updates.phone = newPhone;
        // Phone is attested (no SMS verification); stamp it confirmed unless the
        // caller explicitly opts out.
        updates.phone_confirmed_at = cBody.confirm_phone === false
          ? null
          : new Date().toISOString();
      }

      if (Object.keys(updates).length === 0) {
        return jsonResponse({
          message: "No changes",
          data: {
            email: patient!.email,
            email_verified: Boolean(patient!.email_verified_at),
          },
        });
      }

      // Update the Supabase Auth user email first when it changed; keep it
      // confirmed so the session stays valid (the OTP step re-verifies ownership).
      if (emailChanged && user?.id) {
        const { error: authUpdateError } = await supabaseAdmin.auth.admin
          .updateUserById(user.id, {
            email: newEmail,
            email_confirm: true,
          });
        if (authUpdateError) {
          console.error(
            "contact/update auth email change failed",
            authUpdateError,
          );
          return errorResponse(
            "AUTH_UPDATE_FAILED",
            "Could not update your email. Please try again.",
            500,
          );
        }
      }

      const { error: updateError } = await supabaseAdmin
        .from("patients")
        .update(updates)
        .eq("id", patient!.id)
        .eq("tenant_id", patient!.tenant_id);

      if (updateError) {
        console.error("contact/update patient update failed", updateError);
        return errorResponse(
          "UPDATE_FAILED",
          "Could not update your contact details.",
          500,
        );
      }

      return jsonResponse({
        message: "Contact details updated",
        data: {
          email: newEmail ?? patient!.email,
          phone: newPhone ?? undefined,
          email_verified: !emailChanged,
          requires_email_verification: emailChanged,
        },
      });
    }

    // POST /auth/signout - Sign out a patient
    if (req.method === "POST" && path === "/auth/signout") {
      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const { error } = await supabase.auth.signOut();

      if (error) {
        console.error("Auth signout error:", error);
        return errorResponse("SIGNOUT_ERROR", error.message, 500);
      }

      return jsonResponse({ message: "Signed out successfully" });
    }

    // POST /auth/oauth/resolve - Exchange a social-login (Google/Apple) session
    // for a tenant patient session. The UI authenticates with Supabase OAuth in
    // the browser, then sends that OAuth session's access token here. We verify
    // the email and map it to the patient in THIS tenant (emails are per-tenant).
    // No patient for that email in this tenant → blocked (no auto-create).
    if (req.method === "POST" && path === "/auth/oauth/resolve") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse("TENANT_NOT_FOUND", "Tenant not found", 404);
      }
      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      // Verify the OAuth session and get the authenticated email.
      const { data: { user: oauthUser }, error: oauthError } = await supabase
        .auth.getUser();
      if (oauthError || !oauthUser?.email) {
        return errorResponse(
          "UNAUTHORIZED",
          "Invalid or expired social login",
          401,
        );
      }
      const oauthEmail = oauthUser.email.toLowerCase().trim();

      // Resolve the patient in THIS tenant by email.
      const { data: oauthPatient } = await supabaseAdmin
        .from("patients")
        .select(
          "id, auth_user_id, access_status, first_name, last_name, email_verified_at",
        )
        .eq("tenant_id", tenant.id)
        .ilike("email", oauthEmail)
        .maybeSingle();

      if (!oauthPatient) {
        // Per product decision: block, do not auto-create on social login.
        return errorResponse(
          "NO_ACCOUNT",
          "No account found for this email. Please complete a purchase to create your account.",
          404,
        );
      }
      if (oauthPatient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${oauthPatient.access_status}.`,
          403,
        );
      }

      // Link the patient to an auth user if missing (e.g. account created without
      // one) — prefer the existing linked auth user for the session.
      let sessionAuthUserId = oauthPatient.auth_user_id;
      if (!sessionAuthUserId) {
        await supabaseAdmin
          .from("patients")
          .update({ auth_user_id: oauthUser.id })
          .eq("id", oauthPatient.id);
        sessionAuthUserId = oauthUser.id;
      }

      // A Google/Apple session whose email maps to this tenant patient proves
      // ownership of that email — equivalent to an OTP verify. Stamp it so the
      // post-payment contact-validation gate (PP-566) is satisfied, and release
      // any orders that gate is holding (same semantics as /auth/otp/verify).
      if (!oauthPatient.email_verified_at) {
        await supabaseAdmin
          .from("patients")
          .update({ email_verified_at: new Date().toISOString() })
          .eq("id", oauthPatient.id)
          .eq("tenant_id", tenant.id);
      }
      resumeHeldOrdersForPatient({
        supabaseAdmin,
        supabaseUrl,
        serviceRoleKey: supabaseServiceRoleKey,
        tenantId: tenant.id,
        patientId: oauthPatient.id,
      }).catch((e) =>
        console.warn("OAuth resolve: resumeHeldOrdersForPatient failed", {
          patientId: oauthPatient.id,
          error: e instanceof Error ? e.message : String(e),
        })
      );

      // Issue a session for the patient's auth user (no password needed) via a
      // server-generated magic link, same technique as email OTP.
      const { data: linkData, error: linkError } = await supabaseAdmin.auth
        .admin
        .generateLink({ type: "magiclink", email: oauthEmail });
      const hashedToken = linkData?.properties?.hashed_token;
      if (linkError || !hashedToken) {
        console.error("OAuth resolve generateLink failed", linkError);
        return errorResponse("AUTH_ERROR", "Could not establish session", 500);
      }
      const { data: verifyData, error: verifyError } = await supabase.auth
        .verifyOtp({ type: "magiclink", token_hash: hashedToken });
      if (verifyError || !verifyData.session) {
        console.error("OAuth resolve verifyOtp failed", verifyError);
        return errorResponse("AUTH_ERROR", "Could not establish session", 500);
      }

      return jsonResponse({
        message: "Signed in successfully",
        data: {
          access_token: verifyData.session.access_token,
          refresh_token: verifyData.session.refresh_token,
          expires_in: verifyData.session.expires_in,
          expires_at: verifyData.session.expires_at,
          user: {
            id: verifyData.user?.id,
            email: verifyData.user?.email,
            first_name: oauthPatient.first_name,
            last_name: oauthPatient.last_name,
            patient_id: oauthPatient.id,
            // Always true after this endpoint succeeds (stamped above) — keeps
            // the client-side user in sync without waiting for /auth/me.
            email_verified: true,
          },
        },
      });
    }

    // POST /auth/forgot-password - Request password reset
    if (req.method === "POST" && path === "/auth/forgot-password") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: { email?: string; redirect_url?: string };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { email, redirect_url } = body;

      if (!email) {
        return errorResponse("MISSING_FIELDS", "Email is required", 400);
      }

      if (!redirect_url?.trim()) {
        return errorResponse("MISSING_FIELDS", "redirect_url is required", 400);
      }

      if (!isValidEmail(email)) {
        return errorResponse("INVALID_EMAIL", "Invalid email format", 400);
      }

      const parsedRedirectUrl = parseAbsoluteHttpUrl(redirect_url.trim());
      if (!parsedRedirectUrl) {
        return errorResponse(
          "INVALID_REDIRECT_URL",
          "redirect_url must be an absolute http or https URL",
          400,
        );
      }

      const normalizedEmail = email.toLowerCase();
      const forgotPasswordLogContext = {
        requestId,
        path,
        tenantSlugs,
        redirectUrlOrigin: parsedRedirectUrl.origin,
        redirectUrlPathname: parsedRedirectUrl.pathname,
        email: maskEmail(normalizedEmail),
      };

      console.info(
        "Patient forgot-password request received",
        forgotPasswordLogContext,
      );

      // Verify patient exists for this tenant (silent check - don't reveal if email exists)
      const { data: patient } = await supabaseAdmin
        .from("patients")
        .select("id, auth_user_id")
        .eq("tenant_id", tenant.id)
        .eq("email", normalizedEmail)
        .maybeSingle();

      // Always return success to prevent email enumeration
      if (!patient?.auth_user_id) {
        console.info(
          "Patient forgot-password request completed without matching auth user",
          {
            ...forgotPasswordLogContext,
            tenantId: tenant.id,
            patientFound: Boolean(patient),
            hasAuthUserId: Boolean(patient?.auth_user_id),
          },
        );
        return jsonResponse({
          message:
            "If an account with this email exists, a password reset link has been sent",
        });
      }

      try {
        const nowIso = dateTime().toISOString();
        const expiresAtIso = new Date(
          Date.now() + PATIENT_PASSWORD_RESET_TOKEN_TTL_MS,
        ).toISOString();
        const resetToken = generatePasswordResetToken();
        const resetTokenHash = await sha256Hex(resetToken);

        const { error: invalidateExistingError } = await supabaseAdmin
          .from("patient_password_reset_tokens")
          .update({
            used_at: nowIso,
          })
          .eq("tenant_id", tenant.id)
          .eq("patient_id", patient.id)
          .is("used_at", null);

        if (invalidateExistingError) {
          console.error(
            "Failed to invalidate previous patient password reset tokens",
            {
              ...forgotPasswordLogContext,
              tenantId: tenant.id,
              patientId: patient.id,
              error: normalizeErrorForLog(invalidateExistingError),
            },
          );
        }

        const { error: insertResetTokenError } = await supabaseAdmin
          .from("patient_password_reset_tokens")
          .insert({
            tenant_id: tenant.id,
            patient_id: patient.id,
            auth_user_id: patient.auth_user_id,
            token_hash: resetTokenHash,
            redirect_url: parsedRedirectUrl.toString(),
            expires_at: expiresAtIso,
          });

        if (insertResetTokenError) {
          console.error("Password reset token persistence error:", {
            ...forgotPasswordLogContext,
            tenantId: tenant.id,
            patientId: patient.id,
            authUserId: patient.auth_user_id,
            expiresAtIso,
            error: normalizeErrorForLog(insertResetTokenError),
          });
        } else {
          console.info("Patient password reset token persisted", {
            ...forgotPasswordLogContext,
            tenantId: tenant.id,
            patientId: patient.id,
            authUserId: patient.auth_user_id,
            expiresAtIso,
          });

          const patientResetLink = buildPatientPasswordResetUrl(
            parsedRedirectUrl.toString(),
            resetToken,
          );

          const emailContent = buildPatientPasswordResetEmail({
            tenantName: (tenant as { name?: string | null }).name ?? null,
            recoveryLink: patientResetLink,
          });

          console.info("Attempting patient password reset email delivery", {
            ...forgotPasswordLogContext,
            tenantId: tenant.id,
            patientId: patient.id,
            authUserId: patient.auth_user_id,
          });

          const deliveryResult = await sendEmailViaTenantDistribution({
            supabaseClient: supabaseAdmin,
            tenantId: tenant.id,
            to: normalizedEmail,
            subject: emailContent.subject,
            html: emailContent.html,
            logContext: {
              ...forgotPasswordLogContext,
              flow: "patient_forgot_password",
              tenantId: tenant.id,
              patientId: patient.id,
              authUserId: patient.auth_user_id,
            },
          });

          console.info("Patient password reset email delivery completed", {
            ...forgotPasswordLogContext,
            tenantId: tenant.id,
            patientId: patient.id,
            authUserId: patient.auth_user_id,
            integrationKey: deliveryResult.integrationKey,
          });
        }
      } catch (resetError) {
        console.error("Password reset delivery error:", {
          ...forgotPasswordLogContext,
          tenantId: tenant.id,
          patientId: patient.id,
          authUserId: patient.auth_user_id,
          error: normalizeErrorForLog(resetError),
        });
        // Don't expose the error to prevent information leakage
      }

      return jsonResponse({
        message:
          "If an account with this email exists, a password reset link has been sent",
      });
    }

    // POST /auth/reset-password - Reset password with token
    if (req.method === "POST" && path === "/auth/reset-password") {
      let body: {
        reset_token?: string;
        access_token?: string;
        refresh_token?: string;
        new_password?: string;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { reset_token, access_token, refresh_token, new_password } = body;

      if (!new_password) {
        return errorResponse("MISSING_FIELDS", "New password is required", 400);
      }

      const passwordCheck = isValidPassword(new_password);
      if (!passwordCheck.valid) {
        return errorResponse("WEAK_PASSWORD", passwordCheck.message!, 400);
      }

      if (reset_token?.trim()) {
        const nowIso = dateTime().toISOString();
        const resetTokenHash = await sha256Hex(reset_token.trim());

        const { data: claimedResetToken, error: claimError } =
          await supabaseAdmin
            .from("patient_password_reset_tokens")
            .update({
              used_at: nowIso,
            })
            .eq("token_hash", resetTokenHash)
            .is("used_at", null)
            .gt("expires_at", nowIso)
            .select("id, auth_user_id")
            .maybeSingle();

        if (claimError) {
          console.error("Password reset token claim error:", claimError);
          return errorResponse("UPDATE_ERROR", claimError.message, 500);
        }

        if (!claimedResetToken?.auth_user_id) {
          return errorResponse(
            "INVALID_TOKEN",
            "Invalid or expired reset token",
            401,
          );
        }

        const { error: adminUpdateError } = await supabaseAdmin.auth.admin
          .updateUserById(
            claimedResetToken.auth_user_id,
            {
              password: new_password,
            },
          );

        if (adminUpdateError) {
          console.error("Admin password update error:", adminUpdateError);
          return errorResponse("UPDATE_ERROR", adminUpdateError.message, 500);
        }

        return jsonResponse({ message: "Password updated successfully" });
      }

      // If tokens provided, set the session first
      if (access_token && refresh_token) {
        const { error: sessionError } = await supabase.auth.setSession({
          access_token,
          refresh_token,
        });

        if (sessionError) {
          console.error("Session set error:", sessionError);
          return errorResponse(
            "INVALID_TOKEN",
            "Invalid or expired reset token",
            401,
          );
        }
      } else if (!authHeader) {
        return errorResponse("MISSING_FIELDS", "reset_token is required", 400);
      }

      // Update the password
      const { error: updateError } = await supabase.auth.updateUser({
        password: new_password,
      });

      if (updateError) {
        console.error("Password update error:", updateError);
        return errorResponse("UPDATE_ERROR", updateError.message, 500);
      }

      return jsonResponse({ message: "Password updated successfully" });
    }

    // POST /auth/refresh - Refresh access token
    if (req.method === "POST" && path === "/auth/refresh") {
      let body: { refresh_token?: string };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { refresh_token } = body;

      if (!refresh_token) {
        return errorResponse(
          "MISSING_FIELDS",
          "Refresh token is required",
          400,
        );
      }

      const { data, error } = await supabase.auth.refreshSession({
        refresh_token,
      });

      if (error) {
        console.error("Token refresh error:", error);
        return errorResponse(
          "REFRESH_ERROR",
          "Invalid or expired refresh token",
          401,
        );
      }

      return jsonResponse({
        data: {
          access_token: data.session?.access_token,
          refresh_token: data.session?.refresh_token,
          expires_in: data.session?.expires_in,
          expires_at: data.session?.expires_at,
        },
      });
    }

    // GET /auth/me - Get current patient profile
    if (req.method === "GET" && path === "/auth/me") {
      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      // Get patient profile with shipping and billing address fields
      const { data: patient, error: patientError } = await supabase
        .from("patients")
        .select(
          `
          id, tenant_id, first_name, last_name, email, phone, date_of_birth,
          starting_weight, target_weight, email_verified_at,
          subscribed_to_email_marketing, subscribed_to_sms_marketing,
          shipping_first_name, shipping_last_name, shipping_company,
          shipping_address_line1, shipping_address_line2, shipping_city,
          shipping_state, shipping_postal_code, shipping_country, shipping_instructions,
          billing_first_name, billing_last_name, billing_company,
          billing_address_line1, billing_address_line2, billing_city,
          billing_state, billing_postal_code, billing_country,
          access_status, created_at
        `,
        )
        .eq("auth_user_id", user.id)
        .maybeSingle();

      if (patientError) {
        console.error("Patient fetch error:", patientError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient profile",
          500,
        );
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      const { data: intercomIntegration, error: intercomIntegrationError } =
        await supabaseAdmin
          .from("tenant_integrations")
          .select("settings")
          .eq("tenant_id", patient.tenant_id)
          .eq("integration_key", "intercom")
          .eq("is_enabled", true)
          .maybeSingle();

      if (intercomIntegrationError) {
        console.error(
          "Intercom integration fetch error:",
          intercomIntegrationError,
        );
      }

      const intercomBackendSecret = getStringSetting(
        (
          intercomIntegration as {
            settings?: Record<string, unknown> | null;
          } | null
        )?.settings,
        "backend_secret",
      );

      let patientIntercomUserHash: string | null = null;
      if (intercomBackendSecret) {
        try {
          patientIntercomUserHash = await intercomUserHash(
            user.id,
            intercomBackendSecret,
          );
        } catch (error) {
          console.error("Failed to generate Intercom user hash:", error);
        }
      }

      // Transform flat fields into nested address objects
      const responseData = {
        user_id: user.id,
        id: patient.id,
        tenant_id: patient.tenant_id,
        first_name: patient.first_name,
        last_name: patient.last_name,
        email: patient.email,
        email_verified: Boolean(patient.email_verified_at),
        phone: patient.phone,
        date_of_birth: patient.date_of_birth,
        starting_weight: patient.starting_weight,
        target_weight: patient.target_weight,
        subscribed_to_email_marketing: patient.subscribed_to_email_marketing,
        subscribed_to_sms_marketing: patient.subscribed_to_sms_marketing,
        shipping_address: {
          first_name: patient.shipping_first_name,
          last_name: patient.shipping_last_name,
          company: patient.shipping_company,
          line1: patient.shipping_address_line1,
          line2: patient.shipping_address_line2,
          city: patient.shipping_city,
          state: patient.shipping_state,
          postal_code: patient.shipping_postal_code,
          country: patient.shipping_country,
          instructions: patient.shipping_instructions,
        },
        billing_address: {
          first_name: patient.billing_first_name,
          last_name: patient.billing_last_name,
          company: patient.billing_company,
          line1: patient.billing_address_line1,
          line2: patient.billing_address_line2,
          city: patient.billing_city,
          state: patient.billing_state,
          postal_code: patient.billing_postal_code,
          country: patient.billing_country,
        },
        access_status: patient.access_status,
        created_at: patient.created_at,
        ...(patientIntercomUserHash
          ? { intercom_user_hash: patientIntercomUserHash }
          : {}),
      };

      return jsonResponse({
        data: responseData,
      });
    }

    // PATCH /auth/me - Update current patient profile
    if (req.method === "PATCH" && path === "/auth/me") {
      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      let body: {
        first_name?: string;
        last_name?: string;
        phone?: string;
        date_of_birth?: string;
        starting_weight?: number | null;
        target_weight?: number | null;
        subscribed_to_email_marketing?: boolean;
        subscribed_to_sms_marketing?: boolean;
        shipping_address?: {
          first_name?: string;
          last_name?: string;
          company?: string;
          line1?: string;
          line2?: string;
          city?: string;
          state?: string;
          postal_code?: string;
          country?: string;
          instructions?: string;
        };
        billing_address?: {
          first_name?: string;
          last_name?: string;
          company?: string;
          line1?: string;
          line2?: string;
          city?: string;
          state?: string;
          postal_code?: string;
          country?: string;
        };
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      // Only allow updating specific top-level profile fields
      const allowedFields = [
        "first_name",
        "last_name",
        "phone",
        "date_of_birth",
        "starting_weight",
        "target_weight",
        "subscribed_to_email_marketing",
        "subscribed_to_sms_marketing",
      ];

      const updateData: Record<string, unknown> = {};
      for (const field of allowedFields) {
        if (field in body) {
          updateData[field] = body[field as keyof typeof body];
        }
      }

      for (
        const marketingField of [
          "subscribed_to_email_marketing",
          "subscribed_to_sms_marketing",
        ] as const
      ) {
        if (
          marketingField in updateData &&
          typeof updateData[marketingField] !== "boolean"
        ) {
          return errorResponse(
            "INVALID_MARKETING_SUBSCRIPTION",
            `${marketingField} must be a boolean`,
            400,
          );
        }
      }

      if ("phone" in updateData) {
        const phoneValue = updateData.phone;
        if (typeof phoneValue === "string") {
          updateData.phone = normalizeUsPhoneDigits(phoneValue) || null;
        } else if (phoneValue == null) {
          updateData.phone = null;
        }
      }

      // Handle shipping_address object
      if (body.shipping_address) {
        const sa = body.shipping_address;
        if (sa.first_name !== undefined) {
          updateData.shipping_first_name = sa.first_name || null;
        }
        if (sa.last_name !== undefined) {
          updateData.shipping_last_name = sa.last_name || null;
        }
        if (sa.company !== undefined) {
          updateData.shipping_company = sa.company || null;
        }
        if (sa.line1 !== undefined) {
          updateData.shipping_address_line1 = sa.line1 || null;
        }
        if (sa.line2 !== undefined) {
          updateData.shipping_address_line2 = sa.line2 || null;
        }
        if (sa.city !== undefined) updateData.shipping_city = sa.city || null;
        if (sa.state !== undefined) {
          updateData.shipping_state = sa.state || null;
        }
        if (sa.postal_code !== undefined) {
          updateData.shipping_postal_code = sa.postal_code || null;
        }
        if (sa.country !== undefined) {
          updateData.shipping_country = sa.country || null;
        }
        if (sa.instructions !== undefined) {
          updateData.shipping_instructions = sa.instructions || null;
        }
      }

      // Handle billing_address object
      if (body.billing_address) {
        const ba = body.billing_address;
        if (ba.first_name !== undefined) {
          updateData.billing_first_name = ba.first_name || null;
        }
        if (ba.last_name !== undefined) {
          updateData.billing_last_name = ba.last_name || null;
        }
        if (ba.company !== undefined) {
          updateData.billing_company = ba.company || null;
        }
        if (ba.line1 !== undefined) {
          updateData.billing_address_line1 = ba.line1 || null;
        }
        if (ba.line2 !== undefined) {
          updateData.billing_address_line2 = ba.line2 || null;
        }
        if (ba.city !== undefined) updateData.billing_city = ba.city || null;
        if (ba.state !== undefined) updateData.billing_state = ba.state || null;
        if (ba.postal_code !== undefined) {
          updateData.billing_postal_code = ba.postal_code || null;
        }
        if (ba.country !== undefined) {
          updateData.billing_country = ba.country || null;
        }
      }

      console.log("PATCH /auth/me - updateData:", JSON.stringify(updateData));

      if (Object.keys(updateData).length === 0) {
        return errorResponse("NO_CHANGES", "No valid fields to update", 400);
      }

      // Get patient's tenant_id and existing address info for state validation
      const { data: patientForTenant } = await supabase
        .from("patients")
        .select("tenant_id, country, shipping_country, billing_country")
        .eq("auth_user_id", user.id)
        .single();

      if (patientForTenant) {
        // Validate shipping address state if being updated
        if ("shipping_state" in updateData) {
          const countryToCheck = (updateData.shipping_country as string) ||
            body.shipping_address?.country ||
            patientForTenant.shipping_country ||
            "US";
          const stateValidation = await validateStateAgainstTenant(
            supabaseAdmin,
            patientForTenant.tenant_id,
            updateData.shipping_state as string,
            countryToCheck,
          );
          if (!stateValidation.valid) {
            return errorResponse(
              "INVALID_SHIPPING_STATE",
              stateValidation.message!,
              400,
            );
          }
        }

        // Validate billing address state if being updated
        if ("billing_state" in updateData) {
          const countryToCheck = (updateData.billing_country as string) ||
            body.billing_address?.country ||
            patientForTenant.billing_country ||
            "US";
          const stateValidation = await validateStateAgainstTenant(
            supabaseAdmin,
            patientForTenant.tenant_id,
            updateData.billing_state as string,
            countryToCheck,
          );
          if (!stateValidation.valid) {
            return errorResponse(
              "INVALID_BILLING_STATE",
              stateValidation.message!,
              400,
            );
          }
        }
      }

      const { data: patient, error: updateError } = await supabase
        .from("patients")
        .update(updateData)
        .eq("auth_user_id", user.id)
        .select(
          `
          id, tenant_id, first_name, last_name, email, phone, date_of_birth, 
          starting_weight, target_weight,
          subscribed_to_email_marketing, subscribed_to_sms_marketing,
          shipping_first_name, shipping_last_name, shipping_company,
          shipping_address_line1, shipping_address_line2, shipping_city, 
          shipping_state, shipping_postal_code, shipping_country, shipping_instructions,
          billing_first_name, billing_last_name, billing_company,
          billing_address_line1, billing_address_line2, billing_city, 
          billing_state, billing_postal_code, billing_country,
          access_status
        `,
        )
        .single();

      if (updateError) {
        console.error("Patient update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update patient profile",
          500,
        );
      }

      return jsonResponse({
        message: "Profile updated successfully",
        data: {
          user_id: user.id,
          id: patient.id,
          tenant_id: patient.tenant_id,
          first_name: patient.first_name,
          last_name: patient.last_name,
          email: patient.email,
          phone: patient.phone,
          date_of_birth: patient.date_of_birth,
          starting_weight: patient.starting_weight,
          target_weight: patient.target_weight,
          subscribed_to_email_marketing: patient.subscribed_to_email_marketing,
          subscribed_to_sms_marketing: patient.subscribed_to_sms_marketing,
          shipping_address: {
            first_name: patient.shipping_first_name,
            last_name: patient.shipping_last_name,
            company: patient.shipping_company,
            line1: patient.shipping_address_line1,
            line2: patient.shipping_address_line2,
            city: patient.shipping_city,
            state: patient.shipping_state,
            postal_code: patient.shipping_postal_code,
            country: patient.shipping_country,
            instructions: patient.shipping_instructions,
          },
          billing_address: {
            first_name: patient.billing_first_name,
            last_name: patient.billing_last_name,
            company: patient.billing_company,
            line1: patient.billing_address_line1,
            line2: patient.billing_address_line2,
            city: patient.billing_city,
            state: patient.billing_state,
            postal_code: patient.billing_postal_code,
            country: patient.billing_country,
          },
          access_status: patient.access_status,
        },
      });
    }

    // GET /notifications - List pending in-app actions for the authenticated patient
    if (req.method === "GET" && path === "/notifications") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: orders, error: ordersError } = await supabase
        .from("orders")
        .select(
          `
          id,
          order_number,
          status_id,
          status_changed_at,
          created_at,
          updated_at,
          product:products (
            name
          ),
          order_statuses!inner (
            id,
            status_key,
            patient_status_label,
            patient_microcopy,
            patient_action_required,
            is_terminal,
            display_order,
            is_patient_visible
          )
        `,
        )
        .eq("patient_id", patient!.id)
        .eq("order_statuses.patient_action_required", true)
        .eq("order_statuses.is_patient_visible", true)
        .order("status_changed_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (ordersError) {
        console.error("Notifications fetch error:", ordersError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch notifications",
          500,
        );
      }

      const orderNotifications: PatientNotificationResponse[] = (orders || [])
        .map((order) => {
          const statusInfo = asSingle(
            order.order_statuses as
              | {
                id: string;
                status_key: string;
                patient_status_label: string | null;
                patient_microcopy: string | null;
                patient_action_required: boolean;
                is_terminal: boolean;
                display_order: number;
                is_patient_visible: boolean;
              }
              | {
                id: string;
                status_key: string;
                patient_status_label: string | null;
                patient_microcopy: string | null;
                patient_action_required: boolean;
                is_terminal: boolean;
                display_order: number;
                is_patient_visible: boolean;
              }[]
              | null,
          );
          const product = asSingle(
            order.product as
              | { name: string | null }
              | { name: string | null }[]
              | null,
          );

          const statusDetails = statusInfo
            ? {
              id: statusInfo.id,
              key: statusInfo.status_key,
              label: formatPatientStatusLabel(
                statusInfo.status_key,
                statusInfo.patient_status_label,
              ),
              description: statusInfo.patient_microcopy || null,
              action_required: statusInfo.patient_action_required,
              is_final: statusInfo.is_terminal,
              display_order: statusInfo.display_order,
            }
            : null;

          return {
            id: `order:${order.id}`,
            type: "order_action_required" as const,
            title: statusDetails?.label || "Action required",
            message: statusDetails?.description ||
              "There is an order update that needs your attention.",
            created_at: order.created_at,
            updated_at: order.updated_at,
            resource: {
              type: "order" as const,
              id: order.id,
              order_number: order.order_number,
              product_title: product?.name || null,
              status_changed_at: order.status_changed_at,
            },
          };
        });

      const { data: durableNotifications, error: durableNotificationsError } =
        await supabaseAdmin
          .from("patient_notifications")
          .select(
            `
            id,
            type,
            title,
            body,
            created_at,
            updated_at,
            provider_name,
            provider_patient_id,
            order_id,
            resource
          `,
          )
          .eq("patient_id", patient!.id)
          .is("read_at", null)
          .order("created_at", { ascending: false });

      if (durableNotificationsError) {
        console.error(
          "Durable notifications fetch error:",
          durableNotificationsError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch notifications",
          500,
        );
      }

      const chatNotifications = (durableNotifications || [])
        .filter((row) => row.type === "chat_message")
        .map((row) => mapDurablePatientNotification(row));

      const notifications = sortPatientNotificationsByRecency([
        ...orderNotifications,
        ...chatNotifications,
      ]);

      return jsonResponse({
        data: notifications,
        summary: {
          total_pending_actions: notifications.length,
        },
      });
    }

    // POST /notifications/:id/read - Mark an authenticated patient's durable notification read
    if (
      req.method === "POST" &&
      /^\/notifications\/[a-f0-9-]+\/read$/i.test(path)
    ) {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const notificationId = path.split("/")[2];
      const now = new Date().toISOString();

      const { data: updatedNotification, error: updateError } =
        await supabaseAdmin
          .from("patient_notifications")
          .update({ read_at: now })
          .eq("id", notificationId)
          .eq("patient_id", patient!.id)
          .select("id, read_at")
          .maybeSingle();

      if (updateError) {
        console.error("Notification read update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to mark notification as read",
          500,
        );
      }

      if (!updatedNotification) {
        return errorResponse(
          "NOT_FOUND",
          "Notification not found",
          404,
        );
      }

      return jsonResponse({
        data: {
          id: updatedNotification.id,
          read_at: updatedNotification.read_at,
        },
      });
    }

    // ==================== PUBLIC ENDPOINTS ====================

    // GET /tenant - Get tenant info by slug
    if (req.method === "GET" && path === "/tenant") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant(
        "id, name, slug, status, contact_email",
      );
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      // Get tenant branding
      const { data: branding } = await supabase
        .from("tenant_branding")
        .select("logo_url, primary_color, secondary_color, accent_color")
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      return jsonResponse({
        data: {
          ...tenant,
          branding: branding || null,
        },
      });
    }

    // GET /products - List enabled products for a tenant
    if (req.method === "GET" && path === "/products") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      // Get tenant ID from slug
      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      // Pagination
      const page = parseInt(url.searchParams.get("page") || "1");
      const pageSize = Math.min(
        parseInt(url.searchParams.get("page_size") || "20"),
        100,
      );
      const offset = (page - 1) * pageSize;

      // Category filter
      const categoryKey = url.searchParams.get("category");

      // Build query.
      //
      // `metadata` carries the product gallery. image_url is null for products whose
      // pictures only ever lived in metadata.pdp.images (Compounded Tirzepatide has
      // seven and no image_url), so without it the card shows a placeholder for a
      // product that plainly has photographs.
      //
      // NOTE: this string is a PostgREST *column list*, not SQL. It takes no comments
      // — a `--` line inside it is parsed as a column name and fails the whole query
      // with a 500. Keep the commentary out here.
      const query = supabase
        .from("products")
        .select(
          `
          id,
          name,
          description,
          terms_and_conditions_html,
          price_cents,
          compare_at_price_cents,
          sku,
          image_url,
          payment_type,
          subscription_interval,
          subscription_interval_count,
          subscription_renewal_lead_days,
          included_features,
          metadata,
          created_at,
          product_medications(
            medication:medications(id, title)
          ),
          product_faqs(
            id,
            question,
            answer,
            display_order
          ),
          product_categories:product_category_assignments(
            category:product_categories(id, key, name)
          )
        `,
          { count: "exact" },
        )
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .order("name", { ascending: true })
        .range(offset, offset + pageSize - 1);

      const { data: products, error: productsError, count } = await query;

      if (productsError) throw productsError;

      // Filter by category if specified
      let filteredProducts = products || [];
      if (categoryKey) {
        filteredProducts = filteredProducts.filter((p: any) =>
          p.product_categories?.some(
            (pc: any) => pc.category?.key === categoryKey,
          )
        );
      }

      // Transform response
      const transformedProducts = filteredProducts.map((p: any) => {
        // Single medication vs bundle is not a stored flag — it is derived from the
        // number of DISTINCT medications linked to the product. Count link rows, not
        // SUM(quantity): a product carrying 2x of one medication is still a single.
        const medications = ((p.product_medications as any[]) ?? [])
          .map((pm: any) => pm.medication)
          .filter((m: any) => m?.id)
          .map((m: any) => ({ id: m.id, title: m.title }));

        return {
          id: p.id,
          name: p.name,
          description: p.description,
          terms_and_conditions: p.terms_and_conditions_html,
          terms_and_conditions_html: p.terms_and_conditions_html,
          price_cents: p.price_cents,
          price_formatted: `$${(p.price_cents / 100).toFixed(2)}`,
          // Display-only "was" anchor, struck through beside the real price. NULL on
          // most products; a DB CHECK guarantees it exceeds price_cents. Never charged.
          compare_at_price_cents: p.compare_at_price_cents ?? null,
          compare_at_price_formatted:
            p.compare_at_price_cents != null
              ? `$${(p.compare_at_price_cents / 100).toFixed(2)}`
              : null,
          sku: p.sku,
          image_url: p.image_url,
          payment_type: p.payment_type,
          subscription_interval: p.subscription_interval,
          subscription_interval_count: p.subscription_interval_count,
          subscription_renewal_lead_days: p.subscription_renewal_lead_days,
          medications,
          medication_count: medications.length,
          included_features: Array.isArray(p.included_features)
            ? p.included_features
            : [],
          // Selecting the column is not enough — this mapper builds an explicit
          // object, so anything not named here is dropped. The card reads the
          // gallery out of metadata.pdp.images when image_url is null.
          metadata: p.metadata ?? null,
          faqs: (p.product_faqs as any[])
            ?.sort((a: any, b: any) => {
              const orderDiff = (a.display_order ?? 0) - (b.display_order ?? 0);
              if (orderDiff !== 0) return orderDiff;
              return String(a.id).localeCompare(String(b.id));
            })
            .map((faq: any) => ({
              id: faq.id,
              question: faq.question,
              answer: faq.answer,
              display_order: faq.display_order,
            })) || [],
          categories: p.product_categories
            ?.map((pc: any) => ({
              id: pc.category?.id,
              key: pc.category?.key,
              name: pc.category?.name,
            }))
            .filter((c: any) => c.id) || [],
        };
      });

      return jsonResponse({
        data: transformedProducts,
        pagination: {
          page,
          page_size: pageSize,
          total: count || 0,
          total_pages: Math.ceil((count || 0) / pageSize),
          has_more: offset + pageSize < (count || 0),
        },
      });
    }

    // GET /products/:id - Get product details
    if (req.method === "GET" && path.match(/^\/products\/[a-f0-9-]+$/)) {
      const productId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      // Get tenant ID from slug
      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      // Get product with medications and categories
      const { data: product, error: productError } = await supabase
        .from("products")
        .select(
          `
          id,
          name,
          description,
          terms_and_conditions_html,
          price_cents,
          compare_at_price_cents,
          sku,
          image_url,
          payment_type,
          subscription_interval,
          subscription_interval_count,
          subscription_renewal_lead_days,
          included_features,
          metadata,
          created_at,
          product_faqs(
            id,
            question,
            answer,
            display_order
          ),
          product_medications(
            quantity,
            instructions,
            medication:medications(
              id,
              title,
              description,
              form,
              image_url
            )
          ),
          product_categories:product_category_assignments(
            category:product_categories(id, key, name)
          ),
          product_questionnaires:product_questionnaire_links(
            display_order,
            is_required,
            questionnaire:questionnaire_templates(
              id,
              name,
              description,
              schema,
              version
            )
          )
        `,
        )
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) throw productError;
      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      // Distinct medications backing this product. The count is what separates a
      // single medication from a bundle — count link rows, never SUM(quantity):
      // a product carrying 2x of one medication is still a single.
      const detailMedications = ((product.product_medications as any[]) ?? [])
        .filter((pm: any) => pm.medication?.id)
        .map((pm: any) => ({
          id: pm.medication.id,
          title: pm.medication.title,
          description: pm.medication.description,
          form: pm.medication.form,
          image_url: pm.medication.image_url,
          quantity: pm.quantity,
          instructions: pm.instructions,
        }));

      // Transform response
      const transformedProduct = {
        id: product.id,
        name: product.name,
        description: product.description,
        terms_and_conditions: product.terms_and_conditions_html,
        terms_and_conditions_html: product.terms_and_conditions_html,
        price_cents: product.price_cents,
        price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
        // Display-only "was" anchor, struck through beside the real price. NULL on
        // most products; a DB CHECK guarantees it exceeds price_cents. Never charged.
        compare_at_price_cents: product.compare_at_price_cents ?? null,
        compare_at_price_formatted:
          product.compare_at_price_cents != null
            ? `$${(product.compare_at_price_cents / 100).toFixed(2)}`
            : null,
        sku: product.sku,
        image_url: product.image_url,
        payment_type: product.payment_type,
        subscription_interval: product.subscription_interval,
        subscription_interval_count: product.subscription_interval_count,
        subscription_renewal_lead_days: product.subscription_renewal_lead_days,
        included_features: Array.isArray(product.included_features)
          ? product.included_features
          : [],
        metadata: product.metadata,
        faqs: (product.product_faqs as any[])
          ?.sort((a: any, b: any) => {
            const orderDiff = (a.display_order ?? 0) - (b.display_order ?? 0);
            if (orderDiff !== 0) return orderDiff;
            return String(a.id).localeCompare(String(b.id));
          })
          .map((faq: any) => ({
            id: faq.id,
            question: faq.question,
            answer: faq.answer,
            display_order: faq.display_order,
          })) || [],
        categories: (product.product_categories as any[])
          ?.map((pc: any) => ({
            id: pc.category?.id,
            key: pc.category?.key,
            name: pc.category?.name,
          }))
          .filter((c: any) => c.id) || [],
        // Flat shape, matching GET /products. This used to be nested as
        // { quantity, instructions, medication: { name, ... } } but nothing consumed
        // it — the product page discarded it — and two shapes for one concept
        // invites bugs. `medication_count` is what distinguishes a single
        // medication from a bundle; there is no is_bundle column.
        medications: detailMedications,
        medication_count: detailMedications.length,
        questionnaires: (product.product_questionnaires as any[])
          ?.sort((a: any, b: any) => a.display_order - b.display_order)
          .map((pq: any) => ({
            display_order: pq.display_order,
            is_required: pq.is_required,
            id: pq.questionnaire?.id,
            name: pq.questionnaire?.name,
            description: pq.questionnaire?.description,
            schema: pq.questionnaire?.schema,
            version: pq.questionnaire?.version,
          }))
          .filter((q: any) => q.id) || [],
      };

      return jsonResponse({ data: transformedProduct });
    }

    // POST /products/:id/terms-acceptance - Store accepted terms snapshot for product
    if (
      req.method === "POST" &&
      path.match(/^\/products\/[a-f0-9-]+\/terms-acceptance$/)
    ) {
      const productId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, access_status")
        .eq("auth_user_id", user.id)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (patientError) {
        console.error(
          "Patient lookup error for terms acceptance:",
          patientError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch patient", 500);
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      const { data: product, error: productError } = await supabase
        .from("products")
        .select("id, name, terms_and_conditions_html")
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (productError) {
        console.error(
          "Product lookup error for terms acceptance:",
          productError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }

      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const acceptedAt = dateTime().toISOString();
      const acceptedContent = product.terms_and_conditions_html ?? null;

      const { data: createdAcceptance, error: createAcceptanceError } =
        await supabaseAdmin
          .from("patient_terms_acceptances")
          .insert({
            tenant_id: tenant.id,
            patient_id: patient.id,
            product_id: product.id,
            accepted_at: acceptedAt,
            accepted_content: acceptedContent,
          })
          .select("id, accepted_at")
          .single();

      if (createAcceptanceError) {
        console.error(
          "Terms acceptance creation error:",
          createAcceptanceError,
        );
        return errorResponse(
          "TERMS_ACCEPTANCE_ERROR",
          "Failed to save accepted terms",
          500,
        );
      }

      return jsonResponse(
        {
          data: {
            id: createdAcceptance.id,
            product_id: product.id,
            product_name: product.name,
            patient_id: patient.id,
            accepted_at: createdAcceptance.accepted_at,
            accepted_content: acceptedContent,
          },
        },
        201,
      );
    }

    // GET /products/:id/terms-acceptance-status - Check if patient accepted latest terms for product
    if (
      req.method === "GET" &&
      path.match(/^\/products\/[a-f0-9-]+\/terms-acceptance-status$/)
    ) {
      const productId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: patient, error: patientError } = await supabaseAdmin
        .from("patients")
        .select("id, access_status")
        .eq("auth_user_id", user.id)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (patientError) {
        console.error(
          "Patient lookup error for terms acceptance status:",
          patientError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch patient", 500);
      }

      if (!patient) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      if (patient.access_status !== "active") {
        return errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}. Please contact support.`,
          403,
        );
      }

      const { data: product, error: productError } = await supabase
        .from("products")
        .select("id, name, terms_and_conditions_html")
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      if (productError) {
        console.error(
          "Product lookup error for terms acceptance status:",
          productError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }

      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const { data: latestAcceptance, error: acceptanceError } = await supabase
        .from("patient_terms_acceptances")
        .select("id, accepted_at, accepted_content")
        .eq("tenant_id", tenant.id)
        .eq("patient_id", patient.id)
        .eq("product_id", product.id)
        .order("accepted_at", { ascending: false })
        .limit(1)
        .maybeSingle();

      if (acceptanceError) {
        console.error("Terms acceptance lookup error:", acceptanceError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch terms acceptance",
          500,
        );
      }

      const hasAcceptanceRecord = Boolean(latestAcceptance);
      const acceptedText = normalizeTermsText(
        latestAcceptance?.accepted_content,
      );
      const latestProductTermsText = normalizeTermsText(
        product.terms_and_conditions_html,
      );
      const hasAcceptedLatestTerms = hasAcceptanceRecord &&
        acceptedText === latestProductTermsText;

      return jsonResponse({
        data: {
          product_id: product.id,
          product_name: product.name,
          patient_id: patient.id,
          has_acceptance_record: hasAcceptanceRecord,
          has_accepted_latest_terms: hasAcceptedLatestTerms,
          latest_accepted_at: latestAcceptance?.accepted_at || null,
          comparison_mode: "plain_text_without_html",
        },
      });
    }

    // POST /products/:id/validate-shipping-state - Validate shipping state eligibility for a product
    if (
      req.method === "POST" &&
      path.match(/^\/products\/[a-f0-9-]+\/validate-shipping-state$/)
    ) {
      const productId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        state?: string;
        country?: string;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const rawState = body.state?.trim();
      if (!rawState) {
        return errorResponse("MISSING_FIELDS", "state is required", 400);
      }

      const country = body.country?.trim() || "US";

      const { data: product, error: productError } = await supabase
        .from("products")
        .select("id, name")
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) throw productError;
      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const normalizedState = rawState.toUpperCase();
      const normalizedCountry = country.toUpperCase();
      const validation = await validateStateAgainstTenant(
        supabaseAdmin,
        tenant.id,
        normalizedState,
        normalizedCountry,
      );

      return jsonResponse({
        data: {
          product_id: product.id,
          product_name: product.name,
          state: normalizedState,
          country: normalizedCountry,
          is_shippable: validation.valid,
          message: validation.valid
            ? "Shipping is available for this product in the requested state."
            : validation.message ||
              "Shipping is not available for this product in the requested state.",
        },
      });
    }

    // POST /products/:id/shipping-availability-notifications - Capture an email for future shipping availability updates
    if (
      req.method === "POST" &&
      path.match(
        /^\/products\/[a-f0-9-]+\/shipping-availability-notifications$/,
      )
    ) {
      const productId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        email?: string;
        state?: string;
        country?: string;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const normalizedEmail = body.email?.toLowerCase().trim();
      const normalizedState = body.state?.toUpperCase().trim();
      const normalizedCountry = body.country?.toUpperCase().trim() || "US";

      if (!normalizedEmail || !normalizedState) {
        return errorResponse(
          "MISSING_FIELDS",
          "email and state are required",
          400,
        );
      }

      if (!isValidEmail(normalizedEmail)) {
        return errorResponse("INVALID_EMAIL", "Invalid email format", 400);
      }

      if (
        normalizedCountry === "US" &&
        !US_STATE_CODES.includes(normalizedState)
      ) {
        return errorResponse(
          "INVALID_SHIPPING_STATE",
          "Please provide a valid US shipping state",
          400,
        );
      }

      const { data: product, error: productError } = await supabase
        .from("products")
        .select("id, name")
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) throw productError;
      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const lookupFilters = supabaseAdmin
        .from("shipping_availability_notifications")
        .select("id, email, product_id, shipping_state, country, created_at")
        .eq("tenant_id", tenant.id)
        .eq("product_id", product.id)
        .eq("email", normalizedEmail)
        .eq("shipping_state", normalizedState)
        .eq("country", normalizedCountry)
        .maybeSingle();

      const { data: existingNotification, error: existingNotificationError } =
        await lookupFilters;

      if (existingNotificationError) {
        throw existingNotificationError;
      }

      if (existingNotification) {
        return jsonResponse({
          data: {
            id: existingNotification.id,
            email: existingNotification.email,
            product_id: existingNotification.product_id,
            shipping_state: existingNotification.shipping_state,
            country: existingNotification.country,
            already_exists: true,
            created_at: existingNotification.created_at,
          },
        });
      }

      const { data: createdNotification, error: createdNotificationError } =
        await supabaseAdmin
          .from("shipping_availability_notifications")
          .insert({
            tenant_id: tenant.id,
            product_id: product.id,
            email: normalizedEmail,
            shipping_state: normalizedState,
            country: normalizedCountry,
          })
          .select("id, email, product_id, shipping_state, country, created_at")
          .single();

      if (createdNotificationError) {
        if (createdNotificationError.code === "23505") {
          const {
            data: duplicateNotification,
            error: duplicateNotificationError,
          } = await supabaseAdmin
            .from("shipping_availability_notifications")
            .select(
              "id, email, product_id, shipping_state, country, created_at",
            )
            .eq("tenant_id", tenant.id)
            .eq("product_id", product.id)
            .eq("email", normalizedEmail)
            .eq("shipping_state", normalizedState)
            .eq("country", normalizedCountry)
            .maybeSingle();

          if (duplicateNotificationError) {
            throw duplicateNotificationError;
          }

          if (duplicateNotification) {
            return jsonResponse({
              data: {
                id: duplicateNotification.id,
                email: duplicateNotification.email,
                product_id: duplicateNotification.product_id,
                shipping_state: duplicateNotification.shipping_state,
                country: duplicateNotification.country,
                already_exists: true,
                created_at: duplicateNotification.created_at,
              },
            });
          }
        }

        return errorResponse(
          "CREATE_NOTIFICATION_ERROR",
          "Failed to save shipping availability notification",
          500,
        );
      }

      return jsonResponse(
        {
          data: {
            id: createdNotification.id,
            email: createdNotification.email,
            product_id: createdNotification.product_id,
            shipping_state: createdNotification.shipping_state,
            country: createdNotification.country,
            already_exists: false,
            created_at: createdNotification.created_at,
          },
        },
        201,
      );
    }
    // ==================== ADMIN: TEST PUSH NOTIFICATION ====================
    // POST /admin/test-push-notification
    // Allows admins to send an immediate test push to a patient's registered device.
    // Requires admin JWT (caller must be in admin_users, not patients).

    if (req.method === "POST" && path === "/admin/test-push-notification") {
      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      // Verify caller is an admin (exists in admin_users, not patients)
      const { data: adminUser, error: adminError } = await supabaseAdmin
        .from("admin_users")
        .select("id, email")
        .eq("auth_user_id", user.id)
        .maybeSingle();

      if (adminError || !adminUser) {
        return errorResponse("FORBIDDEN", "Admin access required", 403);
      }

      let body: { patient_id?: string; title?: string; body?: string };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Request body must be valid JSON");
      }

      if (!body.patient_id || typeof body.patient_id !== "string") {
        return errorResponse("INVALID_INPUT", "patient_id is required");
      }

      // Fetch patient to get tenant_id
      const { data: targetPatient, error: patientFetchError } =
        await supabaseAdmin
          .from("patients")
          .select("id, auth_user_id, tenant_id, first_name")
          .eq("id", body.patient_id)
          .maybeSingle();

      if (patientFetchError || !targetPatient) {
        return errorResponse("NOT_FOUND", "Patient not found", 404);
      }
      if (!targetPatient.auth_user_id) {
        return errorResponse(
          "PATIENT_AUTH_NOT_LINKED",
          "Patient does not have a linked auth user for push targeting",
          422,
        );
      }

      const osConfig = await getOneSignalConfig(
        supabaseAdmin,
        targetPatient.tenant_id,
      );
      if (!osConfig) {
        return errorResponse(
          "ONESIGNAL_NOT_CONFIGURED",
          "OneSignal integration is not configured for this tenant. Add app_id and rest_api_key in tenant integrations.",
          422,
        );
      }

      const notificationTitle =
        typeof body.title === "string" && body.title.trim()
          ? body.title.trim()
          : "Test Notification";
      const notificationBody = typeof body.body === "string" && body.body.trim()
        ? body.body.trim()
        : `Hi ${
          targetPatient.first_name ?? "there"
        } — push notifications are working!`;

      const idempotencyKey = `admin-test:${targetPatient.id}:${Date.now()}`;

      // null sendAfterUtc = immediate delivery
      const notificationResult = await scheduleNotificationWithResult(
        targetPatient.auth_user_id,
        notificationTitle,
        notificationBody,
        null,
        osConfig,
        idempotencyKey,
        "/reminders",
      );

      console.info("Admin test notification sent", {
        requestId,
        adminUserId: adminUser.id,
        patientId: targetPatient.id,
        externalUserId: targetPatient.auth_user_id,
        tenantId: targetPatient.tenant_id,
        notificationId: notificationResult.notification_id,
        oneSignalStatus: notificationResult.status,
        oneSignalAccepted: notificationResult.accepted,
        oneSignalError: notificationResult.error,
      });

      return jsonResponse({
        data: {
          sent: notificationResult.accepted,
          notification_id: notificationResult.notification_id,
          // false usually means patient's device is not yet registered with OneSignal
          // for external_id = patients.auth_user_id, or push permission is disabled.
          device_registered: notificationResult.accepted,
          onesignal_status: notificationResult.status,
          onesignal_response: notificationResult.response,
          error: notificationResult.error,
        },
      });
    }

    // ==================== ADMIN: TEST EMAIL ====================
    // POST /admin/test-email
    // Sends a tenant-scoped test email through the configured email distribution provider.

    if (req.method === "POST" && path === "/admin/test-email") {
      if (!authHeader) {
        return errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        );
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();

      if (userError || !user) {
        return errorResponse("UNAUTHORIZED", "Invalid or expired token", 401);
      }

      const { data: adminUser, error: adminError } = await supabaseAdmin
        .from("admin_users")
        .select("id, email")
        .eq("auth_user_id", user.id)
        .maybeSingle();

      if (adminError || !adminUser) {
        return errorResponse("FORBIDDEN", "Admin access required", 403);
      }

      let body: { tenant_id?: string; to?: string };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Request body must be valid JSON");
      }

      if (!body.tenant_id || typeof body.tenant_id !== "string") {
        return errorResponse("INVALID_INPUT", "tenant_id is required");
      }

      if (!body.to || typeof body.to !== "string") {
        return errorResponse("INVALID_INPUT", "Recipient email is required");
      }

      const recipientEmail = body.to.trim().toLowerCase();
      if (!isValidEmail(recipientEmail)) {
        return errorResponse("INVALID_INPUT", "Recipient email is invalid");
      }

      const { data: isSuperadmin, error: roleError } = await supabaseAdmin.rpc(
        "is_platform_superadmin",
        { _auth_user_id: user.id },
      );

      if (roleError) {
        return errorResponse(
          "ROLE_CHECK_FAILED",
          "Failed to verify admin role",
          500,
        );
      }

      if (!isSuperadmin) {
        const { data: membership, error: membershipError } = await supabaseAdmin
          .from("tenant_memberships")
          .select("id")
          .eq("admin_user_id", adminUser.id)
          .eq("tenant_id", body.tenant_id)
          .maybeSingle();

        if (membershipError) {
          return errorResponse(
            "TENANT_ACCESS_CHECK_FAILED",
            "Failed to verify tenant access",
            500,
          );
        }

        if (!membership) {
          return errorResponse("FORBIDDEN", "Tenant access required", 403);
        }
      }

      const { data: tenant, error: tenantError } = await supabaseAdmin
        .from("tenants")
        .select("id, name")
        .eq("id", body.tenant_id)
        .maybeSingle();

      if (tenantError || !tenant) {
        return errorResponse("NOT_FOUND", "Tenant not found", 404);
      }

      const deliveryResult = await sendEmailViaTenantDistribution({
        supabaseClient: supabaseAdmin,
        tenantId: tenant.id,
        to: recipientEmail,
        subject: "Test Email",
        html: `
          <div style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827;">
            <p style="margin: 0 0 16px;">
              This is a test email from ${
          escapeHtml(tenant.name || "your tenant")
        }.
            </p>
            <p style="margin: 0;">
              If you received this message, Resend email delivery and the saved tenant email template are working.
            </p>
          </div>
        `,
        logContext: {
          requestId,
          flow: "admin_test_email",
          adminUserId: adminUser.id,
          tenantId: tenant.id,
        },
      });

      console.info("Admin test email sent", {
        requestId,
        adminUserId: adminUser.id,
        tenantId: tenant.id,
        recipientEmail,
        integrationKey: deliveryResult.integrationKey,
      });

      return jsonResponse({
        data: {
          sent: true,
          integration_key: deliveryResult.integrationKey,
        },
      });
    }

    // ==================== REMINDERS ====================
    // All reminder endpoints require authentication.
    // OneSignal push scheduling is performed server-side via the tenant integration.

    if (
      path === "/reminders" ||
      /^\/reminders\/[a-f0-9-]+(\/enabled)?$/.test(path)
    ) {
      const {
        user,
        patient,
        error: authError,
      } = await getAuthenticatedPatient();
      if (authError) return authError;

      // Shared helper: cancel all 'scheduled' OneSignal notifications for a reminder
      // and mark them as 'cancelled' in the DB. Non-fatal per notification.
      const cancelPendingNotifications = async (
        reminderId: string,
        osConfig: Awaited<ReturnType<typeof getOneSignalConfig>>,
      ): Promise<void> => {
        const { data: pending } = await supabaseAdmin
          .from("patient_reminder_notifications")
          .select("id, onesignal_notification_id")
          .eq("reminder_id", reminderId)
          .eq("status", "scheduled")
          .gte("scheduled_for", new Date().toISOString());

        if (!pending?.length) return;

        await Promise.all(
          pending.map(
            async (row: { id: string; onesignal_notification_id: string }) => {
              if (osConfig) {
                await cancelNotification(
                  row.onesignal_notification_id,
                  osConfig,
                );
              }
              await supabaseAdmin
                .from("patient_reminder_notifications")
                .update({ status: "cancelled" })
                .eq("id", row.id);
            },
          ),
        );
      };

      // Shared helper: schedule next N days of occurrences into OneSignal
      // and persist the notification IDs. Failures per-slot are non-fatal.
      const scheduleOccurrences = async (
        reminder: {
          id: string;
          patient_id: string;
          title: string;
          frequency: string;
          repeat_days: number[] | null;
          time_local: string;
          timezone: string;
        },
        osConfig: Awaited<ReturnType<typeof getOneSignalConfig>>,
        fromDate: Date,
        daysAhead: number,
        externalUserId: string,
      ): Promise<void> => {
        if (!osConfig) return; // Tenant has no OneSignal integration configured

        const occurrences = calculateOccurrences(
          {
            frequency: reminder.frequency as "daily" | "weekly",
            repeat_days: reminder.repeat_days,
            time_local: reminder.time_local,
            timezone: reminder.timezone,
          },
          fromDate,
          daysAhead,
        );

        await Promise.all(
          occurrences.map(async (fireAt) => {
            const dateKey = fireAt.toISOString().slice(0, 10); // YYYY-MM-DD
            const idempotencyKey = `${reminder.id}:${dateKey}`;

            const notificationId = await scheduleNotification(
              externalUserId,
              reminder.title,
              `Time for your ${reminder.title} reminder`,
              fireAt,
              osConfig,
              idempotencyKey,
            );

            if (notificationId) {
              await supabaseAdmin
                .from("patient_reminder_notifications")
                .insert({
                  reminder_id: reminder.id,
                  onesignal_notification_id: notificationId,
                  scheduled_for: fireAt.toISOString(),
                  status: "scheduled",
                });
            }
          }),
        );
      };

      // ── GET /reminders ────────────────────────────────────────────────
      if (req.method === "GET" && path === "/reminders") {
        const { data: reminders, error: fetchError } = await supabaseAdmin
          .from("patient_reminders")
          .select(
            `
            id, category, title, medication_id, frequency, repeat_days,
            time_local, timezone, is_enabled, disabled_reason,
            subscription_linked, subscription_id, created_at, updated_at,
            medications!patient_reminders_medication_id_fkey (title)
          `,
          )
          .eq("patient_id", patient!.id)
          .eq("tenant_id", patient!.tenant_id)
          .is("deleted_at", null)
          .order("created_at", { ascending: true });

        if (fetchError) {
          console.error("Reminders fetch error", fetchError);
          return errorResponse("FETCH_ERROR", "Failed to fetch reminders", 500);
        }

        const data = (reminders ?? []).map((r: Record<string, unknown>) => ({
          id: r.id,
          category: r.category,
          title: r.title,
          medication_id: r.medication_id,
          frequency: r.frequency,
          repeat_days: r.repeat_days,
          time_local: r.time_local,
          timezone: r.timezone,
          is_enabled: r.is_enabled,
          disabled_reason: r.disabled_reason,
          subscription_linked: r.subscription_linked,
          subscription_id: r.subscription_id,
          schedule_summary: buildScheduleSummary(
            r.frequency as "daily" | "weekly",
            r.repeat_days as number[] | null,
            r.time_local as string,
          ),
          created_at: r.created_at,
          updated_at: r.updated_at,
        }));

        return jsonResponse({ data });
      }

      // ── POST /reminders ───────────────────────────────────────────────
      if (req.method === "POST" && path === "/reminders") {
        let body: {
          category?: string;
          medication_id?: string | null;
          frequency?: string;
          repeat_days?: number[] | null;
          time_local?: string;
          timezone?: string;
          subscription_linked?: boolean;
          subscription_id?: string | null;
        };

        try {
          body = await req.json();
        } catch {
          return errorResponse(
            "INVALID_JSON",
            "Request body must be valid JSON",
          );
        }

        const {
          category,
          medication_id,
          frequency,
          repeat_days,
          time_local,
          timezone,
        } = body;

        const validCategories = ["medication", "body", "energy", "weight"];
        const validFrequencies = ["daily", "weekly"];

        if (!category || !validCategories.includes(category)) {
          return errorResponse(
            "INVALID_CATEGORY",
            `category must be one of: ${validCategories.join(", ")}`,
          );
        }
        if (!frequency || !validFrequencies.includes(frequency)) {
          return errorResponse(
            "INVALID_FREQUENCY",
            `frequency must be one of: ${validFrequencies.join(", ")}`,
          );
        }
        if (!time_local || !/^\d{1,2}:\d{2}(:\d{2})?$/.test(time_local)) {
          return errorResponse(
            "INVALID_TIME",
            "time_local must be in HH:MM format",
          );
        }
        if (
          !timezone ||
          typeof timezone !== "string" ||
          timezone.trim().length === 0
        ) {
          return errorResponse("INVALID_TIMEZONE", "timezone is required");
        }
        if (frequency === "weekly") {
          if (
            !Array.isArray(repeat_days) ||
            repeat_days.length === 0 ||
            repeat_days.some((d) => typeof d !== "number" || d < 0 || d > 6)
          ) {
            return errorResponse(
              "INVALID_REPEAT_DAYS",
              "repeat_days is required for weekly frequency (array of 0–6)",
            );
          }
        }
        if (category === "medication") {
          if (!medication_id || typeof medication_id !== "string") {
            return errorResponse(
              "MEDICATION_REQUIRED",
              "medication_id is required for medication category",
            );
          }
          // Verify medication belongs to this tenant
          const { data: med, error: medError } = await supabaseAdmin
            .from("medications")
            .select("id, title")
            .eq("id", medication_id)
            .eq("tenant_id", patient!.tenant_id)
            .maybeSingle();
          if (medError || !med) {
            return errorResponse(
              "INVALID_MEDICATION",
              "medication_id not found for this tenant",
              422,
            );
          }
        } else if (medication_id) {
          return errorResponse(
            "MEDICATION_NOT_ALLOWED",
            "medication_id must be null for non-medication categories",
          );
        }

        // Validate timezone by attempting to use it
        try {
          new Intl.DateTimeFormat("en-US", { timeZone: timezone });
        } catch {
          return errorResponse(
            "INVALID_TIMEZONE",
            `Unknown timezone: ${timezone}`,
          );
        }

        // Derive title server-side
        let medicationName: string | null = null;
        if (category === "medication" && medication_id) {
          const { data: med } = await supabaseAdmin
            .from("medications")
            .select("title")
            .eq("id", medication_id)
            .maybeSingle();
          medicationName = med?.title ?? null;
        }
        const title = deriveReminderTitle(category, medicationName);

        const { data: created, error: insertError } = await supabaseAdmin
          .from("patient_reminders")
          .insert({
            patient_id: patient!.id,
            tenant_id: patient!.tenant_id,
            category,
            title,
            medication_id: category === "medication" ? medication_id : null,
            frequency,
            repeat_days: frequency === "weekly" ? repeat_days : null,
            time_local,
            timezone: timezone.trim(),
            is_enabled: true,
            subscription_linked: body.subscription_linked === true,
            subscription_id: body.subscription_id ?? null,
          })
          .select()
          .single();

        if (insertError || !created) {
          console.error("Reminder insert error", insertError);
          return errorResponse(
            "INSERT_ERROR",
            "Failed to create reminder",
            500,
          );
        }

        // Schedule push notifications for the next 30 days (non-blocking on failure)
        const osConfig = await getOneSignalConfig(
          supabaseAdmin,
          patient!.tenant_id,
        );
        await scheduleOccurrences(created, osConfig, new Date(), 30, user!.id);

        return jsonResponse(
          {
            data: {
              ...created,
              schedule_summary: buildScheduleSummary(
                created.frequency,
                created.repeat_days,
                created.time_local,
              ),
            },
          },
          201,
        );
      }

      // ── PATCH /reminders/:id ──────────────────────────────────────────
      const editMatch = path.match(/^\/reminders\/([a-f0-9-]+)$/);
      if (req.method === "PATCH" && editMatch) {
        const reminderId = editMatch[1];

        const { data: existing, error: fetchError } = await supabaseAdmin
          .from("patient_reminders")
          .select("*")
          .eq("id", reminderId)
          .eq("patient_id", patient!.id)
          .is("deleted_at", null)
          .maybeSingle();

        if (fetchError) {
          return errorResponse("FETCH_ERROR", "Failed to fetch reminder", 500);
        }
        if (!existing) {
          return errorResponse("NOT_FOUND", "Reminder not found", 404);
        }

        let body: {
          category?: string;
          medication_id?: string | null;
          frequency?: string;
          repeat_days?: number[] | null;
          time_local?: string;
          timezone?: string;
          subscription_linked?: boolean;
          subscription_id?: string | null;
        };
        try {
          body = await req.json();
        } catch {
          return errorResponse(
            "INVALID_JSON",
            "Request body must be valid JSON",
          );
        }

        const validCategories = ["medication", "body", "energy", "weight"];
        const validFrequencies = ["daily", "weekly"];

        const category = body.category ?? existing.category;
        const frequency = body.frequency ?? existing.frequency;
        const medication_id = "medication_id" in body
          ? body.medication_id
          : existing.medication_id;
        const repeat_days = "repeat_days" in body
          ? body.repeat_days
          : existing.repeat_days;
        const time_local = body.time_local ?? existing.time_local;
        const timezone = body.timezone ?? existing.timezone;

        if (!validCategories.includes(category)) {
          return errorResponse(
            "INVALID_CATEGORY",
            `category must be one of: ${validCategories.join(", ")}`,
          );
        }
        if (!validFrequencies.includes(frequency)) {
          return errorResponse(
            "INVALID_FREQUENCY",
            `frequency must be one of: ${validFrequencies.join(", ")}`,
          );
        }
        if (frequency === "weekly") {
          if (
            !Array.isArray(repeat_days) ||
            repeat_days.length === 0 ||
            repeat_days.some(
              (d: unknown) => typeof d !== "number" || d < 0 || d > 6,
            )
          ) {
            return errorResponse(
              "INVALID_REPEAT_DAYS",
              "repeat_days is required for weekly frequency",
            );
          }
        }
        if (category === "medication" && !medication_id) {
          return errorResponse(
            "MEDICATION_REQUIRED",
            "medication_id is required for medication category",
          );
        }
        if (category !== "medication" && medication_id) {
          return errorResponse(
            "MEDICATION_NOT_ALLOWED",
            "medication_id must be null for non-medication categories",
          );
        }
        if (timezone) {
          try {
            new Intl.DateTimeFormat("en-US", { timeZone: timezone });
          } catch {
            return errorResponse(
              "INVALID_TIMEZONE",
              `Unknown timezone: ${timezone}`,
            );
          }
        }

        // Re-derive title if category or medication changed
        let medicationName: string | null = null;
        if (category === "medication" && medication_id) {
          const { data: med } = await supabaseAdmin
            .from("medications")
            .select("title")
            .eq("id", medication_id)
            .eq("tenant_id", patient!.tenant_id)
            .maybeSingle();
          if (!med) {
            return errorResponse(
              "INVALID_MEDICATION",
              "medication_id not found for this tenant",
              422,
            );
          }
          medicationName = med.title ?? null;
        }
        const title = deriveReminderTitle(category, medicationName);

        const { data: updated, error: updateError } = await supabaseAdmin
          .from("patient_reminders")
          .update({
            category,
            title,
            medication_id: category === "medication" ? medication_id : null,
            frequency,
            repeat_days: frequency === "weekly" ? repeat_days : null,
            time_local,
            timezone: timezone.trim(),
            ...(typeof body.subscription_linked === "boolean"
              ? { subscription_linked: body.subscription_linked }
              : {}),
            ...("subscription_id" in body
              ? { subscription_id: body.subscription_id }
              : {}),
          })
          .eq("id", reminderId)
          .select()
          .single();

        if (updateError || !updated) {
          console.error("Reminder update error", updateError);
          return errorResponse(
            "UPDATE_ERROR",
            "Failed to update reminder",
            500,
          );
        }

        // Cancel old scheduled notifications and reschedule
        const osConfig = await getOneSignalConfig(
          supabaseAdmin,
          patient!.tenant_id,
        );
        await cancelPendingNotifications(reminderId, osConfig);
        if (updated.is_enabled) {
          await scheduleOccurrences(
            updated,
            osConfig,
            new Date(),
            30,
            user!.id,
          );
        }

        return jsonResponse({
          data: {
            ...updated,
            schedule_summary: buildScheduleSummary(
              updated.frequency,
              updated.repeat_days,
              updated.time_local,
            ),
          },
        });
      }

      // ── PATCH /reminders/:id/enabled ──────────────────────────────────
      const enabledMatch = path.match(/^\/reminders\/([a-f0-9-]+)\/enabled$/);
      if (req.method === "PATCH" && enabledMatch) {
        const reminderId = enabledMatch[1];

        const { data: existing, error: fetchError } = await supabaseAdmin
          .from("patient_reminders")
          .select("*")
          .eq("id", reminderId)
          .eq("patient_id", patient!.id)
          .is("deleted_at", null)
          .maybeSingle();

        if (fetchError) {
          return errorResponse("FETCH_ERROR", "Failed to fetch reminder", 500);
        }
        if (!existing) {
          return errorResponse("NOT_FOUND", "Reminder not found", 404);
        }

        let body: { is_enabled?: boolean };
        try {
          body = await req.json();
        } catch {
          return errorResponse(
            "INVALID_JSON",
            "Request body must be valid JSON",
          );
        }

        if (typeof body.is_enabled !== "boolean") {
          return errorResponse(
            "INVALID_INPUT",
            "is_enabled (boolean) is required",
          );
        }

        const updates: Record<string, unknown> = {
          is_enabled: body.is_enabled,
        };
        if (!body.is_enabled) {
          updates.disabled_reason = "user_disabled";
        } else if (existing.disabled_reason === "user_disabled") {
          updates.disabled_reason = null;
        }

        const { data: updated, error: updateError } = await supabaseAdmin
          .from("patient_reminders")
          .update(updates)
          .eq("id", reminderId)
          .select()
          .single();

        if (updateError || !updated) {
          return errorResponse(
            "UPDATE_ERROR",
            "Failed to update reminder",
            500,
          );
        }

        const osConfig = await getOneSignalConfig(
          supabaseAdmin,
          patient!.tenant_id,
        );
        if (!body.is_enabled) {
          await cancelPendingNotifications(reminderId, osConfig);
        } else {
          await scheduleOccurrences(
            updated,
            osConfig,
            new Date(),
            30,
            user!.id,
          );
        }

        return jsonResponse({
          data: {
            ...updated,
            schedule_summary: buildScheduleSummary(
              updated.frequency,
              updated.repeat_days,
              updated.time_local,
            ),
          },
        });
      }

      // ── DELETE /reminders/:id ─────────────────────────────────────────
      const deleteMatch = path.match(/^\/reminders\/([a-f0-9-]+)$/);
      if (req.method === "DELETE" && deleteMatch) {
        const reminderId = deleteMatch[1];

        const { data: existing, error: fetchError } = await supabaseAdmin
          .from("patient_reminders")
          .select("id, patient_id, tenant_id")
          .eq("id", reminderId)
          .eq("patient_id", patient!.id)
          .is("deleted_at", null)
          .maybeSingle();

        if (fetchError) {
          return errorResponse("FETCH_ERROR", "Failed to fetch reminder", 500);
        }
        if (!existing) {
          return errorResponse("NOT_FOUND", "Reminder not found", 404);
        }

        const osConfig = await getOneSignalConfig(
          supabaseAdmin,
          patient!.tenant_id,
        );
        await cancelPendingNotifications(reminderId, osConfig);

        const { error: deleteError } = await supabaseAdmin
          .from("patient_reminders")
          .update({ deleted_at: new Date().toISOString() })
          .eq("id", reminderId);

        if (deleteError) {
          return errorResponse(
            "DELETE_ERROR",
            "Failed to delete reminder",
            500,
          );
        }

        return jsonResponse({ data: { id: reminderId, deleted: true } });
      }
    }

    // GET /categories - List product categories
    if (req.method === "GET" && path === "/categories") {
      const { data: categories, error } = await supabase
        .from("product_categories")
        .select("id, key, name, description, display_order")
        .eq("is_active", true)
        .order("display_order", { ascending: true });

      if (error) throw error;

      return jsonResponse({ data: categories || [] });
    }

    if (path === "/chat-threads") {
      return errorResponse(
        "MOVED_TO_MESSENGER_API",
        `Endpoint ${req.method} ${path} moved to /functions/v1/messenger-api/telegra-clinical-chat`,
        410,
      );
    }

    // ==================== ORDER AND PLAN ENDPOINTS (MOVED) ====================

    const movedToPlanApi = path === "/plans" ||
      path === "/order-statuses" ||
      path === "/orders" ||
      /^\/orders\/[a-f0-9-]+$/.test(path) ||
      /^\/orders\/[a-f0-9-]+\/status-history$/.test(path) ||
      /^\/orders\/[a-f0-9-]+\/address$/.test(path) ||
      /^\/orders\/[a-f0-9-]+\/checkout$/.test(path) ||
      /^\/orders\/checkout\/cs_[a-zA-Z0-9_]+$/.test(path) ||
      /^\/plans\/[a-f0-9-]+\/cancel$/.test(path) ||
      /^\/plans\/[a-f0-9-]+\/reactivate$/.test(path);

    if (movedToPlanApi) {
      return errorResponse(
        "MOVED_TO_PLAN_API",
        `Endpoint ${req.method} ${path} moved to /functions/v1/plan-api`,
        410,
      );
    }

    // 404 for unknown routes
    return errorResponse(
      "NOT_FOUND",
      `Endpoint ${req.method} ${path} not found`,
      404,
    );
  } catch (error) {
    console.error("Patient API Error:", error);
    return errorResponse(
      "INTERNAL_ERROR",
      error instanceof Error ? error.message : "An unexpected error occurred",
      500,
    );
  }
});
