/**
 * Fire-and-forget emit to comms-event-dispatcher.
 *
 * Producers (plan-api, order-lifecycle, analytics-api, …) call this in one line
 * after they mutate state. It never throws and never blocks the caller's response:
 * a failed emit must not break the originating operation. Auth uses the shared
 * COMMS_INTERNAL_SECRET (same pattern as the other comms-* internal calls).
 *
 * No-op when COMMS_INTERNAL_SECRET is unset (feature not provisioned in this env).
 */

export interface CommsEmitPayload {
  tenant_id: string;
  kind: "event" | "subscription" | "order" | "relative_time";
  // Matching keys (one per kind):
  event_name?: string;
  subscription_event_type?: string;
  order_status?: string;
  // Entity ids — the dispatcher resolves these into the placeholder context.
  patient_id?: string;
  subscription_id?: string;
  order_id?: string;
  product_id?: string;
  entity_id?: string;
  // Behavioral event detail / extra context.
  event?: { event_name?: string; properties?: Record<string, unknown> };
  context?: Record<string, unknown>;
}

export function emitCommsEvent(payload: CommsEmitPayload): void {
  const secret = Deno.env.get("COMMS_INTERNAL_SECRET");
  const url = Deno.env.get("SUPABASE_URL");
  if (!secret || !url || !payload.tenant_id) return; // not provisioned / nothing to do

  // Detached: do not await. Swallow all errors.
  void fetch(`${url}/functions/v1/comms-event-dispatcher`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Authorization: `Bearer ${secret}` },
    body: JSON.stringify(payload),
  }).catch((e) => console.error("emitCommsEvent failed (non-fatal):", e));
}
