-- Display-only "was" price, shown struck through next to the real price.
--
-- The marketing site anchors each price against a higher one ("~~$749~~ $499").
-- That figure is not a discount: the customer is charged `price_cents` on the
-- first order and on every renewal alike. It is presentation only — the same
-- thing WooCommerce calls `regular_price` and Shopify calls `compare_at_price`.
--
-- It is deliberately an exact amount rather than a percentage:
--   * the percentage is derivable from the two prices, the anchor is not — a
--     "33% off $499" back-solves to $744.78, not the $749 marketing wants;
--   * WooCommerce, which we are migrating from, stores the exact amount, so a
--     percentage would force a lossy conversion on a customer-facing number;
--   * a percentage invites someone to eventually apply it to a charge. This
--     value must never touch billing.
--
-- Until now these anchors were hand-typed into the website's content file, where
-- nothing verified them: MICC advertised "was $199" against a real charge of
-- $199, presenting the actual price as if it were a saving.
--
-- NULL means no anchor is shown, which is the case for most products.

ALTER TABLE public.products
  ADD COLUMN IF NOT EXISTS compare_at_price_cents INTEGER;

-- An anchor at or below the price advertises a saving that does not exist.
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'products_compare_at_price_cents_check'
      AND conrelid = 'public.products'::regclass
  ) THEN
    ALTER TABLE public.products
      ADD CONSTRAINT products_compare_at_price_cents_check
      CHECK (
        compare_at_price_cents IS NULL
        OR compare_at_price_cents > price_cents
      );
  END IF;
END $$;

COMMENT ON COLUMN public.products.compare_at_price_cents IS
  'Display-only "was" price, shown struck through beside price_cents. NULL = no anchor shown. Never charged: the customer pays price_cents on the first order and every renewal. Must exceed price_cents.';
