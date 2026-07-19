import { assertEquals } from "../_test/assert.ts";
import {
  getFriendbuyLedgerBalance,
  getPatientReferrals,
  getPendingFriendbuyRewardTotal,
  ingestFriendbuySyncPayload,
  reconcileFriendbuyRewardsAndCoupons,
  sendFriendbuyPurchaseEvent,
  sendFriendbuySignupEvent,
  trackFriendbuyPurchaseForOrder,
  upsertFriendbuyReferralSnapshot,
  verifyFriendbuyWebhookSignature,
} from "./friendbuy.ts";

async function signFriendbuyBody(secret: string, body: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(body),
  );
  return btoa(String.fromCharCode(...new Uint8Array(signature)));
}

Deno.test("verifyFriendbuyWebhookSignature accepts a correctly signed body", async () => {
  const secret = "test-secret-key";
  const body = JSON.stringify({ id: "evt_1", type: "advocateReward" });
  const signature = await signFriendbuyBody(secret, body);

  assertEquals(
    await verifyFriendbuyWebhookSignature(secret, body, signature),
    true,
  );
});

Deno.test("verifyFriendbuyWebhookSignature rejects a missing signature header", async () => {
  const body = JSON.stringify({ id: "evt_1", type: "advocateReward" });
  assertEquals(
    await verifyFriendbuyWebhookSignature("test-secret-key", body, null),
    false,
  );
});

Deno.test("verifyFriendbuyWebhookSignature rejects a signature computed with the wrong secret", async () => {
  const body = JSON.stringify({ id: "evt_1", type: "advocateReward" });
  const signature = await signFriendbuyBody("wrong-secret", body);

  assertEquals(
    await verifyFriendbuyWebhookSignature("test-secret-key", body, signature),
    false,
  );
});

Deno.test("verifyFriendbuyWebhookSignature rejects a tampered body", async () => {
  const secret = "test-secret-key";
  const originalBody = JSON.stringify({ id: "evt_1", type: "advocateReward" });
  const signature = await signFriendbuyBody(secret, originalBody);
  const tamperedBody = JSON.stringify({ id: "evt_1", type: "advocateReward-tampered" });

  assertEquals(
    await verifyFriendbuyWebhookSignature(secret, tamperedBody, signature),
    false,
  );
});

type FriendbuyEventLog = {
  id: string;
  tenant_id: string;
  event_type: string;
  entity_id: string;
  status: "pending" | "success" | "failed";
  request_payload: Record<string, unknown>;
  response_payload?: unknown;
  error_message?: string | null;
  sent_at?: string | null;
};

type FetchCall = {
  url: string;
  method: string;
  body: unknown;
};

type ReferralRecord = Record<string, unknown> & { id: string };
type ReferralSyncEvent = Record<string, unknown> & { id: string };
type ReferralRewardAction = Record<string, unknown> & { id: string };
type ReferralProgramConfig = Record<string, unknown> & { id: string };

const tenantId = "tenant-1";

function createSupabaseMock(options?: {
  integrationEnabled?: boolean;
  eventLogs?: FriendbuyEventLog[];
  referralRecords?: ReferralRecord[];
  referralRewardActions?: ReferralRewardAction[];
  referralProgramConfigs?: ReferralProgramConfig[];
  orders?: Array<Record<string, unknown> & { id: string }>;
  // Simulates a TOCTOU race: the first plain SELECT of a referral_records row
  // whose referral_code matches this value returns empty (as if the row didn't
  // exist yet), while the row stays present for the insert's uniqueness check —
  // forcing the insert to hit 23505 so we exercise the conflict-recovery path.
  hideReferralLookupOnce?: string;
}) {
  let referralLookupHiddenConsumed = false;
  const eventLogs = [...(options?.eventLogs || [])];
  const referralRecords: ReferralRecord[] = [
    ...(options?.referralRecords || []),
  ];
  const referralSyncEvents: ReferralSyncEvent[] = [];
  const referralRewardActions: ReferralRewardAction[] = [
    ...(options?.referralRewardActions || []),
  ];
  const orders = [...(options?.orders || [])];
  const referralProgramConfigs: ReferralProgramConfig[] = options
    ?.referralProgramConfigs || [{
    id: "program-1",
    tenant_id: tenantId,
    status: "active",
    currency: "USD",
    reward_amount_cents: 2500,
  }];
  const tenantIntegrations = [{
    tenant_id: tenantId,
    integration_key: "friendbuy",
    is_enabled: options?.integrationEnabled ?? true,
    settings: {
      merchant_id: "merchant-1",
      campaign_id: "campaign-1",
      mount_element_id: "friendbuy-referral-widget",
      secret_key: "webhook-secret-1",
      api_key: "api-key-1",
      api_secret_key: "api-secret-key-1",
    },
  }];

  // supabase-js serializes insert/update bodies to JSON, which drops keys whose
  // value is `undefined` — so an `undefined` column is left untouched, never
  // written as NULL. Mirror that here so the production `|| undefined` pattern
  // (preserve-if-absent) behaves the same in tests as against PostgREST.
  function stripUndefined(
    payload: Record<string, unknown>,
  ): Record<string, unknown> {
    return Object.fromEntries(
      Object.entries(payload).filter(([, value]) => value !== undefined),
    );
  }

  function findRows(table: string, filters: Record<string, unknown>) {
    const rows = table === "tenant_integrations"
      ? tenantIntegrations
      : table === "friendbuy_event_logs"
      ? eventLogs
      : table === "referral_records"
      ? referralRecords
      : table === "referral_sync_events"
      ? referralSyncEvents
      : table === "referral_reward_actions"
      ? referralRewardActions
      : table === "referral_program_configs"
      ? referralProgramConfigs
      : table === "orders"
      ? orders
      : [];

    return rows.filter((row) =>
      Object.entries(filters).every(([key, value]) => {
        const rowVal = (row as Record<string, unknown>)[key];
        // A null filter (from .is(col, null)) matches null or undefined, so an
        // inserted row that simply omitted the column still counts as "is null".
        return value === null ? rowVal == null : rowVal === value;
      })
    );
  }

  function buildQuery(table: string) {
    const filters: Record<string, unknown> = {};
    let updatePayload: Record<string, unknown> | null = null;
    let insertPayload: Record<string, unknown> | null = null;
    let insertError: { code: string; message: string } | null = null;

    const query = {
      select() {
        return query;
      },
      eq(key: string, value: unknown) {
        filters[key] = value;
        return query;
      },
      is(key: string, value: unknown) {
        filters[key] = value;
        return query;
      },
      order(_key: string, _opts?: unknown) {
        return query;
      },
      limit(_n: number) {
        return query;
      },
      not(_key: string, _op: string, _value: unknown) {
        return query;
      },
      insert(payload: Record<string, unknown>) {
        if (table === "friendbuy_event_logs") {
          const exists = eventLogs.some((log) =>
            log.tenant_id === payload.tenant_id &&
            log.event_type === payload.event_type &&
            log.entity_id === payload.entity_id
          );
          if (exists) {
            return {
              error: {
                code: "23505",
                message: "duplicate key value violates unique constraint",
              },
            };
          }
          eventLogs.push({
            id: `event-${eventLogs.length + 1}`,
            tenant_id: String(payload.tenant_id),
            event_type: String(payload.event_type),
            entity_id: String(payload.entity_id),
            status: String(payload.status) as FriendbuyEventLog["status"],
            request_payload:
              (payload.request_payload as Record<string, unknown>) || {},
          });
          return { error: null };
        }
        if (table === "referral_records") {
          // Enforce the real (tenant_id, referral_code) partial unique index so
          // tests reproduce the prod 23505 the fix targets.
          if (payload.referral_code != null) {
            const exists = referralRecords.some((row) =>
              (row as Record<string, unknown>).tenant_id ===
                payload.tenant_id &&
              (row as Record<string, unknown>).referral_code ===
                payload.referral_code
            );
            if (exists) {
              insertError = {
                code: "23505",
                message: "duplicate key value violates unique constraint",
              };
              return query;
            }
          }
          insertPayload = {
            id: `referral-${referralRecords.length + 1}`,
            ...stripUndefined(payload),
          };
          referralRecords.push(insertPayload as ReferralRecord);
          return query;
        }
        if (table === "referral_sync_events") {
          const exists = referralSyncEvents.some((event) =>
            event.tenant_id === payload.tenant_id &&
            event.source === payload.source &&
            event.source_event_type === payload.source_event_type &&
            event.source_event_id === payload.source_event_id
          );
          if (exists) {
            return {
              error: {
                code: "23505",
                message: "duplicate key value violates unique constraint",
              },
            };
          }
          insertPayload = {
            id: `sync-${referralSyncEvents.length + 1}`,
            ...payload,
          };
          referralSyncEvents.push(insertPayload as ReferralSyncEvent);
          return { error: null };
        }
        return { error: null };
      },
      update(payload: Record<string, unknown>) {
        updatePayload = payload;
        return query;
      },
      upsert(payload: Record<string, unknown>) {
        if (table === "friendbuy_event_logs") {
          const existing = eventLogs.find((log) =>
            log.tenant_id === payload.tenant_id &&
            log.event_type === payload.event_type &&
            log.entity_id === payload.entity_id
          );
          if (existing) {
            Object.assign(existing, payload);
          } else {
            eventLogs.push({
              id: `event-${eventLogs.length + 1}`,
              tenant_id: String(payload.tenant_id),
              event_type: String(payload.event_type),
              entity_id: String(payload.entity_id),
              status: String(payload.status) as FriendbuyEventLog["status"],
              request_payload:
                (payload.request_payload as Record<string, unknown>) || {},
              response_payload: payload.response_payload,
              error_message: payload.error_message as string | null,
              sent_at: payload.sent_at as string | null,
            });
          }
        }
        return { error: null };
      },
      maybeSingle() {
        if (insertError) {
          return { data: null, error: insertError };
        }
        const matches = findRows(table, filters);
        if (updatePayload) {
          const row = matches[0];
          if (!row) {
            return { data: null, error: null };
          }
          Object.assign(row, stripUndefined(updatePayload));
          return { data: { id: (row as { id?: string }).id }, error: null };
        }
        if (insertPayload) {
          return { data: { id: insertPayload.id }, error: null };
        }
        // Race simulation: hide a matching row from the first plain code lookup.
        if (
          table === "referral_records" &&
          !referralLookupHiddenConsumed &&
          options?.hideReferralLookupOnce != null &&
          filters.referral_code === options.hideReferralLookupOnce
        ) {
          referralLookupHiddenConsumed = true;
          return { data: null, error: null };
        }
        return { data: matches[0] || null, error: null };
      },
      then(
        resolve: (value: { data: unknown[]; error: null }) => unknown,
      ) {
        return Promise.resolve(
          resolve({ data: findRows(table, filters), error: null }),
        );
      },
    };

    return query;
  }

  return {
    eventLogs,
    referralRecords,
    referralSyncEvents,
    referralRewardActions,
    referralProgramConfigs,
    client: {
      from(table: string) {
        return buildQuery(table);
      },
    },
  };
}

