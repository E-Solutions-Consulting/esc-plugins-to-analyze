import type { ReactNode } from "react";

export interface IntegrationSettingDefinition {
  key: string;
  label: string;
  placeholder: string;
  description?: ReactNode;
  sensitive?: boolean;
  required?: boolean;
  inputType?: "text" | "url" | "email" | "textarea" | "json";
  rows?: number;
}

export const integrationCategoryOrder = [
  "provider_platform",
  "forms",
  "pharmacy",
  "shipping",
  "email_distribution",
  "customer_support",
  "payment",
  "referrals",
  "push_notifications",
  "analytics",
  "general",
] as const;

export const integrationCategoryLabels: Record<string, string> = {
  provider_platform: "Provider Platform",
  forms: "Forms",
  pharmacy: "Pharmacy",
  shipping: "Shipping",
  email_distribution: "Email Distribution",
  customer_support: "Customer Support",
  payment: "Payment",
  referrals: "Referrals",
  push_notifications: "Push Notifications",
  analytics: "Analytics",
  general: "General",
};

export const integrationCategoryDescriptions: Record<string, string> = {
  provider_platform:
    "Manage external provider platforms used to power clinical and care workflows.",
  forms: "Manage form intake integrations used by downstream workflows.",
  pharmacy:
    "Manage pharmacy platform integrations for prescription fulfillment and delivery tracking.",
  shipping:
    "Manage shipping and fulfillment integrations used to create labels and route carriers.",
  email_distribution: "Manage outbound email delivery integrations.",
  customer_support: "Manage customer support and communication integrations.",
  payment: "Manage payment and billing integrations.",
  referrals: "Manage referral program integrations and hosted referral widgets.",
  analytics: "Manage analytics and reporting integrations.",
  push_notifications:
    "Manage push notification delivery integrations for the mobile app.",
  general: "Manage other platform integrations.",
};

const defaultLabels: Record<string, string> = {
  api_key: "API Key",
  api_url: "API URL",
  access_token: "Access Token",
  username: "Username",
  password: "Password",
  backend_url: "Backend URL",
  client_id: "Client ID",
  client_secret: "Client Secret",
  project_id: "Project ID",
  webhook_authorization: "Webhook Authorization",
  backend_secret: "Backend Secret",
  patient_questionnaire_definition: "Patient Questionnaire Definition",
  sender_email: "Sender Email",
  url: "URL",
  app_id: "App ID",
  help_center_url: "Help Center URL",
  carrier: "Carrier",
  merchant_id: "Merchant ID",
  campaign_id: "Campaign ID",
  mount_element_id: "Mount Element ID",
  secret_key: "Secret Key",
  placement: "Placement",
  banner_title: "Banner Title",
  reward_label: "Reward Label",
};

const defaultPlaceholders: Record<string, string> = {
  api_key: "Enter your API key",
  api_url: "https://api.example.com",
  access_token: "Enter the access token",
  username: "Enter the username",
  password: "Enter the password",
  backend_url: "https://backend.example.com",
  client_id: "Enter the client ID",
  client_secret: "Enter the client secret",
  project_id: "Enter the project ID",
  webhook_authorization: "Enter the webhook authorization value",
  backend_secret: "Enter the backend secret",
  patient_questionnaire_definition: '{\n  "questionnaire": "definition"\n}',
  sender_email: "noreply@yourdomain.com",
  url: "https://api.example.com",
  app_id: "Enter your Intercom App ID",
  help_center_url: "https://intercom.help/your-company",
  carrier: "usps",
  merchant_id: "Enter the Friendbuy merchant ID",
  campaign_id: "Enter the Friendbuy campaign ID",
  mount_element_id: "friendbuy-referral-widget",
  secret_key: "Enter the secret key",
  placement: "dashboard",
  banner_title: "Refer a Friend",
  reward_label: "Both earn $25",
};

