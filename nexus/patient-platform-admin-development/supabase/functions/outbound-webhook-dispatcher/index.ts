/**
 * outbound-webhook-dispatcher
 *
 * Publishes a platform event to RTDH for fan-out to a tenant's configured
 * outbound webhooks (delivery Option B: PP -> RTDH -> Pub/Sub -> external).
 *
 * Request (service-to-service; called from order-lifecycle / analytics / etc.):
 *   POST { tenantId, eventKey, payload }
 *
 * Flow:
 *   1. Derive the event TYPE from its key (lifecycle vs product_usage).
 *   2. Select enabled webhooks of the MATCHING type that subscribe to eventKey
 *      (types are never mixed).
 *   3. Build ONE publish envelope { event, type, tenantId, occurredAt, data,
 *      subscriptions[] } and POST it to RTDH (signed with
 *      rtdh_config.patient_platform_webhook_secret, header
 *      x-patientplatform-signature) — same emit pattern as order-lifecycle.
 *      RTDH publishes to a Pub/Sub topic and a subscriber fans out to each
 *      subscription's target_url (with its own signing secret).
 *   4. Record one delivery row per matched webhook capturing the PUBLISH result
 *      (HTTP delivery to the external endpoint is recorded RTDH-side).
 *
 * This repo owns: selection + the signed publish + the contract. RTDH owns the
 * Pub/Sub topic, fan-out, per-endpoint signing and retries — see
 * docs/OutboundWebhooksAPI.md and the rt-data-hub-functions repo.
 */
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { postSignedRtdhJson } from "../_shared/rtdh-signature.ts";
import { resolveRtdhConfig } from "../_shared/rtdh-config.ts";
import { buildPublishEnvelope, webhookTypeForEvent } from "./helpers.ts";
import { enrichPayload, supabaseLookups, type Row } from "./enrich.ts";

function jsonResponse(
  body: Record<string, unknown>,
  status: number,
  headers: Record<string, string>,
) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...headers, "Content-Type": "application/json" },
  });
}

const RTDH_PUBLISH_TIMEOUT_MS = 8000;

// The OUTBOUND event receiver is its OWN Cloud Function, deployed per-env as
// `patient-platform-outbound-event-receiver-<env>` (see rt-data-hub-functions
// functions/patientPlatformOutboundEventReceiver/function-<env>.yaml). It is a
// distinct function from the inbound `patient-platform-webhook-receiver`; the
// dispatcher must target it by exact name, and it accepts the POST at its root
// (no sub-path routing).
const OUTBOUND_RECEIVER_BASE_NAME = "patient-platform-outbound-event-receiver";

/**
 * Derive the deploy env (dev|staging|prod) from the RTDH base URL, which is a
 * GCP Cloud Functions host like
 * `https://us-central1-allia-rt-data-hub-<env>.cloudfunctions.net`. Falls back
 * to the RTDH_ENV env var, then "prod". This keeps the receiver function name
 * in sync with the environment without extra config.
 */
function deriveRtdhEnv(baseUrl: string): string {
  const m = baseUrl.match(/allia-rt-data-hub-(dev|staging|prod)/i);
  if (m) return m[1].toLowerCase();
  const envVar = (Deno.env.get("RTDH_ENV") || "").toLowerCase();
  if (envVar === "dev" || envVar === "staging" || envVar === "prod") return envVar;
  return "prod";
}

/** Build `<base>/patient-platform-outbound-event-receiver-<env>`. */
function buildRtdhReceiverUrl(baseUrl: string): string {
  const base = baseUrl.replace(/\/+$/, "");
  const env = deriveRtdhEnv(base);
  const fnName = `${OUTBOUND_RECEIVER_BASE_NAME}-${env}`;
  return new URL(`${base}/${fnName}`).toString();
}

