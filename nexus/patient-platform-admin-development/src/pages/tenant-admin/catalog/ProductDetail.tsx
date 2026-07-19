import { HtmlEditor } from "@/components/common/HtmlEditor";
import { ImageUpload } from "@/components/common/ImageUpload";
import { PageHeader } from "@/components/common/PageHeader";
import { ScrollableTextPreview } from "@/components/common/ScrollableTextPreview";
import { TermsPreview } from "@/components/common/TermsPreview";
import {
  ProductCategoriesManager,
  ProductCategoryBadges,
} from "@/components/features/ProductCategoriesManager";
import { IncludedFeaturesEditor } from "@/components/features/IncludedFeaturesEditor";
import { ProductPageContentEditor } from "@/components/features/ProductPageContentEditor";
import { ProductFaqsManager } from "@/components/features/ProductFaqsManager";
import { ProductMedicationsManager } from "@/components/features/ProductMedicationsManager";
import { ProductPaymentProvidersManager } from "@/components/features/ProductPaymentProvidersManager";
import { ProductProviderPlatformsManager } from "@/components/features/ProductProviderPlatformsManager";
import { ProductSalesTab } from "@/components/features/ProductSalesTab";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
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
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { useAuditLog } from "@/hooks/useAuditLog";
import { syncProductToProviders } from "@/hooks/useProductSync";
import { supabase } from "@/integrations/supabase/client";
import { ROUTES } from "@/lib/constants";
import { dateTime } from "@/lib/dayjs";
import { toNullableRichTextHtml } from "@/lib/html-content";
import { getMissingProductAvailabilityInfo } from "@/lib/product-availability";
import { canEditResource } from "@/lib/admin-permissions";
import { useAuth } from "@/stores/authStore";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  Check,
  CreditCard,
  Loader2,
  Package,
  RefreshCw,
  Save,
  Trash2,
} from "lucide-react";
import { useEffect, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { toast } from "sonner";

type SubscriptionIntervalPreset = "every_month" | "every_3_months" | "custom";

const getSubscriptionIntervalPreset = (
  interval: SubscriptionInterval | null | undefined,
  count: number | null | undefined,
): SubscriptionIntervalPreset => {
  const normalizedInterval = interval || "month";
  const normalizedCount = count || 1;

  if (normalizedInterval === "month" && normalizedCount === 1) {
    return "every_month";
  }

  if (normalizedInterval === "month" && normalizedCount === 3) {
    return "every_3_months";
  }

  return "custom";
};

const getSubscriptionFrequencyLabel = (
  interval: SubscriptionInterval | null | undefined,
  count: number | null | undefined,
): string => {
  const normalizedInterval = interval || "month";
  const normalizedCount = count || 1;

  if (normalizedInterval === "month" && normalizedCount === 1) {
    return "Every Month";
  }

  if (normalizedInterval === "month" && normalizedCount === 3) {
    return "Every 3 Months";
  }

  return `Every ${normalizedCount} ${normalizedInterval}${normalizedCount > 1 ? "s" : ""}`;
};

export default function ProductDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(false);
  const [isEditingPayment, setIsEditingPayment] = useState(false);
  const [searchParams] = useSearchParams();
  const [activeTab, setActiveTab] = useState(
    searchParams.get("tab") || "details",
  );
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [renewalLeadDaysInput, setRenewalLeadDaysInput] = useState("0");
  const [renewalAdvanceWeeksInput, setRenewalAdvanceWeeksInput] = useState("2");
  const [paymentPriceInput, setPaymentPriceInput] = useState("0.00");
  // Display-only anchor price. Empty string = no anchor shown.
  const [compareAtPriceInput, setCompareAtPriceInput] = useState("");
  const [paymentTypeDraft, setPaymentTypeDraft] =
    useState<PaymentType>("one_time");
  const [subscriptionIntervalPresetDraft, setSubscriptionIntervalPresetDraft] =
    useState<SubscriptionIntervalPreset>("every_month");
  const [subscriptionIntervalDraft, setSubscriptionIntervalDraft] =
    useState<SubscriptionInterval>("month");
  const [subscriptionIntervalCountDraft, setSubscriptionIntervalCountDraft] =
    useState("1");
  const [availabilityWarning, setAvailabilityWarning] = useState<
    string[] | null
  >(null);
  const [editForm, setEditForm] = useState<{
    name: string;
    sku: string;
    description: string;
    terms_and_conditions_html: string;
    included_features: string[];
  }>({
    name: "",
    sku: "",
    description: "",
    terms_and_conditions_html: "",
    included_features: [],
  });
  const canEditProducts = canEditResource(
    { isPlatformSuperadmin, isTenantAdmin, isCustomerSupport, currentTenantId },
    "product",
  );

  const [
    { data: product, isLoading },
    { data: medicationsCount = 0 },
    { data: paymentProvidersCount = 0, isLoading: isLoadingPaymentProviders },
    { data: providerPlatformsCount = 0, isLoading: isLoadingProviderPlatforms },
    { data: faqsCount = 0, isLoading: isLoadingFaqs },
    { data: salesCount = 0 },
    {
      data: productMedicationDetails = [],
      isLoading: isLoadingProductMedicationDetails,
    },
    { data: productCategoriesCount = 0, isLoading: isLoadingProductCategories },
  ] = useQueries({
    queries: [
      {
        // Fetch product details
        queryKey: ["product", id],
        queryFn: async () => {
          if (!id) throw new Error("Product ID is required");

          const { data, error } = await supabase
            .from("products")
            .select("*")
            .eq("id", id)
            .maybeSingle();

          if (error) throw error;
          return data as Product | null;
        },
        enabled: !!id,
      },
      {
        // Fetch linked medications count
        queryKey: ["product-medications-count", id],
        queryFn: async () => {
          if (!id) return 0;

          const { count, error } = await supabase
            .from("product_medications")
            .select("*", { count: "exact", head: true })
            .eq("product_id", id);

          if (error) throw error;
          return count || 0;
        },
        enabled: !!id,
      },
      {
        // Fetch payment providers count
        queryKey: ["product-payment-providers-count", id],
        queryFn: async () => {
          if (!id) return 0;

          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const { count, error } = await (supabase as any)
            .from("product_payment_providers")
            .select("*", { count: "exact", head: true })
            .eq("product_id", id)
            .eq("is_enabled", true);

          if (error) throw error;
          return count || 0;
        },
        enabled: !!id,
      },
      {
        queryKey: ["product-provider-platforms-count", id],
        queryFn: async () => {
          if (!id) return 0;

          const { count, error } = await supabase
            .from("product_provider_platforms")
            .select("*", { count: "exact", head: true })
            .eq("product_id", id)
            .eq("is_enabled", true);

          if (error) throw error;
          return count || 0;
        },
        enabled: !!id,
      },
      {
        queryKey: ["product-faqs-count", id],
        queryFn: async () => {
          if (!id) return 0;

          const { count, error } = await supabase
            .from("product_faqs")
            .select("*", { count: "exact", head: true })
            .eq("product_id", id);

          if (error) throw error;
          return count || 0;
        },
        enabled: !!id,
      },
      {
        queryKey: ["product-sales-count", id],
        queryFn: async () => {
          if (!id) return 0;

          const { count, error } = await supabase
            .from("orders")
            .select("*", { count: "exact", head: true })
            .eq("product_id", id);

          if (error) throw error;
          return count || 0;
        },
        enabled: !!id,
      },
      {
        queryKey: ["product-medication-availability-details", id],
        queryFn: async () => {
          if (!id) return [];

          const { data, error } = await supabase
            .from("product_medications")
            .select("medication:medications(title, description, image_url)")
            .eq("product_id", id);

          if (error) throw error;
          return data as Array<{
            medication: {
              title: string;
              description: string | null;
              image_url: string | null;
            } | null;
          }>;
        },
        enabled: !!id,
      },
      {
        queryKey: ["product-categories-count", id],
        queryFn: async () => {
          if (!id) return 0;

          const { count, error } = await supabase
            .from("product_category_assignments" as "medication_capabilities")
            .select("*", { count: "exact", head: true })
            .eq("product_id" as "id", id);

          if (error) throw error;
          return count || 0;
        },
        enabled: !!id,
      },
    ],
  });

  useEffect(() => {
    if (!product) return;
    setRenewalLeadDaysInput(
      String(product.subscription_renewal_lead_days ?? 0),
    );
    setRenewalAdvanceWeeksInput(
      String(product.renewal_advance_max_weeks ?? 2),
    );
    setPaymentPriceInput((product.price_cents / 100).toFixed(2));
    setCompareAtPriceInput(
      product.compare_at_price_cents != null
        ? (product.compare_at_price_cents / 100).toFixed(2)
        : "",
    );
    setPaymentTypeDraft(product.payment_type || "one_time");
    setSubscriptionIntervalPresetDraft(
      getSubscriptionIntervalPreset(
        product.subscription_interval,
        product.subscription_interval_count,
      ),
    );
    setSubscriptionIntervalDraft(product.subscription_interval || "month");
    setSubscriptionIntervalCountDraft(
      String(product.subscription_interval_count || 1),
    );
  }, [product]);

  // If the Provider Platforms tab is hidden (product has no medications) but it's
  // the active tab, fall back to Details so the page isn't blank.
  useEffect(() => {
    const readonlyVisibleTabs = new Set([
      "details",
      "page-content",
      "faqs",
      "medications",
      "payments",
      "sales",
    ]);
    if (medicationsCount > 0) {
      readonlyVisibleTabs.add("provider-platforms");
    }

    if (!canEditProducts && !readonlyVisibleTabs.has(activeTab)) {
      setActiveTab("details");
      return;
    }
    if (medicationsCount === 0 && activeTab === "provider-platforms") {
      setActiveTab("details");
    }
  }, [canEditProducts, medicationsCount, activeTab]);

  const updateMutation = useMutation({
    mutationFn: async (data: ProductUpdateData) => {
      if (!id) throw new Error("Product ID is required");
      if (!currentTenantId) throw new Error("No tenant selected");

      const beforeData = product;
      const { data: updated, error } = await supabase
        .from("products")
        .update(data)
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;

      const updatedProduct = updated as Product;

      // Only sync to payment providers if pricing/name/description changes
      const syncableFields = [
        "name",
        "description",
        "price_cents",
        "compare_at_price_cents",
        "payment_type",
        "subscription_interval",
        "subscription_interval_count",
        "subscription_renewal_lead_days",
        "image_url",
      ];
      const hasSyncableChanges = syncableFields.some((field) => field in data);

      if (hasSyncableChanges) {
        try {
          await syncProductToProviders(
            "update",
            {
              id: updatedProduct.id,
              name: updatedProduct.name,
              description: updatedProduct.description,
              price_cents: updatedProduct.price_cents,
              payment_type: updatedProduct.payment_type || "one_time",
              subscription_interval:
                updatedProduct.subscription_interval || null,
              subscription_interval_count:
                updatedProduct.subscription_interval_count || null,
              subscription_renewal_lead_days:
                updatedProduct.subscription_renewal_lead_days ?? 0,
              sku: updatedProduct.sku,
              image_url: updatedProduct.image_url || null,
            },
            currentTenantId,
          );
        } catch (syncError) {
          // Rollback: restore the original product data
          if (beforeData) {
            const rollbackData: ProductUpdateData = {};
            for (const key of Object.keys(data) as Array<
              keyof ProductUpdateData
            >) {
              (rollbackData as Record<string, unknown>)[key] = (
                beforeData as unknown as Record<string, unknown>
              )[key];
            }
            await supabase.from("products").update(rollbackData).eq("id", id);
          }
          throw syncError;
        }
      }

      return { product: updatedProduct, beforeData };
    },
    onSuccess: ({ product: updated, beforeData }) => {
      queryClient.setQueryData(["product", id], updated);
      queryClient.invalidateQueries({ queryKey: ["product", id] });
      queryClient.invalidateQueries({ queryKey: ["products"] });
      logAction({
        action: "update",
        entityType: "product",
        entityId: id!,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: updated as unknown as Record<string, unknown>,
      });
      toast.success("Product updated and synced");
      setIsEditing(false);
      setIsEditingPayment(false);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update product",
      );
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async () => {
      if (!id) throw new Error("Product ID is required");

      const beforeData = product;
      const { error } = await supabase.from("products").delete().eq("id", id);

      if (error) throw error;
      return beforeData;
    },
    onSuccess: (beforeData) => {
      logAction({
        action: "delete",
        entityType: "product",
        entityId: id!,
        beforeData: beforeData as unknown as Record<string, unknown>,
      });
      toast.success("Product deleted successfully");
      navigate(ROUTES.TENANT_ADMIN.CATALOG.PRODUCTS);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to delete product",
      );
    },
  });

  const handleEdit = () => {
    if (product) {
      setEditForm({
        name: product.name,
        sku: product.sku || "",
        description: product.description || "",
        terms_and_conditions_html: product.terms_and_conditions_html || "",
        included_features: Array.isArray(product.included_features)
          ? product.included_features
          : [],
      });
      setFormErrors({});
      setIsEditing(true);
    }
  };

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};

    if (!editForm.name.trim()) {
      errors.name = "Product name is required";
    } else if (editForm.name.trim().length > 100) {
      errors.name = "Name must be 100 characters or less";
    }

    if (editForm.sku && editForm.sku.length > 50) {
      errors.sku = "SKU must be 50 characters or less";
    }

    if (editForm.description && editForm.description.length > 500) {
      errors.description = "Description must be 500 characters or less";
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSave = () => {
    if (!validateForm()) {
      toast.error("Please fix the validation errors");
      return;
    }

    updateMutation.mutate({
      name: editForm.name.trim(),
      sku: editForm.sku.trim() || null,
      description: editForm.description.trim() || null,
      terms_and_conditions_html: toNullableRichTextHtml(
        editForm.terms_and_conditions_html,
      ),
      // Drop blank rows and trim; order is preserved as authored.
      included_features: editForm.included_features
        .map((feature) => feature.trim())
        .filter((feature) => feature.length > 0),
    });
  };

  const handleToggleEnabled = (enabled: boolean) => {
    if (!product) return;
    if (enabled && !canEnable) {
      toast.error(
        getEnableBlockReason() || "Missing requirements to enable product",
      );
      return;
    }

    if (enabled) {
      const missingInfo = getMissingProductInformation();
      if (missingInfo.length > 0) {
        setAvailabilityWarning(missingInfo);
        return;
      }
    }

    updateMutation.mutate({ is_enabled: enabled });
  };

  const handleConfirmEnableWithMissingInfo = () => {
    updateMutation.mutate({ is_enabled: true });
    setAvailabilityWarning(null);
  };

  const handleImageChange = (imageUrl: string | null) => {
    if (!product) return;

    updateMutation.mutate({ image_url: imageUrl });
  };

  /**
   * Persist edited page content into `metadata.pdp`.
   *
   * `metadata` is a shared jsonb blob — other producers keep their own keys in
   * it (`allow_promo_codes`, …). We merge onto whatever is already there and
   * PATCH the whole object, so a bare `{ pdp }` can never drop the rest. An
   * empty `pdp` (everything cleared) is written as `{}` so the key persists but
   * carries nothing, matching the importer's pruned shape.
   */
  const handleSavePageContent = async (pdp: ProductPdpContent) => {
    if (!product) return;

    const base =
      product.metadata && typeof product.metadata === "object"
        ? product.metadata
        : {};

    await updateMutation.mutateAsync({
      metadata: { ...base, pdp },
    });
  };

  const formatCurrency = (cents: number) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(cents / 100);
  };

  const handleSavePaymentConfiguration = () => {
    if (!product) return;

    const trimmedPrice = paymentPriceInput.trim();
    const parsedPrice = Number(trimmedPrice);

    if (!trimmedPrice || !Number.isFinite(parsedPrice) || parsedPrice < 0) {
      toast.error("Price must be a valid positive number");
      setPaymentPriceInput((product.price_cents / 100).toFixed(2));
      return;
    }

    const nextPriceCents = Math.round(parsedPrice * 100);

    // The compare-at price is optional; blank clears it.
    const trimmedCompareAt = compareAtPriceInput.trim();
    let nextCompareAtPriceCents: number | null = null;

    if (trimmedCompareAt) {
      const parsedCompareAt = Number(trimmedCompareAt);

      if (!Number.isFinite(parsedCompareAt) || parsedCompareAt < 0) {
        toast.error("Compare-at price must be a valid positive number");
        return;
      }

      nextCompareAtPriceCents = Math.round(parsedCompareAt * 100);

      // An anchor at or below the price advertises a saving that does not exist.
      if (nextCompareAtPriceCents <= nextPriceCents) {
        toast.error("Compare-at price must be higher than the price");
        return;
      }
    }

    const payload: ProductUpdateData = {
      price_cents: nextPriceCents,
      compare_at_price_cents: nextCompareAtPriceCents,
      payment_type: paymentTypeDraft,
    };

    if (paymentTypeDraft === "subscription") {
      const resolvedInterval =
        subscriptionIntervalPresetDraft === "every_month"
          ? "month"
          : subscriptionIntervalPresetDraft === "every_3_months"
            ? "month"
            : subscriptionIntervalDraft;
      const resolvedIntervalCount =
        subscriptionIntervalPresetDraft === "every_month"
          ? 1
          : subscriptionIntervalPresetDraft === "every_3_months"
            ? 3
            : Number(subscriptionIntervalCountDraft);
      const trimmedLeadDays = renewalLeadDaysInput.trim();
      const parsedLeadDays = Number(trimmedLeadDays);
      const trimmedAdvanceWeeks = renewalAdvanceWeeksInput.trim();
      const parsedAdvanceWeeks = Number(trimmedAdvanceWeeks);

      if (
        !Number.isInteger(resolvedIntervalCount) ||
        resolvedIntervalCount < 1
      ) {
        toast.error("Interval count must be a whole number 1 or greater");
        setSubscriptionIntervalCountDraft(
          String(product.subscription_interval_count || 1),
        );
        return;
      }

      if (
        !trimmedLeadDays ||
        !Number.isInteger(parsedLeadDays) ||
        parsedLeadDays < 0
      ) {
        toast.error("Renewal lead days must be a whole number 0 or greater");
        setRenewalLeadDaysInput(
          String(product.subscription_renewal_lead_days ?? 0),
        );
        return;
      }

      if (
        !trimmedAdvanceWeeks ||
        !Number.isInteger(parsedAdvanceWeeks) ||
        parsedAdvanceWeeks < 0
      ) {
        toast.error(
          "Renewal advance weeks must be a whole number 0 or greater",
        );
        setRenewalAdvanceWeeksInput(
          String(product.renewal_advance_max_weeks ?? 2),
        );
        return;
      }

      payload.subscription_interval = resolvedInterval;
      payload.subscription_interval_count = resolvedIntervalCount;
      payload.subscription_renewal_lead_days = parsedLeadDays;
      payload.renewal_advance_max_weeks = parsedAdvanceWeeks;
    } else {
      payload.subscription_interval = null;
      payload.subscription_interval_count = null;
      payload.subscription_renewal_lead_days = 0;
    }

    updateMutation.mutate(payload);
  };

  const resetPaymentDrafts = () => {
    if (!product) return;

    setPaymentPriceInput((product.price_cents / 100).toFixed(2));
    setCompareAtPriceInput(
      product.compare_at_price_cents != null
        ? (product.compare_at_price_cents / 100).toFixed(2)
        : "",
    );
    setPaymentTypeDraft(product.payment_type || "one_time");
    setSubscriptionIntervalPresetDraft(
      getSubscriptionIntervalPreset(
        product.subscription_interval,
        product.subscription_interval_count,
      ),
    );
    setSubscriptionIntervalDraft(product.subscription_interval || "month");
    setSubscriptionIntervalCountDraft(
      String(product.subscription_interval_count || 1),
    );
    setRenewalLeadDaysInput(
      String(product.subscription_renewal_lead_days ?? 0),
    );
    setRenewalAdvanceWeeksInput(
      String(product.renewal_advance_max_weeks ?? 2),
    );
  };

  const handleEditPaymentConfiguration = () => {
    resetPaymentDrafts();
    setIsEditingPayment(true);
  };

  const handleCancelPaymentConfiguration = () => {
    resetPaymentDrafts();
    setIsEditingPayment(false);
  };

  const hasPaymentProviders = paymentProvidersCount > 0;
  const hasProviderPlatforms = providerPlatformsCount > 0;
  const canEnable = hasPaymentProviders && hasProviderPlatforms;
  const isProductReadinessLoading =
    isLoadingPaymentProviders ||
    isLoadingProviderPlatforms ||
    isLoadingFaqs ||
    isLoadingProductMedicationDetails ||
    isLoadingProductCategories;
  const getMissingProductInformation = () => {
    if (!product) return [];

    return getMissingProductAvailabilityInfo(product, {
      medications: productMedicationDetails
        .map((link) => link.medication)
        .filter((medication): medication is NonNullable<typeof medication> =>
          Boolean(medication),
        ),
      hasCategories: productCategoriesCount > 0,
      hasFaqs: faqsCount > 0,
    });
  };
  const normalizedPaymentPriceCents =
    product && Number.isFinite(Number(paymentPriceInput.trim()))
      ? Math.round(Number(paymentPriceInput.trim()) * 100)
      : (product?.price_cents ?? 0);
  // Mirror handleSavePaymentConfiguration: blank clears the anchor (null),
  // otherwise round to cents. An unparseable draft falls back to the saved
  // value so we don't flag a spurious change.
  const trimmedCompareAtInput = compareAtPriceInput.trim();
  const normalizedCompareAtPriceCents = !trimmedCompareAtInput
    ? null
    : Number.isFinite(Number(trimmedCompareAtInput))
      ? Math.round(Number(trimmedCompareAtInput) * 100)
      : (product?.compare_at_price_cents ?? null);
  const hasPaymentConfigChanges = product
    ? normalizedPaymentPriceCents !== product.price_cents ||
      normalizedCompareAtPriceCents !== (product.compare_at_price_cents ?? null) ||
      paymentTypeDraft !== product.payment_type ||
      (paymentTypeDraft === "subscription" &&
        ((subscriptionIntervalPresetDraft === "every_month"
          ? "month"
          : subscriptionIntervalPresetDraft === "every_3_months"
            ? "month"
            : subscriptionIntervalDraft) !==
          (product.subscription_interval || "month") ||
          (subscriptionIntervalPresetDraft === "every_month"
            ? 1
            : subscriptionIntervalPresetDraft === "every_3_months"
              ? 3
              : Number(subscriptionIntervalCountDraft)) !==
            (product.subscription_interval_count || 1) ||
          Number(renewalLeadDaysInput.trim() || "0") !==
            (product.subscription_renewal_lead_days ?? 0) ||
          Number(renewalAdvanceWeeksInput.trim() || "2") !==
            (product.renewal_advance_max_weeks ?? 2))) ||
      (paymentTypeDraft === "one_time" && product.payment_type !== "one_time")
    : false;

  const getEnableBlockReason = (): string | null => {
    if (!hasPaymentProviders && !hasProviderPlatforms) {
      return "Assign at least one payment provider and one provider platform before enabling";
    }
    if (!hasPaymentProviders)
      return "Assign at least one payment provider before enabling";
    if (!hasProviderPlatforms)
      return "Assign at least one provider platform before enabling";
    return null;
  };

  if (isLoading) {
    return (
      <AdminLayout variant="tenant">
        <div className="space-y-6">
          <Skeleton className="h-10 w-48" />
          <Skeleton className="h-64 w-full" />
        </div>
      </AdminLayout>
    );
  }

  if (!product) {
    return (
      <AdminLayout variant="tenant">
        <div className="text-center py-12">
          <Package className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
          <h2 className="text-xl font-semibold">Product not found</h2>
          <p className="text-muted-foreground mt-2">
            The product you're looking for doesn't exist.
          </p>
          <Button
            variant="outline"
            className="mt-4"
            onClick={() => navigate(ROUTES.TENANT_ADMIN.CATALOG.PRODUCTS)}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Products
          </Button>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title={product.name}
        description={product.sku ? `SKU: ${product.sku}` : "No SKU assigned"}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() => navigate(ROUTES.TENANT_ADMIN.CATALOG.PRODUCTS)}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back
            </Button>
            {canEditProducts && (
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button variant="destructive">
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>Delete Product</AlertDialogTitle>
                    <AlertDialogDescription>
                      Are you sure you want to delete this product? This action
                      cannot be undone and will remove all associated medication
                      links.
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                      onClick={() => deleteMutation.mutate()}
                      className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                      {deleteMutation.isPending ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                      ) : (
                        "Delete"
                      )}
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            )}
          </div>
        }
      />

      <Tabs
        value={activeTab}
        onValueChange={setActiveTab}
        className="space-y-6"
      >
        <TabsList>
          <TabsTrigger value="details">Details</TabsTrigger>
          <TabsTrigger value="page-content">Page Content</TabsTrigger>
          <TabsTrigger value="medications">
            Medications ({medicationsCount})
          </TabsTrigger>
          <TabsTrigger value="faqs">FAQs ({faqsCount})</TabsTrigger>
          {/* Providers (IDs + per-state routing) only apply to products that
              contain medications. Hidden for non-medication products. */}
          {medicationsCount > 0 && (
            <TabsTrigger value="provider-platforms">
              Providers ({providerPlatformsCount})
            </TabsTrigger>
          )}
          <TabsTrigger value="payments">Payment Configuration</TabsTrigger>
          <TabsTrigger value="sales">Sales ({salesCount})</TabsTrigger>
        </TabsList>

        <TabsContent value="details" className="space-y-6">
          {/* Product Information Card */}
          <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle className="text-lg">Product Information</CardTitle>
                <CardDescription>Basic product details</CardDescription>
              </div>
              {!isEditing ? (
                canEditProducts && (
                  <Button variant="outline" onClick={handleEdit}>
                    Edit
                  </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button variant="outline" onClick={() => setIsEditing(false)}>
                    Cancel
                  </Button>
                  <Button
                    onClick={handleSave}
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
            <CardContent className="space-y-6">
              <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                <div className="space-y-4">
                  <div className="rounded-lg border bg-muted/20 p-4 space-y-3">
                    <div className="space-y-1">
                      <Label>Product Status</Label>
                      <p className="text-sm text-muted-foreground">
                        {canEnable
                          ? "This product can be enabled for customers"
                          : getEnableBlockReason()}
                      </p>
                    </div>
                    <div className="flex items-center gap-3">
                      <Switch
                        checked={product.is_enabled}
                        onCheckedChange={handleToggleEnabled}
                        disabled={
                          !canEditProducts ||
                          updateMutation.isPending ||
                          isProductReadinessLoading
                        }
                      />
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
                          product.is_enabled
                            ? "bg-green-100 text-green-800"
                            : "bg-muted text-muted-foreground"
                        }`}
                      >
                        {product.is_enabled ? "Enabled" : "Disabled"}
                      </span>
                    </div>
                  </div>

                  <div className="rounded-lg border bg-muted/20 p-4 space-y-2">
                    <Label>Product Image</Label>
                    <ImageUpload
                      bucket="product-images"
                      folder={product.tenant_id}
                      value={product.image_url}
                      onChange={handleImageChange}
                      disabled={!canEditProducts || updateMutation.isPending}
                    />
                  </div>
                </div>

                <div className="rounded-lg border p-4 md:p-5 space-y-4">
                  <div>
                    <p className="text-sm font-medium">Product Details</p>
                    <p className="text-xs text-muted-foreground">
                      Core metadata used across catalog and checkout flows.
                    </p>
                  </div>

                  {isEditing ? (
                    <div className="grid gap-5 md:grid-cols-2">
                      <div className="space-y-2">
                        <Label htmlFor="name">Product Name *</Label>
                        <Input
                          id="name"
                          value={editForm.name}
                          onChange={(e) =>
                            setEditForm({ ...editForm, name: e.target.value })
                          }
                          maxLength={100}
                          className={
                            formErrors.name ? "border-destructive" : ""
                          }
                        />
                        {formErrors.name && (
                          <p className="text-sm text-destructive">
                            {formErrors.name}
                          </p>
                        )}
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor="sku">SKU</Label>
                        <Input
                          id="sku"
                          value={editForm.sku}
                          onChange={(e) =>
                            setEditForm({ ...editForm, sku: e.target.value })
                          }
                          maxLength={50}
                          className={formErrors.sku ? "border-destructive" : ""}
                        />
                        {formErrors.sku && (
                          <p className="text-sm text-destructive">
                            {formErrors.sku}
                          </p>
                        )}
                      </div>
                      <div className="space-y-2 md:col-span-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                          id="description"
                          value={editForm.description}
                          onChange={(e) =>
                            setEditForm({
                              ...editForm,
                              description: e.target.value,
                            })
                          }
                          maxLength={500}
                          rows={4}
                          className={
                            formErrors.description ? "border-destructive" : ""
                          }
                        />
                        {formErrors.description && (
                          <p className="text-sm text-destructive">
                            {formErrors.description}
                          </p>
                        )}
                      </div>
                      <div className="space-y-2 md:col-span-2">
                        <Label htmlFor="terms_and_conditions_html">
                          Terms and Conditions
                        </Label>
                        <HtmlEditor
                          id="terms_and_conditions_html"
                          value={editForm.terms_and_conditions_html}
                          onChange={(value) =>
                            setEditForm({
                              ...editForm,
                              terms_and_conditions_html: value,
                            })
                          }
                          placeholder="Add the terms and conditions for this product..."
                          minHeightClassName="h-48"
                          editorClassName="overflow-auto resize-y"
                        />
                      </div>
                      <div className="space-y-2 md:col-span-2">
                        <Label>What’s Included</Label>
                        <p className="text-xs text-muted-foreground">
                          Bullets shown under “What’s Included” in the patient
                          checkout summary for this product.
                        </p>
                        <IncludedFeaturesEditor
                          value={editForm.included_features}
                          onChange={(next) =>
                            setEditForm({
                              ...editForm,
                              included_features: next,
                            })
                          }
                        />
                      </div>
                    </div>
                  ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">
                          Product Name
                        </p>
                        <p className="font-medium mt-1">{product.name}</p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">SKU</p>
                        <p className="font-medium font-mono mt-1">
                          {product.sku || "—"}
                        </p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">Created</p>
                        <p className="font-medium mt-1">
                          {dateTime(product.created_at).format("MMM D, YYYY")}
                        </p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3 sm:col-span-2">
                        <p className="text-xs text-muted-foreground">
                          Description
                        </p>
                        <ScrollableTextPreview
                          value={product.description}
                          className="mt-2"
                        />
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3 sm:col-span-2">
                        <p className="text-xs text-muted-foreground">
                          Terms and Conditions
                        </p>
                        <TermsPreview
                          content={product.terms_and_conditions_html}
                          className="mt-2"
                        />
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3 sm:col-span-2">
                        <p className="text-xs text-muted-foreground">
                          What’s Included
                        </p>
                        {product.included_features?.length ? (
                          <ul className="mt-2 space-y-1">
                            {product.included_features.map((feature, index) => (
                              <li
                                key={index}
                                className="flex items-center gap-2 text-sm"
                              >
                                <Check className="h-4 w-4 shrink-0 text-emerald-600" />
                                <span>{feature}</span>
                              </li>
                            ))}
                          </ul>
                        ) : (
                          <p className="mt-2 text-sm text-muted-foreground">
                            —
                          </p>
                        )}
                      </div>
                    </div>
                  )}

                  <div className="border-t pt-4 space-y-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="text-sm font-medium">Categories</p>
                        <p className="text-xs text-muted-foreground">
                          Organize this product in catalog groupings.
                        </p>
                      </div>
                      {canEditProducts && (
                        <ProductCategoriesManager
                          productId={product.id}
                          productName={product.name}
                          trigger={
                            <Button type="button" variant="outline" size="sm">
                              Manage Categories
                            </Button>
                          }
                        />
                      )}
                    </div>
                    <ProductCategoryBadges productId={product.id} />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="page-content" className="space-y-6">
          <ProductPageContentEditor
            value={
              (product.metadata as Record<string, unknown> | null)
                ?.pdp as ProductPdpContent | null
            }
            tenantId={product.tenant_id}
            onSave={handleSavePageContent}
            isSaving={updateMutation.isPending}
            readOnly={!canEditProducts}
          />
        </TabsContent>

        <TabsContent value="medications">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Linked Medications</CardTitle>
              <CardDescription>
                Medications included in this product. Each medication can be
                added with a quantity and instructions.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <ProductMedicationsManager
                productId={product.id}
                productName={product.name}
                readOnly={!canEditProducts}
              />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="faqs">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Product FAQs</CardTitle>
              <CardDescription>
                Frequently asked questions shown in patient-facing product APIs.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <ProductFaqsManager
                productId={product.id}
                productName={product.name}
                readOnly={!canEditProducts}
              />
            </CardContent>
          </Card>
        </TabsContent>

        {medicationsCount > 0 && (
          <TabsContent value="provider-platforms" className="space-y-6">
            <ProductProviderPlatformsManager
              productId={product.id}
              readOnly={!canEditProducts}
            />
          </TabsContent>
        )}

        <TabsContent value="payments" className="space-y-6">
          {/* Payment Configuration Card */}
          <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle className="text-lg flex items-center gap-2">
                  {(isEditingPayment
                    ? paymentTypeDraft
                    : product.payment_type) === "subscription" ? (
                    <RefreshCw className="h-5 w-5 text-primary" />
                  ) : (
                    <CreditCard className="h-5 w-5 text-primary" />
                  )}
                  Payment Configuration
                </CardTitle>
                <CardDescription>
                  Configure how customers are charged for this product
                </CardDescription>
              </div>
              {!isEditingPayment ? (
                canEditProducts && (
                  <Button
                    variant="outline"
                    onClick={handleEditPaymentConfiguration}
                  >
                    Edit
                  </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={handleCancelPaymentConfiguration}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleSavePaymentConfiguration}
                    disabled={
                      updateMutation.isPending || !hasPaymentConfigChanges
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
            <CardContent className="space-y-4">
              {isEditingPayment ? (
                <>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="payment_price">Price (USD)</Label>
                      <Input
                        id="payment_price"
                        type="number"
                        min="0"
                        step="0.01"
                        value={paymentPriceInput}
                        onChange={(e) => setPaymentPriceInput(e.target.value)}
                        disabled={updateMutation.isPending}
                      />
                      <p className="text-xs text-muted-foreground">
                        Applied to one-time purchases and subscription renewals.
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="compare_at_price">
                        Compare-at price (USD){" "}
                        <span className="text-muted-foreground">— optional</span>
                      </Label>
                      <Input
                        id="compare_at_price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="None"
                        value={compareAtPriceInput}
                        onChange={(e) => setCompareAtPriceInput(e.target.value)}
                        disabled={updateMutation.isPending}
                      />
                      <p className="text-xs text-muted-foreground">
                        Shown struck through beside the price on the website
                        (&ldquo;<s>$749</s> $499&rdquo;). Display only — the
                        customer is never charged this, on the first order or on
                        renewals. Must be higher than the price. Leave blank for
                        no strike-through.
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label>Payment Type</Label>
                      <Select
                        value={paymentTypeDraft}
                        onValueChange={(value: PaymentType) => {
                          setPaymentTypeDraft(value);
                          if (value === "subscription") {
                            setSubscriptionIntervalPresetDraft(
                              getSubscriptionIntervalPreset(
                                product.subscription_interval,
                                product.subscription_interval_count,
                              ),
                            );
                            setSubscriptionIntervalDraft(
                              product.subscription_interval || "month",
                            );
                            setSubscriptionIntervalCountDraft(
                              String(product.subscription_interval_count || 1),
                            );
                            setRenewalLeadDaysInput(
                              String(
                                product.subscription_renewal_lead_days ?? 0,
                              ),
                            );
                          }
                        }}
                        disabled={updateMutation.isPending}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="one_time">
                            <div className="flex items-center gap-2">
                              <CreditCard className="h-4 w-4" />
                              One-time Purchase
                            </div>
                          </SelectItem>
                          <SelectItem value="subscription">
                            <div className="flex items-center gap-2">
                              <RefreshCw className="h-4 w-4" />
                              Subscription
                            </div>
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  {paymentTypeDraft === "subscription" && (
                    <div className="rounded-lg border bg-muted/30 p-4 space-y-4">
                      <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <RefreshCw className="h-4 w-4" />
                        Subscription Settings
                      </div>
                      <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                          <Label>Subscription Cycles</Label>
                          <Select
                            value={subscriptionIntervalPresetDraft}
                            onValueChange={(
                              value: SubscriptionIntervalPreset,
                            ) => {
                              setSubscriptionIntervalPresetDraft(value);
                            }}
                            disabled={updateMutation.isPending}
                          >
                            <SelectTrigger>
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="every_month">
                                Every Month
                              </SelectItem>
                              <SelectItem value="every_3_months">
                                Every 3 Months
                              </SelectItem>
                              <SelectItem value="custom">Custom</SelectItem>
                            </SelectContent>
                          </Select>
                          <p className="text-xs text-muted-foreground">
                            Choose a common billing cadence or define a custom
                            one.
                          </p>
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="subscription_renewal_lead_days">
                            Renewal Lead Days
                          </Label>
                          <Input
                            id="subscription_renewal_lead_days"
                            type="number"
                            min="0"
                            step="1"
                            value={renewalLeadDaysInput}
                            onChange={(e) =>
                              setRenewalLeadDaysInput(e.target.value)
                            }
                            disabled={updateMutation.isPending}
                          />
                          <p className="text-xs text-muted-foreground">
                            Days before expiration to trigger renewal billing.
                          </p>
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="renewal_advance_max_weeks">
                            Allow to move renewals to X weeks before end of cycle
                          </Label>
                          <Input
                            id="renewal_advance_max_weeks"
                            type="number"
                            min="0"
                            step="1"
                            value={renewalAdvanceWeeksInput}
                            onChange={(e) =>
                              setRenewalAdvanceWeeksInput(e.target.value)
                            }
                            disabled={updateMutation.isPending}
                          />
                          <p className="text-xs text-muted-foreground">
                            Not yet enforced — the refill-date window is
                            currently fixed at ±2 weeks around the renewal date.
                            Reserved for an upcoming per-product control.
                          </p>
                        </div>
                      </div>
                      {subscriptionIntervalPresetDraft === "custom" && (
                        <div className="grid gap-4 sm:grid-cols-2">
                          <div className="space-y-2">
                            <Label>Billing Period</Label>
                            <Select
                              value={subscriptionIntervalDraft}
                              onValueChange={(value: SubscriptionInterval) => {
                                setSubscriptionIntervalDraft(value);
                              }}
                              disabled={updateMutation.isPending}
                            >
                              <SelectTrigger>
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="day">Daily</SelectItem>
                                <SelectItem value="week">Weekly</SelectItem>
                                <SelectItem value="month">Monthly</SelectItem>
                                <SelectItem value="year">Yearly</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                          <div className="space-y-2">
                            <Label>Interval Count</Label>
                            <Input
                              type="number"
                              min="1"
                              step="1"
                              value={subscriptionIntervalCountDraft}
                              onChange={(e) =>
                                setSubscriptionIntervalCountDraft(
                                  e.target.value,
                                )
                              }
                              disabled={updateMutation.isPending}
                            />
                            <p className="text-xs text-muted-foreground">
                              Example: enter 2 with Monthly for every 2 months.
                            </p>
                          </div>
                        </div>
                      )}
                      <p className="text-sm text-muted-foreground">
                        Customers will be billed{" "}
                        <span className="font-semibold text-foreground">
                          {formatCurrency(normalizedPaymentPriceCents)}
                        </span>{" "}
                        <span className="font-semibold text-foreground">
                          {getSubscriptionFrequencyLabel(
                            subscriptionIntervalPresetDraft === "every_month"
                              ? "month"
                              : subscriptionIntervalPresetDraft ===
                                  "every_3_months"
                                ? "month"
                                : subscriptionIntervalDraft,
                            subscriptionIntervalPresetDraft === "every_month"
                              ? 1
                              : subscriptionIntervalPresetDraft ===
                                  "every_3_months"
                                ? 3
                                : Number(subscriptionIntervalCountDraft) || 1,
                          ).toLowerCase()}
                        </span>
                        . Customers will be charged{" "}
                        <span className="font-semibold text-foreground">
                          {Number(renewalLeadDaysInput.trim() || "0")} day
                          {Number(renewalLeadDaysInput.trim() || "0") === 1
                            ? ""
                            : "s"}
                        </span>{" "}
                        before the subscription expires.
                      </p>
                    </div>
                  )}

                  {paymentTypeDraft === "one_time" && (
                    <p className="text-sm text-muted-foreground">
                      Customers will be charged{" "}
                      <span className="font-semibold text-foreground">
                        {formatCurrency(normalizedPaymentPriceCents)}
                      </span>{" "}
                      once at the time of purchase.
                    </p>
                  )}
                </>
              ) : (
                <>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-md border bg-muted/10 p-3">
                      <p className="text-xs text-muted-foreground">Price</p>
                      <p className="font-medium mt-1">
                        {formatCurrency(product.price_cents)}
                      </p>
                    </div>

                    {/* Its own tile, always shown — including when unset. Folding
                        it into the Price line only when it had a value meant the
                        field was invisible until somebody already knew it
                        existed. */}
                    <div className="rounded-md border bg-muted/10 p-3">
                      <p className="text-xs text-muted-foreground">
                        Compare-at Price
                      </p>
                      {product.compare_at_price_cents != null ? (
                        <>
                          {/* Rendered the way the website renders it, so what an
                              admin sees here is what a customer sees there. */}
                          <p className="font-medium mt-1">
                            <s className="mr-2 font-normal text-muted-foreground">
                              {formatCurrency(product.compare_at_price_cents)}
                            </s>
                            {formatCurrency(product.price_cents)}
                          </p>
                          <p className="mt-1 text-xs text-muted-foreground">
                            Display only — never charged.
                          </p>
                        </>
                      ) : (
                        <>
                          <p className="font-medium mt-1 text-muted-foreground">
                            None
                          </p>
                          <p className="mt-1 text-xs text-muted-foreground">
                            No strike-through shown on the website.
                          </p>
                        </>
                      )}
                    </div>

                    <div className="rounded-md border bg-muted/10 p-3">
                      <p className="text-xs text-muted-foreground">
                        Payment Type
                      </p>
                      <p className="font-medium mt-1">
                        {product.payment_type === "subscription"
                          ? "Subscription"
                          : "One-time Purchase"}
                      </p>
                    </div>
                    {product.payment_type === "subscription" ? (
                      <>
                        <div className="rounded-md border bg-muted/10 p-3">
                          <p className="text-xs text-muted-foreground">
                            Subscription Cycles
                          </p>
                          <p className="font-medium mt-1">
                            {getSubscriptionFrequencyLabel(
                              product.subscription_interval,
                              product.subscription_interval_count,
                            )}
                          </p>
                        </div>
                        <div className="rounded-md border bg-muted/10 p-3">
                          <p className="text-xs text-muted-foreground">
                            Renewal Lead Days
                          </p>
                          <p className="font-medium mt-1">
                            {product.subscription_renewal_lead_days ?? 0}
                          </p>
                        </div>
                        <div className="rounded-md border bg-muted/10 p-3 sm:col-span-2">
                          <p className="text-xs text-muted-foreground">
                            Allow to move renewals to X weeks before end of
                            cycle{" "}
                            <span className="italic">(not yet enforced)</span>
                          </p>
                          <p className="font-medium mt-1">
                            {product.renewal_advance_max_weeks ?? 2} week
                            {(product.renewal_advance_max_weeks ?? 2) === 1
                              ? ""
                              : "s"}
                          </p>
                        </div>
                      </>
                    ) : null}
                  </div>

                  {product.payment_type === "subscription" ? (
                    <p className="text-sm text-muted-foreground">
                      Customers will be billed{" "}
                      <span className="font-semibold text-foreground">
                        {formatCurrency(product.price_cents)}
                      </span>{" "}
                      <span className="font-semibold text-foreground">
                        {getSubscriptionFrequencyLabel(
                          product.subscription_interval,
                          product.subscription_interval_count,
                        ).toLowerCase()}
                      </span>
                      . Customers will be charged{" "}
                      <span className="font-semibold text-foreground">
                        {product.subscription_renewal_lead_days ?? 0} day
                        {(product.subscription_renewal_lead_days ?? 0) === 1
                          ? ""
                          : "s"}
                      </span>{" "}
                      before the subscription expires.
                    </p>
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      Customers will be charged{" "}
                      <span className="font-semibold text-foreground">
                        {formatCurrency(product.price_cents)}
                      </span>{" "}
                      once at the time of purchase.
                    </p>
                  )}
                </>
              )}
            </CardContent>
          </Card>

          <ProductPaymentProvidersManager
            productId={product.id}
            tenantId={currentTenantId}
            allowPromoCodes={Boolean(
              (product.metadata as Record<string, unknown> | null)
                ?.allow_promo_codes,
            )}
            readOnly={!canEditProducts}
          />
        </TabsContent>

        <TabsContent value="sales" className="space-y-6">
          <ProductSalesTab
            productId={product.id}
            tenantId={product.tenant_id}
          />
        </TabsContent>
      </Tabs>

      <AlertDialog
        open={!!availabilityWarning}
        onOpenChange={(open) => {
          if (!open) setAvailabilityWarning(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Make product available?</AlertDialogTitle>
            <AlertDialogDescription>
              "{product.name}" is missing information that customers may see.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="rounded-md border bg-muted/20 p-3">
            <p className="text-sm font-medium">Missing information</p>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
              {availabilityWarning?.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmEnableWithMissingInfo}
              disabled={updateMutation.isPending}
            >
              {updateMutation.isPending && (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              )}
              Confirm
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
