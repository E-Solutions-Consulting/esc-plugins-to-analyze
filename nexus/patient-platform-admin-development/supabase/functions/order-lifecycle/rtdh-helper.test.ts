import { assertEquals } from "../_test/assert.ts";
import {
  notifyRtdhOrderCancelled,
  notifyRtdhOrderStatusUpdated,
  triggerRtdhCreateOrder,
  triggerRtdhProviderPlatformNewOrder,
} from "./rtdh-helper.ts";

Deno.test("notifyRtdhOrderStatusUpdated prefers subscriptionIdOverride over the transaction row", async () => {
  const originalFetch = globalThis.fetch;
  const fetchCalls: Array<{ url: string; body: Record<string, unknown> }> = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

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
            return Promise.resolve({
              data: { slug: "tenant-slug" },
              error: null,
            });
          },
        };
      }

      if (table === "platform_settings") {
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
                value: {
                  api_url: "https://rtdh.example.com",
                  patient_platform_webhook_secret: "secret-1",
                },
              },
              error: null,
            });
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
          order() {
            return this;
          },
          limit() {
            return this;
          },
          maybeSingle() {
            // Simulates the race: the subscription was just created by the
            // caller but the transaction row update has not landed yet.
            return Promise.resolve({
              data: {
                provider_checkout_session_id: null,
                provider_payment_intent_id: "pi_123",
                provider_subscription_id: null,
              },
              error: null,
            });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    await notifyRtdhOrderStatusUpdated({
      // deno-lint-ignore no-explicit-any
      supabase: supabase as any,
      requestId: "request-1",
      tenantId: "tenant-1",
      orderId: "order-1",
      statusId: null,
      statusKey: "payment_pending",
      source: "order-lifecycle:subscription-created",
      subscriptionIdOverride: "sub_created_now_123",
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assertEquals(fetchCalls.length, 1);
  assertEquals(
    fetchCalls[0].url,
    "https://rtdh.example.com/patient-platform-webhook-receiver/order_updated",
  );
  const payload = fetchCalls[0].body.payload as Record<string, unknown>;
  assertEquals(payload.subscription_id, "sub_created_now_123");
  assertEquals(payload.payment_intent_id, "pi_123");
  assertEquals(payload.update_source, "order-lifecycle:subscription-created");
});

Deno.test("notifyRtdhOrderCancelled sends after-provider cancellation to RTDH", async () => {
  const originalFetch = globalThis.fetch;
  const fetchCalls: Array<{ url: string; body: Record<string, unknown> }> = [];
  const fromCalls: string[] = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

  const supabase = {
    from(table: string) {
      fromCalls.push(table);

      if (table === "order_status_history") {
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
            return Promise.resolve({ data: null, error: null });
          },
          insert() {
            return Promise.resolve({ error: null });
          },
        };
      }

      if (table === "tenants") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: { slug: "tenant-slug" },
              error: null,
            });
          },
        };
      }

      if (table === "platform_settings") {
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
                value: {
                  api_url: "https://rtdh.example.com",
                  patient_platform_webhook_secret: "secret-1",
                },
              },
              error: null,
            });
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
          order() {
            return this;
          },
          limit() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({ data: null, error: null });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    await notifyRtdhOrderCancelled({
      supabase,
      requestId: "request-1",
      tenantId: "tenant-1",
      orderId: "order-1",
      statusId: "status-cancelled",
      previousStatusKey: "order_cancellation_processing",
      cancellationStage: "after_provider_creation",
      cancellationReason: "patient_requested",
      source: "order-lifecycle:order-cancellation-processing",
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assertEquals(fromCalls.includes("order_provider_platform_links"), false);
  assertEquals(fetchCalls.length, 1);
  assertEquals(
    fetchCalls[0].url,
    "https://rtdh.example.com/patient-platform-webhook-receiver/order_updated",
  );

  // Envelope must carry BOTH the tenant slug and the internal tenant id so RTDH
  // resolves the real tenant (and never keys the master object as master::unknown::).
  assertEquals(fetchCalls[0].body.tenant, "tenant-slug");
  assertEquals(fetchCalls[0].body.internal_tenant_id, "tenant-1");

  const payload = fetchCalls[0].body.payload as Record<string, unknown>;
  assertEquals(payload.status, "order_cancelled");
  assertEquals(payload.order_status_key, "order_cancelled");
  assertEquals(payload.previous_status, "order_cancellation_processing");
  assertEquals(payload.cancellation_stage, "after_provider_creation");
});

