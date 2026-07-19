import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";

type JsonRecord = Record<string, unknown>;
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

const DEFAULT_MDI_TOKEN_TTL_MS = 55 * 60 * 1000;
const TOKEN_REFRESH_SKEW_MS = 60 * 1000;

interface CachedMdiTokenRow {
  access_token: string;
  expires_at: string;
}

interface MdiAuthTokenResponse {
  access_token: string;
  expires_in?: number | string;
  expires_at?: string | number;
  exp?: string | number;
  [key: string]: unknown;
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

function getValueAtPath(source: unknown, path: string): unknown {
  return path.split(".").reduce<unknown>((current, segment) => {
    if (!current || typeof current !== "object") return undefined;
    return (current as JsonRecord)[segment];
  }, source);
}

function parseExpirationValue(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value >= 1_000_000_000_000 ? value : value * 1000;
  }

  if (typeof value !== "string") return null;
  const trimmedValue = value.trim();
  if (!trimmedValue) return null;

  if (/^\d+$/.test(trimmedValue)) {
    const numericValue = Number(trimmedValue);
    if (!Number.isFinite(numericValue)) return null;
    return numericValue >= 1_000_000_000_000
      ? numericValue
      : numericValue * 1000;
  }

  const parsedValue = Date.parse(trimmedValue);
  return Number.isNaN(parsedValue) ? null : parsedValue;
}

function extractErrorMessage(responseBody: unknown, fallback: string): string {
  if (typeof responseBody === "string" && responseBody.trim().length > 0) {
    return responseBody.trim();
  }

  if (responseBody && typeof responseBody === "object") {
    const record = responseBody as JsonRecord;
    for (const key of ["message", "error", "detail"]) {
      const value = record[key];
      if (typeof value === "string" && value.trim().length > 0) {
        return value.trim();
      }
    }
  }

  return fallback;
}

export function buildMdiClientAuthUrl(baseUrl: string): string {
  return `${baseUrl.replace(/\/+$/, "")}/v1/partner/auth/token`;
}

export function isMdiAccessTokenCacheEntryValid(
  expiresAt: string | null | undefined,
  now = Date.now(),
): boolean {
  if (typeof expiresAt !== "string" || !expiresAt.trim()) return false;

  const parsedExpiration = Date.parse(expiresAt);
  if (Number.isNaN(parsedExpiration)) return false;

  return parsedExpiration - TOKEN_REFRESH_SKEW_MS > now;
}

export function extractMdiAccessTokenExpiry(
  responseBody: unknown,
  now = Date.now(),
): string {
  const absoluteExpirationPaths = [
    "expiresAt",
    "data.expiresAt",
    "expires_at",
    "data.expires_at",
    "expiration",
    "data.expiration",
    "exp",
    "data.exp",
  ];

  for (const path of absoluteExpirationPaths) {
    const expirationValue = parseExpirationValue(getValueAtPath(responseBody, path));
    if (expirationValue && expirationValue > now) {
      return new Date(expirationValue).toISOString();
    }
  }

  const expiresInPaths = ["expires_in", "data.expires_in"];

  for (const path of expiresInPaths) {
    const expiresInValue = getValueAtPath(responseBody, path);
    const expiresInMs = parseExpirationValue(expiresInValue);
    if (expiresInMs) {
      const absoluteExpiration = typeof expiresInValue === "number" &&
          expiresInValue < 1_000_000_000_000
        ? now + expiresInValue * 1000
        : typeof expiresInValue === "string" && /^\d+$/.test(expiresInValue.trim()) &&
            Number(expiresInValue.trim()) < 1_000_000_000_000
        ? now + Number(expiresInValue.trim()) * 1000
        : expiresInMs;

      if (absoluteExpiration > now) {
        return new Date(absoluteExpiration).toISOString();
      }
    }
  }

  return new Date(now + DEFAULT_MDI_TOKEN_TTL_MS).toISOString();
}

