import { createClient } from "jsr:@supabase/supabase-js@2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { cleanupUnpaidOrders } from "./cleanup-unpaid-helper.ts";
import { dateTime } from "../_shared/dayjs.ts";
import { resolveAndPersistProviderPlatformSelection } from "../_shared/provider-platform-selection.ts";
import {
  getTrackingDetailsFromEasyPost,
  resolveTenantEasyPostShippingIntegration,
} from "../_shared/shipping.ts";
import {
  analyzePendingOrderCancellation,
  cancelLinkedPlanForProviderRejectedOrder,
  finalizeDirectOrderCancellation,
  type OrderForPendingCancellation,
  processOrderCancellationProcessing,
} from "./cancel-helper.ts";
import {
  hasCompleteBillingAddress,
  hasCompleteShippingAddress,
  isFieldFilled,
} from "./helpers.ts";
import {
  createMdiOrderForLifecycle,
  requestMdiCaseProcessingForLifecycle,
} from "./mdi-helper.ts";
import {
  isRtdhOrderStatusUpdateKey,
  notifyRtdhOrderCancelled,
  notifyRtdhOrderStatusUpdated,
  triggerRtdhCreateOrder,
} from "./rtdh-helper.ts";
import {
  ensureSubscriptionForCapturedOrder,
  maybeCaptureStripePaymentForPaymentPendingOrder,
  type PaymentReleaseResult,
  releaseStripePaymentForRejectedOrder,
  syncLifecycleDatesForPaymentCollectedOrder,
} from "./stripe-helper.ts";
import {
  createTelegraOrderForLifecycle,
  leaveTelegraWaitingRoomForLifecycle,
  sendTelegraOrderToPharmacyForLifecycle,
} from "./telegra-helper.ts";

interface OrderStatus {
  id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  display_order: number;
  next_status_id: string | null;
  failure_status_id: string | null;
  next_step_owner: string;
}

interface Order {
  id: string;
  order_number: string;
  tenant_id: string;
  patient_id: string;
  subscription_id: string | null;
  status_id: string | null;
  status_changed_at: string | null;
  created_at: string;
  product_id: string | null;
  total_cents: number | null;
  internal_notes: string | null;
  cancellation_reason: string | null;
  cancellation_operation_key: string | null;
  cancellation_operation_started_at: string | null;
  cancellation_operation_completed_at: string | null;
  cancelled_at: string | null;
  renewal_at: string | null;
  paid_at: string | null;
  payment_failed_at: string | null;
  tracking_number: string | null;
  tracking_url: string | null;
  delivered_at: string | null;
  provider_platform_integration_key: string | null;
  // Shipping address fields
  shipping_first_name: string | null;
  shipping_last_name: string | null;
  shipping_address_line1: string | null;
  shipping_address_line2: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string | null;
  // Billing address fields
  billing_first_name: string | null;
  billing_last_name: string | null;
  billing_address_line1: string | null;
  billing_address_line2: string | null;
  billing_city: string | null;
  billing_state: string | null;
  billing_postal_code: string | null;
  billing_country: string | null;
  order_statuses: OrderStatus | null;
}

interface ProcessingResult {
  orderId: string;
  orderNumber: string;
  previousStatus: string | null;
  newStatus: string | null;
  action: "advanced" | "no_change" | "error";
  message: string;
  transitions?: Array<{ from: string; to: string; reason: string }>;
}

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;

function getPaymentPendingSkipReason(
  order: Pick<Order, "paid_at" | "total_cents">,
): string | null {
  if (order.paid_at) {
    return `order already marked as paid at ${order.paid_at}`;
  }

  if ((order.total_cents ?? null) === 0) {
    return "order total is 0 (gift / fully discounted)";
  }

  return null;
}

function normalizeIntegrationKey(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

function isTelegraOrder(
  order: Pick<Order, "provider_platform_integration_key">,
): boolean {
  return (
    normalizeIntegrationKey(order.provider_platform_integration_key) ===
      "telegramd"
  );
}

function isMdiOrder(
  order: Pick<Order, "provider_platform_integration_key">,
): boolean {
  return (
    normalizeIntegrationKey(order.provider_platform_integration_key) ===
      "md_integrations"
  );
}

function isLifecycleHandledTerminalStatus(
  status: Pick<OrderStatus, "status_key"> | null | undefined,
): boolean {
  return status?.status_key === "provider_order_creation_error" ||
    status?.status_key === "provider_rejected";
}

function buildProviderRejectedPaymentHistoryNote(
  result: PaymentReleaseResult,
): string {
  const paymentIntentSuffix = result.paymentIntentId
    ? ` Payment intent: ${result.paymentIntentId}.`
    : "";
  const chargeSuffix = result.chargeId ? ` Charge: ${result.chargeId}.` : "";
  const statusSuffix = result.stripeStatus
    ? ` Stripe status: ${result.stripeStatus}.`
    : "";

  if (result.action === "payment_intent_cancelled") {
    return `Stripe payment intent canceled due to provider rejection.${paymentIntentSuffix}${statusSuffix}`;
  }

  if (result.action === "payment_intent_terminal") {
    return `Stripe payment intent cancellation skipped after provider rejection because the payment intent was already terminal.${paymentIntentSuffix}${statusSuffix}`;
  }

  if (result.action === "charge_refunded") {
    return `Stripe charge refunded due to provider rejection.${paymentIntentSuffix}${chargeSuffix}`;
  }

  if (result.released) {
    return `Stripe payment released due to provider rejection: ${result.message}.${paymentIntentSuffix}${chargeSuffix}${statusSuffix}`;
  }

  return `Stripe payment release attempted after provider rejection but did not succeed: ${result.message}.${paymentIntentSuffix}${chargeSuffix}${statusSuffix}`;
}

Deno.serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
  });

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const requestId = crypto.randomUUID();
  const startTime = Date.now();

  console.info("=== Order Lifecycle Request ===", {
    requestId,
    timestamp: dateTime().toISOString(),
    method: req.method,
    url: req.url,
  });

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL");
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

    if (!supabaseUrl || !supabaseServiceKey) {
      throw new Error("Missing Supabase configuration");
    }

    const supabase = createClient(supabaseUrl, supabaseServiceKey);
    const url = new URL(req.url);
    const orderId = url.searchParams.get("orderId");
    const action = url.searchParams.get("action") || "process";

    // Route: Process all active orders
    if (action === "process-all") {
      console.info("Processing all active orders", { requestId });
      const results = await processAllActiveOrders(supabase, requestId);

      const duration = Date.now() - startTime;
      console.info("=== Batch Processing Complete ===", {
        requestId,
        duration: `${duration}ms`,
        totalProcessed: results.length,
        advanced: results.filter((r) => r.action === "advanced").length,
        unchanged: results.filter((r) => r.action === "no_change").length,
        errors: results.filter((r) => r.action === "error").length,
      });

      return new Response(
        JSON.stringify({
          success: true,
          requestId,
          summary: {
            totalProcessed: results.length,
            advanced: results.filter((r) => r.action === "advanced").length,
            unchanged: results.filter((r) => r.action === "no_change").length,
            errors: results.filter((r) => r.action === "error").length,
          },
          results,
        }),
        {
          headers: { ...corsHeaders, "Content-Type": "application/json" },
          status: 200,
        },
      );
    }

    // Route: Cancel stale unpaid orders (Option 2 checkout abandoned-at-payment).
    // Intended to be invoked on a schedule (pg_cron). Cancels order_created
    // orders with no authorized payment older than the per-tenant window.
    if (action === "cleanup-unpaid") {
      console.info("Cleaning up stale unpaid orders", { requestId });
      const results = await cleanupUnpaidOrders(supabase, requestId, startTime);

      return new Response(
        JSON.stringify({
          success: true,
          requestId,
          summary: {
            totalTouched: results.length,
            cancelled: results.filter((r) => r.action === "cancelled").length,
            skipped: results.filter((r) => r.action === "skipped").length,
            errors: results.filter((r) => r.action === "error").length,
          },
          results,
        }),
        {
          headers: { ...corsHeaders, "Content-Type": "application/json" },
          status: 200,
        },
      );
    }

    // Route: Process single order
    if (orderId) {
      console.info("Processing single order", { requestId, orderId });
      const result = await processOrder(supabase, orderId, requestId);
      const hasError = result.action === "error";

      const duration = Date.now() - startTime;
      console.info("=== Single Order Processing Complete ===", {
        requestId,
        duration: `${duration}ms`,
        orderId,
        action: result.action,
      });

      return new Response(
        JSON.stringify({
          success: !hasError,
          requestId,
          result,
        }),
        {
          headers: { ...corsHeaders, "Content-Type": "application/json" },
          status: hasError ? 422 : 200,
        },
      );
    }

    // No valid action specified
    return new Response(
      JSON.stringify({
        error: "Invalid request",
        message: "Specify either orderId parameter or action=process-all",
      }),
      {
        headers: { ...corsHeaders, "Content-Type": "application/json" },
        status: 400,
      },
    );
  } catch (err: unknown) {
    const error = err as Error;
    const duration = Date.now() - startTime;
    console.error("=== Request Failed ===", {
      requestId,
      duration: `${duration}ms`,
      error: error.message,
      stack: error.stack,
    });

    return new Response(
      JSON.stringify({
        error: "Internal server error",
        requestId,
        message: error.message,
      }),
      {
        headers: { ...corsHeaders, "Content-Type": "application/json" },
        status: 500,
      },
    );
  }
});

/**
 * Get the next configured status from next_status_id.
 */
async function getNextStatus(
  supabase: SupabaseClient,
  currentStatus: Pick<OrderStatus, "next_status_id" | "status_key">,
): Promise<OrderStatus | null> {
  if (!currentStatus.next_status_id) {
    console.warn("No next_status_id configured for current status", {
      currentStatusKey: currentStatus.status_key,
    });
    return null;
  }

  const { data, error } = await supabase
    .from("order_statuses")
    .select(
      "id, status_key, admin_status_label, is_terminal, display_order, next_status_id, failure_status_id, next_step_owner",
    )
    .eq("id", currentStatus.next_status_id)
    .eq("is_active", true)
    .maybeSingle();

  if (error) {
    console.error("Error fetching configured next status", {
      currentStatusKey: currentStatus.status_key,
      nextStatusId: currentStatus.next_status_id,
      error: error.message,
    });
    return null;
  }

  if (!data) {
    console.warn("Configured next status is missing or inactive", {
      currentStatusKey: currentStatus.status_key,
      nextStatusId: currentStatus.next_status_id,
    });
  }

  return data as OrderStatus | null;
}

