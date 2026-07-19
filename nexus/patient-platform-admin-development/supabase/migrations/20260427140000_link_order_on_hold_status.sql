-- Link order_approved failure path to order_on_hold
-- When sendToPharmacyRecipients fails, the order transitions to order_on_hold.
-- Idempotent: only runs when failure_status_id is NULL and target status exists.
UPDATE order_statuses
SET failure_status_id = (
  SELECT id FROM order_statuses WHERE status_key = 'order_on_hold' AND is_active = true LIMIT 1
)
WHERE status_key = 'order_approved'
  AND failure_status_id IS NULL
  AND EXISTS (
    SELECT 1 FROM order_statuses WHERE status_key = 'order_on_hold' AND is_active = true
  );

-- Link order_on_hold retry path back to order_approved
-- When admin triggers "Process Order" on an on-hold order and the retry succeeds,
-- the order returns to order_approved to wait for the prescription_sent_to_pharmacy webhook.
-- Idempotent: only runs when next_status_id is NULL and target status exists.
UPDATE order_statuses
SET next_status_id = (
  SELECT id FROM order_statuses WHERE status_key = 'order_approved' AND is_active = true LIMIT 1
)
WHERE status_key = 'order_on_hold'
  AND next_status_id IS NULL
  AND EXISTS (
    SELECT 1 FROM order_statuses WHERE status_key = 'order_approved' AND is_active = true
  );
