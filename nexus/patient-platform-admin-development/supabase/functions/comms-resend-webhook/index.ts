// comms-resend-webhook — receive Resend delivery events and update comms_run_steps.
//
// Resend posts events like email.delivered / email.opened / email.bounced with the
// email id under data.email_id. We match the run step by provider_message_id (the
// id captured at send time) and stamp delivery_status + the matching timestamp.
//
// Verification: Resend signs via Svix headers (svix-id, svix-timestamp,
// svix-signature). When COMMS_RESEND_WEBHOOK_SECRET is set we verify the HMAC;
// when unset we accept (so the feature works before the secret is provisioned) but
// log a warning. Service-role; verify_jwt=false.

import { createClient } from "npm:@supabase/supabase-js@2.49.2";

// deno-lint-ignore no-explicit-any
type DB = any;

// Map Resend event types -> our delivery_status + which timestamp column to set.
const EVENT_MAP: Record<string, { status: string; column?: string }> = {
  "email.delivered": { status: "delivered", column: "delivered_at" },
  "email.opened": { status: "opened", column: "opened_at" },
  "email.clicked": { status: "clicked" },
  "email.bounced": { status: "bounced", column: "bounced_at" },
  "email.complained": { status: "complained" },
  "email.delivery_delayed": { status: "delivery_delayed" },
};

/** Verify a Svix-style signature (v1,<base64hmac>) over `${id}.${ts}.${body}`. */
async function verifySvix(
  secret: string,
  svixId: string,
  svixTs: string,
  svixSig: string,
  body: string,
): Promise<boolean> {
  // Svix secrets are prefixed "whsec_" and base64-encoded.
  const raw = secret.startsWith("whsec_") ? secret.slice(6) : secret;
  let keyBytes: Uint8Array;
  try {
    const bin = atob(raw);
    keyBytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) keyBytes[i] = bin.charCodeAt(i);
  } catch {
    keyBytes = new TextEncoder().encode(raw);
  }
  const key = await crypto.subtle.importKey(
    "raw",
    keyBytes.buffer as ArrayBuffer,
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signed = `${svixId}.${svixTs}.${body}`;
  const mac = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(signed));
  const expected = btoa(String.fromCharCode(...new Uint8Array(mac)));
  // svix-signature is space-separated "v1,<sig> v1,<sig2>"; match any.
  return svixSig.split(" ").some((part) => {
    const [, sig] = part.split(",");
    return sig === expected;
  });
}

Deno.serve(async (req) => {
  if (req.method !== "POST") {
    return new Response(JSON.stringify({ error: "Method not allowed" }), { status: 405 });
  }

  const body = await req.text();
  const secret = Deno.env.get("COMMS_RESEND_WEBHOOK_SECRET");
  if (secret) {
    const svixId = req.headers.get("svix-id") ?? "";
    const svixTs = req.headers.get("svix-timestamp") ?? "";
    const svixSig = req.headers.get("svix-signature") ?? "";
    const ok = svixId && svixTs && svixSig &&
      (await verifySvix(secret, svixId, svixTs, svixSig, body));
    if (!ok) {
      return new Response(JSON.stringify({ error: "Invalid signature" }), { status: 401 });
    }
  } else {
    console.warn("comms-resend-webhook: COMMS_RESEND_WEBHOOK_SECRET unset — accepting unverified");
  }

  const url = Deno.env.get("SUPABASE_URL")!;
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const db: DB = createClient(url, serviceKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });

  try {
    const event = JSON.parse(body);
    const type = String(event.type ?? "");
    const mapped = EVENT_MAP[type];
    const emailId = event?.data?.email_id ?? event?.data?.id;
    if (!mapped || !emailId) {
      return new Response(JSON.stringify({ ok: true, ignored: true }), { status: 200 });
    }

    const patch: Record<string, unknown> = { delivery_status: mapped.status };
    if (mapped.column) patch[mapped.column] = new Date().toISOString();

    const { data, error } = await db
      .from("comms_run_steps")
      .update(patch)
      .eq("provider_message_id", String(emailId))
      .select("id");
    if (error) throw error;

    return new Response(
      JSON.stringify({ ok: true, type, matched: (data ?? []).length }),
      { status: 200, headers: { "Content-Type": "application/json" } },
    );
  } catch (e) {
    console.error("comms-resend-webhook error:", e);
    return new Response(
      JSON.stringify({ error: e instanceof Error ? e.message : "error" }),
      { status: 500 },
    );
  }
});
