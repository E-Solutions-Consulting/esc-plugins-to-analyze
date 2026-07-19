import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { dateTime } from "./dayjs.ts";

interface TenantIntegrationRecord {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
}

type TenantIntegrationRelation =
  | TenantIntegrationRecord
  | TenantIntegrationRecord[]
  | null;

export interface ProviderPlatformAssignmentRecord {
  id: string;
  product_id: string;
  tenant_integration_id: string;
  provider_product_sku: string | null;
  provider_product_variation_sku: string | null;
  tenant_integrations: TenantIntegrationRelation;
}

export interface ProviderPlatformLoadBalancingRuleRecord {
  id: string;
  product_id: string;
  is_default: boolean;
}

export interface ProviderPlatformLoadBalancingRuleSetStateRecord {
  id: string;
  rule_set_id: string;
  product_id: string;
  state_code: string;
}

export interface ProviderPlatformLoadBalancingRuleSetAllocationRecord {
  id: string;
  rule_set_id: string;
  product_id: string;
  product_provider_platform_id: string;
  allocation_percentage: number;
}

export interface ProviderPlatformSelectionResult {
  productProviderPlatformId: string;
  tenantIntegrationId: string;
  integrationKey: string;
  providerProductSku: string | null;
  providerProductVariationSku: string | null;
  requestedStateCode: string | null;
  appliedStateCode: string | null;
  selectionReason: "single_provider_fallback" | "default_rule" | "state_rule";
  allocationPercentage: number;
  randomBucket: number;
}

function asSingleTenantIntegration(
  value: TenantIntegrationRelation,
): TenantIntegrationRecord | null {
  if (Array.isArray(value)) {
    return value[0] || null;
  }

  return value;
}

export function normalizeStateCode(value: string | null | undefined): string | null {
  if (!value) return null;

  const normalizedValue = value.trim().toUpperCase();
  return normalizedValue.length === 2 ? normalizedValue : null;
}

function getRuleSetForState(params: {
  ruleSets: ProviderPlatformLoadBalancingRuleRecord[];
  states: ProviderPlatformLoadBalancingRuleSetStateRecord[];
  stateCode: string | null;
}): {
  appliedStateCode: string | null;
  selectionReason: "default_rule" | "state_rule";
  ruleSet: ProviderPlatformLoadBalancingRuleRecord;
} | null {
  const { ruleSets, states, stateCode } = params;

  if (stateCode) {
    const matchingState = states.find((state) => state.state_code === stateCode);
    if (matchingState) {
      const matchingRuleSet = ruleSets.find((ruleSet) => ruleSet.id === matchingState.rule_set_id);
      if (!matchingRuleSet) {
        throw new Error(`State rule set ${matchingState.rule_set_id} is missing`);
      }

      return {
        appliedStateCode: stateCode,
        selectionReason: "state_rule",
        ruleSet: matchingRuleSet,
      };
    }
  }

  const defaultRuleSet = ruleSets.find((ruleSet) => ruleSet.is_default);
  if (!defaultRuleSet) {
    return null;
  }

  return {
    appliedStateCode: null,
    selectionReason: "default_rule",
    ruleSet: defaultRuleSet,
  };
}

