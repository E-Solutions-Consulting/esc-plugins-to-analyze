-- Add pending_cancellation as a valid lifecycle state for subscriptions.

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_enum e
    JOIN pg_type t ON t.oid = e.enumtypid
    WHERE t.typname = 'subscription_status'
      AND e.enumlabel = 'pending_cancellation'
  ) THEN
    ALTER TYPE public.subscription_status ADD VALUE 'pending_cancellation';
  END IF;
END $$;
