import {
  buildTelegraAnswerLocationAgreementData,
  buildTelegraAnswerLocationRequestInit,
  buildTelegraConditionsAndSymptomsUrl,
  buildTelegraPatientUrl,
  buildTelegraQuestionnaireAnswerLocationUrl,
  buildTelegraQuestionnaireInstanceUrl,
  buildTelegraQuestionnaireSchemaUrl,
  extractProviderNameFromMetadata,
  extractQuestionnaireInstanceIdsFromMetadata,
  extractTelegraSymptoms,
  getStringSetting,
  isTelegraProviderPlatform,
  populateSymptomsQuestionnaireOptions,
} from "./helpers.ts";
import {
  appendTelegraRequestTimestamp,
  resolveTelegraAccessToken,
} from "../_shared/telegra-auth.ts";
import {
  getJotformSubmissionAnswerByName,
  JOTFORM_PROVIDER_KEY_FIELD_NAME,
  type JotformSubmissionContent,
  type JotformSubmissionQuestionnaireType,
  stringifyJotformAnswer,
} from "./jotform.ts";
import {
  advanceOrderToNextStatus,
  applyProviderLegalAgreementToAgreementQuestions,
  extractConfiguredProductIdsFromProviderProductSku,
  fetchOrderById,
  fetchOrderProviderPlatformLinks,
  fetchPatientById,
  fetchPatientProviderPlatformLink,
  fetchProductProviderPlatform,
  fetchTenantByIdentifier,
  fetchTenantIntegrationById,
  fetchTenantIntegrationForTenantByKey,
  jsonResponse,
  OrderProviderPlatformLinkRow,
  OrderRow,
  parseAnswerLocationBody,
  ParsedAnswerLocationBody,
  parseProductsQueryParam,
  parseRequestedTenantIdentifier,
  parseUpdatePatientProfileBody,
  QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
  SupabaseAdminClient,
  TenantIntegrationRow,
  triggerOrderLifecycleForOrder,
  userHasOrderAccess,
  userHasTenantAccess,
} from "./common.ts";

type JsonRecord = Record<string, unknown>;

function formatTelegraErrorDetail(_responseBody: unknown): string {
  return "";
}

function getHeaderValue(
  headers: HeadersInit | undefined,
  headerName: string,
): string | null {
  if (!headers) return null;

  if (headers instanceof Headers) {
    return headers.get(headerName);
  }

  const normalizedHeaderName = headerName.toLowerCase();
  if (Array.isArray(headers)) {
    const matchingHeader = headers.find(([key]) =>
      key.toLowerCase() === normalizedHeaderName
    );
    return matchingHeader?.[1] ?? null;
  }

  const matchingKey = Object.keys(headers).find((key) =>
    key.toLowerCase() === normalizedHeaderName
  );
  const headerRecord = headers as Record<string, string>;
  return matchingKey ? headerRecord[matchingKey] ?? null : null;
}

function getHeaderEntries(
  headers: HeadersInit | undefined,
): Array<[string, string]> {
  if (!headers) return [];

  if (headers instanceof Headers) {
    return Array.from(headers.entries());
  }

  if (Array.isArray(headers)) {
    return headers.map(([key, value]) => [key, value]);
  }

  return Object.entries(headers as Record<string, string>);
}

function redactHeaderValue(key: string, value: string): string {
  const normalizedKey = key.toLowerCase();
  if (normalizedKey === "authorization") {
    if (value.toLowerCase().startsWith("bearer ")) {
      return "Bearer <redacted>";
    }
    return "<redacted>";
  }

  return value;
}

function shellQuote(value: string): string {
  return `'${value.replace(/'/g, "'\\''")}'`;
}

function formatFormDataValueForCurl(key: string, value: FormDataEntryValue) {
  if (value instanceof File) {
    const fileName = value.name || "<uploaded-file>";
    const fileType = value.type || "application/octet-stream";
    return `${key}=@${fileName};type=${fileType}`;
  }

  return `${key}=${value}`;
}

function buildTelegraCurlCommand(
  endpoint: string,
  requestInit: RequestInit,
): string {
  const method = requestInit.method || "GET";
  const parts = [
    "curl",
    "-i",
    "-X",
    method,
    shellQuote(endpoint),
  ];

  for (const [key, value] of getHeaderEntries(requestInit.headers)) {
    parts.push(
      "-H",
      shellQuote(`${key}: ${redactHeaderValue(key, value)}`),
    );
  }

  const body = requestInit.body;
  if (typeof body === "string") {
    parts.push("--data-raw", shellQuote(body));
  } else if (body instanceof FormData) {
    for (const [key, value] of body.entries()) {
      parts.push("-F", shellQuote(formatFormDataValueForCurl(key, value)));
    }
  } else if (body instanceof URLSearchParams) {
    parts.push("--data-raw", shellQuote(body.toString()));
  } else if (body instanceof File) {
    parts.push(
      "--data-binary",
      shellQuote(`@${body.name || "<uploaded-file>"}`),
    );
  } else if (body instanceof Blob) {
    parts.push("--data-binary", shellQuote("<blob body omitted>"));
  } else if (body) {
    parts.push("--data-binary", shellQuote("<non-string body omitted>"));
  }

  return parts.join(" ");
}

function summarizeUnknownForLog(value: unknown): Record<string, unknown> {
  if (value === null || typeof value === "undefined") {
    return { bodyType: "empty" };
  }

  if (typeof value === "string") {
    return {
      bodyType: "string",
      length: value.length,
    };
  }

  if (Array.isArray(value)) {
    return {
      bodyType: "array",
      length: value.length,
    };
  }

  if (typeof value === "object") {
    const record = value as JsonRecord;
    const summary: JsonRecord = {
      bodyType: "object",
      keys: Object.keys(record).slice(0, 25),
    };

    for (const key of ["message", "error", "detail", "code", "status"]) {
      const entry = record[key];
      if (typeof entry === "string" && entry.trim().length > 0) {
        summary[key] = entry.trim().slice(0, 500);
      } else if (
        typeof entry === "number" || typeof entry === "boolean"
      ) {
        summary[key] = entry;
      }
    }

    return summary;
  }

  return {
    bodyType: typeof value,
    value,
  };
}

function summarizeTelegraPatientPayloadForLog(
  patientData: Record<string, unknown>,
): Record<string, unknown> {
  return {
    bodyType: "object",
    keys: Object.keys(patientData).sort(),
    medicationAllergiesCount: Array.isArray(patientData.medicationAllergies)
      ? patientData.medicationAllergies.length
      : undefined,
    patientMedicationsCount: Array.isArray(patientData.patientMedications)
      ? patientData.patientMedications.length
      : undefined,
    notesCount: Array.isArray(patientData.notes)
      ? patientData.notes.length
      : undefined,
    hasEmail: typeof patientData.email === "string" &&
      patientData.email.length > 0,
    hasPhone: typeof patientData.phone === "string" &&
      patientData.phone.length > 0,
  };
}

function summarizeTelegraAnswerPayloadForLog(
  payload: unknown | File,
): Record<string, unknown> {
  if (payload instanceof File) {
    return {
      payloadType: "file",
      fileType: payload.type || "application/octet-stream",
      fileSize: payload.size,
      hasFileName: payload.name.length > 0,
    };
  }

  if (typeof payload === "string") {
    return {
      payloadType: "string",
      length: payload.length,
    };
  }

  if (Array.isArray(payload)) {
    return {
      payloadType: "array",
      length: payload.length,
    };
  }

  if (payload && typeof payload === "object") {
    return {
      payloadType: "object",
      keys: Object.keys(payload as JsonRecord).slice(0, 25),
    };
  }

  return {
    payloadType: typeof payload,
  };
}

