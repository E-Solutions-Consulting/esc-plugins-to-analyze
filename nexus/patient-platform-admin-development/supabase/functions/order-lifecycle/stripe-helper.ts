import { dateTime } from "../_shared/dayjs.ts";
import { trackFriendbuyPurchaseForOrder } from "../_shared/friendbuy.ts";
import {
  createSendInvoiceStripeSubscription,
  ensureOrderSubscription,
  type StripeSubscriptionDiscount,
  upsertSubscriptionProviderLink,
} from "../_shared/stripe-subscription.ts";

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;

interface OrderForStripeCapture {
  id: string;
  tenant_id: string;
  subscription_id: string | null;
  product_id?: string | null;
  renewal_at?: string | null;
  paid_at?: string | null;
  created_at?: string;
}

export interface PaymentCaptureResult {
  captured: boolean;
  alreadyCaptured: boolean;
  message: string;
}

export interface PaymentCollectedScheduleSyncResult {
  synced: boolean;
  message: string;
  orderExpirationAt: string | null;
  planRenewalAt: string | null;
  // Discriminates specific non-synced outcomes so callers can react without
  // matching on the human-readable message. "missing_subscription_link" means
  // the plan has no Stripe subscription linked yet — expected transiently for
  // embedded/payment-first orders whose subscription is created concurrently.
  code?: "missing_subscription_link";
}

interface StripePaymentIntent {
  id: string;
  status: string;
  capture_method?: string;
  metadata?: Record<string, string>;
  payment_method?: string | { id?: string } | null;
  latest_charge?: string | { id?: string } | null;
}

interface OrderPaymentProviderTransaction {
  id: string;
  provider_payment_intent_id: string | null;
  provider_invoice_id: string | null;
  provider_subscription_id?: string | null;
  provider_checkout_session_id?: string | null;
  payment_status: string | null;
  paid_at: string | null;
  provider_charge_id: string | null;
}

interface StripeInvoice {
  id: string;
  status: string;
  auto_advance?: boolean;
  amount_due?: number;
  amount_remaining?: number;
  currency?: string;
  customer?: string | { id?: string };
  default_payment_method?: string | { id?: string } | null;
  payment_intent?: string | { id?: string } | null;
  subscription?:
    | string
    | {
      id?: string;
      default_payment_method?: string | { id?: string } | null;
    };
  metadata?: Record<string, string>;
}

interface StripeInvoiceListResponse {
  data?: StripeInvoice[];
}

interface StripeExpandedCustomer {
  id?: string;
  invoice_settings?: {
    default_payment_method?: string | { id?: string } | null;
  };
}

interface StripeInvoiceExpandedForManualCapture extends StripeInvoice {
  customer?: string | StripeExpandedCustomer;
  subscription?:
    | string
    | {
      id?: string;
      default_payment_method?: string | { id?: string } | null;
    };
}

interface StripeCheckoutSessionForManualCapture {
  id: string;
  setup_intent?:
    | string
    | {
      id?: string;
      payment_method?: string | { id?: string } | null;
    }
    | null;
  payment_intent?:
    | string
    | {
      id?: string;
      payment_method?: string | { id?: string } | null;
    }
    | null;
}

interface StripeSetupIntentForManualCapture {
  id?: string;
  payment_method?: string | { id?: string } | null;
}

interface StripePaymentMethodListResponse {
  data?: Array<{ id?: string }>;
}

interface ManualCapturePreparationResult {
  paymentIntentId: string | null;
  paymentStatus: string | null;
  providerInvoiceId: string | null;
  providerSubscriptionId: string | null;
  providerCheckoutSessionId: string | null;
  message: string;
}

function selectPreferredPaymentTransactionForCapture(
  transactions: OrderPaymentProviderTransaction[],
): OrderPaymentProviderTransaction | null {
  if (transactions.length === 0) return null;

  const initialCheckoutTransaction = transactions.find(
    (transaction) =>
      Boolean(transaction.provider_payment_intent_id) &&
      !transaction.provider_invoice_id,
  );
  if (initialCheckoutTransaction) return initialCheckoutTransaction;

  const capturableTransaction = transactions.find((transaction) =>
    Boolean(transaction.provider_payment_intent_id)
  );
  if (capturableTransaction) return capturableTransaction;

  return transactions[0] || null;
}

interface SubscriptionRenewalProductConfig {
  id: string;
  subscription_interval: string | null;
  subscription_interval_count: number | null;
  subscription_renewal_lead_days: number | null;
}

function triggerOrderLifecycleForOrderAsync(
  orderId: string,
  tenantId: string,
  requestId: string,
): void {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    console.warn(
      "Unable to trigger async order-lifecycle: missing env config",
      {
        requestId,
        orderId,
        tenantId,
      },
    );
    return;
  }

  const lifecycleUrl =
    `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;
  fetch(lifecycleUrl, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${supabaseServiceKey}`,
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-request-source": "order-lifecycle:payment_collected",
    },
  })
    .then((response) => {
      console.info("Async order-lifecycle trigger completed", {
        requestId,
        orderId,
        tenantId,
        status: response.status,
      });
    })
    .catch((error) => {
      console.warn("Async order-lifecycle trigger failed", {
        requestId,
        orderId,
        tenantId,
        error: error instanceof Error ? error.message : String(error),
      });
    });
}

async function trackFriendbuyPurchaseForCollectedOrder(params: {
  supabase: SupabaseClient;
  orderId: string;
  tenantId: string;
  requestId: string;
  source: string;
}): Promise<void> {
  await trackFriendbuyPurchaseForOrder(params.supabase, {
    tenantId: params.tenantId,
    orderId: params.orderId,
    requestId: params.requestId,
  }).catch((error) => {
    console.warn(
      "Friendbuy purchase tracking failed after payment collection",
      {
        requestId: params.requestId,
        orderId: params.orderId,
        tenantId: params.tenantId,
        source: params.source,
        error: error instanceof Error ? error.message : String(error),
      },
    );
  });
}

async function markOrderAsPaymentCollectedIfNeeded(params: {
  supabase: SupabaseClient;
  order: OrderForStripeCapture;
  paidAt: string;
  requestId: string;
  source: string;
}): Promise<boolean> {
  const { supabase, order, paidAt, requestId, source } = params;

  const { data: paymentCollectedStatus, error: paymentCollectedStatusError } =
    await supabase
      .from("order_statuses")
      .select("id, status_key, display_order")
      .eq("status_key", "payment_collected")
      .eq("is_active", true)
      .maybeSingle();

  if (paymentCollectedStatusError || !paymentCollectedStatus?.id) {
    console.warn(
      "Failed to resolve payment_collected status while syncing payment success",
      {
        requestId,
        orderId: order.id,
        source,
        error: paymentCollectedStatusError?.message || "status_not_found",
      },
    );
    return false;
  }

  const { data: currentOrder, error: currentOrderError } = await supabase
    .from("orders")
    .select("id, status_id, order_statuses(status_key, display_order)")
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id)
    .maybeSingle();

  if (currentOrderError || !currentOrder) {
    console.warn("Failed to fetch current order status for payment sync", {
      requestId,
      orderId: order.id,
      source,
      error: currentOrderError?.message || "order_not_found",
    });
    return false;
  }

  const currentStatus = currentOrder.order_statuses as {
    status_key?: string;
    display_order?: number;
  } | null;

  const currentDisplayOrder = typeof currentStatus?.display_order === "number"
    ? currentStatus.display_order
    : null;
  const paymentCollectedDisplayOrder =
    typeof paymentCollectedStatus.display_order === "number"
      ? paymentCollectedStatus.display_order
      : null;

  if (
    currentDisplayOrder !== null &&
    paymentCollectedDisplayOrder !== null &&
    currentDisplayOrder >= paymentCollectedDisplayOrder
  ) {
    const { error: paidAtError } = await supabase
      .from("orders")
      .update({ paid_at: paidAt })
      .eq("id", order.id)
      .eq("tenant_id", order.tenant_id);

    if (paidAtError) {
      console.warn(
        "Failed to sync paid_at for order already beyond payment_collected",
        {
          requestId,
          orderId: order.id,
          source,
          error: paidAtError.message,
        },
      );
      return false;
    }
    return true;
  }

  const statusChangedAt = dateTime().toISOString();
  const { error: orderUpdateError } = await supabase
    .from("orders")
    .update({
      status_id: paymentCollectedStatus.id,
      status_changed_at: statusChangedAt,
      paid_at: paidAt,
    })
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id);

  if (orderUpdateError) {
    console.warn(
      "Failed to update order to payment_collected after payment success",
      {
        requestId,
        orderId: order.id,
        source,
        error: orderUpdateError.message,
      },
    );
    return false;
  }

  const { error: historyError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: order.id,
      status_id: paymentCollectedStatus.id,
      notes: `Auto-advanced to payment_collected (${source})`,
    });

  if (historyError) {
    console.warn(
      "Failed to insert order_status_history for payment_collected",
      {
        requestId,
        orderId: order.id,
        source,
        error: historyError.message,
      },
    );
  }

  return true;
}

function extractStripeChargeId(
  paymentIntent: StripePaymentIntent,
): string | null {
  if (
    typeof paymentIntent.latest_charge === "string" &&
    paymentIntent.latest_charge.trim().length > 0
  ) {
    return paymentIntent.latest_charge.trim();
  }
  if (
    paymentIntent.latest_charge &&
    typeof paymentIntent.latest_charge === "object" &&
    typeof paymentIntent.latest_charge.id === "string" &&
    paymentIntent.latest_charge.id.trim().length > 0
  ) {
    return paymentIntent.latest_charge.id.trim();
  }
  return null;
}

function getStripeObjectId(
  value: string | { id?: string } | null | undefined,
): string | null {
  if (typeof value === "string" && value.trim().length > 0) {
    return value.trim();
  }
  if (
    value &&
    typeof value === "object" &&
    typeof value.id === "string" &&
    value.id.trim().length > 0
  ) {
    return value.id.trim();
  }
  return null;
}

function getPaymentIntentIdFromInvoice(invoice: StripeInvoice): string | null {
  return getStripeObjectId(invoice.payment_intent ?? null);
}

function getCustomerIdFromInvoice(
  invoice: StripeInvoiceExpandedForManualCapture,
): string | null {
  if (typeof invoice.customer === "string") {
    return invoice.customer.trim() || null;
  }
  return invoice.customer?.id?.trim() || null;
}

function getDefaultPaymentMethodIdFromInvoice(
  invoice: StripeInvoiceExpandedForManualCapture,
): string | null {
  const invoiceDefault = getStripeObjectId(invoice.default_payment_method);
  if (invoiceDefault) return invoiceDefault;

  if (invoice.subscription && typeof invoice.subscription === "object") {
    const subscriptionDefault = getStripeObjectId(
      invoice.subscription.default_payment_method || null,
    );
    if (subscriptionDefault) return subscriptionDefault;
  }

  if (invoice.customer && typeof invoice.customer === "object") {
    const customerDefault = getStripeObjectId(
      invoice.customer.invoice_settings?.default_payment_method || null,
    );
    if (customerDefault) return customerDefault;
  }

  return null;
}

