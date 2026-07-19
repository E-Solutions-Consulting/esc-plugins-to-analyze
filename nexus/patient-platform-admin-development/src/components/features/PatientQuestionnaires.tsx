/**
 * PatientQuestionnaires — the patient intake questionnaire, per provider, with an
 * EXPLICIT Direct | Jotform toggle (Settings → Questionnaires → Patient).
 *
 * Per provider (Telegra / MDI), the mode persists to
 * `tenant_integrations.settings.patient_questionnaire_mode` ('direct' | 'jotform')
 * and drives which connection config shows:
 *  - Direct  → the Patient Questionnaire Definition JSON (the bridge sends answers
 *    to the provider natively). Saved to settings.patient_questionnaire_definition.
 *  - Jotform → the Patient Questionnaire Jotform ID + webhook status. Saved to
 *    settings.patient_questionnaire_form_id (validated against the bridge).
 *
 * Both fields can be edited AND cleared. Switching modes never deletes the other
 * mode's stored value — it just changes which path is active.
 */
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useMutation } from "@tanstack/react-query";
import { Loader2, Save, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { JotformHiddenFieldsHelp } from "@/components/features/JotformHiddenFieldsHelp";
import {
  JotformDefaultWebhookWarning,
  JotformWebhookStatusControl,
} from "@/components/features/JotformWebhookStatusControl";
import { JotformSetupWarning } from "@/components/features/JotformSetupWarning";
import { useJotformIntegrationStatus } from "@/hooks/useJotformIntegrationStatus";
import {
  type ProviderIntegration,
  useProviderIntegrations,
} from "@/hooks/useProviderIntegrations";
import { useAuth } from "@/stores/authStore";
import { validateJotformQuestionnaireForm } from "@/lib/jotform-validation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

type QuestionnaireMode = "direct" | "jotform";

const MODE_SETTING = "patient_questionnaire_mode";
const DEFINITION_SETTING = "patient_questionnaire_definition";
const FORM_ID_SETTING = "patient_questionnaire_form_id";

const settingString = (settings: Record<string, unknown>, key: string) => {
  const value = settings[key];
  return typeof value === "string" ? value : "";
};

const settingDefinitionText = (settings: Record<string, unknown>) => {
  const value = settings[DEFINITION_SETTING];
  if (typeof value === "string") return value;
  if (value && typeof value === "object" && !Array.isArray(value)) {
    return JSON.stringify(value, null, 2);
  }
  return "";
};

/** Effective mode: explicit setting if present, else inferred (has form id). */
const resolveMode = (settings: Record<string, unknown>): QuestionnaireMode => {
  const stored = settings[MODE_SETTING];
  if (stored === "direct" || stored === "jotform") return stored;
  return settingString(settings, FORM_ID_SETTING).trim() ? "jotform" : "direct";
};

interface ProviderPatientQuestionnaireProps {
  provider: ProviderIntegration;
  jotformApiUrl: string;
  jotformDefaultWebhookUrl: string | null;
  shouldShowJotformSetupWarning: boolean;
  shouldShowJotformDefaultWebhookWarning: boolean;
  onPatch: (
    patch: Record<string, unknown | null>,
  ) => Promise<unknown>;
  isSaving: boolean;
}

function ProviderPatientQuestionnaire({
  provider,
  jotformApiUrl,
  jotformDefaultWebhookUrl,
  shouldShowJotformSetupWarning,
  shouldShowJotformDefaultWebhookWarning,
  onPatch,
  isSaving,
}: ProviderPatientQuestionnaireProps) {
  const [mode, setMode] = useState<QuestionnaireMode>(
    resolveMode(provider.settings),
  );
  const [definitionDraft, setDefinitionDraft] = useState(
    settingDefinitionText(provider.settings),
  );
  const [formIdDraft, setFormIdDraft] = useState(
    settingString(provider.settings, FORM_ID_SETTING),
  );

  // Re-seed when the persisted settings change (load / external save).
  useEffect(() => {
    setMode(resolveMode(provider.settings));
    setDefinitionDraft(settingDefinitionText(provider.settings));
    setFormIdDraft(settingString(provider.settings, FORM_ID_SETTING));
  }, [provider.settings]);

  const changeMode = (next: QuestionnaireMode) => {
    if (next === mode) return;
    setMode(next);
    // Persist the explicit mode immediately; keep both fields' stored values.
    void onPatch({ [MODE_SETTING]: next });
  };

  const definitionSave = useMutation({
    mutationFn: async () => {
      const text = definitionDraft.trim();
      if (!text) {
        // Clear the definition.
        await onPatch({ [DEFINITION_SETTING]: null });
        return "cleared" as const;
      }
      let parsed: unknown;
      try {
        parsed = JSON.parse(text);
      } catch {
        throw new Error("Patient Questionnaire Definition must be valid JSON");
      }
      if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
        throw new Error("Patient Questionnaire Definition must be a JSON object");
      }
      await onPatch({ [DEFINITION_SETTING]: parsed, [MODE_SETTING]: "direct" });
      return "saved" as const;
    },
    onSuccess: (result) =>
      toast.success(
        result === "cleared"
          ? "Questionnaire definition cleared"
          : "Questionnaire definition saved",
      ),
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : "Failed to save definition"),
  });

  const formIdSave = useMutation({
    mutationFn: async () => {
      const formId = formIdDraft.trim();
      if (!formId) {
        await onPatch({ [FORM_ID_SETTING]: null });
        return "cleared" as const;
      }
      if (formId.length > 128) {
        throw new Error("Jotform form ID must be 128 characters or less");
      }
      const validated = await validateJotformQuestionnaireForm({
        tenantIntegrationId: provider.id,
        formId,
      });
      await onPatch({ [FORM_ID_SETTING]: validated, [MODE_SETTING]: "jotform" });
      setFormIdDraft(validated);
      return "saved" as const;
    },
    onSuccess: (result) =>
      toast.success(
        result === "cleared"
          ? "Jotform form ID cleared"
          : "Patient questionnaire Jotform ID saved",
      ),
    onError: (e) =>
      toast.error(e instanceof Error ? e.message : "Failed to save Jotform ID"),
  });

  const normalizedFormId = formIdDraft.trim();
  const busy = isSaving || definitionSave.isPending || formIdSave.isPending;

  return (
    <div className="space-y-4 rounded-lg border p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm font-medium">{provider.name}</p>
        {/* Direct | Jotform toggle (explicit, persisted). */}
        <div className="inline-flex rounded-md border p-0.5">
          {(["jotform", "direct"] as QuestionnaireMode[]).map((m) => (
            <button
              key={m}
              type="button"
              disabled={busy || mode === m}
              onClick={() => changeMode(m)}
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
      </div>

      {mode === "direct"
        ? (
          <div className="space-y-2">
            <Label htmlFor={`patient-definition-${provider.id}`}>
              Patient Questionnaire Definition (JSON)
            </Label>
            <p className="text-xs text-muted-foreground">
              Sent to {provider.name} natively. Must be a JSON object.
            </p>
            <Textarea
              id={`patient-definition-${provider.id}`}
              value={definitionDraft}
              onChange={(e) => setDefinitionDraft(e.target.value)}
              rows={10}
              placeholder={'{\n  "questionnaire": "definition"\n}'}
              className="font-mono text-xs"
            />
            <div className="flex gap-2">
              <Button
                size="sm"
                onClick={() => definitionSave.mutate()}
                disabled={busy}
              >
                {definitionSave.isPending
                  ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                  : <Save className="mr-1 h-4 w-4" />}
                Save
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setDefinitionDraft("");
                  definitionSave.mutate();
                }}
                disabled={busy || !settingDefinitionText(provider.settings)}
              >
                <Trash2 className="mr-1 h-4 w-4" /> Clear
              </Button>
            </div>
          </div>
        )
        : (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Label htmlFor={`patient-jotform-${provider.id}`}>
                Patient Questionnaire Jotform ID
              </Label>
              <JotformHiddenFieldsHelp questionnaireType="patient_questionnaire" />
              {shouldShowJotformSetupWarning ? <JotformSetupWarning /> : null}
              {shouldShowJotformDefaultWebhookWarning
                ? <JotformDefaultWebhookWarning />
                : null}
              {normalizedFormId
                ? (
                  <JotformWebhookStatusControl
                    tenantIntegrationId={provider.id}
                    formId={normalizedFormId}
                    defaultWebhookUrl={jotformDefaultWebhookUrl}
                    apiUrl={jotformApiUrl}
                    previewLabel={`Preview ${provider.name} patient questionnaire in Jotform`}
                    editLabel={`Edit ${provider.name} patient questionnaire in Jotform`}
                    showActions={false}
                  />
                )
                : null}
            </div>
            <div className="flex gap-2">
              <Input
                id={`patient-jotform-${provider.id}`}
                value={formIdDraft}
                onChange={(e) => setFormIdDraft(e.target.value)}
                maxLength={128}
                placeholder="Enter the Jotform form ID"
              />
              {normalizedFormId
                ? (
                  <JotformWebhookStatusControl
                    tenantIntegrationId={provider.id}
                    formId={normalizedFormId}
                    defaultWebhookUrl={jotformDefaultWebhookUrl}
                    apiUrl={jotformApiUrl}
                    previewLabel={`Preview ${provider.name} patient questionnaire in Jotform`}
                    editLabel={`Edit ${provider.name} patient questionnaire in Jotform`}
                    showStatus={false}
                    reserveActionSlots
                  />
                )
                : null}
            </div>
            <div className="flex gap-2">
              <Button
                size="sm"
                onClick={() => formIdSave.mutate()}
                disabled={busy}
              >
                {formIdSave.isPending
                  ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                  : <Save className="mr-1 h-4 w-4" />}
                Save &amp; validate
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setFormIdDraft("");
                  formIdSave.mutate();
                }}
                disabled={busy || !settingString(provider.settings, FORM_ID_SETTING)}
              >
                <Trash2 className="mr-1 h-4 w-4" /> Clear
              </Button>
            </div>
          </div>
        )}
    </div>
  );
}

