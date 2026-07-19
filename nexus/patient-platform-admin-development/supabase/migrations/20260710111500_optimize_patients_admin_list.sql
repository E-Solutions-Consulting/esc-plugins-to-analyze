-- Optimize the tenant admin patient list:
--   WHERE tenant_id = ?
--   ORDER BY created_at DESC
--   LIMIT/OFFSET pagination

CREATE INDEX IF NOT EXISTS idx_patients_tenant_created_at_desc
  ON public.patients (tenant_id, created_at DESC);
