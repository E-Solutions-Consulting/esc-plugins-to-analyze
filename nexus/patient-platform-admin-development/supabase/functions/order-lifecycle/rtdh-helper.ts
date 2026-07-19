import { postSignedRtdhJson } from "../_shared/rtdh-signature.ts";
import { resolveRtdhConfig } from "../_shared/rtdh-config.ts";

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;
type RtdhCancellationStage =
  | "before_provider_creation"
  | "after_provider_creation";

const RTDH_CREATE_ORDER_HISTORY_NOTE = "RTDH create-order webhook dispatched";
const RTDH_ORDER_CANCELLED_HISTORY_NOTE =
  "RTDH order cancelled webhook dispatched";
const RTDH_DISPATCH_TIMEOUT_MS = 8000;
const PATIENT_PLATFORM_WEBHOOK_RECEIVER_PATH =
  "patient-platform-webhook-receiver";
const RTDH_STATUS_UPDATE_HISTORY_NOTE_PREFIX =
  "RTDH order status update webhook dispatched";
const RTDH_ORDER_STATUS_UPDATE_KEYS = new Set([
  "shipping_details_required",
  "provider_order_creation_pending",
  "patient_questionnaire_pending",
  "medical_questionnaire_pending",
  "payment_pending",
  "order_approved",
  "order_cancelled",
  "order_pending_cancellation",
  "order_cancellation_processing",
  "provider_order_creation_error",
  "order_cancellation_error",
]);

function buildRtdhReceiverUrl(
  baseUrl: string,
  path: string,
): string {
  const base = baseUrl.replace(/\/+$/, "");
  const receiverPath = path.replace(/^\/+/, "");
  return new URL(`${base}/${receiverPath}`).toString();
}

export function isRtdhOrderStatusUpdateKey(statusKey: string): boolean {
  return RTDH_ORDER_STATUS_UPDATE_KEYS.has(statusKey);
}

type NotifyRtdhOrderStatusUpdatedParams = {
  supabase: SupabaseClient;
  requestId: string;
  tenantId: string;
  orderId: string;
  statusId: string | null;
  statusKey: string;
  previousStatusKey?: string | null;
  source?: string;
  // The Stripe subscription id when the caller already holds it. The
  // payment-collected dispatch races with ensureSubscriptionForCapturedOrder
  // (separate invocations), so the transaction-row read below can miss a
  // subscription written milliseconds later; callers that just created the
  // subscription pass it explicitly.
  subscriptionIdOverride?: string | null;
};

export function notifyRtdhOrderStatusUpdatedAsync(
  params: NotifyRtdhOrderStatusUpdatedParams,
): void {
  notifyRtdhOrderStatusUpdated(params).catch((error) => {
    console.warn("Async RTDH order status update dispatch failed", {
      requestId: params.requestId,
      tenantId: params.tenantId,
      orderId: params.orderId,
      statusKey: params.statusKey,
      source: params.source,
      error: error instanceof Error ? error.message : String(error),
    });
  });
}

