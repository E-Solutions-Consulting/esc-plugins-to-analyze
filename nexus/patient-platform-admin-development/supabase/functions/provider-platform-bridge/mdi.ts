import {
  advanceOrderToNextStatus,
  applyProviderLegalAgreementToAgreementQuestions,
  fetchOrderById,
  fetchPatientProviderPlatformLink,
  fetchProductProviderPlatform,
  fetchTenantIntegrationForTenantByKey,
  jsonResponse,
  OrderProviderPlatformLinkRow,
  OrderRow,
  parseUpdatePatientProfileBody,
  QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
  resolveOrderProviderPlatformLink,
  SupabaseAdminClient,
  triggerOrderLifecycleForOrder,
} from "./common.ts";
import { getStringSetting, isTelegraProviderPlatform } from "./helpers.ts";
import {
  extractJotformFileUrls,
  fetchJotformSubmission,
  getJotformSubmissionAnswerByName,
  getJotformSubmissionFormId,
  getJotformSubmissionOrderId,
  isJotformFileUploadField,
  isJotformIdFileField,
  isJotformUiOnlyField,
  JOTFORM_INTEGRATION_KEY,
  JOTFORM_ORDER_ID_FIELD_NAME,
  JOTFORM_PROVIDER_KEY_FIELD_NAME,
  JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
  JOTFORM_TEAM_WORKSPACE_ID_SETTING,
  type JotformSubmissionAnswer,
  type JotformSubmissionContent,
  type JotformSubmissionQuestionnaireType,
  resolveQuestionnairePresentation,
  stringifyJotformAnswer,
} from "./jotform.ts";
import { handleTelegraJotformPatientQuestionnaireSubmission } from "./telegra.ts";
import { resolveMdiAccessToken } from "../_shared/mdi-auth.ts";
import { dateTime } from "../_shared/dayjs.ts";

type JsonRecord = Record<string, unknown>;

interface MdiQuestionnaire {
  partner_questionnaire_id?: unknown;
  offerings?: unknown;
  [key: string]: unknown;
}

interface MdiCaseQuestionPayload {
  question: string;
  answer?: string;
  type: string;
  partner_questionnaire_question_id?: string;
  important?: boolean;
  critical?: boolean;
  display_in_pdf?: boolean;
  description?: string | null;
  label?: string | null;
  metadata?: string | null;
  displayed_options?: string[];
  file?: File;
  file_type?: string;
}

type MdiCaseQuestionRequestPayload =
  & Omit<MdiCaseQuestionPayload, "type">
  & { type?: string };

type MdiCaseQuestionRequestVariant = {
  payload: MdiCaseQuestionRequestPayload;
  typeLabel: string | null;
};

function extractErrorMessage(responseBody: unknown, fallback: string): string {
  if (typeof responseBody === "string" && responseBody.trim().length > 0) {
    return responseBody.trim();
  }

  if (responseBody && typeof responseBody === "object") {
    const record = responseBody as JsonRecord;
    for (const key of ["message", "error", "detail"]) {
      const value = record[key];
      if (typeof value === "string" && value.trim().length > 0) {
        return value.trim();
      }
    }
  }

  return fallback;
}

async function fetchMdiQuestionnaires(params: {
  backendUrl: string;
  accessToken: string;
  requestId: string;
}): Promise<MdiQuestionnaire[]> {
  const { backendUrl, accessToken, requestId } = params;
  const endpoint = `${
    backendUrl.replace(/\/+$/, "")
  }/v1/partner/questionnaires`;

  const response = await fetch(endpoint, {
    method: "GET",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI questionnaires fetch failed: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  if (!Array.isArray(responseBody)) {
    throw new Error("MDI questionnaires response must be an array");
  }

  return responseBody.filter((entry): entry is MdiQuestionnaire =>
    !!entry && typeof entry === "object" && !Array.isArray(entry)
  );
}

async function fetchMdiQuestionnaireDetails(params: {
  backendUrl: string;
  accessToken: string;
  questionnaireId: string;
  requestId: string;
}): Promise<MdiQuestionnaire> {
  const { backendUrl, accessToken, questionnaireId, requestId } = params;
  const endpoint = `${
    backendUrl.replace(/\/+$/, "")
  }/v1/partner/questionnaires/${encodeURIComponent(questionnaireId)}`;

  const response = await fetch(endpoint, {
    method: "GET",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI questionnaire fetch failed for ${questionnaireId}: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  if (
    !responseBody || typeof responseBody !== "object" ||
    Array.isArray(responseBody)
  ) {
    throw new Error(
      `MDI questionnaire response must be an object for ${questionnaireId}`,
    );
  }

  return responseBody as MdiQuestionnaire;
}

function parseNonEmptyString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0
    ? value.trim()
    : null;
}

function parseOptionalString(value: unknown): string | null | undefined {
  if (value === undefined) return undefined;
  if (value === null) return null;
  return parseNonEmptyString(value);
}

function normalizeJotformQuestionFlagToken(value: unknown): string | null {
  const raw = typeof value === "number" || typeof value === "boolean"
    ? String(value)
    : parseNonEmptyString(value);
  if (!raw) return null;

  const normalized = raw
    .replace(/([a-z0-9])([A-Z])/g, "$1_$2")
    .trim()
    .toLowerCase()
    .replace(/^q(\d+)$/, "$1")
    .replace(/^q(\d+)_/, "$1_")
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");

  return normalized || null;
}

function parseRecordArray(value: unknown): JsonRecord[] {
  if (!Array.isArray(value)) return [];
  return value.filter((entry): entry is JsonRecord =>
    !!entry && typeof entry === "object" && !Array.isArray(entry)
  );
}

function parseOfferingIds(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.flatMap((entry) => parseOfferingIds(entry));
  }

  if (!value || typeof value !== "object") {
    return [];
  }

  const record = value as JsonRecord;
  const offeringId = parseNonEmptyString(record.offering_id);
  return offeringId ? [offeringId] : [];
}

export function filterMdiQuestionnairesByOfferingId(
  questionnaires: MdiQuestionnaire[],
  offeringId: string,
): MdiQuestionnaire[] {
  const normalizedOfferingId = offeringId.trim();
  if (!normalizedOfferingId) {
    return [];
  }

  return questionnaires.filter((questionnaire) =>
    parseOfferingIds(questionnaire.offerings).includes(normalizedOfferingId)
  );
}

export interface MdiMedicationOffering {
  medication_id: string;
  medication_title: string | null;
  offering_id: string | null;
}

export interface MdiQuestionnaireMedicationMatch {
  questionnaireId: string;
  offeringId: string;
  medicationId: string;
  medicationTitle: string | null;
}

export function resolveMdiQuestionnaireIdsForMedicationOfferings(
  questionnaires: MdiQuestionnaire[],
  medicationOfferings: MdiMedicationOffering[],
): {
  questionnaireIds: string[];
  matches: MdiQuestionnaireMedicationMatch[];
  missingMedicationOfferings: MdiMedicationOffering[];
} {
  const questionnaireIds = new Set<string>();
  const matches: MdiQuestionnaireMedicationMatch[] = [];
  const missingMedicationOfferings: MdiMedicationOffering[] = [];

  for (const medicationOffering of medicationOfferings) {
    const offeringId = parseNonEmptyString(medicationOffering.offering_id);
    if (!offeringId) {
      missingMedicationOfferings.push(medicationOffering);
      continue;
    }

    const matchedQuestionnaires = filterMdiQuestionnairesByOfferingId(
      questionnaires,
      offeringId,
    );

    if (matchedQuestionnaires.length === 0) {
      missingMedicationOfferings.push(medicationOffering);
      continue;
    }

    for (const questionnaire of matchedQuestionnaires) {
      const questionnaireId = parseNonEmptyString(
        questionnaire.partner_questionnaire_id,
      );
      if (!questionnaireId) continue;

      questionnaireIds.add(questionnaireId);
      matches.push({
        questionnaireId,
        offeringId,
        medicationId: medicationOffering.medication_id,
        medicationTitle: medicationOffering.medication_title,
      });
    }
  }

  return {
    questionnaireIds: Array.from(questionnaireIds),
    matches,
    missingMedicationOfferings,
  };
}

function formatMdiMedicationOffering(
  medicationOffering: MdiMedicationOffering,
): string {
  const medicationLabel = medicationOffering.medication_title
    ? `${medicationOffering.medication_title} (${medicationOffering.medication_id})`
    : medicationOffering.medication_id;
  const offeringId = parseNonEmptyString(medicationOffering.offering_id);

  return offeringId ? `${medicationLabel}: ${offeringId}` : medicationLabel;
}

async function fetchMdiMedicationOfferingsForProduct(params: {
  supabase: SupabaseAdminClient;
  productId: string;
}): Promise<MdiMedicationOffering[]> {
  const { supabase, productId } = params;
  const { data, error } = await supabase
    .from("product_medications")
    .select(
      `
      medication_id,
      medication:medications (
        id,
        title,
        offering_id
      )
    `,
    )
    .eq("product_id", productId)
    .order("id", { ascending: true });

  if (error) {
    throw new Error(
      `Failed to fetch product medications for MDI questionnaire resolution: ${error.message}`,
    );
  }

  return ((data || []) as Array<{
    medication_id: string;
    medication:
      | {
        id: string;
        title: string | null;
        offering_id: string | null;
      }
      | Array<{
        id: string;
        title: string | null;
        offering_id: string | null;
      }>
      | null;
  }>).map((row) => {
    const medication = Array.isArray(row.medication)
      ? row.medication[0] || null
      : row.medication;

    return {
      medication_id: row.medication_id,
      medication_title: parseNonEmptyString(medication?.title),
      offering_id: parseNonEmptyString(medication?.offering_id),
    };
  });
}

async function fetchMdiQuestionnaireEntriesForMedicationOfferings(params: {
  backendUrl: string;
  accessToken: string;
  requestId: string;
  medicationOfferings: MdiMedicationOffering[];
}): Promise<
  | {
    errorMessage: null;
    questionnaireEntries: Array<[string, MdiQuestionnaire]>;
    questionnaireIds: string[];
    questionnaireMedicationMatches: MdiQuestionnaireMedicationMatch[];
  }
  | {
    errorMessage: string;
    questionnaireEntries: [];
    questionnaireIds: [];
    questionnaireMedicationMatches: [];
  }
> {
  const {
    backendUrl,
    accessToken,
    requestId,
    medicationOfferings,
  } = params;

  const missingOfferingIds = medicationOfferings.filter((offering) =>
    !parseNonEmptyString(offering.offering_id)
  );
  if (missingOfferingIds.length > 0) {
    return {
      errorMessage: `MDI offering_id is missing for linked medication(s): ${
        missingOfferingIds.map(formatMdiMedicationOffering).join(", ")
      }`,
      questionnaireEntries: [],
      questionnaireIds: [],
      questionnaireMedicationMatches: [],
    };
  }

  const questionnaires = await fetchMdiQuestionnaires({
    backendUrl,
    accessToken,
    requestId,
  });
  const resolution = resolveMdiQuestionnaireIdsForMedicationOfferings(
    questionnaires,
    medicationOfferings,
  );

  if (resolution.missingMedicationOfferings.length > 0) {
    return {
      errorMessage:
        `No native MDI questionnaire found for medication offering(s): ${
          resolution.missingMedicationOfferings.map(formatMdiMedicationOffering)
            .join(", ")
        }`,
      questionnaireEntries: [],
      questionnaireIds: [],
      questionnaireMedicationMatches: [],
    };
  }

  if (resolution.questionnaireIds.length === 0) {
    return {
      errorMessage:
        "No native MDI questionnaires were resolved for the linked medication offerings",
      questionnaireEntries: [],
      questionnaireIds: [],
      questionnaireMedicationMatches: [],
    };
  }

  const questionnaireEntries: Array<[string, MdiQuestionnaire]> = [];
  for (const questionnaireId of resolution.questionnaireIds) {
    const questionnaireDetails = await fetchMdiQuestionnaireDetails({
      backendUrl,
      accessToken,
      questionnaireId,
      requestId,
    });

    questionnaireEntries.push([
      questionnaireId,
      {
        ...questionnaireDetails,
        questions: questionnaireDetails.questions ?? [],
      },
    ]);
  }

  return {
    errorMessage: null,
    questionnaireEntries,
    questionnaireIds: resolution.questionnaireIds,
    questionnaireMedicationMatches: resolution.matches,
  };
}

async function persistMdiQuestionnaireIds(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  tenantIntegrationId: string;
  providerPlatformLink: OrderProviderPlatformLinkRow | null;
  questionnaireIds: string[];
}): Promise<void> {
  const {
    supabase,
    order,
    tenantIntegrationId,
    providerPlatformLink,
    questionnaireIds,
  } = params;

  const existingMetadata = providerPlatformLink?.metadata &&
      typeof providerPlatformLink.metadata === "object" &&
      !Array.isArray(providerPlatformLink.metadata)
    ? providerPlatformLink.metadata
    : {};

  const payload: Record<string, unknown> = {
    tenant_id: order.tenant_id,
    order_id: order.id,
    tenant_integration_id: tenantIntegrationId,
    metadata: {
      ...existingMetadata,
      provider: "MDI",
      questionnaire_instance_ids: questionnaireIds,
      last_received_at: dateTime().toISOString(),
    },
  };

  if (providerPlatformLink?.provider_order_id) {
    payload.provider_order_id = providerPlatformLink.provider_order_id;
  }

  const { error } = await supabase
    .from("order_provider_platform_links")
    .upsert(payload, {
      onConflict: "order_id,tenant_integration_id",
      ignoreDuplicates: false,
    });

  if (error) {
    throw new Error(
      `Failed to persist MDI questionnaire ids on order provider platform link: ${error.message}`,
    );
  }
}

function parseBooleanLike(value: unknown): boolean | null {
  if (typeof value === "boolean") return value;
  const normalized = parseNonEmptyString(value)?.toLowerCase();
  if (!normalized) return null;
  if (["yes", "true", "1"].includes(normalized)) return true;
  if (["no", "false", "0"].includes(normalized)) return false;
  return null;
}

function parseDisplayedOptions(value: unknown): string[] | undefined {
  if (value === undefined) return undefined;
  if (!Array.isArray(value)) return undefined;

  return value
    .map((entry) => parseNonEmptyString(entry))
    .filter((entry): entry is string => entry !== null);
}

function toMultipartFieldKey(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
}

function resolveMdiMultipartFile(
  formData: FormData,
  payload: MdiCaseQuestionPayload,
  entry: JsonRecord,
  consumedFields: Set<string>,
  questionIndex: number,
): { fieldName: string; file: File } | null {
  const explicitField = parseNonEmptyString(entry.file_field) ??
    parseNonEmptyString(entry.answer);

  const candidateFieldNames = [
    explicitField,
    payload.partner_questionnaire_question_id,
    `file_${questionIndex}`,
    `question_${questionIndex}`,
    `upload_${questionIndex}`,
    toMultipartFieldKey(payload.question),
    payload.question,
    "file",
  ].filter((value, index, values): value is string =>
    !!value && values.indexOf(value) === index
  );

  for (const fieldName of candidateFieldNames) {
    const fileEntry = formData.get(fieldName);
    if (fileEntry instanceof File && !consumedFields.has(fieldName)) {
      return { fieldName, file: fileEntry };
    }
  }

  const availableFileEntries = Array.from(formData.entries()).filter(
    ([fieldName, value]) =>
      fieldName !== "questions" &&
      value instanceof File &&
      !consumedFields.has(fieldName),
  ) as Array<[string, File]>;

  if (availableFileEntries.length > 0) {
    const [fieldName, file] = availableFileEntries[0];
    return { fieldName, file };
  }

  return null;
}

function isMdiFileQuestionType(value: string): boolean {
  const normalized = value.trim().toLowerCase().replace(/[\s-]+/g, "_");
  return normalized === "file" || normalized === "attachment" ||
    normalized === "upload";
}

export function normalizeMdiQuestionTypeAlias(value: string): string {
  const normalized = value.trim().toLowerCase().replace(/[\s-]+/g, "_");

  switch (normalized) {
    case "bool":
    case "yes_no":
    case "yesno":
      return "boolean";
    case "string":
    case "text":
    case "textarea":
    case "short_text":
    case "long_text":
    case "free_text":
      return "string";
    case "multiselect":
    case "multi_select":
    case "multiple_choice":
    case "multi_option":
    case "checkbox":
    case "checkboxes":
    case "multiple_option":
    case "single_option":
    case "single_choice":
    case "radio":
    case "select":
    case "dropdown":
      return "string";
    default:
      return normalized;
  }
}

function normalizeQuestionLookupKey(value: unknown): string | null {
  const parsed = parseNonEmptyString(value);
  return parsed ? parsed.toLowerCase() : null;
}

function findMdiQuestionnaireQuestion(
  questionnaire: MdiQuestionnaire | null,
  payload: MdiCaseQuestionPayload,
): JsonRecord | null {
  if (!questionnaire) return null;

  const requestedQuestionId = normalizeQuestionLookupKey(
    payload.partner_questionnaire_question_id,
  );
  const requestedQuestionText = normalizeQuestionLookupKey(payload.question);

  for (const question of parseRecordArray(questionnaire.questions)) {
    const questionId = normalizeQuestionLookupKey(
      question.partner_questionnaire_question_id,
    );
    const title = normalizeQuestionLookupKey(question.title);
    const label = normalizeQuestionLookupKey(question.label);
    const description = normalizeQuestionLookupKey(question.description);

    const matchesById = requestedQuestionId &&
      questionId === requestedQuestionId;
    const matchesByText = requestedQuestionText &&
      [title, label, description].includes(requestedQuestionText);

    if (matchesById || matchesByText) {
      return question;
    }
  }

  return null;
}

function findMdiQuestionnaireQuestionMatch(
  questionnaires: MdiQuestionnaire[],
  payload: MdiCaseQuestionPayload,
): { questionnaire: MdiQuestionnaire | null; question: JsonRecord | null } {
  for (const questionnaire of questionnaires) {
    const question = findMdiQuestionnaireQuestion(questionnaire, payload);
    if (question) {
      return { questionnaire, question };
    }
  }

  return { questionnaire: questionnaires[0] || null, question: null };
}

function parseQuestionOptions(question: JsonRecord | null): JsonRecord[] {
  if (!question) return [];
  return parseRecordArray(question.options);
}

function extractQuestionOptionLabels(question: JsonRecord | null): string[] {
  return parseQuestionOptions(question)
    .map((option) =>
      parseNonEmptyString(option.option) ??
        parseNonEmptyString(option.title) ??
        parseNonEmptyString(option.value)
    )
    .filter((option): option is string => option !== null);
}

function isBooleanOptionSet(options: string[]): boolean {
  if (options.length !== 2) return false;
  const normalized = options.map((option) => option.trim().toLowerCase());
  return normalized.includes("yes") && normalized.includes("no");
}

export function resolveMdiCaseQuestionType(params: {
  questionnaire: MdiQuestionnaire | null;
  payload: MdiCaseQuestionPayload;
}): string {
  const { questionnaire, payload } = params;
  const matchedQuestion = findMdiQuestionnaireQuestion(questionnaire, payload);
  const providerType = parseNonEmptyString(matchedQuestion?.type);
  if (providerType) {
    return normalizeMdiQuestionTypeAlias(providerType);
  }

  return normalizeMdiQuestionTypeAlias(payload.type);
}

function isMdiInvalidTypeError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error);
  return message.toLowerCase().includes("type you have entered is invalid");
}

