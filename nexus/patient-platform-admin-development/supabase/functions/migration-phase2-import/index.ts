/**
 * Migration Phase 2 Import — Supabase Edge Function
 *
 * Receives Phase 2 migration batches from the brelloMigrationPhase2 Cloud
 * Function and writes historical order/subscription and approved health data
 * to Supabase.
 *
 * Security: Requires `X-Migration-API-Key` header matching MIGRATION_API_KEY env var.
 * Not intended to be called from the browser.
 *
 * Idempotent migration writes resolve existing Phase 1 stubs/patients and
 * enrich approved Phase 2 targets without lifecycle side effects.
 */

import { createClient, type SupabaseClient } from "jsr:@supabase/supabase-js@2";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
type JsonRecord = Record<string, unknown>;

interface AddressPayload {
  first_name: string | null;
  last_name: string | null;
  company: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  email: string | null;
  phone: string | null;
}

interface ProviderCustomer {
  provider_name: "woocommerce";
  provider_patient_id: string;
}

interface TimelineEntry {
  source: string;
  event_id: string;
  event_type: string;
  status: string | null;
  raw_status?: string | null;
  occurred_at: string | null;
  author?: string | null;
  note?: string | null;
  ids?: JsonRecord;
}

type ProductMappingStatus =
  | "product"
  | "product_unmapped"
  | "product_medication"
  | "missing";

interface ProductMappingPayload {
  key: string | null;
  status: ProductMappingStatus;
  target_table: string | null;
  target_id: string | null;
  target_key: string | null;
  target_label: string | null;
  action: string | null;
  notes: string | null;
}

interface MigratedProduct {
  woo_order_item_id: string;
  name: string | null;
  woo_product_id: string | null;
  woo_variation_id: string | null;
  product_id: string | null;
  quantity: number | null;
  line_subtotal_cents: number | null;
  line_total_cents: number | null;
  subscription_interval: string | null;
  metadata: JsonRecord & {
    product_mapping: ProductMappingPayload;
  };
  source_payload: JsonRecord;
}

interface MigratedOrder {
  source: "woocommerce";
  event_id: string;
  event_type: string;
  status: string;
  is_migrated: true;
  migration_phase: 2;
  occurred_at: string | null;
  updated_at: string | null;
  ids: {
    woo_order_id: string;
    woo_customer_id: string;
    woo_parent_order_id: string | null;
    woo_subscription_id: string | null;
  };
  customer: ProviderCustomer;
  order: {
    product_id: string;
    order_number: string;
    currency: string | null;
    total_cents: number;
    discount_cents: number;
    payment_method: string | null;
    payment_method_title: string | null;
    billing: AddressPayload | null;
    shipping: AddressPayload | null;
    metadata: JsonRecord;
  };
  products: MigratedProduct[];
  timeline: TimelineEntry[];
  source_payload: JsonRecord;
}

interface MigratedSubscription {
  source: "woocommerce";
  event_id: string;
  event_type: "subscription.status_changed";
  status: string;
  is_migrated: true;
  migration_phase: 2;
  occurred_at: string | null;
  updated_at: string | null;
  ids: {
    woo_subscription_id: string;
    woo_customer_id: string;
    woo_parent_order_id: string | null;
  };
  customer: ProviderCustomer;
  subscription: {
    product_id: string;
    status: string;
    started_at: string | null;
    current_period_end_at: string | null;
    expires_at: string | null;
    cancelled_at: string | null;
    billing_period: string | null;
    billing_interval: string | null;
    metadata: JsonRecord;
  };
  order_context: {
    currency: string | null;
    total_cents: number;
    discount_cents: number;
    payment_method: string | null;
    payment_method_title: string | null;
    billing: AddressPayload | null;
    shipping: AddressPayload | null;
  };
  products: MigratedProduct[];
  timeline: TimelineEntry[];
  source_payload: JsonRecord;
}

type HealthTargetTable =
  | "patient_weight_entries"
  | "medication_shot_intakes"
  | "patient_body_measurement_entries"
  | "patient_symptom_entries"
  | "patient_mood_change_entries"
  | "patient_activity_entries";

interface HealthImportItem {
  target_table: HealthTargetTable;
  tenant_id: "brello";
  patient_lookup: {
    legacy_brello_uid: string | null;
  };
  migration_source: "brello";
  migration_source_id: string;
  migration_source_item_key: string;
  metadata: JsonRecord;
  payload: JsonRecord;
}

interface MigrationBatch {
  migration_phase: 2;
  is_migrated: true;
  orders: MigratedOrder[];
  subscriptions: MigratedSubscription[];
  health: HealthImportItem[];
  tenant_slug?: string;
}

interface BatchResult {
  orders_received: number;
  orders_processed: number;
  orders_skipped: number;
  subscriptions_received: number;
  subscriptions_processed: number;
  subscriptions_skipped: number;
  health_received: number;
  health_skipped: number;
  orders_upserted: number;
  subscriptions_upserted: number;
  health_upserted: number;
  product_ids_unresolved: number;
  failed: Array<{ section: string; error: string }>;
}

