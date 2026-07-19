import { useQueries } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';

export function useTenant() {
  const { currentTenantId, isAuthenticated } = useAuth();

  const [
    {data: tenant, isLoading: isTenantLoading, error: tenantError},
    {data: branding, isLoading: isBrandingLoading},
    {data: settings, isLoading: isSettingsLoading}
  ] = useQueries({
    queries: [
      {
        queryKey: ['tenant', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return null;

          const { data, error } = await supabase
            .from('tenants')
            .select('*')
            .eq('id', currentTenantId)
            .single();

          if (error) throw error;
          return data as Tenant;
        },
        enabled: !!currentTenantId && isAuthenticated,
      },
      {
        queryKey: ['tenant-branding', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return null;

          const { data, error } = await supabase
            .from('tenant_branding')
            .select('*')
            .eq('tenant_id', currentTenantId)
            .single();

          if (error && error.code !== 'PGRST116') throw error;
          return data as TenantBranding | null;
        },
        enabled: !!currentTenantId && isAuthenticated,
      },
      {
        queryKey: ['tenant-settings', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return null;

          const { data, error } = await supabase
            .from('tenant_settings')
            .select('*')
            .eq('tenant_id', currentTenantId)
            .single();

          if (error && error.code !== 'PGRST116') throw error;
          return data as TenantSettings | null;
        },
        enabled: !!currentTenantId && isAuthenticated,
      },
    ],
  });

  return {
    tenant,
    branding,
    settings,
    isLoading: isTenantLoading || isBrandingLoading || isSettingsLoading,
    error: tenantError,
    tenantId: currentTenantId,
  };
}
