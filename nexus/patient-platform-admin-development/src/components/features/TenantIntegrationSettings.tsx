import { JotformTeamWorkspaceHelp } from "@/components/features/JotformTeamWorkspaceHelp";
import { JotformHiddenFieldsHelp } from "@/components/features/JotformHiddenFieldsHelp";
import {
  JotformDefaultWebhookWarning,
  JotformWebhookStatusControl,
} from "@/components/features/JotformWebhookStatusControl";
import { TenantPaymentProvidersManager } from "@/components/features/TenantPaymentProvidersManager";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { useAuditLog } from "@/hooks/useAuditLog";
import { supabase } from "@/integrations/supabase/client";
import { validateJotformQuestionnaireForm } from "@/lib/jotform-validation";
import {
  getIntegrationSettingDefinitions,
  getRequiredSettingsForIntegration,
  hasConfiguredRequiredSettings,
  integrationCategoryDescriptions,
  integrationCategoryLabels,
  integrationCategoryOrder,
  type IntegrationSettingDefinition,
} from "@/lib/integration-config";
import { useAuth } from "@/stores/authStore";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import {
  Bell,
  Building2,
  CheckCircle2,
  CreditCard,
  Eye,
  EyeOff,
  FileText,
  Gift,
  Globe,
  Headset,
  Loader2,
  Lock,
  Mail,
  Package,
  Pencil,
  Pill,
  Plug,
  Plus,
  Power,
  PowerOff,
  Save,
  Webhook,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

const JOTFORM_INTEGRATION_KEY = "jotform";
const PATIENT_QUESTIONNAIRE_DEFINITION_SETTING =
  "patient_questionnaire_definition";
const JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING =
  "patient_questionnaire_form_id";
const RTDH_REPLICATED_PROVIDER_SECRET_KEYS: Record<string, Set<string>> = {
  lifefile: new Set(["webhook_username", "webhook_password"]),
  // Friendbuy is a referral platform — its API keys live in the DB only, not in
  // RTDH's GCP Secret Manager. Empty set prevents the set-provider-rtdh-secret
  // call (which would 400 because friendbuy isn't in its SUPPORTED_PROVIDER_KEYS).
  friendbuy: new Set(),
};
const RTDH_WEBHOOK_SECRET_INTEGRATION_LABELS: Record<string, string> = {
  easypost: "EasyPost",
  intercom: "Intercom",
  jotform: "Jotform",
};

function getRtdhProviderSecretKeys(
  integrationKey: string,
  settingDefinitions: IntegrationSettingDefinition[],
): Set<string> {
  const configuredKeys = RTDH_REPLICATED_PROVIDER_SECRET_KEYS[integrationKey];
  if (configuredKeys) return configuredKeys;

  return new Set(
    settingDefinitions
      .filter(
        (setting) =>
          setting.sensitive ||
          /secret|token|password|api_?key|client_?secret/i.test(setting.key),
      )
      .map((setting) => setting.key),
  );
}

type ProviderSettingsFacet = "all" | "credentials" | "questionnaire";

/**
 * The Provider settings split across two pages (see the `facet` prop):
 * - `questionnaire` owns the Patient Questionnaire Definition (+ its Jotform form
 *   id, handled separately) — the Questionnaires → Patient tab.
 * - `credentials` owns everything else (the provider connection creds + URL) — the
 *   Providers page.
 * - `all` shows both (legacy /settings/Integrations route).
 * Persistence is unchanged; this only scopes which settings a given page edits, so a
 * save from one facet must never touch the other facet's keys. Helpers below keep
 * `handleStartEdit`/`handleSaveSettings`/validation consistent with what's rendered.
 */
const isProviderQuestionnaireSetting = (settingKey: string) =>
  settingKey === PATIENT_QUESTIONNAIRE_DEFINITION_SETTING;

const filterProviderSettingsByFacet = <T extends { key: string }>(
  definitions: T[],
  facet: ProviderSettingsFacet,
): T[] => {
  if (facet === "all") return definitions;
  return definitions.filter((definition) =>
    facet === "questionnaire"
      ? isProviderQuestionnaireSetting(definition.key)
      : !isProviderQuestionnaireSetting(definition.key),
  );
};
const JOTFORM_TEAM_WORKSPACE_ID_SETTING = "team_workspace_id";
const JOTFORM_DEFAULT_WEBHOOK_URL_SETTING = "default_webhook_url";
const DEFAULT_JOTFORM_TEAM_WORKSPACE_ID = "261483056110044";
const DEFAULT_JOTFORM_WEBHOOK_URL =
  "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver";
const PATIENT_QUESTIONNAIRE_JOTFORM_ID_SETTING_DEFINITION: IntegrationSettingDefinition =
  {
    key: JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING,
    label: "Patient Questionnaire Jotform ID",
    placeholder: "Enter the Jotform form ID",
    required: false,
  };

const integrationIcons: Record<string, React.ReactNode> = {
  resend: <Mail className="h-5 w-5" />,
  telegramd: <Globe className="h-5 w-5" />,
  zito_care: <Building2 className="h-5 w-5" />,
  md_integrations: <Building2 className="h-5 w-5" />,
  intercom: <Headset className="h-5 w-5" />,
  easypost: <Package className="h-5 w-5" />,
  lifefile: <Pill className="h-5 w-5" />,
  jotform: <FileText className="h-5 w-5" />,
  onesignal: <Bell className="h-5 w-5" />,
  friendbuy: <Gift className="h-5 w-5" />,
};

/**
 * Tenant-managed integration categories: simple credential-based connections an
 * admin enables/disables and configures per tenant (email delivery, support,
 * push, pharmacy, shipping, analytics, general). These render an Enable/Disable
 * toggle and always show their card — even with no tenant_integrations row yet —
 * so a fresh tenant can connect them. provider_platform has its own (already
 * toggle-driven) flow and `forms` (Jotform) has a bespoke renderer, so both are
 * intentionally excluded here.
 */
const TOGGLABLE_CATEGORIES = new Set<string>([
  "email_distribution",
  "customer_support",
  "push_notifications",
  "pharmacy",
  "shipping",
  "referrals",
  "analytics",
  "general",
]);

const isTogglableCategory = (category: string) =>
  TOGGLABLE_CATEGORIES.has(category);

const getSettingValue = (
  settings: Record<string, unknown> | undefined,
  key: string,
) => settings?.[key];

const hasSettingValue = (
  settings: Record<string, unknown> | undefined,
  key: string,
) => {
  const value = getSettingValue(settings, key);

  if (typeof value === "string") {
    return value.trim().length > 0;
  }

  if (value && typeof value === "object" && !Array.isArray(value)) {
    return Object.keys(value as Record<string, unknown>).length > 0;
  }

  return Boolean(value);
};

const getSettingInputValue = (
  settings: Record<string, unknown> | undefined,
  setting: IntegrationSettingDefinition,
) => {
  const value = getSettingValue(settings, setting.key);

  if (typeof value === "string") {
    return value;
  }

  if (
    setting.inputType === "json" &&
    value &&
    typeof value === "object" &&
    !Array.isArray(value)
  ) {
    return JSON.stringify(value, null, 2);
  }

  return "";
};

// validateJotformQuestionnaireForm moved to @/lib/jotform-validation (shared with
// the patient/medical questionnaire editors). Imported at the top of this file.

async function syncJotformDefaultWebhooks(tenantIntegrationId: string) {
  const {
    data: { session },
  } = await supabase.auth.getSession();

  if (!session?.access_token) {
    throw new Error("You must be signed in to sync Jotform webhooks");
  }

  const response = await fetch(
    `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/provider-platform-bridge/jotform-webhook-sync`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${session.access_token}`,
        apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ tenantIntegrationId }),
    },
  );

  const result = (await response.json().catch(() => null)) as {
    message?: string;
  } | null;

  if (!response.ok) {
    throw new Error(result?.message || "Failed to sync Jotform webhooks");
  }
}

async function replicateProviderSecretsToRtdh(input: {
  tenantId: string;
  provider: string;
  secrets: Record<string, string>;
}) {
  if (Object.keys(input.secrets).length === 0) return;

  const { data, error } = await supabase.functions.invoke(
    "set-provider-rtdh-secret",
    {
      body: {
        tenant_id: input.tenantId,
        provider: input.provider,
        secrets: input.secrets,
      },
    },
  );
  if (error) throw error;
  if (data && (data as { error?: string }).error) {
    throw new Error((data as { error: string }).error);
  }
}

export function TenantIntegrationSettings({
  defaultTab = "providers",
  only,
  facet = "all",
  renderProviderFooter,
}: {
  defaultTab?: string;
  /**
   * Restrict which integration tabs render (by tab value). Lets the regrouped IA
   * split this component's categories across pages (Providers, Questionnaires,
   * Communications, Payments, Order Lifecycle). When exactly one tab remains the
   * tab bar is hidden (the page nav already names the section). Omit to show all.
   */
  only?: string[];
  /**
   * For the `provider_platform` (Providers) category, restrict which *facet* of a
   * provider's settings renders, so the two concerns can live on separate pages:
   * - `credentials`  — the provider connection (send-orders creds + base URL); the
   *                    Patient Questionnaire Definition is hidden. Used by the
   *                    Providers page (which also owns the receive-side RTDH secret).
   * - `questionnaire`— only the Patient Questionnaire Definition (Direct path) plus
   *                    its Jotform form ID + webhook (Jotform path). Used by the
   *                    Questionnaires → Patient tab.
   * - `all`          — both (legacy /settings/Integrations route). Default.
   * Persistence is identical for every facet (same tenant_integrations keys); this
   * only changes which fields are visible/editable.
   */
  facet?: ProviderSettingsFacet;
  /**
   * Optional slot rendered at the bottom of each provider_platform card, keyed by
   * the provider integration key (e.g. "telegramd"). The Providers page uses this
   * to render that provider's RTDH webhook-validation secret INSIDE its card, so a
   * provider's two flows — send (credentials) + receive (RTDH secret) — sit
   * together as one provider's settings instead of in separate sections.
   */
  renderProviderFooter?: (providerKey: string) => React.ReactNode;
}) {
  const showTab = (value: string) => !only || only.includes(value);
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [editingIntegration, setEditingIntegration] = useState<string | null>(
    null,
  );
  const [selectedTab, setSelectedTab] = useState<string | null>(null);
  const [formValues, setFormValues] = useState<Record<string, string>>({});
  const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({});
  const [isJotformEditing, setIsJotformEditing] = useState(false);
  const [jotformApiKeyValue, setJotformApiKeyValue] = useState("");
  const [jotformApiUrlValue, setJotformApiUrlValue] = useState("");
  const [isJotformTeamWorkspaceEditing, setIsJotformTeamWorkspaceEditing] =
    useState(false);
  const [jotformTeamWorkspaceIdValue, setJotformTeamWorkspaceIdValue] =
    useState("");
  const [isJotformDefaultWebhookEditing, setIsJotformDefaultWebhookEditing] =
    useState(false);
  const [jotformDefaultWebhookUrlValue, setJotformDefaultWebhookUrlValue] =
    useState("");
  const [rtdhWebhookSecretValues, setRtdhWebhookSecretValues] = useState<
    Record<string, string>
  >({});
  const [
    { data: platformIntegrations = [], isLoading: platformLoading },
    { data: tenantIntegrations = [], isLoading: tenantLoading },
  ] = useQueries({
    queries: [
      {
        queryKey: ["platform-integrations-active"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("platform_integrations")
            .select("*")
            .eq("is_active", true)
            .order("name");

          if (error) throw error;
          return data as PlatformIntegration[];
        },
      },
      {
        queryKey: ["tenant-integrations", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await supabase
            .from("tenant_integrations")
            .select("*")
            .eq("tenant_id", currentTenantId);

          if (error) throw error;
          return data as TenantIntegration[];
        },
        enabled: !!currentTenantId,
      },
    ],
  });

  const updateSettingsMutation = useMutation({
    mutationFn: async ({
      integrationId,
      settings,
    }: {
      integrationId: string;
      settings: Record<string, unknown>;
    }) => {
      const { error } = await supabase
        .from("tenant_integrations")
        .update({ settings: settings as unknown as Record<string, never> })
        .eq("id", integrationId);

      if (error) throw error;
      return { integrationId, settings };
    },
    onSuccess: ({ integrationId }) => {
      queryClient.invalidateQueries({ queryKey: ["tenant-integrations"] });
      queryClient.invalidateQueries({ queryKey: ["jotform-webhook-status"] });
      logAction({
        action: "update",
        entityType: "tenant_integration",
        entityId: integrationId,
        afterData: { settings_updated: true },
      });
      toast.success("Integration settings saved");
      setEditingIntegration(null);
      setFormValues({});
      setShowSecrets({});
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to save settings",
      );
    },
  });

  const toggleTenantIntegrationMutation = useMutation({
    mutationFn: async ({
      integrationKey,
      enabled,
    }: {
      integrationKey: string;
      enabled: boolean;
    }) => {
      if (!currentTenantId) {
        throw new Error("Tenant context is required");
      }

      const existingIntegration = tenantIntegrations.find(
        (tenantIntegration) =>
          tenantIntegration.integration_key === integrationKey,
      );

      if (existingIntegration) {
        const { error } = await supabase
          .from("tenant_integrations")
          .update({ is_enabled: enabled })
          .eq("id", existingIntegration.id);

        if (error) throw error;
        return {
          integrationId: existingIntegration.id,
          integrationKey,
          enabled,
        };
      }

      const { data, error } = await supabase
        .from("tenant_integrations")
        .insert([
          {
            tenant_id: currentTenantId,
            integration_key: integrationKey,
            is_enabled: enabled,
          },
        ])
        .select("id")
        .single();

      if (error) throw error;
      return { integrationId: data.id, integrationKey, enabled };
    },
    onSuccess: ({ integrationId, integrationKey, enabled }) => {
      queryClient.invalidateQueries({ queryKey: ["tenant-integrations"] });
      logAction({
        action: enabled ? "enable" : "disable",
        entityType: "tenant_integration",
        entityId: integrationId,
        afterData: { integration_key: integrationKey, enabled },
      });

      if (!enabled && editingIntegration === integrationKey) {
        setEditingIntegration(null);
        setFormValues({});
        setShowSecrets({});
      }

      toast.success(`Integration ${enabled ? "enabled" : "disabled"}`);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update integration",
      );
    },
  });

  const saveJotformSettingsMutation = useMutation({
    mutationFn: async ({
      apiKey,
      apiUrl,
    }: {
      apiKey: string;
      apiUrl: string;
    }) => {
      if (!currentTenantId) {
        throw new Error("Tenant context is required");
      }

      const existingIntegration = tenantIntegrations.find(
        (tenantIntegration) =>
          tenantIntegration.integration_key === JOTFORM_INTEGRATION_KEY,
      );
      const existingApiKey = getSettingValue(
        existingIntegration?.settings,
        "api_key",
      );
      const trimmedApiKey = apiKey.trim();
      const resolvedApiKey =
        trimmedApiKey ||
        (typeof existingApiKey === "string" ? existingApiKey.trim() : "");
      const trimmedApiUrl = apiUrl.trim();
      const existingTeamWorkspaceId = getSettingValue(
        existingIntegration?.settings,
        JOTFORM_TEAM_WORKSPACE_ID_SETTING,
      );
      const resolvedTeamWorkspaceId =
        (typeof existingTeamWorkspaceId === "string"
          ? existingTeamWorkspaceId.trim()
          : "") || DEFAULT_JOTFORM_TEAM_WORKSPACE_ID;

      if (!resolvedApiKey) {
        throw new Error("Jotform API Key is required");
      }
      if (!trimmedApiUrl) {
        throw new Error("Jotform API URL is required");
      }

      const nextSettings = {
        ...(existingIntegration?.settings || {}),
        api_key: resolvedApiKey,
        api_url: trimmedApiUrl,
        [JOTFORM_TEAM_WORKSPACE_ID_SETTING]: resolvedTeamWorkspaceId,
      };

      const { data, error } = await supabase
        .from("tenant_integrations")
        .upsert(
          {
            tenant_id: currentTenantId,
            integration_key: JOTFORM_INTEGRATION_KEY,
            is_enabled: true,
            settings: nextSettings as unknown as Record<string, never>,
          },
          { onConflict: "tenant_id,integration_key" },
        )
        .select("id")
        .single();

      if (error) throw error;
      return { integrationId: data.id as string };
    },
    onSuccess: ({ integrationId }) => {
      queryClient.invalidateQueries({ queryKey: ["tenant-integrations"] });
      logAction({
        action: "update",
        entityType: "tenant_integration",
        entityId: integrationId,
        afterData: {
          integration_key: JOTFORM_INTEGRATION_KEY,
          settings_updated: true,
        },
      });
      toast.success("Jotform settings saved");
      setIsJotformEditing(false);
      setJotformApiKeyValue("");
      setJotformApiUrlValue("");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save Jotform settings",
      );
    },
  });

  const saveJotformTeamWorkspaceMutation = useMutation({
    mutationFn: async ({ teamWorkspaceId }: { teamWorkspaceId: string }) => {
      if (!currentTenantId) {
        throw new Error("Tenant context is required");
      }

      const normalizedTeamWorkspaceId = teamWorkspaceId.trim();
      if (!normalizedTeamWorkspaceId) {
        throw new Error("Jotform Team Workspace ID is required");
      }

      const existingIntegration = tenantIntegrations.find(
        (tenantIntegration) =>
          tenantIntegration.integration_key === JOTFORM_INTEGRATION_KEY,
      );

      const nextSettings = {
        ...(existingIntegration?.settings || {}),
        [JOTFORM_TEAM_WORKSPACE_ID_SETTING]: normalizedTeamWorkspaceId,
      };

      const { data, error } = await supabase
        .from("tenant_integrations")
        .upsert(
          {
            tenant_id: currentTenantId,
            integration_key: JOTFORM_INTEGRATION_KEY,
            is_enabled: true,
            settings: nextSettings as unknown as Record<string, never>,
          },
          { onConflict: "tenant_id,integration_key" },
        )
        .select("id")
        .single();

      if (error) throw error;
      return {
        integrationId: data.id as string,
        teamWorkspaceId: normalizedTeamWorkspaceId,
      };
    },
    onSuccess: ({ integrationId }) => {
      queryClient.invalidateQueries({ queryKey: ["tenant-integrations"] });
      logAction({
        action: "update",
        entityType: "tenant_integration",
        entityId: integrationId,
        afterData: {
          integration_key: JOTFORM_INTEGRATION_KEY,
          settings_updated: true,
        },
      });
      toast.success("Jotform Team Workspace ID saved");
      setIsJotformTeamWorkspaceEditing(false);
      setJotformTeamWorkspaceIdValue("");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save Jotform Team Workspace ID",
      );
    },
  });

  const saveJotformDefaultWebhookMutation = useMutation({
    mutationFn: async ({ webhookUrl }: { webhookUrl: string }) => {
      if (!currentTenantId) {
        throw new Error("Tenant context is required");
      }

      const normalizedWebhookUrl = webhookUrl.trim();
      if (normalizedWebhookUrl) {
        let parsedWebhookUrl: URL;
        try {
          parsedWebhookUrl = new URL(normalizedWebhookUrl);
        } catch {
          throw new Error("Default webhook URL must be a valid URL");
        }

        if (!/^https?:$/i.test(parsedWebhookUrl.protocol)) {
          throw new Error("Default webhook URL must use HTTP or HTTPS");
        }
      }

      const existingIntegration = tenantIntegrations.find(
        (tenantIntegration) =>
          tenantIntegration.integration_key === JOTFORM_INTEGRATION_KEY,
      );

      const nextSettings: Record<string, unknown> = {
        ...(existingIntegration?.settings || {}),
      };

      if (normalizedWebhookUrl) {
        nextSettings[JOTFORM_DEFAULT_WEBHOOK_URL_SETTING] =
          normalizedWebhookUrl;
      } else {
        delete nextSettings[JOTFORM_DEFAULT_WEBHOOK_URL_SETTING];
      }

      const { data, error } = await supabase
        .from("tenant_integrations")
        .upsert(
          {
            tenant_id: currentTenantId,
            integration_key: JOTFORM_INTEGRATION_KEY,
            is_enabled: true,
            settings: nextSettings as unknown as Record<string, never>,
          },
          { onConflict: "tenant_id,integration_key" },
        )
        .select("id")
        .single();

      if (error) throw error;
      return {
        integrationId: data.id as string,
        webhookUrl: normalizedWebhookUrl,
      };
    },
    onSuccess: ({ integrationId, webhookUrl }) => {
      queryClient.invalidateQueries({ queryKey: ["tenant-integrations"] });
      logAction({
        action: "update",
        entityType: "tenant_integration",
        entityId: integrationId,
        afterData: {
          integration_key: JOTFORM_INTEGRATION_KEY,
          default_webhook_url: webhookUrl || null,
        },
      });
      toast.success(
        webhookUrl
          ? "Default Jotform webhook URL saved. Sync started for configured Jotforms."
          : "Default Jotform webhook URL reset",
      );
      if (webhookUrl) {
        void syncJotformDefaultWebhooks(integrationId)
          .then(() => {
            queryClient.invalidateQueries({
              queryKey: ["jotform-webhook-status"],
            });
          })
          .catch((error) => {
            toast.error(
              error instanceof Error
                ? error.message
                : "Failed to sync configured Jotform webhooks",
            );
          });
      }
      setIsJotformDefaultWebhookEditing(false);
      setJotformDefaultWebhookUrlValue("");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save default Jotform webhook URL",
      );
    },
  });

  const getTenantIntegration = (
    integrationKey: string,
  ): TenantIntegration | undefined => {
    return tenantIntegrations.find(
      (tenantIntegration) =>
        tenantIntegration.integration_key === integrationKey,
    );
  };

  const handleStartEdit = (
    platformIntegration: PlatformIntegration,
    tenantIntegration: TenantIntegration,
  ) => {
    const isProviderPlatform =
      platformIntegration.category === "provider_platform";
    const settingDefinitions = filterProviderSettingsByFacet(
      getIntegrationSettingDefinitions(
        platformIntegration.key,
        platformIntegration.required_settings,
      ),
      isProviderPlatform ? facet : "all",
    );
    const initialValues: Record<string, string> = {};

    settingDefinitions.forEach((setting) => {
      if (!setting.sensitive) {
        initialValues[setting.key] = getSettingInputValue(
          tenantIntegration.settings,
          setting,
        );
      } else {
        initialValues[setting.key] = "";
      }
    });

    // The Jotform form id belongs to the questionnaire facet; only seed it when
    // that facet is visible so a credentials-only edit never touches it.
    if (isProviderPlatform && facet !== "credentials") {
      initialValues[JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING] =
        getSettingInputValue(
          tenantIntegration.settings,
          PATIENT_QUESTIONNAIRE_JOTFORM_ID_SETTING_DEFINITION,
        );
    }

    setFormValues(initialValues);
    setEditingIntegration(platformIntegration.key);
  };

  const handleSaveSettings = async (
    platformIntegration: PlatformIntegration,
    tenantIntegration: TenantIntegration,
  ) => {
    const isProviderPlatform =
      platformIntegration.category === "provider_platform";
    // Only validate/persist the settings this facet actually edits; everything
    // else is carried over untouched via the `...tenantIntegration.settings`
    // spread below, so saving credentials never drops the questionnaire (or v.v.).
    const settingDefinitions = filterProviderSettingsByFacet(
      getIntegrationSettingDefinitions(
        platformIntegration.key,
        platformIntegration.required_settings,
      ),
      isProviderPlatform ? facet : "all",
    );

    const missingRequiredSetting = settingDefinitions.find((setting) => {
      if (setting.required === false) return false;

      const nextValue = formValues[setting.key]?.trim();
      const existingValue = hasSettingValue(
        tenantIntegration.settings,
        setting.key,
      );

      return !nextValue && !existingValue;
    });

    if (missingRequiredSetting) {
      toast.error(`${missingRequiredSetting.label} is required`);
      return;
    }

    try {
      const nextSettings: Record<string, unknown> = {
        ...(tenantIntegration.settings || {}),
      };
      const replicatedSecretKeys = getRtdhProviderSecretKeys(
        platformIntegration.key,
        settingDefinitions,
      );
      const secretsToReplicate: Record<string, string> = {};

      settingDefinitions.forEach((setting) => {
        const nextValue = formValues[setting.key]?.trim();
        const existingValue = getSettingValue(
          tenantIntegration.settings,
          setting.key,
        );

        if (setting.inputType === "json") {
          if (!nextValue) {
            delete nextSettings[setting.key];
            return;
          }

          let parsedValue: unknown;
          try {
            parsedValue = JSON.parse(nextValue);
          } catch {
            throw new Error(`${setting.label} must be valid JSON`);
          }

          if (
            !parsedValue ||
            typeof parsedValue !== "object" ||
            Array.isArray(parsedValue)
          ) {
            throw new Error(`${setting.label} must be a JSON object`);
          }

          nextSettings[setting.key] = parsedValue;
          return;
        }

        if (setting.sensitive) {
          if (nextValue) {
            nextSettings[setting.key] = nextValue;
          } else if (
            typeof existingValue === "string" &&
            existingValue.trim().length > 0
          ) {
            nextSettings[setting.key] = existingValue;
          } else {
            delete nextSettings[setting.key];
          }
          if (
            nextValue &&
            replicatedSecretKeys.has(setting.key) &&
            nextValue !==
              (typeof existingValue === "string" ? existingValue.trim() : "")
          ) {
            secretsToReplicate[setting.key] = nextValue;
          }
          return;
        }

        if (nextValue) {
          nextSettings[setting.key] = nextValue;
        } else {
          delete nextSettings[setting.key];
        }

        if (
          nextValue &&
          replicatedSecretKeys.has(setting.key) &&
          nextValue !==
            (typeof existingValue === "string" ? existingValue.trim() : "")
        ) {
          secretsToReplicate[setting.key] = nextValue;
        }
      });

      if (isProviderPlatform && facet !== "credentials") {
        const normalizedFormId =
          formValues[JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING]?.trim() ||
          "";
        const existingFormId = getSettingInputValue(
          tenantIntegration.settings,
          PATIENT_QUESTIONNAIRE_JOTFORM_ID_SETTING_DEFINITION,
        ).trim();

        if (normalizedFormId.length > 128) {
          throw new Error(
            "Patient questionnaire form ID must be 128 characters or less",
          );
        }

        if (normalizedFormId) {
          const formIdToSave =
            normalizedFormId === existingFormId
              ? normalizedFormId
              : await validateJotformQuestionnaireForm({
                  tenantIntegrationId: tenantIntegration.id,
                  formId: normalizedFormId,
                });
          nextSettings[JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING] =
            formIdToSave;
        } else {
          delete nextSettings[JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING];
        }
      }

      const tenantIdForRtdh = currentTenantId || tenantIntegration.tenant_id;
      if (tenantIdForRtdh) {
        await replicateProviderSecretsToRtdh({
          tenantId: tenantIdForRtdh,
          provider: platformIntegration.key,
          secrets: secretsToReplicate,
        });
      }

      updateSettingsMutation.mutate({
        integrationId: tenantIntegration.id,
        settings: nextSettings,
      });
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : "Invalid integration settings",
      );
    }
  };

  const handleCancelEdit = () => {
    setEditingIntegration(null);
    setFormValues({});
    setShowSecrets({});
  };

  const handleSaveJotformSettings = () => {
    saveJotformSettingsMutation.mutate({
      apiKey: jotformApiKeyValue,
      apiUrl: jotformApiUrlValue,
    });
  };

  const handleSaveJotformTeamWorkspace = () => {
    saveJotformTeamWorkspaceMutation.mutate({
      teamWorkspaceId: jotformTeamWorkspaceIdValue,
    });
  };

  const handleSaveJotformDefaultWebhook = () => {
    saveJotformDefaultWebhookMutation.mutate({
      webhookUrl: jotformDefaultWebhookUrlValue,
    });
  };

  const handleSaveRtdhWebhookSecret = async (
    integrationKey: string,
    tenantIntegration?: TenantIntegration | null,
  ) => {
    const tenantIdForRtdh = currentTenantId || tenantIntegration?.tenant_id;
    const value = rtdhWebhookSecretValues[integrationKey]?.trim() || "";
    if (!tenantIdForRtdh) {
      toast.error("Select a tenant before saving secrets");
      return;
    }
    if (value.length < 8) {
      toast.error("Secret must be at least 8 characters");
      return;
    }

    try {
      await replicateProviderSecretsToRtdh({
        tenantId: tenantIdForRtdh,
        provider: integrationKey,
        secrets: { webhook_secret: value },
      });
      toast.success(
        `${RTDH_WEBHOOK_SECRET_INTEGRATION_LABELS[integrationKey]} RTDH secret updated`,
      );
      setRtdhWebhookSecretValues((currentValues) => ({
        ...currentValues,
        [integrationKey]: "",
      }));
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : "Failed to update RTDH secret",
      );
    }
  };

  const isLoading = platformLoading || tenantLoading;

  const getIntegrationsForCategory = (category: string) =>
    platformIntegrations.filter((platformIntegration) => {
      if (platformIntegration.category !== category) {
        return false;
      }

      // Provider platforms and tenant-managed categories always render their
      // card (with an Enable/Disable toggle) so an admin can connect an
      // integration that has no tenant_integrations row yet. Without this, the
      // card only appeared once a row already existed and was enabled, so a
      // fresh tenant had no way to add e.g. Resend from the UI.
      if (category === "provider_platform" || isTogglableCategory(category)) {
        return true;
      }

      return getTenantIntegration(platformIntegration.key)?.is_enabled;
    });

  const providerIntegrations = getIntegrationsForCategory("provider_platform");
  const jotformTenantIntegration = getTenantIntegration(
    JOTFORM_INTEGRATION_KEY,
  );
  const jotformApiUrlValueFromSettings =
    typeof getSettingValue(jotformTenantIntegration?.settings, "api_url") ===
    "string"
      ? String(
          getSettingValue(jotformTenantIntegration?.settings, "api_url"),
        ).trim()
      : "";
  const jotformDefaultWebhookUrlValueFromSettings =
    typeof getSettingValue(
      jotformTenantIntegration?.settings,
      JOTFORM_DEFAULT_WEBHOOK_URL_SETTING,
    ) === "string"
      ? String(
          getSettingValue(
            jotformTenantIntegration?.settings,
            JOTFORM_DEFAULT_WEBHOOK_URL_SETTING,
          ),
        ).trim()
      : "";
  const emailDistributionIntegrations =
    getIntegrationsForCategory("email_distribution");
  const customerSupportIntegrations =
    getIntegrationsForCategory("customer_support");
  const pushNotificationIntegrations =
    getIntegrationsForCategory("push_notifications");
  const additionalCategoryTabs = integrationCategoryOrder
    .filter(
      (category) =>
        ![
          "provider_platform",
          "forms",
          "email_distribution",
          "customer_support",
          "push_notifications",
        ].includes(category),
    )
    .map((category) => ({
      value: category,
      label: integrationCategoryLabels[category] || category,
      description:
        integrationCategoryDescriptions[category] ||
        "Manage integration settings.",
      integrations: getIntegrationsForCategory(category),
    }))
    .filter((section) => section.integrations.length > 0);

  const renderEmptyState = (
    title: string,
    description: string,
    message: string,
  ) => (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
            <Plug className="h-5 w-5 text-primary" />
          </div>
          <div>
            <CardTitle>{title}</CardTitle>
            <CardDescription>{description}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <p className="py-8 text-center text-muted-foreground">{message}</p>
      </CardContent>
    </Card>
  );

  const renderPatientQuestionnaireJotformIdField = (
    platformIntegration: PlatformIntegration,
    tenantIntegration: TenantIntegration,
  ) => {
    if (platformIntegration.category !== "provider_platform") return null;

    const storedProviderPatientQuestionnaireFormId = getSettingValue(
      tenantIntegration.settings,
      JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING,
    );
    const providerPatientQuestionnaireFormId =
      typeof storedProviderPatientQuestionnaireFormId === "string"
        ? storedProviderPatientQuestionnaireFormId.trim()
        : "";
    const inputId = `provider-patient-questionnaire-jotform-id-${tenantIntegration.id}`;
    const isProviderEditing = editingIntegration === platformIntegration.key;
    const displayedFormId = isProviderEditing
      ? (formValues[JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING] ??
        providerPatientQuestionnaireFormId)
      : providerPatientQuestionnaireFormId;
    const normalizedDisplayedFormId = displayedFormId.trim();
    return (
      <div className="space-y-2 rounded-md border p-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <Label htmlFor={inputId}>Patient Questionnaire Jotform ID</Label>
              <JotformHiddenFieldsHelp questionnaireType="medical_questionnaire" />
              {normalizedDisplayedFormId ? (
                <JotformWebhookStatusControl
                  tenantIntegrationId={tenantIntegration.id}
                  formId={normalizedDisplayedFormId}
                  defaultWebhookUrl={
                    jotformDefaultWebhookUrlValueFromSettings || null
                  }
                  apiUrl={jotformApiUrlValueFromSettings}
                  previewLabel={`Preview ${platformIntegration.name} patient questionnaire in Jotform`}
                  editLabel={`Edit ${platformIntegration.name} patient questionnaire in Jotform`}
                  showActions={false}
                />
              ) : !jotformDefaultWebhookUrlValueFromSettings ? (
                <JotformDefaultWebhookWarning />
              ) : null}
            </div>
            <p className="text-xs text-muted-foreground">
              If not defined, the patient questionnaire will fallback to legacy
              implementation.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
          <Input
            id={inputId}
            value={displayedFormId}
            onChange={(event) =>
              setFormValues((currentValues) => ({
                ...currentValues,
                [JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING]:
                  event.target.value,
              }))
            }
            maxLength={128}
            disabled={!isProviderEditing || updateSettingsMutation.isPending}
            placeholder={
              isProviderEditing ? "Enter the Jotform form ID" : "Not configured"
            }
          />
          {normalizedDisplayedFormId ? (
            <JotformWebhookStatusControl
              tenantIntegrationId={tenantIntegration.id}
              formId={normalizedDisplayedFormId}
              defaultWebhookUrl={
                jotformDefaultWebhookUrlValueFromSettings || null
              }
              apiUrl={jotformApiUrlValueFromSettings}
              previewLabel={`Preview ${platformIntegration.name} patient questionnaire in Jotform`}
              editLabel={`Edit ${platformIntegration.name} patient questionnaire in Jotform`}
              showStatus={false}
              reserveActionSlots
            />
          ) : null}
        </div>
      </div>
    );
  };

  const renderRtdhWebhookSecretSection = (
    platformIntegration: PlatformIntegration,
    tenantIntegration?: TenantIntegration | null,
  ) => {
    const label =
      RTDH_WEBHOOK_SECRET_INTEGRATION_LABELS[platformIntegration.key];
    if (!label) return null;

    const value = rtdhWebhookSecretValues[platformIntegration.key] || "";
    return (
      <div className="space-y-3 rounded-md border p-3">
        <div className="space-y-1">
          <p className="flex items-center gap-2 text-sm font-medium">
            <Webhook className="h-4 w-4 text-emerald-600" />
            RTDH validation secret
          </p>
          <p className="text-sm text-muted-foreground">
            Secret RTDH uses to verify inbound {label} webhook events.
          </p>
        </div>
        <div className="space-y-2">
          <Label htmlFor={`${platformIntegration.key}-rtdh-webhook-secret`}>
            New secret value
          </Label>
          <Input
            id={`${platformIntegration.key}-rtdh-webhook-secret`}
            type="password"
            value={value}
            onChange={(event) =>
              setRtdhWebhookSecretValues((currentValues) => ({
                ...currentValues,
                [platformIntegration.key]: event.target.value,
              }))
            }
            placeholder="Paste or generate a new secret"
            className="font-mono text-xs"
            autoComplete="new-password"
          />
        </div>
        <div className="flex items-center gap-2">
          <Button
            type="button"
            size="sm"
            onClick={() =>
              handleSaveRtdhWebhookSecret(
                platformIntegration.key,
                tenantIntegration,
              )
            }
            disabled={
              value.trim().length < 8 ||
              !(currentTenantId || tenantIntegration?.tenant_id)
            }
          >
            Set / rotate secret
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => {
              const bytes = new Uint8Array(32);
              crypto.getRandomValues(bytes);
              setRtdhWebhookSecretValues((currentValues) => ({
                ...currentValues,
                [platformIntegration.key]: Array.from(bytes)
                  .map((byte) => byte.toString(16).padStart(2, "0"))
                  .join(""),
              }));
            }}
          >
            Generate
          </Button>
        </div>
      </div>
    );
  };

  const renderIntegrationCards = (
    integrations: PlatformIntegration[],
    emptyState: {
      title: string;
      description: string;
      message: string;
    },
  ) => {
    if (isLoading) {
      return (
        <Card>
          <CardContent className="flex items-center justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
          </CardContent>
        </Card>
      );
    }

    if (integrations.length === 0) {
      return renderEmptyState(
        emptyState.title,
        emptyState.description,
        emptyState.message,
      );
    }

    return (
      <div className="space-y-6">
        {integrations.map((platformIntegration) => {
          const tenantIntegration = getTenantIntegration(
            platformIntegration.key,
          );
          const isEnabled = tenantIntegration?.is_enabled ?? false;
          const isProviderPlatform =
            platformIntegration.category === "provider_platform";
          // Categories that get an Enable/Disable toggle so an admin can connect
          // them per tenant (provider platforms + tenant-managed connections).
          // The questionnaire facet never owns provider enablement — that lives on
          // the Providers page — so it hides the toggle and the creds-based badge.
          const showEnableToggle =
            (isProviderPlatform && facet !== "questionnaire") ||
            isTogglableCategory(platformIntegration.category);
          const showConfiguredBadge =
            !isProviderPlatform || facet !== "questionnaire";
          const requiredSettings = getRequiredSettingsForIntegration(
            platformIntegration.key,
            platformIntegration.required_settings,
          );
          const settingDefinitions = getIntegrationSettingDefinitions(
            platformIntegration.key,
            platformIntegration.required_settings,
          );
          // For provider platforms, split the settings by `facet` so the provider
          // connection (credentials) and the patient questionnaire (definition +
          // Jotform) can live on separate pages. Non-provider categories ignore
          // the facet entirely.
          const visibleSettingDefinitions = isProviderPlatform
            ? filterProviderSettingsByFacet(settingDefinitions, facet)
            : settingDefinitions;
          // The Jotform-ID + webhook block belongs to the questionnaire facet.
          const showQuestionnaireExtras =
            isProviderPlatform && facet !== "credentials";
          const isConfigured = hasConfiguredRequiredSettings(
            tenantIntegration?.settings,
            requiredSettings,
            platformIntegration.key,
          );
          const isEditing =
            editingIntegration === platformIntegration.key &&
            isEnabled &&
            !!tenantIntegration;

          return (
            <Card key={platformIntegration.id}>
              <CardHeader>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                      {integrationIcons[platformIntegration.key] || (
                        <Plug className="h-5 w-5 text-primary" />
                      )}
                    </div>
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <CardTitle className="text-lg">
                          {platformIntegration.name}
                        </CardTitle>
                        {showConfiguredBadge ? (
                          isEnabled ? (
                            <Badge variant="default">Enabled</Badge>
                          ) : (
                            <Badge variant="secondary">Disabled</Badge>
                          )
                        ) : null}
                        {showConfiguredBadge && isEnabled ? (
                          isConfigured ? (
                            <Badge variant="default" className="bg-green-600">
                              <CheckCircle2 className="mr-1 h-3 w-3" />
                              Configured
                            </Badge>
                          ) : (
                            <Badge variant="secondary">
                              <Lock className="mr-1 h-3 w-3" />
                              Needs Configuration
                            </Badge>
                          )
                        ) : null}
                      </div>
                      <CardDescription>
                        {platformIntegration.description}
                      </CardDescription>
                    </div>
                  </div>

                  {showEnableToggle ? (
                    <Button
                      variant={isEnabled ? "outline" : "default"}
                      onClick={() =>
                        toggleTenantIntegrationMutation.mutate({
                          integrationKey: platformIntegration.key,
                          enabled: !isEnabled,
                        })
                      }
                      disabled={toggleTenantIntegrationMutation.isPending}
                    >
                      {toggleTenantIntegrationMutation.isPending ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      ) : isEnabled ? (
                        <PowerOff className="mr-2 h-4 w-4" />
                      ) : (
                        <Power className="mr-2 h-4 w-4" />
                      )}
                      {isEnabled ? "Disable" : "Enable"}
                    </Button>
                  ) : null}
                </div>
              </CardHeader>

              <CardContent>
                {!isEnabled ? (
                  <div className="space-y-4">
                    <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                      Enable this integration to configure your tenant-specific
                      credentials and endpoint settings.
                    </div>
                    {renderRtdhWebhookSecretSection(
                      platformIntegration,
                      tenantIntegration,
                    )}
                  </div>
                ) : isEditing && tenantIntegration ? (
                  <div className="space-y-4">
                    {visibleSettingDefinitions.map((setting) => {
                      const showValue = showSecrets[setting.key];

                      return (
                        <div key={setting.key} className="space-y-4">
                          <div className="space-y-2">
                            <Label
                              htmlFor={`${platformIntegration.key}-${setting.key}`}
                            >
                              {setting.label}
                              {setting.required === false ? " (Optional)" : ""}
                            </Label>
                            {setting.inputType === "json" ? (
                              <Textarea
                                id={`${platformIntegration.key}-${setting.key}`}
                                placeholder={setting.placeholder}
                                value={formValues[setting.key] || ""}
                                rows={setting.rows || 10}
                                onChange={(event) =>
                                  setFormValues((currentValues) => ({
                                    ...currentValues,
                                    [setting.key]: event.target.value,
                                  }))
                                }
                                className="font-mono text-xs"
                              />
                            ) : (
                              <div className="flex gap-2">
                                <div className="relative flex-1">
                                  <Input
                                    id={`${platformIntegration.key}-${setting.key}`}
                                    type={
                                      setting.sensitive && !showValue
                                        ? "password"
                                        : setting.inputType || "text"
                                    }
                                    placeholder={
                                      setting.sensitive &&
                                      hasSettingValue(
                                        tenantIntegration.settings,
                                        setting.key,
                                      )
                                        ? "Leave blank to keep existing"
                                        : setting.placeholder
                                    }
                                    value={formValues[setting.key] || ""}
                                    onChange={(event) =>
                                      setFormValues((currentValues) => ({
                                        ...currentValues,
                                        [setting.key]: event.target.value,
                                      }))
                                    }
                                    className={setting.sensitive ? "pr-10" : ""}
                                  />
                                  {setting.sensitive ? (
                                    <Button
                                      type="button"
                                      variant="ghost"
                                      size="sm"
                                      className="absolute right-0 top-0 h-full px-3"
                                      onClick={() =>
                                        setShowSecrets((currentValues) => ({
                                          ...currentValues,
                                          [setting.key]:
                                            !currentValues[setting.key],
                                        }))
                                      }
                                    >
                                      {showValue ? (
                                        <EyeOff className="h-4 w-4" />
                                      ) : (
                                        <Eye className="h-4 w-4" />
                                      )}
                                    </Button>
                                  ) : null}
                                </div>
                              </div>
                            )}
                            {setting.description ? (
                              <p className="text-xs text-muted-foreground">
                                {setting.description}
                              </p>
                            ) : null}
                          </div>
                          {setting.key === "patient_questionnaire_definition" &&
                          showQuestionnaireExtras
                            ? renderPatientQuestionnaireJotformIdField(
                                platformIntegration,
                                tenantIntegration,
                              )
                            : null}
                        </div>
                      );
                    })}
                    <div className="flex gap-2 pt-2">
                      <Button
                        onClick={() =>
                          handleSaveSettings(
                            platformIntegration,
                            tenantIntegration,
                          )
                        }
                        disabled={updateSettingsMutation.isPending}
                      >
                        {updateSettingsMutation.isPending ? (
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : null}
                        Save Settings
                      </Button>
                      <Button variant="outline" onClick={handleCancelEdit}>
                        Cancel
                      </Button>
                    </div>
                    {renderRtdhWebhookSecretSection(
                      platformIntegration,
                      tenantIntegration,
                    )}
                  </div>
                ) : tenantIntegration ? (
                  <div className="space-y-4">
                    {visibleSettingDefinitions.map((setting) => {
                      const currentValue = getSettingInputValue(
                        tenantIntegration.settings,
                        setting,
                      );

                      return (
                        <div key={setting.key} className="space-y-4">
                          <div className="space-y-2">
                            <Label>
                              {setting.label}
                              {setting.required === false ? " (Optional)" : ""}
                            </Label>
                            {setting.inputType === "json" ? (
                              <Textarea
                                value={currentValue}
                                disabled
                                rows={Math.max(setting.rows || 10, 6)}
                                placeholder="Not configured"
                                className="font-mono text-xs"
                              />
                            ) : (
                              <Input
                                type={
                                  setting.sensitive
                                    ? "password"
                                    : setting.inputType || "text"
                                }
                                value={
                                  currentValue
                                    ? setting.sensitive
                                      ? "••••••••••••••••"
                                      : currentValue
                                    : ""
                                }
                                disabled
                                placeholder={
                                  currentValue ? "" : "Not configured"
                                }
                              />
                            )}
                          </div>
                          {setting.key === "patient_questionnaire_definition" &&
                          showQuestionnaireExtras
                            ? renderPatientQuestionnaireJotformIdField(
                                platformIntegration,
                                tenantIntegration,
                              )
                            : null}
                        </div>
                      );
                    })}
                    <Button
                      variant={isConfigured ? "outline" : "default"}
                      onClick={() =>
                        handleStartEdit(platformIntegration, tenantIntegration)
                      }
                    >
                      {isConfigured
                        ? "Update Settings"
                        : "Configure Integration"}
                    </Button>
                    {renderRtdhWebhookSecretSection(
                      platformIntegration,
                      tenantIntegration,
                    )}
                  </div>
                ) : null}
                {isProviderPlatform && renderProviderFooter ? (
                  <div className="mt-6 border-t pt-6">
                    {renderProviderFooter(platformIntegration.key)}
                  </div>
                ) : null}
              </CardContent>
            </Card>
          );
        })}
      </div>
    );
  };

  const renderFormsTab = () => {
    const tenantIntegration = getTenantIntegration(JOTFORM_INTEGRATION_KEY);
    const platformIntegration = platformIntegrations.find(
      (integration) => integration.key === JOTFORM_INTEGRATION_KEY,
    );
    const storedApiKey = getSettingValue(
      tenantIntegration?.settings,
      "api_key",
    );
    const storedApiUrl = getSettingValue(
      tenantIntegration?.settings,
      "api_url",
    );
    const storedTeamWorkspaceId = getSettingValue(
      tenantIntegration?.settings,
      JOTFORM_TEAM_WORKSPACE_ID_SETTING,
    );
    const storedDefaultWebhookUrl = getSettingValue(
      tenantIntegration?.settings,
      JOTFORM_DEFAULT_WEBHOOK_URL_SETTING,
    );
    const teamWorkspaceId =
      typeof storedTeamWorkspaceId === "string"
        ? storedTeamWorkspaceId.trim()
        : "";
    const defaultWebhookUrl =
      typeof storedDefaultWebhookUrl === "string" &&
      storedDefaultWebhookUrl.trim()
        ? storedDefaultWebhookUrl.trim()
        : "";
    const hasApiKey =
      tenantIntegration?.is_enabled === true &&
      typeof storedApiKey === "string" &&
      storedApiKey.trim().length > 0;
    const hasApiUrl =
      tenantIntegration?.is_enabled === true &&
      typeof storedApiUrl === "string" &&
      storedApiUrl.trim().length > 0;
    const isConfigured = hasApiKey && hasApiUrl;
    const isBusy = saveJotformSettingsMutation.isPending;
    const isTeamWorkspaceBusy = saveJotformTeamWorkspaceMutation.isPending;
    const isDefaultWebhookBusy = saveJotformDefaultWebhookMutation.isPending;

    if (isLoading) {
      return (
        <Card>
          <CardContent className="flex items-center justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
          </CardContent>
        </Card>
      );
    }

    return (
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                <FileText className="h-5 w-5 text-primary" />
              </div>
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <CardTitle className="text-lg">
                    {platformIntegration?.name || "Jotform"}
                  </CardTitle>
                  {isConfigured ? (
                    <Badge variant="default" className="bg-green-600">
                      <CheckCircle2 className="mr-1 h-3 w-3" />
                      Configured
                    </Badge>
                  ) : (
                    <Badge variant="secondary">
                      <Lock className="mr-1 h-3 w-3" />
                      Not Configured
                    </Badge>
                  )}
                </div>
                <CardDescription>
                  {platformIntegration?.description ||
                    "Forms integration used to retrieve Jotform submissions."}
                </CardDescription>
              </div>
            </div>
          </div>
        </CardHeader>

        <CardContent className="space-y-6">
          <div className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <h4 className="text-sm font-medium">API Credentials</h4>
                <p className="text-xs text-muted-foreground">
                  Configure API URL and API Key used for Jotform requests.
                </p>
              </div>

              {isJotformEditing ? (
                <div className="flex flex-wrap gap-2">
                  <Button onClick={handleSaveJotformSettings} disabled={isBusy}>
                    {saveJotformSettingsMutation.isPending ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                      <Save className="mr-2 h-4 w-4" />
                    )}
                    Save Settings
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => {
                      setIsJotformEditing(false);
                      setJotformApiKeyValue("");
                      setJotformApiUrlValue("");
                    }}
                    disabled={isBusy}
                  >
                    Cancel
                  </Button>
                </div>
              ) : (
                <Button
                  variant={isConfigured ? "outline" : "default"}
                  onClick={() => {
                    setJotformApiKeyValue("");
                    setJotformApiUrlValue(
                      typeof storedApiUrl === "string" ? storedApiUrl : "",
                    );
                    setIsJotformEditing(true);
                  }}
                  disabled={isBusy}
                >
                  {isConfigured ? (
                    <Pencil className="mr-2 h-4 w-4" />
                  ) : (
                    <Plus className="mr-2 h-4 w-4" />
                  )}
                  {isConfigured ? "Edit Settings" : "Add Settings"}
                </Button>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="jotform-api-url">API URL</Label>
              {isJotformEditing ? (
                <Input
                  id="jotform-api-url"
                  type="url"
                  placeholder="https://api.jotform.com"
                  value={jotformApiUrlValue}
                  onChange={(event) =>
                    setJotformApiUrlValue(event.target.value)
                  }
                />
              ) : (
                <Input
                  id="jotform-api-url"
                  type="url"
                  value={typeof storedApiUrl === "string" ? storedApiUrl : ""}
                  disabled
                  placeholder="Not configured"
                />
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="jotform-api-key">API Key</Label>
              {isJotformEditing ? (
                <Input
                  id="jotform-api-key"
                  type="password"
                  placeholder={
                    hasApiKey
                      ? "Enter a new Jotform API key"
                      : "Enter the Jotform API key"
                  }
                  value={jotformApiKeyValue}
                  onChange={(event) =>
                    setJotformApiKeyValue(event.target.value)
                  }
                  autoComplete="off"
                />
              ) : (
                <Input
                  id="jotform-api-key"
                  type="password"
                  value={hasApiKey ? "••••••••••••••••" : ""}
                  disabled
                  placeholder="Not configured"
                />
              )}
            </div>
          </div>

          {platformIntegration
            ? renderRtdhWebhookSecretSection(platformIntegration, tenantIntegration)
            : null}

          <div className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Label htmlFor="jotform-team-workspace-id">
                    Team Workspace ID
                  </Label>
                  <JotformTeamWorkspaceHelp />
                </div>
                <p className="text-xs text-muted-foreground">
                  Keep this visible for quick verification and edit it
                  independently.
                </p>
              </div>

              {isJotformTeamWorkspaceEditing ? (
                <div className="flex flex-wrap gap-2">
                  <Button
                    onClick={handleSaveJotformTeamWorkspace}
                    disabled={isTeamWorkspaceBusy}
                  >
                    {isTeamWorkspaceBusy ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                      <Save className="mr-2 h-4 w-4" />
                    )}
                    Save Team Workspace ID
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => {
                      setIsJotformTeamWorkspaceEditing(false);
                      setJotformTeamWorkspaceIdValue("");
                    }}
                    disabled={isTeamWorkspaceBusy}
                  >
                    Cancel
                  </Button>
                </div>
              ) : (
                <Button
                  variant={teamWorkspaceId ? "outline" : "default"}
                  onClick={() => {
                    setJotformTeamWorkspaceIdValue(
                      teamWorkspaceId || DEFAULT_JOTFORM_TEAM_WORKSPACE_ID,
                    );
                    setIsJotformTeamWorkspaceEditing(true);
                  }}
                  disabled={isTeamWorkspaceBusy}
                >
                  {teamWorkspaceId ? (
                    <Pencil className="mr-2 h-4 w-4" />
                  ) : (
                    <Plus className="mr-2 h-4 w-4" />
                  )}
                  {teamWorkspaceId
                    ? "Edit Team Workspace ID"
                    : "Add Team Workspace ID"}
                </Button>
              )}
            </div>

            {isJotformTeamWorkspaceEditing ? (
              <Input
                id="jotform-team-workspace-id"
                type="text"
                placeholder={DEFAULT_JOTFORM_TEAM_WORKSPACE_ID}
                value={jotformTeamWorkspaceIdValue}
                onChange={(event) =>
                  setJotformTeamWorkspaceIdValue(event.target.value)
                }
                disabled={isTeamWorkspaceBusy}
              />
            ) : (
              <Input
                id="jotform-team-workspace-id"
                type="text"
                value={teamWorkspaceId}
                disabled
                placeholder="Not configured"
              />
            )}
          </div>

          <div className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Label htmlFor="jotform-default-webhook-url">
                    Default Webhook URL
                  </Label>
                  <Webhook className="h-4 w-4 text-muted-foreground" />
                </div>
                <p className="text-xs text-muted-foreground">
                  Changing this field starts a backend sync that adds the new
                  webhook URL to all currently configured Jotforms without
                  removing existing webhook URLs.
                </p>
                {!defaultWebhookUrl ? (
                  <div className="flex items-center gap-2 text-xs text-amber-700">
                    <JotformDefaultWebhookWarning />
                    <span>
                      Webhook validation and checks are suspended until a
                      Default Webhook URL is configured.
                    </span>
                  </div>
                ) : null}
              </div>

              {isJotformDefaultWebhookEditing ? (
                <div className="flex flex-wrap gap-2">
                  <Button
                    onClick={handleSaveJotformDefaultWebhook}
                    disabled={isDefaultWebhookBusy}
                  >
                    {isDefaultWebhookBusy ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                      <Save className="mr-2 h-4 w-4" />
                    )}
                    Save Default Webhook
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => {
                      setIsJotformDefaultWebhookEditing(false);
                      setJotformDefaultWebhookUrlValue("");
                    }}
                    disabled={isDefaultWebhookBusy}
                  >
                    Cancel
                  </Button>
                </div>
              ) : (
                <Button
                  variant={storedDefaultWebhookUrl ? "outline" : "default"}
                  onClick={() => {
                    setJotformDefaultWebhookUrlValue(defaultWebhookUrl);
                    setIsJotformDefaultWebhookEditing(true);
                  }}
                  disabled={isDefaultWebhookBusy}
                >
                  {storedDefaultWebhookUrl ? (
                    <Pencil className="mr-2 h-4 w-4" />
                  ) : (
                    <Plus className="mr-2 h-4 w-4" />
                  )}
                  {storedDefaultWebhookUrl
                    ? "Edit Default Webhook"
                    : "Add Default Webhook"}
                </Button>
              )}
            </div>

            {isJotformDefaultWebhookEditing ? (
              <Input
                id="jotform-default-webhook-url"
                type="url"
                placeholder={`Example: ${DEFAULT_JOTFORM_WEBHOOK_URL}`}
                value={jotformDefaultWebhookUrlValue}
                onChange={(event) =>
                  setJotformDefaultWebhookUrlValue(event.target.value)
                }
                disabled={isDefaultWebhookBusy}
              />
            ) : (
              <Input
                id="jotform-default-webhook-url"
                type="url"
                value={defaultWebhookUrl}
                disabled
                placeholder="Not configured"
              />
            )}
          </div>
        </CardContent>
      </Card>
    );
  };

  const availableTabValues = [
    "providers",
    "forms",
    "payment-providers",
    "email-distribution",
    "customer-support",
    "push-notifications",
    ...additionalCategoryTabs.map((section) => section.value),
  ].filter(showTab);
  const initialTab = availableTabValues.includes(defaultTab)
    ? defaultTab
    : (availableTabValues[0] ?? "providers");
  const hideTabBar = availableTabValues.length <= 1;
  const visibleAdditionalTabs = additionalCategoryTabs.filter((section) =>
    showTab(section.value),
  );

  const activeTab =
    selectedTab && availableTabValues.includes(selectedTab)
      ? selectedTab
      : initialTab;

  return (
    <Tabs
      value={activeTab}
      onValueChange={setSelectedTab}
      className="space-y-6"
    >
      {!hideTabBar && (
        <TabsList className="flex h-auto w-full flex-wrap justify-start gap-2 rounded-xl bg-muted/50 p-1">
          {showTab("providers") && (
            <TabsTrigger value="providers" className="gap-2">
              <Building2 className="h-4 w-4" />
              Providers
            </TabsTrigger>
          )}
          {showTab("forms") && (
            <TabsTrigger value="forms" className="gap-2">
              <FileText className="h-4 w-4" />
              Forms
            </TabsTrigger>
          )}
          {showTab("payment-providers") && (
            <TabsTrigger value="payment-providers" className="gap-2">
              <CreditCard className="h-4 w-4" />
              Payment Providers
            </TabsTrigger>
          )}
          {showTab("email-distribution") && (
            <TabsTrigger value="email-distribution" className="gap-2">
              <Mail className="h-4 w-4" />
              Email Distribution
            </TabsTrigger>
          )}
          {showTab("customer-support") && (
            <TabsTrigger value="customer-support" className="gap-2">
              <Headset className="h-4 w-4" />
              Customer Support
            </TabsTrigger>
          )}
          {showTab("push-notifications") && (
            <TabsTrigger value="push-notifications" className="gap-2">
              <Bell className="h-4 w-4" />
              Push Notifications
            </TabsTrigger>
          )}
          {visibleAdditionalTabs.map((section) => (
            <TabsTrigger
              key={section.value}
              value={section.value}
              className="gap-2"
            >
              <Plug className="h-4 w-4" />
              {section.label}
            </TabsTrigger>
          ))}
        </TabsList>
      )}

      {showTab("providers") && (
        <TabsContent value="providers" className="space-y-6">
          {renderIntegrationCards(providerIntegrations, {
            title: "Providers",
            description:
              "Configure provider platform integrations used for clinical workflows.",
            message:
              "No provider integrations are available for your organization.",
          })}
        </TabsContent>
      )}

      {showTab("forms") && (
        <TabsContent value="forms" className="space-y-6">
          {renderFormsTab()}
        </TabsContent>
      )}

      {showTab("payment-providers") && (
        <TabsContent value="payment-providers" className="space-y-6">
          <TenantPaymentProvidersManager />
        </TabsContent>
      )}

      {showTab("email-distribution") && (
        <TabsContent value="email-distribution" className="space-y-6">
          {renderIntegrationCards(emailDistributionIntegrations, {
            title: "Email Distribution",
            description:
              "Configure outbound email delivery integrations for tenant communications.",
            message:
              "No email distribution integrations are available for your organization.",
          })}
        </TabsContent>
      )}

      {showTab("customer-support") && (
        <TabsContent value="customer-support" className="space-y-6">
          {renderIntegrationCards(customerSupportIntegrations, {
            title:
              integrationCategoryLabels.customer_support || "Customer Support",
            description:
              integrationCategoryDescriptions.customer_support ||
              "Manage customer support and communication integrations.",
            message:
              "No customer support integrations are available for your organization.",
          })}
        </TabsContent>
      )}

      {showTab("push-notifications") && (
        <TabsContent value="push-notifications" className="space-y-6">
          {renderIntegrationCards(pushNotificationIntegrations, {
            title:
              integrationCategoryLabels.push_notifications ||
              "Push Notifications",
            description:
              integrationCategoryDescriptions.push_notifications ||
              "Manage push notification delivery integrations for the mobile app.",
            message:
              "No push notification integrations are available for your organization.",
          })}
        </TabsContent>
      )}

      {visibleAdditionalTabs.map((section) => (
        <TabsContent
          key={section.value}
          value={section.value}
          className="space-y-6"
        >
          {renderIntegrationCards(section.integrations, {
            title: section.label,
            description: section.description,
            message: `No ${section.label.toLowerCase()} integrations are available for your organization.`,
          })}
        </TabsContent>
      ))}
    </Tabs>
  );
}
