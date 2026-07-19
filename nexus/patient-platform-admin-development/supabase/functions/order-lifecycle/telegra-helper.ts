import { dateTime } from "../_shared/dayjs.ts";
import {
  appendTelegraRequestTimestamp,
  buildTelegraClientAuthUrl as sharedBuildTelegraClientAuthUrl,
  resolveTelegraAccessToken,
} from "../_shared/telegra-auth.ts";
import { triggerRtdhProviderPlatformNewOrder } from "./rtdh-helper.ts";

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;

export interface OrderForTelegra {
  id: string;
  order_number: string;
  tenant_id: string;
  patient_id: string;
  status_id: string | null;
  product_id: string | null;
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
}

interface PatientForTelegra {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  date_of_birth: string | null;
}

interface TenantIntegrationForTelegra {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
  settings: Record<string, unknown> | null;
}

interface ProductProviderPlatformAssignment {
  id: string;
  provider_product_variation_sku: string | null;
  tenant_integration_id: string;
  tenant_integrations: TenantIntegrationForTelegra;
}

interface OrderProviderPlatformLinkForTelegra {
  id: string;
  metadata: Record<string, unknown> | null;
  provider_order_id: string | null;
  tenant_integration_id: string;
  tenant_integrations: TenantIntegrationForTelegra;
}

interface ProviderOrderCreationClaim {
  claimed: boolean;
  provider_order_id: string | null;
  in_progress: boolean;
  link_id: string | null;
  message: string | null;
}

