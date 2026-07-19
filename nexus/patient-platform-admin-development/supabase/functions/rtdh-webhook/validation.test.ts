import { assertEquals } from "../_test/assert.ts";
import { validatePayload } from "./validation.ts";
import type { JsonObject } from "./validation.ts";

const validPayload: JsonObject = {
  master_order_id: "1312312321",
  internal_tenant_id: "tenant_123",
  source_systems: [
    "telegra",
    "md_integrations",
    "stripe",
    "lifefile",
    "easypost",
    "patient_platform",
  ],
  global_status: "Rx Shipping Pickup",
  status_provider: "lifefile",
  updated_at: "2026-04-16T21:19:55.170Z",
  ids: {
    telegra_order_id: "order::32e44358-9aee-4bac-a1e6-51fe24ad0a39",
    patient_platform_order_id: "1312312321",
    telegra_transaction_id: "trn::",
    stripe_subscription_id: "sub_1TAV",
    stripe_invoice_id: "in_1TAV",
    stripe_payment_intent_id: "pi_123",
    lifefile_rx_number: "RX12345",
    easypost_tracking_code: "1Z99V3Y20125048068",
    mdi_case_id: "32e44358-9aee-4bac-a1e6-51fe24ad0a39",
  },
  customer: {
    customer_id: "15145",
    patient_id: "pat::696ea24c-3676-4828-9093-86ad5f2931fd",
    provider_name: "telegra|md_integrations",
    provider_patient_id: "696ea24c-3676-4828-9093-86ad5f2931fd",
    email: "robert.stephens@staging.com",
    phone: "5120763865",
    first_name: "Robert",
    last_name: "Stephens",
  },
  provider: {
    provider_integration: "telegra|md_integrations",
    order_number: "250255",
    status: "requires_order_processing",
    created_at: "2026-03-24T12:34:18.528Z",
    updated_at: "2026-03-26T16:25:57.040Z",
    provider_order_id: "32e44358-9aee-4bac-a1e6-51fe24ad0a39",
    provider_event_type: "order.updated",
    provider_target_entity_status: "processing",
    occurred_at: "2026-03-26T15:58:17.874Z",
    cancelled_at: null,
  },
  subscription: {
    subscription_id: "48822",
    status: "cancelled",
    billing_period: "month",
    billing_interval: 3,
    created_at: "2026-03-16T10:17:24.000Z",
    updated_at: "2026-03-24T08:18:55.000Z",
    next_payment_at: null,
    end_date_at: "2026-03-24T08:18:54.000Z",
    cancelled_at: "2026-03-24T08:18:54.000Z",
  },
  payment: {
    provider: "stripe",
    status: "paid",
    amount: 39900,
    currency: "usd",
    customer_id: "cus_U9ukbKiGWBfpFG",
    subscription_id: "sub_1TAV",
    invoice_id: "in_1TAV",
    payment_intent_id: "pi_123",
    checkout_session_id: "cs_test_123",
    charge_id: "ch_123",
    event_id: "evt_123",
    event_type: "invoice.paid",
    object_id: "in_1TAV",
    object_type: "invoice",
    api_version: "2025-03-31.basil",
    livemode: false,
    provider_created_at: "2026-03-26T15:58:18.000Z",
  },
  prescription: {
    prescription_id: "prescr::40480dff-b951-4189-b2f4-e1ffc8c54159",
    status: "approved",
    approved_by: "Emilio Provider",
    approved_at: "2026-03-26T16:25:55.630Z",
  },
  fulfillment: {
    order_id: "102875209",
    status: "processing",
    updated_at: "2026-03-26T18:40:02.728Z",
    lifefile_order_id: "102875209",
    lifefile_fill_id: "fill_98765",
    order_reference_id: "pp_ref_1312312321",
    rx_number: "RX12345",
    rx_status: "approved",
    order_status: "processing",
    patient_email: "robert.stephens@staging.com",
    tracking_number: "1Z80Y13W0195369133",
  },
  shipping: {
    tracking_number: "1Z80Y13W0195369133",
    carrier: "UPS",
    status: "pre_transit",
    status_detail: "label_created",
    public_url: "https://track.easypost.com/test",
    shipment_id: "shp_c38c28d9815540d8894688debd8c1c8d",
    created_at: "2026-04-08T16:44:07Z",
    updated_at: "2026-04-08T21:24:37Z",
    estimated_delivery_at: "2026-04-09T14:30:00Z",
    shipping_email: "robert.stephens@staging.com",
    shipping_address: {
      line1: "123 Main St",
      line2: null,
      city: "Austin",
      state: "TX",
      postal_code: "78701",
      country: "US",
    },
    tracking_url: "https://track.easypost.com/test",
  },
  products: [
    {
      product_id: "10116",
      product_variation_id: "10119",
      name: "Compounded Semaglutide With B6 (Pyridoxine) - 3 Month",
      subscription_duration: "3-month",
      quantity: 1,
      price: 39900,
    },
  ],
  status_rollup: {
    order_stage: "PROCESSING",
    payment_stage: "PAID",
    prescription_stage: "APPROVED",
    fulfillment_stage: "PROCESSING",
    shipping_stage: "LABEL_CREATED",
    is_complete: false,
    is_cancelled: false,
  },
  timeline: [
    {
      event_id: "event::subscription",
      source: "patient_platform",
      event_type: "subscription.updated",
      status: "cancelled",
      at: "2026-03-26T11:12:57.500Z",
    },
    {
      event_id: "event::telegra",
      source: "telegra",
      event_type: "order_created",
      status: "started",
      at: "2026-03-26T15:58:17.874Z",
    },
  ],
};

