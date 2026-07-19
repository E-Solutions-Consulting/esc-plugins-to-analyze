import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from './useAuditLog';
import { toast } from 'sonner';

// Helper to execute raw queries bypassing TypeScript strict checking
async function rawQuery<T>(
  table: string,
  operation: 'select' | 'insert' | 'update' | 'delete',
  options?: {
    data?: Record<string, unknown>;
    filter?: { column: string; value: unknown };
    order?: string;
    single?: boolean;
  }
): Promise<{ data: T | null; error: Error | null }> {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let query = (supabase as any).from(table);

  switch (operation) {
    case 'select':
      query = query.select('*');
      if (options?.order) query = query.order(options.order);
      break;
    case 'insert':
      query = query.insert(options?.data).select();
      break;
    case 'update':
      query = query.update(options?.data);
      break;
    case 'delete':
      query = query.delete();
      break;
  }

  if (options?.filter) {
    query = query.eq(options.filter.column, options.filter.value);
  }

  if (options?.single) {
    query = query.single();
  }

  const result = await query;
  return result as { data: T | null; error: Error | null };
}

export function usePaymentProviders() {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const { data: providers, isLoading, error } = useQuery({
    queryKey: ['payment-providers'],
    queryFn: async () => {
      const { data, error } = await rawQuery<PaymentProvider[]>('payment_providers', 'select', {
        order: 'name',
      });
      
      if (error) throw error;
      return data || [];
    },
  });

  const createProvider = useMutation({
    mutationFn: async (formData: PaymentProviderFormData) => {
      const insertData = {
        key: formData.key,
        name: formData.name,
        description: formData.description || null,
        logo_url: formData.logo_url || null,
        is_active: formData.is_active,
        required_settings: formData.required_settings,
      };

      const { data, error } = await rawQuery<PaymentProvider>('payment_providers', 'insert', {
        data: insertData,
        single: true,
      });

      if (error) throw error;
      return data;
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['payment-providers'] });
      logAction({
        action: 'create',
        entityType: 'payment_provider',
        entityId: data?.id,
        afterData: data as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Payment provider created successfully');
    },
    onError: (error: Error) => {
      toast.error(`Failed to create provider: ${error.message}`);
    },
  });

  const updateProvider = useMutation({
    mutationFn: async ({ id, ...formData }: PaymentProviderFormData & { id: string }) => {
      const { data: beforeData } = await rawQuery<PaymentProvider>('payment_providers', 'select', {
        filter: { column: 'id', value: id },
        single: true,
      });

      const updateData = {
        key: formData.key,
        name: formData.name,
        description: formData.description || null,
        logo_url: formData.logo_url || null,
        is_active: formData.is_active,
        required_settings: formData.required_settings,
      };

      const { data, error } = await rawQuery<PaymentProvider>('payment_providers', 'update', {
        data: updateData,
        filter: { column: 'id', value: id },
        single: true,
      });

      if (error) throw error;
      return { data, beforeData };
    },
    onSuccess: ({ data, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['payment-providers'] });
      logAction({
        action: 'update',
        entityType: 'payment_provider',
        entityId: data?.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: data as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Payment provider updated successfully');
    },
    onError: (error: Error) => {
      toast.error(`Failed to update provider: ${error.message}`);
    },
  });

  const deleteProvider = useMutation({
    mutationFn: async (id: string) => {
      const { data: beforeData } = await rawQuery<PaymentProvider>('payment_providers', 'select', {
        filter: { column: 'id', value: id },
        single: true,
      });

      const { error } = await rawQuery<null>('payment_providers', 'delete', {
        filter: { column: 'id', value: id },
      });

      if (error) throw error;
      return { id, beforeData };
    },
    onSuccess: ({ id, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['payment-providers'] });
      logAction({
        action: 'delete',
        entityType: 'payment_provider',
        entityId: id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Payment provider deleted successfully');
    },
    onError: (error: Error) => {
      toast.error(`Failed to delete provider: ${error.message}`);
    },
  });

  return {
    providers,
    isLoading,
    error,
    createProvider,
    updateProvider,
    deleteProvider,
  };
}
