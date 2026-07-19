/**
 * Canonical platform event catalog (edge runtime).
 *
 * THE RULE: an event exists here only if we actually capture it. Every key below
 * maps to a real signal the platform records — an `order_statuses.status_key`, a
 * `subscription_events.event_type`, or an `analytics_events.event_type`. Nothing
 * is inferred, guessed, or aspirational.
 *
 * This is the single source of truth for BOTH consumers:
 *   - Outbound Webhooks (Developer -> Outbound Webhooks): which events a tenant
 *     can subscribe an endpoint to.
 *   - Comms Automations (Automations builder): which events can TRIGGER a journey.
 *
 * Keeping one map means trigger/webhook parity is structural rather than
 * hand-maintained. The two catalogs had previously drifted: the trigger list
 * offered order statuses that don't exist (`order_approved`, `pharmacy_approved`)
 * and so could never fire.
 *
 * Deno can't import from `src/`, so `src/lib/platform-events.ts` mirrors the maps
 * below for the UI. Change both together.
 */

/** Canonical event keys. Must match src/lib/platform-events.ts. */
export type PlatformEventKey =
  // Order lifecycle
  | "order.created"
  | "order.paid"
  | "order.cancelled"
  | "provider.review_pending"
  | "provider.approved"
  | "provider.rejected"
  | "prescription.shipped"
  | "order.delivered"
  // Subscription lifecycle
  | "subscription.created"
  | "subscription.renewed"
  | "subscription.cancelled"
  | "subscription.paused"
  | "subscription.resumed"
  | "subscription.renewal_date_changed"
  // Product usage (analytics). Session/UTM events were removed from this
  // catalog: the analytics SDK never emits session_start/session_end event
  // types and utm_attribution is a session PROPERTY, not an event — offering
  // them created webhooks/triggers that could never fire (THE RULE above).
  | "usage.page_view"
  | "usage.activity_event"
  // Named behavioral events — one per analytics.track() call site in the
  // patient UI (the same names the automations trigger dropdown offers).
  // Add a key here ONLY together with the client instrumentation that emits it.
  | "usage.login"
  | "usage.signup_started"
  | "usage.signup_completed"
  | "usage.product_viewed"
  | "usage.checkout_started"
  | "usage.checkout_completed"
  | "usage.questionnaire_started"
  | "usage.questionnaire_step_completed"
  | "usage.questionnaire_completed";

/** Which domain each event belongs to. Drives webhook-type routing in the dispatcher. */
export const PLATFORM_EVENT_DOMAIN: Record<PlatformEventKey, "order" | "subscription" | "usage"> = {
  "order.created": "order",
  "order.paid": "order",
  "order.cancelled": "order",
  "provider.review_pending": "order",
  "provider.approved": "order",
  "provider.rejected": "order",
  "prescription.shipped": "order",
  "order.delivered": "order",
  "subscription.created": "subscription",
  "subscription.renewed": "subscription",
  "subscription.cancelled": "subscription",
  "subscription.paused": "subscription",
  "subscription.resumed": "subscription",
  "subscription.renewal_date_changed": "subscription",
  "usage.page_view": "usage",
  "usage.activity_event": "usage",
  "usage.login": "usage",
  "usage.signup_started": "usage",
  "usage.signup_completed": "usage",
  "usage.product_viewed": "usage",
  "usage.checkout_started": "usage",
  "usage.checkout_completed": "usage",
  "usage.questionnaire_started": "usage",
  "usage.questionnaire_step_completed": "usage",
  "usage.questionnaire_completed": "usage",
};

/**
 * Order `status_key` (order_statuses.status_key) -> event key.
 *
 * This is the source of truth for "which status transitions are public":
 * statuses absent from this map are internal and intentionally NOT emitted.
 */
export const STATUS_KEY_TO_EVENT: Record<string, PlatformEventKey> = {
  order_created: "order.created",
  payment_collected: "order.paid",
  provider_review_pending: "provider.review_pending",
  provider_approved: "provider.approved",
  provider_rejected: "provider.rejected",
  order_cancelled: "order.cancelled",
  order_pending_cancellation: "order.cancelled",
  in_transit: "prescription.shipped",
  order_shipped: "prescription.shipped",
  prescription_shipped: "prescription.shipped",
  order_delivered: "order.delivered",
};

/** The event for an order status_key, or null if none is exposed. */
export function eventForStatusKey(
  statusKey: string | null | undefined,
): PlatformEventKey | null {
  if (!statusKey) return null;
  return STATUS_KEY_TO_EVENT[statusKey] ?? null;
}

