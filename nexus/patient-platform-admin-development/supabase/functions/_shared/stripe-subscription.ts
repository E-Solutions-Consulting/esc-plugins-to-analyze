// Shared Stripe subscription setup used by BOTH the hosted Checkout Session flow
// (plan-api) and the embedded PaymentIntent flow (order-lifecycle, at capture).
//
// These were originally nested in plan-api's request handler and coupled to a
// Stripe Checkout `session` object. They are extracted here, parameterized on a
// Stripe customer id + an idempotency scope (the order id for the embedded flow,
// the checkout session id for the hosted flow), so the embedded flow can create
// the same subscription + local rows at payment capture without reverting to a
// Checkout Session. Behavior for the hosted callers is unchanged.

import { dateTime } from "./dayjs.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = any;

export type StripeSubscriptionDiscount = {
  couponId: string | null;
  promotionCodeId: string | null;
};

export type StripeSubscriptionResponse = {
  id: string;
  current_period_end?: number | null;
  status?: string | null;
  [key: string]: unknown;
};

export type SubscriptionProduct = {
  id: string;
  name: string;
  description?: string | null;
  image_url?: string | null;
  price_cents: number;
  subscription_interval?: string | null;
  subscription_interval_count?: number | null;
};

type Logger = {
  warn: (msg: string, meta?: unknown) => void;
  error: (msg: string, meta?: unknown) => void;
};

const defaultLogger: Logger = {
  warn: (msg, meta) => console.warn(msg, meta),
  error: (msg, meta) => console.error(msg, meta),
};

/**
 * Create a Stripe Subscription (send_invoice collection) for a customer.
 *
 * `idempotencyScope` keys the product/subscription idempotency keys — pass the
 * checkout session id (hosted) or the order id (embedded). `checkoutSessionId`
 * is optional metadata only (present for hosted, omitted for embedded).
 */
