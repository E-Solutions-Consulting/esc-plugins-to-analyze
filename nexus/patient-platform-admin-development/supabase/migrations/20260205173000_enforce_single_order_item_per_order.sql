-- Add a direct product link to orders and enforce single item per order
ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS product_id UUID;

ALTER TABLE public.orders
  ADD CONSTRAINT orders_product_id_fkey
  FOREIGN KEY (product_id)
  REFERENCES public.products(id)
  ON DELETE RESTRICT;

CREATE INDEX IF NOT EXISTS idx_orders_product_id ON public.orders(product_id);

COMMENT ON COLUMN public.orders.product_id IS 'Primary product for this order (single-item orders).';

-- Backfill order product from existing order_items (choose earliest item per order)
UPDATE public.orders o
SET product_id = oi.product_id
FROM (
  SELECT DISTINCT ON (order_id) order_id, product_id
  FROM public.order_items
  ORDER BY order_id, created_at ASC
) oi
WHERE oi.order_id = o.id
  AND o.product_id IS NULL;

-- Enforce a single order item per order
ALTER TABLE public.order_items
  ADD CONSTRAINT order_items_one_per_order UNIQUE (order_id);
