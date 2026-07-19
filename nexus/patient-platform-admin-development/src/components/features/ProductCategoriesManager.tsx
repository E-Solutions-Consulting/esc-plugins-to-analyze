import { useState, useEffect } from 'react';
import { useQuery, useQueries, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from '@/hooks/useAuditLog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { toast } from 'sonner';
import { Loader2, FolderTree } from 'lucide-react';

interface ProductCategory {
  id: string;
  name: string;
  key: string;
  description: string | null;
  is_active: boolean;
  display_order: number;
}

interface ProductCategoriesManagerProps {
  productId: string;
  productName: string;
  trigger?: React.ReactNode;
}

export function ProductCategoriesManager({
  productId,
  productName,
  trigger,
}: ProductCategoriesManagerProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedCategories, setSelectedCategories] = useState<Set<string>>(new Set());
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const [
    { data: categories = [], isLoading: isLoadingCategories },
    { data: assignedCategories = [], isLoading: isLoadingAssigned }
  ] = useQueries({
    queries: [
      // Fetch all active categories
      {
        queryKey: ['product-categories-active'],
        queryFn: async () => {
          const { data, error } = await supabase
            .from('product_categories' as 'medication_capabilities')
            .select('*')
            .eq('is_active', true)
            .order('display_order', { ascending: true });

          if (error) throw error;
          return (data ?? []) as unknown as ProductCategory[];
        },
        enabled: isOpen,
      },
      // Fetch assigned categories for this product
      {
        queryKey: ['product-category-assignments', productId],
        queryFn: async () => {
          const { data, error } = await supabase
            .from('product_category_assignments' as 'medication_capabilities')
            .select('category_id')
            .eq('product_id' as 'id', productId);

          if (error) throw error;
          return (data as unknown as Array<{ category_id: string }>).map((a) => a.category_id);
        },
        enabled: isOpen,
      },
    ],
  });

  // Sync selected categories when assigned data loads
  useEffect(() => {
    if (isOpen && assignedCategories.length >= 0) {
      setSelectedCategories(new Set(assignedCategories));
    }
  }, [isOpen, assignedCategories]);

  // Save mutation
  const saveMutation = useMutation({
    mutationFn: async (categoryIds: string[]) => {
      const beforeCategoryIds = [...assignedCategories];
      
      // Delete all existing assignments
      const { error: deleteError } = await supabase
        .from('product_category_assignments' as 'medication_capabilities')
        .delete()
        .eq('product_id' as 'id', productId);

      if (deleteError) throw deleteError;

      // Insert new assignments
      if (categoryIds.length > 0) {
        const insertData = categoryIds.map((categoryId) => ({
          product_id: productId,
          category_id: categoryId,
        }));
        
        const { error: insertError } = await supabase
          .from('product_category_assignments' as 'medication_capabilities')
          .insert(insertData as never);

        if (insertError) throw insertError;
      }
      
      return { beforeCategoryIds, afterCategoryIds: categoryIds };
    },
    onSuccess: ({ beforeCategoryIds, afterCategoryIds }) => {
      queryClient.invalidateQueries({ queryKey: ['product-category-assignments', productId] });
      queryClient.invalidateQueries({ queryKey: ['product-category-assignments-with-names', productId] });
      
      // Calculate added and removed categories for audit log
      const added = afterCategoryIds.filter(id => !beforeCategoryIds.includes(id));
      const removed = beforeCategoryIds.filter(id => !afterCategoryIds.includes(id));
      
      // Get category names for better audit trail
      const getCategoryNames = (ids: string[]) => 
        ids.map(id => categories.find(c => c.id === id)?.name || id);
      
      const beforeData = {
        category_ids: beforeCategoryIds,
        category_names: getCategoryNames(beforeCategoryIds),
      };
      
      const afterData = {
        category_ids: afterCategoryIds,
        category_names: getCategoryNames(afterCategoryIds),
        added: getCategoryNames(added),
        removed: getCategoryNames(removed),
      };
      
      // Log the category assignment change
      logAction({
        action: 'update_categories',
        entityType: 'product',
        entityId: productId,
        beforeData,
        afterData,
      });
      
      toast.success('Categories updated successfully');
      setIsOpen(false);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update categories');
    },
  });

  const handleToggleCategory = (categoryId: string) => {
    setSelectedCategories((prev) => {
      const next = new Set(prev);
      if (next.has(categoryId)) {
        next.delete(categoryId);
      } else {
        next.add(categoryId);
      }
      return next;
    });
  };

  const handleSave = () => {
    saveMutation.mutate(Array.from(selectedCategories));
  };

  const isLoading = isLoadingCategories || isLoadingAssigned;

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
  };

  const handleOpen = () => {
    setIsOpen(true);
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleOpenChange}>
      {trigger ? (
        <div onClick={handleOpen}>{trigger}</div>
      ) : (
        <Button variant="outline" size="sm" onClick={handleOpen}>
          <FolderTree className="h-4 w-4 mr-2" />
          Categories
        </Button>
      )}
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Product Categories</DialogTitle>
          <DialogDescription>
            Assign categories to "{productName}"
          </DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : categories.length === 0 ? (
          <div className="py-8 text-center text-muted-foreground">
            No categories have been defined yet.
          </div>
        ) : (
          <ScrollArea className="max-h-[300px] pr-4">
            <div className="space-y-3">
              {categories.map((category) => (
                <label
                  key={category.id}
                  className="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-muted/50 transition-colors"
                >
                  <Checkbox
                    checked={selectedCategories.has(category.id)}
                    onCheckedChange={() => handleToggleCategory(category.id)}
                  />
                  <div className="flex-1 min-w-0">
                    <div className="font-medium">{category.name}</div>
                    {category.description && (
                      <p className="text-sm text-muted-foreground mt-0.5">
                        {category.description}
                      </p>
                    )}
                    <Badge variant="secondary" className="mt-1 text-xs">
                      {category.key}
                    </Badge>
                  </div>
                </label>
              ))}
            </div>
          </ScrollArea>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => setIsOpen(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saveMutation.isPending || isLoading}>
            {saveMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
            Save Changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// Badge display component for showing assigned categories
interface ProductCategoryBadgesProps {
  productId: string;
}

export function ProductCategoryBadges({ productId }: ProductCategoryBadgesProps) {
  const { data: categories = [] } = useQuery({
    queryKey: ['product-category-assignments-with-names', productId],
    queryFn: async () => {
      // First get the assignments
      const { data: assignments, error: assignmentsError } = await supabase
        .from('product_category_assignments' as 'medication_capabilities')
        .select('category_id')
        .eq('product_id' as 'id', productId);

      if (assignmentsError) throw assignmentsError;
      
      const categoryIds = (assignments as unknown as Array<{ category_id: string }>).map(a => a.category_id);
      
      if (categoryIds.length === 0) return [];

      // Then get the category details
      const { data: categoriesData, error: categoriesError } = await supabase
        .from('product_categories' as 'medication_capabilities')
        .select('id, name, key')
        .in('id', categoryIds);

      if (categoriesError) throw categoriesError;
      
      return (categoriesData ?? []) as unknown as Array<{ id: string; name: string; key: string }>;
    },
  });

  if (categories.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-1">
      {categories.slice(0, 3).map((cat) => (
        <Badge key={cat.id} variant="outline" className="text-xs">
          {cat.name}
        </Badge>
      ))}
      {categories.length > 3 && (
        <Badge variant="secondary" className="text-xs">
          +{categories.length - 3} more
        </Badge>
      )}
    </div>
  );
}
