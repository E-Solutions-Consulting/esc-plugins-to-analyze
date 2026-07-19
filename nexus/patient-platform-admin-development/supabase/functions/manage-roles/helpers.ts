export const MANAGE_ROLE_ACTIONS = [
  "add_superadmin",
  "remove_superadmin",
  "add_customer_support",
  "remove_customer_support",
  "add_tenant",
  "add_tenant_membership",
  "remove_tenant",
  "deactivate_user",
  "activate_user",
  "remove_from_tenant",
  "update_password",
  "update_profile",
] as const;

export type ManageRolesAction = (typeof MANAGE_ROLE_ACTIONS)[number];

export function extractBearerToken(authHeader: string | null): string | null {
  if (!authHeader) return null;
  const accessToken = authHeader.replace(/^Bearer\s+/i, "").trim();
  return accessToken || null;
}

export function isValidManageRolesAction(action: string): action is ManageRolesAction {
  return MANAGE_ROLE_ACTIONS.includes(action as ManageRolesAction);
}

export function actionRequiresTenantId(action: ManageRolesAction): boolean {
  return action === "add_tenant" ||
    action === "add_tenant_membership" ||
    action === "remove_tenant" ||
    action === "remove_from_tenant";
}

export function actionGrantsTenantAdmin(
  action: ManageRolesAction,
  targetIsCustomerSupport = false,
): boolean {
  return action === "add_tenant" && !targetIsCustomerSupport;
}

export function hasProfileUpdates(fullName?: string, avatarUrl?: string): boolean {
  return fullName !== undefined || avatarUrl !== undefined;
}
