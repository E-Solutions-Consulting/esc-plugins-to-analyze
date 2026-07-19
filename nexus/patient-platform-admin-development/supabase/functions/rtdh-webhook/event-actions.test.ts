import { assertEquals } from "../_test/assert.ts";
import {
  extractRtdhEventId,
  getOrderLinkedNextStatusId,
  resolveForwardEventType,
  shouldApplyDirectStatusTransition,
  shouldInsertOrderHistoryForDirectStatusEvent,
  shouldTriggerOrderLifecycleForDirectStatusEvent,
} from "./event-actions.ts";

Deno.test("extractRtdhEventId prefers the x-rtdh-event-id header", () => {
  assertEquals(
    extractRtdhEventId(
      { timeline: [{ event_id: "evt-from-timeline" }] },
      { "x-rtdh-event-id": "evt-from-header" },
    ),
    "evt-from-header",
  );
});

Deno.test("extractRtdhEventId falls back to the latest timeline event_id", () => {
  assertEquals(
    extractRtdhEventId(
      {
        timeline: [
          { event_id: "evt-old" },
          { event_id: "evt-latest" },
        ],
      },
      {},
    ),
    "evt-latest",
  );
});

Deno.test("extractRtdhEventId skips timeline entries missing an event_id", () => {
  assertEquals(
    extractRtdhEventId(
      {
        timeline: [
          { event_id: "evt-present" },
          { event_id: "" },
          { status: "no_event_id_here" },
        ],
      },
      {},
    ),
    "evt-present",
  );
});

Deno.test("extractRtdhEventId returns null when no event id is available", () => {
  assertEquals(extractRtdhEventId({ timeline: [] }, {}), null);
  assertEquals(extractRtdhEventId({}, {}), null);
});

Deno.test("resolveForwardEventType prefers semantic event_type over order_status_key", () => {
  assertEquals(
    resolveForwardEventType({
      event_type: "order.fulfillment_linked",
      global_status: "order.fulfillment_linked",
      order_status_key: "provider_order_creation_pending",
    }),
    "order.fulfillment_linked",
  );
});

Deno.test("resolveForwardEventType uses semantic global_status when event_type is missing", () => {
  assertEquals(
    resolveForwardEventType({
      global_status: "order.linked",
      order_status_key: null,
    }),
    "order.linked",
  );
});

Deno.test("resolveForwardEventType uses latest semantic timeline event before order_status_key", () => {
  assertEquals(
    resolveForwardEventType({
      order_status_key: "provider_order_creation_pending",
      timeline: [
        { event_type: "order.linked" },
        { event_type: "order.fulfillment_linked" },
      ],
    }),
    "order.fulfillment_linked",
  );
});

Deno.test("resolveForwardEventType uses order_status_key for provider raw status events", () => {
  assertEquals(
    resolveForwardEventType({
      event_type: "case_assigned_to_clinician",
      order_status_key: "provider_review_pending",
    }),
    "provider_review_pending",
  );
});

Deno.test("resolveForwardEventType uses direct global_status before non-semantic event_type", () => {
  assertEquals(
    resolveForwardEventType({
      event_type: "provider_status_changed",
      global_status: "provider_review_pending",
    }),
    "provider_review_pending",
  );
});

Deno.test("resolveForwardEventType falls back to event_type when no status applies", () => {
  assertEquals(
    resolveForwardEventType({
      event_type: "case_assigned_to_clinician",
      global_status: "case_assigned_to_clinician",
    }),
    "case_assigned_to_clinician",
  );
});

Deno.test("getOrderLinkedNextStatusId returns the next status only from order_created", () => {
  assertEquals(
    getOrderLinkedNextStatusId({
      status_key: "order_created",
      next_status_id: "payment_pending_status_id",
    }),
    "payment_pending_status_id",
  );
});

Deno.test("getOrderLinkedNextStatusId does not advance from later statuses", () => {
  assertEquals(
    getOrderLinkedNextStatusId({
      status_key: "payment_pending",
      next_status_id: "payment_collected_status_id",
    }),
    null,
  );
});

Deno.test("getOrderLinkedNextStatusId does not advance without an order_created next status", () => {
  assertEquals(
    getOrderLinkedNextStatusId({
      status_key: "order_created",
      next_status_id: null,
    }),
    null,
  );
});

Deno.test("provider_rejected direct status events trigger order-lifecycle", () => {
  assertEquals(
    shouldTriggerOrderLifecycleForDirectStatusEvent("provider_rejected"),
    true,
  );
});

Deno.test("shipping_exception direct status events trigger order-lifecycle", () => {
  assertEquals(
    shouldTriggerOrderLifecycleForDirectStatusEvent("shipping_exception"),
    true,
  );
});

Deno.test("order_validation_pending direct status events trigger order-lifecycle", () => {
  assertEquals(
    shouldTriggerOrderLifecycleForDirectStatusEvent("order_validation_pending"),
    true,
  );
});

Deno.test("migrated direct status events do not trigger order-lifecycle", () => {
  assertEquals(
    shouldTriggerOrderLifecycleForDirectStatusEvent("provider_rejected", {
      skipLifecycle: true,
    }),
    false,
  );
});

Deno.test("shouldInsertOrderHistoryForDirectStatusEvent skips duplicate status history entries", () => {
  assertEquals(
    shouldInsertOrderHistoryForDirectStatusEvent(
      "payment_collected",
      "payment_collected",
    ),
    false,
  );
});

Deno.test("shouldInsertOrderHistoryForDirectStatusEvent allows history for real transitions", () => {
  assertEquals(
    shouldInsertOrderHistoryForDirectStatusEvent(
      "payment_pending",
      "payment_collected",
    ),
    true,
  );
});

Deno.test("shouldApplyDirectStatusTransition blocks stale status regressions", () => {
  assertEquals(
    shouldApplyDirectStatusTransition(
      { display_order: 10 },
      { display_order: 8 },
    ),
    false,
  );
});

Deno.test("shouldApplyDirectStatusTransition lets payment_collected recover a payment_failed order", () => {
  // payment_failed ranks above payment_collected in display_order, but a
  // confirmed payment must always win (card-update retry, manual invoice
  // payment, admin-initiated charge).
  assertEquals(
    shouldApplyDirectStatusTransition(
      { display_order: 21, status_key: "payment_failed" },
      { display_order: 9, status_key: "payment_collected" },
    ),
    true,
  );
  // Other regressions into/out of payment states stay blocked.
  assertEquals(
    shouldApplyDirectStatusTransition(
      { display_order: 21, status_key: "payment_failed" },
      { display_order: 8, status_key: "payment_pending" },
    ),
    false,
  );
});

Deno.test("shouldApplyDirectStatusTransition allows forward and same-order transitions", () => {
  assertEquals(
    shouldApplyDirectStatusTransition(
      { display_order: 8 },
      { display_order: 10 },
    ),
    true,
  );
  assertEquals(
    shouldApplyDirectStatusTransition(
      { display_order: 8 },
      { display_order: 8 },
    ),
    true,
  );
});
