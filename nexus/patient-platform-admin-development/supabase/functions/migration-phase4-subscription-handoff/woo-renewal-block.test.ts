import { assertEquals } from "../_test/assert.ts";
import { blockWooCommerceRenewal } from "./woo-renewal-block.ts";

function fakeFetch(
  responses: Array<{ status: number; body: unknown }>,
): typeof fetch {
  let call = 0;
  return (() => {
    const next = responses[call];
    call += 1;
    return Promise.resolve(
      new Response(JSON.stringify(next.body), { status: next.status }),
    );
  }) as typeof fetch;
}

Deno.test("blockWooCommerceRenewal updates the subscription status and reports success", async () => {
  const result = await blockWooCommerceRenewal({
    wcBaseUrl: "https://www.brellohealth.com",
    wcAuth: "test-auth",
    wooSubscriptionId: "757566",
    targetStatus: "pending-cancel",
    fetchImpl: fakeFetch([
      { status: 200, body: { status: "active" } },
      { status: 200, body: { status: "pending-cancel" } },
    ]),
  });

  assertEquals(result, {
    attempted: true,
    success: true,
    target_status: "pending-cancel",
    previous_status: "active",
    error: null,
  });
});

Deno.test("blockWooCommerceRenewal reports failure when the lookup request fails", async () => {
  const result = await blockWooCommerceRenewal({
    wcBaseUrl: "https://www.brellohealth.com",
    wcAuth: "test-auth",
    wooSubscriptionId: "757566",
    targetStatus: "pending-cancel",
    fetchImpl: fakeFetch([
      { status: 404, body: { message: "not found" } },
    ]),
  });

  assertEquals(result.attempted, true);
  assertEquals(result.success, false);
  assertEquals(result.previous_status, null);
  assertEquals(result.error, "woo_subscription_lookup_failed_404");
});

Deno.test("blockWooCommerceRenewal reports failure when the update request fails", async () => {
  const result = await blockWooCommerceRenewal({
    wcBaseUrl: "https://www.brellohealth.com",
    wcAuth: "test-auth",
    wooSubscriptionId: "757566",
    targetStatus: "pending-cancel",
    fetchImpl: fakeFetch([
      { status: 200, body: { status: "active" } },
      { status: 500, body: { message: "server error" } },
    ]),
  });

  assertEquals(result.attempted, true);
  assertEquals(result.success, false);
  assertEquals(result.previous_status, "active");
  assertEquals(result.error, "woo_subscription_update_failed_500");
});

Deno.test("blockWooCommerceRenewal reports failure when WooCommerce returns an unexpected status after update", async () => {
  const result = await blockWooCommerceRenewal({
    wcBaseUrl: "https://www.brellohealth.com",
    wcAuth: "test-auth",
    wooSubscriptionId: "757566",
    targetStatus: "pending-cancel",
    fetchImpl: fakeFetch([
      { status: 200, body: { status: "active" } },
      { status: 200, body: { status: "active" } },
    ]),
  });

  assertEquals(result.success, false);
  assertEquals(result.error, null);
  assertEquals(result.previous_status, "active");
});

Deno.test("blockWooCommerceRenewal trims a trailing slash from the base URL", async () => {
  const calls: string[] = [];
  const fetchImpl = ((input: string | URL) => {
    calls.push(String(input));
    return Promise.resolve(
      new Response(JSON.stringify({ status: "pending-cancel" }), {
        status: 200,
      }),
    );
  }) as typeof fetch;

  await blockWooCommerceRenewal({
    wcBaseUrl: "https://www.brellohealth.com/",
    wcAuth: "test-auth",
    wooSubscriptionId: "757566",
    targetStatus: "pending-cancel",
    fetchImpl,
  });

  assertEquals(
    calls[0],
    "https://www.brellohealth.com/wp-json/wc/v3/subscriptions/757566",
  );
});

Deno.test("blockWooCommerceRenewal reports failure when fetch throws", async () => {
  const result = await blockWooCommerceRenewal({
    wcBaseUrl: "https://www.brellohealth.com",
    wcAuth: "test-auth",
    wooSubscriptionId: "757566",
    targetStatus: "pending-cancel",
    fetchImpl: (() => {
      throw new Error("network down");
    }) as unknown as typeof fetch,
  });

  assertEquals(result.attempted, true);
  assertEquals(result.success, false);
  assertEquals(result.error, "network down");
});
