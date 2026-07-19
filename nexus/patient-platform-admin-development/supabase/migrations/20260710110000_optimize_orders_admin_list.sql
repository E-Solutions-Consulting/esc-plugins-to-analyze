-- Optimize the tenant admin orders list:
--   WHERE tenant_id = ?
--   optional equality filters
--   ORDER BY created_at DESC
--   LIMIT/OFFSET pagination

CREATE INDEX IF NOT EXISTS idx_orders_tenant_created_at_desc
  ON public.orders (tenant_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_orders_tenant_status_created_at_desc
  ON public.orders (tenant_id, status_id, created_at DESC)
  WHERE status_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_orders_tenant_product_created_at_desc
  ON public.orders (tenant_id, product_id, created_at DESC)
  WHERE product_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_orders_tenant_provider_platform_created_at_desc
  ON public.orders (tenant_id, provider_platform_integration_key, created_at DESC)
  WHERE provider_platform_integration_key IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_orders_tenant_shipping_state_created_at_desc
  ON public.orders (tenant_id, shipping_state, created_at DESC)
  WHERE shipping_state IS NOT NULL;
