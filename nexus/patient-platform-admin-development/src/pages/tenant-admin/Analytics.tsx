import { useMemo, useState } from "react";
import type { ComponentProps } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowDownRight,
  ArrowUpRight,
  Minus,
} from "lucide-react";
import { Line, LineChart, XAxis, YAxis } from "recharts";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { supabase } from "@/integrations/supabase/client";
import { US_STATES } from "@/lib/usStates";
import { useAuth } from "@/stores/authStore";

type AnalyticsUnit = "day" | "week" | "month";
type OrderTypeFilter = "all" | "initial" | "renewal";

interface OrderTimeseriesRow {
  bucket_label: string;
  total_orders: number;
  initial_orders: number;
  renewal_orders: number;
}

interface OrderByProductRow {
  bucket_label: string;
  product_id: string;
  product_name: string;
  order_count: number;
}

interface OrderByProviderRow {
  bucket_label: string;
  provider_key: string;
  provider_name: string;
  order_count: number;
}

interface SubscriptionTimeseriesRow {
  bucket_label: string;
  new_subscriptions: number;
  total_subscriptions: number;
  churned_subscriptions: number;
}

interface ProductTrendRow {
  product_name: string;
  today_count: number;
  yesterday_count: number;
}

interface SubscriptionDailyTrendRow {
  day_label: string;
  new_subscriptions: number;
  churned_subscriptions: number;
}

interface AnalyticsSummary {
  patient_count: number;
  provider_platform_count: number;
  filter_products: { id: string; name: string }[];
  filter_states: string[];
}

interface HistoryPoint {
  label: string;
  value: number;
}

interface HistoryMetric {
  value: number;
  previous: number;
  history: HistoryPoint[];
}

interface OrderTypeComparisonMetric {
  initialOrders: HistoryMetric;
  renewalOrders: HistoryMetric;
}

interface ProductHistorySeries {
  key: string;
  label: string;
  color: string;
}

interface ProductHistoryChart {
  data: Array<Record<string, string | number>>;
  series: ProductHistorySeries[];
  config: ChartConfig;
}

interface FilterOption {
  value: string;
  label: string;
}

interface AnalyticsMetrics {
  orderTimeseries: OrderTimeseriesRow[];
  subscriptionTimeseries: SubscriptionTimeseriesRow[];
  orderByProduct: OrderByProductRow[];
  orderByProvider: OrderByProviderRow[];
  productTrends: ProductTrendRow[];
  subscriptionDailyTrend: SubscriptionDailyTrendRow[];
}

const metricChartConfig = {
  value: {
    label: "Value",
    color: "hsl(var(--primary))",
  },
} satisfies ChartConfig;

const orderTypeChartConfig = {
  totalOrders: {
    label: "Total orders",
    color: "#6366f1",
  },
  initialOrders: {
    label: "New orders",
    color: "#0ea5e9",
  },
  renewalOrders: {
    label: "Renewals",
    color: "#10b981",
  },
} satisfies ChartConfig;

const PRODUCT_LINE_COLORS = [
  "#0ea5e9",
  "#10b981",
  "#f97316",
  "#8b5cf6",
  "#ec4899",
  "#14b8a6",
  "#eab308",
  "#6366f1",
] as const;

function formatTrend(current: number, previous: number) {
  const delta = current - previous;
  const percent = previous === 0
    ? null
    : Math.abs((delta / previous) * 100);

  return {
    delta,
    percent,
    direction: delta === 0 ? "flat" : delta > 0 ? "up" : "down",
  } as const;
}

function formatMetricValue(value: number) {
  if (Number.isInteger(value)) {
    return value.toLocaleString();
  }
  return value.toFixed(1);
}

function formatPeriodLabel(unit: AnalyticsUnit) {
  if (unit === "day") return "day";
  if (unit === "week") return "week";
  return "month";
}

function buildHistoryMetric(rows: { bucket_label: string }[], getValue: (row: { bucket_label: string }) => number): HistoryMetric {
  const history = rows.map((row) => ({
    label: row.bucket_label,
    value: getValue(row),
  }));
  return {
    value: history.at(-1)?.value ?? 0,
    previous: history.at(-2)?.value ?? 0,
    history,
  };
}

