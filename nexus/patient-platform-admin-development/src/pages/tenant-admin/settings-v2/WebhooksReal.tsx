/**
 * Outbound Webhooks — REAL, working feature.
 *
 * Tenants register endpoints that receive selected platform events. Two distinct,
 * non-mixable types (lifecycle vs product_usage): a webhook's selectable events
 * are scoped to its type. Persists to tenant_outbound_webhooks; deliveries are
 * dispatched/logged by the outbound-webhook-dispatcher edge function.
 */
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ChevronDown, ChevronRight, Loader2, Plus, Webhook } from "lucide-react";
import { toast } from "sonner";
import {
  COMMON_DATA_FIELDS,
  COMMON_PAYLOAD_FIELDS,
  eventsAreValidForType,
  eventsForType,
  WEBHOOK_TYPES,
  type WebhookType,
} from "@/lib/webhook-events";

interface OutboundWebhook {
  id: string;
  tenant_id: string;
  name: string;
  webhook_type: WebhookType;
  target_url: string;
  event_keys: string[];
  is_enabled: boolean;
  created_at: string;
}

interface Delivery {
  id: string;
  webhook_id: string;
  event_key: string;
  status_code: number | null;
  success: boolean;
  attempts: number;
  created_at: string;
}

function randomSecret() {
  const bytes = new Uint8Array(24);
  crypto.getRandomValues(bytes);
  return (
    "whsec_" +
    Array.from(bytes)
      .map((b) => b.toString(16).padStart(2, "0"))
      .join("")
  );
}

