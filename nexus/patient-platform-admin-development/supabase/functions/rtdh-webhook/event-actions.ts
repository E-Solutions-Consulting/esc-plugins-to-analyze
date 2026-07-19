import {
  DIRECT_STATUS_EVENT_TYPES,
  SEMANTIC_EVENT_TYPES,
} from "./event-types.ts";
import { asNonEmptyString, asObject } from "./validation.ts";

export const ORDER_LINKED_SOURCE_STATUS_KEY = "order_created";

/**
 * Resolve the RTDH event_id used as the idempotency key for an inbound event.
 *
 * Prefers the `x-rtdh-event-id` header (set by the PP Consumer once the master object processor
 * publishes the event_id PubSub attribute), then falls back to the most recent timeline entry's
 * `event_id` so direct callers — and older consumer builds that don't send the header — still
 * dedupe. Returns null when neither is present; the caller then processes the event as before
 * (no dedupe, no regression).
 */
export function extractRtdhEventId(
  payload: { timeline?: unknown },
  requestHeaders: Record<string, string> = {},
): string | null {
  const headerEventId = asNonEmptyString(requestHeaders["x-rtdh-event-id"]);
  if (headerEventId) {
    return headerEventId;
  }

  const timeline = Array.isArray(payload.timeline) ? payload.timeline : [];
  // The latest entry describes the transition this event represents.
  for (let index = timeline.length - 1; index >= 0; index--) {
    const entry = asObject(timeline[index]);
    const entryEventId = entry ? asNonEmptyString(entry.event_id) : null;
    if (entryEventId) {
      return entryEventId;
    }
  }

  return null;
}

export function resolveForwardEventType(
  payload: {
    event_type?: unknown;
    order_status_key?: unknown;
    global_status?: unknown;
    timeline?: unknown;
  },
): string | null {
  const eventType = asNonEmptyString(payload.event_type);
  if (isSemanticEventType(eventType)) {
    return eventType;
  }

  const globalStatus = asNonEmptyString(payload.global_status);
  if (isSemanticEventType(globalStatus)) {
    return globalStatus;
  }

  const timelineEventType = getLatestTimelineEventType(payload.timeline);
  if (isSemanticEventType(timelineEventType)) {
    return timelineEventType;
  }

  const orderStatusKey = asNonEmptyString(payload.order_status_key);
  if (orderStatusKey) {
    return orderStatusKey;
  }

  if (
    globalStatus &&
    DIRECT_STATUS_EVENT_TYPES.includes(
      globalStatus as (typeof DIRECT_STATUS_EVENT_TYPES)[number],
    )
  ) {
    return globalStatus;
  }

  return eventType;
}

function isSemanticEventType(eventType: string | null): boolean {
  return !!eventType &&
    SEMANTIC_EVENT_TYPES.includes(
      eventType as (typeof SEMANTIC_EVENT_TYPES)[number],
    );
}

function getLatestTimelineEventType(timelineValue: unknown): string | null {
  const timeline = Array.isArray(timelineValue) ? timelineValue : [];
  for (let index = timeline.length - 1; index >= 0; index--) {
    const entry = asObject(timeline[index]);
    const entryEventType = entry ? asNonEmptyString(entry.event_type) : null;
    if (entryEventType) {
      return entryEventType;
    }
  }

  return null;
}

export type OrderLinkedStatus = {
  status_key: string | null;
  next_status_id: string | null;
};

export function getOrderLinkedNextStatusId(
  currentStatus: OrderLinkedStatus | null | undefined,
): string | null {
  if (currentStatus?.status_key !== ORDER_LINKED_SOURCE_STATUS_KEY) {
    return null;
  }

  return currentStatus.next_status_id || null;
}

export function shouldTriggerOrderLifecycleForDirectStatusEvent(
  eventType: string,
  options: { skipLifecycle?: boolean } = {},
): boolean {
  if (options.skipLifecycle) {
    return false;
  }

  return DIRECT_STATUS_EVENT_TYPES.includes(
    eventType as (typeof DIRECT_STATUS_EVENT_TYPES)[number],
  );
}

export function shouldInsertOrderHistoryForDirectStatusEvent(
  currentStatusKey: string | null | undefined,
  targetStatusKey: string | null | undefined,
): boolean {
  if (!currentStatusKey || !targetStatusKey) {
    return true;
  }

  return currentStatusKey !== targetStatusKey;
}

export type DirectStatusOrdering = {
  display_order: number | null;
  status_key?: string | null;
};

export function shouldApplyDirectStatusTransition(
  currentStatus: DirectStatusOrdering | null | undefined,
  targetStatus: DirectStatusOrdering | null | undefined,
): boolean {
  // A confirmed payment always recovers a failed-payment order. payment_failed
  // ranks above payment_collected in display_order (it is a late-stage failure
  // branch), so without this exception an order could never leave
  // payment_failed when the invoice is eventually paid (card-update retry,
  // manual payment via the Stripe-hosted invoice page, or an admin-initiated
  // charge) — the success event would be dropped as a "stale" regression.
  if (
    targetStatus?.status_key === "payment_collected" &&
    currentStatus?.status_key === "payment_failed"
  ) {
    return true;
  }

  if (
    typeof currentStatus?.display_order !== "number" ||
    typeof targetStatus?.display_order !== "number"
  ) {
    return true;
  }

  return targetStatus.display_order >= currentStatus.display_order;
}
