/**
 * Outbound webhook event catalog.
 *
 * Two DISTINCT webhook types whose events MUST NOT be mixed on one webhook:
 *   - 'lifecycle'     : order / subscription / provider lifecycle events
 *   - 'product_usage' : analytics / usage events from the patient app
 *
 * A webhook subscribes to events of exactly one type; the selectable event keys
 * are scoped to that type.
 *
 * The EVENTS themselves are NOT defined here — they come from the canonical
 * catalog in `src/lib/platform-events.ts`, which the Comms Automations trigger
 * picker also reads. That is what guarantees a tenant can trigger an automation
 * on every event they can subscribe a webhook to. What lives here is the
 * webhook-specific concern: the lifecycle/product_usage split, the no-mixing
 * rule, and the payload fields each event delivers.
 */
import {
  LIFECYCLE_EVENTS,
  USAGE_EVENTS,
  type PlatformEventDef,
} from "@/lib/platform-events";

export type WebhookType = "lifecycle" | "product_usage";

/** A field carried in a delivered event's `data` object. */
export interface WebhookParamDef {
  name: string;
  type: "string" | "number" | "boolean" | "object" | "timestamp";
  description: string;
  /**
   * `id`       — a raw identifier emitted by the producer.
   * `derived`  — resolved by the dispatcher from an id (name, email, label, …).
   *              These are what make a payload human-usable: to email a patient
   *              you need `patient_email`, not `patient_id`.
   *
   * Every field is delivered on every subscribed webhook. Per-webhook field
   * SELECTION is a deliberate future step (it needs a per-subscription payload
   * in the RTDH fan-out envelope); this metadata is what that UI will key off.
   */
  source?: "id" | "derived";
  /**
   * True when the field carries personal data (name / email / phone). Surfaced
   * in the UI so tenants know what their endpoint receives. Note: contact
   * fields ARE delivered — endpoints are tenant-configured and already receive
   * `patient_id`.
   */
  pii?: boolean;
}

export interface WebhookEventDef {
  key: string;
  label: string;
  description: string;
  /**
   * Fields this event carries in the delivered payload's `data` object (on top
   * of the common envelope + common data fields — see COMMON_PAYLOAD_FIELDS and
   * COMMON_DATA_FIELDS). Kept in sync with the producers in
   * supabase/functions/_shared/outbound-emit.ts and each producer call site.
   */
  params: WebhookParamDef[];
}

/**
 * Every delivered webhook has this outer envelope (added by the platform +
 * RTDH), independent of the event. Shown once in the UI so tenants know the full
 * shape, not just the per-event `data` fields.
 */
export const COMMON_PAYLOAD_FIELDS: WebhookParamDef[] = [
  { name: "event", type: "string", description: "The event key, e.g. \"order.paid\"." },
  { name: "type", type: "string", description: "\"lifecycle\" or \"product_usage\"." },
  { name: "tenantId", type: "string", description: "Your tenant's internal id." },
  { name: "occurredAt", type: "timestamp", description: "ISO-8601 time the event occurred." },
  { name: "data", type: "object", description: "The event-specific fields below (plus the common data fields)." },
];

/** Fields present in `data` for EVERY event (added by the dispatcher/RTDH). */
export const COMMON_DATA_FIELDS: WebhookParamDef[] = [
  { name: "tenant", type: "string", description: "Your tenant slug." },
  { name: "internal_tenant_id", type: "string", description: "Your tenant's internal id." },
];

export const WEBHOOK_TYPES: { value: WebhookType; label: string; description: string }[] = [
  {
    value: "lifecycle",
    label: "Lifecycle events",
    description: "Order, subscription and provider lifecycle changes.",
  },
  {
    value: "product_usage",
    label: "Product-usage events",
    description: "Analytics / usage events emitted by the patient app.",
  },
];

// Per-event `data` fields. `source: "id"` fields mirror the payloads built by the
// producers (keep in sync with supabase/functions/_shared/outbound-emit.ts);
// `source: "derived"` fields are resolved centrally by the dispatcher
// (supabase/functions/outbound-webhook-dispatcher/enrich.ts).

/**
 * Fields resolved by the dispatcher from `patient_id` (table: patients).
 * Shared by every event that carries a patient.
 */
const PATIENT_DERIVED_PARAMS: WebhookParamDef[] = [
  { name: "patient_first_name", type: "string", description: "The patient's first name.", source: "derived", pii: true },
  { name: "patient_last_name", type: "string", description: "The patient's last name.", source: "derived", pii: true },
  { name: "patient_full_name", type: "string", description: "The patient's full name (first + last).", source: "derived", pii: true },
  { name: "patient_email", type: "string", description: "The patient's email address — use this to send them email.", source: "derived", pii: true },
  { name: "patient_phone", type: "string", description: "The patient's phone number — use this to send them SMS.", source: "derived", pii: true },
];

