import { assertEquals } from "../_test/assert.ts";
import {
  cancelLinkedPlanForProviderRejectedOrder,
  type OrderForPendingCancellation,
  shouldCancelMdiCaseForLifecycle,
  shouldCancelTelegraOrderForLifecycle,
} from "./cancel-helper.ts";

const providerRejectedOrder: OrderForPendingCancellation = {
  id: "order_1",
  order_number: "1001",
  tenant_id: "tenant_1",
  patient_id: "patient_1",
  subscription_id: "plan_1",
  status_id: "status_1",
  total_cents: 9900,
  internal_notes: null,
  cancellation_reason: null,
  provider_platform_integration_key: null,
  order_statuses: {
    status_key: "provider_rejected",
    admin_status_label: "Provider Rejected",
  },
};

function createSupabaseMock(params: {
  plan:
    | { id: string; status: string | null; cancelled_at: string | null }
    | null;
  planError?: { message: string } | null;
  updateError?: { message: string } | null;
}) {
  const updates: Array<Record<string, unknown>> = [];

  const supabase = {
    from(table: string) {
      assertEquals(table, "subscriptions");

      return {
        select(_columns: string) {
          return {
            eq(_column: string, _value: string) {
              return this;
            },
            maybeSingle() {
              return Promise.resolve({
                data: params.plan,
                error: params.planError || null,
              });
            },
          };
        },
        update(payload: Record<string, unknown>) {
          updates.push(payload);

          return {
            error: params.updateError || null,
            eq(_column: string, _value: string) {
              return this;
            },
          };
        },
      };
    },
  };

  return { supabase, updates };
}

Deno.test("cancelLinkedPlanForProviderRejectedOrder cancels linked active plan", async () => {
  const { supabase, updates } = createSupabaseMock({
    plan: { id: "plan_1", status: "active", cancelled_at: null },
  });

  const result = await cancelLinkedPlanForProviderRejectedOrder({
    supabase,
    order: providerRejectedOrder,
    requestId: "request_1",
  });

  assertEquals(result.updated, true);
  assertEquals(result.planId, "plan_1");
  assertEquals(updates.length, 1);
  assertEquals(updates[0].status, "cancelled");
  assertEquals(typeof updates[0].cancelled_at, "string");
  assertEquals(updates[0].cancellation_reason, null);
});

Deno.test("cancelLinkedPlanForProviderRejectedOrder is idempotent for cancelled plan", async () => {
  const cancelledAt = "2026-05-20T10:00:00.000Z";
  const { supabase, updates } = createSupabaseMock({
    plan: { id: "plan_1", status: "cancelled", cancelled_at: cancelledAt },
  });

  const result = await cancelLinkedPlanForProviderRejectedOrder({
    supabase,
    order: providerRejectedOrder,
  });

  assertEquals(result.updated, false);
  assertEquals(result.planId, "plan_1");
  assertEquals(result.cancelledAt, cancelledAt);
  assertEquals(updates.length, 0);
});

Deno.test("cancelLinkedPlanForProviderRejectedOrder skips orders without a linked plan", async () => {
  const { supabase, updates } = createSupabaseMock({
    plan: { id: "plan_1", status: "active", cancelled_at: null },
  });

  const result = await cancelLinkedPlanForProviderRejectedOrder({
    supabase,
    order: { ...providerRejectedOrder, subscription_id: null },
  });

  assertEquals(result.updated, false);
  assertEquals(result.planId, null);
  assertEquals(updates.length, 0);
});

Deno.test("shouldCancelMdiCaseForLifecycle includes held questionnaire cases", () => {
  assertEquals(
    shouldCancelMdiCaseForLifecycle({
      shouldCancelProviderOrder: false,
      previousStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "md_integrations",
    }),
    true,
  );

  assertEquals(
    shouldCancelMdiCaseForLifecycle({
      shouldCancelProviderOrder: false,
      previousStatusKey: "patient_questionnaire_pending",
      providerPlatformIntegrationKey: "md_integrations",
    }),
    true,
  );
});

Deno.test("shouldCancelMdiCaseForLifecycle does not change non-MDI provider cancellation", () => {
  assertEquals(
    shouldCancelMdiCaseForLifecycle({
      shouldCancelProviderOrder: false,
      previousStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "telegramd",
    }),
    false,
  );
});

Deno.test("shouldCancelTelegraOrderForLifecycle includes questionnaire-pending orders", () => {
  assertEquals(
    shouldCancelTelegraOrderForLifecycle({
      shouldCancelProviderOrder: false,
      previousStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "telegramd",
    }),
    true,
  );

  assertEquals(
    shouldCancelTelegraOrderForLifecycle({
      shouldCancelProviderOrder: false,
      previousStatusKey: "patient_questionnaire_pending",
      providerPlatformIntegrationKey: "telegramd",
    }),
    true,
  );
});

Deno.test("shouldCancelTelegraOrderForLifecycle does not change non-Telegra provider cancellation", () => {
  assertEquals(
    shouldCancelTelegraOrderForLifecycle({
      shouldCancelProviderOrder: false,
      previousStatusKey: "medical_questionnaire_pending",
      providerPlatformIntegrationKey: "md_integrations",
    }),
    false,
  );
});
