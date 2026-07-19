-- Add FAQs that can be managed per product and surfaced to patient-facing apps.
CREATE TABLE IF NOT EXISTS public.product_faqs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  display_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT product_faqs_question_not_blank CHECK (length(trim(question)) > 0),
  CONSTRAINT product_faqs_answer_not_blank CHECK (length(trim(answer)) > 0)
);

ALTER TABLE public.product_faqs ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_faqs'
      AND policyname = 'Access via product ownership for FAQs'
  ) THEN
    EXECUTE $policy$
      CREATE POLICY "Access via product ownership for FAQs"
      ON public.product_faqs
      FOR ALL
      USING (
        product_id IN (
          SELECT id
          FROM public.products
          WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
        )
      )
    $policy$;
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'product_faqs'
      AND policyname = 'Public can view FAQs for enabled products'
  ) THEN
    EXECUTE $policy$
      CREATE POLICY "Public can view FAQs for enabled products"
      ON public.product_faqs
      FOR SELECT
      USING (
        product_id IN (
          SELECT id
          FROM public.products
          WHERE is_enabled = true
        )
      )
    $policy$;
  END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_product_faqs_product_id
ON public.product_faqs(product_id);

CREATE INDEX IF NOT EXISTS idx_product_faqs_product_display_order
ON public.product_faqs(product_id, display_order, created_at);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_product_faqs_updated_at'
      AND tgrelid = 'public.product_faqs'::regclass
  ) THEN
    CREATE TRIGGER update_product_faqs_updated_at
    BEFORE UPDATE ON public.product_faqs
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END
$$;

COMMENT ON TABLE public.product_faqs IS
  'Frequently asked questions linked to products and exposed in patient-facing catalog endpoints.';
