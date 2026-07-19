/**
 * set-rtdh-config
 *
 * Platform-admin write path for RTDH connection settings. This stores the RTDH
 * Cloud Functions base URL and the shared PP -> RTDH webhook signing secrets.
 */
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  asRtdhConfigRecord,
  getTrimmedString,
  resolveRtdhConfig,
} from "../_shared/rtdh-config.ts";
import { saveSecretViaRtdh } from "../_shared/rtdh-secret-manager-interface.ts";
import {
  CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY,
  CONSUMER_WEBHOOK_TOKEN_SECRET_KEY,
  PATIENT_PLATFORM_SECRET_PROVIDER,
  patientPlatformSecretTenant,
} from "../_shared/patient-platform-managed-secrets.ts";

const json = (body: unknown, status: number, cors: Record<string, string>) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...cors, "Content-Type": "application/json" },
  });

const RTDH_RECEIVER_SUFFIXES = [
  "/patient-platform-webhook-receiver",
  "/secret-manager-receiver",
];
const PATIENT_PLATFORM_WEBHOOK_SECRET_KEY = "patient_platform_webhook_secret";
const LEGACY_PATIENT_PLATFORM_RECEIVER_SECRET_KEY =
  "patient_platform_receiver_secret";

function normalizeRtdhBaseUrl(value: string): string {
  let base = value.trim().replace(/\/+$/, "");
  for (const suffix of RTDH_RECEIVER_SUFFIXES) {
    if (base.endsWith(suffix)) {
      base = base.slice(0, -suffix.length);
      break;
    }
  }
  return base;
}

function maskSecret(value: string): string {
  return value ? "********" : "";
}

function getPatientPlatformWebhookSecret(
  record: Record<string, unknown> | null,
): string {
  return getTrimmedString(record, PATIENT_PLATFORM_WEBHOOK_SECRET_KEY) ||
    getTrimmedString(record, LEGACY_PATIENT_PLATFORM_RECEIVER_SECRET_KEY) ||
    getTrimmedString(record, "consumer_secret");
}

