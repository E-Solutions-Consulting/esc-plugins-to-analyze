import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  buildRefundEligibilityHistoryNote,
  determineRefundTierFromPreviousStatus,
  hasCompleteBillingAddress,
  hasCompleteShippingAddress,
  isFieldFilled,
} from "./helpers.ts";

const baseOrder = {
  shipping_first_name: "Jane",
  shipping_last_name: "Doe",
  shipping_address_line1: "123 Main St",
  shipping_city: "Austin",
  shipping_state: "TX",
  shipping_postal_code: "78701",
  shipping_country: "US",
  billing_first_name: "Jane",
  billing_last_name: "Doe",
  billing_address_line1: "123 Main St",
  billing_city: "Austin",
  billing_state: "TX",
  billing_postal_code: "78701",
  billing_country: "US",
};

Deno.test("isFieldFilled checks non-empty trimmed values", () => {
  assertEquals(isFieldFilled(" value "), true);
  assertEquals(isFieldFilled("   "), false);
  assertEquals(isFieldFilled(null), false);
});

Deno.test("hasCompleteShippingAddress validates required shipping fields", () => {
  assertEquals(hasCompleteShippingAddress(baseOrder), true);
  assertEquals(
    hasCompleteShippingAddress({ ...baseOrder, shipping_city: "" }),
    false,
  );
});

Deno.test("hasCompleteBillingAddress validates required billing fields", () => {
  assertEquals(hasCompleteBillingAddress(baseOrder), true);
  assertEquals(
    hasCompleteBillingAddress({ ...baseOrder, billing_country: null }),
    false,
  );
});

Deno.test("determineRefundTierFromPreviousStatus returns full before provider review", () => {
  assertEquals(
    determineRefundTierFromPreviousStatus({
      previousStatusKey: "shipping_details_required",
      previousDisplayOrder: 20,
      providerReviewDisplayOrderThreshold: 40,
      sentToPharmacyDisplayOrderThreshold: 60,
    }),
    "full",
  );
});

Deno.test("determineRefundTierFromPreviousStatus returns partial at provider review", () => {
  assertEquals(
    determineRefundTierFromPreviousStatus({
      previousStatusKey: "provider_review_pending",
      previousDisplayOrder: 40,
      providerReviewDisplayOrderThreshold: 40,
      sentToPharmacyDisplayOrderThreshold: 60,
    }),
    "partial",
  );
});

Deno.test("determineRefundTierFromPreviousStatus returns none at pharmacy stage", () => {
  assertEquals(
    determineRefundTierFromPreviousStatus({
      previousStatusKey: "pharmacy_approval_pending",
      previousDisplayOrder: 60,
      providerReviewDisplayOrderThreshold: 40,
      sentToPharmacyDisplayOrderThreshold: 60,
    }),
    "none",
  );
});

Deno.test("determineRefundTierFromPreviousStatus falls back to display order", () => {
  assertEquals(
    determineRefundTierFromPreviousStatus({
      previousStatusKey: "custom_internal_status",
      previousDisplayOrder: 45,
      providerReviewDisplayOrderThreshold: 40,
      sentToPharmacyDisplayOrderThreshold: 60,
    }),
    "partial",
  );
});

Deno.test("buildRefundEligibilityHistoryNote includes refund context", () => {
  const note = buildRefundEligibilityHistoryNote({
    previousStatusKey: "provider_review_pending",
    previousStatusLabel: "Provider Review Pending",
    refundTier: "partial",
    refundAmountCents: 4900,
    retainedAmountCents: 5000,
    providerFeeCents: 5000,
  });

  assertMatch(note, /Refund eligibility: partial refund/);
  assertMatch(note, /\$49\.00/);
  assertMatch(note, /Provider Review Pending/);
});
