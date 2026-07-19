type JsonRecord = Record<string, unknown>;

export interface NormalizedTelegraWebhookEvent {
  rawType: string | null;
  normalizedType: string | null;
  rawStatus: string | null;
  normalizedStatus: string | null;
  rawTargetEntityStatus: string | null;
  normalizedTargetEntityStatus: string | null;
  providerOrderId: string | null;
  trackingNumber: string | null;
  trackingUrl: string | null;
  occurredAt: string | null;
  shippedAt: string | null;
  deliveredAt: string | null;
  cancelledAt: string | null;
}

export interface TelegraWebhookOrderIdPathAttempt {
  path: string;
  exists: boolean;
  rawValue: string | null;
  acceptedValue: string | null;
  reason:
    | "accepted"
    | "missing"
    | "rejected_empty_string"
    | "rejected_non_string"
    | "rejected_non_order_scoped";
}

export interface TelegraWebhookOrderIdSelection {
  selectedPath: string | null;
  selectedValue: string | null;
  attempts: TelegraWebhookOrderIdPathAttempt[];
}

export interface TelegraWebhookOrderIdDiagnostics {
  providerOrderId: TelegraWebhookOrderIdSelection;
}

type TelegraWebhookEventCategory = "order" | "prescription" | "pharmacy";

const EVENT_TYPE_PATHS = ["eventType"];

const STATUS_PATHS = ["status"];

const TARGET_ENTITY_STATUS_PATHS = ["targetEntity.status"];

const DEFAULT_PROVIDER_ORDER_ID_PATHS = ["targetEntity.id"];
const PRACTITIONER_APPROVAL_PROVIDER_ORDER_ID_PATHS = ["targetEntity.order.id"];
const PHARMACY_SUBMISSION_PROVIDER_ORDER_ID_PATHS = ["targetEntity.order.id"];
const SHIPPING_DETAILS_PROVIDER_ORDER_ID_PATHS = [
  "eventData.order",
  "eventData.order.id",
];

const TRACKING_NUMBER_PATHS = [
  "trackingNumber",
  "tracking_number",
  "tracking.number",
  "shipment.trackingNumber",
  "shipment.tracking_number",
  "data.trackingNumber",
  "data.tracking_number",
  "data.tracking.number",
  "data.shipment.trackingNumber",
  "data.shipment.tracking_number",
  "eventData.shippingDetails.trackingNumber",
  "eventData.shippingDetails.tracking_number",
];

const TRACKING_URL_PATHS = [
  "trackingUrl",
  "tracking_url",
  "tracking.url",
  "shipment.trackingUrl",
  "shipment.tracking_url",
  "data.trackingUrl",
  "data.tracking_url",
  "data.tracking.url",
  "data.shipment.trackingUrl",
  "data.shipment.tracking_url",
  "eventData.shippingDetails.trackingURL",
  "eventData.shippingDetails.trackingUrl",
  "eventData.shippingDetails.tracking_url",
];

const OCCURRED_AT_PATHS = [
  "occurredAt",
  "occurred_at",
  "timestamp",
  "createdAt",
  "created_at",
  "eventTimestamp",
  "event_timestamp",
  "data.occurredAt",
  "data.occurred_at",
  "data.timestamp",
  "data.createdAt",
  "data.created_at",
];

const SHIPPED_AT_PATHS = [
  "shippedAt",
  "shipped_at",
  "shipment.shippedAt",
  "shipment.shipped_at",
  "data.shippedAt",
  "data.shipped_at",
  "data.shipment.shippedAt",
  "data.shipment.shipped_at",
];

const DELIVERED_AT_PATHS = [
  "deliveredAt",
  "delivered_at",
  "shipment.deliveredAt",
  "shipment.delivered_at",
  "data.deliveredAt",
  "data.delivered_at",
  "data.shipment.deliveredAt",
  "data.shipment.delivered_at",
];

const CANCELLED_AT_PATHS = [
  "cancelledAt",
  "cancelled_at",
  "cancelledAt",
  "cancelled_at",
  "data.cancelledAt",
  "data.cancelled_at",
  "data.cancelledAt",
  "data.cancelled_at",
];

