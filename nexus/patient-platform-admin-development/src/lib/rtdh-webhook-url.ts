/**
 * Builds the per-tenant INCOMING webhook URL that a clinical provider (TelegraMD, MD Integrations)
 * must be configured to call. This is the RTDH receiver endpoint — the canonical inbound path
 * (provider → RTDH → Patient Platform). It is NOT the legacy PP `telegra-webhook` edge function.
 *
 * Shape: `<rtdhBase>/<provider-receiver-fn>?tenant=<slug>`
 *   e.g. https://us-central1-allia-rt-data-hub-dev.cloudfunctions.net/telegra-webhook-receiver?tenant=allia
 *
 * `?tenant=<slug>` is REQUIRED by the receiver.
 */

// Per-provider RTDH receiver Cloud Function name.
const PROVIDER_RECEIVER_FN: Record<string, string> = {
  telegramd: "telegra-webhook-receiver",
  md_integrations: "md-integrations-webhook-receiver",
};

/**
 * Build the incoming webhook URL for a provider + tenant, or null if inputs are insufficient
 * (missing base URL, unknown provider, or missing tenant slug).
 */
export function buildProviderIncomingWebhookUrl(params: {
  rtdhBaseUrl: string | undefined | null;
  providerKey: string;
  tenantSlug: string | undefined | null;
}): string | null {
  const { rtdhBaseUrl, providerKey, tenantSlug } = params;
  const fnBase = PROVIDER_RECEIVER_FN[providerKey];
  if (!fnBase || !rtdhBaseUrl || !tenantSlug) return null;

  const base = rtdhBaseUrl.replace(/\/+$/, ""); // strip trailing slashes
  const slug = encodeURIComponent(tenantSlug);
  return `${base}/${fnBase}?tenant=${slug}`;
}
