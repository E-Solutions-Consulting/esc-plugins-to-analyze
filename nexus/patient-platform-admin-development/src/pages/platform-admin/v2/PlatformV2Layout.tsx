/**
 * PlatformV2Layout — thin shell for the (now real) platform-admin routes.
 *
 * After cutover the regrouped platform nav lives in the MAIN AdminLayout sidebar,
 * so this just renders the active page inside AdminLayout(variant=platform). The
 * grouped nav config is the shared source of truth in src/lib/nav-config.
 */
import { ReactNode } from "react";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PLATFORM_V2_BASE, platformV2Groups } from "@/lib/nav-config";

export { PLATFORM_V2_BASE, platformV2Groups };

interface PlatformV2LayoutProps {
  children: ReactNode;
}

export function PlatformV2Layout({ children }: PlatformV2LayoutProps) {
  return <AdminLayout variant="platform">{children}</AdminLayout>;
}
