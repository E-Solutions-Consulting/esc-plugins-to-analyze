import { assertEquals } from "../_test/assert.ts";
import {
  getTenantIdentifier,
  readWebAppBaseUrl,
  sanitizeTenantSlug,
} from "./helpers.ts";

Deno.test("getTenantIdentifier reads query values first and falls back to headers", () => {
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

Deno.test("readWebAppBaseUrl supports current and legacy mobile app metadata", () => {
  assertEquals(
    readWebAppBaseUrl({
      mobile_apps: {
        web_app: {
          base_url: " https://app.example.com ",
        },
      },
    }),
    "https://app.example.com",
  );

  assertEquals(
    readWebAppBaseUrl({
      mobile_apps: {
        web_app_base_url: "https://legacy.example.com",
      },
    }),
    "https://legacy.example.com",
  );

  assertEquals(readWebAppBaseUrl({ mobile_apps: {} }), null);
});
