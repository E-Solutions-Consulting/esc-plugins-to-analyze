export type AdminPermissionContext = {
  isPlatformSuperadmin: boolean;
  isTenantAdmin: boolean;
  isCustomerSupport: boolean;
  currentTenantId?: string | null;
};

export type AdminAction =
  | "patient:view"
  | "patient:create"
  | "patient:edit"
  | "patient:delete"
  | "subscription:view"
  | "subscription:edit"
  | "subscription:status"
  | "subscription:payment"
  | "subscription:product"
  | "subscription:coupon"
  | "order:view"
  | "order:edit"
  | "order:status"
  | "order:refund"
  | "order:export"
  | "order:tracking_edit"
  | "order:shipping_address_edit"
  | "order:billing_address_edit"
  | "order:internal_notes_edit"
  | "product:view"
  | "product:edit"
  | "medication:view"
  | "medication:edit"
  | "automations:manage"
  | "provider_integrations:manage"
  | "questionnaires:manage"
  | "tenant_settings:manage"
  | "tenant_admins:manage"
  | "audit_logs:view";

export type AdminResource =
  | "patient"
  | "subscription"
  | "order"
  | "product"
  | "medication";

export const CUSTOMER_SUPPORT_ORDER_STATUS_KEYS = new Set([
  "provider_order_creation_pending",
  "order_on_hold",
  "order_cancelled",
]);

const CUSTOMER_SUPPORT_ROUTE_PREFIXES = [
  "/tenant-admin/profile",
  "/tenant-admin/patients",
  "/tenant-admin/subscriptions",
  "/tenant-admin/orders",
  "/tenant-admin/settings/products",
  "/tenant-admin/settings/medications",
  "/tenant-admin/catalog/products",
  "/tenant-admin/catalog/medications",
];

const CUSTOMER_SUPPORT_ACTIONS = new Set<AdminAction>([
  "patient:view",
  "patient:create",
  "patient:edit",
  "subscription:view",
  "subscription:edit",
  "subscription:status",
  "subscription:payment",
  "subscription:product",
  "subscription:coupon",
  "order:view",
  "order:edit",
  "order:status",
  "order:shipping_address_edit",
  "order:billing_address_edit",
  "order:internal_notes_edit",
  "product:view",
  "medication:view",
]);

function hasTenantOperatorAccess(context: AdminPermissionContext) {
  return context.isPlatformSuperadmin || context.isTenantAdmin;
}

function matchesRoutePrefix(pathname: string, prefix: string) {
  return pathname === prefix || pathname.startsWith(`${prefix}/`);
}

export function canAccessTenantRoute(
  context: AdminPermissionContext,
  pathname: string,
) {
  if (hasTenantOperatorAccess(context)) return true;
  if (!context.isCustomerSupport) return false;

  return CUSTOMER_SUPPORT_ROUTE_PREFIXES.some((prefix) =>
    matchesRoutePrefix(pathname, prefix),
  );
}

export function canSeeNavItem(
  context: AdminPermissionContext,
  itemUrl: string,
) {
  if (itemUrl.startsWith("/platform-superadmin")) {
    return context.isPlatformSuperadmin;
  }

  if (itemUrl.startsWith("/tenant-admin")) {
    return canAccessTenantRoute(context, itemUrl);
  }

  return true;
}

export function canPerformAction(
  context: AdminPermissionContext,
  action: AdminAction,
) {
  if (hasTenantOperatorAccess(context)) return true;
  if (!context.isCustomerSupport) return false;
  return CUSTOMER_SUPPORT_ACTIONS.has(action);
}

export function canEditResource(
  context: AdminPermissionContext,
  resource: AdminResource,
) {
  return canPerformAction(context, `${resource}:edit` as AdminAction);
}

export function isViewOnlyResource(
  context: AdminPermissionContext,
  resource: AdminResource,
) {
  return (
    canPerformAction(context, `${resource}:view` as AdminAction) &&
    !canEditResource(context, resource)
  );
}

export function canCustomerSupportSetOrderStatus(statusKey: string | null) {
  return Boolean(
    statusKey && CUSTOMER_SUPPORT_ORDER_STATUS_KEYS.has(statusKey),
  );
}

export function filterOrderStatusesForPermissions<
  T extends { status_key: string | null },
>(context: AdminPermissionContext, statuses: T[]) {
  if (!context.isCustomerSupport || hasTenantOperatorAccess(context)) {
    return statuses;
  }

  return statuses.filter((status) =>
    canCustomerSupportSetOrderStatus(status.status_key),
  );
}

export function getTenantAccessDeniedRedirect(context: AdminPermissionContext) {
  return context.isCustomerSupport ? "/tenant-admin/patients" : "/login";
}
