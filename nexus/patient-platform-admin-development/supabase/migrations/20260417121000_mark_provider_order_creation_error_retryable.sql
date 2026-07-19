-- provider_order_creation_error supports explicit retries through order-lifecycle
-- and therefore must not be treated as a terminal status.
UPDATE public.order_statuses
SET is_terminal = false
WHERE status_key = 'provider_order_creation_error';
