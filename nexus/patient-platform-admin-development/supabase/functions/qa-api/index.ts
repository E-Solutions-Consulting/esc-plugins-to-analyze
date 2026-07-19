import { serve } from "https://deno.land/std@0.190.0/http/server.ts";
import {
  createClient,
  type SupabaseClient,
} from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  isDevelopmentEnvironment,
  isNonLiveEnvironment,
  isStagingEnvironment,
} from "../_shared/environment.ts";
import {
  appendTelegraRequestTimestamp,
  resolveTelegraAccessToken,
} from "../_shared/telegra-auth.ts";
import {
  buildQaOrderAddressFields,
  type QaAddressSource,
  type QaResolvedAddress,
  resolveQaShippingAddress,
} from "./order-flow.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

interface ProductRecord {
  id: string;
  tenant_id: string;
  name: string;
  description: string | null;
  terms_and_conditions_html: string | null;
  price_cents: number;
  payment_type: string | null;
  subscription_interval: string | null;
  subscription_interval_count: number | null;
  subscription_renewal_lead_days: number | null;
  image_url: string | null;
  metadata: Record<string, unknown> | null;
}

interface PatientRecord {
  id: string;
  tenant_id: string;
  email: string;
  metadata: Record<string, unknown> | null;
  email_verified_at: string | null;
  first_name: string | null;
  last_name: string | null;
  country: string | null;
  shipping_first_name: string | null;
  shipping_last_name: string | null;
  shipping_company: string | null;
  shipping_address_line1: string | null;
  shipping_address_line2: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string | null;
  shipping_instructions: string | null;
}

interface StripeSettings {
  secret_key?: string;
  webhook_secret?: string;
}

interface StripeProviderRow {
  id: string;
  settings: StripeSettings | null;
  payment_providers?: {
    id?: string;
    key?: string;
    name?: string;
  } | null;
}

interface StripeCustomerResponse {
  id?: string;
}

interface StripePaymentIntentResponse {
  id: string;
  object: string;
  status?: string | null;
  customer?: string | null;
  currency?: string | null;
  amount?: number | null;
  payment_method?: string | null;
  latest_charge?: string | null;
  invoice?: string | null;
  metadata?: Record<string, string>;
}

interface StripePaymentMethodResponse {
  id?: string;
  customer?: string | { id?: string } | null;
}

interface StripeSetupIntentResponse {
  id?: string;
  status?: string | null;
  payment_method?: string | null;
}

interface StripeCouponResponse {
  id?: string;
  name?: string | null;
  valid?: boolean | null;
  duration?: string | null;
  percent_off?: number | null;
  amount_off?: number | null;
  currency?: string | null;
  applies_to?: { products?: string[] | null } | null;
  metadata?: Record<string, string | undefined> | null;
}

interface StripePromotionCodeResponse {
  id?: string;
  code?: string | null;
  active?: boolean | null;
  expires_at?: number | null;
  max_redemptions?: number | null;
  times_redeemed?: number | null;
  coupon?: string | StripeCouponResponse | null;
  promotion?: {
    coupon?: string | StripeCouponResponse | null;
  } | null;
  metadata?: Record<string, string | undefined> | null;
}

interface QaDiscountContext {
  couponId: string | null;
  promotionCodeId: string | null;
  promotionCode: string | null;
  couponName: string | null;
  duration: string | null;
  percentOff: number | null;
  amountOff: number | null;
  currency: string | null;
  appliesToProducts: string[];
  discountCents: number;
  totalCents: number;
}

interface QaOrderSummary {
  id: string;
  order_number: string | null;
  subscription_id: string | null;
  paid_at: string | null;
  renewal_at: string | null;
}

interface OrderRecord {
  id: string;
  tenant_id: string;
  provider_platform_integration_key: string | null;
  order_statuses?: {
    status_key: string | null;
  } | null;
}

interface OrderProviderPlatformOrderRecord {
  id: string;
  provider_platform_order_id: string | null;
}

interface ProviderPlatformOrderIdLinkRow {
  provider_order_id: string | null;
}

interface TelegraTenantIntegration {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
  settings: Record<string, unknown> | null;
}

interface OrderProviderPlatformLinkRow {
  id: string;
  metadata: Record<string, unknown> | null;
  provider_order_id: string | null;
  tenant_integration_id: string;
  tenant_integrations:
    | TelegraTenantIntegration
    | TelegraTenantIntegration[]
    | null;
}

interface AuthenticatedAdminContext {
  id: string;
  email: string | null;
  is_platform_superadmin: boolean;
  tenant_ids: string[];
}

interface AdminUserAuthRow {
  id: string;
  email: string | null;
}

interface UserRoleRow {
  role: string;
}

interface TenantMembershipRow {
  tenant_id: string | null;
}

async function attachPaymentMethodToCustomer(params: {
  secretKey: string;
  paymentMethodId: string;
  customerId: string;
  requestId: string;
}): Promise<void> {
  const { secretKey, paymentMethodId, customerId, requestId } = params;

  try {
    await callStripeApi<Record<string, unknown>>({
      secretKey,
      path: `/v1/payment_methods/${paymentMethodId}/attach`,
      requestId,
      body: new URLSearchParams({
        customer: customerId,
      }),
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    if (
      message.includes("already been attached") ||
      message.includes("already attached")
    ) {
      return;
    }
    throw error;
  }
}

async function createAttachedQaTestPaymentMethod(params: {
  secretKey: string;
  customerId: string;
  patient: PatientRecord;
  requestId: string;
}): Promise<string> {
  const { secretKey, customerId, patient, requestId } = params;
  const body = new URLSearchParams({
    type: "card",
    "card[token]": "tok_visa",
    "billing_details[email]": patient.email,
  });
  const fullName = [patient.first_name, patient.last_name]
    .map((part) => typeof part === "string" ? part.trim() : "")
    .filter(Boolean)
    .join(" ");
  if (fullName) {
    body.set("billing_details[name]", fullName);
  }

  const paymentMethod = await callStripeApi<StripePaymentMethodResponse>({
    secretKey,
    path: "/v1/payment_methods",
    requestId,
    body,
  });
  const paymentMethodId = typeof paymentMethod.id === "string"
    ? paymentMethod.id.trim()
    : "";
  if (!paymentMethodId) {
    throw new Error("qa_payment_method_create_missing_id");
  }

  await attachPaymentMethodToCustomer({
    secretKey,
    paymentMethodId,
    customerId,
    requestId,
  });

  return paymentMethodId;
}

interface QaApiLogContext {
  requestId?: string;
  method?: string;
  path?: string;
  route?: string;
}

function redactForLog(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map((item) => redactForLog(item));
  }

  if (!value || typeof value !== "object") {
    return value;
  }

  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>).map(([key, entry]) => {
      if (
        /authorization|api[-_]?key|secret|token|password|signature/i.test(key)
      ) {
        return [key, "[redacted]"];
      }

      return [key, redactForLog(entry)];
    }),
  );
}

function logQaApiResponse(
  payload: unknown,
  status: number,
  context: QaApiLogContext = {},
) {
  const logPayload = {
    ...context,
    status,
    payload: redactForLog(payload),
  };

  if (status >= 500) {
    console.error("qa-api response error", logPayload);
    return;
  }

  if (status >= 400) {
    console.warn("qa-api response error", logPayload);
    return;
  }

  console.log("qa-api response payload", logPayload);
}

