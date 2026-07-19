CREATE OR REPLACE FUNCTION public.claim_order_provider_platform_creation(
  p_order_id UUID,
  p_tenant_id UUID,
  p_tenant_integration_id UUID,
  p_request_id TEXT,
  p_stale_after_seconds INTEGER DEFAULT 900
)
RETURNS TABLE (
  claimed BOOLEAN,
  provider_order_id TEXT,
  in_progress BOOLEAN,
  link_id UUID,
  message TEXT
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_link public.order_provider_platform_links%ROWTYPE;
  v_started_at TIMESTAMPTZ;
  v_is_fresh_processing BOOLEAN := FALSE;
BEGIN
  INSERT INTO public.order_provider_platform_links (
    tenant_id,
    order_id,
    tenant_integration_id,
    metadata
  )
  VALUES (
    p_tenant_id,
    p_order_id,
    p_tenant_integration_id,
    jsonb_build_object(
      'source', 'order-lifecycle',
      'provider_order_creation_status', 'pending'
    )
  )
  ON CONFLICT (order_id, tenant_integration_id) DO NOTHING;

  SELECT *
  INTO v_link
  FROM public.order_provider_platform_links
  WHERE order_id = p_order_id
    AND tenant_id = p_tenant_id
    AND tenant_integration_id = p_tenant_integration_id
  FOR UPDATE;

  IF NOT FOUND THEN
    claimed := FALSE;
    provider_order_id := NULL;
    in_progress := FALSE;
    link_id := NULL;
    message := 'Provider platform link could not be found or created';
    RETURN NEXT;
    RETURN;
  END IF;

  IF v_link.provider_order_id IS NOT NULL AND btrim(v_link.provider_order_id) <> '' THEN
    claimed := FALSE;
    provider_order_id := v_link.provider_order_id;
    in_progress := FALSE;
    link_id := v_link.id;
    message := 'Provider order already exists';
    RETURN NEXT;
    RETURN;
  END IF;

  BEGIN
    v_started_at := (v_link.metadata->>'provider_order_creation_started_at')::TIMESTAMPTZ;
  EXCEPTION WHEN OTHERS THEN
    v_started_at := NULL;
  END;

  v_is_fresh_processing :=
    v_link.metadata->>'provider_order_creation_status' = 'processing'
    AND v_started_at IS NOT NULL
    AND v_started_at > now() - make_interval(secs => p_stale_after_seconds);

  IF v_is_fresh_processing THEN
    claimed := FALSE;
    provider_order_id := NULL;
    in_progress := TRUE;
    link_id := v_link.id;
    message := 'Provider order creation is already in progress';
    RETURN NEXT;
    RETURN;
  END IF;

  UPDATE public.order_provider_platform_links
  SET metadata = COALESCE(metadata, '{}'::jsonb) || jsonb_build_object(
      'source', 'order-lifecycle',
      'provider_order_creation_status', 'processing',
      'provider_order_creation_request_id', p_request_id,
      'provider_order_creation_started_at', now()
    )
  WHERE id = v_link.id
  RETURNING * INTO v_link;

  claimed := TRUE;
  provider_order_id := NULL;
  in_progress := FALSE;
  link_id := v_link.id;
  message := 'Provider order creation claimed';
  RETURN NEXT;
END;
$$;

GRANT EXECUTE ON FUNCTION public.claim_order_provider_platform_creation(
  UUID,
  UUID,
  UUID,
  TEXT,
  INTEGER
) TO service_role;
