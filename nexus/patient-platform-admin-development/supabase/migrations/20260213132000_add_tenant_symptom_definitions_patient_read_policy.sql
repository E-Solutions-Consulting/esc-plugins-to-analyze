-- Allow patients to view symptom definitions for their tenant
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'tenant_symptom_definitions'
      AND policyname = 'Patients can view symptom definitions'
  ) THEN
    CREATE POLICY "Patients can view symptom definitions"
    ON public.tenant_symptom_definitions
    FOR SELECT
    USING (
      tenant_id = (
        SELECT tenant_id
        FROM public.patients
        WHERE auth_user_id = auth.uid()
        LIMIT 1
      )
    );
  END IF;
END $$;
