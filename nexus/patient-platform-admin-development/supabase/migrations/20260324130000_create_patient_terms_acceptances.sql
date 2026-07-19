-- Store accepted product terms per patient in a dedicated relational table.
CREATE TABLE IF NOT EXISTS public.patient_terms_acceptances (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE RESTRICT,
  accepted_at TIMESTAMPTZ NOT NULL,
  accepted_content TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_patient_terms_acceptances_tenant_id
  ON public.patient_terms_acceptances(tenant_id);

CREATE INDEX IF NOT EXISTS idx_patient_terms_acceptances_patient_id
  ON public.patient_terms_acceptances(patient_id);

CREATE INDEX IF NOT EXISTS idx_patient_terms_acceptances_product_id
  ON public.patient_terms_acceptances(product_id);

CREATE INDEX IF NOT EXISTS idx_patient_terms_acceptances_accepted_at
  ON public.patient_terms_acceptances(accepted_at DESC);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_terms_acceptances_patient_product_accepted_at
  ON public.patient_terms_acceptances(patient_id, product_id, accepted_at);

ALTER TABLE public.patient_terms_acceptances ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_terms_acceptances'
      AND policyname = 'Tenant admins can manage patient terms acceptances'
  ) THEN
    CREATE POLICY "Tenant admins can manage patient terms acceptances"
      ON public.patient_terms_acceptances
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_terms_acceptances'
      AND policyname = 'Patients can view their own terms acceptances'
  ) THEN
    CREATE POLICY "Patients can view their own terms acceptances"
      ON public.patient_terms_acceptances
      FOR SELECT
      USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_proc
    WHERE proname = 'update_updated_at_column'
      AND pg_function_is_visible(oid)
  ) AND NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_patient_terms_acceptances_updated_at'
      AND tgrelid = 'public.patient_terms_acceptances'::regclass
  ) THEN
    CREATE TRIGGER update_patient_terms_acceptances_updated_at
      BEFORE UPDATE ON public.patient_terms_acceptances
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

-- Best-effort backfill from legacy patient columns using the latest ordered product.
INSERT INTO public.patient_terms_acceptances (
  tenant_id,
  patient_id,
  product_id,
  accepted_at,
  accepted_content
)
SELECT
  p.tenant_id,
  p.id,
  o.product_id,
  p.terms_and_conditions_accepted_at,
  p.terms_and_conditions_accepted_content
FROM public.patients p
JOIN LATERAL (
  SELECT orders.product_id
  FROM public.orders
  WHERE orders.patient_id = p.id
    AND orders.product_id IS NOT NULL
  ORDER BY orders.created_at DESC
  LIMIT 1
) o ON TRUE
WHERE p.terms_and_conditions_accepted_at IS NOT NULL
ON CONFLICT (patient_id, product_id, accepted_at) DO NOTHING;

COMMENT ON TABLE public.patient_terms_acceptances IS
  'Historical terms-and-conditions acceptance snapshots by patient and product.';
