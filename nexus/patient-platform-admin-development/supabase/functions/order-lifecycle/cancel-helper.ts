import { dateTime } from "../_shared/dayjs.ts";
import {
  buildRefundEligibilityHistoryNote,
  determineRefundTierFromPreviousStatus,
  type RefundTier,
} from "./helpers.ts";
import { cancelMdiCaseForLifecycle } from "./mdi-helper.ts";
import { cancelTelegraOrderForLifecycle } from "./telegra-helper.ts";

const PROVIDER_CANCELLATION_FEE_CENTS = 5000;

// deno-lint-ignore no-explicit-any
type SupabaseClient = any;

interface CancellationStatusInfo {
  status_key?: string | null;
  admin_status_label?: string | null;
  display_order?: number | null;
}

interface MilestoneStatusRow {
  status_key: string;
  display_order: number | null;
}

interface PaymentTransactionRow {
  provider_payment_intent_id: string | null;
  provider_invoice_id: string | null;
  provider_charge_id: string | null;
  payment_status: string | null;
}

interface ResolvedStripePaymentReference {
  paymentIntentId: string | null;
  chargeId: string | null;
  providerInvoiceId: string | null;
  paymentStatus: string | null;
}

interface PlanOrderRow {
  id: string;
  created_at: string;
  renewal_at: string | null;
}

interface CancellationContext {
  nowIso: string;
  previousStatusKey: string | null;
  previousStatusLabel: string | null;
  hasReachedProviderReviewPending: boolean;
  shouldCancelProviderOrder: boolean;
  refundTier: RefundTier;
  refundAmountCents: number;
  retainedAmountCents: number;
  providerFeeCents: number;
  refundEligibilityHistoryNote: string;
  cancellationOperationKey: string | null;
  paymentIntentId: string | null;
  chargeId: string | null;
  paymentIntentStatus: string | null;
  shouldCaptureFullAmountBeforeRefund: boolean;
  needsProviderFeeCapture: boolean;
  needsRefundProcessing: boolean;
  needsPaymentIntentCancel: boolean;
  needsStripePlanUpdate: boolean;
  targetPlanId: string;
  targetPlanStatus: "pending_cancellation" | "cancelled";
  targetPlanCancelledAt: string | null;
  targetPlanExpiresAt: string | null | undefined;
  targetPlanStripeSubscriptionId: string | null;
  stripeSecretKey: string | null;
}

interface CancellationLogContext {
  requestId?: string;
  orderId?: string;
  tenantId?: string;
}

export interface OrderForPendingCancellation {
  id: string;
  order_number: string;
  tenant_id: string;
  patient_id: string;
  subscription_id: string | null;
  status_id: string | null;
  total_cents: number | null;
  internal_notes: string | null;
  cancellation_reason: string | null;
  cancellation_operation_key?: string | null;
  cancellation_operation_started_at?: string | null;
  cancellation_operation_completed_at?: string | null;
  provider_platform_integration_key?: string | null;
  order_statuses: CancellationStatusInfo | null;
}

export interface PendingCancellationAnalysisResult {
  analyzedAt: string;
  previousStatusKey: string | null;
  previousStatusLabel: string | null;
  refundTier: RefundTier;
  refundAmountCents: number;
  retainedAmountCents: number;
  providerFeeCents: number;
  refundEligibilityHistoryNote: string;
  paymentIntentStatus: string | null;
  needsRefundProcessing: boolean;
  needsPaymentIntentCancel: boolean;
  needsStripePlanUpdate: boolean;
  shouldMoveToProcessing: boolean;
}

export interface CancellationProcessingResult {
  completed: boolean;
  message: string;
  cancelledAt: string;
  refundTier: RefundTier;
  stripeRefundId: string | null;
  stripeRefundStatus: string | null;
  telegraCancelled: boolean;
  mdiCancelled: boolean;
  completionHistoryNote: string;
}

export interface ProviderRejectedPlanCancellationResult {
  updated: boolean;
  planId: string | null;
  cancelledAt: string | null;
  message: string;
}

export interface DirectCancellationResult {
  cancelledAt: string;
  completionHistoryNote: string;
}

function asSingle<T>(value: T | T[] | null | undefined): T | null {
  if (Array.isArray(value)) return value[0] ?? null;
  return value ?? null;
}

function formatCurrency(amountCents: number): string {
  return `$${(Math.max(0, amountCents) / 100).toFixed(2)}`;
}

function normalizePaymentIntentStatus(
  paymentStatus: string | null | undefined,
): string | null {
  if (typeof paymentStatus !== "string") return null;
  const normalized = paymentStatus.trim().toLowerCase();
  return normalized.length > 0 ? normalized : null;
}

function getStripeObjectId(
  value: string | { id?: string } | null | undefined,
): string | null {
  if (typeof value === "string" && value.trim().length > 0) {
    return value.trim();
  }
  if (
    value &&
    typeof value === "object" &&
    typeof value.id === "string" &&
    value.id.trim().length > 0
  ) {
    return value.id.trim();
  }
  return null;
}

function logCancellationDecision(
  message: string,
  details: Record<string, unknown>,
): void {
  console.info(message, details);
}

function isTelegraIntegrationKey(value: string | null | undefined): boolean {
  return typeof value === "string" &&
    value.trim().toLowerCase() === "telegramd";
}

function isMdiIntegrationKey(value: string | null | undefined): boolean {
  return typeof value === "string" &&
    value.trim().toLowerCase() === "md_integrations";
}

export function shouldCancelMdiCaseForLifecycle(params: {
  shouldCancelProviderOrder: boolean;
  previousStatusKey: string | null | undefined;
  providerPlatformIntegrationKey: string | null | undefined;
}): boolean {
  if (!isMdiIntegrationKey(params.providerPlatformIntegrationKey)) {
    return false;
  }

  if (params.shouldCancelProviderOrder) {
    return true;
  }

  return params.previousStatusKey === "patient_questionnaire_pending" ||
    params.previousStatusKey === "medical_questionnaire_pending";
}

export function shouldCancelTelegraOrderForLifecycle(params: {
  shouldCancelProviderOrder: boolean;
  previousStatusKey: string | null | undefined;
  providerPlatformIntegrationKey: string | null | undefined;
}): boolean {
  if (!isTelegraIntegrationKey(params.providerPlatformIntegrationKey)) {
    return false;
  }

  if (params.shouldCancelProviderOrder) {
    return true;
  }

  return params.previousStatusKey === "patient_questionnaire_pending" ||
    params.previousStatusKey === "medical_questionnaire_pending";
}

function isStripeResourceMissingError(
  errorText: string | null | undefined,
): boolean {
  if (!errorText) return false;

  try {
    const parsed = JSON.parse(errorText) as {
      error?: {
        code?: string;
        type?: string;
      };
    };
    return parsed.error?.code === "resource_missing";
  } catch {
    return false;
  }
}

function buildStripeCancellationIdempotencyKey(
  operationKey: string | null | undefined,
  action: string,
): string | null {
  if (!operationKey) return null;
  return `order_cancel_${operationKey}_${action}`.slice(0, 255);
}

