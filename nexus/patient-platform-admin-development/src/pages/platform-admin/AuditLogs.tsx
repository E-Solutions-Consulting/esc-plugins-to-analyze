import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { AuditLogsTable } from '@/components/features/AuditLogsTable';

export function PlatformAuditLogsContent() {
  return (
    <>
      <PageHeader
        title="Platform Audit Logs"
        description="View all platform administration activity for compliance and oversight"
      />

      <AuditLogsTable
        scope="platform"
        showHeader={false}
      />
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function PlatformAuditLogs() {
  return (
    <AdminLayout variant="platform">
      <PlatformAuditLogsContent />
    </AdminLayout>
  );
}