export async function notifyRtdhOrderStatusUpdated(
  params: NotifyRtdhOrderStatusUpdatedParams,
): Promise<void> {
  const {
    supabase,
    requestId,
    tenantId,
    orderId,
    statusId,
    statusKey,
    previousStatusKey = null,
    source = "order-lifecycle",
    subscriptionIdOverride = null,
  } = params;

  if (!isRtdhOrderStatusUpdateKey(statusKey)) {
    return;
  }

  const markerNote = `${RTDH_STATUS_UPDATE_HISTORY_NOTE_PREFIX}: ${statusKey}`;
  if (statusId) {
    const { data: existingDispatchHistory, error: existingHistoryError } =
      await supabase
        .from("order_status_history")
        .select("id")
        .eq("order_id", orderId)
        .eq("status_id", statusId)
        .eq("notes", markerNote)
        .limit(1)
        .maybeSingle();

    if (existingHistoryError) {
      console.warn(
        "RTDH order status update continuing without idempotency check",
        {
          requestId,
          tenantId,
          orderId,
          statusKey,
          error: existingHistoryError.message,
        },
      );
    } else if (existingDispatchHistory?.id) {
      console.info("RTDH order status update already dispatched; skipping", {
        requestId,
        tenantId,
        orderId,
        statusKey,
        historyId: existingDispatchHistory.id,
      });
      return;
    }
  }

  const { data: tenantRow, error: tenantFetchError } = await supabase
    .from("tenants")
    .select("slug")
    .eq("id", tenantId)
    .maybeSingle();

  if (tenantFetchError || !tenantRow?.slug) {
    console.warn(
      "RTDH order status update skipped: unable to resolve tenant slug",
      {
        requestId,
        tenantId,
        orderId,
        statusKey,
        error: tenantFetchError?.message || "tenant_not_found",
      },
    );
    return;
  }

  const { data: rtdhSettingRow, error: rtdhSettingError } = await supabase
    .from("platform_settings")
    .select("value")
    .eq("key", "rtdh_config")
    .maybeSingle();

  if (rtdhSettingError) {
    console.warn("Failed to load RTDH platform settings", {
      requestId,
      tenantId,
      orderId,
      statusKey,
      error: rtdhSettingError.message,
    });
    return;
  }

  const rtdhConfig = await resolveRtdhConfig(rtdhSettingRow?.value);
  const rtdhApi = rtdhConfig.base_url || rtdhConfig.api_url;
  const rtdhConsumerSecret = rtdhConfig.patient_platform_webhook_secret ||
    rtdhConfig.consumer_secret;

  if (!rtdhApi) {
    console.warn(
      "RTDH order status update skipped: base_url is not configured",
      {
        requestId,
        tenantId,
        orderId,
        statusKey,
      },
    );
    return;
  }

  if (!rtdhConsumerSecret) {
    console.warn(
      "RTDH order status update skipped: patient_platform_webhook_secret is not configured",
      {
        requestId,
        tenantId,
        orderId,
        statusKey,
      },
    );
    return;
  }

  const { data: paymentTransaction, error: paymentTransactionError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select(
        "provider_checkout_session_id, provider_payment_intent_id, provider_subscription_id",
      )
      .eq("tenant_id", tenantId)
      .eq("order_id", orderId)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

  if (paymentTransactionError) {
    console.warn(
      "RTDH order status update continuing without Stripe references",
      {
        requestId,
        tenantId,
        orderId,
        statusKey,
        error: paymentTransactionError.message,
      },
    );
  }

  const url = buildRtdhReceiverUrl(
    rtdhApi,
    `${PATIENT_PLATFORM_WEBHOOK_RECEIVER_PATH}/order_updated`,
  );
  const payload = {
    event_id: crypto.randomUUID(),
    event_type: "order_status_updated",
    occurred_at: new Date().toISOString(),
    source: "patient_platform",
    tenant: tenantRow.slug,
    internal_tenant_id: tenantId,
    suppress_patient_platform_webhook: true,
    payload: {
      patient_platform_order_id: orderId,
      status: statusKey,
      order_status_key: statusKey,
      previous_status: previousStatusKey,
      update_source: source,
      checkout_session_id: paymentTransaction?.provider_checkout_session_id ??
        null,
      payment_intent_id: paymentTransaction?.provider_payment_intent_id ?? null,
      // Stripe subscription id exists once ensureSubscriptionForCapturedOrder has
      // run (payment capture); RTDH links it to the master object so renewal
      // invoices (billing_reason=subscription_cycle) can resolve the order.
      subscription_id: subscriptionIdOverride ??
        paymentTransaction?.provider_subscription_id ?? null,
    },
  };

  try {
    const response = await postSignedRtdhJson({
      url,
      requestId,
      requestSource: `${source}:order-status-updated`,
      webhookSecret: rtdhConsumerSecret,
      payload,
      timeoutMs: RTDH_DISPATCH_TIMEOUT_MS,
    });

    if (!response.ok) {
      console.warn("RTDH order status update dispatch failed", {
        requestId,
        tenantId,
        orderId,
        statusKey,
        url,
        status: response.status,
        response: await response.text(),
      });
      return;
    }

    console.info("RTDH order status update dispatch succeeded", {
      requestId,
      tenantId,
      orderId,
      statusKey,
      url,
    });

    if (statusId) {
      const { error: markerInsertError } = await supabase
        .from("order_status_history")
        .insert({
          order_id: orderId,
          status_id: statusId,
          notes: markerNote,
        });

      if (markerInsertError) {
        console.warn(
          "RTDH order status update succeeded but marker insert failed",
          {
            requestId,
            tenantId,
            orderId,
            statusKey,
            error: markerInsertError.message,
          },
        );
      }
    }
  } catch (error) {
    console.warn("RTDH order status update dispatch errored", {
      requestId,
      tenantId,
      orderId,
      statusKey,
      url,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

export async function notifyRtdhOrderCancelled(params: {
  supabase: SupabaseClient;
  requestId: string;
  tenantId: string;
  orderId: string;
  statusId?: string | null;
  previousStatusKey?: string | null;
  cancellationStage?: RtdhCancellationStage;
  cancellationReason?: string;
  source?: string;
}): Promise<void> {
  const {
    supabase,
    requestId,
    tenantId,
    orderId,
    statusId = null,
    previousStatusKey = null,
    cancellationStage = "before_provider_creation",
    cancellationReason = "patient_requested",
    source = "order-lifecycle",
  } = params;

  if (statusId) {
    const { data: existingDispatchHistory, error: existingHistoryError } =
      await supabase
        .from("order_status_history")
        .select("id")
        .eq("order_id", orderId)
        .eq("status_id", statusId)
        .eq("notes", RTDH_ORDER_CANCELLED_HISTORY_NOTE)
        .limit(1)
        .maybeSingle();

    if (existingHistoryError) {
      console.warn(
        "RTDH order cancelled continuing without idempotency check",
        {
          requestId,
          tenantId,
          orderId,
          error: existingHistoryError.message,
        },
      );
    } else if (existingDispatchHistory?.id) {
      console.info("RTDH order cancelled already dispatched; skipping", {
        requestId,
        tenantId,
        orderId,
        historyId: existingDispatchHistory.id,
      });
      return;
    }
  }

  if (cancellationStage === "before_provider_creation") {
    const { data: providerLinks, error: providerLinksError } = await supabase
      .from("order_provider_platform_links")
      .select("id")
      .eq("order_id", orderId)
      .eq("tenant_id", tenantId)
      .not("provider_order_id", "is", null)
      .limit(1);

    if (providerLinksError) {
      console.warn(
        "RTDH order cancelled skipped: failed to check provider links",
        {
          requestId,
          tenantId,
          orderId,
          error: providerLinksError.message,
        },
      );
      return;
    }

    if ((providerLinks || []).length > 0) {
      console.info(
        "RTDH before-provider-creation order cancelled skipped: provider order already exists",
        { requestId, tenantId, orderId },
      );
      return;
    }
  }

  const { data: tenantRow, error: tenantFetchError } = await supabase
    .from("tenants")
    .select("slug")
    .eq("id", tenantId)
    .maybeSingle();

  if (tenantFetchError || !tenantRow?.slug) {
    console.warn(
      "RTDH order cancelled skipped: unable to resolve tenant slug",
      {
        requestId,
        tenantId,
        orderId,
        error: tenantFetchError?.message || "tenant_not_found",
      },
    );
    return;
  }

  const { data: rtdhSettingRow, error: rtdhSettingError } = await supabase
    .from("platform_settings")
    .select("value")
    .eq("key", "rtdh_config")
    .maybeSingle();

  if (rtdhSettingError) {
    console.warn("Failed to load RTDH platform settings", {
      requestId,
      tenantId,
      orderId,
      error: rtdhSettingError.message,
    });
    return;
  }

  const rtdhConfig = await resolveRtdhConfig(rtdhSettingRow?.value);
  const rtdhApi = rtdhConfig.base_url || rtdhConfig.api_url;
  const rtdhConsumerSecret = rtdhConfig.patient_platform_webhook_secret ||
    rtdhConfig.consumer_secret;

  if (!rtdhApi) {
    console.warn("RTDH order cancelled skipped: base_url is not configured", {
      requestId,
      tenantId,
      orderId,
    });
    return;
  }

  if (!rtdhConsumerSecret) {
    console.warn(
      "RTDH order cancelled skipped: patient_platform_webhook_secret is not configured",
      {
        requestId,
        tenantId,
        orderId,
      },
    );
    return;
  }

  const { data: paymentTransaction, error: paymentTransactionError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select(
        "provider_checkout_session_id, provider_payment_intent_id, provider_subscription_id",
      )
      .eq("tenant_id", tenantId)
      .eq("order_id", orderId)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

  if (paymentTransactionError) {
    console.warn(
      "RTDH order cancelled continuing without Stripe references",
      {
        requestId,
        tenantId,
        orderId,
        error: paymentTransactionError.message,
      },
    );
  }

  const url = buildRtdhReceiverUrl(
    rtdhApi,
    `${PATIENT_PLATFORM_WEBHOOK_RECEIVER_PATH}/order_updated`,
  );
  const payload = {
    event_id: crypto.randomUUID(),
    event_type: "order_status_updated",
    occurred_at: new Date().toISOString(),
    source: "patient_platform",
    tenant: tenantRow.slug,
    internal_tenant_id: tenantId,
    suppress_patient_platform_webhook: true,
    payload: {
      patient_platform_order_id: orderId,
      status: "order_cancelled",
      order_status_key: "order_cancelled",
      previous_status: previousStatusKey,
      update_source: source,
      cancellation_stage: cancellationStage,
      cancellation_reason: cancellationReason,
      checkout_session_id: paymentTransaction?.provider_checkout_session_id ??
        null,
      payment_intent_id: paymentTransaction?.provider_payment_intent_id ?? null,
      subscription_id: paymentTransaction?.provider_subscription_id ?? null,
    },
  };

  try {
    const response = await postSignedRtdhJson({
      url,
      requestId,
      requestSource: `${source}:order-cancelled`,
      webhookSecret: rtdhConsumerSecret,
      payload,
      timeoutMs: RTDH_DISPATCH_TIMEOUT_MS,
    });

    if (!response.ok) {
      console.warn("RTDH order cancelled dispatch failed", {
        requestId,
        tenantId,
        orderId,
        url,
        status: response.status,
        response: await response.text(),
      });
      return;
    }

    console.info("RTDH order cancelled dispatch succeeded", {
      requestId,
      tenantId,
      orderId,
      url,
    });

    if (statusId) {
      const { error: markerInsertError } = await supabase
        .from("order_status_history")
        .insert({
          order_id: orderId,
          status_id: statusId,
          notes: RTDH_ORDER_CANCELLED_HISTORY_NOTE,
        });

      if (markerInsertError) {
        console.warn(
          "RTDH order cancelled succeeded but marker insert failed",
          {
            requestId,
            tenantId,
            orderId,
            error: markerInsertError.message,
          },
        );
      }
    }
  } catch (error) {
    console.warn("RTDH order cancelled dispatch errored", {
      requestId,
      tenantId,
      orderId,
      url,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

export async function triggerRtdhProviderPlatformNewOrder(params: {
  supabase: SupabaseClient;
  requestId: string;
  tenantId: string;
  orderId: string;
  orderStatusHistoryId: string | null;
  patientId: string;
  providerPatientId: string;
  providerPlatformKey: "telegramd" | "md_integrations";
  providerPlatformOrderId: string | null;
}): Promise<void> {
  const {
    supabase,
    requestId,
    tenantId,
    orderId,
    orderStatusHistoryId,
    patientId,
    providerPatientId,
    providerPlatformKey,
    providerPlatformOrderId,
  } = params;

  if (!providerPlatformOrderId) {
    console.warn(
      "RTDH provider-platform new-order dispatch skipped: provider order id is missing",
      {
        requestId,
        tenantId,
        orderId,
        patientId,
        providerPlatformKey,
      },
    );
    return;
  }

  if (providerPatientId.trim().length === 0) {
    console.warn(
      "RTDH provider-platform new-order dispatch skipped: provider patient id is missing",
      {
        requestId,
        tenantId,
        orderId,
        patientId,
        providerPlatformKey,
        providerPlatformOrderId,
      },
    );
    return;
  }

  const { data: tenantRow, error: tenantFetchError } = await supabase
    .from("tenants")
    .select("slug")
    .eq("id", tenantId)
    .maybeSingle();

  if (tenantFetchError || !tenantRow?.slug) {
    console.warn(
      "RTDH provider-platform new-order dispatch skipped: unable to resolve tenant slug",
      {
        requestId,
        tenantId,
        orderId,
        error: tenantFetchError?.message || "tenant_not_found",
      },
    );
    return;
  }

  const { data: rtdhSettingRow, error: rtdhSettingError } = await supabase
    .from("platform_settings")
    .select("value")
    .eq("key", "rtdh_config")
    .maybeSingle();

  if (rtdhSettingError) {
    console.warn("Failed to load RTDH platform settings", {
      requestId,
      tenantId,
      orderId,
      patientId,
      providerPlatformKey,
      error: rtdhSettingError.message,
    });
    return;
  }

  const rtdhConfig = await resolveRtdhConfig(rtdhSettingRow?.value);
  const rtdhApi = rtdhConfig.base_url || rtdhConfig.api_url;
  const rtdhConsumerSecret = rtdhConfig.patient_platform_webhook_secret ||
    rtdhConfig.consumer_secret;

  if (!rtdhApi) {
    console.warn(
      "RTDH provider-platform new-order dispatch skipped: base_url is not configured",
      {
        requestId,
        tenantId,
        orderId,
        patientId,
        providerPlatformKey,
        providerPlatformOrderId,
      },
    );
    return;
  }

  if (!rtdhConsumerSecret) {
    console.warn(
      "RTDH provider-platform new-order dispatch skipped: patient_platform_webhook_secret is not configured",
      {
        requestId,
        tenantId,
        orderId,
        patientId,
        providerPlatformKey,
        providerPlatformOrderId,
      },
    );
    return;
  }

  const url = buildRtdhReceiverUrl(
    rtdhApi,
    `${PATIENT_PLATFORM_WEBHOOK_RECEIVER_PATH}/provider-platform/new-order`,
  );
  const payload = {
    source: "patient_platform",
    event_id: orderStatusHistoryId,
    tenant: tenantRow.slug,
    internal_tenant_id: tenantId,
    occurred_at: new Date().toISOString(),
    payload: {
      patient_platform_order_id: orderId,
      patient_id: patientId,
      provider_patient_id: providerPatientId,
      provider_name: providerPlatformKey,
      provider_order_id: providerPlatformOrderId,
      internal_tenant_id: tenantId,
    },
  };

  try {
    const response = await postSignedRtdhJson({
      url,
      requestId,
      requestSource: "order-lifecycle:provider-platform-new-order",
      webhookSecret: rtdhConsumerSecret,
      payload,
    });

    if (!response.ok) {
      console.warn("RTDH provider-platform new-order dispatch failed", {
        requestId,
        tenantId,
        orderId,
        patientId,
        providerPlatformKey,
        providerPlatformOrderId,
        url,
        status: response.status,
        response: await response.text(),
      });
      return;
    }

    console.info("RTDH provider-platform new-order dispatch succeeded", {
      requestId,
      tenantId,
      orderId,
      patientId,
      providerPlatformKey,
      providerPlatformOrderId,
      url,
    });
  } catch (error) {
    console.warn("RTDH provider-platform new-order dispatch errored", {
      requestId,
      tenantId,
      orderId,
      patientId,
      providerPlatformKey,
      providerPlatformOrderId,
      url,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

export async function triggerRtdhCreateOrder(params: {
  supabase: SupabaseClient;
  requestId: string;
  tenantId: string;
  patientId: string;
  orderId: string;
  orderStatusId: string | null;
}): Promise<void> {
  const { supabase, requestId, tenantId, patientId, orderId, orderStatusId } =
    params;

  if (orderStatusId) {
    const { data: existingDispatchHistory, error: existingHistoryError } =
      await supabase
        .from("order_status_history")
        .select("id")
        .eq("order_id", orderId)
        .eq("status_id", orderStatusId)
        .eq("notes", RTDH_CREATE_ORDER_HISTORY_NOTE)
        .limit(1)
        .maybeSingle();

    if (existingHistoryError) {
      console.warn(
        "RTDH create-order dispatch continuing without idempotency check",
        {
          requestId,
          tenantId,
          orderId,
          orderStatusId,
          error: existingHistoryError.message,
        },
      );
    } else if (existingDispatchHistory?.id) {
      console.info(
        "RTDH create-order dispatch previously recorded; dispatching again",
        {
          requestId,
          tenantId,
          orderId,
          orderStatusId,
          historyId: existingDispatchHistory.id,
        },
      );
    }
  }

  const { data: tenantRow, error: tenantFetchError } = await supabase
    .from("tenants")
    .select("slug")
    .eq("id", tenantId)
    .maybeSingle();

  if (tenantFetchError || !tenantRow?.slug) {
    console.warn(
      "RTDH create-order dispatch skipped: unable to resolve tenant slug",
      {
        requestId,
        tenantId,
        orderId,
        error: tenantFetchError?.message || "tenant_not_found",
      },
    );
    return;
  }

  let orderStatusHistoryId: string | null = null;
  if (orderStatusId) {
    const { data: orderStatusHistory, error: historyError } = await supabase
      .from("order_status_history")
      .select("id")
      .eq("order_id", orderId)
      .eq("status_id", orderStatusId)
      .order("created_at", { ascending: true })
      .limit(1)
      .maybeSingle();

    if (historyError) {
      console.warn("RTDH create-order dispatch continuing without event_id", {
        requestId,
        tenantId,
        orderId,
        orderStatusId,
        error: historyError.message,
      });
    } else {
      orderStatusHistoryId = orderStatusHistory?.id ?? null;
    }
  }

  const { data: checkoutTransaction, error: checkoutTransactionError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select("provider_checkout_session_id")
      .eq("tenant_id", tenantId)
      .eq("order_id", orderId)
      .not("provider_checkout_session_id", "is", null)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

  if (checkoutTransactionError) {
    console.warn(
      "RTDH create-order dispatch continuing without checkout session id",
      {
        requestId,
        tenantId,
        orderId,
        error: checkoutTransactionError.message,
      },
    );
  }

  const checkoutSessionId =
    typeof checkoutTransaction?.provider_checkout_session_id === "string" &&
      checkoutTransaction.provider_checkout_session_id.trim().length > 0
      ? checkoutTransaction.provider_checkout_session_id.trim()
      : null;

  // Embedded Stripe Elements checkout (PP-566) has no Checkout Session — the
  // order is linked to a PaymentIntent instead. Send payment_intent_id so RTDH
  // can accept and resolve create-order without a checkout_session_id. The
  // hosted-checkout path keeps sending checkout_session_id as before; both are
  // optional on the RTDH side as long as one identifier (or an existing master
  // object) is present.
  const {
    data: paymentIntentTransaction,
    error: paymentIntentTransactionError,
  } = await supabase
    .from("order_payment_provider_transactions")
    .select("provider_payment_intent_id")
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .not("provider_payment_intent_id", "is", null)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (paymentIntentTransactionError) {
    console.warn(
      "RTDH create-order dispatch continuing without payment intent id",
      {
        requestId,
        tenantId,
        orderId,
        error: paymentIntentTransactionError.message,
      },
    );
  }

  const paymentIntentId =
    typeof paymentIntentTransaction?.provider_payment_intent_id === "string" &&
      paymentIntentTransaction.provider_payment_intent_id.trim().length > 0
      ? paymentIntentTransaction.provider_payment_intent_id.trim()
      : null;

  // Renewal orders are created at invoice.created time — before any PaymentIntent
  // or Checkout Session exists. The renewal payment transaction stores the Stripe
  // invoice ID so RTDH can match the renewal master object keyed on stripe_invoice_id.
  const {
    data: invoiceTransaction,
    error: invoiceTransactionError,
  } = await supabase
    .from("order_payment_provider_transactions")
    .select("provider_invoice_id")
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .not("provider_invoice_id", "is", null)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (invoiceTransactionError) {
    console.warn(
      "RTDH create-order dispatch continuing without invoice id",
      {
        requestId,
        tenantId,
        orderId,
        error: invoiceTransactionError.message,
      },
    );
  }

  const invoiceId =
    typeof invoiceTransaction?.provider_invoice_id === "string" &&
      invoiceTransaction.provider_invoice_id.trim().length > 0
      ? invoiceTransaction.provider_invoice_id.trim()
      : null;

  // Renewal orders already carry the Stripe subscription id in their payment
  // transaction at order_created (first orders don't — the subscription is only
  // created after capture). Forwarding it lets RTDH link the renewal order's
  // master object to the subscription so the NEXT cycle's invoice resolves too.
  const {
    data: subscriptionTransaction,
    error: subscriptionTransactionError,
  } = await supabase
    .from("order_payment_provider_transactions")
    .select("provider_subscription_id")
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .not("provider_subscription_id", "is", null)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (subscriptionTransactionError) {
    console.warn(
      "RTDH create-order dispatch continuing without subscription id",
      {
        requestId,
        tenantId,
        orderId,
        error: subscriptionTransactionError.message,
      },
    );
  }

  const subscriptionId =
    typeof subscriptionTransaction?.provider_subscription_id === "string" &&
      subscriptionTransaction.provider_subscription_id.trim().length > 0
      ? subscriptionTransaction.provider_subscription_id.trim()
      : null;

  // Zero-amount orders (e.g. a 100%-off coupon) have NO Stripe identifier at
  // all: Stripe rejects a $0 PaymentIntent, so the checkout route skips PI
  // creation, and there is no Checkout Session in the embedded flow. Without a
  // checkout_session_id, payment_intent_id, or invoice_id, RTDH create-order
  // would reject the dispatch. Detect the legitimate no-payment case from the
  // order total and signal it explicitly so RTDH can accept create-order keyed
  // on patient_platform_order_id alone.
  let noPaymentRequired = false;
  if (!checkoutSessionId && !paymentIntentId && !invoiceId) {
    const { data: orderRow, error: orderRowError } = await supabase
      .from("orders")
      .select("total_cents")
      .eq("id", orderId)
      .eq("tenant_id", tenantId)
      .maybeSingle();

    if (orderRowError) {
      console.warn(
        "RTDH create-order dispatch could not read order total for no-payment detection",
        { requestId, tenantId, orderId, error: orderRowError.message },
      );
    } else if (typeof orderRow?.total_cents === "number") {
      noPaymentRequired = orderRow.total_cents <= 0;
    }
  }

  // PP-566 parity: the hosted Checkout Session used to seed the customer's email
  // into the RTDH master object (via checkout.session.completed -> customer_details
  // .email), so every later event — including the provider's case_approved — carried
  // it and passed RTDH's customer.email validation. The embedded PaymentIntent flow
  // never creates a Checkout Session, so the email was never seeded and provider
  // approvals were rejected ("customer.email is required"). Send the patient email on
  // create-order so RTDH can persist it on the master object at order.linked.
  const { data: createOrderPatientRow, error: createOrderPatientError } =
    await supabase
      .from("orders")
      .select("patients!inner ( email, first_name, last_name, phone )")
      .eq("id", orderId)
      .eq("tenant_id", tenantId)
      .maybeSingle();

  if (createOrderPatientError) {
    console.warn(
      "RTDH create-order dispatch continuing without customer email",
      { requestId, tenantId, orderId, error: createOrderPatientError.message },
    );
  }

  const createOrderPatient = createOrderPatientRow?.patients
    ? (Array.isArray(createOrderPatientRow.patients)
      ? createOrderPatientRow.patients[0]
      : createOrderPatientRow.patients)
    : null;

  const trimmedOrNull = (value: unknown): string | null =>
    typeof value === "string" && value.trim().length > 0 ? value.trim() : null;

  const customerEmail = trimmedOrNull(createOrderPatient?.email);
  const customerFirstName = trimmedOrNull(createOrderPatient?.first_name);
  const customerLastName = trimmedOrNull(createOrderPatient?.last_name);
  const customerPhone = trimmedOrNull(createOrderPatient?.phone);

  if (!customerEmail) {
    console.warn(
      "RTDH create-order dispatch: patient has no email; provider approval may be rejected by RTDH",
      { requestId, tenantId, orderId },
    );
  }

  const { data: rtdhSettingRow, error: rtdhSettingError } = await supabase
    .from("platform_settings")
    .select("value")
    .eq("key", "rtdh_config")
    .maybeSingle();

  if (rtdhSettingError) {
    console.warn("Failed to load RTDH platform settings", {
      requestId,
      tenantId,
      orderId,
      error: rtdhSettingError.message,
    });
    return;
  }

  const rtdhConfig = await resolveRtdhConfig(rtdhSettingRow?.value);
  const rtdhApi = rtdhConfig.base_url || rtdhConfig.api_url;
  const rtdhConsumerSecret = rtdhConfig.patient_platform_webhook_secret ||
    rtdhConfig.consumer_secret;

  if (!rtdhApi) {
    console.warn(
      "RTDH create-order dispatch skipped: base_url is not configured",
      {
        requestId,
        tenantId,
        orderId,
        checkoutSessionId,
      },
    );
    return;
  }

  if (!rtdhConsumerSecret) {
    console.warn(
      "RTDH create-order dispatch skipped: patient_platform_webhook_secret is not configured",
      {
        requestId,
        tenantId,
        orderId,
        checkoutSessionId,
      },
    );
    return;
  }

  const url = buildRtdhReceiverUrl(
    rtdhApi,
    `${PATIENT_PLATFORM_WEBHOOK_RECEIVER_PATH}/create-order`,
  );
  const payload = {
    source: "patient_platform",
    event_id: orderStatusHistoryId,
    tenant: tenantRow.slug,
    internal_tenant_id: tenantId,
    occurred_at: new Date().toISOString(),
    payload: {
      checkout_session_id: checkoutSessionId,
      payment_intent_id: paymentIntentId,
      ...(invoiceId ? { invoice_id: invoiceId } : {}),
      ...(subscriptionId ? { subscription_id: subscriptionId } : {}),
      patient_platform_order_id: orderId,
      patient_id: patientId,
      internal_tenant_id: tenantId,
      ...(customerEmail ? { customer_email: customerEmail } : {}),
      ...(customerFirstName ? { customer_first_name: customerFirstName } : {}),
      ...(customerLastName ? { customer_last_name: customerLastName } : {}),
      ...(customerPhone ? { customer_phone: customerPhone } : {}),
      ...(noPaymentRequired ? { no_payment_required: true } : {}),
    },
  };

  try {
    const response = await postSignedRtdhJson({
      url,
      requestId,
      requestSource: "order-lifecycle:create-order",
      webhookSecret: rtdhConsumerSecret,
      payload,
    });

    if (!response.ok) {
      console.warn("RTDH create-order dispatch failed", {
        requestId,
        tenantId,
        orderId,
        checkoutSessionId,
        paymentIntentId,
        url,
        status: response.status,
        response: await response.text(),
      });
      return;
    }

    console.info("RTDH create-order dispatch succeeded", {
      requestId,
      tenantId,
      orderId,
      checkoutSessionId,
      paymentIntentId,
      url,
    });

    if (orderStatusId) {
      const { error: markerInsertError } = await supabase
        .from("order_status_history")
        .insert({
          order_id: orderId,
          status_id: orderStatusId,
          notes: RTDH_CREATE_ORDER_HISTORY_NOTE,
        });

      if (markerInsertError) {
        console.warn(
          "RTDH create-order dispatch succeeded but marker insert failed",
          {
            requestId,
            tenantId,
            orderId,
            orderStatusId,
            error: markerInsertError.message,
          },
        );
      }
    }
  } catch (error) {
    console.warn("RTDH create-order dispatch errored", {
      requestId,
      tenantId,
      orderId,
      checkoutSessionId,
      url,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}
