-- Atomic double-charge guard for admin bill-now renewals: enforce at most one
-- in-flight ("pending") renewal attempt per subscription. The refill endpoint
-- claims this row BEFORE billing, so a concurrent second bill-now fails the
-- unique index instead of stacking another Stripe charge.
--
-- Collapse any pre-existing duplicate pendings first (keep the most recent) so
-- the unique index can be built.
UPDATE public.renewal_trigger_attempts a
SET status = 'unresolved',
    notes = COALESCE(a.notes, '') || ' [superseded during unique-index backfill]',
    updated_at = now()
WHERE a.status = 'pending'
  AND a.id <> (
    SELECT b.id
    FROM public.renewal_trigger_attempts b
    WHERE b.subscription_id = a.subscription_id
      AND b.status = 'pending'
    ORDER BY b.triggered_at DESC, b.id DESC
    LIMIT 1
  );

CREATE UNIQUE INDEX IF NOT EXISTS uq_renewal_trigger_attempts_one_pending
  ON public.renewal_trigger_attempts (subscription_id)
  WHERE status = 'pending';
