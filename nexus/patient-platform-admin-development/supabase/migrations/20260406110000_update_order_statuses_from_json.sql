-- Update order statuses from the provided canonical JSON definition.
-- Notes:
-- 1) Upsert by status_key so existing status IDs referenced by orders remain stable.
-- 2) Insert missing statuses using the provided IDs.
-- 3) Update transition links by resolving target rows through status_key.
WITH desired_statuses (
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
) AS (
  VALUES
    ('6be90bf1-532a-4ceb-8651-b5f47748ded8'::uuid, 1, 'order_created', 'Order Started', 'We''ve created your order and are getting things ready.', false, 'Order Created', 'Order record created in the system.', 'system', NULL::integer, false, true, true, '2dc13882-42c4-47b5-b2c6-c51f005c85f6'::uuid, NULL::uuid),
    ('2dc13882-42c4-47b5-b2c6-c51f005c85f6'::uuid, 2, 'shipping_details_required', 'Add Shipping Details', 'Please add your shipping address so we can continue processing your order.', true, 'Shipping Details Required', 'Shipping information is missing or incomplete.', 'system', 2, false, true, true, '98e17224-c4c2-4a5e-a3a1-435aaf8c58c8'::uuid, NULL::uuid),
    ('98e17224-c4c2-4a5e-a3a1-435aaf8c58c8'::uuid, 3, 'provider_order_creation_pending', 'Order Being Created In Provider', 'We are creating your order in your medical platform.', false, 'Order Pending Creation On Provider', 'Order pending creation on provider platform', 'patient', NULL::integer, false, true, false, '4877d87a-89d8-489b-89a6-14301d270328'::uuid, '0f48b184-1e22-49f4-8c32-a9bc3d7362b5'::uuid),
    ('4877d87a-89d8-489b-89a6-14301d270328'::uuid, 4, 'patient_questionnaire_pending', 'Complete Patient Questionnaire', 'Please complete the required questionnaire so we can continue processing your order.', true, 'Patient Questionnaire Pending', 'Required patient questionnaire has not been completed by the patient.', 'patient', NULL::integer, false, true, true, '998e9cd9-4d67-4545-aeef-0b60d7d24dc7'::uuid, NULL::uuid),
    ('998e9cd9-4d67-4545-aeef-0b60d7d24dc7'::uuid, 5, 'medical_questionnaire_pending', 'Complete Medical Questions', 'Answer a few medical questions so a licensed provider can safely review your treatment.', true, 'Medical Questionnaire Pending', 'Required medical questionnaire has not been completed by the patient.', 'patient', 24, false, true, true, 'ab275bc0-3705-4cfd-9223-66bd394049c7'::uuid, NULL::uuid),
    ('ab275bc0-3705-4cfd-9223-66bd394049c7'::uuid, 6, 'provider_review_pending', 'Under Medical Review', 'A licensed provider is reviewing your information to make sure this treatment is right for you.', false, 'Provider Review Pending', 'Order is awaiting review by a licensed provider.', 'provider', NULL::integer, false, true, true, 'c093029c-e113-47fe-8a19-94c65f98b817'::uuid, '7a79f6ba-afa5-4a45-89ee-fcce868fd0ca'::uuid),
    ('c093029c-e113-47fe-8a19-94c65f98b817'::uuid, 7, 'provider_approved', 'Treatment Approved', 'Your treatment has been approved by a licensed provider.', false, 'Provider Approved', 'Treatment approved by provider and cleared to proceed.', 'system', NULL::integer, false, true, true, '4477ac0d-33d9-4005-8847-2681ce1171b6'::uuid, NULL::uuid),
    ('4477ac0d-33d9-4005-8847-2681ce1171b6'::uuid, 8, 'payment_pending', 'Payment Pending', 'Your payment will be processed by the Payment Provider', false, 'Payment Pending', 'Pending payment processing on Payment Provider', 'payment_provider', NULL::integer, false, true, true, '39820324-6ee6-4a00-b673-9b0bcbfd37f6'::uuid, '8479a8ab-e84e-4d95-aeb5-eee25b80b181'::uuid),
    ('39820324-6ee6-4a00-b673-9b0bcbfd37f6'::uuid, 9, 'payment_collected', 'Payment Collected', 'Your payment has been collected.', false, 'Payment Collected', 'Payment successfull on Payment provider', 'system', NULL::integer, false, true, true, 'a062fdd5-09cf-452f-8314-8359e12ee4b6'::uuid, NULL::uuid),
    ('a062fdd5-09cf-452f-8314-8359e12ee4b6'::uuid, 10, 'order_approved', 'Order Approved', 'Your order has been approved and is ready for the next step.', false, 'Order Approved', 'Payment and initial order checks are complete, and the order is approved to continue.', 'system', NULL::integer, false, true, true, 'c426721b-b4a9-4f43-a248-bfbb7249aff1'::uuid, NULL::uuid),
    ('c426721b-b4a9-4f43-a248-bfbb7249aff1'::uuid, 11, 'order_sent_to_pharmacy', NULL, NULL, false, 'Order Sent To Pharmacy', 'Order has been sent to the pharmacy and is awaiting pharmacy review.', 'pharmacy', NULL::integer, false, true, false, 'c01adfc1-e622-4661-86cb-7b636d5ce1cb'::uuid, NULL::uuid),
    ('c01adfc1-e622-4661-86cb-7b636d5ce1cb'::uuid, 12, 'pharmacy_approval_pending', 'Pending Pharmacy Approval', 'The pharmacy is reviewing your prescription to ensure it meets all safety and regulatory requirements.', false, 'Pharmacy Approval Pending', 'Order is pending pharmacy review and approval.', 'pharmacy', 4, false, true, true, '4d578f4c-f6c5-4996-878e-dd6674b7bb48'::uuid, 'dd1fc887-1726-4f82-be33-ea0b27f69c52'::uuid),
    ('4d578f4c-f6c5-4996-878e-dd6674b7bb48'::uuid, 13, 'pharmacy_approved', 'Approved By Pharmacy', 'The pharmacy has approved your order and is preparing it for fulfillment.', false, 'Pharmacy Approved', 'Pharmacy has approved the prescription for fulfillment.', 'pharmacy', NULL::integer, false, true, true, '3d3dfaae-99af-4ff1-8171-8b5f28f94d7a'::uuid, NULL::uuid),
    ('3d3dfaae-99af-4ff1-8171-8b5f28f94d7a'::uuid, 14, 'fulfillment_in_progress', 'Being Prepared', 'Your treatment is being prepared and packaged by the pharmacy.', false, 'Fulfillment In Progress', 'Pharmacy is preparing and packaging the order.', 'pharmacy', NULL::integer, false, true, true, 'bee55703-1fb8-46a5-b952-e096711b8df2'::uuid, NULL::uuid),
    ('bee55703-1fb8-46a5-b952-e096711b8df2'::uuid, 15, 'final_pharmacy_verification', 'Final Quality Check', 'The pharmacy is completing a final safety and quality check before shipment.', false, 'Final Pharmacy Verification', 'Final compliance and safety verification in progress prior to shipment.', 'pharmacy', NULL::integer, false, true, true, '898f90a5-22a4-439a-9865-90124ba9ac06'::uuid, NULL::uuid),
    ('898f90a5-22a4-439a-9865-90124ba9ac06'::uuid, 16, 'in_transit', 'Order Shipped!', 'Your order has shipped and is on its way to you. Tracking details will be available soon.', false, 'In Transit', 'Shipment has been handed off to the carrier.', 'carrier', NULL::integer, false, true, true, '33bee4fd-cb66-4b37-a22b-d69fd0cb6734'::uuid, '9add1741-1f89-4ca0-9a0a-3d7784ec61f4'::uuid),
    ('33bee4fd-cb66-4b37-a22b-d69fd0cb6734'::uuid, 17, 'delivered', 'Delivered', 'Your order has been delivered. You''re all set!', false, 'Delivered', 'Shipment confirmed as delivered to the patient.', 'carrier', NULL::integer, true, true, true, 'c2ae782b-c473-4b1d-af1e-58f2996e06be'::uuid, NULL::uuid),
    ('c2ae782b-c473-4b1d-af1e-58f2996e06be'::uuid, 18, 'id_verification_failed', 'Identity Verification Needed', 'We couldn''t verify your identity. Please try again to continue your order.', true, 'ID Verification Failed', 'Identity verification attempt failed or expired.', 'patient', NULL::integer, false, true, true, '6fcd212a-5965-4657-86b5-5a78c9386322'::uuid, NULL::uuid),
    ('6fcd212a-5965-4657-86b5-5a78c9386322'::uuid, 19, 'medical_questionnaire_incomplete', 'Medical Questions Incomplete', 'Some required medical questions are missing. Please complete them to continue.', true, 'Medical Questionnaire Incomplete', 'Questionnaire submission is incomplete or invalid.', 'patient', NULL::integer, false, true, true, '42091fa0-7284-41d9-9de4-5004126c3cf3'::uuid, NULL::uuid),
    ('42091fa0-7284-41d9-9de4-5004126c3cf3'::uuid, 20, 'medical_followup_required', 'Provider Needs More Information', 'Your provider needs a bit more information before approving your treatment.', true, 'Provider Follow-Up Required', 'Provider requested additional patient input.', 'patient', NULL::integer, false, true, true, '8479a8ab-e84e-4d95-aeb5-eee25b80b181'::uuid, NULL::uuid),
    ('8479a8ab-e84e-4d95-aeb5-eee25b80b181'::uuid, 21, 'payment_failed', 'Payment Issue', 'There was an issue processing your payment. Please update your payment method to continue.', true, 'Payment Failed', 'Payment attempt failed or was declined.', 'patient', NULL::integer, false, true, true, '7a79f6ba-afa5-4a45-89ee-fcce868fd0ca'::uuid, NULL::uuid),
    ('7a79f6ba-afa5-4a45-89ee-fcce868fd0ca'::uuid, 22, 'provider_rejected', 'Treatment Not Approved', 'Based on your information, this treatment wasn''t approved. A care team member may follow up with next steps.', false, 'Provider Rejected', 'Provider did not approve treatment.', 'provider', NULL::integer, true, true, true, 'dd1fc887-1726-4f82-be33-ea0b27f69c52'::uuid, NULL::uuid),
    ('dd1fc887-1726-4f82-be33-ea0b27f69c52'::uuid, 23, 'pharmacy_rejected', 'Issue With Pharmacy Approval', 'The pharmacy couldn''t approve this order. Our team is reviewing next steps.', false, 'Pharmacy Rejected', 'Pharmacy rejected prescription or fulfillment request.', 'ops', NULL::integer, false, true, true, '56ad567c-d070-4ff6-8cfa-dcce359273fa'::uuid, NULL::uuid),
    ('56ad567c-d070-4ff6-8cfa-dcce359273fa'::uuid, 24, 'order_on_hold', 'Order Temporarily On Hold', 'Your order is temporarily on hold while we resolve an internal issue.', false, 'Order On Hold', 'Order paused due to operational or compliance issue.', 'ops', NULL::integer, false, true, true, 'a8b0b0b2-c39f-49ac-a288-74fd1768b4df'::uuid, NULL::uuid),
    ('a8b0b0b2-c39f-49ac-a288-74fd1768b4df'::uuid, 25, 'inventory_unavailable', 'Item Temporarily Unavailable', 'One or more items in your order are temporarily unavailable. We''re working on a solution.', false, 'Inventory Unavailable', 'Required inventory not available for fulfillment.', 'pharmacy', NULL::integer, false, true, true, '9add1741-1f89-4ca0-9a0a-3d7784ec61f4'::uuid, NULL::uuid),
    ('9add1741-1f89-4ca0-9a0a-3d7784ec61f4'::uuid, 26, 'shipping_exception', 'Shipping Delay', 'There''s a delay with your shipment. We''re monitoring the situation closely.', false, 'Shipping Exception', 'Carrier reported an exception or delivery issue.', 'ops', NULL::integer, false, true, true, '6e35c2af-cc8d-47e2-8be0-6f1bac238a08'::uuid, NULL::uuid),
    ('6f38adcf-3a80-4770-bf72-5492970479e9'::uuid, 27, 'order_pending_cancellation', 'Order Pending Cancellation', 'Your order is pending processing for cancelation and refund eligibility calculation.', false, 'Order Pending Cancellation', 'Order is Pending Cancellation', 'system', NULL::integer, false, true, true, '32b663c8-21de-4654-a6ff-93828db2454d'::uuid, NULL::uuid),
    ('32b663c8-21de-4654-a6ff-93828db2454d'::uuid, 28, 'order_cancellation_processing', 'Order Cancellation Processing', 'Your order cancellation is being processed.', false, 'Order Cancellation Processing', 'Order cancellation is being processed', 'system', NULL::integer, false, true, true, '6e35c2af-cc8d-47e2-8be0-6f1bac238a08'::uuid, NULL::uuid),
    ('6e35c2af-cc8d-47e2-8be0-6f1bac238a08'::uuid, 29, 'order_cancelled', 'Order Cancelled', 'This order has been cancelled. If you have questions, our support team can help.', false, 'Order Cancelled', 'Order cancelled by patient, admin, or system.', 'ops', NULL::integer, true, true, true, NULL::uuid, NULL::uuid),
    ('0f48b184-1e22-49f4-8c32-a9bc3d7362b5'::uuid, 30, 'provider_order_creation_error', NULL, NULL, false, 'Provider Order Creation Error', 'Error creating order on Provider Platform', 'system', NULL::integer, true, true, false, '6e35c2af-cc8d-47e2-8be0-6f1bac238a08'::uuid, NULL::uuid)
),
updated_statuses AS (
  UPDATE public.order_statuses AS target
  SET
    display_order = ds.display_order,
    patient_status_label = ds.patient_status_label,
    patient_microcopy = ds.patient_microcopy,
    patient_action_required = ds.patient_action_required,
    admin_status_label = ds.admin_status_label,
    admin_microcopy = ds.admin_microcopy,
    next_step_owner = ds.next_step_owner,
    expiration_timer_hours = ds.expiration_timer_hours,
    is_terminal = ds.is_terminal,
    is_active = ds.is_active,
    is_patient_visible = ds.is_patient_visible
  FROM desired_statuses ds
  WHERE target.status_key = ds.status_key
  RETURNING target.id
),
inserted_statuses AS (
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
  SELECT
    ds.id,
    ds.display_order,
    ds.status_key,
    ds.patient_status_label,
    ds.patient_microcopy,
    ds.patient_action_required,
    ds.admin_status_label,
    ds.admin_microcopy,
    ds.next_step_owner,
    ds.expiration_timer_hours,
    ds.is_terminal,
    ds.is_active,
    ds.is_patient_visible,
    NULL::uuid,
    NULL::uuid
  FROM desired_statuses ds
  WHERE NOT EXISTS (
    SELECT 1
    FROM public.order_statuses existing
    WHERE existing.status_key = ds.status_key
  )
  RETURNING id
),
sync_completed AS (
  SELECT
    (SELECT count(*) FROM updated_statuses) +
      (SELECT count(*) FROM inserted_statuses) AS touched_rows
),
desired_transitions AS (
  SELECT
    current_status.status_key,
    next_status.status_key AS next_status_key,
    failure_status.status_key AS failure_status_key
  FROM desired_statuses current_status
  LEFT JOIN desired_statuses next_status
    ON next_status.id = current_status.next_status_id
  LEFT JOIN desired_statuses failure_status
    ON failure_status.id = current_status.failure_status_id
)
UPDATE public.order_statuses target
SET
  next_status_id = next_actual.id,
  failure_status_id = failure_actual.id
FROM desired_transitions dt
CROSS JOIN sync_completed sc
LEFT JOIN public.order_statuses next_actual
  ON next_actual.status_key = dt.next_status_key
LEFT JOIN public.order_statuses failure_actual
  ON failure_actual.status_key = dt.failure_status_key
WHERE target.status_key = dt.status_key;
