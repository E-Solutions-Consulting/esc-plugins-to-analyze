import { assertEquals } from "../_test/assert.ts";
import {
  buildMdiPatientMessagesEndpointUrl,
  decodeProviderChatContextId,
  encodeProviderChatContextId,
  extractMdiUploadedFileId,
  filterTelegraSystemMessages,
  filterTelegraSystemMessagesInChats,
  getMdiPartnerFileSizeLimitBytes,
  isMdiPartnerFileType,
  isTelegraSystemMessage,
  normalizeMdiMessage,
  normalizeMdiMessages,
  normalizeMdiSingleMessage,
  normalizeMdiUserType,
  normalizeTelegraMessageType,
  parseTelegraChatFilePayload,
  summarizeMdiMessages,
} from "./helpers.ts";

Deno.test("provider chat context ids round-trip opaque context payloads", () => {
  const payload = {
    version: 1 as const,
    plan_id: "plan-1",
    order_id: "order-1",
    tenant_integration_id: "tenant-integration-1",
  };

  const contextId = encodeProviderChatContextId(payload);

  assertEquals(contextId.includes("{"), false);
  assertEquals(contextId.includes("plan-1"), false);
  assertEquals(decodeProviderChatContextId(contextId), payload);
});

Deno.test("provider chat context decoder rejects malformed context ids", () => {
  assertEquals(decodeProviderChatContextId("not-valid-base64"), null);
  assertEquals(
    decodeProviderChatContextId(
      encodeProviderChatContextId({
        version: 1,
        plan_id: "plan-1",
        order_id: "order-1",
        tenant_integration_id: "tenant-integration-1",
      }).slice(2),
    ),
    null,
  );
});

Deno.test("isMdiPartnerFileType validates accepted MDI file categories", () => {
  assertEquals(isMdiPartnerFileType("lab-result"), true);
  assertEquals(isMdiPartnerFileType("face-photo"), true);
  assertEquals(isMdiPartnerFileType("not-a-real-type"), false);
});

Deno.test("getMdiPartnerFileSizeLimitBytes returns MDI size limits by MIME family", () => {
  assertEquals(getMdiPartnerFileSizeLimitBytes("image/png"), 25 * 1024 * 1024);
  assertEquals(getMdiPartnerFileSizeLimitBytes("video/mp4"), 140 * 1024 * 1024);
  assertEquals(
    getMdiPartnerFileSizeLimitBytes("application/pdf"),
    16 * 1024 * 1024,
  );
  assertEquals(
    getMdiPartnerFileSizeLimitBytes("", "face-photo"),
    25 * 1024 * 1024,
  );
  assertEquals(
    getMdiPartnerFileSizeLimitBytes("", "av-video"),
    140 * 1024 * 1024,
  );
});

Deno.test("extractMdiUploadedFileId reads common MDI upload response shapes", () => {
  assertEquals(extractMdiUploadedFileId({ id: "file-1" }), "file-1");
  assertEquals(extractMdiUploadedFileId({ file_id: "file-2" }), "file-2");
  assertEquals(
    extractMdiUploadedFileId({ data: { id: "file-3" } }),
    "file-3",
  );
  assertEquals(extractMdiUploadedFileId({ data: {} }), null);
});

Deno.test("parseTelegraChatFilePayload normalizes valid file payloads", () => {
  assertEquals(
    parseTelegraChatFilePayload({
      name: "folder/report.pdf",
      ext: ".PDF",
      base64Data: "SGV sbG8",
    }),
    {
      file: {
        name: "folder_report.pdf",
        ext: "pdf",
        base64Data: "SGVsbG8=",
        size_bytes: 5,
      },
      error: null,
    },
  );
});

Deno.test("parseTelegraChatFilePayload rejects MIME headers in base64 data", () => {
  assertEquals(
    parseTelegraChatFilePayload({
      name: "image",
      ext: "png",
      base64Data: "data:image/png;base64,SGVsbG8=",
    }),
    {
      file: null,
      error: {
        code: "INVALID_FILE_BASE64",
        message: "file.base64Data must not include a MIME header",
      },
    },
  );
});

Deno.test("parseTelegraChatFilePayload enforces decoded size limits", () => {
  assertEquals(
    parseTelegraChatFilePayload({
      name: "big-file",
      base64Data: "SGVsbG8=",
    }, 4),
    {
      file: null,
      error: {
        code: "FILE_TOO_LARGE",
        message: "File exceeds the provider size limit",
      },
    },
  );
});

Deno.test("buildMdiPatientMessagesEndpointUrl includes patient channel for list requests", () => {
  assertEquals(
    buildMdiPatientMessagesEndpointUrl({
      backendUrl: "https://api.example.test/",
      providerPatientId: "patient 1",
    }),
    "https://api.example.test/v1/partner/patients/patient%201/messages?channel=patient",
  );
});

Deno.test("buildMdiPatientMessagesEndpointUrl includes patient channel for message actions", () => {
  assertEquals(
    buildMdiPatientMessagesEndpointUrl({
      backendUrl: "https://api.example.test",
      providerPatientId: "patient-1",
      messageId: "message/1",
      action: "read",
    }),
    "https://api.example.test/v1/partner/patients/patient-1/messages/message%2F1/read?channel=patient",
  );
});

Deno.test("normalizeTelegraMessageType trims and normalizes message types", () => {
  assertEquals(normalizeTelegraMessageType(" ad-mm "), "ADMM");
  assertEquals(normalizeTelegraMessageType("provider"), "PROVIDER");
  assertEquals(normalizeTelegraMessageType(1), null);
});