interface ExistingOrderStub {
  id: string;
  patient_id: string;
  status_id: string | null;
  metadata: JsonRecord | null;
}

interface ExistingSubscriptionStub {
  id: string;
  patient_id: string;
  status: string;
  metadata: JsonRecord | null;
}

interface ExistingPatient {
  id: string;
  metadata: JsonRecord | null;
}

interface ExistingInjectionSite {
  id: string;
  label: string;
}

// ---------------------------------------------------------------------------
// Tenant ID cache (keyed by slug to support multi-tenant runs)
// ---------------------------------------------------------------------------
const tenantIdCache = new Map<string, string>();

async function getTenantIdBySlug(
  supabase: SupabaseClient,
  slug: string,
): Promise<string> {
  const cached = tenantIdCache.get(slug);
  if (cached) return cached;
  const { data, error } = await supabase
    .from("tenants")
    .select("id")
    .eq("slug", slug)
    .maybeSingle();

  if (error) {
    throw new Error(`Could not resolve tenant "${slug}": ${error.message}`);
  }
  if (!data) {
    throw new Error(
      `Tenant "${slug}" not found — ensure it exists in the tenants table before running the migration`,
    );
  }
  tenantIdCache.set(slug, data.id as string);
  return data.id as string;
}

// ---------------------------------------------------------------------------
// Status ID resolution (for timeline history insertion)
// ---------------------------------------------------------------------------
async function loadStatusIdsByKeys(
  supabase: SupabaseClient,
  keys: string[],
): Promise<Map<string, string>> {
  const unique = [...new Set(keys.filter(Boolean))];
  if (unique.length === 0) return new Map();
  const { data, error } = await supabase
    .from("order_statuses")
    .select("id, status_key")
    .in("status_key", unique);
  if (error) throw new Error(`Status ID lookup failed: ${error.message}`);
  return new Map((data ?? []).map((row: { status_key: string; id: string }) => [row.status_key, row.id]));
}

// ---------------------------------------------------------------------------
// Existing Phase 1 stub lookups
// ---------------------------------------------------------------------------
// Bulk variants — one query for the whole batch instead of one query per
// order/subscription. The per-item finders below are now thin wrappers
// around an in-memory map built from these.
async function loadExistingOrdersByWooOrderIds(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  wooOrderIds: string[],
): Promise<Map<string, ExistingOrderStub>> {
  const uniqueIds = Array.from(new Set(wooOrderIds.filter(Boolean)));
  if (uniqueIds.length === 0) return new Map();

  const { data, error } = await supabaseAdmin
    .from("orders")
    .select("id, patient_id, status_id, metadata")
    .eq("tenant_id", tenantId)
    .filter("metadata->>woo_order_id", "in", `(${uniqueIds.join(",")})`);

  if (error) {
    throw new Error(`Bulk order lookup failed: ${error.message}`);
  }

  const ordersByWooOrderId = new Map<string, ExistingOrderStub>();
  for (const row of (data ?? []) as ExistingOrderStub[]) {
    const wooOrderId = (row.metadata as JsonRecord | null)?.woo_order_id;
    if (typeof wooOrderId === "string") {
      ordersByWooOrderId.set(wooOrderId, row);
    }
  }
  return ordersByWooOrderId;
}

async function loadExistingSubscriptionsByWooSubscriptionIds(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  wooSubscriptionIds: string[],
): Promise<Map<string, ExistingSubscriptionStub>> {
  const uniqueIds = Array.from(new Set(wooSubscriptionIds.filter(Boolean)));
  if (uniqueIds.length === 0) return new Map();

  const { data, error } = await supabaseAdmin
    .from("subscriptions")
    .select("id, patient_id, status, metadata")
    .eq("tenant_id", tenantId)
    .filter(
      "metadata->>woo_subscription_id",
      "in",
      `(${uniqueIds.join(",")})`,
    );

  if (error) {
    throw new Error(`Bulk subscription lookup failed: ${error.message}`);
  }

  const subscriptionsByWooSubscriptionId = new Map<
    string,
    ExistingSubscriptionStub
  >();
  for (const row of (data ?? []) as ExistingSubscriptionStub[]) {
    const wooSubscriptionId = (row.metadata as JsonRecord | null)
      ?.woo_subscription_id;
    if (typeof wooSubscriptionId === "string") {
      subscriptionsByWooSubscriptionId.set(wooSubscriptionId, row);
    }
  }
  return subscriptionsByWooSubscriptionId;
}

