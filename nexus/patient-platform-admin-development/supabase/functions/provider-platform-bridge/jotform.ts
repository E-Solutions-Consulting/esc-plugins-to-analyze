import {
  fetchTenantIntegrationById,
  fetchTenantIntegrationForTenantByKey,
  jsonResponse,
  OrderRow,
  ProductProviderPlatformRow,
  SupabaseAdminClient,
  TenantIntegrationRow,
  userHasTenantAccess,
} from "./common.ts";

export const JOTFORM_INTEGRATION_KEY = "jotform";
export const JOTFORM_ORDER_ID_FIELD_NAME = "patient_platform_order_id";
export const JOTFORM_PROVIDER_KEY_FIELD_NAME = "provider_key";
export const JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME = "questionnaire_type";
export const JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING =
  "patient_questionnaire_form_id";
// Explicit per-provider questionnaire mode set in the admin UI. When present it is
// authoritative; when absent the bridge falls back to inference ("has form id?")
// so existing tenants behave exactly as before.
export const PATIENT_QUESTIONNAIRE_MODE_SETTING = "patient_questionnaire_mode";

export type QuestionnaireMode = "direct" | "jotform";

/** Normalize a stored mode value to a known mode, or null if unset/invalid. */
export function normalizeQuestionnaireMode(
  value: unknown,
): QuestionnaireMode | null {
  return value === "direct" || value === "jotform" ? value : null;
}
export const JOTFORM_TEAM_WORKSPACE_ID_SETTING = "team_workspace_id";
export const JOTFORM_DEFAULT_WEBHOOK_URL_SETTING = "default_webhook_url";
export const JOTFORM_DEFAULT_WEBHOOK_URL =
  "https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/jotform-webhook-receiver";
export const TELEGRA_PROVIDER_KEY = "telegramd";
export const JOTFORM_SAVE_AND_CONTINUE_FORM_PROPERTY = "saveAndContinue";

export type JotformQuestionnaireSelection = "new_order" | "renewal";
export type JotformSubmissionQuestionnaireType =
  | "patient_questionnaire"
  | "medical_questionnaire";

export interface JotformQuestionOption {
  id?: unknown;
  label?: unknown;
  icon?: unknown;
  [key: string]: unknown;
}

export interface PatientQuestionnaireDefinitionQuestion {
  name: string;
  type?: string;
  options?: JotformQuestionOption[];
  multiple?: boolean;
  question: string;
  required?: boolean;
  description?: string;
  mode?: string;
}

export interface PatientQuestionnaireDefinition {
  questions: PatientQuestionnaireDefinitionQuestion[];
}

export interface JotformGeneratedQuestion {
  type: string;
  text: string;
  order: string;
  name: string;
  required?: "Yes" | "No";
  options?: string;
  defaultValue?: string;
  selected?: string;
  subHeader?: string;
  hidden?: "Yes" | "No";
  backText?: string;
  nextText?: string;
}

interface JotformPatientQuestionnaireGenerationRequestBody {
  tenantIntegrationId?: unknown;
  title?: unknown;
  webhookUrl?: unknown;
  saveAsPatientQuestionnaire?: unknown;
}

export interface JotformFormCreateResult {
  formId: string;
  formUrl: string;
  responseBody: Record<string, unknown> | null;
}

export interface JotformWebhookCreateResult {
  webhookUrl: string;
  responseBody: Record<string, unknown> | null;
}

class JotformWriteError extends Error {
  readonly status: number;
  readonly requestUrl: string;
  readonly responseBody: unknown;

  constructor(params: {
    message: string;
    status: number;
    requestUrl: string;
    responseBody: unknown;
  }) {
    super(params.message);
    this.name = "JotformWriteError";
    this.status = params.status;
    this.requestUrl = params.requestUrl;
    this.responseBody = params.responseBody;
  }
}

export const TELEGRA_PATIENT_QUESTIONNAIRE_DEFINITION:
  PatientQuestionnaireDefinition = {
    questions: [
      {
        name: "symptoms",
        type: "symptoms",
        options: [],
        multiple: true,
        question: "Tell us about your symptoms",
        required: false,
        description: "Please check all the symptoms you have.",
      },
      {
        name: "other_symptoms",
        type: "input",
        question: "Other symptoms not listed in previous screen",
        required: true,
        description:
          "Even if they're not related to the condition or products you requested, this will help us analyze your health condition and recommend the best treatments.",
      },
      {
        name: "medication",
        type: "medication",
        options: [],
        multiple: true,
        question: "Please list the medications you regularly take",
        required: false,
        description:
          "Please add all the medications you currently take, even if they're not directly related to the reason for your visit.",
      },
      {
        name: "medication_confirmation",
        options: [
          {
            id: "Yes",
            label: "Yes, Confirm",
          },
        ],
        question: "I confirm that I've listed all medications I take",
        required: true,
      },
      {
        name: "allergies",
        type: "allergies",
        options: [],
        multiple: true,
        question: "Please list any medication allergies you have",
        required: false,
        description:
          "Please add all the allergies you have, even if they're not directly related to the reason for your visit.",
      },
      {
        name: "allergies_confirmation",
        options: [
          {
            id: "Yes",
            label: "Yes, Confirm",
          },
        ],
        question: "I confirm that I've listed all my allergies",
        required: true,
      },
      {
        name: "biological_gender",
        options: [
          {
            id: "male",
            icon: "MaleIcon",
            label: "Male",
          },
          {
            id: "female",
            icon: "FemaleIcon",
            label: "Female",
          },
        ],
        multiple: false,
        question: "What is your biological gender?",
        required: true,
      },
      {
        mode: "picker",
        name: "weight_lbs",
        type: "weight",
        question: "What is your current weight?",
        required: true,
      },
      {
        mode: "picker",
        name: "height_ft",
        type: "height",
        question: "What is your height?",
        required: true,
      },
      {
        name: "birth_date",
        type: "date",
        question: "What's your birth date?",
        required: true,
        description:
          "This helps our healthcare providers personalize your treatment plan and ensure your safety.",
      },
    ],
  };

interface JotformEmbedDetails {
  formId: string;
  baseUrl: string;
  embedHandlerBaseUrl: string;
  embedHandlerScriptUrl: string;
  formUrl: string;
  iframeId: string;
  orderIdFieldName: typeof JOTFORM_ORDER_ID_FIELD_NAME;
}

interface NativeQuestionnairePresentation {
  type: "native";
  orderType: string | null;
  selectedQuestionnaire: JotformQuestionnaireSelection;
  reason: string;
}

interface JotformQuestionnairePresentation {
  type: "jotform";
  orderType: string | null;
  selectedQuestionnaire: JotformQuestionnaireSelection;
  jotform: JotformEmbedDetails;
}

export interface JotformPatientQuestionnairePresentation {
  type: "jotform";
  purpose: "patient_questionnaire";
  jotform: JotformEmbedDetails;
}

export type QuestionnairePresentation =
  | NativeQuestionnairePresentation
  | JotformQuestionnairePresentation;

interface JotformValidationRequestBody {
  tenantIntegrationId?: unknown;
  formId?: unknown;
}

interface JotformWebhookRequestBody {
  tenantIntegrationId?: unknown;
  formId?: unknown;
  webhookUrl?: unknown;
}

interface JotformWebhookSyncRequestBody {
  tenantIntegrationId?: unknown;
}

export interface JotformWebhookEntry {
  id: string | null;
  url: string;
}

interface JotformWebhookFetchResult {
  webhooks: JotformWebhookEntry[];
  responseBody: Record<string, unknown> | null;
}

export type JotformDefaultWebhookStatus =
  | "default_not_configured"
  | "configured"
  | "missing"
  | "inaccessible";

export interface JotformDefaultWebhookCheckResult {
  formId: string;
  defaultWebhookUrl: string | null;
  status: JotformDefaultWebhookStatus;
  hasDefaultWebhook: boolean;
  webhookCount?: number;
  added?: boolean;
  skipped?: boolean;
  message?: string;
}

class JotformLookupError extends Error {
  readonly status: number;
  readonly lookupUrl: string;

  constructor(params: { message: string; status: number; lookupUrl: string }) {
    super(params.message);
    this.name = "JotformLookupError";
    this.status = params.status;
    this.lookupUrl = params.lookupUrl;
  }
}

function getStringSetting(
  settings: Record<string, unknown> | null,
  key: string,
): string | null {
  const value = settings?.[key];
  return typeof value === "string" && value.trim() ? value.trim() : null;
}

function getRecordValues(value: unknown): unknown[] {
  if (Array.isArray(value)) return value;
  if (value && typeof value === "object") {
    return Object.values(value as Record<string, unknown>);
  }

  return [];
}

function redactJotformApiKeyFromUrl(value: string): string {
  try {
    const url = new URL(value);
    if (url.searchParams.has("apiKey")) {
      url.searchParams.set("apiKey", "<redacted>");
    }
    return url.toString();
  } catch {
    return value.replace(/([?&]apiKey=)[^&]+/i, "$1<redacted>");
  }
}

function appendTenantToWebhookUrl(
  webhookUrl: string,
  tenantSlug: string,
): string {
  const url = new URL(webhookUrl);
  url.searchParams.set("tenant", tenantSlug);
  return url.toString();
}

function appendTenantToOptionalWebhookUrl(
  webhookUrl: string | null,
  tenantSlug: string,
): string | null {
  return webhookUrl ? appendTenantToWebhookUrl(webhookUrl, tenantSlug) : null;
}

