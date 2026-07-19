import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { US_STATES } from "@/lib/usStates";
import { useAuth } from "@/stores/authStore";

const US_STATE_CODES = new Set<string>(US_STATES.map((state) => state.code));

export function useTenantAllowedStates() {
  const { currentTenantId } = useAuth();

  const query = useQuery({
    queryKey: ["tenant-allowed-states", currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];

      const { data, error } = await supabase
        .from("tenant_settings")
        .select("allowed_states")
        .eq("tenant_id", currentTenantId)
        .maybeSingle();

      if (error) throw error;

      const states = data?.allowed_states || [];
      return states
        .filter((state): state is string => typeof state === "string")
        .map((state) => state.trim().toUpperCase())
        .filter(Boolean);
    },
    enabled: !!currentTenantId,
  });

  const configuredStateCodes = Array.from(
    new Set((query.data || []).filter((code) => US_STATE_CODES.has(code))),
  );
  const availableStates = (query.data || []).length > 0
    ? US_STATES.filter((state) => configuredStateCodes.includes(state.code))
    : [...US_STATES];

  return {
    ...query,
    availableStates,
  };
}
