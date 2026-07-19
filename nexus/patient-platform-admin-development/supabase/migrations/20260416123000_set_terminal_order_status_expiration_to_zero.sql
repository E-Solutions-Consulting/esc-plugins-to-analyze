UPDATE public.order_statuses
SET expiration_timer_hours = 0
WHERE is_terminal = true
  AND status_key <> 'delivered';
