const AUTHORIZED_PAYMENT_STATUSES = new Set([
  "requires_capture",
  "succeeded",
  "paid",
]);

export function isOrderPaymentAuthorized(params: {
  paidAt?: string | null;
  totalCents?: number | null;
  paymentStatuses?: Array<string | null | undefined>;
}): boolean {
  if (params.paidAt) return true;
  if ((params.totalCents ?? 0) <= 0) return true;

  return (params.paymentStatuses ?? []).some((status) =>
    typeof status === "string" &&
    AUTHORIZED_PAYMENT_STATUSES.has(status.trim().toLowerCase())
  );
}