function jsonResponse(
  payload: unknown,
  status: number,
  corsHeaders: Record<string, string>,
  context: QaApiLogContext = {},
) {
  logQaApiResponse(payload, status, context);

  return new Response(JSON.stringify(payload), {
    status,
    headers: {
      ...corsHeaders,
      "Content-Type": "application/json",
    },
  });
}

function normalizePath(pathname: string): string {
  let path = pathname.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/qa-api")) {
    path = path.slice("/qa-api".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) {
    path = path.slice(0, -1);
  }
  return path;
}

function extractQaSecret(req: Request): string | null {
  const bearerHeader = req.headers.get("authorization") || "";
  if (bearerHeader.toLowerCase().startsWith("bearer ")) {
    const bearerToken = bearerHeader.slice(7).trim();
    if (bearerToken) return bearerToken;
  }

  const headerSecret = req.headers.get("x-qa-api-key")?.trim();
  return headerSecret || null;
}

function getStringParam(
  body: Record<string, unknown>,
  url: URL,
  ...names: string[]
): string | null {
  for (const name of names) {
    const bodyValue = body[name];
    if (typeof bodyValue === "string" && bodyValue.trim()) {
      return bodyValue.trim();
    }

    const queryValue = url.searchParams.get(name);
    if (queryValue?.trim()) {
      return queryValue.trim();
    }
  }

  return null;
}

function getBooleanParam(
  body: Record<string, unknown>,
  url: URL,
  name: string,
  defaultValue: boolean,
): boolean {
  const bodyValue = body[name];
  if (typeof bodyValue === "boolean") {
    return bodyValue;
  }
  if (typeof bodyValue === "string" && bodyValue.trim()) {
    return !["false", "0", "no"].includes(bodyValue.trim().toLowerCase());
  }

  const queryValue = url.searchParams.get(name);
  if (queryValue?.trim()) {
    return !["false", "0", "no"].includes(queryValue.trim().toLowerCase());
  }

  return defaultValue;
}

async function readJsonObjectBody(
  req: Request,
): Promise<Record<string, unknown>> {
  if (req.method !== "POST") {
    return {};
  }

  const contentType = req.headers.get("content-type") || "";
  if (!contentType.toLowerCase().includes("application/json")) {
    return {};
  }

  try {
    const value = await req.json();
    return value && typeof value === "object" && !Array.isArray(value)
      ? value as Record<string, unknown>
      : {};
  } catch {
    return {};
  }
}

async function callStripeApi<T>(params: {
  secretKey: string;
  method?: "GET" | "POST";
  path: string;
  requestId: string;
  body?: URLSearchParams;
  idempotencyKey?: string;
}): Promise<T> {
  const {
    secretKey,
    method = "POST",
    path,
    requestId,
    body,
    idempotencyKey,
  } = params;
  console.log("qa-api outgoing stripe request payload", {
    requestId,
    method,
    path,
    payload: body ? redactForLog(Object.fromEntries(body.entries())) : null,
  });

  const response = await fetch(`https://api.stripe.com${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${secretKey}`,
      ...(body ? { "Content-Type": "application/x-www-form-urlencoded" } : {}),
      ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
    },
    body: body?.toString(),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.error("qa-api outgoing stripe request failed", {
      requestId,
      method,
      path,
      status: response.status,
      payload: errorText,
    });
    throw new Error(
      `stripe_api_${method.toLowerCase()}_${path}_failed:${response.status}:${errorText}:${requestId}`,
    );
  }

  return await response.json() as T;
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

function getPromotionCodeCoupon(
  promotionCode: StripePromotionCodeResponse,
): string | StripeCouponResponse | null {
  return promotionCode.coupon ?? promotionCode.promotion?.coupon ?? null;
}

function getCouponObject(
  coupon: string | StripeCouponResponse | null | undefined,
): StripeCouponResponse | null {
  return coupon && typeof coupon === "object" ? coupon : null;
}

function calculateQaDiscountCents(
  coupon: StripeCouponResponse,
  product: ProductRecord,
): number {
  const priceCents = Math.max(0, product.price_cents || 0);
  if (typeof coupon.percent_off === "number" && coupon.percent_off > 0) {
    return Math.min(
      priceCents,
      Math.round(priceCents * (coupon.percent_off / 100)),
    );
  }

  if (
    typeof coupon.amount_off === "number" &&
    coupon.amount_off > 0 &&
    (!coupon.currency || coupon.currency.toLowerCase() === "usd")
  ) {
    return Math.min(priceCents, Math.round(coupon.amount_off));
  }

  return 0;
}

async function fetchStripeCoupon(params: {
  secretKey: string;
  couponId: string;
  requestId: string;
}): Promise<StripeCouponResponse | null> {
  const { secretKey, couponId, requestId } = params;
  return await callStripeApi<StripeCouponResponse>({
    secretKey,
    method: "GET",
    path: `/v1/coupons/${encodeURIComponent(couponId)}`,
    requestId,
  });
}

async function fetchStripePromotionCodeById(params: {
  secretKey: string;
  promotionCodeId: string;
  requestId: string;
}): Promise<StripePromotionCodeResponse | null> {
  const { secretKey, promotionCodeId, requestId } = params;
  try {
    return await callStripeApi<StripePromotionCodeResponse>({
      secretKey,
      method: "GET",
      path: `/v1/promotion_codes/${
        encodeURIComponent(promotionCodeId)
      }?expand[]=coupon&expand[]=promotion.coupon`,
      requestId,
    });
  } catch {
    return await callStripeApi<StripePromotionCodeResponse>({
      secretKey,
      method: "GET",
      path: `/v1/promotion_codes/${
        encodeURIComponent(promotionCodeId)
      }?expand[]=coupon`,
      requestId,
    });
  }
}

async function fetchStripePromotionCodeByCode(params: {
  secretKey: string;
  code: string;
  requestId: string;
}): Promise<StripePromotionCodeResponse | null> {
  const { secretKey, code, requestId } = params;
  try {
    const response = await callStripeApi<{
      data?: StripePromotionCodeResponse[];
    }>({
      secretKey,
      method: "GET",
      path: `/v1/promotion_codes?code=${
        encodeURIComponent(code)
      }&active=true&limit=1&expand[]=data.coupon&expand[]=data.promotion.coupon`,
      requestId,
    });

    return response.data?.[0] ?? null;
  } catch {
    const response = await callStripeApi<{
      data?: StripePromotionCodeResponse[];
    }>({
      secretKey,
      method: "GET",
      path: `/v1/promotion_codes?code=${
        encodeURIComponent(code)
      }&active=true&limit=1&expand[]=data.coupon`,
      requestId,
    });

    return response.data?.[0] ?? null;
  }
}

function assertPromotionCodeCanBeUsed(
  promotionCode: StripePromotionCodeResponse,
) {
  if (promotionCode.active === false) {
    throw new Error("qa_promotion_code_inactive");
  }

  if (
    typeof promotionCode.expires_at === "number" &&
    promotionCode.expires_at > 0 &&
    promotionCode.expires_at <= Math.floor(Date.now() / 1000)
  ) {
    throw new Error("qa_promotion_code_expired");
  }

  if (
    typeof promotionCode.max_redemptions === "number" &&
    typeof promotionCode.times_redeemed === "number" &&
    promotionCode.max_redemptions <= promotionCode.times_redeemed
  ) {
    throw new Error("qa_promotion_code_fully_redeemed");
  }
}

