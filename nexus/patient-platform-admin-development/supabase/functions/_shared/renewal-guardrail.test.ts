import { assertEquals } from "../_test/assert.ts";
import {
  DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS,
  getRenewalAdvanceMaxDays,
  getRenewalAdvanceMaxWeeks,
} from "./renewal-guardrail.ts";

Deno.test("getRenewalAdvanceMaxWeeks returns configured value", () => {
  assertEquals(getRenewalAdvanceMaxWeeks({ renewal_advance_max_weeks: 4 }), 4);
  assertEquals(getRenewalAdvanceMaxWeeks({ renewal_advance_max_weeks: 0 }), 0);
});

Deno.test("getRenewalAdvanceMaxWeeks unwraps nested array selects", () => {
  assertEquals(
    getRenewalAdvanceMaxWeeks([{ renewal_advance_max_weeks: 6 }]),
    6,
  );
});

Deno.test("getRenewalAdvanceMaxWeeks falls back to default", () => {
  assertEquals(
    getRenewalAdvanceMaxWeeks(null),
    DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS,
  );
  assertEquals(
    getRenewalAdvanceMaxWeeks({}),
    DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS,
  );
  assertEquals(
    getRenewalAdvanceMaxWeeks({ renewal_advance_max_weeks: -3 }),
    DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS,
  );
  assertEquals(
    getRenewalAdvanceMaxWeeks([]),
    DEFAULT_RENEWAL_ADVANCE_MAX_WEEKS,
  );
});

Deno.test("getRenewalAdvanceMaxDays converts weeks to days", () => {
  assertEquals(getRenewalAdvanceMaxDays({ renewal_advance_max_weeks: 2 }), 14);
  assertEquals(getRenewalAdvanceMaxDays({ renewal_advance_max_weeks: 3 }), 21);
});
