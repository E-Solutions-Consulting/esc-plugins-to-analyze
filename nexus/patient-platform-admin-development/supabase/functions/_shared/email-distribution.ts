export interface SendEmailViaTenantDistributionParams {
  supabaseClient: unknown;
  tenantId: string;
  to: string | string[];
  subject: string;
  html: string;
  logContext?: Record<string, unknown>;
}

export interface SendEmailViaTenantDistributionResult {
  integrationKey: string;
  /** Provider message id (e.g. Resend email id) when available — for deliverability tracking. */
  messageId?: string | null;
}

type TenantEmailIntegration = {
  integrationKey: string;
  apiKey: string;
  senderEmail: string | null;
};

type QueryResponse<T> = {
  data: T;
  error: unknown;
};

export interface SupabaseSelectBuilder<T> {
  select(columns: string): SupabaseFilterBuilder<T>;
}

export interface SupabaseFilterBuilder<T>
  extends PromiseLike<QueryResponse<T>> {
  eq(column: string, value: unknown): SupabaseFilterBuilder<T>;
  in(column: string, values: unknown[]): SupabaseFilterBuilder<T>;
  maybeSingle(): PromiseLike<QueryResponse<T | null>>;
}

export interface SupabaseEmailClient {
  from<T = unknown>(table: string): SupabaseSelectBuilder<T>;
}

export const EMAIL_TEMPLATE_TITLE_KEY = "{{EMAIL_TITLE}}";
export const EMAIL_TEMPLATE_CONTENT_KEY = "{{EMAIL_CONTENT}}";

function maskEmail(email: string): string {
  const [localPart = "", domainPart = ""] = email.trim().toLowerCase().split(
    "@",
  );
  if (!domainPart) return "***";
  if (localPart.length <= 2) {
    return `${localPart.slice(0, 1) || "*"}***@${domainPart}`;
  }
  return `${localPart.slice(0, 2)}***@${domainPart}`;
}

function normalizeErrorForLog(error: unknown): Record<string, unknown> {
  if (error instanceof Error) {
    return {
      name: error.name,
      message: error.message,
      stack: error.stack,
    };
  }

  return { error };
}

function escapeHtml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

async function resolveTenantEmailIntegration(
  supabaseClient: SupabaseEmailClient,
  tenantId: string,
  logContext: Record<string, unknown> = {},
): Promise<TenantEmailIntegration> {
  const { data: platformIntegrations, error: platformError } =
    await supabaseClient
      .from<Array<{ key: string }>>("platform_integrations")
      .select("key")
      .eq("category", "email_distribution")
      .eq("is_active", true);

  if (platformError || !platformIntegrations?.length) {
    console.error("Tenant email integration platform lookup failed", {
      ...logContext,
      tenantId,
      platformError,
      hasPlatformIntegrations: Boolean(platformIntegrations?.length),
    });
    throw new Error("email_integration_not_available");
  }

  const integrationKeys = platformIntegrations.map((integration) =>
    integration.key
  );

  const { data: tenantIntegration, error: tenantError } = await supabaseClient
    .from<{
      integration_key: string;
      settings: Record<string, string> | null;
    }>("tenant_integrations")
    .select("integration_key, settings")
    .eq("tenant_id", tenantId)
    .eq("is_enabled", true)
    .in("integration_key", integrationKeys)
    .maybeSingle();

  if (tenantError || !tenantIntegration) {
    console.error("Tenant email integration lookup failed", {
      ...logContext,
      tenantId,
      integrationKeys,
      tenantError,
      hasTenantIntegration: Boolean(tenantIntegration),
    });
    throw new Error("tenant_email_integration_missing");
  }

  const settings = (tenantIntegration.settings || {}) as Record<string, string>;
  const apiKey = settings.api_key;
  if (!apiKey) {
    console.error("Tenant email integration is missing API key", {
      ...logContext,
      tenantId,
      integrationKey: tenantIntegration.integration_key,
    });
    throw new Error("tenant_email_integration_missing_api_key");
  }

  console.info("Tenant email integration resolved", {
    ...logContext,
    tenantId,
    integrationKey: tenantIntegration.integration_key,
    hasSenderEmail: Boolean(settings.sender_email),
  });

  return {
    integrationKey: tenantIntegration.integration_key,
    apiKey,
    senderEmail: settings.sender_email || null,
  };
}