export const formatIntegrationSettingLabel = (settingKey: string) => {
  return (
    defaultLabels[settingKey] ||
    settingKey
      .split("_")
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(" ")
  );
};

const createDefaultSettingDefinition = (
  settingKey: string,
): IntegrationSettingDefinition => ({
  key: settingKey,
  label: formatIntegrationSettingLabel(settingKey),
  placeholder:
    defaultPlaceholders[settingKey] ||
    `Enter ${formatIntegrationSettingLabel(settingKey).toLowerCase()}`,
  sensitive:
    settingKey.includes("token") ||
    settingKey.includes("key") ||
    settingKey.includes("secret") ||
    settingKey.includes("jwt"),
  required: true,
  inputType: settingKey.includes("url")
    ? "url"
    : settingKey.includes("email")
      ? "email"
    : "text",
});

const normalizeRequiredSettingKeys = (requiredSettings: unknown[] = []) =>
  requiredSettings.flatMap((setting) => {
    if (typeof setting === "string") return [setting];
    if (
      setting &&
      typeof setting === "object" &&
      !Array.isArray(setting) &&
      typeof (setting as { key?: unknown }).key === "string"
    ) {
      return [(setting as { key: string }).key];
    }
    return [];
  });

const integrationSettingDefinitions: Record<
  string,
  IntegrationSettingDefinition[]
