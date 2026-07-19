import { assertEquals } from "../_test/assert.ts";
import { buildTelegraClientAuthUrl } from "../_shared/telegra-auth.ts";
import {
  areTelegraQuestionnairesCompletedAndValid,
  buildTelegraCancelOrderUrl,
  buildTelegraCreateOrderPayload,
  buildTelegraLeaveWaitingRoomUrl,
  buildTelegraOrdersUrl,
  buildTelegraQuestionnaireInstanceUrl,
  buildTelegraSendToPharmacyRecipientsUrl,
  createTelegraOrderForLifecycle,
  extractTelegraProviderOrderId,
  extractTelegraProviderPatientId,
  extractTelegraQuestionnaireInstanceIds,
  extractTelegraQuestionnaireStatus,
  extractTelegraQuestionnaireValid,
  leaveTelegraWaitingRoomForLifecycle,
} from "./telegra-helper.ts";

Deno.test("buildTelegraOrdersUrl appends orders path once", () => {
  assertEquals(
    buildTelegraOrdersUrl("https://api.telegramd.example.com/"),
    "https://api.telegramd.example.com/orders",
  );
  assertEquals(
    buildTelegraOrdersUrl("https://api.telegramd.example.com"),
    "https://api.telegramd.example.com/orders",
  );
});

Deno.test("buildTelegraSendToPharmacyRecipientsUrl appends sendToPharmacyRecipients action path once", () => {
  assertEquals(
    buildTelegraSendToPharmacyRecipientsUrl(
      "https://api.telegramd.example.com/",
    ),
    "https://api.telegramd.example.com/orders/actions/sendToPharmacyRecipients",
  );
  assertEquals(
    buildTelegraSendToPharmacyRecipientsUrl(
      "https://api.telegramd.example.com",
    ),
    "https://api.telegramd.example.com/orders/actions/sendToPharmacyRecipients",
  );
});

Deno.test("buildTelegraCancelOrderUrl appends cancel action path once", () => {
  assertEquals(
    buildTelegraCancelOrderUrl("https://api.telegramd.example.com/"),
    "https://api.telegramd.example.com/orders/{orderId}/actions/cancel",
  );
  assertEquals(
    buildTelegraCancelOrderUrl("https://api.telegramd.example.com"),
    "https://api.telegramd.example.com/orders/{orderId}/actions/cancel",
  );
});

Deno.test("buildTelegraLeaveWaitingRoomUrl appends leaveWaitingRoom action path once", () => {
  assertEquals(
    buildTelegraLeaveWaitingRoomUrl("https://api.telegramd.example.com/"),
    "https://api.telegramd.example.com/orders/{orderId}/actions/leaveWaitingRoom",
  );
  assertEquals(
    buildTelegraLeaveWaitingRoomUrl("https://api.telegramd.example.com"),
    "https://api.telegramd.example.com/orders/{orderId}/actions/leaveWaitingRoom",
  );
});

Deno.test("buildTelegraQuestionnaireInstanceUrl appends questionnaire instance path once", () => {
  assertEquals(
    buildTelegraQuestionnaireInstanceUrl(
      "https://api.telegramd.example.com/",
      "qi-123",
    ),
    "https://api.telegramd.example.com/questionnaireInstances/qi-123",
  );
});

Deno.test("buildTelegraClientAuthUrl appends auth client path once", () => {
  assertEquals(
    buildTelegraClientAuthUrl("https://api.telegramd.example.com/"),
    "https://api.telegramd.example.com/auth/client",
  );
  assertEquals(
    buildTelegraClientAuthUrl("https://api.telegramd.example.com"),
    "https://api.telegramd.example.com/auth/client",
  );
});

