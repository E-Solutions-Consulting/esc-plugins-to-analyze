/**
 * Platform-admin n8n settings ("via Nexus").
 *
 * Model: ONE shared n8n admin API key for the whole instance (entered once,
 * stored in GCP Secret Manager as n8n-<env>-admin-api-key). The admin key can see
 * every project in the instance, so per-tenant views are ALWAYS scoped to that
 * tenant's own project — a tenant never sees internal/other-tenant workflows.
 *
 * Per tenant: provision a project (comms-<env>-tenant-<id>); each automation
 * auto-creates a folder. No per-tenant key entry — the shared admin key is used.
 */
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, KeyRound, Loader2, Workflow } from "lucide-react";
import { useAuth } from "@/stores/authStore";
import { supabase } from "@/integrations/supabase/client";
import { PageHeader } from "@/components/common/PageHeader";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "sonner";
import {
  useN8nAdminStatus,
  usePlatformN8nConnection,
  usePlatformN8nProbe,
  usePlatformN8nWorkflows,
  usePlatformProvisionProject,
  useSetN8nAdminKey,
} from "@/hooks/useCommsN8n";
import type { N8nCapabilities } from "@/hooks/useCommsN8n";
import type { N8nWorkflowSummary } from "@/lib/comms-automations/types";

interface TenantRow {
  id: string;
  name: string;
}

const STATUS_VARIANT: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  provisioned: "default",
  pending: "secondary",
  provisioning: "secondary",
  webhook_fallback: "outline",
  failed: "destructive",
};

