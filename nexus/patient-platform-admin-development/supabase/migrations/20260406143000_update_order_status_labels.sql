-- Update order status labels after the canonical sync migration was already applied.
UPDATE public.order_statuses AS os
SET
  patient_status_label = labels.patient_status_label,
  admin_status_label = labels.admin_status_label
FROM (
  VALUES
    ('order_created', 'Order Started', 'Order Created'),
    ('shipping_details_required', 'Add Shipping Details', 'Shipping Details Required'),
    ('provider_order_creation_pending', 'Order Being Created In Provider', 'Order Pending Creation On Provider'),
    ('patient_questionnaire_pending', 'Complete Patient Questionnaire', 'Patient Questionnaire Pending'),
    ('medical_questionnaire_pending', 'Complete Medical Questions', 'Medical Questionnaire Pending'),
    ('provider_review_pending', 'Under Medical Review', 'Provider Review Pending'),
    ('provider_approved', 'Treatment Approved', 'Provider Approved'),
    ('payment_pending', 'Payment Pending', 'Payment Pending'),
    ('payment_collected', 'Payment Collected', 'Payment Collected'),
    ('order_approved', 'Order Approved', 'Order Approved'),
    ('order_sent_to_pharmacy', NULL, 'Order Sent To Pharmacy'),
    ('pharmacy_approval_pending', 'Pending Pharmacy Approval', 'Pharmacy Approval Pending'),
    ('pharmacy_approved', 'Approved By Pharmacy', 'Pharmacy Approved'),
    ('fulfillment_in_progress', 'Being Prepared', 'Fulfillment In Progress'),
    ('final_pharmacy_verification', 'Final Quality Check', 'Final Pharmacy Verification'),
    ('in_transit', 'Order Shipped!', 'In Transit'),
    ('delivered', 'Delivered', 'Delivered'),
    ('id_verification_failed', 'Identity Verification Needed', 'ID Verification Failed'),
    ('medical_questionnaire_incomplete', 'Medical Questions Incomplete', 'Medical Questionnaire Incomplete'),
    ('medical_followup_required', 'Provider Needs More Information', 'Provider Follow-Up Required'),
    ('payment_failed', 'Payment Issue', 'Payment Failed'),
    ('provider_rejected', 'Treatment Not Approved', 'Provider Rejected'),
    ('pharmacy_rejected', 'Issue With Pharmacy Approval', 'Pharmacy Rejected'),
    ('order_on_hold', 'Order Temporarily On Hold', 'Order On Hold'),
    ('inventory_unavailable', 'Item Temporarily Unavailable', 'Inventory Unavailable'),
    ('shipping_exception', 'Shipping Delay', 'Shipping Exception'),
    ('order_pending_cancellation', 'Order Pending Cancellation', 'Order Pending Cancellation'),
    ('order_cancellation_processing', 'Order Cancellation Processing', 'Order Cancellation Processing'),
    ('order_cancelled', 'Order Cancelled', 'Order Cancelled'),
    ('provider_order_creation_error', NULL, 'Provider Order Creation Error')
) AS labels(status_key, patient_status_label, admin_status_label)
WHERE os.status_key = labels.status_key;