function buildJotformApiResourceUrl(
  apiUrl: string,
  resourceSegments: string[],
): string {
  const url = new URL(apiUrl);
  const pathSegments = url.pathname
    .split("/")
    .map((segment) => segment.trim())
    .filter(Boolean);
  const lastSegment = pathSegments[pathSegments.length - 1]?.toLowerCase();
  const hostAlreadyTargetsApi = url.hostname === "api.jotform.com" ||
    url.hostname.endsWith("-api.jotform.com");
  const baseSegments = hostAlreadyTargetsApi
    ? pathSegments
    : lastSegment === "api"
    ? pathSegments.map((segment, index) =>
      index === pathSegments.length - 1 && segment.toLowerCase() === "api"
        ? "API"
        : segment
    )
    : [...pathSegments, "API"];

  url.pathname = `/${
    [
      ...baseSegments,
      ...resourceSegments.map((segment) => encodeURIComponent(segment)),
    ].join("/")
  }`;
  url.search = "";
  url.hash = "";
  return url.toString();
}

export function buildJotformCreateFormUrl(apiUrl: string): string {
  return buildJotformApiResourceUrl(apiUrl, ["form"]);
}

export function buildJotformFormWebhooksUrl(
  apiUrl: string,
  formId: string,
): string {
  return buildJotformApiResourceUrl(apiUrl, ["form", formId, "webhooks"]);
}

export function buildJotformFormWebhookUrl(
  apiUrl: string,
  formId: string,
  webhookId: string,
): string {
  return buildJotformApiResourceUrl(apiUrl, [
    "form",
    formId,
    "webhooks",
    webhookId,
  ]);
}

export function buildNativeQuestionnairePresentation(params: {
  order: Pick<OrderRow, "subscription_order_type">;
  selectedQuestionnaire: JotformQuestionnaireSelection;
  reason: string;
}): NativeQuestionnairePresentation {
  const { order, selectedQuestionnaire, reason } = params;

  return {
    type: "native",
    orderType: order.subscription_order_type || null,
    selectedQuestionnaire,
    reason,
  };
}

function selectJotformQuestionnaire(
  order: Pick<OrderRow, "subscription_order_type">,
): JotformQuestionnaireSelection {
  return order.subscription_order_type === "renewal" ? "renewal" : "new_order";
}

export function buildJotformEmbedBaseUrl(configuredUrl: string): string {
  const url = new URL(configuredUrl);
  const pathSegments = url.pathname
    .split("/")
    .map((segment) => segment.trim())
    .filter(Boolean);
  const lastSegment = pathSegments[pathSegments.length - 1]?.toLowerCase();
  const embedPathSegments = lastSegment === "api"
    ? pathSegments.slice(0, -1)
    : pathSegments;

  if (url.hostname === "api.jotform.com") {
    url.hostname = "www.jotform.com";
  } else if (url.hostname.endsWith("-api.jotform.com")) {
    url.hostname = url.hostname.replace("-api.jotform.com", ".jotform.com");
  }

  url.pathname = embedPathSegments.length > 0
    ? `/${embedPathSegments.join("/")}`
    : "/";
  url.search = "";
  url.hash = "";

  return url.toString().replace(/\/+$/, "");
}

export function buildJotformFormUrl(params: {
  baseUrl: string;
  formId: string;
  orderId: string;
}): string {
  const { baseUrl, formId, orderId } = params;
  const url = new URL(`${baseUrl.replace(/\/+$/, "")}/${formId}`);
  url.searchParams.set(JOTFORM_ORDER_ID_FIELD_NAME, orderId);
  return url.toString();
}

function stringifyJotformQuestionOption(
  option: JotformQuestionOption,
): string | null {
  const value = option.id ?? option.label;
  return typeof value === "string" && value.trim() ? value.trim() : null;
}

function stringifyJotformQuestionOptions(
  options: JotformQuestionOption[] | undefined,
): string | null {
  const values = (options ?? [])
    .map((option) => stringifyJotformQuestionOption(option))
    .filter((value): value is string => value !== null);

  return values.length > 0 ? values.join("|") : null;
}

function mapQuestionDefinitionToJotformType(
  question: PatientQuestionnaireDefinitionQuestion,
): string {
  const options = stringifyJotformQuestionOptions(question.options);
  if (options) {
    return question.multiple ? "control_checkbox" : "control_radio";
  }

  switch (question.type) {
    case "date":
      return "control_datetime";
    case "symptoms":
    case "medication":
    case "allergies":
      return "control_textarea";
    case "height":
    case "weight":
    case "input":
    default:
      return "control_textbox";
  }
}

function buildRequiredGeneratedJotformFields(
  orderOffset = 0,
): JotformGeneratedQuestion[] {
  return [
    {
      type: "control_radio",
      text: JOTFORM_PROVIDER_KEY_FIELD_NAME,
      order: String(orderOffset + 1),
      name: JOTFORM_PROVIDER_KEY_FIELD_NAME,
      required: "Yes",
      options: "md_integrations|telegramd|zito_care",
      selected: TELEGRA_PROVIDER_KEY,
      defaultValue: TELEGRA_PROVIDER_KEY,
      hidden: "Yes",
    },
    {
      type: "control_textbox",
      text: JOTFORM_ORDER_ID_FIELD_NAME,
      order: String(orderOffset + 2),
      name: JOTFORM_ORDER_ID_FIELD_NAME,
      required: "Yes",
      hidden: "Yes",
    },
    {
      type: "control_radio",
      text: JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
      order: String(orderOffset + 3),
      name: JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
      required: "Yes",
      options: "medical_questionnaire|patient_questionnaire",
      selected: "patient_questionnaire",
      defaultValue: "patient_questionnaire",
      hidden: "Yes",
    },
  ];
}

function buildGeneratedJotformPageBreak(
  index: number,
): JotformGeneratedQuestion {
  return {
    type: "control_pagebreak",
    text: "",
    order: "",
    name: `page_break_${index}`,
    backText: "Back",
    nextText: "Next",
  };
}

function withOneVisibleQuestionPerPage(
  questions: JotformGeneratedQuestion[],
): JotformGeneratedQuestion[] {
  const pagedQuestions = questions.flatMap((question, index) =>
    index < questions.length - 1
      ? [question, buildGeneratedJotformPageBreak(index + 1)]
      : [question]
  );

  return pagedQuestions.map((question, index) => ({
    ...question,
    order: String(index + 1),
  }));
}

export function buildJotformQuestionsFromPatientQuestionnaireDefinition(
  definition: PatientQuestionnaireDefinition,
): JotformGeneratedQuestion[] {
  const generatedQuestions = definition.questions.map((question, index) => {
    const options = stringifyJotformQuestionOptions(question.options);
    const generatedQuestion: JotformGeneratedQuestion = {
      type: mapQuestionDefinitionToJotformType(question),
      text: question.question,
      order: String(index + 1),
      name: question.name,
      required: question.required ? "Yes" : "No",
    };

    if (question.description?.trim()) {
      generatedQuestion.subHeader = question.description.trim();
    }

    if (options) {
      generatedQuestion.options = options;
    }

    return generatedQuestion;
  });
  const pagedQuestions = withOneVisibleQuestionPerPage(generatedQuestions);

  return [
    ...pagedQuestions,
    ...buildRequiredGeneratedJotformFields(pagedQuestions.length),
  ];
}

export function buildTelegraPatientQuestionnaireJotformQuestions(): JotformGeneratedQuestion[] {
  return buildJotformQuestionsFromPatientQuestionnaireDefinition(
    TELEGRA_PATIENT_QUESTIONNAIRE_DEFINITION,
  );
}

export function buildJotformCreateFormParams(params: {
  title: string;
  questions: JotformGeneratedQuestion[];
  properties?: Record<string, string>;
}): URLSearchParams {
  const formParams = new URLSearchParams();
  formParams.set("properties[title]", params.title);

  for (const [key, value] of Object.entries(params.properties ?? {})) {
    if (value.trim()) {
      formParams.set(`properties[${key}]`, value.trim());
    }
  }

  params.questions.forEach((question, index) => {
    const questionIndex = String(index + 1);
    const baseKey = `questions[${questionIndex}]`;
    const entries: Record<string, string | undefined> = {
      type: question.type,
      text: question.text,
      order: question.order,
      name: question.name,
      required: question.required,
      options: question.options,
      defaultValue: question.defaultValue,
      selected: question.selected,
      subHeader: question.subHeader,
      hidden: question.hidden,
      backText: question.backText,
      nextText: question.nextText,
    };

    for (const [key, value] of Object.entries(entries)) {
      if (typeof value === "string" && value.trim()) {
        formParams.set(`${baseKey}[${key}]`, value.trim());
      }
    }
  });

  return formParams;
}

function buildJotformEmbedDetails(params: {
  baseUrl: string;
  formId: string;
  orderId: string;
  searchParams?: Record<string, string | null | undefined>;
}): JotformEmbedDetails {
  const { baseUrl, formId, orderId, searchParams } = params;
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const formUrl = new URL(
    buildJotformFormUrl({
      baseUrl: normalizedBaseUrl,
      formId,
      orderId,
    }),
  );

  for (const [key, value] of Object.entries(searchParams ?? {})) {
    const normalizedValue = value?.trim();
    if (normalizedValue) {
      formUrl.searchParams.set(key, normalizedValue);
    }
  }

  return {
    formId,
    baseUrl: normalizedBaseUrl,
    embedHandlerBaseUrl: `${normalizedBaseUrl}/`,
    embedHandlerScriptUrl:
      `${normalizedBaseUrl}/s/umd/latest/for-form-embed-handler.js`,
    formUrl: formUrl.toString(),
    iframeId: `JotFormIFrame-${formId}`,
    orderIdFieldName: JOTFORM_ORDER_ID_FIELD_NAME,
  };
}

