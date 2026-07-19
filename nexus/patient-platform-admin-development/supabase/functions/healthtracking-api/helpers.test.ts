import { assertEquals, assertMatch } from "../_test/assert.ts";
import { checkRateLimit, getCorsHeaders, isAllowedOrigin } from "./helpers.ts";

Deno.test("isAllowedOrigin accepts known origins", () => {
  assertEquals(isAllowedOrigin("http://127.0.0.1:54321"), true);
  assertEquals(isAllowedOrigin("https://tenant.lovableproject.com"), true);
  assertEquals(isAllowedOrigin("https://example.com"), false);
});

Deno.test("getCorsHeaders includes allow-credentials for allowed origin", () => {
  const headers = getCorsHeaders(
    new Request("https://example.com", {
      headers: { origin: "https://tenant.lovable.app" },
    })
  );

  assertEquals(headers["Access-Control-Allow-Origin"], "https://tenant.lovable.app");
  assertEquals(headers["Access-Control-Allow-Credentials"], "true");
  assertEquals(headers["Vary"], "Origin, Access-Control-Request-Headers");
});

Deno.test("checkRateLimit resets after window expires", () => {
  const store = new Map<string, { count: number; resetAt: number }>();
  const key = "client-b";

  for (let i = 0; i < 100; i++) {
    checkRateLimit(key, store, 1000);
  }

  const blocked = checkRateLimit(key, store, 1000);
  assertEquals(blocked.allowed, false);

  const afterReset = checkRateLimit(key, store, 62001);
  assertEquals(afterReset.allowed, true);
});
