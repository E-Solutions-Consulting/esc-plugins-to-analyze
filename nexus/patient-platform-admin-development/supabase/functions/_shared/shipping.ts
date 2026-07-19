export interface TenantEasyPostShippingIntegration {
  integrationKey: "easypost";
  apiKey: string;
  carrier: string | null;
}

export interface EasyPostTrackingDetails {
  trackingUrl: string | null;
  status: string | null;
  updatedAt: string | null;
}

function getTrimmedString(value: unknown): string | null {
  if (typeof value !== "string") return null;

  const normalizedValue = value.trim();
  return normalizedValue.length > 0 ? normalizedValue : null;
}

function toSettingsRecord(value: unknown): Record<string, unknown> {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return {};
  }

  return value as Record<string, unknown>;
}

function buildEasyPostBasicAuthorization(apiKey: string): string {
  return `Basic ${btoa(`${apiKey}:`)}`;
}

export async function resolveTenantEasyPostShippingIntegration(
  // deno-lint-ignore no-explicit-any
  supabaseClient: any,
  tenantId: string,
): Promise<TenantEasyPostShippingIntegration | null> {
  const { data: platformIntegration, error: platformError } =
    await supabaseClient
      .from("platform_integrations")
      .select("key")
      .eq("category", "shipping")
      .eq("is_active", true)
      .eq("key", "easypost")
      .maybeSingle();

  if (platformError) {
    throw new Error(
      `shipping_integration_platform_lookup_failed:${platformError.message}`,
    );
  }

  if (!platformIntegration) {
    return null;
  }

  const { data: tenantIntegration, error: tenantError } = await supabaseClient
    .from("tenant_integrations")
    .select("integration_key, settings")
    .eq("tenant_id", tenantId)
    .eq("integration_key", "easypost")
    .eq("is_enabled", true)
    .maybeSingle();

  if (tenantError) {
    throw new Error(
      `shipping_integration_tenant_lookup_failed:${tenantError.message}`,
    );
  }

  if (!tenantIntegration) {
    return null;
  }

  const settings = toSettingsRecord(tenantIntegration.settings);
  const apiKey = getTrimmedString(settings.api_key);

  if (!apiKey) {
    return null;
  }

  return {
    integrationKey: "easypost",
    apiKey,
    carrier: getTrimmedString(settings.carrier),
  };
}

export async function getTrackingDetailsFromEasyPost(params: {
  apiKey: string;
  trackingNumber: string;
  carrier?: string | null;
  fetchImpl?: typeof fetch;
}): Promise<EasyPostTrackingDetails | null> {
  const trackingNumber = getTrimmedString(params.trackingNumber);
  const apiKey = getTrimmedString(params.apiKey);

  if (!trackingNumber || !apiKey) {
    return null;
  }

  const payload: Record<string, unknown> = {
    tracker: {
      tracking_code: trackingNumber,
    },
  };
  const carrier = getTrimmedString(params.carrier);
  if (carrier) {
    (payload.tracker as Record<string, unknown>).carrier = carrier;
  }

  const fetchImpl = params.fetchImpl ?? fetch;
  const response = await fetchImpl("https://api.easypost.com/v2/trackers", {
    method: "POST",
    headers: {
      Authorization: buildEasyPostBasicAuthorization(apiKey),
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const responseText = await response.text();
    throw new Error(
      `easypost_tracker_lookup_failed:${response.status}:${
        responseText.slice(0, 300)
      }`,
    );
  }

  const responseBody = await response.json();
  const trackingUrl = getTrimmedString(
    responseBody && typeof responseBody === "object"
      ? (responseBody as Record<string, unknown>).public_url
      : null,
  );
  const status = getTrimmedString(
    responseBody && typeof responseBody === "object"
      ? (responseBody as Record<string, unknown>).status
      : null,
  );
  const updatedAt = getTrimmedString(
    responseBody && typeof responseBody === "object"
      ? (responseBody as Record<string, unknown>).updated_at
      : null,
  );

  return {
    trackingUrl,
    status,
    updatedAt,
  };
}

export async function getTrackingUrlFromEasyPost(params: {
  apiKey: string;
  trackingNumber: string;
  carrier?: string | null;
  fetchImpl?: typeof fetch;
}): Promise<string | null> {
  const trackingDetails = await getTrackingDetailsFromEasyPost(params);
  return trackingDetails?.trackingUrl ?? null;
}