function buildProductHistoryChart(
  orderByProduct: OrderByProductRow[],
): ProductHistoryChart {
  // Get unique bucket labels in order
  const bucketLabels: string[] = [];
  const seenLabels = new Set<string>();
  for (const row of orderByProduct) {
    if (!seenLabels.has(row.bucket_label)) {
      seenLabels.add(row.bucket_label);
      bucketLabels.push(row.bucket_label);
    }
  }

  // Get unique products
  const productMap = new Map<string, { id: string; name: string }>();
  for (const row of orderByProduct) {
    if (!productMap.has(row.product_id)) {
      productMap.set(row.product_id, { id: row.product_id, name: row.product_name });
    }
  }

  const products = Array.from(productMap.values())
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((p, index) => ({
      key: `product_${p.id.replace(/-/g, "_")}`,
      label: p.name,
      color: PRODUCT_LINE_COLORS[index % PRODUCT_LINE_COLORS.length],
      id: p.id,
    }));

  // Build lookup: bucket_label -> product_id -> count
  const countLookup = new Map<string, Map<string, number>>();
  for (const row of orderByProduct) {
    let bucketMap = countLookup.get(row.bucket_label);
    if (!bucketMap) {
      bucketMap = new Map();
      countLookup.set(row.bucket_label, bucketMap);
    }
    bucketMap.set(row.product_id, row.order_count);
  }

  const data = bucketLabels.map((label) => {
    const point: Record<string, string | number> = { label };
    const bucketMap = countLookup.get(label);
    for (const product of products) {
      point[product.key] = bucketMap?.get(product.id) ?? 0;
    }
    return point;
  });

  const config = products.reduce<ChartConfig>((acc, product) => {
    acc[product.key] = { label: product.label, color: product.color };
    return acc;
  }, {});

  return {
    data,
    series: products.map(({ id: _, ...rest }) => rest),
    config,
  };
}

function buildProviderHistoryChart(
  orderByProvider: OrderByProviderRow[],
): ProductHistoryChart {
  // Get unique bucket labels in order
  const bucketLabels: string[] = [];
  const seenLabels = new Set<string>();
  for (const row of orderByProvider) {
    if (!seenLabels.has(row.bucket_label)) {
      seenLabels.add(row.bucket_label);
      bucketLabels.push(row.bucket_label);
    }
  }

  // Get unique providers
  const providerMap = new Map<string, string>();
  for (const row of orderByProvider) {
    if (!providerMap.has(row.provider_key)) {
      providerMap.set(row.provider_key, row.provider_name);
    }
  }

  const providers = Array.from(providerMap.entries())
    .sort((a, b) => a[1].localeCompare(b[1]))
    .map(([key, name], index) => ({
      key: `provider_${key.replace(/[^a-zA-Z0-9]/g, "_")}`,
      label: name,
      color: PRODUCT_LINE_COLORS[index % PRODUCT_LINE_COLORS.length],
      integrationKey: key,
    }));

  // Build lookup: bucket_label -> provider_key -> count
  const countLookup = new Map<string, Map<string, number>>();
  for (const row of orderByProvider) {
    let bucketMap = countLookup.get(row.bucket_label);
    if (!bucketMap) {
      bucketMap = new Map();
      countLookup.set(row.bucket_label, bucketMap);
    }
    bucketMap.set(row.provider_key, row.order_count);
  }

  const data = bucketLabels.map((label) => {
    const point: Record<string, string | number> = { label };
    const bucketMap = countLookup.get(label);
    for (const provider of providers) {
      point[provider.key] = bucketMap?.get(provider.integrationKey) ?? 0;
    }
    return point;
  });

  const config = providers.reduce<ChartConfig>((acc, provider) => {
    acc[provider.key] = { label: provider.label, color: provider.color };
    return acc;
  }, {});

  return {
    data,
    series: providers.map(({ integrationKey: _, ...rest }) => rest),
    config,
  };
}

