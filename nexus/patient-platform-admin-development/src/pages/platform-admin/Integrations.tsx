import { useState } from "react";
import { useMutation, useQueries, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Building2,
  CheckCircle2,
  Copy,
  CreditCard,
  ExternalLink,
  Gift,
  Globe,
  Headset,
  Image,
  Loader2,
  Mail,
  Package,
  Plug,
  Star,
  Settings,
  TriangleAlert,
  Trash2,
} from "lucide-react";
import { useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "@/hooks/useAuditLog";
import { PaymentProvidersManager } from "@/components/features/PaymentProvidersManager";
import { ImageUpload } from "@/components/common/ImageUpload";
import { PageHeader } from "@/components/common/PageHeader";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  formatIntegrationSettingLabel,
  getRequiredSettingsForIntegration,
  hasConfiguredRequiredSettings,
  integrationCategoryDescriptions,
  integrationCategoryLabels,
  integrationCategoryOrder,
} from "@/lib/integration-config";

const BRAND_ASSETS_BUCKET = "brand-assets";

const integrationIcons: Record<string, React.ReactNode> = {
  resend: <Mail className="h-5 w-5" />,
  telegramd: <Globe className="h-5 w-5" />,
  zito_care: <Building2 className="h-5 w-5" />,
  md_integrations: <Building2 className="h-5 w-5" />,
  intercom: <Headset className="h-5 w-5" />,
  easypost: <Package className="h-5 w-5" />,
  friendbuy: <Gift className="h-5 w-5" />,
};

function getStoragePathFromPublicUrl(url: string, bucket: string) {
  try {
    const parsedUrl = new URL(url);
    const pathParts = parsedUrl.pathname.split("/");
    const bucketIndex = pathParts.findIndex((part) => part === bucket);
    return bucketIndex === -1 ? null : pathParts.slice(bucketIndex + 1).join("/");
  } catch {
    return null;
  }
}

