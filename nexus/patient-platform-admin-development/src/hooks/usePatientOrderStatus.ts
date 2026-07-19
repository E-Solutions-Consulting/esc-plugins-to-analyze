import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useOrderStatusHistory(orderId: string | undefined) {
  return useQuery({
    queryKey: ['patient-order-status-history', orderId],
    queryFn: async (): Promise<OrderStatusHistoryResponse> => {
      const { data: { session } } = await supabase.auth.getSession();
      
      if (!session) {
        throw new Error('Not authenticated');
      }

      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/orders/${orderId}/status-history`,
        {
          headers: {
            'Authorization': `Bearer ${session.access_token}`,
            'apikey': import.meta.env.VITE_SUPABASE_ANON_KEY,
          },
        }
      );

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to fetch order status history');
      }

      const result = await response.json();
      return result.data;
    },
    enabled: !!orderId,
  });
}

export function useAllOrderStatuses() {
  return useQuery({
    queryKey: ['patient-order-statuses'],
    queryFn: async (): Promise<AllOrderStatusesResponse[]> => {
      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/order-statuses`,
        {
          headers: {
            'apikey': import.meta.env.VITE_SUPABASE_ANON_KEY,
          },
        }
      );

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to fetch order statuses');
      }

      const result = await response.json();
      return result.data;
    },
  });
}
