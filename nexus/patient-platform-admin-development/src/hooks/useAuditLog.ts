import { useCallback } from 'react';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import type { Json } from '@/integrations/supabase/types';

const IGNORED_DIFF_KEYS = new Set(['created_at', 'updated_at']);

interface AuditLogParams {
  action: string;
  entityType: string;
  entityId?: string;
  beforeData?: Record<string, unknown> | null;
  afterData?: Record<string, unknown> | null;
  /** Pass null explicitly for platform-level logs, or a tenant ID for tenant-scoped logs */
  tenantId?: string | null;
}

export function useAuditLog() {
  const { user, currentTenantId } = useAuth();

  const logAction = useCallback(
    async ({
      action,
      entityType,
      entityId,
      beforeData,
      afterData,
      tenantId,
    }: AuditLogParams) => {
      try {
        // Calculate diff
        let diff: Json | null = null;
        if (beforeData && afterData) {
          const diffObj: Record<string, unknown> = {};
          const allKeys = new Set([
            ...Object.keys(beforeData),
            ...Object.keys(afterData),
          ]);
          for (const key of allKeys) {
            if (IGNORED_DIFF_KEYS.has(key)) continue;

            if (JSON.stringify(beforeData[key]) !== JSON.stringify(afterData[key])) {
              diffObj[key] = {
                before: beforeData[key],
                after: afterData[key],
              };
            }
          }
          diff = Object.keys(diffObj).length > 0 ? (diffObj as Json) : null;
        }

        // Determine tenant_id: explicit null means platform-level, undefined means use current tenant
        const effectiveTenantId = tenantId === null ? null : (tenantId || currentTenantId || null);

        const { error } = await supabase.from('audit_logs').insert([{
          tenant_id: effectiveTenantId,
          actor_id: user?.id || undefined,
          actor_email: user?.email || undefined,
          action,
          entity_type: entityType,
          entity_id: entityId || undefined,
          before_data: beforeData ? (beforeData as Json) : undefined,
          after_data: afterData ? (afterData as Json) : undefined,
          diff: diff || undefined,
          request_id: crypto.randomUUID(),
        }]);

        if (error) {
          console.error('Failed to create audit log:', error);
        }
      } catch (error) {
        console.error('Failed to create audit log:', error);
      }
    },
    [user, currentTenantId]
  );

  return { logAction };
}
