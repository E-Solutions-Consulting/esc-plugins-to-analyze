import { Column, DataTable } from "@/components/common/DataTable";
import { MigrationBadge } from "@/components/common/MigrationStatus";
import { PageHeader } from "@/components/common/PageHeader";
import { Calendar } from "@/components/ui/calendar";
import { OrderStatusBadge } from "@/components/features/OrderStatusBadge";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { supabase } from "@/integrations/supabase/client";
import { ROUTES } from "@/lib/constants";
import { canPerformAction } from "@/lib/admin-permissions";
import { dateTime } from "@/lib/dayjs";
import { cn } from "@/lib/utils";
import { US_STATES } from "@/lib/usStates";
import { useAuth } from "@/stores/authStore";
import { useQueries } from "@tanstack/react-query";
import {
  CalendarIcon,
  ChevronDown,
  CircleAlert,
  Download,
  Filter,
  Loader2,
  RotateCcw,
  X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";

const PAGE_SIZE = 25;
const EXPORT_BATCH_SIZE = 1000;
const OPTIONAL_COLUMN_KEYS = [
  "subscription_id",
  "subscription_status",
  "patient_email",
  "shipping_state",
  "total",
  "renewal",
] as const;
const OPTIONAL_COLUMN_LABELS: Record<(typeof OPTIONAL_COLUMN_KEYS)[number], string> = {
  subscription_id: "Subscription ID",
  subscription_status: "Subscription Status",
  patient_email: "Patient Email",
  shipping_state: "Shipping State",
  total: "Total Paid",
  renewal: "Renewal",
};
const DEFAULT_VISIBLE_OPTIONAL_COLUMNS = OPTIONAL_COLUMN_KEYS.reduce(
  (acc, key) => ({ ...acc, [key]: true }),
  {} as Record<(typeof OPTIONAL_COLUMN_KEYS)[number], boolean>,
);
const CREATED_DATE_PRESET_VALUES = [
  "all",
  "last_week",
  "last_month",
  "custom",
] as const;

type CreatedDatePreset = (typeof CREATED_DATE_PRESET_VALUES)[number];

function getInitialVisibleOptionalColumns() {
  if (typeof window === "undefined") {
    return DEFAULT_VISIBLE_OPTIONAL_COLUMNS;
  }

  try {
    const storedValue = window.localStorage.getItem("orders-visible-columns");
    if (!storedValue) return DEFAULT_VISIBLE_OPTIONAL_COLUMNS;

    const parsedValue = JSON.parse(storedValue) as Partial<
      Record<(typeof OPTIONAL_COLUMN_KEYS)[number], boolean>
    >;

    return OPTIONAL_COLUMN_KEYS.reduce(
      (acc, key) => ({
        ...acc,
        [key]:
          typeof parsedValue[key] === "boolean"
            ? parsedValue[key]
            : DEFAULT_VISIBLE_OPTIONAL_COLUMNS[key],
      }),
      {} as Record<(typeof OPTIONAL_COLUMN_KEYS)[number], boolean>,
    );
  } catch {
    return DEFAULT_VISIBLE_OPTIONAL_COLUMNS;
  }
}

function parseDateParam(value: string | null) {
  if (!value) return undefined;

  const parsed = dateTime(value, "YYYY-MM-DD", true);
  return parsed.isValid() ? parsed.toDate() : undefined;
}

function getInitialCreatedDatePreset(value: string | null): CreatedDatePreset {
  if (
    value &&
    CREATED_DATE_PRESET_VALUES.includes(value as CreatedDatePreset)
  ) {
    return value as CreatedDatePreset;
  }

  return "all";
}

function formatSubscriptionId(order: OrderWithDetails) {
  if (!order.subscription) return "—";
  return `SUB-${order.subscription.id.slice(0, 8).toUpperCase()}`;
}

function formatSubscriptionStatus(status: string | null | undefined) {
  if (!status) return "—";

  return status
    .replace(/_/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatSubscriptionOrderType(
  orderType: OrderWithDetails["subscription_order_type"],
) {
  if (!orderType) return "—";
  return orderType === "initial" ? "First Order" : "Renewal";
}

function formatPatientName(order: OrderWithDetails) {
  const firstName = order.patients?.first_name?.trim() || "";
  const lastName = order.patients?.last_name?.trim() || "";
  const fullName = `${firstName} ${lastName}`.trim();

  return fullName || "—";
}

function formatOrderStatusLabel(order: OrderWithDetails) {
  return order.order_status?.admin_status_label || "No Status";
}

function formatCurrency(cents: number) {
  return `$${(cents / 100).toFixed(2)}`;
}

function formatCsvDate(value: string | null) {
  if (!value) return "—";
  return dateTime(value).format("YYYY-MM-DD HH:mm:ss");
}

function escapeCsvValue(value: string) {
  return `"${value.replace(/"/g, '""')}"`;
}

const ORDER_CSV_HEADERS = [
  "Order #",
  "Patient",
  "Patient Email",
  "Product",
  "Subscription ID",
  "Subscription Status",
  "Shipping State",
  "Type",
  "Status",
  "Total Paid",
  "Created",
  "Renewal",
];

function buildOrdersCsvChunk(orders: OrderExportRow[], includeHeader: boolean) {
  const rows = orders.map((order) =>
    [
      order.order_number,
      `${order.patient_first_name} ${order.patient_last_name}`.trim() || "—",
      order.patient_email ?? "—",
      order.product_name ?? "—",
      order.subscription_id
        ? `SUB-${order.subscription_id.slice(0, 8).toUpperCase()}`
        : "—",
      formatSubscriptionStatus(order.subscription_status),
      order.shipping_state ?? "—",
      formatSubscriptionOrderType(order.subscription_order_type),
      order.order_status_label ?? "No Status",
      formatCurrency(order.total_cents),
      formatCsvDate(order.created_at),
      formatCsvDate(order.subscription_current_period_end_at),
    ]
      .map((value) => escapeCsvValue(value))
      .join(","),
  );

  return [
    ...(includeHeader
      ? [ORDER_CSV_HEADERS.map((header) => escapeCsvValue(header)).join(",")]
      : []),
    ...rows,
  ].join("\n");
}

interface OrderStatus {
  id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  next_step_owner: string;
}

interface OrderWithDetails {
  id: string;
  order_number: string;
  status_id: string | null;
  provider_platform_integration_key: string | null;
  total_cents: number;
  discount_cents: number;
  metadata: Record<string, unknown> | null;
  created_at: string;
  renewal_at: string | null;
  shipping_state: string | null;
  subscription_order_type: "initial" | "renewal" | null;
  subscription: {
    id: string;
    status: string;
    current_period_end_at: string | null;
  } | null;
  patients: { first_name: string; last_name: string; email: string | null } | null;
  product: { name: string } | null;
  order_status: OrderStatus | null;
}

interface OrderExportRow {
  id: string;
  order_number: string;
  total_cents: number;
  created_at: string;
  shipping_state: string | null;
  subscription_order_type: "initial" | "renewal" | null;
  subscription_id: string | null;
  subscription_status: string | null;
  subscription_current_period_end_at: string | null;
  patient_first_name: string;
  patient_last_name: string;
  patient_email: string | null;
  product_name: string | null;
  order_status_label: string | null;
}

interface OrderPageCursor {
  createdAt: string;
  id: string;
}

export default function Orders() {
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchInput, setSearchInput] = useState(
    () => searchParams.get("q") || "",
  );
  const [search, setSearch] = useState(() => searchParams.get("q") || "");
  const [statusFilter, setStatusFilter] = useState<string>(
    searchParams.get("status") || "all",
  );
  const [productFilter, setProductFilter] = useState<string>(
    searchParams.get("product") || "all",
  );
  const [providerPlatformFilter, setProviderPlatformFilter] = useState<string>(
    searchParams.get("providerPlatform") || "all",
  );
  const [stateFilter, setStateFilter] = useState<string>(
    searchParams.get("state") || "all",
  );
  const [createdDatePreset, setCreatedDatePreset] = useState<CreatedDatePreset>(
    getInitialCreatedDatePreset(searchParams.get("createdDate")),
  );
  const [createdStartDate, setCreatedStartDate] = useState<Date | undefined>(
    parseDateParam(searchParams.get("createdFrom")),
  );
  const [createdEndDate, setCreatedEndDate] = useState<Date | undefined>(
    parseDateParam(searchParams.get("createdTo")),
  );
  const [isFiltersOpen, setIsFiltersOpen] = useState(false);
  const [visibleOptionalColumns, setVisibleOptionalColumns] = useState<
    Record<(typeof OPTIONAL_COLUMN_KEYS)[number], boolean>
  >(getInitialVisibleOptionalColumns);
  const [isColumnsMenuOpen, setIsColumnsMenuOpen] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [exportedCount, setExportedCount] = useState(0);
  const exportAbortControllerRef = useRef<AbortController | null>(null);
  const [page, setPage] = useState(1);
  const [pageCursors, setPageCursors] = useState<
    Array<OrderPageCursor | null>
  >([null]);
  const permissionContext = {
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    currentTenantId,
  };
  const canExportOrders = canPerformAction(permissionContext, "order:export");
  const hasCreatedDateFilter =
    createdDatePreset === "last_week" ||
    createdDatePreset === "last_month" ||
    (createdDatePreset === "custom" &&
      (createdStartDate !== undefined || createdEndDate !== undefined));

  // Debounce search input to avoid re-fetching on every keystroke
  useEffect(() => {
    const timer = setTimeout(() => setSearch(searchInput.trim()), 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  // Reset to page 1 when any filter or search changes
  useEffect(() => {
    setPage(1);
    setPageCursors([null]);
  }, [
    search,
    statusFilter,
    productFilter,
    providerPlatformFilter,
    stateFilter,
    createdDatePreset,
    createdStartDate,
    createdEndDate,
  ]);

  useEffect(() => {
    const nextParams = new URLSearchParams();

    if (statusFilter !== "all") {
      nextParams.set("status", statusFilter);
    }

    if (search) {
      nextParams.set("q", search);
    }

    if (productFilter !== "all") {
      nextParams.set("product", productFilter);
    }

    if (providerPlatformFilter !== "all") {
      nextParams.set("providerPlatform", providerPlatformFilter);
    }

    if (stateFilter !== "all") {
      nextParams.set("state", stateFilter);
    }

    if (hasCreatedDateFilter) {
      nextParams.set("createdDate", createdDatePreset);
    }

    if (createdDatePreset === "custom" && createdStartDate) {
      nextParams.set("createdFrom", dateTime(createdStartDate).format("YYYY-MM-DD"));
    }

    if (createdDatePreset === "custom" && createdEndDate) {
      nextParams.set("createdTo", dateTime(createdEndDate).format("YYYY-MM-DD"));
    }

    setSearchParams(nextParams, { replace: true });
  }, [
    search,
    productFilter,
    providerPlatformFilter,
    stateFilter,
    createdDatePreset,
    createdStartDate,
    createdEndDate,
    hasCreatedDateFilter,
    setSearchParams,
    statusFilter,
  ]);

  useEffect(() => {
    if (typeof window === "undefined") return;

    window.localStorage.setItem(
      "orders-visible-columns",
      JSON.stringify(visibleOptionalColumns),
    );
  }, [visibleOptionalColumns]);

  useEffect(
    () => () => exportAbortControllerRef.current?.abort(),
    [],
  );

  let createdAtStart: string | null = null;
  let createdAtEnd: string | null = null;

  if (createdDatePreset === "last_week") {
    createdAtStart = dateTime().subtract(7, "day").startOf("day").toISOString();
    createdAtEnd = dateTime().endOf("day").toISOString();
  } else if (createdDatePreset === "last_month") {
    createdAtStart = dateTime().subtract(1, "month").startOf("day").toISOString();
    createdAtEnd = dateTime().endOf("day").toISOString();
  } else if (createdDatePreset === "custom") {
    if (createdStartDate) {
      createdAtStart = dateTime(createdStartDate).startOf("day").toISOString();
    }

    if (createdEndDate) {
      createdAtEnd = dateTime(createdEndDate).endOf("day").toISOString();
    }
  }

  const currentCursor = pageCursors[page - 1] || null;
  const getOrderRpcArgs = (
    cursor: OrderPageCursor | null,
    limit: number,
  ) => ({
    p_created_from: createdAtStart,
    p_created_to: createdAtEnd,
    p_cursor_created_at: cursor?.createdAt ?? null,
    p_cursor_id: cursor?.id ?? null,
    p_limit: limit,
    p_product_id: productFilter === "all" ? null : productFilter,
    p_provider_platform:
      providerPlatformFilter === "all" ? null : providerPlatformFilter,
    p_search: search || null,
    p_shipping_state: stateFilter === "all" ? null : stateFilter,
    p_status_id: statusFilter === "all" ? null : statusFilter,
    p_tenant_id: currentTenantId || "",
  });

  const [
    { data: orderStatuses = [] },
    { data: products = [] },
    { data: providerPlatforms = [] },
    { data: availableStates = [] },
    {
      data: ordersResult = { data: [], hasNextPage: false },
      error: ordersError,
      isError: isOrdersError,
      isFetching: isOrdersFetching,
      isLoading,
      refetch: refetchOrders,
    },
  ] = useQueries({
    queries: [
      {
        // Fetch order statuses for filter dropdown
        queryKey: ["order-statuses-list"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("order_statuses")
            .select(
              "id, status_key, admin_status_label, is_terminal, next_step_owner",
            )
            .eq("is_active", true)
            .order("display_order", { ascending: true });

          if (error) throw error;
          return data as OrderStatus[];
        },
      },
      {
        queryKey: ["order-products", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await supabase
            .from("products")
            .select("id, name")
            .eq("tenant_id", currentTenantId)
            .order("name", { ascending: true });

          if (error) throw error;
          return data as { id: string; name: string }[];
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: ["order-provider-platforms", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data: platformIntegrations, error: platformIntegrationsError } =
            await supabase
              .from("platform_integrations")
              .select("key, name")
              .eq("is_active", true)
              .eq("category", "provider_platform")
              .order("name", { ascending: true });

          if (platformIntegrationsError) throw platformIntegrationsError;
          if (!platformIntegrations || platformIntegrations.length === 0) {
            return [];
          }

          const providerIntegrationKeys = platformIntegrations.map(
            (integration) => integration.key,
          );

          const { data: tenantIntegrations, error: tenantIntegrationsError } =
            await supabase
              .from("tenant_integrations")
              .select("integration_key")
              .eq("tenant_id", currentTenantId)
              .eq("is_enabled", true)
              .in("integration_key", providerIntegrationKeys);

          if (tenantIntegrationsError) throw tenantIntegrationsError;

          return (tenantIntegrations || [])
            .map((tenantIntegration) => {
              const platformIntegration = platformIntegrations.find(
                (integration) =>
                  integration.key === tenantIntegration.integration_key,
              );

              if (!platformIntegration) return null;

              return {
                key: tenantIntegration.integration_key,
                name: platformIntegration.name,
              };
            })
            .filter(
              (
                providerPlatform,
              ): providerPlatform is { key: string; name: string } =>
                providerPlatform !== null,
            );
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: ["order-states", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await supabase
            .rpc("get_tenant_order_states" as never, { p_tenant_id: currentTenantId } as never);

          if (error) throw error;

          const uniqueStateCodes = ((data as { state_code: string }[]) || []).map(
            (row) => row.state_code,
          );

          return US_STATES.filter((state) => uniqueStateCodes.includes(state.code));
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: [
          "orders",
          currentTenantId,
          search,
          statusFilter,
          productFilter,
          providerPlatformFilter,
          stateFilter,
          createdDatePreset,
          createdStartDate ? dateTime(createdStartDate).format("YYYY-MM-DD") : null,
          createdEndDate ? dateTime(createdEndDate).format("YYYY-MM-DD") : null,
          page,
          currentCursor?.createdAt ?? null,
          currentCursor?.id ?? null,
        ],
        queryFn: async ({ signal }) => {
          if (!currentTenantId) {
            return { data: [], hasNextPage: false };
          }

          const { data, error } = await supabase
            .rpc(
              "list_tenant_orders",
              getOrderRpcArgs(currentCursor, PAGE_SIZE + 1),
            )
            .abortSignal(signal);

          if (error) throw error;

          const rows = data || [];
          const mappedOrders = rows.slice(0, PAGE_SIZE).map((order) => ({
            id: order.id,
            order_number: order.order_number,
            status_id: order.status_id,
            provider_platform_integration_key:
              order.provider_platform_integration_key,
            total_cents: order.total_cents,
            discount_cents: order.discount_cents,
            metadata: order.metadata as Record<string, unknown> | null,
            created_at: order.created_at,
            renewal_at: order.renewal_at,
            shipping_state: order.shipping_state,
            subscription_order_type:
              order.subscription_order_type === "initial" ||
              order.subscription_order_type === "renewal"
                ? order.subscription_order_type
                : null,
            subscription: order.subscription_id
              ? {
                  id: order.subscription_id,
                  status: order.subscription_status || "",
                  current_period_end_at:
                    order.subscription_current_period_end_at,
                }
              : null,
            patients: {
              first_name: order.patient_first_name,
              last_name: order.patient_last_name,
              email: order.patient_email,
            },
            product: order.product_name ? { name: order.product_name } : null,
            order_status: order.order_status_id
              ? {
                  id: order.order_status_id,
                  status_key: order.order_status_key || "",
                  admin_status_label: order.order_status_label || "",
                  is_terminal: order.order_status_is_terminal || false,
                  next_step_owner: order.order_status_next_step_owner || "",
                }
              : null,
          })) satisfies OrderWithDetails[];

          return {
            data: mappedOrders,
            hasNextPage: rows.length > PAGE_SIZE,
          };
        },
        placeholderData: (previousData) => previousData,
        enabled: !!currentTenantId,
      },
    ],
  });

  const orders = ordersResult.data;
  const hasNextPage = !isOrdersFetching && ordersResult.hasNextPage;
  const ordersErrorMessage =
    ordersError &&
    typeof ordersError === "object" &&
    "message" in ordersError
      ? String(ordersError.message)
      : "The orders request failed. Please try again.";
  const hasActiveFilters =
    statusFilter !== "all" ||
    productFilter !== "all" ||
    providerPlatformFilter !== "all" ||
    stateFilter !== "all" ||
    hasCreatedDateFilter;

  useEffect(() => {
    if (hasActiveFilters) {
      setIsFiltersOpen(true);
    }
  }, [hasActiveFilters]);

  const clearFilters = () => {
    setStatusFilter("all");
    setProductFilter("all");
    setProviderPlatformFilter("all");
    setStateFilter("all");
    setCreatedDatePreset("all");
    setCreatedStartDate(undefined);
    setCreatedEndDate(undefined);
    setPage(1);
  };

  const toggleOptionalColumn = (
    columnKey: (typeof OPTIONAL_COLUMN_KEYS)[number],
    checked: boolean,
  ) => {
    setVisibleOptionalColumns((current) => ({
      ...current,
      [columnKey]: checked,
    }));
  };

  const handlePageChange = (nextPage: number) => {
    if (nextPage === page + 1 && hasNextPage && orders.length > 0) {
      const lastOrder = orders[orders.length - 1];
      const nextCursor = {
        createdAt: lastOrder.created_at,
        id: lastOrder.id,
      };

      setPageCursors((current) => [
        ...current.slice(0, page),
        nextCursor,
      ]);
      setPage(nextPage);
      return;
    }

    if (nextPage >= 1 && nextPage < page) {
      setPage(nextPage);
    }
  };

  const handleCancelExport = () => {
    exportAbortControllerRef.current?.abort();
  };

  const handleExportCsv = async () => {
    if (!currentTenantId || isExporting || !canExportOrders) return;

    const abortController = new AbortController();
    exportAbortControllerRef.current = abortController;
    setIsExporting(true);
    setExportedCount(0);

    try {
      const csvParts: string[] = [];
      let cursor: OrderPageCursor | null = null;
      let totalExported = 0;
      let isFirstBatch = true;

      while (true) {
        const { data, error } = await supabase
          .rpc(
            "export_tenant_orders_page",
            getOrderRpcArgs(cursor, EXPORT_BATCH_SIZE),
          )
          .abortSignal(abortController.signal);

        if (abortController.signal.aborted) {
          toast.info("Order export cancelled");
          return;
        }
        if (error) throw error;

        const rows = (data || []).map((order) => ({
          ...order,
          subscription_order_type:
            order.subscription_order_type === "initial" ||
            order.subscription_order_type === "renewal"
              ? order.subscription_order_type
              : null,
        })) satisfies OrderExportRow[];

        const csvChunk = buildOrdersCsvChunk(rows, isFirstBatch);
        if (csvChunk) {
          csvParts.push(isFirstBatch ? csvChunk : `\n${csvChunk}`);
        }

        isFirstBatch = false;
        totalExported += rows.length;
        setExportedCount(totalExported);

        if (rows.length < EXPORT_BATCH_SIZE) {
          break;
        }

        const lastOrder = rows[rows.length - 1];
        cursor = { createdAt: lastOrder.created_at, id: lastOrder.id };
      }

      if (isFirstBatch) {
        csvParts.push(buildOrdersCsvChunk([], true));
      }

      const blob = new Blob(["\uFEFF", ...csvParts], {
        type: "text/csv;charset=utf-8;",
      });
      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement("a");

      link.href = downloadUrl;
      link.download = `orders-export-${dateTime().format("YYYY-MM-DD-HHmmss")}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(downloadUrl);

      toast.success(`Exported ${totalExported} orders to CSV`);
    } catch (error) {
      if (abortController.signal.aborted) {
        toast.info("Order export cancelled");
        return;
      }

      toast.error(
        error instanceof Error ? error.message : "Failed to export orders",
      );
    } finally {
      exportAbortControllerRef.current = null;
      setIsExporting(false);
    }
  };

  const columns: Column<OrderWithDetails>[] = [
    {
      key: "order_number",
      header: "Order #",
      cell: (order) => (
        <span className="font-mono font-medium">{order.order_number}</span>
      ),
    },
    {
      key: "patient",
      header: "Patient",
      cell: (order) => <span>{formatPatientName(order)}</span>,
    },
    {
      key: "patient_email",
      header: "Patient Email",
      cell: (order) => order.patients?.email ?? "—",
    },
    {
      key: "product",
      header: "Product",
      cell: (order) => order.product?.name ?? "—",
    },
    {
      key: "subscription_id",
      header: "Subscription ID",
      cell: (order) => {
        if (!order.subscription) return "—";

        return formatSubscriptionId(order);
      },
      className: "font-mono text-xs",
    },
    {
      key: "subscription_status",
      header: "Subscription Status",
      cell: (order) => {
        if (!order.subscription) return "—";

        return (
          <Badge variant="secondary">
            {formatSubscriptionStatus(order.subscription.status)}
          </Badge>
        );
      },
    },
    {
      key: "shipping_state",
      header: "Shipping State",
      cell: (order) => order.shipping_state ?? "—",
    },
    {
      key: "subscription_order_type",
      header: "Type",
      cell: (order) => {
        if (!order.subscription_order_type) return "—";

        return (
          <Badge
            variant={
              order.subscription_order_type === "initial"
                ? "default"
                : "secondary"
            }
          >
            {formatSubscriptionOrderType(order.subscription_order_type)}
          </Badge>
        );
      },
    },
    {
      key: "status",
      header: "Status",
      cell: (order) => (
        <OrderStatusBadge
          status={order.order_status}
          fallbackLabel="No Status"
        />
      ),
    },
    {
      key: "migration",
      header: "Migration",
      cell: (order) => (
        <MigrationBadge metadata={order.metadata} entityType="order" />
      ),
    },
    {
      key: "total",
      header: "Total Paid",
      cell: (order) => {
        const paid = formatCurrency(order.total_cents);
        if (order.discount_cents > 0) {
          const originalCents = order.total_cents + order.discount_cents;
          const discountPct = Math.round(
            (order.discount_cents / originalCents) * 100,
          );
          return (
            <div className="flex flex-col items-end">
              <span>{paid}</span>
              <span className="text-xs text-green-600">{discountPct}% off</span>
            </div>
          );
        }
        return paid;
      },
      className: "text-right",
    },
    {
      key: "created",
      header: "Created",
      cell: (order) => dateTime(order.created_at).format("MMM D, YYYY"),
    },
    {
      key: "renewal",
      header: "Renewal",
      cell: (order) => {
        const renewalAt = order.subscription?.current_period_end_at;
        return renewalAt ? dateTime(renewalAt).format("MMM D, YYYY") : "—";
      },
    },
  ];

  const visibleColumns = columns.filter((column) => {
    if (!OPTIONAL_COLUMN_KEYS.includes(column.key as (typeof OPTIONAL_COLUMN_KEYS)[number])) {
      return true;
    }

    return visibleOptionalColumns[column.key as (typeof OPTIONAL_COLUMN_KEYS)[number]];
  });

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Orders"
        description="Manage and track patient orders"
      />

      <Collapsible
        open={isFiltersOpen}
        onOpenChange={setIsFiltersOpen}
        className="mb-6 rounded-xl border bg-card/80 shadow-sm"
      >
        <div className="p-4">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="space-y-1">
              <div className="flex items-center gap-2 text-sm font-medium">
                <Filter className="h-4 w-4 text-muted-foreground" />
                <span>Filters</span>
                {hasActiveFilters && (
                  <Badge variant="secondary" className="rounded-full px-2 py-0">
                    {[
                      statusFilter,
                      productFilter,
                      providerPlatformFilter,
                      stateFilter,
                    ].filter((value) => value !== "all").length +
                      (hasCreatedDateFilter ? 1 : 0)}{" "}
                    active
                  </Badge>
                )}
              </div>
              <p className="text-sm text-muted-foreground">
                Narrow the order list by workflow status, product, provider
                platform, shipping state, or order creation date.
              </p>
            </div>

            <div className="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
              <Button
                variant="outline"
                size="sm"
                onClick={clearFilters}
                disabled={!hasActiveFilters}
                className="w-full sm:w-auto"
              >
                <RotateCcw className="h-4 w-4" />
                Clear filters
              </Button>
              <CollapsibleTrigger asChild>
                <Button
                  variant="ghost"
                  size="sm"
                  className="w-full justify-between sm:w-auto sm:justify-center"
                >
                  {isFiltersOpen ? "Hide filters" : "Show filters"}
                  <ChevronDown
                    className={`h-4 w-4 transition-transform ${
                      isFiltersOpen ? "rotate-180" : ""
                    }`}
                  />
                </Button>
              </CollapsibleTrigger>
            </div>
          </div>
        </div>

        <CollapsibleContent className="border-t px-4 pb-4">
          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div className="space-y-2">
              <Label
                htmlFor="orders-status-filter"
                className="text-xs uppercase tracking-[0.12em] text-muted-foreground"
              >
                Order status
              </Label>
              <Select
                value={statusFilter}
                onValueChange={(value) => {
                  setStatusFilter(value);
                  setPage(1);
                }}
              >
                <SelectTrigger
                  id="orders-status-filter"
                  className="w-full bg-background"
                >
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem>
                  {orderStatuses.map((status) => (
                    <SelectItem key={status.id} value={status.id}>
                      {status.admin_status_label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label
                htmlFor="orders-product-filter"
                className="text-xs uppercase tracking-[0.12em] text-muted-foreground"
              >
                Product
              </Label>
              <Select
                value={productFilter}
                onValueChange={(value) => {
                  setProductFilter(value);
                  setPage(1);
                }}
              >
                <SelectTrigger
                  id="orders-product-filter"
                  className="w-full bg-background"
                >
                  <SelectValue placeholder="Filter by product" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Products</SelectItem>
                  {products.map((product) => (
                    <SelectItem key={product.id} value={product.id}>
                      {product.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label
                htmlFor="orders-provider-platform-filter"
                className="text-xs uppercase tracking-[0.12em] text-muted-foreground"
              >
                Provider platform
              </Label>
              <Select
                value={providerPlatformFilter}
                onValueChange={(value) => {
                  setProviderPlatformFilter(value);
                  setPage(1);
                }}
              >
                <SelectTrigger
                  id="orders-provider-platform-filter"
                  className="w-full bg-background"
                >
                  <SelectValue placeholder="Filter by provider platform" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Provider Platforms</SelectItem>
                  {providerPlatforms.map((providerPlatform) => (
                    <SelectItem
                      key={providerPlatform.key}
                      value={providerPlatform.key}
                    >
                      {providerPlatform.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label
                htmlFor="orders-state-filter"
                className="text-xs uppercase tracking-[0.12em] text-muted-foreground"
              >
                State
              </Label>
              <Select
                value={stateFilter}
                onValueChange={(value) => {
                  setStateFilter(value);
                  setPage(1);
                }}
              >
                <SelectTrigger
                  id="orders-state-filter"
                  className="w-full bg-background"
                >
                  <SelectValue placeholder="Filter by state" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All States</SelectItem>
                  {availableStates.map((state) => (
                    <SelectItem key={state.code} value={state.code}>
                      {state.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label
                htmlFor="orders-created-date-filter"
                className="text-xs uppercase tracking-[0.12em] text-muted-foreground"
              >
                Created date
              </Label>
              <Select
                value={createdDatePreset}
                onValueChange={(value) => {
                  const nextValue = value as CreatedDatePreset;
                  setCreatedDatePreset(nextValue);
                  if (nextValue !== "custom") {
                    setCreatedStartDate(undefined);
                    setCreatedEndDate(undefined);
                  }
                  setPage(1);
                }}
              >
                <SelectTrigger
                  id="orders-created-date-filter"
                  className="w-full bg-background"
                >
                  <SelectValue placeholder="Filter by created date" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Dates</SelectItem>
                  <SelectItem value="last_week">Last Week</SelectItem>
                  <SelectItem value="last_month">Last Month</SelectItem>
                  <SelectItem value="custom">Custom Range</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {createdDatePreset === "custom" && (
            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
              <div className="space-y-2">
                <Label className="text-xs uppercase tracking-[0.12em] text-muted-foreground">
                  Created from
                </Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      variant="outline"
                      className={cn(
                        "w-full justify-start bg-background text-left font-normal",
                        !createdStartDate && "text-muted-foreground",
                      )}
                    >
                      <CalendarIcon className="mr-2 h-4 w-4" />
                      {createdStartDate
                        ? dateTime(createdStartDate).format("MMM D, YYYY")
                        : "Start date"}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                      mode="single"
                      selected={createdStartDate}
                      onSelect={(date) => {
                        setCreatedStartDate(date);
                        setPage(1);
                      }}
                      disabled={(date) =>
                        (createdEndDate ? date > createdEndDate : false) ||
                        date > dateTime().toDate()
                      }
                      initialFocus
                      className={cn("p-3 pointer-events-auto")}
                    />
                  </PopoverContent>
                </Popover>
              </div>

              <div className="space-y-2">
                <Label className="text-xs uppercase tracking-[0.12em] text-muted-foreground">
                  Created to
                </Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      variant="outline"
                      className={cn(
                        "w-full justify-start bg-background text-left font-normal",
                        !createdEndDate && "text-muted-foreground",
                      )}
                    >
                      <CalendarIcon className="mr-2 h-4 w-4" />
                      {createdEndDate
                        ? dateTime(createdEndDate).format("MMM D, YYYY")
                        : "End date"}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                      mode="single"
                      selected={createdEndDate}
                      onSelect={(date) => {
                        setCreatedEndDate(date);
                        setPage(1);
                      }}
                      disabled={(date) =>
                        (createdStartDate ? date < createdStartDate : false) ||
                        date > dateTime().toDate()
                      }
                      initialFocus
                      className={cn("p-3 pointer-events-auto")}
                    />
                  </PopoverContent>
                </Popover>
              </div>

              {(createdStartDate || createdEndDate) && (
                <div className="flex items-end">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setCreatedStartDate(undefined);
                      setCreatedEndDate(undefined);
                      setPage(1);
                    }}
                    className="h-10 px-3"
                  >
                    <X className="h-4 w-4" />
                    Clear dates
                  </Button>
                </div>
              )}
            </div>
          )}
        </CollapsibleContent>
      </Collapsible>

      {isOrdersError && (
        <Alert variant="destructive" className="mb-4">
          <CircleAlert className="h-4 w-4" />
          <AlertTitle>Unable to load orders</AlertTitle>
          <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span>{ordersErrorMessage}</span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => refetchOrders()}
            >
              Retry
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {isOrdersFetching && !isLoading && !isOrdersError && (
        <div className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Updating orders…
        </div>
      )}

      <DataTable
        columns={visibleColumns}
        data={orders}
        isLoading={isLoading && orders.length === 0}
        searchPlaceholder={
          "Search by order number, provider order ID, or patient name..."
        }
        searchValue={searchInput}
        onSearchChange={(value) => {
          setSearchInput(value);
        }}
        toolbarContent={
          <div className="flex items-center gap-2">
            {canExportOrders && (
              <Button
                variant="outline"
                size="sm"
                onClick={isExporting ? handleCancelExport : handleExportCsv}
                disabled={!currentTenantId}
              >
                {isExporting ? (
                  <X className="h-4 w-4" />
                ) : (
                  <Download className="h-4 w-4" />
                )}
                {isExporting
                  ? `Cancel export (${exportedCount.toLocaleString()})`
                  : "Export CSV"}
              </Button>
            )}
            <DropdownMenu
              open={isColumnsMenuOpen}
              onOpenChange={setIsColumnsMenuOpen}
            >
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                  Columns
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <div className="flex items-center justify-between px-2 py-1.5">
                  <DropdownMenuLabel className="p-0">
                    Optional Columns
                  </DropdownMenuLabel>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7"
                    onClick={() => setIsColumnsMenuOpen(false)}
                  >
                    <X className="h-4 w-4" />
                    <span className="sr-only">Close columns menu</span>
                  </Button>
                </div>
                <DropdownMenuSeparator />
                {OPTIONAL_COLUMN_KEYS.map((columnKey) => (
                  <DropdownMenuCheckboxItem
                    key={columnKey}
                    checked={visibleOptionalColumns[columnKey]}
                    onSelect={(event) => event.preventDefault()}
                    onCheckedChange={(checked) =>
                      toggleOptionalColumn(columnKey, checked === true)
                    }
                  >
                    {OPTIONAL_COLUMN_LABELS[columnKey]}
                  </DropdownMenuCheckboxItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        }
        emptyMessage={
          isOrdersError ? "Unable to load orders" : "No matching orders found"
        }
        page={page}
        pageSize={PAGE_SIZE}
        hasNextPage={hasNextPage}
        onPageChange={handlePageChange}
        onRowClick={(order) =>
          navigate(`${ROUTES.TENANT_ADMIN.ORDERS}/${order.id}`)
        }
      />
    </AdminLayout>
  );
}
