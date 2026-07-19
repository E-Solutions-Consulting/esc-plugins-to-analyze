import { assertEquals } from "../_test/assert.ts";
import {
  buildCorsHeaders,
  getConfiguredCorsAllowedOrigins,
  isAllowedOrigin,
} from "./cors.ts";

function withCorsAllowedOrigins(
  value: string | null,
  fn: () => void,
): void {
  const previousValue = Deno.env.get("CORS_ALLOWED_ORIGINS");

  if (value === null) {
    Deno.env.delete("CORS_ALLOWED_ORIGINS");
  } else {
    Deno.env.set("CORS_ALLOWED_ORIGINS", value);
  }

  try {
    fn();
  } finally {
    if (previousValue === undefined) {
      Deno.env.delete("CORS_ALLOWED_ORIGINS");
    } else {
      Deno.env.set("CORS_ALLOWED_ORIGINS", previousValue);
    }
  }
}

Deno.test("default CORS origins keep localhost and lovable domains enabled", () => {
  withCorsAllowedOrigins(null, () => {
    const origins = getConfiguredCorsAllowedOrigins();

    assertEquals(origins.includes("http://localhost:*"), true);
    assertEquals(origins.includes("https://*.lovable.app"), true);
    assertEquals(isAllowedOrigin("http://localhost:5173", origins), true);
    assertEquals(isAllowedOrigin("https://demo.lovableproject.com", origins), true);
    assertEquals(isAllowedOrigin("https://evil.example.com", origins), false);
  });
});

Deno.test("CORS helper uses configured origin patterns from environment", () => {
  withCorsAllowedOrigins(
    "https://admin.example.com, https://*.staging.example.com",
    () => {
      const origins = getConfiguredCorsAllowedOrigins();

      assertEquals(origins, [
        "https://admin.example.com",
        "https://*.staging.example.com",
      ]);
      assertEquals(isAllowedOrigin("https://admin.example.com", origins), true);
      assertEquals(isAllowedOrigin("https://api.staging.example.com", origins), true);
      assertEquals(isAllowedOrigin("https://demo.lovable.app", origins), false);
    },
  );
});

Deno.test("buildCorsHeaders only returns allow-origin for allowed browser origins", () => {
  withCorsAllowedOrigins("https://admin.example.com", () => {
    const allowedHeaders = buildCorsHeaders(
      new Request("https://example.com", {
        headers: {
          origin: "https://admin.example.com",
          "access-control-request-headers": "authorization,content-type",
        },
      }),
    );

    assertEquals(
      allowedHeaders["Access-Control-Allow-Origin"],
      "https://admin.example.com",
    );
    assertEquals(allowedHeaders["Access-Control-Allow-Credentials"], "true");
    assertEquals(
      allowedHeaders["Access-Control-Allow-Headers"],
      "authorization,content-type",
    );

    const blockedHeaders = buildCorsHeaders(
      new Request("https://example.com", {
        headers: {
          origin: "https://blocked.example.com",
        },
      }),
    );

    assertEquals(blockedHeaders["Access-Control-Allow-Origin"], undefined);
    assertEquals(blockedHeaders["Access-Control-Allow-Credentials"], undefined);
  });
});