export interface TelegraOrderCreationResult {
  created: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

export interface TelegraQuestionnaireValidationResult {
  applicable: boolean;
  validated: boolean;
  allCompletedAndValid: boolean;
  providerName: string;
  message: string;
  questionnaireInstanceIds: string[];
}

export interface TelegraOrderSendToPharmacyResult {
  applicable: boolean;
  sent: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

export interface TelegraOrderCancelResult {
  applicable: boolean;
  cancelled: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

export interface TelegraLeaveWaitingRoomResult {
  applicable: boolean;
  triggered: boolean;
  alreadyTriggered: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

function normalizeIntegrationKey(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

function isOrderForTelegra(order: OrderForTelegra): boolean {
  return normalizeIntegrationKey(order.provider_platform_integration_key) ===
    "telegramd";
}

export function buildTelegraOrdersUrl(baseUrl: string): string {
  return `${baseUrl.replace(/\/+$/, "")}/orders`;
}

export function buildTelegraSendToPharmacyRecipientsUrl(
  baseUrl: string,
): string {
  return `${buildTelegraOrdersUrl(baseUrl)}/actions/sendToPharmacyRecipients`;
}

export function buildTelegraCancelOrderUrl(baseUrl: string): string {
  return `${buildTelegraOrdersUrl(baseUrl)}/{orderId}/actions/cancel`;
}

export function buildTelegraLeaveWaitingRoomUrl(baseUrl: string): string {
  return `${buildTelegraOrdersUrl(baseUrl)}/{orderId}/actions/leaveWaitingRoom`;
}

export function buildTelegraQuestionnaireInstanceUrl(
  baseUrl: string,
  questionnaireInstanceId: string,
): string {
  return `${baseUrl.replace(/\/+$/, "")}/questionnaireInstances/${
    encodeURIComponent(
      questionnaireInstanceId,
    )
  }`;
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

export function buildTelegraClientAuthUrl(baseUrl: string): string {
  return sharedBuildTelegraClientAuthUrl(baseUrl);
}

function compactObject(
  record: Record<string, unknown>,
): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(record).filter(
      ([, value]) => value !== null && value !== undefined && value !== "",
    ),
  );
}

function getValueAtPath(source: unknown, path: string): unknown {
  return path.split(".").reduce<unknown>((current, segment) => {
    if (!current || typeof current !== "object") return undefined;
    return (current as Record<string, unknown>)[segment];
  }, source);
}

function normalizeTelegraOrderScopedId(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.startsWith("order::") ? trimmedValue : null;
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

function extractQuestionnaireInstanceIdsFromMetadata(
  metadata: unknown,
): string[] {
  if (!metadata || typeof metadata !== "object") return [];

  const rawValue = (metadata as Record<string, unknown>)
    .questionnaire_instance_ids;
  if (!Array.isArray(rawValue)) return [];

  return rawValue
    .filter(
      (value): value is string =>
        typeof value === "string" && value.trim().length > 0,
    )
    .map((value) => value.trim());
}

function isTelegraOrderProviderLink(
  link: OrderProviderPlatformLinkForTelegra,
  order: OrderForTelegra,
): boolean {
  const providerName = extractProviderNameFromMetadata(link.metadata);

  return (
    (link.tenant_integrations?.tenant_id === order.tenant_id &&
      link.tenant_integrations?.is_enabled === true &&
      link.tenant_integrations?.integration_key === "telegramd") ||
    isTelegraProviderPlatform(providerName)
  );
}

async function fetchOrderedProviderLinksForOrder(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  requestId: string;
}): Promise<
  { links: OrderProviderPlatformLinkForTelegra[]; errorMessage: null } | {
    links: [];
    errorMessage: string;
  }
> {
  const { supabase, order, requestId } = params;

  const { data: providerLinks, error: providerLinksError } = await supabase
    .from("order_provider_platform_links")
    .select(
      `
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
    `,
    )
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .order("id", { ascending: true });

  if (providerLinksError) {
    return {
      links: [],
      errorMessage:
        `Failed to fetch order provider platform links: ${providerLinksError.message}`,
    };
  }

  const orderedLinks =
    ((providerLinks || []) as OrderProviderPlatformLinkForTelegra[])
      .slice()
      .sort((left, right) => left.id.localeCompare(right.id));

  if (orderedLinks.length > 1) {
    console.error(
      "Invalid provider platform link cardinality for order",
      {
        requestId,
        orderId: order.id,
        tenantId: order.tenant_id,
        linkIds: orderedLinks.map((link) => link.id),
      },
    );

    return {
      links: [],
      errorMessage:
        `Invalid provider platform selection state: expected at most one selected provider link, found ${orderedLinks.length}`,
    };
  }

  return {
    links: orderedLinks,
    errorMessage: null,
  };
}

export function extractTelegraQuestionnaireStatus(
  responseBody: unknown,
): string | null {
  if (!responseBody || typeof responseBody !== "object") return null;

  const status = (responseBody as Record<string, unknown>).status;
  return typeof status === "string" && status.trim().length > 0
    ? status.trim()
    : null;
}

export function extractTelegraQuestionnaireValid(
  responseBody: unknown,
): boolean | null {
  if (!responseBody || typeof responseBody !== "object") return null;

  const valid = (responseBody as Record<string, unknown>).valid;
  return typeof valid === "boolean" ? valid : null;
}

export function areTelegraQuestionnairesCompletedAndValid(
  questionnaires: Array<{ status: string | null; valid: boolean | null }>,
): boolean {
  return (
    questionnaires.length > 0 &&
    questionnaires.every((questionnaire) => questionnaire.valid === true)
  );
}

export function buildTelegraCreateOrderPayload(params: {
  order: OrderForTelegra;
  patient: PatientForTelegra;
  providerProductVariationSku: string | null;
  projectId: string;
}): Record<string, unknown> {
  const { order, patient, providerProductVariationSku, projectId } = params;
  const patientPhone =
    typeof patient.phone === "string" && patient.phone.trim().length > 0
      ? patient.phone.trim()
      : null;
  const productVariations = providerProductVariationSku
    ? [
      compactObject({
        productVariation: providerProductVariationSku,
        quantity: 1,
      }),
    ]
    : [];

  // The Telegra create-order payload is inferred from the available official docs,
  // runtime validation errors, and the implementation requirements for shipping,
  // billing, patient, and product data.
  return compactObject({
    projectId,
    // Telegra support asked clients to send `projectId`, while the public
    // Update Order schema names the same relationship `project`. Send both
    // during creation so the API can resolve either contract without losing
    // compatibility with the support-provided field.
    project: projectId,
    orderNumber: order.order_number,
    externalIdentifier: order.order_number,
    productVariations,
    patient: compactObject({
      firstName: patient.first_name,
      lastName: patient.last_name,
      email: patient.email,
      phone: patientPhone,
      dateOfBirth: patient.date_of_birth,
    }),
    address: compactObject({
      billing: compactObject({
        address1: order.billing_address_line1,
        address2: order.billing_address_line2,
        city: order.billing_city,
        state: order.billing_state,
        zipcode: order.billing_postal_code,
      }),
      shipping: compactObject({
        address1: order.shipping_address_line1,
        address2: order.shipping_address_line2,
        city: order.shipping_city,
        state: order.shipping_state,
        zipcode: order.shipping_postal_code,
      }),
    }),
  });
}

export function extractTelegraProviderOrderId(
  responseBody: unknown,
): string | null {
  const candidatePaths = [
    "id",
    "_id",
    "providerOrderId",
    "provider_order_id",
    "orderId",
    "order_id",
    "data.id",
    "data._id",
    "data.providerOrderId",
    "data.provider_order_id",
    "data.orderId",
    "data.order_id",
    "order.id",
    "order._id",
    "order.providerOrderId",
    "order.provider_order_id",
    "order.orderId",
    "order.order_id",
    "data.order.id",
    "data.order._id",
    "data.order.providerOrderId",
    "data.order.provider_order_id",
    "data.order.orderId",
    "data.order.order_id",
  ];

  for (const path of candidatePaths) {
    const value = getValueAtPath(responseBody, path);
    const providerOrderId = normalizeTelegraOrderScopedId(
      typeof value === "string" ? value : null,
    );
    if (providerOrderId) {
      return providerOrderId;
    }
  }

  return null;
}

export function extractTelegraProviderPatientId(
  responseBody: unknown,
): string | null {
  const candidatePaths = [
    "patient.id",
    "data.patient.id",
    "order.patient.id",
    "data.order.patient.id",
  ];

  for (const path of candidatePaths) {
    const value = getValueAtPath(responseBody, path);
    if (typeof value === "string" && value.trim().length > 0) {
      return value.trim();
    }
  }

  return null;
}

export function extractTelegraQuestionnaireInstanceIds(
  responseBody: unknown,
): string[] {
  const candidatePaths = [
    "questionnaireInstances",
    "data.questionnaireInstances",
    "order.questionnaireInstances",
    "data.order.questionnaireInstances",
  ];

  for (const path of candidatePaths) {
    const value = getValueAtPath(responseBody, path);
    if (!Array.isArray(value)) continue;

    const questionnaireInstanceIds = value
      .map((entry) => {
        if (!entry || typeof entry !== "object") return null;
        const id = (entry as Record<string, unknown>).id;
        return typeof id === "string" && id.trim().length > 0
          ? id.trim()
          : null;
      })
      .filter((id): id is string => Boolean(id));

    if (questionnaireInstanceIds.length > 0) {
      return questionnaireInstanceIds;
    }
  }

  return [];
}

async function upsertOrderProviderPlatformLink(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  tenantIntegrationId: string;
  providerOrderId: string | null;
  questionnaireInstanceIds: string[];
  requestId: string;
}): Promise<void> {
  const {
    supabase,
    order,
    tenantIntegrationId,
    providerOrderId,
    questionnaireInstanceIds,
    requestId,
  } = params;
  const normalizedProviderOrderId = normalizeTelegraOrderScopedId(
    providerOrderId,
  );

  const payload: Record<string, unknown> = {
    tenant_id: order.tenant_id,
    order_id: order.id,
    tenant_integration_id: tenantIntegrationId,
    metadata: {
      source: "order-lifecycle",
      provider: "TelegraMD",
      order_number: order.order_number,
      questionnaire_instance_ids: questionnaireInstanceIds,
      provider_order_creation_status: "succeeded",
      provider_order_creation_completed_at: dateTime().toISOString(),
      last_received_at: dateTime().toISOString(),
    },
  };

  if (normalizedProviderOrderId) {
    payload.provider_order_id = normalizedProviderOrderId;
  }

  const { error } = await supabase
    .from("order_provider_platform_links")
    .upsert(payload, {
      onConflict: "order_id,tenant_integration_id",
      ignoreDuplicates: false,
    });

  if (error) {
    throw new Error(
      `Failed to persist Telegra order provider platform link: ${error.message}`,
    );
  }

  if (normalizedProviderOrderId) {
    const { error: orderUpdateError } = await supabase
      .from("orders")
      .update({
        provider_platform_order_id: normalizedProviderOrderId,
      })
      .eq("id", order.id)
      .eq("tenant_id", order.tenant_id);

    if (orderUpdateError) {
      throw new Error(
        `Failed to persist Telegra provider order id on order: ${orderUpdateError.message}`,
      );
    }
  }

  console.info("Persisted Telegra order provider platform link", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    tenantIntegrationId,
    providerOrderId: normalizedProviderOrderId,
    questionnaireInstanceIds,
  });
}

async function claimProviderOrderCreation(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  tenantIntegrationId: string;
  requestId: string;
}): Promise<ProviderOrderCreationClaim> {
  const { supabase, order, tenantIntegrationId, requestId } = params;

  const { data, error } = await supabase.rpc(
    "claim_order_provider_platform_creation",
    {
      p_order_id: order.id,
      p_tenant_id: order.tenant_id,
      p_tenant_integration_id: tenantIntegrationId,
      p_request_id: requestId,
      p_stale_after_seconds: 900,
    },
  );

  if (error) {
    throw new Error(
      `Failed to claim provider order creation: ${error.message}`,
    );
  }

  const claim = Array.isArray(data) ? data[0] : data;
  if (!claim) {
    throw new Error("Provider order creation claim returned no result");
  }

  return claim as ProviderOrderCreationClaim;
}

async function updateProviderOrderCreationClaimStatus(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  tenantIntegrationId: string;
  status: "failed";
  requestId: string;
  errorMessage?: string;
}): Promise<void> {
  const {
    supabase,
    order,
    tenantIntegrationId,
    status,
    requestId,
    errorMessage,
  } = params;

  const { data: existingLink, error: fetchError } = await supabase
    .from("order_provider_platform_links")
    .select("metadata")
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("tenant_integration_id", tenantIntegrationId)
    .maybeSingle();

  if (fetchError) {
    console.warn("Failed to load provider order creation claim metadata", {
      requestId,
      orderId: order.id,
      tenantIntegrationId,
      error: fetchError.message,
    });
    return;
  }

  const existingMetadata =
    existingLink?.metadata && typeof existingLink.metadata === "object"
      ? existingLink.metadata
      : {};

  const { error: updateError } = await supabase
    .from("order_provider_platform_links")
    .update({
      metadata: {
        ...existingMetadata,
        source: "order-lifecycle",
        provider_order_creation_status: status,
        provider_order_creation_request_id: requestId,
        provider_order_creation_completed_at: dateTime().toISOString(),
        ...(errorMessage
          ? { provider_order_creation_error: errorMessage }
          : {}),
      },
    })
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("tenant_integration_id", tenantIntegrationId);

  if (updateError) {
    console.warn("Failed to update provider order creation claim status", {
      requestId,
      orderId: order.id,
      tenantIntegrationId,
      status,
      error: updateError.message,
    });
  }
}

