/** Tenant settings route wiring + overview landing (grouped IA). */
import { Navigate, Route, Routes } from "react-router-dom";
import { NavGroupCard } from "@/components/common/NavGroupCard";
import { PageHeader } from "@/components/common/PageHeader";
import { SettingsV2Layout, settingsV2Groups, SETTINGS_V2_BASE } from "./SettingsV2Layout";
// Real, working feature components reused by the grouped IA.
import { AdminsContent } from "@/pages/tenant-admin/settings/Admins";
import { AuditLogsContent } from "@/pages/tenant-admin/settings/AuditLogs";
import { GeneralContent } from "@/pages/tenant-admin/settings/General";
import { ReferralsContent } from "@/pages/tenant-admin/settings/Referrals";
import { LegalContent } from "./LegalContent";
import { QuestionnairesPage } from "./NewPages";
import { ProvidersReal } from "./ProvidersReal";
import {
  MedicationDetailPage,
  MedicationsPage,
  NewMedicationPage,
  ProductDetailPage,
} from "./CatalogPages";
import { ProductsHome } from "./ProductsHome";
import {
  CommunicationsPage,
  DeveloperPage,
  GeneralPage,
  OrderLifecyclePage,
  PaymentsPage,
} from "./SettingsPages";

function OverviewPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Settings"
        description="Configure your tenant. Choose an area from the sidebar."
      />
      <div className="grid gap-4 sm:grid-cols-2">
        {settingsV2Groups.map((group) => (
          <NavGroupCard
            key={group.label}
            group={group}
            basePath={SETTINGS_V2_BASE}
          />
        ))}
      </div>
    </div>
  );
}

export default function SettingsV2Routes() {
  return (
    <SettingsV2Layout>
      <Routes>
        <Route index element={<OverviewPage />} />

        {/* Catalog */}
        <Route path="products" element={<ProductsHome />} />
        <Route path="products/detail" element={<ProductDetailPage />} />
        <Route path="medications" element={<MedicationsPage />} />
        <Route path="medications/new" element={<NewMedicationPage />} />
        <Route path="medications/detail" element={<MedicationDetailPage />} />

        {/* Clinical */}
        <Route path="providers" element={<ProvidersReal />} />
        <Route path="questionnaires" element={<QuestionnairesPage />} />

        {/* Platform Settings (consolidated, tabbed pages) */}
        <Route path="general" element={<GeneralPage />} />
        <Route path="communications" element={<CommunicationsPage />} />
        <Route path="payments" element={<PaymentsPage />} />
        <Route path="referrals" element={<ReferralsContent />} />
        <Route path="order-lifecycle" element={<OrderLifecyclePage />} />
        <Route path="developer" element={<DeveloperPage />} />
        <Route path="health-trackers" element={<GeneralContent only={["health-trackers"]} />} />
        <Route path="legal" element={<LegalContent />} />
        <Route path="feature-flags" element={<GeneralContent only={["feature-flags"]} />} />
        <Route path="admins" element={<AdminsContent />} />
        <Route path="audit-logs" element={<AuditLogsContent />} />

        {/* Redirects from prior slugs that were folded into a tabbed page */}
        <Route path="branding" element={<Navigate to={`${SETTINGS_V2_BASE}/general`} replace />} />
        <Route path="domain" element={<Navigate to={`${SETTINGS_V2_BASE}/general`} replace />} />
        <Route path="connections" element={<Navigate to={`${SETTINGS_V2_BASE}/payments`} replace />} />
        <Route path="api-keys" element={<Navigate to={`${SETTINGS_V2_BASE}/developer`} replace />} />
        <Route path="usage-tracking" element={<Navigate to={`${SETTINGS_V2_BASE}/developer`} replace />} />

        <Route path="*" element={<Navigate to={SETTINGS_V2_BASE} replace />} />
      </Routes>
    </SettingsV2Layout>
  );
}
