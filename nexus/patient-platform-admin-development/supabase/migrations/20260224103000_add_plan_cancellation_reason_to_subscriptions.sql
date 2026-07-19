-- Add cancellation reason fields to plans (subscriptions) for patient-triggered plan cancellation.

ALTER TABLE public.subscriptions
  ADD COLUMN IF NOT EXISTS cancellation_reason TEXT,
  ADD COLUMN IF NOT EXISTS cancellation_reason_details TEXT;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'subscriptions_cancellation_reason_check'
      AND conrelid = 'public.subscriptions'::regclass
  ) THEN
    ALTER TABLE public.subscriptions
      ADD CONSTRAINT subscriptions_cancellation_reason_check
      CHECK (
        cancellation_reason IS NULL
        OR cancellation_reason IN (
          'too_expensive',
          'side_effects',
          'not_effective',
          'no_longer_needed',
          'switching_provider',
          'other'
        )
      );
  END IF;
END $$;

COMMENT ON COLUMN public.subscriptions.cancellation_reason IS
  'Patient-selected reason for cancelling the plan.';

COMMENT ON COLUMN public.subscriptions.cancellation_reason_details IS
  'Optional free-text details for plan cancellation reason (required when reason is other).';
