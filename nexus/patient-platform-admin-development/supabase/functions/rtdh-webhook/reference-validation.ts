type TenantIntegrationReferenceResult = {
  data?: unknown;
  error?: unknown;
};

type TenantIntegrationFilterBuilder = {
  eq(column: string, value: string): TenantIntegrationFilterBuilder;
  limit(count: number): TenantIntegrationFilterBuilder;
  maybeSingle(): PromiseLike<TenantIntegrationReferenceResult>;
};

type TenantIntegrationQueryBuilder = {
  select(columns: string): TenantIntegrationFilterBuilder;
};

type TenantIntegrationClient = {
  from(
    table: "tenant_integrations" | "order_payment_provider_transactions",
  ): TenantIntegrationQueryBuilder;
};

export function resolveTenantIntegrationReference(
  supabase: unknown,
  tenantId: string | null,
  providerName: string | null,
): PromiseLike<TenantIntegrationReferenceResult> | null {
  if (!tenantId || !providerName) {
    return null;
  }

  const client = supabase as TenantIntegrationClient;

  return client
    .from("tenant_integrations")
    .select("id")
    .eq("tenant_id", tenantId)
    .eq("integration_key", providerName)
    .maybeSingle();
}

export function resolvePaymentTransactionReference(
  supabase: unknown,
  tenantId: string | null,
  column: string,
  value: string | null,
): PromiseLike<TenantIntegrationReferenceResult> | null {
  if (!tenantId || !value) {
    return null;
  }

  const client = supabase as TenantIntegrationClient;

  return client
    .from("order_payment_provider_transactions")
    .select("id, provider_customer_id")
    .eq("tenant_id", tenantId)
    .eq(column, value)
    .limit(1)
    .maybeSingle();
}
