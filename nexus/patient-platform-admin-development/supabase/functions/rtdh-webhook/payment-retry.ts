// deno-lint-ignore no-import-prefix
import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { asNonEmptyString, asObject } from "./validation.ts";
import type { RtdhEventPayload } from "./validation.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

const PAYMENT_FAILED_STATUS_KEY = "payment_failed";
const PAYMENT_PENDING_STATUS_KEY = "payment_pending";

/**
 * A Stripe customer.updated forwarded by RTDH signals that the patient updated
 * their payment details (billing portal) after a payment failure. Rather than
 * walking the order back to payment_pending up front — which the direct-status
 * regression guard rightly refuses — we retry the payment:
 *
 * - Invoice-backed orders (renewals): pay the failed invoice synchronously and
 *   only reset the order to payment_pending once Stripe confirms the charge,
 *   so the subsequent RTDH payment_collected event can advance it through the
 *   normal path.
 * - PaymentIntent-backed orders (first orders, manual capture, no invoice):
 *   reset to payment_pending and trigger order-lifecycle, which owns the
 *   manual-capture confirm/capture retry mechanics and will re-attempt with
 *   the customer's updated default payment method. A renewed failure flows
 *   back as payment_intent.payment_failed and returns the order to
 *   payment_failed.
 */
export function isCustomerUpdatedEvent(payload?: RtdhEventPayload): boolean {
  const eventType = typeof payload?.event_type === "string"
    ? payload.event_type.trim().toLowerCase()
    : "";
  return eventType === "customer.updated";
}

export type CustomerUpdatedRetryResult = {
  action:
    | "payment_retried"
    | "payment_retry_scheduled"
    | "retry_failed"
    | "skipped";
  reason?: string;
  orderId?: string | null;
  invoiceId?: string | null;
};

async function resolveTenantIdForRetry(
  supabase: SupabaseAdminClient,
  internalTenantId: string,
): Promise<string | null> {
  const { data: byId } = await supabase
    .from("tenants")
    .select("id")
    .eq("id", internalTenantId)
    .maybeSingle();
  if (byId?.id) return byId.id;

  const { data: bySlug } = await supabase
    .from("tenants")
    .select("id")
    .eq("slug", internalTenantId)
    .maybeSingle();
  return bySlug?.id ?? null;
}

async function resetOrderToPaymentPending(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  orderId: string;
  currentStatusId: string;
  requestId: string;
  historyNote: string;
}): Promise<boolean> {
  const { supabase, tenantId, orderId, currentStatusId, requestId, historyNote } = params;

  const { data: pendingStatus, error: pendingStatusError } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("status_key", PAYMENT_PENDING_STATUS_KEY)
    .maybeSingle();

  if (pendingStatusError || !pendingStatus?.id) {
    console.error(
      "rtdh-webhook: customer.updated retry - payment_pending status lookup failed",
      {
        requestId,
        orderId,
        error: pendingStatusError?.message ?? "status_not_found",
      },
    );
    return false;
  }

  // Conditioned on the current (payment_failed) status_id so a racing
  // delivery that already advanced the order is left untouched.
  const { error: updateError } = await supabase
    .from("orders")
    .update({
      status_id: pendingStatus.id,
      status_changed_at: new Date().toISOString(),
      payment_failed_at: null,
      payment_retry_count: 0,
    })
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .eq("status_id", currentStatusId);

  if (updateError) {
    console.error(
      "rtdh-webhook: customer.updated retry - order status reset failed",
      {
        requestId,
        orderId,
        error: updateError.message,
      },
    );
    return false;
  }

  await supabase
    .from("order_status_history")
    .insert({
      order_id: orderId,
      status_id: pendingStatus.id,
      notes: historyNote,
    })
    .then(({ error }: { error: { message: string } | null }) => {
      if (error) {
        console.warn(
          "rtdh-webhook: customer.updated retry history insert failed",
          { requestId, orderId, error: error.message },
        );
      }
    });

  return true;
}

