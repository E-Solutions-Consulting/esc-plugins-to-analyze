import { useMutation, useQueries, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from './useAuditLog';
import type { PaymentProvider } from './usePaymentProviders';

export interface TenantPaymentProviderWithDetails {
  id: string;
  tenant_id: string;
  payment_provider_id: string;
  is_enabled: boolean;
  settings: Record<string, string>;
  payment_provider: PaymentProvider;
}

export interface ProductPaymentProvider {
  id: string;
  product_id: string;
  tenant_payment_provider_id: string;
  is_enabled: boolean;
  created_at: string;
}

// Helper to execute raw queries bypassing TypeScript strict checking
async function rawQuery<T>(
  table: string,
  operation: 'select' | 'insert' | 'update' | 'delete',
  options?: {
    data?: Record<string, unknown> | Record<string, unknown>[];
    filter?: { column: string; value: unknown }[];
    select?: string;
    order?: string;
    single?: boolean;
  }
): Promise<{ data: T | null; error: Error | null }> {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let query = (supabase as any).from(table);

  switch (operation) {
    case 'select':
      query = query.select(options?.select || '*');
      if (options?.order) query = query.order(options.order);
      break;
    case 'insert':
      query = query.insert(options?.data).select();
      break;
    case 'update':
      query = query.update(options?.data).select();
      break;
    case 'delete':
      query = query.delete();
      break;
  }

  if (options?.filter) {
    for (const filter of options.filter) {
      query = query.eq(filter.column, filter.value);
    }
  }

  if (options?.single) {
    query = query.single();
  }

  const result = await query;
  return result as { data: T | null; error: Error | null };
}

export function useProductPaymentProviders(productId: string) {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const [
    { data: tenantProviders, isLoading: isLoadingTenantProviders }, 
    { data: productProviders, isLoading: isLoadingProductProviders }
  ] = useQueries({
    queries: [
      {
        queryKey: ['tenant-payment-providers-enabled', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data: tenantPaymentProviders, error: tenantProvidersError } = await rawQuery<
            Array<{
              id: string;
              tenant_id: string;
              payment_provider_id: string;
              is_enabled: boolean;
              settings: Record<string, string>;
            }>
          >('tenant_payment_providers', 'select', {
            filter: [
              { column: 'tenant_id', value: currentTenantId },
              { column: 'is_enabled', value: true },
            ],
          });

          if (tenantProvidersError) throw tenantProvidersError;
          if (!tenantPaymentProviders || tenantPaymentProviders.length === 0) return [];

          const providerIds = tenantPaymentProviders.map((provider) => provider.payment_provider_id);
          const { data: providers, error: providersError } = await rawQuery<PaymentProvider[]>(
            'payment_providers',
            'select',
            {
              filter: [{ column: 'is_active', value: true }],
            }
          );

          if (providersError) throw providersError;

          return tenantPaymentProviders
            .map((tenantPaymentProvider) => ({
              ...tenantPaymentProvider,
              payment_provider: providers?.find(
                (provider) => provider.id === tenantPaymentProvider.payment_provider_id
              ) as PaymentProvider,
            }))
            .filter(
              (tenantPaymentProvider) =>
                tenantPaymentProvider.payment_provider &&
                providerIds.includes(tenantPaymentProvider.payment_provider_id)
            );
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: ['product-payment-providers', productId],
        queryFn: async () => {
          if (!productId) return [];

          const { data, error } = await rawQuery<ProductPaymentProvider[]>(
            'product_payment_providers',
            'select',
            {
              filter: [{ column: 'product_id', value: productId }],
            }
          );

          if (error) throw error;
          return data || [];
        },
        enabled: !!productId,
      },
    ],
  });

  const providersWithAssignment =
    tenantProviders?.map((tenantPaymentProvider) => {
      const assignment = productProviders?.find(
        (productProvider) =>
          productProvider.tenant_payment_provider_id === tenantPaymentProvider.id
      );

      return {
        ...tenantPaymentProvider,
        isAssigned: !!assignment,
        assignmentEnabled: assignment?.is_enabled ?? false,
        assignmentId: assignment?.id,
      };
    }) || [];

  const toggleProductProvider = useMutation({
    mutationFn: async ({
      tenantPaymentProviderId,
      enabled,
    }: {
      tenantPaymentProviderId: string;
      enabled: boolean;
    }) => {
      const existingAssignment = productProviders?.find(
        (productProvider) =>
          productProvider.tenant_payment_provider_id === tenantPaymentProviderId
      );

      if (existingAssignment) {
        if (enabled) {
          const { data, error } = await rawQuery<ProductPaymentProvider>(
            'product_payment_providers',
            'update',
            {
              data: { is_enabled: true },
              filter: [{ column: 'id', value: existingAssignment.id }],
              single: true,
            }
          );

          if (error) throw error;
          return { data, beforeData: existingAssignment, action: 'update' as const };
        }

        const { error } = await rawQuery<null>('product_payment_providers', 'delete', {
          filter: [{ column: 'id', value: existingAssignment.id }],
        });

        if (error) throw error;
        return { data: null, beforeData: existingAssignment, action: 'delete' as const };
      }

      if (enabled) {
        const { data, error } = await rawQuery<ProductPaymentProvider>(
          'product_payment_providers',
          'insert',
          {
            data: {
              product_id: productId,
              tenant_payment_provider_id: tenantPaymentProviderId,
              is_enabled: true,
            },
            single: true,
          }
        );

        if (error) throw error;
        return { data, beforeData: null, action: 'create' as const };
      }

      return { data: null, beforeData: null, action: 'none' as const };
    },
    onSuccess: ({ data, beforeData, action }) => {
      queryClient.invalidateQueries({ queryKey: ['product-payment-providers', productId] });
      queryClient.invalidateQueries({ queryKey: ['product-payment-providers-count', productId] });
      queryClient.invalidateQueries({ queryKey: ['all-product-payment-providers', currentTenantId] });

      if (action !== 'none') {
        logAction({
          action,
          entityType: 'product_payment_provider',
          entityId: data?.id || beforeData?.id,
          beforeData: beforeData as unknown as Record<string, unknown>,
          afterData: data as unknown as Record<string, unknown>,
        });
      }

      toast.success(
        action === 'create' || action === 'update'
          ? 'Provider enabled for product'
          : 'Provider removed from product'
      );
    },
    onError: (error: Error) => {
      toast.error(`Failed to update provider: ${error.message}`);
    },
  });

  return {
    tenantProviders,
    productProviders,
    providersWithAssignment,
    isLoading: isLoadingTenantProviders || isLoadingProductProviders,
    toggleProductProvider,
  };
}
