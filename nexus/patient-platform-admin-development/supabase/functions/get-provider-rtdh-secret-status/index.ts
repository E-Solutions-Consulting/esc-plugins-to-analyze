/**
 * get-provider-rtdh-secret-status
 *
 * Read-only companion to set-provider-rtdh-secret. Reports whether a tenant/provider webhook
 * validation secret is CONFIGURED in RTDH's Secret Manager (a yes/no) — it never reads or returns
 * the secret value. Used by the Nexus Providers UI to show a "Configured / Not configured" status.
 */
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { resolveRtdhConfig } from "../_shared/rtdh-config.ts";
import { checkSecretExistsViaRtdh } from "../_shared/rtdh-secret-manager-interface.ts";
import {
  CONSUMER_WEBHOOK_TOKEN_SECRET_KEY,
  isPatientPlatformConsumerWebhookToken,
  PATIENT_PLATFORM_SECRET_PROVIDER,
  patientPlatformSecretTenant,
} from "../_shared/patient-platform-managed-secrets.ts";

const json = (
  body: Record<string, unknown>,
  status: number,
  headers: Record<string, string>,
) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...headers, "Content-Type": "application/json" },
  });

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

    const body = await req.json();
    const tenantId = String(body.tenant_id ?? body.tenantId ?? "").trim();
    const provider = String(body.provider ?? body.providerKey ?? "").trim();
    const context = typeof body.context === "string" && body.context.trim()
      ? body.context.trim()
      : null;
    const key = String(body.key ?? "webhook_secret").trim() || "webhook_secret";
    const isGlobalPatientPlatformSecret = isPatientPlatformConsumerWebhookToken(
      provider,
      key,
    );

    if (!tenantId && !isGlobalPatientPlatformSecret) {
      return json({ error: "tenant_id is required" }, 400, cors);
    }
    if (!provider) return json({ error: "provider is required" }, 400, cors);

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
    if (isGlobalPatientPlatformSecret && !isSuper) {
      return json({ error: "Platform superadmin required" }, 403, cors);
    }
    if (!isSuper && !isGlobalPatientPlatformSecret) {
      const { data: membership } = await db
        .from("tenant_memberships")
        .select("id")
        .eq("tenant_id", tenantId)
        .eq("admin_user_id", adminUser.id)
        .maybeSingle();
      if (!membership) return json({ error: "Forbidden" }, 403, cors);
    }

    const { data: rtdhSetting } = await db
      .from("platform_settings")
      .select("value")
      .eq("key", "rtdh_config")
      .maybeSingle();
    const rtdhConfig = resolveRtdhConfig(rtdhSetting?.value);
    let tenant: string;
    if (isGlobalPatientPlatformSecret) {
      tenant = patientPlatformSecretTenant(
        rtdhSetting?.value as Record<string, unknown> | null,
      );
    } else {
      const { data: tenantRow } = await db
        .from("tenants")
        .select("slug, name")
        .eq("id", tenantId)
        .maybeSingle();
      tenant = String(
        body.tenant ?? tenantRow?.slug ?? Deno.env.get("RTDH_SECRET_TENANT") ??
          "allia",
      ).trim();
    }
    if (!tenant) return json({ error: "tenant is required" }, 400, cors);
    const apiUrl = String(body.apiUrl ?? body.api_url ?? rtdhConfig.base_url);
    const fallbackSigningSecret = rtdhConfig.secret_manager_receiver_secret ||
      rtdhConfig.patient_platform_webhook_secret ||
      rtdhConfig.consumer_secret;

    const result = await checkSecretExistsViaRtdh({
      apiUrl,
      tenant,
      provider: isGlobalPatientPlatformSecret
        ? PATIENT_PLATFORM_SECRET_PROVIDER
        : provider,
      context,
      key: isGlobalPatientPlatformSecret
        ? CONSUMER_WEBHOOK_TOKEN_SECRET_KEY
        : key,
      fallbackSigningSecret,
      requestId: crypto.randomUUID(),
    });

    return json(
      {
        success: true,
        tenant,
        provider: isGlobalPatientPlatformSecret
          ? PATIENT_PLATFORM_SECRET_PROVIDER
          : provider,
        exists: result.exists,
        secretId: result.secretId,
        requestId: result.requestId,
      },
      200,
      cors,
    );
  } catch (e) {
    const message = e instanceof Error
      ? e.message
      : "Failed to get provider secret status";
    const notConfigured = message.includes("not configured") ||
      message.includes("is required");
    return json(
      { error: message, configured: !notConfigured },
      notConfigured ? 501 : 500,
      cors,
    );
  }
});
