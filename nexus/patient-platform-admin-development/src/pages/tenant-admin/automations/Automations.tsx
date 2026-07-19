/** Communications Automations — list page. Sibling to Subscriptions/Orders. */
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Plus, Trash2, Workflow } from "lucide-react";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, type Column } from "@/components/common/DataTable";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import {
  useCommsAutomations,
  useCreateAutomation,
  useDeleteAutomation,
} from "@/hooks/useCommsAutomations";
import { TRIGGER_DEFINITIONS } from "@/lib/comms-automations/catalog";
import type { CommsAutomation } from "@/lib/comms-automations/types";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  active: "default",
  draft: "secondary",
  paused: "outline",
  archived: "destructive",
};

function triggerLabel(automation: CommsAutomation): string {
  const kind = automation.trigger_config?.kind;
  return TRIGGER_DEFINITIONS.find((t) => t.kind === kind)?.label ?? "Not set";
}

export default function Automations() {
  const navigate = useNavigate();
  const { data: automations = [], isLoading } = useCommsAutomations();
  const createAutomation = useCreateAutomation();
  const deleteAutomation = useDeleteAutomation();

  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [toDelete, setToDelete] = useState<CommsAutomation | null>(null);

  const confirmDelete = async () => {
    if (!toDelete) return;
    try {
      await deleteAutomation.mutateAsync(toDelete.id);
      toast.success("Automation deleted");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Delete failed");
    } finally {
      setToDelete(null);
    }
  };

  const handleCreate = async () => {
    if (!name.trim()) {
      toast.error("Give your automation a name");
      return;
    }
    try {
      const created = await createAutomation.mutateAsync({ name: name.trim(), description });
      toast.success("Automation created");
      setOpen(false);
      setName("");
      setDescription("");
      navigate(`/tenant-admin/automations/${created.id}`);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to create automation");
    }
  };

  const columns: Column<CommsAutomation>[] = [
    {
      key: "name",
      header: "Automation",
      cell: (a) => (
        <div className="flex items-center gap-2">
          <Workflow className="h-4 w-4 text-muted-foreground" />
          <div>
            <div className="font-medium">{a.name}</div>
            {a.description && (
              <div className="text-xs text-muted-foreground line-clamp-1">{a.description}</div>
            )}
          </div>
        </div>
      ),
    },
    { key: "trigger", header: "Trigger", cell: (a) => <span className="text-sm">{triggerLabel(a)}</span> },
    {
      key: "status",
      header: "Status",
      cell: (a) => (
        <Badge variant={STATUS_VARIANT[a.status] ?? "secondary"} className="capitalize">
          {a.status}
        </Badge>
      ),
    },
    {
      key: "enrolled",
      header: "Enrolled",
      cell: (a) => <span className="tabular-nums">{a.enrolled_count}</span>,
    },
    {
      key: "last",
      header: "Last triggered",
      cell: (a) =>
        a.last_triggered_at ? (
          <span className="text-sm text-muted-foreground">
            {new Date(a.last_triggered_at).toLocaleDateString()}
          </span>
        ) : (
          <span className="text-sm text-muted-foreground">—</span>
        ),
    },
    {
      key: "actions",
      header: "",
      cell: (a) => (
        <div className="flex justify-end">
          <Button
            variant="ghost"
            size="icon"
            aria-label="Delete automation"
            onClick={(e) => { e.stopPropagation(); setToDelete(a); }}
          >
            <Trash2 className="h-4 w-4 text-destructive" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Communications Automations"
        description="Build email & SMS journeys triggered by events, subscriptions and orders — with native n8n hand-off."
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => navigate("/tenant-admin/automations/templates")}>
              Templates
            </Button>
            <Button onClick={() => setOpen(true)}>
              <Plus className="h-4 w-4 mr-1" /> New automation
            </Button>
          </div>
        }
      />

      <DataTable
        columns={columns}
        data={automations}
        isLoading={isLoading}
        emptyMessage="No automations yet. Create your first journey."
        onRowClick={(a) => navigate(`/tenant-admin/automations/${a.id}`)}
      />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>New automation</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="auto-name">Name</Label>
              <Input
                id="auto-name"
                placeholder="e.g. Renewal reminder — 3 days before"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="auto-desc">Description (optional)</Label>
              <Textarea
                id="auto-desc"
                placeholder="What does this automation do?"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
            <Button onClick={handleCreate} disabled={createAutomation.isPending}>
              {createAutomation.isPending ? "Creating…" : "Create & open builder"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!toDelete} onOpenChange={(o) => !o && setToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this automation?</AlertDialogTitle>
            <AlertDialogDescription>
              “{toDelete?.name}” and its steps, enrollments and run history will be permanently
              removed. This does not delete the n8n project or workflow in n8n.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
