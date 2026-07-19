DO $$
DECLARE
  order_validation_pending_id uuid := '338bb55b-c87f-4b0f-9d7d-75a3ec7a5c4b'::uuid;
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM public.order_statuses
    WHERE status_key = 'order_validation_pending'
  ) THEN
    UPDATE public.order_statuses
    SET display_order = display_order + 1
    WHERE is_active = true
      AND display_order >= (
        SELECT display_order
        FROM public.order_statuses
        WHERE status_key = 'provider_review_pending'
        LIMIT 1
      );
  END IF;

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
    order_validation_pending_id,
    (
      SELECT display_order + 1
      FROM public.order_statuses
      WHERE status_key = 'medical_questionnaire_pending'
      LIMIT 1
    ),
    'order_validation_pending',
    NULL,
    NULL,
    false,
    'Order Validation Pending',
    'Order is pending validation by the system.',
    'system',
    0,
    false,
    true,
    false,
    (
      SELECT id
      FROM public.order_statuses
      WHERE status_key = 'provider_review_pending'
      LIMIT 1
    ),
    NULL
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
    is_patient_visible = EXCLUDED.is_patient_visible,
    next_status_id = EXCLUDED.next_status_id,
    failure_status_id = EXCLUDED.failure_status_id;

  UPDATE public.order_statuses
  SET next_status_id = (
    SELECT id
    FROM public.order_statuses
    WHERE status_key = 'order_validation_pending'
    LIMIT 1
  )
  WHERE status_key = 'medical_questionnaire_pending';

  UPDATE public.order_statuses
  SET next_status_id = (
    SELECT id
    FROM public.order_statuses
    WHERE status_key = 'provider_review_pending'
    LIMIT 1
  )
  WHERE status_key = 'order_validation_pending';
END $$;
