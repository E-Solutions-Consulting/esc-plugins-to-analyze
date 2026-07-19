-- Add image_url column to products table
ALTER TABLE public.products ADD COLUMN image_url TEXT;

-- Create storage bucket for product images
INSERT INTO storage.buckets (id, name, public)
VALUES ('product-images', 'product-images', true)
ON CONFLICT (id) DO NOTHING;

-- RLS policy: Allow authenticated users to view all product images
CREATE POLICY "Public read access for product images"
ON storage.objects FOR SELECT
USING (bucket_id = 'product-images');

-- RLS policy: Allow tenant admins to upload product images (path must start with tenant_id)
CREATE POLICY "Tenant admins can upload product images"
ON storage.objects FOR INSERT
WITH CHECK (
  bucket_id = 'product-images' 
  AND EXISTS (
    SELECT 1 FROM public.get_user_tenant_ids(auth.uid()) AS tid
    WHERE tid::text = (storage.foldername(name))[1]
  )
);

-- RLS policy: Allow tenant admins to update their product images
CREATE POLICY "Tenant admins can update product images"
ON storage.objects FOR UPDATE
USING (
  bucket_id = 'product-images' 
  AND EXISTS (
    SELECT 1 FROM public.get_user_tenant_ids(auth.uid()) AS tid
    WHERE tid::text = (storage.foldername(name))[1]
  )
);

-- RLS policy: Allow tenant admins to delete their product images
CREATE POLICY "Tenant admins can delete product images"
ON storage.objects FOR DELETE
USING (
  bucket_id = 'product-images' 
  AND EXISTS (
    SELECT 1 FROM public.get_user_tenant_ids(auth.uid()) AS tid
    WHERE tid::text = (storage.foldername(name))[1]
  )
);