export function selectProviderPlatformAssignment(params: {
  assignments: ProviderPlatformAssignmentRecord[];
  ruleSets: ProviderPlatformLoadBalancingRuleRecord[];
  states: ProviderPlatformLoadBalancingRuleSetStateRecord[];
  allocations: ProviderPlatformLoadBalancingRuleSetAllocationRecord[];
  stateCode?: string | null;
  randomBucket?: number;
}): ProviderPlatformSelectionResult | null {
  const normalizedStateCode = normalizeStateCode(params.stateCode);
  const eligibleAssignments = params.assignments
    .map((assignment) => ({
      ...assignment,
      tenantIntegration: asSingleTenantIntegration(assignment.tenant_integrations),
    }))
    .filter((assignment) => assignment.tenantIntegration?.is_enabled)
    .sort((left, right) =>
      left.tenant_integration_id.localeCompare(right.tenant_integration_id)
    );

  if (eligibleAssignments.length === 0) {
    return null;
  }

  const randomBucket = params.randomBucket ?? Math.floor(Math.random() * 100) + 1;
  if (randomBucket < 1 || randomBucket > 100) {
    throw new Error("Random bucket must be between 1 and 100");
  }

  const applicableRuleSet = getRuleSetForState({
    ruleSets: params.ruleSets,
    states: params.states,
    stateCode: normalizedStateCode,
  });

  if (!applicableRuleSet) {
    if (eligibleAssignments.length === 1) {
      const fallbackAssignment = eligibleAssignments[0];
      return {
        productProviderPlatformId: fallbackAssignment.id,
        tenantIntegrationId: fallbackAssignment.tenant_integration_id,
        integrationKey: fallbackAssignment.tenantIntegration?.integration_key || "unknown",
        providerProductSku: fallbackAssignment.provider_product_sku,
        providerProductVariationSku:
          fallbackAssignment.provider_product_variation_sku,
        requestedStateCode: normalizedStateCode,
        appliedStateCode: null,
        selectionReason: "single_provider_fallback",
        allocationPercentage: 100,
        randomBucket,
      };
    }

    throw new Error(
      `No provider-platform load balancing rules are configured for ${
        normalizedStateCode || "the default state group"
      }`,
    );
  }

  const percentageByAssignmentId = new Map<string, number>();
  for (const rule of params.allocations.filter(
    (allocation) => allocation.rule_set_id === applicableRuleSet.ruleSet.id,
  )) {
    percentageByAssignmentId.set(
      rule.product_provider_platform_id,
      rule.allocation_percentage,
    );
  }

  let totalPercentage = 0;
  for (const assignment of eligibleAssignments) {
    totalPercentage += percentageByAssignmentId.get(assignment.id) || 0;
  }

  if (totalPercentage !== 100) {
    throw new Error(
      `Provider-platform allocations for ${
        applicableRuleSet.appliedStateCode || "default"
      } must total 100%`,
    );
  }

  let cumulativePercentage = 0;
  for (const assignment of eligibleAssignments) {
    const allocationPercentage = percentageByAssignmentId.get(assignment.id) || 0;
    if (allocationPercentage <= 0) continue;

    cumulativePercentage += allocationPercentage;
    if (randomBucket <= cumulativePercentage) {
      return {
        productProviderPlatformId: assignment.id,
        tenantIntegrationId: assignment.tenant_integration_id,
        integrationKey: assignment.tenantIntegration?.integration_key || "unknown",
        providerProductSku: assignment.provider_product_sku,
        providerProductVariationSku:
          assignment.provider_product_variation_sku,
        requestedStateCode: normalizedStateCode,
        appliedStateCode: applicableRuleSet.appliedStateCode,
        selectionReason: applicableRuleSet.selectionReason,
        allocationPercentage,
        randomBucket,
      };
    }
  }

  throw new Error("Failed to select a provider platform from the configured allocations");
}

export async function resolveProviderPlatformSelection(params: {
  supabase: SupabaseClient;
  tenantId: string;
  productId: string;
  stateCode?: string | null;
  randomBucket?: number;
}): Promise<ProviderPlatformSelectionResult | null> {
  const { supabase, tenantId, productId, stateCode, randomBucket } = params;

  const { data: assignmentRows, error: assignmentError } = await supabase
    .from("product_provider_platforms")
    .select(`
      id,
      product_id,
      tenant_integration_id,
      provider_product_sku,
      provider_product_variation_sku,
      tenant_integrations!inner (
        id,
        tenant_id,
        integration_key,
        is_enabled
      )
    `)
    .eq("product_id", productId)
    .eq("is_enabled", true);

  if (assignmentError) {
    throw new Error(
      `Failed to fetch product provider platforms: ${assignmentError.message}`,
    );
  }

  const assignments = ((assignmentRows || []) as ProviderPlatformAssignmentRecord[])
    .filter(
      (assignment) =>
        asSingleTenantIntegration(assignment.tenant_integrations)?.tenant_id === tenantId,
    )
    .filter(
      (assignment) =>
        asSingleTenantIntegration(assignment.tenant_integrations)?.is_enabled === true,
    );

  if (assignments.length === 0) {
    return null;
  }

  const assignmentIds = assignments.map((assignment) => assignment.id);
  const { data: ruleSetRows, error: ruleSetError } = await supabase
    .from("product_provider_platform_load_balancing_rule_sets")
    .select(`
      id,
      product_id,
      is_default
    `)
    .eq("product_id", productId);

  if (ruleSetError) {
    throw new Error(
      `Failed to fetch provider-platform load balancing rule sets: ${ruleSetError.message}`,
    );
  }

  const ruleSets = (ruleSetRows || []) as ProviderPlatformLoadBalancingRuleRecord[];
  const ruleSetIds = ruleSets.map((ruleSet) => ruleSet.id);

  const [{ data: stateRows, error: stateError }, { data: allocationRows, error: allocationError }] =
    await Promise.all([
      ruleSetIds.length > 0
        ? supabase
            .from("product_provider_platform_load_balancing_rule_set_states")
            .select(`
              id,
              rule_set_id,
              product_id,
              state_code
            `)
            .in("rule_set_id", ruleSetIds)
        : Promise.resolve({ data: [], error: null }),
      ruleSetIds.length > 0
        ? supabase
            .from("product_provider_platform_load_balancing_rule_set_allocations")
            .select(`
              id,
              rule_set_id,
              product_id,
              product_provider_platform_id,
              allocation_percentage
            `)
            .in("rule_set_id", ruleSetIds)
            .in("product_provider_platform_id", assignmentIds)
        : Promise.resolve({ data: [], error: null }),
    ]);

  if (stateError) {
    throw new Error(
      `Failed to fetch provider-platform load balancing rule set states: ${stateError.message}`,
    );
  }

  if (allocationError) {
    throw new Error(
      `Failed to fetch provider-platform load balancing rule set allocations: ${allocationError.message}`,
    );
  }

  return selectProviderPlatformAssignment({
    assignments,
    ruleSets,
    states: (stateRows || []) as ProviderPlatformLoadBalancingRuleSetStateRecord[],
    allocations:
      (allocationRows || []) as ProviderPlatformLoadBalancingRuleSetAllocationRecord[],
    stateCode,
    randomBucket,
  });
}