export async function handleCustomerUpdatedPaymentRetry(params: {
  supabase: SupabaseAdminClient;
  payload: RtdhEventPayload;
  requestId: string;
  stripeSecretKeyResolver: (
    supabase: SupabaseAdminClient,
    tenantId: string,
    requestId: string,
  ) => Promise<string | null>;
  // Used by the PaymentIntent branch (first orders): order-lifecycle owns the
  // manual-capture confirm/capture retry mechanics.
  lifecycleTrigger?: (
    orderId: string,
    tenantId: string,
    requestId: string,
  ) => Promise<boolean>;
}): Promise<CustomerUpdatedRetryResult> {
  const { supabase, payload, requestId, stripeSecretKeyResolver, lifecycleTrigger } = params;

  const internalTenantId = asNonEmptyString(payload.internal_tenant_id);
  if (!internalTenantId) {
    return { action: "skipped", reason: "missing_internal_tenant_id" };
  }

  const tenantId = await resolveTenantIdForRetry(supabase, internalTenantId);
  if (!tenantId) {
    return { action: "skipped", reason: "tenant_not_found" };
  }

  const ids = asObject(payload.ids) ?? {};
  const payment = asObject(payload.payment) ?? {};
  const orderId = asNonEmptyString(ids.patient_platform_order_id);
  const invoiceId = asNonEmptyString(payment.invoice_id) ??
    asNonEmptyString(ids.stripe_invoice_id);
  const paymentIntentId = asNonEmptyString(payment.payment_intent_id) ??
    asNonEmptyString(ids.stripe_payment_intent_id);

  if (!orderId || (!invoiceId && !paymentIntentId)) {
    return {
      action: "skipped",
      reason: "missing_order_or_payment_reference",
      orderId,
      invoiceId,
    };
  }

  // Only orders sitting in payment_failed are retry candidates; every other
  // customer.updated (email/address changes, orders that already recovered)
  // is a no-op.
  const { data: order, error: orderError } = await supabase
    .from("orders")
    .select("id, status_id, order_statuses!inner(status_key)")
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (orderError || !order) {
    return {
      action: "skipped",
      reason: orderError ? `order_lookup_failed: ${orderError.message}` : "order_not_found",
      orderId,
      invoiceId,
    };
  }

  const currentStatusKey =
    (order.order_statuses as { status_key?: string } | null)?.status_key ??
      null;
  if (currentStatusKey !== PAYMENT_FAILED_STATUS_KEY) {
    return {
      action: "skipped",
      reason: `order_not_payment_failed (${currentStatusKey})`,
      orderId,
      invoiceId,
    };
  }

  // The payment reference must belong to this order — guards against stale or
  // cross-order deliveries retrying the wrong payment.
  const invoiceLinked = invoiceId
    ? await paymentReferenceLinkedToOrder(supabase, {
      tenantId,
      orderId,
      column: "provider_invoice_id",
      value: invoiceId,
      requestId,
    })
    : false;

  if (invoiceId && invoiceLinked) {
    return await retryInvoicePayment({
      supabase,
      tenantId,
      orderId,
      currentStatusId: order.status_id,
      invoiceId,
      requestId,
      stripeSecretKeyResolver,
    });
  }

  const paymentIntentLinked = paymentIntentId
    ? await paymentReferenceLinkedToOrder(supabase, {
      tenantId,
      orderId,
      column: "provider_payment_intent_id",
      value: paymentIntentId,
      requestId,
    })
    : false;

  if (paymentIntentId && paymentIntentLinked) {
    // First-order manual-capture PaymentIntent: order-lifecycle owns the
    // retry (off-session confirm/capture with the updated default payment
    // method), and its payment_pending processing only runs for orders in
    // payment_pending — so reset first, then trigger it.
    const resetApplied = await resetOrderToPaymentPending({
      supabase,
      tenantId,
      orderId,
      currentStatusId: order.status_id,
      requestId,
      historyNote:
        "Payment retry scheduled after customer payment method update (RTDH customer.updated); re-attempting PaymentIntent via order-lifecycle",
    });

    if (!resetApplied) {
      return {
        action: "retry_failed",
        reason: "status_reset_failed",
        orderId,
        invoiceId,
      };
    }

    let lifecycleTriggered = false;
    if (lifecycleTrigger) {
      lifecycleTriggered = await lifecycleTrigger(orderId, tenantId, requestId)
        .catch((error) => {
          console.warn(
            "rtdh-webhook: customer.updated retry - lifecycle trigger failed",
            {
              requestId,
              orderId,
              error: error instanceof Error ? error.message : String(error),
            },
          );
          return false;
        });
    }

    console.info(
      "rtdh-webhook: customer.updated payment retry scheduled via order-lifecycle",
      { requestId, orderId, paymentIntentId, tenantId, lifecycleTriggered },
    );

    return { action: "payment_retry_scheduled", orderId, invoiceId };
  }

  return {
    action: "skipped",
    reason: "payment_reference_not_linked_to_order",
    orderId,
    invoiceId,
  };
}

