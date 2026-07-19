-- Add category column to platform_integrations
ALTER TABLE public.platform_integrations
ADD COLUMN category text NOT NULL DEFAULT 'general';

-- Add comment for documentation
COMMENT ON COLUMN public.platform_integrations.category IS 'Integration category type (e.g., email_distribution, payment, analytics)';

-- Update Resend to have email_distribution category
UPDATE public.platform_integrations
SET category = 'email_distribution'
WHERE key = 'resend';