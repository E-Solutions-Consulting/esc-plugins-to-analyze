type JsonRecord = Record<string, unknown>;

const FUNCTION_PREFIX = "/provider-platform-bridge";

export interface TelegraSymptomSummary {
  id: string | null;
  description: string | null;
  name: string;
}

interface PopulateSymptomsQuestionnaireResult {
  questionnaire: Record<string, unknown>;
  replacedCount: number;
}

export interface ParsedAnswerLocationPayload {
  questionnaireId: string | null;
  location: string | null;
  value: string | string[] | null;
  file: File | null;
}

function isInformedConsentLocation(location: string): boolean {
  return location.toLowerCase() === "loc::informed-consent:1";
}

function buildTelegraAgreementData(params: {
  signature: string;
  consentDate: string;
}): Record<string, unknown> {
  const { signature, consentDate } = params;
  return {
    consent: true,
    consentDate,
    signature,
  };
}

function parseStringField(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0
    ? value.trim()
    : null;
}

function parseStringArrayField(value: unknown): string[] | null {
  if (!Array.isArray(value)) return null;

  const parsedValues = value
    .map((entry) => parseStringField(entry))
    .filter((entry): entry is string => entry !== null);

  return parsedValues.length > 0 ? parsedValues : null;
}

function parseFormDataValueEntry(
  valueEntry: FormDataEntryValue | null,
): string | string[] | null {
  if (typeof valueEntry !== "string") return null;

  const trimmedValue = valueEntry.trim();
  if (trimmedValue.length === 0) return null;

  if (trimmedValue.startsWith("[")) {
    try {
      const parsed = JSON.parse(trimmedValue);
      const parsedArray = parseStringArrayField(parsed);
      if (parsedArray) return parsedArray;
    } catch {
      // Fall through to treating as a scalar string when JSON parsing fails.
    }
  }

  return trimmedValue;
}

export function normalizeProviderPlatformBridgePath(pathname: string): string {
  let path = pathname.replace(/^\/functions\/v1/, "");
  if (path.startsWith(FUNCTION_PREFIX)) {
    path = path.slice(FUNCTION_PREFIX.length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) {
    path = path.slice(0, -1);
  }
  return path;
}

export function getStringSetting(
  settings: Record<string, unknown> | null | undefined,
  key: string,
): string | null {
  const value = settings?.[key];
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.length > 0 ? trimmedValue : null;
}

export function normalizeProviderPlatformIdentifier(
  value: string | null | undefined,
): string | null {
  if (!value) return null;

  const normalized = value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "");

  return normalized.length > 0 ? normalized : null;
}

export function isTelegraProviderPlatform(
  value: string | null | undefined,
): boolean {
  const normalizedValue = normalizeProviderPlatformIdentifier(value);
  return normalizedValue === "telegramd" || normalizedValue === "telegra";
}

export function extractQuestionnaireInstanceIdsFromMetadata(
  metadata: unknown,
): string[] {
  if (!metadata || typeof metadata !== "object") return [];

  const rawValue = (metadata as JsonRecord).questionnaire_instance_ids;
  if (!Array.isArray(rawValue)) return [];

  return rawValue
    .filter((value): value is string =>
      typeof value === "string" && value.trim().length > 0
    )
    .map((value) => value.trim());
}

export function extractProviderNameFromMetadata(
  metadata: unknown,
): string | null {
  if (!metadata || typeof metadata !== "object") return null;

  const rawValue = (metadata as JsonRecord).provider;
  return typeof rawValue === "string" && rawValue.trim().length > 0
    ? rawValue.trim()
    : null;
}

export function buildTelegraQuestionnaireSchemaUrl(
  baseUrl: string,
  questionnaireInstanceId: string,
): string {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const encodedId = encodeURIComponent(questionnaireInstanceId);
  return `${normalizedBaseUrl}/questionnaireInstances/${encodedId}/schema`;
}

export function buildTelegraQuestionnaireInstanceUrl(
  baseUrl: string,
  questionnaireInstanceId: string,
): string {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const encodedId = encodeURIComponent(questionnaireInstanceId);
  return `${normalizedBaseUrl}/questionnaireInstances/${encodedId}`;
}

export function buildTelegraQuestionnaireAnswerLocationUrl(
  baseUrl: string,
  questionnaireInstanceId: string,
): string {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const encodedId = encodeURIComponent(questionnaireInstanceId);
  return `${normalizedBaseUrl}/questionnaireInstances/${encodedId}/actions/answerLocation?shouldNavigateNext=true`;
}

export function buildTelegraQuestionnaireAnswerUrl(
  baseUrl: string,
  questionnaireInstanceId: string,
): string {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const encodedId = encodeURIComponent(questionnaireInstanceId);
  return `${normalizedBaseUrl}/questionnaireInstances/${encodedId}/actions/answer`;
}

export function buildTelegraPatientUrl(
  baseUrl: string,
  providerPatientId: string,
): string {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const encodedId = encodeURIComponent(providerPatientId);
  return `${normalizedBaseUrl}/patients/${encodedId}`;
}

