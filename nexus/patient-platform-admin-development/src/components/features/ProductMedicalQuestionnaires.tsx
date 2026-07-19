/**
 * Medical-questionnaire editing — the single source of truth shared by:
 *  - Catalog → Product → Provider Platforms ("Jotform Medical Questionnaires"
 *    section, rendered once per enabled provider), and
 *  - Settings → Questionnaires → Medical (one card per product).
 *
 * The medical questionnaire depends on BOTH provider and product. For each
 * provider enabled on a product it offers two delivery paths (persisted to
 * product_provider_platforms.integration_mode):
 *  - Direct  — uses the provider's native questionnaire (no Jotform IDs).
 *  - Jotform — uses the new-order + renewal Jotform form IDs configured here.
 *
 * Persistence is the existing `saveProviderPlatformSku` mutation from
 * useProductProviderPlatforms — no new storage.
 *
 * Two entry points:
 *  - <ProviderMedicalQuestionnaire> — one provider row; used inside the Catalog
 *    manager's existing per-provider loop (it owns the hook/state and passes the
 *    pieces in).
 *  - <ProductMedicalQuestionnaires productId> — self-contained per-product editor
 *    (owns the hook itself); used on the Questionnaires → Medical tab.
 */
import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import type { UseMutationResult } from "@tanstack/react-query";
import { Loader2, Save, Trash2 } from "lucide-react";
import { ROUTES } from "@/lib/constants";
import { JotformWebhookStatusControl } from "@/components/features/JotformWebhookStatusControl";
import { useJotformIntegrationStatus } from "@/hooks/useJotformIntegrationStatus";
import { useProductProviderPlatforms } from "@/hooks/useProductProviderPlatforms";
import { useAuth } from "@/stores/authStore";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

type IntegrationMode = "direct" | "jotform";

/** Minimal shape of a save mutation this component needs (matches
 *  useProductProviderPlatforms().saveProviderPlatformSku). */
type SaveProviderPlatformSku = UseMutationResult<
  unknown,
  unknown,
  {
    tenantIntegrationId: string;
    integrationMode?: IntegrationMode;
    jotformNewOrderQuestionnaireId?: string;
    jotformRenewalQuestionnaireId?: string;
    providerProductSku?: string;
    providerProductVariationSku?: string;
  }
>;

interface MedicalProviderRowProps {
  productId: string;
  providerName: string;
  providerId: string;
  mode: IntegrationMode;
  newOrderQuestionnaireId: string;
  renewalQuestionnaireId: string;
  saveProviderPlatformSku: SaveProviderPlatformSku;
  jotformApiUrl: string;
  jotformDefaultWebhookUrl: string | null;
}

/**
 * One provider as a TABLE ROW (Settings → Questionnaires → Medical). Columns:
 * Provider | Mode (Direct|Jotform) | New-order ID | Renewal ID | actions. Direct
 * shows an inline note; Jotform shows the two id inputs with save + clear.
 */
