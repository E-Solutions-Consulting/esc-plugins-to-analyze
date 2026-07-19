// deno-lint-ignore no-import-prefix
import { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  getOneSignalConfig,
  scheduleNotificationWithResult,
} from "../_shared/onesignal.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";
import { asNonEmptyString, asObject, type JsonObject } from "./validation.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

type ChatNotificationDeps = {
  getOneSignalConfigFn?: typeof getOneSignalConfig;
  scheduleNotificationWithResultFn?: typeof scheduleNotificationWithResult;
};

const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const UUID_URL_NAMESPACE = "6ba7b811-9dad-11d1-80b4-00c04fd430c8";

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-request-id, x-rtdh-webhook-secret, x-webhook-secret, x-webhook-signature, x-qa-bypass, x-rtdh-intent",
    methods: "POST, OPTIONS",
  });
}

function jsonResponse(
  req: Request,
  body: Record<string, unknown>,
  status = 200,
  headers: Record<string, string> = {},
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      ...getCorsHeaders(req),
      "Content-Type": "application/json",
      ...headers,
    },
  });
}

function errorResponse(
  req: Request,
  code: string,
  message: string,
  status: number,
  requestId: string,
  details?: unknown,
): Response {
  return jsonResponse(
    req,
    {
      error: {
        code,
        message,
        details: details ?? null,
      },
      requestId,
    },
    status,
    { "x-request-id": requestId },
  );
}

function nullableString(value: unknown): string | null {
  if (value === null || value === undefined) return null;
  return asNonEmptyString(value);
}

function uuidToBytes(uuid: string): Uint8Array {
  const hex = uuid.replaceAll("-", "");
  const bytes = new Uint8Array(16);
  for (let i = 0; i < bytes.length; i += 1) {
    bytes[i] = Number.parseInt(hex.slice(i * 2, i * 2 + 2), 16);
  }
  return bytes;
}

function bytesToUuid(bytes: Uint8Array): string {
  const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, "0"));
  return [
    hex.slice(0, 4).join(""),
    hex.slice(4, 6).join(""),
    hex.slice(6, 8).join(""),
    hex.slice(8, 10).join(""),
    hex.slice(10, 16).join(""),
  ].join("-");
}

async function uuidV5(name: string, namespace: string): Promise<string> {
  const namespaceBytes = uuidToBytes(namespace);
  const nameBytes = new TextEncoder().encode(name);
  const combined = new Uint8Array(namespaceBytes.length + nameBytes.length);
  combined.set(namespaceBytes);
  combined.set(nameBytes, namespaceBytes.length);

  const digest = await crypto.subtle.digest("SHA-1", combined);
  const uuidBytes = new Uint8Array(digest).slice(0, 16);
  uuidBytes[6] = (uuidBytes[6] & 0x0f) | 0x50;
  uuidBytes[8] = (uuidBytes[8] & 0x3f) | 0x80;
  return bytesToUuid(uuidBytes);
}

export async function buildChatOneSignalIdempotencyKey(
  providerName: string,
  providerMessageId: string,
): Promise<string> {
  if (UUID_PATTERN.test(providerMessageId)) {
    return providerMessageId.toLowerCase();
  }

  return await uuidV5(
    `provider_chat:${providerName}:${providerMessageId}`,
    UUID_URL_NAMESPACE,
  );
}

function isUniqueViolation(error: unknown): boolean {
  return Boolean(
    error &&
      typeof error === "object" &&
      "code" in error &&
      (error as { code?: unknown }).code === "23505",
  );
}

export function isChatMessageReceivedPayload(payload: JsonObject): boolean {
  return payload.event_type === "chat.message.received";
}

async function resolveExistingNotification(
  supabase: SupabaseAdminClient,
  tenantId: string,
  providerName: string,
  providerMessageId: string,
) {
  return await supabase
    .from("patient_notifications")
    .select("id, read_at")
    .eq("tenant_id", tenantId)
    .eq("provider_name", providerName)
    .eq("provider_message_id", providerMessageId)
    .maybeSingle();
}

