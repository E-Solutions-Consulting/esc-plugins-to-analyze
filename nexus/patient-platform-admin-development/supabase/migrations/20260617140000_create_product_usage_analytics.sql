-- Product Usage Tracking — first-party behavioural analytics ("our own Mixpanel").
-- See docs/AnalyticsTracking.md. This migration creates the Supabase HOT STORE
-- (~5 days) for client behavioural events captured by patient-platform-patient-ui
-- (web + Despia mobile), for both authenticated and guest users, plus the
-- per-tenant tracking-settings table that gates collection.
--
-- Conventions mirrored from existing migrations:
--   * idempotent (IF NOT EXISTS + DO $$ policy guards)
--   * tenant-scoped via public.is_tenant_admin(auth.uid(), tenant_id)
--   * platform superadmin via public.is_platform_superadmin(auth.uid())
--   * ingestion writes go through service_role (edge function), never the client
--   * updated_at maintained by public.update_updated_at_column()
--
-- RLS posture (see docs §4.3): behavioural events are PHI-adjacent, so tenant
-- admins may READ their own tenant's data, the service role does all writes, and
-- platform superadmin is intentionally EXCLUDED from the raw event/session/device
-- hot store (cross-tenant analytics is served from the warehouse). Superadmin DOES
-- manage the platform-default settings row and the event-type catalog.

-- ---------------------------------------------------------------------------
-- 1. tenant_analytics_settings — platform default (tenant_id NULL) + per-tenant
--    overrides. The analytics-api /config endpoint returns the EFFECTIVE flags.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.tenant_analytics_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  -- NULL = the single platform-default row; non-NULL = a tenant override.
  tenant_id UUID
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  -- Master switch; when false the client sends nothing for this tenant.
  tracking_enabled BOOLEAN NOT NULL DEFAULT false,
  -- Per-category opt-in toggles (surfaced as switches in the admin settings tab).
  track_page_views BOOLEAN NOT NULL DEFAULT true,
  track_activity_events BOOLEAN NOT NULL DEFAULT true,
  track_time_on_page BOOLEAN NOT NULL DEFAULT true,
  track_device_info BOOLEAN NOT NULL DEFAULT true,
  track_utm_attribution BOOLEAN NOT NULL DEFAULT true,
  track_guest_sessions BOOLEAN NOT NULL DEFAULT true,
  -- Operational knobs.
  session_idle_timeout_minutes INTEGER NOT NULL DEFAULT 30,
  hot_retention_days INTEGER NOT NULL DEFAULT 5,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- One settings row per tenant; one (and only one) platform-default row.
CREATE UNIQUE INDEX IF NOT EXISTS tenant_analytics_settings_tenant_id_key
  ON public.tenant_analytics_settings (tenant_id)
  WHERE tenant_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS tenant_analytics_settings_platform_default_key
  ON public.tenant_analytics_settings ((tenant_id IS NULL))
  WHERE tenant_id IS NULL;

