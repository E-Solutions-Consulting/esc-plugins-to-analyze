import { assertEquals } from "../_test/assert.ts";
import {
  canAssignPlatformSuperadminRole,
  EMAIL_ALREADY_ADMIN_MESSAGE,
  EMAIL_ALREADY_AUTH_ONLY_MESSAGE,
  EMAIL_ALREADY_PATIENT_MESSAGE,
  EMAIL_ALREADY_REGISTERED_MESSAGE,
  getCreateAdminErrorMessage,
  getMissingCreateAdminFields,
  isEmailAlreadyRegisteredError,
  shouldCheckTenantAdminAccess,
} from "./helpers.ts";

Deno.test("getMissingCreateAdminFields returns all required missing fields", () => {
  assertEquals(getMissingCreateAdminFields({}), ["email", "fullName", "password"]);
});

Deno.test("canAssignPlatformSuperadminRole allows only superadmins to assign superadmin", () => {
  assertEquals(canAssignPlatformSuperadminRole(true, false), false);
  assertEquals(canAssignPlatformSuperadminRole(true, true), true);
  assertEquals(canAssignPlatformSuperadminRole(false, false), true);
});

Deno.test("shouldCheckTenantAdminAccess only checks tenant for tenant admin flow", () => {
  assertEquals(shouldCheckTenantAdminAccess(false, "tenant-1"), true);
  assertEquals(shouldCheckTenantAdminAccess(true, "tenant-1"), false);
  assertEquals(shouldCheckTenantAdminAccess(false, undefined), false);
});

Deno.test("isEmailAlreadyRegisteredError recognizes duplicate auth user messages", () => {
  assertEquals(
    isEmailAlreadyRegisteredError(
      "A user with this email address has already been registered",
    ),
    true,
  );
  assertEquals(isEmailAlreadyRegisteredError("User already registered"), true);
  assertEquals(isEmailAlreadyRegisteredError("Failed to assign superadmin role"), false);
});

Deno.test("getCreateAdminErrorMessage returns helpful message for registered email", () => {
  assertEquals(
    getCreateAdminErrorMessage(
      new Error("A user with this email address has already been registered"),
    ),
    EMAIL_ALREADY_REGISTERED_MESSAGE,
  );
});

Deno.test("specific duplicate email messages pass through unchanged", () => {
  assertEquals(
    getCreateAdminErrorMessage(new Error(EMAIL_ALREADY_ADMIN_MESSAGE)),
    EMAIL_ALREADY_ADMIN_MESSAGE,
  );
  assertEquals(
    getCreateAdminErrorMessage(new Error(EMAIL_ALREADY_PATIENT_MESSAGE)),
    EMAIL_ALREADY_PATIENT_MESSAGE,
  );
  assertEquals(
    getCreateAdminErrorMessage(new Error(EMAIL_ALREADY_AUTH_ONLY_MESSAGE)),
    EMAIL_ALREADY_AUTH_ONLY_MESSAGE,
  );
});
