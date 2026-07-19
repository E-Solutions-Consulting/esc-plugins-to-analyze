DO $$
DECLARE
  order_expired_id uuid := 'e1d2c3b4-a5f6-4789-8123-456789abcdef'::uuid;
BEGIN
  INSERT INTO public.order_statuses (
    id,
    display_order,
    status_key,
    patient_status_label,
    patient_microcopy,
    patient_action_required,
    admin_status_label,
    admin_microcopy,
    next_step_owner,
    expiration_timer_hours,
    is_terminal,
    is_active,
    is_patient_visible,
    next_status_id,
    failure_status_id
  )
  VALUES (
    order_expired_id,
    32,
    'order_expired',
    'Order Expired',
    'This order expired before it could be completed. You can start again anytime.',
    false,
    'Order Expired',
    'Order expired due to patient inactivity beyond the configured threshold.',
    'system',
    0,
    true,
    true,
    true,
    NULL,
    NULL
  )
  ON CONFLICT (status_key) DO NOTHING;
END $$;
