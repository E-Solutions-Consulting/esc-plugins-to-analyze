import { assertEquals } from "../_test/assert.ts";
import { isOrderPaymentAuthorized } from "./order-payment-authorization.ts";

Deno.test("payment authorization accepts captured and zero-value orders", () => {
  assertEquals(
    isOrderPaymentAuthorized({ paidAt: "2026-07-14T10:00:00Z" }),
    true,
  );
  assertEquals(isOrderPaymentAuthorized({ totalCents: 0 }), true);
});

Deno.test("payment authorization accepts a manually authorized PaymentIntent", () => {
  assertEquals(
    isOrderPaymentAuthorized({
      totalCents: 49900,
      paymentStatuses: ["requires_capture"],
    }),
    true,
  );
});

Deno.test("payment authorization rejects pending and unpaid transactions", () => {
  assertEquals(
    isOrderPaymentAuthorized({
      totalCents: 49900,
      paymentStatuses: ["pending", "unpaid"],
    }),
    false,
  );
  assertEquals(
    isOrderPaymentAuthorized({
      totalCents: 49900,
      paymentStatuses: ["no_payment_required"],
    }),
    false,
  );
});
