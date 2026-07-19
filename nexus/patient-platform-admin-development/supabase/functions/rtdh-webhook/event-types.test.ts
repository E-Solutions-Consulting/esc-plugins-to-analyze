import { assertEquals } from "../_test/assert.ts";
import { isSupportedEventType, SUPPORTED_EVENT_TYPES } from "./event-types.ts";

Deno.test("isSupportedEventType accepts shipping_exception RTDH master object payload events", () => {
  assertEquals(isSupportedEventType("shipping_exception"), true);
  assertEquals(SUPPORTED_EVENT_TYPES.includes("shipping_exception"), true);
});

Deno.test("isSupportedEventType preserves existing EasyPost delivery events", () => {
  assertEquals(isSupportedEventType("in_transit"), true);
  assertEquals(isSupportedEventType("delivered"), true);
});

Deno.test("isSupportedEventType accepts order_cancelled RTDH master object payload events", () => {
  assertEquals(isSupportedEventType("order_cancelled"), true);
  assertEquals(SUPPORTED_EVENT_TYPES.includes("order_cancelled"), true);
});

Deno.test("isSupportedEventType accepts medical_followup_required (Telegra hold, not a rejection)", () => {
  assertEquals(isSupportedEventType("medical_followup_required"), true);
  assertEquals(
    SUPPORTED_EVENT_TYPES.includes("medical_followup_required"),
    true,
  );
});

Deno.test("isSupportedEventType accepts order_validation_pending RTDH master object payload events", () => {
  assertEquals(isSupportedEventType("order_validation_pending"), true);
  assertEquals(
    SUPPORTED_EVENT_TYPES.includes("order_validation_pending"),
    true,
  );
});
