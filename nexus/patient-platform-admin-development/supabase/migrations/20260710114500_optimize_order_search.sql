-- Support tenant admin order search:
--   order_number ILIKE '%term%'
--
-- Patient-name order search is handled by the patients trigram indexes.

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX IF NOT EXISTS idx_orders_order_number_trgm
  ON public.orders USING gin (order_number gin_trgm_ops)
  WHERE order_number IS NOT NULL;
