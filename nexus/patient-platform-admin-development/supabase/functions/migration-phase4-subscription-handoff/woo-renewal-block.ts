export interface WooRenewalBlockResult {
  attempted: boolean;
  success: boolean;
  target_status: string | null;
  previous_status: string | null;
  error: string | null;
}

function asString(value: unknown): string | null {
  if (value === undefined || value === null) return null;
  const normalized = String(value).trim();
  return normalized === "" ? null : normalized;
}

// Only call this when execute_woo_renewal_block=true is explicitly passed,
// separately from the woo_renewal_blocking_confirmed assertion flag in
// index.ts. This changes a real customer's WooCommerce subscription status
// via the WooCommerce REST API, so it must never run as a side effect of
// any other flag, and must never guess the target status on its own.
export async function blockWooCommerceRenewal(params: {
  wcBaseUrl: string;
  wcAuth: string;
  wooSubscriptionId: string;
  targetStatus: string;
  fetchImpl?: typeof fetch;
}): Promise<WooRenewalBlockResult> {
  const doFetch = params.fetchImpl ?? fetch;
  const url = `${
    params.wcBaseUrl.replace(/\/$/, "")
  }/wp-json/wc/v3/subscriptions/${params.wooSubscriptionId}`;

  try {
    const getResponse = await doFetch(url, {
      headers: { Authorization: `Basic ${params.wcAuth}` },
    });
    if (!getResponse.ok) {
      return {
        attempted: true,
        success: false,
        target_status: params.targetStatus,
        previous_status: null,
        error: `woo_subscription_lookup_failed_${getResponse.status}`,
      };
    }
    const current = await getResponse.json() as { status?: string };
    const previousStatus = asString(current?.status);

    const updateResponse = await doFetch(url, {
      method: "PUT",
      headers: {
        Authorization: `Basic ${params.wcAuth}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ status: params.targetStatus }),
    });
    if (!updateResponse.ok) {
      return {
        attempted: true,
        success: false,
        target_status: params.targetStatus,
        previous_status: previousStatus,
        error: `woo_subscription_update_failed_${updateResponse.status}`,
      };
    }
    const updated = await updateResponse.json() as { status?: string };

    return {
      attempted: true,
      success: asString(updated?.status) === params.targetStatus,
      target_status: params.targetStatus,
      previous_status: previousStatus,
      error: null,
    };
  } catch (error) {
    return {
      attempted: true,
      success: false,
      target_status: params.targetStatus,
      previous_status: null,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}
