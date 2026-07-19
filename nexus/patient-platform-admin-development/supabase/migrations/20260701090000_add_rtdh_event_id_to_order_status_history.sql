-- Idempotency for inbound RTDH events.
--
-- Inbound RTDH order-status events are delivered at-least-once (PubSub redelivery, consumer
-- retries, fan-out partial retries). Without a per-event dedupe key at this sink, a duplicate
-- delivery re-runs the status transition and can drive an order into a wrong/terminal state
-- (e.g. a stale event flipping the order, surfacing as a false "rejected"/"cancelled" to the
-- patient). We record the RTDH event_id that caused each status-history row so a redelivery of
-- the same event is a true no-op.
ALTER TABLE public.order_status_history
  ADD COLUMN IF NOT EXISTS rtdh_event_id TEXT;

-- Lookup path for the idempotency guard: "has this (order_id, rtdh_event_id) already been
-- processed?". Partial index keeps it small (only RTDH-originated rows carry the id).
CREATE INDEX IF NOT EXISTS idx_order_status_history_rtdh_event_id
  ON public.order_status_history (order_id, rtdh_event_id)
  WHERE rtdh_event_id IS NOT NULL;

COMMENT ON COLUMN public.order_status_history.rtdh_event_id IS
  'RTDH event_id (from the event timeline / x-rtdh-event-id header) that produced this status '
  'transition. Used by rtdh-webhook to dedupe at-least-once event deliveries. Null for non-RTDH '
  'transitions (manual admin changes, lifecycle-internal advances).';
