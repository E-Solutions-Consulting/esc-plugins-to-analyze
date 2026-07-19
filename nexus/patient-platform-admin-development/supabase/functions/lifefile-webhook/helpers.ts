import { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { dateTime } from "../_shared/dayjs.ts";

type SupabaseAdminClient = SupabaseClient<unknown, "public", unknown>;

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface OrderRow {
  id: string;
  tenant_id: string;
  status_id: string | null;
  tracking_number: string | null;
  tracking_url: string | null;
  shipped_at: string | null;
  order_statuses: OrderStatusRow | null;
}

export interface OrderStatusRow {
  id: string;
  status_key: string;
  display_order: number;
  is_terminal: boolean;
}

export interface ResolvedOrder {
  order: OrderRow;
  correlationMethod:
    | "provider_platform_link"
    | "prefix_swap"
    | "email_fallback";
}

// ---------------------------------------------------------------------------
// HTTP Basic Auth
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// HTTP Basic Auth
// ---------------------------------------------------------------------------

/**
 * Timing-safe comparison of two strings.
 * Prevents timing-oracle attacks on credential comparison.
 */
function timingSafeEqual(a: string, b: string): boolean {
  const encoder = new TextEncoder();
  const aBytes = encoder.encode(a);
  const bBytes = encoder.encode(b);

  if (aBytes.length !== bBytes.length) {
    // Compare anyway to avoid length-based timing leaks, then return false.
    let result = 1;
    const len = Math.min(aBytes.length, bBytes.length);
    for (let i = 0; i < len; i++) {
      result |= aBytes[i] ^ bBytes[i];
    }
    return false;
  }

  let result = 0;
  for (let i = 0; i < aBytes.length; i++) {
    result |= aBytes[i] ^ bBytes[i];
  }
  return result === 0;
}

/**
 * Verifies an HTTP Basic Auth header against expected credentials.
 * Returns true only when both username and password match exactly.
 */
export function verifyLifeFileBasicAuth(
  authHeader: string | null,
  expectedUsername: string,
  expectedPassword: string,
): boolean {
  if (!authHeader) return false;

  const prefix = "basic ";
  const lower = authHeader.toLowerCase();
  if (!lower.startsWith(prefix)) return false;

  const base64Credentials = authHeader.slice(prefix.length).trim();
  if (!base64Credentials) return false;

  let decoded: string;
  try {
    decoded = atob(base64Credentials);
  } catch {
    return false;
  }

  const colonIndex = decoded.indexOf(":");
  if (colonIndex === -1) return false;

  const username = decoded.slice(0, colonIndex);
  const password = decoded.slice(colonIndex + 1);

  return (
    timingSafeEqual(username, expectedUsername) &&
    timingSafeEqual(password, expectedPassword)
  );
}

// ---------------------------------------------------------------------------
// Order Reference ID normalisation
// ---------------------------------------------------------------------------

/**
 * Extracts the raw UUID portion from a LifeFile orderReferenceId.
 * Handles formats: "trn::UUID", "order::UUID", and bare UUID.
 * Returns null for anything that doesn't contain a recognisable UUID segment.
 */
function extractUuidFromReferenceId(raw: string): string | null {
  const trimmed = raw.trim();
  if (!trimmed) return null;

  // Known prefixes used by LifeFile and Telegra
  const prefixPattern = /^(?:trn|order|transaction|ref)::/i;
  const withoutPrefix = prefixPattern.test(trimmed)
    ? trimmed.replace(prefixPattern, "")
    : trimmed;

  // Validate it looks like a UUID (loose check)
  const uuidPattern =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  return uuidPattern.test(withoutPrefix) ? withoutPrefix : null;
}

/**
 * Attempts to build an "order::UUID" scoped ID from a raw orderReferenceId.
 * Returns null when the raw value cannot be normalised.
 */
export function normalizeLifeFileOrderReferenceId(
  raw: string | null | undefined,
): string | null {
  if (!raw || typeof raw !== "string") return null;

  const trimmed = raw.trim();

  // Already a valid Telegra provider order id – return as-is.
  if (trimmed.startsWith("order::")) return trimmed;

  // Attempt prefix swap (trn::UUID → order::UUID).
  const uuid = extractUuidFromReferenceId(trimmed);
  return uuid ? `order::${uuid}` : null;
}

// ---------------------------------------------------------------------------
// Order resolution
// ---------------------------------------------------------------------------

async function fetchOrderFromProviderLink(
  supabase: SupabaseAdminClient,
  providerOrderId: string,
): Promise<OrderRow | null> {
  const { data: links, error } = await supabase
    .from("order_provider_platform_links")
    .select("order_id")
    .eq("provider_order_id", providerOrderId)
    .limit(2);

  if (error) {
    throw new Error(
      `Failed to query order_provider_platform_links: ${error.message}`,
    );
  }

  if (!links || links.length === 0) return null;

  if (links.length > 1) {
    throw new Error(
      `Ambiguous provider order id – multiple orders matched: ${providerOrderId}`,
    );
  }

  return fetchOrderById(supabase, links[0].order_id as string);
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
      tracking_number,
      tracking_url,
      shipped_at,
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

  if (error)
    throw new Error(`Failed to fetch order ${orderId}: ${error.message}`);
  return data as OrderRow | null;
}

/**
 * Lookups the most recent order for the patient (matched by email) that is
 * currently in a pharmacy-stage status. Returns null when none is found.
 *
 * The set of "pharmacy-stage" statuses are those from order_sent_to_pharmacy
 * through in_transit inclusive – i.e. the full range LifeFile can legitimately update.
 *
 * If more than one active pharmacy-stage order exists for the patient a warning
 * is logged and the most recently created one is returned (safest default).
 */
async function fetchLatestPharmacyStageOrderByEmail(
  supabase: SupabaseAdminClient,
  patientEmail: string,
  requestId: string,
): Promise<OrderRow | null> {
  // Resolve patient id from email.
  const { data: patient, error: patientError } = await supabase
    .from("patients")
    .select("id")
    .eq("email", patientEmail.trim().toLowerCase())
    .maybeSingle();

  if (patientError) {
    throw new Error(
      `Failed to look up patient by email: ${patientError.message}`,
    );
  }

  if (!patient) return null;

  // Find orders currently in a pharmacy-stage status.
  const pharmacyStageKeys = [
    "order_sent_to_pharmacy",
    "pharmacy_approval_pending",
    "pharmacy_approved",
    "fulfillment_in_progress",
    "final_pharmacy_verification",
    "in_transit",
  ];

  const { data: matchingStatuses, error: statusError } = await supabase
    .from("order_statuses")
    .select("id")
    .in("status_key", pharmacyStageKeys)
    .eq("is_active", true);

  if (statusError) {
    throw new Error(
      `Failed to fetch pharmacy-stage status ids: ${statusError.message}`,
    );
  }

  if (!matchingStatuses || matchingStatuses.length === 0) return null;

  const statusIds = matchingStatuses.map((s: { id: string }) => s.id);

  const { data: orders, error: ordersError } = await supabase
    .from("orders")
    .select(
      `
      id,
      tenant_id,
      status_id,
      tracking_number,
      tracking_url,
      shipped_at,
      created_at,
      order_statuses (
        id,
        status_key,
        display_order,
        is_terminal
      )
    `,
    )
    .eq("patient_id", patient.id)
    .in("status_id", statusIds)
    .order("created_at", { ascending: false });

  if (ordersError) {
    throw new Error(
      `Failed to fetch pharmacy-stage orders for patient: ${ordersError.message}`,
    );
  }

  if (!orders || orders.length === 0) return null;

  if (orders.length > 1) {
    console.warn(
      "Multiple pharmacy-stage orders found for patient; using most recent",
      { requestId, patientEmail, count: orders.length },
    );
  }

  const row = orders[0] as OrderRow & { created_at: string };
  // Strip created_at before returning to match the OrderRow shape.
  const { created_at: _unused, ...orderRow } = row;
  return orderRow as OrderRow;
}

/**
 * Resolves the internal order from a LifeFile webhook payload item using a
 * three-step correlation strategy:
 *
 * 1. If orderReferenceId already starts with "order::" → direct provider link lookup.
 * 2. Try swapping the prefix to "order::UUID" → provider link lookup.
 * 3. Fall back to patient email → most recent pharmacy-stage order in our DB.
 *
 * Returns null when no order can be correlated.
 */
export async function resolveOrderFromLifeFilePayload(
  supabase: SupabaseAdminClient,
  params: {
    orderReferenceId: string | null | undefined;
    patientEmail: string | null | undefined;
    requestId: string;
  },
): Promise<ResolvedOrder | null> {
  const { orderReferenceId, patientEmail, requestId } = params;

  // Step 1 & 2: provider platform link lookup via normalized "order::UUID".
  if (orderReferenceId) {
    const normalizedId = normalizeLifeFileOrderReferenceId(orderReferenceId);

    if (normalizedId) {
      const isDirectOrderId = orderReferenceId.trim().startsWith("order::");
      const correlationMethod = isDirectOrderId
        ? ("provider_platform_link" as const)
        : ("prefix_swap" as const);

      const order = await fetchOrderFromProviderLink(supabase, normalizedId);
      if (order) {
        console.info("Resolved order via provider platform link", {
          requestId,
          correlationMethod,
          normalizedId,
          orderId: order.id,
        });
        return { order, correlationMethod };
      }

      console.info(
        `Order not found via provider platform link (${correlationMethod}); trying email fallback`,
        { requestId, normalizedId },
      );
    } else {
      console.warn(
        "orderReferenceId could not be normalised to order::UUID; trying email fallback",
        { requestId, orderReferenceId },
      );
    }
  }

  // Step 3: email fallback.
  if (patientEmail && typeof patientEmail === "string" && patientEmail.trim()) {
    const order = await fetchLatestPharmacyStageOrderByEmail(
      supabase,
      patientEmail,
      requestId,
    );

    if (order) {
      console.info(
        "Resolved order via patient email fallback — monitor this log; " +
          "once LifeFile confirms orderReferenceId format this path should not be needed",
        { requestId, patientEmail, orderId: order.id },
      );
      return { order, correlationMethod: "email_fallback" };
    }
  }

  return null;
}

// ---------------------------------------------------------------------------
// rxStatus → order status key mapping
// ---------------------------------------------------------------------------

/**
 * Maps a LifeFile rxStatus string to our internal order status key.
 * Returns null for "Rx Scheduled" (log-only, no state change) and for
 * any unrecognised value.
 */
export function mapLifeFileRxStatusToOrderStatusKey(
  rxStatus: string | null | undefined,
): string | null {
  if (!rxStatus || typeof rxStatus !== "string") return null;

  const normalised = rxStatus.trim().toLowerCase();

  switch (normalised) {
    case "rx scheduled":
      // Pharmacy has received the Rx; advance order to pharmacy_approval_pending.
      return "pharmacy_approval_pending";
    case "ready for fulfillment":
      return "pharmacy_approved";
    case "fulfillment":
      return "fulfillment_in_progress";
    case "final verification":
      return "final_pharmacy_verification";
    case "rx shipping pickup":
      return "in_transit";
    default:
      return null;
  }
}

// ---------------------------------------------------------------------------
// Forward-only status guard
// ---------------------------------------------------------------------------

/**
 * Returns true only when the target status has a higher display_order than the
 * current status, ensuring orders never regress through the lifecycle.
 */
export function shouldAdvanceStatus(
  currentDisplayOrder: number,
  targetDisplayOrder: number,
): boolean {
  return targetDisplayOrder > currentDisplayOrder;
}

// ---------------------------------------------------------------------------
// Audit note builder
// ---------------------------------------------------------------------------

export function buildLifeFileWebhookNote(params: {
  rxStatus: string | null;
  orderStatus: string | null;
  lifefileOrderId: string | null;
  rxNumber: string | null;
  fillId: string | null;
  trackingNumber: string | null;
  correlationMethod: string;
}): string {
  const parts = ["LifeFile webhook received"];

  if (params.rxStatus) parts.push(`rxStatus=${params.rxStatus}`);
  if (params.orderStatus) parts.push(`orderStatus=${params.orderStatus}`);
  if (params.lifefileOrderId)
    parts.push(`lifefileOrderId=${params.lifefileOrderId}`);
  if (params.rxNumber) parts.push(`rxNumber=${params.rxNumber}`);
  if (params.fillId) parts.push(`fillId=${params.fillId}`);
  if (params.trackingNumber) parts.push(`tracking=${params.trackingNumber}`);
  parts.push(`correlatedBy=${params.correlationMethod}`);

  return parts.join("; ");
}

// ---------------------------------------------------------------------------
// Target status fetcher
// ---------------------------------------------------------------------------

export async function fetchOrderStatusByKey(
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
    throw new Error(
      `Failed to fetch order status ${statusKey}: ${error.message}`,
    );
  }

  return data as OrderStatusRow | null;
}

// Re-export dateTime so index.ts only needs one shared import.
export { dateTime };
