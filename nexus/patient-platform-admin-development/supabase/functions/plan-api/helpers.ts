import {
  buildCorsHeaders,
} from "../_shared/cors.ts";
export { isAllowedOrigin } from "../_shared/cors.ts";

export const US_STATE_CODES = [
  "AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "DC", "FL",
  "GA", "HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME",
  "MD", "MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH",
  "NJ", "NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "PR",
  "RI", "SC", "SD", "TN", "TX", "UT", "VT", "VA", "VI", "WA",
  "WV", "WI", "WY",
];

export interface RateLimitRecord {
  count: number;
  resetAt: number;
}

export const RATE_LIMIT = 100;
export const RATE_WINDOW = 60000;
export const rateLimitMap = new Map<string, RateLimitRecord>();

export function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    methods: "GET, POST, PUT, PATCH, DELETE, OPTIONS",
  });
}

export function checkRateLimit(
  clientId: string,
  store: Map<string, RateLimitRecord> = rateLimitMap,
  now: number = Date.now()
): { allowed: boolean; remaining: number } {
  const record = store.get(clientId);

  if (!record || now > record.resetAt) {
    store.set(clientId, { count: 1, resetAt: now + RATE_WINDOW });
    return { allowed: true, remaining: RATE_LIMIT - 1 };
  }

  if (record.count >= RATE_LIMIT) {
    return { allowed: false, remaining: 0 };
  }

  record.count++;
  return { allowed: true, remaining: RATE_LIMIT - record.count };
}

export function normalizeTenantSlug(value: string | null): string | null {
  if (!value) return null;
  const trimmed = value.trim();
  if (!trimmed) return null;

  const unquoted = trimmed
    .replace(/^"(.*)"$/, "$1")
    .replace(/^'(.*)'$/, "$1")
    .trim();

  return unquoted || null;
}

function normalizeStatusSignal(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

export interface TelegraProviderReviewMetadata {
  last_event_target_entity_status?: string | null;
  last_event_status?: string | null;
  normalized_target_entity_status?: string | null;
  provider_target_entity_status?: string | null;
  provider_status?: string | null;
}

export interface TelegraProviderReviewCancellationDecisionInput {
  currentStatusKey: string | null | undefined;
  providerPlatformIntegrationKey: string | null | undefined;
  providerLinkMetadata: readonly TelegraProviderReviewMetadata[];
}

const TELEGRA_PROVIDER_REVIEW_STALE_STATUS_KEYS = new Set([
  "provider_order_creation_pending",
  "patient_questionnaire_pending",
  "medical_questionnaire_pending",
]);

export function isTelegraProviderIntegrationKey(
  value: string | null | undefined,
): boolean {
  const normalized = normalizeStatusSignal(value);
  return normalized === "telegramd" || normalized === "telegra";
}

function metadataHasProviderReviewSignal(
  metadata: TelegraProviderReviewMetadata,
): boolean {
  const statusKeys = [
    "last_event_target_entity_status",
    "last_event_status",
    "normalized_target_entity_status",
    "provider_target_entity_status",
    "provider_status",
  ] as const satisfies readonly (keyof TelegraProviderReviewMetadata)[];

  return statusKeys.some((key) =>
    normalizeStatusSignal(metadata[key]) === "requires_provider_review"
  );
}

function canHaveStaleTelegraProviderReviewSignal(
  statusKey: string | null,
): boolean {
  return statusKey !== null &&
    TELEGRA_PROVIDER_REVIEW_STALE_STATUS_KEYS.has(statusKey);
}

export function shouldDeferTelegraProviderReviewCancellation(
  params: TelegraProviderReviewCancellationDecisionInput,
): boolean {
  if (!isTelegraProviderIntegrationKey(params.providerPlatformIntegrationKey)) {
    return false;
  }

  const currentStatusKey = normalizeStatusSignal(params.currentStatusKey);
  if (currentStatusKey === "provider_review_pending") {
    return true;
  }

  if (!canHaveStaleTelegraProviderReviewSignal(currentStatusKey)) {
    return false;
  }

  return params.providerLinkMetadata.some(metadataHasProviderReviewSignal);
}

// deno-lint-ignore no-explicit-any
export async function validateStateAgainstTenant(
  supabaseClient: any,
  tenantId: string,
  stateCode: string | null | undefined,
  country: string | null | undefined
): Promise<{ valid: boolean; message?: string }> {
  if (country && country.toUpperCase() !== "US") {
    return { valid: true };
  }

  if (!stateCode) {
    return { valid: true };
  }

  const normalizedState = stateCode.toUpperCase().trim();

  if (!US_STATE_CODES.includes(normalizedState)) {
    return { valid: false, message: `'${stateCode}' is not a valid US state code` };
  }

  const { data: tenantSettings, error } = await supabaseClient
    .from("tenant_settings")
    .select("allowed_states")
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    console.error("Error fetching tenant settings for state validation:", error);
    return { valid: true };
  }

  const allowedStates = (tenantSettings as { allowed_states?: string[] } | null)?.allowed_states;

  if (!allowedStates || allowedStates.length === 0) {
    return { valid: true };
  }

  const normalizedAllowedStates = allowedStates.map((s: string) => s.toUpperCase().trim());
  if (!normalizedAllowedStates.includes(normalizedState)) {
    return {
      valid: false,
      message: `We are unable to ship to ${normalizedState}. Please select a different shipping address.`,
    };
  }

  return { valid: true };
}
