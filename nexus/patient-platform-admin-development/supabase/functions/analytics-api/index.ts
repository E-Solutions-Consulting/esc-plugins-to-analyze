import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { emitCommsEvent } from "../_shared/comms-emit.ts";
import {
  emitOutboundEvent,
  type OutboundEventKey,
  outboundEventForUsageName,
  outboundEventForUsageType,
} from "../_shared/outbound-emit.ts";
import {
  type AnalyticsSettings,
  filterAndSanitizeBatch,
  getTenantSlug,
  type IncomingEvent,
  resolveEffectiveSettings,
} from "./helpers.ts";

const ALLOW_HEADERS =
  "authorization, x-client-info, apikey, content-type, x-tenant-slug, x-tenant-id, x-request-id, x-api-version";

function json(
  body: unknown,
  status: number,
  corsHeaders: Record<string, string>,
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, "Content-Type": "application/json" },
  });
}

interface ResolvedTenant {
  id: string;
  slug: string;
}

// The supabase-js generic client type is awkward to thread through helpers;
// these internal helpers only need `.from(...)`, so accept a loose client.
// deno-lint-ignore no-explicit-any
type DbClient = any;

async function resolveTenant(
  supabase: DbClient,
  slug: string,
): Promise<ResolvedTenant | null> {
  const { data, error } = await supabase
    .from("tenants")
    .select("id, slug")
    .eq("slug", slug)
    .eq("status", "active")
    .maybeSingle();
  if (error || !data) return null;
  return data as ResolvedTenant;
}

async function loadEffectiveSettings(
  supabase: DbClient,
  tenantId: string,
): Promise<AnalyticsSettings> {
  // Platform default row has tenant_id IS NULL; tenant override has tenant_id = tenantId.
  const { data: defaultRow } = await supabase
    .from("tenant_analytics_settings")
    .select("*")
    .is("tenant_id", null)
    .maybeSingle();
  const { data: overrideRow } = await supabase
    .from("tenant_analytics_settings")
    .select("*")
    .eq("tenant_id", tenantId)
    .maybeSingle();
  return resolveEffectiveSettings(
    defaultRow as Partial<AnalyticsSettings> | null,
    overrideRow as Partial<AnalyticsSettings> | null,
  );
}

