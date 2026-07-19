import { useMemo } from "react";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "./useAuditLog";

export interface ProductProviderPlatform {
  id: string;
  product_id: string;
  tenant_integration_id: string;
  jotform_new_order_questionnaire_id: string | null;
  jotform_renewall_questionnaire_id: string | null;
  offering_id: string | null;
  questionnaire_id: string | null;
  provider_product_sku: string | null;
  provider_product_variation_sku: string | null;
  integration_mode: "direct" | "jotform";
  is_enabled: boolean;
  created_at: string;
}

export interface ProductProviderPlatformMedication {
  productMedicationId: string;
  medicationId: string;
  medicationTitle: string;
  medicationImageUrl: string | null;
  offeringId: string;
}

type ProductProviderPlatformLegacyColumns = {
  jotform_first_time_questionnaire_id?: string | null;
  jotform_questionnaire_id?: string | null;
  jotform_renewal_questionnaire_id?: string | null;
};

export interface ProductProviderPlatformLoadBalancingRuleSet {
  id: string;
  product_id: string;
  is_default: boolean;
  created_at: string;
  updated_at: string;
  states: Array<{
    id: string;
    state_code: string;
  }>;
  allocations: Array<{
    id: string;
    product_provider_platform_id: string;
    allocation_percentage: number;
  }>;
}

interface LoadBalancingRuleSetDraft {
  isDefault: boolean;
  stateCodes: string[];
  allocations: Array<{
    productProviderPlatformId: string;
    allocationPercentage: number;
  }>;
}

function uniqueByKey<T>(items: T[], getKey: (item: T) => string): T[] {
  const seenKeys = new Set<string>();

  return items.filter((item) => {
    const key = getKey(item);
    if (seenKeys.has(key)) return false;
    seenKeys.add(key);
    return true;
  });
}

