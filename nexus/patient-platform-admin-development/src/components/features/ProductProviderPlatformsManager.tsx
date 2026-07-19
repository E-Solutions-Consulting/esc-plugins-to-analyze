import { useEffect, useMemo, useRef, useState } from "react";
import {
  AlertCircle,
  Building2,
  Globe,
  Loader2,
  Plug,
  Save,
  Trash2,
} from "lucide-react";
import { Link } from "react-router-dom";
import { useProductProviderPlatforms } from "@/hooks/useProductProviderPlatforms";
import { useAuth } from "@/stores/authStore";
import { ROUTES } from "@/lib/constants";
import { US_STATES } from "@/lib/usStates";
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
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";

interface ProductProviderPlatformsManagerProps {
  productId: string;
  readOnly?: boolean;
}

interface LoadBalancingDraftRow {
  id: string;
  stateCodes: string[];
  allocations: Record<string, string>;
}

const integrationIcons: Record<string, React.ReactNode> = {
  telegramd: <Globe className="h-4 w-4 text-muted-foreground" />,
  zito_care: <Building2 className="h-4 w-4 text-muted-foreground" />,
  md_integrations: <Building2 className="h-4 w-4 text-muted-foreground" />,
};

function getSkuLabel(integrationKey: string): string {
  if (integrationKey === "telegramd") return "Product Variation SKU";
  if (integrationKey === "md_integrations") return "Medication ID";
  return "Provider SKU";
}

function getSkuPlaceholder(integrationKey: string): string {
  if (integrationKey === "telegramd") {
    return "Enter the SKU used by this product variation in Telegra";
  }
  if (integrationKey === "md_integrations") {
    return "Enter the medication ID used by MDI";
  }
  return "Enter the SKU used by this provider platform";
}

function usesSeparateProductSku(integrationKey: string): boolean {
  return integrationKey === "telegramd";
}

function usesVariationSku(integrationKey: string): boolean {
  return integrationKey !== "md_integrations";
}

function usesMedicationOfferingIds(integrationKey: string): boolean {
  return integrationKey === "md_integrations";
}

function recordsEqual(
  left: Record<string, string>,
  right: Record<string, string>,
): boolean {
  const leftKeys = Object.keys(left);
  const rightKeys = Object.keys(right);

  if (leftKeys.length !== rightKeys.length) return false;
  return leftKeys.every((key) => left[key] === right[key]);
}

function parseAllocationValue(value: string): number {
  if (!value.trim()) return 0;
  const parsedValue = Number.parseInt(value, 10);
  if (!Number.isInteger(parsedValue) || parsedValue < 0 || parsedValue > 100) {
    throw new Error("Allocations must be whole numbers between 0 and 100");
  }

  return parsedValue;
}

function ensureUniqueDraftRowIds(
  rows: LoadBalancingDraftRow[],
): LoadBalancingDraftRow[] {
  const seenIds = new Set<string>();

  return rows.map((row, index) => {
    const candidateId = row.id || `draft-row-${index}`;

    if (!seenIds.has(candidateId)) {
      seenIds.add(candidateId);
      return {
        ...row,
        id: candidateId,
      };
    }

    const dedupedId = `${candidateId}-${index}`;
    seenIds.add(dedupedId);
    return {
      ...row,
      id: dedupedId,
    };
  });
}

function getDraftRowContentKey(row: LoadBalancingDraftRow): string {
  return JSON.stringify({
    stateCodes: [...row.stateCodes].sort(),
    allocations: Object.entries(row.allocations).sort(([left], [right]) =>
      left.localeCompare(right)
    ),
  });
}

function dedupeDraftRows(
  rows: LoadBalancingDraftRow[],
): LoadBalancingDraftRow[] {
  const seenKeys = new Set<string>();

  return rows.filter((row) => {
    const key = getDraftRowContentKey(row);
    if (seenKeys.has(key)) return false;
    seenKeys.add(key);
    return true;
  });
}

