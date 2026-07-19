-- Serve the tenant-admin Patients list with count-free keyset pagination and
-- database-side search. Authorization is evaluated once; all returned rows
-- remain explicitly scoped to the requested tenant.

CREATE INDEX IF NOT EXISTS idx_patients_tenant_created_at_id_desc
  ON public.patients (tenant_id, created_at DESC, id DESC);

CREATE OR REPLACE FUNCTION public.list_tenant_patients(
  p_tenant_id uuid,
  p_search text DEFAULT NULL,
  p_cursor_created_at timestamptz DEFAULT NULL,
  p_cursor_id uuid DEFAULT NULL,
  p_limit integer DEFAULT 26
)
RETURNS TABLE (
  id uuid,
  first_name text,
  last_name text,
  email text,
  phone text,
  access_status public.patient_access_status,
  metadata jsonb,
  created_at timestamptz
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
SET row_security = off
SET plan_cache_mode = force_custom_plan
AS $$
DECLARE
  caller_id uuid := auth.uid();
  search_value text := NULLIF(BTRIM(p_search), '');
  search_terms text[];
  first_term text;
  second_term text;
  bounded_limit integer := LEAST(GREATEST(p_limit, 1), 101);
BEGIN
  IF caller_id IS NULL OR NOT (
    public.is_tenant_admin(caller_id, p_tenant_id)
    OR public.has_customer_support_tenant_access(caller_id, p_tenant_id)
  ) THEN
    RAISE EXCEPTION 'Not authorized to list patients for this tenant'
      USING ERRCODE = '42501';
  END IF;

  IF (p_cursor_created_at IS NULL) <> (p_cursor_id IS NULL) THEN
    RAISE EXCEPTION 'Both cursor values must be provided together';
  END IF;

  IF search_value IS NULL THEN
    RETURN QUERY
    SELECT
      patient.id,
      patient.first_name,
      patient.last_name,
      patient.email,
      patient.phone,
      patient.access_status,
      patient.metadata,
      patient.created_at
    FROM public.patients AS patient
    WHERE patient.tenant_id = p_tenant_id
      AND (
        p_cursor_created_at IS NULL
        OR (patient.created_at, patient.id) < (p_cursor_created_at, p_cursor_id)
      )
    ORDER BY patient.created_at DESC, patient.id DESC
    LIMIT bounded_limit;

    RETURN;
  END IF;

  search_terms := regexp_split_to_array(search_value, '\s+');
  first_term := search_terms[1];
  IF cardinality(search_terms) >= 2 THEN
    second_term := search_terms[2];
  END IF;

  RETURN QUERY
  WITH matching_patients AS MATERIALIZED (
    SELECT
      patient.id,
      patient.first_name,
      patient.last_name,
      patient.email,
      patient.phone,
      patient.access_status,
      patient.metadata,
      patient.created_at
    FROM public.patients AS patient
    WHERE patient.tenant_id = p_tenant_id
      AND (
        patient.first_name ILIKE '%' || search_value || '%'
        OR patient.last_name ILIKE '%' || search_value || '%'
        OR patient.email ILIKE '%' || search_value || '%'
        OR patient.phone ILIKE '%' || search_value || '%'
        OR (
          first_term IS NOT NULL
          AND second_term IS NOT NULL
          AND (
            (
              patient.first_name ILIKE '%' || first_term || '%'
              AND patient.last_name ILIKE '%' || second_term || '%'
            )
            OR (
              patient.first_name ILIKE '%' || second_term || '%'
              AND patient.last_name ILIKE '%' || first_term || '%'
            )
          )
        )
      )
  )
  SELECT
    patient.id,
    patient.first_name,
    patient.last_name,
    patient.email,
    patient.phone,
    patient.access_status,
    patient.metadata,
    patient.created_at
  FROM matching_patients AS patient
  WHERE p_cursor_created_at IS NULL
    OR (patient.created_at, patient.id) < (p_cursor_created_at, p_cursor_id)
  ORDER BY patient.created_at DESC, patient.id DESC
  LIMIT bounded_limit;
END;
$$;

REVOKE ALL ON FUNCTION public.list_tenant_patients(
  uuid,
  text,
  timestamptz,
  uuid,
  integer
) FROM PUBLIC;

GRANT EXECUTE ON FUNCTION public.list_tenant_patients(
  uuid,
  text,
  timestamptz,
  uuid,
  integer
) TO authenticated;

