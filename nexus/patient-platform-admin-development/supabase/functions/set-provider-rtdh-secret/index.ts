/**
 * set-provider-rtdh-secret
 *
 * Authenticated Patient Platform broker for tenant/provider integration secrets.
 * PP remains source of truth for the change request; RTDH verifies the signed
 * request and applies it to GCP Secret Manager.
 */
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { resolveRtdhConfig } from "../_shared/rtdh-config.ts";
import {
  saveSecretsViaRtdh,
  saveSecretViaRtdh,
} from "../_shared/rtdh-secret-manager-interface.ts";

type SavedSecret = {
  key: string;
  secretId: string;
  secretName: string;
  versionName: string;
};

type SecretManagerClient = {
  saveSecretsViaRtdh: typeof saveSecretsViaRtdh;
  saveSecretViaRtdh: typeof saveSecretViaRtdh;
};

type HandlerDeps = {
  createClientImpl?: typeof createClient;
  secretManager?: SecretManagerClient;
};

type NormalizedSecretRequest = {
  provider: string;
  context: string | null;
  secrets: Record<string, string>;
};

const SUPPORTED_PROVIDER_KEYS: Record<string, Record<string, string>> = {
  telegramd: {
    webhook_secret: "webhook_secret",
  },
  mdi: {
    webhook_secret: "webhook_secret",
  },
  stripe: {
    webhook_secret: "signing_secret",
    signing_secret: "signing_secret",
  },
  easypost: {
    webhook_secret: "webhook_secret",
  },
  intercom: {
    webhook_secret: "webhook_secret",
  },
  jotform: {
    webhook_secret: "webhook_secret",
  },
  lifefile: {
    webhook_username: "webhook_username",
    webhook_password: "webhook_password",
  },
};

function normalizeProviderKey(value: string): string {
  const normalized = value.trim().toLowerCase().replace(/-/g, "_");
  if (normalized === "telegra") return "telegramd";
  if (normalized === "md_integrations" || normalized === "mdintegrations") {
    return "mdi";
  }
  return normalized;
}

const json = (
  body: Record<string, unknown>,
  status: number,
  headers: Record<string, string>,
) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...headers, "Content-Type": "application/json" },
  });

function nonEmptySecrets(value: unknown): Record<string, string> {
  if (!value || typeof value !== "object" || Array.isArray(value)) return {};

  const output: Record<string, string> = {};
  for (
    const [key, rawValue] of Object.entries(value as Record<string, unknown>)
  ) {
    if (typeof rawValue === "string" && rawValue.trim()) {
      output[key] = rawValue.trim();
    }
  }
  return output;
}

function normalizeSecretRequest(input: {
  provider: string;
  context: string | null;
  body: Record<string, unknown>;
}): NormalizedSecretRequest {
  const provider = normalizeProviderKey(input.provider);
  const keyMap = SUPPORTED_PROVIDER_KEYS[provider];
  if (!keyMap) {
    throw new Error(`Unsupported RTDH provider: ${input.provider}`);
  }

  const rawSecrets = nonEmptySecrets(input.body.secrets);
  const secrets: Record<string, string> = {};
  if (Object.keys(rawSecrets).length > 0) {
    for (const [key, value] of Object.entries(rawSecrets)) {
      const normalizedKey = key.trim();
      const rtdhKey = keyMap[normalizedKey];
      if (!rtdhKey) {
        throw new Error(
          `Unsupported RTDH secret key '${key}' for provider '${provider}'`,
        );
      }
      secrets[rtdhKey] = value;
    }
  } else {
    const key = String(
      input.body.key ?? (input.body.secretValue ? "webhook_secret" : ""),
    ).trim();
    const value = String(input.body.value ?? input.body.secretValue ?? "")
      .trim();
    if (!key) throw new Error("key is required");
    if (value.length < 8) {
      throw new Error("value must be at least 8 characters");
    }

    const rtdhKey = keyMap[key];
    if (!rtdhKey) {
      throw new Error(
        `Unsupported RTDH secret key '${key}' for provider '${provider}'`,
      );
    }
    secrets[rtdhKey] = value;
  }

  return {
    provider,
    context: input.context,
    secrets,
  };
}