async function fetchStripePaymentIntent(
  paymentIntentId: string,
  stripeSecretKey: string,
): Promise<StripePaymentIntent | null> {
  const response = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}?expand[]=latest_charge`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    return null;
  }

  return (await response.json()) as StripePaymentIntent;
}

async function fetchStripeSetupIntentPaymentMethod(
  setupIntentId: string,
  stripeSecretKey: string,
  requestId: string,
  checkoutSessionId: string | null,
): Promise<string | null> {
  const response = await fetch(
    `https://api.stripe.com/v1/setup_intents/${
      encodeURIComponent(
        setupIntentId,
      )
    }`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to fetch setup intent for payment method fallback", {
      requestId,
      setupIntentId,
      checkoutSessionId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  const setupIntent =
    (await response.json()) as StripeSetupIntentForManualCapture;
  return getStripeObjectId(setupIntent.payment_method || null);
}

async function fetchExpandedStripeInvoice(
  invoiceId: string,
  stripeSecretKey: string,
): Promise<StripeInvoiceExpandedForManualCapture | null> {
  const response = await fetch(
    "https://api.stripe.com/v1/invoices/" +
      `${invoiceId}` +
      "?expand[]=default_payment_method" +
      "&expand[]=subscription.default_payment_method" +
      "&expand[]=customer.invoice_settings.default_payment_method",
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    return null;
  }

  return (await response.json()) as StripeInvoiceExpandedForManualCapture;
}

async function setStripeInvoiceAutoAdvanceFalse(
  invoiceId: string,
  stripeSecretKey: string,
): Promise<void> {
  const params = new URLSearchParams();
  params.append("auto_advance", "false");

  await fetch(`https://api.stripe.com/v1/invoices/${invoiceId}`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${stripeSecretKey}`,
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: params.toString(),
  });
}

async function finalizeStripeInvoiceForManualCapture(
  invoiceId: string,
  stripeSecretKey: string,
): Promise<StripeInvoice | null> {
  const params = new URLSearchParams();
  params.append("auto_advance", "false");

  const response = await fetch(
    `https://api.stripe.com/v1/invoices/${invoiceId}/finalize`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params.toString(),
    },
  );

  if (!response.ok) {
    return null;
  }

  return (await response.json()) as StripeInvoice;
}

async function updateStripePaymentIntentCaptureMethodToManual(
  paymentIntentId: string,
  stripeSecretKey: string,
): Promise<StripePaymentIntent | null> {
  const params = new URLSearchParams();
  params.append("capture_method", "manual");

  const response = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params.toString(),
    },
  );

  if (!response.ok) {
    return null;
  }

  return (await response.json()) as StripePaymentIntent;
}

async function confirmStripePaymentIntentForManualCapture(
  paymentIntentId: string,
  stripeSecretKey: string,
): Promise<StripePaymentIntent | null> {
  const params = new URLSearchParams();
  params.append("off_session", "true");

  const response = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}/confirm`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params.toString(),
    },
  );

  if (!response.ok) {
    return null;
  }

  return (await response.json()) as StripePaymentIntent;
}

async function fetchCheckoutSessionPaymentMethod(
  checkoutSessionId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<string | null> {
  const response = await fetch(
    "https://api.stripe.com/v1/checkout/sessions/" +
      `${checkoutSessionId}` +
      "?expand[]=setup_intent.payment_method" +
      "&expand[]=payment_intent.payment_method",
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn(
      "Failed to fetch checkout session for payment method fallback",
      {
        requestId,
        checkoutSessionId,
        status: response.status,
        error: errorText,
      },
    );
    return null;
  }

  const session =
    (await response.json()) as StripeCheckoutSessionForManualCapture;
  if (session.setup_intent && typeof session.setup_intent === "object") {
    const setupPaymentMethod = getStripeObjectId(
      session.setup_intent.payment_method || null,
    );
    if (setupPaymentMethod) return setupPaymentMethod;
  }
  if (typeof session.setup_intent === "string" && session.setup_intent.trim()) {
    const setupPaymentMethod = await fetchStripeSetupIntentPaymentMethod(
      session.setup_intent.trim(),
      stripeSecretKey,
      requestId,
      checkoutSessionId,
    );
    if (setupPaymentMethod) return setupPaymentMethod;
  }

  if (session.payment_intent && typeof session.payment_intent === "object") {
    const paymentIntentPaymentMethod = getStripeObjectId(
      session.payment_intent.payment_method || null,
    );
    if (paymentIntentPaymentMethod) return paymentIntentPaymentMethod;
  }
  if (
    typeof session.payment_intent === "string" &&
    session.payment_intent.trim()
  ) {
    const paymentIntent = await fetchStripePaymentIntent(
      session.payment_intent.trim(),
      stripeSecretKey,
    );
    const paymentIntentPaymentMethod = paymentIntent
      ? getStripeObjectId(paymentIntent.payment_method || null)
      : null;
    if (paymentIntentPaymentMethod) return paymentIntentPaymentMethod;
  }

  return null;
}

async function fetchCustomerFallbackPaymentMethod(
  customerId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<string | null> {
  const response = await fetch(
    `https://api.stripe.com/v1/customers/${
      encodeURIComponent(
        customerId,
      )
    }/payment_methods?type=card&limit=1`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to list customer payment methods for fallback", {
      requestId,
      customerId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  const paymentMethods =
    (await response.json()) as StripePaymentMethodListResponse;
  const paymentMethodId = paymentMethods.data?.[0]?.id?.trim() || null;
  return paymentMethodId || null;
}

async function setStripeCustomerDefaultPaymentMethod(
  customerId: string,
  paymentMethodId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<void> {
  const params = new URLSearchParams();
  params.append("invoice_settings[default_payment_method]", paymentMethodId);

  const response = await fetch(
    `https://api.stripe.com/v1/customers/${encodeURIComponent(customerId)}`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params.toString(),
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to set Stripe customer default payment method", {
      requestId,
      customerId,
      paymentMethodId,
      status: response.status,
      error: errorText,
    });
  }
}

async function setStripeSubscriptionDefaultPaymentMethod(
  subscriptionId: string,
  paymentMethodId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<void> {
  const params = new URLSearchParams();
  params.append("default_payment_method", paymentMethodId);

  const response = await fetch(
    `https://api.stripe.com/v1/subscriptions/${
      encodeURIComponent(
        subscriptionId,
      )
    }`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params.toString(),
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to set Stripe subscription default payment method", {
      requestId,
      subscriptionId,
      paymentMethodId,
      status: response.status,
      error: errorText,
    });
  }
}

