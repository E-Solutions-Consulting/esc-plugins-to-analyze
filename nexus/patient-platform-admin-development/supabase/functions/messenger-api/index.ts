import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { checkRateLimit, getCorsHeaders } from "../patient-api/helpers.ts";
import { resolveMdiAccessToken } from "../_shared/mdi-auth.ts";
import {
  appendTelegraRequestTimestamp,
  resolveTelegraAccessToken,
} from "../_shared/telegra-auth.ts";
import {
  buildMdiPatientMessagesEndpointUrl,
  decodeProviderChatContextId,
  encodeProviderChatContextId,
  extractMdiUploadedFileId,
  filterTelegraSystemMessagesInChats,
  getMdiPartnerFileSizeLimitBytes,
  isMdiPartnerFileType,
  normalizeMdiMessages,
  normalizeMdiSingleMessage,
  parseTelegraChatFilePayload,
  summarizeMdiMessages,
} from "./helpers.ts";

type JsonRecord = Record<string, unknown>;

interface TenantIntegrationForChat {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
  settings: Record<string, unknown> | null;
}

interface PatientProviderPlatformLinkForChat {
  id: string;
  tenant_id: string;
  patient_id: string;
  tenant_integration_id: string;
  provider_patient_id: string | null;
  metadata: Record<string, unknown> | null;
  tenant_integrations:
    | TenantIntegrationForChat[]
    | TenantIntegrationForChat
    | null;
}

interface ProviderChatOrderLink {
  id: string;
  tenant_id: string;
  order_id: string;
  tenant_integration_id: string;
  provider_order_id: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
  tenant_integrations:
    | TenantIntegrationForChat[]
    | TenantIntegrationForChat
    | null;
}

interface ProviderChatOrder {
  id: string;
  subscription_id: string | null;
  order_number: string | null;
  product_id: string | null;
  provider_platform_integration_key: string | null;
  provider_platform_order_id: string | null;
  created_at: string;
  updated_at: string;
  order_statuses:
    | {
      status_key: string | null;
      is_terminal: boolean | null;
      display_order: number | null;
      patient_status_label?: string | null;
      admin_status_label?: string | null;
    }[]
    | {
      status_key: string | null;
      is_terminal: boolean | null;
      display_order: number | null;
      patient_status_label?: string | null;
      admin_status_label?: string | null;
    }
    | null;
  product:
    | {
      id: string;
      name: string | null;
    }[]
    | {
      id: string;
      name: string | null;
    }
    | null;
}

interface ProviderChatPlan {
  id: string;
  status: string | null;
  cancelled_at: string | null;
  created_at: string;
  updated_at: string;
  product:
    | {
      id: string;
      name: string | null;
    }[]
    | {
      id: string;
      name: string | null;
    }
    | null;
}

interface ProviderChatContextSummary {
  unread_count: number;
  last_message_at: string | null;
  last_message_preview: string | null;
}

interface ProviderChatContextResult {
  chat_context_id: string | null;
  plan_id: string;
  plan_title: string;
  plan_status: string | null;
  is_fully_finished: boolean;
  chat_available: boolean;
  unavailable_reason: string | null;
  selected_order_id: string | null;
  provider_order_id: string | null;
  provider: {
    id: string | null;
    integration_key: string | null;
    name: string | null;
    chat_id?: string | null;
  } | null;
  selected_order: {
    id: string;
    order_number: string | null;
    status: {
      key: string | null;
      label: string | null;
      is_terminal: boolean | null;
    } | null;
    provider_id: string | null;
    provider_order_id: string | null;
    provider_chat_id: string | null;
    provider: {
      id: string | null;
      integration_key: string | null;
      name: string | null;
    } | null;
    medication_or_product_name: string | null;
    product: {
      id: string | null;
      name: string | null;
    } | null;
    created_at: string;
    updated_at: string | null;
  } | null;
  summary: ProviderChatContextSummary;
  capabilities: {
    attachments: boolean;
    read_receipts: boolean;
  };
}

type ProviderChatProviderInfo = NonNullable<
  ProviderChatContextResult["provider"]
>;
type ProviderChatSelectedOrder = NonNullable<
  ProviderChatContextResult["selected_order"]
>;

interface ResolvedProviderChatContext {
  context: ProviderChatContextResult;
  token: {
    version: 1;
    plan_id: string;
    order_id: string;
    tenant_integration_id: string;
  };
}

interface AuthenticatedPatient {
  id: string;
  tenant_id: string;
  access_status: string | null;
}

interface TelegraContext {
  providerLink: PatientProviderPlatformLinkForChat;
  tenantIntegration: TenantIntegrationForChat;
  providerPatientId: string;
  providerName: string;
  baseUrl: string;
  accessToken: string;
}

interface ProviderChatAttachmentReference {
  id: string;
}

type ParsedProviderChatAttachments =
  | { attachments: ProviderChatAttachmentReference[]; error: null }
  | { attachments: null; error: Response };

interface MdiContext {
  providerLink: PatientProviderPlatformLinkForChat;
  tenantIntegration: TenantIntegrationForChat;
  providerPatientId: string;
  providerName: string;
  backendUrl: string;
  accessToken: string;
}

function normalizeProviderPlatformIdentifier(
  value: string | null | undefined,
): string | null {
  if (!value) return null;

  const normalized = value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "");

  return normalized.length > 0 ? normalized : null;
}

function isTelegraProviderPlatform(value: string | null | undefined): boolean {
  const normalizedValue = normalizeProviderPlatformIdentifier(value);
  return normalizedValue === "telegramd" || normalizedValue === "telegra";
}

function isMdiProviderPlatform(value: string | null | undefined): boolean {
  const normalizedValue = normalizeProviderPlatformIdentifier(value);
  return normalizedValue === "mdintegrations" || normalizedValue === "mdi";
}

function extractProviderNameFromMetadata(metadata: unknown): string | null {
  if (!metadata || typeof metadata !== "object") return null;

  const rawValue = (metadata as Record<string, unknown>).provider;
  return typeof rawValue === "string" && rawValue.trim().length > 0
    ? rawValue.trim()
    : null;
}

function getStringSetting(
  settings: Record<string, unknown> | null | undefined,
  key: string,
): string | null {
  const value = settings?.[key];
  if (typeof value !== "string") return null;

  const trimmedValue = value.trim();
  return trimmedValue.length > 0 ? trimmedValue : null;
}

function getValueAtPath(source: unknown, path: string): unknown {
  return path.split(".").reduce<unknown>((current, segment) => {
    if (!current || typeof current !== "object") return undefined;
    return (current as JsonRecord)[segment];
  }, source);
}

function extractTelegraChatThreads(responseBody: unknown): unknown[] {
  if (Array.isArray(responseBody)) return responseBody;
  if (!responseBody || typeof responseBody !== "object") return [];

  const channelPaths = ["channel", "body.channel", "data.channel"];

  for (const path of channelPaths) {
    const value = getValueAtPath(responseBody, path);
    if (Array.isArray(value)) return value;
    if (value && typeof value === "object") return [value];
  }

  return [];
}

function extractTelegraParticipantIdentifier(
  responseBody: unknown,
): string | null {
  if (!responseBody || typeof responseBody !== "object") return null;

  const candidatePaths = [
    "participantIdentifier",
    "body.participantIdentifier",
    "data.participantIdentifier",
  ];

  for (const path of candidatePaths) {
    const value = getValueAtPath(responseBody, path);
    if (typeof value === "string" && value.trim().length > 0) {
      return value.trim();
    }
  }

  return null;
}

function includeParticipantIdentifierInChats(
  chats: unknown[],
  participantIdentifier: string | null,
): unknown[] {
  if (!participantIdentifier) return chats;

  return chats.map((chat) => {
    if (!chat || typeof chat !== "object" || Array.isArray(chat)) {
      return chat;
    }

    const record = chat as Record<string, unknown>;
    const currentParticipantIdentifier = record.participantIdentifier;
    if (
      typeof currentParticipantIdentifier === "string" &&
      currentParticipantIdentifier.trim().length > 0
    ) {
      return chat;
    }

    return {
      ...record,
      participantIdentifier,
    };
  });
}

function extractErrorMessage(responseBody: unknown, fallback: string): string {
  if (!responseBody || typeof responseBody !== "object") {
    return fallback;
  }

  const record = responseBody as Record<string, unknown>;
  const candidateKeys = ["message", "error", "detail"];
  for (const key of candidateKeys) {
    const value = record[key];
    if (typeof value === "string" && value.trim().length > 0) {
      return value;
    }
  }

  return fallback;
}

function extractRecordAtPath(
  responseBody: unknown,
  path: string,
): JsonRecord | null {
  const value = getValueAtPath(responseBody, path);
  return value && typeof value === "object" && !Array.isArray(value)
    ? value as JsonRecord
    : null;
}

function extractMdiPagination(responseBody: unknown): Record<string, unknown> {
  return {
    meta: extractRecordAtPath(responseBody, "meta") ||
      extractRecordAtPath(responseBody, "data.meta"),
    links: extractRecordAtPath(responseBody, "links") ||
      extractRecordAtPath(responseBody, "data.links"),
  };
}

async function parseJsonOrTextResponse(response: Response): Promise<unknown> {
  const rawResponse = await response.text();
  if (!rawResponse) return null;

  try {
    return JSON.parse(rawResponse);
  } catch {
    return rawResponse;
  }
}

function resolveTenantIntegration(
  providerLink: PatientProviderPlatformLinkForChat,
): TenantIntegrationForChat | null {
  if (!providerLink.tenant_integrations) return null;
  return Array.isArray(providerLink.tenant_integrations)
    ? providerLink.tenant_integrations[0] || null
    : providerLink.tenant_integrations;
}