async function upsertPatientProviderPlatformLink(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  tenantIntegrationId: string;
  providerPatientId: string | null;
  requestId: string;
}): Promise<void> {
  const { supabase, order, tenantIntegrationId, providerPatientId, requestId } =
    params;

  if (!providerPatientId) return;

  const payload: Record<string, unknown> = {
    tenant_id: order.tenant_id,
    patient_id: order.patient_id,
    tenant_integration_id: tenantIntegrationId,
    provider_patient_id: providerPatientId,
    metadata: {
      source: "order-lifecycle",
      provider: "TelegraMD",
      order_number: order.order_number,
      last_received_at: dateTime().toISOString(),
    },
  };

  const { error } = await supabase
    .from("patient_provider_platform_links")
    .upsert(payload, {
      onConflict: "patient_id,tenant_integration_id",
      ignoreDuplicates: false,
    });

  if (error) {
    console.warn("Failed to upsert Telegra patient provider platform link", {
      requestId,
      orderId: order.id,
      patientId: order.patient_id,
      tenantId: order.tenant_id,
      tenantIntegrationId,
      providerPatientId,
      error: error.message,
    });
  }
}

function buildTelegraOrderCreatedTimelineNote(
  providerOrderId: string | null,
): string {
  return providerOrderId
    ? `Telegra confirmed Order Created (${providerOrderId})`
    : "Telegra confirmed Order Created";
}

