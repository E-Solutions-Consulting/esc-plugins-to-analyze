import { asNonEmptyString, asObject, type JsonObject } from "./validation.ts";

const QUESTIONNAIRE_SUBMITTED_EVENT_TYPES = new Set([
  "jotform_questionnaire_submitted",
  "medical_questionnaire_submitted",
  "patient_questionnaire_submitted",
]);

export type RtdhQuestionnaireType =
  | "patient_questionnaire"
  | "medical_questionnaire";

export interface QuestionnaireSubmittedEvent {
  eventType: string;
  questionnaireType: RtdhQuestionnaireType;
  tenantIdentifier: string;
  orderId: string;
  submissionId: string;
}

export interface QuestionnaireSubmittedParseResult {
  eventType: string;
  errors: string[];
  event: QuestionnaireSubmittedEvent | null;
}

function readFirstString(
  records: Array<JsonObject | null>,
  keys: string[],
): string | null {
  for (const record of records) {
    if (!record) continue;
    for (const key of keys) {
      const value = asNonEmptyString(record[key]);
      if (value) return value;
    }
  }

  return null;
}

function resolveQuestionnaireType(
  eventType: string,
): RtdhQuestionnaireType | null {
  if (eventType === "patient_questionnaire_submitted") {
    return "patient_questionnaire";
  }
  if (eventType === "medical_questionnaire_submitted") {
    return "medical_questionnaire";
  }
  return null;
}

function normalizeQuestionnaireType(
  value: string | null,
): RtdhQuestionnaireType | null {
  if (value === "patient_questionnaire") return "patient_questionnaire";
  if (value === "medical_questionnaire") return "medical_questionnaire";
  return null;
}

export function parseQuestionnaireSubmittedEvent(
  payload: JsonObject,
): QuestionnaireSubmittedParseResult | null {
  const eventType = readFirstString([payload], ["event_type"]);
  if (!eventType || !QUESTIONNAIRE_SUBMITTED_EVENT_TYPES.has(eventType)) {
    return null;
  }

  const nestedPayload = asObject(payload.payload);
  const ids = asObject(payload.ids);
  const jotform = asObject(payload.jotform);
  const records = [payload, nestedPayload, ids, jotform];

  const tenantIdentifier = readFirstString(records, [
    "tenant",
    "internal_tenant_id",
    "tenant_id",
    "tenant_slug",
  ]);
  const orderId = readFirstString(records, [
    "patient_platform_order_id",
    "order_id",
  ]);
  const submissionId = readFirstString(records, [
    "submissionID",
    "submissionId",
    "submission_id",
    "jotform_submission_id",
  ]);

  const errors: string[] = [];
  const questionnaireType = resolveQuestionnaireType(eventType) ??
    normalizeQuestionnaireType(
      readFirstString(records, ["questionnaire_type"]),
    );
  if (!questionnaireType) {
    errors.push(
      "event_type must be patient_questionnaire_submitted or medical_questionnaire_submitted",
    );
  }
  if (!tenantIdentifier) {
    errors.push("tenant must be a non-empty string");
  }
  if (!orderId) {
    errors.push("patient_platform_order_id must be a non-empty string");
  }
  if (!submissionId) {
    errors.push("submissionID must be a non-empty string");
  }

  if (errors.length > 0) {
    return { eventType, errors, event: null };
  }

  return {
    eventType,
    errors: [],
    event: {
      eventType,
      questionnaireType: questionnaireType!,
      tenantIdentifier: tenantIdentifier!,
      orderId: orderId!,
      submissionId: submissionId!,
    },
  };
}
