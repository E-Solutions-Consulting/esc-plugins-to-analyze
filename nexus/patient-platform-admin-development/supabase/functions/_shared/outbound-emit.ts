/**
 * Fire-and-forget emit to outbound-webhook-dispatcher.
 *
 * Producers (order-lifecycle, analytics-api, plan-api, …) call this in one line
 * after they mutate state. It never throws and never blocks the caller's
 * response: a failed emit must not break the originating operation. The
 * dispatcher itself resolves the tenant's matching, enabled webhooks and
 * publishes ONE signed envelope to RTDH for fan-out (see
 * outbound-webhook-dispatcher/index.ts and docs/OutboundWebhooksAPI.md).
 *
 * No secret is required to call the dispatcher (it is a service-to-service edge
 * function using the service-role key internally); we authorize with the
 * platform's own anon/service key like other internal function-to-function
 * calls. The emit is a no-op only when SUPABASE_URL is unavailable.
 *
 * The event catalog itself lives in _shared/platform-events.ts — ONE map shared
 * by Outbound Webhooks and Comms Automations, so the two can't drift apart.
 */

import {
  eventForStatusKey,
  eventForUsageName,
  eventForUsageType,
  type PlatformEventKey,
} from "./platform-events.ts";

/**
 * Outbound event keys ARE the canonical platform event keys. Aliased rather than
 * redeclared so there is exactly one list (see _shared/platform-events.ts).
 */
export type OutboundEventKey = PlatformEventKey;

/** The outbound event for an order status_key, or null if none is exposed. */
export const outboundEventForStatusKey = eventForStatusKey;

/** The product-usage outbound event for an analytics event_type. */
export const outboundEventForUsageType = eventForUsageType;

/** The dedicated outbound event for a NAMED behavioral event, or null. */
export const outboundEventForUsageName = eventForUsageName;

export interface OutboundEmitParams {
  tenantId: string | null | undefined;
  eventKey: OutboundEventKey;
  /** Event-specific payload merged into the delivered `data` object. */
  payload?: Record<string, unknown>;
  /** Optional correlation id echoed through RTDH. */
  requestId?: string;
}

/**
 * Emit a single outbound event to the dispatcher. Detached (does NOT await),
 * swallows all errors — the caller's operation must never fail because a
 * webhook could not be published. The dispatcher is a cheap no-op when the
 * tenant has no matching enabled webhook, so it is safe to call unconditionally.
 */
export function emitOutboundEvent(params: OutboundEmitParams): void {
  const url = Deno.env.get("SUPABASE_URL");
  const key = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ??
    Deno.env.get("SUPABASE_ANON_KEY");
  if (!url || !key || !params.tenantId) return; // nothing to do

  void fetch(`${url}/functions/v1/outbound-webhook-dispatcher`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${key}`,
      apikey: key,
    },
    body: JSON.stringify({
      tenantId: params.tenantId,
      eventKey: params.eventKey,
      payload: params.payload ?? {},
      requestId: params.requestId,
    }),
  }).catch((e) => console.error("emitOutboundEvent failed (non-fatal):", e));
}

/**
 * Convenience: emit the outbound event for an order status transition, if the
 * status maps to a public event. No-ops silently for internal-only statuses.
 */
export function emitOrderStatusEvent(params: {
  tenantId: string | null | undefined;
  statusKey: string | null | undefined;
  orderId?: string | null;
  patientId?: string | null;
  extra?: Record<string, unknown>;
  requestId?: string;
}): void {
  const eventKey = outboundEventForStatusKey(params.statusKey);
  if (!eventKey) return;
  emitOutboundEvent({
    tenantId: params.tenantId,
    eventKey,
    payload: {
      order_id: params.orderId ?? undefined,
      patient_id: params.patientId ?? undefined,
      status_key: params.statusKey ?? undefined,
      ...(params.extra ?? {}),
    },
    requestId: params.requestId,
  });
}
