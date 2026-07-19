import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from '@/hooks/useAuditLog';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { DataTable, Column } from '@/components/common/DataTable';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { toast } from 'sonner';
import { Plus, Loader2, Pencil, Trash2 } from 'lucide-react';
import { ProductTypesManager } from '@/components/features/ProductTypesManager';

interface ProductCategory {
  id: string;
  name: string;
  key: string;
  description: string | null;
  is_active: boolean;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface FormData {
  name: string;
  key: string;
  description: string;
  display_order: number;
}

const emptyFormData: FormData = {
  name: '',
  key: '',
  description: '',
  display_order: 0,
};

export function ProductCategoriesContent() {
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<ProductCategory | null>(null);
  const [formData, setFormData] = useState<FormData>(emptyFormData);

  const { data: categories = [], isLoading } = useQuery({
    queryKey: ['product-categories', search],
    queryFn: async () => {
      let query = supabase
        .from('product_categories' as 'medication_capabilities')
        .select('*')
        .order('display_order', { ascending: true });

      if (search) {
        query = query.or(`name.ilike.%${search}%,key.ilike.%${search}%`);
      }

      const { data, error } = await query;
      if (error) throw error;
      return (data ?? []) as unknown as ProductCategory[];
    },
  });

  const createMutation = useMutation({
    mutationFn: async (data: FormData) => {
      const { data: category, error } = await supabase
        .from('product_categories' as 'medication_capabilities')
        .insert([data as never])
        .select()
        .single();

      if (error) throw error;
      return category as unknown as ProductCategory;
    },
    onSuccess: (category) => {
      queryClient.invalidateQueries({ queryKey: ['product-categories'] });
      logAction({
        action: 'create',
        entityType: 'product_category',
        entityId: category.id,
        afterData: category as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Product category created successfully');
      setIsCreateDialogOpen(false);
      setFormData(emptyFormData);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to create category');
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: FormData }) => {
      const beforeCategory = categories.find((c) => c.id === id);

      const { data: category, error } = await supabase
        .from('product_categories' as 'medication_capabilities')
        .update(data as never)
        .eq('id', id)
        .select()
        .single();

      if (error) throw error;
      return { category: category as unknown as ProductCategory, beforeData: beforeCategory };
    },
    onSuccess: ({ category, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['product-categories'] });
      logAction({
        action: 'update',
        entityType: 'product_category',
        entityId: category.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: category as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Product category updated successfully');
      setIsEditDialogOpen(false);
      setSelectedCategory(null);
      setFormData(emptyFormData);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update category');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => {
      const beforeCategory = categories.find((c) => c.id === id);

      const { error } = await supabase
        .from('product_categories' as 'medication_capabilities')
        .delete()
        .eq('id', id);

      if (error) throw error;
      return { id, beforeData: beforeCategory };
    },
    onSuccess: ({ id, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['product-categories'] });
      logAction({
        action: 'delete',
        entityType: 'product_category',
        entityId: id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Product category deleted successfully');
      setIsDeleteDialogOpen(false);
      setSelectedCategory(null);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to delete category');
    },
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ id, is_active }: { id: string; is_active: boolean }) => {
      const beforeCategory = categories.find((c) => c.id === id);

      const { error } = await supabase
        .from('product_categories' as 'medication_capabilities')
        .update({ is_active } as never)
        .eq('id', id);

      if (error) throw error;
      return { id, is_active, beforeData: beforeCategory };
    },
    onSuccess: ({ id, is_active, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['product-categories'] });
      logAction({
        action: 'update',
        entityType: 'product_category',
        entityId: id,
        beforeData: { is_active: beforeData?.is_active },
        afterData: { is_active },
        tenantId: null,
      });
      toast.success(`Category ${is_active ? 'activated' : 'deactivated'}`);
    },
  });

  const generateKey = (name: string) => {
    return name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_|_$/g, '');
  };

  const handleNameChange = (name: string) => {
    setFormData((prev) => ({
      ...prev,
      name,
      key: prev.key === '' || prev.key === generateKey(prev.name) ? generateKey(name) : prev.key,
    }));
  };

  const handleOpenEdit = (category: ProductCategory) => {
    setSelectedCategory(category);
    setFormData({
      name: category.name,
      key: category.key,
      description: category.description || '',
      display_order: category.display_order,
    });
    setIsEditDialogOpen(true);
  };

