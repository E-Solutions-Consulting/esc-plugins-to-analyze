import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface OrderStatus {
  id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  next_step_owner: string;
}

interface OrderStatusBadgeProps {
  status: OrderStatus | null | undefined;
  fallbackLabel?: string;
  className?: string;
  size?: 'default' | 'sm';
}

// Color mapping based on next_step_owner or terminal state
function getStatusStyle(status: OrderStatus | null | undefined): string {
  if (!status) return 'bg-muted text-muted-foreground';

  const key = status.status_key.toLowerCase();

  if (
    key.includes('error') ||
    key.includes('cancel') ||
    key.includes('reject') ||
    key.includes('fail') ||
    key.includes('denied')
  ) {
    return 'bg-destructive/10 text-destructive border-destructive/20';
  }

  if (status.is_terminal) {
    // Terminal states - check if it's a success or failure terminal
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20';
  }
  
  switch (status.next_step_owner) {
    case 'patient':
      return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
    case 'provider':
      return 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20';
    case 'pharmacy':
      return 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20';
    case 'carrier':
      return 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/20';
    case 'ops':
      return 'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20';
    case 'payment_provider':
      return 'bg-pink-500/10 text-pink-700 dark:text-pink-400 border-pink-500/20';
    case 'system':
    default:
      return 'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-500/20';
  }
}

export function OrderStatusBadge({ 
  status, 
  fallbackLabel = 'Unknown', 
  className, 
  size = 'default' 
}: OrderStatusBadgeProps) {
  const label = status?.admin_status_label || fallbackLabel;
  const style = getStatusStyle(status);
  
  return (
    <Badge 
      variant="outline" 
      className={cn(
        'font-medium border', 
        style,
        size === 'sm' && 'text-xs px-1.5 py-0',
        className
      )}
    >
      {label}
    </Badge>
  );
}
