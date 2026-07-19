import { assertEquals } from "../_test/assert.ts";
import {
  getTelegraWebhookOrderIdDiagnostics,
  getTelegraWebhookSecrets,
  mapTelegraEventToOrderStatus,
  normalizeTelegraWebhookEvent,
  timingSafeEqualString,
  verifyTelegraWebhookSignature,
} from "./helpers.ts";

Deno.test(
  "normalizeTelegraWebhookEvent only reads strict eventType and status properties",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "shipment.delivered",
      status: "delivered",
      data: {
        order: {
          id: "telegra-order-1",
          status: "delivered",
        },
        shipment: {
          trackingNumber: "1Z999",
          trackingUrl: "https://carrier.example/1Z999",
          deliveredAt: "2026-03-09T14:00:00Z",
        },
      },
    });

    assertEquals(normalized, {
      rawType: "shipment.delivered",
      normalizedType: "shipment_delivered",
      rawStatus: "delivered",
      normalizedStatus: "delivered",
      rawTargetEntityStatus: null,
      normalizedTargetEntityStatus: null,
      providerOrderId: null,
      trackingNumber: "1Z999",
      trackingUrl: "https://carrier.example/1Z999",
      occurredAt: null,
      shippedAt: null,
      deliveredAt: "2026-03-09T14:00:00.000Z",
      cancelledAt: null,
    });
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads targetEntity.id and top-level status",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      targetEntity: {
        id: "order::86709a29-xxxx-xxxx-xxxx-f9b2e9085981",
      },
      eventType: "new_status_set_to_request",
      status: "requires_provider_review",
      createdAt: "2022-11-17T02:14:41.159Z",
    });

    assertEquals(normalized, {
      rawType: "new_status_set_to_request",
      normalizedType: "new_status_set_to_request",
      rawStatus: "requires_provider_review",
      normalizedStatus: "requires_provider_review",
      rawTargetEntityStatus: null,
      normalizedTargetEntityStatus: null,
      providerOrderId: "order::86709a29-xxxx-xxxx-xxxx-f9b2e9085981",
      trackingNumber: null,
      trackingUrl: null,
      occurredAt: "2022-11-17T02:14:41.159Z",
      shippedAt: null,
      deliveredAt: null,
      cancelledAt: null,
    });
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads shipping details payloads from eventData",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "shipping_details_set",
      eventData: {
        order: "order::8c2283fc-xxxx-xxxx-xxxx-399b08e24216",
        shippingDetails: {
          trackingNumber: "1Z99V3Y213xxxxxxxx",
          trackingURL: "https://carrier.example/track/1Z99V3Y213xxxxxxxx",
        },
      },
      createdAt: {
        $date: "2025-10-20T17:24:11.738Z",
      },
    });

    assertEquals(normalized, {
      rawType: "shipping_details_set",
      normalizedType: "shipping_details_set",
      rawStatus: null,
      normalizedStatus: null,
      rawTargetEntityStatus: null,
      normalizedTargetEntityStatus: null,
      providerOrderId: "order::8c2283fc-xxxx-xxxx-xxxx-399b08e24216",
      trackingNumber: "1Z99V3Y213xxxxxxxx",
      trackingUrl: "https://carrier.example/track/1Z99V3Y213xxxxxxxx",
      occurredAt: "2025-10-20T17:24:11.738Z",
      shippedAt: null,
      deliveredAt: null,
      cancelledAt: null,
    });
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads targetEntity.status for order_submitted",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "order_submitted",
      targetEntity: {
        id: "order::telegra-order-4",
        status: "requires_provider_review",
      },
    });

    assertEquals(normalized.rawType, "order_submitted");
    assertEquals(normalized.normalizedType, "order_submitted");
    assertEquals(normalized.rawStatus, null);
    assertEquals(normalized.normalizedStatus, null);
    assertEquals(normalized.rawTargetEntityStatus, "requires_provider_review");
    assertEquals(
      normalized.normalizedTargetEntityStatus,
      "requires_provider_review",
    );
    assertEquals(normalized.providerOrderId, "order::telegra-order-4");
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads targetEntity.id and targetEntity.status for order_updated",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "order_updated",
      targetEntity: {
        id: "order::telegra-order-5",
        status: "requires_order_processing",
      },
    });

    assertEquals(normalized.rawType, "order_updated");
    assertEquals(normalized.normalizedType, "order_updated");
    assertEquals(normalized.rawStatus, null);
    assertEquals(normalized.normalizedStatus, null);
    assertEquals(normalized.rawTargetEntityStatus, "requires_order_processing");
    assertEquals(
      normalized.normalizedTargetEntityStatus,
      "requires_order_processing",
    );
    assertEquals(normalized.providerOrderId, "order::telegra-order-5");
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads targetEntity.order.id for practitioner approval payloads",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "prescription_approved_by_practitioner",
      targetEntity: {
        order: {
          id: "order::telegra-order-2",
        },
      },
    });

    assertEquals(normalized.providerOrderId, "order::telegra-order-2");
    assertEquals(
      normalized.normalizedType,
      "prescription_approved_by_practitioner",
    );
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads targetEntity.order.id for prescription sent to pharmacy payloads",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "prescription_sent_to_pharmacy",
      targetEntity: {
        order: {
          id: "order::telegra-order-3",
        },
      },
    });

    assertEquals(normalized.providerOrderId, "order::telegra-order-3");
    assertEquals(normalized.normalizedType, "prescription_sent_to_pharmacy");
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent reads targetEntity.id for Telegra provider order resolution",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "order_submitted",
      status: "requires_provider_review",
      targetEntity: {
        id: "order::telegra-order-4",
      },
    });

    assertEquals(normalized.providerOrderId, "order::telegra-order-4");
    assertEquals(normalized.normalizedType, "order_submitted");
    assertEquals(normalized.normalizedStatus, "requires_provider_review");
    assertEquals(normalized.normalizedTargetEntityStatus, null);
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent ignores nested targetEntity.order ids for non-supported events",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "order_cancelled",
      targetEntity: {
        order: {
          _id: "order::1ed7fe7d-2183-431f-b9c1-2034bc75dc19",
        },
      },
    });

    assertEquals(normalized.providerOrderId, null);
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent ignores non-targetEntity.id provider order ids",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "prescription_approved_by_practitioner",
      targetEntity: {
        _id: "order::1ed7fe7d-2183-431f-b9c1-2034bc75dc19",
      },
    });

    assertEquals(normalized.providerOrderId, null);
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent ignores non-order targetEntity.id values",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "prescription_approved_by_practitioner",
      targetEntity: {
        id: "prfm::8ca21188-57f6-4249-a5e5-a759e305d5fa",
      },
    });

    assertEquals(normalized.providerOrderId, null);
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent ignores provider order ids that do not start with order::",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "prescription_sent_to_pharmacy",
      targetEntity: {
        id: "telegra-order-5",
      },
    });

    assertEquals(normalized.providerOrderId, null);
  },
);

