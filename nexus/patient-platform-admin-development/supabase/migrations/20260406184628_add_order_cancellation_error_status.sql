DO $$
DECLARE
  cancellation_processing_id uuid;
  cancellation_error_id uuid := '19f69fd5-574d-447b-b4dd-b4a3d85d2827'::uuid;
  order_cancelled_id uuid;
BEGIN
  SELECT id
  INTO cancellation_processing_id
  FROM public.order_statuses
  WHERE status_key = 'order_cancellation_processing';

  SELECT id
  INTO order_cancelled_id
  FROM public.order_statuses
  WHERE status_key = 'order_cancelled';

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
    cancellation_error_id,
    31,
    'order_cancellation_error',
    'Cancellation Issue',
    'We hit an issue while cancelling your order. Our support team will review it.',
    false,
    'Order Cancellation Error',
    'Order cancellation processing failed and requires operational follow-up.',
    'ops',
    NULL::integer,
    true,
    true,
    true,
    NULL::uuid,
    NULL::uuid
  )
  ON CONFLICT (status_key) DO UPDATE
  SET
    display_order = EXCLUDED.display_order,
    patient_status_label = EXCLUDED.patient_status_label,
    patient_microcopy = EXCLUDED.patient_microcopy,
    patient_action_required = EXCLUDED.patient_action_required,
    admin_status_label = EXCLUDED.admin_status_label,
    admin_microcopy = EXCLUDED.admin_microcopy,
    next_step_owner = EXCLUDED.next_step_owner,
    expiration_timer_hours = EXCLUDED.expiration_timer_hours,
    is_terminal = EXCLUDED.is_terminal,
    is_active = EXCLUDED.is_active,
    is_patient_visible = EXCLUDED.is_patient_visible;

  SELECT id
  INTO cancellation_error_id
  FROM public.order_statuses
  WHERE status_key = 'order_cancellation_error';

  IF cancellation_processing_id IS NOT NULL THEN
    UPDATE public.order_statuses
    SET
      next_status_id = COALESCE(order_cancelled_id, next_status_id),
      failure_status_id = COALESCE(cancellation_error_id, failure_status_id)
    WHERE id = cancellation_processing_id;
  END IF;
END $$;