function TrendPill({
  current,
  previous,
  suffix,
}: {
  current: number;
  previous: number;
  suffix: string;
}) {
  const trend = formatTrend(current, previous);

  if (trend.direction === "flat") {
    return (
      <div className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground">
        <Minus className="h-3.5 w-3.5" />
        Flat {suffix}
      </div>
    );
  }

  const Icon = trend.direction === "up" ? ArrowUpRight : ArrowDownRight;
  const tone =
    trend.direction === "up"
      ? "bg-emerald-50 text-emerald-700"
      : "bg-rose-50 text-rose-700";
  const trendText = trend.percent === null
    ? `${Math.abs(trend.delta)} ${suffix}`
    : `${Math.round(trend.percent)}% ${suffix}`;

  return (
    <div className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ${tone}`}>
      <Icon className="h-3.5 w-3.5" />
      {trendText}
    </div>
  );
}

function HistoryMetricCard({
  title,
  value,
  description,
  history,
  unit,
  className,
}: {
  title: string;
  value: number;
  description: string;
  history: HistoryPoint[];
  unit: AnalyticsUnit;
  className?: ComponentProps<typeof Card>["className"];
}) {
  const previous = history.at(-2)?.value ?? 0;
  const chartMax = Math.max(1, ...history.map((point) => point.value));

  return (
    <Card className={className}>
      <CardHeader className="space-y-3 pb-3">
        <div className="flex items-start justify-between gap-3">
          <div>
            <CardDescription>{title}</CardDescription>
            <CardTitle className="mt-2 text-3xl">{formatMetricValue(value)}</CardTitle>
          </div>
          <TrendPill
            current={value}
            previous={previous}
            suffix={`vs previous ${formatPeriodLabel(unit)}`}
          />
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <ChartContainer config={metricChartConfig} className="h-28 w-full">
          <LineChart data={history} margin={{ left: -24, right: 8, top: 8, bottom: 0 }}>
            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={16}
            />
            <YAxis hide domain={[0, chartMax]} />
            <ChartTooltip
              cursor={false}
              content={<ChartTooltipContent hideIndicator labelKey="label" />}
            />
            <Line
              type="monotone"
              dataKey="value"
              stroke="var(--color-value)"
              strokeWidth={2.5}
              dot={{ r: 0 }}
              activeDot={{ r: 4 }}
            />
          </LineChart>
        </ChartContainer>
        <p className="text-sm text-muted-foreground">{description}</p>
      </CardContent>
    </Card>
  );
}

function OrderTypeComparisonCard({
  metric,
  unit,
}: {
  metric: OrderTypeComparisonMetric;
  unit: AnalyticsUnit;
}) {
  const chartData = metric.initialOrders.history.map((point, index) => ({
    label: point.label,
    totalOrders: point.value + (metric.renewalOrders.history[index]?.value ?? 0),
    initialOrders: point.value,
    renewalOrders: metric.renewalOrders.history[index]?.value ?? 0,
  }));
  const chartMax = Math.max(
    1,
    ...chartData.flatMap((point) => [
      point.totalOrders,
      point.initialOrders,
      point.renewalOrders,
    ]),
  );
  const totalOrders = metric.initialOrders.value + metric.renewalOrders.value;
  const previousTotalOrders =
    metric.initialOrders.previous + metric.renewalOrders.previous;

  return (
    <Card>
      <CardHeader>
        <CardTitle>New Orders vs Renewals</CardTitle>
        <CardDescription>
          Compare initial orders against renewal orders for the current {formatPeriodLabel(unit)}.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-lg border p-4">
            <p className="text-sm font-medium text-muted-foreground">Total orders</p>
            <div className="mt-2 flex items-start justify-between gap-3">
              <p className="text-3xl font-semibold">
                {totalOrders.toLocaleString()}
              </p>
              <TrendPill
                current={totalOrders}
                previous={previousTotalOrders}
                suffix={`vs previous ${formatPeriodLabel(unit)}`}
              />
            </div>
          </div>

          <div className="rounded-lg border p-4">
            <p className="text-sm font-medium text-muted-foreground">New orders</p>
            <div className="mt-2 flex items-start justify-between gap-3">
              <p className="text-3xl font-semibold">
                {metric.initialOrders.value.toLocaleString()}
              </p>
              <TrendPill
                current={metric.initialOrders.value}
                previous={metric.initialOrders.previous}
                suffix={`vs previous ${formatPeriodLabel(unit)}`}
              />
            </div>
          </div>

          <div className="rounded-lg border p-4">
            <p className="text-sm font-medium text-muted-foreground">Renewals</p>
            <div className="mt-2 flex items-start justify-between gap-3">
              <p className="text-3xl font-semibold">
                {metric.renewalOrders.value.toLocaleString()}
              </p>
              <TrendPill
                current={metric.renewalOrders.value}
                previous={metric.renewalOrders.previous}
                suffix={`vs previous ${formatPeriodLabel(unit)}`}
              />
            </div>
          </div>
        </div>

        <ChartContainer config={orderTypeChartConfig} className="h-56 w-full">
          <LineChart data={chartData} margin={{ left: -20, right: 12, top: 8, bottom: 0 }}>
            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={16}
            />
            <YAxis hide domain={[0, chartMax]} />
            <ChartTooltip
              cursor={false}
              content={<ChartTooltipContent hideIndicator labelKey="label" />}
            />
            <Line
              type="monotone"
              dataKey="totalOrders"
              stroke="var(--color-totalOrders)"
              strokeWidth={2.5}
              dot={{ r: 0 }}
              activeDot={{ r: 4 }}
            />
            <Line
              type="monotone"
              dataKey="initialOrders"
              stroke="var(--color-initialOrders)"
              strokeWidth={2.5}
              dot={{ r: 0 }}
              activeDot={{ r: 4 }}
            />
            <Line
              type="monotone"
              dataKey="renewalOrders"
              stroke="var(--color-renewalOrders)"
              strokeWidth={2.5}
              dot={{ r: 0 }}
              activeDot={{ r: 4 }}
            />
          </LineChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}

export function TenantAnalyticsContent() {
  const { currentTenantId } = useAuth();
  const [analyticsUnit, setAnalyticsUnit] = useState<AnalyticsUnit>("day");
  const [productFilter, setProductFilter] = useState("all");
  const [stateFilter, setStateFilter] = useState("all");
  const [orderTypeFilter, setOrderTypeFilter] = useState<OrderTypeFilter>("all");

  // Fetch filter options and summary (once, independent of filters)
  const { data: summary } = useQuery({
    queryKey: ["tenant-analytics-summary", currentTenantId],
    queryFn: async (): Promise<AnalyticsSummary | null> => {
      if (!currentTenantId) return null;
      const { data, error } = await supabase.rpc(
        "get_analytics_summary" as never,
        { p_tenant_id: currentTenantId } as never,
      );
      if (error) throw error;
      const row = (data as unknown as AnalyticsSummary[])?.[0];
      if (!row) return null;
      return row;
    },
    enabled: !!currentTenantId,
  });

  // Fetch all analytics metrics (re-fetches when filters change)
  const { data: metrics, isLoading } = useQuery({
    queryKey: [
      "tenant-analytics",
      currentTenantId,
      analyticsUnit,
      productFilter,
      stateFilter,
      orderTypeFilter,
    ],
    queryFn: async (): Promise<AnalyticsMetrics | null> => {
      if (!currentTenantId) return null;

      const rpcProductId = productFilter === "all" ? null : productFilter;
      const rpcState = stateFilter === "all" ? null : stateFilter;
      const rpcOrderType = orderTypeFilter === "all" ? null : orderTypeFilter;

      const [orderTimeseriesRes, subTimeseriesRes, orderByProductRes, orderByProviderRes, productTrendsRes, subDailyTrendRes] =
        await Promise.all([
          supabase.rpc("get_analytics_order_timeseries" as never, {
            p_tenant_id: currentTenantId,
            p_unit: analyticsUnit,
            p_product_id: rpcProductId,
            p_state: rpcState,
            p_order_type: rpcOrderType,
          } as never),
          supabase.rpc("get_analytics_subscription_timeseries" as never, {
            p_tenant_id: currentTenantId,
            p_unit: analyticsUnit,
            p_product_id: rpcProductId,
            p_state: rpcState,
          } as never),
          supabase.rpc("get_analytics_order_by_product" as never, {
            p_tenant_id: currentTenantId,
            p_unit: analyticsUnit,
            p_product_id: rpcProductId,
            p_state: rpcState,
            p_order_type: rpcOrderType,
          } as never),
          supabase.rpc("get_analytics_order_by_provider" as never, {
            p_tenant_id: currentTenantId,
            p_unit: analyticsUnit,
            p_product_id: rpcProductId,
            p_state: rpcState,
            p_order_type: rpcOrderType,
          } as never),
          supabase.rpc("get_analytics_product_trends" as never, {
            p_tenant_id: currentTenantId,
            p_product_id: rpcProductId,
            p_state: rpcState,
            p_order_type: rpcOrderType,
          } as never),
          supabase.rpc("get_analytics_subscription_daily_trend" as never, {
            p_tenant_id: currentTenantId,
            p_product_id: rpcProductId,
            p_state: rpcState,
          } as never),
        ]);

      if (orderTimeseriesRes.error) throw (orderTimeseriesRes as { error: unknown }).error;
      if (subTimeseriesRes.error) throw (subTimeseriesRes as { error: unknown }).error;
      if (orderByProductRes.error) throw (orderByProductRes as { error: unknown }).error;
      if (orderByProviderRes.error) throw (orderByProviderRes as { error: unknown }).error;
      if (productTrendsRes.error) throw (productTrendsRes as { error: unknown }).error;
      if (subDailyTrendRes.error) throw (subDailyTrendRes as { error: unknown }).error;

      return {
        orderTimeseries: (orderTimeseriesRes.data || []) as unknown as OrderTimeseriesRow[],
        subscriptionTimeseries: (subTimeseriesRes.data || []) as unknown as SubscriptionTimeseriesRow[],
        orderByProduct: (orderByProductRes.data || []) as unknown as OrderByProductRow[],
        orderByProvider: (orderByProviderRes.data || []) as unknown as OrderByProviderRow[],
        productTrends: (productTrendsRes.data || []) as unknown as ProductTrendRow[],
        subscriptionDailyTrend: (subDailyTrendRes.data || []) as unknown as SubscriptionDailyTrendRow[],
      };
    },
    enabled: !!currentTenantId,
  });

  // Derive chart-ready structures from the RPC results
  const orderTypeComparison = useMemo<OrderTypeComparisonMetric | null>(() => {
    if (!metrics) return null;
    return {
      initialOrders: buildHistoryMetric(metrics.orderTimeseries, (r) => (r as OrderTimeseriesRow).initial_orders),
      renewalOrders: buildHistoryMetric(metrics.orderTimeseries, (r) => (r as OrderTimeseriesRow).renewal_orders),
    };
  }, [metrics]);

  const newSubscriptionsMetric = useMemo<HistoryMetric | null>(
    () => metrics ? buildHistoryMetric(metrics.subscriptionTimeseries, (r) => (r as SubscriptionTimeseriesRow).new_subscriptions) : null,
    [metrics],
  );

  const totalSubscriptionsMetric = useMemo<HistoryMetric | null>(
    () => metrics ? buildHistoryMetric(metrics.subscriptionTimeseries, (r) => (r as SubscriptionTimeseriesRow).total_subscriptions) : null,
    [metrics],
  );

  const churnedSubscriptionsMetric = useMemo<HistoryMetric | null>(
    () => metrics ? buildHistoryMetric(metrics.subscriptionTimeseries, (r) => (r as SubscriptionTimeseriesRow).churned_subscriptions) : null,
    [metrics],
  );

  const productHistoryChart = useMemo<ProductHistoryChart | null>(
    () => metrics ? buildProductHistoryChart(metrics.orderByProduct) : null,
    [metrics],
  );

  const providerPlatformHistoryChart = useMemo<ProductHistoryChart | null>(
    () => metrics ? buildProviderHistoryChart(metrics.orderByProvider) : null,
    [metrics],
  );

  const productOptions = useMemo<FilterOption[]>(() => {
    if (!summary?.filter_products) return [];
    return summary.filter_products.map((p) => ({ value: p.id, label: p.name }));
  }, [summary]);

  const stateOptions = useMemo<FilterOption[]>(() => {
    if (!summary?.filter_states) return [];
    return summary.filter_states
      .map((code) => {
        const state = US_STATES.find((s) => s.code === code);
        return state ? { value: state.code, label: state.name } : null;
      })
      .filter((item): item is FilterOption => item !== null);
  }, [summary]);

  const subscriptionDailyTrend = metrics?.subscriptionDailyTrend || [];
  const maxTrendValue = Math.max(
    1,
    ...subscriptionDailyTrend.flatMap((point) => [
      point.new_subscriptions,
      point.churned_subscriptions,
    ]),
  );

  return (
    <>
      <PageHeader
        title="Analytics"
        description="Monitor operational performance, subscription movement, and growth signals."
      />

      <div className="sticky top-0 z-20 mb-6 grid gap-4 border-b bg-background/95 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 xl:grid-cols-[1.1fr_0.9fr_0.9fr_0.9fr]">
          <div className="space-y-2">
            <p className="text-sm font-medium">Time unit</p>
            <ToggleGroup
              type="single"
              value={analyticsUnit}
              onValueChange={(value) => {
                if (value) {
                  setAnalyticsUnit(value as AnalyticsUnit);
                }
              }}
              variant="outline"
              className="justify-start"
            >
              <ToggleGroupItem value="day">Days</ToggleGroupItem>
              <ToggleGroupItem value="week">Weeks</ToggleGroupItem>
              <ToggleGroupItem value="month">Months</ToggleGroupItem>
            </ToggleGroup>
          </div>

          <div className="space-y-2">
            <p className="text-sm font-medium">Product</p>
            <Select value={productFilter} onValueChange={setProductFilter}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Filter by product" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All products</SelectItem>
                {productOptions.map((product) => (
                  <SelectItem key={product.value} value={product.value}>
                    {product.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <p className="text-sm font-medium">State</p>
            <Select value={stateFilter} onValueChange={setStateFilter}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Filter by state" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All states</SelectItem>
                {stateOptions.map((state) => (
                  <SelectItem key={state.value} value={state.value}>
                    {state.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <p className="text-sm font-medium">Order type</p>
            <Select
              value={orderTypeFilter}
              onValueChange={(value) => setOrderTypeFilter(value as OrderTypeFilter)}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Filter by order type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All order types</SelectItem>
                <SelectItem value="initial">First intake</SelectItem>
                <SelectItem value="renewal">Renewal</SelectItem>
              </SelectContent>
            </Select>
          </div>
      </div>

      <Tabs defaultValue="orders" className="space-y-6">
        <TabsList className="h-auto flex-wrap justify-start gap-2 bg-transparent p-0">
          <TabsTrigger value="orders" className="rounded-full border px-4 py-2 data-[state=active]:border-primary">
            Orders
          </TabsTrigger>
          <TabsTrigger value="subscriptions" className="rounded-full border px-4 py-2 data-[state=active]:border-primary">
            Subscriptions
          </TabsTrigger>
          <TabsTrigger value="provider-platforms" className="rounded-full border px-4 py-2 data-[state=active]:border-primary">
            Provider Platforms
          </TabsTrigger>
        </TabsList>

        <TabsContent value="orders" className="space-y-6">
          {isLoading || !orderTypeComparison ? (
            <div className="grid gap-4">
              <Skeleton className="h-[340px] rounded-xl" />
              <Skeleton className="h-[420px] rounded-xl" />
            </div>
          ) : (
            <>
              <div>
                <h2 className="text-lg font-semibold">Orders dashboard</h2>
                <p className="text-sm text-muted-foreground">
                  Showing the current period and the previous 10 values.
                </p>
              </div>

              <div className="grid gap-4">
                <OrderTypeComparisonCard
                  metric={orderTypeComparison}
                  unit={analyticsUnit}
                />

                <Card>
                  <CardHeader>
                    <CardTitle>Orders by product</CardTitle>
                    <CardDescription>
                      Order history by product for the selected time unit.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {!productHistoryChart || productHistoryChart.series.length === 0 ? (
                      <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                        No orders match the current product and state filters for this chart.
                      </div>
                    ) : (
                      <>
                        <ChartContainer
                          config={productHistoryChart.config}
                          className="h-72 w-full"
                        >
                          <LineChart
                            data={productHistoryChart.data}
                            margin={{ left: -20, right: 12, top: 8, bottom: 0 }}
                          >
                            <XAxis
                              dataKey="label"
                              tickLine={false}
                              axisLine={false}
                              tickMargin={8}
                              minTickGap={16}
                            />
                            <YAxis hide />
                            <ChartTooltip
                              cursor={false}
                              content={<ChartTooltipContent labelKey="label" />}
                            />
                            <ChartLegend
                              verticalAlign="top"
                              content={<ChartLegendContent />}
                            />
                            {productHistoryChart.series.map((product) => (
                              <Line
                                key={product.key}
                                type="monotone"
                                dataKey={product.key}
                                stroke={`var(--color-${product.key})`}
                                strokeWidth={2.5}
                                dot={{ r: 0 }}
                                activeDot={{ r: 4 }}
                              />
                            ))}
                          </LineChart>
                        </ChartContainer>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                          {productHistoryChart.series.map((product) => {
                            const currentValue =
                              Number(
                                productHistoryChart.data.at(-1)?.[product.key] ?? 0,
                              );
                            const previousValue =
                              Number(
                                productHistoryChart.data.at(-2)?.[product.key] ?? 0,
                              );

                            return (
                              <div
                                key={product.key}
                                className="rounded-lg border p-3"
                              >
                                <div className="flex items-start justify-between gap-3">
                                  <div className="min-w-0">
                                    <p className="truncate font-medium">{product.label}</p>
                                    <p className="text-2xl font-semibold">
                                      {currentValue.toLocaleString()}
                                    </p>
                                  </div>
                                  <div
                                    className="mt-1 h-3 w-3 shrink-0 rounded-full"
                                    style={{ backgroundColor: product.color }}
                                  />
                                </div>
                                <div className="mt-3">
                                  <TrendPill
                                    current={currentValue}
                                    previous={previousValue}
                                    suffix={`vs previous ${formatPeriodLabel(analyticsUnit)}`}
                                  />
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </>
                    )}
                  </CardContent>
                </Card>
              </div>
            </>
          )}
        </TabsContent>

        <TabsContent value="subscriptions" className="space-y-6">
          {isLoading || !newSubscriptionsMetric || !totalSubscriptionsMetric || !churnedSubscriptionsMetric ? (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-64 rounded-xl" />
              ))}
              <Skeleton className="h-[520px] rounded-xl md:col-span-2 xl:col-span-4" />
            </div>
          ) : (
            <>
              <div>
                <h2 className="text-lg font-semibold">Subscriptions dashboard</h2>
                <p className="text-sm text-muted-foreground">
                  Showing the current period and the previous 10 values.
                </p>
              </div>

              <div className="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.9fr)] xl:items-start">
                <div className="grid gap-4 md:grid-cols-2">
                  <HistoryMetricCard
                    title="New subscriptions"
                    value={newSubscriptionsMetric.value}
                    history={newSubscriptionsMetric.history}
                    unit={analyticsUnit}
                    description={`Subscriptions created in the current ${formatPeriodLabel(analyticsUnit)} and the previous 10 ${analyticsUnit}s.`}
                  />
                  <HistoryMetricCard
                    title="Total subscriptions"
                    value={totalSubscriptionsMetric.value}
                    history={totalSubscriptionsMetric.history}
                    unit={analyticsUnit}
                    description={`Live subscriptions at the end of each ${formatPeriodLabel(analyticsUnit)} over the last 11 ${analyticsUnit}s.`}
                  />
                  <HistoryMetricCard
                    title="Total churned"
                    value={churnedSubscriptionsMetric.value}
                    history={churnedSubscriptionsMetric.history}
                    unit={analyticsUnit}
                    className="md:col-span-2"
                    description={`Subscriptions cancelled during the current ${formatPeriodLabel(analyticsUnit)} and prior 10 ${analyticsUnit}s.`}
                  />
                </div>

                <Card>
                  <CardHeader>
                    <CardTitle>Quick subscription trend</CardTitle>
                    <CardDescription>
                      New subscriptions vs churn over the last 7 days.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {subscriptionDailyTrend.map((point) => {
                      const netChange = point.new_subscriptions - point.churned_subscriptions;
                      return (
                        <div key={point.day_label} className="space-y-2">
                          <div className="flex items-center justify-between text-sm">
                            <span className="font-medium">{point.day_label}</span>
                            <span className="text-muted-foreground">
                              Net {netChange >= 0 ? "+" : ""}
                              {netChange}
                            </span>
                          </div>
                          <div className="space-y-2">
                            <div className="flex items-center gap-3">
                              <span className="w-14 text-xs text-muted-foreground">New</span>
                              <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                  className="h-full rounded-full bg-emerald-500"
                                  style={{
                                    width: `${(point.new_subscriptions / maxTrendValue) * 100}%`,
                                  }}
                                />
                              </div>
                              <span className="w-6 text-right text-xs">{point.new_subscriptions}</span>
                            </div>
                            <div className="flex items-center gap-3">
                              <span className="w-14 text-xs text-muted-foreground">Churn</span>
                              <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                  className="h-full rounded-full bg-rose-500"
                                  style={{
                                    width: `${(point.churned_subscriptions / maxTrendValue) * 100}%`,
                                  }}
                                />
                              </div>
                              <span className="w-6 text-right text-xs">{point.churned_subscriptions}</span>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </CardContent>
                </Card>
              </div>
            </>
          )}
        </TabsContent>

        <TabsContent value="provider-platforms">
          {isLoading || !providerPlatformHistoryChart ? (
            <div className="grid gap-4">
              <Skeleton className="h-[520px] rounded-xl" />
            </div>
          ) : (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-semibold">Provider platforms dashboard</h2>
                <p className="text-sm text-muted-foreground">
                  Compare order load across provider platforms available to this tenant.
                </p>
              </div>

              <Card>
                <CardHeader>
                  <CardTitle>Provider load comparison</CardTitle>
                  <CardDescription>
                    Order volume by provider platform for the selected time unit.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {providerPlatformHistoryChart.series.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                      No provider platforms are enabled for this tenant.
                    </div>
                  ) : (
                    <>
                      <ChartContainer
                        config={providerPlatformHistoryChart.config}
                        className="h-80 w-full"
                      >
                        <LineChart
                          data={providerPlatformHistoryChart.data}
                          margin={{ left: -20, right: 12, top: 8, bottom: 0 }}
                        >
                          <XAxis
                            dataKey="label"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            minTickGap={16}
                          />
                          <YAxis hide />
                          <ChartTooltip
                            cursor={false}
                            content={<ChartTooltipContent labelKey="label" />}
                          />
                          <ChartLegend
                            verticalAlign="top"
                            content={<ChartLegendContent />}
                          />
                          {providerPlatformHistoryChart.series.map((provider) => (
                            <Line
                              key={provider.key}
                              type="monotone"
                              dataKey={provider.key}
                              stroke={`var(--color-${provider.key})`}
                              strokeWidth={2.5}
                              dot={{ r: 0 }}
                              activeDot={{ r: 4 }}
                            />
                          ))}
                        </LineChart>
                      </ChartContainer>
                      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {providerPlatformHistoryChart.series.map((provider) => {
                          const currentValue = Number(
                            providerPlatformHistoryChart.data.at(-1)?.[provider.key] ?? 0,
                          );
                          const previousValue = Number(
                            providerPlatformHistoryChart.data.at(-2)?.[provider.key] ?? 0,
                          );

                          return (
                            <div key={provider.key} className="rounded-lg border p-3">
                              <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                  <p className="truncate font-medium">{provider.label}</p>
                                  <p className="text-2xl font-semibold">
                                    {currentValue.toLocaleString()}
                                  </p>
                                </div>
                                <div
                                  className="mt-1 h-3 w-3 shrink-0 rounded-full"
                                  style={{ backgroundColor: provider.color }}
                                />
                              </div>
                              <div className="mt-3">
                                <TrendPill
                                  current={currentValue}
                                  previous={previousValue}
                                  suffix={`vs previous ${formatPeriodLabel(analyticsUnit)}`}
                                />
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </>
                  )}
                </CardContent>
              </Card>
            </div>
          )}
        </TabsContent>
      </Tabs>
    </>
  );
}

export default function TenantAnalytics() {
  return (
    <AdminLayout variant="tenant">
      <TenantAnalyticsContent />
    </AdminLayout>
  );
}
