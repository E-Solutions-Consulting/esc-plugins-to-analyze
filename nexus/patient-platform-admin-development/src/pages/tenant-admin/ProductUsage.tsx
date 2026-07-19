import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Line, LineChart, XAxis, YAxis, CartesianGrid } from 'recharts';
import { Activity, Loader2, MousePointerClick, Users, Timer } from 'lucide-react';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from '@/components/ui/chart';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';

// The generated Supabase types don't yet include the product-usage RPCs
// (added in 20260709120000). Following the repo convention for untyped RPCs
// (see Analytics.tsx `supabase.rpc(... as never, ...)`), we call them untyped
// and cast the result rows back to local interfaces.
type Rpc = (name: string, args: Record<string, unknown>) => Promise<{ data: unknown; error: unknown }>;
const rpc = ((name, args) =>
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  (supabase as any).rpc(name, args)) as Rpc;

interface Summary {
  total_events: number;
  total_sessions: number;
  total_devices: number;
  authenticated_sessions: number;
  guest_sessions: number;
  avg_session_seconds: number;
  page_views: number;
}
interface TimeseriesRow {
  day: string;
  events: number;
  sessions: number;
}
interface TopPage {
  page_path: string;
  views: number;
}
interface TopEvent {
  event_name: string;
  occurrences: number;
}
interface RecentSession {
  id: string;
  started_at: string;
  last_activity_at: string;
  is_authenticated: boolean;
  duration_seconds: number;
  page_view_count: number;
  event_count: number;
  entry_url: string | null;
}

const WINDOW_OPTIONS = [
  { value: '7', label: 'Last 7 days' },
  { value: '14', label: 'Last 14 days' },
  { value: '30', label: 'Last 30 days' },
  { value: '90', label: 'Last 90 days' },
];

const chartConfig: ChartConfig = {
  events: { label: 'Events', color: 'hsl(var(--chart-1))' },
  sessions: { label: 'Sessions', color: 'hsl(var(--chart-2))' },
};

function formatDuration(seconds: number): string {
  if (!seconds || seconds <= 0) return '—';
  const m = Math.floor(seconds / 60);
  const s = Math.round(seconds % 60);
  if (m === 0) return `${s}s`;
  return `${m}m ${s}s`;
}

