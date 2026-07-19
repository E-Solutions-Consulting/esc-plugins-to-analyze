/**
 * Pure trigger-matching for comms-event-dispatcher.
 *
 * Extracted so it can be unit-tested against the exact payloads the producers
 * emit (comms-scheduler's sweep, analytics-api, test_trigger) — this function is
 * the join that decides whether a real order status change starts an automation.
 */
import { eventForStatusKey, eventForSubscriptionType } from "../_shared/platform-events.ts";

/**
 * Does this automation's trigger match the incoming event?
 *
 * Order/subscription triggers are configured with a canonical EVENT KEY
 * ("order.paid", "subscription.renewed") — the same vocabulary Outbound Webhooks
 * use (see _shared/platform-events.ts). Producers send the raw signal
 * (order_status / subscription_event_type), so we resolve that to its event key
 * and compare. One named event can therefore cover several internal statuses
 * (e.g. prescription.shipped <- in_transit | order_shipped | prescription_shipped).
 *
 * Raw `to_status` / `event_type` are still honoured: they are the Advanced escape
 * hatch in the builder, and automations saved before named events existed store
 * them. A trigger with neither set is a wildcard (any event of that kind).
 */
export function triggerMatches(
  trigger: Record<string, unknown>,
  payload: Record<string, unknown>,
): boolean {
  const kind = trigger.kind;
  if (kind !== payload.kind) return false;
  switch (kind) {
    case "event":
      return !trigger.event_name || trigger.event_name === payload.event_name;

    case "subscription": {
      // A real renewal arrives with an explicit event_key (emitted from a paid
      // renewal ORDER — subscription_events never records a renewal).
      const incomingKey = payload.event_key ??
        eventForSubscriptionType(payload.subscription_event_type as string | undefined);
      if (trigger.event_key) return trigger.event_key === incomingKey;
      if (trigger.event_type) return trigger.event_type === payload.subscription_event_type;
      return true; // wildcard
    }

    case "order": {
      const incomingKey = eventForStatusKey(payload.order_status as string | undefined);
      if (trigger.event_key) return trigger.event_key === incomingKey;
      if (trigger.to_status) return trigger.to_status === payload.order_status;
      return true; // wildcard
    }

    default:
      return false;
  }
}
