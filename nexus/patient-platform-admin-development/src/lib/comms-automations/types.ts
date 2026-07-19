/** Communications Automations — frontend types (mirror the comms_ DB schema). */

import type { TriggerKind } from "./catalog";

export type CommsAutomationStatus = "draft" | "active" | "paused" | "archived";

export type CommsNodeType =
  | "trigger"
  | "email"
  | "sms"
  | "delay"
  | "wait_until"
  | "branch"
  | "multi_split"
  | "n8n"
  | "exit";

export type CommsChannel = "email" | "sms";

export interface TriggerConfig {
  kind: TriggerKind;
  // event
  event_name?: string;
  /**
   * Canonical platform event key (e.g. "order.paid", "subscription.renewed") —
   * the SAME vocabulary Outbound Webhooks use. Preferred for kind 'order' and
   * 'subscription'. See src/lib/platform-events.ts.
   */
  event_key?: string;
  // subscription — raw subscription_events.event_type (advanced / legacy).
  event_type?: string;
  /**
   * Raw order_statuses.status_key (advanced / legacy). Still honoured by the
   * dispatcher so automations saved before named events keep firing; the builder
   * writes event_key for new order triggers and exposes this only under Advanced.
   */
  to_status?: string;
  // relative_time
  anchor?: "renewal" | "purchase" | "order_shipped" | "order_delivered";
  direction?: "before" | "after";
  offset_days?: number;
  // optional property filters
  filters?: Record<string, unknown>;
}

export interface CommsAutomation {
  id: string;
  tenant_id: string;
  name: string;
  description: string | null;
  status: CommsAutomationStatus;
  trigger_config: TriggerConfig;
  settings: Record<string, unknown>;
  version: number;
  enrolled_count: number;
  last_triggered_at: string | null;
  n8n_folder_id: string | null;
  n8n_folder_name: string | null;
  created_at: string;
  updated_at: string;
}

export interface NodePosition {
  x: number;
  y: number;
}

/** Per-node config; shape depends on node_type. Kept permissive. */
export interface CommsNodeConfig {
  // email
  subject?: string;
  html?: string;
  body?: string;
  template_id?: string;
  to?: string;
  // delay
  amount?: number;
  unit?: "minutes" | "hours" | "days";
  until?: string;
  // branch
  condition?: { field: string; op: string; value?: unknown };
  // multi_split
  cohort_field?: string;
  // n8n
  webhook_id?: string;
  webhook_url?: string;
  /**
   * Send to n8n's TEST url (/webhook-test/<path>) instead of production. For
   * authoring only: the test url accepts ONE call and only while the n8n editor
   * has "Listen for test event" armed, so it can never serve real traffic.
   */
  test_mode?: boolean;
  // trigger (mirrors TriggerConfig for the trigger node card)
  trigger?: TriggerConfig;
  [key: string]: unknown;
}

export interface CommsNode {
  id: string;
  automation_id?: string;
  tenant_id?: string;
  node_type: CommsNodeType;
  config: CommsNodeConfig;
  position: NodePosition;
}

export interface CommsEdge {
  id?: string;
  automation_id?: string;
  tenant_id?: string;
  source_node_id: string;
  target_node_id: string;
  branch_label: string | null;
  sort_order: number;
}

export interface CommsTemplate {
  id: string;
  tenant_id: string;
  channel: CommsChannel;
  name: string;
  subject: string | null;
  body: string;
  placeholders: string[];
  is_archived: boolean;
  created_at: string;
  updated_at: string;
}

export interface AutomationGraph {
  automation: CommsAutomation;
  nodes: CommsNode[];
  edges: CommsEdge[];
}

// --- n8n ---
export type N8nMode = "projects" | "webhook";

export interface CommsN8nConnection {
  id: string;
  tenant_id: string;
  mode: N8nMode;
  n8n_project_id: string | null;
  base_url: string | null;
  api_key_secret_ref: string | null;
  is_connected: boolean;
  last_synced_at: string | null;
}

export interface CommsN8nWebhook {
  id: string;
  tenant_id: string;
  name: string;
  n8n_workflow_id: string | null;
  /** PRODUCTION url (<host>/webhook/<path>) — only live while the workflow is active. */
  webhook_url: string;
  /** Raw path segment; the test url is <host>/webhook-test/<path>. */
  webhook_path?: string | null;
  http_method: string;
  auth_secret_ref: string | null;
  graph_cache: unknown | null;
  graph_cached_at: string | null;
  is_active: boolean;
}

export interface N8nWorkflowSummary {
  id: string;
  name: string;
  active: boolean;
}
