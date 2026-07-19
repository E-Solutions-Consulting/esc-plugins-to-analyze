import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  handleRenewalOrderCreate,
  isRenewalOrderCreateIntent,
} from "./renewal.ts";

type MockResponse = { data?: unknown; error?: { message: string } | null };
type Call = [string, string, unknown?];

function createMockSupabase(responses: Record<string, MockResponse[]>) {
  const calls: Call[] = [];

  function dequeue(key: string): MockResponse {
    const queue = responses[key] || [];
    const next = queue.shift();
    if (!next) return { data: null, error: null };
    return next;
  }

  function from(table: string) {
    const state: { filters: Record<string, unknown>; payload?: unknown } = {
      filters: {},
    };

    const queryBuilder = {
      select(columns: string) {
        calls.push(["select", table, columns]);
        return queryBuilder;
      },
      eq(column: string, value: unknown) {
        state.filters[column] = value;
        calls.push(["eq", table, `${column}:${String(value)}`]);
        return queryBuilder;
      },
      order(column: string, opts?: unknown) {
        calls.push(["order", table, `${column}:${JSON.stringify(opts ?? {})}`]);
        return queryBuilder;
      },
      limit(count: number) {
        calls.push(["limit", table, count]);
        return queryBuilder;
      },
      then(
        resolve: (value: { data: unknown[]; error: unknown }) => unknown,
        _reject?: (reason: unknown) => unknown,
      ) {
        calls.push(["then", table, { ...state.filters }]);
        const response = dequeue(`${table}.array`);
        const rawData = response.data;
        const arrayData = Array.isArray(rawData)
          ? rawData
          : rawData != null
          ? [rawData]
          : [];
        resolve({ data: arrayData, error: response.error ?? null });
        return undefined;
      },
      maybeSingle() {
        calls.push(["maybeSingle", table, { ...state.filters }]);
        return Promise.resolve(dequeue(`${table}.maybeSingle`));
      },
      single() {
        calls.push(["single", table, { ...state.filters }]);
        return Promise.resolve(dequeue(`${table}.single`));
      },
      insert(payload: unknown) {
        state.payload = payload;
        calls.push(["insert", table, payload]);
        if (table === "orders") {
          return {
            select(columns: string) {
              calls.push(["select", table, columns]);
              return {
                single() {
                  calls.push(["single", table, { ...state.filters }]);
                  return Promise.resolve(dequeue("orders.insert_single"));
                },
              };
            },
          };
        }
        return Promise.resolve(dequeue(`${table}.insert`));
      },
      update(payload: unknown) {
        state.payload = payload;
        calls.push(["update", table, payload]);
        return queryBuilder;
      },
      is(column: string, value: unknown) {
        calls.push(["is", table, `${column}:${String(value)}`]);
        return Promise.resolve(dequeue(`${table}.update`));
      },
      delete() {
        calls.push(["delete", table]);
        return {
          eq(column: string, value: unknown) {
            state.filters[column] = value;
            calls.push(["eq", table, `${column}:${String(value)}`]);
            return Promise.resolve(dequeue(`${table}.delete`));
          },
        };
      },
    };

    calls.push(["from", table]);
    return queryBuilder;
  }

  return { supabase: { from }, calls };
}

Deno.test("isRenewalOrderCreateIntent returns true for matching payload field", () => {
  const req = new Request("https://example.com");
  assertEquals(
    isRenewalOrderCreateIntent(req, {
      rtdh_intent: "renewal_order_create",
    } as never),
    true,
  );
});

Deno.test("isRenewalOrderCreateIntent is case-insensitive and trims payload field", () => {
  const req = new Request("https://example.com");
  assertEquals(
    isRenewalOrderCreateIntent(req, {
      rtdh_intent: "  ReNewal_Order_Create ",
    } as never),
    true,
  );
});

Deno.test("isRenewalOrderCreateIntent still supports the legacy header", () => {
  const req = new Request("https://example.com", {
    headers: { "x-rtdh-intent": "renewal_order_create" },
  });
  assertEquals(isRenewalOrderCreateIntent(req), true);
});

