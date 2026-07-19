import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import {
  eventForStatusKey,
  eventForSubscriptionType,
  eventForUsageName,
  eventForUsageType,
  isRenewalPaidEvent,
  statusKeysForEvent,
  subscriptionTypeForEvent,
} from "./platform-events.ts";

Deno.test("order status keys map to their public event", () => {
  assertEquals(eventForStatusKey("payment_collected"), "order.paid");
  assertEquals(eventForStatusKey("provider_approved"), "provider.approved");
  assertEquals(eventForStatusKey("order_delivered"), "order.delivered");
  // Several shipping statuses collapse to one public event.
  assertEquals(eventForStatusKey("in_transit"), "prescription.shipped");
  assertEquals(eventForStatusKey("order_shipped"), "prescription.shipped");
});

Deno.test("internal-only statuses are not public events", () => {
  assertEquals(eventForStatusKey("order_sent_to_pharmacy"), null);
  assertEquals(eventForStatusKey(null), null);
  assertEquals(eventForStatusKey(undefined), null);
});

Deno.test("statuses the OLD comms trigger catalog offered do not exist", () => {
  // These were selectable as triggers but are not real status_keys, so an
  // automation configured with one could never fire. Regression guard: they must
  // never resolve to an event.
  for (const phantom of ["order_approved", "pharmacy_approved", "delivered", "shipped"]) {
    assertEquals(eventForStatusKey(phantom), null, `${phantom} must not be a real status`);
  }
});

Deno.test("subscription event types map explicitly, NOT by substring", () => {
  assertEquals(eventForSubscriptionType("cancelled"), "subscription.cancelled");
  assertEquals(eventForSubscriptionType("paused"), "subscription.paused");
  assertEquals(eventForSubscriptionType("resumed"), "subscription.resumed");
  assertEquals(eventForSubscriptionType("created"), "subscription.created");
});

Deno.test("renewal_date_changed is NOT a renewal (the false-renewal bug)", () => {
  // The old sweeper used `event_type.includes("renew")`, so merely EDITING a
  // renewal date delivered a "subscription renewed" webhook. It must now map to
  // its own event, and never to subscription.renewed.
  assertEquals(
    eventForSubscriptionType("renewal_date_changed"),
    "subscription.renewal_date_changed",
  );
});

Deno.test("no subscription_events type ever produces subscription.renewed", () => {
  // A real renewal is a paid renewal ORDER, not a subscriptions-table event.
  const allCapturedTypes = [
    "created", "cancelled", "paused", "resumed", "status_changed",
    "renewal_date_changed", "expiration_date_changed", "lifecycle_updated",
  ];
  for (const t of allCapturedTypes) {
    const ev = eventForSubscriptionType(t);
    assertEquals(ev === "subscription.renewed", false, `${t} must not mean renewed`);
  }
  assertEquals(subscriptionTypeForEvent("subscription.renewed"), null);
});

Deno.test("internal subscription bookkeeping types are not public", () => {
  assertEquals(eventForSubscriptionType("status_changed"), null);
  assertEquals(eventForSubscriptionType("lifecycle_updated"), null);
  assertEquals(eventForSubscriptionType("expiration_date_changed"), null);
});

Deno.test("a real renewal = a paid order classified 'renewal'", () => {
  assertEquals(isRenewalPaidEvent("payment_collected", "renewal"), true);
  // The FIRST order of a subscription is not a renewal.
  assertEquals(isRenewalPaidEvent("payment_collected", "initial"), false);
  // A non-payment transition on a renewal order is not a renewal event.
  assertEquals(isRenewalPaidEvent("order_shipped", "renewal"), false);
  // A one-off (non-subscription) order has no classification.
  assertEquals(isRenewalPaidEvent("payment_collected", null), false);
});

Deno.test("reverse lookup returns every status behind an event", () => {
  assertEquals(statusKeysForEvent("order.paid"), ["payment_collected"]);
  const shipped = statusKeysForEvent("prescription.shipped");
  assertEquals(shipped.length, 3);
  assertEquals(shipped.includes("in_transit"), true);
  // Round-trips: each status behind an event maps back to that event.
  for (const key of shipped) {
    assertEquals(eventForStatusKey(key), "prescription.shipped");
  }
});

Deno.test("named behavioral events get their first-class usage key", () => {
  assertEquals(eventForUsageName("login"), "usage.login");
  assertEquals(eventForUsageName("checkout_completed"), "usage.checkout_completed");
  // page_leave is real but deliberately not first-class — coarse fallback.
  assertEquals(eventForUsageName("page_leave"), null);
  assertEquals(eventForUsageName(null), null);
});

Deno.test("usage types fall back to the generic activity event", () => {
  assertEquals(eventForUsageType("page_view"), "usage.page_view");
  // session events were removed from the catalog (never emitted by the SDK) —
  // if a producer ever sends them, they surface as generic activity events.
  assertEquals(eventForUsageType("session_start"), "usage.activity_event");
  // Unknown types are surfaced, never dropped.
  assertEquals(eventForUsageType("something_new"), "usage.activity_event");
  assertEquals(eventForUsageType(null), "usage.activity_event");
});