async function parseTelegraResponseBody(
  response: Response,
): Promise<{ rawResponse: string; responseBody: unknown }> {
  const rawResponse = await response.text();
  let responseBody: unknown = null;

  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  return { rawResponse, responseBody };
}

async function fetchTelegraApi(params: {
  operation: string;
  endpoint: string;
  requestInit: RequestInit;
  requestId: string;
  metadata?: Record<string, unknown>;
  requestBodySummary?: Record<string, unknown>;
}): Promise<{ response: Response; responseBody: unknown }> {
  const {
    operation,
    endpoint,
    requestInit,
    requestId,
    metadata,
    requestBodySummary,
  } = params;
  const method = requestInit.method || "GET";
  const timestampedEndpoint = appendTelegraRequestTimestamp(endpoint);
  const startedAt = Date.now();
  const curl = buildTelegraCurlCommand(timestampedEndpoint, requestInit);

  console.info("Telegra API request started", {
    requestId,
    operation,
    method,
    endpoint: timestampedEndpoint,
    curl,
    contentType: getHeaderValue(requestInit.headers, "Content-Type"),
    hasBody: typeof requestInit.body !== "undefined" &&
      requestInit.body !== null,
    requestBodySummary,
    ...metadata,
  });

  let response: Response;
  try {
    response = await fetch(timestampedEndpoint, requestInit);
  } catch (error) {
    console.error("Telegra API request failed before response", {
      requestId,
      operation,
      method,
      endpoint: timestampedEndpoint,
      curl,
      elapsedMs: Date.now() - startedAt,
      error: error instanceof Error ? error.message : String(error),
      ...metadata,
    });
    throw error;
  }

  const { rawResponse, responseBody } = await parseTelegraResponseBody(
    response,
  );
  const responseLog = {
    requestId,
    operation,
    method,
    endpoint: timestampedEndpoint,
    curl,
    status: response.status,
    statusText: response.statusText,
    ok: response.ok,
    elapsedMs: Date.now() - startedAt,
    responseContentType: response.headers.get("Content-Type"),
    responseSize: rawResponse.length,
    ...metadata,
  };

  if (response.ok) {
    console.info("Telegra API response received", responseLog);
  } else {
    console.warn("Telegra API error response received", {
      ...responseLog,
      responseBodySummary: summarizeUnknownForLog(responseBody),
    });
  }

  return { response, responseBody };
}

interface ResolveTelegraProductVariationRequestBody {
  tenantIntegrationId?: string;
  tenant_integration_id?: string;
  productVariationId?: string;
  product_variation_id?: string;
}

function summarizeTelegraAnswerLocationPayload(
  payload: unknown | File,
): Record<string, unknown> {
  if (payload instanceof File) {
    return {
      payloadType: "file",
      fileName: payload.name,
      fileType: payload.type || "application/octet-stream",
      fileSize: payload.size,
    };
  }

  if (typeof payload === "string") {
    return {
      payloadType: "string",
      preview: payload.slice(0, 120),
      length: payload.length,
    };
  }

  if (Array.isArray(payload)) {
    return {
      payloadType: "string[]",
      length: payload.length,
      preview: payload.slice(0, 10),
    };
  }

  return {
    payloadType: typeof payload,
    payload,
  };
}

function parseNonEmptyString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0
    ? value.trim()
    : null;
}

function encodeBytesAsBase64(bytes: Uint8Array): string {
  let binary = "";
  const chunkSize = 0x8000;

  for (let index = 0; index < bytes.length; index += chunkSize) {
    const chunk = bytes.subarray(index, index + chunkSize);
    binary += String.fromCharCode(...chunk);
  }

  return btoa(binary);
}

async function resolveTelegraAnswerSignature(
  value: string | string[] | File,
): Promise<string> {
  if (value instanceof File) {
    const bytes = new Uint8Array(await value.arrayBuffer());
    const mimeType = value.type || "application/octet-stream";
    return `data:${mimeType};base64,${encodeBytesAsBase64(bytes)}`;
  }

  if (typeof value === "string") {
    return value;
  }

  return value[0] || "";
}

function parseStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) return [];
  return value
    .map((entry) => parseNonEmptyString(entry))
    .filter((entry): entry is string => entry !== null);
}

function parseRecordArray(value: unknown): JsonRecord[] {
  if (!Array.isArray(value)) return [];
  return value.filter((entry): entry is JsonRecord =>
    !!entry && typeof entry === "object" && !Array.isArray(entry)
  );
}

function parseBooleanYes(value: unknown): boolean {
  const normalized = parseNonEmptyString(value)?.toLowerCase();
  return normalized === "yes" || normalized === "true";
}

function parseDateOnlyToIso(value: unknown): string | null {
  const trimmed = parseNonEmptyString(value);
  if (!trimmed) return null;
  const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!match) return null;
  const [, year, month, day] = match;
  return new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)))
    .toISOString();
}

function roundToTwo(value: number): number {
  return Math.round(value * 100) / 100;
}

export function parseWeightToPounds(value: unknown): number | null {
  const trimmed = parseNonEmptyString(value);
  if (!trimmed) return null;
  const match = trimmed.match(/(-?\d+(?:\.\d+)?)/);
  if (!match) return null;
  const numericValue = Number(match[1]);
  if (!Number.isFinite(numericValue)) return null;
  const normalized = trimmed.toLowerCase();
  if (normalized.includes("kg")) {
    return roundToTwo(numericValue * 2.20462);
  }
  return roundToTwo(numericValue);
}

export function parseHeightToInches(value: unknown): number | null {
  const trimmed = parseNonEmptyString(value);
  if (!trimmed) return null;
  const normalized = trimmed.toLowerCase();
  const numericMatches = Array.from(
    trimmed.matchAll(/(-?\d+(?:\.\d+)?)/g),
    (match) => Number(match[1]),
  ).filter((entry) => Number.isFinite(entry));

  if (numericMatches.length === 0) return null;

  if (normalized.includes("cm")) {
    return roundToTwo(numericMatches[0] / 2.54);
  }

  if (normalized.includes("ft")) {
    const feet = numericMatches[0];
    const inches = numericMatches[1] ?? 0;
    return roundToTwo(feet * 12 + inches);
  }

  return roundToTwo(numericMatches[0]);
}

function mapSymptomsToNotes(params: {
  symptomValues: string[];
  symptomsById: Map<string, string>;
  otherSymptoms: string | null;
}): string[] {
  const { symptomValues, symptomsById, otherSymptoms } = params;
  const notes: string[] = [];
  const seen = new Set<string>();

  for (const symptomValue of symptomValues) {
    const note = symptomsById.get(symptomValue) || symptomValue;
    if (!seen.has(note)) {
      notes.push(note);
      seen.add(note);
    }
  }

  if (otherSymptoms) {
    const otherNote =
      `Other symptoms not listed in previous screen: ${otherSymptoms}`;
    if (!seen.has(otherNote)) {
      notes.push(otherNote);
    }
  }

  return notes;
}

function getJotformAnswerValueByName(
  submission: JotformSubmissionContent,
  fieldName: string,
): unknown {
  return getJotformSubmissionAnswerByName(submission, fieldName)?.answer;
}

function getJotformAnswerStringByName(
  submission: JotformSubmissionContent,
  fieldName: string,
): string | null {
  const value = getJotformAnswerValueByName(submission, fieldName);
  const answerText = stringifyJotformAnswer(value);
  return answerText.length > 0 ? answerText : null;
}

