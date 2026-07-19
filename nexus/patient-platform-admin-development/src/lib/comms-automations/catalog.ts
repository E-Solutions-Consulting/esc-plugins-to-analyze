/**
 * Communications Automations — trigger & placeholder catalog.
 *
 * What a tenant can start an automation from, and what merge placeholders are
 * available in email/SMS bodies.
 *
 * TRIGGER PARITY: the order/subscription trigger options are DERIVED from the
 * canonical event catalog (src/lib/platform-events.ts) — the same list the
 * Outbound Webhooks picker reads. So every event you can subscribe a webhook to,
 * you can also trigger an automation on. Triggers are a SUPERSET: they add the
 * relative-time axis ("3 days before renewal") and an advanced raw-status escape
 * hatch, neither of which webhooks have.
 *
 * This replaces a hand-maintained list that had drifted badly: it offered
 * `order_approved`, `order_sent_to_pharmacy`, `pharmacy_approved` and `delivered`
 * — none of which are real status keys — so picking one produced a trigger that
 * could never fire.
 *
 * See docs/CommunicationsAutomations.md.
 */
import {
  eventsForDomain,
  type PlatformEventDef,
} from "@/lib/platform-events";

export type TriggerKind =
  | "event"
  | "subscription"
  | "relative_time"
  | "order";

export interface TriggerDefinition {
  kind: TriggerKind;
  label: string;
  description: string;
}

export const TRIGGER_DEFINITIONS: TriggerDefinition[] = [
  {
    kind: "order",
    label: "Order event",
    description:
      "Start when an order reaches a milestone — paid, provider approved, shipped, delivered, cancelled.",
  },
  {
    kind: "subscription",
    label: "Subscription event",
    description:
      "Start on a subscription change — created, renewed, cancelled, paused, resumed.",
  },
  {
    kind: "relative_time",
    label: "Relative time",
    description:
      "Start N days before/after an anchor — e.g. 3 days before renewal, 7 days after purchase.",
  },
  {
    kind: "event",
    label: "Analytics event",
    description:
      "Start when a tracked product/analytics event fires (e.g. checkout completed, product viewed).",
  },
];

/**
 * The order events an automation can trigger on — the SAME named events webhooks
 * expose (Order paid, Provider approved, Prescription shipped, …), not raw
 * internal status keys.
 *
 * `statusKeys` are the underlying `order_statuses.status_key` values the event is
 * captured from; the dispatcher matches an incoming order status against these.
 */
export const ORDER_TRIGGER_EVENTS: PlatformEventDef[] = eventsForDomain("order");

/**
 * The subscription events an automation can trigger on. Same list webhooks
 * expose. Note `subscription.renewed` is a REAL renewal (a renewal order was
 * paid) — distinct from `subscription.renewal_date_changed`, which is only a
 * schedule edit.
 */
export const SUBSCRIPTION_TRIGGER_EVENTS: PlatformEventDef[] = eventsForDomain("subscription");

/** Analytics event names (mirror the ACTIVE analytics_event_types) — fallback
 *  before the live catalog loads; the builder prefers the server's list.
 *  THE RULE: only names the patient UI actually emits (analytics.track call
 *  sites) belong here — an offered-but-never-emitted name produces automations
 *  that can never fire. page_view/session_start/session_end are event TYPES,
 *  not named track events, and were deactivated in the catalog table. */
export const COMMON_EVENT_NAMES = [
  "product_viewed",
  "checkout_started",
  "checkout_completed",
  "questionnaire_started",
  "questionnaire_step_completed",
  "questionnaire_completed",
  "signup_started",
  "signup_completed",
  "login",
] as const;

/** Anchors for relative-time triggers + the column they compute from. */
export const RELATIVE_TIME_ANCHORS = [
  { key: "renewal", label: "Renewal date", column: "subscription.current_period_end_at" },
  { key: "purchase", label: "Purchase / subscription start", column: "subscription.started_at" },
  { key: "order_shipped", label: "Order shipped", column: "order.shipped_at" },
  { key: "order_delivered", label: "Order delivered", column: "order.delivered_at" },
] as const;

export interface PlaceholderField {
  key: string; // e.g. "patient.first_name"
  label: string;
  sample: string;
}

export interface PlaceholderGroup {
  namespace: string;
  label: string;
  fields: PlaceholderField[];
}

/**
 * Placeholder catalog grouped by namespace, grounded in real columns.
 *
 * THE RULE (same as platform-events): a field exists here only if the
 * dispatcher's resolveContext / the executor's enrichContext actually put it
 * on the journey context. This is the single vocabulary for BOTH consumers:
 * template placeholders ({{patient.first_name}}) AND the payload an n8n node
 * receives (context.patient.first_name). Change resolveContext and this file
 * together.
 */