async function appendTelegraOrderCreatedHistory(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  providerOrderId: string | null;
  requestId: string;
}): Promise<string | null> {
  const { supabase, order, providerOrderId, requestId } = params;

  if (!order.status_id) {
    console.warn(
      "Skipping Telegra order-created timeline note: missing status_id",
      {
        requestId,
        orderId: order.id,
        providerOrderId,
      },
    );
    return null;
  }

  const { data: historyRow, error } = await supabase
    .from("order_status_history")
    .insert({
      order_id: order.id,
      status_id: order.status_id,
      notes: buildTelegraOrderCreatedTimelineNote(providerOrderId),
    })
    .select("id")
    .single();

  if (error) {
    console.warn("Failed to insert Telegra order-created timeline note", {
      requestId,
      orderId: order.id,
      providerOrderId,
      error: error.message,
    });
    return null;
  }

  return historyRow?.id ?? null;
}

function extractErrorMessage(responseBody: unknown, fallback: string): string {
  if (!responseBody || typeof responseBody !== "object") {
    return fallback;
  }

  const record = responseBody as Record<string, unknown>;
  const candidateKeys = ["message", "error", "detail"];
  for (const key of candidateKeys) {
    const value = record[key];
    if (typeof value === "string" && value.trim().length > 0) {
      return value;
    }
  }

  return fallback;
}

function formatTelegraErrorDetail(_responseBody: unknown): string {
  // Response bodies from Telegra can include sensitive provider data and should
  // never be written to logs.
  return "";
}