async function resolveQaDiscountContext(params: {
  secretKey: string;
  product: ProductRecord;
  requestId: string;
  promotionCodeId?: string | null;
  promotionCode?: string | null;
  couponId?: string | null;
  useDefaultCoupon: boolean;
}): Promise<QaDiscountContext | null> {
  const {
    secretKey,
    product,
    requestId,
    promotionCodeId,
    promotionCode,
    couponId,
    useDefaultCoupon,
  } = params;
  const hasExplicitDiscount = Boolean(
    promotionCodeId || promotionCode || couponId,
  );
  const defaultPromotionCodeId = !hasExplicitDiscount &&
      useDefaultCoupon &&
      typeof product.metadata?.stripe_promotion_code_id === "string"
    ? product.metadata.stripe_promotion_code_id.trim()
    : null;

  const selectedPromotionCodeId = promotionCodeId || defaultPromotionCodeId;
  if (couponId && (promotionCodeId || promotionCode)) {
    throw new Error(
      "qa_coupon_conflict: use promotion_code_id, promotion_code, or coupon_id",
    );
  }
  if (promotionCodeId && promotionCode) {
    throw new Error(
      "qa_coupon_conflict: use promotion_code_id or promotion_code, not both",
    );
  }

  let resolvedPromotionCode: StripePromotionCodeResponse | null = null;
  let resolvedCoupon: StripeCouponResponse | null = null;
  let resolvedCouponId: string | null = couponId || null;

  if (selectedPromotionCodeId) {
    resolvedPromotionCode = await fetchStripePromotionCodeById({
      secretKey,
      promotionCodeId: selectedPromotionCodeId,
      requestId,
    });
    if (!resolvedPromotionCode?.id) {
      throw new Error("qa_promotion_code_not_found");
    }
    assertPromotionCodeCanBeUsed(resolvedPromotionCode);

    const promotionCoupon = getPromotionCodeCoupon(resolvedPromotionCode);
    resolvedCouponId = getStripeObjectId(promotionCoupon);
    resolvedCoupon = getCouponObject(promotionCoupon);
  } else if (promotionCode) {
    resolvedPromotionCode = await fetchStripePromotionCodeByCode({
      secretKey,
      code: promotionCode,
      requestId,
    });
    if (!resolvedPromotionCode?.id) {
      throw new Error("qa_promotion_code_not_found");
    }
    assertPromotionCodeCanBeUsed(resolvedPromotionCode);

    const promotionCoupon = getPromotionCodeCoupon(resolvedPromotionCode);
    resolvedCouponId = getStripeObjectId(promotionCoupon);
    resolvedCoupon = getCouponObject(promotionCoupon);
  }

  if (resolvedCouponId && !resolvedCoupon) {
    resolvedCoupon = await fetchStripeCoupon({
      secretKey,
      couponId: resolvedCouponId,
      requestId,
    });
  }

  if (!resolvedCouponId || !resolvedCoupon) {
    return null;
  }
  if (resolvedCoupon.valid === false) {
    throw new Error("qa_coupon_invalid");
  }

  const couponAlliaProductId = resolvedCoupon.metadata?.allia_product_id
    ?.trim();
  if (couponAlliaProductId && couponAlliaProductId !== product.id) {
    throw new Error("qa_coupon_product_mismatch");
  }

  const appliesToProducts = resolvedCoupon.applies_to?.products || [];
  const discountCents = calculateQaDiscountCents(resolvedCoupon, product);
  return {
    couponId: resolvedCouponId,
    promotionCodeId: resolvedPromotionCode?.id || null,
    promotionCode: resolvedPromotionCode?.code || promotionCode || null,
    couponName: resolvedCoupon.name || null,
    duration: resolvedCoupon.duration || null,
    percentOff: resolvedCoupon.percent_off ?? null,
    amountOff: resolvedCoupon.amount_off ?? null,
    currency: resolvedCoupon.currency || null,
    appliesToProducts,
    discountCents,
    totalCents: Math.max(0, product.price_cents - discountCents),
  };
}

function parseWebhookResponseBody(responseText: string): unknown {
  if (!responseText) return null;

  try {
    return JSON.parse(responseText);
  } catch {
    return { raw: responseText };
  }
}

function generateCheckoutOrderNumber(now: number = Date.now()): string {
  const timestamp = now.toString(36).toUpperCase();
  const random = Math.random().toString(36).substring(2, 6).toUpperCase();
  return `ORD-${timestamp}-${random}`;
}

async function getOrderCreatedStatusId(supabaseAdmin: SupabaseAdminClient) {
  const { data, error } = await supabaseAdmin
    .from("order_statuses")
    .select("id")
    .eq("status_key", "order_created")
    .eq("is_active", true)
    .maybeSingle();

  if (error) {
    throw new Error(`order_created_status_fetch_failed:${error.message}`);
  }

  if (!data?.id) {
    throw new Error("order_created_status_not_found");
  }

  return data.id as string;
}

async function upsertQaOrderPaymentTransaction(params: {
  supabaseAdmin: SupabaseAdminClient;
  tenantId: string;
  orderId: string;
  subscriptionId: string | null;
  stripePaymentProviderId: string;
  providerPaymentIntentId?: string | null;
  providerCustomerId: string | null;
  paymentStatus: string | null;
}) {
  const {
    supabaseAdmin,
    tenantId,
    orderId,
    subscriptionId,
    stripePaymentProviderId,
    providerPaymentIntentId,
    providerCustomerId,
    paymentStatus,
  } = params;

  const payload: Record<string, unknown> = {
    tenant_id: tenantId,
    order_id: orderId,
    subscription_id: subscriptionId,
    payment_provider_id: stripePaymentProviderId,
    payment_status: paymentStatus || "pending",
  };

  if (providerPaymentIntentId) {
    payload.provider_payment_intent_id = providerPaymentIntentId;
  }

  if (providerCustomerId) {
    payload.provider_customer_id = providerCustomerId;
  }

  const transactionLookup = supabaseAdmin
    .from("order_payment_provider_transactions")
    .select("id")
    .eq("tenant_id", tenantId)
    .eq("order_id", orderId)
    .eq("payment_provider_id", stripePaymentProviderId);
  const { data: existingByIntent, error: intentLookupError } =
    providerPaymentIntentId
      ? await transactionLookup
        .eq("provider_payment_intent_id", providerPaymentIntentId)
        .maybeSingle()
      : await transactionLookup
        .is("provider_payment_intent_id", null)
        .maybeSingle();

  if (intentLookupError) {
    throw new Error(
      `order_transaction_lookup_failed:${intentLookupError.message}`,
    );
  }

  const existingTransactionId = existingByIntent?.id as string | undefined;
  const { error } = existingTransactionId
    ? await supabaseAdmin
      .from("order_payment_provider_transactions")
      .update(payload)
      .eq("id", existingTransactionId)
    : await supabaseAdmin
      .from("order_payment_provider_transactions")
      .insert(payload);

  if (error) {
    throw new Error(`order_transaction_upsert_failed:${error.message}`);
  }
}