function clonePayload(): JsonObject {
  return JSON.parse(JSON.stringify(validPayload)) as JsonObject;
}

Deno.test("validatePayload accepts the RTDH event payload without schema_version", () => {
  assertEquals(validatePayload(clonePayload()), []);
});

Deno.test("validatePayload ignores schema_version when supplied", () => {
  const payload = clonePayload();
  payload.schema_version = "v1.1";

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload rejects invalid nested RTDH fields", () => {
  const payload = clonePayload();
  (payload.customer as JsonObject).email = 42;
  (payload.subscription as JsonObject).billing_interval = "3";
  (payload.payment as JsonObject).amount = "39900";
  (payload.products as JsonObject[])[0].quantity = "1";
  (payload.status_rollup as JsonObject).is_complete = "false";
  (payload.timeline as JsonObject[])[0].at = "not-a-date";

  const errors = validatePayload(payload);

  assertEquals(
    errors.includes("customer.email must be a non-empty string or null"),
    true,
  );
  assertEquals(
    errors.includes("subscription.billing_interval must be a number or null"),
    true,
  );
  assertEquals(
    errors.includes("payment.amount must be a number or null"),
    true,
  );
  assertEquals(errors.includes("products[0].quantity must be a number"), true);
  assertEquals(
    errors.includes("status_rollup.is_complete must be a boolean or null"),
    true,
  );
  assertEquals(
    errors.includes("timeline[0].at must be a valid ISO timestamp string"),
    true,
  );
});

Deno.test("validatePayload rejects missing required nested RTDH fields", () => {
  const payload = clonePayload();
  delete (payload.customer as JsonObject).email;
  delete (payload.payment as JsonObject).event_id;
  delete (payload.status_rollup as JsonObject).shipping_stage;

  const errors = validatePayload(payload);

  assertEquals(
    errors.includes("customer.email is required"),
    true,
  );
  assertEquals(
    errors.includes("payment.event_id is required"),
    true,
  );
  assertEquals(
    errors.includes("status_rollup.shipping_stage is required"),
    true,
  );
});

Deno.test("validatePayload skips selected payment validation when qaBypass is true", () => {
  const payload = clonePayload();
  delete (payload.payment as JsonObject).api_version;
  (payload.payment as JsonObject).livemode = "false";
  (payload.payment as JsonObject).provider_created_at = "not-a-date";

  assertEquals(validatePayload(payload, { qaBypass: true }), []);
});

Deno.test("validatePayload keeps selected payment validation when qaBypass is false", () => {
  const payload = clonePayload();
  delete (payload.payment as JsonObject).api_version;
  (payload.payment as JsonObject).livemode = "false";
  (payload.payment as JsonObject).provider_created_at = "not-a-date";

  const errors = validatePayload(payload, { qaBypass: false });

  assertEquals(errors.includes("payment.api_version is required"), true);
  assertEquals(
    errors.includes("payment.livemode must be a boolean or null"),
    true,
  );
  assertEquals(
    errors.includes(
      "payment.provider_created_at must be a valid ISO timestamp string or null",
    ),
    true,
  );
});

