import { createClient, SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { dateTime } from "../_shared/dayjs.ts";
import {
  getTrackingUrlFromEasyPost,
  resolveTenantEasyPostShippingIntegration,
} from "../_shared/shipping.ts";
import {
  getTelegraWebhookOrderIdDiagnostics,
  mapTelegraEventToOrderStatus,
  type NormalizedTelegraWebhookEvent,
  normalizeTelegraWebhookEvent,
} from "./helpers.ts";
import { explainTelegraOrderStatusTransitionDecision } from "./status-transitions.ts";

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, telegramd-signature, TelegraMD-Signature",
    methods: "POST, OPTIONS",
  });
}

type SupabaseAdminClient = SupabaseClient<any, "public", any>;

interface OrderStatusRow {
  id: string;
  status_key: string;
  display_order: number;
  is_terminal: boolean;
}

interface OrderRow {
  id: string;
  tenant_id: string;
  status_id: string | null;
  provider_platform_order_id: string | null;
  cancellation_reason: string | null;
  tracking_number: string | null;
  tracking_url: string | null;
  shipped_at: string | null;
  delivered_at: string | null;
  cancelled_at: string | null;
  idv_locked_at: string | null;
  order_statuses: OrderStatusRow | null;
}

interface TenantIntegrationRow {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
  settings: Record<string, unknown> | null;
}

function jsonResponse(
  req: Request,
  body: Record<string, unknown>,
  status = 200,
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...getCorsHeaders(req), "Content-Type": "application/json" },
  });
}

function getSupabaseAdminClient(requestId: string): SupabaseAdminClient {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    throw new Error("Missing Supabase configuration");
  }

  return createClient(supabaseUrl, supabaseServiceKey, {
    global: {
      headers: {
        "x-request-id": requestId,
        "x-request-source": "telegra-webhook",
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}

async function fetchOrderById(
  supabase: SupabaseAdminClient,
  orderId: string,
): Promise<OrderRow | null> {
  const { data, error } = await supabase
    .from("orders")
    .select(
      `
      id,
      tenant_id,
      status_id,
      provider_platform_order_id,
      cancellation_reason,
      tracking_number,
      tracking_url,
      shipped_at,
      delivered_at,
      cancelled_at,
      idv_locked_at,
      order_statuses (
        id,
        status_key,
        display_order,
        is_terminal
      )
    `,
    )
    .eq("id", orderId)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch order: ${error.message}`);
  }

  return data as OrderRow | null;
}

async function resolveOrderFromProviderOrderId(
  supabase: SupabaseAdminClient,
  providerOrderId: string,
): Promise<OrderRow | null> {
  const normalizedProviderOrderId = providerOrderId.trim();
  if (!normalizedProviderOrderId.startsWith("order::")) {
    return null;
  }

  const { data, error } = await supabase
    .from("orders")
    .select("id")
    .eq("provider_platform_order_id", normalizedProviderOrderId)
    .limit(2);

  if (error) {
    throw new Error(
      `Failed to resolve order from provider order id: ${error.message}`,
    );
  }

  if (!data || data.length === 0) return null;
  if (data.length > 1) {
    throw new Error(
      "Multiple orders matched the same Telegra provider order id",
    );
  }

  return await fetchOrderById(supabase, data[0].id as string);
}

async function fetchTelegraIntegrationForTenant(
  supabase: SupabaseAdminClient,
  tenantId: string,
): Promise<TenantIntegrationRow | null> {
  const { data, error } = await supabase
    .from("tenant_integrations")
    .select("id, tenant_id, integration_key, is_enabled, settings")
    .eq("tenant_id", tenantId)
    .eq("integration_key", "telegramd")
    .eq("is_enabled", true)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch Telegra integration: ${error.message}`);
  }

  return data as TenantIntegrationRow | null;
}

async function triggerOrderLifecycleForOrder(params: {
  orderId: string;
  tenantId: string;
  requestId: string;
}): Promise<boolean> {
  const { orderId, tenantId, requestId } = params;
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    console.warn("Unable to trigger order-lifecycle: missing env config", {
      requestId,
      orderId,
      tenantId,
    });
    return false;
  }

  const orderLifecycleUrl = `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;

  try {
    const response = await fetch(orderLifecycleUrl, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${supabaseServiceKey}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-request-source": "telegra-webhook:status_changed",
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.warn("Failed to trigger order-lifecycle after Telegra webhook", {
        requestId,
        orderId,
        tenantId,
        status: response.status,
        error: errorText,
      });
      return false;
    }

    console.info("Triggered order-lifecycle after Telegra webhook", {
      requestId,
      orderId,
      tenantId,
      status: response.status,
    });
    return true;
  } catch (error) {
    console.warn("Error triggering order-lifecycle after Telegra webhook", {
      requestId,
      orderId,
      tenantId,
      error: error instanceof Error ? error.message : String(error),
    });
    return false;
  }
}

async function fetchOrderStatus(
  supabase: SupabaseAdminClient,
  statusKey: string,
): Promise<OrderStatusRow | null> {
  const { data, error } = await supabase
    .from("order_statuses")
    .select("id, status_key, display_order, is_terminal")
    .eq("status_key", statusKey)
    .eq("is_active", true)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch order status: ${error.message}`);
  }

  return data as OrderStatusRow | null;
}

