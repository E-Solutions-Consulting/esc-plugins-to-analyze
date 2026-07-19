/**
 * ProductTypesManager — platform-admin CRUD for the global `product_types` table
 * (the catalog's top-level lines: Medications, Labs, Fitness, Wearables,
 * Experiences). Superadmin-managed; tenants then enable/disable per tenant under
 * Catalog → Products.
 *
 * Mirrors the MedicationCapabilities / ProductCategories CRUD pattern, adding an
 * `availability` select ('available' | 'coming_soon'). product_types isn't in the
 * generated Database type union yet, so the table name is cast like elsewhere.
 */
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuditLog } from "@/hooks/useAuditLog";
import { DataTable, Column } from "@/components/common/DataTable";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "sonner";
import { Loader2, Pencil, Plus, Trash2 } from "lucide-react";

type Availability = "available" | "coming_soon";

interface ProductType {
  id: string;
  name: string;
  key: string;
  description: string | null;
  availability: Availability;
  is_active: boolean;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface FormData {
  name: string;
  key: string;
  description: string;
  availability: Availability;
  display_order: number;
}

const emptyFormData: FormData = {
  name: "",
  key: "",
  description: "",
  availability: "coming_soon",
  display_order: 0,
};

// product_types isn't in the generated Database type union; cast the table name
// the same way the app does for product_categories.
const PRODUCT_TYPES_TABLE = "product_types" as "medication_capabilities";

export function ProductTypesManager() {
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [selected, setSelected] = useState<ProductType | null>(null);
  const [formData, setFormData] = useState<FormData>(emptyFormData);

  const { data: types = [], isLoading } = useQuery({
    queryKey: ["product-types-admin"],
    queryFn: async () => {
      const { data, error } = await supabase
        .from(PRODUCT_TYPES_TABLE)
        .select("*")
        .order("display_order", { ascending: true })
        .order("name", { ascending: true });
      if (error) throw error;
      return (data ?? []) as unknown as ProductType[];
    },
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["product-types-admin"] });
    // Tenants read the same table via this key — refresh their view too.
    queryClient.invalidateQueries({ queryKey: ["tenant-product-types"] });
  };

