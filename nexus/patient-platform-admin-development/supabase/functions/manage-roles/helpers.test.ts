import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  actionRequiresTenantId,
  actionGrantsTenantAdmin,
  extractBearerToken,
  hasProfileUpdates,
  isValidManageRolesAction,
} from "./helpers.ts";

Deno.test("extractBearerToken parses bearer token and rejects empty token", () => {
  assertEquals(extractBearerToken("Bearer abc123"), "abc123");
  assertEquals(extractBearerToken("bearer    abc123"), "abc123");
  assertEquals(extractBearerToken("Bearer   "), null);
  assertEquals(extractBearerToken(null), null);
});

Deno.test("isValidManageRolesAction validates action values", () => {
  assertEquals(isValidManageRolesAction("add_superadmin"), true);
  assertEquals(isValidManageRolesAction("add_customer_support"), true);
  assertEquals(isValidManageRolesAction("add_tenant_membership"), true);
  assertEquals(isValidManageRolesAction("invalid"), false);
});

Deno.test("actionRequiresTenantId returns true for tenant scoped actions", () => {
  assertEquals(actionRequiresTenantId("add_tenant"), true);
  assertEquals(actionRequiresTenantId("add_tenant_membership"), true);
  assertEquals(actionRequiresTenantId("remove_tenant"), true);
  assertEquals(actionRequiresTenantId("remove_from_tenant"), true);
  assertEquals(actionRequiresTenantId("activate_user"), false);
});

Deno.test("actionGrantsTenantAdmin preserves customer support access", () => {
  assertEquals(actionGrantsTenantAdmin("add_tenant"), true);
  assertEquals(actionGrantsTenantAdmin("add_tenant", true), false);
  assertEquals(actionGrantsTenantAdmin("add_tenant_membership"), false);
  assertEquals(actionGrantsTenantAdmin("add_customer_support"), false);
});

Deno.test("hasProfileUpdates requires at least one field", () => {
  assertEquals(hasProfileUpdates(undefined, undefined), false);
  assertEquals(hasProfileUpdates("Name", undefined), true);
  assertEquals(hasProfileUpdates(undefined, "https://avatar"), true);
});
