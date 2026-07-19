/**
 * Providers — clinical provider platforms (Telegra/MDI/Zito).
 *
 * This page owns the provider's two connection flows:
 *  - SEND: the provider credentials used to push orders/requests to the provider
 *    (rendered here via the real TenantIntegrationSettings, `facet="credentials"`).
 *  - RECEIVE: the **RTDH webhook validation secret** RTDH uses to verify the
 *    provider's inbound events. Saving sends a write-only secret-change request
 *    to RTDH's Secret Manager Interface; RTDH applies it to GCP Secret Manager.
 *    Backed by the set-provider-rtdh-secret edge function.
 *
 * The provider's Patient Questionnaire Definition is NOT here — it moved to
 * Settings → Questionnaires → Patient (same tenant_integrations storage).
 */
import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { PageHeader } from "@/components/common/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { CheckCircle2, Copy, Loader2, ShieldCheck, XCircle } from "lucide-react";
import { toast } from "sonner";
import { TenantIntegrationSettings } from "@/components/features/TenantIntegrationSettings";
import { usePlatformSettings } from "@/hooks/usePlatformSettings";
import { buildProviderIncomingWebhookUrl } from "@/lib/rtdh-webhook-url";

// Providers that have an RTDH webhook-validation secret managed through RTDH.
const RTDH_SECRET_PROVIDERS: Record<
  string,
  { name: string; rtdhProvider: string; secretName: string }
> = {
  telegramd: {
    name: "Telegra",
    rtdhProvider: "telegramd",
    secretName: "webhook_secret",
  },
  md_integrations: {
    name: "MD Integrations",
    rtdhProvider: "mdi",
    secretName: "webhook_secret",
  },
};

/**
 * RTDH webhook-validation secret control — the provider's RECEIVE flow. Rendered
 * INSIDE the provider's card (via TenantIntegrationSettings' renderProviderFooter)
 * so a provider's two connection flows live together: SEND (credentials above) +
 * RECEIVE (this secret). No outer Card — the provider card is the container.
 */