async function withFriendbuyFetchMock<T>(
  handler: (calls: FetchCall[]) => Promise<T>,
): Promise<T> {
  const originalFetch = globalThis.fetch;
  const calls: FetchCall[] = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    const url = input instanceof Request ? input.url : String(input);
    const method = init?.method || "GET";
    const body = init?.body ? JSON.parse(String(init.body)) : null;
    calls.push({ url, method, body });

    if (url.endsWith("/authorization")) {
      return Promise.resolve(
        new Response(JSON.stringify({ token: "friendbuy-token" }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );
    }

    return Promise.resolve(
      new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );
  }) as typeof fetch;

  try {
    return await handler(calls);
  } finally {
    globalThis.fetch = originalFetch;
  }
}

Deno.test("sendFriendbuySignupEvent sends account-sign-up payload with tenant campaign context", async () => {
  const supabase = createSupabaseMock();

  await withFriendbuyFetchMock(async (calls) => {
    await sendFriendbuySignupEvent(supabase.client, {
      tenantId,
      patientId: "patient-1",
      customer: {
        id: "patient-1",
        email: "friend@example.com",
        firstName: "Friend",
        lastName: "Patient",
      },
      attribution: {
        referralCode: "REF123",
        attributionId: "attr-1",
      },
    });

    assertEquals(calls[1].url.endsWith("/event/account-sign-up"), true);
    assertEquals(calls[1].body, {
      customerId: "patient-1",
      email: "friend@example.com",
      firstName: "Friend",
      lastName: "Patient",
      campaignId: "campaign-1",
      referralCode: "REF123",
      attributionId: "attr-1",
    });
    assertEquals(supabase.eventLogs[0].status, "success");
  });
});

Deno.test("sendFriendbuyPurchaseEvent sends purchase payload once per tenant order", async () => {
  const supabase = createSupabaseMock();

  await withFriendbuyFetchMock(async (calls) => {
    await sendFriendbuyPurchaseEvent(supabase.client, {
      tenantId,
      purchase: {
        orderId: "order-1",
        amount: 99.5,
        currency: "USD",
        customer: {
          id: "patient-1",
          email: "friend@example.com",
          firstName: "Friend",
          lastName: "Patient",
        },
        attribution: {
          referralCode: "REF123",
          attributionId: "attr-1",
        },
        products: [{
          sku: "sku-1",
          name: "Product",
          quantity: 1,
          price: 99.5,
        }],
      },
    });

    await sendFriendbuyPurchaseEvent(supabase.client, {
      tenantId,
      purchase: {
        orderId: "order-1",
        amount: 99.5,
        currency: "USD",
        customer: { id: "patient-1", email: "friend@example.com" },
      },
    });

    const purchaseCalls = calls.filter((call) =>
      call.url.endsWith("/event/purchase")
    );
    assertEquals(purchaseCalls.length, 1);
    assertEquals(purchaseCalls[0].body, {
      orderId: "order-1",
      customerId: "patient-1",
      email: "friend@example.com",
      firstName: "Friend",
      lastName: "Patient",
      amount: 99.5,
      currency: "USD",
      campaignId: "campaign-1",
      referralCode: "REF123",
      attributionId: "attr-1",
      products: [{
        sku: "sku-1",
        name: "Product",
        quantity: 1,
        price: 99.5,
      }],
    });
  });
});