Deno.test("isRenewalOrderCreateIntent returns false when missing or mismatched", () => {
  const missing = new Request("https://example.com");
  const mismatched = new Request("https://example.com");
  assertEquals(isRenewalOrderCreateIntent(missing), false);
  assertEquals(
    isRenewalOrderCreateIntent(mismatched, {
      rtdh_intent: "other_intent",
    } as never),
    false,
  );
});

Deno.test("handleRenewalOrderCreate returns existing order by invoice idempotency", async () => {
  const { supabase, calls } = createMockSupabase({
    "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
    "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
    "order_payment_provider_transactions.maybeSingle": [{
      data: { order_id: "order-existing" },
      error: null,
    }],
  });

  const result = await handleRenewalOrderCreate({
    supabase: supabase as never,
    payload: {
      internal_tenant_id: "tenant-1",
      payment: { provider: "stripe", invoice_id: "in_123" },
    } as never,
    requestId: "req-1",
  });

  assertEquals(result, {
    ok: true,
    tenantId: "tenant-1",
    orderId: "order-existing",
    created: false,
    strategy: "idempotency_invoice",
  });
  assertEquals(
    calls.some((call) => call[0] === "insert" && call[1] === "orders"),
    false,
  );
});

Deno.test("handleRenewalOrderCreate resolves tenant by slug fallback", async () => {
  const { supabase, calls } = createMockSupabase({
    "tenants.maybeSingle": [
      { data: null, error: null },
      { data: { id: "tenant-from-slug" }, error: null },
    ],
  });

  const result = await handleRenewalOrderCreate({
    supabase: supabase as never,
    payload: {
      internal_tenant_id: "clinic-slug",
      payment: { provider: "stripe" },
    } as never,
    requestId: "req-2",
  });

  assertEquals(result, {
    ok: false,
    status: 422,
    code: "validation_error",
    message:
      "Renewal create requires at least one payment identifier (subscription_id, invoice_id, checkout_session_id, or payment_intent_id)",
  });

  const tenantEqCalls = calls.filter(
    (call) => call[0] === "eq" && call[1] === "tenants",
  );
  assertEquals(tenantEqCalls[0], ["eq", "tenants", "id:clinic-slug"]);
  assertEquals(tenantEqCalls[1], ["eq", "tenants", "slug:clinic-slug"]);
});

Deno.test("handleRenewalOrderCreate creates renewal using subscription strategy", async () => {
  const { supabase, calls } = createMockSupabase({
    "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
    "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
    "subscription_payment_provider_links.array": [{
      data: [{ subscription_id: "sub-internal-1" }],
      error: null,
    }],
    "orders.maybeSingle": [
      {
        data: {
          id: "order-source-1",
          tenant_id: "tenant-1",
          patient_id: "patient-1",
          product_id: "product-1",
          subscription_id: "sub-internal-1",
          subtotal_cents: 1000,
          tax_cents: 100,
          shipping_cents: 0,
          total_cents: 1100,
          discount_cents: 0,
          coupon_code: null,
          coupon_name: null,
          currency: "usd",
          shipping_address_line1: "123 Renewal Way",
          shipping_address_line2: "Apt 4",
          shipping_city: "Austin",
          shipping_state: "TX",
          shipping_postal_code: "78701",
          shipping_country: "US",
          provider_platform_integration_key: null,
        },
        error: null,
      },
    ],
    "order_statuses.maybeSingle": [{ data: { id: "status-1" }, error: null }],
    "orders.insert_single": [{ data: { id: "order-new-1" }, error: null }],
    "order_status_history.insert": [{ data: null, error: null }],
    "order_payment_provider_transactions.insert": [{ data: null, error: null }],
  });

  const result = await handleRenewalOrderCreate({
    supabase: supabase as never,
    payload: {
      internal_tenant_id: "tenant-1",
      payment: {
        provider: "stripe",
        subscription_id: "sub_provider_1",
        invoice_id: "in_456",
      },
    } as never,
    requestId: "req-3",
  });

  assertEquals(result, {
    ok: true,
    tenantId: "tenant-1",
    orderId: "order-new-1",
    created: true,
    strategy: "subscription",
  });

  const insertedOrder = calls.find((call) =>
    call[0] === "insert" && call[1] === "orders"
  )?.[2] as Record<string, unknown> | undefined;
  assertEquals(insertedOrder?.shipping_address_line1, "123 Renewal Way");
  assertEquals(insertedOrder?.shipping_address_line2, "Apt 4");
  assertEquals(insertedOrder?.shipping_city, "Austin");
  assertEquals(insertedOrder?.shipping_state, "TX");
  assertEquals(insertedOrder?.shipping_postal_code, "78701");
  assertEquals(insertedOrder?.shipping_country, "US");
});

