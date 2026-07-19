-- Allow free-text cancellation reasons for plans (subscriptions).
-- Previous migration added a constrained value list; this drops it.

ALTER TABLE public.subscriptions
  DROP CONSTRAINT IF EXISTS subscriptions_cancellation_reason_check;

COMMENT ON COLUMN public.subscriptions.cancellation_reason IS
  'Patient-provided free-text reason for cancelling the plan.';