> = {
  resend: [
    {
      key: "api_key",
      label: "API Key",
      placeholder: "Enter your API key",
      sensitive: true,
      required: true,
      description: (
        <>
          Get your API key from{" "}
          <a
            href="https://resend.com/api-keys"
            target="_blank"
            rel="noopener noreferrer"
            data-testid="link-resend-api-keys"
            className="text-primary hover:underline"
          >
            resend.com/api-keys
          </a>
          .
        </>
      ),
    },
    {
      key: "sender_email",
      label: "Sender Email",
      placeholder: "noreply@yourdomain.com",
      inputType: "email",
      required: false,
      description: (
        <>
          The email address that will appear as the sender. Must be from a{" "}
          <a
            href="https://resend.com/domains"
            target="_blank"
            rel="noopener noreferrer"
            data-testid="link-resend-verified-domain"
            className="text-primary hover:underline"
          >
            verified domain
          </a>
          .
        </>
      ),
    },
  ],
  telegramd: [
    {
      key: "username",
      label: "Affiliate Admin Username",
      placeholder: "Enter the Telegra affiliate admin username",
      required: true,
      description:
        "Primary Telegra authentication field. Used with password to authenticate via /auth/client.",
    },
    {
      key: "password",
      label: "Affiliate Admin Password",
      placeholder: "Enter the Telegra affiliate admin password",
      sensitive: true,
      required: true,
      description:
        "Primary Telegra authentication field. Used with username to authenticate via /auth/client.",
    },
    {
      key: "url",
      label: "URL",
      placeholder: "https://api.telegramd.example.com",
      inputType: "url",
      required: true,
    },
    {
      key: "project_id",
      label: "Project ID",
      placeholder: "Enter the Telegra project ID",
      required: true,
      description:
        "Tenant-specific Telegra project identifier sent when creating orders.",
    },
    {
      key: "patient_questionnaire_definition",
      label: "Patient Questionnaire Definition",
      placeholder: '{\n  "questionnaire": "definition"\n}',
      inputType: "json",
      required: true,
      rows: 12,
      description:
        "Tenant-specific JSON object describing the questionnaire required for all Telegra patients.",
    },
  ],
  zito_care: [
    {
      key: "access_token",
      label: "Access Token",
      placeholder: "Enter the Zito Care access token",
      sensitive: true,
      required: true,
    },
    {
      key: "url",
      label: "URL",
      placeholder: "https://api.zitocare.example.com",
      inputType: "url",
      required: true,
    },
  ],
  md_integrations: [
    {
      key: "client_id",
      label: "Client ID",
      placeholder: "Enter the MD Integrations client ID",
      required: true,
    },
    {
      key: "client_secret",
      label: "Client Secret",
      placeholder: "Enter the MD Integrations client secret",
      sensitive: true,
      required: true,
    },
    {
      key: "backend_url",
      label: "Backend URL",
      placeholder: "https://backend.mdintegrations.example.com",
      inputType: "url",
      required: true,
    },
    {
      key: "patient_questionnaire_definition",
      label: "Patient Questionnaire Definition",
      placeholder: '{\n  "questionnaire": "definition"\n}',
      inputType: "json",
      required: true,
      rows: 12,
      description:
        "Tenant-specific JSON object describing the questionnaire required for all MD Integrations patients.",
    },
  ],
  intercom: [
    {
      key: "app_id",
      label: "App ID",
      placeholder: "Enter your Intercom App ID",
      required: true,
    },
    {
      key: "backend_secret",
      label: "Backend Secret",
      placeholder: "Enter your Intercom backend secret",
      sensitive: true,
      required: false,
    },
    {
      key: "help_center_url",
      label: "Help Center URL",
      placeholder: "https://intercom.help/your-company",
      inputType: "url",
      required: false,
    },
  ],
  easypost: [
    {
      key: "api_key",
      label: "API Key",
      placeholder: "Enter your EasyPost API key",
      sensitive: true,
      required: true,
      description: (
        <>
          Create or copy an API key from{" "}
          <a
            href="https://www.easypost.com/account/api-keys"
            target="_blank"
            rel="noopener noreferrer"
            data-testid="link-easypost-api-keys"
            className="text-primary hover:underline"
          >
            EasyPost API Keys
          </a>
          .
        </>
      ),
    },
    {
      key: "carrier",
      label: "Carrier",
      placeholder: "ups",
      required: true,
      description:
        "Carrier identifier to use for this tenant, such as usps, ups, or fedex.",
    },
  ],
  lifefile: [
    {
      key: "webhook_username",
      label: "Webhook Username",
      placeholder: "Enter the Basic Auth username LifeFile will send",
      sensitive: false,
      required: true,
      description:
        "The HTTP Basic Auth username configured in LifeFile for this tenant's webhook endpoint.",
    },
    {
      key: "webhook_password",
      label: "Webhook Password",
      placeholder: "Enter the Basic Auth password LifeFile will send",
      sensitive: true,
      required: true,
      description:
        "The HTTP Basic Auth password configured in LifeFile for this tenant's webhook endpoint.",
    },
  ],
  onesignal: [
    {
      key: "app_id",
      label: "App ID",
      placeholder: "e.g. 7458163d-66ea-4f5b-84a1-ecdebb5138ae",
      required: true,
      description: (
        <>
          Found in your OneSignal dashboard under{" "}
          <a
            href="https://dashboard.onesignal.com"
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary hover:underline"
          >
            Settings → Keys &amp; IDs
          </a>
          .
        </>
      ),
    },
    {
      key: "rest_api_key",
      label: "REST API Key",
      placeholder: "Enter your OneSignal REST API Key",
      sensitive: true,
      required: true,
      description: "Found alongside the App ID in Settings → Keys & IDs.",
    },
  ],
  jotform: [
    {
      key: "api_url",
      label: "API URL",
      placeholder: "https://api.jotform.com",
      inputType: "url",
      required: true,
      description:
        "Base URL RTDH will call with the API key when retrieving submitted Jotform forms.",
    },
    {
      key: "api_key",
      label: "API Key",
      placeholder: "Enter the Jotform API key",
      sensitive: true,
      required: true,
      description:
        "Used by RTDH to access submitted Jotform forms and route form data to the selected provider workflow.",
    },
  ],
  friendbuy: [
    {
      key: "merchant_id",
      label: "Merchant ID",
      placeholder: "Enter the Friendbuy merchant ID",
      required: true,
      description:
        "Used by Patient UI to initialize the Friendbuy SDK and load the tenant campaign script.",
    },
    {
      key: "campaign_id",
      label: "Campaign ID",
      placeholder: "Enter the Friendbuy campaign ID",
      required: true,
      description:
        "Used as campaign context for widget tracking and backend conversion events.",
    },
    {
      key: "mount_element_id",
      label: "Mount Element ID",
      placeholder: "friendbuy-referral-widget",
      required: false,
      description:
        "Raw Patient UI DOM id for the hosted widget mount. In Friendbuy, enter #friendbuy-referral-widget in the HTML element field.",
    },
    {
      key: "secret_key",
      label: "Webhook Signing Secret",
      placeholder: "Enter the Friendbuy webhook signing secret",
      sensitive: true,
      required: true,
      description:
        "Backend-only credential used by PP-admin Edge Functions to verify inbound Friendbuy webhook signatures. Distinct from the API Key/API Secret Key below.",
    },
    {
      key: "api_key",
      label: "API Key",
      placeholder: "Enter the Friendbuy API key",
      sensitive: true,
      required: true,
      description:
        "Backend-only credential used by PP-admin Edge Functions to authenticate outbound Friendbuy API calls.",
    },
    {
      key: "api_secret_key",
      label: "API Secret Key",
      placeholder: "Enter the Friendbuy API secret key",
      sensitive: true,
      required: true,
      description:
        "Backend-only credential paired with API Key for outbound Friendbuy API calls.",
    },
    {
      key: "placement",
      label: "Placement",
      placeholder: "dashboard",
      required: false,
      description:
        "Patient UI placement name sent with page tracking. Defaults to dashboard.",
    },
    {
      key: "banner_title",
      label: "Banner Title",
      placeholder: "Brello Bestie",
      required: false,
      description:
        "PP-owned referral banner title. Friendbuy still owns the invite form below the banner.",
    },
    {
      key: "reward_label",
      label: "Reward Label",
      placeholder: "Both earn $25",
      required: false,
      description:
        "PP-owned referral banner step label. Coupon/reward economics are configured in Friendbuy/Stripe.",
    },
  ],
};