function parseJotformJsonAnswer(value: unknown): unknown {
  if (typeof value !== "string") return value;
  const trimmed = value.trim();
  if (!/^[{[]/.test(trimmed)) return value;

  try {
    return JSON.parse(trimmed) as unknown;
  } catch {
    return value;
  }
}

function normalizeJotformAnswerText(value: unknown): string | null {
  const answerText = stringifyJotformAnswer(value);
  return answerText.length > 0 ? answerText : null;
}

function splitJotformFreeTextList(value: string): string[] {
  return value
    .split(/\r?\n|;|,/)
    .map((entry) => entry.trim())
    .filter((entry) => entry.length > 0);
}

function normalizeJotformStringArrayAnswer(value: unknown): string[] {
  const parsedValue = parseJotformJsonAnswer(value);

  if (Array.isArray(parsedValue)) {
    return parsedValue.flatMap((entry) =>
      normalizeJotformStringArrayAnswer(entry)
    );
  }

  if (parsedValue && typeof parsedValue === "object") {
    return Object.values(parsedValue as JsonRecord).flatMap((entry) =>
      normalizeJotformStringArrayAnswer(entry)
    );
  }

  const answerText = normalizeJotformAnswerText(parsedValue);
  return answerText ? splitJotformFreeTextList(answerText) : [];
}

function parseJotformIntegerPart(value: unknown): number | null {
  if (typeof value === "number" && Number.isInteger(value)) return value;
  const trimmed = typeof value === "string" ? value.trim() : String(value ?? "")
    .trim();
  if (!/^\d+$/.test(trimmed)) return null;
  const parsed = Number(trimmed);
  return Number.isInteger(parsed) ? parsed : null;
}

function formatJotformDateOnly(
  yearValue: unknown,
  monthValue: unknown,
  dayValue: unknown,
): string | null {
  const year = parseJotformIntegerPart(yearValue);
  const month = parseJotformIntegerPart(monthValue);
  const day = parseJotformIntegerPart(dayValue);

  if (
    year === null || month === null || day === null ||
    year < 1000 || year > 9999 ||
    month < 1 || month > 12 ||
    day < 1 || day > 31
  ) {
    return null;
  }

  const date = new Date(Date.UTC(year, month - 1, day));
  if (
    date.getUTCFullYear() !== year ||
    date.getUTCMonth() !== month - 1 ||
    date.getUTCDate() !== day
  ) {
    return null;
  }

  return [
    String(year).padStart(4, "0"),
    String(month).padStart(2, "0"),
    String(day).padStart(2, "0"),
  ].join("-");
}

function normalizeJotformDateAnswer(value: unknown): string | null {
  const parsedValue = parseJotformJsonAnswer(value);

  if (
    parsedValue && typeof parsedValue === "object" &&
    !Array.isArray(parsedValue)
  ) {
    const record = parsedValue as JsonRecord;
    const fromParts = formatJotformDateOnly(
      record.year,
      record.month,
      record.day,
    );
    if (fromParts) return fromParts;
  }

  const answerText = normalizeJotformAnswerText(parsedValue);
  if (!answerText) return null;

  const dateToken = answerText.split(/\s+/)[0] ?? answerText;
  const yearFirst = dateToken.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
  if (yearFirst) {
    return formatJotformDateOnly(yearFirst[1], yearFirst[2], yearFirst[3]);
  }

  const dayFirst = dateToken.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
  if (dayFirst) {
    return formatJotformDateOnly(dayFirst[3], dayFirst[2], dayFirst[1]);
  }

  return null;
}

function normalizeJotformYesAnswer(value: unknown): string | null {
  const answerText = normalizeJotformAnswerText(value);
  if (!answerText) return null;
  return /^yes\b/i.test(answerText) || /^true$/i.test(answerText)
    ? "Yes"
    : answerText;
}

function normalizeJotformGenderAnswer(value: unknown): string | null {
  const answerText = normalizeJotformAnswerText(value);
  if (!answerText) return null;

  const normalized = answerText.trim().toLowerCase();
  if (normalized.startsWith("male")) return "male";
  if (normalized.startsWith("female")) return "female";
  return answerText;
}

function buildJotformMeasuredAnswer(params: {
  submission: JotformSubmissionContent;
  valueFieldName: string;
  unitFieldName: string;
}): string | null {
  const value = getJotformAnswerStringByName(
    params.submission,
    params.valueFieldName,
  );
  if (!value) return null;

  const unit = getJotformAnswerStringByName(
    params.submission,
    params.unitFieldName,
  );

  return unit ? `${value} ${unit}` : value;
}

function normalizeJotformMedicationRecord(value: unknown): JsonRecord | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    const text = normalizeJotformAnswerText(value);
    return text ? { medication_name: text } : null;
  }

  const record = value as JsonRecord;
  const medicationName = parseNonEmptyString(record.medication_name) ||
    parseNonEmptyString(record["Medication name"]) ||
    parseNonEmptyString(record.medication) ||
    parseNonEmptyString(record.Medication) ||
    parseNonEmptyString(record.name) ||
    parseNonEmptyString(record.value) ||
    normalizeJotformAnswerText(record);

  if (!medicationName) return null;

  return {
    medication_name: medicationName,
    dosage: parseNonEmptyString(record.dosage) ||
      parseNonEmptyString(record.Dosage) || "",
    frequency: parseNonEmptyString(record.frequency) ||
      parseNonEmptyString(record.Frequency) || "",
    Condition_threated: parseNonEmptyString(record.Condition_threated) ||
      parseNonEmptyString(record["Condition treated"]) ||
      parseNonEmptyString(record.conditionPrescribed) ||
      parseNonEmptyString(record.condition_treated) ||
      parseNonEmptyString(record.Condition) || "",
  };
}

function normalizeJotformAllergyRecord(value: unknown): JsonRecord | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    const text = normalizeJotformAnswerText(value);
    return text ? { Medication: text } : null;
  }

  const record = value as JsonRecord;
  const medication = parseNonEmptyString(record.Medication) ||
    parseNonEmptyString(record.medicationAllergies) ||
    parseNonEmptyString(record.medication) ||
    parseNonEmptyString(record.allergy) ||
    parseNonEmptyString(record.name) ||
    parseNonEmptyString(record.value) ||
    normalizeJotformAnswerText(record);

  if (!medication) return null;

  return {
    Medication: medication,
    Reaction: parseNonEmptyString(record.Reaction) ||
      parseNonEmptyString(record.reaction) || "",
  };
}

function normalizeJotformRecordArrayAnswer(
  value: unknown,
  normalizeRecord: (entry: unknown) => JsonRecord | null,
): JsonRecord[] {
  const parsedValue = parseJotformJsonAnswer(value);

  if (Array.isArray(parsedValue)) {
    return parsedValue
      .flatMap((entry) =>
        Array.isArray(entry)
          ? normalizeJotformRecordArrayAnswer(entry, normalizeRecord)
          : [normalizeRecord(entry)]
      )
      .filter((entry): entry is JsonRecord => entry !== null);
  }

  if (parsedValue && typeof parsedValue === "object") {
    const record = parsedValue as JsonRecord;
    const nestedRecords = Object.values(record).filter((entry) =>
      !!entry && typeof entry === "object"
    );

    if (
      nestedRecords.length > 0 &&
      nestedRecords.length === Object.keys(record).length
    ) {
      return nestedRecords
        .map((entry) => normalizeRecord(entry))
        .filter((entry): entry is JsonRecord => entry !== null);
    }

    const normalizedRecord = normalizeRecord(record);
    return normalizedRecord ? [normalizedRecord] : [];
  }

  const answerText = normalizeJotformAnswerText(parsedValue);
  return answerText
    ? splitJotformFreeTextList(answerText)
      .map((entry) => normalizeRecord(entry))
      .filter((entry): entry is JsonRecord => entry !== null)
    : [];
}