export async function persistProviderPlatformSelection(params: {
  supabase: SupabaseClient;
  tenantId: string;
  productId: string;
  orderId?: string | null;
  orderNumber?: string | null;
  source: string;
  metadata?: Record<string, unknown>;
  selection: ProviderPlatformSelectionResult;
}): Promise<void> {
  const {
    supabase,
    tenantId,
    productId,
    orderId,
    orderNumber,
    source,
    metadata,
    selection,
  } = params;

  const selectionMetadata = {
    source,
    selection_reason: selection.selectionReason,
    requested_state_code: selection.requestedStateCode,
    applied_state_code: selection.appliedStateCode,
    random_bucket: selection.randomBucket,
    allocation_percentage: selection.allocationPercentage,
    integration_key: selection.integrationKey,
    selected_at: dateTime().toISOString(),
    ...(orderNumber ? { order_number: orderNumber } : {}),
    ...(metadata || {}),
  };

  if (orderId) {
    const { data: existingLinkRows, error: existingLinksError } = await supabase
      .from("order_provider_platform_links")
      .select("id, tenant_integration_id, provider_order_id")
      .eq("tenant_id", tenantId)
      .eq("order_id", orderId);

    if (existingLinksError) {
      throw new Error(
        `Failed to validate existing order provider platform links: ${existingLinksError.message}`,
      );
    }

    const existingLinks = (existingLinkRows || []) as Array<{
      id: string;
      tenant_integration_id: string;
      provider_order_id: string | null;
    }>;

    const conflictingLinks = existingLinks.filter(
      (link) => link.tenant_integration_id !== selection.tenantIntegrationId,
    );
    const conflictingCreatedLinks = conflictingLinks.filter((link) =>
      typeof link.provider_order_id === "string" && link.provider_order_id.trim().length > 0
    );

    if (conflictingCreatedLinks.length > 0) {
      throw new Error(
        "Cannot change provider platform selection because the order already has a provider order id for a different integration",
      );
    }

    const staleLinkIds = conflictingLinks.map((link) => link.id);
    if (staleLinkIds.length > 0) {
      const { error: staleLinkDeleteError } = await supabase
        .from("order_provider_platform_links")
        .delete()
        .in("id", staleLinkIds)
        .eq("tenant_id", tenantId)
        .eq("order_id", orderId);

      if (staleLinkDeleteError) {
        throw new Error(
          `Failed to clean up stale provider platform links: ${staleLinkDeleteError.message}`,
        );
      }
    }

    const { error: linkError } = await supabase
      .from("order_provider_platform_links")
      .upsert(
        {
          tenant_id: tenantId,
          order_id: orderId,
          tenant_integration_id: selection.tenantIntegrationId,
          metadata: selectionMetadata,
        },
        {
          onConflict: "order_id,tenant_integration_id",
          ignoreDuplicates: false,
        },
      );

    if (linkError) {
      throw new Error(
        `Failed to persist selected order provider platform link: ${linkError.message}`,
      );
    }

    const { error: orderUpdateError } = await supabase
      .from("orders")
      .update({
        provider_platform_integration_key: selection.integrationKey,
      })
      .eq("id", orderId)
      .eq("tenant_id", tenantId);

    if (orderUpdateError) {
      throw new Error(
        `Failed to persist order provider platform integration key: ${orderUpdateError.message}`,
      );
    }
  }

  const { error: logError } = await supabase
    .from("provider_platform_selection_logs")
    .insert({
      tenant_id: tenantId,
      product_id: productId,
      order_id: orderId || null,
      tenant_integration_id: selection.tenantIntegrationId,
      product_provider_platform_id: selection.productProviderPlatformId,
      state_code: selection.requestedStateCode,
      applied_state_code: selection.appliedStateCode,
      selection_reason: selection.selectionReason,
      random_bucket: selection.randomBucket,
      metadata: selectionMetadata,
    });

  if (logError) {
    throw new Error(
      `Failed to persist provider platform selection log: ${logError.message}`,
    );
  }
}

export async function resolveAndPersistProviderPlatformSelection(params: {
  supabase: SupabaseClient;
  tenantId: string;
  productId: string;
  stateCode?: string | null;
  orderId?: string | null;
  orderNumber?: string | null;
  source: string;
  metadata?: Record<string, unknown>;
  randomBucket?: number;
}): Promise<ProviderPlatformSelectionResult | null> {
  const selection = await resolveProviderPlatformSelection(params);
  if (!selection) {
    return null;
  }

  await persistProviderPlatformSelection({
    supabase: params.supabase,
    tenantId: params.tenantId,
    productId: params.productId,
    orderId: params.orderId,
    orderNumber: params.orderNumber,
    source: params.source,
    metadata: params.metadata,
    selection,
  });

  return selection;
}