Deno.test("sendFriendbuyPurchaseEvent skips rows already pending from another worker", async () => {
  const supabase = createSupabaseMock({
    eventLogs: [{
      id: "event-existing",
      tenant_id: tenantId,
      event_type: "purchase",
      entity_id: "order-1",
      status: "pending",
      request_payload: {},
    }],
  });

  await withFriendbuyFetchMock(async (calls) => {
    await sendFriendbuyPurchaseEvent(supabase.client, {
      tenantId,
      purchase: {
        orderId: "order-1",
        amount: 25,
        currency: "USD",
        customer: { id: "patient-1", email: "friend@example.com" },
      },
    });

    assertEquals(calls.length, 0);
  });
});

Deno.test("sendFriendbuyPurchaseEvent retries failed rows and rewrites event log", async () => {
  const supabase = createSupabaseMock({
    eventLogs: [{
      id: "event-existing",
      tenant_id: tenantId,
      event_type: "purchase",
      entity_id: "order-1",
      status: "failed",
      request_payload: {},
      error_message: "Friendbuy purchase failed: 500",
    }],
  });

  await withFriendbuyFetchMock(async (calls) => {
    await sendFriendbuyPurchaseEvent(supabase.client, {
      tenantId,
      purchase: {
        orderId: "order-1",
        amount: 25,
        currency: "USD",
        customer: { id: "patient-1", email: "friend@example.com" },
      },
    });

    assertEquals(
      calls.some((call) => call.url.endsWith("/event/purchase")),
      true,
    );
    assertEquals(supabase.eventLogs[0].status, "success");
    assertEquals(supabase.eventLogs[0].error_message, null);
  });
});

Deno.test("getPendingFriendbuyRewardTotal sums only pending rewards and excludes rejected/other-tenant rows", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [
      {
        id: "referral-credited",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        reward_status: "credited",
        status: "rewarded",
        reward_amount_cents: 2500,
        currency: "USD",
      },
      {
        id: "referral-pending",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        reward_status: "pending_approval",
        status: "reward_pending",
        reward_amount_cents: 1500,
        currency: "USD",
      },
      {
        id: "referral-rejected",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        reward_status: "rejected",
        status: "exception",
        reward_amount_cents: 9900,
        currency: "USD",
      },
      {
        id: "referral-other-tenant",
        tenant_id: "tenant-2",
        referrer_patient_id: "patient-1",
        reward_status: "pending_approval",
        status: "reward_pending",
        reward_amount_cents: 9900,
        currency: "USD",
      },
    ],
  });

  const pending = await getPendingFriendbuyRewardTotal(supabase.client, {
    tenantId,
    patientId: "patient-1",
    patientEmail: "advocate@example.com",
    currency: "USD",
  });

  assertEquals(pending.pendingTotal, 15);
  assertEquals(pending.formattedPendingTotal, "+$15.00");
  assertEquals(pending.currency, "USD");
});

Deno.test("getPendingFriendbuyRewardTotal supports email fallback, config reward amount, and excludes expired rows", async () => {
  const supabase = createSupabaseMock({
    referralProgramConfigs: [{
      id: "program-1",
      tenant_id: tenantId,
      status: "active",
      currency: "USD",
      reward_amount_cents: 2500,
    }],
    referralRecords: [
      {
        id: "referral-email-match",
        tenant_id: tenantId,
        referrer_email: " Advocate@Example.com ",
        reward_status: "pending_eligibility",
        status: "reward_pending",
        currency: "USD",
      },
      {
        id: "referral-pending-approval",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        reward_status: "pending_approval",
        status: "reward_pending",
        reward_amount_cents: 1000,
        currency: "USD",
      },
      {
        id: "referral-expired",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        reward_status: "credited",
        status: "expired",
        reward_amount_cents: 9900,
        currency: "USD",
      },
    ],
  });

  const pending = await getPendingFriendbuyRewardTotal(supabase.client, {
    tenantId,
    patientId: "patient-1",
    patientEmail: "advocate@example.com",
    currency: "USD",
  });

  assertEquals(pending.pendingTotal, 35);
  assertEquals(pending.formattedPendingTotal, "+$35.00");
});

Deno.test("getPendingFriendbuyRewardTotal returns safe empty total when Friendbuy is disabled", async () => {
  const supabase = createSupabaseMock({
    integrationEnabled: false,
    referralRecords: [{
      id: "referral-pending",
      tenant_id: tenantId,
      referrer_patient_id: "patient-1",
      reward_status: "pending_approval",
      status: "reward_pending",
      reward_amount_cents: 2500,
      currency: "USD",
    }],
  });

  const pending = await getPendingFriendbuyRewardTotal(supabase.client, {
    tenantId,
    patientId: "patient-1",
    patientEmail: "advocate@example.com",
    currency: "USD",
  });

  assertEquals(pending.pendingTotal, 0);
  assertEquals(pending.formattedPendingTotal, null);
});

Deno.test("getPatientReferrals only lists rows with a friend_email, mapping credited vs pending and excluding dead-end rows", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [
      {
        id: "referral-credited",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        friend_email: "sarah.johnson@gmail.com",
        reward_status: "credited",
        status: "rewarded",
        reward_amount_cents: 2500,
        currency: "USD",
        issued_at: "2025-03-01T00:00:00Z",
        reward_credited_at: "2025-03-05T00:00:00Z",
      },
      {
        id: "referral-pending",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        friend_email: "mike.torres@icloud.com",
        reward_status: "pending_approval",
        status: "reward_pending",
        reward_amount_cents: 2500,
        currency: "USD",
        issued_at: "2025-03-12T00:00:00Z",
      },
      {
        id: "referral-no-friend-yet",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        friend_email: null,
        reward_status: "not_earned",
        status: "issued",
        reward_amount_cents: 2500,
        currency: "USD",
        issued_at: "2025-03-15T00:00:00Z",
      },
      {
        id: "referral-rejected",
        tenant_id: tenantId,
        referrer_patient_id: "patient-1",
        friend_email: "rejected@example.com",
        reward_status: "rejected",
        status: "exception",
        reward_amount_cents: 2500,
        currency: "USD",
        issued_at: "2025-03-16T00:00:00Z",
      },
      {
        id: "referral-other-tenant",
        tenant_id: "tenant-2",
        referrer_patient_id: "patient-1",
        friend_email: "other-tenant@example.com",
        reward_status: "credited",
        status: "rewarded",
        reward_amount_cents: 2500,
        currency: "USD",
        issued_at: "2025-03-17T00:00:00Z",
      },
    ],
  });

  const referrals = await getPatientReferrals(supabase.client, {
    tenantId,
    patientId: "patient-1",
    patientEmail: "advocate@example.com",
    currency: "USD",
  });

  assertEquals(referrals.total, 2);
  assertEquals(referrals.referrals.length, 2);
  assertEquals(referrals.referrals[0].friendEmail, "mike.torres@icloud.com");
  assertEquals(referrals.referrals[0].status, "pending");
  assertEquals(referrals.referrals[0].formattedAmount, "$25.00");
  assertEquals(referrals.referrals[1].friendEmail, "sarah.johnson@gmail.com");
  assertEquals(referrals.referrals[1].status, "credited");
  assertEquals(referrals.referrals[1].occurredAt, "2025-03-05T00:00:00Z");
});

