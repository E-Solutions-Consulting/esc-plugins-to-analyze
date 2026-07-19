/**
 * outbound-webhook-sweeper
 *
 * Cron-invoked producer for the Outbound Webhooks feature. Order status changes
 * and subscription events are written to `order_status_history` /
 * `subscription_events` by ~22 different code paths, PLUS DB triggers that write
 * history with no TypeScript in the path at all. Rather than scatter fragile
 * emits across every write site — which could never be complete, since the DB
 * trigger path has no code to instrument — this sweeper reads the COMMITTED rows
 * since a per-source watermark and forwards each to
 * `outbound-webhook-dispatcher`. That guarantees EVERY transition fans out to
 * subscribed webhooks regardless of which path wrote it.
 *
 * (pg_net IS available — several crons use it. We still sweep rather than fire a
 * per-row DB trigger: an HTTP call inside the order-write transaction would
 * couple order fulfilment to webhook/n8n availability and would fire for rows
 * that later roll back. Sweeping keeps that coupling out of the write path.)
 *
 * Design mirrors comms-scheduler's sweepDomainEvents but is fully independent:
 * its own watermark table (`outbound_event_sweep_state`) and no comms coupling,
 * so Outbound Webhooks work even where the comms feature is not provisioned.
 *
 * Auth: CRON_SECRET (Supabase cron sends no auth header) — same as
 * reminder-scheduler / comms-scheduler. Service-role DB access.
 *
 * Latency = cron cadence. The dispatcher is idempotent-friendly (a repeated
 * emit just re-publishes; downstream endpoints should treat delivery as
 * at-least-once), and the watermark advances only past rows we actually
 * dispatched, so a failed emit is retried on the next tick.
 */
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  type OutboundEventKey,
  outboundEventForStatusKey,
} from "../_shared/outbound-emit.ts";
import {
  eventForSubscriptionType,
  isRenewalPaidEvent,
} from "../_shared/platform-events.ts";

// deno-lint-ignore no-explicit-any
type DB = any;

const SWEEP_LIMIT = 200;

/**
 * Map a subscription_events.event_type to an outbound event key, or null.
 *
 * Explicit lookup, never substring. The previous implementation guessed with
 * `event_type.includes("renew")`, which matched the captured type
 * `renewal_date_changed` — so merely EDITING a renewal date delivered a
 * "subscription renewed" webhook to the tenant. There is no captured `renewed`
 * event type at all; a real renewal is a paid renewal ORDER (see
 * isRenewalPaidEvent, emitted from the order sweep below).
 */
const outboundEventForSubscriptionType = eventForSubscriptionType;

async function readWatermark(db: DB, source: string): Promise<string> {
  const { data } = await db
    .from("outbound_event_sweep_state")
    .select("last_swept_at")
    .eq("source", source)
    .maybeSingle();
  return data?.last_swept_at ?? new Date(0).toISOString();
}

async function writeWatermark(db: DB, source: string, ts: string): Promise<void> {
  await db
    .from("outbound_event_sweep_state")
    .upsert(
      { source, last_swept_at: ts, updated_at: new Date().toISOString() },
      { onConflict: "source" },
    );
}

