# Communications Automations — Scale via RTDH (Phase 2 design)

**Status:** Design proposal (no code). Feature branch `elianomarques/comms-automations`.
**Date:** 2026-06-25
**Author:** Eliano Marques (CTO) + automated design

---

## 1. The question

> "Should the comms triggers go through RTDH? If the first order webhook creates a
> flow, every new webhook just adds its address + metadata to that schema. And at
> millions of orders/day, how do we ensure scale?"

Short answer: **yes — route the *event ingest* hop through RTDH (Pub/Sub), but keep
the *journey execution* in our own engine.** This is an evolution, not a rewrite.

---

## 2. Two hops, two different tools

An automation has two very different concerns:

| Concern | Shape | Right tool |
|---|---|---|
| **Event ingest** ("an order changed") | high-volume, fire-and-forget, needs durability + fan-out | **RTDH / Pub/Sub** (+ Elasticsearch for audit/replay) |
| **Journey execution** (delay 3 days → email → branch → n8n) | stateful, long-running, per-enrollment | **Our engine** (Postgres state machine, `comms_scheduled_jobs`) |

RTDH is the **bus**; Postgres is the **state**. You would never run a 3-day timer in
Pub/Sub, and you would never poll a hot table to discover events. Put each where it
belongs.

---

## 3. What's wrong with today's ingest (be honest)

Today `comms-scheduler` **polls**:

```sql
SELECT … FROM order_status_history WHERE created_at > <watermark> ORDER BY created_at LIMIT 200;
SELECT … FROM subscription_events   WHERE created_at > <watermark> …;
```

At millions of orders/day this does not scale:
- Growing scan against a hot, append-heavy table on every tick.
- Latency = poll interval (not real-time).
- Single serial drain loop (stop-on-failure) — no horizontal parallelism.

This is interim, demo-grade. RTDH fixes exactly this hop.

---

## 4. The key finding: the bus already exists

RTDH **already receives PP order/subscription events** — we do not need to invent a
publish path:

- `order-lifecycle/rtdh-helper.ts` already POSTs `event_type: "order_status_updated"`
  to RTDH for a defined set of status keys (shipped, approved, cancelled, …).
- RTDH already has topics: **`pp_order_events`**, **`rt_data_hub_order_events`**,
  **`rt_data_hub_subscription_events`**, **`stripe_events`**, plus the outbound
  fan-out topic **`pp-outbound-webhook-events`**.
- The outbound-webhook model is **data-only**: one topic + one fan-out function that
  reads a table; **adding a webhook is a row, not infra.** (`tenant_outbound_webhooks`.)

So your intuition — *"the first order webhook creates the flow; every new one just adds
its address + metadata"* — is already the production pattern. We extend it, we don't
build it.

---

## 5. Proposed architecture

```
Order/subscription/analytics event in PP
        │  (published at WRITE time by order-lifecycle/etc. — already happens for orders)
        ▼
   RTDH ingest  ──►  Pub/Sub topic (reuse pp_order_events / rt_data_hub_subscription_events)
        │                 │   durable · at-least-once · 7-day retention
        │                 ├──► Elasticsearch index   (audit / search / replay — RTDH already does this)
        │                 ├──► subscriber: tenant outbound webhooks   (EXISTS today)
        │                 └──► subscriber: comms-event-dispatcher      (NEW — push subscription)
        ▼                              │  match active automations → create enrollments
   (no polling)                        ▼
                          Our engine runs the journey
                          (Postgres state · delays via comms_scheduled_jobs · n8n node POSTs direct)
```

**The single change that matters:** replace the `comms-scheduler` *event sweep* with a
**Pub/Sub push subscription** that delivers each event to `comms-event-dispatcher`.
The scheduler keeps only its **delay queue** job — a small, indexed
`WHERE run_at <= now()` query, not a firehose scan.

### What stays exactly as-is
- `comms-event-dispatcher` (already idempotent via `comms_enrollments.dedup_key`).
- `comms-execute-node` state machine, delays, branches, n8n node (still POSTs direct
  to n8n — that hop is synchronous-by-design and does not go through RTDH).
- The whole builder/UI.

### What's new / changes
- A **Pub/Sub push subscription** → `comms-event-dispatcher` (the dispatcher gains a
  thin "from Pub/Sub envelope" adapter alongside its existing internal-secret path).
- Remove `sweepDomainEvents` from `comms-scheduler` (and the `comms_event_sweep_state`
  watermark table becomes unused — drop in a later migration).
- For events not yet published to RTDH (analytics, relative-time): either publish them
  to a topic too, or keep the lightweight inline/scheduled path for those only.

---

## 6. Scale levers + gaps to close (millions/day)

