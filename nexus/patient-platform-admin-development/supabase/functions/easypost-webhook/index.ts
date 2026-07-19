import { buildCorsHeaders } from "../_shared/cors.ts";

function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    allowHeaders:
      "authorization, x-client-info, apikey, content-type, x-request-id",
    methods: "GET, POST, PUT, PATCH, DELETE, OPTIONS",
  });
}

function jsonResponse(req: Request, body: Record<string, unknown>, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...getCorsHeaders(req), "Content-Type": "application/json" },
  });
}

async function readRequestBody(req: Request): Promise<{
  content: string | null;
  contentLength: number;
  readError: string | null;
}> {
  try {
    const content = await req.text();
    return {
      content,
      contentLength: content.length,
      readError: null,
    };
  } catch (error) {
    return {
      content: null,
      contentLength: 0,
      readError: error instanceof Error ? error.message : String(error),
    };
  }
}

Deno.serve(async (req) => {
  const corsHeaders = getCorsHeaders(req);

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const requestId = req.headers.get("x-request-id")?.trim() ||
    crypto.randomUUID();
  const url = new URL(req.url);
  const headers = Object.fromEntries(req.headers.entries());
  const { content, contentLength, readError } = await readRequestBody(req);

  console.info("Received EasyPost webhook request", {
    requestId,
    method: req.method,
    url: req.url,
    pathname: url.pathname,
    search: url.search,
    headers,
    content,
    contentLength,
    readError,
  });

  return jsonResponse(req, {
    received: true,
    requestId,
  });
});
