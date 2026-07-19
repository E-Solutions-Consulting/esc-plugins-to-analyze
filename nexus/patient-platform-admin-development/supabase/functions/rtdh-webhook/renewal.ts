import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { asNonEmptyString, asObject } from "./validation.ts";
import type { RtdhEventPayload } from "./validation.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

const RENEWAL_INTENT_HEADER = "x-rtdh-intent";
const RENEWAL_INTENT_VALUE = "renewal_order_create";

function normalizeIntent(value: string | null): string {
  return (value || "").trim().toLowerCase();
}

export function isRenewalOrderCreateIntent(
  req: Request,
  payload?: RtdhEventPayload,
): boolean {
  const payloadIntent = typeof payload?.rtdh_intent === "string"
    ? payload.rtdh_intent
    : null;

  return normalizeIntent(payloadIntent) === RENEWAL_INTENT_VALUE ||
    normalizeIntent(req.headers.get(RENEWAL_INTENT_HEADER)) ===
      RENEWAL_INTENT_VALUE;
}

function generateRenewalOrderNumber(): string {
  const ts = Date.now().toString(36).toUpperCase();
  const rand = crypto.randomUUID().replaceAll("-", "").slice(0, 4)
    .toUpperCase();
  return `ORD-${ts}-${rand}`;
}

async function resolveTenantId(
  supabase: SupabaseAdminClient,
  internalTenantId: string,
): Promise<string | null> {
  const { data: byId } = await supabase
    .from("tenants")
    .select("id")
    .eq("id", internalTenantId)
    .maybeSingle();
  if (byId?.id) return byId.id;

  const { data: bySlug } = await supabase
    .from("tenants")
    .select("id")
    .eq("slug", internalTenantId)
    .maybeSingle();
  return bySlug?.id ?? null;
}

async function resolvePaymentProviderId(
  supabase: SupabaseAdminClient,
  providerKey: string,
): Promise<string | null> {
  const { data } = await supabase
    .from("payment_providers")
    .select("id")
    .eq("key", providerKey)
    .maybeSingle();
  return data?.id ?? null;
}

type RenewalIdentifiers = {
  subscriptionId: string | null;
  checkoutSessionId: string | null;
  paymentIntentId: string | null;
  invoiceId: string | null;
  customerId: string | null;
  paymentStatus: string | null;
  paymentProviderKey: string;
};

function getRenewalIdentifiers(payload: RtdhEventPayload): RenewalIdentifiers {
  const payment = asObject(payload.payment);
  return {
    subscriptionId: payment ? asNonEmptyString(payment.subscription_id) : null,
    checkoutSessionId: payment
      ? asNonEmptyString(payment.checkout_session_id)
      : null,
    paymentIntentId: payment ? asNonEmptyString(payment.payment_intent_id) : null,
    invoiceId: payment ? asNonEmptyString(payment.invoice_id) : null,
    customerId: payment ? asNonEmptyString(payment.customer_id) : null,
    paymentStatus: payment ? asNonEmptyString(payment.status) : null,
    paymentProviderKey: payment
      ? asNonEmptyString(payment.provider) || "stripe"
      : "stripe",
  };
}

type OrderContext = {
  id: string;
  tenant_id: string;
  patient_id: string;
  product_id: string | null;
  subscription_id: string | null;
  subtotal_cents: number;
  tax_cents: number;
  shipping_cents: number;
  total_cents: number;
  discount_cents: number | null;
  coupon_code: string | null;
  coupon_name: string | null;
  shipping_first_name: string | null;
  shipping_last_name: string | null;
  shipping_address_line1: string | null;
  shipping_address_line2: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string | null;
  billing_first_name: string | null;
  billing_last_name: string | null;
  billing_address_line1: string | null;
  billing_address_line2: string | null;
  billing_city: string | null;
  billing_state: string | null;
  billing_postal_code: string | null;
  billing_country: string | null;
  provider_platform_integration_key: string | null;
};

