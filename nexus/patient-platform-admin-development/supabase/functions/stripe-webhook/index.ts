// Stripe Webhook Handler Edge Function
// Processes Stripe webhook events for payment lifecycle management

import { createClient, SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { dateTime } from "../_shared/dayjs.ts";
import { trackFriendbuyPurchaseForOrder } from "../_shared/friendbuy.ts";
import {
  cancelNotification,
  getOneSignalConfig,
  scheduleNotification,
} from "../_shared/onesignal.ts";
import { calculateOccurrences } from "../_shared/reminder-schedule.ts";
import { generateOrderNumber, verifyStripeSignature } from "./helpers.ts";
import { handleCustomerUpdated } from "./customer-updated.ts";
import {
  dispatchStripeWebhookEvent,
  StripeWebhookEvent,
} from "./stripe-events-helper.ts";
import {
  getCheckoutSessionIdFromInvoice,
  getSubscriptionIdFromInvoice,
} from "./utils.ts";

const RETRYABLE_EVENTS_WITHOUT_TENANT_CONTEXT = new Set([
  "invoice.created",
  "invoice.finalized",
  "payment_intent.succeeded",
  "payment_intent.amount_capturable_updated",
  "payment_intent.cancelled",
]);

// Use a simple type alias for the client
// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

async function trackFriendbuyPurchaseAfterPaymentCollected(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  orderId: string;
  requestId: string;
  source: string;
}) {
  await trackFriendbuyPurchaseForOrder(params.supabase, {
    tenantId: params.tenantId,
    orderId: params.orderId,
    requestId: params.requestId,
  }).catch((error) => {
    console.warn("Friendbuy purchase tracking failed after Stripe collection", {
      requestId: params.requestId,
      orderId: params.orderId,
      tenantId: params.tenantId,
      source: params.source,
      error: error instanceof Error ? error.message : String(error),
    });
  });
}

class RetryableWebhookError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "RetryableWebhookError";
  }
}

class OrderPaymentProviderMismatchError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "OrderPaymentProviderMismatchError";
  }
}

function getCheckoutSessionIdFromPaymentIntentObject(paymentIntent: {
  metadata?: Record<string, string>;
  payment_details?: {
    order_reference?: string | null;
  } | null;
}): string | null {
  const metadataCheckoutSessionId =
    paymentIntent.metadata?.checkout_session_id?.trim() || "";
  if (metadataCheckoutSessionId) {
    return metadataCheckoutSessionId;
  }

  const orderReference =
    paymentIntent.payment_details?.order_reference?.trim() || "";
  if (orderReference.startsWith("cs_")) {
    return orderReference;
  }

  return null;
}

type UpsertSubscriptionProviderLinkParams = {
  supabase: SupabaseAdminClient;
  tenantId: string;
  subscriptionId: string | null;
  paymentProviderId: string;
  providerSubscriptionId?: string | null;
  providerCheckoutSessionId?: string | null;
  requestId: string;
};

type UpsertSubscriptionProviderLinkResult = {
  linkedSubscriptionId: string | null;
  linkSaved: boolean;
};

async function upsertSubscriptionProviderLink({
  supabase,
  tenantId,
  subscriptionId,
  paymentProviderId,
  providerSubscriptionId,
  providerCheckoutSessionId,
  requestId,
}: UpsertSubscriptionProviderLinkParams): Promise<UpsertSubscriptionProviderLinkResult> {
  if (!subscriptionId) {
    return { linkedSubscriptionId: null, linkSaved: false };
  }
  if (!providerSubscriptionId && !providerCheckoutSessionId) {
    return { linkedSubscriptionId: subscriptionId, linkSaved: false };
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
    return { linkedSubscriptionId: subscriptionId, linkSaved: true };
  }

  console.warn("Failed to upsert subscription payment provider link", {
    requestId,
    tenantId,
    subscriptionId,
    providerSubscriptionId: providerSubscriptionId || null,
    providerCheckoutSessionId: providerCheckoutSessionId || null,
    error: error.message,
  });

  if (providerSubscriptionId) {
    const {
      data: existingLinkBySubscriptionId,
      error: existingLinkBySubscriptionIdError,
    } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", paymentProviderId)
      .eq("provider_subscription_id", providerSubscriptionId)
      .maybeSingle();

    if (existingLinkBySubscriptionIdError) {
      console.warn(
        "Failed to resolve existing subscription payment link by provider_subscription_id after upsert failure",
        {
          requestId,
          tenantId,
          subscriptionId,
          providerSubscriptionId,
          error: existingLinkBySubscriptionIdError.message,
        },
      );
    } else if (existingLinkBySubscriptionId?.subscription_id) {
      return {
        linkedSubscriptionId: existingLinkBySubscriptionId.subscription_id,
        linkSaved: false,
      };
    }
  }

  if (providerCheckoutSessionId) {
    const {
      data: existingLinksByCheckoutSessionId,
      error: existingLinksByCheckoutSessionIdError,
    } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", paymentProviderId)
      .eq("provider_checkout_session_id", providerCheckoutSessionId)
      .order("created_at", { ascending: false })
      .limit(1);

    if (existingLinksByCheckoutSessionIdError) {
      console.warn(
        "Failed to resolve existing subscription payment link by provider_checkout_session_id after upsert failure",
        {
          requestId,
          tenantId,
          subscriptionId,
          providerCheckoutSessionId,
          error: existingLinksByCheckoutSessionIdError.message,
        },
      );
    } else if (existingLinksByCheckoutSessionId?.[0]?.subscription_id) {
      return {
        linkedSubscriptionId:
          existingLinksByCheckoutSessionId[0].subscription_id,
        linkSaved: false,
      };
    }
  }

  return { linkedSubscriptionId: null, linkSaved: false };
}

type UpsertOrderProviderTransactionParams = {
  supabase: SupabaseAdminClient;
  tenantId: string;
  orderId: string;
  paymentProviderId: string;
  requestId: string;
  subscriptionId?: string | null;
  providerPaymentIntentId?: string | null;
  providerInvoiceId?: string | null;
  providerChargeId?: string | null;
  providerSubscriptionId?: string | null;
  providerCheckoutSessionId?: string | null;
  providerCustomerId?: string | null;
  paymentStatus?: string | null;
  paidAt?: string | null;
};

