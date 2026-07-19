// Plan API Edge Function
// Provides public/authenticated endpoints for Patient UI consumption

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { dateTime } from "../_shared/dayjs.ts";
import { isOrderPaymentAuthorized } from "../_shared/order-payment-authorization.ts";
import { resolveMdiAccessToken } from "../_shared/mdi-auth.ts";
import { resolveTelegraAccessToken } from "../_shared/telegra-auth.ts";
import {
  notifyRtdhOrderCancelled,
  notifyRtdhOrderStatusUpdatedAsync,
} from "../order-lifecycle/rtdh-helper.ts";
import {
  checkRateLimit,
  getCorsHeaders,
  isTelegraProviderIntegrationKey,
  normalizeTenantSlug,
  shouldDeferTelegraProviderReviewCancellation,
  type TelegraProviderReviewMetadata,
  validateStateAgainstTenant,
} from "./helpers.ts";

const STRIPE_REFILL_DATE_PRORATION_BEHAVIOR = "none";
const PROVIDER_CANCELLATION_FEE_CENTS = 5000;
const SHIPPING_ADDRESS_COLUMNS = [
  "shipping_first_name",
  "shipping_last_name",
  "shipping_company",
  "shipping_address_line1",
  "shipping_address_line2",
  "shipping_city",
  "shipping_state",
  "shipping_postal_code",
  "shipping_country",
  "shipping_instructions",
] as const;
const BILLING_ADDRESS_COLUMNS = [
  "billing_first_name",
  "billing_last_name",
  "billing_company",
  "billing_address_line1",
  "billing_address_line2",
  "billing_city",
  "billing_state",
  "billing_postal_code",
  "billing_country",
] as const;
const CANONICAL_ORDER_STATUS_KEYS = [
  "order_created",
  "shipping_details_required",
  "provider_order_creation_pending",
  "patient_questionnaire_pending",
  "medical_questionnaire_pending",
  "provider_review_pending",
  "provider_approved",
  "payment_pending",
  "payment_collected",
  "order_approved",
  "order_sent_to_pharmacy",
  "pharmacy_approval_pending",
  "pharmacy_approved",
  "fulfillment_in_progress",
  "final_pharmacy_verification",
  "in_transit",
  "delivered",
] as const;
const CUSTOMER_SUPPORT_ORDER_STATUS_KEYS = new Set([
  "provider_order_creation_pending",
  "order_on_hold",
  "order_cancelled",
]);