function ProviderLogoManager({
  integration,
}: {
  integration: PlatformIntegration;
}) {
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [logoUrl, setLogoUrl] = useState<string | null>(null);

  const { data: logoAssets = [], isLoading } = useQuery({
    queryKey: ["provider-logo-assets", integration.id],
    queryFn: async () => {
      const { data, error } = await supabase
        .from("provider_logo_assets")
        .select("*")
        .eq("platform_integration_id", integration.id)
        .order("is_default", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) throw error;
      return data as ProviderLogoAsset[];
    },
  });

  const resetForm = () => {
    setLogoUrl(null);
  };

  const invalidateLogos = () => {
    queryClient.invalidateQueries({
      queryKey: ["provider-logo-assets", integration.id],
    });
    queryClient.invalidateQueries({ queryKey: ["platform-integrations"] });
  };

  const createLogoMutation = useMutation({
    mutationFn: async () => {
      const normalizedLogoUrl = logoUrl?.trim();

      if (!normalizedLogoUrl) {
        throw new Error("Upload a logo before saving");
      }

      const shouldMakeDefault = logoAssets.length === 0;

      if (shouldMakeDefault) {
        const { error: unsetError } = await supabase
          .from("provider_logo_assets")
          .update({ is_default: false })
          .eq("platform_integration_id", integration.id);

        if (unsetError) throw unsetError;
      }

      const { data, error } = await supabase
        .from("provider_logo_assets")
        .insert({
          platform_integration_id: integration.id,
          logo_url: normalizedLogoUrl,
          is_default: shouldMakeDefault,
        })
        .select()
        .single();

      if (error) throw error;
      return data as ProviderLogoAsset;
    },
    onSuccess: (asset) => {
      invalidateLogos();
      logAction({
        action: "create",
        entityType: "provider_logo_asset",
        entityId: asset.id,
        afterData: asset as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Provider logo saved");
      resetForm();
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Failed to save logo");
    },
  });

  const setDefaultLogoMutation = useMutation({
    mutationFn: async (asset: ProviderLogoAsset) => {
      const { error: unsetError } = await supabase
        .from("provider_logo_assets")
        .update({ is_default: false })
        .eq("platform_integration_id", integration.id);

      if (unsetError) throw unsetError;

      const { error: setError } = await supabase
        .from("provider_logo_assets")
        .update({ is_default: true })
        .eq("id", asset.id);

      if (setError) throw setError;
      return asset;
    },
    onSuccess: (asset) => {
      invalidateLogos();
      logAction({
        action: "update",
        entityType: "provider_logo_asset",
        entityId: asset.id,
        afterData: { is_default: true, integration_key: integration.key },
        tenantId: null,
      });
      toast.success("Default provider logo updated");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update default logo",
      );
    },
  });

  const deleteLogoMutation = useMutation({
    mutationFn: async (asset: ProviderLogoAsset) => {
      if (asset.is_default) {
        throw new Error("Choose another default logo before deleting this one");
      }

      const { error } = await supabase
        .from("provider_logo_assets")
        .delete()
        .eq("id", asset.id);

      if (error) throw error;

      const storagePath = getStoragePathFromPublicUrl(
        asset.logo_url,
        BRAND_ASSETS_BUCKET,
      );
      if (storagePath) {
        await supabase.storage.from(BRAND_ASSETS_BUCKET).remove([storagePath]);
      }

      return asset;
    },
    onSuccess: (asset) => {
      invalidateLogos();
      logAction({
        action: "delete",
        entityType: "provider_logo_asset",
        entityId: asset.id,
        beforeData: asset as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Provider logo deleted");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to delete provider logo",
      );
    },
  });

  const handleCopyUrl = async (url: string) => {
    await navigator.clipboard.writeText(url);
    toast.success("Image URL copied");
  };

  const handleOpenUrl = (url: string) => {
    window.open(url, "_blank", "noopener,noreferrer");
  };

  return (
    <div className="min-w-0 space-y-6 overflow-hidden">
      <div className="flex min-w-0 flex-col items-center gap-5 overflow-hidden rounded-lg border p-6 text-center">
        <div className="space-y-3">
          <Label>Logo Asset</Label>
          <ImageUpload
            bucket={BRAND_ASSETS_BUCKET}
            folder={`provider-platforms/${integration.key}`}
            value={logoUrl}
            onChange={setLogoUrl}
            className="flex flex-col items-center"
            previewClassName="h-32 w-72 max-w-full"
          />
        </div>
        <div>
          <Button
            onClick={() => createLogoMutation.mutate()}
            disabled={createLogoMutation.isPending}
          >
            {createLogoMutation.isPending ? (
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            ) : null}
            Save Logo
          </Button>
        </div>
      </div>

      <div className="min-w-0 divide-y overflow-hidden rounded-lg border">
        <div className="bg-muted/50 p-3 text-sm font-medium">
          Logo Options
        </div>
        {isLoading ? (
          <div className="flex items-center justify-center p-6">
            <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
          </div>
        ) : logoAssets.length === 0 ? (
          <div className="p-6 text-center text-sm text-muted-foreground">
            No logos have been added for this provider.
          </div>
        ) : (
          logoAssets.map((asset) => (
            <div
              key={asset.id}
              className="flex min-w-0 flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="flex min-w-0 items-center gap-3">
                <img
                  src={asset.logo_url}
                  alt={`${integration.name} logo`}
                  className="h-14 w-20 shrink-0 rounded-md border bg-white object-contain p-2"
                />
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    {asset.is_default ? (
                      <Badge className="bg-green-600">
                        <Star className="mr-1 h-3 w-3" />
                        Default
                      </Badge>
                    ) : null}
                  </div>
                  <div className="mt-1 flex min-w-0 max-w-full items-center gap-2">
                    <code
                      className="block min-w-0 flex-1 overflow-x-auto whitespace-nowrap rounded border bg-muted/40 px-2 py-1 text-xs text-muted-foreground"
                      title={asset.logo_url}
                    >
                      {asset.logo_url}
                    </code>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 shrink-0"
                      onClick={() => handleCopyUrl(asset.logo_url)}
                      aria-label="Copy image URL"
                    >
                      <Copy className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 shrink-0"
                      onClick={() => handleOpenUrl(asset.logo_url)}
                      aria-label="Open image URL"
                    >
                      <ExternalLink className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
              <div className="flex shrink-0 gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setDefaultLogoMutation.mutate(asset)}
                  disabled={asset.is_default || setDefaultLogoMutation.isPending}
                >
                  <Star className="mr-2 h-4 w-4" />
                  Set Default
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => deleteLogoMutation.mutate(asset)}
                  disabled={asset.is_default || deleteLogoMutation.isPending}
                >
                  <Trash2 className="mr-2 h-4 w-4" />
                  Delete
                </Button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

export function PlatformIntegrationsContent() {
  const { isPlatformSuperadmin } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const [selectedTenantId, setSelectedTenantId] = useState<string>("");

  const [
    { data: integrations = [], isLoading: integrationsLoading },
    { data: tenants = [] },
    { data: tenantIntegrations = [], refetch: refetchTenantIntegrations },
  ] = useQueries({
    queries: [
      {
        queryKey: ["platform-integrations"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("platform_integrations")
            .select("*")
            .order("name");

          if (error) throw error;
          return data as PlatformIntegration[];
        },
      },
      {
        queryKey: ["tenants-list"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("tenants")
            .select("id, name, slug")
            .eq("status", "active")
            .order("name");

          if (error) throw error;
          return data as Tenant[];
        },
      },
      {
        queryKey: ["tenant-integrations-all"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("tenant_integrations")
            .select("*");

          if (error) throw error;
          return data as TenantIntegration[];
        },
      },
    ],
  });

  const toggleIntegrationMutation = useMutation({
    mutationFn: async ({ id, isActive }: { id: string; isActive: boolean }) => {
      const { error } = await supabase
        .from("platform_integrations")
        .update({ is_active: isActive })
        .eq("id", id);

      if (error) throw error;
      return { id, isActive };
    },
    onSuccess: ({ id, isActive }) => {
      queryClient.invalidateQueries({ queryKey: ["platform-integrations"] });
      const integration = integrations.find((item) => item.id === id);
      logAction({
        action: "update",
        entityType: "platform_integration",
        entityId: id,
        afterData: { key: integration?.key, is_active: isActive },
        tenantId: null,
      });
      toast.success(`Integration ${isActive ? "enabled" : "disabled"}`);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update integration",
      );
    },
  });

  const enableForTenantMutation = useMutation({
    mutationFn: async ({
      tenantId,
      integrationKey,
    }: {
      tenantId: string;
      integrationKey: string;
    }) => {
      const existing = tenantIntegrations.find(
        (tenantIntegration) =>
          tenantIntegration.tenant_id === tenantId &&
          tenantIntegration.integration_key === integrationKey,
      );

      if (existing) {
        const { error } = await supabase
          .from("tenant_integrations")
          .update({ is_enabled: !existing.is_enabled })
          .eq("id", existing.id);

        if (error) throw error;
        return { tenantId, integrationKey, enabled: !existing.is_enabled };
      }

      const { error } = await supabase
        .from("tenant_integrations")
        .insert([
          {
            tenant_id: tenantId,
            integration_key: integrationKey,
            is_enabled: true,
          },
        ]);

      if (error) throw error;
      return { tenantId, integrationKey, enabled: true };
    },
    onSuccess: ({ tenantId, integrationKey, enabled }) => {
      refetchTenantIntegrations();
      const tenant = tenants.find((item) => item.id === tenantId);
      logAction({
        action: enabled ? "enable" : "disable",
        entityType: "tenant_integration",
        entityId: tenantId,
        afterData: {
          integration_key: integrationKey,
          tenant_name: tenant?.name,
          enabled,
        },
        tenantId: null,
      });
      toast.success(
        `Integration ${enabled ? "enabled" : "disabled"} for ${tenant?.name}`,
      );
      setSelectedTenantId("");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to update tenant integration",
      );
    },
  });

  const getTenantIntegrationStatus = (
    tenantId: string,
    integrationKey: string,
  ) => {
    return tenantIntegrations.find(
      (tenantIntegration) =>
        tenantIntegration.tenant_id === tenantId &&
        tenantIntegration.integration_key === integrationKey,
    );
  };

  const getIntegrationsForCategory = (category: string) =>
    integrations.filter((integration) => integration.category === category);

  const providerIntegrations = getIntegrationsForCategory("provider_platform");
  const emailDistributionIntegrations =
    getIntegrationsForCategory("email_distribution");
  const customerSupportIntegrations =
    getIntegrationsForCategory("customer_support");
  const additionalCategoryTabs = integrationCategoryOrder
    .filter(
      (category) =>
        ![
          "provider_platform",
          "email_distribution",
          "customer_support",
        ].includes(category),
    )
    .map((category) => ({
      value: category,
      label: integrationCategoryLabels[category] || category,
      description:
        integrationCategoryDescriptions[category] ||
        "Manage platform integrations.",
      integrations: getIntegrationsForCategory(category),
    }))
    .filter((section) => section.integrations.length > 0);
  const requestedTab = searchParams.get("tab") || "";
  const availableTabValues = [
    "provider-platform",
    "email-distribution",
    "customer-support",
    "payment-providers",
    ...additionalCategoryTabs.map((section) => section.value),
  ];
  const initialTab = availableTabValues.includes(requestedTab)
    ? requestedTab
    : "provider-platform";

  const renderEmptyState = (message: string) => (
    <Card>
      <CardContent className="flex flex-col items-center justify-center py-12">
        <Plug className="mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">{message}</p>
      </CardContent>
    </Card>
  );

  const renderIntegrationCards = (
    sectionIntegrations: PlatformIntegration[],
    emptyMessage: string,
  ) => {
    if (sectionIntegrations.length === 0) {
      return renderEmptyState(emptyMessage);
    }

    return (
      <div className="space-y-6">
        {sectionIntegrations.map((integration) => {
          const requiredSettings = getRequiredSettingsForIntegration(
            integration.key,
            integration.required_settings,
          );

          return (
            <Card key={integration.id}>
              <CardHeader>
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                      {integrationIcons[integration.key] || (
                        <Plug className="h-5 w-5 text-primary" />
                      )}
                    </div>
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <CardTitle className="text-lg">
                          {integration.name}
                        </CardTitle>
                        <Badge
                          variant={
                            integration.is_active ? "default" : "secondary"
                          }
                        >
                          {integration.is_active ? "Active" : "Inactive"}
                        </Badge>
                        <Badge variant="outline">
                          {integrationCategoryLabels[integration.category] ||
                            integration.category}
                        </Badge>
                      </div>
                      <CardDescription>
                        {integration.description}
                      </CardDescription>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    {integration.category === "provider_platform" ? (
                      <Dialog>
                        <DialogTrigger asChild>
                          <Button variant="outline" size="sm">
                            <Image className="mr-2 h-4 w-4" />
                            Manage Logos
                          </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[85vh] max-w-3xl overflow-hidden">
                          <DialogHeader>
                            <DialogTitle>
                              Manage {integration.name} Logos
                            </DialogTitle>
                            <DialogDescription>
                              Upload provider logo options and choose the
                              default logo exposed to patient-facing apps.
                            </DialogDescription>
                          </DialogHeader>
                          <div className="max-h-[calc(85vh-8rem)] overflow-y-auto pr-1">
                            <ProviderLogoManager integration={integration} />
                          </div>
                        </DialogContent>
                      </Dialog>
                    ) : null}
                    <Dialog>
                      <DialogTrigger asChild>
                        <Button variant="outline" size="sm">
                          <Settings className="mr-2 h-4 w-4" />
                          {integration.category === "provider_platform"
                            ? "View Tenant Status"
                            : "Manage Tenants"}
                        </Button>
                      </DialogTrigger>
                      <DialogContent className="max-w-2xl">
                        <DialogHeader>
                          <DialogTitle>
                            {integration.category === "provider_platform"
                              ? `${integration.name} Tenant Status`
                              : `Manage ${integration.name} for Tenants`}
                          </DialogTitle>
                          <DialogDescription>
                            {integration.category === "provider_platform"
                              ? "Tenants enable this provider integration and manage their own credentials."
                              : "Enable or disable this integration for specific tenants."}
                          </DialogDescription>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                          {integration.category !== "provider_platform" ? (
                            <div className="flex gap-2">
                              <Select
                                value={selectedTenantId}
                                onValueChange={setSelectedTenantId}
                              >
                                <SelectTrigger className="flex-1">
                                  <SelectValue placeholder="Select a tenant to enable" />
                                </SelectTrigger>
                                <SelectContent>
                                  {tenants
                                    .filter(
                                      (tenant) =>
                                        !getTenantIntegrationStatus(
                                          tenant.id,
                                          integration.key,
                                        )?.is_enabled,
                                    )
                                    .map((tenant) => (
                                      <SelectItem
                                        key={tenant.id}
                                        value={tenant.id}
                                      >
                                        {tenant.name}
                                      </SelectItem>
                                    ))}
                                </SelectContent>
                              </Select>
                              <Button
                                disabled={
                                  !selectedTenantId ||
                                  enableForTenantMutation.isPending
                                }
                                onClick={() => {
                                  if (!selectedTenantId) return;

                                  enableForTenantMutation.mutate({
                                    tenantId: selectedTenantId,
                                    integrationKey: integration.key,
                                  });
                                }}
                              >
                                Enable
                              </Button>
                            </div>
                          ) : null}

                          <div className="divide-y rounded-lg border">
                            <div className="bg-muted/50 p-3 text-sm font-medium">
                              {integration.category === "provider_platform"
                                ? "Tenants Using This Integration"
                                : "Enabled Tenants"}
                            </div>
                            {tenants
                              .filter(
                                (tenant) =>
                                  getTenantIntegrationStatus(
                                    tenant.id,
                                    integration.key,
                                  )?.is_enabled,
                              )
                              .map((tenant) => {
                                const status = getTenantIntegrationStatus(
                                  tenant.id,
                                  integration.key,
                                );
                                const isConfigured =
                                  hasConfiguredRequiredSettings(
                                    status?.settings,
                                    requiredSettings,
                                    integration.key,
                                  );

                                return (
                                  <div
                                    key={tenant.id}
                                    className="flex items-center justify-between p-3"
                                  >
                                    <div className="flex items-center gap-2">
                                      <span>{tenant.name}</span>
                                      {isConfigured ? (
                                        <Badge
                                          variant="outline"
                                          className="border-green-600 text-green-600"
                                        >
                                          <CheckCircle2 className="mr-1 h-3 w-3" />
                                          Configured
                                        </Badge>
                                      ) : (
                                        <Badge
                                          variant="outline"
                                          className="border-yellow-600 text-yellow-600"
                                        >
                                          <TriangleAlert className="mr-1 h-3 w-3" />
                                          Missing Required Settings
                                        </Badge>
                                      )}
                                    </div>
                                    {integration.category !==
                                    "provider_platform" ? (
                                      <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                          enableForTenantMutation.mutate({
                                            tenantId: tenant.id,
                                            integrationKey: integration.key,
                                          })
                                        }
                                      >
                                        Disable
                                      </Button>
                                    ) : null}
                                  </div>
                                );
                              })}
                            {tenants.filter(
                              (tenant) =>
                                getTenantIntegrationStatus(
                                  tenant.id,
                                  integration.key,
                                )?.is_enabled,
                            ).length === 0 ? (
                              <div className="p-4 text-center text-muted-foreground">
                                {integration.category === "provider_platform"
                                  ? "No tenants have enabled this integration yet"
                                  : "No tenants have this integration enabled"}
                              </div>
                            ) : null}
                          </div>
                        </div>
                      </DialogContent>
                    </Dialog>
                    <Switch
                      checked={integration.is_active}
                      onCheckedChange={(checked) =>
                        toggleIntegrationMutation.mutate({
                          id: integration.id,
                          isActive: checked,
                        })
                      }
                      disabled={toggleIntegrationMutation.isPending}
                    />
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                  <span>Required settings:</span>
                  {requiredSettings.map((setting) => (
                    <Badge key={setting} variant="outline">
                      {formatIntegrationSettingLabel(setting)}
                    </Badge>
                  ))}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    );
  };

  if (!isPlatformSuperadmin) {
    return (
      <>
        <div className="flex h-full items-center justify-center">
          <p className="text-muted-foreground">
            Access denied. Platform Superadmin role required.
          </p>
        </div>
      </>
    );
  }

  if (integrationsLoading) {
    return (
      <>
        <div className="flex h-64 items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Integrations"
        description="Manage third-party service integrations for the platform"
      />

      <Tabs defaultValue={initialTab} className="space-y-6">
        <TabsList className="flex h-auto w-full flex-wrap justify-start gap-2 rounded-xl bg-muted/50 p-1">
          <TabsTrigger value="provider-platform" className="gap-2">
            <Building2 className="h-4 w-4" />
            Provider Platform
          </TabsTrigger>
          <TabsTrigger value="email-distribution" className="gap-2">
            <Mail className="h-4 w-4" />
            Email Distribution
          </TabsTrigger>
          <TabsTrigger value="customer-support" className="gap-2">
            <Headset className="h-4 w-4" />
            Customer Support
          </TabsTrigger>
          <TabsTrigger value="payment-providers" className="gap-2">
            <CreditCard className="h-4 w-4" />
            Payment Providers
          </TabsTrigger>
          {additionalCategoryTabs.map((section) => (
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

        <TabsContent value="provider-platform" className="space-y-4">
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">
              {integrationCategoryLabels.provider_platform ||
                "Provider Platform"}
            </h2>
            <p className="text-sm text-muted-foreground">
              {integrationCategoryDescriptions.provider_platform ||
                "Manage provider platform integrations."}
            </p>
          </div>
          {renderIntegrationCards(
            providerIntegrations,
            "No provider platform integrations configured",
          )}
        </TabsContent>

        <TabsContent value="email-distribution" className="space-y-4">
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">
              {integrationCategoryLabels.email_distribution ||
                "Email Distribution"}
            </h2>
            <p className="text-sm text-muted-foreground">
              {integrationCategoryDescriptions.email_distribution ||
                "Manage email distribution integrations."}
            </p>
          </div>
          {renderIntegrationCards(
            emailDistributionIntegrations,
            "No email distribution integrations configured",
          )}
        </TabsContent>

        <TabsContent value="customer-support" className="space-y-4">
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">
              {integrationCategoryLabels.customer_support || "Customer Support"}
            </h2>
            <p className="text-sm text-muted-foreground">
              {integrationCategoryDescriptions.customer_support ||
                "Manage customer support and communication integrations."}
            </p>
          </div>
          {renderIntegrationCards(
            customerSupportIntegrations,
            "No customer support integrations configured",
          )}
        </TabsContent>

        <TabsContent value="payment-providers" className="space-y-4">
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">Payment Providers</h2>
            <p className="text-sm text-muted-foreground">
              Configure payment providers available for tenants to use.
            </p>
          </div>
          <PaymentProvidersManager />
        </TabsContent>

        {additionalCategoryTabs.map((section) => (
          <TabsContent
            key={section.value}
            value={section.value}
            className="space-y-4"
          >
            <div className="space-y-1">
              <h2 className="text-xl font-semibold">{section.label}</h2>
              <p className="text-sm text-muted-foreground">
                {section.description}
              </p>
            </div>
            {renderIntegrationCards(
              section.integrations,
              `No ${section.label.toLowerCase()} integrations configured`,
            )}
          </TabsContent>
        ))}
      </Tabs>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function PlatformIntegrations() {
  return (
    <AdminLayout variant="platform">
      <PlatformIntegrationsContent />
    </AdminLayout>
  );
}
