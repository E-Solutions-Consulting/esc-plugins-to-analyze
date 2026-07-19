// Shared engine + helpers for Communications Automations edge functions.
// See docs/CommunicationsAutomations.md.
//
// Kept dependency-free (no supabase-js import here) so it can be unit-tested and
// reused across comms-* functions. The supabase client is passed in.

// ---------------------------------------------------------------------------
// Types (mirror the comms_ DB schema; intentionally loose for edge runtime)
// ---------------------------------------------------------------------------
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

export interface CommsNode {
  id: string;
  automation_id: string;
  tenant_id: string;
  node_type: CommsNodeType;
  config: Record<string, unknown>;
  position: { x: number; y: number };
}

export interface CommsEdge {
  id: string;
  automation_id: string;
  source_node_id: string;
  target_node_id: string;
  branch_label: string | null;
  sort_order: number;
}

export interface CommsAutomation {
  id: string;
  tenant_id: string;
  name: string;
  status: string;
  trigger_config: Record<string, unknown>;
  settings: Record<string, unknown>;
}

// ---------------------------------------------------------------------------
// Placeholder rendering — {{patient.first_name}}, {{subscription.renewal_date}} …
// ---------------------------------------------------------------------------

/** Safely resolve a dotted path (e.g. "patient.first_name") from a context object. */
export function resolvePath(ctx: unknown, path: string): unknown {
  return path.split(".").reduce<unknown>((acc, key) => {
    if (acc && typeof acc === "object" && key in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[key];
    }
    return undefined;
  }, ctx);
}

/** Replace {{ a.b.c }} tokens in a template string from the context. Missing -> "". */
export function renderTemplate(tpl: string, ctx: Record<string, unknown>): string {
  if (!tpl) return "";
  return tpl.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (_, key: string) => {
    const value = resolvePath(ctx, key);
    if (value === undefined || value === null) return "";
    return String(value);
  });
}

/** Extract the distinct placeholder keys referenced by a template string. */
export function extractPlaceholders(tpl: string): string[] {
  const keys = new Set<string>();
  for (const m of tpl.matchAll(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g)) {
    keys.add(m[1]);
  }
  return [...keys];
}

