-- Optimize the tenant subscriptions list for staging-sized datasets.
--
-- Authorize the admin once instead of evaluating role-aware RLS helpers for
-- every subscription/patient row. Created/renewal sorts page subscriptions
-- before joining patients. Searches materialize matching patients first and
-- combine those IDs with abbreviated/full subscription-ID matches.

CREATE OR REPLACE FUNCTION public.list_tenant_subscriptions(
  p_tenant_id uuid,
  p_status public.subscription_status DEFAULT NULL,
  p_search text DEFAULT NULL,
  p_sort text DEFAULT 'created_desc',
  p_offset integer DEFAULT 0,
  p_limit integer DEFAULT 26
)
RETURNS TABLE (
  id uuid,
  status public.subscription_status,
  current_period_end_at timestamptz,
  metadata jsonb,
  created_at timestamptz,
  patient_first_name text,
  patient_last_name text
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
  compact_subscription_id text;
  padded_subscription_id text;
  subscription_id_lower uuid;
  subscription_id_upper uuid;
  bounded_offset integer := GREATEST(p_offset, 0);
  bounded_limit integer := LEAST(GREATEST(p_limit, 1), 101);
BEGIN
  IF caller_id IS NULL OR NOT (
    public.is_tenant_admin(caller_id, p_tenant_id)
    OR public.has_customer_support_tenant_access(caller_id, p_tenant_id)
  ) THEN
    RAISE EXCEPTION 'Not authorized to list subscriptions for this tenant'
      USING ERRCODE = '42501';
  END IF;

  IF p_sort NOT IN (
    'name_asc',
    'name_desc',
    'renewal_asc',
    'renewal_desc',
    'created_asc',
    'created_desc'
  ) THEN
    p_sort := 'created_desc';
  END IF;

  IF search_value IS NULL AND p_sort = 'created_desc' THEN
    RETURN QUERY
    WITH page_subscriptions AS MATERIALIZED (
      SELECT
        subscription.id,
        subscription.status,
        subscription.current_period_end_at,
        subscription.metadata,
        subscription.created_at,
        subscription.patient_id
      FROM public.subscriptions AS subscription
      WHERE subscription.tenant_id = p_tenant_id
        AND (p_status IS NULL OR subscription.status = p_status)
      ORDER BY subscription.created_at DESC, subscription.id DESC
      OFFSET bounded_offset
      LIMIT bounded_limit
    )
    SELECT
      subscription.id,
      subscription.status,
      subscription.current_period_end_at,
      subscription.metadata,
      subscription.created_at,
      patient.first_name,
      patient.last_name
    FROM page_subscriptions AS subscription
    JOIN public.patients AS patient
      ON patient.id = subscription.patient_id
     AND patient.tenant_id = p_tenant_id
    ORDER BY subscription.created_at DESC, subscription.id DESC;

    RETURN;
  END IF;

  IF search_value IS NULL AND p_sort = 'created_asc' THEN
    RETURN QUERY
    WITH page_subscriptions AS MATERIALIZED (
      SELECT
        subscription.id,
        subscription.status,
        subscription.current_period_end_at,
        subscription.metadata,
        subscription.created_at,
        subscription.patient_id
      FROM public.subscriptions AS subscription
      WHERE subscription.tenant_id = p_tenant_id
        AND (p_status IS NULL OR subscription.status = p_status)
      ORDER BY subscription.created_at ASC, subscription.id ASC
      OFFSET bounded_offset
      LIMIT bounded_limit
    )
    SELECT
      subscription.id,
      subscription.status,
      subscription.current_period_end_at,
      subscription.metadata,
      subscription.created_at,
      patient.first_name,
      patient.last_name
    FROM page_subscriptions AS subscription
    JOIN public.patients AS patient
      ON patient.id = subscription.patient_id
     AND patient.tenant_id = p_tenant_id
    ORDER BY subscription.created_at ASC, subscription.id ASC;

    RETURN;
  END IF;

  IF search_value IS NULL AND p_sort = 'renewal_asc' THEN
    RETURN QUERY
    WITH page_subscriptions AS MATERIALIZED (
      SELECT
        subscription.id,
        subscription.status,
        subscription.current_period_end_at,
        subscription.metadata,
        subscription.created_at,
        subscription.patient_id
      FROM public.subscriptions AS subscription
      WHERE subscription.tenant_id = p_tenant_id
        AND (p_status IS NULL OR subscription.status = p_status)
      ORDER BY
        subscription.current_period_end_at ASC NULLS LAST,
        subscription.id ASC
      OFFSET bounded_offset
      LIMIT bounded_limit
    )
    SELECT
      subscription.id,
      subscription.status,
      subscription.current_period_end_at,
      subscription.metadata,
      subscription.created_at,
      patient.first_name,
      patient.last_name
    FROM page_subscriptions AS subscription
    JOIN public.patients AS patient
      ON patient.id = subscription.patient_id
     AND patient.tenant_id = p_tenant_id
    ORDER BY
      subscription.current_period_end_at ASC NULLS LAST,
      subscription.id ASC;

    RETURN;
  END IF;

  IF search_value IS NULL AND p_sort = 'renewal_desc' THEN
    RETURN QUERY
    WITH page_subscriptions AS MATERIALIZED (
      SELECT
        subscription.id,
        subscription.status,
        subscription.current_period_end_at,
        subscription.metadata,
        subscription.created_at,
        subscription.patient_id
      FROM public.subscriptions AS subscription
      WHERE subscription.tenant_id = p_tenant_id
        AND (p_status IS NULL OR subscription.status = p_status)
      ORDER BY
        subscription.current_period_end_at DESC NULLS LAST,
        subscription.id DESC
      OFFSET bounded_offset
      LIMIT bounded_limit
    )
    SELECT
      subscription.id,
      subscription.status,
      subscription.current_period_end_at,
      subscription.metadata,
      subscription.created_at,
      patient.first_name,
      patient.last_name
    FROM page_subscriptions AS subscription
    JOIN public.patients AS patient
      ON patient.id = subscription.patient_id
     AND patient.tenant_id = p_tenant_id
    ORDER BY
      subscription.current_period_end_at DESC NULLS LAST,
      subscription.id DESC;

    RETURN;
  END IF;

  IF search_value IS NULL AND p_sort = 'name_asc' THEN
    RETURN QUERY
    SELECT
      subscription.id,
      subscription.status,
      subscription.current_period_end_at,
      subscription.metadata,
      subscription.created_at,
      patient.first_name,
      patient.last_name
    FROM public.patients AS patient
    JOIN public.subscriptions AS subscription
      ON subscription.patient_id = patient.id
     AND subscription.tenant_id = p_tenant_id
    WHERE patient.tenant_id = p_tenant_id
      AND (p_status IS NULL OR subscription.status = p_status)
    ORDER BY
      LOWER(patient.first_name) ASC NULLS LAST,
      LOWER(patient.last_name) ASC NULLS LAST,
      subscription.id ASC
    OFFSET bounded_offset
    LIMIT bounded_limit;

    RETURN;
  END IF;

  IF search_value IS NULL AND p_sort = 'name_desc' THEN
    RETURN QUERY
    SELECT
      subscription.id,
      subscription.status,
      subscription.current_period_end_at,
      subscription.metadata,
      subscription.created_at,
      patient.first_name,
      patient.last_name
    FROM public.patients AS patient
    JOIN public.subscriptions AS subscription
      ON subscription.patient_id = patient.id
     AND subscription.tenant_id = p_tenant_id
    WHERE patient.tenant_id = p_tenant_id
      AND (p_status IS NULL OR subscription.status = p_status)
    ORDER BY
      LOWER(patient.first_name) DESC NULLS LAST,
      LOWER(patient.last_name) DESC NULLS LAST,
      subscription.id DESC
    OFFSET bounded_offset
    LIMIT bounded_limit;

    RETURN;
  END IF;

  search_terms := regexp_split_to_array(search_value, '\s+');
  first_term := search_terms[1];
  IF cardinality(search_terms) >= 2 THEN
    second_term := search_terms[2];
  END IF;

  compact_subscription_id := LOWER(
    REPLACE(regexp_replace(search_value, '^sub-', '', 'i'), '-', '')
  );

  IF compact_subscription_id ~ '^[0-9a-f]{8,32}$' THEN
    padded_subscription_id := rpad(compact_subscription_id, 32, '0');
    subscription_id_lower := (
      substr(padded_subscription_id, 1, 8) || '-' ||
      substr(padded_subscription_id, 9, 4) || '-' ||
      substr(padded_subscription_id, 13, 4) || '-' ||
      substr(padded_subscription_id, 17, 4) || '-' ||
      substr(padded_subscription_id, 21, 12)
    )::uuid;

    padded_subscription_id := rpad(compact_subscription_id, 32, 'f');
    subscription_id_upper := (
      substr(padded_subscription_id, 1, 8) || '-' ||
      substr(padded_subscription_id, 9, 4) || '-' ||
      substr(padded_subscription_id, 13, 4) || '-' ||
      substr(padded_subscription_id, 17, 4) || '-' ||
      substr(padded_subscription_id, 21, 12)
    )::uuid;
  END IF;

  RETURN QUERY
  WITH matching_patients AS MATERIALIZED (
    SELECT patient.id
    FROM public.patients AS patient
    WHERE patient.tenant_id = p_tenant_id
      AND (
        patient.first_name ILIKE '%' || search_value || '%'
        OR patient.last_name ILIKE '%' || search_value || '%'
        OR patient.email ILIKE '%' || search_value || '%'
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
  ),
  candidate_subscriptions AS MATERIALIZED (
    SELECT subscription.id
    FROM matching_patients AS matching_patient
    JOIN public.subscriptions AS subscription
      ON subscription.patient_id = matching_patient.id
     AND subscription.tenant_id = p_tenant_id
    WHERE p_status IS NULL OR subscription.status = p_status

    UNION

    SELECT subscription.id
    FROM public.subscriptions AS subscription
    WHERE subscription.tenant_id = p_tenant_id
      AND (p_status IS NULL OR subscription.status = p_status)
      AND subscription_id_lower IS NOT NULL
      AND subscription.id BETWEEN subscription_id_lower AND subscription_id_upper
  )
  SELECT
    subscription.id,
    subscription.status,
    subscription.current_period_end_at,
    subscription.metadata,
    subscription.created_at,
    patient.first_name,
    patient.last_name
  FROM candidate_subscriptions AS candidate
  JOIN public.subscriptions AS subscription
    ON subscription.id = candidate.id
   AND subscription.tenant_id = p_tenant_id
  JOIN public.patients AS patient
    ON patient.id = subscription.patient_id
   AND patient.tenant_id = p_tenant_id
  ORDER BY
    CASE WHEN p_sort = 'name_asc' THEN LOWER(patient.first_name) END ASC NULLS LAST,
    CASE WHEN p_sort = 'name_asc' THEN LOWER(patient.last_name) END ASC NULLS LAST,
    CASE WHEN p_sort = 'name_desc' THEN LOWER(patient.first_name) END DESC NULLS LAST,
    CASE WHEN p_sort = 'name_desc' THEN LOWER(patient.last_name) END DESC NULLS LAST,
    CASE WHEN p_sort = 'renewal_asc' THEN subscription.current_period_end_at END ASC NULLS LAST,
    CASE WHEN p_sort = 'renewal_desc' THEN subscription.current_period_end_at END DESC NULLS LAST,
    CASE WHEN p_sort = 'created_asc' THEN subscription.created_at END ASC NULLS LAST,
    CASE WHEN p_sort = 'created_desc' THEN subscription.created_at END DESC NULLS LAST,
    CASE WHEN p_sort IN ('name_asc', 'renewal_asc', 'created_asc') THEN subscription.id END ASC,
    subscription.id DESC
  OFFSET bounded_offset
  LIMIT bounded_limit;
END;
$$;

REVOKE ALL ON FUNCTION public.list_tenant_subscriptions(
  uuid,
  public.subscription_status,
  text,
  text,
  integer,
  integer
) FROM PUBLIC;

GRANT EXECUTE ON FUNCTION public.list_tenant_subscriptions(
  uuid,
  public.subscription_status,
  text,
  text,
  integer,
  integer
) TO authenticated;

