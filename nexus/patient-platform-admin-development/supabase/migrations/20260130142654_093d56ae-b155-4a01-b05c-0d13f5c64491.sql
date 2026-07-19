-- Add status_id and status_changed_at to orders table
ALTER TABLE public.orders 
ADD COLUMN status_id UUID REFERENCES public.order_statuses(id),
ADD COLUMN status_changed_at TIMESTAMP WITH TIME ZONE DEFAULT now();

-- Create order status history table
CREATE TABLE public.order_status_history (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id UUID NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
  status_id UUID NOT NULL REFERENCES public.order_statuses(id),
  changed_by UUID REFERENCES public.admin_users(id),
  changed_by_email TEXT,
  notes TEXT,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Create index for faster lookups
CREATE INDEX idx_order_status_history_order_id ON public.order_status_history(order_id);
CREATE INDEX idx_order_status_history_created_at ON public.order_status_history(created_at DESC);

-- Enable RLS
ALTER TABLE public.order_status_history ENABLE ROW LEVEL SECURITY;

-- RLS Policies for order_status_history
CREATE POLICY "Patients can view their own order status history"
ON public.order_status_history
FOR SELECT
USING (
  order_id IN (
    SELECT id FROM public.orders 
    WHERE patient_id = get_patient_by_auth_id(auth.uid())
  )
);

CREATE POLICY "Tenant admins can manage order status history"
ON public.order_status_history
FOR ALL
USING (
  order_id IN (
    SELECT id FROM public.orders 
    WHERE tenant_id IN (SELECT get_user_tenant_ids(auth.uid()))
  )
);

-- Set initial status_id for existing orders based on status enum
UPDATE public.orders o
SET status_id = os.id,
    status_changed_at = o.updated_at
FROM public.order_statuses os
WHERE os.status_key = o.status::text;

-- Create trigger function to auto-log status changes
CREATE OR REPLACE FUNCTION public.log_order_status_change()
RETURNS TRIGGER AS $$
BEGIN
  -- Only log if status_id actually changed
  IF OLD.status_id IS DISTINCT FROM NEW.status_id AND NEW.status_id IS NOT NULL THEN
    INSERT INTO public.order_status_history (order_id, status_id, changed_by, changed_by_email)
    SELECT 
      NEW.id,
      NEW.status_id,
      au.id,
      au.email
    FROM public.admin_users au
    WHERE au.auth_user_id = auth.uid()
    UNION ALL
    SELECT NEW.id, NEW.status_id, NULL, NULL
    WHERE NOT EXISTS (SELECT 1 FROM public.admin_users WHERE auth_user_id = auth.uid())
    LIMIT 1;
    
    -- Update status_changed_at
    NEW.status_changed_at := now();
  END IF;
  
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

-- Create trigger
CREATE TRIGGER trigger_log_order_status_change
BEFORE UPDATE ON public.orders
FOR EACH ROW
EXECUTE FUNCTION public.log_order_status_change();