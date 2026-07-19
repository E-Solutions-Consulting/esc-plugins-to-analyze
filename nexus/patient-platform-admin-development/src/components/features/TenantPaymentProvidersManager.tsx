import { useState } from "react";
import { useTenantPaymentProviders } from "@/hooks/useTenantPaymentProviders";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Loader2,
  CreditCard,
  Settings2,
  Check,
  AlertCircle,
  Eye,
  EyeOff,
  ShieldCheck,
} from "lucide-react";

export function TenantPaymentProvidersManager() {
  const {
    providersWithConfig,
    isLoading,
    saveConfiguration,
    toggleProvider,
    saveRtdhWebhookSecret,
  } = useTenantPaymentProviders();
  const [configureProvider, setConfigureProvider] =
    useState<ProviderWithConfig | null>(null);
  const [formSettings, setFormSettings] = useState<Record<string, string>>({});
  const [formEnabled, setFormEnabled] = useState(false);
  const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({});
  const [rtdhSigningSecret, setRtdhSigningSecret] = useState("");

  const openConfigDialog = (provider: ProviderWithConfig) => {
    setConfigureProvider(provider);
    setFormSettings(provider.configuredSettings || {});
    setFormEnabled(provider.isEnabled);
    setShowSecrets({});
    setRtdhSigningSecret("");
  };

  const closeConfigDialog = () => {
    setConfigureProvider(null);
    setFormSettings({});
    setFormEnabled(false);
    setShowSecrets({});
    setRtdhSigningSecret("");
  };

  const handleSaveConfiguration = async () => {
    if (!configureProvider) return;

    try {
      await saveConfiguration.mutateAsync({
        providerId: configureProvider.id,
        isEnabled: formEnabled,
        settings: formSettings,
      });

      const stripeRtdhSecret = rtdhSigningSecret.trim();

      if (configureProvider.key === "stripe" && stripeRtdhSecret.length > 0) {
        await saveRtdhWebhookSecret.mutateAsync({
          providerKey: configureProvider.key,
          value: stripeRtdhSecret,
        });
      }

      closeConfigDialog();
    } catch {
      // The individual mutations surface their own toast messages.
    }
  };

  const handleToggleProvider = (provider: ProviderWithConfig) => {
    toggleProvider.mutate({
      providerId: provider.id,
      isEnabled: !provider.isEnabled,
    });
  };

  const toggleSecretVisibility = (key: string) => {
    setShowSecrets((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const handleSaveRtdhSigningSecret = () => {
    if (!configureProvider) return;
    saveRtdhWebhookSecret.mutate(
      {
        providerKey: configureProvider.key,
        value: rtdhSigningSecret,
      },
      {
        onSuccess: () => {
          setRtdhSigningSecret("");
        },
      },
    );
  };

  const isConfigurationComplete = (provider: ProviderWithConfig): boolean => {
    const requiredSettings = provider.required_settings.filter(
      (s) => s.required,
    );
    return requiredSettings.every((setting) => {
      const value = provider.configuredSettings[setting.key];
      return value && value.trim().length > 0;
    });
  };

  const renderSettingInput = (setting: RequiredSetting) => {
    const value = formSettings[setting.key] || "";
    const isSecret = setting.type === "secret";
    const isVisible = showSecrets[setting.key];

    if (setting.type === "select" && setting.options) {
      return (
        <Select
          value={value}
          onValueChange={(val) =>
            setFormSettings({ ...formSettings, [setting.key]: val })
          }
        >
          <SelectTrigger>
            <SelectValue
              placeholder={setting.placeholder || `Select ${setting.label}`}
            />
          </SelectTrigger>
          <SelectContent>
            {setting.options.map((option) => (
              <SelectItem key={option} value={option}>
                {option}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      );
    }

    return (
      <div className="relative">
        <Input
          type={isSecret && !isVisible ? "password" : "text"}
          value={value}
          onChange={(e) =>
            setFormSettings({ ...formSettings, [setting.key]: e.target.value })
          }
          placeholder={setting.placeholder}
        />
        {isSecret && (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="absolute right-0 top-0 h-full px-3"
            onClick={() => toggleSecretVisibility(setting.key)}
          >
            {isVisible ? (
              <EyeOff className="h-4 w-4" />
            ) : (
              <Eye className="h-4 w-4" />
            )}
          </Button>
        )}
      </div>
    );
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (!providersWithConfig.length) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <CreditCard className="h-12 w-12 text-muted-foreground mb-4" />
          <h3 className="text-lg font-semibold mb-2">
            No Payment Providers Available
          </h3>
          <p className="text-muted-foreground text-center">
            No payment providers have been configured by the platform
            administrator yet.
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <div className="space-y-4">
        {providersWithConfig.map((provider) => {
          const isComplete = isConfigurationComplete(provider);
          const hasRequiredSettings = provider.required_settings.length > 0;

          return (
            <Card
              key={provider.id}
              className={provider.isEnabled ? "border-primary" : ""}
            >
              <CardHeader>
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-center gap-3">
                    {provider.logo_url ? (
                      <img
                        src={provider.logo_url}
                        alt={provider.name}
                        className="h-10 w-10 rounded object-contain"
                      />
                    ) : (
                      <div className="h-10 w-10 rounded bg-muted flex items-center justify-center">
                        <CreditCard className="h-5 w-5 text-muted-foreground" />
                      </div>
                    )}
                    <div className="space-y-1">
                      <CardTitle className="text-base">
                        {provider.name}
                      </CardTitle>
                      {provider.description && (
                        <CardDescription>
                          {provider.description}
                        </CardDescription>
                      )}
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="text-xs">
                          {provider.key}
                        </Badge>
                        {provider.isEnabled ? (
                          <Badge variant="default">Enabled</Badge>
                        ) : (
                          <Badge variant="secondary">Disabled</Badge>
                        )}
                        {hasRequiredSettings ? (
                          isComplete ? (
                            <Badge variant="default" className="bg-green-600">
                              <Check className="mr-1 h-3 w-3" />
                              Configured
                            </Badge>
                          ) : (
                            <Badge variant="secondary">
                              <AlertCircle className="mr-1 h-3 w-3" />
                              Configuration required
                            </Badge>
                          )
                        ) : null}
                      </div>
                    </div>
                  </div>
                  <Switch
                    checked={provider.isEnabled}
                    onCheckedChange={() => handleToggleProvider(provider)}
                    disabled={toggleProvider.isPending}
                  />
                </div>
              </CardHeader>
              <CardContent className="pt-0">
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full sm:w-auto"
                  onClick={() => openConfigDialog(provider)}
                >
                  <Settings2 className="h-4 w-4 mr-2" />
                  Configure
                </Button>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Configuration Dialog */}
      <Dialog
        open={!!configureProvider}
        onOpenChange={(open) => !open && closeConfigDialog()}
      >
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              {configureProvider?.logo_url ? (
                <img
                  src={configureProvider.logo_url}
                  alt={configureProvider.name}
                  className="h-6 w-6 rounded object-contain"
                />
              ) : (
                <CreditCard className="h-6 w-6" />
              )}
              Configure {configureProvider?.name}
            </DialogTitle>
            <DialogDescription>
              Enter your API credentials and settings for{" "}
              {configureProvider?.name}
            </DialogDescription>
          </DialogHeader>

          {configureProvider && (
            <div className="space-y-4 py-4">
              <div className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                  <Label>Enable Provider</Label>
                  <p className="text-sm text-muted-foreground">
                    Allow this provider to process payments
                  </p>
                </div>
                <Switch
                  checked={formEnabled}
                  onCheckedChange={setFormEnabled}
                />
              </div>

              {configureProvider.required_settings.length > 0 && (
                <div className="space-y-4">
                  <h4 className="font-medium text-sm">Required Settings</h4>
                  {configureProvider.required_settings.map((setting) => (
                    <div key={setting.key} className="space-y-2">
                      <Label htmlFor={setting.key}>
                        {setting.label}
                        {setting.required && (
                          <span className="text-destructive ml-1">*</span>
                        )}
                      </Label>
                      {renderSettingInput(setting)}
                    </div>
                  ))}
                </div>
              )}

              {configureProvider.key === "stripe" && (
                <div className="space-y-3 rounded-lg border p-4">
                  <div className="space-y-1">
                    <p className="flex items-center gap-2 text-sm font-medium">
                      <ShieldCheck className="h-4 w-4 text-emerald-600" />
                      RTDH signing secret
                    </p>
                    <p className="text-sm text-muted-foreground">
                      Secret RTDH uses to verify inbound Stripe webhook events.
                      This is write-only and tenant-scoped in RTDH.
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="stripe-rtdh-signing-secret">
                      New signing secret
                    </Label>
                    <Input
                      id="stripe-rtdh-signing-secret"
                      type="password"
                      value={rtdhSigningSecret}
                      onChange={(event) =>
                        setRtdhSigningSecret(event.target.value)
                      }
                      placeholder="whsec_..."
                      className="font-mono text-xs"
                      autoComplete="new-password"
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      type="button"
                      size="sm"
                      onClick={handleSaveRtdhSigningSecret}
                      disabled={
                        saveRtdhWebhookSecret.isPending ||
                        rtdhSigningSecret.trim().length < 8
                      }
                    >
                      {saveRtdhWebhookSecret.isPending && (
                        <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                      )}
                      Set / rotate secret
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        const bytes = new Uint8Array(32);
                        crypto.getRandomValues(bytes);
                        setRtdhSigningSecret(
                          Array.from(bytes)
                            .map((byte) => byte.toString(16).padStart(2, "0"))
                            .join(""),
                        );
                      }}
                    >
                      Generate
                    </Button>
                  </div>
                </div>
              )}
            </div>
          )}

          <DialogFooter>
            <Button variant="outline" onClick={closeConfigDialog}>
              Cancel
            </Button>
            <Button
              onClick={handleSaveConfiguration}
              disabled={
                saveConfiguration.isPending ||
                saveRtdhWebhookSecret.isPending ||
                (configureProvider?.key === "stripe" &&
                  rtdhSigningSecret.trim().length > 0 &&
                  rtdhSigningSecret.trim().length < 8)
              }
            >
              {(saveConfiguration.isPending ||
                saveRtdhWebhookSecret.isPending) && (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              )}
              Save Configuration
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