Deno.serve(async (req) => {
  const corsHeaders = getCorsHeaders(req);

  function jsonResponse(
    data: unknown,
    status = 200,
    headers: Record<string, string> = {},
  ) {
    return new Response(JSON.stringify(data), {
      status,
      headers: {
        ...corsHeaders,
        "Content-Type": "application/json",
        ...headers,
      },
    });
  }

  function errorResponse(code: string, message: string, status = 400) {
    return jsonResponse({ error: { code, message } }, status);
  }

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const url = new URL(req.url);
  // Normalize path so the router works consistently across environments.
  // Depending on the gateway/runtime, the function may see either:
  // - /messenger-api/telegra-clinical-chat
  // - /functions/v1/messenger-api/telegra-clinical-chat
  const pathname = url.pathname;
  let path = pathname;
  path = path.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/messenger-api")) {
    path = path.slice("/messenger-api".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) path = path.slice(0, -1);

  const clientIp = req.headers.get("x-forwarded-for") || "unknown";
  const requestId = req.headers.get("x-request-id") || crypto.randomUUID();
  const rateCheck = checkRateLimit(clientIp);

  if (!rateCheck.allowed) {
    return errorResponse(
      "RATE_LIMIT_EXCEEDED",
      "Too many requests. Please try again later.",
      429,
    );
  }

  const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY")!;
  const supabaseServiceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const authHeader = req.headers.get("Authorization");

  const supabase = createClient(supabaseUrl, supabaseAnonKey, {
    global: {
      headers: authHeader ? { Authorization: authHeader } : {},
    },
  });

  const supabaseAdmin = createClient(supabaseUrl, supabaseServiceRoleKey, {
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });

  async function getAuthenticatedPatient() {
    if (!authHeader) {
      return {
        patient: null,
        error: errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        ),
      };
    }

    const {
      data: { user },
      error: userError,
    } = await supabase.auth.getUser();

    if (userError || !user) {
      return {
        patient: null,
        error: errorResponse("UNAUTHORIZED", "Invalid or expired token", 401),
      };
    }

    const { data: patient, error: patientError } = await supabaseAdmin
      .from("patients")
      .select("id, tenant_id, access_status")
      .eq("auth_user_id", user.id)
      .maybeSingle();

    if (patientError) {
      console.error("Patient fetch error:", patientError);
      return {
        patient: null,
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient profile",
          500,
        ),
      };
    }

    if (!patient) {
      return {
        patient: null,
        error: errorResponse("NOT_FOUND", "Patient profile not found", 404),
      };
    }

    if (patient.access_status !== "active") {
      return {
        patient: null,
        error: errorResponse(
          "ACCOUNT_INACTIVE",
          `Your account is ${patient.access_status}`,
          403,
        ),
      };
    }

    return {
      patient: patient as AuthenticatedPatient,
      error: null,
    };
  }

  async function getTelegraContext(
    patient: AuthenticatedPatient,
    options: { tenantIntegrationId?: string } = {},
  ) {
    const { data: providerLinks, error: providerLinksError } =
      await supabaseAdmin
        .from("patient_provider_platform_links")
        .select(`
          id,
          tenant_id,
          patient_id,
          tenant_integration_id,
          provider_patient_id,
          metadata,
          tenant_integrations!inner (
            id,
            tenant_id,
            integration_key,
            is_enabled,
            settings
          )
        `)
        .eq("patient_id", patient.id)
        .eq("tenant_id", patient.tenant_id);

    if (providerLinksError) {
      console.error(
        "Provider platform links fetch error:",
        providerLinksError,
      );
      return {
        context: null,
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient provider platform links",
          500,
        ),
      };
    }

    const telegraProviderLink =
      ((providerLinks || []) as PatientProviderPlatformLinkForChat[]).find(
        (link) => {
          const tenantIntegration = resolveTenantIntegration(link);
          if (
            options.tenantIntegrationId &&
            tenantIntegration?.id !== options.tenantIntegrationId
          ) {
            return false;
          }
          return tenantIntegration?.is_enabled === true &&
            (
              isTelegraProviderPlatform(tenantIntegration?.integration_key) ||
              isTelegraProviderPlatform(
                extractProviderNameFromMetadata(link.metadata),
              )
            );
        },
      ) || null;

    if (!telegraProviderLink) {
      return {
        context: null,
        error: errorResponse(
          "TELEGRA_NOT_CONFIGURED",
          "No enabled Telegra provider platform integration was found for this patient",
          404,
        ),
      };
    }

    const telegraTenantIntegration = resolveTenantIntegration(
      telegraProviderLink,
    );

    if (!telegraTenantIntegration) {
      return {
        context: null,
        error: errorResponse(
          "TELEGRA_CONFIG_MISSING",
          "Telegra tenant integration was not found for this patient",
          500,
        ),
      };
    }

    const telegraPatientId =
      typeof telegraProviderLink.provider_patient_id === "string" &&
        telegraProviderLink.provider_patient_id.trim().length > 0
        ? telegraProviderLink.provider_patient_id.trim()
        : null;

    if (!telegraPatientId) {
      return {
        context: null,
        error: errorResponse(
          "TELEGRA_PATIENT_ID_MISSING",
          "No Telegra patient id is linked to this patient",
          404,
        ),
      };
    }

    const telegraBaseUrl = getStringSetting(
      telegraTenantIntegration.settings,
      "url",
    );
    if (!telegraBaseUrl) {
      return {
        context: null,
        error: errorResponse(
          "TELEGRA_CONFIG_MISSING",
          "Telegra integration is missing URL configuration",
          500,
        ),
      };
    }

    const authResult = await resolveTelegraAccessToken({
      supabase: supabaseAdmin,
      tenantIntegrationId: telegraTenantIntegration.id,
      tenantId: telegraTenantIntegration.tenant_id,
      settings: telegraTenantIntegration.settings,
      baseUrl: telegraBaseUrl,
      requestId,
      source: "messenger-api",
    });

    if ("errorMessage" in authResult) {
      return {
        context: null,
        error: errorResponse(
          "TELEGRA_CONFIG_MISSING",
          authResult.errorMessage,
          500,
        ),
      };
    }

    const telegraProviderName =
      extractProviderNameFromMetadata(telegraProviderLink.metadata) ||
      "TelegraMD";

    return {
      context: {
        providerLink: telegraProviderLink,
        tenantIntegration: telegraTenantIntegration,
        providerPatientId: telegraPatientId,
        providerName: telegraProviderName,
        baseUrl: telegraBaseUrl,
        accessToken: authResult.accessToken,
      } as TelegraContext,
      error: null,
    };
  }

  async function getMdiContext(
    patient: AuthenticatedPatient,
    options: { tenantIntegrationId?: string } = {},
  ) {
    const { data: providerLinks, error: providerLinksError } =
      await supabaseAdmin
        .from("patient_provider_platform_links")
        .select(`
          id,
          tenant_id,
          patient_id,
          tenant_integration_id,
          provider_patient_id,
          metadata,
          tenant_integrations!inner (
            id,
            tenant_id,
            integration_key,
            is_enabled,
            settings
          )
        `)
        .eq("patient_id", patient.id)
        .eq("tenant_id", patient.tenant_id);

    if (providerLinksError) {
      console.error(
        "Provider platform links fetch error:",
        providerLinksError,
      );
      return {
        context: null,
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient provider platform links",
          500,
        ),
      };
    }

    const mdiProviderLink =
      ((providerLinks || []) as PatientProviderPlatformLinkForChat[]).find(
        (link) => {
          const tenantIntegration = resolveTenantIntegration(link);
          if (
            options.tenantIntegrationId &&
            tenantIntegration?.id !== options.tenantIntegrationId
          ) {
            return false;
          }
          return tenantIntegration?.is_enabled === true &&
            (
              isMdiProviderPlatform(tenantIntegration?.integration_key) ||
              isMdiProviderPlatform(
                extractProviderNameFromMetadata(link.metadata),
              )
            );
        },
      ) || null;

    if (!mdiProviderLink) {
      return {
        context: null,
        error: errorResponse(
          "MDI_NOT_CONFIGURED",
          "No enabled MDI provider platform integration was found for this patient",
          404,
        ),
      };
    }

    const mdiTenantIntegration = resolveTenantIntegration(mdiProviderLink);

    if (!mdiTenantIntegration) {
      return {
        context: null,
        error: errorResponse(
          "MDI_CONFIG_MISSING",
          "MDI tenant integration was not found for this patient",
          500,
        ),
      };
    }

    const mdiPatientId =
      typeof mdiProviderLink.provider_patient_id === "string" &&
        mdiProviderLink.provider_patient_id.trim().length > 0
        ? mdiProviderLink.provider_patient_id.trim()
        : null;

    if (!mdiPatientId) {
      return {
        context: null,
        error: errorResponse(
          "MDI_PATIENT_ID_MISSING",
          "No MDI patient id is linked to this patient",
          404,
        ),
      };
    }

    const mdiBackendUrl = getStringSetting(
      mdiTenantIntegration.settings,
      "backend_url",
    );
    if (!mdiBackendUrl) {
      return {
        context: null,
        error: errorResponse(
          "MDI_CONFIG_MISSING",
          "MDI integration is missing backend URL configuration",
          500,
        ),
      };
    }

    const authResult = await resolveMdiAccessToken({
      supabase: supabaseAdmin,
      tenantIntegrationId: mdiTenantIntegration.id,
      tenantId: mdiTenantIntegration.tenant_id,
      settings: mdiTenantIntegration.settings,
      baseUrl: mdiBackendUrl,
      requestId,
      source: "messenger-api",
    });

    if ("errorMessage" in authResult) {
      return {
        context: null,
        error: errorResponse(
          "MDI_CONFIG_MISSING",
          authResult.errorMessage,
          500,
        ),
      };
    }

    const mdiProviderName =
      extractProviderNameFromMetadata(mdiProviderLink.metadata) || "MDI";

    return {
      context: {
        providerLink: mdiProviderLink,
        tenantIntegration: mdiTenantIntegration,
        providerPatientId: mdiPatientId,
        providerName: mdiProviderName,
        backendUrl: mdiBackendUrl,
        accessToken: authResult.accessToken,
      } as MdiContext,
      error: null,
    };
  }

  function getMdiHeaders(mdiContext: MdiContext): Record<string, string> {
    return {
      Accept: "application/json",
      Authorization: `Bearer ${mdiContext.accessToken}`,
      "Content-Type": "application/json",
      Version: "2",
      "x-request-id": requestId,
      "x-source": "messenger-api",
    };
  }

  function getMdiMultipartHeaders(
    mdiContext: MdiContext,
  ): Record<string, string> {
    return {
      Accept: "application/json",
      Authorization: `Bearer ${mdiContext.accessToken}`,
      Version: "2",
      "x-request-id": requestId,
      "x-source": "messenger-api",
    };
  }

  function buildMdiPatientMessagesEndpoint(params: {
    mdiContext: MdiContext;
    messageId?: string;
    action?: "read";
  }): string {
    const { mdiContext, messageId, action } = params;
    return buildMdiPatientMessagesEndpointUrl({
      backendUrl: mdiContext.backendUrl,
      providerPatientId: mdiContext.providerPatientId,
      messageId,
      action,
    });
  }

  function buildMdiPartnerFilesEndpoint(mdiContext: MdiContext): string {
    return `${mdiContext.backendUrl.replace(/\/+$/, "")}/v1/partner/files`;
  }

  function buildMdiResponseBase(
    patient: AuthenticatedPatient,
    mdiContext: MdiContext,
  ) {
    return {
      provider_platform: {
        name: mdiContext.providerName,
        integration_key: mdiContext.tenantIntegration.integration_key,
      },
      ids: {
        patient_id: patient.id,
        tenant_id: patient.tenant_id,
        tenant_integration_id: mdiContext.providerLink.tenant_integration_id,
        provider_patient_id: mdiContext.providerPatientId,
      },
    };
  }

  function parseProviderChatAttachmentReferences(
    value: unknown,
  ): ParsedProviderChatAttachments {
    if (value === undefined || value === null) {
      return { attachments: [], error: null };
    }
    if (!Array.isArray(value)) {
      return {
        attachments: null,
        error: errorResponse(
          "INVALID_ATTACHMENTS",
          "attachments must be an array of file id references",
          400,
        ),
      };
    }

    const attachments: ProviderChatAttachmentReference[] = [];
    for (const entry of value) {
      const id = typeof entry === "string"
        ? entry.trim()
        : entry && typeof entry === "object" && !Array.isArray(entry)
        ? typeof (entry as JsonRecord).id === "string"
          ? ((entry as JsonRecord).id as string).trim()
          : typeof (entry as JsonRecord).file_id === "string"
          ? ((entry as JsonRecord).file_id as string).trim()
          : ""
        : "";

      if (!id) {
        return {
          attachments: null,
          error: errorResponse(
            "INVALID_ATTACHMENTS",
            "Each attachment must include a non-empty id",
            400,
          ),
        };
      }

      attachments.push({ id });
    }

    return { attachments, error: null };
  }

  async function uploadMdiPartnerFile(params: {
    mdiContext: MdiContext;
    file: File;
    name: string;
    fileType: string;
  }): Promise<
    | {
      file: {
        id: string;
        name: string;
        type: string;
        size: number;
        mime_type: string;
        raw: unknown;
      };
      error: null;
    }
    | { file: null; error: Response }
  > {
    const { mdiContext, file, name, fileType } = params;
    const endpoint = buildMdiPartnerFilesEndpoint(mdiContext);
    const formData = new FormData();
    formData.set("name", name);
    formData.set("file", file, file.name || name);
    formData.set("type", fileType);

    let mdiResponse: Response;
    try {
      mdiResponse = await fetch(endpoint, {
        method: "POST",
        headers: getMdiMultipartHeaders(mdiContext),
        body: formData,
      });
    } catch (error) {
      console.error("MDI provider chat file upload failed:", error);
      return {
        file: null,
        error: errorResponse(
          "MDI_REQUEST_FAILED",
          "Failed to reach MDI file upload API",
          502,
        ),
      };
    }

    const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);
    if (!mdiResponse.ok) {
      return {
        file: null,
        error: errorResponse(
          "MDI_API_ERROR",
          `MDI file upload request failed: ${
            extractErrorMessage(
              mdiResponseBody,
              `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
            )
          }`,
          502,
        ),
      };
    }

    const fileId = extractMdiUploadedFileId(mdiResponseBody);
    if (!fileId) {
      return {
        file: null,
        error: errorResponse(
          "MDI_FILE_UPLOAD_RESPONSE_INVALID",
          "MDI file upload response did not include a file id",
          502,
        ),
      };
    }

    return {
      file: {
        id: fileId,
        name,
        type: fileType,
        size: file.size,
        mime_type: file.type || "application/octet-stream",
        raw: mdiResponseBody,
      },
      error: null,
    };
  }

  function asSingle<T>(value: T | T[] | null | undefined): T | null {
    return Array.isArray(value) ? value[0] ?? null : value ?? null;
  }

  function isPlanFullyFinished(plan: ProviderChatPlan): boolean {
    const status = (plan.status || "").trim().toLowerCase();
    return Boolean(plan.cancelled_at) ||
      [
        "cancelled",
        "canceled",
        "complete",
        "completed",
        "expired",
        "finished",
        "inactive",
      ].includes(status);
  }

  function getProviderDisplayName(
    tenantIntegration: TenantIntegrationForChat | null,
    linkMetadata: Record<string, unknown> | null,
  ): string | null {
    const metadataProvider = extractProviderNameFromMetadata(linkMetadata);
    if (metadataProvider) return metadataProvider;

    if (isMdiProviderPlatform(tenantIntegration?.integration_key)) {
      return "MDI";
    }
    if (isTelegraProviderPlatform(tenantIntegration?.integration_key)) {
      return "TelegraMD";
    }

    return tenantIntegration?.integration_key || null;
  }

  function getProviderDisplayNameForIntegrationKey(
    integrationKey: string | null | undefined,
  ): string | null {
    if (isMdiProviderPlatform(integrationKey)) return "MDI";
    if (isTelegraProviderPlatform(integrationKey)) return "TelegraMD";
    return integrationKey || null;
  }

  function getProviderCapabilities(
    integrationKey: string | null | undefined,
  ): { attachments: boolean; read_receipts: boolean } {
    if (isMdiProviderPlatform(integrationKey)) {
      return { attachments: true, read_receipts: true };
    }
    if (isTelegraProviderPlatform(integrationKey)) {
      return { attachments: true, read_receipts: false };
    }

    return { attachments: false, read_receipts: false };
  }

  function isSupportedProviderChatIntegration(
    integrationKey: string | null | undefined,
  ): boolean {
    return isMdiProviderPlatform(integrationKey) ||
      isTelegraProviderPlatform(integrationKey);
  }

  function getOrderTimestamp(order: ProviderChatOrder): number {
    const parsedUpdatedAt = Date.parse(order.updated_at);
    const parsedCreatedAt = Date.parse(order.created_at);
    if (!Number.isNaN(parsedUpdatedAt)) return parsedUpdatedAt;
    if (!Number.isNaN(parsedCreatedAt)) return parsedCreatedAt;
    return 0;
  }

  function getPlanTitle(plan: ProviderChatPlan): string {
    const product = asSingle(plan.product);
    return product?.name || `Plan ${plan.id.slice(0, 8)}`;
  }

  function getOrderStatus(order: ProviderChatOrder) {
    return asSingle(order.order_statuses);
  }

  function getOrderStatusLabel(
    status: ReturnType<typeof getOrderStatus>,
  ): string | null {
    if (!status) return null;
    return status.patient_status_label || status.admin_status_label ||
      status.status_key || null;
  }

  function getOrderProduct(
    order: ProviderChatOrder,
    plan: ProviderChatPlan,
  ): { id: string | null; name: string | null } | null {
    const orderProduct = asSingle(order.product);
    if (orderProduct) {
      return {
        id: orderProduct.id || order.product_id || null,
        name: orderProduct.name || null,
      };
    }

    const planProduct = asSingle(plan.product);
    if (planProduct) {
      return {
        id: planProduct.id || null,
        name: planProduct.name || null,
      };
    }

    return order.product_id ? { id: order.product_id, name: null } : null;
  }

  function buildSelectedOrderContext(params: {
    order: ProviderChatOrder;
    plan: ProviderChatPlan;
    provider: ProviderChatProviderInfo | null;
    providerOrderId: string | null;
    providerPatientId?: string | null;
  }): ProviderChatSelectedOrder {
    const { order, plan, provider, providerOrderId, providerPatientId } =
      params;
    const status = getOrderStatus(order);
    const product = getOrderProduct(order, plan);

    return {
      id: order.id,
      order_number: order.order_number || null,
      status: status
        ? {
          key: status.status_key || null,
          label: getOrderStatusLabel(status),
          is_terminal: status.is_terminal ?? null,
        }
        : null,
      provider_id: provider?.id || null,
      provider_order_id: providerOrderId,
      provider_chat_id: providerPatientId || provider?.chat_id || null,
      provider: provider
        ? {
          id: provider.id,
          integration_key: provider.integration_key,
          name: provider.name,
        }
        : null,
      medication_or_product_name: product?.name || getPlanTitle(plan),
      product,
      created_at: order.created_at,
      updated_at: order.updated_at || null,
    };
  }

  function getContextOrderTimestamp(
    context: ProviderChatContextResult,
  ): number {
    const updatedAt = context.selected_order?.updated_at || "";
    const createdAt = context.selected_order?.created_at || "";
    const parsedUpdatedAt = Date.parse(updatedAt);
    const parsedCreatedAt = Date.parse(createdAt);
    if (!Number.isNaN(parsedUpdatedAt)) return parsedUpdatedAt;
    if (!Number.isNaN(parsedCreatedAt)) return parsedCreatedAt;
    return 0;
  }

  function buildEligibleProviderChatOrders(
    contexts: ProviderChatContextResult[],
  ) {
    return contexts
      .filter((context) => context.chat_available && context.selected_order)
      .map((context) => ({
        chat_context_id: context.chat_context_id,
        plan_id: context.plan_id,
        plan_title: context.plan_title,
        plan_status: context.plan_status,
        order: context.selected_order,
        order_id: context.selected_order?.id || null,
        order_number: context.selected_order?.order_number || null,
        order_status: context.selected_order?.status || null,
        provider_id: context.selected_order?.provider_id || null,
        provider_order_id: context.provider_order_id,
        provider_chat_id: context.selected_order?.provider_chat_id || null,
        provider: context.provider,
        medication_or_product_name:
          context.selected_order?.medication_or_product_name || null,
        product: context.selected_order?.product || null,
        created_at: context.selected_order?.created_at || null,
        updated_at: context.selected_order?.updated_at || null,
        capabilities: context.capabilities,
        summary: context.summary,
      }));
  }

  async function getProviderChatContexts(
    patient: AuthenticatedPatient,
  ): Promise<
    | { contexts: ProviderChatContextResult[]; error: null }
    | { contexts: null; error: Response }
  > {
    const { data: plans, error: plansError } = await supabaseAdmin
      .from("subscriptions")
      .select(`
        id,
        status,
        cancelled_at,
        created_at,
        updated_at,
        product:products (
          id,
          name
        )
      `)
      .eq("patient_id", patient.id)
      .eq("tenant_id", patient.tenant_id)
      .order("created_at", { ascending: false });

    if (plansError) {
      console.error("Provider chat plans fetch error:", plansError);
      return {
        contexts: null,
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch provider chat plans",
          500,
        ),
      };
    }

    const activePlans = ((plans || []) as ProviderChatPlan[]).filter((plan) =>
      !isPlanFullyFinished(plan)
    );
    const planIds = activePlans.map((plan) => plan.id);

    if (planIds.length === 0) {
      return { contexts: [], error: null };
    }

    const { data: orders, error: ordersError } = await supabaseAdmin
      .from("orders")
      .select(`
        id,
        subscription_id,
        order_number,
        product_id,
        provider_platform_integration_key,
        provider_platform_order_id,
        created_at,
        updated_at,
        order_statuses (
          status_key,
          is_terminal,
          display_order,
          patient_status_label,
          admin_status_label
        ),
        product:products (
          id,
          name
        )
      `)
      .eq("patient_id", patient.id)
      .eq("tenant_id", patient.tenant_id)
      .in("subscription_id", planIds)
      .order("created_at", { ascending: false });

    if (ordersError) {
      console.error("Provider chat orders fetch error:", ordersError);
      return {
        contexts: null,
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch provider chat orders",
          500,
        ),
      };
    }

    const typedOrders = (orders || []) as ProviderChatOrder[];
    const orderIds = typedOrders.map((order) => order.id);
    const orderLinksByOrderId = new Map<string, ProviderChatOrderLink[]>();

    if (orderIds.length > 0) {
      const { data: orderLinks, error: orderLinksError } = await supabaseAdmin
        .from("order_provider_platform_links")
        .select(`
          id,
          tenant_id,
          order_id,
          tenant_integration_id,
          provider_order_id,
          metadata,
          created_at,
          updated_at,
          tenant_integrations!inner (
            id,
            tenant_id,
            integration_key,
            is_enabled,
            settings
          )
        `)
        .eq("tenant_id", patient.tenant_id)
        .in("order_id", orderIds)
        .order("updated_at", { ascending: false });

      if (orderLinksError) {
        console.error(
          "Provider chat order links fetch error:",
          orderLinksError,
        );
        return {
          contexts: null,
          error: errorResponse(
            "FETCH_ERROR",
            "Failed to fetch provider chat order links",
            500,
          ),
        };
      }

      for (const link of (orderLinks || []) as ProviderChatOrderLink[]) {
        const links = orderLinksByOrderId.get(link.order_id) || [];
        links.push(link);
        orderLinksByOrderId.set(link.order_id, links);
      }
    }

    const { data: patientLinks, error: patientLinksError } = await supabaseAdmin
      .from("patient_provider_platform_links")
      .select(`
          id,
          tenant_id,
          patient_id,
          tenant_integration_id,
          provider_patient_id,
          metadata,
          tenant_integrations!inner (
            id,
            tenant_id,
            integration_key,
            is_enabled,
            settings
          )
        `)
      .eq("patient_id", patient.id)
      .eq("tenant_id", patient.tenant_id);

    if (patientLinksError) {
      console.error(
        "Provider chat patient links fetch error:",
        patientLinksError,
      );
      return {
        contexts: null,
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch provider chat patient links",
          500,
        ),
      };
    }

    const patientLinksByTenantIntegrationId = new Map<
      string,
      PatientProviderPlatformLinkForChat
    >();
    for (
      const link of (patientLinks || []) as PatientProviderPlatformLinkForChat[]
    ) {
      patientLinksByTenantIntegrationId.set(link.tenant_integration_id, link);
    }

    const ordersByPlanId = new Map<string, ProviderChatOrder[]>();
    for (const order of typedOrders) {
      if (!order.subscription_id) continue;
      const planOrders = ordersByPlanId.get(order.subscription_id) || [];
      planOrders.push(order);
      ordersByPlanId.set(order.subscription_id, planOrders);
    }

    const emptySummary: ProviderChatContextSummary = {
      unread_count: 0,
      last_message_at: null,
      last_message_preview: null,
    };

    const contexts = activePlans.map((plan): ProviderChatContextResult => {
      const planOrders = (ordersByPlanId.get(plan.id) || [])
        .sort((a, b) => getOrderTimestamp(b) - getOrderTimestamp(a));
      const baseContext = {
        plan_id: plan.id,
        plan_title: getPlanTitle(plan),
        plan_status: plan.status,
        is_fully_finished: false,
        summary: emptySummary,
      };

      if (planOrders.length === 0) {
        return {
          ...baseContext,
          chat_context_id: null,
          chat_available: false,
          unavailable_reason: "order_missing",
          selected_order_id: null,
          provider_order_id: null,
          provider: null,
          selected_order: null,
          capabilities: { attachments: false, read_receipts: false },
        };
      }

      let fallbackContext: ProviderChatContextResult | null = null;

      for (const order of planOrders) {
        const orderLinks = orderLinksByOrderId.get(order.id) || [];
        if (orderLinks.length === 0) {
          const providerOrderId = order.provider_platform_order_id || null;
          const provider: ProviderChatProviderInfo | null =
            order.provider_platform_integration_key
              ? {
                id: null,
                integration_key: order.provider_platform_integration_key,
                name: getProviderDisplayNameForIntegrationKey(
                  order.provider_platform_integration_key,
                ),
                chat_id: null,
              }
              : null;
          fallbackContext ??= {
            ...baseContext,
            chat_context_id: null,
            chat_available: false,
            unavailable_reason: "provider_order_missing",
            selected_order_id: order.id,
            provider_order_id: providerOrderId,
            provider,
            selected_order: buildSelectedOrderContext({
              order,
              plan,
              provider,
              providerOrderId,
            }),
            capabilities: getProviderCapabilities(
              order.provider_platform_integration_key,
            ),
          };
          continue;
        }

        for (const orderLink of orderLinks) {
          const tenantIntegration = resolveTenantIntegration(
            orderLink as unknown as PatientProviderPlatformLinkForChat,
          );
          const integrationKey = tenantIntegration?.integration_key || null;
          const providerOrderId = orderLink.provider_order_id ||
            order.provider_platform_order_id ||
            null;
          const provider: ProviderChatProviderInfo = {
            id: orderLink.tenant_integration_id,
            integration_key: integrationKey,
            name: getProviderDisplayName(
              tenantIntegration,
              orderLink.metadata,
            ),
            chat_id: null,
          };
          const capabilities = getProviderCapabilities(integrationKey);

          if (!tenantIntegration?.is_enabled) {
            fallbackContext ??= {
              ...baseContext,
              chat_context_id: null,
              chat_available: false,
              unavailable_reason: "provider_integration_disabled",
              selected_order_id: order.id,
              provider_order_id: providerOrderId,
              provider,
              selected_order: buildSelectedOrderContext({
                order,
                plan,
                provider,
                providerOrderId,
              }),
              capabilities,
            };
            continue;
          }

          if (!isSupportedProviderChatIntegration(integrationKey)) {
            fallbackContext ??= {
              ...baseContext,
              chat_context_id: null,
              chat_available: false,
              unavailable_reason: "provider_chat_unsupported",
              selected_order_id: order.id,
              provider_order_id: providerOrderId,
              provider,
              selected_order: buildSelectedOrderContext({
                order,
                plan,
                provider,
                providerOrderId,
              }),
              capabilities,
            };
            continue;
          }

          if (!providerOrderId) {
            fallbackContext ??= {
              ...baseContext,
              chat_context_id: null,
              chat_available: false,
              unavailable_reason: "provider_order_missing",
              selected_order_id: order.id,
              provider_order_id: null,
              provider,
              selected_order: buildSelectedOrderContext({
                order,
                plan,
                provider,
                providerOrderId: null,
              }),
              capabilities,
            };
            continue;
          }

          const patientProviderLink = patientLinksByTenantIntegrationId.get(
            orderLink.tenant_integration_id,
          );
          const providerPatientId =
            typeof patientProviderLink?.provider_patient_id === "string"
              ? patientProviderLink.provider_patient_id.trim()
              : "";

          if (!providerPatientId) {
            fallbackContext ??= {
              ...baseContext,
              chat_context_id: null,
              chat_available: false,
              unavailable_reason: "provider_patient_missing",
              selected_order_id: order.id,
              provider_order_id: providerOrderId,
              provider,
              selected_order: buildSelectedOrderContext({
                order,
                plan,
                provider,
                providerOrderId,
              }),
              capabilities,
            };
            continue;
          }

          const token = {
            version: 1 as const,
            plan_id: plan.id,
            order_id: order.id,
            tenant_integration_id: orderLink.tenant_integration_id,
          };
          const providerWithChat: ProviderChatProviderInfo = {
            ...provider,
            chat_id: providerPatientId,
          };

          return {
            ...baseContext,
            chat_context_id: encodeProviderChatContextId(token),
            chat_available: true,
            unavailable_reason: null,
            selected_order_id: order.id,
            provider_order_id: providerOrderId,
            provider: providerWithChat,
            selected_order: buildSelectedOrderContext({
              order,
              plan,
              provider: providerWithChat,
              providerOrderId,
              providerPatientId,
            }),
            capabilities,
          };
        }
      }

      const fallbackOrder = planOrders[0] || null;
      const fallbackProviderOrderId =
        fallbackOrder?.provider_platform_order_id ||
        null;
      return fallbackContext || {
        ...baseContext,
        chat_context_id: null,
        chat_available: false,
        unavailable_reason: "provider_order_missing",
        selected_order_id: fallbackOrder?.id || null,
        provider_order_id: fallbackProviderOrderId,
        provider: null,
        selected_order: fallbackOrder
          ? buildSelectedOrderContext({
            order: fallbackOrder,
            plan,
            provider: null,
            providerOrderId: fallbackProviderOrderId,
          })
          : null,
        capabilities: { attachments: false, read_receipts: false },
      };
    }).sort((a, b) =>
      getContextOrderTimestamp(b) - getContextOrderTimestamp(a)
    );

    return { contexts, error: null };
  }

  async function resolveProviderChatContext(
    patient: AuthenticatedPatient,
    chatContextId: string,
  ): Promise<
    | { resolved: ResolvedProviderChatContext; error: null }
    | { resolved: null; error: Response }
  > {
    const token = decodeProviderChatContextId(chatContextId);
    if (!token) {
      return {
        resolved: null,
        error: errorResponse(
          "INVALID_CHAT_CONTEXT",
          "Invalid provider chat context id",
          400,
        ),
      };
    }

    const { contexts, error } = await getProviderChatContexts(patient);
    if (!contexts || error) {
      return { resolved: null, error };
    }

    const matchingContext = contexts.find((context) =>
      context.chat_context_id === chatContextId &&
      context.chat_available === true
    );

    if (!matchingContext) {
      return {
        resolved: null,
        error: errorResponse(
          "PROVIDER_CHAT_UNAVAILABLE",
          "Provider chat is not available for this plan",
          404,
        ),
      };
    }

    return {
      resolved: {
        context: matchingContext,
        token,
      },
      error: null,
    };
  }

  try {
    if (req.method === "GET" && path === "/provider-chat/contexts") {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { contexts, error } = await getProviderChatContexts(patient);
      if (!contexts || error) {
        return error;
      }
      const eligibleOrders = buildEligibleProviderChatOrders(contexts);

      return jsonResponse({
        data: {
          available: eligibleOrders.length > 0,
          total_eligible_orders: eligibleOrders.length,
          eligible_orders: eligibleOrders,
          total_contexts: contexts.length,
          contexts,
        },
      });
    }

    const providerChatThreadMatch = path.match(
      /^\/provider-chat\/([^/]+)\/thread$/,
    );
    const providerChatStatusMatch = path.match(
      /^\/provider-chat\/([^/]+)\/status$/,
    );

    if (
      req.method === "GET" &&
      (providerChatThreadMatch || providerChatStatusMatch)
    ) {
      const chatContextId = decodeURIComponent(
        (providerChatThreadMatch?.[1] || providerChatStatusMatch?.[1] || "")
          .trim(),
      );
      const chatType = url.searchParams.get("chatType")?.trim()
        .toLowerCase() || "clinical";
      if (chatType !== "clinical" && chatType !== "support") {
        return errorResponse(
          "INVALID_CHAT_TYPE",
          "chatType query parameter must be either 'clinical' or 'support'",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { resolved, error: contextError } =
        await resolveProviderChatContext(patient, chatContextId);
      if (!resolved || contextError) {
        return contextError;
      }

      const integrationKey = resolved.context.provider?.integration_key;
      const isStatusRequest = Boolean(providerChatStatusMatch);

      if (isMdiProviderPlatform(integrationKey)) {
        const { context: mdiContext, error: mdiContextError } =
          await getMdiContext(patient, {
            tenantIntegrationId: resolved.token.tenant_integration_id,
          });
        if (!mdiContext || mdiContextError) {
          return mdiContextError;
        }

        const mdiEndpoint = buildMdiPatientMessagesEndpoint({ mdiContext });

        let mdiResponse: Response;
        try {
          mdiResponse = await fetch(mdiEndpoint, {
            method: "GET",
            headers: getMdiHeaders(mdiContext),
          });
        } catch (error) {
          console.error("MDI provider chat request failed:", error);
          return errorResponse(
            "MDI_REQUEST_FAILED",
            "Failed to reach MDI messages API",
            502,
          );
        }

        const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);

        if (!mdiResponse.ok) {
          return errorResponse(
            "MDI_API_ERROR",
            `MDI messages request failed: ${
              extractErrorMessage(
                mdiResponseBody,
                `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
              )
            }`,
            502,
          );
        }

        const messages = normalizeMdiMessages(mdiResponseBody);
        const summary = summarizeMdiMessages(messages);
        const responseBase = buildMdiResponseBase(patient, mdiContext);
        const data = {
          ...responseBase,
          chat_context: resolved.context,
          summary,
          ...(isStatusRequest ? {} : {
            pagination: extractMdiPagination(mdiResponseBody),
            messages,
          }),
        };

        return jsonResponse({ data });
      }

      if (isTelegraProviderPlatform(integrationKey)) {
        const { context: telegraContext, error: telegraContextError } =
          await getTelegraContext(patient, {
            tenantIntegrationId: resolved.token.tenant_integration_id,
          });
        if (!telegraContext || telegraContextError) {
          return telegraContextError;
        }

        const telegraEndpoint = `${
          telegraContext.baseUrl.replace(/\/+$/, "")
        }/patientConversations/getByPatient/${
          encodeURIComponent(telegraContext.providerPatientId)
        }?channelType=${encodeURIComponent(chatType)}`;
        const telegraRequestEndpoint = appendTelegraRequestTimestamp(
          telegraEndpoint,
        );

        let telegraResponse: Response;
        try {
          telegraResponse = await fetch(telegraRequestEndpoint, {
            method: "GET",
            headers: {
              Accept: "application/json",
              Authorization: `Bearer ${telegraContext.accessToken}`,
              "Content-Type": "application/json",
            },
          });
        } catch (error) {
          console.error("Telegra provider chat request failed:", error);
          return errorResponse(
            "TELEGRA_REQUEST_FAILED",
            "Failed to reach Telegra chat API",
            502,
          );
        }

        const telegraResponseBody = await parseJsonOrTextResponse(
          telegraResponse,
        );

        if (!telegraResponse.ok) {
          return errorResponse(
            "TELEGRA_API_ERROR",
            `Telegra chat threads request failed: ${
              extractErrorMessage(
                telegraResponseBody,
                `${telegraResponse.status} ${telegraResponse.statusText}`
                  .trim(),
              )
            }`,
            502,
          );
        }

        const normalizedChats = includeParticipantIdentifierInChats(
          extractTelegraChatThreads(telegraResponseBody),
          extractTelegraParticipantIdentifier(telegraResponseBody),
        );
        const chatThreads = chatType === "clinical"
          ? filterTelegraSystemMessagesInChats(normalizedChats)
          : normalizedChats;
        const firstChat = chatThreads.find((chat) =>
          !!chat && typeof chat === "object" && !Array.isArray(chat)
        ) as JsonRecord | undefined;
        const unreadCountValue = firstChat?.unread_count ??
          firstChat?.unreadCount ??
          firstChat?.unread_message_count ?? 0;
        const unreadCount = typeof unreadCountValue === "number"
          ? unreadCountValue
          : typeof unreadCountValue === "string"
          ? Number(unreadCountValue) || 0
          : 0;
        const lastMessage = firstChat?.last_message &&
            typeof firstChat.last_message === "object" &&
            !Array.isArray(firstChat.last_message)
          ? firstChat.last_message as JsonRecord
          : null;
        const summary = {
          total_chats: chatThreads.length,
          unread_count: unreadCount,
          latest_message_at: typeof firstChat?.last_message_at === "string"
            ? firstChat.last_message_at
            : typeof firstChat?.lastMessageAt === "string"
            ? firstChat.lastMessageAt
            : typeof lastMessage?.created_at === "string"
            ? lastMessage.created_at
            : null,
          last_message_preview:
            typeof firstChat?.last_message_preview === "string"
              ? firstChat.last_message_preview
              : typeof firstChat?.lastMessagePreview === "string"
              ? firstChat.lastMessagePreview
              : typeof lastMessage?.message === "string"
              ? lastMessage.message
              : typeof lastMessage?.text === "string"
              ? lastMessage.text
              : null,
        };

        const data = {
          provider_platform: {
            name: telegraContext.providerName,
            integration_key: telegraContext.tenantIntegration.integration_key,
          },
          ids: {
            patient_id: patient.id,
            tenant_id: patient.tenant_id,
            tenant_integration_id:
              telegraContext.providerLink.tenant_integration_id,
            provider_patient_id: telegraContext.providerPatientId,
          },
          chat_context: resolved.context,
          chat_type: chatType,
          total_chats: chatThreads.length,
          summary,
          ...(isStatusRequest ? {} : { chats: chatThreads }),
        };

        return jsonResponse({ data });
      }

      return errorResponse(
        "PROVIDER_CHAT_UNSUPPORTED",
        "Provider chat is not supported for this provider",
        404,
      );
    }

    const providerChatReadMatch = path.match(
      /^\/provider-chat\/([^/]+)\/messages\/([^/]+)\/read$/,
    );
    if (req.method === "POST" && providerChatReadMatch) {
      const chatContextId = decodeURIComponent(
        (providerChatReadMatch[1] || "").trim(),
      );
      const messageId = decodeURIComponent(
        (providerChatReadMatch[2] || "").trim(),
      );
      if (!messageId) {
        return errorResponse(
          "MISSING_FIELDS",
          "message id is required",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { resolved, error: contextError } =
        await resolveProviderChatContext(patient, chatContextId);
      if (!resolved || contextError) {
        return contextError;
      }

      if (!isMdiProviderPlatform(resolved.context.provider?.integration_key)) {
        return errorResponse(
          "READ_RECEIPTS_NOT_SUPPORTED",
          "Read receipts are not supported for this provider chat",
          400,
        );
      }

      const { context: mdiContext, error: mdiContextError } =
        await getMdiContext(patient, {
          tenantIntegrationId: resolved.token.tenant_integration_id,
        });
      if (!mdiContext || mdiContextError) {
        return mdiContextError;
      }

      const mdiEndpoint = buildMdiPatientMessagesEndpoint({
        mdiContext,
        messageId,
        action: "read",
      });

      let mdiResponse: Response;
      try {
        mdiResponse = await fetch(mdiEndpoint, {
          method: "POST",
          headers: getMdiHeaders(mdiContext),
        });
      } catch (error) {
        console.error("MDI provider chat mark read failed:", error);
        return errorResponse(
          "MDI_REQUEST_FAILED",
          "Failed to reach MDI messages API",
          502,
        );
      }

      const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);
      if (!mdiResponse.ok) {
        return errorResponse(
          "MDI_API_ERROR",
          `MDI mark message read request failed: ${
            extractErrorMessage(
              mdiResponseBody,
              `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      return jsonResponse({
        message: "Message marked as read",
        data: {
          ...buildMdiResponseBase(patient, mdiContext),
          chat_context: resolved.context,
          message: normalizeMdiSingleMessage(mdiResponseBody),
        },
      });
    }

    const providerChatTelegraFileMatch = path.match(
      /^\/provider-chat\/([^/]+)\/files\/telegra$/,
    );
    if (req.method === "GET" && providerChatTelegraFileMatch) {
      const chatContextId = decodeURIComponent(
        (providerChatTelegraFileMatch[1] || "").trim(),
      );
      const fileUrl = (url.searchParams.get("url") ||
        url.searchParams.get("fileUrl") || "").trim();
      if (!fileUrl) {
        return errorResponse(
          "MISSING_FIELDS",
          "url query parameter is required",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { resolved, error: contextError } =
        await resolveProviderChatContext(patient, chatContextId);
      if (!resolved || contextError) {
        return contextError;
      }

      if (
        !isTelegraProviderPlatform(resolved.context.provider?.integration_key)
      ) {
        return errorResponse(
          "FILE_DOWNLOAD_NOT_SUPPORTED",
          "File downloads are not supported for this provider chat",
          400,
        );
      }

      const { context: telegraContext, error: telegraContextError } =
        await getTelegraContext(patient, {
          tenantIntegrationId: resolved.token.tenant_integration_id,
        });
      if (!telegraContext || telegraContextError) {
        return telegraContextError;
      }

      const telegraEndpoint = `${
        telegraContext.baseUrl.replace(/\/+$/, "")
      }/patientConversations/actions/getFile?url=${
        encodeURIComponent(fileUrl)
      }`;
      const telegraRequestEndpoint = appendTelegraRequestTimestamp(
        telegraEndpoint,
      );

      let telegraResponse: Response;
      try {
        telegraResponse = await fetch(telegraRequestEndpoint, {
          method: "GET",
          headers: {
            Accept: "*/*",
            Authorization: `Bearer ${telegraContext.accessToken}`,
          },
        });
      } catch (error) {
        console.error("Telegra provider chat file download failed:", error);
        return errorResponse(
          "TELEGRA_REQUEST_FAILED",
          "Failed to reach Telegra file API",
          502,
        );
      }

      if (!telegraResponse.ok) {
        const telegraResponseBody = await parseJsonOrTextResponse(
          telegraResponse,
        );
        return errorResponse(
          "TELEGRA_API_ERROR",
          `Telegra file request failed: ${
            extractErrorMessage(
              telegraResponseBody,
              `${telegraResponse.status} ${telegraResponse.statusText}`
                .trim(),
            )
          }`,
          502,
        );
      }

      const headers = new Headers(corsHeaders);
      headers.set(
        "Content-Type",
        telegraResponse.headers.get("Content-Type") ||
          "application/octet-stream",
      );
      const contentLength = telegraResponse.headers.get("Content-Length");
      if (contentLength) headers.set("Content-Length", contentLength);
      const contentDisposition = telegraResponse.headers.get(
        "Content-Disposition",
      );
      if (contentDisposition) {
        headers.set("Content-Disposition", contentDisposition);
      }
      headers.set("Cache-Control", "private, max-age=60");

      return new Response(telegraResponse.body, {
        status: telegraResponse.status,
        headers,
      });
    }

    const providerChatFileUploadMatch = path.match(
      /^\/provider-chat\/([^/]+)\/files$/,
    );
    if (req.method === "POST" && providerChatFileUploadMatch) {
      const chatContextId = decodeURIComponent(
        (providerChatFileUploadMatch[1] || "").trim(),
      );

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { resolved, error: contextError } =
        await resolveProviderChatContext(patient, chatContextId);
      if (!resolved || contextError) {
        return contextError;
      }

      if (!isMdiProviderPlatform(resolved.context.provider?.integration_key)) {
        return errorResponse(
          "ATTACHMENTS_NOT_SUPPORTED",
          "Attachments are not supported for this provider chat",
          400,
        );
      }

      let formData: FormData;
      try {
        formData = await req.formData();
      } catch {
        return errorResponse(
          "INVALID_MULTIPART",
          "File upload must use multipart/form-data",
          400,
        );
      }

      const fileEntry = formData.get("file");
      if (!(fileEntry instanceof File) || fileEntry.size <= 0) {
        return errorResponse(
          "MISSING_FIELDS",
          "file is required",
          400,
        );
      }

      const fileTypeEntry = formData.get("type");
      if (!isMdiPartnerFileType(fileTypeEntry)) {
        return errorResponse(
          "INVALID_FILE_TYPE",
          "type must be a supported MDI file type",
          400,
        );
      }

      const fileSizeLimit = getMdiPartnerFileSizeLimitBytes(
        fileEntry.type,
        fileTypeEntry,
      );
      if (fileEntry.size > fileSizeLimit) {
        return errorResponse(
          "FILE_TOO_LARGE",
          "File exceeds the provider size limit for this file type",
          400,
        );
      }

      const nameEntry = formData.get("name");
      const fileName = typeof nameEntry === "string" && nameEntry.trim()
        ? nameEntry.trim()
        : fileEntry.name || "upload";

      const { context: mdiContext, error: mdiContextError } =
        await getMdiContext(patient, {
          tenantIntegrationId: resolved.token.tenant_integration_id,
        });
      if (!mdiContext || mdiContextError) {
        return mdiContextError;
      }

      const { file, error: uploadError } = await uploadMdiPartnerFile({
        mdiContext,
        file: fileEntry,
        name: fileName,
        fileType: fileTypeEntry.trim(),
      });
      if (!file || uploadError) {
        return uploadError;
      }

      return jsonResponse({
        message: "File uploaded successfully",
        data: {
          ...buildMdiResponseBase(patient, mdiContext),
          chat_context: resolved.context,
          file,
        },
      });
    }

    const providerChatMessagesMatch = path.match(
      /^\/provider-chat\/([^/]+)\/messages$/,
    );
    if (req.method === "POST" && providerChatMessagesMatch) {
      const chatContextId = decodeURIComponent(
        (providerChatMessagesMatch[1] || "").trim(),
      );
      let body: {
        conversationID?: string;
        conversationId?: string;
        message?: string;
        text?: string;
        channelType?: string;
        reference_message_id?: string;
        referenceMessageId?: string;
        attachments?: unknown[];
        files?: unknown[];
        file?: unknown;
        attachment?: unknown;
      };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const messageValue = typeof body.message === "string"
        ? body.message
        : typeof body.text === "string"
        ? body.text
        : "";
      const message = messageValue.trim();

      const attachmentsValue = body.attachments !== undefined
        ? body.attachments
        : body.files;
      const { attachments, error: attachmentsError } =
        parseProviderChatAttachmentReferences(attachmentsValue);
      if (!attachments || attachmentsError) {
        return attachmentsError;
      }
      const { file: telegraFile, error: telegraFileError } =
        parseTelegraChatFilePayload(body.file ?? body.attachment);
      if (telegraFileError) {
        return errorResponse(
          telegraFileError.code,
          telegraFileError.message,
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { resolved, error: contextError } =
        await resolveProviderChatContext(patient, chatContextId);
      if (!resolved || contextError) {
        return contextError;
      }

      const integrationKey = resolved.context.provider?.integration_key;
      if (isMdiProviderPlatform(integrationKey)) {
        if (!message && attachments.length === 0) {
          return errorResponse(
            "MISSING_FIELDS",
            "message or attachments are required",
            400,
          );
        }
        const referenceMessageIdValue =
          typeof body.reference_message_id === "string"
            ? body.reference_message_id
            : typeof body.referenceMessageId === "string"
            ? body.referenceMessageId
            : "";
        const referenceMessageId = referenceMessageIdValue.trim();
        const { context: mdiContext, error: mdiContextError } =
          await getMdiContext(patient, {
            tenantIntegrationId: resolved.token.tenant_integration_id,
          });
        if (!mdiContext || mdiContextError) {
          return mdiContextError;
        }

        const mdiEndpoint = buildMdiPatientMessagesEndpoint({ mdiContext });
        const mdiPayload: Record<string, unknown> = {
          channel: "patient",
          sender_type: "patient",
        };
        if (message) {
          mdiPayload.text = message;
        }
        if (referenceMessageId) {
          mdiPayload.reference_message_id = referenceMessageId;
        }
        if (attachments.length > 0) {
          mdiPayload.files = attachments;
        }

        let mdiResponse: Response;
        try {
          mdiResponse = await fetch(mdiEndpoint, {
            method: "POST",
            headers: getMdiHeaders(mdiContext),
            body: JSON.stringify(mdiPayload),
          });
        } catch (error) {
          console.error("MDI provider chat send failed:", error);
          return errorResponse(
            "MDI_REQUEST_FAILED",
            "Failed to reach MDI messages API",
            502,
          );
        }

        const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);
        if (!mdiResponse.ok) {
          return errorResponse(
            "MDI_API_ERROR",
            `MDI send message request failed: ${
              extractErrorMessage(
                mdiResponseBody,
                `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
              )
            }`,
            502,
          );
        }

        return jsonResponse({
          message: "Message sent successfully",
          data: {
            ...buildMdiResponseBase(patient, mdiContext),
            chat_context: resolved.context,
            message: normalizeMdiSingleMessage(mdiResponseBody),
          },
        });
      }

      if (isTelegraProviderPlatform(integrationKey)) {
        if (attachments.length > 0) {
          return errorResponse(
            "ATTACHMENTS_NOT_SUPPORTED",
            "Use the file object when sending Telegra provider chat attachments",
            400,
          );
        }
        if (!message && !telegraFile) {
          return errorResponse(
            "MISSING_FIELDS",
            "message or file is required",
            400,
          );
        }
        if (message && telegraFile) {
          return errorResponse(
            "INVALID_MESSAGE_FILE_COMBINATION",
            "Send either message or file for Telegra provider chat, not both",
            400,
          );
        }
        const conversationIdValue = typeof body.conversationID === "string"
          ? body.conversationID
          : typeof body.conversationId === "string"
          ? body.conversationId
          : "";
        const conversationId = conversationIdValue.trim();
        const channelType = typeof body.channelType === "string"
          ? body.channelType.trim().toLowerCase()
          : "clinical";
        if (!conversationId) {
          return errorResponse(
            "MISSING_FIELDS",
            "conversationID is required for Telegra provider chat",
            400,
          );
        }
        if (channelType !== "clinical" && channelType !== "support") {
          return errorResponse(
            "INVALID_CHAT_TYPE",
            "channelType must be either 'clinical' or 'support'",
            400,
          );
        }

        const { context: telegraContext, error: telegraContextError } =
          await getTelegraContext(patient, {
            tenantIntegrationId: resolved.token.tenant_integration_id,
          });
        if (!telegraContext || telegraContextError) {
          return telegraContextError;
        }

        const telegraEndpoint = `${
          telegraContext.baseUrl.replace(/\/+$/, "")
        }/patientConversations/${
          encodeURIComponent(conversationId)
        }/sendMessage`;
        const telegraRequestEndpoint = appendTelegraRequestTimestamp(
          telegraEndpoint,
        );

        let telegraResponse: Response;
        try {
          const telegraPayload: Record<string, unknown> = {
            sender: "patient",
            channelType,
          };
          if (telegraFile) {
            telegraPayload.file = {
              name: telegraFile.name,
              ...(telegraFile.ext ? { ext: telegraFile.ext } : {}),
              base64Data: telegraFile.base64Data,
            };
          } else {
            telegraPayload.message = message;
          }

          telegraResponse = await fetch(telegraRequestEndpoint, {
            method: "POST",
            headers: {
              Accept: "application/json",
              Authorization: `Bearer ${telegraContext.accessToken}`,
              "Content-Type": "application/json",
            },
            body: JSON.stringify(telegraPayload),
          });
        } catch (error) {
          console.error("Telegra provider chat send failed:", error);
          return errorResponse(
            "TELEGRA_REQUEST_FAILED",
            "Failed to reach Telegra chat API",
            502,
          );
        }

        const telegraResponseBody = await parseJsonOrTextResponse(
          telegraResponse,
        );
        if (!telegraResponse.ok) {
          return errorResponse(
            "TELEGRA_API_ERROR",
            `Telegra send message request failed: ${
              extractErrorMessage(
                telegraResponseBody,
                `${telegraResponse.status} ${telegraResponse.statusText}`
                  .trim(),
              )
            }`,
            502,
          );
        }

        return jsonResponse({
          message: "Message sent successfully",
          data: {
            provider_platform: {
              name: telegraContext.providerName,
              integration_key: telegraContext.tenantIntegration.integration_key,
            },
            ids: {
              patient_id: patient.id,
              tenant_id: patient.tenant_id,
              tenant_integration_id:
                telegraContext.providerLink.tenant_integration_id,
              provider_patient_id: telegraContext.providerPatientId,
            },
            chat_context: resolved.context,
            conversation_id: conversationId,
            channel_type: channelType,
            sent_file: telegraFile
              ? {
                name: telegraFile.name,
                ext: telegraFile.ext ?? null,
                size_bytes: telegraFile.size_bytes,
              }
              : null,
            telegra_response: telegraResponseBody,
          },
        });
      }

      return errorResponse(
        "PROVIDER_CHAT_UNSUPPORTED",
        "Provider chat is not supported for this provider",
        404,
      );
    }

    if (req.method === "GET" && path === "/telegra-clinical-chat") {
      const chatType = url.searchParams.get("chatType")?.trim().toLowerCase() ||
        "";
      if (chatType !== "clinical" && chatType !== "support") {
        return errorResponse(
          "INVALID_CHAT_TYPE",
          "chatType query parameter must be either 'clinical' or 'support'",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { context: telegraContext, error: telegraContextError } =
        await getTelegraContext(patient);
      if (!telegraContext || telegraContextError) {
        return telegraContextError;
      }

      const telegraEndpoint = `${
        telegraContext.baseUrl.replace(/\/+$/, "")
      }/patientConversations/getByPatient/${
        encodeURIComponent(telegraContext.providerPatientId)
      }?channelType=${encodeURIComponent(chatType)}`;
      const telegraRequestEndpoint = appendTelegraRequestTimestamp(
        telegraEndpoint,
      );

      let telegraResponse: Response;
      try {
        telegraResponse = await fetch(telegraRequestEndpoint, {
          method: "GET",
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${telegraContext.accessToken}`,
            "Content-Type": "application/json",
          },
        });
      } catch (error) {
        console.error("Telegra chat threads request failed:", error);
        return errorResponse(
          "TELEGRA_REQUEST_FAILED",
          "Failed to reach Telegra chat API",
          502,
        );
      }

      const rawTelegraResponse = await telegraResponse.text();
      let telegraResponseBody: unknown = null;

      if (rawTelegraResponse) {
        try {
          telegraResponseBody = JSON.parse(rawTelegraResponse);
        } catch {
          telegraResponseBody = rawTelegraResponse;
        }
      }

      console.log("Telegra chat threads response received:", {
        endpoint: telegraEndpoint,
        chatType,
        status: telegraResponse.status,
        statusText: telegraResponse.statusText,
        ok: telegraResponse.ok,
      });

      if (!telegraResponse.ok) {
        return errorResponse(
          "TELEGRA_API_ERROR",
          `Telegra chat threads request failed: ${
            extractErrorMessage(
              telegraResponseBody,
              `${telegraResponse.status} ${telegraResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      const normalizedChats = includeParticipantIdentifierInChats(
        extractTelegraChatThreads(telegraResponseBody),
        extractTelegraParticipantIdentifier(telegraResponseBody),
      );
      const chatThreads = chatType === "clinical"
        ? filterTelegraSystemMessagesInChats(normalizedChats)
        : normalizedChats;

      return jsonResponse({
        data: {
          provider_platform: {
            name: telegraContext.providerName,
            integration_key: telegraContext.tenantIntegration.integration_key,
          },
          ids: {
            patient_id: patient.id,
            tenant_id: patient.tenant_id,
            tenant_integration_id:
              telegraContext.providerLink.tenant_integration_id,
            provider_patient_id: telegraContext.providerPatientId,
          },
          chat_type: chatType,
          total_chats: chatThreads.length,
          chats: chatThreads,
        },
      });
    }

    if (req.method === "POST" && path === "/telegra-clinical-chat") {
      let body: {
        conversationID?: string;
        conversationId?: string;
        message?: string;
        channelType?: string;
        file?: unknown;
        attachment?: unknown;
      };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const conversationIdValue = typeof body.conversationID === "string"
        ? body.conversationID
        : typeof body.conversationId === "string"
        ? body.conversationId
        : "";
      const conversationId = conversationIdValue.trim();
      const message = typeof body.message === "string"
        ? body.message.trim()
        : "";
      const { file: telegraFile, error: telegraFileError } =
        parseTelegraChatFilePayload(body.file ?? body.attachment);
      if (telegraFileError) {
        return errorResponse(
          telegraFileError.code,
          telegraFileError.message,
          400,
        );
      }
      const channelType = typeof body.channelType === "string"
        ? body.channelType.trim().toLowerCase()
        : "";

      if (!conversationId || (!message && !telegraFile) || !channelType) {
        return errorResponse(
          "MISSING_FIELDS",
          "conversationID, message or file, and channelType are required",
          400,
        );
      }

      if (message && telegraFile) {
        return errorResponse(
          "INVALID_MESSAGE_FILE_COMBINATION",
          "Send either message or file for Telegra chat, not both",
          400,
        );
      }

      if (channelType !== "clinical" && channelType !== "support") {
        return errorResponse(
          "INVALID_CHAT_TYPE",
          "channelType must be either 'clinical' or 'support'",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { context: telegraContext, error: telegraContextError } =
        await getTelegraContext(patient);
      if (!telegraContext || telegraContextError) {
        return telegraContextError;
      }

      const telegraEndpoint = `${
        telegraContext.baseUrl.replace(/\/+$/, "")
      }/patientConversations/${
        encodeURIComponent(
          conversationId,
        )
      }/sendMessage`;
      const telegraRequestEndpoint = appendTelegraRequestTimestamp(
        telegraEndpoint,
      );

      let telegraResponse: Response;
      try {
        const telegraPayload: Record<string, unknown> = {
          sender: "patient",
          channelType,
        };
        if (telegraFile) {
          telegraPayload.file = {
            name: telegraFile.name,
            ...(telegraFile.ext ? { ext: telegraFile.ext } : {}),
            base64Data: telegraFile.base64Data,
          };
        } else {
          telegraPayload.message = message;
        }

        telegraResponse = await fetch(telegraRequestEndpoint, {
          method: "POST",
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${telegraContext.accessToken}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(telegraPayload),
        });
      } catch (error) {
        console.error("Telegra send message request failed:", error);
        return errorResponse(
          "TELEGRA_REQUEST_FAILED",
          "Failed to reach Telegra chat API",
          502,
        );
      }

      const rawTelegraResponse = await telegraResponse.text();
      let telegraResponseBody: unknown = null;

      if (rawTelegraResponse) {
        try {
          telegraResponseBody = JSON.parse(rawTelegraResponse);
        } catch {
          telegraResponseBody = rawTelegraResponse;
        }
      }

      console.log("Telegra send message response received:", {
        endpoint: telegraEndpoint,
        providerPatientId: telegraContext.providerPatientId,
        conversationId,
        channelType,
        status: telegraResponse.status,
        statusText: telegraResponse.statusText,
        ok: telegraResponse.ok,
      });

      if (!telegraResponse.ok) {
        return errorResponse(
          "TELEGRA_API_ERROR",
          `Telegra send message request failed: ${
            extractErrorMessage(
              telegraResponseBody,
              `${telegraResponse.status} ${telegraResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      return jsonResponse({
        message: "Message sent successfully",
        data: {
          provider_platform: {
            name: telegraContext.providerName,
            integration_key: telegraContext.tenantIntegration.integration_key,
          },
          ids: {
            patient_id: patient.id,
            tenant_id: patient.tenant_id,
            tenant_integration_id:
              telegraContext.providerLink.tenant_integration_id,
            provider_patient_id: telegraContext.providerPatientId,
          },
          conversation_id: conversationId,
          channel_type: channelType,
          sent_file: telegraFile
            ? {
              name: telegraFile.name,
              ext: telegraFile.ext ?? null,
              size_bytes: telegraFile.size_bytes,
            }
            : null,
          telegra_response: telegraResponseBody,
        },
      });
    }

    if (
      req.method === "GET" &&
      (path === "/mdi-patient-chat" || path === "/mdi-patient-chat/status")
    ) {
      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { context: mdiContext, error: mdiContextError } =
        await getMdiContext(patient);
      if (!mdiContext || mdiContextError) {
        return mdiContextError;
      }

      const mdiEndpoint = buildMdiPatientMessagesEndpoint({ mdiContext });

      let mdiResponse: Response;
      try {
        mdiResponse = await fetch(mdiEndpoint, {
          method: "GET",
          headers: getMdiHeaders(mdiContext),
        });
      } catch (error) {
        console.error("MDI messages request failed:", error);
        return errorResponse(
          "MDI_REQUEST_FAILED",
          "Failed to reach MDI messages API",
          502,
        );
      }

      const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);

      console.log("MDI messages response received:", {
        endpoint: mdiEndpoint,
        providerPatientId: mdiContext.providerPatientId,
        status: mdiResponse.status,
        statusText: mdiResponse.statusText,
        ok: mdiResponse.ok,
      });

      if (!mdiResponse.ok) {
        return errorResponse(
          "MDI_API_ERROR",
          `MDI messages request failed: ${
            extractErrorMessage(
              mdiResponseBody,
              `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      const messages = normalizeMdiMessages(mdiResponseBody);
      const summary = summarizeMdiMessages(messages);
      const responseBase = buildMdiResponseBase(patient, mdiContext);

      if (path === "/mdi-patient-chat/status") {
        return jsonResponse({
          data: {
            ...responseBase,
            summary,
          },
        });
      }

      return jsonResponse({
        data: {
          ...responseBase,
          summary,
          pagination: extractMdiPagination(mdiResponseBody),
          messages,
        },
      });
    }

    const mdiReadMessageMatch = path.match(
      /^\/mdi-patient-chat\/([^/]+)\/read$/,
    );
    if (req.method === "POST" && mdiReadMessageMatch) {
      const messageId = decodeURIComponent(mdiReadMessageMatch[1] || "").trim();
      if (!messageId) {
        return errorResponse(
          "MISSING_FIELDS",
          "message id is required",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { context: mdiContext, error: mdiContextError } =
        await getMdiContext(patient);
      if (!mdiContext || mdiContextError) {
        return mdiContextError;
      }

      const mdiEndpoint = buildMdiPatientMessagesEndpoint({
        mdiContext,
        messageId,
        action: "read",
      });

      let mdiResponse: Response;
      try {
        mdiResponse = await fetch(mdiEndpoint, {
          method: "POST",
          headers: getMdiHeaders(mdiContext),
        });
      } catch (error) {
        console.error("MDI mark message read request failed:", error);
        return errorResponse(
          "MDI_REQUEST_FAILED",
          "Failed to reach MDI messages API",
          502,
        );
      }

      const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);

      console.log("MDI mark message read response received:", {
        endpoint: mdiEndpoint,
        providerPatientId: mdiContext.providerPatientId,
        messageId,
        status: mdiResponse.status,
        statusText: mdiResponse.statusText,
        ok: mdiResponse.ok,
      });

      if (!mdiResponse.ok) {
        return errorResponse(
          "MDI_API_ERROR",
          `MDI mark message read request failed: ${
            extractErrorMessage(
              mdiResponseBody,
              `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      return jsonResponse({
        message: "Message marked as read",
        data: {
          ...buildMdiResponseBase(patient, mdiContext),
          message: normalizeMdiSingleMessage(mdiResponseBody),
        },
      });
    }

    const mdiGetMessageMatch = path.match(/^\/mdi-patient-chat\/([^/]+)$/);
    if (req.method === "GET" && mdiGetMessageMatch) {
      const messageId = decodeURIComponent(mdiGetMessageMatch[1] || "").trim();
      if (!messageId) {
        return errorResponse(
          "MISSING_FIELDS",
          "message id is required",
          400,
        );
      }

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { context: mdiContext, error: mdiContextError } =
        await getMdiContext(patient);
      if (!mdiContext || mdiContextError) {
        return mdiContextError;
      }

      const mdiEndpoint = buildMdiPatientMessagesEndpoint({
        mdiContext,
        messageId,
      });

      let mdiResponse: Response;
      try {
        mdiResponse = await fetch(mdiEndpoint, {
          method: "GET",
          headers: getMdiHeaders(mdiContext),
        });
      } catch (error) {
        console.error("MDI get message request failed:", error);
        return errorResponse(
          "MDI_REQUEST_FAILED",
          "Failed to reach MDI messages API",
          502,
        );
      }

      const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);

      console.log("MDI get message response received:", {
        endpoint: mdiEndpoint,
        providerPatientId: mdiContext.providerPatientId,
        messageId,
        status: mdiResponse.status,
        statusText: mdiResponse.statusText,
        ok: mdiResponse.ok,
      });

      if (!mdiResponse.ok) {
        return errorResponse(
          "MDI_API_ERROR",
          `MDI get message request failed: ${
            extractErrorMessage(
              mdiResponseBody,
              `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      return jsonResponse({
        data: {
          ...buildMdiResponseBase(patient, mdiContext),
          message: normalizeMdiSingleMessage(mdiResponseBody),
        },
      });
    }

    if (req.method === "POST" && path === "/mdi-patient-chat") {
      let body: {
        message?: string;
        text?: string;
        reference_message_id?: string;
        referenceMessageId?: string;
      };
      try {
        body = await req.json();
      } catch {
        return errorResponse("INVALID_JSON", "Invalid JSON body", 400);
      }

      const messageValue = typeof body.message === "string"
        ? body.message
        : typeof body.text === "string"
        ? body.text
        : "";
      const message = messageValue.trim();

      if (!message) {
        return errorResponse(
          "MISSING_FIELDS",
          "message is required",
          400,
        );
      }

      const referenceMessageIdValue =
        typeof body.reference_message_id === "string"
          ? body.reference_message_id
          : typeof body.referenceMessageId === "string"
          ? body.referenceMessageId
          : "";
      const referenceMessageId = referenceMessageIdValue.trim();

      const { patient, error: authError } = await getAuthenticatedPatient();
      if (!patient || authError) {
        return authError;
      }

      const { context: mdiContext, error: mdiContextError } =
        await getMdiContext(patient);
      if (!mdiContext || mdiContextError) {
        return mdiContextError;
      }

      const mdiEndpoint = buildMdiPatientMessagesEndpoint({ mdiContext });
      const mdiPayload: Record<string, unknown> = {
        channel: "patient",
        text: message,
      };
      if (referenceMessageId) {
        mdiPayload.reference_message_id = referenceMessageId;
      }

      let mdiResponse: Response;
      try {
        mdiResponse = await fetch(mdiEndpoint, {
          method: "POST",
          headers: getMdiHeaders(mdiContext),
          body: JSON.stringify(mdiPayload),
        });
      } catch (error) {
        console.error("MDI send message request failed:", error);
        return errorResponse(
          "MDI_REQUEST_FAILED",
          "Failed to reach MDI messages API",
          502,
        );
      }

      const mdiResponseBody = await parseJsonOrTextResponse(mdiResponse);

      console.log("MDI send message response received:", {
        endpoint: mdiEndpoint,
        providerPatientId: mdiContext.providerPatientId,
        status: mdiResponse.status,
        statusText: mdiResponse.statusText,
        ok: mdiResponse.ok,
      });

      if (!mdiResponse.ok) {
        return errorResponse(
          "MDI_API_ERROR",
          `MDI send message request failed: ${
            extractErrorMessage(
              mdiResponseBody,
              `${mdiResponse.status} ${mdiResponse.statusText}`.trim(),
            )
          }`,
          502,
        );
      }

      return jsonResponse({
        message: "Message sent successfully",
        data: {
          ...buildMdiResponseBase(patient, mdiContext),
          message: normalizeMdiSingleMessage(mdiResponseBody),
        },
      });
    }

    return errorResponse(
      "NOT_FOUND",
      `Endpoint ${req.method} ${path} not found`,
      404,
    );
  } catch (error) {
    console.error("Messenger API Error:", error);
    return errorResponse(
      "INTERNAL_ERROR",
      error instanceof Error ? error.message : "An unexpected error occurred",
      500,
    );
  }
});