/** Minimal HTML escaping for values interpolated into an email body. */
export function escapeHtml(value: string): string {
  return value.replace(/[<>&"']/g, (c) =>
    ({ "<": "&lt;", ">": "&gt;", "&": "&amp;", '"': "&quot;", "'": "&#39;" }[c] || c)
  );
}

// ---------------------------------------------------------------------------
// Derived/computed placeholder fields (days_until_renewal, total_usd, etc.)
// Given the raw row context, enrich with friendly derived fields.
// ---------------------------------------------------------------------------
function daysBetween(from: Date, to: Date): number {
  const ms = to.getTime() - from.getTime();
  return Math.round(ms / (1000 * 60 * 60 * 24));
}

export function enrichContext(
  ctx: Record<string, unknown>,
  now: Date = new Date(),
): Record<string, unknown> {
  const out: Record<string, unknown> = { ...ctx };

  const sub = ctx.subscription as Record<string, unknown> | undefined;
  if (sub) {
    const enrichedSub = { ...sub };
    if (sub.current_period_end_at) {
      const renewal = new Date(String(sub.current_period_end_at));
      enrichedSub.renewal_date = renewal.toISOString().slice(0, 10);
      enrichedSub.days_until_renewal = daysBetween(now, renewal);
    }
    if (sub.started_at) {
      enrichedSub.days_since_start = daysBetween(new Date(String(sub.started_at)), now);
    }
    out.subscription = enrichedSub;
  }

  const order = ctx.order as Record<string, unknown> | undefined;
  if (order) {
    const enrichedOrder = { ...order };
    if (typeof order.total_cents === "number") {
      enrichedOrder.total_usd = (order.total_cents / 100).toFixed(2);
    }
    if (order.created_at) {
      enrichedOrder.days_since_order = daysBetween(new Date(String(order.created_at)), now);
    }
    out.order = enrichedOrder;
  }

  return out;
}

// ---------------------------------------------------------------------------
// Branch condition evaluation — small, safe comparison DSL (no eval).
// config.condition = { field: "subscription.status", op: "eq", value: "active" }
// ---------------------------------------------------------------------------
export type ConditionOp =
  | "eq" | "neq" | "gt" | "gte" | "lt" | "lte"
  | "contains" | "exists" | "not_exists";

export interface BranchCondition {
  field: string;
  op: ConditionOp;
  value?: unknown;
}

export function evaluateCondition(
  cond: BranchCondition,
  ctx: Record<string, unknown>,
): boolean {
  const actual = resolvePath(ctx, cond.field);
  switch (cond.op) {
    case "exists":
      return actual !== undefined && actual !== null;
    case "not_exists":
      return actual === undefined || actual === null;
    case "eq":
      return String(actual) === String(cond.value);
    case "neq":
      return String(actual) !== String(cond.value);
    case "contains":
      return String(actual ?? "").includes(String(cond.value ?? ""));
    case "gt":
    case "gte":
    case "lt":
    case "lte": {
      const a = Number(actual);
      const b = Number(cond.value);
      if (Number.isNaN(a) || Number.isNaN(b)) return false;
      if (cond.op === "gt") return a > b;
      if (cond.op === "gte") return a >= b;
      if (cond.op === "lt") return a < b;
      return a <= b;
    }
    default:
      return false;
  }
}

// ---------------------------------------------------------------------------
// Graph traversal — find the next node id given the current node + outcome.
// ---------------------------------------------------------------------------

export function findTriggerNode(nodes: CommsNode[]): CommsNode | undefined {
  return nodes.find((n) => n.node_type === "trigger");
}

/**
 * Resolve the next node to run after `currentNodeId`.
 * `branchOutcome` selects an edge by branch_label (for branch/multi_split nodes).
 * Returns the target node id, or null if there is no outgoing edge (end of flow).
 */
export function nextNodeId(
  edges: CommsEdge[],
  currentNodeId: string,
  branchOutcome?: string | null,
): string | null {
  const outgoing = edges
    .filter((e) => e.source_node_id === currentNodeId)
    .sort((a, b) => a.sort_order - b.sort_order);
  if (outgoing.length === 0) return null;

  if (branchOutcome != null) {
    const labelled = outgoing.find((e) => e.branch_label === branchOutcome);
    if (labelled) return labelled.target_node_id;
    // Fall through to an unlabelled "default" edge if present.
    const fallback = outgoing.find((e) => e.branch_label == null);
    return fallback ? fallback.target_node_id : null;
  }

  return outgoing[0].target_node_id;
}

// ---------------------------------------------------------------------------
// Delay computation — config: { amount: number, unit: 'minutes'|'hours'|'days' }
// ---------------------------------------------------------------------------
export function computeDelayRunAt(
  config: Record<string, unknown>,
  from: Date = new Date(),
): Date {
  const amount = Number(config.amount ?? 0);
  const unit = String(config.unit ?? "days");
  const multipliers: Record<string, number> = {
    minutes: 60 * 1000,
    hours: 60 * 60 * 1000,
    days: 24 * 60 * 60 * 1000,
  };
  const ms = amount * (multipliers[unit] ?? multipliers.days);
  return new Date(from.getTime() + ms);
}

// ---------------------------------------------------------------------------
// Mask helpers (avoid persisting raw PHI in run-step metadata)
// ---------------------------------------------------------------------------
export function maskEmail(email: string): string {
  const [local = "", domain = ""] = email.trim().toLowerCase().split("@");
  if (!domain) return "***";
  if (local.length <= 2) return `${local.slice(0, 1) || "*"}***@${domain}`;
  return `${local.slice(0, 2)}***@${domain}`;
}

export function maskPhone(phone: string): string {
  const digits = phone.replace(/\D/g, "");
  if (digits.length < 4) return "***";
  return `***${digits.slice(-4)}`;
}