Deno.test("validatePayload does not require payment.payment_intent_id", () => {
  const payload = clonePayload();
  delete (payload.payment as JsonObject).payment_intent_id;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload does not require payment.checkout_session_id", () => {
  const payload = clonePayload();
  delete (payload.payment as JsonObject).checkout_session_id;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload validates payment.checkout_session_id when supplied", () => {
  const payload = clonePayload();
  (payload.payment as JsonObject).checkout_session_id = "";

  assertEquals(
    validatePayload(payload).includes(
      "payment.checkout_session_id must be a non-empty string or null",
    ),
    true,
  );
});

Deno.test("validatePayload does not validate customer.customer_id", () => {
  const payload = clonePayload();
  (payload.customer as JsonObject).customer_id = 12345;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload does not require products.product_variation_id", () => {
  const payload = clonePayload();
  delete ((payload.products as JsonObject[])[0]).product_variation_id;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload does not require optional customer provider fields", () => {
  const payload = clonePayload();
  delete (payload.customer as JsonObject).patient_id;
  delete (payload.customer as JsonObject).provider_name;
  delete (payload.customer as JsonObject).provider_patient_id;
  delete (payload.customer as JsonObject).phone;
  delete (payload.customer as JsonObject).first_name;
  delete (payload.customer as JsonObject).last_name;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload does not validate customer.patient_id format", () => {
  const payload = clonePayload();
  (payload.customer as JsonObject).patient_id = 12345;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload does not require optional payment subscription_id, charge_id, invoice_id, and payment_intent_id", () => {
  const payload = clonePayload();
  delete (payload.payment as JsonObject).subscription_id;
  delete (payload.payment as JsonObject).charge_id;
  delete (payload.payment as JsonObject).invoice_id;
  delete (payload.payment as JsonObject).payment_intent_id;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload does not require subscription billing_period, billing_interval, and cancelled_at", () => {
  const payload = clonePayload();
  delete (payload.subscription as JsonObject).billing_period;
  delete (payload.subscription as JsonObject).billing_interval;
  delete (payload.subscription as JsonObject).cancelled_at;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload still validates subscription cancelled_at format when present", () => {
  const payload = clonePayload();
  (payload.subscription as JsonObject).cancelled_at = "not-a-timestamp";

  assertEquals(validatePayload(payload), [
    "subscription.cancelled_at must be a valid ISO timestamp string or null",
  ]);
});

Deno.test("validatePayload ignores global_status value and optional status_provider", () => {
  const payload = clonePayload();
  payload.global_status = { stage: "any" };
  delete payload.status_provider;

  assertEquals(validatePayload(payload), []);
});

Deno.test("validatePayload requires at least one timeline event", () => {
  const missingTimelinePayload = clonePayload();
  delete missingTimelinePayload.timeline;

  const emptyTimelinePayload = clonePayload();
  emptyTimelinePayload.timeline = [];

  assertEquals(
    validatePayload(missingTimelinePayload).includes("timeline is required"),
    true,
  );
  assertEquals(
    validatePayload(emptyTimelinePayload).includes(
      "timeline must contain at least one event",
    ),
    true,
  );
});

Deno.test("validatePayload accepts partial middleware dispatch subdocuments", () => {
  const payload: JsonObject = {
    master_order_id: "order-123",
    internal_tenant_id: "tenant-123",
    source_systems: ["stripe"],
    global_status: "paid",
    status_provider: "stripe",
    updated_at: "2026-04-16T21:19:55.170Z",
    customer: {
      customer_id: null,
      patient_id: null,
      provider_name: null,
      provider_patient_id: null,
      email: "patient@example.com",
      phone: null,
      first_name: null,
      last_name: null,
    },
    payment: {
      provider: "stripe",
      status: "paid",
      amount: null,
      currency: null,
      customer_id: null,
      subscription_id: null,
      invoice_id: null,
      payment_intent_id: null,
      checkout_session_id: null,
      charge_id: null,
      event_id: "evt_123",
      event_type: "invoice.paid",
      object_id: null,
      object_type: "invoice",
      api_version: null,
      livemode: null,
      provider_created_at: null,
    },
    timeline: [
      {
        event_id: "evt_123",
        source: "stripe",
        event_type: "invoice.paid",
        status: "paid",
        at: "2026-04-16T21:19:55.170Z",
      },
    ],
  };

  assertEquals(validatePayload(payload), []);
});
