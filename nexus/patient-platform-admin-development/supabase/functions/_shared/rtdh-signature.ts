function bytesToHex(bytes: Uint8Array): string {
  return Array.from(bytes)
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

export async function computeHmacSha256Hex(
  secret: string,
  payload: string,
): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );

  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(payload),
  );

  return bytesToHex(new Uint8Array(signature));
}

export function parseHmacSha256SignatureHeader(
  signatureHeader: string | null,
): string | null {
  const trimmed = signatureHeader?.trim().toLowerCase() || "";
  if (!trimmed) {
    return null;
  }

  if (!trimmed.startsWith("sha256=")) {
    return null;
  }

  return trimmed.slice(7).trim();
}

export function timingSafeEqualHex(
  expectedHex: string,
  actualHex: string | null,
): boolean {
  if (!actualHex || expectedHex.length !== actualHex.length) {
    return false;
  }

  let diff = 0;
  for (let index = 0; index < expectedHex.length; index += 1) {
    diff |= expectedHex.charCodeAt(index) ^ actualHex.charCodeAt(index);
  }

  return diff === 0;
}

export async function verifyHmacSha256Signature(params: {
  secret: string;
  payload: string;
  signatureHeader: string | null;
}): Promise<boolean> {
  const actualSignature = parseHmacSha256SignatureHeader(
    params.signatureHeader,
  );
  if (!actualSignature) {
    return false;
  }

  const expectedSignature = await computeHmacSha256Hex(
    params.secret,
    params.payload,
  );

  return timingSafeEqualHex(expectedSignature, actualSignature);
}

export async function postSignedRtdhJson(params: {
  url: string;
  requestId: string;
  requestSource: string;
  webhookSecret: string;
  payload: unknown;
  timeoutMs?: number;
}): Promise<Response> {
  const {
    url,
    requestId,
    requestSource,
    webhookSecret,
    payload,
    timeoutMs,
  } = params;
  const body = JSON.stringify(payload);
  const signature = await computeHmacSha256Hex(webhookSecret, body);
  const abortController = typeof timeoutMs === "number" && timeoutMs > 0
    ? new AbortController()
    : null;
  const timeoutId = abortController
    ? setTimeout(() => abortController.abort(), timeoutMs)
    : null;

  try {
    return await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-patientplatform-signature": `sha256=${signature}`,
        "x-request-id": requestId,
        "x-request-source": requestSource,
      },
      body,
      signal: abortController?.signal,
    });
  } finally {
    if (timeoutId !== null) {
      clearTimeout(timeoutId);
    }
  }
}
