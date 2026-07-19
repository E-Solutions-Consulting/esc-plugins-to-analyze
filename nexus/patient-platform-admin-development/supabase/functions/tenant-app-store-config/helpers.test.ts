import { assertEquals } from "../_test/assert.ts";
import {
  buildMobileAppsMetadata,
  buildQrCodeApiUrl,
  buildQrCodeStoragePath,
  getWebAppOrigin,
  normalizeStoreInputs,
  normalizeWebAppInput,
  readExistingMobileAppStores,
  readExistingWebAppConfig,
  replacePasskeyAllowedOrigin,
} from "./helpers.ts";

Deno.test("normalizeStoreInputs keeps configured iOS and Android stores", () => {
  assertEquals(
    normalizeStoreInputs([
      {
        id: "ios",
        app_url: " https://apps.apple.com/app/care-link ",
      },
      {
        id: "android",
        app_url: "https://play.google.com/store/apps/details?id=com.demo",
      },
    ]),
    [
      {
        id: "ios",
        app_url: "https://apps.apple.com/app/care-link",
        qr_code_url: "",
      },
      {
        id: "android",
        app_url: "https://play.google.com/store/apps/details?id=com.demo",
        qr_code_url: "",
      },
    ],
  );
});

Deno.test("readExistingMobileAppStores supports legacy links", () => {
  assertEquals(
    readExistingMobileAppStores({
      mobile_apps: {
        ios_app_link: "https://apps.apple.com/app/care-link",
      },
    }),
    [
      {
        id: "ios",
        app_url: "https://apps.apple.com/app/care-link",
        qr_code_url: "",
      },
    ],
  );
});

Deno.test("normalizeWebAppInput keeps configured web app base URL", () => {
  assertEquals(
    normalizeWebAppInput({
      base_url: " https://app.example.com/patient ",
    }),
    {
      base_url: "https://app.example.com/patient",
    },
  );

  assertEquals(normalizeWebAppInput({ base_url: "" }), null);
});

Deno.test("readExistingWebAppConfig supports current and legacy shapes", () => {
  assertEquals(
    readExistingWebAppConfig({
      mobile_apps: {
        web_app: {
          base_url: "https://app.example.com",
        },
      },
    }),
    {
      base_url: "https://app.example.com/",
    },
  );

  assertEquals(
    readExistingWebAppConfig({
      mobile_apps: {
        web_app_base_url: "https://legacy.example.com",
      },
    }),
    {
      base_url: "https://legacy.example.com/",
    },
  );
});

Deno.test("buildMobileAppsMetadata preserves web app when no mobile stores exist", () => {
  assertEquals(
    buildMobileAppsMetadata([], {
      base_url: "https://app.example.com/",
    }),
    {
      web_app: {
        base_url: "https://app.example.com/",
      },
    },
  );

  assertEquals(buildMobileAppsMetadata([], null), undefined);
});

Deno.test("web app origin helpers replace the previous passkey origin", () => {
  assertEquals(
    getWebAppOrigin({ base_url: "https://app.example.com/patient/checkout" }),
    "https://app.example.com",
  );
  assertEquals(getWebAppOrigin(null), null);

  assertEquals(
    replacePasskeyAllowedOrigin(
      ["https://old.example.com/path", "https://other.example.com"],
      "https://old.example.com",
      "https://new.example.com",
    ),
    ["https://other.example.com", "https://new.example.com"],
  );
});

Deno.test("QR helpers create stable storage path and API URL", () => {
  assertEquals(
    buildQrCodeStoragePath("tenant-1", "android"),
    "tenant-1/app-stores/android-qr.svg",
  );

  const qrUrl = new URL(buildQrCodeApiUrl("https://example.com/app"));
  assertEquals(qrUrl.hostname, "api.qrserver.com");
  assertEquals(qrUrl.searchParams.get("format"), "svg");
  assertEquals(qrUrl.searchParams.get("data"), "https://example.com/app");
});
