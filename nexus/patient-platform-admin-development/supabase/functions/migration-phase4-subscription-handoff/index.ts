/**
 * Migration Phase 4 — Same-account Stripe subscription handoff
 *
 * Brello WC -> Brello PP stays in the same Stripe account. This function
 * validates existing Stripe customers/payment methods and can create the new
 * PP-managed Stripe subscription when explicitly run in live mode.
 *
 * Security: Requires X-Migration-API-Key matching MIGRATION_API_KEY.
 */

import { createClient, type SupabaseClient } from "jsr:@supabase/supabase-js@2";
import {
  blockWooCommerceRenewal,
  type WooRenewalBlockResult,
} from "./woo-renewal-block.ts";

type JsonRecord = Record<string, unknown>;
type SupabaseAdmin = SupabaseClient<any, "public", any>;

interface HandoffRequest {
  emails?: string[];
  patient_ids?: string[];
  subscription_ids?: string[];
  dry_run?: boolean;
  confirm_live?: boolean;
  woo_renewal_blocking_confirmed?: boolean;
  allow_missing_woo_subscription_id?: boolean;
  tenant_slug?: string;
  currency?: string;
  price_overrides?: Record<string, string>;
  // Separate, additional opt-in from woo_renewal_blocking_confirmed below.
  // That flag is a human assertion recorded into metadata; this flag makes
  // the function actually call the WooCommerce REST API to change the
  // subscription status once the Stripe handoff succeeds. Both must be
  // explicitly set - this never fires as a side effect of the existing flag.
  execute_woo_renewal_block?: boolean;
  // Required whenever execute_woo_renewal_block is true. No default on
  // purpose - the target status is exactly what Jaime/the WC owner needs to
  // confirm before this can run for real (see PP-532). WooCommerce
  // Subscriptions' own "pending-cancel" status is the closest semantic match
  // for "let the current period finish, never renew again" without ending
  // access immediately, but that is a guess pending confirmation, not a
  // default this function should choose on its own.
  woo_renewal_block_target_status?: string;
  // Skip Stripe entirely for $0/free subscriptions (e.g. CareLink internal
  // users). No payment method, no billing anchor, no Stripe subscription is
  // created. WC renewal blocking still applies. Requires the subscription to
  // already exist in the target tenant (run Step 1 for that tenant first).
  free_handoff?: boolean;
}

interface PatientRow {
  id: string;
  email: string | null;
  metadata: JsonRecord | null;
}

interface ProductRow {
  id: string;
  name: string;
  sku: string | null;
  price_cents: number;
  payment_type: string | null;
  subscription_interval: string | null;
  subscription_interval_count: number | null;
  metadata: JsonRecord | null;
}

interface SubscriptionRow {
  id: string;
  tenant_id: string;
  patient_id: string;
  product_id: string | null;
  status: string;
  current_period_end_at: string | null;
  expires_at: string | null;
  cancelled_at: string | null;
  stripe_subscription_id: string | null;
  metadata: JsonRecord | null;
  patient: PatientRow | null;
  product: ProductRow | null;
}

interface StripeProviderContext {
  paymentProviderId: string;
  tenantPaymentProviderId: string;
  secretKey: string;
}

interface HandoffResult {
  subscription_id: string;
  patient_id: string;
  email: string | null;
  woo_subscription_id: string | null;
  stripe_customer_id: string | null;
  product_id: string | null;
  status: "eligible" | "created" | "skipped" | "blocked" | "failed";
  reason: string | null;
  dry_run: boolean;
  existing_stripe_subscription_id: string | null;
  created_stripe_subscription_id: string | null;
  default_payment_method_id: string | null;
  billing_cycle_anchor: string | null;
  price_mode: "override" | "inline_price_data" | null;
  stripe_price_id: string | null;
  woo_renewal_block: WooRenewalBlockResult | null;
}

interface PatientHandoffContext {
  stripeCustomerId: string | null;
  billingAnchorIso: string | null;
  wooSubscriptionId: string | null;
}

const MIGRATABLE_SUBSCRIPTION_STATUSES = new Set(["active"]);

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body, null, 2), {
    status,
    headers: {
      "Content-Type": "application/json",
    },
  });
}

function normalizeList(values: unknown): string[] {
  if (!Array.isArray(values)) return [];
  return values
    .map((value) => String(value ?? "").trim())
    .filter((value) => value.length > 0);
}

function asRecord(value: unknown): JsonRecord {
  return value && typeof value === "object" && !Array.isArray(value)
    ? value as JsonRecord
    : {};
}