async function fetchTelegraQuestionnaireInstance(params: {
  questionnaireInstanceId: string;
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<unknown> {
  const { questionnaireInstanceId, baseUrl, accessToken, requestId } = params;
  const endpoint = buildTelegraQuestionnaireInstanceUrl(
    baseUrl,
    questionnaireInstanceId,
  );
  const requestEndpoint = appendTelegraRequestTimestamp(endpoint);

  const response = await fetch(requestEndpoint, {
    method: "GET",
    headers: {
      Authorization: `Bearer ${accessToken}`,
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
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
      `Telegra questionnaire instance fetch failed for ${questionnaireInstanceId}: ${response.status} ${response.statusText}` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

export async function validateTelegraQuestionnairesForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  requestId: string;
}): Promise<TelegraQuestionnaireValidationResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForTelegra(order)) {
    return {
      applicable: false,
      validated: true,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: "Order provider platform integration is not Telegra",
      questionnaireInstanceIds: [],
    };
  }

  const providerLinkLookup = await fetchOrderedProviderLinksForOrder({
    supabase,
    order,
    requestId,
  });

  if (providerLinkLookup.errorMessage) {
    return {
      applicable: true,
      validated: false,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: providerLinkLookup.errorMessage,
      questionnaireInstanceIds: [],
    };
  }

  const selectedProviderLink = providerLinkLookup.links[0] || null;
  const telegraProviderLink = selectedProviderLink &&
      isTelegraOrderProviderLink(selectedProviderLink, order)
    ? selectedProviderLink
    : null;

  if (!telegraProviderLink) {
    return {
      applicable: false,
      validated: true,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: "Order is not linked to a Telegra provider platform integration",
      questionnaireInstanceIds: [],
    };
  }

  const questionnaireInstanceIds = extractQuestionnaireInstanceIdsFromMetadata(
    telegraProviderLink.metadata,
  );

  if (questionnaireInstanceIds.length === 0) {
    return {
      applicable: true,
      validated: true,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: "Waiting for Telegra questionnaire instance ids to be available",
      questionnaireInstanceIds,
    };
  }

  const baseUrl = getStringSetting(
    telegraProviderLink.tenant_integrations.settings,
    "url",
  );

  if (!baseUrl) {
    return {
      applicable: true,
      validated: false,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing URL configuration",
      questionnaireInstanceIds,
    };
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraProviderLink.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: telegraProviderLink.tenant_integrations.settings,
    baseUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      applicable: true,
      validated: false,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: authResult.errorMessage,
      questionnaireInstanceIds,
    };
  }

  try {
    const questionnaires = await Promise.all(
      questionnaireInstanceIds.map(async (questionnaireInstanceId) => {
        const questionnaire = await fetchTelegraQuestionnaireInstance({
          questionnaireInstanceId,
          baseUrl,
          accessToken: authResult.accessToken,
          requestId,
        });

        return {
          id: questionnaireInstanceId,
          status: extractTelegraQuestionnaireStatus(questionnaire),
          valid: extractTelegraQuestionnaireValid(questionnaire),
        };
      }),
    );

    const allCompletedAndValid = areTelegraQuestionnairesCompletedAndValid(
      questionnaires,
    );

    const pendingQuestionnaireIds = questionnaires
      .filter((questionnaire) => questionnaire.valid !== true)
      .map((questionnaire) => questionnaire.id);

    return {
      applicable: true,
      validated: true,
      allCompletedAndValid,
      providerName: "TelegraMD",
      message: allCompletedAndValid
        ? "All Telegra questionnaires are valid"
        : `Waiting for valid Telegra questionnaires: ${
          pendingQuestionnaireIds.join(
            ", ",
          )
        }`,
      questionnaireInstanceIds,
    };
  } catch (error) {
    return {
      applicable: true,
      validated: false,
      allCompletedAndValid: false,
      providerName: "TelegraMD",
      message: error instanceof Error ? error.message : String(error),
      questionnaireInstanceIds,
    };
  }
}

export async function sendTelegraOrderToPharmacyForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  requestId: string;
}): Promise<TelegraOrderSendToPharmacyResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForTelegra(order)) {
    return {
      applicable: false,
      sent: false,
      providerName: "TelegraMD",
      message: "Order provider platform integration is not Telegra",
      externalOrderId: null,
    };
  }

  const providerLinkLookup = await fetchOrderedProviderLinksForOrder({
    supabase,
    order,
    requestId,
  });

  if (providerLinkLookup.errorMessage) {
    return {
      applicable: true,
      sent: false,
      providerName: "TelegraMD",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const selectedProviderLink = providerLinkLookup.links[0] || null;
  const telegraProviderLink = selectedProviderLink &&
      isTelegraOrderProviderLink(selectedProviderLink, order)
    ? selectedProviderLink
    : null;

  if (!telegraProviderLink) {
    return {
      applicable: false,
      sent: false,
      providerName: "TelegraMD",
      message: "Order is not linked to a Telegra provider platform integration",
      externalOrderId: null,
    };
  }

  const providerOrderId = normalizeTelegraOrderScopedId(
    telegraProviderLink.provider_order_id,
  );
  if (!providerOrderId) {
    return {
      applicable: true,
      sent: false,
      providerName: "TelegraMD",
      message: "Telegra provider order id is missing or invalid for this order",
      externalOrderId: null,
    };
  }

  const baseUrl = getStringSetting(
    telegraProviderLink.tenant_integrations.settings,
    "url",
  );

  if (!baseUrl) {
    return {
      applicable: true,
      sent: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing URL configuration",
      externalOrderId: providerOrderId,
    };
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraProviderLink.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: telegraProviderLink.tenant_integrations.settings,
    baseUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      applicable: true,
      sent: false,
      providerName: "TelegraMD",
      message: authResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  const endpoint = buildTelegraSendToPharmacyRecipientsUrl(baseUrl);
  const requestEndpoint = appendTelegraRequestTimestamp(endpoint);

  console.info("Calling Telegra sendToPharmacyRecipients", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    providerOrderId,
    endpoint: requestEndpoint,
  });

  const response = await fetch(requestEndpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authResult.accessToken}`,
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
    },
    body: JSON.stringify({
      orderIdentifier: providerOrderId,
    }),
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

  console.info("Telegra sendToPharmacyRecipients response received", {
    requestId,
    orderId: order.id,
    providerOrderId,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    return {
      applicable: true,
      sent: false,
      providerName: "TelegraMD",
      message: `Telegra sendToPharmacyRecipients failed: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
      externalOrderId: providerOrderId,
    };
  }

  return {
    applicable: true,
    sent: true,
    providerName: "TelegraMD",
    message: "Telegra order sent to pharmacy recipients successfully",
    externalOrderId: providerOrderId,
  };
}

