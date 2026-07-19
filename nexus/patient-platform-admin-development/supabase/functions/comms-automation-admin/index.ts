// comms-automation-admin — CRUD for Communications Automations.
//
// Authenticated tenant-admin surface used by the builder UI. Verifies JWT +
// tenant membership (existing pattern), then performs graph CRUD with the
// service-role client. See docs/CommunicationsAutomations.md.

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { enrichContext, extractPlaceholders, renderTemplate } from "../_shared/comms-automations.ts";
import { sendEmailViaTenantDistribution } from "../_shared/email-distribution.ts";
import { sendSmsViaTenant } from "../_shared/comms-sms.ts";
import { statusKeysForEvent, subscriptionTypeForEvent } from "../_shared/platform-events.ts";

type Action =
  | "list_automations"
  | "get_automation"
  | "create_automation"
  | "update_automation"
  | "delete_automation"
  | "save_graph"
  | "list_templates"
  | "upsert_template"
  | "delete_template"
  | "list_enrollments"
  | "list_run_steps"
  | "automation_stats"
  | "test_send"
  | "get_sms_provider"
  | "set_sms_provider"
  | "test_trigger"
  | "trigger_catalog";

interface AdminRequest {
  action: Action;
  tenant_id: string;
  [key: string]: unknown;
}

const json = (
  body: unknown,
  status: number,
  cors: Record<string, string>,
) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...cors, "Content-Type": "application/json" },
  });

