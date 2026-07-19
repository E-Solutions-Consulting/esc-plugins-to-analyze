import { assertEquals } from "../_test/assert.ts";
import {
  buildQaOrderAddressFields,
  resolveQaShippingAddress,
} from "./order-flow.ts";

Deno.test("QA address uses patient shipping details and copies a complete address", () => {
  const result = resolveQaShippingAddress({
    patient: {
      first_name: "Jane",
      last_name: "Doe",
      shipping_address_line1: "123 Main St",
      shipping_city: "Denver",
      shipping_state: "CO",
      shipping_postal_code: "80202",
      shipping_country: "US",
    },
  });

  assertEquals(result.missing, []);
  assertEquals(result.address?.line1, "123 Main St");
  assertEquals(result.address?.first_name, "Jane");
});

Deno.test("QA request address overrides the patient profile", () => {
  const result = resolveQaShippingAddress({
    requested: {
      first_name: "QA",
      last_name: "Buyer",
      line1: "456 Test Ave",
      city: "Austin",
      state: "TX",
      postal_code: "78701",
      country: "US",
    },
    patient: {},
  });

  assertEquals(result.address?.line1, "456 Test Ave");
  assertEquals(result.missing, []);
});

Deno.test("QA address reports incomplete profile fields", () => {
  const result = resolveQaShippingAddress({ patient: { first_name: "Jane" } });
  assertEquals(result.address, null);
  assertEquals(result.missing, [
    "last_name",
    "line1",
    "city",
    "state",
    "postal_code",
  ]);
});

Deno.test("QA order fields copy shipping into billing", () => {
  const fields = buildQaOrderAddressFields({
    first_name: "QA",
    last_name: "Buyer",
    company: null,
    line1: "123 Test St",
    line2: null,
    city: "Denver",
    state: "CO",
    postal_code: "80202",
    country: "US",
    instructions: null,
  });

  assertEquals(fields.billing_address_line1, fields.shipping_address_line1);
  assertEquals(fields.billing_city, fields.shipping_city);
  assertEquals(fields.billing_postal_code, fields.shipping_postal_code);
});
