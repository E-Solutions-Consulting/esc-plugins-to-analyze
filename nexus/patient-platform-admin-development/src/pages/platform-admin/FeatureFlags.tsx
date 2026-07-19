import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { DataTable, Column } from '@/components/common/DataTable';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { Building2 } from 'lucide-react';
import { TenantFeatureFlagManager } from '@/components/features/TenantFeatureFlagManager';

export function FeatureFlagsContent() {
  const { isPlatformSuperadmin } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [selectedFlag, setSelectedFlag] = useState<FeatureFlag | null>(null);
  const [isTenantManagerOpen, setIsTenantManagerOpen] = useState(false);

  const { data: flags = [], isLoading } = useQuery({
    queryKey: ['feature-flags', search],
    queryFn: async () => {
      let query = supabase
        .from('feature_flags')
        .select('*')
        .order('key', { ascending: true });

      if (search) {
        query = query.or(`key.ilike.%${search}%,name.ilike.%${search}%`);
      }

      const { data, error } = await query;
      if (error) throw error;
      return data as FeatureFlag[];
    },
    enabled: isPlatformSuperadmin,
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ id, is_active }: { id: string; is_active: boolean }) => {
      const { error } = await supabase
        .from('feature_flags')
        .update({ is_active })
        .eq('id', id);

      if (error) throw error;
      return { id, is_active };
    },
    onSuccess: ({ id, is_active }) => {
      queryClient.invalidateQueries({ queryKey: ['feature-flags'] });
      logAction({
        action: 'update',
        entityType: 'feature_flag',
        entityId: id,
        afterData: { is_active },
        tenantId: null,
      });
      toast.success(`Flag ${is_active ? 'activated' : 'deactivated'}`);
    },
  });

  const columns: Column<FeatureFlag>[] = [
    {
      key: 'key',
      header: 'Key',
      cell: (flag) => (
        <code className="text-sm bg-muted px-2 py-1 rounded">{flag.key}</code>
      ),
    },
    {
      key: 'name',
      header: 'Name',
      cell: (flag) => (
        <div>
          <p className="font-medium">{flag.name}</p>
          {flag.description && (
            <p className="text-sm text-muted-foreground line-clamp-1">{flag.description}</p>
          )}
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Type',
      cell: (flag) => (
        <Badge variant="outline" className="capitalize">
          {flag.flag_type.replace('_', ' ')}
        </Badge>
      ),
    },
    {
      key: 'default',
      header: 'Default',
      cell: (flag) => (
        <Badge variant={flag.default_value ? 'default' : 'secondary'}>
          {flag.default_value ? 'On' : 'Off'}
        </Badge>
      ),
    },
    {
      key: 'active',
      header: 'Active',
      cell: (flag) => (
        <Switch
          checked={flag.is_active}
          onCheckedChange={(checked) => toggleMutation.mutate({ id: flag.id, is_active: checked })}
        />
      ),
    },
    {
      key: 'actions',
      header: 'Actions',
      cell: (flag) => (
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="outline"
            onClick={() => {
              setSelectedFlag(flag);
              setIsTenantManagerOpen(true);
            }}
          >
            <Building2 className="h-4 w-4 mr-1" />
            Tenants
          </Button>
        </div>
      ),
    },
  ];

  if (!isPlatformSuperadmin) {
    return (
      <>
        <div className="flex items-center justify-center h-full">
          <p className="text-muted-foreground">Access denied. Platform Superadmin role required.</p>
        </div>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Feature Flags"
        description="Manage global feature flags and governance"
      />

      <DataTable
        columns={columns}
        data={flags}
        isLoading={isLoading}
        searchPlaceholder="Search flags..."
        searchValue={search}
        onSearchChange={setSearch}
        emptyMessage="No feature flags found"
      />

      <TenantFeatureFlagManager
        open={isTenantManagerOpen}
        onOpenChange={setIsTenantManagerOpen}
        flag={selectedFlag}
      />
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function FeatureFlags() {
  return (
    <AdminLayout variant="platform">
      <FeatureFlagsContent />
    </AdminLayout>
  );
}
