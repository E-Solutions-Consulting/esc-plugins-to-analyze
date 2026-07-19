import { buildCorsHeaders } from "../_shared/cors.ts";
import { isNonLiveEnvironment } from "../_shared/environment.ts";
export { isAllowedOrigin } from "../_shared/cors.ts";

export const US_STATE_CODES = [
  "AL",
  "AK",
  "AZ",
  "AR",
  "CA",
  "CO",
  "CT",
  "DE",
  "DC",
  "FL",
  "GA",
  "HI",
  "ID",
  "IL",
  "IN",
  "IA",
  "KS",
  "KY",
  "LA",
  "ME",
  "MD",
  "MA",
  "MI",
  "MN",
  "MS",
  "MO",
  "MT",
  "NE",
  "NV",
  "NH",
  "NJ",
  "NM",
  "NY",
  "NC",
  "ND",
  "OH",
  "OK",
  "OR",
  "PA",
  "PR",
  "RI",
  "SC",
  "SD",
  "TN",
  "TX",
  "UT",
  "VT",
  "VA",
  "VI",
  "WA",
  "WV",
  "WI",
  "WY",
];

export interface RateLimitRecord {
  count: number;
  resetAt: number;
}

export type MigrationEntityType = "patient" | "order" | "subscription";

export type MigrationStatusKey =
  | "not_migrated"
  | "migrated"
  | "stub_created"
  | "backfilled"
  | "product_unresolved"
  | "billing_handoff_pending"
  | "pp_managed_billing";

export interface MigrationInfo {
  isMigrated: boolean;
  status: MigrationStatusKey;
  label: string;
  date: string | null;
  dateLabel: string | null;
  sourceSystem: string | null;
  sourceId: string | null;
  warnings: {
    unresolvedProduct: boolean;
    billingHandoffPending: boolean;
  };
}

export const RATE_LIMIT = 100;
export const RATE_WINDOW = 60000;
export const rateLimitMap = new Map<string, RateLimitRecord>();

export function getCorsHeaders(req: Request): Record<string, string> {
  return buildCorsHeaders(req, {
    methods: "GET, POST, PUT, PATCH, DELETE, OPTIONS",
  });
}

export function getBearerToken(authHeader: string | null): string | null {
  if (!authHeader) return null;

  const trimmedHeader = authHeader.trim();
  const match = /^Bearer\s+(.+)$/i.exec(trimmedHeader);
  const token = match ? match[1]?.trim() : trimmedHeader;

  return token || null;
}

export function getSupabaseAnonKeyCandidates(
  ...values: Array<string | null | undefined>
): string[] {
  const candidates = values.flatMap((value) =>
    (value ?? "")
      .split(/[\s,]+/)
      .map((candidate) => candidate.trim())
      .filter(Boolean)
  );

  return Array.from(new Set(candidates));
}

export function isSupabaseAnonKeyHash(
  providedHash: string,
  candidateKeys: string[],
): boolean {
  const normalizedProvidedHash = providedHash.trim().toLowerCase();
  if (!normalizedProvidedHash || candidateKeys.length === 0) return false;

  return candidateKeys.some((candidateKey) =>
    md5Hex(candidateKey) === normalizedProvidedHash ||
    candidateKey === providedHash.trim()
  );
}

function leftRotate(value: number, shift: number): number {
  return (value << shift) | (value >>> (32 - shift));
}

function wordToLittleEndianHex(value: number): string {
  let hex = "";
  for (let i = 0; i < 4; i += 1) {
    hex += ((value >>> (i * 8)) & 0xff).toString(16).padStart(2, "0");
  }
  return hex;
}

