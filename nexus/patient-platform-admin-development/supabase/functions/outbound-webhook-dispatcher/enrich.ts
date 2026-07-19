/**
 * Payload enrichment for outbound webhooks.
 *
 * Producers emit raw ids (`patient_id`, `order_id`, `status_key`, …). Those are
 * useless to a consumer that wants to *act* on the event — to email a patient
 * who just ordered you need their email, not their uuid. Rather than make every
 * producer call site fetch and attach that, the dispatcher enriches once,
 * centrally: it is the single place that sees every event and already holds a
 * service-role client.
 *
 * Resolution (all best-effort — a lookup miss NEVER fails the dispatch, the
 * derived field is simply omitted):
 *   patient_id      -> patient_first_name / _last_name / _full_name / _email / _phone
 *   order_id        -> order_status, product_name, provider_name, pharmacy_name
 *                      (and patient_* when the event carried no patient_id)
 *   status_key      -> status_label
 *   subscription_id -> subscription_status, product_name, current_period_end_at
 *                      (and patient_* when the event carried no patient_id)
 *
 * Derived fields never overwrite a value the producer already supplied.
 * Keep the field names in sync with src/lib/webhook-events.ts (`source: "derived"`).
 */

export type Row = Record<string, unknown>;

/**
 * The narrow slice of Supabase this module needs. Injecting it keeps the logic
 * pure and unit-testable without a database (see enrich.test.ts).
 */
export interface Lookups {
  /** patients row by id (id, first_name, last_name, email, phone). */
  patientById(id: string): Promise<Row | null>;
  /**
   * orders row by id (patient_id, product_id, status, status_key). `status` /
   * `status_key` are FLATTENED from the order_statuses relation by the lookup —
   * orders has no status text column (status_id FK only), and the old select
   * named phantom status/provider_name/pharmacy_name/patient_name columns, so
   * the whole lookup 42703'd and order webhooks silently shipped without
   * order_status/product_name/patient fields resolved via the order.
   */
  orderById(id: string): Promise<Row | null>;
  /** subscriptions row by id (patient_id, status, product_id, current_period_end_at). */
  subscriptionById(id: string): Promise<Row | null>;
  /** products row by id (name). */
  productById(id: string): Promise<Row | null>;
  /** order_statuses row by status_key (admin_status_label). */
  orderStatusByKey(key: string): Promise<Row | null>;
}

const str = (v: unknown): string | undefined => {
  if (typeof v === "string" && v.trim() !== "") return v;
  if (typeof v === "number") return String(v);
  return undefined;
};

/** Assign only when there's a value AND the producer didn't already set it. */
function put(out: Row, key: string, value: unknown): void {
  if (value === undefined || value === null || value === "") return;
  if (out[key] !== undefined && out[key] !== null && out[key] !== "") return;
  out[key] = value;
}

/** Add patient_* derived fields from a patients row. */
function applyPatient(out: Row, patient: Row | null): void {
  if (!patient) return;
  const first = str(patient.first_name);
  const last = str(patient.last_name);
  put(out, "patient_first_name", first);
  put(out, "patient_last_name", last);
  const full = [first, last].filter(Boolean).join(" ").trim();
  put(out, "patient_full_name", full || undefined);
  put(out, "patient_email", str(patient.email));
  put(out, "patient_phone", str(patient.phone));
}

/**
 * Return a NEW payload with derived fields added. Never throws: any lookup that
 * rejects is treated as a miss, because a webhook payload must not be able to
 * break the originating operation.
 */
export async function enrichPayload(
  payload: Row,
  lookups: Lookups,
): Promise<Row> {
  const out: Row = { ...payload };

  const safe = async <T>(p: Promise<T | null>): Promise<T | null> => {
    try {
      return await p;
    } catch {
      return null;
    }
  };

  // Resolve the order first: it may supply the patient_id for events that only
  // carried an order_id, plus product/provider/pharmacy names.
  const orderId = str(out.order_id);
  let order: Row | null = null;
  if (orderId) {
    order = await safe(lookups.orderById(orderId));
    if (order) {
      put(out, "order_status", str(order.status));
      put(out, "status_key", str(order.status_key));
      const productId = str(order.product_id);
      if (productId) {
        const product = await safe(lookups.productById(productId));
        put(out, "product_name", str(product?.name));
      }
    }
  }

  // Subscription: status, product, next renewal — and possibly the patient.
  const subscriptionId = str(out.subscription_id);
  let subscription: Row | null = null;
  if (subscriptionId) {
    subscription = await safe(lookups.subscriptionById(subscriptionId));
    if (subscription) {
      put(out, "subscription_status", str(subscription.status));
      put(out, "current_period_end_at", str(subscription.current_period_end_at));
      const productId = str(subscription.product_id);
      if (productId) {
        const product = await safe(lookups.productById(productId));
        put(out, "product_name", str(product?.name));
      }
    }
  }

  // Human-readable order status.
  const statusKey = str(out.status_key);
  if (statusKey) {
    const st = await safe(lookups.orderStatusByKey(statusKey));
    put(out, "status_label", str(st?.admin_status_label));
  }

  // Patient contact details. Prefer the event's own patient_id, else fall back
  // to the one hanging off the resolved order/subscription.
  const patientId = str(out.patient_id) ??
    str(order?.patient_id) ??
    str(subscription?.patient_id);
  if (patientId) {
    put(out, "patient_id", patientId);
    const patient = await safe(lookups.patientById(patientId));
    applyPatient(out, patient);
  }

  return out;
}

/** Build the Lookups backed by a Supabase service-role client. */
// deno-lint-ignore no-explicit-any
export function supabaseLookups(supabase: any, tenantId: string): Lookups {
  const one = async (
    table: string,
    column: string,
    value: string,
    select: string,
  ): Promise<Row | null> => {
    const { data, error } = await supabase
      .from(table)
      .select(select)
      .eq(column, value)
      .eq("tenant_id", tenantId)
      .maybeSingle();
    // A schema mismatch here silently strips fields from EVERY delivery of
    // that shape — make it visible (enrichPayload still degrades gracefully).
    if (error) console.error(`enrich lookup ${table} failed:`, error);
    return (data as Row) ?? null;
  };

  return {
    patientById: (id) => one("patients", "id", id, "id, first_name, last_name, email, phone"),
    // Status text lives in order_statuses (orders.status_id FK) — embed and
    // flatten so enrichPayload can keep reading order.status / order.status_key.
    orderById: async (id) => {
      const row = await one(
        "orders",
        "id",
        id,
        "id, patient_id, product_id, order_statuses!orders_status_id_fkey (status_key, patient_status_label)",
      );
      if (!row) return null;
      const { order_statuses: st, ...rest } = row as Row & {
        order_statuses?: { status_key?: string; patient_status_label?: string } | null;
      };
      return {
        ...rest,
        status: st?.patient_status_label ?? st?.status_key ?? null,
        status_key: st?.status_key ?? null,
      };
    },
    subscriptionById: (id) =>
      one("subscriptions", "id", id, "id, patient_id, status, product_id, current_period_end_at"),
    productById: (id) => one("products", "id", id, "id, name"),
    // order_statuses is a PLATFORM catalog (no tenant_id column) — query directly.
    orderStatusByKey: async (key) => {
      const { data } = await supabase
        .from("order_statuses")
        .select("status_key, admin_status_label")
        .eq("status_key", key)
        .maybeSingle();
      return (data as Row) ?? null;
    },
  };
}
