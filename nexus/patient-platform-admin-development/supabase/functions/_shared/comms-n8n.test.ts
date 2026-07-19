import { assertEquals } from "../_test/assert.ts";
import { toTestWebhookUrl } from "./comms-n8n.ts";

// Queue-mode n8n serves TEST webhooks on the MAIN/editor host only; the
// dedicated webhook host answers /webhook-test/ with a bare "Cannot POST"
// (verified against dev). The test URL must therefore be built on the editor
// base, not the webhook base — otherwise "Listen for test event" never
// receives anything.
Deno.test("toTestWebhookUrl targets the editor host, not the webhook host", () => {
  const prod = "https://n8n-dev-webhooks.alliahealth.co/webhook/comms-0e4b97e0-d5e6633d";
  assertEquals(
    toTestWebhookUrl(prod, "comms-0e4b97e0-d5e6633d"),
    "https://n8n-dev.alliahealth.co/webhook-test/comms-0e4b97e0-d5e6633d",
  );
});

Deno.test("toTestWebhookUrl derives the path from the production URL when unset", () => {
  const prod = "https://n8n-dev-webhooks.alliahealth.co/webhook/comms-abc";
  assertEquals(
    toTestWebhookUrl(prod, null),
    "https://n8n-dev.alliahealth.co/webhook-test/comms-abc",
  );
});