async function getFailureStatus(
  supabase: SupabaseClient,
  currentStatus: Pick<OrderStatus, "failure_status_id" | "status_key">,
): Promise<OrderStatus | null> {
  if (!currentStatus.failure_status_id) {
    console.warn("No failure_status_id configured for current status", {
      currentStatusKey: currentStatus.status_key,
    });
    return null;
  }

  const { data, error } = await supabase
    .from("order_statuses")
    .select(
      "id, status_key, admin_status_label, is_terminal, display_order, next_status_id, failure_status_id, next_step_owner",
    )
    .eq("id", currentStatus.failure_status_id)
    .eq("is_active", true)
    .maybeSingle();

  if (error) {
    console.error("Error fetching configured failure status", {
      currentStatusKey: currentStatus.status_key,
      failureStatusId: currentStatus.failure_status_id,
      error: error.message,
    });
    return null;
  }

  if (!data) {
    console.warn("Configured failure status is missing or inactive", {
      currentStatusKey: currentStatus.status_key,
      failureStatusId: currentStatus.failure_status_id,
    });
  }

  return data as OrderStatus | null;
}

async function transitionOrderToFailureStatus(params: {
  supabase: SupabaseClient;
  requestId: string;
  orderId: string;
  tenantId: string;
  orderNumber: string;
  currentStatus: OrderStatus;
  message: string;
  failureHistoryNote?: string;
  transitions: Array<{ from: string; to: string; reason: string }>;
}): Promise<ProcessingResult> {
  const {
    supabase,
    requestId,
    orderId,
    tenantId,
    orderNumber,
    currentStatus,
    message,
    failureHistoryNote,
    transitions,
  } = params;

  const failureStatus = await getFailureStatus(supabase, currentStatus);
  if (!failureStatus) {
    return {
      orderId,
      orderNumber,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "error",
      message,
      transitions,
    };
  }

  const failureTimestamp = dateTime().toISOString();
  const { data: failedOrder, error: failureUpdateError } = await supabase
    .from("orders")
    .update({
      status_id: failureStatus.id,
      status_changed_at: failureTimestamp,
    })
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .select("id")
    .maybeSingle();

  if (failureUpdateError || !failedOrder) {
    return {
      orderId,
      orderNumber,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "error",
      message: failureUpdateError
        ? `Failed to move order to configured failure status: ${failureUpdateError.message}`
        : "Order failure transition matched no rows when moving to the configured failure status",
      transitions,
    };
  }

  const resolvedFailureHistoryNote = failureHistoryNote ||
    `Order cancellation processing failed: ${message}`;
  const { error: failureHistoryError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: orderId,
      status_id: failureStatus.id,
      notes: resolvedFailureHistoryNote,
    });

  if (failureHistoryError) {
    return {
      orderId,
      orderNumber,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "error",
      message:
        `Failed to insert order status history: ${failureHistoryError.message}`,
      transitions,
    };
  }

  await notifyRtdhOrderStatusUpdated({
    supabase,
    requestId,
    tenantId,
    orderId,
    statusId: failureStatus.id,
    statusKey: failureStatus.status_key,
    previousStatusKey: currentStatus.status_key,
    source: "order-lifecycle:failure-status",
  });

  return {
    orderId,
    orderNumber,
    previousStatus: currentStatus.admin_status_label,
    newStatus: failureStatus.admin_status_label,
    action: "advanced",
    message,
    transitions: [
      ...transitions,
      {
        from: currentStatus.admin_status_label,
        to: failureStatus.admin_status_label,
        reason: resolvedFailureHistoryNote,
      },
    ],
  };
}

async function getStatusByKey(
  supabase: SupabaseClient,
  statusKey: string,
): Promise<OrderStatus | null> {
  const { data, error } = await supabase
    .from("order_statuses")
    .select(
      "id, status_key, admin_status_label, is_terminal, display_order, next_status_id, failure_status_id, next_step_owner",
    )
    .eq("status_key", statusKey)
    .eq("is_active", true)
    .maybeSingle();

  if (error) {
    console.error("Error fetching status by key", {
      statusKey,
      error: error.message,
    });
    return null;
  }

  return data as OrderStatus | null;
}

function triggerOrderLifecycleForOrderAsync(
  orderId: string,
  tenantId: string,
  requestId: string,
  source: string,
): void {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    console.warn(
      "Unable to trigger async order-lifecycle: missing env config",
      {
        requestId,
        orderId,
        tenantId,
        source,
      },
    );
    return;
  }

  const lifecycleUrl =
    `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;
  fetch(lifecycleUrl, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${supabaseServiceKey}`,
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-request-source": source,
    },
  })
    .then((response) => {
      console.info("Async order-lifecycle trigger completed", {
        requestId,
        orderId,
        tenantId,
        source,
        status: response.status,
      });
    })
    .catch((error) => {
      console.warn("Async order-lifecycle trigger failed", {
        requestId,
        orderId,
        tenantId,
        source,
        error: error instanceof Error ? error.message : String(error),
      });
    });
}

async function insertOrderStatusHistoryIfMissing(params: {
  supabase: SupabaseClient;
  orderId: string;
  statusId: string;
  notes: string;
}): Promise<void> {
  const { supabase, orderId, statusId, notes } = params;

  const { data: existingEntry, error: existingEntryError } = await supabase
    .from("order_status_history")
    .select("id")
    .eq("order_id", orderId)
    .eq("status_id", statusId)
    .eq("notes", notes)
    .limit(1)
    .maybeSingle();

  if (existingEntryError) {
    throw new Error(
      `Failed to inspect order status history for duplicates: ${existingEntryError.message}`,
    );
  }

  if (existingEntry) {
    return;
  }

  const { error: insertError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: orderId,
      status_id: statusId,
      notes,
    });

  if (insertError) {
    throw new Error(
      `Failed to insert order status history: ${insertError.message}`,
    );
  }
}

async function claimOrderLifecycleStage(params: {
  supabase: SupabaseClient;
  order: Order;
  currentStatus: OrderStatus;
  requestId: string;
  stage: string;
}): Promise<string | null> {
  const { supabase, order, currentStatus, requestId, stage } = params;
  const claimedAt = dateTime().toISOString();

  let query = supabase
    .from("orders")
    .update({
      status_changed_at: claimedAt,
    })
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("status_id", currentStatus.id)
    .select("id")
    .maybeSingle();

  query = order.status_changed_at === null
    ? query.is("status_changed_at", null)
    : query.eq("status_changed_at", order.status_changed_at);

  const { data, error } = await query;

  if (error) {
    throw new Error(`Failed to claim ${stage}: ${error.message}`);
  }

  if (!data) {
    console.info(
      "Order lifecycle stage claim skipped because another worker already claimed it",
      {
        requestId,
        orderId: order.id,
        tenantId: order.tenant_id,
        stage,
        statusKey: currentStatus.status_key,
        previousStatusChangedAt: order.status_changed_at,
      },
    );
    return null;
  }

  return claimedAt;
}

async function ensureCancellationOperationKey(params: {
  supabase: SupabaseClient;
  order: Order;
  currentStatus: OrderStatus;
  claimedStatusChangedAt: string;
}): Promise<{
  operationKey: string;
  startedAt: string;
}> {
  const { supabase, order, currentStatus, claimedStatusChangedAt } = params;

  const existingKey = typeof order.cancellation_operation_key === "string" &&
      order.cancellation_operation_key.trim().length > 0
    ? order.cancellation_operation_key.trim()
    : null;
  const existingStartedAt =
    typeof order.cancellation_operation_started_at === "string" &&
      order.cancellation_operation_started_at.trim().length > 0
      ? order.cancellation_operation_started_at
      : null;

  if (existingKey) {
    return {
      operationKey: existingKey,
      startedAt: existingStartedAt || claimedStatusChangedAt,
    };
  }

  const operationKey = crypto.randomUUID();
  const startedAt = claimedStatusChangedAt;
  const { data: updatedOrder, error: updateError } = await supabase
    .from("orders")
    .update({
      cancellation_operation_key: operationKey,
      cancellation_operation_started_at: startedAt,
      cancellation_operation_completed_at: null,
    })
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("status_id", currentStatus.id)
    .eq("status_changed_at", claimedStatusChangedAt)
    .select("id")
    .maybeSingle();

  if (updateError) {
    throw new Error(
      `Failed to persist cancellation operation marker: ${updateError.message}`,
    );
  }

  if (!updatedOrder) {
    throw new Error(
      "Cancellation operation marker update matched no rows after stage claim",
    );
  }

  return {
    operationKey,
    startedAt,
  };
}

async function fetchOrderLifecycleSnapshot(params: {
  supabase: SupabaseClient;
  orderId: string;
}): Promise<
  | (Pick<Order, "id" | "tenant_id" | "status_changed_at" | "cancelled_at"> & {
    order_statuses:
      | Pick<
        OrderStatus,
        "id" | "status_key" | "admin_status_label"
      >
      | null;
  })
  | null