export function PlatformN8nContent() {
  const { isPlatformSuperadmin } = useAuth();
  const [tenantId, setTenantId] = useState<string | undefined>();
  const [adminKey, setAdminKey] = useState("");

  const { data: tenants = [] } = useQuery({
    queryKey: ["n8n-platform-tenants"],
    queryFn: async () => {
      const { data, error } = await supabase.from("tenants").select("id, name").order("name");
      if (error) throw error;
      return data as TenantRow[];
    },
    enabled: isPlatformSuperadmin,
  });

  const { data: adminStatus, isLoading: adminLoading } = useN8nAdminStatus();
  const setAdminKeyMut = useSetN8nAdminKey();
  const { data: connData, isLoading: connLoading } = usePlatformN8nConnection(tenantId);
  const provisionMut = usePlatformProvisionProject();
  const probeMut = usePlatformN8nProbe();
  const workflowsMut = usePlatformN8nWorkflows();
  const [caps, setCaps] = useState<N8nCapabilities | null>(null);
  const [workflows, setWorkflows] = useState<N8nWorkflowSummary[] | null>(null);

  if (!isPlatformSuperadmin) {
    return (
      <div className="flex h-full items-center justify-center">
        <p className="text-muted-foreground">Access denied. Platform Superadmin role required.</p>
      </div>
    );
  }

  const connection = connData?.connection;
  const projectsEnabled = adminStatus?.projects_enabled ?? connData?.projects_enabled;

  const handleSaveAdminKey = async () => {
    if (adminKey.trim().length < 8) {
      toast.error("API key must be at least 8 characters");
      return;
    }
    try {
      const res = await setAdminKeyMut.mutateAsync(adminKey.trim());
      setAdminKey("");
      toast.success(res.changed ? "Admin key stored in Secret Manager" : "Admin key unchanged");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to store admin key");
    }
  };

  const handleProbe = async () => {
    try {
      const res = await probeMut.mutateAsync(tenantId ?? "global");
      setCaps(res.capabilities);
      toast[res.capabilities.authenticated ? "success" : "error"](
        res.capabilities.authenticated
          ? `Connected · ${res.capabilities.projectsApi ? "Projects API available" : "no Projects API"}`
          : `Not authenticated (${res.capabilities.detail || "check the admin key"})`,
      );
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Probe failed");
    }
  };

  const handleProvision = async () => {
    if (!tenantId) return;
    try {
      await provisionMut.mutateAsync(tenantId);
      toast.success("Provisioning requested");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Provision failed");
    }
  };

  const handleListWorkflows = async () => {
    if (!tenantId) return;
    try {
      const res = await workflowsMut.mutateAsync(tenantId);
      setWorkflows(res.workflows ?? []);
      if (res.note === "no_project_yet") {
        toast.message("This tenant has no n8n project yet — provision one first.");
      } else if (res.note === "no_api_key") {
        toast.error("No admin key configured.");
      }
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Could not list workflows");
    }
  };

  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="n8n"
        description="One shared admin connection manages all tenant projects. Each tenant gets an isolated n8n project (comms-<env>-tenant-<id>); each automation becomes a folder. Tenant views are always scoped to that tenant's own project."
      />

      {/* --- Global connection (entered once) --- */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <Workflow className="h-5 w-5 text-rose-500" /> n8n connection (shared)
              </CardTitle>
              <CardDescription>
                The admin API key for the whole n8n instance, written to GCP Secret Manager
                (<code>{adminStatus?.secret_id ?? "n8n-<env>-admin-api-key"}</code>), only-on-change, never
                shown back. Used to provision and manage every tenant project.
              </CardDescription>
            </div>
            {adminLoading ? (
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            ) : adminStatus?.has_admin_key ? (
              <Badge variant="default" className="gap-1">
                <CheckCircle2 className="h-3.5 w-3.5" /> Key configured
              </Badge>
            ) : (
              <Badge variant="secondary">No key</Badge>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <Badge variant="outline">Instance: {adminStatus?.base_url ?? "n8n-dev.alliahealth.co"}</Badge>
            <Badge variant="outline">Projects: {projectsEnabled ? "enabled" : "off"}</Badge>
            {adminStatus?.has_admin_key && (
              <Badge variant="outline" className="gap-1">
                <KeyRound className="h-3 w-3" /> {adminStatus.secret_id}
              </Badge>
            )}
          </div>
          <div className="space-y-1.5">
            <Label>n8n admin API key</Label>
            <Input
              type="password"
              placeholder={adminStatus?.has_admin_key ? "•••••••• (set — paste to rotate)" : "Paste the n8n admin API key"}
              value={adminKey}
              onChange={(e) => setAdminKey(e.target.value)}
            />
          </div>
          <div className="flex flex-wrap gap-2">
            <Button onClick={handleSaveAdminKey} disabled={setAdminKeyMut.isPending}>
              {setAdminKeyMut.isPending && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
              {adminStatus?.has_admin_key ? "Rotate key" : "Store key"}
            </Button>
            <Button variant="outline" onClick={handleProbe} disabled={probeMut.isPending}>
              {probeMut.isPending && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
              Test connection
            </Button>
          </div>
          {caps && (
            <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/30 p-3 text-sm">
              <Badge variant={caps.reachable ? "default" : "destructive"}>
                {caps.reachable ? "Reachable" : "Unreachable"}
              </Badge>
              <Badge variant={caps.authenticated ? "default" : "secondary"}>
                {caps.authenticated ? "Authenticated" : "Not authenticated"}
              </Badge>
              <Badge variant={caps.projectsApi ? "default" : "outline"}>
                {caps.projectsApi ? "Projects API ✓" : "No Projects API"}
              </Badge>
              {caps.detail && <span className="text-xs text-muted-foreground">{caps.detail}</span>}
            </div>
          )}
        </CardContent>
      </Card>

      {/* --- Per-tenant project (no key field) --- */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Tenant project</CardTitle>
          <CardDescription>Pick a tenant to provision and inspect its isolated n8n project.</CardDescription>
        </CardHeader>
        <CardContent>
          <Select value={tenantId} onValueChange={(v) => { setTenantId(v); setWorkflows(null); }}>
            <SelectTrigger className="max-w-sm"><SelectValue placeholder="Select a tenant" /></SelectTrigger>
            <SelectContent>
              {tenants.map((t) => (
                <SelectItem key={t.id} value={t.id}>{t.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </CardContent>
      </Card>

      {tenantId && (
        <>
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-base">Project status</CardTitle>
                  <CardDescription>This tenant's isolated n8n project.</CardDescription>
                </div>
                {connection?.n8n_project_id ? (
                  <Badge variant="default" className="gap-1">
                    <CheckCircle2 className="h-3.5 w-3.5" /> Provisioned
                  </Badge>
                ) : (
                  <Badge variant="secondary">Not provisioned</Badge>
                )}
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {connLoading ? (
                <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              ) : (
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <span className="text-muted-foreground">Status:</span>
                  <Badge variant={STATUS_VARIANT[connection?.provisioning_status ?? "pending"] ?? "secondary"}>
                    {connection?.provisioning_status ?? "pending"}
                  </Badge>
                  {connection?.n8n_project_id && (
                    <Badge variant="outline">project: {connection.n8n_project_id}</Badge>
                  )}
                </div>
              )}
              {connection?.provisioning_error && (
                <p className="text-xs text-destructive">{connection.provisioning_error}</p>
              )}
              <Button variant="outline" onClick={handleProvision} disabled={provisionMut.isPending}>
                {provisionMut.isPending && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
                {connection?.n8n_project_id ? "Re-provision" : "Provision tenant project"}
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-base">Workflows in this tenant's project</CardTitle>
                  <CardDescription>
                    Scoped to this tenant's project only — internal/other-tenant workflows are never shown.
                  </CardDescription>
                </div>
                <Button variant="outline" size="sm" onClick={handleListWorkflows} disabled={workflowsMut.isPending}>
                  {workflowsMut.isPending && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
                  Refresh
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {workflows == null ? (
                <p className="text-sm text-muted-foreground">Click Refresh to list this project's workflows.</p>
              ) : workflows.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No workflows in this tenant's project yet. Create one in n8n inside the tenant project.
                </p>
              ) : (
                <ul className="space-y-2">
                  {workflows.map((w) => (
                    <li key={w.id} className="flex items-center justify-between rounded-md border px-3 py-2">
                      <span className="text-sm font-medium">{w.name || w.id}</span>
                      <Badge variant={w.active ? "default" : "secondary"}>
                        {w.active ? "Active" : "Inactive"}
                      </Badge>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}

export default PlatformN8nContent;
