import { assertEquals } from "../_test/assert.ts";
import {
  buildPublishEnvelope,
  eventsAreValidForType,
  webhookTypeForEvent,
} from "./helpers.ts";

Deno.test("webhookTypeForEvent maps lifecycle events", () => {
  assertEquals(webhookTypeForEvent("order.paid"), "lifecycle");
  assertEquals(webhookTypeForEvent("provider.approved"), "lifecycle");
  assertEquals(webhookTypeForEvent("subscription.cancelled"), "lifecycle");
});

Deno.test("webhookTypeForEvent maps product_usage events", () => {
  assertEquals(webhookTypeForEvent("usage.page_view"), "product_usage");
  // Named behavioral events are product_usage too.
  assertEquals(webhookTypeForEvent("usage.login"), "product_usage");
  assertEquals(webhookTypeForEvent("usage.checkout_completed"), "product_usage");
  // Session events were removed from the catalog (never emitted by the SDK).
  assertEquals(webhookTypeForEvent("usage.session_started"), null);
});

Deno.test("webhookTypeForEvent returns null for unknown events", () => {
  assertEquals(webhookTypeForEvent("not.a.real.event"), null);
});

Deno.test("eventsAreValidForType accepts events of the matching type", () => {
  assertEquals(
    eventsAreValidForType("lifecycle", ["order.paid", "order.delivered"]),
    true,
  );
  assertEquals(
    eventsAreValidForType("product_usage", ["usage.page_view"]),
    true,
  );
});

Deno.test("eventsAreValidForType rejects MIXED types (no mixing rule)", () => {
  // A lifecycle webhook may not subscribe to a product_usage event.
  assertEquals(
    eventsAreValidForType("lifecycle", ["order.paid", "usage.page_view"]),
    false,
  );
  // ...and vice-versa.
  assertEquals(
    eventsAreValidForType("product_usage", ["usage.page_view", "order.paid"]),
    false,
  );
});

Deno.test("eventsAreValidForType rejects unknown events", () => {
  assertEquals(eventsAreValidForType("lifecycle", ["bogus.event"]), false);
});

Deno.test("buildPublishEnvelope carries event, type, tenant and subscriptions", () => {
  const env = buildPublishEnvelope({
    eventKey: "order.paid",
    type: "lifecycle",
    tenantId: "t_1",
    occurredAt: "2026-06-23T00:00:00.000Z",
    data: { orderId: "o_1" },
    subscriptions: [
      { webhookId: "wh_1", targetUrl: "https://a.example/hook", signingSecret: "s1" },
      { webhookId: "wh_2", targetUrl: "https://b.example/hook", signingSecret: "s2" },
    ],
  });
  assertEquals(env.event, "order.paid");
  assertEquals(env.type, "lifecycle");
  assertEquals(env.tenantId, "t_1");
  assertEquals(env.occurredAt, "2026-06-23T00:00:00.000Z");
  assertEquals(env.subscriptions.length, 2);
  assertEquals(env.subscriptions[0].targetUrl, "https://a.example/hook");
  assertEquals(env.subscriptions[1].signingSecret, "s2");
});
