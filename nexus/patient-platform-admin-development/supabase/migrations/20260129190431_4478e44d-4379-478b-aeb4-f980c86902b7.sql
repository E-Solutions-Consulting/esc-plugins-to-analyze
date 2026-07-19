-- Create order_items table to track which products are in each order
CREATE TABLE public.order_items (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  order_id UUID NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE RESTRICT,
  quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
  unit_price_cents INTEGER NOT NULL DEFAULT 0,
  total_cents INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Create indexes for better query performance
CREATE INDEX idx_order_items_order_id ON public.order_items(order_id);
CREATE INDEX idx_order_items_product_id ON public.order_items(product_id);

-- Enable Row Level Security
ALTER TABLE public.order_items ENABLE ROW LEVEL SECURITY;

-- RLS policy: Access via order ownership (tenant admins can manage order items for their orders)
CREATE POLICY "Tenant admins can manage order items via order ownership"
ON public.order_items
FOR ALL
USING (
  order_id IN (
    SELECT id FROM public.orders
    WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
  )
);

-- RLS policy: Patients can view their own order items
CREATE POLICY "Patients can view their own order items"
ON public.order_items
FOR SELECT
USING (
  order_id IN (
    SELECT id FROM public.orders
    WHERE patient_id = get_patient_by_auth_id(auth.uid())
  )
);

-- Add comment for documentation
COMMENT ON TABLE public.order_items IS 'Line items for orders, linking orders to products with quantities and pricing';