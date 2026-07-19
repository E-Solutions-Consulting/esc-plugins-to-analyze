import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { useTenant } from "@/hooks/useTenant";
import { APP_NAME, ROUTES } from "@/lib/constants";
import { canSeeNavItem } from "@/lib/admin-permissions";
import {
  PLATFORM_V2_BASE,
  platformV2Groups,
  SETTINGS_V2_BASE,
  settingsV2Groups,
  tenantWorkspaceGroup,
} from "@/lib/nav-config";
import { useAuth } from "@/stores/authStore";
import {
  Activity,
  BarChart3,
  Building2,
  ChevronDown,
  ClipboardList,
  FileText,
  Flag,
  FolderTree,
  LayoutDashboard,
  Loader2,
  LogOut,
  Moon,
  Palette,
  Plug,
  Repeat,
  Rocket,
  Settings,
  ShoppingCart,
  Sun,
  User,
  Users,
  Zap,
} from "lucide-react";
import { ReactNode, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";

interface AdminLayoutProps {
  children: ReactNode;
  variant: "tenant" | "platform";
}

// Sidebar is data-driven from the regrouped IA (src/lib/nav-config).
// Settings/platform items link into the v2 namespaces, which render the real,
// migrated page components. Catalog stays its own group above Settings.
type SidebarNavGroup = {
  label: string;
  items: { title: string; url: string; icon: typeof LayoutDashboard }[];
};

// Tenant sidebar: Workspace (operational) + the regrouped Settings (which already
// includes the canonical Catalog group → /settings/products, /settings/medications).
// The old standalone Catalog group (/catalog/*) was a duplicate and has been removed;
// those routes now redirect into the new pages.
const tenantGroups: SidebarNavGroup[] = [
  tenantWorkspaceGroup,
  ...settingsV2Groups.map((group) => ({
    label: group.label,
    items: group.items.map((item) => ({
      title: item.title,
      url: `${SETTINGS_V2_BASE}/${item.slug}`,
      icon: item.icon,
    })),
  })),
];

// Platform sidebar: the regrouped platform IA.
const platformGroups: SidebarNavGroup[] = platformV2Groups.map((group) => ({
  label: group.label,
  items: group.items.map((item) => ({
    title: item.title,
    url: `${PLATFORM_V2_BASE}/${item.slug}`,
    icon: item.icon,
  })),
}));

export function AdminLayout({ children, variant }: AdminLayoutProps) {
  const {
    user,
    signOut,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    tenants,
    currentTenantId,
    switchTenant,
    isSessionRefreshing,
  } = useAuth();
  const { tenant, branding } = useTenant();
  const location = useLocation();
  const navigate = useNavigate();
  const [isDark, setIsDark] = useState(false);

  const handleSignOut = async () => {
    await signOut();
    navigate(ROUTES.LOGIN);
  };

  const toggleTheme = () => {
    setIsDark(!isDark);
    document.documentElement.classList.toggle("dark");
  };

  const permissionContext = {
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    currentTenantId,
  };
  const navGroups = (variant === "platform" ? platformGroups : tenantGroups)
    .map((group) => ({
      ...group,
      items: group.items.filter((item) =>
        canSeeNavItem(permissionContext, item.url)
      ),
    }))
    .filter((group) => group.items.length > 0);
  const isActive = (path: string) =>
    location.pathname === path || location.pathname.startsWith(path + "/");

  const userInitials =
    user?.full_name
      ?.split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase() ||
    user?.email?.[0]?.toUpperCase() ||
    "U";

  return (
    <SidebarProvider>
      <div className="min-h-screen flex w-full">
        <Sidebar>
          <SidebarHeader className="border-b border-sidebar-border p-4">
            <div className="flex items-center gap-3">
              {variant === "tenant" && branding?.logo_url ? (
                <img
                  src={branding.logo_url}
                  alt={tenant?.name || "Tenant logo"}
                  className="h-8 w-8 rounded-lg object-cover"
                />
              ) : (
                <div className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center">
                  <span className="text-primary-foreground font-bold text-sm">
                    {variant === "tenant" && tenant?.name
                      ? tenant.name[0].toUpperCase()
                      : "AC"}
                  </span>
                </div>
              )}
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-sm truncate">{APP_NAME}</p>
                <Badge variant="outline" className="text-xs">
                  {variant === "platform"
                    ? "Platform Admin"
                    : isCustomerSupport && !isTenantAdmin
                    ? "Customer Support"
                    : "Tenant Admin"}
                </Badge>
              </div>
            </div>

            {variant === "tenant" && tenants.length > 1 && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="outline"
                    className="w-full mt-3 justify-between"
                  >
                    <Building2 className="h-4 w-4 shrink-0 mr-2" />
                    <span className="truncate flex-1 text-left">
                      {tenants.find((t) => t.tenant_id === currentTenantId)
                        ?.tenant_name ||
                        tenant?.name ||
                        "Select Tenant"}
                    </span>
                    <ChevronDown className="h-4 w-4 shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-56">
                  <DropdownMenuLabel>Switch Tenant</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  {tenants.map((membership) => (
                    <DropdownMenuItem
                      key={membership.tenant_id}
                      onClick={() => switchTenant(membership.tenant_id)}
                      className={
                        currentTenantId === membership.tenant_id
                          ? "bg-accent"
                          : ""
                      }
                    >
                      <Building2 className="h-4 w-4 mr-2" />
                      <span className="truncate">
                        {membership.tenant_name ||
                          membership.tenant_slug ||
                          membership.tenant_id}
                      </span>
                      {membership.is_primary && (
                        <Badge variant="secondary" className="ml-auto text-xs">
                          Primary
                        </Badge>
                      )}
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </SidebarHeader>

          <SidebarContent>
            {navGroups.map((group) => (
              <SidebarGroup key={group.label}>
                <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                <SidebarGroupContent>
                  <SidebarMenu>
                    {group.items.map((item) => (
                      <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                          asChild
                          isActive={isActive(item.url)}
                        >
                          <Link to={item.url}>
                            <item.icon className="h-4 w-4" />
                            <span>{item.title}</span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    ))}
                  </SidebarMenu>
                </SidebarGroupContent>
              </SidebarGroup>
            ))}
          </SidebarContent>

          <SidebarFooter className="border-t border-sidebar-border p-4">
            {isPlatformSuperadmin && variant === "tenant" && (
              <Button
                variant="outline"
                size="sm"
                className="w-full mb-2"
                onClick={() => navigate(ROUTES.PLATFORM_ADMIN.DASHBOARD)}
              >
                <Building2 className="h-4 w-4 mr-2" />
                Platform Admin
              </Button>
            )}

            {variant === "platform" && (
              <Button
                variant="outline"
                size="sm"
                className="w-full mb-2"
                onClick={() => navigate(ROUTES.TENANT_ADMIN.DASHBOARD)}
              >
                <LayoutDashboard className="h-4 w-4 mr-2" />
                Tenant Admin
              </Button>
            )}

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  className="w-full justify-start gap-2 px-2"
                >
                  <Avatar className="h-8 w-8">
                    <AvatarImage src={user?.avatar_url || undefined} />
                    <AvatarFallback>{userInitials}</AvatarFallback>
                  </Avatar>
                  <div className="flex-1 text-left min-w-0">
                    <p className="text-sm font-medium truncate">
                      {user?.full_name || "User"}
                    </p>
                    <p className="text-xs text-muted-foreground truncate">
                      {user?.email}
                    </p>
                  </div>
                  <ChevronDown className="h-4 w-4 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>My Account</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() =>
                    navigate(
                      variant === "platform"
                        ? ROUTES.PLATFORM_ADMIN.PROFILE
                        : ROUTES.TENANT_ADMIN.PROFILE,
                    )
                  }
                >
                  <User className="h-4 w-4 mr-2" />
                  Profile
                </DropdownMenuItem>
                <DropdownMenuItem onClick={toggleTheme}>
                  {isDark ? (
                    <Sun className="h-4 w-4 mr-2" />
                  ) : (
                    <Moon className="h-4 w-4 mr-2" />
                  )}
                  {isDark ? "Light Mode" : "Dark Mode"}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={handleSignOut}
                  className="text-destructive"
                >
                  <LogOut className="h-4 w-4 mr-2" />
                  Sign Out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </SidebarFooter>
        </Sidebar>

        <main className="flex-1 flex flex-col min-w-0">
          <header className="h-14 border-b flex items-center px-4 gap-4 bg-background">
            <SidebarTrigger />
            {variant === "tenant" && currentTenantId && (
              <div className="flex items-center gap-2">
                <Building2 className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium">
                  {tenants.find((t) => t.tenant_id === currentTenantId)
                    ?.tenant_name || tenant?.name}
                </span>
              </div>
            )}
            {isSessionRefreshing && (
              <div
                className="hidden sm:flex items-center gap-2 text-xs text-muted-foreground"
                aria-live="polite"
              >
                <Loader2 className="h-3 w-3 animate-spin" />
                <span>Refreshing session...</span>
              </div>
            )}
            <div className="flex-1" />
          </header>
          <div className="flex-1 p-6 overflow-auto">{children}</div>
        </main>
      </div>
    </SidebarProvider>
  );
}
