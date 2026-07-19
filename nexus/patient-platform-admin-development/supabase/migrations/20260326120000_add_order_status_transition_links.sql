ALTER TABLE public.order_statuses
ADD COLUMN IF NOT EXISTS next_status_id UUID,
ADD COLUMN IF NOT EXISTS failure_status_id UUID;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'order_statuses_next_status_id_fkey'
      AND conrelid = 'public.order_statuses'::regclass
  ) THEN
    ALTER TABLE public.order_statuses
    ADD CONSTRAINT order_statuses_next_status_id_fkey
      FOREIGN KEY (next_status_id)
      REFERENCES public.order_statuses(id)
      ON DELETE SET NULL;
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'order_statuses_failure_status_id_fkey'
      AND conrelid = 'public.order_statuses'::regclass
  ) THEN
    ALTER TABLE public.order_statuses
    ADD CONSTRAINT order_statuses_failure_status_id_fkey
      FOREIGN KEY (failure_status_id)
      REFERENCES public.order_statuses(id)
      ON DELETE SET NULL;
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'order_statuses_next_status_id_check'
      AND conrelid = 'public.order_statuses'::regclass
  ) THEN
    ALTER TABLE public.order_statuses
    ADD CONSTRAINT order_statuses_next_status_id_check
      CHECK (next_status_id IS NULL OR next_status_id <> id);
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'order_statuses_failure_status_id_check'
      AND conrelid = 'public.order_statuses'::regclass
  ) THEN
    ALTER TABLE public.order_statuses
    ADD CONSTRAINT order_statuses_failure_status_id_check
      CHECK (failure_status_id IS NULL OR failure_status_id <> id);
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_order_statuses_next_status_id
  ON public.order_statuses(next_status_id);

CREATE INDEX IF NOT EXISTS idx_order_statuses_failure_status_id
  ON public.order_statuses(failure_status_id);

COMMENT ON COLUMN public.order_statuses.next_status_id IS
  'Explicit next order status used by lifecycle automation. If null, no automatic next status is inferred. Existing rows are initially backfilled to the next active status by display_order in this migration.';

COMMENT ON COLUMN public.order_statuses.failure_status_id IS
  'Order status to use when the current status ends in failure.';

WITH ordered_active_statuses AS (
  SELECT
    id,
    LEAD(id) OVER (ORDER BY display_order, status_key, id) AS derived_next_status_id
  FROM public.order_statuses
  WHERE is_active = true
)
UPDATE public.order_statuses os
SET next_status_id = ordered_active_statuses.derived_next_status_id
FROM ordered_active_statuses
WHERE os.id = ordered_active_statuses.id
  AND os.next_status_id IS NULL;
