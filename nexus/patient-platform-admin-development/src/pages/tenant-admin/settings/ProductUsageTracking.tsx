import { useEffect, useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { Activity, Loader2, Trash2 } from 'lucide-react';

// Retention window bounds — must match the CHECK constraint in
// 20260709120000_analytics_window_prune_and_viewer_rpcs.sql.
const RETENTION_MIN_DAYS = 7;
const RETENTION_MAX_DAYS = 90;
const clampRetention = (n: number) =>
  Math.min(RETENTION_MAX_DAYS, Math.max(RETENTION_MIN_DAYS, Math.round(n)));

// The generated Supabase types do not yet include `tenant_analytics_settings`
// (added in 20260617140000_create_product_usage_analytics.sql). Following the
// existing convention for tables whose types lag (see useProductPaymentProviders /
// usePaymentProviders), we access them through an untyped client and cast the
// result back to our local interface.
const SETTINGS_TABLE = 'tenant_analytics_settings';
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const db = supabase as any;

interface AnalyticsSettings {
  id: string;
  tenant_id: string | null;
  tracking_enabled: boolean;
  track_page_views: boolean;
  track_activity_events: boolean;
  track_time_on_page: boolean;
  track_device_info: boolean;
  track_utm_attribution: boolean;
  track_guest_sessions: boolean;
  session_idle_timeout_minutes: number;
  hot_retention_days: number;
}

type CategoryKey = Exclude<
  keyof AnalyticsSettings,
  'id' | 'tenant_id' | 'tracking_enabled' | 'session_idle_timeout_minutes' | 'hot_retention_days'
>;

interface CategoryDef {
  key: CategoryKey;
  title: string;
  description: string;
}

const CATEGORIES: CategoryDef[] = [
  {
    key: 'track_page_views',
    title: 'Page & screen views',
    description: 'Record which pages/screens patients visit and navigation between them.',
  },
  {
    key: 'track_activity_events',
    title: 'Activity events',
    description: 'Record named interactions (e.g. product viewed, checkout started, questionnaire step).',
  },
  {
    key: 'track_time_on_page',
    title: 'Time on page',
    description: 'Measure dwell time per page/activity and overall session duration.',
  },
  {
    key: 'track_device_info',
    title: 'Device & context',
    description: 'Record device type, platform, OS, app version, locale and timezone.',
  },
  {
    key: 'track_utm_attribution',
    title: 'UTM attribution',
    description: 'Capture referrer and UTM parameters at session start for acquisition analysis.',
  },
  {
    key: 'track_guest_sessions',
    title: 'Guest (unauthenticated) sessions',
    description: 'Track visitors before they sign in. When off, only authenticated activity is recorded.',
  },
];

// Defaults mirror the platform-default seed row, used to render the form before
// a tenant override exists.
const DEFAULTS: Omit<AnalyticsSettings, 'id' | 'tenant_id'> = {
  tracking_enabled: false,
  track_page_views: true,
  track_activity_events: true,
  track_time_on_page: true,
  track_device_info: true,
  track_utm_attribution: true,
  track_guest_sessions: true,
  session_idle_timeout_minutes: 30,
  hot_retention_days: 30,
};

/** Page body without the AdminLayout wrapper (for reuse in Settings v2). */
export function ProductUsageTrackingContent() {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();

  const { data: settings, isLoading } = useQuery({
    queryKey: ['tenant-analytics-settings', currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return null;
      const { data, error } = await db
        .from(SETTINGS_TABLE)
        .select('*')
        .eq('tenant_id', currentTenantId)
        .maybeSingle();
      if (error) throw error;
      return (data ?? null) as AnalyticsSettings | null;
    },
    enabled: !!currentTenantId,
  });

  const effective: Omit<AnalyticsSettings, 'id' | 'tenant_id'> = useMemo(
    () => ({ ...DEFAULTS, ...(settings ?? {}) }),
    [settings],
  );

  const updateMutation = useMutation({
    mutationFn: async (patch: Partial<AnalyticsSettings>) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      if (settings) {
        const { data, error } = await db
          .from(SETTINGS_TABLE)
          .update(patch)
          .eq('id', settings.id)
          .select()
          .single();
        if (error) throw error;
        return { result: data as AnalyticsSettings, previous: settings };
      }

      // First change for this tenant: create the override row from the
      // effective (default) values plus the patch.
      const insertPayload = { ...DEFAULTS, ...patch, tenant_id: currentTenantId };
      const { data, error } = await db
        .from(SETTINGS_TABLE)
        .insert(insertPayload)
        .select()
        .single();
      if (error) throw error;
      return { result: data as AnalyticsSettings, previous: null };
    },
    onSuccess: ({ result, previous }) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-analytics-settings', currentTenantId] });
      logAction({
        action: previous ? 'update' : 'create',
        entityType: 'tenant_analytics_settings',
        entityId: result.id,
        beforeData: previous as unknown as Record<string, unknown> | null,
        afterData: result as unknown as Record<string, unknown>,
        tenantId: currentTenantId,
      });
      toast.success('Product usage tracking settings saved');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to save settings');
    },
  });

  const masterEnabled = effective.tracking_enabled;
  const saving = updateMutation.isPending;

  // Local, editable copy of the retention window so typing doesn't fire a save
  // per keystroke — persisted on blur / Save. Kept in sync with the effective value.
  const [retentionInput, setRetentionInput] = useState<string>(String(effective.hot_retention_days));
  useEffect(() => {
    setRetentionInput(String(effective.hot_retention_days));
  }, [effective.hot_retention_days]);

  const retentionDirty =
    retentionInput.trim() !== '' &&
    clampRetention(Number(retentionInput)) !== effective.hot_retention_days;

  const saveRetention = () => {
    const parsed = Number(retentionInput);
    if (!Number.isFinite(parsed)) {
      setRetentionInput(String(effective.hot_retention_days));
      return;
    }
    const clamped = clampRetention(parsed);
    setRetentionInput(String(clamped));
    if (clamped !== effective.hot_retention_days) {
      updateMutation.mutate({ hot_retention_days: clamped });
    }
  };

  return (
    <>
      <PageHeader
        title="Product Usage Tracking"
        description="Choose what user & product-usage analytics this tenant collects from the patient app (web & mobile). Distinct from the Business Analytics dashboard."
      />

      <div className="space-y-6">
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : (
          <>
            {/* Master switch */}
            <Card>
              <CardHeader>
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                      <Activity className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <CardTitle>Tracking</CardTitle>
                        <Badge variant={masterEnabled ? 'default' : 'secondary'}>
                          {masterEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                      </div>
                      <CardDescription>
                        Master switch. When off, the patient app collects nothing for this tenant.
                      </CardDescription>
                    </div>
                  </div>
                  <Switch
                    checked={masterEnabled}
                    onCheckedChange={(checked) => updateMutation.mutate({ tracking_enabled: checked })}
                    disabled={saving}
                  />
                </div>
              </CardHeader>
            </Card>

            {/* Per-category toggles */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Tracking categories</CardTitle>
                <CardDescription>
                  Fine-grained opt-in. The patient app only sends data for the categories enabled here, and the
                  ingestion API re-enforces these server-side.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {CATEGORIES.map((cat) => (
                  <div
                    key={cat.key}
                    className="flex items-center justify-between gap-4 rounded-lg border p-4"
                  >
                    <div className="flex-1 space-y-1">
                      <span className="font-medium">{cat.title}</span>
                      <p className="text-sm text-muted-foreground">{cat.description}</p>
                    </div>
                    <Switch
                      checked={effective[cat.key]}
                      onCheckedChange={(checked) => updateMutation.mutate({ [cat.key]: checked })}
                      disabled={!masterEnabled || saving}
                    />
                  </div>
                ))}
              </CardContent>
            </Card>

            {/* Data retention window */}
            <Card>
              <CardHeader>
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <Trash2 className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <CardTitle className="text-lg">Data retention</CardTitle>
                    <CardDescription>
                      How long behavioural data is kept in the hot store before the nightly cleanup deletes it.
                    </CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="flex flex-wrap items-end gap-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="hot_retention_days">Retention window (days)</Label>
                    <Input
                      id="hot_retention_days"
                      type="number"
                      inputMode="numeric"
                      min={RETENTION_MIN_DAYS}
                      max={RETENTION_MAX_DAYS}
                      className="w-32"
                      value={retentionInput}
                      onChange={(e) => setRetentionInput(e.target.value)}
                      onBlur={saveRetention}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          saveRetention();
                        }
                      }}
                      disabled={saving}
                    />
                  </div>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={saveRetention}
                    disabled={saving || !retentionDirty}
                  >
                    {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                    Save
                  </Button>
                </div>
                <p className="mt-3 text-sm text-muted-foreground">
                  Between {RETENTION_MIN_DAYS} and {RETENTION_MAX_DAYS} days. Rows older than this window are
                  permanently deleted from the hot store by the nightly cleanup job — independent of any data
                  warehouse export.
                </p>
              </CardContent>
            </Card>

            <p className="text-xs text-muted-foreground">
              Behavioural data is retained in the hot store for {effective.hot_retention_days} days, after which the
              nightly cleanup permanently deletes it. Sessions end after {effective.session_idle_timeout_minutes}{' '}
              minutes of inactivity. No PHI is collected in tracking events.
            </p>
          </>
        )}
      </div>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function ProductUsageTracking() {
  return (
    <AdminLayout variant="tenant">
      <ProductUsageTrackingContent />
    </AdminLayout>
  );
}
