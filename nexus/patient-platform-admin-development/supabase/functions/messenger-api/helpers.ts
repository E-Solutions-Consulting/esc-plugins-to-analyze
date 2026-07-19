type JsonRecord = Record<string, unknown>;

function getValueAtPath(source: unknown, path: string): unknown {
  return path.split(".").reduce<unknown>((current, segment) => {
    if (!current || typeof current !== "object") return undefined;
    return (current as JsonRecord)[segment];
  }, source);
}

export function normalizeTelegraMessageType(value: unknown): string | null {
  if (typeof value !== "string") return null;

  const normalized = value
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, "");

  return normalized.length > 0 ? normalized : null;
}

export function isTelegraSystemMessage(message: unknown): boolean {
  if (!message || typeof message !== "object" || Array.isArray(message)) {
    return false;
  }

  const record = message as JsonRecord;
  return normalizeTelegraMessageType(record.type) === "ADMM";
}

export function filterTelegraSystemMessages(messages: unknown): unknown {
  if (!Array.isArray(messages)) return messages;

  return messages.filter((message) => !isTelegraSystemMessage(message));
}

export function filterTelegraSystemMessagesInChats(
  chats: unknown[],
): unknown[] {
  return chats.map((chat) => {
    if (!chat || typeof chat !== "object" || Array.isArray(chat)) {
      return chat;
    }

    const record = chat as JsonRecord;
    if (!("messages" in record)) return chat;
    const filteredMessages = filterTelegraSystemMessages(record.messages);
    const normalizedLastMessage =
      Array.isArray(filteredMessages) && filteredMessages.length > 0
        ? filteredMessages[filteredMessages.length - 1]
        : record.last_message;

    return {
      ...record,
      messages: filteredMessages,
      last_message: normalizedLastMessage,
    };
  });
}

export type MdiSenderRole =
  | "patient"
  | "clinician"
  | "support_staff"
  | "unknown";

export interface NormalizedMdiMessage {
  id: string;
  patient_id: string | null;
  channel: string | null;
  text: string | null;
  sender_role: MdiSenderRole;
  sender_type: string | null;
  sender_id: string | null;
  sender_name: string | null;
  direction: "inbound" | "outbound" | "unknown";
  read_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  files: unknown[];
  is_unread: boolean;
}

export interface MdiMessageSummary {
  total_messages: number;
  unread_count: number;
  latest_message_id: string | null;
  latest_message_at: string | null;
}

export interface TelegraChatFilePayload {
  name: string;
  ext?: string;
  base64Data: string;
  size_bytes: number;
}

export type ParsedTelegraChatFile =
  | { file: TelegraChatFilePayload | null; error: null }
  | {
    file: null;
    error: {
      code: string;
      message: string;
    };
  };

export interface ProviderChatContextTokenPayload {
  version: 1;
  plan_id: string;
  order_id: string;
  tenant_integration_id: string;
}

export const TELEGRA_CHAT_FILE_MAX_DECODED_BYTES = 25 * 1024 * 1024;

export const MDI_PARTNER_FILE_TYPES = [
  "document",
  "review",
  "other",
  "insurance-policy",
  "contract",
  "driver-license",
  "lab-result",
  "photo",
  "av-video",
  "full-body-photo",
  "back-photo",
  "face-photo",
  "avatar-photo",
  "ipledge-document",
  "auth-form",
] as const;

export type MdiPartnerFileType = typeof MDI_PARTNER_FILE_TYPES[number];

export function isMdiPartnerFileType(
  value: unknown,
): value is MdiPartnerFileType {
  return typeof value === "string" &&
    MDI_PARTNER_FILE_TYPES.includes(value.trim() as MdiPartnerFileType);
}

