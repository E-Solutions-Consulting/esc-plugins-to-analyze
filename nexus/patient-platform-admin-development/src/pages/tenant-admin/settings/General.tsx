import { useQueries, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { toNullableRichTextHtml } from '@/lib/html-content';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { HtmlEditor } from '@/components/common/HtmlEditor';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import { Activity, ArrowDown, ArrowUp, Flag, Globe, Info, LifeBuoy, Loader2, Mail, MapPin, Plus, QrCode, Save, Send, Smartphone, Trash2, Users, X } from 'lucide-react';
import { useState, useEffect, useDeferredValue } from 'react';
import { AllowedStatesManager } from '@/components/features/settings/AllowedStatesManager';
import { ActivitiesTrackingSettings } from '@/components/features/ActivitiesTrackingSettings';
import { InjectionSitesSettings } from '@/components/features/InjectionSitesSettings';
import { MoodTrackingSettings } from '@/components/features/MoodTrackingSettings';
import { SymptomTrackingSettings } from '@/components/features/SymptomTrackingSettings';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';

const TIMEZONES = [
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Phoenix',
  'Pacific/Honolulu',
  'Europe/London',
  'Europe/Paris',
];

const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];

const DATE_FORMATS = [
  { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY (US)' },
  { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY (EU)' },
  { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD (ISO)' },
];

const EMAIL_TEMPLATE_TITLE_KEY = '{{EMAIL_TITLE}}';
const EMAIL_TEMPLATE_CONTENT_KEY = '{{EMAIL_CONTENT}}';
const DEFAULT_EMAIL_TEMPLATE_HTML = `<div style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827; max-width: 640px; margin: 0 auto;">
  <h1 style="font-size: 24px; margin: 0 0 24px;">{{EMAIL_TITLE}}</h1>
  <div>
    {{EMAIL_CONTENT}}
  </div>
</div>`;

async function readFunctionError(error: unknown): Promise<string> {
  if (
    error &&
    typeof error === 'object' &&
    'context' in error &&
    error.context instanceof Response
  ) {
    const status = error.context.status;
    const payload = await error.context.clone().json().catch(async () => {
      const text = await error.context.clone().text().catch(() => '');
      return text ? { error: { message: text } } : null;
    });
    const errorPayload =
      payload && typeof payload === 'object' && 'error' in payload
        ? (payload.error as { code?: string; message?: string })
        : null;

    if (errorPayload?.code || errorPayload?.message) {
      return [errorPayload.code, errorPayload.message, `(HTTP ${status})`]
        .filter(Boolean)
        .join(': ');
    }

    return `Edge Function failed with HTTP ${status}`;
  }

  return error instanceof Error ? error.message : String(error);
}

type GeneralTenantSettings = Partial<TenantSettings> & {
  signup_domain_restrictions_enabled?: boolean;
  allowed_signup_email_domains?: string[];
  metadata?: Record<string, unknown> & {
    support_html?: string | null;
    email_template_html?: string | null;
    mobile_apps?: MobileAppsConfig;
    /** Hours to wait before auto-cancelling an unpaid order_created order
     * (Option 2 checkout abandoned-at-payment). Read by the order-lifecycle
     * cleanup-unpaid job. */
    unpaid_order_cancel_hours?: number;
  };
};

type AppStoreId = 'ios' | 'android';

type AppStoreConfig = {
  id: AppStoreId;
  app_url: string;
  qr_code_url: string;
};

type WebAppConfig = {
  base_url: string;
};

type MobileAppsConfig = {
  stores?: AppStoreConfig[];
  web_app?: WebAppConfig;
  ios_app_link?: string;
  android_app_link?: string;
  web_app_base_url?: string;
};

const APP_STORE_LABELS: Record<AppStoreId, string> = {
  ios: 'iOS',
  android: 'Android',
};

// Default settings for new tenants
const DEFAULT_SETTINGS = {
  timezone: 'America/New_York',
  currency: 'USD',
  date_format: 'MM/DD/YYYY',
  allowed_countries: ['US'],
  allowed_states: [],
} satisfies Pick<
  TenantSettings,
  'timezone' | 'currency' | 'date_format' | 'allowed_countries' | 'allowed_states'
>;

function normalizeSignupDomain(value: string): string {
  return value.trim().toLowerCase().replace(/^@+/, '');
}

function isValidSignupDomain(value: string): boolean {
  return /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/.test(value);
}

function decodeHtmlForEditor(value?: string | null): string {
  const rawValue = value?.trim() || '';
  if (!rawValue || typeof window === 'undefined') {
    return rawValue;
  }

  const parser = new DOMParser();
  const parsed = parser.parseFromString(rawValue, 'text/html');
  const hasElements = parsed.body.children.length > 0;

  if (hasElements || !/&(?:lt|gt|amp|quot|#39);/i.test(rawValue)) {
    return rawValue;
  }

  return parsed.body.textContent?.trim() || rawValue;
}

type SupportFaqDraft = {
  question: string;
  answer: string;
};

/** Parse the tenant_support_configs.faqs JSONB value into editor rows,
 * tolerating malformed/legacy content. */
function parseSupportFaqs(value: unknown): SupportFaqDraft[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((entry) => {
    if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return [];
    const record = entry as Record<string, unknown>;
    const question = typeof record.question === 'string' ? record.question : '';
    const answer = typeof record.answer === 'string' ? record.answer : '';
    if (!question && !answer) return [];
    return [{ question, answer }];
  });
}

/** Serialize editor rows for persistence: trim and drop incomplete rows. */
function serializeSupportFaqs(faqs: SupportFaqDraft[]): SupportFaqDraft[] {
  return faqs.flatMap((faq) => {
    const question = faq.question.trim();
    const answer = faq.answer.trim();
    if (!question || !answer) return [];
    return [{ question, answer }];
  });
}

function normalizeOptionalHttpsUrl(value?: string | null, label = 'URL'): string | undefined {
  const normalized = value?.trim();
  if (!normalized) return undefined;

  let parsed: URL;
  try {
    parsed = new URL(normalized);
  } catch {
    throw new Error(`${label} must be a valid URL`);
  }

  if (parsed.protocol !== 'https:') {
    throw new Error(`${label} must use HTTPS`);
  }

  return parsed.toString();
}

function emptyStoreConfig(id: AppStoreId): AppStoreConfig {
  return {
    id,
    app_url: '',
    qr_code_url: '',
  };
}

function getStoreConfig(metadata: GeneralTenantSettings['metadata'], id: AppStoreId): AppStoreConfig {
  const mobileApps = metadata?.mobile_apps;
  const store = mobileApps?.stores?.find((item) => item.id === id);
  if (store) {
    return {
      id,
      app_url: store.app_url || '',
      qr_code_url: store.qr_code_url || '',
    };
  }

  const legacyUrl = id === 'ios' ? mobileApps?.ios_app_link : mobileApps?.android_app_link;
  return { ...emptyStoreConfig(id), app_url: legacyUrl || '' };
}

function getWebAppBaseUrl(metadata: GeneralTenantSettings['metadata']): string {
  const mobileApps = metadata?.mobile_apps;
  return mobileApps?.web_app?.base_url || mobileApps?.web_app_base_url || '';
}

function setStoreConfig(
  data: GeneralTenantSettings,
  id: AppStoreId,
  updates: Partial<AppStoreConfig>,
): GeneralTenantSettings {
  const currentStores = (data.metadata?.mobile_apps?.stores || []).filter(
    (store) => store.id !== id,
  );
  const nextStore = { ...getStoreConfig(data.metadata, id), ...updates, id };
  return {
    ...data,
    metadata: {
      ...(data.metadata || {}),
      mobile_apps: {
        ...(data.metadata?.mobile_apps || {}),
        stores: [...currentStores, nextStore].sort((a, b) => {
          const order: Record<AppStoreId, number> = { ios: 0, android: 1 };
          return order[a.id] - order[b.id];
        }),
      },
    },
  };
}

function buildMobileAppsMetadata(data: GeneralTenantSettings): MobileAppsConfig | undefined {
  const stores: AppStoreConfig[] = (['ios', 'android'] as AppStoreId[])
    .map((id) => getStoreConfig(data.metadata, id))
    .map((store) => ({
      ...store,
      app_url: normalizeOptionalHttpsUrl(store.app_url, `${APP_STORE_LABELS[store.id]} app URL`) || '',
    }))
    .filter((store) => store.app_url);

  const webAppBaseUrl = normalizeOptionalHttpsUrl(
    getWebAppBaseUrl(data.metadata),
    'Web app base URL',
  );

  if (stores.length === 0 && !webAppBaseUrl) {
    return undefined;
  }

  return {
    ...(stores.length > 0 ? { stores } : {}),
    ...(webAppBaseUrl ? { web_app: { base_url: webAppBaseUrl } } : {}),
  };
}

function validateEmailTemplateHtml(templateHtml?: string | null): void {
  const normalized = templateHtml?.trim();
  if (!normalized) return;

  if (!normalized.includes(EMAIL_TEMPLATE_TITLE_KEY)) {
    throw new Error(`Email template must include ${EMAIL_TEMPLATE_TITLE_KEY}`);
  }

  if (!normalized.includes(EMAIL_TEMPLATE_CONTENT_KEY)) {
    throw new Error(`Email template must include ${EMAIL_TEMPLATE_CONTENT_KEY}`);
  }
}

/**
 * Page body without the AdminLayout wrapper (for reuse in Settings v2).
 *
 * `only` restricts which tabs render, so self-contained tabs can be surfaced in
 * their own Settings v2 home (e.g. Health Trackers, Feature Flags) while reusing
 * the EXACT same component/state/save logic. When a single tab is requested the
 * tab bar is hidden (the v2 nav already names the section). Omitting `only`
 * renders the full page unchanged (used by the original route + v2 General).
 */
export function GeneralContent({ only }: { only?: string[] } = {}) {
  const { currentTenantId, user } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [formData, setFormData] = useState<GeneralTenantSettings>({});
  const [supportFaqs, setSupportFaqs] = useState<SupportFaqDraft[]>([]);
  const [supportHours, setSupportHours] = useState('');
  const showTab = (tab: string) => !only || only.includes(tab);
  const hideTabBar = Array.isArray(only) && only.length === 1;
  const [activeTab, setActiveTab] = useState(only?.[0] ?? 'localization');
  const [domainInput, setDomainInput] = useState('');
  const [testEmailDialogOpen, setTestEmailDialogOpen] = useState(false);
  const [testEmailRecipient, setTestEmailRecipient] = useState(user?.email ?? '');
  const deferredDomainInput = useDeferredValue(domainInput);

  const [
    { data: settings, isLoading },
    { data: supportConfig, isLoading: supportConfigLoading },
    { data: flags = [], isLoading: flagsLoading },
    { data: overrides = [], isLoading: overridesLoading },
  ] = useQueries({
    queries: [
      {
        queryKey: ['tenant-settings', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return null;

          const { data, error } = await supabase
            .from('tenant_settings')
            .select('*')
            .eq('tenant_id', currentTenantId)
            .single();

          // PGRST116 = no rows found - return null so we can create on save
          if (error && error.code !== 'PGRST116') throw error;
          
          // Cast and include allowed_countries/allowed_states which may not be in generated types yet
          if (data) {
            const rawData = data as Record<string, unknown>;
            return {
              ...data,
              allowed_countries: (rawData.allowed_countries as string[]) || ['US'],
              allowed_states: (rawData.allowed_states as string[]) || [],
              signup_domain_restrictions_enabled:
                (rawData.signup_domain_restrictions_enabled as boolean) || false,
              allowed_signup_email_domains:
                (rawData.allowed_signup_email_domains as string[]) || [],
            } as GeneralTenantSettings;
          }
          return null;
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: ['tenant-support-config', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return null;

          const { data, error } = await supabase
            .from('tenant_support_configs')
            .select('*')
            .eq('tenant_id', currentTenantId)
            .single();

          if (error && error.code !== 'PGRST116') throw error;
          return data as TenantSupportConfig | null;
        },
        enabled: !!currentTenantId,
      },
      {
        queryKey: ['feature-flags'],
        queryFn: async () => {
          const { data, error } = await supabase
            .from('feature_flags')
            .select('*')
            .eq('is_active', true)
            .order('name');

          if (error) throw error;
          return data as FeatureFlag[];
        },
      },
      {
        queryKey: ['tenant-flag-overrides', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await supabase
            .from('tenant_feature_flag_overrides')
            .select('*')
            .eq('tenant_id', currentTenantId);

          if (error) throw error;
          return data as FlagOverride[];
        },
        enabled: !!currentTenantId,
      },
    ],
  });

  useEffect(() => {
    if (settings) {
      setFormData({
        ...settings,
        metadata: {
          ...(settings.metadata || {}),
          support_html: supportConfig?.support_html ?? null,
        },
      });
    } else if (currentTenantId && !isLoading) {
      // Initialize with defaults when no settings exist
      setFormData({
        ...DEFAULT_SETTINGS,
        signup_domain_restrictions_enabled: false,
        allowed_signup_email_domains: [],
        metadata: {
          support_html: supportConfig?.support_html ?? null,
        },
      });
    }
    setSupportFaqs(parseSupportFaqs(supportConfig?.faqs));
    setSupportHours(supportConfig?.support_hours ?? '');
  }, [settings, supportConfig, currentTenantId, isLoading]);

  const updateMutation = useMutation({
    mutationFn: async (data: GeneralTenantSettings) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const normalizedAllowedDomains = Array.from(
        new Set(
          (data.allowed_signup_email_domains || [])
            .map(normalizeSignupDomain)
            .filter((domain) => domain && isValidSignupDomain(domain)),
        ),
      );

      const payload: Record<string, unknown> = {
        tenant_id: currentTenantId,
        timezone: data.timezone || DEFAULT_SETTINGS.timezone,
        currency: data.currency || DEFAULT_SETTINGS.currency,
        date_format: data.date_format || DEFAULT_SETTINGS.date_format,
        allowed_countries: data.allowed_countries || DEFAULT_SETTINGS.allowed_countries,
        allowed_states: data.allowed_states || DEFAULT_SETTINGS.allowed_states,
        signup_domain_restrictions_enabled: data.signup_domain_restrictions_enabled ?? false,
        allowed_signup_email_domains: normalizedAllowedDomains,
      };

      const nextMetadata: Record<string, unknown> = {
        ...((data.metadata || {}) as Record<string, unknown>),
      };
      delete nextMetadata.support_html;

      const normalizedEmailTemplateHtml = toNullableRichTextHtml(
        data.metadata?.email_template_html as string | null | undefined,
      );
      validateEmailTemplateHtml(normalizedEmailTemplateHtml);

      if (normalizedEmailTemplateHtml) {
        nextMetadata.email_template_html = normalizedEmailTemplateHtml;
      } else {
        delete nextMetadata.email_template_html;
      }

      const mobileApps = buildMobileAppsMetadata(data);
      // tenant-app-store-config owns mobile app persistence, QR generation, and
      // passkey origin synchronization. Preserve the previous value here so the
      // function can compare the old and new web app origins.
      if (settings?.metadata?.mobile_apps) {
        nextMetadata.mobile_apps = settings.metadata.mobile_apps;
      } else {
        delete nextMetadata.mobile_apps;
      }

      // Unpaid-order auto-cancel window (hours). Persist only a valid positive
      // number; otherwise clear it so the cleanup job falls back to its default.
      const unpaidHoursRaw = data.metadata?.unpaid_order_cancel_hours;
      const unpaidHours =
        typeof unpaidHoursRaw === 'number'
          ? unpaidHoursRaw
          : Number(unpaidHoursRaw);
      if (Number.isFinite(unpaidHours) && unpaidHours > 0) {
        nextMetadata.unpaid_order_cancel_hours = Math.round(unpaidHours);
      } else {
        delete nextMetadata.unpaid_order_cancel_hours;
      }

      payload.metadata = nextMetadata;

      const client = supabase as unknown as {
        from: (table: string) => {
          upsert: (data: Record<string, unknown>, options: { onConflict: string }) => {
            select: () => {
              single: () => Promise<{ data: TenantSettings; error: Error | null }>;
            };
          };
        };
      };

      const { data: result, error } = await client
        .from('tenant_settings')
        .upsert(payload, { onConflict: 'tenant_id' })
        .select()
        .single();

      if (error) throw error;

      const normalizedSupportHtml = toNullableRichTextHtml(
        data.metadata?.support_html as string | null | undefined,
      );
      const normalizedSupportFaqs = serializeSupportFaqs(supportFaqs);
      const normalizedSupportHours = supportHours.trim() || null;

      let supportResult: TenantSupportConfig | null = supportConfig ?? null;

      if (
        normalizedSupportHtml ||
        normalizedSupportFaqs.length > 0 ||
        normalizedSupportHours ||
        supportConfig
      ) {
        const { data: savedSupportConfig, error: supportError } = await supabase
          .from('tenant_support_configs')
          .upsert(
            {
              tenant_id: currentTenantId,
              support_html: normalizedSupportHtml,
              faqs: normalizedSupportFaqs,
              support_hours: normalizedSupportHours,
            },
            { onConflict: 'tenant_id' },
          )
          .select()
          .single();

        if (supportError) throw supportError;
        supportResult = savedSupportConfig as TenantSupportConfig;
      }

      const {
        data: { session },
      } = await supabase.auth.getSession();
      const accessToken = session?.access_token;
      if (!accessToken) {
        throw new Error('Missing auth session');
      }

      const { data: appStoreResult, error: appStoreError } = await supabase.functions.invoke<{
        mobile_apps: MobileAppsConfig | null;
        error?: string;
      }>('tenant-app-store-config', {
        headers: { Authorization: `Bearer ${accessToken}` },
        body: {
          tenantId: currentTenantId,
          stores: mobileApps?.stores || [],
          web_app: mobileApps?.web_app || null,
          web_app_base_url: mobileApps?.web_app?.base_url || null,
        },
      });

      if (appStoreError) throw appStoreError;
      if (appStoreResult?.error) throw new Error(appStoreResult.error);

      return {
        result,
        previousData: settings,
        supportResult,
        previousSupportConfig: supportConfig,
        appStoreResult,
      };
    },
    onSuccess: ({ result, previousData, supportResult, previousSupportConfig, appStoreResult }) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-settings'] });
      queryClient.invalidateQueries({ queryKey: ['tenant-support-config'] });
      if (appStoreResult) {
        setFormData((current) => ({
          ...current,
          metadata: {
            ...(current.metadata || {}),
            mobile_apps: appStoreResult.mobile_apps || undefined,
          },
        }));
      }
      logAction({
        action: previousData ? 'update' : 'create',
        entityType: 'tenant_settings',
        entityId: result.id,
        beforeData: previousData as unknown as Record<string, unknown>,
        afterData: result as unknown as Record<string, unknown>,
      });
      if (supportResult) {
        logAction({
          action: previousSupportConfig ? 'update' : 'create',
          entityType: 'tenant_support_config',
          entityId: supportResult.id,
          beforeData: previousSupportConfig as unknown as Record<string, unknown>,
          afterData: supportResult as unknown as Record<string, unknown>,
        });
      }
      toast.success('Settings saved successfully');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to save settings');
    },
  });

  const toggleFeatureFlagMutation = useMutation({
    mutationFn: async ({ flagId, enabled }: { flagId: string; enabled: boolean }) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const existingOverride = overrides.find((override) => override.feature_flag_id === flagId);

      if (existingOverride) {
        const { error } = await supabase
          .from('tenant_feature_flag_overrides')
          .update({ enabled })
          .eq('id', existingOverride.id);

        if (error) throw error;
      } else {
        const { error } = await supabase
          .from('tenant_feature_flag_overrides')
          .insert([{ feature_flag_id: flagId, tenant_id: currentTenantId, enabled }]);

        if (error) throw error;
      }

      return { flagId, enabled };
    },
    onSuccess: ({ flagId, enabled }) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-flag-overrides'] });
      const flag = flags.find((item) => item.id === flagId);
      logAction({
        action: 'update',
        entityType: 'feature_flag_override',
        entityId: flagId,
        afterData: { flagId, enabled, flagKey: flag?.key },
      });
      toast.success(`Feature ${enabled ? 'enabled' : 'disabled'}`);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update feature');
    },
  });

  const sendTestEmailMutation = useMutation({
    mutationFn: async () => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const recipientEmail = testEmailRecipient.trim();
      if (!recipientEmail) throw new Error('Recipient email is required');

      const { data: result, error: fnError } = await supabase.functions.invoke<{
        data?: {
          sent: boolean;
          integration_key: string;
        };
        error?: { code: string; message: string };
      }>('patient-api/admin/test-email', {
        body: {
          tenant_id: currentTenantId,
          to: recipientEmail,
        },
        method: 'POST',
      });

      if (fnError) throw new Error(await readFunctionError(fnError));
      if (result?.error) {
        throw new Error(`${result.error.code}: ${result.error.message}`);
      }
      if (!result?.data?.sent) {
        throw new Error('Test email was not sent');
      }

      return result.data;
    },
    onSuccess: () => {
      toast.success('Test email sent', {
        description: `Sent to ${testEmailRecipient.trim()}.`,
      });
      setTestEmailDialogOpen(false);
    },
    onError: (error) => {
      const message = error instanceof Error ? error.message : String(error);
      toast.error('Failed to send test email', { description: message });
    },
  });

  const getFlagValue = (flag: FeatureFlag): boolean => {
    const override = overrides.find((item) => item.feature_flag_id === flag.id);
    return override ? override.enabled : flag.default_value;
  };

  const isOverridden = (flagId: string): boolean => {
    return overrides.some((item) => item.feature_flag_id === flagId);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateMutation.mutate(formData);
  };

  const handleAddDomain = () => {
    const normalizedDomain = normalizeSignupDomain(deferredDomainInput);

    if (!normalizedDomain) {
      toast.error('Enter a domain to allow');
      return;
    }

    if (!isValidSignupDomain(normalizedDomain)) {
      toast.error('Enter a valid domain such as company.com');
      return;
    }

    const existingDomains = formData.allowed_signup_email_domains || [];
    if (existingDomains.includes(normalizedDomain)) {
      toast.error('That domain is already on the allowlist');
      return;
    }

    setFormData({
      ...formData,
      allowed_signup_email_domains: [...existingDomains, normalizedDomain],
    });
    setDomainInput('');
  };

  const handleRemoveDomain = (domainToRemove: string) => {
    setFormData({
      ...formData,
      allowed_signup_email_domains: (formData.allowed_signup_email_domains || []).filter(
        (domain) => domain !== domainToRemove,
      ),
    });
  };

  if (isLoading || supportConfigLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <>
      <PageHeader
        title="Settings"
        description="Configure tenant settings and feature access"
      />

      <form onSubmit={handleSubmit} className="space-y-6">
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          {!hideTabBar ? (
            <TabsList className="flex h-auto w-full flex-wrap justify-start gap-2 rounded-xl bg-muted/50 p-1">
              {showTab('localization') ? (
                <TabsTrigger value="localization" className="gap-2">
                  <MapPin className="h-4 w-4" />
                  Localization
                </TabsTrigger>
              ) : null}
              {showTab('apps') ? (
                <TabsTrigger value="apps" className="gap-2">
                  <Smartphone className="h-4 w-4" />
                  Apps
                </TabsTrigger>
              ) : null}
              {showTab('health-trackers') ? (
                <TabsTrigger value="health-trackers" className="gap-2">
                  <Activity className="h-4 w-4" />
                  Health Trackers
                </TabsTrigger>
              ) : null}
              {showTab('feature-flags') ? (
                <TabsTrigger value="feature-flags" className="gap-2">
                  <Flag className="h-4 w-4" />
                  Feature Flags
                </TabsTrigger>
              ) : null}
              {showTab('users') ? (
                <TabsTrigger value="users" className="gap-2">
                  <Users className="h-4 w-4" />
                  Users
                </TabsTrigger>
              ) : null}
              {showTab('support') ? (
                <TabsTrigger value="support" className="gap-2">
                  <LifeBuoy className="h-4 w-4" />
                  Support
                </TabsTrigger>
              ) : null}
              {showTab('communication') ? (
                <TabsTrigger value="communication" className="gap-2">
                  <Mail className="h-4 w-4" />
                  Communication
                </TabsTrigger>
              ) : null}
            </TabsList>
          ) : null}

          {showTab('localization') ? (
          <TabsContent value="localization" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Localization</CardTitle>
                <CardDescription>
                  Configure timezone, currency, and date format preferences
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-6 md:grid-cols-3">
                <div className="space-y-2">
                  <Label htmlFor="timezone">Timezone</Label>
                  <Select
                    value={formData.timezone || ''}
                    onValueChange={(value) => setFormData({ ...formData, timezone: value })}
                  >
                    <SelectTrigger id="timezone">
                      <SelectValue placeholder="Select timezone" />
                    </SelectTrigger>
                    <SelectContent>
                      {TIMEZONES.map((tz) => (
                        <SelectItem key={tz} value={tz}>
                          {tz}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="currency">Currency</Label>
                  <Select
                    value={formData.currency || ''}
                    onValueChange={(value) => setFormData({ ...formData, currency: value })}
                  >
                    <SelectTrigger id="currency">
                      <SelectValue placeholder="Select currency" />
                    </SelectTrigger>
                    <SelectContent>
                      {CURRENCIES.map((currency) => (
                        <SelectItem key={currency} value={currency}>
                          {currency}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="date_format">Date Format</Label>
                  <Select
                    value={formData.date_format || ''}
                    onValueChange={(value) => setFormData({ ...formData, date_format: value })}
                  >
                    <SelectTrigger id="date_format">
                      <SelectValue placeholder="Select format" />
                    </SelectTrigger>
                    <SelectContent>
                      {DATE_FORMATS.map((format) => (
                        <SelectItem key={format.value} value={format.value}>
                          {format.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Orders</CardTitle>
                <CardDescription>
                  Control how long an unpaid checkout order is kept before it is
                  automatically cancelled.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-6 md:grid-cols-3">
                <div className="space-y-2">
                  <Label htmlFor="unpaid_order_cancel_hours">
                    Cancel unpaid orders after (hours)
                  </Label>
                  <Input
                    id="unpaid_order_cancel_hours"
                    type="number"
                    min={1}
                    placeholder="72"
                    value={
                      formData.metadata?.unpaid_order_cancel_hours ?? ''
                    }
                    onChange={(event) =>
                      setFormData({
                        ...formData,
                        metadata: {
                          ...(formData.metadata || {}),
                          unpaid_order_cancel_hours:
                            event.target.value === ''
                              ? undefined
                              : Number(event.target.value),
                        },
                      })
                    }
                  />
                  <p className="text-xs text-muted-foreground">
                    Orders where the customer reached payment but never authorized
                    a payment method are cancelled after this window (and the
                    Stripe authorization is voided). Leave blank to use the
                    default of 72 hours.
                  </p>
                </div>
              </CardContent>
            </Card>

            {(formData.allowed_countries || []).includes('US') && currentTenantId ? (
              <AllowedStatesManager
                tenantId={currentTenantId}
                allowedStates={formData.allowed_states || []}
              />
            ) : null}
          </TabsContent>
          ) : null}

          {showTab('apps') ? (
          <TabsContent value="apps" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Web App</CardTitle>
                <CardDescription>
                  Configure the tenant-specific base URL used by patient-facing web links.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-5 rounded-lg border p-4">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                      <Globe className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <h3 className="font-semibold">Web</h3>
                      <p className="text-sm text-muted-foreground">
                        Base URL for the tenant web app
                      </p>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="web_app_base_url">Base URL</Label>
                    <Input
                      id="web_app_base_url"
                      type="url"
                      value={getWebAppBaseUrl(formData.metadata)}
                      onChange={(event) =>
                        setFormData({
                          ...formData,
                          metadata: {
                            ...(formData.metadata || {}),
                            mobile_apps: {
                              ...(formData.metadata?.mobile_apps || {}),
                              web_app: {
                                base_url: event.target.value,
                              },
                            },
                          },
                        })
                      }
                      placeholder="https://app.example.com"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Mobile Apps</CardTitle>
                <CardDescription>
                  Configure tenant-specific app store badges, links, and QR codes.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-6 xl:grid-cols-2">
                {(['ios', 'android'] as AppStoreId[]).map((storeId) => {
                  const store = getStoreConfig(formData.metadata, storeId);
                  const label = APP_STORE_LABELS[storeId];

                  return (
                    <div key={storeId} className="space-y-5 rounded-lg border p-4">
                      <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Smartphone className="h-5 w-5 text-primary" />
                          </div>
                          <div>
                            <h3 className="font-semibold">{label}</h3>
                            <p className="text-sm text-muted-foreground">
                              Store app URL and generated QR code
                            </p>
                          </div>
                        </div>
                        {store.qr_code_url ? (
                          <Badge variant="outline" className="gap-1">
                            <QrCode className="h-3 w-3" />
                            QR Ready
                          </Badge>
                        ) : null}
                      </div>

                      <div className="space-y-2">
                        <Label htmlFor={`${storeId}_app_url`}>Store App URL</Label>
                        <Input
                          id={`${storeId}_app_url`}
                          type="url"
                          value={store.app_url}
                          onChange={(event) =>
                            setFormData((current) =>
                              setStoreConfig(current, storeId, {
                                app_url: event.target.value,
                                qr_code_url: '',
                              }),
                            )
                          }
                          placeholder={
                            storeId === 'ios'
                              ? 'https://apps.apple.com/...'
                              : 'https://play.google.com/store/apps/details?id=...'
                          }
                        />
                      </div>

                      <div className="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-center">
                        <div className="flex h-28 w-28 items-center justify-center rounded-lg border bg-white p-2">
                          {store.qr_code_url ? (
                            <img
                              src={store.qr_code_url}
                              alt={`${label} QR code`}
                              className="h-full w-full object-contain"
                            />
                          ) : (
                            <QrCode className="h-8 w-8 text-muted-foreground" />
                          )}
                        </div>
                        <div className="space-y-1 text-sm text-muted-foreground">
                          <p>QR code is generated after saving.</p>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </CardContent>
            </Card>
          </TabsContent>
          ) : null}


          {showTab('health-trackers') ? (
          <TabsContent value="health-trackers" className="space-y-6">
            <InjectionSitesSettings />
            <ActivitiesTrackingSettings />
            <MoodTrackingSettings />
            <SymptomTrackingSettings />
          </TabsContent>
          ) : null}

          {showTab('feature-flags') ? (
          <TabsContent value="feature-flags">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Flag className="h-5 w-5" />
                  Feature Flags
                </CardTitle>
                <CardDescription>
                  Toggle tenant features on or off. Changes take effect immediately.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {flagsLoading || overridesLoading ? (
                  <div className="flex items-center justify-center py-6">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                  </div>
                ) : flags.length === 0 ? (
                  <p className="text-muted-foreground py-6 text-center">No feature flags available</p>
                ) : (
                  flags.map((flag) => (
                    <div
                      key={flag.id}
                      className="flex items-center justify-between rounded-lg border p-4"
                    >
                      <div className="flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-medium">{flag.name}</span>
                          {isOverridden(flag.id) ? (
                            <Badge variant="outline" className="text-xs">
                              Customized
                            </Badge>
                          ) : null}
                          {flag.description ? (
                            <Tooltip>
                              <TooltipTrigger asChild>
                                <button type="button" className="text-muted-foreground">
                                  <Info className="h-4 w-4" />
                                  <span className="sr-only">View feature description</span>
                                </button>
                              </TooltipTrigger>
                              <TooltipContent>
                                <p className="max-w-xs">{flag.description}</p>
                              </TooltipContent>
                            </Tooltip>
                          ) : null}
                        </div>
                        <p className="font-mono text-sm text-muted-foreground">{flag.key}</p>
                      </div>
                      <Switch
                        checked={getFlagValue(flag)}
                        onCheckedChange={(checked) =>
                          toggleFeatureFlagMutation.mutate({ flagId: flag.id, enabled: checked })
                        }
                        disabled={toggleFeatureFlagMutation.isPending}
                      />
                    </div>
                  ))
                )}
              </CardContent>
            </Card>
          </TabsContent>
          ) : null}

          {showTab('users') ? (
          <TabsContent value="users">
            <Card>
              <CardHeader>
                <CardTitle>Allowed Signup Domains</CardTitle>
                <CardDescription>
                  Control which email domains are allowed to register in the app.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="flex items-start justify-between gap-4 rounded-lg border p-4">
                  <div className="space-y-1">
                    <Label>Restrict signup by email domain</Label>
                    <p className="text-sm text-muted-foreground">
                      If disabled, all email domains will be allowed to register in the app. If
                      enabled, only emails whose domain matches the allowlist below will be
                      accepted.
                    </p>
                  </div>
                  <Switch
                    checked={formData.signup_domain_restrictions_enabled || false}
                    onCheckedChange={(checked) =>
                      setFormData({ ...formData, signup_domain_restrictions_enabled: checked })
                    }
                  />
                </div>

                <div className="space-y-4 rounded-lg border p-4">
                  <div className="space-y-1">
                    <Label htmlFor="allowed-signup-domain">Allowed domains</Label>
                    <p className="text-sm text-muted-foreground">
                      Add domains like `company.com`. We compare against the part after the `@` in
                      the email address.
                    </p>
                  </div>

                  <div className="flex flex-col gap-3 sm:flex-row">
                    <Input
                      id="allowed-signup-domain"
                      value={domainInput}
                      onChange={(e) => setDomainInput(e.target.value)}
                      placeholder="company.com"
                      disabled={!formData.signup_domain_restrictions_enabled}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          handleAddDomain();
                        }
                      }}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={handleAddDomain}
                      disabled={!formData.signup_domain_restrictions_enabled}
                    >
                      Add Domain
                    </Button>
                  </div>

                  {(formData.allowed_signup_email_domains || []).length > 0 ? (
                    <div className="flex flex-wrap gap-2">
                      {(formData.allowed_signup_email_domains || []).map((domain) => (
                        <Badge
                          key={domain}
                          variant="secondary"
                          className="flex items-center gap-1 px-3 py-1"
                        >
                          {domain}
                          <button
                            type="button"
                            onClick={() => handleRemoveDomain(domain)}
                            className="text-muted-foreground transition hover:text-foreground"
                            aria-label={`Remove ${domain}`}
                            disabled={!formData.signup_domain_restrictions_enabled}
                          >
                            <X className="h-3 w-3" />
                          </button>
                        </Badge>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      No allowed domains configured yet.
                    </p>
                  )}

                  <div className="rounded-md bg-muted/50 p-3 text-sm text-muted-foreground">
                    {formData.signup_domain_restrictions_enabled
                      ? 'Users with email domains not included in this list will be denied registration with an explicit message.'
                      : 'Domain restriction is disabled, so all valid email domains can register.'}
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
          ) : null}

          {showTab('support') ? (
          <TabsContent value="support">
            <Card>
              <CardHeader>
                <CardTitle>Support Content</CardTitle>
                <CardDescription>
                  Manage tenant-specific HTML content shown on the patient Support page.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <Label htmlFor="support_html">Support Page HTML</Label>
                  <HtmlEditor
                    id="support_html"
                    value={decodeHtmlForEditor((formData.metadata?.support_html as string | undefined) ?? '')}
                    onChange={(value) =>
                      setFormData({
                        ...formData,
                        metadata: {
                          ...(formData.metadata || {}),
                          support_html: value,
                        },
                      })
                    }
                    placeholder="Add tenant-specific support instructions, FAQs, or links."
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="support_hours">Support hours</Label>
                  <Input
                    id="support_hours"
                    value={supportHours}
                    onChange={(event) => setSupportHours(event.target.value)}
                    placeholder="e.g. Monday–Friday, 8:00 AM–5:00 PM CST"
                  />
                  <p className="text-xs text-muted-foreground">
                    Shown to patients on the Support page, e.g. Monday–Friday, 8:00 AM–5:00 PM CST.
                  </p>
                </div>

                <div className="space-y-3 border-t pt-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium">Support FAQs</p>
                      <p className="text-xs text-muted-foreground">
                        Frequently asked questions shown on the patient Support page. Rows missing a
                        question or answer are dropped on save.
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setSupportFaqs((current) => [...current, { question: '', answer: '' }])}
                    >
                      <Plus className="h-4 w-4 mr-2" />
                      Add FAQ
                    </Button>
                  </div>

                  {supportFaqs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-center border border-dashed rounded-lg bg-muted/30">
                      <LifeBuoy className="h-10 w-10 text-muted-foreground mb-2" />
                      <p className="text-sm text-muted-foreground">No support FAQs configured</p>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {supportFaqs.map((faq, index) => (
                        <div key={index} className="rounded-md border bg-muted/10 p-3 space-y-3">
                          <div className="flex items-center justify-between gap-3">
                            <span className="text-xs text-muted-foreground">FAQ {index + 1}</span>
                            <div className="flex items-center gap-1">
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={index === 0}
                                aria-label="Move FAQ up"
                                onClick={() =>
                                  setSupportFaqs((current) => {
                                    const next = [...current];
                                    [next[index - 1], next[index]] = [next[index], next[index - 1]];
                                    return next;
                                  })
                                }
                              >
                                <ArrowUp className="h-4 w-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={index === supportFaqs.length - 1}
                                aria-label="Move FAQ down"
                                onClick={() =>
                                  setSupportFaqs((current) => {
                                    const next = [...current];
                                    [next[index], next[index + 1]] = [next[index + 1], next[index]];
                                    return next;
                                  })
                                }
                              >
                                <ArrowDown className="h-4 w-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                aria-label="Remove FAQ"
                                className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                onClick={() =>
                                  setSupportFaqs((current) => current.filter((_, i) => i !== index))
                                }
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            </div>
                          </div>
                          <div className="space-y-2">
                            <Label htmlFor={`support-faq-question-${index}`}>Question</Label>
                            <Input
                              id={`support-faq-question-${index}`}
                              value={faq.question}
                              maxLength={200}
                              placeholder="e.g. How do I contact support?"
                              onChange={(event) =>
                                setSupportFaqs((current) =>
                                  current.map((item, i) =>
                                    i === index ? { ...item, question: event.target.value } : item,
                                  ),
                                )
                              }
                            />
                          </div>
                          <div className="space-y-2">
                            <Label htmlFor={`support-faq-answer-${index}`}>Answer</Label>
                            <Textarea
                              id={`support-faq-answer-${index}`}
                              value={faq.answer}
                              rows={4}
                              maxLength={2000}
                              placeholder="Provide the patient-facing answer..."
                              onChange={(event) =>
                                setSupportFaqs((current) =>
                                  current.map((item, i) =>
                                    i === index ? { ...item, answer: event.target.value } : item,
                                  ),
                                )
                              }
                            />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
          ) : null}

          {showTab('communication') ? (
          <TabsContent value="communication">
            <Card>
              <CardHeader>
                <CardTitle>Email Template</CardTitle>
                <CardDescription>
                  Manage the tenant-wide HTML wrapper used for emails sent through Resend.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-5">
                <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                  <p className="font-medium">Required template keys</p>
                  <div className="mt-3 grid gap-3 md:grid-cols-2">
                    <div className="space-y-1">
                      <Badge variant="secondary" className="font-mono">
                        {EMAIL_TEMPLATE_TITLE_KEY}
                      </Badge>
                      <p className="text-muted-foreground">
                        Replaced with the email title for each message.
                      </p>
                    </div>
                    <div className="space-y-1">
                      <Badge variant="secondary" className="font-mono">
                        {EMAIL_TEMPLATE_CONTENT_KEY}
                      </Badge>
                      <p className="text-muted-foreground">
                        Replaced with the full email body for each message.
                      </p>
                    </div>
                  </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                  <Label htmlFor="email_template_html">Email Template HTML</Label>
                  <div className="flex flex-wrap gap-2">
                    <Dialog
                      open={testEmailDialogOpen}
                      onOpenChange={(open) => {
                        if (open && !testEmailRecipient.trim() && user?.email) {
                          setTestEmailRecipient(user.email);
                        }
                        setTestEmailDialogOpen(open);
                      }}
                    >
                      <DialogTrigger asChild>
                        <Button type="button" variant="outline" size="sm" className="gap-2">
                          <Send className="h-4 w-4" />
                          Send Test Email
                        </Button>
                      </DialogTrigger>
                      <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                          <DialogTitle className="flex items-center gap-2">
                            <Mail className="h-5 w-5" />
                            Send Test Email
                          </DialogTitle>
                          <DialogDescription>
                            Send a test email through Resend using the saved tenant email template.
                            Save changes first to test edits made here.
                          </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-2 py-2">
                          <Label htmlFor="test-email-recipient">Recipient Email</Label>
                          <Input
                            id="test-email-recipient"
                            type="email"
                            value={testEmailRecipient}
                            onChange={(event) => setTestEmailRecipient(event.target.value)}
                            placeholder="admin@example.com"
                          />
                        </div>

                        <DialogFooter className="gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => setTestEmailDialogOpen(false)}
                            disabled={sendTestEmailMutation.isPending}
                          >
                            Cancel
                          </Button>
                          <Button
                            type="button"
                            onClick={() => sendTestEmailMutation.mutate()}
                            disabled={
                              sendTestEmailMutation.isPending || !testEmailRecipient.trim()
                            }
                            className="gap-2"
                          >
                            {sendTestEmailMutation.isPending ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <Send className="h-4 w-4" />
                            )}
                            Send Email
                          </Button>
                        </DialogFooter>
                      </DialogContent>
                    </Dialog>

                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        setFormData({
                          ...formData,
                          metadata: {
                            ...(formData.metadata || {}),
                            email_template_html: DEFAULT_EMAIL_TEMPLATE_HTML,
                          },
                        })
                      }
                    >
                      Use Default Template
                    </Button>
                  </div>
                </div>
                <HtmlEditor
                  id="email_template_html"
                  value={decodeHtmlForEditor(
                    (formData.metadata?.email_template_html as string | undefined) ?? '',
                  )}
                  onChange={(value) =>
                    setFormData({
                      ...formData,
                      metadata: {
                        ...(formData.metadata || {}),
                        email_template_html: value,
                      },
                    })
                  }
                  placeholder={DEFAULT_EMAIL_TEMPLATE_HTML}
                  minHeightClassName="min-h-72"
                />
              </CardContent>
            </Card>
          </TabsContent>
          ) : null}
        </Tabs>

        {!['feature-flags', 'health-trackers'].includes(activeTab) ? (
          <div className="flex justify-end">
            <Button type="submit" disabled={updateMutation.isPending}>
              {updateMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              <Save className="mr-2 h-4 w-4" />
              Save Changes
            </Button>
          </div>
        ) : null}
      </form>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function GeneralSettings() {
  return (
    <AdminLayout variant="tenant">
      <GeneralContent />
    </AdminLayout>
  );
}
