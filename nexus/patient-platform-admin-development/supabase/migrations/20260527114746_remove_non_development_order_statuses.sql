-- Remove legacy order statuses that are not present in the development
-- snapshot. The previous sync migration updates/inserts the development
-- statuses, but the admin UI lists inactive rows too; keeping legacy rows can
-- still show duplicate display_order values in staging.

DROP TABLE IF EXISTS pg_temp.desired_order_status_keys;

CREATE TEMP TABLE desired_order_status_keys (
  status_key text PRIMARY KEY
) ON COMMIT DROP;

INSERT INTO desired_order_status_keys (status_key)
VALUES
  ('order_created'),
  ('shipping_details_required'),
  ('provider_order_creation_pending'),
  ('patient_questionnaire_pending'),
  ('medical_questionnaire_pending'),
  ('provider_review_pending'),
  ('provider_approved'),
  ('payment_pending'),
  ('payment_collected'),
  ('order_approved'),
  ('order_sent_to_pharmacy'),
  ('pharmacy_approval_pending'),
  ('pharmacy_approved'),
  ('fulfillment_in_progress'),
  ('final_pharmacy_verification'),
  ('in_transit'),
  ('delivered'),
  ('id_verification_failed'),
  ('medical_questionnaire_incomplete'),
  ('medical_followup_required'),
  ('payment_failed'),
  ('provider_rejected'),
  ('pharmacy_rejected'),
  ('order_on_hold'),
  ('inventory_unavailable'),
  ('shipping_exception'),
  ('order_cancelled'),
  ('order_pending_cancellation'),
  ('order_cancellation_processing'),
  ('provider_order_creation_error'),
  ('order_cancellation_error'),
  ('order_expired');

DELETE FROM public.order_statuses AS status
WHERE NOT EXISTS (
    SELECT 1
    FROM desired_order_status_keys AS desired
    WHERE desired.status_key = status.status_key
  )
  AND NOT EXISTS (
    SELECT 1
    FROM public.orders AS orders
    WHERE orders.status_id = status.id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM public.order_status_history AS history
    WHERE history.status_id = status.id
  );

DO $$
DECLARE
  extra_statuses text;
  duplicate_orders text;
BEGIN
  SELECT string_agg(
    format('%s (%s)', status.status_key, status.id),
    ', '
    ORDER BY status.display_order, status.status_key
  )
  INTO extra_statuses
  FROM public.order_statuses AS status
  WHERE NOT EXISTS (
    SELECT 1
    FROM desired_order_status_keys AS desired
    WHERE desired.status_key = status.status_key
  );

  IF extra_statuses IS NOT NULL THEN
    RAISE EXCEPTION
      'Cannot make order_statuses match development because these non-development statuses are still referenced or could not be deleted: %',
      extra_statuses;
  END IF;

  SELECT string_agg(display_order::text, ', ' ORDER BY display_order)
  INTO duplicate_orders
  FROM (
    SELECT display_order
    FROM public.order_statuses
    GROUP BY display_order
    HAVING count(*) > 1
  ) AS duplicated;

  IF duplicate_orders IS NOT NULL THEN
    RAISE EXCEPTION
      'order_statuses still has duplicate display_order values after syncing with development: %',
      duplicate_orders;
  END IF;
END $$;