export function buildMdiCaseQuestionTypeCandidates(params: {
  questionnaire: MdiQuestionnaire | null;
  payload: MdiCaseQuestionPayload;
}): string[] {
  const { questionnaire, payload } = params;
  const matchedQuestion = findMdiQuestionnaireQuestion(questionnaire, payload);
  const matchedQuestionType = resolveMdiCaseQuestionType({
    questionnaire,
    payload,
  });
  const optionLabels = extractQuestionOptionLabels(matchedQuestion);
  const normalizedIncomingType = normalizeMdiQuestionTypeAlias(payload.type);

  const optionTypeCandidates = matchedQuestionType === "single_option"
    ? ["single_select", "select", "radio", "choice", "option"]
    : matchedQuestionType === "multiple_option"
    ? ["multiple_select", "multiselect", "checkbox", "choice"]
    : [];

  const booleanFallbackCandidates = isBooleanOptionSet(optionLabels)
    ? ["boolean"]
    : [];

  const candidates = [
    normalizedIncomingType,
    ...booleanFallbackCandidates,
    matchedQuestionType,
    ...optionTypeCandidates,
  ].filter((value, index, values) => value && values.indexOf(value) === index);

  return candidates;
}

function buildMdiCaseQuestionRequestVariants(params: {
  payload: MdiCaseQuestionPayload;
  matchedQuestion: JsonRecord | null;
  displayedOptions: string[];
  typeCandidates: string[];
}): Array<
  { payload: MdiCaseQuestionRequestPayload; typeLabel: string | null }
> {
  const { payload, matchedQuestion, displayedOptions, typeCandidates } = params;

  const matchedQuestionId = parseNonEmptyString(
    matchedQuestion?.partner_questionnaire_question_id,
  );
  const basePayload: MdiCaseQuestionRequestPayload = {
    ...payload,
    partner_questionnaire_question_id:
      payload.partner_questionnaire_question_id ?? matchedQuestionId ??
        undefined,
    displayed_options: displayedOptions.length > 0
      ? displayedOptions
      : payload.displayed_options,
  };

  const variants: MdiCaseQuestionRequestVariant[] = typeCandidates.map((
    typeCandidate,
  ) => ({
    payload: {
      ...basePayload,
      type: typeCandidate,
    },
    typeLabel: typeCandidate,
  }));

  variants.push({
    payload: Object.fromEntries(
      Object.entries(basePayload).filter(([key]) => key !== "type"),
    ) as MdiCaseQuestionRequestPayload,
    typeLabel: null,
  });

  return variants;
}

function parseMdiCaseQuestionPayload(
  value: unknown,
): MdiCaseQuestionPayload | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  const payload = value as JsonRecord;
  const question = parseNonEmptyString(payload.question);
  const type = parseNonEmptyString(payload.type);
  const normalizedType = type ? normalizeMdiQuestionTypeAlias(type) : null;
  const isFileQuestion = type ? isMdiFileQuestionType(type) : false;
  const answer = parseNonEmptyString(payload.answer);

  if (!question || !type || (!isFileQuestion && !answer)) {
    return null;
  }

  const parsedPayload: MdiCaseQuestionPayload = {
    question,
    answer: answer ?? undefined,
    type: normalizedType ?? type,
  };

  const partnerQuestionnaireQuestionId =
    parseNonEmptyString(payload.partner_questionnaire_question_id) ??
      parseNonEmptyString(payload.question_id);
  if (partnerQuestionnaireQuestionId) {
    parsedPayload.partner_questionnaire_question_id =
      partnerQuestionnaireQuestionId;
  }

  if (typeof payload.important === "boolean") {
    parsedPayload.important = payload.important;
  }
  if (typeof payload.critical === "boolean") {
    parsedPayload.critical = payload.critical;
  } else if (typeof payload.is_critical === "boolean") {
    parsedPayload.critical = payload.is_critical;
  }
  if (typeof payload.display_in_pdf === "boolean") {
    parsedPayload.display_in_pdf = payload.display_in_pdf;
  }

  const description = parseOptionalString(payload.description);
  const label = parseOptionalString(payload.label);
  const metadata = parseOptionalString(payload.metadata);
  const displayedOptions = parseDisplayedOptions(payload.displayed_options);

  if (description !== undefined) parsedPayload.description = description;
  if (label !== undefined) parsedPayload.label = label;
  if (metadata !== undefined) parsedPayload.metadata = metadata;
  if (displayedOptions !== undefined) {
    parsedPayload.displayed_options = displayedOptions;
  }

  const fileType = parseNonEmptyString(payload.file_type);
  if (fileType) {
    parsedPayload.file_type = fileType;
  }

  return parsedPayload;
}

