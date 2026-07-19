import { useState } from 'react';
import { useQueries, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from '@/hooks/useAuditLog';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { toast } from 'sonner';
import { Loader2, Building2, RotateCcw, Check, X } from 'lucide-react';

interface TenantFeatureFlagManagerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  flag: FeatureFlag | null;
}

interface TenantWithOverride extends Tenant {
  override?: FlagOverride;
  effectiveValue: boolean;
}

export function TenantFeatureFlagManager({
  open,
  onOpenChange,
  flag,
}: TenantFeatureFlagManagerProps) {
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [selectedTenantId, setSelectedTenantId] = useState<string>('all');

  const [
    { data: tenants = [], isLoading: tenantsLoading },
    { data: overrides = [], isLoading: overridesLoading },
  ] = useQueries({
    queries: [
      {
        queryKey: ['tenants-for-flags'],
        queryFn: async () => {
          const { data, error } = await supabase
            .from('tenants')
            .select('*')
            .order('name');
          if (error) throw error;
          return data as Tenant[];
        },
        enabled: open,
      },
      {
        queryKey: ['flag-overrides', flag?.id],
        queryFn: async () => {
          if (!flag) return [];
          const { data, error } = await supabase
            .from('tenant_feature_flag_overrides')
            .select('*')
            .eq('feature_flag_id', flag.id);
          if (error) throw error;
          return data as FlagOverride[];
        },
        enabled: open && !!flag,
      },
    ],
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ tenantId, enabled }: { tenantId: string; enabled: boolean }) => {
      if (!flag) throw new Error('No flag selected');

      const existingOverride = overrides.find((o) => o.tenant_id === tenantId);

      if (existingOverride) {
        const { error } = await supabase
          .from('tenant_feature_flag_overrides')
          .update({ enabled })
          .eq('id', existingOverride.id);
        if (error) throw error;
      } else {
        const { error } = await supabase
          .from('tenant_feature_flag_overrides')
          .insert([{ feature_flag_id: flag.id, tenant_id: tenantId, enabled }]);
        if (error) throw error;
      }

      return { tenantId, enabled };
    },
    onSuccess: ({ tenantId, enabled }) => {
      queryClient.invalidateQueries({ queryKey: ['flag-overrides', flag?.id] });
      const tenant = tenants.find((t) => t.id === tenantId);
      logAction({
        action: 'update',
        entityType: 'tenant_feature_flag_override',
        entityId: flag?.id || '',
        afterData: { tenantId, tenantName: tenant?.name, enabled, flagKey: flag?.key },
        tenantId,
      });
      toast.success(`Flag ${enabled ? 'enabled' : 'disabled'} for ${tenant?.name}`);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update override');
    },
  });

  const resetMutation = useMutation({
    mutationFn: async (tenantId: string) => {
      if (!flag) throw new Error('No flag selected');

      const existingOverride = overrides.find((o) => o.tenant_id === tenantId);
      if (!existingOverride) return { tenantId };

      const { error } = await supabase
        .from('tenant_feature_flag_overrides')
        .delete()
        .eq('id', existingOverride.id);
      if (error) throw error;

      return { tenantId };
    },
    onSuccess: ({ tenantId }) => {
      queryClient.invalidateQueries({ queryKey: ['flag-overrides', flag?.id] });
      const tenant = tenants.find((t) => t.id === tenantId);
      logAction({
        action: 'delete',
        entityType: 'tenant_feature_flag_override',
        entityId: flag?.id || '',
        beforeData: { tenantId, tenantName: tenant?.name, flagKey: flag?.key },
        tenantId,
      });
      toast.success(`Reset to default for ${tenant?.name}`);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to reset override');
    },
  });

  const isLoading = tenantsLoading || overridesLoading;

  const tenantsWithOverrides: TenantWithOverride[] = tenants.map((tenant) => {
    const override = overrides.find((o) => o.tenant_id === tenant.id);
    return {
      ...tenant,
      override,
      effectiveValue: override ? override.enabled : (flag?.default_value ?? false),
    };
  });

  const filteredTenants =
    selectedTenantId === 'all'
      ? tenantsWithOverrides
      : selectedTenantId === 'overridden'
        ? tenantsWithOverrides.filter((t) => t.override)
        : selectedTenantId === 'default'
          ? tenantsWithOverrides.filter((t) => !t.override)
          : tenantsWithOverrides.filter((t) => t.id === selectedTenantId);

  const overriddenCount = tenantsWithOverrides.filter((t) => t.override).length;
  const enabledCount = tenantsWithOverrides.filter((t) => t.effectiveValue).length;

  if (!flag) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Building2 className="h-5 w-5" />
            Manage "{flag.name}" per Tenant
          </DialogTitle>
          <DialogDescription>
            Configure this feature flag for individual tenants. Tenants without an override will use
            the default value ({flag.default_value ? 'On' : 'Off'}).
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Stats */}
          <div className="flex gap-4 text-sm">
            <div className="flex items-center gap-2">
              <Badge variant="outline">{tenants.length} tenants</Badge>
            </div>
            <div className="flex items-center gap-2">
              <Badge variant="secondary">{overriddenCount} overridden</Badge>
            </div>
            <div className="flex items-center gap-2">
              <Badge variant={enabledCount > 0 ? 'default' : 'secondary'}>
                {enabledCount} enabled
              </Badge>
            </div>
          </div>

          {/* Filter */}
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Filter:</span>
            <Select value={selectedTenantId} onValueChange={setSelectedTenantId}>
              <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="All tenants" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All tenants</SelectItem>
                <SelectItem value="overridden">Overridden only</SelectItem>
                <SelectItem value="default">Using default</SelectItem>
                <Separator className="my-1" />
                {tenants.map((tenant) => (
                  <SelectItem key={tenant.id} value={tenant.id}>
                    {tenant.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Separator />

          {/* Tenant list */}
          {isLoading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : filteredTenants.length === 0 ? (
            <p className="text-center py-8 text-muted-foreground">No tenants match the filter</p>
          ) : (
            <ScrollArea className="h-[300px] pr-4">
              <div className="space-y-2">
                {filteredTenants.map((tenant) => (
                  <div
                    key={tenant.id}
                    className="flex items-center justify-between rounded-lg border p-3"
                  >
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                      <div className="min-w-0 flex-1">
                        <p className="font-medium truncate">{tenant.name}</p>
                        <p className="text-xs text-muted-foreground truncate">{tenant.slug}</p>
                      </div>
                      {tenant.override ? (
                        <Badge variant="outline" className="shrink-0">
                          Overridden
                        </Badge>
                      ) : (
                        <Badge variant="secondary" className="shrink-0">
                          Default
                        </Badge>
                      )}
                    </div>

                    <div className="flex items-center gap-2 ml-4">
                      {tenant.override && (
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => resetMutation.mutate(tenant.id)}
                          disabled={resetMutation.isPending}
                          title="Reset to default"
                        >
                          <RotateCcw className="h-4 w-4" />
                        </Button>
                      )}
                      <div className="flex items-center gap-2">
                        {tenant.effectiveValue ? (
                          <Check className="h-4 w-4 text-primary" />
                        ) : (
                          <X className="h-4 w-4 text-muted-foreground" />
                        )}
                        <Switch
                          checked={tenant.effectiveValue}
                          onCheckedChange={(checked) =>
                            toggleMutation.mutate({ tenantId: tenant.id, enabled: checked })
                          }
                          disabled={toggleMutation.isPending}
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </ScrollArea>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
