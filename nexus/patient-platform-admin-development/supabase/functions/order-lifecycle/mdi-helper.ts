import { dateTime } from "../_shared/dayjs.ts";
import { resolveMdiAccessToken } from "../_shared/mdi-auth.ts";
import { triggerRtdhProviderPlatformNewOrder } from "./rtdh-helper.ts";

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;

export interface OrderForMdi {
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
  provider_platform_integration_key: string | null;
}

interface PatientForMdi {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  date_of_birth: string | null;
}

interface TenantIntegrationForMdi {
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
  tenant_integrations: TenantIntegrationForMdi;
}

interface OrderProviderPlatformLinkForMdi {
  id: string;
  metadata: Record<string, unknown> | null;
  provider_order_id: string | null;
  tenant_integration_id: string;
  tenant_integrations: TenantIntegrationForMdi;
}

export interface MdiOrderCreationResult {
  created: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

export interface MdiCaseProcessingResult {
  applicable: boolean;
  processingRequested: boolean;
  alreadyRequested?: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

export interface MdiCaseCancelResult {
  applicable: boolean;
  cancelled: boolean;
  providerName: string;
  message: string;
  externalOrderId: string | null;
}

interface MdiPatientResponse {
  patient_id: string;
  [key: string]: unknown;
}

interface MdiCaseResponse {
  case_id: string;
  [key: string]: unknown;
}

interface MdiMedicationOffering {
  medication_id: string;
  medication_title: string | null;
  offering_id: string;
}

// ---------------------------------------------------------------------------
// Utility helpers
// ---------------------------------------------------------------------------

function normalizeIntegrationKey(
  value: string | null | undefined,
): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

function isOrderForMdi(order: OrderForMdi): boolean {
  return (
    normalizeIntegrationKey(order.provider_platform_integration_key) ===
      "md_integrations"
  );
}

function getStringSetting(
  settings: Record<string, unknown> | null | undefined,
  key: string,
): string | null {
  const value = settings?.[key];
  if (typeof value !== "string") return null;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

function parseNonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

function uniqueNonEmptyStrings(
  values: Array<string | null | undefined>,
): string[] {
  return Array.from(
    new Set(
      values
        .map((value) => parseNonEmptyString(value))
        .filter((value): value is string => value !== null),
    ),
  );
}

function compactObject(
  record: Record<string, unknown>,
): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(record).filter(
      ([, v]) => v !== null && v !== undefined && v !== "",
    ),
  );
}

function metadataRecord(
  metadata: Record<string, unknown> | null | undefined,
): Record<string, unknown> {
  if (!metadata || typeof metadata !== "object" || Array.isArray(metadata)) {
    return {};
  }

  return metadata;
}

function sanitizeMdiNameField(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^A-Za-z' -]/g, "")
    .replace(/\s+/g, " ")
    .trim();
}

function sanitizeMdiLastName(value: string): string {
  const sanitized = sanitizeMdiNameField(value);
  const withoutLeadingInitials = sanitized.replace(
    /^([A-Za-z]\s+)+(?=[A-Za-z][A-Za-z' -]*$)/,
    "",
  );

  return withoutLeadingInitials || sanitized;
}

function extractErrorMessage(
  responseBody: unknown,
  fallback: string,
): string {
  if (!responseBody || typeof responseBody !== "object") return fallback;
  const record = responseBody as Record<string, unknown>;
  for (const key of ["message", "error", "detail"]) {
    const value = record[key];
    if (typeof value === "string" && value.trim().length > 0) return value;
  }
  return fallback;
}

function getMdiProcessingRequestedAt(
  metadata: Record<string, unknown> | null | undefined,
): string | null {
  const value = metadataRecord(metadata).mdi_processing_requested_at;
  if (typeof value !== "string") return null;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

function getMdiCancelledAt(
  metadata: Record<string, unknown> | null | undefined,
): string | null {
  const value = metadataRecord(metadata).mdi_cancelled_at;
  if (typeof value !== "string") return null;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

// ---------------------------------------------------------------------------
// MDI Patient Creation
// ---------------------------------------------------------------------------

export function buildMdiCreatePatientPayload(params: {
  order: OrderForMdi;
  patient: PatientForMdi;
}): Record<string, unknown> {
  const { order, patient } = params;
  const firstName = sanitizeMdiNameField(patient.first_name);
  const lastName = sanitizeMdiLastName(patient.last_name);

  const phone =
    typeof patient.phone === "string" && patient.phone.trim().length > 0
      ? patient.phone.trim()
      : null;

  const addressFields = compactObject({
    address: order.shipping_address_line1,
    address2: order.shipping_address_line2,
    zip_code: order.shipping_postal_code,
    city_name: order.shipping_city,
    state_name: order.shipping_state,
  });

  // TODO: date_of_birth and gender are hardcoded placeholders.
  // Replace with actual patient data once collected from the patient intake flow.
  console.warn(
    "MDI patient payload uses hardcoded date_of_birth and gender — replace with real patient data in the future",
  );

  return compactObject({
    first_name: firstName,
    last_name: lastName,
    email: patient.email,
    phone_number: phone,
    phone_type: 2,
    date_of_birth: "2000-01-01",
    gender: 2,
    address: Object.keys(addressFields).length > 0 ? addressFields : null,
    is_email_enabled: true,
    metadata: patient.id,
  });
}

async function createMdiPatient(params: {
  backendUrl: string;
  accessToken: string;
  order: OrderForMdi;
  patient: PatientForMdi;
  requestId: string;
}): Promise<{ patientId: string } | { errorMessage: string }> {
  const { backendUrl, accessToken, order, patient, requestId } = params;
  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/patients`;

  const payload = buildMdiCreatePatientPayload({ order, patient });

  console.info("Creating patient in MDI", {
    requestId,
    orderId: order.id,
    patientId: patient.id,
    endpoint,
  });

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
    },
    body: JSON.stringify(payload),
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

  console.info("MDI create patient response received", {
    requestId,
    orderId: order.id,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    const msg = extractErrorMessage(
      responseBody,
      `${response.status} ${response.statusText}`.trim(),
    );
    return { errorMessage: `MDI patient creation failed: ${msg}` };
  }

  const body = responseBody as MdiPatientResponse | null;
  const mdiPatientId = body?.patient_id;

  if (typeof mdiPatientId !== "string" || mdiPatientId.trim().length === 0) {
    return {
      errorMessage:
        "MDI patient creation succeeded but no patient_id was returned",
    };
  }

  console.info("MDI patient created", {
    requestId,
    orderId: order.id,
    mdiPatientId,
  });

  return { patientId: mdiPatientId };
}

// ---------------------------------------------------------------------------
// Persistence helpers
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// MDI Case Creation
// ---------------------------------------------------------------------------

export function buildMdiCreateCasePayload(params: {
  mdiPatientId: string;
  orderNumber: string;
  offeringId?: string | null;
  offeringIds?: string[] | null;
}): Record<string, unknown> {
  const { mdiPatientId, orderNumber, offeringId, offeringIds } = params;

  const normalizedOfferingIds = Array.isArray(offeringIds)
    ? uniqueNonEmptyStrings(offeringIds)
    : uniqueNonEmptyStrings([offeringId]);
  const caseOfferings = normalizedOfferingIds.map((id) => ({
    offering_id: id,
  }));

  return {
    hold_status: true,
    patient_id: mdiPatientId,
    metadata: `Order Number ${orderNumber}`,
    is_additional_approval_needed: true,
    case_files: [],
    case_offerings: caseOfferings,
  };
}

async function createMdiCase(params: {
  backendUrl: string;
  accessToken: string;
  mdiPatientId: string;
  orderNumber: string;
  offeringIds: string[];
  orderId: string;
  requestId: string;
}): Promise<{ caseId: string } | { errorMessage: string }> {
  const {
    backendUrl,
    accessToken,
    mdiPatientId,
    orderNumber,
    offeringIds,
    orderId,
    requestId,
  } = params;

  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases`;
  const payload = buildMdiCreateCasePayload({
    mdiPatientId,
    orderNumber,
    offeringIds,
  });

  console.info("Creating case in MDI", {
    requestId,
    orderId,
    mdiPatientId,
    offeringIds,
    endpoint,
  });

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
    },
    body: JSON.stringify(payload),
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

  console.info("MDI create case response received", {
    requestId,
    orderId,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    const msg = extractErrorMessage(
      responseBody,
      `${response.status} ${response.statusText}`.trim(),
    );
    return { errorMessage: `MDI case creation failed: ${msg}` };
  }

  const body = responseBody as MdiCaseResponse | null;
  const caseId = body?.case_id;

  if (typeof caseId !== "string" || caseId.trim().length === 0) {
    return {
      errorMessage: "MDI case creation succeeded but no case_id was returned",
    };
  }

  console.info("MDI case created", {
    requestId,
    orderId,
    caseId,
  });

  return { caseId };
}

export function buildMdiCaseProcessingUrl(
  backendUrl: string,
  providerOrderId: string,
): string {
  return `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases/${
    encodeURIComponent(providerOrderId)
  }/processing`;
}

export function buildMdiCaseCancelUrl(
  backendUrl: string,
  providerOrderId: string,
): string {
  return `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases/${
    encodeURIComponent(providerOrderId)
  }/cancel`;
}

export function buildMdiCaseCancelPayload(): Record<string, unknown> {
  return {
    reason:
      "Patient requested cancellation before provider review was completed.",
  };
}

async function requestMdiCaseProcessing(params: {
  backendUrl: string;
  accessToken: string;
  providerOrderId: string;
  orderId: string;
  requestId: string;
}): Promise<{ ok: true } | { ok: false; errorMessage: string }> {
  const { backendUrl, accessToken, providerOrderId, orderId, requestId } =
    params;
  const endpoint = buildMdiCaseProcessingUrl(backendUrl, providerOrderId);

  console.info("Requesting MDI case processing", {
    requestId,
    orderId,
    providerOrderId,
    endpoint,
  });

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${accessToken}`,
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

  console.info("MDI case processing response received", {
    requestId,
    orderId,
    providerOrderId,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    const msg = extractErrorMessage(
      responseBody,
      `${response.status} ${response.statusText}`.trim(),
    );
    return { ok: false, errorMessage: `MDI case processing failed: ${msg}` };
  }

  return { ok: true };
}

async function cancelMdiCase(params: {
  backendUrl: string;
  accessToken: string;
  providerOrderId: string;
  orderId: string;
  requestId: string;
}): Promise<{ ok: true } | { ok: false; errorMessage: string }> {
  const { backendUrl, accessToken, providerOrderId, orderId, requestId } =
    params;
  const endpoint = buildMdiCaseCancelUrl(backendUrl, providerOrderId);
  const payload = buildMdiCaseCancelPayload();

  console.info("Cancelling MDI case", {
    requestId,
    orderId,
    providerOrderId,
    endpoint,
  });

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "order-lifecycle",
    },
    body: JSON.stringify(payload),
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

  console.info("MDI case cancel response received", {
    requestId,
    orderId,
    providerOrderId,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
  });

  if (!response.ok) {
    const msg = extractErrorMessage(
      responseBody,
      `${response.status} ${response.statusText}`.trim(),
    );
    return { ok: false, errorMessage: `MDI case cancellation failed: ${msg}` };
  }

  return { ok: true };
}

async function markMdiCaseProcessingRequested(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  providerLink: OrderProviderPlatformLinkForMdi;
  requestId: string;
}): Promise<void> {
  const { supabase, order, providerLink, requestId } = params;
  const now = dateTime().toISOString();
  const existingMetadata = metadataRecord(providerLink.metadata);

  const { error } = await supabase
    .from("order_provider_platform_links")
    .update({
      metadata: {
        ...existingMetadata,
        source: "order-lifecycle",
        provider: "MDI",
        order_number: order.order_number,
        mdi_processing_requested_at: now,
        last_received_at: now,
      },
    })
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("tenant_integration_id", providerLink.tenant_integration_id);

  if (error) {
    throw new Error(
      `Failed to persist MDI case processing marker: ${error.message}`,
    );
  }

  console.info("Persisted MDI case processing marker", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    tenantIntegrationId: providerLink.tenant_integration_id,
    providerOrderId: providerLink.provider_order_id,
  });
}

async function markMdiCaseCancelled(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  providerLink: OrderProviderPlatformLinkForMdi;
  requestId: string;
}): Promise<void> {
  const { supabase, order, providerLink, requestId } = params;
  const now = dateTime().toISOString();
  const existingMetadata = metadataRecord(providerLink.metadata);

  const { error } = await supabase
    .from("order_provider_platform_links")
    .update({
      metadata: {
        ...existingMetadata,
        source: "order-lifecycle",
        provider: "MDI",
        order_number: order.order_number,
        mdi_cancelled_at: now,
        mdi_cancellation_reason:
          "Patient requested cancellation before provider review was completed.",
        last_received_at: now,
      },
    })
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("tenant_integration_id", providerLink.tenant_integration_id);

  if (error) {
    throw new Error(
      `Failed to persist MDI case cancellation marker: ${error.message}`,
    );
  }

  console.info("Persisted MDI case cancellation marker", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    tenantIntegrationId: providerLink.tenant_integration_id,
    providerOrderId: providerLink.provider_order_id,
  });
}

// ---------------------------------------------------------------------------
// Persistence helpers
// ---------------------------------------------------------------------------

async function upsertOrderProviderPlatformLink(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  tenantIntegrationId: string;
  providerOrderId: string | null;
  mdiPatientId: string | null;
  medicationOfferings?: MdiMedicationOffering[];
  requestId: string;
}): Promise<void> {
  const {
    supabase,
    order,
    tenantIntegrationId,
    providerOrderId,
    mdiPatientId,
    medicationOfferings = [],
    requestId,
  } = params;

  const payload: Record<string, unknown> = {
    tenant_id: order.tenant_id,
    order_id: order.id,
    tenant_integration_id: tenantIntegrationId,
    metadata: {
      source: "order-lifecycle",
      provider: "MDI",
      order_number: order.order_number,
      mdi_patient_id: mdiPatientId,
      mdi_medication_offerings: medicationOfferings.map((offering) => ({
        medication_id: offering.medication_id,
        medication_title: offering.medication_title,
        offering_id: offering.offering_id,
      })),
      mdi_offering_ids: medicationOfferings.map((offering) =>
        offering.offering_id
      ),
      last_received_at: dateTime().toISOString(),
    },
  };

  if (providerOrderId) {
    payload.provider_order_id = providerOrderId;
  }

  const { error } = await supabase
    .from("order_provider_platform_links")
    .upsert(payload, {
      onConflict: "order_id,tenant_integration_id",
      ignoreDuplicates: false,
    });

  if (error) {
    throw new Error(
      `Failed to persist MDI order provider platform link: ${error.message}`,
    );
  }

  if (providerOrderId) {
    const { error: orderUpdateError } = await supabase
      .from("orders")
      .update({ provider_platform_order_id: providerOrderId })
      .eq("id", order.id)
      .eq("tenant_id", order.tenant_id);

    if (orderUpdateError) {
      throw new Error(
        `Failed to persist MDI provider order id on order: ${orderUpdateError.message}`,
      );
    }
  }

  console.info("Persisted MDI order provider platform link", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    tenantIntegrationId,
    providerOrderId,
    mdiPatientId,
  });
}

async function upsertPatientProviderPlatformLink(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  tenantIntegrationId: string;
  mdiPatientId: string;
  requestId: string;
}): Promise<void> {
  const { supabase, order, tenantIntegrationId, mdiPatientId, requestId } =
    params;

  const payload: Record<string, unknown> = {
    tenant_id: order.tenant_id,
    patient_id: order.patient_id,
    tenant_integration_id: tenantIntegrationId,
    provider_patient_id: mdiPatientId,
    metadata: {
      source: "order-lifecycle",
      provider: "MDI",
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
    console.warn("Failed to upsert MDI patient provider platform link", {
      requestId,
      orderId: order.id,
      patientId: order.patient_id,
      tenantId: order.tenant_id,
      tenantIntegrationId,
      mdiPatientId,
      error: error.message,
    });
  }
}

// ---------------------------------------------------------------------------
// Provider link resolution (deterministic, strict single-link)
// ---------------------------------------------------------------------------

async function fetchOrderedProviderLinksForOrder(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  requestId: string;
}): Promise<
  | { links: OrderProviderPlatformLinkForMdi[]; errorMessage: null }
  | { links: []; errorMessage: string }
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

  const orderedLinks = (
    (providerLinks || []) as OrderProviderPlatformLinkForMdi[]
  )
    .slice()
    .sort((a, b) => a.id.localeCompare(b.id));

  if (orderedLinks.length > 1) {
    console.error("Invalid provider platform link cardinality for order", {
      requestId,
      orderId: order.id,
      tenantId: order.tenant_id,
      linkIds: orderedLinks.map((l) => l.id),
    });
    return {
      links: [],
      errorMessage:
        `Invalid provider platform selection state: expected at most one selected provider link, found ${orderedLinks.length}`,
    };
  }

  return { links: orderedLinks, errorMessage: null };
}

function formatMedicationOfferingLabel(
  offering: Pick<MdiMedicationOffering, "medication_id" | "medication_title">,
): string {
  return offering.medication_title
    ? `${offering.medication_title} (${offering.medication_id})`
    : offering.medication_id;
}

async function fetchMdiMedicationOfferingsForProduct(params: {
  supabase: SupabaseClient;
  productId: string;
}): Promise<
  | { offerings: MdiMedicationOffering[]; errorMessage: null }
  | { offerings: []; errorMessage: string }
> {
  const { supabase, productId } = params;

  const { data, error } = await supabase
    .from("product_medications")
    .select(
      `
      medication_id,
      medication:medications (
        id,
        title,
        offering_id
      )
    `,
    )
    .eq("product_id", productId);

  if (error) {
    return {
      offerings: [],
      errorMessage:
        `Failed to fetch product medications for MDI offering resolution: ${error.message}`,
    };
  }

  const rows = (data || []) as Array<{
    medication_id: string;
    medication:
      | {
        id: string;
        title: string | null;
        offering_id: string | null;
      }
      | Array<{
        id: string;
        title: string | null;
        offering_id: string | null;
      }>
      | null;
  }>;

  if (rows.length === 0) {
    return {
      offerings: [],
      errorMessage: "No linked medications are configured for this MDI product",
    };
  }

  const medicationOfferings = rows.map((row) => {
    const medication = Array.isArray(row.medication)
      ? row.medication[0] || null
      : row.medication;

    return {
      medication_id: row.medication_id,
      medication_title: parseNonEmptyString(medication?.title),
      offering_id: parseNonEmptyString(medication?.offering_id) || "",
    };
  });

  const missingOfferingIds = medicationOfferings.filter((offering) =>
    !offering.offering_id
  );
  if (missingOfferingIds.length > 0) {
    return {
      offerings: [],
      errorMessage: `MDI offering_id is missing for linked medication(s): ${
        missingOfferingIds.map(formatMedicationOfferingLabel).join(", ")
      }`,
    };
  }

  const offeringCounts = new Map<string, MdiMedicationOffering[]>();
  for (const offering of medicationOfferings) {
    const matches = offeringCounts.get(offering.offering_id) || [];
    matches.push(offering);
    offeringCounts.set(offering.offering_id, matches);
  }

  const duplicateOfferings = Array.from(offeringCounts.entries()).filter(([
    ,
    offerings,
  ]) => offerings.length > 1);
  if (duplicateOfferings.length > 0) {
    return {
      offerings: [],
      errorMessage:
        `MDI offering_id must be distinct for each linked medication. Duplicate value(s): ${
          duplicateOfferings.map(([offeringId, offerings]) =>
            `${offeringId} for ${
              offerings.map(formatMedicationOfferingLabel).join(", ")
            }`
          ).join("; ")
        }`,
    };
  }

  return { offerings: medicationOfferings, errorMessage: null };
}

// ---------------------------------------------------------------------------
// Main entry point – request MDI case processing after payment collection
// ---------------------------------------------------------------------------

export async function requestMdiCaseProcessingForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  requestId: string;
}): Promise<MdiCaseProcessingResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForMdi(order)) {
    return {
      applicable: false,
      processingRequested: false,
      providerName: "MDI",
      message: "Order provider platform integration is not MDI",
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
      processingRequested: false,
      providerName: "MDI",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const providerLink = providerLinkLookup.links[0] || null;
  if (!providerLink) {
    return {
      applicable: true,
      processingRequested: false,
      providerName: "MDI",
      message: "No MDI case provider link found for this order",
      externalOrderId: null,
    };
  }

  const providerName = providerLink.tenant_integrations?.integration_key ||
    "MDI";
  if (
    normalizeIntegrationKey(
      providerLink.tenant_integrations?.integration_key,
    ) !==
      "md_integrations"
  ) {
    return {
      applicable: false,
      processingRequested: false,
      providerName,
      message: `Selected provider platform ${providerName} is not MDI`,
      externalOrderId: null,
    };
  }

  const providerOrderId = providerLink.provider_order_id?.trim() || null;
  if (!providerOrderId) {
    return {
      applicable: true,
      processingRequested: false,
      providerName: "MDI",
      message: "MDI case id is missing for this order",
      externalOrderId: null,
    };
  }

  const existingProcessingRequestedAt = getMdiProcessingRequestedAt(
    providerLink.metadata,
  );
  if (existingProcessingRequestedAt) {
    console.info("MDI case processing already requested for order", {
      requestId,
      orderId: order.id,
      tenantId: order.tenant_id,
      providerOrderId,
      existingProcessingRequestedAt,
    });

    return {
      applicable: true,
      processingRequested: true,
      alreadyRequested: true,
      providerName: "MDI",
      message: "MDI case processing already requested for this order",
      externalOrderId: providerOrderId,
    };
  }

  const backendUrl = getStringSetting(
    providerLink.tenant_integrations.settings,
    "backend_url",
  );
  if (!backendUrl) {
    return {
      applicable: true,
      processingRequested: false,
      providerName: "MDI",
      message: "MDI integration is missing backend_url configuration",
      externalOrderId: providerOrderId,
    };
  }

  const authResult = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: providerLink.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: providerLink.tenant_integrations.settings,
    baseUrl: backendUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      applicable: true,
      processingRequested: false,
      providerName: "MDI",
      message: authResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  const processingResult = await requestMdiCaseProcessing({
    backendUrl,
    accessToken: authResult.accessToken,
    providerOrderId,
    orderId: order.id,
    requestId,
  });

  if (!processingResult.ok) {
    return {
      applicable: true,
      processingRequested: false,
      providerName: "MDI",
      message: processingResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  await markMdiCaseProcessingRequested({
    supabase,
    order,
    providerLink,
    requestId,
  });

  return {
    applicable: true,
    processingRequested: true,
    alreadyRequested: false,
    providerName: "MDI",
    message: "MDI case processing requested successfully",
    externalOrderId: providerOrderId,
  };
}

// ---------------------------------------------------------------------------
// Main entry point – cancel MDI case during deferred order cancellation
// ---------------------------------------------------------------------------

export async function cancelMdiCaseForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  requestId: string;
}): Promise<MdiCaseCancelResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForMdi(order)) {
    return {
      applicable: false,
      cancelled: false,
      providerName: "MDI",
      message: "Order provider platform integration is not MDI",
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
      providerName: "MDI",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const providerLink = providerLinkLookup.links[0] || null;
  if (!providerLink) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "MDI",
      message: "No MDI case provider link found for this order",
      externalOrderId: null,
    };
  }

  const providerName = providerLink.tenant_integrations?.integration_key ||
    "MDI";
  if (
    normalizeIntegrationKey(
      providerLink.tenant_integrations?.integration_key,
    ) !==
      "md_integrations"
  ) {
    return {
      applicable: false,
      cancelled: false,
      providerName,
      message: `Selected provider platform ${providerName} is not MDI`,
      externalOrderId: null,
    };
  }

  const providerOrderId = providerLink.provider_order_id?.trim() || null;
  if (!providerOrderId) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "MDI",
      message: "MDI case id is missing for this order",
      externalOrderId: null,
    };
  }

  const existingCancelledAt = getMdiCancelledAt(providerLink.metadata);
  if (existingCancelledAt) {
    console.info("MDI case already cancelled for order", {
      requestId,
      orderId: order.id,
      tenantId: order.tenant_id,
      providerOrderId,
      existingCancelledAt,
    });

    return {
      applicable: true,
      cancelled: true,
      providerName: "MDI",
      message: "MDI case cancellation already recorded for this order",
      externalOrderId: providerOrderId,
    };
  }

  const backendUrl = getStringSetting(
    providerLink.tenant_integrations.settings,
    "backend_url",
  );
  if (!backendUrl) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "MDI",
      message: "MDI integration is missing backend_url configuration",
      externalOrderId: providerOrderId,
    };
  }

  const authResult = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: providerLink.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: providerLink.tenant_integrations.settings,
    baseUrl: backendUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "MDI",
      message: authResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  const cancelResult = await cancelMdiCase({
    backendUrl,
    accessToken: authResult.accessToken,
    providerOrderId,
    orderId: order.id,
    requestId,
  });

  if (!cancelResult.ok) {
    return {
      applicable: true,
      cancelled: false,
      providerName: "MDI",
      message: cancelResult.errorMessage,
      externalOrderId: providerOrderId,
    };
  }

  await markMdiCaseCancelled({
    supabase,
    order,
    providerLink,
    requestId,
  });

  return {
    applicable: true,
    cancelled: true,
    providerName: "MDI",
    message: "MDI case cancelled successfully",
    externalOrderId: providerOrderId,
  };
}

// ---------------------------------------------------------------------------
// Main entry point – create MDI order for lifecycle
// ---------------------------------------------------------------------------

export async function createMdiOrderForLifecycle(params: {
  supabase: SupabaseClient;
  order: OrderForMdi;
  requestId: string;
}): Promise<MdiOrderCreationResult> {
  const { supabase, order, requestId } = params;

  if (!isOrderForMdi(order)) {
    return {
      created: false,
      providerName: "MDI",
      message: "Order provider platform integration is not MDI",
      externalOrderId: null,
    };
  }

  if (!order.product_id) {
    return {
      created: false,
      providerName: "MDI",
      message: "Order is missing a product_id",
      externalOrderId: null,
    };
  }

  // Resolve existing provider link (deterministic, strict)
  const providerLinkLookup = await fetchOrderedProviderLinksForOrder({
    supabase,
    order,
    requestId,
  });

  if (providerLinkLookup.errorMessage) {
    return {
      created: false,
      providerName: "MDI",
      message: providerLinkLookup.errorMessage,
      externalOrderId: null,
    };
  }

  const selectedProviderLink = providerLinkLookup.links[0] || null;

  if (
    selectedProviderLink?.tenant_integrations?.integration_key &&
    selectedProviderLink.tenant_integrations.integration_key !==
      "md_integrations"
  ) {
    return {
      created: false,
      providerName: selectedProviderLink.tenant_integrations.integration_key,
      message:
        `Selected provider platform ${selectedProviderLink.tenant_integrations.integration_key} is not MDI`,
      externalOrderId: null,
    };
  }

  const existingMdiCaseId = selectedProviderLink?.provider_order_id?.trim();
  if (existingMdiCaseId) {
    console.info("Reusing existing MDI case for order", {
      requestId,
      orderId: order.id,
      tenantId: order.tenant_id,
      tenantIntegrationId: selectedProviderLink.tenant_integration_id,
      mdiCaseId: existingMdiCaseId,
    });

    return {
      created: true,
      providerName: "MDI",
      message: "MDI case already exists for this order",
      externalOrderId: existingMdiCaseId,
    };
  }

  // Fetch product → MDI provider assignment
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
      providerName: "MDI",
      message:
        `Failed to fetch provider platform assignment: ${assignmentsError.message}`,
      externalOrderId: null,
    };
  }

  const mdiAssignment = (
    (productProviderAssignments || []) as ProductProviderPlatformAssignment[]
  ).find(
    (a) =>
      a.tenant_integrations?.tenant_id === order.tenant_id &&
      a.tenant_integrations?.is_enabled &&
      a.tenant_integrations?.integration_key === "md_integrations" &&
      (!selectedProviderLink ||
        a.tenant_integration_id ===
          selectedProviderLink.tenant_integration_id),
  );

  if (!mdiAssignment) {
    return {
      created: false,
      providerName: "MDI",
      message:
        "No enabled MDI provider integration is configured for this product",
      externalOrderId: null,
    };
  }

  const backendUrl = getStringSetting(
    mdiAssignment.tenant_integrations.settings,
    "backend_url",
  );
  if (!backendUrl) {
    return {
      created: false,
      providerName: "MDI",
      message: "MDI integration is missing backend_url configuration",
      externalOrderId: null,
    };
  }

  // Fetch patient
  const { data: patient, error: patientError } = await supabase
    .from("patients")
    .select("id, first_name, last_name, email, phone, date_of_birth")
    .eq("id", order.patient_id)
    .eq("tenant_id", order.tenant_id)
    .maybeSingle();

  if (patientError) {
    return {
      created: false,
      providerName: "MDI",
      message: `Failed to fetch patient details: ${patientError.message}`,
      externalOrderId: null,
    };
  }

  if (!patient) {
    return {
      created: false,
      providerName: "MDI",
      message: "Patient not found for order",
      externalOrderId: null,
    };
  }

  // Step 1: Authenticate with MDI
  const authResult = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: mdiAssignment.tenant_integration_id,
    tenantId: order.tenant_id,
    settings: mdiAssignment.tenant_integrations.settings,
    baseUrl: backendUrl,
    requestId,
    source: "order-lifecycle",
  });

  if ("errorMessage" in authResult) {
    return {
      created: false,
      providerName: "MDI",
      message: authResult.errorMessage,
      externalOrderId: null,
    };
  }

  // Step 2: Resolve or create patient in MDI
  const { data: existingPatientLink } = await supabase
    .from("patient_provider_platform_links")
    .select("provider_patient_id")
    .eq("patient_id", order.patient_id)
    .eq("tenant_integration_id", mdiAssignment.tenant_integration_id)
    .maybeSingle();

  let mdiPatientId: string;

  if (
    existingPatientLink &&
    typeof existingPatientLink.provider_patient_id === "string" &&
    existingPatientLink.provider_patient_id.trim().length > 0
  ) {
    mdiPatientId = existingPatientLink.provider_patient_id;
    console.info("Reusing existing MDI patient", {
      requestId,
      orderId: order.id,
      patientId: order.patient_id,
      mdiPatientId,
    });
  } else {
    const patientResult = await createMdiPatient({
      backendUrl,
      accessToken: authResult.accessToken,
      order,
      patient: patient as PatientForMdi,
      requestId,
    });

    if ("errorMessage" in patientResult) {
      return {
        created: false,
        providerName: "MDI",
        message: patientResult.errorMessage,
        externalOrderId: null,
      };
    }

    mdiPatientId = patientResult.patientId;

    // Persist patient provider platform link
    await upsertPatientProviderPlatformLink({
      supabase,
      order,
      tenantIntegrationId: mdiAssignment.tenant_integration_id,
      mdiPatientId,
      requestId,
    });
  }

  const medicationOfferingsResult = await fetchMdiMedicationOfferingsForProduct(
    {
      supabase,
      productId: order.product_id,
    },
  );

  if (medicationOfferingsResult.errorMessage) {
    return {
      created: false,
      providerName: "MDI",
      message: medicationOfferingsResult.errorMessage,
      externalOrderId: null,
    };
  }

  // Step 3: Create case in MDI
  const caseResult = await createMdiCase({
    backendUrl,
    accessToken: authResult.accessToken,
    mdiPatientId,
    orderNumber: order.order_number,
    offeringIds: medicationOfferingsResult.offerings.map((offering) =>
      offering.offering_id
    ),
    orderId: order.id,
    requestId,
  });

  if ("errorMessage" in caseResult) {
    return {
      created: false,
      providerName: "MDI",
      message: caseResult.errorMessage,
      externalOrderId: null,
    };
  }

  // Persist order provider platform link with case_id
  await upsertOrderProviderPlatformLink({
    supabase,
    order,
    tenantIntegrationId: mdiAssignment.tenant_integration_id,
    providerOrderId: caseResult.caseId,
    mdiPatientId,
    medicationOfferings: medicationOfferingsResult.offerings,
    requestId,
  });

  // Append timeline note
  let mdiHistoryId: string | null = null;
  if (order.status_id) {
    const { data: historyRow, error: historyError } = await supabase
      .from("order_status_history")
      .insert({
        order_id: order.id,
        status_id: order.status_id,
        notes:
          `MDI case created (case ${caseResult.caseId}, patient ${mdiPatientId})`,
      })
      .select("id")
      .single();

    if (historyError) {
      console.warn("Failed to insert MDI case-created timeline note", {
        requestId,
        orderId: order.id,
        mdiCaseId: caseResult.caseId,
        mdiPatientId,
        error: historyError.message,
      });
    } else {
      mdiHistoryId = historyRow?.id ?? null;
    }
  }

  await triggerRtdhProviderPlatformNewOrder({
    supabase,
    requestId,
    tenantId: order.tenant_id,
    orderId: order.id,
    orderStatusHistoryId: mdiHistoryId,
    patientId: order.patient_id,
    providerPatientId: mdiPatientId,
    providerPlatformKey: "md_integrations",
    providerPlatformOrderId: caseResult.caseId,
  });

  return {
    created: true,
    providerName: "MDI",
    message: "MDI case created successfully",
    externalOrderId: caseResult.caseId,
  };
}
