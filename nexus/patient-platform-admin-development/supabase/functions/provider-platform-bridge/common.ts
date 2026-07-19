import { createClient, SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { dateTime } from "../_shared/dayjs.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  extractProviderNameFromMetadata,
  normalizeProviderPlatformIdentifier,
  parseAnswerLocationFormData,
} from "./helpers.ts";
import { notifyRtdhOrderStatusUpdatedAsync } from "../order-lifecycle/rtdh-helper.ts";

// The bridge writes to multiple tables with dynamic payloads; keep the admin
// client permissive and validate rows at the function boundary instead.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type SupabaseAdminClient = SupabaseClient<any, "public", any>;

export interface OrderRow {
  id: string;
  tenant_id: string;
  patient_id: string | null;
  product_id: string | null;
  status_id: string | null;
  subscription_order_type: string | null;
  provider_platform_integration_key: string | null;
  order_statuses: OrderStatusRow | null;
}

export interface OrderStatusRow {
  id: string;
  status_key: string;
  admin_status_label: string;
  display_order: number;
  is_terminal: boolean;
  next_status_id: string | null;
}

export interface TenantIntegrationRow {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
  provider_legal_agreement: string | null;
  settings: Record<string, unknown> | null;
}

export interface OrderProviderPlatformLinkRow {
  id: string;
  provider_order_id: string | null;
  metadata: Record<string, unknown> | null;
  tenant_integration_id: string;
}

export interface PatientProviderPlatformLinkRow {
  id: string;
  provider_patient_id: string | null;
  tenant_integration_id: string;
}

export interface ProductProviderPlatformRow {
  id: string;
  offering_id: string | null;
  questionnaire_id: string | null;
  jotform_new_order_questionnaire_id: string | null;
  jotform_renewall_questionnaire_id: string | null;
  provider_product_sku: string | null;
  provider_product_variation_sku: string | null;
  tenant_integration_id: string;
  // Explicit per-product×provider questionnaire mode ('direct' | 'jotform').
  // Authoritative when set; null falls back to inference ("has form id?").
  integration_mode: string | null;
}

export interface TenantRow {
  id: string;
  slug: string;
}

export interface PatientRow {
  id: string;
  tenant_id: string;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
}

interface AnswerLocationRequestBody {
  "questionnaire-id"?: string;
  questionnaireId?: string;
  location?: string;
  value?: unknown;
}

interface UpdatePatientProfileRequestBody {
  patientData?: Record<string, unknown> | null;
}

export interface ParsedAnswerLocationBody {
  questionnaireId: string | null;
  location: string | null;
  value: string | string[] | null;
  file: File | null;
}

export interface ParsedUpdatePatientProfileBody {
  patientData: Record<string, unknown> | null;
}

export function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-tenant-id, x-tenant-slug, x-request-id, x-api-version",
    exposeHeaders: "content-type, x-request-id",
    methods: "GET, POST, PUT, OPTIONS",
  });
}

export function jsonResponse(
  req: Request,
  body: Record<string, unknown>,
  status = 200,
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...getCorsHeaders(req), "Content-Type": "application/json" },
  });
}

function isJsonRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

export function applyProviderLegalAgreementToAgreementQuestions<T>(
  value: T,
  providerLegalAgreement?: string | null,
): T {
  const normalizedAgreement = typeof providerLegalAgreement === "string" &&
      providerLegalAgreement.trim().length > 0
    ? providerLegalAgreement
    : null;

  const visit = (current: unknown): unknown => {
    if (Array.isArray(current)) {
      return current.map(visit);
    }

    if (!isJsonRecord(current)) {
      return current;
    }

    const next = Object.fromEntries(
      Object.entries(current).map((
        [key, entryValue],
      ) => [key, visit(entryValue)]),
    );
    const type = typeof next.type === "string"
      ? next.type.trim().toLowerCase()
      : "";

    if (type === "agreement") {
      next.provider_legal_agreement = normalizedAgreement;
    }

    return next;
  };

  return visit(value) as T;
}