function getDeferredCancellationTargetStatusKey(params: {
  order: OrderRow;
  targetStatus: OrderStatusRow | null;
}): string | null {
  const { order, targetStatus } = params;

  if (!order.cancellation_reason || order.cancelled_at || !targetStatus) {
    return null;
  }

  if (
    targetStatus.status_key === "provider_approved" ||
    targetStatus.status_key === "payment_pending" ||
    targetStatus.status_key === "order_sent_to_pharmacy" ||
    targetStatus.status_key === "in_transit" ||
    targetStatus.status_key === "delivered"
  ) {
    return "order_pending_cancellation";
  }

  return null;
}

function buildDeferredCancellationWebhookNote(params: {
  originalTargetStatus: OrderStatusRow;
}): string {
  const { originalTargetStatus } = params;

  if (originalTargetStatus.status_key === "provider_approved") {
    return "Telegra webhook received; provider approved, but order has a pending cancellation request, so it was rerouted to order_pending_cancellation.";
  }

  if (originalTargetStatus.status_key === "order_sent_to_pharmacy") {
    return "Telegra webhook received; prescription was sent to pharmacy, but order has a pending cancellation request, so it was rerouted to order_pending_cancellation.";
  }

  if (
    originalTargetStatus.status_key === "in_transit" ||
    originalTargetStatus.status_key === "delivered"
  ) {
    return "Telegra webhook received; fulfillment progression arrived, but order has a pending cancellation request, so it was rerouted to order_pending_cancellation.";
  }

  return "Telegra webhook received; payment progression arrived, but order has a pending cancellation request, so it was rerouted to order_pending_cancellation.";
}

function buildWebhookNote(params: {
  rawType: string | null;
  rawStatus: string | null;
  rawTargetEntityStatus: string | null;
  providerOrderId: string | null;
  trackingNumber: string | null;
}): string {
  const parts = ["Telegra webhook received"];

  if (params.rawType) parts.push(`event=${params.rawType}`);
  if (params.rawStatus) parts.push(`status=${params.rawStatus}`);
  if (params.rawTargetEntityStatus) {
    parts.push(`targetEntity.status=${params.rawTargetEntityStatus}`);
  }
  if (params.providerOrderId) {
    parts.push(`providerOrderId=${params.providerOrderId}`);
  }
  if (params.trackingNumber) parts.push(`tracking=${params.trackingNumber}`);

  return parts.join("; ");
}

