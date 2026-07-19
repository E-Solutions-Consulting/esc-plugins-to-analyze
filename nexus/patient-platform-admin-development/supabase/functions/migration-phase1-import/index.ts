/**
 * Migration Phase 1 Import — Supabase Edge Function
 *
 * Receives batches of transformed Brello user + WooCommerce order data from
 * the brelloMigrationPhase1 Cloud Function and persists them to Supabase:
 *   1. Creates a Supabase auth.users entry for each patient (email only, no password).
 *   2. Upserts a `patients` row linked to the auth user.
 *   3. Upserts `subscriptions` stubs (woo_subscription_id in metadata).
 *   4. Upserts `orders` stubs (woo_order_id in metadata, status_id resolved from order_statuses).
 *
 * Security: Requires `X-Migration-API-Key` header matching MIGRATION_API_KEY env var.
 * Not intended to be called from the browser — CORS is restricted to the Cloud Function.
 *
 * Idempotent: Re-running with the same data produces the same result (upserts, no duplicates).
 */

import { createClient, type SupabaseClient } from "jsr:@supabase/supabase-js@2";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
interface PatientPayload {
  email: string;
  first_name: string;
  last_name: string;
  phone: string | null;
  date_of_birth: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string;
  access_status: "active" | "deactivated";
  starting_weight: number | null;
  target_weight: number | null;
  metadata: Record<string, unknown>;
}

interface SubscriptionStub {
  woo_subscription_id: string;
  status: string;
  woo_customer_id: string;
  created_at: string | null;
}

interface OrderStub {
  woo_order_id: string;
  woo_customer_id: string;
  woo_parent_order_id: string | null;
  status_key: string;
  order_number: string;
  total_cents: number;
  created_at: string | null;
}

interface MigrationBatch {
  patients: PatientPayload[];
  subscriptions: SubscriptionStub[];
  orders: OrderStub[];
  tenant_slug?: string;
}

interface BatchResult {
  processed: number;
  auth_created: number;
  patients_upserted: number;
  subscriptions_upserted: number;
  orders_upserted: number;
  failed: Array<{ email: string; error: string }>;
}

// ---------------------------------------------------------------------------
// Status key → status_id cache (loaded once per invocation)
// ---------------------------------------------------------------------------
let statusKeyCache: Map<string, string> | null = null;

