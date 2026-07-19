-- Create table for product categories
CREATE TABLE public.product_categories (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  name TEXT NOT NULL,
  key TEXT NOT NULL UNIQUE,
  description TEXT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Enable RLS
ALTER TABLE public.product_categories ENABLE ROW LEVEL SECURITY;

-- RLS: Authenticated users can view categories
CREATE POLICY "Authenticated users can view categories"
ON public.product_categories
FOR SELECT
USING (auth.uid() IS NOT NULL);

-- RLS: Superadmin can manage categories
CREATE POLICY "Superadmin can manage categories"
ON public.product_categories
FOR ALL
USING (is_platform_superadmin(auth.uid()));

-- Add updated_at trigger
CREATE TRIGGER update_product_categories_updated_at
BEFORE UPDATE ON public.product_categories
FOR EACH ROW
EXECUTE FUNCTION public.update_updated_at_column();

-- Index for ordering
CREATE INDEX idx_product_categories_display_order ON public.product_categories(display_order);