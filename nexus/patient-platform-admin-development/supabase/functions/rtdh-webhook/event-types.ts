export const DIRECT_STATUS_EVENT_TYPES = [
  "order_validation_pending",
  "provider_review_pending",
  "provider_approved",
  "provider_rejected",
  // A HOLD (not a rejection): Telegra requires_admin_review/requires_affiliate_review/delayed map
  // to this. The order pauses for manual/affiliate/info action but can still proceed — do NOT cancel
  // payment. See RTDH provider-status-map.yaml.
  "medical_followup_required",
  "payment_pending",
  "payment_collected",
  "payment_failed",
  "order_sent_to_pharmacy",
  "pharmacy_approval_pending",
  "pharmacy_approved",
  "fulfillment_in_progress",
  "final_pharmacy_verification",
  "in_transit",
  "delivered",
  "shipping_exception",
  "order_cancelled",
  "order_pending_cancellation",
] as const;

export const SEMANTIC_EVENT_TYPES = [
  "order.linked",
  "order.fulfillment_linked",
  "medical_questionnaire_submitted",
  "patient_questionnaire_submitted",
] as const;

export const SUPPORTED_EVENT_TYPES = [
  "order.linked",
  "order.fulfillment_linked",
  ...DIRECT_STATUS_EVENT_TYPES,
] as const;

export type DirectStatusEventType = (typeof DIRECT_STATUS_EVENT_TYPES)[number];
export type SupportedEventType = (typeof SUPPORTED_EVENT_TYPES)[number];

export function isSupportedEventType(
  eventType: string,
): eventType is SupportedEventType {
  return SUPPORTED_EVENT_TYPES.includes(eventType as SupportedEventType);
}