export function getSupabaseAuthClient(authHeader: string) {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY");

  if (!supabaseUrl || !supabaseAnonKey) {
    throw new Error("Missing Supabase auth configuration");
  }

  return createClient(supabaseUrl, supabaseAnonKey, {
    global: {
      headers: {
        Authorization: authHeader,
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}

export function getSupabaseAdminClient(requestId: string): SupabaseAdminClient {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    throw new Error("Missing Supabase configuration");
  }

  return createClient(supabaseUrl, supabaseServiceKey, {
    global: {
      headers: {
        "x-request-id": requestId,
        "x-request-source": "provider-platform-bridge",
      },
    },
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });
}

export async function fetchOrderById(
  supabase: SupabaseAdminClient,
  orderId: string,
): Promise<OrderRow | null> {
  const { data, error } = await supabase
    .from("orders")
    .select(
      `id, tenant_id, patient_id, product_id, status_id, subscription_order_type, provider_platform_integration_key, order_statuses (
        id,
        status_key,
        admin_status_label,
        display_order,
        is_terminal,
        next_status_id
      )`,
    )
    .eq("id", orderId)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch order: ${error.message}`);
  }

  return data as OrderRow | null;
}

async function fetchNextOrderStatus(
  supabase: SupabaseAdminClient,
  nextStatusId: string | null,
): Promise<OrderStatusRow | null> {
  if (!nextStatusId) {
    return null;
  }

  const { data, error } = await supabase
    .from("order_statuses")
    .select(
      "id, status_key, admin_status_label, display_order, is_terminal, next_status_id",
    )
    .eq("id", nextStatusId)
    .eq("is_active", true)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch next order status: ${error.message}`);
  }

  return data as OrderStatusRow | null;
}

/**
 * The only order statuses a QUESTIONNAIRE-submission handler may advance from.
 * Passing this as `expectedFromStatusKeys` guarantees a questionnaire submission
 * can never push the order across the provider-review gate into provider_approved
 * (and on to payment capture) — provider_approved must come only from a real
 * provider decision via the rtdh-webhook.
 */
export const QUESTIONNAIRE_ADVANCE_FROM_STATUSES = [
  "patient_questionnaire_pending",
  "medical_questionnaire_pending",
] as const;

export async function advanceOrderToNextStatus(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  note: string;
  requestId?: string;
  source?: string;
  /**
   * Guard: only advance when the order's CURRENT status is one of these keys.
   * Callers driven by a specific event (e.g. a questionnaire submission) MUST
   * pass the status(es) that event is allowed to advance from, so the order is
   * never blindly pushed to next_status from an unexpected state. Without this a
   * questionnaire-submitted handler firing while the order has already advanced
   * (e.g. a returning patient whose questionnaire was reused, leaving the order
   * already at provider_review_pending) would advance provider_review_pending →
   * provider_approved — self-approving the order and triggering payment capture
   * with no real provider decision. Omit only for legacy/unconditional callers.
   */
  expectedFromStatusKeys?: readonly string[];
}): Promise<{
  advanced: boolean;
  previousStatusKey: string | null;
  newStatusKey: string | null;
  skippedReason?: "terminal" | "unexpected_status" | "no_next_status";
}> {
  const {
    supabase,
    order,
    note,
    requestId = crypto.randomUUID(),
    source,
    expectedFromStatusKeys,
  } = params;
  const latestOrder = await fetchOrderById(supabase, order.id);
  const effectiveOrder = latestOrder ?? order;
  const currentStatus = effectiveOrder.order_statuses;

  if (!currentStatus || currentStatus.is_terminal) {
    return {
      advanced: false,
      previousStatusKey: currentStatus?.status_key || null,
      newStatusKey: null,
      skippedReason: "terminal",
    };
  }

  // Transition guard: if the caller declared the expected from-status(es), do not
  // advance when the order has already moved past them (idempotent no-op).
  if (
    expectedFromStatusKeys &&
    expectedFromStatusKeys.length > 0 &&
    !expectedFromStatusKeys.includes(currentStatus.status_key)
  ) {
    console.info(
      "advanceOrderToNextStatus: skipped — order not in an expected from-status",
      {
        requestId,
        orderId: effectiveOrder.id,
        currentStatus: currentStatus.status_key,
        expectedFromStatusKeys,
        source,
      },
    );
    return {
      advanced: false,
      previousStatusKey: currentStatus.status_key,
      newStatusKey: null,
      skippedReason: "unexpected_status",
    };
  }

  const nextStatus = await fetchNextOrderStatus(
    supabase,
    currentStatus.next_status_id,
  );

  if (!nextStatus) {
    return {
      advanced: false,
      previousStatusKey: currentStatus.status_key,
      newStatusKey: null,
      skippedReason: "no_next_status",
    };
  }

  const changedAt = dateTime().toISOString();
  const { error: updateError } = await supabase
    .from("orders")
    .update({
      status_id: nextStatus.id,
      status_changed_at: changedAt,
    })
    .eq("id", effectiveOrder.id)
    .eq("tenant_id", effectiveOrder.tenant_id);

  if (updateError) {
    throw new Error(`Failed to update order status: ${updateError.message}`);
  }

  const { error: historyError } = await supabase
    .from("order_status_history")
    .insert({
      order_id: effectiveOrder.id,
      status_id: nextStatus.id,
      notes: note,
    });

  if (historyError) {
    throw new Error(
      `Failed to insert order status history: ${historyError.message}`,
    );
  }

  notifyRtdhOrderStatusUpdatedAsync({
    supabase,
    requestId,
    tenantId: effectiveOrder.tenant_id,
    orderId: effectiveOrder.id,
    statusId: nextStatus.id,
    statusKey: nextStatus.status_key,
    previousStatusKey: currentStatus.status_key,
    source: source ?? "provider-platform-bridge",
  });

  return {
    advanced: true,
    previousStatusKey: currentStatus.status_key,
    newStatusKey: nextStatus.status_key,
  };
}

