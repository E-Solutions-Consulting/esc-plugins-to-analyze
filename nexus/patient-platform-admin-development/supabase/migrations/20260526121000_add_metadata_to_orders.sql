-- Add metadata JSONB column to orders for migration traceability and future use.
-- Required by the Brello → Patient Platform Phase 1 migration to store
-- woo_order_id, woo_parent_order_id, is_migrated, and migration_phase.

ALTER TABLE public.orders
ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb;

-- Unique indexes for JSONB text paths used by migration idempotency checks.
-- These match Supabase filters using metadata->>'woo_order_id' and
-- metadata->>'woo_subscription_id'.
DROP INDEX IF EXISTS public.idx_orders_metadata_woo_order_id;
DROP INDEX IF EXISTS public.idx_subscriptions_metadata_woo_subscription_id;

CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_metadata_woo_order_id_unique
ON public.orders ((metadata ->> 'woo_order_id'))
WHERE metadata ? 'woo_order_id';

CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriptions_metadata_woo_subscription_id_unique
ON public.subscriptions ((metadata ->> 'woo_subscription_id'))
WHERE metadata ? 'woo_subscription_id';
