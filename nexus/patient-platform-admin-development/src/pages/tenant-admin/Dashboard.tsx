import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { OrderStatusBadge } from "@/components/features/OrderStatusBadge";
import {
  Users,
  ShoppingCart,
  TrendingUp,
  TrendingDown,
  ArrowRight,
  Package,
  Minus,
} from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { ROUTES } from "@/lib/constants";
import { dateTime } from "@/lib/dayjs";
import type { Database } from "@/integrations/supabase/types";

interface DashboardOrderStatus {
  id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  next_step_owner: string;
  expiration_timer_hours: number | null;
}

interface DashboardRecentOrder {
  id: string;
  order_number: string;
  created_at: string;
  patients: {
    first_name: string | null;
    last_name: string | null;
  } | null;
  product: {
    name: string | null;
  } | null;
  order_statuses: Omit<DashboardOrderStatus, "expiration_timer_hours"> | null;
}

interface DashboardOverdueStatus {
  status_id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  next_step_owner: string;
  expiration_timer_hours: number;
  overdue_count: number;
  previous_day_overdue_count: number;
  overdueDelta: number;
}

type DashboardSummary =
  Database["public"]["Functions"]["get_tenant_dashboard_summary"]["Returns"][number];

export default function TenantDashboard() {
  const { currentTenantId } = useAuth();
  const navigate = useNavigate();

  const { data: summary, isLoading: isSummaryLoading } = useQuery({
    queryKey: ["tenant-dashboard-summary", currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return null;

      const { data, error } = await supabase
        .rpc("get_tenant_dashboard_summary", {
          p_tenant_id: currentTenantId,
        })
        .single();

      if (error) throw error;

      return data as DashboardSummary;
    },
    enabled: !!currentTenantId,
    staleTime: 60_000,
  });

  const { data: overdueOrdersByStatus = [], isLoading: isOverdueLoading } =
    useQuery({
      queryKey: ["tenant-dashboard-overdue", currentTenantId],
      queryFn: async () => {
        if (!currentTenantId) return [];

        const { data, error } = await supabase.rpc(
          "get_dashboard_overdue_counts",
          { p_tenant_id: currentTenantId },
        );

        if (error) throw error;

        return (
          (data || []) as Array<{
            status_id: string;
            status_key: string;
            admin_status_label: string;
            is_terminal: boolean;
            next_step_owner: string;
            expiration_timer_hours: number;
            overdue_count: number;
            previous_day_overdue_count: number;
          }>
        ).map((row) => ({
          ...row,
          overdueDelta: row.overdue_count - row.previous_day_overdue_count,
        }));
      },
      enabled: !!currentTenantId,
      staleTime: 60_000,
    });

  const { data: recentOrders = [], isLoading: isRecentOrdersLoading } =
    useQuery({
      queryKey: ["tenant-dashboard-recent-orders", currentTenantId],
      queryFn: async () => {
        if (!currentTenantId) return [];

        const { data, error } = await supabase
          .from("orders")
          .select(
            `
            id,
            order_number,
            created_at,
            status_id,
            patients(first_name, last_name),
            product:products(name),
            order_statuses(id, status_key, admin_status_label, is_terminal, next_step_owner)
          `,
          )
          .eq("tenant_id", currentTenantId)
          .order("created_at", { ascending: false })
          .limit(5);

        if (error) throw error;

        return (data || []) as DashboardRecentOrder[];
      },
      enabled: !!currentTenantId,
      staleTime: 30_000,
    });

  const statCards = [
    {
      title: "Total Patients",
      value: summary?.total_patients || 0,
      description: `${summary?.active_patients || 0} active`,
      icon: Users,
      href: ROUTES.TENANT_ADMIN.PATIENTS,
    },
    {
      title: "Total Orders",
      value: summary?.total_orders || 0,
      description: `${summary?.pending_orders || 0} pending`,
      icon: ShoppingCart,
      href: ROUTES.TENANT_ADMIN.ORDERS,
    },
    {
      title: "Products",
      value: summary?.total_products || 0,
      description: `${summary?.enabled_products || 0} enabled`,
      icon: Package,
      href: ROUTES.TENANT_ADMIN.CATALOG.PRODUCTS,
    },
    {
      title: "Growth",
      value: "+12%",
      description: "From last month",
      icon: TrendingUp,
      href: "#",
    },
  ];

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Dashboard"
        description="Overview of your tenant operations"
      />

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
        {statCards.map((stat) => (
          <Card key={stat.title}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {stat.title}
              </CardTitle>
              <stat.icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              {isSummaryLoading ? (
                <Skeleton className="h-8 w-20" />
              ) : (
                <>
                  <div className="text-2xl font-bold">{stat.value}</div>
                  <p className="text-xs text-muted-foreground">
                    {stat.description}
                  </p>
                </>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      <Card className="mb-8">
        <CardHeader>
          <CardTitle>Orders Past Status Expiration</CardTitle>
          <CardDescription>
            Current overdue counts, day-over-day trend, and the same snapshot
            from the previous day
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isOverdueLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-12 w-full" />
              ))}
            </div>
          ) : overdueOrdersByStatus.length === 0 ? (
            <p className="text-muted-foreground text-center py-8">
              No overdue orders by status
            </p>
          ) : (
            <div className="space-y-3">
              {overdueOrdersByStatus.map((status) => (
                <div
                  key={status.status_id}
                  className="flex items-center justify-between gap-4 rounded-lg border p-4 cursor-pointer transition-colors hover:bg-muted/50"
                  onClick={() =>
                    navigate(
                      `${ROUTES.TENANT_ADMIN.ORDERS}?status=${encodeURIComponent(status.status_id)}`,
                    )}
                >
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <OrderStatusBadge status={{ id: status.status_id, status_key: status.status_key, admin_status_label: status.admin_status_label, is_terminal: status.is_terminal, next_step_owner: status.next_step_owner }} />
                    </div>
                    <p className="text-sm text-muted-foreground mt-1">
                      Previous day: {status.previous_day_overdue_count}
                    </p>
                  </div>
                  <div className="text-right shrink-0">
                    <div className="text-2xl font-bold">
                      {status.overdueDelta > 0 ? "+" : ""}
                      {status.overdueDelta}
                    </div>
                    <p className="text-xs text-muted-foreground">
                      vs previous day
                    </p>
                    <div className="mt-2 flex items-center justify-end gap-1 text-xs text-muted-foreground">
                      {status.overdueDelta > 0 ? (
                        <TrendingUp className="h-3.5 w-3.5 text-destructive" />
                      ) : status.overdueDelta < 0 ? (
                        <TrendingDown className="h-3.5 w-3.5 text-emerald-600" />
                      ) : (
                        <Minus className="h-3.5 w-3.5" />
                      )}
                      <span>
                        {status.overdue_count} overdue now
                      </span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Recent Orders */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Recent Orders</CardTitle>
            <CardDescription>Latest orders from your patients</CardDescription>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link to={ROUTES.TENANT_ADMIN.ORDERS}>
              View All
              <ArrowRight className="h-4 w-4 ml-2" />
            </Link>
          </Button>
        </CardHeader>
        <CardContent>
          {isRecentOrdersLoading ? (
            <div className="space-y-4">
              {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4">
                  <Skeleton className="h-10 w-10 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-1/4" />
                    <Skeleton className="h-3 w-1/3" />
                  </div>
                  <Skeleton className="h-6 w-20" />
                </div>
              ))}
            </div>
          ) : recentOrders.length === 0 ? (
            <p className="text-muted-foreground text-center py-8">
              No orders yet
            </p>
          ) : (
            <div className="space-y-4">
              {recentOrders.map((order) => (
                <Link
                  key={order.id}
                  to={`${ROUTES.TENANT_ADMIN.ORDERS}/${order.id}`}
                  className="flex items-center gap-4 p-3 rounded-lg hover:bg-muted/50 transition-colors"
                >
                  <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                    <ShoppingCart className="h-5 w-5 text-primary" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium truncate">
                      {order.patients?.first_name} {order.patients?.last_name}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      {order.order_number}
                      {order.product?.name ? ` • ${order.product.name}` : ""}
                      {" • "}
                      {dateTime(order.created_at).format("MMM D, YYYY")}
                    </p>
                  </div>
                  <OrderStatusBadge
                    status={order.order_statuses}
                    fallbackLabel="No Status"
                  />
                </Link>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