async function getStatusIdByKey(
  supabase: SupabaseClient,
  statusKey: string,
): Promise<string | null> {
  if (!statusKeyCache) {
    const { data, error } = await supabase
      .from("order_statuses")
      .select("id, status_key")
      .eq("is_active", true);

    if (error || !data) {
      console.error("Failed to load order_statuses", error);
      statusKeyCache = new Map();
    } else {
      statusKeyCache = new Map(data.map((row) => [row.status_key, row.id]));
    }
  }
  return statusKeyCache.get(statusKey) ?? null;
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
// TelegraMD integration ID cache (keyed by tenant_id)
// ---------------------------------------------------------------------------
const telegraIntegrationIdCache = new Map<string, string | null>();

async function getTelegraIntegrationId(
  supabase: SupabaseClient,
  tenantId: string,
): Promise<string | null> {
  if (telegraIntegrationIdCache.has(tenantId)) {
    return telegraIntegrationIdCache.get(tenantId) ?? null;
  }
  const { data, error } = await supabase
    .from("tenant_integrations")
    .select("id")
    .eq("tenant_id", tenantId)
    .eq("integration_key", "telegramd")
    .maybeSingle();

  const value = (!error && data?.id) ? data.id as string : null;
  telegraIntegrationIdCache.set(tenantId, value);
  return value;
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

function normalizeEmail(email: string): string {
  return email.toLowerCase().trim();
}

async function findAuthUserIdByEmail(
  supabaseAdmin: SupabaseClient,
  email: string,
): Promise<string | null> {
  const normalizedEmail = normalizeEmail(email);
  const perPage = 1000;

  for (let page = 1; page <= 200; page++) {
    const { data, error } = await supabaseAdmin.auth.admin.listUsers({
      page,
      perPage,
    });

    if (error) {
      throw new Error(
        `Auth user list failed for ${normalizedEmail}: ${error.message}`,
      );
    }

    const users = data?.users ?? [];
    const found = users.find((user) =>
      normalizeEmail(user.email ?? "") === normalizedEmail
    );
    if (found?.id) return found.id;

    if (users.length < perPage) break;
  }

  return null;
}

// ---------------------------------------------------------------------------
// Per-patient processing
// ---------------------------------------------------------------------------
async function processPatient(
  supabaseAdmin: SupabaseClient,
  tenantId: string,
  patient: PatientPayload,
  subscriptions: SubscriptionStub[],
  orders: OrderStub[],
  telegraIntegId: string | null,
): Promise<{
  auth_created: boolean;
  patient_upserted: boolean;
  subscriptions_upserted: number;
  orders_upserted: number;
}> {
  const email = normalizeEmail(patient.email);

  // 1. Resolve or create auth user
  let authUserId: string;
  let authCreated = false;

  const { data: existingPatient, error: existingPatientError } =
    await supabaseAdmin
      .from("patients")
      .select("id, auth_user_id")
      .eq("tenant_id", tenantId)
      .eq("email", email)
      .maybeSingle();

  if (existingPatientError) {
    throw new Error(
      `Existing patient lookup failed for ${email}: ${existingPatientError.message}`,
    );
  }

  if (existingPatient?.auth_user_id) {
    authUserId = existingPatient.auth_user_id as string;
  } else {
    const { data: newUser, error: createError } = await supabaseAdmin.auth.admin
      .createUser({
        email,
        email_confirm: true,
        // No password — patient sets password later via magic link / reset flow
      });

    if (newUser?.user) {
      authUserId = newUser.user.id;
      authCreated = true;
    } else if (createError) {
      const existingAuthUserId = await findAuthUserIdByEmail(
        supabaseAdmin,
        email,
      );
      if (!existingAuthUserId) {
        throw new Error(
          `Auth user create/lookup failed for ${email}: ${createError.message}`,
        );
      }
      authUserId = existingAuthUserId;
    } else {
      throw new Error(`Auth user creation failed for ${email}: unknown error`);
    }
  }

  // 2. Upsert patient profile — select the id back from the upsert response
  // instead of a separate SELECT round-trip (this used to be 2 calls).
  const { data: patientRow, error: patientError } = await supabaseAdmin
    .from("patients")
    .upsert(
      {
        tenant_id: tenantId,
        auth_user_id: authUserId,
        email,
        first_name: patient.first_name,
        last_name: patient.last_name,
        phone: patient.phone,
        date_of_birth: patient.date_of_birth,
        shipping_address_line1: patient.address_line1,
        shipping_address_line2: patient.address_line2,
        shipping_city: patient.city,
        shipping_state: patient.state,
        shipping_postal_code: patient.postal_code,
        shipping_country: patient.country || "US",
        billing_address_line1: patient.address_line1,
        billing_address_line2: patient.address_line2,
        billing_city: patient.city,
        billing_state: patient.state,
        billing_postal_code: patient.postal_code,
        billing_country: patient.country || "US",
        access_status: patient.access_status,
        starting_weight: patient.starting_weight,
        target_weight: patient.target_weight,
        metadata: patient.metadata,
      },
      { onConflict: "tenant_id,email" },
    )
    .select("id")
    .single();

  if (patientError || !patientRow) {
    throw new Error(
      `Patient upsert failed for ${email}: ${
        patientError?.message ?? "no row returned"
      }`,
    );
  }
  const patientId = patientRow.id as string;

  // 2b. Link Telegra provider platform — creates the patient_provider_platform_links
  // row so the Provider Platform tab is populated without needing Step 3.
  const telegraId = patient.metadata?.telegra_id as string | null | undefined;
  if (telegraId && telegraIntegId) {
    const { error: telegraError } = await supabaseAdmin
      .from("patient_provider_platform_links")
      .upsert(
        {
          tenant_id: tenantId,
          patient_id: patientId,
          tenant_integration_id: telegraIntegId,
          provider_patient_id: telegraId,
          metadata: { provider: "TelegraMD", source: "migration", is_migrated: true },
        },
        { onConflict: "patient_id,tenant_integration_id" },
      );
    if (telegraError) {
      console.warn(`Telegra link upsert failed for patient ${email}:`, telegraError.message);
    }
  }

  // 3. Upsert subscription stubs — one SELECT for all existing
  // woo_subscription_ids on this patient, then a single bulk INSERT for
  // whichever are new, instead of a SELECT+INSERT round-trip per row.
  let subscriptionsUpserted = 0;

  if (subscriptions.length > 0) {
    const { data: existingSubRows, error: existingSubsError } =
      await supabaseAdmin
        .from("subscriptions")
        .select("metadata")
        .eq("tenant_id", tenantId)
        .eq("patient_id", patientId);

    if (existingSubsError) {
      console.warn(
        `Existing subscription lookup failed for patient ${patientId}:`,
        existingSubsError.message,
      );
    }

    const existingWooSubIds = new Set(
      (existingSubRows ?? [])
        .map((row) =>
          (row.metadata as Record<string, unknown> | null)
            ?.woo_subscription_id
        )
        .filter((id): id is string => typeof id === "string"),
    );

    const newSubs = subscriptions.filter(
      (sub) => !existingWooSubIds.has(sub.woo_subscription_id),
    );

    if (newSubs.length > 0) {
      const { data: insertedSubs, error: subError } = await supabaseAdmin
        .from("subscriptions")
        .insert(
          newSubs.map((sub) => ({
            tenant_id: tenantId,
            patient_id: patientId,
            status: sub.status,
            started_at: sub.created_at || null,
            metadata: {
              woo_subscription_id: sub.woo_subscription_id,
              is_migrated: true,
              migration_phase: 1,
            },
          })),
        )
        .select("id");

      if (subError) {
        console.warn(
          `Subscription stub bulk insert failed for patient ${patientId}:`,
          subError.message,
        );
      } else {
        subscriptionsUpserted = insertedSubs?.length ?? 0;
      }
    }
  }

  // 4. Upsert order stubs — same one-SELECT-then-bulk-INSERT pattern as
  // subscriptions above.
  let ordersUpserted = 0;

  if (orders.length > 0) {
    const { data: existingOrderRows, error: existingOrdersError } =
      await supabaseAdmin
        .from("orders")
        .select("metadata")
        .eq("tenant_id", tenantId)
        .eq("patient_id", patientId);

    if (existingOrdersError) {
      console.warn(
        `Existing order lookup failed for patient ${patientId}:`,
        existingOrdersError.message,
      );
    }

    const existingWooOrderIds = new Set(
      (existingOrderRows ?? [])
        .map((row) =>
          (row.metadata as Record<string, unknown> | null)?.woo_order_id
        )
        .filter((id): id is string => typeof id === "string"),
    );

    const newOrderRows: Record<string, unknown>[] = [];
    for (const order of orders) {
      if (existingWooOrderIds.has(order.woo_order_id)) continue; // already migrated

      const statusId = await getStatusIdByKey(supabaseAdmin, order.status_key);
      if (!statusId) {
        console.warn(
          `Unknown PP status_key '${order.status_key}' for woo_order ${order.woo_order_id}, defaulting to payment_pending`,
        );
      }

      const resolvedStatusId = statusId ??
        (await getStatusIdByKey(supabaseAdmin, "payment_pending"));
      if (!resolvedStatusId) {
        console.warn(
          `Could not resolve any status_id for woo_order ${order.woo_order_id}, skipping`,
        );
        continue;
      }

      newOrderRows.push({
        tenant_id: tenantId,
        patient_id: patientId,
        order_number: order.order_number,
        status_id: resolvedStatusId,
        status_changed_at: order.created_at || new Date().toISOString(),
        total_cents: order.total_cents,
        subtotal_cents: order.total_cents,
        shipping_cents: 0,
        tax_cents: 0,
        created_at: order.created_at || new Date().toISOString(),
        metadata: {
          woo_order_id: order.woo_order_id,
          woo_parent_order_id: order.woo_parent_order_id,
          is_migrated: true,
          migration_phase: 1,
          migration_phase_1: {
            imported_at: new Date().toISOString(),
          },
        },
      });
    }

    if (newOrderRows.length > 0) {
      const { data: insertedOrders, error: orderError } = await supabaseAdmin
        .from("orders")
        .insert(newOrderRows)
        .select("id, status_id, status_changed_at");

      if (orderError) {
        console.warn(
          `Order stub bulk insert failed for patient ${patientId}:`,
          orderError.message,
        );
      } else {
        ordersUpserted = insertedOrders?.length ?? 0;

        // Create one status history entry per inserted order so the UI's
        // "Status History" card shows the initial migrated status instead of
        // "No status changes recorded yet".
        if (insertedOrders && insertedOrders.length > 0) {
          const { error: historyError } = await supabaseAdmin
            .from("order_status_history")
            .insert(
              insertedOrders.map((o) => ({
                order_id: o.id,
                status_id: o.status_id,
                notes: "Migrated from WooCommerce",
                created_at: o.status_changed_at,
              })),
            );
          if (historyError) {
            console.warn(
              `Order status history bulk insert failed for patient ${patientId}:`,
              historyError.message,
            );
          }
        }
      }
    }
  }

  return {
    auth_created: authCreated,
    patient_upserted: true,
    subscriptions_upserted: subscriptionsUpserted,
    orders_upserted: ordersUpserted,
  };
}

// ---------------------------------------------------------------------------
// Bounded-concurrency map — processes patients in parallel (not one at a
// time) so a 100-patient batch's wall time is ~batch/concurrency instead of
// ~batch, which is what was tipping batches over Supabase's fixed 150s edge
// function idle timeout.
// ---------------------------------------------------------------------------
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

  if (!Array.isArray(batch.patients) || batch.patients.length === 0) {
    return errorResponse(
      "patients array is required and must be non-empty",
      400,
    );
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

  // Resolve Telegra integration id once per invocation (null-safe — if not
  // configured for this tenant the provider link step is simply skipped).
  const telegraIntegId = await getTelegraIntegrationId(supabaseAdmin, tenantId);

  // Build a patient email → orders/subscriptions lookup from the batch
  const ordersByWooCustomerId = new Map<string, OrderStub[]>();
  const subscriptionsByWooCustomerId = new Map<string, SubscriptionStub[]>();

  for (const order of batch.orders ?? []) {
    const cid = String(order.woo_customer_id);
    if (!ordersByWooCustomerId.has(cid)) ordersByWooCustomerId.set(cid, []);
    ordersByWooCustomerId.get(cid)!.push(order);
  }
  for (const sub of batch.subscriptions ?? []) {
    const cid = String(sub.woo_customer_id);
    if (!subscriptionsByWooCustomerId.has(cid)) {
      subscriptionsByWooCustomerId.set(cid, []);
    }
    subscriptionsByWooCustomerId.get(cid)!.push(sub);
  }

  const result: BatchResult = {
    processed: 0,
    auth_created: 0,
    patients_upserted: 0,
    subscriptions_upserted: 0,
    orders_upserted: 0,
    failed: [],
  };

  // Process patients with bounded concurrency rather than one at a time —
  // sequential processing is what was pushing 100-patient batches past
  // Supabase's fixed 150s edge function idle timeout.
  // Kept conservative (not higher) because sustained runs - even a single
  // stream with no competing parallelism - degraded after a few hours at
  // concurrency 10, pointing at resource exhaustion that accumulates over
  // many sequential invocations rather than purely simultaneous load.
  const PATIENT_CONCURRENCY = 5;

  await mapWithConcurrency(
    batch.patients,
    PATIENT_CONCURRENCY,
    async (patient) => {
      const wooId = patient.metadata?.woo_id
        ? String(patient.metadata.woo_id)
        : null;
      const patientOrders = wooId
        ? (ordersByWooCustomerId.get(wooId) ?? [])
        : [];
      const patientSubs = wooId
        ? (subscriptionsByWooCustomerId.get(wooId) ?? [])
        : [];

      try {
        const patientResult = await processPatient(
          supabaseAdmin,
          tenantId,
          patient,
          patientSubs,
          patientOrders,
          telegraIntegId,
        );
        result.processed++;
        result.patients_upserted++;
        if (patientResult.auth_created) result.auth_created++;
        result.subscriptions_upserted += patientResult.subscriptions_upserted;
        result.orders_upserted += patientResult.orders_upserted;
      } catch (err) {
        const message = (err as Error).message;
        console.error(`Failed to process patient ${patient.email}:`, message);
        result.failed.push({ email: patient.email, error: message });
      }
    },
  );

  return jsonResponse({
    success: true,
    ...result,
  });
});
