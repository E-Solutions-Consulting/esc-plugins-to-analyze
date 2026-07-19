// Shared n8n client for Communications Automations.
//
// Supports two modes (see docs/CommunicationsAutomations.md §3):
//   - "projects": n8n Enterprise Projects API (one project per tenant) — lights up
//                 when N8N_PROJECTS_ENABLED=true and a license is configured.
//   - "webhook":  fallback that POSTs to a registered webhook URL (works today on
//                 community edition). HMAC-signed.
//
// API keys are never stored in the DB; functions resolve them from env/secrets by
// the `api_key_secret_ref` recorded on comms_n8n_projects.

export type N8nMode = "projects" | "webhook";

export interface N8nConnection {
  mode: N8nMode;
  baseUrl: string;        // e.g. https://n8n-dev.alliahealth.co
  projectId?: string | null;
  apiKey?: string | null; // resolved from secret ref by the caller
}

export function projectsEnabled(): boolean {
  // Today our self-hosted n8n is Community Edition (no license / multi-main off),
  // so this is false unless an Enterprise license is later activated and the env
  // flag set. When false, callers run in webhook-mode. See allia-infrastructure
  // ai/n8n/*/terraform.tfvars (n8n_multi_main_enabled = false).
  return (Deno.env.get("N8N_PROJECTS_ENABLED") || "").toLowerCase() === "true";
}

/** Deployment environment for n8n URL/secret defaults: dev | staging | prod. */
export function n8nEnvironment(): "dev" | "staging" | "prod" {
  const raw = (Deno.env.get("N8N_ENV") || Deno.env.get("ENVIRONMENT") || "dev").toLowerCase();
  if (raw.startsWith("prod")) return "prod";
  if (raw.startsWith("stag")) return "staging";
  return "dev";
}

// Real hostnames from allia-infrastructure (ai/n8n/{env}/terraform.tfvars).
const N8N_UI_HOSTS: Record<string, string> = {
  dev: "https://n8n-dev.alliahealth.co",
  staging: "https://n8n-staging.alliahealth.co",
  prod: "https://n8n.alliahealth.co",
};
const N8N_WEBHOOK_HOSTS: Record<string, string> = {
  dev: "https://n8n-dev-webhooks.alliahealth.co",
  staging: "https://n8n-staging-webhooks.alliahealth.co",
  prod: "https://n8n-webhooks.alliahealth.co",
};

/** Default n8n UI/API base URL (env override wins, else per-environment default). */
export function defaultN8nBaseUrl(): string {
  return Deno.env.get("N8N_BASE_URL") || N8N_UI_HOSTS[n8nEnvironment()];
}

/** Default n8n webhook base URL (separate ingress from the UI/API host). */
export function defaultN8nWebhookBaseUrl(): string {
  return Deno.env.get("N8N_WEBHOOK_BASE_URL") || N8N_WEBHOOK_HOSTS[n8nEnvironment()];
}

/**
 * Project name for a tenant. Prefixed (`Comms <env> ·`) so our SaaS tenant
 * projects are clearly namespaced and never collide with internal company
 * automations (Pharmacy Data Entry, etc.) that share this n8n instance. Uses the
 * tenant's friendly name when known so it's recognisable in the n8n UI; the
 * tenant_id is appended (short) to guarantee uniqueness.
 */
export function tenantProjectName(tenantId: string, tenantName?: string | null): string {
  const env = n8nEnvironment();
  const label = (tenantName || "").trim();
  const shortId = tenantId.slice(0, 8);
  if (label) {
    // n8n project names allow most chars, but keep it clean.
    const safe = label.replace(/[^A-Za-z0-9 _.-]+/g, " ").replace(/\s+/g, " ").trim().slice(0, 60);
    return `Comms ${env} · ${safe} (${shortId})`;
  }
  return `comms-${env}-tenant-${tenantId}`;
}

/**
 * Sanitize a name for an n8n folder. n8n rejects characters like ':' and '/'
 * ("Folder name contains invalid characters") — verified against n8n-dev. Allow
 * letters/numbers/space/hyphen/underscore/period; collapse everything else to a
 * space, and never return empty.
 */
