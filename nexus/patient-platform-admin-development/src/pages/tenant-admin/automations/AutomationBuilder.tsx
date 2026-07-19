/**
 * Communications Automation builder — canvas + palette + inspector.
 *
 * Holds the working graph (nodes + edges) in local state, seeded from the loaded
 * automation (or a fresh trigger→exit spine). Insert/select/delete mutate local
 * state; Save persists via comms-automation-admin.save_graph; Activate flips the
 * automation status.
 */
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, Check, Loader2, Pencil, Save, Workflow, Zap } from "lucide-react";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "sonner";
import { AutomationCanvas } from "@/components/features/comms-automations/AutomationCanvas";
import { NodePalette } from "@/components/features/comms-automations/NodePalette";
import { NodeInspector } from "@/components/features/comms-automations/NodeInspector";
import { RunActivityPanel } from "@/components/features/comms-automations/RunActivityPanel";
import { Input } from "@/components/ui/input";
import { useN8nConnection, useRenameAutomationFolder } from "@/hooks/useCommsN8n";
import {
  useCommsAutomation,
  useSaveGraph,
  useTestTrigger,
  useUpdateAutomation,
} from "@/hooks/useCommsAutomations";
import type {
  CommsAutomationStatus,
  CommsEdge,
  CommsNode,
  CommsNodeConfig,
  CommsNodeType,
  TriggerConfig,
} from "@/lib/comms-automations/types";

const uuid = () => crypto.randomUUID();

/** Seed a brand-new automation with a trigger → exit spine. */
function seedGraph(): { nodes: CommsNode[]; edges: CommsEdge[] } {
  const trigger: CommsNode = {
    id: uuid(),
    node_type: "trigger",
    config: {},
    position: { x: 0, y: 0 },
  };
  const exit: CommsNode = {
    id: uuid(),
    node_type: "exit",
    config: {},
    position: { x: 0, y: 200 },
  };
  return {
    nodes: [trigger, exit],
    edges: [
      { source_node_id: trigger.id, target_node_id: exit.id, branch_label: null, sort_order: 0 },
    ],
  };
}

