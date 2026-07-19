import { assertEquals, assertMatch } from "../_test/assert.ts";
import {
  checkRateLimit,
  derivePatientMigrationInfo,
  extractEmailDomain,
  getBearerToken,
  getCorsHeaders,
  getDefaultPatientPassword,
  getSupabaseAnonKeyCandidates,
  intercomUserHash,
  isSupabaseAnonKeyHash,
  isValidEmail,
  isValidPassword,
  isValidSignupDomain,
  isValidUsPhoneWithoutCountryCode,
  mapDurablePatientNotification,
  md5Hex,
  normalizeSignupDomain,
  normalizeTenantSlug,
  normalizeUsPhoneDigits,
  sortPatientNotificationsByRecency,
  validateEmailDomainAgainstTenant,
  validateStateAgainstTenant,
} from "./helpers.ts";

Deno.test("getBearerToken extracts token from Authorization header", () => {
  assertEquals(getBearerToken(null), null);
  assertEquals(getBearerToken(""), null);
  assertEquals(getBearerToken("pk_test_123"), "pk_test_123");
  assertEquals(getBearerToken("Bearer pk_test_123"), "pk_test_123");
  assertEquals(getBearerToken("bearer   pk_live_123  "), "pk_live_123");
});

Deno.test("md5Hex returns standard MD5 digests", () => {
  assertEquals(md5Hex(""), "d41d8cd98f00b204e9800998ecf8427e");
  assertEquals(md5Hex("hello"), "5d41402abc4b2a76b9719d911017c592");
  assertEquals(
    md5Hex("supabase-publishable-key"),
    "7f2f0018527ffd47d198fbeb71132cdd",
  );
});

Deno.test("getSupabaseAnonKeyCandidates normalizes configured key values", () => {
  assertEquals(
    getSupabaseAnonKeyCandidates(
      " default-key ",
      null,
      "env-key, rotated-key\nsecond-rotated-key",
      "default-key",
    ),
    ["default-key", "env-key", "rotated-key", "second-rotated-key"],
  );
});

Deno.test("isSupabaseAnonKeyHash accepts any configured anon key hash", () => {
  const firstKey = "first-anon-key";
  const rotatedKey = "rotated-anon-key";

  assertEquals(
    isSupabaseAnonKeyHash(md5Hex(firstKey), [firstKey, rotatedKey]),
    true,
  );
  assertEquals(
    isSupabaseAnonKeyHash(md5Hex(rotatedKey).toUpperCase(), [
      firstKey,
      rotatedKey,
    ]),
    true,
  );
  assertEquals(
    isSupabaseAnonKeyHash(md5Hex("unknown-key"), [firstKey, rotatedKey]),
    false,
  );
});

Deno.test("derivePatientMigrationInfo maps migrated patient metadata", () => {
  assertEquals(
    derivePatientMigrationInfo({
      legacy_brello_uid: "brello-user-1",
      is_migrated: true,
      migration_phase_1: {
        imported_at: "2026-06-01T10:00:00.000Z",
      },
    }),
    {
      isMigrated: true,
      status: "migrated",
      label: "Migrated",
      date: "2026-06-01T10:00:00.000Z",
      dateLabel: "Migration Date",
      sourceSystem: "brello",
      sourceId: "brello-user-1",
      warnings: {
        unresolvedProduct: false,
        billingHandoffPending: false,
      },
    },
  );

  assertEquals(derivePatientMigrationInfo(null), {
    isMigrated: false,
    status: "not_migrated",
    label: "Not migrated",
    date: null,
    dateLabel: null,
    sourceSystem: null,
    sourceId: null,
    warnings: {
      unresolvedProduct: false,
      billingHandoffPending: false,
    },
  });
});

Deno.test("normalizeTenantSlug trims and removes surrounding quotes", () => {
  assertEquals(normalizeTenantSlug(' "allia-demo" '), "allia-demo");
  assertEquals(normalizeTenantSlug(""), null);
});

Deno.test("mapDurablePatientNotification maps chat resources for Patient UI", () => {
  const notification = mapDurablePatientNotification({
    id: "notification-1",
    type: "chat_message",
    title: "New message",
    body: "You have a new message from your care team.",
    created_at: "2026-06-19T10:00:00.000Z",
    updated_at: "2026-06-19T10:01:00.000Z",
    provider_name: "md_integrations",
    provider_patient_id: "mdi-patient-1",
    order_id: null,
    resource: {
      type: "chat",
      provider_name: "md_integrations",
      provider_patient_id: "mdi-patient-1",
      order_id: null,
    },
  });

  assertEquals(notification, {
    id: "notification-1",
    type: "chat_message",
    title: "New message",
    message: "You have a new message from your care team.",
    created_at: "2026-06-19T10:00:00.000Z",
    updated_at: "2026-06-19T10:01:00.000Z",
    resource: {
      type: "chat",
      provider_name: "md_integrations",
      provider_patient_id: "mdi-patient-1",
      order_id: null,
    },
  });
});

