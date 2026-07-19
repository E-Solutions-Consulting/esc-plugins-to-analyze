import { AuthInitializer } from "@/components/auth/AuthInitializer";
import { ProtectedRoute } from "@/components/auth/ProtectedRoute";
import { TenantThemeProvider } from "@/components/common/TenantThemeProvider";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { useEnsureTestIds } from "@/lib/accessibility";
import { ROUTES } from "@/lib/constants";
import { SETTINGS_V2_BASE } from "@/lib/nav-config";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";

// Pages
import Index from "./pages/Index";
import NotFound from "./pages/NotFound";
import ForgotPassword from "./pages/auth/ForgotPassword";
import Login from "./pages/auth/Login";
import ResetPassword from "./pages/auth/ResetPassword";
import Signup from "./pages/auth/Signup";
import ProfilePage from "./pages/Profile";

// Tenant Admin Pages
import TenantAnalytics from "./pages/tenant-admin/Analytics";
import ProductUsage from "./pages/tenant-admin/ProductUsage";
import TenantDashboard from "./pages/tenant-admin/Dashboard";
import OrderDetail from "./pages/tenant-admin/OrderDetail";
import Orders from "./pages/tenant-admin/Orders";
import PatientDetail from "./pages/tenant-admin/PatientDetail";
import Patients from "./pages/tenant-admin/Patients";
import SubscriptionDetail from "./pages/tenant-admin/SubscriptionDetail";
import Subscriptions from "./pages/tenant-admin/Subscriptions";
import Automations from "./pages/tenant-admin/automations/Automations";
import AutomationBuilder from "./pages/tenant-admin/automations/AutomationBuilder";
import AutomationTemplates from "./pages/tenant-admin/automations/Templates";
import MedicationDetail from "./pages/tenant-admin/catalog/MedicationDetail";
import ProductDetail from "./pages/tenant-admin/catalog/ProductDetail";

import TenantAdmins from "./pages/tenant-admin/settings/Admins";
import AuditLogs from "./pages/tenant-admin/settings/AuditLogs";
import BrandingSettings from "./pages/tenant-admin/settings/Branding";
import TenantDeployments from "./pages/tenant-admin/settings/Deployments";
import ProductUsageTracking from "./pages/tenant-admin/settings/ProductUsageTracking";
import GeneralSettings from "./pages/tenant-admin/settings/General";
import TenantIntegrations from "./pages/tenant-admin/settings/Integrations";
import TenantPaymentProviders from "./pages/tenant-admin/settings/PaymentProviders";
import TenantPrivacyPolicy from "./pages/tenant-admin/settings/PrivacyPolicy";
import TenantTermsAndConditions from "./pages/tenant-admin/settings/TermsAndConditions";
// MOCKUP: proposed settings IA (see docs/SettingsIARedesign.md)
import SettingsV2Routes from "./pages/tenant-admin/settings-v2";
// MOCKUP: proposed platform-admin IA (see docs/SettingsIARedesign.md, Part 2)
import PlatformV2Routes from "./pages/platform-admin/v2";

// Platform Admin Pages
import PlatformAdmins from "./pages/platform-admin/Admins";
import PlatformAuditLogs from "./pages/platform-admin/AuditLogs";
import PlatformDashboard from "./pages/platform-admin/Dashboard";
import FeatureFlags from "./pages/platform-admin/FeatureFlags";
import PlatformIntegrations from "./pages/platform-admin/Integrations";
import MedicationCapabilities from "./pages/platform-admin/MedicationCapabilities";
import OrderStatuses from "./pages/platform-admin/OrderStatuses";
import ProductCategories from "./pages/platform-admin/ProductCategories";
import PlatformSettings from "./pages/platform-admin/Settings";
import Tenants from "./pages/platform-admin/Tenants";

const queryClient = new QueryClient();

