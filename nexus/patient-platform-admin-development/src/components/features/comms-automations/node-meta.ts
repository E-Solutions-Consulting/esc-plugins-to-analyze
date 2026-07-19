/** Visual + palette metadata for automation node types. */
import {
  Bell,
  Clock,
  GitBranch,
  LogOut,
  Mail,
  MessageSquare,
  Split,
  Workflow,
  Zap,
  type LucideIcon,
} from "lucide-react";
import type { CommsNodeType } from "@/lib/comms-automations/types";

export interface NodeMeta {
  type: CommsNodeType;
  label: string;
  description: string;
  icon: LucideIcon;
  /** tailwind text color for the icon */
  color: string;
  /** whether it can be added from the palette (trigger is fixed at top) */
  addable: boolean;
  group: "messages" | "delays" | "flow";
}

export const NODE_META: Record<CommsNodeType, NodeMeta> = {
  trigger: {
    type: "trigger",
    label: "Trigger",
    description: "Entry point",
    icon: Zap,
    color: "text-amber-500",
    addable: false,
    group: "flow",
  },
  email: {
    type: "email",
    label: "Email",
    description: "Send an email via Resend",
    icon: Mail,
    color: "text-blue-500",
    addable: true,
    group: "messages",
  },
  sms: {
    type: "sms",
    label: "SMS",
    description: "Send a text via Twilio",
    icon: MessageSquare,
    color: "text-green-600",
    addable: true,
    group: "messages",
  },
  delay: {
    type: "delay",
    label: "Time Delay",
    description: "Wait an amount of time",
    icon: Clock,
    color: "text-orange-500",
    addable: true,
    group: "delays",
  },
  wait_until: {
    type: "wait_until",
    label: "Wait Until",
    description: "Wait until a specific time",
    icon: Bell,
    color: "text-orange-400",
    addable: true,
    group: "delays",
  },
  branch: {
    type: "branch",
    label: "True/False Branch",
    description: "Split on a condition",
    icon: GitBranch,
    color: "text-purple-500",
    addable: true,
    group: "flow",
  },
  multi_split: {
    type: "multi_split",
    label: "Multi-Split",
    description: "Split into cohorts",
    icon: Split,
    color: "text-purple-400",
    addable: true,
    group: "flow",
  },
  n8n: {
    type: "n8n",
    label: "n8n Flow",
    description: "Hand off to an n8n workflow",
    icon: Workflow,
    color: "text-rose-500",
    addable: true,
    group: "flow",
  },
  exit: {
    type: "exit",
    label: "Exit",
    description: "End the journey",
    icon: LogOut,
    color: "text-muted-foreground",
    addable: true,
    group: "flow",
  },
};

export const PALETTE_GROUPS: { label: string; key: NodeMeta["group"] }[] = [
  { label: "Messages", key: "messages" },
  { label: "Delays", key: "delays" },
  { label: "Flow Control", key: "flow" },
];

/** Short human summary of a node's current config, shown on the canvas card. */
export function nodeSummary(type: CommsNodeType, config: Record<string, unknown>): string {
  switch (type) {
    case "email":
      return config.subject ? String(config.subject) : "No subject set";
    case "sms":
      return config.body ? String(config.body).slice(0, 40) : "No message set";
    case "delay": {
      const amount = config.amount ?? 1;
      const unit = config.unit ?? "days";
      return `Wait ${amount} ${unit}`;
    }
    case "wait_until":
      return config.until ? `Until ${config.until}` : "No time set";
    case "branch": {
      const cond = config.condition as { field?: string; op?: string; value?: unknown } | undefined;
      return cond?.field ? `${cond.field} ${cond.op} ${cond.value ?? ""}` : "No condition set";
    }
    case "multi_split":
      return config.cohort_field ? `Split by ${config.cohort_field}` : "No split field";
    case "n8n":
      return config.webhook_id || config.webhook_url ? "n8n workflow selected" : "No workflow selected";
    case "exit":
      return "Ends the journey";
    default:
      return "";
  }
}