Deno.test(
  "getTelegraWebhookOrderIdDiagnostics reports attempted paths and selected ids",
  () => {
    const diagnostics = getTelegraWebhookOrderIdDiagnostics({
      eventType: "prescription_approved_by_practitioner",
      targetEntity: {
        order: {
          id: "order::provider-order-1",
        },
      },
    });

    assertEquals(
      diagnostics.providerOrderId.selectedPath,
      "targetEntity.order.id",
    );
    assertEquals(
      diagnostics.providerOrderId.selectedValue,
      "order::provider-order-1",
    );
    assertEquals(diagnostics.providerOrderId.attempts, [
      {
        path: "targetEntity.order.id",
        exists: true,
        rawValue: "order::provider-order-1",
        acceptedValue: "order::provider-order-1",
        reason: "accepted",
      },
    ]);
  },
);

Deno.test(
  "getTelegraWebhookOrderIdDiagnostics uses eventData.order for shipping details payloads",
  () => {
    const diagnostics = getTelegraWebhookOrderIdDiagnostics({
      eventType: "shipping_details_set",
      eventData: {
        order: "order::provider-order-9",
      },
    });

    assertEquals(diagnostics.providerOrderId.selectedPath, "eventData.order");
    assertEquals(
      diagnostics.providerOrderId.selectedValue,
      "order::provider-order-9",
    );
    assertEquals(diagnostics.providerOrderId.attempts, [
      {
        path: "eventData.order",
        exists: true,
        rawValue: "order::provider-order-9",
        acceptedValue: "order::provider-order-9",
        reason: "accepted",
      },
      {
        path: "eventData.order.id",
        exists: false,
        rawValue: null,
        acceptedValue: null,
        reason: "missing",
      },
    ]);
  },
);

Deno.test(
  "normalizeTelegraWebhookEvent ignores invalid Telegra order ids across nested data paths",
  () => {
    const normalized = normalizeTelegraWebhookEvent({
      eventType: "prescription_sent_to_pharmacy",
      data: {
        targetEntity: {
          id: "telegra-order-8",
          order: {
            id: "telegra-order-10",
            _id: "telegra-order-11",
          },
        },
      },
    });

    assertEquals(normalized.providerOrderId, null);
  },
);