const App = () => {
  useEnsureTestIds();

  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <BrowserRouter
          future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
        >
          <AuthInitializer>
            <TenantThemeProvider>
              <Routes>
                {/* Public routes */}
                <Route path="/" element={<Index />} />
                <Route path={ROUTES.LOGIN} element={<Login />} />
                <Route path={ROUTES.SIGNUP} element={<Signup />} />
                <Route
                  path={ROUTES.FORGOT_PASSWORD}
                  element={<ForgotPassword />}
                />
                <Route path={ROUTES.RESET_PASSWORD} element={<ResetPassword />} />

                {/* Tenant Admin routes */}
                <Route
                  path={ROUTES.TENANT_ADMIN.ROOT}
                  element={
                    <Navigate to={ROUTES.TENANT_ADMIN.DASHBOARD} replace />
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.DASHBOARD}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <TenantDashboard />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.PROFILE}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <ProfilePage variant="tenant" />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.ANALYTICS}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <TenantAnalytics />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.PRODUCT_USAGE}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <ProductUsage />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.PATIENTS}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <Patients />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.PATIENT_DETAIL}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <PatientDetail />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.SUBSCRIPTIONS}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <Subscriptions />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.SUBSCRIPTION_DETAIL}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <SubscriptionDetail />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.ORDERS}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <Orders />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.ORDER_DETAIL}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <OrderDetail />
                    </ProtectedRoute>
                  }
                />

                {/* Communications Automations */}
                <Route
                  path={ROUTES.TENANT_ADMIN.AUTOMATIONS}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <Automations />
                    </ProtectedRoute>
                  }
                />
                {/* Templates must precede the :id route so it isn't captured as an id. */}
                <Route
                  path={ROUTES.TENANT_ADMIN.AUTOMATION_TEMPLATES}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <AutomationTemplates />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.AUTOMATION_DETAIL}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <AutomationBuilder />
                    </ProtectedRoute>
                  }
                />

                {/* Catalog routes.
                    The grouped IA owns the catalog LIST pages under
                    /tenant-admin/settings/(products,medications) (the real list
                    components). The old /catalog list routes redirect there so the
                    sidebar has a single Catalog group. The DETAIL editors stay on
                    the old /catalog detail routes — the real list pages navigate
                    into them. */}
                <Route
                  path={ROUTES.TENANT_ADMIN.CATALOG.ROOT}
                  element={
                    <Navigate to={`${SETTINGS_V2_BASE}/products`} replace />
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.CATALOG.MEDICATIONS}
                  element={
                    <Navigate to={`${SETTINGS_V2_BASE}/medications`} replace />
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.CATALOG.MEDICATION_DETAIL}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <MedicationDetail />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.CATALOG.PRODUCTS}
                  element={
                    <Navigate to={`${SETTINGS_V2_BASE}/products`} replace />
                  }
                />
                <Route
                  path={ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL}
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <ProductDetail />
                    </ProtectedRoute>
                  }
                />

                {/* Tenant Settings routes */}
                {/*
                  Regrouped settings IA now OWNS /tenant-admin/settings/*.
                  Old URLs whose slug changed redirect to their new home; URLs whose
                  slug is unchanged (general, branding, admins, audit-logs,
                  feature-flags) are handled by the grouped shell directly.
                  Legacy backward-compat route for the old /settings-v2 namespace.
                */}
                <Route
                  path="/tenant-admin/settings/integrations"
                  element={<Navigate to="/tenant-admin/settings/connections" replace />}
                />
                <Route
                  path="/tenant-admin/settings/payment-providers"
                  element={<Navigate to="/tenant-admin/settings/connections" replace />}
                />
                <Route
                  path="/tenant-admin/settings/deployments"
                  element={<Navigate to="/tenant-admin/settings/domain" replace />}
                />
                <Route
                  path="/tenant-admin/settings/product-usage-tracking"
                  element={<Navigate to="/tenant-admin/settings/usage-tracking" replace />}
                />
                <Route
                  path="/tenant-admin/settings/terms-and-conditions"
                  element={<Navigate to="/tenant-admin/settings/legal" replace />}
                />
                <Route
                  path="/tenant-admin/settings/privacy-policy"
                  element={<Navigate to="/tenant-admin/settings/legal" replace />}
                />
                <Route
                  path="/tenant-admin/settings-v2/*"
                  element={<Navigate to="/tenant-admin/settings" replace />}
                />
                <Route
                  path="/tenant-admin/settings/*"
                  element={
                    <ProtectedRoute requireTenantAccess>
                      <SettingsV2Routes />
                    </ProtectedRoute>
                  }
                />

                {/*
                  Regrouped platform IA now OWNS /platform-superadmin/*. All old
                  platform slugs (dashboard, tenants, admins, product-categories,
                  medication-capabilities, order-statuses, integrations,
                  feature-flags, settings, audit-logs) are handled by the grouped
                  shell directly. Keep the payment-providers redirect; legacy /v2.
                */}
                <Route
                  path={ROUTES.PLATFORM_ADMIN.PAYMENT_PROVIDERS}
                  element={
                    <ProtectedRoute requirePlatformAdmin>
                      <Navigate
                        to={`/platform-superadmin/integrations?tab=payment-providers`}
                        replace
                      />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="/platform-superadmin/v2/*"
                  element={<Navigate to="/platform-superadmin" replace />}
                />
                <Route
                  path="/platform-superadmin/*"
                  element={
                    <ProtectedRoute requirePlatformAdmin>
                      <PlatformV2Routes />
                    </ProtectedRoute>
                  }
                />

                {/* Catch-all */}
                <Route path="*" element={<NotFound />} />
              </Routes>
            </TenantThemeProvider>
          </AuthInitializer>
        </BrowserRouter>
      </TooltipProvider>
    </QueryClientProvider>
  );
};

export default App;
