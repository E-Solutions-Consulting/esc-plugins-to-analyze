export interface AddressCheckOrder {
  shipping_first_name: string | null;
  shipping_last_name: string | null;
  shipping_address_line1: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string | null;
  billing_first_name: string | null;
  billing_last_name: string | null;
  billing_address_line1: string | null;
  billing_city: string | null;
  billing_state: string | null;
  billing_postal_code: string | null;
  billing_country: string | null;
}

export type RefundTier = "full" | "partial" | "none";

const FULL_REFUND_STATUS_KEYS = [
  "provider_rejected",
];

const PROVIDER_REVIEW_STATUS_KEYS = [
  "provider_review_pending",
  "provider_approved",
  "order_approved",
];

const SENT_TO_PHARMACY_STATUS_KEYS = [
  "sent_to_pharmacy",
  "order_sent_to_pharmacy",
  "pharmacy_approval_pending",
  "approved_by_pharmacy",
  "pharmacy_approved",
  "fulfillment_in_progress",
  "final_pharmacy_verification",
  "in_transit",
  "delivered",
];

export function isFieldFilled(value: string | null | undefined): boolean {
  return !!(value && value.trim().length > 0);
}

export function hasCompleteShippingAddress(order: AddressCheckOrder): boolean {
  return (
    isFieldFilled(order.shipping_first_name) &&
    isFieldFilled(order.shipping_last_name) &&
    isFieldFilled(order.shipping_address_line1) &&
    isFieldFilled(order.shipping_city) &&
    isFieldFilled(order.shipping_state) &&
    isFieldFilled(order.shipping_postal_code) &&
    isFieldFilled(order.shipping_country)
  );
}

export function hasCompleteBillingAddress(order: AddressCheckOrder): boolean {
  return (
    isFieldFilled(order.billing_first_name) &&
    isFieldFilled(order.billing_last_name) &&
    isFieldFilled(order.billing_address_line1) &&
    isFieldFilled(order.billing_city) &&
    isFieldFilled(order.billing_state) &&
    isFieldFilled(order.billing_postal_code) &&
    isFieldFilled(order.billing_country)
  );
}

export function determineRefundTierFromPreviousStatus(params: {
  previousStatusKey: string | null;
  previousDisplayOrder: number | null;
  providerReviewDisplayOrderThreshold: number | null;
  sentToPharmacyDisplayOrderThreshold: number | null;
}): RefundTier {
  const {
    previousStatusKey,
    previousDisplayOrder,
    providerReviewDisplayOrderThreshold,
    sentToPharmacyDisplayOrderThreshold,
  } = params;

  if (
    previousStatusKey &&
    FULL_REFUND_STATUS_KEYS.includes(previousStatusKey)
  ) {
    return "full";
  }

  if (
    previousStatusKey &&
    SENT_TO_PHARMACY_STATUS_KEYS.includes(previousStatusKey)
  ) {
    return "none";
  }

  if (
    previousStatusKey &&
    PROVIDER_REVIEW_STATUS_KEYS.includes(previousStatusKey)
  ) {
    return "partial";
  }

  if (
    typeof previousDisplayOrder === "number" &&
    typeof sentToPharmacyDisplayOrderThreshold === "number" &&
    previousDisplayOrder >= sentToPharmacyDisplayOrderThreshold
  ) {
    return "none";
  }

  if (
    typeof previousDisplayOrder === "number" &&
    typeof providerReviewDisplayOrderThreshold === "number" &&
    previousDisplayOrder >= providerReviewDisplayOrderThreshold
  ) {
    return "partial";
  }

  return "full";
}

function formatCurrencyFromCents(amountCents: number): string {
  return `$${(Math.max(0, amountCents) / 100).toFixed(2)}`;
}

export function buildRefundEligibilityHistoryNote(params: {
  previousStatusKey: string | null;
  previousStatusLabel: string | null;
  refundTier: RefundTier;
  refundAmountCents: number;
  retainedAmountCents: number;
  providerFeeCents: number;
}): string {
  const {
    previousStatusKey,
    previousStatusLabel,
    refundTier,
    refundAmountCents,
    retainedAmountCents,
    providerFeeCents,
  } = params;

  const statusDescriptor = previousStatusLabel || previousStatusKey ||
    "unknown previous status";

  if (refundTier === "full") {
    return `Refund eligibility: full refund (${
      formatCurrencyFromCents(refundAmountCents)
    }) because the previous status was ${statusDescriptor}.`;
  }

  if (refundTier === "partial") {
    return `Refund eligibility: partial refund (${
      formatCurrencyFromCents(refundAmountCents)
    }) after retaining provider fee ${
      formatCurrencyFromCents(providerFeeCents)
    } because the previous status was ${statusDescriptor}.`;
  }

  return `Refund eligibility: no refund. Previous status ${statusDescriptor} is at or beyond pharmacy processing. Retained amount ${
    formatCurrencyFromCents(retainedAmountCents)
  }.`;
}