async function paymentReferenceLinkedToOrder(
  supabase: SupabaseAdminClient,
  params: {
    tenantId: string;
    orderId: string;
    column: "provider_invoice_id" | "provider_payment_intent_id";
    value: string;
    requestId: string;
  },
): Promise<boolean> {
  const { tenantId, orderId, column, value, requestId } = params;
  const { data, error } = await supabase
    .from("order_payment_provider_transactions")
    .select("id")
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .eq(column, value)
    .limit(1)
    .maybeSingle();

  if (error) {
    console.warn(
      "rtdh-webhook: customer.updated retry - transaction lookup failed",
      { requestId, orderId, column, error: error.message },
    );
    return false;
  }

  return Boolean(data);
}

async function retryInvoicePayment(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  orderId: string;
  currentStatusId: string;
  invoiceId: string;
  requestId: string;
  stripeSecretKeyResolver: (
    supabase: SupabaseAdminClient,
    tenantId: string,
    requestId: string,
  ) => Promise<string | null>;
}): Promise<CustomerUpdatedRetryResult> {
  const {
    supabase,
    tenantId,
    orderId,
    currentStatusId,
    invoiceId,
    requestId,
    stripeSecretKeyResolver,
  } = params;

  const stripeSecretKey = await stripeSecretKeyResolver(
    supabase,
    tenantId,
    requestId,
  );
  if (!stripeSecretKey) {
    return {
      action: "skipped",
      reason: "stripe_secret_key_unavailable",
      orderId,
      invoiceId,
    };
  }

  let payResponseOk = false;
  let payErrorCode: string | null = null;
  let payErrorMessage: string | null = null;
  try {
    const response = await fetch(
      `https://api.stripe.com/v1/invoices/${encodeURIComponent(invoiceId)}/pay`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
      },
    );

    if (response.ok) {
      payResponseOk = true;
    } else {
      const errorBody = await response.json().catch(() => null) as
        | { error?: { code?: string; message?: string } }
        | null;
      payErrorCode = errorBody?.error?.code ?? `http_${response.status}`;
      payErrorMessage = errorBody?.error?.message ?? null;
    }
  } catch (error) {
    payErrorCode = "fetch_error";
    payErrorMessage = error instanceof Error ? error.message : String(error);
  }

  // A concurrent delivery (RTDH events fan out at least twice) may have paid
  // the invoice already; treat that as success so both deliveries converge on
  // the same order state.
  const invoiceAlreadyPaid = payErrorCode === "invoice_already_paid" ||
    (payErrorMessage ?? "").toLowerCase().includes("already paid");

  if (!payResponseOk && !invoiceAlreadyPaid) {
    console.warn(
      "rtdh-webhook: customer.updated payment retry failed; order stays payment_failed",
      {
        requestId,
        orderId,
        invoiceId,
        tenantId,
        errorCode: payErrorCode,
        errorMessage: payErrorMessage,
      },
    );
    return { action: "retry_failed", reason: payErrorCode ?? undefined, orderId, invoiceId };
  }

  // Payment confirmed. Reset the order to payment_pending so the incoming
  // RTDH payment_collected event passes the display_order regression guard
  // and advances the order through the normal lifecycle path.
  const resetApplied = await resetOrderToPaymentPending({
    supabase,
    tenantId,
    orderId,
    currentStatusId,
    requestId,
    historyNote:
      `Payment retried after customer payment method update (RTDH customer.updated); invoice ${invoiceId} paid`,
  });

  console.info(
    "rtdh-webhook: customer.updated payment retry succeeded; order reset to payment_pending pending payment_collected",
    {
      requestId,
      orderId,
      invoiceId,
      tenantId,
      invoiceAlreadyPaid,
      resetApplied,
    },
  );

  return {
    action: "payment_retried",
    ...(resetApplied ? {} : { reason: "status_reset_failed" }),
    orderId,
    invoiceId,
  };
}
