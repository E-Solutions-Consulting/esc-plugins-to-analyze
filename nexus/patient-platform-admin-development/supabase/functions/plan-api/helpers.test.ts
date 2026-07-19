import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  checkRateLimit,
  getCorsHeaders,
  normalizeTenantSlug,
  RATE_LIMIT,
  shouldDeferTelegraProviderReviewCancellation,
  validateStateAgainstTenant,
} from "./helpers.ts";

Deno.test("normalizeTenantSlug removes quote wrapping", () => {
  assertEquals(normalizeTenantSlug(" 'tenant-demo' "), "tenant-demo");
  assertEquals(normalizeTenantSlug(null), null);
});

Deno.test("getCorsHeaders echoes allowed origin", () => {
  const headers = getCorsHeaders(
    new Request("https://example.com", {
      headers: { origin: "https://demo.lovableproject.com" },
    }),
  );
  assertEquals(
    headers["Access-Control-Allow-Origin"],
    "https://demo.lovableproject.com",
  );
  assertEquals(headers["Access-Control-Allow-Credentials"], "true");
});

Deno.test("checkRateLimit denies requests after limit", () => {
  const store = new Map<string, { count: number; resetAt: number }>();
  for (let i = 0; i < RATE_LIMIT; i++) {
    checkRateLimit("plan-client", store, 1000);
  }
  assertEquals(checkRateLimit("plan-client", store, 1000).allowed, false);
});

Deno.test("validateStateAgainstTenant enforces tenant state restrictions", async () => {
  const supabaseClient = {
    from: () => ({
      select: () => ({
        eq: () => ({
          maybeSingle: async () => ({
            data: { allowed_states: ["FL"] },
            error: null,
          }),
        }),
      }),
    }),
  };

  assertEquals(
    await validateStateAgainstTenant(supabaseClient, "tenant-1", "FL", "US"),
    { valid: true },
  );
  assertEquals(
    await validateStateAgainstTenant(supabaseClient, "tenant-1", "CA", "US"),
    {
      valid: false,
      message:
        "We are unable to ship to CA. Please select a different shipping address.",
    },
  );
});

Deno.test("shouldDeferTelegraProviderReviewCancellation defers stale local questionnaire status when Telegra reached review", () => {
  assertEquals(
    shouldDeferTelegraProviderReviewCancellation({
      currentStatusKey: "provider_review_pending",
      providerPlatformIntegrationKey: "telegramd",
      providerLinkMetadata: [],
    }),
    true,
  );

  assertEquals(
    shouldDeferTelegraProviderReviewCancellation({
      currentStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "telegramd",
      providerLinkMetadata: [
        {
          last_event_status: "requires_provider_review",
        },
      ],
    }),
    true,
  );

  assertEquals(
    shouldDeferTelegraProviderReviewCancellation({
      currentStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "telegramd",
      providerLinkMetadata: [
        {
          last_event_target_entity_status: "requires_provider_review",
        },
      ],
    }),
    true,
  );
});

Deno.test("shouldDeferTelegraProviderReviewCancellation does not defer after provider decision statuses", () => {
  assertEquals(
    shouldDeferTelegraProviderReviewCancellation({
      currentStatusKey: "provider_approved",
      providerPlatformIntegrationKey: "telegramd",
      providerLinkMetadata: [
        {
          last_event_status: "requires_provider_review",
        },
      ],
    }),
    false,
  );

  assertEquals(
    shouldDeferTelegraProviderReviewCancellation({
      currentStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "md_integrations",
      providerLinkMetadata: [
        {
          last_event_status: "requires_provider_review",
        },
      ],
    }),
    false,
  );
});
