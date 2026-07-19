// cleanup-unpaid-helper: cancels stale, unpaid orders left in `order_created`.
//
// Context (Option 2 / PP-566): the embedded-checkout order is created at
// `order_created` when the customer reaches payment, before authorization. If
// they never authorize a payment method, the order lingers unpaid. This helper
// finds such orders older than a tenant-configurable window and cancels them,
// voiding the (uncaptured) Stripe PaymentIntent with reason `abandoned`.
//
// Reuse-first: no new tables. The window is read from
// `tenant_settings.metadata.unpaid_order_cancel_hours` (configurable in Nexus);
// the order is moved to `order_cancelled`; the PaymentIntent cancel mirrors the
// existing provider-rejection cancel path.

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = any;

const DEFAULT_UNPAID_CANCEL_HOURS = 72;

interface CleanupResult {
  orderId: string;
  orderNumber: string | null;
  action: "cancelled" | "skipped" | "error";
  message: string;
}

function resolveCancelHours(metadata: unknown): number {
  if (metadata && typeof metadata === "object" && !Array.isArray(metadata)) {
    const raw = (metadata as Record<string, unknown>).unpaid_order_cancel_hours;
    const n = typeof raw === "number"
      ? raw
      : typeof raw === "string"
      ? Number(raw)
      : NaN;
    if (Number.isFinite(n) && n > 0) {
      return n;
    }
  }
  return DEFAULT_UNPAID_CANCEL_HOURS;
}

async function getTenantStripeSecretKey(
  supabase: SupabaseAdminClient,
  tenantId: string,
): Promise<string | null> {
  const { data: provider } = await supabase
    .from("tenant_payment_providers")
    .select("settings, payment_providers!inner ( key )")
    .eq("tenant_id", tenantId)
    .eq("is_enabled", true)
    .eq("payment_providers.key", "stripe")
    .maybeSingle();

  const settings = provider?.settings;
  if (settings && typeof settings === "object" && !Array.isArray(settings)) {
    const key = (settings as Record<string, unknown>).secret_key;
    if (typeof key === "string" && key.trim()) return key.trim();
  }
  return null;
}

async function cancelStripePaymentIntentAsAbandoned(
  paymentIntentId: string,
  stripeSecretKey: string,
  orderId: string,
): Promise<{ ok: boolean; skip: boolean; detail: string }> {
  // First read the PaymentIntent's real status — Stripe is the source of truth,
  // not the (possibly stale) local transaction row.
  const getRes = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}`,
    { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
  );

  if (getRes.ok) {
    const pi = await getRes.json();
    const status: string = pi?.status ?? "";
    // Authorized/paid/in-flight → the customer DID pay; never cancel the order.
    if (
      status === "requires_capture" ||
      status === "succeeded" ||
      status === "processing"
    ) {
      return {
        ok: false,
        skip: true,
        detail: `payment_intent_${status}_skip_cancel`,
      };
    }
    // Already canceled → nothing to void; let the order cancel proceed.
    if (status === "canceled") {
      return { ok: true, skip: false, detail: "payment_intent_already_canceled" };
    }
  }

  // Otherwise it's an unconfirmed intent (requires_payment_method /
  // requires_confirmation / requires_action) → safe to cancel as abandoned.
  const res = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}/cancel`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
        "Idempotency-Key": `allia_unpaid_order_pi_cancel_${orderId}`,
      },
      body: "cancellation_reason=abandoned",
    },
  );

  if (res.ok) return { ok: true, skip: false, detail: "payment_intent_canceled" };

  const text = await res.text();
  // If Stripe says it can't be canceled because it's authorized, skip (paid).
  if (/payment_intent_unexpected_state/i.test(text)) {
    return { ok: false, skip: true, detail: "payment_intent_unexpected_state_skip" };
  }
  if (/already been canceled/i.test(text)) {
    return { ok: true, skip: false, detail: "payment_intent_already_terminal" };
  }
  return { ok: false, skip: false, detail: `stripe_cancel_failed: ${text.slice(0, 120)}` };
}

/**
 * Cancel stale unpaid `order_created` orders across all tenants.
 * Returns one result row per order touched.
 */
