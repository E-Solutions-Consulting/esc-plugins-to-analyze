import { assertEquals } from "../_test/assert.ts";
import {
  buildFeatureFlagsResult,
  buildFriendbuyClientConfig,
  buildIntercomClientConfig,
  buildMobileAppsConfig,
  buildProviderPlatformsConfig,
  buildTenantSupportConfig,
  getTenantIdentifier,
  normalizeAvailableStates,
  pickBranding,
  sanitizeTenantSlug,
} from "./helpers.ts";

Deno.test("getTenantIdentifier reads query first and falls back to headers", () => {
  const url = new URL("https://example.com?slug=demo");
  const headers = new Headers({ "x-tenant-id": "tenant-1" });
  assertEquals(getTenantIdentifier(url, headers), {
    slug: "demo",
    tenantId: "tenant-1",
  });
});

Deno.test("sanitizeTenantSlug trims and strips quotes", () => {
  assertEquals(sanitizeTenantSlug(" 'allia-demo' "), "allia-demo");
});

Deno.test("normalizeAvailableStates returns states only for US tenants", () => {
  assertEquals(normalizeAvailableStates(["US"], [" ca ", "ny"]), ["CA", "NY"]);
  assertEquals(normalizeAvailableStates(["BR"], ["SP"]), []);
});

Deno.test("buildFeatureFlagsResult applies overrides over defaults", () => {
  const result = buildFeatureFlagsResult(
    [
      {
        id: "f1",
        key: "feature_a",
        name: "A",
        description: null,
        default_value: false,
        flag_type: "bool",
      },
      {
        id: "f2",
        key: "feature_b",
        name: "B",
        description: null,
        default_value: true,
        flag_type: "bool",
      },
    ],
    [{ feature_flag_id: "f1", enabled: true }],
  );

  assertEquals(result, { feature_a: true, feature_b: true });
});

Deno.test("pickBranding supports object and array response shapes", () => {
  assertEquals(pickBranding([{ primary_color: "#000" }]), {
    primary_color: "#000",
  });
  assertEquals(pickBranding({ primary_color: "#111" }), {
    primary_color: "#111",
  });
  assertEquals(pickBranding(null), null);
});

Deno.test("buildIntercomClientConfig returns normalized settings when enabled", () => {
  const result = buildIntercomClientConfig({
    is_enabled: true,
    settings: {
      app_id: " app_456 ",
      help_center_url: " https://intercom.help/acme-health ",
    },
  });

  assertEquals(result, {
    app_id: "app_456",
    help_center_url: "https://intercom.help/acme-health",
  });
});

Deno.test("buildIntercomClientConfig returns null when integration is disabled", () => {
  const result = buildIntercomClientConfig({
    is_enabled: false,
    settings: {
      app_id: "app_456",
    },
  });

  assertEquals(result, null);
});

Deno.test("buildIntercomClientConfig returns null when required settings are missing", () => {
  assertEquals(
    buildIntercomClientConfig({
      is_enabled: true,
      settings: {
        app_id: "",
      },
    }),
    null,
  );

  assertEquals(
    buildIntercomClientConfig({
      is_enabled: true,
      settings: {
        app_id: "app_456",
        help_center_url: "   ",
      },
    }),
    { app_id: "app_456" },
  );
});

Deno.test("buildFriendbuyClientConfig exposes safe display copy and excludes secrets", () => {
  const config = buildFriendbuyClientConfig({
    is_enabled: true,
    settings: {
      merchant_id: "merchant-1",
      campaign_id: "campaign-1",
      mount_element_id: "friendbuy-referral-widget",
      placement: "dashboard",
      banner_title: "Brello Bestie",
      reward_label: "Both earn $25",
      secret_key: "webhook-secret",
      api_key: "api-key-secret",
      api_secret_key: "api-secret-key-secret",
    },
  });

  assertEquals(config, {
    merchant_id: "merchant-1",
    campaign_id: "campaign-1",
    mount_element_id: "friendbuy-referral-widget",
    placement: "dashboard",
    banner_title: "Brello Bestie",
    reward_label: "Both earn $25",
  });
});

Deno.test("buildFriendbuyClientConfig omits empty banner title", () => {
  const config = buildFriendbuyClientConfig({
    is_enabled: true,
    settings: {
      merchant_id: "merchant-1",
      campaign_id: "campaign-1",
      banner_title: "   ",
    },
  });

  assertEquals(config, {
    merchant_id: "merchant-1",
    campaign_id: "campaign-1",
  });
});

