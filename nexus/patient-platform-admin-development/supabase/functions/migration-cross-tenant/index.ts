/**
 * Cross-Tenant Migration — Supabase Edge Function (PP-854)
 *
 * Moves patients already in PP Brello into PP CareLink (allia tenant),
 * copying all related data. Prerequisite: PP-847 schema change must be
 * applied (UNIQUE(auth_user_id, tenant_id) instead of UNIQUE(auth_user_id)).
 *
 * POST /migration-cross-tenant
 * Auth: X-Migration-API-Key header
 *
 * Body:
 *   source_tenant_slug      string   default "brello"
 *   destination_tenant_slug string   default "allia"
 *   emails                  string[] optional — scope to specific patients
 *   dry_run                 boolean  default false
 *
 * Idempotent: safe to re-run. Patients with metadata.migration_status =
 * "migrated_to_carelink" are skipped unless force=true is passed.
 *
 * Returns:
 *   { success, dry_run, summary: { processed, succeeded, skipped, failed },
 *     patients: [{ email, status, detail }] }
 */

import { createClient, type SupabaseClient } from "jsr:@supabase/supabase-js@2";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface RequestBody {
  source_tenant_slug?: string;
  destination_tenant_slug?: string;
  emails?: string[];
  dry_run?: boolean;
  force?: boolean; // re-migrate even if already marked migrated_to_carelink
}

