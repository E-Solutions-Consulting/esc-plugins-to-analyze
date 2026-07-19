import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQueries, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from "@/hooks/useAuditLog";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, Column } from "@/components/common/DataTable";
import { ImageUpload } from "@/components/common/ImageUpload";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "sonner";
import {
  Plus,
  Loader2,
  ImageIcon,
  Eye,
  Trash2,
  Package,
  ExternalLink,
} from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { MedicationCapabilityBadges } from "@/components/features/MedicationCapabilitiesManager";
import { validateMedication, medicationFormValues } from "@/lib/validations";
import { ROUTES } from "@/lib/constants";
import { canEditResource } from "@/lib/admin-permissions";

const emptyFormData: MedicationFormData = {
  title: "",
  description: "",
  image_url: "",
  form: "tablets",
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

/** Page body without the AdminLayout wrapper (for reuse in the Catalog IA). */
export function MedicationsContent() {
  const navigate = useNavigate();
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();

  const [search, setSearch] = useState("");
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState<MedicationFormData>(emptyFormData);
  const [medicationType, setMedicationType] =
    useState<MedicationTypeOption>("none");
  const [selectedCapabilityIds, setSelectedCapabilityIds] = useState<
    Set<string>
  >(new Set());
  const [isAdvancedCapabilitiesEditing, setIsAdvancedCapabilitiesEditing] =
    useState(false);
  const [medicationToDelete, setMedicationToDelete] =
    useState<Medication | null>(null);
  const canEditMedications = canEditResource(
    { isPlatformSuperadmin, isTenantAdmin, isCustomerSupport, currentTenantId },
    "medication",
  );

  const [
    { data: availableCapabilities = [], isLoading: isLoadingCapabilities },
    { data: medications = [], isLoading },
    { data: medicationProductLinks = [] },
  ] = useQueries({
    queries: [
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
        queryKey: ["medications", currentTenantId, search],
        queryFn: async () => {
          if (!currentTenantId) return [];

          let query = supabase
            .from("medications")
            .select("*")
            .eq("tenant_id", currentTenantId)
            .order("created_at", { ascending: false });

          if (search) {
            query = query.ilike("title", `%${search}%`);
          }

          const { data, error } = await query;
          if (error) throw error;
          return data as unknown as Medication[];
        },
        enabled: !!currentTenantId,
      },
      {
        // Which medications already back a product (single or bundle). Used to
        // show "Open product" vs "Create product" per medication.
        queryKey: ["medication-product-links", currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];
          const { data, error } = await supabase
            .from("product_medications")
            .select("medication_id, product_id, products!inner(tenant_id)")
            .eq("products.tenant_id", currentTenantId);
          if (error) throw error;
          return (data ?? []) as unknown as {
            medication_id: string;
            product_id: string;
          }[];
        },
        enabled: !!currentTenantId,
      },
    ],
  });

  // First product that contains a given medication (for the "Open product" link).
  const productIdForMedication = (medicationId: string): string | null =>
    medicationProductLinks.find((link) => link.medication_id === medicationId)
      ?.product_id ?? null;

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

  const createMutation = useMutation({
    mutationFn: async ({
      data,
      capabilityIds,
    }: {
      data: MedicationFormData;
      capabilityIds: string[];
    }) => {
      const insertData = {
        title: data.title,
        description: data.description || null,
        image_url: data.image_url || null,
        form: data.form,
        tenant_id: currentTenantId,
      };

      const { data: medication, error } = await supabase
        .from("medications")
        .insert([insertData as never])
        .select()
        .single();

      if (error) throw error;

      if (capabilityIds.length > 0) {
        const assignmentData = capabilityIds.map((capabilityId) => ({
          medication_id: medication.id,
          capability_id: capabilityId,
        }));

        const { error: assignmentError } = await supabase
          .from(
            "medication_capability_assignments" as "medication_capabilities",
          )
          .insert(assignmentData as never);

        if (assignmentError) {
          await supabase.from("medications").delete().eq("id", medication.id);
          throw assignmentError;
        }
      }

      return { medication, capabilityIds };
    },
    onSuccess: ({ medication, capabilityIds }) => {
      queryClient.invalidateQueries({ queryKey: ["medications"] });
      logAction({
        action: "create",
        entityType: "medication",
        entityId: medication.id,
        afterData: {
          ...(medication as Record<string, unknown>),
          capability_ids: capabilityIds,
        },
      });
      toast.success("Medication created successfully");
      setIsCreateDialogOpen(false);
      setFormData(emptyFormData);
      setFormErrors({});
      setMedicationType("none");
      setSelectedCapabilityIds(new Set());
      setIsAdvancedCapabilitiesEditing(false);
      navigate(
        ROUTES.TENANT_ADMIN.CATALOG.MEDICATION_DETAIL.replace(
          ":id",
          medication.id,
        ),
      );
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to create medication",
      );
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (medication: Medication) => {
      const { error } = await supabase
        .from("medications")
        .delete()
        .eq("id", medication.id);
      if (error) throw error;
      return medication;
    },
    onSuccess: (medication) => {
      queryClient.invalidateQueries({ queryKey: ["medications"] });
      logAction({
        action: "delete",
        entityType: "medication",
        entityId: medication.id,
        beforeData: medication as unknown as Record<string, unknown>,
      });
      toast.success("Medication deleted successfully");
      setMedicationToDelete(null);
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to delete medication",
      );
    },
  });

  // Create a single-medication product from a medication so it is sellable and
  // can carry provider routing + questionnaires (which live at product level).
  // Reuses the same product create + payment-provider sync used by the Products
  // page, then links the medication via product_medications.
  const createProductFromMedication = useMutation({
    mutationFn: async (medication: Medication) => {
      if (!currentTenantId) throw new Error("No tenant selected");

      const { data: product, error } = await supabase
        .from("products")
        .insert([
          {
            name: medication.title,
            description: medication.description ?? null,
            price_cents: 0,
            tenant_id: currentTenantId,
          },
        ])
        .select()
        .single();
      if (error) throw error;

      // Link the medication. We do NOT sync to payment providers here: the product
      // is created as a $0 draft (Stripe rejects $0 prices), and the admin sets a
      // real price + payment config on the product page, which syncs on save.
      const { error: linkError } = await supabase
        .from("product_medications")
        .insert([
          {
            product_id: product.id,
            medication_id: medication.id,
            quantity: 1,
          },
        ]);
      if (linkError) {
        // Roll back the product so we don't leave an orphan on failure.
        await supabase.from("products").delete().eq("id", product.id);
        throw linkError;
      }

      return product;
    },
    onSuccess: (product) => {
      queryClient.invalidateQueries({ queryKey: ["products"] });
      queryClient.invalidateQueries({ queryKey: ["medication-product-links"] });
      logAction({
        action: "create",
        entityType: "product",
        entityId: product.id,
        afterData: product as unknown as Record<string, unknown>,
      });
      toast.success("Product created — set its price and payment to publish");
      navigate(
        ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(":id", product.id),
      );
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to create product",
      );
    },
  });

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
      new Set(derivedCapabilities.map((capability) => capability.id)),
    );
    setIsAdvancedCapabilitiesEditing(true);
  };

  const handleResetAdvancedCapabilities = () => {
    setSelectedCapabilityIds(
      new Set(derivedCapabilities.map((capability) => capability.id)),
    );
    setIsAdvancedCapabilitiesEditing(false);
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormErrors({});

    const validation = validateMedication(formData);
    if (validation.success === false) {
      setFormErrors(validation.errors);
      toast.error("Please fix the validation errors");
      return;
    }

    createMutation.mutate({
      data: validation.data,
      capabilityIds: isAdvancedCapabilitiesEditing
        ? Array.from(selectedCapabilityIds)
        : derivedCapabilities.map((capability) => capability.id),
    });
  };

  const columns: Column<Medication>[] = [
    {
      key: "image",
      header: "",
      cell: (med) => (
        <div className="w-10 h-10 rounded bg-muted flex items-center justify-center overflow-hidden">
          {med.image_url ? (
            <img
              src={med.image_url}
              alt={med.title}
              className="w-full h-full object-cover"
            />
          ) : (
            <ImageIcon className="h-5 w-5 text-muted-foreground" />
          )}
        </div>
      ),
      className: "w-16",
    },
    {
      key: "title",
      header: "Title",
      cell: (med) => (
        <div
          className="cursor-pointer hover:opacity-80 transition-opacity"
          onClick={() =>
            navigate(
              ROUTES.TENANT_ADMIN.CATALOG.MEDICATION_DETAIL.replace(
                ":id",
                med.id,
              ),
            )
          }
        >
          <p className="font-medium hover:underline">{med.title}</p>
          {med.description && (
            <p className="text-sm text-muted-foreground line-clamp-1">
              {med.description}
            </p>
          )}
        </div>
      ),
    },
    {
      key: "form",
      header: "Form",
      cell: (med) =>
        med.form ? (
          (medicationFormLabels[med.form] ?? med.form)
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    {
      key: "capabilities",
      header: "Capabilities",
      cell: (med) => <MedicationCapabilityBadges medicationId={med.id} />,
    },
    {
      key: "product",
      header: "Product",
      cell: (med) => {
        const linkedProductId = productIdForMedication(med.id);
        if (linkedProductId) {
          return (
            <Button
              variant="ghost"
              size="sm"
              className="h-8"
              title="This medication is sold via a product"
              onClick={() =>
                navigate(
                  ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(
                    ":id",
                    linkedProductId,
                  ),
                )
              }
            >
              <ExternalLink className="h-4 w-4 mr-1" /> Open product
            </Button>
          );
        }
        if (!canEditMedications)
          return <span className="text-muted-foreground text-sm">—</span>;
        return (
          <Button
            variant="outline"
            size="sm"
            className="h-8"
            title="Create a sellable product from this medication"
            disabled={createProductFromMedication.isPending}
            onClick={() => createProductFromMedication.mutate(med)}
          >
            {createProductFromMedication.isPending ? (
              <Loader2 className="h-4 w-4 mr-1 animate-spin" />
            ) : (
              <Package className="h-4 w-4 mr-1" />
            )}
            Create product
          </Button>
        );
      },
      className: "w-40",
    },
    {
      key: "actions",
      header: "",
      cell: (med) => (
        <div className="flex justify-end gap-1">
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-8 p-0"
            aria-label={`Open ${med.title}`}
            title="Open"
            onClick={() =>
              navigate(
                ROUTES.TENANT_ADMIN.CATALOG.MEDICATION_DETAIL.replace(
                  ":id",
                  med.id,
                ),
              )
            }
          >
            <Eye className="h-4 w-4" />
          </Button>
          {canEditMedications && (
            <Button
              variant="outline"
              size="sm"
              className="h-8 w-8 p-0 text-destructive"
              aria-label={`Delete ${med.title}`}
              title="Delete"
              onClick={() => setMedicationToDelete(med)}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          )}
        </div>
      ),
      className: "w-24",
    },
  ];

  return (
    <>
      <PageHeader
        title="Medications"
        description="Manage your tenant medication catalog"
        actions={
          canEditMedications && (
            <Dialog
              open={isCreateDialogOpen}
              onOpenChange={(open) => {
                setIsCreateDialogOpen(open);
                setFormData(emptyFormData);
                setFormErrors({});
                setMedicationType("none");
                setSelectedCapabilityIds(new Set());
                setIsAdvancedCapabilitiesEditing(false);
              }}
            >
              <DialogTrigger asChild>
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Add Medication
                </Button>
              </DialogTrigger>
              <DialogContent className="max-h-[85vh] overflow-y-auto">
                <form onSubmit={handleCreateSubmit}>
                  <DialogHeader>
                    <DialogTitle>Add New Medication</DialogTitle>
                    <DialogDescription>
                      Add a medication to your tenant catalog.
                    </DialogDescription>
                  </DialogHeader>

                  <div className="grid gap-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="title">Title *</Label>
                      <Input
                        id="title"
                        value={formData.title}
                        onChange={(e) =>
                          setFormData({ ...formData, title: e.target.value })
                        }
                        maxLength={100}
                        placeholder="Enter medication title"
                        className={formErrors.title ? "border-destructive" : ""}
                      />
                      {formErrors.title && (
                        <p className="text-sm text-destructive">
                          {formErrors.title}
                        </p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="description">Description</Label>
                      <Textarea
                        id="description"
                        value={formData.description}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            description: e.target.value,
                          })
                        }
                        maxLength={500}
                        placeholder="Enter medication description"
                        rows={3}
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

                    <div className="space-y-2">
                      <Label htmlFor="form">Form *</Label>
                      <p className="text-sm text-muted-foreground">
                        Please select the form of medication.
                      </p>
                      <Select
                        value={formData.form}
                        onValueChange={(value) =>
                          setFormData({
                            ...formData,
                            form: value as MedicationFormData["form"],
                          })
                        }
                      >
                        <SelectTrigger
                          id="form"
                          className={
                            formErrors.form ? "border-destructive" : ""
                          }
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
                      <Label htmlFor="medication-type">
                        What type of medication is this?
                      </Label>
                      <p className="text-sm text-muted-foreground">
                        If you&apos;re not finding the right type, select
                        Undefined and reach out to a superadmin to set it up.
                      </p>
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

                    <div className="space-y-2">
                      <Label>Image</Label>
                      <ImageUpload
                        bucket="medication-images"
                        folder={currentTenantId || ""}
                        value={formData.image_url || null}
                        onChange={(url) =>
                          setFormData({ ...formData, image_url: url || "" })
                        }
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
                        {!isLoadingCapabilities &&
                          !isAdvancedCapabilitiesEditing && (
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
                      {isLoadingCapabilities ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                          <Loader2 className="h-4 w-4 animate-spin" />
                          Loading capabilities...
                        </div>
                      ) : (
                        <>
                          <div className="rounded-md border bg-muted/10 p-3 space-y-2">
                            <p className="text-sm text-muted-foreground">
                              Tracking capabilities are configured automatically
                              based on the form and medication type.
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
                                  Use this only if you need to customize
                                  capabilities further.
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
                                      checked={selectedCapabilityIds.has(
                                        capability.id,
                                      )}
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
                                Advanced users can edit capabilities manually if
                                they need to customize this medication further.
                              </p>
                            </div>
                          )}
                        </>
                      )}
                    </div>
                  </div>

                  <DialogFooter>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => {
                        setIsCreateDialogOpen(false);
                        setFormData(emptyFormData);
                        setFormErrors({});
                        setMedicationType("none");
                        setSelectedCapabilityIds(new Set());
                        setIsAdvancedCapabilitiesEditing(false);
                      }}
                    >
                      Cancel
                    </Button>
                    <Button type="submit" disabled={createMutation.isPending}>
                      {createMutation.isPending && (
                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                      )}
                      Add Medication
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          )
        }
      />

      <DataTable
        columns={columns}
        data={medications}
        isLoading={isLoading}
        searchPlaceholder="Search medications..."
        searchValue={search}
        onSearchChange={setSearch}
        emptyMessage="No medications found"
      />

      <AlertDialog
        open={!!medicationToDelete}
        onOpenChange={(open) => !open && setMedicationToDelete(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Medication</AlertDialogTitle>
            <AlertDialogDescription>
              This permanently deletes{" "}
              <span className="font-medium">{medicationToDelete?.title}</span>{" "}
              and cannot be undone. Products linked to it may be affected.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={(e) => {
                e.preventDefault();
                if (medicationToDelete)
                  deleteMutation.mutate(medicationToDelete);
              }}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending && (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              )}
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

/** Default route wrapper — keeps the old /catalog/medications route working. */
export default function Medications() {
  return (
    <AdminLayout variant="tenant">
      <MedicationsContent />
    </AdminLayout>
  );
}