Deno.test("getPatientReferrals matches by referrer email fallback and applies the program's fallback reward amount", async () => {
  const supabase = createSupabaseMock({
    referralProgramConfigs: [{
      id: "program-1",
      tenant_id: tenantId,
      status: "active",
      currency: "USD",
      reward_amount_cents: 2500,
    }],
    referralRecords: [
      {
        id: "referral-email-match",
        tenant_id: tenantId,
        referrer_email: " Advocate@Example.com ",
        friend_email: "emma.w@outlook.com",
        reward_status: "pending_eligibility",
        status: "reward_pending",
        currency: "USD",
        issued_at: "2025-03-17T00:00:00Z",
      },
    ],
  });

  const referrals = await getPatientReferrals(supabase.client, {
    tenantId,
    patientId: "patient-1",
    patientEmail: "advocate@example.com",
    currency: "USD",
  });

  assertEquals(referrals.total, 1);
  assertEquals(referrals.referrals[0].friendEmail, "emma.w@outlook.com");
  assertEquals(referrals.referrals[0].status, "pending");
  assertEquals(referrals.referrals[0].amountCents, 2500);
  assertEquals(referrals.referrals[0].formattedAmount, "$25.00");
});

Deno.test("getPatientReferrals caps the returned list while keeping the true total", async () => {
  const manyRows = Array.from({ length: 12 }, (_, index) => ({
    id: `referral-${index}`,
    tenant_id: tenantId,
    referrer_patient_id: "patient-1",
    friend_email: `friend-${index}@example.com`,
    reward_status: "credited",
    status: "rewarded",
    reward_amount_cents: 2500,
    currency: "USD",
    issued_at: `2025-01-${String(index + 1).padStart(2, "0")}T00:00:00Z`,
  }));

  const supabase = createSupabaseMock({ referralRecords: manyRows });

  const referrals = await getPatientReferrals(supabase.client, {
    tenantId,
    patientId: "patient-1",
    patientEmail: "advocate@example.com",
    currency: "USD",
  });

  assertEquals(referrals.total, 12);
  assertEquals(referrals.referrals.length, 10);
  assertEquals(referrals.referrals[0].friendEmail, "friend-11@example.com");
});

Deno.test("ingestFriendbuySyncPayload maps coupon tracking fields", async () => {
  const supabase = createSupabaseMock();

  const result = await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "coupon",
    eventId: "coupon-event-1",
    payload: {
      id: "coupon-1",
      code: "REF25",
      friend_email: "friend@example.com",
      advocate_email: "advocate@example.com",
      status: "active",
      expires_at: "2030-01-01T00:00:00.000Z",
      redemption_count: 0,
      max_redemptions: 1,
      stripe_promotion_code_id: "promo_123",
    },
  });

  assertEquals(result.processed, true);
  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].referral_code, "REF25");
  assertEquals(supabase.referralRecords[0].coupon_status, "active");
  assertEquals(supabase.referralRecords[0].validity_status, "active");
  assertEquals(supabase.referralRecords[0].redemption_count, 0);
  assertEquals(supabase.referralRecords[0].max_redemptions, 1);
  assertEquals(
    supabase.referralRecords[0].stripe_promotion_code_id,
    "promo_123",
  );
});

Deno.test("ingestFriendbuySyncPayload maps reward credited fields", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "reward_credited",
    eventId: "reward-event-1",
    payload: {
      id: "reward-1",
      referral_code: "REF25",
      reward_status: "credited",
      credited_at: "2030-02-01T00:00:00.000Z",
      advocate: { email: "advocate@example.com" },
      friend: { email: "friend@example.com" },
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].status, "rewarded");
  assertEquals(supabase.referralRecords[0].reward_status, "credited");
  assertEquals(
    supabase.referralRecords[0].reward_credited_at,
    "2030-02-01T00:00:00.000Z",
  );
});

Deno.test("ingestFriendbuySyncPayload maps a real advocateReward webhook to a single friend row", async () => {
  const supabase = createSupabaseMock();

  // Each coupon code is issued to exactly one friend (see mapAdvocateRewardEvent
  // and the (tenant_id, referral_code) unique index), so friends[] holds at most
  // one entry in practice — one referral_records row results.
  const result = await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "advocateReward",
    eventId: "advocate-reward-event-1",
    payload: {
      id: "evt-1",
      type: "advocateReward",
      createdOn: "2030-03-01T00:00:00.000Z",
      data: [
        {
          rewardId: "reward-1",
          emailAddress: "advocate@example.com",
          customerId: "advocate-customer-1",
          couponCode: "REF25",
          createdOn: "2030-03-01T00:00:00.000Z",
          friends: [
            { friendEmailAddress: "friend-a@example.com", conversionNumber: 1 },
          ],
        },
      ],
    },
  });

  assertEquals(result.processed, true);
  assertEquals(supabase.referralRecords.length, 1);
  const row = supabase.referralRecords[0];
  assertEquals(row.friend_email, "friend-a@example.com");
  assertEquals(row.referrer_email, "advocate@example.com");
  // advocateReward carries no referralCode, and its couponCode is the advocate's
  // reward (a Stripe credit here) — so neither referral_code nor
  // friend_coupon_code is written from this event.
  assertEquals(row.referral_code ?? null, null);
  assertEquals(row.friend_coupon_code ?? null, null);
  assertEquals(row.friendbuy_reward_id, "reward-1");
  assertEquals(row.status, "reward_pending");
  assertEquals(row.reward_status, "pending_eligibility");
});

Deno.test("ingestFriendbuySyncPayload handles an advocateReward with no friends listed as a single referrer-only row", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "advocateReward",
    eventId: "advocate-reward-event-2",
    payload: {
      data: [{
        rewardId: "reward-2",
        emailAddress: "advocate@example.com",
        couponCode: "REF26",
        createdOn: "2030-03-02T00:00:00.000Z",
      }],
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].friend_email, undefined);
  // No referralCode on the event; couponCode (advocate reward) is not stored.
  assertEquals(supabase.referralRecords[0].referral_code ?? null, null);
  assertEquals(supabase.referralRecords[0].referrer_email, "advocate@example.com");
  assertEquals(supabase.referralRecords[0].friendbuy_reward_id, "reward-2");
});

