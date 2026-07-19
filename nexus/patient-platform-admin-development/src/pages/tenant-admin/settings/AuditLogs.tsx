import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { AuditLogsTable } from '@/components/features/AuditLogsTable';
import { useAuth } from '@/stores/authStore';

/** Page body without the AdminLayout wrapper (for reuse in Settings v2). */
export function AuditLogsContent() {
  const { currentTenantId } = useAuth();

  return (
    <>
      <PageHeader
        title="Audit Logs"
        description="View all management activity logs for traceability and compliance"
      />

      <AuditLogsTable
        scope="tenant"
        tenantId={currentTenantId || undefined}
        showHeader={false}
      />
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function AuditLogs() {
  return (
    <AdminLayout variant="tenant">
      <AuditLogsContent />
    </AdminLayout>
  );
}
