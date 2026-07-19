// deno-lint-ignore no-import-prefix
import { createClient, SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { trackFriendbuyPurchaseForOrder } from "../_shared/friendbuy.ts";
import {
  asRtdhConfigRecord,
  getTrimmedString,
} from "../_shared/rtdh-config.ts";
import { notifyRtdhOrderStatusUpdated } from "../order-lifecycle/rtdh-helper.ts";
import {
  handleChatMessageReceivedEvent,
  isChatMessageReceivedPayload,
} from "./chat-notification.ts";
import {
  extractRtdhEventId,
  getOrderLinkedNextStatusId,
  ORDER_LINKED_SOURCE_STATUS_KEY,
  resolveForwardEventType,
  shouldApplyDirectStatusTransition,
  shouldInsertOrderHistoryForDirectStatusEvent,
  shouldTriggerOrderLifecycleForDirectStatusEvent,
} from "./event-actions.ts";
import {
  type DirectStatusEventType,
  isSupportedEventType,
  SUPPORTED_EVENT_TYPES,
  type SupportedEventType,
} from "./event-types.ts";
import {
  extractPatientPlatformOrderId,
  extractWooCommerceCustomerId,
  extractWooCommerceOrderId,
} from "./order-id.ts";
import {
  parseQuestionnaireSubmittedEvent,
  type RtdhQuestionnaireType,
} from "./questionnaire-event.ts";
import {
  resolvePaymentTransactionReference,
  resolveTenantIntegrationReference,
} from "./reference-validation.ts";
import { processRenewalIntent } from "./renewal-action.ts";
import { isRenewalOrderCreateIntent } from "./renewal.ts";
import {
  handleCustomerUpdatedPaymentRetry,
  isCustomerUpdatedEvent,
} from "./payment-retry.ts";
import type { RtdhEventPayload } from "./validation.ts";
import { asNonEmptyString, asObject, validatePayload } from "./validation.ts";
import {
  computeHmacSha256Hex,
  parseHmacSha256SignatureHeader,
  timingSafeEqualHex,
} from "../_shared/rtdh-signature.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

const PATIENT_PLATFORM_ORDER_ID_STRIPE_METADATA_KEY =
  "patient_platform_order_id";
const RTDH_WEBHOOK_TOKEN_CONFIG_KEY = "patient_platform_consumer_webhook_token";

function getSecretDiagnostics(secret: string): {
  secretPrefix: string;
  secretLength: number;
} {
  return {
    secretPrefix: secret.slice(0, 5),
    secretLength: secret.length,
  };
}

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-request-id, x-rtdh-webhook-secret, x-webhook-secret, x-webhook-signature, x-qa-bypass, x-rtdh-intent",
    methods: "POST, OPTIONS",
  });
}

function jsonResponse(
  req: Request,
  body: Record<string, unknown>,
  status = 200,
  headers: Record<string, string> = {},
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      ...getCorsHeaders(req),
      "Content-Type": "application/json",
      ...headers,
    },
  });
}

function errorResponse(
  req: Request,
  code: string,
  message: string,
  status: number,
  requestId: string,
  details?: unknown,
): Response {
  return jsonResponse(
    req,
    {
      error: {
        code,
        message,
        details: details ?? null,
      },
      requestId,
    },
    status,
    { "x-request-id": requestId },
  );
}

function normalizePath(pathname: string): string {
  let path = pathname.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/rtdh-webhook")) {
    path = path.slice("/rtdh-webhook".length);
  }
  if (!path.startsWith("/")) {
    path = `/${path}`;
  }
  if (path.length > 1 && path.endsWith("/")) {
    path = path.slice(0, -1);
  }
  return path;
}

function isMigratedEventPayload(payload: RtdhEventPayload): boolean {
  if (payload.is_migrated === true) {
    return true;
  }

  const migration = asObject(payload.migration);
  if (!migration) {
    return false;
  }

  return (
    migration.is_migrated === true ||
    migration.mode === "historical" ||
    migration.mode === "backfill"
  );
}

async function readRequestBody(req: Request): Promise<{
  content: string | null;
  contentLength: number;
  readError: string | null;
}> {
  try {
    const content = await req.text();
    return { content, contentLength: content.length, readError: null };
  } catch (error) {
    return {
      content: null,
      contentLength: 0,
      readError: error instanceof Error ? error.message : String(error),
    };
  }
}

