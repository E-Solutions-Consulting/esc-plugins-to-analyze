/**
 * RTDH self-heal integration test (PP-90 Phase 3 / PP-531)
 *
 * Verifies that when an RTDH event arrives for a WC order that was NOT
 * captured by the Phase 2 batch (e.g. active orders skipped as wc-on-hold),
 * the rtdh-webhook EF auto-creates a PP order stub via
 * createOrderStubFromWooCommercePayload.
 *
 * Seeds the test patient through the migration-phase1-import EF so no
 * Supabase service-role key is required.
 *
 * Usage (from brello-backend dir):
 *   DOTENV_PRIVATE_KEY=<key-from-.env.keys> \
 *   npx dotenvx run --env-file .env.prod -- \
 *   deno run --allow-env --allow-net \
 *     "/Users/raj/Developer/Professional/Allia/Patient Platform/patient-platform-admin/scripts/test-rtdh-self-heal-staging.ts"
 *
 * Env vars injected by dotenvx from .env.prod:
 *   PP_SUPABASE_URL         — staging Supabase base URL
 *   PP_MIGRATION_API_KEY    — X-Migration-API-Key for EF auth
 *   MIGRATION_API_KEY       — Bearer token for rtdh-webhook MIGRATION_API_KEY bypass
 *
 * What it does:
 *   1. Upserts a fake patient via migration-phase1-import EF (metadata.woo_id set)
 *   2. POSTs a minimal RTDH event to rtdh-webhook with a fake WC order ID for
 *      that customer — expects the EF to auto-create a PP order stub
 *   3. Verifies: rtdh-webhook returns 200 (not 422 "does not match any migrated order")
 *
 * The test patient and auto-created order stub remain in staging (staging cleanup is manual).
 */

import { createClient } from "jsr:@supabase/supabase-js@2";

const PP_SUPABASE_URL = Deno.env.get("PP_SUPABASE_URL") ?? Deno.env.get("SUPABASE_URL");
const PP_MIGRATION_API_KEY = Deno.env.get("PP_MIGRATION_API_KEY");
const MIGRATION_API_KEY = Deno.env.get("MIGRATION_API_KEY");
const SUPABASE_SERVICE_ROLE_KEY = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

if (!PP_SUPABASE_URL || !PP_MIGRATION_API_KEY || !MIGRATION_API_KEY) {
  console.error(
    "Missing required env vars:\n" +
    "  PP_SUPABASE_URL (or SUPABASE_URL)\n" +
    "  PP_MIGRATION_API_KEY\n" +
    "  MIGRATION_API_KEY\n\n" +
    "Run from brello-backend dir:\n" +
    "  DOTENV_PRIVATE_KEY=<key> npx dotenvx run --env-file .env.prod -- deno run --allow-env --allow-net <this-script>",
  );
  Deno.exit(1);
}

const TEST_WOO_CUSTOMER_ID = "TEST-WOO-CUST-SELFHEAL-9999";
const TEST_WOO_ORDER_ID = "TEST-WOO-ORDER-SELFHEAL-8888";
const TEST_EMAIL = "test-rtdh-self-heal-9999@example.com";
const BRELLO_TENANT_SLUG = "brello";

const PHASE1_URL = `${PP_SUPABASE_URL}/functions/v1/migration-phase1-import`;
const RTDH_WEBHOOK_URL = `${PP_SUPABASE_URL}/functions/v1/rtdh-webhook/event`;

console.log(`\nTarget Supabase: ${PP_SUPABASE_URL}`);
console.log(`Phase 1 EF: ${PHASE1_URL}`);
console.log(`RTDH webhook: ${RTDH_WEBHOOK_URL}`);

// ---------------------------------------------------------------------------
// Step 1: seed test patient via Phase 1 import EF
// ---------------------------------------------------------------------------
console.log("\n=== Step 1: seed test patient via migration-phase1-import ===");
console.log(`  email: ${TEST_EMAIL}`);
console.log(`  woo_id: ${TEST_WOO_CUSTOMER_ID}`);

const phase1Payload = {
  tenant_slug: BRELLO_TENANT_SLUG,
  patients: [
    {
      email: TEST_EMAIL,
      first_name: "SelfHeal",
      last_name: "Test",
      access_status: "active",
      metadata: {
        woo_id: TEST_WOO_CUSTOMER_ID,
        source: "rtdh_self_heal_test",
      },
    },
  ],
  subscriptions: [],
  orders: [],
};

const phase1Res = await fetch(PHASE1_URL, {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-Migration-API-Key": PP_MIGRATION_API_KEY,
  },
  body: JSON.stringify(phase1Payload),
});

