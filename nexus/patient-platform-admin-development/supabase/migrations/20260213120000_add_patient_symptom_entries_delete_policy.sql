-- Allow patients to delete their own symptom entries
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_symptom_entries'
      AND policyname = 'Patients can delete their own symptom entries'
  ) THEN
    CREATE POLICY "Patients can delete their own symptom entries"
    ON public.patient_symptom_entries
    FOR DELETE
    USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;
