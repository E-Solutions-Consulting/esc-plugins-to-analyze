// Exercises the REAL triggerMatches against the REAL payloads the producers emit
// (comms-scheduler's sweep). This is the join that decides whether an order status
// change actually starts an automation — the thing that was broken.
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { triggerMatches } from "./trigger-match.ts";

// What comms-scheduler.sweepDomainEvents ACTUALLY posts for an order_status_history row.
const sweptOrder = (statusKey: string) => ({
  kind: "order",
  order_status: statusKey,
  patient_id: "p1",
  order_id: "o1",
});

Deno.test("named trigger fires on the raw status the sweeper emits", () => {
  const trigger = { kind: "order", event_key: "order.paid" };
  assertEquals(triggerMatches(trigger, sweptOrder("payment_collected")), true);
});

Deno.test("named trigger does NOT fire on an unrelated status", () => {
  const trigger = { kind: "order", event_key: "order.paid" };
  assertEquals(triggerMatches(trigger, sweptOrder("order_delivered")), false);
  assertEquals(triggerMatches(trigger, sweptOrder("provider_approved")), false);
});

Deno.test("one named event covers ALL the statuses behind it", () => {
  // "Prescription shipped" is captured from three different internal statuses.
  // A tenant picks ONE trigger and gets all of them — this is the whole point of
  // triggering on events instead of raw statuses.
  const trigger = { kind: "order", event_key: "prescription.shipped" };
  for (const s of ["in_transit", "order_shipped", "prescription_shipped"]) {
    assertEquals(triggerMatches(trigger, sweptOrder(s)), true, `${s} should fire`);
  }
});

Deno.test("LEGACY raw to_status triggers keep firing (no silent breakage)", () => {
  // Automations saved before named events store to_status. They must keep working.
  const legacy = { kind: "order", to_status: "order_delivered" };
  assertEquals(triggerMatches(legacy, sweptOrder("order_delivered")), true);
  assertEquals(triggerMatches(legacy, sweptOrder("payment_collected")), false);
});

Deno.test("real renewal fires subscription.renewed; a date edit does NOT", () => {
  const trigger = { kind: "subscription", event_key: "subscription.renewed" };

  // What the sweep emits for a PAID RENEWAL ORDER (the real renewal).
  const realRenewal = { kind: "subscription", event_key: "subscription.renewed" };
  assertEquals(triggerMatches(trigger, realRenewal), true);

  // What the DB trigger writes when an admin merely EDITS the renewal date.
  // Under the old substring logic this was delivered as a renewal. It must not fire.
  const dateEdit = { kind: "subscription", subscription_event_type: "renewal_date_changed" };
  assertEquals(triggerMatches(trigger, dateEdit), false);
});

Deno.test("renewal_date_changed still fires its OWN trigger", () => {
  const trigger = { kind: "subscription", event_key: "subscription.renewal_date_changed" };
  const dateEdit = { kind: "subscription", subscription_event_type: "renewal_date_changed" };
  assertEquals(triggerMatches(trigger, dateEdit), true);
});

Deno.test("subscription cancel fires from the captured type", () => {
  const trigger = { kind: "subscription", event_key: "subscription.cancelled" };
  assertEquals(
    triggerMatches(trigger, { kind: "subscription", subscription_event_type: "cancelled" }),
    true,
  );
});

Deno.test("kinds never cross-fire", () => {
  const orderTrigger = { kind: "order", event_key: "order.paid" };
  assertEquals(
    triggerMatches(orderTrigger, { kind: "subscription", event_key: "subscription.renewed" }),
    false,
  );
});

Deno.test("an internal status with no public event fires no named trigger", () => {
  // order_sent_to_pharmacy is real but deliberately not a public event.
  const trigger = { kind: "order", event_key: "order.paid" };
  assertEquals(triggerMatches(trigger, sweptOrder("order_sent_to_pharmacy")), false);
  // ...but the advanced raw-status escape hatch can still target it.
  const raw = { kind: "order", to_status: "order_sent_to_pharmacy" };
  assertEquals(triggerMatches(raw, sweptOrder("order_sent_to_pharmacy")), true);
});
