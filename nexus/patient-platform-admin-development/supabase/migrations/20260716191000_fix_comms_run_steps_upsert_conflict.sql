-- comms-execute-node logs every step with
--   upsert(..., { onConflict: "enrollment_id,node_id" })
-- which PostgREST turns into a bare ON CONFLICT (enrollment_id, node_id).
-- The original index (uq_comms_run_steps_enrollment_node) was PARTIAL
-- (WHERE node_id IS NOT NULL), and Postgres cannot match a bare ON CONFLICT
-- column list to a partial index → 42P10 on EVERY insert. Because logStep
-- never checked the error, comms_run_steps stayed empty in every environment:
-- no step history in the Activity view, and the executor's idempotency guard
-- (priorStep) never fired.
--
-- A full unique index has the SAME semantics here: in Postgres, NULLs are
-- distinct in unique indexes, so rows with node_id IS NULL remain unconstrained
-- exactly as before — the WHERE clause never restricted anything.
DROP INDEX IF EXISTS public.uq_comms_run_steps_enrollment_node;
CREATE UNIQUE INDEX IF NOT EXISTS uq_comms_run_steps_enrollment_node
  ON public.comms_run_steps (enrollment_id, node_id);
