export interface StripeWebhookEvent {
  id?: string;
  type: string;
  data?: {
    object?: Record<string, unknown>;
  };
}

export interface StripeWebhookEventHandlers {
  handleCheckoutSessionCompleted: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleCheckoutSessionExpired: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handlePaymentIntentSucceeded: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handlePaymentIntentAmountCapturableUpdated: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handlePaymentIntentFailed: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handlePaymentIntentCancelled: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleSubscriptionCreated: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleSubscriptionUpdated: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleSubscriptionDeleted: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleInvoiceCreated: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleInvoicePaymentFailed: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
  handleCustomerUpdated: (
    object: Record<string, unknown>,
  ) => Promise<void> | void;
}

function getObjectId(object: Record<string, unknown>): string | null {
  const id = object.id;
  if (typeof id === "string" && id.trim().length > 0) {
    return id.trim();
  }
  return null;
}

export async function dispatchStripeWebhookEvent(params: {
  event: StripeWebhookEvent;
  requestId: string;
  handlers: StripeWebhookEventHandlers;
}): Promise<void> {
  const { event, requestId, handlers } = params;
  const object = event.data?.object || {};
  const objectId = getObjectId(object);

  switch (event.type) {
    case "checkout.session.completed": {
      console.info("Handling checkout.session.completed", {
        requestId,
        sessionId: objectId,
      });
      await handlers.handleCheckoutSessionCompleted(object);
      break;
    }

    case "checkout.session.expired": {
      console.info("Handling checkout.session.expired", {
        requestId,
        sessionId: objectId,
      });
      await handlers.handleCheckoutSessionExpired(object);
      break;
    }

    case "payment_intent.succeeded": {
      console.info("Handling payment_intent.succeeded", {
        requestId,
        paymentIntentId: objectId,
      });
      await handlers.handlePaymentIntentSucceeded(object);
      break;
    }

    case "payment_intent.amount_capturable_updated": {
      console.info("Handling payment_intent.amount_capturable_updated", {
        requestId,
        paymentIntentId: objectId,
      });
      await handlers.handlePaymentIntentAmountCapturableUpdated(object);
      break;
    }

    case "payment_intent.payment_failed": {
      console.info("Handling payment_intent.payment_failed", {
        requestId,
        paymentIntentId: objectId,
      });
      await handlers.handlePaymentIntentFailed(object);
      break;
    }

    case "payment_intent.cancelled": {
      console.info("Handling payment_intent.cancelled", {
        requestId,
        paymentIntentId: objectId,
      });
      await handlers.handlePaymentIntentCancelled(object);
      break;
    }

    case "customer.subscription.created": {
      console.info("Handling customer.subscription.created", {
        requestId,
        subscriptionId: objectId,
      });
      await handlers.handleSubscriptionCreated(object);
      break;
    }

    case "customer.subscription.updated": {
      console.info("Handling customer.subscription.updated", {
        requestId,
        subscriptionId: objectId,
      });
      await handlers.handleSubscriptionUpdated(object);
      break;
    }

    case "customer.subscription.deleted": {
      console.info("Handling customer.subscription.deleted", {
        requestId,
        subscriptionId: objectId,
      });
      await handlers.handleSubscriptionDeleted(object);
      break;
    }

    case "invoice.paid": {
      console.info("Handling invoice.paid", {
        requestId,
        invoiceId: objectId,
      });
      console.info("Ignoring invoice.paid for strict intended flow", {
        requestId,
        invoiceId: objectId,
      });
      break;
    }

    case "invoice.created": {
      console.info("Handling invoice.created", {
        requestId,
        invoiceId: objectId,
      });
      await handlers.handleInvoiceCreated(object);
      break;
    }

    case "invoice.finalized": {
      console.info("Handling invoice.finalized", {
        requestId,
        invoiceId: objectId,
      });
      await handlers.handleInvoiceCreated(object);
      break;
    }

    case "invoice.payment_failed": {
      console.info("Handling invoice.payment_failed", {
        requestId,
        invoiceId: objectId,
      });
      await handlers.handleInvoicePaymentFailed(object);
      break;
    }

    case "customer.updated": {
      console.info("Handling customer.updated", {
        requestId,
        customerId: objectId,
      });
      await handlers.handleCustomerUpdated(object);
      break;
    }

    default:
      console.info("Unhandled event type", {
        requestId,
        type: event.type,
        eventId: event.id,
      });
  }
}