export function sanitizeFolderName(name: string): string {
  const cleaned = (name || "")
    .replace(/[^A-Za-z0-9 _.-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, 100);
  return cleaned || "Automation";
}

/** Resolve an n8n API key from a Supabase secret reference (env var name). */
export function resolveApiKey(secretRef?: string | null): string | null {
  if (!secretRef) return null;
  return Deno.env.get(secretRef) || null;
}

async function n8nFetch(
  conn: N8nConnection,
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const headers = new Headers(init.headers);
  if (conn.apiKey) headers.set("X-N8N-API-KEY", conn.apiKey);
  headers.set("Accept", "application/json");
  return await fetch(`${conn.baseUrl.replace(/\/$/, "")}${path}`, {
    ...init,
    headers,
  });
}

// ---------------------------------------------------------------------------
// Capability probe
// ---------------------------------------------------------------------------

export interface N8nCapabilities {
  reachable: boolean;
  authenticated: boolean;          // API key accepted (workflows endpoint 200)
  projectsApi: boolean;            // /api/v1/projects responds (Enterprise)
  workflowCount: number | null;
  baseUrl: string;
  detail: string;
}

/**
 * Probe n8n: is it reachable, does the API key work, and is the Projects
 * (Enterprise) API available? Used to decide real-projects vs webhook-mode and to
 * surface the truth in the UI instead of guessing.
 */
export async function probeCapabilities(conn: N8nConnection): Promise<N8nCapabilities> {
  const out: N8nCapabilities = {
    reachable: false,
    authenticated: false,
    projectsApi: false,
    workflowCount: null,
    baseUrl: conn.baseUrl,
    detail: "",
  };
  if (!conn.apiKey) {
    out.detail = "no_api_key";
    return out;
  }
  // 1) Workflows endpoint => reachable + authenticated (and gives a count).
  try {
    const wf = await n8nFetch(conn, "/api/v1/workflows?limit=1");
    out.reachable = true;
    if (wf.ok) {
      out.authenticated = true;
      try {
        const body = await wf.json();
        const items = (body.data ?? body) as unknown[];
        out.workflowCount = Array.isArray(items) ? items.length : null;
      } catch { /* ignore */ }
    } else {
      out.detail = `workflows_${wf.status}`;
    }
  } catch (e) {
    out.detail = `unreachable:${e instanceof Error ? e.message : "err"}`;
    return out;
  }
  // 2) Projects endpoint => Enterprise Projects available.
  try {
    const pr = await n8nFetch(conn, "/api/v1/projects?limit=1");
    out.projectsApi = pr.ok;
    if (!pr.ok && !out.detail) out.detail = `projects_${pr.status}`;
  } catch { /* projectsApi stays false */ }
  return out;
}

// ---------------------------------------------------------------------------
// Enterprise Projects API
// ---------------------------------------------------------------------------

/** Create an n8n project for a tenant. Requires Enterprise license + admin API key. */
export async function createProject(
  conn: N8nConnection,
  name: string,
): Promise<{ id: string }> {
  const res = await n8nFetch(conn, "/api/v1/projects", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name }),
  });
  if (!res.ok) {
    throw new Error(`n8n createProject failed: ${res.status} ${await res.text()}`);
  }
  const body = await res.json();
  return { id: body.id ?? body.data?.id };
}

/**
 * List workflows in the connection's project. ALWAYS scoped to conn.projectId —
 * the admin API key can see every project in the instance (including internal
 * automations), so listing must never be unscoped. Throws if no project is set.
 */
export async function listWorkflows(
  conn: N8nConnection,
): Promise<Array<{ id: string; name: string; active: boolean }>> {
  if (!conn.projectId) {
    throw new Error("listWorkflows requires a project id (refusing to list the whole instance)");
  }
  const query = `?projectId=${encodeURIComponent(conn.projectId)}&limit=250`;
  const res = await n8nFetch(conn, `/api/v1/workflows${query}`);
  if (!res.ok) {
    throw new Error(`n8n listWorkflows failed: ${res.status} ${await res.text()}`);
  }
  const body = await res.json();
  const items = (body.data ?? body) as Array<Record<string, unknown>>;
  return items.map((w) => ({
    id: String(w.id),
    name: String(w.name ?? ""),
    active: Boolean(w.active),
  }));
}

/**
 * Create a folder inside a project (n8n folders feature). Returns the folder id.
 * Only available when the instance has the folders capability; callers must fall
 * back to tag-mode when this throws/404s.
 */