export const PLACEHOLDER_GROUPS: PlaceholderGroup[] = [
  {
    namespace: "patient",
    label: "Patient",
    fields: [
      { key: "patient.first_name", label: "First name", sample: "Jordan" },
      { key: "patient.last_name", label: "Last name", sample: "Lee" },
      { key: "patient.email", label: "Email", sample: "jordan@example.com" },
      { key: "patient.phone", label: "Phone", sample: "+1 555 0100" },
      { key: "patient.city", label: "City", sample: "Austin" },
      { key: "patient.state", label: "State", sample: "TX" },
      { key: "patient.postal_code", label: "Postal code", sample: "78701" },
      { key: "patient.country", label: "Country", sample: "US" },
    ],
  },
  {
    namespace: "subscription",
    label: "Subscription",
    fields: [
      { key: "subscription.status", label: "Status", sample: "active" },
      { key: "subscription.renewal_date", label: "Renewal date", sample: "2026-07-15" },
      { key: "subscription.days_until_renewal", label: "Days until renewal", sample: "3" },
      { key: "subscription.days_since_start", label: "Days since start", sample: "27" },
      { key: "subscription.started_at", label: "Started at", sample: "2026-06-18T14:02:11Z" },
      { key: "subscription.current_period_end_at", label: "Period end", sample: "2026-07-15T00:00:00Z" },
    ],
  },
  {
    namespace: "order",
    label: "Order",
    fields: [
      { key: "order.order_number", label: "Order number", sample: "ORD-10231" },
      { key: "order.status", label: "Status (patient label)", sample: "Order Shipped" },
      { key: "order.status_key", label: "Status key", sample: "order_shipped" },
      { key: "order.tracking_number", label: "Tracking number", sample: "1Z999AA10123456784" },
      { key: "order.tracking_url", label: "Tracking URL", sample: "https://track…" },
      { key: "order.total_usd", label: "Total (USD)", sample: "499.00" },
      { key: "order.days_since_order", label: "Days since order", sample: "2" },
    ],
  },
  {
    namespace: "product",
    label: "Product",
    fields: [
      { key: "product.name", label: "Name", sample: "Semaglutide" },
      { key: "product.sku", label: "SKU", sample: "SEMA-1M" },
    ],
  },
  {
    namespace: "event",
    label: "Event",
    fields: [
      { key: "event.event_name", label: "Event name", sample: "checkout_completed" },
      { key: "event.properties", label: "Event properties (object)", sample: '{ "method": "password" }' },
    ],
  },
  {
    namespace: "tenant",
    label: "Tenant",
    fields: [
      { key: "tenant.name", label: "Brand name", sample: "Brello Health" },
      { key: "tenant.slug", label: "Brand slug", sample: "brello" },
    ],
  },
];

/**
 * Which context namespaces a trigger KIND delivers — this is the automation's
 * equivalent of the webhooks payload reference: the exact blocks resolveContext
 * puts on the enrollment context, i.e. what n8n receives under `context` and
 * what template placeholders can read. `always` blocks are guaranteed when the
 * source record exists; `conditional` blocks appear only when the triggering
 * event carries that entity (e.g. an analytics event has no order).
 */
export const TRIGGER_CONTEXT_NAMESPACES: Record<
  TriggerKind,
  { always: string[]; conditional: string[] }
> = {
  event: { always: ["event", "patient", "tenant"], conditional: ["product"] },
  order: { always: ["order", "patient", "tenant"], conditional: ["product", "subscription"] },
  subscription: {
    always: ["subscription", "patient", "tenant"],
    conditional: ["product"],
  },
  relative_time: {
    always: ["patient", "tenant"],
    conditional: ["subscription", "order", "product"],
  },
};

/** The placeholder groups a trigger kind delivers, split always/conditional. */
export function payloadGroupsForTrigger(kind: TriggerKind): {
  always: PlaceholderGroup[];
  conditional: PlaceholderGroup[];
} {
  const spec = TRIGGER_CONTEXT_NAMESPACES[kind];
  const byNs = new Map(PLACEHOLDER_GROUPS.map((g) => [g.namespace, g]));
  const pick = (names: string[]) =>
    names.map((n) => byNs.get(n)).filter((g): g is PlaceholderGroup => !!g);
  return { always: pick(spec.always), conditional: pick(spec.conditional) };
}

/** Flattened list of all placeholder keys (for validation). */
export const ALL_PLACEHOLDER_KEYS = PLACEHOLDER_GROUPS.flatMap((g) =>
  g.fields.map((f) => f.key)
);
