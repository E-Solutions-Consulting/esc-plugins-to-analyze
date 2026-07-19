import { assertEquals, assertMatch } from "../_test/assert.ts";
import { isAuthUserNotFoundError, isValidPasswordStrength } from "./helpers.ts";

Deno.test("isValidPasswordStrength requires at least 8 chars", () => {
  assertEquals(isValidPasswordStrength("1234567"), false);
  assertEquals(isValidPasswordStrength("12345678"), true);
});

Deno.test("isAuthUserNotFoundError detects user-not-found variants", () => {
  assertEquals(isAuthUserNotFoundError({ message: "User not found" }), true);
  assertEquals(isAuthUserNotFoundError({ code: "user_not_found" }), true);
  assertEquals(isAuthUserNotFoundError({ message: "other" }), false);
});
