import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { 
  CheckCircle2, 
  Clock, 
  AlertCircle, 
  Package, 
  Truck, 
  Home,
  CreditCard,
  FileCheck,
  XCircle,
  Pause
} from 'lucide-react';

interface OrderStatusBadgeProps {
  statusKey: string;
  label: string;
  actionRequired?: boolean;
  isFinal?: boolean;
  size?: 'sm' | 'default' | 'lg';
  showIcon?: boolean;
  className?: string;
}

const STATUS_CONFIG: Record<string, { 
  icon: React.ComponentType<{ className?: string }>;
  variant: 'default' | 'secondary' | 'destructive' | 'outline';
  className?: string;
}> = {
  order_created: { 
    icon: Package, 
    variant: 'secondary',
  },
  payment_received: { 
    icon: CreditCard, 
    variant: 'secondary',
    className: 'bg-blue-100 text-blue-700 hover:bg-blue-100/80 dark:bg-blue-900 dark:text-blue-300'
  },
  payment_failed: { 
    icon: XCircle, 
    variant: 'destructive',
  },
  provider_review: { 
    icon: FileCheck, 
    variant: 'secondary',
    className: 'bg-yellow-100 text-yellow-700 hover:bg-yellow-100/80 dark:bg-yellow-900 dark:text-yellow-300'
  },
  provider_approved: { 
    icon: CheckCircle2, 
    variant: 'secondary',
    className: 'bg-green-100 text-green-700 hover:bg-green-100/80 dark:bg-green-900 dark:text-green-300'
  },
  pharmacy_processing: { 
    icon: Package, 
    variant: 'secondary',
    className: 'bg-purple-100 text-purple-700 hover:bg-purple-100/80 dark:bg-purple-900 dark:text-purple-300'
  },
  shipped: { 
    icon: Truck, 
    variant: 'secondary',
    className: 'bg-indigo-100 text-indigo-700 hover:bg-indigo-100/80 dark:bg-indigo-900 dark:text-indigo-300'
  },
  out_for_delivery: { 
    icon: Truck, 
    variant: 'secondary',
    className: 'bg-cyan-100 text-cyan-700 hover:bg-cyan-100/80 dark:bg-cyan-900 dark:text-cyan-300'
  },
  delivered: { 
    icon: Home, 
    variant: 'secondary',
    className: 'bg-green-100 text-green-700 hover:bg-green-100/80 dark:bg-green-900 dark:text-green-300'
  },
  cancelled: { 
    icon: XCircle, 
    variant: 'destructive',
  },
  on_hold: { 
    icon: Pause, 
    variant: 'secondary',
    className: 'bg-orange-100 text-orange-700 hover:bg-orange-100/80 dark:bg-orange-900 dark:text-orange-300'
  },
};

const DEFAULT_CONFIG: { 
  icon: React.ComponentType<{ className?: string }>;
  variant: 'default' | 'secondary' | 'destructive' | 'outline';
  className?: string;
} = {
  icon: Clock,
  variant: 'secondary' as const,
};

export function OrderStatusBadge({ 
  statusKey, 
  label, 
  actionRequired,
  isFinal,
  size = 'default',
  showIcon = true,
  className,
}: OrderStatusBadgeProps) {
  const config = STATUS_CONFIG[statusKey] || DEFAULT_CONFIG;
  const Icon = config.icon;

  const sizeClasses = {
    sm: 'text-xs px-2 py-0.5',
    default: 'text-sm px-2.5 py-0.5',
    lg: 'text-base px-3 py-1',
  };

  const iconSizes = {
    sm: 'h-3 w-3',
    default: 'h-3.5 w-3.5',
    lg: 'h-4 w-4',
  };

  if (actionRequired) {
    return (
      <Badge 
        variant="destructive"
        className={cn(sizeClasses[size], 'flex items-center gap-1', className)}
      >
        {showIcon && <AlertCircle className={iconSizes[size]} />}
        {label}
      </Badge>
    );
  }

  if (isFinal && statusKey === 'delivered') {
    return (
      <Badge 
        variant="secondary"
        className={cn(
          sizeClasses[size], 
          'flex items-center gap-1 bg-green-100 text-green-700 hover:bg-green-100/80 dark:bg-green-900 dark:text-green-300',
          className
        )}
      >
        {showIcon && <CheckCircle2 className={iconSizes[size]} />}
        {label}
      </Badge>
    );
  }

  return (
    <Badge 
      variant={config.variant}
      className={cn(sizeClasses[size], 'flex items-center gap-1', config.className, className)}
    >
      {showIcon && <Icon className={iconSizes[size]} />}
      {label}
    </Badge>
  );
}
