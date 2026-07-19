import { postSignedRtdhJson } from "./rtdh-signature.ts";

export type RtdhSecretManagerSavedSecret = {
  key: string;
  secretId: string;
  secretName: string;
  versionName: string;
};

export type RtdhSecretManagerResponse = {
  success: boolean;
  tenant: string;
  provider: string;
  context: string | null;
  saved: RtdhSecretManagerSavedSecret[];
  requestId?: string;
};

const RTDH_RECEIVER_SUFFIXES = [
  "/patient-platform-webhook-receiver",
  "/secret-manager-receiver",
];

function normalizeRtdhBaseUrl(value: string): string {
  let base = value.trim().replace(/\/+$/, "");
  for (const suffix of RTDH_RECEIVER_SUFFIXES) {
    if (base.endsWith(suffix)) {
      base = base.slice(0, -suffix.length);
      break;
    }
  }
  return base;
}

function secretManagerUrl(apiUrl: string): string {
  const configuredBase = Deno.env.get("RTDH_BASE_URL")?.trim();
  const base = normalizeRtdhBaseUrl(configuredBase || apiUrl);

  if (!base) {
    throw new Error("RTDH_BASE_URL or rtdh_config.api_url is required");
  }
  return `${base}/secret-manager-receiver`;
}

function signingSecret(fallback?: string): string {
  const configured =
    Deno.env.get("RTDH_SECRET_MANAGER_RECEIVER_SECRET")?.trim() ||
    Deno.env.get("SECRET_MANAGER_RECEIVER_SECRET")?.trim() ||
    Deno.env.get("PATIENT_PLATFORM_RECEIVER_SECRET")?.trim();
  const secret = configured || fallback?.trim() || "";
  if (!secret) {
    throw new Error("RTDH_SECRET_MANAGER_RECEIVER_SECRET is not configured");
  }
  return secret;
}

function encodeBase64(value: string): string {
  const bytes = new TextEncoder().encode(value);
  let binary = "";
  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }
  return btoa(binary);
}

function encodeSecretsBase64(
  secrets: Record<string, string>,
): Record<string, string> {
  return Object.fromEntries(
    Object.entries(secrets).map(([key, value]) => [key, encodeBase64(value)]),
  );
}

export async function saveSecretsViaRtdh(params: {
  apiUrl: string;
  tenant: string;
  provider: string;
  context?: string | null;
  secrets: Record<string, string>;
  fallbackSigningSecret?: string;
  requestId?: string;
}): Promise<RtdhSecretManagerResponse> {
  const requestId = params.requestId || crypto.randomUUID();
  const response = await postSignedRtdhJson({
    url: secretManagerUrl(params.apiUrl),
    requestId,
    requestSource: "patient-platform:set-rtdh-config",
    webhookSecret: signingSecret(params.fallbackSigningSecret),
    payload: {
      tenant: params.tenant,
      provider: params.provider,
      ...(params.context ? { context: params.context } : {}),
      encoding: "base64",
      secrets: encodeSecretsBase64(params.secrets),
    },
    timeoutMs: 8000,
  });

  const text = await response.text();
  let body: unknown = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { raw: text };
  }

  if (!response.ok) {
    const message = typeof body === "object" && body && "error" in body
      ? String((body as { error: unknown }).error)
      : `RTDH secret-manager request failed (${response.status})`;
    throw new Error(message);
  }

  const data = body as Partial<RtdhSecretManagerResponse> | null;
  if (!data?.success || !Array.isArray(data.saved)) {
    throw new Error("RTDH secret-manager response was invalid");
  }

  return {
    success: true,
    tenant: String(data.tenant ?? params.tenant),
    provider: String(data.provider ?? params.provider),
    context: typeof data.context === "string" ? data.context : null,
    saved: data.saved,
    requestId: typeof data.requestId === "string" ? data.requestId : requestId,
  };
}

export async function checkSecretExistsViaRtdh(params: {
  apiUrl: string;
  tenant: string;
  provider: string;
  context?: string | null;
  key?: string;
  fallbackSigningSecret?: string;
  requestId?: string;
}): Promise<{ exists: boolean; secretId: string | null; requestId: string }> {
  const requestId = params.requestId || crypto.randomUUID();
  const response = await postSignedRtdhJson({
    url: secretManagerUrl(params.apiUrl),
    requestId,
    requestSource: "patient-platform:get-rtdh-secret-status",
    webhookSecret: signingSecret(params.fallbackSigningSecret),
    payload: {
      action: "exists",
      tenant: params.tenant,
      provider: params.provider,
      ...(params.context ? { context: params.context } : {}),
      key: params.key ?? "webhook_secret",
    },
    timeoutMs: 8000,
  });

  const text = await response.text();
  let body: unknown = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { raw: text };
  }

  if (!response.ok) {
    const message = typeof body === "object" && body && "error" in body
      ? String((body as { error: unknown }).error)
      : `RTDH secret-manager request failed (${response.status})`;
    throw new Error(message);
  }

  const data = body as
    | { success?: boolean; exists?: unknown; secretId?: unknown; requestId?: unknown }
    | null;
  if (!data?.success || typeof data.exists !== "boolean") {
    throw new Error("RTDH secret-manager response was invalid");
  }

  return {
    exists: data.exists,
    secretId: typeof data.secretId === "string" ? data.secretId : null,
    requestId: typeof data.requestId === "string" ? data.requestId : requestId,
  };
}

export async function saveSecretViaRtdh(params: {
  apiUrl: string;
  tenant: string;
  provider: string;
  context?: string | null;
  key: string;
  value: string;
  fallbackSigningSecret?: string;
  requestId?: string;
}): Promise<RtdhSecretManagerResponse> {
  const requestId = params.requestId || crypto.randomUUID();
  const response = await postSignedRtdhJson({
    url: secretManagerUrl(params.apiUrl),
    requestId,
    requestSource: "patient-platform:set-rtdh-secret",
    webhookSecret: signingSecret(params.fallbackSigningSecret),
    payload: {
      tenant: params.tenant,
      provider: params.provider,
      ...(params.context ? { context: params.context } : {}),
      encoding: "base64",
      key: params.key,
      value: encodeBase64(params.value),
    },
    timeoutMs: 8000,
  });

  const text = await response.text();
  let body: unknown = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { raw: text };
  }

  if (!response.ok) {
    const message = typeof body === "object" && body && "error" in body
      ? String((body as { error: unknown }).error)
      : `RTDH secret-manager request failed (${response.status})`;
    throw new Error(message);
  }

  const data = body as Partial<RtdhSecretManagerResponse> | null;
  if (!data?.success || !Array.isArray(data.saved)) {
    throw new Error("RTDH secret-manager response was invalid");
  }

  return {
    success: true,
    tenant: String(data.tenant ?? params.tenant),
    provider: String(data.provider ?? params.provider),
    context: typeof data.context === "string" ? data.context : null,
    saved: data.saved,
    requestId: typeof data.requestId === "string" ? data.requestId : requestId,
  };
}