export function resolveQuestionnairePresentation(params: {
  order: Pick<OrderRow, "id" | "subscription_order_type">;
  providerKey?: string | null;
  productProviderPlatform:
    | (
      & Pick<
        ProductProviderPlatformRow,
        | "jotform_new_order_questionnaire_id"
        | "jotform_renewall_questionnaire_id"
      >
      & Partial<Pick<ProductProviderPlatformRow, "integration_mode">>
    )
    | null;
  jotformIntegration:
    | Pick<TenantIntegrationRow, "settings" | "is_enabled">
    | null;
}): QuestionnairePresentation {
  const { order, providerKey, productProviderPlatform, jotformIntegration } =
    params;
  const selectedQuestionnaire = selectJotformQuestionnaire(order);

  // Explicit mode wins. DIRECT → always native (never Jotform), even if a stale
  // form id lingers. Unset → fall back to the historical inference below.
  const mode = normalizeQuestionnaireMode(
    productProviderPlatform?.integration_mode,
  );
  if (mode === "direct") {
    return buildNativeQuestionnairePresentation({
      order,
      selectedQuestionnaire,
      reason: "integration_mode_direct",
    });
  }

  const formId = selectedQuestionnaire === "renewal"
    ? productProviderPlatform?.jotform_renewall_questionnaire_id?.trim()
    : productProviderPlatform?.jotform_new_order_questionnaire_id?.trim();

  if (!formId) {
    return buildNativeQuestionnairePresentation({
      order,
      selectedQuestionnaire,
      reason: "jotform_form_id_not_configured",
    });
  }

  if (!/^\d+$/.test(formId)) {
    return buildNativeQuestionnairePresentation({
      order,
      selectedQuestionnaire,
      reason: "jotform_form_id_invalid",
    });
  }

  const apiUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_url",
  );
  const apiKey = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_key",
  );

  if (!jotformIntegration?.is_enabled || !apiUrl || !apiKey) {
    return buildNativeQuestionnairePresentation({
      order,
      selectedQuestionnaire,
      reason: "jotform_integration_not_configured",
    });
  }

  let baseUrl: string;
  try {
    baseUrl = buildJotformEmbedBaseUrl(apiUrl);
  } catch {
    return buildNativeQuestionnairePresentation({
      order,
      selectedQuestionnaire,
      reason: "jotform_base_url_invalid",
    });
  }

  return {
    type: "jotform",
    orderType: order.subscription_order_type || null,
    selectedQuestionnaire,
    jotform: buildJotformEmbedDetails({
      formId,
      baseUrl,
      orderId: order.id,
      searchParams: {
        [JOTFORM_PROVIDER_KEY_FIELD_NAME]: providerKey ?? null,
      },
    }),
  };
}

export function resolvePatientQuestionnairePresentation(params: {
  order: Pick<OrderRow, "id">;
  providerKey: string | null;
  providerIntegration:
    | Pick<TenantIntegrationRow, "settings" | "is_enabled">
    | null;
  jotformIntegration:
    | Pick<TenantIntegrationRow, "settings" | "is_enabled">
    | null;
}): JotformPatientQuestionnairePresentation | null {
  const { order, providerKey, providerIntegration, jotformIntegration } =
    params;
  const normalizedProviderKey = providerKey?.trim();

  if (!normalizedProviderKey) {
    return null;
  }

  // Explicit mode wins. When the admin set the provider to DIRECT, always use the
  // native provider questionnaire — never Jotform — even if a stale form id lingers.
  // When unset, fall back to the historical inference below (has valid form id?).
  const mode = normalizeQuestionnaireMode(
    getStringSetting(
      providerIntegration?.settings ?? null,
      PATIENT_QUESTIONNAIRE_MODE_SETTING,
    ),
  );
  if (mode === "direct") {
    return null;
  }

  // The Jotform questionnaire form id is configured PER PROVIDER (Nexus →
  // Questionnaires → Patient, one entry per enabled provider). Read it ONLY from
  // the order's own provider integration. We intentionally do NOT fall back to a
  // global form id on the jotform integration: that legacy flat key is no longer
  // managed by the per-provider Nexus UI, and falling back to it forced Jotform
  // onto providers that are "Not Configured" (e.g. TelegraMD), bypassing their
  // native questionnaire. A provider with no form id configured (and not in
  // explicit jotform mode) → native provider questionnaire.
  const formId = getStringSetting(
    providerIntegration?.settings ?? null,
    JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING,
  );

  if (!formId || !/^\d+$/.test(formId)) {
    return null;
  }

  const apiUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_url",
  );
  const apiKey = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_key",
  );

  if (!jotformIntegration?.is_enabled || !apiUrl || !apiKey) {
    return null;
  }

  let baseUrl: string;
  try {
    baseUrl = buildJotformEmbedBaseUrl(apiUrl);
  } catch {
    return null;
  }

  return {
    type: "jotform",
    purpose: "patient_questionnaire",
    jotform: buildJotformEmbedDetails({
      formId,
      baseUrl,
      orderId: order.id,
      searchParams: {
        [JOTFORM_PROVIDER_KEY_FIELD_NAME]: normalizedProviderKey,
        [JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME]: "patient_questionnaire",
      },
    }),
  };
}

export function buildJotformQuestionsUrl(
  apiUrl: string,
  formId: string,
): string {
  const url = new URL(apiUrl);
  const pathSegments = url.pathname
    .split("/")
    .map((segment) => segment.trim())
    .filter(Boolean);
  const lastSegment = pathSegments[pathSegments.length - 1]?.toLowerCase();
  const hostAlreadyTargetsApi = url.hostname === "api.jotform.com" ||
    url.hostname.endsWith("-api.jotform.com");
  const baseSegments = hostAlreadyTargetsApi
    ? pathSegments
    : lastSegment === "api"
    ? pathSegments.map((segment, index) =>
      index === pathSegments.length - 1 && segment.toLowerCase() === "api"
        ? "API"
        : segment
    )
    : [...pathSegments, "API"];

  url.pathname = `/${
    [
      ...baseSegments,
      "form",
      encodeURIComponent(formId),
      "questions",
    ].join("/")
  }`;
  url.search = "";
  url.hash = "";
  return url.toString();
}

export function jotformQuestionsHaveOrderIdField(content: unknown): boolean {
  return getRecordValues(content).some((question) => {
    if (!question || typeof question !== "object" || Array.isArray(question)) {
      return false;
    }

    const name = (question as Record<string, unknown>).name;
    return (
      typeof name === "string" &&
      name.trim() === JOTFORM_ORDER_ID_FIELD_NAME
    );
  });
}

