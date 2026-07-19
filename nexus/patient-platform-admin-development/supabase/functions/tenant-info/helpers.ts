export interface FeatureFlag {
  id: string;
  key: string;
  name: string;
  description: string | null;
  default_value: boolean;
  flag_type: string;
}

export interface FlagOverride {
  feature_flag_id: string;
  enabled: boolean;
}

export interface TenantIntegrationRow {
  is_enabled?: boolean | null;
  settings?: Record<string, unknown> | null;
}

export interface IntercomClientConfig {
  app_id: string;
  help_center_url?: string;
}

export interface FriendbuyClientConfig {
  merchant_id: string;
  campaign_id: string;
  mount_element_id?: string;
  placement?: string;
  banner_title?: string;
  reward_label?: string;
}

export interface ReferralProgramClientConfig {
  status: string;
  currency: string;
  reward_amount_cents: number;
}

export interface ReferralProgramRow {
  status?: string | null;
  currency?: string | null;
  reward_amount_cents?: number | null;
}

export interface TenantSupportConfigRow {
  support_html?: string | null;
  faqs?: unknown;
  support_hours?: string | null;
}

export interface TenantSupportFaq {
  question: string;
  answer: string;
}

export interface TenantSupportConfig {
  html?: string;
  faqs?: TenantSupportFaq[];
  hours?: string;
}

export type AppStoreId = "ios" | "android";

export interface AppStoreConfig {
  id: AppStoreId;
  app_url: string;
  qr_code_url: string;
}

export interface MobileAppsConfig {
  stores: AppStoreConfig[];
}

export interface ProviderPlatformLogoRow {
  id: string;
  key: string;
  name: string;
  logo_url?: string | null;
  provider_logo_assets?:
    | Array<{
      id: string;
      is_default?: boolean | null;
    }>
    | null;
}

export interface ProviderPlatformClientConfig {
  name: string;
  logo_url: string | null;
  default_logo_asset_id: string | null;
}

export function sanitizeTenantSlug(value: string): string {
  return value.trim().replace(/['"]/g, "");
}

export function getTenantIdentifier(
  url: URL,
  headers: Headers,
): { slug: string | null; tenantId: string | null } {
  const slug = url.searchParams.get("slug") || headers.get("x-tenant-slug");
  const tenantId = url.searchParams.get("tenant_id") ||
    headers.get("x-tenant-id");
  return { slug, tenantId };
}

export function normalizeAvailableStates(
  allowedCountries?: string[] | null,
  allowedStates?: string[] | null,
): string[] {
  const normalizedAllowedCountries = (allowedCountries || [])
    .map((country) => country.toUpperCase().trim());

  const normalizedAllowedStates = (allowedStates || [])
    .map((state) => state.toUpperCase().trim())
    .filter((state) => state.length > 0);

  return normalizedAllowedCountries.includes("US")
    ? normalizedAllowedStates
    : [];
}

export function buildFeatureFlagsResult(
  featureFlags: FeatureFlag[] | null | undefined,
  overrides: FlagOverride[] | null | undefined,
): Record<string, boolean> {
  const overridesMap = new Map<string, boolean>();
  (overrides || []).forEach((override) => {
    overridesMap.set(override.feature_flag_id, override.enabled);
  });

  const featureFlagsResult: Record<string, boolean> = {};
  (featureFlags || []).forEach((flag) => {
    const effectiveValue = overridesMap.has(flag.id)
      ? overridesMap.get(flag.id)!
      : flag.default_value;
    featureFlagsResult[flag.key] = effectiveValue;
  });

  return featureFlagsResult;
}

export function pickBranding(
  tenantBranding:
    | Record<string, unknown>
    | Array<Record<string, unknown>>
    | null,
): Record<string, unknown> | null {
  if (Array.isArray(tenantBranding)) {
    return tenantBranding[0] || null;
  }
  return tenantBranding || null;
}

function readNonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim();
  return normalized.length > 0 ? normalized : null;
}

function isAppStoreId(value: unknown): value is AppStoreId {
  return value === "ios" || value === "android";
}

function normalizeAppStoreRecord(
  value: unknown,
): AppStoreConfig | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  const record = value as Record<string, unknown>;
  const id = record.id;
  if (!isAppStoreId(id)) {
    return null;
  }

  const appUrl = readNonEmptyString(record.app_url);
  if (!appUrl) {
    return null;
  }

  return {
    id,
    app_url: appUrl,
    qr_code_url: readNonEmptyString(record.qr_code_url) || "",
  };
}

