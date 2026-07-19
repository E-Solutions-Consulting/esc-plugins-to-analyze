import { supabase } from "@/integrations/supabase/client";
import { useQuery } from "@tanstack/react-query";

const JOTFORM_INTEGRATION_KEY = "jotform";

function hasNonEmptySetting(settings: unknown, key: string): boolean {
  if (!settings || typeof settings !== "object" || Array.isArray(settings)) {
    return false;
  }

  const value = (settings as Record<string, unknown>)[key];
  return typeof value === "string" && value.trim().length > 0;
}

export function useJotformIntegrationStatus(
  tenantId: string | null | undefined,
) {
  const query = useQuery({
    queryKey: ["tenant-jotform-integration-status", tenantId],
    queryFn: async () => {
      if (!tenantId) return null;

      const { data, error } = await supabase
        .from("tenant_integrations")
        .select("is_enabled, settings")
        .eq("tenant_id", tenantId)
        .eq("integration_key", JOTFORM_INTEGRATION_KEY)
        .maybeSingle();

      if (error) throw error;
      return data;
    },
    enabled: Boolean(tenantId),
  });

  const isConfigured = Boolean(
    query.data?.is_enabled &&
      hasNonEmptySetting(query.data.settings, "api_key") &&
      hasNonEmptySetting(query.data.settings, "api_url"),
  );
  const apiUrl = query.data?.settings &&
      typeof query.data.settings === "object" &&
      !Array.isArray(query.data.settings) &&
      typeof (query.data.settings as Record<string, unknown>).api_url ===
        "string"
    ? String((query.data.settings as Record<string, unknown>).api_url).trim()
    : "";
  const defaultWebhookUrl = query.data?.settings &&
      typeof query.data.settings === "object" &&
      !Array.isArray(query.data.settings) &&
      typeof (query.data.settings as Record<string, unknown>)
          .default_webhook_url === "string"
    ? String(
      (query.data.settings as Record<string, unknown>).default_webhook_url,
    ).trim()
    : "";

  return {
    ...query,
    isConfigured,
    apiUrl,
    defaultWebhookUrl,
    hasDefaultWebhookUrl: defaultWebhookUrl.length > 0,
  };
}
