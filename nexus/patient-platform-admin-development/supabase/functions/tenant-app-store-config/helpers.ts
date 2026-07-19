export type AppStoreId = "ios" | "android";

export interface AppStoreInput {
  id: AppStoreId;
  app_url?: string | null;
  qr_code_url?: string | null;
}

export interface AppStoreConfig {
  id: AppStoreId;
  app_url: string;
  qr_code_url: string;
}

export interface WebAppInput {
  base_url?: string | null;
}

export interface WebAppConfig {
  base_url: string;
}

export interface TenantAppStoreConfigRequest {
  tenantId: string;
  stores: AppStoreInput[];
  web_app?: WebAppInput | null;
  web_app_base_url?: string | null;
}

export function isAppStoreId(value: unknown): value is AppStoreId {
  return value === "ios" || value === "android";
}

function readString(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

export function normalizeOptionalHttpsUrl(
  value: unknown,
  fieldLabel: string,
): string {
  const normalized = readString(value);
  if (!normalized) {
    return "";
  }

  let parsed: URL;
  try {
    parsed = new URL(normalized);
  } catch {
    throw new Error(`${fieldLabel} must be a valid URL`);
  }

  if (parsed.protocol !== "https:") {
    throw new Error(`${fieldLabel} must use HTTPS`);
  }

  return parsed.toString();
}

export function normalizeStoreInputs(stores: unknown): AppStoreConfig[] {
  if (!Array.isArray(stores)) {
    return [];
  }

  const normalizedStores: AppStoreConfig[] = [];
  const seenStoreIds = new Set<AppStoreId>();

  stores.forEach((store) => {
    if (!store || typeof store !== "object" || Array.isArray(store)) {
      return;
    }

    const record = store as Record<string, unknown>;
    const storeId = record.id;
    if (!isAppStoreId(storeId) || seenStoreIds.has(storeId)) {
      return;
    }

    const appUrl = normalizeOptionalHttpsUrl(
      record.app_url,
      `${storeId} app URL`,
    );
    if (!appUrl) {
      return;
    }

    normalizedStores.push({
      id: storeId,
      app_url: appUrl,
      qr_code_url: readString(record.qr_code_url),
    });
    seenStoreIds.add(storeId);
  });

  return normalizedStores;
}

export function normalizeWebAppInput(webApp: unknown): WebAppConfig | null {
  if (!webApp || typeof webApp !== "object" || Array.isArray(webApp)) {
    return null;
  }

  const record = webApp as Record<string, unknown>;
  const baseUrl = normalizeOptionalHttpsUrl(
    record.base_url,
    "Web app base URL",
  );

  return baseUrl ? { base_url: baseUrl } : null;
}

export function getWebAppOrigin(webApp: WebAppConfig | null): string | null {
  return webApp ? new URL(webApp.base_url).origin : null;
}

export function replacePasskeyAllowedOrigin(
  allowedOrigins: unknown,
  previousOrigin: string | null,
  nextOrigin: string | null,
): string[] {
  const normalizedOrigins = Array.isArray(allowedOrigins)
    ? allowedOrigins.flatMap((value) => {
      if (typeof value !== "string") return [];
      try {
        return [new URL(value).origin];
      } catch {
        return [];
      }
    })
    : [];
  const nextOrigins = new Set(normalizedOrigins);

  if (previousOrigin) nextOrigins.delete(previousOrigin);
  if (nextOrigin) nextOrigins.add(nextOrigin);

  return Array.from(nextOrigins);
}

export function readExistingMobileAppStores(
  metadata: Record<string, unknown> | null | undefined,
): AppStoreConfig[] {
  if (!metadata || typeof metadata !== "object" || Array.isArray(metadata)) {
    return [];
  }

  const mobileApps = metadata.mobile_apps;
  if (
    !mobileApps ||
    typeof mobileApps !== "object" ||
    Array.isArray(mobileApps)
  ) {
    return [];
  }

  const settings = mobileApps as Record<string, unknown>;
  const stores = normalizeStoreInputs(settings.stores);
  const existingStoreIds = new Set(stores.map((store) => store.id));

  const iosAppLink = normalizeOptionalHttpsUrl(
    settings.ios_app_link,
    "iOS app URL",
  );
  if (iosAppLink && !existingStoreIds.has("ios")) {
    stores.push({
      id: "ios",
      app_url: iosAppLink,
      qr_code_url: "",
    });
  }

  const androidAppLink = normalizeOptionalHttpsUrl(
    settings.android_app_link,
    "Android app URL",
  );
  if (androidAppLink && !existingStoreIds.has("android")) {
    stores.push({
      id: "android",
      app_url: androidAppLink,
      qr_code_url: "",
    });
  }

  return stores;
}

export function readExistingWebAppConfig(
  metadata: Record<string, unknown> | null | undefined,
): WebAppConfig | null {
  if (!metadata || typeof metadata !== "object" || Array.isArray(metadata)) {
    return null;
  }

  const mobileApps = metadata.mobile_apps;
  if (
    !mobileApps ||
    typeof mobileApps !== "object" ||
    Array.isArray(mobileApps)
  ) {
    return null;
  }

  const settings = mobileApps as Record<string, unknown>;
  return normalizeWebAppInput(settings.web_app) ||
    normalizeWebAppInput({ base_url: settings.web_app_base_url });
}

export function buildMobileAppsMetadata(
  stores: AppStoreConfig[],
  webApp: WebAppConfig | null,
): { stores?: AppStoreConfig[]; web_app?: WebAppConfig } | undefined {
  const mobileApps = {
    ...(stores.length > 0 ? { stores } : {}),
    ...(webApp ? { web_app: webApp } : {}),
  };

  return Object.keys(mobileApps).length > 0 ? mobileApps : undefined;
}

export function buildQrCodeStoragePath(
  tenantId: string,
  storeId: AppStoreId,
): string {
  return `${tenantId}/app-stores/${storeId}-qr.svg`;
}

export function buildQrCodeApiUrl(appUrl: string): string {
  const url = new URL("https://api.qrserver.com/v1/create-qr-code/");
  url.searchParams.set("size", "512x512");
  url.searchParams.set("format", "svg");
  url.searchParams.set("data", appUrl);
  return url.toString();
}