Deno.test("ingestFriendbuySyncPayload processes every item in a batched data[] delivery", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "friendIncentive",
    eventId: "friend-incentive-batch-1",
    payload: {
      data: [
        {
          rewardId: "reward-a",
          emailAddress: "friend-a@example.com",
          advocateEmailAddress: "advocate@example.com",
          couponCode: "REF30",
          createdOn: "2030-03-03T00:00:00.000Z",
        },
        {
          rewardId: "reward-b",
          emailAddress: "friend-b@example.com",
          advocateEmailAddress: "advocate@example.com",
          couponCode: "REF31",
          createdOn: "2030-03-04T00:00:00.000Z",
        },
      ],
    },
  });

  assertEquals(supabase.referralRecords.length, 2);
  assertEquals(
    supabase.referralRecords.map((row) => row.friend_email).sort(),
    ["friend-a@example.com", "friend-b@example.com"],
  );
  for (const row of supabase.referralRecords) {
    assertEquals(row.status, "conversion_recorded");
    // Fresh insert — reward_status defaults to not_earned since
    // mapFriendIncentiveEvent intentionally leaves it unset (this event
    // describes the friend's incentive, not the advocate's reward).
    assertEquals(row.reward_status, "not_earned");
  }
});

Deno.test("ingestFriendbuySyncPayload maps a real emailCapture webhook", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "emailCapture",
    eventId: "email-capture-event-1",
    payload: {
      data: [{
        emailAddress: "friend@example.com",
        createdOn: "2030-03-05T00:00:00.000Z",
        advocate: { email: "advocate@example.com" },
        referral: { code: "REF40" },
        campaign: { id: "campaign-abc" },
      }],
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].friend_email, "friend@example.com");
  assertEquals(supabase.referralRecords[0].referrer_email, "advocate@example.com");
  assertEquals(supabase.referralRecords[0].referral_code, "REF40");
  assertEquals(supabase.referralRecords[0].status, "delivered");
});

Deno.test("ingestFriendbuySyncPayload dedupes repeated code-less emailCapture events into one row per friend", async () => {
  const supabase = createSupabaseMock();

  // emailCapture with no referral.code and no incentive.couponCode → the
  // snapshot has no code and no share/conversion/reward id (a keyless row).
  const keylessCapture = {
    tenantId,
    source: "friendbuy_webhook" as const,
    eventType: "emailCapture",
    payload: {
      data: [{
        emailAddress: "friend@example.com",
        createdOn: "2030-03-05T00:00:00.000Z",
        advocate: { email: "advocate@example.com" },
        campaign: { id: "campaign-abc" },
      }],
    },
  };

  // Two deliveries (or a redelivery) of the same keyless capture must collapse
  // to a single row — there is no code for the unique index to guard, so the
  // dedupe comes from the (tenant, friend_email, campaign) lookup branch.
  await ingestFriendbuySyncPayload(supabase.client, {
    ...keylessCapture,
    eventId: "email-capture-event-1",
  });
  await ingestFriendbuySyncPayload(supabase.client, {
    ...keylessCapture,
    eventId: "email-capture-event-2",
  });

  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].friend_email, "friend@example.com");
  assertEquals(supabase.referralRecords[0].friendbuy_campaign_id, "campaign-abc");
  assertEquals(supabase.referralRecords[0].referral_code ?? null, null);
});

Deno.test("ingestFriendbuySyncPayload keeps a later coded row separate from an earlier code-less emailCapture", async () => {
  const supabase = createSupabaseMock();

  // Earliest touchpoint: keyless emailCapture (no code).
  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "emailCapture",
    eventId: "email-capture-event-1",
    payload: {
      data: [{
        emailAddress: "friend@example.com",
        createdOn: "2030-03-05T00:00:00.000Z",
        advocate: { email: "advocate@example.com" },
        campaign: { id: "campaign-abc" },
      }],
    },
  });

  // Same friend later converts WITH a code. The code-less dedupe is scoped to
  // referral_code IS NULL, so this must NOT merge into (or regress) the coded
  // row — it stays a distinct record.
  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "conversion",
    eventId: "conversion-event-1",
    payload: {
      email: "friend@example.com",
      advocateEmail: "advocate@example.com",
      referralCode: "REF99",
      orderId: "order-1",
      campaignId: "campaign-abc",
      createdOn: "2030-03-06T00:00:00.000Z",
    },
  });

  assertEquals(supabase.referralRecords.length, 2);
  const coded = supabase.referralRecords.find((r) => r.referral_code === "REF99");
  const codeless = supabase.referralRecords.find((r) =>
    (r.referral_code ?? null) === null
  );
  assertEquals(coded?.status, "conversion_recorded");
  assertEquals(codeless?.status, "delivered");
});

Deno.test("ingestFriendbuySyncPayload logs but does not mutate referral_records for emailOptOut and Loyalty-only events", async () => {
  for (const eventType of ["emailOptOut", "loyaltyReward", "receipt", "ledgerTransaction", "customerUpdate"]) {
    const supabase = createSupabaseMock();

    const result = await ingestFriendbuySyncPayload(supabase.client, {
      tenantId,
      source: "friendbuy_webhook",
      eventType,
      eventId: `${eventType}-event-1`,
      payload: { data: [{ emailAddress: "friend@example.com" }] },
    });

    assertEquals(result.processed, true);
    assertEquals(result.referralRecordId, null);
    assertEquals(supabase.referralRecords.length, 0);
    assertEquals(supabase.referralSyncEvents.length, 1);
  }
});

Deno.test("ingestFriendbuySyncPayload maps a real /analytics/shares record (flat, no friend, no id)", async () => {
  const supabase = createSupabaseMock();

  const result = await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "share",
    payload: {
      advocateCustomerId: "c9a58f97-7d2c-4cfd-a8e7-ed404038d2b2",
      advocateEmail: "user@example.com",
      advocateName: "Test Advocate",
      channel: "email",
      referralCode: "abc123",
      campaignId: "ad39255f-b7e6-4c8b-baa8-5c7aa0c3e241",
      campaignName: "Evergreen Campaign",
      createdOn: "2021-02-23T01:20:14Z",
    },
  });

  assertEquals(result.processed, true);
  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].referral_code, "abc123");
  assertEquals(supabase.referralRecords[0].referrer_email, "user@example.com");
  assertEquals(supabase.referralRecords[0].status, "delivered");
  assertEquals(supabase.referralRecords[0].delivered_at, "2021-02-23T01:20:14Z");
});

Deno.test("ingestFriendbuySyncPayload maps a real /analytics/clicks record to a clicked status", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "click",
    payload: {
      advocateCustomerId: "e24b313b-c941-4baf-8762-34b3af8cddee",
      advocateEmail: "user@example.com",
      channel: "purl",
      referralCode: "abc123",
      campaignId: "1b9accd7-8ec5-4ff6-8d22-941fdc7338f4",
      createdOn: "2021-02-23T01:20:14Z",
      destinationUrl: "https://example.com",
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].status, "clicked");
  assertEquals(supabase.referralRecords[0].clicked_at, "2021-02-23T01:20:14Z");
});

