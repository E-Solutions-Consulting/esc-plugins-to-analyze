/** Communications template library — manage reusable email/SMS message templates. */
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Mail, MessageSquare, Pencil, Plus, Trash2 } from "lucide-react";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, type Column } from "@/components/common/DataTable";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
import { toast } from "sonner";
import { TemplateEditor } from "@/components/features/comms-automations/TemplateEditor";
import { useCommsTemplates, useDeleteTemplate } from "@/hooks/useCommsAutomations";
import type { CommsTemplate } from "@/lib/comms-automations/types";

export default function Templates() {
  const navigate = useNavigate();
  const { data: templates = [], isLoading } = useCommsTemplates();
  const deleteTemplate = useDeleteTemplate();

  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<CommsTemplate | null>(null);
  const [toDelete, setToDelete] = useState<CommsTemplate | null>(null);

  const openNew = () => { setEditing(null); setEditorOpen(true); };
  const openEdit = (t: CommsTemplate) => { setEditing(t); setEditorOpen(true); };

  const confirmDelete = async () => {
    if (!toDelete) return;
    try {
      await deleteTemplate.mutateAsync(toDelete.id);
      toast.success("Template archived");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Delete failed");
    } finally {
      setToDelete(null);
    }
  };

  const columns: Column<CommsTemplate>[] = [
    {
      key: "name",
      header: "Template",
      cell: (t) => (
        <div className="flex items-center gap-2">
          {t.channel === "email"
            ? <Mail className="h-4 w-4 text-blue-500" />
            : <MessageSquare className="h-4 w-4 text-green-600" />}
          <div>
            <div className="font-medium">{t.name}</div>
            {t.subject && <div className="text-xs text-muted-foreground line-clamp-1">{t.subject}</div>}
          </div>
        </div>
      ),
    },
    { key: "channel", header: "Channel", cell: (t) => <Badge variant="outline" className="capitalize">{t.channel}</Badge> },
    {
      key: "placeholders",
      header: "Placeholders",
      cell: (t) => <span className="text-sm text-muted-foreground">{t.placeholders?.length ?? 0}</span>,
    },
    {
      key: "updated",
      header: "Updated",
      cell: (t) => <span className="text-sm text-muted-foreground">{new Date(t.updated_at).toLocaleDateString()}</span>,
    },
    {
      key: "actions",
      header: "",
      cell: (t) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" onClick={(e) => { e.stopPropagation(); openEdit(t); }}>
            <Pencil className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={(e) => { e.stopPropagation(); setToDelete(t); }}>
            <Trash2 className="h-4 w-4 text-destructive" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Message Templates"
        description="Reusable email & SMS templates with merge placeholders. Reference them from automation steps."
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => navigate("/tenant-admin/automations")}>
              Automations
            </Button>
            <Button onClick={openNew}>
              <Plus className="h-4 w-4 mr-1" /> New template
            </Button>
          </div>
        }
      />

      <DataTable
        columns={columns}
        data={templates}
        isLoading={isLoading}
        emptyMessage="No templates yet. Create one to reuse across automations."
        onRowClick={(t) => openEdit(t)}
      />

      <TemplateEditor open={editorOpen} onOpenChange={setEditorOpen} template={editing} />

      <AlertDialog open={!!toDelete} onOpenChange={(o) => !o && setToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Archive this template?</AlertDialogTitle>
            <AlertDialogDescription>
              “{toDelete?.name}” will be hidden from the library. Automations that already reference it
              keep working until you change them.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete}>Archive</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
