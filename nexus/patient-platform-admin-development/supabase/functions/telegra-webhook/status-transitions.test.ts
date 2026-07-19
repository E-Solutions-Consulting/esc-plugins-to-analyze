import { assertEquals } from "../_test/assert.ts";
import type { NormalizedTelegraWebhookEvent } from "./helpers.ts";
import {
  shouldAdvanceTelegraOrderStatus,
  type TelegraOrderStatusTransition,
} from "./status-transitions.ts";

function buildStatus(
  status_key: string,
  display_order: number,
): TelegraOrderStatusTransition {
  return {
    status_key,
    display_order,
    is_terminal: false,
  };
}

function buildEvent(
  overrides: Partial<NormalizedTelegraWebhookEvent>,
): NormalizedTelegraWebhookEvent {
  return {
    rawType: null,
    normalizedType: null,
    rawStatus: null,
    normalizedStatus: null,
    rawTargetEntityStatus: null,
    normalizedTargetEntityStatus: null,
    providerOrderId: null,
    trackingNumber: null,
    trackingUrl: null,
    occurredAt: null,
    shippedAt: null,
    deliveredAt: null,
    cancelledAt: null,
    ...overrides,
  };
}

Deno.test("shouldAdvanceTelegraOrderStatus advances questionnaire orders on new_status_set_to_request with requires_provider_review", () => {
  assertEquals(
    shouldAdvanceTelegraOrderStatus(
      buildStatus("medical_questionnaire_pending", 30),
      buildStatus("provider_review_pending", 40),
      buildEvent({
        rawType: "new_status_set_to_request",
        normalizedType: "new_status_set_to_request",
        rawTargetEntityStatus: "requires_provider_review",
        normalizedTargetEntityStatus: "requires_provider_review",
      }),
    ),
    true,
  );

  assertEquals(
    shouldAdvanceTelegraOrderStatus(
      buildStatus("medical_questionnaire_pending", 30),
      buildStatus("provider_review_pending", 40),
      buildEvent({
        rawType: "new_status_set_to_request",
        normalizedType: "new_status_set_to_request",
        rawTargetEntityStatus: "submitted",
        normalizedTargetEntityStatus: "submitted",
      }),
    ),
    false,
  );

  assertEquals(
    shouldAdvanceTelegraOrderStatus(
      buildStatus("medical_questionnaire_pending", 30),
      buildStatus("provider_review_pending", 40),
      buildEvent({
        rawType: "order_submitted",
        normalizedType: "order_submitted",
        rawTargetEntityStatus: "requires_provider_review",
        normalizedTargetEntityStatus: "requires_provider_review",
      }),
    ),
    false,
  );
});

Deno.test("shouldAdvanceTelegraOrderStatus only advances provider review orders on practitioner approval", () => {
  const currentStatus = buildStatus("provider_review_pending", 40);
  const targetStatus = buildStatus("provider_approved", 50);

    assertEquals(
      shouldAdvanceTelegraOrderStatus(
        currentStatus,
        targetStatus,
        buildEvent({
          rawType: "prescription_approved",
          normalizedType: "prescription_approved",
        }),
      ),
      false,
    );

    assertEquals(
      shouldAdvanceTelegraOrderStatus(
        currentStatus,
        targetStatus,
        buildEvent({
          rawType: "provider_approved",
          normalizedType: "provider_approved",
        }),
      ),
      false,
    );

    assertEquals(
      shouldAdvanceTelegraOrderStatus(
        currentStatus,
        targetStatus,
        buildEvent({
          rawType: "order_submitted",
          normalizedType: "order_submitted",
        }),
      ),
      false,
    );
  },
);

Deno.test("shouldAdvanceTelegraOrderStatus advances to pharmacy approval pending on prescription sent to pharmacy", () => {
  assertEquals(
    shouldAdvanceTelegraOrderStatus(
      buildStatus("provider_review_pending", 40),
      buildStatus("order_sent_to_pharmacy", 20),
      buildEvent({
        rawType: "prescription_sent_to_pharmacy",
        normalizedType: "prescription_sent_to_pharmacy",
      }),
    ),
    true,
  );

  assertEquals(
    shouldAdvanceTelegraOrderStatus(
      buildStatus("provider_review_pending", 40),
      buildStatus("order_sent_to_pharmacy", 20),
      buildEvent({
        rawType: "pharmacy_pending",
        normalizedType: "pharmacy_pending",
      }),
    ),
    false,
  );
});

Deno.test(
  "shouldAdvanceTelegraOrderStatus forces payment_pending flow on new_status_set_to_request with requires_order_processing",
  () => {
    assertEquals(
      shouldAdvanceTelegraOrderStatus(
        buildStatus("payment_collected", 35),
        buildStatus("payment_pending", 2),
        buildEvent({
          rawType: "new_status_set_to_request",
          normalizedType: "new_status_set_to_request",
          rawTargetEntityStatus: "requires_order_processing",
          normalizedTargetEntityStatus: "requires_order_processing",
        }),
      ),
      true,
    );

    assertEquals(
      shouldAdvanceTelegraOrderStatus(
        buildStatus("payment_pending", 2),
        buildStatus("payment_pending", 2),
        buildEvent({
          rawType: "new_status_set_to_request",
          normalizedType: "new_status_set_to_request",
          rawTargetEntityStatus: "requires_order_processing",
          normalizedTargetEntityStatus: "requires_order_processing",
        }),
      ),
      true,
    );
  },
);

Deno.test(
  "shouldAdvanceTelegraOrderStatus still allows non-approval terminal exceptions from provider review",
  () => {
    assertEquals(
      shouldAdvanceTelegraOrderStatus(
        buildStatus("provider_review_pending", 40),
        buildStatus("shipping_exception", 999),
        buildEvent({
          rawType: "exception",
          normalizedType: "exception",
        }),
      ),
      true,
    );
  },
);
