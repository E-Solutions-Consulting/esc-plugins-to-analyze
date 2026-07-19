import { Button } from "@/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { supabase } from "@/integrations/supabase/client";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle,
  CheckCircle2,
  Eye,
  Loader2,
  Pencil,
  Wrench,
  XCircle,
} from "lucide-react";

export type JotformWebhookStatus =
  | "default_not_configured"
  | "configured"
  | "missing"
  | "inaccessible";

type JotformWebhookStatusResponse = {
  formId?: string;
  defaultWebhookUrl?: string | null;
  webhookStatus?: JotformWebhookStatus;
  hasDefaultWebhook?: boolean;
  added?: boolean;
  message?: string;
};

async function callJotformWebhookEndpoint(params: {
  method: "POST" | "PUT";
  tenantIntegrationId: string;
  formId: string;
}): Promise<JotformWebhookStatusResponse> {
  const {
    data: { session },
  } = await supabase.auth.getSession();

  if (!session?.access_token) {
    throw new Error("You must be signed in to check Jotform webhooks");
  }

  const response = await fetch(
    `${import.meta.env.VITE_SUPABASE_URL}/functions/v1/provider-platform-bridge/jotform-form-webhooks`,
    {
      method: params.method,
      headers: {
        Authorization: `Bearer ${session.access_token}`,
        apikey: import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        tenantIntegrationId: params.tenantIntegrationId,
        formId: params.formId,
      }),
    },
  );

  const result = (await response.json().catch(() => null)) as
    | (JotformWebhookStatusResponse & { message?: string })
    | null;

  if (!response.ok) {
    throw new Error(result?.message || "Unable to check Jotform webhook");
  }

  return result ?? {};
}

export function buildJotformFormLink(params: {
  apiUrl: string;
  formId: string;
}) {
  const { apiUrl, formId } = params;
  const normalizedApiUrl = apiUrl.trim();
  const normalizedFormId = formId.trim();

  if (!normalizedApiUrl || !normalizedFormId) return null;

  try {
    const url = new URL(normalizedApiUrl);
    const pathSegments = url.pathname
      .split("/")
      .map((segment) => segment.trim())
      .filter(Boolean);
    const lastSegment = pathSegments[pathSegments.length - 1]?.toLowerCase();
    const formPathSegments = lastSegment === "api"
      ? pathSegments.slice(0, -1)
      : pathSegments;

    if (url.hostname === "api.jotform.com") {
      url.hostname = "www.jotform.com";
    } else if (url.hostname.endsWith("-api.jotform.com")) {
      url.hostname = url.hostname.replace("-api.jotform.com", ".jotform.com");
    }

    url.pathname = formPathSegments.length > 0
      ? `/${formPathSegments.join("/")}/${encodeURIComponent(normalizedFormId)}`
      : `/${encodeURIComponent(normalizedFormId)}`;
    url.search = "";
    url.hash = "";

    return url.toString();
  } catch {
    return null;
  }
}

export function buildJotformFormEditLink(params: {
  apiUrl: string;
  formId: string;
}) {
  const previewUrl = buildJotformFormLink(params);
  if (!previewUrl) return null;

  try {
    const url = new URL(previewUrl);
    url.pathname = `/build/${encodeURIComponent(params.formId.trim())}`;
    return url.toString();
  } catch {
    return null;
  }
}

export function JotformDefaultWebhookWarning() {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="inline-flex text-amber-600">
          <AlertTriangle className="h-4 w-4" />
        </span>
      </TooltipTrigger>
      <TooltipContent>
        <p className="max-w-xs">
          Default Webhook URL is not configured. Webhook validation and checks
          are suspended for Jotform IDs.
        </p>
      </TooltipContent>
    </Tooltip>
  );
}

function getStatusCopy(status: JotformWebhookStatus | "loading" | "unknown") {
  switch (status) {
    case "configured":
      return "Webhook properly setup.";
    case "missing":
      return "This Jotform exists but is missing the current Default Webhook URL. Click the wrench button to update the webhook on the questionnaire.";
    case "inaccessible":
      return "This Jotform is missing or inaccessible.";
    case "default_not_configured":
      return "Default Webhook URL is not configured. Webhook checks are suspended.";
    case "loading":
      return "Checking Jotform webhook status.";
    default:
      return "Webhook status is unavailable.";
  }
}