Deno.serve(async (req) => {
  const cors = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
    methods: "POST, OPTIONS",
  });

  if (req.method === "OPTIONS") return new Response(null, { headers: cors });
  if (req.method !== "POST") {
    return json({ error: "Method not allowed" }, 405, cors);
  }

  try {
    const url = Deno.env.get("SUPABASE_URL")!;
    const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const anonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
    const db = createClient(url, serviceKey, {
      auth: { autoRefreshToken: false, persistSession: false },
    });

    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      return json({ error: "Missing authorization header" }, 401, cors);
    }

    const authed = createClient(url, anonKey, {
      global: { headers: { Authorization: authHeader } },
      auth: { autoRefreshToken: false, persistSession: false },
    });
    const { data: { user }, error: userError } = await authed.auth.getUser();
    if (userError || !user) return json({ error: "Unauthorized" }, 401, cors);

    const { data: adminUser } = await db
      .from("admin_users")
      .select("id, is_active")
      .eq("auth_user_id", user.id)
      .maybeSingle();
    if (!adminUser || adminUser.is_active === false) {
      return json({ error: "Forbidden" }, 403, cors);
    }

    const { data: isSuper } = await db.rpc("is_platform_superadmin", {
      _auth_user_id: user.id,
    });
    if (!isSuper) {
      return json({ error: "Platform superadmin required" }, 403, cors);
    }

    const body = await req.json();
    const baseUrl = normalizeRtdhBaseUrl(
      String(body.base_url ?? body.api_url ?? ""),
    );
    if (!baseUrl) return json({ error: "base_url is required" }, 400, cors);
    const patientPlatformWebhookSecret = String(
      body[PATIENT_PLATFORM_WEBHOOK_SECRET_KEY] ??
        body[LEGACY_PATIENT_PLATFORM_RECEIVER_SECRET_KEY] ??
        "",
    ).trim();
    const secretManagerReceiverSecret = String(
      body.secret_manager_receiver_secret ?? "",
    ).trim();
    const patientPlatformConsumerWebhookToken = String(
      body[CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY] ?? "",
    ).trim();

    const { data: existing, error: existingError } = await db
      .from("platform_settings")
      .select("id, value")
      .eq("key", "rtdh_config")
      .maybeSingle();
    if (existingError) throw existingError;

    const previous = asRtdhConfigRecord(existing?.value);
    const previousRtdhConfig = resolveRtdhConfig(previous);
    const secretTenant = patientPlatformSecretTenant(previous);
    let consumerWebhookTokenSecretId = getTrimmedString(
      previous,
      "patient_platform_consumer_webhook_token_secret_ref",
    );
    const existingConsumerWebhookToken = getTrimmedString(
      previous,
      CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY,
    );
    const consumerWebhookTokenToManage = patientPlatformConsumerWebhookToken ||
      (!consumerWebhookTokenSecretId ? existingConsumerWebhookToken : "");

    if (consumerWebhookTokenToManage) {
      const secretResult = await saveSecretViaRtdh({
        apiUrl: baseUrl,
        tenant: secretTenant,
        provider: PATIENT_PLATFORM_SECRET_PROVIDER,
        key: CONSUMER_WEBHOOK_TOKEN_SECRET_KEY,
        value: consumerWebhookTokenToManage,
        fallbackSigningSecret: secretManagerReceiverSecret ||
          previousRtdhConfig.secret_manager_receiver_secret ||
          previousRtdhConfig.patient_platform_webhook_secret ||
          previousRtdhConfig.consumer_secret,
        requestId: crypto.randomUUID(),
      });
      consumerWebhookTokenSecretId = secretResult.saved.find(
        (saved) => saved.key === CONSUMER_WEBHOOK_TOKEN_SECRET_KEY,
      )?.secretId ?? consumerWebhookTokenSecretId;
    }

    const nextValue = {
      ...(previous ?? {}),
      api_url: baseUrl,
      base_url: baseUrl,
      ...(patientPlatformWebhookSecret
        ? {
          [PATIENT_PLATFORM_WEBHOOK_SECRET_KEY]: patientPlatformWebhookSecret,
        }
        : {}),
      ...(secretManagerReceiverSecret
        ? { secret_manager_receiver_secret: secretManagerReceiverSecret }
        : {}),
      ...(consumerWebhookTokenToManage
        ? {
          // Transitional runtime copy: the RTDH interface is deliberately
          // write-only, while rtdh-webhook still needs the value to verify HMAC.
          // Remove after the receiver gets a secure secret-resolution path.
          [CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY]: consumerWebhookTokenToManage,
          patient_platform_consumer_webhook_token_secret_ref:
            consumerWebhookTokenSecretId,
          secret_backend: "rtdh_secret_manager_interface",
          secret_tenant: secretTenant,
        }
        : {}),
    };

    const { data: updated, error: updateError } = await db
      .from("platform_settings")
      .update({ value: nextValue })
      .eq("key", "rtdh_config")
      .select("id, value")
      .single();
    if (updateError) throw updateError;

    await db.from("audit_logs").insert({
      tenant_id: null,
      actor_id: user.id,
      actor_email: user.email,
      action: "update",
      entity_type: "platform_setting",
      entity_id: updated.id,
      before_data: {
        key: "rtdh_config",
        value: {
          api_url: getTrimmedString(previous, "api_url"),
          base_url: getTrimmedString(previous, "base_url"),
          [PATIENT_PLATFORM_WEBHOOK_SECRET_KEY]: maskSecret(
            getPatientPlatformWebhookSecret(previous),
          ),
          secret_manager_receiver_secret: maskSecret(
            getTrimmedString(previous, "secret_manager_receiver_secret"),
          ),
          [CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY]: maskSecret(
            getTrimmedString(
              previous,
              CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY,
            ),
          ),
        },
      },
      after_data: {
        key: "rtdh_config",
        value: {
          api_url: baseUrl,
          base_url: baseUrl,
          [PATIENT_PLATFORM_WEBHOOK_SECRET_KEY]: maskSecret(
            getPatientPlatformWebhookSecret(nextValue),
          ),
          secret_manager_receiver_secret: maskSecret(
            getTrimmedString(nextValue, "secret_manager_receiver_secret"),
          ),
          [CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY]: maskSecret(
            getTrimmedString(
              nextValue,
              CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY,
            ),
          ),
        },
      },
      request_id: crypto.randomUUID(),
    });

    return json(
      {
        ok: true,
        setting: updated,
        config: {
          base_url: baseUrl,
          patient_platform_webhook_secret_configured: Boolean(
            getPatientPlatformWebhookSecret(nextValue),
          ),
          secret_manager_receiver_secret_configured: Boolean(
            getTrimmedString(nextValue, "secret_manager_receiver_secret"),
          ),
          patient_platform_consumer_webhook_token_configured: Boolean(
            consumerWebhookTokenSecretId || getTrimmedString(
              nextValue,
              CONSUMER_WEBHOOK_TOKEN_CONFIG_KEY,
            ),
          ),
        },
      },
      200,
      cors,
    );
  } catch (e) {
    const message = e instanceof Error
      ? e.message
      : "Failed to save RTDH config";
    return json({ error: message }, 500, cors);
  }
});
