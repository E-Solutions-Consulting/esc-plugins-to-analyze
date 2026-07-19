DO $$
BEGIN
  IF to_regclass('public.orders') IS NULL THEN
    RETURN;
  END IF;

  ALTER TABLE public.orders
    ADD COLUMN IF NOT EXISTS provider_platform_order_id TEXT;

  IF to_regclass('public.order_provider_platform_links') IS NOT NULL THEN
    UPDATE public.orders AS o
    SET provider_platform_order_id = src.provider_order_id
    FROM (
      SELECT DISTINCT ON (l.order_id)
        l.order_id,
        l.provider_order_id
      FROM public.order_provider_platform_links AS l
      WHERE l.provider_order_id IS NOT NULL
        AND btrim(l.provider_order_id) <> ''
      ORDER BY l.order_id, l.updated_at DESC NULLS LAST, l.created_at DESC NULLS LAST
    ) AS src
    WHERE o.id = src.order_id
      AND (
        o.provider_platform_order_id IS NULL
        OR btrim(o.provider_platform_order_id) = ''
      );
  END IF;

  COMMENT ON COLUMN public.orders.provider_platform_order_id IS
    'Provider platform order id snapshot (for example Telegra order id such as order::...).';
END $$;

CREATE INDEX IF NOT EXISTS idx_orders_provider_platform_order_id
  ON public.orders(provider_platform_order_id);
