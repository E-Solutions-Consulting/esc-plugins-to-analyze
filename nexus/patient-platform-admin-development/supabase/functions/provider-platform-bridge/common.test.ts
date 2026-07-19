import { assertEquals } from "../_test/assert.ts";
import {
  advanceOrderToNextStatus,
  applyProviderLegalAgreementToAgreementQuestions,
  parseUpdatePatientProfileBody,
  QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
} from "./common.ts";

Deno.test("applyProviderLegalAgreementToAgreementQuestions decorates nested agreement questions", () => {
  const questionnaire = {
    id: "questionnaire-1",
    sections: [
      {
        questions: [
          { id: "question-1", type: "text", label: "Name" },
          { id: "question-2", type: "agreement", label: "Consent" },
        ],
      },
    ],
  };

  const decorated = applyProviderLegalAgreementToAgreementQuestions(
    questionnaire,
    "<p>Provider agreement</p>",
  );

  assertEquals(decorated, {
    id: "questionnaire-1",
    sections: [
      {
        questions: [
          { id: "question-1", type: "text", label: "Name" },
          {
            id: "question-2",
            type: "agreement",
            label: "Consent",
            provider_legal_agreement: "<p>Provider agreement</p>",
          },
        ],
      },
    ],
  });
});

Deno.test("parseUpdatePatientProfileBody accepts wrapped patientData payloads", async () => {
  const req = new Request("https://example.com", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      patientData: {
        dateOfBirth: "1990-11-07",
      },
    }),
  });

  const parsed = await parseUpdatePatientProfileBody(req);

  assertEquals(parsed.patientData, {
    dateOfBirth: "1990-11-07",
  });
});

Deno.test("parseUpdatePatientProfileBody accepts raw patient profile payloads", async () => {
  const req = new Request("https://example.com", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      symptoms: ["symp::1", "None of the above"],
      birth_date: "1990-11-07",
    }),
  });

  const parsed = await parseUpdatePatientProfileBody(req);

  assertEquals(parsed.patientData, {
    symptoms: ["symp::1", "None of the above"],
    birth_date: "1990-11-07",
  });
});

Deno.test("advanceOrderToNextStatus uses the latest persisted order status before advancing", async () => {
  const updates: Array<Record<string, unknown>> = [];
  const historyInserts: Array<Record<string, unknown>> = [];

  const staleOrder = {
    id: "order-1",
    tenant_id: "tenant-1",
    patient_id: "patient-1",
    product_id: "product-1",
    status_id: "status-patient-questionnaire-pending",
    subscription_order_type: "initial",
    provider_platform_integration_key: "md_integrations",
    order_statuses: {
      id: "status-patient-questionnaire-pending",
      status_key: "patient_questionnaire_pending",
      admin_status_label: "Patient Questionnaire Pending",
      display_order: 10,
      is_terminal: false,
      next_status_id: "status-medical-questionnaire-pending",
    },
  };

  const latestOrder = {
    ...staleOrder,
    status_id: "status-medical-questionnaire-pending",
    order_statuses: {
      id: "status-medical-questionnaire-pending",
      status_key: "medical_questionnaire_pending",
      admin_status_label: "Medical Questionnaire Pending",
      display_order: 20,
      is_terminal: false,
      next_status_id: "status-provider-review-pending",
    },
  };

  const nextStatus = {
    id: "status-provider-review-pending",
    status_key: "provider_review_pending",
    admin_status_label: "Provider Review Pending",
    display_order: 30,
    is_terminal: false,
    next_status_id: null,
  };

  const supabase = {
    from(table: string) {
      if (table === "orders") {
        return {
          select() {
            return {
              eq(_column: string, _value: string) {
                return {
                  maybeSingle: async () => ({
                    data: latestOrder,
                    error: null,
                  }),
                };
              },
            };
          },
          update(payload: Record<string, unknown>) {
            updates.push(payload);
            return {
              eq(_column: string, _value: string) {
                return {
                  eq: async (_innerColumn: string, _innerValue: string) => ({
                    error: null,
                  }),
                };
              },
            };
          },
        };
      }

      if (table === "order_statuses") {
        return {
          select() {
            return {
              eq(_column: string, _value: string) {
                return {
                  eq(_innerColumn: string, _innerValue: boolean) {
                    return {
                      maybeSingle: async () => ({
                        data: nextStatus,
                        error: null,
                      }),
                    };
                  },
                };
              },
            };
          },
        };
      }

      if (table === "order_status_history") {
        return {
          insert(payload: Record<string, unknown>) {
            historyInserts.push(payload);
            return Promise.resolve({ error: null });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  const result = await advanceOrderToNextStatus({
    supabase: supabase as never,
    order: staleOrder,
    note: "MDI medical questions have been submitted.",
  });

  assertEquals(result, {
    advanced: true,
    previousStatusKey: "medical_questionnaire_pending",
    newStatusKey: "provider_review_pending",
  });
  assertEquals(updates.length, 1);
  assertEquals(updates[0].status_id, "status-provider-review-pending");
  assertEquals(historyInserts, [{
    order_id: "order-1",
    status_id: "status-provider-review-pending",
    notes: "MDI medical questions have been submitted.",
  }]);
});

Deno.test("advanceOrderToNextStatus does NOT advance past the provider-review gate from a questionnaire submission", async () => {
  // Regression for the self-approval bug: a returning patient's questionnaire was
  // reused so the order had already advanced to provider_review_pending. A late
  // questionnaire-submission must NOT push it to provider_approved (which would
  // trigger payment capture with no real provider decision).
  const updates: Array<Record<string, unknown>> = [];
  const historyInserts: Array<Record<string, unknown>> = [];

  const order = {
    id: "order-2",
    tenant_id: "tenant-1",
    patient_id: "patient-1",
    product_id: "product-1",
    status_id: "status-provider-review-pending",
    subscription_order_type: "initial",
    provider_platform_integration_key: "telegramd",
    order_statuses: {
      id: "status-provider-review-pending",
      status_key: "provider_review_pending",
      admin_status_label: "Provider Review Pending",
      display_order: 30,
      is_terminal: false,
      next_status_id: "status-provider-approved",
    },
  };

  const supabase = {
    from(table: string) {
      if (table === "orders") {
        return {
          select() {
            return {
              eq() {
                return {
                  maybeSingle: async () => ({ data: order, error: null }),
                };
              },
            };
          },
          update(payload: Record<string, unknown>) {
            updates.push(payload);
            return {
              eq() {
                return { eq: async () => ({ error: null }) };
              },
            };
          },
        };
      }
      if (table === "order_status_history") {
        return {
          insert(payload: Record<string, unknown>) {
            historyInserts.push(payload);
            return Promise.resolve({ error: null });
          },
        };
      }
      throw new Error(`Unexpected table ${table}`);
    },
  };

  const result = await advanceOrderToNextStatus({
    supabase: supabase as never,
    order,
    note: "Patient Questionnaire has been submitted.",
    expectedFromStatusKeys: QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
  });

  assertEquals(result.advanced, false);
  assertEquals(result.previousStatusKey, "provider_review_pending");
  assertEquals(result.newStatusKey, null);
  assertEquals(result.skippedReason, "unexpected_status");
  // No DB writes — the order is untouched.
  assertEquals(updates.length, 0);
  assertEquals(historyInserts.length, 0);
});