function asString(value: unknown): string | null {
  if (value === undefined || value === null) return null;
  const normalized = String(value).trim();
  return normalized === "" ? null : normalized;
}

function unixSecondsFromIso(value: string | null): number | null {
  if (!value) return null;
  const timestamp = Date.parse(value);
  if (Number.isNaN(timestamp)) return null;
  return Math.floor(timestamp / 1000);
}

function isFutureIso(value: string | null): boolean {
  const timestamp = unixSecondsFromIso(value);
  return Boolean(timestamp && timestamp > Math.floor(Date.now() / 1000));
}

function resolveStripeCustomerId(subscription: SubscriptionRow): string | null {
  const metadata = asRecord(subscription.metadata);
  const sourceBilling = asRecord(metadata.source_billing);
  const migrationPhase2 = asRecord(metadata.migration_phase_2);
  const patientMetadata = asRecord(subscription.patient?.metadata);

  return asString(sourceBilling.stripe_customer_id) ??
    asString(migrationPhase2.stripe_customer_id) ??
    asString(metadata.stripe_customer_id) ??
    asString(patientMetadata.stripe_customer_id);
}

function resolveWooSubscriptionId(
  subscription: SubscriptionRow,
): string | null {
  const metadata = asRecord(subscription.metadata);
  const sourceBilling = asRecord(metadata.source_billing);

  return asString(sourceBilling.woo_subscription_id) ??
    asString(metadata.woo_subscription_id);
}

function resolveBillingAnchor(subscription: SubscriptionRow): string | null {
  const metadata = asRecord(subscription.metadata);
  const sourceBilling = asRecord(metadata.source_billing);
  return asString(sourceBilling.next_payment_at) ??
    subscription.current_period_end_at ??
    subscription.expires_at;
}

function buildPatientHandoffContexts(
  subscriptions: SubscriptionRow[],
): Map<string, PatientHandoffContext> {
  const grouped = new Map<string, SubscriptionRow[]>();
  for (const subscription of subscriptions) {
    const rows = grouped.get(subscription.patient_id) ?? [];
    rows.push(subscription);
    grouped.set(subscription.patient_id, rows);
  }

  const contexts = new Map<string, PatientHandoffContext>();
  for (const [patientId, rows] of grouped.entries()) {
    const activeRows = rows.filter((row) =>
      MIGRATABLE_SUBSCRIPTION_STATUSES.has(row.status)
    );
    const stripeCustomerId = rows
      .map((row) => resolveStripeCustomerId(row))
      .find((value) => value !== null) ?? null;

    const billingAnchorIso = activeRows
      .map((row) => resolveBillingAnchor(row))
      .find((value) => isFutureIso(value)) ?? null;

    const wooSubscriptionId = activeRows
      .map((row) => resolveWooSubscriptionId(row))
      .find((value) => value !== null) ?? null;

    contexts.set(patientId, {
      stripeCustomerId,
      billingAnchorIso,
      wooSubscriptionId,
    });
  }

  return contexts;
}

function resolvePriceOverride(
  subscription: SubscriptionRow,
  priceOverrides: Record<string, string>,
): string | null {
  const product = subscription.product;
  const keys = [
    subscription.id,
    subscription.product_id,
    product?.id,
    product?.sku,
  ].filter(Boolean) as string[];

  for (const key of keys) {
    const value = asString(priceOverrides[key]);
    if (value) return value;
  }

  return null;
}

async function getStripeProviderContext(
  supabase: SupabaseAdmin,
  tenantId: string,
): Promise<StripeProviderContext> {
  const { data, error } = await supabase
    .from("tenant_payment_providers")
    .select(
      "id, payment_provider_id, settings, payment_providers!inner(id, key)",
    )
    .eq("tenant_id", tenantId)
    .eq("payment_providers.key", "stripe")
    .eq("is_enabled", true)
    .maybeSingle();

  if (error || !data) {
    throw new Error(
      `Stripe tenant payment provider not found: ${
        error?.message ?? "missing"
      }`,
    );
  }

  const settings = asRecord(data.settings);
  const secretKey = asString(settings.secret_key);
  const paymentProviderRelation = Array.isArray(data.payment_providers)
    ? data.payment_providers[0]
    : data.payment_providers;
  const paymentProviderId = asString(paymentProviderRelation?.id);

  if (!secretKey || !paymentProviderId) {
    throw new Error("Stripe provider is missing secret_key or provider id");
  }

  return {
    paymentProviderId,
    tenantPaymentProviderId: data.id,
    secretKey,
  };
}

