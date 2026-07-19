import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useSearchParams } from "react-router-dom";
import { useQueries, useMutation, useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { toast } from "sonner";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "@/hooks/useAuditLog";
import { validateMedication, medicationFormValues } from "@/lib/validations";
import { ROUTES } from "@/lib/constants";
import { dateTime } from "@/lib/dayjs";
import { canEditResource } from "@/lib/admin-permissions";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { ScrollableTextPreview } from "@/components/common/ScrollableTextPreview";
import { MedicationCapabilityBadges } from "@/components/features/MedicationCapabilitiesManager";
import { ImageUpload } from "@/components/common/ImageUpload";
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
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
import { Skeleton } from "@/components/ui/skeleton";
import { ArrowLeft, ImageIcon, Loader2, Save, Trash2 } from "lucide-react";

const normalizeMedicationForm = (
  form: string | null | undefined,
): MedicationFormData["form"] => {
  if (form === "injectible_solution") {
    return "injectable_solution";
  }

  if (form === "injectable_solution" || form === "spray") {
    return form;
  }

  return "tablets";
};

const medicationFormLabels: Record<
  (typeof medicationFormValues)[number],
  string
> = {
  tablets: "Tablets",
  injectable_solution: "Injectable Solution",
  spray: "Spray",
};

const medicationFormOptions = medicationFormValues.map((value) => ({
  value,
  label: medicationFormLabels[value],
}));

type MedicationTypeOption = "none" | "weight_loss" | "energy_booster";

interface CapabilityRule {
  label: string;
  keys: string[];
  names: string[];
}

const medicationTypeOptions: Array<{
  value: MedicationTypeOption;
  label: string;
}> = [
  { value: "none", label: "Undefined" },
  { value: "weight_loss", label: "Is Weight Loss Medication" },
  { value: "energy_booster", label: "Is an Energy Booster" },
];

const alwaysEnabledCapabilityRules: CapabilityRule[] = [
  {
    label: "Mood Tracker",
    keys: ["mood_tracking"],
    names: ["Mood Tracker"],
  },
  {
    label: "Symptoms Tracker",
    keys: ["symptoms_tracking"],
    names: ["Symptoms Tracker"],
  },
];

const capabilityRuleByForm: Partial<
  Record<(typeof medicationFormValues)[number], CapabilityRule>
> = {
  injectable_solution: {
    label: "Shot Counter",
    keys: ["shot_counter"],
    names: ["Shot Counter"],
  },
  tablets: {
    label: "Pill Counter",
    keys: ["pill_counter"],
    names: ["Pill Counter"],
  },
};

const capabilityRulesByMedicationType: Partial<
  Record<MedicationTypeOption, CapabilityRule[]>
> = {
  weight_loss: [
    {
      label: "Weight Tracker",
      keys: ["weight_tracker", "weight_tracking"],
      names: ["Weight Tracker"],
    },
    {
      label: "Body Measurement",
      keys: ["body_measurement"],
      names: ["Body Measurement"],
    },
  ],
  energy_booster: [
    {
      label: "Energy Tracker",
      keys: ["energy_tracker", "energy_tracking"],
      names: ["Energy Tracker"],
    },
  ],
};

interface MedicationCapabilityOption {
  id: string;
  name: string;
  key: string;
  description: string | null;
  display_order: number;
}

function findCapabilityMatch(
  capabilities: MedicationCapabilityOption[],
  rule: CapabilityRule,
): MedicationCapabilityOption | null {
  const normalizedKeys = rule.keys.map((value) => value.toLowerCase());
  const normalizedNames = rule.names.map((value) => value.toLowerCase());

  const match = capabilities.find(
    (capability) =>
      normalizedKeys.includes(capability.key.toLowerCase()) ||
      normalizedNames.includes(capability.name.toLowerCase()),
  );

  return match ?? null;
}

function getDerivedCapabilities(
  capabilities: MedicationCapabilityOption[],
  form: MedicationFormData["form"],
  medicationType: MedicationTypeOption,
): MedicationCapabilityOption[] {
  const rules = [
    ...alwaysEnabledCapabilityRules,
    ...(capabilityRuleByForm[form] ? [capabilityRuleByForm[form]!] : []),
    ...(capabilityRulesByMedicationType[medicationType] ?? []),
  ];

  const selected = new Map<string, MedicationCapabilityOption>();
  rules.forEach((rule) => {
    const capability = findCapabilityMatch(capabilities, rule);
    if (capability) {
      selected.set(capability.id, capability);
    }
  });

  return Array.from(selected.values());
}

function inferMedicationType(
  capabilities: MedicationCapabilityOption[],
  assignedCapabilityIds: string[],
): MedicationTypeOption {
  const assignedIds = new Set(assignedCapabilityIds);

  const weightCapabilities = (capabilityRulesByMedicationType.weight_loss ?? [])
    .map((rule) => findCapabilityMatch(capabilities, rule))
    .filter((capability): capability is MedicationCapabilityOption => Boolean(capability));
  if (weightCapabilities.some((capability) => assignedIds.has(capability.id))) {
    return "weight_loss";
  }

  const energyCapabilities = (capabilityRulesByMedicationType.energy_booster ?? [])
    .map((rule) => findCapabilityMatch(capabilities, rule))
    .filter((capability): capability is MedicationCapabilityOption => Boolean(capability));
  if (energyCapabilities.some((capability) => assignedIds.has(capability.id))) {
    return "energy_booster";
  }

  return "none";
}

function areStringSetsEqual(left: Set<string>, right: Set<string>): boolean {
  if (left.size !== right.size) return false;
  for (const value of left) {
    if (!right.has(value)) return false;
  }
  return true;
}

function isSameMedicationFormData(
  left: MedicationFormData,
  right: MedicationFormData,
): boolean {
  return (
    left.title === right.title &&
    left.description === right.description &&
    left.image_url === right.image_url &&
    left.form === right.form
  );
}

const emptyFormData: MedicationFormData = {
  title: "",
  description: "",
  image_url: "",
  form: "tablets",
};

export default function MedicationDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();

  const [editingSection, setEditingSection] = useState<
    "details" | "provider-platform-integrations" | null
  >(null);
  const requestedTab = searchParams.get("tab");
  const [activeTab, setActiveTab] = useState(
    requestedTab === "provider-platform-integrations"
      ? "provider-platform-integrations"
      : "details",
  );
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState<MedicationFormData>(emptyFormData);
  const [offeringIdDraft, setOfferingIdDraft] = useState("");
  const [medicationType, setMedicationType] =
    useState<MedicationTypeOption>("none");
  const [selectedCapabilityIds, setSelectedCapabilityIds] = useState<
    Set<string>
  >(new Set());
  const [isAdvancedCapabilitiesEditing, setIsAdvancedCapabilitiesEditing] =
    useState(false);
  const canEditMedications = canEditResource(
    { isPlatformSuperadmin, isTenantAdmin, isCustomerSupport, currentTenantId },
    "medication",
  );
  const isEditingDetails = editingSection === "details";
  const isEditingProviderPlatformIntegrations =
    editingSection === "provider-platform-integrations";

  const [
    { data: medication, isLoading },
    { data: availableCapabilities = [], isLoading: isLoadingCapabilities },
    {
      data: assignedCapabilityIdsData,
      isLoading: isLoadingAssignedCapabilities,
    },
  ] = useQueries({
    queries: [
      {
        queryKey: ["medication", id],
        queryFn: async () => {
          if (!id) throw new Error("Medication ID is required");

          const { data, error } = await supabase
            .from("medications")
            .select("*")
            .eq("id", id)
            .maybeSingle();

          if (error) throw error;
          return data as unknown as Medication | null;
        },
        enabled: !!id,
      },
      {
        queryKey: ["medication-capabilities-active"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("medication_capabilities")
            .select("id, name, key, description, display_order")
            .eq("is_active", true)
            .order("display_order", { ascending: true })
            .order("name", { ascending: true });

          if (error) throw error;
          return data as MedicationCapabilityOption[];
        },
      },
      {
        queryKey: ["medication-capability-assignments", id],
        queryFn: async () => {
          if (!id) return [];

          const { data, error } = await supabase
            .from(
              "medication_capability_assignments" as "medication_capabilities",
            )
            .select("capability_id")
            .eq("medication_id" as "id", id);

          if (error) throw error;
          return (data as unknown as Array<{ capability_id: string }>).map(
            (assignment) => assignment.capability_id,
          );
        },
        enabled: !!id,
      },
    ],
  });
  const assignedCapabilityIds = assignedCapabilityIdsData ?? [];

  useEffect(() => {
    if (!medication || editingSection !== null) return;

    const nextFormData: MedicationFormData = {
      title: medication.title,
      description: medication.description || "",
      image_url: medication.image_url || "",
      form: normalizeMedicationForm(medication.form),
    };
    setFormData((current) =>
      isSameMedicationFormData(current, nextFormData) ? current : nextFormData,
    );
    setOfferingIdDraft((current) =>
      current === (medication.offering_id || "")
        ? current
        : medication.offering_id || "",
    );
  }, [editingSection, medication]);

  useEffect(() => {
    if (editingSection !== null || !assignedCapabilityIdsData) return;
    const nextMedicationType = inferMedicationType(
      availableCapabilities,
      assignedCapabilityIdsData,
    );
    setMedicationType((current) =>
      current === nextMedicationType ? current : nextMedicationType,
    );
    const nextSelectedCapabilityIds = new Set(assignedCapabilityIdsData);
    setSelectedCapabilityIds((current) =>
      areStringSetsEqual(current, nextSelectedCapabilityIds)
        ? current
        : nextSelectedCapabilityIds,
    );
  }, [assignedCapabilityIdsData, availableCapabilities, editingSection]);

  const derivedCapabilities = getDerivedCapabilities(
    availableCapabilities,
    formData.form,
    medicationType,
  );
  const derivedCapabilityNames = derivedCapabilities.map(
    (capability) => capability.name,
  );
  const selectedCapabilities = availableCapabilities.filter((capability) =>
    selectedCapabilityIds.has(capability.id),
  );
  const displayedCapabilityNames = isAdvancedCapabilitiesEditing
    ? selectedCapabilities.map((capability) => capability.name)
    : derivedCapabilityNames;

  const updateMutation = useMutation({
    mutationFn: async ({
      data,
      capabilityIds,
    }: {
      data: MedicationFormData;
      capabilityIds: string[];
    }) => {
      if (!id) throw new Error("Medication ID is required");

      const beforeData = medication;
      const beforeCapabilityIds = assignedCapabilityIds;

      const updateData = {
        title: data.title,
        description: data.description || null,
        image_url: data.image_url || null,
        form: data.form,
      };

      const { data: updatedMedication, error: updateError } = await supabase
        .from("medications")
        .update(updateData)
        .eq("id", id)
        .select()
        .single();

      if (updateError) throw updateError;

      const { error: deleteAssignmentsError } = await supabase
        .from("medication_capability_assignments" as "medication_capabilities")
        .delete()
        .eq("medication_id" as "id", id);

      if (deleteAssignmentsError) throw deleteAssignmentsError;

      if (capabilityIds.length > 0) {
        const assignmentData = capabilityIds.map((capabilityId) => ({
          medication_id: id,
          capability_id: capabilityId,
        }));

        const { error: insertAssignmentsError } = await supabase
          .from(
            "medication_capability_assignments" as "medication_capabilities",
          )
          .insert(assignmentData as never);

        if (insertAssignmentsError) throw insertAssignmentsError;
      }

      return {
        medication: updatedMedication as unknown as Medication,
        beforeData,
        beforeCapabilityIds,
        afterCapabilityIds: capabilityIds,
      };
    },
    onSuccess: ({
      medication: updatedMedication,
      beforeData,
      beforeCapabilityIds,
      afterCapabilityIds,
    }) => {
      queryClient.invalidateQueries({ queryKey: ["medication", id] });
      queryClient.invalidateQueries({ queryKey: ["medications"] });
      queryClient.invalidateQueries({
        queryKey: ["product-provider-platform-linked-medications"],
      });
      queryClient.invalidateQueries({
        queryKey: ["medication-capability-assignments", id],
      });
      queryClient.invalidateQueries({
        queryKey: ["medication-capability-assignments-with-names", id],
      });

      logAction({
        action: "update",
        entityType: "medication",
        entityId: updatedMedication.id,
        beforeData: {
          ...(beforeData as unknown as Record<string, unknown>),
          capability_ids: beforeCapabilityIds,
        },
        afterData: {
          ...(updatedMedication as unknown as Record<string, unknown>),
          capability_ids: afterCapabilityIds,
        },
      });

      toast.success("Medication updated successfully");
      setEditingSection(null);
      setFormErrors({});
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to update medication",
      );
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async () => {
      if (!id) throw new Error("Medication ID is required");

      const beforeData = medication;
      const beforeCapabilityIds = assignedCapabilityIds;

      const { error } = await supabase
        .from("medications")
        .delete()
        .eq("id", id);

      if (error) throw error;
      return { beforeData, beforeCapabilityIds };
    },
    onSuccess: ({ beforeData, beforeCapabilityIds }) => {
      logAction({
        action: "delete",
        entityType: "medication",
        entityId: id!,
        beforeData: {
          ...(beforeData as unknown as Record<string, unknown>),
          capability_ids: beforeCapabilityIds,
        },
      });

      queryClient.invalidateQueries({ queryKey: ["medications"] });
      toast.success("Medication deleted successfully");
      navigate(ROUTES.TENANT_ADMIN.CATALOG.MEDICATIONS);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to delete medication",
      );
    },
  });

  const updateProviderPlatformSettingsMutation = useMutation({
    mutationFn: async () => {
      if (!id) throw new Error("Medication ID is required");

      const normalizedOfferingId = offeringIdDraft.trim();
      if (normalizedOfferingId.length > 100) {
        throw new Error("Offering ID must be 100 characters or less");
      }

      const beforeData = medication;
      const { data: updatedMedication, error } = await supabase
        .from("medications")
        .update({ offering_id: normalizedOfferingId || null })
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;

      return {
        medication: updatedMedication as unknown as Medication,
        beforeData,
      };
    },
    onSuccess: ({ medication: updatedMedication, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["medication", id] });
      queryClient.invalidateQueries({ queryKey: ["medications"] });
      queryClient.invalidateQueries({
        queryKey: ["product-provider-platform-linked-medications"],
      });

      logAction({
        action: "update",
        entityType: "medication",
        entityId: updatedMedication.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: updatedMedication as unknown as Record<string, unknown>,
      });

      toast.success("Provider platform settings saved");
      setEditingSection(null);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to save provider platform settings",
      );
    },
  });

  const handleFormChange = (form: MedicationFormData["form"]) => {
    setFormData((prev) => ({ ...prev, form }));
  };

  const handleToggleCapability = (capabilityId: string) => {
    setSelectedCapabilityIds((prev) => {
      const next = new Set(prev);
      if (next.has(capabilityId)) {
        next.delete(capabilityId);
      } else {
        next.add(capabilityId);
      }
      return next;
    });
  };

  const handleStartAdvancedCapabilitiesEdit = () => {
    setSelectedCapabilityIds(
      new Set(
        derivedCapabilities.map((capability) => capability.id),
      ),
    );
    setIsAdvancedCapabilitiesEditing(true);
  };

  const handleResetAdvancedCapabilities = () => {
    setSelectedCapabilityIds(
      new Set(
        derivedCapabilities.map((capability) => capability.id),
      ),
    );
    setIsAdvancedCapabilitiesEditing(false);
  };

  const handleEdit = (
    section: "details" | "provider-platform-integrations",
  ) => {
    if (!medication) return;

    setFormData({
      title: medication.title,
      description: medication.description || "",
      image_url: medication.image_url || "",
      form: normalizeMedicationForm(medication.form),
    });
    setMedicationType(
      inferMedicationType(availableCapabilities, assignedCapabilityIds),
    );
    setSelectedCapabilityIds(new Set(assignedCapabilityIds));
    setOfferingIdDraft(medication.offering_id || "");
    setIsAdvancedCapabilitiesEditing(false);
    setFormErrors({});
    setEditingSection(section);
  };

  const handleCancel = () => {
    if (!medication) return;

    setFormData({
      title: medication.title,
      description: medication.description || "",
      image_url: medication.image_url || "",
      form: normalizeMedicationForm(medication.form),
    });
    setMedicationType(
      inferMedicationType(availableCapabilities, assignedCapabilityIds),
    );
    setSelectedCapabilityIds(new Set(assignedCapabilityIds));
    setOfferingIdDraft(medication.offering_id || "");
    setIsAdvancedCapabilitiesEditing(false);
    setFormErrors({});
    setEditingSection(null);
  };

  const handleSave = () => {
    const validation = validateMedication(formData);
    if (validation.success === false) {
      setFormErrors(validation.errors);
      toast.error("Please fix the validation errors");
      return;
    }

    updateMutation.mutate({
      data: validation.data,
      capabilityIds: isAdvancedCapabilitiesEditing
        ? Array.from(selectedCapabilityIds)
        : derivedCapabilities.map((capability) => capability.id),
    });
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

  if (!medication) {
    return (
      <AdminLayout variant="tenant">
        <div className="text-center py-12">
          <ImageIcon className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
          <h2 className="text-xl font-semibold">Medication not found</h2>
          <p className="text-muted-foreground mt-2">
            The medication you&apos;re looking for doesn&apos;t exist.
          </p>
          <Button
            variant="outline"
            className="mt-4"
            onClick={() => navigate(ROUTES.TENANT_ADMIN.CATALOG.MEDICATIONS)}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Medications
          </Button>
        </div>
      </AdminLayout>
    );
  }

  const isSaving = updateMutation.isPending;
  const isCapabilitiesLoading =
    isLoadingCapabilities || isLoadingAssignedCapabilities;

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title={medication.title}
        description="Medication details"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() => navigate(ROUTES.TENANT_ADMIN.CATALOG.MEDICATIONS)}
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back
            </Button>
            {canEditMedications && (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="destructive">
                  <Trash2 className="h-4 w-4 mr-2" />
                  Delete
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Delete Medication</AlertDialogTitle>
                  <AlertDialogDescription>
                    Are you sure you want to delete this medication? This action
                    cannot be undone.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => deleteMutation.mutate()}
                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    disabled={deleteMutation.isPending}
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

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList>
          <TabsTrigger value="details">Details</TabsTrigger>
          <TabsTrigger value="provider-platform-integrations">
            Provider Platform Integrations
          </TabsTrigger>
        </TabsList>

        <TabsContent value="details">
      <Card>
        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle className="text-lg">Medication Information</CardTitle>
            <CardDescription>
              Manage medication details, image, and capabilities
            </CardDescription>
          </div>
          {!isEditingDetails ? (
            canEditMedications && (
            <Button
              variant="outline"
              onClick={() => handleEdit("details")}
              disabled={editingSection !== null}
            >
              Edit
            </Button>
            )
          ) : (
            <div className="flex gap-2">
              <Button variant="outline" onClick={handleCancel}>
                Cancel
              </Button>
              <Button
                onClick={handleSave}
                disabled={isSaving || isCapabilitiesLoading}
              >
                {isSaving && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                <Save className="h-4 w-4 mr-2" />
                Save
              </Button>
            </div>
          )}
        </CardHeader>
            <CardContent className="space-y-6">
              {isEditingDetails ? (
                <div className="grid gap-6">
                  <div className="space-y-2">
                    <Label htmlFor="title">Title *</Label>
                    <Input
                      id="title"
                      value={formData.title}
                      onChange={(e) =>
                        setFormData({ ...formData, title: e.target.value })
                      }
                      maxLength={100}
                      className={formErrors.title ? "border-destructive" : ""}
                    />
                    {formErrors.title && (
                      <p className="text-sm text-destructive">{formErrors.title}</p>
                    )}
                  </div>

                  <div className="grid gap-6 md:grid-cols-2 md:items-start">
                    <div className="space-y-2">
                      <div className="flex min-h-[4.75rem] flex-col justify-between gap-1">
                        <Label htmlFor="form">Form *</Label>
                        <p className="text-sm text-muted-foreground">
                          Please select the form of medication.
                        </p>
                      </div>
                      <Select
                        value={formData.form}
                        onValueChange={(value) =>
                          handleFormChange(value as MedicationFormData["form"])
                        }
                      >
                        <SelectTrigger
                          id="form"
                          className={formErrors.form ? "border-destructive" : ""}
                        >
                          <SelectValue placeholder="Select form" />
                        </SelectTrigger>
                        <SelectContent>
                          {medicationFormOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      {formErrors.form && (
                        <p className="text-sm text-destructive">
                          {formErrors.form}
                        </p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <div className="flex min-h-[4.75rem] flex-col justify-between gap-1">
                        <Label htmlFor="medication-type">
                          What type of medication is this?
                        </Label>
                        <p className="text-sm text-muted-foreground">
                          If you&apos;re not finding the right type, select Undefined and reach out to a
                          superadmin to set it up.
                        </p>
                      </div>
                      <Select
                        value={medicationType}
                        onValueChange={(value) =>
                          setMedicationType(value as MedicationTypeOption)
                        }
                      >
                        <SelectTrigger id="medication-type">
                          <SelectValue placeholder="Select medication type" />
                        </SelectTrigger>
                        <SelectContent>
                          {medicationTypeOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea
                      id="description"
                      value={formData.description}
                      onChange={(e) =>
                        setFormData({ ...formData, description: e.target.value })
                      }
                      maxLength={500}
                      rows={4}
                      className={formErrors.description ? "border-destructive" : ""}
                    />
                    {formErrors.description && (
                      <p className="text-sm text-destructive">
                        {formErrors.description}
                      </p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label>Image</Label>
                    <ImageUpload
                      bucket="medication-images"
                      folder={currentTenantId || ""}
                      value={formData.image_url || null}
                      onChange={(url) =>
                        setFormData({ ...formData, image_url: url || "" })
                      }
                      disabled={isSaving}
                    />
                    {formErrors.image_url && (
                      <p className="text-sm text-destructive">
                        {formErrors.image_url}
                      </p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <div className="flex items-center justify-between gap-3">
                      <Label>Capabilities</Label>
                      {!isCapabilitiesLoading && !isAdvancedCapabilitiesEditing && (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={handleStartAdvancedCapabilitiesEdit}
                        >
                          Edit Capabilities
                        </Button>
                      )}
                    </div>
                    {isCapabilitiesLoading ? (
                      <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Loading capabilities...
                      </div>
                    ) : (
                      <>
                        <div className="rounded-md border bg-muted/10 p-3 space-y-2">
                          <p className="text-sm text-muted-foreground">
                            Tracking capabilities are configured automatically based on the form and
                            medication type.
                          </p>
                          <p className="text-sm font-medium">
                            Selected options:{" "}
                            {displayedCapabilityNames.length > 0
                              ? displayedCapabilityNames.join(", ")
                              : "None"}
                          </p>
                        </div>
                        {isAdvancedCapabilitiesEditing ? (
                        <div className="space-y-3">
                          <div className="rounded-md border border-amber-200 bg-amber-50/60 p-3">
                            <p className="text-sm font-medium">
                              Advanced customization
                            </p>
                            <p className="text-sm text-muted-foreground">
                              Use this only if you need to customize capabilities further.
                            </p>
                            <div className="mt-3">
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleResetAdvancedCapabilities}
                              >
                                Use Automatic Capabilities
                              </Button>
                            </div>
                          </div>
                          <div className="rounded-md border p-3 space-y-2">
                            {availableCapabilities.map((capability) => (
                              <label
                                key={capability.id}
                                className="flex items-start gap-2 cursor-pointer"
                              >
                                <Checkbox
                                  checked={selectedCapabilityIds.has(capability.id)}
                                  onCheckedChange={() =>
                                    handleToggleCapability(capability.id)
                                  }
                                />
                                <div className="min-w-0">
                                  <p className="text-sm font-medium leading-5">
                                    {capability.name}
                                  </p>
                                  {capability.description && (
                                    <p className="text-xs text-muted-foreground">
                                      {capability.description}
                                    </p>
                                  )}
                                </div>
                              </label>
                            ))}
                          </div>
                        </div>
                      ) : (
                        <div className="rounded-md border border-dashed bg-muted/5 p-3">
                          <p className="text-sm text-muted-foreground">
                            Advanced users can edit capabilities manually if they need to customize
                            this medication further.
                          </p>
                        </div>
                        )}
                      </>
                    )}
                  </div>
                </div>
              ) : (
                <div className="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                  <div className="rounded-lg border p-4 bg-muted/20">
                    <p className="text-sm font-medium mb-3">Image</p>
                    <div className="w-full aspect-square rounded bg-muted flex items-center justify-center overflow-hidden">
                      {medication.image_url ? (
                        <img
                          src={medication.image_url}
                          alt={medication.title}
                          className="w-full h-full object-cover"
                        />
                      ) : (
                        <ImageIcon className="h-8 w-8 text-muted-foreground" />
                      )}
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">Title</p>
                        <p className="font-medium mt-1">{medication.title}</p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">Form</p>
                        <p className="font-medium mt-1">
                          {medication.form
                            ? (medicationFormLabels[medication.form] ??
                              medication.form)
                            : "—"}
                        </p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">Created</p>
                        <p className="font-medium mt-1">
                          {dateTime(medication.created_at).format("MMM D, YYYY")}
                        </p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3">
                        <p className="text-xs text-muted-foreground">
                          Medication Type
                        </p>
                        <p className="font-medium mt-1">
                          {medicationTypeOptions.find(
                            (option) => option.value === medicationType,
                          )?.label ?? "—"}
                        </p>
                      </div>
                      <div className="rounded-md border bg-muted/10 p-3 sm:col-span-2">
                        <p className="text-xs text-muted-foreground">Description</p>
                        <ScrollableTextPreview
                          value={medication.description}
                          className="mt-2"
                        />
                      </div>
                    </div>

                    <div className="border-t pt-4">
                      <p className="text-sm font-medium mb-2">Capabilities</p>
                      <MedicationCapabilityBadges medicationId={medication.id} />
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="provider-platform-integrations">
          <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle className="text-lg">Provider Platform Integrations</CardTitle>
                <CardDescription>
                  Manage medication-level provider identifiers used by provider workflows.
                </CardDescription>
              </div>
              {!isEditingProviderPlatformIntegrations ? (
                canEditMedications && (
                <Button
                  variant="outline"
                  onClick={() => handleEdit("provider-platform-integrations")}
                  disabled={editingSection !== null}
                >
                  Edit
                </Button>
                )
              ) : (
                <div className="flex gap-2">
                  <Button variant="outline" onClick={handleCancel}>
                    Cancel
                  </Button>
                  <Button
                    onClick={() => updateProviderPlatformSettingsMutation.mutate()}
                    disabled={updateProviderPlatformSettingsMutation.isPending}
                  >
                    {updateProviderPlatformSettingsMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    <Save className="h-4 w-4 mr-2" />
                    Save
                  </Button>
                </div>
              )}
            </CardHeader>
            <CardContent className="space-y-6">
              {isEditingProviderPlatformIntegrations ? (
                <div className="space-y-2">
                  <Label htmlFor="mdi-offering-id">MDI Offering ID</Label>
                  <p className="text-sm text-muted-foreground">
                    MD Integrations uses this medication-level offering ID when creating case offerings.
                  </p>
                  <Input
                    id="mdi-offering-id"
                    value={offeringIdDraft}
                    onChange={(event) => setOfferingIdDraft(event.target.value)}
                    maxLength={100}
                    placeholder="Enter the MDI offering ID"
                  />
                </div>
              ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="rounded-md border bg-muted/10 p-3">
                    <p className="text-xs text-muted-foreground">MDI Offering ID</p>
                    <p className="font-medium mt-1">
                      {medication.offering_id || "—"}
                    </p>
                  </div>
                  <div className="rounded-md border bg-muted/10 p-3">
                    <p className="text-xs text-muted-foreground">Telegra</p>
                    <p className="font-medium mt-1">Configured at product level</p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </AdminLayout>
  );
}