export async function resolveTenantEmailTemplate(
  supabaseClient: SupabaseEmailClient,
  tenantId: string,
  logContext: Record<string, unknown> = {},
): Promise<string | null> {
  const { data: tenantSettings, error } = await supabaseClient
    .from<{ metadata: Record<string, unknown> | null }>("tenant_settings")
    .select("metadata")
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    console.error("Tenant email template lookup failed", {
      ...logContext,
      tenantId,
      error,
    });
    return null;
  }

  const metadata = (tenantSettings?.metadata || {}) as Record<string, unknown>;
  const templateHtml = metadata.email_template_html;
  if (typeof templateHtml !== "string" || !templateHtml.trim()) {
    return null;
  }

  return templateHtml;
}

function applyTenantEmailTemplate(
  templateHtml: string | null,
  subject: string,
  html: string,
  logContext: Record<string, unknown> = {},
): string {
  if (!templateHtml) return html;

  if (
    !templateHtml.includes(EMAIL_TEMPLATE_TITLE_KEY) ||
    !templateHtml.includes(EMAIL_TEMPLATE_CONTENT_KEY)
  ) {
    console.error("Tenant email template is missing required keys", {
      ...logContext,
      hasTitleKey: templateHtml.includes(EMAIL_TEMPLATE_TITLE_KEY),
      hasContentKey: templateHtml.includes(EMAIL_TEMPLATE_CONTENT_KEY),
    });
    return html;
  }

  return templateHtml
    .replaceAll(EMAIL_TEMPLATE_TITLE_KEY, escapeHtml(subject))
    .replaceAll(EMAIL_TEMPLATE_CONTENT_KEY, html);
}

async function sendEmailViaResend(
  apiKey: string,
  senderEmail: string | null,
  to: string[],
  subject: string,
  html: string,
  logContext: Record<string, unknown> = {},
): Promise<string | null> {
  const fromEmail = senderEmail || "noreply@resend.dev";
  console.info("Sending email via Resend", {
    ...logContext,
    provider: "resend",
    fromEmail,
    to: to.map(maskEmail),
    subject,
    htmlLength: html.length,
  });
  const response = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      from: fromEmail,
      to,
      subject,
      html,
    }),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.error("Resend email send failed", {
      ...logContext,
      provider: "resend",
      fromEmail,
      to: to.map(maskEmail),
      status: response.status,
      statusText: response.statusText,
      errorText,
    });
    throw new Error(`resend_email_failed:${response.status}:${errorText}`);
  }

  let responseBody: unknown = null;
  try {
    responseBody = await response.json();
  } catch (error) {
    console.error("Failed to parse Resend success response", {
      ...logContext,
      provider: "resend",
      fromEmail,
      to: to.map(maskEmail),
      responseStatus: response.status,
      responseStatusText: response.statusText,
      error: normalizeErrorForLog(error),
    });
  }

  const messageId =
    typeof responseBody === "object" && responseBody !== null && "id" in responseBody
      ? (responseBody as { id?: string }).id ?? null
      : null;
  console.info("Resend email send succeeded", {
    ...logContext,
    provider: "resend",
    fromEmail,
    to: to.map(maskEmail),
    status: response.status,
    resendMessageId: messageId,
  });
  return messageId;
}

export async function sendEmailViaTenantDistribution({
  supabaseClient,
  tenantId,
  to,
  subject,
  html,
  logContext = {},
}: SendEmailViaTenantDistributionParams): Promise<
  SendEmailViaTenantDistributionResult
> {
  const emailClient = supabaseClient as SupabaseEmailClient;
  const integration = await resolveTenantEmailIntegration(
    emailClient,
    tenantId,
    logContext,
  );

  const recipients = Array.isArray(to) ? to : [to];
  const templateHtml = await resolveTenantEmailTemplate(
    emailClient,
    tenantId,
    logContext,
  );
  const renderedHtml = applyTenantEmailTemplate(
    templateHtml,
    subject,
    html,
    {
      ...logContext,
      tenantId,
      integrationKey: integration.integrationKey,
    },
  );

  if (integration.integrationKey === "resend") {
    const messageId = await sendEmailViaResend(
      integration.apiKey,
      integration.senderEmail,
      recipients,
      subject,
      renderedHtml,
      {
        ...logContext,
        tenantId,
        integrationKey: integration.integrationKey,
      },
    );
    return { integrationKey: integration.integrationKey, messageId };
  }

  throw new Error(
    `unsupported_email_integration:${integration.integrationKey}`,
  );
}