Deno.test("buildTelegraCreateOrderPayload uses the nested Telegra address schema", () => {
  const payload = buildTelegraCreateOrderPayload({
    order: {
      id: "order-1",
      order_number: "ORD-100",
      tenant_id: "tenant-1",
      patient_id: "patient-1",
      status_id: "status-1",
      product_id: "product-1",
      provider_platform_integration_key: "telegramd",
      shipping_first_name: "Jane",
      shipping_last_name: "Doe",
      shipping_address_line1: "123 Main St",
      shipping_address_line2: "Apt 4",
      shipping_city: "Austin",
      shipping_state: "TX",
      shipping_postal_code: "78701",
      shipping_country: "US",
      billing_first_name: "Jane",
      billing_last_name: "Doe",
      billing_address_line1: "123 Billing St",
      billing_address_line2: null,
      billing_city: "Austin",
      billing_state: "TX",
      billing_postal_code: "78702",
      billing_country: "US",
    },
    patient: {
      id: "patient-1",
      first_name: "Jane",
      last_name: "Doe",
      email: "jane@example.com",
      phone: "+15551234567",
      date_of_birth: "1990-01-01",
    },
    providerProductVariationSku: "TELEGRA-PROD-1",
    projectId: "project-tenant-1",
  });
  assertEquals(payload, {
    projectId: "project-tenant-1",
    project: "project-tenant-1",
    orderNumber: "ORD-100",
    externalIdentifier: "ORD-100",
    productVariations: [
      {
        productVariation: "TELEGRA-PROD-1",
        quantity: 1,
      },
    ],
    patient: {
      firstName: "Jane",
      lastName: "Doe",
      email: "jane@example.com",
      phone: "+15551234567",
      dateOfBirth: "1990-01-01",
    },
    address: {
      billing: {
        address1: "123 Billing St",
        city: "Austin",
        state: "TX",
        zipcode: "78702",
      },
      shipping: {
        address1: "123 Main St",
        address2: "Apt 4",
        city: "Austin",
        state: "TX",
        zipcode: "78701",
      },
    },
  });
});

