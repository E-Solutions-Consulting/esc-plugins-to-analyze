-- Remove the legacy 'status' enum column from orders table
-- We now use status_id which references order_statuses table

ALTER TABLE public.orders DROP COLUMN status;

-- Also drop the order_status enum type since it's no longer used
DROP TYPE IF EXISTS public.order_status;