export async function handleChatMessageReceivedEvent(
  req: Request,
  supabase: SupabaseAdminClient,
  payload: JsonObject,
  requestId: string,
  deps: ChatNotificationDeps = {},
): Promise<Response> {
  const ids = asObject(payload.ids);
  const notification = asObject(payload.notification);
  const resource = asObject(notification?.resource);
  const message = asObject(payload.message);

  const providerName = asNonEmptyString(payload.provider_name);
  const tenantId = asNonEmptyString(ids?.tenant_id);
  const patientId = asNonEmptyString(ids?.patient_id);
  const providerMessageId = asNonEmptyString(ids?.provider_message_id);
  const providerPatientId = nullableString(ids?.provider_patient_id);
  const providerOrderId = nullableString(ids?.provider_order_id);
  const orderId = nullableString(ids?.patient_platform_order_id) ||
    nullableString(resource?.order_id);
  const title = asNonEmptyString(notification?.title);
  const body = asNonEmptyString(notification?.body);

  const validationErrors: string[] = [];
  if (!providerName) validationErrors.push("provider_name is required");
  if (!tenantId) validationErrors.push("ids.tenant_id is required");
  if (!patientId) validationErrors.push("ids.patient_id is required");
  if (!providerMessageId) {
    validationErrors.push("ids.provider_message_id is required");
  }
  if (!title) validationErrors.push("notification.title is required");
  if (!body) validationErrors.push("notification.body is required");
  if (resource?.type !== "chat") {
    validationErrors.push("notification.resource.type must be chat");
  }

  if (validationErrors.length > 0) {
    return errorResponse(
      req,
      "validation_error",
      "Invalid chat.message.received payload",
      422,
      requestId,
      validationErrors,
    );
  }

  const { data: patient, error: patientError } = await supabase
    .from("patients")
    .select("id, tenant_id, auth_user_id")
    .eq("id", patientId!)
    .maybeSingle();

  if (patientError) {
    console.error("rtdh-webhook: chat notification patient fetch failed", {
      requestId,
      patientId,
      error: patientError,
    });
    return errorResponse(
      req,
      "reference_error",
      "Failed to resolve chat notification patient",
      500,
      requestId,
    );
  }

  if (!patient || patient.tenant_id !== tenantId) {
    return errorResponse(
      req,
      "reference_not_found",
      "Chat notification patient does not belong to tenant",
      422,
      requestId,
      [`patient '${patientId}' does not belong to tenant '${tenantId}'`],
    );
  }

  const insertPayload = {
    tenant_id: tenantId!,
    patient_id: patientId!,
    type: "chat_message",
    event_type: "chat.message.received",
    provider_name: providerName!,
    provider_message_id: providerMessageId!,
    provider_patient_id: providerPatientId,
    provider_order_id: providerOrderId,
    order_id: orderId,
    title: title!,
    body: body!,
    resource: {
      type: "chat",
      provider_name: providerName,
      provider_patient_id: providerPatientId,
      order_id: orderId,
    },
    raw_payload: payload,
  };

  const { data: insertedNotification, error: insertError } = await supabase
    .from("patient_notifications")
    .insert(insertPayload)
    .select("id")
    .single();

  if (insertError) {
    if (isUniqueViolation(insertError)) {
      const { data: existingNotification } = await resolveExistingNotification(
        supabase,
        tenantId!,
        providerName!,
        providerMessageId!,
      );
      return jsonResponse(
        req,
        {
          received: true,
          requestId,
          eventType: "chat.message.received",
          status: "duplicate",
          notificationId: existingNotification?.id ?? null,
        },
        200,
        { "x-request-id": requestId },
      );
    }

    console.error("rtdh-webhook: chat notification insert failed", {
      requestId,
      providerName,
      providerMessageId,
      error: insertError,
    });
    return errorResponse(
      req,
      "persistence_error",
      "Failed to persist chat notification",
      500,
      requestId,
    );
  }

  const notificationId = insertedNotification?.id as string | undefined;
  const getOneSignalConfigFn = deps.getOneSignalConfigFn ?? getOneSignalConfig;
  const scheduleNotificationWithResultFn =
    deps.scheduleNotificationWithResultFn ?? scheduleNotificationWithResult;

  let pushResult:
    | Awaited<ReturnType<typeof scheduleNotificationWithResult>>
    | null = null;

  if (patient.auth_user_id && notificationId) {
    const osConfig = await getOneSignalConfigFn(supabase, tenantId!);
    if (osConfig) {
      const oneSignalIdempotencyKey = await buildChatOneSignalIdempotencyKey(
        providerName!,
        providerMessageId!,
      );
      pushResult = await scheduleNotificationWithResultFn(
        patient.auth_user_id,
        title!,
        body!,
        null,
        osConfig,
        oneSignalIdempotencyKey,
        "/provider-chat",
      );

      await supabase
        .from("patient_notifications")
        .update({
          onesignal_notification_id: pushResult.notification_id,
          onesignal_status: pushResult.status,
          onesignal_response: pushResult.response,
          onesignal_error: pushResult.error,
        })
        .eq("id", notificationId);

      if (!pushResult.accepted) {
        console.warn(
          "rtdh-webhook: chat notification push failed after insert",
          {
            requestId,
            notificationId,
            patientId,
            authUserId: patient.auth_user_id,
            providerName,
            providerMessageId,
            onesignalStatus: pushResult.status,
            onesignalError: pushResult.error,
            onesignalResponse: pushResult.response,
          },
        );
      }
    }
  }

  return jsonResponse(
    req,
    {
      received: true,
      requestId,
      eventType: "chat.message.received",
      status: "created",
      notificationId: notificationId ?? null,
      push: pushResult
        ? {
          accepted: pushResult.accepted,
          notification_id: pushResult.notification_id,
          status: pushResult.status,
          response: pushResult.response,
          error: pushResult.error,
        }
        : null,
      message_sender_type: nullableString(message?.sender_type),
    },
    200,
    { "x-request-id": requestId },
  );
}
