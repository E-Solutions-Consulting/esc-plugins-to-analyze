-- Serve the tenant-admin subscriptions list without an exact count or a
-- client-side patient-id fan-out. The function remains subject to RLS because
-- it executes with the caller's privileges.

CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_created_at_id
  ON public.subscriptions (tenant_id, created_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_status_created_at_id
  ON public.subscriptions (tenant_id, status, created_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_renewal_asc_id
  ON public.subscriptions (
    tenant_id,
    current_period_end_at ASC NULLS LAST,
    id ASC
  );

CREATE INDEX IF NOT EXISTS idx_subscriptions_tenant_renewal_desc_id
  ON public.subscriptions (
    tenant_id,
    current_period_end_at DESC NULLS LAST,
    id DESC
  );

CREATE INDEX IF NOT EXISTS idx_patients_tenant_lower_name_id
  ON public.patients (
    tenant_id,
    lower(first_name),
    lower(last_name),
    id
  );

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
SECURITY INVOKER
SET search_path = public
AS $$
DECLARE
  search_value text := NULLIF(BTRIM(p_search), '');
  search_terms text[];
  first_term text;
  second_term text;
  compact_subscription_id text;
  padded_subscription_id text;
  subscription_id_lower uuid;
  subscription_id_upper uuid;
  order_clause text;
  query_text text;
BEGIN
  search_terms := regexp_split_to_array(search_value, '\s+');
  first_term := search_terms[1];
  IF cardinality(search_terms) >= 2 THEN
    second_term := search_terms[2];
  END IF;

  compact_subscription_id := lower(
    replace(regexp_replace(COALESCE(search_value, ''), '^sub-', '', 'i'), '-', '')
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

  order_clause := CASE p_sort
    WHEN 'name_asc' THEN
      'lower(p.first_name) ASC NULLS LAST, lower(p.last_name) ASC NULLS LAST, s.id ASC'
    WHEN 'name_desc' THEN
      'lower(p.first_name) DESC NULLS LAST, lower(p.last_name) DESC NULLS LAST, s.id DESC'
    WHEN 'renewal_asc' THEN
      's.current_period_end_at ASC NULLS LAST, s.id ASC'
    WHEN 'renewal_desc' THEN
      's.current_period_end_at DESC NULLS LAST, s.id DESC'
    WHEN 'created_asc' THEN
      's.created_at ASC, s.id ASC'
    ELSE
      's.created_at DESC, s.id DESC'
  END;

  query_text := format($query$
    SELECT
      s.id,
      s.status,
      s.current_period_end_at,
      s.metadata,
      s.created_at,
      p.first_name,
      p.last_name
    FROM public.subscriptions AS s
    JOIN public.patients AS p ON p.id = s.patient_id
    WHERE s.tenant_id = $1
      AND p.tenant_id = $1
      AND ($2::public.subscription_status IS NULL OR s.status = $2)
      AND (
        $3::text IS NULL
        OR p.first_name ILIKE '%%' || $3 || '%%'
        OR p.last_name ILIKE '%%' || $3 || '%%'
        OR p.email ILIKE '%%' || $3 || '%%'
        OR (
          $4::text IS NOT NULL
          AND $5::text IS NOT NULL
          AND (
            (
              p.first_name ILIKE '%%' || $4 || '%%'
              AND p.last_name ILIKE '%%' || $5 || '%%'
            )
            OR (
              p.first_name ILIKE '%%' || $5 || '%%'
              AND p.last_name ILIKE '%%' || $4 || '%%'
            )
          )
        )
        OR (
          $6::uuid IS NOT NULL
          AND s.id BETWEEN $6 AND $7
        )
      )
    ORDER BY %s
    OFFSET $8
    LIMIT $9
  $query$, order_clause);

  RETURN QUERY EXECUTE query_text
    USING
      p_tenant_id,
      p_status,
      search_value,
      first_term,
      second_term,
      subscription_id_lower,
      subscription_id_upper,
      GREATEST(p_offset, 0),
      LEAST(GREATEST(p_limit, 1), 101);
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
