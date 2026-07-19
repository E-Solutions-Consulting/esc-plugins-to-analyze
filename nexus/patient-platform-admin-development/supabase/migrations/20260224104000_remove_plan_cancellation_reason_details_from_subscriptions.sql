-- Remove free-text cancellation details; keep a single structured cancellation reason.

ALTER TABLE public.subscriptions
  DROP COLUMN IF EXISTS cancellation_reason_details;
