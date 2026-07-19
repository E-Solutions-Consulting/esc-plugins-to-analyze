/**
 * Shared GCP Secret Manager helper.
 *
 * Authenticates to GCP via the JWT-bearer flow using a service-account JSON key
 * stored in the Supabase secret GCP_SECRET_MANAGER_SA_KEY, then calls the Secret
 * Manager REST API.
 *
 * Unlike the RTDH secrets (pre-created by Terraform), tenant-scoped n8n secrets
 * are created on demand, so this helper supports create-if-missing + only-on-change
 * version writes. Callers store only the SECRET NAME (reference) in Supabase —
 * never the secret value.
 */

interface ServiceAccountKey {
  client_email: string;
  private_key: string;
  project_id?: string;
  token_uri?: string;
}

function base64url(input: Uint8Array | string): string {
  const bytes = typeof input === "string" ? new TextEncoder().encode(input) : input;
  let bin = "";
  for (const b of bytes) bin += String.fromCharCode(b);
  return btoa(bin).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

/** Standard (non-url) base64 — Secret Manager payload.data expects RFC 4648 base64. */
function base64Std(value: string): string {
  const bytes = new TextEncoder().encode(value);
  let bin = "";
  for (const b of bytes) bin += String.fromCharCode(b);
  return btoa(bin);
}

function pemToPkcs8(pem: string): ArrayBuffer {
  const body = pem
    .replace(/-----BEGIN PRIVATE KEY-----/, "")
    .replace(/-----END PRIVATE KEY-----/, "")
    .replace(/\s+/g, "");
  const bin = atob(body);
  const buf = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
  return buf.buffer;
}

function loadServiceAccount(): ServiceAccountKey {
  const rawKey = Deno.env.get("GCP_SECRET_MANAGER_SA_KEY");
  if (!rawKey) throw new Error("GCP_SECRET_MANAGER_SA_KEY is not configured");
  return JSON.parse(rawKey) as ServiceAccountKey;
}

/** Project that holds the tenant n8n secrets. Override or fall back to the SA project. */
export function secretsProjectId(sa?: ServiceAccountKey): string {
  const key = sa ?? loadServiceAccount();
  const projectId = Deno.env.get("GCP_N8N_SECRETS_PROJECT_ID") ||
    Deno.env.get("GCP_SECRETS_PROJECT_ID") || key.project_id;
  if (!projectId) throw new Error("GCP secrets project id not configured");
  return projectId;
}

/** Mint a cloud-platform-scoped access token via the SA JWT-bearer grant. */
async function getGcpAccessToken(sa: ServiceAccountKey): Promise<string> {
  const tokenUri = sa.token_uri || "https://oauth2.googleapis.com/token";
  const now = Math.floor(Date.now() / 1000);
  const header = base64url(JSON.stringify({ alg: "RS256", typ: "JWT" }));
  const claims = base64url(
    JSON.stringify({
      iss: sa.client_email,
      scope: "https://www.googleapis.com/auth/cloud-platform",
      aud: tokenUri,
      iat: now,
      exp: now + 3600,
    }),
  );
  const signingInput = `${header}.${claims}`;
  const key = await crypto.subtle.importKey(
    "pkcs8",
    pemToPkcs8(sa.private_key),
    { name: "RSASSA-PKCS1-v1_5", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = await crypto.subtle.sign(
    "RSASSA-PKCS1-v1_5",
    key,
    new TextEncoder().encode(signingInput),
  );
  const jwt = `${signingInput}.${base64url(new Uint8Array(sig))}`;

  const resp = await fetch(tokenUri, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      grant_type: "urn:ietf:params:oauth:grant-type:jwt-bearer",
      assertion: jwt,
    }),
  });
  if (!resp.ok) {
    throw new Error(`GCP token exchange failed (${resp.status}): ${await resp.text()}`);
  }
  const data = await resp.json();
  return data.access_token as string;
}

const SM_BASE = "https://secretmanager.googleapis.com/v1";

