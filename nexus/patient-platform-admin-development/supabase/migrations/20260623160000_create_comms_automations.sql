-- Communications Automations — no-code journey builder (email + SMS) driven by
-- first-party analytics / subscription / order data, with native n8n hand-off.
-- See docs/CommunicationsAutomations.md.
--
-- All tables are tenant-scoped and follow the existing RLS convention:
--   tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
-- updated_at is maintained by the shared public.update_updated_at_column() trigger.

-- ---------------------------------------------------------------------------
-- Enums
-- ---------------------------------------------------------------------------
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'comms_automation_status') THEN
    CREATE TYPE public.comms_automation_status AS ENUM ('draft', 'active', 'paused', 'archived');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'comms_node_type') THEN
    CREATE TYPE public.comms_node_type AS ENUM (
      'trigger', 'email', 'sms', 'delay', 'wait_until', 'branch', 'multi_split', 'n8n', 'exit'
    );
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'comms_enrollment_status') THEN
    CREATE TYPE public.comms_enrollment_status AS ENUM (
      'active', 'completed', 'exited', 'failed', 'cancelled'
    );
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'comms_run_step_status') THEN
    CREATE TYPE public.comms_run_step_status AS ENUM (
      'pending', 'sent', 'skipped', 'failed', 'scheduled'
    );
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'comms_channel') THEN
    CREATE TYPE public.comms_channel AS ENUM ('email', 'sms');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'comms_n8n_mode') THEN
    CREATE TYPE public.comms_n8n_mode AS ENUM ('projects', 'webhook');
  END IF;
END $$;

-- ---------------------------------------------------------------------------
-- comms_automations — one automation / journey / "recipe"
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_automations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  description TEXT,
  status public.comms_automation_status NOT NULL DEFAULT 'draft',
  -- The trigger definition: { kind: 'event'|'subscription'|'relative_time'|'order'|'manual', ... }
  trigger_config JSONB NOT NULL DEFAULT '{}'::jsonb,
  -- Re-enrollment / dedup policy, quiet hours, etc.
  settings JSONB NOT NULL DEFAULT '{}'::jsonb,
  version INTEGER NOT NULL DEFAULT 1,
  enrolled_count INTEGER NOT NULL DEFAULT 0,
  last_triggered_at TIMESTAMPTZ,
  created_by UUID REFERENCES public.admin_users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, name)
);

CREATE INDEX IF NOT EXISTS idx_comms_automations_tenant_id
  ON public.comms_automations(tenant_id);
CREATE INDEX IF NOT EXISTS idx_comms_automations_status
  ON public.comms_automations(tenant_id, status);

