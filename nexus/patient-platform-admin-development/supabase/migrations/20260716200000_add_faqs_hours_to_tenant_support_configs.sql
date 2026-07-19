-- Support FAQs + support hours, editable per tenant in Nexus and served to
-- patient apps via the tenant-info edge function.
-- RLS already covers this table (tenant admins manage own row, superadmin read)
-- via 20260401120000_create_tenant_support_configs.sql — no new policies needed.
ALTER TABLE public.tenant_support_configs
  ADD COLUMN IF NOT EXISTS faqs JSONB NOT NULL DEFAULT '[]'::jsonb,
  ADD COLUMN IF NOT EXISTS support_hours TEXT;