interface PatientResult {
  email: string;
  status: "migrated" | "skipped" | "failed" | "dry_run";
  detail?: string;
  counts?: {
    orders: number;
    subscriptions: number;
    weight_entries: number;
    symptom_entries: number;
  };
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function errorResponse(message: string, status = 500): Response {
  return new Response(JSON.stringify({ success: false, error: message }), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

// ---------------------------------------------------------------------------
// Tenant ID resolution
// ---------------------------------------------------------------------------

const tenantCache = new Map<string, string>();

async function getTenantId(supabase: SupabaseClient, slug: string): Promise<string> {
  if (tenantCache.has(slug)) return tenantCache.get(slug)!;
  const { data, error } = await supabase
    .from("tenants")
    .select("id")
    .eq("slug", slug)
    .maybeSingle();
  if (error || !data) {
    throw new Error(`Tenant "${slug}" not found: ${error?.message ?? "no row"}`);
  }
  tenantCache.set(slug, data.id as string);
  return data.id as string;
}

// ---------------------------------------------------------------------------
// Per-patient migration
// ---------------------------------------------------------------------------

interface SourcePatient {
  id: string;
  auth_user_id: string;
  email: string;
  first_name: string;
  last_name: string;
  phone: string | null;
  date_of_birth: string | null;
  shipping_address_line1: string | null;
  shipping_address_line2: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string | null;
  billing_address_line1: string | null;
  billing_address_line2: string | null;
  billing_city: string | null;
  billing_state: string | null;
  billing_postal_code: string | null;
  billing_country: string | null;
  access_status: string;
  starting_weight: number | null;
  target_weight: number | null;
  metadata: Record<string, unknown>;
}

async function migratePatient(
  supabase: SupabaseClient,
  patient: SourcePatient,
  srcTenantId: string,
  dstTenantId: string,
  dryRun: boolean,
): Promise<{ counts: PatientResult["counts"] }> {
  const counts = { orders: 0, subscriptions: 0, weight_entries: 0, symptom_entries: 0 };

  // 1. Upsert patient in destination tenant (same auth_user_id — requires PP-847)
  if (!dryRun) {
    const { error: patientErr } = await supabase
      .from("patients")
      .upsert(
        {
          tenant_id: dstTenantId,
          auth_user_id: patient.auth_user_id,
          email: patient.email,
          first_name: patient.first_name,
          last_name: patient.last_name,
          phone: patient.phone,
          date_of_birth: patient.date_of_birth,
          shipping_address_line1: patient.shipping_address_line1,
          shipping_address_line2: patient.shipping_address_line2,
          shipping_city: patient.shipping_city,
          shipping_state: patient.shipping_state,
          shipping_postal_code: patient.shipping_postal_code,
          shipping_country: patient.shipping_country ?? "US",
          billing_address_line1: patient.billing_address_line1,
          billing_address_line2: patient.billing_address_line2,
          billing_city: patient.billing_city,
          billing_state: patient.billing_state,
          billing_postal_code: patient.billing_postal_code,
          billing_country: patient.billing_country ?? "US",
          access_status: patient.access_status,
          starting_weight: patient.starting_weight,
          target_weight: patient.target_weight,
          metadata: {
            ...patient.metadata,
            cross_tenant_migration: {
              migrated_from: srcTenantId,
              migrated_at: new Date().toISOString(),
            },
          },
        },
        { onConflict: "tenant_id,email" },
      );
    if (patientErr) {
      throw new Error(`Patient upsert failed: ${patientErr.message}`);
    }
  }

  // Resolve destination patient ID (needed for related-data inserts)
  let dstPatientId: string | null = null;
  if (!dryRun) {
    const { data: dstPatient, error: dstErr } = await supabase
      .from("patients")
      .select("id")
      .eq("tenant_id", dstTenantId)
      .eq("email", patient.email)
      .maybeSingle();
    if (dstErr || !dstPatient) {
      throw new Error(`Could not resolve destination patient ID: ${dstErr?.message ?? "not found"}`);
    }
    dstPatientId = dstPatient.id as string;
  }

  // 2. Copy orders + order_status_history
  const { data: srcOrders, error: ordersErr } = await supabase
    .from("orders")
    .select(
      "id, order_number, status_id, status_changed_at, total_cents, subtotal_cents, shipping_cents, tax_cents, created_at, metadata",
    )
    .eq("tenant_id", srcTenantId)
    .eq("patient_id", patient.id);

  if (ordersErr) {
    throw new Error(`Failed to fetch source orders: ${ordersErr.message}`);
  }

  if (srcOrders && srcOrders.length > 0) {
    // Find which source order IDs are already in destination (idempotency)
    const srcOrderIds = srcOrders.map((o) => o.id as string);
    let existingSourceOrderIds = new Set<string>();

    if (!dryRun) {
      const { data: existingOrders } = await supabase
        .from("orders")
        .select("metadata")
        .eq("tenant_id", dstTenantId)
        .eq("patient_id", dstPatientId!);

      existingSourceOrderIds = new Set(
        (existingOrders ?? [])
          .map((o) => (o.metadata as Record<string, unknown>)?.source_order_id as string)
          .filter(Boolean),
      );
    }

    const ordersToInsert = srcOrders.filter(
      (o) => !existingSourceOrderIds.has(o.id as string),
    );

    if (ordersToInsert.length > 0 && !dryRun) {
      const orderRows = ordersToInsert.map((o) => {
        const srcMeta = (o.metadata ?? {}) as Record<string, unknown>;
        // woo_order_id has a global unique index — rename it so destination copy doesn't collide
        const { woo_order_id: wooOrderId, ...restMeta } = srcMeta;
        return {
          tenant_id: dstTenantId,
          patient_id: dstPatientId!,
          order_number: o.order_number,
          status_id: o.status_id,
          status_changed_at: o.status_changed_at,
          total_cents: o.total_cents,
          subtotal_cents: o.subtotal_cents,
          shipping_cents: o.shipping_cents,
          tax_cents: o.tax_cents,
          created_at: o.created_at,
          metadata: {
            ...restMeta,
            ...(wooOrderId !== undefined ? { legacy_woo_order_id: wooOrderId } : {}),
            source_order_id: o.id,
            source_tenant_id: srcTenantId,
            cross_tenant_migrated_at: new Date().toISOString(),
          },
        };
      });

      const { data: insertedOrders, error: insertOrderErr } = await supabase
        .from("orders")
        .insert(orderRows)
        .select("id, status_id, status_changed_at");

      if (insertOrderErr) {
        throw new Error(`Order insert failed: ${insertOrderErr.message}`);
      }

      counts.orders = insertedOrders?.length ?? 0;

      // Copy order_status_history for newly inserted orders
      if (insertedOrders && insertedOrders.length > 0) {
        // Fetch source history for all source order IDs in one query
        const { data: srcHistory } = await supabase
          .from("order_status_history")
          .select("order_id, status_id, notes, created_at")
          .in("order_id", srcOrderIds);

        if (srcHistory && srcHistory.length > 0) {
          // Map source order IDs → new destination order IDs
          const srcToDestOrderId = new Map<string, string>();
          for (let i = 0; i < ordersToInsert.length; i++) {
            srcToDestOrderId.set(
              ordersToInsert[i].id as string,
              insertedOrders[i].id as string,
            );
          }

          const historyRows = srcHistory
            .filter((h) => srcToDestOrderId.has(h.order_id as string))
            .map((h) => ({
              order_id: srcToDestOrderId.get(h.order_id as string)!,
              status_id: h.status_id,
              notes: h.notes,
              created_at: h.created_at,
            }));

          if (historyRows.length > 0) {
            const { error: historyErr } = await supabase
              .from("order_status_history")
              .insert(historyRows);
            if (historyErr) {
              console.warn(`Order status history insert failed for ${patient.email}:`, historyErr.message);
            }
          }
        }
      }
    } else if (ordersToInsert.length > 0 && dryRun) {
      counts.orders = ordersToInsert.length;
    }
  }

  // 3. Copy subscriptions
  const { data: srcSubs, error: subsErr } = await supabase
    .from("subscriptions")
    .select("id, status, started_at, metadata")
    .eq("tenant_id", srcTenantId)
    .eq("patient_id", patient.id);

  if (subsErr) {
    throw new Error(`Failed to fetch source subscriptions: ${subsErr.message}`);
  }

  if (srcSubs && srcSubs.length > 0) {
    let existingSourceSubIds = new Set<string>();

    if (!dryRun) {
      const { data: existingSubs } = await supabase
        .from("subscriptions")
        .select("metadata")
        .eq("tenant_id", dstTenantId)
        .eq("patient_id", dstPatientId!);

      existingSourceSubIds = new Set(
        (existingSubs ?? [])
          .map((s) => (s.metadata as Record<string, unknown>)?.source_subscription_id as string)
          .filter(Boolean),
      );
    }

    const subsToInsert = srcSubs.filter((s) => !existingSourceSubIds.has(s.id as string));

    if (subsToInsert.length > 0 && !dryRun) {
      const { data: insertedSubs, error: subInsertErr } = await supabase
        .from("subscriptions")
        .insert(
          subsToInsert.map((s) => {
            const srcSubMeta = (s.metadata ?? {}) as Record<string, unknown>;
            // woo_subscription_id has a global unique index — rename to avoid collision
            const { woo_subscription_id: wooSubId, ...restSubMeta } = srcSubMeta;
            return {
              tenant_id: dstTenantId,
              patient_id: dstPatientId!,
              status: s.status,
              started_at: s.started_at,
              metadata: {
                ...restSubMeta,
                ...(wooSubId !== undefined ? { legacy_woo_subscription_id: wooSubId } : {}),
                source_subscription_id: s.id,
                source_tenant_id: srcTenantId,
                cross_tenant_migrated_at: new Date().toISOString(),
              },
            };
          }),
        )
        .select("id");

      if (subInsertErr) {
        throw new Error(`Subscription insert failed: ${subInsertErr.message}`);
      }
      counts.subscriptions = insertedSubs?.length ?? 0;
    } else if (subsToInsert.length > 0 && dryRun) {
      counts.subscriptions = subsToInsert.length;
    }
  }

  // 4. Copy patient_weight_entries (idempotent by weighed_at)
  const { data: srcWeights, error: weightsErr } = await supabase
    .from("patient_weight_entries")
    .select("weight_value, weight_unit, weighed_at, created_at")
    .eq("tenant_id", srcTenantId)
    .eq("patient_id", patient.id);

  if (weightsErr) {
    console.warn(`Failed to fetch weight entries for ${patient.email}:`, weightsErr.message);
  } else if (srcWeights && srcWeights.length > 0) {
    let existingWeighedAts = new Set<string>();

    if (!dryRun) {
      const { data: existingWeights } = await supabase
        .from("patient_weight_entries")
        .select("weighed_at")
        .eq("tenant_id", dstTenantId)
        .eq("patient_id", dstPatientId!);

      existingWeighedAts = new Set(
        (existingWeights ?? []).map((w) => w.weighed_at as string),
      );
    }

    const weightsToInsert = srcWeights.filter(
      (w) => !existingWeighedAts.has(w.weighed_at as string),
    );

    if (weightsToInsert.length > 0 && !dryRun) {
      const { data: insertedWeights, error: weightInsertErr } = await supabase
        .from("patient_weight_entries")
        .insert(
          weightsToInsert.map((w) => ({
            tenant_id: dstTenantId,
            patient_id: dstPatientId!,
            weight_value: w.weight_value,
            weight_unit: w.weight_unit,
            weighed_at: w.weighed_at,
            created_at: w.created_at,
          })),
        )
        .select("id");

      if (weightInsertErr) {
        console.warn(`Weight entry insert failed for ${patient.email}:`, weightInsertErr.message);
      } else {
        counts.weight_entries = insertedWeights?.length ?? 0;
      }
    } else if (weightsToInsert.length > 0 && dryRun) {
      counts.weight_entries = weightsToInsert.length;
    }
  }

  // 5. Copy patient_symptom_entries (idempotent by recorded_at + symptom_label)
  const { data: srcSymptoms, error: symptomsErr } = await supabase
    .from("patient_symptom_entries")
    .select("symptom_label, symptom_severity, symptom_note, recorded_at, created_at")
    .eq("tenant_id", srcTenantId)
    .eq("patient_id", patient.id);

  if (symptomsErr) {
    console.warn(`Failed to fetch symptom entries for ${patient.email}:`, symptomsErr.message);
  } else if (srcSymptoms && srcSymptoms.length > 0) {
    let existingSymptomKeys = new Set<string>();

    if (!dryRun) {
      const { data: existingSymptoms } = await supabase
        .from("patient_symptom_entries")
        .select("recorded_at, symptom_label")
        .eq("tenant_id", dstTenantId)
        .eq("patient_id", dstPatientId!);

      existingSymptomKeys = new Set(
        (existingSymptoms ?? []).map((s) => `${s.recorded_at}::${s.symptom_label}`),
      );
    }

    const symptomsToInsert = srcSymptoms.filter(
      (s) => !existingSymptomKeys.has(`${s.recorded_at}::${s.symptom_label}`),
    );

    if (symptomsToInsert.length > 0 && !dryRun) {
      const { data: insertedSymptoms, error: symptomInsertErr } = await supabase
        .from("patient_symptom_entries")
        .insert(
          symptomsToInsert.map((s) => ({
            tenant_id: dstTenantId,
            patient_id: dstPatientId!,
            symptom_label: s.symptom_label,
            symptom_severity: s.symptom_severity,
            symptom_note: s.symptom_note,
            recorded_at: s.recorded_at,
            created_at: s.created_at,
          })),
        )
        .select("id");

      if (symptomInsertErr) {
        console.warn(`Symptom entry insert failed for ${patient.email}:`, symptomInsertErr.message);
      } else {
        counts.symptom_entries = insertedSymptoms?.length ?? 0;
      }
    } else if (symptomsToInsert.length > 0 && dryRun) {
      counts.symptom_entries = symptomsToInsert.length;
    }
  }

  // 6. Mark source patient as migrated
  if (!dryRun) {
    const { error: markErr } = await supabase
      .from("patients")
      .update({
        metadata: {
          ...patient.metadata,
          migration_status: "migrated_to_carelink",
          migration_status_updated_at: new Date().toISOString(),
        },
      })
      .eq("id", patient.id);

    if (markErr) {
      console.warn(`Failed to mark source patient migrated for ${patient.email}:`, markErr.message);
    }
  }

  return { counts };
}

// ---------------------------------------------------------------------------
// Bounded concurrency
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

  await Promise.all(Array.from({ length: Math.min(concurrency, items.length) }, () => worker()));
  return results;
}

// ---------------------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------------------

Deno.serve(async (req: Request): Promise<Response> => {
  try {
  if (req.method === "OPTIONS") return new Response(null, { status: 204 });
  if (req.method !== "POST") return errorResponse("Method not allowed", 405);

  const apiKey = req.headers.get("X-Migration-API-Key");
  const expectedKey = Deno.env.get("MIGRATION_API_KEY");
  if (!expectedKey || apiKey !== expectedKey) {
    return errorResponse("Unauthorized", 401);
  }

  let body: RequestBody;
  try {
    body = await req.json();
  } catch {
    return errorResponse("Invalid JSON body", 400);
  }

  const srcSlug = body.source_tenant_slug ?? "brello";
  const dstSlug = body.destination_tenant_slug ?? "allia";
  const dryRun = body.dry_run ?? false;
  const force = body.force ?? false;
  const emailFilter = body.emails?.map((e) => e.toLowerCase().trim());

  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const serviceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
  if (!supabaseUrl || !serviceRoleKey) {
    return errorResponse("SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY not configured", 500);
  }

  const supabase = createClient(supabaseUrl, serviceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  let srcTenantId: string, dstTenantId: string;
  try {
    [srcTenantId, dstTenantId] = await Promise.all([
      getTenantId(supabase, srcSlug),
      getTenantId(supabase, dstSlug),
    ]);
  } catch (err) {
    return errorResponse((err as Error).message, 400);
  }

  // Fetch source patients
  let query = supabase
    .from("patients")
    .select(
      "id, auth_user_id, email, first_name, last_name, phone, date_of_birth, " +
      "shipping_address_line1, shipping_address_line2, shipping_city, shipping_state, " +
      "shipping_postal_code, shipping_country, billing_address_line1, billing_address_line2, " +
      "billing_city, billing_state, billing_postal_code, billing_country, " +
      "access_status, starting_weight, target_weight, metadata",
    )
    .eq("tenant_id", srcTenantId);

  if (emailFilter && emailFilter.length > 0) {
    query = query.in("email", emailFilter);
  }

  const { data: srcPatients, error: fetchErr } = await query;
  if (fetchErr) {
    return errorResponse(`Failed to fetch source patients: ${fetchErr.message}`, 500);
  }

  const patients = (srcPatients ?? []) as unknown as SourcePatient[];

  // Separate already-migrated patients (skip unless force=true)
  const toMigrate = patients.filter((p) => {
    const alreadyMigrated =
      (p.metadata?.migration_status as string) === "migrated_to_carelink";
    return force || !alreadyMigrated;
  });

  const skippedCount = patients.length - toMigrate.length;
  const results: PatientResult[] = [];

  await mapWithConcurrency(toMigrate, 3, async (patient) => {
    try {
      const { counts } = await migratePatient(
        supabase,
        patient,
        srcTenantId,
        dstTenantId,
        dryRun,
      );
      results.push({
        email: patient.email,
        status: dryRun ? "dry_run" : "migrated",
        counts,
      });
    } catch (err) {
      const message = (err as Error).message;
      console.error(`Migration failed for ${patient.email}:`, message);
      results.push({ email: patient.email, status: "failed", detail: message });
    }
  });

  const succeeded = results.filter((r) => r.status === "migrated" || r.status === "dry_run").length;
  const failed = results.filter((r) => r.status === "failed").length;

  return jsonResponse({
    success: true,
    dry_run: dryRun,
    source: srcSlug,
    destination: dstSlug,
    summary: {
      total_source_patients: patients.length,
      processed: toMigrate.length,
      succeeded,
      skipped: skippedCount,
      failed,
    },
    patients: results,
  });
  } catch (topErr) {
    console.error("Unhandled top-level error:", (topErr as Error).message, (topErr as Error).stack);
    return errorResponse(`Internal error: ${(topErr as Error).message}`, 500);
  }
});