function inferExtensionFromMimeType(mimeType: string): string {
  const normalized = mimeType.toLowerCase();
  switch (normalized) {
    case "image/jpeg":
      return "jpg";
    case "image/png":
      return "png";
    case "image/gif":
      return "gif";
    case "image/webp":
      return "webp";
    case "application/pdf":
      return "pdf";
    case "text/plain":
      return "txt";
    default: {
      const subtype = normalized.split("/")[1]?.split(";")[0]?.trim();
      return subtype && subtype.length > 0 ? subtype : "bin";
    }
  }
}

function buildMdiBase64Filename(question: string, mimeType: string): string {
  const baseName = question
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);

  const fallbackName = baseName.length > 0 ? baseName : "upload";
  return `${fallbackName}.${inferExtensionFromMimeType(mimeType)}`;
}

function decodeMdiBase64FileAnswer(
  question: string,
  answer: string,
): File | null {
  const match = answer.match(/^data:([^;,]+);base64,([A-Za-z0-9+/=\s]+)$/i);
  if (!match) {
    return null;
  }

  const mimeType = match[1].trim();
  const base64Payload = match[2].replace(/\s+/g, "");
  if (!mimeType || !base64Payload) {
    return null;
  }

  try {
    const binary = atob(base64Payload);
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
    return new File(
      [bytes],
      buildMdiBase64Filename(question, mimeType),
      { type: mimeType },
    );
  } catch {
    return null;
  }
}

export async function parseMdiCaseQuestionBody(
  req: Request,
): Promise<MdiCaseQuestionPayload[] | null> {
  const contentType = req.headers.get("content-type")?.toLowerCase() || "";

  if (contentType.includes("multipart/form-data")) {
    const formData = await req.formData().catch(() => null);
    if (!formData) return null;

    const rawQuestions = formData.get("questions");
    if (typeof rawQuestions !== "string" || rawQuestions.trim().length === 0) {
      return null;
    }

    const parsedQuestions = JSON.parse(rawQuestions) as unknown;
    if (!Array.isArray(parsedQuestions) || parsedQuestions.length === 0) {
      return null;
    }

    const payloads: MdiCaseQuestionPayload[] = [];
    const consumedFields = new Set<string>();

    for (const [index, entry] of parsedQuestions.entries()) {
      const parsedPayload = parseMdiCaseQuestionPayload(entry);
      if (!parsedPayload) {
        return null;
      }

      if (isMdiFileQuestionType(parsedPayload.type)) {
        if (!parsedPayload.file) {
          const resolvedFile = resolveMdiMultipartFile(
            formData,
            parsedPayload,
            entry as JsonRecord,
            consumedFields,
            index,
          );
          if (!resolvedFile) {
            return null;
          }

          parsedPayload.file = resolvedFile.file;
          parsedPayload.answer = resolvedFile.fieldName;
          consumedFields.add(resolvedFile.fieldName);
        }
      }

      payloads.push(parsedPayload);
    }

    return payloads;
  }

  const body = await req.json().catch(() => null);
  if (!Array.isArray(body) || body.length === 0) {
    return null;
  }

  const parsedPayloads = body.map((entry) => {
    const parsedPayload = parseMdiCaseQuestionPayload(entry);
    if (!parsedPayload) {
      return null;
    }

    if (isMdiFileQuestionType(parsedPayload.type) && parsedPayload.answer) {
      parsedPayload.file = decodeMdiBase64FileAnswer(
        parsedPayload.question,
        parsedPayload.answer,
      ) ?? undefined;
    }

    return parsedPayload;
  }).filter((entry): entry is MdiCaseQuestionPayload => entry !== null);

  return parsedPayloads.length === body.length ? parsedPayloads : null;
}

function parseDateOnly(value: unknown): string | null {
  const trimmed = parseNonEmptyString(value);
  if (!trimmed) return null;
  return /^\d{4}-\d{2}-\d{2}$/.test(trimmed) ? trimmed : null;
}

function roundToTwo(value: number): number {
  return Math.round(value * 100) / 100;
}

function parseWeightToKilograms(value: unknown): number | null {
  const trimmed = parseNonEmptyString(value);
  if (!trimmed) return null;
  const match = trimmed.match(/(-?\d+(?:\.\d+)?)/);
  if (!match) return null;
  const numericValue = Number(match[1]);
  if (!Number.isFinite(numericValue)) return null;
  const normalized = trimmed.toLowerCase();
  if (normalized.includes("lb")) {
    return roundToTwo(numericValue / 2.20462);
  }
  return roundToTwo(numericValue);
}

export function parseHeightToCentimeters(value: unknown): number | null {
  const trimmed = parseNonEmptyString(value);
  if (!trimmed) return null;
  const normalized = trimmed.toLowerCase();
  const numericMatches = Array.from(
    trimmed.matchAll(/(-?\d+(?:\.\d+)?)/g),
    (match) => Number(match[1]),
  ).filter((entry) => Number.isFinite(entry));

  if (numericMatches.length === 0) return null;

  if (normalized.includes("cm")) {
    return Math.round(numericMatches[0]);
  }

  if (normalized.includes("ft")) {
    const feet = numericMatches[0];
    const inches = numericMatches[1] ?? 0;
    return Math.round((feet * 12 + inches) * 2.54);
  }

  if (normalized.includes("in")) {
    return Math.round(numericMatches[0] * 2.54);
  }

  return Math.round(numericMatches[0]);
}

function mapGenderToIso5218(value: unknown): number | null {
  if (typeof value === "number" && Number.isInteger(value)) {
    return value;
  }
  const normalized = parseNonEmptyString(value)?.toLowerCase();
  switch (normalized) {
    case "male":
      return 1;
    case "female":
      return 2;
    case "other":
    case "non-binary":
    case "nonbinary":
    case "not_applicable":
    case "not applicable":
      return 9;
    case "unknown":
    case "prefer_not_to_say":
    case "prefer not to say":
      return 0;
    default:
      return null;
  }
}

function parseDateOnlyOrPassthrough(value: unknown): string | null {
  return parseJotformDateOnly(value);
}

function stringifyAllergies(value: unknown): string | null {
  if (typeof value === "string") {
    const trimmed = value.trim();
    return trimmed.length > 0 ? trimmed : null;
  }

  const allergies = parseRecordArray(value)
    .map((entry) => {
      const medication = parseNonEmptyString(entry.Medication) ||
        parseNonEmptyString(entry.medication) ||
        parseNonEmptyString(entry.allergy);
      const reaction = parseNonEmptyString(entry.Reaction) ||
        parseNonEmptyString(entry.reaction);

      if (medication && reaction) return `${medication} (${reaction})`;
      return medication || reaction || null;
    })
    .filter((entry): entry is string => entry !== null);

  return allergies.length > 0 ? allergies.join("; ") : null;
}

function compactObject(
  record: Record<string, unknown>,
): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(record).filter(([, value]) =>
      value !== null && value !== undefined
    ),
  );
}

function normalizeMdiPatientProfilePayload(
  patientData: JsonRecord,
): JsonRecord {
  return compactObject({
    gender: mapGenderToIso5218(patientData.gender),
    date_of_birth: parseDateOnlyOrPassthrough(patientData.date_of_birth) ||
      parseDateOnly(patientData.birth_date),
    weight: parseWeightToKilograms(patientData.weight) ??
      parseWeightToKilograms(patientData.weight_lbs),
    height: parseHeightToCentimeters(patientData.height) ??
      parseHeightToCentimeters(patientData.height_ft),
    special_necessities: parseNonEmptyString(patientData.special_necessities),
    current_medications: parseNonEmptyString(patientData.current_medications),
    medical_conditions: parseNonEmptyString(patientData.medical_conditions),
    allergies: stringifyAllergies(patientData.allergies),
    pregnancy: parseBooleanLike(patientData.pregnancy),
  });
}

async function updateMdiPatientProfile(params: {
  backendUrl: string;
  accessToken: string;
  providerPatientId: string;
  patientData: JsonRecord;
  requestId: string;
}): Promise<unknown> {
  const {
    backendUrl,
    accessToken,
    providerPatientId,
    patientData,
    requestId,
  } = params;
  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/patients/${
    encodeURIComponent(providerPatientId)
  }`;

  const response = await fetch(endpoint, {
    method: "PATCH",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
    body: JSON.stringify(patientData),
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI patient update failed for ${providerPatientId}: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  return responseBody;
}

async function createMdiCaseQuestion(params: {
  backendUrl: string;
  accessToken: string;
  providerOrderId: string;
  payload: MdiCaseQuestionRequestPayload;
  requestId: string;
}): Promise<unknown> {
  const { backendUrl, accessToken, providerOrderId, payload, requestId } =
    params;
  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases/${
    encodeURIComponent(providerOrderId)
  }/questions`;

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
    body: JSON.stringify(payload),
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI case question create failed for ${providerOrderId}: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  return responseBody;
}

async function uploadMdiPartnerFile(params: {
  backendUrl: string;
  accessToken: string;
  file: File;
  name: string;
  fileType: string;
  requestId: string;
}): Promise<Record<string, unknown>> {
  const { backendUrl, accessToken, file, name, fileType, requestId } = params;
  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/files`;
  const formData = new FormData();
  formData.set("name", name);
  formData.set("file", file);
  formData.set("type", fileType);

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
    body: formData,
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI file upload failed for "${name}": ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  if (
    !responseBody || typeof responseBody !== "object" ||
    Array.isArray(responseBody)
  ) {
    throw new Error(`MDI file upload response must be an object for "${name}"`);
  }

  return responseBody as Record<string, unknown>;
}

async function attachMdiFileToCase(params: {
  backendUrl: string;
  accessToken: string;
  providerOrderId: string;
  fileId: string;
  requestId: string;
}): Promise<unknown> {
  const { backendUrl, accessToken, providerOrderId, fileId, requestId } =
    params;
  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases/${
    encodeURIComponent(providerOrderId)
  }/files/${encodeURIComponent(fileId)}`;

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI file attach failed for case ${providerOrderId} and file ${fileId}: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  return responseBody;
}

export async function handleMdiPatientQuestionnaireRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  order: OrderRow;
  providerPlatformLink: OrderProviderPlatformLinkRow | null;
}): Promise<Response> {
  const { supabase, req, order, providerPlatformLink } = params;

  const mdiIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "md_integrations",
  });

  if (!mdiIntegration) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations integration not found",
        message:
          "No enabled MD Integrations integration found for the order tenant",
      },
      404,
    );
  }

  if (
    providerPlatformLink &&
    providerPlatformLink.tenant_integration_id !== mdiIntegration.id
  ) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          "The order is linked to a different provider platform and cannot be fetched from MD Integrations",
      },
      409,
    );
  }

  const questionnaireDefinition = mdiIntegration.settings
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
          "The tenant MD Integrations integration does not have a valid patient_questionnaire_definition object configured",
      },
      404,
    );
  }

  return jsonResponse(req, {
    orderId: order.id,
    provider: "MDI",
    providerOrderId: providerPlatformLink?.provider_order_id || null,
    patientQuestionnaire: questionnaireDefinition,
    symptomsCount: 0,
    symptomsQuestionCount: 0,
  });
}

export async function handleMdiQuestionnairesRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  order: OrderRow;
  providerPlatformLink: OrderProviderPlatformLinkRow | null;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, order, providerPlatformLink, requestId } = params;

  const mdiIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "md_integrations",
  });

  if (!mdiIntegration) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations integration not found",
        message:
          "No enabled MD Integrations integration found for the order tenant",
      },
      404,
    );
  }

  if (
    providerPlatformLink &&
    providerPlatformLink.tenant_integration_id !== mdiIntegration.id
  ) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          "The order is linked to a different provider platform and cannot be fetched from MD Integrations",
      },
      409,
    );
  }

  if (!order.product_id) {
    return jsonResponse(
      req,
      {
        error: "Product not found",
        message: "The order does not have an associated product",
      },
      404,
    );
  }

  const productProviderPlatform = await fetchProductProviderPlatform({
    supabase,
    productId: order.product_id,
    tenantIntegrationId: mdiIntegration.id,
  });

  const jotformIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: JOTFORM_INTEGRATION_KEY,
  });

  const questionnairePresentation = resolveQuestionnairePresentation({
    order,
    providerKey: order.provider_platform_integration_key || "md_integrations",
    productProviderPlatform,
    jotformIntegration,
  });

  if (questionnairePresentation.type === "jotform") {
    return jsonResponse(req, {
      orderId: order.id,
      provider: "MDI",
      providerOrderId: providerPlatformLink?.provider_order_id || null,
      questionnairePresentation,
      questionnaireInstanceIds: [],
      questionnaires: {},
    });
  }

  const backendUrl = getStringSetting(mdiIntegration.settings, "backend_url");
  if (!backendUrl) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message:
          "MD Integrations integration is missing backend_url configuration",
      },
      500,
    );
  }

  const medicationOfferings = await fetchMdiMedicationOfferingsForProduct({
    supabase,
    productId: order.product_id,
  });

  if (medicationOfferings.length === 0) {
    return jsonResponse(
      req,
      {
        error: "MDI questionnaire not configured",
        message:
          "No linked medications with medication-level MDI offering_id values are configured for the product associated with this order",
      },
      404,
    );
  }

  const authResult = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: mdiIntegration.id,
    tenantId: mdiIntegration.tenant_id,
    settings: mdiIntegration.settings,
    baseUrl: backendUrl,
    requestId,
    source: "provider-platform-bridge",
  });

  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message: authResult.errorMessage,
      },
      500,
    );
  }

  let questionnaireEntries: Array<[string, MdiQuestionnaire]> = [];
  let questionnaireIds: string[] = [];
  let questionnaireMedicationMatches: MdiQuestionnaireMedicationMatch[] = [];

  const resolvedQuestionnaires =
    await fetchMdiQuestionnaireEntriesForMedicationOfferings({
      backendUrl,
      accessToken: authResult.accessToken,
      requestId,
      medicationOfferings,
    });

  if (resolvedQuestionnaires.errorMessage) {
    return jsonResponse(
      req,
      {
        error: "MDI questionnaire not configured",
        message: resolvedQuestionnaires.errorMessage,
      },
      404,
    );
  }

  questionnaireEntries = resolvedQuestionnaires.questionnaireEntries;
  questionnaireIds = resolvedQuestionnaires.questionnaireIds;
  questionnaireMedicationMatches =
    resolvedQuestionnaires.questionnaireMedicationMatches;

  await persistMdiQuestionnaireIds({
    supabase,
    order,
    tenantIntegrationId: mdiIntegration.id,
    providerPlatformLink,
    questionnaireIds,
  });

  const questionnaires = Object.fromEntries(
    questionnaireEntries.map(([questionnaireId, questionnaire]) => [
      questionnaireId,
      applyProviderLegalAgreementToAgreementQuestions(
        questionnaire,
        mdiIntegration.provider_legal_agreement,
      ),
    ]),
  );

  return jsonResponse(req, {
    orderId: order.id,
    provider: "MDI",
    providerOrderId: providerPlatformLink?.provider_order_id || null,
    questionnairePresentation,
    questionnaireInstanceIds: questionnaireIds,
    medicationOfferings,
    questionnaireMedicationMatches,
    questionnaires,
  });
}

export async function handleMdiUpdatePatientProfileRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  order: OrderRow;
  providerPlatformLink: OrderProviderPlatformLinkRow | null;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, order, providerPlatformLink, requestId } = params;

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

  const mdiIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "md_integrations",
  });

  if (!mdiIntegration) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations integration not found",
        message:
          "No enabled MD Integrations integration found for the order tenant",
      },
      404,
    );
  }

  if (
    providerPlatformLink &&
    providerPlatformLink.tenant_integration_id !== mdiIntegration.id
  ) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          "The order is linked to a different provider platform and cannot be fetched from MD Integrations",
      },
      409,
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

  const patientProviderPlatformLink = await fetchPatientProviderPlatformLink({
    supabase,
    patientId: order.patient_id,
    tenantId: order.tenant_id,
    tenantIntegrationId: mdiIntegration.id,
  });

  if (!patientProviderPlatformLink?.provider_patient_id) {
    return jsonResponse(
      req,
      {
        error: "MDI patient id not found",
        message:
          "No MD Integrations patient id is stored for the patient associated with this order",
      },
      404,
    );
  }

  const backendUrl = getStringSetting(mdiIntegration.settings, "backend_url");
  if (!backendUrl) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message:
          "MD Integrations integration is missing backend_url configuration",
      },
      500,
    );
  }

  const authResult = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: mdiIntegration.id,
    tenantId: mdiIntegration.tenant_id,
    settings: mdiIntegration.settings,
    baseUrl: backendUrl,
    requestId,
    source: "provider-platform-bridge",
  });

  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message: authResult.errorMessage,
      },
      500,
    );
  }

  const mdiPatientData = normalizeMdiPatientProfilePayload(body.patientData);

  let updateResponse: unknown;
  try {
    updateResponse = await updateMdiPatientProfile({
      backendUrl,
      accessToken: authResult.accessToken,
      providerPatientId: patientProviderPlatformLink.provider_patient_id,
      patientData: mdiPatientData,
      requestId,
    });
  } catch (error) {
    await logOrderProcessingFailure({
      supabase,
      orderId: order.id,
      requestId,
      code: "mdi_patient_profile_patch_failed",
      message: normalizeProcessingErrorMessage(error),
    });
    throw error;
  }

  const orderStatusAdvance = await advanceOrderToNextStatus({
    supabase,
    order,
    note: "Patient Questionnaire has been submitted.",
    requestId,
    source: "provider-platform-bridge:mdi-patient-questionnaire",
    // Guard: never advance a questionnaire submission past the provider-review gate.
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
    provider: "MDI",
    providerOrderId: providerPlatformLink?.provider_order_id || null,
    providerPatientId: patientProviderPlatformLink.provider_patient_id,
    orderStatusAdvanced: orderStatusAdvance.advanced,
    previousStatusKey: orderStatusAdvance.previousStatusKey,
    newStatusKey: orderStatusAdvance.newStatusKey,
    orderLifecycleTriggered,
    response: updateResponse,
  });
}

export async function handleMdiMedicalQuestionsRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  order: OrderRow;
  providerPlatformLink: OrderProviderPlatformLinkRow | null;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, order, providerPlatformLink, requestId } = params;

  const body = await parseMdiCaseQuestionBody(req);
  if (!body) {
    return jsonResponse(
      req,
      {
        error: "Invalid request body",
        message:
          "Provide a non-empty array of objects with question, answer, and type fields, plus optional important, critical, display_in_pdf, description, label, metadata, and displayed_options",
      },
      400,
    );
  }

  const mdiIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "md_integrations",
  });

  if (!mdiIntegration) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations integration not found",
        message:
          "No enabled MD Integrations integration found for the order tenant",
      },
      404,
    );
  }

  if (
    providerPlatformLink &&
    providerPlatformLink.tenant_integration_id !== mdiIntegration.id
  ) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          "The order is linked to a different provider platform and cannot be fetched from MD Integrations",
      },
      409,
    );
  }

  if (!providerPlatformLink?.provider_order_id) {
    return jsonResponse(
      req,
      {
        error: "MDI case id not found",
        message: "No MD Integrations case id is stored for the requested order",
      },
      404,
    );
  }

  const providerOrderId = providerPlatformLink.provider_order_id;
  if (!order.product_id) {
    return jsonResponse(
      req,
      {
        error: "Product not found",
        message: "The order does not have an associated product",
      },
      404,
    );
  }

  const backendUrl = getStringSetting(mdiIntegration.settings, "backend_url");
  if (!backendUrl) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message:
          "MD Integrations integration is missing backend_url configuration",
      },
      500,
    );
  }

  const authResult = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: mdiIntegration.id,
    tenantId: mdiIntegration.tenant_id,
    settings: mdiIntegration.settings,
    baseUrl: backendUrl,
    requestId,
    source: "provider-platform-bridge",
  });

  if ("errorMessage" in authResult) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message: authResult.errorMessage,
      },
      500,
    );
  }

  const medicationOfferings = await fetchMdiMedicationOfferingsForProduct({
    supabase,
    productId: order.product_id,
  });

  if (medicationOfferings.length === 0) {
    return jsonResponse(
      req,
      {
        error: "MDI questionnaire not configured",
        message:
          "No linked medications with medication-level MDI offering_id values are configured for the product associated with this order",
      },
      404,
    );
  }

  const resolvedQuestionnaires =
    await fetchMdiQuestionnaireEntriesForMedicationOfferings({
      backendUrl,
      accessToken: authResult.accessToken,
      requestId,
      medicationOfferings,
    });

  if (resolvedQuestionnaires.errorMessage) {
    return jsonResponse(
      req,
      {
        error: "MDI questionnaire not configured",
        message: resolvedQuestionnaires.errorMessage,
      },
      404,
    );
  }

  const questionnaireEntries = resolvedQuestionnaires.questionnaireEntries;
  const questionnaireIds = resolvedQuestionnaires.questionnaireIds;
  const questionnaires = questionnaireEntries.map(([, questionnaire]) =>
    questionnaire
  );

  const normalizedBody = body.map((payload) => {
    const { questionnaire, question: matchedQuestion } =
      findMdiQuestionnaireQuestionMatch(
        questionnaires,
        payload,
      );
    const typeCandidates = buildMdiCaseQuestionTypeCandidates({
      questionnaire,
      payload,
    });
    const displayedOptions = payload.displayed_options?.length
      ? payload.displayed_options
      : extractQuestionOptionLabels(matchedQuestion);

    return {
      originalPayload: payload,
      typeCandidates,
      requestVariants: buildMdiCaseQuestionRequestVariants({
        payload,
        matchedQuestion,
        displayedOptions,
        typeCandidates,
      }),
      matchedQuestion,
      displayedOptions,
    };
  });

  const createResponses = [];
  for (const entry of normalizedBody) {
    if (isMdiFileQuestionType(entry.originalPayload.type)) {
      if (!entry.originalPayload.file) {
        console.error("MDI file question submission failed", {
          requestId,
          orderId: order.id,
          providerOrderId,
          questionnaireIds,
          question: entry.originalPayload.question,
          error: "File payload missing for file-type question",
        });
        return jsonResponse(
          req,
          {
            error: "Invalid request body",
            message:
              "File-type MDI questions require multipart/form-data with a questions JSON field and attached file entries",
          },
          400,
        );
      }

      const uploadedFile = await uploadMdiPartnerFile({
        backendUrl,
        accessToken: authResult.accessToken,
        file: entry.originalPayload.file,
        name: entry.originalPayload.question,
        fileType: entry.originalPayload.file_type || "lab-result",
        requestId,
      });

      const fileId = parseNonEmptyString(uploadedFile.file_id);
      if (!fileId) {
        throw new Error(
          `MDI file upload did not return file_id for "${entry.originalPayload.question}"`,
        );
      }

      createResponses.push(
        await attachMdiFileToCase({
          backendUrl,
          accessToken: authResult.accessToken,
          providerOrderId,
          fileId,
          requestId,
        }),
      );
      continue;
    }

    let lastError: unknown = null;
    let succeeded = false;

    for (let index = 0; index < entry.requestVariants.length; index += 1) {
      const attempt = entry.requestVariants[index];
      const attemptPayload = attempt.payload;

      try {
        createResponses.push(
          await createMdiCaseQuestion({
            backendUrl,
            accessToken: authResult.accessToken,
            providerOrderId,
            payload: attemptPayload,
            requestId,
          }),
        );
        succeeded = true;
        break;
      } catch (error) {
        lastError = error;
        const shouldRetry = isMdiInvalidTypeError(error) &&
          index < entry.requestVariants.length - 1;

        if (shouldRetry) {
          console.warn("MDI case question submission type retry", {
            requestId,
            orderId: order.id,
            providerOrderId,
            questionnaireIds,
            question: attemptPayload.question,
            partnerQuestionnaireQuestionId:
              attemptPayload.partner_questionnaire_question_id ?? null,
            rejectedType: attempt.typeLabel,
            nextTypeCandidate: entry.requestVariants[index + 1]?.typeLabel ??
              null,
            matchedQuestion: entry.matchedQuestion
              ? {
                partner_questionnaire_question_id:
                  entry.matchedQuestion.partner_questionnaire_question_id ??
                    null,
                title: entry.matchedQuestion.title ?? null,
                label: entry.matchedQuestion.label ?? null,
                type: entry.matchedQuestion.type ?? null,
                order: entry.matchedQuestion.order ?? null,
                options: Array.isArray(entry.matchedQuestion.options)
                  ? entry.matchedQuestion.options
                  : null,
              }
              : null,
            displayedOptions: attemptPayload.displayed_options ?? null,
            error: error instanceof Error ? error.message : String(error),
          });
          continue;
        }

        break;
      }
    }

    if (!succeeded) {
      console.error("MDI case question submission failed", {
        requestId,
        orderId: order.id,
        providerOrderId,
        questionnaireIds,
        question: entry.originalPayload.question,
        partnerQuestionnaireQuestionId:
          entry.requestVariants[0]?.payload.partner_questionnaire_question_id ??
            entry.originalPayload.partner_questionnaire_question_id ?? null,
        incomingType: entry.originalPayload.type,
        attemptedTypes: entry.requestVariants.map((variant) =>
          variant.typeLabel
        ),
        answer: entry.originalPayload.answer,
        matchedQuestion: entry.matchedQuestion
          ? {
            partner_questionnaire_question_id:
              entry.matchedQuestion.partner_questionnaire_question_id ?? null,
            title: entry.matchedQuestion.title ?? null,
            label: entry.matchedQuestion.label ?? null,
            type: entry.matchedQuestion.type ?? null,
            order: entry.matchedQuestion.order ?? null,
            options: Array.isArray(entry.matchedQuestion.options)
              ? entry.matchedQuestion.options
              : null,
          }
          : null,
        displayedOptions: entry.displayedOptions.length > 0
          ? entry.displayedOptions
          : null,
        error: lastError instanceof Error
          ? lastError.message
          : String(lastError),
      });
      throw lastError;
    }
  }

  const holdStatusResponse = await updateMdiCaseHoldStatus({
    backendUrl,
    accessToken: authResult.accessToken,
    providerOrderId,
    holdStatus: false,
    requestId,
  });

  const orderStatusAdvance = await advanceOrderToNextStatus({
    supabase,
    order,
    note: "MDI medical questions have been submitted. Case hold released.",
    requestId,
    source: "provider-platform-bridge:mdi-medical-questionnaire",
    // Guard: never advance a questionnaire submission past the provider-review gate.
    expectedFromStatusKeys: QUESTIONNAIRE_ADVANCE_FROM_STATUSES,
  });

  const orderLifecycleTriggered = await triggerOrderLifecycleForOrder({
    orderId: order.id,
    tenantId: order.tenant_id,
    requestId,
  });

  return jsonResponse(req, {
    orderId: order.id,
    provider: "MDI",
    providerOrderId,
    orderStatusAdvanced: orderStatusAdvance.advanced,
    previousStatusKey: orderStatusAdvance.previousStatusKey,
    newStatusKey: orderStatusAdvance.newStatusKey,
    orderLifecycleTriggered,
    holdStatusReleased: true,
    holdStatusResponse,
    response: createResponses,
  });
}

