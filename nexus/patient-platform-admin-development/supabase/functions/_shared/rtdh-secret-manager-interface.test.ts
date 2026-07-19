import { assertEquals } from "../_test/assert.ts";
import {
  checkSecretExistsViaRtdh,
  saveSecretViaRtdh,
} from "./rtdh-secret-manager-interface.ts";

Deno.test("saveSecretViaRtdh posts the global consumer token namespace", async () => {
  const originalFetch = globalThis.fetch;
  const originalBaseUrl = Deno.env.get("RTDH_BASE_URL");
  const originalSecret = Deno.env.get("RTDH_SECRET_MANAGER_RECEIVER_SECRET");
  const calls: Array<{ url: string; body: Record<string, unknown> }> = [];

  Deno.env.set("RTDH_BASE_URL", "https://rtdh.example.com");
  Deno.env.set("RTDH_SECRET_MANAGER_RECEIVER_SECRET", "receiver-secret");
  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    calls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(
      new Response(JSON.stringify({ success: true, saved: [] }), {
        status: 200,
      }),
    );
  }) as typeof fetch;

  try {
    await saveSecretViaRtdh({
      apiUrl: "https://ignored.example.com",
      tenant: "allia",
      provider: "patient_platform",
      key: "consumer_webhook_token",
      value: "secret-value-1",
      requestId: "request-1",
    });
  } finally {
    globalThis.fetch = originalFetch;
    if (originalBaseUrl === undefined) {
      Deno.env.delete("RTDH_BASE_URL");
    } else {
      Deno.env.set("RTDH_BASE_URL", originalBaseUrl);
    }
    if (originalSecret === undefined) {
      Deno.env.delete("RTDH_SECRET_MANAGER_RECEIVER_SECRET");
    } else {
      Deno.env.set("RTDH_SECRET_MANAGER_RECEIVER_SECRET", originalSecret);
    }
  }

  assertEquals(calls.length, 1);
  assertEquals(
    calls[0].url,
    "https://rtdh.example.com/secret-manager-receiver",
  );
  assertEquals(calls[0].body.tenant, "allia");
  assertEquals(calls[0].body.provider, "patient_platform");
  assertEquals(calls[0].body.encoding, "base64");
  assertEquals(calls[0].body.key, "consumer_webhook_token");
  assertEquals(calls[0].body.value, "c2VjcmV0LXZhbHVlLTE=");
});

Deno.test("checkSecretExistsViaRtdh uses the same global consumer token namespace", async () => {
  const originalFetch = globalThis.fetch;
  const originalBaseUrl = Deno.env.get("RTDH_BASE_URL");
  const originalSecret = Deno.env.get("RTDH_SECRET_MANAGER_RECEIVER_SECRET");
  const calls: Array<{ url: string; body: Record<string, unknown> }> = [];

  Deno.env.set("RTDH_BASE_URL", "https://rtdh.example.com");
  Deno.env.set("RTDH_SECRET_MANAGER_RECEIVER_SECRET", "receiver-secret");
  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    calls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(
      new Response(
        JSON.stringify({ success: true, exists: true, secretId: "secret-1" }),
        { status: 200 },
      ),
    );
  }) as typeof fetch;

  try {
    await checkSecretExistsViaRtdh({
      apiUrl: "https://ignored.example.com",
      tenant: "allia",
      provider: "patient_platform",
      key: "consumer_webhook_token",
      requestId: "request-2",
    });
  } finally {
    globalThis.fetch = originalFetch;
    if (originalBaseUrl === undefined) {
      Deno.env.delete("RTDH_BASE_URL");
    } else {
      Deno.env.set("RTDH_BASE_URL", originalBaseUrl);
    }
    if (originalSecret === undefined) {
      Deno.env.delete("RTDH_SECRET_MANAGER_RECEIVER_SECRET");
    } else {
      Deno.env.set("RTDH_SECRET_MANAGER_RECEIVER_SECRET", originalSecret);
    }
  }

  assertEquals(calls.length, 1);
  assertEquals(
    calls[0].url,
    "https://rtdh.example.com/secret-manager-receiver",
  );
  assertEquals(calls[0].body, {
    action: "exists",
    tenant: "allia",
    provider: "patient_platform",
    key: "consumer_webhook_token",
  });
});
