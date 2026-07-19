import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import {
  buildMdiCaseCancelPayload,
  buildMdiCaseCancelUrl,
  buildMdiCaseProcessingUrl,
  buildMdiCreateCasePayload,
  buildMdiCreatePatientPayload,
  cancelMdiCaseForLifecycle,
  createMdiOrderForLifecycle,
  requestMdiCaseProcessingForLifecycle,
} from "./mdi-helper.ts";

Deno.test("buildMdiCreatePatientPayload maps patient and shipping address fields", () => {
  const order = {
    id: "order-1",
    order_number: "ORD-001",
    tenant_id: "tenant-1",
    patient_id: "patient-1",
    status_id: "status-1",
    product_id: "product-1",
    shipping_first_name: "Jane",
    shipping_last_name: "Doe",
    shipping_address_line1: "123 Main St",
    shipping_address_line2: "Apt 4B",
    shipping_city: "Denver",
    shipping_state: "Colorado",
    shipping_postal_code: "80202",
    shipping_country: "US",
    provider_platform_integration_key: "md_integrations",
  };

  const patient = {
    id: "patient-1",
    first_name: "Jane",
    last_name: "Doe",
    email: "jane@example.com",
    phone: "(303) 555-1234",
    date_of_birth: "1990-01-15",
  };

  const payload = buildMdiCreatePatientPayload({ order, patient });

  assertEquals(payload.first_name, "Jane");
  assertEquals(payload.last_name, "Doe");
  assertEquals(payload.email, "jane@example.com");
  assertEquals(payload.phone_number, "(303) 555-1234");
  assertEquals(payload.is_email_enabled, true);

  const address = payload.address as Record<string, unknown>;
  assertEquals(address.address, "123 Main St");
  assertEquals(address.address2, "Apt 4B");
  assertEquals(address.city_name, "Denver");
  assertEquals(address.state_name, "Colorado");
  assertEquals(address.zip_code, "80202");

  assertEquals(payload.metadata, "patient-1");
});

Deno.test("buildMdiCreatePatientPayload sanitizes names for MDI validation", () => {
  const order = {
    id: "order-1",
    order_number: "ORD-001",
    tenant_id: "tenant-1",
    patient_id: "patient-1",
    status_id: "status-1",
    product_id: "product-1",
    shipping_first_name: "Calvin",
    shipping_last_name: "Lizotte",
    shipping_address_line1: "123 Main St",
    shipping_address_line2: null,
    shipping_city: "Denver",
    shipping_state: "Colorado",
    shipping_postal_code: "80202",
    shipping_country: "US",
    provider_platform_integration_key: "md_integrations",
  };

  const patient = {
    id: "patient-1",
    first_name: "Calvin",
    last_name: "C. Lizotte",
    email: "calvin@example.com",
    phone: null,
    date_of_birth: "1990-01-15",
  };

  const payload = buildMdiCreatePatientPayload({ order, patient });

  assertEquals(payload.first_name, "Calvin");
  assertEquals(payload.last_name, "Lizotte");
});

Deno.test("buildMdiCreatePatientPayload omits empty optional fields", () => {
  const order = {
    id: "order-2",
    order_number: "ORD-002",
    tenant_id: "tenant-1",
    patient_id: "patient-2",
    status_id: null,
    product_id: null,
    shipping_first_name: null,
    shipping_last_name: null,
    shipping_address_line1: null,
    shipping_address_line2: null,
    shipping_city: null,
    shipping_state: null,
    shipping_postal_code: null,
    shipping_country: null,
    provider_platform_integration_key: "md_integrations",
  };

  const patient = {
    id: "patient-2",
    first_name: "John",
    last_name: "Smith",
    email: "john@example.com",
    phone: null,
    date_of_birth: null,
  };

  const payload = buildMdiCreatePatientPayload({ order, patient });

  assertEquals(payload.first_name, "John");
  assertEquals(payload.last_name, "Smith");
  assertEquals(payload.phone_number, undefined);
  // Address with all-null fields becomes an empty object after compacting
  const address = payload.address as Record<string, unknown> | undefined;
  assertEquals(address, undefined, "fully-empty address should be omitted");
});

Deno.test("buildMdiCreateCasePayload builds case payload with offering", () => {
  const payload = buildMdiCreateCasePayload({
    mdiPatientId: "9b0c4571-f980-4fd4-bf9d-6de1ac52dc9d",
    orderNumber: "ORD-001",
    offeringId: "offering-abc-123",
  });

  assertEquals(payload.hold_status, true);
  assertEquals(payload.patient_id, "9b0c4571-f980-4fd4-bf9d-6de1ac52dc9d");
  assertEquals(payload.metadata, "Order Number ORD-001");
  assertEquals(payload.is_additional_approval_needed, true);
  assertEquals(payload.case_files, []);
  const offerings = payload.case_offerings as Array<Record<string, unknown>>;
  assertEquals(offerings.length, 1);
  assertEquals(offerings[0].offering_id, "offering-abc-123");
});