export async function createSendInvoiceStripeSubscription(params: {
  customerId: string;
  tenantId: string;
  product: SubscriptionProduct;
  patientId: string;
  customerEmail: string | null;
  stripeSecretKey: string;
  idempotencyScope: string;
  paymentMethodId?: string | null;
  subscriptionDiscount?: StripeSubscriptionDiscount | null;
  checkoutSessionId?: string | null;
  logger?: Logger;
}): Promise<StripeSubscriptionResponse> {
  const {
    customerId,
    tenantId,
    product,
    patientId,
    customerEmail,
    stripeSecretKey,
    idempotencyScope,
    paymentMethodId,
    subscriptionDiscount,
    checkoutSessionId,
  } = params;

  if (!customerId || !customerId.trim()) {
    throw new Error(
      "Cannot create Stripe subscription without a Stripe customer",
    );
  }

  // Reuse an existing Stripe product for this allia product, else create one.
  let stripeProductId: string | null = null;
  const searchQuery = `metadata['allia_product_id']:'${product.id}'`;
  const searchRes = await fetch(
    `https://api.stripe.com/v1/products/search?query=${
      encodeURIComponent(searchQuery)
    }&limit=1`,
    { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
  );
  if (searchRes.ok) {
    const searchBody = await searchRes.json();
    stripeProductId = searchBody?.data?.[0]?.id ?? null;
  }

  if (!stripeProductId) {
    const stripeProductParams = new URLSearchParams();
    stripeProductParams.append("name", product.name);
    if (product.description) {
      stripeProductParams.append("description", product.description);
    }
    if (product.image_url) {
      stripeProductParams.append("images[0]", product.image_url);
    }
    stripeProductParams.append("metadata[tenant_id]", tenantId);
    stripeProductParams.append("metadata[allia_product_id]", product.id);
    if (checkoutSessionId) {
      stripeProductParams.append(
        "metadata[checkout_session_id]",
        checkoutSessionId,
      );
    }

    const stripeProductResponse = await fetch(
      "https://api.stripe.com/v1/products",
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
          "Idempotency-Key": `allia_checkout_product_${idempotencyScope}`,
        },
        body: stripeProductParams.toString(),
      },
    );

    if (!stripeProductResponse.ok) {
      throw new Error(
        `Failed to create Stripe product for subscription: ${await stripeProductResponse
          .text()}`,
      );
    }

    const stripeProduct = (await stripeProductResponse.json()) as {
      id?: string;
    };
    if (!stripeProduct.id) {
      throw new Error("Stripe product creation returned no id");
    }
    stripeProductId = stripeProduct.id;
  }

  const stripeParams = new URLSearchParams();
  stripeParams.append("customer", customerId.trim());
  stripeParams.append("collection_method", "send_invoice");
  stripeParams.append("days_until_due", "30");
  if (paymentMethodId?.trim()) {
    stripeParams.append("default_payment_method", paymentMethodId.trim());
  }
  if (subscriptionDiscount?.couponId) {
    stripeParams.append("discounts[0][coupon]", subscriptionDiscount.couponId);
  } else if (subscriptionDiscount?.promotionCodeId) {
    stripeParams.append(
      "discounts[0][promotion_code]",
      subscriptionDiscount.promotionCodeId,
    );
  }
  stripeParams.append("metadata[tenant_id]", tenantId);
  stripeParams.append("metadata[product_id]", product.id);
  stripeParams.append("metadata[patient_id]", patientId);
  if (checkoutSessionId) {
    stripeParams.append("metadata[checkout_session_id]", checkoutSessionId);
  }
  if (customerEmail) {
    stripeParams.append("metadata[customer_email]", customerEmail);
  }
  stripeParams.append("items[0][price_data][currency]", "usd");
  stripeParams.append(
    "items[0][price_data][unit_amount]",
    `${product.price_cents}`,
  );
  stripeParams.append("items[0][price_data][product]", stripeProductId);
  if (product.subscription_interval) {
    stripeParams.append(
      "items[0][price_data][recurring][interval]",
      product.subscription_interval,
    );
  }
  if (
    typeof product.subscription_interval_count === "number" &&
    product.subscription_interval_count > 1
  ) {
    stripeParams.append(
      "items[0][price_data][recurring][interval_count]",
      `${product.subscription_interval_count}`,
    );
  }

  const response = await fetch("https://api.stripe.com/v1/subscriptions", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${stripeSecretKey}`,
      "Content-Type": "application/x-www-form-urlencoded",
      "Idempotency-Key": `allia_checkout_subscription_${idempotencyScope}`,
    },
    body: stripeParams.toString(),
  });

  if (!response.ok) {
    throw new Error(
      `Failed to create Stripe subscription: ${await response.text()}`,
    );
  }

  const subscription = (await response.json()) as StripeSubscriptionResponse;
  return normalizeSubscriptionPeriodEnd(subscription);
}

/**
 * Resolve the subscription's current period end across Stripe API versions.
 *
 * As of API version 2026-01-28.clover, `current_period_end`/`current_period_start`
 * are no longer on the subscription object — they live on each subscription item
 * (`items.data[].current_period_end`). Older versions still return them at the
 * top level. This hoists the item-level value onto the returned object so callers
 * can keep reading `subscription.current_period_end` regardless of version.
 */
export function normalizeSubscriptionPeriodEnd(
  subscription: StripeSubscriptionResponse,
): StripeSubscriptionResponse {
  if (typeof subscription.current_period_end === "number") {
    return subscription;
  }

  const items = subscription as unknown as {
    items?: { data?: Array<{ current_period_end?: number | null }> };
  };
  const itemPeriodEnd = items.items?.data
    ?.map((item) => item?.current_period_end)
    .find((value): value is number => typeof value === "number");

  if (typeof itemPeriodEnd === "number") {
    subscription.current_period_end = itemPeriodEnd;
  }
  return subscription;
}

/** Upsert the subscription↔Stripe link row, resolving conflicts. */
export async function upsertSubscriptionProviderLink(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  subscriptionId: string | null;
  paymentProviderId: string;
  providerSubscriptionId?: string | null;
  providerCheckoutSessionId?: string | null;
  logger?: Logger;
}): Promise<string | null> {
  const {
    supabase,
    tenantId,
    subscriptionId,
    paymentProviderId,
    providerSubscriptionId,
    providerCheckoutSessionId,
    logger = defaultLogger,
  } = params;

  if (!subscriptionId) return null;
  if (!providerSubscriptionId && !providerCheckoutSessionId) {
    return subscriptionId;
  }

  const payload: Record<string, unknown> = {
    tenant_id: tenantId,
    subscription_id: subscriptionId,
    payment_provider_id: paymentProviderId,
  };
  if (providerSubscriptionId) {
    payload.provider_subscription_id = providerSubscriptionId;
  }
  if (providerCheckoutSessionId) {
    payload.provider_checkout_session_id = providerCheckoutSessionId;
  }

  const { error } = await supabase
    .from("subscription_payment_provider_links")
    .upsert(payload, {
      onConflict: "subscription_id,payment_provider_id",
      ignoreDuplicates: false,
    });

  if (!error) {
    return subscriptionId;
  }

  logger.warn("Failed to upsert subscription payment provider link", {
    tenantId,
    subscriptionId,
    providerSubscriptionId: providerSubscriptionId || null,
    providerCheckoutSessionId: providerCheckoutSessionId || null,
    error: error.message,
  });

  if (providerSubscriptionId) {
    const { data: existingLink } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", paymentProviderId)
      .eq("provider_subscription_id", providerSubscriptionId)
      .maybeSingle();

    if (existingLink?.subscription_id) {
      return existingLink.subscription_id;
    }
  }

  if (providerCheckoutSessionId) {
    const { data: existingLinks } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", paymentProviderId)
      .eq("provider_checkout_session_id", providerCheckoutSessionId)
      .order("created_at", { ascending: false })
      .limit(1);

    return existingLinks?.[0]?.subscription_id || null;
  }

  return null;
}

/**
 * Find or create the local `subscriptions` row for an order's subscription and
 * ensure the provider link exists. Returns the resolved subscription id.
 */
export async function ensureOrderSubscription(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  patientId: string;
  productId?: string | null;
  stripePaymentProviderId: string;
  providerSubscriptionId?: string | null;
  providerCheckoutSessionId?: string | null;
  startedAt?: string | null;
  renewalAt?: string | null;
  expiresAt?: string | null;
  logger?: Logger;
}): Promise<string | null> {
  const {
    supabase,
    tenantId,
    patientId,
    productId,
    stripePaymentProviderId,
    providerSubscriptionId,
    providerCheckoutSessionId,
    startedAt,
    renewalAt,
    expiresAt,
    logger = defaultLogger,
  } = params;

  let subscriptionId: string | null = null;
  let createdSubscriptionId: string | null = null;

  if (providerSubscriptionId) {
    const { data: linkBySubscriptionId } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_subscription_id", providerSubscriptionId)
      .maybeSingle();

    subscriptionId = linkBySubscriptionId?.subscription_id || null;
  }

  if (!subscriptionId && providerCheckoutSessionId) {
    const { data: linkByCheckoutSessionId } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", providerCheckoutSessionId)
      .maybeSingle();

    subscriptionId = linkByCheckoutSessionId?.subscription_id || null;
  }

  if (!subscriptionId) {
    let subscriptionQuery = supabase
      .from("subscriptions")
      .select("id")
      .eq("tenant_id", tenantId)
      .eq("patient_id", patientId)
      .neq("status", "cancelled")
      .is("cancelled_at", null);

    if (productId) {
      subscriptionQuery = subscriptionQuery.eq("product_id", productId);
    }

    const { data: subscriptionsByPatient } = await subscriptionQuery
      .order("created_at", { ascending: false })
      .limit(1);

    subscriptionId = subscriptionsByPatient?.[0]?.id || null;
  }

  if (
    !subscriptionId && (providerSubscriptionId || providerCheckoutSessionId)
  ) {
    const nowIso = dateTime().toISOString();
    const { data: createdSubscription, error: createdSubscriptionError } =
      await supabase
        .from("subscriptions")
        .insert({
          tenant_id: tenantId,
          patient_id: patientId,
          product_id: productId || null,
          status: "pending_validation",
          started_at: startedAt || nowIso,
          current_period_end_at: renewalAt || null,
          expires_at: expiresAt || renewalAt || null,
        })
        .select("id")
        .single();

    if (createdSubscriptionError) {
      logger.error("Failed to create subscription for order", {
        tenantId,
        patientId,
        productId: productId || null,
        providerSubscriptionId: providerSubscriptionId || null,
        providerCheckoutSessionId: providerCheckoutSessionId || null,
        error: createdSubscriptionError.message,
      });
      return null;
    }

    subscriptionId = createdSubscription.id;
    createdSubscriptionId = createdSubscription.id;
  }

  if (!subscriptionId) return null;

  const linkedSubscriptionId = await upsertSubscriptionProviderLink({
    supabase,
    tenantId,
    subscriptionId,
    paymentProviderId: stripePaymentProviderId,
    providerSubscriptionId: providerSubscriptionId || null,
    providerCheckoutSessionId: providerCheckoutSessionId || null,
    logger,
  });

  if (!linkedSubscriptionId) return null;

  if (createdSubscriptionId && linkedSubscriptionId !== createdSubscriptionId) {
    await supabase
      .from("subscriptions")
      .delete()
      .eq("id", createdSubscriptionId)
      .eq("tenant_id", tenantId)
      .eq("patient_id", patientId);
  }

  return linkedSubscriptionId;
}
