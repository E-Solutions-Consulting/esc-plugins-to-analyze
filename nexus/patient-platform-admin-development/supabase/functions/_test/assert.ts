export function assertEquals<T>(actual: T, expected: T): void {
  if (!deepEqual(actual, expected)) {
    throw new Error(
      `Assertion failed.\nExpected: ${formatValue(expected)}\nActual: ${formatValue(actual)}`
    );
  }
}

export function assertMatch(actual: string, expected: RegExp): void {
  if (!expected.test(actual)) {
    throw new Error(
      `Assertion failed.\nExpected value to match: ${expected}\nActual: ${actual}`
    );
  }
}

function deepEqual(a: unknown, b: unknown): boolean {
  if (Object.is(a, b)) return true;

  if (
    a === null ||
    b === null ||
    typeof a !== "object" ||
    typeof b !== "object"
  ) {
    return false;
  }

  if (Array.isArray(a) || Array.isArray(b)) {
    if (!Array.isArray(a) || !Array.isArray(b)) return false;
    if (a.length !== b.length) return false;
    for (let i = 0; i < a.length; i++) {
      if (!deepEqual(a[i], b[i])) return false;
    }
    return true;
  }

  const aRecord = a as Record<string, unknown>;
  const bRecord = b as Record<string, unknown>;
  const aKeys = Object.keys(aRecord).sort();
  const bKeys = Object.keys(bRecord).sort();

  if (!deepEqual(aKeys, bKeys)) return false;

  for (const key of aKeys) {
    if (!deepEqual(aRecord[key], bRecord[key])) return false;
  }

  return true;
}

function formatValue(value: unknown): string {
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
}
