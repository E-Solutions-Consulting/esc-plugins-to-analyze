export function isValidEmailFormat(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

export function isDuplicateEmailError(message?: string): boolean {
  if (!message) return false;
  const normalized = message.toLowerCase();
  return normalized.includes("duplicate") || normalized.includes("already");
}

export function isAuthUserNotFoundError(err: unknown): boolean {
  if (!err || typeof err !== "object") return false;

  const typed = err as { message?: string; code?: string };
  return typed.message === "User not found" || typed.code === "user_not_found";
}
