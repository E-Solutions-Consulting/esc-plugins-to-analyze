-- Create junction table for product category assignments
CREATE TABLE public.product_category_assignments (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  category_id UUID NOT NULL REFERENCES public.product_categories(id) ON DELETE CASCADE,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  UNIQUE(product_id, category_id)
);

-- Enable RLS
ALTER TABLE public.product_category_assignments ENABLE ROW LEVEL SECURITY;

-- RLS: Access via product ownership (tenant admins can manage their product's categories)
CREATE POLICY "Access via product ownership"
ON public.product_category_assignments
FOR ALL
USING (
  product_id IN (
    SELECT id FROM public.products
    WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
  )
);

-- Indexes for faster lookups
CREATE INDEX idx_product_category_assignments_product_id 
ON public.product_category_assignments(product_id);

CREATE INDEX idx_product_category_assignments_category_id 
ON public.product_category_assignments(category_id);