import {
  extractPatientPlatformOrderId,
  extractWooCommerceCustomerId,
  extractWooCommerceOrderId,
} from "./order-id.ts";
import type { RtdhEventPayload } from "./validation.ts";

function payload(ids: Record<string, unknown> | null): RtdhEventPayload {
  return {
    master_order_id: "master::test",
    internal_tenant_id: "brello",
    source_systems: ["woocommerce"],
    updated_at: "2026-06-13T00:00:00.000Z",
    ids: ids ?? undefined,
    timeline: [],
  };
}

function payloadWithCustomer(
  customer: Record<string, unknown> | null,
): RtdhEventPayload {
  return {
    master_order_id: "master::test",
    internal_tenant_id: "brello",
    source_systems: ["woocommerce"],
    updated_at: "2026-06-13T00:00:00.000Z",
    customer: customer ?? undefined,
    timeline: [],
  };
}

Deno.test("extractPatientPlatformOrderId returns trimmed Patient Platform order id", () => {
  const result = extractPatientPlatformOrderId(
    payload({ patient_platform_order_id: " pp-order-123 " }),
  );

  if (result !== "pp-order-123") {
    throw new Error(`Expected pp-order-123, got ${result}`);
  }
});

Deno.test("extractWooCommerceOrderId prefers woocommerce_order_id", () => {
  const result = extractWooCommerceOrderId(
    payload({
      woocommerce_order_id: " 1001 ",
      woo_order_id: "1002",
      wc_order_id: "1003",
    }),
  );

  if (result !== "1001") {
    throw new Error(`Expected 1001, got ${result}`);
  }
});

Deno.test("extractWooCommerceOrderId supports legacy Woo id aliases", () => {
  const wooOrderId = extractWooCommerceOrderId(
    payload({ woo_order_id: "1002" }),
  );
  const wcOrderId = extractWooCommerceOrderId(
    payload({ wc_order_id: "1003" }),
  );

  if (wooOrderId !== "1002") {
    throw new Error(`Expected 1002, got ${wooOrderId}`);
  }
  if (wcOrderId !== "1003") {
    throw new Error(`Expected 1003, got ${wcOrderId}`);
  }
});

Deno.test("extractWooCommerceOrderId returns null when ids are missing", () => {
  const result = extractWooCommerceOrderId(payload(null));

  if (result !== null) {
    throw new Error(`Expected null, got ${result}`);
  }
});

Deno.test("extractWooCommerceCustomerId returns trimmed customer_id from the customer section", () => {
  const result = extractWooCommerceCustomerId(
    payloadWithCustomer({ customer_id: " 29323 " }),
  );

  if (result !== "29323") {
    throw new Error(`Expected 29323, got ${result}`);
  }
});

Deno.test("extractWooCommerceCustomerId returns null when customer section is missing", () => {
  const result = extractWooCommerceCustomerId(payloadWithCustomer(null));

  if (result !== null) {
    throw new Error(`Expected null, got ${result}`);
  }
});
