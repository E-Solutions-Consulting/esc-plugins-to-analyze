import { useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { Activity, ChevronDown, Loader2, Plus, X } from 'lucide-react';

const ACTIVITIES_FLAG_KEY = 'activities_tracking';
const MAX_LABEL_LENGTH = 60;

export function ActivitiesTrackingSettings() {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [newActivity, setNewActivity] = useState('');
  const [areActivitiesExpanded, setAreActivitiesExpanded] = useState(false);

  const { data: featureFlag, isLoading: flagLoading } = useQuery({
    queryKey: ['feature-flag', ACTIVITIES_FLAG_KEY],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('feature_flags')
        .select('*')
        .eq('key', ACTIVITIES_FLAG_KEY)
        .maybeSingle();

      if (error) throw error;
      return data as FeatureFlag | null;
    },
  });

  const { data: activities = [], isLoading: activitiesLoading } = useQuery({
    queryKey: ['tenant-activity-definitions', currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];

      const { data, error } = await supabase
        .from('tenant_activity_definitions' as 'medication_capabilities')
        .select('*')
        .eq('tenant_id', currentTenantId)
        .order('label', { ascending: true });

      if (error) throw error;
      return (data ?? []) as unknown as ActivityDefinition[];
    },
    enabled: !!currentTenantId,
  });

  const { data: override, isLoading: overrideLoading } = useQuery({
    queryKey: ['tenant-flag-override', currentTenantId, featureFlag?.id],
    queryFn: async () => {
      if (!currentTenantId || !featureFlag) return null;

      const { data, error } = await supabase
        .from('tenant_feature_flag_overrides')
        .select('*')
        .eq('tenant_id', currentTenantId)
        .eq('feature_flag_id', featureFlag.id)
        .maybeSingle();

      if (error) throw error;
      return data as FlagOverride | null;
    },
    enabled: !!currentTenantId && !!featureFlag,
  });

  const toggleMutation = useMutation({
    mutationFn: async (enabled: boolean) => {
      if (!currentTenantId || !featureFlag) {
        throw new Error('Missing tenant or feature flag');
      }

      if (override) {
        const { error } = await supabase
          .from('tenant_feature_flag_overrides')
          .update({ enabled })
          .eq('id', override.id);

        if (error) throw error;
      } else {
        const { error } = await supabase
          .from('tenant_feature_flag_overrides')
          .insert([{ feature_flag_id: featureFlag.id, tenant_id: currentTenantId, enabled }]);

        if (error) throw error;
      }

      return enabled;
    },
    onSuccess: (enabled) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-flag-override', currentTenantId, featureFlag?.id] });
      logAction({
        action: 'update',
        entityType: 'feature_flag_override',
        entityId: featureFlag?.id || '',
        afterData: { enabled, flagKey: ACTIVITIES_FLAG_KEY },
      });
      toast.success(`Activities tracking ${enabled ? 'enabled' : 'disabled'}`);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update activities tracking');
    },
  });

  const addMutation = useMutation({
    mutationFn: async (label: string) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const { data, error } = await supabase
        .from('tenant_activity_definitions' as 'medication_capabilities')
        .insert({ tenant_id: currentTenantId, label } as unknown as Record<string, never>)
        .select()
        .single();

      if (error) throw error;
      return data as unknown as ActivityDefinition;
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-activity-definitions', currentTenantId] });
      logAction({
        action: 'create',
        entityType: 'tenant_activity_definition',
        entityId: data.id,
        afterData: { label: data.label },
      });
      setNewActivity('');
      toast.success('Activity added');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to add activity');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (activity: ActivityDefinition) => {
      const { error } = await supabase
        .from('tenant_activity_definitions' as 'medication_capabilities')
        .delete()
        .eq('id', activity.id);

      if (error) throw error;
      return activity;
    },
    onSuccess: (activity) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-activity-definitions', currentTenantId] });
      logAction({
        action: 'delete',
        entityType: 'tenant_activity_definition',
        entityId: activity.id,
        beforeData: { label: activity.label },
      });
      toast.success('Activity removed');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to remove activity');
    },
  });

  const effectiveEnabled = override ? override.enabled : featureFlag?.default_value ?? false;
  const canToggle = !!featureFlag?.is_active;
  const isLoading = flagLoading || overrideLoading || activitiesLoading;

  const normalizedActivity = newActivity.trim();
  const activityExists = useMemo(() => {
    if (!normalizedActivity) return false;
    return activities.some((activity) => activity.label.toLowerCase() === normalizedActivity.toLowerCase());
  }, [normalizedActivity, activities]);

  const handleAddActivity = () => {
    if (!normalizedActivity) return;

    if (normalizedActivity.length > MAX_LABEL_LENGTH) {
      toast.error(`Activity must be ${MAX_LABEL_LENGTH} characters or less`);
      return;
    }

    if (activityExists) {
      toast.error('This activity already exists');
      return;
    }

    addMutation.mutate(normalizedActivity);
  };

  if (!featureFlag && !flagLoading) {
    return (
      <Card>
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <Activity className="h-5 w-5 text-primary" />
            </div>
            <div>
              <CardTitle>Activities Tracking</CardTitle>
              <CardDescription>Feature flag not configured yet.</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Ask a platform admin to create the <code>activities_tracking</code> feature flag.
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <Activity className="h-5 w-5 text-primary" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <CardTitle>Activities Tracking</CardTitle>
                <Badge variant={effectiveEnabled ? 'default' : 'secondary'}>
                  {effectiveEnabled ? 'Enabled' : 'Disabled'}
                </Badge>
              </div>
              <CardDescription>
                Enable activities tracking and manage the list of activities your patients can record.
              </CardDescription>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <Switch
              checked={effectiveEnabled}
              onCheckedChange={(checked) => toggleMutation.mutate(checked)}
              disabled={!canToggle || toggleMutation.isPending}
            />
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {isLoading ? (
          <div className="flex items-center justify-center py-6">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : (
          <>
            {!featureFlag?.is_active && (
              <p className="text-sm text-muted-foreground">
                This feature flag is disabled at the platform level.
              </p>
            )}

            <div className="space-y-2">
              <Label htmlFor="new-activity">Add activity</Label>
              <div className="flex flex-col sm:flex-row gap-2">
                <Input
                  id="new-activity"
                  placeholder="e.g. Strength training"
                  value={newActivity}
                  onChange={(e) => setNewActivity(e.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                      event.preventDefault();
                      handleAddActivity();
                    }
                  }}
                />
                <Button
                  type="button"
                  onClick={handleAddActivity}
                  disabled={!normalizedActivity || activityExists || addMutation.isPending}
                >
                  {addMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Plus className="h-4 w-4" />
                  )}
                  <span className="sr-only">Add activity</span>
                </Button>
              </div>
              {activityExists && (
                <p className="text-xs text-muted-foreground">This activity is already in your list.</p>
              )}
            </div>

            <Collapsible
              open={areActivitiesExpanded}
              onOpenChange={setAreActivitiesExpanded}
              className="rounded-lg border"
            >
              <CollapsibleTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  className="h-auto w-full justify-between rounded-lg px-3 py-2 hover:bg-muted/50"
                >
                  <span className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">Configured activities</span>
                    <Badge variant="secondary">{activities.length} total</Badge>
                  </span>
                  <ChevronDown
                    className={cn(
                      'h-4 w-4 text-muted-foreground transition-transform',
                      areActivitiesExpanded ? 'rotate-180' : ''
                    )}
                  />
                </Button>
              </CollapsibleTrigger>

              <CollapsibleContent className="px-3 pb-3">
                {activities.length === 0 ? (
                  <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Add activities to make them available in patient tracking forms.
                  </div>
                ) : (
                  <div className="space-y-2">
                    {activities.map((activity) => (
                      <div
                        key={activity.id}
                        className="flex items-center justify-between rounded-md border px-3 py-2"
                      >
                        <span className="text-sm font-medium">{activity.label}</span>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => deleteMutation.mutate(activity)}
                          disabled={deleteMutation.isPending}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </CollapsibleContent>
            </Collapsible>
          </>
        )}
      </CardContent>
    </Card>
  );
}