export async function fetchTenantIntegrationForTenantByKey(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  integrationKey: string;
}): Promise<TenantIntegrationRow | null> {
  const { supabase, tenantId, integrationKey } = params;

  const { data, error } = await supabase
    .from("tenant_integrations")
    .select(
      "id, tenant_id, integration_key, is_enabled, provider_legal_agreement, settings",
    )
    .eq("tenant_id", tenantId)
    .eq("integration_key", integrationKey)
    .eq("is_enabled", true)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Failed to fetch ${integrationKey} integration: ${error.message}`,
    );
  }

  return data as TenantIntegrationRow | null;
}

export async function fetchTenantIntegrationById(params: {
  supabase: SupabaseAdminClient;
  tenantIntegrationId: string;
}): Promise<TenantIntegrationRow | null> {
  const { supabase, tenantIntegrationId } = params;

  const { data, error } = await supabase
    .from("tenant_integrations")
    .select(
      "id, tenant_id, integration_key, is_enabled, provider_legal_agreement, settings",
    )
    .eq("id", tenantIntegrationId)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Failed to fetch tenant integration ${tenantIntegrationId}: ${error.message}`,
    );
  }

  return data as TenantIntegrationRow | null;
}

export async function fetchProductProviderPlatform(params: {
  supabase: SupabaseAdminClient;
  productId: string;
  tenantIntegrationId: string;
}): Promise<ProductProviderPlatformRow | null> {
  const { supabase, productId, tenantIntegrationId } = params;

  const { data, error } = await supabase
    .from("product_provider_platforms")
    .select(
      "id, offering_id, questionnaire_id, jotform_new_order_questionnaire_id, jotform_renewall_questionnaire_id, provider_product_sku, provider_product_variation_sku, tenant_integration_id, integration_mode",
    )
    .eq("product_id", productId)
    .eq("tenant_integration_id", tenantIntegrationId)
    .eq("is_enabled", true)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Failed to fetch product provider platform assignment: ${error.message}`,
    );
  }

  return data as ProductProviderPlatformRow | null;
}

export async function fetchPatientProviderPlatformLink(params: {
  supabase: SupabaseAdminClient;
  patientId: string;
  tenantId: string;
  tenantIntegrationId: string;
}): Promise<PatientProviderPlatformLinkRow | null> {
  const { supabase, patientId, tenantId, tenantIntegrationId } = params;

  const { data, error } = await supabase
    .from("patient_provider_platform_links")
    .select("id, provider_patient_id, tenant_integration_id")
    .eq("patient_id", patientId)
    .eq("tenant_id", tenantId)
    .eq("tenant_integration_id", tenantIntegrationId)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Failed to fetch patient provider platform link: ${error.message}`,
    );
  }

  return data as PatientProviderPlatformLinkRow | null;
}

