/**
 * useProviderIntegrations — the tenant's ENABLED provider-platform integrations
 * (Telegra / MDI / …) plus a mutation to patch a provider's tenant_integrations
 * settings JSON. Backs the Patient questionnaire editor (per provider).
 *
 * Mirrors the provider-list query inside useProductProviderPlatforms, surfaced as a
 * standalone hook so questionnaire UIs can list providers without a productId.
 */
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "@/hooks/useAuditLog";
import { toast } from "sonner";

export interface ProviderIntegration {
  /** tenant_integrations.id */
  id: string;
  /** integration_key, e.g. "telegramd" */
  key: string;
  /** display name from platform_integrations */
  name: string;
  /** current tenant_integrations.settings JSON */
  settings: Record<string, unknown>;
}

export function useProviderIntegrations() {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const queryKey = ["provider-integrations", currentTenantId];

  const query = useQuery({
    queryKey,
    queryFn: async (): Promise<ProviderIntegration[]> => {
      if (!currentTenantId) return [];

      const { data: platformIntegrations, error: platformError } = await supabase
        .from("platform_integrations")
        .select("id, key, name, category, is_active")
        .eq("is_active", true)
        .eq("category", "provider_platform")
        .order("name");
      if (platformError) throw platformError;
      if (!platformIntegrations || platformIntegrations.length === 0) return [];

      const keys = platformIntegrations.map((p) => p.key);

      const { data: tenantIntegrations, error: tenantError } = await supabase
        .from("tenant_integrations")
        .select("*")
        .eq("tenant_id", currentTenantId)
        .eq("is_enabled", true)
        .in("integration_key", keys);
      if (tenantError) throw tenantError;

      return (tenantIntegrations || [])
        .map((ti) => {
          const platform = platformIntegrations.find(
            (p) => p.key === ti.integration_key,
          );
          if (!platform) return null;
          return {
            id: ti.id as string,
            key: ti.integration_key as string,
            name: platform.name as string,
            settings: (ti.settings ?? {}) as Record<string, unknown>,
          } satisfies ProviderIntegration;
        })
        .filter(Boolean) as ProviderIntegration[];
    },
    enabled: !!currentTenantId,
  });

  /**
   * Patch a provider's settings JSON. The caller passes only the keys it changed;
   * we merge over the existing settings so unrelated keys are preserved. Pass
   * `null` for a key to delete it.
   */
  const updateProviderSettings = useMutation({
    mutationFn: async (
      { tenantIntegrationId, patch }: {
        tenantIntegrationId: string;
        patch: Record<string, unknown | null>;
      },
    ) => {
      const existing = (query.data ?? []).find(
        (p) => p.id === tenantIntegrationId,
      );
      const nextSettings: Record<string, unknown> = { ...(existing?.settings ?? {}) };
      for (const [key, value] of Object.entries(patch)) {
        if (value === null || value === undefined) {
          delete nextSettings[key];
        } else {
          nextSettings[key] = value;
        }
      }
      const { error } = await supabase
        .from("tenant_integrations")
        .update({ settings: nextSettings as never })
        .eq("id", tenantIntegrationId);
      if (error) throw error;
      return { tenantIntegrationId, nextSettings };
    },
    onSuccess: ({ tenantIntegrationId }) => {
      queryClient.invalidateQueries({ queryKey });
      void logAction({
        action: "update",
        entityType: "tenant_integration",
        entityId: tenantIntegrationId,
      });
    },
    onError: (error) =>
      toast.error(
        error instanceof Error ? error.message : "Failed to save settings",
      ),
  });

  return {
    providers: query.data ?? [],
    isLoading: query.isLoading,
    updateProviderSettings,
  };
}