async function resolveStripePaymentReferenceFromInvoice(params: {
  providerInvoiceId: string | null;
  stripeSecretKey: string | null;
  logContext?: CancellationLogContext;
}): Promise<{ paymentIntentId: string | null; chargeId: string | null }> {
  const { providerInvoiceId, stripeSecretKey, logContext } = params;

  if (!providerInvoiceId || !stripeSecretKey) {
    logCancellationDecision(
      "Skipping Stripe invoice lookup for payment reference resolution",
      {
        ...logContext,
        providerInvoiceId,
        hasStripeSecretKey: Boolean(stripeSecretKey),
      },
    );
    return { paymentIntentId: null, chargeId: null };
  }

  logCancellationDecision("Resolving Stripe payment reference from invoice", {
    ...logContext,
    providerInvoiceId,
  });

  const response = await fetch(
    `https://api.stripe.com/v1/invoices/${
      encodeURIComponent(providerInvoiceId)
    }?expand[]=payment_intent.latest_charge`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${stripeSecretKey}`,
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    console.warn(
      "Stripe invoice lookup failed for payment reference resolution",
      {
        ...logContext,
        providerInvoiceId,
        status: response.status,
        error: errorText || null,
      },
    );
    return { paymentIntentId: null, chargeId: null };
  }

  const invoice = (await response.json()) as {
    payment_intent?:
      | string
      | {
        id?: string;
        latest_charge?: string | { id?: string } | null;
      }
      | null;
    charge?: string | { id?: string } | null;
  };

  const paymentIntentId = getStripeObjectId(invoice.payment_intent ?? null);
  const paymentIntentChargeId =
    invoice.payment_intent && typeof invoice.payment_intent === "object"
      ? getStripeObjectId(invoice.payment_intent.latest_charge ?? null)
      : null;
  const chargeId = paymentIntentChargeId ||
    getStripeObjectId(invoice.charge ?? null);

  logCancellationDecision("Resolved Stripe payment reference from invoice", {
    ...logContext,
    providerInvoiceId,
    paymentIntentId,
    chargeId,
  });

  return { paymentIntentId, chargeId };
}

async function resolveStripePaymentReferenceFromTransactions(params: {
  paymentTransactions: PaymentTransactionRow[];
  stripeSecretKey: string | null;
  logContext?: CancellationLogContext;
}): Promise<ResolvedStripePaymentReference> {
  const { paymentTransactions, stripeSecretKey, logContext } = params;

  logCancellationDecision(
    "Resolving Stripe payment reference from transactions",
    {
      ...logContext,
      transactionCount: paymentTransactions.length,
    },
  );

  for (const transaction of paymentTransactions) {
    const paymentIntentId =
      typeof transaction.provider_payment_intent_id === "string"
        ? transaction.provider_payment_intent_id.trim()
        : "";
    const providerInvoiceId =
      typeof transaction.provider_invoice_id === "string"
        ? transaction.provider_invoice_id.trim()
        : "";
    const chargeId = typeof transaction.provider_charge_id === "string"
      ? transaction.provider_charge_id.trim()
      : "";

    if (paymentIntentId || chargeId) {
      logCancellationDecision(
        "Resolved Stripe payment reference directly from transaction row",
        {
          ...logContext,
          paymentIntentId: paymentIntentId || null,
          chargeId: chargeId || null,
          providerInvoiceId: providerInvoiceId || null,
          paymentStatus: normalizePaymentIntentStatus(
            transaction.payment_status,
          ),
        },
      );
      return {
        paymentIntentId: paymentIntentId || null,
        chargeId: chargeId || null,
        providerInvoiceId: providerInvoiceId || null,
        paymentStatus: normalizePaymentIntentStatus(transaction.payment_status),
      };
    }

    if (providerInvoiceId) {
      const invoiceReference = await resolveStripePaymentReferenceFromInvoice({
        providerInvoiceId,
        stripeSecretKey,
        logContext,
      });

      if (invoiceReference.paymentIntentId || invoiceReference.chargeId) {
        logCancellationDecision(
          "Resolved Stripe payment reference from transaction invoice fallback",
          {
            ...logContext,
            providerInvoiceId,
            paymentIntentId: invoiceReference.paymentIntentId,
            chargeId: invoiceReference.chargeId,
            paymentStatus: normalizePaymentIntentStatus(
              transaction.payment_status,
            ),
          },
        );
        return {
          paymentIntentId: invoiceReference.paymentIntentId,
          chargeId: invoiceReference.chargeId,
          providerInvoiceId,
          paymentStatus: normalizePaymentIntentStatus(
            transaction.payment_status,
          ),
        };
      }
    }
  }

  logCancellationDecision("No Stripe payment reference could be resolved", {
    ...logContext,
    transactionCount: paymentTransactions.length,
  });

  return {
    paymentIntentId: null,
    chargeId: null,
    providerInvoiceId: null,
    paymentStatus: null,
  };
}

function isPaymentIntentAlreadyFinal(status: string | null): boolean {
  return (
    status === "canceled" || status === "cancelled" || status === "succeeded"
  );
}

function isPaymentIntentCapturable(status: string | null): boolean {
  return status === "requires_capture";
}

function isStripeRefundProcessable(params: {
  paymentIntentId: string | null;
  chargeId: string | null;
  paymentIntentStatus: string | null;
}): boolean {
  const { paymentIntentId, chargeId, paymentIntentStatus } = params;

  if (paymentIntentStatus === "succeeded") {
    return true;
  }

  // Charge-level refund still works when PI context is missing but a charge exists.
  if (!paymentIntentId && Boolean(chargeId)) {
    return true;
  }

  return false;
}

function buildProcessingCompletionHistoryNote(params: {
  previousStatusLabel: string | null;
  previousStatusKey: string | null;
  refundTier: RefundTier;
  refundAmountCents: number;
  retainedAmountCents: number;
  providerFeeCents: number;
  stripeRefundId: string | null;
  paymentIntentId: string | null;
  chargeId: string | null;
  providerFeeCaptured: boolean;
  paymentIntentCancelled: boolean;
  telegraCancelled: boolean;
  mdiCancelled: boolean;
  planStatus: string | null;
}): string {
  const {
    previousStatusLabel,
    previousStatusKey,
    refundTier,
    refundAmountCents,
    retainedAmountCents,
    providerFeeCents,
    stripeRefundId,
    paymentIntentId,
    chargeId,
    providerFeeCaptured,
    paymentIntentCancelled,
    telegraCancelled,
    mdiCancelled,
    planStatus,
  } = params;

  const statusDescriptor = previousStatusLabel || previousStatusKey ||
    "unknown previous status";
  const refundSummary = refundTier === "none"
    ? `No refund issued; retained ${formatCurrency(retainedAmountCents)}`
    : providerFeeCaptured
    ? `Provider fee captured ${
      formatCurrency(providerFeeCents)
    } and remaining authorized amount released`
    : refundTier === "partial"
    ? `Partial refund issued ${
      formatCurrency(
        refundAmountCents,
      )
    } after retaining provider fee ${formatCurrency(providerFeeCents)}`
    : `Full refund issued ${formatCurrency(refundAmountCents)}`;
  const refundSuffix = stripeRefundId
    ? ` (Stripe refund ${stripeRefundId})`
    : "";
  const paymentReferenceSummary = paymentIntentId || chargeId
    ? ` Payment refs:${paymentIntentId ? ` pi=${paymentIntentId}` : ""}${
      chargeId ? ` ch=${chargeId}` : ""
    }.`
    : "";
  const paymentIntentSummary = paymentIntentCancelled
    ? " Payment intent cancelled in Stripe."
    : "";
  const telegraSummary = telegraCancelled ? " Telegra order cancelled." : "";
  const mdiSummary = mdiCancelled ? " MDI case cancelled." : "";
  const planSummary = planStatus
    ? ` Linked plan updated to ${planStatus}.`
    : "";

  return `Cancellation processing completed from ${statusDescriptor}. ${refundSummary}${refundSuffix}.${paymentReferenceSummary}${paymentIntentSummary}${telegraSummary}${mdiSummary}${planSummary}`;
}

function buildDirectCancellationHistoryNote(params: {
  previousStatusLabel: string | null;
  previousStatusKey: string | null;
  planStatus: string | null;
}): string {
  const { previousStatusLabel, previousStatusKey, planStatus } = params;
  const statusDescriptor = previousStatusLabel || previousStatusKey ||
    "unknown previous status";
  const planSummary = planStatus
    ? ` Linked plan updated to ${planStatus}.`
    : "";
  return `Order cancelled directly from pending cancellation because no refund or Stripe processing was required after ${statusDescriptor}.${planSummary}`;
}

function buildProviderCancellationHistoryNote(params: {
  providerName: string;
  externalOrderId: string | null;
  cancelled: boolean;
  message: string;
}): string {
  const { providerName, externalOrderId, cancelled, message } = params;
  const providerOrderSummary = externalOrderId
    ? ` Provider order id: ${externalOrderId}.`
    : "";

  return cancelled
    ? `Provider cancellation succeeded for ${providerName}.${providerOrderSummary} ${message}`
    : `Provider cancellation failed for ${providerName}.${providerOrderSummary} ${message}`;
}

async function insertOrderStatusHistoryNote(params: {
  supabase: SupabaseClient;
  orderId: string;
  statusId: string | null;
  notes: string;
}): Promise<void> {
  const { supabase, orderId, statusId, notes } = params;

  if (!statusId) return;

  const { error } = await supabase.from("order_status_history").insert({
    order_id: orderId,
    status_id: statusId,
    notes,
  });

  if (error) {
    throw new Error(`Failed to insert order status history: ${error.message}`);
  }
}

async function resolvePreviousStatusContext(params: {
  supabase: SupabaseClient;
  orderId: string;
  logContext?: CancellationLogContext;
}): Promise<{
  previousStatusKey: string | null;
  previousStatusLabel: string | null;
  previousDisplayOrder: number | null;
  hasReachedProviderReviewPending: boolean;
  shouldCancelProviderOrder: boolean;
}> {
  const { supabase, orderId, logContext } = params;

  const { data: statusHistoryRows, error: statusHistoryError } = await supabase
    .from("order_status_history")
    .select(
      `
      created_at,
      order_statuses (
        status_key,
        admin_status_label,
        display_order
      )
    `,
    )
    .eq("order_id", orderId)
    .order("created_at", { ascending: false })
    .limit(20);

  if (statusHistoryError) {
    throw new Error(
      `Failed to fetch order status history: ${statusHistoryError.message}`,
    );
  }

  const typedStatusHistory = (
    (statusHistoryRows || []) as Array<{
      order_statuses: CancellationStatusInfo | CancellationStatusInfo[] | null;
    }>
  ).map((entry) => asSingle(entry.order_statuses));

  const latestProviderDecisionStatus = typedStatusHistory.find(
    (status) =>
      status?.status_key === "provider_approved" ||
      status?.status_key === "provider_rejected",
  ) || null;

  const previousStatusInfo = typedStatusHistory.find(
    (status) =>
      status?.status_key !== "order_pending_cancellation" &&
      status?.status_key !== "order_cancellation_processing" &&
      status?.status_key !== "payment_pending",
  ) || null;

  const effectivePreviousStatus = latestProviderDecisionStatus ||
    previousStatusInfo;

  logCancellationDecision("Resolved cancellation previous-status context", {
    ...logContext,
    orderId,
    previousStatusKey: effectivePreviousStatus?.status_key || null,
    previousStatusLabel: effectivePreviousStatus?.admin_status_label || null,
    previousDisplayOrder:
      typeof effectivePreviousStatus?.display_order === "number"
        ? effectivePreviousStatus.display_order
        : null,
    hasReachedProviderReviewPending: typedStatusHistory.some(
      (status) => status?.status_key === "provider_review_pending",
    ),
    shouldCancelProviderOrder: typedStatusHistory.some(
      (status) =>
        status?.status_key === "provider_review_pending" ||
        status?.status_key === "provider_approved" ||
        status?.status_key === "order_approved",
    ),
  });

  return {
    previousStatusKey: effectivePreviousStatus?.status_key || null,
    previousStatusLabel: effectivePreviousStatus?.admin_status_label || null,
    previousDisplayOrder:
      typeof effectivePreviousStatus?.display_order === "number"
        ? effectivePreviousStatus.display_order
        : null,
    hasReachedProviderReviewPending: typedStatusHistory.some(
      (status) => status?.status_key === "provider_review_pending",
    ),
    shouldCancelProviderOrder: typedStatusHistory.some(
      (status) =>
        status?.status_key === "provider_review_pending" ||
        status?.status_key === "provider_approved" ||
        status?.status_key === "order_approved",
    ),
  };
}

async function resolveRefundTierContext(params: {
  supabase: SupabaseClient;
  previousStatusKey: string | null;
  previousStatusLabel: string | null;
  previousDisplayOrder: number | null;
  totalCents: number;
  logContext?: CancellationLogContext;
}): Promise<{
  refundTier: RefundTier;
  refundAmountCents: number;
  retainedAmountCents: number;
  providerFeeCents: number;
  refundEligibilityHistoryNote: string;
}> {
  const {
    supabase,
    previousStatusKey,
    previousStatusLabel,
    previousDisplayOrder,
    totalCents,
    logContext,
  } = params;

  const providerReviewStatusKeys = [
    "provider_review_pending",
    "provider_approved",
    "order_approved",
  ];
  const sentToPharmacyStatusKeys = [
    "sent_to_pharmacy",
    "order_sent_to_pharmacy",
    "pharmacy_approval_pending",
    "approved_by_pharmacy",
    "pharmacy_approved",
    "fulfillment_in_progress",
    "final_pharmacy_verification",
    "in_transit",
    "delivered",
  ];
  const milestoneStatusKeys = Array.from(
    new Set([...providerReviewStatusKeys, ...sentToPharmacyStatusKeys]),
  );

  const { data: milestoneStatuses, error: milestoneStatusesError } =
    await supabase
      .from("order_statuses")
      .select("status_key, display_order")
      .in("status_key", milestoneStatusKeys)
      .eq("is_active", true);

  if (milestoneStatusesError) {
    throw new Error(
      `Order status milestone configuration is not available: ${milestoneStatusesError.message}`,
    );
  }

  const typedMilestoneStatuses = (milestoneStatuses ||
    []) as MilestoneStatusRow[];

  const providerReviewDisplayOrderThreshold = typedMilestoneStatuses
    .filter(
      (status) =>
        providerReviewStatusKeys.includes(status.status_key) &&
        typeof status.display_order === "number",
    )
    .reduce((currentMin: number | null, status) => {
      const displayOrder = status.display_order as number;
      return currentMin === null
        ? displayOrder
        : Math.min(currentMin, displayOrder);
    }, null);

  const sentToPharmacyDisplayOrderThreshold = typedMilestoneStatuses
    .filter(
      (status) =>
        sentToPharmacyStatusKeys.includes(status.status_key) &&
        typeof status.display_order === "number",
    )
    .reduce((currentMin: number | null, status) => {
      const displayOrder = status.display_order as number;
      return currentMin === null
        ? displayOrder
        : Math.min(currentMin, displayOrder);
    }, null);

  const refundTier = determineRefundTierFromPreviousStatus({
    previousStatusKey,
    previousDisplayOrder,
    providerReviewDisplayOrderThreshold,
    sentToPharmacyDisplayOrderThreshold,
  });

  const providerFeeCents = refundTier === "partial"
    ? Math.min(PROVIDER_CANCELLATION_FEE_CENTS, totalCents)
    : 0;
  const refundAmountCents = refundTier === "full"
    ? totalCents
    : refundTier === "partial"
    ? Math.max(totalCents - PROVIDER_CANCELLATION_FEE_CENTS, 0)
    : 0;
  const retainedAmountCents = Math.max(totalCents - refundAmountCents, 0);

  logCancellationDecision("Resolved cancellation refund tier context", {
    ...logContext,
    previousStatusKey,
    previousStatusLabel,
    previousDisplayOrder,
    totalCents,
    providerReviewDisplayOrderThreshold,
    sentToPharmacyDisplayOrderThreshold,
    refundTier,
    refundAmountCents,
    retainedAmountCents,
    providerFeeCents,
  });

  return {
    refundTier,
    refundAmountCents,
    retainedAmountCents,
    providerFeeCents,
    refundEligibilityHistoryNote: buildRefundEligibilityHistoryNote({
      previousStatusKey,
      previousStatusLabel,
      refundTier,
      refundAmountCents,
      retainedAmountCents,
      providerFeeCents,
    }),
  };
}

async function resolvePlanCancellationContext(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  nowIso: string;
  logContext?: CancellationLogContext;
}): Promise<{
  targetPlanId: string;
  targetPlanStatus: "pending_cancellation" | "cancelled";
  targetPlanCancelledAt: string | null;
  targetPlanExpiresAt: string | null | undefined;
  targetPlanStripeSubscriptionId: string | null;
}> {
  const { supabase, order, nowIso, logContext } = params;

  if (
    typeof order.subscription_id !== "string" ||
    order.subscription_id.length === 0
  ) {
    throw new Error("Linked plan not found for this order");
  }

  const { data: plan, error: planError } = await supabase
    .from("subscriptions")
    .select("id")
    .eq("id", order.subscription_id)
    .eq("tenant_id", order.tenant_id)
    .eq("patient_id", order.patient_id)
    .maybeSingle();

  if (planError) {
    throw new Error(`Failed to fetch linked plan: ${planError.message}`);
  }

  if (!plan) {
    throw new Error("Linked plan not found for this order");
  }

  const { data: planOrders, error: planOrdersError } = await supabase
    .from("orders")
    .select("id, created_at, renewal_at")
    .eq("subscription_id", plan.id)
    .eq("tenant_id", order.tenant_id)
    .eq("patient_id", order.patient_id)
    .order("created_at", { ascending: true })
    .order("id", { ascending: true });

  if (planOrdersError) {
    throw new Error(
      `Failed to fetch linked plan orders: ${planOrdersError.message}`,
    );
  }

  const typedPlanOrders = (planOrders || []) as PlanOrderRow[];
  const currentOrderIndex = typedPlanOrders.findIndex(
    (entry) => entry.id === order.id,
  );

  if (currentOrderIndex === -1) {
    throw new Error("Order is not linked to its subscription timeline");
  }

  let targetPlanStatus: "pending_cancellation" | "cancelled" = "cancelled";
  let targetPlanCancelledAt: string | null = nowIso;
  let targetPlanExpiresAt: string | null | undefined;
  const shouldCancelPlanImmediately =
    order.order_statuses?.status_key === "provider_rejected";

  if (currentOrderIndex > 0 && !shouldCancelPlanImmediately) {
    const previousOrder = typedPlanOrders[currentOrderIndex - 1];
    const previousExpirationTime = previousOrder.renewal_at
      ? dateTime(previousOrder.renewal_at).valueOf()
      : Number.NaN;

    if (Number.isFinite(previousExpirationTime)) {
      targetPlanExpiresAt = dateTime(previousExpirationTime)
        .toDate()
        .toISOString();
      targetPlanStatus = previousExpirationTime > Date.now()
        ? "pending_cancellation"
        : "cancelled";
      targetPlanCancelledAt = targetPlanStatus === "cancelled" ? nowIso : null;
    }
  }

  const { data: stripeLink, error: stripeLinkError } = await supabase
    .from("subscription_payment_provider_links")
    .select(
      `
      provider_subscription_id,
      payment_providers!inner (
        key
      )
    `,
    )
    .eq("subscription_id", plan.id)
    .eq("tenant_id", order.tenant_id)
    .eq("payment_providers.key", "stripe")
    .maybeSingle();

  if (stripeLinkError) {
    throw new Error(
      `Failed to fetch linked plan payment provider link: ${stripeLinkError.message}`,
    );
  }

  logCancellationDecision("Resolved linked plan cancellation context", {
    ...logContext,
    targetPlanId: plan.id,
    currentOrderIndex,
    planOrderCount: typedPlanOrders.length,
    shouldCancelPlanImmediately,
    targetPlanStatus,
    targetPlanCancelledAt,
    targetPlanExpiresAt: targetPlanExpiresAt ?? null,
    targetPlanStripeSubscriptionId:
      stripeLink?.provider_subscription_id?.trim() || null,
  });

  return {
    targetPlanId: plan.id,
    targetPlanStatus,
    targetPlanCancelledAt,
    targetPlanExpiresAt,
    targetPlanStripeSubscriptionId:
      stripeLink?.provider_subscription_id?.trim() || null,
  };
}

async function resolveStripeSecretKey(params: {
  supabase: SupabaseClient;
  tenantId: string;
  logContext?: CancellationLogContext;
}): Promise<string | null> {
  const { supabase, tenantId, logContext } = params;

  const { data: stripeProvider, error: providerError } = await supabase
    .from("tenant_payment_providers")
    .select(
      `
      settings,
      payment_providers!inner (
        key
      )
    `,
    )
    .eq("tenant_id", tenantId)
    .eq("is_enabled", true)
    .eq("payment_providers.key", "stripe")
    .maybeSingle();

  if (providerError || !stripeProvider) {
    throw new Error(
      `No Stripe payment provider configured for this tenant: ${
        providerError?.message || "provider_not_found"
      }`,
    );
  }

  const settings = stripeProvider.settings as Record<string, string>;
  const stripeSecretKey = settings?.secret_key || null;
  if (!stripeSecretKey) {
    throw new Error("Stripe secret key not configured");
  }

  logCancellationDecision(
    "Resolved Stripe configuration for cancellation flow",
    {
      ...logContext,
      tenantId,
      hasStripeSecretKey: true,
    },
  );

  return stripeSecretKey;
}

async function resolvePaymentIntentContext(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  stripeSecretKey: string | null;
  logContext?: CancellationLogContext;
}): Promise<{
  paymentIntentId: string | null;
  chargeId: string | null;
  paymentIntentStatus: string | null;
  needsPaymentIntentCancel: boolean;
}> {
  const { supabase, order, stripeSecretKey, logContext } = params;

  const { data: paymentTransactions, error: paymentTransactionError } =
    await supabase
      .from("order_payment_provider_transactions")
      .select(
        `
        provider_payment_intent_id,
        provider_invoice_id,
        provider_charge_id,
        payment_status,
        created_at,
        payment_providers!inner (
          key
        )
      `,
      )
      .eq("order_id", order.id)
      .eq("tenant_id", order.tenant_id)
      .eq("payment_providers.key", "stripe")
      .order("created_at", { ascending: false })
      .limit(10);

  if (paymentTransactionError) {
    throw new Error(
      `Failed to fetch order payment transaction: ${paymentTransactionError.message}`,
    );
  }

  const internalNotes = typeof order.internal_notes === "string"
    ? order.internal_notes
    : "";
  const resolvedReference = await resolveStripePaymentReferenceFromTransactions(
    {
      paymentTransactions: (paymentTransactions ||
        []) as PaymentTransactionRow[],
      stripeSecretKey,
      logContext,
    },
  );

  const resolvedPaymentIntentId = resolvedReference.paymentIntentId ||
    internalNotes.match(/Payment Intent:\s*([^,\s]+)/i)?.[1]?.trim() ||
    null;
  let chargeId = resolvedReference.chargeId;
  let paymentIntentStatus = resolvedReference.paymentStatus;

  if (resolvedPaymentIntentId && stripeSecretKey) {
    logCancellationDecision(
      "Fetching Stripe payment intent state for cancellation",
      {
        ...logContext,
        paymentIntentId: resolvedPaymentIntentId,
        resolvedChargeId: chargeId,
        transactionPaymentStatus: paymentIntentStatus,
      },
    );

    const paymentIntentResponse = await fetch(
      `https://api.stripe.com/v1/payment_intents/${resolvedPaymentIntentId}`,
      {
        method: "GET",
        headers: {
          Authorization: `Bearer ${stripeSecretKey}`,
        },
      },
    );

    if (!paymentIntentResponse.ok) {
      const errorText = await paymentIntentResponse.text();
      throw new Error(
        `Failed to resolve Stripe payment intent state: ${
          errorText || paymentIntentResponse.status
        }`,
      );
    }

    const paymentIntent = (await paymentIntentResponse.json()) as {
      status?: string;
      latest_charge?: string | { id?: string } | null;
    };
    paymentIntentStatus = normalizePaymentIntentStatus(
      paymentIntent.status || null,
    );
    if (!chargeId) {
      chargeId = getStripeObjectId(paymentIntent.latest_charge ?? null);
    }
  }

  const needsPaymentIntentCancel = Boolean(resolvedPaymentIntentId) &&
    !isPaymentIntentAlreadyFinal(paymentIntentStatus);

  logCancellationDecision(
    "Resolved Stripe payment intent cancellation context",
    {
      ...logContext,
      paymentIntentId: resolvedPaymentIntentId,
      chargeId,
      paymentIntentStatus,
      needsPaymentIntentCancel,
    },
  );

  return {
    paymentIntentId: resolvedPaymentIntentId,
    chargeId,
    paymentIntentStatus,
    needsPaymentIntentCancel,
  };
}

