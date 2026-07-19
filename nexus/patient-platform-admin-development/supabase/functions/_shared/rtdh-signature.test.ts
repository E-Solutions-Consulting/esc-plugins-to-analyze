import { assertEquals } from "../_test/assert.ts";
import {
  computeHmacSha256Hex,
  parseHmacSha256SignatureHeader,
  postSignedRtdhJson,
  verifyHmacSha256Signature,
} from "./rtdh-signature.ts";

Deno.test("computeHmacSha256Hex returns lowercase hex for the raw body", async () => {
  const signature = await computeHmacSha256Hex("secret", '{"order_id":"123"}');

  assertEquals(
    signature,
    "01dfad61c9e7eda5507672e97cf12649949e6cdf8b78b61791e6d7df676dc213",
  );
});

Deno.test("parseHmacSha256SignatureHeader requires sha256-prefixed values", () => {
  assertEquals(parseHmacSha256SignatureHeader("abcdef"), null);
  assertEquals(parseHmacSha256SignatureHeader("sha256=ABCDEF"), "abcdef");
  assertEquals(parseHmacSha256SignatureHeader(" sha256=abcdef "), "abcdef");
  assertEquals(parseHmacSha256SignatureHeader(""), null);
  assertEquals(parseHmacSha256SignatureHeader(null), null);
});

Deno.test("verifyHmacSha256Signature validates x-webhook-signature format", async () => {
  const payload = '{"event_type":"chat.message.received"}';
  const signature = await computeHmacSha256Hex("secret", payload);

  assertEquals(
    await verifyHmacSha256Signature({
      secret: "secret",
      payload,
      signatureHeader: `sha256=${signature}`,
    }),
    true,
  );
  assertEquals(
    await verifyHmacSha256Signature({
      secret: "wrong-secret",
      payload,
      signatureHeader: `sha256=${signature}`,
    }),
    false,
  );
});

Deno.test("postSignedRtdhJson signs the exact JSON body and omits legacy auth headers", async () => {
  const originalFetch = globalThis.fetch;
  let capturedUrl: string | null = null;
  let capturedInit: RequestInit | undefined;

  try {
    globalThis.fetch = ((
      input: string | URL | Request,
      init?: RequestInit,
    ) => {
      capturedUrl = input instanceof Request ? input.url : String(input);
      capturedInit = init;
      return Promise.resolve(new Response("ok", { status: 200 }));
    }) as typeof fetch;

    const response = await postSignedRtdhJson({
      url: "https://rtdh.example.com/patient-platform-webhook-receiver",
      requestId: "req_123",
      requestSource: "order-lifecycle:provider-platform-new-order",
      webhookSecret: "secret",
      payload: {
        tenant_id: "tenant-1",
        order_id: "order-1",
      },
    });

    assertEquals(response.status, 200);
    assertEquals(
      capturedUrl,
      "https://rtdh.example.com/patient-platform-webhook-receiver",
    );
    if (!capturedInit) {
      throw new Error("Expected fetch to be called with request init");
    }

    assertEquals(capturedInit.method, "POST");
    assertEquals(
      capturedInit.body,
      '{"tenant_id":"tenant-1","order_id":"order-1"}',
    );

    const headers = capturedInit.headers as Record<string, string>;
    assertEquals(headers["Content-Type"], "application/json");
    assertEquals(headers["x-request-id"], "req_123");
    assertEquals(
      headers["x-request-source"],
      "order-lifecycle:provider-platform-new-order",
    );
    assertEquals(
      headers["x-patientplatform-signature"],
      "sha256=cf167ea67ff423f99795be97533f23c3f02a215cf0ec5eee383d929b8943979d",
    );
    assertEquals(headers.Authorization, undefined);
    assertEquals(headers["x-webhook-secret"], undefined);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("postSignedRtdhJson passes an abort signal when timeout is configured", async () => {
  const originalFetch = globalThis.fetch;
  let capturedInit: RequestInit | undefined;

  try {
    globalThis.fetch = ((
      _input: string | URL | Request,
      init?: RequestInit,
    ) => {
      capturedInit = init;
      return Promise.resolve(new Response("ok", { status: 200 }));
    }) as typeof fetch;

    await postSignedRtdhJson({
      url: "https://rtdh.example.com/patient-platform-webhook-receiver",
      requestId: "req_456",
      requestSource: "order-lifecycle:order-status-updated",
      webhookSecret: "secret",
      payload: { status: "payment_pending" },
      timeoutMs: 8000,
    });

    if (!capturedInit) {
      throw new Error("Expected fetch to be called with request init");
    }

    assertEquals(capturedInit.signal instanceof AbortSignal, true);
  } finally {
    globalThis.fetch = originalFetch;
  }
});