export function JotformWebhookStatusControl({
  tenantIntegrationId,
  formId,
  defaultWebhookUrl,
  apiUrl,
  previewLabel = "Open Jotform preview",
  editLabel = "Edit Jotform questionnaire",
  showStatus = true,
  showActions = true,
  reserveActionSlots = false,
}: {
  tenantIntegrationId: string;
  formId: string;
  defaultWebhookUrl: string | null;
  apiUrl: string;
  previewLabel?: string;
  editLabel?: string;
  showStatus?: boolean;
  showActions?: boolean;
  reserveActionSlots?: boolean;
}) {
  const queryClient = useQueryClient();
  const normalizedFormId = formId.trim();
  const previewUrl = buildJotformFormLink({ apiUrl, formId: normalizedFormId });
  const editUrl = buildJotformFormEditLink({
    apiUrl,
    formId: normalizedFormId,
  });
  const checksEnabled = Boolean(defaultWebhookUrl?.trim() && normalizedFormId);
  const queryKey = [
    "jotform-webhook-status",
    tenantIntegrationId,
    normalizedFormId,
    defaultWebhookUrl || "",
  ];

  const statusQuery = useQuery({
    queryKey,
    queryFn: () =>
      callJotformWebhookEndpoint({
        method: "POST",
        tenantIntegrationId,
        formId: normalizedFormId,
      }),
    enabled: checksEnabled,
  });

  const fixMutation = useMutation({
    mutationFn: () =>
      callJotformWebhookEndpoint({
        method: "PUT",
        tenantIntegrationId,
        formId: normalizedFormId,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey });
    },
  });

  const status: JotformWebhookStatus | "loading" | "unknown" = !checksEnabled
    ? "default_not_configured"
    : statusQuery.isLoading
    ? "loading"
    : statusQuery.isError
    ? "inaccessible"
    : statusQuery.data?.hasDefaultWebhook === true
    ? "configured"
    : statusQuery.data?.webhookStatus === "inaccessible"
    ? "inaccessible"
    : statusQuery.data?.webhookStatus === "default_not_configured"
    ? "default_not_configured"
    : statusQuery.data?.webhookStatus
    ? "missing"
    : "unknown";

  const tooltip = statusQuery.isError
    ? statusQuery.error instanceof Error
      ? statusQuery.error.message
      : getStatusCopy("inaccessible")
    : statusQuery.data?.message || getStatusCopy(status);

  return (
    <div className="flex shrink-0 items-center gap-2">
      {showStatus
        ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <span className="inline-flex">
                {status === "configured"
                  ? <CheckCircle2 className="h-4 w-4 text-green-600" />
                  : status === "missing" || status === "default_not_configured"
                  ? <AlertTriangle className="h-4 w-4 text-amber-600" />
                  : status === "loading"
                  ? (
                    <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                  )
                  : <XCircle className="h-4 w-4 text-destructive" />}
              </span>
            </TooltipTrigger>
            <TooltipContent>
              <p className="max-w-xs">{tooltip}</p>
            </TooltipContent>
          </Tooltip>
        )
        : null}

      {showActions && previewUrl
        ? (
          <Button type="button" variant="outline" size="icon" asChild>
            <a
              href={previewUrl}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={previewLabel}
              title={previewLabel}
            >
              <Eye className="h-4 w-4" />
            </a>
          </Button>
        )
        : null}

      {showActions && editUrl
        ? (
          <Button type="button" variant="outline" size="icon" asChild>
            <a
              href={editUrl}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={editLabel}
              title={editLabel}
            >
              <Pencil className="h-4 w-4" />
            </a>
          </Button>
        )
        : null}

      {showActions && status === "missing"
        ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={() => fixMutation.mutate()}
                disabled={fixMutation.isPending}
                aria-label="Fix Jotform webhook"
                title="Fix Jotform webhook"
              >
                {fixMutation.isPending
                  ? <Loader2 className="h-4 w-4 animate-spin" />
                  : <Wrench className="h-4 w-4" />}
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p className="max-w-xs">
                Click the wrench button to add the current Default Webhook URL
                to this questionnaire.
              </p>
            </TooltipContent>
          </Tooltip>
        )
        : showActions && reserveActionSlots
        ? <span className="h-9 w-9 shrink-0" aria-hidden="true" />
        : null}
    </div>
  );
}