export function PatientQuestionnaires() {
  const { currentTenantId } = useAuth();
  const { providers, isLoading, updateProviderSettings } =
    useProviderIntegrations();
  const {
    apiUrl: jotformApiUrl,
    defaultWebhookUrl: jotformDefaultWebhookUrl,
    hasDefaultWebhookUrl,
    isConfigured: isJotformConfigured,
    isLoading: isJotformStatusLoading,
  } = useJotformIntegrationStatus(currentTenantId);

  const shouldShowJotformSetupWarning = Boolean(currentTenantId) &&
    !isJotformStatusLoading && !isJotformConfigured;
  const shouldShowJotformDefaultWebhookWarning = Boolean(currentTenantId) &&
    !isJotformStatusLoading && isJotformConfigured && !hasDefaultWebhookUrl;

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        The patient intake questionnaire is configured per provider. Choose{" "}
        <strong>Direct</strong> to send answers to the provider natively (configure
        the questionnaire definition), or <strong>Jotform</strong> to collect answers
        through our Jotform integration (set a form ID). The Jotform connection
        (API key / workspace / default webhook) lives on the Connection tab.
      </p>

      {isLoading
        ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        )
        : providers.length === 0
        ? (
          <p className="text-sm text-muted-foreground">
            No provider platforms are enabled yet. Enable a provider under{" "}
            <Link
              to="/tenant-admin/settings/providers"
              className="font-medium text-primary hover:underline"
            >
              Settings → Providers
            </Link>{" "}
            to configure its patient questionnaire.
          </p>
        )
        : (
          <>
            {providers.map((provider) => (
              <ProviderPatientQuestionnaire
                key={provider.id}
                provider={provider}
                jotformApiUrl={jotformApiUrl}
                jotformDefaultWebhookUrl={jotformDefaultWebhookUrl || null}
                shouldShowJotformSetupWarning={shouldShowJotformSetupWarning}
                shouldShowJotformDefaultWebhookWarning={shouldShowJotformDefaultWebhookWarning}
                isSaving={updateProviderSettings.isPending}
                onPatch={(patch) =>
                  updateProviderSettings.mutateAsync({
                    tenantIntegrationId: provider.id,
                    patch,
                  })}
              />
            ))}
            <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
              Only providers enabled for your tenant appear here. To add another,
              enable it under{" "}
              <Link
                to="/tenant-admin/settings/providers"
                className="font-medium text-primary hover:underline"
              >
                Settings → Providers
              </Link>
              .
            </div>
          </>
        )}
    </div>
  );
}