const ORDER_PARAMS: WebhookParamDef[] = [
  { name: "order_id", type: "string", description: "The order's id.", source: "id" },
  { name: "patient_id", type: "string", description: "The patient's id (if identified).", source: "id" },
  { name: "status_key", type: "string", description: "Internal order status that triggered the event.", source: "id" },
  { name: "occurred_at", type: "timestamp", description: "When the status transition was recorded.", source: "id" },
  // Derived — resolved by the dispatcher so the payload is usable without
  // calling back into the platform to look ids up.
  { name: "status_label", type: "string", description: "Human-readable status name, e.g. \"Provider approved\".", source: "derived" },
  { name: "order_status", type: "string", description: "The order's current status (patient-facing label).", source: "derived" },
  { name: "product_name", type: "string", description: "Name of the product ordered.", source: "derived" },
  // provider_name / pharmacy_name were documented but NEVER delivered: orders
  // has no such columns (resolution needs the provider/pharmacy link tables) —
  // removed until actually implemented (THE RULE: don't advertise what we
  // don't produce).
  ...PATIENT_DERIVED_PARAMS,
];

const SUBSCRIPTION_PARAMS: WebhookParamDef[] = [
  { name: "subscription_id", type: "string", description: "The subscription's id.", source: "id" },
  { name: "patient_id", type: "string", description: "The patient's id.", source: "id" },
  { name: "subscription_event_type", type: "string", description: "Internal subscription event type.", source: "id" },
  { name: "occurred_at", type: "timestamp", description: "When the subscription event was recorded.", source: "id" },
  // Derived.
  { name: "subscription_status", type: "string", description: "The subscription's current status.", source: "derived" },
  { name: "product_name", type: "string", description: "Name of the subscribed product/plan.", source: "derived" },
  { name: "current_period_end_at", type: "timestamp", description: "End of the current billing period (next renewal).", source: "derived" },
  ...PATIENT_DERIVED_PARAMS,
];

const USAGE_PARAMS: WebhookParamDef[] = [
  { name: "event_type", type: "string", description: "Raw analytics event type, e.g. \"page_view\".", source: "id" },
  { name: "event_name", type: "string", description: "Optional named event.", source: "id" },
  { name: "patient_id", type: "string", description: "The patient's id (absent for anonymous sessions).", source: "id" },
  { name: "session_id", type: "string", description: "The analytics session id.", source: "id" },
  { name: "page_path", type: "string", description: "Path of the page/screen (navigation events).", source: "id" },
  { name: "properties", type: "object", description: "Arbitrary event properties sent by the app.", source: "id" },
  // Derived (only when the session is identified).
  ...PATIENT_DERIVED_PARAMS,
];

/** The payload fields an event carries, chosen by the entity it is about. */
function paramsForDomain(domain: PlatformEventDef["domain"]): WebhookParamDef[] {
  switch (domain) {
    case "order":
      return ORDER_PARAMS;
    case "subscription":
      return SUBSCRIPTION_PARAMS;
    case "usage":
      return USAGE_PARAMS;
  }
}

const toWebhookEvent = (e: PlatformEventDef): WebhookEventDef => ({
  key: e.key,
  label: e.label,
  description: e.description,
  params: paramsForDomain(e.domain),
});

/**
 * Derived from PLATFORM_EVENTS — see src/lib/platform-events.ts. Add an event
 * there and it appears here AND in the automation trigger picker; there is no
 * second list to remember.
 *
 * Note `subscription.renewed` now means a renewal ORDER WAS PAID. It used to be
 * inferred by substring-matching the subscription event type, which matched
 * `renewal_date_changed` — so editing a renewal date fired a "renewed" webhook.
 */
export const WEBHOOK_EVENT_CATALOG: Record<WebhookType, WebhookEventDef[]> = {
  lifecycle: LIFECYCLE_EVENTS.map(toWebhookEvent),
  product_usage: USAGE_EVENTS.map(toWebhookEvent),
};

export function eventsForType(type: WebhookType): WebhookEventDef[] {
  return WEBHOOK_EVENT_CATALOG[type] ?? [];
}

/** True if every event key belongs to the given type's catalog (no mixing). */
export function eventsAreValidForType(type: WebhookType, keys: string[]): boolean {
  const allowed = new Set(eventsForType(type).map((e) => e.key));
  return keys.every((k) => allowed.has(k));
}

/** The event definition for a key, across both types. */
export function eventDefForKey(key: string): WebhookEventDef | undefined {
  for (const defs of Object.values(WEBHOOK_EVENT_CATALOG)) {
    const found = defs.find((e) => e.key === key);
    if (found) return found;
  }
  return undefined;
}

/** Every field an event carries in `data` (ids + dispatcher-derived). */
export function fieldsForEvent(key: string): WebhookParamDef[] {
  return eventDefForKey(key)?.params ?? [];
}
