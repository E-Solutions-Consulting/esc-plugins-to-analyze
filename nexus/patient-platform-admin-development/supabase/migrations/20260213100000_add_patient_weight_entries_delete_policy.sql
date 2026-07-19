-- Allow patients to delete their own weight entries
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_weight_entries'
      AND policyname = 'Patients can delete their own weight entries'
  ) THEN
    CREATE POLICY "Patients can delete their own weight entries"
    ON public.patient_weight_entries
    FOR DELETE
    USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;