Deno.test("isTelegraSystemMessage detects ADMM messages", () => {
  assertEquals(isTelegraSystemMessage({ type: "ADMM" }), true);
  assertEquals(isTelegraSystemMessage({ type: " ad-mm " }), true);
  assertEquals(isTelegraSystemMessage({ type: "PATIENT" }), false);
});

Deno.test("filterTelegraSystemMessages removes all system messages, including the first one", () => {
  assertEquals(
    filterTelegraSystemMessages([
      { id: "1", type: "ADMM", message: "system intro" },
      { id: "2", type: "PATIENT", message: "hello" },
      { id: "3", type: "ADMM", message: "system update" },
      { id: "4", type: "PROVIDER", message: "hi" },
    ]),
    [
      { id: "2", type: "PATIENT", message: "hello" },
      { id: "4", type: "PROVIDER", message: "hi" },
    ],
  );
});

Deno.test("filterTelegraSystemMessagesInChats updates messages and last_message after filtering", () => {
  assertEquals(
    filterTelegraSystemMessagesInChats([
      {
        id: "chat-1",
        messages: [
          { id: "1", type: "ADMM", message: "system intro" },
          { id: "2", type: "PATIENT", message: "hello" },
          { id: "3", type: "PROVIDER", message: "hi" },
        ],
        last_message: { id: "3", type: "PROVIDER", message: "hi" },
      },
      {
        id: "chat-2",
        messages: [
          { id: "4", type: "ADMM", message: "system only" },
        ],
        last_message: { id: "4", type: "ADMM", message: "system only" },
      },
    ]),
    [
      {
        id: "chat-1",
        messages: [
          { id: "2", type: "PATIENT", message: "hello" },
          { id: "3", type: "PROVIDER", message: "hi" },
        ],
        last_message: { id: "3", type: "PROVIDER", message: "hi" },
      },
      {
        id: "chat-2",
        messages: [],
        last_message: { id: "4", type: "ADMM", message: "system only" },
      },
    ],
  );
});

Deno.test("normalizeMdiUserType maps MDI model names to sender roles", () => {
  assertEquals(normalizeMdiUserType("App\\Models\\Patient"), "patient");
  assertEquals(normalizeMdiUserType("App\\Models\\Clinician"), "clinician");
  assertEquals(normalizeMdiUserType("support staff"), "support_staff");
  assertEquals(normalizeMdiUserType(null), "unknown");
});

Deno.test("normalizeMdiMessage creates a frontend-friendly patient message shape", () => {
  assertEquals(
    normalizeMdiMessage({
      id: "msg-1",
      patient_id: "mdi-patient-1",
      channel: "patient",
      text: "Please confirm this dose.",
      user_type: "App\\Models\\Clinician",
      user_id: "clinician-1",
      user: {
        first_name: "Ada",
        last_name: "Lovelace",
      },
      read_at: null,
      created_at: "2026-06-11T10:00:00.000000Z",
      updated_at: "2026-06-11T10:00:00.000000Z",
      files: [{ id: "file-1" }],
    }),
    {
      id: "msg-1",
      patient_id: "mdi-patient-1",
      channel: "patient",
      text: "Please confirm this dose.",
      sender_role: "clinician",
      sender_type: "App\\Models\\Clinician",
      sender_id: "clinician-1",
      sender_name: "Ada Lovelace",
      direction: "inbound",
      read_at: null,
      created_at: "2026-06-11T10:00:00.000000Z",
      updated_at: "2026-06-11T10:00:00.000000Z",
      files: [{ id: "file-1" }],
      is_unread: true,
    },
  );
});

Deno.test("normalizeMdiMessages extracts common list response shapes", () => {
  assertEquals(
    normalizeMdiMessages({
      data: {
        messages: [
          {
            id: "msg-1",
            user_type: "App\\Models\\Patient",
            text: "Hello",
            read_at: null,
            created_at: "2026-06-11T09:00:00.000000Z",
          },
          {
            id: "msg-2",
            user_type: "App\\Models\\Clinician",
            text: "Hi",
            read_at: "2026-06-11 09:05:00",
            created_at: "2026-06-11T09:04:00.000000Z",
          },
        ],
      },
    }).map((message) => ({
      id: message.id,
      direction: message.direction,
      is_unread: message.is_unread,
    })),
    [
      { id: "msg-1", direction: "outbound", is_unread: false },
      { id: "msg-2", direction: "inbound", is_unread: false },
    ],
  );
});

Deno.test("normalizeMdiSingleMessage extracts wrapped message responses", () => {
  assertEquals(
    normalizeMdiSingleMessage({
      data: {
        id: "msg-1",
        user_type: "App\\Models\\Clinician",
        text: "Wrapped response",
        created_at: "2026-06-11T09:00:00.000000Z",
      },
    })?.id,
    "msg-1",
  );
});

Deno.test("summarizeMdiMessages returns unread count and latest message marker", () => {
  const messages = normalizeMdiMessages([
    {
      id: "msg-1",
      user_type: "App\\Models\\Clinician",
      read_at: null,
      created_at: "2026-06-11T09:00:00.000000Z",
    },
    {
      id: "msg-2",
      user_type: "App\\Models\\Patient",
      read_at: null,
      created_at: "2026-06-11T09:05:00.000000Z",
    },
    {
      id: "msg-3",
      user_type: "support staff",
      read_at: null,
      created_at: "2026-06-11T09:03:00.000000Z",
    },
  ]);

  assertEquals(summarizeMdiMessages(messages), {
    total_messages: 3,
    unread_count: 2,
    latest_message_id: "msg-2",
    latest_message_at: "2026-06-11T09:05:00.000000Z",
  });
});
