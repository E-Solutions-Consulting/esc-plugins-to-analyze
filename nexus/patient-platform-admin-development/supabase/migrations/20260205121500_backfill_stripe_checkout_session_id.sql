-- Backfill stripe_checkout_session_id from internal_notes
UPDATE public.orders
SET stripe_checkout_session_id = substring(internal_notes from 'Stripe Session: ([^,\s]+)')
WHERE stripe_checkout_session_id IS NULL
  AND internal_notes ILIKE '%Stripe Session:%'
  AND substring(internal_notes from 'Stripe Session: ([^,\s]+)') IS NOT NULL;
