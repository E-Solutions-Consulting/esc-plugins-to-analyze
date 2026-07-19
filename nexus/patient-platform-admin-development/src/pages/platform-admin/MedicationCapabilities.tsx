import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuditLog } from "@/hooks/useAuditLog";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, Column } from "@/components/common/DataTable";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import {
  Dialog,
  DialogContent,
  DialogDescription,
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import {
  Loader2,
  MoreHorizontal,
  Pencil,
  Trash2,
  Zap,
} from "lucide-react";
import { dateTime } from "@/lib/dayjs";

interface MedicationCapability {
  id: string;
  name: string;
  key: string;
  description: string | null;
  is_active: boolean;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface CapabilityFormData {
  name: string;
  key: string;
  description: string;
  display_order: string;
}

const emptyFormData: CapabilityFormData = {
  name: "",
  key: "",
  description: "",
  display_order: "0",
};

export function MedicationCapabilitiesContent() {
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [selectedCapability, setSelectedCapability] =
    useState<MedicationCapability | null>(null);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState<CapabilityFormData>(emptyFormData);

  const { data: capabilities = [], isLoading } = useQuery({
    queryKey: ["medication-capabilities", search],
    queryFn: async () => {
      let query = supabase
        .from("medication_capabilities" as never)
        .select("*")
        .order("display_order", { ascending: true })
        .order("name", { ascending: true });

      if (search) {
        query = query.or(`name.ilike.%${search}%,key.ilike.%${search}%`);
      }

      const { data, error } = await query;
      if (error) throw error;
      return (data ?? []) as MedicationCapability[];
    },
  });

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};

    if (!formData.name.trim()) {
      errors.name = "Name is required";
    } else if (formData.name.trim().length > 100) {
      errors.name = "Name must be 100 characters or less";
    }

    if (formData.description && formData.description.length > 500) {
      errors.description = "Description must be 500 characters or less";
    }

    const orderNum = parseInt(formData.display_order, 10);
    if (isNaN(orderNum) || orderNum < 0) {
      errors.display_order =
        "Display order must be a valid non-negative number";
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const updateMutation = useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: string;
      data: CapabilityFormData;
    }) => {
      const beforeCapability = capabilities.find((c) => c.id === id);

      const { data: capability, error } = await supabase
        .from("medication_capabilities" as never)
        .update({
          name: data.name.trim(),
          description: data.description.trim() || null,
          display_order: parseInt(data.display_order, 10) || 0,
        } as never)
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;
      return {
        capability: capability as MedicationCapability,
        beforeData: beforeCapability,
      };
    },
    onSuccess: ({ capability, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["medication-capabilities"] });
      logAction({
        action: "update",
        entityType: "medication_capability",
        entityId: capability.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: capability as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Capability updated successfully");
      setIsEditDialogOpen(false);
      setSelectedCapability(null);
      setFormData(emptyFormData);
      setFormErrors({});
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update capability",
      );
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => {
      const beforeCapability = capabilities.find((c) => c.id === id);

      const { error } = await supabase
        .from("medication_capabilities" as never)
        .delete()
        .eq("id", id);

      if (error) throw error;
      return { id, beforeData: beforeCapability };
    },
    onSuccess: ({ id, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["medication-capabilities"] });
      logAction({
        action: "delete",
        entityType: "medication_capability",
        entityId: id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Capability deleted successfully");
      setIsDeleteDialogOpen(false);
      setSelectedCapability(null);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to delete capability",
      );
    },
  });

  const toggleMutation = useMutation({
    mutationFn: async ({
      id,
      is_active,
    }: {
      id: string;
      is_active: boolean;
    }) => {
      const beforeCapability = capabilities.find((c) => c.id === id);

      const { data, error } = await supabase
        .from("medication_capabilities" as never)
        .update({ is_active } as never)
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;
      return {
        capability: data as MedicationCapability,
        beforeData: beforeCapability,
      };
    },
    onSuccess: ({ capability, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["medication-capabilities"] });
      logAction({
        action: "update",
        entityType: "medication_capability",
        entityId: capability.id,
        beforeData: { is_active: beforeData?.is_active },
        afterData: { is_active: capability.is_active },
        tenantId: null,
      });
      toast.success(
        `Capability ${capability.is_active ? "activated" : "deactivated"}`,
      );
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update capability",
      );
    },
  });

  const handleOpenEdit = (capability: MedicationCapability) => {
    setSelectedCapability(capability);
    setFormData({
      name: capability.name,
      key: capability.key,
      description: capability.description || "",
      display_order: capability.display_order.toString(),
    });
    setFormErrors({});
    setIsEditDialogOpen(true);
  };

  const handleOpenDelete = (capability: MedicationCapability) => {
    setSelectedCapability(capability);
    setIsDeleteDialogOpen(true);
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCapability) return;
    if (!validateForm()) {
      toast.error("Please fix the validation errors");
      return;
    }
    updateMutation.mutate({ id: selectedCapability.id, data: formData });
  };

  const handleDeleteConfirm = () => {
    if (!selectedCapability) return;
    deleteMutation.mutate(selectedCapability.id);
  };

  const columns: Column<MedicationCapability>[] = [
    {
      key: "name",
      header: "Capability",
      cell: (capability) => (
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <Zap className="h-5 w-5 text-primary" />
          </div>
          <div>
            <p className="font-medium">{capability.name}</p>
            <p className="text-sm text-muted-foreground font-mono">
              {capability.key}
            </p>
          </div>
        </div>
      ),
    },
    {
      key: "description",
      header: "Description",
      cell: (capability) => (
        <p className="text-sm text-muted-foreground line-clamp-2 max-w-xs">
          {capability.description || "—"}
        </p>
      ),
    },
    {
      key: "order",
      header: "Order",
      cell: (capability) => (
        <span className="text-sm font-mono">{capability.display_order}</span>
      ),
      className: "w-20",
    },
    {
      key: "status",
      header: "Active",
      cell: (capability) => (
        <Switch
          checked={capability.is_active}
          onCheckedChange={(checked) => {
            toggleMutation.mutate({ id: capability.id, is_active: checked });
          }}
          disabled={toggleMutation.isPending}
          aria-label={`Toggle ${capability.name} active state`}
        />
      ),
      className: "w-20",
    },
    {
      key: "created",
      header: "Created",
      cell: (capability) =>
        dateTime(capability.created_at).format("MMM D, YYYY"),
      className: "w-32",
    },
    {
      key: "actions",
      header: "",
      cell: (capability) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => handleOpenEdit(capability)}>
              <Pencil className="h-4 w-4 mr-2" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => handleOpenDelete(capability)}
              className="text-destructive focus:text-destructive"
            >
              <Trash2 className="h-4 w-4 mr-2" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
      className: "w-12",
    },
  ];

  const renderEditForm = (onSubmit: (e: React.FormEvent) => void) => (
    <form onSubmit={onSubmit}>
      <DialogHeader>
        <DialogTitle>Edit Capability</DialogTitle>
        <DialogDescription>
          Update the medication capability details.
        </DialogDescription>
      </DialogHeader>
      <div className="grid gap-4 py-4">
        <div className="space-y-2">
          <Label htmlFor="name">Name *</Label>
          <Input
            id="name"
            value={formData.name}
            onChange={(e) =>
              setFormData({
                ...formData,
                name: e.target.value,
              })
            }
            maxLength={100}
            placeholder="e.g., Requires Prior Authorization"
            className={formErrors.name ? "border-destructive" : ""}
          />
          {formErrors.name && (
            <p className="text-sm text-destructive">{formErrors.name}</p>
          )}
        </div>
        <div className="space-y-2">
          <Label>Key</Label>
          <code className="block rounded-md border bg-muted px-3 py-2 text-sm">
            {formData.key}
          </code>
          <p className="text-xs text-muted-foreground">
            Unique identifier. Create or rename capability keys through
            migrations.
          </p>
        </div>
        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            value={formData.description}
            onChange={(e) =>
              setFormData({ ...formData, description: e.target.value })
            }
            maxLength={500}
            placeholder="Describe what this capability means for a medication"
            rows={3}
            className={formErrors.description ? "border-destructive" : ""}
          />
          {formErrors.description && (
            <p className="text-sm text-destructive">{formErrors.description}</p>
          )}
        </div>
        <div className="space-y-2">
          <Label htmlFor="display_order">Display Order</Label>
          <Input
            id="display_order"
            type="number"
            min="0"
            value={formData.display_order}
            onChange={(e) =>
              setFormData({ ...formData, display_order: e.target.value })
            }
            placeholder="0"
            className={formErrors.display_order ? "border-destructive" : ""}
          />
          <p className="text-xs text-muted-foreground">
            Lower numbers appear first.
          </p>
          {formErrors.display_order && (
            <p className="text-sm text-destructive">
              {formErrors.display_order}
            </p>
          )}
        </div>
      </div>
      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            setIsEditDialogOpen(false);
            setFormData(emptyFormData);
            setFormErrors({});
          }}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={updateMutation.isPending}>
          {updateMutation.isPending && (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          )}
          Save Changes
        </Button>
      </DialogFooter>
    </form>
  );

  return (
    <>
      <PageHeader
        title="Medication Capabilities"
        description="Define capabilities that can be assigned to medications across all tenants"
      />

      <DataTable
        columns={columns}
        data={capabilities}
        isLoading={isLoading}
        searchPlaceholder="Search capabilities..."
        searchValue={search}
        onSearchChange={setSearch}
        emptyMessage="No capabilities found"
      />

      {/* Edit Dialog */}
      <Dialog
        open={isEditDialogOpen}
        onOpenChange={(open) => {
          setIsEditDialogOpen(open);
          if (!open) {
            setSelectedCapability(null);
            setFormData(emptyFormData);
            setFormErrors({});
          }
        }}
      >
        <DialogContent>{renderEditForm(handleEditSubmit)}</DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog
        open={isDeleteDialogOpen}
        onOpenChange={setIsDeleteDialogOpen}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Capability</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{selectedCapability?.name}"? This
              action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteConfirm}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending && (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              )}
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function MedicationCapabilities() {
  return (
    <AdminLayout variant="platform">
      <MedicationCapabilitiesContent />
    </AdminLayout>
  );
}
