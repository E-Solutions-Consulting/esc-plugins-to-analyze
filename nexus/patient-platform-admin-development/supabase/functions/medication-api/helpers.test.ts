import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  checkRateLimit,
  getCorsHeaders,
  isAllowedOrigin,
  isPlanBlockingMedicationEligibility,
} from "./helpers.ts";

Deno.test("isAllowedOrigin accepts known local and lovable origins", () => {
  assertEquals(isAllowedOrigin("http://localhost:5173"), true);
  assertEquals(isAllowedOrigin("https://demo.lovable.app"), true);
  assertEquals(isAllowedOrigin("https://evil.example.com"), false);
});

Deno.test("getCorsHeaders mirrors allowed origin and request headers", () => {
  const headers = getCorsHeaders(
    new Request("https://example.com", {
      headers: {
        origin: "http://localhost:3000",
        "access-control-request-headers": "authorization,content-type",
      },
    }),
  );

  assertEquals(headers["Access-Control-Allow-Origin"], "http://localhost:3000");
  assertEquals(headers["Access-Control-Allow-Credentials"], "true");
  assertEquals(
    headers["Access-Control-Allow-Headers"],
    "authorization,content-type",
  );
});

Deno.test("checkRateLimit blocks after RATE_LIMIT requests in same window", () => {
  const store = new Map<string, { count: number; resetAt: number }>();
  const now = 1000;
  const key = "client-a";
  let last = { allowed: true, remaining: 0 };

  for (let i = 0; i < 100; i++) {
    last = checkRateLimit(key, store, now);
  }

  assertEquals(last.allowed, true);
  assertEquals(last.remaining, 0);

  const blocked = checkRateLimit(key, store, now);
  assertEquals(blocked.allowed, false);
  assertEquals(blocked.remaining, 0);
});

Deno.test("isPlanBlockingMedicationEligibility only blocks non-cancelled and non-expired plans", () => {
  const now = Date.parse("2026-03-31T12:00:00.000Z");

  assertEquals(
    isPlanBlockingMedicationEligibility(
      {
        status: "active",
        expires_at: "2026-04-01T00:00:00.000Z",
        cancelled_at: null,
      },
      now,
    ),
    true,
  );

  assertEquals(
    isPlanBlockingMedicationEligibility(
      {
        status: "pending_cancellation",
        expires_at: "2026-04-01T00:00:00.000Z",
        cancelled_at: null,
      },
      now,
    ),
    true,
  );

  assertEquals(
    isPlanBlockingMedicationEligibility(
      {
        status: "paused",
        expires_at: null,
        cancelled_at: null,
      },
      now,
    ),
    true,
  );

  assertEquals(
    isPlanBlockingMedicationEligibility(
      {
        status: "active",
        expires_at: "2026-03-01T00:00:00.000Z",
        cancelled_at: null,
      },
      now,
    ),
    false,
  );

  assertEquals(
    isPlanBlockingMedicationEligibility(
      {
        status: "cancelled",
        expires_at: "2026-04-01T00:00:00.000Z",
        cancelled_at: null,
      },
      now,
    ),
    false,
  );

  assertEquals(
    isPlanBlockingMedicationEligibility(
      {
        status: "active",
        expires_at: "2026-04-01T00:00:00.000Z",
        cancelled_at: "2026-03-15T00:00:00.000Z",
      },
      now,
    ),
    false,
  );
});