export function getMdiPartnerFileSizeLimitBytes(
  mimeType: string,
  fileType?: string,
): number {
  const normalizedFileType = fileType?.trim().toLowerCase() || "";
  if (
    [
      "photo",
      "full-body-photo",
      "back-photo",
      "face-photo",
      "avatar-photo",
    ].includes(normalizedFileType)
  ) {
    return 25 * 1024 * 1024;
  }
  if (normalizedFileType === "av-video") return 140 * 1024 * 1024;

  const normalizedMimeType = mimeType.trim().toLowerCase();
  if (normalizedMimeType.startsWith("image/")) return 25 * 1024 * 1024;
  if (normalizedMimeType.startsWith("video/")) return 140 * 1024 * 1024;
  return 16 * 1024 * 1024;
}

export function extractMdiUploadedFileId(responseBody: unknown): string | null {
  const responseRecord = parseRecord(responseBody);
  if (!responseRecord) return null;

  const directId = parseNonEmptyString(responseRecord.id) ||
    parseNonEmptyString(responseRecord.file_id);
  if (directId) return directId;

  const dataRecord = parseRecord(responseRecord.data);
  if (!dataRecord) return null;

  return parseNonEmptyString(dataRecord.id) ||
    parseNonEmptyString(dataRecord.file_id);
}

export function buildMdiPatientMessagesEndpointUrl(params: {
  backendUrl: string;
  providerPatientId: string;
  messageId?: string;
  action?: "read";
  channel?: string;
}): string {
  const channel = params.channel || "patient";
  const basePath = `${
    params.backendUrl.replace(/\/+$/, "")
  }/v1/partner/patients/${
    encodeURIComponent(params.providerPatientId)
  }/messages`;

  const messagePath = params.messageId
    ? `${basePath}/${encodeURIComponent(params.messageId)}`
    : basePath;
  const endpoint = params.action
    ? `${messagePath}/${params.action}`
    : messagePath;
  const separator = endpoint.includes("?") ? "&" : "?";

  return `${endpoint}${separator}channel=${encodeURIComponent(channel)}`;
}

function parseNonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const trimmedValue = value.trim();
  return trimmedValue.length > 0 ? trimmedValue : null;
}

function parseRecord(value: unknown): JsonRecord | null {
  return !!value && typeof value === "object" && !Array.isArray(value)
    ? value as JsonRecord
    : null;
}

function parseRecordArray(value: unknown): JsonRecord[] {
  if (!Array.isArray(value)) return [];
  return value.filter((entry): entry is JsonRecord =>
    !!entry && typeof entry === "object" && !Array.isArray(entry)
  );
}

function base64UrlEncode(value: string): string {
  return btoa(value)
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/g, "");
}

function base64UrlDecode(value: string): string | null {
  const normalizedValue = value.trim().replace(/-/g, "+").replace(/_/g, "/");
  const paddedValue = normalizedValue.padEnd(
    normalizedValue.length + ((4 - normalizedValue.length % 4) % 4),
    "=",
  );

  try {
    return atob(paddedValue);
  } catch {
    return null;
  }
}

export function encodeProviderChatContextId(
  payload: ProviderChatContextTokenPayload,
): string {
  return base64UrlEncode(JSON.stringify(payload));
}

export function decodeProviderChatContextId(
  contextId: string,
): ProviderChatContextTokenPayload | null {
  const decoded = base64UrlDecode(contextId);
  if (!decoded) return null;

  let parsed: unknown;
  try {
    parsed = JSON.parse(decoded);
  } catch {
    return null;
  }

  const record = parseRecord(parsed);
  if (!record) return null;

  if (record.version !== 1) return null;
  const planId = parseNonEmptyString(record.plan_id);
  const orderId = parseNonEmptyString(record.order_id);
  const tenantIntegrationId = parseNonEmptyString(record.tenant_integration_id);

  if (!planId || !orderId || !tenantIntegrationId) return null;

  return {
    version: 1,
    plan_id: planId,
    order_id: orderId,
    tenant_integration_id: tenantIntegrationId,
  };
}