/** Ensure a secret resource exists (automatic replication). No-op if it already exists. */
async function ensureSecretExists(
  token: string,
  projectId: string,
  secretId: string,
): Promise<void> {
  const getUrl = `${SM_BASE}/projects/${projectId}/secrets/${secretId}`;
  const getResp = await fetch(getUrl, { headers: { Authorization: `Bearer ${token}` } });
  if (getResp.ok) return;
  // 403 = the SA can addVersion/access but lacks secrets.get (least-privilege,
  // per-secret grant). The secret exists; proceed to addVersion (which it CAN do).
  if (getResp.status === 403) return;
  if (getResp.status !== 404) {
    throw new Error(`Secret Manager get failed (${getResp.status}): ${await getResp.text()}`);
  }
  const createUrl =
    `${SM_BASE}/projects/${projectId}/secrets?secretId=${encodeURIComponent(secretId)}`;
  const createResp = await fetch(createUrl, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({ replication: { automatic: {} } }),
  });
  // 409 = already created concurrently; treat as success.
  if (!createResp.ok && createResp.status !== 409) {
    throw new Error(`Secret Manager create failed (${createResp.status}): ${await createResp.text()}`);
  }
}

/** Read the latest version's value, or null if the secret/version doesn't exist. */
async function accessLatest(
  token: string,
  projectId: string,
  secretId: string,
): Promise<string | null> {
  const url = `${SM_BASE}/projects/${projectId}/secrets/${secretId}/versions/latest:access`;
  const resp = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
  if (resp.status === 404) return null;
  if (!resp.ok) {
    throw new Error(`Secret Manager access failed (${resp.status}): ${await resp.text()}`);
  }
  const data = await resp.json();
  const b64 = data?.payload?.data as string | undefined;
  if (!b64) return null;
  try {
    return new TextDecoder().decode(
      Uint8Array.from(atob(b64), (c) => c.charCodeAt(0)),
    );
  } catch {
    return null;
  }
}

/**
 * Write a secret value (create-if-missing, only-on-change). Returns the secret
 * reference name to persist (the secretId), and whether a new version was written.
 */
export async function putSecretIfChanged(
  secretId: string,
  value: string,
): Promise<{ secretRef: string; projectId: string; changed: boolean }> {
  const sa = loadServiceAccount();
  const projectId = secretsProjectId(sa);
  const token = await getGcpAccessToken(sa);

  await ensureSecretExists(token, projectId, secretId);

  const current = await accessLatest(token, projectId, secretId);
  if (current !== null && current === value) {
    return { secretRef: secretId, projectId, changed: false };
  }

  const addUrl = `${SM_BASE}/projects/${projectId}/secrets/${secretId}:addVersion`;
  const resp = await fetch(addUrl, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({ payload: { data: base64Std(value) } }),
  });
  if (!resp.ok) {
    throw new Error(`Secret Manager addVersion failed (${resp.status}): ${await resp.text()}`);
  }
  return { secretRef: secretId, projectId, changed: true };
}

/** Resolve a secret value by its reference name (latest version). Null if missing. */
export async function getSecretValue(secretRef: string): Promise<string | null> {
  const sa = loadServiceAccount();
  const projectId = secretsProjectId(sa);
  const token = await getGcpAccessToken(sa);
  return await accessLatest(token, projectId, secretRef);
}

/** Deployment env for secret naming (matches the n8n-{env}-... infra convention). */
function n8nEnv(): string {
  const raw = (Deno.env.get("N8N_ENV") || Deno.env.get("ENVIRONMENT") || "dev").toLowerCase();
  if (raw.startsWith("prod")) return "prod";
  if (raw.startsWith("stag")) return "staging";
  return "dev";
}

/**
 * Deterministic per-tenant secret id for an n8n API key.
 * Follows the infra convention "n8n-{env}-..." (allia-infrastructure
 * ai/n8n/{env}/secrets.tf has n8n-{env}-encryption-key, n8n-{env}-gcp-integrations-sa)
 * so our SaaS tenant secrets are clearly namespaced alongside the existing ones
 * and never collide with internal-automation secrets.
 */
export function tenantN8nSecretId(tenantId: string): string {
  return `n8n-${n8nEnv()}-tenant-${tenantId}-api-key`;
}

/**
 * Global (shared) n8n admin API key secret id. One key for the whole instance —
 * the admin key can see/manage all projects, and our app scopes per-tenant by
 * projectId. Entered once in Nexus (platform n8n settings).
 */
export function adminN8nSecretId(): string {
  return `n8n-${n8nEnv()}-admin-api-key`;
}
