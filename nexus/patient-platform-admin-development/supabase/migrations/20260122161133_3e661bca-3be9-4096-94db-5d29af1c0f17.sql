-- Remove subscription_id foreign key from orders first
ALTER TABLE public.orders DROP COLUMN IF EXISTS subscription_id;

-- Drop subscriptions table
DROP TABLE IF EXISTS public.subscriptions;