export async function fetchPatientById(params: {
  supabase: SupabaseAdminClient;
  patientId: string;
  tenantId: string;
}): Promise<PatientRow | null> {
  const { supabase, patientId, tenantId } = params;

  const { data, error } = await supabase
    .from("patients")
    .select("id, tenant_id, first_name, last_name, email, phone")
    .eq("id", patientId)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    throw new Error(`Failed to fetch patient: ${error.message}`);
  }

  return data as PatientRow | null;
}

export async function fetchTenantByIdentifier(params: {
  supabase: SupabaseAdminClient;
  tenantId?: string | null;
  tenantSlug?: string | null;
}): Promise<TenantRow | null> {
  const { supabase, tenantId, tenantSlug } = params;

  if (tenantId) {
    const { data, error } = await supabase
      .from("tenants")
      .select("id, slug")
      .eq("id", tenantId)
      .maybeSingle();

    if (error) {
      throw new Error(`Failed to fetch tenant by id: ${error.message}`);
    }

    return data as TenantRow | null;
  }

  if (tenantSlug) {
    const { data, error } = await supabase
      .from("tenants")
      .select("id, slug")
      .eq("slug", tenantSlug)
      .maybeSingle();

    if (error) {
      throw new Error(`Failed to fetch tenant by slug: ${error.message}`);
    }

    return data as TenantRow | null;
  }

  return null;
}

export async function fetchOrderProviderPlatformLinks(params: {
  supabase: SupabaseAdminClient;
  orderId: string;
  tenantId: string;
}): Promise<OrderProviderPlatformLinkRow[]> {
  const { supabase, orderId, tenantId } = params;

  const { data, error } = await supabase
    .from("order_provider_platform_links")
    .select("id, provider_order_id, metadata, tenant_integration_id")
    .eq("order_id", orderId)
    .eq("tenant_id", tenantId)
    .order("created_at", { ascending: false });

  if (error) {
    throw new Error(
      `Failed to fetch order provider platform links: ${error.message}`,
    );
  }

  return (data || []) as OrderProviderPlatformLinkRow[];
}

function parseStringField(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0
    ? value.trim()
    : null;
}

function parseStringArrayField(value: unknown): string[] | null {
  if (!Array.isArray(value)) return null;

  const parsedValues = value
    .map((entry) => parseStringField(entry))
    .filter((entry): entry is string => entry !== null);

  return parsedValues.length > 0 ? parsedValues : null;
}

function parseJsonAnswerValue(value: unknown): string | string[] | null {
  const scalar = parseStringField(value);
  if (scalar) return scalar;

  return parseStringArrayField(value);
}

function parseJsonRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export async function parseAnswerLocationBody(
  req: Request,
): Promise<ParsedAnswerLocationBody> {
  const contentType = req.headers.get("content-type")?.toLowerCase() || "";

  if (contentType.includes("multipart/form-data")) {
    return parseAnswerLocationFormData(await req.formData());
  }

  const body = (await req.json()) as AnswerLocationRequestBody;

  return {
    questionnaireId: parseStringField(body["questionnaire-id"]) ||
      parseStringField(body.questionnaireId),
    location: parseStringField(body.location),
    value: parseJsonAnswerValue(body.value),
    file: null,
  };
}

export async function parseUpdatePatientProfileBody(
  req: Request,
): Promise<ParsedUpdatePatientProfileBody> {
  const body = (await req.json()) as UpdatePatientProfileRequestBody;
  const patientData = parseJsonRecord(body.patientData) ||
    parseJsonRecord(body);

  return {
    patientData: patientData && Object.keys(patientData).length > 0
      ? patientData
      : null,
  };
}

export function parseRequestedTenantIdentifier(
  req: Request,
  url: URL,
): { tenantId: string | null; tenantSlug: string | null } {
  const tenantId = parseStringField(
    url.searchParams.get("tenant_id") || req.headers.get("x-tenant-id"),
  );
  const tenantSlug = parseStringField(
    url.searchParams.get("tenant_slug") ||
      url.searchParams.get("slug") ||
      req.headers.get("x-tenant-slug"),
  );

  return { tenantId, tenantSlug };
}

export function parseProductsQueryParam(url: URL): string[] {
  const values = url.searchParams.getAll("products");
  const productIds: string[] = [];
  const seen = new Set<string>();

  for (const value of values) {
    for (const segment of value.split(",")) {
      const parsedProductId = parseStringField(segment);
      if (!parsedProductId || seen.has(parsedProductId)) continue;
      seen.add(parsedProductId);
      productIds.push(parsedProductId);
    }
  }

  return productIds;
}

export function extractConfiguredProductIdsFromProviderProductSku(
  configuredProductSku: string | null | undefined,
): string[] {
  if (!configuredProductSku) return [];

  const productIds: string[] = [];
  const seen = new Set<string>();

  for (const segment of configuredProductSku.split(",")) {
    const value = parseStringField(segment);
    if (!value || seen.has(value)) continue;
    seen.add(value);
    productIds.push(value);
  }

  return productIds;
}

export async function userHasTenantAccess(params: {
  supabase: SupabaseAdminClient;
  authUserId: string;
  tenantId: string;
  requestId?: string;
  orderId?: string;
  orderPatientId?: string | null;
  resource?: string;
}): Promise<boolean> {
  const { supabase, authUserId, tenantId, orderPatientId } = params;

  const { data: adminUser, error: adminUserError } = await supabase
    .from("admin_users")
    .select("id, is_active")
    .eq("auth_user_id", authUserId)
    .maybeSingle();

  if (adminUserError) {
    throw new Error(
      `Failed to fetch admin user access context: ${adminUserError.message}`,
    );
  }

  if (!adminUser || adminUser.is_active === false) {
    if (orderPatientId) {
      const { data: patient, error: patientError } = await supabase
        .from("patients")
        .select("id")
        .eq("id", orderPatientId)
        .eq("tenant_id", tenantId)
        .eq("auth_user_id", authUserId)
        .maybeSingle();

      if (patientError) {
        throw new Error(
          `Failed to validate patient access for order: ${patientError.message}`,
        );
      }

      if (patient) {
        return true;
      }
    }
    return false;
  }

  const { data: superadminRole, error: superadminError } = await supabase
    .from("user_roles")
    .select("id")
    .eq("user_id", adminUser.id)
    .eq("role", "platform_superadmin")
    .maybeSingle();

  if (superadminError) {
    throw new Error(
      `Failed to validate platform superadmin access: ${superadminError.message}`,
    );
  }

  if (superadminRole) {
    return true;
  }

  const { data: membership, error: membershipError } = await supabase
    .from("tenant_memberships")
    .select("id")
    .eq("admin_user_id", adminUser.id)
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (membershipError) {
    throw new Error(
      `Failed to fetch user tenant memberships: ${membershipError.message}`,
    );
  }

  if (!membership) {
    if (orderPatientId) {
      const { data: patient, error: patientError } = await supabase
        .from("patients")
        .select("id")
        .eq("id", orderPatientId)
        .eq("tenant_id", tenantId)
        .eq("auth_user_id", authUserId)
        .maybeSingle();

      if (patientError) {
        throw new Error(
          `Failed to validate patient access for order: ${patientError.message}`,
        );
      }

      if (patient) {
        return true;
      }
    }
  }

  return !!membership;
}

