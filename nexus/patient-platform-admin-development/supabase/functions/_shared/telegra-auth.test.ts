import { assertEquals } from "../_test/assert.ts";
import {
  appendTelegraRequestTimestamp,
  buildTelegraAuthCurl,
  buildTelegraClientAuthUrl,
  extractTelegraAccessTokenExpiry,
  isTelegraAccessTokenCacheEntryValid,
} from "./telegra-auth.ts";

Deno.test("buildTelegraClientAuthUrl appends auth client path once", () => {
  assertEquals(
    buildTelegraClientAuthUrl("https://api.telegramd.example.com/"),
    "https://api.telegramd.example.com/auth/client",
  );
  assertEquals(
    buildTelegraClientAuthUrl("https://api.telegramd.example.com"),
    "https://api.telegramd.example.com/auth/client",
  );
});

Deno.test("appendTelegraRequestTimestamp preserves existing query parameters", () => {
  assertEquals(
    appendTelegraRequestTimestamp(
      "https://api.telegramd.example.com/orders?access_token=abc",
      "2026-06-26T12:34:56.000Z",
    ),
    "https://api.telegramd.example.com/orders?access_token=abc&request_timestamp=2026-06-26T12%3A34%3A56.000Z",
  );
});

Deno.test("buildTelegraAuthCurl redacts credentials", () => {
  assertEquals(
    buildTelegraAuthCurl({
      endpoint:
        "https://api.telegramd.example.com/auth/client?request_timestamp=2026-06-29T10%3A00%3A00.000Z",
      requestId: "request-123",
      source: "qa-api",
    }),
    "curl -X POST -H 'Accept: application/json' -H 'Authorization: Basic <redacted>' -H 'x-request-id: request-123' -H 'x-source: qa-api' 'https://api.telegramd.example.com/auth/client?request_timestamp=2026-06-29T10%3A00%3A00.000Z'",
  );
});

Deno.test("extractTelegraAccessTokenExpiry uses ISO expirations when present", () => {
  const now = Date.parse("2026-04-16T10:00:00.000Z");
  assertEquals(
    extractTelegraAccessTokenExpiry(
      { expiresAt: "2026-04-16T11:00:00.000Z" },
      now,
    ),
    "2026-04-16T11:00:00.000Z",
  );
});

Deno.test("extractTelegraAccessTokenExpiry supports unix second expirations", () => {
  const now = Date.parse("2026-04-16T10:00:00.000Z");
  assertEquals(
    extractTelegraAccessTokenExpiry(
      { data: { exp: 1_776_341_400 } },
      now,
    ),
    "2026-04-16T12:10:00.000Z",
  );
});

Deno.test("extractTelegraAccessTokenExpiry falls back to the default ttl", () => {
  const now = Date.parse("2026-04-16T10:00:00.000Z");
  assertEquals(
    extractTelegraAccessTokenExpiry({ token: "abc" }, now),
    "2026-04-16T10:55:00.000Z",
  );
});

Deno.test("isTelegraAccessTokenCacheEntryValid rejects missing and near-expiry tokens", () => {
  const now = Date.parse("2026-04-16T10:00:00.000Z");
  assertEquals(isTelegraAccessTokenCacheEntryValid(null, now), false);
  assertEquals(
    isTelegraAccessTokenCacheEntryValid("2026-04-16T10:00:30.000Z", now),
    false,
  );
  assertEquals(
    isTelegraAccessTokenCacheEntryValid("2026-04-16T10:02:00.000Z", now),
    true,
  );
});
