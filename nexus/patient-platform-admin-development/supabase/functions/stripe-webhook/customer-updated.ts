import { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { dateTime } from "../_shared/dayjs.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

export type LifecycleTrigger = (
  orderId: string,
  tenantId: string,
  requestId: string,
) => Promise<void>;

export async function handleCustomerUpdated(
  supabase: SupabaseAdminClient,
  customer: Record<string, unknown>,
  tenantId: string,
  requestId: string,
  lifecycleTrigger: LifecycleTrigger,
): Promise<void> {
  const stripeCustomerId =
    typeof customer.id === "string" ? customer.id : null;

  // Only act when the default payment method changed
  const previousAttributes = customer.previous_attributes as
    | Record<string, unknown>
    | undefined;
  const previousDefaultPM =
    previousAttributes?.invoice_settings !== undefined ||
    previousAttributes?.default_source !== undefined;

  if (!stripeCustomerId) {
    console.warn("customer.updated: missing customer id", { requestId });
    return;
  }

  if (!previousDefaultPM) {
    console.info(
      "customer.updated: default payment method unchanged, skipping",
      { requestId, stripeCustomerId },
    );
    return;
  }

  console.info(
    "customer.updated: default payment method changed, checking for payment_failed orders",
    { requestId, stripeCustomerId, tenantId },
  );

  // Find the patient matching this Stripe customer
  const { data: matchedPatients, error: patientError } = await supabase
    .from("patients")
    .select("id")
    .filter("metadata->>stripe_customer_id", "eq", stripeCustomerId)
    .eq("tenant_id", tenantId)
    .limit(2);

  if (patientError) {
    console.warn("customer.updated: failed to look up patient", {
      requestId,
      stripeCustomerId,
      error: patientError.message,
    });
    return;
  }

  if (!matchedPatients || matchedPatients.length === 0) {
    console.info("customer.updated: no matching patient found", {
      requestId,
      stripeCustomerId,
    });
    return;
  }

  if (matchedPatients.length > 1) {
    console.warn(
      "customer.updated: multiple patients matched Stripe customer, skipping",
      { requestId, stripeCustomerId },
    );
    return;
  }

  const patientId = matchedPatients[0].id;

  // Look up payment_pending status id
  const { data: paymentPendingStatus, error: statusError } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("status_key", "payment_pending")
    .eq("is_active", true)
    .maybeSingle();

  if (statusError || !paymentPendingStatus?.id) {
    console.warn(
      "customer.updated: failed to resolve payment_pending status id",
      { requestId, error: statusError?.message },
    );
    return;
  }

  // Find all payment_failed orders for this patient
  const { data: failedOrders, error: ordersError } = await supabase
    .from("orders")
    .select("id, subscription_id, order_statuses!inner(status_key)")
    .eq("patient_id", patientId)
    .eq("tenant_id", tenantId)
    .eq("order_statuses.status_key", "payment_failed");

  if (ordersError) {
    console.warn("customer.updated: failed to query payment_failed orders", {
      requestId,
      patientId,
      error: ordersError.message,
    });
    return;
  }

  if (!failedOrders || failedOrders.length === 0) {
    console.info(
      "customer.updated: no payment_failed orders found for patient",
      { requestId, patientId },
    );
    return;
  }

  console.info(
    "customer.updated: processing payment_failed orders",
    { requestId, patientId, orderCount: failedOrders.length },
  );

  for (const order of failedOrders) {
    const orderId = order.id;

    // Do not charge directly in customer.updated. Move to payment_pending and let
    // order-lifecycle perform the only charge attempt path.
    const { error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: paymentPendingStatus.id,
        status_changed_at: dateTime().toISOString(),
        paid_at: null,
        payment_failed_at: null,
        payment_retry_count: 0,
      })
      .eq("id", orderId)
      .eq("tenant_id", tenantId);

    if (updateError) {
      console.warn(
        "customer.updated: failed to update order to payment_pending",
        { requestId, orderId, error: updateError.message },
      );
      continue;
    }

    const { error: historyError } = await supabase
      .from("order_status_history")
      .insert({
        order_id: orderId,
        status_id: paymentPendingStatus.id,
        notes:
          "Payment method updated by customer via billing portal; moved from payment_failed to payment_pending for lifecycle-managed retry.",
      });

    if (historyError) {
      console.warn(
        "customer.updated: failed to insert order_status_history",
        { requestId, orderId, error: historyError.message },
      );
    }

    await lifecycleTrigger(orderId, tenantId, requestId);
  }
}
