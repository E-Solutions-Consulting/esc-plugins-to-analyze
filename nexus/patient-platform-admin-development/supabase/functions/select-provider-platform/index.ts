import { createClient } from "jsr:@supabase/supabase-js@2";
import type { SupabaseClient } from "npm:@supabase/supabase-js@2.49.2";
import { buildCorsHeaders } from "../_shared/cors.ts";
import {
  normalizeStateCode,
  resolveAndPersistProviderPlatformSelection,
  resolveProviderPlatformSelection,
} from "../_shared/provider-platform-selection.ts";

function jsonResponse(req: Request, body: Record<string, unknown>, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      ...buildCorsHeaders(req, {
        allowHeaders: "authorization, x-client-info, apikey, content-type, x-tenant-id",
        methods: "POST, OPTIONS",
      }),
      "Content-Type": "application/json",
    },
  });
}

function getSupabaseClients(authHeader: string | null) {
  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY");
  const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseAnonKey || !supabaseServiceKey) {
    throw new Error("Missing Supabase configuration");
  }

  const authClient = createClient(supabaseUrl, supabaseAnonKey, {
    global: authHeader
      ? {
          headers: {
            Authorization: authHeader,
          },
        }
      : undefined,
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });

  const adminClient = createClient(supabaseUrl, supabaseServiceKey, {
    auth: {
      autoRefreshToken: false,
      persistSession: false,
    },
  });

  return {
    authClient,
    adminClient,
  };
}

async function requireAuthenticatedUser(authClient: SupabaseClient) {
  const {
    data: { user },
    error,
  } = await authClient.auth.getUser();

  if (error || !user) {
    throw new Error("Unauthorized");
  }

  return user;
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") {
    return jsonResponse(req, {}, 200);
  }

  if (req.method !== "POST") {
    return jsonResponse(req, { error: "Method not allowed" }, 405);
  }

  try {
    const authHeader = req.headers.get("Authorization");
    const { authClient, adminClient } = getSupabaseClients(authHeader);
    await requireAuthenticatedUser(authClient);

    const body = await req.json().catch(() => null) as
      | {
          orderId?: string;
          productId?: string;
          state?: string | null;
          tenantId?: string;
          persistSelection?: boolean;
        }
      | null;

    const persistSelection = body?.persistSelection ?? true;
    let tenantId = body?.tenantId || req.headers.get("x-tenant-id") || null;
    let productId = body?.productId || null;
    const orderId = body?.orderId || null;
    let orderNumber: string | null = null;
    let stateCode = normalizeStateCode(body?.state);

    if (orderId) {
      const { data: order, error: orderError } = await authClient
        .from("orders")
        .select("id, tenant_id, product_id, order_number, shipping_state")
        .eq("id", orderId)
        .maybeSingle();

      if (orderError) {
        throw new Error(`Failed to fetch order: ${orderError.message}`);
      }
      if (!order) {
        return jsonResponse(req, { error: "Order not found" }, 404);
      }

      tenantId = order.tenant_id;
      productId = order.product_id;
      orderNumber = order.order_number;
      stateCode = normalizeStateCode(stateCode || order.shipping_state);
    } else {
      if (!tenantId || !productId) {
        return jsonResponse(
          req,
          { error: "tenantId and productId are required when orderId is not provided" },
          400,
        );
      }

      const { data: product, error: productError } = await authClient
        .from("products")
        .select("id")
        .eq("id", productId)
        .eq("tenant_id", tenantId)
        .maybeSingle();

      if (productError) {
        throw new Error(`Failed to fetch product: ${productError.message}`);
      }
      if (!product) {
        return jsonResponse(req, { error: "Product not found" }, 404);
      }
    }

    if (!tenantId || !productId) {
      return jsonResponse(req, { error: "Could not resolve tenantId and productId" }, 400);
    }

    const selection = persistSelection
      ? await resolveAndPersistProviderPlatformSelection({
          supabase: adminClient,
          tenantId,
          productId,
          orderId,
          orderNumber,
          stateCode,
          source: "select-provider-platform",
        })
      : await resolveProviderPlatformSelection({
          supabase: adminClient,
          tenantId,
          productId,
          stateCode,
        });

    return jsonResponse(req, {
      data: {
        tenant_id: tenantId,
        product_id: productId,
        order_id: orderId,
        state_code: stateCode,
        selection,
      },
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    const status = message === "Unauthorized" ? 401 : 500;

    return jsonResponse(req, { error: message }, status);
  }
});