Deno.test("handleRenewalOrderCreate falls back to checkout session subscription link", async () => {
  const { supabase } = createMockSupabase({
    "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
    "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
    "subscription_payment_provider_links.array": [
      { data: [], error: null },
      { data: [{ subscription_id: "sub-internal-1" }], error: null },
    ],
    "orders.maybeSingle": [
      {
        data: {
          id: "order-source-1",
          tenant_id: "tenant-1",
          patient_id: "patient-1",
          product_id: "product-1",
          subscription_id: "sub-internal-1",
          subtotal_cents: 1000,
          tax_cents: 100,
          shipping_cents: 0,
          total_cents: 1100,
          discount_cents: 0,
          coupon_code: null,
          coupon_name: null,
          currency: "usd",
          shipping_address_line1: null,
          shipping_address_line2: null,
          shipping_city: null,
          shipping_state: null,
          shipping_postal_code: null,
          shipping_country: null,
          provider_platform_integration_key: null,
        },
        error: null,
      },
    ],
    "order_statuses.maybeSingle": [{ data: { id: "status-1" }, error: null }],
    "orders.insert_single": [{ data: { id: "order-new-1" }, error: null }],
    "order_status_history.insert": [{ data: null, error: null }],
    "order_payment_provider_transactions.insert": [{ data: null, error: null }],
  });

  const result = await handleRenewalOrderCreate({
    supabase: supabase as never,
    payload: {
      internal_tenant_id: "tenant-1",
      payment: {
        provider: "stripe",
        subscription_id: "sub_provider_missing_link",
        checkout_session_id: "cs_checkout_linked",
      },
    } as never,
    requestId: "req-checkout-fallback",
  });

  assertEquals(result, {
    ok: true,
    tenantId: "tenant-1",
    orderId: "order-new-1",
    created: true,
    strategy: "subscription_checkout",
  });
});

Deno.test("handleRenewalOrderCreate rolls back on transaction insert error", async () => {
  const { supabase, calls } = createMockSupabase({
    "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
    "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
    "subscription_payment_provider_links.array": [{
      data: [{ subscription_id: "sub-internal-1" }],
      error: null,
    }],
    "orders.maybeSingle": [
      {
        data: {
          id: "order-source-1",
          tenant_id: "tenant-1",
          patient_id: "patient-1",
          product_id: "product-1",
          subscription_id: "sub-internal-1",
          subtotal_cents: 1000,
          tax_cents: 100,
          shipping_cents: 0,
          total_cents: 1100,
          discount_cents: 0,
          coupon_code: null,
          coupon_name: null,
          currency: "usd",
          shipping_address_line1: null,
          shipping_address_line2: null,
          shipping_city: null,
          shipping_state: null,
          shipping_postal_code: null,
          shipping_country: null,
          provider_platform_integration_key: null,
        },
        error: null,
      },
    ],
    "order_statuses.maybeSingle": [{ data: { id: "status-1" }, error: null }],
    "orders.insert_single": [{ data: { id: "order-new-1" }, error: null }],
    "order_status_history.insert": [{ data: null, error: null }],
    "order_payment_provider_transactions.insert": [{
      data: null,
      error: { message: "duplicate key" },
    }],
    "order_status_history.delete": [{ data: null, error: null }],
    "orders.delete": [{ data: null, error: null }],
  });

  const result = await handleRenewalOrderCreate({
    supabase: supabase as never,
    payload: {
      internal_tenant_id: "tenant-1",
      payment: {
        provider: "stripe",
        subscription_id: "sub_provider_1",
        payment_intent_id: "pi_1",
      },
    } as never,
    requestId: "req-4",
  });

  assertEquals(result.ok, false);
  if (!result.ok) {
    assertEquals(result.status, 500);
    assertEquals(result.code, "server_error");
    assertMatch(result.message, /Failed to persist renewal payment transaction/);
  }

  assertEquals(
    calls.some((call) => call[0] === "delete" && call[1] === "order_status_history"),
    true,
  );
  assertEquals(
    calls.some((call) => call[0] === "delete" && call[1] === "orders"),
    true,
  );
});

