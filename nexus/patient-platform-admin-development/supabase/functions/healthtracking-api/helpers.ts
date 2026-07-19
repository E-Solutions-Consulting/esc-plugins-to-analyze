import {
  buildCorsHeaders,
} from "../_shared/cors.ts";
export { isAllowedOrigin } from "../_shared/cors.ts";

export interface RateLimitRecord {
  count: number;
  resetAt: number;
}

export const RATE_LIMIT = 100;
export const RATE_WINDOW = 60000;
export const rateLimitMap = new Map<string, RateLimitRecord>();

export function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    methods: "GET, POST, DELETE, OPTIONS",
  });
}

export function checkRateLimit(
  clientId: string,
  store: Map<string, RateLimitRecord> = rateLimitMap,
  now: number = Date.now()
): { allowed: boolean; remaining: number } {
  const record = store.get(clientId);

  if (!record || now > record.resetAt) {
    store.set(clientId, { count: 1, resetAt: now + RATE_WINDOW });
    return { allowed: true, remaining: RATE_LIMIT - 1 };
  }

  if (record.count >= RATE_LIMIT) {
    return { allowed: false, remaining: 0 };
  }

  record.count++;
  return { allowed: true, remaining: RATE_LIMIT - record.count };
}
