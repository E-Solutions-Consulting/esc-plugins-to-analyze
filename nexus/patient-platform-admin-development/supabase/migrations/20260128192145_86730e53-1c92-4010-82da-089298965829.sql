-- Add RLS policies for patients to manage their own orders

-- Patients can view their own orders
CREATE POLICY "Patients can view their own orders"
ON public.orders FOR SELECT
USING (patient_id = get_patient_by_auth_id(auth.uid()));

-- Patients can create orders for themselves
CREATE POLICY "Patients can create their own orders"
ON public.orders FOR INSERT
WITH CHECK (patient_id = get_patient_by_auth_id(auth.uid()));

-- Patients can update their own orders (e.g., cancel pending orders)
CREATE POLICY "Patients can update their own orders"
ON public.orders FOR UPDATE
USING (patient_id = get_patient_by_auth_id(auth.uid()))
WITH CHECK (patient_id = get_patient_by_auth_id(auth.uid()));