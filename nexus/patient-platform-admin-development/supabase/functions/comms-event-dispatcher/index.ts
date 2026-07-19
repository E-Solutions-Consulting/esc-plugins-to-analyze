// comms-event-dispatcher — receive a domain event, match active automations for
// the tenant, create enrollments, and kick off the first node.
//
// Called by event producers (analytics ingestion, subscription_events trigger,
// order status transitions). Internal-only (shared secret). Service-role.
//
// Body: {
//   tenant_id, kind: 'event'|'subscription'|'order'|'relative_time',
//   event_name?, subscription_event_type?, order_status?,
//   patient_id?, subscription_id?, order_id?, product_id?, entity_id?,
//   event?: { event_name, properties },     // for behavioral events
//   context?: {...}   // optional pre-resolved snapshot; merged over what we fetch
// }
//
// Producers only need to pass IDs — the dispatcher resolves patient/subscription/
// order/product/tenant rows into a placeholder context so messages personalise.

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { findTriggerNode, nextNodeId, type CommsEdge, type CommsNode } from "../_shared/comms-automations.ts";
import { triggerMatches } from "./trigger-match.ts";

// deno-lint-ignore no-explicit-any
type DB = any;

const internalSecret = () => Deno.env.get("COMMS_INTERNAL_SECRET");

/**
 * Resolve a placeholder context from the entity ids on the payload. Each lookup is
 * best-effort; missing ids just leave that namespace out. Tenant-scoped reads.
 */
async function resolveContext(
  db: DB,
  tenantId: string,
  payload: Record<string, unknown>,
): Promise<Record<string, unknown>> {
  const ctx: Record<string, unknown> = {};

  const patientId = payload.patient_id as string | undefined;
  const subscriptionId = payload.subscription_id as string | undefined;
  const orderId = payload.order_id as string | undefined;
  let productId = payload.product_id as string | undefined;

  const lookups: Array<Promise<void>> = [];

  if (patientId) {
    lookups.push(
      db.from("patients")
        // Real columns only — the address lives in shipping_* columns. The
        // previous select named phantom city/state/postal_code columns, so the
        // query 42703'd and EVERY event context silently lost its patient
        // block ({{patient.*}} placeholders and n8n payloads had no patient).
        .select(
          "id, first_name, last_name, email, phone, shipping_city, shipping_state, shipping_postal_code, country",
        )
        .eq("id", patientId).eq("tenant_id", tenantId).maybeSingle()
        .then((r: { data: Record<string, unknown> | null; error: unknown }) => {
          if (r.error) console.error("resolveContext patient lookup failed:", r.error);
          if (r.data) {
            // Keep the placeholder vocabulary (patient.city / patient.state /
            // patient.postal_code) stable for templates and n8n consumers.
            const { shipping_city, shipping_state, shipping_postal_code, ...rest } = r.data;
            ctx.patient = {
              ...rest,
              city: shipping_city ?? null,
              state: shipping_state ?? null,
              postal_code: shipping_postal_code ?? null,
            };
          }
        }),
    );
  }
  if (subscriptionId) {
    lookups.push(
      db.from("subscriptions")
        .select("id, status, started_at, current_period_end_at, expires_at, paused_at, cancelled_at, product_id")
        .eq("id", subscriptionId).eq("tenant_id", tenantId).maybeSingle()
        .then((r: { data: { product_id?: string } | null; error: unknown }) => {
          if (r.error) console.error("resolveContext subscription lookup failed:", r.error);
          if (r.data) { ctx.subscription = r.data; if (!productId && r.data.product_id) productId = r.data.product_id; }
        }),
    );
  }
  if (orderId) {
    lookups.push(
      db.from("orders")
        // Status is a RELATION (orders.status_id -> order_statuses), not a
        // column — selecting a phantom `status` column 42703'd and silently
        // dropped the whole order block from every context. Embed the catalog
        // row and flatten to `status` (patient-facing label) below.
        .select(
          "id, order_number, tracking_number, tracking_url, total_cents, subtotal_cents, shipping_cents, tax_cents, shipped_at, delivered_at, created_at, order_statuses!orders_status_id_fkey (status_key, patient_status_label)",
        )
        .eq("id", orderId).eq("tenant_id", tenantId).maybeSingle()
        .then((r: { data: Record<string, unknown> | null; error: unknown }) => {
          if (r.error) console.error("resolveContext order lookup failed:", r.error);
          if (r.data) {
            const { order_statuses: st, ...rest } = r.data as Record<string, unknown> & {
              order_statuses?: { status_key?: string; patient_status_label?: string } | null;
            };
            ctx.order = {
              ...rest,
              status: st?.patient_status_label ?? st?.status_key ?? null,
              status_key: st?.status_key ?? null,
            };
          }
        }),
    );
  }
  lookups.push(
    db.from("tenants").select("name, slug").eq("id", tenantId).maybeSingle()
      .then((r: { data: unknown }) => { if (r.data) ctx.tenant = r.data; }),
  );

  await Promise.all(lookups);

  // Product depends on subscription resolution, so fetch after.
  if (productId) {
    const { data } = await db.from("products")
      .select("id, name, description, sku, price_cents")
      .eq("id", productId).eq("tenant_id", tenantId).maybeSingle();
    if (data) ctx.product = data;
  }

  // Behavioral event payload.
  if (payload.event) ctx.event = payload.event;

  // Caller-provided context wins (lets producers override/augment).
  return { ...ctx, ...((payload.context as Record<string, unknown>) ?? {}) };
}