const SOURCE_ORDER_BASE = {
  tenant_id: "tenant-1",
  patient_id: "patient-1",
  product_id: "product-1",
  subtotal_cents: 1000,
  tax_cents: 100,
  shipping_cents: 0,
  total_cents: 1100,
  discount_cents: 0,
  coupon_code: null,
  coupon_name: null,
  currency: "usd",
  shipping_first_name: "Jane",
  shipping_last_name: "Doe",
  shipping_address_line1: "123 Main St",
  shipping_address_line2: null,
  shipping_city: "Austin",
  shipping_state: "TX",
  shipping_postal_code: "78701",
  shipping_country: "US",
  billing_first_name: "Jane",
  billing_last_name: "Doe",
  billing_address_line1: "123 Main St",
  billing_address_line2: null,
  billing_city: "Austin",
  billing_state: "TX",
  billing_postal_code: "78701",
  billing_country: "US",
  provider_platform_integration_key: null,
};

Deno.test(
  "handleRenewalOrderCreate resolves via transaction fallback using tx subscription_id when order row has null",
  async () => {
    const { supabase } = createMockSupabase({
      "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
      "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
      // subscription_payment_provider_links misses for subscription_id path
      "subscription_payment_provider_links.array": [
        { data: [], error: null },
      ],
      // transaction fallback: invoice/checkout/payment_intent are null in payload so skipped;
      // provider_subscription_id lookup hits with a subscription_id on the tx row
      "order_payment_provider_transactions.maybeSingle": [{
        data: { order_id: "order-source-1", subscription_id: "sub-internal-from-tx" },
        error: null,
      }],
      "orders.maybeSingle": [{
        data: { id: "order-source-1", ...SOURCE_ORDER_BASE, subscription_id: null },
        error: null,
      }],
      "order_statuses.maybeSingle": [{ data: { id: "status-1" }, error: null }],
      "orders.insert_single": [{ data: { id: "order-new-1" }, error: null }],
      "order_status_history.insert": [{ data: null, error: null }],
      "order_payment_provider_transactions.insert": [{ data: null, error: null }],
    });

    const result = await handleRenewalOrderCreate({
      supabase: supabase as never,
      payload: {
        internal_tenant_id: "tenant-1",
        payment: { provider: "stripe", subscription_id: "sub_provider_1" },
      } as never,
      requestId: "req-tx-sub-fallback",
    });

    assertEquals(result, {
      ok: true,
      tenantId: "tenant-1",
      orderId: "order-new-1",
      created: true,
      strategy: "subscription_transaction",
    });
  },
);

Deno.test(
  "handleRenewalOrderCreate returns reference_not_found with diagnostic flags when order found but both subscription_ids are null",
  async () => {
    const { supabase } = createMockSupabase({
      "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
      "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
      "subscription_payment_provider_links.array": [
        { data: [], error: null },
      ],
      "order_payment_provider_transactions.maybeSingle": [{
        data: { order_id: "order-source-1", subscription_id: null },
        error: null,
      }],
      "orders.maybeSingle": [{
        data: { id: "order-source-1", ...SOURCE_ORDER_BASE, subscription_id: null },
        error: null,
      }],
    });

    const result = await handleRenewalOrderCreate({
      supabase: supabase as never,
      payload: {
        internal_tenant_id: "tenant-1",
        payment: { provider: "stripe", subscription_id: "sub_provider_1" },
      } as never,
      requestId: "req-both-null",
    });

    assertEquals(result.ok, false);
    if (!result.ok) {
      assertEquals(result.status, 422);
      assertEquals(result.code, "reference_not_found");
      assertEquals((result.details as Record<string, unknown>)?.sourceOrderFound, true);
      assertEquals(
        (result.details as Record<string, unknown>)?.sourceOrderMissingSubscriptionId,
        true,
      );
    }
  },
);

