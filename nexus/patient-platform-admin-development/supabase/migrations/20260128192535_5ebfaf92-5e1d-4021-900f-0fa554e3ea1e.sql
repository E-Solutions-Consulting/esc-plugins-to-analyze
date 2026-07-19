-- Remove INSERT and UPDATE policies for patients on orders table
-- Patients should only be able to view their orders, not create or modify them

DROP POLICY IF EXISTS "Patients can create their own orders" ON public.orders;
DROP POLICY IF EXISTS "Patients can update their own orders" ON public.orders;