/** POST one event to the dispatcher; throws on transport failure. */
async function dispatch(
  baseUrl: string,
  key: string,
  payload: {
    tenantId: string;
    eventKey: OutboundEventKey;
    payload: Record<string, unknown>;
  },
): Promise<void> {
  const resp = await fetch(`${baseUrl}/functions/v1/outbound-webhook-dispatcher`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${key}`,
      apikey: key,
    },
    body: JSON.stringify(payload),
  });
  // The dispatcher returns 200 even when nothing matched; only a hard transport
  // / 5xx failure should hold the watermark.
  if (resp.status >= 500) {
    throw new Error(`dispatcher ${resp.status}`);
  }
}

/** Sweep order_status_history since its watermark -> dispatcher. */
async function sweepOrderStatus(db: DB, url: string, key: string): Promise<number> {
  const source = "order_status_history";
  const since = await readWatermark(db, source);
  const { data: rows } = await db
    .from("order_status_history")
    .select(
      "id, created_at, order_id, orders(tenant_id, patient_id, subscription_id, subscription_order_type), order_statuses:status_id(status_key)",
    )
    .gt("created_at", since)
    .order("created_at", { ascending: true })
    .limit(SWEEP_LIMIT);

  let emitted = 0;
  let maxTs = since;
  for (const r of (rows ?? [])) {
    const order = (r.orders ?? {}) as {
      tenant_id?: string;
      patient_id?: string;
      subscription_id?: string;
      subscription_order_type?: string;
    };
    const statusKey = (r.order_statuses as { status_key?: string } | null)?.status_key;
    const eventKey = outboundEventForStatusKey(statusKey);
    // A row with no resolvable tenant is data we can't act on; a status with no
    // public event is intentionally skipped. In both cases advance past it (it
    // will never become emittable) rather than blocking the sweep.
    if (order.tenant_id && eventKey) {
      try {
        await dispatch(url, key, {
          tenantId: order.tenant_id,
          eventKey,
          payload: {
            order_id: r.order_id,
            patient_id: order.patient_id,
            status_key: statusKey,
            occurred_at: r.created_at,
          },
        });
        emitted++;

        // A REAL renewal: a `renewal`-classified order was paid. This is the only
        // honest source of subscription.renewed — the subscription_events table
        // never captures a "renewed" type (see _shared/platform-events.ts).
        if (isRenewalPaidEvent(statusKey, order.subscription_order_type)) {
          await dispatch(url, key, {
            tenantId: order.tenant_id,
            eventKey: "subscription.renewed",
            payload: {
              subscription_id: order.subscription_id,
              patient_id: order.patient_id,
              order_id: r.order_id,
              occurred_at: r.created_at,
            },
          });
          emitted++;
        }
      } catch (e) {
        console.error("outbound sweep order emit failed; holding watermark:", e);
        break; // don't skip past an undelivered event
      }
    }
    if (r.created_at > maxTs) maxTs = r.created_at;
  }
  if (maxTs !== since) await writeWatermark(db, source, maxTs);
  return emitted;
}

/** Sweep subscription_events since its watermark -> dispatcher. */
async function sweepSubscriptionEvents(db: DB, url: string, key: string): Promise<number> {
  const source = "subscription_events";
  const since = await readWatermark(db, source);
  const { data: rows } = await db
    .from("subscription_events")
    .select("id, created_at, tenant_id, patient_id, subscription_id, event_type")
    .gt("created_at", since)
    .order("created_at", { ascending: true })
    .limit(SWEEP_LIMIT);

  let emitted = 0;
  let maxTs = since;
  for (const ev of (rows ?? [])) {
    const eventKey = outboundEventForSubscriptionType(ev.event_type);
    if (ev.tenant_id && eventKey) {
      try {
        await dispatch(url, key, {
          tenantId: ev.tenant_id,
          eventKey,
          payload: {
            subscription_id: ev.subscription_id,
            patient_id: ev.patient_id,
            subscription_event_type: ev.event_type,
            occurred_at: ev.created_at,
          },
        });
        emitted++;
      } catch (e) {
        console.error("outbound sweep subscription emit failed; holding watermark:", e);
        break;
      }
    }
    if (ev.created_at > maxTs) maxTs = ev.created_at;
  }
  if (maxTs !== since) await writeWatermark(db, source, maxTs);
  return emitted;
}

Deno.serve(async (req) => {
  const requestId = req.headers.get("x-request-id") || crypto.randomUUID();
  const cronSecret = Deno.env.get("CRON_SECRET");
  const authHeader = req.headers.get("authorization");
  if (cronSecret && authHeader !== `Bearer ${cronSecret}`) {
    return new Response(JSON.stringify({ error: "Unauthorized" }), { status: 401 });
  }

  const url = Deno.env.get("SUPABASE_URL")!;
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const db: DB = createClient(url, serviceKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const result = { requestId, orders: 0, subscriptions: 0, errors: 0 };
  try {
    try {
      result.orders = await sweepOrderStatus(db, url, serviceKey);
    } catch (e) {
      result.errors++;
      console.error("outbound-webhook-sweeper order sweep error:", e);
    }
    try {
      result.subscriptions = await sweepSubscriptionEvents(db, url, serviceKey);
    } catch (e) {
      result.errors++;
      console.error("outbound-webhook-sweeper subscription sweep error:", e);
    }
    return new Response(JSON.stringify(result), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (e) {
    return new Response(
      JSON.stringify({ error: e instanceof Error ? e.message : String(e), requestId }),
      { status: 500, headers: { "Content-Type": "application/json" } },
    );
  }
});
