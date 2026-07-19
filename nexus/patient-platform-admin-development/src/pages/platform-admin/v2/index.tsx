/**
 * platform-admin v2 route wiring + overview landing.
 * The grouped nav is the proposed IA; each route renders the REAL platform-admin
 * page component (Content split), so functionality is preserved. Old routes intact.
 */
import { Navigate, Route, Routes } from "react-router-dom";
import { NavGroupCard } from "@/components/common/NavGroupCard";
import { PageHeader } from "@/components/common/PageHeader";
import {
  PlatformV2Layout,
  platformV2Groups,
  PLATFORM_V2_BASE,
} from "./PlatformV2Layout";
// MIGRATED (real, working features) — see docs/SettingsIARedesign.md Part 3
import { PlatformDashboardContent } from "@/pages/platform-admin/Dashboard";
import { TenantsContent } from "@/pages/platform-admin/Tenants";
import { AdminsContent as PlatformAdminsContent } from "@/pages/platform-admin/Admins";
import { ProductCategoriesContent } from "@/pages/platform-admin/ProductCategories";
import { MedicationCapabilitiesContent } from "@/pages/platform-admin/MedicationCapabilities";
import { OrderStatusesContent } from "@/pages/platform-admin/OrderStatuses";
import { PlatformIntegrationsContent } from "@/pages/platform-admin/Integrations";
import { PlatformN8nContent } from "@/pages/platform-admin/N8n";
import { FeatureFlagsContent } from "@/pages/platform-admin/FeatureFlags";
import { PlatformSettingsContent } from "@/pages/platform-admin/Settings";
import { PlatformAuditLogsContent } from "@/pages/platform-admin/AuditLogs";
import { ProfileContent } from "@/pages/Profile";

function OverviewPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Platform Admin"
        description="Choose an area from the sidebar."
      />
      <div className="grid gap-4 sm:grid-cols-2">
        {platformV2Groups.map((group) => (
          <NavGroupCard
            key={group.label}
            group={group}
            basePath={PLATFORM_V2_BASE}
          />
        ))}
      </div>
    </div>
  );
}

export default function PlatformV2Routes() {
  return (
    <PlatformV2Layout>
      <Routes>
        <Route index element={<Navigate to={`${PLATFORM_V2_BASE}/dashboard`} replace />} />
        <Route path="overview" element={<OverviewPage />} />
        <Route path="profile" element={<ProfileContent />} />
        <Route path="dashboard" element={<PlatformDashboardContent />} />
        <Route path="tenants" element={<TenantsContent />} />
        <Route path="admins" element={<PlatformAdminsContent />} />
        <Route path="product-categories" element={<ProductCategoriesContent />} />
        <Route path="medication-capabilities" element={<MedicationCapabilitiesContent />} />
        <Route path="order-statuses" element={<OrderStatusesContent />} />
        <Route path="integrations" element={<PlatformIntegrationsContent />} />
        <Route path="n8n" element={<PlatformN8nContent />} />
        <Route path="feature-flags" element={<FeatureFlagsContent />} />
        <Route path="settings" element={<PlatformSettingsContent />} />
        <Route path="audit-logs" element={<PlatformAuditLogsContent />} />
        <Route path="*" element={<Navigate to={PLATFORM_V2_BASE} replace />} />
      </Routes>
    </PlatformV2Layout>
  );
}
