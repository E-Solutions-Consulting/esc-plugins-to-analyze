import { assertEquals } from "../_test/assert.ts";
import {
  buildMdiClientAuthUrl,
  extractMdiAccessTokenExpiry,
  isMdiAccessTokenCacheEntryValid,
} from "./mdi-auth.ts";

Deno.test("buildMdiClientAuthUrl appends auth token path once", () => {
  assertEquals(
    buildMdiClientAuthUrl("https://api.mdi.example.com/"),
    "https://api.mdi.example.com/v1/partner/auth/token",
  );
  assertEquals(
    buildMdiClientAuthUrl("https://api.mdi.example.com"),
    "https://api.mdi.example.com/v1/partner/auth/token",
  );
});

Deno.test("extractMdiAccessTokenExpiry uses expires_in when present", () => {
  const now = Date.parse("2026-04-17T10:00:00.000Z");
  assertEquals(
    extractMdiAccessTokenExpiry(
      { access_token: "abc", expires_in: 3600 },
      now,
    ),
    "2026-04-17T11:00:00.000Z",
  );
});

Deno.test("extractMdiAccessTokenExpiry supports ISO expirations when present", () => {
  const now = Date.parse("2026-04-17T10:00:00.000Z");
  assertEquals(
    extractMdiAccessTokenExpiry(
      { expires_at: "2026-04-17T11:30:00.000Z" },
      now,
    ),
    "2026-04-17T11:30:00.000Z",
  );
});

Deno.test("extractMdiAccessTokenExpiry falls back to the default ttl", () => {
  const now = Date.parse("2026-04-17T10:00:00.000Z");
  assertEquals(
    extractMdiAccessTokenExpiry({ access_token: "abc" }, now),
    "2026-04-17T10:55:00.000Z",
  );
});

Deno.test("isMdiAccessTokenCacheEntryValid rejects missing and near-expiry tokens", () => {
  const now = Date.parse("2026-04-17T10:00:00.000Z");
  assertEquals(isMdiAccessTokenCacheEntryValid(null, now), false);
  assertEquals(
    isMdiAccessTokenCacheEntryValid("2026-04-17T10:00:30.000Z", now),
    false,
  );
  assertEquals(
    isMdiAccessTokenCacheEntryValid("2026-04-17T10:02:00.000Z", now),
    true,
  );
});