async function buildCancellationContext(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  requestId?: string;
}): Promise<CancellationContext> {
  const { supabase, order, requestId } = params;
  const nowIso = dateTime().toISOString();
  const totalCents = Math.max(0, order.total_cents || 0);
  const logContext: CancellationLogContext = {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
  };

  const previousStatus = await resolvePreviousStatusContext({
    supabase,
    orderId: order.id,
    logContext,
  });

  const refundContext = await resolveRefundTierContext({
    supabase,
    previousStatusKey: previousStatus.previousStatusKey,
    previousStatusLabel: previousStatus.previousStatusLabel,
    previousDisplayOrder: previousStatus.previousDisplayOrder,
    totalCents,
    logContext,
  });

  const planContext = await resolvePlanCancellationContext({
    supabase,
    order,
    nowIso,
    logContext,
  });

  const needsStripeProvider = refundContext.refundAmountCents > 0 ||
    Boolean(planContext.targetPlanStripeSubscriptionId);
  const stripeSecretKey = needsStripeProvider || order.internal_notes
    ? await resolveStripeSecretKey({
      supabase,
      tenantId: order.tenant_id,
      logContext,
    }).catch((error) => {
      if (needsStripeProvider) throw error;
      return null;
    })
    : null;

  const paymentIntentContext = await resolvePaymentIntentContext({
    supabase,
    order,
    stripeSecretKey,
    logContext,
  });

  const shouldCaptureFullAmountBeforeRefund =
    refundContext.refundTier === "partial" &&
    previousStatus.previousStatusKey === "order_approved" &&
    isPaymentIntentCapturable(paymentIntentContext.paymentIntentStatus) &&
    Boolean(paymentIntentContext.paymentIntentId) &&
    refundContext.refundAmountCents > 0;
  const needsProviderFeeCapture = !shouldCaptureFullAmountBeforeRefund &&
    refundContext.refundTier === "partial" &&
    isPaymentIntentCapturable(paymentIntentContext.paymentIntentStatus) &&
    Boolean(paymentIntentContext.paymentIntentId) &&
    refundContext.providerFeeCents > 0;
  const needsRefundProcessing = refundContext.refundTier !== "none" &&
    (
      shouldCaptureFullAmountBeforeRefund ||
      isStripeRefundProcessable({
        paymentIntentId: paymentIntentContext.paymentIntentId,
        chargeId: paymentIntentContext.chargeId,
        paymentIntentStatus: paymentIntentContext.paymentIntentStatus,
      })
    );

  logCancellationDecision("Built cancellation processing context", {
    ...logContext,
    totalCents,
    previousStatusKey: previousStatus.previousStatusKey,
    previousStatusLabel: previousStatus.previousStatusLabel,
    refundTier: refundContext.refundTier,
    refundAmountCents: refundContext.refundAmountCents,
    retainedAmountCents: refundContext.retainedAmountCents,
    providerFeeCents: refundContext.providerFeeCents,
    paymentIntentId: paymentIntentContext.paymentIntentId,
    chargeId: paymentIntentContext.chargeId,
    paymentIntentStatus: paymentIntentContext.paymentIntentStatus,
    shouldCaptureFullAmountBeforeRefund,
    needsProviderFeeCapture,
    needsRefundProcessing,
    needsPaymentIntentCancel: paymentIntentContext.needsPaymentIntentCancel,
    needsStripePlanUpdate: Boolean(planContext.targetPlanStripeSubscriptionId),
    targetPlanId: planContext.targetPlanId,
    targetPlanStatus: planContext.targetPlanStatus,
    targetPlanStripeSubscriptionId: planContext.targetPlanStripeSubscriptionId,
  });

  return {
    nowIso,
    previousStatusKey: previousStatus.previousStatusKey,
    previousStatusLabel: previousStatus.previousStatusLabel,
    hasReachedProviderReviewPending:
      previousStatus.hasReachedProviderReviewPending,
    shouldCancelProviderOrder: previousStatus.shouldCancelProviderOrder,
    refundTier: refundContext.refundTier,
    refundAmountCents: refundContext.refundAmountCents,
    retainedAmountCents: refundContext.retainedAmountCents,
    providerFeeCents: refundContext.providerFeeCents,
    refundEligibilityHistoryNote: refundContext.refundEligibilityHistoryNote,
    cancellationOperationKey:
      typeof order.cancellation_operation_key === "string" &&
        order.cancellation_operation_key.trim().length > 0
        ? order.cancellation_operation_key.trim()
        : null,
    paymentIntentId: paymentIntentContext.paymentIntentId,
    chargeId: paymentIntentContext.chargeId,
    paymentIntentStatus: paymentIntentContext.paymentIntentStatus,
    shouldCaptureFullAmountBeforeRefund,
    needsProviderFeeCapture,
    needsRefundProcessing,
    needsPaymentIntentCancel: paymentIntentContext.needsPaymentIntentCancel,
    needsStripePlanUpdate: Boolean(planContext.targetPlanStripeSubscriptionId),
    targetPlanId: planContext.targetPlanId,
    targetPlanStatus: planContext.targetPlanStatus,
    targetPlanCancelledAt: planContext.targetPlanCancelledAt,
    targetPlanExpiresAt: planContext.targetPlanExpiresAt,
    targetPlanStripeSubscriptionId: planContext.targetPlanStripeSubscriptionId,
    stripeSecretKey,
  };
}