/**
 * `subscription_events.event_type` -> event key. EXPLICIT, never substring.
 *
 * The complete set the DB trigger log_subscription_lifecycle_event() writes is:
 *   created | cancelled | paused | resumed | status_changed |
 *   renewal_date_changed | expiration_date_changed | lifecycle_updated
 *
 * Note what is NOT here: there is no captured `renewed` type. A renewal is NOT a
 * subscription-table event at all — see isRenewalPaidEvent() below. The previous
 * implementation guessed with `event_type.includes("renew")`, which matched
 * `renewal_date_changed` and therefore delivered a "subscription renewed" webhook
 * every time an admin merely EDITED a renewal date. That was a false event; this
 * map is why it can't happen again.
 *
 * Types deliberately unmapped (internal bookkeeping, no public meaning):
 * status_changed, expiration_date_changed, lifecycle_updated.
 */
export const SUBSCRIPTION_EVENT_TYPE_TO_EVENT: Record<string, PlatformEventKey> = {
  created: "subscription.created",
  cancelled: "subscription.cancelled",
  paused: "subscription.paused",
  resumed: "subscription.resumed",
  renewal_date_changed: "subscription.renewal_date_changed",
};

/** The event for a subscription_events.event_type, or null if not public. */
export function eventForSubscriptionType(
  eventType: string | null | undefined,
): PlatformEventKey | null {
  if (!eventType) return null;
  return SUBSCRIPTION_EVENT_TYPE_TO_EVENT[eventType] ?? null;
}

/**
 * A REAL renewal: a subscription-linked order classified `renewal` got paid.
 *
 * `orders.subscription_order_type` ('initial' | 'renewal') is set automatically by
 * the DB trigger set_order_subscription_order_type() on every order in a
 * subscription, so "a renewal order was paid" is a fact we already capture —
 * no new plumbing, no inference. Both sweepers use this to emit
 * `subscription.renewed` alongside `order.paid`.
 */
export function isRenewalPaidEvent(
  statusKey: string | null | undefined,
  subscriptionOrderType: string | null | undefined,
): boolean {
  return statusKey === "payment_collected" && subscriptionOrderType === "renewal";
}

/**
 * Reverse lookup: the order status_keys an event is captured from. Derived from
 * STATUS_KEY_TO_EVENT so it can never disagree with the forward map. Used by
 * test_trigger to synthesise a realistic event for a named trigger.
 */
export function statusKeysForEvent(eventKey: string): string[] {
  return Object.entries(STATUS_KEY_TO_EVENT)
    .filter(([, v]) => v === eventKey)
    .map(([k]) => k);
}

/**
 * Reverse lookup: the subscription_events.event_type behind an event key, or null
 * when the event has no such row (subscription.renewed comes from a paid renewal
 * ORDER, not from subscription_events).
 */
export function subscriptionTypeForEvent(eventKey: string): string | null {
  const hit = Object.entries(SUBSCRIPTION_EVENT_TYPE_TO_EVENT)
    .find(([, v]) => v === eventKey);
  return hit ? hit[0] : null;
}

/**
 * Analytics `event_type` -> product-usage event key. Known navigation/session/
 * attribution types map to their specific event; everything else surfaces as the
 * generic activity event so no in-app activity is silently dropped.
 */
export const USAGE_TYPE_TO_EVENT: Record<string, PlatformEventKey> = {
  page_view: "usage.page_view",
  screen_view: "usage.page_view",
  // Everything else (track/identify/…) falls through to usage.activity_event.
};

/** The product-usage event for an analytics event_type (never null). */
export function eventForUsageType(
  eventType: string | null | undefined,
): PlatformEventKey {
  if (!eventType) return "usage.activity_event";
  return USAGE_TYPE_TO_EVENT[eventType] ?? "usage.activity_event";
}

/**
 * Analytics event NAME (analytics_events.event_name from a `track` call) ->
 * its first-class usage event key, or null when the name has no dedicated
 * webhook event. Names here must be really emitted by the patient UI —
 * same vocabulary the automations trigger dropdown offers. Unnamed / unmapped
 * activity still fans out as usage.activity_event via eventForUsageType.
 */
export const USAGE_NAME_TO_EVENT: Record<string, PlatformEventKey> = {
  login: "usage.login",
  signup_started: "usage.signup_started",
  signup_completed: "usage.signup_completed",
  product_viewed: "usage.product_viewed",
  checkout_started: "usage.checkout_started",
  checkout_completed: "usage.checkout_completed",
  questionnaire_started: "usage.questionnaire_started",
  questionnaire_step_completed: "usage.questionnaire_step_completed",
  questionnaire_completed: "usage.questionnaire_completed",
};

/** The dedicated usage event for a named behavioral event, or null. */
export function eventForUsageName(
  eventName: string | null | undefined,
): PlatformEventKey | null {
  if (!eventName) return null;
  return USAGE_NAME_TO_EVENT[eventName] ?? null;
}