function normalizeTelegraFileName(value: unknown): string | null {
  const rawValue = parseNonEmptyString(value);
  if (!rawValue) return null;

  const normalized = rawValue.replace(/[\\/\0]/g, "_").slice(0, 255).trim();
  return normalized.length > 0 ? normalized : null;
}

function normalizeTelegraFileExtension(value: unknown): string | null {
  const rawValue = parseNonEmptyString(value);
  if (!rawValue) return null;

  const normalized = rawValue.trim().replace(/^\.+/, "").toLowerCase();
  if (!normalized) return null;
  return /^[a-z0-9]{1,20}$/.test(normalized) ? normalized : null;
}

function normalizeRawBase64(value: string): {
  base64Data: string;
  sizeBytes: number;
} | {
  error: "mime_header" | "invalid";
} {
  if (/^\s*data:/i.test(value) || value.includes(";base64,")) {
    return { error: "mime_header" };
  }

  const normalized = value.replace(/\s+/g, "");
  if (!normalized || normalized.length % 4 === 1) {
    return { error: "invalid" };
  }

  if (!/^[A-Za-z0-9+/]*={0,2}$/.test(normalized)) {
    return { error: "invalid" };
  }

  const paddedBase64 = normalized.padEnd(
    normalized.length + ((4 - normalized.length % 4) % 4),
    "=",
  );
  const paddingBytes = paddedBase64.endsWith("==")
    ? 2
    : paddedBase64.endsWith("=")
    ? 1
    : 0;

  return {
    base64Data: paddedBase64,
    sizeBytes: Math.max(
      0,
      Math.floor(paddedBase64.length * 3 / 4) - paddingBytes,
    ),
  };
}

export function parseTelegraChatFilePayload(
  value: unknown,
  maxDecodedBytes = TELEGRA_CHAT_FILE_MAX_DECODED_BYTES,
): ParsedTelegraChatFile {
  if (value === undefined || value === null) {
    return { file: null, error: null };
  }

  const record = parseRecord(value);
  if (!record) {
    return {
      file: null,
      error: {
        code: "INVALID_FILE",
        message: "file must be an object with name and base64Data",
      },
    };
  }

  const name = normalizeTelegraFileName(record.name) ||
    normalizeTelegraFileName(record.fileName);
  if (!name) {
    return {
      file: null,
      error: {
        code: "INVALID_FILE",
        message: "file.name is required",
      },
    };
  }

  const rawBase64Data = parseNonEmptyString(record.base64Data) ||
    parseNonEmptyString(record.base64_data) ||
    parseNonEmptyString(record.fileData);
  if (!rawBase64Data) {
    return {
      file: null,
      error: {
        code: "INVALID_FILE",
        message: "file.base64Data is required",
      },
    };
  }

  const normalizedBase64 = normalizeRawBase64(rawBase64Data);
  if ("error" in normalizedBase64) {
    return {
      file: null,
      error: {
        code: "INVALID_FILE_BASE64",
        message: normalizedBase64.error === "mime_header"
          ? "file.base64Data must not include a MIME header"
          : "file.base64Data must be a valid base64 string",
      },
    };
  }

  if (normalizedBase64.sizeBytes > maxDecodedBytes) {
    return {
      file: null,
      error: {
        code: "FILE_TOO_LARGE",
        message: "File exceeds the provider size limit",
      },
    };
  }

  const ext = normalizeTelegraFileExtension(record.ext) ||
    normalizeTelegraFileExtension(record.extension);

  return {
    file: {
      name,
      ...(ext ? { ext } : {}),
      base64Data: normalizedBase64.base64Data,
      size_bytes: normalizedBase64.sizeBytes,
    },
    error: null,
  };
}

export function normalizeMdiUserType(value: unknown): MdiSenderRole {
  const rawValue = parseNonEmptyString(value);
  if (!rawValue) return "unknown";

  const normalizedValue = rawValue
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "");

  if (normalizedValue.includes("patient")) return "patient";
  if (normalizedValue.includes("clinician")) return "clinician";
  if (
    normalizedValue.includes("supportstaff") ||
    normalizedValue.includes("staff") ||
    normalizedValue.includes("support")
  ) {
    return "support_staff";
  }

  return "unknown";
}

