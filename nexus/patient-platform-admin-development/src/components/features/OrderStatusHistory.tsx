import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { ScrollArea } from "@/components/ui/scroll-area";
import { History } from "lucide-react";
import { dateTime } from "@/lib/dayjs";

interface OrderStatusHistoryProps {
  orderId: string;
}

export function OrderStatusHistory({ orderId }: OrderStatusHistoryProps) {
  const { data: history, isLoading } = useQuery({
    queryKey: ["order-status-history", orderId],
    queryFn: async () => {
      const { data, error } = await supabase.rpc(
        "list_order_status_history",
        { p_order_id: orderId },
      );

      if (error) throw error;
      return (data || []).map((entry) => ({
        id: entry.id,
        order_id: entry.order_id,
        status_id: entry.status_id,
        changed_by: entry.changed_by,
        changed_by_email: entry.changed_by_email,
        notes: entry.notes,
        created_at: entry.created_at,
        order_statuses: {
          status_key: entry.status_key,
          admin_status_label: entry.admin_status_label,
          patient_status_label: entry.patient_status_label,
        },
      })) satisfies OrderStatusHistoryEntry[];
    },
    enabled: !!orderId,
  });

  const filteredHistory = history?.filter((entry, index, entries) => {
    if (index === 0) return true;
    const prevEntry = entries[index - 1];
    const isSameStatus =
      entry.order_statuses?.status_key ===
      prevEntry?.order_statuses?.status_key;
    const hasNoNotes = !entry.notes?.trim();
    return !(isSameStatus && hasNoNotes);
  });

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <History className="h-5 w-5 text-primary" />
            </div>
            <div>
              <CardTitle>Status History</CardTitle>
              <CardDescription>
                All status changes for this order
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-16 w-full" />
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <History className="h-5 w-5 text-primary" />
          </div>
          <div>
            <CardTitle>Status History</CardTitle>
            <CardDescription>
              {filteredHistory?.length || 0} status change
              {(filteredHistory?.length || 0) !== 1 ? "s" : ""} recorded
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {!filteredHistory || filteredHistory.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-4">
            No status changes recorded yet
          </p>
        ) : (
          <ScrollArea className="h-[300px] pr-4">
            <div className="relative">
              {/* Timeline line */}
              <div className="absolute left-[15px] top-2 bottom-2 w-0.5 bg-border" />

              <div className="space-y-4">
                {filteredHistory.map((entry, index) => (
                  <div key={entry.id} className="relative flex gap-4 pl-10">
                    {/* Timeline dot */}
                    <div
                      className={`absolute left-[11px] top-1.5 h-2 w-2 rounded-full ${
                        index === 0 ? "bg-primary" : "bg-muted-foreground"
                      }`}
                    />

                    <div className="flex-1 min-w-0 pb-4">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span
                          className={`font-medium ${index === 0 ? "text-primary" : ""}`}
                        >
                          {entry.order_statuses?.admin_status_label ||
                            "Unknown Status"}
                        </span>
                        {index === 0 && (
                          <span className="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded">
                            Current
                          </span>
                        )}
                      </div>
                      <div className="text-sm text-muted-foreground mt-0.5">
                        {dateTime(entry.created_at).format(
                          "MMM D, YYYY h:mm A",
                        )}
                      </div>
                      {entry.changed_by_email && (
                        <div className="text-xs text-muted-foreground mt-1">
                          Changed by: {entry.changed_by_email}
                        </div>
                      )}
                      {entry.notes && (
                        <div className="text-sm mt-2 p-2 bg-muted rounded">
                          {entry.notes}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </ScrollArea>
        )}
      </CardContent>
    </Card>
  );
}