async function upsertOrderProviderPlatformLink(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  tenantIntegrationId: string;
  providerOrderId: string | null;
  rawType: string | null;
  rawStatus: string | null;
  rawTargetEntityStatus: string | null;
}): Promise<void> {
  const {
    supabase,
    order,
    tenantIntegrationId,
    providerOrderId,
    rawType,
    rawStatus,
    rawTargetEntityStatus,
  } = params;

  const { data: existingLink, error: existingLinkError } = await supabase
    .from("order_provider_platform_links")
    .select("metadata")
    .eq("order_id", order.id)
    .eq("tenant_integration_id", tenantIntegrationId)
    .maybeSingle();

  if (existingLinkError) {
    throw new Error(
      `Failed to fetch existing Telegra order link: ${existingLinkError.message}`,
    );
  }

  const existingMetadata =
    existingLink?.metadata && typeof existingLink.metadata === "object"
      ? (existingLink.metadata as Record<string, unknown>)
      : {};

  const payload: Record<string, unknown> = {
    tenant_id: order.tenant_id,
    order_id: order.id,
    tenant_integration_id: tenantIntegrationId,
    metadata: {
      ...existingMetadata,
      source: "telegra-webhook",
      last_event_type: rawType,
      last_event_status: rawStatus,
      last_event_target_entity_status: rawTargetEntityStatus,
      last_received_at: dateTime().toISOString(),
    },
  };

  if (providerOrderId) {
    payload.provider_order_id = providerOrderId;
  }

  const { error } = await supabase
    .from("order_provider_platform_links")
    .upsert(payload, {
      onConflict: "order_id,tenant_integration_id",
      ignoreDuplicates: false,
    });

  if (error) {
    throw new Error(`Failed to upsert Telegra order link: ${error.message}`);
  }

  if (providerOrderId) {
    const { error: orderUpdateError } = await supabase
      .from("orders")
      .update({
        provider_platform_order_id: providerOrderId,
      })
      .eq("id", order.id)
      .eq("tenant_id", order.tenant_id);

    if (orderUpdateError) {
      throw new Error(
        `Failed to persist Telegra provider order id on order: ${orderUpdateError.message}`,
      );
    }
  }
}

async function resolveTargetStatus(params: {
  supabase: SupabaseAdminClient;
  event: NormalizedTelegraWebhookEvent;
  requestId: string;
  order?: OrderRow | null;
}): Promise<{
  targetStatusKey: string | null;
  targetStatus: OrderStatusRow | null;
}> {
  const { supabase, event, requestId, order } = params;
  const targetStatusKey = mapTelegraEventToOrderStatus(event);
  const targetStatus = targetStatusKey
    ? await fetchOrderStatus(supabase, targetStatusKey)
    : null;

  console.info("Telegra webhook target status resolution", {
    requestId,
    orderId: order?.id ?? null,
    currentStatus: order?.order_statuses?.status_key ?? null,
    rawType: event.rawType,
    normalizedType: event.normalizedType,
    rawStatus: event.rawStatus,
    normalizedStatus: event.normalizedStatus,
    rawTargetEntityStatus: event.rawTargetEntityStatus,
    normalizedTargetEntityStatus: event.normalizedTargetEntityStatus,
    targetStatusKey,
    resolvedTargetStatus: targetStatus?.status_key ?? null,
  });

  if (targetStatusKey && !targetStatus) {
    throw new Error(`Mapped Telegra status not found: ${targetStatusKey}`);
  }

  return { targetStatusKey, targetStatus };
}

async function persistOrderUpdate(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  updatePayload: Record<string, unknown>;
}): Promise<void> {
  const { supabase, order, updatePayload } = params;

  if (Object.keys(updatePayload).length === 0) return;

  const { error: orderUpdateError } = await supabase
    .from("orders")
    .update(updatePayload)
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id);

  if (orderUpdateError) {
    throw new Error(
      `Failed to update order from Telegra webhook: ${orderUpdateError.message}`,
    );
  }
}

async function appendOrderStatusHistory(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  targetStatus: OrderStatusRow;
  event: NormalizedTelegraWebhookEvent;
  note?: string;
  requestId: string;
}): Promise<void> {
  const { supabase, order, targetStatus, event, note, requestId } = params;

  const { error: historyError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: order.id,
      status_id: targetStatus.id,
      notes:
        note ||
        buildWebhookNote({
          rawType: event.rawType,
          rawStatus: event.rawStatus,
          rawTargetEntityStatus: event.rawTargetEntityStatus,
          providerOrderId: event.providerOrderId,
          trackingNumber: event.trackingNumber,
        }),
    });

  if (historyError) {
    console.warn("Failed to append order status history for Telegra webhook", {
      requestId,
      orderId: order.id,
      error: historyError.message,
    });
  }
}

