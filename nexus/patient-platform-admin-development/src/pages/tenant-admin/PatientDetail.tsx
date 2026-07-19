import { PageHeader } from "@/components/common/PageHeader";
import { MigrationStatus } from "@/components/common/MigrationStatus";
import { StatusBadge } from "@/components/common/StatusBadge";
import { TermsPreview } from "@/components/common/TermsPreview";
import { ChangePasswordDialog } from "@/components/features/ChangePasswordDialog";
import { OrderStatusBadge } from "@/components/features/OrderStatusBadge";
import { SendTestNotificationDialog } from "@/components/features/SendTestNotificationDialog";
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useAuditLog } from "@/hooks/useAuditLog";
import { useTenantAllowedStates } from "@/hooks/useTenantAllowedStates";
import { supabase } from "@/integrations/supabase/client";
import { ROUTES } from "@/lib/constants";
import { canPerformAction } from "@/lib/admin-permissions";
import { dateTime } from "@/lib/dayjs";
import { normalizePhoneDigits } from "@/lib/phone";
import { normalizeUsStateCode } from "@/lib/usStates";
import {
  validateBillingAddress,
  validatePatient,
  validateShippingAddress,
} from "@/lib/validations";
import { useAuth } from "@/stores/authStore";
import {
  useMutation,
  useQueries,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { ArrowLeft, Loader2, Save, Trash2 } from "lucide-react";
import { useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";

type ProviderIntegrationOption = {
  id: string;
  integration_key: string;
  is_enabled: boolean;
};

type PatientProviderPlatformLink = {
  id: string;
  patient_id: string;
  tenant_id: string;
  tenant_integration_id: string;
  provider_patient_id: string | null;
  metadata: Record<string, unknown>;
  created_at: string;
  updated_at: string;
  tenant_integrations?: ProviderIntegrationOption | null;
};

type PatientTermsAcceptance = {
  id: string;
  tenant_id: string;
  patient_id: string;
  product_id: string;
  accepted_at: string;
  accepted_content: string | null;
  created_at: string;
  products?:
    | { id: string; name: string }
    | Array<{ id: string; name: string }>
    | null;
};

type PatientTenantTermsAcceptance = {
  id: string;
  tenant_id: string;
  patient_id: string;
  platform_terms_version_id: string;
  platform_terms_version: number;
  accepted_at: string;
  created_at: string;
  platform_terms_versions?:
    | {
        id: string;
        version: number;
        content: string;
        published_at: string | null;
      }
    | Array<{
        id: string;
        version: number;
        content: string;
        published_at: string | null;
      }>
    | null;
};

type PatientPrivacyPolicyAcceptance = {
  id: string;
  tenant_id: string;
  patient_id: string;
  privacy_policy_version_id: string;
  privacy_policy_version: number;
  accepted_at: string;
  created_at: string;
  privacy_policy_versions?:
    | { id: string; version: number; content: string; published_at: string | null }
    | Array<{
        id: string;
        version: number;
        content: string;
        published_at: string | null;
      }>
    | null;
};

export default function PatientDetail() {
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
  const {
    availableStates: availableShippingStates,
    isLoading: isLoadingAvailableShippingStates,
    error: availableShippingStatesError,
  } = useTenantAllowedStates();
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [isEditingShipping, setIsEditingShipping] = useState(false);
  const [isEditingBilling, setIsEditingBilling] = useState(false);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [editForm, setEditForm] = useState<PatientUpdateData>({});
  const [isEditingProviderLink, setIsEditingProviderLink] = useState(false);
  const [editingProviderLinkId, setEditingProviderLinkId] = useState<
    string | null
  >(null);
  const [providerLinkForm, setProviderLinkForm] = useState({
    tenant_integration_id: "",
    provider_patient_id: "",
  });
  const [providerLinkError, setProviderLinkError] = useState<string | null>(
    null,
  );
  const permissionContext = {
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    currentTenantId,
  };
  const canEditPatient = canPerformAction(permissionContext, "patient:edit");
  const canDeletePatient = canPerformAction(permissionContext, "patient:delete");
  const canManageProviderLinks = canPerformAction(
    permissionContext,
    "provider_integrations:manage",
  );

  const [
    { data: patient, isLoading: isLoadingPatient },
    { data: termsAcceptances = [], isLoading: isLoadingTermsAcceptances },
    {
      data: platformTermsAcceptances = [],
      isLoading: isLoadingPlatformTermsAcceptances,
    },
    {
      data: privacyPolicyAcceptances = [],
      isLoading: isLoadingPrivacyPolicyAcceptances,
    },
    { data: orders = [], isLoading: isLoadingOrders },
    { data: providerLinks = [], isLoading: isLoadingProviderLinks },
    {
      data: providerPlatformIntegrations = [],
      isLoading: isLoadingProviderPlatformIntegrations,
    },
  ] = useQueries({
    queries: [
      {
        // Fetch patient details
        queryKey: ["patient", id],
        queryFn: async () => {
          if (!id) throw new Error("Patient ID is required");

          const { data, error } = await supabase
            .from("patients")
            .select("*")
            .eq("id", id)
            .single();

          if (error) throw error;
          return data as Patient;
        },
        enabled: !!id,
      },
      {
        queryKey: ["patient-terms-acceptances", id, currentTenantId],
        queryFn: async () => {
          if (!id || !currentTenantId) return [];

          const { data, error } = await supabase
            .from("patient_terms_acceptances")
            .select(
              `
              id,
              tenant_id,
              patient_id,
              product_id,
              accepted_at,
              accepted_content,
              created_at,
              products (
                id,
                name
              )
            `,
            )
            .eq("patient_id", id)
            .eq("tenant_id", currentTenantId)
            .order("accepted_at", { ascending: false });

          if (error) throw error;
          return data as unknown as PatientTermsAcceptance[];
        },
        enabled: !!id && !!currentTenantId,
      },
      {
        queryKey: ["patient-tenant-terms-acceptances", id, currentTenantId],
        queryFn: async () => {
          if (!id || !currentTenantId) return [];

          const client = supabase as unknown as {
            from: (table: string) => {
              select: (query: string) => {
                eq: (
                  column: string,
                  value: string,
                ) => {
                  eq: (
                    column: string,
                    value: string,
                  ) => {
                    order: (
                      column: string,
                      options?: { ascending?: boolean },
                    ) => Promise<{
                      data: unknown[] | null;
                      error: Error | null;
                    }>;
                  };
                };
              };
            };
          };

          const { data, error } = await client
            .from("patient_platform_terms_acceptances")
            .select(
              `
              id,
              tenant_id,
              patient_id,
              platform_terms_version_id,
              platform_terms_version,
              accepted_at,
              created_at,
              platform_terms_versions (
                id,
                version,
                content,
                published_at
              )
            `,
            )
            .eq("patient_id", id)
            .eq("tenant_id", currentTenantId)
            .order("accepted_at", { ascending: false });

          if (error) throw error;
          return data as PatientTenantTermsAcceptance[];
        },
        enabled: !!id && !!currentTenantId,
      },
      {
        queryKey: ["patient-privacy-policy-acceptances", id, currentTenantId],
        queryFn: async () => {
          if (!id || !currentTenantId) return [];

          const client = supabase as unknown as {
            from: (table: string) => {
              select: (query: string) => {
                eq: (column: string, value: string) => {
                  eq: (column: string, value: string) => {
                    order: (
                      column: string,
                      options?: { ascending?: boolean },
                    ) => Promise<{ data: unknown[] | null; error: Error | null }>;
                  };
                };
              };
            };
          };

          const { data, error } = await client
            .from("patient_privacy_policy_acceptances")
            .select(
              `
              id,
              tenant_id,
              patient_id,
              privacy_policy_version_id,
              privacy_policy_version,
              accepted_at,
              created_at,
              privacy_policy_versions (
                id,
                version,
                content,
                published_at
              )
            `,
            )
            .eq("patient_id", id)
            .eq("tenant_id", currentTenantId)
            .order("accepted_at", { ascending: false });

          if (error) throw error;
          return data as PatientPrivacyPolicyAcceptance[];
        },
        enabled: !!id && !!currentTenantId,
      },
      {
        // Fetch patient orders with status
        queryKey: ["patient-orders", id],
        queryFn: async () => {
          if (!id) return [];

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
              product:products (
                id,
                name
              ),
              order_statuses (
                id,
                status_key,
                admin_status_label,
                is_terminal,
                next_step_owner
              )
            `,
            )
            .eq("patient_id", id)
            .order("created_at", { ascending: false });

          if (error) throw error;
          return data as unknown as PatientOrder[];
        },
        enabled: !!id,
      },
      {
        queryKey: ["patient-provider-platform-links", id, currentTenantId],
        queryFn: async () => {
          if (!id || !currentTenantId) return [];

          const { data, error } = await supabase
            .from("patient_provider_platform_links")
            .select(
              `
              id,
              patient_id,
              tenant_id,
              tenant_integration_id,
              provider_patient_id,
              metadata,
              created_at,
              updated_at,
              tenant_integrations (
                id,
                integration_key,
                is_enabled
              )
            `,
            )
            .eq("patient_id", id)
            .eq("tenant_id", currentTenantId)
            .order("created_at", { ascending: true });

          if (error) throw error;
          return data as unknown as PatientProviderPlatformLink[];
        },
        enabled: !!id && !!currentTenantId,
      },
      {
        queryKey: ["platform-integrations", "provider-platforms"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("platform_integrations")
            .select("key, name, category, is_active")
            .eq("category", "provider_platform")
            .eq("is_active", true)
            .order("name", { ascending: true });

          if (error) throw error;
          return data as PlatformIntegration[];
        },
      },
    ],
  });

  const {
    data: tenantProviderIntegrations = [],
    isLoading: isLoadingTenantIntegrations,
  } = useQuery({
    queryKey: [
      "tenant-integrations",
      currentTenantId,
      "provider-platform-links",
      providerPlatformIntegrations
        .map((integration) => integration.key)
        .join(","),
    ],
    queryFn: async () => {
      if (!currentTenantId) return [];
      const providerIntegrationKeys = providerPlatformIntegrations.map(
        (integration) => integration.key,
      );
      if (providerIntegrationKeys.length === 0) return [];

      const { data, error } = await supabase
        .from("tenant_integrations")
        .select("id, integration_key, is_enabled")
        .eq("tenant_id", currentTenantId)
        .eq("is_enabled", true)
        .in("integration_key", providerIntegrationKeys)
        .order("integration_key", { ascending: true });

      if (error) throw error;
      return data as ProviderIntegrationOption[];
    },
    enabled: !!currentTenantId,
  });

  const updateMutation = useMutation({
    mutationFn: async (data: PatientUpdateData) => {
      if (!id) throw new Error("Patient ID is required");

      const beforeData = patient;
      const sanitizedData =
        "phone" in data
          ? {
              ...data,
              phone: normalizePhoneDigits(data.phone || "") || null,
            }
          : data;

      // If email is being changed, use the edge function to update both patient and auth user
      if (sanitizedData.email && sanitizedData.email !== patient?.email) {
        const { data: session } = await supabase.auth.getSession();
        const response = await fetch(
          `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/update-patient-email`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Authorization: `Bearer ${session?.session?.access_token}`,
            },
            body: JSON.stringify({
              patientId: id,
              newEmail: sanitizedData.email,
            }),
          },
        );

        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.error || "Failed to update email");
        }

        // Remove email from data since it's already updated
        const { email: _, ...restData } = sanitizedData;

        // If there are other fields to update, update them
        if (Object.keys(restData).length > 0) {
          const { data: updated, error } = await supabase
            .from("patients")
            .update(restData)
            .eq("id", id)
            .select()
            .single();

          if (error) throw error;
          return { patient: updated as Patient, beforeData };
        }

        // Refetch the patient data
        const { data: refreshed, error: refreshError } = await supabase
          .from("patients")
          .select("*")
          .eq("id", id)
          .single();

        if (refreshError) throw refreshError;
        return { patient: refreshed as Patient, beforeData };
      }

      // Standard update for non-email changes
      const { data: updated, error } = await supabase
        .from("patients")
        .update(sanitizedData)
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;
      return { patient: updated as Patient, beforeData };
    },
    onSuccess: ({ patient: updated, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["patient", id] });
      queryClient.invalidateQueries({ queryKey: ["patients"] });
      logAction({
        action: "update",
        entityType: "patient",
        entityId: id!,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: updated as unknown as Record<string, unknown>,
      });
      toast.success("Patient updated successfully");
      setIsEditingProfile(false);
      setIsEditingShipping(false);
      setIsEditingBilling(false);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update patient",
      );
    },
  });

  const saveProviderLinkMutation = useMutation({
    mutationFn: async () => {
      if (!id) throw new Error("Patient ID is required");
      if (!currentTenantId) throw new Error("Tenant ID is required");

      const providerPatientId = providerLinkForm.provider_patient_id.trim();
      if (!providerLinkForm.tenant_integration_id) {
        throw new Error("Integration is required");
      }
      if (!providerPatientId) {
        throw new Error("Provider patient ID is required");
      }

      if (editingProviderLinkId) {
        const { data, error } = await supabase
          .from("patient_provider_platform_links")
          .update({
            provider_patient_id: providerPatientId,
          })
          .eq("id", editingProviderLinkId)
          .eq("patient_id", id)
          .eq("tenant_id", currentTenantId)
          .select(
            `
            id,
            patient_id,
            tenant_id,
            tenant_integration_id,
            provider_patient_id,
            metadata,
            created_at,
            updated_at,
            tenant_integrations (
              id,
              integration_key,
              is_enabled
            )
          `,
          )
          .single();

        if (error) throw error;
        return data as unknown as PatientProviderPlatformLink;
      }

      const { data, error } = await supabase
        .from("patient_provider_platform_links")
        .insert({
          patient_id: id,
          tenant_id: currentTenantId,
          tenant_integration_id: providerLinkForm.tenant_integration_id,
          provider_patient_id: providerPatientId,
        })
        .select(
          `
          id,
          patient_id,
          tenant_id,
          tenant_integration_id,
          provider_patient_id,
          metadata,
          created_at,
          updated_at,
          tenant_integrations (
            id,
            integration_key,
            is_enabled
          )
        `,
        )
        .single();

      if (error) throw error;
      return data as unknown as PatientProviderPlatformLink;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["patient-provider-platform-links", id],
      });
      toast.success(
        editingProviderLinkId
          ? "Provider patient ID updated"
          : "Provider patient ID saved",
      );
      setIsEditingProviderLink(false);
      setEditingProviderLinkId(null);
      setProviderLinkForm({
        tenant_integration_id: "",
        provider_patient_id: "",
      });
      setProviderLinkError(null);
    },
    onError: (error) => {
      const message =
        error instanceof Error
          ? error.message
          : "Failed to save provider patient ID";
      setProviderLinkError(message);
      toast.error(message);
    },
  });

  const deleteProviderLinkMutation = useMutation({
    mutationFn: async (link: PatientProviderPlatformLink) => {
      const { error } = await supabase
        .from("patient_provider_platform_links")
        .delete()
        .eq("id", link.id)
        .eq("patient_id", link.patient_id)
        .eq("tenant_id", link.tenant_id);

      if (error) throw error;
      return link;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["patient-provider-platform-links", id],
      });
      toast.success("Provider patient ID removed");
      if (editingProviderLinkId) {
        setIsEditingProviderLink(false);
        setEditingProviderLinkId(null);
        setProviderLinkForm({
          tenant_integration_id: "",
          provider_patient_id: "",
        });
        setProviderLinkError(null);
      }
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to remove provider patient ID",
      );
    },
  });

  // Delete patient mutation
  const deleteMutation = useMutation({
    mutationFn: async () => {
      if (!id) throw new Error("Patient ID is required");

      const beforeData = patient;
      const { error } = await supabase.from("patients").delete().eq("id", id);

      if (error) throw error;
      return beforeData;
    },
    onSuccess: (beforeData) => {
      logAction({
        action: "delete",
        entityType: "patient",
        entityId: id!,
        beforeData: beforeData as unknown as Record<string, unknown>,
      });
      toast.success("Patient deleted successfully");
      navigate(ROUTES.TENANT_ADMIN.PATIENTS);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to delete patient",
      );
    },
  });

  const handleEditProfile = () => {
    if (patient) {
      setEditForm({
        first_name: patient.first_name,
        last_name: patient.last_name,
        email: patient.email,
        phone: patient.phone,
        date_of_birth: patient.date_of_birth,
        starting_weight: patient.starting_weight,
        target_weight: patient.target_weight,
        access_status: patient.access_status,
      });
      setFormErrors({});
      setIsEditingProfile(true);
    }
  };

  const handleEditShipping = () => {
    if (isLoadingAvailableShippingStates || availableShippingStatesError) {
      toast.error("Available shipping states could not be loaded.");
      return;
    }

    if (patient) {
      const currentShippingState = normalizeUsStateCode(
        patient.shipping_state,
      );

      setEditForm({
        shipping_first_name: patient.shipping_first_name || patient.first_name,
        shipping_last_name: patient.shipping_last_name || patient.last_name,
        shipping_company: patient.shipping_company,
        shipping_address_line1: patient.shipping_address_line1,
        shipping_address_line2: patient.shipping_address_line2,
        shipping_city: patient.shipping_city,
        shipping_state: availableShippingStates.some(
            (state) => state.code === currentShippingState,
          )
          ? currentShippingState
          : "",
        shipping_postal_code: patient.shipping_postal_code,
        shipping_country: patient.shipping_country,
        shipping_instructions: patient.shipping_instructions,
      });
      setFormErrors({});
      setIsEditingShipping(true);
    }
  };

  const handleEditBilling = () => {
    if (patient) {
      setEditForm({
        billing_first_name: patient.billing_first_name || patient.first_name,
        billing_last_name: patient.billing_last_name || patient.last_name,
        billing_company: patient.billing_company,
        billing_address_line1: patient.billing_address_line1,
        billing_address_line2: patient.billing_address_line2,
        billing_city: patient.billing_city,
        billing_state: patient.billing_state,
        billing_postal_code: patient.billing_postal_code,
        billing_country: patient.billing_country,
      });
      setFormErrors({});
      setIsEditingBilling(true);
    }
  };

  const handleCopyFromShipping = (checked: boolean) => {
    if (checked && patient) {
      setEditForm({
        ...editForm,
        billing_first_name: patient.shipping_first_name || patient.first_name,
        billing_last_name: patient.shipping_last_name || patient.last_name,
        billing_company: patient.shipping_company,
        billing_address_line1: patient.shipping_address_line1,
        billing_address_line2: patient.shipping_address_line2,
        billing_city: patient.shipping_city,
        billing_state: patient.shipping_state,
        billing_postal_code: patient.shipping_postal_code,
        billing_country: patient.shipping_country,
      });
    }
  };

  const handleSaveProfile = () => {
    setFormErrors({});

    const validation = validatePatient({
      first_name: editForm.first_name || "",
      last_name: editForm.last_name || "",
      email: editForm.email || "",
      phone: editForm.phone || "",
      starting_weight: editForm.starting_weight ?? null,
      target_weight: editForm.target_weight ?? null,
    });

    if (validation.success === false) {
      setFormErrors(validation.errors);
      toast.error("Please fix the validation errors");
      return;
    }

    updateMutation.mutate(editForm);
  };

  const handleSaveShipping = () => {
    setFormErrors({});

    if (
      !availableShippingStates.some(
        (state) => state.code === editForm.shipping_state,
      )
    ) {
      setFormErrors({
        shipping_state: "Please select an available shipping state",
      });
      toast.error("Please select an available shipping state.");
      return;
    }

    const validation = validateShippingAddress({
      shipping_first_name: editForm.shipping_first_name || "",
      shipping_last_name: editForm.shipping_last_name || "",
      shipping_company: editForm.shipping_company || "",
      shipping_address_line1: editForm.shipping_address_line1 || "",
      shipping_address_line2: editForm.shipping_address_line2 || "",
      shipping_city: editForm.shipping_city || "",
      shipping_state: editForm.shipping_state || "",
      shipping_postal_code: editForm.shipping_postal_code || "",
      shipping_country: editForm.shipping_country || "",
      shipping_instructions: editForm.shipping_instructions || "",
    });

    if (validation.success === false) {
      setFormErrors(validation.errors);
      toast.error("Please fix the validation errors");
      return;
    }

    updateMutation.mutate(editForm);
  };

  const handleSaveBilling = () => {
    setFormErrors({});

    const validation = validateBillingAddress({
      billing_first_name: editForm.billing_first_name || "",
      billing_last_name: editForm.billing_last_name || "",
      billing_company: editForm.billing_company || "",
      billing_address_line1: editForm.billing_address_line1 || "",
      billing_address_line2: editForm.billing_address_line2 || "",
      billing_city: editForm.billing_city || "",
      billing_state: editForm.billing_state || "",
      billing_postal_code: editForm.billing_postal_code || "",
      billing_country: editForm.billing_country || "",
    });

    if (validation.success === false) {
      setFormErrors(validation.errors);
      toast.error("Please fix the validation errors");
      return;
    }

    updateMutation.mutate(editForm);
  };

  const handleStatusChange = (status: PatientAccessStatus) => {
    if (!patient) return;

    const beforeData = { ...patient };
    updateMutation.mutate(
      { access_status: status },
      {
        onSuccess: () => {
          logAction({
            action: "update",
            entityType: "patient",
            entityId: id!,
            beforeData: beforeData as unknown as Record<string, unknown>,
            afterData: {
              ...beforeData,
              access_status: status,
            } as unknown as Record<string, unknown>,
          });
        },
      },
    );
  };

  const handleAddProviderLink = () => {
    setIsEditingProviderLink(true);
    setEditingProviderLinkId(null);
    setProviderLinkForm({
      tenant_integration_id: "",
      provider_patient_id: "",
    });
    setProviderLinkError(null);
  };

  const handleEditProviderLink = (link: PatientProviderPlatformLink) => {
    setIsEditingProviderLink(true);
    setEditingProviderLinkId(link.id);
    setProviderLinkForm({
      tenant_integration_id: link.tenant_integration_id,
      provider_patient_id: link.provider_patient_id || "",
    });
    setProviderLinkError(null);
  };

  const handleCancelProviderLinkEdit = () => {
    setIsEditingProviderLink(false);
    setEditingProviderLinkId(null);
    setProviderLinkForm({
      tenant_integration_id: "",
      provider_patient_id: "",
    });
    setProviderLinkError(null);
  };

  const handleSaveProviderLink = () => {
    if (!providerLinkForm.tenant_integration_id) {
      setProviderLinkError("Integration is required");
      return;
    }

    if (!providerLinkForm.provider_patient_id.trim()) {
      setProviderLinkError("Provider patient ID is required");
      return;
    }

    setProviderLinkError(null);
    saveProviderLinkMutation.mutate();
  };

  const availableProviderIntegrations = tenantProviderIntegrations.filter(
    (integration) => {
      if (
        editingProviderLinkId &&
        integration.id === providerLinkForm.tenant_integration_id
      ) {
        return true;
      }

      return !providerLinks.some(
        (link) => link.tenant_integration_id === integration.id,
      );
    },
  );

  const providerIntegrationNameByKey = new Map(
    providerPlatformIntegrations.map((integration) => [
      integration.key,
      integration.name,
    ]),
  );

  const formatCurrency = (cents: number) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(cents / 100);
  };

  if (isLoadingPatient) {
    return (
      <AdminLayout variant="tenant">
        <div className="space-y-6">
          <Skeleton className="h-10 w-48" />
          <Skeleton className="h-64 w-full" />
        </div>
      </AdminLayout>
    );
  }

  if (!patient) {
    return (
      <AdminLayout variant="tenant">
        <div className="text-center py-12">
          <h2 className="text-xl font-semibold">Patient not found</h2>
          <p className="text-muted-foreground mt-2">
            The patient you're looking for doesn't exist.
          </p>
          <Button
            variant="outline"
            className="mt-4"
            onClick={() => navigate(ROUTES.TENANT_ADMIN.PATIENTS)}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Patients
          </Button>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title={`${patient.first_name} ${patient.last_name}`}
        description={patient.email}
        actions={
          <div className="flex items-center gap-2">
            <SendTestNotificationDialog
              patientId={patient.id}
              patientName={`${patient.first_name} ${patient.last_name}`}
            />
            <Button
              variant="outline"
              onClick={() => navigate(ROUTES.TENANT_ADMIN.PATIENTS)}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back
            </Button>
          </div>
        }
      />

      <Tabs defaultValue="profile" className="space-y-6">
        <TabsList>
          <TabsTrigger value="profile">Profile</TabsTrigger>
          <TabsTrigger value="platform-terms">
            Tenant Terms ({platformTermsAcceptances.length})
          </TabsTrigger>
          <TabsTrigger value="privacy-policy">
            Privacy Policy ({privacyPolicyAcceptances.length})
          </TabsTrigger>
          <TabsTrigger value="product-terms">
            Product Terms ({termsAcceptances.length})
          </TabsTrigger>
          <TabsTrigger value="orders">Orders ({orders.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="space-y-6">
          {/* Patient Status Card */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Access Status</CardTitle>
              <CardDescription>
                Manage patient access to the platform
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap items-center gap-4">
                <StatusBadge status={patient.access_status} />
                <Select
                  value={patient.access_status}
                  onValueChange={(value) =>
                    handleStatusChange(value as PatientAccessStatus)
                  }
                  disabled={!canEditPatient}
                >
                  <SelectTrigger className="w-48">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="suspended">Suspended</SelectItem>
                    <SelectItem value="deactivated">Deactivated</SelectItem>
                  </SelectContent>
                </Select>
                {canEditPatient && (
                  <div className="ml-auto">
                  <ChangePasswordDialog
                    patientId={patient.id}
                    patientName={`${patient.first_name} ${patient.last_name}`}
                    hasAuthAccount={!!patient.auth_user_id}
                  />
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Patient Information Card */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg">Patient Information</CardTitle>
                <CardDescription>Personal and contact details</CardDescription>
              </div>
              {!isEditingProfile ? (
                canEditPatient && (
                <Button variant="outline" onClick={handleEditProfile}>
                  Edit
                </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={() => setIsEditingProfile(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleSaveProfile}
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
              {isEditingProfile ? (
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="first_name">First Name</Label>
                    <Input
                      id="first_name"
                      value={editForm.first_name || ""}
                      onChange={(e) =>
                        setEditForm({ ...editForm, first_name: e.target.value })
                      }
                      className={
                        formErrors.first_name ? "border-destructive" : ""
                      }
                    />
                    {formErrors.first_name && (
                      <p className="text-sm text-destructive">
                        {formErrors.first_name}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="last_name">Last Name</Label>
                    <Input
                      id="last_name"
                      value={editForm.last_name || ""}
                      onChange={(e) =>
                        setEditForm({ ...editForm, last_name: e.target.value })
                      }
                      className={
                        formErrors.last_name ? "border-destructive" : ""
                      }
                    />
                    {formErrors.last_name && (
                      <p className="text-sm text-destructive">
                        {formErrors.last_name}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                      id="email"
                      type="email"
                      value={editForm.email || ""}
                      onChange={(e) =>
                        setEditForm({ ...editForm, email: e.target.value })
                      }
                      className={formErrors.email ? "border-destructive" : ""}
                    />
                    {formErrors.email && (
                      <p className="text-sm text-destructive">
                        {formErrors.email}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                      id="phone"
                      type="tel"
                      inputMode="numeric"
                      pattern="[0-9]*"
                      maxLength={10}
                      value={editForm.phone || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          phone: e.target.value.replace(/\D/g, "").slice(0, 10),
                        })
                      }
                      className={formErrors.phone ? "border-destructive" : ""}
                    />
                    {formErrors.phone && (
                      <p className="text-sm text-destructive">
                        {formErrors.phone}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="date_of_birth">Date of Birth</Label>
                    <Input
                      id="date_of_birth"
                      type="date"
                      value={editForm.date_of_birth || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          date_of_birth: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="starting_weight">Starting Weight</Label>
                    <Input
                      id="starting_weight"
                      type="number"
                      inputMode="decimal"
                      min="0"
                      step="0.1"
                      value={editForm.starting_weight ?? ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          starting_weight:
                            e.target.value === ""
                              ? null
                              : Number(e.target.value),
                        })
                      }
                      className={
                        formErrors.starting_weight ? "border-destructive" : ""
                      }
                    />
                    {formErrors.starting_weight && (
                      <p className="text-sm text-destructive">
                        {formErrors.starting_weight}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="target_weight">Target Weight</Label>
                    <Input
                      id="target_weight"
                      type="number"
                      inputMode="decimal"
                      min="0"
                      step="0.1"
                      value={editForm.target_weight ?? ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          target_weight:
                            e.target.value === ""
                              ? null
                              : Number(e.target.value),
                        })
                      }
                      className={
                        formErrors.target_weight ? "border-destructive" : ""
                      }
                    />
                    {formErrors.target_weight && (
                      <p className="text-sm text-destructive">
                        {formErrors.target_weight}
                      </p>
                    )}
                  </div>
                </div>
              ) : (
                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <p className="text-sm text-muted-foreground">First Name</p>
                    <p className="font-medium">{patient.first_name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Last Name</p>
                    <p className="font-medium">{patient.last_name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Email</p>
                    <p className="font-medium">{patient.email}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Phone</p>
                    <p className="font-medium">{patient.phone || "—"}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">
                      Date of Birth
                    </p>
                    <p className="font-medium">
                      {patient.date_of_birth
                        ? dateTime(patient.date_of_birth).format("MMM D, YYYY")
                        : "—"}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">
                      Starting Weight
                    </p>
                    <p className="font-medium">
                      {patient.starting_weight !== null &&
                      patient.starting_weight !== undefined
                        ? patient.starting_weight
                        : "—"}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">
                      Target Weight
                    </p>
                    <p className="font-medium">
                      {patient.target_weight !== null &&
                      patient.target_weight !== undefined
                        ? patient.target_weight
                        : "—"}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Created</p>
                    <p className="font-medium">
                      {dateTime(patient.created_at).format("MMM D, YYYY")}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">
                      Email Marketing
                    </p>
                    <Badge
                      variant={
                        patient.subscribed_to_email_marketing
                          ? "default"
                          : "secondary"
                      }
                    >
                      {patient.subscribed_to_email_marketing
                        ? "Subscribed"
                        : "Not subscribed"}
                    </Badge>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">
                      SMS Marketing
                    </p>
                    <Badge
                      variant={
                        patient.subscribed_to_sms_marketing
                          ? "default"
                          : "secondary"
                      }
                    >
                      {patient.subscribed_to_sms_marketing
                        ? "Subscribed"
                        : "Not subscribed"}
                    </Badge>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">
                      Last Updated
                    </p>
                    <p className="font-medium">
                      {dateTime(patient.updated_at).format("MMM D, YYYY")}
                    </p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg">Provider IDs</CardTitle>
                <CardDescription>
                  Store provider-specific patient IDs by integration
                </CardDescription>
              </div>
              {canManageProviderLinks && !isEditingProviderLink ? (
                <Button variant="outline" onClick={handleAddProviderLink}>
                  Add Provider ID
                </Button>
              ) : canManageProviderLinks ? (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={handleCancelProviderLinkEdit}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleSaveProviderLink}
                    disabled={saveProviderLinkMutation.isPending}
                  >
                    {saveProviderLinkMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    <Save className="h-4 w-4 mr-2" />
                    Save
                  </Button>
                </div>
              ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
              {isEditingProviderLink && (
                <div className="grid gap-4 rounded-lg border p-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="provider_integration">Integration</Label>
                    <Select
                      value={providerLinkForm.tenant_integration_id}
                      onValueChange={(value) =>
                        setProviderLinkForm({
                          ...providerLinkForm,
                          tenant_integration_id: value,
                        })
                      }
                      disabled={!!editingProviderLinkId}
                    >
                      <SelectTrigger id="provider_integration">
                        <SelectValue placeholder="Select integration" />
                      </SelectTrigger>
                      <SelectContent>
                        {availableProviderIntegrations.map((integration) => (
                          <SelectItem
                            key={integration.id}
                            value={integration.id}
                          >
                            {providerIntegrationNameByKey.get(
                              integration.integration_key,
                            ) || integration.integration_key}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="provider_patient_id">
                      Provider Patient ID
                    </Label>
                    <Input
                      id="provider_patient_id"
                      value={providerLinkForm.provider_patient_id}
                      onChange={(e) =>
                        setProviderLinkForm({
                          ...providerLinkForm,
                          provider_patient_id: e.target.value,
                        })
                      }
                      placeholder="Enter the patient ID used by the provider"
                    />
                  </div>
                  {providerLinkError && (
                    <p className="text-sm text-destructive md:col-span-2">
                      {providerLinkError}
                    </p>
                  )}
                </div>
              )}

              {isLoadingProviderLinks ||
              isLoadingTenantIntegrations ||
              isLoadingProviderPlatformIntegrations ? (
                <div className="space-y-3">
                  <Skeleton className="h-16 w-full" />
                  <Skeleton className="h-16 w-full" />
                </div>
              ) : providerLinks.length > 0 ? (
                <div className="space-y-3">
                  {providerLinks.map((link) => (
                    <div
                      key={link.id}
                      className="flex flex-col gap-4 rounded-lg border p-4 md:flex-row md:items-center md:justify-between"
                    >
                      <div className="space-y-2">
                        <Badge variant="secondary">
                          {providerIntegrationNameByKey.get(
                            link.tenant_integrations?.integration_key || "",
                          ) ||
                            link.tenant_integrations?.integration_key ||
                            "Unknown integration"}
                        </Badge>
                        <div>
                          <p className="text-sm text-muted-foreground">
                            Provider Patient ID
                          </p>
                          <p className="font-mono text-sm">
                            {link.provider_patient_id || "—"}
                          </p>
                        </div>
                      </div>
                      {canManageProviderLinks && (
                        <div className="flex gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleEditProviderLink(link)}
                        >
                          Edit
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() =>
                            deleteProviderLinkMutation.mutate(link)
                          }
                          disabled={deleteProviderLinkMutation.isPending}
                        >
                          Remove
                        </Button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                  No provider patient IDs stored yet.
                </div>
              )}
            </CardContent>
          </Card>

          {/* Shipping Address Card */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg">Shipping Address</CardTitle>
                <CardDescription>Delivery address for orders</CardDescription>
              </div>
              {!isEditingShipping ? (
                canEditPatient && (
                <Button
                  variant="outline"
                  onClick={handleEditShipping}
                  disabled={
                    isLoadingAvailableShippingStates ||
                    !!availableShippingStatesError
                  }
                  title={
                    availableShippingStatesError
                      ? "Available shipping states could not be loaded."
                      : undefined
                  }
                >
                  Edit
                </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={() => setIsEditingShipping(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleSaveShipping}
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
              {isEditingShipping ? (
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="shipping_first_name">First Name</Label>
                    <Input
                      id="shipping_first_name"
                      value={editForm.shipping_first_name || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_first_name: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.shipping_first_name
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.shipping_first_name && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_first_name}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_last_name">Last Name</Label>
                    <Input
                      id="shipping_last_name"
                      value={editForm.shipping_last_name || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_last_name: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.shipping_last_name
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.shipping_last_name && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_last_name}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="shipping_company">Company (optional)</Label>
                    <Input
                      id="shipping_company"
                      value={editForm.shipping_company || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_company: e.target.value,
                        })
                      }
                      maxLength={200}
                      className={
                        formErrors.shipping_company ? "border-destructive" : ""
                      }
                    />
                    {formErrors.shipping_company && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_company}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_address_line1">
                      Address Line 1
                    </Label>
                    <Input
                      id="shipping_address_line1"
                      value={editForm.shipping_address_line1 || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_address_line1: e.target.value,
                        })
                      }
                      maxLength={255}
                      className={
                        formErrors.shipping_address_line1
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.shipping_address_line1 && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_address_line1}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_address_line2">
                      Address Line 2
                    </Label>
                    <Input
                      id="shipping_address_line2"
                      value={editForm.shipping_address_line2 || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_address_line2: e.target.value,
                        })
                      }
                      maxLength={255}
                      className={
                        formErrors.shipping_address_line2
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.shipping_address_line2 && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_address_line2}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_city">City</Label>
                    <Input
                      id="shipping_city"
                      value={editForm.shipping_city || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_city: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.shipping_city ? "border-destructive" : ""
                      }
                    />
                    {formErrors.shipping_city && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_city}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_state">State</Label>
                    <Select
                      value={editForm.shipping_state || ""}
                      onValueChange={(value) =>
                        setEditForm({
                          ...editForm,
                          shipping_state: value,
                        })
                      }
                    >
                      <SelectTrigger
                        id="shipping_state"
                        className={
                          formErrors.shipping_state ? "border-destructive" : ""
                        }
                      >
                        <SelectValue placeholder="Select state" />
                      </SelectTrigger>
                      <SelectContent>
                        {availableShippingStates.map((state) => (
                          <SelectItem key={state.code} value={state.code}>
                            {state.code} - {state.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {formErrors.shipping_state && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_state}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_postal_code">Postal Code</Label>
                    <Input
                      id="shipping_postal_code"
                      value={editForm.shipping_postal_code || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_postal_code: e.target.value,
                        })
                      }
                      maxLength={20}
                      className={
                        formErrors.shipping_postal_code
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.shipping_postal_code && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_postal_code}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="shipping_country">Country</Label>
                    <Input
                      id="shipping_country"
                      value={editForm.shipping_country || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_country: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.shipping_country ? "border-destructive" : ""
                      }
                    />
                    {formErrors.shipping_country && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_country}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="shipping_instructions">
                      Delivery Instructions
                    </Label>
                    <Input
                      id="shipping_instructions"
                      placeholder="e.g., Leave at front door, ring doorbell twice"
                      value={editForm.shipping_instructions || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          shipping_instructions: e.target.value,
                        })
                      }
                      maxLength={500}
                      className={
                        formErrors.shipping_instructions
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.shipping_instructions && (
                      <p className="text-sm text-destructive">
                        {formErrors.shipping_instructions}
                      </p>
                    )}
                  </div>
                </div>
              ) : (
                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <p className="text-sm text-muted-foreground">Name</p>
                    <p className="font-medium">
                      {patient.shipping_first_name ||
                      patient.first_name ||
                      patient.shipping_last_name ||
                      patient.last_name ? (
                        <>
                          {patient.shipping_first_name || patient.first_name}{" "}
                          {patient.shipping_last_name || patient.last_name}
                          {patient.shipping_company && (
                            <span className="text-muted-foreground">
                              {" "}
                              ({patient.shipping_company})
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
                      {patient.shipping_address_line1 ? (
                        <>
                          {patient.shipping_address_line1}
                          {patient.shipping_address_line2 && (
                            <>, {patient.shipping_address_line2}</>
                          )}
                          <br />
                          {patient.shipping_city &&
                            `${patient.shipping_city}, `}
                          {patient.shipping_state}{" "}
                          {patient.shipping_postal_code}
                          {patient.shipping_country && (
                            <>
                              <br />
                              {patient.shipping_country}
                            </>
                          )}
                        </>
                      ) : (
                        "—"
                      )}
                    </p>
                  </div>
                  <div className="md:col-span-2">
                    <p className="text-sm text-muted-foreground">
                      Delivery Instructions
                    </p>
                    <p className="font-medium">
                      {patient.shipping_instructions || "—"}
                    </p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Billing Address Card */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg">Billing Address</CardTitle>
                <CardDescription>
                  Address for billing and invoices
                </CardDescription>
              </div>
              {!isEditingBilling ? (
                canEditPatient && (
                <Button variant="outline" onClick={handleEditBilling}>
                  Edit
                </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={() => setIsEditingBilling(false)}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleSaveBilling}
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
              {isEditingBilling ? (
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="flex items-center space-x-2 md:col-span-2 pb-2 border-b">
                    <Checkbox
                      id="copy_shipping"
                      onCheckedChange={handleCopyFromShipping}
                    />
                    <Label
                      htmlFor="copy_shipping"
                      className="text-sm font-normal cursor-pointer"
                    >
                      Copy from Shipping Address
                    </Label>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_first_name">First Name</Label>
                    <Input
                      id="billing_first_name"
                      value={editForm.billing_first_name || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_first_name: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.billing_first_name
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.billing_first_name && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_first_name}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_last_name">Last Name</Label>
                    <Input
                      id="billing_last_name"
                      value={editForm.billing_last_name || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_last_name: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.billing_last_name ? "border-destructive" : ""
                      }
                    />
                    {formErrors.billing_last_name && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_last_name}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="billing_company">Company (optional)</Label>
                    <Input
                      id="billing_company"
                      value={editForm.billing_company || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_company: e.target.value,
                        })
                      }
                      maxLength={200}
                      className={
                        formErrors.billing_company ? "border-destructive" : ""
                      }
                    />
                    {formErrors.billing_company && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_company}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_address_line1">
                      Address Line 1
                    </Label>
                    <Input
                      id="billing_address_line1"
                      value={editForm.billing_address_line1 || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_address_line1: e.target.value,
                        })
                      }
                      maxLength={255}
                      className={
                        formErrors.billing_address_line1
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.billing_address_line1 && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_address_line1}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_address_line2">
                      Address Line 2
                    </Label>
                    <Input
                      id="billing_address_line2"
                      value={editForm.billing_address_line2 || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_address_line2: e.target.value,
                        })
                      }
                      maxLength={255}
                      className={
                        formErrors.billing_address_line2
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.billing_address_line2 && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_address_line2}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_city">City</Label>
                    <Input
                      id="billing_city"
                      value={editForm.billing_city || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_city: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.billing_city ? "border-destructive" : ""
                      }
                    />
                    {formErrors.billing_city && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_city}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_state">State</Label>
                    <Input
                      id="billing_state"
                      value={editForm.billing_state || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_state: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.billing_state ? "border-destructive" : ""
                      }
                    />
                    {formErrors.billing_state && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_state}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_postal_code">Postal Code</Label>
                    <Input
                      id="billing_postal_code"
                      value={editForm.billing_postal_code || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_postal_code: e.target.value,
                        })
                      }
                      maxLength={20}
                      className={
                        formErrors.billing_postal_code
                          ? "border-destructive"
                          : ""
                      }
                    />
                    {formErrors.billing_postal_code && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_postal_code}
                      </p>
                    )}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="billing_country">Country</Label>
                    <Input
                      id="billing_country"
                      value={editForm.billing_country || ""}
                      onChange={(e) =>
                        setEditForm({
                          ...editForm,
                          billing_country: e.target.value,
                        })
                      }
                      maxLength={100}
                      className={
                        formErrors.billing_country ? "border-destructive" : ""
                      }
                    />
                    {formErrors.billing_country && (
                      <p className="text-sm text-destructive">
                        {formErrors.billing_country}
                      </p>
                    )}
                  </div>
                </div>
              ) : (
                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <p className="text-sm text-muted-foreground">Name</p>
                    <p className="font-medium">
                      {patient.billing_first_name ||
                      patient.first_name ||
                      patient.billing_last_name ||
                      patient.last_name ? (
                        <>
                          {patient.billing_first_name || patient.first_name}{" "}
                          {patient.billing_last_name || patient.last_name}
                          {patient.billing_company && (
                            <span className="text-muted-foreground">
                              {" "}
                              ({patient.billing_company})
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
                      {patient.billing_address_line1 ? (
                        <>
                          {patient.billing_address_line1}
                          {patient.billing_address_line2 && (
                            <>, {patient.billing_address_line2}</>
                          )}
                          <br />
                          {patient.billing_city && `${patient.billing_city}, `}
                          {patient.billing_state} {patient.billing_postal_code}
                          {patient.billing_country && (
                            <>
                              <br />
                              {patient.billing_country}
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

          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Migration</CardTitle>
              <CardDescription>
                Derived from patient metadata only
              </CardDescription>
            </CardHeader>
            <CardContent>
              <MigrationStatus
                metadata={patient.metadata}
                entityType="patient"
                createdAt={patient.created_at}
              />
            </CardContent>
          </Card>

          {canDeletePatient && (
          <Card className="border-destructive/40">
            <CardHeader>
              <CardTitle className="text-lg text-destructive">
                Delete Patient
              </CardTitle>
              <CardDescription>
                Permanently delete this patient and their associated data. This
                action is irreversible and should only be used when you are
                fully certain.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button
                    variant="destructive"
                    disabled={deleteMutation.isPending}
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete Patient
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>
                      Delete Patient Permanently?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                      This will permanently delete {patient.first_name}{" "}
                      {patient.last_name} and remove their associated data. This
                      action cannot be undone. Only continue if you are fully
                      certain.
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel disabled={deleteMutation.isPending}>
                      Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                      onClick={() => deleteMutation.mutate()}
                      disabled={deleteMutation.isPending}
                      className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                      {deleteMutation.isPending ? (
                        <>
                          <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                          Deleting
                        </>
                      ) : (
                        "Delete Patient"
                      )}
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </CardContent>
          </Card>
          )}
        </TabsContent>

        <TabsContent value="platform-terms">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">
                Accepted Tenant Terms and Conditions
              </CardTitle>
              <CardDescription>
                Tenant terms versions accepted by this patient
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingPlatformTermsAcceptances ? (
                <div className="space-y-2">
                  <Skeleton className="h-24 w-full" />
                  <Skeleton className="h-24 w-full" />
                </div>
              ) : platformTermsAcceptances.length === 0 ? (
                <div className="rounded-md border bg-muted/20 p-4">
                  <p className="font-medium">No accepted tenant terms found.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {platformTermsAcceptances.map((acceptance) => {
                    const version = Array.isArray(
                      acceptance.platform_terms_versions,
                    )
                      ? acceptance.platform_terms_versions[0]
                      : acceptance.platform_terms_versions;

                    return (
                      <div
                        key={acceptance.id}
                        className="rounded-md border bg-muted/20 p-4"
                      >
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                          <p className="text-sm font-semibold">
                            Version {acceptance.platform_terms_version}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            Accepted:{" "}
                            {dateTime(acceptance.accepted_at).format(
                              "MMM D, YYYY h:mm A",
                            )}
                          </p>
                        </div>
                        {version?.published_at && (
                          <p className="mb-2 text-xs text-muted-foreground">
                            Published:{" "}
                            {dateTime(version.published_at).format(
                              "MMM D, YYYY h:mm A",
                            )}
                          </p>
                        )}
                        <TermsPreview content={version?.content} />
                      </div>
                    );
                  })}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="privacy-policy">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">
                Accepted Privacy Policy
              </CardTitle>
              <CardDescription>
                Privacy policy versions accepted by this patient
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingPrivacyPolicyAcceptances ? (
                <div className="space-y-2">
                  <Skeleton className="h-24 w-full" />
                  <Skeleton className="h-24 w-full" />
                </div>
              ) : privacyPolicyAcceptances.length === 0 ? (
                <div className="rounded-md border bg-muted/20 p-4">
                  <p className="font-medium">No accepted privacy policy found.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {privacyPolicyAcceptances.map((acceptance) => {
                    const version = Array.isArray(
                      acceptance.privacy_policy_versions,
                    )
                      ? acceptance.privacy_policy_versions[0]
                      : acceptance.privacy_policy_versions;

                    return (
                      <div
                        key={acceptance.id}
                        className="rounded-md border bg-muted/20 p-4"
                      >
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                          <p className="text-sm font-semibold">
                            Version {acceptance.privacy_policy_version}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            Accepted:{" "}
                            {dateTime(acceptance.accepted_at).format(
                              "MMM D, YYYY h:mm A",
                            )}
                          </p>
                        </div>
                        {version?.published_at && (
                          <p className="mb-2 text-xs text-muted-foreground">
                            Published:{" "}
                            {dateTime(version.published_at).format(
                              "MMM D, YYYY h:mm A",
                            )}
                          </p>
                        )}
                        <TermsPreview content={version?.content} />
                      </div>
                    );
                  })}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="product-terms">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Accepted Product Terms</CardTitle>
              <CardDescription>
                Terms snapshots accepted by this patient per product
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingTermsAcceptances ? (
                <div className="space-y-2">
                  <Skeleton className="h-24 w-full" />
                  <Skeleton className="h-24 w-full" />
                </div>
              ) : termsAcceptances.length === 0 ? (
                <div className="rounded-md border bg-muted/20 p-4">
                  <p className="font-medium">No accepted terms found.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {termsAcceptances.map((acceptance) => {
                    const product = Array.isArray(acceptance.products)
                      ? acceptance.products[0]
                      : acceptance.products;

                    return (
                      <div
                        key={acceptance.id}
                        className="rounded-md border bg-muted/20 p-4"
                      >
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                          <p className="text-sm font-semibold">
                            Product: {product?.name || acceptance.product_id}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            Accepted:{" "}
                            {dateTime(acceptance.accepted_at).format(
                              "MMM D, YYYY h:mm A",
                            )}
                          </p>
                        </div>
                        <TermsPreview content={acceptance.accepted_content} />
                      </div>
                    );
                  })}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="orders">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Order History</CardTitle>
              <CardDescription>
                All orders placed by this patient
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingOrders ? (
                <div className="space-y-2">
                  <Skeleton className="h-12 w-full" />
                  <Skeleton className="h-12 w-full" />
                  <Skeleton className="h-12 w-full" />
                </div>
              ) : orders.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  No orders found for this patient
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Order #</TableHead>
                      <TableHead>Product</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Date</TableHead>
                      <TableHead>Renewal</TableHead>
                      <TableHead>Subscription</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Total</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {orders.map((order) => {
                      const subscriptionLabel = order.subscription
                        ? `SUB-${order.subscription.id.slice(0, 8).toUpperCase()}`
                        : null;
                      const subscriptionStatus = order.subscription?.status
                        ? order.subscription.status
                            .replace(/_/g, " ")
                            .replace(/\b\w/g, (char) => char.toUpperCase())
                        : null;

                      return (
                        <TableRow
                          key={order.id}
                          className="cursor-pointer hover:bg-muted/50"
                          onClick={() =>
                            navigate(
                              `${ROUTES.TENANT_ADMIN.ORDERS}/${order.id}`,
                            )
                          }
                        >
                          <TableCell className="font-medium">
                            {order.order_number}
                          </TableCell>
                          <TableCell className="max-w-[220px] truncate">
                            {order.product?.name ?? "—"}
                          </TableCell>
                          <TableCell>
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
                              "—"
                            )}
                          </TableCell>
                          <TableCell>
                            {dateTime(order.created_at).format("MMM D, YYYY")}
                          </TableCell>
                          <TableCell>
                            {order.subscription?.current_period_end_at
                              ? dateTime(
                                  order.subscription.current_period_end_at,
                                ).format("MMM D, YYYY")
                              : "—"}
                          </TableCell>
                          <TableCell className="max-w-[260px] truncate">
                            {subscriptionLabel ? (
                              <div className="flex flex-col">
                                <span className="font-mono text-xs">
                                  {subscriptionLabel}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                  {subscriptionStatus}
                                </span>
                              </div>
                            ) : (
                              "—"
                            )}
                          </TableCell>
                          <TableCell>
                            <OrderStatusBadge
                              status={order.order_statuses}
                              fallbackLabel="No Status"
                            />
                          </TableCell>
                          <TableCell className="text-right">
                            {formatCurrency(order.total_cents)}
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </AdminLayout>
  );
}
