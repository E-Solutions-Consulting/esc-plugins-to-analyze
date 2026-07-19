import { createClient, SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  buildLifeFileWebhookNote,
  dateTime,
  fetchOrderStatusByKey,
  mapLifeFileRxStatusToOrderStatusKey,
  resolveOrderFromLifeFilePayload,
  shouldAdvanceStatus,
  verifyLifeFileBasicAuth,
} from "./helpers.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-request-id",
    methods: "POST, OPTIONS",
  });
}

function jsonResponse(
  req: Request,
  body: Record<string, unknown>,
  status = 200,
  headers: Record<string, string> = {},
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      ...getCorsHeaders(req),
      "Content-Type": "application/json",
      ...headers,
    },
  });
}

function errorResponse(
  req: Request,
  code: string,
  message: string,
  status = 400,
  headers: Record<string, string> = {},
): Response {
  return jsonResponse(req, { error: { code, message } }, status, headers);
}

// deno-lint-ignore-next-line no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

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
        "x-request-source": "lifefile-webhook",
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}

function triggerOrderLifecycleAsync(
  orderId: string,
  tenantId: string,
  requestId: string,
): void {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) return;

  const url = `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;
  fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${supabaseServiceKey}`,
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-request-source": "lifefile-webhook:status_changed",
    },
  })
    .then((res) => {
      console.info("Triggered order-lifecycle after LifeFile webhook", {
        requestId,
        orderId,
        tenantId,
        status: res.status,
      });
    })
    .catch((err) => {
      console.warn("Failed to trigger order-lifecycle after LifeFile webhook", {
        requestId,
        orderId,
        tenantId,
        error: err instanceof Error ? err.message : String(err),
      });
    });
}

// ---------------------------------------------------------------------------
// Per-item processing result
// ---------------------------------------------------------------------------

interface ItemResult {
  lifefileOrderId: string | null;
  action: "advanced" | "skipped" | "error";
  previousStatusKey?: string | null;
  newStatusKey?: string | null;
  reason: string;
}

// ---------------------------------------------------------------------------
// Process a single LifeFile payload item
// ---------------------------------------------------------------------------

