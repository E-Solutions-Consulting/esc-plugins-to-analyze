import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.2";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers":
    "authorization, x-client-info, apikey, content-type",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

function jsonResponse(data: unknown, status = 200): Response {
  return new Response(JSON.stringify(data), {
    status,
    headers: { ...corsHeaders, "Content-Type": "application/json" },
  });
}

function errorResponse(code: string, message: string, status = 400): Response {
  return jsonResponse({ success: false, error: { code, message } }, status);
}

type Action =
  | "list"
  | "create"
  | "set_default"
  | "deactivate"
  | "update"
  | "toggle_promo_codes";

interface BaseBody {
  action: Action;
  tenant_id: string;
}

interface ListBody extends BaseBody {
  action: "list";
  product_id: string;
}

interface CreateCouponBody extends BaseBody {
  action: "create";
  product_id: string;
  code: string;
  name?: string;
  coupon_type?: "internal" | "marketing";
  discount_type: "percent" | "amount";
  percent_off?: number;
  amount_off?: number;
  currency?: string;
  duration: "once" | "repeating" | "forever";
  duration_in_months?: number;
  max_redemptions?: number;
  expires_at?: number;
}

interface SetDefaultBody extends BaseBody {
  action: "set_default";
  product_id: string;
  promotion_code_id: string | null;
}

interface DeactivateBody extends BaseBody {
  action: "deactivate";
  promotion_code_id: string;
}

interface UpdateBody extends BaseBody {
  action: "update";
  promotion_code_id: string;
  coupon_id?: string;
  name?: string;
  coupon_type?: "internal" | "marketing";
}

interface TogglePromoCodesBody extends BaseBody {
  action: "toggle_promo_codes";
  product_id: string;
  enabled: boolean;
}

type RequestBody =
  | ListBody
  | CreateCouponBody
  | SetDefaultBody
  | DeactivateBody
  | UpdateBody
  | TogglePromoCodesBody;

async function getTenantStripeKey(
  supabase: ReturnType<typeof createClient>,
  tenantId: string,
): Promise<string | null> {
  const { data, error } = await supabase
    .from("tenant_payment_providers")
    .select("settings, payment_providers!inner ( key )")
    .eq("tenant_id", tenantId)
    .eq("is_enabled", true)
    .eq("payment_providers.key", "stripe")
    .maybeSingle();

  if (error || !data) return null;
  const settings = data.settings as Record<string, string> | null;
  return settings?.secret_key ?? null;
}

async function findStripeProductId(
  secretKey: string,
  alliaProductId: string,
): Promise<string | null> {
  const query = `metadata['allia_product_id']:'${alliaProductId}'`;
  const res = await fetch(
    `https://api.stripe.com/v1/products/search?query=${encodeURIComponent(query)}&limit=1`,
    { headers: { Authorization: `Bearer ${secretKey}` } },
  );
  if (!res.ok) return null;
  const body = await res.json();
  const products: Array<{ id: string }> = body?.data ?? [];
  return products.length > 0 ? products[0].id : null;
}

async function authorizeAdmin(
  supabaseAdmin: ReturnType<typeof createClient>,
  userId: string,
  tenantId: string,
): Promise<boolean> {
  const { data: adminUser } = await supabaseAdmin
    .from("admin_users")
    .select("id, is_active")
    .eq("auth_user_id", userId)
    .maybeSingle();

  if (!adminUser || adminUser.is_active === false) return false;

  const { data: isSuperadmin } = await supabaseAdmin.rpc(
    "is_platform_superadmin",
    { _auth_user_id: userId },
  );
  if (isSuperadmin) return true;

  const { data: membership } = await supabaseAdmin
    .from("tenant_memberships")
    .select("id")
    .eq("admin_user_id", adminUser.id)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  return !!membership;
}

