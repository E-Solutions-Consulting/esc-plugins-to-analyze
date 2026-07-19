import { assertEquals } from "../_test/assert.ts";
import {
  buildChatOneSignalIdempotencyKey,
  handleChatMessageReceivedEvent,
  isChatMessageReceivedPayload,
} from "./chat-notification.ts";

type QueryCall = {
  table: string;
  action: string;
  value?: unknown;
};

function canonicalPayload(overrides: Record<string, unknown> = {}) {
  return {
    event_type: "chat.message.received",
    source: "provider_chat",
    provider_name: "telegramd",
    event_id: "telegramd:evt::123",
    occurred_at: "2026-06-19T00:00:00.000Z",
    ids: {
      tenant_id: "tenant-1",
      patient_id: "patient-1",
      provider_patient_id: "pat::123",
      provider_message_id: "evt::123",
      provider_order_id: "order::123",
      patient_platform_order_id: "order-1",
    },
    message: {
      sender_type: "provider",
      raw_sender_type: "affiliate_admin",
      channel_type: "patient",
      preview: null,
    },
    notification: {
      title: "New message",
      body: "You have a new message from your care team.",
      resource: {
        type: "chat",
        provider_name: "telegramd",
        provider_patient_id: "pat::123",
        order_id: "order-1",
      },
    },
    ...overrides,
  };
}

function createSupabaseMock(options: {
  patient?: Record<string, unknown> | null;
  insertError?: Record<string, unknown> | null;
  insertedId?: string;
}) {
  const calls: QueryCall[] = [];
  const state = {
    pendingTable: "",
    pendingInsert: null as unknown,
    pendingUpdate: null as unknown,
  };

  function builder(table: string) {
    const chain = {
      select(value: string) {
        calls.push({ table, action: "select", value });
        return chain;
      },
      eq(column: string, value: unknown) {
        calls.push({ table, action: `eq:${column}`, value });
        return chain;
      },
      insert(value: unknown) {
        calls.push({ table, action: "insert", value });
        state.pendingInsert = value;
        return chain;
      },
      update(value: unknown) {
        calls.push({ table, action: "update", value });
        state.pendingUpdate = value;
        return chain;
      },
      maybeSingle() {
        calls.push({ table, action: "maybeSingle" });
        if (table === "patients") {
          return Promise.resolve({
            data: options.patient === undefined
              ? {
                id: "patient-1",
                tenant_id: "tenant-1",
                auth_user_id: "auth-user-1",
              }
              : options.patient,
            error: null,
          });
        }
        return Promise.resolve({
          data: { id: "notification-existing", read_at: null },
          error: null,
        });
      },
      single() {
        calls.push({ table, action: "single" });
        if (options.insertError) {
          return Promise.resolve({ data: null, error: options.insertError });
        }
        return Promise.resolve({
          data: { id: options.insertedId || "notification-1" },
          error: null,
        });
      },
    };

    return chain;
  }

  return {
    calls,
    supabase: {
      from(table: string) {
        calls.push({ table, action: "from" });
        state.pendingTable = table;
        return builder(table);
      },
    },
  };
}

Deno.test("isChatMessageReceivedPayload recognizes canonical chat event", () => {
  assertEquals(isChatMessageReceivedPayload(canonicalPayload()), true);
  assertEquals(isChatMessageReceivedPayload({ event_type: "order.linked" }), false);
});

Deno.test("buildChatOneSignalIdempotencyKey uses UUID provider message ids unchanged", async () => {
  const key = await buildChatOneSignalIdempotencyKey(
    "md_integrations",
    "523f9563-39c1-4937-afcc-0857499a3a53",
  );

  assertEquals(key, "523f9563-39c1-4937-afcc-0857499a3a53");
});

Deno.test("buildChatOneSignalIdempotencyKey creates stable UUIDs for non-UUID provider message ids", async () => {
  const first = await buildChatOneSignalIdempotencyKey("telegramd", "evt::123");
  const second = await buildChatOneSignalIdempotencyKey("telegramd", "evt::123");
  const different = await buildChatOneSignalIdempotencyKey("telegramd", "evt::456");
  const uuidPattern =
    /^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

  assertEquals(uuidPattern.test(first), true);
  assertEquals(first, second);
  assertEquals(first === different, false);
});

Deno.test("handleChatMessageReceivedEvent passes UUID provider message id to OneSignal", async () => {
  const { supabase } = createSupabaseMock({});
  const scheduleCalls: unknown[][] = [];
  const providerMessageId = "523f9563-39c1-4937-afcc-0857499a3a53";

  await handleChatMessageReceivedEvent(
    new Request("https://example.com/event", { method: "POST" }),
    supabase as never,
    canonicalPayload({
      provider_name: "md_integrations",
      ids: {
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        provider_patient_id: "pat::123",
        provider_message_id: providerMessageId,
        provider_order_id: null,
        patient_platform_order_id: null,
      },
    }),
    "request-1",
    {
      getOneSignalConfigFn: () =>
        Promise.resolve({ app_id: "app-1", rest_api_key: "key-1" }),
      scheduleNotificationWithResultFn: (...args: unknown[]) => {
        scheduleCalls.push(args);
        return Promise.resolve({
          notification_id: "onesignal-1",
          accepted: true,
          status: 200,
          response: { id: "onesignal-1" },
          error: null,
        });
      },
    },
  );

  assertEquals(scheduleCalls.length, 1);
  assertEquals(scheduleCalls[0][5], providerMessageId);
});

