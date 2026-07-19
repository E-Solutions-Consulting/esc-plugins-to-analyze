export type InvoiceLookup = {
  subscription?: string | { id?: string; metadata?: Record<string, string> };
  subscription_details?: {
    metadata?: Record<string, string>;
  };
  parent?: {
    subscription_details?: {
      metadata?: Record<string, string>;
      subscription?: string;
    };
  };
  metadata?: Record<string, string>;
  lines?: {
    data?: Array<{
      metadata?: Record<string, string>;
      parent?: {
        subscription_item_details?: {
          subscription?: string;
        };
      };
      price?: {
        metadata?: Record<string, string>;
      };
    }>;
  };
};

export function getSubscriptionIdFromInvoice(
  invoice: InvoiceLookup,
): { id: string | null; source: string | null } {
  if (typeof invoice.subscription === "string" && invoice.subscription) {
    return { id: invoice.subscription, source: "invoice.subscription" };
  }

  if (typeof invoice.subscription === "object" && invoice.subscription?.id) {
    return { id: invoice.subscription.id, source: "invoice.subscription.id" };
  }

  const lineSubscription = invoice.lines?.data?.[0]?.parent
    ?.subscription_item_details?.subscription;
  if (lineSubscription) {
    return {
      id: lineSubscription,
      source: "lines[0].parent.subscription_item_details.subscription",
    };
  }

  const parentSubscription = invoice.parent?.subscription_details?.subscription;
  if (parentSubscription) {
    return {
      id: parentSubscription,
      source: "parent.subscription_details.subscription",
    };
  }

  return { id: null, source: null };
}

export function getCheckoutSessionIdFromInvoice(
  invoice: InvoiceLookup,
): { id: string | null; source: string | null } {
  const subscriptionDetailsId = invoice.subscription_details?.metadata
    ?.checkout_session_id;
  if (subscriptionDetailsId) {
    return {
      id: subscriptionDetailsId,
      source: "subscription_details.metadata.checkout_session_id",
    };
  }

  const parentSubscriptionDetailsId = invoice.parent?.subscription_details
    ?.metadata?.checkout_session_id;
  if (parentSubscriptionDetailsId) {
    return {
      id: parentSubscriptionDetailsId,
      source: "parent.subscription_details.metadata.checkout_session_id",
    };
  }

  const lineMetadataId = invoice.lines?.data?.[0]?.metadata
    ?.checkout_session_id;
  if (lineMetadataId) {
    return {
      id: lineMetadataId,
      source: "lines[0].metadata.checkout_session_id",
    };
  }

  const linePriceMetadataId = invoice.lines?.data?.[0]?.price?.metadata
    ?.checkout_session_id;
  if (linePriceMetadataId) {
    return {
      id: linePriceMetadataId,
      source: "lines[0].price.metadata.checkout_session_id",
    };
  }

  const invoiceMetadataId = invoice.metadata?.checkout_session_id;
  if (invoiceMetadataId) {
    return {
      id: invoiceMetadataId,
      source: "invoice.metadata.checkout_session_id",
    };
  }

  return { id: null, source: null };
}