function RtdhSecretSection({
  providerKey,
  rtdhProvider,
  name,
  secretName,
}: {
  providerKey: string;
  rtdhProvider: string;
  name: string;
  secretName: string;
}) {
  const { currentTenantId, tenants } = useAuth();
  const { getSettingValue } = usePlatformSettings();
  const [value, setValue] = useState("");

  // Resolve the current tenant's slug and the RTDH base URL to build the incoming webhook URL the
  // provider (Telegra/MDI) must call. `?tenant=<slug>` is required by the RTDH receiver.
  const tenantSlug = tenants.find(
    (t) => t.tenant_id === currentTenantId,
  )?.tenant_slug;
  const rtdhConfig = getSettingValue<{ base_url?: string; api_url?: string }>(
    "rtdh_config",
  );
  const rtdhBaseUrl = rtdhConfig?.base_url || rtdhConfig?.api_url;
  const incomingUrl = buildProviderIncomingWebhookUrl({
    rtdhBaseUrl,
    providerKey,
    tenantSlug,
  });

  // Whether a webhook secret is already configured in RTDH for this tenant/provider (yes/no; the
  // value is never fetched). Drives the "Configured / Not configured" status below.
  const status = useQuery({
    queryKey: ["rtdh-secret-status", currentTenantId, rtdhProvider, secretName],
    enabled: Boolean(currentTenantId),
    staleTime: 30_000,
    queryFn: async () => {
      const { data, error } = await supabase.functions.invoke(
        "get-provider-rtdh-secret-status",
        {
          body: {
            tenant_id: currentTenantId,
            provider: rtdhProvider,
            key: secretName,
          },
        },
      );
      if (error) throw error;
      if (data && (data as { error?: string }).error) {
        throw new Error((data as { error: string }).error);
      }
      return Boolean((data as { exists?: boolean }).exists);
    },
  });

  const save = useMutation({
    mutationFn: async () => {
      if (!currentTenantId)
        throw new Error("Select a tenant before saving secrets");
      if (value.trim().length < 8)
        throw new Error("Secret must be at least 8 characters");
      const { data, error } = await supabase.functions.invoke(
        "set-provider-rtdh-secret",
        {
          body: {
            tenant_id: currentTenantId,
            provider: rtdhProvider,
            key: secretName,
            value: value.trim(),
          },
        },
      );
      if (error) throw error;
      if (data && (data as { error?: string }).error) {
        throw new Error((data as { error: string }).error);
      }
      return data;
    },
    onSuccess: () => {
      toast.success(`${name} RTDH secret updated`);
      setValue("");
      status.refetch();
    },
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : "Failed to update secret"),
  });

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <p className="text-sm font-medium">
          Incoming webhook URL (configure in {name})
        </p>
        <p className="text-sm text-muted-foreground">
          Copy this URL into {name}'s webhook settings, then paste the signing
          secret {name} gives you below. The <code>?tenant=</code> parameter is
          required — use the exact URL for this tenant.
        </p>
        {incomingUrl ? (
          <div className="flex items-center gap-2">
            <Input
              readOnly
              value={incomingUrl}
              onFocus={(e) => e.currentTarget.select()}
              className="font-mono text-xs"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => {
                navigator.clipboard
                  .writeText(incomingUrl)
                  .then(() => toast.success("Incoming webhook URL copied"))
                  .catch(() => toast.error("Failed to copy URL"));
              }}
            >
              <Copy className="h-4 w-4" />
            </Button>
          </div>
        ) : (
          <p className="text-sm text-amber-600">
            Cannot build the URL — ensure a tenant is selected and the RTDH base
            URL is set in Settings → RTDH.
          </p>
        )}
      </div>

      <div className="space-y-3">
        <div className="space-y-1">
          <div className="flex items-center justify-between gap-2">
            <p className="flex items-center gap-2 text-sm font-medium">
              <ShieldCheck className="h-4 w-4 text-emerald-600" />
              {name} signing secret (validate incoming events)
            </p>
            {status.isLoading ? (
              <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Checking…
              </span>
            ) : status.isError ? (
              <span className="text-xs text-muted-foreground">
                Status unavailable
              </span>
            ) : status.data ? (
              <span className="flex items-center gap-1 text-xs font-medium text-emerald-600">
                <CheckCircle2 className="h-3.5 w-3.5" /> Configured
              </span>
            ) : (
              <span className="flex items-center gap-1 text-xs font-medium text-amber-600">
                <XCircle className="h-3.5 w-3.5" /> Not configured
              </span>
            )}
          </div>
          <p className="text-sm text-muted-foreground">
            Paste the signing secret {name} gives you (it is generated by {name},
            not here). Saving sends it to RTDH as <code>{secretName}</code>.
            Write-only — the current value is never shown. Rotate it by pasting a
            new value.
          </p>
        </div>
        <div className="space-y-2">
          <Label>
            {status.data ? "New secret value (rotate)" : "Secret value"}
          </Label>
          <Input
            type="password"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder={`Paste the secret from ${name}`}
            className="font-mono text-xs"
          />
        </div>
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            onClick={() => save.mutate()}
            disabled={
              save.isPending || value.trim().length < 8 || !currentTenantId
            }
          >
            {save.isPending && (
              <Loader2 className="h-4 w-4 mr-1 animate-spin" />
            )}
            {status.data ? "Rotate secret" : "Save secret"}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => {
              const bytes = new Uint8Array(32);
              crypto.getRandomValues(bytes);
              setValue(
                Array.from(bytes)
                  .map((b) => b.toString(16).padStart(2, "0"))
                  .join(""),
              );
            }}
          >
            Generate
          </Button>
        </div>
      </div>
    </div>
  );
}

export function ProvidersReal() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Providers"
        description="Clinical provider platforms. Each provider has two connection flows configured together: SEND — credentials used to push orders to them — and RECEIVE — the RTDH secret used to validate their inbound webhook events."
      />

      {/* Real provider platform credentials (SEND) + each provider's RTDH
          validation secret (RECEIVE) rendered together inside its card. The
          questionnaire facet lives on the Questionnaires page. */}
      <TenantIntegrationSettings
        only={["providers"]}
        facet="credentials"
        renderProviderFooter={(providerKey) => {
          const secret = RTDH_SECRET_PROVIDERS[providerKey];
          if (!secret) return null;
          return (
            <RtdhSecretSection
              providerKey={providerKey}
              rtdhProvider={secret.rtdhProvider}
              name={secret.name}
              secretName={secret.secretName}
            />
          );
        }}
      />
    </div>
  );
}
