import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { getDeploymentEnvironment } from "../_shared/environment.ts";
import {
  buildMobileAppsMetadata,
  buildQrCodeApiUrl,
  buildQrCodeStoragePath,
  getWebAppOrigin,
  normalizeStoreInputs,
  normalizeWebAppInput,
  readExistingMobileAppStores,
  readExistingWebAppConfig,
  replacePasskeyAllowedOrigin,
} from "./helpers.ts";
import type {
  AppStoreConfig,
  TenantAppStoreConfigRequest,
  WebAppConfig,
} from "./helpers.ts";

const BRAND_ASSETS_BUCKET = "brand-assets";

function jsonResponse(
  body: Record<string, unknown>,
  status: number,
  headers: Record<string, string>,
) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...headers, "Content-Type": "application/json" },
  });
}

async function fetchQrSvg(appUrl: string): Promise<string> {
  const response = await fetch(buildQrCodeApiUrl(appUrl));
  if (!response.ok) {
    throw new Error(`QR generation failed with ${response.status}`);
  }

  const svg = await response.text();
  if (!svg.includes("<svg")) {
    throw new Error("QR generation returned an invalid SVG");
  }

  return svg;
}

Deno.serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
    methods: "POST, OPTIONS",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  if (req.method !== "POST") {
    return jsonResponse({ error: "Method not allowed" }, 405, corsHeaders);
  }

  try {
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) {
      return jsonResponse(
        { error: "Missing authorization header" },
        401,
        corsHeaders,
      );
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;

    const supabaseAuth = createClient(supabaseUrl, supabaseAnonKey, {
      global: { headers: { Authorization: authHeader } },
    });
    const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey, {
      auth: { autoRefreshToken: false, persistSession: false },
    });

    const {
      data: { user },
      error: authError,
    } = await supabaseAuth.auth.getUser();
    if (authError || !user) {
      return jsonResponse({ error: "Unauthorized" }, 401, corsHeaders);
    }

    const body = (await req.json()) as TenantAppStoreConfigRequest;
    const tenantId = body.tenantId?.trim();
    if (!tenantId) {
      return jsonResponse({ error: "tenantId is required" }, 400, corsHeaders);
    }

    const { data: isPlatformSuperadmin } = await supabaseAdmin.rpc(
      "is_platform_superadmin",
      { _auth_user_id: user.id },
    );
    const { data: isTenantAdmin } = await supabaseAdmin.rpc("is_tenant_admin", {
      _auth_user_id: user.id,
      _tenant_id: tenantId,
    });

    if (!isPlatformSuperadmin && !isTenantAdmin) {
      return jsonResponse({ error: "Forbidden" }, 403, corsHeaders);
    }

    const { data: existingSettings, error: settingsError } = await supabaseAdmin
      .from("tenant_settings")
      .select("metadata")
      .eq("tenant_id", tenantId)
      .maybeSingle();

    if (settingsError) {
      throw settingsError;
    }

    const previousMetadata = existingSettings?.metadata &&
        typeof existingSettings.metadata === "object" &&
        !Array.isArray(existingSettings.metadata)
      ? (existingSettings.metadata as Record<string, unknown>)
      : {};
    const previousStores = readExistingMobileAppStores(previousMetadata);
    const previousStoreById = new Map(
      previousStores.map((store) => [store.id, store]),
    );
    const previousWebApp = readExistingWebAppConfig(previousMetadata);

    const nextStores = normalizeStoreInputs(body.stores);
    const nextStoreIds = new Set(nextStores.map((store) => store.id));
    const storesWithQr: AppStoreConfig[] = [];
    const hasWebAppInput = "web_app" in body || "web_app_base_url" in body;
    const nextWebApp: WebAppConfig | null = hasWebAppInput
      ? normalizeWebAppInput(body.web_app) ||
        normalizeWebAppInput({ base_url: body.web_app_base_url })
      : previousWebApp;

    for (const store of nextStores) {
      const previousStore = previousStoreById.get(store.id);
      let qrCodeUrl = previousStore?.app_url === store.app_url
        ? previousStore.qr_code_url
        : "";

      if (!qrCodeUrl) {
        const svg = await fetchQrSvg(store.app_url);
        const storagePath = buildQrCodeStoragePath(tenantId, store.id);
        const { error: uploadError } = await supabaseAdmin.storage
          .from(BRAND_ASSETS_BUCKET)
          .upload(storagePath, new Blob([svg], { type: "image/svg+xml" }), {
            contentType: "image/svg+xml",
            upsert: true,
            cacheControl: "3600",
          });

        if (uploadError) {
          throw uploadError;
        }

        qrCodeUrl = supabaseAdmin.storage
          .from(BRAND_ASSETS_BUCKET)
          .getPublicUrl(storagePath).data.publicUrl;
      }

      storesWithQr.push({ ...store, qr_code_url: qrCodeUrl });
    }

    for (const previousStore of previousStores) {
      if (!nextStoreIds.has(previousStore.id)) {
        await supabaseAdmin.storage
          .from(BRAND_ASSETS_BUCKET)
          .remove([buildQrCodeStoragePath(tenantId, previousStore.id)]);
      }
    }

    const nextMobileApps = buildMobileAppsMetadata(storesWithQr, nextWebApp);

    const nextMetadata = {
      ...previousMetadata,
      mobile_apps: nextMobileApps,
    };

    if (!nextMobileApps) {
      delete nextMetadata.mobile_apps;
    }

    const previousOrigin = getWebAppOrigin(previousWebApp);
    const nextOrigin = getWebAppOrigin(nextWebApp);
    const passkeyConfigSnapshots: Array<{
      id: string;
      allowed_origins: string[];
      is_enabled: boolean;
    }> = [];
    let savedSettings: { metadata: unknown } | null = null;

    try {
      if (hasWebAppInput && previousOrigin !== nextOrigin) {
        const environment = getDeploymentEnvironment(supabaseUrl);
        if (!environment) {
          throw new Error(
            "Unable to determine passkey configuration environment",
          );
        }

        const { data: passkeyConfigs, error: passkeyConfigError } =
          await supabaseAdmin
            .from("tenant_passkey_configs")
            .select("id, allowed_origins, is_enabled")
            .eq("tenant_id", tenantId)
            .eq("environment", environment)
            .neq("rp_id", "localhost");

        if (passkeyConfigError) throw passkeyConfigError;

        const configs = passkeyConfigs ?? [];
        const matchingConfigs = previousOrigin
          ? configs.filter((config) =>
            replacePasskeyAllowedOrigin(
              config.allowed_origins,
              null,
              null,
            ).includes(previousOrigin)
          )
          : [];
        const targetConfigs = matchingConfigs.length > 0
          ? matchingConfigs
          : configs.length === 1
          ? configs
          : [];

        if (configs.length > 1 && targetConfigs.length === 0) {
          throw new Error(
            "Could not identify the passkey configuration for the previous web app origin",
          );
        }

        for (const config of targetConfigs) {
          const previousAllowedOrigins = replacePasskeyAllowedOrigin(
            config.allowed_origins,
            null,
            null,
          );
          const nextAllowedOrigins = replacePasskeyAllowedOrigin(
            previousAllowedOrigins,
            previousOrigin,
            nextOrigin,
          );
          const hasAllowedOrigins = nextAllowedOrigins.length > 0;

          passkeyConfigSnapshots.push({
            id: config.id,
            allowed_origins: previousAllowedOrigins,
            is_enabled: config.is_enabled,
          });

          const { error: passkeyUpdateError } = await supabaseAdmin
            .from("tenant_passkey_configs")
            .update({
              allowed_origins: hasAllowedOrigins
                ? nextAllowedOrigins
                : previousAllowedOrigins,
              is_enabled: hasAllowedOrigins,
              updated_at: new Date().toISOString(),
            })
            .eq("id", config.id);

          if (passkeyUpdateError) throw passkeyUpdateError;
        }
      }

      const { data, error: saveError } = await supabaseAdmin
        .from("tenant_settings")
        .upsert(
          {
            tenant_id: tenantId,
            metadata: nextMetadata,
          },
          { onConflict: "tenant_id" },
        )
        .select("metadata")
        .single();

      if (saveError) throw saveError;
      savedSettings = data;
    } catch (saveOrSyncError) {
      for (const snapshot of passkeyConfigSnapshots) {
        const { error: rollbackError } = await supabaseAdmin
          .from("tenant_passkey_configs")
          .update({
            allowed_origins: snapshot.allowed_origins,
            is_enabled: snapshot.is_enabled,
            updated_at: new Date().toISOString(),
          })
          .eq("id", snapshot.id);

        if (rollbackError) {
          console.error("Passkey origin rollback failed:", rollbackError);
        }
      }

      throw saveOrSyncError;
    }

    if (!savedSettings) throw new Error("Tenant settings were not saved");

    return jsonResponse(
      {
        mobile_apps: (savedSettings.metadata as Record<string, unknown>)
          ?.mobile_apps ?? null,
      },
      200,
      corsHeaders,
    );
  } catch (error) {
    console.error("tenant-app-store-config error:", error);
    return jsonResponse(
      {
        error: error instanceof Error ? error.message : "Internal server error",
      },
      500,
      corsHeaders,
    );
  }
});
