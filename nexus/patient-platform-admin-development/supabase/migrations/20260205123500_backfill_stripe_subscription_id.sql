-- Backfill stripe_subscription_id from internal_notes
UPDATE public.orders
SET stripe_subscription_id = substring(internal_notes from 'Subscription: ([^,\s]+)')
WHERE stripe_subscription_id IS NULL
  AND internal_notes ILIKE '%Subscription:%'
  AND substring(internal_notes from 'Subscription: ([^,\s]+)') IS NOT NULL;
