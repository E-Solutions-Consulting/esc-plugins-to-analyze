import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Database } from "lucide-react";
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
import { usePlatformSettings } from "@/hooks/usePlatformSettings";
import { supabase } from "@/integrations/supabase/client";

type RTDHConfig = {
  api_url?: string;
  base_url?: string;
  patient_platform_webhook_secret?: string;
  patient_platform_receiver_secret?: string;
  secret_manager_receiver_secret?: string;
  patient_platform_consumer_webhook_token?: string;
  patient_platform_consumer_webhook_token_secret_ref?: string;
  secret_backend?: string;
};

const DEFAULT_CONFIG: RTDHConfig = {
  base_url: "",
};

export function RTDHSettings() {
  const { getSettingValue, updateRtdhConfig, isLoading } =
    usePlatformSettings();
  const savedConfig = getSettingValue<RTDHConfig>("rtdh_config") ??
    DEFAULT_CONFIG;
  const savedConsumerWebhookToken =
    savedConfig.patient_platform_consumer_webhook_token;
  const savedPatientPlatformWebhookSecret =
    savedConfig.patient_platform_webhook_secret ??
      savedConfig.patient_platform_receiver_secret;

  const [baseUrl, setBaseUrl] = useState("");
  const [receiverSecret, setReceiverSecret] = useState("");
  const [secretManagerReceiverSecret, setSecretManagerReceiverSecret] =
    useState("");
  const [consumerWebhookToken, setConsumerWebhookToken] = useState("");
  const consumerWebhookTokenStatus = useQuery({
    queryKey: [
      "rtdh-secret-status",
      "global",
      "patient_platform",
      "consumer_webhook_token",
    ],
    enabled: Boolean(savedConfig.base_url ?? savedConfig.api_url),
    staleTime: 30_000,
    queryFn: async () => {
      const { data, error } = await supabase.functions.invoke(
        "get-provider-rtdh-secret-status",
        {
          body: {
            provider: "patient_platform",
            key: "consumer_webhook_token",
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

  useEffect(() => {
    setBaseUrl(savedConfig.base_url ?? savedConfig.api_url ?? "");
    setReceiverSecret("");
    setSecretManagerReceiverSecret("");
    setConsumerWebhookToken("");
  }, [
    savedConfig.api_url,
    savedConfig.base_url,
    savedPatientPlatformWebhookSecret,
    savedConfig.secret_manager_receiver_secret,
    savedConsumerWebhookToken,
  ]);

  const isPatientPlatformWebhookSecretConfigured = Boolean(
    savedPatientPlatformWebhookSecret,
  );
  const isSecretManagerReceiverSecretConfigured = Boolean(
    savedConfig.secret_manager_receiver_secret,
  );
  const isConsumerWebhookTokenConfigured = consumerWebhookTokenStatus.data ??
    Boolean(
      savedConfig.patient_platform_consumer_webhook_token_secret_ref ||
        savedConsumerWebhookToken,
    );
  const isDirty =
    baseUrl !== (savedConfig.base_url ?? savedConfig.api_url ?? "") ||
    receiverSecret.trim().length > 0 ||
    secretManagerReceiverSecret.trim().length > 0 ||
    consumerWebhookToken.trim().length > 0;

  const handleSave = () => {
    updateRtdhConfig.mutate(
      {
        base_url: baseUrl.trim(),
        ...(receiverSecret.trim()
          ? { patient_platform_webhook_secret: receiverSecret.trim() }
          : {}),
        ...(secretManagerReceiverSecret.trim()
          ? {
            secret_manager_receiver_secret: secretManagerReceiverSecret.trim(),
          }
          : {}),
        ...(consumerWebhookToken.trim()
          ? {
            patient_platform_consumer_webhook_token: consumerWebhookToken
              .trim(),
          }
          : {}),
      },
      { onSuccess: () => consumerWebhookTokenStatus.refetch() },
    );
  };

  const isSaving = updateRtdhConfig.isPending;

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
            <Database className="h-5 w-5 text-primary" />
          </div>
          <div>
            <CardTitle>RealTime Data Hub</CardTitle>
            <CardDescription>
              Configure the RTDH base URL and webhook signing secrets.
            </CardDescription>
          </div>
          {savedConfig.secret_backend === "rtdh_secret_manager_interface" && (
            <Badge variant="secondary" className="ml-auto">
              RTDH Secret Manager
            </Badge>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="space-y-2">
          <Label htmlFor="rtdh_base_url">RTDH Base URL</Label>
          <Input
            id="rtdh_base_url"
            type="url"
            placeholder="https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net"
            value={baseUrl}
            onChange={(event) => setBaseUrl(event.target.value)}
            disabled={isLoading || isSaving}
          />
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Label htmlFor="rtdh_order_webhook_secret">
              RTDH Order Webhook Secret
            </Label>
            {isPatientPlatformWebhookSecretConfigured && (
              <Badge variant="outline">Configured</Badge>
            )}
          </div>
          <Input
            id="rtdh_order_webhook_secret"
            type="password"
            placeholder={isPatientPlatformWebhookSecretConfigured
              ? "Leave blank to keep current secret"
              : "Shared Patient Platform to RTDH order webhook signing secret"}
            value={receiverSecret}
            onChange={(event) => setReceiverSecret(event.target.value)}
            disabled={isLoading || isSaving}
            autoComplete="new-password"
          />
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Label htmlFor="secret_manager_webhook_secret">
              RTDH Secret Manager Webhook Secret
            </Label>
            {isSecretManagerReceiverSecretConfigured && (
              <Badge variant="outline">Configured</Badge>
            )}
          </div>
          <Input
            id="secret_manager_webhook_secret"
            type="password"
            placeholder={isSecretManagerReceiverSecretConfigured
              ? "Leave blank to keep current secret"
              : "Shared secret-manager signing secret"}
            value={secretManagerReceiverSecret}
            onChange={(event) =>
              setSecretManagerReceiverSecret(event.target.value)}
            disabled={isLoading || isSaving}
            autoComplete="new-password"
          />
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Label htmlFor="patient_platform_consumer_webhook_token">
              Patient Platform Consumer Webhook Token
            </Label>
            {isConsumerWebhookTokenConfigured && (
              <Badge variant="outline">Configured</Badge>
            )}
          </div>
          <Input
            id="patient_platform_consumer_webhook_token"
            type="password"
            placeholder={isConsumerWebhookTokenConfigured
              ? "Leave blank to keep current token"
              : "Shared RTDH to Patient Platform webhook signing token"}
            value={consumerWebhookToken}
            onChange={(event) => setConsumerWebhookToken(event.target.value)}
            disabled={isLoading || isSaving}
            autoComplete="new-password"
          />
        </div>

        <div className="flex justify-end">
          <Button
            onClick={handleSave}
            disabled={isLoading || isSaving || !isDirty}
          >
            Save Settings
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
