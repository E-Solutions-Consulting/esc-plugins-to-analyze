/**
 * Validate a Jotform questionnaire form id against the provider-platform-bridge.
 * Shared by the questionnaire editors (patient + medical) so the validation call
 * lives in one place. Returns the normalized form id, or throws with a message.
 */
import { supabase } from "@/integrations/supabase/client";

export async function validateJotformQuestionnaireForm(params: {
  tenantIntegrationId: string;
  formId: string;
}): Promise<string> {
  const { tenantIntegrationId, formId } = params;
  const {
    data: { session },
  } = await supabase.auth.getSession();

  if (!session?.access_token) {
    throw new Error("You must be signed in to validate Jotform forms");
  }

  const validationResponse = await fetch(
    `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/provider-platform-bridge/jotform-form-validation`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${session.access_token}`,
        apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ tenantIntegrationId, formId }),
    },
  );

  const validationResult = (await validationResponse.json().catch(() => null)) as
    | { message?: string; formId?: string; lookupUrl?: string }
    | null;

  if (!validationResponse.ok) {
    const lookupDetail = validationResult?.lookupUrl
      ? ` Lookup URL: ${validationResult.lookupUrl}`
      : "";
    throw new Error(
      `${validationResult?.message || "Jotform form validation failed"}${lookupDetail}`,
    );
  }

  return validationResult?.formId?.trim() || formId;
}
