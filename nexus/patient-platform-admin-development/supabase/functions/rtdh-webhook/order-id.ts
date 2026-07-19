import {
  asNonEmptyString,
  asObject,
  type RtdhEventPayload,
} from "./validation.ts";

export function extractPatientPlatformOrderId(
  payload: RtdhEventPayload,
): string | null {
  const ids = asObject(payload.ids);
  return ids ? asNonEmptyString(ids.patient_platform_order_id) : null;
}

export function extractWooCommerceOrderId(
  payload: RtdhEventPayload,
): string | null {
  const ids = asObject(payload.ids);
  if (!ids) return null;

  return asNonEmptyString(ids.woocommerce_order_id) ||
    asNonEmptyString(ids.woo_order_id) ||
    asNonEmptyString(ids.wc_order_id);
}

export function extractWooCommerceCustomerId(
  payload: RtdhEventPayload,
): string | null {
  const customer = asObject(payload.customer);
  return customer ? asNonEmptyString(customer.customer_id) : null;
}
