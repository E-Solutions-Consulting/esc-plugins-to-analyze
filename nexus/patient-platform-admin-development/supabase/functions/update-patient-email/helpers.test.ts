import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  isAuthUserNotFoundError,
  isDuplicateEmailError,
  isValidEmailFormat,
} from "./helpers.ts";

Deno.test("isValidEmailFormat validates simple email inputs", () => {
  assertEquals(isValidEmailFormat("patient@example.com"), true);
  assertEquals(isValidEmailFormat("invalid-email"), false);
});

Deno.test("isDuplicateEmailError detects duplicate/already messages", () => {
  assertEquals(isDuplicateEmailError("duplicate key value violates unique"), true);
  assertEquals(isDuplicateEmailError("Email already registered"), true);
  assertEquals(isDuplicateEmailError("permission denied"), false);
});

Deno.test("isAuthUserNotFoundError detects explicit not found codes", () => {
  assertEquals(isAuthUserNotFoundError({ message: "User not found" }), true);
  assertEquals(isAuthUserNotFoundError({ code: "user_not_found" }), true);
  assertEquals(isAuthUserNotFoundError({ code: "other" }), false);
});