export function buildTelegraConditionsAndSymptomsUrl(
  baseUrl: string,
  productIds: string[],
): string {
  const normalizedBaseUrl = baseUrl.replace(/\/+$/, "");
  const url = new URL(
    `${normalizedBaseUrl}/products/actions/getConditionsAndSymptoms`,
  );
  const sanitizedProductIds = productIds
    .map((productId) => parseStringField(productId))
    .filter((productId): productId is string => productId !== null);

  if (sanitizedProductIds.length > 0) {
    url.searchParams.set("products", sanitizedProductIds.join(","));
  }

  return url.toString();
}

export function parseAnswerLocationFormData(
  formData: FormData,
): ParsedAnswerLocationPayload {
  const questionnaireId = parseStringField(formData.get("questionnaire-id")) ||
    parseStringField(formData.get("questionnaireId"));
  const location = parseStringField(formData.get("location"));
  const valueEntry = formData.get("value");

  return {
    questionnaireId,
    location,
    value: parseFormDataValueEntry(valueEntry),
    file: valueEntry instanceof File ? valueEntry : null,
  };
}

export function buildTelegraAnswerRequestInit(params: {
  location: string;
  value?: string | string[] | File;
  data?: Record<string, unknown>;
  accessToken: string;
  requestId: string;
}): RequestInit {
  const { location, value, data, accessToken, requestId } = params;

  if (value instanceof File) {
    const formData = new FormData();
    formData.set("lastLocation", location);
    formData.set("responses[0][location]", location);
    formData.set("responses[0][value]", value, value.name);

    return {
      method: "PUT",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "x-request-id": requestId,
        "x-source": "provider-platform-bridge",
      },
      body: formData,
    };
  }

  const responseEntry: Record<string, unknown> = { location };
  if (typeof data !== "undefined") {
    responseEntry.data = data;
  } else {
    responseEntry.value = value;
  }

  return {
    method: "PUT",
    headers: {
      Authorization: `Bearer ${accessToken}`,
      "Content-Type": "application/json",
      "x-request-id": requestId,
      "x-source": "provider-platform-bridge",
    },
    body: JSON.stringify({
      lastLocation: location,
      responses: [
        responseEntry,
      ],
    }),
  };
}

export function buildTelegraAnswerLocationRequestInit(params: {
  location: string;
  value: string | string[] | File;
  accessToken: string;
  requestId: string;
  agreementData?: Record<string, unknown>;
}): RequestInit {
  const { location, value, accessToken, requestId, agreementData } = params;
  const headers: Record<string, string> = {
    Authorization: `Bearer ${accessToken}`,
    "x-request-id": requestId,
    "x-source": "provider-platform-bridge",
  };

  if (value instanceof File && !agreementData) {
    const formData = new FormData();
    formData.set("location", location);
    formData.set("value", value, value.name);

    return {
      method: "PUT",
      headers,
      body: formData,
    };
  }

  headers["Content-Type"] = "application/json";

  if (agreementData) {
    return {
      method: "PUT",
      headers,
      body: JSON.stringify({
        location,
        data: {
          agreementData,
        },
      }),
    };
  }

  return {
    method: "PUT",
    headers,
    body: JSON.stringify({
      location,
      value,
    }),
  };
}

export function buildTelegraAnswerLocationAgreementData(params: {
  location: string;
  signature: string;
  consentDate?: string;
}): Record<string, unknown> | null {
  const { location, signature, consentDate = new Date().toISOString() } =
    params;
  if (!isInformedConsentLocation(location)) return null;

  return buildTelegraAgreementData({
    consentDate,
    signature,
  });
}

export function extractTelegraSymptoms(
  responseBody: unknown,
): TelegraSymptomSummary[] {
  if (!responseBody || typeof responseBody !== "object") return [];

  const rawSymptoms = (responseBody as JsonRecord).symptoms;
  if (!Array.isArray(rawSymptoms)) return [];

  const symptomsById = new Map<string, TelegraSymptomSummary>();

  for (const rawSymptom of rawSymptoms) {
    if (!rawSymptom || typeof rawSymptom !== "object") continue;

    const symptom = rawSymptom as JsonRecord;
    if (symptom.deleted === true) continue;

    const id = parseStringField(symptom.id) || parseStringField(symptom._id);
    const name = parseStringField(symptom.name);

    if (!id || !name || symptomsById.has(id)) continue;

    symptomsById.set(id, {
      id,
      description: parseStringField(symptom.description),
      name,
    });
  }

  return [
    ...Array.from(symptomsById.values()),
    {
      id: null,
      description: "None of the above",
      name: "None of the above",
    },
  ];
}

export function populateSymptomsQuestionnaireOptions(
  definition: unknown,
  symptoms: TelegraSymptomSummary[],
): PopulateSymptomsQuestionnaireResult | null {
  if (
    !definition || typeof definition !== "object" || Array.isArray(definition)
  ) {
    return null;
  }

  let replacedCount = 0;

  const visit = (node: unknown): unknown => {
    if (Array.isArray(node)) {
      return node.map((entry) => visit(entry));
    }

    if (!node || typeof node !== "object") {
      return node;
    }

    const record = node as JsonRecord;
    const next: JsonRecord = {};

    for (const [key, value] of Object.entries(record)) {
      next[key] = visit(value);
    }

    if (
      typeof record.type === "string" &&
      record.type.trim().toLowerCase() === "symptoms"
    ) {
      next.options = symptoms.map((symptom) => ({ ...symptom }));
      replacedCount += 1;
    }

    return next;
  };

  return {
    questionnaire: visit(definition) as Record<string, unknown>,
    replacedCount,
  };
}
