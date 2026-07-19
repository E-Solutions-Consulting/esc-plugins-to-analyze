import { assertEquals } from "../_test/assert.ts";
import {
  handleCustomerUpdatedPaymentRetry,
  isCustomerUpdatedEvent,
} from "./payment-retry.ts";
import type { RtdhEventPayload } from "./validation.ts";

const TENANT_ID = "d258300b-6f4f-482f-8a88-22c482e6f1e9";
const ORDER_ID = "ba99952b-d206-43bc-96e6-f7253ce70a32";
const INVOICE_ID = "in_1TrcYCL7SOuP0wJqs50VOAca";
const FAILED_STATUS_ID = "status-failed-id";
const PENDING_STATUS_ID = "status-pending-id";

function buildPayload(overrides: Record<string, unknown> = {}): RtdhEventPayload {
  return {
    event_type: "customer.updated",
    internal_tenant_id: TENANT_ID,
    ids: {
      patient_platform_order_id: ORDER_ID,
      stripe_invoice_id: INVOICE_ID,
    },
    payment: {
      invoice_id: INVOICE_ID,
    },
    ...overrides,
  } as unknown as RtdhEventPayload;
}

function buildSupabaseMock(options: {
  orderStatusKey?: string;
  transactionFound?: boolean;
}) {
  const orderUpdates: Array<Record<string, unknown>> = [];
  const historyInserts: Array<Record<string, unknown>> = [];

  const supabase = {
    from(table: string) {
      if (table === "tenants") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({ data: { id: TENANT_ID }, error: null });
          },
        };
      }

      if (table === "orders") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: {
                id: ORDER_ID,
                status_id: FAILED_STATUS_ID,
                order_statuses: {
                  status_key: options.orderStatusKey ?? "payment_failed",
                },
              },
              error: null,
            });
          },
          update(values: Record<string, unknown>) {
            orderUpdates.push(values);
            return {
              eq() {
                return this;
              },
              then(
                resolve: (value: { error: null }) => unknown,
              ) {
                return Promise.resolve({ error: null }).then(resolve);
              },
            };
          },
        };
      }

      if (table === "order_payment_provider_transactions") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          limit() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: options.transactionFound === false
                ? null
                : { id: "txn-1" },
              error: null,
            });
          },
        };
      }

      if (table === "order_statuses") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: { id: PENDING_STATUS_ID },
              error: null,
            });
          },
        };
      }

      if (table === "order_status_history") {
        return {
          insert(values: Record<string, unknown>) {
            historyInserts.push(values);
            return Promise.resolve({ error: null });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  return { supabase, orderUpdates, historyInserts };
}

function stubFetch(
  responder: (url: string) => Response,
): { calls: string[]; restore: () => void } {
  const originalFetch = globalThis.fetch;
  const calls: string[] = [];
  globalThis.fetch = ((input: URL | RequestInfo) => {
    const url = String(input);
    calls.push(url);
    return Promise.resolve(responder(url));
  }) as typeof fetch;
  return { calls, restore: () => (globalThis.fetch = originalFetch) };
}

const stripeSecretKeyResolver = () => Promise.resolve("sk_test_123");

Deno.test("isCustomerUpdatedEvent detects the event type", () => {
  assertEquals(isCustomerUpdatedEvent(buildPayload()), true);
  assertEquals(
    isCustomerUpdatedEvent(
      buildPayload({ event_type: "order_status_updated" }),
    ),
    false,
  );
  assertEquals(isCustomerUpdatedEvent(undefined), false);
});

Deno.test("retries the failed invoice and resets the order to payment_pending on success", async () => {
  const { supabase, orderUpdates, historyInserts } = buildSupabaseMock({});
  const fetchStub = stubFetch(() =>
    new Response(JSON.stringify({ id: INVOICE_ID, status: "paid" }), {
      status: 200,
    })
  );

  try {
    const result = await handleCustomerUpdatedPaymentRetry({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      payload: buildPayload(),
      requestId: "req-1",
      // deno-lint-ignore no-explicit-any
      stripeSecretKeyResolver: stripeSecretKeyResolver as any,
    });

    assertEquals(result.action, "payment_retried");
    assertEquals(result.orderId, ORDER_ID);
    assertEquals(fetchStub.calls.length, 1);
    assertEquals(
      fetchStub.calls[0],
      `https://api.stripe.com/v1/invoices/${INVOICE_ID}/pay`,
    );
    assertEquals(orderUpdates.length, 1);
    assertEquals(orderUpdates[0].status_id, PENDING_STATUS_ID);
    assertEquals(orderUpdates[0].payment_failed_at, null);
    assertEquals(orderUpdates[0].payment_retry_count, 0);
    assertEquals(historyInserts.length, 1);
  } finally {
    fetchStub.restore();
  }
});

Deno.test("skips orders that are not payment_failed without calling Stripe", async () => {
  const { supabase, orderUpdates } = buildSupabaseMock({
    orderStatusKey: "order_approved",
  });
  const fetchStub = stubFetch(() => new Response("{}", { status: 200 }));

  try {
    const result = await handleCustomerUpdatedPaymentRetry({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      payload: buildPayload(),
      requestId: "req-2",
      // deno-lint-ignore no-explicit-any
      stripeSecretKeyResolver: stripeSecretKeyResolver as any,
    });

    assertEquals(result.action, "skipped");
    assertEquals(fetchStub.calls.length, 0);
    assertEquals(orderUpdates.length, 0);
  } finally {
    fetchStub.restore();
  }
});

Deno.test("skips when the invoice is not linked to the order", async () => {
  const { supabase, orderUpdates } = buildSupabaseMock({
    transactionFound: false,
  });
  const fetchStub = stubFetch(() => new Response("{}", { status: 200 }));

  try {
    const result = await handleCustomerUpdatedPaymentRetry({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      payload: buildPayload(),
      requestId: "req-3",
      // deno-lint-ignore no-explicit-any
      stripeSecretKeyResolver: stripeSecretKeyResolver as any,
    });

    assertEquals(result.action, "skipped");
    assertEquals(result.reason, "payment_reference_not_linked_to_order");
    assertEquals(fetchStub.calls.length, 0);
    assertEquals(orderUpdates.length, 0);
  } finally {
    fetchStub.restore();
  }
});

Deno.test("leaves the order in payment_failed when the retry charge fails", async () => {
  const { supabase, orderUpdates } = buildSupabaseMock({});
  const fetchStub = stubFetch(() =>
    new Response(
      JSON.stringify({
        error: { code: "card_declined", message: "Your card was declined." },
      }),
      { status: 402 },
    )
  );

  try {
    const result = await handleCustomerUpdatedPaymentRetry({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      payload: buildPayload(),
      requestId: "req-4",
      // deno-lint-ignore no-explicit-any
      stripeSecretKeyResolver: stripeSecretKeyResolver as any,
    });

    assertEquals(result.action, "retry_failed");
    assertEquals(result.reason, "card_declined");
    assertEquals(orderUpdates.length, 0);
  } finally {
    fetchStub.restore();
  }
});

Deno.test("resets a PaymentIntent-backed first order to payment_pending and triggers order-lifecycle", async () => {
  const { supabase, orderUpdates, historyInserts } = buildSupabaseMock({
    transactionFound: true,
  });
  const fetchStub = stubFetch(() => new Response("{}", { status: 200 }));
  const lifecycleCalls: string[] = [];

  try {
    const result = await handleCustomerUpdatedPaymentRetry({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      payload: buildPayload({
        ids: {
          patient_platform_order_id: ORDER_ID,
          stripe_payment_intent_id: "pi_failed_first_order",
        },
        payment: {
          payment_intent_id: "pi_failed_first_order",
        },
      }),
      requestId: "req-6",
      // deno-lint-ignore no-explicit-any
      stripeSecretKeyResolver: stripeSecretKeyResolver as any,
      lifecycleTrigger: (orderId: string) => {
        lifecycleCalls.push(orderId);
        return Promise.resolve(true);
      },
    });

    assertEquals(result.action, "payment_retry_scheduled");
    // No direct Stripe call — order-lifecycle owns the PaymentIntent retry.
    assertEquals(fetchStub.calls.length, 0);
    assertEquals(orderUpdates.length, 1);
    assertEquals(orderUpdates[0].status_id, PENDING_STATUS_ID);
    assertEquals(orderUpdates[0].payment_failed_at, null);
    assertEquals(historyInserts.length, 1);
    assertEquals(lifecycleCalls, [ORDER_ID]);
  } finally {
    fetchStub.restore();
  }
});

Deno.test("treats an already-paid invoice as success (duplicate delivery)", async () => {
  const { supabase, orderUpdates } = buildSupabaseMock({});
  const fetchStub = stubFetch(() =>
    new Response(
      JSON.stringify({
        error: {
          code: "invoice_already_paid",
          message: "Invoice is already paid",
        },
      }),
      { status: 400 },
    )
  );

  try {
    const result = await handleCustomerUpdatedPaymentRetry({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      payload: buildPayload(),
      requestId: "req-5",
      // deno-lint-ignore no-explicit-any
      stripeSecretKeyResolver: stripeSecretKeyResolver as any,
    });

    assertEquals(result.action, "payment_retried");
    assertEquals(orderUpdates.length, 1);
  } finally {
    fetchStub.restore();
  }
});
