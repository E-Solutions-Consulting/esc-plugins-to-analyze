-- Patients can delete their own intake records
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'medication_shot_intakes'
      AND policyname = 'Patients can delete their own medication shot intakes'
  ) THEN
    CREATE POLICY "Patients can delete their own medication shot intakes"
    ON public.medication_shot_intakes
    FOR DELETE
    USING (patient_id = public.get_patient_by_auth_id(auth.uid()));
  END IF;
END $$;
