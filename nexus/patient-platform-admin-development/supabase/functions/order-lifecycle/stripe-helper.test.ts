import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  syncLifecycleDatesForPaymentCollectedOrder,
  tryPayStripeInvoiceDirectly,
} from "./stripe-helper.ts";

// ---------------------------------------------------------------------------
// Helpers to stub globalThis.fetch for isolated tests
// ---------------------------------------------------------------------------

function stubFetch(
  handler: (input: RequestInfo | URL) => Promise<Response>,
): () => void {
  const original = globalThis.fetch;
  globalThis.fetch = handler as typeof globalThis.fetch;
  return () => {
    globalThis.fetch = original;
  };
}

function jsonResponse(body: unknown, status = 200): Promise<Response> {
  return Promise.resolve(
    new Response(JSON.stringify(body), {
      status,
      headers: { "content-type": "application/json" },
    }),
  );
}

function textResponse(body: string, status: number): Promise<Response> {
  return Promise.resolve(new Response(body, { status }));
}

// ---------------------------------------------------------------------------
// tryPayStripeInvoiceDirectly
// ---------------------------------------------------------------------------

Deno.test("tryPayStripeInvoiceDirectly returns paid: true with paymentIntentId and chargeId on success", async () => {
  const restore = stubFetch(() =>
    jsonResponse({
      id: "in_123",
      payment_intent: "pi_abc",
      charge: "ch_xyz",
    })
  );
  try {
    const result = await tryPayStripeInvoiceDirectly(
      "in_123",
      "sk_test_secret",
      "req-1",
    );
    assertEquals(result.paid, true);
    assertEquals(result.alreadyPaid, false);
    assertEquals(result.paymentIntentId, "pi_abc");
    assertEquals(result.chargeId, "ch_xyz");
    assertMatch(result.message, /paid successfully/);
  } finally {
    restore();
  }
});

Deno.test("tryPayStripeInvoiceDirectly returns paid: true with nested paymentIntentId object", async () => {
  const restore = stubFetch(() =>
    jsonResponse({
      id: "in_nested",
      payment_intent: { id: "pi_nested_123" },
      charge: null,
    })
  );
  try {
    const result = await tryPayStripeInvoiceDirectly(
      "in_nested",
      "sk_test_secret",
      "req-nested",
    );
    assertEquals(result.paid, true);
    assertEquals(result.paymentIntentId, "pi_nested_123");
    assertEquals(result.chargeId, null);
  } finally {
    restore();
  }
});

Deno.test("tryPayStripeInvoiceDirectly returns alreadyPaid: true on invoice_already_paid error code", async () => {
  const restore = stubFetch(() =>
    jsonResponse(
      { error: { code: "invoice_already_paid", message: "Invoice is already paid." } },
      402,
    )
  );
  try {
    const result = await tryPayStripeInvoiceDirectly(
      "in_already_paid",
      "sk_test_secret",
      "req-2",
    );
    assertEquals(result.paid, false);
    assertEquals(result.alreadyPaid, true);
    assertEquals(result.paymentIntentId, null);
    assertEquals(result.chargeId, null);
    assertMatch(result.message, /already paid/);
  } finally {
    restore();
  }
});

Deno.test("tryPayStripeInvoiceDirectly returns paid: false on other Stripe error", async () => {
  const restore = stubFetch(() =>
    jsonResponse(
      { error: { code: "card_declined", message: "Your card was declined." } },
      402,
    )
  );
  try {
    const result = await tryPayStripeInvoiceDirectly(
      "in_declined",
      "sk_test_secret",
      "req-3",
    );
    assertEquals(result.paid, false);
    assertEquals(result.alreadyPaid, false);
    assertEquals(result.paymentIntentId, null);
    assertEquals(result.chargeId, null);
    assertMatch(result.message, /Invoice pay failed/);
  } finally {
    restore();
  }
});

Deno.test("tryPayStripeInvoiceDirectly calls correct Stripe URL", async () => {
  let capturedUrl: string | undefined;
  const restore = stubFetch((input) => {
    capturedUrl = typeof input === "string" ? input : (input as Request).url;
    return jsonResponse({ id: "in_url_test", payment_intent: null, charge: null });
  });
  try {
    await tryPayStripeInvoiceDirectly("in_url_test", "sk_test_key", "req-url");
    assertEquals(
      capturedUrl,
      "https://api.stripe.com/v1/invoices/in_url_test/pay",
    );
  } finally {
    restore();
  }
});

