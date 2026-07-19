-- Deduplicate and enforce uniqueness for Stripe identifiers on orders

-- Null out duplicate stripe_invoice_id values (keep earliest)
WITH ranked_invoices AS (
  SELECT
    id,
    stripe_invoice_id,
    created_at,
    ROW_NUMBER() OVER (PARTITION BY stripe_invoice_id ORDER BY created_at ASC, id ASC) AS rn
  FROM public.orders
  WHERE stripe_invoice_id IS NOT NULL
)
UPDATE public.orders o
SET stripe_invoice_id = NULL
FROM ranked_invoices r
WHERE o.id = r.id
  AND r.rn > 1;

-- Null out duplicate stripe_payment_intent_id values (keep earliest)
WITH ranked_payment_intents AS (
  SELECT
    id,
    stripe_payment_intent_id,
    created_at,
    ROW_NUMBER() OVER (PARTITION BY stripe_payment_intent_id ORDER BY created_at ASC, id ASC) AS rn
  FROM public.orders
  WHERE stripe_payment_intent_id IS NOT NULL
)
UPDATE public.orders o
SET stripe_payment_intent_id = NULL
FROM ranked_payment_intents r
WHERE o.id = r.id
  AND r.rn > 1;

-- Replace non-unique indexes with unique ones
DROP INDEX IF EXISTS idx_orders_stripe_invoice_id;
DROP INDEX IF EXISTS idx_orders_stripe_payment_intent;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_orders_stripe_invoice_id
ON public.orders (stripe_invoice_id)
WHERE stripe_invoice_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_orders_stripe_payment_intent_id
ON public.orders (stripe_payment_intent_id)
WHERE stripe_payment_intent_id IS NOT NULL;