async function fetchJotformQuestions(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<unknown> {
  const { apiUrl, apiKey, formId, teamWorkspaceId, requestId } = params;
  const questionsUrl = new URL(buildJotformQuestionsUrl(apiUrl, formId));
  questionsUrl.searchParams.set("apiKey", apiKey);
  const requestUrl = questionsUrl.toString();
  const lookupUrl = redactJotformApiKeyFromUrl(requestUrl);
  const headers: Record<string, string> = {
    APIKEY: apiKey,
    Accept: "application/json",
    "x-request-id": requestId,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  const response = await fetch(requestUrl, {
    headers,
  });

  let body: Record<string, unknown> | null = null;
  try {
    body = (await response.json()) as Record<string, unknown>;
  } catch {
    body = null;
  }

  if (!response.ok) {
    const message = typeof body?.message === "string" && body.message.trim()
      ? body.message.trim()
      : `JotForm returned ${response.status}`;

    throw new JotformLookupError({
      message: `Unable to retrieve the JotForm form: ${message}`,
      status: response.status,
      lookupUrl,
    });
  }

  return body?.content;
}

async function readJotformResponseBody(
  response: Response,
): Promise<Record<string, unknown> | null> {
  const rawBody = await response.text();
  if (!rawBody) return null;

  try {
    const parsed = JSON.parse(rawBody) as unknown;
    return parsed && typeof parsed === "object" && !Array.isArray(parsed)
      ? parsed as Record<string, unknown>
      : { content: parsed };
  } catch {
    return { content: rawBody };
  }
}

function extractJotformCreatedFormId(
  responseBody: Record<string, unknown> | null,
): string | null {
  const candidates: unknown[] = [
    responseBody?.formID,
    responseBody?.id,
    responseBody?.form_id,
  ];
  const content = responseBody?.content;
  if (content && typeof content === "object" && !Array.isArray(content)) {
    const contentRecord = content as Record<string, unknown>;
    candidates.push(
      contentRecord.formID,
      contentRecord.id,
      contentRecord.form_id,
    );
  } else {
    candidates.push(content);
  }

  for (const candidate of candidates) {
    if (typeof candidate === "number" && Number.isFinite(candidate)) {
      return String(candidate);
    }
    if (typeof candidate === "string" && candidate.trim()) {
      const trimmed = candidate.trim();
      if (/^\d+$/.test(trimmed)) return trimmed;
    }
  }

  return null;
}

function normalizeJotformWebhookEntries(
  content: unknown,
): JotformWebhookEntry[] {
  const webhooks: JotformWebhookEntry[] = [];
  const seen = new Set<string>();

  const addWebhook = (id: unknown, url: unknown) => {
    if (typeof url !== "string" || !url.trim()) return;
    const normalizedUrl = url.trim();
    const normalizedId = typeof id === "string" && id.trim()
      ? id.trim()
      : typeof id === "number" && Number.isFinite(id)
      ? String(id)
      : null;
    const dedupeKey = `${normalizedId || ""}:${normalizedUrl}`;
    if (seen.has(dedupeKey)) return;

    seen.add(dedupeKey);
    webhooks.push({
      id: normalizedId,
      url: normalizedUrl,
    });
  };

  const visit = (value: unknown, fallbackId: unknown = null) => {
    if (!value) return;

    if (typeof value === "string") {
      addWebhook(fallbackId, value);
      return;
    }

    if (Array.isArray(value)) {
      value.forEach((item, index) => visit(item, index));
      return;
    }

    if (typeof value !== "object") return;

    const record = value as Record<string, unknown>;
    const nested = record.webhooks ?? record.content;
    if (nested && nested !== value) {
      visit(nested, fallbackId);
      return;
    }

    const url = record.webhookURL ?? record.webhookUrl ?? record.webhook_url ??
      record.url;
    if (url) {
      addWebhook(
        record.id ?? record.webhookID ?? record.webhookId ?? fallbackId,
        url,
      );
      return;
    }

    for (const [key, entry] of Object.entries(record)) {
      visit(entry, key);
    }
  };

  visit(content);
  return webhooks;
}

async function createJotformForm(params: {
  apiUrl: string;
  apiKey: string;
  title: string;
  questions: JotformGeneratedQuestion[];
  properties?: Record<string, string>;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformFormCreateResult> {
  const {
    apiUrl,
    apiKey,
    title,
    questions,
    properties,
    teamWorkspaceId,
    requestId,
  } = params;
  const createUrl = new URL(buildJotformCreateFormUrl(apiUrl));
  createUrl.searchParams.set("apiKey", apiKey);
  const fullRequestUrl = createUrl.toString();
  const requestUrl = redactJotformApiKeyFromUrl(fullRequestUrl);
  const headers: Record<string, string> = {
    APIKEY: apiKey,
    Accept: "application/json",
    "Content-Type": "application/x-www-form-urlencoded",
    "x-request-id": requestId,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  const response = await fetch(fullRequestUrl, {
    method: "POST",
    headers,
    body: buildJotformCreateFormParams({ title, questions, properties }),
  });
  const responseBody = await readJotformResponseBody(response);

  if (!response.ok) {
    const message = typeof responseBody?.message === "string" &&
        responseBody.message.trim()
      ? responseBody.message.trim()
      : `JotForm returned ${response.status}`;

    throw new JotformWriteError({
      message: `Unable to create Jotform form: ${message}`,
      status: response.status,
      requestUrl,
      responseBody,
    });
  }

  const formId = extractJotformCreatedFormId(responseBody);
  if (!formId) {
    throw new JotformWriteError({
      message: "Jotform form creation response did not include a form id",
      status: response.status,
      requestUrl,
      responseBody,
    });
  }

  return {
    formId,
    formUrl: `${buildJotformEmbedBaseUrl(apiUrl).replace(/\/+$/, "")}/${
      encodeURIComponent(formId)
    }`,
    responseBody,
  };
}

async function createJotformWebhook(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  webhookUrl: string;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformWebhookCreateResult> {
  const {
    apiUrl,
    apiKey,
    formId,
    webhookUrl,
    teamWorkspaceId,
    requestId,
  } = params;
  const webhooksUrl = new URL(buildJotformFormWebhooksUrl(apiUrl, formId));
  webhooksUrl.searchParams.set("apiKey", apiKey);
  const fullRequestUrl = webhooksUrl.toString();
  const requestUrl = redactJotformApiKeyFromUrl(fullRequestUrl);
  const headers: Record<string, string> = {
    APIKEY: apiKey,
    Accept: "application/json",
    "Content-Type": "application/x-www-form-urlencoded",
    "x-request-id": requestId,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  const body = new URLSearchParams();
  body.set("webhookURL", webhookUrl);

  const response = await fetch(fullRequestUrl, {
    method: "POST",
    headers,
    body,
  });
  const responseBody = await readJotformResponseBody(response);

  if (!response.ok) {
    const message = typeof responseBody?.message === "string" &&
        responseBody.message.trim()
      ? responseBody.message.trim()
      : `JotForm returned ${response.status}`;

    throw new JotformWriteError({
      message: `Unable to attach Jotform webhook: ${message}`,
      status: response.status,
      requestUrl,
      responseBody,
    });
  }

  return {
    webhookUrl,
    responseBody,
  };
}

async function fetchJotformWebhooks(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformWebhookFetchResult> {
  const { apiUrl, apiKey, formId, teamWorkspaceId, requestId } = params;
  const webhooksUrl = new URL(buildJotformFormWebhooksUrl(apiUrl, formId));
  webhooksUrl.searchParams.set("apiKey", apiKey);
  const fullRequestUrl = webhooksUrl.toString();
  const requestUrl = redactJotformApiKeyFromUrl(fullRequestUrl);
  const headers: Record<string, string> = {
    APIKEY: apiKey,
    Accept: "application/json",
    "x-request-id": requestId,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  const response = await fetch(fullRequestUrl, {
    method: "GET",
    headers,
  });
  const responseBody = await readJotformResponseBody(response);

  if (!response.ok) {
    const message = typeof responseBody?.message === "string" &&
        responseBody.message.trim()
      ? responseBody.message.trim()
      : `JotForm returned ${response.status}`;

    throw new JotformWriteError({
      message: `Unable to retrieve Jotform webhooks: ${message}`,
      status: response.status,
      requestUrl,
      responseBody,
    });
  }

  return {
    webhooks: normalizeJotformWebhookEntries(
      responseBody?.content ?? responseBody,
    ),
    responseBody,
  };
}

function buildDefaultWebhookNotConfiguredResult(
  formId: string,
): JotformDefaultWebhookCheckResult {
  return {
    formId,
    defaultWebhookUrl: null,
    status: "default_not_configured",
    hasDefaultWebhook: false,
    skipped: true,
    message: "Default Webhook URL is not configured.",
  };
}

function buildJotformWebhookStatusResult(params: {
  formId: string;
  defaultWebhookUrl: string;
  webhooks: JotformWebhookEntry[];
  added?: boolean;
}): JotformDefaultWebhookCheckResult {
  const hasDefaultWebhook = params.webhooks.some((webhook) =>
    webhook.url === params.defaultWebhookUrl
  );

  return {
    formId: params.formId,
    defaultWebhookUrl: params.defaultWebhookUrl,
    status: hasDefaultWebhook ? "configured" : "missing",
    hasDefaultWebhook,
    webhookCount: params.webhooks.length,
    added: params.added ?? false,
  };
}

async function checkJotformDefaultWebhook(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  defaultWebhookUrl: string | null;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformDefaultWebhookCheckResult> {
  const {
    apiUrl,
    apiKey,
    formId,
    defaultWebhookUrl,
    teamWorkspaceId,
    requestId,
  } = params;

  if (!defaultWebhookUrl) {
    return buildDefaultWebhookNotConfiguredResult(formId);
  }

  try {
    const result = await fetchJotformWebhooks({
      apiUrl,
      apiKey,
      formId,
      teamWorkspaceId,
      requestId,
    });

    return buildJotformWebhookStatusResult({
      formId,
      defaultWebhookUrl,
      webhooks: result.webhooks,
    });
  } catch (error) {
    return {
      formId,
      defaultWebhookUrl,
      status: "inaccessible",
      hasDefaultWebhook: false,
      skipped: true,
      message: error instanceof Error
        ? error.message
        : "Unable to retrieve Jotform webhooks.",
    };
  }
}

async function ensureJotformDefaultWebhook(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  defaultWebhookUrl: string | null;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformDefaultWebhookCheckResult> {
  const {
    apiUrl,
    apiKey,
    formId,
    defaultWebhookUrl,
    teamWorkspaceId,
    requestId,
  } = params;

  if (!defaultWebhookUrl) {
    return buildDefaultWebhookNotConfiguredResult(formId);
  }

  const existingWebhooks = await fetchJotformWebhooks({
    apiUrl,
    apiKey,
    formId,
    teamWorkspaceId,
    requestId,
  });

  const currentStatus = buildJotformWebhookStatusResult({
    formId,
    defaultWebhookUrl,
    webhooks: existingWebhooks.webhooks,
  });

  if (currentStatus.hasDefaultWebhook) {
    return currentStatus;
  }

  await createJotformWebhook({
    apiUrl,
    apiKey,
    formId,
    webhookUrl: defaultWebhookUrl,
    teamWorkspaceId,
    requestId,
  });

  const updatedWebhooks = await fetchJotformWebhooks({
    apiUrl,
    apiKey,
    formId,
    teamWorkspaceId,
    requestId,
  });

  return {
    ...buildJotformWebhookStatusResult({
      formId,
      defaultWebhookUrl,
      webhooks: updatedWebhooks.webhooks,
      added: true,
    }),
    status: "configured",
    hasDefaultWebhook: true,
  };
}

async function deleteJotformWebhook(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  webhookId: string;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<void> {
  const {
    apiUrl,
    apiKey,
    formId,
    webhookId,
    teamWorkspaceId,
    requestId,
  } = params;
  const webhookUrl = new URL(
    buildJotformFormWebhookUrl(apiUrl, formId, webhookId),
  );
  webhookUrl.searchParams.set("apiKey", apiKey);
  const fullRequestUrl = webhookUrl.toString();
  const requestUrl = redactJotformApiKeyFromUrl(fullRequestUrl);
  const headers: Record<string, string> = {
    APIKEY: apiKey,
    Accept: "application/json",
    "x-request-id": requestId,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  const response = await fetch(fullRequestUrl, {
    method: "DELETE",
    headers,
  });
  const responseBody = await readJotformResponseBody(response);

  if (!response.ok) {
    const message = typeof responseBody?.message === "string" &&
        responseBody.message.trim()
      ? responseBody.message.trim()
      : `JotForm returned ${response.status}`;

    throw new JotformWriteError({
      message: `Unable to delete Jotform webhook: ${message}`,
      status: response.status,
      requestUrl,
      responseBody,
    });
  }
}

async function replaceJotformWebhooks(params: {
  apiUrl: string;
  apiKey: string;
  formId: string;
  webhookUrl: string | null;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformWebhookEntry[]> {
  const {
    apiUrl,
    apiKey,
    formId,
    webhookUrl,
    teamWorkspaceId,
    requestId,
  } = params;
  const existingWebhooks = await fetchJotformWebhooks({
    apiUrl,
    apiKey,
    formId,
    teamWorkspaceId,
    requestId,
  });

  for (const existingWebhook of existingWebhooks.webhooks) {
    if (!existingWebhook.id) continue;

    await deleteJotformWebhook({
      apiUrl,
      apiKey,
      formId,
      webhookId: existingWebhook.id,
      teamWorkspaceId,
      requestId,
    });
  }

  if (webhookUrl) {
    await createJotformWebhook({
      apiUrl,
      apiKey,
      formId,
      webhookUrl,
      teamWorkspaceId,
      requestId,
    });
  }

  const updatedWebhooks = await fetchJotformWebhooks({
    apiUrl,
    apiKey,
    formId,
    teamWorkspaceId,
    requestId,
  });

  return updatedWebhooks.webhooks;
}

async function persistProviderPatientQuestionnaireFormId(params: {
  supabase: SupabaseAdminClient;
  providerIntegration: TenantIntegrationRow;
  formId: string;
}): Promise<void> {
  const currentSettings = params.providerIntegration.settings &&
      typeof params.providerIntegration.settings === "object" &&
      !Array.isArray(params.providerIntegration.settings)
    ? params.providerIntegration.settings
    : {};
  const nextSettings = {
    ...currentSettings,
    [JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING]: params.formId,
  };

  const { error } = await params.supabase
    .from("tenant_integrations")
    .update({ settings: nextSettings })
    .eq("id", params.providerIntegration.id)
    .eq("tenant_id", params.providerIntegration.tenant_id);

  if (error) {
    throw new Error(
      `Failed to save provider patient questionnaire form id: ${error.message}`,
    );
  }
}

async function resolveJotformApiContext(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  tenantIntegrationId: string;
  authUserId: string;
  requestId: string;
  resource: string;
}): Promise<
  | {
    ok: true;
    tenantIntegration: TenantIntegrationRow;
    jotformIntegration: TenantIntegrationRow;
    apiUrl: string;
    apiKey: string;
    teamWorkspaceId: string | null;
    defaultWebhookUrl: string | null;
    tenantSlug: string;
  }
  | { ok: false; response: Response }
> {
  const {
    supabase,
    req,
    tenantIntegrationId,
    authUserId,
    requestId,
    resource,
  } = params;
  const tenantIntegration = await fetchTenantIntegrationById({
    supabase,
    tenantIntegrationId,
  });

  if (!tenantIntegration) {
    return {
      ok: false,
      response: jsonResponse(
        req,
        {
          error: "Tenant integration not found",
          message: "Enable the provider integration before managing webhooks",
        },
        404,
      ),
    };
  }

  const hasTenantAccess = await userHasTenantAccess({
    supabase,
    authUserId,
    tenantId: tenantIntegration.tenant_id,
    requestId,
    resource,
  });

  if (!hasTenantAccess) {
    return {
      ok: false,
      response: jsonResponse(
        req,
        {
          error: "Forbidden",
          message:
            "You do not have access to manage Jotform webhooks for this tenant",
        },
        403,
      ),
    };
  }

  const jotformIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: tenantIntegration.tenant_id,
    integrationKey: JOTFORM_INTEGRATION_KEY,
  });

  const apiUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_url",
  );
  const apiKey = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_key",
  );
  const teamWorkspaceId = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_TEAM_WORKSPACE_ID_SETTING,
  );
  const defaultWebhookUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_DEFAULT_WEBHOOK_URL_SETTING,
  );
  const { data: tenantRow, error: tenantError } = await params.supabase
    .from("tenants")
    .select("slug")
    .eq("id", tenantIntegration.tenant_id)
    .maybeSingle();

  if (
    tenantError || typeof tenantRow?.slug !== "string" || !tenantRow.slug.trim()
  ) {
    return {
      ok: false,
      response: jsonResponse(
        req,
        {
          error: "Tenant slug not found",
          message: "Unable to resolve tenant slug for Jotform webhook URL.",
        },
        400,
      ),
    };
  }

  if (!jotformIntegration || !apiUrl || !apiKey) {
    return {
      ok: false,
      response: jsonResponse(
        req,
        {
          error: "Jotform integration not configured",
          message:
            "Configure and enable the tenant Jotform integration before managing webhooks.",
        },
        409,
      ),
    };
  }

  return {
    ok: true,
    tenantIntegration,
    jotformIntegration,
    apiUrl,
    apiKey,
    teamWorkspaceId,
    defaultWebhookUrl,
    tenantSlug: tenantRow.slug.trim(),
  };
}

async function listConfiguredJotformFormIdsForTenant(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
}): Promise<string[]> {
  const { supabase, tenantId } = params;
  const formIds = new Set<string>();

  const { data: tenantIntegrations, error: tenantIntegrationsError } =
    await supabase
      .from("tenant_integrations")
      .select("id, settings")
      .eq("tenant_id", tenantId);

  if (tenantIntegrationsError) {
    throw new Error(
      `Failed to load tenant integrations: ${tenantIntegrationsError.message}`,
    );
  }

  const providerIntegrationIds: string[] = [];
  for (const integration of tenantIntegrations ?? []) {
    if (typeof integration.id === "string") {
      providerIntegrationIds.push(integration.id);
    }

    const patientQuestionnaireFormId = getConfiguredJotformFormId(
      getStringSetting(
        integration.settings as Record<string, unknown> | null,
        JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING,
      ),
    );
    if (patientQuestionnaireFormId) formIds.add(patientQuestionnaireFormId);
  }

  if (providerIntegrationIds.length > 0) {
    const { data: productProviderPlatforms, error } = await supabase
      .from("product_provider_platforms")
      .select(
        "jotform_new_order_questionnaire_id, jotform_renewall_questionnaire_id",
      )
      .in("tenant_integration_id", providerIntegrationIds)
      .eq("is_enabled", true);

    if (error) {
      throw new Error(
        `Failed to load product provider platform Jotform ids: ${error.message}`,
      );
    }

    for (const assignment of productProviderPlatforms ?? []) {
      const newOrderFormId = getConfiguredJotformFormId(
        typeof assignment.jotform_new_order_questionnaire_id === "string"
          ? assignment.jotform_new_order_questionnaire_id
          : null,
      );
      const renewalFormId = getConfiguredJotformFormId(
        typeof assignment.jotform_renewall_questionnaire_id === "string"
          ? assignment.jotform_renewall_questionnaire_id
          : null,
      );
      if (newOrderFormId) formIds.add(newOrderFormId);
      if (renewalFormId) formIds.add(renewalFormId);
    }
  }

  return [...formIds].sort();
}

async function syncConfiguredJotformDefaultWebhooks(params: {
  supabase: SupabaseAdminClient;
  tenantId: string;
  apiUrl: string;
  apiKey: string;
  defaultWebhookUrl: string | null;
  teamWorkspaceId: string | null;
  requestId: string;
}): Promise<JotformDefaultWebhookCheckResult[]> {
  const formIds = await listConfiguredJotformFormIdsForTenant({
    supabase: params.supabase,
    tenantId: params.tenantId,
  });

  const results: JotformDefaultWebhookCheckResult[] = [];
  for (const formId of formIds) {
    try {
      results.push(
        await ensureJotformDefaultWebhook({
          apiUrl: params.apiUrl,
          apiKey: params.apiKey,
          formId,
          defaultWebhookUrl: params.defaultWebhookUrl,
          teamWorkspaceId: params.teamWorkspaceId,
          requestId: params.requestId,
        }),
      );
    } catch (error) {
      results.push({
        formId,
        defaultWebhookUrl: params.defaultWebhookUrl,
        status: "inaccessible",
        hasDefaultWebhook: false,
        skipped: true,
        message: error instanceof Error
          ? error.message
          : "Unable to retrieve or update Jotform webhooks.",
      });
    }
  }

  return results;
}

export async function handleJotformPatientQuestionnaireGenerationRequest(
  params: {
    supabase: SupabaseAdminClient;
    req: Request;
    authUserId: string;
    requestId: string;
  },
): Promise<Response> {
  const { supabase, req, authUserId, requestId } = params;
  const body =
    (await req.json()) as JotformPatientQuestionnaireGenerationRequestBody;
  const tenantIntegrationId = typeof body.tenantIntegrationId === "string"
    ? body.tenantIntegrationId.trim()
    : "";
  const title = typeof body.title === "string" && body.title.trim()
    ? body.title.trim()
    : "Telegra Patient Questionnaire";
  const requestedWebhookUrl = typeof body.webhookUrl === "string" &&
      body.webhookUrl.trim()
    ? body.webhookUrl.trim()
    : null;
  const saveAsPatientQuestionnaire = body.saveAsPatientQuestionnaire !== false;

  if (!tenantIntegrationId) {
    return jsonResponse(
      req,
      {
        error: "Missing tenantIntegrationId",
        message:
          "Provide the Telegra tenant integration id that will own this generated patient questionnaire.",
      },
      400,
    );
  }

  const telegraIntegration = await fetchTenantIntegrationById({
    supabase,
    tenantIntegrationId,
  });

  if (!telegraIntegration) {
    return jsonResponse(
      req,
      {
        error: "Tenant integration not found",
        message: "Enable the Telegra integration before generating the form",
      },
      404,
    );
  }

  if (
    !telegraIntegration.is_enabled ||
    telegraIntegration.integration_key !== TELEGRA_PROVIDER_KEY
  ) {
    return jsonResponse(
      req,
      {
        error: "Invalid tenant integration",
        message:
          "The tenantIntegrationId must point to an enabled Telegra integration.",
      },
      400,
    );
  }

  const hasTenantAccess = await userHasTenantAccess({
    supabase,
    authUserId,
    tenantId: telegraIntegration.tenant_id,
    requestId,
    resource: "generate-jotform-patient-questionnaire",
  });

  if (!hasTenantAccess) {
    return jsonResponse(
      req,
      {
        error: "Forbidden",
        message: "You do not have access to generate forms for this tenant",
      },
      403,
    );
  }

  const jotformIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: telegraIntegration.tenant_id,
    integrationKey: JOTFORM_INTEGRATION_KEY,
  });

  const apiUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_url",
  );
  const apiKey = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_key",
  );
  const teamWorkspaceId = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_TEAM_WORKSPACE_ID_SETTING,
  );
  const defaultWebhookUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_DEFAULT_WEBHOOK_URL_SETTING,
  );
  const { data: tenantRow, error: tenantError } = await supabase
    .from("tenants")
    .select("slug")
    .eq("id", telegraIntegration.tenant_id)
    .maybeSingle();

  if (
    tenantError || typeof tenantRow?.slug !== "string" || !tenantRow.slug.trim()
  ) {
    return jsonResponse(
      req,
      {
        error: "Tenant slug not found",
        message: "Unable to resolve tenant slug for Jotform webhook URL.",
      },
      400,
    );
  }

  const webhookUrl = appendTenantToWebhookUrl(
    requestedWebhookUrl || defaultWebhookUrl || JOTFORM_DEFAULT_WEBHOOK_URL,
    tenantRow.slug.trim(),
  );

  if (!jotformIntegration || !apiUrl || !apiKey) {
    return jsonResponse(
      req,
      {
        error: "Jotform integration not configured",
        message:
          "Configure and enable the tenant Jotform integration before generating a patient questionnaire.",
      },
      409,
    );
  }

  let parsedWebhookUrl: URL;
  try {
    parsedWebhookUrl = new URL(webhookUrl);
  } catch {
    return jsonResponse(
      req,
      {
        error: "Invalid webhookUrl",
        message: "Provide a valid HTTP or HTTPS Jotform webhook URL.",
      },
      400,
    );
  }

  if (!/^https?:$/i.test(parsedWebhookUrl.protocol)) {
    return jsonResponse(
      req,
      {
        error: "Invalid webhookUrl",
        message: "Jotform webhook URL must use HTTP or HTTPS.",
      },
      400,
    );
  }

  const questions = buildTelegraPatientQuestionnaireJotformQuestions();
  let createdForm: JotformFormCreateResult;
  try {
    createdForm = await createJotformForm({
      apiUrl,
      apiKey,
      title,
      questions,
      properties: {
        [JOTFORM_SAVE_AND_CONTINUE_FORM_PROPERTY]: "Yes",
      },
      teamWorkspaceId,
      requestId,
    });
  } catch (error) {
    return jsonResponse(
      req,
      {
        error: "Jotform form creation failed",
        message: error instanceof Error
          ? error.message
          : "Unable to create Jotform form",
        ...(error instanceof JotformWriteError
          ? {
            jotformStatus: error.status,
            requestUrl: error.requestUrl,
            jotformResponse: error.responseBody,
          }
          : {}),
      },
      502,
    );
  }

  let webhookResult: JotformWebhookCreateResult | null = null;
  try {
    webhookResult = await createJotformWebhook({
      apiUrl,
      apiKey,
      formId: createdForm.formId,
      webhookUrl,
      teamWorkspaceId,
      requestId,
    });
  } catch (error) {
    return jsonResponse(
      req,
      {
        error: "Jotform webhook creation failed",
        message: error instanceof Error
          ? error.message
          : "Unable to attach Jotform webhook",
        formId: createdForm.formId,
        formUrl: createdForm.formUrl,
        ...(error instanceof JotformWriteError
          ? {
            jotformStatus: error.status,
            requestUrl: error.requestUrl,
            jotformResponse: error.responseBody,
          }
          : {}),
      },
      502,
    );
  }

  if (saveAsPatientQuestionnaire) {
    try {
      await persistProviderPatientQuestionnaireFormId({
        supabase,
        providerIntegration: telegraIntegration,
        formId: createdForm.formId,
      });
    } catch (error) {
      return jsonResponse(
        req,
        {
          error: "Provider settings update failed",
          message: error instanceof Error
            ? error.message
            : "Unable to save generated Jotform form id",
          formId: createdForm.formId,
          formUrl: createdForm.formUrl,
        },
        500,
      );
    }
  }

  return jsonResponse(req, {
    success: true,
    provider: TELEGRA_PROVIDER_KEY,
    providerIntegrationId: telegraIntegration.id,
    questionnaireType: "patient_questionnaire",
    formId: createdForm.formId,
    formUrl: createdForm.formUrl,
    webhook: {
      attached: true,
      webhookUrl: webhookResult.webhookUrl,
    },
    savedAsPatientQuestionnaire: saveAsPatientQuestionnaire,
    hiddenFields: [
      JOTFORM_PROVIDER_KEY_FIELD_NAME,
      JOTFORM_ORDER_ID_FIELD_NAME,
      JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
    ],
    requestId,
  });
}

export async function handleJotformFormWebhooksRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  authUserId: string;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, authUserId, requestId } = params;
  const body = (await req.json()) as JotformWebhookRequestBody;
  const tenantIntegrationId = typeof body.tenantIntegrationId === "string"
    ? body.tenantIntegrationId.trim()
    : "";
  const formId = typeof body.formId === "string" ? body.formId.trim() : "";

  if (!tenantIntegrationId) {
    return jsonResponse(
      req,
      {
        error: "Missing tenantIntegrationId",
        message: "Tenant integration is required",
      },
      400,
    );
  }

  if (!formId) {
    return jsonResponse(
      req,
      {
        error: "Missing formId",
        message: "Jotform form ID is required",
      },
      400,
    );
  }

  const context = await resolveJotformApiContext({
    supabase,
    req,
    tenantIntegrationId,
    authUserId,
    requestId,
    resource: "fetch-jotform-form-webhooks",
  });

  if (!context.ok) return context.response;

  try {
    const result = await checkJotformDefaultWebhook({
      apiUrl: context.apiUrl,
      apiKey: context.apiKey,
      formId,
      defaultWebhookUrl: appendTenantToOptionalWebhookUrl(
        context.defaultWebhookUrl,
        context.tenantSlug,
      ),
      teamWorkspaceId: context.teamWorkspaceId,
      requestId,
    });

    return jsonResponse(req, {
      success: true,
      formId,
      defaultWebhookUrl: result.defaultWebhookUrl,
      webhookStatus: result.status,
      hasDefaultWebhook: result.hasDefaultWebhook,
      webhookCount: result.webhookCount ?? 0,
      message: result.message,
      requestId,
    });
  } catch (error) {
    return jsonResponse(
      req,
      {
        error: "Jotform webhook lookup failed",
        message: error instanceof Error
          ? error.message
          : "Unable to retrieve Jotform webhooks",
        ...(error instanceof JotformWriteError
          ? {
            jotformStatus: error.status,
            requestUrl: error.requestUrl,
            jotformResponse: error.responseBody,
          }
          : {}),
      },
      502,
    );
  }
}

export async function handleJotformFormWebhookUpdateRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  authUserId: string;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, authUserId, requestId } = params;
  const body = (await req.json()) as JotformWebhookRequestBody;
  const tenantIntegrationId = typeof body.tenantIntegrationId === "string"
    ? body.tenantIntegrationId.trim()
    : "";
  const formId = typeof body.formId === "string" ? body.formId.trim() : "";
  const requestedWebhookUrl = typeof body.webhookUrl === "string" &&
      body.webhookUrl.trim()
    ? body.webhookUrl.trim()
    : null;

  if (!tenantIntegrationId) {
    return jsonResponse(
      req,
      {
        error: "Missing tenantIntegrationId",
        message: "Tenant integration is required",
      },
      400,
    );
  }

  if (!formId) {
    return jsonResponse(
      req,
      {
        error: "Missing formId",
        message: "Jotform form ID is required",
      },
      400,
    );
  }

  if (requestedWebhookUrl) {
    let parsedWebhookUrl: URL;
    try {
      parsedWebhookUrl = new URL(requestedWebhookUrl);
    } catch {
      return jsonResponse(
        req,
        {
          error: "Invalid webhookUrl",
          message: "Provide a valid HTTP or HTTPS Jotform webhook URL.",
        },
        400,
      );
    }

    if (!/^https?:$/i.test(parsedWebhookUrl.protocol)) {
      return jsonResponse(
        req,
        {
          error: "Invalid webhookUrl",
          message: "Jotform webhook URL must use HTTP or HTTPS.",
        },
        400,
      );
    }
  }

  const context = await resolveJotformApiContext({
    supabase,
    req,
    tenantIntegrationId,
    authUserId,
    requestId,
    resource: "update-jotform-form-webhook",
  });

  if (!context.ok) return context.response;

  const webhookUrl = requestedWebhookUrl || context.defaultWebhookUrl;
  if (!webhookUrl) {
    return jsonResponse(req, {
      success: true,
      formId,
      defaultWebhookUrl: null,
      webhookStatus: "default_not_configured",
      hasDefaultWebhook: false,
      webhookCount: 0,
      message:
        "Default Webhook URL is not configured; webhook checks are suspended.",
      requestId,
    });
  }

  try {
    const tenantScopedWebhookUrl = appendTenantToWebhookUrl(
      webhookUrl,
      context.tenantSlug,
    );
    const result = await ensureJotformDefaultWebhook({
      apiUrl: context.apiUrl,
      apiKey: context.apiKey,
      formId,
      defaultWebhookUrl: tenantScopedWebhookUrl,
      teamWorkspaceId: context.teamWorkspaceId,
      requestId,
    });

    return jsonResponse(req, {
      success: true,
      formId,
      defaultWebhookUrl: result.defaultWebhookUrl,
      webhookStatus: result.status,
      hasDefaultWebhook: result.hasDefaultWebhook,
      webhookCount: result.webhookCount ?? 0,
      added: result.added ?? false,
      message: result.message,
      requestId,
    });
  } catch (error) {
    return jsonResponse(
      req,
      {
        error: "Jotform webhook update failed",
        message: error instanceof Error
          ? error.message
          : "Unable to update Jotform webhook",
        ...(error instanceof JotformWriteError
          ? {
            jotformStatus: error.status,
            requestUrl: error.requestUrl,
            jotformResponse: error.responseBody,
          }
          : {}),
      },
      502,
    );
  }
}

export async function handleJotformWebhookSyncRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  authUserId: string;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, authUserId, requestId } = params;
  const body = (await req.json()) as JotformWebhookSyncRequestBody;
  const tenantIntegrationId = typeof body.tenantIntegrationId === "string"
    ? body.tenantIntegrationId.trim()
    : "";

  if (!tenantIntegrationId) {
    return jsonResponse(
      req,
      {
        error: "Missing tenantIntegrationId",
        message: "Tenant integration is required",
      },
      400,
    );
  }

  const context = await resolveJotformApiContext({
    supabase,
    req,
    tenantIntegrationId,
    authUserId,
    requestId,
    resource: "sync-jotform-default-webhooks",
  });

  if (!context.ok) return context.response;

  if (!context.defaultWebhookUrl) {
    return jsonResponse(req, {
      success: true,
      defaultWebhookUrl: null,
      webhookStatus: "default_not_configured",
      checked: 0,
      configured: 0,
      added: 0,
      missing: 0,
      skipped: 0,
      inaccessible: 0,
      results: [],
      message:
        "Default Webhook URL is not configured; webhook checks are suspended.",
      requestId,
    });
  }

  const results = await syncConfiguredJotformDefaultWebhooks({
    supabase,
    tenantId: context.tenantIntegration.tenant_id,
    apiUrl: context.apiUrl,
    apiKey: context.apiKey,
    defaultWebhookUrl: appendTenantToWebhookUrl(
      context.defaultWebhookUrl,
      context.tenantSlug,
    ),
    teamWorkspaceId: context.teamWorkspaceId,
    requestId,
  });

  const summary = results.reduce(
    (current, result) => ({
      configured: current.configured +
        (result.status === "configured" ? 1 : 0),
      added: current.added + (result.added ? 1 : 0),
      missing: current.missing + (result.status === "missing" ? 1 : 0),
      skipped: current.skipped + (result.skipped ? 1 : 0),
      inaccessible: current.inaccessible +
        (result.status === "inaccessible" ? 1 : 0),
    }),
    { configured: 0, added: 0, missing: 0, skipped: 0, inaccessible: 0 },
  );

  return jsonResponse(req, {
    success: true,
    defaultWebhookUrl: context.defaultWebhookUrl,
    checked: results.length,
    ...summary,
    results: results.map((result) => ({
      formId: result.formId,
      webhookStatus: result.status,
      hasDefaultWebhook: result.hasDefaultWebhook,
      added: result.added ?? false,
      skipped: result.skipped ?? false,
      message: result.message,
    })),
    requestId,
  });
}

