import { useQueries, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "./useAuditLog";
import { toast } from "sonner";

// Helper to execute raw queries bypassing TypeScript strict checking
async function rawQuery<T>(
  table: string,
  operation: "select" | "insert" | "update" | "delete" | "upsert",
  options?: {
    data?: Record<string, unknown>;
    filter?: { column: string; value: unknown }[];
    select?: string;
    order?: string;
    single?: boolean;
    onConflict?: string;
  },
): Promise<{ data: T | null; error: Error | null }> {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let query = (supabase as any).from(table);

  switch (operation) {
    case "select":
      query = query.select(options?.select || "*");
      if (options?.order) query = query.order(options.order);
      break;
    case "insert":
      query = query.insert(options?.data).select();
      break;
    case "update":
      query = query.update(options?.data);
      break;
    case "delete":
      query = query.delete();
      break;
    case "upsert":
      query = query
        .upsert(options?.data, { onConflict: options?.onConflict })
        .select();
      break;
  }

  if (options?.filter) {
    for (const f of options.filter) {
      query = query.eq(f.column, f.value);
    }
  }

  if (options?.single) {
    query = query.single();
  }

  const result = await query;
  return result as { data: T | null; error: Error | null };
}

const RTDH_REPLICATED_PAYMENT_PROVIDER_SECRET_KEYS: Record<
  string,
  Record<string, string>
> = {
  stripe: {
    secret_key: "api_key",
  },
};

function getRtdhPaymentProviderSecretKeyMap(
  provider: PaymentProvider,
): Record<string, string> {
  const configuredMap =
    RTDH_REPLICATED_PAYMENT_PROVIDER_SECRET_KEYS[provider.key];
  if (configuredMap) return configuredMap;

  return provider.required_settings.reduce<Record<string, string>>(
    (acc, setting) => {
      const key = typeof setting === "string" ? setting : setting.key;
      const type = typeof setting === "string" ? undefined : setting.type;
      if (type === "secret" && key === "webhook_secret") {
        acc[key] = key;
      }

      return acc;
    },
    {},
  );
}

async function replicatePaymentProviderSecretsToRtdh(input: {
  tenantId: string;
  provider: string;
  context?: string;
  secrets: Record<string, string>;
}) {
  if (Object.keys(input.secrets).length === 0) return;

  const { data, error } = await supabase.functions.invoke(
    "set-provider-rtdh-secret",
    {
      body: {
        tenant_id: input.tenantId,
        provider: input.provider,
        ...(input.context ? { context: input.context } : {}),
        secrets: input.secrets,
      },
    },
  );
  if (error) throw error;
  if (data && (data as { error?: string }).error) {
    throw new Error((data as { error: string }).error);
  }
}

export function useTenantPaymentProviders() {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const [
    { data: availableProviders, isLoading: isLoadingProviders },
    { data: tenantProviders, isLoading: isLoadingTenantProviders },
  ] = useQueries({
    queries: [
      // Fetch all active payment providers from platform
      {
        queryKey: ["payment-providers-active"],
        queryFn: async () => {
          const { data, error } = await rawQuery<PaymentProvider[]>(
            "payment_providers",
            "select",
            {
              filter: [{ column: "is_active", value: true }],
              order: "name",
            },
          );

          if (error) throw error;
          return data || [];
        },
      },
      // Fetch tenant's payment provider configurations
      {
        queryKey: ["tenant-payment-providers", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await rawQuery<TenantPaymentProvider[]>(
            "tenant_payment_providers",
            "select",
            {
              filter: [{ column: "tenant_id", value: currentTenantId }],
            },
          );

          if (error) throw error;
          return data || [];
        },
        enabled: !!currentTenantId,
      },
    ],
  });

  // Get combined view of providers with tenant configuration
  const providersWithConfig =
    availableProviders?.map((provider) => {
      const tenantConfig = tenantProviders?.find(
        (tp) => tp.payment_provider_id === provider.id,
      );
      return {
        ...provider,
        tenantConfig: tenantConfig || null,
        isEnabled: tenantConfig?.is_enabled || false,
        configuredSettings: tenantConfig?.settings || {},
      };
    }) || [];

  // Save or update tenant payment provider configuration
  const saveConfiguration = useMutation({
    mutationFn: async ({
      providerId,
      isEnabled,
      settings,
    }: {
      providerId: string;
      isEnabled: boolean;
      settings: Record<string, string>;
    }) => {
      if (!currentTenantId) throw new Error("No tenant selected");

      // Check if tenant already has a configuration for this provider
      const existingConfig = tenantProviders?.find(
        (tp) => tp.payment_provider_id === providerId,
      );
      const provider = availableProviders?.find((p) => p.id === providerId);
      const replicatedSecretKeys = provider
        ? getRtdhPaymentProviderSecretKeyMap(provider)
        : {};
      const secretsToReplicate: Record<string, string> = {};

      for (const [settingsKey, rtdhKey] of Object.entries(
        replicatedSecretKeys,
      )) {
        const nextValue = settings[settingsKey]?.trim();
        const existingValue =
          existingConfig?.settings?.[settingsKey]?.trim() ?? "";
        if (nextValue && nextValue !== existingValue) {
          secretsToReplicate[rtdhKey] = nextValue;
        }
      }

      if (provider) {
        await replicatePaymentProviderSecretsToRtdh({
          tenantId: currentTenantId,
          provider: provider.key,
          secrets: secretsToReplicate,
        });
      }

      const configData = {
        tenant_id: currentTenantId,
        payment_provider_id: providerId,
        is_enabled: isEnabled,
        settings,
      };

      if (existingConfig) {
        // Update existing
        const { data, error } = await rawQuery<TenantPaymentProvider>(
          "tenant_payment_providers",
          "update",
          {
            data: { is_enabled: isEnabled, settings },
            filter: [{ column: "id", value: existingConfig.id }],
            single: true,
          },
        );

        if (error) throw error;
        return { data, beforeData: existingConfig, isNew: false };
      } else {
        // Create new
        const { data, error } = await rawQuery<TenantPaymentProvider>(
          "tenant_payment_providers",
          "insert",
          {
            data: configData,
            single: true,
          },
        );

        if (error) throw error;
        return { data, beforeData: null, isNew: true };
      }
    },
    onSuccess: ({ data, beforeData, isNew }) => {
      queryClient.invalidateQueries({
        queryKey: ["tenant-payment-providers", currentTenantId],
      });
      logAction({
        action: isNew ? "create" : "update",
        entityType: "tenant_payment_provider",
        entityId: data?.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: data as unknown as Record<string, unknown>,
      });
      toast.success("Payment provider configuration saved");
    },
    onError: (error: Error) => {
      toast.error(`Failed to save configuration: ${error.message}`);
    },
  });

  // Toggle provider enabled/disabled quickly
  const toggleProvider = useMutation({
    mutationFn: async ({
      providerId,
      isEnabled,
    }: {
      providerId: string;
      isEnabled: boolean;
    }) => {
      if (!currentTenantId) throw new Error("No tenant selected");

      const existingConfig = tenantProviders?.find(
        (tp) => tp.payment_provider_id === providerId,
      );

      if (existingConfig) {
        const { data, error } = await rawQuery<TenantPaymentProvider>(
          "tenant_payment_providers",
          "update",
          {
            data: { is_enabled: isEnabled },
            filter: [{ column: "id", value: existingConfig.id }],
            single: true,
          },
        );

        if (error) throw error;
        return { data, beforeData: existingConfig };
      } else {
        // Create new configuration with enabled state
        const { data, error } = await rawQuery<TenantPaymentProvider>(
          "tenant_payment_providers",
          "insert",
          {
            data: {
              tenant_id: currentTenantId,
              payment_provider_id: providerId,
              is_enabled: isEnabled,
              settings: {},
            },
            single: true,
          },
        );

        if (error) throw error;
        return { data, beforeData: null };
      }
    },
    onSuccess: ({ data, beforeData }) => {
      queryClient.invalidateQueries({
        queryKey: ["tenant-payment-providers", currentTenantId],
      });
      logAction({
        action: beforeData ? "update" : "create",
        entityType: "tenant_payment_provider",
        entityId: data?.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: data as unknown as Record<string, unknown>,
      });
      toast.success(
        data?.is_enabled
          ? "Payment provider enabled"
          : "Payment provider disabled",
      );
    },
    onError: (error: Error) => {
      toast.error(`Failed to toggle provider: ${error.message}`);
    },
  });

  const saveRtdhWebhookSecret = useMutation({
    mutationFn: async ({
      providerKey,
      value,
    }: {
      providerKey: string;
      value: string;
    }) => {
      if (!currentTenantId) throw new Error("No tenant selected");
      const trimmedValue = value.trim();
      if (trimmedValue.length < 8) {
        throw new Error("Secret must be at least 8 characters");
      }

      await replicatePaymentProviderSecretsToRtdh({
        tenantId: currentTenantId,
        provider: providerKey,
        secrets: { signing_secret: trimmedValue },
      });
    },
    onSuccess: () => {
      toast.success("RTDH signing secret updated");
    },
    onError: (error: Error) => {
      toast.error(`Failed to update RTDH signing secret: ${error.message}`);
    },
  });

  return {
    availableProviders,
    tenantProviders,
    providersWithConfig,
    isLoading: isLoadingProviders || isLoadingTenantProviders,
    saveConfiguration,
    toggleProvider,
    saveRtdhWebhookSecret,
  };
}