async function stripeRequest<T>(
  secretKey: string,
  method: "GET" | "POST",
  path: string,
  body?: URLSearchParams,
  idempotencyKey?: string,
): Promise<T> {
  const headers: Record<string, string> = {
    Authorization: `Bearer ${secretKey}`,
  };
  if (method === "POST") {
    headers["Content-Type"] = "application/x-www-form-urlencoded";
  }
  if (idempotencyKey) {
    headers["Idempotency-Key"] = idempotencyKey;
  }

  const response = await fetch(`https://api.stripe.com/v1${path}`, {
    method,
    headers,
    body: method === "POST" ? body?.toString() : undefined,
  });

  if (!response.ok) {
    let errorCode = "stripe_request_failed";
    let errorType = "unknown";
    try {
      const payload = await response.json();
      const stripeError = asRecord(payload?.error);
      errorCode = asString(stripeError.code) ?? errorCode;
      errorType = asString(stripeError.type) ?? errorType;
    } catch {
      // Keep the public error generic if Stripe does not return JSON.
    }
    throw new Error(
      `Stripe ${method} ${path} failed: ${errorCode} (${errorType})`,
    );
  }

  return await response.json() as T;
}

async function getReusablePaymentMethodId(
  secretKey: string,
  customerId: string,
): Promise<string | null> {
  const customer = await stripeRequest<{
    id: string;
    default_source?: string | { id?: string } | null;
    invoice_settings?: {
      default_payment_method?: string | { id?: string } | null;
    };
  }>(
    secretKey,
    "GET",
    `/customers/${
      encodeURIComponent(customerId)
    }?expand[]=invoice_settings.default_payment_method`,
  );

  const invoicePaymentMethod = customer.invoice_settings
    ?.default_payment_method;
  if (typeof invoicePaymentMethod === "string" && invoicePaymentMethod) {
    return invoicePaymentMethod;
  }
  if (
    invoicePaymentMethod &&
    typeof invoicePaymentMethod === "object" &&
    invoicePaymentMethod.id
  ) {
    return invoicePaymentMethod.id;
  }

  const paymentMethods = await stripeRequest<{
    data?: Array<{ id?: string }>;
  }>(
    secretKey,
    "GET",
    `/payment_methods?customer=${
      encodeURIComponent(customerId)
    }&type=card&limit=1`,
  );
  const firstPaymentMethod = paymentMethods.data?.[0]?.id;
  if (firstPaymentMethod) return firstPaymentMethod;

  const defaultSource = customer.default_source;
  if (typeof defaultSource === "string" && defaultSource) return defaultSource;
  if (defaultSource && typeof defaultSource === "object" && defaultSource.id) {
    return defaultSource.id;
  }

  return null;
}

async function fetchPatients(
  supabase: SupabaseAdmin,
  tenantId: string,
  emails: string[],
  patientIds: string[],
): Promise<PatientRow[]> {
  const byId = new Map<string, PatientRow>();

  if (emails.length > 0) {
    const { data, error } = await supabase
      .from("patients")
      .select("id, email, metadata")
      .eq("tenant_id", tenantId)
      .in("email", emails);
    if (error) {
      throw new Error(`Patient lookup by email failed: ${error.message}`);
    }
    for (const row of data ?? []) byId.set(row.id, row);
  }

  if (patientIds.length > 0) {
    const { data, error } = await supabase
      .from("patients")
      .select("id, email, metadata")
      .eq("tenant_id", tenantId)
      .in("id", patientIds);
    if (error) throw new Error(`Patient lookup by id failed: ${error.message}`);
    for (const row of data ?? []) byId.set(row.id, row);
  }

  return [...byId.values()];
}