async function processLifeFileItem(
  supabase: SupabaseAdminClient,
  // deno-lint-ignore-next-line no-explicit-any
  item: Record<string, unknown>,
  requestId: string,
): Promise<ItemResult> {
  const lifefileOrderId =
    typeof item.orderId === "string" ? item.orderId.trim() || null : null;
  const lifefileFillId =
    typeof item.fillId === "string" ? item.fillId.trim() || null : null;
  const rxNumber =
    typeof item.rxNumber === "string" ? item.rxNumber.trim() || null : null;
  const rxStatus =
    typeof item.rxStatus === "string" ? item.rxStatus.trim() || null : null;
  const orderStatus =
    typeof item.orderStatus === "string"
      ? item.orderStatus.trim() || null
      : null;
  const orderReferenceId =
    typeof item.orderReferenceId === "string"
      ? item.orderReferenceId.trim() || null
      : null;
  const patientEmail =
    typeof item.patientEmail === "string"
      ? item.patientEmail.trim() || null
      : null;
  const trackingNumber =
    typeof item.trackingNumber === "string"
      ? item.trackingNumber.trim() || null
      : null;

  // ------------------------------------------------------------------
  // 1. Map rxStatus to our target status key (null = log-only / unknown)
  // ------------------------------------------------------------------
  const targetStatusKey = mapLifeFileRxStatusToOrderStatusKey(rxStatus);

  if (!targetStatusKey) {
    // Unrecognised rxStatus – acknowledge receipt but make no DB change.
    console.info("LifeFile rxStatus unrecognised (no state change)", {
      requestId,
      rxStatus,
      lifefileOrderId,
    });
    return {
      lifefileOrderId,
      action: "skipped",
      reason: `rxStatus '${rxStatus ?? "null"}' does not map to a state change`,
    };
  }

  // ------------------------------------------------------------------
  // 2. Resolve the internal order
  // ------------------------------------------------------------------
  const resolved = await resolveOrderFromLifeFilePayload(supabase, {
    orderReferenceId,
    patientEmail,
    requestId,
  });

  if (!resolved) {
    console.warn("Could not resolve order from LifeFile payload", {
      requestId,
      orderReferenceId,
      patientEmail,
      lifefileOrderId,
    });
    return {
      lifefileOrderId,
      action: "error",
      reason: "Could not correlate LifeFile payload to an internal order",
    };
  }

  const { order, correlationMethod } = resolved;
  const currentStatus = order.order_statuses;

  if (!currentStatus) {
    return {
      lifefileOrderId,
      action: "error",
      reason: `Order ${order.id} has no status set`,
    };
  }

  if (currentStatus.is_terminal) {
    return {
      lifefileOrderId,
      action: "skipped",
      previousStatusKey: currentStatus.status_key,
      reason: "Order is in a terminal state",
    };
  }

  // ------------------------------------------------------------------
  // 3. Fetch the target status row
  // ------------------------------------------------------------------
  const targetStatus = await fetchOrderStatusByKey(supabase, targetStatusKey);

  if (!targetStatus) {
    return {
      lifefileOrderId,
      action: "error",
      reason: `Target status key '${targetStatusKey}' not found in order_statuses`,
    };
  }

  // ------------------------------------------------------------------
  // 4. Forward-only guard
  // ------------------------------------------------------------------
  if (
    !shouldAdvanceStatus(
      currentStatus.display_order,
      targetStatus.display_order,
    )
  ) {
    console.info("LifeFile status update skipped (would not advance order)", {
      requestId,
      orderId: order.id,
      currentStatusKey: currentStatus.status_key,
      currentDisplayOrder: currentStatus.display_order,
      targetStatusKey,
      targetDisplayOrder: targetStatus.display_order,
    });
    return {
      lifefileOrderId,
      action: "skipped",
      previousStatusKey: currentStatus.status_key,
      newStatusKey: targetStatusKey,
      reason: "Target status does not advance the order (same or regressing)",
    };
  }

  // ------------------------------------------------------------------
  // 5. Build order update payload
  // ------------------------------------------------------------------
  const now = dateTime().toISOString();
  const orderUpdate: Record<string, unknown> = {
    status_id: targetStatus.id,
    status_changed_at: now,
  };

  if (targetStatusKey === "in_transit") {
    if (trackingNumber) orderUpdate.tracking_number = trackingNumber;
    orderUpdate.shipped_at = now;
  }

  // ------------------------------------------------------------------
  // 6. Persist: update orders, insert history, upsert pharmacy link
  // ------------------------------------------------------------------
  const { error: orderUpdateError } = await supabase
    .from("orders")
    .update(orderUpdate)
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id);

  if (orderUpdateError) {
    throw new Error(
      `Failed to update order ${order.id}: ${orderUpdateError.message}`,
    );
  }

  const note = buildLifeFileWebhookNote({
    rxStatus,
    orderStatus,
    lifefileOrderId,
    rxNumber,
    fillId: lifefileFillId,
    trackingNumber,
    correlationMethod,
  });

  const { error: historyError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: order.id,
      status_id: targetStatus.id,
      notes: note,
    });

  if (historyError) {
    // Non-fatal – log and continue.
    console.warn("Failed to insert order_status_history", {
      requestId,
      orderId: order.id,
      error: historyError.message,
    });
  }

  // Upsert pharmacy link for idempotency tracking.
  if (lifefileOrderId) {
    const { error: linkError } = await supabase
      .from("order_pharmacy_platform_links")
      .upsert(
        {
          order_id: order.id,
          tenant_id: order.tenant_id,
          lifefile_order_id: lifefileOrderId,
          lifefile_fill_id: lifefileFillId,
          rx_number: rxNumber,
          latest_rx_status: rxStatus,
          latest_order_status: orderStatus,
          metadata: {
            orderReferenceId: orderReferenceId ?? null,
            correlationMethod,
            lastReceivedAt: now,
            lastProcessedRxStatus: rxStatus,
            lastProcessedOrderStatus: orderStatus,
            trackingNumber: trackingNumber ?? null,
          },
          updated_at: now,
        },
        {
          onConflict: "order_id,lifefile_order_id",
          ignoreDuplicates: false,
        },
      );

    if (linkError) {
      console.warn("Failed to upsert order_pharmacy_platform_links", {
        requestId,
        orderId: order.id,
        lifefileOrderId,
        error: linkError.message,
      });
    }
  }

  // ------------------------------------------------------------------
  // 7. Fire-and-forget order-lifecycle trigger
  // ------------------------------------------------------------------
  triggerOrderLifecycleAsync(order.id, order.tenant_id, requestId);

  console.info("LifeFile order status advanced", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    previousStatusKey: currentStatus.status_key,
    newStatusKey: targetStatusKey,
    correlationMethod,
  });

  return {
    lifefileOrderId,
    action: "advanced",
    previousStatusKey: currentStatus.status_key,
    newStatusKey: targetStatusKey,
    reason: `Advanced from ${currentStatus.status_key} to ${targetStatusKey}`,
  };
}

// ---------------------------------------------------------------------------
// Payload normalisation
// ---------------------------------------------------------------------------

/**
 * LifeFile sends each webhook call as a single-element array: [{...}].
 * The combined test file is an array-of-arrays [[{...}],[{...}]].
 * We normalise both shapes into a flat array of item objects.
 */
function normalizePayload(parsed: unknown): Array<Record<string, unknown>> {
  if (!Array.isArray(parsed)) return [];

  const items: Array<Record<string, unknown>> = [];
  for (const entry of parsed) {
    if (Array.isArray(entry)) {
      for (const inner of entry) {
        if (inner && typeof inner === "object" && !Array.isArray(inner)) {
          items.push(inner as Record<string, unknown>);
        }
      }
    } else if (entry && typeof entry === "object" && !Array.isArray(entry)) {
      items.push(entry as Record<string, unknown>);
    }
  }
  return items;
}