async function ensureQaLocalSubscription(params: {
  supabaseAdmin: SupabaseAdminClient;
  product: ProductRecord;
  patient: PatientRecord;
}): Promise<string | null> {
  const { supabaseAdmin, product, patient } = params;
  if (product.payment_type !== "subscription") return null;

  const { data: existing, error: existingError } = await supabaseAdmin
    .from("subscriptions")
    .select("id")
    .eq("tenant_id", product.tenant_id)
    .eq("patient_id", patient.id)
    .eq("product_id", product.id)
    .eq("status", "pending_validation")
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (existingError) {
    throw new Error(
      `qa_subscription_lookup_failed:${existingError.message}`,
    );
  }
  if (existing?.id) return existing.id as string;

  const { data: created, error: createError } = await supabaseAdmin
    .from("subscriptions")
    .insert({
      tenant_id: product.tenant_id,
      patient_id: patient.id,
      product_id: product.id,
      status: "pending_validation",
      started_at: new Date().toISOString(),
    })
    .select("id")
    .single();

  if (createError) {
    throw new Error(`qa_subscription_create_failed:${createError.message}`);
  }
  return created.id as string;
}

async function createQaOrder(params: {
  supabaseAdmin: SupabaseAdminClient;
  product: ProductRecord;
  patient: PatientRecord;
  address: QaResolvedAddress;
  amountTotal: number;
  discountContext?: QaDiscountContext | null;
}): Promise<QaOrderSummary> {
  const {
    supabaseAdmin,
    product,
    patient,
    address,
    amountTotal,
    discountContext,
  } = params;
  const subscriptionId = await ensureQaLocalSubscription({
    supabaseAdmin,
    product,
    patient,
  });
  const orderCreatedStatusId = await getOrderCreatedStatusId(supabaseAdmin);
  const nowIso = new Date().toISOString();
  const addressFields = buildQaOrderAddressFields(address);

  const { data: insertedOrder, error: insertOrderError } = await supabaseAdmin
    .from("orders")
    .insert({
      order_number: generateCheckoutOrderNumber(),
      tenant_id: product.tenant_id,
      patient_id: patient.id,
      product_id: product.id,
      subscription_id: subscriptionId,
      status_id: orderCreatedStatusId,
      status_changed_at: nowIso,
      subtotal_cents: product.price_cents,
      tax_cents: 0,
      shipping_cents: 0,
      total_cents: amountTotal,
      discount_cents: discountContext?.discountCents ?? 0,
      coupon_code: discountContext?.promotionCode ?? null,
      coupon_name: discountContext?.couponName ?? null,
      internal_notes: "Order created via QA embedded checkout simulation",
      paid_at: null,
      ...addressFields,
    })
    .select("id, order_number, subscription_id, paid_at, renewal_at")
    .single<QaOrderSummary>();

  if (insertOrderError || !insertedOrder) {
    throw new Error(
      `order_create_failed:${insertOrderError?.message || "missing order"}`,
    );
  }

  const { error: historyError } = await supabaseAdmin
    .from("order_status_history")
    .insert({
      order_id: insertedOrder.id,
      status_id: orderCreatedStatusId,
      notes: "Order created via QA embedded checkout simulation",
    });

  if (historyError) {
    console.warn("qa-api order status history insert failed", {
      orderId: insertedOrder.id,
      error: historyError.message,
    });
  }

  return insertedOrder;
}

function normalizeIntegrationKey(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

function normalizeProviderPlatformIdentifier(
  value: string | null | undefined,
): string | null {
  if (!value) return null;

  const normalized = value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "");

  return normalized.length > 0 ? normalized : null;
}

function isTelegraProviderPlatform(value: string | null | undefined): boolean {
  const normalizedValue = normalizeProviderPlatformIdentifier(value);
  return normalizedValue === "telegramd" || normalizedValue === "telegra";
}

function extractProviderNameFromMetadata(metadata: unknown): string | null {
  if (!metadata || typeof metadata !== "object") return null;

  const rawValue = (metadata as Record<string, unknown>).provider;
  return typeof rawValue === "string" && rawValue.trim().length > 0
    ? rawValue.trim()
    : null;
}

function normalizeTelegraOrderScopedId(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.startsWith("order::") ? trimmedValue : null;
}

function getStringSetting(
  settings: Record<string, unknown> | null | undefined,
  key: string,
): string | null {
  const value = settings?.[key];
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.length > 0 ? trimmedValue : null;
}

function normalizeNonEmptyString(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.length > 0 ? trimmedValue : null;
}

function isTelegraOrderProviderLink(
  link: OrderProviderPlatformLinkRow,
  order: OrderRecord,
): boolean {
  const providerName = extractProviderNameFromMetadata(link.metadata);
  const tenantIntegration = Array.isArray(link.tenant_integrations)
    ? link.tenant_integrations[0] || null
    : link.tenant_integrations;

  return (
    (tenantIntegration?.tenant_id === order.tenant_id &&
      tenantIntegration?.is_enabled === true &&
      normalizeIntegrationKey(tenantIntegration?.integration_key) ===
        "telegramd") ||
    isTelegraProviderPlatform(providerName)
  );
}

async function callTelegraApprovePrescription(params: {
  baseUrl: string;
  accessToken: string;
  telegraOrderId: string;
  requestId: string;
}): Promise<unknown> {
  const { baseUrl, accessToken, telegraOrderId, requestId } = params;

  const endpointBase = `${baseUrl.replace(/\/+$/, "")}/orders/${
    encodeURIComponent(telegraOrderId)
  }/actions/lifecycleProcessor/approvePrescription/`;
  const endpoint = `${endpointBase}?access_token=${
    encodeURIComponent(accessToken)
  }`;
  const requestEndpoint = appendTelegraRequestTimestamp(endpoint);

  console.log("qa-api outgoing telegra approve prescription request", {
    requestId,
    route: "approve_order_prescription",
    url: endpointBase,
    payload: null,
    params: { telegra_order_id: telegraOrderId },
  });

  const response = await fetch(requestEndpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "x-request-id": requestId,
      "x-source": "qa-api",
    },
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;

  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `telegra_approve_prescription_failed:${response.status}:${
        typeof responseBody === "string"
          ? responseBody
          : JSON.stringify(responseBody)
      }`,
    );
  }

  return responseBody;
}

async function getAuthenticatedAdmin(params: {
  supabaseAuthClient: SupabaseAdminClient;
  supabaseAdmin: SupabaseAdminClient;
  authHeader: string | null;
  corsHeaders: Record<string, string>;
  logContext: QaApiLogContext;
}): Promise<
  | { admin: AuthenticatedAdminContext; response: null }
  | { admin: null; response: Response }