// A subscription's own `source_billing.woo_parent_order_id` identifies the
// order that started it (set during Phase 1/2 from the shop_subscription
// row's parent_order_id). This is the reverse direction of the lookup above:
// given a set of order ids, find which ones are some subscription's
// initiating order, so that order's row can carry the subscription_id too.
async function loadSubscriptionIdsByParentOrderIds(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  wooOrderIds: string[],
): Promise<Map<string, string>> {
  const uniqueIds = Array.from(new Set(wooOrderIds.filter(Boolean)));
  if (uniqueIds.length === 0) return new Map();

  const { data, error } = await supabaseAdmin
    .from("subscriptions")
    .select("id, metadata")
    .eq("tenant_id", tenantId)
    .filter(
      "metadata->source_billing->>woo_parent_order_id",
      "in",
      `(${uniqueIds.join(",")})`,
    );

  if (error) {
    throw new Error(`Bulk parent-order subscription lookup failed: ${error.message}`);
  }

  const subscriptionIdByParentOrderId = new Map<string, string>();
  for (const row of (data ?? []) as { id: string; metadata: JsonRecord | null }[]) {
    const sourceBilling = row.metadata?.source_billing as JsonRecord | undefined;
    const parentOrderId = sourceBilling?.woo_parent_order_id;
    if (typeof parentOrderId === "string") {
      subscriptionIdByParentOrderId.set(parentOrderId, row.id);
    }
  }
  return subscriptionIdByParentOrderId;
}

async function loadProductsBySku(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
): Promise<Map<string, string>> {
  const { data, error } = await supabaseAdmin
    .from("products")
    .select("id, sku")
    .eq("tenant_id", tenantId)
    .not("sku", "is", null);

  if (error) {
    throw new Error(`Product SKU lookup failed: ${error.message}`);
  }

  return new Map(
    (data ?? []).map((product) => [String(product.sku), String(product.id)]),
  );
}

function getLegacyBrelloUid(item: HealthImportItem): string | null {
  return item.patient_lookup.legacy_brello_uid?.trim() || null;
}

async function loadPatientsByLegacyBrelloUid(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  legacyBrelloUids: string[],
): Promise<Map<string, ExistingPatient>> {
  if (legacyBrelloUids.length === 0) return new Map();

  const { data, error } = await supabaseAdmin
    .from("patients")
    .select("id, metadata")
    .eq("tenant_id", tenantId)
    .in("metadata->>legacy_brello_uid", legacyBrelloUids);

  if (error) {
    throw new Error(
      `Patient lookup failed for health imports: ${error.message}`,
    );
  }

  const patientsByLegacyBrelloUid = new Map<string, ExistingPatient>();
  for (const patient of (data ?? []) as ExistingPatient[]) {
    const legacyBrelloUid = patient.metadata?.legacy_brello_uid;
    if (legacyBrelloUid) {
      patientsByLegacyBrelloUid.set(String(legacyBrelloUid), patient);
    }
  }

  return patientsByLegacyBrelloUid;
}

function getInjectionSiteTargetLabel(item: HealthImportItem): string | null {
  return String(item.payload.injection_site_target_label || "").trim() || null;
}

async function loadInjectionSiteIdsByLabel(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  labels: string[],
): Promise<Map<string, string>> {
  if (labels.length === 0) return new Map();
  const requestedLabels = new Set(
    labels.map((label) => label.trim().toLowerCase()),
  );

  const { data, error } = await supabaseAdmin
    .from("tenant_injection_site_definitions")
    .select("id, label")
    .eq("tenant_id", tenantId);

  if (error) {
    throw new Error(
      `Injection site lookup failed for health imports: ${error.message}`,
    );
  }

  const injectionSiteIdsByLabel = new Map<string, string>();
  for (const site of (data ?? []) as ExistingInjectionSite[]) {
    const normalizedLabel = site.label.trim().toLowerCase();
    if (requestedLabels.has(normalizedLabel)) {
      injectionSiteIdsByLabel.set(normalizedLabel, site.id);
    }
  }

  return injectionSiteIdsByLabel;
}

// ---------------------------------------------------------------------------
// Health imports
// ---------------------------------------------------------------------------
async function processWeightEntry(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  item: HealthImportItem,
): Promise<boolean> {
  const legacyBrelloUid = getLegacyBrelloUid(item);
  if (!legacyBrelloUid) {
    throw new Error("Missing legacy_brello_uid");
  }

  const patient = patientsByLegacyBrelloUid.get(legacyBrelloUid);
  if (!patient) {
    return false;
  }

  const weightValue = Number(item.payload.weight_lbs);
  if (!Number.isFinite(weightValue) || weightValue <= 0) {
    throw new Error("Invalid weight_lbs");
  }

  const weighedAt = String(item.payload.logged_at || "").trim();
  if (!weighedAt) {
    throw new Error("Missing logged_at");
  }

  const { error } = await supabaseAdmin
    .from("patient_weight_entries")
    .upsert(
      {
        tenant_id: tenantId,
        patient_id: patient.id,
        weight_value: weightValue,
        weight_unit: "lb",
        weighed_at: weighedAt,
        metadata: item.metadata,
        migration_source: item.migration_source,
        migration_source_id: item.migration_source_id,
        migration_source_item_key: item.migration_source_item_key,
      },
      {
        onConflict:
          "tenant_id,patient_id,migration_source,migration_source_id,migration_source_item_key",
      },
    );

  if (error) {
    throw new Error(
      `Weight entry upsert failed for source ${item.migration_source_id}: ${error.message}`,
    );
  }

  return true;
}