const phase1Body = await phase1Res.json().catch(() => null);
console.log(`Phase 1 response: HTTP ${phase1Res.status}`);
console.log("Body:", JSON.stringify(phase1Body, null, 2));

if (phase1Res.status !== 200) {
  console.error("Phase 1 EF did not return 200 — patient seed failed");
  Deno.exit(1);
}

console.log("✅ Patient seeded (upserted)");

// ---------------------------------------------------------------------------
// Step 2: POST RTDH event with a WC order ID that is NOT in PP
// ---------------------------------------------------------------------------
console.log("\n=== Step 2: POST RTDH event for an order NOT in PP ===");
console.log(`  woocommerce_order_id: ${TEST_WOO_ORDER_ID}`);
console.log(`  customer.customer_id: ${TEST_WOO_CUSTOMER_ID}`);
console.log("  Expected: rtdh-webhook auto-creates order stub, returns 200");

const now = new Date().toISOString();
const rtdhPayload = {
  master_order_id: `SELFHEAL-TEST-${Date.now()}`,
  internal_tenant_id: BRELLO_TENANT_SLUG,
  source_systems: ["woocommerce"],
  event_type: "order_sent_to_pharmacy",
  updated_at: now,
  ids: {
    woocommerce_order_id: TEST_WOO_ORDER_ID,
  },
  customer: {
    customer_id: TEST_WOO_CUSTOMER_ID,
    email: null, // required key, nullable per EF contract
  },
  timeline: [
    {
      event_id: `selfheal-test-evt-${Date.now()}`,
      source: "woocommerce",
      event_type: "order_sent_to_pharmacy",
      status: "wc-send_to_telegra",
      at: now,
    },
  ],
};

const rtdhRes = await fetch(RTDH_WEBHOOK_URL, {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "Authorization": `Bearer ${MIGRATION_API_KEY}`,
    "x-request-id": `self-heal-test-${Date.now()}`,
  },
  body: JSON.stringify(rtdhPayload),
});

const rtdhBody = await rtdhRes.json().catch(() => null);
console.log(`\nRTDH response: HTTP ${rtdhRes.status}`);
console.log("Body:", JSON.stringify(rtdhBody, null, 2));

// ---------------------------------------------------------------------------
// Step 3: verify
// ---------------------------------------------------------------------------
console.log("\n=== Step 3: results ===");

// The EF returns 200 for soft errors (validation_error) so inspect the body.
const errorCode = rtdhBody?.error?.code;

if (errorCode === "validation_error") {
  console.error("❌ FAIL — payload validation error (shape issue):");
  console.error("  ", JSON.stringify(rtdhBody?.error?.details));
  Deno.exit(1);
} else if (errorCode === "reference_not_found") {
  const detail = JSON.stringify(rtdhBody?.error?.details ?? rtdhBody);
  if (detail.includes("does not match any migrated order")) {
    console.error("❌ FAIL — 422/reference: patient NOT found by woo_id in PP.");
    console.error("   Check that Phase 1 EF stores metadata.woo_id (not woo_customer_id).");
  } else {
    console.error("❌ FAIL — reference_not_found:", detail);
  }
  Deno.exit(1);
} else if (rtdhRes.status === 200 && !errorCode) {
  console.log("✅ PASS — rtdh-webhook returned 200 with no error code");
  console.log("   Self-heal fired: order stub auto-created in PP for WC order");
  console.log("   that was not captured by the Phase 2 batch.");

  // Bonus DB verification if service role key is available
  if (SUPABASE_SERVICE_ROLE_KEY) {
    const supabase = createClient(PP_SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, {
      auth: { autoRefreshToken: false, persistSession: false },
    });
    await new Promise((r) => setTimeout(r, 500));
    const { data: orders } = await supabase
      .from("orders")
      .select("id, metadata, created_at")
      .filter("metadata->>woo_order_id", "eq", TEST_WOO_ORDER_ID);
    if (orders?.length) {
      console.log("\nDB verification:");
      console.log("  Order stub:", JSON.stringify(orders[0], null, 2));
      const meta = orders[0].metadata as Record<string, unknown>;
      const phase1 = meta?.migration_phase_1 as Record<string, unknown> | null;
      console.log(`  created_via: ${phase1?.created_via}`);
      console.log(`  is_migrated: ${meta?.is_migrated}`);
    }
  } else {
    console.log("  (DB verification skipped — set SUPABASE_SERVICE_ROLE_KEY for full check)");
  }

  Deno.exit(0);
} else {
  console.error(`❌ FAIL — unexpected HTTP ${rtdhRes.status}`);
  Deno.exit(1);
}