export function md5Hex(value: string): string {
  const input = new TextEncoder().encode(value);
  const bitLength = input.length * 8;
  const paddedLength = (((input.length + 8) >>> 6) + 1) * 64;
  const bytes = new Uint8Array(paddedLength);
  bytes.set(input);
  bytes[input.length] = 0x80;

  for (let i = 0; i < 8; i += 1) {
    bytes[paddedLength - 8 + i] = Math.floor(bitLength / 2 ** (8 * i)) & 0xff;
  }

  let a0 = 0x67452301;
  let b0 = 0xefcdab89;
  let c0 = 0x98badcfe;
  let d0 = 0x10325476;
  const shifts = [
    7,
    12,
    17,
    22,
    7,
    12,
    17,
    22,
    7,
    12,
    17,
    22,
    7,
    12,
    17,
    22,
    5,
    9,
    14,
    20,
    5,
    9,
    14,
    20,
    5,
    9,
    14,
    20,
    5,
    9,
    14,
    20,
    4,
    11,
    16,
    23,
    4,
    11,
    16,
    23,
    4,
    11,
    16,
    23,
    4,
    11,
    16,
    23,
    6,
    10,
    15,
    21,
    6,
    10,
    15,
    21,
    6,
    10,
    15,
    21,
    6,
    10,
    15,
    21,
  ];
  const constants = Array.from(
    { length: 64 },
    (_, i) => Math.floor(Math.abs(Math.sin(i + 1)) * 2 ** 32) >>> 0,
  );

  for (let offset = 0; offset < bytes.length; offset += 64) {
    const words = new Array<number>(16);
    for (let i = 0; i < 16; i += 1) {
      const wordOffset = offset + i * 4;
      words[i] = bytes[wordOffset] |
        (bytes[wordOffset + 1] << 8) |
        (bytes[wordOffset + 2] << 16) |
        (bytes[wordOffset + 3] << 24);
    }

    let a = a0;
    let b = b0;
    let c = c0;
    let d = d0;

    for (let i = 0; i < 64; i += 1) {
      let f: number;
      let g: number;
      if (i < 16) {
        f = (b & c) | (~b & d);
        g = i;
      } else if (i < 32) {
        f = (d & b) | (~d & c);
        g = (5 * i + 1) % 16;
      } else if (i < 48) {
        f = b ^ c ^ d;
        g = (3 * i + 5) % 16;
      } else {
        f = c ^ (b | ~d);
        g = (7 * i) % 16;
      }

      const nextD = d;
      d = c;
      c = b;
      b = (b +
        leftRotate((a + f + constants[i] + words[g]) >>> 0, shifts[i])) >>> 0;
      a = nextD;
    }

    a0 = (a0 + a) >>> 0;
    b0 = (b0 + b) >>> 0;
    c0 = (c0 + c) >>> 0;
    d0 = (d0 + d) >>> 0;
  }

  return [a0, b0, c0, d0].map(wordToLittleEndianHex).join("");
}

export function checkRateLimit(
  clientId: string,
  store: Map<string, RateLimitRecord> = rateLimitMap,
  now: number = Date.now(),
): { allowed: boolean; remaining: number } {
  const record = store.get(clientId);

  if (!record || now > record.resetAt) {
    store.set(clientId, { count: 1, resetAt: now + RATE_WINDOW });
    return { allowed: true, remaining: RATE_LIMIT - 1 };
  }

  if (record.count >= RATE_LIMIT) {
    return { allowed: false, remaining: 0 };
  }

  record.count++;
  return { allowed: true, remaining: RATE_LIMIT - record.count };
}

export function normalizeTenantSlug(value: string | null): string | null {
  if (!value) return null;
  const trimmed = value.trim();
  if (!trimmed) return null;

  const unquoted = trimmed
    .replace(/^"(.*)"$/, "$1")
    .replace(/^'(.*)'$/, "$1")
    .trim();

  return unquoted || null;
}

export function isValidEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

export function extractEmailDomain(email: string): string | null {
  const normalizedEmail = email.trim().toLowerCase();
  const atIndex = normalizedEmail.lastIndexOf("@");

  if (atIndex <= 0 || atIndex === normalizedEmail.length - 1) {
    return null;
  }

  return normalizedEmail.slice(atIndex + 1);
}

