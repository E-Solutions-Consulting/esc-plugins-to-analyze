// comms-n8n-proxy — authenticated tenant-admin surface to the tenant's n8n.
//
// Server-side proxy so the n8n API key never reaches the browser. Verifies JWT +
// tenant membership, then performs the requested n8n operation in the tenant's
// connection (Enterprise project when licensed; webhook-mode fallback).
//
// Actions: get_connection, connect, set_api_key, provision_project, ensure_project,
//          ensure_folder, list_workflows, get_workflow_graph, register_webhook,
//          list_webhooks, delete_webhook.

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  createFolder,
  createProject,
  createWorkflowWithWebhook,
  defaultN8nBaseUrl,
  defaultN8nWebhookBaseUrl,
  folderEditorUrl,
  foldersSupported,
  getWorkflowGraph,
  listWorkflows,
  probeCapabilities,
  projectsEnabled,
  renameFolder,
  resolveApiKey,
  sanitizeFolderName,
  setWorkflowActive,
  tenantProjectName,
  transferWorkflowToFolder,
  workflowEditorUrl,
  type N8nConnection,
} from "../_shared/comms-n8n.ts";
import {
  adminN8nSecretId,
  getSecretValue,
  putSecretIfChanged,
  tenantN8nSecretId,
} from "../_shared/gcp-secret-manager.ts";

// deno-lint-ignore no-explicit-any
type DB = any;

const json = (b: unknown, s: number, cors: Record<string, string>) =>
  new Response(JSON.stringify(b), { status: s, headers: { ...cors, "Content-Type": "application/json" } });

/**
 * Resolve the tenant's n8n API key. Per the RTDH precedent, the key lives in GCP
 * Resolution order:
 *   1. Per-tenant override secret (proj.api_key_secret_ref) — rarely used.
 *   2. Global shared admin key in GCP Secret Manager (n8n-<env>-admin-api-key) —
 *      the normal path: one key entered once in Nexus.
 *   3. N8N_ADMIN_API_KEY env var (demo/legacy fallback).
 */
async function resolveTenantApiKey(proj: Record<string, unknown> | null): Promise<string | null> {
  // 1. Per-tenant override.
  const ref = proj?.api_key_secret_ref as string | undefined;
  const backend = (proj?.api_key_secret_backend as string | undefined) ?? "gcp_secret_manager";
  if (ref) {
    if (backend === "gcp_secret_manager") {
      try {
        const value = await getSecretValue(ref);
        if (value) return value;
      } catch (e) {
        console.error("resolveTenantApiKey: per-tenant GCP secret access failed", e);
      }
    } else {
      const env = resolveApiKey(ref);
      if (env) return env;
    }
  }
  // 2. Global shared admin key (the standard model).
  try {
    const global = await getSecretValue(adminN8nSecretId());
    if (global) return global;
  } catch (e) {
    console.error("resolveTenantApiKey: global admin GCP secret access failed", e);
  }
  // 3. Env fallback.
  return Deno.env.get("N8N_ADMIN_API_KEY") ?? null;
}

async function loadConnection(db: DB, tenantId: string): Promise<N8nConnection> {
  const { data: proj } = await db
    .from("comms_n8n_projects")
    .select("*")
    .eq("tenant_id", tenantId)
    .maybeSingle();
  return {
    mode: proj?.mode ?? (projectsEnabled() ? "projects" : "webhook"),
    baseUrl: proj?.base_url ?? defaultN8nBaseUrl(),
    projectId: proj?.n8n_project_id ?? null,
    apiKey: await resolveTenantApiKey(proj),
  };
}

/**
 * Ensure the tenant has an n8n project row. When Enterprise Projects are enabled
 * and an admin API key is present, create a real n8n project; otherwise record a
 * webhook-fallback row so the rest of the flow degrades gracefully.
 * Idempotent: returns the existing project if already provisioned.
 */