Deno.test("ingestFriendbuySyncPayload maps a real GetCoupons redeemed record onto friend_coupon_code, not referral_code", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "coupon_status",
    payload: {
      code: "lu8y2nwr1v",
      status: "redeemed",
      distributedOn: "2024-03-26T22:48:00.823Z",
      redeemedOn: "2024-03-26T22:48:47.207Z",
      redemptionOptionName: "$10 Coupon",
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  // GetCoupons `code` is the friend's coupon code, stored as friend_coupon_code
  // — NOT as the advocate's referral_code.
  assertEquals(supabase.referralRecords[0].friend_coupon_code, "lu8y2nwr1v");
  assertEquals(supabase.referralRecords[0].referral_code ?? null, null);
  assertEquals(supabase.referralRecords[0].coupon_status, "redeemed");
  assertEquals(supabase.referralRecords[0].validity_status, "redeemed");
  assertEquals(supabase.referralRecords[0].redemption_count, 1);
  assertEquals(
    supabase.referralRecords[0].delivered_at,
    "2024-03-26T22:48:00.823Z",
  );
  assertEquals(
    supabase.referralRecords[0].redeemed_at,
    "2024-03-26T22:48:47.207Z",
  );
});

Deno.test("ingestFriendbuySyncPayload maps a real GetCoupons distributed-but-unredeemed record", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "coupon_status",
    payload: {
      code: "lpk7wzdf6g",
      status: "distributed",
      distributedOn: "2023-11-29T20:11:56.347Z",
      redemptionOptionName: "$20 Coupon",
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].coupon_status, "distributed");
  assertEquals(supabase.referralRecords[0].validity_status, "active");
  assertEquals(supabase.referralRecords[0].redemption_count, 0);
  assertEquals(supabase.referralRecords[0].redeemed_at ?? null, null);
});

