-- Allow public read access to enabled products for patient-api
CREATE POLICY "Public can view enabled products"
ON public.products
FOR SELECT
USING (is_enabled = true);

-- Allow public read access to product category assignments
CREATE POLICY "Public can view product category assignments"
ON public.product_category_assignments
FOR SELECT
USING (
  product_id IN (
    SELECT id FROM products WHERE is_enabled = true
  )
);

-- Allow public read access to active product categories
CREATE POLICY "Public can view active categories"
ON public.product_categories
FOR SELECT
USING (is_active = true);

-- Allow public read access to product medications for enabled products
CREATE POLICY "Public can view product medications"
ON public.product_medications
FOR SELECT
USING (
  product_id IN (
    SELECT id FROM products WHERE is_enabled = true
  )
);

-- Allow public read access to enabled medications linked to enabled products
CREATE POLICY "Public can view medications via products"
ON public.medications
FOR SELECT
USING (
  id IN (
    SELECT pm.medication_id FROM product_medications pm
    JOIN products p ON p.id = pm.product_id
    WHERE p.is_enabled = true
  )
);

-- Allow public read access to tenant branding for active tenants
CREATE POLICY "Public can view tenant branding"
ON public.tenant_branding
FOR SELECT
USING (
  tenant_id IN (
    SELECT id FROM tenants WHERE status = 'active'
  )
);

-- Allow public read access to questionnaire templates linked to enabled products
CREATE POLICY "Public can view product questionnaire links"
ON public.product_questionnaire_links
FOR SELECT
USING (
  product_id IN (
    SELECT id FROM products WHERE is_enabled = true
  )
);

-- Allow public read access to active questionnaire templates (for patient-api)
CREATE POLICY "Public can view active questionnaires"
ON public.questionnaire_templates
FOR SELECT
USING (is_active = true);