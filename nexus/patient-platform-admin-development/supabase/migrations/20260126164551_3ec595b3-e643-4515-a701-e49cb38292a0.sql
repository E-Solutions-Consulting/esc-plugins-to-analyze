-- Add auth_user_id column to patients table to link with Supabase Auth
ALTER TABLE public.patients 
ADD COLUMN auth_user_id UUID UNIQUE REFERENCES auth.users(id) ON DELETE SET NULL;

-- Create index for faster lookups
CREATE INDEX idx_patients_auth_user_id ON public.patients(auth_user_id) WHERE auth_user_id IS NOT NULL;

-- RLS policy for patients to view/update their own record
CREATE POLICY "Patients can view own record"
ON public.patients
FOR SELECT
USING (auth_user_id = auth.uid());

CREATE POLICY "Patients can update own record"
ON public.patients
FOR UPDATE
USING (auth_user_id = auth.uid())
WITH CHECK (auth_user_id = auth.uid());

-- Function to get patient by auth user id
CREATE OR REPLACE FUNCTION public.get_patient_by_auth_id(_auth_user_id uuid)
RETURNS uuid
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT id FROM public.patients WHERE auth_user_id = _auth_user_id LIMIT 1
$$;

-- Function to check if a user is a patient
CREATE OR REPLACE FUNCTION public.is_patient(_auth_user_id uuid)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.patients WHERE auth_user_id = _auth_user_id
  )
$$;