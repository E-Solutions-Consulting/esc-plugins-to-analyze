// comms-execute-node — execute a single automation node for one enrollment and
// advance the enrollment to its next node (or park it for a delay).
//
// Invoked by comms-event-dispatcher (first node) and comms-scheduler (resumed
// after a delay). Service-role; always scoped by tenant_id. Idempotent via the
// unique (enrollment_id, node_id) index on comms_run_steps.
//
// Body: { enrollment_id: string, node_id?: string }  (node_id defaults to enrollment.current_node_id)

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { sendEmailViaTenantDistribution } from "../_shared/email-distribution.ts";
import { sendSmsViaTenant } from "../_shared/comms-sms.ts";
import {
  computeDelayRunAt,
  enrichContext,
  escapeHtml,
  evaluateCondition,
  maskEmail,
  maskPhone,
  nextNodeId,
  renderTemplate,
  type BranchCondition,
  type CommsEdge,
  type CommsNode,
} from "../_shared/comms-automations.ts";
import {
  defaultN8nBaseUrl,
  resolveApiKey,
  toTestWebhookUrl,
  triggerWebhook,
} from "../_shared/comms-n8n.ts";

// deno-lint-ignore no-explicit-any
type DB = any;

const internalSecret = () => Deno.env.get("COMMS_INTERNAL_SECRET");

async function loadTemplateBody(
  db: DB,
  tenantId: string,
  templateId: string,
): Promise<{ subject: string | null; body: string } | null> {
  const { data } = await db
    .from("comms_templates")
    .select("subject, body")
    .eq("id", templateId)
    .eq("tenant_id", tenantId)
    .maybeSingle();
  return data ?? null;
}

async function logStep(
  db: DB,
  row: Record<string, unknown>,
): Promise<void> {
  // Upsert keyed on (enrollment_id, node_id) for idempotency. Requires the
  // FULL unique index uq_comms_run_steps_enrollment_node — with the original
  // partial index Postgres rejected ON CONFLICT with 42P10 and, unchecked,
  // every step silently vanished (empty Activity, dead idempotency guard).
  const { error } = await db.from("comms_run_steps").upsert(row, {
    onConflict: "enrollment_id,node_id",
    ignoreDuplicates: false,
  });
  if (error) {
    // Loud but non-fatal: losing the log must not abort the journey itself.
    console.error("CRITICAL logStep failed (step history lost):", error, row);
  }
}