Deno.test("buildMdiCreateCasePayload builds case payload with multiple medication offerings", () => {
  const payload = buildMdiCreateCasePayload({
    mdiPatientId: "patient-xyz",
    orderNumber: "ORD-003",
    offeringIds: ["sermorelin-offering", "nad-offering"],
  });

  const offerings = payload.case_offerings as Array<Record<string, unknown>>;
  assertEquals(offerings, [
    { offering_id: "sermorelin-offering" },
    { offering_id: "nad-offering" },
  ]);
});

Deno.test("buildMdiCreateCasePayload omits offering when null", () => {
  const payload = buildMdiCreateCasePayload({
    mdiPatientId: "patient-xyz",
    orderNumber: "ORD-002",
    offeringId: null,
  });

  assertEquals(payload.patient_id, "patient-xyz");
  const offerings = payload.case_offerings as Array<Record<string, unknown>>;
  assertEquals(offerings.length, 0);
});

Deno.test("buildMdiCaseProcessingUrl appends encoded processing path", () => {
  assertEquals(
    buildMdiCaseProcessingUrl("https://api.mdintegrations.com/", "case 1/2"),
    "https://api.mdintegrations.com/v1/partner/cases/case%201%2F2/processing",
  );
});

Deno.test("buildMdiCaseCancelUrl appends encoded cancel path", () => {
  assertEquals(
    buildMdiCaseCancelUrl("https://api.mdintegrations.com/", "case 1/2"),
    "https://api.mdintegrations.com/v1/partner/cases/case%201%2F2/cancel",
  );
});

Deno.test("buildMdiCaseCancelPayload uses provider-facing patient cancellation reason", () => {
  assertEquals(buildMdiCaseCancelPayload(), {
    reason:
      "Patient requested cancellation before provider review was completed.",
  });
});

Deno.test("createMdiOrderForLifecycle reuses existing MDI case for duplicate triggers", async () => {
  const fromCalls: string[] = [];
  const supabase = {
    from(table: string) {
      fromCalls.push(table);
      return {
        select() {
          return this;
        },
        eq() {
          return this;
        },
        order() {
          return Promise.resolve({
            data: [
              {
                id: "link-1",
                metadata: null,
                provider_order_id: "case-existing-123",
                tenant_integration_id: "tenant-integration-1",
                tenant_integrations: {
                  id: "tenant-integration-1",
                  tenant_id: "tenant-1",
                  integration_key: "md_integrations",
                  is_enabled: true,
                  settings: {},
                },
              },
            ],
            error: null,
          });
        },
      };
    },
  };

  const result = await createMdiOrderForLifecycle({
    supabase,
    requestId: "request-1",
    order: {
      id: "order-1",
      order_number: "ORD-001",
      tenant_id: "tenant-1",
      patient_id: "patient-1",
      status_id: "status-1",
      product_id: "product-1",
      shipping_first_name: "Jane",
      shipping_last_name: "Doe",
      shipping_address_line1: "123 Main St",
      shipping_address_line2: null,
      shipping_city: "Denver",
      shipping_state: "Colorado",
      shipping_postal_code: "80202",
      shipping_country: "US",
      provider_platform_integration_key: "md_integrations",
    },
  });

  assertEquals(result, {
    created: true,
    providerName: "MDI",
    message: "MDI case already exists for this order",
    externalOrderId: "case-existing-123",
  });
  assertEquals(fromCalls, ["order_provider_platform_links"]);
});