export function normalizeSignupDomain(domain: string): string {
  return domain.trim().toLowerCase().replace(/^@+/, "");
}

export function isValidSignupDomain(domain: string): boolean {
  return /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/
    .test(domain);
}

export function normalizeUsPhoneDigits(phone: string): string {
  return phone.replace(/\D/g, "");
}

export function isValidUsPhoneWithoutCountryCode(
  phone: string,
): { valid: boolean; message?: string } {
  const normalizedPhone = normalizeUsPhoneDigits(phone);

  if (normalizedPhone.length !== 10) {
    return {
      valid: false,
      message:
        "Phone number must be a valid 10-digit US phone number without country code",
    };
  }

  return { valid: true };
}

export function isValidPassword(
  password: string,
): { valid: boolean; message?: string } {
  if (password.length < 8) {
    return {
      valid: false,
      message: "Password must be at least 8 characters long",
    };
  }
  if (!/[A-Z]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one uppercase letter",
    };
  }
  if (!/[a-z]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one lowercase letter",
    };
  }
  if (!/[0-9]/.test(password)) {
    return {
      valid: false,
      message: "Password must contain at least one number",
    };
  }
  return { valid: true };
}

export function getDefaultPatientPassword(
  email: string,
  supabaseUrl: string,
): string | null {
  const normalizedEmail = email.toLowerCase().trim();

  if (
    normalizedEmail.endsWith("@example.com") &&
    isNonLiveEnvironment(supabaseUrl)
  ) {
    return "allia-tester";
  }

  if (
    normalizedEmail.endsWith("@dev.com") &&
    isNonLiveEnvironment(supabaseUrl)
  ) {
    return "Password123!";
  }

  if (
    (normalizedEmail.endsWith("@staging.com") ||
      normalizedEmail.endsWith("@stg.com")) &&
    isNonLiveEnvironment(supabaseUrl)
  ) {
    return "Password123!";
  }

  return null;
}

const MIGRATION_STATUS_LABELS: Record<MigrationStatusKey, string> = {
  not_migrated: "Not migrated",
  migrated: "Migrated",
  stub_created: "Stub created",
  backfilled: "Backfilled",
  product_unresolved: "Product unresolved",
  billing_handoff_pending: "Billing handoff pending",
  pp_managed_billing: "PP-managed billing",
};

const SOURCE_ID_KEYS_BY_ENTITY: Record<MigrationEntityType, string[]> = {
  patient: [
    "legacy_brello_uid",
    "woo_customer_id",
    "source_id",
    "sourceId",
    "migration_source_id",
    "external_id",
  ],
  order: [
    "woo_order_id",
    "woo_parent_order_id",
    "source_order_id",
    "source_id",
    "sourceId",
    "migration_source_id",
    "external_id",
  ],
  subscription: [
    "woo_subscription_id",
    "source_subscription_id",
    "source_id",
    "sourceId",
    "migration_source_id",
    "external_id",
  ],
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function firstString(
  record: Record<string, unknown>,
  keys: string[],
): string | null {
  for (const key of keys) {
    const value = record[key];
    if (typeof value === "string" && value.trim()) return value.trim();
    if (typeof value === "number" && Number.isFinite(value)) {
      return String(value);
    }
  }

  return null;
}

function parseMigrationPhase(value: unknown) {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value !== "string" || !value.trim()) return null;

  const phase = Number(value);
  return Number.isFinite(phase) ? phase : null;
}

function getPhaseMetadata(metadata: Record<string, unknown>, phase: number) {
  const phaseMetadata = metadata[`migration_phase_${phase}`] ??
    metadata[`migration_phase${phase}`];
  return isRecord(phaseMetadata) ? phaseMetadata : null;
}

