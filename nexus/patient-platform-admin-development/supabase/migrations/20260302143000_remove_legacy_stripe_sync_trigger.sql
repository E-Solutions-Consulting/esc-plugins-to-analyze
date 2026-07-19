-- Stripe payment metadata is now written directly to provider-agnostic entities.
-- Remove the legacy orders->payment entities sync trigger/function to prevent
-- duplicate or stale snapshots from legacy column updates.

DROP TRIGGER IF EXISTS trigger_sync_legacy_stripe_fields_to_payment_entities
  ON public.orders;

DROP FUNCTION IF EXISTS public.sync_legacy_stripe_fields_to_payment_entities();
