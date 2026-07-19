import { assertEquals, assertMatch } from "../_test/assert.ts";
import { getCheckoutSessionIdFromInvoice, getSubscriptionIdFromInvoice } from "./utils.ts";

Deno.test("getSubscriptionIdFromInvoice prefers invoice.subscription string", () => {
  const result = getSubscriptionIdFromInvoice({ subscription: "sub_123" });
  assertEquals(result, { id: "sub_123", source: "invoice.subscription" });
});

Deno.test("getSubscriptionIdFromInvoice uses invoice.subscription.id", () => {
  const result = getSubscriptionIdFromInvoice({ subscription: { id: "sub_456" } });
  assertEquals(result, { id: "sub_456", source: "invoice.subscription.id" });
});

Deno.test("getSubscriptionIdFromInvoice falls back to line parent subscription", () => {
  const result = getSubscriptionIdFromInvoice({
    lines: {
      data: [
        {
          parent: {
            subscription_item_details: {
              subscription: "sub_line_789",
            },
          },
        },
      ],
    },
  });
  assertEquals(result, {
    id: "sub_line_789",
    source: "lines[0].parent.subscription_item_details.subscription",
  });
});

Deno.test("getCheckoutSessionIdFromInvoice uses subscription_details metadata first", () => {
  const result = getCheckoutSessionIdFromInvoice({
    subscription_details: { metadata: { checkout_session_id: "cs_sub_1" } },
    lines: { data: [{ metadata: { checkout_session_id: "cs_line_1" } }] },
  });
  assertEquals(result, {
    id: "cs_sub_1",
    source: "subscription_details.metadata.checkout_session_id",
  });
});

Deno.test("getCheckoutSessionIdFromInvoice falls back to line metadata", () => {
  const result = getCheckoutSessionIdFromInvoice({
    lines: { data: [{ metadata: { checkout_session_id: "cs_line_2" } }] },
  });
  assertEquals(result, {
    id: "cs_line_2",
    source: "lines[0].metadata.checkout_session_id",
  });
});

Deno.test("getCheckoutSessionIdFromInvoice falls back to line price metadata", () => {
  const result = getCheckoutSessionIdFromInvoice({
    lines: {
      data: [{ price: { metadata: { checkout_session_id: "cs_price_3" } } }],
    },
  });
  assertEquals(result, {
    id: "cs_price_3",
    source: "lines[0].price.metadata.checkout_session_id",
  });
});

Deno.test("getCheckoutSessionIdFromInvoice falls back to invoice metadata", () => {
  const result = getCheckoutSessionIdFromInvoice({
    metadata: { checkout_session_id: "cs_invoice_4" },
  });
  assertEquals(result, {
    id: "cs_invoice_4",
    source: "invoice.metadata.checkout_session_id",
  });
});
