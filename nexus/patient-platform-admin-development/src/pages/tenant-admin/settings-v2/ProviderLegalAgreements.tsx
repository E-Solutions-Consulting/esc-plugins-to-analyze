import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileText, Loader2, Save } from "lucide-react";
import { toast } from "sonner";

import { HtmlEditor } from "@/components/common/HtmlEditor";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuditLog } from "@/hooks/useAuditLog";
import { supabase } from "@/integrations/supabase/client";
import type { Tables } from "@/integrations/supabase/types";
import { toNullableRichTextHtml } from "@/lib/html-content";
import { useAuth } from "@/stores/authStore";

type PlatformIntegration = Tables<"platform_integrations">;
type TenantIntegration = Tables<"tenant_integrations">;

export function ProviderLegalAgreements() {
  const { currentTenantId, isTenantAdmin, isPlatformSuperadmin } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [agreementValues, setAgreementValues] = useState<Record<string, string>>(
    {},
  );

  const canManageAgreements = Boolean(
    currentTenantId && (isTenantAdmin || isPlatformSuperadmin),
  );

  const { data: platformIntegrations = [], isLoading: isLoadingPlatforms } =
    useQuery({
      queryKey: ["platform-integrations", "provider-platforms"],
      queryFn: async () => {
        const { data, error } = await supabase
          .from("platform_integrations")
          .select("*")
          .eq("category", "provider_platform")
          .eq("is_active", true)
          .order("name");

        if (error) throw error;
        return data as PlatformIntegration[];
      },
    });

  const { data: tenantIntegrations = [], isLoading: isLoadingTenantIntegrations } =
    useQuery({
      queryKey: ["tenant-integrations", currentTenantId],
      queryFn: async () => {
        if (!currentTenantId) return [];

        const { data, error } = await supabase
          .from("tenant_integrations")
          .select("*")
          .eq("tenant_id", currentTenantId)
          .eq("is_enabled", true);

        if (error) throw error;
        return data as TenantIntegration[];
      },
      enabled: Boolean(currentTenantId),
    });

  const enabledProviderIntegrations = useMemo(() => {
    const providerIntegrationByKey = new Map(
      platformIntegrations.map((integration) => [integration.key, integration]),
    );

    return tenantIntegrations
      .map((tenantIntegration) => ({
        tenantIntegration,
        platformIntegration: providerIntegrationByKey.get(
          tenantIntegration.integration_key,
        ),
      }))
      .filter(
        (
          entry,
        ): entry is {
          tenantIntegration: TenantIntegration;
          platformIntegration: PlatformIntegration;
        } => Boolean(entry.platformIntegration),
      )
      .sort((a, b) =>
        a.platformIntegration.name.localeCompare(b.platformIntegration.name),
      );
  }, [platformIntegrations, tenantIntegrations]);

  useEffect(() => {
    setAgreementValues(
      Object.fromEntries(
        enabledProviderIntegrations.map(({ tenantIntegration }) => [
          tenantIntegration.id,
          tenantIntegration.provider_legal_agreement ?? "",
        ]),
      ),
    );
  }, [enabledProviderIntegrations]);

  const updateAgreement = useMutation({
    mutationFn: async ({
      tenantIntegration,
      content,
    }: {
      tenantIntegration: TenantIntegration;
      content: string;
    }) => {
      const providerLegalAgreement = toNullableRichTextHtml(content);

      const { data, error } = await supabase
        .from("tenant_integrations")
        .update({ provider_legal_agreement: providerLegalAgreement })
        .eq("id", tenantIntegration.id)
        .select("*")
        .single();

      if (error) throw error;

      return {
        before: tenantIntegration,
        after: data as TenantIntegration,
      };
    },
    onSuccess: async ({ before, after }) => {
      queryClient.invalidateQueries({
        queryKey: ["tenant-integrations", currentTenantId],
      });

      await logAction({
        action: "update",
        entityType: "tenant_integration",
        entityId: after.id,
        beforeData: {
          integration_key: before.integration_key,
          provider_legal_agreement: before.provider_legal_agreement,
        },
        afterData: {
          integration_key: after.integration_key,
          provider_legal_agreement: after.provider_legal_agreement,
        },
        tenantId: after.tenant_id,
      });

      toast.success("Provider legal agreement saved");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save provider legal agreement",
      );
    },
  });

  const isLoading = isLoadingPlatforms || isLoadingTenantIntegrations;

  if (!currentTenantId) {
    return (
      <Card>
        <CardContent className="py-10 text-center text-muted-foreground">
          Select a tenant to manage provider legal agreements.
        </CardContent>
      </Card>
    );
  }

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-36 w-full" />
        <Skeleton className="h-36 w-full" />
      </div>
    );
  }

  if (enabledProviderIntegrations.length === 0) {
    return (
      <Card>
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
              <FileText className="h-5 w-5 text-primary" />
            </div>
            <div>
              <CardTitle>Provider Legal Agreement</CardTitle>
              <CardDescription>
                Enable a provider platform before adding provider agreement copy.
              </CardDescription>
            </div>
          </div>
        </CardHeader>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {enabledProviderIntegrations.map(
        ({ platformIntegration, tenantIntegration }) => {
          const value =
            agreementValues[tenantIntegration.id] ??
            tenantIntegration.provider_legal_agreement ??
            "";
          const isSaving =
            updateAgreement.isPending &&
            updateAgreement.variables?.tenantIntegration.id ===
              tenantIntegration.id;

          return (
            <Card key={tenantIntegration.id}>
              <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <CardTitle>{platformIntegration.name}</CardTitle>
                    <CardDescription>
                      Agreement HTML returned for this provider's agreement
                      questionnaire questions.
                    </CardDescription>
                  </div>
                  <Button
                    type="button"
                    onClick={() =>
                      updateAgreement.mutate({
                        tenantIntegration,
                        content: value,
                      })
                    }
                    disabled={!canManageAgreements || isSaving}
                  >
                    {isSaving ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                      <Save className="mr-2 h-4 w-4" />
                    )}
                    Save
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                <HtmlEditor
                  id={`provider-legal-agreement-${tenantIntegration.id}`}
                  value={value}
                  onChange={(nextValue) =>
                    setAgreementValues((currentValues) => ({
                      ...currentValues,
                      [tenantIntegration.id]: nextValue,
                    }))
                  }
                  placeholder="Enter the provider legal agreement HTML..."
                  disabled={!canManageAgreements || isSaving}
                  minHeightClassName="min-h-72"
                />
              </CardContent>
            </Card>
          );
        },
      )}
    </div>
  );
}