| Concern | RTDH today | Action for scale |
|---|---|---|
| Ingest durability | Pub/Sub at-least-once ✅ | none |
| Buffering / backpressure | Pub/Sub IS the buffer ✅ | dispatcher pulls at its own rate |
| Fan-out autoscale | Cloud Functions gen2, **max 10 instances** ⚠️ | raise `maxInstances` on the comms subscriber; this is config, not architecture |
| Retry | 3 attempts exp-backoff (fan-out) ✅ | reuse for delivery |
| **Dead-letter** | exists for some topics (`*_dlq`, `max_delivery_attempts=5`), **not** for the outbound topic ⚠️ | **add a DLQ** for the comms subscription so a poisoned event doesn't wedge the stream |
| **Ordering** | `enable_message_ordering=false` everywhere ⚠️ | **enable ordering keyed by entity id (order_id/subscription_id)** so a journey can't fire on a stale state (`created` then `paid` out of order). Dispatcher should also re-read current entity state at enroll time as a guard |
| Idempotency | content-hash / event-id dedup ✅ | dispatcher already dedups via `dedup_key` — belt and suspenders |
| Hot-partition risk | one tenant flooding | per-tenant concurrency / ordering key includes tenant_id; consider per-tenant quota (the n8n capacity doc already flagged single-tenant starvation) |

**Capacity math (rough):** gen2 CF at ~80 concurrent/instance × raised max-instances.
The dispatcher does cheap work (match + insert enrollment); the expensive work
(sending) is already decoupled into the engine + `comms_scheduled_jobs`. Pub/Sub
absorbs spikes; the engine drains steadily. This is the standard "ingest fast, process
async" shape RTDH already uses for inbound provider webhooks.

---

## 7. "Add a webhook = add a row" — how it maps

Your model is exactly the existing outbound pattern, generalised:

- **Infra is created once per event-type topic** (already exists for orders/subs).
- **A new automation trigger is a data row** (`comms_automations.trigger_config`) — the
  dispatcher reads active automations at event time and matches, identical to how the
  fan-out function reads `tenant_outbound_webhooks`.
- **No per-automation Pub/Sub subscription, no per-tenant infra.** Provisioning the
  tenant's n8n *project/folder* (already built) is the only per-tenant resource, and
  that's in n8n, not Pub/Sub.

So adding the 1,000th automation costs **one row**, not one subscription.

---

## 8. Trade-offs to accept deliberately

1. **New coupling:** today the comms path has ZERO RTDH dependency (verified). Routing
   ingest through RTDH means an RTDH/Pub/Sub outage delays triggers. Mitigation:
   Pub/Sub buffers + replays, so it's a *delay*, not a *loss* — but it is real
   operational coupling.
2. **Ordering correctness:** without ordering keys, out-of-order events can mis-fire a
   journey. Closing this (ordering key + enroll-time state re-read) is **required**
   before high volume, not optional.
3. **n8n is still the downstream bottleneck:** even with perfect ingest, the n8n
   instance (shared with internal automations, capacity already flagged) must scale.
   Per-tenant n8n quotas belong on the roadmap.

---

## 9. Recommended rollout

1. **Phase 1 (now):** ship the direct/polling path — works for the demo and low volume.
2. **Phase 2a:** add a Pub/Sub **push subscription → comms-event-dispatcher** for orders
   + subscriptions (topics already fed). Remove the scheduler sweep for those. Add DLQ.
3. **Phase 2b:** enable **ordering keys** (entity id) + enroll-time state re-read guard.
4. **Phase 2c:** publish analytics + relative-time anchors to a topic too (or keep their
   light path). Raise `maxInstances`. Add per-tenant quotas.
5. **Phase 3:** per-tenant n8n execution quotas; revisit n8n horizontal scale.

Phases 2–3 touch **three repos**: `patient-platform-admin` (dispatcher adapter, remove
sweep), `rt-data-hub-functions` (subscriber/DLQ if a dedicated comms subscriber is
preferred over reusing the fan-out), `allia-infrastructure` (Pub/Sub subscription +
DLQ + ordering terraform). Each is a separate PR.

---

## 10. Decision needed before building Phase 2

- Reuse existing `pp_order_events` / `rt_data_hub_subscription_events` topics, or mint a
  dedicated `comms-domain-events` topic? (Reuse = less infra; dedicated = cleaner
  isolation + independent scaling/DLQ.)
- Does the comms dispatcher subscribe **directly** to the topic, or does RTDH's existing
  processor enrich first and then re-emit a normalised event the dispatcher consumes?
  (Enriched = the dispatcher gets clean, tenant-resolved events; direct = fewer hops.)

These two choices set the Phase-2 shape and are worth deciding together with the RTDH
owner.
