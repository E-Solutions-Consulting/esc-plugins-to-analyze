import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import type {
  FeatureFlag,
  FlagOverride,
  ReferralProgramRow,
  TenantIntegrationRow,
  TenantSupportConfigRow,
} from "./helpers.ts";
import {
  buildFeatureFlagsResult,
  buildFriendbuyClientConfig,
  buildIntercomClientConfig,
  buildMobileAppsConfig,
  buildProviderPlatformsConfig,
  buildReferralProgramClientConfig,
  buildTenantSupportConfig,
  getTenantIdentifier,
  normalizeAvailableStates,
  pickBranding,
  sanitizeTenantSlug,
} from "./helpers.ts";

Deno.serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-tenant-slug, x-tenant-id",
    methods: "GET, OPTIONS",
  });

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const url = new URL(req.url);

    // Get tenant identifier from query param or header
    const { slug, tenantId } = getTenantIdentifier(url, req.headers);

    if (!slug && !tenantId) {
      return new Response(
        JSON.stringify({
          error: "Missing tenant identifier",
          message: "Provide 'slug' or 'tenant_id' as query parameter or header",
        }),
        {
          status: 400,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    // Create Supabase client with service role to access all tables
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(supabaseUrl, supabaseServiceKey);

    // Build query
    let query = supabase
      .from("tenants")
      .select(
        `
        id,
        name,
        slug,
        status,
        tenant_branding (
          logo_url,
          logo_has_wordmark,
          primary_color,
          secondary_color,
          accent_color,
          rise_logo_url,
          aria_logo_url,
          favicon_url,
          font_family,
          support_email,
          terms_url,
          privacy_url,
          hipaa_url
        )
      `,
      )
      .eq("status", "active");

    if (slug) {
      query = query.eq("slug", sanitizeTenantSlug(slug));
    } else if (tenantId) {
      query = query.eq("id", tenantId.trim());
    }

    const { data: tenant, error } = await query.single();

    if (error || !tenant) {
      return new Response(JSON.stringify({ error: "Tenant not found" }), {
        status: 404,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const { data: tenantSettings, error: settingsError } = await supabase
      .from("tenant_settings")
      .select("allowed_states, allowed_countries, metadata")
      .eq("tenant_id", tenant.id)
      .maybeSingle();

    if (settingsError) {
      console.error(
        "Error fetching tenant settings for states:",
        settingsError,
      );
    }

    const rawAllowedCountries = (
      tenantSettings as { allowed_countries?: string[] } | null
    )?.allowed_countries;
    const rawAllowedStates = (
      tenantSettings as { allowed_states?: string[] } | null
    )?.allowed_states;
    const availableStates = normalizeAvailableStates(
      rawAllowedCountries,
      rawAllowedStates,
    );
    const mobileApps = buildMobileAppsConfig(
      (tenantSettings as { metadata?: Record<string, unknown> } | null)
        ?.metadata,
    );

    const { data: intercomIntegration, error: intercomIntegrationError } =
      await supabase
        .from("tenant_integrations")
        .select("is_enabled, settings")
        .eq("tenant_id", tenant.id)
        .eq("integration_key", "intercom")
        .eq("is_enabled", true)
        .maybeSingle();

    if (intercomIntegrationError) {
      console.error(
        "Error fetching tenant intercom integration:",
        intercomIntegrationError,
      );
    }

    const intercom = buildIntercomClientConfig(
      intercomIntegration as TenantIntegrationRow | null,
    );

    const { data: friendbuyIntegration, error: friendbuyIntegrationError } =
      await supabase
        .from("tenant_integrations")
        .select("is_enabled, settings")
        .eq("tenant_id", tenant.id)
        .eq("integration_key", "friendbuy")
        .eq("is_enabled", true)
        .maybeSingle();

    if (friendbuyIntegrationError) {
      console.error(
        "Error fetching tenant Friendbuy integration:",
        friendbuyIntegrationError,
      );
    }

    const friendbuy = buildFriendbuyClientConfig(
      friendbuyIntegration as TenantIntegrationRow | null,
    );

    const { data: referralProgramConfig, error: referralProgramConfigError } =
      await supabase
        .from("referral_program_configs")
        .select(
          "status, currency, reward_amount_cents",
        )
        .eq("tenant_id", tenant.id)
        .eq("status", "active")
        .maybeSingle();

    if (referralProgramConfigError) {
      console.error(
        "Error fetching tenant referral program config:",
        referralProgramConfigError,
      );
    }

    const referralProgram = buildReferralProgramClientConfig(
      referralProgramConfig as ReferralProgramRow | null,
    );

    const { data: providerPlatformIntegrations, error: providerPlatformError } =
      await supabase
        .from("platform_integrations")
        .select(
          `
          id,
          key,
          name,
          logo_url,
          provider_logo_assets (
            id,
            is_default
          )
        `,
        )
        .eq("category", "provider_platform")
        .eq("is_active", true)
        .order("name");

    if (providerPlatformError) {
      console.error(
        "Error fetching provider platform logo config:",
        providerPlatformError,
      );
    }

    const providerPlatforms = buildProviderPlatformsConfig(
      providerPlatformIntegrations,
    );

    const { data: supportConfig, error: supportConfigError } = await supabase
      .from("tenant_support_configs")
      .select("support_html, faqs, support_hours")
      .eq("tenant_id", tenant.id)
      .maybeSingle();

    if (supportConfigError) {
      console.error(
        "Error fetching tenant support config:",
        supportConfigError,
      );
    }

    const support = buildTenantSupportConfig(
      supportConfig as TenantSupportConfigRow | null,
    );

    // Fetch all active feature flags
    const { data: featureFlags } = await supabase
      .from("feature_flags")
      .select("id, key, name, description, default_value, flag_type")
      .eq("is_active", true)
      .order("key");

    // Fetch tenant-specific overrides
    const { data: overrides } = await supabase
      .from("tenant_feature_flag_overrides")
      .select("feature_flag_id, enabled")
      .eq("tenant_id", tenant.id);

    const featureFlagsResult = buildFeatureFlagsResult(
      featureFlags as FeatureFlag[] | null | undefined,
      overrides as FlagOverride[] | null | undefined,
    );

    // Fetch the tenant's Stripe publishable key (safe to expose client-side).
    // Sourced from the Nexus-configured payment provider settings so all Stripe
    // credentials live in one place (no separate front-end env var).
    let stripePublishableKey: string | null = null;
    const { data: stripeProvider } = await supabase
      .from("tenant_payment_providers")
      .select("settings, payment_providers!inner ( key )")
      .eq("tenant_id", tenant.id)
      .eq("is_enabled", true)
      .eq("payment_providers.key", "stripe")
      .maybeSingle();
    const stripeSettings = stripeProvider?.settings;
    if (
      stripeSettings &&
      typeof stripeSettings === "object" &&
      !Array.isArray(stripeSettings)
    ) {
      const pk = (stripeSettings as Record<string, unknown>).publishable_key;
      if (typeof pk === "string" && pk.trim()) {
        stripePublishableKey = pk.trim();
      }
    }

    // Flatten branding into response
    const branding = pickBranding(
      tenant.tenant_branding as
        | Record<string, unknown>
        | Array<Record<string, unknown>>
        | null,
    );

    const response = {
      id: tenant.id,
      name: tenant.name,
      slug: tenant.slug,
      logo_url: branding?.logo_url || null,
      // Boolean: `||` would coerce a legitimate `false` to null.
      logo_has_wordmark: branding?.logo_has_wordmark === true,
      primary_color: branding?.primary_color || null,
      secondary_color: branding?.secondary_color || null,
      accent_color: branding?.accent_color || null,
      rise_logo_url: branding?.rise_logo_url || null,
      aria_logo_url: branding?.aria_logo_url || null,
      favicon_url: branding?.favicon_url || null,
      font_family: branding?.font_family || null,
      support_email: branding?.support_email || null,
      terms_url: branding?.terms_url || null,
      privacy_url: branding?.privacy_url || null,
      hipaa_url: branding?.hipaa_url || null,
      feature_flags: featureFlagsResult,
      available_states: availableStates,
      stripe_publishable_key: stripePublishableKey,
      ...(mobileApps ? { mobile_apps: mobileApps } : {}),
      ...(support ? { support } : {}),
      integrations: {
        ...(intercom ? { intercom } : {}),
        ...(friendbuy ? { friendbuy } : {}),
        ...(referralProgram ? { referral_program: referralProgram } : {}),
        ...(providerPlatforms
          ? { provider_platforms: providerPlatforms }
          : {}),
      },
    };

    return new Response(JSON.stringify(response), {
      status: 200,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  } catch (error) {
    console.error("Error fetching tenant info:", error);
    return new Response(JSON.stringify({ error: "Internal server error" }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});
