import { assertEquals } from "../_test/assert.ts";
import { parseQuestionnaireSubmittedEvent } from "./questionnaire-event.ts";

Deno.test("parseQuestionnaireSubmittedEvent accepts flat RTDH medical questionnaire events", () => {
  const result = parseQuestionnaireSubmittedEvent({
    source: "rtdh",
    event_type: "medical_questionnaire_submitted",
    event_id: "submission_123",
    tenant: "tenant_1",
    occurred_at: "2026-05-26T12:00:00.000Z",
    master_order_id: "master::test",
    patient_platform_order_id: "pp_order_1",
    submissionID: "submission_123",
    payload: {
      patient_platform_order_id: "pp_order_1",
      submissionID: "submission_123",
    },
  });

  assertEquals(result, {
    eventType: "medical_questionnaire_submitted",
    errors: [],
    event: {
      eventType: "medical_questionnaire_submitted",
      questionnaireType: "medical_questionnaire",
      tenantIdentifier: "tenant_1",
      orderId: "pp_order_1",
      submissionId: "submission_123",
    },
  });
});

Deno.test("parseQuestionnaireSubmittedEvent falls back to nested payload fields", () => {
  const result = parseQuestionnaireSubmittedEvent({
    event_type: "patient_questionnaire_submitted",
    tenant: "tenant_1",
    payload: {
      patient_platform_order_id: "pp_order_1",
      submissionID: "submission_123",
    },
  });

  assertEquals(result?.event, {
    eventType: "patient_questionnaire_submitted",
    questionnaireType: "patient_questionnaire",
    tenantIdentifier: "tenant_1",
    orderId: "pp_order_1",
    submissionId: "submission_123",
  });
});

Deno.test("parseQuestionnaireSubmittedEvent accepts RTDH v1 events with nested jotform metadata", () => {
  const result = parseQuestionnaireSubmittedEvent({
    schema_version: "v1",
    master_order_id:
      "master::ZDI1ODMwMGItNmY0Zi00ODJmLThhODgtMjJjNDgyZTZmMWU5::c3RyaXBlX2NoZWNrb3V0X3Nlc3Npb25faWQ::Y3NfdGVzdA",
    internal_tenant_id: "d258300b-6f4f-482f-8a88-22c482e6f1e9",
    ids: {
      patient_platform_order_id: "f384ff3b-1a7a-4390-9ae7-4b638cf45cd6",
      provider_name: "md_integrations",
    },
    jotform: {
      submission_id: "6561396607949750045",
      form_id: "261483071783057",
      form_title: "Patient Questionnaire Form",
      pretty:
        "patient_platform_order_id:f384ff3b-1a7a-4390-9ae7-4b638cf45cd6, questionnaire_type:patient_questionnaire",
    },
    event_type: "medical_questionnaire_submitted",
    questionnaire_type: "medical_questionnaire",
  });

  assertEquals(result, {
    eventType: "medical_questionnaire_submitted",
    errors: [],
    event: {
      eventType: "medical_questionnaire_submitted",
      questionnaireType: "medical_questionnaire",
      tenantIdentifier: "d258300b-6f4f-482f-8a88-22c482e6f1e9",
      orderId: "f384ff3b-1a7a-4390-9ae7-4b638cf45cd6",
      submissionId: "6561396607949750045",
    },
  });
});

Deno.test("parseQuestionnaireSubmittedEvent reports missing required fields", () => {
  const result = parseQuestionnaireSubmittedEvent({
    event_type: "medical_questionnaire_submitted",
  });

  assertEquals(result, {
    eventType: "medical_questionnaire_submitted",
    errors: [
      "tenant must be a non-empty string",
      "patient_platform_order_id must be a non-empty string",
      "submissionID must be a non-empty string",
    ],
    event: null,
  });
});

Deno.test("parseQuestionnaireSubmittedEvent requires explicit patient or medical event type", () => {
  const result = parseQuestionnaireSubmittedEvent({
    event_type: "jotform_questionnaire_submitted",
    tenant: "tenant_1",
    patient_platform_order_id: "pp_order_1",
    submissionID: "submission_123",
  });

  assertEquals(result, {
    eventType: "jotform_questionnaire_submitted",
    errors: [
      "event_type must be patient_questionnaire_submitted or medical_questionnaire_submitted",
    ],
    event: null,
  });
});
