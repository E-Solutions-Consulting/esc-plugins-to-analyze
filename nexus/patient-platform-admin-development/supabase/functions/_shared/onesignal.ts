/* eslint-disable @typescript-eslint/no-explicit-any */
// OneSignal REST API integration for Supabase Edge Functions.
// Handles scheduling and cancelling push notifications for patient reminders.
// REST API key is server-side only — never returned to clients.
// deno-lint-ignore-file no-explicit-any

export interface OneSignalConfig {
  app_id: string;
  rest_api_key: string;
}

const ONESIGNAL_API = "https://api.onesignal.com";

export interface OneSignalScheduleResult {
  notification_id: string | null;
  accepted: boolean;
  status: number | null;
  response: unknown;
  error: string | null;
}

/**
 * Schedule a future push notification via OneSignal.
 *
 * @param externalUserId The Supabase auth user UUID — used as OneSignal external_id
 * @param title          Notification heading
 * @param body           Notification body text
 * @param sendAfterUtc   UTC Date for scheduled delivery. Pass null for immediate delivery.
 * @param config         Tenant-specific OneSignal app_id + rest_api_key
 * @param idempotencyKey Unique key for this occurrence; prevents duplicates on re-runs.
 *                       Recommended format: `${reminderId}:${YYYY-MM-DD}`
 * @param deepLinkPath   Optional in-app path to navigate to on notification tap (default: /reminders)
 * @returns OneSignal notification ID, or null if scheduling failed
 */
export async function scheduleNotification(
  externalUserId: string,
  title: string,
  body: string,
  sendAfterUtc: Date | null,
  config: OneSignalConfig,
  idempotencyKey: string,
  deepLinkPath = "/reminders",
): Promise<string | null> {
  const result = await scheduleNotificationWithResult(
    externalUserId,
    title,
    body,
    sendAfterUtc,
    config,
    idempotencyKey,
    deepLinkPath,
  );

  return result.notification_id;
}

export async function scheduleNotificationWithResult(
  externalUserId: string,
  title: string,
  body: string,
  sendAfterUtc: Date | null,
  config: OneSignalConfig,
  idempotencyKey: string,
  deepLinkPath = "/reminders",
): Promise<OneSignalScheduleResult> {
  let response: Response;

  const payload: Record<string, unknown> = {
    app_id: config.app_id,
    include_external_user_ids: [externalUserId],
    headings: { en: title },
    contents: { en: body },
    data: { url: deepLinkPath },
    idempotency_key: idempotencyKey,
  };

  if (sendAfterUtc !== null) {
    payload.send_after = sendAfterUtc.toISOString();
  }

  try {
    response = await fetch(`${ONESIGNAL_API}/notifications?c=push`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Basic ${config.rest_api_key}`,
      },
      body: JSON.stringify(payload),
    });
  } catch (err) {
    console.error("OneSignal scheduleNotification network error", {
      externalUserId,
      idempotencyKey,
      error: err instanceof Error ? err.message : String(err),
    });
    return {
      notification_id: null,
      accepted: false,
      status: null,
      response: null,
      error: err instanceof Error ? err.message : String(err),
    };
  }

  const responseText = await response.text().catch(() => "");
  let result: unknown = {};
  if (responseText) {
    try {
      result = JSON.parse(responseText);
    } catch {
      result = { raw: responseText };
    }
  }

  if (!response.ok) {
    console.error("OneSignal scheduleNotification failed", {
      status: response.status,
      body: result,
      externalUserId,
      idempotencyKey,
    });
    return {
      notification_id: null,
      accepted: false,
      status: response.status,
      response: result,
      error: `OneSignal request failed with status ${response.status}`,
    };
  }

  const resultRecord = result && typeof result === "object"
    ? result as Record<string, unknown>
    : {};
  const rawNotificationId = resultRecord.id;
  const notificationId = typeof rawNotificationId === "string" &&
      rawNotificationId.trim()
    ? rawNotificationId
    : null;

  if (!notificationId) {
    // 2xx but no id → no matching subscriptions for this user (device not yet registered)
    console.warn(
      "OneSignal scheduleNotification: no notification id returned",
      {
        externalUserId,
        idempotencyKey,
        result,
      },
    );
  }

  return {
    notification_id: notificationId,
    accepted: notificationId !== null,
    status: response.status,
    response: result,
    error: notificationId
      ? null
      : "OneSignal accepted the request but did not create a notification. The patient likely has no subscribed device for this external_id.",
  };
}

/**
 * Cancel a scheduled (not yet delivered) OneSignal notification.
 * Non-fatal — logs a warning if cancellation fails (notification may already be delivered).
 */
export async function cancelNotification(
  notificationId: string,
  config: OneSignalConfig,
): Promise<void> {
  let response: Response;

  try {
    response = await fetch(
      `${ONESIGNAL_API}/notifications/${notificationId}?app_id=${config.app_id}`,
      {
        method: "DELETE",
        headers: {
          Authorization: `Basic ${config.rest_api_key}`,
        },
      },
    );
  } catch (err) {
    console.warn("OneSignal cancelNotification network error", {
      notificationId,
      error: err instanceof Error ? err.message : String(err),
    });
    return;
  }

  if (!response.ok) {
    const text = await response.text().catch(() => "");
    console.warn("OneSignal cancelNotification failed (non-fatal)", {
      status: response.status,
      body: text,
      notificationId,
    });
  }
}

/**
 * Fetch the OneSignal config for a tenant from the tenant_integrations table.
 * Returns null if the integration is not configured or disabled.
 */
export async function getOneSignalConfig(
  supabaseAdmin: any,
  tenantId: string,
): Promise<OneSignalConfig | null> {
  const { data, error } = await supabaseAdmin
    .from("tenant_integrations")
    .select("settings")
    .eq("tenant_id", tenantId)
    .eq("integration_key", "onesignal")
    .eq("is_enabled", true)
    .maybeSingle();

  if (error) {
    console.warn("Failed to fetch OneSignal tenant integration", {
      tenantId,
      error: error.message,
    });
    return null;
  }

  if (!data?.settings) return null;

  const settings = data.settings as Record<string, unknown>;
  const app_id = typeof settings.app_id === "string"
    ? settings.app_id.trim()
    : "";
  const rest_api_key = typeof settings.rest_api_key === "string"
    ? settings.rest_api_key.trim()
    : "";

  if (!app_id || !rest_api_key) return null;

  return { app_id, rest_api_key };
}