export async function cleanupUnpaidOrders(
  supabase: SupabaseAdminClient,
  requestId: string,
  nowMs: number,
): Promise<CleanupResult[]> {
  // Resolve the order_created and order_cancelled status ids once.
  const { data: statuses, error: statusError } = await supabase
    .from("order_statuses")
    .select("id, status_key")
    .in("status_key", ["order_created", "order_cancelled"]);

  if (statusError || !statuses) {
    console.warn("cleanup-unpaid: failed to load statuses", {
      requestId,
      error: statusError?.message,
    });
    return [];
  }

  const createdStatusId = statuses.find((s: { status_key: string }) =>
    s.status_key === "order_created"
  )?.id;
  const cancelledStatusId = statuses.find((s: { status_key: string }) =>
    s.status_key === "order_cancelled"
  )?.id;

  if (!createdStatusId || !cancelledStatusId) {
    console.warn("cleanup-unpaid: required statuses not configured", {
      requestId,
    });
    return [];
  }

  // Candidate orders: still in order_created and older than the SHORTEST window
  // we'd ever use (1h floor) — filtered + capped at the DB level so the job stays
  // fast regardless of table size. Per-tenant window is applied below.
  // Cap the batch small enough that the per-order Stripe status checks always
  // finish within the function timeout; the scheduled job runs repeatedly so a
  // backlog drains over successive runs.
  const MIN_AGE_HOURS = 1;
  const BATCH_LIMIT = 25;
  const minAgeCutoffIso = new Date(
    nowMs - MIN_AGE_HOURS * 60 * 60 * 1000,
  ).toISOString();

  const { data: candidates, error: candidatesError } = await supabase
    .from("orders")
    .select("id, order_number, tenant_id, created_at, status_id")
    .eq("status_id", createdStatusId)
    .lt("created_at", minAgeCutoffIso)
    .order("created_at", { ascending: true })
    .limit(BATCH_LIMIT);

  if (candidatesError || !candidates) {
    console.warn("cleanup-unpaid: failed to load candidate orders", {
      requestId,
      error: candidatesError?.message,
    });
    return [];
  }

  if (candidates.length === 0) {
    console.info("cleanup-unpaid: no stale candidates", { requestId });
    return [];
  }

  // Bulk-fetch all transactions for the candidate orders in ONE query, then
  // evaluate paid-state + payment-intent in memory (avoids N+1 round-trips).
  const candidateIds = candidates.map((o: { id: string }) => o.id);
  const { data: allTx } = await supabase
    .from("order_payment_provider_transactions")
    .select("order_id, payment_status, provider_payment_intent_id, created_at")
    .in("order_id", candidateIds);

  const paidOrderIds = new Set<string>();
  const piByOrder = new Map<string, string>();
  for (const tx of (allTx ?? [])) {
    if (["requires_capture", "succeeded", "paid"].includes(tx.payment_status)) {
      paidOrderIds.add(tx.order_id);
    }
    if (tx.provider_payment_intent_id && !piByOrder.has(tx.order_id)) {
      piByOrder.set(tx.order_id, tx.provider_payment_intent_id);
    }
  }

  // Cache per-tenant config + stripe key.
  const tenantHours = new Map<string, number>();
  const tenantKey = new Map<string, string | null>();
  const results: CleanupResult[] = [];

  for (const order of candidates) {
    const tenantId: string = order.tenant_id;

    // Window (per tenant, from Nexus-configurable tenant_settings.metadata).
    if (!tenantHours.has(tenantId)) {
      const { data: settings } = await supabase
        .from("tenant_settings")
        .select("metadata")
        .eq("tenant_id", tenantId)
        .maybeSingle();
      tenantHours.set(tenantId, resolveCancelHours(settings?.metadata));
    }
    const cutoffMs = nowMs - tenantHours.get(tenantId)! * 60 * 60 * 1000;
    if (new Date(order.created_at).getTime() > cutoffMs) {
      continue; // still within the wait window
    }

    // Unpaid check (from the bulk-fetched transactions, no per-order query).
    if (paidOrderIds.has(order.id)) {
      continue; // payment authorized/captured → not abandoned
    }

    const paymentIntentId: string | null = piByOrder.get(order.id) ?? null;

    if (paymentIntentId) {
      if (!tenantKey.has(tenantId)) {
        tenantKey.set(tenantId, await getTenantStripeSecretKey(supabase, tenantId));
      }
      const secret = tenantKey.get(tenantId);
      if (secret) {
        const cancel = await cancelStripePaymentIntentAsAbandoned(
          paymentIntentId,
          secret,
          order.id,
        );
        if (cancel.skip) {
          // The PaymentIntent is authorized/paid — the customer DID pay, so this
          // order is not abandoned. Leave it untouched.
          results.push({
            orderId: order.id,
            orderNumber: order.order_number,
            action: "skipped",
            message: cancel.detail,
          });
          continue;
        }
        if (!cancel.ok) {
          results.push({
            orderId: order.id,
            orderNumber: order.order_number,
            action: "error",
            message: cancel.detail,
          });
          continue; // don't cancel the order if we couldn't void the auth
        }
      }
    }

    // Move the order to order_cancelled.
    const nowIso = new Date(nowMs).toISOString();
    const { error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: cancelledStatusId,
        status_changed_at: nowIso,
        cancelled_at: nowIso,
      })
      .eq("id", order.id)
      .eq("tenant_id", tenantId)
      .eq("status_id", createdStatusId); // optimistic guard against races

    if (updateError) {
      results.push({
        orderId: order.id,
        orderNumber: order.order_number,
        action: "error",
        message: `order_update_failed: ${updateError.message}`,
      });
      continue;
    }

    await supabase.from("order_status_history").insert({
      order_id: order.id,
      status_id: cancelledStatusId,
      notes:
        "Auto-cancelled: no payment authorized within the unpaid-order window (Option 2 checkout).",
    });

    results.push({
      orderId: order.id,
      orderNumber: order.order_number,
      action: "cancelled",
      message: paymentIntentId
        ? "Order cancelled; PaymentIntent voided as abandoned."
        : "Order cancelled (no PaymentIntent on file).",
    });
  }

  console.info("cleanup-unpaid: complete", {
    requestId,
    candidates: candidates.length,
    cancelled: results.filter((r) => r.action === "cancelled").length,
    errors: results.filter((r) => r.action === "error").length,
  });

  return results;
}
