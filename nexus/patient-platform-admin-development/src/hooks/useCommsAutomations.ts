/**
 * React Query hooks for Communications Automations.
 * All calls go through the comms-automation-admin edge function, which enforces
 * JWT + tenant membership. Tenant scoping comes from the current auth tenant.
 */
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import type {
  AutomationGraph,
  CommsAutomation,
  CommsEdge,
  CommsNode,
  CommsTemplate,
} from "@/lib/comms-automations/types";

const FN = "comms-automation-admin";

async function call<T>(action: string, tenantId: string, payload: Record<string, unknown> = {}): Promise<T> {
  const { data, error } = await supabase.functions.invoke<T>(FN, {
    body: { action, tenant_id: tenantId, ...payload },
  });
  if (error) throw new Error(error.message);
  if (!data) throw new Error("No response from comms-automation-admin");
  return data;
}

export function useCommsAutomations() {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-automations", currentTenantId],
    queryFn: async () => {
      const res = await call<{ automations: CommsAutomation[] }>("list_automations", currentTenantId!);
      return res.automations;
    },
    enabled: !!currentTenantId,
  });
}

export function useCommsAutomation(automationId: string | undefined) {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-automation", currentTenantId, automationId],
    queryFn: async () =>
      await call<AutomationGraph>("get_automation", currentTenantId!, { automation_id: automationId }),
    enabled: !!currentTenantId && !!automationId,
  });
}

export interface TriggerCatalog {
  order_statuses: { key: string; label: string }[];
  event_names: { key: string; category: string | null; description: string | null }[];
  subscription_event_types: string[];
}

/** Real, data-driven trigger options (order statuses + analytics events from their
 *  catalog tables; subscription event types canonical). Keeps the trigger UI in
 *  sync with the platform instead of a stale hardcoded list. */
export function useTriggerCatalog() {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-trigger-catalog", currentTenantId],
    queryFn: async () => await call<TriggerCatalog>("trigger_catalog", currentTenantId!),
    enabled: !!currentTenantId,
    staleTime: 5 * 60 * 1000,
  });
}

export function useCreateAutomation() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { name: string; description?: string; trigger_config?: unknown }) => {
      const res = await call<{ automation: CommsAutomation }>("create_automation", currentTenantId!, input);
      return res.automation;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-automations", currentTenantId] }),
  });
}

export function useUpdateAutomation() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { automation_id: string } & Partial<CommsAutomation>) => {
      const res = await call<{ automation: CommsAutomation }>("update_automation", currentTenantId!, input);
      return res.automation;
    },
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ["comms-automations", currentTenantId] });
      qc.invalidateQueries({ queryKey: ["comms-automation", currentTenantId, vars.automation_id] });
    },
  });
}

export function useDeleteAutomation() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (automationId: string) =>
      await call<{ ok: boolean }>("delete_automation", currentTenantId!, { automation_id: automationId }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-automations", currentTenantId] }),
  });
}

export function useSaveGraph() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { automation_id: string; nodes: CommsNode[]; edges: CommsEdge[] }) =>
      await call<{ ok: boolean }>("save_graph", currentTenantId!, input),
    onSuccess: (_d, vars) =>
      qc.invalidateQueries({ queryKey: ["comms-automation", currentTenantId, vars.automation_id] }),
  });
}

export function useCommsTemplates() {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-templates", currentTenantId],
    queryFn: async () => {
      const res = await call<{ templates: CommsTemplate[] }>("list_templates", currentTenantId!);
      return res.templates;
    },
    enabled: !!currentTenantId,
  });
}

export function useUpsertTemplate() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (template: Partial<CommsTemplate>) => {
      const res = await call<{ template: CommsTemplate }>("upsert_template", currentTenantId!, { template });
      return res.template;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-templates", currentTenantId] }),
  });
}

export function useDeleteTemplate() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (templateId: string) =>
      await call<{ ok: boolean }>("delete_template", currentTenantId!, { template_id: templateId }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-templates", currentTenantId] }),
  });
}

