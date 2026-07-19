import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface StatusBadgeProps {
  status: string;
  className?: string;
  size?: 'default' | 'sm';
}

const statusConfig: Record<string, { label: string; className: string }> = {
  // Patient access status
  active: { label: 'Active', className: 'status-active' },
  suspended: { label: 'Suspended', className: 'status-pending' },
  deactivated: { label: 'Deactivated', className: 'status-cancelled' },
  
  // Order status
  pending: { label: 'Pending', className: 'status-pending' },
  pending_validation: { label: 'Pending Validation', className: 'status-pending' },
  processing: { label: 'Processing', className: 'status-processing' },
  shipped: { label: 'Shipped', className: 'status-shipped' },
  delivered: { label: 'Delivered', className: 'status-delivered' },
  cancelled: { label: 'Cancelled', className: 'status-cancelled' },
  pending_cancellation: { label: 'Pending Cancellation', className: 'status-pending' },
  paused: { label: 'Paused', className: 'status-paused' },
  
  // Tenant status
  inactive: { label: 'Inactive', className: 'status-paused' },
  
  // Readiness status
  not_started: { label: 'Not Started', className: 'status-not_started' },
  in_progress: { label: 'In Progress', className: 'status-in_progress' },
  ready: { label: 'Ready', className: 'status-ready' },
};

export function StatusBadge({ status, className, size = 'default' }: StatusBadgeProps) {
  const config = statusConfig[status] || { label: status, className: 'bg-gray-100 text-gray-800' };
  
  return (
    <Badge 
      variant="secondary" 
      className={cn(
        'font-medium', 
        config.className, 
        size === 'sm' && 'text-xs px-1.5 py-0',
        className
      )}
    >
      {config.label}
    </Badge>
  );
}
