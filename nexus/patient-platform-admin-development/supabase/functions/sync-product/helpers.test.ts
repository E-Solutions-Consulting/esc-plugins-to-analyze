import { assertEquals, assertMatch } from "../_test/assert.ts";
import { mapToStripeInterval } from "./helpers.ts";

Deno.test("mapToStripeInterval maps supported intervals and defaults to month", () => {
  assertEquals(mapToStripeInterval("day"), "day");
  assertEquals(mapToStripeInterval("week"), "week");
  assertEquals(mapToStripeInterval("month"), "month");
  assertEquals(mapToStripeInterval("year"), "year");
  assertEquals(mapToStripeInterval("invalid"), "month");
});