  const createMutation = useMutation({
    mutationFn: async (data: FormData) => {
      const { data: row, error } = await supabase
        .from(PRODUCT_TYPES_TABLE)
        .insert([data as never])
        .select()
        .single();
      if (error) throw error;
      return row as unknown as ProductType;
    },
    onSuccess: (row) => {
      invalidate();
      logAction({
        action: "create",
        entityType: "product_type",
        entityId: row.id,
        afterData: row as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Product type created");
      setIsCreateDialogOpen(false);
      setFormData(emptyFormData);
    },
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : "Failed to create product type"),
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: FormData }) => {
      const before = types.find((t) => t.id === id);
      const { data: row, error } = await supabase
        .from(PRODUCT_TYPES_TABLE)
        .update(data as never)
        .eq("id", id)
        .select()
        .single();
      if (error) throw error;
      return { row: row as unknown as ProductType, before };
    },
    onSuccess: ({ row, before }) => {
      invalidate();
      logAction({
        action: "update",
        entityType: "product_type",
        entityId: row.id,
        beforeData: before as unknown as Record<string, unknown>,
        afterData: row as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Product type updated");
      setIsEditDialogOpen(false);
      setSelected(null);
      setFormData(emptyFormData);
    },
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : "Failed to update product type"),
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => {
      const before = types.find((t) => t.id === id);
      const { error } = await supabase
        .from(PRODUCT_TYPES_TABLE)
        .delete()
        .eq("id", id);
      if (error) throw error;
      return { id, before };
    },
    onSuccess: ({ id, before }) => {
      invalidate();
      logAction({
        action: "delete",
        entityType: "product_type",
        entityId: id,
        beforeData: before as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Product type deleted");
      setIsDeleteDialogOpen(false);
      setSelected(null);
    },
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : "Failed to delete product type"),
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ id, is_active }: { id: string; is_active: boolean }) => {
      const { error } = await supabase
        .from(PRODUCT_TYPES_TABLE)
        .update({ is_active } as never)
        .eq("id", id);
      if (error) throw error;
      return { id, is_active };
    },
    onSuccess: ({ id, is_active }) => {
      invalidate();
      logAction({
        action: "update",
        entityType: "product_type",
        entityId: id,
        afterData: { is_active },
        tenantId: null,
      });
      toast.success(`Product type ${is_active ? "activated" : "deactivated"}`);
    },
  });

  const generateKey = (name: string) =>
    name.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");

  const handleNameChange = (name: string) => {
    setFormData((prev) => ({
      ...prev,
      name,
      key: prev.key === "" || prev.key === generateKey(prev.name)
        ? generateKey(name)
        : prev.key,
    }));
  };

  const openEdit = (type: ProductType) => {
    setSelected(type);
    setFormData({
      name: type.name,
      key: type.key,
      description: type.description || "",
      availability: type.availability,
      display_order: type.display_order,
    });
    setIsEditDialogOpen(true);
  };

  const submit = (e: React.FormEvent, isEdit: boolean) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.key.trim()) {
      toast.error("Name and key are required");
      return;
    }
    if (isEdit && selected) {
      updateMutation.mutate({ id: selected.id, data: formData });
    } else {
      createMutation.mutate(formData);
    }
  };

  const columns: Column<ProductType>[] = [
    {
      key: "display_order",
      header: "#",
      cell: (t) => <span className="text-muted-foreground">{t.display_order}</span>,
      className: "w-12",
    },
    {
      key: "name",
      header: "Name",
      cell: (t) => <p className="font-medium">{t.name}</p>,
    },
    {
      key: "availability",
      header: "Availability",
      cell: (t) => (
        <Badge variant={t.availability === "available" ? "default" : "secondary"}>
          {t.availability === "available" ? "Available" : "Coming soon"}
        </Badge>
      ),
      className: "w-32",
    },
    {
      key: "key",
      header: "Key",
      cell: (t) => (
        <Badge variant="secondary" className="font-mono text-xs">{t.key}</Badge>
      ),
    },
    {
      key: "is_active",
      header: "Active",
      cell: (t) => (
        <Switch
          checked={t.is_active}
          onCheckedChange={(checked) =>
            toggleMutation.mutate({ id: t.id, is_active: checked })}
          disabled={toggleMutation.isPending}
          aria-label={`${t.is_active ? "Disable" : "Enable"} ${t.name}`}
        />
      ),
      className: "w-20",
    },
    {
      key: "actions",
      header: "Actions",
      cell: (t) => (
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => openEdit(t)}
            className="h-8 w-8 p-0"
            aria-label={`Edit ${t.name}`}
          >
            <Pencil className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSelected(t);
              setIsDeleteDialogOpen(true);
            }}
            className="h-8 w-8 p-0 text-destructive hover:text-destructive"
            aria-label={`Delete ${t.name}`}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
      className: "w-24",
    },
  ];

  const renderForm = (isEdit: boolean) => (
    <form onSubmit={(e) => submit(e, isEdit)}>
      <DialogHeader>
        <DialogTitle>{isEdit ? "Edit Product Type" : "Add Product Type"}</DialogTitle>
        <DialogDescription>
          Product types are the catalog's top-level lines, shared across all tenants.
          Tenants enable the ones they offer under Catalog → Products.
        </DialogDescription>
      </DialogHeader>
      <div className="grid gap-4 py-4">
        <div className="space-y-2">
          <Label htmlFor="pt-name">Name *</Label>
          <Input
            id="pt-name"
            value={formData.name}
            onChange={(e) => handleNameChange(e.target.value)}
            placeholder="e.g., Medications"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="pt-key">Key *</Label>
          <Input
            id="pt-key"
            value={formData.key}
            onChange={(e) => setFormData({ ...formData, key: e.target.value })}
            placeholder="e.g., medications"
            className="font-mono"
            disabled={isEdit}
          />
          <p className="text-xs text-muted-foreground">
            Unique identifier used in code.{" "}
            {isEdit ? "Can't change after creation." : "Auto-generated from name."}
          </p>
        </div>
        <div className="space-y-2">
          <Label htmlFor="pt-availability">Availability</Label>
          <Select
            value={formData.availability}
            onValueChange={(v) =>
              setFormData({ ...formData, availability: v as Availability })}
          >
            <SelectTrigger id="pt-availability">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="available">Available</SelectItem>
              <SelectItem value="coming_soon">Coming soon</SelectItem>
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            "Available" types can be enabled by tenants and manage real products.
            "Coming soon" types show as a disabled tile.
          </p>
        </div>
        <div className="space-y-2">
          <Label htmlFor="pt-description">Description</Label>
          <Textarea
            id="pt-description"
            value={formData.description}
            onChange={(e) =>
              setFormData({ ...formData, description: e.target.value })}
            placeholder="Optional description"
            rows={2}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="pt-order">Display Order</Label>
          <Input
            id="pt-order"
            type="number"
            value={formData.display_order}
            onChange={(e) =>
              setFormData({
                ...formData,
                display_order: parseInt(e.target.value) || 0,
              })}
            min={0}
          />
        </div>
      </div>
      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            if (isEdit) setIsEditDialogOpen(false);
            else setIsCreateDialogOpen(false);
            setFormData(emptyFormData);
          }}
        >
          Cancel
        </Button>
        <Button
          type="submit"
          disabled={createMutation.isPending || updateMutation.isPending}
        >
          {(createMutation.isPending || updateMutation.isPending) && (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          )}
          {isEdit ? "Save Changes" : "Add Product Type"}
        </Button>
      </DialogFooter>
    </form>
  );

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold">Product Types</h2>
          <p className="text-sm text-muted-foreground">
            The catalog's top-level lines (Medications, Labs, …). Tenants enable
            these per tenant under Catalog → Products.
          </p>
        </div>
        <Dialog
          open={isCreateDialogOpen}
          onOpenChange={(open) => {
            setIsCreateDialogOpen(open);
            if (!open) setFormData(emptyFormData);
          }}
        >
          <DialogTrigger asChild>
            <Button>
              <Plus className="h-4 w-4 mr-2" /> Add Product Type
            </Button>
          </DialogTrigger>
          <DialogContent>{renderForm(false)}</DialogContent>
        </Dialog>
      </div>

      <DataTable
        columns={columns}
        data={types}
        isLoading={isLoading}
        emptyMessage="No product types found"
      />

      <Dialog
        open={isEditDialogOpen}
        onOpenChange={(open) => {
          setIsEditDialogOpen(open);
          if (!open) {
            setSelected(null);
            setFormData(emptyFormData);
          }
        }}
      >
        <DialogContent>{renderForm(true)}</DialogContent>
      </Dialog>

      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Product Type</AlertDialogTitle>
            <AlertDialogDescription>
              Delete "{selected?.name}"? Tenants that enabled it will lose it. This
              cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => selected && deleteMutation.mutate(selected.id)}
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
    </div>
  );
}
