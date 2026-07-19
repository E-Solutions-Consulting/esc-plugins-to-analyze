// comms-scheduler — cron-invoked tick.
//
// SCHEDULED BY pg_cron -> pg_net -> this function; see the migration
// supabase/migrations/20260714160000_comms_scheduler_cron.sql. (Supabase's
// config.toml has NO schedule key — an earlier version of this comment claimed
// otherwise, and as a result nothing ever invoked this function: order and
// subscription triggers never fired and delays never resumed.)
//
// Three jobs:
//   1) Sweep new order_status_history / subscription_events rows since a
//      watermark and emit them to comms-event-dispatcher. This is the ONLY
//      producer for order/subscription automation triggers.
//   2) Drain due comms_scheduled_jobs (delays / wait_until resumes) by claiming
//      them and invoking comms-execute-node for the parked node.
//   3) Materialise relative-time triggers ("N days before renewal", "N days after
//      purchase") into enrollments by querying subscriptions/orders for matches.
//
// Authorized via CRON_SECRET (Supabase cron has no auth header) — mirrors
// reminder-scheduler. Service-role.

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { isRenewalPaidEvent } from "../_shared/platform-events.ts";

// deno-lint-ignore no-explicit-any
type DB = any;

const BATCH = 100;
const SWEEP_LIMIT = 200;
// Re-kick active enrollments that haven't progressed in this many minutes and
// aren't parked in a delay (covers a lost fire-and-forget chain in execute-node).
const STUCK_AFTER_MINUTES = 10;
const RECOVER_LIMIT = 100;

/** Re-kick active enrollments that appear stuck (no open scheduled job, idle). */
async function recoverStuckEnrollments(
  db: DB,
  baseUrl: string,
  internalSecret: string | undefined,
  now: Date,
): Promise<number> {
  const cutoff = new Date(now.getTime() - STUCK_AFTER_MINUTES * 60 * 1000).toISOString();
  const { data: stuck } = await db
    .from("comms_enrollments")
    .select("id, current_node_id")
    .eq("status", "active")
    .not("current_node_id", "is", null)
    .lt("updated_at", cutoff)
    .limit(RECOVER_LIMIT);

  let recovered = 0;
  for (const e of (stuck ?? [])) {
    // Skip if a scheduled job is still pending for this enrollment (it's a delay,
    // not a stuck hop — the job drain will handle it).
    const { count } = await db
      .from("comms_scheduled_jobs")
      .select("id", { count: "exact", head: true })
      .eq("enrollment_id", e.id)
      .is("completed_at", null);
    if ((count ?? 0) > 0) continue;

    try {
      await fetch(`${baseUrl}/functions/v1/comms-execute-node`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...(internalSecret ? { Authorization: `Bearer ${internalSecret}` } : {}),
        },
        body: JSON.stringify({ enrollment_id: e.id, node_id: e.current_node_id }),
      });
      recovered++;
    } catch (err) {
      console.error("recover stuck enrollment failed:", err);
    }
  }
  return recovered;
}

/**
 * Sweep new subscription_events + order_status_history rows since the last
 * watermark and emit them to comms-event-dispatcher. Returns the number emitted.
 *
 * Why sweep rather than emit inline: order status is written by ~22 different code
 * paths AND by DB triggers with no TypeScript in the path, so inline emits could
 * never be complete. (pg_net IS available, but a per-row DB trigger would put an
 * HTTP call inside the order-write transaction — coupling order fulfilment to
 * comms/n8n availability, and firing for rows that later roll back.) Reading
 * COMMITTED rows since a watermark catches every writer with no coupling.
 *
 * Mirrors outbound-webhook-sweeper, which does the same over the same two tables.
 */
