-- Create enum for who owns next step
CREATE TYPE next_step_owner AS ENUM ('system', 'patient', 'provider', 'pharmacy', 'carrier', 'ops', 'payment_provider');

-- Create order_statuses table
CREATE TABLE public.order_statuses (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  display_order INTEGER NOT NULL DEFAULT 0,
  status_key TEXT NOT NULL UNIQUE,
  patient_status_label TEXT,
  patient_microcopy TEXT,
  patient_action_required BOOLEAN NOT NULL DEFAULT false,
  admin_status_label TEXT NOT NULL,
  admin_microcopy TEXT,
  next_step_owner TEXT NOT NULL DEFAULT 'system',
  expiration_timer_hours INTEGER,
  is_terminal BOOLEAN NOT NULL DEFAULT false,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Enable RLS
ALTER TABLE public.order_statuses ENABLE ROW LEVEL SECURITY;

-- Platform superadmins can manage order statuses
CREATE POLICY "Superadmin can manage order statuses"
  ON public.order_statuses
  FOR ALL
  USING (is_platform_superadmin(auth.uid()));

-- Authenticated users can view order statuses
CREATE POLICY "Authenticated users can view order statuses"
  ON public.order_statuses
  FOR SELECT
  USING (auth.uid() IS NOT NULL);

-- Public can view active order statuses (for patient-facing apps)
CREATE POLICY "Public can view active order statuses"
  ON public.order_statuses
  FOR SELECT
  USING (is_active = true);

-- Create trigger for updated_at
CREATE TRIGGER update_order_statuses_updated_at
  BEFORE UPDATE ON public.order_statuses
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Insert initial data from CSV
INSERT INTO public.order_statuses (display_order, status_key, patient_status_label, patient_microcopy, patient_action_required, admin_status_label, admin_microcopy, next_step_owner, is_terminal) VALUES
(1, 'order_created', 'Order started', 'We''ve created your order and are getting things ready.', false, 'Order created', 'Order record created in the system.', 'system', false),
(2, 'payment_pending', NULL, NULL, false, 'Payment pending', 'Payment has been initiated but not yet confirmed.', 'payment_provider', false),
(3, 'payment_received', 'Payment confirmed', 'Your payment was successful and your order is moving forward.', false, 'Payment received', 'Payment successfully received and recorded.', 'system', false),
(4, 'shipping_details_required', 'Add shipping details', 'Please add your shipping address so we can continue processing your order.', true, 'Shipping details required', 'Shipping information is missing or incomplete.', 'patient', false),
(5, 'id_verification_required', 'Verify your identity', 'We need to verify your identity before your treatment can be reviewed. This usually takes just a few minutes.', true, 'ID verification required', 'Patient identity verification has not been completed.', 'patient', false),
(6, 'medical_questionnaire_pending', 'Complete medical questions', 'Answer a few medical questions so a licensed provider can safely review your treatment.', true, 'Medical questionnaire pending', 'Required medical questionnaire has not been completed by the patient.', 'patient', false),
(7, 'provider_review_pending', 'Under medical review', 'A licensed provider is reviewing your information to make sure this treatment is right for you.', false, 'Provider review pending', 'Order is awaiting review by a licensed provider.', 'provider', false),
(8, 'provider_approved', 'Treatment approved', 'Your treatment has been approved by a licensed provider.', false, 'Provider approved', 'Treatment approved by provider and cleared to proceed.', 'system', false),
(9, 'pharmacy_approval_pending', 'Pending pharmacy approval', 'The pharmacy is reviewing your prescription to ensure it meets all safety and regulatory requirements.', false, 'Pharmacy approval pending', 'Order is pending pharmacy review and approval.', 'pharmacy', false),
(10, 'pharmacy_approved', 'Approved by pharmacy', 'The pharmacy has approved your order and is preparing it for fulfillment.', false, 'Pharmacy approved', 'Pharmacy has approved the prescription for fulfillment.', 'pharmacy', false),
(11, 'fulfillment_in_progress', 'Being prepared', 'Your treatment is being prepared and packaged by the pharmacy.', false, 'Fulfillment in progress', 'Pharmacy is preparing and packaging the order.', 'pharmacy', false),
(12, 'final_pharmacy_verification', 'Final quality check', 'The pharmacy is completing a final safety and quality check before shipment.', false, 'Final pharmacy verification', 'Final compliance and safety verification in progress prior to shipment.', 'pharmacy', false),
(13, 'in_transit', 'Order shipped!', 'Your order has shipped and is on its way to you. Tracking details will be available soon.', false, 'In transit', 'Shipment has been handed off to the carrier.', 'carrier', false),
(14, 'delivered', 'Delivered', 'Your order has been delivered. You''re all set!', false, 'Delivered', 'Shipment confirmed as delivered to the patient.', 'carrier', true),
(15, 'id_verification_failed', 'Identity verification needed', 'We couldn''t verify your identity. Please try again to continue your order.', true, 'ID verification failed', 'Identity verification attempt failed or expired.', 'patient', false),
(16, 'medical_questionnaire_incomplete', 'Medical questions incomplete', 'Some required medical questions are missing. Please complete them to continue.', true, 'Medical questionnaire incomplete', 'Questionnaire submission is incomplete or invalid.', 'patient', false),
(17, 'medical_followup_required', 'Provider needs more information', 'Your provider needs a bit more information before approving your treatment.', true, 'Provider follow-up required', 'Provider requested additional patient input.', 'patient', false),
(18, 'payment_failed', 'Payment issue', 'There was an issue processing your payment. Please update your payment method to continue.', true, 'Payment failed', 'Payment attempt failed or was declined.', 'patient', false),
(19, 'provider_rejected', 'Treatment not approved', 'Based on your information, this treatment wasn''t approved. A care team member may follow up with next steps.', false, 'Provider rejected', 'Provider did not approve treatment.', 'provider', true),
(20, 'pharmacy_rejected', 'Issue with pharmacy approval', 'The pharmacy couldn''t approve this order. Our team is reviewing next steps.', false, 'Pharmacy rejected', 'Pharmacy rejected prescription or fulfillment request.', 'ops', false),
(21, 'order_on_hold', 'Order temporarily on hold', 'Your order is temporarily on hold while we resolve an internal issue.', false, 'Order on hold', 'Order paused due to operational or compliance issue.', 'ops', false),
(22, 'inventory_unavailable', 'Item temporarily unavailable', 'One or more items in your order are temporarily unavailable. We''re working on a solution.', false, 'Inventory unavailable', 'Required inventory not available for fulfillment.', 'pharmacy', false),
(23, 'shipping_exception', 'Shipping delay', 'There''s a delay with your shipment. We''re monitoring the situation closely.', false, 'Shipping exception', 'Carrier reported an exception or delivery issue.', 'ops', false),
(24, 'order_cancelled', 'Order cancelled', 'This order has been cancelled. If you have questions, our support team can help.', false, 'Order cancelled', 'Order cancelled by patient, admin, or system.', 'ops', true);