export function WebhooksReal() {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<OutboundWebhook | null>(null);

  const openCreate = () => {
    setEditing(null);
    setDialogOpen(true);
  };
  const openEdit = (wh: OutboundWebhook) => {
    setEditing(wh);
    setDialogOpen(true);
  };

  const { data: webhooks = [], isLoading } = useQuery({
    queryKey: ["outbound-webhooks", currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];
      const { data, error } = await supabase
        .from("tenant_outbound_webhooks")
        .select("*")
        .eq("tenant_id", currentTenantId)
        .order("created_at", { ascending: false });
      if (error) throw error;
      return (data ?? []) as OutboundWebhook[];
    },
    enabled: !!currentTenantId,
  });

  const { data: deliveries = [] } = useQuery({
    queryKey: ["outbound-webhook-deliveries", currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];
      const { data, error } = await supabase
        .from("tenant_outbound_webhook_deliveries")
        .select("*")
        .eq("tenant_id", currentTenantId)
        .order("created_at", { ascending: false })
        .limit(20);
      if (error) throw error;
      return (data ?? []) as Delivery[];
    },
    enabled: !!currentTenantId,
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ id, enabled }: { id: string; enabled: boolean }) => {
      const { error } = await supabase
        .from("tenant_outbound_webhooks")
        .update({ is_enabled: enabled })
        .eq("id", id);
      if (error) throw error;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["outbound-webhooks"] });
      toast.success("Webhook updated");
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : "Update failed"),
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => {
      const { error } = await supabase.from("tenant_outbound_webhooks").delete().eq("id", id);
      if (error) throw error;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["outbound-webhooks"] });
      toast.success("Webhook deleted");
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : "Delete failed"),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="flex items-center gap-2 text-lg font-semibold">
            <Webhook className="h-5 w-5" /> Outbound Webhooks
          </h3>
          <p className="text-sm text-muted-foreground">
            Forward selected events to external endpoints (n8n, Attentive, automation
            engines). Lifecycle and product-usage events use separate webhooks and cannot be
            mixed.
          </p>
        </div>
        <Button size="sm" onClick={openCreate}>
          <Plus className="h-4 w-4 mr-1" /> Add webhook
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Endpoints</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-6">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : webhooks.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">
              No webhooks configured yet.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Target URL</TableHead>
                  <TableHead>Events</TableHead>
                  <TableHead className="w-20">Enabled</TableHead>
                  <TableHead className="w-20" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {webhooks.map((wh) => (
                  <TableRow key={wh.id}>
                    <TableCell className="font-medium">{wh.name}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="text-[10px]">
                        {wh.webhook_type}
                      </Badge>
                    </TableCell>
                    <TableCell className="max-w-[220px] truncate font-mono text-xs">
                      {wh.target_url}
                    </TableCell>
                    <TableCell>{wh.event_keys.length}</TableCell>
                    <TableCell>
                      <Switch
                        checked={wh.is_enabled}
                        onCheckedChange={(v) => toggleMutation.mutate({ id: wh.id, enabled: v })}
                      />
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(wh)}>
                          Edit
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-destructive"
                          onClick={() => deleteMutation.mutate(wh.id)}
                        >
                          Delete
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Recent deliveries</CardTitle>
        </CardHeader>
        <CardContent>
          {deliveries.length === 0 ? (
            <p className="py-4 text-center text-sm text-muted-foreground">No deliveries yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Event</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Attempts</TableHead>
                  <TableHead>When</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {deliveries.map((d) => (
                  <TableRow key={d.id}>
                    <TableCell className="font-mono text-xs">{d.event_key}</TableCell>
                    <TableCell>
                      <Badge variant={d.success ? "default" : "destructive"}>
                        {d.status_code ?? "—"}
                      </Badge>
                    </TableCell>
                    <TableCell>{d.attempts}</TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {new Date(d.created_at).toLocaleString()}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <WebhookDialog
        open={dialogOpen}
        onOpenChange={(v) => {
          setDialogOpen(v);
          if (!v) setEditing(null);
        }}
        tenantId={currentTenantId}
        editing={editing}
        onSaved={() => queryClient.invalidateQueries({ queryKey: ["outbound-webhooks"] })}
      />
    </div>
  );
}

function WebhookDialog({
  open,
  onOpenChange,
  tenantId,
  editing,
  onSaved,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  tenantId: string | null;
  editing: OutboundWebhook | null;
  onSaved: () => void;
}) {
  const isEdit = !!editing;
  const [name, setName] = useState("");
  const [type, setType] = useState<WebhookType>("lifecycle");
  const [url, setUrl] = useState("");
  const [secret, setSecret] = useState(randomSecret());
  const [secretDirty, setSecretDirty] = useState(false);
  const [selected, setSelected] = useState<string[]>([]);
  // Which event rows have their payload params expanded.
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  // Hydrate from the row being edited (or reset for a fresh create) whenever the
  // dialog opens or the target changes.
  useEffect(() => {
    if (!open) return;
    if (editing) {
      setName(editing.name);
      setType(editing.webhook_type);
      setUrl(editing.target_url);
      setSelected(editing.event_keys);
      setSecret("");
      setSecretDirty(false);
    } else {
      setName("");
      setType("lifecycle");
      setUrl("");
      setSelected([]);
      setSecret(randomSecret());
      setSecretDirty(true); // a create always writes a fresh secret
    }
    setExpanded(new Set());
  }, [open, editing]);

  const events = useMemo(() => eventsForType(type), [type]);

  // Switching type clears any selection that doesn't belong to the new type
  // (enforces "no mixing").
  const onTypeChange = (next: WebhookType) => {
    setType(next);
    setSelected((cur) => cur.filter((k) => eventsForType(next).some((e) => e.key === k)));
  };

  const toggle = (k: string) =>
    setSelected((cur) => (cur.includes(k) ? cur.filter((x) => x !== k) : [...cur, k]));

  const allKeys = useMemo(() => events.map((e) => e.key), [events]);
  const allSelected = allKeys.length > 0 && allKeys.every((k) => selected.includes(k));
  const someSelected = selected.length > 0 && !allSelected;
  const toggleAll = () =>
    setSelected((cur) => (allKeys.every((k) => cur.includes(k)) ? [] : allKeys));

  const toggleExpanded = (k: string) =>
    setExpanded((cur) => {
      const next = new Set(cur);
      if (next.has(k)) next.delete(k);
      else next.add(k);
      return next;
    });

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!tenantId) throw new Error("No tenant selected");
      if (!name.trim()) throw new Error("Name is required");
      if (!url.trim()) throw new Error("Target URL is required");
      if (selected.length === 0) throw new Error("Select at least one event");
      if (!eventsAreValidForType(type, selected)) {
        throw new Error("Selected events don't all belong to this webhook type");
      }

      if (isEdit && editing) {
        // Type is immutable (lifecycle vs product_usage can't be mixed); only
        // update the secret when the user regenerated it.
        const patch: Record<string, unknown> = {
          name: name.trim(),
          target_url: url.trim(),
          event_keys: selected,
        };
        if (secretDirty && secret) patch.signing_secret = secret;
        const { error } = await supabase
          .from("tenant_outbound_webhooks")
          .update(patch)
          .eq("id", editing.id);
        if (error) throw error;
      } else {
        const { error } = await supabase.from("tenant_outbound_webhooks").insert({
          tenant_id: tenantId,
          name: name.trim(),
          webhook_type: type,
          target_url: url.trim(),
          signing_secret: secret,
          event_keys: selected,
          is_enabled: true,
        });
        if (error) throw error;
      }
    },
    onSuccess: () => {
      toast.success(isEdit ? "Webhook updated" : "Webhook created");
      onSaved();
      onOpenChange(false);
    },
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : isEdit ? "Update failed" : "Create failed"),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? "Edit outbound webhook" : "Add outbound webhook"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? "Update the name, target URL, and subscribed events. The type is fixed once created (types cannot be mixed)."
              : "Choose a type first — the available events are scoped to it. Types cannot be mixed."}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>Name</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="n8n automation" />
            </div>
            <div className="space-y-2">
              <Label>Type</Label>
              <Select
                value={type}
                onValueChange={(v) => onTypeChange(v as WebhookType)}
                disabled={isEdit}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {WEBHOOK_TYPES.map((t) => (
                    <SelectItem key={t.value} value={t.value}>
                      {t.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {isEdit && (
                <p className="text-[11px] text-muted-foreground">
                  Type can't change after creation.
                </p>
              )}
            </div>
          </div>

          <div className="space-y-2">
            <Label>Target URL</Label>
            <Input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://hooks.example.com/…" />
          </div>

          <div className="space-y-2">
            <Label>Signing secret</Label>
            <div className="flex gap-2">
              <Input
                readOnly
                value={
                  isEdit && !secretDirty
                    ? "•••••••• (unchanged)"
                    : secret
                }
                className="font-mono text-xs"
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                  setSecret(randomSecret());
                  setSecretDirty(true);
                }}
              >
                Regenerate
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Payloads are HMAC-SHA256 signed with this secret (header X-Allia-Signature).
              {isEdit &&
                (secretDirty
                  ? " Saving will rotate the secret — update your endpoint to match."
                  : " Kept as-is; regenerate only if you want to rotate it.")}
            </p>
          </div>

          <div>
            <div className="mb-2 flex items-center justify-between">
              <Label className="block">Events ({type})</Label>
              <label className="flex items-center gap-2 text-xs text-muted-foreground">
                <Checkbox
                  checked={allSelected ? true : someSelected ? "indeterminate" : false}
                  onCheckedChange={toggleAll}
                />
                Select all ({selected.length}/{allKeys.length})
              </label>
            </div>
            <div className="grid max-h-64 gap-2 overflow-auto">
              {events.map((e) => {
                const isOpen = expanded.has(e.key);
                return (
                  <div key={e.key} className="rounded-md border text-sm">
                    <div className="flex items-start gap-2 p-2">
                      <Checkbox
                        className="mt-0.5"
                        checked={selected.includes(e.key)}
                        onCheckedChange={() => toggle(e.key)}
                      />
                      <div className="min-w-0 flex-1">
                        <span className="font-mono text-xs">{e.key}</span>
                        <span className="block text-xs text-muted-foreground">{e.description}</span>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-6 shrink-0 px-2 text-xs"
                        onClick={() => toggleExpanded(e.key)}
                      >
                        {isOpen ? (
                          <ChevronDown className="h-3.5 w-3.5" />
                        ) : (
                          <ChevronRight className="h-3.5 w-3.5" />
                        )}
                        {e.params.length} fields
                      </Button>
                    </div>
                    {isOpen && (
                      <div className="border-t bg-muted/40 px-3 py-2">
                        <p className="mb-1 text-[11px] font-medium text-muted-foreground">
                          Payload fields (under <span className="font-mono">data</span>) — all are
                          delivered.{" "}
                          <span className="font-normal">
                            <span className="font-mono">derived</span> fields are resolved for you
                            (names, email, phone) so you don't have to look ids up.
                          </span>
                        </p>
                        <ul className="space-y-1">
                          {e.params.map((p) => (
                            <li key={p.name} className="flex flex-wrap items-center gap-1.5 text-[11px]">
                              <span className="font-mono text-foreground">{p.name}</span>
                              <span className="text-muted-foreground">{p.type}</span>
                              {p.source === "derived" && (
                                <Badge variant="outline" className="px-1 py-0 text-[9px]">
                                  derived
                                </Badge>
                              )}
                              {p.pii && (
                                <Badge variant="outline" className="border-amber-400 px-1 py-0 text-[9px] text-amber-600 dark:text-amber-400">
                                  PII
                                </Badge>
                              )}
                              <span className="text-muted-foreground">— {p.description}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
            <details className="mt-2 text-[11px] text-muted-foreground">
              <summary className="cursor-pointer">Common fields on every delivery</summary>
              <div className="mt-1 space-y-2 rounded-md border bg-muted/40 p-2">
                <div>
                  <p className="mb-1 font-medium">Envelope</p>
                  <ul className="space-y-0.5">
                    {COMMON_PAYLOAD_FIELDS.map((p) => (
                      <li key={p.name} className="flex gap-2">
                        <span className="font-mono text-foreground">{p.name}</span>
                        <span>{p.type}</span>
                        <span>— {p.description}</span>
                      </li>
                    ))}
                  </ul>
                </div>
                <div>
                  <p className="mb-1 font-medium">
                    Always in <span className="font-mono">data</span>
                  </p>
                  <ul className="space-y-0.5">
                    {COMMON_DATA_FIELDS.map((p) => (
                      <li key={p.name} className="flex gap-2">
                        <span className="font-mono text-foreground">{p.name}</span>
                        <span>{p.type}</span>
                        <span>— {p.description}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </details>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
            {saveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? "Save changes" : "Create webhook"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