async function sweepDomainEvents(
  db: DB,
  baseUrl: string,
  internalSecret: string | undefined,
): Promise<number> {
  let emitted = 0;
  const post = (payload: Record<string, unknown>) =>
    fetch(`${baseUrl}/functions/v1/comms-event-dispatcher`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(internalSecret ? { Authorization: `Bearer ${internalSecret}` } : {}),
      },
      body: JSON.stringify(payload),
    });

  // --- subscription_events ---
  {
    const { data: state } = await db
      .from("comms_event_sweep_state").select("last_swept_at")
      .eq("source", "subscription_events").maybeSingle();
    const since = state?.last_swept_at ?? new Date(0).toISOString();
    const { data: events } = await db
      .from("subscription_events")
      .select("id, tenant_id, patient_id, subscription_id, event_type, created_at")
      .gt("created_at", since)
      .order("created_at", { ascending: true })
      .limit(SWEEP_LIMIT);

    // Advance the watermark only past rows we actually dispatched, so a failed
    // emit is retried next tick (at-least-once; the dispatcher dedups doubles).
    let maxTs = since;
    for (const ev of (events ?? [])) {
      try {
        await post({
          tenant_id: ev.tenant_id,
          kind: "subscription",
          subscription_event_type: ev.event_type,
          patient_id: ev.patient_id,
          subscription_id: ev.subscription_id,
          entity_id: ev.id,
        });
        emitted++;
        if (ev.created_at > maxTs) maxTs = ev.created_at;
      } catch (e) {
        console.error("sweep sub emit failed; stopping advance at this row:", e);
        break; // don't skip past an undelivered event
      }
    }
    if (maxTs !== since) {
      await db.from("comms_event_sweep_state")
        .upsert({ source: "subscription_events", last_swept_at: maxTs, updated_at: new Date().toISOString() },
          { onConflict: "source" });
    }
  }

  // --- order_status_history (join orders for tenant/patient, statuses for key) ---
  {
    const { data: state } = await db
      .from("comms_event_sweep_state").select("last_swept_at")
      .eq("source", "order_status_history").maybeSingle();
    const since = state?.last_swept_at ?? new Date(0).toISOString();
    const { data: rows } = await db
      .from("order_status_history")
      .select(
        "id, created_at, order_id, orders(tenant_id, patient_id, subscription_id, subscription_order_type), order_statuses:status_id(status_key)",
      )
      .gt("created_at", since)
      .order("created_at", { ascending: true })
      .limit(SWEEP_LIMIT);

    let maxTs = since;
    for (const r of (rows ?? [])) {
      const order = (r.orders ?? {}) as {
        tenant_id?: string;
        patient_id?: string;
        subscription_id?: string;
        subscription_order_type?: string;
      };
      const statusKey = (r.order_statuses as { status_key?: string } | null)?.status_key;
      // A row with no resolvable tenant/status is data we can't act on — advance
      // past it (it will never become emittable) rather than blocking the sweep.
      if (order.tenant_id && statusKey) {
        try {
          await post({
            tenant_id: order.tenant_id,
            kind: "order",
            order_status: statusKey,
            patient_id: order.patient_id,
            order_id: r.order_id,
            entity_id: r.id,
          });
          emitted++;

          // A REAL renewal: a `renewal`-classified order was paid. Emitted as a
          // subscription event so "subscription.renewed" triggers fire — the
          // subscription_events table never records a renewal (see
          // _shared/platform-events.ts), so this is the only honest source, and
          // it matches what Outbound Webhooks now emit for the same row.
          if (isRenewalPaidEvent(statusKey, order.subscription_order_type)) {
            await post({
              tenant_id: order.tenant_id,
              kind: "subscription",
              event_key: "subscription.renewed",
              patient_id: order.patient_id,
              subscription_id: order.subscription_id,
              order_id: r.order_id,
              entity_id: `renewed:${r.id}`,
            });
            emitted++;
          }
        } catch (e) {
          console.error("sweep order emit failed; stopping advance at this row:", e);
          break;
        }
      }
      if (r.created_at > maxTs) maxTs = r.created_at;
    }
    if (maxTs !== since) {
      await db.from("comms_event_sweep_state")
        .upsert({ source: "order_status_history", last_swept_at: maxTs, updated_at: new Date().toISOString() },
          { onConflict: "source" });
    }
  }

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
  const internalSecret = Deno.env.get("COMMS_INTERNAL_SECRET");
  const db: DB = createClient(url, serviceKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const now = new Date();
  const result = { requestId, drained: 0, relative_enrolled: 0, swept: 0, recovered: 0, errors: 0 };

  try {
    // --- 0) Sweep new subscription/order domain events -> dispatcher ---
    try {
      result.swept = await sweepDomainEvents(db, url, internalSecret);
    } catch (e) {
      result.errors++;
      console.error("comms-scheduler sweep error:", e);
    }

    // --- 0b) Recover enrollments stuck after a lost chain hop ---
    try {
      result.recovered = await recoverStuckEnrollments(db, url, internalSecret, now);
    } catch (e) {
      result.errors++;
      console.error("comms-scheduler recovery error:", e);
    }

    // --- 1) Drain due scheduled jobs ---
    const { data: dueJobs } = await db
      .from("comms_scheduled_jobs")
      .select("*")
      .is("completed_at", null)
      .is("claimed_at", null)
      .lte("run_at", now.toISOString())
      .order("run_at", { ascending: true })
      .limit(BATCH);

    for (const job of (dueJobs ?? [])) {
      // Claim atomically: only proceed if still unclaimed.
      const { data: claimed } = await db
        .from("comms_scheduled_jobs")
        .update({ claimed_at: now.toISOString(), claimed_by: requestId, attempts: (job.attempts ?? 0) + 1 })
        .eq("id", job.id)
        .is("claimed_at", null)
        .select("id")
        .maybeSingle();
      if (!claimed) continue; // lost the race to another tick

      try {
        if (job.job_kind === "advance" && job.enrollment_id) {
          await fetch(`${url}/functions/v1/comms-execute-node`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              ...(internalSecret ? { Authorization: `Bearer ${internalSecret}` } : {}),
            },
            body: JSON.stringify({ enrollment_id: job.enrollment_id, node_id: job.node_id }),
          });
        }
        await db.from("comms_scheduled_jobs")
          .update({ completed_at: new Date().toISOString() })
          .eq("id", job.id);
        result.drained++;
      } catch (e) {
        result.errors++;
        await db.from("comms_scheduled_jobs")
          .update({ last_error: String(e), claimed_at: null })
          .eq("id", job.id);
      }
    }

    // --- 2) Materialise relative-time triggers ---
    // For each active automation with a relative_time trigger, find subscriptions
    // (or orders) whose anchor date is exactly `offset_days` away and dispatch.
    const { data: relAutomations } = await db
      .from("comms_automations")
      .select("id, tenant_id, trigger_config")
      .eq("status", "active")
      .contains("trigger_config", { kind: "relative_time" });

    for (const a of (relAutomations ?? [])) {
      const cfg = (a.trigger_config ?? {}) as Record<string, unknown>;
      const anchor = String(cfg.anchor ?? "renewal");
      const direction = String(cfg.direction ?? "before"); // before | after
      const days = Number(cfg.offset_days ?? 0);

      // Compute the target calendar date the anchor column should equal.
      // before => anchor_date = today + days ; after => anchor_date = today - days
      const target = new Date(now);
      target.setUTCHours(0, 0, 0, 0);
      target.setUTCDate(target.getUTCDate() + (direction === "before" ? days : -days));
      const dayStart = target.toISOString();
      const dayEnd = new Date(target.getTime() + 24 * 60 * 60 * 1000).toISOString();

      let matches: Array<Record<string, unknown>> = [];
      if (anchor === "renewal" || anchor === "purchase") {
        const col = anchor === "renewal" ? "current_period_end_at" : "started_at";
        const { data } = await db
          .from("subscriptions")
          .select("id, patient_id, status, started_at, current_period_end_at, product_id")
          .eq("tenant_id", a.tenant_id)
          .eq("status", "active")
          .gte(col, dayStart)
          .lt(col, dayEnd);
        matches = data ?? [];
      } else if (anchor === "order_shipped" || anchor === "order_delivered") {
        const col = anchor === "order_shipped" ? "shipped_at" : "delivered_at";
        const { data } = await db
          .from("orders")
          .select("id, patient_id, status, order_number, shipped_at, delivered_at, total_cents")
          .eq("tenant_id", a.tenant_id)
          .gte(col, dayStart)
          .lt(col, dayEnd);
        matches = data ?? [];
      }

      for (const m of matches) {
        const isSub = anchor === "renewal" || anchor === "purchase";
        await fetch(`${url}/functions/v1/comms-event-dispatcher`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            ...(internalSecret ? { Authorization: `Bearer ${internalSecret}` } : {}),
          },
          body: JSON.stringify({
            tenant_id: a.tenant_id,
            kind: "relative_time",
            patient_id: m.patient_id,
            entity_id: m.id,
            context: isSub ? { subscription: m } : { order: m },
            // Re-use the dispatcher's matching: stamp the same kind on the automation trigger.
            _automation_id: a.id,
          }),
        }).then(() => result.relative_enrolled++).catch((e) => {
          result.errors++;
          console.error("relative dispatch failed:", e);
        });
      }
    }

    return new Response(JSON.stringify({ ok: true, ...result }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  } catch (error) {
    console.error("comms-scheduler error:", error);
    return new Response(
      JSON.stringify({ error: error instanceof Error ? error.message : "error", ...result }),
      { status: 500 },
    );
  }
});
