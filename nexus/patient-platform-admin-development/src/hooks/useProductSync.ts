import { supabase } from '@/integrations/supabase/client';

/**
 * Syncs a product to all enabled payment providers for the tenant.
 * This should be called after creating or updating a product.
 * 
 * @param action - 'create' or 'update'
 * @param product - The product data to sync
 * @param tenantId - The tenant's ID
 * @returns SyncResponse with success status and per-provider results
 * @throws Error if sync fails and blocking is required
 */
export async function syncProductToProviders(
  action: 'create' | 'update',
  product: ProductSyncData,
  tenantId: string
): Promise<SyncResponse> {
  const { data, error } = await supabase.functions.invoke<SyncResponse>('sync-product', {
    body: {
      action,
      product,
      tenant_id: tenantId,
    },
  });

  if (error) {
    throw new Error(`Product sync failed: ${error.message}`);
  }

  if (!data) {
    throw new Error('No response from sync service');
  }

  if (!data.success) {
    throw new Error(data.error || 'Product sync failed for one or more providers');
  }

  return data;
}

/**
 * Hook-style wrapper for product sync that can be used in mutations.
 * Provides both the sync function and helper utilities.
 */
export function useProductSync() {
  const sync = async (
    action: 'create' | 'update',
    product: ProductSyncData,
    tenantId: string
  ): Promise<SyncResponse> => {
    return syncProductToProviders(action, product, tenantId);
  };

  return { syncProductToProviders: sync };
}
