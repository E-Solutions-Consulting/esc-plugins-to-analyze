import { buildCorsHeaders } from "../_shared/cors.ts";

const DEFAULT_BRELLO_BACKEND_BASE_URL =
  "https://brello-backend-dev-241930798806.us-central1.run.app/";

function jsonResponse(
  body: unknown,
  status: number,
  corsHeaders: Record<string, string>,
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      ...corsHeaders,
      "Content-Type": "application/json",
    },
  });
}

Deno.serve(async (req): Promise<Response> => {
  const corsHeaders = buildCorsHeaders(req, {
    methods: "GET, OPTIONS",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  if (req.method !== "GET") {
    return jsonResponse({ error: "Method not allowed" }, 405, {
      ...corsHeaders,
      Allow: "GET",
    });
  }

  const brelloEducationApiKey = Deno.env.get("BRELLO_EDUCATION_API_KEY");
  if (!brelloEducationApiKey) {
    console.error("Missing BRELLO_EDUCATION_API_KEY");
    return jsonResponse(
      { error: "Education content is not configured" },
      500,
      corsHeaders,
    );
  }

  let brelloUrl: URL;
  try {
    const requestUrl = new URL(req.url);
    const topic = requestUrl.searchParams.get("topic");
    const brelloBackendBaseUrl = Deno.env.get("BRELLO_BACKEND_BASE_URL") ||
      DEFAULT_BRELLO_BACKEND_BASE_URL;

    brelloUrl = new URL(
      "/patient-admin/v1/page/education",
      brelloBackendBaseUrl,
    );

    if (topic) {
      brelloUrl.searchParams.set("topic", topic);
    }
  } catch (error) {
    console.error("Invalid Brello education configuration", error);
    return jsonResponse(
      { error: "Education content is not configured" },
      500,
      corsHeaders,
    );
  }

  try {
    const response = await fetch(brelloUrl, {
      headers: {
        "x-api-key": brelloEducationApiKey,
      },
    });

    if (!response.ok) {
      console.error(
        `Failed to fetch Brello education content: ${response.status}`,
      );
      return jsonResponse(
        {
          error: "Failed to fetch Brello education content",
          status: response.status,
        },
        502,
        corsHeaders,
      );
    }

    const body = await response.json();
    return jsonResponse(body, 200, corsHeaders);
  } catch (error) {
    console.error("Failed to fetch Brello education content", error);
    return jsonResponse(
      { error: "Failed to fetch Brello education content" },
      502,
      corsHeaders,
    );
  }
});