Deno.test("triggerRtdhProviderPlatformNewOrder includes canonical and provider patient ids", async () => {
  const originalFetch = globalThis.fetch;
  const fetchCalls: Array<{ url: string; body: Record<string, unknown> }> = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

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
            return Promise.resolve({
              data: { slug: "tenant-slug" },
              error: null,
            });
          },
        };
      }

      if (table === "platform_settings") {
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
                value: {
                  api_url: "https://rtdh.example.com",
                  patient_platform_webhook_secret: "secret-1",
                },
              },
              error: null,
            });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    await triggerRtdhProviderPlatformNewOrder({
      supabase,
      requestId: "request-1",
      tenantId: "tenant-1",
      orderId: "order-1",
      orderStatusHistoryId: "history-1",
      patientId: "patient-1",
      providerPatientId: "mdi-patient-1",
      providerPlatformKey: "md_integrations",
      providerPlatformOrderId: "case-1",
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assertEquals(fetchCalls.length, 1);
  assertEquals(
    fetchCalls[0].url,
    "https://rtdh.example.com/patient-platform-webhook-receiver/provider-platform/new-order",
  );
  assertEquals(fetchCalls[0].body.tenant, "tenant-slug");
  assertEquals(fetchCalls[0].body.internal_tenant_id, "tenant-1");
  const payload = fetchCalls[0].body.payload as Record<string, unknown>;
  assertEquals(payload.patient_platform_order_id, "order-1");
  assertEquals(payload.patient_id, "patient-1");
  assertEquals(payload.provider_patient_id, "mdi-patient-1");
  assertEquals(payload.provider_name, "md_integrations");
  assertEquals(payload.provider_order_id, "case-1");
  assertEquals(payload.internal_tenant_id, "tenant-1");
});

Deno.test("triggerRtdhCreateOrder includes canonical patient_id", async () => {
  const originalFetch = globalThis.fetch;
  const fetchCalls: Array<{ url: string; body: Record<string, unknown> }> = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

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
            return Promise.resolve({
              data: { slug: "tenant-slug" },
              error: null,
            });
          },
        };
      }

      if (table === "platform_settings") {
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
                value: {
                  api_url: "https://rtdh.example.com",
                  patient_platform_webhook_secret: "secret-1",
                },
              },
              error: null,
            });
          },
        };
      }

      if (table === "order_payment_provider_transactions") {
        let selected = "";
        return {
          select(columns: string) {
            selected = columns;
            return this;
          },
          eq() {
            return this;
          },
          not() {
            return this;
          },
          order() {
            return this;
          },
          limit() {
            return this;
          },
          maybeSingle() {
            if (selected.includes("provider_payment_intent_id")) {
              return Promise.resolve({
                data: { provider_payment_intent_id: "pi_123" },
                error: null,
              });
            }
            return Promise.resolve({ data: null, error: null });
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
                patients: {
                  email: "jane@example.com",
                  first_name: "Jane",
                  last_name: "Doe",
                  phone: "2025550123",
                },
              },
              error: null,
            });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    await triggerRtdhCreateOrder({
      supabase,
      requestId: "request-1",
      tenantId: "tenant-1",
      patientId: "patient-1",
      orderId: "order-1",
      orderStatusId: null,
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assertEquals(fetchCalls.length, 1);
  assertEquals(
    fetchCalls[0].url,
    "https://rtdh.example.com/patient-platform-webhook-receiver/create-order",
  );
  assertEquals(fetchCalls[0].body.tenant, "tenant-slug");
  assertEquals(fetchCalls[0].body.internal_tenant_id, "tenant-1");
  const payload = fetchCalls[0].body.payload as Record<string, unknown>;
  assertEquals(payload.patient_platform_order_id, "order-1");
  assertEquals(payload.patient_id, "patient-1");
  assertEquals(payload.payment_intent_id, "pi_123");
  assertEquals(payload.internal_tenant_id, "tenant-1");
});

Deno.test("triggerRtdhCreateOrder includes invoice_id and subscription_id for renewal orders", async () => {
  const originalFetch = globalThis.fetch;
  const fetchCalls: Array<{ url: string; body: Record<string, unknown> }> = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

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
            return Promise.resolve({
              data: { slug: "tenant-slug" },
              error: null,
            });
          },
        };
      }

      if (table === "platform_settings") {
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
                value: {
                  api_url: "https://rtdh.example.com",
                  patient_platform_webhook_secret: "secret-1",
                },
              },
              error: null,
            });
          },
        };
      }

      if (table === "order_payment_provider_transactions") {
        let selected = "";
        return {
          select(columns: string) {
            selected = columns;
            return this;
          },
          eq() {
            return this;
          },
          not() {
            return this;
          },
          order() {
            return this;
          },
          limit() {
            return this;
          },
          maybeSingle() {
            // Renewal orders carry the Stripe invoice + subscription on their
            // payment transaction but have no Checkout Session or PaymentIntent.
            if (selected.includes("provider_subscription_id")) {
              return Promise.resolve({
                data: { provider_subscription_id: "sub_renewal_123" },
                error: null,
              });
            }
            if (selected.includes("provider_invoice_id")) {
              return Promise.resolve({
                data: { provider_invoice_id: "in_renewal_123" },
                error: null,
              });
            }
            return Promise.resolve({ data: null, error: null });
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
                patients: {
                  email: "jane@example.com",
                  first_name: "Jane",
                  last_name: "Doe",
                  phone: "2025550123",
                },
              },
              error: null,
            });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    await triggerRtdhCreateOrder({
      supabase,
      requestId: "request-renewal",
      tenantId: "tenant-1",
      patientId: "patient-1",
      orderId: "order-renewal-1",
      orderStatusId: null,
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assertEquals(fetchCalls.length, 1);
  const payload = fetchCalls[0].body.payload as Record<string, unknown>;
  assertEquals(payload.invoice_id, "in_renewal_123");
  assertEquals(payload.subscription_id, "sub_renewal_123");
  assertEquals(payload.patient_platform_order_id, "order-renewal-1");
});