export async function cancelTelegraOrderForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  requestId: string;
}): Promise<TelegraOrderCancelResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForTelegra(order)) {
    return {
      applicable: false,
      cancelled: false,
      providerName: "TelegraMD",
      message: "Order provider platform integration is not Telegra",
      externalOrderId: null,
    };
  }

  const providerLinkLookup = await fetchOrderedProviderLinksForOrder({
    supabase,
    order,
    requestId,
  });

  if (providerLinkLookup.errorMessage) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "TelegraMD",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const selectedProviderLink = providerLinkLookup.links[0] || null;
  const telegraProviderLink = selectedProviderLink &&
      isTelegraOrderProviderLink(selectedProviderLink, order)
    ? selectedProviderLink
    : null;

  if (!telegraProviderLink) {
    return {
      applicable: false,
      cancelled: false,
      providerName: "TelegraMD",
      message: "Order is not linked to a Telegra provider platform integration",
      externalOrderId: null,
    };
  }

  const providerOrderId = normalizeTelegraOrderScopedId(
    telegraProviderLink.provider_order_id,
  );
  if (!providerOrderId) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "TelegraMD",
      message: "Telegra provider order id is missing or invalid for this order",
      externalOrderId: null,
    };
  }

  const baseUrl = getStringSetting(
    telegraProviderLink.tenant_integrations.settings,
    "url",
  );

  if (!baseUrl) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing URL configuration",
      externalOrderId: providerOrderId,
    };
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraProviderLink.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: telegraProviderLink.tenant_integrations.settings,
    baseUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "TelegraMD",
      message: authResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  const endpoint = buildTelegraCancelOrderUrl(baseUrl);
  const cancelEndpoint = endpoint.replace(
    "{orderId}",
    encodeURIComponent(providerOrderId),
  );
  const requestEndpoint = appendTelegraRequestTimestamp(cancelEndpoint);

  console.info("Calling Telegra order cancel", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    providerOrderId,
    endpoint: requestEndpoint,
  });

  const response = await fetch(requestEndpoint, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${authResult.accessToken}`,
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
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

  console.info("Telegra order cancel response received", {
    requestId,
    orderId: order.id,
    providerOrderId,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "TelegraMD",
      message: `Telegra cancel failed: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
      externalOrderId: providerOrderId,
    };
  }

  return {
    applicable: true,
    cancelled: true,
    providerName: "TelegraMD",
    message: "Telegra order cancelled successfully",
    externalOrderId: providerOrderId,
  };
}

export async function leaveTelegraWaitingRoomForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  requestId: string;
}): Promise<TelegraLeaveWaitingRoomResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForTelegra(order)) {
    return {
      applicable: false,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: "Order provider platform integration is not Telegra",
      externalOrderId: null,
    };
  }

  const providerLinkLookup = await fetchOrderedProviderLinksForOrder({
    supabase,
    order,
    requestId,
  });

  if (providerLinkLookup.errorMessage) {
    return {
      applicable: true,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const selectedProviderLink = providerLinkLookup.links[0] || null;
  const telegraProviderLink = selectedProviderLink &&
      isTelegraOrderProviderLink(selectedProviderLink, order)
    ? selectedProviderLink
    : null;

  if (!telegraProviderLink) {
    return {
      applicable: false,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: "Order is not linked to a Telegra provider platform integration",
      externalOrderId: null,
    };
  }

  const providerOrderId = normalizeTelegraOrderScopedId(
    telegraProviderLink.provider_order_id,
  );
  if (!providerOrderId) {
    return {
      applicable: true,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: "Telegra provider order id is missing or invalid for this order",
      externalOrderId: null,
    };
  }

  const existingMetadata =
    telegraProviderLink.metadata && typeof telegraProviderLink.metadata ===
        "object"
      ? telegraProviderLink.metadata
      : {};

  if (
    typeof existingMetadata.telegra_leave_waiting_room_requested_at ===
      "string" &&
    existingMetadata.telegra_leave_waiting_room_requested_at.trim().length > 0
  ) {
    return {
      applicable: true,
      triggered: true,
      alreadyTriggered: true,
      providerName: "TelegraMD",
      message: "Telegra leaveWaitingRoom already requested for this order",
      externalOrderId: providerOrderId,
    };
  }

  const baseUrl = getStringSetting(
    telegraProviderLink.tenant_integrations.settings,
    "url",
  );

  if (!baseUrl) {
    return {
      applicable: true,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing URL configuration",
      externalOrderId: providerOrderId,
    };
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraProviderLink.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: telegraProviderLink.tenant_integrations.settings,
    baseUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      applicable: true,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: authResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  const endpoint = buildTelegraLeaveWaitingRoomUrl(baseUrl);
  const leaveWaitingRoomEndpoint = endpoint.replace(
    "{orderId}",
    encodeURIComponent(providerOrderId),
  );
  const requestEndpoint = appendTelegraRequestTimestamp(
    leaveWaitingRoomEndpoint,
  );

  console.info("Calling Telegra leaveWaitingRoom", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    providerOrderId,
    endpoint: requestEndpoint,
  });

  const response = await fetch(requestEndpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authResult.accessToken}`,
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
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

  console.info("Telegra leaveWaitingRoom response received", {
    requestId,
    orderId: order.id,
    providerOrderId,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    return {
      applicable: true,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message: `Telegra leaveWaitingRoom failed: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
      externalOrderId: providerOrderId,
    };
  }

  const requestedAt = dateTime().toISOString();
  const { error: updateError } = await supabase
    .from("order_provider_platform_links")
    .update({
      metadata: {
        ...existingMetadata,
        telegra_leave_waiting_room_requested_at: requestedAt,
        telegra_leave_waiting_room_request_id: requestId,
      },
    })
    .eq("id", telegraProviderLink.id)
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id);

  if (updateError) {
    return {
      applicable: true,
      triggered: false,
      alreadyTriggered: false,
      providerName: "TelegraMD",
      message:
        `Telegra leaveWaitingRoom succeeded but metadata update failed: ${updateError.message}`,
      externalOrderId: providerOrderId,
    };
  }

  return {
    applicable: true,
    triggered: true,
    alreadyTriggered: false,
    providerName: "TelegraMD",
    message: "Telegra leaveWaitingRoom requested successfully",
    externalOrderId: providerOrderId,
  };
}