// ---------------------------------------------------------------------------
// syncLifecycleDatesForPaymentCollectedOrder — missing subscription link
// ---------------------------------------------------------------------------

type MockResponse = { data?: unknown; error?: { message: string } | null };

// Minimal table-keyed Supabase mock (mirrors rtdh-webhook/renewal.test.ts).
// Terminal `.maybeSingle()`/`.single()` dequeue `${table}.maybeSingle|single`;
// awaiting a builder that ends on a filter dequeues `${table}.array`.
function createMockSupabase(responses: Record<string, MockResponse[]>) {
  function dequeue(key: string): MockResponse {
    const next = (responses[key] || []).shift();
    return next ?? { data: null, error: null };
  }

  function from(table: string) {
    const builder = {
      select: () => builder,
      eq: () => builder,
      order: () => builder,
      limit: () => builder,
      not: () => builder,
      update: () => builder,
      then(
        resolve: (value: { data: unknown; error: unknown }) => unknown,
      ) {
        const response = dequeue(`${table}.array`);
        const raw = response.data;
        const data = Array.isArray(raw) ? raw : raw != null ? [raw] : [];
        resolve({ data, error: response.error ?? null });
        return undefined;
      },
      maybeSingle: () => Promise.resolve(dequeue(`${table}.maybeSingle`)),
      single: () => Promise.resolve(dequeue(`${table}.single`)),
    };
    return builder;
  }

  return { from };
}

Deno.test("syncLifecycleDatesForPaymentCollectedOrder returns code missing_subscription_link when the plan has no Stripe link", async () => {
  const orderId = "order-1";
  const supabase = createMockSupabase({
    // product renewal cycle
    "products.maybeSingle": [{
      data: {
        id: "product-1",
        subscription_interval: "month",
        subscription_interval_count: 1,
        subscription_renewal_lead_days: 0,
      },
      error: null,
    }],
    // payment_providers: 1st lookup inside resolveInvoiceLinePeriodEnd returns
    // null (short-circuits to interval calc, avoids the Stripe fetch); 2nd is
    // the main stripe-provider lookup.
    "payment_providers.maybeSingle": [
      { data: null, error: null },
      { data: { id: "stripe-provider" }, error: null },
    ],
    // most-recent-order guard: this order IS the most recent for the plan
    "orders.maybeSingle": [{ data: { id: orderId }, error: null }],
    // linked plan: dates null so the "already synced" guard is false
    "subscriptions.maybeSingle": [{
      data: { id: "sub-local-1", current_period_end_at: null, expires_at: null },
      error: null,
    }],
    // validateStripePaymentProviderForOrder: order is on Stripe
    "order_payment_provider_transactions.array": [{
      data: [{ payment_provider_id: "stripe-provider" }],
      error: null,
    }],
    // tenant Stripe config with a secret key
    "tenant_payment_providers.maybeSingle": [{
      data: { settings: { secret_key: "sk_test_secret" } },
      error: null,
    }],
    // the crux: no subscription_payment_provider_links row for the plan
    "subscription_payment_provider_links.maybeSingle": [{
      data: null,
      error: null,
    }],
  });

  // No Stripe network should be reached before the missing-link return.
  const restore = stubFetch(() => {
    throw new Error("unexpected Stripe fetch");
  });
  try {
    const result = await syncLifecycleDatesForPaymentCollectedOrder({
      supabase: supabase as never,
      order: {
        id: orderId,
        tenant_id: "tenant-1",
        subscription_id: "sub-local-1",
        product_id: "product-1",
        renewal_at: null,
        paid_at: "2026-07-14T23:35:55.848Z",
        created_at: "2026-07-14T23:30:58.538Z",
      },
      requestId: "req-missing-link",
    });

    assertEquals(result.synced, false);
    assertEquals(result.code, "missing_subscription_link");
    assertMatch(result.message, /missing Stripe subscription reference/);
  } finally {
    restore();
  }
});
