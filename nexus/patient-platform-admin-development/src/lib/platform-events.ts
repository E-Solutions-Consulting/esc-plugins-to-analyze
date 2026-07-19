/**
 * Canonical platform event catalog (UI).
 *
 * THE RULE: an event exists here only if we actually capture it. Every key below
 * maps to a real signal the platform records — an `order_statuses.status_key`, a
 * `subscription_events.event_type`, or (for renewals) a paid order classified
 * `subscription_order_type = 'renewal'`. Nothing is inferred or aspirational.
 *
 * ONE list, TWO consumers:
 *   - `src/lib/webhook-events.ts`            — what an endpoint can SUBSCRIBE to.
 *   - `src/lib/comms-automations/catalog.ts` — what can TRIGGER an automation.
 *
 * Triggers are a superset of webhooks: they add a timing axis (relative-time:
 * "3 days before renewal") and an advanced raw-status escape hatch. But every
 * event a webhook can subscribe to, an automation can trigger on — by
 * construction, because both read this file. The two catalogs previously drifted:
 * the trigger list offered `order_approved` / `pharmacy_approved` / `delivered`,
 * none of which are real status keys, so those triggers could never fire.
 *
 * Mirrored for the Deno runtime in `supabase/functions/_shared/platform-events.ts`
 * (edge functions can't import from `src/`). Change both together.
 */

/** Canonical event keys. Must match _shared/platform-events.ts. */
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

/** Which domain an event belongs to — drives the entity a trigger enrolls on. */
export type PlatformEventDomain = "order" | "subscription" | "usage";

export interface PlatformEventDef {
  key: PlatformEventKey;
  label: string;
  description: string;
  domain: PlatformEventDomain;
  /**
   * The captured signal this event is derived from — the honest answer to "how do
   * we know this happened?". Order events list their `order_statuses.status_key`
   * values; subscription events their `subscription_events.event_type`.
   */
  capturedFrom: string[];
}