Deno.test("mapTelegraEventToOrderStatus maps shipment lifecycle events", () => {
  assertEquals(
    mapTelegraEventToOrderStatus(
      normalizeTelegraWebhookEvent({
        eventType: "shipment.status",
        status: "shipped",
      }),
    ),
    "in_transit",
  );
  assertEquals(
    mapTelegraEventToOrderStatus(
      normalizeTelegraWebhookEvent({ eventType: "shipment.exception" }),
    ),
    "shipping_exception",
  );
  assertEquals(
    mapTelegraEventToOrderStatus(
      normalizeTelegraWebhookEvent({ eventType: "order.cancelled" }),
    ),
    "order_cancelled",
  );
  assertEquals(
    mapTelegraEventToOrderStatus(
      normalizeTelegraWebhookEvent({ eventType: "shipping_details_set" }),
    ),
    "in_transit",
  );
});

Deno.test(
  "mapTelegraEventToOrderStatus maps new_status_set_to_request with requires_order_processing to payment_pending",
  () => {
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "new_status_set_to_request",
          targetEntity: {
            id: "order::telegra-order-6",
            status: "requires_order_processing",
          },
        }),
      ),
      "payment_pending",
    );

    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "new_status_set_to_request",
          targetEntity: {
            id: "order::telegra-order-7",
            status: "requires_provider_review",
          },
        }),
      ),
      "provider_review_pending",
    );
  },
);

Deno.test(
  "mapTelegraEventToOrderStatus maps prescription and pharmacy lifecycle events",
  () => {
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "prescription.review",
          status: "approved",
        }),
      ),
      "provider_review_pending",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "prescription.update",
          status: "additional_info_required",
        }),
      ),
      "medical_followup_required",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "new_status_set_to_request",
          status: "requires_provider_review",
        }),
      ),
      "provider_review_pending",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "prescription_approved_by_practitioner",
          targetEntity: {
            status: "requires_provider_review",
            order: {
              id: "order::telegra-order-2",
            },
          },
        }),
      ),
      "provider_approved",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "prescription_approved_by_practitioner",
          targetEntity: {
            order: {
              id: "order::telegra-order-2",
            },
          },
        }),
      ),
      "provider_approved",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "prescription_sent_to_pharmacy",
          targetEntity: {
            order: {
              id: "order::telegra-order-3",
            },
          },
        }),
      ),
      "order_sent_to_pharmacy",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "order_submitted",
          targetEntity: {
            id: "order::telegra-order-4",
            status: "requires_provider_review",
          },
        }),
      ),
      "provider_review_pending",
    );
    assertEquals(
      mapTelegraEventToOrderStatus(
        normalizeTelegraWebhookEvent({
          eventType: "pharmacy.fulfillment",
          status: "processing",
        }),
      ),
      "fulfillment_in_progress",
    );
  },
);

Deno.test(
  "getTelegraWebhookSecrets returns the Telegra access token for signature verification",
  () => {
    assertEquals(
      getTelegraWebhookSecrets({
        access_token: "access-token",
      }),
      ["access-token"],
    );
  },
);

Deno.test(
  "verifyTelegraWebhookSignature validates HMAC SHA-256 signatures",
  async () => {
    const payload = JSON.stringify({
      eventType: "prescription_approved_by_practitioner",
      targetEntity: {
        order: {
          id: "order::1ed7fe7d-2183-431f-b9c1-2034bc75dc19",
        },
      },
    });

    const key = await crypto.subtle.importKey(
      "raw",
      new TextEncoder().encode("shared-secret"),
      { name: "HMAC", hash: "SHA-256" },
      false,
      ["sign"],
    );
    const signatureBytes = new Uint8Array(
      await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(payload)),
    );
    const hexSignature = Array.from(signatureBytes)
      .map((byte) => byte.toString(16).padStart(2, "0"))
      .join("");

    assertEquals(
      await verifyTelegraWebhookSignature({
        payload,
        signatureHeader: `sha256=${hexSignature}`,
        secrets: ["shared-secret"],
      }),
      true,
    );
    assertEquals(
      await verifyTelegraWebhookSignature({
        payload,
        signatureHeader: hexSignature,
        secrets: ["shared-secret"],
      }),
      true,
    );
    assertEquals(
      await verifyTelegraWebhookSignature({
        payload,
        signatureHeader: hexSignature,
        secrets: ["wrong-secret"],
      }),
      false,
    );
  },
);

Deno.test(
  "timingSafeEqualString compares values without early length exits",
  () => {
    assertEquals(timingSafeEqualString("secret", "secret"), true);
    assertEquals(timingSafeEqualString("secret", "secret-2"), false);
    assertEquals(timingSafeEqualString("secret", "wrong"), false);
  },
);
