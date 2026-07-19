export const DEFAULT_CORS_ALLOWED_ORIGINS = [
  "http://localhost:*",
  "http://127.0.0.1:*",
  "https://*.lovableproject.com",
  "https://*.lovable.app",
] as const;

export const DEFAULT_CORS_ALLOW_HEADERS =
  "authorization, x-client-info, apikey, apiKey, content-type, x-tenant-slug, x-api-version, x-request-id, x-supabase-api-version, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version";

export const DEFAULT_CORS_EXPOSE_HEADERS =
  "Content-Length, X-JSON, X-Request-Id";

export const DEFAULT_CORS_MAX_AGE = "86400";

export interface CorsOptions {
  allowCredentials?: boolean;
  allowHeaders?: string;
  exposeHeaders?: string;
  maxAge?: string;
  methods?: string;
}

function parseCsv(value: string | undefined): string[] {
  return (value || "")
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
}

function normalizeOriginValue(value: string): string {
  return value
    .trim()
    .replace(/^"(.*)"$/, "$1")
    .replace(/^'(.*)'$/, "$1")
    .replace(/\/$/, "");
}

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function patternToRegExp(pattern: string): RegExp {
  const normalizedPattern = normalizeOriginValue(pattern);
  const escapedPattern = normalizedPattern
    .split("*")
    .map((segment) => escapeRegex(segment))
    .join(".*");
  return new RegExp(`^${escapedPattern}$`);
}

export function getConfiguredCorsAllowedOrigins(): string[] {
  const configuredOrigins = parseCsv(Deno.env.get("CORS_ALLOWED_ORIGINS"));
  return configuredOrigins.length > 0
    ? configuredOrigins.map(normalizeOriginValue)
    : [...DEFAULT_CORS_ALLOWED_ORIGINS];
}

export function isAllowedOrigin(
  origin: string,
  allowedOrigins: string[] = getConfiguredCorsAllowedOrigins(),
): boolean {
  const normalizedOrigin = normalizeOriginValue(origin);
  return allowedOrigins.some((pattern) => patternToRegExp(pattern).test(normalizedOrigin));
}

export function buildCorsHeaders(
  req: Request,
  options: CorsOptions = {},
): Record<string, string> {
  const origin = req.headers.get("origin");
  const requestHeaders = req.headers.get("access-control-request-headers");
  const headers: Record<string, string> = {
    "Access-Control-Allow-Headers": requestHeaders || options.allowHeaders || DEFAULT_CORS_ALLOW_HEADERS,
    "Access-Control-Allow-Methods": options.methods || "GET, POST, PUT, PATCH, DELETE, OPTIONS",
    "Access-Control-Expose-Headers": options.exposeHeaders || DEFAULT_CORS_EXPOSE_HEADERS,
    "Access-Control-Max-Age": options.maxAge || DEFAULT_CORS_MAX_AGE,
    Vary: "Origin, Access-Control-Request-Headers",
  };

  if (!origin) {
    headers["Access-Control-Allow-Origin"] = "*";
    return headers;
  }

  if (isAllowedOrigin(origin)) {
    headers["Access-Control-Allow-Origin"] = normalizeOriginValue(origin);
    if (options.allowCredentials !== false) {
      headers["Access-Control-Allow-Credentials"] = "true";
    }
  }

  return headers;
}