async function fetchSubscriptions(
  supabase: SupabaseAdmin,
  tenantId: string,
  patients: PatientRow[],
  subscriptionIds: string[],
): Promise<SubscriptionRow[]> {
  const selectedPatientIds = patients.map((patient) => patient.id);
  const byId = new Map<string, SubscriptionRow>();

  async function fetchQuery(
    query: PromiseLike<
      { data: any[] | null; error: { message: string } | null }
    >,
  ) {
    const { data, error } = await query;
    if (error) throw new Error(`Subscription lookup failed: ${error.message}`);
    for (const row of data ?? []) {
      byId.set(row.id, {
        ...row,
        patient: Array.isArray(row.patient) ? row.patient[0] : row.patient,
        product: Array.isArray(row.product) ? row.product[0] : row.product,
      });
    }
  }

  const select =
    "id, tenant_id, patient_id, product_id, status, current_period_end_at, expires_at, cancelled_at, stripe_subscription_id, metadata, patient:patients(id, email, metadata), product:products(id, name, sku, price_cents, payment_type, subscription_interval, subscription_interval_count, metadata)";

  if (selectedPatientIds.length > 0) {
    await fetchQuery(
      supabase
        .from("subscriptions")
        .select(select)
        .eq("tenant_id", tenantId)
        .in("patient_id", selectedPatientIds),
    );
  }

  if (subscriptionIds.length > 0) {
    await fetchQuery(
      supabase
        .from("subscriptions")
        .select(select)
        .eq("tenant_id", tenantId)
        .in("id", subscriptionIds),
    );
  }

  return [...byId.values()];
}