Deno.serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: ALLOW_HEADERS,
    methods: "GET, POST, OPTIONS",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const url = new URL(req.url);
    const pathname = url.pathname;
    const slug = getTenantSlug(url, req.headers);

    if (!slug) {
      return json(
        { error: "Missing tenant identifier", message: "Provide x-tenant-slug" },
        400,
        corsHeaders,
      );
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const anonKey = Deno.env.get("SUPABASE_ANON_KEY")!;

    // Service-role client: ingestion + config reads (clients never touch Postgres).
    const supabase = createClient(supabaseUrl, serviceKey, {
      auth: { autoRefreshToken: false, persistSession: false },
    });

    const tenant = await resolveTenant(supabase, slug);
    if (!tenant) {
      return json({ error: "Tenant not found" }, 404, corsHeaders);
    }

    // ---- GET /analytics-api/config — effective tracking flags ----
    if (req.method === "GET" && pathname.endsWith("/config")) {
      const settings = await loadEffectiveSettings(supabase, tenant.id);
      return json(settings, 200, corsHeaders);
    }

    // ---- POST /analytics-api/collect — batch ingest ----
    if (req.method === "POST" && pathname.endsWith("/collect")) {
      const settings = await loadEffectiveSettings(supabase, tenant.id);

      // Master switch off → accept nothing (silently OK so the client can stop sending).
      if (!settings.tracking_enabled) {
        return json({ accepted: 0, rejected: 0, reason: "tracking_disabled" }, 202, corsHeaders);
      }

      // Optional Bearer → resolve patient identity for this batch.
      let authUserId: string | null = null;
      let patientId: string | null = null;
      const authHeader = req.headers.get("Authorization");
      if (authHeader) {
        const userClient = createClient(supabaseUrl, anonKey, {
          global: { headers: { Authorization: authHeader } },
        });
        const { data: { user } } = await userClient.auth.getUser();
        if (user) {
          authUserId = user.id;
          const { data: patient } = await supabase
            .from("patients")
            .select("id")
            .eq("auth_user_id", user.id)
            .eq("tenant_id", tenant.id)
            .maybeSingle();
          patientId = (patient as { id: string } | null)?.id ?? null;
        }
      }
      const isAuthenticated = !!authUserId;

      let payload: {
        anonymous_id?: string;
        session_id?: string;
        device?: Record<string, unknown>;
        session?: Record<string, unknown>;
        events?: IncomingEvent[];
      };
      try {
        payload = await req.json();
      } catch {
        return json({ error: "Invalid JSON body" }, 400, corsHeaders);
      }

      const anonymousId = payload.anonymous_id;
      if (!anonymousId || typeof anonymousId !== "string") {
        return json({ error: "Missing anonymous_id" }, 400, corsHeaders);
      }
      const events = Array.isArray(payload.events) ? payload.events : [];

      const { accepted, rejected } = filterAndSanitizeBatch(events, settings, {
        isAuthenticated,
      });

      // 1) Upsert device (only when device-info tracking is on).
      let deviceId: string | null = null;
      if (settings.track_device_info && payload.device) {
        const deviceRow = {
          tenant_id: tenant.id,
          anonymous_id: anonymousId,
          ...payload.device,
          last_seen_at: new Date().toISOString(),
        };
        const { data: device } = await supabase
          .from("analytics_devices")
          .upsert(deviceRow, { onConflict: "tenant_id,anonymous_id" })
          .select("id")
          .maybeSingle();
        deviceId = (device as { id: string } | null)?.id ?? null;
      }

      // 2) Open/locate the session.
      let sessionId: string | null = (payload.session_id as string) ?? null;
      const utm = settings.track_utm_attribution
        ? ((payload.session?.utm as Record<string, unknown>) ?? {})
        : {};
      if (!sessionId) {
        const sessionRow = {
          tenant_id: tenant.id,
          device_id: deviceId,
          anonymous_id: anonymousId,
          patient_id: patientId,
          auth_user_id: authUserId,
          is_authenticated: isAuthenticated,
          entry_url: payload.session?.entry_url ?? null,
          referrer: payload.session?.referrer ?? null,
          utm,
        };
        const { data: session } = await supabase
          .from("analytics_sessions")
          .insert(sessionRow)
          .select("id")
          .maybeSingle();
        sessionId = (session as { id: string } | null)?.id ?? null;
      } else if (isAuthenticated) {
        // Backfill identity on an existing session once the user authenticates.
        await supabase
          .from("analytics_sessions")
          .update({
            patient_id: patientId,
            auth_user_id: authUserId,
            is_authenticated: true,
            last_activity_at: new Date().toISOString(),
          })
          .eq("id", sessionId)
          .eq("tenant_id", tenant.id);
      }

      // 3) Insert events idempotently (ignore duplicates on client_event_id).
      let insertedCount = 0;
      if (accepted.length > 0) {
        const rows = accepted.map((e) => ({
          tenant_id: tenant.id,
          session_id: sessionId,
          device_id: deviceId,
          anonymous_id: anonymousId,
          patient_id: patientId,
          auth_user_id: authUserId,
          event_type: e.event_type,
          event_name: e.event_name ?? null,
          page_path: e.page_path ?? null,
          page_title: e.page_title ?? null,
          referrer: e.referrer ?? null,
          duration_ms: e.duration_ms ?? null,
          properties: e.properties ?? {},
          client_event_id: e.client_event_id,
          occurred_at: e.occurred_at ?? new Date().toISOString(),
        }));
        const { data: inserted, error: insertError } = await supabase
          .from("analytics_events")
          .upsert(rows, { onConflict: "tenant_id,client_event_id", ignoreDuplicates: true })
          .select("id");
        if (insertError) {
          console.error("analytics_events insert error:", insertError);
        } else {
          insertedCount = (inserted as unknown[] | null)?.length ?? 0;

          // Feed Communications Automations: emit named behavioral events tied to a
          // patient so event-triggered journeys can enrol. Fire-and-forget; the
          // dispatcher itself filters to automations whose trigger matches.
          if (patientId) {
            const seen = new Set<string>();
            for (const e of accepted) {
              const name = e.event_name;
              if (!name || seen.has(name)) continue;
              seen.add(name);
              emitCommsEvent({
                tenant_id: tenant.id,
                kind: "event",
                event_name: name,
                patient_id: patientId,
                // Dedup identity for THIS occurrence. Without it the dispatcher
                // keys enrollments on patient+event_name alone, i.e. a patient
                // could enroll in a `login`-triggered journey exactly once,
                // ever. client_event_id is idempotent across client retries but
                // unique per real occurrence — exactly the dedup we want.
                entity_id: e.client_event_id,
                event: { event_name: name, properties: e.properties ?? {} },
              });
            }
          }

          // Feed Outbound Webhooks (product_usage type): forward one event per
          // distinct usage key to any tenant webhook subscribed to it. Named
          // behavioral events (login, checkout_completed, …) get their
          // first-class key so a subscriber can pick exactly the events they
          // want; everything else falls back to the coarse type event
          // (usage.page_view / usage.activity_event). Fires regardless of
          // patient identification (anonymous usage still fans out).
          // Fire-and-forget; the dispatcher is a no-op when unsubscribed.
          {
            const seenUsage = new Set<OutboundEventKey>();
            for (const e of accepted) {
              const eventKey = outboundEventForUsageName(e.event_name) ??
                outboundEventForUsageType(e.event_type);
              if (seenUsage.has(eventKey)) continue;
              seenUsage.add(eventKey);
              emitOutboundEvent({
                tenantId: tenant.id,
                eventKey,
                payload: {
                  event_type: e.event_type,
                  event_name: e.event_name ?? undefined,
                  patient_id: patientId ?? undefined,
                  session_id: sessionId ?? undefined,
                  page_path: e.page_path ?? undefined,
                  properties: e.properties ?? {},
                },
              });
            }
          }
        }
      }

      // 4) Roll up session counters + activity timestamp.
      if (sessionId) {
        const pageViews = accepted.filter((e) => e.event_type === "page_view").length;
        await supabase.rpc("bump_analytics_session_counters", {
          p_session_id: sessionId,
          p_tenant_id: tenant.id,
          p_event_delta: insertedCount,
          p_page_view_delta: pageViews,
        }).then(undefined, () => {
          // RPC is optional/best-effort; counters can also be recomputed in the warehouse.
        });
      }

      return json(
        {
          accepted: insertedCount,
          rejected: rejected.length,
          session_id: sessionId,
          ...(rejected.length ? { rejections: rejected } : {}),
        },
        202,
        corsHeaders,
      );
    }

    return json({ error: "Not found" }, 404, corsHeaders);
  } catch (error) {
    console.error("analytics-api error:", error);
    return json({ error: "Internal server error" }, 500, corsHeaders);
  }
});