async function finalizeWebhookEvent(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  event: NormalizedTelegraWebhookEvent;
  historyNote?: string;
  requestId: string;
  targetStatus?: OrderStatusRow | null;
  updatePayload?: Record<string, unknown>;
}): Promise<{ targetStatus: OrderStatusRow | null; statusChanged: boolean }> {
  const {
    supabase,
    order,
    event,
    historyNote,
    requestId,
    targetStatus: providedTargetStatus,
    updatePayload = {},
  } = params;
  const targetStatus =
    providedTargetStatus ??
    (
      await resolveTargetStatus({
        supabase,
        event,
        requestId,
        order,
      })
    ).targetStatus;
  const nextUpdatePayload = { ...updatePayload };
  const nowIso = dateTime().toISOString();

  let statusChanged = false;
  const transitionDecision = targetStatus
    ? explainTelegraOrderStatusTransitionDecision(
        order.order_statuses,
        targetStatus,
        event,
      )
    : null;

  console.info("Telegra webhook transition decision", {
    requestId,
    orderId: order.id,
    currentStatus: order.order_statuses?.status_key ?? null,
    targetStatus: targetStatus?.status_key ?? null,
    rawType: event.rawType,
    normalizedType: event.normalizedType,
    rawStatus: event.rawStatus,
    normalizedStatus: event.normalizedStatus,
    rawTargetEntityStatus: event.rawTargetEntityStatus,
    normalizedTargetEntityStatus: event.normalizedTargetEntityStatus,
    shouldAdvance: transitionDecision?.shouldAdvance ?? false,
    reason: transitionDecision?.reason ?? "no_target_status",
    pendingUpdateFields: Object.keys(nextUpdatePayload),
  });

  if (targetStatus && transitionDecision?.shouldAdvance) {
    nextUpdatePayload.status_id = targetStatus.id;
    nextUpdatePayload.status_changed_at = nowIso;
    statusChanged = true;
  }

  await persistOrderUpdate({
    supabase,
    order,
    updatePayload: nextUpdatePayload,
  });

  if (statusChanged && targetStatus) {
    await appendOrderStatusHistory({
      supabase,
      order,
      targetStatus,
      event,
      note: historyNote,
      requestId,
    });
  }

  console.info("Telegra webhook finalize result", {
    requestId,
    orderId: order.id,
    previousStatus: order.order_statuses?.status_key ?? null,
    targetStatus: targetStatus?.status_key ?? null,
    statusChanged,
    persistedUpdateFields: Object.keys(nextUpdatePayload),
  });

  return { targetStatus, statusChanged };
}

