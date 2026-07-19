-- Normalize rows that used the historical misspelled enum label.
--
-- This intentionally runs after the migration that adds 'pending_cancellation'
-- so the new enum value is committed before it is used in DML.
DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_enum e
    JOIN pg_type t ON t.oid = e.enumtypid
    WHERE t.typnamespace = 'public'::regnamespace
      AND t.typname = 'subscription_status'
      AND e.enumlabel = 'pending_cancelation'
  ) THEN
    UPDATE public.subscriptions
    SET status = 'pending_cancellation'::public.subscription_status
    WHERE status::text = 'pending_cancelation';
  END IF;
END $$;