Deno.test("createTelegraOrderForLifecycle fails before calling Telegra when Project ID is missing", async () => {
  const originalFetch = globalThis.fetch;
  let fetchCalled = false;

  globalThis.fetch = (() => {
    fetchCalled = true;
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

  const supabase = {
    from(table: string) {
      if (table === "order_provider_platform_links") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          order() {
            return Promise.resolve({ data: [], error: null });
          },
        };
      }

      if (table === "product_provider_platforms") {
        let eqCount = 0;
        return {
          select() {
            return this;
          },
          eq() {
            eqCount += 1;
            if (eqCount >= 2) {
              return Promise.resolve({
                data: [{
                  id: "assignment-1",
                  provider_product_variation_sku: "TELEGRA-SKU-1",
                  tenant_integration_id: "tenant-integration-1",
                  tenant_integrations: {
                    id: "tenant-integration-1",
                    tenant_id: "tenant-1",
                    integration_key: "telegramd",
                    is_enabled: true,
                    settings: {
                      url: "https://api.telegramd.example.com",
                      access_token: "telegra-token-1",
                      project_id: "   ",
                    },
                  },
                }],
                error: null,
              });
            }
            return this;
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    const result = await createTelegraOrderForLifecycle({
      supabase,
      requestId: "request-1",
      order: {
        id: "order-1",
        order_number: "ORD-001",
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        status_id: "status-1",
        product_id: "product-1",
        provider_platform_integration_key: "telegramd",
        shipping_first_name: "Jane",
        shipping_last_name: "Doe",
        shipping_address_line1: "123 Main St",
        shipping_address_line2: null,
        shipping_city: "Austin",
        shipping_state: "TX",
        shipping_postal_code: "78701",
        shipping_country: "US",
        billing_first_name: "Jane",
        billing_last_name: "Doe",
        billing_address_line1: "123 Billing St",
        billing_address_line2: null,
        billing_city: "Austin",
        billing_state: "TX",
        billing_postal_code: "78702",
        billing_country: "US",
      },
    });

    assertEquals(result, {
      created: false,
      providerName: "TelegraMD",
      message: "Telegra integration is missing Project ID configuration",
      externalOrderId: null,
    });
    assertEquals(fetchCalled, false);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("leaveTelegraWaitingRoomForLifecycle posts to Telegra and marks metadata", async () => {
  const originalFetch = globalThis.fetch;
  const fetchCalls: Array<{ url: string; method: string | undefined }> = [];
  const providerLinkUpdates: Record<string, unknown>[] = [];

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      method: init?.method,
    });
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

  const supabase = {
    from(table: string) {
      if (table === "order_provider_platform_links") {
        let updatePayload: Record<string, unknown> | null = null;
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          order() {
            return Promise.resolve({
              data: [{
                id: "link-1",
                provider_order_id: "order::telegra-order-1",
                tenant_integration_id: "tenant-integration-1",
                metadata: { provider: "TelegraMD" },
                tenant_integrations: {
                  id: "tenant-integration-1",
                  tenant_id: "tenant-1",
                  integration_key: "telegramd",
                  is_enabled: true,
                  settings: {
                    url: "https://api.telegramd.example.com",
                    access_token: "telegra-token-1",
                  },
                },
              }],
              error: null,
            });
          },
          update(payload: Record<string, unknown>) {
            updatePayload = payload;
            return this;
          },
          then(resolve: (value: { error: null }) => void) {
            if (updatePayload) {
              providerLinkUpdates.push(updatePayload);
            }
            resolve({ error: null });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    const result = await leaveTelegraWaitingRoomForLifecycle({
      supabase,
      requestId: "request-1",
      order: {
        id: "order-1",
        order_number: "ORD-001",
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        status_id: "status-1",
        product_id: "product-1",
        provider_platform_integration_key: "telegramd",
        shipping_first_name: "Jane",
        shipping_last_name: "Doe",
        shipping_address_line1: "123 Main St",
        shipping_address_line2: null,
        shipping_city: "Austin",
        shipping_state: "TX",
        shipping_postal_code: "78701",
        shipping_country: "US",
        billing_first_name: "Jane",
        billing_last_name: "Doe",
        billing_address_line1: "123 Billing St",
        billing_address_line2: null,
        billing_city: "Austin",
        billing_state: "TX",
        billing_postal_code: "78702",
        billing_country: "US",
      },
    });

    assertEquals(result.applicable, true);
    assertEquals(result.triggered, true);
    assertEquals(result.alreadyTriggered, false);
    assertEquals(result.externalOrderId, "order::telegra-order-1");
    assertEquals(fetchCalls.length, 1);
    assertEquals(fetchCalls[0].method, "POST");
    const requestUrl = new URL(fetchCalls[0].url);
    assertEquals(
      requestUrl.origin + requestUrl.pathname,
      "https://api.telegramd.example.com/orders/order%3A%3Atelegra-order-1/actions/leaveWaitingRoom",
    );
    assertEquals(
      typeof requestUrl.searchParams.get("request_timestamp"),
      "string",
    );
    assertEquals(providerLinkUpdates.length, 1);
    const metadata = providerLinkUpdates[0].metadata as Record<
      string,
      unknown
    >;
    assertEquals(metadata.provider, "TelegraMD");
    assertEquals(
      typeof metadata.telegra_leave_waiting_room_requested_at,
      "string",
    );
    assertEquals(
      metadata.telegra_leave_waiting_room_request_id,
      "request-1",
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("createTelegraOrderForLifecycle fails without provider patient id and skips RTDH dispatch", async () => {
  const originalFetch = globalThis.fetch;
  const originalConsoleInfo = console.info;
  const fetchCalls: Array<{ url: string; body: Record<string, unknown> }> = [];
  const infoLogs: Array<{ message: unknown; details: unknown }> = [];
  const upserts: Array<{ table: string; payload: Record<string, unknown> }> =
    [];

  console.info = ((message: unknown, details?: unknown) => {
    infoLogs.push({ message, details });
  }) as typeof console.info;

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({
      url: String(input),
      body: JSON.parse(String(init?.body)) as Record<string, unknown>,
    });
    return Promise.resolve(
      new Response(JSON.stringify({ id: "order::telegra-order-1" }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );
  }) as typeof fetch;

  const supabase = {
    rpc(functionName: string) {
      if (functionName === "claim_order_provider_platform_creation") {
        return Promise.resolve({
          data: [{
            claimed: true,
            provider_order_id: null,
            in_progress: false,
            link_id: "link-1",
            message: "Provider order creation claimed",
          }],
          error: null,
        });
      }
      throw new Error(`Unexpected RPC ${functionName}`);
    },
    from(table: string) {
      if (table === "order_provider_platform_links") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          order() {
            return Promise.resolve({ data: [], error: null });
          },
          upsert(payload: Record<string, unknown>) {
            upserts.push({ table, payload });
            return Promise.resolve({ error: null });
          },
        };
      }

      if (table === "product_provider_platforms") {
        let eqCount = 0;
        return {
          select() {
            return this;
          },
          eq() {
            eqCount += 1;
            if (eqCount >= 2) {
              return Promise.resolve({
                data: [
                  {
                    id: "assignment-1",
                    provider_product_variation_sku: "TELEGRA-SKU-1",
                    tenant_integration_id: "tenant-integration-1",
                    tenant_integrations: {
                      id: "tenant-integration-1",
                      tenant_id: "tenant-1",
                      integration_key: "telegramd",
                      is_enabled: true,
                      settings: {
                        url: "https://api.telegramd.example.com",
                        access_token: "telegra-token-1",
                        project_id: "project-tenant-1",
                      },
                    },
                  },
                ],
                error: null,
              });
            }
            return this;
          },
        };
      }

      if (table === "patients") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: {
                id: "patient-1",
                first_name: "Jane",
                last_name: "Doe",
                email: "jane@example.com",
                phone: "+15551234567",
                date_of_birth: "1990-01-01",
              },
              error: null,
            });
          },
        };
      }

      if (table === "orders") {
        let eqCount = 0;
        return {
          update() {
            return this;
          },
          eq() {
            eqCount += 1;
            if (eqCount >= 2) {
              return Promise.resolve({ error: null });
            }
            return this;
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    const result = await createTelegraOrderForLifecycle({
      supabase,
      requestId: "request-1",
      order: {
        id: "order-1",
        order_number: "ORD-001",
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        status_id: "status-1",
        product_id: "product-1",
        provider_platform_integration_key: "telegramd",
        shipping_first_name: "Jane",
        shipping_last_name: "Doe",
        shipping_address_line1: "123 Main St",
        shipping_address_line2: null,
        shipping_city: "Austin",
        shipping_state: "TX",
        shipping_postal_code: "78701",
        shipping_country: "US",
        billing_first_name: "Jane",
        billing_last_name: "Doe",
        billing_address_line1: "123 Billing St",
        billing_address_line2: null,
        billing_city: "Austin",
        billing_state: "TX",
        billing_postal_code: "78702",
        billing_country: "US",
      },
    });

    assertEquals(result, {
      created: false,
      providerName: "TelegraMD",
      message:
        "Telegra order creation succeeded but no provider patient id was returned",
      externalOrderId: "order::telegra-order-1",
    });
    assertEquals(fetchCalls.length, 1);
    const requestUrl = new URL(fetchCalls[0].url);
    assertEquals(
      requestUrl.origin + requestUrl.pathname,
      "https://api.telegramd.example.com/orders",
    );
    assertEquals(
      typeof requestUrl.searchParams.get("request_timestamp"),
      "string",
    );
    assertEquals(fetchCalls[0].body.projectId, "project-tenant-1");
    assertEquals(fetchCalls[0].body.project, "project-tenant-1");
    assertEquals(upserts.length, 1);
    assertEquals(upserts[0].table, "order_provider_platform_links");
    assertEquals(
      upserts[0].payload.provider_order_id,
      "order::telegra-order-1",
    );
    const requestParameterLog = infoLogs.find((log) =>
      log.message === "Telegra create order request parameters"
    );
    const requestParameterDetails = requestParameterLog?.details as
      | Record<string, unknown>
      | undefined;
    assertEquals(requestParameterDetails?.method, "POST");
    assertEquals(requestParameterDetails?.orderId, "order-1");
    assertEquals(requestParameterDetails?.tenantId, "tenant-1");
    assertEquals(
      requestParameterDetails?.tenantIntegrationId,
      "tenant-integration-1",
    );
    assertEquals(
      (requestParameterDetails?.headers as Record<string, unknown>)
        ?.Authorization,
      "Bearer <redacted>",
    );
    assertEquals(requestParameterDetails?.parameters, fetchCalls[0].body);
  } finally {
    globalThis.fetch = originalFetch;
    console.info = originalConsoleInfo;
  }
});

Deno.test("createTelegraOrderForLifecycle skips Telegra when provider creation is already claimed", async () => {
  const originalFetch = globalThis.fetch;
  let fetchCalled = false;

  globalThis.fetch = (() => {
    fetchCalled = true;
    return Promise.resolve(new Response("{}", { status: 200 }));
  }) as typeof fetch;

  const supabase = {
    rpc(functionName: string) {
      if (functionName === "claim_order_provider_platform_creation") {
        return Promise.resolve({
          data: [{
            claimed: false,
            provider_order_id: null,
            in_progress: true,
            link_id: "link-1",
            message: "Provider order creation is already in progress",
          }],
          error: null,
        });
      }
      throw new Error(`Unexpected RPC ${functionName}`);
    },
    from(table: string) {
      if (table === "order_provider_platform_links") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          order() {
            return Promise.resolve({
              data: [{
                id: "link-1",
                provider_order_id: null,
                tenant_integration_id: "tenant-integration-1",
                metadata: {},
                tenant_integrations: {
                  id: "tenant-integration-1",
                  tenant_id: "tenant-1",
                  integration_key: "telegramd",
                  is_enabled: true,
                  settings: {
                    url: "https://api.telegramd.example.com",
                    access_token: "telegra-token-1",
                  },
                },
              }],
              error: null,
            });
          },
        };
      }

      if (table === "product_provider_platforms") {
        let eqCount = 0;
        return {
          select() {
            return this;
          },
          eq() {
            eqCount += 1;
            if (eqCount >= 2) {
              return Promise.resolve({
                data: [{
                  id: "assignment-1",
                  provider_product_variation_sku: "TELEGRA-SKU-1",
                  tenant_integration_id: "tenant-integration-1",
                  tenant_integrations: {
                    id: "tenant-integration-1",
                    tenant_id: "tenant-1",
                    integration_key: "telegramd",
                    is_enabled: true,
                    settings: {
                      url: "https://api.telegramd.example.com",
                      access_token: "telegra-token-1",
                      project_id: "project-tenant-1",
                    },
                  },
                }],
                error: null,
              });
            }
            return this;
          },
        };
      }

      if (table === "patients") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: {
                id: "patient-1",
                first_name: "Jane",
                last_name: "Doe",
                email: "jane@example.com",
                phone: "+15551234567",
                date_of_birth: "1990-01-01",
              },
              error: null,
            });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    const result = await createTelegraOrderForLifecycle({
      supabase,
      requestId: "request-1",
      order: {
        id: "order-1",
        order_number: "ORD-001",
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        status_id: "status-1",
        product_id: "product-1",
        provider_platform_integration_key: "telegramd",
        shipping_first_name: "Jane",
        shipping_last_name: "Doe",
        shipping_address_line1: "123 Main St",
        shipping_address_line2: null,
        shipping_city: "Austin",
        shipping_state: "TX",
        shipping_postal_code: "78701",
        shipping_country: "US",
        billing_first_name: "Jane",
        billing_last_name: "Doe",
        billing_address_line1: "123 Billing St",
        billing_address_line2: null,
        billing_city: "Austin",
        billing_state: "TX",
        billing_postal_code: "78702",
        billing_country: "US",
      },
    });

    assertEquals(result, {
      created: true,
      providerName: "TelegraMD",
      message: "Provider order creation is already in progress",
      externalOrderId: null,
    });
    assertEquals(fetchCalled, false);
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("createTelegraOrderForLifecycle marks claim failed when Telegra request throws", async () => {
  const originalFetch = globalThis.fetch;
  const providerLinkUpdates: Record<string, unknown>[] = [];

  globalThis.fetch = (() => {
    return Promise.reject(new Error("network timeout"));
  }) as typeof fetch;

  const supabase = {
    rpc(functionName: string) {
      if (functionName === "claim_order_provider_platform_creation") {
        return Promise.resolve({
          data: [{
            claimed: true,
            provider_order_id: null,
            in_progress: false,
            link_id: "link-1",
            message: "Provider order creation claimed",
          }],
          error: null,
        });
      }
      throw new Error(`Unexpected RPC ${functionName}`);
    },
    from(table: string) {
      if (table === "order_provider_platform_links") {
        let selectedColumns = "";
        let updatePayload: Record<string, unknown> | null = null;
        return {
          select(columns?: string) {
            selectedColumns = columns || "";
            return this;
          },
          eq() {
            return this;
          },
          order() {
            return Promise.resolve({
              data: [{
                id: "link-1",
                provider_order_id: null,
                tenant_integration_id: "tenant-integration-1",
                metadata: {},
                tenant_integrations: {
                  id: "tenant-integration-1",
                  tenant_id: "tenant-1",
                  integration_key: "telegramd",
                  is_enabled: true,
                  settings: {
                    url: "https://api.telegramd.example.com",
                    access_token: "telegra-token-1",
                  },
                },
              }],
              error: null,
            });
          },
          maybeSingle() {
            if (selectedColumns === "metadata") {
              return Promise.resolve({ data: { metadata: {} }, error: null });
            }
            throw new Error(
              `Unexpected maybeSingle columns ${selectedColumns}`,
            );
          },
          update(payload: Record<string, unknown>) {
            updatePayload = payload;
            return this;
          },
          then(resolve: (value: { error: null }) => void) {
            if (updatePayload) {
              providerLinkUpdates.push(updatePayload);
            }
            resolve({ error: null });
          },
        };
      }

      if (table === "product_provider_platforms") {
        let eqCount = 0;
        return {
          select() {
            return this;
          },
          eq() {
            eqCount += 1;
            if (eqCount >= 2) {
              return Promise.resolve({
                data: [{
                  id: "assignment-1",
                  provider_product_variation_sku: "TELEGRA-SKU-1",
                  tenant_integration_id: "tenant-integration-1",
                  tenant_integrations: {
                    id: "tenant-integration-1",
                    tenant_id: "tenant-1",
                    integration_key: "telegramd",
                    is_enabled: true,
                    settings: {
                      url: "https://api.telegramd.example.com",
                      access_token: "telegra-token-1",
                      project_id: "project-tenant-1",
                    },
                  },
                }],
                error: null,
              });
            }
            return this;
          },
        };
      }

      if (table === "patients") {
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          maybeSingle() {
            return Promise.resolve({
              data: {
                id: "patient-1",
                first_name: "Jane",
                last_name: "Doe",
                email: "jane@example.com",
                phone: "+15551234567",
                date_of_birth: "1990-01-01",
              },
              error: null,
            });
          },
        };
      }

      throw new Error(`Unexpected table ${table}`);
    },
  };

  try {
    const result = await createTelegraOrderForLifecycle({
      supabase,
      requestId: "request-1",
      order: {
        id: "order-1",
        order_number: "ORD-001",
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        status_id: "status-1",
        product_id: "product-1",
        provider_platform_integration_key: "telegramd",
        shipping_first_name: "Jane",
        shipping_last_name: "Doe",
        shipping_address_line1: "123 Main St",
        shipping_address_line2: null,
        shipping_city: "Austin",
        shipping_state: "TX",
        shipping_postal_code: "78701",
        shipping_country: "US",
        billing_first_name: "Jane",
        billing_last_name: "Doe",
        billing_address_line1: "123 Billing St",
        billing_address_line2: null,
        billing_city: "Austin",
        billing_state: "TX",
        billing_postal_code: "78702",
        billing_country: "US",
      },
    });

    assertEquals(result, {
      created: false,
      providerName: "TelegraMD",
      message: "Telegra order creation failed before response: network timeout",
      externalOrderId: null,
    });
    const failedMetadata = providerLinkUpdates[0].metadata as Record<
      string,
      unknown
    >;
    assertEquals(failedMetadata.provider_order_creation_status, "failed");
    assertEquals(
      failedMetadata.provider_order_creation_error,
      "network timeout",
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("extractTelegraProviderPatientId reads patient.id from Telegra success responses", () => {
  assertEquals(
    extractTelegraProviderPatientId({
      id: "provider-order-1",
      patient: {
        id: "provider-patient-1",
      },
    }),
    "provider-patient-1",
  );

  assertEquals(
    extractTelegraProviderPatientId({
      data: {
        patient: {
          id: "provider-patient-2",
        },
      },
    }),
    "provider-patient-2",
  );
});

Deno.test("extractTelegraProviderOrderId reads the provider order id from Telegra success responses", () => {
  assertEquals(
    extractTelegraProviderOrderId({
      id: "order::provider-order-1",
    }),
    "order::provider-order-1",
  );

  assertEquals(
    extractTelegraProviderOrderId({
      data: {
        order: {
          id: "order::provider-order-2",
        },
      },
    }),
    "order::provider-order-2",
  );

  assertEquals(
    extractTelegraProviderOrderId({
      order: {
        _id: "order::provider-order-3",
      },
    }),
    "order::provider-order-3",
  );
});

Deno.test("extractTelegraProviderOrderId ignores Telegra ids that do not start with order::", () => {
  assertEquals(
    extractTelegraProviderOrderId({
      id: "provider-order-1",
    }),
    null,
  );

  assertEquals(
    extractTelegraProviderOrderId({
      data: {
        order: {
          id: "provider-order-2",
        },
      },
    }),
    null,
  );
});

Deno.test("extractTelegraQuestionnaireInstanceIds reads questionnaireInstances[].id from Telegra success responses", () => {
  assertEquals(
    extractTelegraQuestionnaireInstanceIds({
      questionnaireInstances: [
        { id: "qi-1" },
        { id: "qi-2" },
        { name: "missing-id" },
      ],
    }),
    ["qi-1", "qi-2"],
  );

  assertEquals(
    extractTelegraQuestionnaireInstanceIds({
      data: {
        questionnaireInstances: [
          { id: "qi-3" },
        ],
      },
    }),
    ["qi-3"],
  );
});

Deno.test("extractTelegraQuestionnaireStatus reads the questionnaire status", () => {
  assertEquals(
    extractTelegraQuestionnaireStatus({
      status: "completed",
      valid: true,
    }),
    "completed",
  );
  assertEquals(extractTelegraQuestionnaireStatus({ status: 1 }), null);
});

Deno.test("extractTelegraQuestionnaireValid reads the questionnaire valid flag", () => {
  assertEquals(
    extractTelegraQuestionnaireValid({
      status: "completed",
      valid: true,
    }),
    true,
  );
  assertEquals(extractTelegraQuestionnaireValid({ valid: "true" }), null);
});

Deno.test("areTelegraQuestionnairesCompletedAndValid requires valid=true for every questionnaire", () => {
  assertEquals(
    areTelegraQuestionnairesCompletedAndValid([
      { status: "completed", valid: true },
      { status: "in_progress", valid: true },
    ]),
    true,
  );
  assertEquals(
    areTelegraQuestionnairesCompletedAndValid([
      { status: "draft", valid: true },
      { status: "completed", valid: false },
    ]),
    false,
  );
});
