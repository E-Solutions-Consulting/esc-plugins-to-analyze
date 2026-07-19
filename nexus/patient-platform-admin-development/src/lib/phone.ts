export function normalizePhoneDigits(value: string): string {
  return value.replace(/\D/g, '');
}