-- ---------------------------------------------------------------------------
-- 2. analytics_devices — one row per (tenant, anonymous_id). anonymous_id is a
--    client-generated UUID persisted in localStorage so guest -> auth behaviour
--    can be stitched to a patient.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.analytics_devices (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  anonymous_id TEXT NOT NULL,
  platform TEXT,            -- web | ios | android
  device_type TEXT,         -- mobile | tablet | desktop
  os_name TEXT,
  os_version TEXT,
  app_version TEXT,
  browser_name TEXT,
  browser_version TEXT,
  user_agent TEXT,
  screen_width INTEGER,
  screen_height INTEGER,
  onesignal_player_id TEXT,
  locale TEXT,
  timezone TEXT,
  first_seen_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  last_seen_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS analytics_devices_tenant_anon_key
  ON public.analytics_devices (tenant_id, anonymous_id);

-- ---------------------------------------------------------------------------
-- 3. analytics_sessions — opened on first event, closed on idle/explicit end.
--    patient_id / auth_user_id backfilled on identify.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.analytics_sessions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  device_id UUID
    REFERENCES public.analytics_devices(id) ON DELETE SET NULL,
  anonymous_id TEXT NOT NULL,
  -- Identity (null while guest).
  patient_id UUID
    REFERENCES public.patients(id) ON DELETE SET NULL,
  auth_user_id UUID,
  is_authenticated BOOLEAN NOT NULL DEFAULT false,
  -- Acquisition context.
  entry_url TEXT,
  referrer TEXT,
  utm JSONB NOT NULL DEFAULT '{}'::jsonb,  -- source/medium/campaign/term/content
  -- Lifecycle + rollups (maintained server-side as events arrive).
  started_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  last_activity_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  ended_at TIMESTAMP WITH TIME ZONE,
  duration_seconds INTEGER NOT NULL DEFAULT 0,
  page_view_count INTEGER NOT NULL DEFAULT 0,
  event_count INTEGER NOT NULL DEFAULT 0,
  -- Warehouse export handshake.
  exported_to_warehouse BOOLEAN NOT NULL DEFAULT false,
  exported_at TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS analytics_sessions_tenant_started_idx
  ON public.analytics_sessions (tenant_id, started_at DESC);
CREATE INDEX IF NOT EXISTS analytics_sessions_device_idx
  ON public.analytics_sessions (device_id);
CREATE INDEX IF NOT EXISTS analytics_sessions_patient_idx
  ON public.analytics_sessions (patient_id);
CREATE INDEX IF NOT EXISTS analytics_sessions_export_idx
  ON public.analytics_sessions (tenant_id, started_at)
  WHERE exported_to_warehouse = false;

-- ---------------------------------------------------------------------------
-- 4. analytics_event_types — governance catalog of known event names. Unknown
--    events are still accepted by the API (and flagged); this powers the admin
--    catalog and the warehouse vocabulary.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.analytics_event_types (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key TEXT NOT NULL UNIQUE,
  category TEXT,
  description TEXT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------------
-- 5. analytics_events — the firehose. Idempotent on (tenant_id, client_event_id);
--    event_date is the generated partition/prune/warehouse key.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.analytics_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL
    REFERENCES public.tenants(id) ON DELETE CASCADE,
  session_id UUID
    REFERENCES public.analytics_sessions(id) ON DELETE SET NULL,
  device_id UUID
    REFERENCES public.analytics_devices(id) ON DELETE SET NULL,
  anonymous_id TEXT NOT NULL,
  patient_id UUID
    REFERENCES public.patients(id) ON DELETE SET NULL,
  auth_user_id UUID,
  -- page_view | track | identify | session_start | session_end
  event_type TEXT NOT NULL,
  event_name TEXT,
  page_path TEXT,
  page_title TEXT,
  referrer TEXT,
  duration_ms INTEGER,          -- time-on-page / activity duration
  properties JSONB NOT NULL DEFAULT '{}'::jsonb,
  -- Idempotency for safe client retries / offline replay.
  client_event_id TEXT NOT NULL,
  occurred_at TIMESTAMP WITH TIME ZONE NOT NULL,   -- client clock
  received_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(), -- server clock
  -- Partition / prune / warehouse partition key.
  event_date DATE GENERATED ALWAYS AS ((received_at AT TIME ZONE 'UTC')::date) STORED,
  -- Warehouse export handshake.
  exported_to_warehouse BOOLEAN NOT NULL DEFAULT false,
  exported_at TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS analytics_events_idempotency_key
  ON public.analytics_events (tenant_id, client_event_id);
CREATE INDEX IF NOT EXISTS analytics_events_tenant_occurred_idx
  ON public.analytics_events (tenant_id, occurred_at DESC);
CREATE INDEX IF NOT EXISTS analytics_events_session_idx
  ON public.analytics_events (session_id);
CREATE INDEX IF NOT EXISTS analytics_events_anon_idx
  ON public.analytics_events (anonymous_id);
CREATE INDEX IF NOT EXISTS analytics_events_export_idx
  ON public.analytics_events (event_date)
  WHERE exported_to_warehouse = false;

-- ---------------------------------------------------------------------------
-- updated_at triggers (idempotent)
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  t TEXT;
  tables TEXT[] := ARRAY[
    'tenant_analytics_settings',
    'analytics_devices',
    'analytics_sessions',
    'analytics_event_types'
  ];
BEGIN
  FOREACH t IN ARRAY tables LOOP
    IF NOT EXISTS (
      SELECT 1 FROM pg_trigger
      WHERE tgname = 'update_' || t || '_updated_at'
        AND tgrelid = ('public.' || t)::regclass
    ) THEN
      EXECUTE format(
        'CREATE TRIGGER update_%1$s_updated_at
           BEFORE UPDATE ON public.%1$s
           FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column()',
        t
      );
    END IF;
  END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- RLS
-- ---------------------------------------------------------------------------
ALTER TABLE public.tenant_analytics_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.analytics_devices ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.analytics_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.analytics_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.analytics_event_types ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  -- ---- service_role full access on all five tables (ingestion + ops) ----
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='tenant_analytics_settings' AND policyname='service_role_full_access_tenant_analytics_settings') THEN
    CREATE POLICY "service_role_full_access_tenant_analytics_settings" ON public.tenant_analytics_settings FOR ALL TO service_role USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_devices' AND policyname='service_role_full_access_analytics_devices') THEN
    CREATE POLICY "service_role_full_access_analytics_devices" ON public.analytics_devices FOR ALL TO service_role USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_sessions' AND policyname='service_role_full_access_analytics_sessions') THEN
    CREATE POLICY "service_role_full_access_analytics_sessions" ON public.analytics_sessions FOR ALL TO service_role USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_events' AND policyname='service_role_full_access_analytics_events') THEN
    CREATE POLICY "service_role_full_access_analytics_events" ON public.analytics_events FOR ALL TO service_role USING (true) WITH CHECK (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_event_types' AND policyname='service_role_full_access_analytics_event_types') THEN
    CREATE POLICY "service_role_full_access_analytics_event_types" ON public.analytics_event_types FOR ALL TO service_role USING (true) WITH CHECK (true);
  END IF;

  -- ---- tenant_analytics_settings: tenant admins manage their own row + read platform default ----
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='tenant_analytics_settings' AND policyname='Tenant admins can view their analytics settings') THEN
    CREATE POLICY "Tenant admins can view their analytics settings" ON public.tenant_analytics_settings
      FOR SELECT USING (tenant_id IS NULL OR public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='tenant_analytics_settings' AND policyname='Tenant admins can insert their analytics settings') THEN
    CREATE POLICY "Tenant admins can insert their analytics settings" ON public.tenant_analytics_settings
      FOR INSERT WITH CHECK (tenant_id IS NOT NULL AND public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='tenant_analytics_settings' AND policyname='Tenant admins can update their analytics settings') THEN
    CREATE POLICY "Tenant admins can update their analytics settings" ON public.tenant_analytics_settings
      FOR UPDATE USING (tenant_id IS NOT NULL AND public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;
  -- platform superadmin manages everything incl. the platform-default row.
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='tenant_analytics_settings' AND policyname='Superadmins can manage all analytics settings') THEN
    CREATE POLICY "Superadmins can manage all analytics settings" ON public.tenant_analytics_settings
      FOR ALL USING (public.is_platform_superadmin(auth.uid())) WITH CHECK (public.is_platform_superadmin(auth.uid()));
  END IF;

  -- ---- analytics_devices / sessions / events: tenant admins READ their tenant only ----
  -- (No superadmin read on raw behavioural data — see header note.)
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_devices' AND policyname='Tenant admins can view their analytics devices') THEN
    CREATE POLICY "Tenant admins can view their analytics devices" ON public.analytics_devices
      FOR SELECT USING (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_sessions' AND policyname='Tenant admins can view their analytics sessions') THEN
    CREATE POLICY "Tenant admins can view their analytics sessions" ON public.analytics_sessions
      FOR SELECT USING (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_events' AND policyname='Tenant admins can view their analytics events') THEN
    CREATE POLICY "Tenant admins can view their analytics events" ON public.analytics_events
      FOR SELECT USING (public.is_tenant_admin(auth.uid(), tenant_id));
  END IF;

  -- ---- analytics_event_types: any tenant admin may read the catalog; superadmin manages it ----
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_event_types' AND policyname='Authenticated can view analytics event types') THEN
    CREATE POLICY "Authenticated can view analytics event types" ON public.analytics_event_types
      FOR SELECT TO authenticated USING (true);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='analytics_event_types' AND policyname='Superadmins can manage analytics event types') THEN
    CREATE POLICY "Superadmins can manage analytics event types" ON public.analytics_event_types
      FOR ALL USING (public.is_platform_superadmin(auth.uid())) WITH CHECK (public.is_platform_superadmin(auth.uid()));
  END IF;
END $$;

-- ---------------------------------------------------------------------------
-- Seed: the single platform-default settings row (tracking OFF by default;
-- each tenant opts in via the Product Usage Tracking settings tab).
-- ---------------------------------------------------------------------------
INSERT INTO public.tenant_analytics_settings (tenant_id, tracking_enabled)
SELECT NULL, false
WHERE NOT EXISTS (
  SELECT 1 FROM public.tenant_analytics_settings WHERE tenant_id IS NULL
);

-- ---------------------------------------------------------------------------
-- Session counter rollup RPC (best-effort; called by analytics-api after insert).
-- SECURITY DEFINER so the service role can update counters; granted to service_role.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.bump_analytics_session_counters(
  p_session_id UUID,
  p_tenant_id UUID,
  p_event_delta INTEGER,
  p_page_view_delta INTEGER
) RETURNS void
LANGUAGE sql
SECURITY DEFINER
SET search_path = public
AS $$
  UPDATE public.analytics_sessions
     SET event_count = event_count + GREATEST(COALESCE(p_event_delta, 0), 0),
         page_view_count = page_view_count + GREATEST(COALESCE(p_page_view_delta, 0), 0),
         last_activity_at = now(),
         duration_seconds = GREATEST(0, EXTRACT(EPOCH FROM (now() - started_at))::int)
   WHERE id = p_session_id
     AND tenant_id = p_tenant_id;
$$;

REVOKE ALL ON FUNCTION public.bump_analytics_session_counters(UUID, UUID, INTEGER, INTEGER) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.bump_analytics_session_counters(UUID, UUID, INTEGER, INTEGER) TO service_role;

-- Seed: canonical event-type catalog (permissive — unknown events still accepted).
INSERT INTO public.analytics_event_types (key, category, description)
VALUES
  ('page_view',                    'navigation', 'A page/screen view'),
  ('session_start',                'session',    'Start of a session'),
  ('session_end',                  'session',    'End of a session'),
  ('product_viewed',               'commerce',   'Viewed a product detail page'),
  ('checkout_started',             'commerce',   'Began the checkout flow'),
  ('checkout_completed',           'commerce',   'Completed checkout / order paid'),
  ('questionnaire_started',        'onboarding', 'Began a questionnaire'),
  ('questionnaire_step_completed', 'onboarding', 'Completed a questionnaire step'),
  ('questionnaire_completed',      'onboarding', 'Completed a questionnaire'),
  ('signup_started',               'auth',       'Began account creation'),
  ('signup_completed',             'auth',       'Completed account creation'),
  ('login',                        'auth',       'Authenticated sign-in')
ON CONFLICT (key) DO NOTHING;