Deno.test("reconcileFriendbuyRewardsAndCoupons pulls GetReferralRewards and GetCoupons, combining synced counts", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [{
      id: "referral-existing-1",
      tenant_id: tenantId,
      referrer_email: "advocate@example.com",
      friend_email: "friend@example.com",
      referral_code: "existing-code-1",
      friend_coupon_code: "coupon-code-1",
    }],
  });
  const originalFetch = globalThis.fetch;
  const calledUrls: string[] = [];

  globalThis.fetch = ((input: URL | RequestInfo) => {
    const url = input instanceof Request ? input.url : String(input);
    calledUrls.push(url);

    if (url.endsWith("/authorization")) {
      return Promise.resolve(
        new Response(JSON.stringify({ token: "friendbuy-token" }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );
    }
    if (url.includes("/analytics/rewards/referral")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({
            totalResults: 1,
            results: [{
              id: "reward-1",
              advocateEmail: "advocate@example.com",
              referralCode: "reward-code-1",
              status: "Rewarded",
              createdOn: "2024-01-01T00:00:00.000Z",
            }],
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    if (url.includes("/analytics/distributed-advocate-rewards")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({ totalResults: 0, results: [] }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    if (url.includes("/reward/coupons")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({
            records: [{
              code: "coupon-code-1",
              status: "redeemed",
              distributedOn: "2024-01-01T00:00:00.000Z",
              redeemedOn: "2024-01-02T00:00:00.000Z",
            }],
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    return Promise.resolve(new Response("not found", { status: 404 }));
  }) as typeof fetch;

  try {
    const result = await reconcileFriendbuyRewardsAndCoupons(supabase.client, {
      tenantId,
    });

    assertEquals(result.ok, true);
    assertEquals(result.synced, 2);
    assertEquals(
      calledUrls.some((url) => url.includes("/analytics/rewards/referral")),
      true,
    );
    // Coupons are the FRIEND's, so GetCoupons is queried by friend email.
    assertEquals(
      calledUrls.some((url) =>
        url.includes("/reward/coupons") &&
        url.includes("email=friend%40example.com")
      ),
      true,
    );
    // reward creates its own referralCode-keyed row; the coupon redemption
    // matches the existing row by friend_coupon_code (no orphan) → 2 rows.
    assertEquals(supabase.referralRecords.length, 2);
    const rewardRow = supabase.referralRecords.find((row) =>
      row.referral_code === "reward-code-1"
    );
    const couponRow = supabase.referralRecords.find((row) =>
      row.friend_coupon_code === "coupon-code-1"
    );
    assertEquals(rewardRow?.reward_status, "credited");
    assertEquals(couponRow?.id, "referral-existing-1");
    assertEquals(couponRow?.coupon_status, "redeemed");
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("reconcileFriendbuyRewardsAndCoupons follows nextPageToken until all reward pages are fetched", async () => {
  const supabase = createSupabaseMock();
  const originalFetch = globalThis.fetch;
  const rewardUrls: string[] = [];

  globalThis.fetch = ((input: URL | RequestInfo) => {
    const url = input instanceof Request ? input.url : String(input);

    if (url.endsWith("/authorization")) {
      return Promise.resolve(
        new Response(JSON.stringify({ token: "friendbuy-token" }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );
    }
    if (url.includes("/analytics/rewards/referral")) {
      rewardUrls.push(url);
      const isPage2 = url.includes("pageToken=tok2");
      return Promise.resolve(
        new Response(
          JSON.stringify({
            totalResults: 2,
            nextPageToken: isPage2 ? null : "tok2",
            results: [{
              id: isPage2 ? "reward-2" : "reward-1",
              advocateEmail: "advocate@example.com",
              referralCode: isPage2 ? "reward-code-2" : "reward-code-1",
              status: "Rewarded",
              createdOn: "2024-01-01T00:00:00.000Z",
            }],
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    if (url.includes("/analytics/distributed-advocate-rewards")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({ totalResults: 0, results: [] }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    if (url.includes("/reward/coupons")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({ records: [] }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    return Promise.resolve(new Response("not found", { status: 404 }));
  }) as typeof fetch;

  try {
    const result = await reconcileFriendbuyRewardsAndCoupons(supabase.client, {
      tenantId,
    });

    assertEquals(result.ok, true);
    // Two pages of rewards, one record each → 2 synced from rewards alone.
    assertEquals(result.synced >= 2, true);
    // The rewards endpoint must have been fetched twice.
    assertEquals(rewardUrls.length, 2);
    assertEquals(rewardUrls.some((url) => url.includes("pageToken=tok2")), true);
    // Both reward rows must land in referral_records.
    assertEquals(
      supabase.referralRecords.some((r) => r.referral_code === "reward-code-1"),
      true,
    );
    assertEquals(
      supabase.referralRecords.some((r) => r.referral_code === "reward-code-2"),
      true,
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("reconcileFriendbuyRewardsAndCoupons populates reward_amount_cents from distributed-advocate-rewards", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [{
      id: "referral-existing-1",
      tenant_id: tenantId,
      referrer_email: "advocate@example.com",
      referral_code: "ref-code-1",
      reward_amount_cents: 0,
    }],
  });
  const originalFetch = globalThis.fetch;

  globalThis.fetch = ((input: URL | RequestInfo) => {
    const url = input instanceof Request ? input.url : String(input);

    if (url.endsWith("/authorization")) {
      return Promise.resolve(
        new Response(JSON.stringify({ token: "friendbuy-token" }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );
    }
    if (url.includes("/analytics/rewards/referral")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({ totalResults: 0, results: [] }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    if (url.includes("/analytics/distributed-advocate-rewards")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({
            totalResults: 1,
            results: [{
              advocateEmail: "advocate@example.com",
              advocateCustomerId: "customer-1",
              friendEmail: "friend@example.com",
              referralCode: "ref-code-1",
              couponCode: "SAVE25",
              rewardAmount: 25,
              rewardType: "Coupon Code",
              campaignId: "campaign-1",
              createdOn: "2024-02-01T00:00:00.000Z",
            }],
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    if (url.includes("/reward/coupons")) {
      return Promise.resolve(
        new Response(
          JSON.stringify({ records: [] }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );
    }
    return Promise.resolve(new Response("not found", { status: 404 }));
  }) as typeof fetch;

  try {
    const result = await reconcileFriendbuyRewardsAndCoupons(supabase.client, {
      tenantId,
    });

    assertEquals(result.ok, true);
    assertEquals(result.synced >= 1, true);

    // upsertFriendbuyReferralSnapshot matches on referral_code alone (codes are
    // unique per friend), so the pre-seeded row — despite having no friend_email
    // — is UPDATED in place rather than duplicated. Exactly one row for the code.
    const rowsForCode = supabase.referralRecords.filter((r) =>
      r.referral_code === "ref-code-1"
    );
    assertEquals(rowsForCode.length, 1);
    const creditedRow = rowsForCode[0];
    assertEquals(creditedRow.id, "referral-existing-1");
    assertEquals(creditedRow.reward_status, "credited");
    assertEquals(creditedRow.reward_amount_cents, 2500);
    assertEquals(creditedRow.friend_email, "friend@example.com");

    // Idempotency: a distributed_advocate_reward sync event must be recorded
    // using SAVE25 (the couponCode) as the source_event_id.
    const syncEvent = supabase.referralSyncEvents.find((e) =>
      e.source_event_type === "distributed_advocate_reward" &&
      e.source_event_id === "SAVE25"
    );
    assertEquals(syncEvent !== undefined, true);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("upsertFriendbuyReferralSnapshot matches an existing code row regardless of friend_email (no duplicate insert)", async () => {
  const supabase = createSupabaseMock();

  // A share arrives first — carries the code but no friend identity.
  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "share",
    payload: {
      advocateEmail: "advocate@example.com",
      referralCode: "merge-code",
      createdOn: "2024-05-01T00:00:00.000Z",
    },
  });
  assertEquals(supabase.referralRecords.length, 1);
  assertEquals(supabase.referralRecords[0].friend_email ?? null, null);

  // The friend then converts on the same code. The lookup keys on referral_code
  // alone, so this UPDATES the share row (filling friend_email) instead of
  // inserting a second row that would trip referral_records_tenant_code_unique.
  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "conversion",
    payload: {
      email: "friend@example.com",
      advocateEmail: "advocate@example.com",
      referralCode: "merge-code",
      orderId: "order-1",
      createdOn: "2024-05-02T00:00:00.000Z",
    },
  });

  const rowsForCode = supabase.referralRecords.filter((r) =>
    r.referral_code === "merge-code"
  );
  assertEquals(rowsForCode.length, 1);
  assertEquals(rowsForCode[0].friend_email, "friend@example.com");
  assertEquals(rowsForCode[0].status, "conversion_recorded");
});

Deno.test("upsertFriendbuyReferralSnapshot recovers from a concurrent-insert 23505 by updating the row that won", async () => {
  // A row for this code already exists (as if a parallel reconcile sync or the
  // webhook created it), but we hide it from the first lookup to reproduce the
  // TOCTOU window: our existence check misses it, our insert then hits 23505.
  const supabase = createSupabaseMock({
    referralRecords: [{
      id: "referral-winner-1",
      tenant_id: tenantId,
      referral_code: "race-code",
      friend_email: "friend@example.com",
      status: "clicked",
    }],
    hideReferralLookupOnce: "race-code",
  });

  const returnedId = await upsertFriendbuyReferralSnapshot(supabase.client, {
    tenantId,
    snapshot: {
      referralCode: "race-code",
      friendEmail: "friend@example.com",
      status: "purchased",
      occurredAt: "2024-05-03T00:00:00.000Z",
    },
    rawPayload: {},
  });

  // Recovered onto the existing row — no duplicate, no dropped snapshot.
  assertEquals(returnedId, "referral-winner-1");
  const rowsForCode = supabase.referralRecords.filter((r) =>
    r.referral_code === "race-code"
  );
  assertEquals(rowsForCode.length, 1);
  assertEquals(rowsForCode[0].status, "purchased");
});

async function withLedgerFetchMock<T>(
  ledger: { status: number; body: unknown },
  handler: (calls: FetchCall[]) => Promise<T>,
): Promise<T> {
  const originalFetch = globalThis.fetch;
  const calls: FetchCall[] = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    const url = input instanceof Request ? input.url : String(input);
    const method = init?.method || "GET";
    calls.push({ url, method, body: init?.body ? String(init.body) : null });

    if (url.endsWith("/authorization")) {
      return Promise.resolve(
        new Response(JSON.stringify({ token: "friendbuy-token" }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );
    }

    return Promise.resolve(
      new Response(JSON.stringify(ledger.body), {
        status: ledger.status,
        headers: { "Content-Type": "application/json" },
      }),
    );
  }) as typeof fetch;

  try {
    return await handler(calls);
  } finally {
    globalThis.fetch = originalFetch;
  }
}

Deno.test("getFriendbuyLedgerBalance converts Friendbuy's dollar total into cents", async () => {
  const supabase = createSupabaseMock();
  await withLedgerFetchMock(
    { status: 200, body: { currency: "usd", total: 12.5, creditDetails: [] } },
    async (calls) => {
      const result = await getFriendbuyLedgerBalance(supabase.client, {
        tenantId,
        customerId: "patient-1",
        currency: "USD",
      });
      assertEquals(result, { availableCents: 1250, currency: "USD" });

      // The ledger balance is a GET keyed on customerId + currency.
      const ledgerCall = calls.find((c) => c.url.includes("/ledger-balance"));
      assertEquals(ledgerCall?.method, "GET");
      assertEquals(ledgerCall?.url.includes("customerId=patient-1"), true);
      assertEquals(ledgerCall?.url.includes("currency=USD"), true);
    },
  );
});

Deno.test("getFriendbuyLedgerBalance treats a 404 (no ledger for customer) as null", async () => {
  const supabase = createSupabaseMock();
  await withLedgerFetchMock(
    {
      status: 404,
      body: { error: "Not found", message: "No ledger found for customer" },
    },
    async () => {
      const result = await getFriendbuyLedgerBalance(supabase.client, {
        tenantId,
        customerId: "patient-1",
        currency: "USD",
      });
      assertEquals(result, null);
    },
  );
});

Deno.test("getFriendbuyLedgerBalance clamps a non-positive total to zero cents", async () => {
  const supabase = createSupabaseMock();
  await withLedgerFetchMock(
    { status: 200, body: { currency: "USD", total: 0 } },
    async () => {
      const result = await getFriendbuyLedgerBalance(supabase.client, {
        tenantId,
        customerId: "patient-1",
      });
      assertEquals(result, { availableCents: 0, currency: "USD" });
    },
  );
});

Deno.test("getFriendbuyLedgerBalance returns null without calling Friendbuy when customerId is missing", async () => {
  const supabase = createSupabaseMock();
  await withLedgerFetchMock(
    { status: 200, body: { total: 10 } },
    async (calls) => {
      const result = await getFriendbuyLedgerBalance(supabase.client, {
        tenantId,
        customerId: "",
      });
      assertEquals(result, null);
      assertEquals(calls.length, 0);
    },
  );
});

Deno.test("getFriendbuyLedgerBalance returns null when the Friendbuy integration is not configured", async () => {
  const supabase = createSupabaseMock({ integrationEnabled: false });
  await withLedgerFetchMock(
    { status: 200, body: { total: 10 } },
    async (calls) => {
      const result = await getFriendbuyLedgerBalance(supabase.client, {
        tenantId,
        customerId: "patient-1",
      });
      assertEquals(result, null);
      // No authorization or ledger call should be attempted.
      assertEquals(calls.length, 0);
    },
  );
});

Deno.test("friendIncentive webhook stores the friend couponCode in friend_coupon_code and merges into the existing referral row by identity", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [{
      id: "referral-existing-1",
      tenant_id: tenantId,
      referral_code: "brelloadvocate",
      friendbuy_campaign_id: "campaign-1",
      referrer_email: "advocate@example.com",
      friend_email: "friend@example.com",
      status: "conversion_recorded",
    }],
  });

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "friendIncentive",
    eventId: "friend-incentive-1",
    payload: {
      data: [{
        rewardId: "reward-99",
        emailAddress: "friend@example.com",
        advocateEmailAddress: "advocate@example.com",
        couponCode: "frndl4ztgs0699",
        campaignId: "campaign-1",
        createdOn: "2030-03-03T00:00:00.000Z",
      }],
    },
  });

  // Merged into the existing referralCode-keyed row (no orphan), and the
  // friend's minted coupon landed in friend_coupon_code — referral_code intact.
  assertEquals(supabase.referralRecords.length, 1);
  const row = supabase.referralRecords[0];
  assertEquals(row.id, "referral-existing-1");
  assertEquals(row.referral_code, "brelloadvocate");
  assertEquals(row.friend_coupon_code, "frndl4ztgs0699");
});

Deno.test("advocateReward webhook merges into the existing referral row by identity instead of orphaning", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [{
      id: "referral-existing-1",
      tenant_id: tenantId,
      referral_code: "brelloadvocate",
      friendbuy_campaign_id: "campaign-1",
      referrer_email: "advocate@example.com",
      friend_email: "friend@example.com",
      status: "conversion_recorded",
      reward_status: "not_earned",
    }],
  });

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "advocateReward",
    eventId: "advocate-reward-1",
    payload: {
      data: [{
        rewardId: "reward-1",
        emailAddress: "advocate@example.com",
        couponCode: "advocate-credit-code",
        campaignId: "campaign-1",
        createdOn: "2030-03-04T00:00:00.000Z",
        friends: [{ friendEmailAddress: "friend@example.com" }],
      }],
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  const row = supabase.referralRecords[0];
  assertEquals(row.id, "referral-existing-1");
  assertEquals(row.referral_code, "brelloadvocate");
  // Advocate's reward coupon is never written to referral_code/friend_coupon_code.
  assertEquals(row.friend_coupon_code ?? null, null);
  assertEquals(row.reward_status, "pending_eligibility");
});

Deno.test("emailCapture splits the advocate referral code and the friend incentive coupon into separate columns", async () => {
  const supabase = createSupabaseMock();

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_webhook",
    eventType: "emailCapture",
    eventId: "email-capture-split-1",
    payload: {
      data: [{
        emailAddress: "friend@example.com",
        createdOn: "2030-03-05T00:00:00.000Z",
        advocate: { email: "advocate@example.com" },
        referral: { code: "brelloadvocate" },
        incentive: { couponCode: "frnd-incentive-1" },
        campaign: { id: "campaign-abc" },
      }],
    },
  });

  assertEquals(supabase.referralRecords.length, 1);
  const row = supabase.referralRecords[0];
  assertEquals(row.referral_code, "brelloadvocate");
  assertEquals(row.friend_coupon_code, "frnd-incentive-1");
});

Deno.test("coupon_status reconcile matches an existing row by friend_coupon_code without overwriting referral_code", async () => {
  const supabase = createSupabaseMock({
    referralRecords: [{
      id: "referral-existing-1",
      tenant_id: tenantId,
      referral_code: "ref-code-1",
      friend_coupon_code: "SAVE25",
      referrer_email: "advocate@example.com",
      friend_email: "friend@example.com",
    }],
  });

  await ingestFriendbuySyncPayload(supabase.client, {
    tenantId,
    source: "friendbuy_api",
    eventType: "coupon_status",
    payload: {
      code: "SAVE25",
      status: "redeemed",
      distributedOn: "2024-03-26T22:48:00.823Z",
      redeemedOn: "2024-03-26T22:48:47.207Z",
    },
  });

  // Matched the existing row by its friend coupon code — no orphan insert, and
  // the advocate's referral_code is left intact (not clobbered with the coupon).
  assertEquals(supabase.referralRecords.length, 1);
  const row = supabase.referralRecords[0];
  assertEquals(row.referral_code, "ref-code-1");
  assertEquals(row.friend_coupon_code, "SAVE25");
  assertEquals(row.coupon_status, "redeemed");
  assertEquals(row.validity_status, "redeemed");
});

Deno.test("trackFriendbuyPurchaseForOrder records the purchaser as friend without hardcoding redemption and links the sync event", async () => {
  const supabase = createSupabaseMock({
    orders: [{
      id: "order-1",
      tenant_id: tenantId,
      order_number: "1001",
      total_cents: 5000,
      coupon_code: "ref-code-1",
      metadata: {},
      patients: {
        id: "patient-9",
        email: "buyer@example.com",
        first_name: "Buy",
        last_name: "Er",
        metadata: {},
      },
      products: { id: "prod-1", name: "Thing", sku: "SKU1", price_cents: 5000 },
    }],
    referralRecords: [{
      id: "referral-existing-1",
      tenant_id: tenantId,
      referral_code: "ref-code-1",
      referrer_email: "advocate@example.com",
    }],
  });

  await withFriendbuyFetchMock(async () => {
    await trackFriendbuyPurchaseForOrder(supabase.client, {
      tenantId,
      orderId: "order-1",
    });
  });

  const row = supabase.referralRecords.find((r) =>
    r.referral_code === "ref-code-1"
  );
  assertEquals(row?.friend_email, "buyer@example.com");
  // Referrer preserved on the matched row (not wiped by the purchase update).
  assertEquals(row?.referrer_email, "advocate@example.com");
  // Redemption is no longer assumed at purchase time — reconcile owns it.
  assertEquals(row?.validity_status ?? null, null);
  assertEquals(row?.redemption_count ?? null, null);
  // The nexus sync event is linked to the referral row it touched.
  const linked = supabase.referralSyncEvents.find((e) =>
    e.source_event_type === "nexus_purchase_link"
  );
  assertEquals(linked?.referral_record_id, "referral-existing-1");
});

