import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import {
  canPerformAction,
  filterOrderStatusesForPermissions,
} from '@/lib/admin-permissions';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

interface OrderStatus {
  id: string;
  status_key: string;
  admin_status_label: string;
  display_order: number;
  is_active: boolean;
  is_terminal: boolean;
}

interface OrderStatusSelectProps {
  orderId: string;
  currentStatusId: string | null;
  onStatusChange?: () => void;
}

const ORDER_LIFECYCLE_TRIGGER_STATUS_KEY = 'provider_order_creation_pending';

export function OrderStatusSelect({ orderId, currentStatusId, onStatusChange }: OrderStatusSelectProps) {
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();

  // Fetch all available statuses
  const { data: statuses, isLoading: statusesLoading } = useQuery({
    queryKey: ['order-statuses'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('order_statuses')
        .select('id, status_key, admin_status_label, display_order, is_active, is_terminal')
        .eq('is_active', true)
        .order('display_order', { ascending: true });

      if (error) throw error;
      return data as OrderStatus[];
    },
  });

  const updateStatusMutation = useMutation({
    mutationFn: async (newStatus: OrderStatus) => {
      const {
        data: { session },
      } = await supabase.auth.getSession();

      if (!session) {
        throw new Error('Not authenticated');
      }

      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/admin/orders/${orderId}/status`,
        {
          method: 'PATCH',
          headers: {
            Authorization: `Bearer ${session.access_token}`,
            apikey: import.meta.env.VITE_SUPABASE_ANON_KEY,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ status_id: newStatus.id }),
        },
      );

      const responseBody = await response.json().catch(() => null);
      if (!response.ok) {
        const errorMessage =
          responseBody?.error?.message ||
          responseBody?.message ||
          'Failed to update order status';
        throw new Error(errorMessage);
      }

      let lifecycleTriggerError: string | null = null;

      if (newStatus.status_key === ORDER_LIFECYCLE_TRIGGER_STATUS_KEY) {
        try {
          const response = await fetch(
            `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/order-lifecycle?orderId=${orderId}`,
            {
              method: 'GET',
              headers: {
                apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
                'Content-Type': 'application/json',
              },
            },
          );

          if (!response.ok) {
            const errorText = await response.text();
            lifecycleTriggerError = errorText || 'Request failed';
          }
        } catch (error) {
          lifecycleTriggerError = error instanceof Error ? error.message : 'Request failed';
        }
      }

      return {
        data: responseBody?.data ?? {
          id: orderId,
          status_id: newStatus.id,
          status: newStatus.status_key,
        },
        lifecycleTriggerError,
      };
    },
    onSuccess: ({ data, lifecycleTriggerError }) => {
      queryClient.invalidateQueries({ queryKey: ['order', orderId] });
      queryClient.invalidateQueries({ queryKey: ['order-status-history', orderId] });
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      
      logAction({
        action: 'update_status',
        entityType: 'order',
        entityId: orderId,
        afterData: { status_id: data.status_id },
        tenantId: currentTenantId,
      });

      if (lifecycleTriggerError) {
        toast.warning(`Order status updated, but lifecycle trigger failed: ${lifecycleTriggerError}`);
      } else {
        toast.success('Order status updated');
      }
      onStatusChange?.();
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update status');
    },
  });

  const permissionContext = {
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    currentTenantId,
  };
  const canChangeStatus = canPerformAction(permissionContext, 'order:status');
  const availableStatuses = filterOrderStatusesForPermissions(
    permissionContext,
    statuses || [],
  );

  const handleStatusChange = (statusId: string) => {
    const nextStatus = availableStatuses.find((status) => status.id === statusId);

    if (canChangeStatus && statusId !== currentStatusId && nextStatus) {
      updateStatusMutation.mutate(nextStatus);
    }
  };

  const currentStatus = statuses?.find(s => s.id === currentStatusId);

  return (
    <div className="flex items-center gap-4">
      <Label>Update Status:</Label>
      <Select
        value={currentStatusId || ''}
        onValueChange={handleStatusChange}
        disabled={updateStatusMutation.isPending || statusesLoading || !canChangeStatus}
      >
        <SelectTrigger className="w-64">
          <SelectValue placeholder="Select status...">
            {currentStatus?.admin_status_label || 'Select status...'}
          </SelectValue>
        </SelectTrigger>
        <SelectContent>
          {availableStatuses.map((status) => (
            <SelectItem key={status.id} value={status.id}>
              <div className="flex items-center gap-2">
                <span>{status.admin_status_label}</span>
                {status.is_terminal && (
                  <span className="text-xs text-muted-foreground">(Terminal)</span>
                )}
              </div>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {updateStatusMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
    </div>
  );
}
