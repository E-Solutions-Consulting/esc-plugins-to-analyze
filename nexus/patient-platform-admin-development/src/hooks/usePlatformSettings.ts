import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from './useAuditLog';
import { toast } from 'sonner';

export function usePlatformSettings() {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const { data: settings, isLoading, error } = useQuery({
    queryKey: ['platform-settings'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('platform_settings' as 'admin_users')
        .select('*')
        .order('category', { ascending: true });

      if (error) throw error;
      return data as unknown as PlatformSetting[];
    },
  });

  const updateSetting = useMutation({
    mutationFn: async ({
      key,
      value,
      previousValue,
    }: {
      key: string;
      value: Record<string, unknown>;
      previousValue: Record<string, unknown>;
    }) => {
      const client = supabase as unknown as {
        from: (table: string) => {
          update: (data: unknown) => {
            eq: (col: string, val: string) => {
              select: () => {
                single: () => Promise<{ data: unknown; error: Error | null }>;
              };
            };
          };
        };
      };

      const { data, error } = await client
        .from('platform_settings')
        .update({ value })
        .eq('key', key)
        .select()
        .single();

      if (error) throw error;
      return { data: data as PlatformSetting, previousValue };
    },
    onSuccess: async (result, variables) => {
      queryClient.invalidateQueries({ queryKey: ['platform-settings'] });

      await logAction({
        action: 'update',
        entityType: 'platform_setting',
        entityId: result.data.id,
        beforeData: {
          key: variables.key,
          value: variables.previousValue,
        },
        afterData: {
          key: variables.key,
          value: variables.value,
        },
        tenantId: null,
      });

      toast.success('Setting updated successfully');
    },
    onError: (error) => {
      console.error('Failed to update setting:', error);
      toast.error('Failed to update setting');
    },
  });

  const updateRtdhConfig = useMutation({
    mutationFn: async (value: {
      api_url?: string;
      base_url?: string;
      patient_platform_webhook_secret?: string;
      patient_platform_receiver_secret?: string;
      secret_manager_receiver_secret?: string;
      patient_platform_consumer_webhook_token?: string;
    }) => {
      const { data, error } = await supabase.functions.invoke<{
        ok: boolean;
        setting: PlatformSetting;
        config: {
          base_url: string;
          patient_platform_webhook_secret_configured: boolean;
          secret_manager_receiver_secret_configured: boolean;
          patient_platform_consumer_webhook_token_configured: boolean;
        };
      }>('set-rtdh-config', { body: value });

      if (error) throw error;
      if (!data?.ok) throw new Error('Failed to save RTDH settings');
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-settings'] });
      toast.success('RTDH settings saved successfully');
    },
    onError: (error) => {
      console.error('Failed to save RTDH settings:', error);
      toast.error(error instanceof Error ? error.message : 'Failed to save RTDH settings');
    },
  });

  const getSettingByKey = (key: string): PlatformSetting | undefined =>
    settings?.find((setting) => setting.key === key);

  const getSettingValue = <T,>(key: string): T | undefined => {
    const setting = getSettingByKey(key);
    return setting?.value as T | undefined;
  };

  return {
    settings,
    isLoading,
    error,
    updateSetting,
    updateRtdhConfig,
    getSettingByKey,
    getSettingValue,
  };
}