export async function userHasOrderAccess(params: {
  supabase: SupabaseAdminClient;
  authUserId: string;
  order: OrderRow;
}): Promise<boolean> {
  const { supabase, authUserId, order } = params;

  const hasTenantAccess = await userHasTenantAccess({
    supabase,
    authUserId,
    tenantId: order.tenant_id,
  });

  if (hasTenantAccess) {
    return true;
  }

  if (!order.patient_id) {
    return false;
  }

  const { data, error } = await supabase
    .from("patients")
    .select("id")
    .eq("id", order.patient_id)
    .eq("tenant_id", order.tenant_id)
    .eq("auth_user_id", authUserId)
    .maybeSingle();

  if (error) {
    throw new Error(
      `Failed to validate patient order access: ${error.message}`,
    );
  }

  return !!data;
}

export async function triggerOrderLifecycleForOrder(params: {
  orderId: string;
  tenantId: string;
  requestId: string;
}): Promise<boolean> {
  const { orderId, tenantId, requestId } = params;
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseServiceKey) {
    console.warn("Unable to trigger order-lifecycle: missing env config", {
      requestId,
      orderId,
      tenantId,
    });
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
        "x-request-source": "provider-platform-bridge:patient_profile_updated",
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.warn(
        "Failed to trigger order-lifecycle after provider patient update",
        {
          requestId,
          orderId,
          tenantId,
          status: response.status,
          error: errorText,
        },
      );
      return false;
    }

    console.info("Triggered order-lifecycle after provider patient update", {
      requestId,
      orderId,
      tenantId,
      status: response.status,
    });
    return true;
  } catch (error) {
    console.warn(
      "Error triggering order-lifecycle after provider patient update",
      {
        requestId,
        orderId,
        tenantId,
        error: error instanceof Error ? error.message : String(error),
      },
    );
    return false;
  }
}

export async function resolveOrderProviderPlatformLink(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
}): Promise<{
  providerPlatformLinks: OrderProviderPlatformLinkRow[];
  providerPlatformLink: OrderProviderPlatformLinkRow | null;
  providerIntegration: TenantIntegrationRow | null;
  providerName: string | null;
  providerIntegrationKey: string | null;
}> {
  const { supabase, order } = params;
  const providerPlatformLinks = await fetchOrderProviderPlatformLinks({
    supabase,
    orderId: order.id,
    tenantId: order.tenant_id,
  });

  if (providerPlatformLinks.length > 1) {
    throw new Error(
      `Invalid order_provider_platform_links state: expected at most one link for order ${order.id} in tenant ${order.tenant_id}, found ${providerPlatformLinks.length}`,
    );
  }
  const providerPlatformLink = providerPlatformLinks[0] || null;
  const providerName = extractProviderNameFromMetadata(
    providerPlatformLink?.metadata,
  );
  const providerIntegration = providerPlatformLink
    ? await fetchTenantIntegrationById({
      supabase,
      tenantIntegrationId: providerPlatformLink.tenant_integration_id,
    })
    : null;

  return {
    providerPlatformLinks,
    providerPlatformLink,
    providerIntegration,
    providerIntegrationKey: providerIntegration?.integration_key || null,
    providerName: providerName ||
      normalizeProviderPlatformIdentifier(
        order.provider_platform_integration_key ?? null,
      ),
  };
}