async function findOrderBySubscriptionLink(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  paymentProviderId: string;
  column:
    | "provider_subscription_id"
    | "provider_checkout_session_id";
  value: string | null;
  strategy: string;
}): Promise<{ order: OrderContext | null; strategy: string | null }> {
  const { supabase, tenantId, paymentProviderId, column, value, strategy } = params;

  if (!value) {
    return { order: null, strategy: null };
  }

  const { data: links } = await supabase
    .from("subscription_payment_provider_links")
    .select("subscription_id")
    .eq("tenant_id", tenantId)
    .eq("payment_provider_id", paymentProviderId)
    .eq(column, value)
    .order("created_at", { ascending: false })
    .limit(2);

  if (!links || links.length === 0 || !links[0]?.subscription_id) {
    console.info("rtdh-webhook: renewal subscription link miss", {
      strategy,
      column,
      tenantId,
      rowCount: links?.length ?? 0,
    });
    return { order: null, strategy: null };
  }

  if (links.length > 1) {
    console.warn("rtdh-webhook: renewal subscription link ambiguous match", {
      strategy,
      column,
      tenantId,
      matchCount: links.length,
    });
  }

  const link = links[0];

  const { data: order } = await supabase
    .from("orders")
    .select(
      "id,tenant_id,patient_id,product_id,subscription_id,subtotal_cents,tax_cents,shipping_cents,total_cents,discount_cents,coupon_code,coupon_name,shipping_first_name,shipping_last_name,shipping_address_line1,shipping_address_line2,shipping_city,shipping_state,shipping_postal_code,shipping_country,billing_first_name,billing_last_name,billing_address_line1,billing_address_line2,billing_city,billing_state,billing_postal_code,billing_country,provider_platform_integration_key",
    )
    .eq("tenant_id", tenantId)
    .eq("subscription_id", link.subscription_id)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (!order) {
    return { order: null, strategy: null };
  }

  return { order: order as OrderContext, strategy };
}

async function findSourceOrderContext(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  paymentProviderId: string;
  identifiers: RenewalIdentifiers;
}): Promise<{ order: OrderContext | null; strategy: string | null }> {
  const { supabase, tenantId, paymentProviderId, identifiers } = params;

  if (identifiers.subscriptionId) {
    const bySubscriptionLink = await findOrderBySubscriptionLink({
      supabase,
      tenantId,
      paymentProviderId,
      column: "provider_subscription_id",
      value: identifiers.subscriptionId,
      strategy: "subscription",
    });

    if (bySubscriptionLink.order) {
      return bySubscriptionLink;
    }
  }

  if (identifiers.checkoutSessionId) {
    const byCheckoutLink = await findOrderBySubscriptionLink({
      supabase,
      tenantId,
      paymentProviderId,
      column: "provider_checkout_session_id",
      value: identifiers.checkoutSessionId,
      strategy: "subscription_checkout",
    });

    if (byCheckoutLink.order) {
      return byCheckoutLink;
    }
  }

  const transactionLookups: Array<{
    column:
      | "provider_invoice_id"
      | "provider_checkout_session_id"
      | "provider_payment_intent_id"
      | "provider_subscription_id";
    value: string | null;
    strategy: string;
  }> = [
    {
      column: "provider_invoice_id",
      value: identifiers.invoiceId,
      strategy: "invoice",
    },
    {
      column: "provider_checkout_session_id",
      value: identifiers.checkoutSessionId,
      strategy: "checkout",
    },
    {
      column: "provider_payment_intent_id",
      value: identifiers.paymentIntentId,
      strategy: "payment_intent",
    },
    {
      column: "provider_subscription_id",
      value: identifiers.subscriptionId,
      strategy: "subscription_transaction",
    },
  ];

  for (const lookup of transactionLookups) {
    if (!lookup.value) continue;
    const { data: tx } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id, subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", paymentProviderId)
      .eq(lookup.column, lookup.value)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();
    if (!tx?.order_id) {
      console.info("rtdh-webhook: renewal transaction miss", {
        strategy: lookup.strategy,
        column: lookup.column,
        tenantId,
      });
      continue;
    }

    const { data: order } = await supabase
      .from("orders")
      .select(
        "id,tenant_id,patient_id,product_id,subscription_id,subtotal_cents,tax_cents,shipping_cents,total_cents,discount_cents,coupon_code,coupon_name,shipping_first_name,shipping_last_name,shipping_address_line1,shipping_address_line2,shipping_city,shipping_state,shipping_postal_code,shipping_country,billing_first_name,billing_last_name,billing_address_line1,billing_address_line2,billing_city,billing_state,billing_postal_code,billing_country,provider_platform_integration_key",
      )
      .eq("id", tx.order_id)
      .eq("tenant_id", tenantId)
      .maybeSingle();
    if (order) {
      // If the order row has no subscription_id (e.g. created before the subscription
      // entity migration populated the field), fall back to the subscription_id stored
      // on the transaction row itself, which is populated by the write paths.
      const effectiveSubscriptionId = order.subscription_id ??
        tx.subscription_id ??
        null;
      return {
        order: { ...order, subscription_id: effectiveSubscriptionId } as OrderContext,
        strategy: lookup.strategy,
      };
    }
  }

  return { order: null, strategy: null };
}