export async function createTelegraOrderForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForTelegra;
  requestId: string;
}): Promise<TelegraOrderCreationResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForTelegra(order)) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: "Order provider platform integration is not Telegra",
      externalOrderId: null,
    };
  }

  if (!order.product_id) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: "Order is missing a product_id",
      externalOrderId: null,
    };
  }

  const providerLinkLookup = await fetchOrderedProviderLinksForOrder({
    supabase,
    order,
    requestId,
  });

  if (providerLinkLookup.errorMessage) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const selectedProviderLink = providerLinkLookup.links[0] || null;

  if (
    selectedProviderLink?.tenant_integrations?.integration_key &&
    selectedProviderLink.tenant_integrations.integration_key !== "telegramd"
  ) {
    return {
      created: false,
      providerName: selectedProviderLink.tenant_integrations.integration_key,
      message:
        `Selected provider platform ${selectedProviderLink.tenant_integrations.integration_key} is not yet supported for lifecycle order creation`,
      externalOrderId: null,
    };
  }

  const { data: productProviderAssignments, error: assignmentsError } =
    await supabase
      .from("product_provider_platforms")
      .select(
        `
      id,
      provider_product_variation_sku,
      tenant_integration_id,
      tenant_integrations!inner (
        id,
        tenant_id,
        integration_key,
        is_enabled,
        settings
      )
    `,
      )
      .eq("product_id", order.product_id)
      .eq("is_enabled", true);

  if (assignmentsError) {
    return {
      created: false,
      providerName: "TelegraMD",
      message:
        `Failed to fetch provider platform assignment: ${assignmentsError.message}`,
      externalOrderId: null,
    };
  }

  const telegraAssignment = (
    (productProviderAssignments || []) as ProductProviderPlatformAssignment[]
  ).find(
    (assignment) =>
      assignment.tenant_integrations?.tenant_id === order.tenant_id &&
      assignment.tenant_integrations?.is_enabled &&
      assignment.tenant_integrations?.integration_key === "telegramd" &&
      (!selectedProviderLink ||
        assignment.tenant_integration_id ===
          selectedProviderLink.tenant_integration_id),
  );

  if (!telegraAssignment) {
    return {
      created: false,
      providerName: "TelegraMD",
      message:
        "No enabled Telegra provider integration is configured for this product",
      externalOrderId: null,
    };
  }

  const baseUrl = getStringSetting(
    telegraAssignment.tenant_integrations.settings,
    "url",
  );

  if (!baseUrl) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing URL configuration",
      externalOrderId: null,
    };
  }

  const projectId = getStringSetting(
    telegraAssignment.tenant_integrations.settings,
    "project_id",
  );

  if (!projectId) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing Project ID configuration",
      externalOrderId: null,
    };
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraAssignment.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: telegraAssignment.tenant_integrations.settings,
    baseUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: authResult.errorMessage,
      externalOrderId: null,
    };
  }

  const { data: patient, error: patientError } = await supabase
    .from("patients")
    .select("id, first_name, last_name, email, phone, date_of_birth")
    .eq("id", order.patient_id)
    .eq("tenant_id", order.tenant_id)
    .maybeSingle();

  if (patientError) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: `Failed to fetch patient details: ${patientError.message}`,
      externalOrderId: null,
    };
  }

  if (!patient) {
    return {
      created: false,
      providerName: "TelegraMD",
      message: "Patient not found for order",
      externalOrderId: null,
    };
  }

  const payload = buildTelegraCreateOrderPayload({
    order,
    patient: patient as PatientForTelegra,
    providerProductVariationSku:
      telegraAssignment.provider_product_variation_sku,
    projectId,
  });

  const endpoint = buildTelegraOrdersUrl(baseUrl);
  const requestEndpoint = appendTelegraRequestTimestamp(endpoint);
  const providerCreationClaim = await claimProviderOrderCreation({
    supabase,
    order,
    tenantIntegrationId: telegraAssignment.tenant_integration_id,
    requestId,
  });

  if (!providerCreationClaim.claimed) {
    console.info(
      "Skipped Telegra create order because creation is already claimed",
      {
        requestId,
        orderId: order.id,
        tenantIntegrationId: telegraAssignment.tenant_integration_id,
        providerOrderId: providerCreationClaim.provider_order_id,
        inProgress: providerCreationClaim.in_progress,
        message: providerCreationClaim.message,
      },
    );

    return {
      created: true,
      providerName: "TelegraMD",
      message: providerCreationClaim.message ||
        "Telegra order creation is already in progress",
      externalOrderId: normalizeTelegraOrderScopedId(
        providerCreationClaim.provider_order_id,
      ),
    };
  }

  const requestBody = JSON.stringify(payload);
  console.info("Telegra create order request parameters", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    tenantIntegrationId: telegraAssignment.tenant_integration_id,
    method: "POST",
    endpoint: requestEndpoint,
    headers: {
      Authorization: "Bearer <redacted>",
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
    },
    parameters: payload,
  });

  let response: Response;
  try {
    response = await fetch(requestEndpoint, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${authResult.accessToken}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-source": "order-lifecycle",
      },
      body: requestBody,
    });
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    await updateProviderOrderCreationClaimStatus({
      supabase,
      order,
      tenantIntegrationId: telegraAssignment.tenant_integration_id,
      status: "failed",
      requestId,
      errorMessage,
    });

    console.error("Telegra create order request failed before response", {
      requestId,
      orderId: order.id,
      endpoint: requestEndpoint,
      error: errorMessage,
    });

    return {
      created: false,
      providerName: "TelegraMD",
      message: `Telegra order creation failed before response: ${errorMessage}`,
      externalOrderId: null,
    };
  }

  const rawResponse = await response.text();
  let responseBody: unknown = null;

  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  console.info("Telegra create order response received", {
    requestId,
    orderId: order.id,
    httpStatus: response.status,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
    response: responseBody,
  });

  if (!response.ok) {
    const errorMessage = extractErrorMessage(
      responseBody,
      `${response.status} ${response.statusText}`.trim(),
    );
    await updateProviderOrderCreationClaimStatus({
      supabase,
      order,
      tenantIntegrationId: telegraAssignment.tenant_integration_id,
      status: "failed",
      requestId,
      errorMessage,
    });

    return {
      created: false,
      providerName: "TelegraMD",
      message: `Telegra order creation failed: ${errorMessage}`,
      externalOrderId: extractTelegraProviderOrderId(responseBody),
    };
  }

  const providerOrderId = extractTelegraProviderOrderId(responseBody);
  const providerPatientId = extractTelegraProviderPatientId(responseBody);
  const questionnaireInstanceIds = extractTelegraQuestionnaireInstanceIds(
    responseBody,
  );

  if (!providerOrderId) {
    console.warn(
      "Telegra order created without a provider order id in the response",
      {
        requestId,
        orderId: order.id,
        tenantIntegrationId: telegraAssignment.tenant_integration_id,
      },
    );
  }

  await upsertOrderProviderPlatformLink({
    supabase,
    order,
    tenantIntegrationId: telegraAssignment.tenant_integration_id,
    providerOrderId,
    questionnaireInstanceIds,
    requestId,
  });

  if (!providerPatientId) {
    return {
      created: false,
      providerName: "TelegraMD",
      message:
        "Telegra order creation succeeded but no provider patient id was returned",
      externalOrderId: providerOrderId,
    };
  }

  await upsertPatientProviderPlatformLink({
    supabase,
    order,
    tenantIntegrationId: telegraAssignment.tenant_integration_id,
    providerPatientId,
    requestId,
  });
  const telegraHistoryId = await appendTelegraOrderCreatedHistory({
    supabase,
    order,
    providerOrderId,
    requestId,
  });
  await triggerRtdhProviderPlatformNewOrder({
    supabase,
    requestId,
    tenantId: order.tenant_id,
    orderId: order.id,
    orderStatusHistoryId: telegraHistoryId,
    patientId: order.patient_id,
    providerPatientId,
    providerPlatformKey: "telegramd",
    providerPlatformOrderId: providerOrderId,
  });

  return {
    created: true,
    providerName: "TelegraMD",
    message: "Telegra order created successfully",
    externalOrderId: providerOrderId,
  };
}