async function getExistingStripeLink(
  supabase: SupabaseAdmin,
  paymentProviderId: string,
  subscriptionId: string,
): Promise<string | null> {
  const { data, error } = await supabase
    .from("subscription_payment_provider_links")
    .select("provider_subscription_id")
    .eq("payment_provider_id", paymentProviderId)
    .eq("subscription_id", subscriptionId)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Subscription payment link lookup failed: ${error.message}`,
    );
  }

  return asString(data?.provider_subscription_id);
}

// Shared by the dry-run preview and the live path so dry-run can validate
// product readiness (and surface the same errors) without ever calling
// Stripe. Throws the same error codes either way.
function validateSubscriptionProduct(
  subscription: SubscriptionRow,
): ProductRow & {
  subscription_interval: string;
  subscription_interval_count: number;
} {
  const product = subscription.product;
  if (!product) throw new Error("missing_product");
  if (product.payment_type !== "subscription") {
    throw new Error("product_is_not_subscription");
  }
  if (!product.subscription_interval || !product.subscription_interval_count) {
    throw new Error("product_missing_subscription_interval");
  }
  if (!Number.isFinite(product.price_cents) || product.price_cents <= 0) {
    throw new Error("product_missing_price_cents");
  }
  return product as ProductRow & {
    subscription_interval: string;
    subscription_interval_count: number;
  };
}

// Stripe's items[].price_data only accepts an existing product id - it does
// not support inline product_data the way prices/invoiceitems do. So when a
// PP product has no stripe_product_id yet, create the Stripe product once
// and cache the id back onto the product row for every future handoff.
async function getOrCreateStripeProductId(params: {
  supabase: SupabaseAdmin;
  secretKey: string;
  product: ProductRow;
}): Promise<string> {
  const metadata = asRecord(params.product.metadata);
  const existingStripeProductId = asString(metadata.stripe_product_id);
  if (existingStripeProductId) return existingStripeProductId;

  const body = new URLSearchParams();
  body.append("name", params.product.name);
  body.append("metadata[allia_product_id]", params.product.id);
  if (params.product.sku) {
    body.append("metadata[sku]", params.product.sku);
  }

  const created = await stripeRequest<{ id: string }>(
    params.secretKey,
    "POST",
    "/products",
    body,
    `brello_pp_product_${params.product.id}`,
  );

  const { error } = await params.supabase
    .from("products")
    .update({ metadata: { ...metadata, stripe_product_id: created.id } })
    .eq("id", params.product.id);
  if (error) {
    throw new Error(
      `Failed to cache stripe_product_id for product ${params.product.id}: ${error.message}`,
    );
  }

  return created.id;
}

async function appendSubscriptionPriceParams(params: {
  supabase: SupabaseAdmin;
  secretKey: string;
  body: URLSearchParams;
  subscription: SubscriptionRow;
  stripePriceId: string | null;
  currency: string;
}): Promise<"override" | "inline_price_data"> {
  if (params.stripePriceId) {
    params.body.append("items[0][price]", params.stripePriceId);
    return "override";
  }

  const product = validateSubscriptionProduct(params.subscription);

  const stripeProductId = await getOrCreateStripeProductId({
    supabase: params.supabase,
    secretKey: params.secretKey,
    product,
  });

  params.body.append("items[0][price_data][currency]", params.currency);
  params.body.append(
    "items[0][price_data][unit_amount]",
    `${product.price_cents}`,
  );
  params.body.append(
    "items[0][price_data][recurring][interval]",
    product.subscription_interval,
  );
  params.body.append(
    "items[0][price_data][recurring][interval_count]",
    `${product.subscription_interval_count}`,
  );
  params.body.append("items[0][price_data][product]", stripeProductId);

  return "inline_price_data";
}

async function createStripeSubscription(params: {
  supabase: SupabaseAdmin;
  stripeProvider: StripeProviderContext;
  subscription: SubscriptionRow;
  stripeCustomerId: string;
  paymentMethodId: string;
  billingAnchorSeconds: number;
  stripePriceId: string | null;
  currency: string;
  wooSubscriptionId: string | null;
}): Promise<{ id: string }> {
  const body = new URLSearchParams();
  body.append("customer", params.stripeCustomerId);
  body.append("collection_method", "charge_automatically");
  body.append("default_payment_method", params.paymentMethodId);
  body.append("billing_cycle_anchor", `${params.billingAnchorSeconds}`);
  body.append("proration_behavior", "none");
  body.append("metadata[tenant_id]", params.subscription.tenant_id);
  body.append("metadata[patient_id]", params.subscription.patient_id);
  body.append("metadata[subscription_id]", params.subscription.id);
  body.append("metadata[migration_phase]", "4");
  body.append("metadata[is_migrated]", "true");
  if (params.wooSubscriptionId) {
    body.append("metadata[woo_subscription_id]", params.wooSubscriptionId);
  }
  await appendSubscriptionPriceParams({
    supabase: params.supabase,
    secretKey: params.stripeProvider.secretKey,
    body,
    subscription: params.subscription,
    stripePriceId: params.stripePriceId,
    currency: params.currency,
  });

  return await stripeRequest<{ id: string }>(
    params.stripeProvider.secretKey,
    "POST",
    "/subscriptions",
    body,
    `brello_pp_subscription_handoff_${params.subscription.id}`,
  );
}

async function saveHandoffResult(params: {
  supabase: SupabaseAdmin;
  subscription: SubscriptionRow;
  stripeProvider: StripeProviderContext;
  stripeSubscriptionId: string;
  stripeCustomerId: string;
  paymentMethodId: string;
  wooSubscriptionId: string | null;
  billingAnchorIso: string | null;
  wooRenewalBlockingConfirmed: boolean;
  wooRenewalBlockResult: WooRenewalBlockResult | null;
}) {
  const metadata = {
    ...(params.subscription.metadata ?? {}),
    migration_phase_4: {
      is_migrated: true,
      provider: "stripe",
      same_account_handoff: true,
      stripe_customer_id: params.stripeCustomerId,
      stripe_subscription_id: params.stripeSubscriptionId,
      default_payment_method_id: params.paymentMethodId,
      woo_subscription_id: params.wooSubscriptionId,
      billing_cycle_anchor: params.billingAnchorIso,
      imported_at: new Date().toISOString(),
      // woo_renewal_blocking_confirmed is a human assertion, recorded as-is.
      // woo_renewal_block_result is the actual verified outcome of calling
      // the WooCommerce API, present only when execute_woo_renewal_block
      // was set. Do not conflate the two - one is a claim, the other is proof.
      woo_renewal_blocked: params.wooRenewalBlockingConfirmed,
      woo_renewal_blocking_confirmed: params.wooRenewalBlockingConfirmed,
      woo_renewal_block_result: params.wooRenewalBlockResult,
    },
  };

  const { error: subscriptionError } = await params.supabase
    .from("subscriptions")
    .update({
      stripe_subscription_id: params.stripeSubscriptionId,
      metadata,
    })
    .eq("id", params.subscription.id)
    .eq("tenant_id", params.subscription.tenant_id);
  if (subscriptionError) {
    throw new Error(`Subscription update failed: ${subscriptionError.message}`);
  }

  const { error: linkError } = await params.supabase
    .from("subscription_payment_provider_links")
    .upsert({
      tenant_id: params.subscription.tenant_id,
      subscription_id: params.subscription.id,
      payment_provider_id: params.stripeProvider.paymentProviderId,
      provider_subscription_id: params.stripeSubscriptionId,
      metadata: {
        migration_phase: 4,
        same_account_handoff: true,
        stripe_customer_id: params.stripeCustomerId,
        woo_subscription_id: params.wooSubscriptionId,
        woo_renewal_blocking_confirmed: params.wooRenewalBlockingConfirmed,
        woo_renewal_block_result: params.wooRenewalBlockResult,
      },
    }, {
      onConflict: "subscription_id,payment_provider_id",
      ignoreDuplicates: false,
    });
  if (linkError) {
    throw new Error(
      `Subscription payment link upsert failed: ${linkError.message}`,
    );
  }
}

async function saveFreeHandoffResult(params: {
  supabase: SupabaseAdmin;
  subscription: SubscriptionRow;
  wooSubscriptionId: string | null;
  wooRenewalBlockingConfirmed: boolean;
  wooRenewalBlockResult: WooRenewalBlockResult | null;
}) {
  const metadata = {
    ...(params.subscription.metadata ?? {}),
    migration_phase_4: {
      is_migrated: true,
      provider: "free",
      free_handoff: true,
      woo_subscription_id: params.wooSubscriptionId,
      imported_at: new Date().toISOString(),
      woo_renewal_blocked: params.wooRenewalBlockingConfirmed,
      woo_renewal_blocking_confirmed: params.wooRenewalBlockingConfirmed,
      woo_renewal_block_result: params.wooRenewalBlockResult,
    },
  };

  const { error } = await params.supabase
    .from("subscriptions")
    .update({ metadata })
    .eq("id", params.subscription.id)
    .eq("tenant_id", params.subscription.tenant_id);

  if (error) {
    throw new Error(`Free handoff update failed: ${error.message}`);
  }
}

Deno.serve(async (req) => {
  if (req.method !== "POST") {
    return json({ error: "method_not_allowed" }, 405);
  }

  const expectedApiKey = Deno.env.get("MIGRATION_API_KEY");
  const providedApiKey = req.headers.get("X-Migration-API-Key");
  if (!expectedApiKey || providedApiKey !== expectedApiKey) {
    return json({ error: "unauthorized" }, 401);
  }

  try {
    const body = await req.json().catch(() => ({})) as HandoffRequest;
    const emails = normalizeList(body.emails);
    const patientIds = normalizeList(body.patient_ids);
    const subscriptionIds = normalizeList(body.subscription_ids);
    const dryRun = body.dry_run !== false;
    const confirmLive = body.confirm_live === true;
    const wooRenewalBlockingConfirmed =
      body.woo_renewal_blocking_confirmed === true;
    const allowMissingWooSubscriptionId =
      body.allow_missing_woo_subscription_id === true;
    const executeWooRenewalBlock = body.execute_woo_renewal_block === true;
    const wooRenewalBlockTargetStatus = asString(
      body.woo_renewal_block_target_status,
    );
    const tenantSlug = asString(body.tenant_slug) ?? "brello";
    const currency = (asString(body.currency) ?? "usd").toLowerCase();
    const priceOverrides = asRecord(body.price_overrides) as Record<
      string,
      string
    >;
    const freeHandoff = body.free_handoff === true;

    if (
      emails.length === 0 && patientIds.length === 0 &&
      subscriptionIds.length === 0
    ) {
      return json({
        error: "scope_required",
        message: "Provide emails, patient_ids, or subscription_ids",
      }, 400);
    }
    if (!dryRun && !confirmLive) {
      return json({
        error: "confirm_live_required",
        message: "Set confirm_live=true when dry_run=false",
      }, 400);
    }
    if (!dryRun && !wooRenewalBlockingConfirmed && !freeHandoff) {
      // free_handoff = $0 subscription; WC renewal cannot charge anything,
      // so the renewal-blocking confirmation is not required for this path.
      return json({
        error: "woo_renewal_blocking_confirmation_required",
        message:
          "Set woo_renewal_blocking_confirmed=true after confirming WooCommerce renewal will not charge these migrated subscriptions",
      }, 400);
    }
    if (executeWooRenewalBlock && !wooRenewalBlockTargetStatus) {
      return json({
        error: "woo_renewal_block_target_status_required",
        message:
          "Set woo_renewal_block_target_status when execute_woo_renewal_block=true - this function will not guess the target WooCommerce subscription status",
      }, 400);
    }
    let wcBaseUrl: string | null = null;
    let wcAuth: string | null = null;
    if (executeWooRenewalBlock) {
      wcBaseUrl = Deno.env.get("WC_BASE_URL") ?? null;
      wcAuth = Deno.env.get("WC_AUTH") ?? null;
      if (!wcBaseUrl || !wcAuth) {
        return json({
          error: "woo_credentials_not_configured",
          message:
            "WC_BASE_URL and WC_AUTH must be set on this function before execute_woo_renewal_block can run",
        }, 400);
      }
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL");
    const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
    if (!supabaseUrl || !serviceRoleKey) {
      throw new Error("Missing Supabase environment variables");
    }

    const supabaseAdmin = createClient(supabaseUrl, serviceRoleKey, {
      auth: { persistSession: false, autoRefreshToken: false },
    }) as SupabaseAdmin;

    const { data: tenant, error: tenantError } = await supabaseAdmin
      .from("tenants")
      .select("id, slug")
      .eq("slug", tenantSlug)
      .maybeSingle();
    if (tenantError || !tenant?.id) {
      throw new Error(
        `Tenant not found: ${tenantError?.message ?? tenantSlug}`,
      );
    }

    const stripeProvider = freeHandoff
      ? null
      : await getStripeProviderContext(supabaseAdmin, tenant.id);
    const patients = await fetchPatients(
      supabaseAdmin,
      tenant.id,
      emails,
      patientIds,
    );
    const subscriptions = await fetchSubscriptions(
      supabaseAdmin,
      tenant.id,
      patients,
      subscriptionIds,
    );
    const patientContexts = buildPatientHandoffContexts(subscriptions);

    const results: HandoffResult[] = [];

    for (const subscription of subscriptions) {
      const patientContext = patientContexts.get(subscription.patient_id);
      const wooSubscriptionId = resolveWooSubscriptionId(subscription) ??
        patientContext?.wooSubscriptionId ?? null;
      const stripeCustomerId = resolveStripeCustomerId(subscription) ??
        patientContext?.stripeCustomerId ?? null;
      const billingAnchorIso = isFutureIso(resolveBillingAnchor(subscription))
        ? resolveBillingAnchor(subscription)
        : patientContext?.billingAnchorIso ??
          resolveBillingAnchor(subscription);
      const billingAnchorSeconds = unixSecondsFromIso(billingAnchorIso);
      const stripePriceId = resolvePriceOverride(subscription, priceOverrides);
      const baseResult: HandoffResult = {
        subscription_id: subscription.id,
        patient_id: subscription.patient_id,
        email: subscription.patient?.email ?? null,
        woo_subscription_id: wooSubscriptionId,
        stripe_customer_id: stripeCustomerId,
        product_id: subscription.product_id,
        status: "blocked",
        reason: null,
        dry_run: dryRun,
        existing_stripe_subscription_id: subscription.stripe_subscription_id,
        created_stripe_subscription_id: null,
        default_payment_method_id: null,
        billing_cycle_anchor: billingAnchorIso,
        price_mode: null,
        stripe_price_id: stripePriceId,
        woo_renewal_block: null,
      };

      try {
        const existingProviderSubscriptionId = stripeProvider
          ? await getExistingStripeLink(
            supabaseAdmin,
            stripeProvider.paymentProviderId,
            subscription.id,
          )
          : null;
        const existingStripeSubscriptionId =
          subscription.stripe_subscription_id ?? existingProviderSubscriptionId;
        if (existingStripeSubscriptionId) {
          results.push({
            ...baseResult,
            status: "skipped",
            reason: "already_has_stripe_subscription",
            existing_stripe_subscription_id: existingStripeSubscriptionId,
          });
          continue;
        }
        if (!MIGRATABLE_SUBSCRIPTION_STATUSES.has(subscription.status)) {
          results.push({
            ...baseResult,
            reason: "subscription_status_not_migratable",
          });
          continue;
        }
        if (!wooSubscriptionId && !allowMissingWooSubscriptionId) {
          results.push({
            ...baseResult,
            reason: "missing_woo_subscription_id",
          });
          continue;
        }

        // Free handoff path (e.g. CareLink internal users with $0 subscriptions).
        // Skip all Stripe logic — no customer, payment method, or billing anchor
        // needed. WC renewal blocking still runs if execute_woo_renewal_block=true.
        if (freeHandoff) {
          if (dryRun) {
            results.push({ ...baseResult, status: "eligible", reason: "free_handoff" });
            continue;
          }
          let wooRenewalBlockResult: WooRenewalBlockResult | null = null;
          if (executeWooRenewalBlock && wooSubscriptionId) {
            wooRenewalBlockResult = await blockWooCommerceRenewal({
              wcBaseUrl: wcBaseUrl as string,
              wcAuth: wcAuth as string,
              wooSubscriptionId,
              targetStatus: wooRenewalBlockTargetStatus as string,
            });
          } else if (executeWooRenewalBlock) {
            wooRenewalBlockResult = {
              attempted: false,
              success: false,
              target_status: wooRenewalBlockTargetStatus,
              previous_status: null,
              error: "missing_woo_subscription_id",
            };
          }
          await saveFreeHandoffResult({
            supabase: supabaseAdmin,
            subscription,
            wooSubscriptionId,
            wooRenewalBlockingConfirmed,
            wooRenewalBlockResult,
          });
          results.push({
            ...baseResult,
            status: "created",
            reason: "free_handoff",
            woo_renewal_block: wooRenewalBlockResult,
          });
          continue;
        }

        if (!stripeCustomerId) {
          results.push({ ...baseResult, reason: "missing_stripe_customer_id" });
          continue;
        }
        if (!subscription.product) {
          results.push({ ...baseResult, reason: "missing_product" });
          continue;
        }
        if (!billingAnchorSeconds) {
          results.push({ ...baseResult, reason: "missing_billing_anchor" });
          continue;
        }
        if (billingAnchorSeconds <= Math.floor(Date.now() / 1000)) {
          results.push({ ...baseResult, reason: "billing_anchor_not_future" });
          continue;
        }

        // Dry-run preview only - must not call Stripe (no product creation
        // side effect), just surface the same validation errors as the live
        // path would hit.
        let priceMode: "override" | "inline_price_data";
        if (stripePriceId) {
          priceMode = "override";
        } else {
          validateSubscriptionProduct(subscription);
          priceMode = "inline_price_data";
        }

        const paymentMethodId = await getReusablePaymentMethodId(
          stripeProvider!.secretKey,
          stripeCustomerId,
        );
        if (!paymentMethodId) {
          results.push({ ...baseResult, reason: "missing_payment_method" });
          continue;
        }

        if (dryRun) {
          results.push({
            ...baseResult,
            status: "eligible",
            reason: null,
            default_payment_method_id: paymentMethodId,
            price_mode: priceMode,
          });
          continue;
        }

        const created = await createStripeSubscription({
          supabase: supabaseAdmin,
          stripeProvider: stripeProvider!,
          subscription,
          stripeCustomerId,
          paymentMethodId,
          billingAnchorSeconds,
          stripePriceId,
          currency,
          wooSubscriptionId,
        });

        let wooRenewalBlockResult: WooRenewalBlockResult | null = null;
        if (executeWooRenewalBlock && wooSubscriptionId) {
          wooRenewalBlockResult = await blockWooCommerceRenewal({
            wcBaseUrl: wcBaseUrl as string,
            wcAuth: wcAuth as string,
            wooSubscriptionId,
            targetStatus: wooRenewalBlockTargetStatus as string,
          });
        } else if (executeWooRenewalBlock) {
          wooRenewalBlockResult = {
            attempted: false,
            success: false,
            target_status: wooRenewalBlockTargetStatus,
            previous_status: null,
            error: "missing_woo_subscription_id",
          };
        }

        await saveHandoffResult({
          supabase: supabaseAdmin,
          subscription,
          stripeProvider: stripeProvider!,
          stripeSubscriptionId: created.id,
          stripeCustomerId,
          paymentMethodId,
          wooSubscriptionId,
          billingAnchorIso,
          wooRenewalBlockingConfirmed,
          wooRenewalBlockResult,
        });

        results.push({
          ...baseResult,
          status: "created",
          reason: null,
          created_stripe_subscription_id: created.id,
          default_payment_method_id: paymentMethodId,
          price_mode: priceMode,
          woo_renewal_block: wooRenewalBlockResult,
        });
      } catch (error) {
        results.push({
          ...baseResult,
          status: "failed",
          reason: error instanceof Error ? error.message : String(error),
        });
      }
    }

    return json({
      dry_run: dryRun,
      tenant_slug: tenantSlug,
      live_safety: {
        confirm_live: confirmLive,
        woo_renewal_blocking_confirmed: wooRenewalBlockingConfirmed,
        allow_missing_woo_subscription_id: allowMissingWooSubscriptionId,
        execute_woo_renewal_block: executeWooRenewalBlock,
        woo_renewal_block_target_status: wooRenewalBlockTargetStatus,
        free_handoff: freeHandoff,
      },
      requested: {
        emails,
        patient_ids: patientIds,
        subscription_ids: subscriptionIds,
      },
      patients_matched: patients.length,
      subscriptions_matched: subscriptions.length,
      summary: {
        eligible: results.filter((row) => row.status === "eligible").length,
        created: results.filter((row) => row.status === "created").length,
        skipped: results.filter((row) => row.status === "skipped").length,
        blocked: results.filter((row) => row.status === "blocked").length,
        failed: results.filter((row) => row.status === "failed").length,
      },
      results,
    });
  } catch (error) {
    return json({
      error: "migration_phase4_subscription_handoff_failed",
      message: error instanceof Error ? error.message : String(error),
    }, 500);
  }
});