Deno.serve(async (req) => {
  if (req.method !== "POST") {
    return new Response(JSON.stringify({ error: "Method not allowed" }), { status: 405 });
  }
  const secret = internalSecret();
  if (secret && req.headers.get("authorization") !== `Bearer ${secret}`) {
    return new Response(JSON.stringify({ error: "Unauthorized" }), { status: 401 });
  }

  const url = Deno.env.get("SUPABASE_URL")!;
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const db: DB = createClient(url, serviceKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  try {
    const payload = await req.json();
    const { tenant_id, patient_id } = payload;
    if (!tenant_id || !payload.kind) {
      return new Response(JSON.stringify({ error: "Missing tenant_id or kind" }), { status: 400 });
    }

    let matched: Array<Record<string, unknown>>;
    if (payload.kind === "relative_time" && payload._automation_id) {
      // Scheduler-driven: enroll this specific automation (match already done by date query).
      const { data } = await db
        .from("comms_automations")
        .select("id, trigger_config, enrolled_count")
        .eq("tenant_id", tenant_id)
        .eq("id", payload._automation_id)
        .eq("status", "active");
      matched = data ?? [];
    } else {
      const { data: automations } = await db
        .from("comms_automations")
        .select("id, trigger_config, enrolled_count")
        .eq("tenant_id", tenant_id)
        .eq("status", "active");
      matched = (automations ?? []).filter((a: Record<string, unknown>) =>
        triggerMatches((a.trigger_config ?? {}) as Record<string, unknown>, payload)
      );
    }

    // Resolve the placeholder context once for this event (shared across matches).
    const resolvedContext = await resolveContext(db, tenant_id, payload);

    const enrolled: string[] = [];
    for (const automation of matched) {
      // Load graph to find the trigger node + first real step.
      const [{ data: nodes }, { data: edges }] = await Promise.all([
        db.from("comms_automation_nodes").select("*").eq("automation_id", automation.id).eq("tenant_id", tenant_id),
        db.from("comms_automation_edges").select("*").eq("automation_id", automation.id).eq("tenant_id", tenant_id),
      ]);
      const triggerNode = findTriggerNode((nodes ?? []) as CommsNode[]);
      if (!triggerNode) continue;
      const firstNodeId = nextNodeId((edges ?? []) as CommsEdge[], triggerNode.id);
      if (!firstNodeId) continue;

      // Dedup: one enrollment per (automation, patient + triggering entity).
      const dedupKey = [patient_id, payload.event_name, payload.subscription_event_type, payload.order_status, payload.entity_id]
        .filter(Boolean).join(":") || null;

      const { data: enrollment, error } = await db
        .from("comms_enrollments")
        .insert({
          automation_id: automation.id,
          tenant_id,
          patient_id: patient_id ?? null,
          current_node_id: firstNodeId,
          context: resolvedContext,
          dedup_key: dedupKey,
        })
        .select("id")
        .single();

      if (error) {
        // Unique violation on dedup_key => already enrolled; skip silently.
        if (String(error.code) === "23505") continue;
        console.error("enrollment insert error:", error);
        continue;
      }

      enrolled.push(enrollment.id);
      await db.from("comms_automations")
        .update({ enrolled_count: Number(automation.enrolled_count ?? 0) + 1, last_triggered_at: new Date().toISOString() })
        .eq("id", automation.id);

      // Kick the first node.
      await fetch(`${url}/functions/v1/comms-execute-node`, {
        method: "POST",
        headers: { "Content-Type": "application/json", ...(secret ? { Authorization: `Bearer ${secret}` } : {}) },
        body: JSON.stringify({ enrollment_id: enrollment.id, node_id: firstNodeId }),
      }).catch((e) => console.error("kick first node failed:", e));
    }

    return new Response(
      JSON.stringify({ ok: true, matched: matched.length, enrolled: enrolled.length }),
      { status: 200, headers: { "Content-Type": "application/json" } },
    );
  } catch (error) {
    console.error("comms-event-dispatcher error:", error);
    return new Response(
      JSON.stringify({ error: error instanceof Error ? error.message : "error" }),
      { status: 500 },
    );
  }
});