async function handleList(
  body: ListBody,
  stripeKey: string,
): Promise<Response> {
  const stripeProductId = await findStripeProductId(stripeKey, body.product_id);
  if (!stripeProductId) {
    return jsonResponse({
      success: true,
      data: [],
      stripe_product_found: false,
    });
  }

  const allCodes: unknown[] = [];
  let hasMore = true;
  let startingAfter: string | undefined;

  while (hasMore) {
    const params = new URLSearchParams({ limit: "100" });
    // Support both legacy coupon expansion and newer promotion.coupon shape.
    params.append("expand[]", "data.coupon");
    params.append("expand[]", "data.promotion.coupon");
    if (startingAfter) params.set("starting_after", startingAfter);

    const listRes = await fetch(
      `https://api.stripe.com/v1/promotion_codes?${params.toString()}`,
      { headers: { Authorization: `Bearer ${stripeKey}` } },
    );

    if (!listRes.ok) {
      const err = await listRes.json();
      return errorResponse(
        "STRIPE_ERROR",
        err.error?.message ?? "Failed to list promotion codes",
        500,
      );
    }

    const listBody = await listRes.json();
    const codes: Array<{
      id: string;
      metadata?: Record<string, string>;
      coupon?: { applies_to?: { products?: string[] } };
      // Newer Stripe response shape.
      promotion?: { coupon?: { applies_to?: { products?: string[] } } };
      // Expanded coupon can include metadata when available.
      promotion_coupon?: { metadata?: Record<string, string> };
    }> = listBody.data ?? [];

    const filtered = codes.filter((pc) => {
      const couponObject = pc.coupon ?? pc.promotion?.coupon;
      const appliesToProducts = couponObject?.applies_to?.products;

      // Primary scoping strategy: metadata tag written at creation time.
      const promotionMetadataProductId = pc.metadata?.allia_product_id;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const couponMetadataProductId = (couponObject as any)?.metadata
        ?.allia_product_id;
      if (
        promotionMetadataProductId === body.product_id ||
        couponMetadataProductId === body.product_id
      ) {
        return true;
      }

      // Fallback for coupons that expose applies_to products.
      return (
        Array.isArray(appliesToProducts) &&
        appliesToProducts.includes(stripeProductId)
      );
    });

    // Normalize response shape so frontend can always access `promotionCode.coupon`.
    const normalized = filtered.map((pc) => ({
      ...pc,
      coupon: pc.coupon ?? pc.promotion?.coupon ?? null,
      coupon_type:
        (pc.metadata?.coupon_type as "internal" | "marketing" | undefined) ??
        null,
      created_by: pc.metadata?.created_by_email ?? null,
    }));

    allCodes.push(...normalized);
    hasMore = listBody.has_more ?? false;
    if (hasMore && codes.length > 0) {
      startingAfter = codes[codes.length - 1].id;
    } else {
      hasMore = false;
    }
  }

  return jsonResponse({
    success: true,
    data: allCodes,
    stripe_product_found: true,
    stripe_product_id: stripeProductId,
  });
}