function getMigrationDate(metadata: Record<string, unknown>) {
  for (const phase of [4, 3, 2, 1]) {
    const phaseMetadata = getPhaseMetadata(metadata, phase);
    const phaseImportedAt = phaseMetadata
      ? firstString(phaseMetadata, [
        "imported_at",
        "migrated_at",
        "backfilled_at",
      ])
      : null;

    if (phaseImportedAt) {
      return { date: phaseImportedAt, dateLabel: "Migration Date" };
    }
  }

  const importedAt = firstString(metadata, [
    "imported_at",
    "migration_imported_at",
    "migrated_at",
    "backfilled_at",
  ]);

  if (!importedAt) return { date: null, dateLabel: null };

  return {
    date: importedAt,
    dateLabel: "Migration Date",
  };
}

export function derivePatientMigrationInfo(metadata: unknown): MigrationInfo {
  const safeMetadata = isRecord(metadata) ? metadata : {};
  const migrationPhase = parseMigrationPhase(safeMetadata.migration_phase);
  const sourceId = firstString(
    safeMetadata,
    SOURCE_ID_KEYS_BY_ENTITY.patient,
  );
  const sourceSystem = firstString(safeMetadata, [
    "source_system",
    "migration_source",
    "source",
    "legacy_system",
  ]) ||
    ("woo_customer_id" in safeMetadata
      ? "woocommerce"
      : "legacy_brello_uid" in safeMetadata
      ? "brello"
      : null);
  const migrated = safeMetadata.is_migrated === true ||
    migrationPhase !== null ||
    sourceId !== null ||
    getPhaseMetadata(safeMetadata, 2) !== null ||
    getPhaseMetadata(safeMetadata, 3) !== null ||
    getPhaseMetadata(safeMetadata, 4) !== null;
  const { date, dateLabel } = getMigrationDate(safeMetadata);

  return {
    isMigrated: migrated,
    status: migrated ? "migrated" : "not_migrated",
    label: migrated
      ? MIGRATION_STATUS_LABELS.migrated
      : MIGRATION_STATUS_LABELS.not_migrated,
    date,
    dateLabel,
    sourceSystem,
    sourceId,
    warnings: {
      unresolvedProduct: false,
      billingHandoffPending: false,
    },
  };
}

function bufferToHex(buffer: ArrayBuffer): string {
  return Array.from(new Uint8Array(buffer))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

export async function intercomUserHash(
  userId: string,
  intercomSecret: string,
): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(intercomSecret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );

  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(userId),
  );

  return bufferToHex(signature);
}

// deno-lint-ignore no-explicit-any
export async function validateStateAgainstTenant(
  supabaseClient: any,
  tenantId: string,
  stateCode: string | null | undefined,
  country: string | null | undefined,
): Promise<{ valid: boolean; message?: string }> {
  if (country && country.toUpperCase() !== "US") {
    return { valid: true };
  }

  if (!stateCode) {
    return { valid: true };
  }

  const normalizedState = stateCode.toUpperCase().trim();

  if (!US_STATE_CODES.includes(normalizedState)) {
    return {
      valid: false,
      message: `'${stateCode}' is not a valid US state code`,
    };
  }

  const { data: tenantSettings, error } = await supabaseClient
    .from("tenant_settings")
    .select("allowed_states")
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    console.error(
      "Error fetching tenant settings for state validation:",
      error,
    );
    return { valid: true };
  }

  const allowedStates = (tenantSettings as { allowed_states?: string[] } | null)
    ?.allowed_states;

  if (!allowedStates || allowedStates.length === 0) {
    return { valid: true };
  }

  const normalizedAllowedStates = allowedStates.map((s: string) =>
    s.toUpperCase().trim()
  );
  if (!normalizedAllowedStates.includes(normalizedState)) {
    return {
      valid: false,
      message:
        `We are unable to ship to ${normalizedState}. Please select a different shipping address.`,
    };
  }

  return { valid: true };
}