async function createManualCapturePaymentIntentForInvoice(
  invoice: StripeInvoiceExpandedForManualCapture,
  stripeSecretKey: string,
  providerSubscriptionId: string | null,
  providerCheckoutSessionId: string | null,
  requestId: string,
): Promise<StripePaymentIntent | null> {
  const customerId = getCustomerIdFromInvoice(invoice);
  const paymentMethodId = getDefaultPaymentMethodIdFromInvoice(invoice);
  const amountCents =
    typeof invoice.amount_remaining === "number" && invoice.amount_remaining > 0
      ? invoice.amount_remaining
      : invoice.amount_due || 0;
  const currency = invoice.currency || "";

  if (!customerId || !paymentMethodId || amountCents <= 0 || !currency) {
    return null;
  }

  const params = new URLSearchParams();
  params.append("amount", `${amountCents}`);
  params.append("currency", currency);
  params.append("customer", customerId);
  params.append("payment_method", paymentMethodId);
  params.append("confirm", "true");
  params.append("off_session", "true");
  params.append("capture_method", "manual");
  params.append("metadata[invoice_id]", invoice.id);
  if (providerSubscriptionId) {
    params.append("metadata[subscription_id]", providerSubscriptionId);
  }
  if (providerCheckoutSessionId) {
    params.append("metadata[checkout_session_id]", providerCheckoutSessionId);
  }
  if (invoice.metadata?.tenant_id) {
    params.append("metadata[tenant_id]", invoice.metadata.tenant_id);
  }
  if (invoice.metadata?.product_id) {
    params.append("metadata[product_id]", invoice.metadata.product_id);
  }

  const response = await fetch("https://api.stripe.com/v1/payment_intents", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${stripeSecretKey}`,
      "Content-Type": "application/x-www-form-urlencoded",
      "Idempotency-Key": `allia_payment_pending_invoice_${invoice.id}`,
    },
    body: params.toString(),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Stripe rejected manual-capture payment intent creation", {
      requestId,
      invoiceId: invoice.id,
      customerId,
      paymentMethodId,
      amountCents,
      currency,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  return (await response.json()) as StripePaymentIntent;
}

async function ensureManualCapturePaymentIntentForOrder(params: {
  supabase: SupabaseClient;
  order: OrderForStripeCapture;
  paymentTransaction: OrderPaymentProviderTransaction | null;
  stripeProviderId: string;
  stripeSecretKey: string;
  requestId: string;
}): Promise<ManualCapturePreparationResult> {
  const {
    supabase,
    order,
    paymentTransaction,
    stripeProviderId,
    stripeSecretKey,
    requestId,
  } = params;

  let providerInvoiceId = paymentTransaction?.provider_invoice_id || null;
  let providerSubscriptionId = paymentTransaction?.provider_subscription_id ||
    null;
  let providerCheckoutSessionId =
    paymentTransaction?.provider_checkout_session_id || null;

  if (order.subscription_id) {
    const {
      data: subscriptionProviderLink,
      error: subscriptionProviderLinkError,
    } = await supabase
      .from("subscription_payment_provider_links")
      .select("provider_subscription_id, provider_checkout_session_id")
      .eq("tenant_id", order.tenant_id)
      .eq("payment_provider_id", stripeProviderId)
      .eq("subscription_id", order.subscription_id)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

    if (subscriptionProviderLinkError) {
      return {
        paymentIntentId: null,
        paymentStatus: null,
        providerInvoiceId,
        providerSubscriptionId,
        providerCheckoutSessionId,
        message:
          `Failed to resolve Stripe subscription link for order: ${subscriptionProviderLinkError.message}`,
      };
    }

    providerSubscriptionId = providerSubscriptionId ||
      subscriptionProviderLink?.provider_subscription_id ||
      null;
    providerCheckoutSessionId = providerCheckoutSessionId ||
      subscriptionProviderLink?.provider_checkout_session_id ||
      null;
  }

  if (!providerInvoiceId && providerSubscriptionId) {
    const invoicesResponse = await fetch(
      `https://api.stripe.com/v1/invoices?subscription=${
        encodeURIComponent(
          providerSubscriptionId,
        )
      }&limit=10`,
      {
        method: "GET",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
        },
      },
    );

    if (invoicesResponse.ok) {
      const invoices =
        (await invoicesResponse.json()) as StripeInvoiceListResponse;
      const invoiceCandidates = invoices.data || [];
      const preferredInvoice = invoiceCandidates.find(
        (candidate) =>
          candidate.status !== "paid" &&
          candidate.status !== "void" &&
          candidate.status !== "uncollectible",
      ) ||
        invoiceCandidates[0] ||
        null;
      providerInvoiceId = preferredInvoice?.id || null;
    }
  }

  if (!providerInvoiceId) {
    return {
      paymentIntentId: null,
      paymentStatus: null,
      providerInvoiceId,
      providerSubscriptionId,
      providerCheckoutSessionId,
      message:
        "No Stripe invoice found to create manual-capture payment intent",
    };
  }

  let expandedInvoice = await fetchExpandedStripeInvoice(
    providerInvoiceId,
    stripeSecretKey,
  );

  if (!expandedInvoice) {
    return {
      paymentIntentId: null,
      paymentStatus: null,
      providerInvoiceId,
      providerSubscriptionId,
      providerCheckoutSessionId,
      message: "Failed to fetch Stripe invoice while preparing manual capture",
    };
  }

  providerSubscriptionId = providerSubscriptionId ||
    getStripeObjectId(expandedInvoice.subscription || null);

  if (expandedInvoice.status === "paid") {
    const existingPaidIntentId = getPaymentIntentIdFromInvoice(expandedInvoice);
    return {
      paymentIntentId: existingPaidIntentId,
      paymentStatus: "succeeded",
      providerInvoiceId,
      providerSubscriptionId,
      providerCheckoutSessionId,
      message: existingPaidIntentId
        ? `Invoice ${providerInvoiceId} is already paid`
        : `Invoice ${providerInvoiceId} is paid and has no payment intent`,
    };
  }

  if (expandedInvoice.auto_advance !== false) {
    await setStripeInvoiceAutoAdvanceFalse(providerInvoiceId, stripeSecretKey);
  }

  let paymentIntentId = getPaymentIntentIdFromInvoice(expandedInvoice);

  if (!paymentIntentId && expandedInvoice.status === "draft") {
    const finalizedInvoice = await finalizeStripeInvoiceForManualCapture(
      providerInvoiceId,
      stripeSecretKey,
    );
    if (finalizedInvoice) {
      paymentIntentId = getPaymentIntentIdFromInvoice(finalizedInvoice);
      expandedInvoice = (await fetchExpandedStripeInvoice(
        providerInvoiceId,
        stripeSecretKey,
      )) || expandedInvoice;
    }
  }

  let paymentIntent: StripePaymentIntent | null = null;
  if (paymentIntentId) {
    paymentIntent = await fetchStripePaymentIntent(
      paymentIntentId,
      stripeSecretKey,
    );
  }

  let fallbackPaymentMethodId: string | null = null;
  if (!getDefaultPaymentMethodIdFromInvoice(expandedInvoice)) {
    const invoiceCustomerId = getCustomerIdFromInvoice(expandedInvoice);

    if (providerCheckoutSessionId) {
      fallbackPaymentMethodId = await fetchCheckoutSessionPaymentMethod(
        providerCheckoutSessionId,
        stripeSecretKey,
        requestId,
      );
    }

    if (!fallbackPaymentMethodId && invoiceCustomerId) {
      fallbackPaymentMethodId = await fetchCustomerFallbackPaymentMethod(
        invoiceCustomerId,
        stripeSecretKey,
        requestId,
      );
    }

    if (fallbackPaymentMethodId) {
      expandedInvoice.default_payment_method = fallbackPaymentMethodId;

      if (invoiceCustomerId) {
        await setStripeCustomerDefaultPaymentMethod(
          invoiceCustomerId,
          fallbackPaymentMethodId,
          stripeSecretKey,
          requestId,
        );
      }

      if (providerSubscriptionId) {
        await setStripeSubscriptionDefaultPaymentMethod(
          providerSubscriptionId,
          fallbackPaymentMethodId,
          stripeSecretKey,
          requestId,
        );
      }
    }
  }

  if (!paymentIntentId || !paymentIntent) {
    const createdPaymentIntent =
      await createManualCapturePaymentIntentForInvoice(
        expandedInvoice,
        stripeSecretKey,
        providerSubscriptionId,
        providerCheckoutSessionId,
        requestId,
      );

    if (!createdPaymentIntent?.id) {
      return {
        paymentIntentId: null,
        paymentStatus: null,
        providerInvoiceId,
        providerSubscriptionId,
        providerCheckoutSessionId,
        message: "Failed to create Stripe payment intent for manual capture",
      };
    }

    paymentIntentId = createdPaymentIntent.id;
    paymentIntent = createdPaymentIntent;
  }

  if (paymentIntent.status === "requires_payment_method") {
    return {
      paymentIntentId,
      paymentStatus: paymentIntent.status,
      providerInvoiceId,
      providerSubscriptionId,
      providerCheckoutSessionId,
      message:
        `Payment intent ${paymentIntentId} requires a payment method and cannot be captured`,
    };
  }

  if (
    paymentIntent.capture_method !== "manual" &&
    paymentIntent.status !== "succeeded"
  ) {
    const updatedPaymentIntent =
      await updateStripePaymentIntentCaptureMethodToManual(
        paymentIntentId,
        stripeSecretKey,
      );
    if (updatedPaymentIntent) {
      paymentIntent = updatedPaymentIntent;
    }
  }

  if (
    paymentIntent.status === "requires_confirmation" ||
    paymentIntent.status === "requires_payment_method"
  ) {
    const confirmedPaymentIntent =
      await confirmStripePaymentIntentForManualCapture(
        paymentIntentId,
        stripeSecretKey,
      );
    if (confirmedPaymentIntent) {
      paymentIntent = confirmedPaymentIntent;
    }
  }

  return {
    paymentIntentId,
    paymentStatus: paymentIntent.status || null,
    providerInvoiceId,
    providerSubscriptionId,
    providerCheckoutSessionId,
    message:
      `Prepared manual-capture payment intent ${paymentIntentId} with status ${paymentIntent.status}`,
  };
}

async function markStripeInvoicePaidOutOfBandIfNeeded(params: {
  stripeSecretKey: string;
  providerInvoiceId: string | null;
  requestId: string;
  orderId: string;
  paymentIntentId: string;
}): Promise<void> {
  const {
    stripeSecretKey,
    providerInvoiceId,
    requestId,
    orderId,
    paymentIntentId,
  } = params;
  if (!providerInvoiceId) return;

  const invoiceResponse = await fetch(
    `https://api.stripe.com/v1/invoices/${providerInvoiceId}`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!invoiceResponse.ok) {
    const errorText = await invoiceResponse.text();
    console.warn("Failed to retrieve Stripe invoice while syncing paid state", {
      requestId,
      orderId,
      paymentIntentId,
      providerInvoiceId,
      status: invoiceResponse.status,
      error: errorText,
    });
    return;
  }

  const stripeInvoice = (await invoiceResponse.json()) as StripeInvoice;
  if (stripeInvoice.status === "paid") {
    return;
  }

  const payParams = new URLSearchParams();
  payParams.append("paid_out_of_band", "true");
  const payResponse = await fetch(
    `https://api.stripe.com/v1/invoices/${providerInvoiceId}/pay`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: payParams.toString(),
    },
  );

  if (!payResponse.ok) {
    const errorText = await payResponse.text();
    console.warn(
      "Failed to mark Stripe invoice as paid_out_of_band after capture",
      {
        requestId,
        orderId,
        paymentIntentId,
        providerInvoiceId,
        status: payResponse.status,
        error: errorText,
      },
    );
    return;
  }

  console.info("Marked Stripe invoice as paid_out_of_band after capture", {
    requestId,
    orderId,
    paymentIntentId,
    providerInvoiceId,
  });
}

