import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  getFriendbuyIntegrationConfig,
  ingestFriendbuySyncPayload,
  verifyFriendbuyWebhookSignature,
} from "../_shared/friendbuy.ts";

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-friendbuy-event-id, x-friendbuy-event-type, x-tenant-id, x-tenant-slug",
    methods: "POST, OPTIONS",
  });
}

function jsonResponse(req: Request, body: Record<string, unknown>, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...getCorsHeaders(req), "Content-Type": "application/json" },
  });
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0
    ? value.trim()
    : null;
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === "object" && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}

async function resolveTenantId(
  // deno-lint-ignore no-explicit-any
  supabaseAdmin: any,
  req: Request,
  payload: Record<string, unknown>,
): Promise<string | null> {
  const url = new URL(req.url);
  const tenantId = readString(req.headers.get("x-tenant-id")) ||
    readString(url.searchParams.get("tenant_id")) ||
    readString(payload.tenant_id) ||
    readString(payload.tenantId);
  if (tenantId) return tenantId;

  const tenantSlug = readString(req.headers.get("x-tenant-slug")) ||
    readString(url.searchParams.get("tenant_slug")) ||
    readString(payload.tenant_slug) ||
    readString(payload.tenantSlug);
  if (!tenantSlug) return null;

  const { data, error } = await supabaseAdmin
    .from("tenants")
    .select("id")
    .eq("slug", tenantSlug)
    .maybeSingle();

  if (error) {
    console.warn("Friendbuy webhook tenant lookup failed", {
      tenantSlug,
      error: error.message,
    });
  }

  return readString(data?.id) || null;
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { status: 204, headers: getCorsHeaders(req) });
  }

  if (req.method !== "POST") {
    return jsonResponse(req, { error: "Method not allowed" }, 405);
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!supabaseUrl || !serviceKey) {
    return jsonResponse(req, { error: "Missing Supabase configuration" }, 500);
  }

  const supabaseAdmin = createClient(supabaseUrl, serviceKey, {
    global: { headers: { "x-request-source": "friendbuy-webhook" } },
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const rawBody = await req.text();
  let parsedBody: unknown = {};
  try {
    parsedBody = rawBody ? JSON.parse(rawBody) : {};
  } catch {
    // Fall through with an empty payload; signature verification below
    // still runs against the raw (unparsed) body and will reject tampered
    // or malformed requests.
  }
  const payload = asRecord(parsedBody);
  const tenantId = await resolveTenantId(supabaseAdmin, req, payload);
  if (!tenantId) {
    return jsonResponse(req, {
      error: "tenant_id or tenant_slug is required",
    }, 400);
  }

  const friendbuyConfig = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    tenantId,
  );
  if (!friendbuyConfig) {
    return jsonResponse(req, { error: "Unauthorized" }, 401);
  }

  const validSignature = await verifyFriendbuyWebhookSignature(
    friendbuyConfig.secretKey,
    rawBody,
    req.headers.get("x-friendbuy-hmac-sha256"),
  );
  if (!validSignature) {
    return jsonResponse(req, { error: "Unauthorized" }, 401);
  }

  const eventType = readString(req.headers.get("x-friendbuy-event-type")) ||
    readString(payload.event_type) ||
    readString(payload.eventType) ||
    readString(payload.type) ||
    "unknown";
  const eventId = readString(req.headers.get("x-friendbuy-event-id")) ||
    readString(payload.event_id) ||
    readString(payload.eventId) ||
    readString(payload.id);

  const result = await ingestFriendbuySyncPayload(supabaseAdmin, {
    tenantId,
    source: "friendbuy_webhook",
    eventType,
    eventId,
    payload,
  });

  return jsonResponse(req, {
    ok: true,
    processed: result.processed,
    referral_record_id: result.referralRecordId,
  });
});
