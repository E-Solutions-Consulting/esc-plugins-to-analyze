-- Add column to control patient visibility of order statuses
ALTER TABLE public.order_statuses
ADD COLUMN is_patient_visible boolean NOT NULL DEFAULT true;

-- Add comment for documentation
COMMENT ON COLUMN public.order_statuses.is_patient_visible IS 'Whether this status is exposed to patients via the patient API';