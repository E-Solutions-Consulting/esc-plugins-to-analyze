-- Migration: Add order_sent_to_pharmacy status
--
-- Inserts a new order_sent_to_pharmacy status at display_order 11, between
-- order_approved (10) and pharmacy_approval_pending (was 11, becomes 12).
-- All statuses with display_order >= 11 are shifted up by 1 to make room.
--
-- Idempotent at every step level – safe to run multiple times:
--
--   Step 1 (display_order shift): only fires when pharmacy_approval_pending is
--           still at display_order 11, meaning the shift has not yet happened.
--   Step 2 (INSERT): uses ON CONFLICT (status_key) DO NOTHING so a duplicate
--           row is silently skipped.
--   Step 3 (next_status_id wiring): uses IS DISTINCT FROM guards so no-op
--           updates never write unnecessary rows.
--
-- Final display_order chain after this migration:
--   order_approved                = 10
--   order_sent_to_pharmacy        = 11  (NEW)
--   pharmacy_approval_pending     = 12
--   pharmacy_approved             = 13
--   fulfillment_in_progress       = 14
--   final_pharmacy_verification   = 15
--   in_transit                    = 16

DO $$
DECLARE
  v_new_id              UUID    := 'f0a1b2c3-d4e5-4f6a-9b8c-7d6e5f4a3b2c';
  v_order_approved_id   UUID;
  v_pharmacy_pending_id UUID;
  v_new_status_id       UUID;
BEGIN
  -- -----------------------------------------------------------------------
  -- Step 1: Shift display_order up by 1 for every active status at >= 11.
  -- Guard: only runs if pharmacy_approval_pending is still sitting at
  -- display_order 11, i.e. the shift has never happened.
  -- -----------------------------------------------------------------------
  IF EXISTS (
    SELECT 1 FROM public.order_statuses
    WHERE status_key = 'pharmacy_approval_pending'
      AND display_order = 11
      AND is_active = true
  ) THEN
    UPDATE public.order_statuses
    SET display_order = display_order + 1
    WHERE display_order >= 11
      AND is_active = true;

    RAISE NOTICE 'display_order shift applied (pharmacy_approval_pending moved to 12)';
  ELSE
    RAISE NOTICE 'display_order shift already applied – skipping';
  END IF;

  -- -----------------------------------------------------------------------
  -- Step 2: Resolve neighbour IDs (may already exist if partial re-run).
  -- -----------------------------------------------------------------------
  SELECT id INTO v_order_approved_id
  FROM public.order_statuses
  WHERE status_key = 'order_approved';

  SELECT id INTO v_pharmacy_pending_id
  FROM public.order_statuses
  WHERE status_key = 'pharmacy_approval_pending';

  -- -----------------------------------------------------------------------
  -- Step 3: Insert the new status at display_order 11.
  -- ON CONFLICT (status_key) DO NOTHING makes this a no-op on re-run.
  -- We also guard the next_status_id insert-time value so it resolves even
  -- on first-run where v_pharmacy_pending_id may be non-null.
  -- -----------------------------------------------------------------------
  INSERT INTO public.order_statuses (
    id,
    display_order,
    status_key,
    patient_status_label,
    patient_microcopy,
    admin_status_label,
    admin_microcopy,
    next_step_owner,
    is_terminal,
    next_status_id,
    is_active
  ) VALUES (
    v_new_id,
    11,
    'order_sent_to_pharmacy',
    'Sent to pharmacy',
    'Your prescription has been sent to the pharmacy for processing.',
    'Order sent to pharmacy',
    'Order dispatched via Telegra sendToPharmacyRecipients; waiting for prescription_sent_to_pharmacy webhook from Telegra.',
    'pharmacy',
    false,
    v_pharmacy_pending_id,
    true
  )
  ON CONFLICT (status_key) DO NOTHING;

  -- Resolve the actual ID of the status we just inserted (or that already existed).
  SELECT id INTO v_new_status_id
  FROM public.order_statuses
  WHERE status_key = 'order_sent_to_pharmacy';

  -- -----------------------------------------------------------------------
  -- Step 3b: Ensure display_order and is_active are correct.
  -- Handles partial-run recovery (e.g. row was inserted with wrong values
  -- by a previous failed attempt or manual intervention).
  -- -----------------------------------------------------------------------
  UPDATE public.order_statuses
  SET display_order = 11,
      is_active     = true
  WHERE id = v_new_status_id
    AND (display_order IS DISTINCT FROM 11 OR is_active IS DISTINCT FROM true);

  -- -----------------------------------------------------------------------
  -- Step 4: Wire next_status_id links.
  -- IS DISTINCT FROM guards mean these are no-ops if already correct.
  -- -----------------------------------------------------------------------

  -- order_approved → order_sent_to_pharmacy
  UPDATE public.order_statuses
  SET next_status_id = v_new_status_id
  WHERE id = v_order_approved_id
    AND next_status_id IS DISTINCT FROM v_new_status_id;

  -- order_sent_to_pharmacy → pharmacy_approval_pending
  -- (set only if insert did not already populate it, or if it points elsewhere)
  UPDATE public.order_statuses
  SET next_status_id = v_pharmacy_pending_id
  WHERE id = v_new_status_id
    AND next_status_id IS DISTINCT FROM v_pharmacy_pending_id;

  RAISE NOTICE 'order_sent_to_pharmacy migration complete (id=%)', v_new_status_id;
END $$;