async function handleWebhookEvent(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  event: NormalizedTelegraWebhookEvent;
  requestId: string;
}): Promise<{ targetStatus: OrderStatusRow | null; statusChanged: boolean }> {
  const { supabase, order, event, requestId } = params;
  const { targetStatus } = await resolveTargetStatus({
    supabase,
    event,
    requestId,
    order,
  });
  let effectiveTargetStatus = targetStatus;
  let historyNote: string | undefined;
  const nowIso = dateTime().toISOString();
  const updatePayload: Record<string, unknown> = {};
  let trackingUrl = event.trackingUrl;

  const deferredCancellationTargetStatusKey =
    getDeferredCancellationTargetStatusKey({
      order,
      targetStatus,
    });

  if (deferredCancellationTargetStatusKey && targetStatus) {
    const deferredCancellationTargetStatus = await fetchOrderStatus(
      supabase,
      deferredCancellationTargetStatusKey,
    );

    if (!deferredCancellationTargetStatus) {
      throw new Error(
        `Mapped deferred cancellation status not found: ${deferredCancellationTargetStatusKey}`,
      );
    }

    effectiveTargetStatus = deferredCancellationTargetStatus;
    historyNote = buildDeferredCancellationWebhookNote({
      originalTargetStatus: targetStatus,
    });

    console.info("Telegra webhook rerouted to pending cancellation", {
      requestId,
      orderId: order.id,
      currentStatus: order.order_statuses?.status_key ?? null,
      originalTargetStatus: targetStatus.status_key,
      effectiveTargetStatus: effectiveTargetStatus.status_key,
      cancellationReasonPresent: Boolean(order.cancellation_reason),
    });
  }

  if (
    !trackingUrl &&
    event.trackingNumber &&
    event.normalizedType === "shipping_details_set"
  ) {
    try {
      const easyPostIntegration =
        await resolveTenantEasyPostShippingIntegration(
          supabase,
          order.tenant_id,
        );

      if (easyPostIntegration) {
        trackingUrl = await getTrackingUrlFromEasyPost({
          apiKey: easyPostIntegration.apiKey,
          trackingNumber: event.trackingNumber,
          carrier: easyPostIntegration.carrier,
        });
      }
    } catch (error) {
      console.warn(
        "Failed to resolve tracking URL from EasyPost for Telegra webhook",
        {
          requestId,
          orderId: order.id,
          tenantId: order.tenant_id,
          trackingNumber: event.trackingNumber,
          error: error instanceof Error ? error.message : String(error),
        },
      );
    }
  }

  if (event.trackingNumber && event.trackingNumber !== order.tracking_number) {
    updatePayload.tracking_number = event.trackingNumber;
  }

  if (trackingUrl && trackingUrl !== order.tracking_url) {
    updatePayload.tracking_url = trackingUrl;
  }

  if (effectiveTargetStatus?.status_key === "in_transit" && !order.shipped_at) {
    updatePayload.shipped_at = event.shippedAt || event.occurredAt || nowIso;
  }

  if (effectiveTargetStatus?.status_key === "delivered") {
    updatePayload.delivered_at =
      event.deliveredAt || order.delivered_at || event.occurredAt || nowIso;

    if (!order.shipped_at) {
      updatePayload.shipped_at = event.shippedAt || event.occurredAt || nowIso;
    }
  }

  if (
    effectiveTargetStatus?.status_key === "order_cancelled" &&
    !order.cancelled_at
  ) {
    updatePayload.cancelled_at =
      event.cancelledAt || event.occurredAt || nowIso;
  }

  console.info("Telegra webhook prepared order updates", {
    requestId,
    orderId: order.id,
    currentStatus: order.order_statuses?.status_key ?? null,
    targetStatus: effectiveTargetStatus?.status_key ?? null,
    trackingNumberChanged:
      Boolean(event.trackingNumber) &&
      event.trackingNumber !== order.tracking_number,
    trackingUrlChanged:
      Boolean(trackingUrl) && trackingUrl !== order.tracking_url,
    preparedUpdateFields: Object.keys(updatePayload),
  });

  return await finalizeWebhookEvent({
    supabase,
    order,
    event,
    historyNote,
    requestId,
    targetStatus: effectiveTargetStatus,
    updatePayload,
  });
}