Deno.test(
  "handleRenewalOrderCreate uses newest row and resolves when checkout session link has multiple matches",
  async () => {
    const { supabase } = createMockSupabase({
      "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
      "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
      // subscription_id path misses; checkout session path returns two rows (ambiguous)
      "subscription_payment_provider_links.array": [
        { data: [], error: null },
        {
          data: [
            { subscription_id: "sub-newest" },
            { subscription_id: "sub-older" },
          ],
          error: null,
        },
      ],
      "orders.maybeSingle": [{
        data: { id: "order-source-1", ...SOURCE_ORDER_BASE, subscription_id: "sub-newest" },
        error: null,
      }],
      "order_statuses.maybeSingle": [{ data: { id: "status-1" }, error: null }],
      "orders.insert_single": [{ data: { id: "order-new-1" }, error: null }],
      "order_status_history.insert": [{ data: null, error: null }],
      "order_payment_provider_transactions.insert": [{ data: null, error: null }],
    });

    const result = await handleRenewalOrderCreate({
      supabase: supabase as never,
      payload: {
        internal_tenant_id: "tenant-1",
        payment: {
          provider: "stripe",
          subscription_id: "sub_missing_link",
          checkout_session_id: "cs_ambiguous",
        },
      } as never,
      requestId: "req-ambiguous-checkout",
    });

    assertEquals(result, {
      ok: true,
      tenantId: "tenant-1",
      orderId: "order-new-1",
      created: true,
      strategy: "subscription_checkout",
    });
  },
);

Deno.test(
  "handleRenewalOrderCreate copies billing and shipping names from source order to renewal order",
  async () => {
    const { supabase, calls } = createMockSupabase({
      "tenants.maybeSingle": [{ data: { id: "tenant-1" }, error: null }],
      "payment_providers.maybeSingle": [{ data: { id: "provider-1" }, error: null }],
      "subscription_payment_provider_links.array": [
        { data: [{ subscription_id: "sub-internal-1" }], error: null },
      ],
      "orders.maybeSingle": [{
        data: { id: "order-source-1", ...SOURCE_ORDER_BASE, subscription_id: "sub-internal-1" },
        error: null,
      }],
      "order_statuses.maybeSingle": [{ data: { id: "status-1" }, error: null }],
      "orders.insert_single": [{ data: { id: "order-new-names" }, error: null }],
      "order_status_history.insert": [{ data: null, error: null }],
      "order_payment_provider_transactions.insert": [{ data: null, error: null }],
    });

    await handleRenewalOrderCreate({
      supabase: supabase as never,
      payload: {
        internal_tenant_id: "tenant-1",
        payment: { provider: "stripe", subscription_id: "sub_provider_1" },
      } as never,
      requestId: "req-names-copy",
    });

    const insertCall = calls.find((c) => c[0] === "insert" && c[1] === "orders");
    const inserted = (insertCall?.[2] as Record<string, unknown>) ?? {};

    assertEquals(inserted.shipping_first_name, SOURCE_ORDER_BASE.shipping_first_name);
    assertEquals(inserted.shipping_last_name, SOURCE_ORDER_BASE.shipping_last_name);
    assertEquals(inserted.billing_first_name, SOURCE_ORDER_BASE.billing_first_name);
    assertEquals(inserted.billing_last_name, SOURCE_ORDER_BASE.billing_last_name);
    assertEquals(inserted.billing_address_line1, SOURCE_ORDER_BASE.billing_address_line1);
    assertEquals(inserted.billing_city, SOURCE_ORDER_BASE.billing_city);
    assertEquals(inserted.billing_state, SOURCE_ORDER_BASE.billing_state);
    assertEquals(inserted.billing_postal_code, SOURCE_ORDER_BASE.billing_postal_code);
    assertEquals(inserted.billing_country, SOURCE_ORDER_BASE.billing_country);
  },
);
