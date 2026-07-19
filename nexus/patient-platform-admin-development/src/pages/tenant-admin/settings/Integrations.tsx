import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { TenantIntegrationSettings } from '@/components/features/TenantIntegrationSettings';

interface TenantIntegrationsProps {
  defaultTab?: string;
}

export default function TenantIntegrations({ defaultTab }: TenantIntegrationsProps) {
  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Integrations"
        description="Configure integrations, payment providers, and health tracking for your organization"
      />
      <TenantIntegrationSettings defaultTab={defaultTab} />
    </AdminLayout>
  );
}
