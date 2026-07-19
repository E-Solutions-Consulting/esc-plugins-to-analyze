-- Allow patients to delete their own energy entries
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'patient_energy_entries'
      AND policyname = 'Patients can delete their own energy entries'
  ) THEN
    CREATE POLICY "Patients can delete their own energy entries"
    ON public.patient_energy_entries
    FOR DELETE
    USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;
