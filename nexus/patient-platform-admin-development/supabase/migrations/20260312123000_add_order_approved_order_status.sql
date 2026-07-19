DO $$
DECLARE
  payment_collected_order INTEGER;
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM public.order_statuses
    WHERE status_key = 'order_approved'
  ) THEN
    SELECT display_order
    INTO payment_collected_order
    FROM public.order_statuses
    WHERE status_key IN ('payment_collected', 'payment_received')
    ORDER BY CASE status_key
      WHEN 'payment_collected' THEN 0
      ELSE 1
    END
    LIMIT 1;

    IF payment_collected_order IS NULL THEN
      RAISE EXCEPTION
        'Cannot insert order_approved: payment_collected/payment_received status not found';
    END IF;

    UPDATE public.order_statuses
    SET display_order = display_order + 1
    WHERE display_order > payment_collected_order;

    INSERT INTO public.order_statuses (
      display_order,
      status_key,
      patient_status_label,
      patient_microcopy,
      patient_action_required,
      admin_status_label,
      admin_microcopy,
      next_step_owner,
      is_terminal
    ) VALUES (
      payment_collected_order + 1,
      'order_approved',
      'Order approved',
      'Your order has been approved and is ready for the next step.',
      false,
      'Order approved',
      'Payment and initial order checks are complete, and the order is approved to continue.',
      'system',
      false
    );
  END IF;
END $$;