export function useProductProviderPlatforms(productId: string) {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  const [
    {
      data: providerPlatformIntegrations = [],
      isLoading: isLoadingProviderPlatforms,
    },
    {
      data: productProviderPlatforms = [],
      isLoading: isLoadingProductProviderPlatforms,
    },
    { data: linkedMedications = [], isLoading: isLoadingLinkedMedications },
    { data: tenantAllowedStates = [], isLoading: isLoadingTenantAllowedStates },
    {
      data: loadBalancingRuleSets = [],
      isLoading: isLoadingLoadBalancingRules,
    },
  ] = useQueries({
    queries: [
      {
        queryKey: ["tenant-provider-platform-integrations", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const {
            data: platformIntegrations,
            error: platformIntegrationsError,
          } = await supabase
            .from("platform_integrations")
            .select("id, key, name, description, category, is_active")
            .eq("is_active", true)
            .eq("category", "provider_platform")
            .order("name");

          if (platformIntegrationsError) throw platformIntegrationsError;
          if (!platformIntegrations || platformIntegrations.length === 0) {
            return [];
          }

          const providerIntegrationKeys = platformIntegrations.map((
            integration,
          ) => integration.key);

          const { data: tenantIntegrations, error: tenantIntegrationsError } =
            await supabase
              .from("tenant_integrations")
              .select("*")
              .eq("tenant_id", currentTenantId)
              .eq("is_enabled", true)
              .in("integration_key", providerIntegrationKeys);

          if (tenantIntegrationsError) throw tenantIntegrationsError;

          return (tenantIntegrations || [])
            .map((tenantIntegration) => {
              const integration = platformIntegrations.find(
                (platformIntegration) =>
                  platformIntegration.key === tenantIntegration.integration_key,
              );

              if (!integration) return null;

              return {
                tenantIntegration: tenantIntegration as TenantIntegration,
                integration: integration as PlatformIntegration,
              };
            })
            .filter(Boolean) as Array<{
              tenantIntegration: TenantIntegration;
              integration: PlatformIntegration;
            }>;
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: ["product-provider-platforms", productId],
        queryFn: async () => {
          if (!productId) return [];

          const { data, error } = await supabase
            .from("product_provider_platforms")
            .select("*")
            .eq("product_id", productId);

          if (error) throw error;
          return data as ProductProviderPlatform[];
        },
        enabled: !!productId,
      },
      {
        queryKey: ["product-provider-platform-linked-medications", productId],
        queryFn: async () => {
          if (!productId) return [];

          const { data, error } = await supabase
            .from("product_medications")
            .select(`
              id,
              medication_id,
              medication:medications(id, title, image_url, offering_id)
            `)
            .eq("product_id", productId)
            .order("id", { ascending: true });

          if (error) throw error;

          return (data || []).map((entry) => {
            const medication = entry.medication as
              | {
                id: string;
                title: string | null;
                image_url: string | null;
                offering_id: string | null;
              }
              | null;

            return {
              productMedicationId: entry.id,
              medicationId: entry.medication_id,
              medicationTitle: medication?.title || "Unknown medication",
              medicationImageUrl: medication?.image_url || null,
              offeringId: medication?.offering_id || "",
            };
          }) as ProductProviderPlatformMedication[];
        },
        enabled: !!productId,
      },
      {
        queryKey: ["tenant-allowed-states", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await supabase
            .from("tenant_settings")
            .select("allowed_states")
            .eq("tenant_id", currentTenantId)
            .maybeSingle();

          if (error) throw error;

          const states =
            (data as { allowed_states?: string[] } | null)?.allowed_states ||
            [];
          return states.filter((state): state is string =>
            typeof state === "string"
          ).map((state) => state.toUpperCase().trim()).filter(Boolean);
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: [
          "product-provider-platform-load-balancing-rule-sets",
          productId,
        ],
        queryFn: async () => {
          if (!productId) return [];

          const { data: ruleSets, error: ruleSetsError } = await supabase
            .from("product_provider_platform_load_balancing_rule_sets")
            .select("*")
            .eq("product_id", productId)
            .order("is_default", { ascending: false })
            .order("created_at", { ascending: true });

          if (ruleSetsError) throw ruleSetsError;
          if (!ruleSets || ruleSets.length === 0) return [];

          const ruleSetIds = ruleSets.map((ruleSet) => ruleSet.id);

          const [
            { data: states, error: statesError },
            { data: allocations, error: allocationsError },
          ] = await Promise.all([
            supabase
              .from("product_provider_platform_load_balancing_rule_set_states")
              .select("id, rule_set_id, state_code")
              .in("rule_set_id", ruleSetIds)
              .order("state_code", { ascending: true }),
            supabase
              .from(
                "product_provider_platform_load_balancing_rule_set_allocations",
              )
              .select(
                "id, rule_set_id, product_provider_platform_id, allocation_percentage",
              )
              .in("rule_set_id", ruleSetIds),
          ]);

          if (statesError) throw statesError;
          if (allocationsError) throw allocationsError;

          return uniqueByKey(
            ruleSets.map((ruleSet) => ({
              ...(ruleSet as {
                id: string;
                product_id: string;
                is_default: boolean;
                created_at: string;
                updated_at: string;
              }),
              states: uniqueByKey(
                (states || [])
                  .filter((state) => state.rule_set_id === ruleSet.id)
                  .map((state) => ({
                    id: state.id,
                    state_code: state.state_code,
                  })),
                (state) => state.state_code,
              ),
              allocations: uniqueByKey(
                (allocations || [])
                  .filter((allocation) => allocation.rule_set_id === ruleSet.id)
                  .map((allocation) => ({
                    id: allocation.id,
                    product_provider_platform_id:
                      allocation.product_provider_platform_id,
                    allocation_percentage: allocation.allocation_percentage,
                  })),
                (allocation) => allocation.product_provider_platform_id,
              ),
            })) as ProductProviderPlatformLoadBalancingRuleSet[],
            (ruleSet) => ruleSet.id,
          );
        },
        enabled: !!productId,
      },
    ],
  });

  const providersWithAssignment = useMemo(
    () =>
      providerPlatformIntegrations.map(({ integration, tenantIntegration }) => {
        const assignment = productProviderPlatforms.find(
          (productProviderPlatform) =>
            productProviderPlatform.tenant_integration_id ===
              tenantIntegration.id,
        );
        const legacyAssignment = assignment as
          | (ProductProviderPlatform & ProductProviderPlatformLegacyColumns)
          | undefined;

        return {
          id: tenantIntegration.id,
          integration,
          tenantIntegration,
          isAssigned: !!assignment,
          assignmentId: assignment?.id,
          jotformNewOrderQuestionnaireId:
            assignment?.jotform_new_order_questionnaire_id ||
            legacyAssignment?.jotform_first_time_questionnaire_id ||
            "",
          jotformRenewalQuestionnaireId:
            assignment?.jotform_renewall_questionnaire_id ||
            legacyAssignment?.jotform_questionnaire_id ||
            legacyAssignment?.jotform_renewal_questionnaire_id ||
            "",
          offeringId: assignment?.offering_id || "",
          questionnaireId: assignment?.questionnaire_id || "",
          providerProductSku: assignment?.provider_product_sku || "",
          providerProductVariationSku:
            assignment?.provider_product_variation_sku || "",
        };
      }),
    [productProviderPlatforms, providerPlatformIntegrations],
  );

  const invalidateAllQueries = () => {
    queryClient.invalidateQueries({
      queryKey: ["product-provider-platforms", productId],
    });
    queryClient.invalidateQueries({
      queryKey: ["product-provider-platforms-count", productId],
    });
    queryClient.invalidateQueries({
      queryKey: ["all-product-provider-platforms", currentTenantId],
    });
    queryClient.invalidateQueries({
      queryKey: ["product-provider-platform-linked-medications", productId],
    });
    queryClient.invalidateQueries({
      queryKey: [
        "product-provider-platform-load-balancing-rule-sets",
        productId,
      ],
    });
  };

  const resolveTelegraProductVariation = async ({
    tenantIntegrationId,
    productVariationId,
  }: {
    tenantIntegrationId: string;
    productVariationId: string;
  }): Promise<string> => {
    const {
      data: { session },
    } = await supabase.auth.getSession();

    if (!session?.access_token) {
      throw new Error(
        "You must be signed in to resolve Telegra product variations",
      );
    }

    const lookupResponse = await fetch(
      `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/provider-platform-bridge/telegra-product-variation`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${session.access_token}`,
          apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          tenantIntegrationId,
          productVariationId,
        }),
      },
    );

    const lookupResult = (await lookupResponse.json()) as {
      productId?: string;
      message?: string;
    };

    if (!lookupResponse.ok) {
      throw new Error(
        lookupResult?.message || "Failed to resolve Telegra product variation",
      );
    }

    const resolvedProductId = lookupResult?.productId?.trim();
    if (!resolvedProductId) {
      throw new Error(
        lookupResult?.message ||
          "Telegra product variation lookup did not return a product id",
      );
    }

    return resolvedProductId;
  };

  const validateJotformQuestionnaireForm = async ({
    tenantIntegrationId,
    formId,
  }: {
    tenantIntegrationId: string;
    formId: string;
  }): Promise<string> => {
    const {
      data: { session },
    } = await supabase.auth.getSession();

    if (!session?.access_token) {
      throw new Error("You must be signed in to validate Jotform forms");
    }

    const validationResponse = await fetch(
      `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/provider-platform-bridge/jotform-form-validation`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${session.access_token}`,
          apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          tenantIntegrationId,
          formId,
        }),
      },
    );

    const validationResult =
      (await validationResponse.json().catch(() => null)) as
        | {
          message?: string;
          formId?: string;
          lookupUrl?: string;
        }
        | null;

    if (!validationResponse.ok) {
      const lookupDetail = validationResult?.lookupUrl
        ? ` Lookup URL: ${validationResult.lookupUrl}`
        : "";
      throw new Error(
        `${
          validationResult?.message || "Jotform form validation failed"
        }${lookupDetail}`,
      );
    }

    return validationResult?.formId?.trim() || formId;
  };

  const toggleProductProviderPlatform = useMutation({
    mutationFn: async ({
      tenantIntegrationId,
      enabled,
    }: {
      tenantIntegrationId: string;
      enabled: boolean;
    }) => {
      const existingAssignment = productProviderPlatforms.find(
        (productProviderPlatform) =>
          productProviderPlatform.tenant_integration_id === tenantIntegrationId,
      );

      if (existingAssignment) {
        if (enabled) {
          const { data, error } = await supabase
            .from("product_provider_platforms")
            .update({ is_enabled: true })
            .eq("id", existingAssignment.id)
            .select()
            .single();

          if (error) throw error;
          return {
            action: "update" as const,
            beforeData: existingAssignment,
            data: data as ProductProviderPlatform,
          };
        }

        const { error } = await supabase
          .from("product_provider_platforms")
          .delete()
          .eq("id", existingAssignment.id);

        if (error) throw error;
        return {
          action: "delete" as const,
          beforeData: existingAssignment,
          data: null,
        };
      }

      if (enabled) {
        const { data, error } = await supabase
          .from("product_provider_platforms")
          .insert([
            {
              product_id: productId,
              tenant_integration_id: tenantIntegrationId,
              is_enabled: true,
            },
          ])
          .select()
          .single();

        if (error) throw error;
        return {
          action: "create" as const,
          beforeData: null,
          data: data as ProductProviderPlatform,
        };
      }

      return {
        action: "none" as const,
        beforeData: null,
        data: null,
      };
    },
    onSuccess: ({ action, beforeData, data }) => {
      invalidateAllQueries();

      if (action !== "none") {
        logAction({
          action,
          entityType: "product_provider_platform",
          entityId: data?.id || beforeData?.id,
          beforeData: beforeData as unknown as Record<string, unknown>,
          afterData: data as unknown as Record<string, unknown>,
        });
      }

      toast.success(
        action === "create" || action === "update"
          ? "Provider platform enabled for product"
          : "Provider platform removed from product",
      );
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to update provider platform",
      );
    },
  });

  const saveProviderPlatformSku = useMutation({
    mutationFn: async ({
      tenantIntegrationId,
      providerProductVariationSku,
      providerProductSku,
      jotformNewOrderQuestionnaireId,
      jotformRenewalQuestionnaireId,
      offeringId,
      questionnaireId,
      integrationMode,
    }: {
      tenantIntegrationId: string;
      providerProductVariationSku?: string;
      providerProductSku?: string;
      jotformNewOrderQuestionnaireId?: string;
      jotformRenewalQuestionnaireId?: string;
      offeringId?: string;
      questionnaireId?: string;
      integrationMode?: "direct" | "jotform";
    }) => {
      const existingAssignment = productProviderPlatforms.find(
        (productProviderPlatform) =>
          productProviderPlatform.tenant_integration_id === tenantIntegrationId,
      );
      const providerIntegration = providerPlatformIntegrations.find(
        ({ tenantIntegration }) => tenantIntegration.id === tenantIntegrationId,
      );
      const integrationKey = providerIntegration?.integration.key || null;

      if (!existingAssignment) {
        throw new Error(
          "Enable the provider platform before saving provider settings",
        );
      }

      const normalizedProviderProductVariationSku = providerProductVariationSku
        ?.trim();
      let normalizedProviderProductSku = providerProductSku?.trim();
      let normalizedJotformNewOrderQuestionnaireId =
        jotformNewOrderQuestionnaireId?.trim();
      let normalizedJotformRenewalQuestionnaireId =
        jotformRenewalQuestionnaireId?.trim();
      const normalizedOfferingId = offeringId?.trim();
      const normalizedQuestionnaireId = questionnaireId?.trim();

      if (
        typeof normalizedProviderProductVariationSku === "string" &&
        normalizedProviderProductVariationSku.length > 100
      ) {
        throw new Error(
          "Provider product variation SKU must be 100 characters or less",
        );
      }
      if (
        typeof normalizedProviderProductSku === "string" &&
        normalizedProviderProductSku.length > 100
      ) {
        throw new Error("Provider product SKU must be 100 characters or less");
      }
      if (
        typeof normalizedOfferingId === "string" &&
        normalizedOfferingId.length > 100
      ) {
        throw new Error("Offering ID must be 100 characters or less");
      }
      if (
        typeof normalizedJotformNewOrderQuestionnaireId === "string" &&
        normalizedJotformNewOrderQuestionnaireId.length > 128
      ) {
        throw new Error(
          "Jotform new order questionnaire ID must be 128 characters or less",
        );
      }
      if (
        typeof normalizedJotformRenewalQuestionnaireId === "string" &&
        normalizedJotformRenewalQuestionnaireId.length > 128
      ) {
        throw new Error(
          "Jotform renewal questionnaire ID must be 128 characters or less",
        );
      }
      if (
        typeof normalizedQuestionnaireId === "string" &&
        normalizedQuestionnaireId.length > 100
      ) {
        throw new Error("Questionnaire ID must be 100 characters or less");
      }

      if (
        integrationKey === "telegramd" &&
        typeof providerProductVariationSku !== "undefined"
      ) {
        if (normalizedProviderProductVariationSku) {
          normalizedProviderProductSku = await resolveTelegraProductVariation({
            tenantIntegrationId,
            productVariationId: normalizedProviderProductVariationSku,
          });
        } else {
          normalizedProviderProductSku = "";
        }
      }

      if (
        typeof jotformNewOrderQuestionnaireId !== "undefined" &&
        normalizedJotformNewOrderQuestionnaireId
      ) {
        normalizedJotformNewOrderQuestionnaireId =
          await validateJotformQuestionnaireForm({
            tenantIntegrationId,
            formId: normalizedJotformNewOrderQuestionnaireId,
          });
      }
      if (
        typeof jotformRenewalQuestionnaireId !== "undefined" &&
        normalizedJotformRenewalQuestionnaireId
      ) {
        normalizedJotformRenewalQuestionnaireId =
          await validateJotformQuestionnaireForm({
            tenantIntegrationId,
            formId: normalizedJotformRenewalQuestionnaireId,
          });
      }

      const updatePayload: {
        jotform_new_order_questionnaire_id?: string | null;
        jotform_renewall_questionnaire_id?: string | null;
        offering_id?: string | null;
        questionnaire_id?: string | null;
        provider_product_sku?: string | null;
        provider_product_variation_sku?: string | null;
        integration_mode?: "direct" | "jotform";
      } = {};

      if (typeof integrationMode !== "undefined") {
        updatePayload.integration_mode = integrationMode;
      }

      if (typeof providerProductVariationSku !== "undefined") {
        updatePayload.provider_product_variation_sku =
          normalizedProviderProductVariationSku || null;
      }

      if (
        typeof providerProductSku !== "undefined" ||
        (integrationKey === "telegramd" &&
          typeof providerProductVariationSku !== "undefined")
      ) {
        updatePayload.provider_product_sku = normalizedProviderProductSku ||
          null;
      }
      if (typeof jotformNewOrderQuestionnaireId !== "undefined") {
        updatePayload.jotform_new_order_questionnaire_id =
          normalizedJotformNewOrderQuestionnaireId || null;
      }
      if (typeof jotformRenewalQuestionnaireId !== "undefined") {
        updatePayload.jotform_renewall_questionnaire_id =
          normalizedJotformRenewalQuestionnaireId || null;
      }
      if (typeof offeringId !== "undefined") {
        updatePayload.offering_id = normalizedOfferingId || null;
      }
      if (typeof questionnaireId !== "undefined") {
        updatePayload.questionnaire_id = normalizedQuestionnaireId || null;
      }

      const { data, error } = await supabase
        .from("product_provider_platforms")
        .update(updatePayload)
        .eq("id", existingAssignment.id)
        .select()
        .single();

      if (error) throw error;

      return {
        beforeData: existingAssignment,
        data: data as ProductProviderPlatform,
        providerProductSku: typeof providerProductSku === "undefined"
          ? undefined
          : normalizedProviderProductSku || null,
        jotformNewOrderQuestionnaireId:
          typeof jotformNewOrderQuestionnaireId === "undefined"
            ? undefined
            : normalizedJotformNewOrderQuestionnaireId || null,
        jotformRenewalQuestionnaireId:
          typeof jotformRenewalQuestionnaireId === "undefined"
            ? undefined
            : normalizedJotformRenewalQuestionnaireId || null,
        offeringId: typeof offeringId === "undefined"
          ? undefined
          : normalizedOfferingId || null,
        questionnaireId: typeof questionnaireId === "undefined"
          ? undefined
          : normalizedQuestionnaireId || null,
        providerProductVariationSku:
          typeof providerProductVariationSku === "undefined"
            ? undefined
            : normalizedProviderProductVariationSku || null,
      };
    },
    onSuccess: ({
      beforeData,
      data,
      offeringId,
      questionnaireId,
      providerProductVariationSku,
      providerProductSku,
      jotformNewOrderQuestionnaireId,
      jotformRenewalQuestionnaireId,
    }) => {
      queryClient.setQueryData<ProductProviderPlatform[]>(
        ["product-provider-platforms", productId],
        (current) =>
          (current || []).map((entry) =>
            entry.id === data.id ? (data as ProductProviderPlatform) : entry
          ),
      );
      queryClient.invalidateQueries({
        queryKey: ["product-provider-platforms", productId],
      });
      queryClient.invalidateQueries({ queryKey: ["jotform-webhook-status"] });
      logAction({
        action: "update",
        entityType: "product_provider_platform",
        entityId: beforeData.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: data as unknown as Record<string, unknown>,
      });
      toast.success(
        offeringId ||
          questionnaireId ||
          jotformNewOrderQuestionnaireId ||
          jotformRenewalQuestionnaireId ||
          providerProductVariationSku ||
          providerProductSku
          ? "Provider platform settings saved"
          : "Provider platform settings cleared",
      );
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save provider platform settings",
      );
    },
  });

  const saveLoadBalancingRules = useMutation({
    mutationFn: async (ruleSetDrafts: LoadBalancingRuleSetDraft[]) => {
      const beforeData = loadBalancingRuleSets;

      const { data: existingRuleSets, error: fetchError } = await supabase
        .from("product_provider_platform_load_balancing_rule_sets")
        .select("id")
        .eq("product_id", productId);

      if (fetchError) throw fetchError;

      const existingRuleSetIds = (existingRuleSets || []).map((ruleSet) =>
        ruleSet.id
      );

      if (existingRuleSetIds.length > 0) {
        const { error: deleteError } = await supabase
          .from("product_provider_platform_load_balancing_rule_sets")
          .delete()
          .in("id", existingRuleSetIds);

        if (deleteError) throw deleteError;
      }

      if (ruleSetDrafts.length === 0) {
        return {
          beforeData,
          data: [] as ProductProviderPlatformLoadBalancingRuleSet[],
        };
      }

      const { data: insertedRuleSets, error: insertRuleSetsError } =
        await supabase
          .from("product_provider_platform_load_balancing_rule_sets")
          .insert(
            ruleSetDrafts.map((ruleSetDraft) => ({
              product_id: productId,
              is_default: ruleSetDraft.isDefault,
            })),
          )
          .select();

      if (insertRuleSetsError) throw insertRuleSetsError;

      const inserted = insertedRuleSets || [];
      const statePayload = inserted.flatMap((ruleSet, index) =>
        ruleSetDrafts[index].stateCodes.map((stateCode) => ({
          rule_set_id: ruleSet.id,
          product_id: productId,
          state_code: stateCode,
        }))
      );
      const allocationPayload = inserted.flatMap((ruleSet, index) =>
        ruleSetDrafts[index].allocations.map((allocation) => ({
          rule_set_id: ruleSet.id,
          product_id: productId,
          product_provider_platform_id: allocation.productProviderPlatformId,
          allocation_percentage: allocation.allocationPercentage,
        }))
      );

      if (statePayload.length > 0) {
        const { error: insertStatesError } = await supabase
          .from("product_provider_platform_load_balancing_rule_set_states")
          .insert(statePayload);

        if (insertStatesError) throw insertStatesError;
      }

      if (allocationPayload.length > 0) {
        const { error: insertAllocationsError } = await supabase
          .from("product_provider_platform_load_balancing_rule_set_allocations")
          .insert(allocationPayload);

        if (insertAllocationsError) throw insertAllocationsError;
      }

      return {
        beforeData,
        data: inserted.map((ruleSet, index) => ({
          ...(ruleSet as {
            id: string;
            product_id: string;
            is_default: boolean;
            created_at: string;
            updated_at: string;
          }),
          states: ruleSetDrafts[index].stateCodes.map((stateCode) => ({
            id: crypto.randomUUID(),
            state_code: stateCode,
          })),
          allocations: ruleSetDrafts[index].allocations.map((allocation) => ({
            id: crypto.randomUUID(),
            product_provider_platform_id: allocation.productProviderPlatformId,
            allocation_percentage: allocation.allocationPercentage,
          })),
        })) as ProductProviderPlatformLoadBalancingRuleSet[],
      };
    },
    onSuccess: ({ beforeData, data }) => {
      queryClient.invalidateQueries({
        queryKey: [
          "product-provider-platform-load-balancing-rule-sets",
          productId,
        ],
      });
      logAction({
        action: "update",
        entityType: "product_provider_platform_load_balancing_rule_set",
        entityId: productId,
        beforeData: { ruleSets: beforeData } as Record<string, unknown>,
        afterData: { ruleSets: data } as Record<string, unknown>,
      });
      toast.success("Load balancing rules saved");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save load balancing rules",
      );
    },
  });

  return {
    providersWithAssignment,
    productProviderPlatforms,
    linkedMedications,
    loadBalancingRuleSets,
    tenantAllowedStates,
    isLoading: isLoadingProviderPlatforms ||
      isLoadingProductProviderPlatforms ||
      isLoadingLinkedMedications ||
      isLoadingTenantAllowedStates ||
      isLoadingLoadBalancingRules,
    toggleProductProviderPlatform,
    saveProviderPlatformSku,
    resolveTelegraProductVariation,
    saveLoadBalancingRules,
  };
}
