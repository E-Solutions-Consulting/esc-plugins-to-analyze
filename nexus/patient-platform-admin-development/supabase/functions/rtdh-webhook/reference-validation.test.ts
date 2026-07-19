import { assertEquals } from "../_test/assert.ts";
import {
  resolvePaymentTransactionReference,
  resolveTenantIntegrationReference,
} from "./reference-validation.ts";

Deno.test("resolveTenantIntegrationReference scopes provider lookup to resolved tenant", async () => {
  const calls: Array<[string, string, string?]> = [];
  const builder = {
    select(columns: string) {
      calls.push(["select", columns]);
      return builder;
    },
    eq(column: string, value: string) {
      calls.push(["eq", column, value]);
      return builder;
    },
    limit(count: number) {
      calls.push(["limit", String(count)]);
      return builder;
    },
    maybeSingle() {
      calls.push(["maybeSingle", ""]);
      return Promise.resolve({ data: { id: "integration-1" }, error: null });
    },
  };
  const supabase = {
    from(table: "tenant_integrations") {
      calls.push(["from", table]);
      return builder;
    },
  };

  await resolveTenantIntegrationReference(
    supabase,
    "tenant-1",
    "telegramd",
  );

  assertEquals(calls, [
    ["from", "tenant_integrations"],
    ["select", "id"],
    ["eq", "tenant_id", "tenant-1"],
    ["eq", "integration_key", "telegramd"],
    ["maybeSingle", ""],
  ]);
});

Deno.test("resolveTenantIntegrationReference skips lookup without tenant or provider", () => {
  const supabase = {
    from() {
      throw new Error("query should not be built");
    },
  };

  assertEquals(
    resolveTenantIntegrationReference(supabase, null, "telegramd"),
    null,
  );
  assertEquals(
    resolveTenantIntegrationReference(supabase, "tenant-1", null),
    null,
  );
});

Deno.test("resolvePaymentTransactionReference scopes provider identifiers to resolved tenant", async () => {
  const calls: Array<[string, string, string?]> = [];
  const builder = {
    select(columns: string) {
      calls.push(["select", columns]);
      return builder;
    },
    eq(column: string, value: string) {
      calls.push(["eq", column, value]);
      return builder;
    },
    limit(count: number) {
      calls.push(["limit", String(count)]);
      return builder;
    },
    maybeSingle() {
      calls.push(["maybeSingle", ""]);
      return Promise.resolve({ data: { id: "transaction-1" }, error: null });
    },
  };
  const supabase = {
    from(table: "order_payment_provider_transactions") {
      calls.push(["from", table]);
      return builder;
    },
  };

  await resolvePaymentTransactionReference(
    supabase,
    "tenant-1",
    "provider_subscription_id",
    "sub_123",
  );

  assertEquals(calls, [
    ["from", "order_payment_provider_transactions"],
    ["select", "id, provider_customer_id"],
    ["eq", "tenant_id", "tenant-1"],
    ["eq", "provider_subscription_id", "sub_123"],
    ["limit", "1"],
    ["maybeSingle", ""],
  ]);
});
