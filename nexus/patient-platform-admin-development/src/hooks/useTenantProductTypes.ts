/**
 * useTenantProductTypes — the catalog's top-level product TYPES (Medications,
 * Labs, Fitness, Wearables, Experiences) joined with this tenant's activation.
 *
 * - product_types: global definitions (key/name/description/availability), managed
 *   by superadmin. `availability` is 'available' | 'coming_soon'.
 * - tenant_product_types: per-tenant enablement (is_enabled). Tenant admins toggle
 *   which available types they offer.
 *
 * product_types / tenant_product_types are not in the generated Database type
 * union yet, so we cast the table name like the rest of the app does for
 * product_categories.
 */
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "@/hooks/useAuditLog";
import { toast } from "sonner";

export type ProductTypeAvailability = "available" | "coming_soon";

export interface ProductType {
  id: string;
  key: string;
  name: string;
  description: string | null;
  availability: ProductTypeAvailability;
  display_order: number;
  is_active: boolean;
}

export interface TenantProductType extends ProductType {
  /** Whether this tenant has enabled the type. */
  isEnabled: boolean;
}

// product_types / tenant_product_types aren't in the generated Database types
// union; cast the table name the same way the app does for product_categories.
const PRODUCT_TYPES_TABLE = "product_types" as "medication_capabilities";
const TENANT_PRODUCT_TYPES_TABLE =
  "tenant_product_types" as "medication_capabilities";

export function useTenantProductTypes() {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const queryKey = ["tenant-product-types", currentTenantId];

  const query = useQuery({
    queryKey,
    queryFn: async (): Promise<TenantProductType[]> => {
      const { data: typesData, error: typesError } = await supabase
        .from(PRODUCT_TYPES_TABLE)
        .select("*")
        .order("display_order", { ascending: true })
        .order("name", { ascending: true });
      if (typesError) throw typesError;
      const types = (typesData ?? []) as unknown as ProductType[];

      // Read the tenant's explicit enable/disable rows. We track BOTH states so we
      // can tell "explicitly disabled" apart from "no row yet".
      const explicitEnabled = new Map<string, boolean>();
      if (currentTenantId) {
        const { data: tenantData, error: tenantError } = await supabase
          .from(TENANT_PRODUCT_TYPES_TABLE)
          .select("product_type_id, is_enabled")
          .eq("tenant_id", currentTenantId);
        if (tenantError) throw tenantError;
        for (
          const row of (tenantData ?? []) as unknown as {
            product_type_id: string;
            is_enabled: boolean;
          }[]
        ) {
          explicitEnabled.set(row.product_type_id, row.is_enabled);
        }
      }

      // An AVAILABLE type is enabled by default when the tenant has no explicit
      // row, so every tenant (incl. brand-new ones, or DBs where the backfill
      // hasn't run) sees Medications without manual setup. An explicit row always
      // wins (lets an admin opt out). Coming-soon types are never enabled.
      return types
        .filter((type) => type.is_active)
        .map((type) => {
          const explicit = explicitEnabled.get(type.id);
          const isEnabled = explicit !== undefined
            ? explicit
            : type.availability === "available";
          return { ...type, isEnabled };
        });
    },
    enabled: true,
  });

  // Enable/disable a product type for the current tenant (upsert on the unique
  // (tenant_id, product_type_id) pair).
  const setTypeEnabled = useMutation({
    mutationFn: async (
      { productTypeId, isEnabled }: {
        productTypeId: string;
        isEnabled: boolean;
      },
    ) => {
      if (!currentTenantId) throw new Error("No tenant selected");
      const { error } = await supabase
        .from(TENANT_PRODUCT_TYPES_TABLE)
        .upsert(
          {
            tenant_id: currentTenantId,
            product_type_id: productTypeId,
            is_enabled: isEnabled,
          } as never,
          { onConflict: "tenant_id,product_type_id" },
        );
      if (error) throw error;
      return { productTypeId, isEnabled };
    },
    onSuccess: ({ isEnabled }) => {
      queryClient.invalidateQueries({ queryKey });
      void logAction({
        action: "update",
        entityType: "tenant_product_type",
        afterData: { isEnabled },
      });
      toast.success(isEnabled ? "Product type enabled" : "Product type disabled");
    },
    onError: (error) =>
      toast.error(
        error instanceof Error ? error.message : "Failed to update product type",
      ),
  });

  return {
    productTypes: query.data ?? [],
    isLoading: query.isLoading,
    setTypeEnabled,
  };
}
