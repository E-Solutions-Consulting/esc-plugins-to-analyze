export async function verifyStripeSignature(
  payload: string,
  signature: string,
  secret: string,
  now: number = Date.now(),
): Promise<{ valid: boolean; error?: string }> {
  try {
    const parts = signature.split(",").reduce((acc, part) => {
      const [key, value] = part.split("=");
      acc[key] = value;
      return acc;
    }, {} as Record<string, string>);

    const timestamp = parts.t;
    const v1Signature = parts.v1;

    if (!timestamp || !v1Signature) {
      return { valid: false, error: "Missing signature components" };
    }

    const timestampMs = parseInt(timestamp, 10) * 1000;
    if (Math.abs(now - timestampMs) > 300000) {
      return { valid: false, error: "Timestamp outside tolerance window" };
    }

    const signedPayload = `${timestamp}.${payload}`;
    const expectedSignature = await computeHmacSha256Hex(secret, signedPayload);

    if (expectedSignature !== v1Signature) {
      return { valid: false, error: "Signature mismatch" };
    }

    return { valid: true };
  } catch (error) {
    return { valid: false, error: `Verification failed: ${error}` };
  }
}

async function computeHmacSha256Hex(
  secret: string,
  payload: string,
): Promise<string> {
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );

  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    encoder.encode(payload),
  );
  return bytesToHex(new Uint8Array(signature));
}

function bytesToHex(bytes: Uint8Array): string {
  return Array.from(bytes)
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

export function generateOrderNumber(
  now: number = Date.now(),
  randomFn: () => number = Math.random,
): string {
  const timestamp = now.toString(36).toUpperCase();
  const random = randomFn().toString(36).substring(2, 6).toUpperCase();
  return `ORD-${timestamp}-${random}`;
}