Deno.test("sortPatientNotificationsByRecency orders durable and order notifications together", () => {
  const sorted = sortPatientNotificationsByRecency([
    {
      id: "order:1",
      type: "order_action_required",
      title: "Order",
      message: "Order message",
      created_at: "2026-06-19T09:00:00.000Z",
      updated_at: "2026-06-19T09:00:00.000Z",
      resource: {
        type: "order",
        id: "order-1",
        order_number: "ORD-1",
        product_title: "Plan",
        status_changed_at: "2026-06-19T09:00:00.000Z",
      },
    },
    {
      id: "notification-1",
      type: "chat_message",
      title: "Chat",
      message: "Chat message",
      created_at: "2026-06-19T10:00:00.000Z",
      updated_at: "2026-06-19T10:00:00.000Z",
      resource: {
        type: "chat",
        provider_name: "telegramd",
        provider_patient_id: "pat::1",
        order_id: "order-1",
      },
    },
  ]);

  assertEquals(sorted.map((item) => item.id), ["notification-1", "order:1"]);
});

Deno.test("isValidEmail and isValidPassword enforce basic validation", () => {
  assertEquals(isValidEmail("patient@example.com"), true);
  assertEquals(isValidEmail("invalid"), false);
  assertEquals(isValidPassword("weak").valid, false);
  assertEquals(isValidPassword("StrongPass1").valid, true);
});

Deno.test("signup domain helpers normalize and validate domains", () => {
  assertEquals(extractEmailDomain("User@Example.com"), "example.com");
  assertEquals(extractEmailDomain("invalid-email"), null);
  assertEquals(normalizeSignupDomain("@Example.com "), "example.com");
  assertEquals(isValidSignupDomain("example.com"), true);
  assertEquals(isValidSignupDomain("bad_domain"), false);
});

Deno.test("isValidUsPhoneWithoutCountryCode enforces digits-only 10-digit US phone numbers", () => {
  assertEquals(isValidUsPhoneWithoutCountryCode("4155551212").valid, true);
  assertEquals(
    isValidUsPhoneWithoutCountryCode("415 555 1212").valid,
    true,
  );
  assertEquals(
    isValidUsPhoneWithoutCountryCode("(415)5551212").valid,
    true,
  );
  assertEquals(
    isValidUsPhoneWithoutCountryCode("+14155551212").valid,
    false,
  );
  assertEquals(
    isValidUsPhoneWithoutCountryCode("415555121").valid,
    false,
  );
  assertEquals(
    isValidUsPhoneWithoutCountryCode("14155551212").valid,
    false,
  );
});

Deno.test("normalizeUsPhoneDigits strips non-digit characters", () => {
  assertEquals(normalizeUsPhoneDigits("(415) 555-1212"), "4155551212");
  assertEquals(normalizeUsPhoneDigits("+1 415 555 1212"), "14155551212");
});

Deno.test("getDefaultPatientPassword applies environment-scoped email rules", () => {
  const previousAppEnv = Deno.env.get("APP_ENV");
  const previousEnvironment = Deno.env.get("ENVIRONMENT");
  const previousSupabaseEnv = Deno.env.get("SUPABASE_ENV");

  try {
    Deno.env.set("APP_ENV", "development");
    Deno.env.delete("ENVIRONMENT");
    Deno.env.delete("SUPABASE_ENV");
    assertEquals(
      getDefaultPatientPassword("patient@dev.com", "https://prod.supabase.co"),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@staging.com",
        "https://prod.supabase.co",
      ),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword("patient@stg.com", "https://prod.supabase.co"),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@example.com",
        "https://prod.supabase.co",
      ),
      "allia-tester",
    );

    Deno.env.set("APP_ENV", "staging");
    assertEquals(
      getDefaultPatientPassword("patient@dev.com", "https://prod.supabase.co"),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@staging.com",
        "https://prod.supabase.co",
      ),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword("patient@stg.com", "https://prod.supabase.co"),
      "Password123!",
    );

    Deno.env.set("APP_ENV", "production");
    assertEquals(
      getDefaultPatientPassword(
        "patient@example.com",
        "https://rhzrxfckhogjppjsioyn.supabase.co",
      ),
      null,
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@staging.com",
        "https://prod.supabase.co",
      ),
      null,
    );
    assertEquals(
      getDefaultPatientPassword("patient@stg.com", "https://prod.supabase.co"),
      null,
    );
    assertEquals(
      getDefaultPatientPassword("patient@dev.com", "https://prod.supabase.co"),
      null,
    );

    Deno.env.delete("APP_ENV");
    assertEquals(
      getDefaultPatientPassword(
        "patient@example.com",
        "https://rhzrxfckhogjppjsioyn.supabase.co",
      ),
      "allia-tester",
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@dev.com",
        "https://sunzxjnbgtknqeivljtd.supabase.co",
      ),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@staging.com",
        "https://rhzrxfckhogjppjsioyn.supabase.co",
      ),
      "Password123!",
    );
    assertEquals(
      getDefaultPatientPassword(
        "patient@stg.com",
        "https://rhzrxfckhogjppjsioyn.supabase.co",
      ),
      "Password123!",
    );
  } finally {
    if (previousAppEnv === undefined) {
      Deno.env.delete("APP_ENV");
    } else {
      Deno.env.set("APP_ENV", previousAppEnv);
    }

    if (previousEnvironment === undefined) {
      Deno.env.delete("ENVIRONMENT");
    } else {
      Deno.env.set("ENVIRONMENT", previousEnvironment);
    }

    if (previousSupabaseEnv === undefined) {
      Deno.env.delete("SUPABASE_ENV");
    } else {
      Deno.env.set("SUPABASE_ENV", previousSupabaseEnv);
    }
  }
});