async function upsertOrderPaymentProviderTransaction({
  supabase,
  tenantId,
  orderId,
  paymentProviderId,
  requestId,
  subscriptionId,
  providerPaymentIntentId,
  providerInvoiceId,
  providerChargeId,
  providerSubscriptionId,
  providerCheckoutSessionId,
  providerCustomerId,
  paymentStatus,
  paidAt,
}: UpsertOrderProviderTransactionParams): Promise<void> {
  if (
    !providerPaymentIntentId &&
    !providerInvoiceId &&
    !providerChargeId &&
    !providerSubscriptionId &&
    !providerCheckoutSessionId &&
    !paymentStatus &&
    !paidAt
  ) {
    return;
  }

  const payload: Record<string, unknown> = {
    tenant_id: tenantId,
    order_id: orderId,
    payment_provider_id: paymentProviderId,
    payment_status: paymentStatus || "unknown",
  };
  if (subscriptionId) payload.subscription_id = subscriptionId;
  if (providerPaymentIntentId) {
    payload.provider_payment_intent_id = providerPaymentIntentId;
  }
  if (providerInvoiceId) payload.provider_invoice_id = providerInvoiceId;
  if (providerChargeId) payload.provider_charge_id = providerChargeId;
  if (providerSubscriptionId) {
    payload.provider_subscription_id = providerSubscriptionId;
  }
  if (providerCheckoutSessionId) {
    payload.provider_checkout_session_id = providerCheckoutSessionId;
  }
  if (providerCustomerId) {
    payload.provider_customer_id = providerCustomerId;
  }
  if (paidAt) payload.paid_at = paidAt;

  const { data: configuredProviderRows, error: configuredProviderRowsError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select("payment_provider_id")
      .eq("tenant_id", tenantId)
      .eq("order_id", orderId)
      .limit(20);

  if (configuredProviderRowsError) {
    console.warn("Failed to validate order payment provider before upsert", {
      requestId,
      tenantId,
      orderId,
      paymentProviderId,
      error: configuredProviderRowsError.message,
    });
    throw new Error(
      `order_payment_provider_validation_failed:${configuredProviderRowsError.message}`,
    );
  }

  if ((configuredProviderRows || []).length > 0) {
    const hasExpectedProvider = (configuredProviderRows || []).some(
      (row) => row.payment_provider_id === paymentProviderId,
    );

    if (!hasExpectedProvider) {
      console.warn(
        "Order payment provider mismatch for Stripe webhook upsert",
        {
          requestId,
          tenantId,
          orderId,
          expectedPaymentProviderId: paymentProviderId,
          configuredPaymentProviderIds:
            configuredProviderRows?.map((row) => row.payment_provider_id) || [],
        },
      );
      throw new OrderPaymentProviderMismatchError(
        "Order payment provider is not Stripe",
      );
    }
  }

  const lookupStrategies: Array<{
    label: string;
    apply: (query: any) => any;
    enabled: boolean;
  }> = [
    {
      label: "provider_payment_intent_id",
      enabled: Boolean(providerPaymentIntentId),
      apply: (query) =>
        query.eq("provider_payment_intent_id", providerPaymentIntentId),
    },
    {
      label: "provider_invoice_id",
      enabled: Boolean(providerInvoiceId),
      apply: (query) => query.eq("provider_invoice_id", providerInvoiceId),
    },
    {
      label: "provider_charge_id",
      enabled: Boolean(providerChargeId),
      apply: (query) => query.eq("provider_charge_id", providerChargeId),
    },
    {
      label: "provider_checkout_session_id_without_invoice",
      enabled: Boolean(providerCheckoutSessionId),
      apply: (query) =>
        query
          .eq("provider_checkout_session_id", providerCheckoutSessionId)
          .is("provider_invoice_id", null),
    },
    {
      label: "provider_subscription_id_with_invoice",
      enabled: Boolean(providerSubscriptionId) && Boolean(providerInvoiceId),
      apply: (query) =>
        query
          .eq("provider_subscription_id", providerSubscriptionId)
          .eq("provider_invoice_id", providerInvoiceId),
    },
    {
      label: "payment_status",
      enabled: Boolean(payload.payment_status),
      apply: (query) => query.eq("payment_status", payload.payment_status),
    },
  ];

  let existingTransactionId: string | null = null;
  for (const strategy of lookupStrategies) {
    if (!strategy.enabled) continue;

    const baseQuery = supabase
      .from("order_payment_provider_transactions")
      .select("id")
      .eq("order_id", orderId)
      .eq("payment_provider_id", paymentProviderId)
      .order("created_at", { ascending: false })
      .limit(1);

    const { data: existingTransactions, error: lookupError } =
      await strategy.apply(baseQuery);

    if (lookupError) {
      console.warn(
        "Failed to lookup order payment provider transaction before upsert",
        {
          requestId,
          tenantId,
          orderId,
          strategy: strategy.label,
          providerPaymentIntentId: providerPaymentIntentId || null,
          providerInvoiceId: providerInvoiceId || null,
          providerChargeId: providerChargeId || null,
          providerSubscriptionId: providerSubscriptionId || null,
          providerCheckoutSessionId: providerCheckoutSessionId || null,
          paymentStatus: payload.payment_status || null,
          error: lookupError.message,
        },
      );
      throw new Error(
        `order_payment_provider_transactions lookup failed: ${lookupError.message}`,
      );
    }

    existingTransactionId = existingTransactions?.[0]?.id || null;
    if (existingTransactionId) break;
  }

  const attemptedOperation = existingTransactionId ? "update" : "insert";
  const { error } = existingTransactionId
    ? await supabase
        .from("order_payment_provider_transactions")
        .update(payload)
        .eq("id", existingTransactionId)
    : await supabase
        .from("order_payment_provider_transactions")
        .insert(payload);

  if (error) {
    console.warn("Failed to upsert order payment provider transaction", {
      requestId,
      tenantId,
      orderId,
      attemptedOperation,
      providerPaymentIntentId: providerPaymentIntentId || null,
      providerInvoiceId: providerInvoiceId || null,
      providerChargeId: providerChargeId || null,
      providerSubscriptionId: providerSubscriptionId || null,
      providerCheckoutSessionId: providerCheckoutSessionId || null,
      paymentStatus: paymentStatus || "unknown",
      error: error.message,
    });
    throw new Error(
      `order_payment_provider_transactions upsert failed: ${error.message}`,
    );
  }
}

type EnsureOrderSubscriptionParams = {
  supabase: SupabaseAdminClient;
  tenantId: string;
  patientId: string;
  productId?: string | null;
  stripePaymentProviderId: string;
  requestId: string;
  providerSubscriptionId?: string | null;
  providerCheckoutSessionId?: string | null;
  startedAt?: string | null;
  renewalAt?: string | null;
  expiresAt?: string | null;
};

async function ensureOrderSubscription({
  supabase,
  tenantId,
  patientId,
  productId,
  stripePaymentProviderId,
  requestId,
  providerSubscriptionId,
  providerCheckoutSessionId,
  startedAt,
  renewalAt,
  expiresAt,
}: EnsureOrderSubscriptionParams): Promise<string | null> {
  let subscriptionId: string | null = null;
  let createdSubscriptionId: string | null = null;

  if (providerSubscriptionId) {
    const { data: linkBySubscriptionId, error: linkBySubscriptionIdError } =
      await supabase
        .from("subscription_payment_provider_links")
        .select("subscription_id")
        .eq("tenant_id", tenantId)
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_subscription_id", providerSubscriptionId)
        .maybeSingle();

    if (linkBySubscriptionIdError) {
      console.warn(
        "Failed to lookup subscription link by provider_subscription_id",
        {
          requestId,
          tenantId,
          providerSubscriptionId,
          error: linkBySubscriptionIdError.message,
        },
      );
    } else {
      subscriptionId = linkBySubscriptionId?.subscription_id || null;
    }
  }

  if (!subscriptionId && providerCheckoutSessionId) {
    const {
      data: linkByCheckoutSessionId,
      error: linkByCheckoutSessionIdError,
    } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", providerCheckoutSessionId)
      .maybeSingle();

    if (linkByCheckoutSessionIdError) {
      console.warn(
        "Failed to lookup subscription link by provider_checkout_session_id",
        {
          requestId,
          tenantId,
          providerCheckoutSessionId,
          error: linkByCheckoutSessionIdError.message,
        },
      );
    } else {
      subscriptionId = linkByCheckoutSessionId?.subscription_id || null;
    }
  }

  if (!subscriptionId) {
    let subscriptionQuery = supabase
      .from("subscriptions")
      .select("id")
      .eq("tenant_id", tenantId)
      .eq("patient_id", patientId);

    if (productId) {
      subscriptionQuery = subscriptionQuery.eq("product_id", productId);
    }

    const { data: subscriptionsByPatient, error: subscriptionsByPatientError } =
      await subscriptionQuery
        .order("created_at", { ascending: false })
        .limit(2);

    if (subscriptionsByPatientError) {
      console.warn("Failed to lookup subscription by patient/product", {
        requestId,
        tenantId,
        patientId,
        productId: productId || null,
        error: subscriptionsByPatientError.message,
      });
    } else if (subscriptionsByPatient && subscriptionsByPatient.length > 0) {
      if (subscriptionsByPatient.length > 1) {
        console.warn(
          "Multiple subscriptions matched patient/product; using most recent",
          {
            requestId,
            tenantId,
            patientId,
            productId: productId || null,
            matchedSubscriptionIds: subscriptionsByPatient.map((row) => row.id),
          },
        );
      }
      subscriptionId = subscriptionsByPatient[0].id;
    }
  }

  if (
    !subscriptionId &&
    (providerSubscriptionId || providerCheckoutSessionId)
  ) {
    const nowIso = dateTime().toISOString();
    const subscriptionInsertPayload: Record<string, unknown> = {
      tenant_id: tenantId,
      patient_id: patientId,
      product_id: productId || null,
      status: "pending_validation",
      started_at: startedAt || nowIso,
      current_period_end_at: renewalAt || null,
      expires_at: expiresAt || renewalAt || null,
    };

    const { data: createdSubscription, error: createdSubscriptionError } =
      await supabase
        .from("subscriptions")
        .insert(subscriptionInsertPayload)
        .select("id")
        .single();

    if (createdSubscriptionError) {
      console.error("Failed to create subscription for order linkage", {
        requestId,
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

  if (!subscriptionId) {
    return null;
  }

  const { linkedSubscriptionId } = await upsertSubscriptionProviderLink({
    supabase,
    tenantId,
    subscriptionId,
    paymentProviderId: stripePaymentProviderId,
    providerSubscriptionId: providerSubscriptionId || null,
    providerCheckoutSessionId: providerCheckoutSessionId || null,
    requestId,
  });

  if (!linkedSubscriptionId) {
    return null;
  }

  if (createdSubscriptionId && linkedSubscriptionId !== createdSubscriptionId) {
    const { error: cleanupError } = await supabase
      .from("subscriptions")
      .delete()
      .eq("id", createdSubscriptionId)
      .eq("tenant_id", tenantId)
      .eq("patient_id", patientId);

    if (cleanupError) {
      console.warn("Failed to cleanup race-created duplicate subscription", {
        requestId,
        tenantId,
        patientId,
        createdSubscriptionId,
        linkedSubscriptionId,
        error: cleanupError.message,
      });
    } else {
      console.info("Cleaned up race-created duplicate subscription", {
        requestId,
        tenantId,
        patientId,
        createdSubscriptionId,
        linkedSubscriptionId,
      });
    }
  }

  return linkedSubscriptionId;
}

async function markSubscriptionAsActiveIfPendingValidation(
  supabase: SupabaseAdminClient,
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
      "Failed to resolve linked subscription while marking plan active",
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
    console.warn("Failed to mark linked subscription as active after payment", {
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

async function markSubscriptionAsActiveForOrder(
  supabase: SupabaseAdminClient,
  tenantId: string,
  orderId: string,
  requestId: string,
  source: string,
): Promise<void> {
  const { data: order, error: orderLookupError } = await supabase
    .from("orders")
    .select("subscription_id")
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (orderLookupError || !order) {
    console.warn(
      "Failed to resolve order subscription while marking plan active",
      {
        requestId,
        tenantId,
        orderId,
        source,
        error: orderLookupError?.message || "order_not_found",
      },
    );
    return;
  }

  await markSubscriptionAsActiveIfPendingValidation(
    supabase,
    tenantId,
    order.subscription_id || null,
    requestId,
    source,
  );
}

Deno.serve(async (req) => {
  const requestId = crypto.randomUUID();
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, stripe-signature",
    methods: "POST, OPTIONS",
  });
  const requestStartTime = Date.now();

  // Log all incoming requests
  console.info("=== Stripe Webhook Request Received ===", {
    requestId,
    timestamp: dateTime().toISOString(),
    method: req.method,
    url: req.url,
  });

  // Log request headers (excluding sensitive values)
  const headersLog: Record<string, string> = {};
  req.headers.forEach((value, key) => {
    // Mask sensitive header values but show they exist
    if (key.toLowerCase() === "stripe-signature") {
      headersLog[key] = value.substring(0, 20) + "...[TRUNCATED]";
    } else if (key.toLowerCase() === "authorization") {
      headersLog[key] = "[PRESENT]";
    } else {
      headersLog[key] = value;
    }
  });
  console.info("Request Headers", { requestId, headers: headersLog });

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    console.info("CORS preflight request handled", { requestId });
    return new Response(null, { headers: corsHeaders });
  }

  if (req.method !== "POST") {
    console.warn("Invalid method received", { requestId, method: req.method });
    return new Response(JSON.stringify({ error: "Method not allowed" }), {
      status: 405,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey, {
    global: {
      headers: {
        "x-request-id": requestId,
        "x-request-source": "stripe-webhook",
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });

  try {
    const signature = req.headers.get("stripe-signature");
    const body = await req.text();

    // Log body size and signature presence
    console.info("Request body received", {
      requestId,
      bodyLength: body.length,
      hasSignature: !!signature,
      signaturePreview: signature ? signature.substring(0, 30) + "..." : null,
    });

    if (!signature) {
      console.error("Missing Stripe signature", { requestId });
      return new Response(
        JSON.stringify({ error: "Missing stripe-signature header" }),
        {
          status: 400,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    // Parse the event to get tenant context
    let event;
    try {
      event = JSON.parse(body);
      console.info("Event parsed successfully", {
        requestId,
        eventId: event.id,
        eventType: event.type,
        apiVersion: event.api_version,
        created: event.created
          ? dateTime.unix(event.created).toISOString()
          : null,
        livemode: event.livemode,
      });
    } catch (parseError) {
      console.error("Failed to parse JSON body", {
        requestId,
        error: String(parseError),
      });
      return new Response(JSON.stringify({ error: "Invalid JSON payload" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    const { data: globalStripeProvider, error: globalStripeProviderError } =
      await supabaseAdmin
        .from("payment_providers")
        .select("id")
        .eq("key", "stripe")
        .maybeSingle();

    if (globalStripeProviderError) {
      console.error("Failed to resolve Stripe payment provider id", {
        requestId,
        error: globalStripeProviderError.message,
      });
    }

    const stripePaymentProviderId = globalStripeProvider?.id || null;

    // Get tenant_id from event metadata to retrieve webhook secret
    // Check multiple locations for tenant_id based on event type
    const objectMetadata = event.data?.object?.metadata || {};
    let tenantId = objectMetadata.tenant_id;

    // For invoice events, also check subscription_details.metadata
    if (!tenantId && event.data?.object?.subscription_details?.metadata) {
      tenantId = event.data.object.subscription_details.metadata.tenant_id;
      console.info("Found tenant_id in subscription_details.metadata", {
        requestId,
        tenantId,
        eventType: event.type,
      });
    }

    // For invoice events, also check parent.subscription_details.metadata
    if (
      !tenantId &&
      event.data?.object?.parent?.subscription_details?.metadata
    ) {
      tenantId =
        event.data.object.parent.subscription_details.metadata.tenant_id;
      console.info("Found tenant_id in parent.subscription_details.metadata", {
        requestId,
        tenantId,
        eventType: event.type,
      });
    }

    // For invoice events, try to infer tenant from related order data (subscription/checkout/payment intent)
    if (!tenantId && event.type.startsWith("invoice.") && event.data?.object) {
      const invoiceObject = event.data.object;
      const { id: invoiceSubscriptionId, source: invoiceSubscriptionSource } =
        getSubscriptionIdFromInvoice(invoiceObject);
      const {
        id: invoiceCheckoutSessionId,
        source: invoiceCheckoutSessionSource,
      } = getCheckoutSessionIdFromInvoice(invoiceObject);
      const invoicePaymentIntentId = getPaymentIntentIdFromInvoice(
        invoiceObject as StripeInvoiceManualCapture | Invoice,
      );

      if (stripePaymentProviderId && invoiceSubscriptionId) {
        const { data: linksBySubscription, error: subscriptionLookupError } =
          await supabaseAdmin
            .from("subscription_payment_provider_links")
            .select("tenant_id")
            .eq("payment_provider_id", stripePaymentProviderId)
            .eq("provider_subscription_id", invoiceSubscriptionId)
            .limit(2);

        if (subscriptionLookupError) {
          console.warn("Failed to lookup tenant by subscription id", {
            requestId,
            subscriptionId: invoiceSubscriptionId,
            source: invoiceSubscriptionSource,
            error: subscriptionLookupError.message,
          });
        } else if (linksBySubscription && linksBySubscription.length > 1) {
          console.warn(
            "Multiple orders matched subscription id - skipping tenant lookup",
            {
              requestId,
              subscriptionId: invoiceSubscriptionId,
              source: invoiceSubscriptionSource,
              matchedTenantIds: linksBySubscription.map((row) => row.tenant_id),
            },
          );
        } else if (linksBySubscription && linksBySubscription.length === 1) {
          tenantId = linksBySubscription[0].tenant_id;
          console.info("Found tenant_id from subscription id lookup", {
            requestId,
            tenantId,
            subscriptionId: invoiceSubscriptionId,
            source: invoiceSubscriptionSource,
          });
        }
      }

      if (!tenantId && stripePaymentProviderId && invoiceCheckoutSessionId) {
        const { data: linksByCheckoutSession, error: sessionLookupError } =
          await supabaseAdmin
            .from("subscription_payment_provider_links")
            .select("tenant_id")
            .eq("payment_provider_id", stripePaymentProviderId)
            .eq("provider_checkout_session_id", invoiceCheckoutSessionId)
            .limit(2);

        if (sessionLookupError) {
          console.warn("Failed to lookup tenant by checkout_session_id", {
            requestId,
            checkoutSessionId: invoiceCheckoutSessionId,
            source: invoiceCheckoutSessionSource,
            error: sessionLookupError.message,
          });
        } else if (
          linksByCheckoutSession &&
          linksByCheckoutSession.length > 1
        ) {
          console.warn(
            "Multiple orders matched checkout_session_id - skipping tenant lookup",
            {
              requestId,
              checkoutSessionId: invoiceCheckoutSessionId,
              source: invoiceCheckoutSessionSource,
              matchedTenantIds: linksByCheckoutSession.map(
                (row) => row.tenant_id,
              ),
            },
          );
        } else if (
          linksByCheckoutSession &&
          linksByCheckoutSession.length === 1
        ) {
          tenantId = linksByCheckoutSession[0].tenant_id;
          console.info("Found tenant_id from checkout_session_id lookup", {
            requestId,
            tenantId,
            checkoutSessionId: invoiceCheckoutSessionId,
            source: invoiceCheckoutSessionSource,
          });
        }
      }

      if (!tenantId && stripePaymentProviderId && invoicePaymentIntentId) {
        const {
          data: transactionsByPaymentIntent,
          error: paymentIntentLookupError,
        } = await supabaseAdmin
          .from("order_payment_provider_transactions")
          .select("tenant_id")
          .eq("payment_provider_id", stripePaymentProviderId)
          .eq("provider_payment_intent_id", invoicePaymentIntentId)
          .limit(2);

        if (paymentIntentLookupError) {
          console.warn("Failed to lookup tenant by payment intent id", {
            requestId,
            paymentIntentId: invoicePaymentIntentId,
            error: paymentIntentLookupError.message,
          });
        } else if (
          transactionsByPaymentIntent &&
          transactionsByPaymentIntent.length > 1
        ) {
          console.warn(
            "Multiple orders matched payment intent id - skipping tenant lookup",
            {
              requestId,
              paymentIntentId: invoicePaymentIntentId,
              matchedTenantIds: transactionsByPaymentIntent.map(
                (row) => row.tenant_id,
              ),
            },
          );
        } else if (
          transactionsByPaymentIntent &&
          transactionsByPaymentIntent.length === 1
        ) {
          tenantId = transactionsByPaymentIntent[0].tenant_id;
          console.info("Found tenant_id from payment intent id lookup", {
            requestId,
            tenantId,
            paymentIntentId: invoicePaymentIntentId,
          });
        }
      }
    }

    // For invoice events, try to resolve tenant from expanded customer metadata
    const rawInvoiceCustomer = event.type.startsWith("invoice.")
      ? event.data?.object?.customer
      : null;
    if (
      !tenantId &&
      rawInvoiceCustomer &&
      typeof rawInvoiceCustomer === "object"
    ) {
      const customerMetadataTenantId = rawInvoiceCustomer.metadata?.tenant_id;
      if (customerMetadataTenantId) {
        tenantId = customerMetadataTenantId;
        console.info("Found tenant_id in expanded customer metadata", {
          requestId,
          tenantId,
          eventType: event.type,
        });
      }
    }

    // For invoice events, try to find tenant by Stripe customer ID stored in patient metadata
    if (!tenantId && event.type.startsWith("invoice.") && rawInvoiceCustomer) {
      const stripeCustomerId =
        typeof rawInvoiceCustomer === "string"
          ? rawInvoiceCustomer
          : rawInvoiceCustomer?.id;

      if (!stripeCustomerId) {
        console.warn("Invoice customer present but missing id", {
          requestId,
          eventType: event.type,
        });
      } else {
        console.info("Attempting to lookup tenant by Stripe customer ID", {
          requestId,
          stripeCustomerId,
          eventType: event.type,
          customerType: typeof rawInvoiceCustomer,
        });

        const {
          data: patientsByStripeCustomer,
          error: patientByStripeCustomerError,
        } = await supabaseAdmin
          .from("patients")
          .select("id, tenant_id")
          .filter("metadata->>stripe_customer_id", "eq", stripeCustomerId)
          .limit(2);

        if (patientByStripeCustomerError) {
          console.warn("Failed to lookup tenant by Stripe customer ID", {
            requestId,
            stripeCustomerId,
            error: patientByStripeCustomerError.message,
          });
        } else if (
          patientsByStripeCustomer &&
          patientsByStripeCustomer.length > 1
        ) {
          console.warn(
            "Multiple patients matched Stripe customer ID - skipping ambiguous tenant lookup",
            {
              requestId,
              stripeCustomerId,
              matchedPatientIds: patientsByStripeCustomer.map(
                (patient) => patient.id,
              ),
              matchedTenantIds: patientsByStripeCustomer.map(
                (patient) => patient.tenant_id,
              ),
            },
          );
        } else if (
          patientsByStripeCustomer &&
          patientsByStripeCustomer.length === 1
        ) {
          tenantId = patientsByStripeCustomer[0].tenant_id;
          console.info("Found tenant_id from Stripe customer ID lookup", {
            requestId,
            tenantId,
            stripeCustomerId,
          });
        }
      }
    }

    // For invoice events, also try to get tenant from subscription lookup
    if (
      !tenantId &&
      event.type.startsWith("invoice.") &&
      event.data?.object?.subscription
    ) {
      const rawInvoiceSubscription = event.data.object.subscription;
      const subscriptionId =
        typeof rawInvoiceSubscription === "string"
          ? rawInvoiceSubscription
          : rawInvoiceSubscription?.id;

      if (!subscriptionId) {
        console.warn("Invoice subscription present but missing id", {
          requestId,
          eventType: event.type,
        });
      }

      // If subscription is expanded, try to read tenant_id from its metadata
      if (
        !tenantId &&
        rawInvoiceSubscription &&
        typeof rawInvoiceSubscription === "object"
      ) {
        const subscriptionMetadataTenantId =
          rawInvoiceSubscription.metadata?.tenant_id;
        if (subscriptionMetadataTenantId) {
          tenantId = subscriptionMetadataTenantId;
          console.info("Found tenant_id in expanded subscription metadata", {
            requestId,
            tenantId,
            eventType: event.type,
          });
        }
      }

      console.info("Attempting to lookup tenant from subscription", {
        requestId,
        subscriptionId,
        eventType: event.type,
      });

      // First, try to find an existing transaction with this subscription's payment intent
      const paymentIntentId = event.data?.object?.payment_intent;
      if (stripePaymentProviderId && paymentIntentId) {
        const { data: existingTransaction } = await supabaseAdmin
          .from("order_payment_provider_transactions")
          .select("tenant_id")
          .eq("payment_provider_id", stripePaymentProviderId)
          .eq("provider_payment_intent_id", paymentIntentId)
          .maybeSingle();

        if (existingTransaction?.tenant_id) {
          tenantId = existingTransaction.tenant_id;
          console.info("Found tenant_id from existing transaction", {
            requestId,
            tenantId,
            paymentIntentId,
          });
        }
      }

      // If still no tenant, try to find by customer email
      if (!tenantId && event.data?.object?.customer_email) {
        const customerEmail = event.data.object.customer_email.toLowerCase();
        const { data: patient } = await supabaseAdmin
          .from("patients")
          .select("tenant_id")
          .eq("email", customerEmail)
          .maybeSingle();

        if (patient?.tenant_id) {
          tenantId = patient.tenant_id;
          console.info("Found tenant_id from patient email lookup", {
            requestId,
            tenantId,
            customerEmail,
          });
        }
      }

      // If still no tenant, fetch the subscription from Stripe to get its metadata
      if (!tenantId && subscriptionId) {
        console.info("Attempting to fetch subscription metadata from Stripe", {
          requestId,
          subscriptionId,
        });

        // We need to find which tenant has this subscription to get their Stripe key
        // Try to find by customer email first to get a tenant candidate
        const customerEmail = event.data?.object?.customer_email;
        if (customerEmail) {
          const { data: patient } = await supabaseAdmin
            .from("patients")
            .select("tenant_id")
            .eq("email", customerEmail.toLowerCase())
            .maybeSingle();

          if (patient?.tenant_id) {
            // Get tenant's Stripe secret key
            const { data: stripeProvider } = await supabaseAdmin
              .from("tenant_payment_providers")
              .select(
                `
                settings,
                payment_providers!inner (key)
              `,
              )
              .eq("tenant_id", patient.tenant_id)
              .eq("is_enabled", true)
              .eq("payment_providers.key", "stripe")
              .maybeSingle();

            if (stripeProvider) {
              const settings = stripeProvider.settings as Record<
                string,
                string
              >;
              const secretKey = settings?.secret_key;

              if (secretKey) {
                try {
                  // Fetch subscription from Stripe API
                  const subscriptionResponse = await fetch(
                    `https://api.stripe.com/v1/subscriptions/${subscriptionId}`,
                    {
                      method: "GET",
                      headers: {
                        Authorization: `Bearer ${secretKey}`,
                      },
                    },
                  );

                  if (subscriptionResponse.ok) {
                    const subscription = await subscriptionResponse.json();
                    if (subscription.metadata?.tenant_id) {
                      tenantId = subscription.metadata.tenant_id;
                      console.info(
                        "Found tenant_id from Stripe subscription metadata",
                        {
                          requestId,
                          tenantId,
                          subscriptionId,
                        },
                      );
                    } else {
                      console.warn(
                        "Subscription exists but has no tenant_id in metadata",
                        {
                          requestId,
                          subscriptionId,
                          metadataKeys: Object.keys(
                            subscription.metadata || {},
                          ),
                        },
                      );
                    }
                  } else {
                    console.warn("Failed to fetch subscription from Stripe", {
                      requestId,
                      subscriptionId,
                      status: subscriptionResponse.status,
                    });
                  }
                } catch (fetchError) {
                  console.error("Error fetching subscription from Stripe", {
                    requestId,
                    subscriptionId,
                    error:
                      fetchError instanceof Error
                        ? fetchError.message
                        : String(fetchError),
                  });
                }
              }
            }
          }
        }
      }
    }

    // For payment intent events, infer tenant from related order/patient data
    if (
      !tenantId &&
      event.type.startsWith("payment_intent.") &&
      event.data?.object
    ) {
      const paymentIntentObject = event.data.object;
      const paymentIntentId = paymentIntentObject.id;
      const paymentIntentInvoiceId =
        paymentIntentObject.invoice ||
        paymentIntentObject.metadata?.invoice_id ||
        null;
      const paymentIntentCheckoutSessionId =
        getCheckoutSessionIdFromPaymentIntentObject(paymentIntentObject);
      const rawPaymentIntentCustomer = paymentIntentObject.customer;
      const paymentIntentCustomerId =
        typeof rawPaymentIntentCustomer === "string"
          ? rawPaymentIntentCustomer
          : rawPaymentIntentCustomer?.id;

      if (stripePaymentProviderId && paymentIntentId) {
        const {
          data: transactionsByPaymentIntent,
          error: paymentIntentLookupError,
        } = await supabaseAdmin
          .from("order_payment_provider_transactions")
          .select("tenant_id")
          .eq("payment_provider_id", stripePaymentProviderId)
          .eq("provider_payment_intent_id", paymentIntentId)
          .limit(2);

        if (paymentIntentLookupError) {
          console.warn(
            "Failed to lookup tenant by payment intent id (payment_intent event)",
            {
              requestId,
              paymentIntentId,
              error: paymentIntentLookupError.message,
            },
          );
        } else if (
          transactionsByPaymentIntent &&
          transactionsByPaymentIntent.length > 1
        ) {
          console.warn(
            "Multiple orders matched payment intent id - skipping tenant lookup",
            {
              requestId,
              paymentIntentId,
              matchedTenantIds: transactionsByPaymentIntent.map(
                (row) => row.tenant_id,
              ),
            },
          );
        } else if (
          transactionsByPaymentIntent &&
          transactionsByPaymentIntent.length === 1
        ) {
          tenantId = transactionsByPaymentIntent[0].tenant_id;
          console.info(
            "Found tenant_id from payment intent id lookup (payment_intent event)",
            {
              requestId,
              tenantId,
              paymentIntentId,
            },
          );
        }
      }

      if (!tenantId && stripePaymentProviderId && paymentIntentInvoiceId) {
        const { data: transactionsByInvoice, error: invoiceLookupError } =
          await supabaseAdmin
            .from("order_payment_provider_transactions")
            .select("tenant_id")
            .eq("payment_provider_id", stripePaymentProviderId)
            .eq("provider_invoice_id", paymentIntentInvoiceId)
            .limit(2);

        if (invoiceLookupError) {
          console.warn(
            "Failed to lookup tenant by invoice id (payment_intent event)",
            {
              requestId,
              invoiceId: paymentIntentInvoiceId,
              error: invoiceLookupError.message,
            },
          );
        } else if (transactionsByInvoice && transactionsByInvoice.length > 1) {
          console.warn(
            "Multiple orders matched invoice id - skipping tenant lookup",
            {
              requestId,
              invoiceId: paymentIntentInvoiceId,
              matchedTenantIds: transactionsByInvoice.map(
                (row) => row.tenant_id,
              ),
            },
          );
        } else if (
          transactionsByInvoice &&
          transactionsByInvoice.length === 1
        ) {
          tenantId = transactionsByInvoice[0].tenant_id;
          console.info(
            "Found tenant_id from invoice id lookup (payment_intent event)",
            {
              requestId,
              tenantId,
              invoiceId: paymentIntentInvoiceId,
            },
          );
        }
      }

      if (
        !tenantId &&
        stripePaymentProviderId &&
        paymentIntentCheckoutSessionId
      ) {
        const {
          data: linksByCheckoutSession,
          error: checkoutSessionLinkLookupError,
        } = await supabaseAdmin
          .from("subscription_payment_provider_links")
          .select("tenant_id")
          .eq("payment_provider_id", stripePaymentProviderId)
          .eq("provider_checkout_session_id", paymentIntentCheckoutSessionId)
          .limit(2);

        if (checkoutSessionLinkLookupError) {
          console.warn(
            "Failed to lookup tenant by checkout session id via subscription links (payment_intent event)",
            {
              requestId,
              checkoutSessionId: paymentIntentCheckoutSessionId,
              error: checkoutSessionLinkLookupError.message,
            },
          );
        } else if (
          linksByCheckoutSession &&
          linksByCheckoutSession.length > 1
        ) {
          console.warn(
            "Multiple subscriptions matched checkout session id - skipping tenant lookup",
            {
              requestId,
              checkoutSessionId: paymentIntentCheckoutSessionId,
              matchedTenantIds: linksByCheckoutSession.map(
                (row) => row.tenant_id,
              ),
            },
          );
        } else if (
          linksByCheckoutSession &&
          linksByCheckoutSession.length === 1
        ) {
          tenantId = linksByCheckoutSession[0].tenant_id;
          console.info(
            "Found tenant_id from checkout session id lookup via subscription links (payment_intent event)",
            {
              requestId,
              tenantId,
              checkoutSessionId: paymentIntentCheckoutSessionId,
            },
          );
        }
      }

      if (
        !tenantId &&
        stripePaymentProviderId &&
        paymentIntentCheckoutSessionId
      ) {
        const {
          data: transactionsByCheckoutSession,
          error: checkoutSessionLookupError,
        } = await supabaseAdmin
          .from("order_payment_provider_transactions")
          .select("tenant_id")
          .eq("payment_provider_id", stripePaymentProviderId)
          .eq("provider_checkout_session_id", paymentIntentCheckoutSessionId)
          .limit(2);

        if (checkoutSessionLookupError) {
          console.warn(
            "Failed to lookup tenant by checkout session id (payment_intent event)",
            {
              requestId,
              checkoutSessionId: paymentIntentCheckoutSessionId,
              error: checkoutSessionLookupError.message,
            },
          );
        } else if (
          transactionsByCheckoutSession &&
          transactionsByCheckoutSession.length > 1
        ) {
          console.warn(
            "Multiple orders matched checkout session id - skipping tenant lookup",
            {
              requestId,
              checkoutSessionId: paymentIntentCheckoutSessionId,
              matchedTenantIds: transactionsByCheckoutSession.map(
                (row) => row.tenant_id,
              ),
            },
          );
        } else if (
          transactionsByCheckoutSession &&
          transactionsByCheckoutSession.length === 1
        ) {
          tenantId = transactionsByCheckoutSession[0].tenant_id;
          console.info(
            "Found tenant_id from checkout session id lookup (payment_intent event)",
            {
              requestId,
              tenantId,
              checkoutSessionId: paymentIntentCheckoutSessionId,
            },
          );
        }
      }

      if (!tenantId && paymentIntentCustomerId) {
        const {
          data: patientsByStripeCustomer,
          error: patientByStripeCustomerError,
        } = await supabaseAdmin
          .from("patients")
          .select("id, tenant_id")
          .filter(
            "metadata->>stripe_customer_id",
            "eq",
            paymentIntentCustomerId,
          )
          .limit(2);

        if (patientByStripeCustomerError) {
          console.warn(
            "Failed to lookup tenant by Stripe customer ID (payment_intent event)",
            {
              requestId,
              stripeCustomerId: paymentIntentCustomerId,
              error: patientByStripeCustomerError.message,
            },
          );
        } else if (
          patientsByStripeCustomer &&
          patientsByStripeCustomer.length > 1
        ) {
          console.warn(
            "Multiple patients matched Stripe customer ID - skipping ambiguous tenant lookup",
            {
              requestId,
              stripeCustomerId: paymentIntentCustomerId,
              matchedPatientIds: patientsByStripeCustomer.map(
                (patient) => patient.id,
              ),
              matchedTenantIds: patientsByStripeCustomer.map(
                (patient) => patient.tenant_id,
              ),
            },
          );
        } else if (
          patientsByStripeCustomer &&
          patientsByStripeCustomer.length === 1
        ) {
          tenantId = patientsByStripeCustomer[0].tenant_id;
          console.info(
            "Found tenant_id from Stripe customer ID lookup (payment_intent event)",
            {
              requestId,
              tenantId,
              stripeCustomerId: paymentIntentCustomerId,
            },
          );
        }
      }
    }

    // Log metadata for debugging
    console.info("Event metadata extracted", {
      requestId,
      eventType: event.type,
      hasTenantId: !!tenantId,
      tenantId: tenantId || null,
      metadataKeys: Object.keys(objectMetadata),
      objectType: event.data?.object?.object,
      hasSubscriptionDetails: !!event.data?.object?.subscription_details,
      subscriptionDetailsMetadataKeys: event.data?.object?.subscription_details
        ?.metadata
        ? Object.keys(event.data.object.subscription_details.metadata)
        : [],
    });

    if (!tenantId) {
      console.warn(
        "Missing tenant_id in event metadata - skipping processing",
        {
          requestId,
          eventType: event.type,
          eventId: event.id,
          objectId: event.data?.object?.id,
        },
      );

      if (RETRYABLE_EVENTS_WITHOUT_TENANT_CONTEXT.has(event.type)) {
        const duration = Date.now() - requestStartTime;
        console.error(
          "Missing tenant context for retryable event - returning 503 for Stripe retry",
          {
            requestId,
            duration: `${duration}ms`,
            eventType: event.type,
            eventId: event.id,
            objectId: event.data?.object?.id,
          },
        );
        return new Response(
          JSON.stringify({ error: "missing_tenant_context", retryable: true }),
          {
            status: 503,
            headers: { ...corsHeaders, "Content-Type": "application/json" },
          },
        );
      }

      // For non-critical events without tenant context, skip to avoid unnecessary retries.
      const duration = Date.now() - requestStartTime;
      console.info("=== Request Completed (No Tenant Context) ===", {
        requestId,
        duration: `${duration}ms`,
        status: 200,
      });
      return new Response(
        JSON.stringify({ received: true, skipped: "no_tenant_context" }),
        {
          status: 200,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    // Get tenant's Stripe configuration
    console.info("Fetching tenant Stripe configuration", {
      requestId,
      tenantId,
    });
    const { data: stripeProvider, error: providerError } = await supabaseAdmin
      .from("tenant_payment_providers")
      .select(
        `
        id,
        settings,
        payment_providers!inner (id, key)
      `,
      )
      .eq("tenant_id", tenantId)
      .eq("is_enabled", true)
      .eq("payment_providers.key", "stripe")
      .maybeSingle();

    if (providerError || !stripeProvider) {
      console.error("Failed to fetch Stripe provider for tenant", {
        requestId,
        tenantId,
        error: providerError?.message || "Provider not found",
      });
      const duration = Date.now() - requestStartTime;
      console.info("=== Request Completed (Provider Error) ===", {
        requestId,
        duration: `${duration}ms`,
        status: 400,
      });
      return new Response(
        JSON.stringify({ error: "Stripe not configured for tenant" }),
        {
          status: 400,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    console.info("Tenant Stripe configuration found", {
      requestId,
      tenantId,
      providerId: stripeProvider.id,
      hasWebhookSecret: !!(stripeProvider.settings as Record<string, string>)
        ?.webhook_secret,
    });

    const settings = stripeProvider.settings as Record<string, string>;
    const webhookSecret = settings?.webhook_secret;
    const stripeSecretKey = settings?.secret_key || null;
    const tenantStripeProvider = stripeProvider.payment_providers as {
      id?: string;
      key?: string;
    } | null;
    const resolvedStripePaymentProviderId =
      tenantStripeProvider?.id || stripePaymentProviderId;

    if (!resolvedStripePaymentProviderId) {
      console.error("Unable to resolve Stripe payment provider id for tenant", {
        requestId,
        tenantId,
      });
      return new Response(
        JSON.stringify({ error: "Stripe payment provider id not found" }),
        {
          status: 500,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    if (!webhookSecret) {
      console.warn(
        "No webhook secret configured for tenant - signature verification skipped",
        {
          requestId,
          tenantId,
        },
      );
      // If no webhook secret, we can't verify - log and continue with caution
      // In production, you might want to reject this
    } else {
      // Verify signature
      console.info("Verifying Stripe signature", { requestId, tenantId });
      const verification = await verifyStripeSignature(
        body,
        signature,
        webhookSecret,
      );
      if (!verification.valid) {
        console.error("Stripe signature verification failed", {
          requestId,
          tenantId,
          error: verification.error,
        });
        const duration = Date.now() - requestStartTime;
        console.info("=== Request Completed (Signature Invalid) ===", {
          requestId,
          duration: `${duration}ms`,
          status: 401,
        });
        return new Response(JSON.stringify({ error: "Invalid signature" }), {
          status: 401,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        });
      }
      console.info("Stripe signature verified successfully", {
        requestId,
        tenantId,
      });
    }

    console.info("Processing Stripe event", {
      requestId,
      type: event.type,
      tenantId,
      eventId: event.id,
    });

    await dispatchStripeWebhookEvent({
      event: event as StripeWebhookEvent,
      requestId,
      handlers: {
        handleCheckoutSessionCompleted: (object) =>
          handleCheckoutSessionCompleted(
            supabaseAdmin,
            object as unknown as CheckoutSession,
            tenantId,
            requestId,
            resolvedStripePaymentProviderId,
            stripeSecretKey,
          ),
        handleCheckoutSessionExpired: (object) =>
          handleCheckoutSessionExpired(
            object as unknown as CheckoutSession,
            tenantId,
            requestId,
          ),
        handlePaymentIntentSucceeded: (object) =>
          handlePaymentIntentSucceeded(
            supabaseAdmin,
            object as unknown as PaymentIntent,
            tenantId,
            requestId,
            stripeSecretKey,
            resolvedStripePaymentProviderId,
          ),
        handlePaymentIntentAmountCapturableUpdated: (object) =>
          handlePaymentIntentAmountCapturableUpdated(
            supabaseAdmin,
            object as unknown as PaymentIntent,
            tenantId,
            requestId,
            stripeSecretKey,
            resolvedStripePaymentProviderId,
          ),
        handlePaymentIntentFailed: (object) =>
          handlePaymentIntentFailed(
            supabaseAdmin,
            object as unknown as PaymentIntent,
            tenantId,
            requestId,
            resolvedStripePaymentProviderId,
          ),
        handlePaymentIntentCancelled: (object) =>
          handlePaymentIntentCancelled(
            supabaseAdmin,
            object as unknown as PaymentIntent,
            tenantId,
            requestId,
            resolvedStripePaymentProviderId,
          ),
        handleSubscriptionCreated: (object) =>
          handleSubscriptionCreated(
            supabaseAdmin,
            object as unknown as Subscription,
            tenantId,
            requestId,
            resolvedStripePaymentProviderId,
          ),
        handleSubscriptionUpdated: (object) =>
          handleSubscriptionUpdated(
            supabaseAdmin,
            object as unknown as Subscription,
            tenantId,
            requestId,
          ),
        handleSubscriptionDeleted: (object) =>
          handleSubscriptionDeleted(
            supabaseAdmin,
            object as unknown as Subscription,
            tenantId,
            requestId,
            resolvedStripePaymentProviderId,
          ),
        handleInvoiceCreated: (object) =>
          handleInvoiceCreated(
            supabaseAdmin,
            object as unknown as Invoice,
            tenantId,
            requestId,
            stripeSecretKey,
            resolvedStripePaymentProviderId,
          ),
        handleInvoicePaymentFailed: (object) =>
          handleInvoicePaymentFailed(
            object as unknown as Invoice,
            tenantId,
            requestId,
          ),
        handleCustomerUpdated: (object) =>
          handleCustomerUpdated(
            supabaseAdmin,
            object,
            tenantId,
            requestId,
            triggerOrderLifecycle,
          ),
      },
    });

    const duration = Date.now() - requestStartTime;
    console.info("=== Request Completed Successfully ===", {
      requestId,
      duration: `${duration}ms`,
      status: 200,
      eventType: event.type,
      eventId: event.id,
    });

    return new Response(JSON.stringify({ received: true, type: event.type }), {
      status: 200,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  } catch (error) {
    const duration = Date.now() - requestStartTime;
    const retryable = error instanceof RetryableWebhookError;
    const providerMismatch = error instanceof OrderPaymentProviderMismatchError;
    console.error("=== Request Failed with Error ===", {
      requestId,
      duration: `${duration}ms`,
      retryable,
      providerMismatch,
      error: error instanceof Error ? error.message : String(error),
      stack: error instanceof Error ? error.stack : undefined,
    });

    if (providerMismatch) {
      return new Response(
        JSON.stringify({
          received: true,
          ignored: true,
          reason: "order_payment_provider_not_stripe",
        }),
        {
          status: 200,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    if (retryable) {
      return new Response(
        JSON.stringify({
          error: error.message,
          retryable: true,
        }),
        {
          status: 503,
          headers: { ...corsHeaders, "Content-Type": "application/json" },
        },
      );
    }

    return new Response(JSON.stringify({ error: "Internal server error" }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});

// ============= Helper Functions =============

/**
 * Propagate metadata to a Stripe subscription so subsequent events have tenant context.
 * This is critical because Stripe doesn't automatically copy checkout session metadata to subscriptions.
 * Without this, events like invoice.paid won't have tenant_id and will be skipped.
 */
async function propagateMetadataToSubscription(
  tenantId: string,
  subscriptionId: string,
  metadata: Record<string, string>,
  requestId: string,
): Promise<void> {
  try {
    console.info("Propagating metadata to subscription", {
      requestId,
      subscriptionId,
      metadataKeys: Object.keys(metadata),
    });

    // Get tenant's Stripe secret key
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(supabaseUrl, supabaseServiceKey);

    const { data: stripeProvider, error: providerError } = await supabase
      .from("tenant_payment_providers")
      .select(
        `
        settings,
        payment_providers!inner (key)
      `,
      )
      .eq("tenant_id", tenantId)
      .eq("is_enabled", true)
      .eq("payment_providers.key", "stripe")
      .maybeSingle();

    if (providerError || !stripeProvider) {
      console.warn("Could not fetch Stripe provider for metadata propagation", {
        requestId,
        tenantId,
        error: providerError?.message,
      });
      return;
    }

    const settings = stripeProvider.settings as Record<string, string>;
    const secretKey = settings?.secret_key;

    if (!secretKey) {
      console.warn(
        "No Stripe secret key configured for tenant - cannot update subscription metadata",
        {
          requestId,
          tenantId,
        },
      );
      return;
    }

    // Update subscription metadata via Stripe API
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(metadata)) {
      params.append(`metadata[${key}]`, value);
    }

    const response = await fetch(
      `https://api.stripe.com/v1/subscriptions/${subscriptionId}`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${secretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: params.toString(),
      },
    );

    if (!response.ok) {
      const errorText = await response.text();
      console.error("Failed to update subscription metadata", {
        requestId,
        subscriptionId,
        status: response.status,
        error: errorText,
      });
      return;
    }

    console.info("Successfully propagated metadata to subscription", {
      requestId,
      subscriptionId,
      tenantId,
    });
  } catch (error) {
    // Don't throw - this is a non-critical enhancement
    console.error("Error propagating metadata to subscription", {
      requestId,
      subscriptionId,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

// ============= Event Handlers =============

interface StripeAddress {
  line1?: string;
  line2?: string;
  city?: string;
  state?: string;
  postal_code?: string;
  country?: string;
}

interface StripeCustomerDetails {
  email?: string;
  name?: string;
  phone?: string;
  address?: StripeAddress;
}

interface StripeShippingDetails {
  name?: string;
  phone?: string;
  address?: StripeAddress;
}

interface CheckoutSession {
  id: string;
  livemode?: boolean | null;
  customer_email?: string | null;
  customer?: string | null;
  payment_status: string;
  status: string;
  mode: string;
  amount_total?: number | null;
  currency?: string | null;
  subscription?: string;
  payment_intent?: string;
  setup_intent?: string | { id?: string };
  customer_details?: StripeCustomerDetails;
  shipping_details?: StripeShippingDetails;
  metadata?: {
    tenant_id?: string;
    patient_id?: string;
    product_id?: string;
    customer_email?: string;
  };
  // Payment details for storing
  payment_method_types?: string[];
  // Discount details
  total_details?: {
    amount_discount?: number | null;
  } | null;
  discounts?: Array<{
    promotion_code?: string | { id?: string; code?: string } | null;
    coupon?: string | { id?: string; name?: string | null } | null;
  }> | null;
}

interface CheckoutSessionListResponse {
  data?: CheckoutSession[];
}

interface SubscriptionCheckoutProduct {
  id: string;
  name: string;
  description: string | null;
  price_cents: number;
  payment_type: string;
  subscription_interval: string | null;
  subscription_interval_count: number | null;
  image_url: string | null;
}

interface StripeSubscriptionResponse {
  id: string;
  current_period_start?: number;
  current_period_end?: number;
}

interface StripeProductResponse {
  id: string;
}

type StripeSubscriptionDiscount = {
  couponId: string | null;
  promotionCodeId: string | null;
};

function getStripePaymentMethodId(
  value: string | { id?: string } | null | undefined,
): string | null {
  if (typeof value === "string" && value.trim()) return value.trim();
  if (
    value &&
    typeof value === "object" &&
    typeof value.id === "string" &&
    value.id.trim()
  ) {
    return value.id.trim();
  }
  return null;
}

function getStripeObjectId(
  value: string | { id?: string } | null | undefined,
): string | null {
  if (typeof value === "string" && value.trim()) return value.trim();
  if (
    value &&
    typeof value === "object" &&
    typeof value.id === "string" &&
    value.id.trim()
  ) {
    return value.id.trim();
  }
  return null;
}

async function createSendInvoiceStripeSubscription(params: {
  session: CheckoutSession;
  tenantId: string;
  product: SubscriptionCheckoutProduct;
  patientId: string;
  customerEmail: string | null;
  stripeSecretKey: string;
  paymentMethodId?: string | null;
  subscriptionDiscount?: StripeSubscriptionDiscount | null;
  requestId: string;
}): Promise<StripeSubscriptionResponse> {
  const {
    session,
    tenantId,
    product,
    patientId,
    customerEmail,
    stripeSecretKey,
    paymentMethodId,
    subscriptionDiscount,
    requestId,
  } = params;

  if (!session.customer || !session.customer.trim()) {
    console.error("Cannot create subscription without Stripe customer", {
      requestId,
      sessionId: session.id,
      tenantId,
    });
    throw new RetryableWebhookError(
      "setup_checkout_missing_stripe_customer_for_subscription_creation",
    );
  }

  // Search for an existing Stripe product by allia_product_id metadata
  let stripeProductId: string | null = null;
  const searchQuery = `metadata['allia_product_id']:'${product.id}'`;
  const searchRes = await fetch(
    `https://api.stripe.com/v1/products/search?query=${encodeURIComponent(searchQuery)}&limit=1`,
    { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
  );
  if (searchRes.ok) {
    const searchBody = await searchRes.json();
    stripeProductId = searchBody?.data?.[0]?.id ?? null;
  }

  if (!stripeProductId) {
    // No existing product found — create a new one
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
    stripeProductParams.append("metadata[checkout_session_id]", session.id);

    const stripeProductResponse = await fetch(
      "https://api.stripe.com/v1/products",
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
          "Idempotency-Key": `allia_checkout_product_${session.id}`,
        },
        body: stripeProductParams.toString(),
      },
    );

    if (!stripeProductResponse.ok) {
      const errorText = await stripeProductResponse.text();
      let stripeErrorMessage = errorText;
      try {
        const parsedError = JSON.parse(errorText) as {
          error?: { message?: string };
        };
        const parsedMessage = parsedError?.error?.message?.trim();
        if (parsedMessage) {
          stripeErrorMessage = parsedMessage;
        }
      } catch {
        // Keep raw response text when Stripe error payload is not JSON.
      }

      console.error(
        "Failed to create Stripe product for subscription checkout",
        {
          requestId,
          sessionId: session.id,
          tenantId,
          productId: product.id,
          status: stripeProductResponse.status,
          error: errorText,
        },
      );

      throw new RetryableWebhookError(
        `stripe_product_create_failed:${stripeErrorMessage}`,
      );
    }

    const stripeProduct =
      (await stripeProductResponse.json()) as StripeProductResponse;
    if (!stripeProduct.id) {
      throw new RetryableWebhookError("stripe_product_create_missing_id");
    }
    stripeProductId = stripeProduct.id;
  }

  const stripeParams = new URLSearchParams();
  stripeParams.append("customer", session.customer.trim());
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
  stripeParams.append("metadata[checkout_session_id]", session.id);
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
      "Idempotency-Key": `allia_checkout_subscription_${session.id}`,
    },
    body: stripeParams.toString(),
  });

  if (!response.ok) {
    const errorText = await response.text();
    let stripeErrorMessage = errorText;
    try {
      const parsedError = JSON.parse(errorText) as {
        error?: { message?: string };
      };
      const parsedMessage = parsedError?.error?.message?.trim();
      if (parsedMessage) {
        stripeErrorMessage = parsedMessage;
      }
    } catch {
      // Keep raw response text when Stripe error payload is not JSON.
    }

    console.error("Failed to create send_invoice Stripe subscription", {
      requestId,
      sessionId: session.id,
      tenantId,
      productId: product.id,
      status: response.status,
      error: errorText,
    });

    throw new RetryableWebhookError(
      `stripe_subscription_create_failed:${stripeErrorMessage}`,
    );
  }

  return (await response.json()) as StripeSubscriptionResponse;
}

async function resolveProviderSubscriptionIdByCheckoutSession(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  stripePaymentProviderId: string;
  providerCheckoutSessionId: string;
  requestId: string;
}): Promise<string | null> {
  const {
    supabase,
    tenantId,
    stripePaymentProviderId,
    providerCheckoutSessionId,
    requestId,
  } = params;

  const { data: existingLinks, error } = await supabase
    .from("subscription_payment_provider_links")
    .select("provider_subscription_id")
    .eq("tenant_id", tenantId)
    .eq("payment_provider_id", stripePaymentProviderId)
    .eq("provider_checkout_session_id", providerCheckoutSessionId)
    .not("provider_subscription_id", "is", null)
    .order("created_at", { ascending: false })
    .limit(1);

  if (error) {
    console.warn(
      "Failed to lookup Stripe subscription link by checkout session",
      {
        requestId,
        tenantId,
        providerCheckoutSessionId,
        error: error.message,
      },
    );
    return null;
  }

  return existingLinks?.[0]?.provider_subscription_id || null;
}

async function getOrderCreatedStatusId(
  supabase: SupabaseAdminClient,
  requestId: string,
): Promise<string> {
  const { data: orderCreatedStatus, error: orderCreatedStatusError } =
    await supabase
      .from("order_statuses")
      .select("id")
      .eq("status_key", "order_created")
      .eq("is_active", true)
      .maybeSingle();

  if (orderCreatedStatusError) {
    throw new Error(
      `Failed to fetch order_created status: ${orderCreatedStatusError.message}`,
    );
  }

  if (!orderCreatedStatus?.id) {
    throw new Error("order_created status not found");
  }

  console.info("Resolved order_created status id", {
    requestId,
    statusId: orderCreatedStatus.id,
  });

  return orderCreatedStatus.id;
}

async function handleCheckoutSessionCompleted(
  supabase: SupabaseAdminClient,
  session: CheckoutSession,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
  stripeSecretKey: string | null,
) {
  const metadata = session.metadata || {};
  const productId = metadata.product_id?.trim() || null;
  const patientIdFromMetadata =
    typeof metadata.patient_id === "string" ? metadata.patient_id.trim() : "";
  const patient_id = patientIdFromMetadata || null;
  const stripeCustomerId =
    typeof session.customer === "string" && session.customer.trim().length > 0
      ? session.customer.trim()
      : null;

  // Resolve customer email from Stripe payload when available.
  const customerEmailCandidate =
    session.customer_details?.email ||
    session.customer_email ||
    metadata.customer_email ||
    null;
  const customerEmail =
    typeof customerEmailCandidate === "string"
      ? customerEmailCandidate.trim().toLowerCase()
      : "";
  console.info("Checkout session completed - processing", {
    requestId,
    sessionId: session.id,
    patientId: patient_id,
    productId: productId || "none",
    customerEmail: customerEmail || "unknown",
    stripeCustomerId,
    paymentStatus: session.payment_status,
    mode: session.mode,
    amountTotal: session.amount_total,
    currency: session.currency,
  });

  // For manual-capture flows, checkout can complete before capture.
  // We still persist order + provider references so capture/cancel can be performed later.
  const isZeroAmountCheckout = (session.amount_total ?? null) === 0;
  const isPaymentSettled =
    session.payment_status === "paid" ||
    session.payment_status === "no_payment_required" ||
    isZeroAmountCheckout;
  if (!isPaymentSettled) {
    console.info(
      "Checkout completed with unsettled payment status; persisting pending payment context",
      {
        requestId,
        sessionId: session.id,
        paymentStatus: session.payment_status,
      },
    );
  }

  if (!patient_id) {
    console.error("Checkout session missing patient_id metadata", {
      requestId,
      sessionId: session.id,
      tenantId,
      customerEmail: customerEmail || null,
      stripeCustomerId,
    });
    throw new Error(
      "Missing patient_id in checkout session metadata. Guest checkout is not supported.",
    );
  }

  let checkoutStripeSubscriptionId: string | null =
    session.subscription || null;

  if (session.mode === "setup") {
    throw new Error(
      "Legacy Stripe setup checkout sessions are no longer supported",
    );
  } else if (session.mode === "payment") {
    // Get product for calculating totals
    let product: SubscriptionCheckoutProduct | null = null;
    if (productId) {
      console.info("Fetching product data", {
        requestId,
        productId: productId,
      });
      const { data: fetchedProduct, error: productError } = await supabase
        .from("products")
        .select(
          "id, name, description, price_cents, payment_type, subscription_interval, subscription_interval_count, image_url",
        )
        .eq("id", productId)
        .single();

      if (productError) {
        console.warn("Failed to fetch product data", {
          requestId,
          productId: productId,
          error: productError.message,
        });
      } else {
        product = fetchedProduct;
      }
    } else {
      console.warn("Checkout session is missing product_id metadata", {
        requestId,
        sessionId: session.id,
        tenantId,
      });
    }

    const subtotalCents =
      typeof product?.price_cents === "number"
        ? product.price_cents
        : session.amount_total || 0;
    const taxCents = 0; // Tax calculation would go here
    const shippingCents = 0; // Shipping calculation would go here
    const totalCents =
      session.amount_total ?? subtotalCents + taxCents + shippingCents;

    // Extract coupon/discount info from the checkout session
    const checkoutDiscountInfo = stripeSecretKey
      ? await extractDiscountFromCheckoutSession(
          session,
          stripeSecretKey,
          requestId,
        )
      : { discountCents: 0, couponCode: null, couponName: null };
    const subscriptionDiscount = stripeSecretKey
      ? await resolveSubscriptionDiscountFromCheckoutSession(
          session,
          stripeSecretKey,
          requestId,
        )
      : null;

    let createdSubscription: StripeSubscriptionResponse | null = null;
    let subscriptionRenewalAt: string | null = null;
    if (product?.payment_type === "subscription") {
      if (!stripeSecretKey) {
        throw new Error(
          "Stripe secret key is required for subscription checkout processing",
        );
      }
      let paymentMethodId: string | null = null;
      if (session.payment_intent?.trim()) {
        const paymentIntentDetails = await fetchStripePaymentIntentDetails(
          session.payment_intent.trim(),
          stripeSecretKey,
          requestId,
        );

        if (!paymentIntentDetails) {
          throw new RetryableWebhookError(
            "Failed to resolve payment intent for subscription checkout",
          );
        }

        paymentMethodId = getStripePaymentMethodId(
          paymentIntentDetails.payment_method || null,
        );
      } else if (isZeroAmountCheckout) {
        if (session.customer?.trim()) {
          paymentMethodId = await fetchStripeCustomerDefaultPaymentMethod(
            session.customer.trim(),
            stripeSecretKey,
            requestId,
          );
        }

        console.info(
          "Subscription checkout completed without payment_intent because the checkout total was zero",
          {
            requestId,
            sessionId: session.id,
            tenantId,
            paymentStatus: session.payment_status,
            amountTotal: session.amount_total ?? null,
            hasDefaultPaymentMethod: !!paymentMethodId,
          },
        );
      } else {
        throw new RetryableWebhookError(
          "Payment checkout session missing payment_intent for subscription product",
        );
      }

      if (!paymentMethodId) {
        console.warn(
          "Subscription checkout completed without a reusable payment method; creating subscription without default payment method",
          {
            requestId,
            sessionId: session.id,
            tenantId,
            paymentStatus: session.payment_status,
          },
        );
      }

      checkoutStripeSubscriptionId =
        await resolveProviderSubscriptionIdByCheckoutSession({
          supabase,
          tenantId,
          stripePaymentProviderId,
          providerCheckoutSessionId: session.id,
          requestId,
        });

      if (!checkoutStripeSubscriptionId) {
        createdSubscription = await createSendInvoiceStripeSubscription({
          session,
          tenantId,
          product,
          patientId: patient_id!,
          customerEmail: customerEmail || null,
          stripeSecretKey,
          paymentMethodId,
          subscriptionDiscount,
          requestId,
        });
      }

      if (!checkoutStripeSubscriptionId && !createdSubscription?.id) {
        throw new RetryableWebhookError(
          "Unable to create Stripe subscription from payment checkout",
        );
      }

      if (createdSubscription?.id) {
        checkoutStripeSubscriptionId = createdSubscription.id;
        subscriptionRenewalAt =
          typeof createdSubscription.current_period_end === "number"
            ? dateTime
                .unix(createdSubscription.current_period_end)
                .toISOString()
            : null;
      }
    }

    const resolvedSubscriptionId = await ensureOrderSubscription({
      supabase,
      tenantId,
      patientId: patient_id!,
      productId: productId || null,
      stripePaymentProviderId,
      requestId,
      providerCheckoutSessionId: session.id,
      providerSubscriptionId: checkoutStripeSubscriptionId,
      startedAt: dateTime().toISOString(),
      renewalAt: subscriptionRenewalAt,
      expiresAt: subscriptionRenewalAt,
    });

    if (!resolvedSubscriptionId) {
      throw new Error("Unable to link checkout order to subscription plan");
    }

    const paidAt = isPaymentSettled ? dateTime().toISOString() : null;
    console.info(
      "Checkout session completed; order creation is deferred to plan-api checkout confirmation",
      {
        requestId,
        tenantId,
        sessionId: session.id,
        patientId: patient_id,
        productId: productId || null,
        subscriptionId: resolvedSubscriptionId,
        paymentIntentId: session.payment_intent || null,
        paymentStatus: session.payment_status || null,
        subtotalCents,
        totalCents,
        discountCents: checkoutDiscountInfo.discountCents,
        rtdhDispatchAttempted: false,
        expectedRtdhDispatchPath:
          "plan-api GET /orders/checkout/{session_id} after checkout confirmation creates the order",
      },
    );

    if (paidAt) {
      await markSubscriptionAsActiveIfPendingValidation(
        supabase,
        tenantId,
        resolvedSubscriptionId,
        requestId,
        "checkout_session_completed_paid",
      );
    }
  } else {
    // Legacy checkout.session mode=subscription is intentionally unsupported.
    console.warn("Ignoring unsupported checkout session mode", {
      requestId,
      sessionId: session.id,
      tenantId,
      mode: session.mode,
    });
    return;
  }

  // Update patient metadata with Stripe customer ID (do not auto-populate addresses)
  const patientUpdate: Record<string, unknown> = {};

  // Handle Stripe customer ID and metadata
  if (session.customer) {
    console.info("Updating patient record", {
      requestId,
      patientId: patient_id,
      stripeCustomerId: session.customer || null,
      updatingFields: Object.keys(patientUpdate),
    });

    // Merge with existing metadata if we have a customer ID
    if (session.customer) {
      const { data: currentPatient } = await supabase
        .from("patients")
        .select("metadata")
        .eq("id", patient_id)
        .single();

      const existingMetadata =
        (currentPatient?.metadata as Record<string, unknown>) || {};
      patientUpdate.metadata = {
        ...existingMetadata,
        stripe_customer_id: session.customer,
      };
    }

    const { error: updateError } = await supabase
      .from("patients")
      .update(patientUpdate)
      .eq("id", patient_id);

    if (updateError) {
      console.warn("Failed to update patient record", {
        requestId,
        patientId: patient_id,
        error: updateError.message,
      });
    } else {
      console.info("Patient record updated successfully", {
        requestId,
        patientId: patient_id,
        updatedFields: Object.keys(patientUpdate),
      });
    }
  }

  // IMPORTANT: Propagate metadata to subscription so subsequent events (invoice.*) have tenant context
  if (checkoutStripeSubscriptionId && patient_id) {
    const subscriptionMetadata: Record<string, string> = {
      tenant_id: tenantId,
      patient_id: patient_id,
      checkout_session_id: session.id,
    };
    if (productId) {
      subscriptionMetadata.product_id = productId;
    }

    await propagateMetadataToSubscription(
      tenantId,
      checkoutStripeSubscriptionId,
      subscriptionMetadata,
      requestId,
    );
  }
}

async function handleCheckoutSessionExpired(
  session: CheckoutSession,
  tenantId: string,
  requestId: string,
) {
  console.info("Checkout session expired", {
    requestId,
    sessionId: session.id,
    tenantId,
    patientId: session.metadata?.patient_id,
    productId: session.metadata?.product_id,
  });
  // Could implement abandoned cart tracking here
}

async function fetchCheckoutSessionBySubscriptionId(
  subscriptionId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<CheckoutSession | null> {
  const response = await fetch(
    `https://api.stripe.com/v1/checkout/sessions?subscription=${encodeURIComponent(
      subscriptionId,
    )}&limit=1`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to fetch checkout session by subscription id", {
      requestId,
      subscriptionId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  const sessions = (await response.json()) as CheckoutSessionListResponse;
  return sessions?.data?.[0] || null;
}

async function fetchCheckoutSessionById(
  checkoutSessionId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<CheckoutSession | null> {
  const response = await fetch(
    `https://api.stripe.com/v1/checkout/sessions/${checkoutSessionId}`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to fetch checkout session by id", {
      requestId,
      checkoutSessionId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  return (await response.json()) as CheckoutSession;
}

interface PaymentIntent {
  id: string;
  status: string;
  amount: number;
  currency: string;
  customer?: string | { id?: string };
  invoice?: string | null;
  metadata: Record<string, string>;
  payment_details?: {
    order_reference?: string | null;
  } | null;
  cancellation_reason?: string | null;
  last_payment_error?: {
    message: string;
    code: string;
  };
}

async function handlePaymentIntentSucceeded(
  supabase: SupabaseAdminClient,
  paymentIntent: PaymentIntent,
  tenantId: string,
  requestId: string,
  stripeSecretKey: string | null,
  stripePaymentProviderId: string,
): Promise<void> {
  console.info("Payment intent succeeded", {
    requestId,
    paymentIntentId: paymentIntent.id,
    tenantId,
    amount: paymentIntent.amount,
    currency: paymentIntent.currency,
    status: paymentIntent.status,
  });

  const { data: existingTransaction } = await supabase
    .from("order_payment_provider_transactions")
    .select("order_id")
    .eq("payment_provider_id", stripePaymentProviderId)
    .eq("provider_payment_intent_id", paymentIntent.id)
    .maybeSingle();

  const paymentIntentInvoiceId =
    paymentIntent.invoice || paymentIntent.metadata?.invoice_id || null;
  const paymentIntentCheckoutSessionId =
    getCheckoutSessionIdFromPaymentIntentObject(paymentIntent);
  const paymentIntentSubscriptionId =
    paymentIntent.metadata?.subscription_id?.trim() || null;
  const paidAt =
    paymentIntent.status === "succeeded" ? dateTime().toISOString() : null;

  if (existingTransaction?.order_id) {
    await upsertOrderPaymentProviderTransaction({
      supabase,
      tenantId,
      orderId: existingTransaction.order_id,
      paymentProviderId: stripePaymentProviderId,
      requestId,
      providerPaymentIntentId: paymentIntent.id,
      providerInvoiceId: paymentIntentInvoiceId,
      providerSubscriptionId: paymentIntentSubscriptionId,
      providerCheckoutSessionId: paymentIntentCheckoutSessionId,
      paymentStatus: paymentIntent.status,
      paidAt,
    });

    if (paidAt) {
      const { error: paidAtUpdateError } = await supabase
        .from("orders")
        .update({ paid_at: paidAt })
        .eq("id", existingTransaction.order_id)
        .eq("tenant_id", tenantId);

      if (paidAtUpdateError) {
        console.warn(
          "Failed to update paid_at on existing payment intent transaction match",
          {
            requestId,
            paymentIntentId: paymentIntent.id,
            orderId: existingTransaction.order_id,
            error: paidAtUpdateError.message,
          },
        );
      }

      await markSubscriptionAsActiveForOrder(
        supabase,
        tenantId,
        existingTransaction.order_id,
        requestId,
        "payment_intent_succeeded_existing_transaction",
      );
    }

    console.info(
      "Updated existing order transaction for captured payment intent",
      {
        requestId,
        paymentIntentId: paymentIntent.id,
        orderId: existingTransaction.order_id,
        paymentStatus: paymentIntent.status,
      },
    );
    return;
  }

  if (paymentIntentInvoiceId || paymentIntentCheckoutSessionId) {
    let matchedOrder: {
      id: string;
      order_number: string;
    } | null = null;
    let lookupSource:
      | "provider_invoice_id"
      | "provider_checkout_session_id"
      | null = null;

    if (paymentIntentInvoiceId) {
      const { data: transactionsByInvoice, error: ordersByInvoiceError } =
        await supabase
          .from("order_payment_provider_transactions")
          .select("order_id")
          .eq("tenant_id", tenantId)
          .eq("payment_provider_id", stripePaymentProviderId)
          .eq("provider_invoice_id", paymentIntentInvoiceId)
          .limit(2);

      if (ordersByInvoiceError) {
        console.warn(
          "Failed to lookup order by provider_invoice_id for payment intent",
          {
            requestId,
            paymentIntentId: paymentIntent.id,
            invoiceId: paymentIntentInvoiceId,
            error: ordersByInvoiceError.message,
          },
        );
      } else if (transactionsByInvoice && transactionsByInvoice.length > 1) {
        console.warn(
          "Multiple orders matched provider_invoice_id; using most recent for payment intent update",
          {
            requestId,
            paymentIntentId: paymentIntent.id,
            invoiceId: paymentIntentInvoiceId,
            matchedOrderIds: transactionsByInvoice.map((row) => row.order_id),
          },
        );
        matchedOrder = transactionsByInvoice[0]?.order_id
          ? {
              id: transactionsByInvoice[0].order_id,
              order_number: transactionsByInvoice[0].order_id,
            }
          : null;
        lookupSource = "provider_invoice_id";
      } else if (
        transactionsByInvoice &&
        transactionsByInvoice.length === 1 &&
        transactionsByInvoice[0]?.order_id
      ) {
        matchedOrder = {
          id: transactionsByInvoice[0].order_id,
          order_number: transactionsByInvoice[0].order_id,
        };
        lookupSource = "provider_invoice_id";
      }
    }

    if (!matchedOrder && paymentIntentCheckoutSessionId) {
      const {
        data: transactionsByCheckoutSession,
        error: ordersByCheckoutSessionError,
      } = await supabase
        .from("order_payment_provider_transactions")
        .select("order_id")
        .eq("tenant_id", tenantId)
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_checkout_session_id", paymentIntentCheckoutSessionId)
        .limit(2);

      if (ordersByCheckoutSessionError) {
        console.warn(
          "Failed to lookup order by provider_checkout_session_id for payment intent",
          {
            requestId,
            paymentIntentId: paymentIntent.id,
            checkoutSessionId: paymentIntentCheckoutSessionId,
            error: ordersByCheckoutSessionError.message,
          },
        );
      } else if (
        transactionsByCheckoutSession &&
        transactionsByCheckoutSession.length > 1
      ) {
        console.warn(
          "Multiple orders matched provider_checkout_session_id; using most recent for payment intent update",
          {
            requestId,
            paymentIntentId: paymentIntent.id,
            checkoutSessionId: paymentIntentCheckoutSessionId,
            matchedOrderIds: transactionsByCheckoutSession.map(
              (row) => row.order_id,
            ),
          },
        );
        matchedOrder = transactionsByCheckoutSession[0]?.order_id
          ? {
              id: transactionsByCheckoutSession[0].order_id,
              order_number: transactionsByCheckoutSession[0].order_id,
            }
          : null;
        lookupSource = "provider_checkout_session_id";
      } else if (
        transactionsByCheckoutSession &&
        transactionsByCheckoutSession.length === 1 &&
        transactionsByCheckoutSession[0]?.order_id
      ) {
        matchedOrder = {
          id: transactionsByCheckoutSession[0].order_id,
          order_number: transactionsByCheckoutSession[0].order_id,
        };
        lookupSource = "provider_checkout_session_id";
      }
    }

    if (matchedOrder) {
      await upsertOrderPaymentProviderTransaction({
        supabase,
        tenantId,
        orderId: matchedOrder.id,
        paymentProviderId: stripePaymentProviderId,
        requestId,
        providerPaymentIntentId: paymentIntent.id,
        providerInvoiceId: paymentIntentInvoiceId,
        providerSubscriptionId: paymentIntentSubscriptionId,
        providerCheckoutSessionId: paymentIntentCheckoutSessionId,
        paymentStatus: paymentIntent.status,
        paidAt,
      });

      if (paidAt) {
        const { error: paidAtUpdateError } = await supabase
          .from("orders")
          .update({ paid_at: paidAt })
          .eq("id", matchedOrder.id)
          .eq("tenant_id", tenantId);

        if (paidAtUpdateError) {
          console.warn("Failed to update paid_at on matched order", {
            requestId,
            paymentIntentId: paymentIntent.id,
            orderId: matchedOrder.id,
            lookupSource,
            error: paidAtUpdateError.message,
          });
        }

        await markSubscriptionAsActiveForOrder(
          supabase,
          tenantId,
          matchedOrder.id,
          requestId,
          "payment_intent_succeeded_matched_order",
        );
      }

      console.info("Persisted payment intent on matched order transaction", {
        requestId,
        paymentIntentId: paymentIntent.id,
        orderId: matchedOrder.id,
        lookupSource,
      });
      return;
    }
  }

  if (paymentIntentSubscriptionId) {
    const persistedOrderId = await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: paymentIntentInvoiceId,
      checkoutSessionId: paymentIntentCheckoutSessionId,
      subscriptionId: paymentIntentSubscriptionId,
      customerId: getCustomerIdFromStripeObject(paymentIntent.customer),
      paymentStatus: paymentIntent.status,
      requestId,
      source: "payment_intent_succeeded_subscription_fallback",
    });

    if (persistedOrderId) {
      if (paidAt) {
        const { error: paidAtUpdateError } = await supabase
          .from("orders")
          .update({ paid_at: paidAt })
          .eq("id", persistedOrderId)
          .eq("tenant_id", tenantId);

        if (paidAtUpdateError) {
          console.warn("Failed to update paid_at on fallback matched order", {
            requestId,
            paymentIntentId: paymentIntent.id,
            orderId: persistedOrderId,
            error: paidAtUpdateError.message,
          });
        }

        await markSubscriptionAsActiveForOrder(
          supabase,
          tenantId,
          persistedOrderId,
          requestId,
          "payment_intent_succeeded_subscription_fallback",
        );
      }
      return;
    }
  }

  if (!stripeSecretKey) {
    console.warn(
      "Stripe secret key missing - cannot fetch checkout session for payment intent",
      {
        requestId,
        tenantId,
        paymentIntentId: paymentIntent.id,
      },
    );
    return;
  }

  const checkoutSessionId =
    getCheckoutSessionIdFromPaymentIntentObject(paymentIntent);
  let checkoutSession: CheckoutSession | null = null;

  if (checkoutSessionId) {
    const response = await fetch(
      `https://api.stripe.com/v1/checkout/sessions/${checkoutSessionId}`,
      {
        method: "GET",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
        },
      },
    );

    if (!response.ok) {
      const errorText = await response.text();
      console.error(
        "Failed to fetch checkout session by id for payment intent",
        {
          requestId,
          checkoutSessionId,
          paymentIntentId: paymentIntent.id,
          status: response.status,
          error: errorText,
        },
      );
      return;
    }

    checkoutSession = await response.json();
  } else {
    const response = await fetch(
      `https://api.stripe.com/v1/checkout/sessions?payment_intent=${paymentIntent.id}&limit=1`,
      {
        method: "GET",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
        },
      },
    );

    if (!response.ok) {
      const errorText = await response.text();
      console.error("Failed to fetch checkout sessions for payment intent", {
        requestId,
        paymentIntentId: paymentIntent.id,
        status: response.status,
        error: errorText,
      });
      return;
    }

    const sessions = await response.json();
    checkoutSession = sessions?.data?.[0] || null;
  }

  if (!checkoutSession) {
    console.warn(
      "No checkout session found for payment intent - skipping order creation",
      {
        requestId,
        paymentIntentId: paymentIntent.id,
      },
    );
    return;
  }

  if (checkoutSession.mode !== "payment") {
    console.info(
      "Skipping payment_intent checkout-session recovery for non-payment mode",
      {
        requestId,
        paymentIntentId: paymentIntent.id,
        checkoutSessionId: checkoutSession.id,
        checkoutMode: checkoutSession.mode,
      },
    );
    return;
  }

  try {
    await handleCheckoutSessionCompleted(
      supabase,
      checkoutSession,
      tenantId,
      requestId,
      stripePaymentProviderId,
      stripeSecretKey,
    );
  } catch (error) {
    throw new RetryableWebhookError(
      `payment_intent_checkout_recovery_failed:${
        error instanceof Error ? error.message : String(error)
      }`,
    );
  }
}

async function resolveOrderIdForPaymentIntent(
  supabase: SupabaseAdminClient,
  tenantId: string,
  stripePaymentProviderId: string,
  paymentIntentId: string,
  invoiceId: string | null,
  checkoutSessionId: string | null,
  subscriptionId: string | null,
  requestId: string,
): Promise<string | null> {
  const { data: transactionByPaymentIntent, error: byPaymentIntentError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_payment_intent_id", paymentIntentId)
      .maybeSingle();

  if (byPaymentIntentError) {
    console.warn("Failed to lookup order by provider_payment_intent_id", {
      requestId,
      tenantId,
      paymentIntentId,
      error: byPaymentIntentError.message,
    });
  } else if (transactionByPaymentIntent?.order_id) {
    return transactionByPaymentIntent.order_id;
  }

  if (invoiceId) {
    const { data: transactionsByInvoice, error: byInvoiceError } =
      await supabase
        .from("order_payment_provider_transactions")
        .select("order_id")
        .eq("tenant_id", tenantId)
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_invoice_id", invoiceId)
        .limit(2);

    if (byInvoiceError) {
      console.warn(
        "Failed to lookup order by provider_invoice_id for payment intent",
        {
          requestId,
          tenantId,
          paymentIntentId,
          invoiceId,
          error: byInvoiceError.message,
        },
      );
    } else if (
      transactionsByInvoice &&
      transactionsByInvoice.length >= 1 &&
      transactionsByInvoice[0]?.order_id
    ) {
      if (transactionsByInvoice.length > 1) {
        console.warn(
          "Multiple orders matched provider_invoice_id for payment intent; using most recent",
          {
            requestId,
            tenantId,
            paymentIntentId,
            invoiceId,
            matchedOrderIds: transactionsByInvoice.map((row) => row.order_id),
          },
        );
      }
      return transactionsByInvoice[0].order_id;
    }
  }

  if (checkoutSessionId) {
    const {
      data: transactionsByCheckoutSession,
      error: byCheckoutSessionError,
    } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", checkoutSessionId)
      .limit(2);

    if (byCheckoutSessionError) {
      console.warn(
        "Failed to lookup order by provider_checkout_session_id for payment intent",
        {
          requestId,
          tenantId,
          paymentIntentId,
          checkoutSessionId,
          error: byCheckoutSessionError.message,
        },
      );
    } else if (
      transactionsByCheckoutSession &&
      transactionsByCheckoutSession.length >= 1 &&
      transactionsByCheckoutSession[0]?.order_id
    ) {
      if (transactionsByCheckoutSession.length > 1) {
        console.warn(
          "Multiple orders matched provider_checkout_session_id for payment intent; using most recent",
          {
            requestId,
            tenantId,
            paymentIntentId,
            checkoutSessionId,
            matchedOrderIds: transactionsByCheckoutSession.map(
              (row) => row.order_id,
            ),
          },
        );
      }
      return transactionsByCheckoutSession[0].order_id;
    }
  }

  if (subscriptionId) {
    const { data: transactionsBySubscription, error: bySubscriptionError } =
      await supabase
        .from("order_payment_provider_transactions")
        .select("order_id")
        .eq("tenant_id", tenantId)
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_subscription_id", subscriptionId)
        .limit(2);

    if (bySubscriptionError) {
      console.warn(
        "Failed to lookup order by provider_subscription_id for payment intent",
        {
          requestId,
          tenantId,
          paymentIntentId,
          subscriptionId,
          error: bySubscriptionError.message,
        },
      );
    } else if (
      transactionsBySubscription &&
      transactionsBySubscription.length >= 1 &&
      transactionsBySubscription[0]?.order_id
    ) {
      if (transactionsBySubscription.length > 1) {
        console.warn(
          "Multiple orders matched provider_subscription_id for payment intent; using most recent",
          {
            requestId,
            tenantId,
            paymentIntentId,
            subscriptionId,
            matchedOrderIds: transactionsBySubscription.map(
              (row) => row.order_id,
            ),
          },
        );
      }
      return transactionsBySubscription[0].order_id;
    }
  }

  return null;
}

async function persistPaymentIntentReferenceForOrder(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  stripePaymentProviderId: string;
  paymentIntentId: string;
  invoiceId: string | null;
  checkoutSessionId: string | null;
  subscriptionId: string | null;
  customerId: string | null;
  paymentStatus: string | null;
  requestId: string;
  source: string;
}): Promise<string | null> {
  const {
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntentId,
    invoiceId,
    checkoutSessionId,
    subscriptionId,
    customerId,
    paymentStatus,
    requestId,
    source,
  } = params;

  const matchedOrderId = await resolveOrderIdForPaymentIntent(
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntentId,
    invoiceId,
    checkoutSessionId,
    subscriptionId,
    requestId,
  );

  if (!matchedOrderId) {
    console.warn(
      "No matching order found while persisting payment intent reference",
      {
        requestId,
        tenantId,
        paymentIntentId,
        invoiceId,
        checkoutSessionId,
        subscriptionId,
        source,
      },
    );
    return null;
  }

  await upsertOrderPaymentProviderTransaction({
    supabase,
    tenantId,
    orderId: matchedOrderId,
    paymentProviderId: stripePaymentProviderId,
    requestId,
    providerPaymentIntentId: paymentIntentId,
    providerInvoiceId: invoiceId,
    providerSubscriptionId: subscriptionId,
    providerCheckoutSessionId: checkoutSessionId,
    providerCustomerId: customerId,
    paymentStatus: paymentStatus || "unknown",
    paidAt: null,
  });

  console.info("Persisted payment intent reference for order", {
    requestId,
    tenantId,
    orderId: matchedOrderId,
    paymentIntentId,
    invoiceId,
    checkoutSessionId,
    subscriptionId,
    paymentStatus: paymentStatus || "unknown",
    source,
  });

  return matchedOrderId;
}

async function updateOrderStatusByKey(
  supabase: SupabaseAdminClient,
  tenantId: string,
  orderId: string,
  statusKey: string,
  requestId: string,
): Promise<void> {
  const { data: status, error: statusLookupError } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("status_key", statusKey)
    .eq("is_active", true)
    .maybeSingle();

  if (statusLookupError || !status?.id) {
    console.warn(
      "Failed to resolve order status id for payment intent update",
      {
        requestId,
        tenantId,
        orderId,
        statusKey,
        error: statusLookupError?.message || "status_not_found",
      },
    );
    return;
  }

  const { error: updateError } = await supabase
    .from("orders")
    .update({
      status_id: status.id,
      status_changed_at: dateTime().toISOString(),
    })
    .eq("id", orderId)
    .eq("tenant_id", tenantId);

  if (updateError) {
    console.warn("Failed to update order status from payment intent", {
      requestId,
      tenantId,
      orderId,
      statusKey,
      error: updateError.message,
    });
  }
}

async function getNextOrderStatus(
  supabase: SupabaseAdminClient,
  currentStatus: {
    next_status_id: string | null;
    status_key: string;
  },
  requestId: string,
): Promise<{
  id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  display_order: number;
  next_status_id: string | null;
  failure_status_id: string | null;
  next_step_owner: string;
} | null> {
  if (!currentStatus.next_status_id) {
    console.warn("No next_status_id configured for current order status", {
      requestId,
      currentStatusKey: currentStatus.status_key,
    });
    return null;
  }

  const { data: nextStatus, error: nextStatusError } = await supabase
    .from("order_statuses")
    .select(
      "id, status_key, admin_status_label, is_terminal, display_order, next_status_id, failure_status_id, next_step_owner",
    )
    .eq("id", currentStatus.next_status_id)
    .eq("is_active", true)
    .maybeSingle();

  if (nextStatusError) {
    console.warn("Failed to resolve next order status", {
      requestId,
      currentStatusKey: currentStatus.status_key,
      nextStatusId: currentStatus.next_status_id,
      error: nextStatusError.message,
    });
    return null;
  }

  return nextStatus || null;
}

async function handlePaymentIntentAmountCapturableUpdated(
  supabase: SupabaseAdminClient,
  paymentIntent: PaymentIntent,
  tenantId: string,
  requestId: string,
  stripeSecretKey: string | null,
  stripePaymentProviderId: string,
): Promise<void> {
  const paymentIntentInvoiceId =
    paymentIntent.invoice || paymentIntent.metadata?.invoice_id || null;
  const paymentIntentCheckoutSessionId =
    getCheckoutSessionIdFromPaymentIntentObject(paymentIntent);
  const paymentIntentSubscriptionId =
    paymentIntent.metadata?.subscription_id?.trim() || null;

  console.info("Payment intent became capturable", {
    requestId,
    paymentIntentId: paymentIntent.id,
    tenantId,
    status: paymentIntent.status,
    invoiceId: paymentIntentInvoiceId,
    checkoutSessionId: paymentIntentCheckoutSessionId,
  });

  const matchedOrderId = await resolveOrderIdForPaymentIntent(
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntent.id,
    paymentIntentInvoiceId,
    paymentIntentCheckoutSessionId,
    paymentIntentSubscriptionId,
    requestId,
  );

  if (matchedOrderId) {
    await upsertOrderPaymentProviderTransaction({
      supabase,
      tenantId,
      orderId: matchedOrderId,
      paymentProviderId: stripePaymentProviderId,
      requestId,
      providerPaymentIntentId: paymentIntent.id,
      providerInvoiceId: paymentIntentInvoiceId,
      providerSubscriptionId: paymentIntentSubscriptionId,
      providerCheckoutSessionId: paymentIntentCheckoutSessionId,
      paymentStatus: paymentIntent.status,
      paidAt: null,
    });
    return;
  }

  if (!paymentIntentInvoiceId || !stripeSecretKey) {
    console.warn(
      "Unable to create pending order for capturable payment intent",
      {
        requestId,
        paymentIntentId: paymentIntent.id,
        tenantId,
        hasInvoiceId: !!paymentIntentInvoiceId,
        hasStripeSecretKey: !!stripeSecretKey,
      },
    );
    return;
  }

  const invoiceResponse = await fetch(
    `https://api.stripe.com/v1/invoices/${paymentIntentInvoiceId}?expand[]=subscription`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!invoiceResponse.ok) {
    const errorText = await invoiceResponse.text();
    console.warn("Failed to fetch invoice for capturable payment intent", {
      requestId,
      tenantId,
      paymentIntentId: paymentIntent.id,
      invoiceId: paymentIntentInvoiceId,
      status: invoiceResponse.status,
      error: errorText,
    });
    return;
  }

  const invoice = (await invoiceResponse.json()) as Invoice;
  const { id: invoiceSubscriptionId } = getSubscriptionIdFromInvoice(invoice);
  const { id: invoiceCheckoutSessionId } =
    getCheckoutSessionIdFromInvoice(invoice);

  const matchedOrderIdFromInvoiceContext =
    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: paymentIntentInvoiceId,
      checkoutSessionId:
        invoiceCheckoutSessionId || paymentIntentCheckoutSessionId,
      subscriptionId: invoiceSubscriptionId || paymentIntentSubscriptionId,
      customerId: getCustomerIdFromStripeObject(paymentIntent.customer),
      paymentStatus: paymentIntent.status || "requires_capture",
      requestId,
      source: "payment_intent_amount_capturable_updated",
    });

  if (matchedOrderIdFromInvoiceContext) {
    return;
  }

  await createPendingOrderForManualCaptureInvoice({
    supabase,
    invoice,
    paymentIntent,
    tenantId,
    requestId,
    stripeSecretKey,
    stripePaymentProviderId,
  });
}

async function handlePaymentIntentFailed(
  supabase: SupabaseAdminClient,
  paymentIntent: PaymentIntent,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
): Promise<void> {
  const paymentIntentInvoiceId =
    paymentIntent.invoice || paymentIntent.metadata?.invoice_id || null;
  const paymentIntentCheckoutSessionId =
    getCheckoutSessionIdFromPaymentIntentObject(paymentIntent);
  const paymentIntentSubscriptionId =
    paymentIntent.metadata?.subscription_id?.trim() || null;

  console.error("Payment intent failed", {
    requestId,
    paymentIntentId: paymentIntent.id,
    tenantId,
    invoiceId: paymentIntentInvoiceId,
    checkoutSessionId: paymentIntentCheckoutSessionId,
    errorMessage: paymentIntent.last_payment_error?.message,
    errorCode: paymentIntent.last_payment_error?.code,
  });

  const matchedOrderId = await resolveOrderIdForPaymentIntent(
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntent.id,
    paymentIntentInvoiceId,
    paymentIntentCheckoutSessionId,
    paymentIntentSubscriptionId,
    requestId,
  );

  if (!matchedOrderId) {
    return;
  }

  await upsertOrderPaymentProviderTransaction({
    supabase,
    tenantId,
    orderId: matchedOrderId,
    paymentProviderId: stripePaymentProviderId,
    requestId,
    providerPaymentIntentId: paymentIntent.id,
    providerInvoiceId: paymentIntentInvoiceId,
    providerSubscriptionId: paymentIntentSubscriptionId,
    providerCheckoutSessionId: paymentIntentCheckoutSessionId,
    paymentStatus: paymentIntent.status || "payment_failed",
    paidAt: null,
  });

  await updateOrderStatusByKey(
    supabase,
    tenantId,
    matchedOrderId,
    "payment_failed",
    requestId,
  );

  // Track the failure timestamp and reset retry counter
  const failedAt = new Date().toISOString();
  const { error: trackError } = await supabase
    .from("orders")
    .update({
      payment_failed_at: failedAt,
      payment_retry_count: 0,
    })
    .eq("id", matchedOrderId)
    .eq("tenant_id", tenantId);

  if (trackError) {
    console.warn("Failed to set payment_failed_at on order", {
      requestId,
      tenantId,
      orderId: matchedOrderId,
      error: trackError.message,
    });
  }

  // Write an audit history note with the failure reason
  const { data: failedStatus } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("status_key", "payment_failed")
    .eq("is_active", true)
    .maybeSingle();

  if (failedStatus?.id) {
    const failureCode = paymentIntent.last_payment_error?.code || null;
    const failureMessage = paymentIntent.last_payment_error?.message || null;
    const historyNote = [
      `Payment intent ${paymentIntent.id} failed.`,
      failureCode ? `Code: ${failureCode}.` : null,
      failureMessage ? `Reason: ${failureMessage}.` : null,
    ]
      .filter(Boolean)
      .join(" ");

    const { error: historyError } = await supabase
      .from("order_status_history")
      .insert({
        order_id: matchedOrderId,
        status_id: failedStatus.id,
        notes: historyNote,
      });

    if (historyError) {
      console.warn("Failed to insert payment_failed history note", {
        requestId,
        tenantId,
        orderId: matchedOrderId,
        error: historyError.message,
      });
    }
  }
}

async function handlePaymentIntentCancelled(
  supabase: SupabaseAdminClient,
  paymentIntent: PaymentIntent,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
): Promise<void> {
  const paymentIntentInvoiceId =
    paymentIntent.invoice || paymentIntent.metadata?.invoice_id || null;
  const paymentIntentCheckoutSessionId =
    getCheckoutSessionIdFromPaymentIntentObject(paymentIntent);
  const paymentIntentSubscriptionId =
    paymentIntent.metadata?.subscription_id?.trim() || null;

  console.info("Payment intent cancelled", {
    requestId,
    paymentIntentId: paymentIntent.id,
    tenantId,
    status: paymentIntent.status,
    cancellationReason: paymentIntent.cancellation_reason || null,
    invoiceId: paymentIntentInvoiceId,
    checkoutSessionId: paymentIntentCheckoutSessionId,
  });

  const matchedOrderId = await resolveOrderIdForPaymentIntent(
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntent.id,
    paymentIntentInvoiceId,
    paymentIntentCheckoutSessionId,
    paymentIntentSubscriptionId,
    requestId,
  );

  if (!matchedOrderId) {
    return;
  }

  await upsertOrderPaymentProviderTransaction({
    supabase,
    tenantId,
    orderId: matchedOrderId,
    paymentProviderId: stripePaymentProviderId,
    requestId,
    providerPaymentIntentId: paymentIntent.id,
    providerInvoiceId: paymentIntentInvoiceId,
    providerSubscriptionId: paymentIntentSubscriptionId,
    providerCheckoutSessionId: paymentIntentCheckoutSessionId,
    paymentStatus: paymentIntent.status || "cancelled",
    paidAt: null,
  });
}

interface Subscription {
  id: string;
  customer: string;
  status: string;
  current_period_start: number;
  current_period_end: number;
  cancel_at_period_end: boolean;
  cancelled_at?: number;
  metadata: Record<string, string>;
  items: {
    data: Array<{
      id: string;
      price: {
        id: string;
        product: string;
      };
    }>;
  };
}

async function handleSubscriptionCreated(
  supabase: SupabaseAdminClient,
  subscription: Subscription,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
): Promise<void> {
  // Safely convert Unix timestamps to ISO strings
  const currentPeriodStart = subscription.current_period_start
    ? dateTime.unix(subscription.current_period_start).toISOString()
    : null;
  const currentPeriodEnd = subscription.current_period_end
    ? dateTime.unix(subscription.current_period_end).toISOString()
    : null;

  console.info("Subscription created", {
    requestId,
    subscriptionId: subscription.id,
    tenantId,
    status: subscription.status,
    customerId: subscription.customer,
    currentPeriodStart,
    currentPeriodEnd,
  });

  const metadataPatientId =
    typeof subscription.metadata?.patient_id === "string"
      ? subscription.metadata.patient_id.trim()
      : "";
  const metadataProductId =
    typeof subscription.metadata?.product_id === "string"
      ? subscription.metadata.product_id.trim()
      : "";
  const metadataCheckoutSessionId =
    typeof subscription.metadata?.checkout_session_id === "string"
      ? subscription.metadata.checkout_session_id.trim()
      : "";

  if (metadataPatientId) {
    await ensureOrderSubscription({
      supabase,
      tenantId,
      patientId: metadataPatientId,
      productId: metadataProductId || null,
      stripePaymentProviderId,
      requestId,
      providerSubscriptionId: subscription.id,
      providerCheckoutSessionId: metadataCheckoutSessionId || null,
      startedAt: currentPeriodStart,
      renewalAt: currentPeriodEnd,
      expiresAt: currentPeriodEnd,
    });
  }

  await updateOrderRenewalDateFromSubscription(
    supabase,
    subscription,
    tenantId,
    requestId,
    "created",
    stripePaymentProviderId,
  );

  if (metadataCheckoutSessionId) {
    // Temporary RTDH webhook development bridge:
    // advance the matched checkout-created order when Stripe creates the subscription.
    await advanceMatchedOrderForSubscriptionCreated(
      supabase,
      tenantId,
      requestId,
      stripePaymentProviderId,
      subscription.id,
      metadataCheckoutSessionId,
    );
  }
}

async function advanceMatchedOrderForSubscriptionCreated(
  supabase: SupabaseAdminClient,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
  stripeSubscriptionId: string,
  checkoutSessionId: string,
): Promise<void> {
  const { data: matchedTransactions, error: transactionLookupError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select(
        "order_id, orders!inner(id, order_number, status_id, order_statuses!inner(id, status_key, admin_status_label, next_status_id, is_terminal, display_order, failure_status_id, next_step_owner))",
      )
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", checkoutSessionId)
      .order("created_at", { ascending: false })
      .limit(2);

  if (transactionLookupError) {
    console.warn(
      "Failed to lookup order by provider_checkout_session_id for subscription creation",
      {
        requestId,
        tenantId,
        checkoutSessionId,
        stripeSubscriptionId,
        error: transactionLookupError.message,
      },
    );
    return;
  }

  if (matchedTransactions && matchedTransactions.length > 1) {
    console.warn(
      "Multiple orders matched provider_checkout_session_id for subscription creation; using most recent",
      {
        requestId,
        tenantId,
        checkoutSessionId,
        stripeSubscriptionId,
        matchedOrderIds: matchedTransactions.map((row) => row.order_id),
      },
    );
  }

  const matchedTransaction = matchedTransactions?.[0];

  if (!matchedTransaction?.order_id) {
    console.info(
      "No existing order matched checkout session for customer.subscription.created",
      {
        requestId,
        tenantId,
        checkoutSessionId,
        stripeSubscriptionId,
      },
    );
    return;
  }

  const { data: matchedOrder, error: matchedOrderError } = await supabase
    .from("orders")
    .select(
      "id, order_number, status_id, order_statuses!inner(id, status_key, admin_status_label, next_status_id, is_terminal, display_order, failure_status_id, next_step_owner)",
    )
    .eq("id", matchedTransaction.order_id)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (matchedOrderError || !matchedOrder?.id) {
    console.warn(
      "Failed to load matched order for customer.subscription.created",
      {
        requestId,
        tenantId,
        orderId: matchedTransaction.order_id,
        checkoutSessionId,
        stripeSubscriptionId,
        error: matchedOrderError?.message || "order_not_found",
      },
    );
    return;
  }

  const currentStatus = Array.isArray(matchedOrder.order_statuses)
    ? matchedOrder.order_statuses[0] || null
    : matchedOrder.order_statuses;
  if (!currentStatus) {
    console.warn(
      "Matched order is missing current status for subscription creation advance",
      {
        requestId,
        tenantId,
        orderId: matchedOrder.id,
        checkoutSessionId,
        stripeSubscriptionId,
      },
    );
    return;
  }

  if (currentStatus.status_key !== "order_created") {
    console.info(
      "Matched order is not in order_created; skipping subscription creation advance",
      {
        requestId,
        tenantId,
        orderId: matchedOrder.id,
        orderNumber: matchedOrder.order_number,
        currentStatusKey: currentStatus.status_key,
        checkoutSessionId,
        stripeSubscriptionId,
      },
    );
    return;
  }

  const nextStatus = await getNextOrderStatus(
    supabase,
    currentStatus,
    requestId,
  );
  if (!nextStatus) {
    console.warn(
      "No next status configured for matched order on customer.subscription.created",
      {
        requestId,
        tenantId,
        orderId: matchedOrder.id,
        orderNumber: matchedOrder.order_number,
        currentStatusKey: currentStatus.status_key,
        checkoutSessionId,
        stripeSubscriptionId,
      },
    );
    return;
  }

  const { error: updateError } = await supabase
    .from("orders")
    .update({
      status_id: nextStatus.id,
      status_changed_at: dateTime().toISOString(),
    })
    .eq("id", matchedOrder.id)
    .eq("tenant_id", tenantId)
    .eq("status_id", matchedOrder.status_id);

  if (updateError) {
    console.warn(
      "Failed to advance matched order on customer.subscription.created",
      {
        requestId,
        tenantId,
        orderId: matchedOrder.id,
        orderNumber: matchedOrder.order_number,
        currentStatusKey: currentStatus.status_key,
        nextStatusKey: nextStatus.status_key,
        checkoutSessionId,
        stripeSubscriptionId,
        error: updateError.message,
      },
    );
    return;
  }

  await supabase.from("order_status_history").insert({
    order_id: matchedOrder.id,
    status_id: nextStatus.id,
    notes: `Advanced from customer.subscription.created via Stripe checkout session ${checkoutSessionId}`,
  });

  console.info(
    "Advanced matched order to next status on customer.subscription.created",
    {
      requestId,
      tenantId,
      orderId: matchedOrder.id,
      orderNumber: matchedOrder.order_number,
      previousStatusKey: currentStatus.status_key,
      nextStatusKey: nextStatus.status_key,
      checkoutSessionId,
      stripeSubscriptionId,
    },
  );

  await triggerOrderLifecycle(matchedOrder.id, tenantId, requestId);
}

async function handleSubscriptionUpdated(
  supabase: SupabaseAdminClient,
  subscription: Subscription,
  tenantId: string,
  requestId: string,
): Promise<void> {
  // Safely convert Unix timestamps to ISO strings
  const currentPeriodEnd = subscription.current_period_end
    ? dateTime.unix(subscription.current_period_end).toISOString()
    : null;

  console.info("Subscription updated", {
    requestId,
    subscriptionId: subscription.id,
    tenantId,
    status: subscription.status,
    cancelAtPeriodEnd: subscription.cancel_at_period_end,
    currentPeriodEnd,
  });
  console.info(
    "Skipping expiration date sync for customer.subscription.updated event",
    {
      requestId,
      subscriptionId: subscription.id,
      tenantId,
    },
  );

  // ── Reminder lifecycle: re-enable reminders when subscription becomes active again ──
  if (subscription.status === "active") {
    await reactivateSubscriptionLinkedReminders(
      supabase,
      subscription.id,
      tenantId,
      requestId,
    );
  }

  // ── Reminder lifecycle: disable reminders when subscription is cancelled/past_due ──
  if (
    subscription.status === "canceled" ||
    subscription.status === "past_due"
  ) {
    await disableSubscriptionLinkedReminders(
      supabase,
      subscription.id,
      tenantId,
      requestId,
    );
  }
}

/**
 * Disable all subscription-linked reminders and cancel their pending OneSignal notifications.
 * Only affects reminders with subscription_linked = true.
 */
async function disableSubscriptionLinkedReminders(
  supabase: SupabaseAdminClient,
  stripeSubscriptionId: string,
  tenantId: string,
  requestId: string,
): Promise<void> {
  // Resolve internal subscription UUIDs from the Stripe subscription ID
  const { data: links } = await supabase
    .from("subscription_payment_provider_links")
    .select("subscription_id")
    .eq("tenant_id", tenantId)
    .eq("provider_subscription_id", stripeSubscriptionId);

  const subscriptionIds = (links ?? []).map(
    (l: { subscription_id: string }) => l.subscription_id,
  );
  if (subscriptionIds.length === 0) return;

  // Find subscription-linked reminders for these subscriptions
  const { data: reminders } = await supabase
    .from("patient_reminders")
    .select("id, tenant_id")
    .in("subscription_id", subscriptionIds)
    .eq("subscription_linked", true)
    .eq("is_enabled", true)
    .is("deleted_at", null);

  if (!reminders?.length) return;

  const osConfig = await getOneSignalConfig(supabase, tenantId);
  const now = new Date().toISOString();

  for (const reminder of reminders) {
    try {
      // Cancel pending OneSignal notifications
      const { data: pending } = await supabase
        .from("patient_reminder_notifications")
        .select("id, onesignal_notification_id")
        .eq("reminder_id", reminder.id)
        .eq("status", "scheduled")
        .gte("scheduled_for", now);

      if (pending?.length && osConfig) {
        await Promise.all(
          pending.map(
            async (row: { id: string; onesignal_notification_id: string }) => {
              await cancelNotification(row.onesignal_notification_id, osConfig);
              await supabase
                .from("patient_reminder_notifications")
                .update({ status: "cancelled" })
                .eq("id", row.id);
            },
          ),
        );
      }

      // Disable the reminder
      await supabase
        .from("patient_reminders")
        .update({ is_enabled: false, disabled_reason: "subscription_expired" })
        .eq("id", reminder.id);

      console.info(
        "reminder-lifecycle: disabled subscription-linked reminder",
        {
          requestId,
          reminderId: reminder.id,
          stripeSubscriptionId,
        },
      );
    } catch (err) {
      console.error("reminder-lifecycle: failed to disable reminder", {
        requestId,
        reminderId: reminder.id,
        error: err instanceof Error ? err.message : String(err),
      });
    }
  }
}

/**
 * Re-enable subscription-linked reminders that were disabled due to subscription expiry,
 * and reschedule their OneSignal push notifications.
 */
async function reactivateSubscriptionLinkedReminders(
  supabase: SupabaseAdminClient,
  stripeSubscriptionId: string,
  tenantId: string,
  requestId: string,
): Promise<void> {
  const { data: links } = await supabase
    .from("subscription_payment_provider_links")
    .select("subscription_id")
    .eq("tenant_id", tenantId)
    .eq("provider_subscription_id", stripeSubscriptionId);

  const subscriptionIds = (links ?? []).map(
    (l: { subscription_id: string }) => l.subscription_id,
  );
  if (subscriptionIds.length === 0) return;

  const { data: reminders } = await supabase
    .from("patient_reminders")
    .select(
      "id, patient_id, tenant_id, title, frequency, repeat_days, time_local, timezone",
    )
    .in("subscription_id", subscriptionIds)
    .eq("subscription_linked", true)
    .eq("is_enabled", false)
    .eq("disabled_reason", "subscription_expired")
    .is("deleted_at", null);

  if (!reminders?.length) return;

  const osConfig = await getOneSignalConfig(supabase, tenantId);
  const now = new Date();

  for (const reminder of reminders) {
    try {
      await supabase
        .from("patient_reminders")
        .update({ is_enabled: true, disabled_reason: null })
        .eq("id", reminder.id);

      // Reschedule next 30 days of occurrences
      if (osConfig) {
        const occurrences = calculateOccurrences(
          {
            frequency: reminder.frequency as "daily" | "weekly",
            repeat_days: reminder.repeat_days,
            time_local: reminder.time_local,
            timezone: reminder.timezone,
          },
          now,
          30,
        );

        for (const fireAt of occurrences) {
          const dateKey = fireAt.toISOString().slice(0, 10);
          const idempotencyKey = `${reminder.id}:${dateKey}`;

          const notificationId = await scheduleNotification(
            reminder.patient_id,
            reminder.title,
            `Time for your ${reminder.title} reminder`,
            fireAt,
            osConfig,
            idempotencyKey,
          );

          if (notificationId) {
            await supabase.from("patient_reminder_notifications").insert({
              reminder_id: reminder.id,
              onesignal_notification_id: notificationId,
              scheduled_for: fireAt.toISOString(),
              status: "scheduled",
            });
          }
        }
      }

      console.info(
        "reminder-lifecycle: reactivated subscription-linked reminder",
        {
          requestId,
          reminderId: reminder.id,
          stripeSubscriptionId,
        },
      );
    } catch (err) {
      console.error("reminder-lifecycle: failed to reactivate reminder", {
        requestId,
        reminderId: reminder.id,
        error: err instanceof Error ? err.message : String(err),
      });
    }
  }
}

async function handleSubscriptionDeleted(
  supabase: SupabaseAdminClient,
  subscription: Subscription,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
): Promise<void> {
  const cancelledAt = subscription.cancelled_at
    ? dateTime.unix(subscription.cancelled_at).toISOString()
    : dateTime().toISOString();

  console.info("Subscription deleted", {
    requestId,
    subscriptionId: subscription.id,
    tenantId,
    status: subscription.status,
    cancelledAt: cancelledAt,
  });

  // ── Reminder lifecycle: disable subscription-linked reminders ──────────
  await disableSubscriptionLinkedReminders(
    supabase,
    subscription.id,
    tenantId,
    requestId,
  );

  const { data: linkedSubscriptions, error: linkLookupError } = await supabase
    .from("subscription_payment_provider_links")
    .select("subscription_id")
    .eq("tenant_id", tenantId)
    .eq("payment_provider_id", stripePaymentProviderId)
    .eq("provider_subscription_id", subscription.id);

  if (linkLookupError) {
    console.warn(
      "Failed to lookup linked subscription ids for Stripe subscription deletion",
      {
        requestId,
        tenantId,
        stripeSubscriptionId: subscription.id,
        error: linkLookupError.message,
      },
    );
  }

  const linkedSubscriptionIds = (linkedSubscriptions || [])
    .map((row) => row.subscription_id)
    .filter((id): id is string => typeof id === "string");

  if (linkedSubscriptionIds.length > 0) {
    const { error: updateByLinkError } = await supabase
      .from("subscriptions")
      .update({
        status: "cancelled",
        cancelled_at: cancelledAt,
      })
      .eq("tenant_id", tenantId)
      .in("id", linkedSubscriptionIds);

    if (updateByLinkError) {
      console.warn(
        "Failed to mark linked subscriptions as cancelled from Stripe deletion",
        {
          requestId,
          tenantId,
          stripeSubscriptionId: subscription.id,
          linkedSubscriptionIds,
          error: updateByLinkError.message,
        },
      );
    }
    return;
  }
}

async function updateOrderRenewalDateFromSubscription(
  supabase: SupabaseAdminClient,
  subscription: Subscription,
  tenantId: string,
  requestId: string,
  eventType: "created" | "updated",
  stripePaymentProviderId: string,
): Promise<void> {
  const expirationAt = subscription.current_period_end
    ? dateTime.unix(subscription.current_period_end).toISOString()
    : null;

  if (!expirationAt) {
    console.warn(
      "Subscription missing current_period_end; skipping order renewal update",
      {
        requestId,
        subscriptionId: subscription.id,
        tenantId,
        eventType,
      },
    );
    return;
  }

  const subscriptionId = subscription.id;
  const checkoutSessionId = subscription.metadata?.checkout_session_id;

  let matchedOrder: {
    id: string;
    order_number: string;
    product_id: string | null;
    subscription_id: string | null;
  } | null = null;
  let lookupSource:
    | "subscription_payment_provider_links.provider_subscription_id"
    | "subscription_payment_provider_links.provider_checkout_session_id"
    | null = null;

  const { data: linksBySubscription, error: subscriptionLookupError } =
    await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_subscription_id", subscriptionId)
      .limit(2);

  if (subscriptionLookupError) {
    console.error(
      "Error finding order by subscription_payment_provider_links.provider_subscription_id for renewal update",
      {
        requestId,
        subscriptionId,
        tenantId,
        error: subscriptionLookupError.message,
      },
    );
    return;
  }

  if (linksBySubscription && linksBySubscription.length > 1) {
    console.warn(
      "Multiple subscriptions matched provider_subscription_id; using most recent linked order for renewal update",
      {
        requestId,
        subscriptionId,
        tenantId,
        matchedSubscriptionIds: linksBySubscription.map(
          (row) => row.subscription_id,
        ),
      },
    );
  }

  if (linksBySubscription && linksBySubscription.length >= 1) {
    const matchedSubscriptionId =
      linksBySubscription[0]?.subscription_id || null;
    if (matchedSubscriptionId) {
      const { data: ordersBySubscription, error: ordersBySubscriptionError } =
        await supabase
          .from("orders")
          .select("id, order_number, product_id, subscription_id, created_at")
          .eq("tenant_id", tenantId)
          .eq("subscription_id", matchedSubscriptionId)
          .order("created_at", { ascending: false })
          .limit(2);

      if (ordersBySubscriptionError) {
        console.error(
          "Error finding order by subscription_id for renewal update",
          {
            requestId,
            subscriptionId: matchedSubscriptionId,
            tenantId,
            error: ordersBySubscriptionError.message,
          },
        );
        return;
      }

      if (ordersBySubscription && ordersBySubscription.length > 1) {
        console.warn(
          "Multiple orders matched subscription_id; using most recent for renewal update",
          {
            requestId,
            subscriptionId: matchedSubscriptionId,
            tenantId,
            matchedOrderIds: ordersBySubscription.map((order) => order.id),
          },
        );
      }

      if (ordersBySubscription && ordersBySubscription.length >= 1) {
        matchedOrder = ordersBySubscription[0];
        lookupSource =
          "subscription_payment_provider_links.provider_subscription_id";
      }
    }
  }

  if (!matchedOrder && checkoutSessionId) {
    const { data: linksByCheckoutSession, error: sessionLookupError } =
      await supabase
        .from("subscription_payment_provider_links")
        .select("subscription_id")
        .eq("tenant_id", tenantId)
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_checkout_session_id", checkoutSessionId)
        .limit(2);

    if (sessionLookupError) {
      console.error(
        "Error finding subscription by provider_checkout_session_id for renewal update",
        {
          requestId,
          checkoutSessionId,
          tenantId,
          error: sessionLookupError.message,
        },
      );
      return;
    }

    if (linksByCheckoutSession && linksByCheckoutSession.length > 1) {
      console.warn(
        "Multiple subscriptions matched provider_checkout_session_id; using most recent linked order for renewal update",
        {
          requestId,
          checkoutSessionId,
          tenantId,
          matchedSubscriptionIds: linksByCheckoutSession.map(
            (row) => row.subscription_id,
          ),
        },
      );
    }

    const matchedSubscriptionId =
      linksByCheckoutSession?.[0]?.subscription_id || null;
    if (matchedSubscriptionId) {
      const { data: ordersBySession, error: ordersBySessionError } =
        await supabase
          .from("orders")
          .select("id, order_number, product_id, subscription_id, created_at")
          .eq("tenant_id", tenantId)
          .eq("subscription_id", matchedSubscriptionId)
          .order("created_at", { ascending: false })
          .limit(2);

      if (ordersBySessionError) {
        console.error(
          "Error finding order by subscription_id (from checkout session link) for renewal update",
          {
            requestId,
            checkoutSessionId,
            subscriptionId: matchedSubscriptionId,
            tenantId,
            error: ordersBySessionError.message,
          },
        );
        return;
      }

      if (ordersBySession && ordersBySession.length > 1) {
        console.warn(
          "Multiple orders matched subscription_id from checkout session link; using most recent for renewal update",
          {
            requestId,
            checkoutSessionId,
            subscriptionId: matchedSubscriptionId,
            tenantId,
            matchedOrderIds: ordersBySession.map((order) => order.id),
          },
        );
      }

      if (ordersBySession && ordersBySession.length >= 1) {
        matchedOrder = ordersBySession[0];
        lookupSource =
          "subscription_payment_provider_links.provider_checkout_session_id";
      }
    }
  }

  if (!matchedOrder || !matchedOrder.subscription_id) {
    console.warn("No matching order found for subscription renewal update", {
      requestId,
      subscriptionId,
      tenantId,
      checkoutSessionId: checkoutSessionId || null,
      matchedOrderId: matchedOrder?.id || null,
      eventType,
    });
    return;
  }

  const { error: updateSubscriptionError } = await supabase
    .from("subscriptions")
    .update({
      expires_at: expirationAt,
    })
    .eq("id", matchedOrder.subscription_id);

  if (updateSubscriptionError) {
    console.error("Failed to update subscription lifecycle dates", {
      requestId,
      subscriptionRecordId: matchedOrder.subscription_id,
      orderId: matchedOrder.id,
      orderNumber: matchedOrder.order_number,
      expirationAt,
      lookupSource,
      error: updateSubscriptionError.message,
    });
    return;
  }

  console.info("Subscription expiration date updated", {
    requestId,
    subscriptionRecordId: matchedOrder.subscription_id,
    orderId: matchedOrder.id,
    orderNumber: matchedOrder.order_number,
    expirationAt,
    lookupSource,
    eventType,
  });
}

interface Invoice {
  id: string;
  customer: string | { id?: string };
  customer_email?: string;
  customer_name?: string;
  amount_due: number;
  amount_remaining?: number;
  subtotal?: number;
  total_discount_amounts?: Array<{ amount: number }> | null;
  discount?: {
    coupon?: { name?: string | null };
    promotion_code?: string | { id?: string; code?: string } | null;
  } | null;
  subscription?: string | { id?: string; metadata?: Record<string, string> };
  status: string;
  auto_advance?: boolean;
  collection_method?: string;
  default_payment_method?: string | { id?: string } | null;
  amount_paid: number;
  currency: string;
  billing_reason?: string;
  metadata: Record<string, string>;
  subscription_details?: {
    metadata?: Record<string, string>;
  };
  parent?: {
    subscription_details?: {
      metadata?: Record<string, string>;
      subscription?: string;
    };
  };
  payment_intent?: string | { id?: string } | null;
  charge?: string;
  lines: {
    data: Array<{
      description: string;
      amount: number;
      metadata?: Record<string, string>;
      period?: {
        start?: number;
        end?: number;
      };
      parent?: {
        subscription_item_details?: {
          subscription?: string;
        };
      };
      price?: {
        product?: string;
        metadata?: Record<string, string>;
      };
      pricing?: {
        price_details?: {
          product?: string;
          price?: string;
        };
      };
      subscription_item?: string;
    }>;
  };
}

function getExpirationAtFromInvoice(invoice: Invoice): string | null {
  const periodEnds = invoice.lines?.data
    ?.map((line) => line.period?.end)
    .filter((value): value is number => typeof value === "number");

  if (!periodEnds || periodEnds.length === 0) return null;

  const latestPeriodEnd = Math.max(...periodEnds);
  return dateTime.unix(latestPeriodEnd).toISOString();
}

const ORDER_CREATING_INVOICE_BILLING_REASONS = new Set([
  "subscription_create",
  "subscription_cycle",
  "subscription_threshold",
]);

const MANUAL_CAPTURE_INVOICE_BILLING_REASONS = new Set([
  "subscription_create",
  "subscription_cycle",
  "subscription_threshold",
]);

interface StripeInvoiceManualCapture {
  id: string;
  status?: string;
  auto_advance?: boolean;
  billing_reason?: string;
  amount_due?: number;
  amount_remaining?: number;
  currency?: string;
  customer?: string | { id?: string };
  default_payment_method?: string | { id?: string } | null;
  collection_method?: string;
  metadata?: Record<string, string>;
  subscription?:
    | string
    | {
        id?: string;
        default_payment_method?: string | { id?: string } | null;
      };
  subscription_details?: {
    metadata?: Record<string, string>;
  };
  parent?: {
    subscription_details?: {
      metadata?: Record<string, string>;
      subscription?: string;
    };
  };
  payment_intent?:
    | string
    | {
        id?: string;
        status?: string;
        capture_method?: string;
      }
    | null;
}

function getPaymentIntentIdFromInvoice(
  invoice: StripeInvoiceManualCapture | Invoice,
): string | null {
  const paymentIntent = invoice.payment_intent;
  if (typeof paymentIntent === "string" && paymentIntent.trim().length > 0) {
    return paymentIntent.trim();
  }
  if (
    paymentIntent &&
    typeof paymentIntent === "object" &&
    typeof paymentIntent.id === "string" &&
    paymentIntent.id.trim().length > 0
  ) {
    return paymentIntent.id.trim();
  }
  return null;
}

interface StripePaymentIntentDetails {
  id: string;
  status: string;
  capture_method?: string;
  customer?: string | { id?: string };
  payment_method?: string | { id?: string } | null;
  metadata?: Record<string, string>;
}

interface StripeInvoiceExpandedForManualCapture extends StripeInvoiceManualCapture {
  customer?:
    | string
    | {
        id?: string;
        invoice_settings?: {
          default_payment_method?: string | { id?: string } | null;
        };
      };
  subscription?:
    | string
    | {
        id?: string;
        default_payment_method?: string | { id?: string } | null;
      };
}

async function updateStripeInvoiceAutoAdvance(
  invoiceId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripeInvoiceManualCapture | null> {
  const params = new URLSearchParams();
  params.append("auto_advance", "false");

  const response = await fetch(
    `https://api.stripe.com/v1/invoices/${invoiceId}`,
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
    console.warn(
      "Failed to disable invoice auto_advance for recurring manual capture",
      {
        requestId,
        invoiceId,
        status: response.status,
        error: errorText,
      },
    );
    return null;
  }

  return (await response.json()) as StripeInvoiceManualCapture;
}

async function finalizeInvoiceForManualCapture(
  invoiceId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripeInvoiceManualCapture | null> {
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
    const errorText = await response.text();
    console.warn("Failed to finalize invoice for recurring manual capture", {
      requestId,
      invoiceId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  return (await response.json()) as StripeInvoiceManualCapture;
}

interface StripeDiscountInfo {
  discountCents: number;
  couponCode: string | null;
  couponName: string | null;
}

/**
 * Resolves a Stripe promotion_code field, which may be an ID string or an
 * already-expanded object, into a human-readable promo code string.
 */
async function resolvePromotionCode(
  promotionCode: string | { id?: string; code?: string } | null | undefined,
  stripeSecretKey: string,
  requestId: string,
): Promise<string | null> {
  if (!promotionCode) return null;

  // Already expanded object with a code
  if (typeof promotionCode === "object" && promotionCode.code) {
    return promotionCode.code;
  }

  const promoCodeId =
    typeof promotionCode === "string" ? promotionCode : promotionCode.id;

  if (!promoCodeId) return null;

  const response = await fetch(
    `https://api.stripe.com/v1/promotion_codes/${promoCodeId}`,
    {
      method: "GET",
      headers: { Authorization: `Bearer ${stripeSecretKey}` },
    },
  );

  if (!response.ok) {
    console.warn("Failed to retrieve promotion code from Stripe", {
      requestId,
      promoCodeId,
      status: response.status,
    });
    return null;
  }

  const data = (await response.json()) as { code?: string };
  return data.code || null;
}

async function resolveSubscriptionDiscountFromCheckoutSession(
  session: CheckoutSession,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripeSubscriptionDiscount | null> {
  if ((session.total_details?.amount_discount || 0) <= 0) {
    return null;
  }

  const firstDiscount = session.discounts?.[0];
  if (!firstDiscount) return null;

  const directCouponId = getStripeObjectId(firstDiscount.coupon);
  const promotionCodeId = getStripeObjectId(firstDiscount.promotion_code);
  if (directCouponId) {
    return { couponId: directCouponId, promotionCodeId };
  }
  if (!promotionCodeId) return null;

  const response = await fetch(
    `https://api.stripe.com/v1/promotion_codes/${promotionCodeId}?expand[]=coupon`,
    {
      method: "GET",
      headers: { Authorization: `Bearer ${stripeSecretKey}` },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn(
      "Failed to retrieve promotion code coupon for Stripe subscription",
      {
        requestId,
        promotionCodeId,
        status: response.status,
        error: errorText,
      },
    );
    return { couponId: null, promotionCodeId };
  }

  const data = (await response.json()) as {
    coupon?: string | { id?: string } | null;
  };
  return {
    couponId: getStripeObjectId(data.coupon),
    promotionCodeId,
  };
}

/** Extracts discount info from a Stripe checkout session. */
async function extractDiscountFromCheckoutSession(
  session: CheckoutSession,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripeDiscountInfo> {
  const discountCents = session.total_details?.amount_discount || 0;
  if (discountCents === 0) {
    return { discountCents: 0, couponCode: null, couponName: null };
  }

  const firstDiscount = session.discounts?.[0];
  const couponCode = await resolvePromotionCode(
    firstDiscount?.promotion_code,
    stripeSecretKey,
    requestId,
  );

  let couponName: string | null = null;
  if (firstDiscount?.coupon) {
    couponName =
      typeof firstDiscount.coupon === "object"
        ? (firstDiscount.coupon.name ?? null)
        : null;
  }

  return { discountCents, couponCode, couponName };
}

/** Extracts discount info from a Stripe invoice. */
async function extractDiscountFromInvoice(
  invoice: Invoice | StripeInvoiceManualCapture,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripeDiscountInfo> {
  const invoiceWithDiscount = invoice as Invoice;
  const discountCents =
    invoiceWithDiscount.total_discount_amounts?.[0]?.amount || 0;
  if (discountCents === 0) {
    return { discountCents: 0, couponCode: null, couponName: null };
  }

  const discount = invoiceWithDiscount.discount;
  const couponName = discount?.coupon?.name ?? null;
  const couponCode = await resolvePromotionCode(
    discount?.promotion_code,
    stripeSecretKey,
    requestId,
  );

  return { discountCents, couponCode, couponName };
}

async function fetchStripePaymentIntentDetails(
  paymentIntentId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripePaymentIntentDetails | null> {
  const response = await fetch(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}`,
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
      "Failed to retrieve payment intent while preparing recurring manual capture",
      {
        requestId,
        paymentIntentId,
        status: response.status,
        error: errorText,
      },
    );
    return null;
  }

  return (await response.json()) as StripePaymentIntentDetails;
}

async function fetchStripeCustomerDefaultPaymentMethod(
  customerId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<string | null> {
  const response = await fetch(
    "https://api.stripe.com/v1/customers/" +
      `${customerId}` +
      "?expand[]=invoice_settings.default_payment_method",
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to fetch Stripe customer default payment method", {
      requestId,
      customerId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  const customer = (await response.json()) as {
    invoice_settings?: {
      default_payment_method?: string | { id?: string } | null;
    };
  };

  return getStripePaymentMethodId(
    customer.invoice_settings?.default_payment_method || null,
  );
}

function getCustomerIdFromStripeObject(
  customer: string | { id?: string } | null | undefined,
): string | null {
  if (typeof customer === "string" && customer.trim()) {
    return customer.trim();
  }
  if (
    customer &&
    typeof customer === "object" &&
    typeof customer.id === "string" &&
    customer.id.trim()
  ) {
    return customer.id.trim();
  }
  return null;
}

function getCustomerIdFromInvoice(
  invoice:
    | StripeInvoiceManualCapture
    | Invoice
    | StripeInvoiceExpandedForManualCapture,
): string | null {
  return getCustomerIdFromStripeObject(invoice.customer);
}

function getDefaultPaymentMethodIdFromInvoice(
  invoice: StripeInvoiceExpandedForManualCapture,
): string | null {
  const invoiceDefaultPaymentMethod = getStripePaymentMethodId(
    invoice.default_payment_method || null,
  );
  if (invoiceDefaultPaymentMethod) return invoiceDefaultPaymentMethod;

  if (invoice.subscription && typeof invoice.subscription === "object") {
    const subscriptionDefaultPaymentMethod = getStripePaymentMethodId(
      invoice.subscription.default_payment_method || null,
    );
    if (subscriptionDefaultPaymentMethod) {
      return subscriptionDefaultPaymentMethod;
    }
  }

  if (invoice.customer && typeof invoice.customer === "object") {
    const customerDefaultPaymentMethod = getStripePaymentMethodId(
      invoice.customer.invoice_settings?.default_payment_method || null,
    );
    if (customerDefaultPaymentMethod) return customerDefaultPaymentMethod;
  }

  return null;
}

async function fetchInvoiceExpandedForManualCapture(
  invoiceId: string,
  stripeSecretKey: string,
  requestId: string,
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
    const errorText = await response.text();
    console.warn("Failed to fetch expanded invoice for manual capture", {
      requestId,
      invoiceId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  return (await response.json()) as StripeInvoiceExpandedForManualCapture;
}

async function createManualCapturePaymentIntentForInvoice(
  invoice: StripeInvoiceExpandedForManualCapture,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripePaymentIntentDetails | null> {
  const customerId = getCustomerIdFromInvoice(invoice);
  const paymentMethodId = getDefaultPaymentMethodIdFromInvoice(invoice);
  const amountCents =
    typeof invoice.amount_remaining === "number" && invoice.amount_remaining > 0
      ? invoice.amount_remaining
      : invoice.amount_due || 0;

  if (
    !customerId ||
    !paymentMethodId ||
    amountCents <= 0 ||
    !invoice.currency
  ) {
    console.warn(
      "Insufficient invoice context to create manual-capture payment intent",
      {
        requestId,
        invoiceId: invoice.id,
        customerId: customerId || null,
        paymentMethodId: paymentMethodId || null,
        amountCents,
        currency: invoice.currency || null,
      },
    );
    return null;
  }

  const { id: subscriptionId } = getSubscriptionIdFromInvoice(invoice);
  const { id: checkoutSessionId } = getCheckoutSessionIdFromInvoice(invoice);
  const params = new URLSearchParams();
  params.append("amount", `${amountCents}`);
  params.append("currency", invoice.currency);
  params.append("customer", customerId);
  params.append("payment_method", paymentMethodId);
  params.append("confirm", "true");
  params.append("off_session", "true");
  params.append("capture_method", "manual");
  params.append("metadata[invoice_id]", invoice.id);
  if (subscriptionId) {
    params.append("metadata[subscription_id]", subscriptionId);
  }
  if (checkoutSessionId) {
    params.append("metadata[checkout_session_id]", checkoutSessionId);
  }
  if (invoice.metadata?.tenant_id) {
    params.append("metadata[tenant_id]", invoice.metadata.tenant_id);
  }
  if (invoice.metadata?.product_id) {
    params.append("metadata[product_id]", invoice.metadata.product_id);
  }
  if (invoice.subscription_details?.metadata?.product_id) {
    params.append(
      "metadata[product_id]",
      invoice.subscription_details.metadata.product_id,
    );
  }

  const response = await fetch("https://api.stripe.com/v1/payment_intents", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${stripeSecretKey}`,
      "Content-Type": "application/x-www-form-urlencoded",
      "Idempotency-Key": `allia_manual_capture_invoice_${invoice.id}`,
    },
    body: params.toString(),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.warn("Failed to create manual-capture payment intent for invoice", {
      requestId,
      invoiceId: invoice.id,
      customerId,
      paymentMethodId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  return (await response.json()) as StripePaymentIntentDetails;
}

async function updateStripePaymentIntentCaptureMethodToManual(
  paymentIntentId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripePaymentIntentDetails | null> {
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
    const errorText = await response.text();
    console.warn(
      "Failed to set manual capture method on recurring payment intent",
      {
        requestId,
        paymentIntentId,
        status: response.status,
        error: errorText,
      },
    );
    return null;
  }

  return (await response.json()) as StripePaymentIntentDetails;
}

async function confirmStripePaymentIntentForManualCapture(
  paymentIntentId: string,
  stripeSecretKey: string,
  requestId: string,
): Promise<StripePaymentIntentDetails | null> {
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
    const errorText = await response.text();
    console.warn(
      "Failed to confirm recurring payment intent for manual capture",
      {
        requestId,
        paymentIntentId,
        status: response.status,
        error: errorText,
      },
    );
    return null;
  }

  return (await response.json()) as StripePaymentIntentDetails;
}

async function handleInvoiceCreated(
  supabase: SupabaseAdminClient,
  invoice: Invoice,
  tenantId: string,
  requestId: string,
  stripeSecretKey: string | null,
  stripePaymentProviderId: string,
): Promise<void> {
  const billingReason = invoice.billing_reason || "unknown";
  const isManualCaptureInvoice =
    MANUAL_CAPTURE_INVOICE_BILLING_REASONS.has(billingReason);

  if (!isManualCaptureInvoice) {
    return;
  }

  if (
    invoice.status === "paid" ||
    invoice.status === "void" ||
    invoice.status === "uncollectible"
  ) {
    return;
  }

  const invoiceAmountToCollect =
    typeof invoice.amount_remaining === "number"
      ? invoice.amount_remaining
      : invoice.amount_due || 0;
  if (invoiceAmountToCollect <= 0) {
    console.info(
      "Skipping manual-capture preparation for zero-amount Stripe invoice",
      {
        requestId,
        invoiceId: invoice.id,
        billingReason,
        amountDue: invoice.amount_due ?? null,
        amountRemaining: invoice.amount_remaining ?? null,
      },
    );
    return;
  }

  if (!stripeSecretKey) {
    console.warn(
      "Stripe secret key is missing - cannot prepare recurring invoice for manual capture",
      {
        requestId,
        invoiceId: invoice.id,
        billingReason,
      },
    );
    return;
  }

  const { id: invoiceSubscriptionId } = getSubscriptionIdFromInvoice(invoice);
  const { id: invoiceCheckoutSessionId } =
    getCheckoutSessionIdFromInvoice(invoice);

  let workingInvoice: StripeInvoiceManualCapture = {
    id: invoice.id,
    status: invoice.status,
    auto_advance: invoice.auto_advance,
    billing_reason: billingReason,
    payment_intent: invoice.payment_intent || null,
  };

  if (workingInvoice.auto_advance !== false) {
    const updatedInvoice = await updateStripeInvoiceAutoAdvance(
      invoice.id,
      stripeSecretKey,
      requestId,
    );
    if (updatedInvoice) {
      workingInvoice = updatedInvoice;
    }
  }

  let paymentIntentId = getPaymentIntentIdFromInvoice(workingInvoice);

  if (!paymentIntentId && workingInvoice.status === "draft") {
    const finalizedInvoice = await finalizeInvoiceForManualCapture(
      workingInvoice.id,
      stripeSecretKey,
      requestId,
    );
    if (finalizedInvoice) {
      workingInvoice = finalizedInvoice;
      paymentIntentId = getPaymentIntentIdFromInvoice(workingInvoice);
    }
  }

  if (!paymentIntentId) {
    const expandedInvoice = await fetchInvoiceExpandedForManualCapture(
      workingInvoice.id,
      stripeSecretKey,
      requestId,
    );
    if (!expandedInvoice) {
      return;
    }

    const createdPaymentIntent =
      await createManualCapturePaymentIntentForInvoice(
        expandedInvoice,
        stripeSecretKey,
        requestId,
      );
    if (!createdPaymentIntent?.id) {
      return;
    }
    paymentIntentId = createdPaymentIntent.id;
  }

  let paymentIntent = await fetchStripePaymentIntentDetails(
    paymentIntentId,
    stripeSecretKey,
    requestId,
  );
  if (!paymentIntent) {
    return;
  }

  if (
    paymentIntent.status === "requires_capture" &&
    paymentIntent.capture_method === "manual"
  ) {
    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: invoice.id || null,
      checkoutSessionId: invoiceCheckoutSessionId,
      subscriptionId:
        paymentIntent.metadata?.subscription_id?.trim() ||
        invoiceSubscriptionId,
      customerId: getCustomerIdFromInvoice(invoice),
      paymentStatus: paymentIntent.status || "requires_capture",
      requestId,
      source: "invoice_created_already_requires_capture",
    });

    console.info(
      "Recurring invoice payment intent already authorized for manual capture",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        status: paymentIntent.status,
      },
    );
    return;
  }

  if (paymentIntent.status === "succeeded") {
    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: invoice.id || null,
      checkoutSessionId: invoiceCheckoutSessionId,
      subscriptionId:
        paymentIntent.metadata?.subscription_id?.trim() ||
        invoiceSubscriptionId,
      customerId: getCustomerIdFromInvoice(invoice),
      paymentStatus: paymentIntent.status || "succeeded",
      requestId,
      source: "invoice_created_already_succeeded",
    });

    console.info(
      "Recurring invoice payment intent already captured before manual capture orchestration",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        status: paymentIntent.status,
      },
    );
    return;
  }

  if (paymentIntent.capture_method !== "manual") {
    const updatedPaymentIntent =
      await updateStripePaymentIntentCaptureMethodToManual(
        paymentIntentId,
        stripeSecretKey,
        requestId,
      );
    if (!updatedPaymentIntent) {
      return;
    }
    paymentIntent = updatedPaymentIntent;
  }

  if (paymentIntent.status === "requires_capture") {
    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: invoice.id || null,
      checkoutSessionId: invoiceCheckoutSessionId,
      subscriptionId:
        paymentIntent.metadata?.subscription_id?.trim() ||
        invoiceSubscriptionId,
      customerId: getCustomerIdFromInvoice(invoice),
      paymentStatus: paymentIntent.status || "requires_capture",
      requestId,
      source: "invoice_created_prepared_requires_capture",
    });

    console.info(
      "Recurring invoice payment intent prepared for manual capture",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        status: paymentIntent.status,
      },
    );
    return;
  }

  if (
    paymentIntent.status !== "requires_confirmation" &&
    paymentIntent.status !== "requires_payment_method"
  ) {
    console.warn(
      "Recurring payment intent is in a non-confirmable state for manual capture",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        status: paymentIntent.status,
        captureMethod: paymentIntent.capture_method || null,
      },
    );
    return;
  }

  const matchedOrderIdForConfirm = await resolveOrderIdForPaymentIntent(
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntentId,
    invoice.id || null,
    invoiceCheckoutSessionId,
    paymentIntent.metadata?.subscription_id?.trim() || invoiceSubscriptionId,
    requestId,
  );

  if (!matchedOrderIdForConfirm) {
    console.info(
      "Skipping recurring payment intent confirmation because no linked order was found",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        tenantId,
      },
    );

    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: invoice.id || null,
      checkoutSessionId: invoiceCheckoutSessionId,
      subscriptionId:
        paymentIntent.metadata?.subscription_id?.trim() ||
        invoiceSubscriptionId,
      customerId: getCustomerIdFromInvoice(invoice),
      paymentStatus: paymentIntent.status || "requires_confirmation",
      requestId,
      source: "invoice_created_confirm_skipped_no_order",
    });

    return;
  }

  const { data: orderForConfirm, error: orderForConfirmError } = await supabase
    .from("orders")
    .select("order_statuses!inner(status_key)")
    .eq("id", matchedOrderIdForConfirm)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  const orderStatusKeyForConfirm = (
    orderForConfirm?.order_statuses as unknown as { status_key?: string }
  )?.status_key || null;

  if (orderForConfirmError || !orderStatusKeyForConfirm) {
    console.warn(
      "Skipping recurring payment intent confirmation because linked order status could not be resolved",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        tenantId,
        orderId: matchedOrderIdForConfirm,
        error: orderForConfirmError?.message || "status_unresolved",
      },
    );

    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: invoice.id || null,
      checkoutSessionId: invoiceCheckoutSessionId,
      subscriptionId:
        paymentIntent.metadata?.subscription_id?.trim() ||
        invoiceSubscriptionId,
      customerId: getCustomerIdFromInvoice(invoice),
      paymentStatus: paymentIntent.status || "requires_confirmation",
      requestId,
      source: "invoice_created_confirm_skipped_status_unresolved",
    });

    return;
  }

  if (orderStatusKeyForConfirm !== "payment_pending") {
    console.info(
      "Skipping recurring payment intent confirmation because linked order is not payment_pending",
      {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId,
        tenantId,
        orderId: matchedOrderIdForConfirm,
        orderStatusKey: orderStatusKeyForConfirm,
      },
    );

    await persistPaymentIntentReferenceForOrder({
      supabase,
      tenantId,
      stripePaymentProviderId,
      paymentIntentId: paymentIntent.id,
      invoiceId: invoice.id || null,
      checkoutSessionId: invoiceCheckoutSessionId,
      subscriptionId:
        paymentIntent.metadata?.subscription_id?.trim() ||
        invoiceSubscriptionId,
      customerId: getCustomerIdFromInvoice(invoice),
      paymentStatus: paymentIntent.status || "requires_confirmation",
      requestId,
      source: "invoice_created_confirm_skipped_not_payment_pending",
    });

    return;
  }

  const confirmedPaymentIntent =
    await confirmStripePaymentIntentForManualCapture(
      paymentIntentId,
      stripeSecretKey,
      requestId,
    );

  if (!confirmedPaymentIntent) {
    return;
  }

  console.info("Confirmed recurring payment intent for manual capture flow", {
    requestId,
    invoiceId: invoice.id,
    paymentIntentId,
    status: confirmedPaymentIntent.status,
    captureMethod: confirmedPaymentIntent.capture_method || null,
  });

  await persistPaymentIntentReferenceForOrder({
    supabase,
    tenantId,
    stripePaymentProviderId,
    paymentIntentId: paymentIntentId,
    invoiceId: invoice.id || null,
    checkoutSessionId: invoiceCheckoutSessionId,
    subscriptionId:
      confirmedPaymentIntent.metadata?.subscription_id?.trim() ||
      paymentIntent.metadata?.subscription_id?.trim() ||
      invoiceSubscriptionId,
    customerId: getCustomerIdFromInvoice(invoice),
    paymentStatus: confirmedPaymentIntent.status || "requires_capture",
    requestId,
    source: "invoice_created_confirmed_manual_capture",
  });
}

async function getExpirationAtFromStripeSubscription(
  subscriptionId: string | null,
  stripeSecretKey: string | null,
  requestId: string,
): Promise<string | null> {
  if (!subscriptionId || !stripeSecretKey) return null;

  const response = await fetch(
    `https://api.stripe.com/v1/subscriptions/${subscriptionId}`,
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
      "Failed to fetch Stripe subscription for expiration fallback",
      {
        requestId,
        subscriptionId,
        status: response.status,
        error: errorText,
      },
    );
    return null;
  }

  const subscription = (await response.json()) as {
    current_period_end?: number;
  };

  return subscription.current_period_end
    ? dateTime.unix(subscription.current_period_end).toISOString()
    : null;
}

async function updateSubscriptionLifecycleFromStripeInvoice(
  supabase: SupabaseAdminClient,
  tenantId: string,
  stripePaymentProviderId: string,
  subscriptionId: string | null,
  checkoutSessionId: string | null,
  expirationAt: string | null,
  requestId: string,
): Promise<void> {
  if (!expirationAt) return;

  if (subscriptionId) {
    const { data: linksBySubscription, error: updateBySubscriptionError } =
      await supabase
        .from("subscription_payment_provider_links")
        .select("subscription_id")
        .eq("tenant_id", tenantId)
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_subscription_id", subscriptionId)
        .limit(2);

    if (updateBySubscriptionError) {
      console.warn(
        "Failed to lookup subscription lifecycle using provider_subscription_id",
        {
          requestId,
          tenantId,
          subscriptionId,
          error: updateBySubscriptionError.message,
        },
      );
      return;
    }

    const matchedSubscriptionIds = (linksBySubscription || [])
      .map((row) => row.subscription_id)
      .filter((id): id is string => typeof id === "string");

    if (matchedSubscriptionIds.length > 0) {
      const { error: updateBySubscriptionIdError } = await supabase
        .from("subscriptions")
        .update({
          current_period_end_at: expirationAt,
          expires_at: expirationAt,
        })
        .eq("tenant_id", tenantId)
        .in("id", matchedSubscriptionIds);

      if (updateBySubscriptionIdError) {
        console.warn(
          "Failed to update subscription lifecycle using provider_subscription_id links",
          {
            requestId,
            tenantId,
            subscriptionId,
            matchedSubscriptionIds,
            error: updateBySubscriptionIdError.message,
          },
        );
      } else {
        return;
      }
    }
  }

  if (!checkoutSessionId) return;

  const { data: linksByCheckoutSession, error: updateByCheckoutError } =
    await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", checkoutSessionId)
      .limit(2);

  if (updateByCheckoutError) {
    console.warn(
      "Failed to lookup subscription lifecycle using provider_checkout_session_id",
      {
        requestId,
        tenantId,
        checkoutSessionId,
        error: updateByCheckoutError.message,
      },
    );
    return;
  }

  const matchedSubscriptionIds = (linksByCheckoutSession || [])
    .map((row) => row.subscription_id)
    .filter((id): id is string => typeof id === "string");

  if (matchedSubscriptionIds.length === 0) return;

  const { error: updateSubscriptionsError } = await supabase
    .from("subscriptions")
    .update({
      current_period_end_at: expirationAt,
      expires_at: expirationAt,
    })
    .eq("tenant_id", tenantId)
    .in("id", matchedSubscriptionIds);

  if (updateSubscriptionsError) {
    console.warn(
      "Failed to update subscription lifecycle using provider_checkout_session_id links",
      {
        requestId,
        tenantId,
        checkoutSessionId,
        matchedSubscriptionIds,
        error: updateSubscriptionsError.message,
      },
    );
  }
}

async function getProductIdFromCheckoutSession(
  supabase: SupabaseAdminClient,
  checkoutSessionId: string,
  stripePaymentProviderId: string,
  stripeSecretKey: string | null,
  requestId: string,
): Promise<string | null> {
  const { data: linksByCheckoutSession, error: linkLookupError } =
    await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", checkoutSessionId)
      .limit(1);

  if (linkLookupError) {
    console.warn(
      "Failed to lookup subscription link by provider_checkout_session_id for product_id",
      {
        requestId,
        checkoutSessionId,
        error: linkLookupError.message,
      },
    );
  } else if (linksByCheckoutSession?.[0]?.subscription_id) {
    const { data: existingOrderViaSubscription, error: orderLookupError } =
      await supabase
        .from("orders")
        .select("product_id")
        .eq("subscription_id", linksByCheckoutSession[0].subscription_id)
        .order("created_at", { ascending: false })
        .limit(1)
        .maybeSingle();

    if (orderLookupError) {
      console.warn(
        "Failed to lookup product_id from existing order via subscription_id",
        {
          requestId,
          checkoutSessionId,
          subscriptionId: linksByCheckoutSession[0].subscription_id,
          error: orderLookupError.message,
        },
      );
    } else if (existingOrderViaSubscription?.product_id) {
      return existingOrderViaSubscription.product_id;
    }
  }

  if (!stripeSecretKey) {
    console.warn(
      "Stripe secret key missing - cannot fetch checkout session for product_id",
      {
        requestId,
        checkoutSessionId,
      },
    );
    return null;
  }

  const response = await fetch(
    `https://api.stripe.com/v1/checkout/sessions/${checkoutSessionId}`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.error("Failed to fetch checkout session for product_id fallback", {
      requestId,
      checkoutSessionId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  const session = await response.json();
  return session?.metadata?.product_id || null;
}

async function getProductIdFromSubscriptionMetadata(
  subscriptionId: string,
  stripeSecretKey: string | null,
  requestId: string,
): Promise<string | null> {
  if (!stripeSecretKey) {
    console.warn(
      "Stripe secret key missing - cannot fetch subscription for product_id",
      {
        requestId,
        subscriptionId,
      },
    );
    return null;
  }

  const response = await fetch(
    `https://api.stripe.com/v1/subscriptions/${subscriptionId}`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.error("Failed to fetch subscription for product_id fallback", {
      requestId,
      subscriptionId,
      status: response.status,
      error: errorText,
    });
    return null;
  }

  const subscription = await response.json();
  return subscription?.metadata?.product_id || null;
}

async function createPendingOrderForManualCaptureInvoice(params: {
  supabase: SupabaseAdminClient;
  invoice: Invoice;
  paymentIntent: PaymentIntent;
  tenantId: string;
  requestId: string;
  stripeSecretKey: string | null;
  stripePaymentProviderId: string;
}): Promise<void> {
  const {
    supabase,
    invoice,
    paymentIntent,
    tenantId,
    requestId,
    stripeSecretKey,
    stripePaymentProviderId,
  } = params;

  const billingReason = invoice.billing_reason || "unknown";
  const { id: subscriptionId } = getSubscriptionIdFromInvoice(invoice);
  const { id: checkoutSessionId } = getCheckoutSessionIdFromInvoice(invoice);
  const renewalAt =
    getExpirationAtFromInvoice(invoice) ||
    (await getExpirationAtFromStripeSubscription(
      subscriptionId,
      stripeSecretKey,
      requestId,
    ));

  if (
    !subscriptionId ||
    !ORDER_CREATING_INVOICE_BILLING_REASONS.has(billingReason)
  ) {
    return;
  }

  if (invoice.id) {
    const { data: existingByInvoice } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_invoice_id", invoice.id)
      .maybeSingle();

    if (existingByInvoice?.order_id) {
      await upsertOrderPaymentProviderTransaction({
        supabase,
        tenantId,
        orderId: existingByInvoice.order_id,
        paymentProviderId: stripePaymentProviderId,
        requestId,
        providerPaymentIntentId: paymentIntent.id,
        providerInvoiceId: invoice.id,
        providerSubscriptionId: subscriptionId,
        providerCheckoutSessionId: checkoutSessionId,
        paymentStatus: paymentIntent.status,
        paidAt: null,
      });
      return;
    }
  }

  const { data: existingByPaymentIntent } = await supabase
    .from("order_payment_provider_transactions")
    .select("order_id")
    .eq("tenant_id", tenantId)
    .eq("payment_provider_id", stripePaymentProviderId)
    .eq("provider_payment_intent_id", paymentIntent.id)
    .maybeSingle();

  if (existingByPaymentIntent?.order_id) {
    await upsertOrderPaymentProviderTransaction({
      supabase,
      tenantId,
      orderId: existingByPaymentIntent.order_id,
      paymentProviderId: stripePaymentProviderId,
      requestId,
      providerPaymentIntentId: paymentIntent.id,
      providerInvoiceId: invoice.id || null,
      providerSubscriptionId: subscriptionId,
      providerCheckoutSessionId: checkoutSessionId,
      paymentStatus: paymentIntent.status,
      paidAt: null,
    });
    return;
  }

  if (subscriptionId) {
    const { data: existingBySubscription } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_subscription_id", subscriptionId)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

    if (existingBySubscription?.order_id) {
      await upsertOrderPaymentProviderTransaction({
        supabase,
        tenantId,
        orderId: existingBySubscription.order_id,
        paymentProviderId: stripePaymentProviderId,
        requestId,
        providerPaymentIntentId: paymentIntent.id,
        providerInvoiceId: invoice.id || null,
        providerSubscriptionId: subscriptionId,
        providerCheckoutSessionId: checkoutSessionId,
        paymentStatus: paymentIntent.status,
        paidAt: null,
      });
      return;
    }
  }

  if (checkoutSessionId) {
    const { data: existingByCheckoutSession } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", checkoutSessionId)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();

    if (existingByCheckoutSession?.order_id) {
      await upsertOrderPaymentProviderTransaction({
        supabase,
        tenantId,
        orderId: existingByCheckoutSession.order_id,
        paymentProviderId: stripePaymentProviderId,
        requestId,
        providerPaymentIntentId: paymentIntent.id,
        providerInvoiceId: invoice.id || null,
        providerSubscriptionId: subscriptionId,
        providerCheckoutSessionId: checkoutSessionId,
        paymentStatus: paymentIntent.status,
        paidAt: null,
      });
      return;
    }
  }

  if (subscriptionId) {
    const { data: linkedSubscriptionByProviderId } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_subscription_id", subscriptionId)
      .maybeSingle();

    const internalSubscriptionId =
      linkedSubscriptionByProviderId?.subscription_id || null;

    if (internalSubscriptionId) {
      const { data: existingOrderByInternalSubscription } = await supabase
        .from("orders")
        .select("id")
        .eq("tenant_id", tenantId)
        .eq("subscription_id", internalSubscriptionId)
        .order("created_at", { ascending: false })
        .limit(1)
        .maybeSingle();

      if (existingOrderByInternalSubscription?.id) {
        await upsertOrderPaymentProviderTransaction({
          supabase,
          tenantId,
          orderId: existingOrderByInternalSubscription.id,
          paymentProviderId: stripePaymentProviderId,
          requestId,
          subscriptionId: internalSubscriptionId,
          providerPaymentIntentId: paymentIntent.id,
          providerInvoiceId: invoice.id || null,
          providerSubscriptionId: subscriptionId,
          providerCheckoutSessionId: checkoutSessionId,
          paymentStatus: paymentIntent.status,
          paidAt: null,
        });
        return;
      }
    }
  }

  if (checkoutSessionId) {
    const { data: linkedSubscriptionByCheckoutSession } = await supabase
      .from("subscription_payment_provider_links")
      .select("subscription_id")
      .eq("tenant_id", tenantId)
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", checkoutSessionId)
      .maybeSingle();

    const internalSubscriptionId =
      linkedSubscriptionByCheckoutSession?.subscription_id || null;

    if (internalSubscriptionId) {
      const { data: existingOrderByInternalSubscription } = await supabase
        .from("orders")
        .select("id")
        .eq("tenant_id", tenantId)
        .eq("subscription_id", internalSubscriptionId)
        .order("created_at", { ascending: false })
        .limit(1)
        .maybeSingle();

      if (existingOrderByInternalSubscription?.id) {
        await upsertOrderPaymentProviderTransaction({
          supabase,
          tenantId,
          orderId: existingOrderByInternalSubscription.id,
          paymentProviderId: stripePaymentProviderId,
          requestId,
          subscriptionId: internalSubscriptionId,
          providerPaymentIntentId: paymentIntent.id,
          providerInvoiceId: invoice.id || null,
          providerSubscriptionId: subscriptionId,
          providerCheckoutSessionId: checkoutSessionId,
          paymentStatus: paymentIntent.status,
          paidAt: null,
        });
        return;
      }
    }
  }

  let productId: string | null =
    invoice.subscription_details?.metadata?.product_id ||
    invoice.parent?.subscription_details?.metadata?.product_id ||
    null;
  if (!productId && invoice.lines?.data?.length > 0) {
    productId = invoice.lines.data[0]?.metadata?.product_id || null;
  }
  if (!productId && invoice.lines?.data?.length > 0) {
    productId = invoice.lines.data[0]?.price?.metadata?.product_id || null;
  }
  if (!productId && checkoutSessionId) {
    productId = await getProductIdFromCheckoutSession(
      supabase,
      checkoutSessionId,
      stripePaymentProviderId,
      stripeSecretKey,
      requestId,
    );
  }
  if (!productId && subscriptionId) {
    productId = await getProductIdFromSubscriptionMetadata(
      subscriptionId,
      stripeSecretKey,
      requestId,
    );
  }

  const customerEmail =
    typeof invoice.customer_email === "string"
      ? invoice.customer_email.trim().toLowerCase()
      : "";
  const stripeCustomerId =
    typeof invoice.customer === "string"
      ? invoice.customer.trim().length > 0
        ? invoice.customer.trim()
        : null
      : typeof invoice.customer?.id === "string" &&
          invoice.customer.id.trim().length > 0
        ? invoice.customer.id.trim()
        : null;

  let patient: { id: string } | null = null;

  if (customerEmail) {
    const { data: patientByEmail, error: patientByEmailError } = await supabase
      .from("patients")
      .select("id")
      .eq("tenant_id", tenantId)
      .eq("email", customerEmail)
      .maybeSingle();

    if (patientByEmailError) {
      throw new Error(
        `Patient lookup failed by email: ${patientByEmailError.message}`,
      );
    }

    if (patientByEmail) {
      patient = patientByEmail;
    }
  }

  if (!patient && stripeCustomerId) {
    const {
      data: patientsByStripeCustomer,
      error: patientByStripeCustomerError,
    } = await supabase
      .from("patients")
      .select("id")
      .eq("tenant_id", tenantId)
      .filter("metadata->>stripe_customer_id", "eq", stripeCustomerId)
      .limit(2);

    if (patientByStripeCustomerError) {
      throw new Error(
        `Patient lookup failed by Stripe customer id: ${patientByStripeCustomerError.message}`,
      );
    }

    if (patientsByStripeCustomer && patientsByStripeCustomer.length > 1) {
      throw new Error("Multiple patients matched Stripe customer id");
    }

    if (patientsByStripeCustomer && patientsByStripeCustomer.length === 1) {
      patient = patientsByStripeCustomer[0];
    }
  }

  if (!patient) {
    throw new Error("Patient not found for manual-capture invoice");
  }

  const linkedSubscriptionId = await ensureOrderSubscription({
    supabase,
    tenantId,
    patientId: patient.id,
    productId: productId || null,
    stripePaymentProviderId,
    requestId,
    providerSubscriptionId: subscriptionId,
    providerCheckoutSessionId: checkoutSessionId,
    startedAt: dateTime().toISOString(),
    renewalAt,
    expiresAt: renewalAt,
  });

  if (!linkedSubscriptionId) {
    throw new Error(
      "Unable to link manual-capture invoice order to subscription plan",
    );
  }

  const orderCreatedStatusId = await getOrderCreatedStatusId(
    supabase,
    requestId,
  );
  const amountCents = paymentIntent.amount;
  const orderNumber = generateOrderNumber();

  // Extract coupon/discount info from the invoice
  const manualCaptureDiscountInfo = stripeSecretKey
    ? await extractDiscountFromInvoice(invoice, stripeSecretKey, requestId)
    : { discountCents: 0, couponCode: null, couponName: null };

  const { data: order, error: orderError } = await supabase
    .from("orders")
    .insert({
      order_number: orderNumber,
      tenant_id: tenantId,
      patient_id: patient.id,
      product_id: productId,
      subscription_id: linkedSubscriptionId,
      status_id: orderCreatedStatusId,
      status_changed_at: dateTime().toISOString(),
      subtotal_cents: amountCents,
      tax_cents: 0,
      shipping_cents: 0,
      total_cents: amountCents,
      discount_cents: manualCaptureDiscountInfo.discountCents,
      coupon_code: manualCaptureDiscountInfo.couponCode,
      coupon_name: manualCaptureDiscountInfo.couponName,
      internal_notes: `Manual capture order created - Invoice: ${invoice.id}, Subscription: ${subscriptionId}, Payment Intent: ${paymentIntent.id}`,
      paid_at: null,
      renewal_at: renewalAt,
    })
    .select("id, order_number")
    .single();

  if (orderError) {
    if (orderError.code === "23505") {
      return;
    }
    throw orderError;
  }

  await upsertOrderPaymentProviderTransaction({
    supabase,
    tenantId,
    orderId: order.id,
    paymentProviderId: stripePaymentProviderId,
    requestId,
    subscriptionId: linkedSubscriptionId,
    providerPaymentIntentId: paymentIntent.id,
    providerInvoiceId: invoice.id || null,
    providerChargeId: invoice.charge || null,
    providerSubscriptionId: subscriptionId,
    providerCheckoutSessionId: checkoutSessionId,
    paymentStatus: paymentIntent.status,
    paidAt: null,
  });

  console.info("Created pending order for manual-capture invoice", {
    requestId,
    tenantId,
    orderId: order.id,
    orderNumber: order.order_number,
    invoiceId: invoice.id,
    paymentIntentId: paymentIntent.id,
    subscriptionId,
    checkoutSessionId,
    paymentStatus: paymentIntent.status,
  });

  await triggerOrderLifecycle(order.id, tenantId, requestId);
}

async function findPatientForInvoiceContext(
  supabase: SupabaseAdminClient,
  tenantId: string,
  invoiceId: string,
  customerEmail: string,
  stripeCustomerId: string | null,
  requestId: string,
): Promise<{ id: string } | null> {
  if (customerEmail) {
    const { data: patientByEmail, error: patientByEmailError } = await supabase
      .from("patients")
      .select("id")
      .eq("tenant_id", tenantId)
      .eq("email", customerEmail)
      .maybeSingle();

    if (patientByEmailError) {
      console.error("Failed to find patient by email for invoice", {
        requestId,
        invoiceId,
        tenantId,
        customerEmail,
        error: patientByEmailError.message,
      });
      throw new Error(
        `Patient lookup failed by email: ${patientByEmailError.message}`,
      );
    }

    if (patientByEmail) {
      return patientByEmail;
    }
  }

  if (stripeCustomerId) {
    const {
      data: patientsByStripeCustomer,
      error: patientByStripeCustomerError,
    } = await supabase
      .from("patients")
      .select("id")
      .eq("tenant_id", tenantId)
      .filter("metadata->>stripe_customer_id", "eq", stripeCustomerId)
      .limit(2);

    if (patientByStripeCustomerError) {
      console.error(
        "Failed to find patient by Stripe customer id for invoice",
        {
          requestId,
          invoiceId,
          tenantId,
          stripeCustomerId,
          error: patientByStripeCustomerError.message,
        },
      );
      throw new Error(
        `Patient lookup failed by Stripe customer id: ${patientByStripeCustomerError.message}`,
      );
    }

    if (patientsByStripeCustomer && patientsByStripeCustomer.length > 1) {
      const errorMessage = "Multiple patients matched Stripe customer id";
      console.error("Ambiguous patient lookup for invoice", {
        requestId,
        invoiceId,
        tenantId,
        stripeCustomerId,
        matchedPatientIds: patientsByStripeCustomer.map((row) => row.id),
      });
      throw new Error(errorMessage);
    }

    if (patientsByStripeCustomer && patientsByStripeCustomer.length === 1) {
      const matchedPatient = patientsByStripeCustomer[0];
      console.info("Resolved patient by Stripe customer id for invoice", {
        requestId,
        invoiceId,
        tenantId,
        stripeCustomerId,
        patientId: matchedPatient.id,
      });
      return matchedPatient;
    }
  }

  return null;
}

async function handleInvoicePaid(
  supabase: SupabaseAdminClient,
  invoice: Invoice,
  tenantId: string,
  requestId: string,
  stripeSecretKey: string | null,
  stripePaymentProviderId: string,
) {
  const billingReason = invoice.billing_reason || "unknown";
  const { id: subscriptionId, source: subscriptionSource } =
    getSubscriptionIdFromInvoice(invoice);
  const { id: checkoutSessionId, source: checkoutSessionSource } =
    getCheckoutSessionIdFromInvoice(invoice);
  const invoicePaymentIntentId = getPaymentIntentIdFromInvoice(invoice);
  const expirationAtFromInvoice = getExpirationAtFromInvoice(invoice);
  const expirationAt =
    expirationAtFromInvoice ||
    (await getExpirationAtFromStripeSubscription(
      subscriptionId,
      stripeSecretKey,
      requestId,
    ));

  console.info("Invoice paid - processing", {
    requestId,
    invoiceId: invoice.id,
    tenantId,
    customerId: invoice.customer,
    subscriptionId,
    subscriptionSource,
    checkoutSessionId,
    checkoutSessionSource,
    amountPaid: invoice.amount_paid,
    currency: invoice.currency,
    status: invoice.status,
    billingReason,
    paymentIntentId: invoicePaymentIntentId,
    expirationAtSource: expirationAtFromInvoice
      ? "invoice.lines.period.end"
      : "subscription.current_period_end_fallback",
    expirationAt,
  });

  const isInitialSubscriptionInvoice = billingReason === "subscription_create";

  if (isInitialSubscriptionInvoice) {
    console.info("Initial subscription invoice - creating new order", {
      requestId,
      invoiceId: invoice.id,
      paymentIntentId: invoicePaymentIntentId,
      checkoutSessionId,
      subscriptionId,
    });
  }

  // Only process subscription invoices (initial or recurring)
  if (!subscriptionId) {
    console.info(
      "Invoice is not for a subscription - skipping order creation",
      {
        requestId,
        invoiceId: invoice.id,
      },
    );
    return;
  }

  const refillChangeSource =
    invoice.subscription_details?.metadata?.allia_refill_change_source ||
    invoice.subscription_details?.metadata?.last_refill_date_updated_via ||
    invoice.metadata?.allia_refill_change_source ||
    invoice.metadata?.last_refill_date_updated_via ||
    (typeof invoice.subscription === "object"
      ? invoice.subscription?.metadata?.allia_refill_change_source ||
        invoice.subscription?.metadata?.last_refill_date_updated_via
      : null);

  if (!ORDER_CREATING_INVOICE_BILLING_REASONS.has(billingReason)) {
    console.info("Skipping order creation for non-renewal invoice", {
      requestId,
      invoiceId: invoice.id,
      billingReason,
      subscriptionId,
      refillChangeSource: refillChangeSource || null,
    });
    return;
  }

  // Resolve product and lifecycle dates first so subscription tracking stays up to date
  // even when order creation is skipped (for example, missing patient lookup data).
  let productId: string | null = null;

  // Try subscription metadata first
  productId =
    invoice.subscription_details?.metadata?.product_id ||
    invoice.parent?.subscription_details?.metadata?.product_id ||
    null;

  // Try line metadata
  if (!productId && invoice.lines?.data?.length > 0) {
    productId = invoice.lines.data[0]?.metadata?.product_id || null;
  }

  // Try line item price metadata
  if (!productId && invoice.lines?.data?.length > 0) {
    productId = invoice.lines.data[0]?.price?.metadata?.product_id || null;
  }

  // Fallback: try checkout session metadata (via subscription metadata)
  if (!productId && checkoutSessionId) {
    productId = await getProductIdFromCheckoutSession(
      supabase,
      checkoutSessionId,
      stripePaymentProviderId,
      stripeSecretKey,
      requestId,
    );
  }

  // Fallback: fetch subscription from Stripe and read metadata directly
  if (!productId && subscriptionId) {
    productId = await getProductIdFromSubscriptionMetadata(
      subscriptionId,
      stripeSecretKey,
      requestId,
    );
  }

  const renewalAt = expirationAt;

  const customerEmail =
    typeof invoice.customer_email === "string"
      ? invoice.customer_email.trim().toLowerCase()
      : "";
  const stripeCustomerId =
    typeof invoice.customer === "string"
      ? invoice.customer.trim().length > 0
        ? invoice.customer.trim()
        : null
      : typeof invoice.customer?.id === "string" &&
          invoice.customer.id.trim().length > 0
        ? invoice.customer.id.trim()
        : null;

  if (!customerEmail && !stripeCustomerId) {
    const errorMessage =
      "Invoice is missing both customer_email and customer id";
    console.error("Cannot resolve patient for invoice", {
      requestId,
      invoiceId: invoice.id,
      customerEmail: invoice.customer_email || null,
      customerId: invoice.customer || null,
      error: errorMessage,
    });
    throw new Error(errorMessage);
  }

  let patient = await findPatientForInvoiceContext(
    supabase,
    tenantId,
    invoice.id,
    customerEmail,
    stripeCustomerId,
    requestId,
  );

  // Recovery path: invoice.paid can arrive before checkout.session.completed.
  // If we can map subscription -> checkout session, run the checkout handler
  // to ensure order/subscription linkage is persisted, then retry lookup.
  if (!patient && stripeSecretKey) {
    let checkoutSessionForRecovery: CheckoutSession | null = null;

    if (checkoutSessionId) {
      checkoutSessionForRecovery = await fetchCheckoutSessionById(
        checkoutSessionId,
        stripeSecretKey,
        requestId,
      );
    }

    if (!checkoutSessionForRecovery && subscriptionId) {
      checkoutSessionForRecovery = await fetchCheckoutSessionBySubscriptionId(
        subscriptionId,
        stripeSecretKey,
        requestId,
      );
    }

    if (checkoutSessionForRecovery) {
      console.info(
        "Patient not found for invoice - running checkout session recovery path",
        {
          requestId,
          invoiceId: invoice.id,
          subscriptionId,
          checkoutSessionId: checkoutSessionForRecovery.id,
        },
      );

      try {
        await handleCheckoutSessionCompleted(
          supabase,
          checkoutSessionForRecovery,
          tenantId,
          requestId,
          stripePaymentProviderId,
          stripeSecretKey,
        );
      } catch (recoveryError) {
        console.warn(
          "Checkout session recovery during invoice.paid failed; continuing with direct patient lookup",
          {
            requestId,
            invoiceId: invoice.id,
            tenantId,
            subscriptionId,
            checkoutSessionId: checkoutSessionForRecovery.id,
            error:
              recoveryError instanceof Error
                ? recoveryError.message
                : String(recoveryError),
          },
        );
      }

      patient = await findPatientForInvoiceContext(
        supabase,
        tenantId,
        invoice.id,
        customerEmail,
        stripeCustomerId,
        requestId,
      );
    }
  }

  if (!patient) {
    console.error(
      "Failed to find patient for invoice - returning retryable error",
      {
        requestId,
        invoiceId: invoice.id,
        tenantId,
        customerEmail: customerEmail || null,
        stripeCustomerId,
      },
    );
    throw new RetryableWebhookError("patient_not_found_for_invoice");
  }

  const linkedSubscriptionId = await ensureOrderSubscription({
    supabase,
    tenantId,
    patientId: patient.id,
    productId: productId || null,
    stripePaymentProviderId,
    requestId,
    providerSubscriptionId: subscriptionId,
    providerCheckoutSessionId: checkoutSessionId,
    startedAt: dateTime().toISOString(),
    renewalAt,
    expiresAt: renewalAt,
  });

  if (!linkedSubscriptionId) {
    throw new RetryableWebhookError(
      "unable_to_link_invoice_order_to_subscription_plan",
    );
  }

  // Get product details if we have a product_id
  let productName = "Subscription Renewal";
  if (productId) {
    const { data: product } = await supabase
      .from("products")
      .select("name")
      .eq("id", productId)
      .single();

    if (product) {
      productName = product.name;
    }
  }

  if (invoice.charge) {
    const { data: existingTransaction } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_charge_id", invoice.charge)
      .maybeSingle();

    if (existingTransaction?.order_id) {
      console.info(
        "Duplicate order detected for invoice charge - skipping creation",
        {
          requestId,
          existingOrderId: existingTransaction.order_id,
          chargeId: invoice.charge,
          invoiceId: invoice.id,
        },
      );
      return;
    }
  }

  if (invoice.id) {
    const { data: existingTransaction } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_invoice_id", invoice.id)
      .maybeSingle();

    if (existingTransaction?.order_id) {
      const { data: existingOrder } = await supabase
        .from("orders")
        .select("id, order_number")
        .eq("id", existingTransaction.order_id)
        .maybeSingle();

      if (existingOrder?.id) {
        console.info(
          "Existing pending order found for provider_invoice_id - updating to payment_collected",
          {
            requestId,
            orderId: existingOrder.id,
            orderNumber: existingOrder.order_number,
            invoiceId: invoice.id,
          },
        );

        await updateOrderToPaymentCollected(
          supabase,
          existingOrder.id,
          existingOrder.order_number || existingOrder.id,
          invoice,
          tenantId,
          requestId,
          stripePaymentProviderId,
        );
      } else {
        console.info(
          "Duplicate order detected for provider_invoice_id - skipping creation",
          {
            requestId,
            existingOrderId: existingTransaction.order_id,
            invoiceId: invoice.id,
          },
        );
      }

      await upsertOrderPaymentProviderTransaction({
        supabase,
        tenantId,
        orderId: existingTransaction.order_id,
        paymentProviderId: stripePaymentProviderId,
        requestId,
        providerPaymentIntentId: invoicePaymentIntentId,
        providerInvoiceId: invoice.id,
        providerChargeId: invoice.charge || null,
        providerSubscriptionId: subscriptionId,
        providerCheckoutSessionId: checkoutSessionId,
        paymentStatus: "paid",
        paidAt: dateTime().toISOString(),
      });

      await updateSubscriptionLifecycleFromStripeInvoice(
        supabase,
        tenantId,
        stripePaymentProviderId,
        subscriptionId,
        checkoutSessionId,
        renewalAt,
        requestId,
      );

      console.info("Invoice payment persisted against existing order", {
        requestId,
        existingOrderId: existingTransaction.order_id,
        invoiceId: invoice.id,
      });
      return;
    }
  }

  const invoiceOrderKind = isInitialSubscriptionInvoice
    ? "initial subscription"
    : "recurring";

  // Create order for the subscription payment
  const orderNumber = generateOrderNumber();
  const amountCents = invoice.amount_paid;

  console.info(`Creating order for ${invoiceOrderKind} invoice payment`, {
    requestId,
    invoiceId: invoice.id,
    orderNumber,
    patientId: patient.id,
    amountCents,
    productId,
    paymentIntentId: invoicePaymentIntentId,
  });

  const statusId = await getOrderCreatedStatusId(supabase, requestId);

  // Extract coupon/discount info from the invoice
  const invoiceDiscountInfo = stripeSecretKey
    ? await extractDiscountFromInvoice(invoice, stripeSecretKey, requestId)
    : { discountCents: 0, couponCode: null, couponName: null };

  const paidAt = dateTime().toISOString();
  const { data: order, error: orderError } = await supabase
    .from("orders")
    .insert({
      order_number: orderNumber,
      tenant_id: tenantId,
      patient_id: patient.id,
      product_id: productId,
      subscription_id: linkedSubscriptionId,
      status_id: statusId,
      status_changed_at: dateTime().toISOString(),
      subtotal_cents: amountCents,
      tax_cents: 0,
      shipping_cents: 0,
      total_cents: amountCents,
      discount_cents: invoiceDiscountInfo.discountCents,
      coupon_code: invoiceDiscountInfo.couponCode,
      coupon_name: invoiceDiscountInfo.couponName,
      internal_notes: `${
        isInitialSubscriptionInvoice ? "Initial Subscription" : "Recurring"
      } Invoice: ${invoice.id}, Subscription: ${subscriptionId}, Product: ${productName}`,
      paid_at: paidAt,
      renewal_at: renewalAt,
    })
    .select("id, order_number")
    .single();

  if (orderError) {
    if (orderError.code === "23505") {
      console.warn("Duplicate order insert detected for invoice - skipping", {
        requestId,
        invoiceId: invoice.id,
        paymentIntentId: invoicePaymentIntentId,
        error: orderError.message,
      });
      return;
    }
    console.error("Failed to create order for recurring invoice", {
      requestId,
      invoiceId: invoice.id,
      error: orderError.message,
      code: orderError.code,
    });
    return;
  }

  console.info("Order created successfully for recurring invoice payment", {
    requestId,
    orderId: order.id,
    orderNumber: order.order_number,
    statusId: statusId,
    invoiceId: invoice.id,
    subscriptionId: invoice.subscription,
  });

  await upsertOrderPaymentProviderTransaction({
    supabase,
    tenantId,
    orderId: order.id,
    paymentProviderId: stripePaymentProviderId,
    requestId,
    subscriptionId: linkedSubscriptionId,
    providerPaymentIntentId: invoicePaymentIntentId,
    providerInvoiceId: invoice.id,
    providerChargeId: invoice.charge || null,
    providerSubscriptionId: subscriptionId,
    providerCheckoutSessionId: checkoutSessionId,
    paymentStatus: "paid",
    paidAt,
  });

  await markSubscriptionAsActiveIfPendingValidation(
    supabase,
    tenantId,
    linkedSubscriptionId,
    requestId,
    "invoice_paid_new_order",
  );

  // Trigger order-lifecycle to process the order further
  await triggerOrderLifecycle(order.id, tenantId, requestId);
}

// Helper function to update an order to payment_collected status and trigger lifecycle
async function updateOrderToPaymentCollected(
  supabase: SupabaseAdminClient,
  orderId: string,
  orderNumber: string,
  invoice: Invoice,
  tenantId: string,
  requestId: string,
  stripePaymentProviderId: string,
) {
  const subscriptionId =
    typeof invoice.subscription === "string"
      ? invoice.subscription
      : invoice.subscription?.id || null;
  const invoicePaymentIntentId = getPaymentIntentIdFromInvoice(invoice);
  const expirationAt = getExpirationAtFromInvoice(invoice);

  // Fetch the payment_collected status
  const { data: paymentCollectedStatus } = await supabase
    .from("order_statuses")
    .select("id, status_key, display_order")
    .eq("status_key", "payment_collected")
    .eq("is_active", true)
    .maybeSingle();

  if (!paymentCollectedStatus) {
    console.error("payment_collected status not found", {
      requestId,
      orderId,
    });
    return;
  }

  // Fetch current order status to ensure we only advance from earlier statuses
  const { data: currentOrder, error: currentOrderError } = await supabase
    .from("orders")
    .select(
      "id, subscription_id, product_id, status_id, paid_at, order_statuses(status_key, display_order)",
    )
    .eq("id", orderId)
    .maybeSingle();

  if (currentOrderError || !currentOrder) {
    console.error("Failed to fetch current order status", {
      requestId,
      orderId,
      error: currentOrderError?.message || "Order not found",
    });
    return;
  }

  const currentStatus = currentOrder.order_statuses as {
    status_key?: string;
    display_order?: number;
  } | null;
  const renewalAt = expirationAt;

  if (
    typeof currentStatus?.display_order === "number" &&
    currentStatus.display_order >= paymentCollectedStatus.display_order
  ) {
    console.info(
      "Order already at or beyond payment_collected; skipping status update",
      {
        requestId,
        orderId,
        orderNumber,
        currentStatusKey: currentStatus.status_key || null,
        currentDisplayOrder: currentStatus.display_order,
        paymentCollectedDisplayOrder: paymentCollectedStatus.display_order,
      },
    );
    if (currentStatus?.status_key === "payment_collected") {
      // Ensure paid_at is set — it may be null if the order was moved to
      // payment_collected by a prior path (e.g. handleCustomerUpdated) that
      // didn't set it. syncLifecycleDatesForPaymentCollectedOrder needs it.
      if (!(currentOrder as { paid_at?: string | null }).paid_at) {
        await supabase
          .from("orders")
          .update({ paid_at: dateTime().toISOString() })
          .eq("id", orderId);
      }
      await trackFriendbuyPurchaseAfterPaymentCollected({
        supabase,
        tenantId,
        orderId,
        requestId,
        source: "stripe_webhook_already_collected",
      });
      await triggerOrderLifecycle(orderId, tenantId, requestId);
    }
    return;
  }

  // Update the order status
  const updatePayload: Record<string, unknown> = {
    status_id: paymentCollectedStatus.id,
    status_changed_at: dateTime().toISOString(),
    paid_at: dateTime().toISOString(),
  };

  if (renewalAt) {
    updatePayload.renewal_at = renewalAt;
  }

  const { error: updateError } = await supabase
    .from("orders")
    .update(updatePayload)
    .eq("id", orderId);

  if (updateError) {
    console.error("Failed to update order to payment_collected", {
      requestId,
      orderId,
      error: updateError.message,
    });
    return;
  }

  await upsertOrderPaymentProviderTransaction({
    supabase,
    tenantId,
    orderId,
    paymentProviderId: stripePaymentProviderId,
    requestId,
    providerPaymentIntentId: invoicePaymentIntentId,
    providerInvoiceId: invoice.id,
    providerChargeId: invoice.charge || null,
    providerSubscriptionId: subscriptionId,
    paymentStatus: "paid",
    paidAt: dateTime().toISOString(),
  });

  await markSubscriptionAsActiveIfPendingValidation(
    supabase,
    tenantId,
    (currentOrder as { subscription_id?: string | null })?.subscription_id ||
      null,
    requestId,
    "update_order_to_payment_collected",
  );

  // Add status history entry
  await supabase.from("order_status_history").insert({
    order_id: orderId,
    status_id: paymentCollectedStatus.id,
    notes: `Payment confirmed via Stripe invoice: ${invoice.id}`,
  });

  console.info("Order updated to payment_collected status", {
    requestId,
    orderId,
    orderNumber,
    invoiceId: invoice.id,
    newStatusId: paymentCollectedStatus.id,
  });

  await trackFriendbuyPurchaseAfterPaymentCollected({
    supabase,
    tenantId,
    orderId,
    requestId,
    source: "stripe_webhook_payment_collected",
  });

  // Trigger order-lifecycle to process the order further
  await triggerOrderLifecycle(orderId, tenantId, requestId);
}

// Helper function to trigger the order-lifecycle API
async function triggerOrderLifecycle(
  orderId: string,
  tenantId: string,
  requestId: string,
) {
  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;

  const orderLifecycleUrl = `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;

  console.info("Triggering order-lifecycle API", {
    requestId,
    orderId,
    tenantId,
    url: orderLifecycleUrl,
  });

  try {
    const response = await fetch(orderLifecycleUrl, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${supabaseServiceKey}`,
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error("order-lifecycle API call failed", {
        requestId,
        orderId,
        status: response.status,
        error: errorText,
      });
      return;
    }

    const result = await response.json();
    console.info("order-lifecycle API call succeeded", {
      requestId,
      orderId,
      result,
    });
  } catch (error) {
    console.error("Failed to call order-lifecycle API", {
      requestId,
      orderId,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

function handleInvoicePaymentFailed(
  invoice: Invoice,
  tenantId: string,
  requestId: string,
) {
  console.error("Invoice payment failed", {
    requestId,
    invoiceId: invoice.id,
    tenantId,
    customerId: invoice.customer,
    subscriptionId: invoice.subscription,
    status: invoice.status,
  });
  // Could pause subscription or notify patient
}