async function processBodyMeasurementEntry(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  item: HealthImportItem,
): Promise<boolean> {
  const legacyBrelloUid = getLegacyBrelloUid(item);
  if (!legacyBrelloUid) {
    throw new Error("Missing legacy_brello_uid");
  }

  const patient = patientsByLegacyBrelloUid.get(legacyBrelloUid);
  if (!patient) {
    return false;
  }

  const chestInches = Number(item.payload.chest_inches);
  const waistInches = Number(item.payload.waist_inches);
  const hipsInches = Number(item.payload.hips_inches);
  const armsInches = Number(item.payload.arms_inches);

  if (
    [chestInches, waistInches, hipsInches, armsInches].some((value) =>
      !Number.isFinite(value) || value <= 0
    )
  ) {
    throw new Error("Invalid body measurements");
  }

  const measuredAt = String(item.payload.measured_at || "").trim();
  if (!measuredAt) {
    throw new Error("Missing measured_at");
  }

  const { error } = await supabaseAdmin
    .from("patient_body_measurement_entries")
    .upsert(
      {
        tenant_id: tenantId,
        patient_id: patient.id,
        chest_inches: chestInches,
        waist_inches: waistInches,
        hips_inches: hipsInches,
        arms_inches: armsInches,
        measured_at: measuredAt,
        metadata: item.metadata,
        migration_source: item.migration_source,
        migration_source_id: item.migration_source_id,
        migration_source_item_key: item.migration_source_item_key,
      },
      {
        onConflict:
          "tenant_id,patient_id,migration_source,migration_source_id,migration_source_item_key",
      },
    );

  if (error) {
    throw new Error(
      `Body measurement upsert failed for source ${item.migration_source_id}: ${error.message}`,
    );
  }

  return true;
}

async function processMedicationShotIntake(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  injectionSiteIdsByLabel: Map<string, string>,
  item: HealthImportItem,
): Promise<boolean> {
  const legacyBrelloUid = getLegacyBrelloUid(item);
  if (!legacyBrelloUid) {
    throw new Error("Missing legacy_brello_uid");
  }

  const patient = patientsByLegacyBrelloUid.get(legacyBrelloUid);
  if (!patient) {
    return false;
  }

  const medicationId = String(item.payload.medication_id || "").trim();
  if (!medicationId) {
    throw new Error("Missing medication_id");
  }

  const dosageStrength = Number(item.payload.dosage_strength);
  if (!Number.isFinite(dosageStrength) || dosageStrength <= 0) {
    throw new Error("Invalid dosage_strength");
  }

  const painLevel = Number(item.payload.pain_level);
  if (!Number.isInteger(painLevel) || painLevel < 0 || painLevel > 5) {
    throw new Error("Invalid pain_level");
  }

  const intakeDate = String(item.payload.intake_date || "").trim();
  if (!intakeDate) {
    throw new Error("Missing intake_date");
  }

  const injectionSiteLabel = getInjectionSiteTargetLabel(item);
  const injectionSiteId = injectionSiteLabel
    ? injectionSiteIdsByLabel.get(injectionSiteLabel.toLowerCase()) ?? null
    : null;
  // Working notes: unmapped sites (Other, Right Buttock, Left Buttock) use null injection_site_id.
  // Source label is preserved in metadata for audit. Do not throw — ingest the row with null site.
  if (injectionSiteLabel && !injectionSiteId) {
    console.warn(
      `Injection site label not found in tenant definitions: "${injectionSiteLabel}" for source ${item.migration_source_id}. Using null injection_site_id.`,
    );
  }

  const { error } = await supabaseAdmin
    .from("medication_shot_intakes")
    .upsert(
      {
        tenant_id: tenantId,
        patient_id: patient.id,
        medication_id: medicationId,
        dosage_strength: dosageStrength,
        pain_level: painLevel,
        intake_date: intakeDate,
        injection_site_id: injectionSiteId,
        metadata: item.metadata,
        migration_source: item.migration_source,
        migration_source_id: item.migration_source_id,
        migration_source_item_key: item.migration_source_item_key,
      },
      {
        onConflict:
          "tenant_id,patient_id,migration_source,migration_source_id,migration_source_item_key",
      },
    );

  if (error) {
    throw new Error(
      `Medication shot intake upsert failed for source ${item.migration_source_id}: ${error.message}`,
    );
  }

  return true;
}

