-- Create order_pharmacy_platform_links table.
-- Stores LifeFile pharmacy identifiers against our internal orders and
-- provides an idempotency / deduplication anchor for the lifefile-webhook edge function.
-- Each row represents one LifeFile orderId + fillId pair linked to an internal order.
--
-- Safe to run multiple times: TABLE/INDEX creation uses IF NOT EXISTS; the trigger
-- and policy blocks are guarded with existence checks so re-runs are no-ops.

CREATE TABLE IF NOT EXISTS public.order_pharmacy_platform_links (
  id                   UUID        NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  order_id             UUID        NOT NULL REFERENCES public.orders (id) ON DELETE CASCADE,
  tenant_id            UUID        NOT NULL REFERENCES public.tenants (id) ON DELETE CASCADE,
  lifefile_order_id    TEXT        NOT NULL,
  lifefile_fill_id     TEXT,
  rx_number            TEXT,
  latest_rx_status     TEXT,
  latest_order_status  TEXT,
  metadata             JSONB,
  created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),

  -- One row per (internal order, LifeFile order). A patient can have the same LifeFile
  -- orderId across multiple fills only if different order_ids, which is handled correctly.
  CONSTRAINT order_pharmacy_platform_links_order_lifefile_order_unique
    UNIQUE (order_id, lifefile_order_id)
);

-- Ensure the unique constraint exists when rerunning the migration.
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'order_pharmacy_platform_links_order_lifefile_order_unique'
      AND conrelid = 'public.order_pharmacy_platform_links'::regclass
  ) THEN
    ALTER TABLE public.order_pharmacy_platform_links
      ADD CONSTRAINT order_pharmacy_platform_links_order_lifefile_order_unique
      UNIQUE (order_id, lifefile_order_id);
  END IF;
END $$;

-- Indexes for the common lookup paths in the webhook handler.
CREATE INDEX IF NOT EXISTS order_pharmacy_platform_links_order_id_idx
  ON public.order_pharmacy_platform_links (order_id);

CREATE INDEX IF NOT EXISTS order_pharmacy_platform_links_lifefile_order_id_idx
  ON public.order_pharmacy_platform_links (lifefile_order_id);

CREATE INDEX IF NOT EXISTS order_pharmacy_platform_links_tenant_id_idx
  ON public.order_pharmacy_platform_links (tenant_id);

-- Keep updated_at current on every write.
-- Uses the shared update_updated_at_column() function that already exists in this project.
DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_proc
    WHERE proname = 'update_updated_at_column'
      AND pg_function_is_visible(oid)
  ) AND NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'order_pharmacy_platform_links_updated_at'
      AND tgrelid = 'public.order_pharmacy_platform_links'::regclass
  ) THEN
    CREATE TRIGGER order_pharmacy_platform_links_updated_at
      BEFORE UPDATE ON public.order_pharmacy_platform_links
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

-- Row Level Security.
-- ENABLE ROW LEVEL SECURITY is idempotent in PostgreSQL (no error if already enabled).
ALTER TABLE public.order_pharmacy_platform_links ENABLE ROW LEVEL SECURITY;

-- Service role has unrestricted access (used by edge functions).
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'order_pharmacy_platform_links'
      AND policyname = 'service_role_full_access_order_pharmacy_platform_links'
  ) THEN
    CREATE POLICY "service_role_full_access_order_pharmacy_platform_links"
      ON public.order_pharmacy_platform_links
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END $$;

-- Patients can read their own pharmacy links via the orders relationship.
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'order_pharmacy_platform_links'
      AND policyname = 'patients_read_own_order_pharmacy_platform_links'
  ) THEN
    CREATE POLICY "patients_read_own_order_pharmacy_platform_links"
      ON public.order_pharmacy_platform_links
      FOR SELECT
      TO authenticated
      USING (
        order_id IN (
          SELECT o.id
          FROM public.orders o
          WHERE o.patient_id = public.get_patient_by_auth_id(auth.uid())
        )
      );
  END IF;
END $$;