// --- Run activity ---

export interface CommsEnrollment {
  id: string;
  patient_id: string | null;
  status: string;
  current_node_id: string | null;
  enrolled_at: string;
  completed_at: string | null;
  last_error: string | null;
  patients?: { first_name?: string; last_name?: string; email?: string } | null;
}

export interface CommsRunStep {
  id: string;
  node_id: string | null;
  node_type: string;
  status: string;
  provider_message_id: string | null;
  error: string | null;
  metadata: Record<string, unknown>;
  delivery_status?: string | null;
  created_at: string;
}

/**
 * The engine runs ASYNCHRONOUSLY (dispatcher -> execute-node -> n8n, chained by
 * fire-and-forget calls), so a run appears and advances over a few seconds. Poll
 * while the Activity view is open — otherwise the first render after "Send test
 * event" is empty and reads as "it didn't work".
 */
const ACTIVITY_POLL_MS = 5000;

export function useAutomationStats(automationId: string | undefined) {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-automation-stats", currentTenantId, automationId],
    queryFn: async () =>
      await call<{ counts: Record<string, number>; total: number }>(
        "automation_stats", currentTenantId!, { automation_id: automationId },
      ),
    enabled: !!currentTenantId && !!automationId,
    refetchInterval: ACTIVITY_POLL_MS,
  });
}

export function useAutomationEnrollments(automationId: string | undefined) {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-enrollments", currentTenantId, automationId],
    queryFn: async () => {
      const res = await call<{ enrollments: CommsEnrollment[] }>(
        "list_enrollments", currentTenantId!, { automation_id: automationId },
      );
      return res.enrollments;
    },
    enabled: !!currentTenantId && !!automationId,
    refetchInterval: ACTIVITY_POLL_MS,
  });
}

export function useEnrollmentSteps() {
  const { currentTenantId } = useAuth();
  return useMutation({
    mutationFn: async (enrollmentId: string) => {
      const res = await call<{ steps: CommsRunStep[] }>(
        "list_run_steps", currentTenantId!, { enrollment_id: enrollmentId },
      );
      return res.steps;
    },
  });
}

export interface TestSendInput {
  channel: "email" | "sms";
  to: string;
  subject?: string;
  body?: string;
  template_id?: string;
  sample_context?: Record<string, unknown>;
}

export function useTestSend() {
  const { currentTenantId } = useAuth();
  return useMutation({
    mutationFn: async (input: TestSendInput) =>
      await call<{ ok: boolean; integration?: string; provider_message_id?: string | null }>(
        "test_send", currentTenantId!, input,
      ),
  });
}

export function useTestTrigger() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (automationId: string) =>
      await call<{ ok: boolean; dispatched: Record<string, unknown>; result: unknown }>(
        "test_trigger", currentTenantId!, { automation_id: automationId },
      ),
    onSuccess: (_d, automationId) => {
      qc.invalidateQueries({ queryKey: ["comms-enrollments", currentTenantId, automationId] });
      qc.invalidateQueries({ queryKey: ["comms-automation-stats", currentTenantId, automationId] });
    },
  });
}

// --- SMS (Twilio) provider config ---

export interface SmsProviderState {
  configured: boolean;
  is_enabled: boolean;
  account_sid: string | null;
  from_number: string | null;
  has_auth_token: boolean;
}

export function useSmsProvider() {
  const { currentTenantId } = useAuth();
  return useQuery({
    queryKey: ["comms-sms-provider", currentTenantId],
    queryFn: async () => await call<SmsProviderState>("get_sms_provider", currentTenantId!),
    enabled: !!currentTenantId,
  });
}

export function useSetSmsProvider() {
  const { currentTenantId } = useAuth();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: {
      account_sid?: string;
      from_number?: string;
      auth_token?: string;
      is_enabled?: boolean;
    }) => await call<{ ok: boolean }>("set_sms_provider", currentTenantId!, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comms-sms-provider", currentTenantId] }),
  });
}