async function markLinkedSubscriptionValidIfPendingValidation(
  supabase: SupabaseClient,
  tenantId: string,
  subscriptionId: string | null,
  requestId: string,
  source: string,
): Promise<void> {
  if (!subscriptionId) return;

  const { data: subscription, error: subscriptionLookupError } = await supabase
    .from("subscriptions")
    .select("id, status")
    .eq("id", subscriptionId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (subscriptionLookupError || !subscription) {
    console.warn(
      "Failed to resolve linked subscription while marking plan valid",
      {
        requestId,
        tenantId,
        subscriptionId,
        source,
        error: subscriptionLookupError?.message || "subscription_not_found",
      },
    );
    return;
  }

  if (subscription.status !== "pending_validation") {
    return;
  }

  const { error: activateError } = await supabase
    .from("subscriptions")
    .update({
      status: "active",
      paused_at: null,
      cancelled_at: null,
    })
    .eq("id", subscriptionId)
    .eq("tenant_id", tenantId);

  if (activateError) {
    console.warn("Failed to activate linked subscription after payment", {
      requestId,
      tenantId,
      subscriptionId,
      source,
      error: activateError.message,
    });
    return;
  }

  console.info("Linked subscription marked as active after payment", {
    requestId,
    tenantId,
    subscriptionId,
    source,
  });
}

async function validateStripePaymentProviderForOrder(params: {
  supabase: SupabaseClient;
  tenantId: string;
  orderId: string;
  stripeProviderId: string;
}): Promise<{ valid: boolean; message: string | null }> {
  const { supabase, tenantId, orderId, stripeProviderId } = params;
  const { data: providerTransactions, error: providerTransactionsError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select("payment_provider_id")
      .eq("tenant_id", tenantId)
      .eq("order_id", orderId)
      .limit(20);

  if (providerTransactionsError) {
    return {
      valid: false,
      message:
        `Failed to validate order payment provider: ${providerTransactionsError.message}`,
    };
  }

  if (!providerTransactions || providerTransactions.length === 0) {
    return {
      valid: false,
      message: "Order payment provider is not set",
    };
  }

  const hasStripeProvider = providerTransactions.some(
    (transaction: { payment_provider_id: string | null }) =>
      transaction.payment_provider_id === stripeProviderId,
  );

  if (!hasStripeProvider) {
    return {
      valid: false,
      message: "Order payment provider is not Stripe",
    };
  }

  return {
    valid: true,
    message: null,
  };
}

export interface InvoicePayResult {
  paid: boolean;
  alreadyPaid: boolean;
  paymentIntentId: string | null;
  chargeId: string | null;
  message: string;
}

export async function tryPayStripeInvoiceDirectly(
  invoiceId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<InvoicePayResult> {
  const response = await fetch(
    `https://api.stripe.com/v1/invoices/${invoiceId}/pay`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "",
    },
  );

  if (response.ok) {
    const invoice = (await response.json()) as {
      payment_intent?: string | { id?: string } | null;
      charge?: string | null;
    };
    const paymentIntentId = typeof invoice.payment_intent === "string"
      ? invoice.payment_intent
      : (invoice.payment_intent as { id?: string } | null)?.id ?? null;
    const chargeId = typeof invoice.charge === "string" ? invoice.charge : null;
    return {
      paid: true,
      alreadyPaid: false,
      paymentIntentId,
      chargeId,
      message: `Invoice ${invoiceId} paid successfully`,
    };
  }

  const errorText = await response.text();
  let errorCode: string | null = null;
  try {
    const parsed = JSON.parse(errorText) as {
      error?: { code?: string; message?: string };
    };
    errorCode = parsed.error?.code ?? null;
  } catch {
    // ignore
  }

  if (errorCode === "invoice_already_paid") {
    return {
      paid: false,
      alreadyPaid: true,
      paymentIntentId: null,
      chargeId: null,
      message: `Invoice ${invoiceId} is already paid`,
    };
  }

  console.warn("tryPayStripeInvoiceDirectly: Stripe invoice pay failed", {
    requestId,
    invoiceId,
    status: response.status,
    error: errorText,
  });
  return {
    paid: false,
    alreadyPaid: false,
    paymentIntentId: null,
    chargeId: null,
    message: `Invoice pay failed: ${response.status} ${errorText}`,
  };
}

export async function maybeCaptureStripePaymentForPaymentPendingOrder(
  supabase: SupabaseClient,
  order: OrderForStripeCapture,
  requestId: string,
): Promise<PaymentCaptureResult> {
  const { data: stripeProvider, error: stripeProviderError } = await supabase
    .from("payment_providers")
    .select("id")
    .eq("key", "stripe")
    .maybeSingle();

  if (stripeProviderError || !stripeProvider?.id) {
    return {
      captured: false,
      alreadyCaptured: false,
      message: `Stripe payment provider is unavailable: ${
        stripeProviderError?.message || "not configured"
      }`,
    };
  }

  const providerValidation = await validateStripePaymentProviderForOrder({
    supabase,
    tenantId: order.tenant_id,
    orderId: order.id,
    stripeProviderId: stripeProvider.id,
  });

  if (!providerValidation.valid) {
    return {
      captured: false,
      alreadyCaptured: false,
      message: providerValidation.message ||
        "Order payment provider is not Stripe",
    };
  }

  const { data: providerLink, error: providerLinkError } = await supabase
    .from("tenant_payment_providers")
    .select("settings")
    .eq("tenant_id", order.tenant_id)
    .eq("payment_provider_id", stripeProvider.id)
    .eq("is_enabled", true)
    .maybeSingle();

  if (providerLinkError || !providerLink) {
    return {
      captured: false,
      alreadyCaptured: false,
      message: `Stripe tenant configuration is unavailable: ${
        providerLinkError?.message || "not configured"
      }`,
    };
  }

  const stripeSecretKey =
    (providerLink.settings as Record<string, string>)?.secret_key?.trim() || "";
  if (!stripeSecretKey) {
    return {
      captured: false,
      alreadyCaptured: false,
      message: "Stripe secret key is missing for tenant",
    };
  }

  const { data: transactions, error: transactionError } = await supabase
    .from("order_payment_provider_transactions")
    .select(
      "id, provider_payment_intent_id, provider_invoice_id, provider_subscription_id, provider_checkout_session_id, payment_status, paid_at, provider_charge_id",
    )
    .eq("tenant_id", order.tenant_id)
    .eq("order_id", order.id)
    .eq("payment_provider_id", stripeProvider.id)
    .order("created_at", { ascending: false })
    .limit(20);

  if (transactionError) {
    return {
      captured: false,
      alreadyCaptured: false,
      message:
        `Failed to lookup payment transaction: ${transactionError.message}`,
    };
  }

  const transactionRows = (transactions ||
    []) as OrderPaymentProviderTransaction[];
  let paymentTransaction = selectPreferredPaymentTransactionForCapture(
    transactionRows,
  );
  let paymentIntentId = paymentTransaction?.provider_payment_intent_id || null;

  if (!paymentIntentId) {
    // For renewal orders: try paying the invoice directly first. The invoice ID is
    // stored on the transaction row when the renewal order is created from the RTDH
    // payload. This avoids needing the payment intent to be in requires_capture state.
    const invoiceIdForPay = paymentTransaction?.provider_invoice_id ?? null;
    if (invoiceIdForPay) {
      const invoicePayResult = await tryPayStripeInvoiceDirectly(
        invoiceIdForPay,
        stripeSecretKey,
        requestId,
      );

      if (invoicePayResult.paid || invoicePayResult.alreadyPaid) {
        const paidAt = dateTime().toISOString();
        const transactionUpdatePayload: Record<string, unknown> = {
          payment_status: "succeeded",
          paid_at: paidAt,
        };
        if (invoicePayResult.chargeId) {
          transactionUpdatePayload.provider_charge_id =
            invoicePayResult.chargeId;
        }
        if (invoicePayResult.paymentIntentId) {
          transactionUpdatePayload.provider_payment_intent_id =
            invoicePayResult.paymentIntentId;
        }

        if (paymentTransaction?.id) {
          await supabase
            .from("order_payment_provider_transactions")
            .update(transactionUpdatePayload)
            .eq("id", paymentTransaction.id);
        }

        await supabase
          .from("orders")
          .update({ paid_at: paidAt })
          .eq("id", order.id)
          .eq("tenant_id", order.tenant_id);

        await markLinkedSubscriptionValidIfPendingValidation(
          supabase,
          order.tenant_id,
          order.subscription_id,
          requestId,
          "invoice_pay_direct",
        );

        const paymentCollectedSynced =
          await markOrderAsPaymentCollectedIfNeeded({
            supabase,
            order,
            paidAt,
            requestId,
            source: "invoice_pay_direct",
          });

        if (paymentCollectedSynced) {
          await trackFriendbuyPurchaseForCollectedOrder({
            supabase,
            orderId: order.id,
            tenantId: order.tenant_id,
            requestId,
            source: "invoice_pay_direct",
          });
          triggerOrderLifecycleForOrderAsync(
            order.id,
            order.tenant_id,
            requestId,
          );
        }

        return {
          captured: !invoicePayResult.alreadyPaid,
          alreadyCaptured: invoicePayResult.alreadyPaid,
          message:
            `${invoicePayResult.message}; waiting for rtdh-webhook payment_collected event`,
        };
      }
      // Declined or other error — fall through to ensureManualCapturePaymentIntentForOrder
    }

    const preparationResult = await ensureManualCapturePaymentIntentForOrder({
      supabase,
      order,
      paymentTransaction,
      stripeProviderId: stripeProvider.id,
      stripeSecretKey,
      requestId,
    });

    if (!preparationResult.paymentIntentId) {
      return {
        captured: false,
        alreadyCaptured: false,
        message: preparationResult.message,
      };
    }

    const transactionUpdatePayload: Record<string, unknown> = {
      tenant_id: order.tenant_id,
      order_id: order.id,
      payment_provider_id: stripeProvider.id,
      provider_payment_intent_id: preparationResult.paymentIntentId,
      payment_status: preparationResult.paymentStatus || "unknown",
    };
    if (preparationResult.providerInvoiceId) {
      transactionUpdatePayload.provider_invoice_id =
        preparationResult.providerInvoiceId;
    }
    if (preparationResult.providerSubscriptionId) {
      transactionUpdatePayload.provider_subscription_id =
        preparationResult.providerSubscriptionId;
    }
    if (preparationResult.providerCheckoutSessionId) {
      transactionUpdatePayload.provider_checkout_session_id =
        preparationResult.providerCheckoutSessionId;
    }

    if (paymentTransaction?.id) {
      const { error: updateTransactionError } = await supabase
        .from("order_payment_provider_transactions")
        .update(transactionUpdatePayload)
        .eq("id", paymentTransaction.id);

      if (updateTransactionError) {
        return {
          captured: false,
          alreadyCaptured: false,
          message:
            `Failed to persist prepared payment intent on transaction: ${updateTransactionError.message}`,
        };
      }
    } else {
      const { data: insertedTransaction, error: insertTransactionError } =
        await supabase
          .from("order_payment_provider_transactions")
          .insert(transactionUpdatePayload)
          .select(
            "id, provider_payment_intent_id, provider_invoice_id, provider_subscription_id, provider_checkout_session_id, payment_status, paid_at, provider_charge_id",
          )
          .single();

      if (insertTransactionError) {
        return {
          captured: false,
          alreadyCaptured: false,
          message:
            `Failed to create payment transaction for prepared payment intent: ${insertTransactionError.message}`,
        };
      }

      paymentTransaction =
        insertedTransaction as OrderPaymentProviderTransaction;
    }

    paymentIntentId = preparationResult.paymentIntentId;
    paymentTransaction = {
      ...(paymentTransaction || {
        id: "",
        paid_at: null,
        provider_charge_id: null,
      }),
      provider_payment_intent_id: paymentIntentId,
      provider_invoice_id: preparationResult.providerInvoiceId ||
        paymentTransaction?.provider_invoice_id ||
        null,
      provider_subscription_id: preparationResult.providerSubscriptionId ||
        paymentTransaction?.provider_subscription_id ||
        null,
      provider_checkout_session_id:
        preparationResult.providerCheckoutSessionId ||
        paymentTransaction?.provider_checkout_session_id ||
        null,
      payment_status: preparationResult.paymentStatus ||
        paymentTransaction?.payment_status ||
        null,
    } as OrderPaymentProviderTransaction;
  }

  if (!paymentIntentId || !paymentTransaction?.id) {
    return {
      captured: false,
      alreadyCaptured: false,
      message: "Unable to persist Stripe payment intent for capture",
    };
  }

  const paymentIntent = await fetchStripePaymentIntent(
    paymentIntentId,
    stripeSecretKey,
  );

  if (!paymentIntent) {
    return {
      captured: false,
      alreadyCaptured: false,
      message: `Failed to retrieve Stripe payment intent: ${paymentIntentId}`,
    };
  }

  if (paymentIntent.status === "succeeded") {
    const paidAt = paymentTransaction.paid_at || dateTime().toISOString();
    const providerChargeId = extractStripeChargeId(paymentIntent);

    const transactionUpdatePayload: Record<string, unknown> = {
      payment_status: paymentIntent.status,
      paid_at: paidAt,
    };
    if (providerChargeId) {
      transactionUpdatePayload.provider_charge_id = providerChargeId;
    }

    const { error: transactionUpdateError } = await supabase
      .from("order_payment_provider_transactions")
      .update(transactionUpdatePayload)
      .eq("id", paymentTransaction.id);

    if (transactionUpdateError) {
      console.warn("Failed to sync already-captured payment transaction", {
        requestId,
        orderId: order.id,
        paymentIntentId,
        error: transactionUpdateError.message,
      });
    }

    const { error: orderPaidAtError } = await supabase
      .from("orders")
      .update({ paid_at: paidAt })
      .eq("id", order.id)
      .eq("tenant_id", order.tenant_id);

    if (orderPaidAtError) {
      console.warn(
        "Failed to sync order paid_at for already-captured payment",
        {
          requestId,
          orderId: order.id,
          paymentIntentId,
          error: orderPaidAtError.message,
        },
      );
    }

    await markLinkedSubscriptionValidIfPendingValidation(
      supabase,
      order.tenant_id,
      order.subscription_id,
      requestId,
      "payment_intent_already_captured",
    );

    const paymentCollectedSynced = await markOrderAsPaymentCollectedIfNeeded({
      supabase,
      order,
      paidAt,
      requestId,
      source: "payment_intent_already_captured",
    });

    await markStripeInvoicePaidOutOfBandIfNeeded({
      stripeSecretKey,
      providerInvoiceId: paymentTransaction.provider_invoice_id,
      requestId,
      orderId: order.id,
      paymentIntentId,
    });

    if (paymentCollectedSynced) {
      await trackFriendbuyPurchaseForCollectedOrder({
        supabase,
        orderId: order.id,
        tenantId: order.tenant_id,
        requestId,
        source: "payment_intent_already_captured",
      });
      triggerOrderLifecycleForOrderAsync(order.id, order.tenant_id, requestId);
    }

    return {
      captured: false,
      alreadyCaptured: true,
      message:
        `Payment intent ${paymentIntentId} is already captured; waiting for rtdh-webhook payment_collected event`,
    };
  }

  if (paymentIntent.status !== "requires_capture") {
    // For renewal orders coming from payment_failed: the transaction may hold a stale
    // failed PI. Try paying the invoice directly before giving up.
    const invoiceIdForRetry = paymentTransaction?.provider_invoice_id ?? null;
    if (invoiceIdForRetry) {
      const invoicePayResult = await tryPayStripeInvoiceDirectly(
        invoiceIdForRetry,
        stripeSecretKey,
        requestId,
      );

      if (invoicePayResult.paid || invoicePayResult.alreadyPaid) {
        const paidAt = dateTime().toISOString();
        const transactionUpdatePayload: Record<string, unknown> = {
          payment_status: "succeeded",
          paid_at: paidAt,
        };
        if (invoicePayResult.chargeId) {
          transactionUpdatePayload.provider_charge_id =
            invoicePayResult.chargeId;
        }
        if (invoicePayResult.paymentIntentId) {
          transactionUpdatePayload.provider_payment_intent_id =
            invoicePayResult.paymentIntentId;
        }

        if (paymentTransaction?.id) {
          await supabase
            .from("order_payment_provider_transactions")
            .update(transactionUpdatePayload)
            .eq("id", paymentTransaction.id);
        }

        await supabase
          .from("orders")
          .update({ paid_at: paidAt })
          .eq("id", order.id)
          .eq("tenant_id", order.tenant_id);

        await markLinkedSubscriptionValidIfPendingValidation(
          supabase,
          order.tenant_id,
          order.subscription_id,
          requestId,
          "invoice_pay_direct_retry",
        );

        const paymentCollectedSynced =
          await markOrderAsPaymentCollectedIfNeeded({
            supabase,
            order,
            paidAt,
            requestId,
            source: "invoice_pay_direct_retry",
          });

        if (paymentCollectedSynced) {
          await trackFriendbuyPurchaseForCollectedOrder({
            supabase,
            orderId: order.id,
            tenantId: order.tenant_id,
            requestId,
            source: "invoice_pay_direct_retry",
          });
          triggerOrderLifecycleForOrderAsync(
            order.id,
            order.tenant_id,
            requestId,
          );
        }

        return {
          captured: !invoicePayResult.alreadyPaid,
          alreadyCaptured: invoicePayResult.alreadyPaid,
          message:
            `${invoicePayResult.message}; waiting for rtdh-webhook payment_collected event`,
        };
      }
    }

    return {
      captured: false,
      alreadyCaptured: false,
      message:
        `Payment intent ${paymentIntentId} is not capturable (status: ${paymentIntent.status})`,
    };
  }

  const captureResponse = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}/capture`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "",
    },
  );

  if (!captureResponse.ok) {
    const errorText = await captureResponse.text();
    return {
      captured: false,
      alreadyCaptured: false,
      message: `Stripe capture failed: ${captureResponse.status} ${errorText}`,
    };
  }

  const capturedPaymentIntent =
    (await captureResponse.json()) as StripePaymentIntent;
  const capturedAt = dateTime().toISOString();
  const capturedChargeId = extractStripeChargeId(capturedPaymentIntent);
  const transactionUpdatePayload: Record<string, unknown> = {
    payment_status: capturedPaymentIntent.status,
    paid_at: capturedAt,
  };
  if (capturedChargeId) {
    transactionUpdatePayload.provider_charge_id = capturedChargeId;
  }

  const { error: transactionUpdateError } = await supabase
    .from("order_payment_provider_transactions")
    .update(transactionUpdatePayload)
    .eq("id", paymentTransaction.id);

  if (transactionUpdateError) {
    console.warn("Failed to update payment transaction after Stripe capture", {
      requestId,
      orderId: order.id,
      paymentIntentId,
      error: transactionUpdateError.message,
    });
  }

  const { error: orderPaidAtError } = await supabase
    .from("orders")
    .update({ paid_at: capturedAt })
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id);

  if (orderPaidAtError) {
    console.warn("Failed to update order paid_at after Stripe capture", {
      requestId,
      orderId: order.id,
      paymentIntentId,
      error: orderPaidAtError.message,
    });
  }

  await markLinkedSubscriptionValidIfPendingValidation(
    supabase,
    order.tenant_id,
    order.subscription_id,
    requestId,
    "payment_intent_capture",
  );

  const paymentCollectedSynced = await markOrderAsPaymentCollectedIfNeeded({
    supabase,
    order,
    paidAt: capturedAt,
    requestId,
    source: "payment_intent_capture",
  });

  await markStripeInvoicePaidOutOfBandIfNeeded({
    stripeSecretKey,
    providerInvoiceId: paymentTransaction.provider_invoice_id,
    requestId,
    orderId: order.id,
    paymentIntentId,
  });

  if (paymentCollectedSynced) {
    await trackFriendbuyPurchaseForCollectedOrder({
      supabase,
      orderId: order.id,
      tenantId: order.tenant_id,
      requestId,
      source: "payment_intent_capture",
    });
    triggerOrderLifecycleForOrderAsync(order.id, order.tenant_id, requestId);
  }

  return {
    captured: true,
    alreadyCaptured: false,
    message:
      `Payment captured for payment intent ${paymentIntentId}; waiting for rtdh-webhook payment_collected event`,
  };
}

function addSubscriptionIntervalToDate(
  baseDate: Date,
  interval: string,
  intervalCount: number,
): Date | null {
  const normalizedCount = intervalCount > 0 ? intervalCount : 1;
  const nextDate = dateTime(baseDate).toDate();

  switch (interval) {
    case "day":
      nextDate.setUTCDate(nextDate.getUTCDate() + normalizedCount);
      return nextDate;
    case "week":
      nextDate.setUTCDate(nextDate.getUTCDate() + normalizedCount * 7);
      return nextDate;
    case "month":
      nextDate.setUTCMonth(nextDate.getUTCMonth() + normalizedCount);
      return nextDate;
    case "year":
      nextDate.setUTCFullYear(nextDate.getUTCFullYear() + normalizedCount);
      return nextDate;
    default:
      return null;
  }
}

function parseIsoDate(value: string | null | undefined): Date | null {
  if (!value) return null;
  const parsed = dateTime(value).toDate();
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function areDatesEqualBySecond(
  left: string | null | undefined,
  right: string | null | undefined,
): boolean {
  const leftDate = parseIsoDate(left);
  const rightDate = parseIsoDate(right);
  if (!leftDate || !rightDate) return false;
  return Math.abs(leftDate.getTime() - rightDate.getTime()) < 1000;
}

export interface PaymentReleaseResult {
  released: boolean;
  message: string;
  action:
    | "charge_refunded"
    | "payment_intent_cancelled"
    | "payment_intent_terminal"
    | "missing_reference"
    | "not_configured"
    | "failed";
  paymentIntentId: string | null;
  chargeId: string | null;
  stripeStatus: string | null;
}

export async function releaseStripePaymentForRejectedOrder(params: {
  supabase: SupabaseClient;
  order: OrderForStripeCapture;
  requestId: string;
}): Promise<PaymentReleaseResult> {
  const { supabase, order, requestId } = params;

  // Fetch the Stripe secret key for this tenant
  const { data: stripeProvider, error: providerError } = await supabase
    .from("tenant_payment_providers")
    .select(
      `
      settings,
      payment_providers!inner (
        key
      )
    `,
    )
    .eq("tenant_id", order.tenant_id)
    .eq("is_enabled", true)
    .eq("payment_providers.key", "stripe")
    .maybeSingle();

  if (providerError || !stripeProvider) {
    return {
      released: false,
      message: "No Stripe payment provider configured for this tenant",
      action: "not_configured",
      paymentIntentId: null,
      chargeId: null,
      stripeStatus: null,
    };
  }

  const settings = stripeProvider.settings &&
      typeof stripeProvider.settings === "object" &&
      !Array.isArray(stripeProvider.settings)
    ? (stripeProvider.settings as Record<string, unknown>)
    : {};
  const stripeSecretKey = typeof settings.secret_key === "string"
    ? settings.secret_key.trim()
    : "";

  if (!stripeSecretKey) {
    return {
      released: false,
      message: "Stripe secret key not configured",
      action: "not_configured",
      paymentIntentId: null,
      chargeId: null,
      stripeStatus: null,
    };
  }

  // Find the most recent payment transaction for this order
  const { data: transactions, error: txError } = await supabase
    .from("order_payment_provider_transactions")
    .select(
      "id, payment_status, provider_payment_intent_id, provider_charge_id, provider_invoice_id",
    )
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .order("created_at", { ascending: false })
    .limit(5);

  if (txError) {
    return {
      released: false,
      message:
        `Failed to fetch order payment provider transactions: ${txError.message}`,
      action: "failed",
      paymentIntentId: null,
      chargeId: null,
      stripeStatus: null,
    };
  }

  const txList = (transactions ?? []) as Array<{
    id: string;
    payment_status: string | null;
    provider_payment_intent_id: string | null;
    provider_charge_id: string | null;
    provider_invoice_id: string | null;
  }>;

  // Resolve the payment intent ID
  let paymentIntentId: string | null = null;
  let chargeId: string | null = null;
  for (const tx of txList) {
    if (tx.provider_payment_intent_id) {
      paymentIntentId = tx.provider_payment_intent_id;
      chargeId = tx.provider_charge_id || null;
      break;
    }
  }

  if (!paymentIntentId && !chargeId) {
    return {
      released: false,
      message: "No Stripe payment reference found for this order",
      action: "missing_reference",
      paymentIntentId: null,
      chargeId: null,
      stripeStatus: null,
    };
  }

  if (paymentIntentId && !chargeId) {
    const paymentIntent = await fetchStripePaymentIntent(
      paymentIntentId,
      stripeSecretKey,
    );
    chargeId = paymentIntent ? extractStripeChargeId(paymentIntent) : null;
  }

  // If the order has been paid (captured), issue a full refund
  if (order.paid_at && (paymentIntentId || chargeId)) {
    const refundParams = new URLSearchParams();
    if (paymentIntentId) {
      refundParams.append("payment_intent", paymentIntentId);
    } else if (chargeId) {
      refundParams.append("charge", chargeId);
    }

    const refundResponse = await fetch("https://api.stripe.com/v1/refunds", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
        "Idempotency-Key": `allia_provider_rejected_refund_${order.id}`,
      },
      body: refundParams.toString(),
    });

    if (!refundResponse.ok) {
      const errorText = await refundResponse.text();
      console.error("Stripe refund failed for rejected order", {
        requestId,
        orderId: order.id,
        tenantId: order.tenant_id,
        paymentIntentId,
        chargeId,
        status: refundResponse.status,
        error: errorText,
      });
      return {
        released: false,
        message: `Stripe refund failed: ${errorText || refundResponse.status}`,
        action: "failed",
        paymentIntentId,
        chargeId,
        stripeStatus: null,
      };
    }

    console.info("Stripe charge refunded for provider-rejected order", {
      requestId,
      orderId: order.id,
      tenantId: order.tenant_id,
      chargeId,
    });
    return {
      released: true,
      message: "Stripe charge refunded due to provider rejection",
      action: "charge_refunded",
      paymentIntentId,
      chargeId,
      stripeStatus: null,
    };
  }

  // If not yet captured, cancel the payment intent authorization
  if (paymentIntentId) {
    const cancelResponse = await fetch(
      `https://api.stripe.com/v1/payment_intents/${paymentIntentId}/cancel`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
          "Idempotency-Key":
            `allia_provider_rejected_payment_intent_cancel_${order.id}`,
        },
        body: "cancellation_reason=abandoned",
      },
    );

    if (!cancelResponse.ok) {
      const errorText = await cancelResponse.text();
      // If already canceled/succeeded, treat as non-fatal
      let parsedError: { error?: { code?: string } } | null = null;
      try {
        parsedError = JSON.parse(errorText);
      } catch {
        // ignore
      }
      const alreadyDone =
        parsedError?.error?.code === "payment_intent_unexpected_state";
      if (alreadyDone) {
        return {
          released: true,
          message: "Payment intent was already in a terminal state",
          action: "payment_intent_terminal",
          paymentIntentId,
          chargeId,
          stripeStatus: null,
        };
      }
      console.error(
        "Stripe payment intent cancellation failed for rejected order",
        {
          requestId,
          orderId: order.id,
          tenantId: order.tenant_id,
          paymentIntentId,
          status: cancelResponse.status,
          error: errorText,
        },
      );
      return {
        released: false,
        message: `Stripe payment intent cancel failed: ${
          errorText || cancelResponse.status
        }`,
        action: "failed",
        paymentIntentId,
        chargeId,
        stripeStatus: null,
      };
    }

    const cancelledPaymentIntent = await cancelResponse.json() as {
      id?: string;
      status?: string;
    };

    console.info("Stripe payment intent canceled for provider-rejected order", {
      requestId,
      orderId: order.id,
      tenantId: order.tenant_id,
      paymentIntentId: cancelledPaymentIntent.id || paymentIntentId,
      paymentIntentStatus: cancelledPaymentIntent.status || null,
    });
    return {
      released: true,
      message: "Stripe payment intent canceled due to provider rejection",
      action: "payment_intent_cancelled",
      paymentIntentId: cancelledPaymentIntent.id || paymentIntentId,
      chargeId,
      stripeStatus: cancelledPaymentIntent.status || null,
    };
  }

  return {
    released: false,
    message: "No actionable Stripe payment reference found",
    action: "missing_reference",
    paymentIntentId,
    chargeId,
    stripeStatus: null,
  };
}

