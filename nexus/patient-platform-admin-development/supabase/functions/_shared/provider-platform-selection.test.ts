import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  normalizeStateCode,
  selectProviderPlatformAssignment,
  type ProviderPlatformAssignmentRecord,
  type ProviderPlatformLoadBalancingRuleRecord,
  type ProviderPlatformLoadBalancingRuleSetAllocationRecord,
  type ProviderPlatformLoadBalancingRuleSetStateRecord,
} from "./provider-platform-selection.ts";

const baseAssignments: ProviderPlatformAssignmentRecord[] = [
  {
    id: "assignment-a",
    product_id: "product-1",
    tenant_integration_id: "integration-a",
    provider_product_sku: null,
    provider_product_variation_sku: "sku-a",
    tenant_integrations: {
      id: "integration-a",
      tenant_id: "tenant-1",
      integration_key: "telegramd",
      is_enabled: true,
    },
  } as ProviderPlatformAssignmentRecord,
  {
    id: "assignment-b",
    product_id: "product-1",
    tenant_integration_id: "integration-b",
    provider_product_sku: null,
    provider_product_variation_sku: "sku-b",
    tenant_integrations: {
      id: "integration-b",
      tenant_id: "tenant-1",
      integration_key: "md_integrations",
      is_enabled: true,
    },
  } as ProviderPlatformAssignmentRecord,
];

const baseRules: ProviderPlatformLoadBalancingRuleRecord[] = [
  {
    id: "rule-set-default",
    product_id: "product-1",
    is_default: true,
  },
  {
    id: "rule-set-tx",
    product_id: "product-1",
    is_default: false,
  },
];

const baseStates: ProviderPlatformLoadBalancingRuleSetStateRecord[] = [
  {
    id: "state-tx",
    rule_set_id: "rule-set-tx",
    product_id: "product-1",
    state_code: "TX",
  },
];

const baseAllocations: ProviderPlatformLoadBalancingRuleSetAllocationRecord[] = [
  {
    id: "alloc-default-a",
    rule_set_id: "rule-set-default",
    product_id: "product-1",
    product_provider_platform_id: "assignment-a",
    allocation_percentage: 70,
  },
  {
    id: "alloc-default-b",
    rule_set_id: "rule-set-default",
    product_id: "product-1",
    product_provider_platform_id: "assignment-b",
    allocation_percentage: 30,
  },
  {
    id: "alloc-tx-a",
    rule_set_id: "rule-set-tx",
    product_id: "product-1",
    product_provider_platform_id: "assignment-a",
    allocation_percentage: 20,
  },
  {
    id: "alloc-tx-b",
    rule_set_id: "rule-set-tx",
    product_id: "product-1",
    product_provider_platform_id: "assignment-b",
    allocation_percentage: 80,
  },
];

Deno.test("normalizeStateCode uppercases and trims two-letter values", () => {
  assertEquals(normalizeStateCode(" tx "), "TX");
  assertEquals(normalizeStateCode("Texas"), null);
  assertEquals(normalizeStateCode(null), null);
});

Deno.test("selectProviderPlatformAssignment uses exact state rules before default rules", () => {
  const selection = selectProviderPlatformAssignment({
    assignments: baseAssignments,
    ruleSets: baseRules,
    states: baseStates,
    allocations: baseAllocations,
    stateCode: "tx",
    randomBucket: 50,
  });

  assertEquals(selection?.tenantIntegrationId, "integration-b");
  assertEquals(selection?.appliedStateCode, "TX");
  assertEquals(selection?.selectionReason, "state_rule");
  assertEquals(selection?.allocationPercentage, 80);
});

Deno.test("selectProviderPlatformAssignment falls back to default rules when state override is absent", () => {
  const selection = selectProviderPlatformAssignment({
    assignments: baseAssignments,
    ruleSets: baseRules,
    states: baseStates,
    allocations: baseAllocations,
    stateCode: "CA",
    randomBucket: 65,
  });

  assertEquals(selection?.tenantIntegrationId, "integration-a");
  assertEquals(selection?.appliedStateCode, null);
  assertEquals(selection?.selectionReason, "default_rule");
  assertEquals(selection?.allocationPercentage, 70);
});

Deno.test("selectProviderPlatformAssignment falls back to the only provider when rules are absent", () => {
  const selection = selectProviderPlatformAssignment({
    assignments: [baseAssignments[0]],
    ruleSets: [],
    states: [],
    allocations: [],
    stateCode: "CA",
    randomBucket: 100,
  });

  assertEquals(selection?.tenantIntegrationId, "integration-a");
  assertEquals(selection?.selectionReason, "single_provider_fallback");
  assertEquals(selection?.allocationPercentage, 100);
});

Deno.test("selectProviderPlatformAssignment rejects malformed rule totals", () => {
  let message = "";

  try {
    selectProviderPlatformAssignment({
      assignments: baseAssignments,
      ruleSets: [
        {
          id: "bad-default-rule-set",
          product_id: "product-1",
          is_default: true,
        },
      ],
      states: [],
      allocations: [
        {
          id: "bad-rule-a",
          rule_set_id: "bad-default-rule-set",
          product_id: "product-1",
          product_provider_platform_id: "assignment-a",
          allocation_percentage: 60,
        },
        {
          id: "bad-rule-b",
          rule_set_id: "bad-default-rule-set",
          product_id: "product-1",
          product_provider_platform_id: "assignment-b",
          allocation_percentage: 20,
        },
      ],
      randomBucket: 10,
    });
  } catch (error) {
    message = error instanceof Error ? error.message : String(error);
  }

  assertMatch(message, /must total 100%/);
});

Deno.test("selectProviderPlatformAssignment rejects missing rules when multiple providers are enabled", () => {
  let message = "";

  try {
    selectProviderPlatformAssignment({
      assignments: baseAssignments,
      ruleSets: [],
      states: [],
      allocations: [],
      stateCode: "WA",
      randomBucket: 10,
    });
  } catch (error) {
    message = error instanceof Error ? error.message : String(error);
  }

  assertMatch(message, /No provider-platform load balancing rules/);
});
