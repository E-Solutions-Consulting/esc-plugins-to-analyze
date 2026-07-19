/**
 * SettingsV2Layout — thin shell for the (now real) settings routes.
 *
 * After cutover the regrouped settings nav lives in the MAIN AdminLayout sidebar,
 * so this just renders the active settings page inside AdminLayout. The grouped
 * nav config is the shared source of truth in src/lib/nav-config.
 */
import { ReactNode } from "react";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { SETTINGS_V2_BASE, settingsV2Groups } from "@/lib/nav-config";

export { SETTINGS_V2_BASE, settingsV2Groups };

interface SettingsV2LayoutProps {
  children: ReactNode;
}

export function SettingsV2Layout({ children }: SettingsV2LayoutProps) {
  return <AdminLayout variant="tenant">{children}</AdminLayout>;
}
