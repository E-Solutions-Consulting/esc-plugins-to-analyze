-- Add configurable renewal lead days for subscription products.
ALTER TABLE public.products
  ADD COLUMN IF NOT EXISTS subscription_renewal_lead_days INTEGER;

UPDATE public.products
SET subscription_renewal_lead_days = 0
WHERE subscription_renewal_lead_days IS NULL;

ALTER TABLE public.products
  ALTER COLUMN subscription_renewal_lead_days SET DEFAULT 0,
  ALTER COLUMN subscription_renewal_lead_days SET NOT NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'products_subscription_renewal_lead_days_check'
      AND conrelid = 'public.products'::regclass
  ) THEN
    ALTER TABLE public.products
      ADD CONSTRAINT products_subscription_renewal_lead_days_check
      CHECK (subscription_renewal_lead_days >= 0);
  END IF;
END $$;

COMMENT ON COLUMN public.products.subscription_renewal_lead_days IS
  'Number of days before plan expiration when subscription renewal should be initiated.';