async function handleCreate(
  body: CreateCouponBody,
  stripeKey: string,
  createdByEmail: string,
): Promise<Response> {
  const { product_id, code, discount_type, duration } = body;

  if (!code || !discount_type || !duration) {
    return errorResponse(
      "MISSING_PARAMS",
      "code, discount_type, and duration are required",
    );
  }
  if (!["percent", "amount"].includes(discount_type)) {
    return errorResponse(
      "INVALID_DISCOUNT_TYPE",
      'discount_type must be "percent" or "amount"',
    );
  }
  if (!["once", "repeating", "forever"].includes(duration)) {
    return errorResponse(
      "INVALID_DURATION",
      'duration must be "once", "repeating", or "forever"',
    );
  }
  if (discount_type === "percent") {
    if (
      typeof body.percent_off !== "number" ||
      body.percent_off < 1 ||
      body.percent_off > 100
    ) {
      return errorResponse(
        "INVALID_PERCENT",
        "percent_off must be a number between 1 and 100",
      );
    }
  }
  if (discount_type === "amount") {
    if (typeof body.amount_off !== "number" || body.amount_off < 1) {
      return errorResponse(
        "INVALID_AMOUNT",
        "amount_off must be a positive integer (cents)",
      );
    }
    if (!body.currency) {
      return errorResponse(
        "MISSING_CURRENCY",
        "currency is required when discount_type is amount",
      );
    }
  }
  if (duration === "repeating") {
    if (
      typeof body.duration_in_months !== "number" ||
      body.duration_in_months < 1
    ) {
      return errorResponse(
        "INVALID_DURATION_MONTHS",
        "duration_in_months must be a positive integer when duration is repeating",
      );
    }
  }

  const sanitisedCode = code.toUpperCase().replace(/[^A-Z0-9\-_]/g, "");
  if (!sanitisedCode) {
    return errorResponse(
      "INVALID_CODE",
      "Promotion code must contain at least one alphanumeric character",
    );
  }

  const stripeProductId = await findStripeProductId(stripeKey, product_id);
  if (!stripeProductId) {
    return errorResponse(
      "PRODUCT_NOT_SYNCED",
      "This product has not been synced to Stripe. Please sync it first from the Provider Platforms tab.",
      422,
    );
  }

  const stripeHeaders = {
    Authorization: `Bearer ${stripeKey}`,
    "Content-Type": "application/x-www-form-urlencoded",
  };

  // Step 1: Create Stripe Coupon
  const couponParams = new URLSearchParams();
  couponParams.append("duration", duration);
  if (discount_type === "percent") {
    couponParams.append("percent_off", String(body.percent_off));
  } else {
    couponParams.append("amount_off", String(body.amount_off));
    couponParams.append("currency", body.currency!.toLowerCase());
  }
  if (duration === "repeating") {
    couponParams.append("duration_in_months", String(body.duration_in_months));
  }
  couponParams.append("applies_to[products][0]", stripeProductId);
  couponParams.append("metadata[allia_product_id]", product_id);
  if (createdByEmail) {
    couponParams.append("metadata[created_by_email]", createdByEmail);
  }
  if (body.name) {
    couponParams.append("name", body.name.slice(0, 40));
  }

  const couponRes = await fetch("https://api.stripe.com/v1/coupons", {
    method: "POST",
    headers: stripeHeaders,
    body: couponParams.toString(),
  });

  if (!couponRes.ok) {
    const err = await couponRes.json();
    return errorResponse(
      "STRIPE_COUPON_ERROR",
      err.error?.message ?? "Failed to create Stripe coupon",
      500,
    );
  }

  const coupon = await couponRes.json();

  // Step 2: Create Stripe Promotion Code
  const buildPromoParams = (shape: "new" | "legacy") => {
    const promoParams = new URLSearchParams();
    if (shape === "new") {
      // Newer Stripe API shape: promotion[type]=coupon + promotion[coupon]=...
      promoParams.append("promotion[type]", "coupon");
      promoParams.append("promotion[coupon]", coupon.id);
    } else {
      // Legacy Stripe API shape.
      promoParams.append("coupon", coupon.id);
    }
    promoParams.append("code", sanitisedCode);
    if (typeof body.max_redemptions === "number" && body.max_redemptions > 0) {
      promoParams.append("max_redemptions", String(body.max_redemptions));
    }
    if (typeof body.expires_at === "number" && body.expires_at > 0) {
      promoParams.append("expires_at", String(body.expires_at));
    }
    promoParams.append("metadata[allia_product_id]", product_id);
    if (createdByEmail) {
      promoParams.append("metadata[created_by_email]", createdByEmail);
    }
    if (body.coupon_type) {
      promoParams.append("metadata[coupon_type]", body.coupon_type);
    }
    return promoParams;
  };

  let promoRes = await fetch("https://api.stripe.com/v1/promotion_codes", {
    method: "POST",
    headers: stripeHeaders,
    body: buildPromoParams("new").toString(),
  });

  // Backward compatibility: retry using legacy payload if API rejects the new shape.
  if (!promoRes.ok) {
    const firstErr = await promoRes.json();
    const firstMessage = String(firstErr?.error?.message ?? "").toLowerCase();
    const firstParam = String(firstErr?.error?.param ?? "").toLowerCase();

    if (
      firstMessage.includes("unknown parameter") &&
      (firstParam.includes("promotion") || firstMessage.includes("promotion"))
    ) {
      promoRes = await fetch("https://api.stripe.com/v1/promotion_codes", {
        method: "POST",
        headers: stripeHeaders,
        body: buildPromoParams("legacy").toString(),
      });
    } else {
      // Preserve the original error for downstream handling.
      return errorResponse(
        "STRIPE_PROMO_ERROR",
        firstErr.error?.message ?? "Failed to create Stripe promotion code",
        500,
      );
    }
  }

  if (!promoRes.ok) {
    const err = await promoRes.json();
    // Best-effort cleanup: delete the orphaned coupon
    await fetch(`https://api.stripe.com/v1/coupons/${coupon.id}`, {
      method: "DELETE",
      headers: stripeHeaders,
    });
    return errorResponse(
      "STRIPE_PROMO_ERROR",
      err.error?.message ?? "Failed to create Stripe promotion code",
      500,
    );
  }

  const promotionCode = await promoRes.json();
  return jsonResponse(
    {
      success: true,
      data: {
        ...promotionCode,
        coupon,
        coupon_type:
          (promotionCode.metadata?.coupon_type as string | undefined) ?? null,
        created_by:
          (promotionCode.metadata?.created_by_email as string | undefined) ??
          null,
      },
    },
    201,
  );
}

