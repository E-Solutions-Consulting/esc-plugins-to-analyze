-- Ensure the correctly spelled subscription cancellation-pending state exists.
--
-- An earlier migration filename used "cancelation"; some environments may have
-- already applied an older enum value before the migration body was corrected.
-- Use a fresh migration version so deployed databases receive the new enum label.
ALTER TYPE public.subscription_status ADD VALUE IF NOT EXISTS 'pending_cancellation';