async function processSymptomEntry(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  item: HealthImportItem,
): Promise<boolean> {
  const legacyBrelloUid = getLegacyBrelloUid(item);
  if (!legacyBrelloUid) {
    throw new Error("Missing legacy_brello_uid");
  }

  const patient = patientsByLegacyBrelloUid.get(legacyBrelloUid);
  if (!patient) {
    return false;
  }

  const symptomLabel = String(item.payload.symptom || "").trim();
  if (!symptomLabel) {
    throw new Error("Missing symptom");
  }

  const recordedAt = String(item.payload.logged_at || "").trim();
  if (!recordedAt) {
    throw new Error("Missing logged_at");
  }

  const { error } = await supabaseAdmin
    .from("patient_symptom_entries")
    .upsert(
      {
        tenant_id: tenantId,
        patient_id: patient.id,
        symptom_label: symptomLabel,
        recorded_at: recordedAt,
        metadata: item.metadata,
        migration_source: item.migration_source,
        migration_source_id: item.migration_source_id,
        migration_source_item_key: item.migration_source_item_key,
      },
      {
        onConflict:
          "tenant_id,patient_id,migration_source,migration_source_id,migration_source_item_key",
      },
    );

  if (error) {
    throw new Error(
      `Symptom entry upsert failed for source ${item.migration_source_id}: ${error.message}`,
    );
  }

  return true;
}

async function processMoodChangeEntry(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  item: HealthImportItem,
): Promise<boolean> {
  const legacyBrelloUid = getLegacyBrelloUid(item);
  if (!legacyBrelloUid) {
    throw new Error("Missing legacy_brello_uid");
  }

  const patient = patientsByLegacyBrelloUid.get(legacyBrelloUid);
  if (!patient) {
    return false;
  }

  const moodChangeLabel = String(item.payload.mood || "").trim();
  if (!moodChangeLabel) {
    throw new Error("Missing mood");
  }

  const recordedAt = String(item.payload.logged_at || "").trim();
  if (!recordedAt) {
    throw new Error("Missing logged_at");
  }

  const { error } = await supabaseAdmin
    .from("patient_mood_change_entries")
    .upsert(
      {
        tenant_id: tenantId,
        patient_id: patient.id,
        mood_change_label: moodChangeLabel,
        recorded_at: recordedAt,
        metadata: item.metadata,
        migration_source: item.migration_source,
        migration_source_id: item.migration_source_id,
        migration_source_item_key: item.migration_source_item_key,
      },
      {
        onConflict:
          "tenant_id,patient_id,migration_source,migration_source_id,migration_source_item_key",
      },
    );

  if (error) {
    throw new Error(
      `Mood change entry upsert failed for source ${item.migration_source_id}: ${error.message}`,
    );
  }

  return true;
}

async function processActivityEntry(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  item: HealthImportItem,
): Promise<boolean> {
  const legacyBrelloUid = getLegacyBrelloUid(item);
  if (!legacyBrelloUid) {
    throw new Error("Missing legacy_brello_uid");
  }

  const patient = patientsByLegacyBrelloUid.get(legacyBrelloUid);
  if (!patient) {
    return false;
  }

  const activityLabel = String(item.payload.activity || "").trim();
  if (!activityLabel) {
    throw new Error("Missing activity");
  }

  const recordedAt = String(item.payload.logged_at || "").trim();
  if (!recordedAt) {
    throw new Error("Missing logged_at");
  }

  const { error } = await supabaseAdmin
    .from("patient_activity_entries")
    .upsert(
      {
        tenant_id: tenantId,
        patient_id: patient.id,
        activity_label: activityLabel,
        recorded_at: recordedAt,
        metadata: item.metadata,
        migration_source: item.migration_source,
        migration_source_id: item.migration_source_id,
        migration_source_item_key: item.migration_source_item_key,
      },
      {
        onConflict:
          "tenant_id,patient_id,migration_source,migration_source_id,migration_source_item_key",
      },
    );

  if (error) {
    throw new Error(
      `Activity entry upsert failed for source ${item.migration_source_id}: ${error.message}`,
    );
  }

  return true;
}

async function processHealthEntry(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patientsByLegacyBrelloUid: Map<string, ExistingPatient>,
  injectionSiteIdsByLabel: Map<string, string>,
  item: HealthImportItem,
): Promise<boolean> {
  switch (item.target_table) {
    case "patient_weight_entries":
      return processWeightEntry(
        supabaseAdmin,
        tenantId,
        patientsByLegacyBrelloUid,
        item,
      );
    case "patient_body_measurement_entries":
      return processBodyMeasurementEntry(
        supabaseAdmin,
        tenantId,
        patientsByLegacyBrelloUid,
        item,
      );
    case "medication_shot_intakes":
      return processMedicationShotIntake(
        supabaseAdmin,
        tenantId,
        patientsByLegacyBrelloUid,
        injectionSiteIdsByLabel,
        item,
      );
    case "patient_symptom_entries":
      return processSymptomEntry(
        supabaseAdmin,
        tenantId,
        patientsByLegacyBrelloUid,
        item,
      );
    case "patient_mood_change_entries":
      return processMoodChangeEntry(
        supabaseAdmin,
        tenantId,
        patientsByLegacyBrelloUid,
        item,
      );
    case "patient_activity_entries":
      return processActivityEntry(
        supabaseAdmin,
        tenantId,
        patientsByLegacyBrelloUid,
        item,
      );
    default:
      return false;
  }
}

