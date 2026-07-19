-- Backfill existing orders with the initial 'order_created' status
UPDATE public.orders
SET status_id = (SELECT id FROM public.order_statuses WHERE status_key = 'order_created' AND is_active = true LIMIT 1)
WHERE status_id IS NULL;