Deno.serve(async (req) => {
  if (req.method !== "POST") {
    return new Response(JSON.stringify({ error: "Method not allowed" }), { status: 405 });
  }
  // Internal-only: require shared secret (dispatcher/scheduler call this).
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
    const { enrollment_id, node_id } = await req.json();
    if (!enrollment_id) {
      return new Response(JSON.stringify({ error: "Missing enrollment_id" }), { status: 400 });
    }

    const { data: enrollment } = await db
      .from("comms_enrollments")
      .select("*")
      .eq("id", enrollment_id)
      .maybeSingle();
    if (!enrollment) {
      return new Response(JSON.stringify({ error: "Enrollment not found" }), { status: 404 });
    }
    if (enrollment.status !== "active") {
      return new Response(JSON.stringify({ ok: true, skipped: "not_active" }), { status: 200 });
    }

    const tenantId = enrollment.tenant_id as string;
    const targetNodeId = (node_id ?? enrollment.current_node_id) as string | null;
    if (!targetNodeId) {
      // No node to run -> complete.
      await db.from("comms_enrollments")
        .update({ status: "completed", completed_at: new Date().toISOString() })
        .eq("id", enrollment_id);
      return new Response(JSON.stringify({ ok: true, completed: true }), { status: 200 });
    }

    const [{ data: node }, { data: edges }] = await Promise.all([
      db.from("comms_automation_nodes").select("*").eq("id", targetNodeId).eq("tenant_id", tenantId).maybeSingle(),
      db.from("comms_automation_edges").select("*").eq("automation_id", enrollment.automation_id).eq("tenant_id", tenantId),
    ]);
    if (!node) {
      return new Response(JSON.stringify({ error: "Node not found" }), { status: 404 });
    }

    const ctx = enrichContext((enrollment.context ?? {}) as Record<string, unknown>);
    const typedNode = node as CommsNode;
    const typedEdges = (edges ?? []) as CommsEdge[];
    let branchOutcome: string | null = null;
    let parked = false;

    const baseStep = {
      enrollment_id,
      automation_id: enrollment.automation_id,
      tenant_id: tenantId,
      node_id: typedNode.id,
      node_type: typedNode.node_type,
    };

    // Idempotency guard: if this (enrollment, node) already produced a terminal
    // step, don't re-run side effects (covers scheduler recovery / double-invoke).
    // We still need to advance, so fall through to the advance logic below.
    const { data: priorStep } = await db
      .from("comms_run_steps")
      .select("status, metadata, node_type")
      .eq("enrollment_id", enrollment_id)
      .eq("node_id", typedNode.id)
      .maybeSingle();
    const alreadyRan = !!priorStep &&
      ["sent", "skipped", "scheduled"].includes(String(priorStep.status));

    if (alreadyRan) {
      // For a delay already parked, do nothing (its job will resume it).
      if (priorStep.status === "scheduled") {
        return new Response(JSON.stringify({ ok: true, already: "scheduled" }), { status: 200 });
      }
      // For branch/multi_split we must recompute the outcome to pick the edge.
      if (typedNode.node_type === "branch") {
        const cond = (typedNode.config as Record<string, unknown>).condition as BranchCondition;
        branchOutcome = cond && evaluateCondition(cond, ctx) ? "true" : "false";
      } else if (typedNode.node_type === "multi_split") {
        const key = (typedNode.config as Record<string, unknown>).cohort_field;
        branchOutcome = key ? String((ctx as Record<string, unknown>)[String(key)] ?? "") : null;
      } else if (typedNode.node_type === "exit") {
        return new Response(JSON.stringify({ ok: true, already: "exited" }), { status: 200 });
      }
      // Skip the side-effect switch; go straight to advancing.
    } else
    switch (typedNode.node_type) {
      case "trigger": {
        // Pass-through; trigger is the entry marker.
        await logStep(db, { ...baseStep, status: "skipped" });
        break;
      }

      case "email": {
        const cfg = typedNode.config as Record<string, unknown>;
        const to = String((ctx.patient as Record<string, unknown>)?.email ?? cfg.to ?? "");
        if (!to) {
          await logStep(db, { ...baseStep, status: "skipped", error: "no_recipient" });
          break;
        }
        let subject = String(cfg.subject ?? "");
        let html = String(cfg.html ?? cfg.body ?? "");
        if (cfg.template_id) {
          const tpl = await loadTemplateBody(db, tenantId, String(cfg.template_id));
          if (tpl) {
            subject = tpl.subject ?? subject;
            html = tpl.body || html;
          }
        }
        // Escape patient-derived values when interpolating into HTML.
        const safeCtx = { ...ctx };
        const result = await sendEmailViaTenantDistribution({
          supabaseClient: db,
          tenantId,
          to,
          subject: renderTemplate(subject, safeCtx),
          html: renderTemplate(html, safeCtx),
          logContext: { automationId: enrollment.automation_id, enrollmentId: enrollment_id },
        });
        await logStep(db, {
          ...baseStep,
          status: "sent",
          provider_message_id: result.messageId ?? null,
          metadata: { channel: "email", to: maskEmail(to), integration: result.integrationKey },
        });
        break;
      }

      case "sms": {
        const cfg = typedNode.config as Record<string, unknown>;
        const to = String((ctx.patient as Record<string, unknown>)?.phone ?? cfg.to ?? "");
        if (!to) {
          await logStep(db, { ...baseStep, status: "skipped", error: "no_phone" });
          break;
        }
        let smsBody = String(cfg.body ?? "");
        if (cfg.template_id) {
          const tpl = await loadTemplateBody(db, tenantId, String(cfg.template_id));
          if (tpl) smsBody = tpl.body || smsBody;
        }
        const sid = await sendSmsViaTenant(db, tenantId, to, renderTemplate(smsBody, ctx));
        await logStep(db, {
          ...baseStep,
          status: "sent",
          provider_message_id: sid,
          metadata: { channel: "sms", to: maskPhone(to) },
        });
        break;
      }

      case "delay":
      case "wait_until": {
        const runAt = typedNode.node_type === "delay"
          ? computeDelayRunAt(typedNode.config as Record<string, unknown>)
          : new Date(String((typedNode.config as Record<string, unknown>).until ?? Date.now()));
        // Park: schedule resume at the next node after this delay.
        const resumeNodeId = nextNodeId(typedEdges, typedNode.id);
        await db.from("comms_scheduled_jobs").insert({
          tenant_id: tenantId,
          automation_id: enrollment.automation_id,
          enrollment_id,
          node_id: resumeNodeId,
          job_kind: "advance",
          run_at: runAt.toISOString(),
        });
        await db.from("comms_enrollments")
          .update({ current_node_id: resumeNodeId })
          .eq("id", enrollment_id);
        await logStep(db, { ...baseStep, status: "scheduled", metadata: { run_at: runAt.toISOString() } });
        parked = true;
        break;
      }

      case "branch": {
        const cond = (typedNode.config as Record<string, unknown>).condition as BranchCondition;
        const result = cond ? evaluateCondition(cond, ctx) : false;
        branchOutcome = result ? "true" : "false";
        await logStep(db, { ...baseStep, status: "skipped", metadata: { outcome: branchOutcome } });
        break;
      }

      case "multi_split": {
        // config.split = "cohort_key" resolved from context, else first edge.
        const key = (typedNode.config as Record<string, unknown>).cohort_field;
        branchOutcome = key
          ? String((ctx as Record<string, unknown>)[String(key)] ?? "")
          : null;
        await logStep(db, { ...baseStep, status: "skipped", metadata: { outcome: branchOutcome } });
        break;
      }

      case "n8n": {
        const cfg = typedNode.config as Record<string, unknown>;
        const webhookId = cfg.webhook_id ? String(cfg.webhook_id) : null;
        const testMode = cfg.test_mode === true;
        let webhookUrl = cfg.webhook_url ? String(cfg.webhook_url) : null;
        let webhookPath: string | null = null;
        let workflowId: string | null = null;
        let authRef: string | null = null;
        if (webhookId) {
          const { data: wh } = await db
            .from("comms_n8n_webhooks")
            .select("webhook_url, webhook_path, http_method, auth_secret_ref, n8n_workflow_id")
            .eq("id", webhookId)
            .eq("tenant_id", tenantId)
            .maybeSingle();
          if (wh) {
            webhookUrl = wh.webhook_url;
            webhookPath = wh.webhook_path ?? null;
            workflowId = wh.n8n_workflow_id ?? null;
            authRef = wh.auth_secret_ref;
          }
        }
        if (!webhookUrl) {
          await logStep(db, { ...baseStep, status: "failed", error: "no_n8n_webhook" });
          break;
        }

        // Test mode targets n8n's TEST url, which only responds while the editor
        // has "Listen for test event" armed (and only once). It exists so an author
        // can watch the payload land on the n8n canvas while building the flow.
        // Production triggers always use the production url.
        const targetUrl = testMode ? toTestWebhookUrl(webhookUrl, webhookPath) : webhookUrl;

        const res = await triggerWebhook(targetUrl, {
          tenant_id: tenantId,
          automation_id: enrollment.automation_id,
          enrollment_id,
          context: ctx,
        }, {
          signingSecret: Deno.env.get("COMMS_N8N_SIGNING_SECRET"),
          authHeaderValue: resolveApiKey(authRef),
        });
        await logStep(db, {
          ...baseStep,
          status: res.ok ? "sent" : "failed",
          provider_message_id: res.executionId ?? null,
          error: res.ok ? null : (testMode ? `n8n_test_${res.status}` : `n8n_status_${res.status}`),
          // workflow_id lets the UI deep-link straight to the run in n8n.
          metadata: {
            base_url: defaultN8nBaseUrl(),
            workflow_id: workflowId,
            ...(testMode ? { test_mode: true } : {}),
          },
        });
        break;
      }

      case "exit": {
        await logStep(db, { ...baseStep, status: "skipped" });
        await db.from("comms_enrollments")
          .update({ status: "exited", completed_at: new Date().toISOString() })
          .eq("id", enrollment_id);
        return new Response(JSON.stringify({ ok: true, exited: true }), { status: 200 });
      }
    }

    if (parked) {
      return new Response(JSON.stringify({ ok: true, parked: true }), { status: 200 });
    }

    // Advance to next node.
    const next = nextNodeId(typedEdges, typedNode.id, branchOutcome);
    if (!next) {
      await db.from("comms_enrollments")
        .update({ status: "completed", current_node_id: null, completed_at: new Date().toISOString() })
        .eq("id", enrollment_id);
      return new Response(JSON.stringify({ ok: true, completed: true }), { status: 200 });
    }

    await db.from("comms_enrollments").update({ current_node_id: next }).eq("id", enrollment_id);

    // Chain immediately to the next node (single hop per invocation; recurse via self-call
    // is avoided to keep each invocation bounded — caller loop / scheduler picks up parked jobs).
    const internalUrl = `${url}/functions/v1/comms-execute-node`;
    await fetch(internalUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(secret ? { Authorization: `Bearer ${secret}` } : {}),
      },
      body: JSON.stringify({ enrollment_id, node_id: next }),
    }).catch((e) => console.error("chain next node failed:", e));

    return new Response(JSON.stringify({ ok: true, advanced_to: next }), { status: 200 });
  } catch (error) {
    console.error("comms-execute-node error:", error);
    return new Response(
      JSON.stringify({ error: error instanceof Error ? error.message : "error" }),
      { status: 500 },
    );
  }
});