export function buildTelegraPatientDataFromJotformSubmission(
  submission: JotformSubmissionContent,
): JsonRecord {
  const patientData: JsonRecord = {};
  const symptoms = normalizeJotformStringArrayAnswer(
    getJotformAnswerValueByName(submission, "symptoms"),
  );
  if (symptoms.length > 0) patientData.symptoms = symptoms;

  const otherSymptoms = getJotformAnswerStringByName(
    submission,
    "other_symptoms",
  );
  if (otherSymptoms) patientData.other_symptoms = otherSymptoms;

  const medications = normalizeJotformRecordArrayAnswer(
    getJotformAnswerValueByName(submission, "medication"),
    normalizeJotformMedicationRecord,
  );
  if (medications.length > 0) patientData.medication = medications;

  const medicationConfirmation = normalizeJotformYesAnswer(
    getJotformAnswerValueByName(submission, "medication_confirmation"),
  );
  if (medicationConfirmation) {
    patientData.medication_confirmation = medicationConfirmation;
  }

  const allergies = normalizeJotformRecordArrayAnswer(
    getJotformAnswerValueByName(submission, "allergies"),
    normalizeJotformAllergyRecord,
  );
  if (allergies.length > 0) patientData.allergies = allergies;

  const allergiesConfirmation = normalizeJotformYesAnswer(
    getJotformAnswerValueByName(submission, "allergies_confirmation"),
  );
  if (allergiesConfirmation) {
    patientData.allergies_confirmation = allergiesConfirmation;
  }

  const gender = normalizeJotformGenderAnswer(
    getJotformAnswerValueByName(submission, "biological_gender"),
  );
  if (gender) patientData.biological_gender = gender;

  const weight = buildJotformMeasuredAnswer({
    submission,
    valueFieldName: "weight_value",
    unitFieldName: "weight_unit",
  }) ?? getJotformAnswerStringByName(submission, "weight_lbs");
  if (weight) patientData.weight_lbs = weight;

  const height = buildJotformMeasuredAnswer({
    submission,
    valueFieldName: "height_value",
    unitFieldName: "height_unit",
  }) ?? getJotformAnswerStringByName(submission, "height_ft");
  if (height) patientData.height_ft = height;

  const birthDate = normalizeJotformDateAnswer(
    getJotformAnswerValueByName(submission, "date_of_birth"),
  ) ?? normalizeJotformDateAnswer(
    getJotformAnswerValueByName(submission, "birth_date"),
  );
  if (birthDate) patientData.birth_date = birthDate;

  return patientData;
}

function normalizeLegacyOrStructuredPatientData(
  patientData: JsonRecord,
): JsonRecord | null {
  if (
    "dateOfBirth" in patientData ||
    "genderBiological" in patientData ||
    "patientMedications" in patientData ||
    "medicationAllergies" in patientData ||
    "notes" in patientData
  ) {
    return patientData;
  }

  const hasNewPayloadShape = [
    "symptoms",
    "other_symptoms",
    "medication",
    "allergies",
    "medication_confirmation",
    "allergies_confirmation",
    "biological_gender",
    "weight_lbs",
    "height_ft",
    "birth_date",
  ].some((key) => key in patientData);

  return hasNewPayloadShape ? null : patientData;
}