async function handleUpdate(
  body: UpdateBody,
  stripeKey: string,
): Promise<Response> {
  const { promotion_code_id, coupon_id, name, coupon_type } = body;

  if (!promotion_code_id) {
    return errorResponse("MISSING_PARAMS", "promotion_code_id is required");
  }

  const stripeHeaders = {
    Authorization: `Bearer ${stripeKey}`,
    "Content-Type": "application/x-www-form-urlencoded",
  };

  if (coupon_type !== undefined) {
    const promoParams = new URLSearchParams();
    promoParams.append(
      "metadata[coupon_type]",
      coupon_type === null ? "" : coupon_type,
    );
    const promoRes = await fetch(
      `https://api.stripe.com/v1/promotion_codes/${promotion_code_id}`,
      { method: "POST", headers: stripeHeaders, body: promoParams.toString() },
    );
    if (!promoRes.ok) {
      const err = await promoRes.json();
      return errorResponse(
        "STRIPE_ERROR",
        err.error?.message ?? "Failed to update promotion code",
        500,
      );
    }
  }

  if (name !== undefined && coupon_id) {
    const couponParams = new URLSearchParams();
    couponParams.append("name", name.slice(0, 40));
    const couponRes = await fetch(
      `https://api.stripe.com/v1/coupons/${coupon_id}`,
      {
        method: "POST",
        headers: stripeHeaders,
        body: couponParams.toString(),
      },
    );
    if (!couponRes.ok) {
      const err = await couponRes.json();
      return errorResponse(
        "STRIPE_COUPON_ERROR",
        err.error?.message ?? "Failed to update coupon name",
        500,
      );
    }
  }

  const getRes = await fetch(
    `https://api.stripe.com/v1/promotion_codes/${promotion_code_id}?expand[]=coupon`,
    { headers: { Authorization: `Bearer ${stripeKey}` } },
  );
  if (!getRes.ok) {
    const err = await getRes.json();
    return errorResponse(
      "STRIPE_ERROR",
      err.error?.message ?? "Failed to fetch updated promotion code",
      500,
    );
  }

  const updated = await getRes.json();
  const couponObject = updated.coupon ?? updated.promotion?.coupon ?? null;
  return jsonResponse({
    success: true,
    data: {
      ...updated,
      coupon: couponObject,
      coupon_type:
        (updated.metadata?.coupon_type as string | undefined) ?? null,
    },
  });
}

async function handleSetDefault(
  body: SetDefaultBody,
  supabaseAdmin: ReturnType<typeof createClient>,
): Promise<Response> {
  const { product_id, tenant_id, promotion_code_id } = body;
  if (!product_id) {
    return errorResponse("MISSING_PARAMS", "product_id is required");
  }

  const { data: productRow, error: fetchError } = await supabaseAdmin
    .from("products")
    .select("id, metadata")
    .eq("id", product_id)
    .eq("tenant_id", tenant_id)
    .maybeSingle();

  if (fetchError || !productRow) {
    return errorResponse("NOT_FOUND", "Product not found", 404);
  }

  const currentMeta =
    (productRow.metadata as Record<string, unknown> | null) ?? {};

  let updatedMeta: Record<string, unknown>;
  if (promotion_code_id === null) {
    const { stripe_promotion_code_id: _removed, ...rest } = currentMeta;
    updatedMeta = rest;
  } else {
    updatedMeta = {
      ...currentMeta,
      stripe_promotion_code_id: promotion_code_id,
    };
  }

  const { error: updateError } = await supabaseAdmin
    .from("products")
    .update({ metadata: updatedMeta, updated_at: new Date().toISOString() })
    .eq("id", product_id)
    .eq("tenant_id", tenant_id);

  if (updateError) {
    return errorResponse(
      "UPDATE_ERROR",
      `Failed to update product metadata: ${updateError.message}`,
      500,
    );
  }

  return jsonResponse({
    success: true,
    data: { product_id, stripe_promotion_code_id: promotion_code_id },
  });
}

async function handleDeactivate(
  body: DeactivateBody,
  stripeKey: string,
): Promise<Response> {
  const { promotion_code_id } = body;
  if (!promotion_code_id) {
    return errorResponse("MISSING_PARAMS", "promotion_code_id is required");
  }

  const res = await fetch(
    `https://api.stripe.com/v1/promotion_codes/${promotion_code_id}`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({ active: "false" }).toString(),
    },
  );

  if (!res.ok) {
    const err = await res.json();
    return errorResponse(
      "STRIPE_ERROR",
      err.error?.message ?? "Failed to deactivate promotion code",
      500,
    );
  }

  const updated = await res.json();
  return jsonResponse({ success: true, data: updated });
}

