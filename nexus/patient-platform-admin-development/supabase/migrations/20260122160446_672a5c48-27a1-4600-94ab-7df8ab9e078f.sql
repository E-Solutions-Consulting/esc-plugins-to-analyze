-- Remove protocol_id column from subscriptions first (due to foreign key)
ALTER TABLE public.subscriptions DROP COLUMN IF EXISTS protocol_id;

-- Drop protocol-related tables
DROP TABLE IF EXISTS public.protocol_questionnaire_links;
DROP TABLE IF EXISTS public.protocol_products;
DROP TABLE IF EXISTS public.protocols;