// Best-effort read of the renewal invoice's line-item period end — Stripe's
// authoritative cycle end. We source the cycle boundary from this instead of
// `paid_at + interval` (the app server clock), which never advances under a
// Stripe test clock and can drift from Stripe in production. Returns null on any
// failure so callers fall back to the interval calculation. NOTE: use the LINE
// period end (`lines.data[].period.end`), not the top-level `invoice.period_end`,
// which is a degenerate point value on subscription invoices.
async function resolveInvoiceLinePeriodEnd(params: {
  supabase: SupabaseClient;
  tenantId: string;
  orderId: string;
  requestId: string;
}): Promise<Date | null> {
  const { supabase, tenantId, orderId, requestId } = params;
  try {
    const { data: stripeProviderRow } = await supabase
      .from("payment_providers")
      .select("id")
      .eq("key", "stripe")
      .maybeSingle();
    if (!stripeProviderRow?.id) return null;

    const { data: txRow } = await supabase
      .from("order_payment_provider_transactions")
      .select("provider_invoice_id")
      .eq("order_id", orderId)
      .eq("tenant_id", tenantId)
      .not("provider_invoice_id", "is", null)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();
    const invoiceId = txRow?.provider_invoice_id?.trim() || "";
    if (!invoiceId) return null;

    const { data: cfg } = await supabase
      .from("tenant_payment_providers")
      .select("settings")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripeProviderRow.id)
      .eq("is_enabled", true)
      .maybeSingle();
    const secret =
      (cfg?.settings as Record<string, string>)?.secret_key?.trim() || "";
    if (!secret) return null;

    const response = await fetch(
      `https://api.stripe.com/v1/invoices/${encodeURIComponent(invoiceId)}`,
      { headers: { Authorization: `Bearer ${secret}` } },
    );
    if (!response.ok) {
      console.warn(
        "Renewal invoice period fetch failed; falling back to interval calc",
        { orderId, invoiceId, status: response.status, requestId },
      );
      return null;
    }
    const invoice = (await response.json()) as {
      lines?: { data?: Array<{ period?: { end?: number } }> };
    };
    const periodEnds = (invoice.lines?.data ?? [])
      .map((line) => line.period?.end)
      .filter((value): value is number => typeof value === "number");
    if (periodEnds.length === 0) return null;
    return dateTime.unix(Math.max(...periodEnds)).toDate();
  } catch (error) {
    console.warn(
      "Renewal invoice period lookup threw; falling back to interval calc",
      {
        orderId,
        requestId,
        error: error instanceof Error ? error.message : String(error),
      },
    );
    return null;
  }
}

