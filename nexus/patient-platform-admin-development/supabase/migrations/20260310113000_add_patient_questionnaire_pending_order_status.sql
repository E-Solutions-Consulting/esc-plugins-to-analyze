DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM public.order_statuses
    WHERE status_key = 'patient_questionnaire_pending'
  ) THEN
    UPDATE public.order_statuses
    SET display_order = display_order + 1
    WHERE display_order >= 6;

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
      6,
      'patient_questionnaire_pending',
      'Complete patient questionnaire',
      'Please complete the required questionnaire so we can continue processing your order.',
      true,
      'Patient questionnaire pending',
      'Required patient questionnaire has not been completed by the patient.',
      'patient',
      false
    );
  END IF;
END $$;