async function applyPlanCancellationState(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  context: CancellationContext;
}): Promise<void> {
  const { supabase, order, context } = params;

  const planUpdatePayload: {
    status: "pending_cancellation" | "cancelled";
    cancelled_at: string | null;
    expires_at?: string | null;
    cancellation_reason: string | null;
  } = {
    status: context.targetPlanStatus,
    cancelled_at: context.targetPlanCancelledAt,
    cancellation_reason: order.cancellation_reason,
  };

  if (context.targetPlanExpiresAt !== undefined) {
    planUpdatePayload.expires_at = context.targetPlanExpiresAt;
  }

  const { error: updatedPlanError } = await supabase
    .from("subscriptions")
    .update(planUpdatePayload)
    .eq("id", context.targetPlanId)
    .eq("tenant_id", order.tenant_id)
    .eq("patient_id", order.patient_id);

  if (updatedPlanError) {
    throw new Error(
      `Order cancelled, but failed to update linked plan: ${updatedPlanError.message}`,
    );
  }
}

async function applyStripePlanCancellationIfNeeded(params: {
  context: CancellationContext;
  logContext?: CancellationLogContext;
}): Promise<void> {
  const { context, logContext } = params;

  if (!context.targetPlanStripeSubscriptionId) {
    logCancellationDecision("Skipping Stripe plan cancellation update", {
      ...logContext,
      reason: "no_stripe_subscription_link",
      targetPlanStatus: context.targetPlanStatus,
    });
    return;
  }
  if (!context.stripeSecretKey) {
    throw new Error("Stripe secret key not configured");
  }

  if (context.targetPlanStatus === "pending_cancellation") {
    const stripeCancelAtPeriodEndParams = new URLSearchParams();
    stripeCancelAtPeriodEndParams.append("cancel_at_period_end", "true");
    stripeCancelAtPeriodEndParams.append(
      "metadata[allia_plan_status]",
      "pending_cancellation",
    );
    stripeCancelAtPeriodEndParams.append(
      "metadata[allia_pending_cancellation_source]",
      "order_lifecycle_cancellation_processing",
    );

    logCancellationDecision(
      "Calling Stripe subscription update for pending cancellation",
      {
        ...logContext,
        subscriptionId: context.targetPlanStripeSubscriptionId,
        targetPlanStatus: context.targetPlanStatus,
        cancelAtPeriodEnd: true,
      },
    );

    const stripePendingCancelResponse = await fetch(
      `https://api.stripe.com/v1/subscriptions/${context.targetPlanStripeSubscriptionId}`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${context.stripeSecretKey}`,
          "Content-Type": "application/x-www-form-urlencoded",
          ...(buildStripeCancellationIdempotencyKey(
              context.cancellationOperationKey,
              "subscription_pending_cancel",
            )
            ? {
              "Idempotency-Key": buildStripeCancellationIdempotencyKey(
                context.cancellationOperationKey,
                "subscription_pending_cancel",
              )!,
            }
            : {}),
        },
        body: stripeCancelAtPeriodEndParams.toString(),
      },
    );

    if (!stripePendingCancelResponse.ok) {
      const errorText = await stripePendingCancelResponse.text();
      if (isStripeResourceMissingError(errorText)) {
        console.warn(
          "Stripe subscription missing while setting pending cancellation; treating as already absent",
          {
            ...logContext,
            subscriptionId: context.targetPlanStripeSubscriptionId,
            targetPlanStatus: context.targetPlanStatus,
            error: errorText || null,
          },
        );
        return;
      }
      throw new Error(
        `Failed to stop Stripe auto-renewal for linked plan: ${
          errorText || stripePendingCancelResponse.status
        }`,
      );
    }

    const stripePendingCancelResult = await stripePendingCancelResponse
      .json() as {
        id?: string;
        cancel_at_period_end?: boolean;
        status?: string;
        cancel_at?: number | null;
        canceled_at?: number | null;
      };
    logCancellationDecision(
      "Stripe subscription update completed for pending cancellation",
      {
        ...logContext,
        subscriptionId: stripePendingCancelResult.id ||
          context.targetPlanStripeSubscriptionId,
        status: stripePendingCancelResult.status || null,
        cancelAtPeriodEnd: stripePendingCancelResult.cancel_at_period_end ??
          null,
        cancelAt: stripePendingCancelResult.cancel_at ?? null,
        canceledAt: stripePendingCancelResult.canceled_at ?? null,
      },
    );
  } else {
    logCancellationDecision("Calling Stripe subscription cancellation", {
      ...logContext,
      subscriptionId: context.targetPlanStripeSubscriptionId,
      targetPlanStatus: context.targetPlanStatus,
    });

    const stripePlanCancelResponse = await fetch(
      `https://api.stripe.com/v1/subscriptions/${context.targetPlanStripeSubscriptionId}`,
      {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${context.stripeSecretKey}`,
        },
      },
    );

    if (!stripePlanCancelResponse.ok) {
      const errorText = await stripePlanCancelResponse.text();
      if (isStripeResourceMissingError(errorText)) {
        console.warn(
          "Stripe subscription missing during cancellation; treating as already cancelled",
          {
            ...logContext,
            subscriptionId: context.targetPlanStripeSubscriptionId,
            targetPlanStatus: context.targetPlanStatus,
            error: errorText || null,
          },
        );
        return;
      }
      throw new Error(
        `Failed to cancel linked Stripe plan: ${
          errorText || stripePlanCancelResponse.status
        }`,
      );
    }

    const stripePlanCancelResult = await stripePlanCancelResponse.json() as {
      id?: string;
      status?: string;
      canceled_at?: number | null;
    };
    logCancellationDecision("Stripe subscription cancellation completed", {
      ...logContext,
      subscriptionId: stripePlanCancelResult.id ||
        context.targetPlanStripeSubscriptionId,
      status: stripePlanCancelResult.status || null,
      canceledAt: stripePlanCancelResult.canceled_at ?? null,
    });
  }
}

async function cancelStripePaymentIntentIfNeeded(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  context: CancellationContext;
  logContext?: CancellationLogContext;
}): Promise<boolean> {
  const { supabase, order, context, logContext } = params;

  if (!context.needsPaymentIntentCancel || !context.paymentIntentId) {
    logCancellationDecision("Skipping Stripe payment intent cancellation", {
      ...logContext,
      paymentIntentId: context.paymentIntentId,
      needsPaymentIntentCancel: context.needsPaymentIntentCancel,
    });
    return false;
  }

  if (!context.stripeSecretKey) {
    throw new Error("Stripe secret key not configured");
  }

  logCancellationDecision("Calling Stripe payment intent cancellation", {
    ...logContext,
    paymentIntentId: context.paymentIntentId,
    currentPaymentIntentStatus: context.paymentIntentStatus,
  });

  const response = await fetch(
    `https://api.stripe.com/v1/payment_intents/${context.paymentIntentId}/cancel`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${context.stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
        ...(buildStripeCancellationIdempotencyKey(
            context.cancellationOperationKey,
            "payment_intent_cancel",
          )
          ? {
            "Idempotency-Key": buildStripeCancellationIdempotencyKey(
              context.cancellationOperationKey,
              "payment_intent_cancel",
            )!,
          }
          : {}),
      },
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(
      `Failed to cancel Stripe payment intent: ${errorText || response.status}`,
    );
  }

  const paymentIntent = (await response.json()) as { status?: string };
  const cancelledStatus = normalizePaymentIntentStatus(paymentIntent.status) ||
    "cancelled";

  logCancellationDecision("Stripe payment intent cancellation completed", {
    ...logContext,
    paymentIntentId: context.paymentIntentId,
    cancelledStatus,
  });

  const { error: updateTransactionError } = await supabase
    .from("order_payment_provider_transactions")
    .update({
      payment_status: cancelledStatus,
    })
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("provider_payment_intent_id", context.paymentIntentId);

  if (updateTransactionError) {
    throw new Error(
      `Failed to persist cancelled payment intent state: ${updateTransactionError.message}`,
    );
  }

  return true;
}

async function captureProviderFeeOnStripePaymentIntentIfNeeded(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  context: CancellationContext;
  logContext?: CancellationLogContext;
}): Promise<{ performed: boolean; retainedProviderFeeOnly: boolean }> {
  const { supabase, order, context, logContext } = params;

  if (
    (!context.needsProviderFeeCapture &&
      !context.shouldCaptureFullAmountBeforeRefund) ||
    !context.paymentIntentId
  ) {
    logCancellationDecision("Skipping Stripe payment intent capture", {
      ...logContext,
      paymentIntentId: context.paymentIntentId,
      refundTier: context.refundTier,
      providerFeeCents: context.providerFeeCents,
      refundAmountCents: context.refundAmountCents,
      paymentIntentStatus: context.paymentIntentStatus,
      shouldCaptureFullAmountBeforeRefund:
        context.shouldCaptureFullAmountBeforeRefund,
      needsProviderFeeCapture: context.needsProviderFeeCapture,
    });
    return { performed: false, retainedProviderFeeOnly: false };
  }

  if (!context.stripeSecretKey) {
    throw new Error("Stripe secret key not configured");
  }

  const captureParams = new URLSearchParams();
  if (!context.shouldCaptureFullAmountBeforeRefund) {
    captureParams.append("amount_to_capture", `${context.providerFeeCents}`);
  }

  logCancellationDecision(
    context.shouldCaptureFullAmountBeforeRefund
      ? "Calling Stripe payment intent capture for full amount before refund"
      : "Calling Stripe payment intent capture for provider fee",
    {
      ...logContext,
      paymentIntentId: context.paymentIntentId,
      providerFeeCents: context.providerFeeCents,
      refundAmountCents: context.refundAmountCents,
      retainedAmountCents: context.retainedAmountCents,
      paymentIntentStatus: context.paymentIntentStatus,
      captureMode: context.shouldCaptureFullAmountBeforeRefund
        ? "full_amount"
        : "provider_fee_only",
    },
  );

  const response = await fetch(
    `https://api.stripe.com/v1/payment_intents/${context.paymentIntentId}/capture`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${context.stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
        ...(buildStripeCancellationIdempotencyKey(
            context.cancellationOperationKey,
            context.shouldCaptureFullAmountBeforeRefund
              ? "payment_intent_capture_full"
              : "payment_intent_capture_fee",
          )
          ? {
            "Idempotency-Key": buildStripeCancellationIdempotencyKey(
              context.cancellationOperationKey,
              context.shouldCaptureFullAmountBeforeRefund
                ? "payment_intent_capture_full"
                : "payment_intent_capture_fee",
            )!,
          }
          : {}),
      },
      body: captureParams.toString(),
    },
  );

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(
      `Failed to capture Stripe payment intent for cancellation: ${
        errorText || response.status
      }`,
    );
  }

  const paymentIntent = (await response.json()) as {
    status?: string;
    latest_charge?: string | { id?: string } | null;
  };
  const capturedStatus = normalizePaymentIntentStatus(paymentIntent.status) ||
    "succeeded";
  const capturedAt = dateTime().toISOString();
  const latestChargeId = typeof paymentIntent.latest_charge === "string"
    ? paymentIntent.latest_charge
    : paymentIntent.latest_charge?.id || null;

  logCancellationDecision(
    context.shouldCaptureFullAmountBeforeRefund
      ? "Stripe payment intent capture completed for full amount before refund"
      : "Stripe payment intent capture completed for provider fee",
    {
      ...logContext,
      paymentIntentId: context.paymentIntentId,
      capturedStatus,
      latestChargeId,
      providerFeeCents: context.providerFeeCents,
      refundAmountCents: context.refundAmountCents,
      captureMode: context.shouldCaptureFullAmountBeforeRefund
        ? "full_amount"
        : "provider_fee_only",
    },
  );

  const transactionUpdatePayload: Record<string, string> = {
    payment_status: capturedStatus,
    paid_at: capturedAt,
  };
  if (latestChargeId) {
    transactionUpdatePayload.provider_charge_id = latestChargeId;
  }

  const { error: updateTransactionError } = await supabase
    .from("order_payment_provider_transactions")
    .update(transactionUpdatePayload)
    .eq("order_id", order.id)
    .eq("tenant_id", order.tenant_id)
    .eq("provider_payment_intent_id", context.paymentIntentId);

  if (updateTransactionError) {
    throw new Error(
      `Failed to persist provider fee capture state: ${updateTransactionError.message}`,
    );
  }

  const { error: updateOrderError } = await supabase
    .from("orders")
    .update({
      paid_at: capturedAt,
    })
    .eq("id", order.id)
    .eq("tenant_id", order.tenant_id);

  if (updateOrderError) {
    throw new Error(
      `Failed to persist provider fee capture paid_at on order: ${updateOrderError.message}`,
    );
  }

  return {
    performed: true,
    retainedProviderFeeOnly: !context.shouldCaptureFullAmountBeforeRefund,
  };
}