> {
  const { supabase, orderId } = params;

  const { data, error } = await supabase
    .from("orders")
    .select(
      `
      id,
      tenant_id,
      status_changed_at,
      cancelled_at,
      order_statuses (
        id,
        status_key,
        admin_status_label
      )
    `,
    )
    .eq("id", orderId)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Failed to refetch order lifecycle snapshot: ${error.message}`,
    );
  }

  return data as
    | (
      & Pick<
        Order,
        "id" | "tenant_id" | "status_changed_at" | "cancelled_at"
      >
      & {
        order_statuses:
          | Pick<
            OrderStatus,
            "id" | "status_key" | "admin_status_label"
          >
          | null;
      }
    )
    | null;
}

/**
 * Process a single order - check conditions and advance status if applicable
 */
async function processOrder(
  supabase: SupabaseClient,
  orderId: string,
  requestId: string,
  transitions: Array<{ from: string; to: string; reason: string }> = [],
): Promise<ProcessingResult> {
  // Fetch order with current status and shipping address
  const { data: order, error: orderError } = await supabase
    .from("orders")
    .select(
      `
      id,
      order_number,
      tenant_id,
      patient_id,
      subscription_id,
      status_id,
      status_changed_at,
      created_at,
      product_id,
      total_cents,
      internal_notes,
      cancellation_reason,
      cancellation_operation_key,
      cancellation_operation_started_at,
      cancellation_operation_completed_at,
      cancelled_at,
      renewal_at,
      paid_at,
      payment_failed_at,
      provider_platform_integration_key,
      tracking_number,
      tracking_url,
      delivered_at,
      shipping_first_name,
      shipping_last_name,
      shipping_address_line1,
      shipping_address_line2,
      shipping_city,
      shipping_state,
      shipping_postal_code,
      shipping_country,
      billing_first_name,
      billing_last_name,
      billing_address_line1,
      billing_address_line2,
      billing_city,
      billing_state,
      billing_postal_code,
      billing_country,
      order_statuses (
        id,
        status_key,
        admin_status_label,
        is_terminal,
        display_order,
        next_status_id,
        failure_status_id,
        next_step_owner
      )
    `,
    )
    .eq("id", orderId)
    .maybeSingle();

  if (orderError) {
    console.error("Error fetching order", {
      requestId,
      orderId,
      error: orderError.message,
    });
    return {
      orderId,
      orderNumber: "unknown",
      previousStatus: null,
      newStatus: null,
      action: "error",
      message: `Failed to fetch order: ${orderError.message}`,
    };
  }

  if (!order) {
    return {
      orderId,
      orderNumber: "unknown",
      previousStatus: null,
      newStatus: null,
      action: "error",
      message: "Order not found",
    };
  }

  const typedOrder = order as Order;
  const currentStatus = typedOrder.order_statuses;

  // Some terminal states still need lifecycle side effects when invoked again.
  if (
    currentStatus?.is_terminal &&
    !isLifecycleHandledTerminalStatus(currentStatus)
  ) {
    console.info("Order is in terminal state, skipping", {
      requestId,
      orderId,
      status: currentStatus.status_key,
    });
    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message: "Order is in terminal state",
      transitions,
    };
  }

  if (
    typedOrder.cancellation_reason &&
    !typedOrder.cancelled_at &&
    currentStatus?.status_key === "provider_review_pending"
  ) {
    if (isMdiOrder(typedOrder)) {
      const pendingCancelStatus = await getStatusByKey(
        supabase,
        "order_pending_cancellation",
      );

      if (!pendingCancelStatus) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: "order_pending_cancellation status is not configured",
          transitions,
        };
      }

      const transitionAt = dateTime().toISOString();
      const { error: updateError } = await supabase
        .from("orders")
        .update({
          status_id: pendingCancelStatus.id,
          status_changed_at: transitionAt,
        })
        .eq("id", orderId)
        .eq("tenant_id", typedOrder.tenant_id);

      if (updateError) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message:
            `Failed to queue MDI provider review cancellation: ${updateError.message}`,
          transitions,
        };
      }

      const queueNote =
        "Deferred MDI cancellation request reached provider review; queueing cancellation processing.";
      const { error: historyInsertError } = await supabase
        .from("order_status_history")
        .insert({
          order_id: orderId,
          status_id: pendingCancelStatus.id,
          notes: queueNote,
        });

      if (historyInsertError) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message:
            `Failed to insert order status history: ${historyInsertError.message}`,
          transitions,
        };
      }

      return processOrder(supabase, orderId, requestId, [
        ...transitions,
        {
          from: currentStatus.admin_status_label,
          to: pendingCancelStatus.admin_status_label,
          reason: queueNote,
        },
      ]);
    }

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message:
        "Cancellation request recorded; waiting for provider decision before processing",
      transitions,
    };
  }

  if (
    typedOrder.cancellation_reason &&
    !typedOrder.cancelled_at &&
    (currentStatus?.status_key === "provider_approved" ||
      currentStatus?.status_key === "order_approved" ||
      currentStatus?.status_key === "provider_rejected" ||
      currentStatus?.status_key === "payment_pending")
  ) {
    const pendingCancelStatus = await getStatusByKey(
      supabase,
      "order_pending_cancellation",
    );

    if (!pendingCancelStatus) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message: "order_pending_cancellation status is not configured",
        transitions,
      };
    }

    const transitionAt = dateTime().toISOString();
    const { error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: pendingCancelStatus.id,
        status_changed_at: transitionAt,
      })
      .eq("id", orderId)
      .eq("tenant_id", typedOrder.tenant_id);

    if (updateError) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          `Failed to queue deferred cancellation: ${updateError.message}`,
        transitions,
      };
    }

    const queueNote = currentStatus.status_key === "provider_approved"
      ? "Deferred cancellation request reached provider approval; queueing cancellation processing."
      : currentStatus.status_key === "order_approved"
      ? "Deferred cancellation request reached order approval; queueing cancellation processing before any pharmacy send."
      : currentStatus.status_key === "payment_pending"
      ? "Deferred cancellation request reached payment pending; queueing cancellation processing before payment capture."
      : "Deferred cancellation request reached provider rejection; queueing cancellation processing.";
    const { error: historyInsertError } = await supabase
      .from("order_status_history")
      .insert({
        order_id: orderId,
        status_id: pendingCancelStatus.id,
        notes: queueNote,
      });

    if (historyInsertError) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          `Failed to insert order status history: ${historyInsertError.message}`,
        transitions,
      };
    }

    return processOrder(supabase, orderId, requestId, [
      ...transitions,
      {
        from: currentStatus.admin_status_label,
        to: pendingCancelStatus.admin_status_label,
        reason: queueNote,
      },
    ]);
  }

  // Release Stripe payment when provider rejects and there is no cancellation request
  if (
    currentStatus?.status_key === "provider_rejected" &&
    !typedOrder.cancellation_reason
  ) {
    console.info(
      "Order is provider_rejected without cancellation_reason; attempting Stripe payment release",
      {
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
      },
    );

    const releaseResult = await releaseStripePaymentForRejectedOrder({
      supabase,
      order: typedOrder,
      requestId,
    });

    const paymentHistoryNote = buildProviderRejectedPaymentHistoryNote(
      releaseResult,
    );
    const { error: paymentHistoryError } = await supabase.from(
      "order_status_history",
    ).insert({
      order_id: orderId,
      status_id: typedOrder.status_id,
      notes: paymentHistoryNote,
    });

    if (paymentHistoryError) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          `Failed to insert provider rejection payment history: ${paymentHistoryError.message}`,
        transitions,
      };
    }

    let planCancellationResult;
    try {
      planCancellationResult = await cancelLinkedPlanForProviderRejectedOrder({
        supabase,
        order: typedOrder as unknown as OrderForPendingCancellation,
        requestId,
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      console.error(
        "Failed to cancel linked plan for provider-rejected order",
        {
          requestId,
          orderId: typedOrder.id,
          tenantId: typedOrder.tenant_id,
          error: message,
        },
      );
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message,
        transitions,
      };
    }

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message:
        `provider_rejected: ${releaseResult.message}; ${planCancellationResult.message}`,
      transitions,
    };
  }

  if (currentStatus?.status_key === "order_validation_pending") {
    console.info(
      "Order in order_validation_pending, evaluating provider validation handoff",
      {
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        providerPlatformIntegrationKey:
          typedOrder.provider_platform_integration_key,
      },
    );

    if (isMdiOrder(typedOrder)) {
      const validationClaim = await claimOrderLifecycleStage({
        supabase,
        order: typedOrder,
        currentStatus,
        requestId,
        stage: "order_validation_pending",
      });

      if (!validationClaim) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "no_change",
          message:
            "order_validation_pending is already being processed by another worker",
          transitions,
        };
      }

      const nextStatus = await getNextStatus(supabase, currentStatus);
      if (!nextStatus) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: "No next status configured after order_validation_pending",
          transitions,
        };
      }

      const { data: updatedOrder, error: updateError } = await supabase
        .from("orders")
        .update({
          status_id: nextStatus.id,
          status_changed_at: dateTime().toISOString(),
        })
        .eq("id", orderId)
        .eq("tenant_id", typedOrder.tenant_id)
        .eq("status_id", currentStatus.id)
        .eq("status_changed_at", validationClaim)
        .select("id")
        .maybeSingle();

      if (updateError || !updatedOrder) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: updateError ? "error" : "no_change",
          message: updateError
            ? `Failed to advance order_validation_pending: ${updateError.message}`
            : "order_validation_pending transition matched no rows after stage claim",
          transitions,
        };
      }

      const transitionNote =
        "Auto-advanced: MDI order validation completed by system.";
      await supabase.from("order_status_history").insert({
        order_id: orderId,
        status_id: nextStatus.id,
        notes: transitionNote,
      });

      await notifyRtdhOrderStatusUpdated({
        supabase,
        requestId,
        tenantId: typedOrder.tenant_id,
        orderId,
        statusId: nextStatus.id,
        statusKey: nextStatus.status_key,
        previousStatusKey: currentStatus.status_key,
        source: "order-lifecycle:order-validation-pending",
      });

      triggerOrderLifecycleForOrderAsync(
        typedOrder.id,
        typedOrder.tenant_id,
        requestId,
        "order-lifecycle:order_validation_pending_mdi",
      );

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: nextStatus.admin_status_label,
        action: "advanced",
        message:
          "MDI order validation completed; advanced to next status and queued lifecycle reprocessing",
        transitions: [
          ...transitions,
          {
            from: currentStatus.admin_status_label,
            to: nextStatus.admin_status_label,
            reason: transitionNote,
          },
        ],
      };
    }

    if (isTelegraOrder(typedOrder)) {
      const validationClaim = await claimOrderLifecycleStage({
        supabase,
        order: typedOrder,
        currentStatus,
        requestId,
        stage: "order_validation_pending",
      });

      if (!validationClaim) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "no_change",
          message:
            "order_validation_pending is already being processed by another worker",
          transitions,
        };
      }

      const leaveWaitingRoomResult = await leaveTelegraWaitingRoomForLifecycle({
        supabase,
        order: typedOrder,
        requestId,
      });

      console.info("order_validation_pending Telegra leaveWaitingRoom result", {
        requestId,
        orderId,
        applicable: leaveWaitingRoomResult.applicable,
        triggered: leaveWaitingRoomResult.triggered,
        alreadyTriggered: leaveWaitingRoomResult.alreadyTriggered,
        providerOrderId: leaveWaitingRoomResult.externalOrderId,
        message: leaveWaitingRoomResult.message,
      });

      if (!leaveWaitingRoomResult.triggered) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: leaveWaitingRoomResult.applicable ? "error" : "no_change",
          message: leaveWaitingRoomResult.message,
          transitions,
        };
      }

      await insertOrderStatusHistoryIfMissing({
        supabase,
        orderId,
        statusId: currentStatus.id,
        notes: leaveWaitingRoomResult.alreadyTriggered
          ? "Telegra leaveWaitingRoom already requested; waiting for provider review webhook."
          : "Telegra leaveWaitingRoom requested; waiting for provider review webhook.",
      });

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message: leaveWaitingRoomResult.alreadyTriggered
          ? "Telegra leaveWaitingRoom already requested; waiting for provider review webhook"
          : "Telegra leaveWaitingRoom requested; waiting for provider review webhook",
        transitions,
      };
    }

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message:
        "Order validation pending is only automated for MDI and Telegra orders",
      transitions,
    };
  }

  // Provider approval starts payment collection for non-Telegra providers.
  // Telegra continues via provider webhooks/pharmacy flow and must not capture
  // payment just because the prescription was approved.
  if (currentStatus?.status_key === "provider_approved") {
    if (isTelegraOrder(typedOrder)) {
      console.info(
        "Order in provider_approved for Telegra; holding status and skipping payment_pending auto-advance",
        {
          requestId,
          orderId: typedOrder.id,
          tenantId: typedOrder.tenant_id,
          providerPlatformIntegrationKey:
            typedOrder.provider_platform_integration_key,
        },
      );

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "Telegra provider_approved orders do not auto-advance to payment_pending",
        transitions,
      };
    }

    console.info(
      "Order in provider_approved, advancing to payment_pending",
      {
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        providerPlatformIntegrationKey:
          typedOrder.provider_platform_integration_key,
      },
    );

    const providerApprovedClaim = await claimOrderLifecycleStage({
      supabase,
      order: typedOrder,
      currentStatus,
      requestId,
      stage: "provider_approved",
    });

    if (!providerApprovedClaim) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "provider_approved is already being processed by another worker",
        transitions,
      };
    }

    const nextStatus = await getNextStatus(supabase, currentStatus);
    if (!nextStatus) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          "payment_pending status is not configured after provider_approved",
        transitions,
      };
    }

    if (nextStatus.status_key !== "payment_pending") {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          `Expected provider_approved to transition to payment_pending, got ${nextStatus.status_key}`,
        transitions,
      };
    }

    const transitionAt = dateTime().toISOString();
    const { data: updatedOrder, error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: nextStatus.id,
        status_changed_at: transitionAt,
      })
      .eq("id", orderId)
      .eq("tenant_id", typedOrder.tenant_id)
      .eq("status_id", currentStatus.id)
      .eq("status_changed_at", providerApprovedClaim)
      .select("id")
      .maybeSingle();

    if (updateError || !updatedOrder) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: updateError ? "error" : "no_change",
        message: updateError
          ? `Failed to advance provider_approved to payment_pending: ${updateError.message}`
          : "provider_approved transition matched no rows after stage claim",
        transitions,
      };
    }

    await supabase.from("order_status_history").insert({
      order_id: orderId,
      status_id: nextStatus.id,
      notes:
        "Auto-advanced: provider_approved moved to payment_pending for payment capture.",
    });

    await notifyRtdhOrderStatusUpdated({
      supabase,
      requestId,
      tenantId: typedOrder.tenant_id,
      orderId,
      statusId: nextStatus.id,
      statusKey: nextStatus.status_key,
      previousStatusKey: currentStatus.status_key,
      source: "order-lifecycle:provider-approved",
    });

    return processOrder(supabase, orderId, requestId, [
      ...transitions,
      {
        from: currentStatus.admin_status_label,
        to: nextStatus.admin_status_label,
        reason: "Provider approved; payment collection required",
      },
    ]);
  }

  // Expire orders in payment_failed status that have exceeded the 7-day retry window
  if (currentStatus?.status_key === "payment_failed") {
    const failedAt = typedOrder.payment_failed_at
      ? new Date(typedOrder.payment_failed_at)
      : null;
    const sevenDaysAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);

    if (failedAt && failedAt < sevenDaysAgo) {
      const expiredStatus = await getStatusByKey(supabase, "order_expired");

      if (!expiredStatus) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: "order_expired status is not configured",
          transitions,
        };
      }

      const expiredAt = dateTime().toISOString();
      const { error: updateError } = await supabase
        .from("orders")
        .update({
          status_id: expiredStatus.id,
          status_changed_at: expiredAt,
        })
        .eq("id", orderId)
        .eq("tenant_id", typedOrder.tenant_id);

      if (updateError) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: `Failed to expire order: ${updateError.message}`,
          transitions,
        };
      }

      const expiryNote =
        `Order expired: 7-day payment retry window elapsed since ${typedOrder.payment_failed_at}.`;
      await supabase.from("order_status_history").insert({
        order_id: orderId,
        status_id: expiredStatus.id,
        notes: expiryNote,
      });

      const newTransitions = [
        ...transitions,
        {
          from: currentStatus.admin_status_label,
          to: expiredStatus.admin_status_label,
          reason: expiryNote,
        },
      ];

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: expiredStatus.admin_status_label,
        action: "advanced",
        message: "Order expired due to payment retry window",
        transitions: newTransitions,
      };
    }

    // Not yet expired — nothing to do
    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message:
        "Order is in payment_failed status; waiting for patient retry or expiry",
      transitions,
    };
  }

  if (currentStatus?.status_key === "order_created") {
    console.info(
      "Order in order_created, dispatching RTDH create-order webhook",
      {
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        statusId: typedOrder.status_id,
      },
    );

    await triggerRtdhCreateOrder({
      supabase,
      requestId,
      tenantId: typedOrder.tenant_id,
      patientId: typedOrder.patient_id,
      orderId: typedOrder.id,
      orderStatusId: typedOrder.status_id,
    });

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message:
        "Order is in order_created; RTDH create-order webhook dispatch attempted",
      transitions,
    };
  }

  // Handle shipping_details_required status
  if (currentStatus?.status_key === "shipping_details_required") {
    console.info(
      "Order in shipping_details_required, checking order shipping and billing addresses",
      {
        requestId,
        orderId,
      },
    );

    // PP-566 contact-validation gate: do not advance toward provider/questionnaire
    // creation until the patient has VERIFIED their email (post-payment account &
    // contact step). This guarantees the provider managing the questionnaires
    // receives a validated email. Phone is attested separately (no SMS), so it is
    // not gated here.
    const { data: gatePatient, error: gatePatientError } = await supabase
      .from("patients")
      .select("email_verified_at")
      .eq("id", typedOrder.patient_id)
      .eq("tenant_id", typedOrder.tenant_id)
      .maybeSingle();

    if (gatePatientError) {
      console.warn(
        "Could not read patient email_verified_at for contact gate; holding order",
        { requestId, orderId, error: gatePatientError.message },
      );
    }

    if (!gatePatient?.email_verified_at) {
      console.info(
        "Order held at shipping_details_required: email not yet verified (PP-566 contact gate)",
        { requestId, orderId, patientId: typedOrder.patient_id },
      );
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "Waiting for the patient to verify their email before provider intake.",
        transitions,
      };
    }

    // Use the order's shipping and billing addresses (already fetched)
    const hasShipping = hasCompleteShippingAddress(typedOrder);
    const hasBilling = hasCompleteBillingAddress(typedOrder);

    console.info("Order address validation result", {
      requestId,
      orderId,
      hasShipping,
      hasBilling,
      shippingFields: {
        first_name: isFieldFilled(typedOrder.shipping_first_name),
        last_name: isFieldFilled(typedOrder.shipping_last_name),
        line1: isFieldFilled(typedOrder.shipping_address_line1),
        line2: isFieldFilled(typedOrder.shipping_address_line2),
        city: isFieldFilled(typedOrder.shipping_city),
        state: isFieldFilled(typedOrder.shipping_state),
        postal_code: isFieldFilled(typedOrder.shipping_postal_code),
        country: isFieldFilled(typedOrder.shipping_country),
      },
      billingFields: {
        first_name: isFieldFilled(typedOrder.billing_first_name),
        last_name: isFieldFilled(typedOrder.billing_last_name),
        line1: isFieldFilled(typedOrder.billing_address_line1),
        line2: isFieldFilled(typedOrder.billing_address_line2),
        city: isFieldFilled(typedOrder.billing_city),
        state: isFieldFilled(typedOrder.billing_state),
        postal_code: isFieldFilled(typedOrder.billing_postal_code),
        country: isFieldFilled(typedOrder.billing_country),
      },
    });

    if (hasShipping && hasBilling) {
      // Get the next configured status
      const nextStatus = await getNextStatus(supabase, currentStatus);

      if (!nextStatus) {
        console.warn("No next status found after shipping_details_required", {
          requestId,
        });
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "no_change",
          message: "No next status configured after shipping_details_required",
          transitions,
        };
      }

      // Update order to next status
      const { error: updateError } = await supabase
        .from("orders")
        .update({
          status_id: nextStatus.id,
          status_changed_at: dateTime().toISOString(),
        })
        .eq("id", orderId);

      if (updateError) {
        console.error("Failed to advance order status", {
          requestId,
          orderId,
          error: updateError.message,
        });
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: `Failed to update order: ${updateError.message}`,
          transitions,
        };
      }

      // Log the status change in history
      await supabase.from("order_status_history").insert({
        order_id: orderId,
        status_id: nextStatus.id,
        notes: `Auto-advanced: Shipping and billing addresses validated`,
      });

      await notifyRtdhOrderStatusUpdated({
        supabase,
        requestId,
        tenantId: typedOrder.tenant_id,
        orderId,
        statusId: nextStatus.id,
        statusKey: nextStatus.status_key,
        previousStatusKey: currentStatus.status_key,
        source: "order-lifecycle:shipping-details-validated",
      });

      console.info("Order advanced from shipping_details_required", {
        requestId,
        orderId,
        previousStatus: currentStatus.status_key,
        newStatus: nextStatus.status_key,
      });

      // Record this transition
      const newTransitions = [
        ...transitions,
        {
          from: currentStatus.admin_status_label,
          to: nextStatus.admin_status_label,
          reason: "Shipping and billing addresses validated",
        },
      ];

      // Recursively process the order again to check if more automations apply
      return processOrder(supabase, orderId, requestId, newTransitions);
    } else {
      // Address data is incomplete, stay in current status
      const missingRequirements: string[] = [];
      if (!hasShipping) missingRequirements.push("shipping address");
      if (!hasBilling) missingRequirements.push("billing address");

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message: `Waiting for: ${missingRequirements.join(", ")}`,
        transitions,
      };
    }
  }

  // Handle payment_pending status - capture authorized payment intent if needed
  if (currentStatus?.status_key === "order_pending_cancellation") {
    console.info(
      "Order in order_pending_cancellation, evaluating cancellation requirements",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
      },
    );

    const pendingCancellationClaim = await claimOrderLifecycleStage({
      supabase,
      order: typedOrder,
      currentStatus,
      requestId,
      stage: "order_pending_cancellation",
    });

    if (!pendingCancellationClaim) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "Order pending cancellation is already being processed by another worker",
        transitions,
      };
    }

    let analysisResult;
    try {
      analysisResult = await analyzePendingOrderCancellation({
        supabase,
        order: typedOrder as unknown as OrderForPendingCancellation,
        requestId,
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      console.error("Failed to analyze pending order cancellation", {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
        error: message,
      });
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message,
        transitions,
      };
    }

    console.info("Pending cancellation analysis completed", {
      requestId,
      orderId,
      tenantId: typedOrder.tenant_id,
      previousStatusKey: analysisResult.previousStatusKey,
      refundTier: analysisResult.refundTier,
      refundAmountCents: analysisResult.refundAmountCents,
      retainedAmountCents: analysisResult.retainedAmountCents,
      providerFeeCents: analysisResult.providerFeeCents,
      paymentIntentStatus: analysisResult.paymentIntentStatus,
      needsRefundProcessing: analysisResult.needsRefundProcessing,
      needsPaymentIntentCancel: analysisResult.needsPaymentIntentCancel,
      needsStripePlanUpdate: analysisResult.needsStripePlanUpdate,
      shouldMoveToProcessing: analysisResult.shouldMoveToProcessing,
    });

    try {
      await insertOrderStatusHistoryIfMissing({
        supabase,
        orderId,
        statusId: currentStatus.id,
        notes: analysisResult.refundEligibilityHistoryNote,
      });
    } catch (historyError) {
      const message = historyError instanceof Error
        ? historyError.message
        : String(historyError);
      console.error(
        "Failed to insert order_pending_cancellation refund eligibility history",
        {
          requestId,
          orderId,
          tenantId: typedOrder.tenant_id,
          error: message,
        },
      );
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message,
        transitions,
      };
    }

    if (!analysisResult.shouldMoveToProcessing) {
      const cancelStatus = await getStatusByKey(supabase, "order_cancelled");

      if (!cancelStatus) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: "order_cancelled status is not configured",
          transitions,
        };
      }

      let directCancellationResult;
      try {
        directCancellationResult = await finalizeDirectOrderCancellation({
          supabase,
          order: typedOrder as unknown as OrderForPendingCancellation,
          requestId,
        });
      } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message,
          transitions,
        };
      }

      const { data: cancelledOrder, error: updateError } = await supabase
        .from("orders")
        .update({
          status_id: cancelStatus.id,
          status_changed_at: directCancellationResult.cancelledAt,
          cancelled_at: directCancellationResult.cancelledAt,
        })
        .eq("id", orderId)
        .eq("tenant_id", typedOrder.tenant_id)
        .eq("status_id", currentStatus.id)
        .eq("status_changed_at", pendingCancellationClaim)
        .select("id")
        .maybeSingle();

      if (updateError || !cancelledOrder) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: updateError
            ? `Failed to update order: ${updateError.message}`
            : "Order cancellation update matched no rows after stage claim",
          transitions,
        };
      }

      const { error: cancelHistoryError } = await supabase
        .from("order_status_history")
        .insert({
          order_id: orderId,
          status_id: cancelStatus.id,
          notes: directCancellationResult.completionHistoryNote,
        });

      if (cancelHistoryError) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message:
            `Failed to insert order status history: ${cancelHistoryError.message}`,
          transitions,
        };
      }

      await notifyRtdhOrderCancelled({
        supabase,
        requestId,
        tenantId: typedOrder.tenant_id,
        orderId,
        statusId: cancelStatus.id,
        previousStatusKey: currentStatus.status_key,
        cancellationStage: "before_provider_creation",
        cancellationReason: typedOrder.cancellation_reason ||
          "patient_requested",
        source: "order-lifecycle:direct-order-cancellation",
      });

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: cancelStatus.admin_status_label,
        action: "advanced",
        message: "Order cancelled without additional Stripe processing",
        transitions: [
          ...transitions,
          {
            from: currentStatus.admin_status_label,
            to: cancelStatus.admin_status_label,
            reason: directCancellationResult.completionHistoryNote,
          },
        ],
      };
    }

    const nextStatus = await getNextStatus(supabase, currentStatus);
    if (!nextStatus) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message: "No next status configured after order_pending_cancellation",
        transitions,
      };
    }

    const cancellationOperation = await ensureCancellationOperationKey({
      supabase,
      order: typedOrder,
      currentStatus,
      claimedStatusChangedAt: pendingCancellationClaim,
    });

    const { data: processingOrder, error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: nextStatus.id,
        status_changed_at: analysisResult.analyzedAt,
        cancellation_operation_key: cancellationOperation.operationKey,
        cancellation_operation_started_at: cancellationOperation.startedAt,
        cancellation_operation_completed_at: null,
      })
      .eq("id", orderId)
      .eq("tenant_id", typedOrder.tenant_id)
      .eq("status_id", currentStatus.id)
      .eq("status_changed_at", pendingCancellationClaim)
      .select("id")
      .maybeSingle();

    if (updateError || !processingOrder) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message: updateError
          ? `Failed to update order: ${updateError.message}`
          : "Order processing transition matched no rows after stage claim",
        transitions,
      };
    }

    const processingTransitionNote =
      "Cancellation requires refund or Stripe processing; advanced to order_cancellation_processing.";

    try {
      await insertOrderStatusHistoryIfMissing({
        supabase,
        orderId,
        statusId: nextStatus.id,
        notes: processingTransitionNote,
      });
    } catch (processingHistoryError) {
      const message = processingHistoryError instanceof Error
        ? processingHistoryError.message
        : String(processingHistoryError);
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message,
        transitions,
      };
    }

    await notifyRtdhOrderStatusUpdated({
      supabase,
      requestId,
      tenantId: typedOrder.tenant_id,
      orderId,
      statusId: nextStatus.id,
      statusKey: nextStatus.status_key,
      previousStatusKey: currentStatus.status_key,
      source: "order-lifecycle:order_pending_cancellation",
    });

    triggerOrderLifecycleForOrderAsync(
      typedOrder.id,
      typedOrder.tenant_id,
      requestId,
      "order-lifecycle:order_pending_cancellation",
    );

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: nextStatus.admin_status_label,
      action: "advanced",
      message: "Order cancellation requires processing",
      transitions: [
        ...transitions,
        {
          from: currentStatus.admin_status_label,
          to: nextStatus.admin_status_label,
          reason:
            "Cancellation requires refund or Stripe processing; advanced to order_cancellation_processing.",
        },
      ],
    };
  }

  if (currentStatus?.status_key === "order_cancellation_processing") {
    console.info(
      "Order in order_cancellation_processing, applying refund and Stripe cancellation work",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
      },
    );

    const cancellationProcessingClaim = await claimOrderLifecycleStage({
      supabase,
      order: typedOrder,
      currentStatus,
      requestId,
      stage: "order_cancellation_processing",
    });

    if (!cancellationProcessingClaim) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "Order cancellation processing is already being handled by another worker",
        transitions,
      };
    }

    const cancellationOperation = await ensureCancellationOperationKey({
      supabase,
      order: typedOrder,
      currentStatus,
      claimedStatusChangedAt: cancellationProcessingClaim,
    });

    const nextStatus = await getNextStatus(supabase, currentStatus);
    if (!nextStatus) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "No next status configured after order_cancellation_processing",
        transitions,
      };
    }

    let processingResult;
    try {
      processingResult = await processOrderCancellationProcessing({
        supabase,
        order: {
          ...(typedOrder as unknown as OrderForPendingCancellation),
          cancellation_operation_key: cancellationOperation.operationKey,
          cancellation_operation_started_at: cancellationOperation.startedAt,
          cancellation_operation_completed_at:
            typedOrder.cancellation_operation_completed_at,
        },
        requestId,
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      return await transitionOrderToFailureStatus({
        supabase,
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        orderNumber: typedOrder.order_number,
        currentStatus,
        message,
        transitions,
      });
    }

    const { data: updatedOrder, error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: nextStatus.id,
        status_changed_at: processingResult.cancelledAt,
        cancelled_at: processingResult.cancelledAt,
        cancellation_operation_completed_at: processingResult.cancelledAt,
      })
      .eq("id", orderId)
      .eq("tenant_id", typedOrder.tenant_id)
      .eq("status_id", currentStatus.id)
      .eq("status_changed_at", cancellationProcessingClaim)
      .select("id")
      .maybeSingle();

    if (updateError) {
      return await transitionOrderToFailureStatus({
        supabase,
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        orderNumber: typedOrder.order_number,
        currentStatus,
        message: `Failed to update order: ${updateError.message}`,
        transitions,
      });
    }

    if (!updatedOrder) {
      const refreshedOrder = await fetchOrderLifecycleSnapshot({
        supabase,
        orderId: typedOrder.id,
      });

      if (
        refreshedOrder &&
        refreshedOrder.tenant_id === typedOrder.tenant_id &&
        refreshedOrder.order_statuses?.status_key !== currentStatus.status_key
      ) {
        console.info(
          "Order cancellation processing completion skipped because another worker already moved the order",
          {
            requestId,
            orderId: typedOrder.id,
            tenantId: typedOrder.tenant_id,
            previousStatus: currentStatus.status_key,
            refreshedStatus: refreshedOrder.order_statuses?.status_key ?? null,
            refreshedStatusChangedAt: refreshedOrder.status_changed_at,
            cancelledAt: refreshedOrder.cancelled_at,
          },
        );

        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: refreshedOrder.order_statuses?.admin_status_label ?? null,
          action: "no_change",
          message:
            "Order cancellation processing completion was already applied by another worker",
          transitions,
        };
      }

      return await transitionOrderToFailureStatus({
        supabase,
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        orderNumber: typedOrder.order_number,
        currentStatus,
        message:
          "Order status update matched no rows (order id and tenant id filter)",
        transitions,
      });
    }

    const { error: historyInsertError } = await supabase
      .from("order_status_history")
      .insert({
        order_id: orderId,
        status_id: nextStatus.id,
        notes: processingResult.completionHistoryNote,
      });

    if (historyInsertError) {
      return await transitionOrderToFailureStatus({
        supabase,
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        orderNumber: typedOrder.order_number,
        currentStatus,
        message:
          `Failed to insert order status history: ${historyInsertError.message}`,
        transitions,
      });
    }

    await notifyRtdhOrderCancelled({
      supabase,
      requestId,
      tenantId: typedOrder.tenant_id,
      orderId,
      statusId: nextStatus.id,
      previousStatusKey: currentStatus.status_key,
      cancellationStage: "after_provider_creation",
      cancellationReason: typedOrder.cancellation_reason || "patient_requested",
      source: "order-lifecycle:order-cancellation-processing",
    });

    triggerOrderLifecycleForOrderAsync(
      typedOrder.id,
      typedOrder.tenant_id,
      requestId,
      "order-lifecycle:order_cancellation_processing",
    );

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: nextStatus.admin_status_label,
      action: "advanced",
      message: processingResult.message,
      transitions: [
        ...transitions,
        {
          from: currentStatus.admin_status_label,
          to: nextStatus.admin_status_label,
          reason: processingResult.completionHistoryNote,
        },
      ],
    };
  }

  // Handle provider order creation and retry-on-error
  if (
    currentStatus?.status_key === "provider_order_creation_pending" ||
    currentStatus?.status_key === "provider_order_creation_error"
  ) {
    const isRetryingProviderOrderCreation =
      currentStatus.status_key === "provider_order_creation_error";
    const providerCreationPendingStatus = await getStatusByKey(
      supabase,
      "provider_order_creation_pending",
    );

    if (!providerCreationPendingStatus) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          "provider_order_creation_pending status is not configured for retry",
        transitions,
      };
    }

    if (isRetryingProviderOrderCreation) {
      const retryQueuedAt = dateTime().toISOString();
      const { error: retryQueueError } = await supabase
        .from("orders")
        .update({
          status_id: providerCreationPendingStatus.id,
          status_changed_at: retryQueuedAt,
        })
        .eq("id", orderId)
        .eq("tenant_id", typedOrder.tenant_id);

      if (retryQueueError) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message:
            `Failed to move order back to provider_order_creation_pending: ${retryQueueError.message}`,
          transitions,
        };
      }

      await supabase.from("order_status_history").insert({
        order_id: orderId,
        status_id: providerCreationPendingStatus.id,
        notes:
          "Retry requested: moved from provider_order_creation_error back to provider_order_creation_pending",
      });

      await notifyRtdhOrderStatusUpdated({
        supabase,
        requestId,
        tenantId: typedOrder.tenant_id,
        orderId,
        statusId: providerCreationPendingStatus.id,
        statusKey: providerCreationPendingStatus.status_key,
        previousStatusKey: currentStatus.status_key,
        source: "order-lifecycle:provider-order-retry",
      });

      return processOrder(supabase, orderId, requestId, [
        ...transitions,
        {
          from: currentStatus.admin_status_label,
          to: providerCreationPendingStatus.admin_status_label,
          reason:
            "Retry requested: moved back to provider_order_creation_pending",
        },
      ]);
    }

    console.info(
      "Order in provider_order_creation_pending, preparing provider platform order creation",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
        productId: typedOrder.product_id,
      },
    );

    let selectedIntegrationKey = normalizeIntegrationKey(
      typedOrder.provider_platform_integration_key,
    );

    const { data: existingProviderLinks, error: existingProviderLinksError } =
      await supabase
        .from("order_provider_platform_links")
        .select(
          `
          id,
          tenant_integration_id,
          tenant_integrations!inner (
            integration_key
          )
        `,
        )
        .eq("order_id", typedOrder.id)
        .eq("tenant_id", typedOrder.tenant_id)
        .order("id", { ascending: true });

    if (existingProviderLinksError) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          `Failed to validate existing provider platform selection: ${existingProviderLinksError.message}`,
        transitions,
      };
    }

    const orderedProviderLinks = (existingProviderLinks || []) as Array<{
      id: string;
      tenant_integrations?: { integration_key?: string | null } | null;
    }>;

    if (orderedProviderLinks.length > 1) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message:
          `Invalid provider platform selection state: expected at most one selected provider link, found ${orderedProviderLinks.length}`,
        transitions,
      };
    }

    if (orderedProviderLinks.length === 1) {
      const existingLinkIntegrationKey = normalizeIntegrationKey(
        orderedProviderLinks[0]?.tenant_integrations?.integration_key || null,
      );

      selectedIntegrationKey = existingLinkIntegrationKey ||
        selectedIntegrationKey;
    } else {
      if (!typedOrder.product_id) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message:
            "Cannot resolve provider platform selection: order is missing product_id",
          transitions,
        };
      }

      const selection = await resolveAndPersistProviderPlatformSelection({
        supabase,
        tenantId: typedOrder.tenant_id,
        productId: typedOrder.product_id,
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        stateCode: typedOrder.shipping_state,
        source: "order-lifecycle",
      });

      if (!selection) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message:
            "No eligible provider platform assignment found for this order",
          transitions,
        };
      }

      selectedIntegrationKey = normalizeIntegrationKey(
        selection.integrationKey,
      );

      console.info("Provider platform selected in lifecycle", {
        requestId,
        orderId,
        integrationKey: selection.integrationKey,
        selectionReason: selection.selectionReason,
        appliedStateCode: selection.appliedStateCode,
      });
    }

    let providerOrderResult: {
      created: boolean;
      providerName: string;
      message: string;
      externalOrderId: string | null;
    };
    const orderForProviderCreation: Order = {
      ...typedOrder,
      provider_platform_integration_key: selectedIntegrationKey ||
        typedOrder.provider_platform_integration_key,
    };

    if (selectedIntegrationKey === "telegramd") {
      providerOrderResult = await createTelegraOrderForLifecycle({
        supabase,
        order: orderForProviderCreation,
        requestId,
      });
    } else if (selectedIntegrationKey === "md_integrations") {
      providerOrderResult = await createMdiOrderForLifecycle({
        supabase,
        order: orderForProviderCreation,
        requestId,
      });
    } else {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          `Selected provider platform '${selectedIntegrationKey}' is not supported for lifecycle order creation`,
        transitions,
      };
    }

    if (!providerOrderResult.created) {
      console.error("Provider order creation failed", {
        requestId,
        orderId,
        message: providerOrderResult.message,
      });

      if (typedOrder.status_id) {
        await supabase.from("order_status_history").insert({
          order_id: orderId,
          status_id: typedOrder.status_id,
          notes:
            `Provider order creation failed: ${providerOrderResult.message}`,
        });
      }

      return await transitionOrderToFailureStatus({
        supabase,
        requestId,
        orderId: typedOrder.id,
        tenantId: typedOrder.tenant_id,
        orderNumber: typedOrder.order_number,
        currentStatus,
        message: providerOrderResult.message,
        failureHistoryNote:
          `Provider order creation failed: ${providerOrderResult.message}`,
        transitions,
      });
    }

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: currentStatus.admin_status_label,
      action: "no_change",
      message:
        "Provider order created successfully; waiting for order.fulfillment_linked webhook event to advance status",
      transitions,
    };
  }

  // Handle payment_pending status - capture authorized payment intent if needed
  if (currentStatus?.status_key === "payment_pending") {
    console.info(
      "Order in payment_pending, checking Stripe payment capture state",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
      },
    );

    const shouldSkipPaymentPending = Boolean(
      getPaymentPendingSkipReason(typedOrder),
    );
    const skipPaymentPendingReason = getPaymentPendingSkipReason(typedOrder);

    if (shouldSkipPaymentPending) {
      console.info(
        "Payment_pending local auto-advance skipped; waiting for RTDH payment_collected event",
        {
          requestId,
          orderId,
          tenantId: typedOrder.tenant_id,
          reason: skipPaymentPendingReason,
        },
      );

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          `Payment pending remains unchanged because payment_collected is only applied from rtdh-webhook; local skip reason: ${skipPaymentPendingReason}`,
        transitions,
      };
    }

    const captureResult = await maybeCaptureStripePaymentForPaymentPendingOrder(
      supabase,
      typedOrder,
      requestId,
    );

    console.info("Payment-pending capture result", {
      requestId,
      orderId,
      captured: captureResult.captured,
      alreadyCaptured: captureResult.alreadyCaptured,
      message: captureResult.message,
    });

    if (captureResult.captured || captureResult.alreadyCaptured) {
      // Embedded checkout (PP-566) does not create a Stripe subscription up
      // front. Now that payment is captured (post-approval), create it for
      // subscription products so renewals/cancellation/portal work. Best-effort
      // and idempotent (keyed by order id); a failure here must not undo the
      // captured payment. The status still waits for RTDH payment_collected.
      // No-op for one-time products and orders that already have a subscription
      // (incl. the hosted flow).
      try {
        const subResult = await ensureSubscriptionForCapturedOrder(
          supabase,
          typedOrder.id,
          typedOrder.tenant_id,
          requestId,
        );
        if (subResult.created) {
          console.info("Subscription created for captured embedded order", {
            requestId,
            orderId: typedOrder.id,
            subscriptionId: subResult.subscriptionId,
          });

          // The RTDH payment_collected round trip races with this invocation:
          // its status-update dispatch usually reads the payment transaction
          // before the subscription ids above were persisted, so the master
          // object misses the stripe_subscription_id link that renewal
          // invoices resolve against. Send a follow-up status update carrying
          // the STRIPE subscription id (sub_...) explicitly now that it is
          // known — subResult.subscriptionId is the local row id and must NOT
          // be sent to RTDH.
          if (subResult.stripeSubscriptionId) {
            const { data: currentOrderStatus } = await supabase
              .from("orders")
              .select("order_statuses!inner(status_key)")
              .eq("id", typedOrder.id)
              .eq("tenant_id", typedOrder.tenant_id)
              .maybeSingle();
            const currentStatusKey = (currentOrderStatus?.order_statuses as
              | { status_key?: string }
              | null)?.status_key;

            await notifyRtdhOrderStatusUpdated({
              supabase,
              requestId,
              tenantId: typedOrder.tenant_id,
              orderId: typedOrder.id,
              statusId: null,
              statusKey:
                currentStatusKey && isRtdhOrderStatusUpdateKey(currentStatusKey)
                  ? currentStatusKey
                  : "payment_pending",
              previousStatusKey: null,
              source: "order-lifecycle:subscription-created",
              subscriptionIdOverride: subResult.stripeSubscriptionId,
            });
          }

          // Run the authoritative schedule-sync now that the subscription and
          // its subscription_payment_provider_links row exist. Doing it here —
          // instead of relying on the racing payment_collected handler — makes
          // the sync deterministic: the payment_collected round-trip often runs
          // before the link is written and skips with "missing subscription
          // reference", leaving the lead-days-adjusted renewal and Stripe
          // trial_end unaligned. Best-effort (a failure must not disturb the
          // captured payment); idempotent — the payment_collected handler's own
          // sync then hits the "already synced" guard and is a no-op.
          try {
            const { data: freshOrder } = await supabase
              .from("orders")
              .select(
                "id, tenant_id, subscription_id, product_id, renewal_at, paid_at, created_at",
              )
              .eq("id", typedOrder.id)
              .eq("tenant_id", typedOrder.tenant_id)
              .maybeSingle();

            if (freshOrder?.subscription_id) {
              const captureSyncResult =
                await syncLifecycleDatesForPaymentCollectedOrder({
                  supabase,
                  order: freshOrder,
                  requestId,
                });
              console.info("Schedule sync after subscription creation", {
                requestId,
                orderId: typedOrder.id,
                synced: captureSyncResult.synced,
                message: captureSyncResult.message,
              });
            }
          } catch (syncError) {
            console.error(
              "Schedule sync after subscription creation failed (payment still captured)",
              {
                requestId,
                orderId: typedOrder.id,
                error: syncError instanceof Error
                  ? syncError.message
                  : String(syncError),
              },
            );
          }
        } else {
          console.debug("Subscription setup at capture: no-op/skip", {
            requestId,
            orderId: typedOrder.id,
            message: subResult.message,
          });
        }
      } catch (subError) {
        console.error(
          "Subscription setup at capture failed (payment still captured)",
          {
            requestId,
            orderId: typedOrder.id,
            error: subError instanceof Error
              ? subError.message
              : String(subError),
          },
        );
      }

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: currentStatus.admin_status_label,
        action: "no_change",
        message:
          `${captureResult.message}; waiting for rtdh-webhook payment_collected event to advance status`,
        transitions: [
          ...transitions,
          {
            from: currentStatus.admin_status_label,
            to: currentStatus.admin_status_label,
            reason:
              "Payment confirmed locally; status advancement waits for RTDH payment_collected",
          },
        ],
      };
    }

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message: captureResult.message,
      transitions,
    };
  }

  // Handle payment_collected status - sync lifecycle dates, advance status, and trigger provider workflow
  if (currentStatus?.status_key === "payment_collected") {
    console.info(
      "Order in payment_collected, syncing order and plan lifecycle dates",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
        statusKey: currentStatus.status_key,
      },
    );

    const syncResult = await syncLifecycleDatesForPaymentCollectedOrder({
      supabase,
      order: typedOrder,
      requestId,
    });

    console.info("Payment-collected lifecycle schedule sync result", {
      requestId,
      orderId,
      statusKey: currentStatus.status_key,
      synced: syncResult.synced,
      orderExpirationAt: syncResult.orderExpirationAt,
      planRenewalAt: syncResult.planRenewalAt,
      message: syncResult.message,
    });

    const completionMessage = syncResult.message;
    // Ownership resolves the race: the embedded/payment-first subscription (and
    // its link) is created by the concurrent payment_pending invocation, which
    // is the sole owner of that order's schedule sync (ensureSubscriptionForCapturedOrder
    // + the authoritative sync it runs). A missing link here therefore just
    // means that setup is still in flight — this handler is NOT the syncer, so
    // it advances status without attempting or reporting a sync. When the link
    // already exists (hosted, renewal, or the recovery case where setup's own
    // sync failed) the sync above runs normally and reports its real result.
    const subscriptionSetupOwnsSync = !syncResult.synced &&
      syncResult.code === "missing_subscription_link";
    const nextStatus = await getNextStatus(supabase, currentStatus);

    if (!nextStatus) {
      console.warn("No next status found after payment_collected", {
        requestId,
        orderId,
      });
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          `${completionMessage}. No next status configured after payment_collected`,
        transitions,
      };
    }

    const { error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: nextStatus.id,
        status_changed_at: dateTime().toISOString(),
      })
      .eq("id", orderId);

    if (updateError) {
      console.error(
        "Failed to advance order status after payment_collected",
        {
          requestId,
          orderId,
          error: updateError.message,
        },
      );
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message: `Failed to update order: ${updateError.message}`,
        transitions,
      };
    }

    const scheduleSyncNote = syncResult.synced
      ? `Auto-advanced: payment_collected sync complete (${completionMessage})`
      : subscriptionSetupOwnsSync
      ? "Auto-advanced: payment_collected"
      : `Auto-advanced: payment_collected schedule sync skipped (${completionMessage})`;

    await supabase.from("order_status_history").insert({
      order_id: orderId,
      status_id: nextStatus.id,
      notes: scheduleSyncNote,
    });

    await notifyRtdhOrderStatusUpdated({
      supabase,
      requestId,
      tenantId: typedOrder.tenant_id,
      orderId,
      statusId: nextStatus.id,
      statusKey: nextStatus.status_key,
      previousStatusKey: currentStatus.status_key,
      source: "order-lifecycle:payment-collected",
    });

    if (nextStatus.status_key === "order_approved") {
      triggerOrderLifecycleForOrderAsync(
        typedOrder.id,
        typedOrder.tenant_id,
        requestId,
        "order-lifecycle:payment_collected_to_order_approved",
      );
    }

    const currentTransitions = [
      ...transitions,
      {
        from: currentStatus.admin_status_label,
        to: nextStatus.admin_status_label,
        reason: syncResult.synced
          ? "Payment-collected lifecycle dates synced"
          : subscriptionSetupOwnsSync
          ? "Payment-collected advanced; schedule sync owned by subscription setup"
          : "Payment-collected schedule sync skipped",
      },
    ];

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: nextStatus.admin_status_label,
      action: "advanced",
      message: completionMessage,
      transitions: currentTransitions,
    };
  }

  // Handle order_approved status – hand the paid order back to the selected
  // provider, then wait for its sent-to-pharmacy webhook to advance the order.
  if (currentStatus?.status_key === "order_approved") {
    console.info(
      "Order in order_approved, evaluating provider pharmacy handoff",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
      },
    );

    if (isMdiOrder(typedOrder)) {
      const mdiProcessingResult = await requestMdiCaseProcessingForLifecycle({
        supabase,
        order: typedOrder,
        requestId,
      });

      console.info("order_approved MDI case processing request result", {
        requestId,
        orderId,
        applicable: mdiProcessingResult.applicable,
        processingRequested: mdiProcessingResult.processingRequested,
        alreadyRequested: mdiProcessingResult.alreadyRequested,
        providerOrderId: mdiProcessingResult.externalOrderId,
        message: mdiProcessingResult.message,
      });

      if (!mdiProcessingResult.processingRequested) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: "error",
          message: mdiProcessingResult.message,
          transitions,
        };
      }

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message: mdiProcessingResult.alreadyRequested
          ? "MDI case processing already requested; waiting for sent-to-pharmacy webhook"
          : "MDI case processing requested; waiting for sent-to-pharmacy webhook",
        transitions,
      };
    }

    if (!isTelegraOrder(typedOrder)) {
      const nextStatus = await getNextStatus(supabase, currentStatus);
      if (nextStatus) {
        const { error: updateError } = await supabase
          .from("orders")
          .update({
            status_id: nextStatus.id,
            status_changed_at: dateTime().toISOString(),
          })
          .eq("id", orderId);
        if (!updateError) {
          await supabase.from("order_status_history").insert({
            order_id: orderId,
            status_id: nextStatus.id,
            notes:
              "Auto-advanced: order_approved (selected provider platform is not Telegra)",
          });
          return {
            orderId: typedOrder.id,
            orderNumber: typedOrder.order_number,
            previousStatus: currentStatus.admin_status_label,
            newStatus: nextStatus.admin_status_label,
            action: "advanced",
            message:
              "Selected provider platform is not Telegra; auto-advanced past order_approved",
            transitions: [
              ...transitions,
              {
                from: currentStatus.admin_status_label,
                to: nextStatus.admin_status_label,
                reason: "Selected provider platform is not Telegra",
              },
            ],
          };
        }
      }

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message:
          "Selected provider platform is not Telegra and no next status is configured",
        transitions,
      };
    }

    const sendResult = await sendTelegraOrderToPharmacyForLifecycle({
      supabase,
      order: typedOrder,
      requestId,
    });

    console.info("order_approved sendToPharmacyRecipients result", {
      requestId,
      orderId,
      applicable: sendResult.applicable,
      sent: sendResult.sent,
      providerOrderId: sendResult.externalOrderId,
      message: sendResult.message,
    });

    // No Telegra integration configured – auto-advance past this status
    if (!sendResult.applicable) {
      const nextStatus = await getNextStatus(supabase, currentStatus);
      if (nextStatus) {
        const { error: updateError } = await supabase
          .from("orders")
          .update({
            status_id: nextStatus.id,
            status_changed_at: dateTime().toISOString(),
          })
          .eq("id", orderId);
        if (!updateError) {
          await supabase.from("order_status_history").insert({
            order_id: orderId,
            status_id: nextStatus.id,
            notes:
              "Auto-advanced: order_approved (no pharmacy integration configured)",
          });
          return {
            orderId: typedOrder.id,
            orderNumber: typedOrder.order_number,
            previousStatus: currentStatus.admin_status_label,
            newStatus: nextStatus.admin_status_label,
            action: "advanced",
            message:
              "No pharmacy integration; auto-advanced past order_approved",
            transitions: [
              ...transitions,
              {
                from: currentStatus.admin_status_label,
                to: nextStatus.admin_status_label,
                reason: "No pharmacy integration configured",
              },
            ],
          };
        }
      }
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message: "No pharmacy integration and no next status configured",
        transitions,
      };
    }

    if (!sendResult.sent) {
      console.warn(
        "sendToPharmacyRecipients failed; transitioning order to order_on_hold",
        { requestId, orderId, message: sendResult.message },
      );
      return transitionOrderToFailureStatus({
        supabase,
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
        orderNumber: typedOrder.order_number,
        currentStatus,
        message: sendResult.message || "Pharmacy send failed",
        failureHistoryNote:
          `sendToPharmacyRecipients failed: ${sendResult.message}`,
        transitions,
      });
    }

    // Sent successfully – order stays at order_approved waiting for
    // Telegra prescription_sent_to_pharmacy webhook → advances to order_sent_to_pharmacy
    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "no_change",
      message:
        "Prescription sent to pharmacy via Telegra; waiting for prescription_sent_to_pharmacy webhook",
      transitions,
    };
  }

  // Handle order_on_hold – retry sendToPharmacyRecipients; on success transition back to
  // order_approved so the prescription_sent_to_pharmacy webhook can advance normally.
  if (currentStatus?.status_key === "order_on_hold") {
    console.info("Order is on hold; retrying sendToPharmacyRecipients", {
      requestId,
      orderId,
    });

    const retryResult = await sendTelegraOrderToPharmacyForLifecycle({
      supabase,
      order: typedOrder,
      requestId,
    });

    console.info("order_on_hold retry sendToPharmacyRecipients result", {
      requestId,
      orderId,
      applicable: retryResult.applicable,
      sent: retryResult.sent,
      message: retryResult.message,
    });

    if (!retryResult.applicable) {
      // No pharmacy integration – advance via next_status_id (order_approved)
      const nextStatus = await getNextStatus(supabase, currentStatus);
      if (nextStatus) {
        const { error: updateError } = await supabase
          .from("orders")
          .update({
            status_id: nextStatus.id,
            status_changed_at: dateTime().toISOString(),
          })
          .eq("id", orderId)
          .eq("tenant_id", typedOrder.tenant_id);
        if (!updateError) {
          await supabase.from("order_status_history").insert({
            order_id: orderId,
            status_id: nextStatus.id,
            notes:
              "Auto-advanced from order_on_hold: no pharmacy integration configured",
          });
          return {
            orderId: typedOrder.id,
            orderNumber: typedOrder.order_number,
            previousStatus: currentStatus.admin_status_label,
            newStatus: nextStatus.admin_status_label,
            action: "advanced",
            message:
              "No pharmacy integration; auto-advanced past order_on_hold",
            transitions: [
              ...transitions,
              {
                from: currentStatus.admin_status_label,
                to: nextStatus.admin_status_label,
                reason: "No pharmacy integration configured",
              },
            ],
          };
        }
      }
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "no_change",
        message: "No pharmacy integration and no next status configured",
        transitions,
      };
    }

    if (!retryResult.sent) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message: retryResult.message || "Pharmacy retry send failed",
        transitions,
      };
    }

    // Retry sent successfully – restore to order_approved to wait for webhook
    const approvedStatus = await getNextStatus(supabase, currentStatus);
    if (approvedStatus) {
      const { error: restoreError } = await supabase
        .from("orders")
        .update({
          status_id: approvedStatus.id,
          status_changed_at: dateTime().toISOString(),
        })
        .eq("id", orderId)
        .eq("tenant_id", typedOrder.tenant_id);
      if (!restoreError) {
        await supabase.from("order_status_history").insert({
          order_id: orderId,
          status_id: approvedStatus.id,
          notes:
            "Pharmacy retry succeeded; restored to order_approved awaiting webhook",
        });
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: approvedStatus.admin_status_label,
          action: "advanced",
          message:
            "Pharmacy retry succeeded; waiting for prescription_sent_to_pharmacy webhook",
          transitions: [
            ...transitions,
            {
              from: currentStatus.admin_status_label,
              to: approvedStatus.admin_status_label,
              reason: "Pharmacy retry succeeded",
            },
          ],
        };
      }
    }

    return {
      orderId: typedOrder.id,
      orderNumber: typedOrder.order_number,
      previousStatus: currentStatus.admin_status_label,
      newStatus: null,
      action: "error",
      message:
        "Pharmacy retry sent but failed to restore order to order_approved",
      transitions,
    };
  }

  if (currentStatus?.status_key === "in_transit") {
    console.info(
      "Order is in in_transit status, checking whether tracking URL needs to be populated",
      {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
        hasTrackingNumber: Boolean(typedOrder.tracking_number),
        hasTrackingUrl: Boolean(typedOrder.tracking_url),
      },
    );

    if (typedOrder.tracking_url) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: transitions.length > 0 ? "advanced" : "no_change",
        message: transitions.length > 0
          ? `Order advanced through ${transitions.length} status(es)`
          : "Tracking URL already populated",
        transitions,
      };
    }

    if (!typedOrder.tracking_number) {
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: transitions.length > 0 ? "advanced" : "no_change",
        message: transitions.length > 0
          ? `Order advanced through ${transitions.length} status(es)`
          : "Tracking URL cannot be populated without a tracking number",
        transitions,
      };
    }

    try {
      const easyPostIntegration =
        await resolveTenantEasyPostShippingIntegration(
          supabase,
          typedOrder.tenant_id,
        );

      if (!easyPostIntegration) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: transitions.length > 0 ? "advanced" : "no_change",
          message: transitions.length > 0
            ? `Order advanced through ${transitions.length} status(es)`
            : "No supported shipping integration available to populate tracking URL",
          transitions,
        };
      }

      const trackingDetails = await getTrackingDetailsFromEasyPost({
        apiKey: easyPostIntegration.apiKey,
        trackingNumber: typedOrder.tracking_number,
        carrier: easyPostIntegration.carrier,
      });
      const trackingUrl = trackingDetails?.trackingUrl ?? null;
      const isDelivered = trackingDetails?.status === "delivered";

      if (!trackingUrl && !isDelivered) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: transitions.length > 0 ? "advanced" : "no_change",
          message: transitions.length > 0
            ? `Order advanced through ${transitions.length} status(es)`
            : "Shipping integration did not return a tracking update",
          transitions,
        };
      }

      const updatePayload: Record<string, unknown> = {};
      if (trackingUrl && trackingUrl !== typedOrder.tracking_url) {
        updatePayload.tracking_url = trackingUrl;
      }

      let nextStatus: OrderStatus | null = null;
      if (isDelivered) {
        nextStatus = await getStatusByKey(supabase, "delivered");

        if (!nextStatus) {
          return {
            orderId: typedOrder.id,
            orderNumber: typedOrder.order_number,
            previousStatus: currentStatus.admin_status_label,
            newStatus: null,
            action: "error",
            message: "Delivered status is not configured",
            transitions,
          };
        }

        updatePayload.status_id = nextStatus.id;
        updatePayload.status_changed_at = new Date().toISOString();
        updatePayload.delivered_at = typedOrder.delivered_at ||
          trackingDetails?.updatedAt ||
          new Date().toISOString();
      }

      if (Object.keys(updatePayload).length > 0) {
        const { error: updateError } = await supabase
          .from("orders")
          .update(updatePayload)
          .eq("id", typedOrder.id);

        if (updateError) {
          console.error("Failed to update order from shipping integration", {
            requestId,
            orderId,
            error: updateError.message,
          });
          return {
            orderId: typedOrder.id,
            orderNumber: typedOrder.order_number,
            previousStatus: currentStatus.admin_status_label,
            newStatus: null,
            action: "error",
            message:
              `Failed to update order from shipping integration: ${updateError.message}`,
            transitions,
          };
        }
      }

      if (nextStatus) {
        await supabase.from("order_status_history").insert({
          order_id: typedOrder.id,
          status_id: nextStatus.id,
          notes:
            "Auto-advanced: shipping integration reported delivered status",
        });

        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: nextStatus.admin_status_label,
          action: "advanced",
          message: "Order marked as delivered from shipping integration update",
          transitions: [
            ...transitions,
            {
              from: currentStatus.admin_status_label,
              to: nextStatus.admin_status_label,
              reason: "Shipping integration reported delivered",
            },
          ],
        };
      }

      if (Object.keys(updatePayload).length === 0) {
        return {
          orderId: typedOrder.id,
          orderNumber: typedOrder.order_number,
          previousStatus: currentStatus.admin_status_label,
          newStatus: null,
          action: transitions.length > 0 ? "advanced" : "no_change",
          message: transitions.length > 0
            ? `Order advanced through ${transitions.length} status(es)`
            : "No shipping integration changes detected",
          transitions,
        };
      }

      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: currentStatus.admin_status_label,
        action: "advanced",
        message: "Tracking URL populated from shipping integration",
        transitions: [
          ...transitions,
          {
            from: currentStatus.admin_status_label,
            to: currentStatus.admin_status_label,
            reason: "Tracking URL populated from shipping integration",
          },
        ],
      };
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      console.error("Failed to populate tracking URL for in_transit order", {
        requestId,
        orderId,
        tenantId: typedOrder.tenant_id,
        trackingNumber: typedOrder.tracking_number,
        error: message,
      });
      return {
        orderId: typedOrder.id,
        orderNumber: typedOrder.order_number,
        previousStatus: currentStatus.admin_status_label,
        newStatus: null,
        action: "error",
        message: `Failed to populate tracking URL: ${message}`,
        transitions,
      };
    }
  }

  // No automation rules matched for current status
  return {
    orderId: typedOrder.id,
    orderNumber: typedOrder.order_number,
    previousStatus: currentStatus?.admin_status_label || null,
    newStatus: null,
    action: transitions.length > 0 ? "advanced" : "no_change",
    message: transitions.length > 0
      ? `Order advanced through ${transitions.length} status(es)`
      : "No automation rules for current status",
    transitions,
  };
}

/**
 * Process all active (non-terminal) orders
 */
async function processAllActiveOrders(
  supabase: SupabaseClient,
  requestId: string,
): Promise<ProcessingResult[]> {
  // Get all terminal status IDs to exclude
  const { data: terminalStatuses, error: statusError } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("is_terminal", true);

  if (statusError) {
    console.error("Error fetching terminal statuses", {
      requestId,
      error: statusError.message,
    });
    throw new Error(
      `Failed to fetch terminal statuses: ${statusError.message}`,
    );
  }

  const terminalStatusIds = (terminalStatuses || []).map(
    (s: { id: string }) => s.id,
  );

  // Fetch all non-terminal orders
  let query = supabase.from("orders").select("id").not("status_id", "is", null);

  // Exclude terminal orders if we have terminal statuses
  if (terminalStatusIds.length > 0) {
    query = query.not("status_id", "in", `(${terminalStatusIds.join(",")})`);
  }

  const { data: orders, error: ordersError } = await query;

  if (ordersError) {
    console.error("Error fetching active orders", {
      requestId,
      error: ordersError.message,
    });
    throw new Error(`Failed to fetch active orders: ${ordersError.message}`);
  }

  console.info("Found active orders to process", {
    requestId,
    count: orders?.length || 0,
  });

  if (!orders || orders.length === 0) {
    return [];
  }

  // Process each order
  const results: ProcessingResult[] = [];
  for (const order of orders) {
    const result = await processOrder(supabase, order.id, requestId);
    results.push(result);
  }

  return results;
}
