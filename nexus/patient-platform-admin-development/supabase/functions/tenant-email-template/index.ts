import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  resolveTenantEmailTemplate,
  type SupabaseEmailClient,
} from "../_shared/email-distribution.ts";
import {
  getTenantIdentifier,
  readWebAppBaseUrl,
  sanitizeTenantSlug,
  type TenantEmailTemplateResponse,
} from "./helpers.ts";

function jsonResponse(
  body: unknown,
  status: number,
  headers: Record<string, string>,
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...headers, "Content-Type": "application/json" },
  });
}

Deno.serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-tenant-slug, x-tenant-id",
    methods: "GET, OPTIONS",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  if (req.method !== "GET") {
    return jsonResponse({ error: "Method not allowed" }, 405, corsHeaders);
  }

  try {
    const url = new URL(req.url);
    const { slug, tenantId } = getTenantIdentifier(url, req.headers);

    if (!slug && !tenantId) {
      return jsonResponse(
        {
          error: "Missing tenant identifier",
          message: "Provide 'slug' or 'tenant_id' as query parameter or header",
        },
        400,
        corsHeaders,
      );
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL");
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

    if (!supabaseUrl || !supabaseServiceKey) {
      throw new Error("Missing Supabase configuration");
    }

    const supabase = createClient(supabaseUrl, supabaseServiceKey, {
      auth: {
        autoRefreshToken: false,
        persistSession: false,
      },
    });

    let tenantQuery = supabase
      .from("tenants")
      .select("id, slug")
      .eq("status", "active");

    if (slug) {
      tenantQuery = tenantQuery.eq("slug", sanitizeTenantSlug(slug));
    } else if (tenantId) {
      tenantQuery = tenantQuery.eq("id", tenantId.trim());
    }

    const { data: tenant, error: tenantError } = await tenantQuery.single();

    if (tenantError || !tenant) {
      return jsonResponse({ error: "Tenant not found" }, 404, corsHeaders);
    }

    const emailTemplateHtml = await resolveTenantEmailTemplate(
      supabase as unknown as SupabaseEmailClient,
      tenant.id,
      { source: "tenant-email-template" },
    );
    const { data: tenantSettings, error: settingsError } = await supabase
      .from("tenant_settings")
      .select("metadata")
      .eq("tenant_id", tenant.id)
      .maybeSingle();

    if (settingsError) {
      throw settingsError;
    }

    const response: TenantEmailTemplateResponse = {
      tenant_id: tenant.id,
      slug: tenant.slug,
      email_template_html: emailTemplateHtml,
      web_app_base_url: readWebAppBaseUrl(
        (tenantSettings?.metadata as Record<string, unknown> | null) ?? null,
      ),
    };

    return jsonResponse(response, 200, corsHeaders);
  } catch (error) {
    console.error("Error fetching tenant email template:", error);
    return jsonResponse({ error: "Internal server error" }, 500, corsHeaders);
  }
});
