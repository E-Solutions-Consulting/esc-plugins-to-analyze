import { assertEquals } from "../_test/assert.ts";
import {
  normalizeSubscriptionPeriodEnd,
  type StripeSubscriptionResponse,
} from "./stripe-subscription.ts";

Deno.test("normalizeSubscriptionPeriodEnd keeps a top-level current_period_end (pre-Clover)", () => {
  const sub = {
    id: "sub_1",
    current_period_end: 1789818487,
  } as StripeSubscriptionResponse;

  const result = normalizeSubscriptionPeriodEnd(sub);
  assertEquals(result.current_period_end, 1789818487);
});

Deno.test("normalizeSubscriptionPeriodEnd hoists item-level current_period_end (Clover 2026-01-28)", () => {
  // On API version 2026-01-28.clover the field is null on the subscription and
  // present on the subscription item instead.
  const sub = {
    id: "sub_2",
    current_period_end: null,
    items: { data: [{ current_period_end: 1789818487 }] },
  } as unknown as StripeSubscriptionResponse;

  const result = normalizeSubscriptionPeriodEnd(sub);
  assertEquals(result.current_period_end, 1789818487);
});

Deno.test("normalizeSubscriptionPeriodEnd leaves current_period_end unset when neither level has it", () => {
  const sub = {
    id: "sub_3",
    items: { data: [{}] },
  } as unknown as StripeSubscriptionResponse;

  const result = normalizeSubscriptionPeriodEnd(sub);
  assertEquals(typeof result.current_period_end === "number", false);
});

Deno.test("normalizeSubscriptionPeriodEnd uses the first numeric item period end", () => {
  const sub = {
    id: "sub_4",
    items: {
      data: [
        { current_period_end: null },
        { current_period_end: 1789818487 },
      ],
    },
  } as unknown as StripeSubscriptionResponse;

  const result = normalizeSubscriptionPeriodEnd(sub);
  assertEquals(result.current_period_end, 1789818487);
});