function formatDateTime(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function ProductUsageContent() {
  const { currentTenantId } = useAuth();
  const [days, setDays] = useState<string>('30');
  const numDays = Number(days);

  const enabled = !!currentTenantId;

  const summaryQuery = useQuery({
    queryKey: ['product-usage-summary', currentTenantId, numDays],
    enabled,
    queryFn: async () => {
      const { data, error } = await rpc('get_product_usage_summary', {
        p_tenant_id: currentTenantId,
        p_days: numDays,
      });
      if (error) throw error;
      const row = (Array.isArray(data) ? data[0] : data) as Summary | undefined;
      return row ?? null;
    },
  });

  const timeseriesQuery = useQuery({
    queryKey: ['product-usage-timeseries', currentTenantId, numDays],
    enabled,
    queryFn: async () => {
      const { data, error } = await rpc('get_product_usage_timeseries', {
        p_tenant_id: currentTenantId,
        p_days: numDays,
      });
      if (error) throw error;
      return (data ?? []) as TimeseriesRow[];
    },
  });

  const topPagesQuery = useQuery({
    queryKey: ['product-usage-top-pages', currentTenantId, numDays],
    enabled,
    queryFn: async () => {
      const { data, error } = await rpc('get_product_usage_top_pages', {
        p_tenant_id: currentTenantId,
        p_days: numDays,
        p_limit: 10,
      });
      if (error) throw error;
      return (data ?? []) as TopPage[];
    },
  });

  const topEventsQuery = useQuery({
    queryKey: ['product-usage-top-events', currentTenantId, numDays],
    enabled,
    queryFn: async () => {
      const { data, error } = await rpc('get_product_usage_top_events', {
        p_tenant_id: currentTenantId,
        p_days: numDays,
        p_limit: 10,
      });
      if (error) throw error;
      return (data ?? []) as TopEvent[];
    },
  });

  const recentSessionsQuery = useQuery({
    queryKey: ['product-usage-recent-sessions', currentTenantId],
    enabled,
    queryFn: async () => {
      const { data, error } = await rpc('get_product_usage_recent_sessions', {
        p_tenant_id: currentTenantId,
        p_limit: 25,
      });
      if (error) throw error;
      return (data ?? []) as RecentSession[];
    },
  });

  const summary = summaryQuery.data;
  const chartData = useMemo(
    () =>
      (timeseriesQuery.data ?? []).map((r) => ({
        day: new Date(r.day).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        events: Number(r.events),
        sessions: Number(r.sessions),
      })),
    [timeseriesQuery.data],
  );

  const hasAnyData = (summary?.total_events ?? 0) > 0 || (summary?.total_sessions ?? 0) > 0;
  const initialLoading = summaryQuery.isLoading || timeseriesQuery.isLoading;

  const kpis = [
    {
      label: 'Events',
      value: summary?.total_events ?? 0,
      icon: Activity,
      sub: `${summary?.page_views ?? 0} page views`,
    },
    {
      label: 'Sessions',
      value: summary?.total_sessions ?? 0,
      icon: MousePointerClick,
      sub: `${summary?.authenticated_sessions ?? 0} auth · ${summary?.guest_sessions ?? 0} guest`,
    },
    {
      label: 'Devices',
      value: summary?.total_devices ?? 0,
      icon: Users,
      sub: 'seen in window',
    },
    {
      label: 'Avg session',
      value: formatDuration(summary?.avg_session_seconds ?? 0),
      icon: Timer,
      sub: 'duration',
    },
  ];

  return (
    <>
      <PageHeader
        title="Product Usage"
        description="Behavioural analytics from the patient app (web & mobile) — page views, sessions, and activity events. Data comes from the hot store; older data lives in the warehouse."
      />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <p className="text-sm text-muted-foreground">
            Showing recent behavioural activity for this tenant.
          </p>
          <Select value={days} onValueChange={setDays}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {WINDOW_OPTIONS.map((o) => (
                <SelectItem key={o.value} value={o.value}>
                  {o.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {initialLoading ? (
          <div className="grid gap-4 md:grid-cols-4">
            {[0, 1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-28 w-full" />
            ))}
          </div>
        ) : !hasAnyData ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center gap-2 py-16 text-center">
              <Activity className="h-8 w-8 text-muted-foreground" />
              <p className="font-medium">No usage data yet</p>
              <p className="max-w-md text-sm text-muted-foreground">
                Nothing has been recorded in this window. If you expect data, check that Product Usage Tracking is
                enabled for this tenant in Settings, and that the patient app has been used recently.
              </p>
            </CardContent>
          </Card>
        ) : (
          <>
            {/* KPI cards */}
            <div className="grid gap-4 md:grid-cols-4">
              {kpis.map((k) => (
                <Card key={k.label}>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">{k.label}</CardTitle>
                    <k.icon className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">
                      {typeof k.value === 'number' ? k.value.toLocaleString() : k.value}
                    </div>
                    <p className="text-xs text-muted-foreground">{k.sub}</p>
                  </CardContent>
                </Card>
              ))}
            </div>

            {/* Time series */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Activity over time</CardTitle>
                <CardDescription>Events and sessions per day.</CardDescription>
              </CardHeader>
              <CardContent>
                <ChartContainer config={chartConfig} className="h-[280px] w-full">
                  <LineChart data={chartData} margin={{ left: 4, right: 12, top: 8 }}>
                    <CartesianGrid vertical={false} strokeDasharray="3 3" />
                    <XAxis dataKey="day" tickLine={false} axisLine={false} tickMargin={8} minTickGap={24} />
                    <YAxis tickLine={false} axisLine={false} width={32} allowDecimals={false} />
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Line
                      dataKey="events"
                      type="monotone"
                      stroke="var(--color-events)"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      dataKey="sessions"
                      type="monotone"
                      stroke="var(--color-sessions)"
                      strokeWidth={2}
                      dot={false}
                    />
                  </LineChart>
                </ChartContainer>
              </CardContent>
            </Card>

            {/* Top pages + top events */}
            <div className="grid gap-4 lg:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle className="text-lg">Top pages</CardTitle>
                  <CardDescription>Most viewed pages/screens in the window.</CardDescription>
                </CardHeader>
                <CardContent>
                  {(topPagesQuery.data ?? []).length === 0 ? (
                    <p className="py-6 text-sm text-muted-foreground">No page views recorded.</p>
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Page</TableHead>
                          <TableHead className="text-right">Views</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(topPagesQuery.data ?? []).map((p) => (
                          <TableRow key={p.page_path}>
                            <TableCell className="font-mono text-xs">{p.page_path}</TableCell>
                            <TableCell className="text-right">{Number(p.views).toLocaleString()}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="text-lg">Top events</CardTitle>
                  <CardDescription>Most frequent named activity events.</CardDescription>
                </CardHeader>
                <CardContent>
                  {(topEventsQuery.data ?? []).length === 0 ? (
                    <p className="py-6 text-sm text-muted-foreground">No activity events recorded.</p>
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Event</TableHead>
                          <TableHead className="text-right">Count</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(topEventsQuery.data ?? []).map((e) => (
                          <TableRow key={e.event_name}>
                            <TableCell>{e.event_name}</TableCell>
                            <TableCell className="text-right">
                              {Number(e.occurrences).toLocaleString()}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Recent sessions */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Recent sessions</CardTitle>
                <CardDescription>The 25 most recent sessions in the hot store.</CardDescription>
              </CardHeader>
              <CardContent>
                {recentSessionsQuery.isLoading ? (
                  <div className="flex items-center justify-center py-8">
                    <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
                  </div>
                ) : (recentSessionsQuery.data ?? []).length === 0 ? (
                  <p className="py-6 text-sm text-muted-foreground">No sessions recorded.</p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Started</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Entry</TableHead>
                        <TableHead className="text-right">Pages</TableHead>
                        <TableHead className="text-right">Events</TableHead>
                        <TableHead className="text-right">Duration</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(recentSessionsQuery.data ?? []).map((s) => (
                        <TableRow key={s.id}>
                          <TableCell className="whitespace-nowrap text-sm">
                            {formatDateTime(s.started_at)}
                          </TableCell>
                          <TableCell>
                            <Badge variant={s.is_authenticated ? 'default' : 'secondary'}>
                              {s.is_authenticated ? 'Authenticated' : 'Guest'}
                            </Badge>
                          </TableCell>
                          <TableCell className="max-w-[220px] truncate font-mono text-xs">
                            {s.entry_url ?? '—'}
                          </TableCell>
                          <TableCell className="text-right">{s.page_view_count}</TableCell>
                          <TableCell className="text-right">{s.event_count}</TableCell>
                          <TableCell className="text-right">{formatDuration(s.duration_seconds)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </>
  );
}

export default function ProductUsage() {
  return (
    <AdminLayout variant="tenant">
      <ProductUsageContent />
    </AdminLayout>
  );
}
