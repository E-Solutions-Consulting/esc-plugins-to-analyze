-- Track acceptance details for patient signup terms and conditions.
ALTER TABLE public.patients
ADD COLUMN IF NOT EXISTS terms_and_conditions_accepted_at TIMESTAMPTZ,
ADD COLUMN IF NOT EXISTS terms_and_conditions_accepted_content TEXT;

COMMENT ON COLUMN public.patients.terms_and_conditions_accepted_at IS
  'Timestamp when the patient accepted terms and conditions.';

COMMENT ON COLUMN public.patients.terms_and_conditions_accepted_content IS
  'Exact terms and conditions content accepted by the patient at signup.';
