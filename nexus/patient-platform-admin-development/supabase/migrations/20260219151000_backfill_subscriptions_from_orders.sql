-- Backfill lifecycle subscriptions from historical orders.
-- Data moved from orders to subscriptions:
--   - orders.stripe_subscription_id -> subscriptions.stripe_subscription_id
--   - orders.stripe_checkout_session_id (initial order) -> subscriptions.stripe_checkout_session_id
--   - orders.renewal_at (latest order) -> subscriptions.current_period_end_at

WITH first_orders AS (
  SELECT DISTINCT ON (o.stripe_subscription_id)
    o.stripe_subscription_id,
    o.tenant_id,
    o.patient_id,
    o.product_id,
    o.stripe_checkout_session_id,
    o.created_at AS first_order_at
  FROM public.orders o
  WHERE o.stripe_subscription_id IS NOT NULL
  ORDER BY o.stripe_subscription_id, o.created_at ASC, o.id ASC
),
last_orders AS (
  SELECT DISTINCT ON (o.stripe_subscription_id)
    o.stripe_subscription_id,
    o.product_id,
    o.renewal_at,
    o.paused_at,
    o.cancelled_at
  FROM public.orders o
  WHERE o.stripe_subscription_id IS NOT NULL
  ORDER BY o.stripe_subscription_id, o.created_at DESC, o.id DESC
),
subscription_seed AS (
  SELECT
    f.stripe_subscription_id,
    f.tenant_id,
    f.patient_id,
    COALESCE(l.product_id, f.product_id) AS product_id,
    CASE
      WHEN l.cancelled_at IS NOT NULL THEN 'cancelled'::public.subscription_status
      WHEN l.paused_at IS NOT NULL THEN 'paused'::public.subscription_status
      ELSE 'active'::public.subscription_status
    END AS status,
    f.first_order_at AS started_at,
    l.renewal_at AS current_period_end_at,
    l.paused_at,
    l.cancelled_at,
    f.stripe_checkout_session_id
  FROM first_orders f
  JOIN last_orders l USING (stripe_subscription_id)
)
INSERT INTO public.subscriptions (
  tenant_id,
  patient_id,
  product_id,
  status,
  started_at,
  current_period_end_at,
  paused_at,
  cancelled_at,
  stripe_subscription_id,
  stripe_checkout_session_id,
  created_at,
  updated_at
)
SELECT
  s.tenant_id,
  s.patient_id,
  s.product_id,
  s.status,
  s.started_at,
  s.current_period_end_at,
  s.paused_at,
  s.cancelled_at,
  s.stripe_subscription_id,
  s.stripe_checkout_session_id,
  s.started_at,
  now()
FROM subscription_seed s
ON CONFLICT (stripe_subscription_id) DO UPDATE
SET
  tenant_id = EXCLUDED.tenant_id,
  patient_id = EXCLUDED.patient_id,
  product_id = COALESCE(EXCLUDED.product_id, public.subscriptions.product_id),
  status = CASE
    WHEN EXCLUDED.cancelled_at IS NOT NULL THEN 'cancelled'::public.subscription_status
    WHEN EXCLUDED.paused_at IS NOT NULL THEN 'paused'::public.subscription_status
    ELSE 'active'::public.subscription_status
  END,
  started_at = COALESCE(
    LEAST(public.subscriptions.started_at, EXCLUDED.started_at),
    public.subscriptions.started_at,
    EXCLUDED.started_at
  ),
  current_period_end_at = COALESCE(
    GREATEST(public.subscriptions.current_period_end_at, EXCLUDED.current_period_end_at),
    public.subscriptions.current_period_end_at,
    EXCLUDED.current_period_end_at
  ),
  paused_at = COALESCE(EXCLUDED.paused_at, public.subscriptions.paused_at),
  cancelled_at = COALESCE(EXCLUDED.cancelled_at, public.subscriptions.cancelled_at),
  stripe_checkout_session_id = COALESCE(
    public.subscriptions.stripe_checkout_session_id,
    EXCLUDED.stripe_checkout_session_id
  ),
  updated_at = now();

UPDATE public.orders o
SET subscription_id = s.id
FROM public.subscriptions s
WHERE o.stripe_subscription_id IS NOT NULL
  AND s.stripe_subscription_id = o.stripe_subscription_id
  AND o.subscription_id IS DISTINCT FROM s.id;