Deno.test("handleChatMessageReceivedEvent persists and records OneSignal result", async () => {
  const { supabase, calls } = createSupabaseMock({});
  const scheduleCalls: unknown[][] = [];
  const response = await handleChatMessageReceivedEvent(
    new Request("https://example.com/event", { method: "POST" }),
    supabase as never,
    canonicalPayload(),
    "request-1",
    {
      getOneSignalConfigFn: () =>
        Promise.resolve({ app_id: "app-1", rest_api_key: "key-1" }),
      scheduleNotificationWithResultFn: (...args: unknown[]) => {
        scheduleCalls.push(args);
        return Promise.resolve({
          notification_id: "onesignal-1",
          accepted: true,
          status: 200,
          response: { id: "onesignal-1" },
          error: null,
        });
      },
    },
  );

  assertEquals(response.status, 200);
  const body = await response.json();
  assertEquals(body.status, "created");
  assertEquals(body.push.accepted, true);
  assertEquals(body.push.status, 200);
  assertEquals(body.push.response, { id: "onesignal-1" });
  assertEquals(
    calls.some((call) =>
      call.table === "patient_notifications" && call.action === "insert"
    ),
    true,
  );
  assertEquals(
    calls.some((call) =>
      call.table === "patient_notifications" && call.action === "update"
    ),
    true,
  );
  assertEquals(scheduleCalls.length, 1);
  assertEquals(
    /^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/
      .test(String(scheduleCalls[0][5])),
    true,
  );
});

Deno.test("handleChatMessageReceivedEvent records and returns OneSignal failure details", async () => {
  const originalWarn = console.warn;
  const warnings: unknown[] = [];
  console.warn = (...args: unknown[]) => {
    warnings.push(args);
  };

  try {
    const { supabase, calls } = createSupabaseMock({});
    const response = await handleChatMessageReceivedEvent(
      new Request("https://example.com/event", { method: "POST" }),
      supabase as never,
      canonicalPayload(),
      "request-1",
      {
        getOneSignalConfigFn: () =>
          Promise.resolve({ app_id: "app-1", rest_api_key: "key-1" }),
        scheduleNotificationWithResultFn: () =>
          Promise.resolve({
            notification_id: null,
            accepted: false,
            status: 400,
            response: { errors: ["invalid_player_ids"] },
            error: "OneSignal request failed with status 400",
          }),
      },
    );

    assertEquals(response.status, 200);
    const body = await response.json();
    assertEquals(body.status, "created");
    assertEquals(body.push.accepted, false);
    assertEquals(body.push.status, 400);
    assertEquals(body.push.response, { errors: ["invalid_player_ids"] });
    assertEquals(body.push.error, "OneSignal request failed with status 400");
    assertEquals(
      calls.some((call) =>
        call.table === "patient_notifications" && call.action === "update"
      ),
      true,
    );
    assertEquals(
      warnings.some((entry) =>
        Array.isArray(entry) &&
        entry[0] === "rtdh-webhook: chat notification push failed after insert"
      ),
      true,
    );
  } finally {
    console.warn = originalWarn;
  }
});

Deno.test("handleChatMessageReceivedEvent succeeds when OneSignal is not configured", async () => {
  const { supabase, calls } = createSupabaseMock({});
  const response = await handleChatMessageReceivedEvent(
    new Request("https://example.com/event", { method: "POST" }),
    supabase as never,
    canonicalPayload(),
    "request-1",
    {
      getOneSignalConfigFn: () => Promise.resolve(null),
    },
  );

  const body = await response.json();
  assertEquals(response.status, 200);
  assertEquals(body.status, "created");
  assertEquals(body.push, null);
  assertEquals(
    calls.some((call) =>
      call.table === "patient_notifications" && call.action === "insert"
    ),
    true,
  );
});

Deno.test("handleChatMessageReceivedEvent treats duplicate provider message as success", async () => {
  const { supabase } = createSupabaseMock({
    insertError: { code: "23505", message: "duplicate" },
  });
  const response = await handleChatMessageReceivedEvent(
    new Request("https://example.com/event", { method: "POST" }),
    supabase as never,
    canonicalPayload(),
    "request-1",
  );

  const body = await response.json();
  assertEquals(response.status, 200);
  assertEquals(body.status, "duplicate");
  assertEquals(body.notificationId, "notification-existing");
});

Deno.test("handleChatMessageReceivedEvent rejects patient from another tenant", async () => {
  const { supabase } = createSupabaseMock({
    patient: {
      id: "patient-1",
      tenant_id: "tenant-2",
      auth_user_id: "auth-user-1",
    },
  });
  const response = await handleChatMessageReceivedEvent(
    new Request("https://example.com/event", { method: "POST" }),
    supabase as never,
    canonicalPayload(),
    "request-1",
  );

  const body = await response.json();
  assertEquals(response.status, 422);
  assertEquals(body.error.code, "reference_not_found");
});