async function handleTogglePromoCodes(
  body: TogglePromoCodesBody,
  supabaseAdmin: ReturnType<typeof createClient>,
): Promise<Response> {
  const { product_id, tenant_id, enabled } = body;
  if (!product_id) {
    return errorResponse("MISSING_PARAMS", "product_id is required");
  }
  if (typeof enabled !== "boolean") {
    return errorResponse("MISSING_PARAMS", "enabled (boolean) is required");
  }

  const { data: productRow, error: fetchError } = await supabaseAdmin
    .from("products")
    .select("id, metadata")
    .eq("id", product_id)
    .eq("tenant_id", tenant_id)
    .maybeSingle();

  if (fetchError) {
    console.error("[toggle_promo_codes] fetch error:", fetchError);
    return errorResponse(
      "DB_ERROR",
      `Database error: ${fetchError.message}`,
      500,
    );
  }
  if (!productRow) {
    return errorResponse(
      "NOT_FOUND",
      `Product ${product_id} not found for tenant ${tenant_id}`,
      404,
    );
  }

  const currentMeta =
    (productRow.metadata as Record<string, unknown> | null) ?? {};

  // Remove stale auto-apply field if present, then set the toggle.

  const { stripe_promotion_code_id, ...rest } = currentMeta;
  const updatedMeta: Record<string, unknown> = enabled
    ? { ...rest, allow_promo_codes: true }
    : { ...rest };

  const { error: updateError } = await supabaseAdmin
    .from("products")
    .update({ metadata: updatedMeta, updated_at: new Date().toISOString() })
    .eq("id", product_id)
    .eq("tenant_id", tenant_id);

  if (updateError) {
    console.error("[toggle_promo_codes] update error:", updateError);
    return errorResponse(
      "UPDATE_ERROR",
      `Failed to update product metadata: ${updateError.message}`,
      500,
    );
  }

  return jsonResponse({
    success: true,
    data: { product_id, allow_promo_codes: enabled },
  });
}

// ─── Main handler ─────────────────────────────────────────────────────────────

serve(async (req) => {
  try {
    return await handleRequest(req);
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    const stack = err instanceof Error ? err.stack : undefined;
    console.error("[stripe-coupon-api] Unhandled exception:", message, stack);
    return errorResponse("INTERNAL_ERROR", message, 500);
  }
});

async function handleRequest(req: Request): Promise<Response> {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  if (req.method !== "POST") {
    return errorResponse("METHOD_NOT_ALLOWED", "Only POST is supported", 405);
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY")!;

  const authHeader = req.headers.get("Authorization");
  if (!authHeader) {
    return errorResponse("UNAUTHORIZED", "Missing authorization header", 401);
  }

  const supabaseAuth = createClient(supabaseUrl, supabaseAnonKey, {
    global: { headers: { Authorization: authHeader } },
    auth: { autoRefreshToken: false, persistSession: false },
  });
  const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey);

  const {
    data: { user },
    error: userError,
  } = await supabaseAuth.auth.getUser();

  if (userError || !user) {
    return errorResponse("UNAUTHORIZED", "Invalid session", 401);
  }

  let body: RequestBody;
  try {
    body = await req.json();
  } catch {
    return errorResponse("INVALID_BODY", "Request body must be valid JSON");
  }

  const { action, tenant_id } = body;

  if (!action) return errorResponse("MISSING_ACTION", "action is required");
  if (!tenant_id)
    return errorResponse("MISSING_PARAMS", "tenant_id is required");

  if (!(await authorizeAdmin(supabaseAdmin, user.id, tenant_id))) {
    return errorResponse("FORBIDDEN", "Insufficient permissions", 403);
  }

  if (action === "set_default") {
    return handleSetDefault(body as SetDefaultBody, supabaseAdmin);
  }

  if (action === "toggle_promo_codes") {
    return handleTogglePromoCodes(body as TogglePromoCodesBody, supabaseAdmin);
  }

  // Remaining actions require the Stripe key
  const stripeKey = await getTenantStripeKey(supabaseAdmin, tenant_id);
  if (!stripeKey) {
    return errorResponse(
      "NO_STRIPE_KEY",
      "No Stripe provider configured for this tenant",
    );
  }

  if (action === "list") return handleList(body as ListBody, stripeKey);
  if (action === "create")
    return handleCreate(body as CreateCouponBody, stripeKey, user.email ?? "");
  if (action === "update") return handleUpdate(body as UpdateBody, stripeKey);
  if (action === "deactivate")
    return handleDeactivate(body as DeactivateBody, stripeKey);

  return errorResponse(
    "INVALID_ACTION",
    `Unknown action: ${action}. Valid actions are: list, create, update, set_default, deactivate, toggle_promo_codes`,
  );
}
