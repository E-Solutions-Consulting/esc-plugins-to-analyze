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

const SYMPTOM_FLAG_KEY = 'symptoms_tracking';
const MAX_LABEL_LENGTH = 60;

interface SymptomDefinition {
  id: string;
  tenant_id: string;
  label: string;
  is_active: boolean;
  display_order: number;
  created_at: string;
  updated_at: string;
}

export function SymptomTrackingSettings() {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [newSymptom, setNewSymptom] = useState('');
  const [areSymptomsExpanded, setAreSymptomsExpanded] = useState(false);

  const { data: featureFlag, isLoading: flagLoading } = useQuery({
    queryKey: ['feature-flag', SYMPTOM_FLAG_KEY],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('feature_flags')
        .select('*')
        .eq('key', SYMPTOM_FLAG_KEY)
        .maybeSingle();

      if (error) throw error;
      return data as FeatureFlag | null;
    },
  });

  const { data: symptoms = [], isLoading: symptomsLoading } = useQuery({
    queryKey: ['tenant-symptom-definitions', currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];

      const { data, error } = await supabase
        .from('tenant_symptom_definitions' as 'medication_capabilities')
        .select('*')
        .eq('tenant_id', currentTenantId)
        .order('label', { ascending: true });

      if (error) throw error;
      return (data ?? []) as unknown as SymptomDefinition[];
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
        afterData: { enabled, flagKey: SYMPTOM_FLAG_KEY },
      });
      toast.success(`Symptom tracking ${enabled ? 'enabled' : 'disabled'}`);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update symptom tracking');
    },
  });

  const addMutation = useMutation({
    mutationFn: async (label: string) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const { data, error } = await supabase
        .from('tenant_symptom_definitions' as 'medication_capabilities')
        .insert({ tenant_id: currentTenantId, label } as unknown as Record<string, never>)
        .select()
        .single();

      if (error) throw error;
      return data as unknown as SymptomDefinition;
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-symptom-definitions', currentTenantId] });
      logAction({
        action: 'create',
        entityType: 'tenant_symptom_definition',
        entityId: data.id,
        afterData: { label: data.label },
      });
      setNewSymptom('');
      toast.success('Symptom added');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to add symptom');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (symptom: SymptomDefinition) => {
      const { error } = await supabase
        .from('tenant_symptom_definitions' as 'medication_capabilities')
        .delete()
        .eq('id', symptom.id);

      if (error) throw error;
      return symptom;
    },
    onSuccess: (symptom) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-symptom-definitions', currentTenantId] });
      logAction({
        action: 'delete',
        entityType: 'tenant_symptom_definition',
        entityId: symptom.id,
        beforeData: { label: symptom.label },
      });
      toast.success('Symptom removed');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to remove symptom');
    },
  });

  const effectiveEnabled = override ? override.enabled : featureFlag?.default_value ?? false;
  const canToggle = !!featureFlag?.is_active;
  const isLoading = flagLoading || overrideLoading || symptomsLoading;

  const normalizedSymptom = newSymptom.trim();
  const symptomExists = useMemo(() => {
    if (!normalizedSymptom) return false;
    return symptoms.some(
      (symptom) => symptom.label.toLowerCase() === normalizedSymptom.toLowerCase()
    );
  }, [normalizedSymptom, symptoms]);

  const handleAddSymptom = () => {
    if (!normalizedSymptom) return;

    if (normalizedSymptom.length > MAX_LABEL_LENGTH) {
      toast.error(`Symptom must be ${MAX_LABEL_LENGTH} characters or less`);
      return;
    }

    if (symptomExists) {
      toast.error('This symptom already exists');
      return;
    }

    addMutation.mutate(normalizedSymptom);
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
              <CardTitle>Symptom Tracking</CardTitle>
              <CardDescription>Feature flag not configured yet.</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Ask a platform admin to create the <code>symptoms_tracking</code> feature flag.
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
                <CardTitle>Symptom Tracking</CardTitle>
                <Badge variant={effectiveEnabled ? 'default' : 'secondary'}>
                  {effectiveEnabled ? 'Enabled' : 'Disabled'}
                </Badge>
              </div>
              <CardDescription>
                Enable symptom tracking and manage the list of symptoms your patients can record.
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
              <Label htmlFor="new-symptom">Add symptom</Label>
              <div className="flex flex-col sm:flex-row gap-2">
                <Input
                  id="new-symptom"
                  placeholder="e.g. Nausea"
                  value={newSymptom}
                  onChange={(e) => setNewSymptom(e.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                      event.preventDefault();
                      handleAddSymptom();
                    }
                  }}
                />
                <Button
                  type="button"
                  onClick={handleAddSymptom}
                  disabled={!normalizedSymptom || symptomExists || addMutation.isPending}
                >
                  {addMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Plus className="h-4 w-4" />
                  )}
                  <span className="sr-only">Add symptom</span>
                </Button>
              </div>
              {symptomExists && (
                <p className="text-xs text-muted-foreground">This symptom is already in your list.</p>
              )}
            </div>

            <Collapsible
              open={areSymptomsExpanded}
              onOpenChange={setAreSymptomsExpanded}
              className="rounded-lg border"
            >
              <CollapsibleTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  className="h-auto w-full justify-between rounded-lg px-3 py-2 hover:bg-muted/50"
                >
                  <span className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">Configured symptoms</span>
                    <Badge variant="secondary">{symptoms.length} total</Badge>
                  </span>
                  <ChevronDown
                    className={cn(
                      'h-4 w-4 text-muted-foreground transition-transform',
                      areSymptomsExpanded ? 'rotate-180' : ''
                    )}
                  />
                </Button>
              </CollapsibleTrigger>

              <CollapsibleContent className="px-3 pb-3">
                {symptoms.length === 0 ? (
                  <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Add symptoms to make them available in patient tracking forms.
                  </div>
                ) : (
                  <div className="space-y-2">
                    {symptoms.map((symptom) => (
                      <div
                        key={symptom.id}
                        className="flex items-center justify-between rounded-md border px-3 py-2"
                      >
                        <span className="text-sm font-medium">{symptom.label}</span>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => deleteMutation.mutate(symptom)}
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
