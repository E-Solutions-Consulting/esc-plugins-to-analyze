-- Returns distinct shipping state codes for a tenant's orders
CREATE OR REPLACE FUNCTION get_tenant_order_states(p_tenant_id uuid)
RETURNS TABLE(state_code text)
LANGUAGE sql
STABLE
SECURITY DEFINER
AS $$
  SELECT DISTINCT UPPER(TRIM(shipping_state)) AS state_code
  FROM orders
  WHERE tenant_id = p_tenant_id
    AND shipping_state IS NOT NULL
    AND TRIM(shipping_state) <> ''
  ORDER BY state_code;
$$;

GRANT EXECUTE ON FUNCTION get_tenant_order_states(uuid) TO authenticated;