function MedicalProviderRow({
  productId,
  providerName,
  providerId,
  mode,
  newOrderQuestionnaireId,
  renewalQuestionnaireId,
  saveProviderPlatformSku,
  jotformApiUrl,
  jotformDefaultWebhookUrl,
}: MedicalProviderRowProps) {
  const [newOrderDraft, setNewOrderDraft] = useState(newOrderQuestionnaireId);
  const [renewalDraft, setRenewalDraft] = useState(renewalQuestionnaireId);

  useEffect(() => {
    setNewOrderDraft(newOrderQuestionnaireId);
  }, [newOrderQuestionnaireId]);
  useEffect(() => {
    setRenewalDraft(renewalQuestionnaireId);
  }, [renewalQuestionnaireId]);

  const isJotform = mode === "jotform";
  const hasStoredIds = Boolean(
    newOrderQuestionnaireId || renewalQuestionnaireId,
  );

  return (
    <TableRow>
      <TableCell className="align-top font-medium pt-4">{providerName}</TableCell>
      <TableCell className="align-top pt-3">
        <div className="inline-flex rounded-md border p-0.5">
          {(["jotform", "direct"] as const).map((m) => (
            <button
              key={m}
              type="button"
              disabled={saveProviderPlatformSku.isPending || mode === m}
              onClick={() =>
                saveProviderPlatformSku.mutate({
                  tenantIntegrationId: providerId,
                  integrationMode: m,
                })}
              className={`rounded px-2.5 py-1 text-xs font-medium capitalize transition-colors disabled:opacity-100 ${
                mode === m
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
              }`}
            >
              {m === "jotform" ? "Jotform" : "Direct"}
            </button>
          ))}
        </div>
      </TableCell>
      {isJotform
        ? (
          <>
            <TableCell className="align-top pt-3">
              <div className="flex items-center gap-1">
                <Input
                  className="h-8 font-mono text-xs"
                  value={newOrderDraft}
                  onChange={(e) => setNewOrderDraft(e.target.value)}
                  maxLength={128}
                  placeholder="New-order form ID"
                />
                {newOrderDraft.trim()
                  ? (
                    <JotformWebhookStatusControl
                      tenantIntegrationId={providerId}
                      formId={newOrderDraft.trim()}
                      defaultWebhookUrl={jotformDefaultWebhookUrl}
                      apiUrl={jotformApiUrl}
                      previewLabel="Preview new order questionnaire in Jotform"
                      editLabel="Edit new order questionnaire in Jotform"
                      showActions={false}
                    />
                  )
                  : null}
              </div>
            </TableCell>
            <TableCell className="align-top pt-3">
              <Input
                className="h-8 font-mono text-xs"
                value={renewalDraft}
                onChange={(e) => setRenewalDraft(e.target.value)}
                maxLength={128}
                placeholder="(none)"
              />
            </TableCell>
            <TableCell className="align-top pt-3 text-right">
              <div className="flex justify-end gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() =>
                    saveProviderPlatformSku.mutate({
                      tenantIntegrationId: providerId,
                      jotformNewOrderQuestionnaireId: newOrderDraft || "",
                      jotformRenewalQuestionnaireId: renewalDraft || "",
                    })}
                  disabled={saveProviderPlatformSku.isPending}
                >
                  {saveProviderPlatformSku.isPending
                    ? <Loader2 className="h-4 w-4 animate-spin" />
                    : <Save className="h-4 w-4" />}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-destructive"
                  title="Clear form IDs"
                  onClick={() => {
                    setNewOrderDraft("");
                    setRenewalDraft("");
                    saveProviderPlatformSku.mutate({
                      tenantIntegrationId: providerId,
                      jotformNewOrderQuestionnaireId: "",
                      jotformRenewalQuestionnaireId: "",
                    });
                  }}
                  disabled={saveProviderPlatformSku.isPending || !hasStoredIds}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            </TableCell>
          </>
        )
        : (
          <TableCell colSpan={3} className="align-top pt-4">
            <p className="text-xs text-muted-foreground">
              Uses the provider’s native questionnaire — it runs off the provider
              IDs configured for this product (no Jotform form IDs needed).
              Submissions arrive via the provider’s direct webhook path.
            </p>
            <Button asChild variant="link" size="sm" className="h-auto px-0 text-xs">
              <Link
                to={`${ROUTES.TENANT_ADMIN.CATALOG.PRODUCT_DETAIL.replace(
                  ":id",
                  productId,
                )}?tab=provider-platforms`}
              >
                View / edit provider IDs →
              </Link>
            </Button>
          </TableCell>
        )}
    </TableRow>
  );
}

interface ProductMedicalQuestionnairesProps {
  productId: string;
}

/**
 * Self-contained per-product medical-questionnaire editor — a TABLE of the
 * product's enabled providers, each toggling Direct | Jotform with inline IDs.
 * Used on Settings → Questionnaires → Medical. Persistence is the same
 * saveProviderPlatformSku mutation used by the Catalog → Product card editor.
 */
export function ProductMedicalQuestionnaires({
  productId,
}: ProductMedicalQuestionnairesProps) {
  const { currentTenantId } = useAuth();
  const {
    apiUrl: jotformApiUrl,
    defaultWebhookUrl: jotformDefaultWebhookUrl,
  } = useJotformIntegrationStatus(currentTenantId);
  const {
    providersWithAssignment,
    productProviderPlatforms,
    isLoading,
    saveProviderPlatformSku,
  } = useProductProviderPlatforms(productId);

  const assignedProviders = useMemo(
    () => providersWithAssignment.filter((provider) => provider.isAssigned),
    [providersWithAssignment],
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (assignedProviders.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        No provider platforms are enabled for this product yet. Enable a provider
        on the product to configure its medical questionnaire.
      </p>
    );
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Provider</TableHead>
          <TableHead className="w-40">Integration</TableHead>
          <TableHead>New-order form ID</TableHead>
          <TableHead>Renewal form ID</TableHead>
          <TableHead className="w-24" />
        </TableRow>
      </TableHeader>
      <TableBody>
        {assignedProviders.map((provider) => {
          const assignment = productProviderPlatforms.find(
            (ppp) => ppp.tenant_integration_id === provider.id,
          );
          return (
            <MedicalProviderRow
              key={provider.id}
              productId={productId}
              providerName={provider.integration.name}
              providerId={provider.id}
              mode={assignment?.integration_mode ?? "direct"}
              newOrderQuestionnaireId={provider.jotformNewOrderQuestionnaireId ||
                ""}
              renewalQuestionnaireId={provider.jotformRenewalQuestionnaireId ||
                ""}
              saveProviderPlatformSku={saveProviderPlatformSku}
              jotformApiUrl={jotformApiUrl}
              jotformDefaultWebhookUrl={jotformDefaultWebhookUrl || null}
            />
          );
        })}
      </TableBody>
    </Table>
  );
}