function getValueAtPath(source: unknown, path: string): unknown {
  return path.split(".").reduce<unknown>((current, segment) => {
    if (!current || typeof current !== "object") return undefined;
    return (current as JsonRecord)[segment];
  }, source);
}

function summarizeDebugValue(value: unknown): string | null {
  if (value === undefined) return null;
  if (value === null) return "null";
  if (typeof value === "string") return value;
  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }
  if (Array.isArray(value)) return "[array]";
  if (typeof value === "object") return "[object]";
  return String(value);
}

function getFirstNonEmptyString(
  source: unknown,
  paths: string[],
): string | null {
  for (const path of paths) {
    const value = getValueAtPath(source, path);
    if (typeof value === "string" && value.trim().length > 0) {
      return value.trim();
    }
  }
  return null;
}

function normalizeTelegraOrderScopedId(value: unknown): string | null {
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.startsWith("order::") ? trimmedValue : null;
}

function inspectOrderScopedPath(value: unknown): {
  acceptedValue: string | null;
  reason: TelegraWebhookOrderIdPathAttempt["reason"];
} {
  if (value === undefined) {
    return { acceptedValue: null, reason: "missing" };
  }
  if (typeof value !== "string") {
    return { acceptedValue: null, reason: "rejected_non_string" };
  }

  const trimmedValue = value.trim();
  if (trimmedValue.length === 0) {
    return { acceptedValue: null, reason: "rejected_empty_string" };
  }

  return trimmedValue.startsWith("order::")
    ? { acceptedValue: trimmedValue, reason: "accepted" }
    : { acceptedValue: null, reason: "rejected_non_order_scoped" };
}

function inspectOrderIdPaths(
  source: unknown,
  paths: string[],
  inspectValue: (value: unknown) => {
    acceptedValue: string | null;
    reason: TelegraWebhookOrderIdPathAttempt["reason"];
  },
): TelegraWebhookOrderIdSelection {
  let selectedPath: string | null = null;
  let selectedValue: string | null = null;

  const attempts = paths.map((path) => {
    const value = getValueAtPath(source, path);
    const result = inspectValue(value);

    if (!selectedPath && result.acceptedValue) {
      selectedPath = path;
      selectedValue = result.acceptedValue;
    }

    return {
      path,
      exists: value !== undefined,
      rawValue: summarizeDebugValue(value),
      acceptedValue: result.acceptedValue,
      reason: result.reason,
    };
  });

  return {
    selectedPath,
    selectedValue,
    attempts,
  };
}

function getProviderOrderIdPaths(normalizedType: string | null): string[] {
  if (normalizedType === "prescription_approved_by_practitioner") {
    return PRACTITIONER_APPROVAL_PROVIDER_ORDER_ID_PATHS;
  }
  if (normalizedType === "prescription_sent_to_pharmacy") {
    return PHARMACY_SUBMISSION_PROVIDER_ORDER_ID_PATHS;
  }
  if (normalizedType === "shipping_details_set") {
    return SHIPPING_DETAILS_PROVIDER_ORDER_ID_PATHS;
  }

  return DEFAULT_PROVIDER_ORDER_ID_PATHS;
}

function getFirstOrderScopedId(
  source: unknown,
  paths: string[],
): string | null {
  for (const path of paths) {
    const providerOrderId = normalizeTelegraOrderScopedId(
      getValueAtPath(source, path),
    );
    if (providerOrderId) return providerOrderId;
  }

  return null;
}

export function getTelegraWebhookOrderIdDiagnostics(
  payload: unknown,
): TelegraWebhookOrderIdDiagnostics {
  const rawType = getFirstNonEmptyString(payload, EVENT_TYPE_PATHS);
  const normalizedType = normalizeKey(rawType);

  return {
    providerOrderId: inspectOrderIdPaths(
      payload,
      getProviderOrderIdPaths(normalizedType),
      inspectOrderScopedPath,
    ),
  };
}

function normalizeTimestampInput(value: unknown): string | null {
  if (typeof value === "string" && value.trim().length > 0) {
    return value.trim();
  }
  if (value && typeof value === "object") {
    const dateValue = (value as JsonRecord).$date;
    if (typeof dateValue === "string" && dateValue.trim().length > 0) {
      return dateValue.trim();
    }
  }
  return null;
}