export const PLATFORM_EVENTS: PlatformEventDef[] = [
  // --- Order lifecycle -----------------------------------------------------
  {
    key: "order.created",
    label: "Order created",
    description: "A new order was created.",
    domain: "order",
    capturedFrom: ["order_created"],
  },
  {
    key: "order.paid",
    label: "Order paid",
    description: "Payment was collected for an order.",
    domain: "order",
    capturedFrom: ["payment_collected"],
  },
  {
    key: "provider.review_pending",
    label: "Provider review pending",
    description: "An order is awaiting provider review.",
    domain: "order",
    capturedFrom: ["provider_review_pending"],
  },
  {
    key: "provider.approved",
    label: "Provider approved",
    description: "A provider approved the order.",
    domain: "order",
    capturedFrom: ["provider_approved"],
  },
  {
    key: "provider.rejected",
    label: "Provider rejected",
    description: "A provider rejected the order.",
    domain: "order",
    capturedFrom: ["provider_rejected"],
  },
  {
    key: "prescription.shipped",
    label: "Prescription shipped",
    description: "The prescription was shipped.",
    domain: "order",
    capturedFrom: ["in_transit", "order_shipped", "prescription_shipped"],
  },
  {
    key: "order.delivered",
    label: "Order delivered",
    description: "The order was delivered.",
    domain: "order",
    capturedFrom: ["order_delivered"],
  },
  {
    key: "order.cancelled",
    label: "Order cancelled",
    description: "An order was cancelled.",
    domain: "order",
    capturedFrom: ["order_cancelled", "order_pending_cancellation"],
  },

  // --- Subscription lifecycle ---------------------------------------------
  {
    key: "subscription.created",
    label: "Subscription created",
    description: "A subscription was created.",
    domain: "subscription",
    capturedFrom: ["created"],
  },
  {
    key: "subscription.renewed",
    label: "Subscription renewed",
    description:
      "A subscription actually renewed — a renewal order was paid. (Not the same as an admin editing the renewal date.)",
    domain: "subscription",
    // The ONLY honest source: subscription_events never captures a "renewed"
    // type. orders.subscription_order_type is set by a DB trigger, so "a renewal
    // order got paid" is a fact we already record.
    capturedFrom: ["order:payment_collected + subscription_order_type=renewal"],
  },
  {
    key: "subscription.cancelled",
    label: "Subscription cancelled",
    description: "A subscription was cancelled.",
    domain: "subscription",
    capturedFrom: ["cancelled"],
  },
  {
    key: "subscription.paused",
    label: "Subscription paused",
    description: "A subscription was paused.",
    domain: "subscription",
    capturedFrom: ["paused"],
  },
  {
    key: "subscription.resumed",
    label: "Subscription resumed",
    description: "A paused subscription became active again.",
    domain: "subscription",
    capturedFrom: ["resumed"],
  },
  {
    key: "subscription.renewal_date_changed",
    label: "Renewal date changed",
    description:
      "The next renewal date moved. This is a schedule change, NOT a renewal.",
    domain: "subscription",
    capturedFrom: ["renewal_date_changed"],
  },

  // --- Product usage -------------------------------------------------------
  {
    key: "usage.page_view",
    label: "Page / screen view",
    description: "A page or screen was viewed.",
    domain: "usage",
    capturedFrom: ["page_view", "screen_view"],
  },
  {
    key: "usage.activity_event",
    label: "Activity event",
    description: "A tracked in-app activity occurred (any not listed below).",
    domain: "usage",
    capturedFrom: ["*"],
  },
  // Named behavioral events — capturedFrom is the analytics event_name the
  // patient UI emits via analytics.track(). Keep in lockstep with the client
  // call sites and USAGE_NAME_TO_EVENT in _shared/platform-events.ts.
  {
    key: "usage.login",
    label: "Login",
    description: "A patient signed in.",
    domain: "usage",
    capturedFrom: ["login"],
  },
  {
    key: "usage.signup_started",
    label: "Signup started",
    description: "A visitor opened the signup flow.",
    domain: "usage",
    capturedFrom: ["signup_started"],
  },
  {
    key: "usage.signup_completed",
    label: "Signup completed",
    description: "An account was created.",
    domain: "usage",
    capturedFrom: ["signup_completed"],
  },
  {
    key: "usage.product_viewed",
    label: "Product viewed",
    description: "A product detail page was viewed.",
    domain: "usage",
    capturedFrom: ["product_viewed"],
  },
  {
    key: "usage.checkout_started",
    label: "Checkout started",
    description: "A buyer opened checkout for a product.",
    domain: "usage",
    capturedFrom: ["checkout_started"],
  },
  {
    key: "usage.checkout_completed",
    label: "Checkout completed",
    description: "Payment was authorized in checkout.",
    domain: "usage",
    capturedFrom: ["checkout_completed"],
  },
  {
    key: "usage.questionnaire_started",
    label: "Questionnaire started",
    description: "A patient opened a questionnaire with work to do.",
    domain: "usage",
    capturedFrom: ["questionnaire_started"],
  },
  {
    key: "usage.questionnaire_step_completed",
    label: "Questionnaire step completed",
    description: "A questionnaire answer was accepted.",
    domain: "usage",
    capturedFrom: ["questionnaire_step_completed"],
  },
  {
    key: "usage.questionnaire_completed",
    label: "Questionnaire completed",
    description: "A questionnaire was fully completed.",
    domain: "usage",
    capturedFrom: ["questionnaire_completed"],
  },
];

/**
 * Order `status_key` -> event key. Mirrors STATUS_KEY_TO_EVENT in
 * `_shared/platform-events.ts`; derived here so the two can't disagree.
 */
export const STATUS_KEY_TO_EVENT: Record<string, PlatformEventKey> = Object.fromEntries(
  PLATFORM_EVENTS
    .filter((e) => e.domain === "order")
    .flatMap((e) => e.capturedFrom.map((k) => [k, e.key] as const)),
) as Record<string, PlatformEventKey>;

export function eventsForDomain(domain: PlatformEventDomain): PlatformEventDef[] {
  return PLATFORM_EVENTS.filter((e) => e.domain === domain);
}

export function platformEventDef(key: string): PlatformEventDef | undefined {
  return PLATFORM_EVENTS.find((e) => e.key === key);
}

/** Lifecycle = order + subscription (everything that isn't product usage). */
export const LIFECYCLE_EVENTS: PlatformEventDef[] = PLATFORM_EVENTS.filter(
  (e) => e.domain !== "usage",
);

export const USAGE_EVENTS: PlatformEventDef[] = eventsForDomain("usage");
