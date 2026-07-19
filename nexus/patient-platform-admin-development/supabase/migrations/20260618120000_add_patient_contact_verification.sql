-- PP-566: post-payment account & contact validation.
--
-- The new checkout stage (after payment, before questionnaires) verifies the
-- patient's email via OTP so the provider managing the questionnaires receives a
-- validated email. Phone is reviewed/confirmed (no SMS verification today).
--
-- email_verified_at  - set when the patient completes email OTP verification.
--                      Cleared when the email is changed (must re-verify).
-- phone_confirmed_at  - set when the patient confirms their phone is correct
--                      (attestation, not an SMS-verified state). Cleared on change.

ALTER TABLE public.patients
  ADD COLUMN IF NOT EXISTS email_verified_at timestamptz NULL,
  ADD COLUMN IF NOT EXISTS phone_confirmed_at timestamptz NULL;

COMMENT ON COLUMN public.patients.email_verified_at IS
  'When the patient verified their email via OTP (PP-566 post-payment validation). Null = unverified; cleared on email change.';
COMMENT ON COLUMN public.patients.phone_confirmed_at IS
  'When the patient confirmed their phone is correct (attestation, not SMS-verified). Cleared on phone change.';
