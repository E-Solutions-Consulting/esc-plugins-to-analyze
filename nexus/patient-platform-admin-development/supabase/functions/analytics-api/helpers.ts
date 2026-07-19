// Pure, testable helpers for the analytics-api edge function.
// See docs/AnalyticsAPI.md and docs/AnalyticsTracking.md.

/** Effective per-tenant tracking flags (tenant override else platform default). */
export interface AnalyticsSettings {
  tracking_enabled: boolean;
  track_page_views: boolean;
  track_activity_events: boolean;
  track_time_on_page: boolean;
  track_device_info: boolean;
  track_utm_attribution: boolean;
  track_guest_sessions: boolean;
  session_idle_timeout_minutes: number;
  hot_retention_days: number;
}

export const DEFAULT_SETTINGS: AnalyticsSettings = {
  tracking_enabled: false,
  track_page_views: true,
  track_activity_events: true,
  track_time_on_page: true,
  track_device_info: true,
  track_utm_attribution: true,
  track_guest_sessions: true,
  session_idle_timeout_minutes: 30,
  hot_retention_days: 30,
};

/**
 * Resolve the effective settings from a platform-default row and an optional
 * tenant override row. Either may be null/partial; missing fields fall back to
 * DEFAULT_SETTINGS, then platform default, then tenant override (most specific wins).
 */
export function resolveEffectiveSettings(
  platformDefault: Partial<AnalyticsSettings> | null | undefined,
  tenantOverride: Partial<AnalyticsSettings> | null | undefined,
): AnalyticsSettings {
  return {
    ...DEFAULT_SETTINGS,
    ...(platformDefault ?? {}),
    ...(tenantOverride ?? {}),
  };
}

/** Incoming event from the client batch (shape mirrors the SDK). */
export interface IncomingEvent {
  client_event_id?: string;
  event_type?: string;
  event_name?: string;
  page_path?: string;
  page_title?: string;
  referrer?: string;
  duration_ms?: number;
  properties?: Record<string, unknown>;
  occurred_at?: string;
}

export const KNOWN_EVENT_TYPES = [
  "page_view",
  "track",
  "identify",
  "session_start",
  "session_end",
] as const;

/**
 * Property keys that must never appear in behavioural events (PHI / PII guard).
 * Matching is case-insensitive and substring-based so e.g. `patient_email` is caught.
 */
export const DISALLOWED_PROPERTY_KEY_PATTERNS = [
  "email",
  "phone",
  "ssn",
  "dob",
  "date_of_birth",
  "birth",
  "address",
  "first_name",
  "last_name",
  "full_name",
  "password",
  "diagnosis",
  "medication",
  "allerg",
  "condition",
  "symptom",
  "card_number",
  "cvv",
] as const;

export function isDisallowedPropertyKey(key: string): boolean {
  const k = key.toLowerCase();
  return DISALLOWED_PROPERTY_KEY_PATTERNS.some((p) => k.includes(p));
}

/**
 * Remove disallowed (PHI/PII) keys from an event's properties. Returns the
 * sanitised object and the list of stripped keys (for observability).
 */
export function sanitizeProperties(
  properties: Record<string, unknown> | undefined | null,
): { clean: Record<string, unknown>; stripped: string[] } {
  const clean: Record<string, unknown> = {};
  const stripped: string[] = [];
  if (!properties || typeof properties !== "object") {
    return { clean, stripped };
  }
  for (const [key, value] of Object.entries(properties)) {
    if (isDisallowedPropertyKey(key)) {
      stripped.push(key);
      continue;
    }
    clean[key] = value;
  }
  return { clean, stripped };
}

/** Maximum events accepted in a single /collect batch. */
export const MAX_BATCH_SIZE = 100;
/** Maximum serialized size of a single event's properties (bytes). */
export const MAX_PROPERTIES_BYTES = 8 * 1024;

/** Category each event type belongs to, for tenant flag gating. */
export function eventCategoryFlag(
  event: IncomingEvent,
): keyof AnalyticsSettings | null {
  switch (event.event_type) {
    case "page_view":
      return "track_page_views";
    case "track":
      return "track_activity_events";
    case "session_start":
    case "session_end":
    case "identify":
      // Lifecycle/identity events are always allowed when tracking is enabled.
      return null;
    default:
      return "track_activity_events";
  }
}

export interface ValidationResult {
  valid: boolean;
  reason?: string;
}

/** Validate a single event's shape before persistence. */
export function validateEvent(event: IncomingEvent): ValidationResult {
  if (!event || typeof event !== "object") {
    return { valid: false, reason: "not_an_object" };
  }
  if (!event.client_event_id || typeof event.client_event_id !== "string") {
    return { valid: false, reason: "missing_client_event_id" };
  }
  if (!event.event_type || typeof event.event_type !== "string") {
    return { valid: false, reason: "missing_event_type" };
  }
  if (event.properties) {
    const size = JSON.stringify(event.properties).length;
    if (size > MAX_PROPERTIES_BYTES) {
      return { valid: false, reason: "properties_too_large" };
    }
  }
  return { valid: true };
}

export interface FilterOutcome {
  accepted: IncomingEvent[];
  rejected: Array<{ client_event_id?: string; reason: string }>;
}

/**
 * Apply tenant settings + validation + PII sanitisation to a batch. Events for
 * disabled categories are rejected; surviving events get sanitised properties.
 */
export function filterAndSanitizeBatch(
  events: IncomingEvent[],
  settings: AnalyticsSettings,
  opts: { isAuthenticated: boolean },
): FilterOutcome {
  const accepted: IncomingEvent[] = [];
  const rejected: Array<{ client_event_id?: string; reason: string }> = [];

  for (const raw of events.slice(0, MAX_BATCH_SIZE)) {
    const validation = validateEvent(raw);
    if (!validation.valid) {
      rejected.push({ client_event_id: raw?.client_event_id, reason: validation.reason! });
      continue;
    }
    // Guest gating: drop guest events when the tenant disabled guest sessions.
    if (!opts.isAuthenticated && !settings.track_guest_sessions) {
      rejected.push({ client_event_id: raw.client_event_id, reason: "guest_tracking_disabled" });
      continue;
    }
    // Category gating.
    const flag = eventCategoryFlag(raw);
    if (flag && !settings[flag]) {
      rejected.push({ client_event_id: raw.client_event_id, reason: "category_disabled" });
      continue;
    }
    // Time-on-page gating: strip duration when time tracking is off.
    const event: IncomingEvent = { ...raw };
    if (!settings.track_time_on_page) {
      delete event.duration_ms;
    }
    const { clean } = sanitizeProperties(event.properties);
    event.properties = clean;
    accepted.push(event);
  }

  if (events.length > MAX_BATCH_SIZE) {
    rejected.push({ reason: `batch_truncated_to_${MAX_BATCH_SIZE}` });
  }

  return { accepted, rejected };
}

export function sanitizeTenantSlug(value: string): string {
  return value.trim().replace(/['"]/g, "");
}

export function getTenantSlug(url: URL, headers: Headers): string | null {
  const slug = url.searchParams.get("tenant_slug") || headers.get("x-tenant-slug");
  return slug ? sanitizeTenantSlug(slug) : null;
}