// ---------------------------------------------------------------------------
// MDI Case Hold Status
// ---------------------------------------------------------------------------

export async function updateMdiCaseHoldStatus(params: {
  backendUrl: string;
  accessToken: string;
  providerOrderId: string;
  holdStatus: boolean;
  requestId: string;
}): Promise<unknown> {
  const { backendUrl, accessToken, providerOrderId, holdStatus, requestId } =
    params;
  const endpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases/${
    encodeURIComponent(providerOrderId)
  }/status`;

  console.debug("mdi: updating case hold_status", {
    requestId,
    providerOrderId,
    holdStatus,
    endpoint,
  });

  const response = await fetch(endpoint, {
    method: "PATCH",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${accessToken}`,
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
    body: JSON.stringify({ hold_status: holdStatus }),
  });

  const rawResponse = await response.text();
  let responseBody: unknown = null;
  if (rawResponse) {
    try {
      responseBody = JSON.parse(rawResponse);
    } catch {
      responseBody = rawResponse;
    }
  }

  console.debug("mdi: case hold_status update response", {
    requestId,
    providerOrderId,
    holdStatus,
    httpStatus: response.status,
    httpStatusText: response.statusText,
    responseBody,
  });

  if (!response.ok && response.status === 403 && holdStatus === false) {
    const caseEndpoint = `${backendUrl.replace(/\/+$/, "")}/v1/partner/cases/${
      encodeURIComponent(providerOrderId)
    }`;

    console.warn(
      "mdi: hold_status PATCH forbidden; checking case current hold_status",
      {
        requestId,
        providerOrderId,
        caseEndpoint,
      },
    );

    const caseResponse = await fetch(caseEndpoint, {
      method: "GET",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${accessToken}`,
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
    });

    const rawCaseResponse = await caseResponse.text();
    let caseBody: unknown = null;
    if (rawCaseResponse) {
      try {
        caseBody = JSON.parse(rawCaseResponse);
      } catch {
        caseBody = rawCaseResponse;
      }
    }

    const caseHoldStatus = caseBody && typeof caseBody === "object" &&
        !Array.isArray(caseBody)
      ? (caseBody as JsonRecord).hold_status
      : undefined;

    console.debug("mdi: case hold_status verification response", {
      requestId,
      providerOrderId,
      httpStatus: caseResponse.status,
      httpStatusText: caseResponse.statusText,
      holdStatus: caseHoldStatus,
      responseBody: caseBody,
    });

    if (caseResponse.ok && caseHoldStatus === false) {
      console.info(
        "mdi: hold_status already false; proceeding after forbidden update response",
        {
          requestId,
          providerOrderId,
        },
      );
      return caseBody;
    }
  }

  if (!response.ok) {
    throw new Error(
      `MDI case hold_status update failed for ${providerOrderId}: ${
        extractErrorMessage(
          responseBody,
          `${response.status} ${response.statusText}`.trim(),
        )
      }`,
    );
  }

  return responseBody;
}

// ---------------------------------------------------------------------------
// JotForm Submission → MDI Processing
// ---------------------------------------------------------------------------

async function downloadFileFromUrl(params: {
  fileUrl: string;
  apiKey: string;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<{ blob: Blob; fileName: string; contentType: string }> {
  const { fileUrl, apiKey, teamWorkspaceId, requestId } = params;

  console.debug("mdi: downloading file from JotForm", {
    requestId,
    fileUrl,
  });

  const headers: Record<string, string> = {
    APIKEY: apiKey,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  const response = await fetch(fileUrl, { headers });

  if (!response.ok) {
    throw new Error(
      `Failed to download file from ${fileUrl}: ${response.status} ${response.statusText}`,
    );
  }

  const blob = await response.blob();
  const urlPath = new URL(fileUrl).pathname;
  const fileName = decodeURIComponent(
    urlPath.split("/").pop() || "uploaded-file",
  );
  const contentType = response.headers.get("content-type") ||
    blob.type ||
    "application/octet-stream";

  return { blob, fileName, contentType };
}

function normalizeProcessingErrorMessage(error: unknown): string {
  const message = error instanceof Error ? error.message : String(error);
  return message.replace(/\s+/g, " ").trim().slice(0, 300);
}

async function logOrderProcessingFailure(params: {
  supabase: SupabaseAdminClient;
  orderId: string;
  requestId: string;
  code: string;
  message: string;
}): Promise<void> {
  const { supabase, orderId, requestId, code, message } = params;

  try {
    const order = await fetchOrderById(supabase, orderId);
    if (!order?.status_id) {
      console.warn("mdi: unable to log order processing failure", {
        requestId,
        orderId,
        code,
        reason: "missing_order_or_status_id",
      });
      return;
    }

    const notes =
      `[provider-platform-bridge][${code}] ${message} (requestId=${requestId})`;

    const { error } = await supabase
      .from("order_status_history")
      .insert({
        order_id: order.id,
        status_id: order.status_id,
        notes,
      });

    if (error) {
      console.warn("mdi: failed to insert order processing failure note", {
        requestId,
        orderId,
        code,
        error: error.message,
      });
    }
  } catch (error) {
    console.warn("mdi: failed to log order processing failure", {
      requestId,
      orderId,
      code,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

const PATIENT_QUESTIONNAIRE_PENDING_STATUS_KEY =
  "patient_questionnaire_pending";
const MEDICAL_QUESTIONNAIRE_PENDING_STATUS_KEY =
  "medical_questionnaire_pending";
const JOTFORM_ID_SKIP_CONFIRMATION_FIELD_NAME = "areYou";

const JOTFORM_IMPORTANT_QUESTIONS_FIELD_NAMES = new Set([
  "important_questions",
  "important_question_names",
  "jotform_important_questions",
  "mdi_important_questions",
]);

const JOTFORM_CRITICAL_QUESTIONS_FIELD_NAMES = new Set([
  "critical_questions",
  "critical_question_names",
  "is_critical_questions",
  "jotform_critical_questions",
  "mdi_critical_questions",
]);

const JOTFORM_QUESTION_FLAG_FIELD_NAMES = new Set([
  ...JOTFORM_IMPORTANT_QUESTIONS_FIELD_NAMES,
  ...JOTFORM_CRITICAL_QUESTIONS_FIELD_NAMES,
]);

const JOTFORM_IMPORTANT_FIELD_SUFFIXES = ["is_important", "important"];
const JOTFORM_CRITICAL_FIELD_SUFFIXES = ["is_critical", "critical"];

interface MdiJotformQuestionFlags {
  important?: boolean;
  critical?: boolean;
}

interface JotformOrderStatusGate {
  shouldProcess: boolean;
  order: OrderRow;
  currentStatusKey: string | null;
  skippedReason: string | null;
}

async function getJotformOrderStatusGate(params: {
  supabase: SupabaseAdminClient;
  order: OrderRow;
  expectedStatusKey: string;
}): Promise<JotformOrderStatusGate> {
  const { supabase, order, expectedStatusKey } = params;
  const latestOrder = await fetchOrderById(supabase, order.id);
  const effectiveOrder = latestOrder ?? order;
  const currentStatusKey = effectiveOrder.order_statuses?.status_key ?? null;

  if (currentStatusKey !== expectedStatusKey) {
    return {
      shouldProcess: false,
      order: effectiveOrder,
      currentStatusKey,
      skippedReason: currentStatusKey
        ? "unexpected_current_status"
        : "missing_current_status",
    };
  }

  return {
    shouldProcess: true,
    order: effectiveOrder,
    currentStatusKey,
    skippedReason: null,
  };
}

function normalizeJotformPatientDataKey(
  value: string | null | undefined,
): string | null {
  if (!value) return null;

  const normalized = value
    .replace(/([a-z0-9])([A-Z])/g, "$1_$2")
    .trim()
    .toLowerCase()
    .replace(/^q\d+_?/, "")
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");

  if (!normalized) return null;

  const aliases: Record<string, string> = {
    birth_date: "birth_date",
    date_birth: "birth_date",
    date_of_birth: "date_of_birth",
    dateofbirth: "date_of_birth",
    dob: "birth_date",
    gender: "gender",
    biological_gender: "gender",
    gender_biological: "gender",
    sex: "gender",
    weight: "weight",
    weight_value: "weight",
    weight_lbs: "weight_lbs",
    height: "height",
    height_value: "height",
    height_ft: "height_ft",
    allergies: "allergies",
    allergies_list: "allergies",
    medication_allergies: "allergies",
    allergy: "allergies",
    current_medications: "current_medications",
    current_medication: "current_medications",
    medications: "current_medications",
    medications_list: "current_medications",
    medication: "current_medications",
    medical_conditions: "medical_conditions",
    medical_condition: "medical_conditions",
    other_symptoms_text: "medical_conditions",
    conditions: "medical_conditions",
    condition: "medical_conditions",
    special_necessities: "special_necessities",
    special_needs: "special_necessities",
    pregnancy: "pregnancy",
    pregnant: "pregnancy",
  };

  return aliases[normalized] ?? null;
}

function getNormalizedJotformAnswerName(
  entry: Pick<JotformSubmissionAnswer, "name">,
): string | null {
  return normalizeJotformQuestionFlagToken(entry.name);
}

function parseJotformBooleanFlag(value: unknown): boolean | null {
  if (typeof value === "boolean") return value;
  if (typeof value === "number") {
    if (value === 1) return true;
    if (value === 0) return false;
  }

  const normalized = typeof value === "string"
    ? value.trim().toLowerCase()
    : stringifyJotformAnswer(value).trim().toLowerCase();
  if (!normalized) return null;

  if (
    ["1", "true", "yes", "y", "on", "important", "critical"].includes(
      normalized,
    )
  ) {
    return true;
  }
  if (["0", "false", "no", "n", "off", "none"].includes(normalized)) {
    return false;
  }

  return null;
}

function extractJotformQuestionFlagListTokens(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.flatMap((entry) =>
      extractJotformQuestionFlagListTokens(entry)
    );
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if (!trimmed) return [];

    if (
      (trimmed.startsWith("[") && trimmed.endsWith("]")) ||
      (trimmed.startsWith("{") && trimmed.endsWith("}"))
    ) {
      try {
        return extractJotformQuestionFlagListTokens(JSON.parse(trimmed));
      } catch {
        // Fall through to delimiter parsing below.
      }
    }

    return trimmed
      .split(/[\n,;|]+/)
      .map((token) => normalizeJotformQuestionFlagToken(token))
      .filter((token): token is string => token !== null);
  }

  const token = normalizeJotformQuestionFlagToken(value);
  return token ? [token] : [];
}

function collectJotformQuestionFlagList(params: {
  submission: JotformSubmissionContent;
  fieldNames: Set<string>;
}): Set<string> {
  const tokens = new Set<string>();

  for (const entry of Object.values(params.submission.answers ?? {})) {
    const fieldName = getNormalizedJotformAnswerName(entry);
    if (!fieldName || !params.fieldNames.has(fieldName)) continue;

    for (const token of extractJotformQuestionFlagListTokens(entry.answer)) {
      tokens.add(token);
    }
  }

  return tokens;
}

function getJotformQuestionFlagTargetFromFieldName(params: {
  fieldName: string | null;
  suffixes: string[];
}): string | null {
  const { fieldName, suffixes } = params;
  if (!fieldName) return null;

  for (const suffix of suffixes) {
    const suffixWithSeparator = `_${suffix}`;
    if (fieldName.endsWith(suffixWithSeparator)) {
      return fieldName.slice(0, -suffixWithSeparator.length) || null;
    }

    const prefixWithSeparator = `${suffix}_`;
    if (fieldName.startsWith(prefixWithSeparator)) {
      return fieldName.slice(prefixWithSeparator.length) || null;
    }
  }

  return null;
}

function getJotformQuestionFlagTarget(params: {
  entry: JotformSubmissionAnswer;
  suffixes: string[];
}): string | null {
  return getJotformQuestionFlagTargetFromFieldName({
    fieldName: getNormalizedJotformAnswerName(params.entry),
    suffixes: params.suffixes,
  });
}

function getJotformQuestionTokens(
  entry: Pick<JotformSubmissionAnswer, "name" | "text" | "order">,
): Set<string> {
  const tokens = new Set<string>();

  for (const value of [entry.name, entry.text, entry.order]) {
    const token = normalizeJotformQuestionFlagToken(value);
    if (token) tokens.add(token);
  }

  return tokens;
}

function entryTargetsJotformQuestion(params: {
  entry: JotformSubmissionAnswer;
  suffixes: string[];
  questionTokens: Set<string>;
}): boolean {
  const target = getJotformQuestionFlagTarget({
    entry: params.entry,
    suffixes: params.suffixes,
  });

  return !!target && params.questionTokens.has(target);
}

export function isJotformQuestionFlagConfigField(
  entry: Pick<JotformSubmissionAnswer, "name">,
): boolean {
  const fieldName = getNormalizedJotformAnswerName(entry);
  if (!fieldName) return false;
  if (JOTFORM_QUESTION_FLAG_FIELD_NAMES.has(fieldName)) return true;

  return !!getJotformQuestionFlagTargetFromFieldName({
    fieldName,
    suffixes: [
      ...JOTFORM_IMPORTANT_FIELD_SUFFIXES,
      ...JOTFORM_CRITICAL_FIELD_SUFFIXES,
    ],
  });
}

export function resolveMdiJotformQuestionFlags(params: {
  submission: JotformSubmissionContent;
  entry: Pick<JotformSubmissionAnswer, "name" | "text" | "order">;
}): MdiJotformQuestionFlags {
  const questionTokens = getJotformQuestionTokens(params.entry);
  const importantList = collectJotformQuestionFlagList({
    submission: params.submission,
    fieldNames: JOTFORM_IMPORTANT_QUESTIONS_FIELD_NAMES,
  });
  const criticalList = collectJotformQuestionFlagList({
    submission: params.submission,
    fieldNames: JOTFORM_CRITICAL_QUESTIONS_FIELD_NAMES,
  });

  const flags: MdiJotformQuestionFlags = {};
  if ([...questionTokens].some((token) => importantList.has(token))) {
    flags.important = true;
  }
  if ([...questionTokens].some((token) => criticalList.has(token))) {
    flags.critical = true;
  }

  for (const answer of Object.values(params.submission.answers ?? {})) {
    const parsedFlag = parseJotformBooleanFlag(answer.answer);
    if (parsedFlag !== true) continue;

    if (
      entryTargetsJotformQuestion({
        entry: answer,
        suffixes: JOTFORM_IMPORTANT_FIELD_SUFFIXES,
        questionTokens,
      })
    ) {
      flags.important = true;
    }

    if (
      entryTargetsJotformQuestion({
        entry: answer,
        suffixes: JOTFORM_CRITICAL_FIELD_SUFFIXES,
        questionTokens,
      })
    ) {
      flags.critical = true;
    }
  }

  return flags;
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

function normalizeJotformSubmissionQuestionnaireType(
  value: unknown,
): JotformSubmissionQuestionnaireType | null {
  const normalized = typeof value === "string"
    ? value.trim()
    : String(value ?? "")
      .trim();
  if (normalized === "patient_questionnaire") return "patient_questionnaire";
  if (normalized === "medical_questionnaire") return "medical_questionnaire";
  return null;
}

export function resolveMdiJotformEffectiveQuestionnaireType(params: {
  incomingQuestionnaireType: JotformSubmissionQuestionnaireType;
  submission: JotformSubmissionContent;
}): {
  effectiveQuestionnaireType: JotformSubmissionQuestionnaireType;
  submittedQuestionnaireType: JotformSubmissionQuestionnaireType | null;
} {
  const submittedQuestionnaireType =
    normalizeJotformSubmissionQuestionnaireType(
      getJotformAnswerStringByName(
        params.submission,
        JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
      ),
    );

  return {
    effectiveQuestionnaireType: params.incomingQuestionnaireType,
    submittedQuestionnaireType,
  };
}

function parseIntegerPart(value: unknown): number | null {
  if (typeof value === "number" && Number.isInteger(value)) return value;
  const trimmed = typeof value === "string" ? value.trim() : String(value ?? "")
    .trim();
  if (!/^\d+$/.test(trimmed)) return null;
  const parsed = Number(trimmed);
  return Number.isInteger(parsed) ? parsed : null;
}

function formatDateOnlyFromParts(
  yearValue: unknown,
  monthValue: unknown,
  dayValue: unknown,
): string | null {
  const year = parseIntegerPart(yearValue);
  const month = parseIntegerPart(monthValue);
  const day = parseIntegerPart(dayValue);

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

function parseJotformDateOnly(value: unknown): string | null {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    const record = value as JsonRecord;
    const fromRecord = formatDateOnlyFromParts(
      record.year,
      record.month,
      record.day,
    );
    if (fromRecord) return fromRecord;
  }

  const trimmed = stringifyJotformAnswer(value);
  if (!trimmed) return null;
  const dateToken = trimmed.split(/\s+/)[0] ?? trimmed;
  const yearFirst = dateToken.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
  if (yearFirst) {
    return formatDateOnlyFromParts(yearFirst[1], yearFirst[2], yearFirst[3]);
  }

  const dayFirst = dateToken.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
  if (dayFirst) {
    return formatDateOnlyFromParts(dayFirst[3], dayFirst[2], dayFirst[1]);
  }

  return null;
}

function stringifyJotformRecordRow(record: JsonRecord): string | null {
  const ignoredKeys = new Set(["id", "row_id", "rowid"]);
  const values = Object.entries(record)
    .filter(([key]) =>
      !ignoredKeys.has(
        key.trim().toLowerCase().replace(/[^a-z0-9]+/g, "_"),
      )
    )
    .map(([, value]) => stringifyJotformAnswer(value))
    .filter((value) => value.length > 0);

  return values.length > 0 ? values.join(" - ") : null;
}

function stringifyJotformListAnswer(value: unknown): string | null {
  if (typeof value === "string") {
    const trimmed = value.trim();
    if (trimmed.startsWith("[") || trimmed.startsWith("{")) {
      try {
        return stringifyJotformListAnswer(JSON.parse(trimmed));
      } catch {
        // Fall through to the raw string when it is not valid JSON.
      }
    }
  }

  if (Array.isArray(value)) {
    const rows = value
      .map((entry) =>
        entry && typeof entry === "object" && !Array.isArray(entry)
          ? stringifyJotformRecordRow(entry as JsonRecord)
          : stringifyJotformAnswer(entry)
      )
      .filter((entry): entry is string =>
        typeof entry === "string" && entry.length > 0
      );

    return rows.length > 0 ? rows.join("; ") : null;
  }

  if (value && typeof value === "object") {
    const record = value as JsonRecord;
    const nestedRecords = Object.values(record).filter((
      entry,
    ): entry is JsonRecord =>
      !!entry && typeof entry === "object" && !Array.isArray(entry)
    );
    if (
      nestedRecords.length > 0 &&
      nestedRecords.length === Object.keys(record).length
    ) {
      const rows = nestedRecords
        .map((entry) => stringifyJotformRecordRow(entry))
        .filter((entry): entry is string => entry !== null);
      return rows.length > 0 ? rows.join("; ") : null;
    }

    return stringifyJotformRecordRow(record);
  }

  const answerText = stringifyJotformAnswer(value);
  return answerText.length > 0 ? answerText : null;
}

function hasStructuredJotformListAnswer(value: unknown): boolean {
  if (typeof value === "string") {
    const trimmed = value.trim();
    if (trimmed.startsWith("[") || trimmed.startsWith("{")) {
      try {
        return hasStructuredJotformListAnswer(JSON.parse(trimmed));
      } catch {
        return false;
      }
    }
  }

  if (Array.isArray(value)) {
    return value.some((entry) =>
      !!entry && typeof entry === "object" && !Array.isArray(entry)
    );
  }

  if (value && typeof value === "object") {
    const record = value as JsonRecord;
    return Object.values(record).some((entry) =>
      !!entry && typeof entry === "object"
    );
  }

  return false;
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

function resolveJotformPatientMedicalConditions(
  submission: JotformSubmissionContent,
): string | null {
  const freeText = getJotformAnswerStringByName(
    submission,
    "other_symptoms_text",
  );
  if (freeText) return freeText;

  const option = getJotformAnswerStringByName(
    submission,
    "other_symptoms_options",
  );
  if (!option || /^no\b/i.test(option.trim())) return null;

  return option;
}

function addMdiPatientMetafield(
  metafields: JsonRecord[],
  params: {
    key: string;
    title: string;
    value: unknown;
    type?: string;
  },
): void {
  const answerText = hasStructuredJotformListAnswer(params.value)
    ? stringifyJotformListAnswer(params.value) ??
      stringifyJotformAnswer(params.value)
    : stringifyJotformAnswer(params.value);
  if (!answerText) return;

  metafields.push({
    key: params.key,
    title: params.title,
    value: answerText,
    type: params.type ?? "string",
  });
}

function buildMdiPatientMetafieldsFromJotformSubmission(
  submission: JotformSubmissionContent,
): JsonRecord[] {
  const metafields: JsonRecord[] = [];
  addMdiPatientMetafield(metafields, {
    key: "symptoms",
    title: "Reported symptoms",
    value: getJotformAnswerValueByName(submission, "symptoms"),
  });
  addMdiPatientMetafield(metafields, {
    key: "medications_confirm",
    title: "Medication confirmation",
    value: getJotformAnswerValueByName(submission, "medications_confirm"),
  });
  addMdiPatientMetafield(metafields, {
    key: "allergies_confirm",
    title: "Allergy confirmation",
    value: getJotformAnswerValueByName(submission, "allergies_confirm"),
  });
  addMdiPatientMetafield(metafields, {
    key: "other_symptoms_options",
    title: "Other symptoms option",
    value: getJotformAnswerValueByName(submission, "other_symptoms_options"),
  });
  addMdiPatientMetafield(metafields, {
    key: "id_verification_skipped_confirmation",
    title: "ID verification skipped confirmation",
    value: getJotformAnswerValueByName(
      submission,
      JOTFORM_ID_SKIP_CONFIRMATION_FIELD_NAME,
    ),
  });

  return metafields;
}

function buildMdiPatientDataFromJotformSubmission(
  submission: JotformSubmissionContent,
): JsonRecord {
  const patientData: JsonRecord = {};

  for (const entry of Object.values(submission.answers ?? {})) {
    if (isJotformUiOnlyField(entry.type)) continue;
    if (
      entry.name === JOTFORM_ORDER_ID_FIELD_NAME ||
      entry.name === JOTFORM_PROVIDER_KEY_FIELD_NAME ||
      entry.name === JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME ||
      entry.name === JOTFORM_ID_SKIP_CONFIRMATION_FIELD_NAME
    ) {
      continue;
    }
    if (isJotformFileUploadField(entry.type)) continue;

    const answerText = stringifyJotformAnswer(entry.answer);
    if (!answerText) continue;

    const key = normalizeJotformPatientDataKey(entry.name) ||
      normalizeJotformPatientDataKey(entry.text);
    if (!key) continue;

    patientData[key] = key === "current_medications" || key === "allergies"
      ? stringifyJotformListAnswer(entry.answer) ?? answerText
      : answerText;
  }

  patientData.gender = getJotformAnswerStringByName(
    submission,
    "biological_gender",
  ) ?? patientData.gender;
  patientData.date_of_birth = parseJotformDateOnly(
    getJotformAnswerValueByName(submission, "date_of_birth"),
  ) ?? patientData.date_of_birth;
  patientData.weight = buildJotformMeasuredAnswer({
    submission,
    valueFieldName: "weight_value",
    unitFieldName: "weight_unit",
  }) ?? patientData.weight;
  patientData.height = buildJotformMeasuredAnswer({
    submission,
    valueFieldName: "height_value",
    unitFieldName: "height_unit",
  }) ?? patientData.height;
  patientData.current_medications = stringifyJotformListAnswer(
    getJotformAnswerValueByName(submission, "medications_list"),
  ) ?? patientData.current_medications;
  patientData.allergies = stringifyJotformListAnswer(
    getJotformAnswerValueByName(submission, "allergies_list"),
  ) ?? patientData.allergies;
  patientData.medical_conditions =
    resolveJotformPatientMedicalConditions(submission) ??
      patientData.medical_conditions;

  return patientData;
}

export function buildMdiPatientProfilePayloadFromJotformSubmission(
  submission: JotformSubmissionContent,
): JsonRecord {
  const patientData = buildMdiPatientDataFromJotformSubmission(submission);
  const payload = normalizeMdiPatientProfilePayload(patientData);
  const metafields = buildMdiPatientMetafieldsFromJotformSubmission(
    submission,
  );

  if (metafields.length > 0) {
    payload.metafields = metafields;
  }

  return payload;
}

interface UploadedJotformMdiFile {
  fieldName: string;
  fieldText: string;
  fileId: string;
  fileName: string;
}

function isJotformIdUploadAnswer(entry: {
  name: string;
  text: string;
}): boolean {
  const normalizedName = entry.name
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");

  return isJotformIdFileField(entry.text) ||
    normalizedName === "id_file_from_camera" ||
    normalizedName === "id_file_from_gallery" ||
    normalizedName.includes("id_upload") ||
    normalizedName.includes("id_verification");
}

async function uploadJotformIdFilesToMdi(params: {
  submission: JotformSubmissionContent;
  jotformApiKey: string;
  jotformTeamWorkspaceId?: string | null;
  backendUrl: string;
  accessToken: string;
  requestId: string;
  orderId: string;
  submissionId: string;
}): Promise<UploadedJotformMdiFile[]> {
  const uploadedFiles: UploadedJotformMdiFile[] = [];

  for (const entry of Object.values(params.submission.answers ?? {})) {
    if (!isJotformIdUploadAnswer(entry)) continue;

    const fileUrls = extractJotformFileUrls(entry.answer);
    if (fileUrls.length === 0) continue;

    for (const fileUrl of fileUrls) {
      try {
        const { blob, fileName, contentType } = await downloadFileFromUrl({
          fileUrl,
          apiKey: params.jotformApiKey,
          teamWorkspaceId: params.jotformTeamWorkspaceId,
          requestId: params.requestId,
        });

        const file = new File([blob], fileName, { type: contentType });
        const uploadResult = await uploadMdiPartnerFile({
          backendUrl: params.backendUrl,
          accessToken: params.accessToken,
          file,
          name: fileName,
          fileType: "driver-license",
          requestId: params.requestId,
        });

        const fileId = parseNonEmptyString(uploadResult.file_id);
        if (!fileId) {
          throw new Error(
            `MDI file upload for "${fileName}" did not return file_id`,
          );
        }

        uploadedFiles.push({
          fieldName: entry.name,
          fieldText: entry.text,
          fileId,
          fileName,
        });

        console.info("mdi: patient ID file uploaded", {
          requestId: params.requestId,
          orderId: params.orderId,
          submissionId: params.submissionId,
          fieldName: entry.name,
          fieldText: entry.text,
          fileId,
          fileName,
        });
      } catch (error) {
        console.error("mdi: patient ID file upload failed", {
          requestId: params.requestId,
          orderId: params.orderId,
          submissionId: params.submissionId,
          fieldName: entry.name,
          fieldText: entry.text,
          fileUrl,
          error: error instanceof Error ? error.message : String(error),
        });
        throw error;
      }
    }
  }

  return uploadedFiles;
}

export async function handleJotformSubmissionProcessing(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  orderId: string;
  submissionId: string;
  questionnaireType: JotformSubmissionQuestionnaireType;
  requestId: string;
}): Promise<Response> {
  const {
    supabase,
    req,
    orderId,
    submissionId,
    questionnaireType,
    requestId,
  } = params;

  console.info("mdi: starting JotForm submission processing", {
    requestId,
    orderId,
    submissionId,
    questionnaireType,
  });

  // 1. Fetch order
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

  // 2. Get JotForm integration settings and fetch the submitted form.
  const jotformIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: JOTFORM_INTEGRATION_KEY,
  });

  const jotformApiUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_url",
  );
  const jotformApiKey = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_key",
  );
  const jotformTeamWorkspaceId = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_TEAM_WORKSPACE_ID_SETTING,
  );

  if (!jotformApiUrl || !jotformApiKey) {
    return jsonResponse(
      req,
      {
        error: "JotForm integration not configured",
        message: "JotForm API URL and API Key must be configured on the tenant",
      },
      409,
    );
  }

  const submission = await fetchJotformSubmission({
    apiUrl: jotformApiUrl,
    apiKey: jotformApiKey,
    submissionId,
    teamWorkspaceId: jotformTeamWorkspaceId,
    requestId,
  });

  const submissionFormId = getJotformSubmissionFormId(submission);
  const submissionOrderId = getJotformSubmissionOrderId(submission);

  if (!submissionOrderId) {
    return jsonResponse(
      req,
      {
        error: "Missing JotForm order field",
        message:
          `JotForm submission ${submissionId} does not include ${JOTFORM_ORDER_ID_FIELD_NAME}`,
        submissionId,
        formId: submissionFormId,
      },
      422,
    );
  }

  if (submissionOrderId !== order.id) {
    return jsonResponse(
      req,
      {
        error: "JotForm order mismatch",
        message:
          `JotForm submission ${submissionId} belongs to order ${submissionOrderId}, not ${order.id}`,
        submissionId,
        formId: submissionFormId,
      },
      409,
    );
  }

  const {
    effectiveQuestionnaireType,
    submittedQuestionnaireType,
  } = resolveMdiJotformEffectiveQuestionnaireType({
    incomingQuestionnaireType: questionnaireType,
    submission,
  });

  if (
    submittedQuestionnaireType &&
    submittedQuestionnaireType !== questionnaireType
  ) {
    console.warn("mdi: JotForm questionnaire type differs from RTDH event", {
      requestId,
      orderId,
      submissionId,
      formId: submissionFormId,
      incomingQuestionnaireType: questionnaireType,
      submittedQuestionnaireType,
      effectiveQuestionnaireType,
    });
  }

  // 3. Route patient questionnaire submissions by the order provider. RTDH
  // normalizes the submission into the authoritative questionnaire event type;
  // the JotForm hidden field is retained only for diagnostics.
  if (effectiveQuestionnaireType === "patient_questionnaire") {
    const statusGate = await getJotformOrderStatusGate({
      supabase,
      order,
      expectedStatusKey: PATIENT_QUESTIONNAIRE_PENDING_STATUS_KEY,
    });

    if (!statusGate.shouldProcess) {
      console.info("mdi: skipping JotForm patient questionnaire submission", {
        requestId,
        orderId,
        submissionId,
        formId: submissionFormId,
        currentStatusKey: statusGate.currentStatusKey,
        skippedReason: statusGate.skippedReason,
      });

      return jsonResponse(req, {
        success: true,
        skipped: true,
        skippedReason: statusGate.skippedReason,
        questionnaireType: effectiveQuestionnaireType,
        incomingQuestionnaireType: questionnaireType,
        submittedQuestionnaireType,
        orderId,
        submissionId,
        formId: submissionFormId,
        currentStatusKey: statusGate.currentStatusKey,
        requestId,
      });
    }

    const {
      providerName,
      providerIntegrationKey,
    } = await resolveOrderProviderPlatformLink({
      supabase,
      order: statusGate.order,
    });

    if (
      isTelegraProviderPlatform(
        providerIntegrationKey || providerName ||
          statusGate.order.provider_platform_integration_key,
      )
    ) {
      return await handleTelegraJotformPatientQuestionnaireSubmission({
        supabase,
        req,
        order: statusGate.order,
        submission,
        submissionId,
        formId: submissionFormId,
        incomingQuestionnaireType: questionnaireType,
        submittedQuestionnaireType,
        requestId,
      });
    }

    const mdiIntegration = await fetchTenantIntegrationForTenantByKey({
      supabase,
      tenantId: order.tenant_id,
      integrationKey: "md_integrations",
    });

    if (!mdiIntegration) {
      return jsonResponse(
        req,
        {
          error: "MDI integration not found",
          message:
            "MD Integrations integration is not configured for this tenant",
        },
        409,
      );
    }

    if (!order.patient_id) {
      return jsonResponse(
        req,
        {
          error: "Patient not found",
          message: `Order ${orderId} does not have a patient assigned`,
        },
        409,
      );
    }

    const patientProviderLink = await fetchPatientProviderPlatformLink({
      supabase,
      patientId: order.patient_id,
      tenantId: order.tenant_id,
      tenantIntegrationId: mdiIntegration.id,
    });

    if (!patientProviderLink?.provider_patient_id) {
      return jsonResponse(
        req,
        {
          error: "Patient provider link not found",
          message: `No MDI patient link found for patient on order ${orderId}`,
        },
        409,
      );
    }

    const providerPatientId = patientProviderLink.provider_patient_id;
    const backendUrl = getStringSetting(
      mdiIntegration.settings,
      "backend_url",
    );
    if (!backendUrl) {
      return jsonResponse(
        req,
        {
          error: "MDI integration misconfigured",
          message: "MD Integrations backend_url is not configured",
        },
        409,
      );
    }

    const accessToken = await resolveMdiAccessToken({
      supabase,
      tenantIntegrationId: mdiIntegration.id,
      tenantId: mdiIntegration.tenant_id,
      settings: mdiIntegration.settings,
      baseUrl: backendUrl,
      requestId,
      source: "provider-platform-bridge",
    });

    if ("errorMessage" in accessToken) {
      return jsonResponse(
        req,
        {
          error: "MD Integrations configuration invalid",
          message: accessToken.errorMessage,
        },
        500,
      );
    }

    const mdiPatientData = buildMdiPatientProfilePayloadFromJotformSubmission(
      submission,
    );
    let uploadedIdFiles: UploadedJotformMdiFile[] = [];
    try {
      uploadedIdFiles = await uploadJotformIdFilesToMdi({
        submission,
        jotformApiKey,
        jotformTeamWorkspaceId,
        backendUrl,
        accessToken: accessToken.accessToken,
        requestId,
        orderId,
        submissionId,
      });
    } catch (error) {
      await logOrderProcessingFailure({
        supabase,
        orderId,
        requestId,
        code: "jotform_patient_file_upload_failed",
        message: normalizeProcessingErrorMessage(error),
      });
      throw error;
    }

    const driverLicenseFileId = uploadedIdFiles[0]?.fileId ?? null;
    if (driverLicenseFileId) {
      mdiPatientData.driver_license_id = driverLicenseFileId;
    }

    let patientProfileUpdated = false;
    let updateResponse: unknown = null;
    if (Object.keys(mdiPatientData).length > 0) {
      try {
        updateResponse = await updateMdiPatientProfile({
          backendUrl,
          accessToken: accessToken.accessToken,
          providerPatientId,
          patientData: mdiPatientData,
          requestId,
        });
      } catch (error) {
        await logOrderProcessingFailure({
          supabase,
          orderId,
          requestId,
          code: "jotform_patient_profile_patch_failed",
          message: normalizeProcessingErrorMessage(error),
        });
        throw error;
      }
      patientProfileUpdated = true;
    }

    const orderStatusAdvance = await advanceOrderToNextStatus({
      supabase,
      order: statusGate.order,
      note:
        `JotForm patient questionnaire submission ${submissionId} has been processed.`,
      requestId,
      source: "provider-platform-bridge:jotform-patient-questionnaire",
    });

    const orderLifecycleTriggered = orderStatusAdvance.advanced
      ? await triggerOrderLifecycleForOrder({
        orderId: order.id,
        tenantId: order.tenant_id,
        requestId,
      })
      : false;

    return jsonResponse(req, {
      success: true,
      questionnaireType: effectiveQuestionnaireType,
      incomingQuestionnaireType: questionnaireType,
      submittedQuestionnaireType,
      orderId,
      submissionId,
      formId: submissionFormId,
      provider: "MDI",
      providerPatientId,
      patientProfileUpdated,
      patientDataFields: Object.keys(mdiPatientData),
      idFilesUploaded: uploadedIdFiles,
      idVerificationSkippedConfirmation: getJotformAnswerStringByName(
        submission,
        JOTFORM_ID_SKIP_CONFIRMATION_FIELD_NAME,
      ),
      orderStatusAdvanced: orderStatusAdvance.advanced,
      previousStatusKey: orderStatusAdvance.previousStatusKey,
      newStatusKey: orderStatusAdvance.newStatusKey,
      orderLifecycleTriggered,
      response: updateResponse,
      requestId,
    });
  }

  const medicalStatusGate = await getJotformOrderStatusGate({
    supabase,
    order,
    expectedStatusKey: MEDICAL_QUESTIONNAIRE_PENDING_STATUS_KEY,
  });

  if (!medicalStatusGate.shouldProcess) {
    console.info("mdi: skipping JotForm medical questionnaire submission", {
      requestId,
      orderId,
      submissionId,
      formId: submissionFormId,
      currentStatusKey: medicalStatusGate.currentStatusKey,
      skippedReason: medicalStatusGate.skippedReason,
    });

    return jsonResponse(req, {
      success: true,
      skipped: true,
      skippedReason: medicalStatusGate.skippedReason,
      questionnaireType: effectiveQuestionnaireType,
      incomingQuestionnaireType: questionnaireType,
      submittedQuestionnaireType,
      orderId,
      submissionId,
      formId: submissionFormId,
      currentStatusKey: medicalStatusGate.currentStatusKey,
      requestId,
    });
  }

  const mdiIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: order.tenant_id,
    integrationKey: "md_integrations",
  });

  if (!mdiIntegration) {
    return jsonResponse(
      req,
      {
        error: "MDI integration not found",
        message:
          "MD Integrations integration is not configured for this tenant",
      },
      409,
    );
  }

  // 4. Resolve MDI provider platform link (case_id = provider_order_id).
  const { providerPlatformLink } = await resolveOrderProviderPlatformLink({
    supabase,
    order,
  });

  if (
    providerPlatformLink &&
    providerPlatformLink.tenant_integration_id !== mdiIntegration.id
  ) {
    return jsonResponse(
      req,
      {
        error: "Provider platform mismatch",
        message:
          "The order is linked to a different provider platform and cannot process an MDI JotForm questionnaire",
      },
      409,
    );
  }

  if (!providerPlatformLink?.provider_order_id) {
    return jsonResponse(
      req,
      {
        error: "Provider platform link not found",
        message:
          `No MDI provider platform link found for order ${orderId}. Case may not have been created yet.`,
      },
      409,
    );
  }

  const providerOrderId = providerPlatformLink.provider_order_id;

  // 5. Resolve MDI patient link.
  if (!order.patient_id) {
    return jsonResponse(
      req,
      {
        error: "Patient not found",
        message: `Order ${orderId} does not have a patient assigned`,
      },
      409,
    );
  }

  const patientProviderLink = await fetchPatientProviderPlatformLink({
    supabase,
    patientId: order.patient_id,
    tenantId: order.tenant_id,
    tenantIntegrationId: mdiIntegration.id,
  });

  if (!patientProviderLink?.provider_patient_id) {
    return jsonResponse(
      req,
      {
        error: "Patient provider link not found",
        message: `No MDI patient link found for patient on order ${orderId}`,
      },
      409,
    );
  }

  const providerPatientId = patientProviderLink.provider_patient_id;

  // 6. Get MDI integration settings and access token.
  const backendUrl = getStringSetting(
    mdiIntegration.settings,
    "backend_url",
  );
  if (!backendUrl) {
    return jsonResponse(
      req,
      {
        error: "MDI integration misconfigured",
        message: "MD Integrations backend_url is not configured",
      },
      409,
    );
  }

  const accessToken = await resolveMdiAccessToken({
    supabase,
    tenantIntegrationId: mdiIntegration.id,
    tenantId: mdiIntegration.tenant_id,
    settings: mdiIntegration.settings,
    baseUrl: backendUrl,
    requestId,
    source: "provider-platform-bridge",
  });

  if ("errorMessage" in accessToken) {
    return jsonResponse(
      req,
      {
        error: "MD Integrations configuration invalid",
        message: accessToken.errorMessage,
      },
      500,
    );
  }

  const answers = submission.answers ?? {};
  const answerEntries = Object.values(answers);

  const uploadedFiles: Array<{
    fieldText: string;
    fileId: string;
    fileName: string;
    isIdFile: boolean;
  }> = [];
  const questionsToSubmit: Array<{
    question: string;
    answer: string;
    important?: boolean;
    critical?: boolean;
  }> = [];

  for (const entry of answerEntries) {
    if (isJotformUiOnlyField(entry.type)) continue;
    if (isJotformQuestionFlagConfigField(entry)) continue;
    if (
      entry.name === JOTFORM_ORDER_ID_FIELD_NAME ||
      entry.name === JOTFORM_PROVIDER_KEY_FIELD_NAME ||
      entry.name === JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME ||
      entry.name === JOTFORM_ID_SKIP_CONFIRMATION_FIELD_NAME
    ) {
      continue;
    }

    const fileUrls = extractJotformFileUrls(entry.answer);
    const isFileAnswer = isJotformFileUploadField(entry.type) ||
      (isJotformIdUploadAnswer(entry) && fileUrls.length > 0);

    if (isFileAnswer) {
      if (fileUrls.length === 0) continue;

      const isIdFile = isJotformIdUploadAnswer(entry) ||
        isJotformIdFileField(entry.text);

      for (const fileUrl of fileUrls) {
        try {
          const { blob, fileName, contentType } = await downloadFileFromUrl({
            fileUrl,
            apiKey: jotformApiKey,
            teamWorkspaceId: jotformTeamWorkspaceId,
            requestId,
          });

          const file = new File([blob], fileName, { type: contentType });
          const mdiFileType = isIdFile ? "driver-license" : "document";
          const uploadResult = await uploadMdiPartnerFile({
            backendUrl,
            accessToken: accessToken.accessToken,
            file,
            name: fileName,
            fileType: mdiFileType,
            requestId,
          });

          const fileId = parseNonEmptyString(uploadResult.file_id);
          if (!fileId) {
            console.error("mdi: MDI file upload did not return file_id", {
              requestId,
              orderId,
              submissionId,
              fieldText: entry.text,
              fileName,
            });
            continue;
          }

          await attachMdiFileToCase({
            backendUrl,
            accessToken: accessToken.accessToken,
            providerOrderId,
            fileId,
            requestId,
          });

          uploadedFiles.push({
            fieldText: entry.text,
            fileId,
            fileName,
            isIdFile,
          });
        } catch (error) {
          await logOrderProcessingFailure({
            supabase,
            orderId,
            requestId,
            code: "jotform_medical_file_upload_failed",
            message: normalizeProcessingErrorMessage(error),
          });
          throw error;
        }
      }
      continue;
    }

    const answerText = hasStructuredJotformListAnswer(entry.answer)
      ? stringifyJotformListAnswer(entry.answer) ??
        stringifyJotformAnswer(entry.answer)
      : stringifyJotformAnswer(entry.answer);
    if (answerText.length === 0) continue;

    const questionFlags = resolveMdiJotformQuestionFlags({
      submission,
      entry,
    });

    questionsToSubmit.push({
      question: entry.text || entry.name,
      answer: answerText,
      ...questionFlags,
    });
  }

  for (const questionEntry of questionsToSubmit) {
    try {
      await createMdiCaseQuestion({
        backendUrl,
        accessToken: accessToken.accessToken,
        providerOrderId,
        payload: {
          question: questionEntry.question,
          answer: questionEntry.answer,
          type: "string",
          important: questionEntry.important,
          critical: questionEntry.critical,
        },
        requestId,
      });
    } catch (error) {
      await logOrderProcessingFailure({
        supabase,
        orderId,
        requestId,
        code: "jotform_medical_question_submit_failed",
        message: normalizeProcessingErrorMessage(error),
      });
      throw error;
    }
  }

  try {
    await updateMdiCaseHoldStatus({
      backendUrl,
      accessToken: accessToken.accessToken,
      providerOrderId,
      holdStatus: false,
      requestId,
    });
  } catch (error) {
    await logOrderProcessingFailure({
      supabase,
      orderId,
      requestId,
      code: "jotform_medical_hold_release_failed",
      message: normalizeProcessingErrorMessage(error),
    });
    throw error;
  }

  const orderStatusAdvance = await advanceOrderToNextStatus({
    supabase,
    order: medicalStatusGate.order,
    note:
      `JotForm medical questionnaire submission ${submissionId} has been processed.`,
    requestId,
    source: "provider-platform-bridge:jotform-medical-questionnaire",
  });

  const orderLifecycleTriggered = orderStatusAdvance.advanced
    ? await triggerOrderLifecycleForOrder({
      orderId: order.id,
      tenantId: order.tenant_id,
      requestId,
    })
    : false;

  return jsonResponse(req, {
    success: true,
    questionnaireType: effectiveQuestionnaireType,
    incomingQuestionnaireType: questionnaireType,
    submittedQuestionnaireType,
    orderId,
    submissionId,
    formId: submissionFormId,
    provider: "MDI",
    providerOrderId,
    providerPatientId,
    filesUploaded: uploadedFiles.map((f) => ({
      fieldText: f.fieldText,
      fileId: f.fileId,
      fileName: f.fileName,
      isIdFile: f.isIdFile,
    })),
    questionsSubmitted: questionsToSubmit.length,
    holdStatusReleased: true,
    idVerificationSkippedConfirmation: getJotformAnswerStringByName(
      submission,
      JOTFORM_ID_SKIP_CONFIRMATION_FIELD_NAME,
    ),
    orderStatusAdvanced: orderStatusAdvance.advanced,
    previousStatusKey: orderStatusAdvance.previousStatusKey,
    newStatusKey: orderStatusAdvance.newStatusKey,
    orderLifecycleTriggered,
    requestId,
  });
}
