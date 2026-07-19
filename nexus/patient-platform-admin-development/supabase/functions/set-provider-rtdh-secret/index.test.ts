import { assertEquals } from "../_test/assert.ts";
import { createSetProviderRtdhSecretHandler } from "./index.ts";

type TableResponse = { data: unknown; error: { message: string } | null };

function jsonRequest(body: Record<string, unknown>): Request {
  return new Request("https://functions.test/set-provider-rtdh-secret", {
    method: "POST",
    headers: {
      Authorization: "Bearer token-1",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
  });
}

function createQueryBuilder(
  table: string,
  responses: Record<string, TableResponse[]>,
  calls: unknown[][],
) {
  return {
    select(value?: string) {
      calls.push(["select", table, value]);
      return this;
    },
    eq(column: string, value: unknown) {
      calls.push(["eq", table, column, value]);
      return this;
    },
    maybeSingle() {
      calls.push(["maybeSingle", table]);
      const response = responses[table]?.shift();
      return Promise.resolve(response ?? { data: null, error: null });
    },
  };
}

function createMockCreateClient(params: {
  isSuper: boolean;
  hasMembership: boolean;
  tenantSlug?: string | null;
  calls: unknown[][];
}) {
  const responses: Record<string, TableResponse[]> = {
    admin_users: [{ data: { id: "admin-1", is_active: true }, error: null }],
    tenant_memberships: [
      {
        data: params.hasMembership ? { id: "membership-1" } : null,
        error: null,
      },
    ],
    tenants: [
      {
        data: params.tenantSlug === null
          ? null
          : { slug: params.tenantSlug ?? "brello", name: "Brello" },
        error: null,
      },
    ],
    platform_settings: [
      {
        data: {
          value: {
            base_url: "https://rtdh.example.com",
            secret_manager_receiver_secret: "receiver-secret",
          },
        },
        error: null,
      },
    ],
  };

  return () => ({
    auth: {
      getUser: () =>
        Promise.resolve({
          data: { user: { id: "auth-user-1", email: "admin@example.com" } },
          error: null,
        }),
    },
    from: (table: string) => createQueryBuilder(table, responses, params.calls),
    rpc: (name: string, args: Record<string, unknown>) => {
      params.calls.push(["rpc", name, args]);
      return Promise.resolve({ data: params.isSuper, error: null });
    },
  });
}

Deno.test("set-provider-rtdh-secret allows tenant admin and sends tenant-scoped base64 payload", async () => {
  const calls: unknown[][] = [];
  const secretCalls: Array<Record<string, unknown>> = [];
  const handler = createSetProviderRtdhSecretHandler({
    createClientImpl: createMockCreateClient({
      isSuper: false,
      hasMembership: true,
      tenantSlug: "brello",
      calls,
    }) as never,
    secretManager: {
      saveSecretsViaRtdh: (params) => {
        secretCalls.push({ kind: "bulk", ...params });
        return Promise.resolve({
          success: true,
          tenant: params.tenant,
          provider: params.provider,
          context: params.context ?? null,
          saved: [],
          requestId: params.requestId,
        });
      },
      saveSecretViaRtdh: (params) => {
        secretCalls.push({ kind: "single", ...params });
        return Promise.resolve({
          success: true,
          tenant: params.tenant,
          provider: params.provider,
          context: params.context ?? null,
          saved: [],
          requestId: params.requestId,
        });
      },
    },
  });

  const response = await handler(jsonRequest({
    tenant_id: "tenant-1",
    tenant: "spoofed",
    provider: "md_integrations",
    key: "webhook_secret",
    value: "secret-value-1",
  }));
  const body = await response.json();

  assertEquals(response.status, 200);
  assertEquals(body.success, true);
  assertEquals(secretCalls.length, 1);
  assertEquals(secretCalls[0].kind, "single");
  assertEquals(secretCalls[0].tenant, "brello");
  assertEquals(secretCalls[0].provider, "mdi");
  assertEquals(secretCalls[0].key, "webhook_secret");
  assertEquals(secretCalls[0].value, "secret-value-1");
});

Deno.test("set-provider-rtdh-secret rejects admins outside the target tenant", async () => {
  const calls: unknown[][] = [];
  const secretCalls: Array<Record<string, unknown>> = [];
  const handler = createSetProviderRtdhSecretHandler({
    createClientImpl: createMockCreateClient({
      isSuper: false,
      hasMembership: false,
      tenantSlug: "brello",
      calls,
    }) as never,
    secretManager: {
      saveSecretsViaRtdh: (params) => {
        secretCalls.push({ kind: "bulk", ...params });
        throw new Error("should not be called");
      },
      saveSecretViaRtdh: (params) => {
        secretCalls.push({ kind: "single", ...params });
        throw new Error("should not be called");
      },
    },
  });

  const response = await handler(jsonRequest({
    tenant_id: "tenant-2",
    provider: "telegramd",
    key: "webhook_secret",
    value: "secret-value-1",
  }));
  const body = await response.json();

  assertEquals(response.status, 403);
  assertEquals(body.error, "Forbidden");
  assertEquals(secretCalls.length, 0);
});

Deno.test("set-provider-rtdh-secret maps bulk Stripe webhook secret to signing_secret", async () => {
  const calls: unknown[][] = [];
  const secretCalls: Array<Record<string, unknown>> = [];
  const handler = createSetProviderRtdhSecretHandler({
    createClientImpl: createMockCreateClient({
      isSuper: true,
      hasMembership: false,
      tenantSlug: "brello",
      calls,
    }) as never,
    secretManager: {
      saveSecretsViaRtdh: (params) => {
        secretCalls.push({ kind: "bulk", ...params });
        return Promise.resolve({
          success: true,
          tenant: params.tenant,
          provider: params.provider,
          context: params.context ?? null,
          saved: [],
          requestId: params.requestId,
        });
      },
      saveSecretViaRtdh: (params) => {
        secretCalls.push({ kind: "single", ...params });
        return Promise.resolve({
          success: true,
          tenant: params.tenant,
          provider: params.provider,
          context: params.context ?? null,
          saved: [],
          requestId: params.requestId,
        });
      },
    },
  });

  const response = await handler(jsonRequest({
    tenant_id: "tenant-1",
    provider: "stripe",
    secrets: {
      webhook_secret: "whsec_secret",
    },
  }));

  assertEquals(response.status, 200);
  assertEquals(secretCalls.length, 1);
  assertEquals(secretCalls[0].kind, "single");
  assertEquals(secretCalls[0].tenant, "brello");
  assertEquals(secretCalls[0].provider, "stripe");
  assertEquals(secretCalls[0].key, "signing_secret");
  assertEquals(secretCalls[0].value, "whsec_secret");
});

Deno.test("set-provider-rtdh-secret rejects MDI client credential updates", async () => {
  const calls: unknown[][] = [];
  const secretCalls: Array<Record<string, unknown>> = [];
  const handler = createSetProviderRtdhSecretHandler({
    createClientImpl: createMockCreateClient({
      isSuper: false,
      hasMembership: true,
      tenantSlug: "brello",
      calls,
    }) as never,
    secretManager: {
      saveSecretsViaRtdh: (params) => {
        secretCalls.push({ kind: "bulk", ...params });
        return Promise.resolve({
          success: true,
          tenant: params.tenant,
          provider: params.provider,
          context: params.context ?? null,
          saved: [],
          requestId: params.requestId,
        });
      },
      saveSecretViaRtdh: (params) => {
        secretCalls.push({ kind: "single", ...params });
        return Promise.resolve({
          success: true,
          tenant: params.tenant,
          provider: params.provider,
          context: params.context ?? null,
          saved: [],
          requestId: params.requestId,
        });
      },
    },
  });

  const response = await handler(jsonRequest({
    tenant_id: "tenant-1",
    provider: "md_integrations",
    secrets: {
      client_id: "mdi-client-1",
    },
  }));
  const body = await response.json();

  assertEquals(response.status, 400);
  assertEquals(
    body.error,
    "Unsupported RTDH secret key 'client_id' for provider 'mdi'",
  );
  assertEquals(secretCalls.length, 0);
});
