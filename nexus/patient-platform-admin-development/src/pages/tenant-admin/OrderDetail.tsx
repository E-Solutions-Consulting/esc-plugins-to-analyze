import { PageHeader } from "@/components/common/PageHeader";
import { MigrationStatus } from "@/components/common/MigrationStatus";
import { StatusBadge } from "@/components/common/StatusBadge";
import { OrderStatusBadge } from "@/components/features/OrderStatusBadge";
import { OrderStatusHistory } from "@/components/features/OrderStatusHistory";
import { OrderStatusSelect } from "@/components/features/OrderStatusSelect";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { useAuditLog } from "@/hooks/useAuditLog";
import { useTenantAllowedStates } from "@/hooks/useTenantAllowedStates";
import { supabase } from "@/integrations/supabase/client";
import { canPerformAction } from "@/lib/admin-permissions";
import { ROUTES } from "@/lib/constants";
import { dateTime } from "@/lib/dayjs";
import { normalizeUsStateCode, US_STATES } from "@/lib/usStates";
import { useAuth } from "@/stores/authStore";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  CheckCircle2,
  Copy,
  CreditCard,
  DollarSign,
  ExternalLink,
  FileText,
  Loader2,
  MapPin,
  Package,
  RefreshCw,
  Repeat,
  Save,
  Truck,
  User,
} from "lucide-react";
import { useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";

type OrderProviderPlatformLink = {
  id: string;
  provider_order_id: string | null;
  metadata: {
    provider?: string;
    integration_key?: string;
    source?: string;
    order_number?: string;
    questionnaire_instance_ids?: string[];
  } | null;
  created_at: string;
  updated_at: string;
};

const EMPTY_STATE_VALUE = "__empty_state__";
const ADDRESS_STATUS_ORDER = [
  "order_created",
  "shipping_details_required",
  "provider_order_creation_pending",
  "patient_questionnaire_pending",
  "medical_questionnaire_pending",
  "provider_review_pending",
  "provider_approved",
  "payment_pending",
  "payment_collected",
  "order_approved",
  "order_sent_to_pharmacy",
  "pharmacy_approval_pending",
  "pharmacy_approved",
  "fulfillment_in_progress",
  "final_pharmacy_verification",
  "in_transit",
  "delivered",
] as const;

type AddressStatusRule = {
  status_key: string;
  display_order: number | null;
};

const DEFAULT_ADDRESS_STATUS_THRESHOLDS = {
  providerReviewPending: {
    status_key: "provider_review_pending",
    display_order: null,
  },
  paymentPending: {
    status_key: "payment_pending",
    display_order: null,
  },
} satisfies Record<string, AddressStatusRule>;
const DEV_OR_STAGING_SUPABASE_PROJECT_REFS = new Set([
  "sunzxjnbgtknqeivljtd",
  "rhzrxfckhogjppjsioyn",
]);
const SHIPPING_ADDRESS_FIELDS = [
  "shipping_first_name",
  "shipping_last_name",
  "shipping_company",
  "shipping_address_line1",
  "shipping_address_line2",
  "shipping_city",
  "shipping_state",
  "shipping_postal_code",
  "shipping_country",
  "shipping_instructions",
] as const;
const BILLING_ADDRESS_FIELDS = [
  "billing_first_name",
  "billing_last_name",
  "billing_company",
  "billing_address_line1",
  "billing_address_line2",
  "billing_city",
  "billing_state",
  "billing_postal_code",
  "billing_country",
] as const;

function formatUsStateCode(value: string | null | undefined) {
  const normalizedCode = normalizeUsStateCode(value);
  if (normalizedCode) return normalizedCode;

  const normalizedValue = value?.trim().toUpperCase();
  return normalizedValue || null;
}

function getStatusRank(statusKey: string | null | undefined): number | null {
  if (!statusKey) return null;
  const index = ADDRESS_STATUS_ORDER.indexOf(
    statusKey as (typeof ADDRESS_STATUS_ORDER)[number],
  );
  return index >= 0 ? index : null;
}

function getStatusDisplayOrder(
  status: { display_order?: number | null } | null | undefined,
): number | null {
  return typeof status?.display_order === "number"
    ? status.display_order
    : null;
}

function isStatusBefore(
  currentStatus:
    | { status_key?: string | null; display_order?: number | null }
    | null
    | undefined,
  thresholdStatus:
    | { status_key?: string | null; display_order?: number | null }
    | null
    | undefined,
) {
  if (!currentStatus?.status_key || !thresholdStatus?.status_key) return false;
  if (currentStatus.status_key === thresholdStatus.status_key) return false;

  const currentDisplayOrder = getStatusDisplayOrder(currentStatus);
  const thresholdDisplayOrder = getStatusDisplayOrder(thresholdStatus);
  if (
    typeof currentDisplayOrder === "number" &&
    typeof thresholdDisplayOrder === "number"
  ) {
    return currentDisplayOrder < thresholdDisplayOrder;
  }

  const currentRank = getStatusRank(currentStatus.status_key);
  const thresholdRank = getStatusRank(thresholdStatus.status_key);
  return currentRank !== null && thresholdRank !== null &&
    currentRank < thresholdRank;
}

function hasChangedFields(
  updateData: Record<string, unknown>,
  beforeData: Record<string, unknown> | null | undefined,
  fields: readonly string[],
) {
  if (!beforeData) return false;

  return fields.some((field) =>
    Object.prototype.hasOwnProperty.call(updateData, field) &&
    (updateData[field] || null) !== (beforeData[field] || null)
  );
}

function getAddressChangeHistoryNote(params: {
  shippingChanged: boolean;
  billingChanged: boolean;
}) {
  const labels = [
    params.shippingChanged ? "shipping address" : null,
    params.billingChanged ? "billing address" : null,
  ].filter(Boolean);

  return `Tenant admin updated ${labels.join(" and ")}.`;
}

function getSupabaseProjectRef(): string | null {
  const configuredProjectId = import.meta.env.VITE_SUPABASE_PROJECT_ID;
  if (typeof configuredProjectId === "string" && configuredProjectId.trim()) {
    return configuredProjectId.trim();
  }

  const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
  if (typeof supabaseUrl !== "string" || !supabaseUrl.trim()) return null;

  try {
    return new URL(supabaseUrl).hostname.toLowerCase().split(".")[0] || null;
  } catch {
    return null;
  }
}

function isDevOrStagingSupabaseEnvironment(): boolean {
  const projectRef = getSupabaseProjectRef();
  return Boolean(
    projectRef && DEV_OR_STAGING_SUPABASE_PROJECT_REFS.has(projectRef),
  );
}

function isTelegraIntegrationKey(value: string | null | undefined): boolean {
  const normalizedValue = value?.trim().toLowerCase();
  return normalizedValue === "telegramd" || normalizedValue === "telegra";
}

function isTelegraProviderLink(link: OrderProviderPlatformLink): boolean {
  return isTelegraIntegrationKey(link.metadata?.integration_key) ||
    isTelegraIntegrationKey(link.metadata?.provider) ||
    Boolean(link.provider_order_id?.trim().startsWith("order::"));
}

function buildAddressInputFromShippingForm(form: {
  shipping_first_name: string;
  shipping_last_name: string;
  shipping_company: string;
  shipping_address_line1: string;
  shipping_address_line2: string;
  shipping_city: string;
  shipping_state: string;
  shipping_postal_code: string;
  shipping_country: string;
  shipping_instructions: string;
}) {
  return {
    first_name: form.shipping_first_name,
    last_name: form.shipping_last_name,
    company: form.shipping_company,
    line1: form.shipping_address_line1,
    line2: form.shipping_address_line2,
    city: form.shipping_city,
    state: form.shipping_state,
    postal_code: form.shipping_postal_code,
    country: form.shipping_country,
    instructions: form.shipping_instructions,
  };
}

function buildAddressInputFromBillingForm(form: {
  billing_first_name: string;
  billing_last_name: string;
  billing_company: string;
  billing_address_line1: string;
  billing_address_line2: string;
  billing_city: string;
  billing_state: string;
  billing_postal_code: string;
  billing_country: string;
}) {
  return {
    first_name: form.billing_first_name,
    last_name: form.billing_last_name,
    company: form.billing_company,
    line1: form.billing_address_line1,
    line2: form.billing_address_line2,
    city: form.billing_city,
    state: form.billing_state,
    postal_code: form.billing_postal_code,
    country: form.billing_country,
  };
}

function StateSelect({
  id,
  value,
  onValueChange,
  states,
}: {
  id: string;
  value: string;
  onValueChange: (value: string) => void;
  states: ReadonlyArray<(typeof US_STATES)[number]>;
}) {
  return (
    <Select
      value={value || EMPTY_STATE_VALUE}
      onValueChange={(selectedValue) =>
        onValueChange(
          selectedValue === EMPTY_STATE_VALUE ? "" : selectedValue,
        )
      }
    >
      <SelectTrigger id={id}>
        <SelectValue placeholder="Select state" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={EMPTY_STATE_VALUE}>Select state</SelectItem>
        {states.map((state) => (
          <SelectItem
            key={state.code}
            value={state.code}
            title={`${state.code} - ${state.name}`}
          >
            {state.code}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

export default function OrderDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const {
    currentTenantId,
    user,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const {
    availableStates: availableShippingStates,
    isLoading: isLoadingAvailableShippingStates,
    error: availableShippingStatesError,
  } = useTenantAllowedStates();
  const [isEditingTracking, setIsEditingTracking] = useState(false);
  const [isEditingNotes, setIsEditingNotes] = useState(false);
  const [isEditingAddress, setIsEditingAddress] = useState(false);
  const [isEditingBillingAddress, setIsEditingBillingAddress] =
    useState(false);
  const [trackingForm, setTrackingForm] = useState({
    tracking_number: "",
    tracking_url: "",
  });
  const [notesForm, setNotesForm] = useState("");
  const [addressForm, setAddressForm] = useState({
    shipping_first_name: "",
    shipping_last_name: "",
    shipping_company: "",
    shipping_address_line1: "",
    shipping_address_line2: "",
    shipping_city: "",
    shipping_state: "",
    shipping_postal_code: "",
    shipping_country: "",
    shipping_instructions: "",
  });
  const [billingAddressForm, setBillingAddressForm] = useState({
    billing_first_name: "",
    billing_last_name: "",
    billing_company: "",
    billing_address_line1: "",
    billing_address_line2: "",
    billing_city: "",
    billing_state: "",
    billing_postal_code: "",
    billing_country: "",
  });
  const [isProcessingOrder, setIsProcessingOrder] = useState(false);
  const [isApprovingTelegraOrder, setIsApprovingTelegraOrder] =
    useState(false);

  const handleProcessOrder = async () => {
    if (!id) return;
    setIsProcessingOrder(true);
    try {
      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/order-lifecycle?orderId=${id}`,
        {
          method: "GET",
          headers: {
            apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
            "Content-Type": "application/json",
          },
        },
      );
      const result = await response.json();
      if (response.ok) {
        toast.success(result.message || "Order processed successfully");
        queryClient.invalidateQueries({ queryKey: ["order", id] });
        queryClient.invalidateQueries({
          queryKey: ["order-status-history", id],
        });
        queryClient.invalidateQueries({
          queryKey: ["order-provider-transactions", id, currentTenantId],
        });
        queryClient.invalidateQueries({
          queryKey: ["order-provider-platform-links", id, currentTenantId],
        });
      } else {
        toast.error(result.error || "Failed to process order");
      }
    } catch (error) {
      toast.error("Failed to trigger order processing");
    } finally {
      setIsProcessingOrder(false);
    }
  };

  const handleApproveTelegraOrder = async () => {
    if (!id) return;

    setIsApprovingTelegraOrder(true);
    try {
      const {
        data: { session },
      } = await supabase.auth.getSession();

      if (!session) {
        throw new Error("Not authenticated");
      }

      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/qa-api/admin/approve_order_prescription/${id}`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${session.access_token}`,
            apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
            "Content-Type": "application/json",
          },
        },
      );

      const result = await response.json().catch(() => null);
      if (!response.ok) {
        throw new Error(
          result?.error ||
            result?.message ||
            "Failed to approve Telegra order prescription",
        );
      }

      toast.success(result?.message || "Telegra order prescription approved");
      queryClient.invalidateQueries({ queryKey: ["order", id] });
      queryClient.invalidateQueries({
        queryKey: ["order-status-history", id],
      });
      queryClient.invalidateQueries({
        queryKey: ["order-provider-platform-links", id, currentTenantId],
      });
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to approve Telegra order prescription",
      );
    } finally {
      setIsApprovingTelegraOrder(false);
    }
  };

  const [
    { data: order, isLoading },
    {
      data: providerTransactions = [],
      isLoading: isLoadingProviderTransactions,
    },
    {
      data: providerPlatformLinks = [],
      isLoading: isLoadingProviderPlatformLinks,
    },
    { data: addressStatusThresholds = DEFAULT_ADDRESS_STATUS_THRESHOLDS },
  ] = useQueries({
    queries: [
      {
        // Fetch order details with patient info
        queryKey: ["order", id],
        queryFn: async () => {
          if (!id) throw new Error("Order ID is required");

          const { data, error } = await supabase
            .from("orders")
            .select(
              `
              *,
              subscription:subscriptions (
                id,
                status,
                current_period_end_at
              ),
              order_statuses (
                id,
                status_key,
                admin_status_label,
                patient_status_label,
                is_terminal,
                display_order,
                next_step_owner
              ),
              product:products (
                id,
                name
              ),
              patients (
                id,
                first_name,
                last_name,
                email,
                phone,
                shipping_first_name,
                shipping_last_name,
                shipping_company,
                shipping_address_line1,
                shipping_address_line2,
                shipping_city,
                shipping_state,
                shipping_postal_code,
                shipping_country,
                shipping_instructions,
                billing_first_name,
                billing_last_name,
                billing_company,
                billing_address_line1,
                billing_address_line2,
                billing_city,
                billing_state,
                billing_postal_code,
                billing_country
              )
            `,
            )
            .eq("id", id)
            .single();

          if (error) throw error;
          return data as unknown as OrderWithPatient;
        },
        enabled: !!id,
      },
      {
        queryKey: ["order-provider-transactions", id, currentTenantId],
        queryFn: async (): Promise<OrderPaymentProviderTransaction[]> => {
          if (!id || !currentTenantId) return [];

          const { data, error } = await supabase
            .from("order_payment_provider_transactions")
            .select(
              `
              id,
              payment_status,
              paid_at,
              provider_payment_intent_id,
              provider_invoice_id,
              provider_charge_id,
              provider_subscription_id,
              provider_checkout_session_id,
              created_at,
              provider:payment_providers (
                name,
                key
              )
            `,
            )
            .eq("order_id", id)
            .eq("tenant_id", currentTenantId)
            .order("created_at", { ascending: false });

          // Keep order details page available if provider-agnostic tables are not deployed yet.
          if (error?.code === "42P01") return [];
          if (error) throw error;

          return (data || []) as unknown as OrderPaymentProviderTransaction[];
        },
        enabled: !!id && !!currentTenantId,
      },
      {
        queryKey: ["order-provider-platform-links", id, currentTenantId],
        queryFn: async (): Promise<OrderProviderPlatformLink[]> => {
          if (!id || !currentTenantId) return [];

          // Generated Supabase types have not been refreshed for this new table yet.
          const { data, error } = await supabase
            .from("order_provider_platform_links")
            .select(
              `
              id,
              provider_order_id,
              metadata,
              created_at,
              updated_at
            `,
            )
            .eq("order_id", id)
            .eq("tenant_id", currentTenantId)
            .order("created_at", { ascending: false });

          if (error?.code === "42P01") return [];
          if (error) throw error;

          return (data || []) as OrderProviderPlatformLink[];
        },
        enabled: !!id && !!currentTenantId,
      },
      {
        queryKey: ["order-address-status-thresholds"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("order_statuses")
            .select("status_key, display_order")
            .in("status_key", ["provider_review_pending", "payment_pending"])
            .eq("is_active", true);

          if (error) throw error;

          const statuses = new Map(
            (data || []).map((status) => [status.status_key, status]),
          );

          return {
            providerReviewPending:
              statuses.get("provider_review_pending") ??
                DEFAULT_ADDRESS_STATUS_THRESHOLDS.providerReviewPending,
            paymentPending:
              statuses.get("payment_pending") ??
                DEFAULT_ADDRESS_STATUS_THRESHOLDS.paymentPending,
          };
        },
      },
    ],
  });

  const currentOrderStatus = order?.order_statuses as
    | { status_key?: string | null; display_order?: number | null }
    | null
    | undefined;
  const permissionContext = {
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    currentTenantId,
  };
  const canEditTracking = canPerformAction(
    permissionContext,
    "order:tracking_edit",
  );
  const isShippingAddressStatusLocked = Boolean(
    currentOrderStatus?.status_key &&
      !isStatusBefore(
        currentOrderStatus,
        addressStatusThresholds.providerReviewPending,
      ),
  );
  const canEditShippingAddress = canPerformAction(
    permissionContext,
    "order:shipping_address_edit",
  ) && isStatusBefore(
    currentOrderStatus,
    addressStatusThresholds.providerReviewPending,
  );
  const canEditBillingAddress = canPerformAction(
    permissionContext,
    "order:billing_address_edit",
  ) && isStatusBefore(
    currentOrderStatus,
    addressStatusThresholds.paymentPending,
  );
  const canEditInternalNotes = canPerformAction(
    permissionContext,
    "order:internal_notes_edit",
  );
  const isTelegraOrder = isTelegraIntegrationKey(
    order?.provider_platform_integration_key,
  ) || providerPlatformLinks.some(isTelegraProviderLink);
  const canApproveTelegraOrder = isDevOrStagingSupabaseEnvironment() &&
    currentOrderStatus?.status_key === "provider_review_pending" &&
    isTelegraOrder;

  const updateMutation = useMutation({
    mutationFn: async (data: OrderUpdateData) => {
      if (!id) throw new Error("Order ID is required");

      const beforeData = order;

      // Status updates are now handled by status_id - timestamp updates
      // should be triggered by status key via the order status system
      const updateData: Record<string, unknown> = { ...data };

      const { data: updated, error } = await supabase
        .from("orders")
        .update(updateData)
        .eq("id", id)
        .select(
          `
          *,
          subscription:subscriptions (
            id,
            status,
            current_period_end_at
          ),
          patients (
            id,
            first_name,
            last_name,
            email,
            phone,
            shipping_first_name,
            shipping_last_name,
            shipping_company,
            shipping_address_line1,
            shipping_address_line2,
            shipping_city,
            shipping_state,
            shipping_postal_code,
            shipping_country,
            shipping_instructions,
            billing_first_name,
            billing_last_name,
            billing_company,
            billing_address_line1,
            billing_address_line2,
            billing_city,
            billing_state,
            billing_postal_code,
            billing_country
          )
        `,
        )
        .single();

      if (error) throw error;
      const shippingChanged = hasChangedFields(
        updateData,
        beforeData as unknown as Record<string, unknown> | null | undefined,
        SHIPPING_ADDRESS_FIELDS,
      );
      const billingChanged = hasChangedFields(
        updateData,
        beforeData as unknown as Record<string, unknown> | null | undefined,
        BILLING_ADDRESS_FIELDS,
      );

      if (shippingChanged || billingChanged) {
        const { error: historyError } = await supabase
          .from("order_status_history")
          .insert({
            order_id: id,
            status_id: updated.status_id,
            changed_by: user?.id ?? null,
            changed_by_email: user?.email ?? null,
            notes: getAddressChangeHistoryNote({
              shippingChanged,
              billingChanged,
            }),
          });

        if (historyError) throw historyError;
      }

      return { order: updated as unknown as OrderWithPatient, beforeData };
    },
    onSuccess: ({ order: updated, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["order", id] });
      queryClient.invalidateQueries({ queryKey: ["orders"] });
      queryClient.invalidateQueries({ queryKey: ["order-status-history", id] });
      logAction({
        action: "update",
        entityType: "order",
        entityId: id!,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: updated as unknown as Record<string, unknown>,
        tenantId: currentTenantId,
      });
      toast.success("Order updated successfully");
      setIsEditingTracking(false);
      setIsEditingNotes(false);
      setIsEditingAddress(false);
      setIsEditingBillingAddress(false);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update order",
      );
    },
  });

  const updateAddressViaPlanApiMutation = useMutation({
    mutationFn: async (
      payload: {
        shipping_address?: {
          first_name: string;
          last_name: string;
          company: string;
          line1: string;
          line2: string;
          city: string;
          state: string;
          postal_code: string;
          country: string;
          instructions?: string;
        };
        billing_address?: {
          first_name: string;
          last_name: string;
          company: string;
          line1: string;
          line2: string;
          city: string;
          state: string;
          postal_code: string;
          country: string;
        };
      },
    ) => {
      if (!id) throw new Error("Order ID is required");

      const {
        data: { session },
      } = await supabase.auth.getSession();

      if (!session) {
        throw new Error("Not authenticated");
      }

      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/admin/orders/${id}/address`,
        {
          method: "PATCH",
          headers: {
            Authorization: `Bearer ${session.access_token}`,
            apikey: import.meta.env.VITE_SUPABASE_ANON_KEY,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        },
      );

      const responseBody = await response.json().catch(() => null);
      if (!response.ok) {
        const errorMessage =
          responseBody?.error?.message ||
          responseBody?.message ||
          "Failed to update order address";
        throw new Error(errorMessage);
      }

      return responseBody;
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["order", id] }),
        queryClient.invalidateQueries({ queryKey: ["orders"] }),
        queryClient.invalidateQueries({
          queryKey: ["order-status-history", id],
        }),
        queryClient.invalidateQueries({
          queryKey: ["order-provider-transactions", id, currentTenantId],
        }),
        queryClient.invalidateQueries({
          queryKey: ["order-provider-platform-links", id, currentTenantId],
        }),
      ]);
      setIsEditingAddress(false);
      setIsEditingBillingAddress(false);
      toast.success("Order address updated successfully");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update order address",
      );
    },
  });

  const updateNotesViaPlanApiMutation = useMutation({
    mutationFn: async (internalNotes: string | null) => {
      if (!id) throw new Error("Order ID is required");

      const {
        data: { session },
      } = await supabase.auth.getSession();

      if (!session) {
        throw new Error("Not authenticated");
      }

      const response = await fetch(
        `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/plan-api/admin/orders/${id}/notes`,
        {
          method: "PATCH",
          headers: {
            Authorization: `Bearer ${session.access_token}`,
            apikey: import.meta.env.VITE_SUPABASE_ANON_KEY,
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ internal_notes: internalNotes }),
        },
      );

      const responseBody = await response.json().catch(() => null);
      if (!response.ok) {
        const errorMessage =
          responseBody?.error?.message ||
          responseBody?.message ||
          "Failed to update internal notes";
        throw new Error(errorMessage);
      }

      return responseBody;
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["order", id] }),
        queryClient.invalidateQueries({ queryKey: ["orders"] }),
      ]);
      setIsEditingNotes(false);
      toast.success("Internal notes updated successfully");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update internal notes",
      );
    },
  });

  const handleEditTracking = () => {
    if (!canEditTracking) {
      toast.error("Tracking information is view-only for this role.");
      return;
    }

    setTrackingForm({
      tracking_number: order?.tracking_number || "",
      tracking_url: order?.tracking_url || "",
    });
    setIsEditingTracking(true);
  };

  const handleSaveTracking = () => {
    if (!canEditTracking) {
      toast.error("Tracking information is view-only for this role.");
      return;
    }

    updateMutation.mutate({
      tracking_number: trackingForm.tracking_number || null,
      tracking_url: trackingForm.tracking_url || null,
    });
  };

  const handleEditNotes = () => {
    if (!canEditInternalNotes) {
      toast.error("You do not have permission to edit internal notes.");
      return;
    }

    setNotesForm(order?.internal_notes || "");
    setIsEditingNotes(true);
  };

  const handleSaveNotes = () => {
    if (!canEditInternalNotes) {
      toast.error("You do not have permission to edit internal notes.");
      return;
    }

    updateNotesViaPlanApiMutation.mutate(notesForm || null);
  };

  const handleEditAddress = () => {
    if (!canEditShippingAddress) {
      toast.error(
        "Shipping address can only be edited before provider review pending.",
      );
      return;
    }

    if (isLoadingAvailableShippingStates || availableShippingStatesError) {
      toast.error("Available shipping states could not be loaded.");
      return;
    }

    const currentShippingState = normalizeUsStateCode(order?.shipping_state);

    setAddressForm({
      shipping_first_name: order?.shipping_first_name || "",
      shipping_last_name: order?.shipping_last_name || "",
      shipping_company: order?.shipping_company || "",
      shipping_address_line1: order?.shipping_address_line1 || "",
      shipping_address_line2: order?.shipping_address_line2 || "",
      shipping_city: order?.shipping_city || "",
      shipping_state: availableShippingStates.some(
          (state) => state.code === currentShippingState,
        )
        ? currentShippingState
        : "",
      shipping_postal_code: order?.shipping_postal_code || "",
      shipping_country: order?.shipping_country || "",
      shipping_instructions: order?.shipping_instructions || "",
    });
    setIsEditingAddress(true);
  };

  const handleSaveAddress = () => {
    if (!canEditShippingAddress) {
      toast.error(
        "Shipping address can only be edited before provider review pending.",
      );
      return;
    }

    if (
      !availableShippingStates.some(
        (state) => state.code === addressForm.shipping_state,
      )
    ) {
      toast.error("Please select an available shipping state.");
      return;
    }

    updateAddressViaPlanApiMutation.mutate({
      shipping_address: buildAddressInputFromShippingForm(addressForm),
    });
  };

  const handleEditBillingAddress = () => {
    if (!canEditBillingAddress) {
      toast.error(
        "Billing address can only be edited before payment pending.",
      );
      return;
    }

    setBillingAddressForm({
      billing_first_name: order?.billing_first_name || "",
      billing_last_name: order?.billing_last_name || "",
      billing_company: order?.billing_company || "",
      billing_address_line1: order?.billing_address_line1 || "",
      billing_address_line2: order?.billing_address_line2 || "",
      billing_city: order?.billing_city || "",
      billing_state: normalizeUsStateCode(order?.billing_state),
      billing_postal_code: order?.billing_postal_code || "",
      billing_country: order?.billing_country || "",
    });
    setIsEditingBillingAddress(true);
  };

  const handleSaveBillingAddress = () => {
    if (!canEditBillingAddress) {
      toast.error(
        "Billing address can only be edited before payment pending.",
      );
      return;
    }

    updateAddressViaPlanApiMutation.mutate({
      billing_address: buildAddressInputFromBillingForm(billingAddressForm),
    });
  };

  const handleCopyFromPatient = () => {
    if (!order?.patients) return;
    const patient = order.patients;
    const patientShippingState = normalizeUsStateCode(patient.shipping_state);

    setAddressForm({
      shipping_first_name:
        patient.shipping_first_name || patient.first_name || "",
      shipping_last_name: patient.shipping_last_name || patient.last_name || "",
      shipping_company: patient.shipping_company || "",
      shipping_address_line1: patient.shipping_address_line1 || "",
      shipping_address_line2: patient.shipping_address_line2 || "",
      shipping_city: patient.shipping_city || "",
      shipping_state: availableShippingStates.some(
          (state) => state.code === patientShippingState,
        )
        ? patientShippingState
        : "",
      shipping_postal_code: patient.shipping_postal_code || "",
      shipping_country: patient.shipping_country || "",
      shipping_instructions: patient.shipping_instructions || "",
    });
    toast.info("Address copied from patient profile");
  };

  const handleCopyBillingFromPatient = () => {
    if (!order?.patients) return;
    const patient = order.patients;

    setBillingAddressForm({
      billing_first_name:
        patient.billing_first_name || patient.first_name || "",
      billing_last_name: patient.billing_last_name || patient.last_name || "",
      billing_company: patient.billing_company || "",
      billing_address_line1: patient.billing_address_line1 || "",
      billing_address_line2: patient.billing_address_line2 || "",
      billing_city: patient.billing_city || "",
      billing_state: normalizeUsStateCode(patient.billing_state),
      billing_postal_code: patient.billing_postal_code || "",
      billing_country: patient.billing_country || "",
    });
    toast.info("Billing address copied from patient profile");
  };

  const handleCopyBillingFromShipping = () => {
    const shippingAddress = isEditingAddress
      ? addressForm
      : {
          shipping_first_name: order?.shipping_first_name || "",
          shipping_last_name: order?.shipping_last_name || "",
          shipping_company: order?.shipping_company || "",
          shipping_address_line1: order?.shipping_address_line1 || "",
          shipping_address_line2: order?.shipping_address_line2 || "",
          shipping_city: order?.shipping_city || "",
          shipping_state: normalizeUsStateCode(order?.shipping_state),
          shipping_postal_code: order?.shipping_postal_code || "",
          shipping_country: order?.shipping_country || "",
        };

    setBillingAddressForm({
      billing_first_name: shippingAddress.shipping_first_name,
      billing_last_name: shippingAddress.shipping_last_name,
      billing_company: shippingAddress.shipping_company,
      billing_address_line1: shippingAddress.shipping_address_line1,
      billing_address_line2: shippingAddress.shipping_address_line2,
      billing_city: shippingAddress.shipping_city,
      billing_state: shippingAddress.shipping_state,
      billing_postal_code: shippingAddress.shipping_postal_code,
      billing_country: shippingAddress.shipping_country,
    });
    toast.info("Billing address copied from shipping address");
  };

  const formatCurrency = (cents: number) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(cents / 100);
  };

  const formatDateTime = (value: string | null) => {
    if (!value) return "—";
    return dateTime(value).format("MMM D, YYYY h:mm A");
  };

  const getIntegrationDisplayName = (key: string | null | undefined): string => {
    if (!key) return "—";
    const normalized = String(key).trim().toLowerCase();
    const displayNames: Record<string, string> = {
      telegramd: "TelegraMD",
      telegra: "TelegraMD",
      md_integrations: "MDI",
      stripe: "Stripe",
      paypal: "PayPal",
    };
    return displayNames[normalized] || key;
  };

  if (isLoading) {
    return (
      <AdminLayout variant="tenant">
        <div className="space-y-6">
          <Skeleton className="h-10 w-48" />
          <div className="grid gap-6 md:grid-cols-2">
            <Skeleton className="h-64" />
            <Skeleton className="h-64" />
          </div>
        </div>
      </AdminLayout>
    );
  }

  if (!order) {
    return (
      <AdminLayout variant="tenant">
        <div className="text-center py-12">
          <h2 className="text-xl font-semibold">Order not found</h2>
          <p className="text-muted-foreground mt-2">
            The order you're looking for doesn't exist.
          </p>
          <Button
            variant="outline"
            className="mt-4"
            onClick={() => navigate(ROUTES.TENANT_ADMIN.ORDERS)}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Orders
          </Button>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title={order.product?.name || "Order Details"}
        description={`Order ${order.order_number}`}
        actions={
          <div className="flex items-center gap-2">
            {canApproveTelegraOrder && (
              <Button
                variant="outline"
                onClick={handleApproveTelegraOrder}
                disabled={isApprovingTelegraOrder}
              >
                {isApprovingTelegraOrder ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <CheckCircle2 className="h-4 w-4 mr-2" />
                )}
                Approve Telegra Order
              </Button>
            )}
            <Button
              variant="default"
              onClick={handleProcessOrder}
              disabled={isProcessingOrder}
            >
              {isProcessingOrder ? (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <RefreshCw className="h-4 w-4 mr-2" />
              )}
              Process Order
            </Button>
            <Button
              variant="outline"
              onClick={() => navigate(ROUTES.TENANT_ADMIN.ORDERS)}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Orders
            </Button>
          </div>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main Content - Left Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* Order Status Card */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                    <Package className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <CardTitle>Order Status</CardTitle>
                    {order.status_changed_at && (
                      <CardDescription>
                        Since{" "}
                        {dateTime(order.status_changed_at).format(
                          "MMM D, YYYY h:mm A",
                        )}
                      </CardDescription>
                    )}
                  </div>
                </div>
                <OrderStatusBadge
                  status={order.order_statuses}
                  fallbackLabel="No Status"
                />
              </div>
            </CardHeader>
            <CardContent>
              <OrderStatusSelect
                orderId={order.id}
                currentStatusId={order.status_id}
              />
            </CardContent>
          </Card>

          {providerPlatformLinks.length > 0 && (
            <Card>
              <CardHeader>
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                    <Package className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <CardTitle>Provider Platform Details</CardTitle>
                    <CardDescription>
                      External provider-platform references for this order
                    </CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                {isLoadingProviderPlatformLinks ? (
                  <>
                    <Skeleton className="h-20 w-full" />
                    <Skeleton className="h-20 w-full" />
                  </>
                ) : providerPlatformLinks.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    No provider platform link records found for this order.
                  </p>
                ) : (
                  providerPlatformLinks.map((link) => (
                    <div
                      key={link.id}
                      className="rounded-md border p-3 space-y-2"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <p className="font-medium">
                          { getIntegrationDisplayName(
                              order.provider_platform_integration_key
                          ) || "Unknown Integration"}
                        </p>
                        <span className="text-xs text-muted-foreground">
                          Linked {formatDateTime(link.created_at)}
                        </span>
                      </div>
                      <div className="flex items-center justify-between gap-4">
                        <span className="text-sm text-muted-foreground">
                          Integration Key
                        </span>
                        <span className="font-mono text-sm text-right break-all">
                          {getIntegrationDisplayName(
                            link.metadata?.integration_key ||
                              order.provider_platform_integration_key,
                          )}
                        </span>
                      </div>
                      <div className="flex items-center justify-between gap-4">
                        <span className="text-sm text-muted-foreground">
                          Selection Source
                        </span>
                        <span className="font-mono text-sm text-right break-all">
                          {link.metadata?.source || "—"}
                        </span>
                      </div>
                      <div className="flex items-center justify-between gap-4">
                        <span className="text-sm text-muted-foreground">
                          Provider Order ID
                        </span>
                        <span className="font-mono text-sm text-right break-all">
                          {link.provider_order_id || "—"}
                        </span>
                      </div>
                      <div className="flex items-start justify-between gap-4">
                        <span className="text-sm text-muted-foreground">
                          Questionnaire Instance IDs
                        </span>
                        <div className="text-right">
                          {Array.isArray(
                            link.metadata?.questionnaire_instance_ids,
                          ) &&
                          link.metadata.questionnaire_instance_ids.length > 0 ? (
                            <div className="space-y-1">
                              {link.metadata.questionnaire_instance_ids.map(
                                (questionnaireId) => (
                                  <div
                                    key={questionnaireId}
                                    className="font-mono text-sm break-all"
                                  >
                                    {questionnaireId}
                                  </div>
                                ),
                              )}
                            </div>
                          ) : (
                            <span className="font-mono text-sm">—</span>
                          )}
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>
          )}

          {/* Status History */}
          <OrderStatusHistory orderId={order.id} />

          {/* Tracking Information */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <Truck className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Tracking Information</CardTitle>
                  <CardDescription>
                    Shipping and tracking details
                  </CardDescription>
                </div>
              </div>
              {!isEditingTracking ? (
                canEditTracking && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleEditTracking}
                >
                  Edit
                </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setIsEditingTracking(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    size="sm"
                    onClick={handleSaveTracking}
                    disabled={updateMutation.isPending}
                  >
                    {updateMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    <Save className="h-4 w-4 mr-2" />
                    Save
                  </Button>
                </div>
              )}
            </CardHeader>
            <CardContent>
              {isEditingTracking ? (
                <div className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="tracking_number">Tracking Number</Label>
                    <Input
                      id="tracking_number"
                      value={trackingForm.tracking_number}
                      onChange={(e) =>
                        setTrackingForm({
                          ...trackingForm,
                          tracking_number: e.target.value,
                        })
                      }
                      placeholder="Enter tracking number"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="tracking_url">Tracking URL</Label>
                    <Input
                      id="tracking_url"
                      value={trackingForm.tracking_url}
                      onChange={(e) =>
                        setTrackingForm({
                          ...trackingForm,
                          tracking_url: e.target.value,
                        })
                      }
                      placeholder="https://..."
                    />
                  </div>
                </div>
              ) : (
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">
                      Tracking Number
                    </span>
                    <span className="font-mono">
                      {order.tracking_number || "—"}
                    </span>
                  </div>
                  {order.tracking_url && (
                    <div className="flex items-center justify-between">
                      <span className="text-muted-foreground">
                        Tracking Link
                      </span>
                      <a
                        href={order.tracking_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        data-testid="link-track-package"
                        className="text-primary hover:underline flex items-center gap-1"
                      >
                        Track Package
                        <ExternalLink className="h-3 w-3" />
                      </a>
                    </div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Shipping Address */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <MapPin className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Shipping Address</CardTitle>
                  <CardDescription>
                    Where the order will be delivered
                  </CardDescription>
                </div>
              </div>
              {!isEditingAddress ? (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <span
                      className="inline-flex"
                      tabIndex={isShippingAddressStatusLocked ? 0 : undefined}
                    >
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={handleEditAddress}
                        disabled={
                          !canEditShippingAddress ||
                          isLoadingAvailableShippingStates ||
                          !!availableShippingStatesError
                        }
                        title={
                          !isShippingAddressStatusLocked &&
                          availableShippingStatesError
                            ? "Available shipping states could not be loaded."
                            : undefined
                        }
                      >
                        Edit
                      </Button>
                    </span>
                  </TooltipTrigger>
                  {isShippingAddressStatusLocked ? (
                    <TooltipContent className="max-w-xs">
                      <p>
                        This order was already assigned or processed by a
                        provider. Shipping address cannot be changed on Nexus.
                        Reach out to the provider platform or to the pharmacy.
                      </p>
                    </TooltipContent>
                  ) : null}
                </Tooltip>
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleCopyFromPatient}
                  >
                    <Copy className="h-4 w-4 mr-2" />
                    Copy from Patient
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setIsEditingAddress(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    size="sm"
                    onClick={handleSaveAddress}
                    disabled={
                      updateMutation.isPending || !canEditShippingAddress
                    }
                  >
                    {updateMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    <Save className="h-4 w-4 mr-2" />
                    Save
                  </Button>
                </div>
              )}
            </CardHeader>
            <CardContent>
              {isEditingAddress ? (
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="shipping_first_name">First Name</Label>
                    <Input
                      id="shipping_first_name"
                      value={addressForm.shipping_first_name}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_first_name: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_last_name">Last Name</Label>
                    <Input
                      id="shipping_last_name"
                      value={addressForm.shipping_last_name}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_last_name: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="shipping_company">Company (optional)</Label>
                    <Input
                      id="shipping_company"
                      value={addressForm.shipping_company}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_company: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="shipping_address_line1">
                      Address Line 1
                    </Label>
                    <Input
                      id="shipping_address_line1"
                      value={addressForm.shipping_address_line1}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_address_line1: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="shipping_address_line2">
                      Address Line 2
                    </Label>
                    <Input
                      id="shipping_address_line2"
                      value={addressForm.shipping_address_line2}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_address_line2: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_city">City</Label>
                    <Input
                      id="shipping_city"
                      value={addressForm.shipping_city}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_city: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_state">State</Label>
                    <StateSelect
                      id="shipping_state"
                      value={addressForm.shipping_state}
                      states={availableShippingStates}
                      onValueChange={(value) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_state: value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_postal_code">Postal Code</Label>
                    <Input
                      id="shipping_postal_code"
                      value={addressForm.shipping_postal_code}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_postal_code: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_country">Country</Label>
                    <Input
                      id="shipping_country"
                      value={addressForm.shipping_country}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_country: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="shipping_instructions">
                      Delivery Instructions
                    </Label>
                    <Input
                      id="shipping_instructions"
                      placeholder="e.g., Leave at front door, ring doorbell twice"
                      value={addressForm.shipping_instructions}
                      onChange={(e) =>
                        setAddressForm({
                          ...addressForm,
                          shipping_instructions: e.target.value,
                        })
                      }
                    />
                  </div>
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="grid gap-6 md:grid-cols-2">
                    <div>
                      <p className="text-sm text-muted-foreground">Recipient</p>
                      <p className="font-medium">
                        {order.shipping_first_name ||
                        order.shipping_last_name ? (
                          <>
                            {order.shipping_first_name}{" "}
                            {order.shipping_last_name}
                            {order.shipping_company && (
                              <span className="text-muted-foreground">
                                ({order.shipping_company})
                              </span>
                            )}
                          </>
                        ) : (
                          "—"
                        )}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Address</p>
                      <p className="font-medium">
                        {order.shipping_address_line1 || order.shipping_city ? (
                          <>
                            {order.shipping_address_line1}
                            {order.shipping_address_line2 && (
                              <>, {order.shipping_address_line2}</>
                            )}
                            <br />
                            {[
                              order.shipping_city,
                              formatUsStateCode(order.shipping_state),
                              order.shipping_postal_code,
                            ]
                              .filter(Boolean)
                              .join(", ")}
                            {order.shipping_country && (
                              <>
                                <br />
                                {order.shipping_country}
                              </>
                            )}
                          </>
                        ) : (
                          "—"
                        )}
                      </p>
                    </div>
                  </div>
                  {order.shipping_instructions && (
                    <div>
                      <p className="text-sm text-muted-foreground">
                        Delivery Instructions
                      </p>
                      <p className="font-medium">
                        {order.shipping_instructions}
                      </p>
                    </div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Billing Address */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <MapPin className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Billing Address</CardTitle>
                  <CardDescription>
                    Address used for billing and payment records
                  </CardDescription>
                </div>
              </div>
              {!isEditingBillingAddress ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleEditBillingAddress}
                  disabled={!canEditBillingAddress}
                  title={
                    !canEditBillingAddress
                      ? "Billing address can only be edited before payment pending."
                      : undefined
                  }
                >
                  Edit
                </Button>
              ) : (
                <div className="flex flex-wrap justify-end gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleCopyBillingFromShipping}
                  >
                    <Copy className="h-4 w-4 mr-2" />
                    Copy from Shipping
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleCopyBillingFromPatient}
                  >
                    <Copy className="h-4 w-4 mr-2" />
                    Copy from Patient
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setIsEditingBillingAddress(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    size="sm"
                    onClick={handleSaveBillingAddress}
                    disabled={
                      updateMutation.isPending || !canEditBillingAddress
                    }
                  >
                    {updateMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    <Save className="h-4 w-4 mr-2" />
                    Save
                  </Button>
                </div>
              )}
            </CardHeader>
            <CardContent>
              {isEditingBillingAddress ? (
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="billing_first_name">First Name</Label>
                    <Input
                      id="billing_first_name"
                      value={billingAddressForm.billing_first_name}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_first_name: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_last_name">Last Name</Label>
                    <Input
                      id="billing_last_name"
                      value={billingAddressForm.billing_last_name}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_last_name: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="billing_company">Company (optional)</Label>
                    <Input
                      id="billing_company"
                      value={billingAddressForm.billing_company}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_company: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="billing_address_line1">
                      Address Line 1
                    </Label>
                    <Input
                      id="billing_address_line1"
                      value={billingAddressForm.billing_address_line1}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_address_line1: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="billing_address_line2">
                      Address Line 2
                    </Label>
                    <Input
                      id="billing_address_line2"
                      value={billingAddressForm.billing_address_line2}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_address_line2: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_city">City</Label>
                    <Input
                      id="billing_city"
                      value={billingAddressForm.billing_city}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_city: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_state">State</Label>
                    <StateSelect
                      id="billing_state"
                      value={billingAddressForm.billing_state}
                      states={US_STATES}
                      onValueChange={(value) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_state: value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_postal_code">Postal Code</Label>
                    <Input
                      id="billing_postal_code"
                      value={billingAddressForm.billing_postal_code}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_postal_code: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_country">Country</Label>
                    <Input
                      id="billing_country"
                      value={billingAddressForm.billing_country}
                      onChange={(e) =>
                        setBillingAddressForm({
                          ...billingAddressForm,
                          billing_country: e.target.value,
                        })
                      }
                    />
                  </div>
                </div>
              ) : (
                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <p className="text-sm text-muted-foreground">Recipient</p>
                    <p className="font-medium">
                      {order.billing_first_name || order.billing_last_name ? (
                        <>
                          {order.billing_first_name} {order.billing_last_name}
                          {order.billing_company && (
                            <span className="text-muted-foreground">
                              {" "}
                              ({order.billing_company})
                            </span>
                          )}
                        </>
                      ) : (
                        "—"
                      )}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Address</p>
                    <p className="font-medium">
                      {order.billing_address_line1 || order.billing_city ? (
                        <>
                          {order.billing_address_line1}
                          {order.billing_address_line2 && (
                            <>, {order.billing_address_line2}</>
                          )}
                          <br />
                          {[
                            order.billing_city,
                            formatUsStateCode(order.billing_state),
                            order.billing_postal_code,
                          ]
                            .filter(Boolean)
                            .join(", ")}
                          {order.billing_country && (
                            <>
                              <br />
                              {order.billing_country}
                            </>
                          )}
                        </>
                      ) : (
                        "—"
                      )}
                    </p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Payment Provider Details */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <CreditCard className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Payment Provider Details</CardTitle>
                  <CardDescription>
                    Provider transaction references for this order
                  </CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {isLoadingProviderTransactions ? (
                <>
                  <Skeleton className="h-20 w-full" />
                  <Skeleton className="h-20 w-full" />
                </>
              ) : providerTransactions.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No provider payment details found.
                </p>
              ) : (
                providerTransactions.map((transaction) => (
                  <div
                    key={transaction.id}
                    className="rounded-md border p-3 space-y-2"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <p className="font-medium">
                        {transaction.provider
                          ? `${transaction.provider.name} (${transaction.provider.key})`
                          : "Provider"}
                      </p>
                      {transaction.payment_status ? (
                        <Badge variant="secondary">
                          {transaction.payment_status}
                        </Badge>
                      ) : (
                        <span className="text-xs text-muted-foreground">
                          Status unavailable
                        </span>
                      )}
                    </div>
                    <div className="space-y-1 text-xs text-muted-foreground font-mono">
                      <p className="break-all">
                        paid_at: {formatDateTime(transaction.paid_at)}
                      </p>
                      <p className="break-all">
                        invoice: {transaction.provider_invoice_id || "—"}
                      </p>
                      <p className="break-all">
                        payment_intent:{" "}
                        {transaction.provider_payment_intent_id || "—"}
                      </p>
                      <p className="break-all">
                        charge: {transaction.provider_charge_id || "—"}
                      </p>
                      <p className="break-all">
                        subscription:{" "}
                        {transaction.provider_subscription_id || "—"}
                      </p>
                      <p className="break-all">
                        checkout:{" "}
                        {transaction.provider_checkout_session_id || "—"}
                      </p>
                      <p className="break-all">
                        snapshot_created:{" "}
                        {formatDateTime(transaction.created_at)}
                      </p>
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          {/* Internal Notes */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <FileText className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Internal Notes</CardTitle>
                  <CardDescription>Private notes for your team</CardDescription>
                </div>
              </div>
              {!isEditingNotes ? (
                canEditInternalNotes && (
                <Button variant="outline" size="sm" onClick={handleEditNotes}>
                  Edit
                </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setIsEditingNotes(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    size="sm"
                    onClick={handleSaveNotes}
                    disabled={updateNotesViaPlanApiMutation.isPending}
                  >
                    {updateNotesViaPlanApiMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    <Save className="h-4 w-4 mr-2" />
                    Save
                  </Button>
                </div>
              )}
            </CardHeader>
            <CardContent>
              {isEditingNotes ? (
                <Textarea
                  value={notesForm}
                  onChange={(e) => setNotesForm(e.target.value)}
                  placeholder="Add internal notes..."
                  rows={4}
                />
              ) : (
                <p
                  className={
                    order.internal_notes ? "" : "text-muted-foreground"
                  }
                >
                  {order.internal_notes || "No internal notes"}
                </p>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Sidebar - Right Column */}
        <div className="space-y-6">
          {/* Patient Card */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <User className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Patient</CardTitle>
                  <CardDescription>Customer information</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <p className="font-medium">
                  {order.patients.first_name} {order.patients.last_name}
                </p>
                <p className="text-sm text-muted-foreground">
                  {order.patients.email}
                </p>
                {order.patients.phone && (
                  <p className="text-sm text-muted-foreground">
                    {order.patients.phone}
                  </p>
                )}
              </div>
              <Link to={`${ROUTES.TENANT_ADMIN.PATIENTS}/${order.patients.id}`}>
                <Button variant="outline" size="sm" className="w-full">
                  <User className="h-4 w-4 mr-2" />
                  View Patient Profile
                </Button>
              </Link>
            </CardContent>
          </Card>

          {/* Product Card */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <Package className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Product</CardTitle>
                  <CardDescription>Linked product</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              {order.product ? (
                <p className="font-medium">{order.product.name}</p>
              ) : (
                <p className="text-muted-foreground">—</p>
              )}
              {order.product && (
                <Link
                  to={ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(
                    ":id",
                    order.product.id,
                  )}
                >
                  <Button variant="outline" size="sm" className="w-full">
                    <Package className="h-4 w-4 mr-2" />
                    View Product
                  </Button>
                </Link>
              )}
            </CardContent>
          </Card>

          {/* Subscription Card */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <Repeat className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Subscription</CardTitle>
                  <CardDescription>Linked lifecycle record</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              {order.subscription ? (
                <>
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Subscription</span>
                    <span className="font-mono text-xs">
                      {`SUB-${order.subscription.id.slice(0, 8).toUpperCase()}`}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">State</span>
                    <StatusBadge status={order.subscription.status} size="sm" />
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Order Type</span>
                    {order.subscription_order_type ? (
                      <Badge
                        variant={
                          order.subscription_order_type === "initial"
                            ? "default"
                            : "secondary"
                        }
                      >
                        {order.subscription_order_type === "initial"
                          ? "First Order"
                          : "Renewal"}
                      </Badge>
                    ) : (
                      <span>—</span>
                    )}
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Renewal</span>
                    <span>
                      {order.subscription.current_period_end_at
                        ? dateTime(
                            order.subscription.current_period_end_at,
                          ).format("MMM D, YYYY")
                        : "—"}
                    </span>
                  </div>
                  <Link
                    to={ROUTES.TENANT_ADMIN.SUBSCRIPTION_DETAIL.replace(
                      ":id",
                      order.subscription.id,
                    )}
                  >
                    <Button variant="outline" size="sm" className="w-full">
                      <Repeat className="h-4 w-4 mr-2" />
                      View Subscription
                    </Button>
                  </Link>
                </>
              ) : (
                <p className="text-muted-foreground">No linked subscription</p>
              )}
            </CardContent>
          </Card>

          {/* Order Summary Card */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                  <DollarSign className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <CardTitle>Order Summary</CardTitle>
                  <CardDescription>Payment details</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Subtotal</span>
                <span>{formatCurrency(order.subtotal_cents)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Shipping</span>
                <span>{formatCurrency(order.shipping_cents)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Tax</span>
                <span>{formatCurrency(order.tax_cents)}</span>
              </div>
              {order.discount_cents > 0 && (
                <div className="flex items-start justify-between">
                  <span className="text-muted-foreground flex flex-col gap-0.5">
                    <span>Discount</span>
                    {(order.coupon_code || order.coupon_name) && (
                      <span className="text-xs font-mono text-muted-foreground/70">
                        {[order.coupon_code, order.coupon_name]
                          .filter(Boolean)
                          .join(" — ")}
                      </span>
                    )}
                  </span>
                  <span className="text-green-600">
                    -{formatCurrency(order.discount_cents)}
                  </span>
                </div>
              )}
              <Separator />
              <div className="flex items-center justify-between font-medium text-lg">
                <span>Total</span>
                <span>{formatCurrency(order.total_cents)}</span>
              </div>
            </CardContent>
          </Card>

          {/* Metadata Card */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm">Order Metadata</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Order ID</span>
                <span className="font-mono text-xs">{order.id}</span>
              </div>
              <MigrationStatus
                metadata={order.metadata}
                entityType="order"
                createdAt={order.created_at}
              />
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Renewal Date</span>
                <span>
                  {order.subscription?.current_period_end_at
                    ? dateTime(order.subscription.current_period_end_at).format(
                        "MMM D, YYYY",
                      )
                    : "—"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Created</span>
                <span>{dateTime(order.created_at).format("MMM D, YYYY")}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Updated</span>
                <span>{dateTime(order.updated_at).format("MMM D, YYYY")}</span>
              </div>
              <Separator />
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">
                  Cancellation Operation Key
                </span>
                <span className="font-mono text-xs">
                  {order.cancellation_operation_key || "—"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">
                  Cancellation Started
                </span>
                <span>
                  {order.cancellation_operation_started_at
                    ? dateTime(order.cancellation_operation_started_at).format(
                        "MMM D, YYYY h:mm A",
                      )
                    : "—"}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted-foreground">
                  Cancellation Completed
                </span>
                <span>
                  {order.cancellation_operation_completed_at
                    ? dateTime(
                        order.cancellation_operation_completed_at,
                      ).format("MMM D, YYYY h:mm A")
                    : "—"}
                </span>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
