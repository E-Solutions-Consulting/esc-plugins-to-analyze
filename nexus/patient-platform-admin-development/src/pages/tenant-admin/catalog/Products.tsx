import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useQueries, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { syncProductToProviders } from '@/hooks/useProductSync';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { DataTable, Column } from '@/components/common/DataTable';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
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
import { HtmlEditor } from '@/components/common/HtmlEditor';
import { toast } from 'sonner';
import { Plus, Package, Loader2, Eye, RefreshCw, CreditCard } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { ProductCategoryBadges } from '@/components/features/ProductCategoriesManager';
import { dateTime } from '@/lib/dayjs';
import { ROUTES } from '@/lib/constants';
import { toNullableRichTextHtml } from '@/lib/html-content';
import { getMissingProductAvailabilityInfo } from '@/lib/product-availability';
import { canEditResource } from '@/lib/admin-permissions';

const emptyFormData: ProductFormData = {
  name: '',
  sku: '',
  description: '',
  terms_and_conditions_html: '',
  price: '',
};

/**
 * Generate a URL/SKU-friendly slug from a product name
 * e.g., "Semaglutide Weight Loss Plan" -> "semaglutide-weight-loss-plan"
 */
function generateSkuFromName(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '') // Remove special characters except spaces and hyphens
    .replace(/\s+/g, '-')     // Replace spaces with hyphens
    .replace(/-+/g, '-')      // Collapse multiple hyphens
    .replace(/^-|-$/g, '')    // Trim leading/trailing hyphens
    .slice(0, 50);            // Limit to 50 chars (SKU max length)
}