export default function AutomationBuilder() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data, isLoading } = useCommsAutomation(id);
  const { data: n8nConn } = useN8nConnection();
  const saveGraph = useSaveGraph();
  const testTrigger = useTestTrigger();
  const updateAutomation = useUpdateAutomation();

  const [nodes, setNodes] = useState<CommsNode[]>([]);
  const [edges, setEdges] = useState<CommsEdge[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [dirty, setDirty] = useState(false);
  const [view, setView] = useState<"build" | "activity">("build");
  const renameFolder = useRenameAutomationFolder();
  const [editingName, setEditingName] = useState(false);
  const [nameDraft, setNameDraft] = useState("");

  // Initialise local graph from the server (or seed a fresh one).
  useEffect(() => {
    if (!data) return;
    if (data.nodes && data.nodes.length > 0) {
      setNodes(data.nodes);
      setEdges(data.edges ?? []);
      setSelectedId(data.nodes.find((n) => n.node_type === "trigger")?.id ?? data.nodes[0].id);
    } else {
      const seeded = seedGraph();
      setNodes(seeded.nodes);
      setEdges(seeded.edges);
      setSelectedId(seeded.nodes[0].id);
      setDirty(true);
    }
  }, [data]);

  const automation = data?.automation;
  const triggerNode = nodes.find((n) => n.node_type === "trigger");
  const selectedNode = nodes.find((n) => n.id === selectedId) ?? null;

  const triggerConfig: TriggerConfig = useMemo(
    () => (automation?.trigger_config ?? { kind: "event" }) as TriggerConfig,
    [automation],
  );
  const [localTrigger, setLocalTrigger] = useState<TriggerConfig>(triggerConfig);
  useEffect(() => setLocalTrigger(triggerConfig), [triggerConfig]);

  // --- graph editing ---
  const updateNodeConfig = (nodeId: string, config: CommsNodeConfig) => {
    setNodes((prev) => prev.map((n) => (n.id === nodeId ? { ...n, config } : n)));
    setDirty(true);
  };

  const insertNodeAfter = (sourceNodeId: string, type: CommsNodeType, branchLabel?: string | null) => {
    const newNode: CommsNode = { id: uuid(), node_type: type, config: {}, position: { x: 0, y: 0 } };
    setNodes((prev) => [...prev, newNode]);
    setEdges((prev) => {
      // Find the edge we're inserting into (source -> X on this branch label).
      const existing = prev.find(
        (e) => e.source_node_id === sourceNodeId && (branchLabel == null || e.branch_label === branchLabel),
      );
      const rest = prev.filter((e) => e !== existing);
      const newEdges: CommsEdge[] = [
        ...rest,
        {
          source_node_id: sourceNodeId,
          target_node_id: newNode.id,
          branch_label: branchLabel ?? null,
          sort_order: 0,
        },
      ];
      // Re-link the new node downstream to whatever the source used to point at.
      const downstream = existing?.target_node_id ?? null;
      if (type === "branch") {
        // A branch needs two labelled outgoing edges; "true" continues the spine,
        // "false" is left open for the user to add a step on the canvas.
        if (downstream) {
          newEdges.push({
            source_node_id: newNode.id,
            target_node_id: downstream,
            branch_label: "true",
            sort_order: 0,
          });
        }
      } else if (downstream) {
        newEdges.push({
          source_node_id: newNode.id,
          target_node_id: downstream,
          branch_label: null,
          sort_order: 0,
        });
      }
      return newEdges;
    });
    setSelectedId(newNode.id);
    setDirty(true);
  };

  const deleteNode = (nodeId: string) => {
    const node = nodes.find((n) => n.id === nodeId);
    if (!node || node.node_type === "trigger") return;
    // Re-link: source(s) of this node point to this node's first target.
    const incoming = edges.filter((e) => e.target_node_id === nodeId);
    const outgoing = edges.filter((e) => e.source_node_id === nodeId);
    const firstTarget = outgoing[0]?.target_node_id ?? null;

    setEdges((prev) => {
      let next = prev.filter((e) => e.source_node_id !== nodeId && e.target_node_id !== nodeId);
      if (firstTarget) {
        next = [
          ...next,
          ...incoming.map((e) => ({
            source_node_id: e.source_node_id,
            target_node_id: firstTarget,
            branch_label: e.branch_label,
            sort_order: e.sort_order,
          })),
        ];
      }
      return next;
    });
    setNodes((prev) => prev.filter((n) => n.id !== nodeId));
    setSelectedId(triggerNode?.id ?? null);
    setDirty(true);
  };

  // The canvas "+" opens a node-type menu and passes the chosen type here.
  const handleAddAfter = (sourceNodeId: string, type: CommsNodeType, branchLabel?: string | null) =>
    insertNodeAfter(sourceNodeId, type, branchLabel);

  const handlePaletteAdd = (type: CommsNodeType) => {
    // Add after the currently-selected node (or after the trigger).
    const anchor = selectedNode && selectedNode.node_type !== "exit"
      ? selectedNode.id
      : triggerNode?.id;
    if (!anchor) return;
    insertNodeAfter(anchor, type);
  };

  // --- persistence ---
  const handleSave = async () => {
    if (!id) return;
    try {
      await Promise.all([
        saveGraph.mutateAsync({ automation_id: id, nodes, edges }),
        updateAutomation.mutateAsync({ automation_id: id, trigger_config: localTrigger }),
      ]);
      setDirty(false);
      toast.success("Automation saved");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Save failed");
    }
  };

  const handleTestTrigger = async () => {
    if (!id) return;
    try {
      // The test event runs against the PERSISTED graph — an unsaved canvas
      // (e.g. an n8n node connected but not yet saved) makes the walker step
      // trigger→exit and report "exited" with nothing sent, which reads as a
      // broken webhook. Save first so the test exercises what's on screen.
      if (dirty) {
        await Promise.all([
          saveGraph.mutateAsync({ automation_id: id, nodes, edges }),
          updateAutomation.mutateAsync({ automation_id: id, trigger_config: localTrigger }),
        ]);
        setDirty(false);
      }
      const res = await testTrigger.mutateAsync(id);
      const enrolled = (res.result as { enrolled?: number } | undefined)?.enrolled ?? 0;
      toast.success(
        enrolled > 0
          ? `Test event fired — ${enrolled} enrollment(s). Check Activity.`
          : "Test event fired, but nothing enrolled (check the trigger matches).",
      );
      setView("activity");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Test event failed");
    }
  };

  const startRename = () => {
    setNameDraft(automation?.name ?? "");
    setEditingName(true);
  };
  const saveRename = async () => {
    const next = nameDraft.trim();
    setEditingName(false);
    if (!id || !next || next === automation?.name) return;
    try {
      await updateAutomation.mutateAsync({ automation_id: id, name: next });
      // Keep the n8n folder name in sync (best-effort; non-fatal if no folder yet).
      renameFolder.mutateAsync({ automation_id: id, name: next }).catch(() => {});
      toast.success("Automation renamed");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Rename failed");
    }
  };

  /** Validate the graph before activation; returns a list of human-readable issues. */
  const validateForActivation = (): string[] => {
    const issues: string[] = [];
    if (!localTrigger?.kind) issues.push("Pick a trigger.");
    if (localTrigger?.kind === "event" && !localTrigger.event_name) issues.push("Choose an event name for the trigger.");
    // Order/subscription triggers are configured with a named event_key; the raw
    // to_status / event_type are the Advanced escape hatch (and what automations
    // saved before named events carry). Either satisfies the trigger.
    if (localTrigger?.kind === "subscription" && !localTrigger.event_key && !localTrigger.event_type) {
      issues.push("Choose a subscription event for the trigger.");
    }
    if (localTrigger?.kind === "order" && !localTrigger.event_key && !localTrigger.to_status) {
      issues.push("Choose an order event for the trigger.");
    }

    // An automation needs at least one ACTION step: Email, SMS, or an n8n flow.
    const messageNodes = nodes.filter((n) => n.node_type === "email" || n.node_type === "sms");
    const n8nNodes = nodes.filter((n) => n.node_type === "n8n");
    if (messageNodes.length === 0 && n8nNodes.length === 0) {
      issues.push("Add at least one action step (Email, SMS, or an n8n flow).");
    }
    for (const n of messageNodes) {
      const hasContent = n.config.template_id || n.config.body || n.config.html;
      if (!hasContent) issues.push(`A ${n.node_type.toUpperCase()} step has no message (pick a template or write a body).`);
    }
    for (const n of n8nNodes) {
      const hasWorkflow = n.config.webhook_id || n.config.webhook_url;
      if (!hasWorkflow) issues.push("An n8n step has no workflow selected.");
    }
    for (const n of nodes.filter((n) => n.node_type === "branch")) {
      const cond = n.config.condition as { field?: string } | undefined;
      if (!cond?.field) issues.push("A branch has no condition field set.");
    }
    return issues;
  };

  const handleStatusChange = async (status: CommsAutomationStatus) => {
    if (!id) return;
    if (status === "active") {
      const issues = validateForActivation();
      if (issues.length > 0) {
        toast.error(`Can't activate yet: ${issues[0]}`, {
          description: issues.length > 1 ? `+${issues.length - 1} more issue(s) to fix.` : undefined,
        });
        return;
      }
      // Persist graph first so we never activate a stale draft.
      await saveGraph.mutateAsync({ automation_id: id, nodes, edges });
      await updateAutomation.mutateAsync({ automation_id: id, trigger_config: localTrigger });
      setDirty(false);
    }
    try {
      await updateAutomation.mutateAsync({ automation_id: id, status });
      toast.success(`Automation ${status}`);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to update status");
    }
  };

  if (isLoading || !automation) {
    return (
      <AdminLayout variant="tenant">
        <div className="flex h-64 items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout variant="tenant">
      <div className="flex h-[calc(100vh-7rem)] flex-col">
        {/* Header bar */}
        <div className="flex items-center justify-between border-b pb-3">
          <div className="flex items-center gap-3">
            <Button variant="ghost" size="icon" onClick={() => navigate("/tenant-admin/automations")}>
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <div>
              {editingName ? (
                <div className="flex items-center gap-1">
                  <Input
                    autoFocus
                    className="h-8 w-64"
                    value={nameDraft}
                    onChange={(e) => setNameDraft(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === "Enter") saveRename();
                      if (e.key === "Escape") setEditingName(false);
                    }}
                    onBlur={saveRename}
                  />
                  <Button variant="ghost" size="icon" className="h-7 w-7" onClick={saveRename}>
                    <Check className="h-4 w-4" />
                  </Button>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={startRename}
                  className="group flex items-center gap-1.5 text-left"
                  title="Rename automation (also renames its n8n folder)"
                >
                  <h1 className="text-lg font-semibold">{automation.name}</h1>
                  <Pencil className="h-3.5 w-3.5 text-muted-foreground opacity-0 group-hover:opacity-100" />
                </button>
              )}
              <p className="text-xs text-muted-foreground">{automation.description}</p>
            </div>
            {dirty && <Badge variant="outline">Unsaved changes</Badge>}
            {(automation.n8n_folder_name || n8nConn?.connection?.n8n_project_id || n8nConn?.connection?.mode === "webhook") && (
              <Badge variant="outline" className="gap-1 font-normal">
                <Workflow className="h-3 w-3 text-rose-500" />
                {n8nConn?.connection?.n8n_project_id ? "n8n project" : "n8n webhook-mode"}
                {automation.n8n_folder_name ? ` · ${automation.n8n_folder_name}` : ""}
              </Badge>
            )}
          </div>
          <div className="flex items-center gap-2">
            <div className="flex rounded-md border p-0.5">
              <Button
                variant={view === "build" ? "secondary" : "ghost"}
                size="sm"
                className="h-7"
                onClick={() => setView("build")}
              >
                Build
              </Button>
              <Button
                variant={view === "activity" ? "secondary" : "ghost"}
                size="sm"
                className="h-7"
                onClick={() => setView("activity")}
              >
                Activity
              </Button>
            </div>
            <Select value={automation.status} onValueChange={(v) => handleStatusChange(v as CommsAutomationStatus)}>
              <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">Draft</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="paused">Paused</SelectItem>
                <SelectItem value="archived">Archived</SelectItem>
              </SelectContent>
            </Select>
            {automation.status === "active" && (
              <Button variant="outline" onClick={handleTestTrigger} disabled={testTrigger.isPending}>
                {testTrigger.isPending ? <Loader2 className="h-4 w-4 mr-1 animate-spin" /> : <Zap className="h-4 w-4 mr-1" />}
                Send test event
              </Button>
            )}
            <Button onClick={handleSave} disabled={saveGraph.isPending || !dirty}>
              {saveGraph.isPending ? <Loader2 className="h-4 w-4 mr-1 animate-spin" /> : <Save className="h-4 w-4 mr-1" />}
              Save
            </Button>
          </div>
        </div>

        {/* Body: Activity view OR the builder grid (palette | canvas | inspector) */}
        {view === "activity" ? (
          <div className="flex-1 overflow-y-auto p-6">
            <RunActivityPanel automationId={automation.id} />
          </div>
        ) : (
        <div className="grid flex-1 grid-cols-[15rem_1fr_20rem] overflow-hidden">
          <aside className="overflow-y-auto border-r">
            <NodePalette onAdd={handlePaletteAdd} />
          </aside>

          <main className="overflow-hidden bg-muted/20">
            <AutomationCanvas
              nodes={nodes}
              edges={edges}
              selectedNodeId={selectedId}
              enrolledCount={automation.enrolled_count}
              onSelectNode={setSelectedId}
              onAddAfter={handleAddAfter}
            />
          </main>

          <aside className="overflow-hidden border-l">
            {selectedNode ? (
              <NodeInspector
                node={selectedNode}
                automationId={automation.id}
                triggerConfig={localTrigger}
                onChangeConfig={(config) => updateNodeConfig(selectedNode.id, config)}
                onChangeTrigger={(t) => {
                  setLocalTrigger(t);
                  setDirty(true);
                }}
                onDelete={() => deleteNode(selectedNode.id)}
              />
            ) : (
              <div className="flex h-full items-center justify-center p-6 text-center text-sm text-muted-foreground">
                Select a step to configure it.
              </div>
            )}
          </aside>
        </div>
        )}
      </div>
    </AdminLayout>
  );
}