async function issueStripeRefundIfNeeded(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  context: CancellationContext;
  logContext?: CancellationLogContext;
}): Promise<{
  stripeRefundId: string | null;
  stripeRefundStatus: string | null;
}> {
  const { supabase, order, context, logContext } = params;

  if (!context.needsRefundProcessing || context.refundAmountCents <= 0) {
    logCancellationDecision("Skipping Stripe refund issuance", {
      ...logContext,
      refundTier: context.refundTier,
      refundAmountCents: context.refundAmountCents,
      needsRefundProcessing: context.needsRefundProcessing,
    });
    return { stripeRefundId: null, stripeRefundStatus: null };
  }

  if (!context.stripeSecretKey) {
    throw new Error("Stripe secret key not configured");
  }

  let paymentIntentId = context.paymentIntentId;
  let chargeId = context.chargeId;

  if (!paymentIntentId && !chargeId) {
    const { data: paymentTransactions, error: paymentTransactionError } =
      await supabase
        .from("order_payment_provider_transactions")
        .select(
          `
          provider_payment_intent_id,
          provider_invoice_id,
          provider_charge_id,
          payment_status,
          created_at,
          payment_providers!inner (
            key
          )
        `,
        )
        .eq("order_id", order.id)
        .eq("tenant_id", order.tenant_id)
        .eq("payment_providers.key", "stripe")
        .order("created_at", { ascending: false })
        .limit(10);

    if (paymentTransactionError) {
      throw new Error(
        `Failed to fetch order payment transaction: ${paymentTransactionError.message}`,
      );
    }

    const resolvedReference =
      await resolveStripePaymentReferenceFromTransactions({
        paymentTransactions: (paymentTransactions ||
          []) as PaymentTransactionRow[],
        stripeSecretKey: context.stripeSecretKey,
        logContext,
      });

    paymentIntentId = paymentIntentId || resolvedReference.paymentIntentId;
    chargeId = chargeId || resolvedReference.chargeId;
  }

  if (!paymentIntentId && !chargeId) {
    throw new Error("Unable to locate Stripe payment reference for refund");
  }

  const stripeRefundParams = new URLSearchParams();
  stripeRefundParams.append("amount", `${context.refundAmountCents}`);
  stripeRefundParams.append("reason", "requested_by_customer");
  if (paymentIntentId) {
    stripeRefundParams.append("payment_intent", paymentIntentId);
  } else if (chargeId) {
    stripeRefundParams.append("charge", chargeId);
  }
  stripeRefundParams.append("metadata[allia_order_id]", order.id);
  stripeRefundParams.append("metadata[allia_order_number]", order.order_number);
  stripeRefundParams.append("metadata[allia_refund_tier]", context.refundTier);
  if (order.cancellation_reason) {
    stripeRefundParams.append(
      "metadata[allia_cancellation_reason]",
      order.cancellation_reason,
    );
  }

  logCancellationDecision("Calling Stripe refund", {
    ...logContext,
    paymentIntentId,
    chargeId,
    refundTier: context.refundTier,
    refundAmountCents: context.refundAmountCents,
    providerFeeCents: context.providerFeeCents,
    retainedAmountCents: context.retainedAmountCents,
  });

  const stripeRefundResponse = await fetch(
    "https://api.stripe.com/v1/refunds",
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${context.stripeSecretKey}`,
        "Content-Type": "application/x-www-form-urlencoded",
        ...(buildStripeCancellationIdempotencyKey(
            context.cancellationOperationKey,
            "refund",
          )
          ? {
            "Idempotency-Key": buildStripeCancellationIdempotencyKey(
              context.cancellationOperationKey,
              "refund",
            )!,
          }
          : {}),
      },
      body: stripeRefundParams.toString(),
    },
  );

  if (!stripeRefundResponse.ok) {
    const errorText = await stripeRefundResponse.text();
    throw new Error(
      `Failed to issue Stripe refund for this order: ${
        errorText || stripeRefundResponse.status
      }`,
    );
  }

  const stripeRefund = (await stripeRefundResponse.json()) as {
    id?: string;
    status?: string;
    amount?: number;
    payment_intent?: string | null;
    charge?: string | null;
  };

  logCancellationDecision("Stripe refund completed", {
    ...logContext,
    stripeRefundId: stripeRefund.id || null,
    stripeRefundStatus: stripeRefund.status || null,
    stripeRefundAmountCents: stripeRefund.amount ?? null,
    paymentIntentId: typeof stripeRefund.payment_intent === "string"
      ? stripeRefund.payment_intent
      : paymentIntentId,
    chargeId: typeof stripeRefund.charge === "string"
      ? stripeRefund.charge
      : chargeId,
    expectedRefundAmountCents: context.refundAmountCents,
  });

  return {
    stripeRefundId: stripeRefund.id || null,
    stripeRefundStatus: stripeRefund.status || null,
  };
}

export async function analyzePendingOrderCancellation(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  requestId?: string;
}): Promise<PendingCancellationAnalysisResult> {
  const context = await buildCancellationContext(params);

  logCancellationDecision("Analyzed order_pending_cancellation requirements", {
    requestId: params.requestId,
    orderId: params.order.id,
    tenantId: params.order.tenant_id,
    refundTier: context.refundTier,
    refundAmountCents: context.refundAmountCents,
    providerFeeCents: context.providerFeeCents,
    shouldCaptureFullAmountBeforeRefund:
      context.shouldCaptureFullAmountBeforeRefund,
    needsProviderFeeCapture: context.needsProviderFeeCapture,
    needsRefundProcessing: context.needsRefundProcessing,
    needsPaymentIntentCancel: context.needsPaymentIntentCancel,
    needsStripePlanUpdate: context.needsStripePlanUpdate,
  });

  return {
    analyzedAt: context.nowIso,
    previousStatusKey: context.previousStatusKey,
    previousStatusLabel: context.previousStatusLabel,
    refundTier: context.refundTier,
    refundAmountCents: context.refundAmountCents,
    retainedAmountCents: context.retainedAmountCents,
    providerFeeCents: context.providerFeeCents,
    refundEligibilityHistoryNote: context.refundEligibilityHistoryNote,
    paymentIntentStatus: context.paymentIntentStatus,
    needsRefundProcessing: context.needsRefundProcessing,
    needsPaymentIntentCancel: context.needsPaymentIntentCancel,
    needsStripePlanUpdate: context.needsStripePlanUpdate,
    shouldMoveToProcessing: context.needsRefundProcessing ||
      context.needsProviderFeeCapture ||
      context.needsPaymentIntentCancel ||
      context.needsStripePlanUpdate ||
      shouldCancelMdiCaseForLifecycle({
        shouldCancelProviderOrder: context.shouldCancelProviderOrder,
        previousStatusKey: context.previousStatusKey,
        providerPlatformIntegrationKey:
          params.order.provider_platform_integration_key,
      }),
  };
}

export async function finalizeDirectOrderCancellation(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  requestId?: string;
}): Promise<DirectCancellationResult> {
  const { supabase, order } = params;
  const context = await buildCancellationContext({
    supabase,
    order,
    requestId: params.requestId,
  });

  if (
    context.needsRefundProcessing ||
    context.needsProviderFeeCapture ||
    context.needsPaymentIntentCancel ||
    context.needsStripePlanUpdate
  ) {
    throw new Error(
      "Direct cancellation is not allowed while refund or Stripe processing is still required",
    );
  }

  await applyPlanCancellationState({ supabase, order, context });

  return {
    cancelledAt: context.nowIso,
    completionHistoryNote: buildDirectCancellationHistoryNote({
      previousStatusLabel: context.previousStatusLabel,
      previousStatusKey: context.previousStatusKey,
      planStatus: context.targetPlanStatus,
    }),
  };
}

export async function processOrderCancellationProcessing(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  requestId: string;
}): Promise<CancellationProcessingResult> {
  const { supabase, order, requestId } = params;
  const context = await buildCancellationContext({
    supabase,
    order,
    requestId,
  });
  const logContext: CancellationLogContext = {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
  };

  logCancellationDecision("Starting order_cancellation_processing actions", {
    ...logContext,
    previousStatusKey: context.previousStatusKey,
    refundTier: context.refundTier,
    refundAmountCents: context.refundAmountCents,
    providerFeeCents: context.providerFeeCents,
    paymentIntentId: context.paymentIntentId,
    chargeId: context.chargeId,
    paymentIntentStatus: context.paymentIntentStatus,
    shouldCaptureFullAmountBeforeRefund:
      context.shouldCaptureFullAmountBeforeRefund,
    needsProviderFeeCapture: context.needsProviderFeeCapture,
    needsRefundProcessing: context.needsRefundProcessing,
    needsPaymentIntentCancel: context.needsPaymentIntentCancel,
    needsStripePlanUpdate: context.needsStripePlanUpdate,
    shouldCancelProviderOrder: context.shouldCancelProviderOrder,
  });

  let paymentIntentCaptured = false;
  let providerFeeCaptured = false;
  let telegraCancelled = false;
  let mdiCancelled = false;
  if (
    shouldCancelTelegraOrderForLifecycle({
      shouldCancelProviderOrder: context.shouldCancelProviderOrder,
      previousStatusKey: context.previousStatusKey,
      providerPlatformIntegrationKey: order.provider_platform_integration_key,
    })
  ) {
    const telegraCancelResult = await cancelTelegraOrderForLifecycle({
      supabase,
      order: {
        id: order.id,
        order_number: order.order_number,
        tenant_id: order.tenant_id,
        patient_id: order.patient_id,
        status_id: order.status_id,
        product_id: null,
        shipping_first_name: null,
        shipping_last_name: null,
        shipping_address_line1: null,
        shipping_address_line2: null,
        shipping_city: null,
        shipping_state: null,
        shipping_postal_code: null,
        shipping_country: null,
        billing_first_name: null,
        billing_last_name: null,
        billing_address_line1: null,
        billing_address_line2: null,
        billing_city: null,
        billing_state: null,
        billing_postal_code: null,
        billing_country: null,
        provider_platform_integration_key:
          order.provider_platform_integration_key || null,
      },
      requestId,
    });

    if (telegraCancelResult.applicable) {
      await insertOrderStatusHistoryNote({
        supabase,
        orderId: order.id,
        statusId: order.status_id,
        notes: buildProviderCancellationHistoryNote({
          providerName: telegraCancelResult.providerName,
          externalOrderId: telegraCancelResult.externalOrderId,
          cancelled: telegraCancelResult.cancelled,
          message: telegraCancelResult.message,
        }),
      });
    }

    if (telegraCancelResult.applicable && !telegraCancelResult.cancelled) {
      throw new Error(telegraCancelResult.message);
    }

    telegraCancelled = telegraCancelResult.cancelled;
  } else if (
    shouldCancelMdiCaseForLifecycle({
      shouldCancelProviderOrder: context.shouldCancelProviderOrder,
      previousStatusKey: context.previousStatusKey,
      providerPlatformIntegrationKey: order.provider_platform_integration_key,
    })
  ) {
    const mdiCancelResult = await cancelMdiCaseForLifecycle({
      supabase,
      order: {
        id: order.id,
        order_number: order.order_number,
        tenant_id: order.tenant_id,
        patient_id: order.patient_id,
        status_id: order.status_id,
        product_id: null,
        shipping_first_name: null,
        shipping_last_name: null,
        shipping_address_line1: null,
        shipping_address_line2: null,
        shipping_city: null,
        shipping_state: null,
        shipping_postal_code: null,
        shipping_country: null,
        provider_platform_integration_key:
          order.provider_platform_integration_key || null,
      },
      requestId,
    });

    if (mdiCancelResult.applicable) {
      await insertOrderStatusHistoryNote({
        supabase,
        orderId: order.id,
        statusId: order.status_id,
        notes: buildProviderCancellationHistoryNote({
          providerName: mdiCancelResult.providerName,
          externalOrderId: mdiCancelResult.externalOrderId,
          cancelled: mdiCancelResult.cancelled,
          message: mdiCancelResult.message,
        }),
      });
    }

    if (mdiCancelResult.applicable && !mdiCancelResult.cancelled) {
      throw new Error(mdiCancelResult.message);
    }

    mdiCancelled = mdiCancelResult.cancelled;
  } else if (context.shouldCancelProviderOrder) {
    logCancellationDecision(
      "Skipping provider cancellation for unsupported order",
      {
        ...logContext,
        providerPlatformIntegrationKey:
          order.provider_platform_integration_key || null,
        previousStatusKey: context.previousStatusKey,
      },
    );
  }

  const captureResult = await captureProviderFeeOnStripePaymentIntentIfNeeded({
    supabase,
    order,
    context,
    logContext,
  });
  paymentIntentCaptured = captureResult.performed;
  providerFeeCaptured = captureResult.retainedProviderFeeOnly;
  const refundResult = await issueStripeRefundIfNeeded({
    supabase,
    order,
    context,
    logContext,
  });
  const paymentIntentCancelled = paymentIntentCaptured
    ? false
    : await cancelStripePaymentIntentIfNeeded({
      supabase,
      order,
      context,
      logContext,
    });
  await applyStripePlanCancellationIfNeeded({ context, logContext });
  await applyPlanCancellationState({ supabase, order, context });

  const completionHistoryNote = buildProcessingCompletionHistoryNote({
    previousStatusLabel: context.previousStatusLabel,
    previousStatusKey: context.previousStatusKey,
    refundTier: context.refundTier,
    refundAmountCents: context.refundAmountCents,
    retainedAmountCents: context.retainedAmountCents,
    providerFeeCents: context.providerFeeCents,
    stripeRefundId: refundResult.stripeRefundId,
    paymentIntentId: context.paymentIntentId,
    chargeId: context.chargeId,
    providerFeeCaptured,
    paymentIntentCancelled,
    telegraCancelled,
    mdiCancelled,
    planStatus: context.targetPlanStatus,
  });

  console.info(
    "Processed order cancellation in order_cancellation_processing",
    {
      ...logContext,
      refundTier: context.refundTier,
      refundAmountCents: context.refundAmountCents,
      providerFeeCents: context.providerFeeCents,
      paymentIntentCaptured,
      providerFeeCaptured,
      stripeRefundId: refundResult.stripeRefundId,
      stripeRefundStatus: refundResult.stripeRefundStatus,
      needsPaymentIntentCancel: context.needsPaymentIntentCancel,
      needsStripePlanUpdate: context.needsStripePlanUpdate,
      previousStatusKey: context.previousStatusKey,
      targetPlanStatus: context.targetPlanStatus,
      telegraCancelled,
      mdiCancelled,
      paymentIntentCancelled,
    },
  );

  return {
    completed: true,
    message: "Order cancellation processing completed successfully",
    cancelledAt: context.nowIso,
    refundTier: context.refundTier,
    stripeRefundId: refundResult.stripeRefundId,
    stripeRefundStatus: refundResult.stripeRefundStatus,
    telegraCancelled,
    mdiCancelled,
    completionHistoryNote,
  };
}

export async function cancelLinkedPlanForProviderRejectedOrder(params: {
  supabase: SupabaseClient;
  order: OrderForPendingCancellation;
  requestId?: string;
}): Promise<ProviderRejectedPlanCancellationResult> {
  const { supabase, order, requestId } = params;

  if (
    typeof order.subscription_id !== "string" ||
    order.subscription_id.trim().length === 0
  ) {
    return {
      updated: false,
      planId: null,
      cancelledAt: null,
      message: "Provider-rejected order has no linked plan",
    };
  }

  const { data: plan, error: planError } = await supabase
    .from("subscriptions")
    .select("id, status, cancelled_at")
    .eq("id", order.subscription_id)
    .eq("tenant_id", order.tenant_id)
    .eq("patient_id", order.patient_id)
    .maybeSingle();

  if (planError) {
    throw new Error(
      `Failed to fetch linked plan for provider rejection: ${planError.message}`,
    );
  }

  if (!plan) {
    throw new Error("Linked plan not found for provider-rejected order");
  }

  const typedPlan = plan as {
    id: string;
    status: string | null;
    cancelled_at: string | null;
  };

  if (typedPlan.status === "cancelled" && typedPlan.cancelled_at) {
    logCancellationDecision(
      "Linked plan already cancelled for provider-rejected order",
      {
        requestId,
        orderId: order.id,
        tenantId: order.tenant_id,
        targetPlanId: typedPlan.id,
        cancelledAt: typedPlan.cancelled_at,
      },
    );

    return {
      updated: false,
      planId: typedPlan.id,
      cancelledAt: typedPlan.cancelled_at,
      message: "Linked plan is already cancelled",
    };
  }

  const cancelledAt = typedPlan.cancelled_at || dateTime().toISOString();
  const { error: updateError } = await supabase
    .from("subscriptions")
    .update({
      status: "cancelled",
      cancelled_at: cancelledAt,
      cancellation_reason: order.cancellation_reason,
    })
    .eq("id", typedPlan.id)
    .eq("tenant_id", order.tenant_id)
    .eq("patient_id", order.patient_id);

  if (updateError) {
    throw new Error(
      `Failed to cancel linked plan for provider rejection: ${updateError.message}`,
    );
  }

  logCancellationDecision("Cancelled linked plan for provider-rejected order", {
    requestId,
    orderId: order.id,
    tenantId: order.tenant_id,
    targetPlanId: typedPlan.id,
    cancelledAt,
  });

  return {
    updated: true,
    planId: typedPlan.id,
    cancelledAt,
    message: "Linked plan cancelled due to provider rejection",
  };
}

export const __testOnly = {
  isPaymentIntentAlreadyFinal,
  isPaymentIntentCapturable,
  isStripeRefundProcessable,
};
