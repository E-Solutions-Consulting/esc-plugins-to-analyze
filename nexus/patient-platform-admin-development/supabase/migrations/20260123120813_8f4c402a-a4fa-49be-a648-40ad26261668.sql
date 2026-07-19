-- Add unique constraint for external_approval_links upsert
CREATE UNIQUE INDEX IF NOT EXISTS external_approval_links_unique_entity 
ON public.external_approval_links (tenant_id, entity_type, entity_id, provider_type);