function getFirstTimestampString(
  source: unknown,
  paths: string[],
): string | null {
  for (const path of paths) {
    const normalizedValue = normalizeTimestampInput(
      getValueAtPath(source, path),
    );
    if (normalizedValue) return normalizedValue;
  }
  return null;
}

function toIsoTimestamp(value: unknown): string | null {
  const normalizedValue = normalizeTimestampInput(value);
  if (!normalizedValue) return null;
  const timestamp = new Date(normalizedValue);
  return Number.isNaN(timestamp.getTime()) ? null : timestamp.toISOString();
}

function normalizeKey(value: string | null): string | null {
  if (!value) return null;

  const normalized = value
    .trim()
    .replace(/([a-z0-9])([A-Z])/g, "$1_$2")
    .replace(/[^a-zA-Z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .toLowerCase();

  return normalized.length > 0 ? normalized : null;
}

export function normalizeTelegraWebhookEvent(
  payload: unknown,
): NormalizedTelegraWebhookEvent {
  const rawType = getFirstNonEmptyString(payload, EVENT_TYPE_PATHS);
  const normalizedType = normalizeKey(rawType);
  const rawStatus = getFirstNonEmptyString(payload, STATUS_PATHS);
  const rawTargetEntityStatus = getFirstNonEmptyString(
    payload,
    TARGET_ENTITY_STATUS_PATHS,
  );

  return {
    rawType,
    normalizedType,
    rawStatus,
    normalizedStatus: normalizeKey(rawStatus),
    rawTargetEntityStatus,
    normalizedTargetEntityStatus: normalizeKey(rawTargetEntityStatus),
    providerOrderId: getFirstOrderScopedId(
      payload,
      getProviderOrderIdPaths(normalizedType),
    ),
    trackingNumber: getFirstNonEmptyString(payload, TRACKING_NUMBER_PATHS),
    trackingUrl: getFirstNonEmptyString(payload, TRACKING_URL_PATHS),
    occurredAt: toIsoTimestamp(
      getFirstTimestampString(payload, OCCURRED_AT_PATHS),
    ),
    shippedAt: toIsoTimestamp(
      getFirstTimestampString(payload, SHIPPED_AT_PATHS),
    ),
    deliveredAt: toIsoTimestamp(
      getFirstTimestampString(payload, DELIVERED_AT_PATHS),
    ),
    cancelledAt: toIsoTimestamp(
      getFirstTimestampString(payload, CANCELLED_AT_PATHS),
    ),
  };
}

interface DirectTelegraEventMatch {
  category: TelegraWebhookEventCategory;
  statusKey: string;
}

const DIRECT_EVENT_MAP = new Map<string, DirectTelegraEventMatch>([
  [
    "prescription_received",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "prescription_created",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "prescription_submitted",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "prescription_pending_review",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "prescription_review_pending",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "provider_review_pending",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "requires_provider_review",
    {
      category: "prescription",
      statusKey: "provider_review_pending",
    },
  ],
  [
    "prescription_approved",
    {
      category: "prescription",
      statusKey: "provider_approved",
    },
  ],
  [
    "prescription_approved_by_practitioner",
    {
      category: "prescription",
      statusKey: "provider_approved",
    },
  ],
  [
    "provider_approved",
    {
      category: "prescription",
      statusKey: "provider_approved",
    },
  ],
  [
    "prescription_rejected",
    {
      category: "prescription",
      statusKey: "provider_rejected",
    },
  ],
  [
    "prescription_denied",
    {
      category: "prescription",
      statusKey: "provider_rejected",
    },
  ],
  [
    "provider_rejected",
    {
      category: "prescription",
      statusKey: "provider_rejected",
    },
  ],
  [
    "prescription_followup_required",
    {
      category: "prescription",
      statusKey: "medical_followup_required",
    },
  ],
  [
    "medical_followup_required",
    {
      category: "prescription",
      statusKey: "medical_followup_required",
    },
  ],
  [
    "additional_info_required",
    {
      category: "prescription",
      statusKey: "medical_followup_required",
    },
  ],
  [
    "prescription_sent_to_pharmacy",
    {
      category: "pharmacy",
      statusKey: "order_sent_to_pharmacy",
    },
  ],
  [
    "order_submitted",
    {
      category: "prescription",
      statusKey: "provider_approved",
    },
  ],
  [
    "sent_to_pharmacy",
    {
      category: "pharmacy",
      statusKey: "order_sent_to_pharmacy",
    },
  ],
  [
    "pharmacy_pending",
    {
      category: "pharmacy",
      statusKey: "pharmacy_approval_pending",
    },
  ],
  [
    "pharmacy_approval_pending",
    {
      category: "pharmacy",
      statusKey: "pharmacy_approval_pending",
    },
  ],
  [
    "pharmacy_approved",
    {
      category: "pharmacy",
      statusKey: "pharmacy_approved",
    },
  ],
  [
    "approved_by_pharmacy",
    {
      category: "pharmacy",
      statusKey: "pharmacy_approved",
    },
  ],
  [
    "order_pharmacy_approved",
    {
      category: "pharmacy",
      statusKey: "pharmacy_approved",
    },
  ],
  [
    "fulfillment_in_progress",
    {
      category: "pharmacy",
      statusKey: "fulfillment_in_progress",
    },
  ],
  [
    "fulfillment_started",
    {
      category: "pharmacy",
      statusKey: "fulfillment_in_progress",
    },
  ],
  [
    "preparing",
    {
      category: "pharmacy",
      statusKey: "fulfillment_in_progress",
    },
  ],
  [
    "processing",
    {
      category: "pharmacy",
      statusKey: "fulfillment_in_progress",
    },
  ],
  [
    "final_pharmacy_verification",
    {
      category: "pharmacy",
      statusKey: "final_pharmacy_verification",
    },
  ],
  [
    "quality_check",
    {
      category: "pharmacy",
      statusKey: "final_pharmacy_verification",
    },
  ],
  [
    "quality_control",
    {
      category: "pharmacy",
      statusKey: "final_pharmacy_verification",
    },
  ],
  [
    "pharmacy_rejected",
    {
      category: "pharmacy",
      statusKey: "pharmacy_rejected",
    },
  ],
  [
    "inventory_unavailable",
    {
      category: "pharmacy",
      statusKey: "inventory_unavailable",
    },
  ],
  [
    "in_transit",
    {
      category: "order",
      statusKey: "in_transit",
    },
  ],
  [
    "shipment_in_transit",
    {
      category: "order",
      statusKey: "in_transit",
    },
  ],
  [
    "shipped",
    {
      category: "order",
      statusKey: "in_transit",
    },
  ],
  [
    "order_shipped",
    {
      category: "order",
      statusKey: "in_transit",
    },
  ],
  [
    "shipping_details_set",
    {
      category: "order",
      statusKey: "in_transit",
    },
  ],
  [
    "delivered",
    {
      category: "order",
      statusKey: "delivered",
    },
  ],
  [
    "order_delivered",
    {
      category: "order",
      statusKey: "delivered",
    },
  ],
  [
    "shipping_exception",
    {
      category: "order",
      statusKey: "shipping_exception",
    },
  ],
  [
    "delivery_exception",
    {
      category: "order",
      statusKey: "shipping_exception",
    },
  ],
  [
    "shipment_exception",
    {
      category: "order",
      statusKey: "shipping_exception",
    },
  ],
  [
    "failed_delivery",
    {
      category: "order",
      statusKey: "shipping_exception",
    },
  ],
  [
    "cancelled",
    {
      category: "order",
      statusKey: "order_cancelled",
    },
  ],
  [
    "cancelled",
    {
      category: "order",
      statusKey: "order_cancelled",
    },
  ],
  [
    "order_cancelled",
    {
      category: "order",
      statusKey: "order_cancelled",
    },
  ],
  [
    "order_cancelled",
    {
      category: "order",
      statusKey: "order_cancelled",
    },
  ],
]);

function getNormalizedCandidates(
  event: NormalizedTelegraWebhookEvent,
): string[] {
  if (
    event.normalizedType === "order_submitted" &&
    event.normalizedTargetEntityStatus === "requires_provider_review"
  ) {
    return [
      event.normalizedTargetEntityStatus,
      event.normalizedType,
      event.normalizedStatus,
    ].filter((value): value is string => Boolean(value));
  }

  return [
    event.normalizedType,
    event.normalizedStatus,
    event.normalizedTargetEntityStatus,
  ].filter((value): value is string => Boolean(value));
}

function isPrescriptionCandidate(candidate: string): boolean {
  return (
    candidate.includes("prescription") ||
    candidate === "rx" ||
    candidate.startsWith("rx_") ||
    candidate.endsWith("_rx") ||
    candidate.includes("_rx_") ||
    candidate.startsWith("provider_review") ||
    candidate.startsWith("provider_approved") ||
    candidate.startsWith("provider_rejected") ||
    candidate.includes("medical_followup") ||
    (candidate.includes("provider") &&
      (candidate.includes("review") ||
        candidate.includes("approve") ||
        candidate.includes("reject") ||
        candidate.includes("deny") ||
        candidate.includes("followup") ||
        candidate.includes("follow_up")))
  );
}

function isPharmacyCandidate(candidate: string): boolean {
  return (
    candidate.includes("pharmacy") ||
    candidate.includes("fulfillment") ||
    // "verification" without "identity"/"id_check" to avoid catching IDV events
    (candidate.includes("verification") &&
      !candidate.includes("identity") &&
      !candidate.includes("id_")) ||
    candidate.includes("quality") ||
    candidate.includes("inventory") ||
    candidate.includes("dispens") ||
    candidate === "processing" ||
    candidate === "preparing"
  );
}

function categorizeTelegraWebhookEvent(
  event: NormalizedTelegraWebhookEvent,
): TelegraWebhookEventCategory {
  const candidates = getNormalizedCandidates(event);

  for (const candidate of candidates) {
    const directMatch = DIRECT_EVENT_MAP.get(candidate);
    if (directMatch) return directMatch.category;
  }

  for (const candidate of candidates) {
    if (isPrescriptionCandidate(candidate)) return "prescription";
    if (isPharmacyCandidate(candidate)) return "pharmacy";
  }

  return "order";
}

function inferPrescriptionStatusKey(candidate: string): string | null {
  if (
    candidate.includes("followup") ||
    candidate.includes("follow_up") ||
    candidate.includes("additional_info") ||
    candidate.includes("clarification") ||
    candidate.includes("question")
  ) {
    return "medical_followup_required";
  }

  if (
    candidate.includes("reject") ||
    candidate.includes("declin") ||
    candidate.includes("deny")
  ) {
    return "provider_rejected";
  }

  if (
    candidate.includes("approve") ||
    candidate.includes("signed") ||
    candidate.includes("issued")
  ) {
    return "provider_approved";
  }

  if (
    candidate.includes("pending") ||
    candidate.includes("review") ||
    candidate.includes("received") ||
    candidate.includes("submitted") ||
    candidate.includes("created")
  ) {
    return "provider_review_pending";
  }

  return null;
}

function inferPharmacyStatusKey(candidate: string): string | null {
  if (
    candidate.includes("reject") ||
    candidate.includes("declin") ||
    candidate.includes("deny")
  ) {
    return "pharmacy_rejected";
  }

  if (candidate.includes("inventory")) return "inventory_unavailable";
  if (candidate.includes("quality") || candidate.includes("verification")) {
    return "final_pharmacy_verification";
  }
  if (
    candidate.includes("fulfillment") ||
    candidate.includes("preparing") ||
    candidate === "processing"
  ) {
    return "fulfillment_in_progress";
  }
  if (candidate.includes("approve")) return "pharmacy_approved";
  if (candidate.includes("pending") || candidate.includes("sent_to_pharmacy")) {
    return candidate.includes("sent_to_pharmacy")
      ? "order_sent_to_pharmacy"
      : "pharmacy_approval_pending";
  }

  return null;
}

function isIdvCandidate(candidate: string): boolean {
  return (
    (candidate.includes("identity") ||
      candidate.includes("id_check") ||
      candidate.includes("id_verification") ||
      candidate.includes("kyc")) &&
    (candidate.includes("fail") ||
      candidate.includes("error") ||
      candidate.includes("reject") ||
      candidate.includes("declin") ||
      candidate.includes("expired") ||
      candidate.includes("invalid") ||
      candidate.includes("blurry") ||
      candidate.includes("unreadable"))
  );
}

function inferOrderStatusKey(candidate: string): string | null {
  // IDV failure must be checked before generic shipping/pharmacy patterns
  if (isIdvCandidate(candidate)) return "id_verification_failed";

  if (candidate.includes("deliver")) return "delivered";
  if (candidate.includes("ship") || candidate.includes("transit")) {
    return "in_transit";
  }
  if (
    candidate.includes("exception") ||
    candidate.includes("delay") ||
    candidate.includes("failed_delivery")
  ) {
    return "shipping_exception";
  }
  if (candidate.includes("cancel")) return "order_cancelled";

  return null;
}

export function mapTelegraEventToOrderStatus(
  event: NormalizedTelegraWebhookEvent,
): string | null {
  if (
    event.normalizedType === "new_status_set_to_request" &&
    event.normalizedTargetEntityStatus === "requires_order_processing"
  ) {
    return "payment_pending";
  }

  const candidates = getNormalizedCandidates(event);
  const category = categorizeTelegraWebhookEvent(event);

  for (const candidate of candidates) {
    const directMatch = DIRECT_EVENT_MAP.get(candidate);
    if (directMatch) return directMatch.statusKey;
  }

  for (const candidate of candidates) {
    if (category === "prescription") {
      const inferred = inferPrescriptionStatusKey(candidate);
      if (inferred) return inferred;
      continue;
    }

    if (category === "pharmacy") {
      const inferred = inferPharmacyStatusKey(candidate);
      if (inferred) return inferred;
      continue;
    }

    const inferred = inferOrderStatusKey(candidate);
    if (inferred) return inferred;
  }

  return null;
}

function appendUniqueSecret(secrets: string[], value: unknown): void {
  if (typeof value !== "string") return;

  const normalizedValue = value.trim();
  if (!normalizedValue || secrets.includes(normalizedValue)) return;

  secrets.push(normalizedValue);
}

export function getTelegraWebhookSecrets(
  settings: Record<string, unknown> | null | undefined,
): string[] {
  const secrets: string[] = [];

  appendUniqueSecret(secrets, settings?.access_token);

  return secrets;
}

function bytesToHex(bytes: Uint8Array): string {
  return Array.from(bytes)
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

function bytesToBase64(bytes: Uint8Array): string {
  return btoa(String.fromCharCode(...bytes));
}

async function computeHmacSha256Bytes(
  secret: string,
  payload: string,
): Promise<Uint8Array> {
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    encoder.encode(payload),
  );

  return new Uint8Array(signature);
}

export async function verifyTelegraWebhookSignature(params: {
  payload: string;
  signatureHeader: string;
  secrets: string[];
}): Promise<boolean> {
  const { payload, signatureHeader, secrets } = params;
  const trimmedSignature = signatureHeader.trim();

  if (!trimmedSignature || secrets.length === 0) return false;

  const signature = trimmedSignature.toLowerCase().startsWith("sha256=")
    ? trimmedSignature.slice(7).trim()
    : trimmedSignature;
  const normalizedHexSignature = signature.toLowerCase();

  for (const secret of secrets) {
    const signatureBytes = await computeHmacSha256Bytes(secret, payload);
    const expectedHex = bytesToHex(signatureBytes);
    if (timingSafeEqualString(expectedHex, normalizedHexSignature)) {
      return true;
    }

    const expectedBase64 = bytesToBase64(signatureBytes);
    if (timingSafeEqualString(expectedBase64, signature)) {
      return true;
    }
  }

  return false;
}

export function timingSafeEqualString(left: string, right: string): boolean {
  const leftBytes = new TextEncoder().encode(left);
  const rightBytes = new TextEncoder().encode(right);

  let mismatch = leftBytes.length === rightBytes.length ? 0 : 1;
  const maxLength = Math.max(leftBytes.length, rightBytes.length);

  for (let index = 0; index < maxLength; index += 1) {
    mismatch |= (leftBytes[index] ?? 0) ^ (rightBytes[index] ?? 0);
  }

  return mismatch === 0;
}
