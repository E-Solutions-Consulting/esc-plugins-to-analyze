import { useAuth } from '@/stores/authStore';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { RTDHSettings } from '@/components/features/settings/RTDHSettings';

export function PlatformSettingsContent() {
  const { isPlatformSuperadmin } = useAuth();

  if (!isPlatformSuperadmin) {
    return (
      <>
        <div className="flex items-center justify-center h-full">
          <p className="text-muted-foreground">Access denied. Platform Superadmin role required.</p>
        </div>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Platform Settings"
        description="Platform-level configuration"
      />
      <RTDHSettings />
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function PlatformSettings() {
  return (
    <AdminLayout variant="platform">
      <PlatformSettingsContent />
    </AdminLayout>
  );
}
