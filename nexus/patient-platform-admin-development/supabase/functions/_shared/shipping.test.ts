import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  getTrackingDetailsFromEasyPost,
  getTrackingUrlFromEasyPost,
  resolveTenantEasyPostShippingIntegration,
} from "./shipping.ts";

function createSupabaseStub(config: {
  platformIntegration?: { key: string } | null;
  platformError?: { message: string } | null;
  tenantIntegration?: {
    integration_key: string;
    settings: Record<string, unknown> | null;
  } | null;
  tenantError?: { message: string } | null;
}) {
  return {
    from(table: string) {
      if (table === "platform_integrations") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle: async () => ({
            data: config.platformIntegration ?? null,
            error: config.platformError ?? null,
          }),
        };
      }

      if (table === "tenant_integrations") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle: async () => ({
            data: config.tenantIntegration ?? null,
            error: config.tenantError ?? null,
          }),
        };
      }

      throw new Error(`Unexpected table: ${table}`);
    },
  };
}

Deno.test("resolveTenantEasyPostShippingIntegration returns null when EasyPost is not an active shipping integration", async () => {
  const integration = await resolveTenantEasyPostShippingIntegration(
    createSupabaseStub({
      platformIntegration: null,
    }),
    "tenant-1",
  );

  assertEquals(integration, null);
});

Deno.test("resolveTenantEasyPostShippingIntegration returns null when the tenant has not enabled EasyPost", async () => {
  const integration = await resolveTenantEasyPostShippingIntegration(
    createSupabaseStub({
      platformIntegration: { key: "easypost" },
      tenantIntegration: null,
    }),
    "tenant-1",
  );

  assertEquals(integration, null);
});

Deno.test("resolveTenantEasyPostShippingIntegration returns normalized tenant settings", async () => {
  const integration = await resolveTenantEasyPostShippingIntegration(
    createSupabaseStub({
      platformIntegration: { key: "easypost" },
      tenantIntegration: {
        integration_key: "easypost",
        settings: {
          api_key: "  EZAK123  ",
          carrier: "  UPS  ",
        },
      },
    }),
    "tenant-1",
  );

  assertEquals(integration, {
    integrationKey: "easypost",
    apiKey: "EZAK123",
    carrier: "UPS",
  });
});

Deno.test("resolveTenantEasyPostShippingIntegration returns null when the tenant config has no API key", async () => {
  const integration = await resolveTenantEasyPostShippingIntegration(
    createSupabaseStub({
      platformIntegration: { key: "easypost" },
      tenantIntegration: {
        integration_key: "easypost",
        settings: {
          carrier: "UPS",
        },
      },
    }),
    "tenant-1",
  );

  assertEquals(integration, null);
});

Deno.test("getTrackingDetailsFromEasyPost creates a tracker and returns normalized tracker data", async () => {
  let requestUrl = "";
  let requestInit: RequestInit | undefined;

  const trackingDetails = await getTrackingDetailsFromEasyPost({
    apiKey: "EZAK123",
    trackingNumber: "1Z99V3Y213xxxxxxxx",
    carrier: "UPS",
    fetchImpl: async (input, init) => {
      requestUrl = String(input);
      requestInit = init;

      return new Response(
        JSON.stringify({
          public_url: "https://track.easypost.com/example",
          status: "delivered",
          updated_at: "2026-03-27T10:00:00Z",
        }),
        {
          status: 200,
          headers: {
            "Content-Type": "application/json",
          },
        },
      );
    },
  });

  assertEquals(trackingDetails, {
    trackingUrl: "https://track.easypost.com/example",
    status: "delivered",
    updatedAt: "2026-03-27T10:00:00Z",
  });
  assertEquals(requestUrl, "https://api.easypost.com/v2/trackers");
  assertEquals(requestInit?.method, "POST");
  assertEquals(
    (requestInit?.headers as Record<string, string>).Authorization,
    `Basic ${btoa("EZAK123:")}`,
  );
  assertEquals(
    (requestInit?.headers as Record<string, string>)["Content-Type"],
    "application/json",
  );
  assertEquals(
    requestInit?.body,
    JSON.stringify({
      tracker: {
        tracking_code: "1Z99V3Y213xxxxxxxx",
        carrier: "UPS",
      },
    }),
  );
});

Deno.test("getTrackingUrlFromEasyPost returns the public_url from tracker details", async () => {
  const trackingUrl = await getTrackingUrlFromEasyPost({
    apiKey: "EZAK123",
    trackingNumber: "1Z99V3Y213xxxxxxxx",
    fetchImpl: async () =>
      new Response(
        JSON.stringify({
          public_url: "https://track.easypost.com/example",
          status: "in_transit",
        }),
        {
          status: 200,
          headers: {
            "Content-Type": "application/json",
          },
        },
      ),
  });

  assertEquals(trackingUrl, "https://track.easypost.com/example");
});

Deno.test("getTrackingUrlFromEasyPost returns null when EasyPost does not include a public_url", async () => {
  const trackingUrl = await getTrackingUrlFromEasyPost({
    apiKey: "EZAK123",
    trackingNumber: "1Z99V3Y213xxxxxxxx",
    fetchImpl: async () =>
      new Response(
        JSON.stringify({
          id: "trk_123",
        }),
        {
          status: 200,
          headers: {
            "Content-Type": "application/json",
          },
        },
      ),
  });

  assertEquals(trackingUrl, null);
});

Deno.test("getTrackingDetailsFromEasyPost surfaces EasyPost API errors", async () => {
  let thrown: Error | null = null;

  try {
    await getTrackingDetailsFromEasyPost({
      apiKey: "EZAK123",
      trackingNumber: "1Z99V3Y213xxxxxxxx",
      fetchImpl: async () =>
        new Response(
          JSON.stringify({
            error: {
              message: "Invalid tracker",
            },
          }),
          {
            status: 422,
            headers: {
              "Content-Type": "application/json",
            },
          },
        ),
    });
  } catch (error) {
    thrown = error as Error;
  }

  assertMatch(thrown?.message || "", /^easypost_tracker_lookup_failed:422:/);
});
