-- Classify each subscription-linked order as either the first lifecycle order ("initial")
-- or a subsequent renewal ("renewal").

ALTER TABLE public.orders
  ADD COLUMN IF NOT EXISTS subscription_order_type TEXT;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'orders_subscription_order_type_check'
      AND conrelid = 'public.orders'::regclass
  ) THEN
    ALTER TABLE public.orders
      ADD CONSTRAINT orders_subscription_order_type_check
      CHECK (
        subscription_order_type IS NULL
        OR subscription_order_type IN ('initial', 'renewal')
      );
  END IF;
END $$;

COMMENT ON COLUMN public.orders.subscription_order_type IS
  'Subscription lifecycle classification for this order: "initial" for the first order in a subscription, "renewal" for subsequent orders.';

CREATE OR REPLACE FUNCTION public.set_order_subscription_order_type()
RETURNS TRIGGER AS $$
DECLARE
  v_created_at TIMESTAMPTZ;
  v_id UUID;
BEGIN
  IF NEW.subscription_id IS NULL THEN
    NEW.subscription_order_type = NULL;
    RETURN NEW;
  END IF;

  v_created_at := COALESCE(NEW.created_at, now());
  NEW.created_at := v_created_at;

  v_id := COALESCE(NEW.id, gen_random_uuid());
  NEW.id := v_id;

  IF EXISTS (
    SELECT 1
    FROM public.orders o
    WHERE o.subscription_id = NEW.subscription_id
      AND o.id <> NEW.id
      AND (
        o.created_at < v_created_at
        OR (o.created_at = v_created_at AND o.id < v_id)
      )
  ) THEN
    NEW.subscription_order_type = 'renewal';
  ELSE
    NEW.subscription_order_type = 'initial';
  END IF;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public;

DROP TRIGGER IF EXISTS trigger_zz_set_order_subscription_order_type ON public.orders;

CREATE TRIGGER trigger_zz_set_order_subscription_order_type
  BEFORE INSERT OR UPDATE OF
    subscription_id,
    created_at,
    stripe_subscription_id,
    stripe_checkout_session_id
  ON public.orders
  FOR EACH ROW
  EXECUTE FUNCTION public.set_order_subscription_order_type();

WITH ranked_subscription_orders AS (
  SELECT
    o.id,
    CASE
      WHEN ROW_NUMBER() OVER (
        PARTITION BY o.subscription_id
        ORDER BY o.created_at ASC, o.id ASC
      ) = 1 THEN 'initial'
      ELSE 'renewal'
    END AS computed_subscription_order_type
  FROM public.orders o
  WHERE o.subscription_id IS NOT NULL
)
UPDATE public.orders o
SET subscription_order_type = r.computed_subscription_order_type
FROM ranked_subscription_orders r
WHERE o.id = r.id
  AND o.subscription_order_type IS DISTINCT FROM r.computed_subscription_order_type;

UPDATE public.orders
SET subscription_order_type = NULL
WHERE subscription_id IS NULL
  AND subscription_order_type IS NOT NULL;
