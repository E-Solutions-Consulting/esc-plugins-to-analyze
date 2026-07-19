import type { NormalizedTelegraWebhookEvent } from "./helpers.ts";

export interface TelegraOrderStatusTransition {
  status_key: string;
  display_order: number;
  is_terminal: boolean;
}

export interface TelegraOrderStatusDecision {
  shouldAdvance: boolean;
  reason: string;
}

export function explainTelegraOrderStatusTransitionDecision(
  currentStatus: TelegraOrderStatusTransition | null,
  targetStatus: TelegraOrderStatusTransition,
  event: NormalizedTelegraWebhookEvent,
): TelegraOrderStatusDecision {
  if (
    targetStatus.status_key === "payment_pending" &&
    event.normalizedType === "new_status_set_to_request" &&
    event.normalizedTargetEntityStatus === "requires_order_processing"
  ) {
    return {
      shouldAdvance: true,
      reason:
        "forced_payment_pending_for_new_status_set_to_request_requires_order_processing",
    };
  }

  if (!currentStatus) {
    return {
      shouldAdvance: true,
      reason: "no_current_status",
    };
  }
  if (currentStatus.status_key === targetStatus.status_key) {
    return {
      shouldAdvance: false,
      reason: "target_matches_current_status",
    };
  }
  if (currentStatus.is_terminal) {
    return {
      shouldAdvance: false,
      reason: "current_status_terminal",
    };
  }

  if (currentStatus.status_key === "medical_questionnaire_pending") {
    const shouldAdvance = targetStatus.status_key === "provider_review_pending" &&
      event.normalizedType === "new_status_set_to_request" &&
      event.normalizedTargetEntityStatus === "requires_provider_review";

    return {
      shouldAdvance,
      reason: shouldAdvance
        ? "questionnaire_requires_provider_review_request_received"
        : "medical_questionnaire_pending_requires_new_status_set_to_request_with_requires_provider_review",
    };
  }

  if (
    currentStatus.status_key === "provider_review_pending" &&
    targetStatus.status_key === "provider_approved"
  ) {
    const shouldAdvance = targetStatus.status_key === "provider_approved" &&
      event.normalizedType === "prescription_approved_by_practitioner";

    return {
      shouldAdvance,
      reason: shouldAdvance
        ? "provider_approved_by_practitioner_event"
        : "provider_review_pending_requires_prescription_approved_by_practitioner",
    };
  }

  if (
    targetStatus.status_key === "order_sent_to_pharmacy" &&
    event.normalizedType === "prescription_sent_to_pharmacy"
  ) {
    return {
      shouldAdvance: true,
      reason: "prescription_sent_to_pharmacy_event",
    };
  }

  if (
    targetStatus.status_key === "shipping_exception" ||
    targetStatus.status_key === "order_cancelled"
  ) {
    return {
      shouldAdvance: true,
      reason: "terminal_shipping_or_cancel_override",
    };
  }

  const shouldAdvance = targetStatus.display_order >= currentStatus.display_order;
  return {
    shouldAdvance,
    reason: shouldAdvance
      ? "display_order_allows_advance"
      : "display_order_blocks_regression",
  };
}

export function shouldAdvanceTelegraOrderStatus(
  currentStatus: TelegraOrderStatusTransition | null,
  targetStatus: TelegraOrderStatusTransition,
  event: NormalizedTelegraWebhookEvent,
): boolean {
  return explainTelegraOrderStatusTransitionDecision(
    currentStatus,
    targetStatus,
    event,
  ).shouldAdvance;
}
