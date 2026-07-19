export interface TenantEmailTemplateResponse {
  tenant_id: string;
  slug: string;
  email_template_html: string | null;
  web_app_base_url: string | null;
}

export function sanitizeTenantSlug(value: string): string {
  return value.trim().replace(/['"]/g, "");
}

export function getTenantIdentifier(
  url: URL,
  headers: Headers,
): { slug: string | null; tenantId: string | null } {
  const slug = url.searchParams.get("slug") || headers.get("x-tenant-slug");
  const tenantId = url.searchParams.get("tenant_id") ||
    headers.get("x-tenant-id");

  return { slug, tenantId };
}

function readNonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim();
  return normalized.length > 0 ? normalized : null;
}

export function readWebAppBaseUrl(
  metadata: Record<string, unknown> | null | undefined,
): string | null {
  if (!metadata || typeof metadata !== "object" || Array.isArray(metadata)) {
    return null;
  }

  const mobileApps = metadata.mobile_apps;
  if (
    !mobileApps ||
    typeof mobileApps !== "object" ||
    Array.isArray(mobileApps)
  ) {
    return null;
  }

  const settings = mobileApps as Record<string, unknown>;
  const webApp = settings.web_app;
  if (webApp && typeof webApp === "object" && !Array.isArray(webApp)) {
    const baseUrl = readNonEmptyString(
      (webApp as Record<string, unknown>).base_url,
    );
    if (baseUrl) return baseUrl;
  }

  return readNonEmptyString(settings.web_app_base_url);
}