export function ProductProviderPlatformsManager({
  productId,
  readOnly = false,
}: ProductProviderPlatformsManagerProps) {
  const { currentTenantId } = useAuth();
  const knownStateCodes = useMemo(
    () => new Set(US_STATES.map((state) => state.code)),
    [],
  );
  const {
    providersWithAssignment,
    linkedMedications,
    loadBalancingRuleSets,
    tenantAllowedStates,
    isLoading,
    toggleProductProviderPlatform,
    saveProviderPlatformSku,
    resolveTelegraProductVariation,
    saveLoadBalancingRules,
  } = useProductProviderPlatforms(productId);
  const [
    providerProductVariationSkuDrafts,
    setProviderProductVariationSkuDrafts,
  ] = useState<
    Record<string, string>
  >({});
  const [providerProductSkuDrafts, setProviderProductSkuDrafts] = useState<
    Record<string, string>
  >(
    {},
  );
  const [loadBalancingDraftRows, setLoadBalancingDraftRows] = useState<
    LoadBalancingDraftRow[]
  >([]);
  const [hasUnsavedRuleChanges, setHasUnsavedRuleChanges] = useState(false);
  const lastSyncedLoadBalancingSignatureRef = useRef<string>("");

  const assignedProviders = useMemo(
    () =>
      providersWithAssignment.filter(
        (provider) => provider.isAssigned && !!provider.assignmentId,
      ) as Array<
        (typeof providersWithAssignment)[number] & { assignmentId: string }
      >,
    [providersWithAssignment],
  );

  const normalizedTenantAllowedStates = useMemo(
    () =>
      Array.from(
        new Set(
          tenantAllowedStates
            .filter((stateCode): stateCode is string =>
              typeof stateCode === "string"
            )
            .map((stateCode) => stateCode.trim().toUpperCase())
            .filter((stateCode) => knownStateCodes.has(stateCode)),
        ),
      ),
    [knownStateCodes, tenantAllowedStates],
  );

  const selectableStates = useMemo(
    () =>
      normalizedTenantAllowedStates.length > 0
        ? US_STATES.filter((state) =>
          normalizedTenantAllowedStates.includes(state.code)
        )
        : US_STATES,
    [normalizedTenantAllowedStates],
  );

  const providerDraftSource = useMemo(
    () =>
      providersWithAssignment.map((provider) => ({
        id: provider.id,
        providerProductVariationSku: provider.providerProductVariationSku || "",
        providerProductSku: provider.providerProductSku || "",
      })),
    [providersWithAssignment],
  );

  const syncedProviderDrafts = useMemo(() => {
    const nextVariationDrafts = Object.fromEntries(
      providerDraftSource.map((provider) => [
        provider.id,
        provider.providerProductVariationSku,
      ]),
    );
    const nextProductDrafts = Object.fromEntries(
      providerDraftSource.map((
        provider,
      ) => [provider.id, provider.providerProductSku]),
    );
    return {
      nextVariationDrafts,
      nextProductDrafts,
    };
  }, [providerDraftSource]);

  useEffect(() => {
    setProviderProductVariationSkuDrafts((currentDrafts) =>
      recordsEqual(currentDrafts, syncedProviderDrafts.nextVariationDrafts)
        ? currentDrafts
        : syncedProviderDrafts.nextVariationDrafts
    );
    setProviderProductSkuDrafts((currentDrafts) =>
      recordsEqual(currentDrafts, syncedProviderDrafts.nextProductDrafts)
        ? currentDrafts
        : syncedProviderDrafts.nextProductDrafts
    );
  }, [syncedProviderDrafts]);

  const loadBalancingSourceSignature = useMemo(
    () =>
      JSON.stringify({
        assignedProviders: assignedProviders.map((provider) => ({
          assignmentId: provider.assignmentId,
        })),
        ruleSets: loadBalancingRuleSets.map((ruleSet) => ({
          id: ruleSet.id,
          isDefault: ruleSet.is_default,
          stateCodes: (ruleSet.states || []).map((state) => state.state_code)
            .sort(),
          allocations: (ruleSet.allocations || [])
            .map((allocation) => ({
              productProviderPlatformId:
                allocation.product_provider_platform_id,
              allocationPercentage: allocation.allocation_percentage,
            }))
            .sort((left, right) =>
              left.productProviderPlatformId.localeCompare(
                right.productProviderPlatformId,
              )
            ),
        })),
      }),
    [assignedProviders, loadBalancingRuleSets],
  );

  const handleVariationSkuBlur = async (
    providerId: string,
    savedVariationSku: string,
    shouldResolveProductSku: boolean,
  ) => {
    if (readOnly) {
      return;
    }

    if (!shouldResolveProductSku || saveProviderPlatformSku.isPending) {
      return;
    }

    const draftVariationSku = providerProductVariationSkuDrafts[providerId] ||
      "";
    if (draftVariationSku.trim() === savedVariationSku.trim()) {
      return;
    }

    if (!draftVariationSku.trim()) {
      setProviderProductSkuDrafts((currentDrafts) => ({
        ...currentDrafts,
        [providerId]: "",
      }));
      return;
    }

    try {
      const resolvedProductSku = await resolveTelegraProductVariation({
        tenantIntegrationId: providerId,
        productVariationId: draftVariationSku,
      });

      setProviderProductSkuDrafts((currentDrafts) => {
        if ((currentDrafts[providerId] || "").trim().length > 0) {
          return currentDrafts;
        }

        return {
          ...currentDrafts,
          [providerId]: resolvedProductSku,
        };
      });
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to resolve Telegra product variation",
      );
    }
  };

  useEffect(() => {
    if (hasUnsavedRuleChanges) {
      return;
    }

    if (
      lastSyncedLoadBalancingSignatureRef.current ===
        loadBalancingSourceSignature
    ) {
      return;
    }

    if (assignedProviders.length === 0) {
      lastSyncedLoadBalancingSignatureRef.current =
        loadBalancingSourceSignature;
      setLoadBalancingDraftRows([]);
      return;
    }

    const emptyAllocations = Object.fromEntries(
      assignedProviders.map((provider) => [provider.assignmentId, "0"]),
    );
    let nextRows = loadBalancingRuleSets
      .map((row) => ({
        id: row.id,
        stateCodes: (row.states || []).map((state) => state.state_code).sort(),
        allocations: {
          ...emptyAllocations,
          ...Object.fromEntries(
            (row.allocations || []).map((allocation) => [
              allocation.product_provider_platform_id,
              String(allocation.allocation_percentage),
            ]),
          ),
        },
      }))
      .sort((left, right) => {
        if (left.stateCodes.length === 0) return -1;
        if (right.stateCodes.length === 0) return 1;
        return left.stateCodes[0].localeCompare(right.stateCodes[0]);
      });

    if (nextRows.length === 0) {
      const defaultRow: LoadBalancingDraftRow = {
        id: "default-rule",
        stateCodes: [],
        allocations: { ...emptyAllocations },
      };

      if (assignedProviders.length === 1) {
        defaultRow.allocations[assignedProviders[0].assignmentId] = "100";
      }

      nextRows = [defaultRow];
    }

    lastSyncedLoadBalancingSignatureRef.current = loadBalancingSourceSignature;
    setLoadBalancingDraftRows(
      dedupeDraftRows(ensureUniqueDraftRowIds(nextRows)),
    );
  }, [
    assignedProviders,
    hasUnsavedRuleChanges,
    loadBalancingRuleSets,
    loadBalancingSourceSignature,
  ]);

  const updateAllocation = (
    rowIndex: number,
    assignmentId: string,
    value: string,
  ) => {
    if (readOnly) return;
    if (!/^\d{0,3}$/.test(value)) return;

    setHasUnsavedRuleChanges(true);
    setLoadBalancingDraftRows((currentRows) =>
      currentRows.map((row, index) => {
        if (index !== rowIndex) return row;

        const nextAllocations = {
          ...row.allocations,
          [assignmentId]: value,
        };

        if (assignedProviders.length === 2) {
          const otherProvider = assignedProviders.find(
            (provider) => provider.assignmentId !== assignmentId,
          );

          if (otherProvider) {
            const currentValue = Number.parseInt(value || "0", 10) || 0;
            const boundedValue = Math.min(Math.max(currentValue, 0), 100);
            nextAllocations[otherProvider.assignmentId] = String(
              100 - boundedValue,
            );
          }
        }

        return {
          ...row,
          allocations: nextAllocations,
        };
      })
    );
  };

  const addStateOverride = () => {
    if (readOnly) return;
    setHasUnsavedRuleChanges(true);
    const defaultRow = loadBalancingDraftRows.find((row) =>
      row.stateCodes.length === 0
    );
    const defaultAllocations = Object.fromEntries(
      assignedProviders.map((provider) => [
        provider.assignmentId,
        defaultRow?.allocations[provider.assignmentId] || "0",
      ]),
    );

    setLoadBalancingDraftRows((currentRows) => [
      ...currentRows,
      {
        id: crypto.randomUUID(),
        stateCodes: [],
        allocations: defaultAllocations,
      },
    ]);
  };

  // Remove a ruleset by its stable id. Used by the per-row remove button so a
  // freshly-added (still empty) override row can be cancelled — keying on
  // stateCodes failed because a new row shares the default row's empty stateCodes.
  const removeRulesetById = (id: string) => {
    if (readOnly) return;
    setHasUnsavedRuleChanges(true);
    setLoadBalancingDraftRows((currentRows) =>
      currentRows.filter((row) => row.id !== id)
    );
  };

  const toggleStateForRule = (
    rowIndex: number,
    stateCode: string,
    checked: boolean,
  ) => {
    if (readOnly) return;
    setHasUnsavedRuleChanges(true);
    setLoadBalancingDraftRows((currentRows) =>
      currentRows.map((row, index) => {
        if (index !== rowIndex) return row;

        const nextStateCodes = checked
          ? [...row.stateCodes, stateCode]
          : row.stateCodes.filter((code) => code !== stateCode);

        return {
          ...row,
          stateCodes: Array.from(new Set(nextStateCodes)).sort(),
        };
      })
    );
  };

  const getRowTotal = (row: LoadBalancingDraftRow) =>
    assignedProviders.reduce(
      (total, provider) =>
        total +
        (Number.parseInt(row.allocations[provider.assignmentId] || "0", 10) ||
          0),
      0,
    );
  const handleSaveLoadBalancingRules = () => {
    if (readOnly) return;

    try {
      if (assignedProviders.length === 0) {
        return;
      }

      const emptyRuleCount = loadBalancingDraftRows.filter((row) =>
        row.stateCodes.length === 0
      ).length;
      if (emptyRuleCount > 1) {
        throw new Error(
          "Assign at least one state to every extra ruleset before saving",
        );
      }

      const payload = loadBalancingDraftRows.map((row, rowIndex) => {
        if (row.stateCodes.length === 0 && rowIndex !== 0) {
          throw new Error(
            "Assign at least one state to every extra ruleset before saving",
          );
        }

        const total = assignedProviders.reduce((sum, provider) => {
          return sum +
            parseAllocationValue(row.allocations[provider.assignmentId] || "0");
        }, 0);

        if (total !== 100) {
          throw new Error(
            `${
              row.stateCodes.length > 0 ? row.stateCodes.join(", ") : "Default"
            } allocations must total 100% before saving`,
          );
        }

        return {
          isDefault: rowIndex === 0 && row.stateCodes.length === 0,
          stateCodes: row.stateCodes,
          allocations: assignedProviders.map((provider) => ({
            productProviderPlatformId: provider.assignmentId,
            allocationPercentage: parseAllocationValue(
              row.allocations[provider.assignmentId] || "0",
            ),
          })),
        };
      });

      saveLoadBalancingRules.mutate(payload, {
        onSuccess: () => {
          setHasUnsavedRuleChanges(false);
        },
      });
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to validate routing rules",
      );
    }
  };

  if (isLoading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center py-8">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  if (!providersWithAssignment.length) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Provider Platforms</CardTitle>
          <CardDescription>
            Select which provider platforms can offer this product and store
            provider-specific settings for each one.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col items-center justify-center py-6 text-center">
            <AlertCircle className="mb-3 h-10 w-10 text-muted-foreground" />
            <h4 className="mb-1 font-medium">No Provider Platforms Enabled</h4>
            <p className="mb-4 text-sm text-muted-foreground">
              Enable a provider platform for your tenant under Settings →
              Providers first, then enable it for this product here.
            </p>
            {!readOnly ? (
              <Button variant="outline" size="sm" asChild>
                <Link to="/tenant-admin/settings/providers">
                  <Plug className="mr-2 h-4 w-4" />
                  Go to Providers
                </Link>
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Providers</CardTitle>
        <CardDescription>
          Enable the providers that can offer this product, store their
          identifiers (IDs), and define the per-state routing percentages used
          when new orders are created. Questionnaires are configured under
          Settings → Questionnaires.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="ids" className="space-y-6">
          <TabsList>
            <TabsTrigger value="ids">IDs</TabsTrigger>
            <TabsTrigger value="routing">Routing</TabsTrigger>
          </TabsList>

          <TabsContent value="ids" className="space-y-3">
          {providersWithAssignment.map((provider) => {
            const skuLabel = getSkuLabel(provider.integration.key);
            const showProductSkuField = usesSeparateProductSku(
              provider.integration.key,
            );
            const showVariationSkuField = usesVariationSku(
              provider.integration.key,
            );
            const showMedicationOfferingIds = usesMedicationOfferingIds(
              provider.integration.key,
            );
            const hasMedicationOfferingIds = showMedicationOfferingIds &&
              linkedMedications.length > 0 &&
              linkedMedications.every((medication) => medication.offeringId);
            const isConfigured = hasMedicationOfferingIds ||
              provider.jotformNewOrderQuestionnaireId ||
              provider.jotformRenewalQuestionnaireId ||
              (showVariationSkuField && provider.providerProductVariationSku) ||
              provider.providerProductSku;

            return (
              <div key={provider.id} className="rounded-lg border p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                      {integrationIcons[provider.integration.key] || (
                        <Plug className="h-4 w-4 text-muted-foreground" />
                      )}
                    </div>
                    <div className="space-y-1">
                      <p className="font-medium">{provider.integration.name}</p>
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="text-xs">
                          {provider.integration.key}
                        </Badge>
                        {isConfigured
                          ? <Badge variant="secondary">Configured</Badge>
                          : null}
                      </div>
                      {provider.integration.description
                        ? (
                          <p className="text-sm text-muted-foreground">
                            {provider.integration.description}
                          </p>
                        )
                        : null}
                    </div>
                  </div>

                  <Switch
                    checked={provider.isAssigned}
                    onCheckedChange={(checked) => {
                      if (readOnly) return;
                      toggleProductProviderPlatform.mutate({
                        tenantIntegrationId: provider.id,
                        enabled: checked,
                      });
                    }}
                    disabled={readOnly || toggleProductProviderPlatform.isPending}
                  />
                </div>

                <div className="mt-4 space-y-4">
                  {showMedicationOfferingIds
                    ? (
                      <div className="space-y-2">
                        <Label>Medication Offering IDs</Label>
                        <p className="text-sm text-muted-foreground">
                          MD Integrations uses medication-level offering IDs for
                          case creation.
                        </p>
                        {linkedMedications.length === 0
                          ? (
                            <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                              Link medications to this product before
                              configuring MDI offering IDs.
                            </div>
                          )
                          : (
                            <div className="space-y-3">
                              {linkedMedications.map((medication) => (
                                <div
                                  key={medication.productMedicationId}
                                  className="grid gap-3 rounded-md border bg-muted/10 p-3 md:grid-cols-[minmax(0,1fr)_minmax(180px,0.6fr)_auto] md:items-center"
                                >
                                  <div className="min-w-0">
                                    <p className="text-sm font-medium truncate">
                                      {medication.medicationTitle}
                                    </p>
                                  </div>
                                  <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">
                                      Offering ID
                                    </p>
                                    <p className="mt-1 break-all text-sm font-medium">
                                      {medication.offeringId ||
                                        "Not configured"}
                                    </p>
                                  </div>
                                  <Button variant="outline" asChild>
                                    <Link
                                      to={ROUTES.TENANT_ADMIN.CATALOG
                                        .MEDICATION_DETAIL.replace(
                                          ":id",
                                          medication.medicationId,
                                        )}
                                    >
                                      {readOnly ? "View medication" : "Edit medication"}
                                    </Link>
                                  </Button>
                                </div>
                              ))}
                            </div>
                          )}
                      </div>
                    )
                    : null}

                  {showVariationSkuField
                    ? (
                      <div className="space-y-2">
                        <Label htmlFor={`provider-platform-sku-${provider.id}`}>
                          {skuLabel}
                        </Label>
                        {showProductSkuField
                          ? (
                            <p className="text-sm text-muted-foreground">
                              Telegra product variation ids start with `pvt::`.
                            </p>
                          )
                          : provider.integration.key === "md_integrations"
                          ? (
                            <p className="text-sm text-muted-foreground">
                              This is the medication ID stored on the
                              product-provider assignment and sent to MDI when
                              creating the case.
                            </p>
                          )
                          : null}
                        <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
                          <Input
                            id={`provider-platform-sku-${provider.id}`}
                            value={providerProductVariationSkuDrafts[
                              provider.id
                            ] || ""}
                            onChange={(event) => {
                              if (readOnly) return;
                              const nextValue = event.target.value;
                              setProviderProductVariationSkuDrafts((
                                currentDrafts,
                              ) => ({
                                ...currentDrafts,
                                [provider.id]: nextValue,
                              }));
                              if (showProductSkuField) {
                                setProviderProductSkuDrafts((
                                  currentDrafts,
                                ) => ({
                                  ...currentDrafts,
                                  [provider.id]: "",
                                }));
                              }
                            }}
                            onBlur={() => {
                              void handleVariationSkuBlur(
                                provider.id,
                                provider.providerProductVariationSku || "",
                                showProductSkuField,
                              );
                            }}
                            maxLength={100}
                            placeholder={getSkuPlaceholder(
                              provider.integration.key,
                            )}
                            disabled={readOnly || !provider.isAssigned}
                          />
                          {!readOnly && !showProductSkuField
                            ? (
                              <Button
                                variant="outline"
                                onClick={() =>
                                  saveProviderPlatformSku.mutate({
                                    tenantIntegrationId: provider.id,
                                    providerProductSku: undefined,
                                    providerProductVariationSku:
                                      providerProductVariationSkuDrafts[
                                        provider.id
                                      ] || "",
                                  })}
                                disabled={!provider.isAssigned ||
                                  saveProviderPlatformSku.isPending}
                              >
                                {saveProviderPlatformSku.isPending
                                  ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                  )
                                  : <Save className="mr-2 h-4 w-4" />}
                                Save Settings
                              </Button>
                            )
                            : null}
                        </div>
                      </div>
                    )
                    : null}

                  {showProductSkuField
                    ? (
                      <div className="space-y-2">
                        <Label
                          htmlFor={`provider-platform-product-sku-${provider.id}`}
                        >
                          Product SKU
                        </Label>
                        <p className="text-sm text-muted-foreground">
                          Resolved automatically from the Telegra product
                          variation. Product ids start with `pro::`.
                        </p>
                        <div className="flex gap-2">
                          <Input
                            id={`provider-platform-product-sku-${provider.id}`}
                            value={providerProductSkuDrafts[provider.id] || ""}
                            readOnly
                            placeholder="Will be filled automatically from the variation SKU"
                            disabled={readOnly || !provider.isAssigned}
                          />
                          {!readOnly ? (
                            <Button
                              variant="outline"
                              onClick={() =>
                                saveProviderPlatformSku.mutate({
                                  tenantIntegrationId: provider.id,
                                  providerProductSku: showProductSkuField
                                    ? providerProductSkuDrafts[provider.id] || ""
                                    : undefined,
                                  providerProductVariationSku:
                                    providerProductVariationSkuDrafts[
                                      provider.id
                                    ] || "",
                                })}
                              disabled={!provider.isAssigned ||
                                saveProviderPlatformSku.isPending}
                            >
                              {saveProviderPlatformSku.isPending
                                ? (
                                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                )
                                : <Save className="mr-2 h-4 w-4" />}
                              Save Settings
                            </Button>
                          ) : null}
                        </div>
                      </div>
                    )
                    : null}

                  {!readOnly && !provider.isAssigned
                    ? (
                      <p className="text-sm text-muted-foreground">
                        Enable this provider platform first to save
                        provider-specific settings.
                      </p>
                    )
                    : null}
                  {/* Medical questionnaire config moved to Settings →
                      Questionnaires → Medical. This section is now IDs + Routing
                      only. */}
                </div>
              </div>
            );
          })}
          {!readOnly ? (
            <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
              Only providers enabled for your tenant appear here. To add another
              provider, enable it first under{" "}
              <Link
                to="/tenant-admin/settings/providers"
                className="font-medium text-primary hover:underline"
              >
                Settings → Providers
              </Link>
              , then toggle it on for this product.
            </div>
          ) : null}
          </TabsContent>

          <TabsContent value="routing">
        <div className="pt-2">
          <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div className="space-y-1">
              <h3 className="text-base font-medium">Load Balancing Rules</h3>
              <p className="text-sm text-muted-foreground">
                Set the percentage split across assigned provider platforms.
                State overrides take precedence over the default rule and every
                row must total 100%.
              </p>
            </div>

            {!readOnly && assignedProviders.length > 0
              ? (
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={addStateOverride}
                  >
                    Add Ruleset
                  </Button>
                </div>
              )
              : null}
          </div>

          {assignedProviders.length === 0
            ? (
              <div className="mt-4 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                Enable at least one provider platform before configuring routing
                percentages.
              </div>
            )
            : (
              <div className="mt-4 space-y-4">
                {loadBalancingDraftRows.map((row, rowIndex) => {
                  const total = getRowTotal(row);
                  const isValid = total === 100;
                  const isDefaultRule = rowIndex === 0 &&
                    row.stateCodes.length === 0;
                  const unavailableStateCodes = new Set(
                    loadBalancingDraftRows
                      .filter((_, candidateIndex) =>
                        candidateIndex !== rowIndex
                      )
                      .flatMap((candidateRow) => candidateRow.stateCodes),
                  );
                  const stateOptions = selectableStates.map((state) => {
                    const isSelected = row.stateCodes.includes(state.code);
                    const isDisabled = !isSelected &&
                      unavailableStateCodes.has(state.code);

                    return {
                      ...state,
                      isSelected,
                      isDisabled,
                    };
                  });
                  const rowLabel = row.stateCodes.length > 0
                    ? `${row.stateCodes.length} States`
                    : isDefaultRule
                    ? "Default Rule"
                    : "Unassigned Ruleset";

                  return (
                    <div
                      key={`${row.id}-${rowIndex}`}
                      className="rounded-lg border p-4"
                    >
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                          <p className="font-medium">{rowLabel}</p>
                          <p className="text-sm text-muted-foreground">
                            {isDefaultRule
                              ? "Used when no state-specific ruleset matches the order."
                              : "Select the states that should use this percentage split."}
                          </p>
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge
                            variant={isValid ? "secondary" : "destructive"}
                          >
                            Total {total}%
                          </Badge>
                          {!readOnly && !isDefaultRule
                            ? (
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title="Remove this ruleset"
                                onClick={() => removeRulesetById(row.id)}
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            )
                            : null}
                        </div>
                      </div>

                      <div className="mt-4 space-y-2">
                        <div className="flex items-center justify-between gap-3">
                          <Label>States</Label>
                          {row.stateCodes.length > 0
                            ? (
                              <span className="text-xs text-muted-foreground">
                                {row.stateCodes.length} selected
                              </span>
                            )
                            : null}
                        </div>
                        <Popover>
                          <PopoverTrigger asChild>
                            <Button
                              type="button"
                              variant="outline"
                              className="justify-start"
                              disabled={readOnly}
                            >
                              {row.stateCodes.length > 0
                                ? row.stateCodes.join(", ")
                                : "Choose states"}
                            </Button>
                          </PopoverTrigger>
                          <PopoverContent
                            className="w-[320px] p-0"
                            align="start"
                          >
                            <ScrollArea className="h-72 p-4">
                              <div className="space-y-3">
                                {stateOptions.length === 0
                                  ? (
                                    <p className="text-sm text-muted-foreground">
                                      No tenant-allowed states are available.
                                    </p>
                                  )
                                  : (
                                    stateOptions.map((state) => {
                                      return (
                                        <label
                                          key={state.code}
                                          className={`flex items-center gap-3 text-sm ${
                                            state.isDisabled
                                              ? "cursor-not-allowed opacity-50"
                                              : ""
                                          }`}
                                        >
                                          <Checkbox
                                            checked={state.isSelected}
                                            disabled={readOnly || state.isDisabled}
                                            onCheckedChange={(checked) =>
                                              toggleStateForRule(
                                                rowIndex,
                                                state.code,
                                                checked === true,
                                              )}
                                          />
                                          <span>
                                            {state.code} - {state.name}
                                          </span>
                                        </label>
                                      );
                                    })
                                  )}
                              </div>
                            </ScrollArea>
                          </PopoverContent>
                        </Popover>
                      </div>

                      <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {assignedProviders.map((provider) => (
                          <div
                            key={provider.assignmentId}
                            className="space-y-2"
                          >
                            <Label
                              htmlFor={`${row.id}-${provider.assignmentId}`}
                            >
                              {provider.integration.name}
                            </Label>
                            <div className="flex items-center gap-2">
                              <Input
                                id={`${row.id}-${provider.assignmentId}`}
                                inputMode="numeric"
                                value={row.allocations[provider.assignmentId] ||
                                  "0"}
                                onChange={(event) =>
                                  updateAllocation(
                                    rowIndex,
                                    provider.assignmentId,
                                    event.target.value,
                                  )}
                                disabled={readOnly}
                              />
                              <span className="text-sm text-muted-foreground">
                                %
                              </span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                              {provider.integration.key}
                            </p>
                          </div>
                        ))}
                      </div>
                    </div>
                  );
                })}

                {!readOnly ? (
                  <div className="flex justify-end">
                    <Button
                      type="button"
                      onClick={handleSaveLoadBalancingRules}
                      disabled={saveLoadBalancingRules.isPending}
                    >
                      {saveLoadBalancingRules.isPending
                        ? <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        : <Save className="mr-2 h-4 w-4" />}
                      Save Load Balancing Rules
                    </Button>
                  </div>
                ) : null}
              </div>
            )}
        </div>
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}
