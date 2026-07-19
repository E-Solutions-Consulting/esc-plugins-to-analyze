// reminder-scheduler: Daily safety-net Edge Function
//
// Purpose: Top up pre-scheduled OneSignal notifications for reminders whose
// 30-day window is running low (< 14 days of scheduled jobs remaining).
// Also marks stale 'scheduled' rows as 'delivered' for hygiene.
//
// Triggered: daily at 01:00 UTC via pg_cron (see supabase/config.toml)
// Uses service-role key to bypass RLS.

import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  getOneSignalConfig,
  scheduleNotification,
} from "../_shared/onesignal.ts";
import { calculateOccurrences } from "../_shared/reminder-schedule.ts";

const SCHEDULE_WINDOW_DAYS = 30;
const TOPUP_THRESHOLD_DAYS = 14; // Top up when window has fewer than 14 days remaining

Deno.serve(async (req) => {
  const requestId = req.headers.get("x-request-id") || crypto.randomUUID();

  // Validate the request is coming from an authorized source
  // (Supabase cron calls don't carry an auth header, so we use a shared secret)
  const cronSecret = Deno.env.get("CRON_SECRET");
  const authHeader = req.headers.get("authorization");
  if (cronSecret && authHeader !== `Bearer ${cronSecret}`) {
    console.warn("reminder-scheduler: unauthorized call rejected", {
      requestId,
    });
    return new Response(JSON.stringify({ error: "Unauthorized" }), {
      status: 401,
      headers: { "Content-Type": "application/json" },
    });
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseServiceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;

  const supabase = createClient(supabaseUrl, supabaseServiceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  const now = new Date();
  const topupCutoff = new Date(now);
  topupCutoff.setDate(topupCutoff.getDate() + TOPUP_THRESHOLD_DAYS);

  console.info("reminder-scheduler: starting run", {
    requestId,
    now: now.toISOString(),
    topupCutoff: topupCutoff.toISOString(),
  });

  let totalScheduled = 0;
  let totalCleaned = 0;
  let totalReminders = 0;

  try {
    // ── Step 1: Find all enabled, non-deleted reminders ─────────────────
    const { data: reminders, error: remindersError } = await supabase
      .from("patient_reminders")
      .select(
        "id, patient_id, tenant_id, category, title, frequency, repeat_days, time_local, timezone, medication_id",
      )
      .eq("is_enabled", true)
      .is("deleted_at", null);

    if (remindersError) {
      throw new Error(`Failed to fetch reminders: ${remindersError.message}`);
    }

    totalReminders = reminders?.length ?? 0;
    console.info("reminder-scheduler: reminders fetched", {
      requestId,
      totalReminders,
    });

    // Group reminders by tenant_id to minimize OneSignal config lookups
    const tenantConfigCache = new Map<
      string,
      Awaited<ReturnType<typeof getOneSignalConfig>>
    >();
    const patientAuthUserIdCache = new Map<string, string | null>();

    async function getConfig(tenantId: string) {
      if (!tenantConfigCache.has(tenantId)) {
        tenantConfigCache.set(
          tenantId,
          await getOneSignalConfig(supabase, tenantId),
        );
      }
      return tenantConfigCache.get(tenantId)!;
    }

    async function getPatientAuthUserId(patientId: string) {
      if (!patientAuthUserIdCache.has(patientId)) {
        const { data: patient, error: patientError } = await supabase
          .from("patients")
          .select("auth_user_id")
          .eq("id", patientId)
          .maybeSingle();

        if (patientError) {
          console.error(
            "reminder-scheduler: failed to fetch patient auth user",
            {
              requestId,
              patientId,
              error: patientError.message,
            },
          );
        }

        patientAuthUserIdCache.set(patientId, patient?.auth_user_id ?? null);
      }

      return patientAuthUserIdCache.get(patientId) ?? null;
    }

    for (const reminder of reminders ?? []) {
      try {
        // ── Step 2: Check how far ahead this reminder is already scheduled ──
        const { data: latestNotification } = await supabase
          .from("patient_reminder_notifications")
          .select("scheduled_for")
          .eq("reminder_id", reminder.id)
          .eq("status", "scheduled")
          .gte("scheduled_for", now.toISOString())
          .order("scheduled_for", { ascending: false })
          .limit(1)
          .maybeSingle();

        const latestScheduled = latestNotification?.scheduled_for
          ? new Date(latestNotification.scheduled_for)
          : null;

        // Skip if already scheduled past the top-up threshold
        if (latestScheduled && latestScheduled > topupCutoff) continue;

        // ── Step 3: Schedule occurrences from the latest scheduled date ──
        const osConfig = await getConfig(reminder.tenant_id);
        if (!osConfig) continue;

        const externalUserId = await getPatientAuthUserId(reminder.patient_id);
        if (!externalUserId) {
          console.warn(
            "reminder-scheduler: reminder patient has no auth user",
            {
              requestId,
              reminderId: reminder.id,
              patientId: reminder.patient_id,
            },
          );
          continue;
        }

        const scheduleFrom = latestScheduled ?? now;
        const daysToSchedule = SCHEDULE_WINDOW_DAYS -
          Math.floor(
            (scheduleFrom.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
          );

        if (daysToSchedule <= 0) continue;

        const occurrences = calculateOccurrences(
          {
            frequency: reminder.frequency as "daily" | "weekly",
            repeat_days: reminder.repeat_days,
            time_local: reminder.time_local,
            timezone: reminder.timezone,
          },
          scheduleFrom,
          daysToSchedule,
        );

        for (const fireAt of occurrences) {
          const dateKey = fireAt.toISOString().slice(0, 10);
          const idempotencyKey = `${reminder.id}:${dateKey}`;

          const notificationId = await scheduleNotification(
            externalUserId,
            reminder.title,
            `Time for your ${reminder.title} reminder`,
            fireAt,
            osConfig,
            idempotencyKey,
          );

          if (notificationId) {
            await supabase.from("patient_reminder_notifications").insert({
              reminder_id: reminder.id,
              onesignal_notification_id: notificationId,
              scheduled_for: fireAt.toISOString(),
              status: "scheduled",
            });
            totalScheduled++;
          }
        }
      } catch (err) {
        console.error("reminder-scheduler: error processing reminder", {
          requestId,
          reminderId: reminder.id,
          error: err instanceof Error ? err.message : String(err),
        });
      }
    }

    // ── Step 4: Clean up stale 'scheduled' rows (delivery best-effort) ──
    // Rows whose scheduled_for is > 1 hour ago are assumed delivered.
    const staleThreshold = new Date(now);
    staleThreshold.setHours(staleThreshold.getHours() - 1);

    const { error: cleanupError, count } = await supabase
      .from("patient_reminder_notifications")
      .update({ status: "delivered" })
      .eq("status", "scheduled")
      .lt("scheduled_for", staleThreshold.toISOString());

    if (cleanupError) {
      console.warn("reminder-scheduler: cleanup failed (non-fatal)", {
        requestId,
        error: cleanupError.message,
      });
    } else {
      totalCleaned = count ?? 0;
    }

    console.info("reminder-scheduler: run complete", {
      requestId,
      totalReminders,
      totalScheduled,
      totalCleaned,
    });

    return new Response(
      JSON.stringify({
        ok: true,
        reminders_processed: totalReminders,
        notifications_scheduled: totalScheduled,
        notifications_cleaned: totalCleaned,
      }),
      { status: 200, headers: { "Content-Type": "application/json" } },
    );
  } catch (err) {
    console.error("reminder-scheduler: fatal error", {
      requestId,
      error: err instanceof Error ? err.message : String(err),
    });

    return new Response(
      JSON.stringify({
        ok: false,
        error: err instanceof Error ? err.message : "Unknown error",
      }),
      { status: 500, headers: { "Content-Type": "application/json" } },
    );
  }
});
