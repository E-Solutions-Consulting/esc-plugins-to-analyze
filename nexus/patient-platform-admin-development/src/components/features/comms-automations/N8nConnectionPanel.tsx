/**
 * Settings panel to connect the tenant's n8n and register selectable workflows.
 * - Shows connection state (Enterprise Projects vs webhook-mode).
 * - Lets the admin provision a per-tenant n8n project (when licensed) or register
 *   a webhook URL to invoke as an `n8n` node in automations.
 * - Previews a registered workflow's graph.
 */
import { useState } from "react";
import { CheckCircle2, Loader2, Plus, Workflow } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { toast } from "sonner";
import { N8nFlowViz } from "./N8nFlowViz";
import {
  useN8nConnection,
  useN8nWebhooks,
  useN8nWorkflowGraph,
  useRegisterN8nWebhook,
} from "@/hooks/useCommsN8n";

export function N8nConnectionPanel() {
  const { data: connData, isLoading } = useN8nConnection();
  const { data: webhooks = [] } = useN8nWebhooks();
  const registerWebhook = useRegisterN8nWebhook();
  const graphMutation = useN8nWorkflowGraph();

  const [whName, setWhName] = useState("");
  const [whUrl, setWhUrl] = useState("");
  const [whWorkflowId, setWhWorkflowId] = useState("");
  const [previewGraph, setPreviewGraph] = useState<unknown>(null);

  const connection = connData?.connection;
  const projectsEnabled = connData?.projects_enabled;

  const handleRegister = async () => {
    if (!whName.trim() || !whUrl.trim()) {
      toast.error("Name and webhook URL are required");
      return;
    }
    try {
      await registerWebhook.mutateAsync({
        name: whName.trim(),
        webhook_url: whUrl.trim(),
        n8n_workflow_id: whWorkflowId.trim() || null,
      });
      setWhName("");
      setWhUrl("");
      setWhWorkflowId("");
      toast.success("Workflow registered");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Register failed");
    }
  };

  const handlePreview = async (workflowId: string | null, webhookId: string) => {
    if (!workflowId) {
      toast.message("No workflow id — connect an API key to fetch the graph.");
      return;
    }
    const res = await graphMutation.mutateAsync({ workflow_id: workflowId, webhook_id: webhookId });
    setPreviewGraph(res.graph);
  };

  if (isLoading) {
    return <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />;
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2">
                <Workflow className="h-5 w-5 text-rose-500" /> n8n integration
              </CardTitle>
              <CardDescription>
                Hand off automations to your own n8n flows. We provision one project per tenant when
                Enterprise Projects are enabled, and fall back to webhook-mode otherwise.
              </CardDescription>
            </div>
            {connection?.is_connected ? (
              <Badge variant="default" className="gap-1">
                <CheckCircle2 className="h-3.5 w-3.5" /> Connected
              </Badge>
            ) : (
              <Badge variant="secondary">Not connected</Badge>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="text-muted-foreground">Mode:</span>
            <Badge variant="outline">{connection?.mode ?? (projectsEnabled ? "projects" : "webhook")}</Badge>
            {connection?.base_url && (
              <span className="text-muted-foreground">{connection.base_url}</span>
            )}
            {connection?.n8n_project_id && (
              <Badge variant="outline">project: {connection.n8n_project_id}</Badge>
            )}
          </div>
          <p className="text-xs text-muted-foreground">
            The n8n connection (API key, base URL, project) is managed by your platform admin under
            <strong> Platform → Integrations &amp; Data → n8n</strong>. Your tenant project is created
            automatically when you build your first automation; each automation becomes a folder in it.
            {!projectsEnabled && " Enterprise Projects aren't enabled yet — running in webhook-mode; register a workflow's webhook below to use it as an n8n step."}
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Registered workflows</CardTitle>
          <CardDescription>
            These appear in the builder's <strong>n8n</strong> step. Designed in n8n; triggered from here.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1.5fr_1fr_auto] sm:items-end">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input placeholder="Welcome series" value={whName} onChange={(e) => setWhName(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Webhook URL</Label>
              <Input
                placeholder="https://n8n-dev-webhooks.alliahealth.co/webhook/…"
                value={whUrl}
                onChange={(e) => setWhUrl(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Workflow ID (optional)</Label>
              <Input placeholder="for graph preview" value={whWorkflowId} onChange={(e) => setWhWorkflowId(e.target.value)} />
            </div>
            <Button onClick={handleRegister} disabled={registerWebhook.isPending}>
              <Plus className="h-4 w-4 mr-1" /> Register
            </Button>
          </div>

          <Separator />

          {webhooks.length === 0 ? (
            <p className="text-sm text-muted-foreground">No workflows registered yet.</p>
          ) : (
            <ul className="space-y-2">
              {webhooks.map((w) => (
                <li key={w.id} className="flex items-center justify-between rounded-md border px-3 py-2">
                  <div>
                    <div className="text-sm font-medium">{w.name}</div>
                    <div className="text-xs text-muted-foreground font-mono truncate max-w-md">{w.webhook_url}</div>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handlePreview(w.n8n_workflow_id, w.id)}
                    disabled={graphMutation.isPending}
                  >
                    Preview flow
                  </Button>
                </li>
              ))}
            </ul>
          )}

          {previewGraph != null && <N8nFlowViz graph={previewGraph} />}
        </CardContent>
      </Card>
    </div>
  );
}