> {
  const {
    supabaseAuthClient,
    supabaseAdmin,
    authHeader,
    corsHeaders,
    logContext,
  } = params;

  if (!authHeader) {
    return {
      admin: null,
      response: jsonResponse(
        { error: "Authorization header required" },
        401,
        corsHeaders,
        logContext,
      ),
    };
  }

  const {
    data: { user },
    error: userError,
  } = await supabaseAuthClient.auth.getUser();

  if (userError || !user) {
    return {
      admin: null,
      response: jsonResponse(
        { error: "Invalid or expired token" },
        401,
        corsHeaders,
        logContext,
      ),
    };
  }

  const { data: adminUser, error: adminUserError } = await supabaseAdmin
    .from("admin_users")
    .select("id, email")
    .eq("auth_user_id", user.id)
    .maybeSingle<AdminUserAuthRow>();

  if (adminUserError) {
    throw new Error(`admin_user_fetch_failed:${adminUserError.message}`);
  }

  if (!adminUser) {
    return {
      admin: null,
      response: jsonResponse(
        { error: "Admin access required" },
        403,
        corsHeaders,
        logContext,
      ),
    };
  }

  const { data: userRoles, error: userRolesError } = await supabaseAdmin
    .from("user_roles")
    .select("role")
    .eq("user_id", adminUser.id) as {
      data: UserRoleRow[] | null;
      error: { message: string } | null;
    };

  if (userRolesError) {
    throw new Error(`admin_roles_fetch_failed:${userRolesError.message}`);
  }

  const roles = new Set((userRoles || []).map((row) => row.role));
  const isPlatformSuperadmin = roles.has("platform_superadmin");
  const isTenantAdmin = roles.has("tenant_admin");

  if (!isPlatformSuperadmin && !isTenantAdmin) {
    return {
      admin: null,
      response: jsonResponse(
        { error: "Tenant admin access required" },
        403,
        corsHeaders,
        logContext,
      ),
    };
  }

  let tenantIds: string[] = [];
  if (!isPlatformSuperadmin) {
    const { data: memberships, error: membershipsError } = await supabaseAdmin
      .from("tenant_memberships")
      .select("tenant_id")
      .eq("admin_user_id", adminUser.id) as {
        data: TenantMembershipRow[] | null;
        error: { message: string } | null;
      };

    if (membershipsError) {
      throw new Error(
        `admin_memberships_fetch_failed:${membershipsError.message}`,
      );
    }

    tenantIds = (memberships || [])
      .map((membership) => membership.tenant_id)
      .filter((tenantId): tenantId is string => typeof tenantId === "string");

    if (tenantIds.length === 0) {
      return {
        admin: null,
        response: jsonResponse(
          { error: "No tenant access granted" },
          403,
          corsHeaders,
          logContext,
        ),
      };
    }
  }

  return {
    admin: {
      id: adminUser.id,
      email: adminUser.email,
      is_platform_superadmin: isPlatformSuperadmin,
      tenant_ids: tenantIds,
    },
    response: null,
  };
}