function normalizeIntegrationKey(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

function isMdiIntegrationKey(value: string | null | undefined): boolean {
  return normalizeIntegrationKey(value) === "md_integrations";
}

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = any;

type ShippingAddressProfileUpdate = {
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
};

function buildPatientShippingAddressUpdate(address: {
  first_name?: string | null;
  last_name?: string | null;
  company?: string | null;
  line1?: string | null;
  line2?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  country?: string | null;
  instructions?: string | null;
}): ShippingAddressProfileUpdate {
  return {
    shipping_first_name: address.first_name || null,
    shipping_last_name: address.last_name || null,
    shipping_company: address.company || null,
    shipping_address_line1: address.line1 || null,
    shipping_address_line2: address.line2 || null,
    shipping_city: address.city || null,
    shipping_state: address.state || null,
    shipping_postal_code: address.postal_code || null,
    shipping_country: address.country || null,
    shipping_instructions: address.instructions || null,
  };
}

interface OrderProviderLinkMetadataRow {
  metadata: unknown;
}

function toTelegraProviderReviewMetadata(
  metadata: unknown,
): TelegraProviderReviewMetadata | null {
  if (!metadata || typeof metadata !== "object") {
    return null;
  }

  const record = metadata as Record<string, unknown>;
  return {
    last_event_target_entity_status:
      typeof record.last_event_target_entity_status === "string"
        ? record.last_event_target_entity_status
        : null,
    last_event_status: typeof record.last_event_status === "string"
      ? record.last_event_status
      : null,
    normalized_target_entity_status:
      typeof record.normalized_target_entity_status === "string"
        ? record.normalized_target_entity_status
        : null,
    provider_target_entity_status:
      typeof record.provider_target_entity_status === "string"
        ? record.provider_target_entity_status
        : null,
    provider_status: typeof record.provider_status === "string"
      ? record.provider_status
      : null,
  };
}

async function fetchOrderProviderLinkMetadata(params: {
  supabase: SupabaseAdminClient;
  orderId: string;
  tenantId: string;
  providerPlatformIntegrationKey: string | null | undefined;
}): Promise<TelegraProviderReviewMetadata[]> {
  if (!isTelegraProviderIntegrationKey(params.providerPlatformIntegrationKey)) {
    return [];
  }

  const { data: providerLinks, error: providerLinksError } = await params
    .supabase
    .from("order_provider_platform_links")
    .select(
      `
      metadata,
      tenant_integrations!inner (
        integration_key
      )
    `,
    )
    .eq("order_id", params.orderId)
    .eq("tenant_id", params.tenantId)
    .eq("tenant_integrations.integration_key", "telegramd")
    .order("updated_at", { ascending: false });

  if (providerLinksError) {
    throw new Error(
      `Failed to fetch Telegra provider-review state: ${providerLinksError.message}`,
    );
  }

  return ((providerLinks || []) as OrderProviderLinkMetadataRow[])
    .map((link) => toTelegraProviderReviewMetadata(link.metadata))
    .filter((metadata): metadata is TelegraProviderReviewMetadata =>
      metadata !== null
    );
}

/**
 * The payment-first invariant.
 *
 * No checkout step past payment (contact details, email verification, the medical
 * questionnaire, provider intake) may be reached OR ACTED ON unless the order has
 * a confirmed authorization (or has already been captured). Enforce it on the
 * server: hiding a step in the UI does
 * not stop anyone from calling the endpoint directly, and this codebase has twice
 * shipped bugs of exactly that shape — a questionnaire submit that advanced an
 * order to `provider_approved` with no real provider approval, and an inbound
 * status event that skipped payment entirely and shipped a $499 order with
 * `paid_at` still null.
 *
 * Manual-capture PaymentIntents are `requires_capture` until clinical approval;
 * that is an authorization and MUST pass even though `paid_at` is still null. A
 * $0 order (a 100%-off coupon) is also legitimately unpaid and MUST still pass.
 */
function assertOrderPaid(
  order: {
    paid_at?: string | null;
    total_cents?: number | null;
    payment_statuses?: Array<string | null | undefined>;
  },
):
  | { ok: true }
  | { ok: false; code: string; message: string; status: number } {
  if (
    isOrderPaymentAuthorized({
      paidAt: order.paid_at,
      totalCents: order.total_cents,
      paymentStatuses: order.payment_statuses,
    })
  ) return { ok: true };

  return {
    ok: false,
    code: "PAYMENT_REQUIRED",
    message: "This order does not have a confirmed payment authorization",
    status: 409,
  };
}

type CheckoutEligibilityResult =
  | { ok: true }
  | { ok: false; code: string; message: string; status: number };

async function checkCheckoutEligibility(params: {
  supabaseAdmin: SupabaseAdminClient;
  tenantId: string;
  productId: string;
  buyerEmail: string;
  /** order being resumed; its subscriptions are exempt from the guards */
  resumeOrderId?: string | null;
}): Promise<CheckoutEligibilityResult> {
  const { supabaseAdmin, tenantId, productId, buyerEmail } = params;

  // --- Email-based eligibility (guest-safe) -----------------------------
  // Block the purchase if any patient with this email already has an active
  // (non-cancelled, non-expired) subscription whose product shares a
  // medication with the product being purchased. Mirrors the rule in
  // medication-api so the same conflict logic applies pre-account.
  const isPlanBlocking = (plan: {
    status: string | null;
    expires_at: string | null;
    cancelled_at: string | null;
  }): boolean => {
    if (plan.cancelled_at || plan.status === "cancelled") return false;
    if (!plan.expires_at) return true;
    const expiresAt = Date.parse(plan.expires_at);
    if (Number.isNaN(expiresAt)) return true;
    return expiresAt > Date.now();
  };

  // Subscriptions tied to an order we are resuming (explicit order_id reuse
  // or a resumable pre-provider draft). These belong to the SAME in-progress
  // checkout, so neither the same-product guard nor the medication-overlap
  // guard below should treat them as a pre-existing blocking plan. Populated
  // by the same-product guard and reused by the medication-overlap guard.
  const exemptedSubscriptionIds = new Set<string>();

  // --- Same-product duplicate guard (guest-safe) ------------------------
  // Block a NEW order for a product the buyer already has in progress: an
  // active/pending subscription for this product, OR an active (non-terminal,
  // non-cancelled) order for this product. Prevents a returning customer from
  // accidentally buying a second concurrent plan/order of the same medication.
  {
    const { data: dupEmailPatients } = await supabaseAdmin
      .from("patients")
      .select("id")
      .eq("tenant_id", tenantId)
      .ilike("email", buyerEmail);
    const dupPatientIds = (dupEmailPatients ?? []).map((p: { id: string }) =>
      p.id
    );

    if (dupPatientIds.length > 0) {
      // (a) an active (non-terminal) order for this exact product.
      // The guard must NOT block an order that the reuse logic below would
      // itself resume — otherwise re-preparing the SAME checkout (applying a
      // coupon, form auto-prepare, or simply returning to an abandoned draft)
      // is rejected as a "duplicate" of the very order it is trying to update.
      // Two exemptions, mirroring the reuse logic:
      //   1. the order the caller explicitly re-prepares (body.order_id), and
      //   2. any PRE-PROVIDER draft (no provider order id yet, status still
      //      order_created / shipping_details_required) — these are resumable
      //      drafts, not committed concurrent orders.
      const reusingOrderId = params.resumeOrderId?.trim();
      const RESUMABLE_DRAFT_STATUSES = [
        "order_created",
        "shipping_details_required",
      ];
      const { data: sameProductOrders } = await supabaseAdmin
        .from("orders")
        .select(
          "id, subscription_id, provider_platform_order_id, order_statuses!inner ( status_key, is_terminal )",
        )
        .eq("tenant_id", tenantId)
        .in("patient_id", dupPatientIds)
        .eq("product_id", productId);

      // Collect the subscription_ids attached to orders we are exempting, so
      // the subscription check below does not re-block via the pending
      // subscription that belongs to the very draft order we are resuming
      // (a subscription checkout creates a pending_validation sub linked to
      // its order — that is the same in-progress checkout, not a duplicate).
      const hasActiveOrder = (sameProductOrders ?? []).some((o: {
        id: string;
        subscription_id: string | null;
        provider_platform_order_id: string | null;
        order_statuses:
          | { status_key: string; is_terminal: boolean | null }
          | { status_key: string; is_terminal: boolean | null }[]
          | null;
      }) => {
        const s = Array.isArray(o.order_statuses)
          ? o.order_statuses[0]
          : o.order_statuses;
        const isExplicitReuse = reusingOrderId && o.id === reusingOrderId;
        const isResumableDraft = !o.provider_platform_order_id &&
          s && RESUMABLE_DRAFT_STATUSES.includes(s.status_key);
        if (isExplicitReuse || isResumableDraft) {
          if (o.subscription_id) {
            exemptedSubscriptionIds.add(o.subscription_id);
          }
          return false;
        }
        if (!s || s.is_terminal === true) return false;
        return true;
      });

      // (b) an active/pending subscription for this exact product — excluding
      // any subscription tied to an order we are resuming above.
      const { data: sameProductSubs } = await supabaseAdmin
        .from("subscriptions")
        .select("id, status, expires_at, cancelled_at")
        .eq("tenant_id", tenantId)
        .in("patient_id", dupPatientIds)
        .eq("product_id", productId);
      const hasBlockingSub = (sameProductSubs ?? []).some((sub: {
        id: string;
        status: string | null;
        expires_at: string | null;
        cancelled_at: string | null;
      }) => !exemptedSubscriptionIds.has(sub.id) && isPlanBlocking(sub));

      if (hasBlockingSub || hasActiveOrder) {
        console.info(
          "Blocking payment intent: duplicate same-product plan/order",
          {
            productId,
            buyerEmail,
            hasBlockingSub,
            hasActiveOrder,
          },
        );
        return {
          ok: false,
          code: "DUPLICATE_PRODUCT_ORDER",
          message:
            "You already have an active or pending order for this product. Manage it from My Plan.",
          status: 409,
        };
      }
    }
  }

  const { data: targetMeds } = await supabaseAdmin
    .from("product_medications")
    .select("medication_id")
    .eq("product_id", productId);
  const targetMedicationIds = Array.from(
    new Set(
      (targetMeds ?? [])
        .map((m: { medication_id: string | null }) => m.medication_id)
        .filter((v: unknown): v is string =>
          typeof v === "string" && v.length > 0
        ),
    ),
  );

  if (targetMedicationIds.length > 0) {
    const { data: emailPatients } = await supabaseAdmin
      .from("patients")
      .select("id")
      .eq("tenant_id", tenantId)
      .ilike("email", buyerEmail);
    const patientIds = (emailPatients ?? []).map((p: { id: string }) => p.id);

    if (patientIds.length > 0) {
      const { data: emailPlans } = await supabaseAdmin
        .from("subscriptions")
        .select("id, product_id, status, expires_at, cancelled_at")
        .eq("tenant_id", tenantId)
        .in("patient_id", patientIds)
        .not("product_id", "is", null);

      const blockingProductIds = Array.from(
        new Set(
          (emailPlans ?? [])
            .filter((plan: {
              id: string;
              product_id: string | null;
              status: string | null;
              expires_at: string | null;
              cancelled_at: string | null;
            }) => !exemptedSubscriptionIds.has(plan.id) && isPlanBlocking(plan))
            .map((plan: { product_id: string | null }) => plan.product_id)
            .filter((v: unknown): v is string =>
              typeof v === "string" && v.length > 0
            ),
        ),
      );

      if (blockingProductIds.length > 0) {
        const { data: overlap } = await supabaseAdmin
          .from("product_medications")
          .select("product_id, medication_id")
          .in("product_id", blockingProductIds)
          .in("medication_id", targetMedicationIds);

        if ((overlap ?? []).length > 0) {
          console.info("Blocking payment intent for medication conflict", {
            productId,
            buyerEmail,
          });
          return {
            ok: false,
            code: "NOT_ELIGIBLE",
            message:
              "Sorry we couldn't process your order. Check your inbox for more information.",
            status: 409,
          };
        }
      }
    }
  }

  return { ok: true };
}

type StripeCheckoutSession = {
  id: string;
  status?: string | null;
  payment_status?: string | null;
  customer?: string | null;
  customer_email?: string | null;
  customer_details?: {
    email?: string | null;
    name?: string | null;
    phone?: string | null;
  } | null;
  shipping_details?: {
    name?: string | null;
    phone?: string | null;
    address?: Record<string, string | null> | null;
  } | null;
  amount_total?: number | null;
  currency?: string | null;
  mode?: string | null;
  created?: number | null;
  expires_at?: number | null;
  metadata?: Record<string, string | undefined> | null;
  payment_intent?: string | null;
  subscription?: string | null;
  invoice?: string | null;
  total_details?: {
    amount_discount?: number | null;
  } | null;
  discounts?:
    | Array<{
      promotion_code?: string | { id?: string; code?: string } | null;
      coupon?: { name?: string | null } | string | null;
    }>
    | null;
};

type StripeSubscriptionResponse = {
  id: string;
  current_period_end?: number;
};

type SubscriptionCheckoutProduct = {
  id: string;
  name: string;
  description?: string | null;
  price_cents: number;
  payment_type?: string | null;
  subscription_interval?: string | null;
  subscription_interval_count?: number | null;
  image_url?: string | null;
};

type StripePaymentIntentDetails = {
  payment_method?: string | { id?: string } | null;
};

type StripeDiscountInfo = {
  discountCents: number;
  couponCode: string | null;
  couponName: string | null;
};

function generateCheckoutOrderNumber(): string {
  const timestamp = Date.now().toString(36).toUpperCase();
  const random = Math.random().toString(36).substring(2, 6).toUpperCase();
  return `ORD-${timestamp}-${random}`;
}

Deno.serve(async (req) => {
  const corsHeaders = getCorsHeaders(req);

  function jsonResponse(
    data: unknown,
    status = 200,
    headers: Record<string, string> = {},
  ) {
    return new Response(JSON.stringify(data), {
      status,
      headers: {
        ...corsHeaders,
        "Content-Type": "application/json",
        ...headers,
      },
    });
  }

  function errorResponse(code: string, message: string, status = 400) {
    return jsonResponse({ error: { code, message } }, status);
  }

  function asSingle<T>(value: T | T[] | null | undefined): T | null {
    if (Array.isArray(value)) {
      return value[0] ?? null;
    }

    return value ?? null;
  }

  function addUtcDays(date: Date, days: number): Date {
    const nextDate = dateTime(date).toDate();
    nextDate.setUTCDate(nextDate.getUTCDate() + days);
    return nextDate;
  }

  function validateRefillDateWindow(
    newDate: Date,
    anchorAt: string | null,
    anchorLabel = "current renewal date",
    daysBefore = 14,
    daysAfter = 14,
  ): Response | null {
    if (!anchorAt) {
      return errorResponse(
        "PLAN_DATE_MISSING",
        `Plan has no ${anchorLabel} to adjust`,
        400,
      );
    }

    const anchorDate = dateTime(anchorAt).toDate();
    if (Number.isNaN(anchorDate.getTime())) {
      return errorResponse(
        "INVALID_PLAN_DATE",
        `Plan ${anchorLabel} is invalid`,
        400,
      );
    }

    const startOfUtcDay = (date: Date): number => {
      const d = new Date(date.getTime());
      d.setUTCHours(0, 0, 0, 0);
      return d.getTime();
    };

    // Hardcoded window relative to the anchor date (reschedule only).
    // TODO(PP-872 follow-up): make this per-product/dynamic on the admin side
    // (see products.renewal_advance_max_weeks).
    const earliestMs = startOfUtcDay(addUtcDays(anchorDate, -daysBefore));
    const latestMs = startOfUtcDay(addUtcDays(anchorDate, daysAfter));
    const newDateMs = startOfUtcDay(newDate);
    if (newDateMs < earliestMs || newDateMs > latestMs) {
      return errorResponse(
        "REFILL_DATE_OUT_OF_RANGE",
        daysAfter === 0
          ? `Refill date must be within ${daysBefore} days before the ${anchorLabel}`
          : `Refill date must be within 2 weeks of the ${anchorLabel}`,
        400,
      );
    }

    return null;
  }

  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const url = new URL(req.url);
  // Normalize path so the router works consistently across environments.
  // Depending on the gateway/runtime, the function may see either:
  // - /plan-api/orders
  // - /functions/v1/plan-api/orders
  const pathname = url.pathname;
  let path = pathname;
  path = path.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/plan-api")) {
    path = path.slice("/plan-api".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) path = path.slice(0, -1);

  // Rate limiting
  const clientIp = req.headers.get("x-forwarded-for") || "unknown";
  const rateCheck = checkRateLimit(clientIp);

  if (!rateCheck.allowed) {
    return errorResponse(
      "RATE_LIMIT_EXCEEDED",
      "Too many requests. Please try again later.",
      429,
    );
  }

  // Create Supabase clients
  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
  const supabaseServiceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const authHeader = req.headers.get("Authorization");
  const inboundRequestId = req.headers.get("x-request-id");
  const requestId = inboundRequestId && inboundRequestId.trim()
    ? inboundRequestId.trim()
    : crypto.randomUUID();
  const requestSource = `plan-api:${req.method}:${path}`;
  const requestContextHeaders: Record<string, string> = {
    "x-request-id": requestId,
    "x-request-source": requestSource,
  };
  if (authHeader) {
    requestContextHeaders.Authorization = authHeader;
  }

  // Regular client for authenticated requests
  const supabase = createClient(supabaseUrl, supabaseAnonKey, {
    global: {
      headers: requestContextHeaders,
    },
  });

  // Service role client for privileged order and payment-provider operations
  const supabaseAdmin = createClient(supabaseUrl, supabaseServiceRoleKey, {
    global: {
      headers: {
        "x-request-id": requestId,
        "x-request-source": requestSource,
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });

  // Extract tenant slug from header or query param
  const headerTenantSlug = normalizeTenantSlug(
    req.headers.get("x-tenant-slug"),
  );
  const queryTenantSlug = normalizeTenantSlug(
    url.searchParams.get("tenant_slug"),
  );
  const tenantSlugs = Array.from(
    new Set([headerTenantSlug, queryTenantSlug].filter(Boolean) as string[]),
  );

  // Debug logging for troubleshooting external calls
  console.log("Plan API Request Debug:", {
    requestId,
    method: req.method,
    path,
    headerTenantSlug,
    queryTenantSlug,
    tenantSlugs,
    origin: req.headers.get("origin"),
    secFetchCredentials: req.headers.get("sec-fetch-credentials"),
    hasCookie: Boolean(req.headers.get("cookie")),
    hasApiKeyLower: Boolean(req.headers.get("apikey")),
    hasApiKeyCamel: Boolean(req.headers.get("apiKey")),
  });

  async function getActiveTenant<T extends string>(select: T) {
    for (const slug of tenantSlugs) {
      const { data, error } = await supabase
        .from("tenants")
        .select(select)
        .eq("slug", slug)
        .eq("status", "active")
        .maybeSingle();

      if (error) throw error;
      if (data) return data;
    }
    return null;
  }

  /**
   * Tenant lookup for GUEST-safe routes.
   *
   * getActiveTenant() reads `tenants` through the caller's (anon) client and
   * throws on error. Under RLS an unauthenticated read fails, so on a truly
   * anonymous request it throws and the caller sees an opaque INTERNAL_ERROR.
   * Routes that legitimately run before login (pre-flight, guest checkout) must
   * resolve the tenant with the service-role client instead.
   */
  async function getActiveTenantAsAdmin<T extends string>(select: T) {
    for (const slug of tenantSlugs) {
      const { data, error } = await supabaseAdmin
        .from("tenants")
        .select(select)
        .eq("slug", slug)
        .eq("status", "active")
        .maybeSingle();

      if (error) throw error;
      if (data) return data;
    }
    return null;
  }

  async function triggerOrderLifecycleForOrder(
    orderId: string,
    tenantId: string,
  ): Promise<void> {
    const orderLifecycleUrl =
      `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;

    try {
      const response = await fetch(orderLifecycleUrl, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${supabaseServiceRoleKey}`,
          "Content-Type": "application/json",
          "x-request-id": requestId,
          "x-request-source": `${requestSource}:trigger_order_lifecycle`,
        },
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.warn("Failed to trigger order-lifecycle", {
          requestId,
          tenantId,
          orderId,
          status: response.status,
          error: errorText,
        });
        return;
      }

      console.info("Triggered order-lifecycle", {
        requestId,
        tenantId,
        orderId,
      });
    } catch (error) {
      console.warn("Error triggering order-lifecycle", {
        requestId,
        tenantId,
        orderId,
        error: error instanceof Error ? error.message : String(error),
      });
    }
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

  function normalizeProviderPlatformIdentifier(
    value: string | null | undefined,
  ): string | null {
    if (!value) return null;

    const normalized = value.trim().toLowerCase().replace(/[^a-z0-9]+/g, "");
    return normalized.length > 0 ? normalized : null;
  }

  function compactObject(
    record: Record<string, unknown>,
  ): Record<string, unknown> {
    return Object.fromEntries(
      Object.entries(record).filter(([, value]) =>
        value !== null && value !== undefined
      ),
    );
  }

  function hasProvidedAddressFields(
    updateData: Record<string, string | null>,
    columns: readonly string[],
  ): boolean {
    return columns.some((column) =>
      Object.prototype.hasOwnProperty.call(updateData, column)
    );
  }

  function hasChangedAddressFields(
    updateData: Record<string, string | null>,
    order: Record<string, unknown>,
    columns: readonly string[],
  ): boolean {
    return columns.some((column) =>
      Object.prototype.hasOwnProperty.call(updateData, column) &&
      (updateData[column] ?? null) !== (order[column] ?? null)
    );
  }

  function getStatusRank(statusKey: string | null | undefined): number | null {
    if (!statusKey) return null;
    const index = CANONICAL_ORDER_STATUS_KEYS.indexOf(
      statusKey as (typeof CANONICAL_ORDER_STATUS_KEYS)[number],
    );
    return index >= 0 ? index : null;
  }

  function getStatusDisplayOrder(
    status: { display_order?: number | null } | null | undefined,
  ): number | null {
    return typeof status?.display_order === "number"
      ? status.display_order
      : null;
  }

  function isStatusBefore(
    currentStatus: {
      status_key?: string | null;
      display_order?: number | null;
    },
    thresholdStatus:
      | { status_key?: string | null; display_order?: number | null }
      | null
      | undefined,
  ): boolean {
    if (!currentStatus.status_key || !thresholdStatus?.status_key) return false;
    if (currentStatus.status_key === thresholdStatus.status_key) return false;

    const currentDisplayOrder = getStatusDisplayOrder(currentStatus);
    const thresholdDisplayOrder = getStatusDisplayOrder(thresholdStatus);
    if (
      typeof currentDisplayOrder === "number" &&
      typeof thresholdDisplayOrder === "number"
    ) {
      return currentDisplayOrder < thresholdDisplayOrder;
    }

    const currentRank = getStatusRank(currentStatus.status_key);
    const thresholdRank = getStatusRank(thresholdStatus.status_key);
    return currentRank !== null && thresholdRank !== null &&
      currentRank < thresholdRank;
  }

  function isStatusAfter(
    currentStatus: {
      status_key?: string | null;
      display_order?: number | null;
    },
    thresholdStatus:
      | { status_key?: string | null; display_order?: number | null }
      | null
      | undefined,
  ): boolean {
    if (!currentStatus.status_key || !thresholdStatus?.status_key) return false;
    if (currentStatus.status_key === thresholdStatus.status_key) return false;

    const currentDisplayOrder = getStatusDisplayOrder(currentStatus);
    const thresholdDisplayOrder = getStatusDisplayOrder(thresholdStatus);
    if (
      typeof currentDisplayOrder === "number" &&
      typeof thresholdDisplayOrder === "number"
    ) {
      return currentDisplayOrder > thresholdDisplayOrder;
    }

    const currentRank = getStatusRank(currentStatus.status_key);
    const thresholdRank = getStatusRank(thresholdStatus.status_key);
    return currentRank !== null && thresholdRank !== null &&
      currentRank > thresholdRank;
  }

  async function fetchAddressUpdateStatusThresholds(): Promise<{
    providerReviewPending:
      | { status_key: string; display_order: number | null }
      | null;
    paymentPending: { status_key: string; display_order: number | null } | null;
    shippingDetailsRequired:
      | { status_key: string; display_order: number | null }
      | null;
  }> {
    const { data, error } = await supabaseAdmin
      .from("order_statuses")
      .select("status_key, display_order")
      .in("status_key", [
        "provider_review_pending",
        "payment_pending",
        "shipping_details_required",
      ])
      .eq("is_active", true);

    if (error) {
      throw new Error(
        `Failed to fetch order status thresholds: ${error.message}`,
      );
    }

    const statuses = new Map(
      (data || []).map((
        status: { status_key: string; display_order: number | null },
      ) => [status.status_key, status]),
    );

    return {
      providerReviewPending: statuses.get("provider_review_pending") ?? {
        status_key: "provider_review_pending",
        display_order: null,
      },
      paymentPending: statuses.get("payment_pending") ?? {
        status_key: "payment_pending",
        display_order: null,
      },
      shippingDetailsRequired: statuses.get("shipping_details_required") ?? {
        status_key: "shipping_details_required",
        display_order: null,
      },
    };
  }

  function buildMdiShippingAddressPayload(
    order: Record<string, unknown>,
  ): Record<string, unknown> {
    const address = compactObject({
      address: order.shipping_address_line1,
      address2: order.shipping_address_line2,
      zip_code: order.shipping_postal_code,
      city_name: order.shipping_city,
      state_name: order.shipping_state,
    });

    return Object.keys(address).length > 0 ? { address } : {};
  }

  function buildTelegraShippingAddressPayload(
    order: Record<string, unknown>,
  ): Record<string, unknown> {
    return {
      address: {
        billing: {
          address1: typeof order.billing_address_line1 === "string"
            ? order.billing_address_line1
            : "",
          address2: typeof order.billing_address_line2 === "string"
            ? order.billing_address_line2
            : "",
          city: typeof order.billing_city === "string"
            ? order.billing_city
            : "",
          state: typeof order.billing_state === "string"
            ? order.billing_state
            : "",
          zipcode: typeof order.billing_postal_code === "string"
            ? order.billing_postal_code
            : "",
        },
        shipping: {
          address1: typeof order.shipping_address_line1 === "string"
            ? order.shipping_address_line1
            : "",
          address2: typeof order.shipping_address_line2 === "string"
            ? order.shipping_address_line2
            : "",
          city: typeof order.shipping_city === "string"
            ? order.shipping_city
            : "",
          state: typeof order.shipping_state === "string"
            ? order.shipping_state
            : "",
          zipcode: typeof order.shipping_postal_code === "string"
            ? order.shipping_postal_code
            : "",
        },
      },
    };
  }

  async function parseProviderResponseBody(
    response: Response,
  ): Promise<unknown> {
    const responseText = await response.text();
    if (!responseText) return null;

    try {
      return JSON.parse(responseText);
    } catch {
      return responseText;
    }
  }

  function extractProviderErrorMessage(
    responseBody: unknown,
    fallback: string,
  ): string {
    if (typeof responseBody === "string" && responseBody.trim().length > 0) {
      return responseBody.trim();
    }

    if (responseBody && typeof responseBody === "object") {
      const record = responseBody as Record<string, unknown>;
      for (const key of ["message", "error", "detail"]) {
        const value = record[key];
        if (typeof value === "string" && value.trim().length > 0) {
          return value.trim();
        }
      }
    }

    return fallback;
  }

  function escapeSingleQuotedShell(value: string): string {
    return value.replace(/'/g, `'"'"'`);
  }

  async function syncLinkedProviderShippingAddress(
    order: Record<string, unknown>,
  ): Promise<{ attempted: boolean; provider: string | null }> {
    const tenantId = typeof order.tenant_id === "string"
      ? order.tenant_id
      : null;
    const orderId = typeof order.id === "string" ? order.id : null;
    const patientId = typeof order.patient_id === "string"
      ? order.patient_id
      : null;
    if (!tenantId || !orderId) {
      throw new Error(
        "Cannot sync provider shipping address without order id and tenant id",
      );
    }

    const { data: providerLinks, error: providerLinksError } =
      await supabaseAdmin
        .from("order_provider_platform_links")
        .select("id, provider_order_id, metadata, tenant_integration_id")
        .eq("order_id", orderId)
        .eq("tenant_id", tenantId)
        .order("created_at", { ascending: false });

    if (providerLinksError) {
      throw new Error(
        `Failed to fetch order provider platform link: ${providerLinksError.message}`,
      );
    }

    if (!providerLinks || providerLinks.length === 0) {
      console.info("Skipping provider shipping sync: order is not linked", {
        requestId,
        orderId,
      });
      return { attempted: false, provider: null };
    }

    if (providerLinks.length > 1) {
      throw new Error(
        `Expected at most one order provider platform link for order ${orderId}, found ${providerLinks.length}`,
      );
    }

    const providerLink = providerLinks[0];
    const providerOrderId =
      typeof providerLink.provider_order_id === "string" &&
        providerLink.provider_order_id.trim()
        ? providerLink.provider_order_id.trim()
        : null;
    if (!providerOrderId) {
      console.info(
        "Skipping provider shipping sync: provider order id is not stored",
        { requestId, orderId, providerLinkId: providerLink.id },
      );
      return { attempted: false, provider: null };
    }

    const { data: tenantIntegration, error: tenantIntegrationError } =
      await supabaseAdmin
        .from("tenant_integrations")
        .select("id, tenant_id, integration_key, settings")
        .eq("id", providerLink.tenant_integration_id)
        .maybeSingle();

    if (tenantIntegrationError) {
      throw new Error(
        `Failed to fetch provider platform integration: ${tenantIntegrationError.message}`,
      );
    }

    if (!tenantIntegration) {
      throw new Error("Provider platform integration not found");
    }

    const provider = normalizeProviderPlatformIdentifier(
      tenantIntegration.integration_key ||
        (typeof order.provider_platform_integration_key === "string"
          ? order.provider_platform_integration_key
          : null),
    );

    if (provider === "mdintegrations") {
      if (!patientId) {
        throw new Error(
          "Cannot sync MDI shipping address without an order patient id",
        );
      }

      const backendUrl = getStringSetting(
        tenantIntegration.settings,
        "backend_url",
      );
      if (!backendUrl) {
        throw new Error(
          "MD Integrations integration is missing backend_url configuration",
        );
      }

      const { data: patientLink, error: patientLinkError } = await supabaseAdmin
        .from("patient_provider_platform_links")
        .select("provider_patient_id")
        .eq("patient_id", patientId)
        .eq("tenant_id", tenantId)
        .eq("tenant_integration_id", tenantIntegration.id)
        .maybeSingle();

      if (patientLinkError) {
        throw new Error(
          `Failed to fetch patient provider platform link: ${patientLinkError.message}`,
        );
      }

      const providerPatientId =
        typeof patientLink?.provider_patient_id === "string" &&
          patientLink.provider_patient_id.trim()
          ? patientLink.provider_patient_id.trim()
          : null;
      if (!providerPatientId) {
        throw new Error(
          "Cannot sync MDI shipping address without stored provider patient id",
        );
      }

      const authResult = await resolveMdiAccessToken({
        supabase: supabaseAdmin,
        tenantIntegrationId: tenantIntegration.id,
        tenantId,
        settings: tenantIntegration.settings,
        baseUrl: backendUrl,
        requestId,
        source: "plan-api",
      });

      if ("errorMessage" in authResult) {
        throw new Error(authResult.errorMessage);
      }

      const response = await fetch(
        `${backendUrl.replace(/\/+$/, "")}/v1/partner/patients/${
          encodeURIComponent(providerPatientId)
        }`,
        {
          method: "PATCH",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: `Bearer ${authResult.accessToken}`,
            "x-request-id": requestId,
            "x-source": "plan-api",
          },
          body: JSON.stringify(buildMdiShippingAddressPayload(order)),
        },
      );

      const responseBody = await parseProviderResponseBody(response);
      if (!response.ok) {
        throw new Error(
          `MDI shipping address sync failed: ${
            extractProviderErrorMessage(
              responseBody,
              `${response.status} ${response.statusText}`.trim(),
            )
          }`,
        );
      }

      console.info("Synced shipping address to MDI", {
        requestId,
        orderId,
        providerOrderId,
        providerPatientId,
      });
      return { attempted: true, provider: "mdintegrations" };
    }

    if (provider === "telegramd" || provider === "telegra") {
      const baseUrl = getStringSetting(tenantIntegration.settings, "url");
      if (!baseUrl) {
        throw new Error("Telegra integration is missing url configuration");
      }

      const authResult = await resolveTelegraAccessToken({
        supabase: supabaseAdmin,
        tenantIntegrationId: tenantIntegration.id,
        tenantId,
        settings: tenantIntegration.settings,
        baseUrl,
        requestId,
        source: "plan-api",
      });

      if ("errorMessage" in authResult) {
        throw new Error(authResult.errorMessage);
      }

      const telegraUrl = `${baseUrl.replace(/\/+$/, "")}/orders/${
        encodeURIComponent(providerOrderId)
      }`;
      const telegraPayload = buildTelegraShippingAddressPayload(order);

      const telegraCurlCommand =
        `curl -X PUT '${escapeSingleQuotedShell(telegraUrl)}' ` +
        `-H 'Accept: application/json' ` +
        `-H 'Content-Type: application/json' ` +
        `-H 'Authorization: Bearer <redacted>' ` +
        `-H 'x-request-id: ${escapeSingleQuotedShell(requestId)}' ` +
        `-H 'x-source: plan-api' ` +
        `-d '${escapeSingleQuotedShell(JSON.stringify(telegraPayload))}'`;

      console.info("Telegra address update request curl", {
        requestId,
        orderId,
        providerOrderId,
        curl: telegraCurlCommand,
      });

      const response = await fetch(
        telegraUrl,
        {
          method: "PUT",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: `Bearer ${authResult.accessToken}`,
            "x-request-id": requestId,
            "x-source": "plan-api",
          },
          body: JSON.stringify(telegraPayload),
        },
      );

      const responseBody = await parseProviderResponseBody(response);
      console.info("Telegra address update response", {
        requestId,
        orderId,
        providerOrderId,
        status: response.status,
        statusText: response.statusText,
        body: responseBody,
      });

      if (!response.ok) {
        throw new Error(
          `Telegra shipping address sync failed: ${
            extractProviderErrorMessage(
              responseBody,
              `${response.status} ${response.statusText}`.trim(),
            )
          }`,
        );
      }

      console.info("Synced shipping address to Telegra", {
        requestId,
        orderId,
        providerOrderId,
      });
      return { attempted: true, provider: "telegramd" };
    }

    console.info("Skipping provider shipping sync: unsupported provider", {
      requestId,
      orderId,
      provider,
    });
    return { attempted: false, provider };
  }

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

  async function fetchStripePaymentIntentDetails(
    paymentIntentId: string,
    stripeSecretKey: string,
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
      console.warn(
        "Failed to retrieve payment intent for checkout order sync",
        {
          requestId,
          paymentIntentId,
          status: response.status,
          error: await response.text(),
        },
      );
      return null;
    }

    return (await response.json()) as StripePaymentIntentDetails;
  }

  /**
   * Resolve a reusable Stripe Customer for a patient, creating one if needed.
   *
   * The embedded PaymentIntent checkout (PP-566) previously created no Stripe
   * Customer, which left saved cards unusable for off-session renewals, the
   * billing portal, and payment-failed retry. This mirrors the hosted flow,
   * which relied on Checkout Session `customer_creation: always`. The id is
   * cached on `patients.metadata.stripe_customer_id` (the same location the
   * billing-portal and retry-payment routes already read).
   *
   * Returns the customer id, or null if creation failed (caller decides whether
   * that is fatal — for one-time products it is not).
   */
  async function ensureStripeCustomerForPatient(params: {
    patientId: string;
    tenantId: string;
    stripeSecretKey: string;
    email: string;
    fullName?: string | null;
    phone?: string | null;
  }): Promise<string | null> {
    const { patientId, tenantId, stripeSecretKey, email, fullName, phone } =
      params;

    const { data: patientRow, error: patientFetchError } = await supabaseAdmin
      .from("patients")
      .select("metadata")
      .eq("id", patientId)
      .eq("tenant_id", tenantId)
      .maybeSingle();

    if (patientFetchError) {
      console.warn("ensureStripeCustomerForPatient: patient fetch failed", {
        requestId,
        patientId,
        error: patientFetchError.message,
      });
    }

    const patientMetadata: Record<string, unknown> =
      patientRow?.metadata && typeof patientRow.metadata === "object" &&
        !Array.isArray(patientRow.metadata)
        ? patientRow.metadata as Record<string, unknown>
        : {};

    const existingCustomerId =
      typeof patientMetadata.stripe_customer_id === "string"
        ? patientMetadata.stripe_customer_id.trim()
        : "";
    if (existingCustomerId) {
      return existingCustomerId;
    }

    // Before minting a NEW customer, adopt the one the patient's most recent
    // payment actually ran against. Payment-first checkout creates the Stripe
    // Customer from the raw email BEFORE the patient row exists — if this
    // helper runs before /checkout/finalize persists that id (e.g. the
    // checkout customer-session fetch racing a mid-checkout signup), creating
    // a second customer strands the saved card and the subscription anchor on
    // the first one. The transaction customer is authoritative: captures and
    // subscriptions are created against it.
    const { data: latestTxn } = await supabaseAdmin
      .from("order_payment_provider_transactions")
      .select("provider_customer_id, orders!inner(patient_id, tenant_id)")
      .eq("orders.patient_id", patientId)
      .eq("orders.tenant_id", tenantId)
      .not("provider_customer_id", "is", null)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle();
    const txnCustomerId =
      typeof latestTxn?.provider_customer_id === "string"
        ? latestTxn.provider_customer_id.trim()
        : "";
    if (txnCustomerId) {
      const { error: adoptError } = await supabaseAdmin
        .from("patients")
        .update({
          metadata: { ...patientMetadata, stripe_customer_id: txnCustomerId },
        })
        .eq("id", patientId)
        .eq("tenant_id", tenantId);
      if (adoptError) {
        console.warn(
          "ensureStripeCustomerForPatient: failed to persist adopted transaction customer",
          { requestId, patientId, txnCustomerId, error: adoptError.message },
        );
      }
      return txnCustomerId;
    }

    // Create the Stripe Customer.
    const customerParams = new URLSearchParams();
    customerParams.append("email", email);
    if (fullName?.trim()) {
      customerParams.append("name", fullName.trim());
    }
    if (phone?.trim()) {
      customerParams.append("phone", phone.trim());
    }
    customerParams.append("metadata[tenant_id]", tenantId);
    customerParams.append("metadata[patient_id]", patientId);

    const customerRes = await fetch("https://api.stripe.com/v1/customers", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: customerParams.toString(),
    });

    if (!customerRes.ok) {
      console.warn("ensureStripeCustomerForPatient: customer creation failed", {
        requestId,
        patientId,
        status: customerRes.status,
        error: await customerRes.text(),
      });
      return null;
    }

    const customer = (await customerRes.json()) as { id?: string };
    const customerId = typeof customer.id === "string"
      ? customer.id.trim()
      : "";
    if (!customerId) {
      return null;
    }

    // Persist on the patient so the billing-portal/retry-payment routes and
    // future renewals reuse the same Customer.
    const { error: persistError } = await supabaseAdmin
      .from("patients")
      .update({
        metadata: { ...patientMetadata, stripe_customer_id: customerId },
      })
      .eq("id", patientId)
      .eq("tenant_id", tenantId);

    if (persistError) {
      console.warn(
        "ensureStripeCustomerForPatient: failed to persist stripe_customer_id (customer still created)",
        { requestId, patientId, customerId, error: persistError.message },
      );
    }

    return customerId;
  }

  // --- Shared checkout building blocks ------------------------------------
  // Extracted verbatim from POST /orders/{product_id}/payment-intent so the
  // payment-first routes (POST /checkout/authorize, POST /checkout/finalize)
  // reuse EXACTLY the same behaviour instead of re-implementing it.

  type CheckoutRouteError = { code: string; message: string; status: number };

  type ResolvedTenantStripe = {
    stripeSecretKey: string;
    /**
     * payment_providers.id (the GLOBAL provider row) — NOT
     * tenant_payment_providers.id. See the comment inside.
     */
    stripePaymentProviderId: string;
  };

  /**
   * Resolve the tenant's enabled Stripe credentials.
   * Returns `{ error }` with the exact code/message/status the payment-intent
   * route used to return inline.
   */
  async function resolveTenantStripe(
    tenantId: string,
  ): Promise<ResolvedTenantStripe | { error: CheckoutRouteError }> {
    const { data: stripeProvider, error: providerError } = await supabaseAdmin
      .from("tenant_payment_providers")
      .select(
        `id, is_enabled, settings, payment_providers!inner (id, key, name)`,
      )
      .eq("tenant_id", tenantId)
      .eq("is_enabled", true)
      .eq("payment_providers.key", "stripe")
      .maybeSingle();

    if (providerError) {
      console.error("Provider fetch error:", providerError);
      return {
        error: {
          code: "FETCH_ERROR",
          message: "Failed to fetch payment provider",
          status: 500,
        },
      };
    }
    if (!stripeProvider) {
      return {
        error: {
          code: "NO_PAYMENT_PROVIDER",
          message: "No Stripe payment provider configured for this tenant",
          status: 400,
        },
      };
    }

    // order_payment_provider_transactions.payment_provider_id references
    // payment_providers.id (the GLOBAL provider row), NOT
    // tenant_payment_providers.id. The joined payment_providers relation
    // carries the correct id; using stripeProvider.id here would violate the
    // foreign key and silently drop the transaction link (PP-566 regression).
    const stripePaymentProviderRelation = Array.isArray(
        stripeProvider.payment_providers,
      )
      ? stripeProvider.payment_providers[0]
      : stripeProvider.payment_providers;
    const stripePaymentProviderId = (stripePaymentProviderRelation as
      | { id?: string }
      | null
      | undefined)?.id;
    if (!stripePaymentProviderId) {
      console.error(
        "Stripe payment_providers.id missing on joined provider",
        {
          tenantPaymentProviderId: stripeProvider.id,
        },
      );
      return {
        error: {
          code: "NO_PAYMENT_PROVIDER",
          message: "Stripe payment provider is misconfigured for this tenant",
          status: 400,
        },
      };
    }

    const piSettings = stripeProvider.settings as Record<string, string>;
    const stripeSecretKey = piSettings?.secret_key;
    if (!stripeSecretKey) {
      return {
        error: {
          code: "PROVIDER_NOT_CONFIGURED",
          message: "Stripe secret key not configured",
          status: 400,
        },
      };
    }

    return { stripeSecretKey, stripePaymentProviderId };
  }

  type ResolvedCheckoutCoupon = {
    amountCents: number;
    discountCents: number;
    appliedCouponCode: string | null;
  };

  /**
   * Optional coupon: resolve a typed promo code to its discount, returning the
   * net amount to charge. Never throws — a bad/unknown code is ignored (the
   * customer simply pays full price), exactly as the inline block did.
   */
  async function resolveCheckoutCoupon(params: {
    stripeSecretKey: string;
    basePriceCents: number;
    promotionCode?: string | null;
  }): Promise<ResolvedCheckoutCoupon> {
    let amountCents = params.basePriceCents;
    let appliedCouponCode: string | null = null;
    let discountCents = 0;
    const typedPromo = (params.promotionCode ?? "").trim();
    const stripeSecretKey = params.stripeSecretKey;

    if (typedPromo) {
      try {
        const applyCoupon = (coupon: {
          id?: string;
          amount_off?: number;
          percent_off?: number;
        }, label: string) => {
          if (typeof coupon.amount_off === "number") {
            discountCents = Math.min(coupon.amount_off, amountCents);
          } else if (typeof coupon.percent_off === "number") {
            discountCents = Math.round(
              (amountCents * coupon.percent_off) / 100,
            );
          }
          appliedCouponCode = label;
          amountCents = Math.max(0, amountCents - discountCents);
        };

        // 1) Treat the input as a promotion code first (the usual case). Expand
        // the coupon (supporting both `coupon` and newer `promotion.coupon`
        // shapes) so amount_off/percent_off are available.
        const promoParams = new URLSearchParams({
          code: typedPromo,
          active: "true",
          limit: "1",
        });
        promoParams.append("expand[]", "data.coupon");
        promoParams.append("expand[]", "data.promotion.coupon");
        const promoRes = await fetch(
          `https://api.stripe.com/v1/promotion_codes?${promoParams.toString()}`,
          { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
        );
        let resolved = false;
        if (promoRes.ok) {
          const promoBody = await promoRes.json();
          const promo = promoBody?.data?.[0];
          const coupon = promo?.coupon ?? promo?.promotion?.coupon;
          if (coupon) {
            applyCoupon(coupon, promo.code ?? typedPromo);
            resolved = true;
          }
        }

        // 2) Fall back to treating the input as a Stripe coupon id directly
        // (some campaign codes are coupon ids, not promotion codes).
        if (!resolved) {
          const couponRes = await fetch(
            `https://api.stripe.com/v1/coupons/${
              encodeURIComponent(typedPromo)
            }`,
            { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
          );
          if (couponRes.ok) {
            const coupon = await couponRes.json();
            if (coupon?.valid !== false) {
              applyCoupon(coupon, coupon.id ?? typedPromo);
            }
          }
        }
      } catch (_e) {
        console.warn("Coupon lookup failed; ignoring", { typedPromo });
      }
    }

    return { amountCents, discountCents, appliedCouponCode };
  }

  type ResolvedCheckoutPatient = { patientId: string; accountExists: boolean };

  /**
   * Resolve the patient for a checkout.
   *
   * The checkout flow creates the patient/account (via patient-api
   * /auth/signup) before the order is created, so the patient already exists.
   * Prefer the authenticated patient; fall back to an email lookup (covers the
   * brief window before the JWT is attached). orders.patient_id is NOT NULL, so
   * a missing patient is a hard error — we never create guests here (the signup
   * route owns patient creation).
   *
   * account_exists: this email belongs to a genuine RETURNING customer — a
   * patient linked to an auth user whose email is ALREADY VERIFIED. The UI uses
   * this to show a LOGIN step for returning customers instead of the
   * create-password + email-verify step.
   *
   * Why email_verified_at (not just a patient row / auth_user_id): account-first
   * checkout creates the patient + auth user BEFORE this runs, so a first-time
   * buyer ALSO has a patient id and auth_user_id by now. The distinguishing
   * signal is email_verified_at — a freshly auto-created account has it null
   * (the post-payment step is where they verify), whereas a real prior account
   * verified earlier. The order still attaches to the resolved patient either
   * way.
   */
  async function resolveCheckoutPatient(params: {
    tenantId: string;
    buyerEmail: string;
    authenticatedPatient?:
      | {
        id?: string;
        auth_user_id?: string | null;
        email_verified_at?: string | null;
      }
      | null;
  }): Promise<ResolvedCheckoutPatient | { error: CheckoutRouteError }> {
    const { tenantId, buyerEmail, authenticatedPatient } = params;

    let resolvedPatientId = authenticatedPatient?.id ?? null;
    let accountExists = Boolean(
      authenticatedPatient?.auth_user_id &&
        authenticatedPatient?.email_verified_at,
    );
    if (!resolvedPatientId) {
      const { data: existingPatient } = await supabaseAdmin
        .from("patients")
        .select("id, auth_user_id, email_verified_at")
        .eq("tenant_id", tenantId)
        .ilike("email", buyerEmail)
        .maybeSingle();
      resolvedPatientId = existingPatient?.id ?? null;
      accountExists = Boolean(
        existingPatient?.auth_user_id && existingPatient?.email_verified_at,
      );
    }

    if (!resolvedPatientId) {
      return {
        error: {
          code: "PATIENT_NOT_FOUND",
          message:
            "No account found for this email. Please complete sign up first.",
          status: 409,
        },
      };
    }

    return { patientId: resolvedPatientId, accountExists };
  }

  type CheckoutOrderProduct = {
    id: string;
    price_cents: number;
    payment_type: string | null;
  };

  /**
   * Create (or reuse+refresh) the checkout order, in exactly the state the
   * embedded Elements flow produces: the same order_created status, the same
   * order_status_history row, the same subscription handling, the same shipping
   * columns, and the same patient-profile shipping-address sync.
   */
  async function createOrReuseCheckoutOrder(params: {
    tenantId: string;
    patientId: string;
    product: CheckoutOrderProduct;
    stripePaymentProviderId: string;
    amountCents: number;
    discountCents: number;
    appliedCouponCode: string | null;
    fullName?: string | null;
    shippingAddressLine1?: string | null;
    shippingAddressLine2?: string | null;
    shippingCity?: string | null;
    shippingState?: string | null;
    shippingPostalCode?: string | null;
    shippingCountry?: string | null;
    /** Explicit order to reuse (the UI passes the order_id it already holds). */
    reuseOrderId?: string | null;
    /** Internal notes used on a genuinely new order. */
    newOrderNotes: string;
    /** Internal notes used when refreshing a reused order. */
    reusedOrderNotes: string;
    /**
     * Whether to look for a reusable in-progress order at all. The payment-first
     * finalize flow keys idempotency on the PaymentIntent instead, so it opts out.
     */
    allowReuse: boolean;
  }): Promise<
    | {
      orderId: string;
      orderNumber: string;
      reused: boolean;
      subscriptionId: string | null;
    }
    | { error: CheckoutRouteError }
  > {
    const {
      tenantId,
      patientId,
      product,
      stripePaymentProviderId,
      amountCents,
      discountCents,
      appliedCouponCode,
      reuseOrderId,
      newOrderNotes,
      reusedOrderNotes,
      allowReuse,
    } = params;

    const orderCreatedStatusId = await getOrderCreatedStatusId(supabaseAdmin);
    const orderNumber = generateCheckoutOrderNumber();

    // Split the buyer name for the order's shipping/billing first/last name —
    // the order-lifecycle requires complete shipping AND billing addresses to
    // advance past shipping_details_required. We collect a single shipping
    // address at checkout and use it for billing too (the form's "same as
    // shipping" default), so both are complete up front.
    const orderNameParts = (params.fullName ?? "").trim().split(/\s+/);
    const orderFirstName = orderNameParts[0] || "Customer";
    const orderLastName = orderNameParts.slice(1).join(" ") || orderFirstName;
    const shipLine1 = params.shippingAddressLine1?.trim() || null;
    const shipLine2 = params.shippingAddressLine2?.trim() || null;
    const shipCity = params.shippingCity?.trim() || null;
    const shipState = params.shippingState?.trim() || null;
    const shipPostal = params.shippingPostalCode?.trim() || null;
    const shipCountry = params.shippingCountry?.trim() || "US";

    // The order's shared field set (used for both reuse-update and insert).
    // Shipping address doubles as billing (the checkout form's "same as
    // shipping" default), so billing is always complete up front — the
    // order-lifecycle requires complete shipping AND billing to advance past
    // shipping_details_required.
    const orderFields = {
      product_id: product.id,
      subtotal_cents: product.price_cents,
      tax_cents: 0,
      shipping_cents: 0,
      total_cents: amountCents,
      discount_cents: discountCents,
      coupon_code: appliedCouponCode,
      shipping_first_name: orderFirstName,
      shipping_last_name: orderLastName,
      shipping_address_line1: shipLine1,
      shipping_address_line2: shipLine2,
      shipping_city: shipCity,
      shipping_state: shipState,
      shipping_postal_code: shipPostal,
      shipping_country: shipCountry,
      billing_first_name: orderFirstName,
      billing_last_name: orderLastName,
      billing_address_line1: shipLine1,
      billing_address_line2: shipLine2,
      billing_city: shipCity,
      billing_state: shipState,
      billing_postal_code: shipPostal,
      billing_country: shipCountry,
    };

    if (shipLine1 || shipCity || shipState || shipPostal) {
      const { error: profileAddressUpdateError } = await supabaseAdmin
        .from("patients")
        .update(
          buildPatientShippingAddressUpdate({
            first_name: orderFirstName,
            last_name: orderLastName,
            line1: shipLine1,
            line2: shipLine2,
            city: shipCity,
            state: shipState,
            postal_code: shipPostal,
            country: shipCountry,
          }),
        )
        .eq("id", patientId)
        .eq("tenant_id", tenantId);

      if (profileAddressUpdateError) {
        console.error("Patient shipping address update error:", {
          patientId,
          tenantId,
          error: profileAddressUpdateError.message,
        });
        return {
          error: {
            code: "PROFILE_UPDATE_ERROR",
            message: "Failed to save shipping address to patient profile",
            status: 500,
          },
        };
      }
    }

    // Reuse the in-progress checkout order instead of creating a new one, so
    // that re-preparing the SAME checkout (applying/changing a coupon, or the
    // form-complete auto-prepare) updates one order rather than spawning
    // abandoned duplicates.
    //
    // PRIMARY (authoritative): the UI passes the order_id it already holds for
    // this checkout. We reuse exactly that order (validated to belong to this
    // patient + product and not be terminal/cancelled). This is race-free —
    // unlike status-based matching, it works no matter how far the order has
    // advanced (an order can reach the provider within seconds of creation, so
    // a coupon applied later would otherwise miss any status window).
    let reusableOrder:
      | { id: string; order_number: string; subscription_id: string | null }
      | null = null;

    const requestedReuseOrderId = reuseOrderId?.trim();
    if (allowReuse && requestedReuseOrderId) {
      const { data: targeted } = await supabaseAdmin
        .from("orders")
        .select(
          "id, order_number, subscription_id, order_statuses!inner ( status_key )",
        )
        .eq("id", requestedReuseOrderId)
        .eq("tenant_id", tenantId)
        .eq("patient_id", patientId)
        .eq("product_id", product.id)
        .not(
          "order_statuses.status_key",
          "in",
          "(order_cancelled,order_pending_cancellation,order_cancellation_processing,order_cancellation_error)",
        )
        .maybeSingle();
      if (targeted?.id) {
        reusableOrder = {
          id: targeted.id,
          order_number: targeted.order_number,
          subscription_id: targeted.subscription_id ?? null,
        };
      }
    }

    // FALLBACK (no order_id supplied, e.g. older UI): reuse the patient's most
    // recent PRE-PROVIDER order for this product. Kept for safety, but the UI
    // should always pass order_id once it has one.
    if (allowReuse && !reusableOrder) {
      const { data: heuristic } = await supabaseAdmin
        .from("orders")
        .select(
          "id, order_number, subscription_id, order_statuses!inner ( status_key )",
        )
        .eq("tenant_id", tenantId)
        .eq("patient_id", patientId)
        .eq("product_id", product.id)
        .is("provider_platform_order_id", null)
        .in("order_statuses.status_key", [
          "order_created",
          "shipping_details_required",
        ])
        .order("created_at", { ascending: false })
        .limit(1)
        .maybeSingle();
      if (heuristic?.id) {
        reusableOrder = {
          id: heuristic.id,
          order_number: heuristic.order_number,
          subscription_id: heuristic.subscription_id ?? null,
        };
      }
    }

    const localSubscriptionId = product.payment_type === "subscription"
      ? reusableOrder?.subscription_id ||
        await ensureOrderSubscription({
          supabase: supabaseAdmin,
          tenantId,
          patientId,
          productId: product.id,
          stripePaymentProviderId,
          startedAt: dateTime().toISOString(),
          createIfMissing: true,
        })
      : null;

    if (product.payment_type === "subscription" && !localSubscriptionId) {
      console.warn(
        "Embedded checkout could not create pending local subscription",
        {
          requestId,
          tenantId,
          patientId,
          productId: product.id,
        },
      );
    }

    const orderSubscriptionFields = localSubscriptionId
      ? { subscription_id: localSubscriptionId }
      : {};

    let createdOrder: { id: string; order_number: string } | null = null;
    let createOrderError: { message: string } | null = null;

    if (reusableOrder?.id) {
      const { data: updatedOrder, error: updateOrderError } =
        await supabaseAdmin
          .from("orders")
          .update({
            ...orderFields,
            ...orderSubscriptionFields,
            status_changed_at: dateTime().toISOString(),
            internal_notes: reusedOrderNotes,
          })
          .eq("id", reusableOrder.id)
          .eq("tenant_id", tenantId)
          .select("id, order_number")
          .single();
      createdOrder = updatedOrder ?? null;
      createOrderError = updateOrderError;

      // Clear any stale payment-intent transaction from a prior amount on the
      // reused order so we don't leave an orphaned PI link (a new PI is created
      // by the caller for the refreshed amount).
      if (createdOrder) {
        await supabaseAdmin
          .from("order_payment_provider_transactions")
          .delete()
          .eq("tenant_id", tenantId)
          .eq("order_id", createdOrder.id)
          .eq("payment_status", "pending");
      }
    } else {
      const { data: insertedOrder, error: insertOrderError } =
        await supabaseAdmin
          .from("orders")
          .insert({
            order_number: orderNumber,
            tenant_id: tenantId,
            patient_id: patientId,
            status_id: orderCreatedStatusId,
            status_changed_at: dateTime().toISOString(),
            internal_notes: newOrderNotes,
            ...orderFields,
            ...orderSubscriptionFields,
          })
          .select("id, order_number")
          .single();
      createdOrder = insertedOrder ?? null;
      createOrderError = insertOrderError;
    }

    if (createOrderError || !createdOrder) {
      console.error("Order creation failed", createOrderError);
      return {
        error: {
          code: "ORDER_CREATE_ERROR",
          message: "Failed to start checkout. Please try again.",
          status: 500,
        },
      };
    }

    // Only record the "order created" history entry for a genuinely new order;
    // a reused order already has it.
    if (!reusableOrder?.id) {
      await supabaseAdmin
        .from("order_status_history")
        .insert({
          order_id: createdOrder.id,
          status_id: orderCreatedStatusId,
          notes: newOrderNotes,
        });
    }

    return {
      orderId: createdOrder.id,
      orderNumber: createdOrder.order_number,
      reused: Boolean(reusableOrder?.id),
      subscriptionId: localSubscriptionId,
    };
  }

  async function fetchStripeCustomerDefaultPaymentMethod(
    customerId: string,
    stripeSecretKey: string,
  ): Promise<string | null> {
    const response = await fetch(
      `https://api.stripe.com/v1/customers/${customerId}?expand[]=invoice_settings.default_payment_method`,
      {
        method: "GET",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
        },
      },
    );

    if (!response.ok) {
      console.warn("Failed to fetch Stripe customer default payment method", {
        requestId,
        customerId,
        status: response.status,
        error: await response.text(),
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

  async function resolvePromotionCode(
    promotionCode: string | { id?: string; code?: string } | null | undefined,
    stripeSecretKey: string,
  ): Promise<string | null> {
    if (!promotionCode) return null;
    if (typeof promotionCode === "object" && promotionCode.code) {
      return promotionCode.code;
    }

    const promoCodeId = typeof promotionCode === "string"
      ? promotionCode
      : promotionCode.id;
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

  async function extractDiscountFromCheckoutSession(
    session: StripeCheckoutSession,
    stripeSecretKey: string,
  ): Promise<StripeDiscountInfo> {
    const discountCents = session.total_details?.amount_discount || 0;
    if (discountCents === 0) {
      return { discountCents: 0, couponCode: null, couponName: null };
    }

    const firstDiscount = session.discounts?.[0];
    const couponCode = await resolvePromotionCode(
      firstDiscount?.promotion_code,
      stripeSecretKey,
    );

    let couponName: string | null = null;
    if (firstDiscount?.coupon && typeof firstDiscount.coupon === "object") {
      couponName = firstDiscount.coupon.name ?? null;
    }

    return { discountCents, couponCode, couponName };
  }

  async function createSendInvoiceStripeSubscription(params: {
    session: StripeCheckoutSession;
    tenantId: string;
    product: SubscriptionCheckoutProduct;
    patientId: string;
    customerEmail: string | null;
    stripeSecretKey: string;
    paymentMethodId?: string | null;
  }): Promise<StripeSubscriptionResponse> {
    const {
      session,
      tenantId,
      product,
      patientId,
      customerEmail,
      stripeSecretKey,
      paymentMethodId,
    } = params;

    if (!session.customer || !session.customer.trim()) {
      throw new Error(
        "Cannot create Stripe subscription without a Stripe customer",
      );
    }

    // Search for an existing Stripe product by allia_product_id metadata
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
        throw new Error(
          `Failed to create Stripe product for checkout subscription: ${await stripeProductResponse
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
    stripeParams.append("customer", session.customer.trim());
    stripeParams.append("collection_method", "send_invoice");
    stripeParams.append("days_until_due", "30");
    if (paymentMethodId?.trim()) {
      stripeParams.append("default_payment_method", paymentMethodId.trim());
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
      throw new Error(
        `Failed to create Stripe subscription for checkout: ${await response
          .text()}`,
      );
    }

    return (await response.json()) as StripeSubscriptionResponse;
  }

  async function getOrderCreatedStatusId(
    supabase: SupabaseAdminClient,
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

    return orderCreatedStatus.id;
  }

  async function upsertSubscriptionProviderLink(params: {
    supabase: SupabaseAdminClient;
    tenantId: string;
    subscriptionId: string | null;
    paymentProviderId: string;
    providerSubscriptionId?: string | null;
    providerCheckoutSessionId?: string | null;
  }): Promise<string | null> {
    const {
      supabase,
      tenantId,
      subscriptionId,
      paymentProviderId,
      providerSubscriptionId,
      providerCheckoutSessionId,
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

    console.warn("Failed to upsert subscription payment provider link", {
      requestId,
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

  async function ensureOrderSubscription(params: {
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
    createIfMissing?: boolean;
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
      createIfMissing = false,
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
      !subscriptionId &&
      (createIfMissing || providerSubscriptionId || providerCheckoutSessionId)
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
        console.error("Failed to create subscription for checkout order", {
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

    if (!subscriptionId) return null;

    const linkedSubscriptionId = await upsertSubscriptionProviderLink({
      supabase,
      tenantId,
      subscriptionId,
      paymentProviderId: stripePaymentProviderId,
      providerSubscriptionId: providerSubscriptionId || null,
      providerCheckoutSessionId: providerCheckoutSessionId || null,
    });

    if (!linkedSubscriptionId) return null;

    if (
      createdSubscriptionId &&
      linkedSubscriptionId !== createdSubscriptionId
    ) {
      await supabase
        .from("subscriptions")
        .delete()
        .eq("id", createdSubscriptionId)
        .eq("tenant_id", tenantId)
        .eq("patient_id", patientId);
    }

    return linkedSubscriptionId;
  }

  async function markSubscriptionAsActiveIfPendingValidation(
    supabase: SupabaseAdminClient,
    tenantId: string,
    subscriptionId: string | null,
    source: string,
  ): Promise<void> {
    if (!subscriptionId) return;

    const { data: subscription } = await supabase
      .from("subscriptions")
      .select("id, status")
      .eq("id", subscriptionId)
      .eq("tenant_id", tenantId)
      .maybeSingle();

    if (!subscription || subscription.status !== "pending_validation") {
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
      console.warn(
        "Failed to activate subscription after checkout order sync",
        {
          requestId,
          tenantId,
          subscriptionId,
          source,
          error: activateError.message,
        },
      );
    }
  }

  async function upsertOrderPaymentProviderTransaction(params: {
    supabase: SupabaseAdminClient;
    tenantId: string;
    orderId: string;
    paymentProviderId: string;
    subscriptionId?: string | null;
    providerPaymentIntentId?: string | null;
    providerSubscriptionId?: string | null;
    providerCheckoutSessionId?: string | null;
    providerCustomerId?: string | null;
    providerInvoiceId?: string | null;
    paymentStatus?: string | null;
    paidAt?: string | null;
  }): Promise<void> {
    const {
      supabase,
      tenantId,
      orderId,
      paymentProviderId,
      subscriptionId,
      providerPaymentIntentId,
      providerSubscriptionId,
      providerCheckoutSessionId,
      providerCustomerId,
      providerInvoiceId,
      paymentStatus,
      paidAt,
    } = params;

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
    if (providerSubscriptionId) {
      payload.provider_subscription_id = providerSubscriptionId;
    }
    if (providerCheckoutSessionId) {
      payload.provider_checkout_session_id = providerCheckoutSessionId;
    }
    if (providerCustomerId) {
      payload.provider_customer_id = providerCustomerId;
    }
    if (providerInvoiceId) {
      payload.provider_invoice_id = providerInvoiceId;
    }
    if (paidAt) payload.paid_at = paidAt;

    let existingTransactionId: string | null = null;
    if (providerPaymentIntentId) {
      const { data: existingByIntent } = await supabase
        .from("order_payment_provider_transactions")
        .select("id")
        .eq("order_id", orderId)
        .eq("payment_provider_id", paymentProviderId)
        .eq("provider_payment_intent_id", providerPaymentIntentId)
        .maybeSingle();

      existingTransactionId = existingByIntent?.id || null;
    }

    if (!existingTransactionId && providerCheckoutSessionId) {
      const { data: existingByCheckout } = await supabase
        .from("order_payment_provider_transactions")
        .select("id")
        .eq("order_id", orderId)
        .eq("payment_provider_id", paymentProviderId)
        .eq("provider_checkout_session_id", providerCheckoutSessionId)
        .order("created_at", { ascending: false })
        .limit(1);

      existingTransactionId = existingByCheckout?.[0]?.id || null;
    }

    const { error } = existingTransactionId
      ? await supabase
        .from("order_payment_provider_transactions")
        .update(payload)
        .eq("id", existingTransactionId)
      : await supabase
        .from("order_payment_provider_transactions")
        .insert(payload);

    if (error) {
      throw new Error(
        `order_payment_provider_transactions upsert failed: ${error.message}`,
      );
    }
  }

  async function ensureCheckoutOrder(params: {
    supabase: SupabaseAdminClient;
    tenantId: string;
    stripePaymentProviderId: string;
    stripeSecretKey: string;
    session: StripeCheckoutSession;
    authenticatedPatientId?: string | null;
  }): Promise<{ orderId: string | null; created: boolean }> {
    const {
      supabase,
      tenantId,
      stripePaymentProviderId,
      stripeSecretKey,
      session,
      authenticatedPatientId,
    } = params;

    const metadata = session.metadata || {};
    const productId = metadata.product_id?.trim() || null;
    const patientId = metadata.patient_id?.trim() || null;

    console.info("Ensuring Stripe checkout order", {
      requestId,
      tenantId,
      sessionId: session.id,
      sessionStatus: session.status || null,
      paymentStatus: session.payment_status || null,
      mode: session.mode || null,
      productId,
      patientId,
      authenticatedPatientId: authenticatedPatientId || null,
      paymentIntentId: session.payment_intent || null,
      tenantSlugs,
    });

    if (!patientId) {
      throw new Error("Checkout session is missing patient_id metadata");
    }

    if (authenticatedPatientId && authenticatedPatientId !== patientId) {
      throw new Error(
        "Checkout session does not belong to the authenticated patient",
      );
    }

    if (session.payment_intent) {
      const { data: existingByIntent } = await supabase
        .from("order_payment_provider_transactions")
        .select("order_id")
        .eq("payment_provider_id", stripePaymentProviderId)
        .eq("provider_payment_intent_id", session.payment_intent)
        .maybeSingle();

      if (existingByIntent?.order_id) {
        console.info(
          "Stripe checkout order already exists by payment intent; skipping creation and order-lifecycle trigger",
          {
            requestId,
            tenantId,
            sessionId: session.id,
            paymentIntentId: session.payment_intent,
            existingOrderId: existingByIntent.order_id,
          },
        );
        return { orderId: existingByIntent.order_id, created: false };
      }
    }

    const { data: existingByCheckout } = await supabase
      .from("order_payment_provider_transactions")
      .select("order_id")
      .eq("payment_provider_id", stripePaymentProviderId)
      .eq("provider_checkout_session_id", session.id)
      .maybeSingle();

    if (existingByCheckout?.order_id) {
      console.info(
        "Stripe checkout order already exists by checkout session; skipping creation and order-lifecycle trigger",
        {
          requestId,
          tenantId,
          sessionId: session.id,
          existingOrderId: existingByCheckout.order_id,
        },
      );
      return { orderId: existingByCheckout.order_id, created: false };
    }

    let product: SubscriptionCheckoutProduct | null = null;
    if (productId) {
      const { data: fetchedProduct, error: productError } = await supabase
        .from("products")
        .select(
          "id, name, description, price_cents, payment_type, subscription_interval, subscription_interval_count, image_url",
        )
        .eq("id", productId)
        .eq("tenant_id", tenantId)
        .maybeSingle();

      if (productError) {
        throw new Error(`Failed to fetch product: ${productError.message}`);
      }

      product = fetchedProduct;
    }

    const subtotalCents = typeof product?.price_cents === "number"
      ? product.price_cents
      : session.amount_total || 0;
    const taxCents = 0;
    const shippingCents = 0;
    const totalCents = session.amount_total ??
      subtotalCents + taxCents + shippingCents;
    const isZeroAmountCheckout = (session.amount_total ?? null) === 0;
    const isPaymentSettled = session.payment_status === "paid" ||
      session.payment_status === "no_payment_required" ||
      isZeroAmountCheckout;
    const paidAt = isPaymentSettled ? dateTime().toISOString() : null;
    const checkoutDiscountInfo = await extractDiscountFromCheckoutSession(
      session,
      stripeSecretKey,
    );

    const customerEmailCandidate = session.customer_details?.email ||
      session.customer_email ||
      metadata.customer_email ||
      null;
    const customerEmail = typeof customerEmailCandidate === "string"
      ? customerEmailCandidate.trim().toLowerCase()
      : null;

    let checkoutStripeSubscriptionId = session.subscription || null;
    let subscriptionRenewalAt: string | null = null;
    if (product?.payment_type === "subscription") {
      let paymentMethodId: string | null = null;

      if (session.payment_intent?.trim()) {
        const paymentIntentDetails = await fetchStripePaymentIntentDetails(
          session.payment_intent.trim(),
          stripeSecretKey,
        );
        paymentMethodId = getStripePaymentMethodId(
          paymentIntentDetails?.payment_method || null,
        );
      } else if (isZeroAmountCheckout && session.customer?.trim()) {
        paymentMethodId = await fetchStripeCustomerDefaultPaymentMethod(
          session.customer.trim(),
          stripeSecretKey,
        );
      }

      if (!checkoutStripeSubscriptionId) {
        if (!product) {
          throw new Error(
            "Subscription checkout session is missing a valid product",
          );
        }
        const createdSubscription = await createSendInvoiceStripeSubscription({
          session,
          tenantId,
          product,
          patientId,
          customerEmail,
          stripeSecretKey,
          paymentMethodId,
        });
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
      patientId,
      productId,
      stripePaymentProviderId,
      providerCheckoutSessionId: session.id,
      providerSubscriptionId: checkoutStripeSubscriptionId,
      startedAt: dateTime().toISOString(),
      renewalAt: subscriptionRenewalAt,
      expiresAt: subscriptionRenewalAt,
    });

    const orderCreatedStatusId = await getOrderCreatedStatusId(supabase);
    const orderNumber = generateCheckoutOrderNumber();

    const { data: order, error: orderError } = await supabase
      .from("orders")
      .insert({
        order_number: orderNumber,
        tenant_id: tenantId,
        patient_id: patientId,
        product_id: productId || null,
        subscription_id: resolvedSubscriptionId,
        status_id: orderCreatedStatusId,
        status_changed_at: dateTime().toISOString(),
        subtotal_cents: subtotalCents,
        tax_cents: taxCents,
        shipping_cents: shippingCents,
        total_cents: totalCents,
        discount_cents: checkoutDiscountInfo.discountCents,
        coupon_code: checkoutDiscountInfo.couponCode,
        coupon_name: checkoutDiscountInfo.couponName,
        internal_notes: `Stripe Session: ${session.id}${
          checkoutStripeSubscriptionId
            ? `, Subscription: ${checkoutStripeSubscriptionId}`
            : ""
        }${
          session.payment_intent
            ? `, Payment Intent: ${session.payment_intent}`
            : ""
        }`,
        paid_at: paidAt,
        renewal_at: subscriptionRenewalAt,
      })
      .select("id")
      .single();

    if (orderError) {
      throw new Error(`Failed to create order: ${orderError.message}`);
    }

    console.info("Stripe checkout order inserted", {
      requestId,
      tenantId,
      orderId: order.id,
      sessionId: session.id,
      paymentIntentId: session.payment_intent || null,
      subscriptionId: resolvedSubscriptionId,
      paymentStatus: session.payment_status || null,
    });

    const { data: orderCreatedHistory, error: orderCreatedHistoryError } =
      await supabase
        .from("order_status_history")
        .insert({
          order_id: order.id,
          status_id: orderCreatedStatusId,
          notes: "Order created via Stripe checkout",
        })
        .select("id")
        .single();

    if (orderCreatedHistoryError) {
      console.warn("Failed to insert order-created status history", {
        requestId,
        orderId: order.id,
        error: orderCreatedHistoryError.message,
      });
    }

    await upsertOrderPaymentProviderTransaction({
      supabase,
      tenantId,
      orderId: order.id,
      paymentProviderId: stripePaymentProviderId,
      subscriptionId: resolvedSubscriptionId,
      providerPaymentIntentId: session.payment_intent || null,
      providerSubscriptionId: checkoutStripeSubscriptionId,
      providerCheckoutSessionId: session.id,
      providerCustomerId: session.customer || null,
      providerInvoiceId: session.invoice || null,
      paymentStatus: session.payment_status || null,
      paidAt,
    });

    if (session.customer) {
      const { data: currentPatient } = await supabase
        .from("patients")
        .select("metadata")
        .eq("id", patientId)
        .eq("tenant_id", tenantId)
        .maybeSingle();

      const existingMetadata =
        (currentPatient?.metadata as Record<string, unknown> | null) || {};

      await supabase
        .from("patients")
        .update({
          metadata: {
            ...existingMetadata,
            stripe_customer_id: session.customer,
          },
        })
        .eq("id", patientId)
        .eq("tenant_id", tenantId);
    }

    await triggerOrderLifecycleForOrder(order.id, tenantId);
    await markSubscriptionAsActiveIfPendingValidation(
      supabase,
      tenantId,
      resolvedSubscriptionId,
      "plan_api_checkout_session",
    );

    return { orderId: order.id, created: true };
  }

  async function fetchOrderProviderPlatforms(params: {
    orderIds: string[];
    tenantId: string;
  }): Promise<
    Map<
      string,
      Array<{
        name: string | null;
        integration_key: string | null;
      }>
    >
  > {
    const { orderIds, tenantId } = params;
    const providerPlatformsByOrderId = new Map<
      string,
      Array<{
        name: string | null;
        integration_key: string | null;
      }>
    >();

    if (orderIds.length === 0) {
      return providerPlatformsByOrderId;
    }

    const { data: providerLinks, error: providerLinksError } =
      await supabaseAdmin
        .from("order_provider_platform_links")
        .select("order_id, tenant_integration_id, provider_order_id, metadata")
        .eq("tenant_id", tenantId)
        .in("order_id", orderIds)
        .order("created_at", { ascending: false });

    if (providerLinksError) {
      throw new Error(
        `Failed to fetch order provider platform links: ${providerLinksError.message}`,
      );
    }

    const tenantIntegrationIds = Array.from(
      new Set(
        (providerLinks || [])
          .map((link) => link.tenant_integration_id)
          .filter(
            (id): id is string => typeof id === "string" && id.length > 0,
          ),
      ),
    );

    const tenantIntegrationsById = new Map<
      string,
      { integration_key: string | null }
    >();

    if (tenantIntegrationIds.length > 0) {
      const { data: tenantIntegrations, error: tenantIntegrationsError } =
        await supabaseAdmin
          .from("tenant_integrations")
          .select("id, integration_key")
          .eq("tenant_id", tenantId)
          .in("id", tenantIntegrationIds);

      if (tenantIntegrationsError) {
        throw new Error(
          `Failed to fetch tenant integrations for order provider platforms: ${tenantIntegrationsError.message}`,
        );
      }

      for (const integration of tenantIntegrations || []) {
        tenantIntegrationsById.set(integration.id, {
          integration_key: integration.integration_key || null,
        });
      }
    }

    const integrationKeys = Array.from(
      new Set(
        Array.from(tenantIntegrationsById.values())
          .map((integration) => integration.integration_key)
          .filter(
            (key): key is string => typeof key === "string" && key.length > 0,
          ),
      ),
    );

    const platformIntegrationsByKey = new Map<
      string,
      { name: string | null }
    >();

    if (integrationKeys.length > 0) {
      const { data: platformIntegrations, error: platformIntegrationsError } =
        await supabaseAdmin
          .from("platform_integrations")
          .select("key, name")
          .eq("category", "provider_platform")
          .in("key", integrationKeys);

      if (platformIntegrationsError) {
        throw new Error(
          `Failed to fetch platform integrations for order provider platforms: ${platformIntegrationsError.message}`,
        );
      }

      for (const integration of platformIntegrations || []) {
        platformIntegrationsByKey.set(integration.key, {
          name: integration.name || null,
        });
      }
    }

    for (const providerLink of providerLinks || []) {
      const tenantIntegration = tenantIntegrationsById.get(
        providerLink.tenant_integration_id,
      );
      const integrationKey = tenantIntegration?.integration_key || null;
      const platformIntegration = integrationKey
        ? platformIntegrationsByKey.get(integrationKey)
        : null;
      const metadata = providerLink.metadata &&
          typeof providerLink.metadata === "object" &&
          !Array.isArray(providerLink.metadata)
        ? (providerLink.metadata as Record<string, unknown>)
        : null;
      const metadataProvider = typeof metadata?.provider === "string" &&
          metadata.provider.trim().length > 0
        ? metadata.provider.trim()
        : null;

      const orderProviderPlatforms =
        providerPlatformsByOrderId.get(providerLink.order_id) || [];

      orderProviderPlatforms.push({
        name: platformIntegration?.name || metadataProvider,
        integration_key: integrationKey,
      });

      providerPlatformsByOrderId.set(
        providerLink.order_id,
        orderProviderPlatforms,
      );
    }

    return providerPlatformsByOrderId;
  }

  async function ensureStripePaymentDetailsPortalConfiguration(params: {
    tenantId: string;
    tenantPaymentProviderId: string;
    stripeSecretKey: string;
    settings: Record<string, unknown>;
  }): Promise<{ configurationId: string | null; error: Response | null }> {
    const { tenantId, tenantPaymentProviderId, stripeSecretKey, settings } =
      params;

    const existingConfigurationId =
      typeof settings.billing_portal_payment_details_configuration_id ===
          "string"
        ? settings.billing_portal_payment_details_configuration_id.trim()
        : "";

    if (existingConfigurationId) {
      // Verify the configuration still exists in Stripe before using it
      const verifyRes = await fetch(
        `https://api.stripe.com/v1/billing_portal/configurations/${existingConfigurationId}`,
        {
          method: "GET",
          headers: { Authorization: `Bearer ${stripeSecretKey}` },
        },
      );

      if (verifyRes.ok) {
        return { configurationId: existingConfigurationId, error: null };
      }

      // Configuration no longer exists — clear the stale ID and fall through to create a new one
      console.warn(
        "Stored Stripe billing portal configuration no longer exists; will recreate",
        { tenantId, tenantPaymentProviderId, existingConfigurationId },
      );

      // Clear stale ID from settings so the update below persists the new ID correctly
      delete (settings as Record<string, unknown>)
        .billing_portal_payment_details_configuration_id;

      await supabaseAdmin
        .from("tenant_payment_providers")
        .update({ settings: { ...settings } })
        .eq("id", tenantPaymentProviderId)
        .eq("tenant_id", tenantId);
    }

    const configurationParams = new URLSearchParams();
    configurationParams.append("name", "Payment details only");
    configurationParams.append("features[invoice_history][enabled]", "false");
    configurationParams.append(
      "features[payment_method_update][enabled]",
      "true",
    );
    configurationParams.append(
      "features[subscription_cancel][enabled]",
      "false",
    );
    configurationParams.append(
      "features[subscription_update][enabled]",
      "false",
    );
    configurationParams.append("features[customer_update][enabled]", "false");

    const stripeConfigurationResponse = await fetch(
      "https://api.stripe.com/v1/billing_portal/configurations",
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
          "Idempotency-Key":
            `${tenantPaymentProviderId}-payment-details-portal-config`,
        },
        body: configurationParams.toString(),
      },
    );

    if (!stripeConfigurationResponse.ok) {
      const errorText = await stripeConfigurationResponse.text();
      let errorMessage = "Failed to create Stripe billing portal configuration";

      try {
        const parsedError = JSON.parse(errorText) as {
          error?: { message?: string };
        };
        errorMessage = parsedError.error?.message || errorMessage;
      } catch {
        // Keep generic fallback message
      }

      console.error("Stripe billing portal configuration creation error", {
        tenantId,
        tenantPaymentProviderId,
        status: stripeConfigurationResponse.status,
        error: errorText,
      });

      return {
        configurationId: null,
        error: errorResponse("STRIPE_ERROR", errorMessage, 500),
      };
    }

    const stripeConfiguration = (await stripeConfigurationResponse.json()) as {
      id?: string;
    };

    if (!stripeConfiguration.id) {
      console.error("Stripe returned invalid billing portal configuration", {
        tenantId,
        tenantPaymentProviderId,
      });

      return {
        configurationId: null,
        error: errorResponse(
          "STRIPE_ERROR",
          "Stripe returned an invalid billing portal configuration",
          500,
        ),
      };
    }

    const updatedSettings = {
      ...settings,
      billing_portal_payment_details_configuration_id: stripeConfiguration.id,
    };

    const { error: providerUpdateError } = await supabaseAdmin
      .from("tenant_payment_providers")
      .update({
        settings: updatedSettings,
      })
      .eq("id", tenantPaymentProviderId)
      .eq("tenant_id", tenantId);

    if (providerUpdateError) {
      console.warn(
        "Failed to persist Stripe billing portal configuration ID for tenant payment provider",
        {
          tenantId,
          tenantPaymentProviderId,
          configurationId: stripeConfiguration.id,
          error: providerUpdateError.message,
        },
      );
    }

    return { configurationId: stripeConfiguration.id, error: null };
  }

  try {
    // ==================== ORDER ENDPOINTS ====================

    // Helper to get authenticated patient
    async function getAuthenticatedPatient() {
      if (!authHeader) {
        return {
          patient: null,
          error: errorResponse(
            "UNAUTHORIZED",
            "Authorization header required",
            401,
          ),
        };
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();
      if (userError || !user) {
        return {
          patient: null,
          error: errorResponse("UNAUTHORIZED", "Invalid or expired token", 401),
        };
      }

      const { data: patient, error: patientError } = await supabase
        .from("patients")
        .select(
          "id, tenant_id, first_name, last_name, email, access_status, auth_user_id, email_verified_at",
        )
        .eq("auth_user_id", user.id)
        .maybeSingle();

      if (patientError) {
        console.error("Patient fetch error:", patientError);
        return {
          patient: null,
          error: errorResponse("FETCH_ERROR", "Failed to fetch patient", 500),
        };
      }

      if (!patient) {
        return {
          patient: null,
          error: errorResponse("NOT_FOUND", "Patient profile not found", 404),
        };
      }

      if (patient.access_status !== "active") {
        return {
          patient: null,
          error: errorResponse(
            "ACCOUNT_INACTIVE",
            `Your account is ${patient.access_status}`,
            403,
          ),
        };
      }

      return { patient, error: null };
    }

    async function validateOrderStripePaymentProvider(
      orderId: string,
      tenantId: string,
    ): Promise<{ valid: boolean; response: Response | null }> {
      const { data: providerTransactions, error: providerTransactionsError } =
        await supabaseAdmin
          .from("order_payment_provider_transactions")
          .select(
            `
          payment_provider_id,
          payment_providers!inner (
            key
          )
        `,
          )
          .eq("order_id", orderId)
          .eq("tenant_id", tenantId)
          .limit(20);

      if (providerTransactionsError) {
        console.error(
          "Order payment provider validation error while checking Stripe:",
          providerTransactionsError,
        );
        return {
          valid: false,
          response: errorResponse(
            "FETCH_ERROR",
            "Failed to validate order payment provider",
            500,
          ),
        };
      }

      if (!providerTransactions || providerTransactions.length === 0) {
        return {
          valid: false,
          response: errorResponse(
            "INVALID_PAYMENT_PROVIDER",
            "Order payment provider is not set",
            400,
          ),
        };
      }

      const hasStripeProvider = providerTransactions.some((transaction) => {
        const paymentProvider = transaction.payment_providers as
          | { key?: string }
          | Array<{ key?: string }>
          | null;
        if (Array.isArray(paymentProvider)) {
          return paymentProvider.some((provider) => provider.key === "stripe");
        }
        return paymentProvider?.key === "stripe";
      });

      if (!hasStripeProvider) {
        return {
          valid: false,
          response: errorResponse(
            "INVALID_PAYMENT_PROVIDER",
            "Order payment provider is not Stripe",
            400,
          ),
        };
      }

      return { valid: true, response: null };
    }

    // Helper to get authenticated tenant/platform admin
    async function getAuthenticatedAdmin() {
      if (!authHeader) {
        return {
          admin: null,
          error: errorResponse(
            "UNAUTHORIZED",
            "Authorization header required",
            401,
          ),
        };
      }

      const {
        data: { user },
        error: userError,
      } = await supabase.auth.getUser();
      if (userError || !user) {
        return {
          admin: null,
          error: errorResponse("UNAUTHORIZED", "Invalid or expired token", 401),
        };
      }

      const { data: adminUser, error: adminUserError } = await supabaseAdmin
        .from("admin_users")
        .select("id, email")
        .eq("auth_user_id", user.id)
        .maybeSingle();

      if (adminUserError) {
        console.error("Admin fetch error:", adminUserError);
        return {
          admin: null,
          error: errorResponse(
            "FETCH_ERROR",
            "Failed to fetch admin profile",
            500,
          ),
        };
      }

      if (!adminUser) {
        return {
          admin: null,
          error: errorResponse("FORBIDDEN", "Admin access required", 403),
        };
      }

      const { data: userRoles, error: userRolesError } = await supabaseAdmin
        .from("user_roles")
        .select("role")
        .eq("user_id", adminUser.id);

      if (userRolesError) {
        console.error("Admin roles fetch error:", userRolesError);
        return {
          admin: null,
          error: errorResponse(
            "FETCH_ERROR",
            "Failed to fetch admin roles",
            500,
          ),
        };
      }

      const roles = new Set((userRoles || []).map((row) => row.role));
      const isPlatformSuperadmin = roles.has("platform_superadmin");
      const isTenantAdmin = roles.has("tenant_admin");
      const isCustomerSupport = roles.has("customer_support");

      if (!isPlatformSuperadmin && !isTenantAdmin && !isCustomerSupport) {
        return {
          admin: null,
          error: errorResponse(
            "FORBIDDEN",
            "Tenant admin or customer support access required",
            403,
          ),
        };
      }

      let tenantIds: string[] = [];
      if (!isPlatformSuperadmin) {
        const { data: memberships, error: membershipsError } =
          await supabaseAdmin
            .from("tenant_memberships")
            .select("tenant_id")
            .eq("admin_user_id", adminUser.id);

        if (membershipsError) {
          console.error("Admin memberships fetch error:", membershipsError);
          return {
            admin: null,
            error: errorResponse(
              "FETCH_ERROR",
              "Failed to fetch admin tenant memberships",
              500,
            ),
          };
        }

        tenantIds = (memberships || [])
          .map((membership) => membership.tenant_id)
          .filter(
            (tenantId): tenantId is string => typeof tenantId === "string",
          );

        if (tenantIds.length === 0) {
          return {
            admin: null,
            error: errorResponse("FORBIDDEN", "No tenant access granted", 403),
          };
        }
      }

      return {
        admin: {
          id: adminUser.id,
          email: adminUser.email,
          auth_user_id: user.id,
          is_platform_superadmin: isPlatformSuperadmin,
          is_tenant_admin: isTenantAdmin,
          is_customer_support: isCustomerSupport,
          tenant_ids: tenantIds,
        },
        error: null,
      };
    }

    // Generate order number
    function generateOrderNumber(): string {
      const timestamp = Date.now().toString(36).toUpperCase();
      const random = Math.random().toString(36).substring(2, 6).toUpperCase();
      return `ORD-${timestamp}-${random}`;
    }

    // POST /orders - Create a new order
    if (req.method === "POST" && path === "/orders") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: {
        product_id?: string;
        shipping_address?: {
          first_name?: string;
          last_name?: string;
          company?: string;
          line1?: string;
          line2?: string;
          city?: string;
          state?: string;
          postal_code?: string;
          country?: string;
          instructions?: string;
        };
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { product_id, shipping_address } = body;

      if (!product_id) {
        return errorResponse("MISSING_FIELDS", "product_id is required", 400);
      }

      // Validate product exists and is enabled for this tenant
      const { data: product, error: productError } = await supabase
        .from("products")
        .select(
          "id, name, price_cents, payment_type, subscription_interval, subscription_interval_count, subscription_renewal_lead_days, terms_and_conditions_html",
        )
        .eq("id", product_id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) {
        console.error("Product fetch error:", productError);
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }

      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      // Determine shipping address
      if (!shipping_address) {
        return errorResponse(
          "MISSING_FIELDS",
          "shipping_address is required",
          400,
        );
      }

      const finalAddress: {
        first_name: string | null;
        last_name: string | null;
        company: string | null;
        line1: string | null;
        line2: string | null;
        city: string | null;
        state: string | null;
        postal_code: string | null;
        country: string | null;
        instructions: string | null;
      } = {
        first_name: shipping_address.first_name || null,
        last_name: shipping_address.last_name || null,
        company: shipping_address.company || null,
        line1: shipping_address.line1 || null,
        line2: shipping_address.line2 || null,
        city: shipping_address.city || null,
        state: shipping_address.state || null,
        postal_code: shipping_address.postal_code || null,
        country: shipping_address.country || "US",
        instructions: shipping_address.instructions || null,
      };

      // Validate address has required fields
      if (
        !finalAddress.line1 ||
        !finalAddress.city ||
        !finalAddress.state ||
        !finalAddress.postal_code
      ) {
        return errorResponse(
          "INVALID_ADDRESS",
          "Shipping address must include line1, city, state, and postal_code",
          400,
        );
      }

      // Validate shipping state against tenant's allowed states
      const shippingStateValidation = await validateStateAgainstTenant(
        supabaseAdmin,
        patient!.tenant_id,
        finalAddress.state,
        finalAddress.country,
      );
      if (!shippingStateValidation.valid) {
        return errorResponse(
          "INVALID_STATE",
          shippingStateValidation.message!,
          400,
        );
      }

      const { error: profileAddressUpdateError } = await supabaseAdmin
        .from("patients")
        .update(buildPatientShippingAddressUpdate(finalAddress))
        .eq("id", patient!.id)
        .eq("tenant_id", patient!.tenant_id);

      if (profileAddressUpdateError) {
        console.error("Patient shipping address update error:", {
          patientId: patient!.id,
          tenantId: patient!.tenant_id,
          error: profileAddressUpdateError.message,
        });
        return errorResponse(
          "PROFILE_UPDATE_ERROR",
          "Failed to save shipping address to patient profile",
          500,
        );
      }

      // Calculate order totals (can be enhanced with tax calculation)
      const subtotalCents = product.price_cents;
      const shippingCents = 0; // Can be calculated based on address
      const taxCents = 0; // Can be calculated based on address
      const totalCents = subtotalCents + shippingCents + taxCents;

      // Fetch the initial order status (order_created)
      const { data: initialStatus, error: initialStatusError } =
        await supabaseAdmin
          .from("order_statuses")
          .select(
            `
          id,
          status_key,
          patient_status_label,
          patient_microcopy,
          patient_action_required,
          is_terminal,
          display_order
        `,
          )
          .eq("status_key", "order_created")
          .eq("is_active", true)
          .maybeSingle();

      if (initialStatusError || !initialStatus?.id) {
        console.error(
          "Failed to resolve required initial order status order_created",
          {
            error: initialStatusError?.message || "order_created_not_found",
            tenantId: patient!.tenant_id,
            patientId: patient!.id,
          },
        );
        return errorResponse(
          "ORDER_STATUS_ERROR",
          "Required initial order status order_created is not configured",
          500,
        );
      }

      const initialStatusId = initialStatus.id;

      // Create the order
      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .insert({
          tenant_id: patient!.tenant_id,
          patient_id: patient!.id,
          product_id: product.id,
          product_name: product.name,
          order_number: generateOrderNumber(),
          status_id: initialStatusId,
          status_changed_at: dateTime().toISOString(),
          subtotal_cents: subtotalCents,
          shipping_cents: shippingCents,
          tax_cents: taxCents,
          total_cents: totalCents,
          shipping_first_name: finalAddress.first_name,
          shipping_last_name: finalAddress.last_name,
          shipping_company: finalAddress.company,
          shipping_address_line1: finalAddress.line1,
          shipping_address_line2: finalAddress.line2,
          shipping_city: finalAddress.city,
          shipping_state: finalAddress.state,
          shipping_postal_code: finalAddress.postal_code,
          shipping_country: finalAddress.country,
          shipping_instructions: finalAddress.instructions,
        })
        .select()
        .single();

      if (orderError) {
        console.error("Order creation error:", orderError);
        return errorResponse("ORDER_ERROR", "Failed to create order", 500);
      }

      await triggerOrderLifecycleForOrder(order.id, patient!.tenant_id);

      const statusDetails = initialStatus
        ? {
          id: initialStatus.id,
          key: initialStatus.status_key,
          label: initialStatus.patient_status_label ||
            initialStatus.status_key
              .replace(/_/g, " ")
              .replace(/\b\w/g, (c: string) => c.toUpperCase()),
          description: initialStatus.patient_microcopy || null,
          action_required: initialStatus.patient_action_required,
          is_final: initialStatus.is_terminal,
          display_order: initialStatus.display_order,
        }
        : null;

      return jsonResponse(
        {
          message: "Order created successfully",
          data: {
            id: order.id,
            order_number: order.order_number,
            status: statusDetails?.key ?? null,
            status_id: order.status_id,
            status_details: statusDetails,
            product: {
              id: product.id,
              name: product.name,
              price_cents: product.price_cents,
              terms_and_conditions_html: product.terms_and_conditions_html,
              subscription_renewal_lead_days:
                product.subscription_renewal_lead_days,
            },
            subtotal_cents: order.subtotal_cents,
            shipping_cents: order.shipping_cents,
            tax_cents: order.tax_cents,
            total_cents: order.total_cents,
            total_formatted: `$${(order.total_cents / 100).toFixed(2)}`,
            subscription_order_type: order.subscription_order_type,
            shipping_address: {
              first_name: order.shipping_first_name,
              last_name: order.shipping_last_name,
              company: order.shipping_company,
              line1: order.shipping_address_line1,
              line2: order.shipping_address_line2,
              city: order.shipping_city,
              state: order.shipping_state,
              postal_code: order.shipping_postal_code,
              country: order.shipping_country,
              instructions: order.shipping_instructions,
            },
            renewal_at: order.renewal_at,
            created_at: order.created_at,
          },
        },
        201,
      );
    }

    // GET /orders - List patient's orders
    if (req.method === "GET" && path === "/orders") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      // Pagination
      const page = parseInt(url.searchParams.get("page") || "1");
      const pageSize = Math.min(
        parseInt(url.searchParams.get("page_size") || "20"),
        100,
      );
      const offset = (page - 1) * pageSize;

      // Status filter by status_id (UUID of order_statuses record)
      const statusIdFilter = url.searchParams.get("status_id");
      // Status key filter (e.g., "delivered", "in_transit")
      const statusKeyFilter = url.searchParams.get("status_key");

      let query = supabase
        .from("orders")
        .select(
          `id, order_number, status_id, cancellation_reason, status_changed_at, subtotal_cents, shipping_cents, tax_cents, total_cents, tracking_number, tracking_url, shipped_at, delivered_at, cancelled_at, paused_at, renewal_at, subscription_order_type, idv_locked_at, payment_failed_at, payment_retry_count, created_at, updated_at,
          subscription:subscriptions (
            current_period_end_at,
            expires_at
          ),
          product:products (
            name
          ),
          order_statuses (
            id,
            status_key,
            patient_status_label,
            patient_microcopy,
            patient_action_required,
            is_terminal,
            display_order
          )`,
          { count: "exact" },
        )
        .eq("patient_id", patient!.id)
        .order("created_at", { ascending: false })
        .range(offset, offset + pageSize - 1);

      if (statusIdFilter) {
        query = query.eq("status_id", statusIdFilter);
      }

      if (statusKeyFilter) {
        // Filter by joining on order_statuses.status_key
        query = query.eq("order_statuses.status_key", statusKeyFilter);
      }

      const { data: orders, error: ordersError, count } = await query;

      if (ordersError) {
        console.error("Orders fetch error:", ordersError);
        return errorResponse("FETCH_ERROR", "Failed to fetch orders", 500);
      }

      const transformedOrders = (orders || []).map((order) => {
        const statusInfo = order.order_statuses as unknown as {
          id: string;
          status_key: string;
          patient_status_label: string | null;
          patient_microcopy: string | null;
          patient_action_required: boolean;
          is_terminal: boolean;
          display_order: number;
        } | null;

        const product = asSingle(
          order.product as { name: string } | { name: string }[] | null,
        );
        const subscription = asSingle(
          order.subscription as
            | {
              current_period_end_at: string | null;
              expires_at: string | null;
            }
            | {
              current_period_end_at: string | null;
              expires_at: string | null;
            }[]
            | null,
        );

        return {
          id: order.id,
          order_number: order.order_number,
          status_id: order.status_id,
          product_title: product?.name || null,
          status_details: statusInfo
            ? {
              id: statusInfo.id,
              key: statusInfo.status_key,
              label: statusInfo.patient_status_label ||
                statusInfo.status_key
                  .replace(/_/g, " ")
                  .replace(/\b\w/g, (c: string) => c.toUpperCase()),
              description: statusInfo.patient_microcopy || null,
              action_required: statusInfo.patient_action_required,
              is_final: statusInfo.is_terminal,
              display_order: statusInfo.display_order,
            }
            : null,
          status_changed_at: order.status_changed_at,
          subtotal_cents: order.subtotal_cents,
          shipping_cents: order.shipping_cents,
          tax_cents: order.tax_cents,
          total_cents: order.total_cents,
          total_formatted: `$${(order.total_cents / 100).toFixed(2)}`,
          subscription_order_type: order.subscription_order_type,
          tracking: order.tracking_number
            ? {
              number: order.tracking_number,
              url: order.tracking_url,
            }
            : null,
          shipped_at: order.shipped_at,
          delivered_at: order.delivered_at,
          cancelled_at: order.cancelled_at,
          cancellation_reason: order.cancellation_reason || null,
          paused_at: order.paused_at,
          renewal_at: subscription?.current_period_end_at || null,
          expires_at: subscription?.expires_at || null,
          idv_locked_at: order.idv_locked_at || null,
          payment_failed_at: order.payment_failed_at || null,
          payment_retry_count: order.payment_retry_count ?? 0,
          created_at: order.created_at,
          updated_at: order.updated_at,
        };
      });

      return jsonResponse({
        data: transformedOrders,
        pagination: {
          page,
          page_size: pageSize,
          total: count || 0,
          total_pages: Math.ceil((count || 0) / pageSize),
          has_more: offset + pageSize < (count || 0),
        },
      });
    }

    // GET /orders/:id - Get order details
    if (req.method === "GET" && path.match(/^\/orders\/[a-f0-9-]+$/)) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: order, error: orderError } = await supabase
        .from("orders")
        .select(
          `
          *,
          subscription:subscriptions (
            current_period_end_at,
            expires_at
          ),
          product:products (
            name
          ),
          order_statuses (
            id,
            status_key,
            patient_status_label,
            patient_microcopy,
            patient_action_required,
            is_terminal,
            display_order
          )
        `,
        )
        .eq("id", orderId)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      // Get order status history
      const { data: statusHistory, error: historyError } = await supabase
        .from("order_status_history")
        .select(
          `
          id,
          created_at,
          notes,
          order_statuses (
            id,
            status_key,
            patient_status_label,
            patient_microcopy,
            patient_action_required,
            is_terminal
          )
        `,
        )
        .eq("order_id", orderId)
        .order("created_at", { ascending: true });

      if (historyError) {
        console.error("Status history fetch error:", historyError);
        // Don't fail the request, just log the error
      }

      const statusInfo = order.order_statuses as unknown as {
        id: string;
        status_key: string;
        patient_status_label: string | null;
        patient_microcopy: string | null;
        patient_action_required: boolean;
        is_terminal: boolean;
        display_order: number;
      } | null;

      const transformedHistory = (statusHistory || []).map((entry) => {
        const entryStatus = entry.order_statuses as unknown as {
          id: string;
          status_key: string;
          patient_status_label: string | null;
          patient_microcopy: string | null;
          patient_action_required: boolean;
          is_terminal: boolean;
        } | null;

        return {
          id: entry.id,
          timestamp: entry.created_at,
          status: entryStatus
            ? {
              id: entryStatus.id,
              key: entryStatus.status_key,
              label: entryStatus.patient_status_label ||
                entryStatus.status_key
                  .replace(/_/g, " ")
                  .replace(/\b\w/g, (c: string) => c.toUpperCase()),
              description: entryStatus.patient_microcopy || null,
              action_required: entryStatus.patient_action_required,
              is_final: entryStatus.is_terminal,
            }
            : null,
        };
      });

      return jsonResponse({
        data: {
          id: order.id,
          order_number: order.order_number,
          status_id: order.status_id,
          product_id: order.product_id || null,
          product_title: order.product?.name || null,
          status_details: statusInfo
            ? {
              id: statusInfo.id,
              key: statusInfo.status_key,
              label: statusInfo.patient_status_label ||
                statusInfo.status_key
                  .replace(/_/g, " ")
                  .replace(/\b\w/g, (c: string) => c.toUpperCase()),
              description: statusInfo.patient_microcopy || null,
              action_required: statusInfo.patient_action_required,
              is_final: statusInfo.is_terminal,
              display_order: statusInfo.display_order,
            }
            : null,
          status_changed_at: order.status_changed_at,
          status_history: transformedHistory,
          subtotal_cents: order.subtotal_cents,
          shipping_cents: order.shipping_cents,
          tax_cents: order.tax_cents,
          total_cents: order.total_cents,
          total_formatted: `$${(order.total_cents / 100).toFixed(2)}`,
          subscription_order_type: order.subscription_order_type,
          shipping_address: {
            first_name: order.shipping_first_name,
            last_name: order.shipping_last_name,
            company: order.shipping_company,
            line1: order.shipping_address_line1,
            line2: order.shipping_address_line2,
            city: order.shipping_city,
            state: order.shipping_state,
            postal_code: order.shipping_postal_code,
            country: order.shipping_country,
            instructions: order.shipping_instructions,
          },
          billing_address: {
            first_name: order.billing_first_name,
            last_name: order.billing_last_name,
            company: order.billing_company,
            line1: order.billing_address_line1,
            line2: order.billing_address_line2,
            city: order.billing_city,
            state: order.billing_state,
            postal_code: order.billing_postal_code,
            country: order.billing_country,
          },
          tracking: order.tracking_number
            ? {
              number: order.tracking_number,
              url: order.tracking_url,
            }
            : null,
          metadata: {
            cancellation_operation_key: order.cancellation_operation_key ||
              null,
            cancellation_operation_started_at:
              order.cancellation_operation_started_at || null,
            cancellation_operation_completed_at:
              order.cancellation_operation_completed_at || null,
          },
          internal_notes: null, // Don't expose internal notes to patients
          shipped_at: order.shipped_at,
          delivered_at: order.delivered_at,
          cancelled_at: order.cancelled_at,
          cancellation_reason: order.cancellation_reason || null,
          paused_at: order.paused_at,
          renewal_at: order.subscription?.current_period_end_at || null,
          expires_at: order.subscription?.expires_at || null,
          idv_locked_at: order.idv_locked_at || null,
          payment_failed_at: order.payment_failed_at || null,
          payment_retry_count: order.payment_retry_count ?? 0,
          created_at: order.created_at,
          updated_at: order.updated_at,
        },
      });
    }

    // GET /orders/:id/status-history - Get status history for a specific order
    if (
      req.method === "GET" &&
      path.match(/^\/orders\/[a-f0-9-]+\/status-history$/)
    ) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      // Verify the order belongs to this patient
      const { data: order, error: orderError } = await supabase
        .from("orders")
        .select(
          "id, order_number, status_id, status_changed_at, order_statuses(status_key)",
        )
        .eq("id", orderId)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      // Get status history
      const { data: statusHistory, error: historyError } = await supabase
        .from("order_status_history")
        .select(
          `
          id,
          created_at,
          notes,
          order_statuses (
            id,
            status_key,
            patient_status_label,
            patient_microcopy,
            patient_action_required,
            is_terminal,
            display_order
          )
        `,
        )
        .eq("order_id", orderId)
        .order("created_at", { ascending: true });

      if (historyError) {
        console.error("Status history fetch error:", historyError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch status history",
          500,
        );
      }

      const transformedHistory = (statusHistory || []).map((entry) => {
        const entryStatus = entry.order_statuses as unknown as {
          id: string;
          status_key: string;
          patient_status_label: string | null;
          patient_microcopy: string | null;
          patient_action_required: boolean;
          is_terminal: boolean;
          display_order: number;
        } | null;

        return {
          id: entry.id,
          timestamp: entry.created_at,
          status: entryStatus
            ? {
              id: entryStatus.id,
              key: entryStatus.status_key,
              label: entryStatus.patient_status_label ||
                entryStatus.status_key
                  .replace(/_/g, " ")
                  .replace(/\b\w/g, (c: string) => c.toUpperCase()),
              description: entryStatus.patient_microcopy || null,
              action_required: entryStatus.patient_action_required,
              is_final: entryStatus.is_terminal,
              display_order: entryStatus.display_order,
            }
            : null,
        };
      });

      const currentStatusInfo = asSingle(
        order.order_statuses as
          | {
            status_key: string;
          }
          | {
            status_key: string;
          }[]
          | null,
      );

      return jsonResponse({
        data: {
          order_id: order.id,
          order_number: order.order_number,
          current_status: currentStatusInfo?.status_key ?? null,
          status_changed_at: order.status_changed_at,
          history: transformedHistory,
          total_transitions: transformedHistory.length,
        },
      });
    }

    // POST /orders/:id/cancel - Queue order cancellation and let order-lifecycle process it
    if (req.method === "POST" && path.match(/^\/orders\/[a-f0-9-]+\/cancel$/)) {
      const orderId = path.split("/")[2];
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let reasonRaw: string | null = null;
      try {
        const rawBody = await req.text();
        if (rawBody.trim().length > 0) {
          const parsedBody = JSON.parse(rawBody) as { reason?: unknown };
          if (
            typeof parsedBody.reason === "string" &&
            parsedBody.reason.trim().length > 0
          ) {
            reasonRaw = parsedBody.reason.trim();
          }
        }
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select(
          `
          id,
          tenant_id,
          patient_id,
          order_number,
          cancelled_at,
          cancellation_reason,
          provider_platform_integration_key,
          order_statuses (
            id,
            status_key
          )
        `,
        )
        .eq("id", orderId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const orderStatus = order.order_statuses as unknown as {
        id: string;
        status_key: string;
      } | null;

      if (!orderStatus) {
        return errorResponse(
          "CONFIG_ERROR",
          "Order status configuration is missing for this order",
          500,
        );
      }

      if (orderStatus.status_key === "order_cancelled" || order.cancelled_at) {
        return jsonResponse({
          message: "Order is already cancelled",
          data: {
            id: order.id,
            order_number: order.order_number,
            status: "order_cancelled",
            cancelled_at: order.cancelled_at,
            cancellation_reason: order.cancellation_reason,
          },
        });
      }

      if (orderStatus.status_key === "order_pending_cancellation") {
        return jsonResponse({
          message: "Order cancellation is already pending",
          data: {
            id: order.id,
            order_number: order.order_number,
            status: "order_pending_cancellation",
            cancelled_at: null,
            cancellation_reason: order.cancellation_reason,
          },
        });
      }

      let telegraProviderLinkMetadata: TelegraProviderReviewMetadata[] = [];
      if (
        isTelegraProviderIntegrationKey(order.provider_platform_integration_key)
      ) {
        try {
          telegraProviderLinkMetadata = await fetchOrderProviderLinkMetadata({
            supabase: supabaseAdmin,
            orderId: order.id,
            tenantId: patient!.tenant_id,
            providerPlatformIntegrationKey:
              order.provider_platform_integration_key,
          });
        } catch (metadataError) {
          console.error(
            "Failed to inspect Telegra provider-review state before cancellation:",
            metadataError,
          );
          return errorResponse(
            "FETCH_ERROR",
            "Failed to inspect provider review state before cancellation",
            500,
          );
        }
      }

      const shouldDeferForTelegraProviderReview =
        shouldDeferTelegraProviderReviewCancellation({
          currentStatusKey: orderStatus.status_key,
          providerPlatformIntegrationKey:
            order.provider_platform_integration_key,
          providerLinkMetadata: telegraProviderLinkMetadata,
        });

      if (
        orderStatus.status_key === "provider_review_pending" ||
        shouldDeferForTelegraProviderReview
      ) {
        if (isMdiIntegrationKey(order.provider_platform_integration_key)) {
          const { data: pendingCancelStatus, error: pendingCancelStatusError } =
            await supabaseAdmin
              .from("order_statuses")
              .select("id, status_key")
              .eq("status_key", "order_pending_cancellation")
              .eq("is_active", true)
              .maybeSingle();

          if (pendingCancelStatusError || !pendingCancelStatus?.id) {
            console.error(
              "Pending-cancellation status lookup failed for MDI provider review cancellation",
              pendingCancelStatusError,
            );
            return errorResponse(
              "CONFIG_ERROR",
              "Order order_pending_cancellation status is not configured",
              500,
            );
          }

          const nowIso = dateTime().toISOString();
          const cancellationReason = order.cancellation_reason || reasonRaw;
          const { data: updatedOrder, error: updateOrderError } =
            await supabaseAdmin
              .from("orders")
              .update({
                status_id: pendingCancelStatus.id,
                status_changed_at: nowIso,
                cancellation_reason: cancellationReason,
              })
              .eq("id", order.id)
              .eq("tenant_id", patient!.tenant_id)
              .eq("patient_id", patient!.id)
              .select(
                "id, order_number, cancelled_at, cancellation_reason, order_statuses(status_key)",
              )
              .single();

          if (updateOrderError) {
            console.error(
              "Order update error while queueing MDI provider review cancellation:",
              updateOrderError,
            );
            return errorResponse(
              "UPDATE_ERROR",
              "Failed to queue MDI provider review cancellation",
              500,
            );
          }

          const { error: historyInsertError } = await supabaseAdmin
            .from("order_status_history")
            .insert({
              order_id: order.id,
              status_id: pendingCancelStatus.id,
              notes:
                "Patient requested order cancellation during MDI provider review; pending lifecycle processing.",
            });

          if (historyInsertError) {
            console.error(
              "Order status history insert error while queueing MDI provider review cancellation:",
              historyInsertError,
            );
            return errorResponse(
              "UPDATE_ERROR",
              "Failed to queue MDI provider review cancellation history",
              500,
            );
          }

          notifyRtdhOrderStatusUpdatedAsync({
            supabase: supabaseAdmin,
            requestId,
            tenantId: patient!.tenant_id,
            orderId: order.id,
            statusId: pendingCancelStatus.id,
            statusKey: pendingCancelStatus.status_key,
            previousStatusKey: orderStatus.status_key,
            source: "plan-api:order-cancel-request",
          });

          await triggerOrderLifecycleForOrder(order.id, patient!.tenant_id);

          const updatedStatusInfo = asSingle(
            updatedOrder.order_statuses as
              | {
                status_key: string;
              }
              | {
                status_key: string;
              }[]
              | null,
          );

          return jsonResponse({
            message: "Order cancellation requested successfully",
            data: {
              id: updatedOrder.id,
              order_number: updatedOrder.order_number,
              status: updatedStatusInfo?.status_key ||
                "order_pending_cancellation",
              cancelled_at: updatedOrder.cancelled_at,
              cancellation_reason: updatedOrder.cancellation_reason,
            },
          });
        }

        if (order.cancellation_reason) {
          return jsonResponse({
            message:
              "Order cancellation is already pending provider review resolution",
            data: {
              id: order.id,
              order_number: order.order_number,
              status: orderStatus.status_key,
              cancelled_at: null,
              cancellation_reason: order.cancellation_reason,
            },
          });
        }

        const { data: updatedOrder, error: updateOrderError } =
          await supabaseAdmin
            .from("orders")
            .update({
              cancellation_reason: reasonRaw,
            })
            .eq("id", order.id)
            .eq("tenant_id", patient!.tenant_id)
            .eq("patient_id", patient!.id)
            .select(
              "id, order_number, cancelled_at, cancellation_reason, order_statuses(status_key)",
            )
            .single();

        if (updateOrderError) {
          console.error(
            "Order update error while deferring cancellation during provider review:",
            updateOrderError,
          );
          return errorResponse(
            "UPDATE_ERROR",
            "Failed to save deferred order cancellation request",
            500,
          );
        }

        const { error: historyInsertError } = await supabaseAdmin
          .from("order_status_history")
          .insert({
            order_id: order.id,
            status_id: orderStatus.id,
            notes: shouldDeferForTelegraProviderReview &&
                orderStatus.status_key !== "provider_review_pending"
              ? "Patient requested order cancellation after Telegra reached provider review; local status was behind provider state, so processing will resume after provider decision."
              : "Patient requested order cancellation during provider review; processing will resume after provider decision.",
          });

        if (historyInsertError) {
          console.error(
            "Order status history insert error while deferring cancellation during provider review:",
            historyInsertError,
          );
          return errorResponse(
            "UPDATE_ERROR",
            "Failed to save deferred order cancellation history",
            500,
          );
        }

        const updatedStatusInfo = asSingle(
          updatedOrder.order_statuses as
            | {
              status_key: string;
            }
            | {
              status_key: string;
            }[]
            | null,
        );

        return jsonResponse({
          message:
            "Order cancellation requested successfully and will be processed after provider decision",
          data: {
            id: updatedOrder.id,
            order_number: updatedOrder.order_number,
            status: updatedStatusInfo?.status_key || orderStatus.status_key,
            cancelled_at: updatedOrder.cancelled_at,
            cancellation_reason: updatedOrder.cancellation_reason,
          },
        });
      }

      const { data: pendingCancelStatus, error: pendingCancelStatusError } =
        await supabaseAdmin
          .from("order_statuses")
          .select("id, status_key")
          .eq("status_key", "order_pending_cancellation")
          .eq("is_active", true)
          .maybeSingle();

      if (pendingCancelStatusError || !pendingCancelStatus?.id) {
        console.error(
          "Pending-cancellation status lookup failed",
          pendingCancelStatusError,
        );
        return errorResponse(
          "CONFIG_ERROR",
          "Order order_pending_cancellation status is not configured",
          500,
        );
      }

      const nowIso = dateTime().toISOString();
      const { data: updatedOrder, error: updateOrderError } =
        await supabaseAdmin
          .from("orders")
          .update({
            status_id: pendingCancelStatus.id,
            status_changed_at: nowIso,
            cancellation_reason: reasonRaw,
          })
          .eq("id", order.id)
          .eq("tenant_id", patient!.tenant_id)
          .eq("patient_id", patient!.id)
          .select(
            "id, order_number, cancelled_at, cancellation_reason, order_statuses(status_key)",
          )
          .single();

      if (updateOrderError) {
        console.error(
          "Order update error while queueing cancellation:",
          updateOrderError,
        );
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to queue order cancellation",
          500,
        );
      }

      const { error: historyInsertError } = await supabaseAdmin
        .from("order_status_history")
        .insert({
          order_id: order.id,
          status_id: pendingCancelStatus.id,
          notes:
            "Patient requested order cancellation; pending lifecycle processing.",
        });

      if (historyInsertError) {
        console.error(
          "Order status history insert error while queueing cancellation:",
          historyInsertError,
        );
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to queue order cancellation history",
          500,
        );
      }

      notifyRtdhOrderStatusUpdatedAsync({
        supabase: supabaseAdmin,
        requestId,
        tenantId: patient!.tenant_id,
        orderId: order.id,
        statusId: pendingCancelStatus.id,
        statusKey: pendingCancelStatus.status_key,
        previousStatusKey: orderStatus.status_key,
        source: "plan-api:order-cancel-request",
      });

      await triggerOrderLifecycleForOrder(order.id, patient!.tenant_id);

      const updatedStatusInfo = asSingle(
        updatedOrder.order_statuses as
          | {
            status_key: string;
          }
          | {
            status_key: string;
          }[]
          | null,
      );

      return jsonResponse({
        message: "Order cancellation requested successfully",
        data: {
          id: updatedOrder.id,
          order_number: updatedOrder.order_number,
          status: updatedStatusInfo?.status_key || "order_pending_cancellation",
          cancelled_at: updatedOrder.cancelled_at,
          cancellation_reason: updatedOrder.cancellation_reason,
        },
      });
    }

    // POST /orders/:id/resume - Re-run the order lifecycle for an order the
    // authenticated patient owns. Used after the post-payment account & contact
    // step (PP-566): once the patient verifies their email, the order — which the
    // contact-validation gate held at shipping_details_required — can advance
    // toward provider intake. Safe/idempotent: order-lifecycle re-evaluates state.
    //
    // PAYMENT GATE: this route advances the order toward the questionnaire and
    // provider intake, so it must refuse an order without a confirmed payment
    // authorization. Ownership is not enough — without this check a buyer can
    // reach the questionnaire (and provider intake) on an unpaid order simply by
    // calling this endpoint. Hiding the step in the UI does not prevent that.
    // See assertOrderPaid.
    if (
      req.method === "POST" &&
      path.match(/^\/orders\/[a-f0-9-]+\/resume$/)
    ) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("id, tenant_id, paid_at, total_cents")
        .eq("id", orderId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }
      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const { data: paymentTransactions, error: paymentTransactionsError } =
        await supabaseAdmin
          .from("order_payment_provider_transactions")
          .select("payment_status")
          .eq("tenant_id", order.tenant_id)
          .eq("order_id", order.id);

      if (paymentTransactionsError) {
        return errorResponse(
          "FETCH_ERROR",
          "Failed to verify order payment authorization",
          500,
        );
      }

      const paymentStatuses = (paymentTransactions ?? []).map(
        (transaction) => transaction.payment_status,
      );
      const normalizedPaymentStatuses = paymentStatuses.map((status) =>
        typeof status === "string" ? status.trim().toLowerCase() : null
      );
      const hasAuthorizedPaymentStatus = normalizedPaymentStatuses.some(
        (status) =>
          status === "requires_capture" ||
          status === "succeeded" ||
          status === "paid",
      );
      const paidGate = assertOrderPaid({
        ...order,
        payment_statuses: paymentStatuses,
      });
      if (!paidGate.ok) {
        console.warn("Blocked resume without payment authorization", {
          orderId,
          patientId: patient!.id,
          tenantId: order.tenant_id,
          paymentAuthorizationChecks: {
            hasPaidAt: Boolean(order.paid_at),
            totalCents: order.total_cents,
            isZeroAmountOrder: (order.total_cents ?? 0) <= 0,
            paymentTransactionCount: paymentStatuses.length,
            paymentStatuses: normalizedPaymentStatuses,
            hasAuthorizedPaymentStatus,
          },
          rejectionReasons: [
            !order.paid_at ? "paid_at_missing" : null,
            (order.total_cents ?? 0) > 0 ? "positive_order_total" : null,
            !hasAuthorizedPaymentStatus ? "no_authorized_payment_status" : null,
          ].filter(Boolean),
        });
        return errorResponse(paidGate.code, paidGate.message, paidGate.status);
      }

      try {
        await triggerOrderLifecycleForOrder(order.id, patient!.tenant_id);
      } catch (lifecycleError) {
        console.error("Order resume: lifecycle trigger failed", {
          orderId,
          error: lifecycleError,
        });
      }

      return jsonResponse({
        message: "Order resumed",
        data: { order_id: order.id },
      });
    }

    // POST /orders/:id/setup-complete - Finalize a zero-amount ($0/100%-off)
    // subscription after the embedded SetupIntent has saved a card. Verifies the
    // SetupIntent succeeded, sets the saved card as the customer's default
    // payment method (so renewals can charge off_session), and triggers the
    // order lifecycle (which advances order_created and, at the payment-skip
    // step, creates the Stripe subscription with the saved card).
    if (
      req.method === "POST" &&
      path.match(/^\/orders\/[a-f0-9-]+\/setup-complete$/)
    ) {
      const orderId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse("TENANT_NOT_FOUND", "Tenant not found", 404);
      }

      let body: { setup_intent_id?: string } = {};
      try {
        body = await req.json();
      } catch {
        // body optional
      }
      const setupIntentId = (body.setup_intent_id ?? "").trim();
      if (!setupIntentId) {
        return errorResponse(
          "SETUP_INTENT_REQUIRED",
          "setup_intent_id is required",
          400,
        );
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("id, tenant_id")
        .eq("id", orderId)
        .eq("tenant_id", tenant.id)
        .maybeSingle();
      if (orderError || !order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      // Resolve the tenant Stripe secret key.
      const { data: stripeProvider } = await supabaseAdmin
        .from("tenant_payment_providers")
        .select("settings, payment_providers!inner (key)")
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();
      const stripeSecretKey =
        (stripeProvider?.settings as Record<string, string>)?.secret_key
          ?.trim() || "";
      if (!stripeSecretKey) {
        return errorResponse(
          "PROVIDER_NOT_CONFIGURED",
          "Stripe secret key not configured",
          400,
        );
      }

      // Verify the SetupIntent succeeded and belongs to this order.
      const siRes = await fetch(
        `https://api.stripe.com/v1/setup_intents/${
          encodeURIComponent(setupIntentId)
        }`,
        { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
      );
      if (!siRes.ok) {
        return errorResponse(
          "STRIPE_ERROR",
          "Could not verify subscription setup",
          400,
        );
      }
      const setupIntent = await siRes.json() as {
        status?: string;
        customer?: string | { id?: string } | null;
        payment_method?: string | { id?: string } | null;
        metadata?: Record<string, string>;
      };
      if (setupIntent.status !== "succeeded") {
        return errorResponse(
          "SETUP_NOT_COMPLETE",
          "Payment method has not been saved yet",
          409,
        );
      }
      if (setupIntent.metadata?.patient_platform_order_id !== orderId) {
        return errorResponse(
          "SETUP_MISMATCH",
          "Setup does not match this order",
          409,
        );
      }

      const setupCustomerId = typeof setupIntent.customer === "string"
        ? setupIntent.customer
        : setupIntent.customer?.id || "";
      const setupPaymentMethodId =
        typeof setupIntent.payment_method === "string"
          ? setupIntent.payment_method
          : setupIntent.payment_method?.id || "";

      // Make the saved card the customer's default so off_session renewals work.
      if (setupCustomerId && setupPaymentMethodId) {
        const updateParams = new URLSearchParams();
        updateParams.append(
          "invoice_settings[default_payment_method]",
          setupPaymentMethodId,
        );
        await fetch(
          `https://api.stripe.com/v1/customers/${
            encodeURIComponent(setupCustomerId)
          }`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: updateParams.toString(),
          },
        ).catch((e) =>
          console.warn("setup-complete: failed to set default PM", {
            orderId,
            error: e instanceof Error ? e.message : String(e),
          })
        );
      }

      // Advance the order — this dispatches RTDH create-order and, at the
      // payment-skip step, creates the Stripe subscription with the saved card.
      try {
        await triggerOrderLifecycleForOrder(order.id, tenant.id);
      } catch (lifecycleError) {
        console.error(
          "setup-complete: lifecycle trigger failed (card saved)",
          { orderId, error: lifecycleError },
        );
      }

      console.info("Zero-amount subscription setup completed", {
        orderId,
        setupIntentId,
      });

      return jsonResponse({
        message: "Subscription setup complete",
        data: { order_id: order.id },
      });
    }

    // POST /orders/:id/retry-payment - Retry a failed payment for an order in payment_failed status
    if (
      req.method === "POST" &&
      path.match(/^\/orders\/[a-f0-9-]+\/retry-payment$/)
    ) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select(
          `
          id,
          tenant_id,
          patient_id,
          order_number,
          subscription_id,
          payment_failed_at,
          payment_retry_count,
          order_statuses (
            id,
            status_key
          )
        `,
        )
        .eq("id", orderId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error for retry-payment:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const orderStatus = order.order_statuses as unknown as {
        id: string;
        status_key: string;
      } | null;

      if (!orderStatus || orderStatus.status_key !== "payment_failed") {
        return errorResponse(
          "INVALID_STATE",
          "Order is not in payment_failed status",
          409,
        );
      }

      const paymentFailedAt = order.payment_failed_at
        ? new Date(order.payment_failed_at as string)
        : null;
      if (paymentFailedAt) {
        const sevenDaysAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        if (paymentFailedAt < sevenDaysAgo) {
          return errorResponse(
            "PAYMENT_WINDOW_EXPIRED",
            "The 7-day payment retry window has expired for this order",
            410,
          );
        }
      }

      // Look up most recent payment intent from transactions
      const { data: transactions, error: txError } = await supabaseAdmin
        .from("order_payment_provider_transactions")
        .select("provider_payment_intent_id, provider_invoice_id")
        .eq("order_id", orderId)
        .eq("tenant_id", patient!.tenant_id)
        .order("created_at", { ascending: false })
        .limit(5);

      if (txError) {
        console.error("Transaction fetch error for retry-payment:", txError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch payment transaction",
          500,
        );
      }

      let paymentIntentId = (transactions ?? []).find(
        (tx: { provider_payment_intent_id: string | null }) =>
          tx.provider_payment_intent_id,
      )?.provider_payment_intent_id ?? null;

      // If no payment intent, check if we have a stored invoice ID
      let fallbackInvoiceId = !paymentIntentId
        ? ((transactions ?? []).find(
          (tx: { provider_invoice_id: string | null }) =>
            tx.provider_invoice_id,
        )?.provider_invoice_id ?? null)
        : null;

      // Fallback: resolve the payment intent from the Stripe subscription's latest invoice
      if (!paymentIntentId && order.subscription_id) {
        const { data: subLink } = await supabaseAdmin
          .from("subscription_payment_provider_links")
          .select(
            `
            provider_subscription_id,
            payment_providers!inner (
              key
            )
          `,
          )
          .eq("subscription_id", order.subscription_id)
          .eq("tenant_id", patient!.tenant_id)
          .eq("payment_providers.key", "stripe")
          .maybeSingle();

        const stripeSubId = subLink?.provider_subscription_id ?? null;
        if (stripeSubId) {
          // We need the secret key early here — fetch the provider settings temporarily
          const { data: providerForFallback } = await supabaseAdmin
            .from("tenant_payment_providers")
            .select(`id, settings, payment_providers!inner ( key )`)
            .eq("tenant_id", patient!.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

          const fallbackSettings = providerForFallback?.settings &&
              typeof providerForFallback.settings === "object" &&
              !Array.isArray(providerForFallback.settings)
            ? (providerForFallback.settings as Record<string, unknown>)
            : {};
          const fallbackKey = typeof fallbackSettings.secret_key === "string"
            ? fallbackSettings.secret_key.trim()
            : "";

          if (fallbackKey && providerForFallback) {
            const subRes = await fetch(
              `https://api.stripe.com/v1/subscriptions/${stripeSubId}?expand[]=latest_invoice.payment_intent`,
              {
                method: "GET",
                headers: { Authorization: `Bearer ${fallbackKey}` },
              },
            );
            if (subRes.ok) {
              const sub = (await subRes.json()) as {
                latest_invoice?: {
                  id?: string;
                  payment_intent?: { id?: string } | string | null;
                } | null;
              };
              const invoice = sub.latest_invoice;
              const pi = invoice?.payment_intent;
              const resolvedId = typeof pi === "string"
                ? pi
                : typeof pi === "object" && pi?.id
                ? pi.id
                : null;
              if (resolvedId) {
                paymentIntentId = resolvedId;
                fallbackInvoiceId = null;
                // Persist so future retries don't need to re-fetch
                await supabaseAdmin
                  .from("order_payment_provider_transactions")
                  .upsert(
                    {
                      order_id: orderId,
                      tenant_id: patient!.tenant_id,
                      payment_provider_id: providerForFallback.id,
                      provider_payment_intent_id: resolvedId,
                      provider_invoice_id: invoice?.id ?? null,
                    },
                    {
                      onConflict:
                        "payment_provider_id,provider_payment_intent_id",
                    },
                  );
              } else if (invoice?.id) {
                // Invoice exists but has no payment intent yet — pay the invoice directly
                fallbackInvoiceId = invoice.id;
              }
            }
          }
        }
      }

      if (!paymentIntentId && !fallbackInvoiceId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "No payment intent or invoice found for this order",
          400,
        );
      }

      // Get Stripe secret key
      const { data: stripeProvider, error: providerError } = await supabaseAdmin
        .from("tenant_payment_providers")
        .select(
          `
          settings,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("tenant_id", patient!.tenant_id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (providerError || !stripeProvider) {
        return errorResponse(
          "NO_PAYMENT_PROVIDER",
          "No Stripe payment provider configured for this tenant",
          400,
        );
      }

      const providerSettings = stripeProvider.settings &&
          typeof stripeProvider.settings === "object" &&
          !Array.isArray(stripeProvider.settings)
        ? (stripeProvider.settings as Record<string, unknown>)
        : {};
      const stripeSecretKey = typeof providerSettings.secret_key === "string"
        ? providerSettings.secret_key.trim()
        : "";

      if (!stripeSecretKey) {
        return errorResponse(
          "PROVIDER_NOT_CONFIGURED",
          "Stripe secret key not configured",
          400,
        );
      }

      // Attempt to charge: confirm the payment intent or pay the invoice directly
      let stripeConfirmResponse: Response;
      if (paymentIntentId) {
        // Fetch the customer's current default payment method so we charge the new card,
        // not the old failed one stored on the payment intent
        let defaultPaymentMethodId: string | null = null;
        const { data: patientMeta } = await supabaseAdmin
          .from("patients")
          .select("metadata")
          .eq("id", patient!.id)
          .eq("tenant_id", patient!.tenant_id)
          .maybeSingle();
        const meta = patientMeta?.metadata &&
            typeof patientMeta.metadata === "object" &&
            !Array.isArray(patientMeta.metadata)
          ? (patientMeta.metadata as Record<string, unknown>)
          : {};
        const stripeCustomerId = typeof meta.stripe_customer_id === "string"
          ? meta.stripe_customer_id.trim()
          : "";

        if (stripeCustomerId) {
          const custRes = await fetch(
            `https://api.stripe.com/v1/customers/${stripeCustomerId}`,
            {
              method: "GET",
              headers: { Authorization: `Bearer ${stripeSecretKey}` },
            },
          );
          if (custRes.ok) {
            const cust = (await custRes.json()) as {
              invoice_settings?: { default_payment_method?: string | null };
              default_source?: string | null;
            };
            defaultPaymentMethodId =
              cust.invoice_settings?.default_payment_method ||
              cust.default_source ||
              null;
          }
        }

        const confirmParams = new URLSearchParams();
        confirmParams.append("off_session", "true");
        confirmParams.append("error_on_requires_action", "true");
        if (defaultPaymentMethodId) {
          confirmParams.append("payment_method", defaultPaymentMethodId);
        }
        stripeConfirmResponse = await fetch(
          `https://api.stripe.com/v1/payment_intents/${paymentIntentId}/confirm`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: confirmParams.toString(),
          },
        );
      } else {
        // No payment intent — pay the invoice directly (Stripe will use the customer's current default)
        stripeConfirmResponse = await fetch(
          `https://api.stripe.com/v1/invoices/${fallbackInvoiceId}/pay`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
          },
        );
      }

      const newRetryCount = ((order.payment_retry_count as number) ?? 0) + 1;

      if (!stripeConfirmResponse.ok) {
        // Increment retry counter and surface the error
        await supabaseAdmin
          .from("orders")
          .update({ payment_retry_count: newRetryCount })
          .eq("id", orderId)
          .eq("tenant_id", patient!.tenant_id);

        const errorBody = await stripeConfirmResponse.text();
        let stripeErrorMessage = "Payment retry failed";
        try {
          const parsed = JSON.parse(errorBody) as {
            error?: { message?: string };
          };
          stripeErrorMessage = parsed.error?.message || stripeErrorMessage;
        } catch {
          // ignore
        }

        console.error("Payment retry failed for order", {
          orderId,
          paymentIntentId,
          retryCount: newRetryCount,
          status: stripeConfirmResponse.status,
        });

        await supabaseAdmin.from("order_status_history").insert({
          order_id: orderId,
          status_id: orderStatus.id,
          notes:
            `Payment retry attempt ${newRetryCount} failed for payment intent ${paymentIntentId}: ${stripeErrorMessage}`,
        });

        return jsonResponse(
          {
            error: {
              code: "PAYMENT_RETRY_FAILED",
              message: stripeErrorMessage,
            },
            data: { payment_retry_count: newRetryCount },
          },
          422,
        );
      }

      // Success — advance order to payment_pending and clear payment_failed_at
      const { data: paymentPendingStatus } = await supabaseAdmin
        .from("order_statuses")
        .select("id, status_key")
        .eq("status_key", "payment_pending")
        .eq("is_active", true)
        .maybeSingle();

      if (!paymentPendingStatus?.id) {
        return errorResponse(
          "STATUS_NOT_CONFIGURED",
          "payment_pending status is not configured",
          500,
        );
      }

      const nowIso = dateTime().toISOString();
      await supabaseAdmin
        .from("orders")
        .update({
          status_id: paymentPendingStatus.id,
          status_changed_at: nowIso,
          paid_at: nowIso,
          payment_failed_at: null,
          payment_retry_count: 0,
        })
        .eq("id", orderId)
        .eq("tenant_id", patient!.tenant_id);

      await supabaseAdmin.from("order_status_history").insert({
        order_id: orderId,
        status_id: paymentPendingStatus.id,
        notes:
          `Payment retry succeeded for payment intent ${paymentIntentId}; order advanced to payment_pending.`,
      });

      notifyRtdhOrderStatusUpdatedAsync({
        supabase: supabaseAdmin,
        requestId,
        tenantId: patient!.tenant_id,
        orderId,
        statusId: paymentPendingStatus.id,
        statusKey: paymentPendingStatus.status_key,
        previousStatusKey: orderStatus.status_key,
        source: "plan-api:payment-retry",
      });

      await triggerOrderLifecycleForOrder(orderId, patient!.tenant_id);

      return jsonResponse({
        message: "Payment retry initiated successfully",
        data: {
          id: orderId,
          status: "payment_pending",
        },
      });
    }

    // PATCH /orders/:id - Update order (cancel or update shipping address for patients)
    // POST /orders/:id/payment-portal - Create a Stripe Billing Portal session scoped to the order's plan
    if (
      req.method === "POST" &&
      path.match(/^\/orders\/[a-f0-9-]+\/payment-portal$/)
    ) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: { return_url?: string } = {};
      try {
        body = await req.json();
      } catch {
        // Body is optional
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("id, tenant_id, patient_id, subscription_id")
        .eq("id", orderId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error for payment-portal:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      if (!order.subscription_id) {
        return errorResponse(
          "PLAN_REFERENCE_MISSING",
          "Order is not linked to a plan",
          400,
        );
      }

      // Delegate to the plan-level portal logic
      const originHeader = req.headers.get("origin");
      const refererHeader = req.headers.get("referer");
      let baseUrl: string;
      if (originHeader) {
        baseUrl = originHeader;
      } else if (refererHeader) {
        try {
          baseUrl = new URL(refererHeader).origin;
        } catch {
          baseUrl = url.origin;
        }
      } else {
        baseUrl = url.origin;
      }

      const returnUrlRaw = typeof body.return_url === "string"
        ? body.return_url.trim()
        : "";
      const returnUrl = returnUrlRaw || `${baseUrl}/my-plan/orders/${orderId}`;

      let parsedReturnUrl: URL;
      try {
        parsedReturnUrl = new URL(returnUrl);
      } catch {
        return errorResponse(
          "INVALID_RETURN_URL",
          "return_url must be an absolute URL",
          400,
        );
      }

      if (!["http:", "https:"].includes(parsedReturnUrl.protocol)) {
        return errorResponse(
          "INVALID_RETURN_URL",
          "return_url must use http or https",
          400,
        );
      }

      // Fetch the plan to confirm patient ownership
      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select("id, tenant_id, patient_id, status")
        .eq("id", order.subscription_id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error("Plan fetch error for order payment-portal:", planError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      const { data: stripeProvider, error: providerError } = await supabaseAdmin
        .from("tenant_payment_providers")
        .select(
          `
          id,
          settings,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("tenant_id", patient!.tenant_id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (providerError || !stripeProvider) {
        return errorResponse(
          "NO_PAYMENT_PROVIDER",
          "No Stripe payment provider configured for this tenant",
          400,
        );
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
        return errorResponse(
          "PROVIDER_NOT_CONFIGURED",
          "Stripe secret key not configured",
          400,
        );
      }

      // Resolve Stripe customer ID from patient metadata or subscription
      const { data: patientPaymentContext } = await supabaseAdmin
        .from("patients")
        .select("metadata")
        .eq("id", patient!.id)
        .eq("tenant_id", patient!.tenant_id)
        .maybeSingle();

      const patientMetadata = patientPaymentContext?.metadata &&
          typeof patientPaymentContext.metadata === "object" &&
          !Array.isArray(patientPaymentContext.metadata)
        ? (patientPaymentContext.metadata as Record<string, unknown>)
        : {};

      let stripeCustomerId =
        typeof patientMetadata.stripe_customer_id === "string"
          ? patientMetadata.stripe_customer_id.trim()
          : "";

      if (!stripeCustomerId) {
        const { data: stripeLink } = await supabaseAdmin
          .from("subscription_payment_provider_links")
          .select(
            `
            provider_subscription_id,
            payment_providers!inner (
              key
            )
          `,
          )
          .eq("subscription_id", plan.id)
          .eq("tenant_id", patient!.tenant_id)
          .eq("payment_providers.key", "stripe")
          .maybeSingle();

        const stripeSubscriptionId = stripeLink?.provider_subscription_id ||
          null;
        if (stripeSubscriptionId) {
          const subRes = await fetch(
            `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
            {
              method: "GET",
              headers: { Authorization: `Bearer ${stripeSecretKey}` },
            },
          );
          if (subRes.ok) {
            const sub = (await subRes.json()) as {
              customer?: string | { id?: string } | null;
            };
            const customerId = typeof sub.customer === "string"
              ? sub.customer
              : sub.customer?.id || "";
            if (customerId) {
              stripeCustomerId = customerId;
              await supabaseAdmin
                .from("patients")
                .update({
                  metadata: {
                    ...patientMetadata,
                    stripe_customer_id: customerId,
                  },
                })
                .eq("id", patient!.id)
                .eq("tenant_id", patient!.tenant_id);
            }
          }
        }
      }

      if (!stripeCustomerId) {
        return errorResponse(
          "PAYMENT_CUSTOMER_NOT_FOUND",
          "Stripe customer reference not found",
          404,
        );
      }

      const {
        configurationId: stripePortalConfigurationId,
        error: stripePortalConfigurationError,
      } = await ensureStripePaymentDetailsPortalConfiguration({
        tenantId: patient!.tenant_id,
        tenantPaymentProviderId: stripeProvider.id,
        stripeSecretKey,
        settings,
      });

      if (stripePortalConfigurationError) return stripePortalConfigurationError;
      if (!stripePortalConfigurationId) {
        return errorResponse(
          "STRIPE_ERROR",
          "Stripe billing portal configuration is unavailable",
          500,
        );
      }

      const portalParams = new URLSearchParams();
      portalParams.append("customer", stripeCustomerId);
      portalParams.append("configuration", stripePortalConfigurationId);
      portalParams.append("return_url", parsedReturnUrl.toString());

      const stripePortalResponse = await fetch(
        "https://api.stripe.com/v1/billing_portal/sessions",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${stripeSecretKey}`,
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: portalParams.toString(),
        },
      );

      if (!stripePortalResponse.ok) {
        const errorText = await stripePortalResponse.text();
        let errorMessage = "Failed to create Stripe billing portal session";
        try {
          const parsed = JSON.parse(errorText) as {
            error?: { message?: string };
          };
          errorMessage = parsed.error?.message || errorMessage;
        } catch {
          // ignore
        }
        console.error(
          "Stripe billing portal session creation error for order portal",
          {
            orderId,
            customerId: stripeCustomerId,
            status: stripePortalResponse.status,
            error: errorText,
          },
        );
        return errorResponse("STRIPE_ERROR", errorMessage, 500);
      }

      const portalSession = (await stripePortalResponse.json()) as {
        id?: string;
        url?: string;
        return_url?: string;
      };

      if (!portalSession.id || !portalSession.url) {
        return errorResponse(
          "STRIPE_ERROR",
          "Stripe returned an invalid billing portal session response",
          500,
        );
      }

      return jsonResponse({
        message: "Payment portal session created",
        data: {
          order_id: orderId,
          session_id: portalSession.id,
          portal_url: portalSession.url,
          return_url: portalSession.return_url || parsedReturnUrl.toString(),
        },
      });
    }

    // PATCH /orders/:id - Update order (cancel or update shipping address for patients)
    if (req.method === "PATCH" && path.match(/^\/orders\/[a-f0-9-]+$/)) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: {
        action?: string;
        reason?: string;
        shipping_address?: {
          first_name?: string;
          last_name?: string;
          company?: string;
          line1?: string;
          line2?: string;
          city?: string;
          state?: string;
          postal_code?: string;
          country?: string;
          instructions?: string;
        };
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { action, shipping_address } = body;
      const reasonRaw =
        typeof body.reason === "string" && body.reason.trim().length > 0
          ? body.reason.trim()
          : null;

      // Get the order
      const { data: order, error: orderError } = await supabase
        .from("orders")
        .select("*, order_statuses(status_key)")
        .eq("id", orderId)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const statusInfo = asSingle(
        order.order_statuses as
          | {
            status_key: string;
          }
          | {
            status_key: string;
          }[]
          | null,
      );
      const statusKey = statusInfo?.status_key ?? null;

      // Handle cancel action
      if (action === "cancel") {
        // Only allow cancellation of pending orders
        if (statusKey !== "order_created") {
          return errorResponse(
            "CANNOT_CANCEL",
            `Cannot cancel order with status '${
              statusKey ?? "unknown"
            }'. Only order_created orders can be cancelled.`,
            400,
          );
        }

        const { data: cancelStatus, error: cancelStatusError } =
          await supabaseAdmin
            .from("order_statuses")
            .select("id")
            .eq("status_key", "order_cancelled")
            .eq("is_active", true)
            .maybeSingle();

        if (cancelStatusError || !cancelStatus?.id) {
          console.error("Cancel status lookup failed", cancelStatusError);
          return errorResponse(
            "CONFIG_ERROR",
            "Order cancellation status is not configured",
            500,
          );
        }

        // Update order status
        const { data: updatedOrder, error: updateError } = await supabaseAdmin
          .from("orders")
          .update({
            status_id: cancelStatus.id,
            status_changed_at: dateTime().toISOString(),
            cancelled_at: dateTime().toISOString(),
            cancellation_reason: reasonRaw,
          })
          .eq("id", orderId)
          .select(
            "id, order_number, status_id, cancelled_at, cancellation_reason, order_statuses(status_key)",
          )
          .single();

        if (updateError) {
          console.error("Order update error:", updateError);
          return errorResponse("UPDATE_ERROR", "Failed to cancel order", 500);
        }

        const updatedStatusInfo = asSingle(
          updatedOrder.order_statuses as
            | {
              status_key: string;
            }
            | {
              status_key: string;
            }[]
            | null,
        );

        if (typeof order.tenant_id === "string") {
          await notifyRtdhOrderCancelled({
            supabase: supabaseAdmin,
            requestId,
            orderId,
            tenantId: order.tenant_id,
            statusId: cancelStatus.id,
            previousStatusKey: statusKey,
            cancellationStage: "before_provider_creation",
            cancellationReason: reasonRaw || "patient_requested",
            source: "plan-api:pre-provider-order-cancelled",
          });
        } else {
          console.warn(
            "RTDH pre-provider cancellation notification skipped: order tenant_id is missing",
            { requestId, orderId },
          );
        }

        return jsonResponse({
          message: "Order cancelled successfully",
          data: {
            id: updatedOrder.id,
            order_number: updatedOrder.order_number,
            status: updatedStatusInfo?.status_key ?? null,
            cancelled_at: updatedOrder.cancelled_at,
            cancellation_reason: updatedOrder.cancellation_reason,
          },
        });
      }

      // Handle shipping address update
      if (shipping_address) {
        // Only allow shipping address updates for pending or processing orders
        if (statusKey !== "shipping_details_required") {
          return errorResponse(
            "CANNOT_UPDATE",
            `Cannot update shipping address for order with status '${
              statusKey ?? "unknown"
            }'. Only shipping_details_required orders can be updated.`,
            400,
          );
        }

        // Build update object with only provided fields
        const updateData: Record<string, string | null> = {};
        if (shipping_address.first_name !== undefined) {
          updateData.shipping_first_name = shipping_address.first_name || null;
        }
        if (shipping_address.last_name !== undefined) {
          updateData.shipping_last_name = shipping_address.last_name || null;
        }
        if (shipping_address.company !== undefined) {
          updateData.shipping_company = shipping_address.company || null;
        }
        if (shipping_address.line1 !== undefined) {
          updateData.shipping_address_line1 = shipping_address.line1 || null;
        }
        if (shipping_address.line2 !== undefined) {
          updateData.shipping_address_line2 = shipping_address.line2 || null;
        }
        if (shipping_address.city !== undefined) {
          updateData.shipping_city = shipping_address.city || null;
        }
        if (shipping_address.state !== undefined) {
          updateData.shipping_state = shipping_address.state || null;
        }
        if (shipping_address.postal_code !== undefined) {
          updateData.shipping_postal_code = shipping_address.postal_code ||
            null;
        }
        if (shipping_address.country !== undefined) {
          updateData.shipping_country = shipping_address.country || null;
        }
        if (shipping_address.instructions !== undefined) {
          updateData.shipping_instructions = shipping_address.instructions ||
            null;
        }

        if (Object.keys(updateData).length === 0) {
          return errorResponse(
            "NO_CHANGES",
            "No shipping address fields provided",
            400,
          );
        }

        // Validate shipping state if being updated
        if (shipping_address.state !== undefined) {
          // Determine the country to check (use new value if provided, else existing order value)
          const countryToCheck = shipping_address.country ||
            order.shipping_country || "US";
          const stateValidation = await validateStateAgainstTenant(
            supabaseAdmin,
            patient!.tenant_id,
            shipping_address.state,
            countryToCheck,
          );
          if (!stateValidation.valid) {
            return errorResponse(
              "INVALID_STATE",
              stateValidation.message!,
              400,
            );
          }
        }

        const { data: updatedOrder, error: updateError } = await supabaseAdmin
          .from("orders")
          .update(updateData)
          .eq("id", orderId)
          .select("*, order_statuses(status_key)")
          .single();

        if (updateError) {
          console.error("Order update error:", updateError);
          return errorResponse("UPDATE_ERROR", "Failed to update order", 500);
        }

        const { error: profileAddressUpdateError } = await supabaseAdmin
          .from("patients")
          .update(updateData)
          .eq("id", patient!.id)
          .eq("tenant_id", patient!.tenant_id);

        if (profileAddressUpdateError) {
          console.error("Patient shipping address update error:", {
            patientId: patient!.id,
            tenantId: patient!.tenant_id,
            orderId,
            error: profileAddressUpdateError.message,
          });
          return errorResponse(
            "PROFILE_UPDATE_ERROR",
            "Failed to save shipping address to patient profile",
            500,
          );
        }

        // Trigger order-lifecycle to check if status should advance (async, don't wait)
        const lifecycleUrl =
          `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;
        fetch(lifecycleUrl, {
          method: "GET",
          headers: {
            apikey: supabaseAnonKey,
            "Content-Type": "application/json",
          },
        })
          .then((res) => {
            console.info("Order lifecycle triggered after shipping update", {
              orderId,
              status: res.status,
            });
          })
          .catch((err) => {
            console.error("Failed to trigger order lifecycle", {
              orderId,
              error: err.message,
            });
          });

        const updatedStatusInfo = updatedOrder.order_statuses as {
          status_key: string;
        } | null;

        return jsonResponse({
          message: "Order shipping address updated successfully",
          data: {
            id: updatedOrder.id,
            order_number: updatedOrder.order_number,
            status: updatedStatusInfo?.status_key ?? null,
            shipping_address: {
              first_name: updatedOrder.shipping_first_name,
              last_name: updatedOrder.shipping_last_name,
              company: updatedOrder.shipping_company,
              line1: updatedOrder.shipping_address_line1,
              line2: updatedOrder.shipping_address_line2,
              city: updatedOrder.shipping_city,
              state: updatedOrder.shipping_state,
              postal_code: updatedOrder.shipping_postal_code,
              country: updatedOrder.shipping_country,
              instructions: updatedOrder.shipping_instructions,
            },
            updated_at: updatedOrder.updated_at,
          },
        });
      }

      return errorResponse(
        "INVALID_REQUEST",
        "Either 'action' or 'shipping_address' must be provided",
        400,
      );
    }

    // PATCH /orders/:id/address - Update order shipping address only
    if (
      req.method === "PATCH" &&
      path.match(/^\/orders\/[a-f0-9-]+\/address$/)
    ) {
      const orderId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      type AddressInput = {
        first_name?: string;
        last_name?: string;
        company?: string;
        line1?: string;
        line2?: string;
        city?: string;
        state?: string;
        postal_code?: string;
        country?: string;
        instructions?: string;
      };

      let body: AddressInput & {
        shipping_address?: AddressInput;
        billing_address?: AddressInput;
        save_to_profile?: boolean;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      // Get the order with status info
      const { data: order, error: orderError } = await supabase
        .from("orders")
        .select("*, order_statuses(status_key, display_order)")
        .eq("id", orderId)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const statusInfo = asSingle(
        order.order_statuses as
          | { status_key: string; display_order: number | null }
          | { status_key: string; display_order: number | null }[]
          | null,
      );
      const statusKey = statusInfo?.status_key ?? null;

      // Support both flat and nested shipping_address payloads
      const address = body.shipping_address ?? body;
      const billingAddress = body.billing_address;
      const save_to_profile = body.save_to_profile === true;

      // Build update object with only provided fields
      const updateData: Record<string, string | null> = {};
      if (address.first_name !== undefined) {
        updateData.shipping_first_name = address.first_name || null;
      }
      if (address.last_name !== undefined) {
        updateData.shipping_last_name = address.last_name || null;
      }
      if (address.company !== undefined) {
        updateData.shipping_company = address.company || null;
      }
      if (address.line1 !== undefined) {
        updateData.shipping_address_line1 = address.line1 || null;
      }
      if (address.line2 !== undefined) {
        updateData.shipping_address_line2 = address.line2 || null;
      }
      if (address.city !== undefined) {
        updateData.shipping_city = address.city || null;
      }
      if (address.state !== undefined) {
        updateData.shipping_state = address.state || null;
      }
      if (address.postal_code !== undefined) {
        updateData.shipping_postal_code = address.postal_code || null;
      }
      if (address.country !== undefined) {
        updateData.shipping_country = address.country || null;
      }
      if (address.instructions !== undefined) {
        updateData.shipping_instructions = address.instructions || null;
      }
      if (billingAddress) {
        if (billingAddress.first_name !== undefined) {
          updateData.billing_first_name = billingAddress.first_name || null;
        }
        if (billingAddress.last_name !== undefined) {
          updateData.billing_last_name = billingAddress.last_name || null;
        }
        if (billingAddress.company !== undefined) {
          updateData.billing_company = billingAddress.company || null;
        }
        if (billingAddress.line1 !== undefined) {
          updateData.billing_address_line1 = billingAddress.line1 || null;
        }
        if (billingAddress.line2 !== undefined) {
          updateData.billing_address_line2 = billingAddress.line2 || null;
        }
        if (billingAddress.city !== undefined) {
          updateData.billing_city = billingAddress.city || null;
        }
        if (billingAddress.state !== undefined) {
          updateData.billing_state = billingAddress.state || null;
        }
        if (billingAddress.postal_code !== undefined) {
          updateData.billing_postal_code = billingAddress.postal_code || null;
        }
        if (billingAddress.country !== undefined) {
          updateData.billing_country = billingAddress.country || null;
        }
      }

      if (Object.keys(updateData).length === 0) {
        return errorResponse("NO_CHANGES", "No address fields provided", 400);
      }

      const hasShippingUpdate = hasProvidedAddressFields(
        updateData,
        SHIPPING_ADDRESS_COLUMNS,
      );
      const hasBillingUpdate = hasProvidedAddressFields(
        updateData,
        BILLING_ADDRESS_COLUMNS,
      );
      const shippingAddressChanged = hasChangedAddressFields(
        updateData,
        order as Record<string, unknown>,
        SHIPPING_ADDRESS_COLUMNS,
      );
      const billingAddressChanged = hasChangedAddressFields(
        updateData,
        order as Record<string, unknown>,
        BILLING_ADDRESS_COLUMNS,
      );

      let statusThresholds: Awaited<
        ReturnType<typeof fetchAddressUpdateStatusThresholds>
      >;
      try {
        statusThresholds = await fetchAddressUpdateStatusThresholds();
      } catch (error) {
        console.error("Order status threshold fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch order status rules",
          500,
        );
      }

      if (
        hasShippingUpdate &&
        !isStatusBefore(
          {
            status_key: statusKey,
            display_order: statusInfo?.display_order ?? null,
          },
          statusThresholds.providerReviewPending,
        )
      ) {
        return errorResponse(
          "CANNOT_UPDATE_SHIPPING_ADDRESS",
          `Cannot update shipping address for order with status '${
            statusKey ?? "unknown"
          }'. Shipping address can only be updated before provider_review_pending.`,
          400,
        );
      }

      if (
        hasBillingUpdate &&
        !isStatusBefore(
          {
            status_key: statusKey,
            display_order: statusInfo?.display_order ?? null,
          },
          statusThresholds.paymentPending,
        )
      ) {
        return errorResponse(
          "CANNOT_UPDATE_BILLING_ADDRESS",
          `Cannot update billing address for order with status '${
            statusKey ?? "unknown"
          }'. Billing address can only be updated before payment_pending.`,
          400,
        );
      }

      // Validate shipping state if being updated
      if (address.state !== undefined) {
        // Determine the country to check (use new value if provided, else existing order value)
        const countryToCheck = address.country || order.shipping_country ||
          "US";
        const stateValidation = await validateStateAgainstTenant(
          supabaseAdmin,
          patient!.tenant_id,
          address.state,
          countryToCheck,
        );
        if (!stateValidation.valid) {
          return errorResponse(
            "INVALID_SHIPPING_STATE",
            stateValidation.message!,
            400,
          );
        }
      }

      if (billingAddress?.state !== undefined) {
        const countryToCheck = billingAddress.country ||
          order.billing_country || "US";
        const stateValidation = await validateStateAgainstTenant(
          supabaseAdmin,
          patient!.tenant_id,
          billingAddress.state,
          countryToCheck,
        );
        if (!stateValidation.valid) {
          return errorResponse(
            "INVALID_BILLING_STATE",
            stateValidation.message!,
            400,
          );
        }
      }

      const { data: updatedOrder, error: updateError } = await supabaseAdmin
        .from("orders")
        .update(updateData)
        .eq("id", orderId)
        .select("*, order_statuses(status_key, display_order)")
        .single();

      if (updateError) {
        console.error("Order update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update order address",
          500,
        );
      }

      if (save_to_profile) {
        const { error: profileUpdateError } = await supabaseAdmin
          .from("patients")
          .update(updateData)
          .eq("id", patient!.id);

        if (profileUpdateError) {
          console.error("Patient profile update error:", profileUpdateError);
          return errorResponse(
            "PROFILE_UPDATE_ERROR",
            "Failed to update patient profile",
            500,
          );
        }
      }

      if (shippingAddressChanged || billingAddressChanged) {
        const changedAddressLabels = [
          shippingAddressChanged ? "shipping address" : null,
          billingAddressChanged ? "billing address" : null,
        ].filter(Boolean).join(" and ");
        const { error: historyError } = await supabaseAdmin
          .from("order_status_history")
          .insert({
            order_id: orderId,
            status_id: updatedOrder.status_id,
            notes: `Patient updated ${changedAddressLabels}.`,
          });

        if (historyError) {
          console.error("Order address history insert error:", historyError);
          return errorResponse(
            "HISTORY_INSERT_ERROR",
            "Failed to record order address change",
            500,
          );
        }
      }

      const shouldSyncProviderShippingAddress = hasShippingUpdate &&
        shippingAddressChanged &&
        isStatusAfter(
          {
            status_key: statusKey,
            display_order: statusInfo?.display_order ?? null,
          },
          statusThresholds.shippingDetailsRequired,
        );
      let providerShippingSync:
        | { attempted: boolean; provider: string | null }
        | null = null;
      if (shouldSyncProviderShippingAddress) {
        try {
          providerShippingSync = await syncLinkedProviderShippingAddress(
            updatedOrder as Record<string, unknown>,
          );
        } catch (error) {
          console.error("Provider shipping address sync failed", {
            requestId,
            orderId,
            error: error instanceof Error ? error.message : String(error),
          });
          return errorResponse(
            "PROVIDER_SHIPPING_SYNC_ERROR",
            "Failed to sync shipping address with the linked provider platform",
            502,
          );
        }
      }

      // Trigger order-lifecycle to check if status should advance (async, don't wait)
      const lifecycleUrl =
        `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;
      fetch(lifecycleUrl, {
        method: "GET",
        headers: {
          apikey: supabaseAnonKey,
          "Content-Type": "application/json",
        },
      })
        .then((res) => {
          console.info("Order lifecycle triggered after address update", {
            orderId,
            status: res.status,
          });
        })
        .catch((err) => {
          console.error("Failed to trigger order lifecycle", {
            orderId,
            error: err.message,
          });
        });

      const updatedStatusInfo = updatedOrder.order_statuses as {
        status_key: string;
        display_order?: number | null;
      } | null;

      return jsonResponse({
        message: "Order address updated successfully",
        data: {
          id: updatedOrder.id,
          order_number: updatedOrder.order_number,
          status: updatedStatusInfo?.status_key ?? null,
          shipping_address: {
            first_name: updatedOrder.shipping_first_name,
            last_name: updatedOrder.shipping_last_name,
            company: updatedOrder.shipping_company,
            line1: updatedOrder.shipping_address_line1,
            line2: updatedOrder.shipping_address_line2,
            city: updatedOrder.shipping_city,
            state: updatedOrder.shipping_state,
            postal_code: updatedOrder.shipping_postal_code,
            country: updatedOrder.shipping_country,
            instructions: updatedOrder.shipping_instructions,
          },
          billing_address: {
            first_name: updatedOrder.billing_first_name,
            last_name: updatedOrder.billing_last_name,
            company: updatedOrder.billing_company,
            line1: updatedOrder.billing_address_line1,
            line2: updatedOrder.billing_address_line2,
            city: updatedOrder.billing_city,
            state: updatedOrder.billing_state,
            postal_code: updatedOrder.billing_postal_code,
            country: updatedOrder.billing_country,
          },
          provider_shipping_sync: providerShippingSync,
          updated_at: updatedOrder.updated_at,
        },
      });
    }

    // PATCH /admin/orders/:id/status - Update order status from tenant admin UI
    if (
      req.method === "PATCH" &&
      path.match(/^\/admin\/orders\/[a-f0-9-]+\/status$/)
    ) {
      const orderId = path.split("/")[3];

      const { admin, error: authError } = await getAuthenticatedAdmin();
      if (authError) return authError;
      if (!admin) {
        return errorResponse("FORBIDDEN", "Tenant admin access required", 403);
      }

      let body: { status_id?: string; notes?: string } = {};
      try {
        const parsedBody = (await req.json()) as unknown;
        if (
          parsedBody &&
          typeof parsedBody === "object" &&
          !Array.isArray(parsedBody)
        ) {
          body = parsedBody as { status_id?: string; notes?: string };
        }
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const targetStatusId = typeof body.status_id === "string"
        ? body.status_id.trim()
        : "";
      if (!targetStatusId) {
        return errorResponse("MISSING_FIELDS", "status_id is required", 400);
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("id, tenant_id, status_id, order_statuses(status_key)")
        .eq("id", orderId)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const orderTenantId = typeof order.tenant_id === "string"
        ? order.tenant_id
        : null;
      const hasTenantAccess = admin.is_platform_superadmin ||
        (orderTenantId !== null && admin.tenant_ids.includes(orderTenantId));
      if (!hasTenantAccess) {
        return errorResponse(
          "FORBIDDEN",
          "You do not have access to this order",
          403,
        );
      }

      const { data: targetStatus, error: targetStatusError } =
        await supabaseAdmin
          .from("order_statuses")
          .select("id, status_key")
          .eq("id", targetStatusId)
          .eq("is_active", true)
          .maybeSingle();

      if (targetStatusError) {
        console.error("Order status lookup error:", targetStatusError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch target order status",
          500,
        );
      }

      if (!targetStatus) {
        return errorResponse("STATUS_NOT_FOUND", "Order status not found", 404);
      }

      if (
        admin.is_customer_support &&
        !admin.is_tenant_admin &&
        !admin.is_platform_superadmin &&
        !CUSTOMER_SUPPORT_ORDER_STATUS_KEYS.has(targetStatus.status_key)
      ) {
        return errorResponse(
          "FORBIDDEN_STATUS",
          "Customer support cannot set this order status",
          403,
        );
      }

      const currentStatusInfo = asSingle(
        order.order_statuses as
          | { status_key: string }
          | { status_key: string }[]
          | null,
      );
      const previousStatusKey = currentStatusInfo?.status_key ?? null;

      if (order.status_id === targetStatus.id) {
        return jsonResponse({
          message: "Order status unchanged",
          data: {
            id: order.id,
            status_id: order.status_id,
            status: targetStatus.status_key,
          },
        });
      }

      const changedAt = dateTime().toISOString();
      const updateQuery = supabaseAdmin
        .from("orders")
        .update({
          status_id: targetStatus.id,
          status_changed_at: changedAt,
        })
        .eq("id", orderId);

      const guardedUpdateQuery = order.status_id
        ? updateQuery.eq("status_id", order.status_id)
        : updateQuery.is("status_id", null);

      const { data: updatedOrder, error: updateError } =
        await guardedUpdateQuery
          .select(
            "id, status_id, status_changed_at, order_statuses(status_key)",
          )
          .maybeSingle();

      if (updateError) {
        console.error("Order status update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update order status",
          500,
        );
      }

      if (!updatedOrder) {
        return errorResponse(
          "ORDER_STATUS_CHANGED",
          "Order status changed before this update could be applied",
          409,
        );
      }

      if (orderTenantId) {
        notifyRtdhOrderStatusUpdatedAsync({
          supabase: supabaseAdmin,
          requestId,
          tenantId: orderTenantId,
          orderId,
          statusId: targetStatus.id,
          statusKey: targetStatus.status_key,
          previousStatusKey,
          source: "plan-api:admin-order-status-updated",
        });
      } else {
        console.warn(
          "RTDH admin status update notification skipped: order tenant_id is missing",
          { requestId, orderId },
        );
      }

      return jsonResponse({
        message: "Order status updated successfully",
        data: {
          id: updatedOrder.id,
          status_id: updatedOrder.status_id,
          status: targetStatus.status_key,
          status_changed_at: updatedOrder.status_changed_at,
        },
      });
    }

    // PATCH /admin/orders/:id/notes - Update internal notes from tenant admin UI
    if (
      req.method === "PATCH" &&
      path.match(/^\/admin\/orders\/[a-f0-9-]+\/notes$/)
    ) {
      const orderId = path.split("/")[3];

      const { admin, error: authError } = await getAuthenticatedAdmin();
      if (authError) return authError;
      if (!admin) {
        return errorResponse("FORBIDDEN", "Tenant admin access required", 403);
      }

      let body: { internal_notes?: string | null } = {};
      try {
        const parsedBody = (await req.json()) as unknown;
        if (
          parsedBody &&
          typeof parsedBody === "object" &&
          !Array.isArray(parsedBody)
        ) {
          body = parsedBody as { internal_notes?: string | null };
        }
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      if (
        body.internal_notes !== null &&
        body.internal_notes !== undefined &&
        typeof body.internal_notes !== "string"
      ) {
        return errorResponse(
          "INVALID_NOTES",
          "internal_notes must be a string or null",
          400,
        );
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("id, tenant_id, internal_notes")
        .eq("id", orderId)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const orderTenantId = typeof order.tenant_id === "string"
        ? order.tenant_id
        : null;
      const hasTenantAccess = admin.is_platform_superadmin ||
        (orderTenantId !== null && admin.tenant_ids.includes(orderTenantId));
      if (!hasTenantAccess) {
        return errorResponse(
          "FORBIDDEN",
          "You do not have access to this order",
          403,
        );
      }

      const nextInternalNotes = body.internal_notes?.trim() || null;

      const { data: updatedOrder, error: updateError } = await supabaseAdmin
        .from("orders")
        .update({ internal_notes: nextInternalNotes })
        .eq("id", orderId)
        .select("id, internal_notes, updated_at")
        .single();

      if (updateError) {
        console.error("Order notes update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update internal notes",
          500,
        );
      }

      return jsonResponse({
        message: "Internal notes updated successfully",
        data: {
          id: updatedOrder.id,
          internal_notes: updatedOrder.internal_notes,
          updated_at: updatedOrder.updated_at,
        },
      });
    }

    // PATCH /admin/orders/:id/address - Update order shipping and billing address from tenant admin UI
    if (
      req.method === "PATCH" &&
      path.match(/^\/admin\/orders\/[a-f0-9-]+\/address$/)
    ) {
      const orderId = path.split("/")[3];

      const { admin, error: authError } = await getAuthenticatedAdmin();
      if (authError) return authError;
      if (!admin) {
        return errorResponse("FORBIDDEN", "Tenant admin access required", 403);
      }

      type AddressInput = {
        first_name?: string;
        last_name?: string;
        company?: string;
        line1?: string;
        line2?: string;
        city?: string;
        state?: string;
        postal_code?: string;
        country?: string;
        instructions?: string;
      };

      let body: AddressInput & {
        shipping_address?: AddressInput;
        billing_address?: AddressInput;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const { data: order, error: orderError } = await supabaseAdmin
        .from("orders")
        .select("*, order_statuses(status_key, display_order)")
        .eq("id", orderId)
        .maybeSingle();

      if (orderError) {
        console.error("Order fetch error:", orderError);
        return errorResponse("FETCH_ERROR", "Failed to fetch order", 500);
      }

      if (!order) {
        return errorResponse("ORDER_NOT_FOUND", "Order not found", 404);
      }

      const orderTenantId = typeof order.tenant_id === "string"
        ? order.tenant_id
        : null;
      const hasTenantAccess = admin.is_platform_superadmin ||
        (orderTenantId !== null && admin.tenant_ids.includes(orderTenantId));
      if (!hasTenantAccess) {
        return errorResponse(
          "FORBIDDEN",
          "You do not have access to this order",
          403,
        );
      }

      const statusInfo = asSingle(
        order.order_statuses as
          | { status_key: string; display_order: number | null }
          | { status_key: string; display_order: number | null }[]
          | null,
      );
      const statusKey = statusInfo?.status_key ?? null;

      const address = body.shipping_address ?? body;
      const billingAddress = body.billing_address;

      const updateData: Record<string, string | null> = {};
      if (address.first_name !== undefined) {
        updateData.shipping_first_name = address.first_name || null;
      }
      if (address.last_name !== undefined) {
        updateData.shipping_last_name = address.last_name || null;
      }
      if (address.company !== undefined) {
        updateData.shipping_company = address.company || null;
      }
      if (address.line1 !== undefined) {
        updateData.shipping_address_line1 = address.line1 || null;
      }
      if (address.line2 !== undefined) {
        updateData.shipping_address_line2 = address.line2 || null;
      }
      if (address.city !== undefined) {
        updateData.shipping_city = address.city || null;
      }
      if (address.state !== undefined) {
        updateData.shipping_state = address.state || null;
      }
      if (address.postal_code !== undefined) {
        updateData.shipping_postal_code = address.postal_code || null;
      }
      if (address.country !== undefined) {
        updateData.shipping_country = address.country || null;
      }
      if (address.instructions !== undefined) {
        updateData.shipping_instructions = address.instructions || null;
      }
      if (billingAddress) {
        if (billingAddress.first_name !== undefined) {
          updateData.billing_first_name = billingAddress.first_name || null;
        }
        if (billingAddress.last_name !== undefined) {
          updateData.billing_last_name = billingAddress.last_name || null;
        }
        if (billingAddress.company !== undefined) {
          updateData.billing_company = billingAddress.company || null;
        }
        if (billingAddress.line1 !== undefined) {
          updateData.billing_address_line1 = billingAddress.line1 || null;
        }
        if (billingAddress.line2 !== undefined) {
          updateData.billing_address_line2 = billingAddress.line2 || null;
        }
        if (billingAddress.city !== undefined) {
          updateData.billing_city = billingAddress.city || null;
        }
        if (billingAddress.state !== undefined) {
          updateData.billing_state = billingAddress.state || null;
        }
        if (billingAddress.postal_code !== undefined) {
          updateData.billing_postal_code = billingAddress.postal_code || null;
        }
        if (billingAddress.country !== undefined) {
          updateData.billing_country = billingAddress.country || null;
        }
      }

      if (Object.keys(updateData).length === 0) {
        return errorResponse("NO_CHANGES", "No address fields provided", 400);
      }

      const hasShippingUpdate = hasProvidedAddressFields(
        updateData,
        SHIPPING_ADDRESS_COLUMNS,
      );
      const hasBillingUpdate = hasProvidedAddressFields(
        updateData,
        BILLING_ADDRESS_COLUMNS,
      );
      const shippingAddressChanged = hasChangedAddressFields(
        updateData,
        order as Record<string, unknown>,
        SHIPPING_ADDRESS_COLUMNS,
      );
      const billingAddressChanged = hasChangedAddressFields(
        updateData,
        order as Record<string, unknown>,
        BILLING_ADDRESS_COLUMNS,
      );

      let statusThresholds: Awaited<
        ReturnType<typeof fetchAddressUpdateStatusThresholds>
      >;
      try {
        statusThresholds = await fetchAddressUpdateStatusThresholds();
      } catch (error) {
        console.error("Order status threshold fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch order status rules",
          500,
        );
      }

      if (
        hasShippingUpdate &&
        !isStatusBefore(
          {
            status_key: statusKey,
            display_order: statusInfo?.display_order ?? null,
          },
          statusThresholds.providerReviewPending,
        )
      ) {
        return errorResponse(
          "CANNOT_UPDATE_SHIPPING_ADDRESS",
          `Cannot update shipping address for order with status '${
            statusKey ?? "unknown"
          }'. Shipping address can only be updated before provider_review_pending.`,
          400,
        );
      }

      if (
        hasBillingUpdate &&
        !isStatusBefore(
          {
            status_key: statusKey,
            display_order: statusInfo?.display_order ?? null,
          },
          statusThresholds.paymentPending,
        )
      ) {
        return errorResponse(
          "CANNOT_UPDATE_BILLING_ADDRESS",
          `Cannot update billing address for order with status '${
            statusKey ?? "unknown"
          }'. Billing address can only be updated before payment_pending.`,
          400,
        );
      }

      if (address.state !== undefined) {
        const countryToCheck = address.country || order.shipping_country ||
          "US";
        const stateValidation = await validateStateAgainstTenant(
          supabaseAdmin,
          order.tenant_id,
          address.state,
          countryToCheck,
        );
        if (!stateValidation.valid) {
          return errorResponse(
            "INVALID_SHIPPING_STATE",
            stateValidation.message!,
            400,
          );
        }
      }

      if (billingAddress?.state !== undefined) {
        const countryToCheck = billingAddress.country ||
          order.billing_country || "US";
        const stateValidation = await validateStateAgainstTenant(
          supabaseAdmin,
          order.tenant_id,
          billingAddress.state,
          countryToCheck,
        );
        if (!stateValidation.valid) {
          return errorResponse(
            "INVALID_BILLING_STATE",
            stateValidation.message!,
            400,
          );
        }
      }

      const { data: updatedOrder, error: updateError } = await supabaseAdmin
        .from("orders")
        .update(updateData)
        .eq("id", orderId)
        .select("*, order_statuses(status_key, display_order)")
        .single();

      if (updateError) {
        console.error("Order update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update order address",
          500,
        );
      }

      if (shippingAddressChanged || billingAddressChanged) {
        const changedAddressLabels = [
          shippingAddressChanged ? "shipping address" : null,
          billingAddressChanged ? "billing address" : null,
        ].filter(Boolean).join(" and ");
        const { error: historyError } = await supabaseAdmin
          .from("order_status_history")
          .insert({
            order_id: orderId,
            status_id: updatedOrder.status_id,
            changed_by: admin.id,
            changed_by_email: admin.email,
            notes: `Tenant admin updated ${changedAddressLabels}.`,
          });

        if (historyError) {
          console.error("Order address history insert error:", historyError);
          return errorResponse(
            "HISTORY_INSERT_ERROR",
            "Failed to record order address change",
            500,
          );
        }
      }

      const shouldSyncProviderShippingAddress = hasShippingUpdate &&
        shippingAddressChanged &&
        isStatusAfter(
          {
            status_key: statusKey,
            display_order: statusInfo?.display_order ?? null,
          },
          statusThresholds.shippingDetailsRequired,
        );
      let providerShippingSync:
        | { attempted: boolean; provider: string | null }
        | null = null;
      if (shouldSyncProviderShippingAddress) {
        try {
          providerShippingSync = await syncLinkedProviderShippingAddress(
            updatedOrder as Record<string, unknown>,
          );
        } catch (error) {
          console.error("Provider shipping address sync failed", {
            requestId,
            orderId,
            error: error instanceof Error ? error.message : String(error),
          });
          return errorResponse(
            "PROVIDER_SHIPPING_SYNC_ERROR",
            "Failed to sync shipping address with the linked provider platform",
            502,
          );
        }
      }

      const lifecycleUrl =
        `${supabaseUrl}/functions/v1/order-lifecycle?orderId=${orderId}`;
      fetch(lifecycleUrl, {
        method: "GET",
        headers: {
          apikey: supabaseAnonKey,
          "Content-Type": "application/json",
        },
      })
        .then((res) => {
          console.info("Order lifecycle triggered after admin address update", {
            orderId,
            status: res.status,
          });
        })
        .catch((err) => {
          console.error("Failed to trigger order lifecycle", {
            orderId,
            error: err.message,
          });
        });

      const updatedStatusInfo = updatedOrder.order_statuses as {
        status_key: string;
        display_order?: number | null;
      } | null;

      return jsonResponse({
        message: "Order address updated successfully",
        data: {
          id: updatedOrder.id,
          order_number: updatedOrder.order_number,
          status: updatedStatusInfo?.status_key ?? null,
          shipping_address: {
            first_name: updatedOrder.shipping_first_name,
            last_name: updatedOrder.shipping_last_name,
            company: updatedOrder.shipping_company,
            line1: updatedOrder.shipping_address_line1,
            line2: updatedOrder.shipping_address_line2,
            city: updatedOrder.shipping_city,
            state: updatedOrder.shipping_state,
            postal_code: updatedOrder.shipping_postal_code,
            country: updatedOrder.shipping_country,
            instructions: updatedOrder.shipping_instructions,
          },
          billing_address: {
            first_name: updatedOrder.billing_first_name,
            last_name: updatedOrder.billing_last_name,
            company: updatedOrder.billing_company,
            line1: updatedOrder.billing_address_line1,
            line2: updatedOrder.billing_address_line2,
            city: updatedOrder.billing_city,
            state: updatedOrder.billing_state,
            postal_code: updatedOrder.billing_postal_code,
            country: updatedOrder.billing_country,
          },
          provider_shipping_sync: providerShippingSync,
          updated_at: updatedOrder.updated_at,
        },
      });
    }

    // POST /checkout/preflight
    //
    // Runs the guest-safe eligibility + duplicate-plan guards for an email WITHOUT
    // creating anything. The UI calls this immediately before confirming the card,
    // so a blocked buyer is stopped BEFORE the money moves rather than being
    // charged and refunded.
    //
    // Also reports account_exists so the UI can offer a sign-in prompt ("we found
    // an existing plan for this email — log in to manage it") instead of a
    // dead-end error.
    if (req.method === "POST" && path === "/checkout/preflight") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      // Guest-safe: this route runs BEFORE login, so the tenant must be resolved
      // with the admin client. getActiveTenant() reads through the anon client and
      // throws under RLS on an anonymous request — which is exactly what made
      // pre-flight return an opaque INTERNAL_ERROR and hang checkout on
      // "Authorizing…", because the client treats a failed pre-flight as
      // "do not charge".
      const tenant = await getActiveTenantAsAdmin("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: { email?: string; product_id?: string; order_id?: string };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_BODY", "Invalid JSON body", 400);
      }

      const { patient } = await getAuthenticatedPatient().catch(() => ({
        patient: null,
      }));
      const buyerEmail = (patient?.email ?? body.email ?? "").trim()
        .toLowerCase();
      const productId = (body.product_id ?? "").trim();

      if (!buyerEmail || !buyerEmail.includes("@")) {
        return errorResponse(
          "EMAIL_REQUIRED",
          "A valid email is required to start checkout",
          400,
        );
      }
      if (!productId) {
        return errorResponse("PRODUCT_REQUIRED", "product_id is required", 400);
      }

      let eligibility: CheckoutEligibilityResult;
      let accountExists = false;
      try {
        eligibility = await checkCheckoutEligibility({
          supabaseAdmin,
          tenantId: tenant.id,
          productId,
          buyerEmail,
          resumeOrderId: body.order_id ?? null,
        });

        // Does an account already exist for this email? Drives the sign-in prompt.
        const { data: existingPatients } = await supabaseAdmin
          .from("patients")
          .select("id")
          .eq("tenant_id", tenant.id)
          .ilike("email", buyerEmail)
          .limit(1);
        accountExists = (existingPatients ?? []).length > 0;
      } catch (error) {
        // A blank INTERNAL_ERROR here is worse than useless: the client treats a
        // failed pre-flight as "do not charge", so the buyer is left on a spinning
        // Authorize button with no payment and no explanation. Surface the cause.
        console.error("Pre-flight failed", {
          tenantId: tenant.id,
          productId,
          error: error instanceof Error ? error.message : String(error),
        });
        return errorResponse(
          "PREFLIGHT_FAILED",
          error instanceof Error ? error.message : "Pre-flight check failed",
          500,
        );
      }

      if (!eligibility.ok) {
        return jsonResponse({
          message: "Checkout blocked",
          data: {
            ok: false,
            code: eligibility.code,
            reason: eligibility.message,
            account_exists: accountExists,
            // The buyer already has a plan/order for this — signing in is the
            // useful next step, not an error toast.
            should_sign_in: accountExists,
          },
        });
      }

      return jsonResponse({
        message: "Checkout allowed",
        data: { ok: true, account_exists: accountExists },
      });
    }

    // POST /checkout/authorize
    //
    // PAYMENT-FIRST checkout, step 1 of 2.
    //
    // The legacy embedded flow (POST /orders/{id}/payment-intent, below) creates
    // the ACCOUNT and the ORDER *before* the card is charged, so a failed payment
    // leaves the buyer signed in with a real, unpaid order. This route flips that
    // around: it authorizes the money FIRST and creates NOTHING in our database —
    // no patient, no order, no subscription. Only once Stripe reports the payment
    // authorized does POST /checkout/finalize (below) mint the order.
    //
    // Guest-callable (anon key only, no JWT).
    // POST /checkout/quote
    //
    // Price a product with an optional promo code. Creates NOTHING — no account,
    // no order, no Stripe intent.
    //
    // Applying a coupon used to go through the order-creating payment-intent
    // route, which meant simply TYPING a promo code minted an account and an
    // unpaid order — the very phantom-order bug payment-first exists to prevent.
    // Re-pricing must be a read.
    if (req.method === "POST" && path === "/checkout/quote") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const quoteTenant = await getActiveTenantAsAdmin("id, name");
      if (!quoteTenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let quoteBody: { product_id?: string; promotion_code?: string } = {};
      try {
        quoteBody = await req.json();
      } catch {
        return errorResponse("INVALID_BODY", "Invalid JSON body", 400);
      }

      const quoteProductId = (quoteBody.product_id ?? "").trim();
      if (!quoteProductId) {
        return errorResponse("PRODUCT_REQUIRED", "product_id is required", 400);
      }

      const { data: quoteProduct } = await supabaseAdmin
        .from("products")
        .select("id, price_cents, payment_type")
        .eq("id", quoteProductId)
        .eq("tenant_id", quoteTenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (!quoteProduct) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const quoteStripe = await resolveTenantStripe(quoteTenant.id);
      if ("error" in quoteStripe) {
        return errorResponse(
          quoteStripe.error.code,
          quoteStripe.error.message,
          quoteStripe.error.status,
        );
      }

      const quote = await resolveCheckoutCoupon({
        stripeSecretKey: quoteStripe.stripeSecretKey,
        basePriceCents: quoteProduct.price_cents as number,
        promotionCode: quoteBody.promotion_code,
      });

      return jsonResponse({
        message: "Quote",
        data: {
          amount_cents: quote.amountCents,
          discount_cents: quote.discountCents,
          coupon_code: quote.appliedCouponCode,
          // A 100%-off coupon takes the total to $0. Stripe rejects a $0
          // PaymentIntent, so that checkout has to go down the SetupIntent path
          // (a card is still needed for renewals) instead of /checkout/authorize.
          requires_payment: quote.amountCents > 0,
          currency: "usd",
        },
      });
    }

    // POST /checkout/customer-session
    //
    // Returning-buyer support: mint a Stripe CustomerSession for the
    // AUTHENTICATED patient so the checkout PaymentElement can show their
    // previously saved cards and offer saving new ones. Read-only from our
    // side — creates nothing in our database (the Stripe Customer is
    // created/reused via the same ensureStripeCustomerForPatient cache the
    // billing portal uses, patients.metadata.stripe_customer_id).
    //
    // The client secret expires after ~30 minutes; it is only consumed at
    // Elements mount to RENDER saved payment methods — the payment itself
    // confirms against the PaymentIntent from /checkout/authorize, whose
    // customer MUST match (authorize reuses the same helper when the request
    // is authenticated).
    if (req.method === "POST" && path === "/checkout/customer-session") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }
      const sessionTenant = await getActiveTenantAsAdmin("id, name");
      if (!sessionTenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      const { patient: sessionPatient, error: sessionAuthError } =
        await getAuthenticatedPatient();
      if (sessionAuthError) return sessionAuthError;
      // getAuthenticatedPatient resolves by auth_user_id only — refuse to mint
      // a session against this tenant's Stripe account for a patient row that
      // belongs to a different tenant.
      if (sessionPatient!.tenant_id !== sessionTenant.id) {
        return errorResponse("NOT_FOUND", "Patient profile not found", 404);
      }

      const sessionStripeCreds = await resolveTenantStripe(sessionTenant.id);
      if ("error" in sessionStripeCreds) {
        return errorResponse(
          sessionStripeCreds.error.code,
          sessionStripeCreds.error.message,
          sessionStripeCreds.error.status,
        );
      }

      const sessionCustomerId = await ensureStripeCustomerForPatient({
        patientId: sessionPatient!.id,
        tenantId: sessionTenant.id,
        stripeSecretKey: sessionStripeCreds.stripeSecretKey,
        email: sessionPatient!.email,
        fullName: [sessionPatient!.first_name, sessionPatient!.last_name]
          .filter(Boolean)
          .join(" ") || null,
      });
      if (!sessionCustomerId) {
        return errorResponse(
          "STRIPE_ERROR",
          "Could not prepare saved payment methods",
          500,
        );
      }

      const customerSessionParams = new URLSearchParams();
      customerSessionParams.append("customer", sessionCustomerId);
      customerSessionParams.append(
        "components[payment_element][enabled]",
        "true",
      );
      customerSessionParams.append(
        "components[payment_element][features][payment_method_redisplay]",
        "enabled",
      );
      // Cards saved by the legacy setup_future_usage path carry
      // allow_redisplay=unspecified — without that filter nothing would show.
      for (const filter of ["always", "limited", "unspecified"]) {
        customerSessionParams.append(
          "components[payment_element][features][payment_method_allow_redisplay_filters][]",
          filter,
        );
      }
      customerSessionParams.append(
        "components[payment_element][features][payment_method_save]",
        "enabled",
      );
      customerSessionParams.append(
        "components[payment_element][features][payment_method_save_usage]",
        "off_session",
      );
      customerSessionParams.append(
        "components[payment_element][features][payment_method_remove]",
        "disabled",
      );

      const customerSessionRes = await fetch(
        "https://api.stripe.com/v1/customer_sessions",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${sessionStripeCreds.stripeSecretKey}`,
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: customerSessionParams.toString(),
        },
      );

      if (!customerSessionRes.ok) {
        const customerSessionError = await customerSessionRes.text();
        console.error("Checkout customer-session: Stripe error", {
          requestId,
          tenantId: sessionTenant.id,
          patientId: sessionPatient!.id,
          status: customerSessionRes.status,
          error: customerSessionError,
        });
        return errorResponse(
          "STRIPE_ERROR",
          "Could not prepare saved payment methods",
          500,
        );
      }

      const customerSession = (await customerSessionRes.json()) as {
        client_secret?: string;
      };
      if (!customerSession.client_secret) {
        return errorResponse(
          "STRIPE_ERROR",
          "Could not prepare saved payment methods",
          500,
        );
      }

      return jsonResponse({
        message: "Customer session created",
        data: {
          customer_session_client_secret: customerSession.client_secret,
          customer_id: sessionCustomerId,
        },
      });
    }

    if (req.method === "POST" && path === "/checkout/authorize") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      // Guest-safe tenant lookup: getActiveTenant() reads through the anon client
      // and THROWS under RLS on an anonymous request.
      const tenant = await getActiveTenantAsAdmin("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        product_id?: string;
        email?: string;
        full_name?: string;
        phone?: string;
        promotion_code?: string;
      } = {};
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_BODY", "Invalid JSON body", 400);
      }

      // OPTIONAL auth (returning buyers): when a signed-in patient makes the
      // request, their account email wins over the form email and their cached
      // Stripe Customer is reused — required for saved payment methods, whose
      // CustomerSession customer must match the PaymentIntent's. Guests (anon
      // key) resolve to null and take exactly the pre-existing path.
      const { patient: rawAuthorizePatient } = await getAuthenticatedPatient()
        .catch(() => ({ patient: null }));
      const authorizePatient =
        rawAuthorizePatient && rawAuthorizePatient.tenant_id === tenant.id
          ? rawAuthorizePatient
          : null;

      const buyerEmail = (authorizePatient?.email ?? body.email ?? "")
        .trim()
        .toLowerCase();
      if (!buyerEmail || !buyerEmail.includes("@")) {
        return errorResponse(
          "EMAIL_REQUIRED",
          "A valid email is required to start checkout",
          400,
        );
      }

      const productId = (body.product_id ?? "").trim();
      if (!productId) {
        return errorResponse("PRODUCT_REQUIRED", "product_id is required", 400);
      }

      const { data: product, error: productError } = await supabaseAdmin
        .from("products")
        .select(
          "id, name, description, terms_and_conditions_html, price_cents, payment_type, image_url, metadata",
        )
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) {
        console.error("Product fetch error:", productError);
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }
      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      // Same guards as the legacy route: a conflicting active medication (or a
      // duplicate in-progress plan) must block the purchase BEFORE the money
      // moves, even when the buyer is not authenticated.
      // These are the SAME guards /checkout/preflight runs. The client used to call
      // preflight first and then authorize, which meant paying for the tenant
      // lookup, the product lookup and the eligibility queries TWICE — about two
      // seconds of dead time between the buyer pressing Purchase and anything
      // happening. Authorize is the only gate that matters (it is what stands
      // between the buyer and the charge), so it reports everything preflight did
      // and the client can skip that hop.
      const authorizeEligibility = await checkCheckoutEligibility({
        supabaseAdmin,
        tenantId: tenant.id,
        productId: product.id,
        buyerEmail,
      });
      if (!authorizeEligibility.ok) {
        // Does an account already exist for this email? If so, "sign in to manage
        // the plan you already have" is the useful next step, not an error.
        const { data: blockedExisting } = await supabaseAdmin
          .from("patients")
          .select("id")
          .eq("tenant_id", tenant.id)
          .ilike("email", buyerEmail)
          .limit(1);
        const blockedAccountExists = (blockedExisting ?? []).length > 0;

        return jsonResponse({
          message: "Checkout blocked",
          data: {
            ok: false,
            code: authorizeEligibility.code,
            reason: authorizeEligibility.message,
            account_exists: blockedAccountExists,
            // An authenticated buyer IS signed in — a "sign in to manage your
            // plan" CTA would be nonsense; the reason alone is the answer.
            should_sign_in: authorizePatient ? false : blockedAccountExists,
            already_signed_in: Boolean(authorizePatient),
            requires_payment: false,
            client_secret: null,
            payment_intent_id: null,
          },
        });
      }

      const authorizeStripeCreds = await resolveTenantStripe(tenant.id);
      if ("error" in authorizeStripeCreds) {
        return errorResponse(
          authorizeStripeCreds.error.code,
          authorizeStripeCreds.error.message,
          authorizeStripeCreds.error.status,
        );
      }
      const { stripeSecretKey: authorizeSecretKey } = authorizeStripeCreds;

      const authorizeCoupon = await resolveCheckoutCoupon({
        stripeSecretKey: authorizeSecretKey,
        basePriceCents: product.price_cents as number,
        promotionCode: body.promotion_code,
      });
      const authorizeAmountCents = authorizeCoupon.amountCents;

      const authorizeProductPayload = {
        id: product.id,
        name: product.name,
        description: product.description,
        terms_and_conditions_html: product.terms_and_conditions_html,
        price_cents: product.price_cents,
        price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
        payment_type: product.payment_type,
      };

      // Stripe rejects a $0 PaymentIntent. Rather than re-implement the
      // SetupIntent / no-charge branches here, report requires_payment:false and
      // let the client fall back to the EXISTING payment-intent route, which
      // already handles both $0 cases correctly.
      if (authorizeAmountCents <= 0) {
        return jsonResponse({
          message: "No payment required for this amount",
          data: {
            client_secret: null,
            payment_intent_id: null,
            requires_payment: false,
            amount_cents: 0,
            discount_cents: authorizeCoupon.discountCents,
            coupon_code: authorizeCoupon.appliedCouponCode,
            currency: "usd",
            product: authorizeProductPayload,
          },
        });
      }

      // Resolve the Stripe Customer.
      //
      // AUTHENTICATED buyer: reuse the patient's cached customer via
      // ensureStripeCustomerForPatient (patients.metadata.stripe_customer_id) —
      // the SAME source /checkout/customer-session uses, so the PaymentIntent's
      // customer matches the CustomerSession's and saved-card confirms work.
      // On failure, fall through to the guest path below (checkout still works;
      // the buyer just re-enters their card).
      //
      // GUEST: create a Stripe Customer straight from the email. We deliberately
      // do NOT use ensureStripeCustomerForPatient — that needs a patient row,
      // and the whole point of payment-first is that no patient exists yet.
      // /checkout/finalize writes this customer id onto patients.metadata
      // afterwards, so renewals/billing-portal keep working.
      let authorizeCustomerId = "";
      if (authorizePatient) {
        authorizeCustomerId = (await ensureStripeCustomerForPatient({
          patientId: authorizePatient.id,
          tenantId: tenant.id,
          stripeSecretKey: authorizeSecretKey,
          email: buyerEmail,
          fullName: body.full_name ?? null,
          phone: body.phone ?? null,
        })) ?? "";
      }

      if (!authorizeCustomerId) {
        const authorizeCustomerParams = new URLSearchParams();
        authorizeCustomerParams.append("email", buyerEmail);
        if (body.full_name?.trim()) {
          authorizeCustomerParams.append("name", body.full_name.trim());
        }
        if (body.phone?.trim()) {
          authorizeCustomerParams.append("phone", body.phone.trim());
        }
        authorizeCustomerParams.append("metadata[tenant_id]", tenant.id);
        authorizeCustomerParams.append(
          "metadata[checkout_flow]",
          "payment_first",
        );

        const authorizeCustomerRes = await fetch(
          "https://api.stripe.com/v1/customers",
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${authorizeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: authorizeCustomerParams.toString(),
          },
        );

        if (!authorizeCustomerRes.ok) {
          const customerError = await authorizeCustomerRes.text();
          console.error("Payment-first: Stripe customer creation failed", {
            requestId,
            tenantId: tenant.id,
            buyerEmail,
            status: authorizeCustomerRes.status,
            error: customerError,
          });
          return errorResponse(
            "STRIPE_ERROR",
            "Could not start checkout. Please try again.",
            500,
          );
        }

        const authorizeCustomer = (await authorizeCustomerRes.json()) as {
          id?: string;
        };
        authorizeCustomerId = typeof authorizeCustomer.id === "string"
          ? authorizeCustomer.id.trim()
          : "";
      }
      if (!authorizeCustomerId) {
        return errorResponse(
          "STRIPE_ERROR",
          "Could not start checkout. Please try again.",
          500,
        );
      }

      // The PaymentIntent mirrors the legacy route's params exactly (manual
      // capture, automatic payment methods without redirects, off_session reuse
      // for subscriptions). The metadata carries everything /checkout/finalize
      // needs to mint the order once the payment is authorized — there is no
      // order id or patient id to reference yet.
      const authorizeIsSubscription = product.payment_type === "subscription";
      const authorizePiParams = new URLSearchParams();
      authorizePiParams.append("amount", String(authorizeAmountCents));
      authorizePiParams.append("currency", "usd");
      authorizePiParams.append("capture_method", "manual");
      authorizePiParams.append("automatic_payment_methods[enabled]", "true");
      authorizePiParams.append(
        "automatic_payment_methods[allow_redirects]",
        "never",
      );
      authorizePiParams.append("receipt_email", buyerEmail);
      authorizePiParams.append("customer", authorizeCustomerId);
      if (authorizeIsSubscription) {
        authorizePiParams.append("setup_future_usage", "off_session");
      }
      authorizePiParams.append("metadata[tenant_id]", tenant.id);
      authorizePiParams.append("metadata[product_id]", product.id);
      authorizePiParams.append("metadata[customer_email]", buyerEmail);
      authorizePiParams.append("metadata[checkout_flow]", "payment_first");
      if (authorizePatient) {
        authorizePiParams.append("metadata[patient_id]", authorizePatient.id);
      }
      authorizePiParams.append(
        "metadata[amount_cents]",
        String(authorizeAmountCents),
      );
      authorizePiParams.append(
        "metadata[discount_cents]",
        String(authorizeCoupon.discountCents),
      );
      if (body.full_name?.trim()) {
        authorizePiParams.append("metadata[full_name]", body.full_name.trim());
      }
      if (body.phone?.trim()) {
        authorizePiParams.append("metadata[phone]", body.phone.trim());
      }
      if (authorizeCoupon.appliedCouponCode) {
        authorizePiParams.append(
          "metadata[coupon_code]",
          authorizeCoupon.appliedCouponCode,
        );
      }

      const authorizePiRes = await fetch(
        "https://api.stripe.com/v1/payment_intents",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${authorizeSecretKey}`,
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: authorizePiParams.toString(),
        },
      );

      if (!authorizePiRes.ok) {
        const stripeError = await authorizePiRes.json();
        console.error(
          "Payment-first: Stripe PaymentIntent error:",
          stripeError,
        );
        return errorResponse(
          "STRIPE_ERROR",
          stripeError.error?.message || "Failed to create payment intent",
          500,
        );
      }

      const authorizePaymentIntent = await authorizePiRes.json();

      console.info("Payment-first: PaymentIntent created (no order yet)", {
        requestId,
        paymentIntentId: authorizePaymentIntent.id,
        productId: product.id,
        buyerEmail,
        amountCents: authorizeAmountCents,
        couponCode: authorizeCoupon.appliedCouponCode,
      });

      return jsonResponse({
        message: "Payment intent created",
        data: {
          client_secret: authorizePaymentIntent.client_secret,
          payment_intent_id: authorizePaymentIntent.id,
          requires_payment: true,
          amount_cents: authorizeAmountCents,
          discount_cents: authorizeCoupon.discountCents,
          coupon_code: authorizeCoupon.appliedCouponCode,
          currency: "usd",
          product: authorizeProductPayload,
        },
      });
    }

    // POST /checkout/finalize
    //
    // PAYMENT-FIRST checkout, step 2 of 2.
    //
    // Called AFTER the buyer's card has been authorized by the PaymentIntent that
    // POST /checkout/authorize returned. This is where — and only where — the
    // order is created. The security gate is that we RETRIEVE the PaymentIntent
    // from Stripe with the tenant's secret key and check its status ourselves; the
    // client is never trusted to say "payment succeeded".
    //
    // Guest-callable (anon key only, no JWT).
    if (req.method === "POST" && path === "/checkout/finalize") {
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenantAsAdmin("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      let body: {
        payment_intent_id?: string;
        product_id?: string;
        email?: string;
        full_name?: string;
        phone?: string;
        shipping_address_line1?: string;
        shipping_address_line2?: string;
        shipping_city?: string;
        shipping_state?: string;
        shipping_postal_code?: string;
        shipping_country?: string;
        promotion_code?: string;
        subscribe_to_email_and_sms_marketing?: boolean;
      } = {};
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_BODY", "Invalid JSON body", 400);
      }

      const paymentIntentId = (body.payment_intent_id ?? "").trim();
      if (!paymentIntentId) {
        return errorResponse(
          "PAYMENT_INTENT_REQUIRED",
          "payment_intent_id is required",
          400,
        );
      }

      const finalizeProductId = (body.product_id ?? "").trim();
      if (!finalizeProductId) {
        return errorResponse("PRODUCT_REQUIRED", "product_id is required", 400);
      }

      // The buyer may already be signed in by the time they finalize (the
      // post-payment account step signs them in), so prefer the authenticated
      // patient's email exactly like the legacy route does.
      const { patient: finalizePatient } = await getAuthenticatedPatient()
        .catch(
          () => ({ patient: null }),
        );
      const finalizeEmail = (finalizePatient?.email ?? body.email ?? "").trim()
        .toLowerCase();
      if (!finalizeEmail || !finalizeEmail.includes("@")) {
        return errorResponse(
          "EMAIL_REQUIRED",
          "A valid email is required to complete checkout",
          400,
        );
      }

      // The product row and the tenant's Stripe credentials do not depend on each
      // other. Running them back-to-back put two serial round-trips on the path
      // between the buyer's card clearing and their order existing.
      const [
        { data: finalizeProduct, error: finalizeProductError },
        finalizeStripeCreds,
      ] = await Promise.all([
        supabaseAdmin
          .from("products")
          .select(
            "id, name, description, terms_and_conditions_html, price_cents, payment_type, image_url, metadata",
          )
          .eq("id", finalizeProductId)
          .eq("tenant_id", tenant.id)
          .eq("is_enabled", true)
          .maybeSingle(),
        resolveTenantStripe(tenant.id),
      ]);

      if (finalizeProductError) {
        console.error("Product fetch error:", finalizeProductError);
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }
      if (!finalizeProduct) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      if ("error" in finalizeStripeCreds) {
        return errorResponse(
          finalizeStripeCreds.error.code,
          finalizeStripeCreds.error.message,
          finalizeStripeCreds.error.status,
        );
      }
      const {
        stripeSecretKey: finalizeSecretKey,
        stripePaymentProviderId: finalizeProviderId,
      } = finalizeStripeCreds;

      // --- SECURITY GATE: ask Stripe, never the client ----------------------
      const finalizePiRes = await fetch(
        `https://api.stripe.com/v1/payment_intents/${
          encodeURIComponent(paymentIntentId)
        }`,
        { headers: { Authorization: `Bearer ${finalizeSecretKey}` } },
      );

      if (!finalizePiRes.ok) {
        console.error("Payment-first: PaymentIntent retrieve failed", {
          requestId,
          tenantId: tenant.id,
          paymentIntentId,
          status: finalizePiRes.status,
        });
        return errorResponse(
          "PAYMENT_INTENT_NOT_FOUND",
          "Payment could not be verified",
          404,
        );
      }

      const finalizePaymentIntent = (await finalizePiRes.json()) as {
        id?: string;
        status?: string;
        customer?: string | { id?: string } | null;
        metadata?: Record<string, string> | null;
      };

      // Only an actually-authorized payment may mint an order. requires_capture
      // is the expected state (capture_method=manual); succeeded covers a payment
      // already captured; processing covers async methods still settling.
      const finalizeStatus = finalizePaymentIntent.status ?? "";
      if (
        finalizeStatus !== "requires_capture" &&
        finalizeStatus !== "succeeded" &&
        finalizeStatus !== "processing"
      ) {
        console.warn(
          "Payment-first: finalize blocked, payment not authorized",
          {
            requestId,
            tenantId: tenant.id,
            paymentIntentId,
            status: finalizeStatus,
          },
        );
        return errorResponse(
          "PAYMENT_NOT_AUTHORIZED",
          "This payment has not been authorized",
          409,
        );
      }

      // The intent must be OURS: same tenant, same product. Otherwise a buyer
      // could authorize a $1 intent and finalize a $499 product.
      const finalizeIntentMetadata = finalizePaymentIntent.metadata ?? {};
      if (
        finalizeIntentMetadata.tenant_id !== tenant.id ||
        finalizeIntentMetadata.product_id !== finalizeProduct.id
      ) {
        console.warn("Payment-first: finalize blocked, intent mismatch", {
          requestId,
          tenantId: tenant.id,
          productId: finalizeProduct.id,
          paymentIntentId,
          intentTenantId: finalizeIntentMetadata.tenant_id ?? null,
          intentProductId: finalizeIntentMetadata.product_id ?? null,
        });
        return errorResponse(
          "INTENT_MISMATCH",
          "This payment does not belong to this checkout",
          400,
        );
      }

      const finalizeProductPayload = {
        id: finalizeProduct.id,
        name: finalizeProduct.name,
        description: finalizeProduct.description,
        terms_and_conditions_html: finalizeProduct.terms_and_conditions_html,
        price_cents: finalizeProduct.price_cents,
        price_formatted: `$${(finalizeProduct.price_cents / 100).toFixed(2)}`,
        payment_type: finalizeProduct.payment_type,
      };

      // --- IDEMPOTENCY: one payment => at most one order --------------------
      // A double-submit (or a retried request) must return the SAME order rather
      // than minting a second one. order_payment_provider_transactions is the
      // link written by upsertOrderPaymentProviderTransaction below, so the
      // presence of a row for this payment intent means we already finalized.
      const { data: existingTransaction } = await supabaseAdmin
        .from("order_payment_provider_transactions")
        .select("order_id")
        .eq("tenant_id", tenant.id)
        .eq("payment_provider_id", finalizeProviderId)
        .eq("provider_payment_intent_id", paymentIntentId)
        .maybeSingle();

      if (existingTransaction?.order_id) {
        const { data: existingOrder } = await supabaseAdmin
          .from("orders")
          .select("id, order_number, patient_id")
          .eq("id", existingTransaction.order_id)
          .eq("tenant_id", tenant.id)
          .maybeSingle();

        if (existingOrder?.id) {
          const { data: existingOrderPatient } = await supabaseAdmin
            .from("patients")
            .select("auth_user_id, email_verified_at")
            .eq("id", existingOrder.patient_id)
            .eq("tenant_id", tenant.id)
            .maybeSingle();

          console.info(
            "Payment-first: finalize is a replay; returning the existing order",
            {
              requestId,
              tenantId: tenant.id,
              paymentIntentId,
              orderId: existingOrder.id,
            },
          );

          return jsonResponse({
            message: "Order already created for this payment",
            data: {
              order_id: existingOrder.id,
              order_number: existingOrder.order_number,
              account_exists: Boolean(
                existingOrderPatient?.auth_user_id &&
                  existingOrderPatient?.email_verified_at,
              ),
              payment_intent_id: paymentIntentId,
              product: finalizeProductPayload,
            },
          });
        }
      }

      // --- ONLY NOW: resolve the patient and create the order ---------------
      const finalizeResolvedPatient = await resolveCheckoutPatient({
        tenantId: tenant.id,
        buyerEmail: finalizeEmail,
        authenticatedPatient: finalizePatient,
      });
      if ("error" in finalizeResolvedPatient) {
        return errorResponse(
          finalizeResolvedPatient.error.code,
          finalizeResolvedPatient.error.message,
          finalizeResolvedPatient.error.status,
        );
      }
      const {
        patientId: finalizePatientId,
        accountExists: finalizeAccountExists,
      } = finalizeResolvedPatient;

      // Re-resolve the coupon so the order carries the same amount/discount the
      // PaymentIntent charged. Prefer the amount Stripe actually authorized (from
      // the intent metadata written by /checkout/authorize) so the order can never
      // disagree with the money — fall back to a fresh lookup for older intents.
      // /checkout/authorize already resolved the coupon and wrote the result onto
      // the PaymentIntent's metadata. Those numbers ARE the money — Stripe charged
      // them — so use them directly and do not re-resolve.
      //
      // This used to always call resolveCheckoutCoupon() (a round-trip to the
      // Stripe promotion_codes API) and then throw the answer away whenever the
      // metadata was present, which it always is. Pure latency on the path between
      // the buyer's card clearing and their order appearing.
      const intentAmountCents = Number(
        finalizeIntentMetadata.amount_cents ?? "",
      );
      const intentDiscountCents = Number(
        finalizeIntentMetadata.discount_cents ?? "",
      );
      const hasIntentAmount = finalizeIntentMetadata.amount_cents !== undefined &&
        Number.isFinite(intentAmountCents);

      // Only an intent minted before this metadata existed needs a fresh lookup.
      const finalizeCoupon = hasIntentAmount ? null : await resolveCheckoutCoupon({
        stripeSecretKey: finalizeSecretKey,
        basePriceCents: finalizeProduct.price_cents as number,
        promotionCode: body.promotion_code ??
          finalizeIntentMetadata.coupon_code ?? null,
      });

      const finalizeAmountCents = hasIntentAmount
        ? intentAmountCents
        : finalizeCoupon!.amountCents;
      const finalizeDiscountCents =
        finalizeIntentMetadata.discount_cents !== undefined &&
          Number.isFinite(intentDiscountCents)
          ? intentDiscountCents
          : (finalizeCoupon?.discountCents ?? 0);
      const finalizeCouponCode = finalizeIntentMetadata.coupon_code ??
        finalizeCoupon?.appliedCouponCode ?? null;

      // The Stripe Customer created by /checkout/authorize has no patient row yet.
      // Write it onto patients.metadata exactly the way
      // ensureStripeCustomerForPatient would, so renewals, the billing portal and
      // retry-payment all find it. Never overwrite an existing customer id.
      const finalizeCustomerId = typeof finalizePaymentIntent.customer ===
          "string"
        ? finalizePaymentIntent.customer.trim()
        : (finalizePaymentIntent.customer as { id?: string } | null)?.id
          ?.trim() ?? "";

      if (finalizeCustomerId) {
        const { data: finalizePatientRow } = await supabaseAdmin
          .from("patients")
          .select("metadata")
          .eq("id", finalizePatientId)
          .eq("tenant_id", tenant.id)
          .maybeSingle();

        const finalizePatientMetadata: Record<string, unknown> =
          finalizePatientRow?.metadata &&
            typeof finalizePatientRow.metadata === "object" &&
            !Array.isArray(finalizePatientRow.metadata)
            ? finalizePatientRow.metadata as Record<string, unknown>
            : {};

        const alreadyLinkedCustomerId =
          typeof finalizePatientMetadata.stripe_customer_id === "string"
            ? finalizePatientMetadata.stripe_customer_id.trim()
            : "";

        if (!alreadyLinkedCustomerId) {
          const { error: persistCustomerError } = await supabaseAdmin
            .from("patients")
            .update({
              metadata: {
                ...finalizePatientMetadata,
                stripe_customer_id: finalizeCustomerId,
              },
            })
            .eq("id", finalizePatientId)
            .eq("tenant_id", tenant.id);

          if (persistCustomerError) {
            console.warn(
              "Payment-first: failed to persist stripe_customer_id on the patient (renewals may not find the customer)",
              {
                requestId,
                patientId: finalizePatientId,
                customerId: finalizeCustomerId,
                error: persistCustomerError.message,
              },
            );
          }
        }
      }

      // Marketing opt-in captured on the checkout form (opt-in only; we never
      // silently downgrade an existing preference).
      if (body.subscribe_to_email_and_sms_marketing === true) {
        const { error: marketingUpdateError } = await supabaseAdmin
          .from("patients")
          .update({
            subscribed_to_email_marketing: true,
            subscribed_to_sms_marketing: true,
          })
          .eq("id", finalizePatientId)
          .eq("tenant_id", tenant.id);

        if (marketingUpdateError) {
          console.warn("Payment-first: failed to persist marketing opt-in", {
            requestId,
            patientId: finalizePatientId,
            error: marketingUpdateError.message,
          });
        }
      }

      // Create the order in EXACTLY the state the legacy embedded flow produces
      // (same status, same order_status_history row, same subscription handling,
      // same shipping/billing columns). allowReuse:false because idempotency here
      // is keyed on the PaymentIntent above, not on a status window.
      const finalizeOrderResult = await createOrReuseCheckoutOrder({
        tenantId: tenant.id,
        patientId: finalizePatientId,
        product: {
          id: finalizeProduct.id,
          price_cents: finalizeProduct.price_cents as number,
          payment_type: finalizeProduct.payment_type as string | null,
        },
        stripePaymentProviderId: finalizeProviderId,
        amountCents: finalizeAmountCents,
        discountCents: finalizeDiscountCents,
        appliedCouponCode: finalizeCouponCode,
        fullName: body.full_name ?? finalizeIntentMetadata.full_name ?? null,
        shippingAddressLine1: body.shipping_address_line1 ?? null,
        shippingAddressLine2: body.shipping_address_line2 ?? null,
        shippingCity: body.shipping_city ?? null,
        shippingState: body.shipping_state ?? null,
        shippingPostalCode: body.shipping_postal_code ?? null,
        shippingCountry: body.shipping_country ?? null,
        reuseOrderId: null,
        allowReuse: false,
        newOrderNotes: "Order created via payment-first checkout",
        reusedOrderNotes: "Order created via payment-first checkout",
      });
      if ("error" in finalizeOrderResult) {
        return errorResponse(
          finalizeOrderResult.error.code,
          finalizeOrderResult.error.message,
          finalizeOrderResult.error.status,
        );
      }

      // Link the payment intent to the order — the SAME call the legacy route
      // makes. Without this row the RTDH create-order dispatch has no
      // payment_intent_id to link by and the order stalls at order_created.
      // paid_at is deliberately NOT set here: capture (and the Stripe/RTDH paid
      // callback) still owns that, exactly as today.
      try {
        await upsertOrderPaymentProviderTransaction({
          supabase: supabaseAdmin,
          tenantId: tenant.id,
          orderId: finalizeOrderResult.orderId,
          paymentProviderId: finalizeProviderId,
          providerPaymentIntentId: paymentIntentId,
          providerCustomerId: finalizeCustomerId || null,
          paymentStatus: "pending",
        });
      } catch (txError) {
        console.error(
          "CRITICAL: failed to link payment transaction to order; order will stall at order_created until linked",
          {
            orderId: finalizeOrderResult.orderId,
            paymentIntentId,
            paymentProviderId: finalizeProviderId,
            error: txError instanceof Error ? txError.message : String(txError),
          },
        );
      }

      // Trigger the order lifecycle (dispatches the RTDH create-order webhook) —
      // identical to the legacy embedded flow.
      try {
        await triggerOrderLifecycleForOrder(
          finalizeOrderResult.orderId,
          tenant.id,
        );
      } catch (lifecycleError) {
        console.error(
          "Order lifecycle trigger failed (order still created)",
          lifecycleError,
        );
      }

      console.info("Payment-first: order created after payment authorization", {
        requestId,
        paymentIntentId,
        paymentIntentStatus: finalizeStatus,
        orderId: finalizeOrderResult.orderId,
        productId: finalizeProduct.id,
        buyerEmail: finalizeEmail,
        amountCents: finalizeAmountCents,
        couponCode: finalizeCouponCode,
      });

      return jsonResponse({
        message: "Order created",
        data: {
          order_id: finalizeOrderResult.orderId,
          order_number: finalizeOrderResult.orderNumber,
          account_exists: finalizeAccountExists,
          payment_intent_id: paymentIntentId,
          amount_cents: finalizeAmountCents,
          discount_cents: finalizeDiscountCents,
          coupon_code: finalizeCouponCode,
          currency: "usd",
          product: finalizeProductPayload,
        },
      });
    }

    // POST /orders/{product_id}/payment-intent - Create a Stripe PaymentIntent
    // for the embedded Stripe Elements checkout (Option 2 flow). Unlike the
    // hosted /checkout route below, this supports GUEST checkout: no account or
    // login is required before payment. Eligibility is enforced server-side by
    // email so a conflicting active medication blocks the purchase even when the
    // buyer is not authenticated.
    if (
      req.method === "POST" &&
      path.match(/^\/orders\/[a-f0-9-]+\/payment-intent$/)
    ) {
      const productId = path.split("/")[2];

      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      // Parse request body. email is required for guest checkout; an
      // authenticated patient may omit it (we fall back to their account email).
      let body: {
        email?: string;
        full_name?: string;
        phone?: string;
        /** Optional promotion code (as typed by the customer) to apply. */
        promotion_code?: string;
        /**
         * Optional existing order id. When the UI re-prepares the SAME checkout
         * (e.g. applying/changing a coupon), it passes the order_id it already
         * has so we UPDATE that order in place instead of creating another one.
         * This is the authoritative dedup signal — no heuristic reuse-matching
         * race (an order can advance to the provider within seconds).
         */
        order_id?: string;
        /** Shipping details captured on the checkout form. */
        shipping_address_line1?: string;
        shipping_address_line2?: string;
        shipping_city?: string;
        shipping_state?: string;
        shipping_postal_code?: string;
        shipping_country?: string;
      } = {};
      try {
        body = await req.json();
      } catch {
        // Body is optional
      }

      // Resolve the buyer email: authenticated patient email wins, otherwise the
      // email supplied in the checkout form.
      const { patient } = await getAuthenticatedPatient().catch(() => ({
        patient: null,
      }));
      const buyerEmail = (patient?.email ?? body.email ?? "").trim()
        .toLowerCase();

      if (!buyerEmail || !buyerEmail.includes("@")) {
        return errorResponse(
          "EMAIL_REQUIRED",
          "A valid email is required to start checkout",
          400,
        );
      }

      // Load the product for this tenant.
      const { data: product, error: productError } = await supabaseAdmin
        .from("products")
        .select(
          "id, name, description, terms_and_conditions_html, price_cents, payment_type, image_url, metadata",
        )
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) {
        console.error("Product fetch error:", productError);
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }
      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const eligibility = await checkCheckoutEligibility({
        supabaseAdmin,
        tenantId: tenant.id,
        productId: product.id,
        buyerEmail,
        resumeOrderId: body.order_id,
      });
      if (!eligibility.ok) {
        return errorResponse(
          eligibility.code,
          eligibility.message,
          eligibility.status,
        );
      }

      // --- Resolve tenant Stripe credentials --------------------------------
      const stripeCreds = await resolveTenantStripe(tenant.id);
      if ("error" in stripeCreds) {
        return errorResponse(
          stripeCreds.error.code,
          stripeCreds.error.message,
          stripeCreds.error.status,
        );
      }
      const { stripeSecretKey, stripePaymentProviderId } = stripeCreds;

      // --- Optional coupon: resolve a typed promo code to its discount ------
      const { amountCents, discountCents, appliedCouponCode } =
        await resolveCheckoutCoupon({
          stripeSecretKey,
          basePriceCents: product.price_cents as number,
          promotionCode: body.promotion_code,
        });

      // --- Resolve the patient ----------------------------------------------
      const resolvedPatient = await resolveCheckoutPatient({
        tenantId: tenant.id,
        buyerEmail,
        authenticatedPatient: patient,
      });
      if ("error" in resolvedPatient) {
        return errorResponse(
          resolvedPatient.error.code,
          resolvedPatient.error.message,
          resolvedPatient.error.status,
        );
      }
      const { patientId: resolvedPatientId, accountExists } = resolvedPatient;

      // --- Create the order synchronously (status: order_created) -----------
      // Creating the order up front (rather than waiting for the Stripe webhook)
      // gives the UI an orderId immediately so it can drive the questionnaire +
      // tracking steps. We reuse the same status + lifecycle trigger as the
      // hosted-checkout path, so the RTDH create-order webhook fires exactly as
      // it does today and the Stripe→RTDH→paid callback (keyed on the payment
      // intent id) advances this order without any change to those flows.
      const orderResult = await createOrReuseCheckoutOrder({
        tenantId: tenant.id,
        patientId: resolvedPatientId,
        product: {
          id: product.id,
          price_cents: product.price_cents as number,
          payment_type: product.payment_type as string | null,
        },
        stripePaymentProviderId,
        amountCents,
        discountCents,
        appliedCouponCode,
        fullName: body.full_name ?? null,
        shippingAddressLine1: body.shipping_address_line1 ?? null,
        shippingAddressLine2: body.shipping_address_line2 ?? null,
        shippingCity: body.shipping_city ?? null,
        shippingState: body.shipping_state ?? null,
        shippingPostalCode: body.shipping_postal_code ?? null,
        shippingCountry: body.shipping_country ?? null,
        reuseOrderId: body.order_id ?? null,
        allowReuse: true,
        newOrderNotes: "Order created via embedded Elements checkout",
        reusedOrderNotes:
          "Order reused for embedded Elements checkout (Option 2) — coupon/amount refreshed",
      });
      if ("error" in orderResult) {
        return errorResponse(
          orderResult.error.code,
          orderResult.error.message,
          orderResult.error.status,
        );
      }
      const createdOrder = {
        id: orderResult.orderId,
        order_number: orderResult.orderNumber,
      };

      // --- Zero-amount (100% discount) path ---------------------------------
      // Stripe rejects a $0 PaymentIntent. Behavior splits by product type:
      //   * One-time product  -> no card needed; advance straight to the
      //     questionnaire (client_secret null, requires_payment false).
      //   * Subscription      -> a card is STILL required so the subscription can
      //     renew once the 100%-off coupon expires. Stripe rejects a $0
      //     PaymentIntent but ALLOWS a $0 SetupIntent, so we create a SetupIntent
      //     (attached to the Customer, usage off_session) and return its
      //     client_secret with requires_setup:true for the embedded element.
      if (amountCents <= 0) {
        const isSubscriptionZero = product.payment_type === "subscription";

        if (isSubscriptionZero) {
          // A subscription must capture a payment method even at $0. Ensure a
          // Stripe Customer, then create a SetupIntent to collect/save the card.
          const setupCustomerId = await ensureStripeCustomerForPatient({
            patientId: resolvedPatientId,
            tenantId: tenant.id,
            stripeSecretKey,
            email: buyerEmail,
            fullName: body.full_name ?? null,
            phone: body.phone ?? null,
          });

          if (!setupCustomerId) {
            console.error(
              "Zero-amount subscription: failed to create Stripe customer; cannot collect card for renewals",
              { orderId: createdOrder.id, buyerEmail },
            );
            return errorResponse(
              "STRIPE_ERROR",
              "Could not start subscription setup. Please try again.",
              500,
            );
          }

          const siParams = new URLSearchParams();
          siParams.append("customer", setupCustomerId);
          siParams.append("usage", "off_session");
          siParams.append("automatic_payment_methods[enabled]", "true");
          siParams.append(
            "automatic_payment_methods[allow_redirects]",
            "never",
          );
          siParams.append("metadata[tenant_id]", tenant.id);
          siParams.append("metadata[product_id]", product.id);
          siParams.append("metadata[customer_email]", buyerEmail);
          siParams.append("metadata[checkout_flow]", "elements_option2_setup");
          siParams.append(
            "metadata[patient_platform_order_id]",
            createdOrder.id,
          );
          siParams.append("metadata[patient_id]", resolvedPatientId);
          if (appliedCouponCode) {
            siParams.append("metadata[coupon_code]", appliedCouponCode);
          }

          const siRes = await fetch("https://api.stripe.com/v1/setup_intents", {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: siParams.toString(),
          });

          if (!siRes.ok) {
            const stripeError = await siRes.json();
            console.error("Stripe SetupIntent error:", stripeError);
            return errorResponse(
              "STRIPE_ERROR",
              stripeError.error?.message ||
                "Failed to start subscription setup",
              500,
            );
          }

          const setupIntent = await siRes.json();

          // Record the customer on the order transaction so the subscription can
          // be created later (at the payment-skip step) with the saved card.
          try {
            await upsertOrderPaymentProviderTransaction({
              supabase: supabaseAdmin,
              tenantId: tenant.id,
              orderId: createdOrder.id,
              paymentProviderId: stripePaymentProviderId,
              providerCustomerId: setupCustomerId,
              paymentStatus: "no_payment_required",
            });
          } catch (txError) {
            console.error(
              "Zero-amount subscription: failed to record customer transaction",
              { orderId: createdOrder.id, error: txError },
            );
          }

          // Do NOT advance the lifecycle yet — wait until the card is saved
          // (the UI confirms the SetupIntent, then calls the existing confirm
          // path / re-fetch). The order stays at order_created until then.
          console.info(
            "Zero-amount subscription: SetupIntent created to collect card",
            {
              orderId: createdOrder.id,
              buyerEmail,
              couponCode: appliedCouponCode,
            },
          );

          return jsonResponse({
            message: "Subscription setup required (no charge today)",
            data: {
              client_secret: setupIntent.client_secret,
              setup_intent_id: setupIntent.id,
              payment_intent_id: null,
              requires_payment: false,
              requires_setup: true,
              order_id: createdOrder.id,
              account_exists: accountExists,
              order_number: createdOrder.order_number,
              amount_cents: 0,
              discount_cents: discountCents,
              coupon_code: appliedCouponCode,
              currency: "usd",
              product: {
                id: product.id,
                name: product.name,
                description: product.description,
                terms_and_conditions_html: product.terms_and_conditions_html,
                price_cents: product.price_cents,
                price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
                payment_type: product.payment_type,
              },
            },
          });
        }

        // One-time $0 product: no card needed; advance straight through.
        try {
          await triggerOrderLifecycleForOrder(createdOrder.id, tenant.id);
        } catch (lifecycleError) {
          console.error(
            "Order lifecycle trigger failed (zero-amount order still created)",
            lifecycleError,
          );
        }

        console.info("Zero-amount order created (coupon covers full price)", {
          orderId: createdOrder.id,
          buyerEmail,
          couponCode: appliedCouponCode,
        });

        return jsonResponse({
          message: "Order created (no payment required)",
          data: {
            client_secret: null,
            payment_intent_id: null,
            requires_payment: false,
            requires_setup: false,
            order_id: createdOrder.id,
            account_exists: accountExists,
            order_number: createdOrder.order_number,
            amount_cents: 0,
            discount_cents: discountCents,
            coupon_code: appliedCouponCode,
            currency: "usd",
            product: {
              id: product.id,
              name: product.name,
              description: product.description,
              terms_and_conditions_html: product.terms_and_conditions_html,
              price_cents: product.price_cents,
              price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
              payment_type: product.payment_type,
            },
          },
        });
      }

      // --- Create the PaymentIntent (manual capture; multiple methods) ------
      // automatic_payment_methods surfaces every method enabled in the tenant's
      // Stripe Dashboard that is compatible with this PaymentIntent — cards,
      // Apple Pay, Google Pay, Link, and more — in the embedded PaymentElement.
      // allow_redirects=never keeps the customer on-page (no redirect methods)
      // which suits the embedded, single-page Option 2 flow. Manual capture
      // mirrors the hosted flow: authorize now, capture after clinical review.
      // patient_platform_order_id ties the intent to the order so the existing
      // Stripe/RTDH webhooks resolve and advance THIS order on payment.
      const isSubscription = product.payment_type === "subscription";

      // Resolve a reusable Stripe Customer and attach it to the PaymentIntent.
      // Without a Customer, setup_future_usage saves a payment method that
      // cannot be reused off_session (renewals, retry-payment, billing portal).
      // The hosted flow got this via Checkout Session customer_creation:always;
      // the embedded flow must create it explicitly. Best-effort: a failure here
      // must not block a one-time purchase, but for subscriptions a missing
      // Customer means renewals can't be set up later.
      const stripeCustomerId = await ensureStripeCustomerForPatient({
        patientId: resolvedPatientId,
        tenantId: tenant.id,
        stripeSecretKey,
        email: buyerEmail,
        fullName: body.full_name ?? null,
        phone: body.phone ?? null,
      });
      if (!stripeCustomerId && isSubscription) {
        console.error(
          "Subscription PaymentIntent proceeding WITHOUT a Stripe customer; renewals cannot be set up",
          { orderId: createdOrder.id, buyerEmail },
        );
      }

      const piParams = new URLSearchParams();
      piParams.append("amount", String(amountCents));
      piParams.append("currency", "usd");
      piParams.append("capture_method", "manual");
      piParams.append("automatic_payment_methods[enabled]", "true");
      piParams.append("automatic_payment_methods[allow_redirects]", "never");
      piParams.append("receipt_email", buyerEmail);
      if (stripeCustomerId) {
        piParams.append("customer", stripeCustomerId);
      }
      if (isSubscription) {
        piParams.append("setup_future_usage", "off_session");
      }
      piParams.append("metadata[tenant_id]", tenant.id);
      piParams.append("metadata[product_id]", product.id);
      piParams.append("metadata[customer_email]", buyerEmail);
      piParams.append("metadata[checkout_flow]", "elements_option2");
      piParams.append("metadata[patient_platform_order_id]", createdOrder.id);
      piParams.append("metadata[patient_id]", resolvedPatientId);
      if (body.full_name?.trim()) {
        piParams.append("metadata[full_name]", body.full_name.trim());
      }
      if (body.phone?.trim()) {
        piParams.append("metadata[phone]", body.phone.trim());
      }
      if (appliedCouponCode) {
        piParams.append("metadata[coupon_code]", appliedCouponCode);
        piParams.append("metadata[discount_cents]", String(discountCents));
      }

      const piRes = await fetch("https://api.stripe.com/v1/payment_intents", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: piParams.toString(),
      });

      if (!piRes.ok) {
        const stripeError = await piRes.json();
        console.error("Stripe PaymentIntent error:", stripeError);
        return errorResponse(
          "STRIPE_ERROR",
          stripeError.error?.message || "Failed to create payment intent",
          500,
        );
      }

      const paymentIntent = await piRes.json();

      // Link the payment intent to the order so the Stripe/RTDH paid-callback
      // can find this order by provider_payment_intent_id.
      try {
        await upsertOrderPaymentProviderTransaction({
          supabase: supabaseAdmin,
          tenantId: tenant.id,
          orderId: createdOrder.id,
          paymentProviderId: stripePaymentProviderId,
          providerPaymentIntentId: paymentIntent.id,
          providerCustomerId: stripeCustomerId,
          paymentStatus: "pending",
        });
      } catch (txError) {
        // This link is REQUIRED for the order to progress: without the
        // order_payment_provider_transactions row, the RTDH create-order
        // dispatch has no payment_intent_id (nor checkout_session_id) to link
        // by, so it is rejected and the order stalls at order_created (the
        // patient never reaches the questionnaire). Surface loudly.
        console.error(
          "CRITICAL: failed to link payment transaction to order; order will stall at order_created until linked",
          {
            orderId: createdOrder.id,
            paymentIntentId: paymentIntent.id,
            paymentProviderId: stripePaymentProviderId,
            error: txError instanceof Error ? txError.message : String(txError),
          },
        );
      }

      // Trigger the order lifecycle, which (for order_created) dispatches the
      // RTDH create-order webhook — identical to the hosted-checkout flow.
      try {
        await triggerOrderLifecycleForOrder(createdOrder.id, tenant.id);
      } catch (lifecycleError) {
        console.error(
          "Order lifecycle trigger failed (order still created)",
          lifecycleError,
        );
      }

      console.info("PaymentIntent + order created", {
        paymentIntentId: paymentIntent.id,
        orderId: createdOrder.id,
        productId: product.id,
        buyerEmail,
        amountCents,
        couponCode: appliedCouponCode,
      });

      return jsonResponse({
        message: "Payment intent created",
        data: {
          client_secret: paymentIntent.client_secret,
          payment_intent_id: paymentIntent.id,
          requires_payment: true,
          order_id: createdOrder.id,
          account_exists: accountExists,
          order_number: createdOrder.order_number,
          amount_cents: amountCents,
          discount_cents: discountCents,
          coupon_code: appliedCouponCode,
          currency: "usd",
          product: {
            id: product.id,
            name: product.name,
            description: product.description,
            terms_and_conditions_html: product.terms_and_conditions_html,
            price_cents: product.price_cents,
            price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
            payment_type: product.payment_type,
          },
        },
      });
    }

    // POST /orders/{product_id}/checkout - Create Stripe checkout session
    // Requires an authenticated patient.
    if (
      req.method === "POST" &&
      path.match(/^\/orders\/[a-f0-9-]+\/checkout$/)
    ) {
      const productId = path.split("/")[2];

      // Require tenant context
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id, name");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      // Parse request body
      let body: {
        success_url?: string;
        cancel_url?: string;
        /** Optional: caller-supplied promotion code ID to apply to this session. */
        promotion_code_id?: string;
      } = {};

      try {
        body = await req.json();
      } catch {
        // Body is optional
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      if (patient!.tenant_id !== tenant.id) {
        return errorResponse(
          "TENANT_MISMATCH",
          "Patient does not belong to this tenant",
          403,
        );
      }

      const patientId = patient!.id;
      const customerEmail = patient!.email;

      // Get product details
      const { data: product, error: productError } = await supabase
        .from("products")
        .select(
          "id, name, description, terms_and_conditions_html, price_cents, payment_type, subscription_interval, subscription_interval_count, subscription_renewal_lead_days, image_url, metadata",
        )
        .eq("id", productId)
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) {
        console.error("Product fetch error:", productError);
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }

      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      // Get tenant's Stripe payment provider configuration
      const { data: stripeProvider, error: providerError } = await supabaseAdmin
        .from("tenant_payment_providers")
        .select(
          `
          id,
          is_enabled,
          settings,
          payment_providers!inner (
            id,
            key,
            name
          )
        `,
        )
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (providerError) {
        console.error("Provider fetch error:", providerError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch payment provider",
          500,
        );
      }

      if (!stripeProvider) {
        return errorResponse(
          "NO_PAYMENT_PROVIDER",
          "No Stripe payment provider configured for this tenant",
          400,
        );
      }

      const settings = stripeProvider.settings as Record<string, string>;
      const stripeSecretKey = settings?.secret_key;

      if (!stripeSecretKey) {
        return errorResponse(
          "PROVIDER_NOT_CONFIGURED",
          "Stripe secret key not configured",
          400,
        );
      }

      // Fetch tenant settings for allowed countries
      const { data: tenantSettings } = await supabaseAdmin
        .from("tenant_settings")
        .select("allowed_countries")
        .eq("tenant_id", tenant.id)
        .maybeSingle();

      // Default to US if no settings or empty array
      const allowedCountries: string[] = (
          tenantSettings as { allowed_countries?: string[] } | null
        )?.allowed_countries?.length
        ? (tenantSettings as { allowed_countries: string[] }).allowed_countries
        : ["US"];

      // Determine base URL from request Origin or Referer header
      const originHeader = req.headers.get("origin");
      const refererHeader = req.headers.get("referer");
      let baseUrl: string;

      if (originHeader) {
        baseUrl = originHeader;
      } else if (refererHeader) {
        try {
          const refererUrl = new URL(refererHeader);
          baseUrl = refererUrl.origin;
        } catch {
          baseUrl = url.origin;
        }
      } else {
        baseUrl = url.origin;
      }

      // Set success/cancel URLs using the calling app's base URL
      let successUrl =
        `${baseUrl}/checkout/success?session_id={CHECKOUT_SESSION_ID}`;
      let cancelUrl = `${baseUrl}/checkout/cancel`;

      // Allow overrides from body if provided
      if (body.success_url) successUrl = body.success_url;
      if (body.cancel_url) cancelUrl = body.cancel_url;

      // Build Stripe Checkout Session params
      const sessionParams = new URLSearchParams();
      const isSubscription = product.payment_type === "subscription";
      sessionParams.append("mode", "payment");
      sessionParams.append("success_url", successUrl);
      sessionParams.append("cancel_url", cancelUrl);
      if (isSubscription) {
        // Subscription checkout still charges the initial order immediately,
        // but we require a persisted customer and reusable card so renewals can
        // be created from the checkout payment method.
        sessionParams.append("customer_creation", "always");
        sessionParams.append("payment_method_types[0]", "card");
      }

      // Session metadata (for checkout.session.completed event)
      sessionParams.append("metadata[tenant_id]", tenant.id);
      sessionParams.append("metadata[product_id]", product.id);
      // Authorize now, capture later (manual capture flow) for all products.
      sessionParams.append("payment_intent_data[capture_method]", "manual");
      sessionParams.append(
        "payment_intent_data[metadata][tenant_id]",
        tenant.id,
      );
      sessionParams.append(
        "payment_intent_data[metadata][product_id]",
        product.id,
      );
      if (isSubscription) {
        sessionParams.append(
          "payment_intent_data[setup_future_usage]",
          "off_session",
        );
      }

      // Only set customer_email if we have one (authenticated users)
      if (customerEmail) {
        sessionParams.append("customer_email", customerEmail);
        sessionParams.append("metadata[customer_email]", customerEmail);
        sessionParams.append(
          "payment_intent_data[metadata][customer_email]",
          customerEmail,
        );
      }

      // Include patient_id if we have one (for existing patients)
      if (patientId) {
        sessionParams.append("client_reference_id", patientId);
        sessionParams.append("metadata[patient_id]", patientId);
        sessionParams.append(
          "payment_intent_data[metadata][patient_id]",
          patientId,
        );
      }

      // Note: Shipping address collection disabled - addresses managed separately

      // If the product has promo code entry enabled, show Stripe's built-in
      // promo code input field. Customers enter the code manually at checkout.
      const productMeta =
        (product.metadata as Record<string, unknown> | null) ?? {};

      // Look up the synced Stripe product ID so that coupon `applies_to`
      // restrictions match the line item. Coupons are scoped to the Stripe
      // product at creation time; if we use inline price_data with product_data
      // Stripe creates a temporary product that never matches applies_to.
      // Always search to avoid creating duplicate products.
      let stripeProductId: string | null = null;
      const searchQuery = `metadata['allia_product_id']:'${product.id}'`;
      const stripeProductRes = await fetch(
        `https://api.stripe.com/v1/products/search?query=${
          encodeURIComponent(
            searchQuery,
          )
        }&limit=1`,
        { headers: { Authorization: `Bearer ${stripeSecretKey}` } },
      );
      if (stripeProductRes.ok) {
        const stripeProductBody = await stripeProductRes.json();
        stripeProductId = stripeProductBody?.data?.[0]?.id ?? null;
      }
      if (productMeta.allow_promo_codes === true) {
        sessionParams.append("allow_promotion_codes", "true");
      }

      sessionParams.append("line_items[0][price_data][currency]", "usd");
      sessionParams.append(
        "line_items[0][price_data][unit_amount]",
        product.price_cents.toString(),
      );
      // Use the synced Stripe product when available so coupon applies_to
      // restrictions match. Fall back to inline product_data otherwise.
      if (stripeProductId) {
        sessionParams.append(
          "line_items[0][price_data][product]",
          stripeProductId,
        );
      } else {
        sessionParams.append(
          "line_items[0][price_data][product_data][name]",
          product.name,
        );
        if (product.description) {
          sessionParams.append(
            "line_items[0][price_data][product_data][description]",
            product.description,
          );
        }
        if (product.image_url) {
          sessionParams.append(
            "line_items[0][price_data][product_data][images][0]",
            product.image_url,
          );
        }
      }
      sessionParams.append("line_items[0][quantity]", "1");

      // Create Stripe Checkout Session
      console.info("Creating Stripe checkout session", {
        tenantId: tenant.id,
        productId: product.id,
        paymentType: product.payment_type,
        isSubscription,
        mode: isSubscription ? "setup" : "payment",
        hasCurrencyParam: sessionParams.has("currency"),
        currency: sessionParams.get("currency"),
      });
      const stripeResponse = await fetch(
        "https://api.stripe.com/v1/checkout/sessions",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${stripeSecretKey}`,
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: sessionParams.toString(),
        },
      );

      if (!stripeResponse.ok) {
        const stripeError = await stripeResponse.json();
        console.error("Stripe Checkout Session error:", stripeError);
        return errorResponse(
          "STRIPE_ERROR",
          stripeError.error?.message || "Failed to create checkout session",
          500,
        );
      }

      const session = await stripeResponse.json();

      console.info("Checkout session created", {
        sessionId: session.id,
        productId: product.id,
        customerEmail: customerEmail || null,
        patientId,
        tenantId: tenant.id,
      });

      return jsonResponse({
        message: "Checkout session created",
        data: {
          checkout_url: session.url,
          session_id: session.id,
          expires_at: session.expires_at,
          product: {
            id: product.id,
            name: product.name,
            description: product.description,
            terms_and_conditions_html: product.terms_and_conditions_html,
            price_cents: product.price_cents,
            price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
            payment_type: product.payment_type,
            subscription_renewal_lead_days:
              product.subscription_renewal_lead_days,
          },
        },
      });
    }

    // GET /orders/checkout/{session_id} - Retrieve Stripe checkout session details
    if (
      req.method === "GET" &&
      path.match(/^\/orders\/checkout\/cs_[a-zA-Z0-9_]+$/)
    ) {
      const sessionId = path.split("/")[3];
      let authenticatedPatientId: string | null = null;

      // Require tenant context
      if (tenantSlugs.length === 0) {
        return errorResponse("MISSING_TENANT", "Tenant slug is required", 400);
      }

      const tenant = await getActiveTenant("id");
      if (!tenant) {
        return errorResponse(
          "TENANT_NOT_FOUND",
          "Tenant not found or inactive",
          404,
        );
      }

      // Get tenant's Stripe payment provider configuration
      const { data: stripeProvider, error: providerError } = await supabaseAdmin
        .from("tenant_payment_providers")
        .select(
          `
          id,
          is_enabled,
          settings,
          payment_providers!inner (
            id,
            key,
            name
          )
        `,
        )
        .eq("tenant_id", tenant.id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (providerError) {
        console.error("Provider fetch error:", providerError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch payment provider",
          500,
        );
      }

      if (!stripeProvider) {
        return errorResponse(
          "NO_PAYMENT_PROVIDER",
          "No Stripe payment provider configured for this tenant",
          400,
        );
      }

      const settings = stripeProvider.settings as Record<string, string>;
      const stripeSecretKey = settings?.secret_key;

      if (!stripeSecretKey) {
        return errorResponse(
          "PROVIDER_NOT_CONFIGURED",
          "Stripe secret key not configured",
          400,
        );
      }

      if (authHeader) {
        const { patient, error: authError } = await getAuthenticatedPatient();
        if (authError) return authError;
        authenticatedPatientId = patient!.id;

        if (patient!.tenant_id !== tenant.id) {
          return errorResponse(
            "FORBIDDEN",
            "Authenticated patient does not belong to this tenant",
            403,
          );
        }
      }

      const { data: stripePaymentProvider, error: stripePaymentProviderError } =
        await supabaseAdmin
          .from("payment_providers")
          .select("id")
          .eq("key", "stripe")
          .maybeSingle();

      if (stripePaymentProviderError || !stripePaymentProvider?.id) {
        console.error("Failed to resolve Stripe payment provider id", {
          requestId,
          error: stripePaymentProviderError?.message ||
            "stripe_payment_provider_not_found",
        });
        return errorResponse(
          "FETCH_ERROR",
          "Failed to resolve Stripe payment provider",
          500,
        );
      }

      // Retrieve the checkout session from Stripe
      const stripeResponse = await fetch(
        `https://api.stripe.com/v1/checkout/sessions/${sessionId}`,
        {
          method: "GET",
          headers: {
            Authorization: `Bearer ${stripeSecretKey}`,
          },
        },
      );

      if (!stripeResponse.ok) {
        const errorData = await stripeResponse.text();
        console.error("Stripe API error:", errorData);

        if (stripeResponse.status === 404) {
          return errorResponse(
            "SESSION_NOT_FOUND",
            "Checkout session not found",
            404,
          );
        }

        return errorResponse(
          "STRIPE_ERROR",
          "Failed to retrieve checkout session from Stripe",
          500,
        );
      }

      const session = (await stripeResponse.json()) as StripeCheckoutSession;

      // Verify session belongs to this tenant
      const sessionTenantId = session.metadata?.tenant_id;
      if (sessionTenantId && sessionTenantId !== tenant.id) {
        return errorResponse(
          "SESSION_NOT_FOUND",
          "Checkout session not found",
          404,
        );
      }

      console.info("Checkout session retrieved", {
        sessionId: session.id,
        tenantId: tenant.id,
        status: session.status,
        paymentStatus: session.payment_status,
        mode: session.mode || null,
        productId: session.metadata?.product_id || null,
        patientId: session.metadata?.patient_id || null,
        paymentIntentId: session.payment_intent || null,
        authenticatedPatientId,
      });

      let orderId: string | null = null;
      if (session.status === "complete") {
        try {
          const ensuredOrder = await ensureCheckoutOrder({
            supabase: supabaseAdmin,
            tenantId: tenant.id,
            stripePaymentProviderId: stripePaymentProvider.id,
            stripeSecretKey,
            session,
            authenticatedPatientId,
          });
          orderId = ensuredOrder.orderId;
          console.info("Checkout confirmation ensured order", {
            requestId,
            tenantId: tenant.id,
            sessionId: session.id,
            orderId,
            created: ensuredOrder.created,
            lifecycleTriggerAttempted: ensuredOrder.created,
            skipReason: ensuredOrder.created
              ? null
              : "order_already_existed_before_checkout_confirmation",
          });
        } catch (error) {
          const message = error instanceof Error
            ? error.message
            : String(error);
          console.error("Failed to ensure order from checkout session", {
            requestId,
            tenantId: tenant.id,
            sessionId: session.id,
            error: message,
          });

          if (message.includes("authenticated patient")) {
            return errorResponse(
              "FORBIDDEN",
              "Checkout session does not belong to the authenticated patient",
              403,
            );
          }

          return errorResponse(
            "ORDER_SYNC_ERROR",
            "Failed to create order from checkout session",
            500,
          );
        }
      } else {
        console.info(
          "Checkout session is not complete; skipping order ensure and order-lifecycle trigger",
          {
            requestId,
            tenantId: tenant.id,
            sessionId: session.id,
            status: session.status || null,
            paymentStatus: session.payment_status || null,
          },
        );
      }

      return jsonResponse({
        data: {
          id: session.id,
          order_id: orderId,
          status: session.status,
          payment_status: session.payment_status,
          customer_email: session.customer_details?.email ||
            session.customer_email || null,
          customer_name: session.customer_details?.name || null,
          customer_phone: session.customer_details?.phone ||
            session.shipping_details?.phone ||
            null,
          amount_total: session.amount_total,
          currency: session.currency,
          mode: session.mode,
          product_id: session.metadata?.product_id || null,
          created_at: session.created
            ? dateTime.unix(session.created).toISOString()
            : null,
          expires_at: session.expires_at
            ? dateTime.unix(session.expires_at).toISOString()
            : null,
          shipping_details: session.shipping_details
            ? {
              name: session.shipping_details.name,
              address: session.shipping_details.address,
            }
            : null,
        },
      });
    }

    // ==================== PLAN ENDPOINTS ====================

    // GET /plans - List plans currently associated with the authenticated patient
    // Default behavior excludes cancelled plans. Use ?include_cancelled=true to include all.
    if (req.method === "GET" && path === "/plans") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const includeCancelled =
        url.searchParams.get("include_cancelled") === "true";

      let plansQuery = supabase
        .from("subscriptions")
        .select(
          `
          id,
          status,
          started_at,
          current_period_end_at,
          expires_at,
          paused_at,
          cancelled_at,
          cancellation_reason,
          created_at,
          updated_at,
          product:products (
            id,
            name,
            description,
            terms_and_conditions_html,
            price_cents,
            payment_type,
            subscription_interval,
            subscription_interval_count,
            subscription_renewal_lead_days,
            image_url
          )
        `,
        )
        .eq("patient_id", patient!.id)
        .eq("tenant_id", patient!.tenant_id)
        .order("created_at", { ascending: false });

      if (!includeCancelled) {
        plansQuery = plansQuery.neq("status", "cancelled");
      }

      const { data: plans, error: plansError } = await plansQuery;

      if (plansError) {
        console.error("Plans fetch error:", plansError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plans", 500);
      }

      const planIds = (plans || []).map((plan) => plan.id);
      const ordersByPlanId = new Map<
        string,
        Array<{
          id: string;
          order_number: string;
          status_id: string | null;
          cancellation_reason: string | null;
          status_details: {
            id: string;
            key: string;
            label: string;
            description: string | null;
            action_required: boolean;
            is_final: boolean;
            display_order: number;
          } | null;
          status_changed_at: string | null;
          subtotal_cents: number;
          shipping_cents: number;
          tax_cents: number;
          total_cents: number;
          total_formatted: string;
          subscription_order_type: string | null;
          provider_platforms: Array<{
            name: string | null;
            integration_key: string | null;
          }>;
          tracking: {
            number: string;
            url: string | null;
          } | null;
          shipped_at: string | null;
          delivered_at: string | null;
          cancelled_at: string | null;
          paused_at: string | null;
          created_at: string;
          updated_at: string;
        }>
      >();
      const providerPlatformsByOrderId = new Map<
        string,
        Array<{
          name: string | null;
          integration_key: string | null;
        }>
      >();

      if (planIds.length > 0) {
        const { data: planOrders, error: planOrdersError } = await supabase
          .from("orders")
          .select(
            `
            id,
            subscription_id,
            order_number,
            status_id,
            cancellation_reason,
            status_changed_at,
            subtotal_cents,
            shipping_cents,
            tax_cents,
            total_cents,
            subscription_order_type,
            tracking_number,
            tracking_url,
            shipped_at,
            delivered_at,
            cancelled_at,
            paused_at,
            created_at,
            updated_at,
            order_statuses (
              id,
              status_key,
              patient_status_label,
              patient_microcopy,
              patient_action_required,
              is_terminal,
              display_order
            )
          `,
          )
          .in("subscription_id", planIds)
          .eq("patient_id", patient!.id)
          .eq("tenant_id", patient!.tenant_id)
          .order("created_at", { ascending: false });

        if (planOrdersError) {
          console.error("Plan orders fetch error:", planOrdersError);
          return errorResponse(
            "FETCH_ERROR",
            "Failed to fetch plan orders",
            500,
          );
        }

        const orderIds = (planOrders || []).map((order) => order.id);

        try {
          const fetchedProviderPlatforms = await fetchOrderProviderPlatforms({
            orderIds,
            tenantId: patient!.tenant_id,
          });

          for (const [orderId, providerPlatforms] of fetchedProviderPlatforms) {
            providerPlatformsByOrderId.set(orderId, providerPlatforms);
          }
        } catch (providerPlatformsError) {
          console.error(
            "Plan order provider platforms fetch error:",
            providerPlatformsError,
          );
          return errorResponse(
            "FETCH_ERROR",
            "Failed to fetch plan order provider platforms",
            500,
          );
        }

        for (const order of planOrders || []) {
          if (!order.subscription_id) continue;

          const statusInfo = order.order_statuses as unknown as {
            id: string;
            status_key: string;
            patient_status_label: string | null;
            patient_microcopy: string | null;
            patient_action_required: boolean;
            is_terminal: boolean;
            display_order: number;
          } | null;

          const transformedOrder = {
            id: order.id,
            order_number: order.order_number,
            status_id: order.status_id,
            cancellation_reason: order.cancellation_reason,
            status_details: statusInfo
              ? {
                id: statusInfo.id,
                key: statusInfo.status_key,
                label: statusInfo.patient_status_label ||
                  statusInfo.status_key
                    .replace(/_/g, " ")
                    .replace(/\b\w/g, (c: string) => c.toUpperCase()),
                description: statusInfo.patient_microcopy || null,
                action_required: statusInfo.patient_action_required,
                is_final: statusInfo.is_terminal,
                display_order: statusInfo.display_order,
              }
              : null,
            status_changed_at: order.status_changed_at,
            subtotal_cents: order.subtotal_cents,
            shipping_cents: order.shipping_cents,
            tax_cents: order.tax_cents,
            total_cents: order.total_cents,
            total_formatted: `$${(order.total_cents / 100).toFixed(2)}`,
            subscription_order_type: order.subscription_order_type,
            provider_platforms: providerPlatformsByOrderId.get(order.id) || [],
            tracking: order.tracking_number
              ? {
                number: order.tracking_number,
                url: order.tracking_url,
              }
              : null,
            shipped_at: order.shipped_at,
            delivered_at: order.delivered_at,
            cancelled_at: order.cancelled_at,
            paused_at: order.paused_at,
            created_at: order.created_at,
            updated_at: order.updated_at,
          };

          const existingOrders = ordersByPlanId.get(order.subscription_id) ||
            [];
          existingOrders.push(transformedOrder);
          ordersByPlanId.set(order.subscription_id, existingOrders);
        }
      }

      const transformedPlans = (plans || []).map((plan) => {
        const product = asSingle(
          plan.product as
            | {
              id: string;
              name: string;
              description: string | null;
              terms_and_conditions_html: string | null;
              price_cents: number;
              payment_type: string | null;
              subscription_interval: string | null;
              subscription_interval_count: number | null;
              subscription_renewal_lead_days: number;
              image_url: string | null;
            }
            | {
              id: string;
              name: string;
              description: string | null;
              terms_and_conditions_html: string | null;
              price_cents: number;
              payment_type: string | null;
              subscription_interval: string | null;
              subscription_interval_count: number | null;
              subscription_renewal_lead_days: number;
              image_url: string | null;
            }[]
            | null,
        );

        return {
          id: plan.id,
          status: plan.status,
          is_current: plan.status !== "cancelled",
          started_at: plan.started_at,
          renewal_at: plan.current_period_end_at,
          expires_at: plan.expires_at,
          paused_at: plan.paused_at,
          cancelled_at: plan.cancelled_at,
          cancellation_reason: plan.cancellation_reason,
          created_at: plan.created_at,
          updated_at: plan.updated_at,
          orders: ordersByPlanId.get(plan.id) || [],
          product: product
            ? {
              id: product.id,
              name: product.name,
              description: product.description,
              terms_and_conditions_html: product.terms_and_conditions_html,
              image_url: product.image_url,
              price_cents: product.price_cents,
              price_formatted: `$${(product.price_cents / 100).toFixed(2)}`,
              payment_type: product.payment_type,
              subscription_interval: product.subscription_interval,
              subscription_interval_count: product.subscription_interval_count,
              subscription_renewal_lead_days:
                product.subscription_renewal_lead_days,
            }
            : null,
        };
      });

      return jsonResponse({
        data: transformedPlans,
        meta: {
          total: transformedPlans.length,
          include_cancelled: includeCancelled,
        },
      });
    }

    // POST /plans/:id/refill-date - Update a patient's plan refill (renewal) date
    if (
      req.method === "POST" &&
      path.match(/^\/plans\/[a-f0-9-]+\/refill-date$/)
    ) {
      const planId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: {
        new_date?: string;
      } = {};

      try {
        const parsedBody = (await req.json()) as unknown;
        if (
          parsedBody &&
          typeof parsedBody === "object" &&
          !Array.isArray(parsedBody)
        ) {
          body = parsedBody as { new_date?: string };
        }
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const newDateRaw = typeof body.new_date === "string"
        ? body.new_date.trim()
        : "";
      if (!newDateRaw) {
        return errorResponse("MISSING_FIELDS", "new_date is required", 400);
      }

      const parsedRenewalDate = dateTime(newDateRaw).toDate();
      if (Number.isNaN(parsedRenewalDate.getTime())) {
        return errorResponse(
          "INVALID_DATE",
          "new_date must be a valid date",
          400,
        );
      }

      const normalizedRenewalAt = parsedRenewalDate.toISOString();

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select(
          `
          id,
          tenant_id,
          patient_id,
          status,
          started_at,
          expires_at,
          current_period_end_at,
          product:products (
            subscription_renewal_lead_days,
            renewal_advance_max_weeks,
            subscription_interval,
            subscription_interval_count
          )
        `,
        )
        .eq("id", planId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error(
          "Plan fetch error while updating refill date:",
          planError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status !== "active") {
        return errorResponse(
          "PLAN_NOT_ACTIVE",
          "Refill date can only be updated for active plans",
          400,
        );
      }

      const refillWindowError = validateRefillDateWindow(
        parsedRenewalDate,
        plan.current_period_end_at,
      );
      if (refillWindowError) return refillWindowError;

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while updating refill date:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      const updatePayload: {
        current_period_end_at: string;
      } = {
        current_period_end_at: normalizedRenewalAt,
      };

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", patient!.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while updating refill date:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        const targetRenewalUnix = Math.floor(
          parsedRenewalDate.getTime() / 1000,
        );
        const nowUnix = Math.floor(Date.now() / 1000);

        if (targetRenewalUnix < nowUnix) {
          return errorResponse(
            "INVALID_DATE",
            "new_date must be a future billing date for Stripe-linked plans",
            400,
          );
        }

        const updateParams = new URLSearchParams();
        // Keep renewal-date updates proration-free so Stripe does not create credits/refunds.
        updateParams.append("trial_end", String(targetRenewalUnix));
        updateParams.append(
          "proration_behavior",
          STRIPE_REFILL_DATE_PRORATION_BEHAVIOR,
        );
        updateParams.append("metadata[allia_refill_date]", normalizedRenewalAt);
        updateParams.append(
          "metadata[allia_refill_change_source]",
          "patient_refill_endpoint",
        );
        updateParams.append(
          "metadata[allia_refill_change_at]",
          dateTime().toISOString(),
        );
        updateParams.append(
          "metadata[last_refill_date_updated_by]",
          patient!.email || "unknown",
        );
        updateParams.append(
          "metadata[last_refill_date_updated_via]",
          "patient_portal",
        );

        const stripeUpdateResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: updateParams.toString(),
          },
        );

        if (!stripeUpdateResponse.ok) {
          const errorText = await stripeUpdateResponse.text();
          let stripeErrorMessage =
            "Failed to update Stripe subscription billing date";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error("Stripe subscription refill-date update error", {
            planId: plan.id,
            stripeSubscriptionId,
            status: stripeUpdateResponse.status,
            error: errorText,
            requestId,
          });

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        const stripeSubscription = (await stripeUpdateResponse.json()) as {
          current_period_end?: number;
        };
        const syncedRenewalAt =
          typeof stripeSubscription.current_period_end === "number"
            ? dateTime(stripeSubscription.current_period_end * 1000)
              .toDate()
              .toISOString()
            : normalizedRenewalAt;

        updatePayload.current_period_end_at = syncedRenewalAt;
      }

      const { data: updatedPlan, error: updateError } = await supabaseAdmin
        .from("subscriptions")
        .update(updatePayload)
        .eq("id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .select("id, status, current_period_end_at, expires_at, updated_at")
        .single();

      if (updateError) {
        console.error("Plan refill date update error:", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update plan refill date",
          500,
        );
      }

      return jsonResponse({
        message: "Plan refill date updated successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /admin/plans/:id/refill-date - Update a plan refill date from tenant admin UI (with Stripe sync)
    if (
      req.method === "POST" &&
      path.match(/^\/admin\/plans\/[a-f0-9-]+\/refill-date$/)
    ) {
      const planId = path.split("/")[3];

      const { admin, error: authError } = await getAuthenticatedAdmin();
      if (authError) return authError;
      if (!admin) {
        return errorResponse("FORBIDDEN", "Tenant admin access required", 403);
      }

      let body: {
        new_date?: string;
      } = {};

      try {
        const parsedBody = (await req.json()) as unknown;
        if (
          parsedBody &&
          typeof parsedBody === "object" &&
          !Array.isArray(parsedBody)
        ) {
          body = parsedBody as { new_date?: string };
        }
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const newDateRaw = typeof body.new_date === "string"
        ? body.new_date.trim()
        : "";
      if (!newDateRaw) {
        return errorResponse("MISSING_FIELDS", "new_date is required", 400);
      }

      const parsedRenewalDate = dateTime(newDateRaw).toDate();
      if (Number.isNaN(parsedRenewalDate.getTime())) {
        return errorResponse(
          "INVALID_DATE",
          "new_date must be a valid date",
          400,
        );
      }

      const normalizedRenewalAt = parsedRenewalDate.toISOString();

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select(
          `
          id,
          tenant_id,
          patient_id,
          product_id,
          status,
          started_at,
          expires_at,
          current_period_end_at,
          product:products (
            subscription_renewal_lead_days,
            renewal_advance_max_weeks,
            subscription_interval,
            subscription_interval_count
          )
        `,
        )
        .eq("id", planId)
        .maybeSingle();

      if (planError) {
        console.error(
          "Plan fetch error while admin is updating refill date:",
          planError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      const hasTenantAccess = admin.is_platform_superadmin ||
        admin.tenant_ids.includes(plan.tenant_id);

      if (!hasTenantAccess) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status !== "active") {
        return errorResponse(
          "PLAN_NOT_ACTIVE",
          "Refill date can only be updated for active plans",
          400,
        );
      }

      // PP-872: admins may move the renewal up to 15 days before the plan's
      // expiry date, but never past expiry (so the renewal always stays ahead
      // of coverage ending — no inverted renewal/expiry, no coverage gap). 15
      // (not 14) so the current renewal stays selectable on the standard
      // 15-day-lead products (renewal = expiry − 15).
      const refillWindowError = validateRefillDateWindow(
        parsedRenewalDate,
        plan.expires_at,
        "expiry date",
        15,
        0,
      );
      if (refillWindowError) return refillWindowError;

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", plan.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while admin is updating refill date:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      const updatePayload: {
        current_period_end_at: string;
      } = {
        current_period_end_at: normalizedRenewalAt,
      };

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", plan.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while admin is updating refill date:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        // Whether moving the refill date bills immediately. Picking today (or a
        // past date, already blocked by the window) sets trial_end=now and bills
        // right away; a future date just reschedules the next billing cycle.
        const targetRenewalUnix = Math.floor(
          parsedRenewalDate.getTime() / 1000,
        );
        const nowUnix = Math.floor(Date.now() / 1000);
        const billsNow = targetRenewalUnix <= nowUnix;

        // Double-charge guard: only relevant when this change bills immediately.
        // A future-dated refill just reschedules the next cycle and cannot stack
        // a second charge, so we skip the check (and the extra Stripe call). For
        // an immediate bill, bail if the subscription already has an open/unpaid
        // invoice in flight.
        if (billsNow) {
          const invoicesResponse = await fetch(
            `https://api.stripe.com/v1/invoices?subscription=${
              encodeURIComponent(stripeSubscriptionId)
            }&limit=10`,
            {
              method: "GET",
              headers: { Authorization: `Bearer ${stripeSecretKey}` },
            },
          );
          if (!invoicesResponse.ok) {
            const errorText = await invoicesResponse.text();
            console.error(
              "Stripe list-invoices error during admin refill-date",
              {
                planId: plan.id,
                stripeSubscriptionId,
                status: invoicesResponse.status,
                error: errorText,
                requestId,
                adminId: admin.id,
              },
            );
            return errorResponse(
              "STRIPE_ERROR",
              "Failed to check for existing invoices",
              500,
            );
          }
          const invoicesList = (await invoicesResponse.json()) as {
            data?: { status?: string }[];
          };
          const hasOpenInvoice = (invoicesList.data || []).some(
            (invoice) =>
              invoice.status !== "paid" &&
              invoice.status !== "void" &&
              invoice.status !== "uncollectible",
          );
          if (hasOpenInvoice) {
            return errorResponse(
              "RENEWAL_IN_PROGRESS",
              "A renewal is already being processed for this plan",
              409,
            );
          }
        }

        const updateParams = new URLSearchParams();
        // Move Stripe's next billing date to the chosen refill date so it
        // actually triggers a new payment cycle then (same trial_end mechanism
        // as the patient refill + resume endpoints). Picking today (or a past
        // date, already blocked by the window) bills now.
        updateParams.append(
          "trial_end",
          billsNow ? "now" : String(targetRenewalUnix),
        );
        // Keep this Stripe sync proration-free to avoid credits/refunds side effects.
        updateParams.append(
          "proration_behavior",
          STRIPE_REFILL_DATE_PRORATION_BEHAVIOR,
        );
        updateParams.append("metadata[allia_refill_date]", normalizedRenewalAt);
        updateParams.append(
          "metadata[allia_refill_change_source]",
          "tenant_admin_refill_endpoint",
        );
        updateParams.append(
          "metadata[allia_refill_change_at]",
          dateTime().toISOString(),
        );
        updateParams.append(
          "metadata[last_refill_date_updated_by]",
          admin.email || "unknown",
        );
        updateParams.append(
          "metadata[last_refill_date_updated_via]",
          "tenant_admin",
        );

        // Atomic double-charge guard: for an immediate bill, claim a single
        // in-flight "pending" attempt for this subscription BEFORE billing. A
        // partial unique index (one pending per subscription) makes a concurrent
        // second bill-now fail here instead of stacking another charge. This row
        // also feeds the reconciliation sweep. Future-dated refills don't bill
        // now, so they neither claim nor record.
        let attemptId: string | null = null;
        if (billsNow) {
          const { data: attemptRow, error: attemptInsertError } =
            await supabaseAdmin
              .from("renewal_trigger_attempts")
              .insert({
                tenant_id: plan.tenant_id,
                subscription_id: plan.id,
                provider_subscription_id: stripeSubscriptionId,
                triggered_by_email: admin.email || null,
                triggered_at: dateTime().toISOString(),
                status: "pending",
              })
              .select("id")
              .single();
          if (attemptInsertError) {
            if (attemptInsertError.code === "23505") {
              return errorResponse(
                "RENEWAL_IN_PROGRESS",
                "A renewal is already being processed for this plan",
                409,
              );
            }
            // Non-uniqueness failure: proceed without the lock (the open-invoice
            // check above is still a guard); reconciliation just won't track it.
            console.error("Failed to record renewal_trigger_attempts row", {
              planId: plan.id,
              requestId,
              error: attemptInsertError,
            });
          } else {
            attemptId = attemptRow?.id ?? null;
          }
        }

        const stripeUpdateResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: updateParams.toString(),
          },
        );

        if (!stripeUpdateResponse.ok) {
          const errorText = await stripeUpdateResponse.text();
          let stripeErrorMessage = "Failed to sync refill date with Stripe";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error(
            "Stripe subscription refill-date update error (admin)",
            {
              planId: plan.id,
              stripeSubscriptionId,
              status: stripeUpdateResponse.status,
              error: errorText,
              requestId,
              adminId: admin.id,
            },
          );

          // Billing didn't happen — release the claim so it doesn't block future
          // attempts or get flagged as a "charged but no order" by the sweep.
          if (attemptId) {
            await supabaseAdmin
              .from("renewal_trigger_attempts")
              .delete()
              .eq("id", attemptId);
          }

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        // Persist the renewal date Stripe actually applied (read it back rather
        // than trusting the requested value), matching the patient refill +
        // trigger endpoints. Falls back to the requested date if Stripe omits it.
        const stripeSubscription = (await stripeUpdateResponse.json()) as {
          current_period_end?: number;
        };
        if (typeof stripeSubscription.current_period_end === "number") {
          updatePayload.current_period_end_at = dateTime(
            stripeSubscription.current_period_end * 1000,
          )
            .toDate()
            .toISOString();
        }
        // The reconciliation attempt was already recorded before billing (the
        // atomic guard above); nothing else to record here.
      }

      const { data: updatedPlan, error: updateError } = await supabase
        .from("subscriptions")
        .update(updatePayload)
        .eq("id", plan.id)
        .eq("tenant_id", plan.tenant_id)
        .select("id, status, current_period_end_at, expires_at, updated_at")
        .single();

      if (updateError) {
        console.error("Plan refill date update error (admin):", updateError);
        return errorResponse(
          "UPDATE_ERROR",
          "Failed to update plan refill date",
          500,
        );
      }

      return jsonResponse({
        message: "Plan refill date updated successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /admin/plans/:id/pause - Pause an active plan from tenant admin UI (with Stripe sync)
    if (
      req.method === "POST" &&
      path.match(/^\/admin\/plans\/[a-f0-9-]+\/pause$/)
    ) {
      const planId = path.split("/")[3];

      const { admin, error: authError } = await getAuthenticatedAdmin();
      if (authError) return authError;
      if (!admin) {
        return errorResponse("FORBIDDEN", "Tenant admin access required", 403);
      }

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select("id, tenant_id, patient_id, status")
        .eq("id", planId)
        .maybeSingle();

      if (planError) {
        console.error(
          "Plan fetch error while admin is pausing plan:",
          planError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      const hasTenantAccess = admin.is_platform_superadmin ||
        admin.tenant_ids.includes(plan.tenant_id);

      if (!hasTenantAccess) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status !== "active") {
        return errorResponse(
          "PLAN_NOT_ACTIVE",
          "Only active plans can be paused",
          400,
        );
      }

      const pausedAt = dateTime().toISOString();

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", plan.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while admin is pausing plan:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", plan.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while admin is pausing plan:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        const pauseParams = new URLSearchParams();
        pauseParams.append("pause_collection[behavior]", "void");
        pauseParams.append("metadata[allia_plan_status]", "paused");
        pauseParams.append("metadata[allia_paused_at]", pausedAt);
        pauseParams.append(
          "metadata[allia_pause_source]",
          "tenant_admin_pause_endpoint",
        );
        pauseParams.append(
          "metadata[last_pause_updated_by]",
          admin.email || "unknown",
        );
        pauseParams.append("metadata[last_pause_updated_via]", "tenant_admin");

        const stripePauseResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: pauseParams.toString(),
          },
        );

        if (!stripePauseResponse.ok) {
          const errorText = await stripePauseResponse.text();
          let stripeErrorMessage = "Failed to sync plan pause with Stripe";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error("Stripe subscription pause error (admin)", {
            planId: plan.id,
            stripeSubscriptionId,
            status: stripePauseResponse.status,
            error: errorText,
            requestId,
            adminId: admin.id,
          });

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        await stripePauseResponse.json();
      }

      const { data: updatedPlan, error: updateError } = await supabase
        .from("subscriptions")
        .update({
          status: "paused",
          paused_at: pausedAt,
          current_period_end_at: null,
        })
        .eq("id", plan.id)
        .eq("tenant_id", plan.tenant_id)
        .select(
          "id, status, current_period_end_at, expires_at, paused_at, updated_at",
        )
        .single();

      if (updateError) {
        console.error("Plan pause update error (admin):", updateError);
        return errorResponse("UPDATE_ERROR", "Failed to pause plan", 500);
      }

      return jsonResponse({
        message: "Plan paused successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          paused_at: updatedPlan.paused_at,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /admin/plans/:id/resume - Resume a paused plan from tenant admin UI (with renewal-date recalculation + Stripe sync)
    if (
      req.method === "POST" &&
      path.match(/^\/admin\/plans\/[a-f0-9-]+\/resume$/)
    ) {
      const planId = path.split("/")[3];

      const { admin, error: authError } = await getAuthenticatedAdmin();
      if (authError) return authError;
      if (!admin) {
        return errorResponse("FORBIDDEN", "Tenant admin access required", 403);
      }

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select(
          `
          id,
          tenant_id,
          patient_id,
          status,
          expires_at,
          product:products (
            subscription_renewal_lead_days,
            renewal_advance_max_weeks,
            subscription_interval,
            subscription_interval_count
          )
        `,
        )
        .eq("id", planId)
        .maybeSingle();

      if (planError) {
        console.error(
          "Plan fetch error while admin is resuming plan:",
          planError,
        );
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      const hasTenantAccess = admin.is_platform_superadmin ||
        admin.tenant_ids.includes(plan.tenant_id);

      if (!hasTenantAccess) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status !== "paused") {
        return errorResponse(
          "PLAN_NOT_PAUSED",
          "Only paused plans can be resumed",
          400,
        );
      }

      if (!plan.expires_at) {
        return errorResponse(
          "PLAN_EXPIRATION_MISSING",
          "Plan expiration date is required to resume",
          400,
        );
      }

      const expiresAtDate = dateTime(plan.expires_at).toDate();
      if (Number.isNaN(expiresAtDate.getTime())) {
        return errorResponse(
          "INVALID_PLAN_EXPIRATION",
          "Plan expiration date is invalid",
          400,
        );
      }

      const product = plan.product as {
        subscription_renewal_lead_days?: number;
      } | null;
      const renewalLeadDays = Math.max(
        0,
        typeof product?.subscription_renewal_lead_days === "number"
          ? product.subscription_renewal_lead_days
          : 0,
      );

      const expirationMinusLead = dateTime(expiresAtDate).toDate();
      expirationMinusLead.setUTCDate(
        expirationMinusLead.getUTCDate() - renewalLeadDays,
      );

      const now = dateTime().toDate();
      const calculatedRenewalAtDate =
        expirationMinusLead.getTime() > now.getTime()
          ? expirationMinusLead
          : now;
      let renewalAt = calculatedRenewalAtDate.toISOString();

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", plan.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while admin is resuming plan:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", plan.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while admin is resuming plan:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        const targetRenewalUnix = Math.floor(
          dateTime(renewalAt).valueOf() / 1000,
        );
        const nowUnix = Math.floor(Date.now() / 1000);

        const resumeParams = new URLSearchParams();
        // Unpause Stripe collection and align the next billing date with the resumed renewal date.
        resumeParams.append("pause_collection", "");
        resumeParams.append(
          "proration_behavior",
          STRIPE_REFILL_DATE_PRORATION_BEHAVIOR,
        );
        if (targetRenewalUnix <= nowUnix) {
          resumeParams.append("trial_end", "now");
        } else {
          resumeParams.append("trial_end", String(targetRenewalUnix));
        }
        resumeParams.append("metadata[allia_plan_status]", "active");
        resumeParams.append(
          "metadata[allia_resumed_at]",
          dateTime().toISOString(),
        );
        resumeParams.append(
          "metadata[allia_resume_source]",
          "tenant_admin_resume_endpoint",
        );
        resumeParams.append("metadata[allia_resume_renewal_at]", renewalAt);
        resumeParams.append(
          "metadata[last_resume_updated_by]",
          admin.email || "unknown",
        );
        resumeParams.append(
          "metadata[last_resume_updated_via]",
          "tenant_admin",
        );

        const stripeResumeResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: resumeParams.toString(),
          },
        );

        if (!stripeResumeResponse.ok) {
          const errorText = await stripeResumeResponse.text();
          let stripeErrorMessage = "Failed to sync plan resume with Stripe";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error("Stripe subscription resume error (admin):", {
            planId: plan.id,
            stripeSubscriptionId,
            status: stripeResumeResponse.status,
            error: errorText,
            requestId,
            adminId: admin.id,
          });

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        const stripeSubscription = (await stripeResumeResponse.json()) as {
          current_period_end?: number;
        };

        if (typeof stripeSubscription.current_period_end === "number") {
          renewalAt = dateTime(stripeSubscription.current_period_end * 1000)
            .toDate()
            .toISOString();
        }
      }

      const { data: updatedPlan, error: updateError } = await supabase
        .from("subscriptions")
        .update({
          status: "active",
          paused_at: null,
          current_period_end_at: renewalAt,
        })
        .eq("id", plan.id)
        .eq("tenant_id", plan.tenant_id)
        .select(
          "id, status, current_period_end_at, expires_at, paused_at, updated_at",
        )
        .single();

      if (updateError) {
        console.error("Plan resume update error (admin):", updateError);
        return errorResponse("UPDATE_ERROR", "Failed to resume plan", 500);
      }

      return jsonResponse({
        message: "Plan resumed successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          paused_at: updatedPlan.paused_at,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /plans/:id/pause - Pause a patient's active plan
    if (req.method === "POST" && path.match(/^\/plans\/[a-f0-9-]+\/pause$/)) {
      const planId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select("id, tenant_id, patient_id, status")
        .eq("id", planId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error("Plan fetch error while pausing plan:", planError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status !== "active") {
        return errorResponse(
          "PLAN_NOT_ACTIVE",
          "Only active plans can be paused",
          400,
        );
      }

      const pausedAt = dateTime().toISOString();

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while pausing plan:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", patient!.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while pausing plan:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        const pauseParams = new URLSearchParams();
        pauseParams.append("pause_collection[behavior]", "void");
        pauseParams.append("metadata[allia_plan_status]", "paused");
        pauseParams.append("metadata[allia_paused_at]", pausedAt);
        pauseParams.append(
          "metadata[allia_pause_source]",
          "patient_pause_endpoint",
        );
        pauseParams.append(
          "metadata[last_pause_updated_by]",
          patient!.email || "unknown",
        );
        pauseParams.append(
          "metadata[last_pause_updated_via]",
          "patient_portal",
        );

        const stripePauseResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: pauseParams.toString(),
          },
        );

        if (!stripePauseResponse.ok) {
          const errorText = await stripePauseResponse.text();
          let stripeErrorMessage = "Failed to sync plan pause with Stripe";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error("Stripe subscription pause error:", {
            planId: plan.id,
            stripeSubscriptionId,
            status: stripePauseResponse.status,
            error: errorText,
            requestId,
            patientId: patient!.id,
          });

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        await stripePauseResponse.json();
      }

      const { data: updatedPlan, error: updateError } = await supabaseAdmin
        .from("subscriptions")
        .update({
          status: "paused",
          paused_at: pausedAt,
          current_period_end_at: null,
        })
        .eq("id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .select(
          "id, status, current_period_end_at, expires_at, paused_at, updated_at",
        )
        .single();

      if (updateError) {
        console.error("Plan pause update error:", updateError);
        return errorResponse("UPDATE_ERROR", "Failed to pause plan", 500);
      }

      return jsonResponse({
        message: "Plan paused successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          paused_at: updatedPlan.paused_at,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /plans/:id/resume - Resume a patient's paused plan
    if (req.method === "POST" && path.match(/^\/plans\/[a-f0-9-]+\/resume$/)) {
      const planId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select(
          `
          id,
          tenant_id,
          patient_id,
          status,
          expires_at,
          product:products (
            subscription_renewal_lead_days,
            renewal_advance_max_weeks,
            subscription_interval,
            subscription_interval_count
          )
        `,
        )
        .eq("id", planId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error("Plan fetch error while resuming plan:", planError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status !== "paused") {
        return errorResponse(
          "PLAN_NOT_PAUSED",
          "Only paused plans can be resumed",
          400,
        );
      }

      if (!plan.expires_at) {
        return errorResponse(
          "PLAN_EXPIRATION_MISSING",
          "Plan expiration date is required to resume",
          400,
        );
      }

      const expiresAtDate = dateTime(plan.expires_at).toDate();
      if (Number.isNaN(expiresAtDate.getTime())) {
        return errorResponse(
          "INVALID_PLAN_EXPIRATION",
          "Plan expiration date is invalid",
          400,
        );
      }

      const product = plan.product as {
        subscription_renewal_lead_days?: number;
      } | null;
      const renewalLeadDays = Math.max(
        0,
        typeof product?.subscription_renewal_lead_days === "number"
          ? product.subscription_renewal_lead_days
          : 0,
      );

      const expirationMinusLead = dateTime(expiresAtDate).toDate();
      expirationMinusLead.setUTCDate(
        expirationMinusLead.getUTCDate() - renewalLeadDays,
      );

      const now = dateTime().toDate();
      const calculatedRenewalAtDate =
        expirationMinusLead.getTime() > now.getTime()
          ? expirationMinusLead
          : now;
      let renewalAt = calculatedRenewalAtDate.toISOString();

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while resuming plan:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", patient!.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while resuming plan:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        const targetRenewalUnix = Math.floor(
          dateTime(renewalAt).valueOf() / 1000,
        );
        const nowUnix = Math.floor(Date.now() / 1000);

        const resumeParams = new URLSearchParams();
        // Unpause Stripe collection and align the next billing date with the resumed renewal date.
        resumeParams.append("pause_collection", "");
        resumeParams.append(
          "proration_behavior",
          STRIPE_REFILL_DATE_PRORATION_BEHAVIOR,
        );
        if (targetRenewalUnix <= nowUnix) {
          resumeParams.append("trial_end", "now");
        } else {
          resumeParams.append("trial_end", String(targetRenewalUnix));
        }
        resumeParams.append("metadata[allia_plan_status]", "active");
        resumeParams.append(
          "metadata[allia_resumed_at]",
          dateTime().toISOString(),
        );
        resumeParams.append(
          "metadata[allia_resume_source]",
          "patient_resume_endpoint",
        );
        resumeParams.append("metadata[allia_resume_renewal_at]", renewalAt);
        resumeParams.append(
          "metadata[last_resume_updated_by]",
          patient!.email || "unknown",
        );
        resumeParams.append(
          "metadata[last_resume_updated_via]",
          "patient_portal",
        );

        const stripeResumeResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: resumeParams.toString(),
          },
        );

        if (!stripeResumeResponse.ok) {
          const errorText = await stripeResumeResponse.text();
          let stripeErrorMessage = "Failed to sync plan resume with Stripe";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error("Stripe subscription resume error:", {
            planId: plan.id,
            stripeSubscriptionId,
            status: stripeResumeResponse.status,
            error: errorText,
            requestId,
            patientId: patient!.id,
          });

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        const stripeSubscription = (await stripeResumeResponse.json()) as {
          current_period_end?: number;
        };

        if (typeof stripeSubscription.current_period_end === "number") {
          renewalAt = dateTime(stripeSubscription.current_period_end * 1000)
            .toDate()
            .toISOString();
        }
      }

      const { data: updatedPlan, error: updateError } = await supabaseAdmin
        .from("subscriptions")
        .update({
          status: "active",
          paused_at: null,
          current_period_end_at: renewalAt,
        })
        .eq("id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .select(
          "id, status, current_period_end_at, expires_at, paused_at, updated_at",
        )
        .single();

      if (updateError) {
        console.error("Plan resume update error:", updateError);
        return errorResponse("UPDATE_ERROR", "Failed to resume plan", 500);
      }

      return jsonResponse({
        message: "Plan resumed successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          paused_at: updatedPlan.paused_at,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /plans/:id/reactivate - Reactivate a patient's order_pending_cancellation plan
    if (
      req.method === "POST" &&
      path.match(/^\/plans\/[a-f0-9-]+\/reactivate$/)
    ) {
      const planId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select(
          "id, tenant_id, patient_id, status, current_period_end_at, expires_at, paused_at, cancelled_at, cancellation_reason",
        )
        .eq("id", planId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error("Plan fetch error while reactivating plan:", planError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status === "active") {
        return jsonResponse({
          message: "Plan is already active",
          data: {
            id: plan.id,
            status: plan.status,
            renewal_at: plan.current_period_end_at,
            expires_at: plan.expires_at,
            paused_at: plan.paused_at,
            cancelled_at: plan.cancelled_at,
            cancellation_reason: plan.cancellation_reason,
          },
        });
      }

      if (plan.status !== "pending_cancellation") {
        return errorResponse(
          "PLAN_NOT_PENDING_CANCELLATION",
          "Only plans pending cancellation can be reactivated",
          400,
        );
      }

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error while reactivating plan:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const hasStripeLink = Boolean(stripeLink);
      const stripeSubscriptionId =
        stripeLink?.provider_subscription_id?.trim() || null;
      if (hasStripeLink && !stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      let syncedRenewalAt = plan.current_period_end_at;

      if (stripeSubscriptionId) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", patient!.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error(
            "Stripe provider fetch error while reactivating plan:",
            providerError,
          );
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        const stripeSecretKey = settings?.secret_key || null;
        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }

        const reactivationParams = new URLSearchParams();
        reactivationParams.append("cancel_at_period_end", "false");
        reactivationParams.append("metadata[allia_plan_status]", "active");
        reactivationParams.append(
          "metadata[allia_reactivated_at]",
          dateTime().toISOString(),
        );
        reactivationParams.append(
          "metadata[allia_reactivated_source]",
          "patient_reactivate_endpoint",
        );
        reactivationParams.append(
          "metadata[last_reactivation_updated_by]",
          patient!.email || "unknown",
        );
        reactivationParams.append(
          "metadata[last_reactivation_updated_via]",
          "patient_portal",
        );

        const stripeReactivateResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "POST",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: reactivationParams.toString(),
          },
        );

        if (!stripeReactivateResponse.ok) {
          const errorText = await stripeReactivateResponse.text();
          let stripeErrorMessage =
            "Failed to reactivate linked Stripe subscription";

          try {
            const parsedError = JSON.parse(errorText) as {
              error?: { message?: string };
            };
            stripeErrorMessage = parsedError.error?.message ||
              stripeErrorMessage;
          } catch {
            // Keep fallback message
          }

          console.error("Stripe subscription reactivation error:", {
            planId: plan.id,
            stripeSubscriptionId,
            status: stripeReactivateResponse.status,
            error: errorText,
            requestId,
            patientId: patient!.id,
          });

          return errorResponse("STRIPE_ERROR", stripeErrorMessage, 500);
        }

        const stripeSubscription = (await stripeReactivateResponse.json()) as {
          current_period_end?: number;
        };

        if (typeof stripeSubscription.current_period_end === "number") {
          syncedRenewalAt = dateTime(
            stripeSubscription.current_period_end * 1000,
          ).toISOString();
        }
      }

      const updatePayload: {
        status: "active";
        paused_at: null;
        cancelled_at: null;
        cancellation_reason: null;
        current_period_end_at?: string | null;
      } = {
        status: "active",
        paused_at: null,
        cancelled_at: null,
        cancellation_reason: null,
      };

      if (syncedRenewalAt !== undefined) {
        updatePayload.current_period_end_at = syncedRenewalAt;
      }

      const { data: updatedPlan, error: updateError } = await supabaseAdmin
        .from("subscriptions")
        .update(updatePayload)
        .eq("id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .select(
          "id, status, current_period_end_at, expires_at, paused_at, cancelled_at, cancellation_reason, updated_at",
        )
        .single();

      if (updateError) {
        console.error("Plan reactivation update error:", updateError);
        return errorResponse("UPDATE_ERROR", "Failed to reactivate plan", 500);
      }

      return jsonResponse({
        message: "Plan reactivated successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          paused_at: updatedPlan.paused_at,
          cancelled_at: updatedPlan.cancelled_at,
          cancellation_reason: updatedPlan.cancellation_reason,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // POST /plans/:id/payment-details - Create a Stripe Billing Portal session for payment details management
    if (
      req.method === "POST" &&
      path.match(/^\/plans\/[a-f0-9-]+\/payment-details$/)
    ) {
      const planId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: {
        return_url?: string;
      } = {};

      try {
        body = await req.json();
      } catch {
        // Body is optional
      }

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select("id, tenant_id, patient_id, status")
        .eq("id", planId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error("Plan fetch error:", planError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      if (plan.status === "cancelled") {
        return errorResponse(
          "PLAN_INACTIVE",
          "Cannot update payment details for a cancelled plan",
          400,
        );
      }

      const { data: stripeProvider, error: providerError } = await supabaseAdmin
        .from("tenant_payment_providers")
        .select(
          `
          id,
          settings,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("tenant_id", patient!.tenant_id)
        .eq("is_enabled", true)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (providerError || !stripeProvider) {
        console.error("Stripe provider fetch error:", providerError);
        return errorResponse(
          "NO_PAYMENT_PROVIDER",
          "No Stripe payment provider configured for this tenant",
          400,
        );
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
        return errorResponse(
          "PROVIDER_NOT_CONFIGURED",
          "Stripe secret key not configured",
          400,
        );
      }

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const stripeSubscriptionId = stripeLink?.provider_subscription_id || null;
      if (!stripeSubscriptionId) {
        return errorResponse(
          "PAYMENT_REFERENCE_MISSING",
          "Plan is missing Stripe subscription reference",
          400,
        );
      }

      const { data: patientPaymentContext, error: patientPaymentContextError } =
        await supabaseAdmin
          .from("patients")
          .select("metadata")
          .eq("id", patient!.id)
          .eq("tenant_id", patient!.tenant_id)
          .maybeSingle();

      if (patientPaymentContextError) {
        console.error(
          "Patient payment context fetch error:",
          patientPaymentContextError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient payment context",
          500,
        );
      }

      const patientMetadata = patientPaymentContext?.metadata &&
          typeof patientPaymentContext.metadata === "object" &&
          !Array.isArray(patientPaymentContext.metadata)
        ? (patientPaymentContext.metadata as Record<string, unknown>)
        : {};

      let stripeCustomerId =
        typeof patientMetadata.stripe_customer_id === "string"
          ? patientMetadata.stripe_customer_id.trim()
          : "";

      if (!stripeCustomerId) {
        const stripeSubscriptionResponse = await fetch(
          `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
          {
            method: "GET",
            headers: {
              Authorization: `Bearer ${stripeSecretKey}`,
            },
          },
        );

        if (!stripeSubscriptionResponse.ok) {
          const errorText = await stripeSubscriptionResponse.text();
          console.error(
            "Stripe subscription fetch error while creating payment details session",
            {
              status: stripeSubscriptionResponse.status,
              subscriptionId: stripeSubscriptionId,
              error: errorText,
            },
          );
          return errorResponse(
            "STRIPE_ERROR",
            "Failed to fetch Stripe subscription",
            500,
          );
        }

        const stripeSubscription =
          (await stripeSubscriptionResponse.json()) as {
            customer?: string | { id?: string | null } | null;
          };
        const subscriptionCustomerId =
          typeof stripeSubscription.customer === "string"
            ? stripeSubscription.customer
            : stripeSubscription.customer?.id || "";

        if (subscriptionCustomerId) {
          stripeCustomerId = subscriptionCustomerId;

          const { error: patientMetadataUpdateError } = await supabaseAdmin
            .from("patients")
            .update({
              metadata: {
                ...patientMetadata,
                stripe_customer_id: subscriptionCustomerId,
              },
            })
            .eq("id", patient!.id)
            .eq("tenant_id", patient!.tenant_id);

          if (patientMetadataUpdateError) {
            console.warn(
              "Failed to persist Stripe customer ID to patient metadata",
              {
                patientId: patient!.id,
                tenantId: patient!.tenant_id,
                error: patientMetadataUpdateError.message,
              },
            );
          }
        }
      }

      if (!stripeCustomerId) {
        return errorResponse(
          "PAYMENT_CUSTOMER_NOT_FOUND",
          "Stripe customer reference not found for this plan",
          404,
        );
      }

      const originHeader = req.headers.get("origin");
      const refererHeader = req.headers.get("referer");
      let baseUrl: string;

      if (originHeader) {
        baseUrl = originHeader;
      } else if (refererHeader) {
        try {
          const refererUrl = new URL(refererHeader);
          baseUrl = refererUrl.origin;
        } catch {
          baseUrl = url.origin;
        }
      } else {
        baseUrl = url.origin;
      }

      const returnUrlRaw = typeof body.return_url === "string"
        ? body.return_url.trim()
        : "";
      const returnUrl = returnUrlRaw || `${baseUrl}/plans/${plan.id}`;

      let parsedReturnUrl: URL;
      try {
        parsedReturnUrl = new URL(returnUrl);
      } catch {
        return errorResponse(
          "INVALID_RETURN_URL",
          "return_url must be an absolute URL",
          400,
        );
      }

      if (!["http:", "https:"].includes(parsedReturnUrl.protocol)) {
        return errorResponse(
          "INVALID_RETURN_URL",
          "return_url must use http or https",
          400,
        );
      }

      const {
        configurationId: stripePortalConfigurationId,
        error: stripePortalConfigurationError,
      } = await ensureStripePaymentDetailsPortalConfiguration({
        tenantId: patient!.tenant_id,
        tenantPaymentProviderId: stripeProvider.id,
        stripeSecretKey,
        settings,
      });

      if (stripePortalConfigurationError) {
        return stripePortalConfigurationError;
      }

      if (!stripePortalConfigurationId) {
        return errorResponse(
          "STRIPE_ERROR",
          "Stripe billing portal configuration is unavailable",
          500,
        );
      }

      const portalParams = new URLSearchParams();
      portalParams.append("customer", stripeCustomerId);
      portalParams.append("configuration", stripePortalConfigurationId);
      portalParams.append("return_url", parsedReturnUrl.toString());

      const stripePortalResponse = await fetch(
        "https://api.stripe.com/v1/billing_portal/sessions",
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${stripeSecretKey}`,
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: portalParams.toString(),
        },
      );

      if (!stripePortalResponse.ok) {
        const errorText = await stripePortalResponse.text();
        let errorMessage = "Failed to create Stripe billing portal session";

        try {
          const parsedError = JSON.parse(errorText) as {
            error?: { message?: string };
          };
          errorMessage = parsedError.error?.message || errorMessage;
        } catch {
          // Keep generic fallback message
        }

        console.error("Stripe billing portal session creation error", {
          status: stripePortalResponse.status,
          customerId: stripeCustomerId,
          subscriptionId: stripeSubscriptionId,
          error: errorText,
        });

        return errorResponse("STRIPE_ERROR", errorMessage, 500);
      }

      const portalSession = (await stripePortalResponse.json()) as {
        id?: string;
        url?: string;
        return_url?: string;
      };

      if (!portalSession.id || !portalSession.url) {
        return errorResponse(
          "STRIPE_ERROR",
          "Stripe returned an invalid billing portal session response",
          500,
        );
      }

      return jsonResponse({
        message: "Payment details session created",
        data: {
          plan_id: plan.id,
          session_id: portalSession.id,
          portal_url: portalSession.url,
          return_url: portalSession.return_url || parsedReturnUrl.toString(),
        },
      });
    }

    // POST /plans/:id/cancel - Cancel a patient's plan
    if (req.method === "POST" && path.match(/^\/plans\/[a-f0-9-]+\/cancel$/)) {
      const planId = path.split("/")[2];

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (authError) return authError;

      let body: {
        reason?: string;
      };

      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const reasonRaw = body.reason?.trim();

      if (!reasonRaw) {
        return errorResponse("MISSING_FIELDS", "reason is required", 400);
      }

      const { data: plan, error: planError } = await supabaseAdmin
        .from("subscriptions")
        .select(
          "id, tenant_id, patient_id, status, current_period_end_at, expires_at, cancelled_at, cancellation_reason",
        )
        .eq("id", planId)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .maybeSingle();

      if (planError) {
        console.error("Plan fetch error:", planError);
        return errorResponse("FETCH_ERROR", "Failed to fetch plan", 500);
      }

      if (!plan) {
        return errorResponse("PLAN_NOT_FOUND", "Plan not found", 404);
      }

      const now = dateTime().toDate();
      const nowTime = now.getTime();
      const expiresAtTime = plan.expires_at
        ? dateTime(plan.expires_at).valueOf()
        : Number.NaN;
      const hasValidExpiration = Number.isFinite(expiresAtTime);
      const isPastExpiration = !hasValidExpiration || nowTime >= expiresAtTime;
      const targetStatus = isPastExpiration
        ? "cancelled"
        : "pending_cancellation";
      const cancelledAt = targetStatus === "cancelled"
        ? now.toISOString()
        : null;

      if (plan.status === "cancelled") {
        return jsonResponse({
          message: "Plan is already cancelled",
          data: {
            id: plan.id,
            status: plan.status,
            renewal_at: plan.current_period_end_at,
            expires_at: plan.expires_at,
            cancelled_at: plan.cancelled_at,
            cancellation_reason: plan.cancellation_reason,
            orders_cancelled_count: 0,
          },
        });
      }

      if (plan.status === "pending_cancellation" && !isPastExpiration) {
        return jsonResponse({
          message: "Plan is already pending cancellation",
          data: {
            id: plan.id,
            status: plan.status,
            renewal_at: plan.current_period_end_at,
            expires_at: plan.expires_at,
            cancelled_at: plan.cancelled_at,
            cancellation_reason: plan.cancellation_reason,
            orders_cancelled_count: 0,
          },
        });
      }

      const { data: stripeLink, error: stripeLinkError } = await supabaseAdmin
        .from("subscription_payment_provider_links")
        .select(
          `
          provider_subscription_id,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("subscription_id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("payment_providers.key", "stripe")
        .maybeSingle();

      if (stripeLinkError) {
        console.error(
          "Plan payment provider link fetch error:",
          stripeLinkError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch plan payment provider link",
          500,
        );
      }

      const stripeSubscriptionId = stripeLink?.provider_subscription_id || null;
      const needsStripeProvider = Boolean(stripeSubscriptionId);
      let stripeSecretKey: string | null = null;

      if (needsStripeProvider) {
        const { data: stripeProvider, error: providerError } =
          await supabaseAdmin
            .from("tenant_payment_providers")
            .select(
              `
            settings,
            payment_providers!inner (
              key
            )
          `,
            )
            .eq("tenant_id", patient!.tenant_id)
            .eq("is_enabled", true)
            .eq("payment_providers.key", "stripe")
            .maybeSingle();

        if (providerError || !stripeProvider) {
          console.error("Stripe provider fetch error:", providerError);
          return errorResponse(
            "NO_PAYMENT_PROVIDER",
            "No Stripe payment provider configured for this tenant",
            400,
          );
        }

        const settings = stripeProvider.settings as Record<string, string>;
        stripeSecretKey = settings?.secret_key || null;

        if (!stripeSecretKey) {
          return errorResponse(
            "PROVIDER_NOT_CONFIGURED",
            "Stripe secret key not configured",
            400,
          );
        }
      }

      if (stripeSubscriptionId) {
        if (targetStatus === "pending_cancellation") {
          const stripeCancelAtPeriodEndParams = new URLSearchParams();
          stripeCancelAtPeriodEndParams.append("cancel_at_period_end", "true");
          stripeCancelAtPeriodEndParams.append(
            "metadata[allia_plan_status]",
            "pending_cancellation",
          );
          stripeCancelAtPeriodEndParams.append(
            "metadata[allia_pending_cancellation_at]",
            now.toISOString(),
          );
          stripeCancelAtPeriodEndParams.append(
            "metadata[allia_pending_cancellation_source]",
            "patient_cancel_endpoint",
          );

          const stripeCancelAtPeriodEndResponse = await fetch(
            `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
            {
              method: "POST",
              headers: {
                Authorization: `Bearer ${stripeSecretKey!}`,
                "Content-Type": "application/x-www-form-urlencoded",
              },
              body: stripeCancelAtPeriodEndParams.toString(),
            },
          );

          if (!stripeCancelAtPeriodEndResponse.ok) {
            const errorText = await stripeCancelAtPeriodEndResponse.text();
            console.error("Stripe pending cancellation sync error:", {
              status: stripeCancelAtPeriodEndResponse.status,
              subscriptionId: stripeSubscriptionId,
              error: errorText,
            });
            return errorResponse(
              "STRIPE_ERROR",
              "Failed to cancel Stripe auto-renewal for this plan",
              500,
            );
          }

          await stripeCancelAtPeriodEndResponse.json();
        } else {
          const stripeCancelResponse = await fetch(
            `https://api.stripe.com/v1/subscriptions/${stripeSubscriptionId}`,
            {
              method: "DELETE",
              headers: {
                Authorization: `Bearer ${stripeSecretKey!}`,
              },
            },
          );

          if (!stripeCancelResponse.ok) {
            const errorText = await stripeCancelResponse.text();
            console.error("Stripe plan cancellation error:", {
              status: stripeCancelResponse.status,
              subscriptionId: stripeSubscriptionId,
              error: errorText,
            });
            return errorResponse(
              "STRIPE_ERROR",
              "Failed to cancel linked Stripe plan",
              500,
            );
          }
        }
      }

      const { data: updatedPlan, error: updateError } = await supabaseAdmin
        .from("subscriptions")
        .update({
          status: targetStatus,
          cancelled_at: cancelledAt,
          cancellation_reason: reasonRaw,
        })
        .eq("id", plan.id)
        .eq("tenant_id", patient!.tenant_id)
        .eq("patient_id", patient!.id)
        .select(
          "id, status, current_period_end_at, expires_at, cancelled_at, cancellation_reason, updated_at",
        )
        .single();

      if (updateError) {
        if (
          updateError.code === "22P02" &&
          targetStatus === "pending_cancellation"
        ) {
          console.error(
            "Plan update failed because pending_cancellation is missing from subscription_status enum",
            {
              requestId,
              planId: plan.id,
              tenantId: patient!.tenant_id,
              error: updateError,
            },
          );
          return errorResponse(
            "SCHEMA_MISMATCH",
            "Database subscription_status enum is inconsistent. Apply the pending_cancellation enum migrations, including normalization if needed.",
            500,
          );
        }
        console.error("Plan update error:", updateError);
        return errorResponse("UPDATE_ERROR", "Failed to cancel plan", 500);
      }

      // --- Cancel the linked in-flight order(s), fulfillment-stage-gated -----
      // Cancelling a plan must also cancel any order on it that has NOT yet been
      // sent to the pharmacy (pre-fulfillment), and propagate that to the
      // provider. Orders already sent to pharmacy / shipped are left alone — the
      // Stripe cancel above already stops the renewal; the dispensed order stands.
      // Delivered/terminal orders are skipped. We queue eligible orders to
      // `order_pending_cancellation` and let order-lifecycle (cancel-helper) do
      // the provider cancellation + refund evaluation, mirroring /orders/:id/cancel.
      let ordersCancelledCount = 0;
      try {
        const { data: pendingCancelStatus } = await supabaseAdmin
          .from("order_statuses")
          .select("id")
          .eq("status_key", "order_pending_cancellation")
          .eq("is_active", true)
          .maybeSingle();

        // `order_sent_to_pharmacy` is the pre/post-fulfillment cutoff.
        const { data: sentToPharmacyStatus } = await supabaseAdmin
          .from("order_statuses")
          .select("display_order")
          .eq("status_key", "order_sent_to_pharmacy")
          .maybeSingle();
        const fulfillmentCutoff =
          typeof sentToPharmacyStatus?.display_order === "number"
            ? sentToPharmacyStatus.display_order
            : null;

        if (pendingCancelStatus?.id && fulfillmentCutoff !== null) {
          const { data: linkedOrders } = await supabaseAdmin
            .from("orders")
            .select(
              "id, cancelled_at, provider_platform_integration_key, order_statuses!inner ( id, status_key, display_order, is_terminal )",
            )
            .eq("tenant_id", patient!.tenant_id)
            .eq("patient_id", patient!.id)
            .eq("subscription_id", plan.id);

          for (const o of linkedOrders ?? []) {
            const s = Array.isArray(o.order_statuses)
              ? o.order_statuses[0]
              : o.order_statuses;
            if (!s || o.cancelled_at) continue;
            // Skip terminal, already-cancelling, and post-fulfillment orders.
            if (s.is_terminal === true) continue;
            if (
              s.status_key === "order_pending_cancellation" ||
              s.status_key === "order_cancellation_processing"
            ) continue;
            if (
              typeof s.display_order === "number" &&
              s.display_order >= fulfillmentCutoff
            ) {
              continue; // post-fulfillment: renewal-only, leave the order.
            }

            let providerLinkMetadata: TelegraProviderReviewMetadata[] = [];
            if (
              isTelegraProviderIntegrationKey(
                o.provider_platform_integration_key,
              )
            ) {
              providerLinkMetadata = await fetchOrderProviderLinkMetadata({
                supabase: supabaseAdmin,
                orderId: o.id,
                tenantId: patient!.tenant_id,
                providerPlatformIntegrationKey:
                  o.provider_platform_integration_key,
              });
            }

            const shouldDeferForTelegraProviderReview =
              shouldDeferTelegraProviderReviewCancellation({
                currentStatusKey: s.status_key,
                providerPlatformIntegrationKey:
                  o.provider_platform_integration_key,
                providerLinkMetadata,
              });

            if (shouldDeferForTelegraProviderReview) {
              const { error: deferError } = await supabaseAdmin
                .from("orders")
                .update({
                  cancellation_reason: reasonRaw,
                })
                .eq("id", o.id)
                .eq("tenant_id", patient!.tenant_id)
                .eq("patient_id", patient!.id);

              if (deferError) {
                console.error(
                  "Plan cancel: failed to defer Telegra provider review order cancellation",
                  {
                    requestId,
                    planId: plan.id,
                    orderId: o.id,
                    error: deferError.message,
                  },
                );
                continue;
              }

              await supabaseAdmin.from("order_status_history").insert({
                order_id: o.id,
                status_id: s.id,
                notes:
                  "Plan cancellation requested while Telegra order is in provider review; order cancellation will resume after provider decision.",
              });

              ordersCancelledCount += 1;
              continue;
            }

            const { error: queueError } = await supabaseAdmin
              .from("orders")
              .update({
                status_id: pendingCancelStatus.id,
                status_changed_at: dateTime().toISOString(),
                cancellation_reason: reasonRaw,
              })
              .eq("id", o.id)
              .eq("tenant_id", patient!.tenant_id)
              .eq("patient_id", patient!.id);

            if (queueError) {
              console.error("Plan cancel: failed to queue order cancellation", {
                requestId,
                planId: plan.id,
                orderId: o.id,
                error: queueError.message,
              });
              continue;
            }

            await supabaseAdmin.from("order_status_history").insert({
              order_id: o.id,
              status_id: pendingCancelStatus.id,
              notes:
                "Order cancellation queued because the patient cancelled the plan (pre-fulfillment).",
            });

            // Let order-lifecycle process the cancellation (provider + refund).
            await triggerOrderLifecycleForOrder(o.id, patient!.tenant_id);
            ordersCancelledCount += 1;
          }
        }
      } catch (orderCancelError) {
        // Best-effort: plan cancellation already succeeded; do not fail it if the
        // order-cancellation fan-out hits an error.
        console.error("Plan cancel: order cancellation fan-out failed", {
          requestId,
          planId: plan.id,
          error: orderCancelError instanceof Error
            ? orderCancelError.message
            : String(orderCancelError),
        });
      }

      return jsonResponse({
        message: targetStatus === "cancelled"
          ? "Plan cancelled successfully"
          : "Plan cancellation scheduled successfully",
        data: {
          id: updatedPlan.id,
          status: updatedPlan.status,
          renewal_at: updatedPlan.current_period_end_at,
          expires_at: updatedPlan.expires_at,
          cancelled_at: updatedPlan.cancelled_at,
          cancellation_reason: updatedPlan.cancellation_reason,
          orders_cancelled_count: ordersCancelledCount,
          updated_at: updatedPlan.updated_at,
        },
      });
    }

    // ==================== ORDER STATUSES ENDPOINT ====================

    // GET /order-statuses - List all active order statuses (public endpoint)
    if (req.method === "GET" && path === "/order-statuses") {
      // This is a public endpoint - no authentication required
      // Returns only patient-visible status information for UI display

      const { data: statuses, error: statusError } = await supabase
        .from("order_statuses")
        .select(
          `
          id,
          status_key,
          patient_status_label,
          patient_microcopy,
          patient_action_required,
          is_terminal,
          display_order
        `,
        )
        .eq("is_active", true)
        .eq("is_patient_visible", true)
        .order("display_order", { ascending: true });

      if (statusError) {
        console.error("Order statuses fetch error:", statusError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch order statuses",
          500,
        );
      }

      // Transform to patient-friendly format
      const patientStatuses = statuses?.map((status) => ({
        id: status.id,
        key: status.status_key,
        label: status.patient_status_label ||
          status.status_key
            .replace(/_/g, " ")
            .replace(/\b\w/g, (c: string) => c.toUpperCase()),
        description: status.patient_microcopy || null,
        action_required: status.patient_action_required,
        is_final: status.is_terminal,
        display_order: status.display_order,
      })) || [];

      console.info("Order statuses fetched", {
        count: patientStatuses.length,
      });

      return jsonResponse({
        data: patientStatuses,
      });
    }

    // 404 for unknown routes
    return errorResponse(
      "NOT_FOUND",
      `Endpoint ${req.method} ${path} not found`,
      404,
    );
  } catch (error) {
    console.error("Plan API Error:", error);
    return errorResponse(
      "INTERNAL_ERROR",
      error instanceof Error ? error.message : "An unexpected error occurred",
      500,
    );
  }
});
