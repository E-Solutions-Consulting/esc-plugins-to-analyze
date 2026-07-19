import { useOrderStatusHistory } from "@/hooks/usePatientOrderStatus";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import {
  CheckCircle2,
  Circle,
  Clock,
  AlertCircle,
  Package,
  Truck,
  Home,
  CreditCard,
  FileCheck,
  XCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { dateTime } from "@/lib/dayjs";

interface OrderTrackingTimelineProps {
  orderId: string;
  className?: string;
  showHeader?: boolean;
}

const STATUS_ICONS: Record<
  string,
  React.ComponentType<{ className?: string }>
> = {
  order_created: Package,
  payment_received: CreditCard,
  payment_failed: XCircle,
  provider_review: FileCheck,
  provider_approved: CheckCircle2,
  pharmacy_processing: Package,
  shipped: Truck,
  out_for_delivery: Truck,
  delivered: Home,
  cancelled: XCircle,
  on_hold: AlertCircle,
};

function getStatusIcon(statusKey: string) {
  return STATUS_ICONS[statusKey] || Circle;
}

export function OrderTrackingTimeline({
  orderId,
  className,
  showHeader = true,
}: OrderTrackingTimelineProps) {
  const {
    data: statusHistory,
    isLoading,
    error,
  } = useOrderStatusHistory(orderId);

  if (isLoading) {
    return (
      <Card className={className}>
        {showHeader && (
          <CardHeader>
            <CardTitle>Order Tracking</CardTitle>
            <CardDescription>Loading status history...</CardDescription>
          </CardHeader>
        )}
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="flex gap-4">
                <Skeleton className="h-8 w-8 rounded-full" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-3 w-48" />
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card className={className}>
        {showHeader && (
          <CardHeader>
            <CardTitle>Order Tracking</CardTitle>
          </CardHeader>
        )}
        <CardContent>
          <div className="flex items-center gap-2 text-destructive">
            <AlertCircle className="h-5 w-5" />
            <span>Failed to load order tracking</span>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!statusHistory || statusHistory.history.length === 0) {
    return (
      <Card className={className}>
        {showHeader && (
          <CardHeader>
            <CardTitle>Order Tracking</CardTitle>
            <CardDescription>
              Order #{statusHistory?.order_number}
            </CardDescription>
          </CardHeader>
        )}
        <CardContent>
          <p className="text-muted-foreground text-center py-4">
            No tracking information available yet
          </p>
        </CardContent>
      </Card>
    );
  }

  const latestStatus = statusHistory.history[statusHistory.history.length - 1];

  return (
    <Card className={className}>
      {showHeader && (
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Order Tracking</CardTitle>
              <CardDescription>
                Order #{statusHistory.order_number}
              </CardDescription>
            </div>
            {latestStatus?.status?.action_required && (
              <Badge variant="destructive" className="flex items-center gap-1">
                <AlertCircle className="h-3 w-3" />
                Action Required
              </Badge>
            )}
          </div>
        </CardHeader>
      )}
      <CardContent>
        <div className="relative">
          {/* Timeline line */}
          <div className="absolute left-4 top-4 bottom-4 w-0.5 bg-border" />

          <div className="space-y-0">
            {statusHistory.history.map((entry, index) => {
              const isLatest = index === statusHistory.history.length - 1;
              const isCompleted = !isLatest;
              const statusKey = entry.status?.key || "unknown";
              const StatusIcon = getStatusIcon(statusKey);
              const isFinal = entry.status?.is_final;
              const isActionRequired = entry.status?.action_required;

              return (
                <div
                  key={entry.id}
                  className={cn(
                    "relative flex gap-4 pb-6 last:pb-0",
                    isLatest && "font-medium",
                  )}
                >
                  {/* Timeline dot/icon */}
                  <div
                    className={cn(
                      "relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2",
                      isLatest &&
                        !isFinal &&
                        "bg-primary border-primary text-primary-foreground",
                      isLatest &&
                        isFinal &&
                        "bg-green-500 border-green-500 text-white",
                      isLatest &&
                        isActionRequired &&
                        "bg-destructive border-destructive text-destructive-foreground",
                      isCompleted &&
                        "bg-muted border-muted-foreground/30 text-muted-foreground",
                    )}
                  >
                    {isCompleted ? (
                      <CheckCircle2 className="h-4 w-4" />
                    ) : (
                      <StatusIcon className="h-4 w-4" />
                    )}
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0 pt-0.5">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span
                        className={cn(
                          "text-sm",
                          isLatest
                            ? "text-foreground"
                            : "text-muted-foreground",
                        )}
                      >
                        {entry.status?.label || "Unknown Status"}
                      </span>
                      {isLatest && (
                        <Badge variant="secondary" className="text-xs">
                          Current
                        </Badge>
                      )}
                      {isFinal && isLatest && (
                        <Badge
                          variant="outline"
                          className="text-xs text-green-600 border-green-600"
                        >
                          Complete
                        </Badge>
                      )}
                    </div>

                    {entry.status?.description && (
                      <p
                        className={cn(
                          "text-sm mt-0.5",
                          isLatest
                            ? "text-muted-foreground"
                            : "text-muted-foreground/70",
                        )}
                      >
                        {entry.status.description}
                      </p>
                    )}

                    <div className="flex items-center gap-1 mt-1 text-xs text-muted-foreground">
                      <Clock className="h-3 w-3" />
                      <time dateTime={entry.timestamp}>
                        {dateTime(entry.timestamp).format("MMM D, YYYY h:mm A")}
                      </time>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
