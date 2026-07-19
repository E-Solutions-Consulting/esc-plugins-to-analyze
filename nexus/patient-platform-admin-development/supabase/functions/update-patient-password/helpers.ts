export function isValidPasswordStrength(password: string): boolean {
  return password.length >= 8;
}

export function isAuthUserNotFoundError(err: unknown): boolean {
  if (!err || typeof err !== "object") return false;

  const typed = err as { message?: string; code?: string };
  return typed.message === "User not found" || typed.code === "user_not_found";
}
