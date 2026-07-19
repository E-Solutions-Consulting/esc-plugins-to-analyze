import { useQueries } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { OrderStatusBadge } from '@/components/features/OrderStatusBadge';
import { DollarSign, Package, TrendingUp, Users, ShoppingCart } from 'lucide-react';
import { dateTime } from '@/lib/dayjs';
import { useNavigate } from 'react-router-dom';

interface ProductSalesTabProps {
  productId: string;
  tenantId: string;
}

export function ProductSalesTab({ productId, tenantId }: ProductSalesTabProps) {
  const navigate = useNavigate();

  const [
    { data: metrics, isLoading: isLoadingMetrics },
    { data: recentOrders = [], isLoading: isLoadingOrders },
  ] = useQueries({
    queries: [
      {
        // Fetch sales metrics
        queryKey: ['product-sales-metrics', productId, tenantId],
        queryFn: async () => {
          const { data: orders, error } = await supabase
            .from('orders')
            .select('id, patient_id, subtotal_cents, total_cents')
            .eq('product_id', productId)
            .eq('tenant_id', tenantId);

          if (error) throw error;

          const totalOrders = orders?.length || 0;
          const totalQuantity = orders?.length || 0;
          const totalRevenue = orders?.reduce((sum, order) => sum + order.total_cents, 0) || 0;
          const uniqueCustomers = new Set(orders?.map(order => order.patient_id)).size;

          return {
            totalOrders,
            totalQuantity,
            totalRevenue,
            uniqueCustomers,
          } as SalesMetrics;
        },
        enabled: !!productId && !!tenantId,
      },
      {
        // Fetch recent orders containing this product
        queryKey: ['product-recent-orders', productId, tenantId],
        queryFn: async () => {
          const { data: orders, error } = await supabase
            .from('orders')
            .select(`
              id,
              order_number,
              subtotal_cents,
              total_cents,
              created_at,
              patient:patients!inner (
                id,
                first_name,
                last_name
              ),
              order_status:order_statuses (
                id,
                status_key,
                admin_status_label,
                is_terminal,
                next_step_owner
              )
            `)
            .eq('product_id', productId)
            .eq('tenant_id', tenantId)
            .order('created_at', { ascending: false })
            .limit(10);

          if (error) throw error;

          return (orders || []).map(order => {
            return {
              orderId: order.id,
              orderNumber: order.order_number,
              patientName: `${order.patient.first_name} ${order.patient.last_name}`,
              patientId: order.patient.id,
              quantity: 1,
              unitPriceCents: order.subtotal_cents,
              totalCents: order.total_cents,
              orderStatus: order.order_status,
              createdAt: order.created_at,
            } as RecentOrder;
          });
        },
        enabled: !!productId && !!tenantId,
      },
    ],
  });

  const formatCurrency = (cents: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(cents / 100);
  };

  if (isLoadingMetrics) {
    return (
      <div className="space-y-6">
        <div className="grid gap-4 md:grid-cols-4">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} className="h-32" />
          ))}
        </div>
        <Skeleton className="h-64" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Metrics Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Orders</CardTitle>
            <ShoppingCart className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.totalOrders || 0}</div>
            <p className="text-xs text-muted-foreground">Orders containing this product</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Units Sold</CardTitle>
            <Package className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.totalQuantity || 0}</div>
            <p className="text-xs text-muted-foreground">Total quantity sold</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Revenue</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatCurrency(metrics?.totalRevenue || 0)}</div>
            <p className="text-xs text-muted-foreground">Total revenue from this product</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Customers</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.uniqueCustomers || 0}</div>
            <p className="text-xs text-muted-foreground">Unique customers</p>
          </CardContent>
        </Card>
      </div>

      {/* Recent Orders Table */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Recent Orders</CardTitle>
          <CardDescription>Latest orders containing this product</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoadingOrders ? (
            <div className="space-y-2">
              {[...Array(5)].map((_, i) => (
                <Skeleton key={i} className="h-12" />
              ))}
            </div>
          ) : recentOrders.length === 0 ? (
            <div className="text-center py-8">
              <TrendingUp className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
              <h3 className="text-lg font-medium">No sales yet</h3>
              <p className="text-muted-foreground mt-1">
                This product hasn't been ordered yet
              </p>
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Order</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead className="text-right">Qty</TableHead>
                  <TableHead className="text-right">Unit Price</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Date</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentOrders.map((order) => (
                  <TableRow 
                    key={`${order.orderId}-${order.createdAt}`}
                    className="cursor-pointer hover:bg-muted/50"
                    onClick={() => navigate(`/tenant-admin/orders/${order.orderId}`)}
                  >
                    <TableCell className="font-medium">{order.orderNumber}</TableCell>
                    <TableCell>
                      <button
                        data-testid={`button-open-patient-${order.patientId}`}
                        className="text-primary hover:underline"
                        onClick={(e) => {
                          e.stopPropagation();
                          navigate(`/tenant-admin/patients/${order.patientId}`);
                        }}
                      >
                        {order.patientName}
                      </button>
                    </TableCell>
                    <TableCell className="text-right">{order.quantity}</TableCell>
                    <TableCell className="text-right">{formatCurrency(order.unitPriceCents)}</TableCell>
                    <TableCell className="text-right font-medium">{formatCurrency(order.totalCents)}</TableCell>
                    <TableCell>
                      <OrderStatusBadge status={order.orderStatus} fallbackLabel="No Status" />
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {dateTime(order.createdAt).format('MMM D, YYYY')}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