Deno.test("cancelMdiCaseForLifecycle posts cancel request and records marker", async () => {
  const fetchCalls: Array<{ url: string; init: RequestInit | undefined }> = [];
  const originalFetch = globalThis.fetch;
  let updatedMetadata: Record<string, unknown> | null = null;

  globalThis.fetch = ((input: URL | RequestInfo, init?: RequestInit) => {
    fetchCalls.push({ url: String(input), init });
    return Promise.resolve(
      new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );
  }) as typeof fetch;

  try {
    const supabase = {
      from(table: string) {
        if (table === "tenant_integration_auth_tokens") {
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
                  access_token: "mdi-token-1",
                  expires_at: "2099-01-01T00:00:00.000Z",
                },
                error: null,
              });
            },
          };
        }

        assertEquals(table, "order_provider_platform_links");
        return {
          select() {
            return this;
          },
          eq() {
            return this;
          },
          order() {
            return Promise.resolve({
              data: [
                {
                  id: "link-1",
                  metadata: null,
                  provider_order_id: "case-existing-123",
                  tenant_integration_id: "tenant-integration-1",
                  tenant_integrations: {
                    id: "tenant-integration-1",
                    tenant_id: "tenant-1",
                    integration_key: "md_integrations",
                    is_enabled: true,
                    settings: {
                      backend_url: "https://api.mdintegrations.com",
                      client_id: "client-1",
                      client_secret: "secret-1",
                    },
                  },
                },
              ],
              error: null,
            });
          },
          update(payload: { metadata?: Record<string, unknown> }) {
            updatedMetadata = payload.metadata ?? null;
            return {
              error: null,
              eq() {
                return this;
              },
            };
          },
        };
      },
    };

    const result = await cancelMdiCaseForLifecycle({
      supabase,
      requestId: "request-1",
      order: {
        id: "order-1",
        order_number: "ORD-001",
        tenant_id: "tenant-1",
        patient_id: "patient-1",
        status_id: "status-1",
        product_id: "product-1",
        shipping_first_name: "Jane",
        shipping_last_name: "Doe",
        shipping_address_line1: "123 Main St",
        shipping_address_line2: null,
        shipping_city: "Denver",
        shipping_state: "Colorado",
        shipping_postal_code: "80202",
        shipping_country: "US",
        provider_platform_integration_key: "md_integrations",
      },
    });

    assertEquals(result, {
      applicable: true,
      cancelled: true,
      providerName: "MDI",
      message: "MDI case cancelled successfully",
      externalOrderId: "case-existing-123",
    });
    assertEquals(fetchCalls.length, 1);
    assertEquals(
      fetchCalls[0].url,
      "https://api.mdintegrations.com/v1/partner/cases/case-existing-123/cancel",
    );
    assertEquals(fetchCalls[0].init?.method, "POST");
    assertEquals(
      (fetchCalls[0].init?.headers as Record<string, string>).Authorization,
      "Bearer mdi-token-1",
    );
    assertEquals(
      JSON.parse(String(fetchCalls[0].init?.body)),
      buildMdiCaseCancelPayload(),
    );
    const metadata = updatedMetadata as unknown as Record<string, unknown>;
    assertEquals(metadata.provider, "MDI");
    assertEquals(
      metadata.mdi_cancellation_reason,
      "Patient requested cancellation before provider review was completed.",
    );
    assertEquals(typeof metadata.mdi_cancelled_at, "string");
  } finally {
    globalThis.fetch = originalFetch;
  }
});

Deno.test("requestMdiCaseProcessingForLifecycle skips when already requested", async () => {
  const fromCalls: string[] = [];
  const supabase = {
    from(table: string) {
      fromCalls.push(table);
      return {
        select() {
          return this;
        },
        eq() {
          return this;
        },
        order() {
          return Promise.resolve({
            data: [
              {
                id: "link-1",
                metadata: {
                  mdi_processing_requested_at: "2026-05-28T12:00:00.000Z",
                },
                provider_order_id: "case-existing-123",
                tenant_integration_id: "tenant-integration-1",
                tenant_integrations: {
                  id: "tenant-integration-1",
                  tenant_id: "tenant-1",
                  integration_key: "md_integrations",
                  is_enabled: true,
                  settings: {
                    backend_url: "https://api.mdintegrations.com",
                  },
                },
              },
            ],
            error: null,
          });
        },
      };
    },
  };

  const result = await requestMdiCaseProcessingForLifecycle({
    supabase,
    requestId: "request-1",
    order: {
      id: "order-1",
      order_number: "ORD-001",
      tenant_id: "tenant-1",
      patient_id: "patient-1",
      status_id: "status-1",
      product_id: "product-1",
      shipping_first_name: "Jane",
      shipping_last_name: "Doe",
      shipping_address_line1: "123 Main St",
      shipping_address_line2: null,
      shipping_city: "Denver",
      shipping_state: "Colorado",
      shipping_postal_code: "80202",
      shipping_country: "US",
      provider_platform_integration_key: "md_integrations",
    },
  });

  assertEquals(result, {
    applicable: true,
    processingRequested: true,
    alreadyRequested: true,
    providerName: "MDI",
    message: "MDI case processing already requested for this order",
    externalOrderId: "case-existing-123",
  });
  assertEquals(fromCalls, ["order_provider_platform_links"]);
});
