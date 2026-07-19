// deno-lint-ignore no-import-prefix
import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { handleRenewalOrderCreate } from "./renewal.ts";
import type { RtdhEventPayload } from "./validation.ts";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = SupabaseClient<any, "public", any>;

type JsonResponseBuilder = (
  req: Request,
  body: Record<string, unknown>,
  status?: number,
  headers?: Record<string, string>,
) => Response;

type ErrorResponseBuilder = (
  req: Request,
  code: string,
  message: string,
  status: number,
  requestId: string,
  details?: unknown,
) => Response;

export async function processRenewalIntent(params: {
  req: Request;
  supabase: SupabaseAdminClient;
  payload: RtdhEventPayload;
  requestId: string;
  jsonResponse: JsonResponseBuilder;
  errorResponse: ErrorResponseBuilder;
  renewalHandler?: typeof handleRenewalOrderCreate;
  lifecycleTrigger: (
    orderId: string,
    tenantId: string,
    requestId: string,
  ) => Promise<boolean>;
}): Promise<Response> {
  const {
    req,
    supabase,
    payload,
    requestId,
    jsonResponse,
    errorResponse,
    renewalHandler = handleRenewalOrderCreate,
    lifecycleTrigger,
  } = params;

  const renewalResult = await renewalHandler({
    supabase,
    payload,
    requestId,
  });

  if (!renewalResult.ok) {
    return errorResponse(
      req,
      renewalResult.code,
      renewalResult.message,
      renewalResult.status,
      requestId,
      renewalResult.details,
    );
  }

  const lifecycleTriggered = renewalResult.created
    ? await lifecycleTrigger(renewalResult.orderId, renewalResult.tenantId, requestId)
    : false;

  return jsonResponse(
    req,
    {
      received: true,
      requestId,
      eventType: "renewal_order_create",
      actionResult: {
        action: "renewal_order_create",
        orderId: renewalResult.orderId,
        created: renewalResult.created,
        resolutionStrategy: renewalResult.strategy,
        lifecycleTriggered,
      },
    },
    200,
    { "x-request-id": requestId },
  );
}