/** Page body without the AdminLayout wrapper (for reuse in the Catalog IA). */
export function ProductsContent() {
  const navigate = useNavigate();
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [availabilityWarning, setAvailabilityWarning] = useState<{
    product: Product;
    missingInfo: string[];
  } | null>(null);
  const canEditProducts = canEditResource(
    { isPlatformSuperadmin, isTenantAdmin, isCustomerSupport, currentTenantId },
    "product",
  );
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState<ProductFormData>(emptyFormData);

  const PAGE_SIZE = 25;

  const { data: productsResult, isLoading } = useQuery({
    queryKey: ['products', currentTenantId, search, page],
    queryFn: async () => {
      if (!currentTenantId) return { data: [], count: 0 };

      const from = (page - 1) * PAGE_SIZE;
      const to = from + PAGE_SIZE - 1;

      let query = supabase
        .from('products')
        .select('*', { count: 'exact' })
        .eq('tenant_id', currentTenantId)
        .order('created_at', { ascending: false })
        .range(from, to);

      if (search) {
        query = query.or(`name.ilike.%${search}%,sku.ilike.%${search}%`);
      }

      const { data, error, count } = await query;
      if (error) throw error;
      return { data: (data || []) as Product[], count: count || 0 };
    },
    enabled: !!currentTenantId,
  });

  const products = useMemo(() => productsResult?.data || [], [productsResult?.data]);
  const totalProducts = productsResult?.count || 0;

  const productIds = useMemo(() => products.map((p) => p.id), [products]);

  // Fetch product readiness details in parallel
  const [
    { data: productMedications = [], isLoading: isLoadingMedications },
    { data: productPaymentProviders = [], isLoading: isLoadingPaymentProviders },
    { data: productProviderPlatforms = [], isLoading: isLoadingProviderPlatforms },
    { data: productCategoryAssignments = [], isLoading: isLoadingCategoryAssignments },
    { data: productFaqs = [], isLoading: isLoadingProductFaqs },
  ] = useQueries({
    queries: [
      {
        queryKey: ['all-product-medications', currentTenantId, productIds],
        queryFn: async () => {
          if (!currentTenantId || productIds.length === 0) return [];

          const { data, error } = await supabase
            .from('product_medications')
            .select('product_id, medication:medications(title, description, image_url)')
            .in('product_id', productIds);

          if (error) throw error;
          return data as Array<{
            product_id: string;
            medication: { title: string; description: string | null; image_url: string | null } | null;
          }>;
        },
        enabled: !!currentTenantId && productIds.length > 0,
      },
      {
        queryKey: ['all-product-payment-providers', currentTenantId, productIds],
        queryFn: async () => {
          if (!currentTenantId || productIds.length === 0) return [];

          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const { data, error } = await (supabase as any)
            .from('product_payment_providers')
            .select('product_id')
            .in('product_id', productIds)
            .eq('is_enabled', true);

          if (error) throw error;
          return data as Array<{ product_id: string }>;
        },
        enabled: !!currentTenantId && productIds.length > 0,
      },
      {
        queryKey: ['all-product-provider-platforms', currentTenantId, productIds],
        queryFn: async () => {
          if (!currentTenantId || productIds.length === 0) return [];

          const { data, error } = await supabase
            .from('product_provider_platforms')
            .select('product_id')
            .in('product_id', productIds)
            .eq('is_enabled', true);

          if (error) throw error;
          return data as Array<{ product_id: string }>;
        },
        enabled: !!currentTenantId && productIds.length > 0,
      },
      {
        queryKey: ['all-product-category-assignments', currentTenantId, productIds],
        queryFn: async () => {
          if (!currentTenantId || productIds.length === 0) return [];

          const { data, error } = await supabase
            .from('product_category_assignments' as 'medication_capabilities')
            .select('product_id')
            .in('product_id' as 'id', productIds);

          if (error) throw error;
          return data as unknown as Array<{ product_id: string }>;
        },
        enabled: !!currentTenantId && productIds.length > 0,
      },
      {
        queryKey: ['all-product-faqs', currentTenantId, productIds],
        queryFn: async () => {
          if (!currentTenantId || productIds.length === 0) return [];

          const { data, error } = await supabase
            .from('product_faqs')
            .select('product_id')
            .in('product_id', productIds);

          if (error) throw error;
          return data as Array<{ product_id: string }>;
        },
        enabled: !!currentTenantId && productIds.length > 0,
      },
    ],
  });

  // Helper to check if product has payment providers
  const hasPaymentProviders = (productId: string): boolean => {
    return productPaymentProviders.some((pp) => pp.product_id === productId);
  };

  // Helper to check if product has provider platforms
  const hasProviderPlatforms = (productId: string): boolean => {
    return productProviderPlatforms.some((pp) => pp.product_id === productId);
  };

  const hasProductCategories = (productId: string): boolean => {
    return productCategoryAssignments.some((assignment) => assignment.product_id === productId);
  };

  const hasProductFaqs = (productId: string): boolean => {
    return productFaqs.some((faq) => faq.product_id === productId);
  };

  const getProductMedications = (productId: string) => {
    return productMedications
      .filter((link) => link.product_id === productId)
      .map((link) => link.medication)
      .filter((medication): medication is NonNullable<typeof medication> => Boolean(medication));
  };

  const getMissingProductInformation = (product: Product) => {
    return getMissingProductAvailabilityInfo(product, {
      medications: getProductMedications(product.id),
      hasCategories: hasProductCategories(product.id),
      hasFaqs: hasProductFaqs(product.id),
    });
  };

  const isProductReadinessLoading =
    isLoadingMedications ||
    isLoadingPaymentProviders ||
    isLoadingProviderPlatforms ||
    isLoadingCategoryAssignments ||
    isLoadingProductFaqs;

  const getEnableBlockReason = (productId: string): string | null => {
    const hasPaymentProvider = hasPaymentProviders(productId);
    const hasProviderPlatform = hasProviderPlatforms(productId);

    if (!hasPaymentProvider && !hasProviderPlatform) {
      return 'Assign at least one payment provider and one provider platform before enabling';
    }
    if (!hasPaymentProvider) {
      return 'Assign at least one payment provider before enabling';
    }
    if (!hasProviderPlatform) {
      return 'Assign at least one provider platform before enabling';
    }
    return null;
  };

  // Helper to check if product can be enabled
  const canEnableProduct = (product: Product): boolean => {
    return hasPaymentProviders(product.id) && hasProviderPlatforms(product.id);
  };

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};
    
    if (!formData.name.trim()) {
      errors.name = 'Product name is required';
    } else if (formData.name.trim().length > 100) {
      errors.name = 'Name must be 100 characters or less';
    }
    
    if (formData.sku && formData.sku.length > 50) {
      errors.sku = 'SKU must be 50 characters or less';
    }

    if (formData.description && formData.description.length > 500) {
      errors.description = 'Description must be 500 characters or less';
    }
    
    if (!formData.price.trim()) {
      errors.price = 'Price is required';
    } else {
      const priceNum = parseFloat(formData.price);
      if (isNaN(priceNum) || priceNum < 0) {
        errors.price = 'Price must be a valid positive number';
      }
    }
    
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const createMutation = useMutation({
    mutationFn: async (data: ProductFormData) => {
      if (!currentTenantId) throw new Error('No tenant selected');
      
      const priceCents = Math.round(parseFloat(data.price) * 100);
      
      const { data: product, error } = await supabase
        .from('products')
        .insert([{
          name: data.name.trim(),
          sku: data.sku.trim() || null,
          description: data.description.trim() || null,
          terms_and_conditions_html: toNullableRichTextHtml(data.terms_and_conditions_html),
          price_cents: priceCents,
          tenant_id: currentTenantId,
        }])
        .select()
        .single();

      if (error) throw error;

      // Sync to payment providers (blocking - will throw on failure)
      try {
        await syncProductToProviders('create', {
          id: product.id,
          name: product.name,
          description: product.description,
          price_cents: product.price_cents,
          payment_type: product.payment_type || 'one_time',
          subscription_interval: product.subscription_interval || null,
          subscription_interval_count: product.subscription_interval_count || null,
          subscription_renewal_lead_days: product.subscription_renewal_lead_days ?? 0,
          sku: product.sku,
          image_url: product.image_url,
        }, currentTenantId);
      } catch (syncError) {
        // Rollback: delete the product if sync fails
        await supabase.from('products').delete().eq('id', product.id);
        throw syncError;
      }

      return product;
    },
    onSuccess: (product) => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      logAction({
        action: 'create',
        entityType: 'product',
        entityId: product.id,
        afterData: product,
      });
      toast.success('Product created and synced to payment providers');
      setIsCreateDialogOpen(false);
      setFormData(emptyFormData);
      setFormErrors({});
      navigate(ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(':id', product.id));
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to create product');
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: ProductFormData }) => {
      if (!currentTenantId) throw new Error('No tenant selected');
      
      const beforeProduct = products.find((p) => p.id === id);
      const priceCents = Math.round(parseFloat(data.price) * 100);
      
      const { data: product, error } = await supabase
        .from('products')
        .update({
          name: data.name.trim(),
          sku: data.sku.trim() || null,
          description: data.description.trim() || null,
          terms_and_conditions_html: toNullableRichTextHtml(data.terms_and_conditions_html),
          price_cents: priceCents,
        })
        .eq('id', id)
        .select()
        .single();

      if (error) throw error;

      // Sync to payment providers (blocking - will throw on failure)
      try {
        await syncProductToProviders('update', {
          id: product.id,
          name: product.name,
          description: product.description,
          price_cents: product.price_cents,
          payment_type: product.payment_type || 'one_time',
          subscription_interval: product.subscription_interval || null,
          subscription_interval_count: product.subscription_interval_count || null,
          subscription_renewal_lead_days: product.subscription_renewal_lead_days ?? 0,
          sku: product.sku,
          image_url: product.image_url,
        }, currentTenantId);
      } catch (syncError) {
        // Rollback: restore the original product data
        if (beforeProduct) {
          await supabase.from('products').update({
            name: beforeProduct.name,
            sku: beforeProduct.sku,
            description: beforeProduct.description,
            terms_and_conditions_html: beforeProduct.terms_and_conditions_html,
            price_cents: beforeProduct.price_cents,
          }).eq('id', id);
        }
        throw syncError;
      }

      return { product, beforeData: beforeProduct };
    },
    onSuccess: ({ product, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      logAction({
        action: 'update',
        entityType: 'product',
        entityId: product.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: product as unknown as Record<string, unknown>,
      });
      toast.success('Product updated and synced to payment providers');
      setIsEditDialogOpen(false);
      setSelectedProduct(null);
      setFormData(emptyFormData);
      setFormErrors({});
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update product');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => {
      const beforeProduct = products.find((p) => p.id === id);
      
      const { error } = await supabase
        .from('products')
        .delete()
        .eq('id', id);

      if (error) throw error;
      return { id, beforeData: beforeProduct };
    },
    onSuccess: ({ id, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      logAction({
        action: 'delete',
        entityType: 'product',
        entityId: id,
        beforeData: beforeData as unknown as Record<string, unknown>,
      });
      toast.success('Product deleted successfully');
      setIsDeleteDialogOpen(false);
      setSelectedProduct(null);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to delete product');
    },
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ id, is_enabled }: { id: string; is_enabled: boolean }) => {
      const beforeProduct = products.find((p) => p.id === id);
      
      // Validate before enabling
      if (is_enabled && beforeProduct) {
        const enableBlockReason = getEnableBlockReason(id);
        if (enableBlockReason) {
          throw new Error(enableBlockReason);
        }
      }
      
      const { data, error } = await supabase
        .from('products')
        .update({ is_enabled })
        .eq('id', id)
        .select()
        .single();

      if (error) throw error;
      return { product: data, beforeData: beforeProduct };
    },
    onSuccess: ({ product, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      logAction({
        action: 'update',
        entityType: 'product',
        entityId: product.id,
        beforeData: { is_enabled: beforeData?.is_enabled },
        afterData: { is_enabled: product.is_enabled },
      });
      toast.success(`Product ${product.is_enabled ? 'enabled' : 'disabled'}`);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update product');
    },
  });

  const formatPrice = (cents: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(cents / 100);
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      toast.error('Please fix the validation errors');
      return;
    }
    
    createMutation.mutate(formData);
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedProduct) return;
    
    if (!validateForm()) {
      toast.error('Please fix the validation errors');
      return;
    }
    
    updateMutation.mutate({ id: selectedProduct.id, data: formData });
  };

  const handleDeleteConfirm = () => {
    if (!selectedProduct) return;
    deleteMutation.mutate(selectedProduct.id);
  };

  const handleProductStatusChange = (product: Product, checked: boolean) => {
    if (checked) {
      const enableBlockReason = getEnableBlockReason(product.id);
      if (enableBlockReason) {
        toast.error(enableBlockReason);
        return;
      }

      const missingInfo = getMissingProductInformation(product);
      if (missingInfo.length > 0) {
        setAvailabilityWarning({ product, missingInfo });
        return;
      }
    }

    toggleMutation.mutate({ id: product.id, is_enabled: checked });
  };

  const handleConfirmEnableWithMissingInfo = () => {
    if (!availabilityWarning) return;

    toggleMutation.mutate({
      id: availabilityWarning.product.id,
      is_enabled: true,
    });
    setAvailabilityWarning(null);
  };

  const columns: Column<Product>[] = [
    {
      key: 'name',
      header: 'Product',
      cell: (product) => (
        <div 
          className="flex items-center gap-3 cursor-pointer hover:opacity-80 transition-opacity"
          onClick={() => navigate(ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(':id', product.id))}
        >
          {product.image_url ? (
            <img 
              src={product.image_url} 
              alt={product.name}
              className="h-10 w-10 rounded-lg object-cover border"
            />
          ) : (
            <div className="h-10 w-10 rounded-lg bg-muted flex items-center justify-center">
              <Package className="h-5 w-5 text-muted-foreground" />
            </div>
          )}
          <div>
            <p className="font-medium hover:underline">{product.name}</p>
            {product.sku && (
              <p className="text-sm text-muted-foreground font-mono">{product.sku}</p>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'categories',
      header: 'Categories',
      cell: (product) => <ProductCategoryBadges productId={product.id} />,
    },
    {
      key: 'medications',
      header: 'Medications',
      cell: (product) => {
        const meds = productMedications
          .filter((m) => m.product_id === product.id)
          .map((m) => m.medication?.title)
          .filter(Boolean) as string[];
        if (meds.length === 0) return <span className="text-muted-foreground text-sm">—</span>;
        return (
          <div className="flex flex-wrap gap-1">
            {meds.map((title) => (
              <Badge key={title} variant="outline" className="text-xs">{title}</Badge>
            ))}
          </div>
        );
      },
    },
    {
      key: 'price',
      header: 'Price',
      cell: (product) => {
        const price = formatPrice(product.price_cents);
        if (product.payment_type === 'subscription' && product.subscription_interval && product.subscription_interval_count) {
          const interval = product.subscription_interval_count === 1 
            ? product.subscription_interval 
            : `${product.subscription_interval_count} ${product.subscription_interval}s`;
          return (
            <div>
              <span>{price}</span>
              <span className="text-muted-foreground text-sm"> / {interval}</span>
            </div>
          );
        }
        return price;
      },
    },
    {
      key: 'payment_type',
      header: 'Type',
      cell: (product) => (
        <Badge variant={product.payment_type === 'subscription' ? 'default' : 'secondary'} className="gap-1">
          {product.payment_type === 'subscription' ? (
            <>
              <RefreshCw className="h-3 w-3" />
              Subscription
            </>
          ) : (
            <>
              <CreditCard className="h-3 w-3" />
              One-time
            </>
          )}
        </Badge>
      ),
    },
    {
      key: 'status',
      header: 'Enabled',
      cell: (product) => {
        const enableBlockReason = getEnableBlockReason(product.id);
        return (
          <Switch
            checked={product.is_enabled}
            onCheckedChange={(checked) => handleProductStatusChange(product, checked)}
            disabled={!canEditProducts || toggleMutation.isPending || isProductReadinessLoading}
            aria-label={`Toggle ${product.name} enabled state`}
            title={!product.is_enabled && enableBlockReason ? enableBlockReason : undefined}
          />
        );
      },
    },
    {
      key: 'created',
      header: 'Created',
      cell: (product) => dateTime(product.created_at).format('MMM D, YYYY'),
    },
    {
      key: 'actions',
      header: '',
      cell: (product) => (
        <Button
          variant="outline"
          size="sm"
          className="h-8 w-8 p-0"
          aria-label={`View details for ${product.name}`}
          title="View details"
          onClick={() => navigate(ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(':id', product.id))}
        >
          <Eye className="h-4 w-4" />
        </Button>
      ),
      className: 'w-12',
    },
  ];

  const renderForm = (onSubmit: (e: React.FormEvent) => void, isEdit: boolean) => (
    <form onSubmit={onSubmit}>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Product' : 'Add New Product'}</DialogTitle>
        <DialogDescription>
          {isEdit
            ? 'Update product details.'
            : 'Create a new product for your catalog.'}
        </DialogDescription>
      </DialogHeader>
      <div className="grid gap-4 py-4">
        <div className="space-y-2">
          <Label htmlFor="name">Product Name *</Label>
          <Input
            id="name"
            value={formData.name}
            onChange={(e) => {
              const newName = e.target.value;
              // Auto-generate SKU from name only during create (not edit)
              if (!isEdit) {
                setFormData({
                  ...formData,
                  name: newName,
                  sku: generateSkuFromName(newName),
                });
              } else {
                setFormData({ ...formData, name: newName });
              }
            }}
            maxLength={100}
            placeholder="Enter product name"
            className={formErrors.name ? 'border-destructive' : ''}
          />
          {formErrors.name && <p className="text-sm text-destructive">{formErrors.name}</p>}
        </div>
        <div className="space-y-2">
          <Label htmlFor="sku">SKU (optional)</Label>
          <Input
            id="sku"
            value={formData.sku}
            onChange={(e) => setFormData({ ...formData, sku: e.target.value })}
            maxLength={50}
            placeholder="e.g., PROD-001"
            className={formErrors.sku ? 'border-destructive' : ''}
          />
          {formErrors.sku && <p className="text-sm text-destructive">{formErrors.sku}</p>}
        </div>
        <div className="space-y-2">
          <Label htmlFor="description">Description (optional)</Label>
          <Textarea
            id="description"
            value={formData.description}
            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
            maxLength={500}
            placeholder="Enter product description"
            rows={3}
            className={formErrors.description ? 'border-destructive' : ''}
          />
          {formErrors.description && <p className="text-sm text-destructive">{formErrors.description}</p>}
        </div>
        <div className="space-y-2">
          <Label htmlFor="terms_and_conditions_html">Terms and Conditions (optional)</Label>
          <HtmlEditor
            id="terms_and_conditions_html"
            value={formData.terms_and_conditions_html}
            onChange={(value) => setFormData({ ...formData, terms_and_conditions_html: value })}
            placeholder="Add the terms and conditions for this product..."
            minHeightClassName="h-40 overflow-y-auto resize-y"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="price">Price (USD) *</Label>
          <Input
            id="price"
            type="number"
            step="0.01"
            min="0"
            value={formData.price}
            onChange={(e) => setFormData({ ...formData, price: e.target.value })}
            placeholder="0.00"
            className={formErrors.price ? 'border-destructive' : ''}
          />
          {formErrors.price && <p className="text-sm text-destructive">{formErrors.price}</p>}
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
            setFormErrors({});
          }}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={createMutation.isPending || updateMutation.isPending}>
          {(createMutation.isPending || updateMutation.isPending) && (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          )}
          {isEdit ? 'Save Changes' : 'Create Product'}
        </Button>
      </DialogFooter>
    </form>
  );

  return (
    <>
      <PageHeader
        title="Products"
        description="Manage your product catalog"
        actions={canEditProducts ? (
          <Dialog open={isCreateDialogOpen} onOpenChange={(open) => {
            setIsCreateDialogOpen(open);
            if (!open) {
              setFormData(emptyFormData);
              setFormErrors({});
            }
          }}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Add Product
              </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
              {renderForm(handleCreateSubmit, false)}
            </DialogContent>
          </Dialog>
        ) : undefined}
      />

      <DataTable
        columns={columns}
        data={products}
        isLoading={isLoading}
        searchPlaceholder="Search products..."
        searchValue={search}
        onSearchChange={(value) => {
          setSearch(value);
          setPage(1);
        }}
        emptyMessage="No products found"
        page={page}
        pageSize={PAGE_SIZE}
        total={totalProducts}
        onPageChange={setPage}
      />

      {/* Edit Dialog */}
      <Dialog open={isEditDialogOpen} onOpenChange={(open) => {
        setIsEditDialogOpen(open);
        if (!open) {
          setSelectedProduct(null);
          setFormData(emptyFormData);
          setFormErrors({});
        }
      }}>
        <DialogContent className="max-h-[90vh] overflow-y-auto">
          {renderForm(handleEditSubmit, true)}
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Product</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{selectedProduct?.name}"? This action cannot be undone.
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

      <AlertDialog
        open={!!availabilityWarning}
        onOpenChange={(open) => {
          if (!open) setAvailabilityWarning(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Make product available?</AlertDialogTitle>
            <AlertDialogDescription>
              "{availabilityWarning?.product.name}" is missing information that customers may see.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="rounded-md border bg-muted/20 p-3">
            <p className="text-sm font-medium">Missing information</p>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
              {availabilityWarning?.missingInfo.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmEnableWithMissingInfo}
              disabled={toggleMutation.isPending}
            >
              {toggleMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Confirm
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function Products() {
  return (
    <AdminLayout variant="tenant">
      <ProductsContent />
    </AdminLayout>
  );
}
