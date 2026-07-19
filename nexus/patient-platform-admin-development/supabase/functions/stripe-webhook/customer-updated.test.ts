import { assertEquals } from "../_test/assert.ts";
import { handleCustomerUpdated } from "./customer-updated.ts";

// ---------------------------------------------------------------------------
// Minimal mock Supabase builder (same pattern as renewal.test.ts)
// ---------------------------------------------------------------------------

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
    const queryBuilder = {
      select(_columns: string) {
        calls.push(["select", table, _columns]);
        return queryBuilder;
      },
      eq(column: string, value: unknown) {
        calls.push(["eq", table, `${column}:${String(value)}`]);
        return queryBuilder;
      },
      filter(column: string, op: string, value: unknown) {
        calls.push(["filter", table, `${column}:${op}:${String(value)}`]);
        return queryBuilder;
      },
      limit(n: number) {
        calls.push(["limit", table, n]);
        return queryBuilder;
      },
      update(payload: unknown) {
        calls.push(["update", table, payload]);
        return queryBuilder;
      },
      insert(payload: unknown) {
        calls.push(["insert", table, payload]);
        return Promise.resolve(dequeue(`${table}.insert`));
      },
      maybeSingle() {
        calls.push(["maybeSingle", table]);
        return Promise.resolve(dequeue(`${table}.maybeSingle`));
      },
      then(
        resolve: (value: { data: unknown[]; error: unknown }) => unknown,
        _reject?: unknown,
      ) {
        calls.push(["then", table]);
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
    };
    calls.push(["from", table]);
    return queryBuilder;
  }

  return { supabase: { from }, calls };
}

// No-op lifecycle trigger for tests — we just record invocations
function makeLifecycleSpy(): {
  trigger: (orderId: string, tenantId: string, requestId: string) => Promise<void>;
  calls: string[];
} {
  const calls: string[] = [];
  return {
    trigger: (orderId) => {
      calls.push(orderId);
      return Promise.resolve();
    },
    calls,
  };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

Deno.test(
  "handleCustomerUpdated skips when default payment method has not changed",
  async () => {
    const { supabase, calls } = createMockSupabase({});
    const spy = makeLifecycleSpy();

    await handleCustomerUpdated(
      supabase as never,
      {
        id: "cus_test_123",
        previous_attributes: {}, // no invoice_settings or default_source change
      },
      "tenant-1",
      "req-skip-pm",
      spy.trigger,
    );

    assertEquals(spy.calls.length, 0);
    assertEquals(
      calls.filter((c) => c[0] === "from").length,
      0,
    );
  },
);

Deno.test(
  "handleCustomerUpdated skips when no patient matches the Stripe customer",
  async () => {
    const { supabase } = createMockSupabase({
      "patients.array": [{ data: [], error: null }],
    });
    const spy = makeLifecycleSpy();

    await handleCustomerUpdated(
      supabase as never,
      {
        id: "cus_no_patient",
        previous_attributes: { invoice_settings: { default_payment_method: "pm_old" } },
      },
      "tenant-1",
      "req-no-patient",
      spy.trigger,
    );

    assertEquals(spy.calls.length, 0);
  },
);

Deno.test(
  "handleCustomerUpdated moves payment_failed orders to payment_pending and triggers lifecycle",
  async () => {
    const { supabase, calls } = createMockSupabase({
      "patients.array": [{ data: [{ id: "patient-1" }], error: null }],
      "order_statuses.maybeSingle": [{ data: { id: "status-pp-id" }, error: null }],
      "orders.array": [
        // select: failed orders
        { data: [{ id: "order-failed-1" }, { id: "order-failed-2" }], error: null },
        // update order-failed-1
        { data: null, error: null },
        // update order-failed-2
        { data: null, error: null },
      ],
      "order_status_history.insert": [
        { data: null, error: null },
        { data: null, error: null },
      ],
    });
    const spy = makeLifecycleSpy();

    await handleCustomerUpdated(
      supabase as never,
      {
        id: "cus_with_failures",
        previous_attributes: { invoice_settings: { default_payment_method: "pm_new" } },
      },
      "tenant-1",
      "req-retry",
      spy.trigger,
    );

    // Both failed orders must be moved to payment_pending
    const updateCalls = calls.filter(
      (c) => c[0] === "update" && c[1] === "orders",
    );
    assertEquals(updateCalls.length, 2);

    const firstUpdate = updateCalls[0][2] as Record<string, unknown>;
    assertEquals(firstUpdate.status_id, "status-pp-id");
    assertEquals(firstUpdate.paid_at, null);
    assertEquals(firstUpdate.payment_failed_at, null);
    assertEquals(firstUpdate.payment_retry_count, 0);

    // History note must be inserted for each order
    const historyCalls = calls.filter(
      (c) => c[0] === "insert" && c[1] === "order_status_history",
    );
    assertEquals(historyCalls.length, 2);

    const firstHistory = historyCalls[0][2] as Record<string, unknown>;
    assertEquals(firstHistory.status_id, "status-pp-id");
    assertEquals(firstHistory.order_id, "order-failed-1");

    // Lifecycle triggered for each order
    assertEquals(spy.calls, ["order-failed-1", "order-failed-2"]);
  },
);

Deno.test(
  "handleCustomerUpdated skips update when multiple patients match (ambiguous)",
  async () => {
    const { supabase } = createMockSupabase({
      "patients.array": [{
        data: [{ id: "patient-a" }, { id: "patient-b" }],
        error: null,
      }],
    });
    const spy = makeLifecycleSpy();

    await handleCustomerUpdated(
      supabase as never,
      {
        id: "cus_ambiguous",
        previous_attributes: { invoice_settings: {} },
      },
      "tenant-1",
      "req-ambiguous",
      spy.trigger,
    );

    assertEquals(spy.calls.length, 0);
  },
);

Deno.test(
  "handleCustomerUpdated continues to next order when one update fails",
  async () => {
    const { supabase } = createMockSupabase({
      "patients.array": [{ data: [{ id: "patient-1" }], error: null }],
      "order_statuses.maybeSingle": [{ data: { id: "status-pp-id" }, error: null }],
      "orders.array": [
        { data: [{ id: "order-fail-update" }, { id: "order-ok" }], error: null },
        // first update fails
        { data: null, error: { message: "DB error" } },
        // second update succeeds
        { data: null, error: null },
      ],
      "order_status_history.insert": [{ data: null, error: null }],
    });
    const spy = makeLifecycleSpy();

    await handleCustomerUpdated(
      supabase as never,
      {
        id: "cus_partial",
        previous_attributes: { invoice_settings: {} },
      },
      "tenant-1",
      "req-partial",
      spy.trigger,
    );

    // Only the successfully updated order triggers lifecycle
    assertEquals(spy.calls, ["order-ok"]);
  },
);