export async function handleJotformFormValidationRequest(params: {
  supabase: SupabaseAdminClient;
  req: Request;
  authUserId: string;
  requestId: string;
}): Promise<Response> {
  const { supabase, req, authUserId, requestId } = params;
  const body = (await req.json()) as JotformValidationRequestBody;
  const tenantIntegrationId = typeof body.tenantIntegrationId === "string"
    ? body.tenantIntegrationId.trim()
    : "";
  const formId = typeof body.formId === "string" ? body.formId.trim() : "";

  if (!tenantIntegrationId) {
    return jsonResponse(
      req,
      {
        error: "Missing tenantIntegrationId",
        message: "Tenant integration is required",
      },
      400,
    );
  }

  if (!formId) {
    return jsonResponse(
      req,
      {
        error: "Missing formId",
        message: "Jotform questionnaire ID is required",
      },
      400,
    );
  }

  if (!/^\d+$/.test(formId)) {
    return jsonResponse(
      req,
      {
        error: "Invalid formId",
        message: "Jotform questionnaire ID must be the numeric form ID.",
      },
      400,
    );
  }

  const providerIntegration = await fetchTenantIntegrationById({
    supabase,
    tenantIntegrationId,
  });

  if (!providerIntegration) {
    return jsonResponse(
      req,
      {
        error: "Tenant integration not found",
        message: "Enable the tenant integration before saving settings",
      },
      404,
    );
  }

  const hasTenantAccess = await userHasTenantAccess({
    supabase,
    authUserId,
    tenantId: providerIntegration.tenant_id,
  });

  if (!hasTenantAccess) {
    return jsonResponse(
      req,
      {
        error: "Forbidden",
        message: "You do not have access to validate this Jotform form",
      },
      403,
    );
  }

  const jotformIntegration = await fetchTenantIntegrationForTenantByKey({
    supabase,
    tenantId: providerIntegration.tenant_id,
    integrationKey: JOTFORM_INTEGRATION_KEY,
  });

  const apiUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_url",
  );
  const apiKey = getStringSetting(
    jotformIntegration?.settings ?? null,
    "api_key",
  );
  const teamWorkspaceId = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_TEAM_WORKSPACE_ID_SETTING,
  );
  const defaultWebhookUrl = getStringSetting(
    jotformIntegration?.settings ?? null,
    JOTFORM_DEFAULT_WEBHOOK_URL_SETTING,
  );
  const { data: tenantRow, error: tenantError } = await supabase
    .from("tenants")
    .select("slug")
    .eq("id", providerIntegration.tenant_id)
    .maybeSingle();

  const tenantScopedDefaultWebhookUrl =
    !tenantError && typeof tenantRow?.slug === "string" && tenantRow.slug.trim()
      ? appendTenantToOptionalWebhookUrl(
        defaultWebhookUrl,
        tenantRow.slug.trim(),
      )
      : defaultWebhookUrl;

  if (!apiUrl || !apiKey) {
    return jsonResponse(
      req,
      {
        error: "Jotform integration not configured",
        message:
          "Configure the Jotform API URL and API Key before saving a Jotform questionnaire ID.",
      },
      409,
    );
  }

  let questions: unknown;
  try {
    questions = await fetchJotformQuestions({
      apiUrl,
      apiKey,
      formId,
      teamWorkspaceId,
      requestId,
    });
  } catch (error) {
    return jsonResponse(
      req,
      {
        error: "Jotform form lookup failed",
        message: error instanceof Error
          ? error.message
          : "Unable to retrieve the Jotform form",
        formId,
        ...(error instanceof JotformLookupError
          ? {
            jotformStatus: error.status,
            lookupUrl: error.lookupUrl,
          }
          : {}),
      },
      422,
    );
  }

  const hasRequiredField = jotformQuestionsHaveOrderIdField(questions);

  if (!hasRequiredField) {
    return jsonResponse(
      req,
      {
        error: "Missing required Jotform field",
        message:
          `This Jotform form must include a field named ${JOTFORM_ORDER_ID_FIELD_NAME} before it can be saved.`,
      },
      422,
    );
  }

  let webhookResult: JotformDefaultWebhookCheckResult;
  try {
    webhookResult = await ensureJotformDefaultWebhook({
      apiUrl,
      apiKey,
      formId,
      defaultWebhookUrl: tenantScopedDefaultWebhookUrl,
      teamWorkspaceId,
      requestId,
    });
  } catch (error) {
    webhookResult = {
      formId,
      defaultWebhookUrl,
      status: "inaccessible",
      hasDefaultWebhook: false,
      skipped: true,
      message: error instanceof Error
        ? error.message
        : "Unable to retrieve or update Jotform webhooks.",
    };
  }

  return jsonResponse(req, {
    valid: true,
    formId,
    defaultWebhookUrl: webhookResult.defaultWebhookUrl,
    webhookStatus: webhookResult.status,
    hasDefaultWebhook: webhookResult.hasDefaultWebhook,
    webhookCount: webhookResult.webhookCount ?? 0,
    added: webhookResult.added ?? false,
    message: webhookResult.message,
  });
}

// ---------------------------------------------------------------------------
// JotForm Submission Processing
// ---------------------------------------------------------------------------

export function buildJotformSubmissionUrl(
  apiUrl: string,
  submissionId: string,
): string {
  const url = new URL(apiUrl);
  const pathSegments = url.pathname
    .split("/")
    .map((segment) => segment.trim())
    .filter(Boolean);
  const lastSegment = pathSegments[pathSegments.length - 1]?.toLowerCase();
  const hostAlreadyTargetsApi = url.hostname === "api.jotform.com" ||
    url.hostname.endsWith("-api.jotform.com");
  const baseSegments = hostAlreadyTargetsApi || lastSegment === "api"
    ? pathSegments.map((segment, index) =>
      index === pathSegments.length - 1 && segment.toLowerCase() === "api"
        ? "API"
        : segment
    )
    : [...pathSegments, "API"];

  url.pathname = `/${
    [
      ...baseSegments,
      "submission",
      encodeURIComponent(submissionId),
    ].join("/")
  }`;
  url.search = "";
  url.hash = "";
  return url.toString();
}

export interface JotformSubmissionAnswer {
  name: string;
  text: string;
  type: string;
  answer: unknown;
  order: string;
}

export interface JotformSubmissionContent {
  id: string;
  form_id: string;
  answers: Record<string, JotformSubmissionAnswer>;
  [key: string]: unknown;
}

export interface JotformMedicalQuestionnaireFormMatch {
  formId: string;
  selectedQuestionnaire: JotformQuestionnaireSelection;
}

export interface JotformSubmissionQuestionnaireMatch {
  type: JotformSubmissionQuestionnaireType;
  formId: string;
  selectedQuestionnaire?: JotformQuestionnaireSelection;
}

export class JotformSubmissionLookupError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly lookupUrl: string,
  ) {
    super(message);
    this.name = "JotformSubmissionLookupError";
  }
}

