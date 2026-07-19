import { assertEquals } from "../_test/assert.ts";
import {
  DEFAULT_SETTINGS,
  eventCategoryFlag,
  filterAndSanitizeBatch,
  getTenantSlug,
  isDisallowedPropertyKey,
  MAX_BATCH_SIZE,
  resolveEffectiveSettings,
  sanitizeProperties,
  sanitizeTenantSlug,
  validateEvent,
} from "./helpers.ts";

// ---------------------------------------------------------------------------
// resolveEffectiveSettings — tenant override wins over platform default
// ---------------------------------------------------------------------------
Deno.test("resolveEffectiveSettings falls back to defaults when both null", () => {
  assertEquals(resolveEffectiveSettings(null, null), DEFAULT_SETTINGS);
});

Deno.test("resolveEffectiveSettings: tenant override beats platform default", () => {
  const result = resolveEffectiveSettings(
    { tracking_enabled: true, track_page_views: true },
    { track_page_views: false },
  );
  assertEquals(result.tracking_enabled, true); // from platform default
  assertEquals(result.track_page_views, false); // tenant override wins
});

// ---------------------------------------------------------------------------
// PII guard
// ---------------------------------------------------------------------------
Deno.test("isDisallowedPropertyKey catches PHI-ish keys (case/substring insensitive)", () => {
  assertEquals(isDisallowedPropertyKey("email"), true);
  assertEquals(isDisallowedPropertyKey("patient_Email"), true);
  assertEquals(isDisallowedPropertyKey("date_of_birth"), true);
  assertEquals(isDisallowedPropertyKey("shippingAddress"), true);
  assertEquals(isDisallowedPropertyKey("medication_name"), true);
  assertEquals(isDisallowedPropertyKey("product_id"), false);
  assertEquals(isDisallowedPropertyKey("step"), false);
});

Deno.test("sanitizeProperties strips disallowed keys and reports them", () => {
  const { clean, stripped } = sanitizeProperties({
    product_id: "p_123",
    email: "a@b.com",
    quantity: 2,
    phone: "555",
  });
  assertEquals(clean, { product_id: "p_123", quantity: 2 });
  assertEquals(stripped.sort(), ["email", "phone"]);
});

Deno.test("sanitizeProperties handles null/undefined", () => {
  assertEquals(sanitizeProperties(null), { clean: {}, stripped: [] });
  assertEquals(sanitizeProperties(undefined), { clean: {}, stripped: [] });
});

// ---------------------------------------------------------------------------
// validateEvent
// ---------------------------------------------------------------------------
Deno.test("validateEvent requires client_event_id and event_type", () => {
  assertEquals(validateEvent({ event_type: "page_view" }).valid, false);
  assertEquals(validateEvent({ client_event_id: "c1" }).valid, false);
  assertEquals(
    validateEvent({ client_event_id: "c1", event_type: "page_view" }).valid,
    true,
  );
});

Deno.test("validateEvent rejects oversized properties", () => {
  const big = { client_event_id: "c1", event_type: "track", properties: { blob: "x".repeat(9000) } };
  assertEquals(validateEvent(big).reason, "properties_too_large");
});

// ---------------------------------------------------------------------------
// eventCategoryFlag
// ---------------------------------------------------------------------------
Deno.test("eventCategoryFlag maps event types to gating flags", () => {
  assertEquals(eventCategoryFlag({ event_type: "page_view" }), "track_page_views");
  assertEquals(eventCategoryFlag({ event_type: "track" }), "track_activity_events");
  assertEquals(eventCategoryFlag({ event_type: "session_start" }), null);
  assertEquals(eventCategoryFlag({ event_type: "identify" }), null);
});

// ---------------------------------------------------------------------------
// filterAndSanitizeBatch — the heart of the server-side guard
// ---------------------------------------------------------------------------
Deno.test("filterAndSanitizeBatch drops page_view when track_page_views off", () => {
  const settings = { ...DEFAULT_SETTINGS, track_page_views: false };
  const { accepted, rejected } = filterAndSanitizeBatch(
    [{ client_event_id: "c1", event_type: "page_view", page_path: "/x" }],
    settings,
    { isAuthenticated: true },
  );
  assertEquals(accepted.length, 0);
  assertEquals(rejected[0].reason, "category_disabled");
});

Deno.test("filterAndSanitizeBatch rejects guest events when guest tracking off", () => {
  const settings = { ...DEFAULT_SETTINGS, track_guest_sessions: false };
  const { accepted, rejected } = filterAndSanitizeBatch(
    [{ client_event_id: "c1", event_type: "track", event_name: "x" }],
    settings,
    { isAuthenticated: false },
  );
  assertEquals(accepted.length, 0);
  assertEquals(rejected[0].reason, "guest_tracking_disabled");
});

Deno.test("filterAndSanitizeBatch strips duration_ms when time tracking off", () => {
  const settings = { ...DEFAULT_SETTINGS, track_time_on_page: false };
  const { accepted } = filterAndSanitizeBatch(
    [{ client_event_id: "c1", event_type: "page_view", duration_ms: 5000 }],
    settings,
    { isAuthenticated: true },
  );
  assertEquals(accepted.length, 1);
  assertEquals(accepted[0].duration_ms, undefined);
});

Deno.test("filterAndSanitizeBatch sanitises properties on accepted events", () => {
  const { accepted } = filterAndSanitizeBatch(
    [{
      client_event_id: "c1",
      event_type: "track",
      event_name: "checkout_started",
      properties: { product_id: "p1", email: "leak@x.com" },
    }],
    DEFAULT_SETTINGS,
    { isAuthenticated: true },
  );
  assertEquals(accepted[0].properties, { product_id: "p1" });
});

Deno.test("filterAndSanitizeBatch truncates oversized batches", () => {
  const many = Array.from({ length: MAX_BATCH_SIZE + 5 }, (_, i) => ({
    client_event_id: `c${i}`,
    event_type: "track",
    event_name: "e",
  }));
  const { accepted, rejected } = filterAndSanitizeBatch(many, DEFAULT_SETTINGS, {
    isAuthenticated: true,
  });
  assertEquals(accepted.length, MAX_BATCH_SIZE);
  assertEquals(
    rejected.some((r) => r.reason === `batch_truncated_to_${MAX_BATCH_SIZE}`),
    true,
  );
});

// ---------------------------------------------------------------------------
// tenant slug helpers
// ---------------------------------------------------------------------------
Deno.test("sanitizeTenantSlug trims and strips quotes", () => {
  assertEquals(sanitizeTenantSlug(`  'acme'  `), "acme");
});

Deno.test("getTenantSlug reads query param then header", () => {
  const url = new URL("https://x/analytics-api/config?tenant_slug=acme");
  assertEquals(getTenantSlug(url, new Headers()), "acme");
  const url2 = new URL("https://x/analytics-api/config");
  assertEquals(getTenantSlug(url2, new Headers({ "x-tenant-slug": "beta" })), "beta");
  assertEquals(getTenantSlug(url2, new Headers()), null);
});