export function createSetProviderRtdhSecretHandler(deps: HandlerDeps = {}) {
  const createClientForRequest = deps.createClientImpl ?? createClient;
  const secretManager = deps.secretManager ?? {
    saveSecretsViaRtdh,
    saveSecretViaRtdh,
  };

  return async (req: Request) => {
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
      const db = createClientForRequest(url, serviceKey, {
        auth: { autoRefreshToken: false, persistSession: false },
      });

      const authHeader = req.headers.get("Authorization");
      if (!authHeader) {
        return json({ error: "Missing authorization header" }, 401, cors);
      }

      const authed = createClientForRequest(url, anonKey, {
        global: { headers: { Authorization: authHeader } },
        auth: { autoRefreshToken: false, persistSession: false },
      });
      const { data: { user }, error: userError } = await authed.auth.getUser();
      if (userError || !user) return json({ error: "Unauthorized" }, 401, cors);

      const body = await req.json() as Record<string, unknown>;
      const tenantId = String(body.tenant_id ?? body.tenantId ?? "").trim();
      const provider = String(body.provider ?? body.providerKey ?? "").trim();
      const context = typeof body.context === "string" && body.context.trim()
        ? body.context.trim()
        : null;

      if (!tenantId) return json({ error: "tenant_id is required" }, 400, cors);
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
      if (!isSuper) {
        const { data: membership } = await db
          .from("tenant_memberships")
          .select("id")
          .eq("tenant_id", tenantId)
          .eq("admin_user_id", adminUser.id)
          .maybeSingle();
        if (!membership) return json({ error: "Forbidden" }, 403, cors);
      }

      const { data: tenantRow } = await db
        .from("tenants")
        .select("slug, name")
        .eq("id", tenantId)
        .maybeSingle();
      const tenant = typeof tenantRow?.slug === "string"
        ? tenantRow.slug.trim()
        : "";
      if (!tenant) return json({ error: "tenant not found" }, 404, cors);

      const { data: rtdhSetting } = await db
        .from("platform_settings")
        .select("value")
        .eq("key", "rtdh_config")
        .maybeSingle();
      const rtdhConfig = resolveRtdhConfig(rtdhSetting?.value);
      const apiUrl = String(body.apiUrl ?? body.api_url ?? rtdhConfig.base_url);
      const fallbackSigningSecret = rtdhConfig.secret_manager_receiver_secret ||
        rtdhConfig.patient_platform_webhook_secret ||
        rtdhConfig.consumer_secret;
      const requestId = crypto.randomUUID();
      let result;
      let normalizedRequest: NormalizedSecretRequest;

      try {
        normalizedRequest = normalizeSecretRequest({
          provider,
          context,
          body,
        });
      } catch (error) {
        return json(
          { error: error instanceof Error ? error.message : "Invalid secret" },
          400,
          cors,
        );
      }

      if (Object.keys(normalizedRequest.secrets).length > 1) {
        result = await secretManager.saveSecretsViaRtdh({
          apiUrl,
          tenant,
          provider: normalizedRequest.provider,
          context: normalizedRequest.context,
          secrets: normalizedRequest.secrets,
          fallbackSigningSecret,
          requestId,
        });
      } else {
        const [[key, value]] = Object.entries(normalizedRequest.secrets);

        result = await secretManager.saveSecretViaRtdh({
          apiUrl,
          tenant,
          provider: normalizedRequest.provider,
          context: normalizedRequest.context,
          key,
          value,
          fallbackSigningSecret,
          requestId,
        });
      }

      return json(
        {
          success: true,
          tenant: result.tenant,
          provider: result.provider,
          context: result.context,
          saved: result.saved as SavedSecret[],
          requestId: result.requestId,
        },
        200,
        cors,
      );
    } catch (e) {
      const message = e instanceof Error
        ? e.message
        : "Failed to set provider secret";
      const notConfigured = message.includes("not configured") ||
        message.includes("is required");
      return json(
        { error: message, configured: !notConfigured },
        notConfigured ? 501 : 500,
        cors,
      );
    }
  };
}

if (import.meta.main) {
  Deno.serve(createSetProviderRtdhSecretHandler());
}
