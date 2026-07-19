-- Legacy columns replaced by public.patient_terms_acceptances table.
ALTER TABLE public.patients
DROP COLUMN IF EXISTS terms_and_conditions_accepted_at,
DROP COLUMN IF EXISTS terms_and_conditions_accepted_content;
