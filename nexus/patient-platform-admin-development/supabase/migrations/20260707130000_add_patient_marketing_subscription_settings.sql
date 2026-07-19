-- Store patient-level marketing subscription preferences captured during signup.

ALTER TABLE public.patients
  ADD COLUMN IF NOT EXISTS subscribed_to_email_marketing BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS subscribed_to_sms_marketing BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN public.patients.subscribed_to_email_marketing IS
  'Whether the patient opted in to receive marketing communications by email.';

COMMENT ON COLUMN public.patients.subscribed_to_sms_marketing IS
  'Whether the patient opted in to receive marketing communications by SMS.';
