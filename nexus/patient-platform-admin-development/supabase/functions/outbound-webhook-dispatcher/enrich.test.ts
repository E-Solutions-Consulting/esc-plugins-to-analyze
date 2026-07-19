import { assertEquals } from "../_test/assert.ts";
import { enrichPayload, type Lookups, type Row } from "./enrich.ts";

/** In-memory Lookups for tests. Missing keys resolve to null. */
function fakeLookups(seed: {
  patients?: Record<string, Row>;
  orders?: Record<string, Row>;
  subscriptions?: Record<string, Row>;
  products?: Record<string, Row>;
  orderStatuses?: Record<string, Row>;
}): Lookups {
  return {
    patientById: (id) => Promise.resolve(seed.patients?.[id] ?? null),
    orderById: (id) => Promise.resolve(seed.orders?.[id] ?? null),
    subscriptionById: (id) => Promise.resolve(seed.subscriptions?.[id] ?? null),
    productById: (id) => Promise.resolve(seed.products?.[id] ?? null),
    orderStatusByKey: (k) => Promise.resolve(seed.orderStatuses?.[k] ?? null),
  };
}

Deno.test("resolves patient contact fields from patient_id", async () => {
  const out = await enrichPayload(
    { patient_id: "p1" },
    fakeLookups({
      patients: {
        p1: { id: "p1", first_name: "Ada", last_name: "Lovelace", email: "ada@x.io", phone: "+15551234567" },
      },
    }),
  );
  assertEquals(out.patient_first_name, "Ada");
  assertEquals(out.patient_last_name, "Lovelace");
  assertEquals(out.patient_full_name, "Ada Lovelace");
  assertEquals(out.patient_email, "ada@x.io");
  assertEquals(out.patient_phone, "+15551234567");
});

Deno.test("resolves order fields and product name, plus patient via order", async () => {
  // Fake rows mirror what supabaseLookups.orderById actually RETURNS: status /
  // status_key flattened from the order_statuses relation. The previous fakes
  // had phantom orders columns (status/provider_name/pharmacy_name/
  // patient_name), so the real lookup 42703'd in production while these tests
  // stayed green — keep fakes in lockstep with the real select.
  const out = await enrichPayload(
    { order_id: "o1", status_key: "provider_approved" },
    fakeLookups({
      orders: {
        o1: { id: "o1", patient_id: "p1", product_id: "prod1", status: "Provider Approved", status_key: "provider_approved" },
      },
      products: { prod1: { id: "prod1", name: "Semaglutide" } },
      patients: { p1: { id: "p1", first_name: "Grace", last_name: "Hopper", email: "grace@x.io" } },
      orderStatuses: { provider_approved: { status_key: "provider_approved", admin_status_label: "Provider approved" } },
    }),
  );
  assertEquals(out.order_status, "Provider Approved");
  assertEquals(out.product_name, "Semaglutide");
  assertEquals(out.status_label, "Provider approved");
  // patient_id fell out of the order → contact resolved.
  assertEquals(out.patient_id, "p1");
  assertEquals(out.patient_email, "grace@x.io");
});

Deno.test("resolves subscription fields and its patient", async () => {
  const out = await enrichPayload(
    { subscription_id: "s1" },
    fakeLookups({
      subscriptions: {
        s1: { id: "s1", patient_id: "p2", status: "active", product_id: "prod9", current_period_end_at: "2026-08-01T00:00:00Z" },
      },
      products: { prod9: { id: "prod9", name: "Tirzepatide" } },
      patients: { p2: { id: "p2", first_name: "Alan", last_name: "Turing", phone: "+1999" } },
    }),
  );
  assertEquals(out.subscription_status, "active");
  assertEquals(out.product_name, "Tirzepatide");
  assertEquals(out.current_period_end_at, "2026-08-01T00:00:00Z");
  assertEquals(out.patient_full_name, "Alan Turing");
  assertEquals(out.patient_phone, "+1999");
});

Deno.test("never overwrites a value the producer already supplied", async () => {
  const out = await enrichPayload(
    { patient_id: "p1", patient_email: "explicit@producer.io" },
    fakeLookups({ patients: { p1: { id: "p1", email: "resolved@db.io" } } }),
  );
  assertEquals(out.patient_email, "explicit@producer.io");
});

Deno.test("lookup miss just omits the derived field (no throw)", async () => {
  const out = await enrichPayload(
    { patient_id: "ghost", order_id: "nope" },
    fakeLookups({}),
  );
  assertEquals(out.patient_id, "ghost");
  assertEquals(out.patient_email, undefined);
  assertEquals(out.order_status, undefined);
});

Deno.test("a rejecting lookup is treated as a miss, not an error", async () => {
  const boom: Lookups = {
    patientById: () => Promise.reject(new Error("db down")),
    orderById: () => Promise.resolve(null),
    subscriptionById: () => Promise.resolve(null),
    productById: () => Promise.resolve(null),
    orderStatusByKey: () => Promise.resolve(null),
  };
  const out = await enrichPayload({ patient_id: "p1" }, boom);
  assertEquals(out.patient_id, "p1");
  assertEquals(out.patient_email, undefined);
});

Deno.test("partial patient name yields a trimmed full name", async () => {
  const out = await enrichPayload(
    { patient_id: "p1" },
    fakeLookups({ patients: { p1: { id: "p1", first_name: "Cher" } } }),
  );
  assertEquals(out.patient_full_name, "Cher");
});

Deno.test("empty-string db values are ignored", async () => {
  const out = await enrichPayload(
    { patient_id: "p1" },
    fakeLookups({ patients: { p1: { id: "p1", first_name: "", email: "" } } }),
  );
  assertEquals(out.patient_first_name, undefined);
  assertEquals(out.patient_email, undefined);
});
