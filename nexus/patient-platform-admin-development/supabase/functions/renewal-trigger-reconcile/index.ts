import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";

// Grace window: a manual trigger normally produces a renewal order within
// seconds (Stripe invoice -> RTDH -> renewal_order_create). If none exists this
// long after the trigger, we flag it — the patient may have been charged with
// no order created.
const GRACE_PERIOD_MS = 15 * 60 * 1000;
// How far back we keep re-checking already-`unresolved` attempts. A late RTDH
// delivery (after the grace window) should flip the attempt back to `fulfilled`
// rather than leave a stale false alarm. Bounded so the sweep stays cheap.
const UNRESOLVED_RECHECK_MS = 7 * 24 * 60 * 60 * 1000;
const MAX_ATTEMPTS_PER_RUN = 200;

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
    methods: "POST, OPTIONS",
  });
}

function jsonResponse(
  req: Request,
  body: Record<string, unknown>,
  status = 200,
) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...getCorsHeaders(req), "Content-Type": "application/json" },
  });
}

interface RenewalTriggerAttempt {
  id: string;
  tenant_id: string;
  subscription_id: string;
  provider_subscription_id: string | null;
  triggered_at: string;
}

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;

// Look for a renewal order that appeared for this subscription at/after the
// trigger. Returns { orderId } when found, or { error: true } on a query
// failure (caller should leave the attempt untouched and retry next tick).
async function findRenewalOrderId(
  db: SupabaseClient,
  attempt: RenewalTriggerAttempt,
): Promise<{ orderId: string | null; error: boolean }> {
  const { data: orders, error } = await db
    .from("orders")
    .select("id, created_at")
    .eq("subscription_id", attempt.subscription_id)
    .eq("subscription_order_type", "renewal")
    .gte("created_at", attempt.triggered_at)
    .order("created_at", { ascending: false })
    .limit(1);

  if (error) {
    console.error("[renewal-trigger-reconcile] order lookup failed", {
      attemptId: attempt.id,
      subscriptionId: attempt.subscription_id,
      error,
    });
    return { orderId: null, error: true };
  }

  return { orderId: (orders || [])[0]?.id ?? null, error: false };
}

async function markFulfilled(
  db: SupabaseClient,
  attemptId: string,
  orderId: string,
): Promise<void> {
  const nowIso = new Date().toISOString();
  await db
    .from("renewal_trigger_attempts")
    .update({
      status: "fulfilled",
      resolved_order_id: orderId,
      resolved_at: nowIso,
      updated_at: nowIso,
    })
    .eq("id", attemptId);
}

// Scheduled sweep (pg_cron): resolve admin-triggered renewals.
// - `pending` attempts past the grace window become `fulfilled` (order found) or
//   `unresolved` (with an ops alert) when none appeared.
// - `unresolved` attempts are re-checked and flipped back to `fulfilled` if the
//   order arrived late (no re-alert), so a delayed RTDH delivery doesn't leave a
//   stale false positive.
// Alert-only — RTDH is the authoritative order creator, so we never fabricate
// the order here.
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

  // Cron-only: the scheduled pg_cron tick presents the shared CRON_SECRET
  // (same convention as outbound-webhook-sweeper / friendbuy-reconcile).
  const cronSecret = Deno.env.get("CRON_SECRET");
  const authHeader = req.headers.get("Authorization");
  if (!cronSecret || authHeader !== `Bearer ${cronSecret}`) {
    console.warn("[renewal-trigger-reconcile] unauthorized");
    return jsonResponse(req, { error: "Unauthorized" }, 401);
  }

  const supabaseAdmin = createClient(supabaseUrl, serviceKey, {
    global: { headers: { "x-request-source": "renewal-trigger-reconcile" } },
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const nowMs = Date.now();
  const graceCutoffIso = new Date(nowMs - GRACE_PERIOD_MS).toISOString();
  const recheckFloorIso = new Date(nowMs - UNRESOLVED_RECHECK_MS).toISOString();

  let fulfilled = 0;
  let unresolved = 0;
  let recovered = 0;

  // 1) Pending attempts past the grace window.
  const { data: pendingRows, error: pendingError } = await supabaseAdmin
    .from("renewal_trigger_attempts")
    .select(
      "id, tenant_id, subscription_id, provider_subscription_id, triggered_at",
    )
    .eq("status", "pending")
    .lt("triggered_at", graceCutoffIso)
    .order("triggered_at", { ascending: true })
    .limit(MAX_ATTEMPTS_PER_RUN);

  if (pendingError) {
    console.error("[renewal-trigger-reconcile] failed to load pending", {
      error: pendingError,
    });
    return jsonResponse(req, { error: "Failed to load attempts" }, 500);
  }

  for (const attempt of (pendingRows || []) as RenewalTriggerAttempt[]) {
    const { orderId, error } = await findRenewalOrderId(supabaseAdmin, attempt);
    if (error) continue; // leave pending; retry next tick

    if (orderId) {
      await markFulfilled(supabaseAdmin, attempt.id, orderId);
      fulfilled += 1;
      continue;
    }

    // No order after the grace window — the patient may have been charged with
    // no order. Flag for ops (structured error feeds edge-function log
    // monitoring). We do NOT auto-create the order.
    await supabaseAdmin
      .from("renewal_trigger_attempts")
      .update({
        status: "unresolved",
        notes:
          `No renewal order found within ${GRACE_PERIOD_MS / 60000} minutes of trigger.`,
        updated_at: new Date().toISOString(),
      })
      .eq("id", attempt.id);
    unresolved += 1;

    console.error("renewal_trigger_safeguard", {
      attemptId: attempt.id,
      tenantId: attempt.tenant_id,
      subscriptionId: attempt.subscription_id,
      providerSubscriptionId: attempt.provider_subscription_id,
      triggeredAt: attempt.triggered_at,
    });
  }

  // 2) Re-check recent `unresolved` attempts: a late RTDH delivery should clear
  //    the alarm. Only flips to `fulfilled`; never re-alerts.
  const { data: unresolvedRows, error: unresolvedError } = await supabaseAdmin
    .from("renewal_trigger_attempts")
    .select(
      "id, tenant_id, subscription_id, provider_subscription_id, triggered_at",
    )
    .eq("status", "unresolved")
    .gte("triggered_at", recheckFloorIso)
    .order("triggered_at", { ascending: true })
    .limit(MAX_ATTEMPTS_PER_RUN);

  if (unresolvedError) {
    console.error("[renewal-trigger-reconcile] failed to load unresolved", {
      error: unresolvedError,
    });
  } else {
    for (const attempt of (unresolvedRows || []) as RenewalTriggerAttempt[]) {
      const { orderId, error } = await findRenewalOrderId(
        supabaseAdmin,
        attempt,
      );
      if (error || !orderId) continue;

      await markFulfilled(supabaseAdmin, attempt.id, orderId);
      recovered += 1;
      console.log("[renewal-trigger-reconcile] recovered late renewal order", {
        attemptId: attempt.id,
        subscriptionId: attempt.subscription_id,
        orderId,
      });
    }
  }

  console.log("[renewal-trigger-reconcile] sweep complete", {
    fulfilled,
    unresolved,
    recovered,
  });

  return jsonResponse(req, { fulfilled, unresolved, recovered });
});
