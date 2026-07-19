import { useAuditLog } from "@/hooks/useAuditLog";
import { supabase } from "@/integrations/supabase/client";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

// ─── Types ─────────────────────────────────────────────────────────────────────

export interface StripeCoupon {
  id: string;
  name: string | null;
  percent_off: number | null;
  amount_off: number | null;
  currency: string | null;
  duration: "once" | "repeating" | "forever";
  duration_in_months: number | null;
  applies_to: { products: string[] } | null;
  valid: boolean;
  created: number;
}

export interface StripePromotionCode {
  id: string;
  code: string;
  active: boolean;
  coupon: StripeCoupon;
  max_redemptions: number | null;
  times_redeemed: number;
  expires_at: number | null;
  created: number;
  metadata: Record<string, string>;
  /** Derived from metadata.coupon_type by the edge function */
  coupon_type: "internal" | "marketing" | null;
  /** Derived from metadata.created_by_email by the edge function */
  created_by: string | null;
}

export interface CreateCouponInput {
  code: string;
  name: string;
  coupon_type?: "internal" | "marketing";
  discount_type: "percent" | "amount";
  percent_off?: number;
  amount_off?: number;
  currency?: string;
  duration: "once" | "repeating" | "forever";
  duration_in_months?: number;
  max_redemptions?: number;
  /** Unix timestamp (seconds) */
  expires_at?: number;
}

export interface UpdateCouponInput {
  promotion_code_id: string;
  coupon_id?: string;
  name?: string;
  coupon_type?: "internal" | "marketing";
}

// ─── Helpers ───────────────────────────────────────────────────────────────────

async function invokeCouponApi<T>(body: Record<string, unknown>): Promise<T> {
  const { data, error } = await supabase.functions.invoke<{
    success: boolean;
    data: T;
    error?: { code: string; message: string };
  }>("stripe-coupon-api", { body });

  if (error) {
    // Try to extract the meaningful error message from the response body.
    // The Supabase client returns the raw Response as error.context; read its
    // body to get the actual error detail before falling back to the generic
    // "Edge Function returned a non-2xx status code" message.
    try {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const responseBody = await (error as any).context?.json?.();
      // Our edge functions return { success: false, error: { code, message } }
      // Supabase gateway errors return { message: "...", code: "..." }
      const detail =
        responseBody?.error?.message ||
        responseBody?.message ||
        responseBody?.msg;
      if (detail) {
        throw new Error(String(detail));
      }
    } catch (inner) {
      if (inner instanceof Error && inner.message !== (error as Error).message)
        throw inner;
    }
    throw new Error((error as Error).message ?? "Edge function error");
  }
  if (!data?.success) {
    throw new Error(data?.error?.message ?? "Operation failed");
  }
  return data.data as T;
}

// ─── Hooks ─────────────────────────────────────────────────────────────────────

/** Fetch all Stripe promotion codes that apply to the given product. */
export function useProductCoupons(productId: string, tenantId: string | null) {
  return useQuery<StripePromotionCode[]>({
    queryKey: ["product-coupons", productId, tenantId],
    enabled: !!productId && !!tenantId,
    staleTime: 30_000,
    queryFn: () =>
      invokeCouponApi<StripePromotionCode[]>({
        action: "list",
        product_id: productId,
        tenant_id: tenantId,
      }),
  });
}

/** Create a Stripe coupon + promotion code scoped to the given product. */
export function useCreateCoupon(productId: string, tenantId: string | null) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  return useMutation({
    mutationFn: (input: CreateCouponInput) =>
      invokeCouponApi<StripePromotionCode>({
        action: "create",
        product_id: productId,
        tenant_id: tenantId,
        ...input,
      }),
    onSuccess: (promotionCode) => {
      queryClient.invalidateQueries({
        queryKey: ["product-coupons", productId],
      });
      toast.success(`Coupon "${promotionCode.code}" created successfully`);
      logAction("create_coupon", "product", productId, {
        promotion_code_id: promotionCode.id,
        code: promotionCode.code,
      });
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to create coupon");
    },
  });
}

/** Deactivate (archive) a Stripe promotion code. */
export function useDeactivateCoupon(
  productId: string,
  tenantId: string | null,
) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  return useMutation({
    mutationFn: (promotionCodeId: string) =>
      invokeCouponApi<StripePromotionCode>({
        action: "deactivate",
        promotion_code_id: promotionCodeId,
        tenant_id: tenantId,
      }),
    onSuccess: (promotionCode) => {
      queryClient.invalidateQueries({
        queryKey: ["product-coupons", productId],
      });
      toast.success(`Coupon "${promotionCode.code}" deactivated`);
      logAction("deactivate_coupon", "product", productId, {
        promotion_code_id: promotionCode.id,
        code: promotionCode.code,
      });
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to deactivate coupon");
    },
  });
}

/**
 * Set or clear the default promotion code stored in product.metadata.
 * When set, the plan-api checkout session will auto-apply the code instead of
 * showing the patient a promo code entry field.
 */
export function useSetDefaultCoupon(
  productId: string,
  tenantId: string | null,
) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  return useMutation({
    mutationFn: (promotionCodeId: string | null) =>
      invokeCouponApi<{
        product_id: string;
        stripe_promotion_code_id: string | null;
      }>({
        action: "set_default",
        product_id: productId,
        tenant_id: tenantId,
        promotion_code_id: promotionCodeId,
      }),
    onSuccess: (_result, promotionCodeId) => {
      queryClient.invalidateQueries({ queryKey: ["product", productId] });
      queryClient.invalidateQueries({
        queryKey: ["product-coupons", productId],
      });
      toast.success(
        promotionCodeId
          ? "Default coupon set — this coupon will auto-apply at checkout"
          : "Default coupon cleared — patients can enter a code at checkout",
      );
      logAction("set_default_coupon", "product", productId, {
        stripe_promotion_code_id: promotionCodeId,
      });
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to update default coupon");
    },
  });
}

/** Update a coupon's name or type label. */
export function useUpdateCoupon(productId: string, tenantId: string | null) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  return useMutation({
    mutationFn: (input: UpdateCouponInput) =>
      invokeCouponApi<StripePromotionCode>({
        action: "update",
        tenant_id: tenantId,
        ...input,
      }),
    onSuccess: (promotionCode) => {
      queryClient.invalidateQueries({
        queryKey: ["product-coupons", productId],
      });
      toast.success(`Coupon "${promotionCode.code}" updated`);
      logAction("update_coupon", "product", productId, {
        promotion_code_id: promotionCode.id,
        code: promotionCode.code,
      });
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to update coupon");
    },
  });
}

/** Enable or disable the promo code entry field for a product at checkout. */
export function useToggleProductPromoCodes(
  productId: string,
  tenantId: string | null,
) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();

  return useMutation({
    mutationFn: (enabled: boolean) =>
      invokeCouponApi<{ product_id: string; allow_promo_codes: boolean }>({
        action: "toggle_promo_codes",
        product_id: productId,
        tenant_id: tenantId,
        enabled,
      }),
    onSuccess: (_result, enabled) => {
      queryClient.invalidateQueries({ queryKey: ["product", productId] });
      queryClient.invalidateQueries({
        queryKey: ["product-coupons", productId],
      });
      toast.success(
        enabled
          ? "Promo code entry enabled — patients can enter a code at checkout"
          : "Promo code entry disabled",
      );
      logAction("toggle_promo_codes", "product", productId, {
        allow_promo_codes: enabled,
      });
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to update promo code setting");
    },
  });
}