-- ---------------------------------------------------------------------------
-- comms_automation_nodes — canvas nodes
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_automation_nodes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  automation_id UUID NOT NULL REFERENCES public.comms_automations(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  node_type public.comms_node_type NOT NULL,
  -- Channel/template/delay/branch/n8n config, shape depends on node_type.
  config JSONB NOT NULL DEFAULT '{}'::jsonb,
  -- Canvas placement.
  position JSONB NOT NULL DEFAULT '{"x":0,"y":0}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comms_nodes_automation_id
  ON public.comms_automation_nodes(automation_id);
CREATE INDEX IF NOT EXISTS idx_comms_nodes_tenant_id
  ON public.comms_automation_nodes(tenant_id);

-- ---------------------------------------------------------------------------
-- comms_automation_edges — directed edges between nodes
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_automation_edges (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  automation_id UUID NOT NULL REFERENCES public.comms_automations(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  source_node_id UUID NOT NULL REFERENCES public.comms_automation_nodes(id) ON DELETE CASCADE,
  target_node_id UUID NOT NULL REFERENCES public.comms_automation_nodes(id) ON DELETE CASCADE,
  -- Branch label: NULL for linear, 'true'/'false' for branch, cohort key for multi_split.
  branch_label TEXT,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comms_edges_automation_id
  ON public.comms_automation_edges(automation_id);
CREATE INDEX IF NOT EXISTS idx_comms_edges_source
  ON public.comms_automation_edges(source_node_id);
CREATE INDEX IF NOT EXISTS idx_comms_edges_tenant_id
  ON public.comms_automation_edges(tenant_id);

-- ---------------------------------------------------------------------------
-- comms_templates — reusable email/SMS message templates (per tenant)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  channel public.comms_channel NOT NULL,
  name TEXT NOT NULL,
  -- Email only.
  subject TEXT,
  -- HTML for email, plain text for SMS. Contains {{placeholder}} tokens.
  body TEXT NOT NULL DEFAULT '',
  -- Cached list of placeholder keys referenced, for validation/preview.
  placeholders JSONB NOT NULL DEFAULT '[]'::jsonb,
  is_archived BOOLEAN NOT NULL DEFAULT false,
  created_by UUID REFERENCES public.admin_users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, channel, name)
);

CREATE INDEX IF NOT EXISTS idx_comms_templates_tenant_id
  ON public.comms_templates(tenant_id);

-- ---------------------------------------------------------------------------
-- comms_enrollments — a patient's live run through an automation
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_enrollments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  automation_id UUID NOT NULL REFERENCES public.comms_automations(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID REFERENCES public.patients(id) ON DELETE SET NULL,
  status public.comms_enrollment_status NOT NULL DEFAULT 'active',
  current_node_id UUID REFERENCES public.comms_automation_nodes(id) ON DELETE SET NULL,
  -- Snapshot of resolved trigger context (subscription/order/event ids + placeholder values).
  context JSONB NOT NULL DEFAULT '{}'::jsonb,
  -- Idempotency: dedup a patient against the same triggering entity.
  dedup_key TEXT,
  enrolled_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  completed_at TIMESTAMPTZ,
  last_error TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comms_enrollments_automation_id
  ON public.comms_enrollments(automation_id);
CREATE INDEX IF NOT EXISTS idx_comms_enrollments_tenant_id
  ON public.comms_enrollments(tenant_id);
CREATE INDEX IF NOT EXISTS idx_comms_enrollments_patient_id
  ON public.comms_enrollments(patient_id);
CREATE INDEX IF NOT EXISTS idx_comms_enrollments_status
  ON public.comms_enrollments(tenant_id, status);
-- Prevent duplicate enrollment for the same automation + dedup key.
CREATE UNIQUE INDEX IF NOT EXISTS uq_comms_enrollments_dedup
  ON public.comms_enrollments(automation_id, dedup_key)
  WHERE dedup_key IS NOT NULL;

-- ---------------------------------------------------------------------------
-- comms_run_steps — per-node execution log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_run_steps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  enrollment_id UUID NOT NULL REFERENCES public.comms_enrollments(id) ON DELETE CASCADE,
  automation_id UUID NOT NULL REFERENCES public.comms_automations(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  node_id UUID REFERENCES public.comms_automation_nodes(id) ON DELETE SET NULL,
  node_type public.comms_node_type NOT NULL,
  status public.comms_run_step_status NOT NULL DEFAULT 'pending',
  -- Provider message id (Resend/Twilio) or n8n execution id; never raw PHI.
  provider_message_id TEXT,
  error TEXT,
  -- Small non-PHI metadata (e.g. masked recipient, integration key).
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comms_run_steps_enrollment_id
  ON public.comms_run_steps(enrollment_id);
CREATE INDEX IF NOT EXISTS idx_comms_run_steps_tenant_id
  ON public.comms_run_steps(tenant_id);
CREATE INDEX IF NOT EXISTS idx_comms_run_steps_created_at
  ON public.comms_run_steps(created_at DESC);
-- Idempotency guard: one terminal step per (enrollment, node).
CREATE UNIQUE INDEX IF NOT EXISTS uq_comms_run_steps_enrollment_node
  ON public.comms_run_steps(enrollment_id, node_id)
  WHERE node_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- comms_scheduled_jobs — due-time queue for delays & relative-time triggers
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_scheduled_jobs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  automation_id UUID NOT NULL REFERENCES public.comms_automations(id) ON DELETE CASCADE,
  enrollment_id UUID REFERENCES public.comms_enrollments(id) ON DELETE CASCADE,
  -- Node to execute when due (for delay/wait_until). NULL = relative-time enrollment job.
  node_id UUID REFERENCES public.comms_automation_nodes(id) ON DELETE CASCADE,
  job_kind TEXT NOT NULL DEFAULT 'advance', -- 'advance' | 'relative_time_enroll'
  run_at TIMESTAMPTZ NOT NULL,
  -- Scheduler claim fields (so concurrent ticks don't double-run).
  claimed_at TIMESTAMPTZ,
  claimed_by TEXT,
  attempts INTEGER NOT NULL DEFAULT 0,
  completed_at TIMESTAMPTZ,
  last_error TEXT,
  payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comms_jobs_due
  ON public.comms_scheduled_jobs(run_at)
  WHERE completed_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_comms_jobs_tenant_id
  ON public.comms_scheduled_jobs(tenant_id);
CREATE INDEX IF NOT EXISTS idx_comms_jobs_enrollment_id
  ON public.comms_scheduled_jobs(enrollment_id);

-- ---------------------------------------------------------------------------
-- comms_n8n_projects — tenant <-> n8n project mapping
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_n8n_projects (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE UNIQUE,
  mode public.comms_n8n_mode NOT NULL DEFAULT 'webhook',
  -- n8n Enterprise project id (NULL in webhook-mode).
  n8n_project_id TEXT,
  base_url TEXT,
  -- Supabase secret name holding the project-scoped n8n API key (never the key itself).
  api_key_secret_ref TEXT,
  is_connected BOOLEAN NOT NULL DEFAULT false,
  last_synced_at TIMESTAMPTZ,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_comms_n8n_projects_tenant_id
  ON public.comms_n8n_projects(tenant_id);

-- ---------------------------------------------------------------------------
-- comms_n8n_webhooks — registered n8n flows selectable as an n8n node target
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.comms_n8n_webhooks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  -- n8n workflow id (when API available) and the invocable webhook URL.
  n8n_workflow_id TEXT,
  webhook_url TEXT NOT NULL,
  http_method TEXT NOT NULL DEFAULT 'POST',
  -- Secret name for an auth header value, if the webhook is secured.
  auth_secret_ref TEXT,
  -- Cached n8n workflow graph JSON for read-only visualisation in our UI.
  graph_cache JSONB,
  graph_cached_at TIMESTAMPTZ,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, name)
);

CREATE INDEX IF NOT EXISTS idx_comms_n8n_webhooks_tenant_id
  ON public.comms_n8n_webhooks(tenant_id);

-- ---------------------------------------------------------------------------
-- updated_at triggers (shared helper)
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  tbl TEXT;
  trg TEXT;
BEGIN
  FOREACH tbl IN ARRAY ARRAY[
    'comms_automations', 'comms_automation_nodes', 'comms_templates',
    'comms_enrollments', 'comms_n8n_projects', 'comms_n8n_webhooks'
  ] LOOP
    trg := 'set_updated_at_' || tbl;
    IF NOT EXISTS (
      SELECT 1 FROM pg_trigger WHERE tgname = trg
    ) THEN
      EXECUTE format(
        'CREATE TRIGGER %I BEFORE UPDATE ON public.%I
           FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column()',
        trg, tbl
      );
    END IF;
  END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- RLS — enable + tenant-admin manage policies
-- ---------------------------------------------------------------------------
DO $$
DECLARE
  tbl TEXT;
  pol TEXT;
BEGIN
  FOREACH tbl IN ARRAY ARRAY[
    'comms_automations', 'comms_automation_nodes', 'comms_automation_edges',
    'comms_templates', 'comms_enrollments', 'comms_run_steps',
    'comms_scheduled_jobs', 'comms_n8n_projects', 'comms_n8n_webhooks'
  ] LOOP
    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', tbl);
    pol := 'Tenant admins can manage ' || tbl;
    IF NOT EXISTS (
      SELECT 1 FROM pg_policies
      WHERE schemaname = 'public' AND tablename = tbl AND policyname = pol
    ) THEN
      EXECUTE format(
        'CREATE POLICY %I ON public.%I FOR ALL
           USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
           WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))',
        pol, tbl
      );
    END IF;
  END LOOP;
END $$;