function getSupabaseAdminClient(requestId: string): SupabaseAdminClient {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    throw new Error("Missing Supabase configuration");
  }

  return createClient(supabaseUrl, supabaseServiceKey, {
    global: {
      headers: {
        "x-request-id": requestId,
        "x-request-source": "rtdh-webhook-event",
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

async function resolveTenantId(
  supabase: SupabaseAdminClient,
  tenantIdentifier: string,
): Promise<string | null> {
  // Only attempt UUID lookup when identifier looks like a UUID — passing a slug
  // to the UUID-typed `id` column causes a Postgres error on every invocation.
  if (UUID_RE.test(tenantIdentifier)) {
    const { data: tenantById, error: tenantByIdError } = await supabase
      .from("tenants")
      .select("id")
      .eq("id", tenantIdentifier)
      .maybeSingle();

    if (!tenantByIdError && tenantById?.id) {
      return tenantById.id;
    }
  }

  const { data: tenantBySlug, error: tenantBySlugError } = await supabase
    .from("tenants")
    .select("id")
    .eq("slug", tenantIdentifier)
    .maybeSingle();

  if (!tenantBySlugError && tenantBySlug?.id) {
    return tenantBySlug.id;
  }

  return null;
}

async function fetchOrderTenantId(
  supabase: SupabaseAdminClient,
  orderId: string,
): Promise<string | null> {
  const { data, error } = await supabase
    .from("orders")
    .select("tenant_id")
    .eq("id", orderId)
    .maybeSingle();

  if (error || !data?.tenant_id) {
    return null;
  }

  return data.tenant_id as string;
}

async function getStatusIdByKey(
  supabase: SupabaseAdminClient,
  statusKey: string,
): Promise<string | null> {
  const { data, error } = await supabase
    .from("order_statuses")
    .select("id")
    .eq("status_key", statusKey)
    .maybeSingle();

  if (error || !data?.id) {
    return null;
  }

  return data.id as string;
}

// Step 1/2 are point-in-time batch jobs reading GCS backups - they cannot
// guarantee coverage of every active order forever, since new WooCommerce
// orders/renewals keep appearing after the batch runs. Without this, any
// such order would permanently fail to link (rtdh-webhook only looks orders
// up, it never created them), and Patient Platform's lifecycle processor
// would never see it - silently losing active orders is not acceptable for
// data consistency. This mirrors exactly the stub shape
// migration-phase1-import would have written if the order had existed at
// batch time; everything downstream of order resolution treats it the same
// as any pre-existing stub.
async function createOrderStubFromWooCommercePayload(
  supabase: SupabaseAdminClient,
  payload: RtdhEventPayload,
  tenantId: string,
  wooOrderId: string,
): Promise<string | null> {
  const wooCustomerId = extractWooCommerceCustomerId(payload);
  if (!wooCustomerId) {
    return null;
  }

  const { data: patient, error: patientError } = await supabase
    .from("patients")
    .select("id")
    .eq("tenant_id", tenantId)
    .filter("metadata->>woo_id", "eq", wooCustomerId)
    .maybeSingle();

  if (patientError || !patient?.id) {
    return null;
  }

  const statusId = await getStatusIdByKey(supabase, "payment_pending");
  if (!statusId) {
    console.error(
      "rtdh-webhook: cannot auto-create order stub, payment_pending status not found",
      { tenantId, wooOrderId },
    );
    return null;
  }

  const now = new Date().toISOString();
  const { data: created, error: createError } = await supabase
    .from("orders")
    .insert({
      tenant_id: tenantId,
      patient_id: patient.id,
      order_number: `WOO-${wooOrderId}`,
      status_id: statusId,
      status_changed_at: now,
      created_at: now,
      metadata: {
        woo_order_id: wooOrderId,
        is_migrated: true,
        migration_phase: 1,
        migration_phase_1: {
          imported_at: now,
          created_via: "rtdh_step3_auto_create",
        },
      },
    })
    .select("id")
    .single();

  if (createError || !created?.id) {
    console.error("rtdh-webhook: failed to auto-create order stub", {
      tenantId,
      wooOrderId,
      error: createError?.message,
    });
    return null;
  }

  console.info(
    "rtdh-webhook: auto-created order stub for WooCommerce order not seen by migration batch",
    { tenantId, wooOrderId, orderId: created.id, patientId: patient.id },
  );

  return created.id as string;
}

async function resolveOrderIdFromPayload(
  supabase: SupabaseAdminClient,
  payload: RtdhEventPayload,
  tenantId: string | null,
): Promise<string | null> {
  const patientPlatformOrderId = extractPatientPlatformOrderId(payload);
  if (patientPlatformOrderId) {
    return patientPlatformOrderId;
  }

  const wooOrderId = extractWooCommerceOrderId(payload);
  if (!wooOrderId || !tenantId) {
    return null;
  }

  const { data, error } = await supabase
    .from("orders")
    .select("id")
    .eq("tenant_id", tenantId)
    .filter("metadata->>woo_order_id", "eq", wooOrderId)
    .maybeSingle();

  if (!error && data?.id) {
    return data.id as string;
  }

  return await createOrderStubFromWooCommercePayload(
    supabase,
    payload,
    tenantId,
    wooOrderId,
  );
}

async function getRtdhWebhookToken(
  supabase: SupabaseAdminClient,
  requestId: string,
): Promise<string> {
  const { data, error } = await supabase
    .from("platform_settings")
    .select("value")
    .eq("key", "rtdh_config")
    .maybeSingle();

  if (error) {
    console.warn(
      "rtdh-webhook: authorization failed - unable to read RTDH config",
      { requestId, error: error.message },
    );
    return "";
  }

  const config = asRtdhConfigRecord(data?.value);
  const token = getTrimmedString(config, RTDH_WEBHOOK_TOKEN_CONFIG_KEY);

  if (!token) {
    console.warn(
      "rtdh-webhook: authorization failed - RTDH webhook token key is missing or empty",
      {
        requestId,
        settingFound: Boolean(data),
        valueIsObject: Boolean(config),
        expectedConfigKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
        configKeys: config ? Object.keys(config).sort() : [],
      },
    );
  } else {
    console.info("rtdh-webhook: loaded configured consumer webhook token", {
      requestId,
      configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
      ...getSecretDiagnostics(token),
    });
  }

  return token;
}

async function isAuthorized(
  req: Request,
  supabase: SupabaseAdminClient,
  requestId: string,
): Promise<boolean> {
  const attemptedLegacyMethods: string[] = [];
  const rtdhWebhookSecret = req.headers.get("x-rtdh-webhook-secret")?.trim() ||
    "";
  const webhookSecret = req.headers.get("x-webhook-secret")?.trim() || "";
  const authorization = req.headers.get("authorization") || "";
  const bearerToken = authorization.toLowerCase().startsWith("bearer ")
    ? authorization.slice(7).trim()
    : "";

  if (rtdhWebhookSecret) {
    attemptedLegacyMethods.push("x-rtdh-webhook-secret");
  }
  if (webhookSecret) {
    attemptedLegacyMethods.push("x-webhook-secret");
  }
  if (authorization.trim()) {
    attemptedLegacyMethods.push("authorization");
  }

  const migrationApiKey = Deno.env.get("MIGRATION_API_KEY")?.trim() || "";
  if (migrationApiKey) {
    const migrationKeyMatched = [
      rtdhWebhookSecret,
      webhookSecret,
      bearerToken,
    ].some((candidate) => candidate === migrationApiKey);

    if (migrationKeyMatched) {
      console.debug(
        "rtdh-webhook: authorization succeeded via MIGRATION_API_KEY bypass",
        { requestId, attemptedMethods: attemptedLegacyMethods },
      );
      return true;
    }

    if (attemptedLegacyMethods.length > 0) {
      console.warn(
        "rtdh-webhook: MIGRATION_API_KEY bypass attempted but did not match",
        { requestId, attemptedMethods: attemptedLegacyMethods },
      );
    }
  }

  if (attemptedLegacyMethods.length > 0) {
    console.warn(
      "rtdh-webhook: authorization rejected unsupported auth methods",
      { requestId, attemptedMethods: attemptedLegacyMethods },
    );
  }

  const configuredSecret = await getRtdhWebhookToken(supabase, requestId);
  if (!configuredSecret) {
    console.warn(
      "rtdh-webhook: authorization failed - RTDH webhook token is not configured",
      { requestId, configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY },
    );
    return false;
  }

  const signatureHeader = req.headers.get("x-webhook-signature");
  if (!signatureHeader) {
    console.warn(
      "rtdh-webhook: authorization failed - missing x-webhook-signature header",
      {
        requestId,
        attemptedMethods: attemptedLegacyMethods,
        configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
        ...getSecretDiagnostics(configuredSecret),
      },
    );
    return false;
  }

  if (!signatureHeader.trim().toLowerCase().startsWith("sha256=")) {
    console.warn(
      "rtdh-webhook: authorization failed - invalid x-webhook-signature format",
      {
        requestId,
        attemptedMethods: attemptedLegacyMethods,
        configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
        sentSignatureHeader: signatureHeader,
        ...getSecretDiagnostics(configuredSecret),
      },
    );
    return false;
  }

  const rawPayload = await req.clone().text();
  const calculatedSignature = await computeHmacSha256Hex(
    configuredSecret,
    rawPayload,
  );
  const sentSignature = parseHmacSha256SignatureHeader(signatureHeader);
  const signatureValid = timingSafeEqualHex(
    calculatedSignature,
    sentSignature,
  );

  console.info("rtdh-webhook: HMAC signature validation details", {
    requestId,
    configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
    calculatedSignature,
    sentSignature,
    sentSignatureHeader: signatureHeader,
    signaturesMatch: signatureValid,
    ...getSecretDiagnostics(configuredSecret),
  });

  if (signatureValid) {
    console.debug("rtdh-webhook: authorization succeeded via HMAC signature", {
      requestId,
      configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
      ...getSecretDiagnostics(configuredSecret),
    });
    return true;
  }

  console.warn(
    "rtdh-webhook: authorization failed - invalid x-webhook-signature",
    {
      requestId,
      attemptedMethods: attemptedLegacyMethods,
      configKey: RTDH_WEBHOOK_TOKEN_CONFIG_KEY,
      ...getSecretDiagnostics(configuredSecret),
    },
  );
  return false;
}

async function validateReferences(
  supabase: SupabaseAdminClient,
  payload: RtdhEventPayload,
): Promise<{
  errors: string[];
  resolvedTenantId: string | null;
  unmatchedInvoiceId: string | null;
  resolvedOrderId: string | null;
}> {
  const errors: string[] = [];

  const tenantIdentifier = payload.internal_tenant_id;
  const ids = asObject(payload.ids);
  const patientPlatformOrderId = ids
    ? asNonEmptyString(ids.patient_platform_order_id)
    : null;
  const customer = asObject(payload.customer);
  const patientId = ids ? asNonEmptyString(ids.patient_id) : null;
  const subscription = asObject(payload.subscription);
  const subscriptionId = subscription
    ? asNonEmptyString(subscription.subscription_id)
    : null;
  const payment = asObject(payload.payment);
  const checkoutSessionId = payment
    ? asNonEmptyString(payment.checkout_session_id)
    : null;
  const paymentSubscriptionId = payment
    ? asNonEmptyString(payment.subscription_id)
    : null;
  const paymentIntentId = payment
    ? asNonEmptyString(payment.payment_intent_id)
    : null;
  const invoiceId = payment ? asNonEmptyString(payment.invoice_id) : null;
  const paymentCustomerId = payment
    ? asNonEmptyString(payment.customer_id)
    : null;
  const providerName = customer
    ? asNonEmptyString(customer.provider_name)
    : null;
  const paymentProvider = payment ? asNonEmptyString(payment.provider) : null;

  const resolvedTenantId = await resolveTenantId(supabase, tenantIdentifier);
  const orderId = await resolveOrderIdFromPayload(
    supabase,
    payload,
    resolvedTenantId,
  );

  const productIds: string[] = [];
  if (Array.isArray(payload.products)) {
    for (const item of payload.products) {
      const obj = asObject(item);
      if (obj) {
        const pid = asNonEmptyString(obj.product_id);
        if (pid && !productIds.includes(pid)) {
          productIds.push(pid);
        }
      }
    }
  }

  const results = await Promise.all([
    orderId
      ? supabase.from("orders").select("id").eq("id", orderId).maybeSingle()
      : null,
    patientId
      ? supabase.from("patients").select("id").eq("id", patientId).maybeSingle()
      : null,
    subscriptionId
      ? supabase
        .from("subscriptions")
        .select("id, patient_id")
        .eq("stripe_subscription_id", subscriptionId)
        .maybeSingle()
      : null,
    resolvePaymentTransactionReference(
      supabase,
      resolvedTenantId,
      "provider_checkout_session_id",
      checkoutSessionId,
    ),
    resolvePaymentTransactionReference(
      supabase,
      resolvedTenantId,
      "provider_subscription_id",
      paymentSubscriptionId,
    ),
    resolvePaymentTransactionReference(
      supabase,
      resolvedTenantId,
      "provider_payment_intent_id",
      paymentIntentId,
    ),
    resolvePaymentTransactionReference(
      supabase,
      resolvedTenantId,
      "provider_invoice_id",
      invoiceId,
    ),
    resolveTenantIntegrationReference(supabase, resolvedTenantId, providerName),
    paymentProvider
      ? supabase
        .from("payment_providers")
        .select("id")
        .eq("key", paymentProvider)
        .maybeSingle()
      : null,
    ...productIds.map((pid) =>
      supabase.from("products").select("id").eq("id", pid).maybeSingle()
    ),
  ]);

  const [
    orderResult,
    patientResult,
    subscriptionResult,
    checkoutSessionResult,
    paymentSubscriptionResult,
    paymentIntentResult,
    invoiceResult,
    providerNameResult,
    paymentProviderResult,
  ] = results;

  const productResults = results.slice(9) as Array<{
    data: { id: string } | null;
    error: unknown;
  }>;

  if (!resolvedTenantId) {
    errors.push(
      `internal_tenant_id '${tenantIdentifier}' does not match any tenant id or slug`,
    );
  }
  if (orderId && (orderResult?.error || !orderResult?.data)) {
    errors.push(
      `ids.patient_platform_order_id '${orderId}' does not match any order`,
    );
  } else if (
    !patientPlatformOrderId &&
    extractWooCommerceOrderId(payload) &&
    !orderId
  ) {
    errors.push(
      `ids.woocommerce_order_id '${
        extractWooCommerceOrderId(
          payload,
        )
      }' does not match any migrated order`,
    );
  }
  if (patientId && (patientResult?.error || !patientResult?.data)) {
    errors.push(`ids.patient_id '${patientId}' does not match any patient`);
  }
  if (subscriptionId) {
    if (subscriptionResult?.error || !subscriptionResult?.data) {
      errors.push(
        `subscription.subscription_id '${subscriptionId}' does not match any plan`,
      );
    } else if (patientId && subscriptionResult.data.patient_id !== patientId) {
      errors.push(
        `subscription.subscription_id '${subscriptionId}' does not belong to ids.patient_id '${patientId}'`,
      );
    }
  }

  if (
    checkoutSessionId &&
    resolvedTenantId &&
    (checkoutSessionResult?.error || !checkoutSessionResult?.data)
  ) {
    errors.push(
      `payment.checkout_session_id '${checkoutSessionId}' does not match any transaction`,
    );
  }
  if (
    paymentSubscriptionId &&
    resolvedTenantId &&
    (paymentSubscriptionResult?.error || !paymentSubscriptionResult?.data)
  ) {
    errors.push(
      `payment.subscription_id '${paymentSubscriptionId}' does not match any transaction`,
    );
  }
  if (
    paymentIntentId &&
    resolvedTenantId &&
    (paymentIntentResult?.error || !paymentIntentResult?.data)
  ) {
    errors.push(
      `payment.payment_intent_id '${paymentIntentId}' does not match any transaction`,
    );
  }
  // Do not fail validation for unmatched invoice_id — it will be saved to the
  // matching transaction after the order is resolved from other identifiers.
  const unmatchedInvoiceId = invoiceId &&
      resolvedTenantId &&
      (invoiceResult?.error || !invoiceResult?.data)
    ? invoiceId
    : null;

  // Cross-check payment.customer_id against provider_customer_id stored on the transaction
  if (paymentCustomerId) {
    const transactionResults = [
      checkoutSessionResult,
      paymentSubscriptionResult,
      paymentIntentResult,
      invoiceResult,
    ];
    const storedCustomerId = transactionResults
      .map(
        (r) =>
          (r?.data as { provider_customer_id?: string } | null)
            ?.provider_customer_id ?? null,
      )
      .find((id) => typeof id === "string" && id.length > 0) ?? null;

    if (storedCustomerId && storedCustomerId !== paymentCustomerId) {
      errors.push(
        `payment.customer_id '${paymentCustomerId}' does not match the stored provider_customer_id '${storedCustomerId}' on the transaction`,
      );
    }
  }

  if (
    providerName &&
    resolvedTenantId &&
    (providerNameResult?.error || !providerNameResult?.data)
  ) {
    errors.push(
      `customer.provider_name '${providerName}' does not match any provider platform integration`,
    );
  }
  if (
    paymentProvider &&
    (paymentProviderResult?.error || !paymentProviderResult?.data)
  ) {
    errors.push(
      `payment.provider '${paymentProvider}' does not match any payment provider`,
    );
  }

  productIds.forEach((pid, index) => {
    const result = productResults[index];
    if (result?.error || !result?.data) {
      errors.push(`products[].product_id '${pid}' does not match any product`);
    }
  });

  return {
    errors,
    resolvedTenantId,
    unmatchedInvoiceId,
    resolvedOrderId: orderId,
  };
}

async function triggerOrderLifecycleForOrder(
  orderId: string,
  tenantId: string,
  requestId: string,
): Promise<boolean> {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    console.warn(
      "rtdh-webhook: Unable to trigger order-lifecycle: missing env config",
      { requestId, orderId, tenantId },
    );
    return false;
  }

  const lifecycleUrl =
    `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;

  try {
    const response = await fetch(lifecycleUrl, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${supabaseServiceKey}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-request-source": "rtdh-webhook:event_action",
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.warn("rtdh-webhook: Failed to trigger order-lifecycle", {
        requestId,
        orderId,
        tenantId,
        status: response.status,
        error: errorText,
      });
      return false;
    }

    console.info("rtdh-webhook: Triggered order-lifecycle", {
      requestId,
      orderId,
      tenantId,
      status: response.status,
    });
    return true;
  } catch (error) {
    console.warn("rtdh-webhook: Error triggering order-lifecycle", {
      requestId,
      orderId,
      tenantId,
      error: error instanceof Error ? error.message : String(error),
    });
    return false;
  }
}

async function getTenantStripeSecretKey(
  supabase: SupabaseAdminClient,
  tenantId: string,
  requestId: string,
): Promise<string | null> {
  const { data: stripeProvider, error } = await supabase
    .from("tenant_payment_providers")
    .select(
      `
      settings,
      payment_providers!inner (
        key
      )
    `,
    )
    .eq("tenant_id", tenantId)
    .eq("is_enabled", true)
    .eq("payment_providers.key", "stripe")
    .maybeSingle();

  if (error || !stripeProvider) {
    console.warn("rtdh-webhook: Stripe provider config not found", {
      requestId,
      tenantId,
      error: error?.message || null,
    });
    return null;
  }

  const settings = stripeProvider.settings &&
      typeof stripeProvider.settings === "object" &&
      !Array.isArray(stripeProvider.settings)
    ? (stripeProvider.settings as Record<string, unknown>)
    : {};
  const secretKey = typeof settings.secret_key === "string"
    ? settings.secret_key.trim()
    : "";

  if (!secretKey) {
    console.warn("rtdh-webhook: Stripe secret key not configured", {
      requestId,
      tenantId,
    });
    return null;
  }

  return secretKey;
}

async function resolveStripePaymentIntentIdForOrder(params: {
  supabase: SupabaseAdminClient;
  orderId: string;
  tenantId: string;
  payloadStripePaymentIntentId: string | null;
  requestId: string;
}): Promise<string | null> {
  const {
    supabase,
    orderId,
    tenantId,
    payloadStripePaymentIntentId,
    requestId,
  } = params;

  const { data: transaction, error } = await supabase
    .from("order_payment_provider_transactions")
    .select(
      `
      provider_payment_intent_id,
      payment_providers!inner (
        key
      )
    `,
    )
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .eq("payment_providers.key", "stripe")
    .not("provider_payment_intent_id", "is", null)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (error) {
    console.warn(
      "rtdh-webhook: order.linked - failed to fetch Stripe transaction payment intent reference",
      {
        requestId,
        orderId,
        tenantId,
        error: error.message,
      },
    );
    return null;
  }

  return (
    transaction?.provider_payment_intent_id?.trim() ||
    payloadStripePaymentIntentId
  );
}

async function syncOrderIdToStripePaymentIntentMetadata(params: {
  supabase: SupabaseAdminClient;
  orderId: string;
  tenantId: string;
  payloadStripePaymentIntentId: string | null;
  requestId: string;
}): Promise<boolean> {
  const {
    supabase,
    orderId,
    tenantId,
    payloadStripePaymentIntentId,
    requestId,
  } = params;

  const stripePaymentIntentId = await resolveStripePaymentIntentIdForOrder({
    supabase,
    orderId,
    tenantId,
    payloadStripePaymentIntentId,
    requestId,
  });

  if (!stripePaymentIntentId) {
    console.info(
      "rtdh-webhook: order.linked - no Stripe payment intent reference available for metadata sync",
      {
        requestId,
        orderId,
        tenantId,
      },
    );
    return false;
  }

  const stripeSecretKey = await getTenantStripeSecretKey(
    supabase,
    tenantId,
    requestId,
  );
  if (!stripeSecretKey) {
    return false;
  }

  const paramsBody = new URLSearchParams();
  paramsBody.append(
    `metadata[${PATIENT_PLATFORM_ORDER_ID_STRIPE_METADATA_KEY}]`,
    orderId,
  );

  try {
    const response = await fetch(
      `https://api.stripe.com/v1/payment_intents/${
        encodeURIComponent(
          stripePaymentIntentId,
        )
      }`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: paramsBody.toString(),
      },
    );

    if (!response.ok) {
      const errorText = await response.text();
      console.warn(
        "rtdh-webhook: order.linked - Stripe payment intent metadata update failed",
        {
          requestId,
          orderId,
          tenantId,
          stripePaymentIntentId,
          status: response.status,
          error: errorText,
        },
      );
      return false;
    }

    console.info(
      "rtdh-webhook: order.linked - Stripe payment intent metadata updated",
      {
        requestId,
        orderId,
        tenantId,
        stripePaymentIntentId,
        metadataKey: PATIENT_PLATFORM_ORDER_ID_STRIPE_METADATA_KEY,
      },
    );
    return true;
  } catch (error) {
    console.warn(
      "rtdh-webhook: order.linked - Stripe payment intent metadata update errored",
      {
        requestId,
        orderId,
        tenantId,
        stripePaymentIntentId,
        error: error instanceof Error ? error.message : String(error),
      },
    );
    return false;
  }
}

async function handleDirectStatusEvent(
  supabase: SupabaseAdminClient,
  orderId: string | null,
  tenantId: string,
  eventType: DirectStatusEventType,
  requestId: string,
  options: { skipLifecycle?: boolean; rtdhEventId?: string | null } = {},
): Promise<{
  action: string;
  orderId: string | null;
  statusAdvanced: boolean;
  lifecycleTriggered: boolean;
}> {
  const rtdhEventId = asNonEmptyString(options.rtdhEventId);
  console.debug("rtdh-webhook: handleDirectStatusEvent invoked", {
    requestId,
    orderId,
    tenantId,
    eventType,
    rtdhEventId,
    skipLifecycle: options.skipLifecycle === true,
  });

  if (!orderId) {
    console.warn(
      "rtdh-webhook: direct status event - order ID is missing from payload",
      { requestId, tenantId, eventType },
    );
    return {
      action: eventType,
      orderId: null,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  const { data: order, error: orderError } = await supabase
    .from("orders")
    .select(
      "id, tenant_id, status_id, order_statuses!inner(id, status_key, display_order, next_status_id)",
    )
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (orderError) {
    console.error("rtdh-webhook: direct status event - order fetch error", {
      requestId,
      orderId,
      tenantId,
      eventType,
      error: orderError.message,
    });
    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  if (!order) {
    console.warn("rtdh-webhook: direct status event - order not found", {
      requestId,
      orderId,
      tenantId,
      eventType,
    });
    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  // Idempotency guard: inbound RTDH events are delivered at-least-once. If we have already
  // recorded a status-history row for this exact RTDH event_id on this order, a duplicate has
  // arrived — skip the update, history insert, and lifecycle so the order is never re-advanced
  // (which is how a stale/duplicate event surfaces as a false "rejected"/"cancelled").
  if (rtdhEventId) {
    const { data: existingForEvent, error: existingForEventError } =
      await supabase
        .from("order_status_history")
        .select("id")
        .eq("order_id", orderId)
        .eq("rtdh_event_id", rtdhEventId)
        .limit(1)
        .maybeSingle();

    if (existingForEventError) {
      // Fail open: a lookup error must not block a legitimate transition. Log and continue;
      // the same-status guard below still prevents the most common duplicate.
      console.warn(
        "rtdh-webhook: direct status event - idempotency lookup failed; proceeding",
        {
          requestId,
          orderId,
          tenantId,
          eventType,
          rtdhEventId,
          error: existingForEventError.message,
        },
      );
    } else if (existingForEvent?.id) {
      console.info(
        "rtdh-webhook: direct status event - duplicate RTDH event_id already processed; skipping",
        {
          requestId,
          orderId,
          tenantId,
          eventType,
          rtdhEventId,
        },
      );
      return {
        action: eventType,
        orderId,
        statusAdvanced: false,
        lifecycleTriggered: false,
      };
    }
  }

  const currentStatus = order.order_statuses as unknown as {
    id: string;
    status_key: string;
    display_order: number | null;
    next_status_id: string | null;
  };

  const { data: targetStatus, error: targetStatusError } = await supabase
    .from("order_statuses")
    .select("id, status_key, display_order")
    .eq("status_key", eventType)
    .maybeSingle();

  if (targetStatusError) {
    console.error(
      "rtdh-webhook: direct status event - target status fetch error",
      {
        requestId,
        orderId,
        tenantId,
        eventType,
        error: targetStatusError.message,
      },
    );
    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  if (!targetStatus) {
    console.warn(
      "rtdh-webhook: direct status event - target status not found",
      {
        requestId,
        orderId,
        tenantId,
        eventType,
      },
    );
    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  if (!shouldApplyDirectStatusTransition(currentStatus, targetStatus)) {
    console.info(
      "rtdh-webhook: direct status event would regress order status; skipping stale event",
      {
        requestId,
        orderId,
        tenantId,
        eventType,
        currentStatus: currentStatus?.status_key,
        currentDisplayOrder: currentStatus?.display_order,
        targetStatus: targetStatus.status_key,
        targetDisplayOrder: targetStatus.display_order,
        rtdhEventId,
      },
    );

    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  if (currentStatus?.status_key === targetStatus.status_key) {
    console.info(
      "rtdh-webhook: direct status event matched current order status; skipping update, history insert, and lifecycle",
      {
        requestId,
        orderId,
        tenantId,
        eventType,
        currentStatus: currentStatus.status_key,
      },
    );

    if (eventType === "payment_collected") {
      await trackFriendbuyPurchaseForOrder(supabase, {
        tenantId,
        orderId,
        requestId,
      }).catch((error) => {
        console.warn(
          "rtdh-webhook: Friendbuy purchase tracking failed for already-collected order",
          {
            requestId,
            orderId,
            tenantId,
            error: error instanceof Error ? error.message : String(error),
          },
        );
      });
    }

    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  const statusUpdate: Record<string, unknown> = {
    status_id: targetStatus.id,
    status_changed_at: new Date().toISOString(),
  };

  if (eventType === "payment_failed") {
    statusUpdate.payment_failed_at = new Date().toISOString();
    statusUpdate.payment_retry_count = 0;
  }

  if (
    eventType === "payment_collected" &&
    currentStatus?.status_key === "payment_failed"
  ) {
    // Recovering from a failed payment (see shouldApplyDirectStatusTransition)
    // — clear the failure markers so the order doesn't look failed downstream.
    statusUpdate.payment_failed_at = null;
    statusUpdate.payment_retry_count = 0;
  }

  const { error: updateError } = await supabase
    .from("orders")
    .update(statusUpdate)
    .eq("id", orderId)
    .eq("tenant_id", tenantId);

  if (updateError) {
    console.error("rtdh-webhook: direct status event - status update failed", {
      requestId,
      orderId,
      tenantId,
      eventType,
      fromStatus: currentStatus?.status_key,
      toStatusId: targetStatus.id,
      error: updateError.message,
    });
    return {
      action: eventType,
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  const shouldInsertHistory = shouldInsertOrderHistoryForDirectStatusEvent(
    currentStatus?.status_key,
    targetStatus.status_key,
  );

  if (shouldInsertHistory) {
    const { error: historyError } = await supabase
      .from("order_status_history")
      .insert({
        order_id: orderId,
        status_id: targetStatus.id,
        rtdh_event_id: rtdhEventId,
        notes: `Transitioned from ${
          currentStatus?.status_key ?? "unknown"
        } to ${targetStatus.status_key} by rtdh-webhook ${eventType} event`,
      });

    if (historyError) {
      console.warn(
        "rtdh-webhook: direct status event - status history insert failed",
        {
          requestId,
          orderId,
          tenantId,
          eventType,
          error: historyError.message,
        },
      );
    } else {
      console.info("rtdh-webhook: direct status event - status updated", {
        requestId,
        orderId,
        tenantId,
        eventType,
        fromStatus: currentStatus?.status_key,
        toStatus: targetStatus.status_key,
      });
    }
  } else {
    console.info(
      "rtdh-webhook: direct status event matched current order status; skipping history insert",
      {
        requestId,
        orderId,
        tenantId,
        eventType,
        currentStatus: currentStatus?.status_key,
      },
    );
  }

  if (eventType === "payment_collected") {
    await trackFriendbuyPurchaseForOrder(supabase, {
      tenantId,
      orderId,
      requestId,
    }).catch((error) => {
      console.warn("rtdh-webhook: Friendbuy purchase tracking failed", {
        requestId,
        orderId,
        tenantId,
        error: error instanceof Error ? error.message : String(error),
      });
    });
  }

  const shouldTriggerLifecycle =
    shouldTriggerOrderLifecycleForDirectStatusEvent(eventType, options);
  const lifecycleTriggered = shouldTriggerLifecycle
    ? await triggerOrderLifecycleForOrder(orderId, tenantId, requestId)
    : false;

  return {
    action: eventType,
    orderId,
    statusAdvanced: true,
    lifecycleTriggered,
  };
}

async function handleOrderLinked(
  supabase: SupabaseAdminClient,
  orderId: string | null,
  tenantId: string,
  requestId: string,
  payloadStripePaymentIntentId: string | null,
): Promise<{
  action: string;
  orderId: string | null;
  statusAdvanced: boolean;
  lifecycleTriggered: boolean;
  stripeMetadataSynced: boolean;
}> {
  console.debug("rtdh-webhook: handleOrderLinked invoked", {
    requestId,
    orderId,
    tenantId,
    payloadStripePaymentIntentId,
  });

  if (!orderId) {
    console.warn(
      "rtdh-webhook: order.linked - order ID is missing from payload",
      { requestId, tenantId },
    );
    return {
      action: "order.linked",
      orderId: null,
      statusAdvanced: false,
      lifecycleTriggered: false,
      stripeMetadataSynced: false,
    };
  }

  console.debug("rtdh-webhook: order.linked - fetching order", {
    requestId,
    orderId,
    tenantId,
  });

  const { data: order, error: orderError } = await supabase
    .from("orders")
    .select(
      "id, tenant_id, status_id, order_statuses!inner(id, status_key, next_status_id)",
    )
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (orderError) {
    console.error("rtdh-webhook: order.linked - order fetch error", {
      requestId,
      orderId,
      tenantId,
      error: orderError.message,
    });
    return {
      action: "order.linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
      stripeMetadataSynced: false,
    };
  }

  if (!order) {
    console.warn("rtdh-webhook: order.linked - order not found in database", {
      requestId,
      orderId,
      tenantId,
    });
    return {
      action: "order.linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
      stripeMetadataSynced: false,
    };
  }

  const currentStatus = order.order_statuses as unknown as {
    id: string;
    status_key: string;
    next_status_id: string | null;
  };

  console.debug("rtdh-webhook: order.linked - current order status", {
    requestId,
    orderId,
    statusKey: currentStatus?.status_key,
    nextStatusId: currentStatus?.next_status_id,
  });

  let statusAdvanced = false;
  let lifecycleTriggered = false;
  const stripeMetadataSynced = await syncOrderIdToStripePaymentIntentMetadata({
    supabase,
    orderId,
    tenantId,
    payloadStripePaymentIntentId,
    requestId,
  });
  const orderLinkedNextStatusId = getOrderLinkedNextStatusId(currentStatus);

  if (currentStatus?.status_key !== ORDER_LINKED_SOURCE_STATUS_KEY) {
    console.info(
      "rtdh-webhook: order.linked - order is not in order_created, skipping",
      {
        requestId,
        orderId,
        currentStatusKey: currentStatus?.status_key,
      },
    );

    return {
      action: "order.linked",
      orderId,
      statusAdvanced,
      lifecycleTriggered,
      stripeMetadataSynced,
    };
  }

  if (orderLinkedNextStatusId) {
    console.debug("rtdh-webhook: order.linked - advancing status", {
      requestId,
      orderId,
      fromStatus: currentStatus.status_key,
      toStatusId: orderLinkedNextStatusId,
    });

    const { data: orderLinkedNextStatus, error: orderLinkedNextStatusError } =
      await supabase
        .from("order_statuses")
        .select("id, status_key")
        .eq("id", orderLinkedNextStatusId)
        .maybeSingle();

    if (orderLinkedNextStatusError || !orderLinkedNextStatus?.status_key) {
      console.warn("rtdh-webhook: order.linked - next status lookup failed", {
        requestId,
        orderId,
        tenantId,
        nextStatusId: orderLinkedNextStatusId,
        error: orderLinkedNextStatusError?.message || "next_status_not_found",
      });
    }

    const { data: updatedOrders, error: updateError } = await supabase
      .from("orders")
      .update({
        status_id: orderLinkedNextStatusId,
        status_changed_at: new Date().toISOString(),
      })
      .eq("id", orderId)
      .eq("tenant_id", tenantId)
      .eq("status_id", currentStatus.id)
      .select("id");

    if (updateError) {
      console.error("rtdh-webhook: order.linked - status update failed", {
        requestId,
        orderId,
        currentStatusKey: currentStatus.status_key,
        nextStatusId: orderLinkedNextStatusId,
        error: updateError.message,
      });
    } else if ((updatedOrders || []).length === 0) {
      console.info(
        "rtdh-webhook: order.linked - status update skipped because the order status changed",
        {
          requestId,
          orderId,
          expectedStatusId: currentStatus.id,
          nextStatusId: orderLinkedNextStatusId,
        },
      );
    } else {
      console.debug("rtdh-webhook: order.linked - status update succeeded", {
        requestId,
        orderId,
        fromStatus: currentStatus.status_key,
        toStatusId: orderLinkedNextStatusId,
      });

      statusAdvanced = true;

      const { error: historyError } = await supabase
        .from("order_status_history")
        .insert({
          order_id: orderId,
          status_id: orderLinkedNextStatusId,
          notes:
            `Advanced from ${currentStatus.status_key} by rtdh-webhook order.linked event`,
        });

      if (historyError) {
        console.warn(
          "rtdh-webhook: order.linked - status history insert failed",
          {
            requestId,
            orderId,
            error: historyError.message,
          },
        );
      } else {
        console.info(
          "rtdh-webhook: order.linked - status advanced successfully",
          {
            requestId,
            orderId,
            fromStatus: currentStatus.status_key,
            toStatusId: orderLinkedNextStatusId,
          },
        );
      }

      if (orderLinkedNextStatus?.status_key) {
        await notifyRtdhOrderStatusUpdated({
          supabase,
          requestId,
          tenantId,
          orderId,
          statusId: orderLinkedNextStatus.id,
          statusKey: orderLinkedNextStatus.status_key,
          previousStatusKey: currentStatus.status_key,
          source: "rtdh-webhook:order.linked",
        });
      }
    }
  } else {
    console.info(
      "rtdh-webhook: order.linked - no next status configured for current status",
      {
        requestId,
        orderId,
        currentStatusKey: currentStatus?.status_key,
      },
    );
  }

  if (statusAdvanced) {
    lifecycleTriggered = await triggerOrderLifecycleForOrder(
      orderId,
      tenantId,
      requestId,
    );
  }

  return {
    action: "order.linked",
    orderId,
    statusAdvanced,
    lifecycleTriggered,
    stripeMetadataSynced,
  };
}

async function handleOrderFulfillmentLinked(
  supabase: SupabaseAdminClient,
  orderId: string | null,
  tenantId: string,
  requestId: string,
): Promise<{
  action: string;
  orderId: string | null;
  statusAdvanced: boolean;
  lifecycleTriggered: boolean;
}> {
  console.debug("rtdh-webhook: handleOrderFulfillmentLinked invoked", {
    requestId,
    orderId,
    tenantId,
  });

  if (!orderId) {
    console.warn(
      "rtdh-webhook: order.fulfillment_linked - order ID is missing from payload",
      { requestId, tenantId },
    );
    return {
      action: "order.fulfillment_linked",
      orderId: null,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  console.debug("rtdh-webhook: order.fulfillment_linked - fetching order", {
    requestId,
    orderId,
    tenantId,
  });

  const { data: order, error: orderError } = await supabase
    .from("orders")
    .select(
      "id, tenant_id, status_id, order_statuses!inner(id, status_key, next_status_id)",
    )
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (orderError) {
    console.error(
      "rtdh-webhook: order.fulfillment_linked - order fetch error",
      {
        requestId,
        orderId,
        tenantId,
        error: orderError.message,
      },
    );
    return {
      action: "order.fulfillment_linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  if (!order) {
    console.warn(
      "rtdh-webhook: order.fulfillment_linked - order not found in database",
      {
        requestId,
        orderId,
        tenantId,
      },
    );
    return {
      action: "order.fulfillment_linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  const currentStatus = order.order_statuses as unknown as {
    id: string;
    status_key: string;
    next_status_id: string | null;
  };

  console.debug(
    "rtdh-webhook: order.fulfillment_linked - current order status",
    {
      requestId,
      orderId,
      statusKey: currentStatus?.status_key,
      nextStatusId: currentStatus?.next_status_id,
    },
  );

  const canAdvanceFromFulfillmentLinked =
    currentStatus?.status_key === "provider_order_creation_pending" ||
    currentStatus?.status_key === "provider_order_creation_error";

  if (!canAdvanceFromFulfillmentLinked) {
    console.info(
      "rtdh-webhook: order.fulfillment_linked - order not in an advanceable provider creation status, skipping",
      {
        requestId,
        orderId,
        tenantId,
        expectedStatuses: [
          "provider_order_creation_pending",
          "provider_order_creation_error",
        ],
        currentStatus: currentStatus?.status_key,
      },
    );
    return {
      action: "order.fulfillment_linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  const { data: providerCreationPendingStatus, error: pendingStatusError } =
    await supabase
      .from("order_statuses")
      .select("id, status_key, next_status_id")
      .eq("status_key", "provider_order_creation_pending")
      .maybeSingle();

  if (
    pendingStatusError || !providerCreationPendingStatus?.id ||
    !providerCreationPendingStatus?.next_status_id
  ) {
    console.warn(
      "rtdh-webhook: order.fulfillment_linked - provider_order_creation_pending next_status_id is not configured",
      {
        requestId,
        orderId,
        tenantId,
        currentStatusKey: currentStatus.status_key,
        pendingStatusError: pendingStatusError?.message || null,
        providerCreationPendingStatusId: providerCreationPendingStatus?.id ??
          null,
      },
    );
    return {
      action: "order.fulfillment_linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  const targetStatusId = providerCreationPendingStatus.next_status_id;

  console.debug("rtdh-webhook: order.fulfillment_linked - advancing status", {
    requestId,
    orderId,
    fromStatus: currentStatus.status_key,
    targetResolvedFromStatus: providerCreationPendingStatus.status_key,
    toStatusId: targetStatusId,
  });

  const { data: nextStatus, error: nextStatusError } = await supabase
    .from("order_statuses")
    .select("id, status_key")
    .eq("id", targetStatusId)
    .maybeSingle();

  if (nextStatusError || !nextStatus?.status_key) {
    console.warn(
      "rtdh-webhook: order.fulfillment_linked - next status lookup failed",
      {
        requestId,
        orderId,
        tenantId,
        nextStatusId: targetStatusId,
        error: nextStatusError?.message || "next_status_not_found",
      },
    );
  }

  const { data: updatedOrders, error: updateError } = await supabase
    .from("orders")
    .update({
      status_id: targetStatusId,
      status_changed_at: new Date().toISOString(),
    })
    .eq("id", orderId)
    .eq("tenant_id", tenantId)
    .eq("status_id", currentStatus.id)
    .select("id");

  if (updateError) {
    console.error(
      "rtdh-webhook: order.fulfillment_linked - status update failed",
      {
        requestId,
        orderId,
        tenantId,
        currentStatusKey: currentStatus.status_key,
        nextStatusId: targetStatusId,
        error: updateError.message,
      },
    );
    return {
      action: "order.fulfillment_linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  if ((updatedOrders || []).length === 0) {
    console.info(
      "rtdh-webhook: order.fulfillment_linked - status update skipped because the order status changed",
      {
        requestId,
        orderId,
        tenantId,
        expectedStatusId: currentStatus.id,
        nextStatusId: targetStatusId,
      },
    );
    return {
      action: "order.fulfillment_linked",
      orderId,
      statusAdvanced: false,
      lifecycleTriggered: false,
    };
  }

  console.debug(
    "rtdh-webhook: order.fulfillment_linked - status update succeeded",
    {
      requestId,
      orderId,
      fromStatus: currentStatus.status_key,
      toStatusId: targetStatusId,
    },
  );

  const { error: historyError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: orderId,
      status_id: targetStatusId,
      notes: `Advanced from ${currentStatus.status_key} to ${
        nextStatus?.status_key ?? targetStatusId
      } by rtdh-webhook order.fulfillment_linked event using provider_order_creation_pending.next_status_id`,
    });

  if (historyError) {
    console.warn(
      "rtdh-webhook: order.fulfillment_linked - status history insert failed",
      {
        requestId,
        orderId,
        error: historyError.message,
      },
    );
  } else {
    console.info(
      "rtdh-webhook: order.fulfillment_linked - status advanced successfully",
      {
        requestId,
        orderId,
        tenantId,
        fromStatus: currentStatus.status_key,
        toStatusId: targetStatusId,
      },
    );
  }

  if (nextStatus?.status_key) {
    await notifyRtdhOrderStatusUpdated({
      supabase,
      requestId,
      tenantId,
      orderId,
      statusId: nextStatus.id,
      statusKey: nextStatus.status_key,
      previousStatusKey: currentStatus.status_key,
      source: "rtdh-webhook:order.fulfillment_linked",
    });
  }

  const lifecycleTriggered = await triggerOrderLifecycleForOrder(
    orderId,
    tenantId,
    requestId,
  );

  return {
    action: "order.fulfillment_linked",
    orderId,
    statusAdvanced: true,
    lifecycleTriggered,
  };
}

async function handleJotformQuestionnaireSubmitted(
  orderId: string | null,
  tenantId: string,
  submissionId: string | null,
  requestId: string,
  questionnaireType: RtdhQuestionnaireType,
  sourceEventType = "jotform_questionnaire_submitted",
): Promise<{
  action: string;
  questionnaireType: RtdhQuestionnaireType;
  orderId: string | null;
  submissionId: string | null;
  processingTriggered: boolean;
  processingResult?: unknown;
}> {
  console.debug("rtdh-webhook: handleJotformQuestionnaireSubmitted invoked", {
    requestId,
    orderId,
    tenantId,
    submissionId,
    questionnaireType,
  });

  if (!orderId) {
    console.warn(
      "rtdh-webhook: jotform_questionnaire_submitted - order ID is missing from payload",
      { requestId, tenantId },
    );
    return {
      action: sourceEventType,
      questionnaireType,
      orderId: null,
      submissionId,
      processingTriggered: false,
    };
  }

  if (!submissionId) {
    console.warn(
      "rtdh-webhook: jotform_questionnaire_submitted - submission ID is missing from payload",
      { requestId, orderId, tenantId },
    );
    return {
      action: sourceEventType,
      questionnaireType,
      orderId,
      submissionId: null,
      processingTriggered: false,
    };
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    console.warn(
      "rtdh-webhook: jotform_questionnaire_submitted - missing Supabase env config",
      { requestId, orderId, tenantId },
    );
    return {
      action: sourceEventType,
      questionnaireType,
      orderId,
      submissionId,
      processingTriggered: false,
    };
  }

  const bridgeUrl =
    `${supabaseUrl}/functions/v1/provider-platform-bridge/internal/order/${orderId}/process-jotform-submission`;

  try {
    const response = await fetch(bridgeUrl, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${supabaseServiceKey}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-request-source": `rtdh-webhook:${sourceEventType}`,
      },
      body: JSON.stringify({ submissionId, questionnaireType }),
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error(
        "rtdh-webhook: jotform_questionnaire_submitted - provider-platform-bridge call failed",
        {
          requestId,
          orderId,
          tenantId,
          submissionId,
          status: response.status,
          error: errorText,
        },
      );
      return {
        action: sourceEventType,
        questionnaireType,
        orderId,
        submissionId,
        processingTriggered: false,
      };
    }

    const rawProcessingResult = await response.text();
    let processingResult: unknown = null;
    if (rawProcessingResult) {
      try {
        processingResult = JSON.parse(rawProcessingResult);
      } catch {
        processingResult = rawProcessingResult;
      }
    }

    console.info(
      "rtdh-webhook: jotform_questionnaire_submitted - processing triggered successfully",
      {
        requestId,
        orderId,
        tenantId,
        submissionId,
        status: response.status,
      },
    );

    return {
      action: sourceEventType,
      questionnaireType,
      orderId,
      submissionId,
      processingTriggered: true,
      processingResult,
    };
  } catch (error) {
    console.error(
      "rtdh-webhook: jotform_questionnaire_submitted - error triggering processing",
      {
        requestId,
        orderId,
        tenantId,
        submissionId,
        error: error instanceof Error ? error.message : String(error),
      },
    );
    return {
      action: sourceEventType,
      questionnaireType,
      orderId,
      submissionId,
      processingTriggered: false,
    };
  }
}

async function processEventAction(
  supabase: SupabaseAdminClient,
  payload: RtdhEventPayload,
  eventType: SupportedEventType,
  tenantId: string,
  requestId: string,
  rtdhEventId: string | null = null,
): Promise<Record<string, unknown>> {
  const orderId = await resolveOrderIdFromPayload(supabase, payload, tenantId);
  const payment = asObject(payload.payment);
  const payloadStripePaymentIntentId = payment
    ? asNonEmptyString(payment.payment_intent_id)
    : null;
  const isMigratedEvent = isMigratedEventPayload(payload);

  console.debug("rtdh-webhook: processEventAction dispatching", {
    requestId,
    eventType,
    orderId,
    tenantId,
    payloadStripePaymentIntentId,
    isMigratedEvent,
  });

  switch (eventType) {
    case "order.linked":
      console.debug("rtdh-webhook: dispatching to handleOrderLinked", {
        requestId,
        orderId,
      });
      return handleOrderLinked(
        supabase,
        orderId,
        tenantId,
        requestId,
        payloadStripePaymentIntentId,
      );
    case "order.fulfillment_linked":
      console.debug(
        "rtdh-webhook: dispatching to handleOrderFulfillmentLinked",
        {
          requestId,
          orderId,
        },
      );
      return handleOrderFulfillmentLinked(
        supabase,
        orderId,
        tenantId,
        requestId,
      );
    case "order_validation_pending":
    case "provider_review_pending":
    case "provider_approved":
    case "provider_rejected":
    case "medical_followup_required":
    case "payment_pending":
    case "payment_collected":
    case "payment_failed":
    case "order_sent_to_pharmacy":
    case "pharmacy_approval_pending":
    case "pharmacy_approved":
    case "fulfillment_in_progress":
    case "final_pharmacy_verification":
    case "in_transit":
    case "delivered":
    case "shipping_exception":
    case "order_cancelled":
    case "order_pending_cancellation":
      console.debug("rtdh-webhook: dispatching direct status event", {
        requestId,
        orderId,
        eventType,
      });
      return handleDirectStatusEvent(
        supabase,
        orderId,
        tenantId,
        eventType,
        requestId,
        { skipLifecycle: isMigratedEvent, rtdhEventId },
      );
  }
}

async function handleEvent(req: Request, requestId: string): Promise<Response> {
  console.debug("rtdh-webhook/event: starting request processing", {
    requestId,
    method: req.method,
  });

  if (req.method !== "POST") {
    console.warn("rtdh-webhook/event: request rejected - method not POST", {
      requestId,
      method: req.method,
    });
    return errorResponse(
      req,
      "method_not_allowed",
      "Use POST for /event",
      405,
      requestId,
    );
  }

  const supabase = getSupabaseAdminClient(requestId);

  if (!(await isAuthorized(req, supabase, requestId))) {
    console.warn("rtdh-webhook/event: request rejected - unauthorized", {
      requestId,
    });
    return errorResponse(
      req,
      "unauthorized",
      "Invalid RTDH webhook secret",
      401,
      requestId,
    );
  }

  const requestHeaders = Object.fromEntries(req.headers.entries());
  const qaBypass =
    req.headers.get("x-qa-bypass")?.trim().toLowerCase() === "true";
  const { content, contentLength, readError } = await readRequestBody(req);

  if (readError) {
    console.error("rtdh-webhook/event: failed to read request body", {
      requestId,
      readError,
    });
    return errorResponse(
      req,
      "read_error",
      "Failed to read request body",
      400,
      requestId,
      { readError },
    );
  }

  let parsed: unknown = null;
  let parseError: string | null = null;

  try {
    parsed = content ? JSON.parse(content) : null;
  } catch (error) {
    parseError = error instanceof Error ? error.message : String(error);
  }

  if (parseError) {
    console.warn("rtdh-webhook/event: JSON parse failed", {
      requestId,
      parseError,
      contentLength,
    });
  }

  console.info("rtdh-webhook/event: received request body", {
    requestId,
    method: req.method,
    url: req.url,
    headers: requestHeaders,
    rawContent: content,
    rawContentLength: contentLength,
    rawReadError: readError,
    rawParseError: parseError,
    parsedPayload: parsed,
  });

  const payload = asObject(parsed);
  if (!payload) {
    console.warn("rtdh-webhook/event: payload is not a valid object", {
      requestId,
      parseError,
      parsedType: typeof parsed,
    });
    return errorResponse(
      req,
      "invalid_json",
      "Payload must be a valid JSON object",
      400,
      requestId,
      { parseError },
    );
  }

  if (isChatMessageReceivedPayload(payload)) {
    return handleChatMessageReceivedEvent(req, supabase, payload, requestId);
  }

  const questionnaireEvent = parseQuestionnaireSubmittedEvent(payload);
  if (questionnaireEvent) {
    if (questionnaireEvent.errors.length > 0 || !questionnaireEvent.event) {
      console.warn(
        "rtdh-webhook/event: questionnaire submitted payload validation failed",
        {
          requestId,
          eventType: questionnaireEvent.eventType,
          errors: questionnaireEvent.errors,
        },
      );
      return errorResponse(
        req,
        "validation_error",
        "Invalid RTDH questionnaire submitted payload",
        422,
        requestId,
        questionnaireEvent.errors,
      );
    }

    const resolvedTenantId = await resolveTenantId(
      supabase,
      questionnaireEvent.event.tenantIdentifier,
    );

    if (!resolvedTenantId) {
      return errorResponse(
        req,
        "reference_not_found",
        "Unable to resolve tenant from questionnaire submitted payload",
        422,
        requestId,
        [
          `tenant '${questionnaireEvent.event.tenantIdentifier}' does not match any tenant id or slug`,
        ],
      );
    }

    const orderTenantId = await fetchOrderTenantId(
      supabase,
      questionnaireEvent.event.orderId,
    );
    if (!orderTenantId) {
      return errorResponse(
        req,
        "reference_not_found",
        "Questionnaire submitted payload references an unknown order",
        422,
        requestId,
        [
          `patient_platform_order_id '${questionnaireEvent.event.orderId}' does not match any order`,
        ],
      );
    }

    if (orderTenantId !== resolvedTenantId) {
      return errorResponse(
        req,
        "reference_mismatch",
        "Questionnaire submitted payload tenant does not match the order tenant",
        422,
        requestId,
        [
          `patient_platform_order_id '${questionnaireEvent.event.orderId}' belongs to tenant '${orderTenantId}', not '${resolvedTenantId}'`,
        ],
      );
    }

    const actionResult = await handleJotformQuestionnaireSubmitted(
      questionnaireEvent.event.orderId,
      resolvedTenantId,
      questionnaireEvent.event.submissionId,
      requestId,
      questionnaireEvent.event.questionnaireType,
      questionnaireEvent.event.eventType,
    );

    return jsonResponse(
      req,
      {
        received: true,
        requestId,
        eventType: questionnaireEvent.event.eventType,
        actionResult,
      },
      200,
      { "x-request-id": requestId },
    );
  }

  const validationErrors = validatePayload(payload, { qaBypass });
  if (validationErrors.length > 0) {
    console.warn("rtdh-webhook/event: payload validation failed", {
      requestId,
      qaBypass,
      errorCount: validationErrors.length,
      errors: validationErrors,
    });
    return errorResponse(
      req,
      "validation_error",
      "Invalid RTDH event payload",
      200,
      requestId,
      validationErrors,
    );
  }

  console.info("rtdh-webhook/event: payload validation passed", {
    requestId,
    qaBypass,
  });

  const typedPayload = payload as unknown as RtdhEventPayload;

  if (isRenewalOrderCreateIntent(req, typedPayload)) {
    console.info("rtdh-webhook/event: renewal intent detected", {
      requestId,
      intent: "renewal_order_create",
    });

    return processRenewalIntent({
      req,
      supabase,
      payload: typedPayload,
      requestId,
      jsonResponse,
      errorResponse,
      lifecycleTrigger: triggerOrderLifecycleForOrder,
    });
  }

  // Stripe customer.updated (patient updated payment details, e.g. after a
  // failed renewal payment). Handled as a payment-retry action instead of a
  // status event: as a status event it maps to payment_pending, which the
  // direct-status regression guard below would (correctly) drop for orders
  // sitting in payment_failed — so no retry would ever run.
  if (isCustomerUpdatedEvent(typedPayload)) {
    const retryResult = await handleCustomerUpdatedPaymentRetry({
      supabase,
      payload: typedPayload,
      requestId,
      stripeSecretKeyResolver: getTenantStripeSecretKey,
      lifecycleTrigger: triggerOrderLifecycleForOrder,
    });

    console.info("rtdh-webhook/event: customer.updated processed", {
      requestId,
      ...retryResult,
    });

    return jsonResponse(
      req,
      {
        received: true,
        requestId,
        eventType: "customer.updated",
        actionResult: {
          action: `payment_retry_${retryResult.action}`,
          orderId: retryResult.orderId ?? null,
          invoiceId: retryResult.invoiceId ?? null,
          reason: retryResult.reason ?? null,
        },
      },
      200,
      { "x-request-id": requestId },
    );
  }

  const referenceValidation = await validateReferences(supabase, typedPayload);
  if (referenceValidation.errors.length > 0) {
    console.warn("rtdh-webhook/event: reference validation failed", {
      requestId,
      errorCount: referenceValidation.errors.length,
      errors: referenceValidation.errors,
    });
    return errorResponse(
      req,
      "reference_not_found",
      "One or more referenced entities do not exist",
      422,
      requestId,
      referenceValidation.errors,
    );
  }

  const resolvedTenantId = referenceValidation.resolvedTenantId;
  if (!resolvedTenantId) {
    return errorResponse(
      req,
      "reference_not_found",
      "Unable to resolve tenant id from internal_tenant_id",
      422,
      requestId,
    );
  }

  console.info("rtdh-webhook/event: reference validation passed", {
    requestId,
    resolvedTenantId,
  });

  // If invoice_id was provided but didn't match any existing transaction,
  // find the order's transaction via other identifiers and update it.
  if (referenceValidation.unmatchedInvoiceId) {
    const orderId = referenceValidation.resolvedOrderId;

    if (orderId) {
      const { error: invoiceUpdateError } = await supabase
        .from("order_payment_provider_transactions")
        .update({
          provider_invoice_id: referenceValidation.unmatchedInvoiceId,
        })
        .eq("order_id", orderId)
        .eq("tenant_id", resolvedTenantId)
        .is("provider_invoice_id", null);

      if (invoiceUpdateError) {
        console.warn(
          "rtdh-webhook/event: failed to update transaction with invoice_id",
          {
            requestId,
            orderId,
            invoiceId: referenceValidation.unmatchedInvoiceId,
            error: invoiceUpdateError.message,
          },
        );
      } else {
        console.info(
          "rtdh-webhook/event: updated transaction with invoice_id from payload",
          {
            requestId,
            orderId,
            invoiceId: referenceValidation.unmatchedInvoiceId,
          },
        );
      }
    }
  }

  const eventType = resolveForwardEventType(typedPayload);
  if (!eventType) {
    console.warn("rtdh-webhook/event: no event type found in payload", {
      requestId,
      eventType: typedPayload.event_type,
      orderStatusKey: typedPayload.order_status_key,
      globalStatus: typedPayload.global_status,
    });
    return errorResponse(
      req,
      "unsupported_event_type",
      "No event type found in payload",
      422,
      requestId,
    );
  }

  console.info("rtdh-webhook/event: resolved forward event type", {
    requestId,
    eventType,
    payloadEventType: typedPayload.event_type,
    orderStatusKey: typedPayload.order_status_key,
    globalStatus: typedPayload.global_status,
  });

  if (!isSupportedEventType(eventType)) {
    console.warn("rtdh-webhook/event: unsupported event type", {
      requestId,
      eventType,
      supported: [...SUPPORTED_EVENT_TYPES],
    });
    return errorResponse(
      req,
      "unsupported_event_type",
      `Unsupported event_type: '${eventType}'`,
      422,
      requestId,
      { event_type: eventType, supported: [...SUPPORTED_EVENT_TYPES] },
    );
  }

  console.info("rtdh-webhook/event: event type is supported", {
    requestId,
    eventType,
  });

  // Process the event action based on the most recent timeline event_type
  console.info("rtdh-webhook/event: processing event action", {
    requestId,
    eventType,
  });

  const rtdhEventId = extractRtdhEventId(typedPayload, requestHeaders);
  console.info("rtdh-webhook/event: resolved idempotency event id", {
    requestId,
    eventType,
    rtdhEventId,
  });

  const actionResult = await processEventAction(
    supabase,
    typedPayload,
    eventType as SupportedEventType,
    resolvedTenantId,
    requestId,
    rtdhEventId,
  );

  console.info("rtdh-webhook/event: event action completed", {
    requestId,
    eventType,
    actionResult,
  });

  return jsonResponse(
    req,
    {
      received: true,
      requestId,
      eventType,
      actionResult,
    },
    200,
    { "x-request-id": requestId },
  );
}

Deno.serve(async (req: Request) => {
  const requestId = req.headers.get("x-request-id")?.trim() ||
    crypto.randomUUID();
  const path = normalizePath(new URL(req.url).pathname);

  if (req.method === "OPTIONS") {
    return new Response(null, {
      status: 204,
      headers: getCorsHeaders(req),
    });
  }

  if (path === "/event") {
    try {
      return await handleEvent(req, requestId);
    } catch (error) {
      console.error("rtdh-webhook/event: unhandled request failure", {
        requestId,
        error: error instanceof Error ? error.message : String(error),
        stack: error instanceof Error ? error.stack : null,
      });
      return errorResponse(
        req,
        "internal_error",
        "Unhandled RTDH webhook processing error",
        500,
        requestId,
      );
    }
  }

  return errorResponse(req, "not_found", "Route not found", 404, requestId);
});