function extractMdiSenderName(user: unknown): string | null {
  const record = parseRecord(user);
  if (!record) return null;

  const fullName = parseNonEmptyString(record.full_name);
  if (fullName) return fullName;

  const firstName = parseNonEmptyString(record.first_name);
  const lastName = parseNonEmptyString(record.last_name);
  const combinedName = [firstName, lastName].filter(Boolean).join(" ").trim();
  return combinedName.length > 0 ? combinedName : null;
}

export function extractMdiMessages(responseBody: unknown): JsonRecord[] {
  if (Array.isArray(responseBody)) return parseRecordArray(responseBody);

  const responseRecord = parseRecord(responseBody);
  if (!responseRecord) return [];

  const candidatePaths = [
    "message",
    "messages",
    "data",
    "body",
    "body.message",
    "body.messages",
    "body.data",
    "data.message",
    "data.messages",
    "data.data",
  ];

  for (const path of candidatePaths) {
    const value = getValueAtPath(responseRecord, path);
    const messages = parseRecordArray(value);
    if (messages.length > 0) return messages;

    const message = parseRecord(value);
    if (message && parseNonEmptyString(message.id)) return [message];
  }

  return parseNonEmptyString(responseRecord.id) ? [responseRecord] : [];
}

export function normalizeMdiMessage(
  message: unknown,
): NormalizedMdiMessage | null {
  const record = parseRecord(message);
  if (!record) return null;

  const id = parseNonEmptyString(record.id);
  if (!id) return null;

  const senderRole = normalizeMdiUserType(record.user_type);
  const direction = senderRole === "patient"
    ? "outbound"
    : senderRole === "unknown"
    ? "unknown"
    : "inbound";
  const readAt = parseNonEmptyString(record.read_at);

  return {
    id,
    patient_id: parseNonEmptyString(record.patient_id),
    channel: parseNonEmptyString(record.channel),
    text: parseNonEmptyString(record.text),
    sender_role: senderRole,
    sender_type: parseNonEmptyString(record.user_type),
    sender_id: parseNonEmptyString(record.user_id),
    sender_name: extractMdiSenderName(record.user),
    direction,
    read_at: readAt,
    created_at: parseNonEmptyString(record.created_at),
    updated_at: parseNonEmptyString(record.updated_at),
    files: Array.isArray(record.files) ? record.files : [],
    is_unread: direction === "inbound" && !readAt,
  };
}

export function normalizeMdiMessages(
  responseBody: unknown,
): NormalizedMdiMessage[] {
  return extractMdiMessages(responseBody).flatMap((message) => {
    const normalizedMessage = normalizeMdiMessage(message);
    return normalizedMessage ? [normalizedMessage] : [];
  });
}

export function normalizeMdiSingleMessage(
  responseBody: unknown,
): NormalizedMdiMessage | null {
  return normalizeMdiMessages(responseBody)[0] ??
    normalizeMdiMessage(responseBody);
}

export function summarizeMdiMessages(
  messages: NormalizedMdiMessage[],
): MdiMessageSummary {
  const latestMessage = messages.reduce<NormalizedMdiMessage | null>(
    (latest, message) => {
      if (!message.created_at) return latest;
      if (!latest?.created_at) return message;

      const currentTimestamp = Date.parse(message.created_at);
      const latestTimestamp = Date.parse(latest.created_at);

      if (Number.isNaN(currentTimestamp)) return latest;
      if (Number.isNaN(latestTimestamp)) return message;

      return currentTimestamp > latestTimestamp ? message : latest;
    },
    null,
  );

  return {
    total_messages: messages.length,
    unread_count: messages.filter((message) => message.is_unread).length,
    latest_message_id: latestMessage?.id ?? null,
    latest_message_at: latestMessage?.created_at ?? null,
  };
}