Deno.test("buildTenantSupportConfig returns normalized html when present", () => {
  assertEquals(
    buildTenantSupportConfig({
      support_html: " <p>Need help?</p> ",
    }),
    { html: "<p>Need help?</p>" },
  );
});

Deno.test("buildTenantSupportConfig returns null when html is empty", () => {
  assertEquals(
    buildTenantSupportConfig({
      support_html: "   ",
    }),
    null,
  );
});

Deno.test("buildTenantSupportConfig passes through valid faqs", () => {
  assertEquals(
    buildTenantSupportConfig({
      support_html: "<p>Contact us</p>",
      faqs: [
        { question: " How do refills work? ", answer: " We ship monthly. " },
        { question: "Can I pause?", answer: "Yes, any time." },
      ],
    }),
    {
      html: "<p>Contact us</p>",
      faqs: [
        { question: "How do refills work?", answer: "We ship monthly." },
        { question: "Can I pause?", answer: "Yes, any time." },
      ],
    },
  );
});

Deno.test("buildTenantSupportConfig drops malformed faq entries", () => {
  assertEquals(
    buildTenantSupportConfig({
      faqs: [
        { question: "Valid?", answer: "Yes." },
        { question: "   ", answer: "Blank question" },
        { question: "Blank answer", answer: "" },
        { question: "Missing answer" },
        "not-an-object",
        null,
        42,
        ["nested", "array"],
      ],
    }),
    {
      faqs: [{ question: "Valid?", answer: "Yes." }],
    },
  );
});

Deno.test("buildTenantSupportConfig omits faqs when not an array", () => {
  assertEquals(
    buildTenantSupportConfig({
      support_hours: "Monday–Friday, 8:00 AM–5:00 PM CST",
      faqs: { question: "not", answer: "an array" },
    }),
    { hours: "Monday–Friday, 8:00 AM–5:00 PM CST" },
  );
});

Deno.test("buildTenantSupportConfig emits trimmed hours", () => {
  assertEquals(
    buildTenantSupportConfig({
      support_hours: "  Monday–Friday, 8:00 AM–5:00 PM CST  ",
    }),
    { hours: "Monday–Friday, 8:00 AM–5:00 PM CST" },
  );
});

Deno.test("buildTenantSupportConfig returns null when everything is empty", () => {
  assertEquals(
    buildTenantSupportConfig({
      support_html: "   ",
      faqs: [],
      support_hours: "  ",
    }),
    null,
  );
  assertEquals(buildTenantSupportConfig(null), null);
});

Deno.test("buildMobileAppsConfig returns normalized configured links only", () => {
  assertEquals(
    buildMobileAppsConfig({
      mobile_apps: {
        ios_app_link: " https://apps.apple.com/app/care-link ",
        android_app_link: "",
      },
    }),
    {
      stores: [
        {
          id: "ios",
          app_url: "https://apps.apple.com/app/care-link",
          qr_code_url: "",
        },
      ],
    },
  );

  assertEquals(buildMobileAppsConfig({ mobile_apps: {} }), null);
  assertEquals(buildMobileAppsConfig(null), null);
});

Deno.test("buildMobileAppsConfig returns new store metadata shape", () => {
  assertEquals(
    buildMobileAppsConfig({
      mobile_apps: {
        stores: [
          {
            id: "android",
            app_url: " https://play.google.com/store/apps/details?id=com.demo ",
            qr_code_url: " https://cdn.example.com/android-qr.svg ",
          },
        ],
      },
    }),
    {
      stores: [
        {
          id: "android",
          app_url: "https://play.google.com/store/apps/details?id=com.demo",
          qr_code_url: "https://cdn.example.com/android-qr.svg",
        },
      ],
    },
  );
});

Deno.test("buildProviderPlatformsConfig exposes logo defaults without settings", () => {
  assertEquals(
    buildProviderPlatformsConfig([
      {
        id: "integration-1",
        key: "telegramd",
        name: "TelegraMD",
        logo_url: " https://cdn.example.com/telegra.svg ",
        provider_logo_assets: [
          { id: "logo-1", is_default: false },
          { id: "logo-2", is_default: true },
        ],
      },
      {
        id: "integration-2",
        key: "md_integrations",
        name: "MD Integrations",
        logo_url: null,
        provider_logo_assets: [],
      },
    ]),
    {
      telegramd: {
        name: "TelegraMD",
        logo_url: "https://cdn.example.com/telegra.svg",
        default_logo_asset_id: "logo-2",
      },
      md_integrations: {
        name: "MD Integrations",
        logo_url: null,
        default_logo_asset_id: null,
      },
    },
  );

  assertEquals(buildProviderPlatformsConfig([]), null);
});