  const handleOpenDelete = (category: ProductCategory) => {
    setSelectedCategory(category);
    setIsDeleteDialogOpen(true);
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.key.trim()) {
      toast.error('Name and key are required');
      return;
    }
    createMutation.mutate(formData);
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCategory) return;
    if (!formData.name.trim() || !formData.key.trim()) {
      toast.error('Name and key are required');
      return;
    }
    updateMutation.mutate({ id: selectedCategory.id, data: formData });
  };

  const handleDeleteConfirm = () => {
    if (!selectedCategory) return;
    deleteMutation.mutate(selectedCategory.id);
  };

  const getToggleTooltip = (category: ProductCategory) => {
    if (category.is_active) {
      return `Disable ${category.name}. Disabled categories are hidden when assigning categories to products.`;
    }

    return `Enable ${category.name}. Enabled categories are available when assigning categories to products.`;
  };

  const columns: Column<ProductCategory>[] = [
    {
      key: 'display_order',
      header: '#',
      cell: (cat) => <span className="text-muted-foreground">{cat.display_order}</span>,
      className: 'w-12',
    },
    {
      key: 'name',
      header: 'Name',
      cell: (cat) => (
        <div>
          <p className="font-medium">{cat.name}</p>
        </div>
      ),
    },
    {
      key: 'description',
      header: 'Description',
      cell: (cat) => (
        <p className="max-w-md text-sm text-muted-foreground line-clamp-2">
          {cat.description || 'No description'}
        </p>
      ),
    },
    {
      key: 'key',
      header: 'Key',
      cell: (cat) => (
        <Badge variant="secondary" className="font-mono text-xs">
          {cat.key}
        </Badge>
      ),
    },
    {
      key: 'is_active',
      header: 'Active',
      cell: (cat) => (
        <Tooltip>
          <TooltipTrigger asChild>
            <span className="inline-flex">
              <Switch
                checked={cat.is_active}
                onCheckedChange={(checked) => toggleMutation.mutate({ id: cat.id, is_active: checked })}
                disabled={toggleMutation.isPending}
                aria-label={`${cat.is_active ? 'Disable' : 'Enable'} ${cat.name}`}
              />
            </span>
          </TooltipTrigger>
          <TooltipContent>
            <p className="max-w-xs">{getToggleTooltip(cat)}</p>
          </TooltipContent>
        </Tooltip>
      ),
      className: 'w-20',
    },
    {
      key: 'actions',
      header: 'Actions',
      cell: (cat) => (
        <div className="flex items-center gap-2">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleOpenEdit(cat)}
                className="h-8 w-8 p-0"
                aria-label={`Edit ${cat.name}`}
              >
                <Pencil className="h-4 w-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>Edit</TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleOpenDelete(cat)}
                className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                aria-label={`Remove ${cat.name}`}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>Remove</TooltipContent>
          </Tooltip>
        </div>
      ),
      className: 'w-24',
    },
  ];

  const renderForm = (onSubmit: (e: React.FormEvent) => void, isEdit: boolean) => (
    <form onSubmit={onSubmit}>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Category' : 'Add New Category'}</DialogTitle>
        <DialogDescription>
          {isEdit
            ? 'Update product category details.'
            : 'Create a new product category for organizing products across all tenants.'}
        </DialogDescription>
      </DialogHeader>
      <div className="grid gap-4 py-4">
        <div className="space-y-2">
          <Label htmlFor="name">Name *</Label>
          <Input
            id="name"
            value={formData.name}
            onChange={(e) => handleNameChange(e.target.value)}
            placeholder="e.g., Weight Loss"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="key">Key *</Label>
          <Input
            id="key"
            value={formData.key}
            onChange={(e) => setFormData({ ...formData, key: e.target.value })}
            placeholder="e.g., weight_loss"
            className="font-mono"
          />
          <p className="text-xs text-muted-foreground">
            Unique identifier used in code. Auto-generated from name.
          </p>
        </div>
        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            value={formData.description}
            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
            placeholder="Optional description"
            rows={2}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="display_order">Display Order</Label>
          <Input
            id="display_order"
            type="number"
            value={formData.display_order}
            onChange={(e) => setFormData({ ...formData, display_order: parseInt(e.target.value) || 0 })}
            min={0}
          />
        </div>
      </div>
      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            if (isEdit) {
              setIsEditDialogOpen(false);
            } else {
              setIsCreateDialogOpen(false);
            }
            setFormData(emptyFormData);
          }}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={createMutation.isPending || updateMutation.isPending}>
          {(createMutation.isPending || updateMutation.isPending) && (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          )}
          {isEdit ? 'Save Changes' : 'Add Category'}
        </Button>
      </DialogFooter>
    </form>
  );

  return (
    <>
      <PageHeader
        title="Product Catalog"
        description="Manage the catalog shared across all tenants: top-level product types (lines) and the sub-categories used to tag products."
      />

      {/* Primary: the top-level product TYPES (Medications, Labs, …). */}
      <ProductTypesManager />

      {/* Secondary: the legacy sub-categories (Weight Loss, Energy, …) used as
          per-product tags within a type. Kept editable here. */}
      <div className="mt-10 space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Sub-categories</h2>
            <p className="text-sm text-muted-foreground">
              Tags assigned to products within a type (e.g. Weight Loss, Energy).
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
                <Plus className="h-4 w-4 mr-2" />
                Add Sub-category
              </Button>
            </DialogTrigger>
            <DialogContent>{renderForm(handleCreateSubmit, false)}</DialogContent>
          </Dialog>
        </div>

        <DataTable
          columns={columns}
          data={categories}
          isLoading={isLoading}
          searchPlaceholder="Search sub-categories..."
          searchValue={search}
          onSearchChange={setSearch}
          emptyMessage="No sub-categories found"
        />
      </div>

      {/* Edit Dialog */}
      <Dialog
        open={isEditDialogOpen}
        onOpenChange={(open) => {
          setIsEditDialogOpen(open);
          if (!open) {
            setSelectedCategory(null);
            setFormData(emptyFormData);
          }
        }}
      >
        <DialogContent>{renderForm(handleEditSubmit, true)}</DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Category</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{selectedCategory?.name}"? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteConfirm}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function ProductCategories() {
  return (
    <AdminLayout variant="platform">
      <ProductCategoriesContent />
    </AdminLayout>
  );
}