export async function handleRenewalOrderCreate(params: {
  supabase: SupabaseAdminClient;
  payload: RtdhEventPayload;
  requestId: string;
}): Promise<
  | {
    ok: true;
    tenantId: string;
    orderId: string;
    created: boolean;
    strategy: string;
  }
  | { ok: false; status: number; code: string; message: string; details?: unknown }
> {
  const { supabase, payload, requestId } = params;
  const tenantIdentifier = payload.internal_tenant_id;
  const tenantId = await resolveTenantId(supabase, tenantIdentifier);
  if (!tenantId) {
    return {
      ok: false,
      status: 422,
      code: "reference_not_found",
      message: "Unable to resolve tenant id from internal_tenant_id",
    };
  }

  const identifiers = getRenewalIdentifiers(payload);
  if (
    !identifiers.subscriptionId && !identifiers.invoiceId &&
    !identifiers.checkoutSessionId && !identifiers.paymentIntentId
  ) {
    return {
      ok: false,
      status: 422,
      code: "validation_error",
      message:
        "Renewal create requires at least one payment identifier (subscription_id, invoice_id, checkout_session_id, or payment_intent_id)",
    };
  }

  const paymentProviderId = await resolvePaymentProviderId(
    supabase,
    identifiers.paymentProviderKey,
  );
  if (!paymentProviderId) {
    return {
      ok: false,
      status: 422,
      code: "reference_not_found",
      message: `payment.provider '${identifiers.paymentProviderKey}' does not match any payment provider`,
    };
  }

  if (identifiers.invoiceId) {
    const { data: existingByInvoice } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", paymentProviderId)
      .eq("provider_invoice_id", identifiers.invoiceId)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();
    if (existingByInvoice?.order_id) {
      console.info("rtdh-webhook: renewal-intent existing order by invoice", {
        requestId,
        tenantId,
        invoiceId: identifiers.invoiceId,
        orderId: existingByInvoice.order_id,
      });
      return {
        ok: true,
        tenantId,
        orderId: existingByInvoice.order_id,
        created: false,
        strategy: "idempotency_invoice",
      };
    }
  }

  const source = await findSourceOrderContext({
    supabase,
    tenantId,
    paymentProviderId,
    identifiers,
  });
  if (!source.order || !source.order.subscription_id) {
    return {
      ok: false,
      status: 422,
      code: "reference_not_found",
      message: "Unable to resolve renewal subscription/order context",
      details: {
        resolutionStrategy: source.strategy,
        hasSubscriptionId: Boolean(identifiers.subscriptionId),
        hasInvoiceId: Boolean(identifiers.invoiceId),
        hasCheckoutSessionId: Boolean(identifiers.checkoutSessionId),
        hasPaymentIntentId: Boolean(identifiers.paymentIntentId),
        sourceOrderFound: Boolean(source.order),
        sourceOrderMissingSubscriptionId: source.order !== null &&
          !source.order.subscription_id,
      },
    };
  }

  const { data: orderCreatedStatus } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("status_key", "order_created")
    .maybeSingle();
  if (!orderCreatedStatus?.id) {
    return {
      ok: false,
      status: 500,
      code: "server_error",
      message: "order_created status not found",
    };
  }

  const nowIso = new Date().toISOString();
  const renewalNote = `RTDH renewal order create (${source.strategy}) invoice:${
    identifiers.invoiceId ?? "n/a"
  } subscription:${identifiers.subscriptionId ?? "n/a"}`;

  const { data: insertedOrder, error: orderInsertError } = await supabase
    .from("orders")
    .insert({
      order_number: generateRenewalOrderNumber(),
      tenant_id: source.order.tenant_id,
      patient_id: source.order.patient_id,
      product_id: source.order.product_id,
      subscription_id: source.order.subscription_id,
      status_id: orderCreatedStatus.id,
      status_changed_at: nowIso,
      subtotal_cents: source.order.subtotal_cents ?? 0,
      tax_cents: source.order.tax_cents ?? 0,
      shipping_cents: source.order.shipping_cents ?? 0,
      total_cents: source.order.total_cents ?? 0,
      discount_cents: source.order.discount_cents ?? 0,
      coupon_code: source.order.coupon_code,
      coupon_name: source.order.coupon_name,
      shipping_first_name: source.order.shipping_first_name,
      shipping_last_name: source.order.shipping_last_name,
      shipping_address_line1: source.order.shipping_address_line1,
      shipping_address_line2: source.order.shipping_address_line2,
      shipping_city: source.order.shipping_city,
      shipping_state: source.order.shipping_state,
      shipping_postal_code: source.order.shipping_postal_code,
      shipping_country: source.order.shipping_country,
      billing_first_name: source.order.billing_first_name,
      billing_last_name: source.order.billing_last_name,
      billing_address_line1: source.order.billing_address_line1,
      billing_address_line2: source.order.billing_address_line2,
      billing_city: source.order.billing_city,
      billing_state: source.order.billing_state,
      billing_postal_code: source.order.billing_postal_code,
      billing_country: source.order.billing_country,
      provider_platform_integration_key:
        source.order.provider_platform_integration_key,
      internal_notes: renewalNote,
    })
    .select("id")
    .single();

  if (orderInsertError || !insertedOrder?.id) {
    return {
      ok: false,
      status: 500,
      code: "server_error",
      message: `Failed to create renewal order: ${
        orderInsertError?.message ?? "unknown error"
      }`,
    };
  }

  const { error: orderStatusHistoryInsertError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: insertedOrder.id,
      status_id: orderCreatedStatus.id,
      notes: "Order created via RTDH renewal intent",
    });

  if (orderStatusHistoryInsertError) {
    console.warn("rtdh-webhook: renewal-intent order status history insert failed", {
      requestId,
      tenantId,
      orderId: insertedOrder.id,
      error: orderStatusHistoryInsertError.message,
    });
  }

  const { error: txInsertError } = await supabase
    .from("order_payment_provider_transactions")
    .insert({
      tenant_id: tenantId,
      order_id: insertedOrder.id,
      payment_provider_id: paymentProviderId,
      subscription_id: source.order.subscription_id,
      provider_checkout_session_id: identifiers.checkoutSessionId,
      provider_subscription_id: identifiers.subscriptionId,
      provider_payment_intent_id: identifiers.paymentIntentId,
      provider_invoice_id: identifiers.invoiceId,
      provider_customer_id: identifiers.customerId,
      payment_status: identifiers.paymentStatus,
    });

  if (txInsertError) {
    console.error("rtdh-webhook: renewal-intent transaction insert failed", {
      requestId,
      tenantId,
      orderId: insertedOrder.id,
      error: txInsertError.message,
    });

    const { error: statusRollbackError } = await supabase
      .from("order_status_history")
      .delete()
      .eq("order_id", insertedOrder.id);

    if (statusRollbackError) {
      console.error("rtdh-webhook: renewal-intent status history rollback failed", {
        requestId,
        tenantId,
        orderId: insertedOrder.id,
        error: statusRollbackError.message,
      });
    }

    const { error: orderRollbackError } = await supabase
      .from("orders")
      .delete()
      .eq("id", insertedOrder.id);

    if (orderRollbackError) {
      console.error("rtdh-webhook: renewal-intent order rollback failed", {
        requestId,
        tenantId,
        orderId: insertedOrder.id,
        error: orderRollbackError.message,
      });
    }

    return {
      ok: false,
      status: 500,
      code: "server_error",
      message: `Failed to persist renewal payment transaction: ${
        txInsertError.message
      }`,
    };
  }

  // Link the Stripe subscription ID onto the PP subscriptions row so that future
  // validateReferences lookups by stripe_subscription_id succeed.
  if (source.order.subscription_id && identifiers.subscriptionId) {
    const { error: subscriptionLinkError } = await supabase
      .from("subscriptions")
      .update({ stripe_subscription_id: identifiers.subscriptionId })
      .eq("id", source.order.subscription_id)
      .is("stripe_subscription_id", null);

    if (subscriptionLinkError) {
      console.warn(
        "rtdh-webhook: renewal-intent subscription stripe_subscription_id link failed",
        {
          requestId,
          tenantId,
          orderId: insertedOrder.id,
          subscriptionId: source.order.subscription_id,
          stripeSubscriptionId: identifiers.subscriptionId,
          error: subscriptionLinkError.message,
        },
      );
    } else {
      console.info(
        "rtdh-webhook: renewal-intent subscription stripe_subscription_id linked",
        {
          requestId,
          tenantId,
          subscriptionId: source.order.subscription_id,
          stripeSubscriptionId: identifiers.subscriptionId,
        },
      );
    }
  }

  console.info("rtdh-webhook: renewal-intent order created", {
    requestId,
    tenantId,
    orderId: insertedOrder.id,
    strategy: source.strategy,
  });

  return {
    ok: true,
    tenantId,
    orderId: insertedOrder.id,
    created: true,
    strategy: source.strategy || "unknown",
  };
}