export async function createFolder(
  conn: N8nConnection,
  name: string,
  parentFolderId?: string | null,
): Promise<{ id: string }> {
  if (!conn.projectId) throw new Error("createFolder requires a project id");
  const body: Record<string, unknown> = { name };
  if (parentFolderId) body.parentFolderId = parentFolderId;
  const res = await n8nFetch(conn, `/api/v1/projects/${encodeURIComponent(conn.projectId)}/folders`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    throw new Error(`n8n createFolder failed: ${res.status} ${await res.text()}`);
  }
  const json = await res.json();
  return { id: String(json.id ?? json.data?.id) };
}

/** Probe whether this n8n instance exposes the folders capability for the project. */
export async function foldersSupported(conn: N8nConnection): Promise<boolean> {
  if (!conn.projectId || !conn.apiKey) return false;
  const res = await n8nFetch(conn, `/api/v1/projects/${encodeURIComponent(conn.projectId)}/folders`);
  return res.ok;
}

/** Fetch a workflow's full graph (nodes + connections) for read-only visualisation. */
export async function getWorkflowGraph(
  conn: N8nConnection,
  workflowId: string,
): Promise<unknown> {
  const res = await n8nFetch(conn, `/api/v1/workflows/${encodeURIComponent(workflowId)}`);
  if (!res.ok) {
    throw new Error(`n8n getWorkflowGraph failed: ${res.status} ${await res.text()}`);
  }
  const body = await res.json();
  return body.data ?? body;
}

// ---------------------------------------------------------------------------
// Workflow lifecycle — create / place-in-folder / activate / deactivate
// (all verified against n8n-dev)
// ---------------------------------------------------------------------------

export interface CreatedWorkflow {
  id: string;
  webhookId: string | null;
  webhookPath: string | null;
  active: boolean;
}

/**
 * Create a workflow seeded with a single Webhook trigger node. n8n auto-assigns a
 * webhookId; the invocable URL is `${webhookBaseUrl}/webhook/${path}`. The public
 * create API can't target a project/folder, so callers should follow with
 * transferWorkflowToFolder().
 */
export async function createWorkflowWithWebhook(
  conn: N8nConnection,
  name: string,
  webhookPath: string,
): Promise<CreatedWorkflow> {
  const workflow = {
    name,
    nodes: [
      {
        parameters: { httpMethod: "POST", path: webhookPath, responseMode: "onReceived" },
        id: crypto.randomUUID(),
        name: "Comms Trigger",
        type: "n8n-nodes-base.webhook",
        typeVersion: 2,
        position: [260, 300],
      },
    ],
    connections: {},
    settings: {},
  };
  const res = await n8nFetch(conn, "/api/v1/workflows", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(workflow),
  });
  if (!res.ok) {
    throw new Error(`n8n createWorkflow failed: ${res.status} ${await res.text()}`);
  }
  const body = await res.json();
  const data = body.data ?? body;
  const node = (data.nodes ?? [])[0] ?? {};
  return {
    id: String(data.id),
    webhookId: node.webhookId ? String(node.webhookId) : null,
    webhookPath: node.parameters?.path ? String(node.parameters.path) : webhookPath,
    active: Boolean(data.active),
  };
}

/**
 * Move a workflow into a tenant project + (optionally) a specific folder.
 * `destinationParentFolderId` is undocumented in the public OpenAPI but accepted
 * by n8n-dev (verified: PUT returns 204). Without it the workflow lands at project
 * root.
 */
export async function transferWorkflowToFolder(
  conn: N8nConnection,
  workflowId: string,
  destinationProjectId: string,
  destinationParentFolderId?: string | null,
): Promise<void> {
  const body: Record<string, unknown> = { destinationProjectId };
  if (destinationParentFolderId) body.destinationParentFolderId = destinationParentFolderId;
  const res = await n8nFetch(conn, `/api/v1/workflows/${encodeURIComponent(workflowId)}/transfer`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  // 200/204 are both success here.
  if (!res.ok) {
    throw new Error(`n8n transferWorkflow failed: ${res.status} ${await res.text()}`);
  }
}

/** Activate (start) or deactivate (stop) a workflow. Returns the new active state. */
export async function setWorkflowActive(
  conn: N8nConnection,
  workflowId: string,
  active: boolean,
): Promise<boolean> {
  const verb = active ? "activate" : "deactivate";
  const res = await n8nFetch(conn, `/api/v1/workflows/${encodeURIComponent(workflowId)}/${verb}`, {
    method: "POST",
  });
  if (!res.ok) {
    throw new Error(`n8n ${verb} failed: ${res.status} ${await res.text()}`);
  }
  try {
    const body = await res.json();
    return Boolean((body.data ?? body).active);
  } catch {
    return active;
  }
}

/** Rename a folder (used when an automation is renamed). PATCH; verified 200. */
export async function renameFolder(
  conn: N8nConnection,
  folderId: string,
  name: string,
): Promise<void> {
  if (!conn.projectId) throw new Error("renameFolder requires a project id");
  const res = await n8nFetch(
    conn,
    `/api/v1/projects/${encodeURIComponent(conn.projectId)}/folders/${encodeURIComponent(folderId)}`,
    { method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ name }) },
  );
  if (!res.ok) {
    throw new Error(`n8n renameFolder failed: ${res.status} ${await res.text()}`);
  }
}