async function fetchCachedMdiAccessToken(params: {
  supabase: SupabaseAdminClient;
  tenantIntegrationId: string;
  tenantId: string;
  requestId: string;
}): Promise<CachedMdiTokenRow | null> {
  const { supabase, tenantIntegrationId, tenantId, requestId } = params;

  const { data, error } = await supabase
    .from("tenant_integration_auth_tokens")
    .select("access_token, expires_at")
    .eq("tenant_integration_id", tenantIntegrationId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    console.warn("Failed to read cached MDI access token", {
      requestId,
      tenantIntegrationId,
      tenantId,
      error: error.message,
    });
    return null;
  }

  return (data as CachedMdiTokenRow | null) ?? null;
}

async function cacheMdiAccessToken(params: {
  supabase: SupabaseAdminClient;
  tenantIntegrationId: string;
  tenantId: string;
  accessToken: string;
  expiresAt: string;
  requestId: string;
}): Promise<void> {
  const {
    supabase,
    tenantIntegrationId,
    tenantId,
    accessToken,
    expiresAt,
    requestId,
  } = params;

  const { error } = await supabase
    .from("tenant_integration_auth_tokens")
    .upsert({
      tenant_integration_id: tenantIntegrationId,
      tenant_id: tenantId,
      access_token: accessToken,
      expires_at: expiresAt,
      refreshed_at: new Date().toISOString(),
    }, {
      onConflict: "tenant_integration_id",
      ignoreDuplicates: false,
    });

  if (error) {
    console.warn("Failed to cache MDI access token", {
      requestId,
      tenantIntegrationId,
      tenantId,
      error: error.message,
    });
  }
}

async function authenticateWithMdiClientCredentials(params: {
  baseUrl: string;
  clientId: string;
  clientSecret: string;
  requestId: string;
  source: string;
}): Promise<
  | { accessToken: string; expiresAt: string }
  | { errorMessage: string }
> {
  const { baseUrl, clientId, clientSecret, requestId, source } = params;
  const endpoint = buildMdiClientAuthUrl(baseUrl);

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-source": source,
    },
    body: JSON.stringify({
      grant_type: "client_credentials",
      client_id: clientId,
      client_secret: clientSecret,
      scope: "*",
    }),
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    return {
      errorMessage: `MDI authentication failed: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    };
  }

  const token = responseBody as MdiAuthTokenResponse | null;
  if (
    typeof token?.access_token !== "string" ||
    token.access_token.trim().length === 0
  ) {
    return {
      errorMessage:
        "MDI authentication succeeded but no access token was returned",
    };
  }

  return {
    accessToken: token.access_token.trim(),
    expiresAt: extractMdiAccessTokenExpiry(responseBody),
  };
}

export async function resolveMdiAccessToken(params: {
  supabase: SupabaseAdminClient;
  tenantIntegrationId: string;
  tenantId: string;
  settings: Record<string, unknown> | null | undefined;
  baseUrl: string;
  requestId: string;
  source: string;
}): Promise<{ accessToken: string } | { errorMessage: string }> {
  const {
    supabase,
    tenantIntegrationId,
    tenantId,
    settings,
    baseUrl,
    requestId,
    source,
  } = params;

  const clientId = getStringSetting(settings, "client_id");
  const clientSecret = getStringSetting(settings, "client_secret");

  if (!clientId || !clientSecret) {
    return {
      errorMessage:
        "MDI integration is missing authentication configuration: provide client_id and client_secret",
    };
  }

  const cachedToken = await fetchCachedMdiAccessToken({
    supabase,
    tenantIntegrationId,
    tenantId,
    requestId,
  });

  if (cachedToken && isMdiAccessTokenCacheEntryValid(cachedToken.expires_at)) {
    return { accessToken: cachedToken.access_token };
  }

  const authResult = await authenticateWithMdiClientCredentials({
    baseUrl,
    clientId,
    clientSecret,
    requestId,
    source,
  });

  if ("errorMessage" in authResult) {
    return authResult;
  }

  await cacheMdiAccessToken({
    supabase,
    tenantIntegrationId,
    tenantId,
    accessToken: authResult.accessToken,
    expiresAt: authResult.expiresAt,
    requestId,
  });

  return { accessToken: authResult.accessToken };
}