// ---------------------------------------------------------------------------
// Bounded-concurrency map — processes batch items in parallel instead of
// one at a time, same pattern and constant used in migration-phase1-import.
// Kept conservative (not higher) because sustained sequential runs degraded
// Supabase Edge Function calls even with no competing parallel streams.
// ---------------------------------------------------------------------------
const BATCH_CONCURRENCY = 5;

async function mapWithConcurrency<T, R>(
  items: T[],
  concurrency: number,
  fn: (item: T) => Promise<R>,
): Promise<R[]> {
  const results: R[] = new Array(items.length);
  let nextIndex = 0;

  async function worker() {
    while (true) {
      const i = nextIndex++;
      if (i >= items.length) return;
      results[i] = await fn(items[i]);
    }
  }

  const workerCount = Math.min(concurrency, items.length);
  await Promise.all(Array.from({ length: workerCount }, () => worker()));
  return results;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function errorResponse(message: string, status = 500): Response {
  return new Response(JSON.stringify({ success: false, error: message }), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

// ---------------------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------------------
Deno.serve(async (req: Request): Promise<Response> => {
  // Only allow POST
  if (req.method === "OPTIONS") {
    return new Response(null, { status: 204 });
  }
  if (req.method !== "POST") {
    return errorResponse("Method not allowed", 405);
  }

  // Validate API key
  const apiKey = req.headers.get("X-Migration-API-Key");
  const expectedApiKey = Deno.env.get("MIGRATION_API_KEY");
  if (!expectedApiKey || apiKey !== expectedApiKey) {
    return errorResponse("Unauthorized", 401);
  }

  // Parse request body
  let batch: MigrationBatch;
  try {
    batch = await req.json();
  } catch {
    return errorResponse("Invalid JSON body", 400);
  }

  if (batch.migration_phase !== 2) {
    return errorResponse("migration_phase must be 2", 400);
  }
  if (batch.is_migrated !== true) {
    return errorResponse("is_migrated must be true", 400);
  }
  if (!Array.isArray(batch.orders)) {
    return errorResponse("orders must be an array", 400);
  }
  if (!Array.isArray(batch.subscriptions)) {
    return errorResponse("subscriptions must be an array", 400);
  }
  if (!Array.isArray(batch.health)) {
    return errorResponse("health must be an array", 400);
  }

  // Build Supabase admin client
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!supabaseUrl || !serviceRoleKey) {
    return errorResponse(
      "SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY not configured",
      500,
    );
  }

  const supabaseAdmin = createClient(supabaseUrl, serviceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  // Resolve tenant id from batch payload (defaults to "brello" for backwards compat)
  const tenantSlug = batch.tenant_slug || "brello";
  let tenantId: string;
  try {
    tenantId = await getTenantIdBySlug(supabaseAdmin, tenantSlug);
  } catch (err) {
    return errorResponse((err as Error).message, 500);
  }

  const result: BatchResult = {
    orders_received: batch.orders.length,
    orders_processed: 0,
    orders_skipped: 0,
    subscriptions_received: batch.subscriptions.length,
    subscriptions_processed: 0,
    subscriptions_skipped: 0,
    health_received: batch.health.length,
    health_skipped: 0,
    orders_upserted: 0,
    subscriptions_upserted: 0,
    health_upserted: 0,
    product_ids_unresolved: 0,
    failed: [],
  };

  let productBySku: Map<string, string>;
  try {
    productBySku = await loadProductsBySku(supabaseAdmin, tenantId);
  } catch (err) {
    return errorResponse((err as Error).message, 500);
  }

  // 1. Enrich existing order stubs — one bulk lookup for the whole batch,
  // then bounded-concurrency updates instead of a per-order SELECT+UPDATE
  // round trip processed one at a time.
  let existingOrdersByWooOrderId: Map<string, ExistingOrderStub>;
  try {
    existingOrdersByWooOrderId = await loadExistingOrdersByWooOrderIds(
      supabaseAdmin,
      tenantId,
      batch.orders.map((order) => order.ids.woo_order_id),
    );
  } catch (err) {
    result.failed.push({ section: "orders", error: (err as Error).message });
    existingOrdersByWooOrderId = new Map();
  }

  // Resolve orders.subscription_id from either direction: a renewal order's
  // own `_subscription_renewal` meta (woo_subscription_id), or an order that
  // is itself some subscription's initiating order (parent_order_id).
  let subscriptionIdByWooSubscriptionId: Map<string, ExistingSubscriptionStub>;
  let subscriptionIdByParentOrderId: Map<string, string>;
  try {
    [subscriptionIdByWooSubscriptionId, subscriptionIdByParentOrderId] =
      await Promise.all([
        loadExistingSubscriptionsByWooSubscriptionIds(
          supabaseAdmin,
          tenantId,
          batch.orders.map((order) => order.ids.woo_subscription_id ?? ""),
        ),
        loadSubscriptionIdsByParentOrderIds(
          supabaseAdmin,
          tenantId,
          batch.orders.map((order) => order.ids.woo_order_id),
        ),
      ]);
  } catch (err) {
    result.failed.push({ section: "orders", error: (err as Error).message });
    subscriptionIdByWooSubscriptionId = new Map();
    subscriptionIdByParentOrderId = new Map();
  }

  // Bulk-load status IDs for all timeline status keys across all orders so the
  // per-order history insertion can resolve them without individual DB queries.
  let timelineStatusIdsByKey: Map<string, string> = new Map();
  try {
    const allTimelineKeys = batch.orders.flatMap((order) =>
      order.timeline.map((entry) => entry.status).filter(Boolean)
    ) as string[];
    timelineStatusIdsByKey = await loadStatusIdsByKeys(supabaseAdmin, allTimelineKeys);
  } catch (err) {
    result.failed.push({ section: "orders_timeline_statuses", error: (err as Error).message });
  }

  await mapWithConcurrency(batch.orders, BATCH_CONCURRENCY, async (order) => {
    const wooOrderId = order.ids.woo_order_id;

    try {
      const orderSku = String(
        order.products[0]?.metadata?.product_mapping?.target_key ?? "",
      );
      const resolvedProductId = orderSku
        ? (productBySku.get(orderSku) ?? null)
        : null;
      if (!resolvedProductId) {
        result.product_ids_unresolved++;
      }

      const existingOrder = existingOrdersByWooOrderId.get(wooOrderId) ??
        null;

      if (!existingOrder) {
        result.orders_skipped++;
        return;
      }

      const metadata = {
        ...(existingOrder.metadata ?? {}),
        migration_phase_2: {
          is_migrated: true,
          imported_at: new Date().toISOString(),
          event_id: order.event_id,
          event_type: order.event_type,
          source_status: order.status,
          occurred_at: order.occurred_at,
          updated_at: order.updated_at,
          mapped_product_sku: orderSku || null,
          product_id_resolved: Boolean(resolvedProductId),
        },
      };

      const resolvedSubscriptionId = (order.ids.woo_subscription_id
        ? subscriptionIdByWooSubscriptionId.get(order.ids.woo_subscription_id)
          ?.id
        : undefined) ?? subscriptionIdByParentOrderId.get(wooOrderId) ?? null;

      const { error: updateError } = await supabaseAdmin
        .from("orders")
        .update({
          product_id: resolvedProductId,
          order_number: order.order.order_number,
          total_cents: order.order.total_cents,
          subtotal_cents: order.order.total_cents,
          discount_cents: order.order.discount_cents,
          subscription_id: resolvedSubscriptionId,
          metadata,
        })
        .eq("id", existingOrder.id)
        .eq("tenant_id", tenantId);

      if (updateError) {
        throw new Error(
          `Order update failed for woo_order ${wooOrderId}: ${updateError.message}`,
        );
      }

      // Replace the Phase 1 single-stub history entry with the full WC timeline
      // now that Phase 2 has richer date data (payment date, final status date).
      const historyEntries = order.timeline
        .filter((entry) => entry.status && timelineStatusIdsByKey.has(entry.status))
        .map((entry) => ({
          order_id: existingOrder.id,
          status_id: timelineStatusIdsByKey.get(entry.status!)!,
          notes: (entry as { note?: string }).note ?? "Migrated from WooCommerce",
          created_at: entry.occurred_at ?? new Date().toISOString(),
        }));

      if (historyEntries.length > 0) {
        await supabaseAdmin
          .from("order_status_history")
          .delete()
          .eq("order_id", existingOrder.id);
        await supabaseAdmin.from("order_status_history").insert(historyEntries);
      }

      result.orders_processed++;
      result.orders_upserted++;
    } catch (err) {
      result.failed.push({
        section: "orders",
        error: `woo_order ${wooOrderId}: ${(err as Error).message}`,
      });
    }
  });

  // 2. Enrich existing subscription stubs — same bulk-lookup +
  // bounded-concurrency pattern as orders above.
  let existingSubscriptionsByWooSubscriptionId: Map<
    string,
    ExistingSubscriptionStub
  >;
  try {
    existingSubscriptionsByWooSubscriptionId =
      await loadExistingSubscriptionsByWooSubscriptionIds(
        supabaseAdmin,
        tenantId,
        batch.subscriptions.map((sub) => sub.ids.woo_subscription_id),
      );
  } catch (err) {
    result.failed.push({
      section: "subscriptions",
      error: (err as Error).message,
    });
    existingSubscriptionsByWooSubscriptionId = new Map();
  }

  await mapWithConcurrency(
    batch.subscriptions,
    BATCH_CONCURRENCY,
    async (subscription) => {
      const wooSubscriptionId = subscription.ids.woo_subscription_id;

      try {
        const subscriptionSku = String(
          subscription.products[0]?.metadata?.product_mapping?.target_key ?? "",
        );
        const resolvedProductId = subscriptionSku
          ? (productBySku.get(subscriptionSku) ?? null)
          : null;
        if (!resolvedProductId) {
          result.product_ids_unresolved++;
        }

        const existingSubscription = existingSubscriptionsByWooSubscriptionId
          .get(wooSubscriptionId) ?? null;

        if (!existingSubscription) {
          result.subscriptions_skipped++;
          return;
        }

        const metadata = {
          ...(existingSubscription.metadata ?? {}),
          source_billing: {
            stripe_customer_id: typeof subscription.subscription.metadata
                ?.stripe_customer_id === "string"
              ? subscription.subscription.metadata.stripe_customer_id
              : null,
            woo_subscription_id: wooSubscriptionId,
            woo_customer_id: subscription.ids.woo_customer_id,
            woo_parent_order_id: subscription.ids.woo_parent_order_id,
            billing_period: subscription.subscription.billing_period,
            billing_interval: subscription.subscription.billing_interval,
            next_payment_at: subscription.subscription.current_period_end_at,
          },
          migration_phase_2: {
            is_migrated: true,
            imported_at: new Date().toISOString(),
            event_id: subscription.event_id,
            event_type: subscription.event_type,
            source_status: subscription.status,
            occurred_at: subscription.occurred_at,
            updated_at: subscription.updated_at,
            mapped_product_sku: subscriptionSku || null,
            product_id_resolved: Boolean(resolvedProductId),
            stripe_customer_id: typeof subscription.subscription.metadata
                ?.stripe_customer_id === "string"
              ? subscription.subscription.metadata.stripe_customer_id
              : null,
            source_metadata: subscription.subscription.metadata,
          },
        };

        const { error: updateError } = await supabaseAdmin
          .from("subscriptions")
          .update({
            product_id: resolvedProductId,
            started_at: subscription.subscription.started_at,
            current_period_end_at:
              subscription.subscription.current_period_end_at,
            expires_at: subscription.subscription.expires_at,
            cancelled_at: subscription.subscription.cancelled_at,
            metadata,
          })
          .eq("id", existingSubscription.id)
          .eq("tenant_id", tenantId);

        if (updateError) {
          throw new Error(
            `Subscription update failed for woo_subscription ${wooSubscriptionId}: ${updateError.message}`,
          );
        }

        result.subscriptions_processed++;
        result.subscriptions_upserted++;
      } catch (err) {
        result.failed.push({
          section: "subscriptions",
          error: `woo_subscription ${wooSubscriptionId}: ${
            (err as Error).message
          }`,
        });
      }
    },
  );

  // 3. Upsert approved health entries
  const supportedHealthItems = batch.health.filter((item) =>
    item.target_table === "patient_weight_entries" ||
    item.target_table === "medication_shot_intakes" ||
    item.target_table === "patient_body_measurement_entries" ||
    item.target_table === "patient_symptom_entries" ||
    item.target_table === "patient_mood_change_entries" ||
    item.target_table === "patient_activity_entries"
  );
  result.health_skipped += batch.health.length - supportedHealthItems.length;

  const legacyBrelloUids = Array.from(
    new Set(
      supportedHealthItems
        .map((item) => getLegacyBrelloUid(item))
        .filter((value): value is string => Boolean(value)),
    ),
  );

  let patientsByLegacyBrelloUid: Map<string, ExistingPatient>;
  let injectionSiteIdsByLabel: Map<string, string>;
  try {
    patientsByLegacyBrelloUid = await loadPatientsByLegacyBrelloUid(
      supabaseAdmin,
      tenantId,
      legacyBrelloUids,
    );
    const injectionSiteLabels = Array.from(
      new Set(
        supportedHealthItems
          .filter((item) => item.target_table === "medication_shot_intakes")
          .map((item) => getInjectionSiteTargetLabel(item))
          .filter((value): value is string => Boolean(value)),
      ),
    );
    injectionSiteIdsByLabel = await loadInjectionSiteIdsByLabel(
      supabaseAdmin,
      tenantId,
      injectionSiteLabels,
    );
  } catch (err) {
    result.failed.push({
      section: "health",
      error: (err as Error).message,
    });
    patientsByLegacyBrelloUid = new Map();
    injectionSiteIdsByLabel = new Map();
  }

  await mapWithConcurrency(
    supportedHealthItems,
    BATCH_CONCURRENCY,
    async (item) => {
      try {
        const wasUpserted = await processHealthEntry(
          supabaseAdmin,
          tenantId,
          patientsByLegacyBrelloUid,
          injectionSiteIdsByLabel,
          item,
        );

        if (wasUpserted) {
          result.health_upserted++;
        } else {
          result.health_skipped++;
        }
      } catch (err) {
        result.failed.push({
          section: "health",
          error: `${item.target_table} ${item.migration_source_id}: ${
            (err as Error).message
          }`,
        });
      }
    },
  );

  return jsonResponse({
    success: true,
    ...result,
  });
});