async function ensureProject(db: DB, tenantId: string): Promise<Record<string, unknown>> {
  const { data: existing } = await db
    .from("comms_n8n_projects").select("*").eq("tenant_id", tenantId).maybeSingle();
  // Only short-circuit when a REAL project already exists. A prior webhook_fallback
  // row (recorded when the key wasn't resolving yet) must NOT block a retry — if the
  // admin key now works we should create the real project instead of staying stuck.
  if (existing?.n8n_project_id) {
    return existing;
  }

  const conn = await loadConnection(db, tenantId);
  // No Enterprise/api key -> record fallback, don't fail the caller.
  if (!projectsEnabled() || !conn.apiKey) {
    const { data } = await db.from("comms_n8n_projects").upsert({
      tenant_id: tenantId,
      mode: "webhook",
      base_url: conn.baseUrl,
      provisioning_status: "webhook_fallback",
      is_connected: false,
      last_synced_at: new Date().toISOString(),
    }, { onConflict: "tenant_id" }).select().single();
    return data;
  }

  try {
    const { data: tenantRow } = await db.from("tenants").select("name").eq("id", tenantId).maybeSingle();
    const project = await createProject(conn, tenantProjectName(tenantId, tenantRow?.name));
    const { data } = await db.from("comms_n8n_projects").upsert({
      tenant_id: tenantId,
      mode: "projects",
      base_url: conn.baseUrl,
      n8n_project_id: project.id,
      provisioning_status: "provisioned",
      is_connected: true,
      last_synced_at: new Date().toISOString(),
    }, { onConflict: "tenant_id" }).select().single();
    return data;
  } catch (e) {
    const { data } = await db.from("comms_n8n_projects").upsert({
      tenant_id: tenantId,
      mode: "webhook",
      base_url: conn.baseUrl,
      provisioning_status: "failed",
      provisioning_error: e instanceof Error ? e.message : String(e),
    }, { onConflict: "tenant_id" }).select().single();
    return data;
  }
}

/**
 * Ensure an automation has a folder inside the tenant project (automation = folder).
 * Creates a real n8n folder when supported; otherwise records a 'tag' ledger row so
 * the hierarchy is represented even in the degraded shared-project mode.
 */
async function ensureFolder(
  db: DB,
  tenantId: string,
  automationId: string,
  name: string,
): Promise<Record<string, unknown>> {
  const { data: existing } = await db
    .from("comms_n8n_folders").select("*").eq("automation_id", automationId).maybeSingle();
  if (existing?.n8n_folder_id || existing?.backend === "tag") return existing;

  const project = await ensureProject(db, tenantId);
  const conn = await loadConnection(db, tenantId);
  conn.projectId = (project?.n8n_project_id as string | null) ?? conn.projectId;

  const folderName = sanitizeFolderName(name);
  let folderId: string | null = null;
  let backend = "tag";
  if (conn.projectId && conn.apiKey && (await foldersSupported(conn))) {
    try {
      const folder = await createFolder(conn, folderName);
      folderId = folder.id;
      backend = "folder";
    } catch (e) {
      console.error("ensureFolder: createFolder failed, using tag mode", e);
    }
  }

  const { data } = await db.from("comms_n8n_folders").upsert({
    tenant_id: tenantId,
    automation_id: automationId,
    n8n_project_id: (project?.n8n_project_id as string | null) ?? null,
    n8n_folder_id: folderId,
    name: folderName,
    backend,
  }, { onConflict: "automation_id" }).select().single();

  await db.from("comms_automations")
    .update({ n8n_folder_id: folderId, n8n_folder_name: folderName })
    .eq("id", automationId).eq("tenant_id", tenantId);

  return data;
}