Deno.serve(async (req: Request) => {
  const corsHeaders = getCorsHeaders(req);
  const url = new URL(req.url);
  let path = url.pathname.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/lifefile-webhook")) {
    path = path.slice("/lifefile-webhook".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) path = path.slice(0, -1);

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const requestId = req.headers.get("x-request-id") || crypto.randomUUID();

  if (path !== "/event") {
    return errorResponse(
      req,
      "NOT_FOUND",
      "LifeFile webhook route not found.",
      404,
      { "x-request-id": requestId },
    );
  }

  if (req.method !== "POST") {
    return errorResponse(
      req,
      "METHOD_NOT_ALLOWED",
      "Use POST for LifeFile event delivery.",
      405,
      { "x-request-id": requestId },
    );
  }

  // ---------------------------------------------------------------------------
  // Authentication – read Basic Auth credentials from tenant_integrations DB
  // ---------------------------------------------------------------------------
  const supabaseForAuth = getSupabaseAdminClient(requestId);
  const { data: lifefileIntegrations, error: intError } = await supabaseForAuth
    .from("tenant_integrations")
    .select("settings")
    .eq("integration_key", "lifefile")
    .eq("is_enabled", true);

  if (intError) {
    console.error("Failed to fetch lifefile tenant integrations", {
      requestId,
      error: intError.message,
    });
    return errorResponse(
      req,
      "SERVICE_MISCONFIGURED",
      "Webhook endpoint is not properly configured.",
      503,
      { "x-request-id": requestId },
    );
  }

  if (!lifefileIntegrations || lifefileIntegrations.length === 0) {
    console.warn("No enabled lifefile integration found; rejecting webhook", {
      requestId,
    });
    return errorResponse(
      "UNAUTHORIZED",
      "Invalid or missing credentials.",
      401,
      { "x-request-id": requestId },
    );
  }

  const authHeader = req.headers.get("authorization");
  const matchedIntegration = lifefileIntegrations.find((row) => {
    const s = row.settings as Record<string, unknown>;
    const u =
      typeof s?.webhook_username === "string" ? s.webhook_username : null;
    const p =
      typeof s?.webhook_password === "string" ? s.webhook_password : null;
    if (!u || !p) return false;
    return verifyLifeFileBasicAuth(authHeader, u, p);
  });

  if (!matchedIntegration) {
    return errorResponse(
      req,
      "UNAUTHORIZED",
      "Invalid or missing credentials.",
      401,
      { "x-request-id": requestId },
    );
  }

  // ---------------------------------------------------------------------------
  // Parse body
  // ---------------------------------------------------------------------------
  const contentType = req.headers.get("content-type") || "";
  const rawBody = await req.text();

  if (!rawBody.trim()) {
    return errorResponse(req, "EMPTY_BODY", "Request body is required.", 400, {
      "x-request-id": requestId,
    });
  }

  let parsedBody: unknown;
  try {
    parsedBody = JSON.parse(rawBody);
  } catch {
    return errorResponse(
      req,
      "INVALID_JSON",
      "Webhook body must be valid JSON.",
      400,
      { "x-request-id": requestId },
    );
  }

  const items = normalizePayload(parsedBody);

  if (items.length === 0) {
    console.warn("LifeFile webhook received with no processable items", {
      requestId,
      contentType,
      bodyLength: rawBody.length,
    });
    return jsonResponse(
      req,
      { received: true, processed: 0, skipped: 0, errors: 0, results: [] },
      200,
      { "x-request-id": requestId },
    );
  }

  console.info("Processing LifeFile webhook batch", {
    requestId,
    itemCount: items.length,
  });

  // ---------------------------------------------------------------------------
  // Process each item – per-item errors are isolated and logged
  // ---------------------------------------------------------------------------
  const supabase = getSupabaseAdminClient(requestId);
  const results: ItemResult[] = [];

  for (const item of items) {
    try {
      const result = await processLifeFileItem(supabase, item, requestId);
      results.push(result);
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      console.error("Unhandled error processing LifeFile item", {
        requestId,
        lifefileOrderId: item.orderId ?? null,
        error: message,
      });
      results.push({
        lifefileOrderId: typeof item.orderId === "string" ? item.orderId : null,
        action: "error",
        reason: message,
      });
    }
  }

  const advanced = results.filter((r) => r.action === "advanced").length;
  const skipped = results.filter((r) => r.action === "skipped").length;
  const errors = results.filter((r) => r.action === "error").length;

  console.info("LifeFile webhook batch complete", {
    requestId,
    total: results.length,
    advanced,
    skipped,
    errors,
  });

  return jsonResponse(
    req,
    {
      received: true,
      requestId,
      processed: results.length,
      advanced,
      skipped,
      errors,
      results,
    },
    200,
    { "x-request-id": requestId },
  );
});