export const getIntegrationSettingDefinitions = (
  integrationKey: string,
  requiredSettings: unknown[] = [],
): IntegrationSettingDefinition[] => {
  return (
    integrationSettingDefinitions[integrationKey] ||
    normalizeRequiredSettingKeys(requiredSettings).map(createDefaultSettingDefinition)
  );
};

export const getRequiredSettingsForIntegration = (
  integrationKey: string,
  requiredSettings: unknown[] = [],
) => {
  const definitions = getIntegrationSettingDefinitions(
    integrationKey,
    requiredSettings,
  );
  const requiredDefinitionKeys = definitions
    .filter((setting) => setting.required !== false)
    .map((setting) => setting.key);

  return Array.from(
    new Set([
      ...normalizeRequiredSettingKeys(requiredSettings),
      ...requiredDefinitionKeys,
    ]),
  );
};

export const hasConfiguredRequiredSettings = (
  settings: Record<string, unknown> | null | undefined,
  requiredSettings: string[] = [],
  integrationKey?: string,
) => {
  if (!settings) return false;

  if (integrationKey === "telegramd") {
    const hasUsername =
      typeof settings.username === "string" &&
      settings.username.trim().length > 0;
    const hasPassword =
      typeof settings.password === "string" &&
      settings.password.trim().length > 0;
    const hasAccessToken =
      typeof settings.access_token === "string" &&
      settings.access_token.trim().length > 0;

    if (!(hasUsername && hasPassword) && !hasAccessToken) {
      return false;
    }
  }

  return requiredSettings.every((settingKey) => {
    if (
      integrationKey === "telegramd" &&
      ["access_token", "username", "password"].includes(settingKey)
    ) {
      return true;
    }

    const value = settings[settingKey];

    if (typeof value === "string") {
      return value.trim().length > 0;
    }

    return Boolean(value);
  });
};