/** Editor deep-link to a single workflow. */
export function workflowEditorUrl(baseUrl: string, workflowId: string): string {
  return `${baseUrl.replace(/\/$/, "")}/workflow/${workflowId}`;
}

/** Deep-link to a workflow's execution list (newest first) — "show me the run". */
export function workflowExecutionsUrl(baseUrl: string, workflowId: string): string {
  return `${baseUrl.replace(/\/$/, "")}/workflow/${workflowId}/executions`;
}

/**
 * The TEST webhook URL for a production webhook URL.
 *
 * n8n serves each webhook at two paths: `/webhook/<path>` (production — live only
 * while the workflow is ACTIVE) and `/webhook-test/<path>` (test — live only while
 * the editor has "Listen for test event" armed, and only for a single call).
 *
 * HOSTS DIFFER in our queue-mode deployment: production webhooks are served by
 * the dedicated webhook processors (n8n-*-webhooks.alliahealth.co), but TEST
 * webhooks are registered on the MAIN/editor instance only — the webhook host
 * answers /webhook-test/ with a bare "Cannot POST" (verified against dev).
 * Building the test URL on the webhook host meant "Listen for test event"
 * never received anything, no matter what the author did in n8n.
 */
export function toTestWebhookUrl(
  productionUrl: string,
  webhookPath?: string | null,
): string {
  const path = webhookPath ??
    productionUrl.split("/webhook/")[1] ?? null;
  if (path) {
    return `${defaultN8nBaseUrl().replace(/\/$/, "")}/webhook-test/${path}`;
  }
  // Last resort (no derivable path): same-host rewrite.
  return productionUrl.replace("/webhook/", "/webhook-test/");
}

/** Editor deep-link to an automation's folder (project → folder → workflows). */
export function folderEditorUrl(baseUrl: string, projectId: string, folderId: string): string {
  return `${baseUrl.replace(/\/$/, "")}/projects/${projectId}/folders/${folderId}/workflows`;
}

// ---------------------------------------------------------------------------
// Webhook invocation (works in both modes)
// ---------------------------------------------------------------------------

/** Compute an HMAC-SHA256 hex signature for a webhook payload. */
export async function signPayload(secret: string, payload: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(payload));
  return [...new Uint8Array(sig)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

export interface TriggerWebhookResult {
  ok: boolean;
  status: number;
  executionId?: string;
  body?: string;
}

/** POST a JSON payload to an n8n webhook, optionally HMAC-signed. */
export async function triggerWebhook(
  webhookUrl: string,
  payload: Record<string, unknown>,
  opts: { signingSecret?: string | null; authHeaderValue?: string | null; method?: string } = {},
): Promise<TriggerWebhookResult> {
  const bodyStr = JSON.stringify(payload);
  const headers: Record<string, string> = { "Content-Type": "application/json" };
  if (opts.signingSecret) {
    headers["X-Comms-Signature"] = await signPayload(opts.signingSecret, bodyStr);
  }
  if (opts.authHeaderValue) {
    headers["Authorization"] = opts.authHeaderValue;
  }
  const res = await fetch(webhookUrl, {
    method: opts.method || "POST",
    headers,
    body: bodyStr,
  });
  const text = await res.text();
  let executionId: string | undefined;
  try {
    executionId = JSON.parse(text)?.executionId;
  } catch (_) {
    // n8n webhooks may return plain text; ignore parse failures.
  }
  return { ok: res.ok, status: res.status, executionId, body: text.slice(0, 500) };
}
