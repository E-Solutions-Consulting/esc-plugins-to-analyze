/**
 * Single source of truth for the regrouped admin navigation (tenant + platform).
 * Lives in a layout-free module so both AdminLayout and the settings/platform
 * route shells can import it without a circular dependency.
 * See docs/SettingsIARedesign.md.
 */
import {
  Activity,
  BarChart3,
  Building2,
  ClipboardList,
  CreditCard,
  FileText,
  Flag,
  FolderTree,
  Gift,
  HeartPulse,
  KeyRound,
  LayoutDashboard,
  Megaphone,
  Package,
  Pill,
  Plug,
  Repeat,
  Server,
  Settings,
  ShoppingCart,
  Stethoscope,
  Truck,
  Users,
  Workflow,
  Zap,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

// Canonical bases for the regrouped admin IA (the real routes).
export const SETTINGS_V2_BASE = "/tenant-admin/settings";
export const PLATFORM_V2_BASE = "/platform-superadmin";

export interface NavItem {
  title: string;
  slug: string;
  icon: LucideIcon;
}
export interface NavGroup {
  label: string;
  items: NavItem[];
}

/* -------------------- Tenant settings groups -------------------- */
export const settingsV2Groups: NavGroup[] = [
  {
    // Catalog: Medications (add clinical items) and Products (configure items as
    // sellable products) are SIBLINGS — you add medications first, then build
    // products from them.
    label: "Catalog",
    items: [
      { title: "Medications", slug: "medications", icon: Pill },
      { title: "Products", slug: "products", icon: Package },
    ],
  },
  {
    label: "Clinical",
    items: [
      { title: "Providers", slug: "providers", icon: Stethoscope },
      { title: "Questionnaires", slug: "questionnaires", icon: ClipboardList },
    ],
  },
  {
    // Consolidated configuration. Overlapping concerns are tabs WITHIN a page
    // (General, Communications, Order Lifecycle, Developer) to keep the nav short.
    label: "Platform Settings",
    items: [
      { title: "General", slug: "general", icon: Settings }, // Localization · Signup · Branding · Domain
      { title: "Communications", slug: "communications", icon: Megaphone }, // Email · SMS · Push · Webhook · Support
      { title: "Payments", slug: "payments", icon: CreditCard },
      { title: "Referrals", slug: "referrals", icon: Gift },
      { title: "Order Lifecycle", slug: "order-lifecycle", icon: Truck }, // Pharmacy · Shipping
      { title: "Developer", slug: "developer", icon: KeyRound }, // API Keys · Webhooks · Usage Tracking
      { title: "Health Trackers", slug: "health-trackers", icon: HeartPulse },
      { title: "Legal", slug: "legal", icon: FileText },
      { title: "Feature Flags", slug: "feature-flags", icon: Flag },
      { title: "Admins & Roles", slug: "admins", icon: Users },
      { title: "Audit Logs", slug: "audit-logs", icon: ClipboardList },
    ],
  },
];

/* -------------------- Platform admin groups -------------------- */
export const platformV2Groups: NavGroup[] = [
  {
    label: "Workspace",
    items: [
      { title: "Dashboard", slug: "dashboard", icon: LayoutDashboard },
      { title: "Tenants", slug: "tenants", icon: Building2 },
      { title: "Admins & Roles", slug: "admins", icon: Users },
    ],
  },
  {
    label: "Platform Catalog",
    items: [
      { title: "Product Catalog", slug: "product-categories", icon: FolderTree },
      { title: "Medication Capabilities", slug: "medication-capabilities", icon: Zap },
      { title: "Order Statuses", slug: "order-statuses", icon: ShoppingCart },
    ],
  },
  {
    label: "Integrations & Automations",
    items: [
      { title: "Integrations", slug: "integrations", icon: Plug },
      { title: "n8n (Automations)", slug: "n8n", icon: Workflow },
      { title: "Feature Flags", slug: "feature-flags", icon: Flag },
      { title: "RTDH & Platform Settings", slug: "settings", icon: Server },
    ],
  },
  {
    label: "Governance & Access",
    items: [{ title: "Audit Logs", slug: "audit-logs", icon: ClipboardList }],
  },
];

/* -------------------- Tenant top (operational) zone -------------------- */
/** The CRM-style Workspace zone + Catalog zone shown above Settings. */
export const tenantWorkspaceGroup: { label: string; items: { title: string; url: string; icon: LucideIcon }[] } = {
  label: "Workspace",
  items: [
    { title: "Dashboard", url: "/tenant-admin/dashboard", icon: LayoutDashboard },
    { title: "Analytics", url: "/tenant-admin/analytics", icon: BarChart3 },
    { title: "Product Usage", url: "/tenant-admin/product-usage", icon: Activity },
    { title: "Patients", url: "/tenant-admin/patients", icon: Users },
    { title: "Subscriptions", url: "/tenant-admin/subscriptions", icon: Repeat },
    { title: "Orders", url: "/tenant-admin/orders", icon: ShoppingCart },
    { title: "Automations", url: "/tenant-admin/automations", icon: Workflow },
  ],
};