Deno.serve(async (req) => {
  const cors = buildCorsHeaders(req);
  if (req.method === "OPTIONS") return new Response(null, { headers: cors });
  if (req.method !== "POST") return json({ error: "Method not allowed" }, 405, cors);

  try {
    const url = Deno.env.get("SUPABASE_URL")!;
    const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const anonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
    const db = createClient(url, serviceKey, {
      auth: { autoRefreshToken: false, persistSession: false },
    });

    const body = (await req.json()) as AdminRequest;
    const { action, tenant_id } = body;
    if (!action || !tenant_id) {
      return json({ error: "Missing action or tenant_id" }, 400, cors);
    }

    // --- Auth: verify JWT + tenant membership (mirrors sync-product) ---
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) return json({ error: "Missing authorization header" }, 401, cors);

    const authed = createClient(url, anonKey, {
      global: { headers: { Authorization: authHeader } },
      auth: { autoRefreshToken: false, persistSession: false },
    });
    const { data: { user }, error: userErr } = await authed.auth.getUser();
    if (userErr || !user) return json({ error: "Unauthorized" }, 401, cors);

    const { data: adminUser } = await db
      .from("admin_users")
      .select("id, is_active")
      .eq("auth_user_id", user.id)
      .maybeSingle();
    if (!adminUser || adminUser.is_active === false) {
      return json({ error: "Forbidden" }, 403, cors);
    }

    const { data: isSuperadmin } = await db.rpc("is_platform_superadmin", {
      _auth_user_id: user.id,
    });
    if (!isSuperadmin) {
      const { data: membership } = await db
        .from("tenant_memberships")
        .select("id")
        .eq("admin_user_id", adminUser.id)
        .eq("tenant_id", tenant_id)
        .maybeSingle();
      if (!membership) return json({ error: "Forbidden" }, 403, cors);
    }

    const adminUserId = adminUser.id as string;

    // --- Dispatch ---
    switch (action) {
      case "list_automations": {
        const { data, error } = await db
          .from("comms_automations")
          .select("*")
          .eq("tenant_id", tenant_id)
          .order("updated_at", { ascending: false });
        if (error) throw error;
        return json({ automations: data }, 200, cors);
      }

      case "get_automation": {
        const id = String(body.automation_id);
        const [{ data: automation }, { data: nodes }, { data: edges }] = await Promise.all([
          db.from("comms_automations").select("*").eq("id", id).eq("tenant_id", tenant_id).maybeSingle(),
          db.from("comms_automation_nodes").select("*").eq("automation_id", id).eq("tenant_id", tenant_id),
          db.from("comms_automation_edges").select("*").eq("automation_id", id).eq("tenant_id", tenant_id),
        ]);
        if (!automation) return json({ error: "Not found" }, 404, cors);
        return json({ automation, nodes: nodes ?? [], edges: edges ?? [] }, 200, cors);
      }

      case "create_automation": {
        const { name, description, trigger_config } = body as Record<string, unknown>;
        const automationName = String(name ?? "Untitled automation");
        const { data, error } = await db
          .from("comms_automations")
          .insert({
            tenant_id,
            name: automationName,
            description: description ? String(description) : null,
            trigger_config: trigger_config ?? {},
            created_by: adminUserId,
          })
          .select()
          .single();
        if (error) throw error;

        // Per the n8n hierarchy model: tenant -> project, automation -> folder.
        // Ensure the tenant project exists and create this automation's folder.
        // Best-effort: never block automation creation on n8n provisioning.
        try {
          await fetch(`${url}/functions/v1/comms-n8n-proxy`, {
            method: "POST",
            headers: { "Content-Type": "application/json", Authorization: authHeader },
            body: JSON.stringify({
              action: "ensure_folder",
              tenant_id,
              automation_id: data.id,
              name: automationName,
            }),
          });
        } catch (e) {
          console.error("create_automation: n8n ensure_folder failed (non-fatal)", e);
        }

        return json({ automation: data }, 200, cors);
      }

      case "update_automation": {
        const id = String(body.automation_id);
        const patch: Record<string, unknown> = {};
        for (const k of ["name", "description", "status", "trigger_config", "settings"]) {
          if (k in body) patch[k] = (body as Record<string, unknown>)[k];
        }
        const { data, error } = await db
          .from("comms_automations")
          .update(patch)
          .eq("id", id)
          .eq("tenant_id", tenant_id)
          .select()
          .single();
        if (error) throw error;
        return json({ automation: data }, 200, cors);
      }

      case "delete_automation": {
        const id = String(body.automation_id);
        const { error } = await db
          .from("comms_automations")
          .delete()
          .eq("id", id)
          .eq("tenant_id", tenant_id);
        if (error) throw error;
        return json({ ok: true }, 200, cors);
      }

      case "save_graph": {
        // Diff-save the graph. Critically we DON'T delete-all+reinsert nodes:
        // comms_enrollments.current_node_id (ON DELETE SET NULL) and
        // comms_scheduled_jobs.node_id (ON DELETE CASCADE) reference nodes, so a
        // blanket delete would strand/complete in-flight enrollments and drop their
        // delay jobs. Instead: upsert incoming nodes by id, delete only removed ones.
        const id = String(body.automation_id);
        const nodes = (body.nodes as Array<Record<string, unknown>>) ?? [];
        const edges = (body.edges as Array<Record<string, unknown>>) ?? [];

        const { data: automation } = await db
          .from("comms_automations")
          .select("id")
          .eq("id", id)
          .eq("tenant_id", tenant_id)
          .maybeSingle();
        if (!automation) return json({ error: "Not found" }, 404, cors);

        // Upsert incoming nodes (preserve caller ids so edges/enrollments stay valid).
        const nodeRows = nodes.map((n) => ({
          id: n.id,
          automation_id: id,
          tenant_id,
          node_type: n.node_type,
          config: n.config ?? {},
          position: n.position ?? { x: 0, y: 0 },
        }));
        if (nodeRows.length) {
          const { error: nErr } = await db.from("comms_automation_nodes")
            .upsert(nodeRows, { onConflict: "id" });
          if (nErr) throw nErr;
        }

        // Delete nodes that are no longer in the graph (cascades their edges; sets
        // null on any enrollment that was sitting on a now-removed node).
        const keepIds = nodeRows.map((n) => n.id).filter(Boolean);
        let staleQuery = db.from("comms_automation_nodes").delete()
          .eq("automation_id", id).eq("tenant_id", tenant_id);
        if (keepIds.length) {
          // PostgREST `in` list — UUIDs are safe as bare tokens.
          staleQuery = staleQuery.not("id", "in", `(${keepIds.join(",")})`);
        }
        const { error: delErr } = await staleQuery;
        if (delErr) throw delErr;

        // Edges have no inbound refs from runtime tables, so replace them wholesale.
        await db.from("comms_automation_edges").delete().eq("automation_id", id).eq("tenant_id", tenant_id);
        const edgeRows = edges.map((e, i) => ({
          automation_id: id,
          tenant_id,
          source_node_id: e.source_node_id,
          target_node_id: e.target_node_id,
          branch_label: e.branch_label ?? null,
          sort_order: typeof e.sort_order === "number" ? e.sort_order : i,
        }));
        if (edgeRows.length) {
          const { error: eErr } = await db.from("comms_automation_edges").insert(edgeRows);
          if (eErr) throw eErr;
        }

        await db.from("comms_automations").update({ updated_at: new Date().toISOString() })
          .eq("id", id).eq("tenant_id", tenant_id);

        return json({ ok: true, node_count: nodeRows.length, edge_count: edgeRows.length }, 200, cors);
      }

      case "list_templates": {
        const { data, error } = await db
          .from("comms_templates")
          .select("*")
          .eq("tenant_id", tenant_id)
          .eq("is_archived", false)
          .order("updated_at", { ascending: false });
        if (error) throw error;
        return json({ templates: data }, 200, cors);
      }

      case "upsert_template": {
        const t = body.template as Record<string, unknown>;
        const placeholders = extractPlaceholders(
          `${String(t.subject ?? "")} ${String(t.body ?? "")}`,
        );
        const row = {
          ...(t.id ? { id: t.id } : {}),
          tenant_id,
          channel: t.channel,
          name: t.name,
          subject: t.subject ?? null,
          body: t.body ?? "",
          placeholders,
          created_by: adminUserId,
        };
        const { data, error } = await db
          .from("comms_templates")
          .upsert(row, { onConflict: "id" })
          .select()
          .single();
        if (error) throw error;
        return json({ template: data }, 200, cors);
      }

      case "delete_template": {
        const id = String(body.template_id);
        const { error } = await db
          .from("comms_templates")
          .update({ is_archived: true })
          .eq("id", id)
          .eq("tenant_id", tenant_id);
        if (error) throw error;
        return json({ ok: true }, 200, cors);
      }

      case "list_enrollments": {
        // Recent enrollments for an automation (run activity view).
        const automationId = String(body.automation_id);
        const limit = Math.min(Number(body.limit ?? 50), 200);
        const { data, error } = await db
          .from("comms_enrollments")
          .select("id, patient_id, status, current_node_id, enrolled_at, completed_at, last_error, patients(first_name, last_name, email)")
          .eq("automation_id", automationId)
          .eq("tenant_id", tenant_id)
          .order("enrolled_at", { ascending: false })
          .limit(limit);
        if (error) throw error;
        return json({ enrollments: data }, 200, cors);
      }

      case "list_run_steps": {
        // Per-node execution log for one enrollment.
        const enrollmentId = String(body.enrollment_id);
        const { data, error } = await db
          .from("comms_run_steps")
          .select("id, node_id, node_type, status, provider_message_id, error, metadata, delivery_status, created_at")
          .eq("enrollment_id", enrollmentId)
          .eq("tenant_id", tenant_id)
          .order("created_at", { ascending: true });
        if (error) throw error;
        return json({ steps: data }, 200, cors);
      }

      case "get_sms_provider": {
        // Return whether Twilio is configured for this tenant (never the auth token).
        const { data } = await db
          .from("tenant_integrations")
          .select("is_enabled, settings")
          .eq("tenant_id", tenant_id)
          .eq("integration_key", "twilio")
          .maybeSingle();
        const settings = (data?.settings ?? {}) as Record<string, string>;
        return json({
          configured: !!data,
          is_enabled: data?.is_enabled ?? false,
          account_sid: settings.account_sid ?? null,
          from_number: settings.from_number ?? null,
          has_auth_token: !!settings.auth_token,
        }, 200, cors);
      }

      case "set_sms_provider": {
        // Upsert the tenant's Twilio credentials. Service-role write, but gated by
        // the JWT + tenant-membership check above. Auth token is write-only.
        const accountSid = String(body.account_sid ?? "").trim();
        const fromNumber = String(body.from_number ?? "").trim();
        const authToken = body.auth_token ? String(body.auth_token).trim() : null;
        const isEnabled = body.is_enabled !== false;

        // Preserve the existing auth token if the admin didn't enter a new one.
        const { data: existing } = await db
          .from("tenant_integrations")
          .select("id, settings")
          .eq("tenant_id", tenant_id)
          .eq("integration_key", "twilio")
          .maybeSingle();
        const prevSettings = (existing?.settings ?? {}) as Record<string, string>;
        const settings = {
          account_sid: accountSid || prevSettings.account_sid || "",
          from_number: fromNumber || prevSettings.from_number || "",
          auth_token: authToken ?? prevSettings.auth_token ?? "",
        };

        const row = {
          ...(existing?.id ? { id: existing.id } : {}),
          tenant_id,
          integration_key: "twilio",
          is_enabled: isEnabled,
          settings,
        };
        const { error } = await db
          .from("tenant_integrations")
          .upsert(row, { onConflict: "tenant_id,integration_key" });
        if (error) throw error;
        return json({ ok: true, has_auth_token: !!settings.auth_token }, 200, cors);
      }

      case "test_trigger": {
        // Fire this automation's trigger on demand with a sample (or real) patient,
        // so the journey runs instantly in a demo without waiting for the cron sweep.
        const automationId = String(body.automation_id);
        const { data: automation } = await db
          .from("comms_automations")
          .select("id, trigger_config, status")
          .eq("id", automationId).eq("tenant_id", tenant_id).maybeSingle();
        if (!automation) return json({ error: "Automation not found" }, 404, cors);
        if (automation.status !== "active") {
          return json({ error: "Activate the automation before sending a test event" }, 409, cors);
        }
        const tcfg = (automation.trigger_config ?? {}) as Record<string, unknown>;

        // Use a real patient if one is given/available so personalisation works.
        let patientId = body.patient_id ? String(body.patient_id) : null;
        if (!patientId) {
          const { data: p } = await db.from("patients")
            .select("id").eq("tenant_id", tenant_id).limit(1).maybeSingle();
          patientId = p?.id ?? null;
        }

        // Build a dispatcher payload matching the automation's trigger kind.
        //
        // entity_id must be UNIQUE PER CLICK. It feeds the dispatcher's dedup key,
        // and a constant one (the old `test-<automationId>`) meant the 2nd and
        // every later test for the same patient hit the enrollments unique index,
        // was swallowed as a duplicate, and returned enrolled:0 — which reads as
        // "your trigger doesn't match" and sends people hunting a bug that isn't
        // there. With a unique id, enrolled:0 honestly means "no match".
        const payload: Record<string, unknown> = {
          tenant_id,
          patient_id: patientId,
          entity_id: `test-${crypto.randomUUID()}`,
        };
        const kind = String(tcfg.kind ?? "event");
        if (kind === "order") {
          payload.kind = "order";
          // Named event (canonical) is the configured form; resolve it back to a
          // representative status_key so the dispatcher matches exactly as it
          // would for a real swept row. Raw to_status is the advanced/legacy form.
          const eventKey = tcfg.event_key ? String(tcfg.event_key) : null;
          const statusKey = eventKey
            ? statusKeysForEvent(eventKey)[0] ?? null
            : (tcfg.to_status ? String(tcfg.to_status) : null);
          if (!statusKey) {
            return json({ error: "Trigger has no order event configured" }, 400, cors);
          }
          payload.order_status = statusKey;
          // Attach a real order if available for richer context.
          const { data: o } = await db.from("orders")
            .select("id").eq("tenant_id", tenant_id)
            .eq(patientId ? "patient_id" : "tenant_id", patientId ?? tenant_id)
            .limit(1).maybeSingle();
          if (o?.id) payload.order_id = o.id;
        } else if (kind === "subscription") {
          payload.kind = "subscription";
          const eventKey = tcfg.event_key ? String(tcfg.event_key) : null;
          if (eventKey) {
            // subscription.renewed has no subscription_events type behind it (it
            // comes from a paid renewal order), so pass the key straight through —
            // the dispatcher matches on event_key first.
            payload.event_key = eventKey;
            const rawType = subscriptionTypeForEvent(eventKey);
            if (rawType) payload.subscription_event_type = rawType;
          } else {
            payload.subscription_event_type = tcfg.event_type ?? "created";
          }
          const { data: s } = await db.from("subscriptions")
            .select("id").eq("tenant_id", tenant_id).limit(1).maybeSingle();
          if (s?.id) payload.subscription_id = s.id;
        } else {
          payload.kind = "event";
          payload.event_name = tcfg.event_name ?? "checkout_completed";
          payload.event = { event_name: payload.event_name, properties: { test: true } };
        }

        const internalSecret = Deno.env.get("COMMS_INTERNAL_SECRET");
        const res = await fetch(`${url}/functions/v1/comms-event-dispatcher`, {
          method: "POST",
          headers: { "Content-Type": "application/json", ...(internalSecret ? { Authorization: `Bearer ${internalSecret}` } : {}) },
          body: JSON.stringify(payload),
        });
        const dispatchResult = await res.json().catch(() => ({}));
        return json({ ok: res.ok, dispatched: payload, result: dispatchResult }, res.ok ? 200 : 502, cors);
      }

      case "test_send": {
        // Render a template (or inline subject/body) against a sample context and
        // send one message to a test recipient. Lets admins preview real delivery.
        const channel = String(body.channel ?? "email");
        const to = String(body.to ?? "").trim();
        if (!to) return json({ error: "Missing test recipient (to)" }, 400, cors);

        let subject = String(body.subject ?? "");
        let messageBody = String(body.body ?? "");
        if (body.template_id) {
          const { data: tpl } = await db
            .from("comms_templates")
            .select("subject, body, channel")
            .eq("id", String(body.template_id))
            .eq("tenant_id", tenant_id)
            .maybeSingle();
          if (tpl) {
            subject = tpl.subject ?? subject;
            messageBody = tpl.body || messageBody;
          }
        }

        // Sample context: caller may pass one; default to a small illustrative set.
        const sampleCtx = enrichContext(
          (body.sample_context as Record<string, unknown>) ?? {
            patient: { first_name: "Jordan", last_name: "Lee", email: to },
            tenant: {},
          },
        );

        try {
          if (channel === "sms") {
            const sid = await sendSmsViaTenant(db, tenant_id, to, renderTemplate(messageBody, sampleCtx));
            return json({ ok: true, channel, provider_message_id: sid }, 200, cors);
          }
          const result = await sendEmailViaTenantDistribution({
            supabaseClient: db,
            tenantId: tenant_id,
            to,
            subject: renderTemplate(subject || "Test message", sampleCtx),
            html: renderTemplate(messageBody, sampleCtx),
            logContext: { testSend: true },
          });
          return json({ ok: true, channel, integration: result.integrationKey, provider_message_id: result.messageId }, 200, cors);
        } catch (e) {
          return json({ error: e instanceof Error ? e.message : "test send failed" }, 502, cors);
        }
      }

      case "trigger_catalog": {
        // Real, data-driven trigger options so the UI never shows a stale hardcoded
        // list. Order statuses + analytics event names come from their catalog
        // tables; subscription event types are a fixed enum from the DB trigger fn.
        const [{ data: orderStatuses }, { data: eventTypes }] = await Promise.all([
          db.from("order_statuses")
            .select("status_key, admin_status_label, display_order, is_active")
            .eq("is_active", true)
            .order("display_order", { ascending: true }),
          db.from("analytics_event_types")
            .select("key, category, description, is_active")
            .eq("is_active", true)
            .order("category", { ascending: true }),
        ]);
        return json({
          order_statuses: (orderStatuses ?? []).map((s: Record<string, unknown>) => ({
            key: s.status_key,
            label: s.admin_status_label,
          })),
          event_names: (eventTypes ?? []).map((e: Record<string, unknown>) => ({
            key: e.key,
            category: e.category,
            description: e.description,
          })),
          // Canonical subscription_events.event_type values (from
          // log_subscription_lifecycle_event()). Not a table, so enumerated here.
          subscription_event_types: [
            "created", "cancelled", "paused", "resumed",
            "renewal_date_changed", "expiration_date_changed",
            "status_changed", "lifecycle_updated",
          ],
        }, 200, cors);
      }

      case "automation_stats": {
        // Lightweight counts for the activity header.
        const automationId = String(body.automation_id);
        const { data, error } = await db
          .from("comms_enrollments")
          .select("status")
          .eq("automation_id", automationId)
          .eq("tenant_id", tenant_id);
        if (error) throw error;
        const counts: Record<string, number> = {};
        for (const r of (data ?? []) as Array<{ status: string }>) {
          counts[r.status] = (counts[r.status] ?? 0) + 1;
        }
        return json({ counts, total: (data ?? []).length }, 200, cors);
      }

      default:
        return json({ error: `Unknown action: ${action}` }, 400, cors);
    }
  } catch (error) {
    console.error("comms-automation-admin error:", error);
    return json(
      { error: error instanceof Error ? error.message : "Internal server error" },
      500,
      cors,
    );
  }
});