Deno.serve(async (req) => {
  const cors = buildCorsHeaders(req);
  if (req.method === "OPTIONS") return new Response(null, { headers: cors });
  if (req.method !== "POST") return json({ error: "Method not allowed" }, 405, cors);

  try {
    const url = Deno.env.get("SUPABASE_URL")!;
    const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const anonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
    const db: DB = createClient(url, serviceKey, { auth: { autoRefreshToken: false, persistSession: false } });

    const body = await req.json();
    const { action, tenant_id } = body;
    if (!action || !tenant_id) return json({ error: "Missing action or tenant_id" }, 400, cors);

    // Auth: JWT + tenant membership.
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) return json({ error: "Missing authorization header" }, 401, cors);
    const authed = createClient(url, anonKey, {
      global: { headers: { Authorization: authHeader } },
      auth: { autoRefreshToken: false, persistSession: false },
    });
    const { data: { user }, error: userErr } = await authed.auth.getUser();
    if (userErr || !user) return json({ error: "Unauthorized" }, 401, cors);
    const { data: adminUser } = await db.from("admin_users").select("id, is_active").eq("auth_user_id", user.id).maybeSingle();
    if (!adminUser || adminUser.is_active === false) return json({ error: "Forbidden" }, 403, cors);
    const { data: isSuper } = await db.rpc("is_platform_superadmin", { _auth_user_id: user.id });
    if (!isSuper) {
      const { data: membership } = await db
        .from("tenant_memberships").select("id")
        .eq("admin_user_id", adminUser.id).eq("tenant_id", tenant_id).maybeSingle();
      if (!membership) return json({ error: "Forbidden" }, 403, cors);
    }

    switch (action) {
      case "get_connection": {
        const { data } = await db.from("comms_n8n_projects").select("*").eq("tenant_id", tenant_id).maybeSingle();
        return json({
          connection: data ?? null,
          projects_enabled: projectsEnabled(),
          default_base_url: defaultN8nBaseUrl(),
        }, 200, cors);
      }

      case "get_admin_status": {
        // Global (superadmin-only): is the shared n8n admin key configured?
        if (!isSuper) return json({ error: "Superadmin required" }, 403, cors);
        let hasKey = false;
        try {
          hasKey = !!(await getSecretValue(adminN8nSecretId()));
        } catch (_) { /* secret not present / no access */ }
        if (!hasKey && Deno.env.get("N8N_ADMIN_API_KEY")) hasKey = true; // env fallback
        return json({
          has_admin_key: hasKey,
          secret_id: adminN8nSecretId(),
          base_url: defaultN8nBaseUrl(),
          projects_enabled: projectsEnabled(),
          source: hasKey
            ? (Deno.env.get("N8N_ADMIN_API_KEY") ? "env_or_gcp" : "gcp")
            : "none",
        }, 200, cors);
      }

      case "set_admin_api_key": {
        // Global (superadmin-only): store the one shared n8n admin key in GCP
        // Secret Manager (only-on-change). Used to manage all tenant projects.
        if (!isSuper) return json({ error: "Superadmin required" }, 403, cors);
        const apiKey = String(body.api_key ?? "").trim();
        if (apiKey.length < 8) return json({ error: "api_key must be at least 8 characters" }, 400, cors);
        try {
          const result = await putSecretIfChanged(adminN8nSecretId(), apiKey);
          return json({ ok: true, changed: result.changed, secret_id: result.secretRef, gcp_project: result.projectId }, 200, cors);
        } catch (e) {
          const msg = e instanceof Error ? e.message : "secret write failed";
          return json({ error: msg, configured: !msg.includes("not configured") },
            msg.includes("not configured") ? 501 : 500, cors);
        }
      }

      case "probe": {
        // Report what n8n-dev actually supports (reachable / authenticated /
        // Projects API) so the UI shows the truth instead of guessing.
        const conn = await loadConnection(db, tenant_id);
        const caps = await probeCapabilities(conn);
        return json({ capabilities: caps, projects_enabled: projectsEnabled() }, 200, cors);
      }

      case "connect": {
        // Register/refresh a connection row. mode is 'projects' or 'webhook'.
        const mode = projectsEnabled() ? (body.mode ?? "projects") : "webhook";
        const row = {
          tenant_id,
          mode,
          base_url: body.base_url ?? defaultN8nBaseUrl(),
          api_key_secret_ref: body.api_key_secret_ref ?? null,
          n8n_project_id: body.n8n_project_id ?? null,
          is_connected: true,
          last_synced_at: new Date().toISOString(),
        };
        const { data, error } = await db.from("comms_n8n_projects")
          .upsert(row, { onConflict: "tenant_id" }).select().single();
        if (error) throw error;
        return json({ connection: data }, 200, cors);
      }

      case "set_api_key": {
        // Store the tenant's n8n API key in GCP Secret Manager (tenant-prefixed,
        // only-on-change), persist only the secret reference. RTDH precedent.
        const apiKey = String(body.api_key ?? "");
        if (apiKey.trim().length < 8) {
          return json({ error: "api_key must be at least 8 characters" }, 400, cors);
        }
        const secretId = tenantN8nSecretId(tenant_id);
        let result;
        try {
          result = await putSecretIfChanged(secretId, apiKey.trim());
        } catch (e) {
          const msg = e instanceof Error ? e.message : "secret write failed";
          return json({ error: msg, configured: !msg.includes("not configured") },
            msg.includes("not configured") ? 501 : 500, cors);
        }
        const { data, error } = await db.from("comms_n8n_projects").upsert({
          tenant_id,
          base_url: body.base_url ?? defaultN8nBaseUrl(),
          api_key_secret_ref: result.secretRef,
          api_key_secret_backend: "gcp_secret_manager",
          gcp_project_id: result.projectId,
          is_connected: true,
          last_synced_at: new Date().toISOString(),
        }, { onConflict: "tenant_id" }).select().single();
        if (error) throw error;
        return json({ connection: data, changed: result.changed }, 200, cors);
      }

      case "ensure_project": {
        const project = await ensureProject(db, tenant_id);
        return json({ connection: project }, 200, cors);
      }

      case "ensure_folder": {
        const automationId = String(body.automation_id);
        const name = String(body.name ?? "Automation");
        if (!automationId) return json({ error: "Missing automation_id" }, 400, cors);
        const folder = await ensureFolder(db, tenant_id, automationId, name);
        return json({ folder }, 200, cors);
      }

      case "provision_project": {
        // Explicit (re)provision. Uses the same idempotent helper; records status.
        const project = await ensureProject(db, tenant_id);
        return json({ connection: project }, 200, cors);
      }

      case "list_workflows": {
        const conn = await loadConnection(db, tenant_id);
        if (!conn.apiKey) return json({ workflows: [], note: "no_api_key" }, 200, cors);
        // SECURITY: the admin key can see ALL projects in the instance (incl.
        // internal automations). A tenant must only ever see workflows in THEIR
        // OWN n8n project. If they have no project yet, return empty — never the
        // unscoped instance-wide list.
        if (!conn.projectId) {
          return json({ workflows: [], note: "no_project_yet" }, 200, cors);
        }
        const workflows = await listWorkflows(conn); // scoped to conn.projectId
        return json({ workflows }, 200, cors);
      }

      case "get_workflow_graph": {
        const conn = await loadConnection(db, tenant_id);
        const workflowId = String(body.workflow_id);
        // Serve cached graph if present and fresh; else fetch + cache on the webhook row.
        if (conn.apiKey) {
          const graph = await getWorkflowGraph(conn, workflowId);
          if (body.webhook_id) {
            await db.from("comms_n8n_webhooks")
              .update({ graph_cache: graph, graph_cached_at: new Date().toISOString() })
              .eq("id", body.webhook_id).eq("tenant_id", tenant_id);
          }
          return json({ graph }, 200, cors);
        }
        if (body.webhook_id) {
          const { data } = await db.from("comms_n8n_webhooks")
            .select("graph_cache").eq("id", body.webhook_id).eq("tenant_id", tenant_id).maybeSingle();
          if (data?.graph_cache) return json({ graph: data.graph_cache, cached: true }, 200, cors);
        }
        return json({ graph: null, note: "no_api_key_and_no_cache" }, 200, cors);
      }

      case "register_webhook": {
        const w = body.webhook as Record<string, unknown>;
        const row = {
          ...(w.id ? { id: w.id } : {}),
          tenant_id,
          name: w.name,
          n8n_workflow_id: w.n8n_workflow_id ?? null,
          webhook_url: w.webhook_url,
          http_method: w.http_method ?? "POST",
          auth_secret_ref: w.auth_secret_ref ?? null,
        };
        const { data, error } = await db.from("comms_n8n_webhooks")
          .upsert(row, { onConflict: "id" }).select().single();
        if (error) throw error;
        return json({ webhook: data }, 200, cors);
      }

      case "list_webhooks": {
        const { data, error } = await db.from("comms_n8n_webhooks")
          .select("*").eq("tenant_id", tenant_id).eq("is_active", true)
          .order("updated_at", { ascending: false });
        if (error) throw error;
        return json({ webhooks: data }, 200, cors);
      }

      case "delete_webhook": {
        const id = String(body.webhook_id);
        const { error } = await db.from("comms_n8n_webhooks")
          .update({ is_active: false }).eq("id", id).eq("tenant_id", tenant_id);
        if (error) throw error;
        return json({ ok: true }, 200, cors);
      }

      case "create_automation_workflow": {
        // Auto-create an n8n workflow (with a Webhook trigger) for an automation,
        // place it in the automation's folder, register it, and return the webhook
        // URL + editor links so the n8n node can self-wire. Idempotent per automation.
        const automationId = String(body.automation_id);
        const conn = await loadConnection(db, tenant_id);
        if (!conn.apiKey || !conn.projectId) {
          return json({ error: "Tenant has no provisioned n8n project/key yet" }, 409, cors);
        }
        // Reuse if this automation already has a workflow registered.
        const { data: existingWh } = await db.from("comms_n8n_webhooks")
          .select("*").eq("tenant_id", tenant_id).eq("automation_id", automationId)
          .eq("is_active", true).maybeSingle();
        if (existingWh?.n8n_workflow_id) {
          // Re-activate on reuse. A workflow created before activation was wired
          // (or one someone deactivated in n8n) has a DEAD production webhook —
          // this self-heals it instead of silently handing back a 404 URL.
          let reusedActive = false;
          let reusedError: string | null = null;
          try {
            reusedActive = await setWorkflowActive(conn, existingWh.n8n_workflow_id, true);
          } catch (e) {
            reusedError = e instanceof Error ? e.message : String(e);
            console.error("re-activate on reuse failed:", reusedError);
          }
          return json({
            webhook: existingWh,
            reused: true,
            active: reusedActive,
            activation_error: reusedError,
            editor_url: workflowEditorUrl(conn.baseUrl, existingWh.n8n_workflow_id),
          }, 200, cors);
        }

        const { data: automation } = await db.from("comms_automations")
          .select("name, n8n_folder_id").eq("id", automationId).eq("tenant_id", tenant_id).maybeSingle();
        const wfName = sanitizeFolderName(automation?.name ?? "Automation") + " — n8n";
        const path = `comms-${tenant_id.slice(0, 8)}-${automationId.slice(0, 8)}`;

        const created = await createWorkflowWithWebhook(conn, wfName, path);
        // Move it into the tenant project + automation folder.
        try {
          await transferWorkflowToFolder(conn, created.id, conn.projectId, automation?.n8n_folder_id);
        } catch (e) {
          console.error("transferWorkflowToFolder failed (workflow stays at project root):", e);
        }

        // ACTIVATE. n8n creates workflows INACTIVE, and an inactive workflow's
        // PRODUCTION webhook (/webhook/<path>) is not registered with n8n's router
        // — it 404s. We are about to store that production URL as the node's
        // target, so the workflow must be live or the very first trigger fails with
        // n8n_status_404. Best-effort: if activation fails we still register (the
        // UI's Activate button is the recovery), but we report `active` so the UI
        // can tell the truth immediately rather than after a stale refetch.
        let active = false;
        let activationError: string | null = null;
        try {
          active = await setWorkflowActive(conn, created.id, true);
        } catch (e) {
          activationError = e instanceof Error ? e.message : String(e);
          console.error("activate workflow failed (its webhook will 404 until activated):", activationError);
        }

        const webhookUrl = `${defaultN8nWebhookBaseUrl()}/webhook/${created.webhookPath}`;

        // Plain INSERT: this row is new (the reuse guard above returns early), and
        // the previous `upsert(..., { onConflict: "id" })` supplied no id, so it was
        // never an upsert anyway — just an INSERT wearing a misleading hat.
        const { data: wh, error } = await db.from("comms_n8n_webhooks").insert({
          tenant_id,
          automation_id: automationId,
          name: wfName,
          n8n_workflow_id: created.id,
          webhook_url: webhookUrl,
          webhook_path: created.webhookPath,
          http_method: "POST",
        }).select().single();
        if (error) throw error;

        return json({
          webhook: wh,
          active,
          activation_error: activationError,
          editor_url: workflowEditorUrl(conn.baseUrl, created.id),
          folder_url: automation?.n8n_folder_id
            ? folderEditorUrl(conn.baseUrl, conn.projectId, automation.n8n_folder_id) : null,
        }, 200, cors);
      }

      case "set_workflow_active": {
        // Activate/deactivate a tenant workflow (controls from our UI).
        const workflowId = String(body.workflow_id);
        const active = body.active !== false;
        const conn = await loadConnection(db, tenant_id);
        if (!conn.apiKey) return json({ error: "No n8n key" }, 409, cors);
        // Confirm the workflow belongs to this tenant (it must be registered).
        const { data: owned } = await db.from("comms_n8n_webhooks")
          .select("id").eq("tenant_id", tenant_id).eq("n8n_workflow_id", workflowId).maybeSingle();
        if (!owned) return json({ error: "Workflow not registered for this tenant" }, 403, cors);
        const nowActive = await setWorkflowActive(conn, workflowId, active);
        return json({ active: nowActive }, 200, cors);
      }

      case "rename_folder": {
        // Rename an automation's n8n folder (when the automation is renamed).
        const automationId = String(body.automation_id);
        const newName = sanitizeFolderName(String(body.name ?? ""));
        const conn = await loadConnection(db, tenant_id);
        const { data: folderRow } = await db.from("comms_n8n_folders")
          .select("n8n_folder_id").eq("automation_id", automationId).eq("tenant_id", tenant_id).maybeSingle();
        if (conn.apiKey && conn.projectId && folderRow?.n8n_folder_id) {
          try {
            await renameFolder(conn, folderRow.n8n_folder_id, newName);
          } catch (e) {
            console.error("renameFolder failed:", e);
          }
        }
        await db.from("comms_n8n_folders").update({ name: newName })
          .eq("automation_id", automationId).eq("tenant_id", tenant_id);
        await db.from("comms_automations").update({ n8n_folder_name: newName })
          .eq("id", automationId).eq("tenant_id", tenant_id);
        return json({ ok: true, name: newName }, 200, cors);
      }

      case "get_links": {
        // Editor deep-links for an automation (workflow + folder).
        const automationId = String(body.automation_id);
        const conn = await loadConnection(db, tenant_id);
        const [{ data: wh }, { data: folderRow }] = await Promise.all([
          db.from("comms_n8n_webhooks").select("n8n_workflow_id")
            .eq("tenant_id", tenant_id).eq("automation_id", automationId).eq("is_active", true).maybeSingle(),
          db.from("comms_n8n_folders").select("n8n_folder_id")
            .eq("tenant_id", tenant_id).eq("automation_id", automationId).maybeSingle(),
        ]);
        return json({
          editor_url: wh?.n8n_workflow_id ? workflowEditorUrl(conn.baseUrl, wh.n8n_workflow_id) : null,
          folder_url: (conn.projectId && folderRow?.n8n_folder_id)
            ? folderEditorUrl(conn.baseUrl, conn.projectId, folderRow.n8n_folder_id) : null,
          workflow_id: wh?.n8n_workflow_id ?? null,
        }, 200, cors);
      }

      default:
        return json({ error: `Unknown action: ${action}` }, 400, cors);
    }
  } catch (error) {
    console.error("comms-n8n-proxy error:", error);
    return json({ error: error instanceof Error ? error.message : "error" }, 500, cors);
  }
});