Deno.test("getCorsHeaders echoes allowed origin for credentialed requests", () => {
  const headers = getCorsHeaders(
    new Request("https://example.com", {
      headers: { origin: "http://localhost:5173" },
    }),
  );
  assertEquals(headers["Access-Control-Allow-Origin"], "http://localhost:5173");
  assertEquals(headers["Access-Control-Allow-Credentials"], "true");
});

Deno.test("checkRateLimit blocks once capacity is exceeded", () => {
  const store = new Map<string, { count: number; resetAt: number }>();
  const key = "patient-ip";

  for (let i = 0; i < 100; i++) {
    checkRateLimit(key, store, 1000);
  }

  assertEquals(checkRateLimit(key, store, 1000).allowed, false);
});

Deno.test("validateStateAgainstTenant validates state and tenant-allowed list", async () => {
  const supabaseClient = {
    from: () => ({
      select: () => ({
        eq: () => ({
          maybeSingle: async () => ({
            data: { allowed_states: ["CA", "TX"] },
            error: null,
          }),
        }),
      }),
    }),
  };

  assertEquals(
    await validateStateAgainstTenant(supabaseClient, "tenant-1", "ca", "US"),
    { valid: true },
  );
  assertEquals(
    await validateStateAgainstTenant(supabaseClient, "tenant-1", "NY", "US"),
    {
      valid: false,
      message:
        "We are unable to ship to NY. Please select a different shipping address.",
    },
  );
  assertEquals(
    await validateStateAgainstTenant(supabaseClient, "tenant-1", null, "US"),
    { valid: true },
  );
});

Deno.test("validateEmailDomainAgainstTenant allows all domains when restriction is disabled", async () => {
  const supabaseClient = {
    from: () => ({
      select: () => ({
        eq: () => ({
          maybeSingle: async () => ({
            data: {
              signup_domain_restrictions_enabled: false,
              allowed_signup_email_domains: ["example.com"],
            },
            error: null,
          }),
        }),
      }),
    }),
  };

  assertEquals(
    await validateEmailDomainAgainstTenant(
      supabaseClient,
      "tenant-1",
      "user@other.com",
    ),
    { valid: true },
  );
});

Deno.test("validateEmailDomainAgainstTenant denies domains outside the tenant allowlist", async () => {
  const supabaseClient = {
    from: () => ({
      select: () => ({
        eq: () => ({
          maybeSingle: async () => ({
            data: {
              signup_domain_restrictions_enabled: true,
              allowed_signup_email_domains: ["example.com", "@allia.care"],
            },
            error: null,
          }),
        }),
      }),
    }),
  };

  assertEquals(
    await validateEmailDomainAgainstTenant(
      supabaseClient,
      "tenant-1",
      "user@example.com",
    ),
    { valid: true },
  );
  assertEquals(
    await validateEmailDomainAgainstTenant(
      supabaseClient,
      "tenant-1",
      "user@blocked.com",
    ),
    {
      valid: false,
      message:
        "This email domain is not allowed to register on the app. Please use an approved email domain.",
    },
  );
});

Deno.test("intercomUserHash generates a stable HMAC SHA-256 hash", async () => {
  const hash = await intercomUserHash(
    "user-123",
    "intercom-secret",
  );

  assertEquals(
    hash,
    "5c22779dba19ae4ea14f7526369346a559caadbaeeb4ed87782220395151594c",
  );
  assertMatch(hash, /^[a-f0-9]{64}$/);
});