serve(async (req: Request) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-qa-api-key, x-request-id",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const url = new URL(req.url);
  const path = normalizePath(url.pathname);
  const requestId = req.headers.get("x-request-id") || crypto.randomUUID();
  const requestLogContext = {
    requestId,
    method: req.method,
    path,
  };
  let routeForLog: string | undefined;
  const respond = (
    payload: unknown,
    status: number,
    route?: string,
  ) =>
    jsonResponse(payload, status, corsHeaders, {
      ...requestLogContext,
      route,
    });

  console.log("qa-api request parameters", {
    ...requestLogContext,
    pathname: url.pathname,
    searchParams: Object.fromEntries(url.searchParams.entries()),
  });

  try {
    if (req.method !== "POST" && req.method !== "GET") {
      return respond({ error: "Method not allowed" }, 405);
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL");
    const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY");
    const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
    const qaApiSecret = Deno.env.get("QA_API_KEY");
    const authHeader = req.headers.get("Authorization");

    if (!supabaseUrl || !supabaseServiceKey) {
      return respond(
        { error: "Missing Supabase environment configuration" },
        500,
      );
    }

    const newOrderRouteMatch = path.match(/^\/new_order\/([^/]+)\/([^/]+)$/);
    const approveOrderPrescriptionRouteMatch = path.match(
      /^\/approve_order_prescription\/([^/]+)$/,
    );
    const adminApproveOrderPrescriptionRouteMatch = path.match(
      /^\/admin\/approve_order_prescription\/([^/]+)$/,
    );
    const providerPlatformOrderIdRouteMatch = path.match(
      /^\/provider_platform_order_id\/([^/]+)$/,
    );

    if (providerPlatformOrderIdRouteMatch) {
      if (
        !isDevelopmentEnvironment(supabaseUrl) &&
        !isStagingEnvironment(supabaseUrl)
      ) {
        return respond(
          {
            error:
              "provider_platform_order_id is only enabled in development and staging environments",
          },
          403,
          "provider_platform_order_id",
        );
      }
    } else if (adminApproveOrderPrescriptionRouteMatch) {
      if (
        !isDevelopmentEnvironment(supabaseUrl) &&
        !isStagingEnvironment(supabaseUrl)
      ) {
        return respond(
          {
            error:
              "admin approve_order_prescription is only enabled in development and staging environments",
          },
          403,
          "admin_approve_order_prescription",
        );
      }
    } else if (!isNonLiveEnvironment(supabaseUrl)) {
      return respond(
        { error: "qa-api is only enabled in non-live environments" },
        403,
      );
    }

    if (!adminApproveOrderPrescriptionRouteMatch && !qaApiSecret) {
      return respond(
        { error: "QA_API_KEY is not configured" },
        500,
      );
    }

    const providedSecret = adminApproveOrderPrescriptionRouteMatch
      ? qaApiSecret
      : extractQaSecret(req);
    if (
      !adminApproveOrderPrescriptionRouteMatch &&
      (!providedSecret || providedSecret !== qaApiSecret)
    ) {
      return respond({ error: "Unauthorized" }, 401);
    }

    if (
      !newOrderRouteMatch &&
      !approveOrderPrescriptionRouteMatch &&
      !adminApproveOrderPrescriptionRouteMatch &&
      !providerPlatformOrderIdRouteMatch
    ) {
      return respond(
        {
          error:
            "Route not found. Use /qa-api/new_order/{product-id}/{user-email}, /qa-api/approve_order_prescription/{order-id}, /qa-api/admin/approve_order_prescription/{order-id}, or /qa-api/provider_platform_order_id/{order-id}",
        },
        404,
      );
    }

    const supabaseAdmin = createClient(supabaseUrl, supabaseServiceKey, {
      auth: {
        autoRefreshToken: false,
        persistSession: false,
      },
    });

    const supabaseAuth = supabaseAnonKey
      ? createClient(supabaseUrl, supabaseAnonKey, {
        global: {
          headers: {
            ...(authHeader ? { Authorization: authHeader } : {}),
            "x-request-id": requestId,
            "x-request-source": "qa-api",
          },
        },
      })
      : null;

    if (providerPlatformOrderIdRouteMatch) {
      const route = "provider_platform_order_id";
      routeForLog = route;

      if (req.method !== "GET") {
        return respond({ error: "Method not allowed" }, 405, route);
      }

      const orderId = decodeURIComponent(providerPlatformOrderIdRouteMatch[1])
        .trim();
      console.log("qa-api route parameters", {
        ...requestLogContext,
        route,
        params: { order_id: orderId },
      });

      if (!orderId) {
        return respond(
          { error: "order-id is required" },
          400,
          route,
        );
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("id, provider_platform_order_id")
        .eq("id", orderId)
        .maybeSingle<OrderProviderPlatformOrderRecord>();

      if (orderError) {
        throw new Error(`order_fetch_failed:${orderError.message}`);
      }

      if (!order) {
        return respond({ error: "Order not found" }, 404, route);
      }

      let providerPlatformOrderId = normalizeNonEmptyString(
        order.provider_platform_order_id,
      );

      if (!providerPlatformOrderId) {
        const { data: providerLink, error: providerLinkError } =
          await supabaseAdmin
            .from("order_provider_platform_links")
            .select("provider_order_id")
            .eq("order_id", order.id)
            .not("provider_order_id", "is", null)
            .order("updated_at", { ascending: false, nullsFirst: false })
            .order("created_at", { ascending: false, nullsFirst: false })
            .limit(1)
            .maybeSingle<ProviderPlatformOrderIdLinkRow>();

        if (providerLinkError) {
          throw new Error(
            `order_provider_link_fetch_failed:${providerLinkError.message}`,
          );
        }

        providerPlatformOrderId = normalizeNonEmptyString(
          providerLink?.provider_order_id,
        );
      }

      return respond(
        {
          message: "Provider platform order id retrieved",
          data: {
            order_id: order.id,
            provider_platform_order_id: providerPlatformOrderId,
          },
        },
        200,
        route,
      );
    }

    if (
      approveOrderPrescriptionRouteMatch ||
      adminApproveOrderPrescriptionRouteMatch
    ) {
      const isAdminRoute = Boolean(adminApproveOrderPrescriptionRouteMatch);
      const route = isAdminRoute
        ? "admin_approve_order_prescription"
        : "approve_order_prescription";
      routeForLog = route;

      if (req.method !== "POST") {
        return respond({ error: "Method not allowed" }, 405, route);
      }

      if (
        !isDevelopmentEnvironment(supabaseUrl) &&
        !isStagingEnvironment(supabaseUrl)
      ) {
        return respond(
          {
            error:
              "approve_order_prescription is only enabled in development and staging environments",
          },
          403,
          route,
        );
      }

      if (isAdminRoute && !supabaseAuth) {
        return respond(
          { error: "Missing Supabase anonymous key configuration" },
          500,
          route,
        );
      }

      const adminAccess = isAdminRoute
        ? await getAuthenticatedAdmin({
          supabaseAuthClient: supabaseAuth!,
          supabaseAdmin,
          authHeader,
          corsHeaders,
          logContext: {
            ...requestLogContext,
            route,
          },
        })
        : null;

      if (adminAccess?.response) {
        return adminAccess.response;
      }

      const orderId = decodeURIComponent(
        (adminApproveOrderPrescriptionRouteMatch ||
          approveOrderPrescriptionRouteMatch)![1],
      ).trim();
      console.log("qa-api route parameters", {
        ...requestLogContext,
        route,
        params: { order_id: orderId },
      });

      if (!orderId) {
        return respond(
          { error: "order-id is required" },
          400,
          route,
        );
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select(`
          id,
          tenant_id,
          provider_platform_integration_key,
          order_statuses (
            status_key
          )
        `)
        .eq("id", orderId)
        .maybeSingle<OrderRecord>();

      if (orderError) {
        throw new Error(`order_fetch_failed:${orderError.message}`);
      }

      if (!order) {
        return respond({ error: "Order not found" }, 404, route);
      }

      if (
        isAdminRoute &&
        adminAccess?.admin &&
        !adminAccess.admin.is_platform_superadmin &&
        !adminAccess.admin.tenant_ids.includes(order.tenant_id)
      ) {
        return respond(
          { error: "You do not have access to this order" },
          403,
          route,
        );
      }

      if (
        isAdminRoute &&
        order.order_statuses?.status_key !== "provider_review_pending"
      ) {
        return respond(
          {
            error:
              "Telegra order prescription approval is only available while the order is pending provider review",
          },
          400,
          route,
        );
      }

      const { data: providerLinks, error: providerLinksError } =
        await supabaseAdmin
          .from("order_provider_platform_links")
          .select(`
            id,
            metadata,
            provider_order_id,
            tenant_integration_id,
            tenant_integrations!inner (
              id,
              tenant_id,
              integration_key,
              is_enabled,
              settings
            )
          `)
          .eq("order_id", order.id)
          .eq("tenant_id", order.tenant_id)
          .order("id", { ascending: true });

      if (providerLinksError) {
        throw new Error(
          `order_provider_link_fetch_failed:${providerLinksError.message}`,
        );
      }

      const normalizedLinks =
        (providerLinks || []) as OrderProviderPlatformLinkRow[];

      if (normalizedLinks.length > 1) {
        return respond(
          {
            error:
              "Order has multiple provider platform links; cannot unambiguously select a Telegra integration",
          },
          400,
          route,
        );
      }

      const selectedProviderLink = normalizedLinks[0] || null;
      const orderLooksTelegra =
        normalizeIntegrationKey(order.provider_platform_integration_key) ===
          "telegramd" ||
        Boolean(
          selectedProviderLink &&
            isTelegraOrderProviderLink(selectedProviderLink, order),
        );

      if (!orderLooksTelegra || !selectedProviderLink) {
        return respond(
          {
            error:
              "Order is not linked to a Telegra provider platform integration",
          },
          400,
          route,
        );
      }

      if (!isTelegraOrderProviderLink(selectedProviderLink, order)) {
        return respond(
          {
            error:
              "Order is not linked to a Telegra provider platform integration",
          },
          400,
          route,
        );
      }

      const telegraOrderId = normalizeTelegraOrderScopedId(
        selectedProviderLink.provider_order_id,
      );

      if (!telegraOrderId) {
        return respond(
          {
            error:
              "Telegra provider order id is missing or invalid for this order",
          },
          400,
          route,
        );
      }

      const selectedTenantIntegration = Array.isArray(
          selectedProviderLink.tenant_integrations,
        )
        ? selectedProviderLink.tenant_integrations[0] || null
        : selectedProviderLink.tenant_integrations;

      if (!selectedTenantIntegration) {
        return respond(
          {
            error:
              "Telegra tenant integration could not be resolved for this order",
          },
          400,
          route,
        );
      }

      const telegraBaseUrl = getStringSetting(
        selectedTenantIntegration.settings,
        "url",
      );

      if (!telegraBaseUrl) {
        return respond(
          { error: "Telegra integration is missing URL configuration" },
          400,
          route,
        );
      }

      const authResult = await resolveTelegraAccessToken({
        supabase: supabaseAdmin,
        tenantIntegrationId: selectedProviderLink.tenant_integration_id,
        tenantId: order.tenant_id,
        settings: selectedTenantIntegration.settings,
        baseUrl: telegraBaseUrl,
        requestId,
        source: "qa-api",
      });

      if ("errorMessage" in authResult) {
        return respond(
          { error: authResult.errorMessage },
          400,
          route,
        );
      }

      const telegraResponse = await callTelegraApprovePrescription({
        baseUrl: telegraBaseUrl,
        accessToken: authResult.accessToken,
        telegraOrderId,
        requestId,
      });

      return respond(
        {
          message: "Prescription approved",
          data: {
            order_id: order.id,
            telegra_order_id: telegraOrderId,
            telegra_response: telegraResponse,
          },
        },
        200,
        route,
      );
    }

    const route = "new_order";
    routeForLog = route;
    const productId = decodeURIComponent(newOrderRouteMatch![1]);
    const userEmail = decodeURIComponent(newOrderRouteMatch![2]).trim()
      .toLowerCase();
    const requestBody = await readJsonObjectBody(req);
    const requestedPromotionCodeId = getStringParam(
      requestBody,
      url,
      "promotion_code_id",
      "stripe_promotion_code_id",
    );
    const requestedPromotionCode = getStringParam(
      requestBody,
      url,
      "promotion_code",
      "coupon_code",
      "code",
    );
    const requestedCouponId = getStringParam(
      requestBody,
      url,
      "coupon_id",
      "stripe_coupon_id",
    );
    const useDefaultCoupon = getBooleanParam(
      requestBody,
      url,
      "use_default_coupon",
      true,
    );
    const requestedShippingAddress = requestBody.shipping_address &&
        typeof requestBody.shipping_address === "object" &&
        !Array.isArray(requestBody.shipping_address)
      ? requestBody.shipping_address as QaAddressSource
      : null;
    console.log("qa-api route parameters", {
      ...requestLogContext,
      route,
      params: {
        product_id: productId,
        user_email: userEmail,
        promotion_code_id: requestedPromotionCodeId,
        promotion_code: requestedPromotionCode,
        coupon_id: requestedCouponId,
        use_default_coupon: useDefaultCoupon,
        has_shipping_address_override: Boolean(requestedShippingAddress),
      },
    });

    if (!productId || !userEmail) {
      return respond(
        { error: "Both product-id and user-email are required" },
        400,
        route,
      );
    }

    const { data: product, error: productError } = await supabaseAdmin
      .from("products")
      .select(
        "id, tenant_id, name, description, terms_and_conditions_html, price_cents, payment_type, subscription_interval, subscription_interval_count, subscription_renewal_lead_days, image_url, metadata",
      )
      .eq("id", productId)
      .eq("is_enabled", true)
      .maybeSingle<ProductRecord>();

    if (productError) {
      throw new Error(`product_fetch_failed:${productError.message}`);
    }

    if (!product) {
      return respond({ error: "Product not found" }, 404, route);
    }

    const { data: patient, error: patientError } = await supabaseAdmin
      .from("patients")
      .select(
        "id, tenant_id, email, metadata, email_verified_at, first_name, last_name, country, shipping_first_name, shipping_last_name, shipping_company, shipping_address_line1, shipping_address_line2, shipping_city, shipping_state, shipping_postal_code, shipping_country, shipping_instructions",
      )
      .ilike("email", userEmail)
      .eq("tenant_id", product.tenant_id)
      .maybeSingle<PatientRecord>();

    if (patientError) {
      throw new Error(`patient_fetch_failed:${patientError.message}`);
    }

    if (!patient) {
      return respond(
        { error: "Patient not found for product tenant" },
        404,
        route,
      );
    }

    if (!patient.email_verified_at) {
      return respond(
        {
          error:
            "QA patient email must be verified before an order can enter provider intake",
        },
        409,
        route,
      );
    }

    const resolvedAddress = resolveQaShippingAddress({
      requested: requestedShippingAddress,
      patient,
    });
    if (!resolvedAddress.address) {
      return respond(
        {
          error: "A complete shipping address is required for QA orders",
          missing_fields: resolvedAddress.missing,
        },
        400,
        route,
      );
    }

    const { error: patientAddressUpdateError } = await supabaseAdmin
      .from("patients")
      .update({
        shipping_first_name: resolvedAddress.address.first_name,
        shipping_last_name: resolvedAddress.address.last_name,
        shipping_company: resolvedAddress.address.company,
        shipping_address_line1: resolvedAddress.address.line1,
        shipping_address_line2: resolvedAddress.address.line2,
        shipping_city: resolvedAddress.address.city,
        shipping_state: resolvedAddress.address.state,
        shipping_postal_code: resolvedAddress.address.postal_code,
        shipping_country: resolvedAddress.address.country,
        shipping_instructions: resolvedAddress.address.instructions,
      })
      .eq("id", patient.id)
      .eq("tenant_id", product.tenant_id);
    if (patientAddressUpdateError) {
      throw new Error(
        `patient_shipping_address_update_failed:${patientAddressUpdateError.message}`,
      );
    }

    const { data: stripeProvider, error: stripeProviderError } =
      await supabaseAdmin
        .from("tenant_payment_providers")
        .select(`
          id,
          settings,
          payment_providers!inner (
            id,
            key,
            name
          )
        `)
        .eq("tenant_id", product.tenant_id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle<StripeProviderRow>();

    if (stripeProviderError) {
      throw new Error(
        `stripe_provider_fetch_failed:${stripeProviderError.message}`,
      );
    }

    if (!stripeProvider?.settings?.secret_key) {
      return respond(
        { error: "Stripe is not configured for this tenant" },
        400,
        route,
      );
    }

    const stripeSecretKey = stripeProvider.settings.secret_key;
    const stripePaymentProviderId = stripeProvider.payment_providers?.id ||
      null;
    if (!stripePaymentProviderId) {
      throw new Error("stripe_payment_provider_id_not_found");
    }

    const isSubscription = product.payment_type === "subscription";
    const discountContext = await resolveQaDiscountContext({
      secretKey: stripeSecretKey,
      product,
      requestId,
      promotionCodeId: requestedPromotionCodeId,
      promotionCode: requestedPromotionCode,
      couponId: requestedCouponId,
      useDefaultCoupon,
    });
    const qaDiscountCents = discountContext?.discountCents ?? 0;
    const qaAmountTotal = discountContext?.totalCents ?? product.price_cents;
    const order = await createQaOrder({
      supabaseAdmin,
      product,
      patient,
      address: resolvedAddress.address,
      amountTotal: qaAmountTotal,
      discountContext,
    });

    const requiresStripeCustomer = qaAmountTotal > 0 || isSubscription;
    const existingStripeCustomerId = requiresStripeCustomer &&
        typeof patient.metadata?.stripe_customer_id === "string"
      ? patient.metadata.stripe_customer_id
      : null;
    const stripeCustomerId = requiresStripeCustomer
      ? existingStripeCustomerId ||
        (
          await callStripeApi<StripeCustomerResponse>({
            secretKey: stripeSecretKey,
            path: "/v1/customers",
            requestId,
            body: new URLSearchParams({
              email: patient.email,
              "metadata[tenant_id]": product.tenant_id,
              "metadata[patient_id]": patient.id,
            }),
          })
        ).id || null
      : null;

    if (requiresStripeCustomer && !stripeCustomerId) {
      throw new Error("stripe_customer_create_missing_id");
    }

    if (stripeCustomerId && !existingStripeCustomerId) {
      const metadata = {
        ...(patient.metadata || {}),
        stripe_customer_id: stripeCustomerId,
      };
      const { error: patientMetadataError } = await supabaseAdmin
        .from("patients")
        .update({ metadata })
        .eq("id", patient.id)
        .eq("tenant_id", product.tenant_id);
      if (patientMetadataError) {
        throw new Error(
          `patient_stripe_customer_cache_failed:${patientMetadataError.message}`,
        );
      }
    }

    const requestedPaymentMethodId = stripeCustomerId
      ? await createAttachedQaTestPaymentMethod({
        secretKey: stripeSecretKey,
        customerId: stripeCustomerId,
        patient,
        requestId,
      })
      : "pm_card_visa";

    let paymentIntent: StripePaymentIntentResponse | null = null;
    let setupIntent: StripeSetupIntentResponse | null = null;
    let resolvedPaymentMethodId: string | null = stripeCustomerId
      ? requestedPaymentMethodId
      : null;

    if (qaAmountTotal > 0) {
      if (!stripeCustomerId) {
        throw new Error("stripe_customer_required_for_payment_intent");
      }
      const paymentIntentParams = new URLSearchParams({
        amount: `${qaAmountTotal}`,
        currency: "usd",
        customer: stripeCustomerId,
        capture_method: "manual",
        description: product.name,
        receipt_email: patient.email,
        "automatic_payment_methods[enabled]": "true",
        "automatic_payment_methods[allow_redirects]": "never",
        "metadata[tenant_id]": product.tenant_id,
        "metadata[product_id]": product.id,
        "metadata[patient_id]": patient.id,
        "metadata[patient_platform_order_id]": order.id,
        "metadata[customer_email]": patient.email,
        "metadata[checkout_flow]": "qa_api_embedded",
        "metadata[qa_discount_cents]": `${qaDiscountCents}`,
      });
      if (isSubscription) {
        paymentIntentParams.set("setup_future_usage", "off_session");
      }
      if (discountContext?.promotionCode) {
        paymentIntentParams.set(
          "metadata[coupon_code]",
          discountContext.promotionCode,
        );
      }

      paymentIntent = await callStripeApi<StripePaymentIntentResponse>({
        secretKey: stripeSecretKey,
        path: "/v1/payment_intents",
        requestId,
        body: paymentIntentParams,
      });

      await upsertQaOrderPaymentTransaction({
        supabaseAdmin,
        tenantId: product.tenant_id,
        orderId: order.id,
        subscriptionId: order.subscription_id,
        stripePaymentProviderId,
        providerPaymentIntentId: paymentIntent.id,
        providerCustomerId: stripeCustomerId,
        paymentStatus: "pending",
      });
    } else if (isSubscription) {
      if (!stripeCustomerId) {
        throw new Error("stripe_customer_required_for_setup_intent");
      }
      setupIntent = await callStripeApi<StripeSetupIntentResponse>({
        secretKey: stripeSecretKey,
        path: "/v1/setup_intents",
        requestId,
        body: new URLSearchParams({
          customer: stripeCustomerId,
          payment_method: requestedPaymentMethodId,
          confirm: "true",
          usage: "off_session",
          "automatic_payment_methods[enabled]": "true",
          "automatic_payment_methods[allow_redirects]": "never",
          "metadata[tenant_id]": product.tenant_id,
          "metadata[product_id]": product.id,
          "metadata[patient_id]": patient.id,
          "metadata[patient_platform_order_id]": order.id,
          "metadata[checkout_flow]": "qa_api_embedded_setup",
        }),
      });
      if (setupIntent.status !== "succeeded") {
        throw new Error(
          `qa_setup_intent_not_succeeded:${setupIntent.status || "unknown"}`,
        );
      }
      resolvedPaymentMethodId = setupIntent.payment_method?.trim() ||
        resolvedPaymentMethodId;

      await upsertQaOrderPaymentTransaction({
        supabaseAdmin,
        tenantId: product.tenant_id,
        orderId: order.id,
        subscriptionId: order.subscription_id,
        stripePaymentProviderId,
        providerCustomerId: stripeCustomerId,
        paymentStatus: "no_payment_required",
      });
    }

    const lifecycleUrl =
      `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${order.id}`;
    console.log("qa-api outgoing order-lifecycle request", {
      ...requestLogContext,
      route,
      url: lifecycleUrl,
      payload: null,
    });
    const lifecycleResp = await fetch(lifecycleUrl, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${supabaseServiceKey}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-request-source": "qa-api:new_order",
      },
    });
    const lifecycleText = await lifecycleResp.text();
    if (!lifecycleResp.ok) {
      throw new Error(
        `order_lifecycle_failed:${lifecycleResp.status}:${lifecycleText}`,
      );
    }

    if (paymentIntent) {
      paymentIntent = await callStripeApi<StripePaymentIntentResponse>({
        secretKey: stripeSecretKey,
        path: `/v1/payment_intents/${paymentIntent.id}/confirm`,
        requestId,
        body: new URLSearchParams({
          payment_method: requestedPaymentMethodId,
        }),
      });
      if (paymentIntent.status !== "requires_capture") {
        throw new Error(
          `qa_payment_intent_not_authorized:${
            paymentIntent.status || "unknown"
          }`,
        );
      }
      resolvedPaymentMethodId = paymentIntent.payment_method?.trim() ||
        resolvedPaymentMethodId;

      await upsertQaOrderPaymentTransaction({
        supabaseAdmin,
        tenantId: product.tenant_id,
        orderId: order.id,
        subscriptionId: order.subscription_id,
        stripePaymentProviderId,
        providerPaymentIntentId: paymentIntent.id,
        providerCustomerId: stripeCustomerId,
        paymentStatus: paymentIntent.status,
      });
    }

    if (stripeCustomerId && resolvedPaymentMethodId) {
      try {
        await attachPaymentMethodToCustomer({
          secretKey: stripeSecretKey,
          paymentMethodId: resolvedPaymentMethodId,
          customerId: stripeCustomerId,
          requestId,
        });
      } catch (attachError) {
        console.warn("qa-api default payment method attach failed", {
          requestId,
          stripeCustomerId,
          paymentMethodId: resolvedPaymentMethodId,
          error: attachError instanceof Error
            ? attachError.message
            : String(attachError),
        });
      }

      try {
        await callStripeApi<Record<string, unknown>>({
          secretKey: stripeSecretKey,
          path: `/v1/customers/${stripeCustomerId}`,
          requestId,
          body: new URLSearchParams({
            "invoice_settings[default_payment_method]": resolvedPaymentMethodId,
          }),
        });
      } catch (defaultPaymentMethodError) {
        console.warn("qa-api default payment method update failed", {
          requestId,
          stripeCustomerId,
          paymentMethodId: resolvedPaymentMethodId,
          error: defaultPaymentMethodError instanceof Error
            ? defaultPaymentMethodError.message
            : String(defaultPaymentMethodError),
        });
      }
    }

    const { data: finalOrder, error: finalOrderError } = await supabaseAdmin
      .from("orders")
      .select(
        "id, order_number, subscription_id, paid_at, renewal_at, order_statuses(status_key)",
      )
      .eq("id", order.id)
      .eq("tenant_id", product.tenant_id)
      .maybeSingle();
    if (finalOrderError || !finalOrder) {
      throw new Error(
        `qa_order_final_read_failed:${
          finalOrderError?.message || "order not found"
        }`,
      );
    }
    const finalStatusRelation = finalOrder.order_statuses as
      | { status_key?: string | null }
      | Array<{ status_key?: string | null }>
      | null;
    const finalStatus = Array.isArray(finalStatusRelation)
      ? finalStatusRelation[0]?.status_key || null
      : finalStatusRelation?.status_key || null;

    return respond(
      {
        message: "QA order created",
        data: {
          tenant_id: product.tenant_id,
          patient_id: patient.id,
          product_id: product.id,
          amount_subtotal_cents: product.price_cents,
          amount_discount_cents: qaDiscountCents,
          amount_total_cents: qaAmountTotal,
          coupon_id: discountContext?.couponId || null,
          promotion_code_id: discountContext?.promotionCodeId || null,
          promotion_code: discountContext?.promotionCode || null,
          coupon_duration: discountContext?.duration || null,
          stripe_customer_id: stripeCustomerId,
          stripe_payment_method_id: resolvedPaymentMethodId,
          stripe_payment_intent_id: paymentIntent?.id || null,
          stripe_payment_intent_status: paymentIntent?.status || null,
          stripe_setup_intent_id: setupIntent?.id || null,
          stripe_setup_intent_status: setupIntent?.status || null,
          lifecycle_response: parseWebhookResponseBody(lifecycleText),
          order_status: finalStatus,
          lifecycle_pending: finalStatus === "order_created",
          order_created: true,
          order: {
            id: finalOrder.id,
            order_number: finalOrder.order_number,
            subscription_id: finalOrder.subscription_id,
            paid_at: finalOrder.paid_at,
            renewal_at: finalOrder.renewal_at,
          },
        },
      },
      200,
      route,
    );
  } catch (error) {
    console.error("qa-api request failed", {
      ...requestLogContext,
      route: routeForLog,
      error: error instanceof Error ? error.message : String(error),
    });

    return respond(
      {
        error: error instanceof Error ? error.message : "Unknown error",
        request_id: requestId,
      },
      500,
      routeForLog,
    );
  }
});
