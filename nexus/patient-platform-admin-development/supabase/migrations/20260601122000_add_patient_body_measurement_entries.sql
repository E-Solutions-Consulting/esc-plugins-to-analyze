-- Create table for patient body measurement tracking entries
CREATE TABLE IF NOT EXISTS public.patient_body_measurement_entries (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  chest_inches NUMERIC NOT NULL CHECK (chest_inches > 0),
  waist_inches NUMERIC NOT NULL CHECK (waist_inches > 0),
  hips_inches NUMERIC NOT NULL CHECK (hips_inches > 0),
  arms_inches NUMERIC NOT NULL CHECK (arms_inches > 0),
  measured_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_patient_body_measurement_entries_tenant_id
  ON public.patient_body_measurement_entries(tenant_id);

CREATE INDEX IF NOT EXISTS idx_patient_body_measurement_entries_patient_id
  ON public.patient_body_measurement_entries(patient_id);

CREATE INDEX IF NOT EXISTS idx_patient_body_measurement_entries_measured_at
  ON public.patient_body_measurement_entries(measured_at);

ALTER TABLE public.patient_body_measurement_entries ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_body_measurement_entries'
      AND policyname = 'Tenant admins can manage patient body measurement entries'
  ) THEN
    CREATE POLICY "Tenant admins can manage patient body measurement entries"
    ON public.patient_body_measurement_entries
    FOR ALL
    USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
    WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_body_measurement_entries'
      AND policyname = 'Patients can view their own body measurement entries'
  ) THEN
    CREATE POLICY "Patients can view their own body measurement entries"
    ON public.patient_body_measurement_entries
    FOR SELECT
    USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_body_measurement_entries'
      AND policyname = 'Patients can create their own body measurement entries'
  ) THEN
    CREATE POLICY "Patients can create their own body measurement entries"
    ON public.patient_body_measurement_entries
    FOR INSERT
    WITH CHECK (
      patient_id = public.get_patient_by_auth_id(auth.uid())
      AND tenant_id = (
        SELECT tenant_id FROM public.patients WHERE auth_user_id = auth.uid() LIMIT 1
      )
    );
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_body_measurement_entries'
      AND policyname = 'Patients can delete their own body measurement entries'
  ) THEN
    CREATE POLICY "Patients can delete their own body measurement entries"
    ON public.patient_body_measurement_entries
    FOR DELETE
    USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END
$$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_patient_body_measurement_entries_updated_at'
      AND tgrelid = 'public.patient_body_measurement_entries'::regclass
  ) THEN
    CREATE TRIGGER update_patient_body_measurement_entries_updated_at
      BEFORE UPDATE ON public.patient_body_measurement_entries
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END
$$;

COMMENT ON TABLE public.patient_body_measurement_entries IS 'Patient-reported body measurement tracking entries';
COMMENT ON COLUMN public.patient_body_measurement_entries.chest_inches IS 'Chest measurement value in inches';
COMMENT ON COLUMN public.patient_body_measurement_entries.waist_inches IS 'Waist measurement value in inches';
COMMENT ON COLUMN public.patient_body_measurement_entries.hips_inches IS 'Hips measurement value in inches';
COMMENT ON COLUMN public.patient_body_measurement_entries.arms_inches IS 'Arms measurement value in inches';
COMMENT ON COLUMN public.patient_body_measurement_entries.measured_at IS 'Date/time of the body measurement';
