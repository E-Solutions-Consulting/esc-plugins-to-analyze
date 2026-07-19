-- Fetch one order's status history without evaluating the RLS policies on
-- every order in the tenant. The direct PostgREST query caused PostgreSQL to
-- materialize all accessible order ids before applying the requested order id.

CREATE OR REPLACE FUNCTION public.list_order_status_history(p_order_id uuid)
RETURNS TABLE (
  id uuid,
  order_id uuid,
  status_id uuid,
  changed_by uuid,
  changed_by_email text,
  notes text,
  created_at timestamptz,
  status_key text,
  admin_status_label text,
  patient_status_label text
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
SET row_security = off
AS $$
DECLARE
  caller_id uuid := auth.uid();
  order_tenant_id uuid;
BEGIN
  SELECT requested_order.tenant_id
  INTO order_tenant_id
  FROM public.orders AS requested_order
  WHERE requested_order.id = p_order_id;

  IF order_tenant_id IS NULL THEN
    RAISE EXCEPTION 'Order not found'
      USING ERRCODE = 'P0002';
  END IF;

  IF caller_id IS NULL OR NOT (
    public.is_tenant_admin(caller_id, order_tenant_id)
    OR public.has_customer_support_tenant_access(caller_id, order_tenant_id)
  ) THEN
    RAISE EXCEPTION 'Not authorized to view order status history'
      USING ERRCODE = '42501';
  END IF;

  RETURN QUERY
  SELECT
    history.id,
    history.order_id,
    history.status_id,
    history.changed_by,
    history.changed_by_email,
    history.notes,
    history.created_at,
    status.status_key,
    status.admin_status_label,
    status.patient_status_label
  FROM public.order_status_history AS history
  JOIN public.order_statuses AS status ON status.id = history.status_id
  WHERE history.order_id = p_order_id
  ORDER BY history.created_at DESC;
END;
$$;

REVOKE ALL ON FUNCTION public.list_order_status_history(uuid) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.list_order_status_history(uuid) TO authenticated;