// deno-lint-ignore no-explicit-any
export async function validateEmailDomainAgainstTenant(
  supabaseClient: any,
  tenantId: string,
  email: string,
): Promise<{ valid: boolean; message?: string }> {
  const emailDomain = extractEmailDomain(email);

  if (!emailDomain) {
    return { valid: false, message: "Invalid email format" };
  }

  const { data: tenantSettings, error } = await supabaseClient
    .from("tenant_settings")
    .select("signup_domain_restrictions_enabled, allowed_signup_email_domains")
    .eq("tenant_id", tenantId)
    .maybeSingle();

  if (error) {
    console.error(
      "Error fetching tenant settings for email domain validation:",
      error,
    );
    return { valid: true };
  }

  const restrictionsEnabled =
    (tenantSettings as { signup_domain_restrictions_enabled?: boolean } | null)
      ?.signup_domain_restrictions_enabled || false;
  const allowedDomains = (
    tenantSettings as { allowed_signup_email_domains?: string[] } | null
  )?.allowed_signup_email_domains || [];

  if (!restrictionsEnabled) {
    return { valid: true };
  }

  const normalizedAllowedDomains = allowedDomains
    .map(normalizeSignupDomain)
    .filter(isValidSignupDomain);

  if (normalizedAllowedDomains.includes(emailDomain)) {
    return { valid: true };
  }

  return {
    valid: false,
    message:
      "This email domain is not allowed to register on the app. Please use an approved email domain.",
  };
}

export type PatientOrderNotificationResponse = {
  id: string;
  type: "order_action_required";
  title: string;
  message: string;
  created_at: string;
  updated_at: string;
  resource: {
    type: "order";
    id: string;
    order_number: string | null;
    product_title: string | null;
    status_changed_at: string | null;
  };
};

export type PatientNotificationRow = {
  id: string;
  type: string | null;
  title: string | null;
  body: string | null;
  created_at: string;
  updated_at: string;
  provider_name: string | null;
  provider_patient_id: string | null;
  order_id: string | null;
  resource: unknown;
};

export type PatientChatNotificationResponse = {
  id: string;
  type: "chat_message";
  title: string;
  message: string;
  created_at: string;
  updated_at: string;
  resource: {
    type: "chat";
    provider_name: string | null;
    provider_patient_id: string | null;
    order_id: string | null;
  };
};

export type PatientNotificationResponse =
  | PatientOrderNotificationResponse
  | PatientChatNotificationResponse;

function readResourceString(
  resource: unknown,
  key: string,
): string | null {
  if (!resource || typeof resource !== "object" || Array.isArray(resource)) {
    return null;
  }
  const value = (resource as Record<string, unknown>)[key];
  return typeof value === "string" && value.trim() ? value : null;
}

export function mapDurablePatientNotification(
  row: PatientNotificationRow,
): PatientChatNotificationResponse {
  return {
    id: row.id,
    type: "chat_message",
    title: row.title || "New message",
    message: row.body || "You have a new message from your care team.",
    created_at: row.created_at,
    updated_at: row.updated_at,
    resource: {
      type: "chat",
      provider_name: row.provider_name ||
        readResourceString(row.resource, "provider_name"),
      provider_patient_id: row.provider_patient_id ||
        readResourceString(row.resource, "provider_patient_id"),
      order_id: row.order_id || readResourceString(row.resource, "order_id"),
    },
  };
}

function notificationTime(value: PatientNotificationResponse): number {
  const updated = Date.parse(value.updated_at || "");
  if (Number.isFinite(updated)) return updated;

  const created = Date.parse(value.created_at || "");
  return Number.isFinite(created) ? created : 0;
}

export function sortPatientNotificationsByRecency(
  notifications: PatientNotificationResponse[],
): PatientNotificationResponse[] {
  return [...notifications].sort(
    (left, right) => notificationTime(right) - notificationTime(left),
  );
}
