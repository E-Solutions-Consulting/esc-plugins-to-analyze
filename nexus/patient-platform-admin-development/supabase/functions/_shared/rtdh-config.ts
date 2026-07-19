export type RtdhConfig = {
  api_url: string;
  access_token: string;
  consumer_secret: string;
  base_url: string;
  patient_platform_webhook_secret: string;
  patient_platform_receiver_secret: string;
  secret_manager_receiver_secret: string;
  patient_platform_consumer_webhook_token: string;
  patient_platform_consumer_webhook_token_secret_ref?: string;
  access_token_secret_ref?: string;
  consumer_secret_secret_ref?: string;
  secret_backend?: string;
  secret_tenant?: string;
  secret_metadata?: Record<string, unknown>;
};

export function asRtdhConfigRecord(
  value: unknown,
): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }
  return value as Record<string, unknown>;
}

export function getTrimmedString(
  record: Record<string, unknown> | null,
  key: string,
): string {
  const value = record?.[key];
  return typeof value === "string" ? value.trim() : "";
}

function resolveLocalValue(
  config: Record<string, unknown> | null,
  envKey: string,
  plaintextFallbackKey: string,
): string {
  let envValue = "";
  try {
    envValue = Deno.env.get(envKey)?.trim() ?? "";
  } catch {
    envValue = "";
  }
  return envValue || getTrimmedString(config, plaintextFallbackKey);
}

function resolveFirstLocalValue(
  config: Record<string, unknown> | null,
  envKeys: string[],
  plaintextFallbackKeys: string[],
): string {
  for (const envKey of envKeys) {
    let envValue = "";
    try {
      envValue = Deno.env.get(envKey)?.trim() ?? "";
    } catch {
      envValue = "";
    }
    if (envValue) return envValue;
  }

  for (const fallbackKey of plaintextFallbackKeys) {
    const fallbackValue = getTrimmedString(config, fallbackKey);
    if (fallbackValue) return fallbackValue;
  }

  return "";
}

export function resolveRtdhConfig(value: unknown): RtdhConfig {
  const config = asRtdhConfigRecord(value);
  const secretMetadata = config?.secret_metadata &&
      typeof config.secret_metadata === "object" &&
      !Array.isArray(config.secret_metadata)
    ? config.secret_metadata as Record<string, unknown>
    : undefined;

  const baseUrl = resolveFirstLocalValue(config, ["RTDH_BASE_URL"], [
    "base_url",
    "api_url",
  ]);
  const patientPlatformWebhookSecret = resolveFirstLocalValue(
    config,
    [],
    [
      "patient_platform_webhook_secret",
      "patient_platform_receiver_secret",
      "consumer_secret",
    ],
  );
  const secretManagerReceiverSecret = resolveFirstLocalValue(
    config,
    [
      "RTDH_SECRET_MANAGER_RECEIVER_SECRET",
      "SECRET_MANAGER_RECEIVER_SECRET",
    ],
    ["secret_manager_receiver_secret", "patient_platform_receiver_secret"],
  );

  return {
    api_url: baseUrl,
    access_token: resolveLocalValue(
      config,
      "RTDH_ACCESS_TOKEN",
      "access_token",
    ),
    consumer_secret: patientPlatformWebhookSecret,
    base_url: baseUrl,
    patient_platform_webhook_secret: patientPlatformWebhookSecret,
    patient_platform_receiver_secret: patientPlatformWebhookSecret,
    secret_manager_receiver_secret: secretManagerReceiverSecret,
    patient_platform_consumer_webhook_token: getTrimmedString(
      config,
      "patient_platform_consumer_webhook_token",
    ),
    patient_platform_consumer_webhook_token_secret_ref: getTrimmedString(
      config,
      "patient_platform_consumer_webhook_token_secret_ref",
    ) || undefined,
    access_token_secret_ref:
      getTrimmedString(config, "access_token_secret_ref") || undefined,
    consumer_secret_secret_ref:
      getTrimmedString(config, "consumer_secret_secret_ref") || undefined,
    secret_backend: getTrimmedString(config, "secret_backend") || undefined,
    secret_tenant: getTrimmedString(config, "secret_tenant") || undefined,
    secret_metadata: secretMetadata,
  };
}
