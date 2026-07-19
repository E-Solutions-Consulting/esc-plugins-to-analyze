-- Capture request context on subscription lifecycle events.
-- This allows tracing event source (e.g., plan-api, stripe-webhook).

CREATE OR REPLACE FUNCTION public.log_subscription_lifecycle_event()
RETURNS TRIGGER AS $$
DECLARE
  event_type_value TEXT;
  changed_fields TEXT[] := ARRAY[]::TEXT[];
  actor_id UUID;
  actor_email TEXT;
  metadata_value JSONB := '{}'::jsonb;
  request_headers_raw TEXT;
  request_headers JSONB := '{}'::jsonb;
  request_source TEXT;
  request_id TEXT;
BEGIN
  request_headers_raw := current_setting('request.headers', true);

  IF request_headers_raw IS NOT NULL AND BTRIM(request_headers_raw) <> '' THEN
    BEGIN
      request_headers := request_headers_raw::jsonb;
    EXCEPTION
      WHEN OTHERS THEN
        request_headers := '{}'::jsonb;
    END;
  END IF;

  request_source := NULLIF(BTRIM(request_headers->>'x-request-source'), '');
  request_id := NULLIF(BTRIM(request_headers->>'x-request-id'), '');

  IF TG_OP = 'INSERT' THEN
    event_type_value := 'created';
  ELSIF TG_OP = 'UPDATE' THEN
    IF OLD.status IS DISTINCT FROM NEW.status THEN
      changed_fields := array_append(changed_fields, 'status');
    END IF;
    IF OLD.current_period_end_at IS DISTINCT FROM NEW.current_period_end_at THEN
      changed_fields := array_append(changed_fields, 'renewal_at');
    END IF;
    IF OLD.expires_at IS DISTINCT FROM NEW.expires_at THEN
      changed_fields := array_append(changed_fields, 'expires_at');
    END IF;
    IF OLD.paused_at IS DISTINCT FROM NEW.paused_at THEN
      changed_fields := array_append(changed_fields, 'paused_at');
    END IF;
    IF OLD.cancelled_at IS DISTINCT FROM NEW.cancelled_at THEN
      changed_fields := array_append(changed_fields, 'cancelled_at');
    END IF;

    IF array_length(changed_fields, 1) IS NULL THEN
      RETURN NEW;
    END IF;

    IF OLD.status IS DISTINCT FROM NEW.status THEN
      event_type_value := CASE NEW.status
        WHEN 'cancelled' THEN 'cancelled'
        WHEN 'paused' THEN 'paused'
        WHEN 'active' THEN 'resumed'
        ELSE 'status_changed'
      END;
    ELSIF OLD.current_period_end_at IS DISTINCT FROM NEW.current_period_end_at THEN
      event_type_value := 'renewal_date_changed';
    ELSIF OLD.expires_at IS DISTINCT FROM NEW.expires_at THEN
      event_type_value := 'expiration_date_changed';
    ELSE
      event_type_value := 'lifecycle_updated';
    END IF;

    metadata_value := jsonb_build_object('changed_fields', to_jsonb(changed_fields));
  ELSE
    RETURN NEW;
  END IF;

  metadata_value := metadata_value || jsonb_build_object(
    'request_source', COALESCE(request_source, 'unknown'),
    'request_id', request_id
  );

  SELECT au.id, au.email
  INTO actor_id, actor_email
  FROM public.admin_users au
  WHERE au.auth_user_id = auth.uid()
  LIMIT 1;

  INSERT INTO public.subscription_events (
    subscription_id,
    tenant_id,
    patient_id,
    event_type,
    old_status,
    new_status,
    old_renewal_at,
    new_renewal_at,
    old_expires_at,
    new_expires_at,
    old_paused_at,
    new_paused_at,
    old_cancelled_at,
    new_cancelled_at,
    changed_by,
    changed_by_email,
    metadata
  )
  VALUES (
    NEW.id,
    NEW.tenant_id,
    NEW.patient_id,
    event_type_value,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.status ELSE NULL END,
    NEW.status,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.current_period_end_at ELSE NULL END,
    NEW.current_period_end_at,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.expires_at ELSE NULL END,
    NEW.expires_at,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.paused_at ELSE NULL END,
    NEW.paused_at,
    CASE WHEN TG_OP = 'UPDATE' THEN OLD.cancelled_at ELSE NULL END,
    NEW.cancelled_at,
    actor_id,
    actor_email,
    metadata_value
  );

  RETURN NEW;
END;
$$ LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public;
