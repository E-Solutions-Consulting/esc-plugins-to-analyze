/** Run activity for an automation: enrollment stats + recent enrollments + per-node steps. */
import { useEffect, useState } from "react";
import { ChevronRight, ExternalLink, Loader2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import {
  useAutomationEnrollments,
  useAutomationStats,
  useEnrollmentSteps,
  type CommsEnrollment,
  type CommsRunStep,
} from "@/hooks/useCommsAutomations";

const ENROLLMENT_VARIANT: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  active: "secondary",
  completed: "default",
  exited: "outline",
  failed: "destructive",
  cancelled: "outline",
};

const STEP_VARIANT: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  sent: "default",
  scheduled: "secondary",
  skipped: "outline",
  failed: "destructive",
  pending: "secondary",
};

function patientLabel(e: CommsEnrollment): string {
  const p = e.patients;
  if (p?.first_name || p?.last_name) return `${p.first_name ?? ""} ${p.last_name ?? ""}`.trim();
  return p?.email ?? (e.patient_id ? e.patient_id.slice(0, 8) : "Unknown");
}

/**
 * Raw run-step error codes are meaningless to a tenant admin. Translate the ones
 * with a known cause AND a known fix — an n8n 404 means exactly one thing.
 */
const STEP_ERROR_HINTS: Record<string, string> = {
  n8n_status_404:
    "Webhook not registered — the n8n workflow is not active. Activate it in the n8n step.",
  no_n8n_webhook: "No n8n workflow is connected to this step.",
  no_recipient: "The patient has no email address.",
  no_phone: "The patient has no phone number.",
};

function stepErrorLabel(error: string): string {
  if (STEP_ERROR_HINTS[error]) return STEP_ERROR_HINTS[error];
  // Test-mode failures: n8n only answers the test url while "Listen for test
  // event" is armed, so a 404 here means nobody was listening.
  if (error.startsWith("n8n_test_")) {
    return "n8n test URL did not answer — click “Listen for test event” in n8n, then fire the test again.";
  }
  return error;
}

/** Deep-link to the n8n run this step produced (or the workflow's run list). */
function n8nRunUrl(s: CommsRunStep): string | null {
  const baseUrl = s.metadata?.base_url ? String(s.metadata.base_url) : null;
  const workflowId = s.metadata?.workflow_id ? String(s.metadata.workflow_id) : null;
  if (!baseUrl || !workflowId) return null;
  // n8n's webhook responds with `{"message":"Workflow was started"}` and NO
  // executionId under the default responseMode, so provider_message_id is usually
  // null. Fall back to the workflow's execution list — newest run is at the top.
  return s.provider_message_id
    ? `${baseUrl}/workflow/${workflowId}/executions/${s.provider_message_id}`
    : `${baseUrl}/workflow/${workflowId}/executions`;
}

function StepList({ steps }: { steps: CommsRunStep[] }) {
  if (steps.length === 0) {
    return <p className="px-4 py-2 text-xs text-muted-foreground">No steps recorded yet.</p>;
  }
  return (
    <ul className="space-y-1 px-4 py-2">
      {steps.map((s) => {
        const runUrl = s.node_type === "n8n" ? n8nRunUrl(s) : null;
        return (
          <li key={s.id} className="flex flex-wrap items-center gap-2 text-xs">
            <Badge variant={STEP_VARIANT[s.status] ?? "secondary"} className="capitalize">{s.status}</Badge>
            <span className="font-medium">{s.node_type}</span>
            {s.metadata?.test_mode ? <Badge variant="outline">test url</Badge> : null}
            {s.metadata?.to ? <span className="text-muted-foreground">→ {String(s.metadata.to)}</span> : null}
            {s.delivery_status ? (
              <Badge variant="outline" className="capitalize">{s.delivery_status}</Badge>
            ) : null}
            {runUrl ? (
              <a
                href={runUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1 text-primary hover:underline"
              >
                View run in n8n <ExternalLink className="h-3 w-3" />
              </a>
            ) : null}
            {s.error ? <span className="text-destructive">{stepErrorLabel(s.error)}</span> : null}
            <span className="ml-auto text-muted-foreground">
              {new Date(s.created_at).toLocaleString()}
            </span>
          </li>
        );
      })}
    </ul>
  );
}

function EnrollmentRow({ enrollment }: { enrollment: CommsEnrollment }) {
  // A failed enrollment is the thing the user came here to see — don't bury the
  // reason behind a click.
  const failed = enrollment.status === "failed" || !!enrollment.last_error;
  const [open, setOpen] = useState(failed);
  const [steps, setSteps] = useState<CommsRunStep[] | null>(null);
  const stepsMutation = useEnrollmentSteps();
  const loadSteps = stepsMutation.mutateAsync;

  // Load steps whenever the row is open — including on mount for a failed one, and
  // again as the enrollment progresses (the panel polls, and a run that is still
  // walking its nodes would otherwise show a frozen first snapshot).
  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    loadSteps(enrollment.id)
      .then((res) => { if (!cancelled) setSteps(res); })
      .catch(() => { /* transient; the next poll retries */ });
    return () => { cancelled = true; };
  }, [open, enrollment.id, enrollment.status, enrollment.current_node_id, loadSteps]);

  const toggle = () => setOpen((v) => !v);

  return (
    <div className="rounded-md border">
      <button
        type="button"
        onClick={toggle}
        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
      >
        <ChevronRight className={cn("h-4 w-4 transition-transform", open && "rotate-90")} />
        <span className="font-medium">{patientLabel(enrollment)}</span>
        <Badge variant={ENROLLMENT_VARIANT[enrollment.status] ?? "secondary"} className="capitalize">
          {enrollment.status}
        </Badge>
        <span className="ml-auto text-xs text-muted-foreground">
          {new Date(enrollment.enrolled_at).toLocaleString()}
        </span>
      </button>
      {open && (
        <div className="border-t bg-muted/30">
          {stepsMutation.isPending && steps === null ? (
            <div className="flex items-center gap-2 px-4 py-2 text-xs text-muted-foreground">
              <Loader2 className="h-3.5 w-3.5 animate-spin" /> Loading steps…
            </div>
          ) : (
            <StepList steps={steps ?? []} />
          )}
        </div>
      )}
    </div>
  );
}

export function RunActivityPanel({ automationId }: { automationId: string }) {
  const { data: stats } = useAutomationStats(automationId);
  const { data: enrollments = [], isLoading, refetch, isRefetching } =
    useAutomationEnrollments(automationId);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Badge variant="outline">Total: {stats?.total ?? 0}</Badge>
        {Object.entries(stats?.counts ?? {}).map(([status, n]) => (
          <Badge key={status} variant={ENROLLMENT_VARIANT[status] ?? "secondary"} className="capitalize">
            {status}: {n}
          </Badge>
        ))}
        <Button variant="ghost" size="sm" className="ml-auto" onClick={() => refetch()} disabled={isRefetching}>
          {isRefetching ? <Loader2 className="h-4 w-4 animate-spin" /> : "Refresh"}
        </Button>
      </div>

      {isLoading ? (
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      ) : enrollments.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          No enrollments yet. When the trigger fires, patients enrolled here show with their per-step log.
        </p>
      ) : (
        <div className="space-y-2">
          {enrollments.map((e) => (
            <EnrollmentRow key={e.id} enrollment={e} />
          ))}
        </div>
      )}
    </div>
  );
}