async function transformAppPatientDataToTelegraPayload(params: {
  patientData: JsonRecord;
  supabase: SupabaseAdminClient;
  order: OrderRow;
  telegraIntegration: TenantIntegrationRow;
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<JsonRecord> {
  const {
    patientData,
    supabase,
    order,
    telegraIntegration,
    baseUrl,
    accessToken,
    requestId,
  } = params;

  const passthrough = normalizeLegacyOrStructuredPatientData(patientData);
  if (passthrough) {
    return passthrough;
  }

  const productAssignment = order.product_id
    ? await fetchProductProviderPlatform({
      supabase,
      productId: order.product_id,
      tenantIntegrationId: telegraIntegration.id,
    })
    : null;

  const productIds = extractConfiguredProductIdsFromProviderProductSku(
    productAssignment?.provider_product_sku,
  );

  const symptomsResponse = await fetchTelegraConditionsAndSymptoms({
    baseUrl,
    accessToken,
    productIds,
    requestId,
  });
  const symptoms = extractTelegraSymptoms(symptomsResponse);
  const symptomsById = new Map(
    symptoms
      .filter((symptom) => symptom.id && symptom.name)
      .map((symptom) => [symptom.id as string, symptom.name]),
  );

  const nowIso = new Date().toISOString();
  const notes = mapSymptomsToNotes({
    symptomValues: parseStringArray(patientData.symptoms),
    symptomsById,
    otherSymptoms: parseNonEmptyString(patientData.other_symptoms),
  });

  const medicationAllergies = parseRecordArray(patientData.allergies).map(
    (allergy) => ({
      key: crypto.randomUUID(),
      medicationAllergies: parseNonEmptyString(allergy.Medication) ||
        parseNonEmptyString(allergy.medicationAllergies) ||
        parseNonEmptyString(allergy.medication) ||
        "",
      reaction: parseNonEmptyString(allergy.Reaction) ||
        parseNonEmptyString(allergy.reaction) ||
        "",
    }),
  ).filter((entry) => entry.medicationAllergies.length > 0);

  const patientMedications = parseRecordArray(patientData.medication).map(
    (medication) => ({
      key: crypto.randomUUID(),
      medication: parseNonEmptyString(medication.medication_name) ||
        parseNonEmptyString(medication.medication) ||
        "",
      dosage: parseNonEmptyString(medication.dosage) || "",
      frequency: parseNonEmptyString(medication.frequency) || "",
      conditionPrescribed: parseNonEmptyString(medication.Condition_threated) ||
        parseNonEmptyString(medication.conditionPrescribed) ||
        parseNonEmptyString(medication.condition_treated) ||
        "",
    }),
  ).filter((entry) => entry.medication.length > 0);

  const normalizedPayload: JsonRecord = {
    updatedAt: nowIso,
  };

  const dateOfBirth = parseDateOnlyToIso(patientData.birth_date);
  if (dateOfBirth) normalizedPayload.dateOfBirth = dateOfBirth;

  const genderBiological = parseNonEmptyString(patientData.biological_gender);
  if (genderBiological) normalizedPayload.genderBiological = genderBiological;

  const weight = parseWeightToPounds(patientData.weight_lbs);
  if (weight !== null) normalizedPayload.weight = weight;

  const height = parseHeightToInches(patientData.height_ft);
  if (height !== null) normalizedPayload.height = height;

  if (parseBooleanYes(patientData.allergies_confirmation)) {
    normalizedPayload.allergiesConfirmationDate = nowIso;
  }

  if (parseBooleanYes(patientData.medication_confirmation)) {
    normalizedPayload.medicationsConfirmationDate = nowIso;
  }

  if (notes.length > 0) normalizedPayload.notes = notes;
  if (medicationAllergies.length > 0) {
    normalizedPayload.medicationAllergies = medicationAllergies;
  }
  if (patientMedications.length > 0) {
    normalizedPayload.patientMedications = patientMedications;
  }

  return normalizedPayload;
}

async function fetchTelegraQuestionnaireSchema(params: {
  questionnaireInstanceId: string;
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<unknown> {
  const { questionnaireInstanceId, baseUrl, accessToken, requestId } = params;
  const endpoint = buildTelegraQuestionnaireSchemaUrl(
    baseUrl,
    questionnaireInstanceId,
  );

  const { response, responseBody } = await fetchTelegraApi({
    operation: "fetch questionnaire schema",
    endpoint,
    requestId,
    metadata: { questionnaireInstanceId },
    requestInit: {
      method: "GET",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
    },
  });

  if (!response.ok) {
    throw new Error(
      `Telegra questionnaire schema fetch failed for ${questionnaireInstanceId}: ${response.status} ${response.statusText}` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

function extractQuestionnaireInstanceStatus(
  responseBody: unknown,
): string | null {
  if (!responseBody || typeof responseBody !== "object") return null;

  const status = (responseBody as Record<string, unknown>).status;
  return typeof status === "string" && status.trim().length > 0
    ? status.trim()
    : null;
}

function extractQuestionnaireInstanceValid(
  responseBody: unknown,
): boolean | null {
  if (!responseBody || typeof responseBody !== "object") return null;

  const valid = (responseBody as Record<string, unknown>).valid;
  return typeof valid === "boolean" ? valid : null;
}

async function fetchTelegraQuestionnaireInstance(params: {
  questionnaireInstanceId: string;
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<unknown> {
  const { questionnaireInstanceId, baseUrl, accessToken, requestId } = params;
  const endpoint = buildTelegraQuestionnaireInstanceUrl(
    baseUrl,
    questionnaireInstanceId,
  );

  const { response, responseBody } = await fetchTelegraApi({
    operation: "fetch questionnaire instance",
    endpoint,
    requestId,
    metadata: { questionnaireInstanceId },
    requestInit: {
      method: "GET",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
    },
  });

  if (!response.ok) {
    throw new Error(
      `Telegra questionnaire instance fetch failed for ${questionnaireInstanceId}: ${response.status} ${response.statusText}` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

async function fetchTelegraConditionsAndSymptoms(params: {
  baseUrl: string;
  accessToken: string;
  productIds: string[];
  requestId: string;
}): Promise<unknown> {
  const { baseUrl, accessToken, productIds, requestId } = params;
  const endpoint = buildTelegraConditionsAndSymptomsUrl(baseUrl, productIds);

  const { response, responseBody } = await fetchTelegraApi({
    operation: "fetch conditions and symptoms",
    endpoint,
    requestId,
    metadata: {
      productIdsCount: productIds.length,
      productIds: productIds.slice(0, 20),
    },
    requestInit: {
      method: "GET",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
    },
  });

  if (!response.ok) {
    throw new Error(
      `Telegra conditions and symptoms fetch failed: ${response.status} ${response.statusText}` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

async function fetchTelegraProductVariations(params: {
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<unknown> {
  const { baseUrl, accessToken, requestId } = params;
  const endpoint = `${baseUrl.replace(/\/+$/, "")}/productVariations`;

  const { response, responseBody } = await fetchTelegraApi({
    operation: "fetch product variations",
    endpoint,
    requestId,
    requestInit: {
      method: "GET",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
    },
  });

  if (!response.ok) {
    throw new Error(
      `Telegra product variations fetch failed: ${response.status} ${response.statusText}` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

export async function handleTelegraResolveProductVariationRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  authUserId: string;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, authUserId, requestId } = params;
  const body = await req.json() as ResolveTelegraProductVariationRequestBody;

  const tenantIntegrationId = parseNonEmptyString(
    body.tenantIntegrationId ?? body.tenant_integration_id,
  );
  const productVariationId = parseNonEmptyString(
    body.productVariationId ?? body.product_variation_id,
  );

  if (!tenantIntegrationId || !productVariationId) {
    return jsonResponse(
      req,
      {
        error: "Invalid request",
        message:
          "Provide tenantIntegrationId and productVariationId in the request body",
      },
      400,
    );
  }

  const tenantIntegration = await fetchTenantIntegrationById({
    supabase,
    tenantIntegrationId,
  });

  if (!tenantIntegration) {
    return jsonResponse(
      req,
      {
        error: "Not found",
        message: `No tenant integration found for id ${tenantIntegrationId}`,
      },
      404,
    );
  }

  if (
    !tenantIntegration.is_enabled ||
    tenantIntegration.integration_key !== "telegramd"
  ) {
    return jsonResponse(
      req,
      {
        error: "Invalid integration",
        message:
          "The provided tenant integration is not an enabled Telegra integration",
      },
      400,
    );
  }

  const hasTenantAccess = await userHasTenantAccess({
    supabase,
    authUserId,
    tenantId: tenantIntegration.tenant_id,
    requestId,
    resource: "resolve-telegra-product-variation",
  });

  if (!hasTenantAccess) {
    return jsonResponse(
      req,
      {
        error: "Forbidden",
        message: "You do not have access to the requested tenant integration",
      },
      403,
    );
  }

  const baseUrl = getStringSetting(tenantIntegration.settings, "url");
  if (!baseUrl) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: "Telegra integration is missing URL configuration",
      },
      400,
    );
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: tenantIntegration.id,
    tenantId: tenantIntegration.tenant_id,
    settings: tenantIntegration.settings,
    baseUrl,
    requestId,
    source: "provider-platform-bridge",
  });

  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: authResult.errorMessage,
      },
      400,
    );
  }

  const responseBody = await fetchTelegraProductVariations({
    baseUrl,
    accessToken: authResult.accessToken,
    requestId,
  });

  const productVariations = Array.isArray(
      (responseBody as { productVariations?: unknown })?.productVariations,
    )
    ? ((responseBody as { productVariations: Array<Record<string, unknown>> })
      .productVariations)
    : [];

  const matchingVariation = productVariations.find((variation) =>
    parseNonEmptyString(variation.id) === productVariationId
  );
  const matchingVariationProduct =
    matchingVariation?.product && typeof matchingVariation.product === "object"
      ? matchingVariation.product as Record<string, unknown>
      : null;
  const productId = parseNonEmptyString(
    matchingVariationProduct?.id ?? matchingVariationProduct?._id,
  );

  if (!matchingVariation || !productId) {
    return jsonResponse(
      req,
      {
        error: "Telegra product variation not found",
        message:
          "No Telegra product variation matched the provided variation SKU",
      },
      404,
    );
  }

  return jsonResponse(req, {
    productVariationId,
    productId,
  });
}

async function updateTelegraPatient(params: {
  providerPatientId: string;
  patientData: Record<string, unknown>;
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<unknown> {
  const { providerPatientId, patientData, baseUrl, accessToken, requestId } =
    params;

  const endpoint = buildTelegraPatientUrl(baseUrl, providerPatientId);
  const { response, responseBody } = await fetchTelegraApi({
    operation: "update patient",
    endpoint,
    requestId,
    metadata: { providerPatientId },
    requestBodySummary: summarizeTelegraPatientPayloadForLog(patientData),
    requestInit: {
      method: "PUT",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
      body: JSON.stringify(patientData),
    },
  });

  if (!response.ok) {
    throw new Error(
      `Telegra patient update failed for ${providerPatientId}: ${response.status} ${response.statusText}` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

async function answerTelegraQuestionnaireLocation(params: {
  questionnaireInstanceId: string;
  location: string;
  value: string | string[] | File;
  baseUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<unknown> {
  const {
    questionnaireInstanceId,
    location,
    value,
    baseUrl,
    accessToken,
    requestId,
  } = params;

  const endpoint = buildTelegraQuestionnaireAnswerLocationUrl(
    baseUrl,
    questionnaireInstanceId,
  );
  const agreementData = buildTelegraAnswerLocationAgreementData({
    location,
    signature: await resolveTelegraAnswerSignature(value),
  });

  const requestInit = buildTelegraAnswerLocationRequestInit({
    location,
    value,
    ...(agreementData ? { agreementData } : {}),
    accessToken,
    requestId,
  });
  const { response, responseBody } = await fetchTelegraApi({
    operation: "answer questionnaire location",
    endpoint,
    requestId,
    metadata: {
      questionnaireInstanceId,
      location,
      hasAgreementData: agreementData !== null,
    },
    requestBodySummary: agreementData
      ? { bodyType: "object", keys: ["agreementData"] }
      : summarizeTelegraAnswerPayloadForLog(value),
    requestInit,
  });

  if (!response.ok) {
    throw new Error(
      `Telegra answerLocation failed for ${questionnaireInstanceId}: ${response.status} ${response.statusText}` +
        ` - request=${
          JSON.stringify({
            location,
            contentType:
              (requestInit.headers as Record<string, string> | undefined)?.[
                "Content-Type"
              ] ||
              (requestInit.headers instanceof Headers
                ? requestInit.headers.get("Content-Type")
                : null),
            payload: summarizeTelegraAnswerLocationPayload(value),
          })
        }` +
        formatTelegraErrorDetail(responseBody).trim(),
    );
  }

  return responseBody;
}

async function resolveTelegraOrderContext(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  orderId: string;
  requestId: string;
  authUserId: string;
}): Promise<
  | {
    order: OrderRow;
    telegraIntegration: TenantIntegrationRow;
    providerPlatformLink: OrderProviderPlatformLinkRow;
    questionnaireInstanceIds: string[];
    baseUrl: string;
    accessToken: string;
  }
  | Response
> {
  const { supabase, req, orderId, requestId, authUserId } = params;

  const order = await fetchOrderById(supabase, orderId);
  if (!order) {
    return jsonResponse(
      req,
      {
        error: "Order not found",
        message: `No order found for id ${orderId}`,
      },
      404,
    );
  }

  const hasOrderAccess = await userHasOrderAccess({
    supabase,
    authUserId,
    order,
  });
  if (!hasOrderAccess) {
    return jsonResponse(
      req,
      {
        error: "Forbidden",
        message: "You do not have access to the requested order",
      },
      403,
    );
  }

  const telegraIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "telegramd",
  });
  if (!telegraIntegration) {
    return jsonResponse(
      req,
      {
        error: "Telegra integration not found",
        message: "No enabled Telegra integration found for the order tenant",
      },
      404,
    );
  }

  const providerPlatformLinks = await fetchOrderProviderPlatformLinks({
    supabase,
    orderId: order.id,
    tenantId: order.tenant_id,
  });

  const providerPlatformLink = providerPlatformLinks.find(
    (link) => link.tenant_integration_id === telegraIntegration.id,
  ) || null;

  if (!providerPlatformLink) {
    const hasAnyProviderLink = providerPlatformLinks.length > 0;
    return jsonResponse(
      req,
      {
        error: hasAnyProviderLink
          ? "Provider platform mismatch"
          : "Provider platform link not found",
        message: hasAnyProviderLink
          ? "The order is linked to a different provider platform and cannot be fetched from Telegra"
          : "No Telegra provider-platform link found for this order",
      },
      hasAnyProviderLink ? 409 : 404,
    );
  }

  const providerName = extractProviderNameFromMetadata(
    providerPlatformLink.metadata,
  );
  if (providerName && !isTelegraProviderPlatform(providerName)) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          `The order provider platform (${providerName}) does not match the Telegra bridge`,
      },
      409,
    );
  }

  const questionnaireInstanceIds = extractQuestionnaireInstanceIdsFromMetadata(
    providerPlatformLink.metadata,
  );

  const baseUrl = getStringSetting(telegraIntegration.settings, "url");
  if (!baseUrl) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: "Telegra integration is missing URL configuration",
      },
      500,
    );
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraIntegration.id,
    tenantId: telegraIntegration.tenant_id,
    settings: telegraIntegration.settings,
    baseUrl,
    requestId,
    source: "provider-platform-bridge",
  });
  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: authResult.errorMessage,
      },
      500,
    );
  }

  return {
    order,
    telegraIntegration,
    providerPlatformLink,
    questionnaireInstanceIds,
    baseUrl,
    accessToken: authResult.accessToken,
  };
}

export async function handleTelegraSymptomsRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  url: URL;
  authUserId: string;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, url, authUserId, requestId } = params;
  const { tenantId, tenantSlug } = parseRequestedTenantIdentifier(req, url);
  if (!tenantId && !tenantSlug) {
    return jsonResponse(
      req,
      {
        error: "Missing tenant identifier",
        message:
          "Provide tenant_id or slug as a query parameter, or x-tenant-id or x-tenant-slug as a request header",
      },
      400,
    );
  }

  const productIds = parseProductsQueryParam(url);
  if (productIds.length === 0) {
    return jsonResponse(
      req,
      {
        error: "Missing products",
        message:
          "Provide at least one Telegra product id in the products query parameter",
      },
      400,
    );
  }

  const tenant = await fetchTenantByIdentifier({
    supabase,
    tenantId,
    tenantSlug,
  });

  if (!tenant) {
    return jsonResponse(
      req,
      {
        error: "Tenant not found",
        message: "No tenant was found for the provided tenant identifier",
      },
      404,
    );
  }

  const hasTenantAccess = await userHasTenantAccess({
    supabase,
    authUserId,
    tenantId: tenant.id,
  });

  if (!hasTenantAccess) {
    return jsonResponse(
      req,
      {
        error: "Forbidden",
        message: "You do not have access to the requested tenant",
      },
      403,
    );
  }

  const telegraIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: tenant.id,
    integrationKey: "telegramd",
  });

  if (!telegraIntegration) {
    return jsonResponse(
      req,
      {
        error: "Telegra integration not found",
        message:
          "No enabled Telegra integration found for the requested tenant",
      },
      404,
    );
  }

  const baseUrl = getStringSetting(telegraIntegration.settings, "url");
  if (!baseUrl) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: "Telegra integration is missing URL configuration",
      },
      500,
    );
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraIntegration.id,
    tenantId: telegraIntegration.tenant_id,
    settings: telegraIntegration.settings,
    baseUrl,
    requestId,
    source: "provider-platform-bridge",
  });

  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: authResult.errorMessage,
      },
      500,
    );
  }

  const responseBody = await fetchTelegraConditionsAndSymptoms({
    baseUrl,
    accessToken: authResult.accessToken,
    productIds,
    requestId,
  });

  return jsonResponse(req, {
    symptoms: extractTelegraSymptoms(responseBody),
  });
}

export async function handleTelegraPatientQuestionnaireRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  orderId: string;
  requestId: string;
  authUserId: string;
}): Promise<Response> {
  const { supabase, req, orderId, requestId, authUserId } = params;
  const orderContext = await resolveTelegraOrderContext({
    supabase,
    req,
    orderId,
    requestId,
    authUserId,
  });

  if (orderContext instanceof Response) {
    return orderContext;
  }

  const telegraOrder = orderContext.order;
  const telegraIntegration = orderContext.telegraIntegration;
  const telegraProviderPlatformLink = orderContext.providerPlatformLink;
  const baseUrl = orderContext.baseUrl;
  const accessToken = orderContext.accessToken;

  const questionnaireDefinition = telegraIntegration.settings
    ?.patient_questionnaire_definition;

  if (
    !questionnaireDefinition ||
    typeof questionnaireDefinition !== "object" ||
    Array.isArray(questionnaireDefinition)
  ) {
    return jsonResponse(
      req,
      {
        error: "Patient questionnaire definition not found",
        message:
          "The tenant Telegra integration does not have a valid patient_questionnaire_definition object configured",
      },
      404,
    );
  }

  const productAssignment = telegraOrder.product_id
    ? await fetchProductProviderPlatform({
      supabase,
      productId: telegraOrder.product_id,
      tenantIntegrationId: telegraIntegration.id,
    })
    : null;

  const productIds = extractConfiguredProductIdsFromProviderProductSku(
    productAssignment?.provider_product_sku,
  );

  const symptomsResponse = await fetchTelegraConditionsAndSymptoms({
    baseUrl,
    accessToken,
    productIds,
    requestId,
  });
  const symptoms = extractTelegraSymptoms(symptomsResponse);
  const populatedQuestionnaire = populateSymptomsQuestionnaireOptions(
    questionnaireDefinition,
    symptoms,
  );

  if (!populatedQuestionnaire) {
    return jsonResponse(
      req,
      {
        error: "Patient questionnaire definition invalid",
        message:
          "The tenant Telegra integration patient_questionnaire_definition must be a JSON object",
      },
      500,
    );
  }

  return jsonResponse(req, {
    orderId: telegraOrder.id,
    provider: "TelegraMD",
    providerOrderId: telegraProviderPlatformLink?.provider_order_id || null,
    patientQuestionnaire: populatedQuestionnaire.questionnaire,
    symptomsCount: symptoms.length,
    symptomsQuestionCount: populatedQuestionnaire.replacedCount,
  });
}

export async function handleTelegraUpdatePatientProfileRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  orderId: string;
  requestId: string;
  authUserId: string;
}): Promise<Response> {
  const { supabase, req, orderId, requestId, authUserId } = params;
  const orderContext = await resolveTelegraOrderContext({
    supabase,
    req,
    orderId,
    requestId,
    authUserId,
  });

  if (orderContext instanceof Response) {
    return orderContext;
  }

  const {
    order,
    telegraIntegration,
    providerPlatformLink,
    baseUrl,
    accessToken,
  } = orderContext;

  const body = await parseUpdatePatientProfileBody(req);

  if (!body.patientData) {
    return jsonResponse(
      req,
      {
        error: "Invalid request body",
        message: "Provide a non-empty patientData object",
      },
      400,
    );
  }

  if (!order.patient_id) {
    return jsonResponse(
      req,
      {
        error: "Patient not found",
        message: "The order does not have an associated patient",
      },
      404,
    );
  }

  if (!providerPlatformLink.provider_order_id) {
    return jsonResponse(
      req,
      {
        error: "Telegra order id not found",
        message: "This order does not have a matching Telegra order id yet",
      },
      409,
    );
  }

  const patientProviderPlatformLink = await fetchPatientProviderPlatformLink({
    supabase,
    patientId: order.patient_id,
    tenantId: order.tenant_id,
    tenantIntegrationId: telegraIntegration.id,
  });

  if (!patientProviderPlatformLink?.provider_patient_id) {
    return jsonResponse(
      req,
      {
        error: "Telegra patient id not found",
        message:
          "No Telegra patient id is stored for the patient associated with this order",
      },
      404,
    );
  }

  const patient = await fetchPatientById({
    supabase,
    patientId: order.patient_id,
    tenantId: order.tenant_id,
  });

  if (!patient) {
    return jsonResponse(
      req,
      {
        error: "Patient not found",
        message:
          "No patient profile was found for the patient associated with this order",
      },
      404,
    );
  }

  const patientPhone =
    typeof patient.phone === "string" && patient.phone.trim().length > 0
      ? patient.phone.trim()
      : null;

  const normalizedPatientData = await transformAppPatientDataToTelegraPayload({
    patientData: body.patientData,
    supabase,
    order,
    telegraIntegration,
    baseUrl,
    accessToken,
    requestId,
  });

  const telegraPatientData = {
    ...normalizedPatientData,
    id: patientProviderPlatformLink.provider_patient_id,
    email: patient.email,
    firstName: patient.first_name,
    lastName: patient.last_name,
    ...(patientPhone ? { phone: patientPhone } : {}),
  };

  const updateResponse = await updateTelegraPatient({
    providerPatientId: patientProviderPlatformLink.provider_patient_id,
    patientData: telegraPatientData,
    baseUrl,
    accessToken,
    requestId,
  });

  const orderStatusAdvance = await advanceOrderToNextStatus({
    supabase,
    order,
    note: "Patient Questionnaire has been submitted.",
    requestId,
    source: "provider-platform-bridge:telegra-patient-questionnaire",
    // Guard: a questionnaire submission must never advance past the provider-review
    // gate (e.g. a returning patient whose questionnaire was reused leaves the order
    // already at provider_review_pending — advancing would self-approve + capture).
    expectedFromStatusKeys: QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
  });

  const orderLifecycleTriggered = await triggerOrderLifecycleForOrder({
    orderId: order.id,
    tenantId: order.tenant_id,
    requestId,
  });

  return jsonResponse(req, {
    orderId: order.id,
    patientId: order.patient_id,
    provider: "TelegraMD",
    providerOrderId: providerPlatformLink.provider_order_id,
    providerPatientId: patientProviderPlatformLink.provider_patient_id,
    orderStatusAdvanced: orderStatusAdvance.advanced,
    previousStatusKey: orderStatusAdvance.previousStatusKey,
    newStatusKey: orderStatusAdvance.newStatusKey,
    orderLifecycleTriggered,
    response: updateResponse,
  });
}

