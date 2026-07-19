import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { reconcileFriendbuyRewardsAndCoupons } from "../_shared/friendbuy.ts";

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
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

async function authorizeAdmin(
  supabaseUrl: string,
  serviceKey: string,
  req: Request,
  tenantId: string,
): Promise<boolean> {
  const anonKey = Deno.env.get("SUPABASE_ANON_KEY");
  const authHeader = req.headers.get("Authorization");
  if (!anonKey || !authHeader) return false;

  const userClient = createClient(supabaseUrl, anonKey, {
    global: { headers: { Authorization: authHeader } },
    auth: { autoRefreshToken: false, persistSession: false },
  });
  const { data: userData } = await userClient.auth.getUser();
  const userId = userData.user?.id;
  if (!userId) return false;

  const admin = createClient(supabaseUrl, serviceKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });
  const { data: isSuperadmin } = await admin.rpc("is_platform_superadmin", {
    _auth_user_id: userId,
  });
  if (isSuperadmin) return true;

  const { data: adminUser } = await admin
    .from("admin_users")
    .select("id, is_active")
    .eq("auth_user_id", userId)
    .maybeSingle();
  if (!adminUser?.id || adminUser.is_active === false) return false;

  const { data: membership } = await admin
    .from("tenant_memberships")
    .select("id")
    .eq("admin_user_id", adminUser.id)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  return Boolean(membership?.id);
}

// Narrow reconciliation: Friendbuy's webhook system has no event for a
// reward's final credited/rejected outcome, or for coupon distribution/
// redemption. This pulls exactly the two Merchant API endpoints that cover
// those gaps (GetReferralRewards, GetCoupons) and nothing else — every other
// referral signal already arrives via friendbuy-webhook in near-real-time.
// See docs/FriendbuyIntegration.md.
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

  const payload = asRecord(await req.json().catch(() => ({})));
  const tenantId = readString(payload.tenant_id) || readString(payload.tenantId);

  if (!tenantId) {
    return jsonResponse(req, { error: "tenant_id is required" }, 400);
  }

  // Two callers are allowed: an admin/superadmin (interactive "Reconcile"
  // button) via their JWT, or the scheduled pg_cron tick presenting the shared
  // CRON_SECRET (same convention as outbound-webhook-sweeper). The cron path
  // skips the tenant-membership check since it runs with no user session.
  const cronSecret = Deno.env.get("CRON_SECRET");
  const authHeader = req.headers.get("Authorization");
  const isCronRequest = Boolean(cronSecret) &&
    authHeader === `Bearer ${cronSecret}`;

  const authorized = isCronRequest ||
    await authorizeAdmin(supabaseUrl, serviceKey, req, tenantId);
  if (!authorized) {
    console.warn("[friendbuy-reconcile] unauthorized", { tenantId });
    return jsonResponse(req, { error: "Unauthorized" }, 401);
  }

  const supabaseAdmin = createClient(supabaseUrl, serviceKey, {
    global: { headers: { "x-request-source": "friendbuy-reconcile" } },
    auth: { autoRefreshToken: false, persistSession: false },
  });

  console.log("[friendbuy-reconcile] starting reconcile", { tenantId });

  const result = await reconcileFriendbuyRewardsAndCoupons(supabaseAdmin, {
    tenantId,
    pageToken: readString(payload.page_token) || readString(payload.pageToken),
    fromDate: readString(payload.from_date) || readString(payload.fromDate),
    toDate: readString(payload.to_date) || readString(payload.toDate),
    pageSize: readString(payload.page_size) || readString(payload.pageSize),
  });

  console.log("[friendbuy-reconcile] result", {
    ok: result.ok,
    status: result.status,
    synced: result.synced,
  });

  return jsonResponse(req, {
    ok: result.ok,
    status: result.status,
    synced: result.synced,
  }, result.ok ? 200 : 502);
});