Deno.serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
    methods: "POST, OPTIONS",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }
  if (req.method !== "POST") {
    return jsonResponse({ error: "Method not allowed" }, 405, corsHeaders);
  }

  try {
    const { tenantId, eventKey, payload, requestId } = await req.json();
    if (!tenantId || !eventKey) {
      return jsonResponse(
        { error: "tenantId and eventKey are required" },
        400,
        corsHeaders,
      );
    }

    const type = webhookTypeForEvent(eventKey);
    if (!type) {
      return jsonResponse(
        { error: `Unknown eventKey: ${eventKey}` },
        400,
        corsHeaders,
      );
    }

    const supabase = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    );

    // Only webhooks of the matching type that subscribe to this event key.
    const { data: webhooks, error } = await supabase
      .from("tenant_outbound_webhooks")
      .select("id, target_url, signing_secret, event_keys")
      .eq("tenant_id", tenantId)
      .eq("webhook_type", type)
      .eq("is_enabled", true)
      .contains("event_keys", [eventKey]);

    if (error) throw error;

    const matched = webhooks ?? [];
    if (matched.length === 0) {
      return jsonResponse({ published: false, matched: 0 }, 200, corsHeaders);
    }

    const { data: tenantRow, error: tenantError } = await supabase
      .from("tenants")
      .select("slug")
      .eq("id", tenantId)
      .maybeSingle();
    if (tenantError || !tenantRow?.slug) {
      return jsonResponse(
        { error: "Unable to resolve tenant slug" },
        400,
        corsHeaders,
      );
    }

    // Load RTDH connection config, like order-lifecycle.
    const { data: settingRow } = await supabase
      .from("platform_settings")
      .select("value")
      .eq("key", "rtdh_config")
      .maybeSingle();
    const rtdhConfig = await resolveRtdhConfig(settingRow?.value);
    const rtdhApi = rtdhConfig.base_url || rtdhConfig.api_url;
    const rtdhConsumerSecret = rtdhConfig.patient_platform_webhook_secret ||
      rtdhConfig.consumer_secret;

    if (!rtdhApi || !rtdhConsumerSecret) {
      return jsonResponse(
        { error: "RTDH is not configured (base_url / webhook secret missing)" },
        503,
        corsHeaders,
      );
    }

    // Enrich the raw id-only payload with human-readable derived fields
    // (patient name/email/phone, product/provider/pharmacy names, status label).
    // Best-effort: a lookup miss just omits that field, never fails the dispatch.
    const rawPayload: Row =
      payload && typeof payload === "object" && !Array.isArray(payload)
        ? (payload as Row)
        : { payload };
    let enrichedData: Row;
    try {
      enrichedData = await enrichPayload(
        rawPayload,
        supabaseLookups(supabase, tenantId),
      );
    } catch (e) {
      console.error("payload enrichment failed (non-fatal):", e);
      enrichedData = rawPayload;
    }

    const occurredAt = new Date().toISOString();
    const envelope = buildPublishEnvelope({
      eventKey,
      type,
      tenantId,
      occurredAt,
      data: {
        ...enrichedData,
        tenant: tenantRow.slug,
        internal_tenant_id: tenantId,
      },
      subscriptions: matched.map((wh) => ({
        webhookId: wh.id,
        targetUrl: wh.target_url,
        signingSecret: wh.signing_secret,
      })),
    });

    const url = buildRtdhReceiverUrl(rtdhApi);
    let publishStatus: number | null = null;
    let publishOk = false;
    let errMsg: string | null = null;
    try {
      const resp = await postSignedRtdhJson({
        url,
        requestId: typeof requestId === "string" && requestId
          ? requestId
          : crypto.randomUUID(),
        requestSource: "outbound-webhook-dispatcher",
        webhookSecret: rtdhConsumerSecret,
        payload: envelope,
        timeoutMs: RTDH_PUBLISH_TIMEOUT_MS,
      });
      publishStatus = resp.status;
      publishOk = resp.ok;
    } catch (e) {
      errMsg = e instanceof Error ? e.message : String(e);
    }

    // One delivery row per matched webhook capturing the publish-to-RTDH result.
    await supabase.from("tenant_outbound_webhook_deliveries").insert(
      matched.map((wh) => ({
        webhook_id: wh.id,
        tenant_id: tenantId,
        event_key: eventKey,
        status_code: publishStatus,
        success: publishOk,
        attempts: 1,
        error: errMsg,
      })),
    );

    return jsonResponse(
      { published: publishOk, matched: matched.length, publishStatus },
      200,
      corsHeaders,
    );
  } catch (e) {
    return jsonResponse(
      { error: e instanceof Error ? e.message : "Dispatch failed" },
      500,
      corsHeaders,
    );
  }
});
