import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  generateOrderNumber,
  verifyStripeSignature,
} from "./helpers.ts";

Deno.test("verifyStripeSignature validates a correctly signed payload", async () => {
  const payload = '{"id":"evt_test"}';
  const secret = "whsec_test_secret";
  const now = 1700000000000;
  const timestamp = Math.floor(now / 1000);
  const sig = await computeHmacSha256Hex(secret, `${timestamp}.${payload}`);
  const header = `t=${timestamp},v1=${sig}`;

  assertEquals(await verifyStripeSignature(payload, header, secret, now), {
    valid: true,
  });
});

Deno.test("verifyStripeSignature rejects outdated signatures", async () => {
  const payload = "{}";
  const secret = "whsec_test_secret";
  const now = 1700000000000;
  const oldTimestamp = Math.floor((now - 301000) / 1000);
  const sig = await computeHmacSha256Hex(secret, `${oldTimestamp}.${payload}`);
  const header = `t=${oldTimestamp},v1=${sig}`;

  assertEquals(
    await verifyStripeSignature(payload, header, secret, now),
    { valid: false, error: "Timestamp outside tolerance window" },
  );
});

Deno.test("generateOrderNumber includes ORD prefix and uppercase segments", () => {
  const orderNumber = generateOrderNumber(1700000000000, () => 0.123456789);
  assertMatch(orderNumber, /^ORD-[0-9A-Z]+-[0-9A-Z]{4}$/);
});

async function computeHmacSha256Hex(
  secret: string,
  payload: string,
): Promise<string> {
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    encoder.encode(payload),
  );
  return Array.from(new Uint8Array(signature))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}
