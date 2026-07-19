/** Right-side inspector that edits the selected node's config. */
import { useEffect, useRef, useState } from "react";
import { ChevronDown, ChevronRight, ExternalLink, Eye, Loader2, Play, Plus, Square, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { PlaceholderPicker } from "./PlaceholderPicker";
import { N8nFlowViz } from "./N8nFlowViz";
import { NODE_META } from "./node-meta";
import {
  COMMON_EVENT_NAMES,
  ORDER_TRIGGER_EVENTS,
  type PlaceholderGroup,
  RELATIVE_TIME_ANCHORS,
  SUBSCRIPTION_TRIGGER_EVENTS,
  TRIGGER_DEFINITIONS,
  payloadGroupsForTrigger,
} from "@/lib/comms-automations/catalog";
import {
  useCreateAutomationWorkflow,
  useN8nLinks,
  useN8nWebhooks,
  useN8nWorkflowGraph,
  useN8nWorkflows,
  useSetWorkflowActive,
} from "@/hooks/useCommsN8n";
import { Badge } from "@/components/ui/badge";
import { useCommsTemplates, useTriggerCatalog } from "@/hooks/useCommsAutomations";
import type {
  CommsChannel,
  CommsNode,
  CommsNodeConfig,
  TriggerConfig,
} from "@/lib/comms-automations/types";

/** Pick an existing template for this channel; selecting one drives the node by template_id. */
function TemplatePicker({
  channel,
  value,
  onChange,
}: {
  channel: CommsChannel;
  value?: string;
  onChange: (templateId: string | undefined) => void;
}) {
  const { data: templates = [] } = useCommsTemplates();
  const options = templates.filter((t) => t.channel === channel);
  return (
    <div className="space-y-1.5">
      <Label>Use a saved template (optional)</Label>
      <Select
        value={value ?? "__none__"}
        onValueChange={(v) => onChange(v === "__none__" ? undefined : v)}
      >
        <SelectTrigger><SelectValue placeholder="Write inline below" /></SelectTrigger>
        <SelectContent>
          <SelectItem value="__none__">— Write inline —</SelectItem>
          {options.map((t) => (
            <SelectItem key={t.id} value={t.id}>{t.name}</SelectItem>
          ))}
        </SelectContent>
      </Select>
      {value && (
        <p className="text-xs text-muted-foreground">
          Using the saved template. Its subject/body override the inline fields at send time.
        </p>
      )}
    </div>
  );
}

interface NodeInspectorProps {
  node: CommsNode;
  automationId: string;
  triggerConfig: TriggerConfig;
  onChangeConfig: (config: CommsNodeConfig) => void;
  onChangeTrigger: (trigger: TriggerConfig) => void;
  onDelete: () => void;
}

export function NodeInspector({
  node,
  automationId,
  triggerConfig,
  onChangeConfig,
  onChangeTrigger,
  onDelete,
}: NodeInspectorProps) {
  const meta = NODE_META[node.node_type];
  const config = node.config;

  const set = (patch: Partial<CommsNodeConfig>) => onChangeConfig({ ...config, ...patch });
  const setTrigger = (patch: Partial<TriggerConfig>) => {
    // Switching KIND resets the config: each kind has its own matching fields
    // (event_name / event_key / to_status / event_type / anchor…), and a plain
    // merge leaves the old kind's fields behind — a saved trigger then carries
    // e.g. `event_key: "order.created"` next to `event_name: "login"`, which
    // confuses debugging even where the dispatcher ignores the stale key.
    if (patch.kind && patch.kind !== triggerConfig.kind) {
      onChangeTrigger({ kind: patch.kind });
      return;
    }
    onChangeTrigger({ ...triggerConfig, ...patch });
  };

  const bodyRef = useRef<HTMLTextAreaElement>(null);
  const insertToken = (token: string) => {
    const el = bodyRef.current;
    if (!el) {
      set({ body: `${config.body ?? ""}${token}` });
      return;
    }
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? el.value.length;
    const next = `${el.value.slice(0, start)}${token}${el.value.slice(end)}`;
    set({ body: next });
  };

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b px-4 py-3">
        <div className="flex items-center gap-2">
          <meta.icon className={`h-4 w-4 ${meta.color}`} />
          <span className="font-medium">{meta.label}</span>
        </div>
        {node.node_type !== "trigger" && (
          <Button variant="ghost" size="icon" onClick={onDelete} aria-label="Delete node">
            <Trash2 className="h-4 w-4 text-destructive" />
          </Button>
        )}
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {node.node_type === "trigger" && (
          <TriggerEditor trigger={triggerConfig} onChange={setTrigger} />
        )}

        {node.node_type === "email" && (
          <>
            <TemplatePicker
              channel="email"
              value={config.template_id}
              onChange={(id) => set({ template_id: id })}
            />
            <div className="space-y-1.5">
              <Label>Subject</Label>
              <Input
                placeholder="You still have work to do"
                value={String(config.subject ?? "")}
                onChange={(e) => set({ subject: e.target.value })}
              />
            </div>
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label>Body (HTML)</Label>
                <PlaceholderPicker onInsert={insertToken} />
              </div>
              <Textarea
                ref={bodyRef}
                rows={10}
                className="font-mono text-sm"
                placeholder="<p>Hi {{patient.first_name}}, your plan renews on {{subscription.renewal_date}}.</p>"
                value={String(config.body ?? "")}
                onChange={(e) => set({ body: e.target.value })}
              />
              <p className="text-xs text-muted-foreground">
                Sends to the enrolled patient's email via the tenant's Resend integration.
              </p>
            </div>
          </>
        )}

        {node.node_type === "sms" && (
          <div className="space-y-3">
            <TemplatePicker
              channel="sms"
              value={config.template_id}
              onChange={(id) => set({ template_id: id })}
            />
            <div className="flex items-center justify-between">
              <Label>Message</Label>
              <PlaceholderPicker onInsert={insertToken} />
            </div>
            <Textarea
              ref={bodyRef}
              rows={5}
              placeholder="Hi {{patient.first_name}} — your order {{order.order_number}} has shipped!"
              value={String(config.body ?? "")}
              onChange={(e) => set({ body: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">
              Sends via the tenant's Twilio integration to the patient's phone.
            </p>
          </div>
        )}

        {node.node_type === "delay" && (
          <div className="flex items-end gap-2">
            <div className="space-y-1.5 flex-1">
              <Label>Wait</Label>
              <Input
                type="number"
                min={1}
                value={Number(config.amount ?? 1)}
                onChange={(e) => set({ amount: Number(e.target.value) })}
              />
            </div>
            <div className="space-y-1.5 flex-1">
              <Label>Unit</Label>
              <Select
                value={String(config.unit ?? "days")}
                onValueChange={(v) => set({ unit: v as CommsNodeConfig["unit"] })}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="minutes">Minutes</SelectItem>
                  <SelectItem value="hours">Hours</SelectItem>
                  <SelectItem value="days">Days</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        )}

        {node.node_type === "wait_until" && (
          <div className="space-y-1.5">
            <Label>Wait until</Label>
            <Input
              type="datetime-local"
              value={String(config.until ?? "")}
              onChange={(e) => set({ until: e.target.value })}
            />
          </div>
        )}

        {node.node_type === "branch" && <BranchEditor config={config} onChange={set} />}

        {node.node_type === "multi_split" && (
          <div className="space-y-1.5">
            <Label>Split by context field</Label>
            <Input
              placeholder="e.g. subscription.status"
              value={String(config.cohort_field ?? "")}
              onChange={(e) => set({ cohort_field: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">
              Each outgoing branch is labelled with a value; the patient follows the matching branch.
            </p>
          </div>
        )}

        {node.node_type === "n8n" && (
          <N8nNodeEditor config={config} onChange={set} automationId={automationId} />
        )}

        {node.node_type === "exit" && (
          <p className="text-sm text-muted-foreground">
            This node ends the journey for the enrolled patient.
          </p>
        )}
      </div>
    </div>
  );
}

function TriggerEditor({
  trigger,
  onChange,
}: {
  trigger: TriggerConfig;
  onChange: (patch: Partial<TriggerConfig>) => void;
}) {
  // Analytics event names stay data-driven (the tenant's analytics_event_types).
  const { data: catalog } = useTriggerCatalog();
  const eventNames = catalog?.event_names?.length
    ? catalog.event_names.map((e) => ({ key: e.key, label: e.key }))
    : COMMON_EVENT_NAMES.map((e) => ({ key: e, label: e }));

  // Order/subscription triggers use the CANONICAL event catalog — the same named
  // events Outbound Webhooks expose, so trigger↔webhook parity is structural.
  // The tenant's raw order_statuses stay available under "Advanced" for the rare
  // case someone needs an internal status that has no public event.
  const rawOrderStatuses = catalog?.order_statuses ?? [];
  const [showAdvancedOrder, setShowAdvancedOrder] = useState(!!trigger.to_status);

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label>Start automation when…</Label>
        <Select value={trigger.kind} onValueChange={(v) => onChange({ kind: v as TriggerConfig["kind"] })}>
          <SelectTrigger><SelectValue placeholder="Choose a trigger" /></SelectTrigger>
          <SelectContent>
            {TRIGGER_DEFINITIONS.map((t) => (
              <SelectItem key={t.kind} value={t.kind}>{t.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p className="text-xs text-muted-foreground">
          {TRIGGER_DEFINITIONS.find((t) => t.kind === trigger.kind)?.description}
        </p>
      </div>

      {trigger.kind === "event" && (
        <div className="space-y-1.5">
          <Label>Event name</Label>
          <Select value={trigger.event_name ?? ""} onValueChange={(v) => onChange({ event_name: v })}>
            <SelectTrigger><SelectValue placeholder="Select event" /></SelectTrigger>
            <SelectContent>
              {eventNames.map((e) => (
                <SelectItem key={e.key} value={e.key}>{e.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}

      {trigger.kind === "subscription" && (
        <div className="space-y-1.5">
          <Label>Subscription event</Label>
          <Select
            value={trigger.event_key ?? ""}
            onValueChange={(v) => onChange({ event_key: v, event_type: undefined })}
          >
            <SelectTrigger><SelectValue placeholder="Select event" /></SelectTrigger>
            <SelectContent>
              {SUBSCRIPTION_TRIGGER_EVENTS.map((e) => (
                <SelectItem key={e.key} value={e.key}>{e.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            {SUBSCRIPTION_TRIGGER_EVENTS.find((e) => e.key === trigger.event_key)?.description ??
              "The same events your outbound webhooks can subscribe to."}
          </p>
        </div>
      )}

      {trigger.kind === "order" && (
        <div className="space-y-1.5">
          <Label>Order event</Label>
          <Select
            value={trigger.event_key ?? ""}
            onValueChange={(v) => onChange({ event_key: v, to_status: undefined })}
          >
            <SelectTrigger><SelectValue placeholder="Select event" /></SelectTrigger>
            <SelectContent>
              {ORDER_TRIGGER_EVENTS.map((e) => (
                <SelectItem key={e.key} value={e.key}>{e.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            {ORDER_TRIGGER_EVENTS.find((e) => e.key === trigger.event_key)?.description ??
              "The same events your outbound webhooks can subscribe to."}
          </p>

          {/* Escape hatch: fire on a raw internal status that has no public event. */}
          {!showAdvancedOrder ? (
            <button
              type="button"
              className="text-xs text-muted-foreground underline hover:text-foreground"
              onClick={() => setShowAdvancedOrder(true)}
            >
              Advanced: use a raw order status instead
            </button>
          ) : (
            <div className="space-y-1.5 rounded-md border border-dashed p-2.5">
              <Label className="text-xs">Raw order status (advanced)</Label>
              <Select
                value={trigger.to_status ?? ""}
                onValueChange={(v) => onChange({ to_status: v, event_key: undefined })}
              >
                <SelectTrigger><SelectValue placeholder="Select a raw status_key" /></SelectTrigger>
                <SelectContent>
                  {rawOrderStatuses.map((s) => (
                    <SelectItem key={s.key} value={s.key}>{s.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                Fires on an internal status directly. Most automations should use an
                event above — those match what your webhooks receive.
              </p>
            </div>
          )}
        </div>
      )}

      {trigger.kind === "relative_time" && (
        <div className="space-y-3">
          <div className="flex items-end gap-2">
            <div className="space-y-1.5 w-24">
              <Label>Days</Label>
              <Input
                type="number"
                min={0}
                value={Number(trigger.offset_days ?? 0)}
                onChange={(e) => onChange({ offset_days: Number(e.target.value) })}
              />
            </div>
            <div className="space-y-1.5 flex-1">
              <Label>Direction</Label>
              <Select
                value={trigger.direction ?? "before"}
                onValueChange={(v) => onChange({ direction: v as "before" | "after" })}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="before">before</SelectItem>
                  <SelectItem value="after">after</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Anchor</Label>
            <Select
              value={trigger.anchor ?? "renewal"}
              onValueChange={(v) => onChange({ anchor: v as TriggerConfig["anchor"] })}
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {RELATIVE_TIME_ANCHORS.map((a) => (
                  <SelectItem key={a.key} value={a.key}>{a.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              Checked every minute — e.g. "3 days before renewal" enrolls matching subscriptions
              once their renewal date falls in range.
            </p>
          </div>
        </div>
      )}

      {trigger.kind && <TriggerPayloadReference kind={trigger.kind} />}
    </div>
  );
}

/**
 * The automation counterpart to the webhooks payload reference: for the chosen
 * trigger kind, list the exact context blocks the dispatcher resolves onto the
 * enrollment — what an n8n node receives under `context` and what
 * {{namespace.field}} placeholders can read in email/SMS templates. Grounded
 * in resolveContext/enrichContext via TRIGGER_CONTEXT_NAMESPACES (THE RULE:
 * only fields actually delivered are listed).
 */
function TriggerPayloadReference({ kind }: { kind: TriggerConfig["kind"] }) {
  const [open, setOpen] = useState(false);
  const { always, conditional } = payloadGroupsForTrigger(kind);
  const fieldCount = [...always, ...conditional].reduce((n, g) => n + g.fields.length, 0);

  const renderGroup = (g: PlaceholderGroup, note?: string) => (
    <div key={g.namespace}>
      <p className="mt-2 text-[11px] font-medium">
        <span className="font-mono">context.{g.namespace}</span>
        {note && <span className="ml-1 font-normal text-muted-foreground">({note})</span>}
      </p>
      <ul className="mt-0.5 space-y-0.5">
        {g.fields.map((f) => (
          <li key={f.key} className="flex flex-wrap items-baseline gap-1.5 text-[11px]">
            <span className="font-mono text-foreground">{f.key}</span>
            <span className="text-muted-foreground">— {f.label}, e.g. </span>
            <span className="font-mono text-muted-foreground">{f.sample}</span>
          </li>
        ))}
      </ul>
    </div>
  );

  return (
    <div className="rounded-md border">
      <button
        type="button"
        className="flex w-full items-center justify-between gap-2 p-2.5 text-left"
        onClick={() => setOpen((v) => !v)}
      >
        <span className="text-xs font-medium">Payload this trigger delivers</span>
        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
          {fieldCount} fields
          {open ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
        </span>
      </button>
      {open && (
        <div className="border-t bg-muted/40 px-3 py-2">
          <p className="text-[11px] text-muted-foreground">
            Every enrollment carries this context: it is the JSON an n8n node receives under{" "}
            <span className="font-mono">context</span>, and the same fields power{" "}
            <span className="font-mono">{"{{namespace.field}}"}</span> placeholders in email/SMS
            templates.
          </p>
          {always.map((g) => renderGroup(g))}
          {conditional.map((g) => renderGroup(g, "when the trigger carries this entity"))}
        </div>
      )}
    </div>
  );
}

function BranchEditor({
  config,
  onChange,
}: {
  config: CommsNodeConfig;
  onChange: (patch: Partial<CommsNodeConfig>) => void;
}) {
  const cond = config.condition ?? { field: "", op: "eq", value: "" };
  const setCond = (patch: Partial<typeof cond>) => onChange({ condition: { ...cond, ...patch } });
  return (
    <div className="space-y-3">
      <p className="text-sm text-muted-foreground">
        Patients matching the condition follow the <strong>True</strong> branch; others follow{" "}
        <strong>False</strong>.
      </p>
      <div className="space-y-1.5">
        <Label>Field</Label>
        <Input
          placeholder="subscription.status"
          value={cond.field}
          onChange={(e) => setCond({ field: e.target.value })}
        />
      </div>
      <div className="space-y-1.5">
        <Label>Operator</Label>
        <Select value={cond.op} onValueChange={(v) => setCond({ op: v })}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            {["eq", "neq", "gt", "gte", "lt", "lte", "contains", "exists", "not_exists"].map((op) => (
              <SelectItem key={op} value={op}>{op}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {!["exists", "not_exists"].includes(cond.op) && (
        <div className="space-y-1.5">
          <Label>Value</Label>
          <Input
            placeholder="active"
            value={String(cond.value ?? "")}
            onChange={(e) => setCond({ value: e.target.value })}
          />
        </div>
      )}
    </div>
  );
}

/**
 * The TEST url for a stored production webhook url. Mirrors the server rule in
 * supabase/functions/_shared/comms-n8n.ts (toTestWebhookUrl): in our queue-mode
 * n8n, production webhooks are served by the dedicated webhook host
 * (n8n-<env>-webhooks.…) while TEST webhooks only exist on the MAIN/editor host
 * (n8n-<env>.…) — hence the `-webhooks` strip. Display-only; the executor
 * computes its own target.
 */
function testUrlForWebhook(webhookUrl: string, webhookPath?: string | null): string {
  try {
    const u = new URL(webhookUrl);
    const editorHost = u.host.replace("-webhooks.", ".");
    const path = webhookPath ?? webhookUrl.split("/webhook/")[1] ?? "";
    return `${u.protocol}//${editorHost}/webhook-test/${path}`;
  } catch {
    return webhookUrl.replace("/webhook/", "/webhook-test/");
  }
}

function N8nNodeEditor({
  config,
  onChange,
  automationId,
}: {
  config: CommsNodeConfig;
  onChange: (patch: Partial<CommsNodeConfig>) => void;
  automationId: string;
}) {
  const { data: webhooks = [], isLoading } = useN8nWebhooks();
  const { data: liveWorkflows = [] } = useN8nWorkflows();
  const graphMutation = useN8nWorkflowGraph();
  const createWorkflow = useCreateAutomationWorkflow();
  const setActive = useSetWorkflowActive();
  const linksMut = useN8nLinks();
  const [graph, setGraph] = useState<unknown>(null);
  const [editorUrl, setEditorUrl] = useState<string | null>(null);
  const [folderUrl, setFolderUrl] = useState<string | null>(null);

  // Resolve editor links for this automation (workflow + folder) on mount/select.
  useEffect(() => {
    let cancelled = false;
    if (automationId) {
      linksMut.mutateAsync(automationId).then((r) => {
        if (cancelled) return;
        if (r.editor_url) setEditorUrl(r.editor_url);
        if (r.folder_url) setFolderUrl(r.folder_url);
      }).catch(() => { /* non-fatal */ });
    }
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [automationId, config.webhook_id]);

  const selected = webhooks.find((w) => w.id === config.webhook_id);
  // Live active/inactive state of the selected workflow (if we can match it by id).
  const liveMatch = selected?.n8n_workflow_id
    ? liveWorkflows.find((w) => w.id === selected.n8n_workflow_id)
    : undefined;

  const handleAutoCreate = async () => {
    try {
      const res = await createWorkflow.mutateAsync(automationId);
      // Self-wire the node to the freshly created/registered workflow.
      onChange({ webhook_id: res.webhook.id });
      setEditorUrl(res.editor_url);
      if (res.active) {
        toast.success(
          res.reused
            ? "Linked existing n8n workflow (active)"
            : "n8n workflow created, activated & connected",
        );
      } else {
        // A workflow that isn't active has a DEAD webhook — never let that pass silently.
        toast.warning("Workflow created but could NOT be activated — its webhook will 404.", {
          description: res.activation_error ?? "Use the Activate button to retry.",
        });
      }
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Could not create workflow");
    }
  };

  const handleToggleActive = async () => {
    if (!selected?.n8n_workflow_id) return;
    try {
      const res = await setActive.mutateAsync({
        workflow_id: selected.n8n_workflow_id,
        active: !(liveMatch?.active ?? false),
      });
      toast.success(res.active ? "Workflow activated" : "Workflow deactivated");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Could not change workflow state");
    }
  };

  const editHref = editorUrl;

  const handlePreview = async () => {
    if (!selected?.n8n_workflow_id) {
      // Fall back to cached graph on the webhook row.
      setGraph(selected?.graph_cache ?? null);
      return;
    }
    const res = await graphMutation.mutateAsync({
      workflow_id: selected.n8n_workflow_id,
      webhook_id: selected.id,
    });
    setGraph(res.graph);
  };

  return (
    <div className="space-y-3">
      <div className="space-y-1.5">
        <Label>n8n workflow</Label>
        <Select
          value={config.webhook_id ?? ""}
          onValueChange={(v) => {
            onChange({ webhook_id: v });
            setGraph(null);
          }}
        >
          <SelectTrigger>
            <SelectValue placeholder={isLoading ? "Loading…" : "Select a registered workflow"} />
          </SelectTrigger>
          <SelectContent>
            {webhooks.map((w) => (
              <SelectItem key={w.id} value={w.id}>{w.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p className="text-xs text-muted-foreground">
          Pick an existing workflow, or auto-create one wired to this automation.
        </p>
      </div>

      {/* No workflow yet -> one-click create + connect (placed in this automation's folder). */}
      {!selected && (
        <Button onClick={handleAutoCreate} disabled={createWorkflow.isPending}>
          {createWorkflow.isPending
            ? <Loader2 className="h-4 w-4 mr-1 animate-spin" />
            : <Plus className="h-4 w-4 mr-1" />}
          Create &amp; connect n8n workflow
        </Button>
      )}

      {selected && (
        <>
          <Separator />
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="text-muted-foreground">Workflow status:</span>
            <Badge variant={liveMatch?.active ? "default" : "destructive"}>
              {liveMatch?.active ? "Active" : "Inactive"}
            </Badge>
            {selected.n8n_workflow_id && (
              <Button
                variant={liveMatch?.active ? "outline" : "default"}
                size="sm"
                onClick={handleToggleActive}
                disabled={setActive.isPending}
              >
                {setActive.isPending
                  ? <Loader2 className="h-3.5 w-3.5 mr-1 animate-spin" />
                  : liveMatch?.active ? <Square className="h-3.5 w-3.5 mr-1" /> : <Play className="h-3.5 w-3.5 mr-1" />}
                {liveMatch?.active ? "Deactivate" : "Activate"}
              </Button>
            )}
          </div>

          {/* An inactive workflow's production webhook is not registered with n8n's
              router — it 404s. Without this the UI showed a quiet grey "Inactive"
              badge and the step failed silently at runtime. */}
          {!liveMatch?.active && (
            <div className="rounded-md border border-destructive/40 bg-destructive/10 p-2.5 text-xs">
              <p className="font-medium text-destructive">This workflow is not running in n8n.</p>
              <p className="mt-1 text-muted-foreground">
                Its webhook URL only exists while the workflow is active — until you
                activate it, this step fails with{" "}
                <code className="font-mono">n8n_status_404</code>. Click{" "}
                <strong>Activate</strong> above.
              </p>
            </div>
          )}

          {/* Authoring aid: n8n's test URL streams the payload onto the editor
              canvas, but only while "Listen for test event" is armed, and only for
              a single call — so it can never serve real traffic. */}
          <div className="rounded-md border p-2.5">
            <div className="flex items-center justify-between gap-3">
              <Label htmlFor="n8n-test-mode" className="text-xs font-medium">
                Send to n8n TEST url (for building)
              </Label>
              <Switch
                id="n8n-test-mode"
                checked={config.test_mode === true}
                onCheckedChange={(v) => onChange({ test_mode: v })}
              />
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
              {config.test_mode
                ? "Click “Listen for test event” in n8n first, then fire a test event here to watch the data land on the canvas. It accepts ONE call — re-arm n8n for each test. Turn this off before going live."
                : "Production: real triggers post to the live webhook URL."}
            </p>
            {/* Show exactly where each mode posts — the two URLs live on
                DIFFERENT hosts (test = editor instance, production = webhook
                processors), so guessing one from the other misleads. */}
            <div className="mt-2 space-y-1 text-xs">
              <div className={config.test_mode ? "font-medium" : "text-muted-foreground"}>
                <span className="mr-1">Test URL:</span>
                <code className="font-mono break-all">
                  {testUrlForWebhook(selected.webhook_url, selected.webhook_path)}
                </code>
              </div>
              <div className={config.test_mode ? "text-muted-foreground" : "font-medium"}>
                <span className="mr-1">Production URL:</span>
                <code className="font-mono break-all">{selected.webhook_url}</code>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button variant="outline" size="sm" onClick={handlePreview} disabled={graphMutation.isPending}>
              <Eye className="h-3.5 w-3.5 mr-1" />
              {graphMutation.isPending ? "Loading…" : "Preview flow"}
            </Button>
            {editHref && (
              <a href={editHref} target="_blank" rel="noreferrer"
                className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                Edit in n8n <ExternalLink className="h-3 w-3" />
              </a>
            )}
            {folderUrl && (
              <a href={folderUrl} target="_blank" rel="noreferrer"
                className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                Open folder <ExternalLink className="h-3 w-3" />
              </a>
            )}
            {selected.webhook_url && (
              <a href={selected.webhook_url} target="_blank" rel="noreferrer"
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:underline">
                Webhook <ExternalLink className="h-3 w-3" />
              </a>
            )}
          </div>
          {graph != null && <N8nFlowViz graph={graph} />}
        </>
      )}
    </div>
  );
}