export async function fetchJotformSubmission(params: {
  apiUrl: string;
  apiKey: string;
  submissionId: string;
  teamWorkspaceId?: string | null;
  requestId: string;
}): Promise<JotformSubmissionContent> {
  const { apiUrl, apiKey, submissionId, teamWorkspaceId, requestId } = params;
  const submissionUrl = new URL(
    buildJotformSubmissionUrl(apiUrl, submissionId),
  );
  submissionUrl.searchParams.set("apiKey", apiKey);
  const requestUrl = submissionUrl.toString();
  const lookupUrl = redactJotformApiKeyFromUrl(requestUrl);
  const headers: Record<string, string> = {
    APIKEY: apiKey,
    Accept: "application/json",
    "x-request-id": requestId,
  };

  if (teamWorkspaceId?.trim()) {
    headers["jf-team-id"] = teamWorkspaceId.trim();
  }

  console.debug("jotform: fetching submission", {
    requestId,
    submissionId,
    lookupUrl,
  });

  const response = await fetch(requestUrl, {
    headers,
  });

  let body: Record<string, unknown> | null = null;
  try {
    body = (await response.json()) as Record<string, unknown>;
  } catch {
    body = null;
  }

  if (!response.ok) {
    const message = typeof body?.message === "string" && body.message.trim()
      ? body.message.trim()
      : `JotForm returned ${response.status}`;
    throw new JotformSubmissionLookupError(
      `Unable to retrieve JotForm submission ${submissionId}: ${message}`,
      response.status,
      lookupUrl,
    );
  }

  const content = body?.content;
  if (!content || typeof content !== "object" || Array.isArray(content)) {
    throw new Error(
      `JotForm submission ${submissionId} response content is invalid`,
    );
  }

  console.debug("jotform: submission fetched successfully", {
    requestId,
    submissionId,
    formId: (content as Record<string, unknown>).form_id,
    answerCount: Object.keys(
      (content as Record<string, unknown>).answers ?? {},
    ).length,
  });

  return content as unknown as JotformSubmissionContent;
}

export function getJotformSubmissionFormId(
  submission: Pick<JotformSubmissionContent, "form_id">,
): string | null {
  return typeof submission.form_id === "string" && submission.form_id.trim()
    ? submission.form_id.trim()
    : null;
}

export function getJotformSubmissionAnswerByName(
  submission: Pick<JotformSubmissionContent, "answers">,
  fieldName: string,
): JotformSubmissionAnswer | null {
  const normalizedFieldName = fieldName.trim();
  if (!normalizedFieldName) return null;

  return Object.values(submission.answers ?? {}).find((answer) =>
    answer?.name?.trim() === normalizedFieldName
  ) ?? null;
}

export function getJotformSubmissionOrderId(
  submission: Pick<JotformSubmissionContent, "answers">,
): string | null {
  const answer = getJotformSubmissionAnswerByName(
    submission,
    JOTFORM_ORDER_ID_FIELD_NAME,
  );

  if (!answer) return null;
  const value = stringifyJotformAnswer(answer.answer);
  return value.length > 0 ? value : null;
}

function getConfiguredJotformFormId(
  value: string | null | undefined,
): string | null {
  const formId = value?.trim();
  return formId && /^\d+$/.test(formId) ? formId : null;
}

export function getJotformPatientQuestionnaireFormId(
  providerIntegration:
    | Pick<TenantIntegrationRow, "settings">
    | null,
  legacyJotformIntegration?:
    | Pick<TenantIntegrationRow, "settings">
    | null,
): string | null {
  const providerFormId = getConfiguredJotformFormId(
    getStringSetting(
      providerIntegration?.settings ?? null,
      JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING,
    ),
  );
  if (providerFormId) return providerFormId;

  return getConfiguredJotformFormId(
    getStringSetting(
      legacyJotformIntegration?.settings ?? null,
      JOTFORM_PATIENT_QUESTIONNAIRE_FORM_ID_SETTING,
    ),
  );
}

export function getJotformMedicalQuestionnaireFormMatch(params: {
  order: Pick<OrderRow, "subscription_order_type">;
  productProviderPlatform:
    | Pick<
      ProductProviderPlatformRow,
      | "jotform_new_order_questionnaire_id"
      | "jotform_renewall_questionnaire_id"
    >
    | null;
}): JotformMedicalQuestionnaireFormMatch | null {
  const selectedQuestionnaire = selectJotformQuestionnaire(params.order);
  const formId = getConfiguredJotformFormId(
    selectedQuestionnaire === "renewal"
      ? params.productProviderPlatform?.jotform_renewall_questionnaire_id
      : params.productProviderPlatform?.jotform_new_order_questionnaire_id,
  );

  return formId ? { formId, selectedQuestionnaire } : null;
}

export function resolveJotformSubmissionQuestionnaireMatch(params: {
  submission: Pick<JotformSubmissionContent, "form_id">;
  order: Pick<OrderRow, "subscription_order_type">;
  productProviderPlatform:
    | Pick<
      ProductProviderPlatformRow,
      | "jotform_new_order_questionnaire_id"
      | "jotform_renewall_questionnaire_id"
    >
    | null;
  providerIntegration:
    | Pick<TenantIntegrationRow, "settings">
    | null;
  jotformIntegration:
    | Pick<TenantIntegrationRow, "settings">
    | null;
}): JotformSubmissionQuestionnaireMatch | null {
  const submittedFormId = getJotformSubmissionFormId(params.submission);
  if (!submittedFormId) return null;

  const patientQuestionnaireFormId = getJotformPatientQuestionnaireFormId(
    params.providerIntegration,
    params.jotformIntegration,
  );
  const medicalQuestionnaireForm = getJotformMedicalQuestionnaireFormMatch({
    order: params.order,
    productProviderPlatform: params.productProviderPlatform,
  });

  if (
    patientQuestionnaireFormId &&
    medicalQuestionnaireForm?.formId &&
    patientQuestionnaireFormId === medicalQuestionnaireForm.formId &&
    submittedFormId === patientQuestionnaireFormId
  ) {
    return null;
  }

  if (patientQuestionnaireFormId === submittedFormId) {
    return {
      type: "patient_questionnaire",
      formId: submittedFormId,
    };
  }

  if (medicalQuestionnaireForm?.formId === submittedFormId) {
    return {
      type: "medical_questionnaire",
      formId: submittedFormId,
      selectedQuestionnaire: medicalQuestionnaireForm.selectedQuestionnaire,
    };
  }

  return null;
}

const JOTFORM_UI_ONLY_TYPES = new Set([
  "control_head",
  "control_button",
  "control_pagebreak",
  "control_collapse",
  "control_divider",
  "control_text",
  "control_image",
  "control_hidden",
]);

const JOTFORM_FILE_UPLOAD_TYPE = "control_fileupload";

const JOTFORM_ID_FILE_FIELD_LABELS = new Set([
  "id upload",
  "file upload",
  "id document",
  "id verification",
  "take from camera",
  "pick from gallery",
]);

export function isJotformFileUploadField(type: string): boolean {
  return type === JOTFORM_FILE_UPLOAD_TYPE;
}

export function isJotformUiOnlyField(type: string): boolean {
  return JOTFORM_UI_ONLY_TYPES.has(type);
}

export function isJotformIdFileField(fieldText: string): boolean {
  return JOTFORM_ID_FILE_FIELD_LABELS.has(fieldText.trim().toLowerCase());
}

export function stringifyJotformAnswer(answer: unknown): string {
  if (answer === null || answer === undefined) return "";
  if (typeof answer === "string") return answer.trim();
  if (typeof answer === "number" || typeof answer === "boolean") {
    return String(answer);
  }

  if (Array.isArray(answer)) {
    return answer
      .map((item) => typeof item === "string" ? item.trim() : String(item))
      .filter((item) => item.length > 0)
      .join(", ");
  }

  if (typeof answer === "object") {
    const record = answer as Record<string, unknown>;
    // Handle fullname: { first, middle, last }
    if ("first" in record || "last" in record) {
      return [
        record.prefix,
        record.first,
        record.middle,
        record.last,
        record.suffix,
      ]
        .filter((part) => typeof part === "string" && part.trim().length > 0)
        .map((part) => (part as string).trim())
        .join(" ");
    }
    // Handle address: { addr_line1, addr_line2, city, state, postal, country }
    if ("addr_line1" in record || "city" in record) {
      return [
        record.addr_line1,
        record.addr_line2,
        record.city,
        record.state,
        record.postal,
        record.country,
      ]
        .filter((part) => typeof part === "string" && part.trim().length > 0)
        .map((part) => (part as string).trim())
        .join(", ");
    }
    // Handle datetime: { year, month, day, hour, min, ampm }
    if ("year" in record || "month" in record) {
      const dateParts = [record.year, record.month, record.day]
        .filter((part) =>
          part !== undefined && part !== null &&
          String(part).trim().length > 0
        );
      const timeParts = [record.hour, record.min]
        .filter((part) =>
          part !== undefined && part !== null &&
          String(part).trim().length > 0
        );
      let result = dateParts.map(String).join("-");
      if (timeParts.length > 0) {
        result += ` ${timeParts.map(String).join(":")}`;
        if (typeof record.ampm === "string") result += ` ${record.ampm}`;
      }
      return result;
    }
    // Generic object: join non-empty values
    return Object.values(record)
      .filter((value) =>
        value !== null && value !== undefined &&
        String(value).trim().length > 0
      )
      .map((value) => String(value).trim())
      .join(", ");
  }

  return String(answer);
}

export function extractJotformFileUrls(answer: unknown): string[] {
  const urls: string[] = [];
  const seen = new Set<string>();

  function addUrl(value: string, nested: boolean): void {
    const trimmed = value.trim();
    if (!trimmed) return;
    if (nested && !/^https?:\/\//i.test(trimmed)) return;
    if (seen.has(trimmed)) return;
    seen.add(trimmed);
    urls.push(trimmed);
  }

  function visit(value: unknown, nested: boolean): void {
    if (typeof value === "string") {
      const trimmed = value.trim();
      if (!trimmed) return;

      if (/^https?:\/\//i.test(trimmed)) {
        addUrl(trimmed, nested);
        return;
      }

      if (/^[{[]/.test(trimmed)) {
        try {
          const parsed = JSON.parse(trimmed) as unknown;
          visit(parsed, true);
        } catch {
          // Ignore non-JSON strings that are not URLs.
        }
      }
      return;
    }

    if (Array.isArray(value)) {
      for (const entry of value) visit(entry, true);
      return;
    }

    if (value && typeof value === "object") {
      for (const entry of Object.values(value as Record<string, unknown>)) {
        visit(entry, true);
      }
    }
  }

  visit(answer, false);
  return urls;
}
