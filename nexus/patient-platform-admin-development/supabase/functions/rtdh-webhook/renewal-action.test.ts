import { assertEquals } from "../_test/assert.ts";
import { processRenewalIntent } from "./renewal-action.ts";

function jsonResponse(
  _req: Request,
  body: Record<string, unknown>,
  status = 200,
  headers: Record<string, string> = {},
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      "Content-Type": "application/json",
      ...headers,
    },
  });
}

function errorResponse(
  req: Request,
  code: string,
  message: string,
  status: number,
  requestId: string,
  details?: unknown,
): Response {
  return jsonResponse(
    req,
    {
      error: {
        code,
        message,
        details: details ?? null,
      },
      requestId,
    },
    status,
    { "x-request-id": requestId },
  );
}

Deno.test("processRenewalIntent triggers lifecycle for newly created renewals", async () => {
  const lifecycleCalls: Array<[string, string, string]> = [];

  const response = await processRenewalIntent({
    req: new Request("https://example.com/event", { method: "POST" }),
    supabase: {} as never,
    payload: { internal_tenant_id: "tenant-1" } as never,
    requestId: "req-created",
    jsonResponse,
    errorResponse,
    renewalHandler: async () => ({
      ok: true,
      tenantId: "tenant-1",
      orderId: "order-1",
      created: true,
      strategy: "subscription",
    }),
    lifecycleTrigger: async (orderId, tenantId, requestId) => {
      lifecycleCalls.push([orderId, tenantId, requestId]);
      return true;
    },
  });

  assertEquals(lifecycleCalls, [["order-1", "tenant-1", "req-created"]]);
  assertEquals(response.status, 200);
  assertEquals(await response.json(), {
    received: true,
    requestId: "req-created",
    eventType: "renewal_order_create",
    actionResult: {
      action: "renewal_order_create",
      orderId: "order-1",
      created: true,
      resolutionStrategy: "subscription",
      lifecycleTriggered: true,
    },
  });
});

Deno.test("processRenewalIntent does not retrigger lifecycle for reused renewal orders", async () => {
  let lifecycleCalls = 0;

  const response = await processRenewalIntent({
    req: new Request("https://example.com/event", { method: "POST" }),
    supabase: {} as never,
    payload: { internal_tenant_id: "tenant-1" } as never,
    requestId: "req-reused",
    jsonResponse,
    errorResponse,
    renewalHandler: async () => ({
      ok: true,
      tenantId: "tenant-1",
      orderId: "order-existing",
      created: false,
      strategy: "idempotency_invoice",
    }),
    lifecycleTrigger: async () => {
      lifecycleCalls += 1;
      return true;
    },
  });

  assertEquals(lifecycleCalls, 0);
  assertEquals(response.status, 200);
  assertEquals(await response.json(), {
    received: true,
    requestId: "req-reused",
    eventType: "renewal_order_create",
    actionResult: {
      action: "renewal_order_create",
      orderId: "order-existing",
      created: false,
      resolutionStrategy: "idempotency_invoice",
      lifecycleTriggered: false,
    },
  });
});

Deno.test("processRenewalIntent returns an error response without triggering lifecycle", async () => {
  let lifecycleCalls = 0;

  const response = await processRenewalIntent({
    req: new Request("https://example.com/event", { method: "POST" }),
    supabase: {} as never,
    payload: { internal_tenant_id: "tenant-1" } as never,
    requestId: "req-error",
    jsonResponse,
    errorResponse,
    renewalHandler: async () => ({
      ok: false,
      status: 422,
      code: "validation_error",
      message: "bad renewal payload",
      details: { field: "payment" },
    }),
    lifecycleTrigger: async () => {
      lifecycleCalls += 1;
      return true;
    },
  });

  assertEquals(lifecycleCalls, 0);
  assertEquals(response.status, 422);
  assertEquals(await response.json(), {
    error: {
      code: "validation_error",
      message: "bad renewal payload",
      details: { field: "payment" },
    },
    requestId: "req-error",
  });
});