export function buildIntercomClientConfig(
  integration: TenantIntegrationRow | null | undefined,
): IntercomClientConfig | null {
  if (!integration?.is_enabled) {
    return null;
  }

  const settings = integration.settings;
  if (!settings || typeof settings !== "object" || Array.isArray(settings)) {
    return null;
  }

  const appId = readNonEmptyString(settings.app_id);
  const helpCenterUrl = readNonEmptyString(settings.help_center_url);

  if (!appId) {
    return null;
  }

  return {
    app_id: appId,
    ...(helpCenterUrl ? { help_center_url: helpCenterUrl } : {}),
  };
}

export function buildFriendbuyClientConfig(
  integration: TenantIntegrationRow | null | undefined,
): FriendbuyClientConfig | null {
  if (!integration?.is_enabled) return null;

  const settings = integration.settings;
  if (!settings || typeof settings !== "object" || Array.isArray(settings)) {
    return null;
  }

  const merchantId = readNonEmptyString(settings.merchant_id);
  const campaignId = readNonEmptyString(settings.campaign_id);
  if (!merchantId || !campaignId) return null;

  const mountElementId = readNonEmptyString(settings.mount_element_id) ||
    readNonEmptyString(settings.widget_id);
  const placement = readNonEmptyString(settings.placement);
  const bannerTitle = readNonEmptyString(settings.banner_title);
  const rewardLabel = readNonEmptyString(settings.reward_label);

  return {
    merchant_id: merchantId,
    campaign_id: campaignId,
    ...(mountElementId ? { mount_element_id: mountElementId } : {}),
    ...(placement ? { placement } : {}),
    ...(bannerTitle ? { banner_title: bannerTitle } : {}),
    ...(rewardLabel ? { reward_label: rewardLabel } : {}),
  };
}

export function buildReferralProgramClientConfig(
  config: ReferralProgramRow | null | undefined,
): ReferralProgramClientConfig | null {
  if (!config || config.status !== "active") return null;

  return {
    status: config.status,
    currency: config.currency || "USD",
    reward_amount_cents: config.reward_amount_cents || 0,
  };
}

function normalizeSupportFaqs(value: unknown): TenantSupportFaq[] {
  if (!Array.isArray(value)) return [];

  return value.flatMap((entry) => {
    if (!entry || typeof entry !== "object" || Array.isArray(entry)) return [];
    const record = entry as Record<string, unknown>;
    const question = readNonEmptyString(record.question);
    const answer = readNonEmptyString(record.answer);
    if (!question || !answer) return [];
    return [{ question, answer }];
  });
}

export function buildTenantSupportConfig(
  config: TenantSupportConfigRow | null | undefined,
): TenantSupportConfig | null {
  const html = readNonEmptyString(config?.support_html);
  const faqs = normalizeSupportFaqs(config?.faqs);
  const hours = readNonEmptyString(config?.support_hours);

  if (!html && faqs.length === 0 && !hours) {
    return null;
  }

  return {
    ...(html ? { html } : {}),
    ...(faqs.length > 0 ? { faqs } : {}),
    ...(hours ? { hours } : {}),
  };
}

export function buildMobileAppsConfig(
  metadata: Record<string, unknown> | null | undefined,
): MobileAppsConfig | null {
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
  const stores = Array.isArray(settings.stores)
    ? settings.stores
      .map(normalizeAppStoreRecord)
      .filter((store): store is AppStoreConfig => Boolean(store))
    : [];

  const existingStoreIds = new Set(stores.map((store) => store.id));
  const iosAppLink = readNonEmptyString(settings.ios_app_link);
  const androidAppLink = readNonEmptyString(settings.android_app_link);

  if (iosAppLink && !existingStoreIds.has("ios")) {
    stores.push({
      id: "ios",
      app_url: iosAppLink,
      qr_code_url: "",
    });
  }

  if (androidAppLink && !existingStoreIds.has("android")) {
    stores.push({
      id: "android",
      app_url: androidAppLink,
      qr_code_url: "",
    });
  }

  return stores.length > 0 ? { stores } : null;
}

export function buildProviderPlatformsConfig(
  integrations: ProviderPlatformLogoRow[] | null | undefined,
): Record<string, ProviderPlatformClientConfig> | null {
  const result: Record<string, ProviderPlatformClientConfig> = {};

  (integrations || []).forEach((integration) => {
    const key = readNonEmptyString(integration.key);
    const name = readNonEmptyString(integration.name);

    if (!key || !name) {
      return;
    }

    const defaultAsset = (integration.provider_logo_assets || []).find(
      (asset) => asset.is_default,
    );

    result[key] = {
      name,
      logo_url: readNonEmptyString(integration.logo_url),
      default_logo_asset_id: defaultAsset?.id || null,
    };
  });

  return Object.keys(result).length > 0 ? result : null;
}
