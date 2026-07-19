/** React Query hooks for the per-tenant n8n integration (via comms-n8n-proxy). */
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import type {
  CommsN8nConnection,
  CommsN8nWebhook,
  N8nWorkflowSummary,
} from "@/lib/comms-automations/types";

const FN = "comms-n8n-proxy";

async function call<T>(action: string, tenantId: string, payload: Record<string, unknown> = {}): Promise<T> {
  const { data, error } = await supabase.functions.invoke<T>(FN, {
    body: { action, tenant_id: tenantId, ...payload },
  });
  if (error) throw new Error(error.message);
  if (!data) throw new Error("No response from comms-n8n-proxy");
  return data;
}

export function useN8nConnection() {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-n8n-connection", currentTenantId],
    queryFn: async () =>
      await call<{
        connection: CommsN8nConnection | null;
        projects_enabled: boolean;
        default_base_url: string;
      }>("get_connection", currentTenantId!),
    enabled: !!currentTenantId,
  });
}

export function useConnectN8n() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: Record<string, unknown>) =>
      await call<{ connection: CommsN8nConnection }>("connect", currentTenantId!, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-n8n-connection", currentTenantId] }),
  });
}

export function useProvisionN8nProject() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (project_name?: string) =>
      await call<{ connection: CommsN8nConnection }>("provision_project", currentTenantId!, { project_name }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-n8n-connection", currentTenantId] }),
  });
}

export function useN8nWorkflows(enabled = true) {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-n8n-workflows", currentTenantId],
    queryFn: async () => {
      const res = await call<{ workflows: N8nWorkflowSummary[] }>("list_workflows", currentTenantId!);
      return res.workflows;
    },
    enabled: !!currentTenantId && enabled,
  });
}

export function useN8nWebhooks() {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-n8n-webhooks", currentTenantId],
    queryFn: async () => {
      const res = await call<{ webhooks: CommsN8nWebhook[] }>("list_webhooks", currentTenantId!);
      return res.webhooks;
    },
    enabled: !!currentTenantId,
  });
}

export function useRegisterN8nWebhook() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (webhook: Partial<CommsN8nWebhook>) =>
      await call<{ webhook: CommsN8nWebhook }>("register_webhook", currentTenantId!, { webhook }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-n8n-webhooks", currentTenantId] }),
  });
}

export function useN8nWorkflowGraph() {
  const { currentTenantId } = useAuth();
  return useMutation({
    mutationFn: async (input: { workflow_id: string; webhook_id?: string }) =>
      await call<{ graph: unknown; cached?: boolean }>("get_workflow_graph", currentTenantId!, input),
  });
}

export interface AutomationWorkflowResult {
  webhook: CommsN8nWebhook;
  editor_url: string | null;
  folder_url?: string | null;
  reused?: boolean;
  /** Live active state after create/reuse. An INACTIVE workflow's webhook 404s. */
  active?: boolean;
  /** Set when activation failed — surface it; a silent failure means a dead step. */
  activation_error?: string | null;
}

/** Auto-create (or reuse) an n8n workflow+webhook for an automation, placed in its
 *  folder, activated, and registered so the n8n node self-wires. */
export function useCreateAutomationWorkflow() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (automationId: string) =>
      await call<AutomationWorkflowResult>("create_automation_workflow", currentTenantId!, {
        automation_id: automationId,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["comms-n8n-webhooks", currentTenantId] });
      // The workflow LIST drives the Active/Inactive badge, and it is stale the
      // moment we create+activate a workflow — without this the badge reads
      // "Inactive" for a workflow we just switched on.
      qc.invalidateQueries({ queryKey: ["comms-n8n-workflows", currentTenantId] });
    },
  });
}

/** Activate / deactivate an n8n workflow from our UI. */
export function useSetWorkflowActive() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { workflow_id: string; active: boolean }) =>
      await call<{ active: boolean }>("set_workflow_active", currentTenantId!, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-n8n-workflows", currentTenantId] }),
  });
}

/** Editor deep-links (workflow + folder) for an automation. */
export function useN8nLinks() {
  const { currentTenantId } = useAuth();
  return useMutation({
    mutationFn: async (automationId: string) =>
      await call<{ editor_url: string | null; folder_url: string | null; workflow_id: string | null }>(
        "get_links", currentTenantId!, { automation_id: automationId },
      ),
  });
}

/** Rename an automation's n8n folder (called when the automation is renamed). */
export function useRenameAutomationFolder() {
  const { currentTenantId } = useAuth();
  return useMutation({
    mutationFn: async (input: { automation_id: string; name: string }) =>
      await call<{ ok: boolean; name: string }>("rename_folder", currentTenantId!, input),
  });
}

/* ----------------------------------------------------------------------------
 * Platform-admin variants: operate on an EXPLICIT tenant_id (a platform
 * superadmin manages each tenant's n8n connection). The proxy authorises
 * platform superadmins for any tenant.
 * ------------------------------------------------------------------------- */

export function usePlatformN8nConnection(tenantId: string | undefined) {
  return useQuery({
    queryKey: ["platform-n8n-connection", tenantId],
    queryFn: async () =>
      await call<{
        connection: CommsN8nConnection | null;
        projects_enabled: boolean;
        default_base_url: string;
      }>("get_connection", tenantId!),
    enabled: !!tenantId,
  });
}

export function usePlatformSetN8nApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { tenant_id: string; api_key: string; base_url?: string }) => {
      const { tenant_id, ...rest } = input;
      return await call<{ connection: CommsN8nConnection; changed: boolean }>("set_api_key", tenant_id, rest);
    },
    onSuccess: (_d, vars) =>
      qc.invalidateQueries({ queryKey: ["platform-n8n-connection", vars.tenant_id] }),
  });
}

// --- Global shared admin key (entered once; superadmin-only) ---
// Global actions still pass a tenant_id to satisfy the proxy auth header; the
// superadmin path ignores it. We pass "global" as a harmless placeholder.

export interface N8nAdminStatus {
  has_admin_key: boolean;
  secret_id: string;
  base_url: string;
  projects_enabled: boolean;
  source: string;
}

export function useN8nAdminStatus() {
  return useQuery({
    queryKey: ["n8n-admin-status"],
    queryFn: async () => await call<N8nAdminStatus>("get_admin_status", "global"),
  });
}

export function useSetN8nAdminKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (api_key: string) =>
      await call<{ ok: boolean; changed: boolean; secret_id: string }>("set_admin_api_key", "global", { api_key }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["n8n-admin-status"] }),
  });
}

export function usePlatformProvisionProject() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (tenantId: string) =>
      await call<{ connection: CommsN8nConnection }>("provision_project", tenantId),
    onSuccess: (_d, tenantId) =>
      qc.invalidateQueries({ queryKey: ["platform-n8n-connection", tenantId] }),
  });
}

export interface N8nCapabilities {
  reachable: boolean;
  authenticated: boolean;
  projectsApi: boolean;
  workflowCount: number | null;
  baseUrl: string;
  detail: string;
}

export function usePlatformN8nProbe() {
  return useMutation({
    mutationFn: async (tenantId: string) =>
      await call<{ capabilities: N8nCapabilities; projects_enabled: boolean }>("probe", tenantId),
  });
}

export function usePlatformN8nWorkflows() {
  return useMutation({
    mutationFn: async (tenantId: string) =>
      await call<{ workflows: N8nWorkflowSummary[]; note?: string }>("list_workflows", tenantId),
  });
}
