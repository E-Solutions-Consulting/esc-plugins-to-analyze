/**
 * Pure, testable helpers for the outbound-webhook dispatcher.
 *
 * The two webhook types (lifecycle, product_usage) are never mixed: each event
 * key maps to exactly one type, and a webhook only receives events of its own
 * type. EVENT_TYPE is DERIVED from the canonical catalog
 * (_shared/platform-events.ts) rather than hand-listed — this map is the
 * dispatcher's routing gate, so an event missing from it is silently dropped, and
 * hand-maintaining a fourth copy of the event list is how that happens.
 */
import { PLATFORM_EVENT_DOMAIN, type PlatformEventKey } from "../_shared/platform-events.ts";

export type WebhookType = "lifecycle" | "product_usage";

export const EVENT_TYPE: Record<string, WebhookType> = Object.fromEntries(
  (Object.entries(PLATFORM_EVENT_DOMAIN) as Array<[PlatformEventKey, string]>).map((
    [key, domain],
  ) => [key, domain === "usage" ? "product_usage" : "lifecycle"]),
) as Record<string, WebhookType>;

/** The webhook type for an event key, or null if unknown. */
export function webhookTypeForEvent(eventKey: string): WebhookType | null {
  return EVENT_TYPE[eventKey] ?? null;
}

/** True if every event key belongs to the given type (no mixing). */
export function eventsAreValidForType(type: WebhookType, keys: string[]): boolean {
  return keys.every((k) => EVENT_TYPE[k] === type);
}

export interface WebhookSubscription {
  webhookId: string;
  targetUrl: string;
  /** Per-endpoint secret RTDH uses to sign the final delivery to targetUrl. */
  signingSecret: string;
}

export interface PublishEnvelope {
  event: string;
  type: WebhookType;
  tenantId: string;
  occurredAt: string;
  data: unknown;
  subscriptions: WebhookSubscription[];
}

/**
 * Build the envelope POSTed to RTDH. RTDH publishes it to a Pub/Sub topic and a
 * subscriber fans out to each subscription's targetUrl, signing each delivery
 * with that subscription's signingSecret. The whole envelope is itself signed
 * (x-patientplatform-signature) by the dispatcher before transport.
 */
export function buildPublishEnvelope(params: {
  eventKey: string;
  type: WebhookType;
  tenantId: string;
  occurredAt: string;
  data: unknown;
  subscriptions: WebhookSubscription[];
}): PublishEnvelope {
  return {
    event: params.eventKey,
    type: params.type,
    tenantId: params.tenantId,
    occurredAt: params.occurredAt,
    data: params.data,
    subscriptions: params.subscriptions,
  };
}
