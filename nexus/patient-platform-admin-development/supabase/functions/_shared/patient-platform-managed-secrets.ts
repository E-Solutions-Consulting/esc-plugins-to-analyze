import { getTrimmedString } from "./rtdh-config.ts";

export const PATIENT_PLATFORM_SECRET_PROVIDER = "patient_platform";
export const CONSUMER_WEBHOOK_TOKEN_SECRET_KEY = "consumer_webhook_token";
export const CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY =
  "patient_platform_consumer_webhook_token";

export function patientPlatformSecretTenant(
  config?: Record<string, unknown> | null,
): string {
  return Deno.env.get("RTDH_SECRET_TENANT")?.trim() ||
    getTrimmedString(config ?? null, "secret_tenant") ||
    "allia";
}

export function isPatientPlatformConsumerWebhookToken(
  provider: string,
  key: string,
): boolean {
  return provider.trim().toLowerCase().replace(/-/g, "_") ===
      PATIENT_PLATFORM_SECRET_PROVIDER &&
    key.trim() === CONSUMER_WEBHOOK_TOKEN_SECRET_KEY;
}