export async function syncLifecycleDatesForPaymentCollectedOrder(params: {
  supabase: SupabaseClient;
  order: OrderForStripeCapture;
  requestId: string;
}): Promise<PaymentCollectedScheduleSyncResult> {
  const { supabase, order, requestId } = params;

  if (!order.subscription_id) {
    return {
      synced: false,
      message: "Order is missing subscription reference",
      orderExpirationAt: null,
      planRenewalAt: null,
    };
  }

  if (!order.product_id) {
    return {
      synced: false,
      message: "Order is missing product reference",
      orderExpirationAt: null,
      planRenewalAt: null,
    };
  }

  const { data: product, error: productError } = await supabase
    .from("products")
    .select(
      "id, subscription_interval, subscription_interval_count, subscription_renewal_lead_days",
    )
    .eq("id", order.product_id)
    .eq("tenant_id", order.tenant_id)
    .maybeSingle();

  if (productError || !product) {
    return {
      synced: false,
      message: `Failed to fetch product renewal cycle: ${
        productError?.message || "product_not_found"
      }`,
      orderExpirationAt: null,
      planRenewalAt: null,
    };
  }

  const typedProduct = product as SubscriptionRenewalProductConfig;
  if (!typedProduct.subscription_interval) {
    return {
      synced: false,
      message: "Product has no subscription interval configured",
      orderExpirationAt: null,
      planRenewalAt: null,
    };
  }

  const calculationAnchor = parseIsoDate(order.paid_at) ||
    parseIsoDate(order.created_at) ||
    dateTime().toDate();
  const intervalCount =
    typeof typedProduct.subscription_interval_count === "number" &&
      typedProduct.subscription_interval_count > 0
      ? typedProduct.subscription_interval_count
      : 1;

  // Prefer Stripe's actual invoice line-period end (authoritative, test-clock
  // correct); fall back to the server-anchored interval calc only if the invoice
  // period is unavailable. Computed here (before the sync gates) so the order's
  // expiration, the already-synced check, and the plan write all use one
  // consistent, Stripe-sourced value.
  const stripeInvoicePeriodEnd = await resolveInvoiceLinePeriodEnd({
    supabase,
    tenantId: order.tenant_id,
    orderId: order.id,
    requestId,
  });

  const expirationDate = stripeInvoicePeriodEnd ??
    addSubscriptionIntervalToDate(
      calculationAnchor,
      typedProduct.subscription_interval,
      intervalCount,
    );

  if (!expirationDate) {
    return {
      synced: false,
      message:
        `Unsupported subscription interval '${typedProduct.subscription_interval}'`,
      orderExpirationAt: null,
      planRenewalAt: null,
    };
  }

  const renewalLeadDays = Math.max(
    0,
    typeof typedProduct.subscription_renewal_lead_days === "number"
      ? typedProduct.subscription_renewal_lead_days
      : 0,
  );
  const renewalDate = dateTime(expirationDate).toDate();
  renewalDate.setUTCDate(renewalDate.getUTCDate() - renewalLeadDays);
  const now = dateTime().toDate();
  const effectiveRenewalDate = renewalDate.getTime() > now.getTime()
    ? renewalDate
    : now;

  const calculatedOrderExpirationAt = expirationDate.toISOString();
  const calculatedPlanRenewalAt = effectiveRenewalDate.toISOString();

  let orderExpirationSynced = false;
  if (
    !areDatesEqualBySecond(
      order.renewal_at || null,
      calculatedOrderExpirationAt,
    )
  ) {
    const { error: orderExpirationUpdateError } = await supabase
      .from("orders")
      .update({
        renewal_at: calculatedOrderExpirationAt,
      })
      .eq("id", order.id)
      .eq("tenant_id", order.tenant_id);

    if (orderExpirationUpdateError) {
      return {
        synced: false,
        message:
          `Failed to update order expiration schedule: ${orderExpirationUpdateError.message}`,
        orderExpirationAt: calculatedOrderExpirationAt,
        planRenewalAt: calculatedPlanRenewalAt,
      };
    }

    orderExpirationSynced = true;
  }

  const { data: mostRecentOrder, error: mostRecentOrderError } = await supabase
    .from("orders")
    .select("id")
    .eq("tenant_id", order.tenant_id)
    .eq("subscription_id", order.subscription_id)
    .order("created_at", { ascending: false })
    .order("id", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (mostRecentOrderError) {
    return {
      synced: false,
      message:
        `Failed to determine most recent order for subscription: ${mostRecentOrderError.message}`,
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  if (mostRecentOrder?.id && mostRecentOrder.id !== order.id) {
    return {
      synced: orderExpirationSynced,
      message:
        "Order expiration updated; skipped plan/Stripe sync because this is not the most recent order for the plan",
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const { data: plan, error: planError } = await supabase
    .from("subscriptions")
    .select("id, current_period_end_at, expires_at")
    .eq("id", order.subscription_id)
    .eq("tenant_id", order.tenant_id)
    .maybeSingle();

  if (planError || !plan) {
    return {
      synced: false,
      message: `Failed to fetch linked plan: ${
        planError?.message || "plan_not_found"
      }`,
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const isAlreadySynced = areDatesEqualBySecond(
    orderExpirationSynced
      ? calculatedOrderExpirationAt
      : order.renewal_at || null,
    calculatedOrderExpirationAt,
  ) &&
    areDatesEqualBySecond(
      plan.current_period_end_at,
      calculatedPlanRenewalAt,
    ) &&
    areDatesEqualBySecond(plan.expires_at, calculatedOrderExpirationAt);

  if (isAlreadySynced) {
    return {
      synced: true,
      message: "Order and plan schedule are already in sync",
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const { data: stripeProvider, error: stripeProviderError } = await supabase
    .from("payment_providers")
    .select("id")
    .eq("key", "stripe")
    .maybeSingle();

  if (stripeProviderError || !stripeProvider?.id) {
    return {
      synced: false,
      message: `Stripe payment provider is unavailable: ${
        stripeProviderError?.message || "not_configured"
      }`,
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const providerValidation = await validateStripePaymentProviderForOrder({
    supabase,
    tenantId: order.tenant_id,
    orderId: order.id,
    stripeProviderId: stripeProvider.id,
  });
  if (!providerValidation.valid) {
    return {
      synced: false,
      message: providerValidation.message ||
        "Order payment provider is not Stripe",
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const { data: providerConfig, error: providerConfigError } = await supabase
    .from("tenant_payment_providers")
    .select("settings")
    .eq("tenant_id", order.tenant_id)
    .eq("payment_provider_id", stripeProvider.id)
    .eq("is_enabled", true)
    .maybeSingle();

  if (providerConfigError || !providerConfig) {
    return {
      synced: false,
      message: `Stripe tenant configuration is unavailable: ${
        providerConfigError?.message || "not_configured"
      }`,
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const stripeSecretKey =
    (providerConfig.settings as Record<string, string>)?.secret_key?.trim() ||
    "";
  if (!stripeSecretKey) {
    return {
      synced: false,
      message: "Stripe secret key is missing for tenant",
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const { data: subscriptionLink, error: subscriptionLinkError } =
    await supabase
      .from("subscription_payment_provider_links")
      .select("provider_subscription_id")
      .eq("tenant_id", order.tenant_id)
      .eq("subscription_id", order.subscription_id)
      .eq("payment_provider_id", stripeProvider.id)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

  if (subscriptionLinkError) {
    return {
      synced: false,
      message:
        `Failed to fetch Stripe subscription link for plan: ${subscriptionLinkError.message}`,
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  const stripeSubscriptionId =
    subscriptionLink?.provider_subscription_id?.trim() || "";
  if (!stripeSubscriptionId) {
    return {
      synced: false,
      message: "Plan is missing Stripe subscription reference",
      code: "missing_subscription_link",
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: calculatedPlanRenewalAt,
    };
  }

  let syncedPlanRenewalAt = calculatedPlanRenewalAt;
  const targetRenewalUnix = Math.floor(
    dateTime(calculatedPlanRenewalAt).valueOf() / 1000,
  );
  const nowUnix = Math.floor(Date.now() / 1000);
  const stripeParams = new URLSearchParams();
  stripeParams.append("proration_behavior", "none");
  if (targetRenewalUnix <= nowUnix) {
    stripeParams.append("trial_end", "now");
  } else {
    stripeParams.append("trial_end", String(targetRenewalUnix));
  }
  stripeParams.append(
    "metadata[allia_lifecycle_source]",
    "order_lifecycle_payment_collected",
  );
  stripeParams.append(
    "metadata[allia_order_expiration_at]",
    calculatedOrderExpirationAt,
  );
  stripeParams.append(
    "metadata[allia_plan_renewal_at]",
    calculatedPlanRenewalAt,
  );
  stripeParams.append(
    "metadata[allia_plan_expires_at]",
    calculatedOrderExpirationAt,
  );
  stripeParams.append(
    "metadata[allia_schedule_synced_at]",
    dateTime().toISOString(),
  );

  const stripeUpdateResponse = await fetch(
    `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
        "Idempotency-Key": `allia_provider_rejected_cancel_${order.id}`,
      },
      body: stripeParams.toString(),
    },
  );

  if (!stripeUpdateResponse.ok) {
    const errorText = await stripeUpdateResponse.text();
    let stripeErrorMessage = errorText;
    let parsedErrorParam: string | null = null;
    try {
      const parsedError = JSON.parse(errorText) as {
        error?: { message?: string; param?: string };
      };
      const parsedMessage = parsedError?.error?.message?.trim();
      if (parsedMessage) {
        stripeErrorMessage = parsedMessage;
      }
      parsedErrorParam = parsedError?.error?.param ?? null;
    } catch {
      // Keep fallback message from raw response.
    }

    // If Stripe rejected trial_end because it's in the past (e.g. test clock
    // frozen ahead of our calculated date), retry with trial_end=now so the
    // subscription renews immediately rather than blocking order advancement.
    if (
      stripeUpdateResponse.status === 400 &&
      parsedErrorParam === "trial_end"
    ) {
      console.warn(
        "Stripe trial_end rejected as past date; retrying with trial_end=now",
        {
          requestId,
          orderId: order.id,
          stripeSubscriptionId,
          targetRenewalUnix,
        },
      );
      const retryParams = new URLSearchParams(stripeParams.toString());
      retryParams.set("trial_end", "now");
      const retryResponse = await fetch(
        `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${stripeSecretKey}`,
            "Content-Type": "application/x-www-form-urlencoded",
            "Idempotency-Key": `allia_provider_rejected_cancel_${order.id}_now`,
          },
          body: retryParams.toString(),
        },
      );
      if (retryResponse.ok) {
        const retrySub = (await retryResponse.json()) as {
          current_period_end?: number;
        };
        if (typeof retrySub.current_period_end === "number") {
          syncedPlanRenewalAt = dateTime(retrySub.current_period_end * 1000)
            .toDate()
            .toISOString();
        }
        // Fall through to local DB update below
      } else {
        const retryErrorText = await retryResponse.text();
        console.warn("Stripe subscription schedule sync retry also failed", {
          requestId,
          orderId: order.id,
          stripeSubscriptionId,
          status: retryResponse.status,
          error: retryErrorText,
        });
        // Stripe billing date was NOT moved but we still persist the locally
        // computed renewal date below — the DB can now disagree with Stripe.
        // Flag for ops (structured marker feeds edge-function log monitoring).
        console.error("subscription_stripe_sync_drift", {
          reason: "trial_end_retry_failed",
          requestId,
          orderId: order.id,
          subscriptionId: order.subscription_id,
          stripeSubscriptionId,
          localRenewalAt: syncedPlanRenewalAt,
        });
        // Fall through to local DB update — don't block order advancement
      }
    } else {
      console.warn("Stripe subscription schedule sync failed", {
        requestId,
        orderId: order.id,
        subscriptionId: order.subscription_id,
        stripeSubscriptionId,
        status: stripeUpdateResponse.status,
        error: errorText,
      });
      // Don't return synced: false — Stripe schedule sync is best-effort.
      // The order should still advance so fulfillment is not blocked. But the
      // DB renewal date may now disagree with Stripe — flag for ops.
      console.error("subscription_stripe_sync_drift", {
        reason: "schedule_sync_failed",
        requestId,
        orderId: order.id,
        subscriptionId: order.subscription_id,
        stripeSubscriptionId,
        status: stripeUpdateResponse.status,
        localRenewalAt: syncedPlanRenewalAt,
      });
    }
  }

  if (stripeUpdateResponse.ok) {
    const stripeSubscription = (await stripeUpdateResponse.json()) as {
      current_period_end?: number;
    };
    if (typeof stripeSubscription.current_period_end === "number") {
      syncedPlanRenewalAt = dateTime(
        stripeSubscription.current_period_end * 1000,
      )
        .toDate()
        .toISOString();
    }
  }

  const { error: planUpdateError } = await supabase
    .from("subscriptions")
    .update({
      current_period_end_at: syncedPlanRenewalAt,
      expires_at: calculatedOrderExpirationAt,
    })
    .eq("id", order.subscription_id)
    .eq("tenant_id", order.tenant_id);

  if (planUpdateError) {
    return {
      synced: false,
      message:
        `Failed to update plan lifecycle schedule: ${planUpdateError.message}`,
      orderExpirationAt: calculatedOrderExpirationAt,
      planRenewalAt: syncedPlanRenewalAt,
    };
  }

  return {
    synced: true,
    message:
      `Updated order expiration to ${calculatedOrderExpirationAt} and plan renewal to ${syncedPlanRenewalAt}`,
    orderExpirationAt: calculatedOrderExpirationAt,
    planRenewalAt: syncedPlanRenewalAt,
  };
}

/**
 * Create the Stripe subscription for a just-captured embedded-checkout order.
 *
 * The embedded PaymentIntent flow (PP-566) does not create a Stripe Subscription
 * up front (unlike the hosted Checkout Session success-handler). We defer it to
 * payment capture: once payment is captured at `payment_pending` (after clinical
 * approval), if the order is for a subscription product and has no Stripe
 * subscription link yet, create the Stripe subscription + local provider link
 * here. The embedded checkout may already have created a pending local
 * `subscriptions` row so My Plan can show the in-progress plan before payment
 * capture; when that exists, this helper attaches Stripe to that same row.
 *
 * Best-effort and idempotent: failures are logged and never block the capture
 * result; the subscription create is keyed by order id so re-runs don't duplicate.
 * No-op for one-time products and for orders that already have a Stripe-linked
 * subscription.
 */
export async function ensureSubscriptionForCapturedOrder(
  supabase: SupabaseClient,
  orderId: string,
  tenantId: string,
  requestId: string,
): Promise<
  {
    created: boolean;
    subscriptionId: string | null;
    // Stripe subscription id (sub_...), distinct from the local subscriptions
    // row id above; RTDH identity links must use this one.
    stripeSubscriptionId?: string | null;
    message: string;
  }
> {
  // Load the order with the product fields needed to build the subscription.
  const { data: order, error: orderError } = await supabase
    .from("orders")
    .select(
      "id, tenant_id, patient_id, product_id, subscription_id, coupon_code, products!inner ( id, name, description, image_url, price_cents, payment_type, subscription_interval, subscription_interval_count )",
    )
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (orderError || !order) {
    return {
      created: false,
      subscriptionId: null,
      message: `ensureSubscriptionForCapturedOrder: order load failed: ${
        orderError?.message || "not found"
      }`,
    };
  }

  const product = Array.isArray(order.products)
    ? order.products[0]
    : order.products;

  if (!product || product.payment_type !== "subscription") {
    return {
      created: false,
      subscriptionId: order.subscription_id ?? null,
      message: "Not a subscription product; no subscription needed",
    };
  }

  // Resolve Stripe provider + secret key for this tenant.
  const { data: stripeProvider } = await supabase
    .from("payment_providers")
    .select("id")
    .eq("key", "stripe")
    .maybeSingle();
  if (!stripeProvider?.id) {
    return {
      created: false,
      subscriptionId: null,
      message: "Stripe payment provider unavailable",
    };
  }
  const stripePaymentProviderId = stripeProvider.id as string;

  if (order.subscription_id) {
    const { data: existingSubscriptionLink } = await supabase
      .from("subscription_payment_provider_links")
      .select("provider_subscription_id")
      .eq("tenant_id", tenantId)
      .eq("subscription_id", order.subscription_id)
      .eq("payment_provider_id", stripePaymentProviderId)
      .maybeSingle();

    if (
      typeof existingSubscriptionLink?.provider_subscription_id === "string" &&
      existingSubscriptionLink.provider_subscription_id.trim()
    ) {
      // The Stripe subscription already exists (link table is populated), but an
      // earlier capture may have left subscriptions.stripe_subscription_id null
      // — back-fill it (only-if-null) so the renewal path can resolve it. This
      // makes re-runs idempotent and self-repairs orders captured before this fix.
      await supabase
        .from("subscriptions")
        .update({
          stripe_subscription_id:
            existingSubscriptionLink.provider_subscription_id,
        })
        .eq("id", order.subscription_id)
        .eq("tenant_id", tenantId)
        .is("stripe_subscription_id", null);

      return {
        created: false,
        subscriptionId: order.subscription_id,
        message: "Order already has a Stripe-linked subscription",
      };
    }
  }

  const { data: providerLink } = await supabase
    .from("tenant_payment_providers")
    .select("settings")
    .eq("tenant_id", tenantId)
    .eq("payment_provider_id", stripePaymentProviderId)
    .eq("is_enabled", true)
    .maybeSingle();
  const stripeSecretKey =
    (providerLink?.settings as Record<string, string>)?.secret_key?.trim() ||
    "";
  if (!stripeSecretKey) {
    return {
      created: false,
      subscriptionId: null,
      message: "Stripe secret key missing for tenant",
    };
  }

  // Resolve the Stripe customer id + payment method from the order's transaction
  // and the captured PaymentIntent.
  const { data: txns } = await supabase
    .from("order_payment_provider_transactions")
    .select("provider_payment_intent_id, provider_customer_id")
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .eq("payment_provider_id", stripePaymentProviderId)
    .order("created_at", { ascending: false })
    .limit(5);

  const txnRows = (txns || []) as Array<{
    provider_payment_intent_id: string | null;
    provider_customer_id: string | null;
  }>;
  const customerId =
    txnRows.find((t) => t.provider_customer_id)?.provider_customer_id || null;
  const paymentIntentId = txnRows.find((t) => t.provider_payment_intent_id)
    ?.provider_payment_intent_id || null;

  if (!customerId) {
    return {
      created: false,
      subscriptionId: null,
      message:
        "No Stripe customer on order; cannot create subscription (Phase A must run first)",
    };
  }

  // Prefer the payment method used on the captured PI; fall back to the
  // customer's default payment method.
  let paymentMethodId: string | null = null;
  if (paymentIntentId) {
    const pi = await fetchStripePaymentIntent(paymentIntentId, stripeSecretKey)
      .catch(() => null);
    paymentMethodId = getStripeObjectId(pi?.payment_method || null);
  }
  if (!paymentMethodId) {
    const cust = await fetch(
      `https://api.stripe.com/v1/customers/${customerId}?expand[]=invoice_settings.default_payment_method`,
      { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
    ).then((r) => (r.ok ? r.json() : null)).catch(() => null);
    paymentMethodId = getStripeObjectId(
      cust?.invoice_settings?.default_payment_method || null,
    );
  }

  // Resolve the order coupon to a Stripe coupon/promotion for the subscription.
  // Renewal-vs-first-cycle discounting is governed by the Stripe Coupon.duration
  // (managed in Stripe), not here.
  let subscriptionDiscount: StripeSubscriptionDiscount | null = null;
  const couponCode = typeof order.coupon_code === "string"
    ? order.coupon_code.trim()
    : "";
  if (couponCode) {
    try {
      const promoRes = await fetch(
        `https://api.stripe.com/v1/promotion_codes?code=${
          encodeURIComponent(couponCode)
        }&active=true&limit=1`,
        { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
      );
      if (promoRes.ok) {
        const promo = (await promoRes.json())?.data?.[0];
        if (promo?.id) {
          subscriptionDiscount = {
            couponId: null,
            promotionCodeId: promo.id,
          };
        }
      }
      if (!subscriptionDiscount) {
        // Fall back to treating the code as a coupon id directly.
        const couponRes = await fetch(
          `https://api.stripe.com/v1/coupons/${encodeURIComponent(couponCode)}`,
          { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
        );
        if (couponRes.ok) {
          const coupon = await couponRes.json();
          if (coupon?.id && coupon?.valid !== false) {
            subscriptionDiscount = {
              couponId: coupon.id,
              promotionCodeId: null,
            };
          }
        }
      }
    } catch (_e) {
      console.warn("ensureSubscriptionForCapturedOrder: coupon lookup failed", {
        requestId,
        orderId,
        couponCode,
      });
    }
  }

  // Look up the buyer email for subscription metadata (best-effort).
  const { data: patientRow } = await supabase
    .from("patients")
    .select("email")
    .eq("id", order.patient_id)
    .eq("tenant_id", tenantId)
    .maybeSingle();
  const customerEmail = typeof patientRow?.email === "string"
    ? patientRow.email
    : null;

  // Create the Stripe subscription (keyed by order id for idempotency).
  let stripeSubscriptionId: string | null = null;
  let renewalAt: string | null = null;
  try {
    const createdSubscription = await createSendInvoiceStripeSubscription({
      customerId,
      tenantId,
      product: {
        id: product.id,
        name: product.name,
        description: product.description,
        image_url: product.image_url,
        price_cents: product.price_cents,
        subscription_interval: product.subscription_interval,
        subscription_interval_count: product.subscription_interval_count,
      },
      patientId: order.patient_id,
      customerEmail,
      stripeSecretKey,
      idempotencyScope: orderId,
      paymentMethodId,
      subscriptionDiscount,
    });
    stripeSubscriptionId = createdSubscription.id;
    renewalAt = typeof createdSubscription.current_period_end === "number"
      ? dateTime.unix(createdSubscription.current_period_end).toISOString()
      : null;
  } catch (subError) {
    return {
      created: false,
      subscriptionId: null,
      message: `Stripe subscription creation failed: ${
        subError instanceof Error ? subError.message : String(subError)
      }`,
    };
  }

  // Create/find the local subscriptions row + provider link. Embedded checkout
  // creates a pending local subscription before payment so My Plan can show the
  // order immediately; attach Stripe to that row when present.
  const localSubscriptionId = order.subscription_id
    ? await upsertSubscriptionProviderLink({
      supabase,
      tenantId,
      subscriptionId: order.subscription_id,
      paymentProviderId: stripePaymentProviderId,
      providerSubscriptionId: stripeSubscriptionId,
    })
    : await ensureOrderSubscription({
      supabase,
      tenantId,
      patientId: order.patient_id,
      productId: product.id,
      stripePaymentProviderId,
      providerSubscriptionId: stripeSubscriptionId,
      startedAt: dateTime().toISOString(),
      renewalAt,
      expiresAt: renewalAt,
    });

  if (!localSubscriptionId) {
    return {
      created: false,
      subscriptionId: null,
      message:
        "Stripe subscription created but local subscriptions row could not be resolved",
    };
  }

  // Persist the Stripe subscription id + renewal window onto the canonical
  // subscriptions row. The embedded flow previously only wrote the link table +
  // transaction, leaving subscriptions.stripe_subscription_id null — but the
  // renewal path (rtdh-webhook validateReferences) resolves the subscription by
  // that column, and renewal_at/expires_at drive the "Renews/Expires" dates in
  // My Plan. Guard stripe_subscription_id as only-if-null so we never clobber an
  // id linked elsewhere (e.g. the hosted flow or a renewal back-fill).
  await supabase
    .from("subscriptions")
    .update({
      current_period_end_at: renewalAt,
      expires_at: renewalAt,
    })
    .eq("id", localSubscriptionId)
    .eq("tenant_id", tenantId);

  await supabase
    .from("subscriptions")
    .update({ stripe_subscription_id: stripeSubscriptionId })
    .eq("id", localSubscriptionId)
    .eq("tenant_id", tenantId)
    .is("stripe_subscription_id", null);

  // Link the subscription + renewal date onto the order, and record the
  // provider_subscription_id on the transaction for downstream resolution.
  await supabase
    .from("orders")
    .update({
      subscription_id: localSubscriptionId,
      renewal_at: renewalAt,
    })
    .eq("id", orderId)
    .eq("tenant_id", tenantId);

  await supabase
    .from("order_payment_provider_transactions")
    .update({ provider_subscription_id: stripeSubscriptionId })
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .eq("payment_provider_id", stripePaymentProviderId);

  return {
    created: true,
    subscriptionId: localSubscriptionId,
    stripeSubscriptionId,
    message:
      `Created Stripe subscription ${stripeSubscriptionId} and linked local subscription ${localSubscriptionId}`,
  };
}
