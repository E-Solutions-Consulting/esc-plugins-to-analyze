export interface CreateAdminRequest {
  email: string;
  fullName: string;
  password: string;
  isPlatformSuperadmin: boolean;
  tenantId?: string;
}

export function getMissingCreateAdminFields(
  payload: Partial<CreateAdminRequest>
): string[] {
  const missing: string[] = [];

  if (!payload.email) missing.push("email");
  if (!payload.fullName) missing.push("fullName");
  if (!payload.password) missing.push("password");

  return missing;
}

export function canAssignPlatformSuperadminRole(
  requested: boolean,
  callerIsPlatformSuperadmin: boolean
): boolean {
  if (!requested) return true;
  return callerIsPlatformSuperadmin;
}

export function shouldCheckTenantAdminAccess(
  isPlatformSuperadminRequest: boolean,
  tenantId?: string
): boolean {
  return !isPlatformSuperadminRequest && !!tenantId;
}

export const EMAIL_ALREADY_REGISTERED_MESSAGE =
  "A user with this email address is already registered. Use a different email address or update the existing user's roles instead.";

export const EMAIL_ALREADY_ADMIN_MESSAGE =
  "This email already belongs to an admin user. Search for the user in Admins & Roles and update their roles instead.";

export const EMAIL_ALREADY_PATIENT_MESSAGE =
  "This email already belongs to a patient account. Patient accounts do not appear in Admins & Roles and cannot be reused for admin access.";

export const EMAIL_ALREADY_AUTH_ONLY_MESSAGE =
  "This email already exists in Supabase Auth but is not linked to an admin profile. Ask an engineer to inspect the auth user before creating admin access.";

export function isEmailAlreadyRegisteredError(message: string): boolean {
  const normalizedMessage = message.toLowerCase();

  return (
    normalizedMessage.includes("already been registered") ||
    normalizedMessage.includes("already registered") ||
    normalizedMessage.includes("user already registered") ||
    normalizedMessage.includes("email address already")
  );
}

export function getCreateAdminErrorMessage(error: unknown): string {
  const message = error instanceof Error ? error.message : "An error occurred";

  if (isEmailAlreadyRegisteredError(message)) {
    return EMAIL_ALREADY_REGISTERED_MESSAGE;
  }

  return message;
}
