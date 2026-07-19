import { useTenant } from "@/hooks/useTenant";
import { applyTenantTheme, clearTenantTheme } from "@/lib/tenant-theme";
import { useEffect } from "react";

/**
 * Applies the active tenant's brand colours to Nexus.
 *
 * `useTenant` keys its branding query on the current tenant, so switching tenant
 * re-runs this effect with the new colours; signing out drops the branding and
 * reverts to the stylesheet defaults.
 */
export function TenantThemeProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const { branding, tenantId } = useTenant();

  useEffect(() => {
    // No tenant (logged out, or platform-superadmin with none selected) → defaults.
    if (!tenantId) {
      clearTenantTheme();
      return;
    }
    // Wait for the branding row rather than flashing the default palette first;
    // a tenant with no row resolves to null and correctly clears to defaults.
    if (branding === undefined) return;

    applyTenantTheme(branding);
  }, [branding, tenantId]);

  useEffect(() => clearTenantTheme, []);

  return <>{children}</>;
}
