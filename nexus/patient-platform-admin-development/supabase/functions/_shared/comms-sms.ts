// Shared SMS sender for Communications Automations.
// Sends via the tenant's Twilio integration (stored in tenant_integrations,
// integration_key='twilio', settings { account_sid, auth_token, from_number }) —
// mirroring how Resend credentials are stored per tenant.

// deno-lint-ignore no-explicit-any
type DB = any;

/** Send an SMS via the tenant's Twilio integration. Returns the Twilio message SID. */
export async function sendSmsViaTenant(
  db: DB,
  tenantId: string,
  to: string,
  body: string,
): Promise<string | null> {
  const { data: integ } = await db
    .from("tenant_integrations")
    .select("settings")
    .eq("tenant_id", tenantId)
    .eq("integration_key", "twilio")
    .eq("is_enabled", true)
    .maybeSingle();
  if (!integ?.settings) throw new Error("twilio_not_configured");
  const { account_sid, auth_token, from_number } = integ.settings as Record<string, string>;
  if (!account_sid || !auth_token || !from_number) throw new Error("twilio_incomplete");

  const params = new URLSearchParams({ To: to, From: from_number, Body: body });
  const res = await fetch(
    `https://api.twilio.com/2010-04-01/Accounts/${account_sid}/Messages.json`,
    {
      method: "POST",
      headers: {
        Authorization: `Basic ${btoa(`${account_sid}:${auth_token}`)}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params.toString(),
    },
  );
  if (!res.ok) throw new Error(`twilio_send_failed:${res.status}:${await res.text()}`);
  const json = await res.json();
  return json.sid ?? null;
}
