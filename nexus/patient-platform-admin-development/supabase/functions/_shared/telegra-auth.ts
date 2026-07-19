import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";

type JsonRecord = Record<string, unknown>;
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

const DEFAULT_TELEGRA_TOKEN_TTL_MS = 55 * 60 * 1000;
const TOKEN_REFRESH_SKEW_MS = 60 * 1000;

interface CachedTelegraTokenRow {
  access_token: string;
  expires_at: string;
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

export function buildTelegraClientAuthUrl(baseUrl: string): string {
  return `${baseUrl.replace(/\/+$/, "")}/auth/client`;
}

export function appendTelegraRequestTimestamp(
  endpoint: string,
  timestamp = new Date().toISOString(),
): string {
  const url = new URL(endpoint);
  url.searchParams.set("request_timestamp", timestamp);
  return url.toString();
}

function quoteCurlValue(value: string): string {
  return `'${value.replace(/'/g, `'\\''`)}'`;
}

export function buildTelegraAuthCurl(params: {
  endpoint: string;
  requestId: string;
  source: string;
}): string {
  const { endpoint, requestId, source } = params;

  return [
    "curl",
    "-X",
    "POST",
    "-H",
    quoteCurlValue("Accept: application/json"),
    "-H",
    quoteCurlValue("Authorization: Basic <redacted>"),
    "-H",
    quoteCurlValue(`x-request-id: ${requestId}`),
    "-H",
    quoteCurlValue(`x-source: ${source}`),
    quoteCurlValue(endpoint),
  ].join(" ");
}

export function isTelegraAccessTokenCacheEntryValid(
  expiresAt: string | null | undefined,
  now = Date.now(),
): boolean {
  if (typeof expiresAt !== "string" || !expiresAt.trim()) return false;

  const parsedExpiration = Date.parse(expiresAt);
  if (Number.isNaN(parsedExpiration)) return false;

  return parsedExpiration - TOKEN_REFRESH_SKEW_MS > now;
}

export function extractTelegraAccessTokenExpiry(
  responseBody: unknown,
  now = Date.now(),
): string {
  const candidatePaths = [
    "expiresAt",
    "data.expiresAt",
    "expires_at",
    "data.expires_at",
    "expiration",
    "data.expiration",
    "exp",
    "data.exp",
  ];

  for (const path of candidatePaths) {
    const expirationValue = parseExpirationValue(
      getValueAtPath(responseBody, path),
    );
    if (expirationValue && expirationValue > now) {
      return new Date(expirationValue).toISOString();
    }
  }

  // Telegra auth responses observed in this repo only guarantee `token`.
  return new Date(now + DEFAULT_TELEGRA_TOKEN_TTL_MS).toISOString();
}

async function fetchCachedTelegraAccessToken(params: {
  supabase: SupabaseAdminClient;
  tenantIntegrationId: string;
  tenantId: string;
  requestId: string;
}): Promise<CachedTelegraTokenRow | null> {
  const { supabase, tenantIntegrationId, tenantId, requestId } = params;

  const { data, error } = await supabase
    .from("tenant_integration_auth_tokens")
    .select("access_token, expires_at")
    .eq("tenant_integration_id", tenantIntegrationId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    console.warn("Failed to read cached Telegra access token", {
      requestId,
      tenantIntegrationId,
      tenantId,
      error: error.message,
    });
    return null;
  }

  return (data as CachedTelegraTokenRow | null) ?? null;
}

async function cacheTelegraAccessToken(params: {
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
    console.warn("Failed to cache Telegra access token", {
      requestId,
      tenantIntegrationId,
      tenantId,
      error: error.message,
    });
  }
}

async function authenticateWithTelegraClientCredentials(params: {
  baseUrl: string;
  username: string;
  password: string;
  requestId: string;
  source: string;
}): Promise<
  | { accessToken: string; expiresAt: string }
  | { errorMessage: string }
> {
  const { baseUrl, username, password, requestId, source } = params;
  const endpoint = appendTelegraRequestTimestamp(
    buildTelegraClientAuthUrl(baseUrl),
  );
  const basicAuthToken = btoa(`${username}:${password}`);

  console.info("Calling Telegra auth API", {
    requestId,
    source,
    endpoint,
    method: "POST",
    payload: null,
    headers: {
      Accept: "application/json",
      Authorization: "Basic <redacted>",
      "x-request-id": requestId,
      "x-source": source,
    },
  });

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Basic ${basicAuthToken}`,
      "x-request-id": requestId,
      "x-source": source,
    },
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

  console.info("Telegra auth API response received", {
    requestId,
    source,
    endpoint,
    httpStatus: response.status,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
    response: responseBody,
  });

  if (response.status === 429) {
    console.warn("Telegra auth API rate limited", {
      requestId,
      source,
      endpoint,
      httpStatus: response.status,
      statusText: response.statusText,
      curl: buildTelegraAuthCurl({
        endpoint,
        requestId,
        source,
      }),
    });
  }

  if (!response.ok) {
    const message = typeof responseBody === "object" && responseBody !== null &&
        typeof (responseBody as JsonRecord).message === "string"
      ? String((responseBody as JsonRecord).message)
      : `${response.status} ${response.statusText}`.trim();

    return {
      errorMessage: `Telegra authentication failed: ${message}`,
    };
  }

  const token = getValueAtPath(responseBody, "token");
  if (typeof token !== "string" || token.trim().length === 0) {
    return {
      errorMessage:
        "Telegra authentication succeeded but no access token was returned",
    };
  }

  return {
    accessToken: token.trim(),
    expiresAt: extractTelegraAccessTokenExpiry(responseBody),
  };
}

export async function resolveTelegraAccessToken(params: {
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

  const username = getStringSetting(settings, "username");
  const password = getStringSetting(settings, "password");

  if (username && password) {
    const cachedToken = await fetchCachedTelegraAccessToken({
      supabase,
      tenantIntegrationId,
      tenantId,
      requestId,
    });

    if (
      cachedToken &&
      isTelegraAccessTokenCacheEntryValid(cachedToken.expires_at)
    ) {
      return { accessToken: cachedToken.access_token };
    }

    const authResult = await authenticateWithTelegraClientCredentials({
      baseUrl,
      username,
      password,
      requestId,
      source,
    });

    if ("errorMessage" in authResult) {
      return authResult;
    }

    await cacheTelegraAccessToken({
      supabase,
      tenantIntegrationId,
      tenantId,
      accessToken: authResult.accessToken,
      expiresAt: authResult.expiresAt,
      requestId,
    });

    return { accessToken: authResult.accessToken };
  }

  const accessToken = getStringSetting(settings, "access_token");
  if (accessToken) {
    return { accessToken };
  }

  return {
    errorMessage:
      "Telegra integration is missing authentication configuration: provide username/password or access_token",
  };
}