Deno.serve(async (req) => {
  const requestId = crypto.randomUUID();
  const corsHeaders = getCorsHeaders(req);

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  if (req.method !== "POST") {
    return jsonResponse(req, { error: "Method not allowed", requestId }, 405);
  }

  console.info("=== Telegra Webhook Request Received ===", {
    requestId,
    timestamp: dateTime().toISOString(),
    method: req.method,
    url: req.url,
  });

  try {
    const supabase = getSupabaseAdminClient(requestId);
    const rawBody = await req.text();
    if (!rawBody.trim()) {
      return jsonResponse(
        req,
        { error: "Request body is required", requestId },
        400,
      );
    }

    let payload: unknown;
    try {
      payload = JSON.parse(rawBody);
    } catch {
      return jsonResponse(
        req,
        { error: "Invalid JSON payload", requestId },
        400,
      );
    }

    const normalizedEvent = normalizeTelegraWebhookEvent(payload);
    const orderIdDiagnostics = getTelegraWebhookOrderIdDiagnostics(payload);

    console.info("Telegra webhook normalized payload", {
      requestId,
      rawType: normalizedEvent.rawType,
      normalizedType: normalizedEvent.normalizedType,
      rawStatus: normalizedEvent.rawStatus,
      normalizedStatus: normalizedEvent.normalizedStatus,
      rawTargetEntityStatus: normalizedEvent.rawTargetEntityStatus,
      normalizedTargetEntityStatus:
        normalizedEvent.normalizedTargetEntityStatus,
      providerOrderId: normalizedEvent.providerOrderId,
      occurredAt: normalizedEvent.occurredAt,
      shippedAt: normalizedEvent.shippedAt,
      deliveredAt: normalizedEvent.deliveredAt,
      cancelledAt: normalizedEvent.cancelledAt,
    });

    console.info("Telegra webhook order ID extraction", {
      requestId,
      rawType: normalizedEvent.rawType,
      normalizedType: normalizedEvent.normalizedType,
      selectedProviderOrderId: orderIdDiagnostics.providerOrderId.selectedValue,
      selectedProviderOrderIdPath:
        orderIdDiagnostics.providerOrderId.selectedPath,
      providerOrderIdAttempts: orderIdDiagnostics.providerOrderId.attempts,
    });

    if (
      normalizedEvent.normalizedType === "prescription_approved_by_practitioner"
    ) {
      const payloadRecord =
        payload && typeof payload === "object"
          ? (payload as Record<string, unknown>)
          : null;
      const targetEntity =
        payloadRecord?.targetEntity &&
        typeof payloadRecord.targetEntity === "object"
          ? (payloadRecord.targetEntity as Record<string, unknown>)
          : null;
      const targetOrder =
        targetEntity?.order && typeof targetEntity.order === "object"
          ? (targetEntity.order as Record<string, unknown>)
          : null;
      const eventData =
        payloadRecord?.eventData && typeof payloadRecord.eventData === "object"
          ? (payloadRecord.eventData as Record<string, unknown>)
          : null;
      const performedBy =
        eventData?.performedBy && typeof eventData.performedBy === "object"
          ? (eventData.performedBy as Record<string, unknown>)
          : null;

      console.info("Telegra practitioner approval webhook received", {
        requestId,
        normalizedEvent,
        extractedIdentifiers: {
          targetEntityId: targetEntity?.id ?? null,
          targetEntityUnderscoreId: targetEntity?._id ?? null,
          targetOrderId: targetOrder?.id ?? null,
          targetOrderUnderscoreId: targetOrder?._id ?? null,
          performedByOrder: performedBy?.order ?? null,
        },
        payload,
      });
    }

    if (!normalizedEvent.providerOrderId) {
      console.warn("Telegra webhook order ID extraction failed", {
        requestId,
        rawType: normalizedEvent.rawType,
        normalizedType: normalizedEvent.normalizedType,
        providerOrderIdAttempts: orderIdDiagnostics.providerOrderId.attempts,
      });
      return jsonResponse(
        req,
        {
          error: "Webhook payload is missing a valid provider order id",
          requestId,
          received: false,
        },
        400,
      );
    }

    const resolutionStrategy = normalizedEvent.providerOrderId
      ? "orders.provider_platform_order_id"
      : "none";
    const resolvedOrder = normalizedEvent.providerOrderId
      ? await resolveOrderFromProviderOrderId(
          supabase,
          normalizedEvent.providerOrderId,
        )
      : null;

    console.info("Telegra webhook order resolution attempt", {
      requestId,
      rawType: normalizedEvent.rawType,
      normalizedType: normalizedEvent.normalizedType,
      resolutionStrategy,
      providerOrderId: normalizedEvent.providerOrderId,
      resolvedOrderId: resolvedOrder?.id ?? null,
      resolvedTenantId: resolvedOrder?.tenant_id ?? null,
    });

    if (!resolvedOrder) {
      return jsonResponse(
        req,
        {
          error: "Unable to resolve order from Telegra webhook payload",
          requestId,
          received: false,
        },
        404,
      );
    }

    const telegraIntegration = await fetchTelegraIntegrationForTenant(
      supabase,
      resolvedOrder.tenant_id,
    );

    if (!telegraIntegration) {
      return jsonResponse(
        req,
        {
          error: "No enabled Telegra integration configured for tenant",
          requestId,
          received: false,
        },
        400,
      );
    }

    if (
      normalizedEvent.normalizedType === "new_status_set_to_request" &&
      normalizedEvent.normalizedTargetEntityStatus !==
        "requires_order_processing" &&
      normalizedEvent.normalizedTargetEntityStatus !==
        "requires_provider_review"
    ) {
      console.info(
        "Telegra webhook ignored by new_status_set_to_request gate",
        {
          requestId,
          providerOrderId: normalizedEvent.providerOrderId,
          normalizedType: normalizedEvent.normalizedType,
          normalizedStatus: normalizedEvent.normalizedStatus,
          normalizedTargetEntityStatus:
            normalizedEvent.normalizedTargetEntityStatus,
          allowedTargetEntityStatuses: [
            "requires_order_processing",
            "requires_provider_review",
          ],
        },
      );
      return jsonResponse(req, {
        success: true,
        requestId,
        received: true,
        ignored: true,
        reason:
          "Ignoring new_status_set_to_request webhook events without a supported targetEntity.status",
        orderId: resolvedOrder.id,
        targetStatus: null,
        statusChanged: false,
      });
    }

    await upsertOrderProviderPlatformLink({
      supabase,
      order: resolvedOrder,
      tenantIntegrationId: telegraIntegration.id,
      providerOrderId: normalizedEvent.providerOrderId,
      rawType: normalizedEvent.rawType,
      rawStatus: normalizedEvent.rawStatus,
      rawTargetEntityStatus: normalizedEvent.rawTargetEntityStatus,
    });

    const { targetStatus, statusChanged } = await handleWebhookEvent({
      supabase,
      order: resolvedOrder,
      event: normalizedEvent,
      requestId,
    });

    // IDV lock detection: after setting id_verification_failed, check attempt count
    const IDV_MAX_ATTEMPTS = 3;
    if (
      statusChanged &&
      targetStatus?.status_key === "id_verification_failed"
    ) {
      try {
        const idvStatusRow = await fetchOrderStatus(
          supabase,
          "id_verification_failed",
        );
        if (idvStatusRow) {
          const { count: idvCount } = await supabase
            .from("order_status_history")
            .select("id", { count: "exact", head: true })
            .eq("order_id", resolvedOrder.id)
            .eq("status_id", idvStatusRow.id);

          const totalAttempts = idvCount ?? 0;
          if (
            totalAttempts >= IDV_MAX_ATTEMPTS &&
            !resolvedOrder.idv_locked_at
          ) {
            const { error: lockError } = await supabase
              .from("orders")
              .update({ idv_locked_at: dateTime().toISOString() })
              .eq("id", resolvedOrder.id);

            if (lockError) {
              console.warn("Failed to set idv_locked_at on order", {
                requestId,
                orderId: resolvedOrder.id,
                error: lockError.message,
              });
            } else {
              console.info("IDV locked: max attempts exceeded", {
                requestId,
                orderId: resolvedOrder.id,
                attempts: totalAttempts,
              });
            }
          }
        }
      } catch (idvLockError) {
        console.warn("IDV lock detection failed (non-fatal)", {
          requestId,
          orderId: resolvedOrder.id,
          error:
            idvLockError instanceof Error
              ? idvLockError.message
              : String(idvLockError),
        });
      }
    }

    const orderLifecycleTriggered = statusChanged
      ? await triggerOrderLifecycleForOrder({
          orderId: resolvedOrder.id,
          tenantId: resolvedOrder.tenant_id,
          requestId,
        })
      : false;

    return jsonResponse(req, {
      success: true,
      requestId,
      received: true,
      orderId: resolvedOrder.id,
      targetStatus: targetStatus?.status_key || null,
      statusChanged,
      orderLifecycleTriggered,
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error("Telegra webhook failed", {
      requestId,
      error: message,
    });

    return jsonResponse(
      req,
      {
        error: "Internal server error",
        message,
        requestId,
      },
      500,
    );
  }
});
