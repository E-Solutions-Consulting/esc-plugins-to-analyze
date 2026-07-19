import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { dateTime } from "@/lib/dayjs";
import { Link, useNavigate, useParams } from "react-router-dom";
import {
  ArrowLeft,
  CalendarClock,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  CreditCard,
  Loader2,
  Package,
  Receipt,
  Save,
  User,
} from "lucide-react";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { MigrationStatus } from "@/components/common/MigrationStatus";
import { PageHeader } from "@/components/common/PageHeader";
import { OrderStatusBadge } from "@/components/features/OrderStatusBadge";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { ROUTES } from "@/lib/constants";
import { useAuth } from "@/stores/authStore";
import { supabase } from "@/integrations/supabase/client";
import { toast } from "sonner";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

function formatDate(value: string | null): string {
  if (!value) return "—";
  return dateTime(value).format("MMM D, YYYY");
}

function formatDateTime(value: string | null): string {
  if (!value) return "—";
  return dateTime(value).format("MMM D, YYYY h:mm A");
}

function formatCurrency(cents: number): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(cents / 100);
}

function formatEventType(eventType: string): string {
  return eventType
    .replace(/_/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function toDateInputValue(value: string | null): string {
  if (!value) return "";
  return dateTime(value).format("YYYY-MM-DD");
}

function getRefillDateWindow(subscription: SubscriptionDetails): {
  min: string;
  max: string;
} | null {
  if (!subscription.expires_at) return null;

  const anchor = dateTime(subscription.expires_at);
  if (!anchor.isValid()) return null;

  // PP-872: from 15 days before the plan's expiry date up to the expiry date
  // itself — never past expiry. 15 (not 14) keeps the current renewal
  // selectable on the standard 15-day-lead products. TODO(follow-up): make this
  // per-product/dynamic on the admin side (see products.renewal_advance_max_weeks).
  return {
    min: anchor.subtract(15, "day").format("YYYY-MM-DD"),
    max: anchor.format("YYYY-MM-DD"),
  };
}

function toRenewalIso(value: string, currentRenewalAt: string | null): string {
  const [year, month, day] = value.split("-").map(Number);
  if (
    !Number.isInteger(year) ||
    !Number.isInteger(month) ||
    !Number.isInteger(day) ||
    month < 1 ||
    month > 12 ||
    day < 1 ||
    day > 31
  ) {
    throw new Error("Please select a valid refill date");
  }

  const parsedCurrentRenewalDate = currentRenewalAt
    ? dateTime(currentRenewalAt)
    : null;
  const currentRenewalDate = parsedCurrentRenewalDate?.isValid()
    ? parsedCurrentRenewalDate
    : null;
  const hours = currentRenewalDate ? currentRenewalDate.hour() : 12;
  const minutes = currentRenewalDate ? currentRenewalDate.minute() : 0;
  const seconds = currentRenewalDate ? currentRenewalDate.second() : 0;
  const milliseconds = currentRenewalDate
    ? currentRenewalDate.millisecond()
    : 0;

  const nextRenewal = dateTime()
    .set("year", year)
    .set("month", month - 1)
    .set("date", day)
    .set("hour", hours)
    .set("minute", minutes)
    .set("second", seconds)
    .set("millisecond", milliseconds);

  if (!nextRenewal.isValid()) {
    throw new Error("Please select a valid refill date");
  }

  return nextRenewal.toISOString();
}

export default function SubscriptionDetail() {
  const ORDERS_PAGE_SIZE = 5;
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const [isPaymentDetailsOpen, setIsPaymentDetailsOpen] = useState(false);
  const [ordersPage, setOrdersPage] = useState(1);
  const [selectedStatus, setSelectedStatus] =
    useState<SubscriptionStatus | null>(null);
  const [selectedRenewalDate, setSelectedRenewalDate] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["subscription-detail", id, currentTenantId],
    queryFn: async (): Promise<SubscriptionDetailsPayload | null> => {
      if (!id || !currentTenantId) return null;

      const [
        subscriptionRes,
        ordersRes,
        providerLinksRes,
        paymentTransactionsRes,
        eventsRes,
      ] = await Promise.all([
        supabase
          .from("subscriptions")
          .select(
            `
            id,
            status,
            started_at,
            current_period_end_at,
            expires_at,
            paused_at,
            cancelled_at,
            cancellation_reason,
            metadata,
            created_at,
            updated_at,
            patient:patients (
              id,
              first_name,
              last_name,
              email,
              phone,
              access_status
            ),
            product:products (
              id,
              name,
              price_cents,
              payment_type,
              subscription_interval,
              subscription_interval_count,
              subscription_renewal_lead_days,
              renewal_advance_max_weeks
            )
          `,
          )
          .eq("id", id)
          .eq("tenant_id", currentTenantId)
          .maybeSingle(),
        supabase
          .from("orders")
          .select(
            `
            id,
            order_number,
            subscription_order_type,
            total_cents,
            created_at,
            paid_at,
            order_statuses (
              id,
              status_key,
              admin_status_label,
              is_terminal,
              next_step_owner
            )
          `,
          )
          .eq("subscription_id", id)
          .eq("tenant_id", currentTenantId)
          .order("created_at", { ascending: false }),
        supabase
          .from("subscription_payment_provider_links")
          .select(
            `
            id,
            provider_subscription_id,
            provider_checkout_session_id,
            created_at,
            updated_at,
            provider:payment_providers (
              name,
              key
            )
          `,
          )
          .eq("subscription_id", id)
          .eq("tenant_id", currentTenantId)
          .order("created_at", { ascending: false }),
        supabase
          .from("order_payment_provider_transactions")
          .select(
            `
            id,
            payment_status,
            paid_at,
            provider_payment_intent_id,
            provider_invoice_id,
            provider_charge_id,
            created_at,
            order:orders (
              id,
              order_number,
              total_cents,
              created_at
            ),
            provider:payment_providers (
              name,
              key
            )
          `,
          )
          .eq("subscription_id", id)
          .eq("tenant_id", currentTenantId)
          .order("created_at", { ascending: false })
          .limit(50),
        supabase
          .from("subscription_events")
          .select(
            `
            id,
            event_type,
            old_status,
            new_status,
            old_renewal_at,
            new_renewal_at,
            old_expires_at,
            new_expires_at,
            old_paused_at,
            new_paused_at,
            old_cancelled_at,
            new_cancelled_at,
            changed_by_email,
            notes,
            created_at
          `,
          )
          .eq("subscription_id", id)
          .eq("tenant_id", currentTenantId)
          .order("created_at", { ascending: false })
          .limit(100),
      ]);

      if (subscriptionRes.error) throw subscriptionRes.error;
      if (ordersRes.error) throw ordersRes.error;

      // Keep detail page available even when provider-agnostic payment tables are not deployed yet.
      const providerLinksMissingTable =
        providerLinksRes.error?.code === "42P01";
      const paymentTransactionsMissingTable =
        paymentTransactionsRes.error?.code === "42P01";
      const eventsMissingTable = eventsRes.error?.code === "42P01";

      if (providerLinksRes.error && !providerLinksMissingTable) {
        throw providerLinksRes.error;
      }
      if (paymentTransactionsRes.error && !paymentTransactionsMissingTable) {
        throw paymentTransactionsRes.error;
      }
      if (eventsRes.error && !eventsMissingTable) {
        throw eventsRes.error;
      }

      if (!subscriptionRes.data) return null;

      return {
        subscription: subscriptionRes.data as unknown as SubscriptionDetails,
        orders: (ordersRes.data || []) as unknown as SubscriptionOrder[],
        providerLinks: providerLinksMissingTable
          ? []
          : ((providerLinksRes.data || []) as unknown as ProviderLink[]),
        paymentTransactions: paymentTransactionsMissingTable
          ? []
          : ((paymentTransactionsRes.data ||
              []) as unknown as PaymentTransaction[]),
        events: eventsMissingTable
          ? []
          : ((eventsRes.data || []) as unknown as SubscriptionEvent[]),
      };
    },
    enabled: !!id && !!currentTenantId,
  });

  useEffect(() => {
    if (!data) return;
    const totalPages = Math.max(
      1,
      Math.ceil(data.orders.length / ORDERS_PAGE_SIZE),
    );
    if (ordersPage > totalPages) {
      setOrdersPage(totalPages);
    }
  }, [data, ordersPage, ORDERS_PAGE_SIZE]);

  useEffect(() => {
    if (!data?.subscription.status) return;
    setSelectedStatus(data.subscription.status);
  }, [data?.subscription.status]);

  useEffect(() => {
    setSelectedRenewalDate(
      toDateInputValue(data?.subscription.current_period_end_at || null),
    );
  }, [data?.subscription.current_period_end_at]);

  const updateStatusMutation = useMutation({
    mutationFn: async (newStatus: SubscriptionStatus) => {
      if (!id || !currentTenantId || !data?.subscription) {
        throw new Error("Subscription context is missing");
      }

      const subscription = data.subscription;

      if (newStatus === "paused") {
        if (subscription.status !== "active") {
          throw new Error("Only active plans can be paused");
        }

        const {
          data: { session },
        } = await supabase.auth.getSession();

        if (!session) {
          throw new Error("Not authenticated");
        }

        const response = await fetch(
          `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/admin/plans/${id}/pause`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${session.access_token}`,
              apikey: import.meta.env.VITE_SUPABASE_ANON_KEY,
            },
          },
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok) {
          const errorMessage =
            payload?.error?.message ||
            payload?.message ||
            "Failed to pause plan";
          throw new Error(errorMessage);
        }

        return newStatus;
      }

      if (newStatus === "active" && subscription.status === "paused") {
        const {
          data: { session },
        } = await supabase.auth.getSession();

        if (!session) {
          throw new Error("Not authenticated");
        }

        const response = await fetch(
          `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/admin/plans/${id}/resume`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${session.access_token}`,
              apikey: import.meta.env.VITE_SUPABASE_ANON_KEY,
            },
          },
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok) {
          const errorMessage =
            payload?.error?.message ||
            payload?.message ||
            "Failed to resume plan";
          throw new Error(errorMessage);
        }

        return newStatus;
      }

      const now = dateTime().toISOString();
      const updatePayload: {
        status: SubscriptionStatus;
        paused_at?: string | null;
        cancelled_at?: string | null;
        cancellation_reason?: string | null;
      } = { status: newStatus };

      if (newStatus === "cancelled") {
        updatePayload.cancelled_at = subscription.cancelled_at || now;
      } else {
        updatePayload.paused_at = null;
        updatePayload.cancelled_at = null;
        updatePayload.cancellation_reason = null;
      }

      const { error } = await supabase
        .from("subscriptions")
        .update(updatePayload)
        .eq("id", id)
        .eq("tenant_id", currentTenantId)
        .select("id, status")
        .single();

      if (error) throw error;
      return newStatus;
    },
    onSuccess: async (newStatus) => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ["subscription-detail", id, currentTenantId],
        }),
        queryClient.invalidateQueries({
          queryKey: ["subscriptions", currentTenantId],
        }),
      ]);
      setSelectedStatus(newStatus);
      if (newStatus === "paused") {
        setSelectedRenewalDate("");
        toast.success("Plan paused successfully");
        return;
      }
      if (newStatus === "active" && subscription.status === "paused") {
        toast.success("Plan resumed successfully");
        return;
      }
      toast.success("Subscription status updated");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to update subscription status",
      );
    },
  });

  const updateRefillDateMutation = useMutation({
    mutationFn: async (newDate: string) => {
      if (!id || !currentTenantId || !data?.subscription) {
        throw new Error("Subscription context is missing");
      }

      if (data.subscription.status !== "active") {
        throw new Error("Refill date can only be changed for active plans");
      }

      if (!newDate) {
        throw new Error("Please select a refill date");
      }

      const normalizedRenewalAt = toRenewalIso(
        newDate,
        data.subscription.current_period_end_at,
      );
      const refillDateWindow = getRefillDateWindow(data.subscription);
      if (
        refillDateWindow &&
        (newDate < refillDateWindow.min || newDate > refillDateWindow.max)
      ) {
        throw new Error("Please select a refill date in the allowed window");
      }
      const {
        data: { session },
      } = await supabase.auth.getSession();

      if (!session) {
        throw new Error("Not authenticated");
      }

      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/admin/plans/${id}/refill-date`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${session.access_token}`,
            apikey: import.meta.env.VITE_SUPABASE_ANON_KEY,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            new_date: normalizedRenewalAt,
          }),
        },
      );

      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        const errorMessage =
          payload?.error?.message ||
          payload?.message ||
          "Failed to update subscription refill date";
        throw new Error(errorMessage);
      }

      return payload?.data?.renewal_at || normalizedRenewalAt;
    },
    onSuccess: async (updatedRenewalAt) => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: ["subscription-detail", id, currentTenantId],
        }),
        queryClient.invalidateQueries({
          queryKey: ["subscriptions", currentTenantId],
        }),
      ]);
      setSelectedRenewalDate(toDateInputValue(updatedRenewalAt));
      toast.success("Subscription refill date updated");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to update subscription refill date",
      );
    },
  });

  if (isLoading) {
    return (
      <AdminLayout variant="tenant">
        <div className="space-y-6">
          <Skeleton className="h-10 w-72" />
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Skeleton className="h-28" />
            <Skeleton className="h-28" />
            <Skeleton className="h-28" />
            <Skeleton className="h-28" />
          </div>
          <div className="grid gap-6 lg:grid-cols-3">
            <Skeleton className="h-96 lg:col-span-2" />
            <Skeleton className="h-96" />
          </div>
        </div>
      </AdminLayout>
    );
  }

  if (!data) {
    return (
      <AdminLayout variant="tenant">
        <div className="text-center py-12">
          <h2 className="text-xl font-semibold">Subscription not found</h2>
          <p className="text-muted-foreground mt-2">
            The subscription you are looking for does not exist or is not
            available for this tenant.
          </p>
          <Button
            variant="outline"
            className="mt-4"
            onClick={() => navigate(ROUTES.TENANT_ADMIN.SUBSCRIPTIONS)}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Subscriptions
          </Button>
        </div>
      </AdminLayout>
    );
  }

  const { subscription, orders, providerLinks, paymentTransactions, events } =
    data;
  const effectiveStatus = selectedStatus || subscription.status;
  const hasStatusChanged = effectiveStatus !== subscription.status;
  const currentRenewalDateInput = toDateInputValue(
    subscription.current_period_end_at,
  );
  const hasRefillDateChanged = selectedRenewalDate !== currentRenewalDateInput;
  const canEditRefillDate = subscription.status === "active";
  const refillDateWindow = getRefillDateWindow(subscription);

  const subscriptionCode = `SUB-${subscription.id.slice(0, 8).toUpperCase()}`;
  const totalRevenueCents = orders.reduce(
    (sum, order) => sum + order.total_cents,
    0,
  );
  const ordersTotalPages = Math.max(
    1,
    Math.ceil(orders.length / ORDERS_PAGE_SIZE),
  );
  const paginatedOrders = orders.slice(
    (ordersPage - 1) * ORDERS_PAGE_SIZE,
    ordersPage * ORDERS_PAGE_SIZE,
  );

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title={`Subscription ${subscriptionCode}`}
        description="Lifecycle details, orders, and payment linkage"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() => navigate(ROUTES.TENANT_ADMIN.SUBSCRIPTIONS)}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Subscriptions
            </Button>
          </div>
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>State</CardDescription>
            <CardTitle className="text-base">
              <StatusBadge status={subscription.status} size="sm" />
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="flex items-center gap-2">
              <Select
                value={effectiveStatus}
                onValueChange={(value) =>
                  setSelectedStatus(value as SubscriptionStatus)
                }
                disabled={updateStatusMutation.isPending}
              >
                <SelectTrigger className="h-8">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="pending_validation">
                    Pending Validation
                  </SelectItem>
                  <SelectItem value="pending_cancellation">
                    Pending Cancellation
                  </SelectItem>
                  <SelectItem value="paused">Paused</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
              <Button
                size="sm"
                variant="outline"
                disabled={!hasStatusChanged || updateStatusMutation.isPending}
                onClick={() => updateStatusMutation.mutate(effectiveStatus)}
              >
                {updateStatusMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Save className="h-4 w-4" />
                )}
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Refill Date</CardDescription>
            <CardTitle className="text-base">
              {formatDate(subscription.current_period_end_at)}
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="flex items-center gap-2">
              <Input
                type="date"
                className="h-8"
                value={selectedRenewalDate}
                min={refillDateWindow?.min}
                max={refillDateWindow?.max}
                onChange={(event) => setSelectedRenewalDate(event.target.value)}
                disabled={
                  !canEditRefillDate || updateRefillDateMutation.isPending
                }
              />
              <Button
                size="sm"
                variant="outline"
                disabled={
                  !canEditRefillDate ||
                  !selectedRenewalDate ||
                  !hasRefillDateChanged ||
                  updateRefillDateMutation.isPending
                }
                onClick={() =>
                  updateRefillDateMutation.mutate(selectedRenewalDate)
                }
              >
                {updateRefillDateMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Save className="h-4 w-4" />
                )}
              </Button>
            </div>
            {!canEditRefillDate && (
              <p className="mt-2 text-xs text-muted-foreground">
                Refill date can only be changed for active plans.
              </p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Orders in Subscription</CardDescription>
            <CardTitle className="text-base">{orders.length}</CardTitle>
          </CardHeader>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Total Order Value</CardDescription>
            <CardTitle className="text-base">
              {formatCurrency(totalRevenueCents)}
            </CardTitle>
          </CardHeader>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <CalendarClock className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle>Subscription Orders</CardTitle>
                  <CardDescription>
                    All orders associated with this subscription lifecycle
                  </CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              {orders.length === 0 ? (
                <p className="text-muted-foreground py-4">
                  No orders linked to this subscription.
                </p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Order</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Created</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Paid At</TableHead>
                      <TableHead className="text-right">Total</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {paginatedOrders.map((order) => (
                      <TableRow
                        key={order.id}
                        className="cursor-pointer hover:bg-muted/50"
                        onClick={() =>
                          navigate(
                            ROUTES.TENANT_ADMIN.ORDER_DETAIL.replace(
                              ":id",
                              order.id,
                            ),
                          )
                        }
                      >
                        <TableCell className="font-medium">
                          {order.order_number}
                        </TableCell>
                        <TableCell>
                          {order.subscription_order_type ? (
                            <Badge
                              variant={
                                order.subscription_order_type === "initial"
                                  ? "default"
                                  : "secondary"
                              }
                            >
                              {order.subscription_order_type === "initial"
                                ? "First Order"
                                : "Renewal"}
                            </Badge>
                          ) : (
                            "—"
                          )}
                        </TableCell>
                        <TableCell>{formatDate(order.created_at)}</TableCell>
                        <TableCell>
                          <OrderStatusBadge
                            status={order.order_statuses}
                            fallbackLabel="No Status"
                          />
                        </TableCell>
                        <TableCell>{formatDateTime(order.paid_at)}</TableCell>
                        <TableCell className="text-right">
                          {formatCurrency(order.total_cents)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}

              {orders.length > ORDERS_PAGE_SIZE && (
                <div className="mt-4 flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">
                    Showing {(ordersPage - 1) * ORDERS_PAGE_SIZE + 1} to{" "}
                    {Math.min(ordersPage * ORDERS_PAGE_SIZE, orders.length)} of{" "}
                    {orders.length} orders
                  </p>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        setOrdersPage((page) => Math.max(1, page - 1))
                      }
                      disabled={ordersPage <= 1}
                    >
                      <ChevronLeft className="h-4 w-4 mr-1" />
                      Previous
                    </Button>
                    <span className="text-sm text-muted-foreground">
                      Page {ordersPage} of {ordersTotalPages}
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        setOrdersPage((page) =>
                          Math.min(ordersTotalPages, page + 1),
                        )
                      }
                      disabled={ordersPage >= ordersTotalPages}
                    >
                      Next
                      <ChevronRight className="h-4 w-4 ml-1" />
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <CalendarClock className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle>Subscription Events</CardTitle>
                  <CardDescription>
                    Lifecycle timeline captured from subscription event logs
                  </CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              {events.length === 0 ? (
                <p className="text-muted-foreground py-4">
                  No subscription events found.
                </p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>When</TableHead>
                      <TableHead>Event</TableHead>
                      <TableHead>Changes</TableHead>
                      <TableHead>Actor</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {events.map((event) => {
                      const changes: string[] = [];

                      if (event.old_status !== event.new_status) {
                        changes.push(
                          `status: ${event.old_status || "—"} -> ${event.new_status || "—"}`,
                        );
                      }
                      if (event.old_renewal_at !== event.new_renewal_at) {
                        changes.push(
                          `renewal: ${formatDateTime(event.old_renewal_at)} -> ${formatDateTime(event.new_renewal_at)}`,
                        );
                      }
                      if (event.old_expires_at !== event.new_expires_at) {
                        changes.push(
                          `expiration: ${formatDateTime(event.old_expires_at)} -> ${formatDateTime(event.new_expires_at)}`,
                        );
                      }
                      if (event.old_paused_at !== event.new_paused_at) {
                        changes.push(
                          `paused_at: ${formatDateTime(event.old_paused_at)} -> ${formatDateTime(event.new_paused_at)}`,
                        );
                      }
                      if (event.old_cancelled_at !== event.new_cancelled_at) {
                        changes.push(
                          `cancelled_at: ${formatDateTime(event.old_cancelled_at)} -> ${formatDateTime(event.new_cancelled_at)}`,
                        );
                      }

                      return (
                        <TableRow key={event.id}>
                          <TableCell>
                            {formatDateTime(event.created_at)}
                          </TableCell>
                          <TableCell>
                            <Badge variant="secondary">
                              {formatEventType(event.event_type)}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-xs text-muted-foreground">
                            <div className="space-y-1">
                              {changes.length > 0 ? (
                                changes.map((change, index) => (
                                  <div
                                    key={`${event.id}-change-${index}`}
                                    className="font-mono"
                                  >
                                    {change}
                                  </div>
                                ))
                              ) : (
                                <div className="font-mono">—</div>
                              )}
                              {event.notes && <div>{event.notes}</div>}
                            </div>
                          </TableCell>
                          <TableCell>
                            {event.changed_by_email || "System"}
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          <Collapsible
            open={isPaymentDetailsOpen}
            onOpenChange={setIsPaymentDetailsOpen}
          >
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <div className="flex items-center gap-2">
                  <CreditCard className="h-5 w-5 text-primary" />
                  <div>
                    <CardTitle>
                      Provider Linkage & Payment Transactions
                    </CardTitle>
                    <CardDescription>
                      Provider lifecycle identifiers and payment snapshots
                      linked to this subscription
                    </CardDescription>
                  </div>
                </div>
                <CollapsibleTrigger asChild>
                  <Button variant="outline" size="sm">
                    {isPaymentDetailsOpen ? "Hide" : "Show"}
                    <ChevronDown
                      className={`ml-2 h-4 w-4 transition-transform ${
                        isPaymentDetailsOpen ? "rotate-180" : ""
                      }`}
                    />
                  </Button>
                </CollapsibleTrigger>
              </CardHeader>
              <CollapsibleContent>
                <CardContent className="space-y-6">
                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <CreditCard className="h-4 w-4 text-primary" />
                      <h3 className="text-sm font-medium">Provider Linkage</h3>
                    </div>
                    {providerLinks.length === 0 ? (
                      <p className="text-muted-foreground">
                        No provider links found.
                      </p>
                    ) : (
                      <div className="space-y-3">
                        {providerLinks.map((link) => (
                          <div
                            key={link.id}
                            className="rounded-md border p-3 space-y-1"
                          >
                            <p className="font-medium">
                              {link.provider
                                ? `${link.provider.name} (${link.provider.key})`
                                : "Provider"}
                            </p>
                            <p className="text-xs text-muted-foreground font-mono break-all">
                              subscription:{" "}
                              {link.provider_subscription_id || "—"}
                            </p>
                            <p className="text-xs text-muted-foreground font-mono break-all">
                              checkout:{" "}
                              {link.provider_checkout_session_id || "—"}
                            </p>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>

                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <Receipt className="h-4 w-4 text-primary" />
                      <h3 className="text-sm font-medium">
                        Payment Transactions
                      </h3>
                    </div>
                    {paymentTransactions.length === 0 ? (
                      <p className="text-muted-foreground">
                        No payment transactions found.
                      </p>
                    ) : (
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Provider</TableHead>
                            <TableHead>Order</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Paid At</TableHead>
                            <TableHead>References</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {paymentTransactions.map((transaction) => (
                            <TableRow key={transaction.id}>
                              <TableCell>
                                {transaction.provider
                                  ? `${transaction.provider.name} (${transaction.provider.key})`
                                  : "—"}
                              </TableCell>
                              <TableCell>
                                {transaction.order?.order_number || "—"}
                              </TableCell>
                              <TableCell>
                                {transaction.payment_status ? (
                                  <Badge variant="secondary">
                                    {transaction.payment_status}
                                  </Badge>
                                ) : (
                                  "—"
                                )}
                              </TableCell>
                              <TableCell>
                                {formatDateTime(transaction.paid_at)}
                              </TableCell>
                              <TableCell className="text-xs font-mono text-muted-foreground">
                                {transaction.provider_invoice_id ||
                                transaction.provider_payment_intent_id ||
                                transaction.provider_charge_id
                                  ? [
                                      transaction.provider_invoice_id
                                        ? `inv:${transaction.provider_invoice_id}`
                                        : null,
                                      transaction.provider_payment_intent_id
                                        ? `pi:${transaction.provider_payment_intent_id}`
                                        : null,
                                      transaction.provider_charge_id
                                        ? `ch:${transaction.provider_charge_id}`
                                        : null,
                                    ]
                                      .filter(Boolean)
                                      .join(" | ")
                                  : "—"}
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    )}
                  </div>
                </CardContent>
              </CollapsibleContent>
            </Card>
          </Collapsible>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <User className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle>Patient</CardTitle>
                  <CardDescription>Linked patient profile</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-2">
              {subscription.patient ? (
                <>
                  <p className="font-medium">
                    {subscription.patient.first_name}{" "}
                    {subscription.patient.last_name}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {subscription.patient.email}
                  </p>
                  {subscription.patient.phone && (
                    <p className="text-sm text-muted-foreground">
                      {subscription.patient.phone}
                    </p>
                  )}
                  <Button variant="outline" size="sm" asChild>
                    <Link
                      to={ROUTES.TENANT_ADMIN.PATIENT_DETAIL.replace(
                        ":id",
                        subscription.patient.id,
                      )}
                    >
                      <User className="h-4 w-4 mr-2" />
                      View Patient Details
                    </Link>
                  </Button>
                </>
              ) : (
                <p className="text-muted-foreground">No patient linked.</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Package className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle>Product</CardTitle>
                  <CardDescription>Subscribed item</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-2">
              {subscription.product ? (
                <>
                  <p className="font-medium">{subscription.product.name}</p>
                  <p className="text-sm text-muted-foreground">
                    {formatCurrency(subscription.product.price_cents)}
                  </p>
                  {subscription.product.payment_type === "subscription" &&
                    subscription.product.subscription_interval &&
                    subscription.product.subscription_interval_count && (
                      <p className="text-sm text-muted-foreground">
                        Every {subscription.product.subscription_interval_count}{" "}
                        {subscription.product.subscription_interval}
                        {subscription.product.subscription_interval_count > 1
                          ? "s"
                          : ""}
                      </p>
                    )}
                  <Button variant="outline" size="sm" asChild>
                    <Link
                      to={ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(
                        ":id",
                        subscription.product.id,
                      )}
                    >
                      View Product
                    </Link>
                  </Button>
                </>
              ) : (
                <p className="text-muted-foreground">No product linked.</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Lifecycle Metadata</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Subscription ID</span>
                <span className="font-mono text-xs">{subscription.id}</span>
              </div>
              <MigrationStatus
                metadata={subscription.metadata}
                entityType="subscription"
                createdAt={subscription.created_at}
              />
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Started</span>
                <span>{formatDate(subscription.started_at)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Renewal</span>
                <span>{formatDate(subscription.current_period_end_at)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Expires</span>
                <span>{formatDate(subscription.expires_at)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Paused</span>
                <span>{formatDate(subscription.paused_at)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Cancelled</span>
                <span>{formatDate(subscription.cancelled_at)}</span>
              </div>
              {(subscription.status === "cancelled" ||
                subscription.status === "pending_cancellation") && (
                <div className="flex items-center justify-between gap-4">
                  <span className="text-muted-foreground">
                    Cancellation Reason
                  </span>
                  <span className="text-right">
                    {subscription.cancellation_reason?.trim() || "—"}
                  </span>
                </div>
              )}
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Created</span>
                <span>{formatDate(subscription.created_at)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Updated</span>
                <span>{formatDate(subscription.updated_at)}</span>
              </div>
              {subscription.metadata &&
                Object.keys(subscription.metadata).length > 0 && (
                  <div className="pt-2">
                    <p className="text-muted-foreground mb-1">Metadata</p>
                    <pre className="text-xs rounded bg-muted p-2 overflow-x-auto">
                      {JSON.stringify(subscription.metadata, null, 2)}
                    </pre>
                  </div>
                )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