export async function handleTelegraJotformPatientQuestionnaireSubmission(
  params: {
    supabase: SupabaseAdminClient;
    req: Request;
    order: OrderRow;
    submission: JotformSubmissionContent;
    submissionId: string;
    formId: string | null;
    incomingQuestionnaireType: JotformSubmissionQuestionnaireType;
    submittedQuestionnaireType: JotformSubmissionQuestionnaireType | null;
    requestId: string;
  },
): Promise<Response> {
  const {
    supabase,
    req,
    order,
    submission,
    submissionId,
    formId,
    incomingQuestionnaireType,
    submittedQuestionnaireType,
    requestId,
  } = params;
  const submittedProviderKey = getJotformAnswerStringByName(
    submission,
    JOTFORM_PROVIDER_KEY_FIELD_NAME,
  );

  if (
    submittedProviderKey && !isTelegraProviderPlatform(submittedProviderKey)
  ) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          `Jotform submission ${submissionId} is marked for provider ${submittedProviderKey}, not Telegra.`,
        orderId: order.id,
        submissionId,
        formId,
      },
      409,
    );
  }

  const telegraIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "telegramd",
  });
  if (!telegraIntegration) {
    return jsonResponse(
      req,
      {
        error: "Telegra integration not found",
        message: "No enabled Telegra integration found for the order tenant",
      },
      404,
    );
  }

  if (!order.patient_id) {
    return jsonResponse(
      req,
      {
        error: "Patient not found",
        message: `Order ${order.id} does not have a patient assigned`,
      },
      409,
    );
  }

  const providerPlatformLinks = await fetchOrderProviderPlatformLinks({
    supabase,
    orderId: order.id,
    tenantId: order.tenant_id,
  });
  const providerPlatformLink = providerPlatformLinks.find(
    (link) => link.tenant_integration_id === telegraIntegration.id,
  ) || null;

  if (!providerPlatformLink) {
    const hasAnyProviderLink = providerPlatformLinks.length > 0;
    return jsonResponse(
      req,
      {
        error: hasAnyProviderLink
          ? "Provider platform mismatch"
          : "Provider platform link not found",
        message: hasAnyProviderLink
          ? "The order is linked to a different provider platform and cannot process a Telegra Jotform patient questionnaire"
          : "No Telegra provider-platform link found for this order",
        orderId: order.id,
        submissionId,
        formId,
      },
      hasAnyProviderLink ? 409 : 404,
    );
  }

  const providerName = extractProviderNameFromMetadata(
    providerPlatformLink.metadata,
  );
  if (providerName && !isTelegraProviderPlatform(providerName)) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          `The order provider platform (${providerName}) does not match the Telegra bridge`,
        orderId: order.id,
        submissionId,
        formId,
      },
      409,
    );
  }

  if (!providerPlatformLink.provider_order_id) {
    return jsonResponse(
      req,
      {
        error: "Telegra order id not found",
        message: "This order does not have a matching Telegra order id yet",
        orderId: order.id,
        submissionId,
        formId,
      },
      409,
    );
  }

  const patientProviderPlatformLink = await fetchPatientProviderPlatformLink({
    supabase,
    patientId: order.patient_id,
    tenantId: order.tenant_id,
    tenantIntegrationId: telegraIntegration.id,
  });

  if (!patientProviderPlatformLink?.provider_patient_id) {
    return jsonResponse(
      req,
      {
        error: "Telegra patient id not found",
        message:
          "No Telegra patient id is stored for the patient associated with this order",
        orderId: order.id,
        submissionId,
        formId,
      },
      404,
    );
  }

  const patient = await fetchPatientById({
    supabase,
    patientId: order.patient_id,
    tenantId: order.tenant_id,
  });

  if (!patient) {
    return jsonResponse(
      req,
      {
        error: "Patient not found",
        message:
          "No patient profile was found for the patient associated with this order",
        orderId: order.id,
        submissionId,
        formId,
      },
      404,
    );
  }

  const baseUrl = getStringSetting(telegraIntegration.settings, "url");
  if (!baseUrl) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: "Telegra integration is missing URL configuration",
        orderId: order.id,
        submissionId,
        formId,
      },
      500,
    );
  }

  const authResult = await resolveTelegraAccessToken({
    supabase,
    tenantIntegrationId: telegraIntegration.id,
    tenantId: telegraIntegration.tenant_id,
    settings: telegraIntegration.settings,
    baseUrl,
    requestId,
    source: "provider-platform-bridge",
  });
  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "Telegra configuration invalid",
        message: authResult.errorMessage,
        orderId: order.id,
        submissionId,
        formId,
      },
      500,
    );
  }

  const patientPhone =
    typeof patient.phone === "string" && patient.phone.trim().length > 0
      ? patient.phone.trim()
      : null;
  const jotformPatientData = buildTelegraPatientDataFromJotformSubmission(
    submission,
  );
  const normalizedPatientData = await transformAppPatientDataToTelegraPayload({
    patientData: jotformPatientData,
    supabase,
    order,
    telegraIntegration,
    baseUrl,
    accessToken: authResult.accessToken,
    requestId,
  });
  const telegraPatientData = {
    ...normalizedPatientData,
    id: patientProviderPlatformLink.provider_patient_id,
    email: patient.email,
    firstName: patient.first_name,
    lastName: patient.last_name,
    ...(patientPhone ? { phone: patientPhone } : {}),
  };

  const updateResponse = await updateTelegraPatient({
    providerPatientId: patientProviderPlatformLink.provider_patient_id,
    patientData: telegraPatientData,
    baseUrl,
    accessToken: authResult.accessToken,
    requestId,
  });

  const orderStatusAdvance = await advanceOrderToNextStatus({
    supabase,
    order,
    note: "Patient Questionnaire has been submitted.",
    requestId,
    source: "provider-platform-bridge:telegra-patient-questionnaire",
    // Guard: a questionnaire submission must never advance past the provider-review
    // gate (e.g. a returning patient whose questionnaire was reused leaves the order
    // already at provider_review_pending — advancing would self-approve + capture).
    expectedFromStatusKeys: QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
  });

  const orderLifecycleTriggered = await triggerOrderLifecycleForOrder({
    orderId: order.id,
    tenantId: order.tenant_id,
    requestId,
  });

  return jsonResponse(req, {
    success: true,
    questionnaireType: "patient_questionnaire",
    incomingQuestionnaireType,
    submittedQuestionnaireType,
    orderId: order.id,
    patientId: order.patient_id,
    submissionId,
    formId,
    provider: "TelegraMD",
    providerOrderId: providerPlatformLink.provider_order_id,
    providerPatientId: patientProviderPlatformLink.provider_patient_id,
    patientDataFields: Object.keys(normalizedPatientData),
    orderStatusAdvanced: orderStatusAdvance.advanced,
    previousStatusKey: orderStatusAdvance.previousStatusKey,
    newStatusKey: orderStatusAdvance.newStatusKey,
    orderLifecycleTriggered,
    response: updateResponse,
    requestId,
  });
}

function validateTelegraQuestionnaireAnswerBody(
  req: Request,
  body: ParsedAnswerLocationBody,
): Response | null {
  const answerValue = body.file || body.value;

  if (!body.questionnaireId || !body.location || !answerValue) {
    return jsonResponse(
      req,
      {
        error: "Invalid request body",
        message:
          "Provide questionnaire-id, location, and either a non-empty string value, a non-empty string array value, or a file upload",
      },
      400,
    );
  }

  return null;
}

export async function handleTelegraAnswerLocationRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  orderId: string;
  requestId: string;
  authUserId: string;
}): Promise<Response> {
  const { supabase, req, orderId, requestId, authUserId } = params;
  const orderContext = await resolveTelegraOrderContext({
    supabase,
    req,
    orderId,
    requestId,
    authUserId,
  });

  if (orderContext instanceof Response) {
    return orderContext;
  }

  const {
    order,
    providerPlatformLink,
    questionnaireInstanceIds,
    baseUrl,
    accessToken,
  } = orderContext;

  const body = await parseAnswerLocationBody(req);
  const validationError = validateTelegraQuestionnaireAnswerBody(req, body);
  if (validationError) {
    return validationError;
  }

  if (!questionnaireInstanceIds.includes(body.questionnaireId!)) {
    return jsonResponse(
      req,
      {
        error: "Questionnaire mismatch",
        message:
          "The provided questionnaire id is not available for this order on Telegra",
      },
      409,
    );
  }

  const answerResponse = await answerTelegraQuestionnaireLocation({
    questionnaireInstanceId: body.questionnaireId!,
    location: body.location!,
    value: body.file || (body.value as string | string[]),
    baseUrl,
    accessToken,
    requestId,
  });

  return jsonResponse(req, {
    orderId: order.id,
    provider: "TelegraMD",
    providerOrderId: providerPlatformLink.provider_order_id,
    questionnaireId: body.questionnaireId!,
    response: answerResponse,
  });
}

export async function handleTelegraQuestionnairesRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  orderId: string;
  requestId: string;
  authUserId: string;
}): Promise<Response> {
  const { supabase, req, orderId, requestId, authUserId } = params;
  const orderContext = await resolveTelegraOrderContext({
    supabase,
    req,
    orderId,
    requestId,
    authUserId,
  });

  if (orderContext instanceof Response) {
    return orderContext;
  }

  const {
    order,
    telegraIntegration,
    providerPlatformLink,
    questionnaireInstanceIds,
    baseUrl,
    accessToken,
  } = orderContext;

  if (questionnaireInstanceIds.length === 0) {
    return jsonResponse(
      req,
      {
        error: "No questionnaires found",
        message:
          "This order does not have stored Telegra questionnaire instance ids",
      },
      404,
    );
  }

  const questionnaireEntries = await Promise.all(
    questionnaireInstanceIds.map(async (questionnaireInstanceId) => {
      const [schema, questionnaireInstance] = await Promise.all([
        fetchTelegraQuestionnaireSchema({
          questionnaireInstanceId,
          baseUrl,
          accessToken,
          requestId,
        }),
        fetchTelegraQuestionnaireInstance({
          questionnaireInstanceId,
          baseUrl,
          accessToken,
          requestId,
        }),
      ]);

      return [
        questionnaireInstanceId,
        {
          schema: applyProviderLegalAgreementToAgreementQuestions(
            schema,
            telegraIntegration.provider_legal_agreement,
          ),
          status: extractQuestionnaireInstanceStatus(questionnaireInstance),
          valid: extractQuestionnaireInstanceValid(questionnaireInstance),
        },
      ] as const;
    }),
  );

  return jsonResponse(req, {
    orderId: order.id,
    provider: "TelegraMD",
    providerOrderId: providerPlatformLink.provider_order_id,
    questionnaireInstanceIds,
    questionnaires: Object.fromEntries(questionnaireEntries),
  });
}
