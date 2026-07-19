-- Communications Automations — n8n project/folder hierarchy + secret-ref storage.
--
-- Model (per user direction): tenant -> one n8n PROJECT; each automation -> a
-- FOLDER inside that project. Degrades to a shared project + tenant/automation
-- tagging when Enterprise Projects / folders aren't licensed.
--
-- Per-tenant n8n API keys live in GCP Secret Manager (shared platform SA, the
-- set-provider-rtdh-secret precedent); we store only the secret REFERENCE.

-- --- comms_n8n_projects: record how the API key secret is stored + provisioning state
ALTER TABLE public.comms_n8n_projects
  ADD COLUMN IF NOT EXISTS api_key_secret_backend TEXT NOT NULL DEFAULT 'gcp_secret_manager',
  ADD COLUMN IF NOT EXISTS gcp_project_id TEXT,
  ADD COLUMN IF NOT EXISTS provisioning_status TEXT NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS provisioning_error TEXT;

COMMENT ON COLUMN public.comms_n8n_projects.api_key_secret_ref IS
  'Secret Manager secret id (e.g. tenant-<uuid>-n8n-api-key) OR a Supabase env var name. Never the key itself.';
COMMENT ON COLUMN public.comms_n8n_projects.api_key_secret_backend IS
  'Where api_key_secret_ref resolves: gcp_secret_manager | supabase_env.';
COMMENT ON COLUMN public.comms_n8n_projects.provisioning_status IS
  'pending | provisioning | provisioned | failed | webhook_fallback.';

-- --- Per-automation n8n folder mapping (automation = folder inside the tenant project)
ALTER TABLE public.comms_automations
  ADD COLUMN IF NOT EXISTS n8n_folder_id TEXT,
  ADD COLUMN IF NOT EXISTS n8n_folder_name TEXT;

COMMENT ON COLUMN public.comms_automations.n8n_folder_id IS
  'n8n folder id for this automation within the tenant project (NULL until provisioned / in fallback).';

-- --- comms_n8n_folders: explicit ledger of created folders (one per automation)
CREATE TABLE IF NOT EXISTS public.comms_n8n_folders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  automation_id UUID REFERENCES public.comms_automations(id) ON DELETE CASCADE,
  n8n_project_id TEXT,
  n8n_folder_id TEXT,
  name TEXT NOT NULL,
  -- 'folder' when Enterprise folders exist; 'tag' in the degraded shared-project mode.
  backend TEXT NOT NULL DEFAULT 'tag',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (automation_id)
);

CREATE INDEX IF NOT EXISTS idx_comms_n8n_folders_tenant_id
  ON public.comms_n8n_folders(tenant_id);

-- updated_at trigger
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'set_updated_at_comms_n8n_folders') THEN
    CREATE TRIGGER set_updated_at_comms_n8n_folders
      BEFORE UPDATE ON public.comms_n8n_folders
      FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

-- RLS: tenant-admin manage (mirrors the other comms_ tables)
ALTER TABLE public.comms_n8n_folders ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_policies
    WHERE schemaname = 'public' AND tablename = 'comms_n8n_folders'
      AND policyname = 'Tenant admins can manage comms_n8n_folders'
  ) THEN
    CREATE POLICY "Tenant admins can manage comms_n8n_folders"
      ON public.comms_n8n_folders FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
      WITH CHECK (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;
